<template>
    <div class="container-fluid px-0 sop-scope sop-master-dokumen">
        <!-- ══ 1. EXECUTIVE HEADER (HCIS SIGNATURE LUMINOUS HEADER) ══ -->
        <HcisHeader
            title="Master Dokumen"
            kicker="Tata Kelola · Arsip Terkendali"
            description="Pusat tata kelola dokumen &amp; versi dokumen terkendali. Siapkan satu master dokumen, pantau penggunaannya di berbagai paket, dan kelola alur pengesahan."
            icon="journal-bookmark-fill"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="!loading"
                    :label="`${total} Master Dokumen`"
                    variant="brand"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        label="Segarkan"
                        :loading="loading"
                        title="Segarkan daftar dokumen"
                        aria-label="Segarkan daftar dokumen"
                        @click="segarkan(terpilih)"
                    />

                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="file-earmark-spreadsheet"
                        label="Impor Excel"
                        title="Impor register dokumen dari Excel"
                        aria-label="Impor register dokumen dari Excel"
                        @click="imporModal = true"
                    />

                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="plus-lg"
                        label="Dokumen Baru"
                        title="Tambah Master Dokumen Baru"
                        aria-label="Tambah Master Dokumen Baru"
                        @click="bukaBuat"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. EXECUTIVE TELEMETRY KPI STATS ══ -->
        <SopMasterDokumenStats
            :total="total"
            :ringkasan="ringkas"
            :loading="loading"
            @filter-all="pilihTapis(null)"
            @filter-attention="pilihTapis('PERHATIAN')"
            @filter-incomplete="pilihTapis('BELUM_LENGKAP')"
        />

        <!-- ══ 3. MOBILE VIEW TOGGLE SWITCHER (Screen <= 991px) ══ -->
        <div class="d-lg-none mb-3 mdx-mobile-nav">
            <div class="sop-seg w-100 shadow-sm">
                <button
                    type="button"
                    class="w-50 justify-content-center"
                    :class="{ active: mobileTab === 'daftar' }"
                    @click="mobileTab = 'daftar'"
                >
                    <i class="bi bi-list-nested me-1"></i> Daftar Dokumen <span class="seg-count">{{ total }}</span>
                </button>
                <button
                    type="button"
                    class="w-50 justify-content-center"
                    :class="{ active: mobileTab === 'detail' }"
                    :disabled="!terpilih"
                    @click="mobileTab = 'detail'"
                >
                    <i class="bi bi-file-text me-1"></i> Rincian Dokumen
                </button>
            </div>
        </div>

        <!-- ══ 4. MASTER-DETAIL 2-COLUMN WORKSPACE ══ -->
        <div class="mb-5 mdx-workspace-grid">
            <!-- ── LEFT COLUMN: SIDEBAR DAFTAR MASTER DOKUMEN ── -->
            <aside class="mdx-sidebar-wrap" :class="{ 'd-none d-lg-block': mobileTab === 'detail' }">
                <div class="mdx-sidebar-card">
                    <!-- 1. Sticky Control Header -->
                    <div class="mdx-sidebar__header">
                        <!-- Top Title & Controls -->
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <i class="bi bi-collection-fill text-primary" style="font-size: 0.95rem;"></i>
                                <span class="fw-bold text-dark text-truncate" style="font-size: 0.875rem;">Daftar Master Dokumen</span>
                                <span class="mdx-header-counter">{{ total }}</span>
                            </div>
                            <button
                                type="button"
                                class="btn btn-link btn-sm p-1 text-muted text-decoration-none d-flex align-items-center flex-shrink-0"
                                title="Muat ulang daftar dokumen"
                                :disabled="loading"
                                @click="segarkan()"
                            >
                                <i class="bi bi-arrow-clockwise" :class="{ 'spin-active': loading }"></i>
                            </button>
                        </div>

                        <!-- Quick Alarm Counter Strip -->
                        <div
                            v-if="(ringkas.PERHATIAN || tapis === 'PERHATIAN') || (ringkas.BELUM_LENGKAP || tapis === 'BELUM_LENGKAP')"
                            class="d-flex align-items-center gap-1.5 flex-wrap mb-2"
                        >
                            <button
                                v-if="ringkas.PERHATIAN || tapis === 'PERHATIAN'"
                                type="button"
                                class="mh-alarm-pill"
                                :class="{ on: tapis === 'PERHATIAN' }"
                                title="Masa berlaku atau jadwal review sudah lewat / tinggal ≤90 hari"
                                @click="pilihTapis('PERHATIAN')"
                            >
                                <i class="bi bi-alarm-fill me-1 flex-shrink-0"></i>
                                <span class="text-truncate">{{ ringkas.PERHATIAN }} Perlu Ditinjau</span>
                            </button>
                            <button
                                v-if="ringkas.BELUM_LENGKAP || tapis === 'BELUM_LENGKAP'"
                                type="button"
                                class="mh-alarm-pill is-quiet"
                                :class="{ on: tapis === 'BELUM_LENGKAP' }"
                                title="Belum punya versi berlaku — belum berversi, atau versinya masih draft"
                                @click="pilihTapis('BELUM_LENGKAP')"
                            >
                                <i class="bi bi-hourglass-split me-1 flex-shrink-0"></i>
                                <span class="text-truncate">{{ ringkas.BELUM_LENGKAP }} Belum Lengkap</span>
                            </button>
                        </div>

                        <!-- 1. Live Search Input -->
                        <div class="mb-2">
                            <HcisField
                                id="sop-master-search-input"
                                ref="cari"
                                v-model="q"
                                type="search"
                                placeholder="Cari judul, nomor dokumen, atau kode… (/)"
                                clearable
                                @update:model-value="cariDebounce"
                                @keydown.down.prevent="pindah(1)"
                                @keydown.enter.prevent="pindah(0)"
                            >
                                <template #prefix>
                                    <i class="bi bi-search text-muted"></i>
                                </template>
                            </HcisField>
                        </div>

                        <!-- 2. Lifecycle Status Quick Chips Row -->
                        <div class="mdx-tapis-chips mb-2">
                            <button
                                v-for="t in tapisan"
                                :key="t.key ?? 'semua'"
                                type="button"
                                class="mdx-chip-btn"
                                :class="{ on: tapis === t.key }"
                                @click="pilihTapis(t.key)"
                            >
                                <span class="text-truncate">{{ t.label }}</span>
                            </button>
                        </div>


                        <!-- 3. Sort & Filter Toggle Bar -->
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <div class="flex-grow-1 min-w-0">
                                <HcisSelect
                                    id="sop-filter-urut"
                                    v-model="urut"
                                    size="sm"
                                    leading-icon="sort-down"
                                    placeholder="Urutkan Dokumen"
                                    :clearable="false"
                                    :filterable="false"
                                    @update:model-value="muat(1)"
                                >
                                    <HcisSelectOption value="terbaru" label="Terbaru" icon="clock-history" />
                                    <HcisSelectOption value="judul" label="Judul (A–Z)" icon="sort-alpha-down" />
                                    <HcisSelectOption value="pemakai" label="Terbanyak Dipakai" icon="people-fill" />
                                </HcisSelect>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm mdx-filter-toggle-btn flex-shrink-0"
                                :class="{ 'is-active': showFacetedFilter || activeFacetedFilterCount > 0 }"
                                :title="showFacetedFilter ? 'Sembunyikan filter lanjutan' : 'Buka filter lanjutan'"
                                @click="showFacetedFilter = !showFacetedFilter"
                            >
                                <i class="bi bi-sliders me-1"></i>
                                <span>Filter</span>
                                <span v-if="activeFacetedFilterCount > 0" class="badge bg-primary text-white ms-1 rounded-pill">
                                    {{ activeFacetedFilterCount }}
                                </span>
                            </button>
                        </div>

                        <!-- 4. Collapsible Faceted Multi-Filter Panel -->
                        <transition name="mdx-filter-slide">
                            <div v-show="showFacetedFilter" class="mdx-faceted-box mb-2">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                    <div class="d-flex align-items-center gap-1.5 text-xs fw-bold text-slate-700">
                                        <i class="bi bi-funnel-fill text-primary"></i>
                                        <span>Filter Spesifik</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button
                                            v-if="activeFacetedFilterCount > 0"
                                            type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none text-danger text-xs fw-bold"
                                            title="Bersihkan filter spesifik"
                                            @click="clearAllFacetedFilters"
                                        >
                                            Reset
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none text-muted text-xs d-flex align-items-center gap-1"
                                            title="Tutup panel filter"
                                            @click="showFacetedFilter = false"
                                        >
                                            <i class="bi bi-x-lg"></i>
                                            <span>Tutup</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <!-- Jenis Dokumen (Full Width) -->
                                    <div class="col-12">
                                        <label for="sop-filter-jenis" class="form-label mdx-facet-lbl">Jenis (Mutu)</label>
                                        <HcisSelect
                                        id="sop-filter-jenis"
                                        v-model="jenis"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="320"
                                        leading-icon="diagram-3"
                                        placeholder="Semua Jenis Mutu..."
                                        @update:model-value="muat(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua Jenis Mutu" />
                                        <HcisSelectOption value="BELUM" label="Belum diklasifikasi" icon="question-circle" />
                                        <HcisSelectOption
                                            v-for="j in semuaJenisOpsi"
                                            :key="j.Id"
                                            :value="j.Id"
                                            :label="`${j.Kode_Jenis_Dokumen || `L${j.Level_Hirarki || '-'}`} · ${j.Nama_Jenis}`"
                                            :description="`Level ${j.Level_Hirarki || '-'} Hirarki Mutu`"
                                            :badge="`L${j.Level_Hirarki || '-'}`"
                                            badge-variant="brand"
                                        />
                                    </HcisSelect>
                                </div>

                                <!-- Departemen (Full Width) -->
                                <div class="col-12">
                                    <label for="sop-filter-departemen" class="form-label mdx-facet-lbl">Departemen</label>
                                    <HcisSelect
                                        id="sop-filter-departemen"
                                        v-model="departemen"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="320"
                                        leading-icon="diagram-2"
                                        placeholder="Semua Departemen..."
                                        @update:model-value="muat(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua Departemen" />
                                        <HcisSelectOption value="BELUM" label="Tanpa pemilik" icon="building-dash" />
                                        <HcisSelectOption
                                            v-for="dp in departemenOpsi"
                                            :key="dp.Id"
                                            :value="dp.Id"
                                            :label="`${dp.Kode_Departemen} — ${dp.Nama_Departemen}`"
                                            :description="`Kode Dept: ${dp.Kode_Departemen}`"
                                            :badge="dp.Kode_Departemen"
                                            badge-variant="teal"
                                        />
                                    </HcisSelect>
                                </div>

                                <!-- APQC PCF (Full Width) -->
                                <div class="col-12">
                                    <label for="sop-filter-apqc" class="form-label mdx-facet-lbl">Proses Bisnis APQC</label>
                                    <HcisSelect
                                        id="sop-filter-apqc"
                                        v-model="apqc"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="360"
                                        leading-icon="bounding-box"
                                        placeholder="Semua Proses APQC..."
                                        @update:model-value="muat(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua Proses APQC" />
                                        <HcisSelectOption value="BELUM" label="Belum dipetakan" icon="question-circle" />
                                        <HcisSelectOption
                                            v-for="a in semuaApqcOpsi"
                                            :key="a.Id"
                                            :value="a.Id"
                                            :label="`PCF #${a.Kode_Proses} · ${a.Nama_Proses}`"
                                            :badge="`#${a.Kode_Proses}`"
                                            badge-variant="purple"
                                        />
                                    </HcisSelect>
                                </div>

                                <!-- Entitas PT (Half Width) -->
                                <div class="col-6">
                                    <label for="sop-filter-entitas" class="form-label mdx-facet-lbl">Entitas PT</label>
                                    <HcisSelect
                                        id="sop-filter-entitas"
                                        v-model="entitas"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="260"
                                        leading-icon="building"
                                        placeholder="Semua Entitas..."
                                        @update:model-value="muat(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua Entitas" />
                                        <HcisSelectOption value="BELUM" label="Tanpa entitas" icon="building-dash" />
                                        <HcisSelectOption
                                            v-for="e in entitasOpsi"
                                            :key="e.Id"
                                            :value="e.Id"
                                            :label="`${e.Kode_Entitas} · ${e.Nama_Singkat || e.Nama_Entitas}`"
                                            :badge="e.Kode_Entitas"
                                            badge-variant="neutral"
                                        />
                                    </HcisSelect>
                                </div>

                                <!-- Ruang Lingkup (Half Width).

                                     Sumbu LAIN dari Entitas PT meski masternya sama:
                                     yang di atas menjawab "siapa pemiliknya", yang ini
                                     "berlaku di mana". Ikonnya sengaja berbeda — dua
                                     kotak bersebelahan dengan daftar isi yang identik
                                     akan tertukar kalau ikonnya juga sama.

                                     Menampilkan Kode_Entitas, BUKAN kode cetak pemilik:
                                     di posisi lingkup, EHG berarti "seluruh entitas",
                                     dan HOL tak pernah muncul di sini (DDL-2026-056). -->
                                <div class="col-6">
                                    <label for="sop-filter-lingkup" class="form-label mdx-facet-lbl">Ruang Lingkup</label>
                                    <HcisSelect
                                        id="sop-filter-lingkup"
                                        v-model="lingkup"
                                        size="sm"
                                        filterable
                                        clearable
                                        popover-min-width="260"
                                        leading-icon="geo-alt"
                                        placeholder="Semua Lingkup..."
                                        @update:model-value="muat(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua Lingkup" />
                                        <HcisSelectOption value="BELUM" label="Tanpa lingkup" icon="geo" />
                                        <HcisSelectOption
                                            v-for="e in entitasOpsi"
                                            :key="e.Id"
                                            :value="e.Id"
                                            :label="`${e.Kode_Entitas} · ${e.Nama_Singkat || e.Nama_Entitas}`"
                                            :badge="e.Kode_Entitas"
                                            badge-variant="neutral"
                                        />
                                    </HcisSelect>
                                </div>

                                <!-- Tipe Media (Full Width) -->
                                <div class="col-12">
                                    <label for="sop-filter-tipe" class="form-label mdx-facet-lbl">Tipe Media</label>
                                    <HcisSelect
                                        id="sop-filter-tipe"
                                        v-model="tipe"
                                        size="sm"
                                        clearable
                                        popover-min-width="220"
                                        leading-icon="file-earmark-text"
                                        placeholder="Semua Tipe..."
                                        @update:model-value="muat(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua Tipe Media" />
                                        <HcisSelectOption
                                            v-for="t in tipeOpsi"
                                            :key="t"
                                            :value="t"
                                            :label="t"
                                            :icon="getTipeMediaIcon(t)"
                                        />
                                    </HcisSelect>
                                </div>
                            </div>
                        </div>
                    </transition>

                        <!-- 5. Active Filter Tags Bar -->
                        <div v-if="activeFilterTags.length > 0" class="mdx-active-tags-strip mb-2">
                            <span
                                v-for="tag in activeFilterTags"
                                :key="tag.key"
                                class="mdx-active-tag"
                                :title="`Hapus filter ${tag.label}`"
                                @click="clearSingleFilter(tag.key)"
                            >
                                <span class="tag-lbl text-truncate">{{ tag.label }}</span>
                                <i class="bi bi-x-circle-fill tag-x flex-shrink-0"></i>
                            </span>
                            <button
                                type="button"
                                class="btn-reset-all flex-shrink-0"
                                title="Reset semua filter"
                                @click="resetAllFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <!-- 2. Scrollable Master List Body -->
                    <div class="mdx-sidebar__body">
                        <!-- Skeleton list saat memuat data -->
                        <div v-if="loading" class="p-2 d-flex flex-column gap-2">
                            <div
                                v-for="n in 6"
                                :key="n"
                                class="sop-master-item-row pe-none"
                                style="opacity: 0.88; pointer-events: none;"
                            >
                                <div class="item-jewel d-flex align-items-center justify-content-center" style="background: rgba(148, 163, 184, 0.12); border-color: rgba(203, 213, 225, 0.4);">
                                    <HcisSkeleton width="18px" height="18px" rounded="xs" />
                                </div>
                                <div class="item-content flex-grow-1 min-w-0">
                                    <div class="item-header-row mb-1">
                                        <HcisSkeleton :width="`${55 + (n * 7)}%`" height="15px" rounded="xs" />
                                        <HcisSkeleton width="38px" height="18px" rounded="full" />
                                    </div>
                                    <div class="item-number-row mb-1">
                                        <HcisSkeleton width="105px" height="12px" rounded="xs" />
                                    </div>
                                    <div class="item-identity-row mb-1.5">
                                        <HcisSkeleton width="48px" height="16px" rounded="xs" />
                                        <HcisSkeleton width="58px" height="16px" rounded="xs" />
                                    </div>
                                    <div class="item-footer-row">
                                        <div class="d-flex align-items-center gap-1 min-w-0">
                                            <HcisSkeleton width="7px" height="7px" rounded="full" />
                                            <HcisSkeleton width="75px" height="13px" rounded="xs" />
                                        </div>
                                        <div class="d-flex align-items-center gap-1 ms-auto">
                                            <HcisSkeleton width="28px" height="14px" rounded="xs" />
                                            <HcisSkeleton width="32px" height="14px" rounded="xs" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!rows.length" class="p-4 text-center">
                            <!-- Kalimatnya menyebut PENYARING MANA yang sedang
                                 menyala. Kartu bernilai nol pun bisa ditekan,
                                 jadi daftar kosong di sini sering merupakan
                                 jawaban yang benar — dan "tak ada yang cocok
                                 dengan filter" tak memberi tahu filter apa,
                                 sehingga admin menyangka layarnya rusak. -->
                            <HcisEmptyState
                                :icon="tapis === 'PERHATIAN' ? 'check-circle' : 'search'"
                                :title="kosongJudul"
                                :description="kosongKalimat"
                                variant="flat"
                                size="sm"
                            />
                            <div v-if="q || tapis || activeFacetedFilterCount > 0" class="mt-3">
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="arrow-counterclockwise"
                                    label="Reset Semua Filter"
                                    @click="resetAllFilters"
                                />
                            </div>
                        </div>

                        <div v-else ref="daftar" class="mdx-items-list">
                            <SopMasterDokumenCard
                                v-for="(d, i) in rows"
                                :key="d.Id_SOP_Dokumen"
                                :item="d"
                                :is-selected="terpilih === d.Id_SOP_Dokumen"
                                @select="pilih(d)"
                                @keydown.down.prevent="pindah(i + 1)"
                                @keydown.up.prevent="pindah(i - 1)"
                            />
                        </div>
                    </div>

                    <!-- 3. Sidebar Pagination Footer -->
                    <div v-if="total > perHal" class="mdx-sidebar__footer">
                        <HcisPagination
                            :current-page="page"
                            :page-size="perHal"
                            :total="total"
                            size="sm"
                            layout="prev, pager, next"
                            :disabled="loading"
                            @page-change="muat"
                        />
                    </div>
                </div>
            </aside>

            <!-- ── RIGHT COLUMN: DETAIL PANEL ── -->
            <main ref="panelCol" class="mdx-detail-wrap" :class="{ 'd-none d-lg-block': mobileTab === 'daftar' }">
                <div class="d-lg-none mb-3">
                    <HcisButton variant="secondary" size="sm" block leading-icon="arrow-left" label="Kembali ke Daftar Dokumen" @click="mobileTab = 'daftar'" />
                </div>

                <HcisCard variant="glass" sentiment="brand" size="lg" class="w-100 h-100">
                    <div v-if="!terpilih" class="mdx-hampa-state py-5 text-center">
                        <HcisEmptyState
                            icon="journal-text"
                            title="Pilih Dokumen"
                            description="Pilih salah satu dokumen dari daftar di sebelah kiri untuk melihat rincian arsip, berkas terlampir, versi berlaku, dan alur integrasi paket."
                            variant="flat"
                            sentiment="neutral"
                        />
                    </div>

                    <!-- Skeleton Detail saat memuat rincian -->
                    <div v-else-if="detailLoading" class="p-3 d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex gap-2">
                                <HcisSkeleton width="60px" height="24px" rounded="sm" />
                                <HcisSkeleton width="120px" height="24px" rounded="sm" />
                            </div>
                            <div class="d-flex gap-2">
                                <HcisSkeleton width="100px" height="32px" rounded="sm" />
                                <HcisSkeleton width="130px" height="32px" rounded="sm" />
                            </div>
                        </div>
                        <HcisSkeleton width="65%" height="1.8rem" rounded="sm" />
                        <HcisSkeleton width="35%" height="1.1rem" rounded="xs" />
                        <div class="row g-2.5 mt-1">
                            <div class="col-6 col-md-3">
                                <HcisSkeleton width="100%" height="70px" rounded="md" />
                            </div>
                            <div class="col-6 col-md-3">
                                <HcisSkeleton width="100%" height="70px" rounded="md" />
                            </div>
                            <div class="col-6 col-md-3">
                                <HcisSkeleton width="100%" height="70px" rounded="md" />
                            </div>
                            <div class="col-6 col-md-3">
                                <HcisSkeleton width="100%" height="70px" rounded="md" />
                            </div>
                        </div>
                        <HcisSkeleton width="100%" height="40px" rounded="sm" class="mt-2" />
                        <HcisSkeleton width="100%" height="180px" rounded="md" />
                    </div>

                    <template v-else-if="detail">
                        <!-- ══ 1. HERO IDENTITY & GOVERNANCE BANNER ══ -->
                        <header class="mdx-detail-hero mb-3">
                            <!-- Top Metadata & Action Dock -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2.5">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap min-w-0">
                                    <HcisBadge
                                        :label="detail.dokumen.Tipe"
                                        :variant="getTypeVariant(detail.dokumen.Tipe)"
                                        appearance="solid"
                                        size="sm"
                                    />
                                    <span v-if="detail.dokumen.Jenis_Nama" class="mdx-hero-tag jenis-tag" :title="`Jenis Dokumen: ${detail.dokumen.Jenis_Nama}`">
                                        <i class="bi bi-diagram-3-fill me-1 flex-shrink-0"></i>
                                        <span class="text-truncate" style="max-width: 220px;">
                                            {{ detail.dokumen.Jenis_Kode || `L${detail.dokumen.Jenis_Level}` }} · {{ detail.dokumen.Jenis_Nama }}
                                        </span>
                                    </span>
                                    <span v-else class="mdx-hero-tag neutral-tag">
                                        <i class="bi bi-question-circle me-1 flex-shrink-0"></i>Belum Diklasifikasi
                                    </span>

                                    <span v-if="versiBerlaku" class="mdx-hero-tag active-tag">
                                        <i class="bi bi-patch-check-fill me-1 flex-shrink-0"></i>v{{ versiBerlaku.Versi_Ke }} Berlaku
                                    </span>
                                    <span v-else class="mdx-hero-tag warning-tag">
                                        <i class="bi bi-hourglass-split me-1 flex-shrink-0"></i>Belum Ada Versi Terbit
                                    </span>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-auto">
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="pencil-square"
                                        label="Ubah Identitas"
                                        @click="bukaUbah(detailRow)"
                                    />
                                    <HcisButton
                                        variant="primary"
                                        size="sm"
                                        leading-icon="patch-check"
                                        label="Versi &amp; Pengesahan"
                                        @click="bukaVersi(detailRow)"
                                    />
                                </div>
                            </div>

                            <!-- Document Title -->
                            <h2 class="mdx-hero-title mb-2.5" :title="detail.dokumen.Judul">{{ detail.dokumen.Judul }}</h2>

                            <!-- Monospace Crystal Number & Segment Anatomy Bar -->
                            <div class="mdx-hero-number-strip">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2.5">
                                    <!-- Number Box + Copy Button -->
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <div
                                            class="mdx-official-number font-monospace"
                                            :class="{ 'is-incomplete': !detail.dokumen.Nomor_Lengkap }"
                                            :title="detail.dokumen.Nomor_Lengkap ? 'Nomor Dokumen Resmi' : 'Nomor Belum Lengkap'"
                                        >
                                            <i class="bi bi-hash flex-shrink-0"></i>
                                            <span class="text-truncate" style="max-width: 320px;">
                                                {{ detail.dokumen.Nomor_Lengkap || detail.dokumen.Nomor_Pratinjau || detail.dokumen.Nomor_SOP_Resmi || 'Belum Bernomor' }}
                                            </span>
                                        </div>

                                        <button
                                            v-if="detail.dokumen.Nomor_Lengkap || detail.dokumen.Nomor_SOP_Resmi"
                                            type="button"
                                            class="btn btn-sm mdx-copy-btn flex-shrink-0"
                                            :class="{ 'is-copied': nomorTersalin }"
                                            :title="nomorTersalin ? 'Nomor berhasil disalin!' : 'Salin Nomor Dokumen ke Clipboard'"
                                            @click="salinNomorDokumen(detail.dokumen.Nomor_Lengkap || detail.dokumen.Nomor_SOP_Resmi)"
                                        >
                                            <i :class="nomorTersalin ? 'bi bi-check2 text-success' : 'bi bi-clipboard'"></i>
                                        </button>
                                    </div>

                                    <!-- Segment Breakdown Pills -->
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap ms-auto">
                                        <span v-if="detail.dokumen.Entitas_Pemilik_Singkat" class="mdx-seg-pill" :title="`Entitas Pemilik: ${detail.dokumen.Entitas_Pemilik_Nama || detail.dokumen.Entitas_Pemilik_Singkat}`">
                                            <i class="bi bi-building me-1 flex-shrink-0 text-muted"></i>
                                            <span class="text-truncate" style="max-width: 140px;">{{ detail.dokumen.Entitas_Pemilik_Singkat }}</span>
                                        </span>
                                        <span v-if="detail.dokumen.Pemilik_Label" class="mdx-seg-pill" :title="`Departemen: ${detail.dokumen.Pemilik_Label}`">
                                            <i class="bi bi-diagram-3 me-1 flex-shrink-0 text-muted"></i>
                                            <span class="text-truncate" style="max-width: 200px;">{{ detail.dokumen.Pemilik_Label }}</span>
                                        </span>
                                        <span v-if="detail.dokumen.APQC_Kode" class="mdx-seg-pill" :title="`APQC: ${detail.dokumen.APQC_Kode} · ${detail.dokumen.APQC_Nama}`">
                                            <i class="bi bi-bounding-box me-1 flex-shrink-0 text-muted"></i>
                                            <span class="text-truncate" style="max-width: 140px;">PCF #{{ detail.dokumen.APQC_Kode }}</span>
                                        </span>
                                        <span v-if="detail.dokumen.Entitas_Lingkup_Singkat && detail.dokumen.Entitas_Lingkup_Singkat !== detail.dokumen.Entitas_Pemilik_Singkat" class="mdx-seg-pill" :title="`Ruang Lingkup: ${detail.dokumen.Entitas_Lingkup_Nama || detail.dokumen.Entitas_Lingkup_Singkat}`">
                                            <i class="bi bi-geo me-1 flex-shrink-0 text-muted"></i>
                                            <span class="text-truncate" style="max-width: 140px;">Lingkup: {{ detail.dokumen.Entitas_Lingkup_Singkat }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </header>

                        <!-- ══ 2. EXECUTIVE TELEMETRY KPI METRICS ══ -->
                        <HcisStatGroup :cols="4" gap="sm" class="mb-3">
                            <HcisStatCard
                                layout="compact"
                                label="Versi Berlaku"
                                :value="versiBerlaku ? $vlabel(versiBerlaku.Versi_Ke) : 'Belum Ada'"
                                caption="Status versi aktif"
                                icon="patch-check-fill"
                                :sentiment="versiBerlaku ? 'success' : 'neutral'"
                            />
                            <HcisStatCard
                                layout="compact"
                                label="Berlaku Sejak"
                                :value="formatTanggalSingkat(versiBerlaku?.Tanggal_Berlaku)"
                                caption="Masa berlaku dokumen"
                                icon="calendar2-check-fill"
                                sentiment="brand"
                            />
                            <HcisStatCard
                                layout="compact"
                                label="Pengesah Dokumen"
                                :value="blokPengesah.length ? (blokPengesah[0].Nama || `${blokPengesah.length} Pejabat`) : 'Belum Disahkan'"
                                :caption="blokPengesah.length > 1 ? `+${blokPengesah.length - 1} pejabat lainnya` : (blokPengesah.length ? 'Pejabat pengesah' : 'Tanpa pengesah')"
                                icon="person-badge-fill"
                                :sentiment="blokPengesah.length ? 'purple' : 'neutral'"
                            />
                            <HcisStatCard
                                layout="compact"
                                label="Karyawan Terikat"
                                :value="detail.total_karyawan || 0"
                                suffix="Orang"
                                caption="Penerima penugasan"
                                icon="people-fill"
                                sentiment="teal"
                            />
                        </HcisStatGroup>

                        <!-- ══ 3. STANDARDIZED HCISTABS NAVIGATION ══ -->
                        <HcisTabs
                            v-model="tab"
                            :tabs="tabs"
                            variant="segmented"
                            class="mb-3"
                        />

                        <!-- ══ TAB 1: RINGKASAN & MATRIKS TATA KELOLA ══ -->
                        <template v-if="tab === 'ringkasan'">
                            <div class="mdx-tab-content d-flex flex-column gap-3.5">
                                <!-- Card A: Spesifikasi & Matriks Tata Kelola Dokumen -->
                                <HcisCard
                                    title="Matriks Tata Kelola &amp; Klasifikasi Dokumen"
                                    description="Spesifikasi standar mutu ISO, klasifikasi APQC PCF v7.3, serta batas kepemilikan dan ruang lingkup."
                                    icon="shield-check"
                                    sentiment="primary"
                                    variant="outlined"
                                    size="sm"
                                    badge="Standar Mutu ISO/APQC"
                                    badge-variant="neutral"
                                >
                                    <div class="mdx-spec-grid pt-1">
                                        <!-- Tile 1: Level Hirarki Mutu -->
                                        <div class="mdx-spec-tile">
                                            <div class="spec-icon bg-blue-subtle text-primary">
                                                <i class="bi bi-diagram-3-fill"></i>
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                                    <span class="spec-label">Level Hirarki Mutu</span>
                                                    <HcisBadge
                                                        v-if="detail.dokumen.Jenis_Level"
                                                        :label="`L${detail.dokumen.Jenis_Level}`"
                                                        variant="brand"
                                                        size="xs"
                                                    />
                                                </div>
                                                <div class="spec-value text-truncate" :title="detail.dokumen.Jenis_Nama ? `${detail.dokumen.Jenis_Kode ? `${detail.dokumen.Jenis_Kode} · ` : ''}${detail.dokumen.Jenis_Nama}` : 'Belum Diklasifikasi'">
                                                    {{ detail.dokumen.Jenis_Nama ? `${detail.dokumen.Jenis_Kode ? `${detail.dokumen.Jenis_Kode} · ` : ''}${detail.dokumen.Jenis_Nama}` : 'Belum Diklasifikasi' }}
                                                </div>
                                                <span class="spec-sub text-truncate d-block" title="Piramid Mutu Dokumen Terkendali">Piramid Mutu Dokumen Terkendali</span>
                                            </div>
                                        </div>

                                        <!-- Tile 2: Klasifikasi Proses Bisnis APQC -->
                                        <div class="mdx-spec-tile">
                                            <div class="spec-icon bg-purple-subtle text-purple">
                                                <i class="bi bi-bounding-box"></i>
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                                    <span class="spec-label">Proses Bisnis APQC</span>
                                                    <HcisBadge
                                                        v-if="detail.dokumen.APQC_Kode"
                                                        :label="`#${detail.dokumen.APQC_Kode}`"
                                                        variant="purple"
                                                        size="xs"
                                                    />
                                                </div>
                                                <div class="spec-value text-truncate" :title="detail.dokumen.APQC_Kode ? `PCF #${detail.dokumen.APQC_Kode} · ${detail.dokumen.APQC_Nama}` : 'Belum Dipetakan'">
                                                    {{ detail.dokumen.APQC_Kode ? `PCF #${detail.dokumen.APQC_Kode} · ${detail.dokumen.APQC_Nama}` : 'Belum Dipetakan' }}
                                                </div>
                                                <span class="spec-sub text-truncate d-block" title="APQC Process Classification Framework">APQC Process Classification Framework</span>
                                            </div>
                                        </div>

                                        <!-- Tile 3: Departemen Penanggung Jawab -->
                                        <div class="mdx-spec-tile">
                                            <div class="spec-icon bg-teal-subtle text-teal">
                                                <i class="bi bi-diagram-2-fill"></i>
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                                    <span class="spec-label">Departemen Penanggung Jawab</span>
                                                    <HcisBadge
                                                        v-if="detail.dokumen.Departemen_Kode"
                                                        :label="detail.dokumen.Departemen_Kode"
                                                        variant="teal"
                                                        size="xs"
                                                    />
                                                </div>
                                                <div class="spec-value text-truncate" :title="detail.dokumen.Pemilik_Label || 'Tanpa Departemen'">
                                                    {{ detail.dokumen.Pemilik_Label || 'Tanpa Departemen' }}
                                                </div>
                                                <span class="spec-sub text-truncate d-block" :title="`Entitas: ${detail.dokumen.Entitas_Pemilik_Nama || detail.dokumen.Entitas_Pemilik_Singkat || '—'}`">
                                                    Entitas: {{ detail.dokumen.Entitas_Pemilik_Nama || detail.dokumen.Entitas_Pemilik_Singkat || '—' }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Tile 4: Ruang Lingkup Penerapan -->
                                        <div class="mdx-spec-tile">
                                            <div class="spec-icon bg-amber-subtle text-amber">
                                                <i class="bi bi-geo-alt-fill"></i>
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                                    <span class="spec-label">Ruang Lingkup Penerapan</span>
                                                    <HcisBadge
                                                        :label="detail.dokumen.Entitas_Pemilik_Id === detail.dokumen.Entitas_Lingkup_Id ? 'Internal PT' : 'Lintas Entitas'"
                                                        :variant="detail.dokumen.Entitas_Pemilik_Id === detail.dokumen.Entitas_Lingkup_Id ? 'neutral' : 'purple'"
                                                        size="xs"
                                                    />
                                                </div>
                                                <div class="spec-value text-truncate" :title="detail.dokumen.Entitas_Lingkup_Nama || detail.dokumen.Entitas_Lingkup_Singkat || 'Mengikuti Entitas Pemilik'">
                                                    {{ detail.dokumen.Entitas_Lingkup_Nama || detail.dokumen.Entitas_Lingkup_Singkat || 'Mengikuti Entitas Pemilik' }}
                                                </div>
                                                <span class="spec-sub text-truncate d-block" title="Cakupan Legalitas &amp; Implementasi">Cakupan Legalitas &amp; Implementasi</span>
                                            </div>
                                        </div>
                                    </div>
                                </HcisCard>

                                <!-- Card B: Berkas & Media Versi Berlaku -->
                                <HcisCard
                                    :title="isKuis ? 'Ujian Evaluasi Versi Berlaku' : 'Berkas Lampiran Versi Berlaku'"
                                    :description="isKuis ? 'Evaluasi pemahaman karyawan terintegrasi HC Learn.' : 'Dokumen berkas resmi yang disahkan untuk versi operasional aktif.'"
                                    :icon="isKuis ? 'patch-question-fill' : 'file-earmark-pdf-fill'"
                                    sentiment="primary"
                                    variant="outlined"
                                    size="sm"
                                    :badge="versiBerlaku ? $vlabel(versiBerlaku.Versi_Ke) + ' Berlaku' : null"
                                    badge-variant="success"
                                >
                                    <div class="pt-1">
                                        <template v-if="isKuis">
                                            <div v-if="versiBerlaku && versiBerlaku.Punya_Ujian" class="mdx-media-card">
                                                <div class="media-icon quiz">
                                                    <i class="bi bi-patch-question-fill"></i>
                                                </div>
                                                <div class="min-w-0 flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" :title="versiBerlaku.Nama_Ujian || 'Ujian Tertaut'">
                                                        {{ versiBerlaku.Nama_Ujian || 'Ujian Tertaut' }}
                                                    </div>
                                                    <div class="text-muted small d-flex align-items-center gap-1.5 mt-0.5 flex-wrap">
                                                        <HcisBadge label="HC LEARN" variant="purple" size="xs" />
                                                        <span>· Terintegrasi evaluasi pemahaman karyawan</span>
                                                    </div>
                                                </div>
                                                <HcisButton
                                                    variant="secondary"
                                                    size="sm"
                                                    leading-icon="box-arrow-up-right"
                                                    label="Buka Ujian"
                                                    @click="bukaBerkasBerlaku"
                                                />
                                            </div>
                                            <HcisEmptyState
                                                v-else
                                                icon="patch-question"
                                                title="Belum Ada Ujian pada Versi Berlaku"
                                                description="Tautkan ujian HC Learn melalui menu Versi &amp; Pengesahan agar karyawan dapat mengikuti evaluasi."
                                                variant="flat"
                                                sentiment="neutral"
                                                size="sm"
                                                class="py-3"
                                            >
                                                <template #actions>
                                                    <HcisButton
                                                        variant="secondary"
                                                        size="xs"
                                                        leading-icon="patch-check"
                                                        label="Buka Versi &amp; Pengesahan"
                                                        @click="bukaVersi(detailRow)"
                                                    />
                                                </template>
                                            </HcisEmptyState>
                                        </template>

                                        <template v-else>
                                            <div v-if="detail.berkas" class="mdx-media-card">
                                                <div class="media-icon doc">
                                                    <i class="bi bi-file-earmark-pdf-fill"></i>
                                                </div>
                                                <div class="min-w-0 flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" :title="detail.berkas.judul || 'Berkas Dokumen Resmi'">
                                                        {{ detail.berkas.judul || 'Berkas Dokumen Resmi' }}
                                                    </div>
                                                    <div class="text-muted small d-flex align-items-center gap-1.5 mt-0.5 flex-wrap">
                                                        <HcisBadge :label="String(detail.berkas.type || detail.dokumen.Tipe || '').toUpperCase()" variant="neutral" size="xs" />
                                                        <span v-if="detail.berkas.total_halaman">· {{ detail.berkas.total_halaman }} halaman</span>
                                                        <span class="d-none d-sm-inline text-muted">· Versi aktif terkendali</span>
                                                    </div>
                                                </div>
                                                <HcisButton
                                                    variant="secondary"
                                                    size="sm"
                                                    :loading="sibukBerlaku"
                                                    :leading-icon="isForm ? 'download' : 'eye'"
                                                    :label="isForm ? 'Unduh Berkas' : 'Lihat Berkas'"
                                                    @click="bukaBerkasBerlaku"
                                                />
                                            </div>
                                            <HcisEmptyState
                                                v-else
                                                icon="file-earmark-arrow-up"
                                                title="Belum Ada Berkas pada Versi Berlaku"
                                                description="Lampirkan berkas dokumen melalui menu Versi &amp; Pengesahan agar dapat diakses atau diunduh oleh penerima dokumen."
                                                variant="flat"
                                                sentiment="neutral"
                                                size="sm"
                                                class="py-3"
                                            >
                                                <template #actions>
                                                    <HcisButton
                                                        variant="secondary"
                                                        size="xs"
                                                        leading-icon="patch-check"
                                                        label="Buka Versi &amp; Pengesahan"
                                                        @click="bukaVersi(detailRow)"
                                                    />
                                                </template>
                                            </HcisEmptyState>
                                        </template>
                                    </div>
                                </HcisCard>

                                <!-- Card C: Keterangan & Deskripsi Prosedur -->
                                <HcisCard
                                    title="Maksud, Tujuan &amp; Ruang Lingkup Prosedur"
                                    description="Ringkasan penjelasan ruang lingkup serta batasan prosedur operasional dokumen."
                                    icon="card-text"
                                    sentiment="primary"
                                    variant="outlined"
                                    size="sm"
                                >
                                    <template #actions>
                                        <HcisButton
                                            v-if="detail.dokumen.Deskripsi"
                                            variant="ghost"
                                            size="xs"
                                            leading-icon="pencil"
                                            label="Ubah Deskripsi"
                                            @click="bukaUbah(detailRow)"
                                        />
                                    </template>

                                    <div class="pt-1">
                                        <div
                                            v-if="detail.dokumen.Deskripsi"
                                            class="mdx-desc-content"
                                        >
                                            <i class="bi bi-quote desc-quote-icon"></i>
                                            <p class="mb-0 text-slate-800" style="white-space: pre-line; line-height: 1.65; word-break: break-word; overflow-wrap: anywhere;">
                                                {{ detail.dokumen.Deskripsi }}
                                            </p>
                                        </div>

                                        <HcisEmptyState
                                            v-else
                                            icon="card-text"
                                            title="Belum Ada Ringkasan Deskripsi"
                                            description="Tambahkan ringkasan maksud, tujuan, dan batasan penerapan dokumen ini."
                                            variant="flat"
                                            sentiment="neutral"
                                            size="xs"
                                            class="py-3"
                                        >
                                            <template #actions>
                                                <HcisButton
                                                    variant="secondary"
                                                    size="xs"
                                                    leading-icon="pencil"
                                                    label="Tulis Ringkasan"
                                                    @click="bukaUbah(detailRow)"
                                                />
                                            </template>
                                        </HcisEmptyState>
                                    </div>
                                </HcisCard>

                                <!-- Card D: Pengarsipan & Proteksi Tata Kelola -->
                                <HcisAlert
                                    v-if="detail.nonaktif"
                                    variant="neutral"
                                    size="sm"
                                    appearance="subtle"
                                    title="Dokumen sudah dinonaktifkan"
                                    icon="archive-fill"
                                    class="mt-0"
                                >
                                    <template #default>
                                        <p v-if="detail.nonaktif.Tanpa_Jejak" class="mb-0 small">
                                            Dinonaktifkan sebelum jejak penonaktifan dicatat, jadi tak ada keterangan
                                            siapa maupun kapan. Bukan berarti tak ada yang terdampak — tak ada yang
                                            pernah merekamnya.
                                        </p>
                                        <template v-else>
                                            <p class="mb-1.5 small">
                                                Dinonaktifkan
                                                <strong>{{ waktuJejak(detail.nonaktif.Nonaktif_At) }}</strong>
                                                oleh
                                                <strong>{{ detail.nonaktif.Nonaktif_Oleh || '—' }}</strong>.
                                            </p>
                                            <p v-if="detail.nonaktif.Nonaktif_Catatan" class="mb-1.5 small mdx-nonaktif-catatan">
                                                “{{ detail.nonaktif.Nonaktif_Catatan }}”
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                Kondisi saat itu:
                                                <strong class="tnum">{{ detail.nonaktif.Nonaktif_Paket ?? '—' }} paket</strong>
                                                ·
                                                <strong class="tnum">{{ detail.nonaktif.Nonaktif_Orang ?? '—' }} karyawan</strong>
                                                masih memegangnya.
                                            </p>
                                        </template>
                                    </template>
                                </HcisAlert>

                                <HcisAlert
                                    v-else
                                    variant="danger"
                                    size="sm"
                                    appearance="subtle"
                                    title="Nonaktifkan Dokumen"
                                    icon="shield-slash"
                                    class="mt-0"
                                >
                                    <template #default>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <p class="mb-0 small flex-grow-1 min-w-0" style="max-width: 600px;">
                                                Dokumen akan berhenti ditawarkan di Document Builder.
                                                <template v-if="detail.paket_pemakai.length">
                                                    <strong>{{ detail.paket_pemakai.length }} paket</strong> yang sudah
                                                    memakainya tetap berjalan sampai dilepas secara mandiri.
                                                </template>
                                            </p>
                                            <HcisButton
                                                variant="danger-quiet"
                                                size="xs"
                                                leading-icon="archive"
                                                label="Nonaktifkan Dokumen"
                                                :disabled="nonaktifModal"
                                                @click="bukaNonaktif"
                                            />
                                        </div>
                                    </template>
                                </HcisAlert>
                            </div>
                        </template>

                        <!-- ── TAB 2: PAKET & ALUR ── -->
                        <template v-else-if="tab === 'paket'">
                            <div class="mdx-tab-content d-flex flex-column gap-3.5">
                                <HcisCard
                                    title="Paket Pemakai Dokumen"
                                    description="Perubahan versi berlaku pada dokumen ini akan langsung memperbarui versi acuan di seluruh paket operasional terkait."
                                    icon="box-seam"
                                    sentiment="primary"
                                    variant="outlined"
                                    size="sm"
                                    :badge="`${detail.paket_pemakai.length} Paket Terhubung`"
                                    badge-variant="brand"
                                >
                                    <template #actions>
                                        <HcisButton
                                            variant="ghost"
                                            size="xs"
                                            leading-icon="box-arrow-up-right"
                                            label="Buka Builder"
                                            href="/sop/admin/builder"
                                        />
                                    </template>

                                    <HcisEmptyState
                                        v-if="!detail.paket_pemakai.length"
                                        icon="box-seam"
                                        title="Belum Dipakai Paket Mana Pun"
                                        description="Hubungkan dokumen ini ke dalam paket tata kelola operasional melalui menu Document Builder."
                                        variant="flat"
                                        sentiment="neutral"
                                        size="sm"
                                        class="py-3"
                                    >
                                        <template #actions>
                                            <HcisButton
                                                variant="secondary"
                                                size="xs"
                                                leading-icon="box-arrow-up-right"
                                                label="Buka Document Builder"
                                                href="/sop/admin/builder"
                                            />
                                        </template>
                                    </HcisEmptyState>

                                    <div v-else class="pt-1">
                                        <div class="d-flex flex-column gap-2 mb-1">
                                            <div
                                                v-for="p in paketUrut"
                                                :key="p.Id_SOP_Paket"
                                                class="mdx-paket-item"
                                            >
                                                <div class="d-flex align-items-center justify-content-between w-100 gap-3">
                                                    <div class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1">
                                                        <div class="mdx-paket-icon flex-shrink-0">
                                                            <i class="bi bi-box-seam"></i>
                                                        </div>
                                                        <div class="min-w-0 flex-grow-1">
                                                            <a
                                                                class="text-truncate fw-bold text-dark text-decoration-none d-block mdx-paket-link"
                                                                :href="'/sop/admin/builder?paket=' + p.Id_SOP_Paket"
                                                                :title="p.Nama_Paket"
                                                            >
                                                                {{ p.Nama_Paket }}
                                                            </a>
                                                            <div class="d-flex align-items-center gap-2.5 mt-1">
                                                                <div class="ratio-bar-track flex-shrink-0" :title="`Proporsi karyawan terikat: ${p.Jumlah_Karyawan} orang`">
                                                                    <div class="ratio-bar-fill" :style="{ width: lebarBar(p) }"></div>
                                                                </div>
                                                                <span class="text-muted small font-monospace text-truncate">
                                                                    <i class="bi bi-people me-1"></i>{{ p.Jumlah_Karyawan }} orang
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                        <HcisBadge
                                                            :label="p.Status === 'PUBLISHED' ? 'Terbit' : 'Draft'"
                                                            :variant="p.Status === 'PUBLISHED' ? 'success' : 'neutral'"
                                                            size="xs"
                                                        />
                                                        <HcisButton
                                                            variant="ghost"
                                                            size="xs"
                                                            icon-only
                                                            leading-icon="box-arrow-up-right"
                                                            title="Buka paket di Document Builder"
                                                            aria-label="Buka paket di Document Builder"
                                                            :href="'/sop/admin/builder?paket=' + p.Id_SOP_Paket"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </HcisCard>

                                <!-- Diagram Alur Versi & Paket -->
                                <HcisCard
                                    title="Diagram Alur Versi &amp; Paket"
                                    description="Visualisasi interaktif relasi antara dokumen, versi berlaku/arsip, dan paket-paket operasional penerima."
                                    icon="diagram-3"
                                    sentiment="primary"
                                    variant="outlined"
                                    size="sm"
                                >
                                    <div class="mdx-flow-container pt-1">
                                        <SopDokumenAlur
                                            :dokumen="detail.dokumen"
                                            :versi="detail.riwayat_versi"
                                            :paket="detail.paket_pemakai"
                                            :sibuk="berkasSibuk"
                                            @lihat-versi="bukaIsiVersi"
                                            @terbit-versi="pratinjau = $event"
                                            @kembalikan-versi="kembalikan = $event"
                                        />
                                    </div>
                                </HcisCard>
                            </div>
                        </template>

                        <!-- ── TAB 3: VERSI ── -->
                        <template v-else-if="tab === 'versi'">
                            <div class="mdx-tab-content">
                                <HcisCard
                                    title="Riwayat Versi Dokumen"
                                    kicker="TATA KELOLA VERSI"
                                    description="Histori seluruh versi dokumen, masa berlaku, pengesahan, dan berkas lampiran."
                                    :badge="`${detailVersi.total || (detail.riwayat_versi ? detail.riwayat_versi.length : 0)} Versi`"
                                    icon="clock-history"
                                    sentiment="brand"
                                    variant="outlined"
                                    size="sm"
                                    class="mb-3"
                                >
                                    <template #actions>
                                        <HcisButton
                                            variant="primary"
                                            size="xs"
                                            leading-icon="plus-lg"
                                            label="Tambah Versi"
                                            @click="bukaVersi(detailRow)"
                                        />
                                    </template>

                                    <HcisEmptyState
                                        v-if="!detailVersi.total && !(detail.riwayat_versi && detail.riwayat_versi.length)"
                                        icon="clock-history"
                                        title="Belum Ada Versi Tersimpan"
                                        description="Dokumen baru bisa digunakan oleh paket operasional setelah memiliki minimal satu versi yang disahkan dan berlaku."
                                        variant="flat"
                                        sentiment="neutral"
                                        size="sm"
                                        class="py-3"
                                    >
                                        <template #actions>
                                            <HcisButton
                                                variant="primary"
                                                size="xs"
                                                leading-icon="plus-lg"
                                                label="Tambah Versi Pertama"
                                                @click="bukaVersi(detailRow)"
                                            />
                                        </template>
                                    </HcisEmptyState>

                                    <div v-else>
                                        <HcisTimeline
                                            :items="versiTimelineItems"
                                            variant="card"
                                            sentiment="brand"
                                            class="p-1"
                                        >
                                            <template #avatar="{ item }">
                                                <span class="fw-bold font-monospace" style="font-size: 0.75rem;">
                                                    {{ item.initials || item.avatarText }}
                                                </span>
                                            </template>

                                            <template #itemContent="{ item }">
                                                <div class="d-flex flex-column gap-2.5">
                                                    <!-- Top Badges Row -->
                                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                        <div class="d-flex align-items-center gap-1.5 flex-wrap min-w-0">
                                                            <HcisBadge
                                                                :label="item.role"
                                                                :variant="item.sentiment"
                                                                size="xs"
                                                                :dot="item.active"
                                                            />

                                                            <!-- Tahap Pill -->
                                                            <span
                                                                v-if="item.tahapNama"
                                                                class="mdx-tahap-pill"
                                                                :style="{ '--tc': item.tahapWarna || '#64748b' }"
                                                            >
                                                                <i class="bi bi-signpost-split-fill"></i>
                                                                {{ item.tahapNama }}
                                                            </span>

                                                            <!-- Lampiran Indicator -->
                                                            <span v-if="item.raw && item.raw.Punya_Berkas" class="sop-versi-lampiran-badge">
                                                                <i class="bi bi-file-earmark-pdf-fill text-primary me-1"></i>PDF Terlampir
                                                            </span>
                                                            <span v-else-if="item.raw && item.raw.Nama_Ujian" class="sop-versi-lampiran-badge">
                                                                <i class="bi bi-patch-question-fill text-purple me-1"></i>Kuis HC Learn
                                                            </span>
                                                        </div>

                                                        <!-- Pindah tahap hanya untuk draft yang MASIH BERJALAN. -->
                                                        <HcisButton
                                                            v-if="item.draft"
                                                            variant="ghost"
                                                            size="xs"
                                                            leading-icon="signpost-split"
                                                            label="Pindahkan tahap"
                                                            @click.stop="bukaTahapVersi(item.raw)"
                                                        />
                                                    </div>

                                                    <!-- Ringkasan Perubahan -->
                                                    <div v-if="item.content" class="sop-versi-summary-box">
                                                        <i class="bi bi-quote text-primary fs-6 me-1 flex-shrink-0"></i>
                                                        <span>{{ item.content }}</span>
                                                    </div>

                                                    <!-- Lifecycle Timestamps Bar -->
                                                    <div class="sop-versi-meta-grid">
                                                        <div class="sop-versi-meta-item">
                                                            <i class="bi bi-calendar-plus text-primary flex-shrink-0"></i>
                                                            <span>Dibuat: <strong class="text-dark">{{ item.dibuat }}</strong></span>
                                                        </div>
                                                        <div v-if="item.terbit" class="sop-versi-meta-item">
                                                            <i class="bi bi-broadcast text-success flex-shrink-0"></i>
                                                            <span>Terbit: <strong class="text-success">{{ item.terbit }}</strong></span>
                                                        </div>
                                                        <div class="sop-versi-meta-item">
                                                            <i class="bi bi-clock-history text-secondary flex-shrink-0"></i>
                                                            <span>Masa Berlaku: <strong class="text-dark">{{ item.rentangMasa }}</strong></span>
                                                        </div>
                                                        <div v-if="item.raw && item.raw.Disahkan_Oleh" class="sop-versi-meta-item">
                                                            <i class="bi bi-person-check text-purple flex-shrink-0"></i>
                                                            <span class="text-truncate" style="max-width: 260px;" :title="`Pengesah: ${item.raw.Disahkan_Oleh}`">
                                                                Pengesah: <strong class="text-dark">{{ item.raw.Disahkan_Oleh }}</strong>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template #itemActions="{ item }">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <!-- Tombol Utama: Lihat Detail -->
                                                    <HcisButton
                                                        variant="primary"
                                                        size="xs"
                                                        leading-icon="info-circle"
                                                        label="Lihat Detail"
                                                        title="Buka detail lengkap versi, dokumen, pejabat pengesah & catatan review"
                                                        @click="bukaDetailVersi(item.raw)"
                                                    />

                                                    <!-- Wizard Arsip -->
                                                    <HcisButton
                                                        v-if="item.dibatalkan"
                                                        variant="ghost"
                                                        size="xs"
                                                        leading-icon="archive"
                                                        label="Wizard Arsip"
                                                        title="Buka wizard versi arsip yang dibatalkan ini"
                                                        @click="suntingDraft(item.raw)"
                                                    />

                                                    <!-- Aksi Lanjutan jika Draft -->
                                                    <template v-else-if="item.draft">
                                                        <HcisButton
                                                            v-if="item.raw.Boleh_Terbit"
                                                            variant="success"
                                                            size="xs"
                                                            leading-icon="broadcast"
                                                            label="Pratinjau Terbit"
                                                            title="Versi sudah di langkah Pemberkasan — buka pratinjau sebelum terbit"
                                                            @click="pratinjau = item.raw"
                                                        />
                                                        <HcisButton
                                                            variant="secondary"
                                                            size="xs"
                                                            leading-icon="pencil-square"
                                                            label="Sunting Draft"
                                                            title="Buka wizard untuk melanjutkan pengisian draft"
                                                            @click="suntingDraft(item.raw)"
                                                        />
                                                        <HcisButton
                                                            variant="danger-quiet"
                                                            size="xs"
                                                            icon-only
                                                            leading-icon="trash"
                                                            title="Hapus draft revisi"
                                                            aria-label="Hapus draft revisi"
                                                            :loading="menghapus === item.raw.Id_SOP_Item_Versi"
                                                            @click="hapusDraft(item.raw)"
                                                        />
                                                    </template>

                                                    <!-- Membatalkan Versi Berlaku -->
                                                    <HcisButton
                                                        v-if="item.raw && item.raw.Status === 'PUBLISHED' && !item.dibatalkan"
                                                        variant="danger-quiet"
                                                        size="xs"
                                                        leading-icon="x-octagon"
                                                        label="Batalkan Versi"
                                                        title="Batalkan versi yang sedang berlaku — hanya bisa selama belum diterima karyawan"
                                                        @click="batalTerbit = item.raw"
                                                    />

                                                    <!-- Ubah Masa Berlaku Terbatas -->
                                                    <HcisButton
                                                        v-else-if="item.bolehUbahTerbatas"
                                                        variant="secondary"
                                                        size="xs"
                                                        leading-icon="calendar-range"
                                                        label="Ubah Masa Berlaku &amp; Pengesah"
                                                        title="Versi terbit: hanya masa berlaku, jadwal review, ringkasan, dan blok pengesah yang bisa diubah."
                                                        @click="suntingDraft(item.raw)"
                                                    />

                                                    <!-- Kembalikan jika Obsolete -->
                                                    <HcisButton
                                                        v-else-if="item.raw && item.raw.Status === 'OBSOLETE' && item.raw.Punya_Berkas"
                                                        variant="warning"
                                                        size="xs"
                                                        leading-icon="arrow-counterclockwise"
                                                        label="Kembalikan"
                                                        title="Kembalikan &amp; aktifkan kembali versi arsip ini"
                                                        @click="kembalikan = item.raw"
                                                    />
                                                </div>
                                            </template>
                                        </HcisTimeline>

                                        <div v-if="detailVersi.total > detailVersiPerPage" class="d-flex justify-content-center mt-3 pt-2 border-top">
                                            <HcisPagination
                                                :current-page="detailVersi.page"
                                                :page-size="detailVersiPerPage"
                                                :total="detailVersi.total"
                                                size="sm"
                                                layout="total, prev, pager, next"
                                                @update:current-page="muatDetailVersi"
                                            />
                                        </div>
                                    </div>
                                </HcisCard>
                            </div>
                        </template>

                        <!-- ── TAB 4: RIWAYAT AUDIT ── -->
                        <template v-else-if="tab === 'riwayat'">
                            <div class="mdx-tab-content">
                                <HcisCard
                                    title="Audit Trail &amp; Log Perubahan"
                                    kicker="REKAM JEJAK SISTEM"
                                    description="Kronologi perubahan dokumen, penerbitan versi, dan aktivitas tata kelola dokumen."
                                    :badge="`${riwayat.baris.length} Log Aktivitas`"
                                    icon="journal-text"
                                    sentiment="brand"
                                    variant="outlined"
                                    size="sm"
                                    class="mb-3"
                                >
                                    <template #actions>
                                        <HcisButton
                                            variant="secondary"
                                            size="xs"
                                            leading-icon="arrow-clockwise"
                                            label="Muat Ulang"
                                            :loading="riwayat.memuat"
                                            @click="muatRiwayat(true)"
                                        />
                                    </template>

                                    <div v-if="riwayat.memuat && !riwayat.baris.length" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        <span class="text-muted small">Memuat rekam jejak audit…</span>
                                    </div>

                                    <HcisEmptyState
                                        v-else-if="!riwayat.baris.length"
                                        icon="journal-text"
                                        title="Belum Ada Jejak Audit"
                                        description="Riwayat audit dan log perubahan dokumen akan dicatat secara otomatis saat ada pembaruan dokumen atau pengesahan versi."
                                        variant="flat"
                                        sentiment="neutral"
                                        size="sm"
                                        class="py-3"
                                    />

                                    <HcisTimeline
                                        v-else
                                        :items="auditTimelineItems"
                                        variant="card"
                                        sentiment="brand"
                                        class="p-1"
                                    />
                                </HcisCard>
                            </div>
                        </template>
                    </template>
                </HcisCard>
            </main>
        </div>

        <!-- ══ 5. MODAL CREATE / EDIT IDENTITAS (SMART GUIDED FORM MODAL) ══ -->
        <SopMasterDokumenFormModal
            :show="form.open"
            :item="form.rawItem"
            :saving="form.saving"
            :tipe-opsi="tipeOpsiBaru"
            :jenis-opsi="jenisOpsi"
            :apqc-opsi="apqcOpsi"
            :entitas-opsi="entitasOpsi"
            :departemen-opsi="departemenOpsi"
            :saran-no-dokumen="saranNoDokumen"
            :saran-loading="saranLoading"
            :saran-error="saranError"
            :dokumen-terakhir="dokumenTerakhir"
            :pratinjau-nomor-lengkap="pratinjauNomorLengkap"
            :segmen-kosong="segmenNomorKosong"
            @close="form.open = false"
            @submit="handleFormSubmit"
            @tipe-change="onFormTipeChange"
            @segment-change="onFormSegmentChange"
            @no-dokumen-input="onFormNoDokumenInput"
            @apply-saran="onFormApplySaran"
            @retry-saran="mintaSaranNomor"
        />

        <!-- KONFIRMASI PENONAKTIFAN. Sebarannya diambil sendiri dari server,
             bukan diwariskan dari kartu detail: kartu itu menjumlahkan hitungan
             per-paket sehingga orang yang memegang dokumen lewat dua paket
             terhitung dua kali. -->
        <SopMasterDokumenImporModal
            :show="imporModal"
            @close="imporModal = false"
            @berhasil="segarkan()"
        />

        <SopMasterDokumenNonaktifModal
            :show="nonaktifModal"
            :id="terpilih"
            :dokumen="detailRow"
            @close="tutupNonaktif"
            @berhasil="setelahNonaktif"
        />

        <!-- ══ 6. SUB-COMPONENTS & DIALOGS ══ -->
        <!-- PEMINDAH TAHAP VERSI.
             Daftar tujuan datang dari server: alurnya tersimpan sebagai data
             dan berubah tanpa rilis, jadi layar yang menghafalnya akan
             menawarkan langkah yang nanti ditolak. -->
        <HcisDialog
            :model-value="Boolean(tahapVersi.versi)"
            title="Pindahkan Tahap Versi"
            kicker="Proses Versi"
            icon="signpost-split"
            size="sm"
            sentiment="brand"
            header-divider
            @update:model-value="(v) => { if (!v) tutupTahapVersi(); }"
        >
            <p class="text-muted small mb-3">
                Versi <strong>{{ labelVersi(tahapVersi.versi?.Versi_Ke) }}</strong> sekarang di tahap
                <strong>{{ tahapVersi.kini?.Nama_Tahap || '—' }}</strong>.
            </p>

            <div v-if="tahapVersi.memuat" class="text-center py-3 text-muted small">Memuat tahap…</div>

            <HcisEmptyState
                v-else-if="!tahapVersi.tujuan.length"
                icon="signpost"
                title="Tak Ada Langkah Berikutnya"
                description="Tahap ini adalah tahap akhir. Lanjutkan dengan membuat versi baru."
                variant="flat"
                sentiment="neutral"
                size="sm"
            />

            <div v-else class="d-grid gap-2">
                <button
                    v-for="t in tahapVersi.tujuan"
                    :key="t.Kode_Tahap"
                    type="button"
                    class="mdx-tahap-opsi"
                    :style="{ '--tc': t.Warna || '#64748b' }"
                    :disabled="tahapVersi.menyimpan"
                    @click="pindahTahapVersi(t)"
                >
                    <span class="mdx-tahap-opsi__nama">{{ t.Nama_Tahap }}</span>
                    <span v-if="t.Deskripsi" class="mdx-tahap-opsi__ket">{{ t.Deskripsi }}</span>
                    <span v-if="t.Butuh_Berkas" class="mdx-tahap-opsi__syarat">
                        <i class="bi bi-paperclip me-1"></i>menuntut berkas dan pengesahan
                    </span>
                </button>
            </div>

            <p v-if="tahapVersi.galat" class="text-danger small mt-3 mb-0">{{ tahapVersi.galat }}</p>
        </HcisDialog>

        <!-- Modal Rinci Detail Versi Dokumen -->
        <SopVersiDetailModal
            v-model="modalDetailVersiBuka"
            :versi="versiTerpilihDetail"
            :dokumen="detail?.dokumen || detailRow"
            :id-dokumen="terpilih"
        />

        <SopDokumenVersiModal
            :show="Boolean(versiDok)"
            :dokumen="versiDok"
            :sunting-awal="suntingAwal"
            @close="
                versiDok = null;
                suntingAwal = null;
            "
            @berubah="segarkan"
        />

        <SopBatalTerbitDialog
            :versi="batalTerbit"
            @close="batalTerbit = null"
            @berhasil="segarkan"
        />

        <SopTerbitPratinjau :versi="pratinjau" @close="pratinjau = null" @berhasil="setelahTerbit" />

        <SopTerbitPratinjau :versi="kembalikan" mode="kembali" @close="kembalikan = null" @berhasil="setelahTerbit" />

        <!-- PRATINJAU ADMIN MEMAKAI VIEWER BERKAS PENUH.
             SopKontenViewer merender dokumen sebagai iframe telanjang: tak ada
             unduh, tak ada cetak, tak ada zoom/putar/layar penuh. Itu memang
             cukup untuk karyawan yang tugasnya membaca — tapi admin di Master
             Dokumen sedang MEMERIKSA berkas, dan memeriksa berarti memperbesar,
             mencetak, dan menyimpan salinan.

             Video tetap lewat SopKontenViewer: muatannya URL embed Bunny, yakni
             iframe pemutar, bukan berkas media yang bisa dibuka viewer berkas. -->
        <HcisFileViewer
            v-if="berkasDokumen"
            :model-value="berkasTerbuka"
            :file="berkasDokumen"
            :title="detail && detail.dokumen ? detail.dokumen.Judul : ''"
            allow-download
            allow-print
            allow-external-link
            @update:model-value="(buka) => !buka && tutupBerkas()"
            @close="tutupBerkas"
        />

        <SopKontenViewer
            v-else
            :show="berkasTerbuka"
            :konten="berkasAktif"
            :judul="detail && detail.dokumen ? detail.dokumen.Judul : ''"
            :tipe="detail && detail.dokumen ? detail.dokumen.Tipe : 'DOKUMEN'"
            readonly
            admin
            @close="tutupBerkas"
        />
    </div>
</template>

<script>
import axios from 'axios';
import {
    HcisHeader,
    HcisCard,
    HcisButton,
    HcisBadge,
    HcisAlert,
    HcisStatCard,
    HcisStatGroup,
    HcisTimeline,
    HcisField,
    HcisDialog,
    HcisPagination,
    HcisEmptyState,
    HcisFileViewer,
    HcisTabs,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    hcisMessage,
} from '@/components/ui';
import SopMasterDokumenStats from './Components/SopMasterDokumenStats.vue';
import SopMasterDokumenCard from './Components/SopMasterDokumenCard.vue';
import SopMasterDokumenFormModal from './Components/SopMasterDokumenFormModal.vue';
import SopMasterDokumenNonaktifModal from './Components/SopMasterDokumenNonaktifModal.vue';
import SopMasterDokumenImporModal from './Components/SopMasterDokumenImporModal.vue';
import SopDokumenVersiModal from '../../Shared/components/ui/SopDokumenVersiModal.vue';
import SopVersiDetailModal from '../../Shared/components/ui/SopVersiDetailModal.vue';
import SopTerbitPratinjau from '../../Shared/components/ui/SopTerbitPratinjau.vue';
import SopBatalTerbitDialog from '../../Shared/components/ui/SopBatalTerbitDialog.vue';
import SopKontenViewer from '../../Shared/components/ui/SopKontenViewer.vue';
import SopDokumenAlur from '../../Shared/components/ui/SopDokumenAlur.vue';
import pageMeta from '../../Shared/support/pageMeta.js';
import { BLOK, blokBerlaku, temaAksen } from '../../Shared/support/constants';
import { nomorDokumen, labelVersi } from '../../Shared/support/versi.js';
import { sopMasterDataApi, labelDepartemen, labelJenisDokumen, labelEntitas } from '../SopJenisDokumen/api/sopJenisDokumenApi.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/admin/dokumen';
const PER_HAL = 15;

export default {
    name: 'SopMasterDokumen',
    components: {
        HcisHeader,
        HcisCard,
        HcisButton,
        HcisBadge,
        HcisAlert,
        HcisStatCard,
        HcisStatGroup,
        HcisTimeline,
        HcisField,
        HcisDialog,
        HcisPagination,
        HcisEmptyState,
        HcisFileViewer,
        HcisTabs,
        HcisSelect,
        HcisSelectOption,
        HcisSkeleton,
        SopMasterDokumenStats,
        SopMasterDokumenCard,
        SopMasterDokumenFormModal,
        SopMasterDokumenNonaktifModal,
        SopMasterDokumenImporModal,
        SopDokumenVersiModal,
        SopVersiDetailModal,
        SopTerbitPratinjau,
        SopBatalTerbitDialog,
        SopKontenViewer,
        SopDokumenAlur,
    },
    mixins: [pageMeta],
    data() {
        const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
        return {
            dokDiminta: searchParams ? searchParams.get('dok') : null,
            tabDiminta: searchParams ? searchParams.get('tab') : null,
            rows: [],
            total: 0,
            page: 1,
            totalPage: 1,
            perHal: PER_HAL,
            tipeOpsi: [],
            tipeOpsiBaru: [],
            q: '',
            tipe: '',
            urut: 'terbaru',
            tapis: null,
            jenis: '',
            departemen: '',
            entitas: '',
            lingkup: '',
            apqc: '',
            /* Daftar jenis TANPA saringan tipe — dipakai dropdown filter, yang
               memang harus bisa menyebut jenis apa pun, bukan hanya yang sah
               bagi tipe yang kebetulan sedang dipilih di form. */
            semuaJenisOpsi: [],
            ringkas: {},
            loading: false,
            timer: null,
            tapisan: [
                { key: null, label: 'Semua' },
                { key: 'PERHATIAN', label: 'Perlu Ditinjau' },
                { key: 'BELUM_LENGKAP', label: 'Belum Lengkap' },
                { key: 'SIAP', label: 'Belum Dipakai' },
                /* Pintu terpisah, dan sengaja paling kanan: populasinya keluar
                   dari daur hidup dokumen, jadi ia bukan tahap keempat
                   melainkan tempat lain. Dari sini daftarnya terurut dari yang
                   PALING TERAKHIR dinonaktifkan. */
                { key: 'NONAKTIF', label: 'Dibatalkan' },
            ],
            terpilih: null,
            detailRow: null,
            detail: null,
            detailLoading: false,
            berkasTerbuka: false,
            versiDok: null,
            riwayat: { baris: [], memuat: false, untuk: null },
            tahapVersi: { versi: null, kini: null, tujuan: [], memuat: false, menyimpan: false, galat: '' },
            tab: (searchParams ? searchParams.get('tab') : null) || 'ringkasan',
            detailVersi: {
                rows: [],
                total: 0,
                page: 1,
                total_page: 1,
                memuat: false,
            },
            detailVersiPerPage: 5,
            nonaktifModal: false,
            imporModal: false,
            pratinjau: null,
            berkasSibuk: null,
            berkasVersi: null,
            menghapus: null,
            suntingAwal: null,
            kembalikan: null,
            batalTerbit: null,
            modalDetailVersiBuka: false,
            versiTerpilihDetail: null,
            form: {
                open: false,
                id: null,
                Judul: '',
                Nomor_SOP_Resmi: '',
                Tipe: 'DOKUMEN',
                Deskripsi: '',
                Jenis_Dokumen_Id: '',
                APQC_Id: '',
                Departemen_Id: '',
                Entitas_Pemilik_Id: '',
                Entitas_Lingkup_Id: '',
                No_Dokumen: '',
                saving: false,
                dipakai: false,
            },
            /* Opsi tata kelola. jenisOpsi TERGANTUNG tipe yang sedang dipilih:
               menawarkan jenis yang nanti ditolak server adalah cara tercepat
               membuat admin mengira sistemnya rusak. */
            jenisOpsi: [],
            apqcOpsi: [],
            semuaApqcOpsi: [],
            departemenOpsi: [],
            entitasOpsi: [],
            /* Penanda layar, bukan data: menandai admin sudah memilih ruang
               lingkup sendiri, sehingga ia tak ditimpa lagi oleh entitas pemilik. */
            lingkupDisentuh: false,
            /* Saran nomor dari server untuk kombinasi yang sedang dipilih.
               Disimpan terpisah dari form supaya "Pakai saran" tetap bisa
               mengembalikan angka sistem setelah admin menimpanya. */
            saranNoDokumen: null,
            saranLoading: false,
            saranError: false,
            dokumenTerakhir: null,
            timerNomor: null,
            saranSeq: 0,
            pratinjauNomorLengkap: '',
            segmenNomorKosong: [],
            nomorDisentuh: false,
            mobileTab: 'daftar',
            showFacetedFilter: false,
            nomorTersalin: false,
            timerSalin: null,
        };
    },
    computed: {
        activeFacetedFilterCount() {
            let count = 0;
            if (this.jenis) count++;
            if (this.departemen) count++;
            if (this.apqc) count++;
            if (this.entitas) count++;
            if (this.lingkup) count++;
            if (this.tipe) count++;
            return count;
        },
        activeFilterTags() {
            const tags = [];
            if (this.jenis) {
                const found = this.semuaJenisOpsi.find((j) => j.Id === this.jenis);
                const label = this.jenis === 'BELUM' ? 'Belum Diklasifikasi' : (found?.Nama_Jenis || 'Jenis');
                tags.push({ key: 'jenis', label: `Jenis: ${label}` });
            }
            if (this.departemen) {
                const found = this.departemenOpsi.find((dp) => dp.Id === this.departemen);
                const label = this.departemen === 'BELUM' ? 'Tanpa Pemilik' : (found?.Kode_Departemen || 'Dept');
                tags.push({ key: 'departemen', label: `Dept: ${label}` });
            }
            if (this.apqc) {
                const found = this.semuaApqcOpsi.find((a) => a.Id === this.apqc);
                const label = this.apqc === 'BELUM' ? 'Belum Dipetakan' : (found?.Kode_Proses ? `PCF #${found.Kode_Proses}` : 'APQC');
                tags.push({ key: 'apqc', label: `APQC: ${label}` });
            }
            if (this.entitas) {
                const found = this.entitasOpsi.find((e) => e.Id === this.entitas);
                const label = this.entitas === 'BELUM' ? 'Tanpa Entitas' : (found?.Kode_Entitas || 'Entitas');
                tags.push({ key: 'entitas', label: `PT: ${label}` });
            }
            if (this.lingkup) {
                const found = this.entitasOpsi.find((e) => e.Id === this.lingkup);
                const label = this.lingkup === 'BELUM' ? 'Tanpa Lingkup' : (found?.Kode_Entitas || 'Lingkup');
                tags.push({ key: 'lingkup', label: `Lingkup: ${label}` });
            }
            if (this.tipe) {
                tags.push({ key: 'tipe', label: `Tipe: ${this.tipe}` });
            }
            return tags;
        },
        pakaiNomorResmi() {
            return blokBerlaku(this.form.Tipe, BLOK.NOMOR_RESMI);
        },
        /* Divisi tak lagi ditanyakan: yang dipakai operasional hanya Departemen,
           dan divisinya diturunkan server dari departemen yang dipilih. */
        /** Bentuk tiga digit dari nilai yang sedang diketik, mis. 3 -> "003". */
        pratinjauNoDokumen() {
            return nomorDokumen(this.form.No_Dokumen === '' ? null : this.form.No_Dokumen);
        },
        /* Tombol simpan menegakkan kewajiban yang sama dengan server. Ini bukan
           penggantinya — validasi server tetap satu-satunya yang mengikat —
           melainkan supaya admin tak menunggu balasan 422 untuk sesuatu yang
           sudah terlihat kosong di layar. */
        bisaSimpan() {
            if (!this.form.Judul.trim()) return false;
            if (!this.form.Departemen_Id) return false;
            if (!this.form.Entitas_Pemilik_Id || !this.form.Entitas_Lingkup_Id) return false;
            if (this.jenisOpsi.length && !this.form.Jenis_Dokumen_Id) return false;
            if (this.apqcOpsi.length && !this.form.APQC_Id) return false;

            return true;
        },
        versiBerlaku() {
            return ((this.detail && this.detail.riwayat_versi) || []).find((v) => v.Status === 'PUBLISHED') || null;
        },
        sibukBerlaku() {
            return Boolean(this.versiBerlaku && this.berkasSibuk === this.versiBerlaku.Id_SOP_Item_Versi);
        },
        paketUrut() {
            return [...(this.detail?.paket_pemakai || [])].sort(
                (a, b) => (b.Jumlah_Karyawan || 0) - (a.Jumlah_Karyawan || 0),
            );
        },
        isKuis() {
            return this.detail?.dokumen?.Tipe === 'KUIS';
        },
        isForm() {
            return this.detail?.dokumen?.Tipe === 'FORM';
        },
        blokPengesah() {
            return this.versiBerlaku?.Pengesah || [];
        },
        /* Muatan berkas yang sedang dibuka: milik versi yang dipilih, atau
           berkas versi berlaku dokumennya bila tak ada yang dipilih. */
        berkasAktif() {
            return this.berkasVersi || this.detail?.berkas || null;
        },

        /**
         * Berkas yang bisa ditangani viewer berkas penuh.
         *
         * HANYA `document`. `video` adalah URL embed Bunny — iframe pemutar,
         * bukan berkas media, jadi viewer berkas hanya akan menampilkan bingkai
         * kosong. `unduhan` (item FORM) tak pernah sampai ke sini: jalurnya
         * membuka tab unduhan dan tak pernah membuka viewer.
         */
        berkasDokumen() {
            const k = this.berkasAktif;
            if (!k || k.type !== 'document' || !k.url) return null;

            return {
                url: k.url,
                name: k.nama_berkas || k.judul || 'Berkas Dokumen',
                type: k.mime || null,
            };
        },

        /* KEADAAN KOSONG YANG MENYEBUT SEBABNYA.

           Sejak kartu ringkas bisa ditekan meski angkanya nol, daftar kosong
           sering merupakan jawaban yang BENAR — bukan kegagalan. Yang membuatnya
           terbaca sebagai kerusakan adalah kalimat generik: "tak ada yang cocok
           dengan filter" tak menyebut filter apa, sehingga admin tak bisa
           membedakan "tak ada yang perlu ditinjau" dari "layarnya rusak". */
        kosongJudul() {
            if (this.q) return 'Tidak Ada yang Cocok';
            if (this.tapis === 'PERHATIAN') return 'Tak Ada yang Perlu Ditinjau';
            if (this.tapis === 'BELUM_LENGKAP') return 'Semua Dokumen Sudah Lengkap';

            return this.tapis ? 'Tidak Ada Dokumen di Pintu Ini' : 'Belum Ada Dokumen';
        },

        kosongKalimat() {
            if (this.q) return `Tak ada dokumen yang cocok dengan "${this.q}".`;
            if (this.tapis === 'PERHATIAN') {
                return 'Tak ada dokumen berlaku yang masa berlakunya atau jadwal reviewnya '
                    + 'sudah lewat maupun tinggal 90 hari lagi.';
            }
            if (this.tapis === 'BELUM_LENGKAP') {
                return 'Setiap dokumen sudah punya versi yang berlaku.';
            }
            if (this.tapis === 'NONAKTIF') return 'Belum ada dokumen yang dinonaktifkan.';
            if (this.tapis) return 'Tak ada dokumen yang cocok dengan penyaring ini.';

            return 'Belum ada dokumen.';
        },

        rentang() {
            if (!this.rows.length) return '0 dari ' + this.total;
            const awal = (this.page - 1) * this.perHal + 1;

            return `${awal}–${awal + this.rows.length - 1} dari ${this.total}`;
        },
        labelTapis() {
            return (this.tapisan.find((t) => t.key === this.tapis) || {}).label || '';
        },
        tabs() {
            const paketCount = this.detail?.paket_pemakai?.length ?? 0;
            const versiCount = this.detailVersi.total || (this.detail?.riwayat_versi ? this.detail.riwayat_versi.length : 0);

            return [
                {
                    key: 'ringkasan',
                    label: 'Ringkasan',
                    icon: 'file-earmark-text',
                },
                {
                    key: 'paket',
                    label: 'Paket & Alur',
                    icon: 'diagram-3',
                    badge: paketCount,
                    badgeVariant: 'primary',
                },
                {
                    key: 'versi',
                    label: 'Versi',
                    icon: 'clock-history',
                    badge: versiCount,
                    badgeVariant: 'primary',
                },
                {
                    key: 'riwayat',
                    label: 'Riwayat Audit',
                    icon: 'journal-text',
                    badge: this.riwayat.baris?.length ? this.riwayat.baris.length : undefined,
                    badgeVariant: 'neutral',
                },
            ];
        },
        auditTimelineItems() {
            if (!this.riwayat?.baris?.length) return [];
            return this.riwayat.baris.map((r, idx) => {
                const isPublish = r.Aksi === 'VERSI_PUBLISH';
                const isDanger = r.Aksi === 'VERSI_HAPUS' || r.Aksi === 'DOK_NONAKTIF';
                const sentiment = isPublish ? 'success' : isDanger ? 'danger' : 'brand';
                const icon = isPublish ? 'patch-check-fill' : isDanger ? 'trash3-fill' : 'journal-text';

                const meta = (r.Perubahan || []).map((p) => ({
                    label: p.field || 'Perubahan',
                    value: `${p.lama || 'kosong'} → ${p.baru || 'kosong'}`,
                }));

                return {
                    id: r.Id_SOP_Log || idx,
                    title: this.labelAksi(r.Aksi),
                    name: r.Oleh_Nama || r.Oleh_Username || 'Sistem',
                    role: r.Versi_Ke !== null && r.Versi_Ke !== undefined ? this.$vlabel(r.Versi_Ke) : undefined,
                    time: this.waktuJejak(r.Created_At),
                    content: r.Keterangan || '',
                    sentiment,
                    status: r.Versi_Ke !== null && r.Versi_Ke !== undefined ? this.$vlabel(r.Versi_Ke) : undefined,
                    icon,
                    meta,
                };
            });
        },
        versiTimelineItems() {
            const list = this.detailVersi.rows?.length ? this.detailVersi.rows : (this.detail?.riwayat_versi || []);
            if (!list.length) return [];
            return list.map((v, idx) => {
                /* Dibatalkan datang dari server — jawaban yang sama yang dipakai
                   penjaga di SopDokumenService. Turunan lama membaca nama tahap
                   dan mencari kata "batal" di dalamnya; nama tahapnya berbunyi
                   "Cancel", jadi cabang itu tak pernah menyala. Yang menahannya
                   selama ini cuma satu cabang Tahap_Kode. */
                const isCancelled = v.Dibatalkan === true;
                const isPublished = v.Status === 'PUBLISHED' && !isCancelled;
                /* Boleh_Disunting adalah FAKTA dari server, bukan turunan
                   layar. Yang lama — Status DRAFT dikurangi batal — kebetulan
                   menjawab sama, tapi ia jawaban KEDUA untuk pertanyaan yang
                   sudah dijawab SopVersiKeadaan, dan jawaban kedua itulah yang
                   menyimpang saat aturannya bergeser. */
                const isDraft = v.Boleh_Disunting === true;
                const isObsolete = v.Status === 'OBSOLETE' || (!isPublished && !isDraft && !isCancelled);

                const sentiment = isCancelled ? 'danger' : (isPublished ? 'success' : (isDraft ? 'warning' : 'neutral'));
                const icon = isCancelled ? 'x-circle-fill' : (isPublished ? 'patch-check-fill' : (isDraft ? 'pencil-square' : 'archive-fill'));
                const vLabel = this.$vlabel(v.Versi_Ke);

                const dibuatStr = this.waktuJejak(v.Created_At);
                const terbitStr = v.Tanggal_Publish
                    ? this.waktuJejak(v.Tanggal_Publish)
                    : (isPublished && v.Tanggal_Pengesahan ? this.waktuJejak(v.Tanggal_Pengesahan) : (isDraft ? null : (v.Tanggal_Berlaku ? this.waktuJejak(v.Tanggal_Berlaku) : null)));
                const rentangMasa = isCancelled ? 'Dibatalkan (Arsip)' : this.formatRentangMasa(v, idx, list);

                const meta = [
                    { label: 'Dibuat', value: dibuatStr !== '—' ? dibuatStr : '—' },
                    { label: 'Tanggal Terbit', value: isCancelled ? 'Dibatalkan' : (terbitStr || (isDraft ? 'Belum Terbit (Draft)' : '—')) },
                    { label: 'Masa Berlaku', value: rentangMasa },
                ];

                if (v.Disahkan_Oleh) {
                    meta.push({
                        label: 'Pengesah',
                        value: v.Jabatan_Pengesah ? `${v.Disahkan_Oleh} (${v.Jabatan_Pengesah})` : v.Disahkan_Oleh,
                    });
                }
                if (v.Nomor_Pengesahan) {
                    meta.push({ label: 'No. Sah', value: v.Nomor_Pengesahan });
                }
                /* Kurang_Terbit adalah checklist tahap terbit apa adanya dari
                   server — bukan tebakan layar. Begitu ia terisi, ia menggantikan
                   peringatan lama: "Tanpa Berkas" menyebut satu hal padahal yang
                   menahan versi ini bisa juga tanggal berlaku atau pengesahnya,
                   dan admin yang menambal satu-satunya hal yang disebut akan
                   kembali menemui tombol terbit yang tetap tak muncul. */
                if (v.Kurang_Terbit?.length) {
                    meta.push({ label: 'Belum Lengkap', value: v.Kurang_Terbit.join(' · ') });
                } else if (this.isiVersiKosong(v)) {
                    meta.push({ label: 'Peringatan', value: this.isKuis ? 'Tanpa Ujian' : 'Tanpa Berkas' });
                }

                return {
                    id: v.Id_SOP_Item_Versi || idx,
                    title: `${vLabel} · ${v.Tahap_Nama || (isCancelled ? 'Dibatalkan (Arsip)' : (isPublished ? 'Versi Berlaku' : isDraft ? 'Draft Revisi' : 'Versi Arsip'))}`,
                    tahapNama: isCancelled ? 'DIBATALKAN' : (v.Tahap_Nama || null),
                    tahapWarna: isCancelled ? '#ef4444' : (v.Tahap_Warna || null),
                    tahapKode: v.Tahap_Kode || null,
                    /* Keadaan versi disediakan SEKALI di sini, bukan dihitung ulang
                       di templat aksinya. Templat sempat menanyakan hal yang sama
                       dengan ekspresi lain — memeriksa Status lebih dulu — sehingga
                       versi batal (Status tetap DRAFT, yang berpindah TAHAPNYA)
                       menawarkan "Sunting Draft" di kartu yang lencananya sendiri
                       berbunyi "Dibatalkan (Arsip)". */
                    dibatalkan: isCancelled,
                    draft: isDraft,
                    /* Versi terbit yang masih menerima perubahan terbatas —
                       dijawab server lewat Field_Boleh_Ubah, bukan disimpulkan
                       dari status di sini. */
                    bolehUbahTerbatas: !isDraft && !isCancelled
                        && Array.isArray(v.Field_Boleh_Ubah) && v.Field_Boleh_Ubah.length > 0,
                    raw: v,
                    name: v.Disahkan_Oleh ? `Disahkan oleh ${v.Disahkan_Oleh}` : undefined,
                    role: isCancelled ? 'Dibatalkan (Arsip)' : (isPublished ? 'Versi Aktif' : isDraft ? 'Draft' : 'Arsip'),
                    time: isCancelled ? 'Dibatalkan (Arsip)' : (isDraft ? 'Draft (Belum Terbit)' : (terbitStr ? `Terbit: ${terbitStr}` : (v.Tanggal_Berlaku ? `Berlaku: ${v.Tanggal_Berlaku}` : ''))),
                    content: v.Ringkasan_Perubahan || (isCancelled ? 'Versi ini telah dibatalkan pada siklus tata kelola dan tersimpan sebagai arsip audit trail.' : (isPublished ? 'Versi aktif yang digunakan pada seluruh paket tata kelola operasional.' : '')),
                    status: isCancelled ? 'Dibatalkan' : (isPublished ? 'Berlaku' : isDraft ? 'Draft' : 'Arsip'),
                    sentiment,
                    icon,
                    initials: vLabel,
                    avatarText: vLabel,
                    active: isPublished,
                    dibuat: dibuatStr,
                    terbit: terbitStr,
                    rentangMasa,
                    meta,
                };
            });
        },
    },
    watch: {
        tab(t) {
            if (t === 'riwayat') this.muatRiwayat();
            if (t === 'versi' && !this.detailVersi.rows.length) this.muatDetailVersi(1);
        },
        'form.Entitas_Lingkup_Id'(baru, lama) {
            /* Hanya perubahan dari tangan admin yang mengunci; penyamaan otomatis
               di ikutkanLingkup() menulis nilai yang sama dengan pemilik. */
            if (this.form.open && lama !== undefined && baru && baru !== this.form.Entitas_Pemilik_Id) {
                this.lingkupDisentuh = true;
            }
        },
        /* Lima penentu kombinasi. Berubahnya salah satu berarti berubahnya deret,
           jadi sarannya diminta ulang — dan bila admin belum menyentuh nomornya
           sendiri, nilainya ikut disegarkan. */
        'form.Jenis_Dokumen_Id'() { this.mintaSaranNomor(); },
        'form.APQC_Id'() { this.mintaSaranNomor(); },
        'form.Departemen_Id'() { this.mintaSaranNomor(); },
        'form.Entitas_Pemilik_Id'() { this.mintaSaranNomor(); },
        'form.Entitas_Lingkup_Id'() { this.mintaSaranNomor(); },
        'form.No_Dokumen'(baru, lama) {
            if (this.form.open && lama !== undefined && baru !== this.saranNoDokumen) {
                this.nomorDisentuh = true;
            }
            /* Pratinjau ikut nomor yang diketik. Ditunda sesaat supaya
               mengetik "12" tidak menembak dua permintaan. */
            clearTimeout(this.timerNomor);
            this.timerNomor = setTimeout(() => this.mintaSaranNomor(), 350);
        },
        'form.Tipe'(baru) {
            if (!blokBerlaku(baru, BLOK.NOMOR_RESMI)) {
                this.form.Nomor_SOP_Resmi = '';
            }
            this.muatJenisOpsi(baru);
            this.muatApqcOpsi(baru);
        },
    },
    mounted() {
        document.title = 'Master Dokumen | KPI-Unreal';
        this.muat(1);
        this.muatUnitOpsi();
        this.muatEntitasOpsi();
        this.muatSemuaJenis();
        this.muatSemuaApqc();
        window.addEventListener('keydown', this.pintasan);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.pintasan);
        clearTimeout(this.timer);
        clearTimeout(this.timerNomor);
        clearTimeout(this.timerSalin);
    },
    methods: {
        getTypeVariant(type) {
            switch (type) {
                case 'DOKUMEN': return 'brand';
                case 'KUIS': return 'purple';
                case 'FORM': return 'teal';
                case 'VIDEO': return 'warning';
                default: return 'neutral';
            }
        },
        getTypeMeta(type) {
            switch (String(type || '').toUpperCase()) {
                case 'DOKUMEN':
                    return { icon: 'bi-file-earmark-text-fill', color: '#4f46e5', bg: '#e0e7ff', border: '#c7d2fe', label: 'Dokumen' };
                case 'FORM':
                    return { icon: 'bi-ui-checks-grid', color: '#0d9488', bg: '#ccfbf1', border: '#99f6e4', label: 'Form' };
                case 'VIDEO':
                    return { icon: 'bi-play-circle-fill', color: '#d97706', bg: '#fef3c7', border: '#fde68a', label: 'Video' };
                case 'KUIS':
                    return { icon: 'bi-patch-question-fill', color: '#7c3aed', bg: '#ede9fe', border: '#ddd6fe', label: 'Kuis' };
                case 'TAUTAN':
                    return { icon: 'bi-link-45deg', color: '#0284c7', bg: '#e0f2fe', border: '#bae6fd', label: 'Tautan' };
                case 'PARAGRAF':
                    return { icon: 'bi-text-paragraph', color: '#64748b', bg: '#f1f5f9', border: '#e2e8f0', label: 'Paragraf' };
                case 'ABSENSI':
                    return { icon: 'bi-fingerprint', color: '#e11d48', bg: '#ffe4e6', border: '#fecdd3', label: 'Absensi' };
                default:
                    return { icon: 'bi-journal-text', color: '#475569', bg: '#f1f5f9', border: '#e2e8f0', label: type || 'Dokumen' };
            }
        },
        async muatRiwayat(paksa = false) {
            const id = this.detailRow?.Id_SOP_Dokumen;
            if (!id) return;

            if (!paksa && this.riwayat.untuk === id) return;

            this.riwayat.memuat = true;
            try {
                const r = await axios.get(`${API}/${id}/riwayat`);
                this.riwayat.baris = r.data?.result || [];
                this.riwayat.untuk = id;
            } catch (e) {
                this.riwayat.baris = [];
                this.riwayat.untuk = null;
                hcisMessage.error(e?.response?.data?.message || 'Gagal memuat riwayat dokumen ini.');
            } finally {
                this.riwayat.memuat = false;
            }
        },
        labelAksi(a) {
            return (
                {
                    DOK_BUAT: 'Dokumen dibuat',
                    DOK_UBAH: 'Metadata diubah',
                    DOK_NONAKTIF: 'Dokumen dinonaktifkan',
                    VERSI_BUAT: 'Versi dibuat',
                    VERSI_UBAH: 'Versi diubah',
                    VERSI_HAPUS: 'Versi dihapus',
                    VERSI_PUBLISH: 'Versi diterbitkan',
                    VERSI_ROLLBACK: 'Versi dikembalikan',
                }[a] || a
            );
        },
        toneAksi(a) {
            if (a === 'VERSI_PUBLISH') return 'ok';
            if (a === 'VERSI_HAPUS' || a === 'DOK_NONAKTIF') return 'bahaya';
            if (a === 'VERSI_ROLLBACK') return 'balik';

            return 'netral';
        },
        waktuJejak(t) {
            if (!t) return '—';
            const d = new Date(String(t).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return String(t);

            return d.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        formatTanggalSingkat(t) {
            if (!t) return '—';
            const clean = String(t).split(' ')[0];
            const parts = clean.split('-');
            if (parts.length === 3) {
                const [y, m, d] = parts;
                const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const bIdx = parseInt(m, 10) - 1;
                if (bIdx >= 0 && bIdx < 12) {
                    return `${parseInt(d, 10)} ${bulan[bIdx]} ${y}`;
                }
            }
            return clean;
        },
        lebarBar(p) {
            const n = p.Jumlah_Karyawan || 0;
            return n === 0 ? '3px' : Math.max(6, Math.round((n / (this.detail?.total_karyawan || 100)) * 100)) + '%';
        },
        pintasan(e) {
            if ((e.key === '/' || (e.ctrlKey && e.key === 'k')) && !/input|textarea|select/i.test(e.target.tagName)) {
                e.preventDefault();
                const inputEl = document.getElementById('sop-master-search-input');
                if (inputEl) inputEl.focus();
            }
        },
        pilihTapis(k) {
            this.tapis = this.tapis === k ? null : k;
            this.muat(1);
        },
        async pindah(i) {
            if (i > this.rows.length - 1 && this.page < this.totalPage) {
                await this.muat(this.page + 1);
                return this.pindah(0);
            }
            if (i < 0 && this.page > 1) {
                await this.muat(this.page - 1);
                return this.pindah(this.rows.length - 1);
            }

            const n = Math.max(0, Math.min(this.rows.length - 1, i));
            const d = this.rows[n];
            if (!d) return;

            this.pilih(d);
            this.$nextTick(() => {
                const el = this.$refs.daftar?.children?.[n];
                el?.focus?.();
                el?.scrollIntoView?.({ block: 'nearest' });
            });
        },
        cariDebounce() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.muat(1), 300);
        },
        async muat(page = 1) {
            if (typeof page === 'object' && page !== null) {
                page = page.page || page.currentPage || 1;
            }
            const targetPage = Math.max(1, Number(page) || 1);
            this.page = targetPage;
            this.loading = true;
            try {
                const r = await axios.get(`${API}/current`, {
                    params: {
                        q: this.q || undefined,
                        tipe: this.tipe || undefined,
                        tahap: this.tapis || undefined,
                        jenis: this.jenis || undefined,
                        departemen: this.departemen || undefined,
                        entitas: this.entitas || undefined,
                        lingkup: this.lingkup || undefined,
                        apqc: this.apqc || undefined,
                        urut: this.urut,
                        page: targetPage,
                        limit: this.perHal || PER_HAL,
                    },
                });
                const d = r.data?.result || {};
                this.rows = d.rows || [];
                this.total = d.total || 0;
                this.page = d.page || targetPage;
                this.totalPage = d.total_page || 1;
                this.perHal = d.per_page || PER_HAL;
                this.tipeOpsi = d.tipe_opsi || [];
                /* Daftar untuk MEMBUAT, bukan untuk menyaring. Tipe yang
                   dimatikan di Setup System hilang dari sini tapi tetap ada di
                   penyaring — dokumen lama bertipe itu masih harus bisa dicari. */
                this.tipeOpsiBaru = d.tipe_opsi_baru || d.tipe_opsi || [];
                this.ringkas = d.ringkasan || {};

                if (this.dokDiminta) {
                    const targetId = this.dokDiminta;
                    const targetTab = this.tabDiminta || this.tab || 'ringkasan';
                    this.dokDiminta = null;
                    this.tabDiminta = null;
                    this.tab = targetTab;
                    const diminta = this.rows.find((x) => String(x.Id_SOP_Dokumen) === String(targetId));
                    if (diminta) {
                        await this.pilih(diminta, true);
                        return;
                    } else {
                        await this.pilih({ Id_SOP_Dokumen: targetId }, true);
                        return;
                    }
                }

                if (!this.terpilih && this.rows.length) {
                    await this.pilih(this.rows[0]);
                } else if (this.terpilih) {
                    const masih = this.rows.find((x) => x.Id_SOP_Dokumen === this.terpilih);
                    if (masih) this.detailRow = masih;
                }
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal memuat daftar dokumen.');
            } finally {
                this.loading = false;
            }
        },
        bukaDetailVersi(v) {
            this.versiTerpilihDetail = v;
            this.modalDetailVersiBuka = true;
        },
        /* Membuka modal versi LANGSUNG pada versi itu — dipakai tombol
           "Sunting" maupun "Lengkapi Versi". Satu jalur, bukan dua yang
           pelan-pelan menyimpang. */
        suntingDraft(v) {
            this.suntingAwal = v.Id_SOP_Item_Versi;
            this.versiDok = this.detailRow;
        },
        async hapusDraft(v) {
            this.menghapus = v.Id_SOP_Item_Versi;
            try {
                await axios.delete(`${API}/versi/${v.Id_SOP_Item_Versi}`);
                hcisMessage.success('Versi draft dihapus.');
                await this.segarkan();
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal menghapus versi.');
            } finally {
                this.menghapus = null;
            }
        },
        /* Satu pintu penutup untuk kedua viewer: dua penutup yang menulis
           keadaan yang sama adalah cara salah satunya lupa membersihkan
           berkasVersi, dan pratinjau berikutnya membuka berkas yang lama. */
        tutupBerkas() {
            this.berkasTerbuka = false;
            this.berkasVersi = null;
        },

        bukaBerkasBerlaku() {
            if (this.versiBerlaku) return this.bukaIsiVersi(this.versiBerlaku);
            this.berkasTerbuka = true;
        },
        async bukaIsiVersi(v, unduh = this.isForm) {
            this.berkasSibuk = v.Id_SOP_Item_Versi;
            try {
                const r = await axios.get(`${API}/versi/${v.Id_SOP_Item_Versi}/konten`, {
                    params: unduh ? { unduh: 1 } : {},
                });
                const k = r.data?.result || null;
                if (!k) throw new Error();

                if (unduh) {
                    window.open(k.url, '_blank', 'noopener');
                    return;
                }
                this.berkasVersi = k;
                this.berkasTerbuka = true;
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal membuka isi versi ini.');
            } finally {
                this.berkasSibuk = null;
            }
        },
        async muatDetailVersi(page = 1) {
            if (!this.terpilih) return;
            const targetPage = typeof page === 'object' && page !== null ? (page.page || page.currentPage || 1) : Math.max(1, Number(page) || 1);
            this.detailVersi.page = targetPage;
            this.detailVersi.memuat = true;
            try {
                const r = await axios.get(`${API}/${this.terpilih}/versi`, {
                    params: {
                        page: targetPage,
                        limit: this.detailVersiPerPage,
                    },
                });
                const res = r.data?.result || {};
                this.detailVersi.rows = res.rows || [];
                this.detailVersi.total = res.total || 0;
                this.detailVersi.total_page = res.total_page || 1;
            } catch (e) {
                this.detailVersi.rows = [];
                this.detailVersi.total = 0;
                this.detailVersi.total_page = 1;
            } finally {
                this.detailVersi.memuat = false;
            }
        },
        async pilih(d, preserveTab = false) {
            if (!d || !d.Id_SOP_Dokumen) return;
            const currentTab = this.tab;
            this.terpilih = d.Id_SOP_Dokumen;
            this.detailRow = d;
            this.detailLoading = true;
            this.detail = null;
            if (!preserveTab) {
                this.tab = 'ringkasan';
            } else {
                this.tab = currentTab || 'ringkasan';
            }
            this.detailVersi = { rows: [], total: 0, page: 1, total_page: 1, memuat: false };
            this.riwayat = { baris: [], memuat: false, untuk: null };
            this.mobileTab = 'detail';

            try {
                const r = await axios.get(`${API}/${d.Id_SOP_Dokumen}/detail`);
                this.detail = r.data?.result || null;
                if (this.detail?.dokumen) {
                    this.detailRow = {
                        ...d,
                        ...this.detail.dokumen,
                        Jumlah_Paket_Pemakai: (this.detail.paket_pemakai || []).length,
                    };
                }
                await this.muatDetailVersi(1);
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal memuat dokumen.');
            } finally {
                this.detailLoading = false;
            }
        },
        async segarkan(targetId = null) {
            const id = targetId || this.terpilih;
            await this.muat(this.page);
            const d = this.rows.find((x) => x.Id_SOP_Dokumen === id);
            if (d) {
                await this.pilih(d, true);
            } else if (id) {
                await this.pilih({ Id_SOP_Dokumen: id }, true);
            }
        },
        bukaVersi(d) {
            this.versiDok = d;
        },
        isiVersiKosong(v) {
            return this.isKuis ? !v.Punya_Ujian : !v.Punya_Berkas;
        },
        labelMasa(v) {
            return v.Tanggal_Berlaku ? 'berlaku sejak ' + v.Tanggal_Berlaku : 'tanggal berlaku belum di-set';
        },
        formatRentangMasa(v, idx, list) {
            if (v.Status === 'DRAFT') {
                return 'Menunggu penerbitan';
            }
            const mulai = v.Tanggal_Berlaku || (v.Tanggal_Publish ? v.Tanggal_Publish.split(' ')[0] : '—');
            if (v.Status === 'PUBLISHED') {
                return `${mulai} s/d Sekarang (Aktif)`;
            }
            if (v.Status === 'OBSOLETE') {
                const versiBaru = list && idx > 0 ? list[idx - 1] : null;
                const akhir = versiBaru?.Tanggal_Berlaku || (versiBaru?.Tanggal_Publish ? versiBaru.Tanggal_Publish.split(' ')[0] : 'Digantikan');
                return `${mulai} s/d ${akhir}`;
            }
            return mulai;
        },
        async setelahTerbit() {
            this.pratinjau = null;
            this.kembalikan = null;
            await this.segarkan();
        },
        /* Konfirmasi penonaktifan pindah ke modalnya sendiri (bukan hcisConfirm)
           karena ia harus MENGAMBIL angka sebaran dari server dan MENAGIH
           catatan — dua hal yang tak muat di kotak konfirmasi satu kalimat. */
        bukaNonaktif() {
            if (!this.terpilih || this.detail?.nonaktif) return;
            this.nonaktifModal = true;
        },
        tutupNonaktif() {
            this.nonaktifModal = false;
        },
        async setelahNonaktif() {
            this.nonaktifModal = false;
            /* Kartu detail SENGAJA tetap terbuka. Dokumennya masih ada, hanya
               berubah keadaan — dan yang baru saja lahir, jejak penonaktifannya,
               justru ada di kartu ini. Menutupnya membuang satu-satunya
               konfirmasi bahwa catatannya benar-benar tersimpan. */
            const baris = this.detailRow;
            await Promise.all([this.muat(this.page), this.pilih(baris, true)]);
        },
        /**
         * Jenis yang sah bagi satu tipe. Dipanggil ulang setiap tipe berubah,
         * bukan sekali saat halaman dibuka: daftar jenis untuk DOKUMEN dan
         * untuk FORM memang berbeda, dan menyisakan pilihan lama di layar
         * membuat admin memilih sesuatu yang server pasti tolak.
         */
        async muatJenisOpsi(tipe) {
            if (!tipe) {
                this.jenisOpsi = [];

                return;
            }
            try {
                this.jenisOpsi = await sopMasterDataApi.jenisDokumen(tipe);
            } catch (e) {
                this.jenisOpsi = [];
                hcisMessage.error(e?.message || 'Gagal memuat daftar jenis dokumen.');
            }

            /* Jenis yang sudah terisi tapi tak ada di daftar tipe baru
               dikosongkan — membiarkannya berarti mengirim pasangan tak sah. */
            if (this.form.Jenis_Dokumen_Id
                && !this.jenisOpsi.some((j) => j.Id === this.form.Jenis_Dokumen_Id)) {
                this.form.Jenis_Dokumen_Id = '';
            }
        },
        async muatApqcOpsi(tipe) {
            if (!tipe) {
                this.apqcOpsi = [];

                return;
            }
            try {
                this.apqcOpsi = await sopMasterDataApi.apqc(tipe);
            } catch (e) {
                this.apqcOpsi = [];
            }
            if (this.form.APQC_Id && !this.apqcOpsi.some((a) => a.Id === this.form.APQC_Id)) {
                this.form.APQC_Id = '';
            }
        },
        /**
         * Minta saran nomor untuk kombinasi yang sedang dipilih.
         *
         * Nomor yang sudah DISENTUH admin tidak ditimpa: nilai kustom itu justru
         * alasan field ini bisa diubah — dokumen lama yang penomorannya sudah
         * beredar tak boleh tergerus oleh saran sistem.
         */
        async mintaSaranNomor() {
            if (!this.form.open) return;

            /* Penanda urutan. Memilih entitas ikut mengubah ruang lingkup,
               sehingga dua permintaan terbang hampir bersamaan — dan yang lebih
               tua bisa tiba belakangan lalu menimpa hasil yang lebih baru.
               Sejak nomor resmi ditampilkan di sini, tertimpa berarti
               memperlihatkan nomor yang salah kepada admin. */
            const seq = ++this.saranSeq;
            this.saranLoading = true;
            this.saranError = false;

            try {
                const r = await axios.get(`${API}/saran-nomor`, {
                    params: {
                        jenis: this.form.Jenis_Dokumen_Id || undefined,
                        entitas_pemilik: this.form.Entitas_Pemilik_Id || undefined,
                        departemen: this.form.Departemen_Id || undefined,
                        entitas_lingkup: this.form.Entitas_Lingkup_Id || undefined,
                        apqc: this.form.APQC_Id || undefined,
                        /* Nomor yang SEDANG diketik ikut dikirim supaya
                           pratinjaunya mengikuti, bukan memperlihatkan saran
                           sistem yang justru sedang ditolak admin. */
                        no_dokumen: this.form.No_Dokumen === '' ? undefined : this.form.No_Dokumen,
                        dokumen: this.form.id || undefined,
                    },
                });

                if (seq !== this.saranSeq) return;

                const saran = r.data?.result?.No_Dokumen;
                this.saranNoDokumen = saran === undefined ? null : saran;
                this.dokumenTerakhir = r.data?.result?.Dokumen_Terakhir || null;
                this.pratinjauNomorLengkap = r.data?.result?.Nomor_Pratinjau || '';
                this.segmenNomorKosong = r.data?.result?.Segmen_Kosong || [];
                this.saranError = false;

                if (!this.nomorDisentuh && this.saranNoDokumen !== null) {
                    this.form.No_Dokumen = this.saranNoDokumen;
                }
            } catch (e) {
                if (seq !== this.saranSeq) return;
                this.saranNoDokumen = null;
                this.dokumenTerakhir = null;
                this.pratinjauNomorLengkap = '';
                this.segmenNomorKosong = [];
                this.saranError = e?.response?.data?.message || 'Gagal memuat rekomendasi nomor urut dari server.';
            } finally {
                if (seq === this.saranSeq) {
                    this.saranLoading = false;
                }
            }
        },
        async muatSemuaApqc() {
            try {
                this.semuaApqcOpsi = await sopMasterDataApi.apqc(null);
            } catch (e) {
                this.semuaApqcOpsi = [];
            }
        },
        async muatSemuaJenis() {
            try {
                this.semuaJenisOpsi = await sopMasterDataApi.jenisDokumen(null);
            } catch (e) {
                this.semuaJenisOpsi = [];
            }
        },
        async bukaTahapVersi(v) {
            this.tahapVersi = { versi: v, kini: null, tujuan: [], memuat: true, menyimpan: false, galat: '' };
            try {
                const r = await axios.get(`${API}/versi/${v.Id_SOP_Item_Versi}/tahap`);
                this.tahapVersi.kini = r.data?.result?.Tahap || null;
                this.tahapVersi.tujuan = r.data?.result?.Tujuan || [];
            } catch (e) {
                this.tahapVersi.galat = e?.response?.data?.message || 'Gagal memuat tahap.';
            } finally {
                this.tahapVersi.memuat = false;
            }
        },
        tutupTahapVersi() {
            this.tahapVersi = { versi: null, kini: null, tujuan: [], memuat: false, menyimpan: false, galat: '' };
        },
        async pindahTahapVersi(t) {
            if (this.tahapVersi.menyimpan) return;
            this.tahapVersi.menyimpan = true;
            this.tahapVersi.galat = '';
            try {
                const r = await axios.put(
                    `${API}/versi/${this.tahapVersi.versi.Id_SOP_Item_Versi}/tahap`,
                    { Kode_Tahap: t.Kode_Tahap },
                );
                hcisMessage.success(r.data?.message || 'Tahap dipindahkan.');
                const baris = this.detailRow;
                this.tutupTahapVersi();
                /* Tab dipertahankan: admin sedang berdiri di tab Versi, dan
                   melemparnya kembali ke Ringkasan setiap kali menggeser tahap
                   membuat pekerjaan berurutan terasa seperti diulang. */
                if (baris) await this.pilih(baris, true);
            } catch (e) {
                /* Penolakan alur, berkas, dan pengesah datang sebagai 422 dengan
                   kalimat yang sudah ditulis untuk admin — ditampilkan apa
                   adanya, bukan diganti pesan umum yang tak bisa dikerjakan. */
                this.tahapVersi.galat = e?.response?.data?.message || 'Gagal memindahkan tahap.';
            } finally {
                this.tahapVersi.menyimpan = false;
            }
        },
        labelDepartemen,
        labelJenisDokumen,
        labelEntitas,
        nomorDokumen,
        labelVersi,
        /**
         * Warna badge entitas.
         *
         * Warna PT adalah fakta merek yang ditentukan di luar sistem, jadi ia
         * DISIMPAN di master. Bila belum diisi, jatuh ke temaAksen() yang
         * menghash kodenya — konsisten, sekadar bukan warna resminya.
         */
        warnaEntitas(warna, kode) {
            const c = warna || temaAksen(kode || '').color;

            return { '--ec': c };
        },
        /* Sumbernya MASTER KODE DEPARTEMEN, bukan struktur HRIS: kodenyalah
           yang tercetak sebagai segmen nomor dokumen, dan 35 dari 52 kode tak
           punya baris sendiri di struktur yang tersimpan. */
        async muatUnitOpsi() {
            try {
                this.departemenOpsi = await sopMasterDataApi.departemen();
            } catch (e) {
                this.departemenOpsi = [];
                hcisMessage.error(e?.message || 'Gagal memuat daftar departemen.');
            }
        },
        async muatEntitasOpsi() {
            try {
                this.entitasOpsi = await sopMasterDataApi.entitas();
            } catch (e) {
                this.entitasOpsi = [];
                hcisMessage.error(e?.message || 'Gagal memuat daftar entitas.');
            }
        },
        /**
         * "Berlaku di" mengikuti entitas pemilik SELAMA admin belum menyentuhnya.
         *
         * Mayoritas dokumen berlaku di tempat ia dimiliki, jadi field ketiga
         * lebih baik berupa konfirmasi daripada keputusan. Begitu admin
         * mengubahnya sendiri, pilihannya tidak ditimpa lagi.
         */
        ikutkanLingkup() {
            if (!this.lingkupDisentuh) {
                this.form.Entitas_Lingkup_Id = this.form.Entitas_Pemilik_Id;
            }
        },
        salinNomorDokumen(nomor) {
            if (!nomor) return;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(nomor);
                } else {
                    const el = document.createElement('textarea');
                    el.value = nomor;
                    el.style.position = 'fixed';
                    el.style.left = '-9999px';
                    document.body.appendChild(el);
                    el.focus();
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                }
                this.nomorTersalin = true;
                clearTimeout(this.timerSalin);
                this.timerSalin = setTimeout(() => {
                    this.nomorTersalin = false;
                }, 1800);
                hcisMessage.success(`Nomor Dokumen berhasil disalin: ${nomor}`);
            } catch (e) {
                hcisMessage.info(nomor);
            }
        },
        getTipeMediaIcon(tipe) {
            const map = {
                DOKUMEN: 'file-earmark-pdf',
                FORM: 'file-earmark-spreadsheet',
                VIDEO: 'play-circle',
                KUIS: 'patch-question',
                TAUTAN: 'link-45deg',
                PARAGRAF: 'text-paragraph',
                ABSENSI: 'calendar-check',
            };
            return map[tipe] || 'file-earmark-text';
        },
        clearSingleFilter(key) {
            if (key === 'jenis') this.jenis = '';
            if (key === 'departemen') this.departemen = '';
            if (key === 'apqc') this.apqc = '';
            if (key === 'entitas') this.entitas = '';
            if (key === 'lingkup') this.lingkup = '';
            if (key === 'tipe') this.tipe = '';
            this.muat(1);
        },
        clearAllFacetedFilters() {
            this.jenis = '';
            this.departemen = '';
            this.apqc = '';
            this.entitas = '';
            this.lingkup = '';
            this.tipe = '';
            this.muat(1);
        },
        resetAllFilters() {
            this.q = '';
            this.tapis = null;
            this.jenis = '';
            this.departemen = '';
            this.apqc = '';
            this.entitas = '';
            this.lingkup = '';
            this.tipe = '';
            this.urut = 'terbaru';
            this.muat(1);
        },
        onFormTipeChange(tipe) {
            this.form.Tipe = tipe;
            this.muatJenisOpsi(tipe);
            this.muatApqcOpsi(tipe);
        },
        onFormSegmentChange(state) {
            Object.assign(this.form, state);
            this.mintaSaranNomor();
        },
        onFormNoDokumenInput(val) {
            /* Cukup menyetel nilainya. Pengamat 'form.No_Dokumen' sudah meminta
               sarannya ulang dengan jeda 350 ms; memanggilnya lagi di sini
               menembakkan satu permintaan tanpa jeda pada SETIAP ketukan
               tombol, tepat yang hendak dihindari jeda itu. */
            this.form.No_Dokumen = val;
        },
        onFormApplySaran(saran) {
            this.form.No_Dokumen = saran;
            this.mintaSaranNomor();
        },
        async handleFormSubmit(payload) {
            this.form.saving = true;
            try {
                const body = {
                    Judul: payload.Judul.trim(),
                    Tipe: payload.Tipe,
                    Deskripsi: payload.Deskripsi ? payload.Deskripsi.trim() : null,
                    Jenis_Dokumen_Id: payload.Jenis_Dokumen_Id || null,
                    APQC_Id: payload.APQC_Id || null,
                    Departemen_Id: payload.Departemen_Id || null,
                    Entitas_Pemilik_Id: payload.Entitas_Pemilik_Id || null,
                    Entitas_Lingkup_Id: payload.Entitas_Lingkup_Id || null,
                    No_Dokumen: payload.No_Dokumen === '' || payload.No_Dokumen === null ? null : Number(payload.No_Dokumen),
                };

                let res;
                if (payload.id) {
                    res = await axios.put(`${API}/update/${payload.id}`, body);
                } else {
                    res = await axios.post(`${API}/store`, body);
                }

                const targetId = payload.id || res.data?.result?.Id_SOP_Dokumen;
                hcisMessage.success(payload.id ? 'Identitas dokumen diperbarui.' : 'dokumen baru berhasil dibuat.');
                this.form.open = false;
                await this.segarkan(targetId);
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal menyimpan data.');
            } finally {
                this.form.saving = false;
            }
        },
        bukaBuat() {
            this.form = {
                open: true,
                rawItem: null,
                id: null,
                Judul: '',
                Nomor_SOP_Resmi: '',
                nomorResmiLama: '',
                Tipe: this.tipeOpsiBaru[0] || 'DOKUMEN',
                Deskripsi: '',
                Jenis_Dokumen_Id: '',
                APQC_Id: '',
                Departemen_Id: '',
                Entitas_Pemilik_Id: '',
                Entitas_Lingkup_Id: '',
                No_Dokumen: '',
                saving: false,
                dipakai: false,
            };
            this.lingkupDisentuh = false;
            this.nomorDisentuh = false;
            this.saranNoDokumen = null;
            this.pratinjauNomorLengkap = '';
            this.segmenNomorKosong = [];
            this.mintaSaranNomor();
            this.muatJenisOpsi(this.form.Tipe);
            this.muatApqcOpsi(this.form.Tipe);
        },
        bukaUbah(d) {
            if (!d) return;
            const doc = (this.detail?.dokumen && (this.detail.dokumen.Id_SOP_Dokumen === d.Id_SOP_Dokumen || String(this.detail.dokumen.Id_SOP_Dokumen) === String(d.Id_SOP_Dokumen)))
                ? { ...d, ...this.detail.dokumen }
                : d;
            this.form = {
                open: true,
                rawItem: doc,
                id: doc.Id_SOP_Dokumen,
                Judul: doc.Judul || '',
                Nomor_SOP_Resmi: doc.Nomor_SOP_Resmi || '',
                nomorResmiLama: doc.Nomor_SOP_Resmi || '',
                Tipe: doc.Tipe,
                Deskripsi: doc.Deskripsi || '',
                /* Dokumen lama datang tanpa nilai. Sengaja dibiarkan kosong dan
                   wajib diisi di sini — inilah jalur backfill terpandu bagi 41
                   dokumen yang lahir sebelum kolomnya ada. */
                Jenis_Dokumen_Id: doc.Jenis_Dokumen_Id || '',
                APQC_Id: doc.APQC_Id || '',
                Departemen_Id: doc.Departemen_Id || '',
                Entitas_Pemilik_Id: doc.Entitas_Pemilik_Id || '',
                Entitas_Lingkup_Id: doc.Entitas_Lingkup_Id || '',
                No_Dokumen: doc.No_Dokumen ?? '',
                saving: false,
                dipakai: (doc.Jumlah_Paket_Pemakai || 0) > 0,
            };
            /* Dokumen yang sudah punya nomor dibuka apa adanya; sarannya tidak
               boleh menimpanya. */
            this.nomorDisentuh = doc.No_Dokumen !== null && doc.No_Dokumen !== undefined && doc.No_Dokumen !== '';
            this.saranNoDokumen = null;
            this.pratinjauNomorLengkap = '';
            this.segmenNomorKosong = [];
            this.mintaSaranNomor();
            /* Dokumen lama dibuka dengan lingkup yang sudah tersimpan; jangan
               ditimpa oleh pemilik. */
            this.lingkupDisentuh = Boolean(doc.Entitas_Lingkup_Id);
            this.muatJenisOpsi(this.form.Tipe);
            this.muatApqcOpsi(this.form.Tipe);
        },
        async simpan() {
            const f = this.form;
            if (!this.bisaSimpan) return;

            f.saving = true;
            try {
                const payload = {
                    Judul: f.Judul.trim(),
                    /* TIDAK dikirim: server yang merakitnya dari identitas.
                       Mengirimnya dari sini membuka penulis kedua untuk satu
                       nilai, dan dua penulis pasti menyimpang. */
                    Tipe: f.Tipe,
                    Deskripsi: f.Deskripsi ? f.Deskripsi.trim() : null,
                    Jenis_Dokumen_Id: f.Jenis_Dokumen_Id || null,
                    APQC_Id: f.APQC_Id || null,
                    Departemen_Id: f.Departemen_Id || null,
                    Entitas_Pemilik_Id: f.Entitas_Pemilik_Id || null,
                    Entitas_Lingkup_Id: f.Entitas_Lingkup_Id || null,
                    No_Dokumen: f.No_Dokumen === '' || f.No_Dokumen === null ? null : Number(f.No_Dokumen),
                };

                let res;
                if (f.id) {
                    res = await axios.put(`${API}/update/${f.id}`, payload);
                } else {
                    res = await axios.post(`${API}/store`, payload);
                }

                const targetId = f.id || res.data?.result?.Id_SOP_Dokumen;

                hcisMessage.success(f.id ? 'Identitas dokumen diperbarui.' : 'dokumen baru berhasil dibuat.');
                f.open = false;
                await this.segarkan(targetId);
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal menyimpan data.');
            } finally {
                f.saving = false;
            }
        },
    },
};
</script>

