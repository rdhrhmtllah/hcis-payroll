<template>
    <main class="pa-gk-page">
        <!-- Header -->
        <HcisHeader
            title="Grup Level Kurva"
            kicker="MASTER DATA · KURVA GRADE"
            description="Level dalam satu grup dibandingkan dalam satu kurva normal (disarankan ≥ 30 peserta per grup)."
            icon="diagram-2-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="mb-3"
        >
            <template #badge>
                <HcisBadge
                    v-if="adaPerubahan"
                    label="Ada perubahan belum disimpan"
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
                <HcisBadge
                    v-else-if="levelTanpaGrup.length"
                    :label="`${levelTanpaGrup.length} level belum masuk grup`"
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    dot
                />
                <HcisBadge
                    v-else
                    :label="`${grup.length} Grup Terkonfigurasi`"
                    variant="success"
                    appearance="glass"
                    size="sm"
                    dot
                />
            </template>

            <template #actions>
                <div class="pa-gk-header-actions">
                    <HcisButton
                        href="/performance-appraisal"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        :icon-only="isMobile"
                        title="Kembali ke Assessment Cycle"
                    >
                        <span class="d-none d-sm-inline">Assessment Cycle</span>
                    </HcisButton>
                    <HcisButton
                        label="Segarkan"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :icon-only="isMobile"
                        :loading="memuat"
                        title="Muat ulang data"
                        @click="muat"
                    />
                    <HcisButton
                        :label="isMobile ? 'Simpan' : 'Simpan konfigurasi'"
                        variant="primary"
                        size="sm"
                        leading-icon="check2-circle"
                        data-uji="simpan"
                        :loading="menyimpan || memuat"
                        title="Simpan konfigurasi grup kurva"
                        @click="simpan"
                    />
                </div>
            </template>

            <template #console>
                <nav class="pa-admin-switcher" aria-label="Navigasi Master Data Performance Appraisal">
                    <a
                        v-for="item in masterNavList"
                        :key="item.slug"
                        :href="item.href"
                        class="pa-admin-switcher__item"
                        :class="{ 'pa-admin-switcher__item--active': item.slug === 'grup-kurva' }"
                    >
                        <HcisIcon :name="item.icon" size="2xs" />
                        <span>{{ item.label }}</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <!-- Loading Skeleton -->
        <HcisSkeleton v-if="memuat && !dimuat" :lines="6" />

        <!-- Error State -->
        <HcisResultState
            v-else-if="galat"
            status="error"
            title="Grup level kurva tidak dapat dimuat"
            :description="galat"
            show-retry
            @retry="muat"
        />

        <!-- Main 2-Column Master-Detail Layout -->
        <div v-else class="pa-gk-container">
            <!-- Peringatan Level Belum Masuk Grup -->
            <HcisAlert
                v-if="levelTanpaGrup.length"
                variant="warning"
                appearance="glass"
                class="mb-3"
                icon="exclamation-triangle-fill"
                :title="`${levelTanpaGrup.length} level belum masuk grup`"
                description="Di siklus baru, level tanpa grup menjadi grupnya sendiri — kurvanya bisa terlalu kecil."
            />

            <!-- Peringatan Galat Simpan -->
            <HcisAlert v-if="galatSimpan" variant="danger" :title="galatSimpan" class="mb-3" />

            <!-- Mobile Dual-Pane Segmented Tabs via HcisTabs (<= 768px) -->
            <HcisTabs
                v-if="isMobile"
                v-model="mobileActiveTab"
                :tabs="mobileTabs"
                variant="segmented"
                block
                size="sm"
                class="mb-3 d-md-none"
            />

            <div class="pa-gk-layout">
                <!-- KOLOM KIRI: DAFTAR GRUP & TAMBAH GRUP -->
                <aside class="pa-gk-sidebar" :class="{ 'd-none d-md-block': mobileActiveTab !== 'grup' }">
                    <HcisCard variant="default" size="sm" class="pa-gk-sidebar-card">
                        <template #header>
                            <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisIcon name="diagram-2" size="sm" class="text-slate-600" />
                                    <h3 class="pa-gk-panel-title">Grup Kurva</h3>
                                </div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <HcisBadge
                                        :label="`${grup.length} Grup`"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                    <HcisButton
                                        v-if="isMobile"
                                        :label="bukaFormTambah ? 'Tutup Form' : '+ Buat Grup'"
                                        :variant="bukaFormTambah ? 'ghost' : 'secondary'"
                                        size="xs"
                                        @click="bukaFormTambah = !bukaFormTambah"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- Form Tambah Grup Cepat (Desktop always visible, mobile toggleable) -->
                        <Transition name="pa-gk-slide">
                            <div v-if="!isMobile || bukaFormTambah" class="pa-gk-tambah-box mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <HcisIcon name="folder-plus" size="xs" class="text-slate-600" />
                                        <span class="fw-bold text-slate-800" style="font-size: 0.8125rem;">Tambah Grup Baru</span>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-12 col-sm-4">
                                        <HcisField
                                            id="pa-gk-kode"
                                            v-model="baru.kode"
                                            label="Kode grup"
                                            placeholder="mis. STAF"
                                            size="sm"
                                            @keydown.enter="tambahGrup"
                                        />
                                    </div>
                                    <div class="col-12 col-sm-8">
                                        <HcisField
                                            id="pa-gk-nama"
                                            v-model="baru.nama"
                                            label="Nama grup"
                                            placeholder="mis. Staff & Senior Staff"
                                            size="sm"
                                            @keydown.enter="tambahGrup"
                                        />
                                    </div>
                                </div>

                                <HcisButton
                                    label="Simpan & Buat Grup"
                                    variant="primary"
                                    size="sm"
                                    leading-icon="plus-lg"
                                    data-uji="tambah-grup"
                                    class="w-100"
                                    @click="tambahGrup"
                                />
                            </div>
                        </Transition>

                        <!-- Pencarian Cepat Grup (Jika grup lebih dari 3) -->
                        <div v-if="grup.length > 3" class="pa-gk-search-grup mb-2.5">
                            <HcisField
                                v-model="cariGrup"
                                placeholder="Cari nama atau kode grup..."
                                size="sm"
                            />
                        </div>

                        <!-- Daftar Kartu Grup -->
                        <div class="pa-gk-grup-list">
                            <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                                <span class="text-uppercase fw-bold text-slate-500" style="font-size: 0.6875rem; letter-spacing: 0.04em;">
                                    Daftar Grup Kurva
                                </span>
                                <span class="text-slate-400" style="font-size: 0.6875rem;">
                                    {{ grupTersaring.length }} terdaftar
                                </span>
                            </div>

                            <HcisCard
                                v-for="g in grupTersaring"
                                :key="g.kode"
                                variant="glass"
                                size="xs"
                                interactive
                                :sentiment="filterGrupAktif === g.kode ? 'brand' : 'neutral'"
                                class="pa-gk-grup-card mb-2.5"
                                :class="{ 'is-selected': filterGrupAktif === g.kode }"
                                :data-uji="`grup-${g.kode}`"
                                @click="pilihGrupCard(g.kode)"
                            >
                                <template #header>
                                    <div class="pa-gk-grup-card__header-row">
                                        <div class="pa-gk-grup-card__title-box">
                                            <span class="pa-gk-code-pill font-monospace" :class="{ 'is-active': filterGrupAktif === g.kode }">{{ g.kode }}</span>
                                            <h4 class="pa-gk-grup-card__nama">{{ g.nama }}</h4>
                                        </div>
                                        <div class="pa-gk-grup-card__action-buttons" @click.stop>
                                            <HcisButton
                                                variant="ghost"
                                                size="xs"
                                                leading-icon="pencil-square"
                                                title="Ubah nama grup"
                                                aria-label="Ubah nama grup"
                                                @click="bukaEditGrup(g)"
                                            />
                                            <HcisButton
                                                variant="ghost"
                                                size="xs"
                                                leading-icon="trash"
                                                sentiment="danger"
                                                title="Hapus grup"
                                                aria-label="Hapus grup"
                                                @click="hapusGrup(g)"
                                            />
                                        </div>
                                    </div>
                                </template>

                                <div class="pa-gk-card-meta-row my-1.5">
                                    <span class="pa-gk__cacah" :class="{ 'is-kecil': jumlahGrup(g.kode) < 30 }">
                                        <HcisIcon name="people-fill" size="2xs" class="me-1 text-slate-400" />
                                        <strong>{{ jumlahGrup(g.kode) }} peserta</strong> di siklus terakhir
                                    </span>
                                    <HcisBadge
                                        :label="jumlahGrup(g.kode) >= 30 ? '≥ 30 Ideal' : '< 30 Kecil'"
                                        :variant="jumlahGrup(g.kode) >= 30 ? 'success' : 'warning'"
                                        size="xs"
                                        dot
                                    />
                                </div>

                                <!-- Gaussian Progress Bar -->
                                <div class="pa-gk-progress-track mb-2" :title="`${jumlahGrup(g.kode)}/30 peserta menuju kurva normal ideal`">
                                    <div
                                        class="pa-gk-progress-fill"
                                        :class="jumlahGrup(g.kode) >= 30 ? 'is-ideal' : 'is-kecil'"
                                        :style="{ width: `${Math.min(100, Math.round((jumlahGrup(g.kode) / 30) * 100))}%` }"
                                    />
                                </div>

                                <!-- Level Chips with non-overflow wrapping -->
                                <div class="pa-gk-member-chips">
                                    <span
                                        v-for="l in levelDi(g.kode)"
                                        :key="l.id"
                                        class="pa-gk-level-chip"
                                    >
                                        <span class="pa-gk-level-chip__name">{{ l.nama }}</span>
                                        <span class="pa-gk-level-chip__count">· {{ cacah(l.id) }}</span>
                                        <button
                                            type="button"
                                            class="pa-gk-level-chip__remove"
                                            title="Lepas level dari grup ini"
                                            aria-label="Lepas level"
                                            @click.stop="lepasLevel(l.id)"
                                        >
                                            <HcisIcon name="x" size="2xs" />
                                        </button>
                                    </span>
                                    <span v-if="!levelDi(g.kode).length" class="text-muted fst-italic pa-gk__kosong">Belum ada level terhubung</span>
                                </div>

                                <!-- Mobile Navigation Action Pill -->
                                <template v-if="isMobile" #footer>
                                    <div class="pa-gk-card-mobile-action">
                                        <div class="d-flex align-items-center justify-content-between text-brand" style="font-size: 0.75rem; font-weight: 600;">
                                            <span>{{ levelDi(g.kode).length }} Level Jabatan</span>
                                            <span class="d-inline-flex align-items-center gap-1">
                                                Kelola Level <HcisIcon name="arrow-right-short" size="xs" />
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </HcisCard>

                            <div v-if="!grupTersaring.length" class="pa-gk-empty-grup">
                                <span v-if="cariGrup">Tidak ada grup yang cocok dengan "{{ cariGrup }}".</span>
                                <span v-else>Belum ada grup kurva. Buat grup menggunakan form di atas.</span>
                            </div>
                        </div>

                        <!-- Mini Panduan Distribusi Kurva Normal -->
                        <div class="pa-gk-sidebar-guide mt-2 pt-2.5">
                            <div class="d-flex align-items-center gap-1.5 mb-1 text-slate-700">
                                <HcisIcon name="info-circle" size="xs" class="text-slate-500" />
                                <span class="fw-semibold" style="font-size: 0.75rem;">Ketentuan Distribusi Normal</span>
                            </div>
                            <p class="text-slate-500 mb-0" style="font-size: 0.72rem; line-height: 1.45;">
                                Grup idealnya memiliki <strong>&ge; 30 peserta</strong> agar kurva Gaussian valid. Klik salah satu kartu grup untuk memfilter tabel di sebelah kanan.
                            </p>
                        </div>
                    </HcisCard>
                </aside>

                <!-- KOLOM KANAN: TABEL / KARTU PENEMPATAN LEVEL -->
                <section class="pa-gk-main" :class="{ 'd-none d-md-block': mobileActiveTab !== 'penempatan' }">
                    <HcisCard variant="default" class="pa-gk-main-card">
                        <template #header>
                            <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                                <div>
                                    <h3 class="pa-gk__subjudul">Penempatan level</h3>
                                    <p class="pa-gk-panel-desc d-none d-sm-block">
                                        Atur penempatan tiap level ke dalam grup kurva normal yang sesuai.
                                    </p>
                                </div>
                                <!-- Mini summary indicator for mobile/tablet -->
                                <div v-if="level.length > 0" class="pa-gk-quick-stats">
                                    <button
                                        type="button"
                                        class="pa-gk-stat-pill"
                                        :class="{ 'is-active': filterTab === 'semua' && !filterGrupAktif }"
                                        @click="handleChipClick({ value: 'semua' })"
                                    >
                                        Total: <strong>{{ level.length }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        class="pa-gk-stat-pill"
                                        :class="{ 'is-warning': levelTanpaGrup.length > 0, 'is-active': filterTab === 'tanpa-grup' && !filterGrupAktif }"
                                        @click="handleChipClick({ value: 'tanpa-grup' })"
                                    >
                                        Belum grup: <strong>{{ levelTanpaGrup.length }}</strong>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Contextual Active Filter Banner -->
                        <div v-if="filterGrupAktif" class="pa-gk-active-filter-banner mb-3">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <HcisIcon name="funnel-fill" size="xs" class="text-brand flex-shrink-0" />
                                <div class="text-truncate">
                                    <span class="text-slate-500" style="font-size: 0.75rem;">Filter grup:</span>
                                    <strong class="text-slate-800 ms-1" style="font-size: 0.8125rem;">{{ filterGrupAktif }}</strong>
                                    <span v-if="namaGrupAktif" class="text-slate-500 ms-1" style="font-size: 0.75rem;">— {{ namaGrupAktif }}</span>
                                    <span class="badge bg-white text-slate-700 border border-slate-200 ms-1.5" style="font-size: 0.6875rem;">{{ levelTersaring.length }} level</span>
                                </div>
                            </div>
                            <HcisButton
                                label="Lihat Semua"
                                variant="ghost"
                                size="xs"
                                leading-icon="x-circle"
                                @click="filterGrupAktif = ''"
                            />
                        </div>

                        <!-- Standard Design System Filter Bar Component -->
                        <HcisFilterBar
                            :search="cariLevel"
                            search-placeholder="Cari level jabatan atau ID..."
                            :quick-chips="quickChips"
                            :active-chip-value="filterGrupAktif || filterTab"
                            :results-count="levelTersaring.length"
                            variant="flat"
                            size="sm"
                            class="mb-3"
                            @update:search="(val) => cariLevel = val"
                            @chip-click="handleChipClick"
                            @clear-search="cariLevel = ''"
                        />

                        <!-- Dedicated Touch-Optimized Mobile Level Cards (<= 768px) -->
                        <div v-if="isMobile" class="pa-gk-mobile-level-list">
                            <div
                                v-for="row in levelTersaring"
                                :key="`mobile-lvl-${row.id}`"
                                class="pa-gk-mobile-level-card"
                                :class="{ 'is-unassigned': !row.grup }"
                            >
                                <!-- Top Row: Level Name & Status Badge -->
                                <div class="pa-gk-mobile-level-card__header">
                                    <div class="pa-gk-mobile-level-card__title-group">
                                        <span class="pa-gk-mobile-level-card__name">{{ row.nama }}</span>
                                        <span class="pa-gk-mobile-level-card__id">ID #{{ row.id }}</span>
                                    </div>
                                    <div class="pa-gk-mobile-level-card__status">
                                        <HcisBadge
                                            v-if="row.grup && jumlahGrup(row.grup) >= 30"
                                            label="Ideal (≥ 30)"
                                            variant="success"
                                            size="xs"
                                            dot
                                        />
                                        <HcisBadge
                                            v-else-if="row.grup && jumlahGrup(row.grup) < 30"
                                            label="Kecil (< 30)"
                                            variant="warning"
                                            size="xs"
                                            dot
                                        />
                                        <HcisBadge
                                            v-else
                                            label="Tanpa Grup"
                                            variant="neutral"
                                            size="xs"
                                            dot
                                        />
                                    </div>
                                </div>

                                <!-- Middle Row: Peserta Count and Summary -->
                                <div class="pa-gk-mobile-level-card__meta">
                                    <div class="pa-gk-mobile-level-card__peserta">
                                        <HcisIcon name="people-fill" size="2xs" class="text-slate-400" />
                                        <span><strong>{{ formatAngka(cacah(row.id)) }}</strong> peserta terakhir</span>
                                    </div>
                                    <span v-if="row.grup" class="pa-gk-mobile-level-card__grup-info">
                                        Total grup: <strong>{{ formatAngka(jumlahGrup(row.grup)) }}</strong> peserta
                                    </span>
                                </div>

                                <!-- Bottom Action: Prominent Select Input for Group -->
                                <div class="pa-gk-mobile-level-card__select-box">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="pa-gk-mobile-select-label">Grup Kurva Normal:</span>
                                        <span v-if="row.grup" class="pa-gk-mobile-selected-pill font-monospace">{{ row.grup }}</span>
                                    </div>
                                    <HcisSelect
                                        v-model="row.grup"
                                        size="sm"
                                        :aria-label="`Grup untuk ${row.nama}`"
                                        placeholder="— Tanpa grup (Mandiri) —"
                                        class="w-100"
                                    >
                                        <HcisSelectOption :value="''" label="— Tanpa grup (Mandiri) —" />
                                        <HcisSelectOption
                                            v-for="g in grup"
                                            :key="g.kode"
                                            :value="g.kode"
                                            :label="`${g.kode} - ${g.nama}`"
                                        />
                                    </HcisSelect>
                                </div>
                            </div>

                            <!-- Empty Search / Filter State for Mobile -->
                            <div v-if="levelTersaring.length === 0" class="pa-gk-empty-level-mobile">
                                <HcisEmptyState
                                    title="Tidak ada level jabatan"
                                    description="Tidak ada level yang cocok dengan filter atau pencarian saat ini."
                                    size="sm"
                                />
                            </div>
                        </div>

                        <!-- Standard Design System Table Component for Desktop / Tablet (> 768px) -->
                        <HcisTable
                            v-else
                            :columns="kolomTable"
                            :rows="levelTersaring"
                            size="sm"
                            hoverable
                            bordered
                            empty-title="Tidak ada level jabatan"
                            empty-description="Tidak ada level yang cocok dengan filter atau pencarian saat ini."
                        >
                            <template #cell-level="{ row }">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-slate-800">{{ row.nama }}</span>
                                    <small class="text-muted">ID #{{ row.id }}</small>
                                </div>
                            </template>

                            <template #cell-peserta="{ row }">
                                <span class="fw-semibold text-slate-700">{{ formatAngka(cacah(row.id)) }}</span>
                            </template>

                            <template #cell-grup="{ row }">
                                <HcisSelect
                                    v-model="row.grup"
                                    size="sm"
                                    :aria-label="`Grup untuk ${row.nama}`"
                                    placeholder="Tanpa grup"
                                    class="w-100"
                                >
                                    <HcisSelectOption :value="''" label="— Tanpa grup —" />
                                    <HcisSelectOption
                                        v-for="g in grup"
                                        :key="g.kode"
                                        :value="g.kode"
                                        :label="`${g.kode} - ${g.nama}`"
                                    />
                                </HcisSelect>
                            </template>

                            <template #cell-status="{ row }">
                                <div class="d-flex justify-content-end">
                                    <HcisBadge
                                        v-if="row.grup && jumlahGrup(row.grup) >= 30"
                                        label="Ideal (≥ 30)"
                                        variant="success"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-else-if="row.grup && jumlahGrup(row.grup) < 30"
                                        label="Kecil (< 30)"
                                        variant="warning"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-else
                                        label="Mandiri"
                                        variant="neutral"
                                        size="xs"
                                        dot
                                    />
                                </div>
                            </template>
                        </HcisTable>

                        <!-- Footer Simpan (Desktop / Tablet) -->
                        <template #footer>
                            <div class="pa-gk__simpan d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                                <div>
                                    <HcisBadge
                                        v-if="adaPerubahan"
                                        label="Ada perubahan konfigurasi yang belum disimpan"
                                        variant="warning"
                                        size="sm"
                                        dot
                                        pulse
                                    />
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <HcisButton
                                        v-if="adaPerubahan"
                                        label="Batalkan"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="arrow-counterclockwise"
                                        :disabled="menyimpan"
                                        @click="batalkanPerubahan"
                                    />
                                    <HcisButton
                                        label="Simpan konfigurasi"
                                        variant="primary"
                                        size="sm"
                                        leading-icon="check2-circle"
                                        data-uji="simpan"
                                        :loading="menyimpan || memuat"
                                        @click="simpan"
                                    />
                                </div>
                            </div>
                        </template>
                    </HcisCard>
                </section>
            </div>
        </div>

        <!-- Dialog: Ubah Nama Grup -->
        <HcisDialog
            v-model="dialogEdit"
            title="Ubah Grup Kurva"
            size="sm"
            variant="glass"
            sentiment="brand"
        >
            <div class="d-flex flex-column gap-3 py-2">
                <HcisField
                    id="pa-gk-edit-kode"
                    v-model="targetEdit.kode"
                    label="Kode grup"
                    placeholder="mis. STAF"
                    @keydown.enter="simpanEditGrup"
                />
                <HcisField
                    id="pa-gk-edit-nama"
                    v-model="targetEdit.nama"
                    label="Nama grup"
                    placeholder="mis. Staff & Senior Staff"
                    @keydown.enter="simpanEditGrup"
                />
            </div>
            <template #footer>
                <div class="d-flex justify-content-end gap-2 w-100 pa-gk-dialog-actions">
                    <HcisButton
                        label="Batal"
                        variant="secondary"
                        size="sm"
                        @click="dialogEdit = false"
                    />
                    <HcisButton
                        label="Simpan Perubahan"
                        variant="primary"
                        size="sm"
                        leading-icon="check-lg"
                        @click="simpanEditGrup"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Mobile Floating Glass Dock for Instant Save/Cancel Action -->
        <Transition name="pa-gk-dock">
            <aside v-if="adaPerubahan && isMobile" class="pa-gk-floating-dock" aria-label="Aksi simpan perubahan cepat">
                <div class="pa-gk-dock__inner">
                    <div class="pa-gk-dock__status">
                        <span class="pa-gk-dock__dot" />
                        <span class="pa-gk-dock__text">Perubahan belum disimpan</span>
                    </div>
                    <div class="pa-gk-dock__actions">
                        <HcisButton
                            label="Batal"
                            variant="secondary"
                            size="sm"
                            :disabled="menyimpan"
                            @click="batalkanPerubahan"
                        />
                        <HcisButton
                            label="Simpan"
                            variant="primary"
                            size="sm"
                            leading-icon="check2-circle"
                            data-uji="simpan-dock"
                            :loading="menyimpan || memuat"
                            @click="simpan"
                        />
                    </div>
                </div>
            </aside>
        </Transition>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisDivider,
    HcisField,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisTable,
    HcisTabs,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';

import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalGrupKurvaPage' });

const masterNavList = [
    { slug: 'jenis-kompetensi', label: 'Jenis Kompetensi', icon: 'diagram-2-fill', href: '/performance-appraisal/master/jenis-kompetensi' },
    { slug: 'kompetensi', label: 'Daftar Kompetensi', icon: 'bullseye', href: '/performance-appraisal/master/kompetensi' },
    { slug: 'syarat-kompetensi', label: 'Matriks Kompetensi', icon: 'grid-3x3-gap-fill', href: '/performance-appraisal/master/syarat-kompetensi' },
    { slug: 'ikatan-paket-soal', label: 'Matriks Soal', icon: 'diagram-3-fill', href: '/performance-appraisal/master/ikatan-paket-soal' },
    { slug: 'kategori-penilai', label: 'Jenis & Komposisi Penilai', icon: 'person-badge-fill', href: '/performance-appraisal/master/kategori-penilai' },
    { slug: 'bobot-pilar', label: 'Bobot KPI & Kompetensi', icon: 'pie-chart-fill', href: '/performance-appraisal/master/bobot-pilar' },
    { slug: 'pita-nilai', label: 'Skala Nilai & Grade', icon: 'bar-chart-steps', href: '/performance-appraisal/master/pita-nilai' },
    { slug: 'grup-kurva', label: 'Grup Level Kurva', icon: 'diagram-2-fill', href: '/performance-appraisal/master/grup-kurva' },
    { slug: 'relasi-organisasi', label: 'Relasi Organisasi', icon: 'people-fill', href: '/system/organisasi/relasi' },
];

const kolomTable = [
    { key: 'level', label: 'Level Jabatan', sortable: true },
    { key: 'peserta', label: 'Peserta Terakhir', align: 'center', width: '130px', sortable: true },
    { key: 'grup', label: 'Grup Kurva' },
    { key: 'status', label: 'Status Kurva', align: 'right', width: '140px' },
];

const memuat = ref(false);
const dimuat = ref(false);
const galat = ref('');
const menyimpan = ref(false);
const galatSimpan = ref('');
const snapshot = ref('');

const grup = ref([]);
const level = ref([]);
const cacahLevel = ref({});
const baru = reactive({ kode: '', nama: '' });

// Filter & Search
const cariLevel = ref('');
const cariGrup = ref('');
const bukaFormTambah = ref(false);
const filterTab = ref('semua'); // 'semua' | 'tanpa-grup'
const filterGrupAktif = ref('');

// Mobile Viewport & Segmented Tab State
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
const mobileActiveTab = ref('penempatan'); // 'penempatan' | 'grup'

const mobileTabs = computed(() => [
    {
        key: 'penempatan',
        label: 'Penempatan Level',
        badge: level.value.length,
        icon: 'table',
    },
    {
        key: 'grup',
        label: 'Grup Kurva',
        badge: grup.value.length,
        icon: 'diagram-2',
    },
]);

function handleResize() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth <= 768;
    }
}

function pilihGrupCard(kode) {
    filterGrupAktif.value = filterGrupAktif.value === kode ? '' : kode;
    if (isMobile.value && filterGrupAktif.value) {
        mobileActiveTab.value = 'penempatan';
    }
}

// Dialog Ubah Nama
const dialogEdit = ref(false);
const targetEdit = reactive({ kodeLama: '', kode: '', nama: '' });

// Reactive computed
const grupTersaring = computed(() => {
    if (!cariGrup.value.trim()) return grup.value;
    const q = cariGrup.value.trim().toLowerCase();
    return grup.value.filter((g) => g.kode.toLowerCase().includes(q) || g.nama.toLowerCase().includes(q));
});

const levelTanpaGrup = computed(() => level.value.filter((l) => !l.grup));
const namaGrupAktif = computed(() => {
    if (!filterGrupAktif.value) return '';
    return grup.value.find((g) => g.kode === filterGrupAktif.value)?.nama || '';
});

const quickChips = computed(() => [
    { value: 'semua', label: 'Semua Level', badge: level.value.length },
    {
        value: 'tanpa-grup',
        label: 'Belum Masuk Grup',
        badge: levelTanpaGrup.value.length,
        color: levelTanpaGrup.value.length > 0 ? 'warning' : '',
    },
    ...(filterGrupAktif.value ? [{
        value: filterGrupAktif.value,
        label: `Grup: ${filterGrupAktif.value}`,
        color: 'brand',
    }] : []),
]);