<style scoped>
.sop-master-dokumen {
    width: 100%;
}

/* Master-Detail 2-Column Grid */
.mdx-workspace-grid {
    display: grid;
    grid-template-columns: minmax(380px, 420px) minmax(0, 1fr);
    gap: 1.35rem;
    align-items: start;
}

@media (max-width: 991.98px) {
    .mdx-workspace-grid {
        grid-template-columns: 1fr;
    }
}

/* Sidebar Shell */
.mdx-sidebar-wrap {
    position: sticky;
    top: 76px;
    width: 100%;
    z-index: 5;
}

.mdx-header-counter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 18px;
    padding: 0 6px;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 750;
    color: var(--hcis-brand-700, #1d4ed8);
    background: var(--hcis-brand-50, #eff6ff);
    border: 1px solid var(--hcis-brand-200, #bfdbfe);
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin-active {
    animation: spin 0.8s linear infinite;
    display: inline-block;
}

.mh-alarm-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.25;
    white-space: nowrap;
    max-width: 175px;
    min-width: 0;
    overflow: hidden;
    flex-shrink: 0;
    color: var(--hcis-warning-800, #92400e);
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.22rem 0.65rem;
    cursor: pointer;
    user-select: none;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(245, 158, 11, 0.06);
}

.mh-alarm-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mh-alarm-pill:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.5);
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(245, 158, 11, 0.12);
}

.mh-alarm-pill:active {
    transform: translateY(0);
}

.mh-alarm-pill.on {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 2px 6px -1px rgba(217, 119, 6, 0.35);
}

/* PIL SUNYI untuk "Belum Lengkap". Warnanya netral dengan sengaja: pekerjaan
   yang menggantung tanpa tenggat tak boleh berteriak sekeras tanggal yang
   sudah lewat. Dua peringatan berwarna sama membuat keduanya berhenti dibaca. */
.mh-alarm-pill.is-quiet {
    color: var(--hcis-slate-700, #334155);
    background: var(--hcis-slate-100, #f1f5f9);
    border-color: var(--hcis-slate-300, #cbd5e1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.mh-alarm-pill.is-quiet:hover {
    background: var(--hcis-slate-200, #e2e8f0);
    border-color: var(--hcis-slate-400, #94a3b8);
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
}

.mh-alarm-pill.is-quiet:active {
    transform: translateY(0);
}

.mh-alarm-pill.is-quiet.on {
    background: linear-gradient(135deg, #475569 0%, #334155 100%);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 2px 6px -1px rgba(71, 85, 105, 0.35);
}

.mdx-tapis-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding-bottom: 2px;
}

.mdx-tapis-chips::-webkit-scrollbar {
    display: none;
}

/* Catatan penonaktifan ditampilkan sebagai KUTIPAN: kalimatnya ditulis seorang
   admin, bukan dihasilkan sistem, dan pembacanya perlu tahu bedanya. */
.mdx-nonaktif-catatan {
    padding-left: 0.6rem;
    border-left: 2px solid var(--hcis-slate-300, var(--hcis-slate-300));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-style: italic;
}

.mdx-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-600, #475569);
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.22rem 0.65rem;
    cursor: pointer;
    white-space: nowrap;
    max-width: 160px;
    transition: all 0.15s ease;
    user-select: none;
    flex-shrink: 0;
}

.mdx-chip-btn span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mdx-chip-btn:hover {
    background: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-800, #1e293b);
}

.mdx-chip-btn.on {
    background: var(--hcis-brand-600, #2563eb);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 2px 6px -1px rgba(37, 99, 235, 0.35);
}

.mdx-select {
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.775rem;
    padding: 0.35rem 0.65rem;
}

/* Faceted Filter Toggle Button */
.mdx-filter-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    height: 32px;
    font-size: 0.775rem;
    font-weight: 650;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-300, #cbd5e1);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0 0.68rem;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.mdx-filter-toggle-btn:hover {
    background: var(--hcis-slate-100, #f1f5f9);
    border-color: var(--hcis-slate-400, #94a3b8);
    color: var(--hcis-slate-800, #1e293b);
}

.mdx-filter-toggle-btn.is-active {
    background: #eff6ff;
    border-color: var(--hcis-blue-500, #3b82f6);
    color: #1d4ed8;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.1);
}

/* Faceted Multi-Filter Drawer */
.mdx-faceted-box {
    padding: 0.75rem 0.85rem;
    background: #ffffff;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.05);
}

.mdx-filter-slide-enter-active,
.mdx-filter-slide-leave-active {
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.mdx-filter-slide-enter-from,
.mdx-filter-slide-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(-6px);
}

.mdx-filter-slide-enter-to,
.mdx-filter-slide-leave-from {
    opacity: 1;
    max-height: 520px;
    transform: translateY(0);
}

.mdx-facet-lbl {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500, #64748b);
    margin-bottom: 0.15rem;
    display: block;
}

/* Active Filter Tags Strip */
.mdx-active-tags-strip {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.28rem;
    padding: 0.25rem 0;
}

.mdx-active-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 650;
    color: #1d4ed8;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
    padding: 0.1rem 0.45rem;
    cursor: pointer;
    max-width: 175px;
    overflow: hidden;
    white-space: nowrap;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.mdx-active-tag .tag-lbl {
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mdx-active-tag:hover {
    background: #dbeafe;
    border-color: #93c5fd;
}

.tag-x {
    font-size: 0.65rem;
    color: #60a5fa;
}

.mdx-active-tag:hover .tag-x {
    color: var(--hcis-red-500, #ef4444);
}

.btn-reset-all {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-red-600, #dc2626);
    background: transparent;
    border: none;
    padding: 0.1rem 0.35rem;
    cursor: pointer;
    text-decoration: underline;
}

.btn-reset-all:hover {
    color: var(--hcis-red-800, #991b1b);
}

/* ── Master Sidebar Card Structure (Reliable Clean Shell) ── */
.mdx-sidebar-card {
    display: flex;
    flex-direction: column;
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.mdx-sidebar__header {
    flex-shrink: 0;
    padding: 0.85rem 0.95rem;
    background: var(--hcis-slate-50, #f8fafc);
    border-bottom: 1px solid #edf2f7;
}

.mdx-sidebar__body {
    max-height: calc(100vh - 280px);
    min-height: 520px;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0.55rem 0.65rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.45) transparent;
}

.mdx-sidebar__body::-webkit-scrollbar {
    width: 5px;
}

.mdx-sidebar__body::-webkit-scrollbar-track {
    background: transparent;
}

.mdx-sidebar__body::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 999px;
}

.mdx-sidebar__body::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.7);
}

.mdx-sidebar__footer {
    flex-shrink: 0;
    padding: 0.45rem 0.6rem;
    background: var(--hcis-slate-50);
    border-top: 1px solid #edf2f7;
    display: flex;
    justify-content: center;
    align-items: center;
}

.mdx-sidebar__footer :deep(.hcis-pagination) {
    justify-content: center;
    width: 100%;
}

.mdx-sidebar__footer :deep(.hcis-pagination__desktop) {
    justify-content: center;
    gap: 4px;
    width: 100%;
}

.sop-doc-title {
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.25;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    transition: color 0.15s ease;
}

.sop-doc-card.is-selected .sop-doc-title {
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.sop-doc-token {
    font-size: 0.7rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: var(--hcis-slate-100);
    padding: 0.08rem 0.4rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    max-width: 140px;
}

.sop-doc-tag {
    font-size: 0.65rem;
    font-weight: 650;
    padding: 0.08rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    white-space: nowrap;
}

.tag-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.tag-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.sop-doc-count {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.12rem 0.45rem;
}

.sop-doc-count.is-zero {
    opacity: 0.45;
}

/* Detail Panel Head */
/* ── Detail Panel Hero Identity Header ── */
.mdx-detail-hero {
    position: relative;
    padding-bottom: 1.15rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
}

.mdx-hero-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.18rem 0.6rem;
    line-height: 1.3;
    max-width: 260px;
    white-space: nowrap;
    overflow: hidden;
}

.jenis-tag {
    color: var(--hcis-indigo-700, #4338ca);
    background: var(--hcis-indigo-50, #eef2ff);
    border: 1px solid var(--hcis-indigo-200, #c7d2fe);
}

.active-tag {
    color: var(--hcis-emerald-700, #047857);
    background: var(--hcis-emerald-50, #ecfdf5);
    border: 1px solid var(--hcis-emerald-200, #a7f3d0);
}

.warning-tag {
    color: var(--hcis-amber-800, #92400e);
    background: var(--hcis-amber-50, #fffbeb);
    border: 1px solid var(--hcis-amber-200, #fde68a);
}

.neutral-tag {
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.mdx-hero-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.35;
    letter-spacing: -0.02em;
    word-break: break-word;
    overflow-wrap: anywhere;
}

/* Hero Monospace Number Strip */
.mdx-hero-number-strip {
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.mdx-official-number {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.75rem;
    border-radius: 6px;
    font-size: 0.825rem;
    font-weight: 800;
    color: var(--hcis-white, #ffffff);
    background: var(--hcis-slate-900, #0f172a);
    letter-spacing: 0.03em;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
    max-width: 100%;
}

.mdx-official-number.is-incomplete {
    color: #92400e;
    background: #fef3c7;
    border: 1px dashed #d97706;
    box-shadow: none;
}

.mdx-copy-btn {
    display: inline-grid;
    place-items: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border-radius: 6px;
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-300, #cbd5e1);
    color: var(--hcis-slate-600, #475569);
    transition: all 0.15s ease;
}

.mdx-copy-btn:hover {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
}

.mdx-copy-btn.is-copied {
    background: #f0fdf4;
    border-color: #86efac;
    color: #16a34a;
}

.mdx-seg-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
    background: var(--hcis-white, #ffffff);
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: 6px;
    padding: 0.2rem 0.55rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
    transition: border-color 0.15s ease, background-color 0.15s ease;
    max-width: 220px;
}

.mdx-seg-pill:hover {
    border-color: var(--hcis-slate-400, #94a3b8);
    background: #f8fafc;
}

/* Stat Cards refinements in Detail Panel */
.mdx-detail-wrap :deep(.hcis-stat) {
    height: 100%;
    min-height: 94px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-radius: var(--hcis-radius-lg, 10px);
    transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease, border-color 0.18s ease;
}

.mdx-detail-wrap :deep(.hcis-stat:hover) {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px -4px rgba(15, 23, 42, 0.07);
}

.mdx-detail-wrap :deep(.hcis-stat__value--compact) {
    font-size: 1.18rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    line-height: 1.25;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}

/* ── Governance Spec Grid Container & Tiles ── */
.mdx-spec-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.15rem;
    margin-top: 0.25rem;
}

@media (max-width: 767.98px) {
    .mdx-spec-grid {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
}

.mdx-spec-tile {
    display: flex;
    align-items: flex-start;
    gap: 0.95rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.03);
    height: 100%;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.mdx-spec-tile:hover {
    border-color: rgba(148, 163, 184, 0.8);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}

.spec-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.bg-blue-subtle { background: #eff6ff; border: 1px solid #dbeafe; }
.bg-purple-subtle { background: #faf5ff; border: 1px solid #ede9fe; }
.bg-teal-subtle { background: #f0fdfa; border: 1px solid #ccfbf1; }
.bg-amber-subtle { background: #fffbeb; border: 1px solid #fef3c7; }

.text-purple { color: var(--hcis-violet-600); }
.text-teal { color: #0d9488; }
.text-amber { color: var(--hcis-amber-600); }

.spec-label {
    font-size: 0.6875rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    display: block;
    line-height: 1.15;
    margin-bottom: 0.25rem;
}

.spec-value {
    font-size: 0.875rem;
    font-weight: 750;
    color: #0f172a;
    line-height: 1.35;
}

.spec-sub {
    font-size: 0.72rem;
    color: #94a3b8;
    display: block;
    margin-top: 0.25rem;
}

/* Stylized Quote Description Box */
.mdx-desc-content {
    position: relative;
    padding: 0.95rem 1.15rem 0.95rem 2.25rem;
    background: #f8fafc;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-left: 3.5px solid var(--hcis-brand-500, #3b82f6);
    border-radius: 8px;
    color: #1e293b;
    font-size: 0.835rem;
    line-height: 1.65;
    white-space: pre-wrap;
}

.desc-quote-icon {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    font-size: 1.1rem;
    color: #93c5fd;
}

.mdx-nomor-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
}

/* Pil tata kelola. Sengaja dibedakan warnanya dari pil nomor: nomor adalah
   identitas dokumen, sedangkan dua ini adalah keputusan tata kelola yang bisa
   berubah tanpa mengubah dokumennya. */
.mdx-jenis-pill,
.mdx-pemilik-pill,
.mdx-belum-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 650;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
}

.mdx-nomorlengkap-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border-radius: 0.5rem;
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    background: var(--hcis-slate-900);
}

.mdx-nomorlengkap-pill.is-kurang {
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border: 1px dashed var(--hcis-amber-600);
}

.mdx-tahap-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    background: var(--tc);
}

.mdx-tahap-opsi {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    width: 100%;
    padding: 0.6rem 0.8rem;
    text-align: left;
    border-radius: 0.6rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    border-left: 4px solid var(--tc);
    background: #fff;
    transition: background 0.15s ease;
}

.mdx-tahap-opsi:hover:not(:disabled) {
    background: var(--hcis-surface-subtle, var(--hcis-slate-50));
}

.mdx-tahap-opsi:disabled {
    opacity: 0.6;
}

.mdx-tahap-opsi__nama {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--hcis-text, var(--hcis-slate-900));
}

.mdx-tahap-opsi__ket {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.mdx-tahap-opsi__syarat {
    font-size: 0.7rem;
    color: var(--hcis-amber-700);
}

.mdx-nomorresmi-kotak {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.6rem 0.8rem;
    border-radius: 0.5rem;
    background: var(--hcis-surface-subtle, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border, #e5e7eb);
}

.mdx-nomorresmi-kotak__value {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: var(--hcis-text, var(--hcis-slate-900));
}

.mdx-nomorresmi-kotak__kurang {
    font-size: 0.75rem;
    color: var(--hcis-amber-700);
}

.mdx-nomorresmi-kotak__siap {
    font-size: 0.75rem;
    color: var(--hcis-emerald-700);
}

.mdx-nomorlengkap-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.5rem 0.7rem;
    border-radius: 0.5rem;
    background: var(--hcis-surface-subtle, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border, #e5e7eb);
}

.mdx-nomorlengkap-preview__label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.mdx-nomorlengkap-preview__value {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--hcis-text, var(--hcis-slate-900));
}

.mdx-nomorlengkap-preview__kurang {
    font-size: 0.72rem;
    color: var(--hcis-amber-700);
}

.mdx-nodok-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-300);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
}

.mdx-nodok-pratinjau {
    display: inline-grid;
    place-items: center;
    min-width: 2.75rem;
    padding: 0.3rem 0.5rem;
    border-radius: 0.5rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
}

.mdx-apqc-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 650;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
    color: #9d174d;
    background: #fdf2f8;
    border: 1px solid #fbcfe8;
}

.mdx-jenis-pill {
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
}

.mdx-pemilik-pill {
    color: #0f766e;
    background: var(--hcis-emerald-50);
    border: 1px solid #99f6e4;
}

.mdx-belum-pill {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-50);
    border: 1px dashed var(--hcis-amber-300);
}

.mdx-entitas-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
    color: #fff;
    background: var(--ec, var(--hcis-indigo-700));
    border: 1px solid var(--ec, var(--hcis-indigo-700));
}

.mdx-lingkup-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 650;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
    color: var(--hcis-indigo-800);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
}

.mdx-detail-title {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.3;
    letter-spacing: -0.02em;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
}

.mdx-detail-desc {
    font-size: 0.85rem;
    line-height: 1.55;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

/* ── Facts Metrics Grid ── */
.mdx-facts-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
}

@media (max-width: 1200px) {
    .mdx-facts-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.fact-box {
    padding: 0.85rem 1rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 10px);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02);
    transition: all 0.15s ease;
}

.fact-box:hover {
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
}

.fact-box--wide {
    grid-column: span 1;
}

.fact-label {
    font-size: 0.685rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    margin: 0;
}

.fact-icon-badge {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: grid;
    place-items: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.fact-icon-emerald {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    border: 1px solid var(--hcis-green-200);
}

.fact-icon-indigo {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    border: 1px solid var(--hcis-indigo-200);
}

.fact-icon-purple {
    background: #ede9fe;
    color: var(--hcis-violet-700);
    border: 1px solid #ddd6fe;
}

.fact-icon-teal {
    background: #ccfbf1;
    color: #0f766e;
    border: 1px solid #99f6e4;
}

.fact-value {
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.fact-approver-item {
    padding: 0.2rem 0;
    border-bottom: 1px dashed var(--hcis-slate-200);
}

.fact-approver-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.fa-name {
    font-size: 0.8rem;
    font-weight: 650;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    display: block;
}

.fa-role {
    font-size: 0.7rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    display: block;
}


/* ── Content Sections & Media Cards ── */
.mdx-section-card {
    padding: 1.15rem 1.25rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02);
}

.mdx-section-danger {
    background: rgba(254, 242, 242, 0.4);
    border-color: rgba(239, 68, 68, 0.25);
}

.mdx-hampa-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 480px;
    padding: 3.5rem 1.5rem;
}

.mdx-media-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 10px);
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.03);
    transition: all 0.15s ease;
}

.mdx-media-card:hover {
    border-color: rgba(148, 163, 184, 0.8);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.06);
}

.media-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.media-icon.doc { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
.media-icon.quiz { background: #faf5ff; color: #7c3aed; border: 1px solid #ede9fe; }

/* Paket list items */
.mdx-paket-item {
    padding: 0.85rem 1.15rem;
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 10px);
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.02);
}

.mdx-paket-item:hover {
    background: #ffffff;
    border-color: var(--hcis-brand-400, #60a5fa);
    box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.08);
    transform: translateY(-1px);
}

.mdx-paket-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: #eff6ff;
    color: var(--hcis-brand-600, #2563eb);
    border: 1px solid #dbeafe;
    display: grid;
    place-items: center;
    font-size: 1.1rem;
}

.mdx-paket-link {
    font-size: 0.875rem;
    line-height: 1.35;
    transition: color 0.15s ease;
}

.mdx-paket-link:hover {
    color: var(--hcis-brand-600, #2563eb) !important;
}

/* Ratio bar */
.ratio-bar-track {
    width: 120px;
    height: 5px;
    background: var(--hcis-slate-200, #e2e8f0);
    border-radius: 999px;
    overflow: hidden;
}

@media (max-width: 575.98px) {
    .ratio-bar-track {
        width: 70px;
    }
}

.ratio-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 999px;
}

.mdx-flow-container {
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    background: #ffffff;
}

/* Version cards */
.mdx-versi-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1.15rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 10px);
    margin-bottom: 0.65rem;
    background: var(--hcis-white);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
    transition: all 0.15s ease;
}

.mdx-versi-card:hover {
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.04);
}

.mdx-versi-card.is-published {
    border-left: 3.5px solid var(--hcis-success-500, var(--hcis-emerald-500));
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.04) 0%, var(--hcis-white) 100%);
}

.mdx-versi-card.is-draft {
    border-left: 3.5px solid var(--hcis-warning-500, var(--hcis-amber-500));
    background: linear-gradient(90deg, rgba(245, 158, 11, 0.04) 0%, var(--hcis-white) 100%);
}

.v-num-badge {
    font-size: 0.825rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

/* Timeline */
.rw-item {
    position: relative;
    padding-left: 1.5rem;
    padding-bottom: 1.25rem;
    border-left: 2px solid var(--hcis-slate-200);
}

.rw-item:last-child {
    border-left-color: transparent;
    padding-bottom: 0;
}

.rw-titik {
    position: absolute;
    left: -5px;
    top: 4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-slate-300);
}

.rw-titik.ok { background: var(--hcis-success-500, var(--hcis-emerald-500)); }
.rw-titik.bahaya { background: var(--hcis-danger-500, var(--hcis-red-500)); }
.rw-titik.balik { background: var(--hcis-warning-500, var(--hcis-amber-500)); }

/* Sop Versi Card Styling */
.sop-versi-lampiran-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    font-size: 0.6875rem;
    font-weight: 600;
    border-radius: 4px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
}

.sop-versi-summary-box {
    padding: 0.6rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-left: 3.5px solid var(--hcis-brand-500, #3b82f6);
    font-size: 0.8125rem;
    color: #334155;
    line-height: 1.55;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.sop-versi-meta-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem 1.25rem;
    padding-top: 0.25rem;
    font-size: 0.78125rem;
    color: var(--hcis-slate-500);
}

.sop-versi-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}
</style>