function handleChipClick(chip) {
    if (chip.value === 'semua') {
        filterTab.value = 'semua';
        filterGrupAktif.value = '';
    } else if (chip.value === 'tanpa-grup') {
        filterTab.value = 'tanpa-grup';
        filterGrupAktif.value = '';
    } else {
        filterGrupAktif.value = chip.value;
    }
}

function ambilSnapshot() {
    return JSON.stringify({
        grup: grup.value.map((g) => ({ kode: g.kode, nama: g.nama })),
        level: level.value.map((l) => ({ id: l.id, grup: l.grup || '' })),
    });
}

const adaPerubahan = computed(() => {
    if (!dimuat.value) return false;
    return ambilSnapshot() !== snapshot.value;
});

// Level Tersaring
const levelTersaring = computed(() => {
    let list = level.value;

    if (cariLevel.value.trim()) {
        const q = cariLevel.value.trim().toLowerCase();
        list = list.filter((l) => l.nama.toLowerCase().includes(q) || String(l.id).includes(q));
    }

    if (filterGrupAktif.value) {
        list = list.filter((l) => l.grup === filterGrupAktif.value);
    } else if (filterTab.value === 'tanpa-grup') {
        list = list.filter((l) => !l.grup);
    }

    return list;
});

function formatAngka(v) {
    if (v === null || v === undefined) return '0';
    return Number(v).toLocaleString('id-ID');
}

function terapkan(d) {
    grup.value = (d.grup || []).map((g) => ({ kode: g.kode, nama: g.nama }));
    const semua = [
        ...(d.grup || []).flatMap((g) => g.level.map((l) => ({ id: l.id, nama: l.nama, grup: g.kode }))),
        ...(d.level_tanpa_grup || []).map((l) => ({ id: l.id, nama: l.nama, grup: '' })),
    ];
    level.value = semua.sort((a, b) => a.id - b.id);
    cacahLevel.value = Object.fromEntries((d.cacah_level || []).map((c) => [c.level_id, c.jumlah]));
    snapshot.value = ambilSnapshot();
}

async function muat() {
    memuat.value = true;
    galat.value = '';

    try {
        terapkan(await paApi.daftarGrupKurva());
        dimuat.value = true;
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat grup level kurva.';
    } finally {
        memuat.value = false;
    }
}

function cacah(levelId) {
    return cacahLevel.value[levelId] ?? 0;
}

function levelDi(kode) {
    return level.value.filter((l) => l.grup === kode);
}

function jumlahGrup(kode) {
    return levelDi(kode).reduce((t, l) => t + cacah(l.id), 0);
}

function lepasLevel(levelId) {
    const l = level.value.find((item) => item.id === levelId);
    if (l) {
        l.grup = '';
    }
}

function tambahGrup() {
    const kode = baru.kode.trim().toUpperCase();
    const nama = baru.nama.trim();

    if (!kode || !nama || grup.value.some((g) => g.kode === kode)) {
        galatSimpan.value = 'Grup baru butuh kode unik dan nama.';
        return;
    }

    grup.value.push({ kode, nama });
    baru.kode = '';
    baru.nama = '';
    galatSimpan.value = '';
    hcisMessage.success(`Grup ${kode} berhasil ditambahkan.`);
}

function bukaEditGrup(g) {
    targetEdit.kodeLama = g.kode;
    targetEdit.kode = g.kode;
    targetEdit.nama = g.nama;
    dialogEdit.value = true;
}

function simpanEditGrup() {
    const kodeBaru = targetEdit.kode.trim().toUpperCase();
    const namaBaru = targetEdit.nama.trim();

    if (!kodeBaru || !namaBaru) {
        hcisMessage.error('Kode dan nama grup tidak boleh kosong.');
        return;
    }

    if (kodeBaru !== targetEdit.kodeLama && grup.value.some((g) => g.kode === kodeBaru)) {
        hcisMessage.error('Kode grup sudah digunakan.');
        return;
    }

    const g = grup.value.find((item) => item.kode === targetEdit.kodeLama);
    if (g) {
        g.nama = namaBaru;
        if (kodeBaru !== targetEdit.kodeLama) {
            g.kode = kodeBaru;
            level.value.forEach((l) => {
                if (l.grup === targetEdit.kodeLama) {
                    l.grup = kodeBaru;
                }
            });
        }
    }

    dialogEdit.value = false;
    hcisMessage.success(`Grup ${kodeBaru} diperbarui.`);
}

async function hapusGrup(g) {
    const levelTerkait = levelDi(g.kode);
    const pesan =
        levelTerkait.length > 0
            ? `Grup "${g.nama}" (${g.kode}) memiliki ${levelTerkait.length} level terkait. Menghapus grup ini akan mengembalikan level-level tersebut menjadi tanpa grup.`
            : `Apakah Anda yakin ingin menghapus grup "${g.nama}" (${g.kode})?`;

    const yakin = await hcisConfirm({
        title: 'Hapus Grup Kurva?',
        description: pesan,
        confirmText: 'Ya, Hapus Grup',
        cancelText: 'Batal',
        sentiment: 'danger',
        icon: 'trash',
    });

    if (!yakin) return;

    level.value.forEach((l) => {
        if (l.grup === g.kode) {
            l.grup = '';
        }
    });

    grup.value = grup.value.filter((item) => item.kode !== g.kode);
    if (filterGrupAktif.value === g.kode) {
        filterGrupAktif.value = '';
    }
    hcisMessage.success(`Grup ${g.kode} berhasil dihapus.`);
}

function batalkanPerubahan() {
    if (!snapshot.value) return;
    try {
        const data = JSON.parse(snapshot.value);
        grup.value = data.grup.map((g) => ({ ...g }));
        const petaGrup = Object.fromEntries(data.level.map((l) => [l.id, l.grup]));
        level.value.forEach((l) => {
            l.grup = petaGrup[l.id] ?? '';
        });
        hcisMessage.info('Perubahan dibatalkan ke kondisi awal.');
    } catch {
        muat();
    }
}

/** Seluruh konfigurasi dikirim sekaligus; grup tanpa level tidak ikut. */
async function simpan() {
    menyimpan.value = true;
    galatSimpan.value = '';

    const kiriman = grup.value
        .map((g) => ({ kode: g.kode, nama: g.nama, level_ids: levelDi(g.kode).map((l) => l.id) }))
        .filter((g) => g.level_ids.length > 0);

    try {
        const hasil = await paApi.simpanGrupKurva(kiriman);
        terapkan(hasil);
        snapshot.value = ambilSnapshot();
        hcisMessage.success('Grup level kurva disimpan.');
        await muat();
    } catch (e) {
        galatSimpan.value = e?.message || 'Konfigurasi gagal disimpan.';
        hcisMessage.error(galatSimpan.value);
    } finally {
        menyimpan.value = false;
    }
}

onMounted(() => {
    muat();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', handleResize, { passive: true });
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
    }
});

defineExpose({
    simpan,
    tambahGrup,
    level,
    grup,
    baru,
    muat,
    levelTanpaGrup,
    cacahLevel,
    jumlahGrup,
    levelDi,
    cacah,
    isMobile,
    mobileActiveTab,
});
</script>

<style scoped>
.pa-gk-page {
    min-width: 0;
    max-width: 100%;
}

.pa-gk-header-actions {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    flex-wrap: nowrap;
}

/* Sub-Navigation Switcher */
.pa-admin-switcher {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    padding: 0.25rem;
    margin-top: 0.5rem;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface-soft);
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
    border-radius: var(--hcis-radius-md);
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.pa-admin-switcher__item:hover {
    color: var(--hcis-text-strong);
    background: rgba(255, 255, 255, 0.6);
}

.pa-admin-switcher__item--active {
    color: var(--hcis-text-strong);
    background: var(--hcis-surface);
    font-weight: 750;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

/* 2-Column Master Detail Layout */
.pa-gk-container {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-gk-layout {
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: var(--hcis-space-4, 1.25rem);
    align-items: start;
}

.pa-gk-panel-title {
    margin: 0;
    font-size: var(--hcis-font-size-base, 1rem);
    font-weight: 700;
    color: var(--hcis-slate-800);
}

.pa-gk-panel-desc {
    margin: 0.15rem 0 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-slate-500);
}

.pa-gk__subjudul {
    margin: 0;
    font-size: var(--hcis-font-size-base, 1rem);
    font-weight: 700;
    color: var(--hcis-slate-800);
}

/* Sidebar (Left Panel) */
.pa-gk-sidebar {
    position: sticky;
    top: 1rem;
    align-self: start;
}

.pa-gk-sidebar-card {
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.85);
    background: #ffffff;
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
}

/* Tambah Box (Clean Creator Panel) */
.pa-gk-tambah-box {
    padding: 0.85rem;
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.2);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgb(var(--hcis-brand-50-rgb) / 0.6) 0%, rgba(255, 255, 255, 0.96) 100%);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 #ffffff;
}

/* Group List in Sidebar */
.pa-gk-grup-list {
    display: flex;
    flex-direction: column;
}

.pa-gk-grup-card {
    border-radius: var(--hcis-radius-lg, 12px);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                border-color 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-gk-grup-card:hover {
    transform: translateY(-2px);
}

.pa-gk-grup-card.is-selected {
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.6) !important;
    box-shadow: 0 0 0 2px var(--hcis-brand-primary, #0284c7),
                0 8px 24px -4px rgba(2, 132, 199, 0.22),
                inset 0 1px 0 #ffffff !important;
    transform: translateY(-2px);
}

.pa-gk-grup-card__header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.4rem;
    width: 100%;
}

.pa-gk-grup-card__title-box {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
    min-width: 0;
    flex: 1;
}

.pa-gk-code-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 0.12rem 0.4rem;
    border-radius: 6px;
    line-height: 1.2;
    flex-shrink: 0;
}

.pa-gk-code-pill.is-active {
    background: var(--hcis-brand-primary, #0284c7);
    color: #ffffff;
    border-color: var(--hcis-brand-primary, #0284c7);
}

.pa-gk-grup-card__nama {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-slate-800, #1e293b);
    line-height: 1.35;
    word-break: break-word;
    white-space: normal;
}

.pa-gk-grup-card__action-buttons {
    display: flex;
    align-items: center;
    gap: 0.15rem;
    flex-shrink: 0;
}

.pa-gk-card-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.pa-gk__cacah {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-slate-600, #475569);
    line-height: 1.3;
}

.pa-gk__cacah.is-kecil {
    color: #b45309;
}

.pa-gk-member-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    width: 100%;
}

.pa-gk-level-chip {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.72rem;
    color: var(--hcis-slate-700, #334155);
    line-height: 1.25;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.pa-gk-level-chip__name {
    font-weight: 600;
}

.pa-gk-level-chip__count {
    color: var(--hcis-slate-400, #94a3b8);
    font-size: 0.6875rem;
}

.pa-gk-level-chip__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--hcis-slate-400, #94a3b8);
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.15s ease;
    margin-left: 0.1rem;
}

.pa-gk-level-chip__remove:hover {
    background: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-800, #1e293b);
}

.pa-gk__kosong {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-style: italic;
}

.pa-gk-empty-grup {
    padding: var(--hcis-space-3);
    text-align: center;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-slate-400);
}

/* Gaussian Distribution Progress Bar */
.pa-gk-progress-track {
    height: 4px;
    background: var(--hcis-slate-100, #f1f5f9);
    border-radius: 999px;
    overflow: hidden;
    width: 100%;
}

.pa-gk-progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-gk-progress-fill.is-ideal {
    background: #10b981;
}

.pa-gk-progress-fill.is-kecil {
    background: #f59e0b;
}

.pa-gk-card-mobile-action {
    user-select: none;
}

/* Slide Transition for Tambah Box */
.pa-gk-slide-enter-active,
.pa-gk-slide-leave-active {
    transition: all 0.22s ease-out;
}

.pa-gk-slide-enter-from,
.pa-gk-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.pa-gk-sidebar-guide {
    border-top: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

/* Main Section (Right Panel) */
.pa-gk-main-card {
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.85);
    background: #ffffff;
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
}

/* Mobile Floating Glass Dock */
.pa-gk-floating-dock {
    position: fixed;
    bottom: 1rem;
    left: 1rem;
    right: 1rem;
    z-index: 1040;
    pointer-events: none;
}

.pa-gk-dock__inner {
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.16), 0 4px 12px -2px rgba(15, 23, 42, 0.08);
}

.pa-gk-dock__status {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-gk-dock__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
    animation: pa-gk-pulse 1.8s infinite;
    flex-shrink: 0;
}

@keyframes pa-gk-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.pa-gk-dock__text {
    font-size: 0.775rem;
    font-weight: 600;
    color: var(--hcis-slate-700, #334155);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-gk-dock__actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
}

/* Dock Transition Animations */
.pa-gk-dock-enter-active,
.pa-gk-dock-leave-active {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.24s ease;
}

.pa-gk-dock-enter-from,
.pa-gk-dock-leave-to {
    opacity: 0;
    transform: translateY(110%) scale(0.96);
}

/* Dialog Mobile Actions (50/50 Grid) */
.pa-gk-dialog-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

/* Quick Stats Pills */
.pa-gk-quick-stats {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-gk-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}

.pa-gk-stat-pill:hover {
    background: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-800, #1e293b);
}

.pa-gk-stat-pill.is-active {
    background: var(--hcis-brand-subtle, #e0f2fe);
    color: var(--hcis-brand-primary, #0284c7);
    border-color: rgba(2, 132, 199, 0.25);
    font-weight: 600;
}

.pa-gk-stat-pill.is-warning {
    color: #b45309;
    background: #fef3c7;
    border-color: #fde68a;
}

/* Active Filter Banner */
.pa-gk-active-filter-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--hcis-brand-subtle, #e0f2fe);
    border: 1px solid rgba(2, 132, 199, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
}

/* Mobile Level List Cards */
.pa-gk-mobile-level-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding-bottom: 2rem;
}

.pa-gk-mobile-level-card {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.75rem 0.85rem;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease;
}

.pa-gk-mobile-level-card.is-unassigned {
    border-left: 3.5px solid #f59e0b;
    background: #fffdfa;
}

.pa-gk-mobile-level-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-gk-mobile-level-card__title-group {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.35rem;
    min-width: 0;
}

.pa-gk-mobile-level-card__name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-slate-850, #1e293b);
    line-height: 1.25;
}

.pa-gk-mobile-level-card__id {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, #64748b);
    background: var(--hcis-slate-100, #f1f5f9);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.pa-gk-mobile-level-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-600, #475569);
    padding-bottom: 0.4rem;
    border-bottom: 1px dashed var(--hcis-border-subtle, #e2e8f0);
}

.pa-gk-mobile-level-card__peserta {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-gk-mobile-level-card__grup-info {
    font-size: 0.72rem;
    color: var(--hcis-slate-500, #64748b);
}

.pa-gk-mobile-level-card__select-box {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-gk-mobile-select-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-700, #334155);
}

.pa-gk-mobile-selected-pill {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-brand-primary, #0284c7);
    background: var(--hcis-brand-subtle, #e0f2fe);
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
}

.pa-gk-empty-level-mobile {
    padding: 1.5rem 0.5rem;
    text-align: center;
}

/* Responsive Breakpoints */
@media (min-width: 769px) and (max-width: 1024px) {
    .pa-gk-layout {
        grid-template-columns: 300px minmax(0, 1fr) !important;
        gap: 1rem !important;
    }
    .pa-gk-sidebar {
        position: sticky;
        top: 1rem;
    }
}

@media (max-width: 768px) {
    .pa-gk-layout {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }
    .pa-gk-sidebar {
        position: static;
    }
    .pa-gk-sidebar-card,
    .pa-gk-main-card {
        border-radius: var(--hcis-radius-lg, 12px);
    }
    .pa-gk-grup-card {
        padding: 0.65rem 0.75rem !important;
    }
    .pa-gk-dialog-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }
    .pa-gk-dialog-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}
</style>
