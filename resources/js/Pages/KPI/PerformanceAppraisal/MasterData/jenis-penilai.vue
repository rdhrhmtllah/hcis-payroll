<template>
    <main class="pa-jp-page">
        <!-- Unified HCIS Header with Sub-Navigation Console -->
        <HcisHeader
            title="Jenis & Komposisi Penilai"
            kicker="MASTER DATA · LEVEL × JENIS & KOMPOSISI PENILAI"
            description="Tandai kategori penilai yang berlaku pada tiap level jabatan dan atur komposisi persentase bobot (%) serta kuotanya langsung di sini. Total bobot tiap level harus genap 100%."
            icon="person-badge-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="mb-3"
        >
            <template #badge>
                <HcisBadge
                    v-if="perluPerhatian.length"
                    :label="`${perluPerhatian.length} level perlu perhatian`"
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
                <HcisBadge
                    v-else-if="level.length"
                    :label="`${level.length} level aktif`"
                    variant="brand"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>

            <template #actions>
                <div class="pa-header-actions">
                    <HcisButton
                        label="Segarkan"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        title="Muat ulang matriks jenis penilai"
                        @click="muat"
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
                        :class="{ 'pa-admin-switcher__item--active': activeMaster === item.slug }"
                    >
                        <HcisIcon :name="item.icon" size="2xs" />
                        <span>{{ item.label }}</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <!-- Error State -->
        <HcisResultState
            v-if="error && !memuat"
            status="danger"
            variant="glass"
            layout="compact"
            title="Matriks jenis penilai tidak dapat dimuat"
            :description="error"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat"
        />

        <!-- Loading Skeleton -->
        <div v-else-if="memuat" class="pa-jp-skeleton">
            <div class="pa-skel-cockpit">
                <HcisSkeleton v-for="n in 4" :key="`skel-st-${n}`" height="5.5rem" rounded="xl" />
            </div>
            <div class="pa-skel-toolbar">
                <HcisSkeleton width="340px" height="2.5rem" rounded="lg" />
                <HcisSkeleton width="240px" height="2.5rem" rounded="lg" />
            </div>
            <div class="pa-skel-matrix">
                <HcisSkeleton v-for="n in 6" :key="`sk-${n}`" height="4rem" rounded="xl" />
            </div>
        </div>

        <template v-else>
            <!-- 4-Stat Cockpit Overview -->
            <HcisStatGroup :cols="4" gap="sm" :layout="isMobile ? 'dense' : 'compact'" class="pa-cockpit-group mb-3">
                <HcisStatCard
                    label="Level Jabatan"
                    :value="`${level.length} Level`"
                    :caption="isMobile ? `${formatAngka(totalKaryawanAktif)} Karyawan` : `${formatAngka(totalKaryawanAktif)} Karyawan Aktif`"
                    icon="layers-fill"
                    sentiment="brand"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
                <HcisStatCard
                    label="Kategori Penilai"
                    :value="`${kategori.length} Kategori`"
                    :caption="isMobile ? 'Atasan, Rekan, Diri' : 'Atasan, Rekan & Diri'"
                    icon="person-badge-fill"
                    sentiment="purple"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
                <HcisStatCard
                    label="Kelengkapan Bobot"
                    :value="`${levelLengkapCount}/${levelAktifCount} Level`"
                    :caption="levelBelumLengkapCount === 0 ? '100% Pas' : `${levelBelumLengkapCount} level belum 100%`"
                    :icon="levelBelumLengkapCount === 0 ? 'check-circle-fill' : 'clock-history'"
                    :sentiment="levelBelumLengkapCount === 0 ? 'success' : 'brand'"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
                <HcisStatCard
                    label="Pengecualian Level"
                    :value="`${levelDikecualikanCount} Non-PA`"
                    :caption="levelDikecualikanCount === 0 ? 'Semua diikutkan' : `${levelDikecualikanCount} level dikecualikan`"
                    :icon="levelDikecualikanCount === 0 ? 'shield-check' : 'slash-circle'"
                    :sentiment="levelDikecualikanCount === 0 ? 'success' : 'neutral'"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
            </HcisStatGroup>

            <!-- Alert List: Level Memerlukan Perhatian -->
            <HcisAlert
                v-if="perluPerhatian.length"
                variant="danger"
                appearance="glass"
                class="mb-3"
                icon="exclamation-octagon-fill"
                :title="isMobile ? `${perluPerhatian.length} level perlu penyesuaian bobot` : `${perluPerhatian.length} level berisi karyawan tetapi komposisi penilainya belum benar`"
            >
                <ul class="pa-jp-alert-list">
                    <li v-for="l in perluPerhatian" :key="`w-${l.id_level}`" class="pa-jp-alert-item">
                        <div class="pa-jp-alert-content">
                            <strong>{{ l.nama_level }}</strong>
                            <span v-if="l.jumlah_kategori === 0"> &mdash; belum punya satu pun kategori penilai</span>
                            <span v-else-if="l.jumlah_belum_berbobot > 0">
                                &mdash; {{ l.jumlah_belum_berbobot }} kategori belum berbobot
                            </span>
                            <span v-else> &mdash; total bobot {{ l.total_bobot }}%, seharusnya 100%</span>
                            <span class="pa-jp-alert-count">({{ formatAngka(l.jumlah_karyawan) }} karyawan)</span>
                        </div>
                        <HcisButton
                            label="Atur Komposisi"
                            variant="danger"
                            size="xs"
                            leading-icon="pencil-square"
                            @click="bukaDialogKomposisi(l)"
                        />
                    </li>
                </ul>
            </HcisAlert>

            <!-- Standard Design System Filter Bar Component -->
            <HcisFilterBar
                :search="kataKunciLevel"
                :search-placeholder="isMobile ? 'Cari level jabatan...' : 'Cari level jabatan / ID level...'"
                :quick-chips="statusQuickChips"
                :active-chip-value="filterTab"
                :results-count="levelTersaring.length"
                :show-view-toggle="true"
                :view-mode="viewMode"
                variant="card"
                sentiment="brand"
                size="sm"
                glow
                class="pa-filter-bar-wrap mb-3"
                @update:search="kataKunciLevel = $event"
                @chip-click="ubahChipFilter"
                @update:view-mode="viewMode = $event"
                @reset="resetFilter"
            />

            <!-- Floating Bulk Action Bar -->
            <transition name="pa-bulk-bar">
                <div v-if="terpilih.length > 0" class="pa-bulk-bar mb-3">
                    <div class="pa-bulk-bar__info">
                        <div class="pa-bulk-bar__count-badge">
                            <HcisIcon name="check2-square" size="xs" />
                            <strong>{{ terpilih.length }} level dipilih</strong>
                        </div>
                        <span class="pa-bulk-bar__meta">
                            ({{ formatAngka(totalKaryawanTerpilih) }} karyawan &bull; {{ terpilihAktif.length }} aktif, {{ terpilihDikecualikan.length }} non-PA)
                        </span>
                    </div>

                    <div class="pa-bulk-bar__actions">
                        <HcisButton
                            v-if="terpilihAktif.length > 0"
                            :label="`Kecualikan (${terpilihAktif.length})`"
                            variant="warning"
                            size="xs"
                            leading-icon="slash-circle"
                            @click="bukaDialogBulkKecualikan"
                        />
                        <HcisButton
                            v-if="terpilihDikecualikan.length > 0"
                            :label="`Ikutkan Lagi (${terpilihDikecualikan.length})`"
                            variant="secondary"
                            size="xs"
                            leading-icon="arrow-counterclockwise"
                            @click="bukaKonfirmasiBulkCabut"
                        />
                        <HcisButton
                            label="Batal Pilih"
                            variant="secondary"
                            size="xs"
                            @click="terpilih = []"
                        />
                    </div>
                </div>
            </transition>

            <!-- Collapsible Section: Urutan Wizard & Kalibrasi Penilai -->
            <div v-if="kategori.length" class="pa-config-collapsible mb-3">
                <div
                    class="pa-config-card-toggle"
                    :class="{ 'is-open': bukaPengaturanKategori }"
                    role="button"
                    tabindex="0"
                    :aria-expanded="bukaPengaturanKategori"
                    @click="bukaPengaturanKategori = !bukaPengaturanKategori"
                    @keydown.enter="bukaPengaturanKategori = !bukaPengaturanKategori"
                >
                    <div class="pa-config-toggle__left">
                        <div class="pa-config-toggle__icon">
                            <HcisIcon name="diagram-3-fill" size="xs" />
                        </div>
                        <div class="pa-config-toggle__text">
                            <div class="pa-config-toggle__title-row">
                                <span class="pa-config-toggle__title">Urutan Wizard & Kalibrasi Penilai</span>
                                <HcisBadge label="Berlaku Seketika" variant="success" appearance="subtle" size="2xs" dot />
                            </div>
                            <div class="pa-config-toggle__preview">
                                <span class="pa-config-preview-label">Alur evaluasi:</span>
                                <div class="pa-config-preview-chips">
                                    <span
                                        v-for="(k, idx) in urutanKategoriRingkas"
                                        :key="`preview-k-${k.id}`"
                                        class="pa-config-mini-chip"
                                    >
                                        <span class="pa-config-mini-chip__num">{{ idx + 1 }}</span>
                                        <span>{{ k.nama }}</span>
                                        <HcisIcon v-if="idx < urutanKategoriRingkas.length - 1" name="arrow-right-short" size="2xs" class="pa-config-arrow" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pa-config-toggle__right">
                        <HcisButton
                            :label="bukaPengaturanKategori ? 'Tutup Pengaturan' : 'Atur Urutan & Kalibrasi'"
                            :leading-icon="bukaPengaturanKategori ? 'chevron-up' : 'sliders'"
                            variant="secondary"
                            size="xs"
                            @click.stop="bukaPengaturanKategori = !bukaPengaturanKategori"
                        />
                    </div>
                </div>

                <transition name="pa-collapse">
                    <div v-if="bukaPengaturanKategori" class="pa-config-collapse-content">
                        <PaPengaturanKategoriPenilai
                            :kategori="kategori"
                            @berubah="muat()"
                        />
                    </div>
                </transition>
            </div>

            <!--
             | Minimal masa kerja (DDL-2026-191): sebaris dengan pengaturan di atasnya. Angkanya
             | nilai AWAL siklus baru; siklus yang sudah ada memegang salinannya sendiri.
             -->
            <PaAturanMasaKerja
                v-if="masaKerja"
                class="mb-3"
                :nilai="masaKerja.min_masa_kerja_bulan"
                :maks="masaKerja.maks_bulan"
                boleh-ubah
                :menyimpan="menyimpanMasaKerja"
                kicker="MASTER DATA · KELAYAKAN PESERTA"
                keterangan="Angka ini menjadi nilai awal setiap siklus baru; siklus yang sudah ada tidak berubah."
                @simpan="simpanMasaKerja"
            />

            <!-- Empty Search Results State -->
            <HcisEmptyState
                v-if="levelTersaring.length === 0"
                variant="card"
                icon="search"
                title="Tidak ada level yang sesuai"
                description="Coba ubah kata kunci pencarian atau pilih tab filter level lainnya."
                primary-text="Reset Filter"
                primary-icon="arrow-clockwise"
                class="mb-3"
                @primary-click="resetFilter"
            />

            <!-- Main Content Container (v-else) -->
            <div v-else class="pa-jp-content">
                <!-- Mode 1: HcisCard Grid View -->
                <div v-if="viewMode === 'grid'" class="pa-jp-grid">
                    <HcisCard
                        v-for="l in levelTersaring"
                        :key="`card-level-${l.id_level}`"
                        variant="default"
                        :sentiment="l.dikecualikan_pa ? 'neutral' : (Math.abs(l.total_bobot - 100) < 0.01 && l.jumlah_belum_berbobot === 0 ? 'success' : 'brand')"
                        class="pa-level-card"
                        :class="{
                            'is-selected': terpilih.includes(l.id_level),
                            'is-excluded': l.dikecualikan_pa,
                            'is-deficit': !l.dikecualikan_pa && (Math.abs(l.total_bobot - 100) > 0.01 || l.jumlah_belum_berbobot > 0),
                        }"
                    >
                        <template #header>
                            <div class="pa-level-card__header">
                                <div class="pa-level-card__title-wrap">
                                    <label class="pa-card-check-wrap" :title="`Pilih ${l.nama_level}`">
                                        <input
                                            type="checkbox"
                                            :checked="terpilih.includes(l.id_level)"
                                            class="pa-check-input"
                                            @change="togglePilihLevel(l.id_level)"
                                        />
                                    </label>
                                    <div class="pa-level-card__name-box">
                                        <div class="pa-level-card__badges">
                                            <span class="pa-level-id-pill">ID: {{ l.id_level }}</span>
                                            <HcisBadge
                                                v-if="l.dikecualikan_pa"
                                                label="Non-PA"
                                                variant="neutral"
                                                size="xs"
                                            />
                                            <HcisBadge
                                                v-else-if="Math.abs(l.total_bobot - 100) < 0.01 && l.jumlah_belum_berbobot === 0"
                                                label="100% Pas"
                                                variant="success"
                                                size="xs"
                                                dot
                                            />
                                            <HcisBadge
                                                v-else-if="l.total_bobot < 100"
                                                :label="`Kurang ${formatDesimal(100 - l.total_bobot)}%`"
                                                variant="warning"
                                                size="xs"
                                                dot
                                            />
                                            <HcisBadge
                                                v-else-if="l.total_bobot > 100"
                                                :label="`Lebih ${formatDesimal(l.total_bobot - 100)}%`"
                                                variant="danger"
                                                size="xs"
                                                dot
                                            />
                                            <HcisBadge
                                                v-else
                                                label="Belum Lengkap"
                                                variant="warning"
                                                size="xs"
                                                dot
                                            />
                                        </div>
                                        <h3 class="pa-level-card__title">{{ l.nama_level }}</h3>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="pa-level-card__body">
                            <!-- Employee and Policy Meta -->
                            <div class="pa-level-card__meta-bar">
                                <span class="pa-level-card__emp">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <strong>{{ formatAngka(l.jumlah_karyawan) }}</strong> karyawan
                                </span>
                                <template v-if="l.policy">
                                    <span class="pa-level-sep">&bull;</span>
                                    <span class="pa-level-policy-tag">{{ l.policy.kode }} v{{ l.policy.versi }}</span>
                                </template>
                            </div>

                            <!-- Exclusion notice if Non-PA -->
                            <div v-if="l.dikecualikan_pa" class="pa-level-excluded-notice">
                                <div class="pa-level-excluded-icon">
                                    <HcisIcon name="slash-circle" size="xs" />
                                </div>
                                <div class="pa-level-excluded-text">
                                    <strong>Dikecualikan dari Penilaian (Non-PA)</strong>
                                    <span v-if="l.alasan_dikecualikan">{{ l.alasan_dikecualikan }}</span>
                                    <span v-else class="text-muted">Tidak dievaluasi pada siklus aktif</span>
                                </div>
                            </div>

                            <!-- Composition Visual if Active -->
                            <div v-else class="pa-level-comp-box">
                                <div class="pa-comp-header">
                                    <span class="pa-comp-label">
                                        <HcisIcon name="pie-chart-fill" size="2xs" />
                                        <span>Komposisi Bobot</span>
                                    </span>
                                    <span
                                        class="pa-comp-total-pill"
                                        :class="{
                                            'is-valid': Math.abs(l.total_bobot - 100) < 0.01 && l.jumlah_belum_berbobot === 0,
                                            'is-warning': l.total_bobot < 100 || l.jumlah_belum_berbobot > 0,
                                            'is-danger': l.total_bobot > 100,
                                        }"
                                    >
                                        <HcisIcon
                                            :name="Math.abs(l.total_bobot - 100) < 0.01 && l.jumlah_belum_berbobot === 0 ? 'check-circle-fill' : 'exclamation-circle-fill'"
                                            size="2xs"
                                        />
                                        <span>{{ l.total_bobot }}%</span>
                                    </span>
                                </div>

                                <!-- Multi-segment Stacked Progress Bar -->
                                <div class="pa-comp-bar" :title="`Total alokasi bobot: ${l.total_bobot}%`">
                                    <div
                                        v-for="seg in segmenKomposisi(l)"
                                        :key="`seg-${l.id_level}-${seg.kode}`"
                                        class="pa-comp-bar__segment"
                                        :style="{ width: `${seg.lebar}%`, backgroundColor: seg.warna }"
                                        :title="`${seg.nama}: ${seg.bobot}%`"
                                    />
                                    <div
                                        v-if="l.total_bobot < 100"
                                        class="pa-comp-bar__remaining"
                                        :style="{ width: `${Math.max(0, 100 - l.total_bobot)}%` }"
                                        :title="`Sisa belum teralokasi: ${formatDesimal(100 - l.total_bobot)}%`"
                                    />
                                </div>

                                <!-- Active Categories Pill Breakdown -->
                                <div v-if="kategoriAktifLevel(l).length > 0" class="pa-comp-raters-list">
                                    <div
                                        v-for="k in kategoriAktifLevel(l)"
                                        :key="`k-active-${l.id_level}-${k.kode}`"
                                        class="pa-comp-rater-row"
                                        role="button"
                                        tabindex="0"
                                        :title="judulSel(l, k)"
                                        @click="bukaKonfirmasiSel(l, k)"
                                    >
                                        <div class="pa-comp-rater-name-wrap">
                                            <span class="pa-comp-rater-dot" :style="{ backgroundColor: warnaKategori(k.kode) }" />
                                            <span class="pa-comp-rater-name">{{ k.nama }}</span>
                                        </div>
                                        <div class="pa-comp-rater-values">
                                            <template v-if="l.sel[k.kode]?.berbobot">
                                                <strong class="pa-comp-rater-pct">{{ l.sel[k.kode].bobot }}%</strong>
                                                <span class="pa-comp-rater-quota">({{ l.sel[k.kode].min }}–{{ l.sel[k.kode].maks ?? '∞' }})</span>
                                            </template>
                                            <span v-else class="pa-comp-rater-pending">Menunggu bobot</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="pa-comp-empty-prompt">
                                    <HcisIcon name="info-circle" size="2xs" />
                                    <span>Belum ada penilai dikonfigurasi</span>
                                </div>
                            </div>
                        </div>

                        <template #footer>
                            <div class="pa-level-card__footer">
                                <template v-if="l.dikecualikan_pa">
                                    <HcisButton
                                        label="Ikutkan Lagi"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="arrow-counterclockwise"
                                        :loading="sibukLevel === l.id_level"
                                        class="w-100"
                                        @click="bukaKonfirmasiCabutPengecualian(l)"
                                    />
                                </template>
                                <template v-else>
                                    <HcisButton
                                        :label="isMobile ? 'Atur Bobot' : 'Atur Komposisi'"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="pencil-square"
                                        @click="bukaDialogKomposisi(l)"
                                    />
                                    <HcisButton
                                        label="Kecualikan"
                                        variant="danger-quiet"
                                        size="sm"
                                        leading-icon="slash-circle"
                                        :loading="sibukLevel === l.id_level"
                                        @click="bukaDialog(l)"
                                    />
                                </template>
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <!-- Mode 2: Interactive Matrix Table Card -->
                <HcisCard
                    v-else-if="viewMode === 'table'"
                    variant="glass"
                    sentiment="brand"
                    :padding="false"
                    class="pa-matrix-card"
                >
                    <div class="pa-matrix-scroll-wrap">
                        <table class="pa-matrix-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="pa-col-check">
                                        <div class="pa-th-content pa-th-content--center">
                                            <input
                                                type="checkbox"
                                                :checked="isSemuaTerpilih"
                                                :indeterminate.prop="isSebagianTerpilih"
                                                :disabled="levelTersaring.length === 0"
                                                class="pa-check-input"
                                                title="Pilih semua level yang tampil"
                                                @change="togglePilihSemua($event.target.checked)"
                                            />
                                        </div>
                                    </th>
                                    <th scope="col" class="pa-col-level">
                                        <div class="pa-th-content">
                                            <HcisIcon name="layers-fill" size="2xs" color="brand" />
                                            <span>Level Jabatan</span>
                                        </div>
                                    </th>
                                    <th
                                        v-for="k in kategori"
                                        :key="k.kode"
                                        scope="col"
                                        class="pa-col-rater"
                                        :title="k.deskripsi || k.nama"
                                    >
                                        <div class="pa-th-content pa-th-content--center">
                                            <HcisIcon :name="ikonKategori(k.kode)" size="2xs" />
                                            <span>{{ k.nama }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="pa-col-total">
                                        <div class="pa-th-content pa-th-content--center">
                                            <HcisIcon name="percent" size="2xs" />
                                            <span>Total</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="pa-col-aksi">
                                        <div class="pa-th-content pa-th-content--right">
                                            <span>Aksi</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="l in levelTersaring"
                                    :key="l.id_level"
                                    class="pa-matrix-row"
                                    :class="{
                                        'is-selected': terpilih.includes(l.id_level),
                                        'is-excluded': l.dikecualikan_pa,
                                        'is-deficit': !l.dikecualikan_pa && (Math.abs(l.total_bobot - 100) > 0.01 || l.jumlah_belum_berbobot > 0),
                                    }"
                                >
                                    <td class="pa-col-check">
                                        <div class="pa-td-check-wrap">
                                            <input
                                                type="checkbox"
                                                :checked="terpilih.includes(l.id_level)"
                                                class="pa-check-input"
                                                @change="togglePilihLevel(l.id_level)"
                                            />
                                        </div>
                                    </td>
                                    <td class="pa-col-level">
                                        <div class="pa-level-meta">
                                            <div class="pa-level-title-row">
                                                <strong class="pa-level-name">{{ l.nama_level }}</strong>
                                                <span class="pa-level-id-pill">ID: {{ l.id_level }}</span>
                                                <span v-if="l.dikecualikan_pa" class="pa-tag-excluded">Non-PA</span>
                                            </div>
                                            <div class="pa-level-reqs-row">
                                                <HcisIcon name="people-fill" size="2xs" />
                                                <span>{{ formatAngka(l.jumlah_karyawan) }} karyawan</span>
                                                <template v-if="l.policy">
                                                    <span class="pa-level-sep">&bull;</span>
                                                    <span class="pa-level-policy-tag">{{ l.policy.kode }} v{{ l.policy.versi }}</span>
                                                </template>
                                            </div>
                                            <div v-if="l.dikecualikan_pa && l.alasan_dikecualikan" class="pa-level-alasan">
                                                <HcisIcon name="info-circle" size="2xs" class="me-1 flex-shrink-0" />
                                                <span>{{ l.alasan_dikecualikan }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!--
                                        TIGA keadaan sel:
                                        1. Tidak berlaku (dash `—` with clean hover prompt).
                                        2. Berlaku tetapi belum berbobot (`Menunggu bobot` warm amber badge).
                                        3. Berlaku dan berbobot (`{{ bobot }}%` bold text + kuota subtitle).
                                    -->
                                    <td v-for="k in kategori" :key="`${l.id_level}-${k.kode}`" class="pa-col-rater">
                                        <span v-if="l.dikecualikan_pa" class="pa-cell-na__dash">&mdash;</span>

                                        <button
                                            v-else
                                            type="button"
                                            class="pa-cell-btn"
                                            :class="{
                                                'is-on': !!l.sel[k.kode],
                                                'is-pending': l.sel[k.kode] && !l.sel[k.kode].berbobot,
                                            }"
                                            :disabled="sibukSel === `${l.id_level}-${k.id}`"
                                            :title="judulSel(l, k)"
                                            @click="bukaKonfirmasiSel(l, k)"
                                        >
                                            <template v-if="!l.sel[k.kode]">
                                                <span class="pa-cell-na__dash">&mdash;</span>
                                                <span class="pa-cell-hover-hint">+ Berlakukan</span>
                                            </template>
                                            <template v-else-if="!l.sel[k.kode].berbobot">
                                                <div class="pa-cell-pending-wrap">
                                                    <HcisIcon name="hourglass-split" size="2xs" class="text-amber-600" />
                                                    <span class="pa-cell-menunggu">Menunggu bobot</span>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <strong class="pa-cell-bobot">{{ l.sel[k.kode].bobot }}%</strong>
                                                <span class="pa-cell-kuota">
                                                    {{ l.sel[k.kode].min }}&ndash;{{ l.sel[k.kode].maks ?? '&infin;' }} penilai
                                                </span>
                                            </template>
                                        </button>
                                    </td>

                                    <td class="pa-col-total">
                                        <template v-if="!l.dikecualikan_pa && l.jumlah_kategori">
                                            <button
                                                v-if="Math.abs(l.total_bobot - 100) > 0.01 || l.jumlah_belum_berbobot > 0"
                                                type="button"
                                                class="pa-total-pill is-off"
                                                :title="`Total bobot ${l.total_bobot}% (belum 100%). Klik untuk mengatur komposisi bobot.`"
                                                @click="bukaDialogKomposisi(l)"
                                            >
                                                <div class="pa-total-pill-inner">
                                                    <div class="pa-total-head">
                                                        <HcisIcon name="exclamation-octagon-fill" size="2xs" class="text-danger flex-shrink-0" />
                                                        <span class="pa-total-num">{{ l.total_bobot }}%</span>
                                                    </div>
                                                    <span v-if="l.total_bobot < 100" class="pa-total-badge-sub is-kurang"> (Kurang {{ formatDesimal(100 - l.total_bobot) }}%)</span>
                                                    <span v-else-if="l.total_bobot > 100" class="pa-total-badge-sub is-lebih"> (Lebih {{ formatDesimal(l.total_bobot - 100) }}%)</span>
                                                    <span v-else-if="l.jumlah_belum_berbobot > 0" class="pa-total-badge-sub is-pending"> (Belum Lengkap)</span>
                                                </div>
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                class="pa-total-pill is-complete"
                                                title="Bobot sudah genap 100%. Klik untuk meninjau atau menyesuaikan komposisi."
                                                @click="bukaDialogKomposisi(l)"
                                            >
                                                <div class="pa-total-head">
                                                    <HcisIcon name="check-circle-fill" size="2xs" class="text-emerald-600 flex-shrink-0" />
                                                    <span class="pa-total-num">{{ l.total_bobot }}%</span>
                                                </div>
                                            </button>
                                        </template>
                                        <span v-else class="pa-cell-na__dash">&mdash;</span>
                                    </td>

                                    <td class="pa-col-aksi">
                                        <div class="pa-td-aksi-wrap">
                                            <HcisButton
                                                v-if="l.dikecualikan_pa"
                                                label="Ikutkan Lagi"
                                                variant="secondary"
                                                size="xs"
                                                leading-icon="arrow-counterclockwise"
                                                :loading="sibukLevel === l.id_level"
                                                @click="bukaKonfirmasiCabutPengecualian(l)"
                                            />
                                            <template v-else>
                                                <HcisButton
                                                    label="Atur Bobot"
                                                    variant="secondary"
                                                    size="xs"
                                                    leading-icon="pencil-square"
                                                    title="Atur komposisi bobot dan kuota penilai untuk level ini"
                                                    @click="bukaDialogKomposisi(l)"
                                                />
                                                <HcisButton
                                                    label="Kecualikan"
                                                    variant="danger-quiet"
                                                    size="xs"
                                                    leading-icon="slash-circle"
                                                    :loading="sibukLevel === l.id_level"
                                                    @click="bukaDialog(l)"
                                                />
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </HcisCard>

                <!-- Context Footer Note -->
                <div class="pa-jp-catatan-card mt-3">
                    <HcisIcon name="info-circle-fill" size="xs" class="text-brand-600 flex-shrink-0" />
                    <p class="mb-0">
                        Klik sel untuk menandai kategori mana yang menilai level bersangkutan, atau klik tombol
                        <strong>Atur Bobot</strong> / pill total untuk mengatur persentase bobot (%) dan kuota penilai
                        hingga totalnya genap 100%. Data tersimpan dan tersinkronisasi secara real-time pada kebijakan aktif.
                    </p>
                </div>
            </div>
        </template>

        <!-- Dialog 1: Konfirmasi Toggle Sel Matriks / Ikutkan Level -->
        <HcisDialog
            :model-value="konfirmasi !== null"
            :title="konfirmasi?.judul ?? ''"
            kicker="MASTER DATA · MATRIKS JENIS PENILAI"
            :icon="konfirmasi?.ikon ?? 'question-circle'"
            :sentiment="konfirmasi?.nada ?? 'brand'"
            :size="isMobile ? 'full' : 'md'"
            @update:model-value="konfirmasi = null"
        >
            <div v-if="konfirmasi" class="pa-jp-dialog">
                <!-- AKIBATNYA DISEBUTKAN SECARA TRANSPARAN -->
                <HcisAlert
                    v-if="konfirmasi.jenis === 'sel-cabut'"
                    variant="warning"
                    appearance="subtle"
                    icon="exclamation-triangle-fill"
                >
                    <p class="mb-1">
                        <strong>{{ konfirmasi.kategori.nama }}</strong> tidak akan lagi menilai
                        <strong>{{ konfirmasi.level.nama_level }}</strong> pada siklus berikutnya.
                    </p>
                    <p class="mb-1">
                        Bobot dan kuota yang sudah ditetapkan untuk sel ini
                        <strong>ikut tercabut</strong>, sehingga total bobot level ini tidak lagi
                        100% sampai sisanya disesuaikan di Komposisi Penilai.
                    </p>
                    <p class="mb-0">
                        Barisnya dinonaktifkan, bukan dihapus &mdash; riwayatnya tetap bisa dibaca.
                        Siklus yang sedang berjalan tidak disentuh.
                    </p>
                </HcisAlert>

                <HcisAlert
                    v-else-if="konfirmasi.jenis === 'sel-pasang'"
                    variant="info"
                    appearance="subtle"
                    icon="info-circle-fill"
                >
                    <p class="mb-1">
                        <strong>{{ konfirmasi.kategori.nama }}</strong> akan menilai
                        <strong>{{ konfirmasi.level.nama_level }}</strong>.
                    </p>
                    <p class="mb-0">
                        Sel ini lahir <strong>tanpa bobot</strong>. Selama bobotnya belum
                        ditetapkan di Komposisi Penilai, siklus untuk level ini
                        <strong>tidak bisa disiapkan</strong>.
                    </p>
                </HcisAlert>

                <HcisAlert v-else variant="info" appearance="subtle" icon="info-circle-fill">
                    <p class="mb-1">
                        <strong>{{ formatAngka(konfirmasi.level.jumlah_karyawan) }} karyawan</strong>
                        di level ini akan kembali dibentuk sebagai peserta pada penyiapan siklus
                        berikutnya.
                    </p>
                    <p class="mb-0">
                        Pastikan level ini sudah punya syarat kompetensi, komposisi penilai, dan
                        bobot yang lengkap &mdash; kalau belum, penyiapan siklusnya akan ditolak.
                    </p>
                </HcisAlert>
            </div>

            <template #footer>
                <div class="pa-dialog-actions--responsive">
                    <HcisButton label="Batal" variant="secondary" size="sm" @click="konfirmasi = null" />
                    <HcisButton
                        :label="konfirmasi?.labelAksi ?? 'Lanjutkan'"
                        :variant="konfirmasi?.nada === 'warning' ? 'warning' : 'primary'"
                        size="sm"
                        :leading-icon="konfirmasi?.ikon"
                        :loading="menyimpan"
                        data-uji="konfirmasi-lanjut"
                        @click="jalankanKonfirmasi"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Dialog 2: Kecualikan Level dari Performance Appraisal -->
        <HcisDialog
            v-model="dialog"
            :title="`Kecualikan ${calon?.nama_level ?? 'level'} dari Performance Appraisal?`"
            kicker="MASTER DATA · PENGECUALIAN LEVEL"
            icon="slash-circle"
            sentiment="warning"
            :size="isMobile ? 'full' : 'md'"
        >
            <div class="pa-jp-dialog">
                <HcisAlert variant="info" appearance="subtle" icon="info-circle-fill">
                    <p class="mb-1">
                        <strong>{{ formatAngka(calon?.jumlah_karyawan ?? 0) }} karyawan</strong>
                        ada di level ini. Mereka tidak akan lagi dibentuk sebagai peserta pada
                        penyiapan siklus <strong>berikutnya</strong>.
                    </p>
                    <p class="mb-0">
                        Siklus yang <strong>sedang berjalan tidak disentuh</strong> — pesertanya
                        membawa levelnya sebagai salinan, dan mencabut mereka di tengah jalan
                        akan membuang penilaian yang sudah masuk.
                    </p>
                </HcisAlert>

                <HcisField
                    v-model="alasan"
                    label="Alasan pengecualian"
                    type="textarea"
                    :rows="3"
                    required
                    placeholder="Mis. level ini bukan jenjang jabatan sungguhan dan tidak pernah dinilai."
                    hint="Wajib (min. 5 karakter). Tanpa alasan, pencabutan pengecualian di masa mendatang tidak memiliki histori proteksi."
                />
            </div>

            <template #footer>
                <div class="pa-dialog-actions--responsive">
                    <HcisButton label="Batal" variant="secondary" size="sm" @click="dialog = false" />
                    <HcisButton
                        label="Kecualikan Level Ini"
                        variant="warning"
                        size="sm"
                        leading-icon="slash-circle"
                        :disabled="alasan.trim().length < 5"
                        :loading="menyimpan"
                        @click="kecualikan"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Dialog 3: Atur Komposisi & Bobot Penilai -->
        <HcisDialog
            v-model="dialogKomposisi"
            :title="`Atur Komposisi Penilai — ${levelKomposisi?.nama_level ?? 'Level'}`"
            kicker="MASTER DATA · KOMPOSISI & BOBOT PENILAI"
            icon="sliders"
            sentiment="brand"
            :size="isMobile ? 'full' : 'lg'"
        >
            <div v-if="levelKomposisi" class="pa-komposisi-dialog">
                <!-- Meta Info Banner -->
                <div class="pa-komposisi-meta-banner">
                    <div class="pa-komposisi-meta-left">
                        <div class="pa-komposisi-level-badge">
                            <HcisIcon name="layers-fill" size="xs" color="brand" />
                            <strong>{{ levelKomposisi.nama_level }}</strong>
                            <span class="pa-level-id-pill">ID: {{ levelKomposisi.id_level }}</span>
                        </div>
                        <div class="pa-komposisi-meta-sub">
                            <HcisIcon name="people-fill" size="2xs" />
                            <span>{{ formatAngka(levelKomposisi.jumlah_karyawan) }} Karyawan Aktif</span>
                            <span class="pa-level-sep">&bull;</span>
                            <span v-if="levelKomposisi.policy" class="pa-level-policy-tag">
                                {{ levelKomposisi.policy.kode }} v{{ levelKomposisi.policy.versi }} ({{ levelKomposisi.policy.status }})
                            </span>
                            <span v-else class="text-xs text-neutral-500">Kebijakan Baru (Otomatis dibuat)</span>
                        </div>
                    </div>

                    <!-- Total Weight Gauge & Auto-Balance Button -->
                    <div class="pa-komposisi-gauge-box">
                        <div class="pa-komposisi-gauge-stat">
                            <span class="pa-gauge-label">Total Bobot:</span>
                            <strong
                                class="pa-gauge-value"
                                :class="{
                                    'is-valid': Math.abs(totalBobotDraf - 100) < 0.01,
                                    'is-danger': Math.abs(totalBobotDraf - 100) >= 0.01,
                                }"
                            >
                                {{ totalBobotDraf }}%
                            </strong>
                            <span
                                v-if="Math.abs(totalBobotDraf - 100) < 0.01"
                                class="pa-gauge-status is-valid"
                            >
                                <HcisIcon name="check-circle-fill" size="2xs" /> 100% Pas
                            </span>
                            <span
                                v-else-if="totalBobotDraf < 100"
                                class="pa-gauge-status is-danger"
                            >
                                Kurang {{ formatDesimal(100 - totalBobotDraf) }}%
                            </span>
                            <span
                                v-else
                                class="pa-gauge-status is-danger"
                            >
                                Lebih {{ formatDesimal(totalBobotDraf - 100) }}%
                            </span>
                        </div>

                        <HcisButton
                            label="Bagi Rata Bobot"
                            variant="secondary"
                            size="xs"
                            leading-icon="calculator"
                            title="Bagi rata 100% ke seluruh kategori penilai yang aktif"
                            @click="bagiRataBobot"
                        />
                    </div>
                </div>

                <!-- Info Alert if not 100% -->
                <HcisAlert
                    v-if="Math.abs(totalBobotDraf - 100) >= 0.01"
                    variant="warning"
                    appearance="subtle"
                    icon="exclamation-triangle-fill"
                >
                    <p class="mb-0">
                        Total bobot rater saat ini <strong>{{ totalBobotDraf }}%</strong>. Aturan penilai mewajibkan total bobot persentase dari seluruh kategori penilai yang aktif tepat <strong>100%</strong> sebelum dapat disimpan.
                    </p>
                </HcisAlert>

                <!-- Interactive Table of Categories -->
                <div class="pa-komposisi-table-wrap">
                    <table class="pa-komposisi-table">
                        <thead>
                            <tr>
                                <th class="pa-th-aktif">Aktif</th>
                                <th class="pa-th-kategori">Kategori Penilai</th>
                                <th class="pa-th-bobot">Bobot (%)</th>
                                <th class="pa-th-kuota">Min Penilai</th>
                                <th class="pa-th-kuota">Maks Penilai</th>
                                <th class="pa-th-kuota">Min Selesai</th>
                                <th class="pa-th-manual">Manual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in drafKomposisi"
                                :key="item.pa_rater_category_id"
                                :class="{ 'is-inactive': !item.aktif }"
                            >
                                <td class="pa-td-aktif">
                                    <label class="pa-checkbox-label">
                                        <input
                                            type="checkbox"
                                            :checked="item.aktif"
                                            @change="onToggleKategori(item, $event.target.checked)"
                                        />
                                    </label>
                                </td>
                                <td class="pa-td-kategori">
                                    <div class="pa-rater-meta">
                                        <div class="pa-rater-name-row">
                                            <HcisIcon :name="ikonKategori(item.kode)" size="2xs" />
                                            <strong>{{ item.nama }}</strong>
                                        </div>
                                        <small v-if="item.deskripsi" class="pa-rater-desc">{{ item.deskripsi }}</small>
                                    </div>
                                </td>
                                <td class="pa-td-bobot">
                                    <div class="pa-input-unit-wrap">
                                        <input
                                            v-model.number="item.bobot_persen"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.5"
                                            class="pa-mini-input"
                                            :disabled="!item.aktif"
                                            placeholder="0"
                                        />
                                        <span class="pa-input-unit">%</span>
                                    </div>
                                </td>
                                <td class="pa-td-kuota">
                                    <input
                                        v-model.number="item.min_rater"
                                        type="number"
                                        min="1"
                                        class="pa-mini-input"
                                        :disabled="!item.aktif"
                                        placeholder="1"
                                    />
                                </td>
                                <td class="pa-td-kuota">
                                    <input
                                        v-model.number="item.maks_rater"
                                        type="number"
                                        min="1"
                                        class="pa-mini-input"
                                        :disabled="!item.aktif"
                                        placeholder="&infin;"
                                    />
                                </td>
                                <td class="pa-td-kuota">
                                    <input
                                        v-model.number="item.min_rater_selesai"
                                        type="number"
                                        min="1"
                                        class="pa-mini-input"
                                        :disabled="!item.aktif"
                                        placeholder="Opsional"
                                    />
                                </td>
                                <td class="pa-td-manual">
                                    <label class="pa-checkbox-label" title="Izinkan penambahan penilai secara manual">
                                        <input
                                            type="checkbox"
                                            :checked="item.flag_izinkan_manual === 'Y'"
                                            :disabled="!item.aktif"
                                            @change="item.flag_izinkan_manual = $event.target.checked ? 'Y' : 'T'"
                                        />
                                        <span class="pa-checkbox-text">Ya</span>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pa-komposisi-notes">
                    <HcisIcon name="info-circle" size="2xs" class="text-neutral-500 flex-shrink-0" />
                    <span class="text-xs text-neutral-600">
                        Min Penilai default 1. Maks Penilai dapat dikosongkan untuk kuota tidak terbatas (&infin;). Pastikan jumlah bobot persentase kategori yang aktif tepat 100%.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="pa-dialog-actions--responsive">
                    <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogKomposisi = false" />
                    <HcisButton
                        label="Simpan Komposisi"
                        variant="primary"
                        size="sm"
                        leading-icon="check2"
                        :disabled="!drafValid || Math.abs(totalBobotDraf - 100) >= 0.01"
                        :loading="menyimpanKomposisi"
                        data-uji="simpan-komposisi"
                        @click="simpanKomposisiDraf"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Dialog 4: Pengecualian Level Massal -->
        <HcisDialog
            v-model="dialogBulkKecualikan"
            :title="`Kecualikan ${terpilihAktif.length} Level dari Performance Appraisal?`"
            kicker="MASTER DATA · PENGECUALIAN MASSAL"
            icon="slash-circle"
            sentiment="warning"
            :size="isMobile ? 'full' : 'md'"
        >
            <div class="pa-jp-dialog">
                <HcisAlert variant="warning" appearance="subtle" icon="exclamation-triangle-fill">
                    <p class="mb-1">
                        <strong>{{ terpilihAktif.length }} level</strong> (total <strong>{{ formatAngka(totalKaryawanTerpilihAktif) }} karyawan</strong>) akan dikecualikan:
                    </p>
                    <div class="pa-bulk-level-tags mb-2">
                        <span v-for="l in terpilihAktifObj" :key="l.id_level" class="pa-tag-pill">
                            {{ l.nama_level }} ({{ formatAngka(l.jumlah_karyawan) }})
                        </span>
                    </div>
                    <p class="mb-0 text-xs text-neutral-600">
                        Karyawan pada level-level ini tidak akan dibentuk sebagai peserta pada penyiapan siklus berikutnya. Siklus yang sedang berjalan tidak disentuh.
                    </p>
                </HcisAlert>

                <HcisField
                    v-model="alasanBulk"
                    label="Alasan pengecualian massal"
                    type="textarea"
                    :rows="3"
                    required
                    placeholder="Mis. Jenjang posisi non-struktural atau mitra pihak ketiga yang tidak dievaluasi."
                    hint="Wajib (min. 5 karakter) untuk riwayat histori proteksi seluruh level terpilih."
                />
            </div>

            <template #footer>
                <div class="pa-dialog-actions--responsive">
                    <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogBulkKecualikan = false" />
                    <HcisButton
                        :label="`Kecualikan ${terpilihAktif.length} Level Ini`"
                        variant="warning"
                        size="sm"
                        leading-icon="slash-circle"
                        :disabled="alasanBulk.trim().length < 5"
                        :loading="menyimpanBulk"
                        data-uji="konfirmasi-bulk-kecualikan"
                        @click="jalankanBulkKecualikan"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Dialog 5: Aktivasi / Ikutkan Kembali Level Massal -->
        <HcisDialog
            v-model="dialogBulkCabut"
            :title="`Ikutkan Kembali ${terpilihDikecualikan.length} Level ke Performance Appraisal?`"
            kicker="MASTER DATA · AKTIVASI MASSAL"
            icon="arrow-counterclockwise"
            sentiment="brand"
            :size="isMobile ? 'full' : 'md'"
        >
            <div class="pa-jp-dialog">
                <HcisAlert variant="info" appearance="subtle" icon="info-circle-fill">
                    <p class="mb-1">
                        <strong>{{ terpilihDikecualikan.length }} level</strong> (total <strong>{{ formatAngka(totalKaryawanTerpilihDikecualikan) }} karyawan</strong>) akan diikutsertakan kembali:
                    </p>
                    <div class="pa-bulk-level-tags mb-2">
                        <span v-for="l in terpilihDikecualikanObj" :key="l.id_level" class="pa-tag-pill">
                            {{ l.nama_level }} ({{ formatAngka(l.jumlah_karyawan) }})
                        </span>
                    </div>
                    <p class="mb-0 text-xs text-neutral-600">
                        Pastikan seluruh level yang diaktifkan kembali sudah memiliki komposisi penilai yang genap 100% sebelum penyiapan siklus dimulai.
                    </p>
                </HcisAlert>
            </div>

            <template #footer>
                <div class="pa-dialog-actions--responsive">
                    <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogBulkCabut = false" />
                    <HcisButton
                        :label="`Ikutkan ${terpilihDikecualikan.length} Level Lagi`"
                        variant="primary"
                        size="sm"
                        leading-icon="arrow-counterclockwise"
                        :loading="menyimpanBulk"
                        data-uji="konfirmasi-bulk-cabut"
                        @click="jalankanBulkCabut"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Toast Feedback Notification -->
        <transition name="pa-jp-toast">
            <div
                v-if="pesan"
                class="pa-jp-toast"
                :class="`pa-jp-toast--${pesanError ? 'danger' : 'success'}`"
                role="status"
                aria-live="polite"
            >
                <HcisIcon :name="pesanError ? 'exclamation-triangle-fill' : 'check-circle-fill'" size="sm" />
                <span>{{ pesan }}</span>
                <button type="button" class="pa-jp-toast__close" aria-label="Tutup" @click="pesan = ''">&times;</button>
            </div>
        </transition>
    </main>
</template>

<script setup>
/**
 * Matriks LEVEL JABATAN × JENIS PENILAI.
 *
 * Mengatur pemetaan kategori penilai yang berlaku per level jabatan, menampilkan
 * status bobot real-time, dan memfasilitasi pengecualian level non-PA.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    HcisAlert,
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
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
} from '@/components/ui';
import paApi from '../api/paApi.js';
import { formatAngka } from '../utils/formatAngka.js';
import PaAturanMasaKerja from '../components/PaAturanMasaKerja.vue';
import PaPengaturanKategoriPenilai from './components/PaPengaturanKategoriPenilai.vue';

defineOptions({ name: 'PerformanceAppraisalJenisPenilaiPage' });

const activeMaster = 'kategori-penilai';
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
        viewMode.value = isMobile.value ? 'grid' : 'table';
        viewModeInitialized.value = true;
    }
}

const kategori = ref([]);
const level = ref([]);
const perluPerhatian = ref([]);
const memuat = ref(false);
const error = ref('');

const filterTab = ref('semua');
const kataKunciLevel = ref('');
const bukaPengaturanKategori = ref(false);

const urutanKategoriRingkas = computed(() =>
    [...kategori.value].sort(
        (a, b) =>
            (a.urutan_pengisian ?? Number.MAX_SAFE_INTEGER) - (b.urutan_pengisian ?? Number.MAX_SAFE_INTEGER) ||
            a.id - b.id,
    ),
);

const sibukSel = ref(null);
const konfirmasi = ref(null);
const dialog = ref(false);
const calon = ref(null);
const alasan = ref('');
const menyimpan = ref(false);
const sibukLevel = ref(null);

const dialogKomposisi = ref(false);
const levelKomposisi = ref(null);
const drafKomposisi = ref([]);
const menyimpanKomposisi = ref(false);

const terpilih = ref([]);
const dialogBulkKecualikan = ref(false);
const dialogBulkCabut = ref(false);
const alasanBulk = ref('');
const menyimpanBulk = ref(false);

const pesan = ref('');
const pesanError = ref(false);

const masaKerja = ref(null);
const menyimpanMasaKerja = ref(false);

async function muatMasaKerja() {
    try {
        const res = await paApi.kelayakanMasaKerjaMaster();
        masaKerja.value = res?.result ?? res?.data ?? res;
    } catch {
        // Bukan bagian inti layar ini; matriksnya tetap tampil walau pengaturan ini gagal dimuat.
        masaKerja.value = null;
    }
}

async function simpanMasaKerja(bulan) {
    menyimpanMasaKerja.value = true;
    try {
        const res = await paApi.simpanKelayakanMasaKerjaMaster(bulan);
        masaKerja.value = res?.result ?? res?.data ?? res;
        pesan.value = `Minimal masa kerja master disimpan: ${bulan} bulan.`;
        pesanError.value = false;
    } catch (e) {
        pesan.value = e?.message || 'Minimal masa kerja gagal disimpan.';
        pesanError.value = true;
    } finally {
        menyimpanMasaKerja.value = false;
    }
}

let jedaPesan = null;

/* Stat Summary Computeds */
const levelAktifCount = computed(() => level.value.filter((l) => !l.dikecualikan_pa).length);
const levelDikecualikanCount = computed(() => level.value.filter((l) => l.dikecualikan_pa).length);
const totalKaryawanAktif = computed(() =>
    level.value
        .filter((l) => !l.dikecualikan_pa)
        .reduce((sum, l) => sum + (Number(l.jumlah_karyawan) || 0), 0)
);

const levelLengkapCount = computed(() =>
    level.value.filter(
        (l) => !l.dikecualikan_pa && Math.abs(l.total_bobot - 100) < 0.01 && l.jumlah_belum_berbobot === 0
    ).length
);

const levelBelumLengkapCount = computed(() =>
    level.value.filter(
        (l) => !l.dikecualikan_pa && (Math.abs(l.total_bobot - 100) > 0.01 || l.jumlah_belum_berbobot > 0 || l.jumlah_kategori === 0)
    ).length
);

const levelPerluPerhatianCount = computed(() => {
    if (perluPerhatian.value && perluPerhatian.value.length > 0) {
        return perluPerhatian.value.length;
    }
    return levelBelumLengkapCount.value;
});

const statusQuickChips = computed(() => {
    const chips = [
        {
            id: 'semua',
            value: 'semua',
            label: isMobile.value ? 'Semua' : 'Semua Level',
            badge: level.value.length,
            icon: 'layers-fill',
        },
    ];
    if (perluPerhatian.value.length > 0 || levelBelumLengkapCount.value > 0) {
        chips.push({
            id: 'perhatian',
            value: 'perhatian',
            label: isMobile.value ? 'Perlu Perhatian' : 'Perlu Perhatian',
            badge: levelPerluPerhatianCount.value,
            color: 'warning',
            icon: 'exclamation-triangle-fill',
        });
    }
    chips.push({
        id: 'lengkap',
        value: 'lengkap',
        label: isMobile.value ? '100% Pas' : 'Lengkap (100%)',
        badge: levelLengkapCount.value,
        color: 'success',
        icon: 'check-circle-fill',
    });
    if (levelDikecualikanCount.value > 0) {
        chips.push({
            id: 'dikecualikan',
            value: 'dikecualikan',
            label: isMobile.value ? 'Non-PA' : 'Dikecualikan',
            badge: levelDikecualikanCount.value,
            icon: 'slash-circle',
        });
    }
    return chips;
});

function ubahChipFilter(chip) {
    filterTab.value = chip?.value ?? chip ?? 'semua';
}

/* Level Filtering & Search */
const levelTersaring = computed(() => {
    const q = kataKunciLevel.value.trim().toLowerCase();
    const tab = filterTab.value;

    return level.value.filter((l) => {
        // Tab Filtering
        if (tab === 'perhatian') {
            const isPerlu = !l.dikecualikan_pa && (Math.abs(l.total_bobot - 100) > 0.01 || l.jumlah_belum_berbobot > 0 || l.jumlah_kategori === 0);
            if (!isPerlu) return false;
        } else if (tab === 'lengkap') {
            const isLengkap = !l.dikecualikan_pa && Math.abs(l.total_bobot - 100) < 0.01 && l.jumlah_belum_berbobot === 0;
            if (!isLengkap) return false;
        } else if (tab === 'dikecualikan') {
            if (!l.dikecualikan_pa) return false;
        }

        // Search Query Filtering
        if (!q) return true;

        const nama = String(l.nama_level || '').toLowerCase();
        const id = String(l.id_level || '').toLowerCase();
        const policyKode = String(l.policy?.kode || '').toLowerCase();

        return nama.includes(q) || id.includes(q) || policyKode.includes(q);
    });
});

function resetFilter() {
    filterTab.value = 'semua';
    kataKunciLevel.value = '';
}

function ikonKategori(kode) {
    const peta = {
        ATASAN: 'person-badge',
        BAWAHAN: 'diagram-3-fill',
        PEER: 'people-fill',
        REKAN: 'people-fill',
        SELF: 'person-circle',
        DIRI_SENDIRI: 'person-circle',
    };

    return peta[String(kode).toUpperCase()] || 'person-fill';
}

function warnaKategori(kode) {
    const k = String(kode).toUpperCase();
    if (k === 'ATASAN') return '#2563eb';
    if (k === 'BAWAHAN') return '#059669';
    if (k === 'PEER' || k === 'REKAN') return '#d97706';
    if (k === 'SELF' || k === 'DIRI_SENDIRI') return '#7c3aed';
    return '#4f46e5';
}

function segmenKomposisi(l) {
    if (!l || l.dikecualikan_pa) return [];
    const list = [];
    const total = Number(l.total_bobot) || 0;
    const factor = total > 100 ? 100 / total : 1;

    kategori.value.forEach((k) => {
        const sel = l.sel?.[k.kode];
        if (sel && sel.berbobot && Number(sel.bobot) > 0) {
            const b = Number(sel.bobot);
            list.push({
                kode: k.kode,
                nama: k.nama,
                bobot: b,
                lebar: Math.max(2, b * factor),
                warna: warnaKategori(k.kode),
            });
        }
    });

    return list;
}

function kategoriAktifLevel(l) {
    if (!l || !l.sel) return [];
    return kategori.value.filter((k) => Boolean(l.sel[k.kode]));
}

function lapor(teks, gagal = false) {
    pesanError.value = gagal;
    pesan.value = teks;

    if (jedaPesan) clearTimeout(jedaPesan);
    jedaPesan = setTimeout(() => {
        pesan.value = '';
    }, 6000);
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const response = await paApi.matriksJenisPenilai();
        const d = response?.result ?? response?.data ?? response;

        kategori.value = d?.kategori ?? [];
        level.value = d?.level ?? [];
        perluPerhatian.value = d?.perlu_perhatian ?? [];
    } catch (e) {
        error.value = e?.message || 'Gagal memuat matriks jenis penilai.';
    } finally {
        memuat.value = false;
    }
}

function judulSel(l, k) {
    const sel = l.sel[k.kode];

    if (!sel) return `${k.nama} tidak menilai ${l.nama_level}. Klik untuk memberlakukannya.`;
    if (!sel.berbobot)
        return `${k.nama} menilai ${l.nama_level}, bobotnya belum ditetapkan di Komposisi Penilai. Klik untuk mencabutnya.`;

    return `${k.nama} menilai ${l.nama_level} dengan bobot ${sel.bobot}%. Klik untuk mencabutnya.`;
}

function bukaKonfirmasiSel(l, k) {
    const sel = l.sel[k.kode];

    konfirmasi.value = {
        jenis: sel ? 'sel-cabut' : 'sel-pasang',
        level: l,
        kategori: k,
        judul: sel
            ? `Cabut ${k.nama} dari ${l.nama_level}?`
            : `Berlakukan ${k.nama} untuk ${l.nama_level}?`,
        nada: sel ? 'warning' : 'brand',
        ikon: sel ? 'slash-circle' : 'check2-square',
        labelAksi: sel ? 'Cabut Sel Ini' : 'Berlakukan',
    };
}

function bukaKonfirmasiCabutPengecualian(l) {
    konfirmasi.value = {
        jenis: 'level-ikutkan',
        level: l,
        kategori: null,
        judul: `Ikutkan ${l.nama_level} kembali pada Performance Appraisal?`,
        nada: 'brand',
        ikon: 'arrow-counterclockwise',
        labelAksi: 'Ikutkan Lagi',
    };
}

async function jalankanKonfirmasi() {
    const k = konfirmasi.value;

    if (!k) return;

    menyimpan.value = true;

    try {
        if (k.jenis === 'level-ikutkan') {
            sibukLevel.value = k.level.id_level;
            await paApi.cabutPengecualianLevelPa(k.level.id_level);
            lapor(`${k.level.nama_level} kembali diikutkan pada Performance Appraisal.`);
        } else {
            const berlaku = k.jenis === 'sel-pasang';
            sibukSel.value = `${k.level.id_level}-${k.kategori.id}`;
            await paApi.setelSelJenisPenilai(k.level.id_level, k.kategori.id, berlaku);

            lapor(
                berlaku
                    ? `${k.kategori.nama} kini menilai ${k.level.nama_level}. Bobotnya ditetapkan di Komposisi Penilai.`
                    : `${k.kategori.nama} tidak lagi menilai ${k.level.nama_level}.`,
            );
        }

        konfirmasi.value = null;
        await muat();
    } catch (e) {
        lapor(e?.message || 'Perubahan gagal disimpan.', true);
    } finally {
        menyimpan.value = false;
        sibukSel.value = null;
        sibukLevel.value = null;
    }
}

function bukaDialog(l) {
    calon.value = l;
    alasan.value = '';
    dialog.value = true;
}

async function kecualikan() {
    if (!calon.value) return;

    menyimpan.value = true;

    try {
        const response = await paApi.kecualikanLevelPa(calon.value.id_level, alasan.value.trim());
        const d = response?.result ?? response?.data ?? response;

        const sisa = Number(d?.peserta_siklus_berjalan ?? 0);

        lapor(
            `${calon.value.nama_level} dikecualikan dari Performance Appraisal.`
            + (sisa > 0 ? ` ${formatAngka(sisa)} peserta di siklus berjalan tetap utuh.` : ''),
        );

        dialog.value = false;
        await muat();
    } catch (e) {
        lapor(e?.message || 'Gagal mengecualikan level.', true);
    } finally {
        menyimpan.value = false;
    }
}

function formatDesimal(val) {
    const num = Number(val);
    if (isNaN(num)) return '0';
    return Number.isInteger(num) ? String(num) : num.toFixed(1);
}

const totalBobotDraf = computed(() => {
    const aktifItems = (drafKomposisi.value || []).filter((i) => i.aktif);
    const sum = aktifItems.reduce((acc, i) => acc + (Number(i.bobot_persen) || 0), 0);
    return Math.round(sum * 100) / 100;
});

const drafValid = computed(() => {
    const aktifItems = (drafKomposisi.value || []).filter((i) => i.aktif);
    if (aktifItems.length === 0) return false;
    if (Math.abs(totalBobotDraf.value - 100) > 0.01) return false;

    for (const item of aktifItems) {
        const b = Number(item.bobot_persen);
        if (isNaN(b) || b <= 0) return false;
        const min = Number(item.min_rater);
        if (isNaN(min) || min < 1) return false;
        if (item.maks_rater !== '' && item.maks_rater !== null && item.maks_rater !== undefined) {
            const maks = Number(item.maks_rater);
            if (isNaN(maks) || maks < min) return false;
            if (item.min_rater_selesai !== '' && item.min_rater_selesai !== null && item.min_rater_selesai !== undefined) {
                const minSelesai = Number(item.min_rater_selesai);
                if (isNaN(minSelesai) || minSelesai > maks) return false;
            }
        }
    }
    return true;
});

function bukaDialogKomposisi(l) {
    levelKomposisi.value = l;
    drafKomposisi.value = (kategori.value || []).map((k) => {
        const sel = l.sel ? l.sel[k.kode] : null;
        const aktif = Boolean(sel);
        return {
            pa_rater_category_id: k.id,
            kode: k.kode,
            nama: k.nama,
            deskripsi: k.deskripsi,
            aktif,
            bobot_persen: sel && sel.bobot !== null && sel.bobot !== undefined ? Number(sel.bobot) : 0,
            min_rater: sel && sel.min !== null && sel.min !== undefined ? Number(sel.min) : 1,
            maks_rater: sel && sel.maks !== null && sel.maks !== undefined ? Number(sel.maks) : '',
            min_rater_selesai: sel && sel.min_selesai !== null && sel.min_selesai !== undefined ? Number(sel.min_selesai) : '',
            flag_izinkan_manual: sel && sel.izinkan_manual ? 'Y' : 'T',
        };
    });
    dialogKomposisi.value = true;
}

function onToggleKategori(item, checked) {
    item.aktif = checked;
    if (!checked) {
        item.bobot_persen = 0;
    } else {
        if (!item.min_rater || item.min_rater < 1) {
            item.min_rater = 1;
        }
    }
}

function bagiRataBobot() {
    const aktifItems = (drafKomposisi.value || []).filter((i) => i.aktif);
    if (aktifItems.length === 0) return;
    const n = aktifItems.length;
    const porsi = Math.floor(100 / n);
    const sisa = 100 - porsi * n;

    aktifItems.forEach((item, idx) => {
        item.bobot_persen = porsi + (idx === 0 ? sisa : 0);
    });
}

async function simpanKomposisiDraf() {
    if (!levelKomposisi.value || !drafValid.value) return;

    const aktifItems = (drafKomposisi.value || []).filter((i) => i.aktif);
    const rincian = aktifItems.map((item, idx) => ({
        pa_rater_category_id: item.pa_rater_category_id,
        bobot_persen: Number(item.bobot_persen),
        min_rater: item.min_rater ? Number(item.min_rater) : 1,
        maks_rater: item.maks_rater !== '' && item.maks_rater !== null && item.maks_rater !== undefined ? Number(item.maks_rater) : null,
        min_rater_selesai: item.min_rater_selesai !== '' && item.min_rater_selesai !== null && item.min_rater_selesai !== undefined ? Number(item.min_rater_selesai) : null,
        flag_izinkan_manual: item.flag_izinkan_manual === 'Y' ? 'Y' : 'T',
        urutan_tampil: idx + 1,
    }));

    menyimpanKomposisi.value = true;
    try {
        await paApi.simpanKomposisiLevel(levelKomposisi.value.id_level, rincian);
        lapor(`Komposisi penilai untuk ${levelKomposisi.value.nama_level} berhasil disimpan (Total 100%).`);
        dialogKomposisi.value = false;
        await muat();
    } catch (e) {
        lapor(e?.message || 'Gagal menyimpan komposisi penilai.', true);
    } finally {
        menyimpanKomposisi.value = false;
    }
}

const isSemuaTerpilih = computed(() =>
    levelTersaring.value.length > 0 && levelTersaring.value.every((l) => terpilih.value.includes(l.id_level))
);

const isSebagianTerpilih = computed(() => {
    const count = levelTersaring.value.filter((l) => terpilih.value.includes(l.id_level)).length;
    return count > 0 && count < levelTersaring.value.length;
});

const terpilihAktifObj = computed(() =>
    level.value.filter((l) => terpilih.value.includes(l.id_level) && !l.dikecualikan_pa)
);

const terpilihDikecualikanObj = computed(() =>
    level.value.filter((l) => terpilih.value.includes(l.id_level) && l.dikecualikan_pa)
);

const terpilihAktif = computed(() => terpilihAktifObj.value.map((l) => l.id_level));
const terpilihDikecualikan = computed(() => terpilihDikecualikanObj.value.map((l) => l.id_level));

const totalKaryawanTerpilih = computed(() =>
    level.value
        .filter((l) => terpilih.value.includes(l.id_level))
        .reduce((sum, l) => sum + (Number(l.jumlah_karyawan) || 0), 0)
);

const totalKaryawanTerpilihAktif = computed(() =>
    terpilihAktifObj.value.reduce((sum, l) => sum + (Number(l.jumlah_karyawan) || 0), 0)
);

const totalKaryawanTerpilihDikecualikan = computed(() =>
    terpilihDikecualikanObj.value.reduce((sum, l) => sum + (Number(l.jumlah_karyawan) || 0), 0)
);

function togglePilihSemua(checked) {
    if (checked) {
        const ids = levelTersaring.value.map((l) => l.id_level);
        terpilih.value = Array.from(new Set([...terpilih.value, ...ids]));
    } else {
        const ids = new Set(levelTersaring.value.map((l) => l.id_level));
        terpilih.value = terpilih.value.filter((id) => !ids.has(id));
    }
}

function togglePilihLevel(id) {
    if (terpilih.value.includes(id)) {
        terpilih.value = terpilih.value.filter((x) => x !== id);
    } else {
        terpilih.value.push(id);
    }
}

function bukaDialogBulkKecualikan() {
    if (terpilihAktif.value.length === 0) return;
    alasanBulk.value = '';
    dialogBulkKecualikan.value = true;
}

async function jalankanBulkKecualikan() {
    if (terpilihAktif.value.length === 0) return;
    menyimpanBulk.value = true;
    try {
        const res = await paApi.kecualikanBanyakLevelPa(terpilihAktif.value, alasanBulk.value.trim());
        const d = res?.result ?? res?.data ?? res;
        const sisa = Number(d?.total_peserta_berjalan ?? 0);
        lapor(
            `${d?.jumlah_level ?? terpilihAktif.value.length} level berhasil dikecualikan dari Performance Appraisal.`
            + (sisa > 0 ? ` ${formatAngka(sisa)} peserta di siklus berjalan tetap utuh.` : ''),
        );
        dialogBulkKecualikan.value = false;
        terpilih.value = [];
        await muat();
    } catch (e) {
        lapor(e?.message || 'Gagal mengecualikan level massal.', true);
    } finally {
        menyimpanBulk.value = false;
    }
}

function bukaKonfirmasiBulkCabut() {
    if (terpilihDikecualikan.value.length === 0) return;
    dialogBulkCabut.value = true;
}

async function jalankanBulkCabut() {
    if (terpilihDikecualikan.value.length === 0) return;
    menyimpanBulk.value = true;
    try {
        const res = await paApi.cabutBanyakPengecualianLevelPa(terpilihDikecualikan.value);
        const d = res?.result ?? res?.data ?? res;
        lapor(`${d?.jumlah_level ?? terpilihDikecualikan.value.length} level berhasil diikutsertakan kembali pada Performance Appraisal.`);
        dialogBulkCabut.value = false;
        terpilih.value = [];
        await muat();
    } catch (e) {
        lapor(e?.message || 'Gagal mencabut pengecualian level massal.', true);
    } finally {
        menyimpanBulk.value = false;
    }
}

onMounted(() => {
    cekUkuranLayar();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', cekUkuranLayar, { passive: true });
    }
    muat();
    muatMasaKerja();
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
    if (jedaPesan) clearTimeout(jedaPesan);
});
</script>

<style scoped>
.pa-jp-page {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

/* Header Actions */
.pa-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Master Switcher in Header Console */
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
    -webkit-overflow-scrolling: touch;
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
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.76rem;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-admin-switcher__item:hover {
    color: var(--hcis-brand, #2563eb);
    background: rgba(255, 255, 255, 0.85);
}

.pa-admin-switcher__item--active {
    color: var(--hcis-brand, #2563eb);
    background: #ffffff;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(37, 99, 235, 0.05);
}

/* Cockpit Stat Cards */
.pa-cockpit-group {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}

.pa-cockpit-group :deep(.hcis-stat),
.pa-cockpit-group .hcis-stat {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    height: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
}

.pa-cockpit-group :deep(.hcis-stat__compact-wrap) {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    min-width: 0;
}

/* Skeleton Loading Layout */
.pa-jp-skeleton {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-skel-cockpit {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100% !important;
}

.pa-skel-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-skel-matrix {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Alert List */
.pa-jp-alert-list {
    margin: 0.5rem 0 0 0;
    padding-left: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-jp-alert-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0.75rem;
    background: #ffffff;
    border: 1px solid #fecaca;
    border-radius: var(--hcis-radius-md, 8px);
    box-shadow: 0 1px 2px rgba(239, 68, 68, 0.04);
}

.pa-jp-alert-content {
    flex: 1;
    min-width: 0;
    font-size: 0.8125rem;
    color: #7f1d1d;
}

.pa-jp-alert-content strong {
    color: #991b1b;
}

.pa-jp-alert-count {
    margin-left: 0.35rem;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
}

/* ════ COLLAPSIBLE CONFIGURATION PANEL ════ */
.pa-config-collapsible {
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    border-radius: var(--hcis-radius-xl, 14px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    transition: all 0.2s ease;
}

.pa-config-collapsible:hover {
    border-color: rgba(37, 99, 235, 0.2);
}

.pa-config-card-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.875rem 1.15rem;
    cursor: pointer;
    background: var(--hcis-surface, #ffffff);
    transition: background-color 0.15s ease;
    user-select: none;
}

.pa-config-card-toggle:hover {
    background: var(--hcis-surface-soft, #f8fafc);
}

.pa-config-card-toggle.is-open {
    border-bottom: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    background: var(--hcis-surface-soft, #f8fafc);
}

.pa-config-toggle__left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
}

.pa-config-toggle__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: var(--hcis-surface-brand-subtle, rgba(37, 99, 235, 0.08));
    color: var(--hcis-brand, #2563eb);
    flex-shrink: 0;
}

.pa-config-toggle__text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-config-toggle__title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-config-toggle__title {
    font-size: 0.925rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-config-toggle__preview {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-config-preview-label {
    font-weight: 500;
}

.pa-config-preview-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-config-mini-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
    color: var(--hcis-text-primary, #334155);
}

.pa-config-mini-chip__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    font-size: 0.65rem;
    font-weight: 700;
}

.pa-config-arrow {
    color: #94a3b8;
    margin-left: 0.1rem;
}

.pa-config-toggle__right {
    flex-shrink: 0;
}

.pa-config-collapse-content {
    padding: 1rem 1.15rem;
    background: #ffffff;
}

/* Seamless embedded PaPengaturanKategoriPenilai */
.pa-config-collapse-content :deep(.pa-atur-kategori) {
    border: none;
    padding: 0.5rem 0 0.25rem;
    box-shadow: none;
    background: transparent;
}

.pa-collapse-enter-active,
.pa-collapse-leave-active {
    transition: all 0.2s ease;
}

.pa-collapse-enter-from,
.pa-collapse-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Floating Bulk Action Bar */
.pa-bulk-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1.15rem;
    background: #ffffff;
    border: 1px solid #c7d2fe;
    border-left: 4px solid var(--hcis-brand, #2563eb);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.1);
    flex-wrap: wrap;
}

.pa-bulk-bar__info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-bulk-bar__count-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-brand, #2563eb);
    font-size: 0.85rem;
    font-weight: 750;
}

.pa-bulk-bar__meta {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 500;
}

.pa-bulk-bar__actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-left: auto;
}

.pa-bulk-bar-enter-active,
.pa-bulk-bar-leave-active {
    transition: all 180ms ease;
}

.pa-bulk-bar-enter-from,
.pa-bulk-bar-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* Master Data Matrix Card Wrap */
.pa-matrix-card {
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
}

.pa-matrix-scroll-wrap {
    overflow-x: auto;
    width: 100%;
    -webkit-overflow-scrolling: touch;
}

.pa-matrix-scroll-wrap::-webkit-scrollbar {
    height: 6px;
}
.pa-matrix-scroll-wrap::-webkit-scrollbar-track {
    background: #f8fafc;
}
.pa-matrix-scroll-wrap::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.pa-matrix-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
}

.pa-matrix-table thead th {
    padding: 0.85rem 0.95rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border-bottom: 2px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    position: sticky;
    top: 0;
    z-index: 2;
    white-space: nowrap;
}

.pa-th-content {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-th-content--center {
    justify-content: center;
}

.pa-th-content--right {
    justify-content: flex-end;
}

/* Columns */
.pa-col-check {
    width: 2.8rem;
    min-width: 2.8rem;
    max-width: 2.8rem;
    text-align: center;
    padding: 0.5rem 0.4rem !important;
}

.pa-td-check-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-check-input {
    width: 1rem;
    height: 1rem;
    border-radius: 4px;
    border: 1.5px solid #94a3b8;
    accent-color: var(--hcis-brand, #2563eb);
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-check-input:hover {
    border-color: var(--hcis-brand, #2563eb);
}

.pa-check-input:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.pa-check-input:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.pa-col-level {
    min-width: 17rem;
    width: 28%;
    text-align: left;
    position: sticky;
    left: 0;
    background: #ffffff;
    z-index: 1;
    border-right: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.06));
    box-shadow: 2px 0 6px rgba(0, 0, 0, 0.02);
}

.pa-matrix-table thead th.pa-col-level {
    background: var(--hcis-surface-soft, #f8fafc);
    z-index: 3;
    border-right: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
}

.pa-col-rater {
    min-width: 9.5rem;
    text-align: center;
    padding: 0.6rem 0.75rem;
}

.pa-col-total {
    min-width: 8.5rem;
    width: 11%;
    text-align: center;
    padding: 0.6rem 0.75rem;
}

.pa-col-aksi {
    min-width: 11.5rem;
    width: 14%;
    text-align: right;
    padding: 0.6rem 0.95rem;
}

.pa-matrix-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.05));
    vertical-align: middle;
}

/* Row Hover & State Styling */
.pa-matrix-row {
    transition: background-color 100ms ease;
}

.pa-matrix-row:hover td {
    background-color: #f8faff;
}

.pa-matrix-row:hover .pa-col-level {
    background-color: #f8faff;
}

.pa-matrix-row.is-selected td {
    background-color: #f5f7ff !important;
}

.pa-matrix-row.is-selected .pa-col-level {
    background-color: #f5f7ff !important;
}

/* Deficit Row */
.pa-matrix-row.is-deficit td {
    background-color: #fffbfa;
}

.pa-matrix-row.is-deficit .pa-col-level {
    background-color: #fffbfa;
    border-left: 3.5px solid #ef4444;
}

.pa-matrix-row.is-deficit:hover td,
.pa-matrix-row.is-deficit:hover .pa-col-level {
    background-color: #fff1f2;
}

/* Excluded Row */
.pa-matrix-row.is-excluded {
    opacity: 0.65;
}

.pa-matrix-row.is-excluded td,
.pa-matrix-row.is-excluded .pa-col-level {
    background-color: #f8fafc;
}

/* Level Meta Typography */
.pa-level-meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-level-title-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-level-name {
    font-size: 0.86rem;
    color: #0f172a;
    font-weight: 750;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-level-id-pill {
    font-size: 0.65rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-weight: 750;
    color: var(--hcis-brand, #2563eb);
    background: rgba(37, 99, 235, 0.08);
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    border: 1px solid rgba(37, 99, 235, 0.15);
}

.pa-tag-excluded {
    font-size: 0.63rem;
    font-weight: 750;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
}

.pa-level-reqs-row {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.725rem;
    color: #64748b;
    font-weight: 550;
    white-space: nowrap;
}

.pa-level-sep { opacity: 0.4; }

.pa-level-policy-tag {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-weight: 650;
    color: #475569;
}

.pa-level-alasan {
    display: flex;
    align-items: center;
    font-size: 0.7rem;
    font-style: italic;
    color: #94a3b8;
    line-height: 1.25;
}

/* Interactive Cell Button */
.pa-cell-btn {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    width: 100%;
    min-height: 3rem;
    padding: 0.35rem 0.5rem;
    border: 1.5px dashed #cbd5e1;
    border-radius: var(--hcis-radius-lg, 10px);
    background: #ffffff;
    cursor: pointer;
    line-height: 1.2;
    transition: all 140ms cubic-bezier(0.16, 1, 0.3, 1);
    box-sizing: border-box;
}

.pa-cell-btn:hover:not(:disabled) {
    border-color: var(--hcis-brand, #2563eb);
    background: #f5f7ff;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(37, 99, 235, 0.08);
}

.pa-cell-btn:hover:not(:disabled) .pa-cell-hover-hint {
    opacity: 1;
}

.pa-cell-btn:disabled {
    cursor: progress;
    opacity: 0.6;
}

.pa-cell-hover-hint {
    position: absolute;
    bottom: 2px;
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--hcis-brand, #2563eb);
    opacity: 0;
    transition: opacity 120ms ease;
}

.pa-cell-btn.is-on {
    border-style: solid;
    border-color: rgba(37, 99, 235, 0.25);
    background: linear-gradient(180deg, #ffffff 0%, rgba(37, 99, 235, 0.04) 100%);
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.06);
}

.pa-cell-btn.is-on:hover:not(:disabled) {
    border-color: var(--hcis-brand, #2563eb);
    background: rgba(37, 99, 235, 0.08);
}

.pa-cell-btn.is-pending {
    border-style: dashed;
    border-color: #fde68a;
    background: #fffdf5;
}

.pa-cell-pending-wrap {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pa-cell-bobot {
    font-size: 0.9rem;
    font-weight: 800;
    color: #1e1b4b;
}

.pa-cell-kuota {
    font-size: 0.68rem;
    color: #64748b;
    font-weight: 550;
}

.pa-cell-menunggu {
    font-size: 0.66rem;
    color: #b45309;
    font-weight: 700;
    white-space: nowrap;
}

.pa-cell-na__dash {
    color: #cbd5e1;
    font-size: 1.1rem;
    line-height: 1;
}

/* Total Column Badge Pill */
.pa-total-pill {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.3rem 0.65rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: #f1f5f9;
    color: #475569;
    text-decoration: none;
    line-height: 1.25;
    min-width: 5.5rem;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-total-pill-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.05rem;
}

.pa-total-head {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pa-total-num {
    font-size: 0.85rem;
    font-weight: 800;
}

.pa-total-badge-sub {
    font-size: 0.64rem;
    font-weight: 700;
    white-space: nowrap;
}

.pa-total-pill.is-complete {
    background: rgba(16, 185, 129, 0.1);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.pa-total-pill.is-complete:hover {
    background: rgba(16, 185, 129, 0.18);
    transform: translateY(-1px);
}

.pa-total-pill.is-off {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.pa-total-pill.is-off:hover {
    background: #fee2e2;
    border-color: #f87171;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15);
}

.pa-total-badge-sub.is-kurang {
    color: #b91c1c;
}

.pa-total-badge-sub.is-lebih {
    color: #c2410c;
}

.pa-total-badge-sub.is-pending {
    color: #b45309;
}

/* Action Wrap */
.pa-td-aksi-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    flex-wrap: nowrap;
}

/* Context Footnote Card */
.pa-jp-catatan-card {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.45;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

/* Dialog Stack */
.pa-jp-dialog {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* Dialog Komposisi Penilai */
.pa-komposisi-dialog {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-komposisi-meta-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.875rem 1.15rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    border-radius: var(--hcis-radius-lg, 12px);
    flex-wrap: wrap;
}

.pa-komposisi-meta-left {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-komposisi-level-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-komposisi-meta-sub {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: #64748b;
}

.pa-komposisi-gauge-box {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    background: #ffffff;
    padding: 0.5rem 0.85rem;
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    border-radius: var(--hcis-radius-lg, 10px);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.pa-komposisi-gauge-stat {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
}

.pa-gauge-label {
    color: #64748b;
    font-weight: 500;
}

.pa-gauge-value {
    font-size: 0.95rem;
    font-weight: 800;
}

.pa-gauge-value.is-valid {
    color: #059669;
}

.pa-gauge-value.is-danger {
    color: #dc2626;
}

.pa-gauge-status {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-gauge-status.is-valid {
    background: #d1fae5;
    color: #065f46;
}

.pa-gauge-status.is-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Table in Komposisi Dialog */
.pa-komposisi-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

.pa-komposisi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.pa-komposisi-table th {
    background: var(--hcis-surface-soft, #f8fafc);
    color: #475569;
    font-weight: 700;
    padding: 0.65rem 0.85rem;
    text-align: left;
    border-bottom: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    white-space: nowrap;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.pa-komposisi-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.05));
    vertical-align: middle;
}

.pa-komposisi-table tr:last-child td {
    border-bottom: 0;
}

.pa-komposisi-table tr.is-inactive {
    background: #fafafa;
    opacity: 0.6;
}

.pa-th-aktif, .pa-td-aktif {
    width: 3.2rem;
    text-align: center;
}

.pa-th-kategori, .pa-td-kategori {
    min-width: 11rem;
}

.pa-th-bobot, .pa-td-bobot {
    width: 7.5rem;
}

.pa-th-kuota, .pa-td-kuota {
    width: 6.5rem;
}

.pa-th-manual, .pa-td-manual {
    width: 5rem;
    text-align: center;
}

.pa-rater-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-rater-name-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    color: #1e293b;
}

.pa-rater-desc {
    color: #64748b;
    font-size: 0.7rem;
    line-height: 1.25;
}

.pa-input-unit-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.pa-mini-input {
    width: 100%;
    padding: 0.4rem 0.6rem;
    border: 1px solid #cbd5e1;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8125rem;
    background: #ffffff;
    color: #0f172a;
    transition: all 120ms ease;
    box-sizing: border-box;
}

.pa-mini-input:focus {
    outline: none;
    border-color: var(--hcis-brand, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.pa-mini-input:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
    border-color: #e2e8f0;
}

.pa-td-bobot .pa-mini-input {
    padding-right: 1.5rem;
    font-weight: 700;
}

.pa-input-unit {
    position: absolute;
    right: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    pointer-events: none;
}

.pa-bulk-level-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    max-height: 9rem;
    overflow-y: auto;
    padding: 0.5rem;
    background: #f8fafc;
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    border-radius: var(--hcis-radius-md, 8px);
}

.pa-tag-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 650;
    color: #334155;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
}

.pa-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    font-size: 0.75rem;
}

.pa-checkbox-text {
    font-weight: 600;
    color: #475569;
}

.pa-komposisi-notes {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* Floating Toast Notification */
.pa-jp-toast {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    z-index: var(--hcis-z-toast, 99999);
    display: flex;
    align-items: center;
    gap: 0.55rem;
    max-width: min(28rem, calc(100vw - 2.5rem));
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    border: 1px solid var(--hcis-border, #e2e8f0);
    background: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
    font-size: 0.84rem;
    font-weight: 550;
}

.pa-jp-toast--success {
    border-color: #86efac;
    color: #14532d;
    background: #f0fdf4;
}

.pa-jp-toast--danger {
    border-color: #fca5a5;
    color: #7f1d1d;
    background: #fef2f2;
}

.pa-jp-toast__close {
    margin-left: auto;
    border: 0;
    background: transparent;
    font-size: 1.15rem;
    line-height: 1;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}

.pa-jp-toast__close:hover {
    opacity: 1;
}

.pa-jp-toast-enter-active,
.pa-jp-toast-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.pa-jp-toast-enter-from,
.pa-jp-toast-leave-to {
    opacity: 0;
    transform: translateY(0.4rem);
}

/* ════ DUAL-MODE CONTENT & RESPONSIVE CARD GRID ════ */
.pa-jp-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* Toolbar & Segment Switcher */
.pa-matrix-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.2rem 0.25rem;
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

/* Card Grid Layout */
.pa-jp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-level-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    transition: transform 150ms ease, box-shadow 150ms ease;
}

.pa-level-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

.pa-level-card.is-selected {
    border-color: var(--hcis-brand, #2563eb);
    background-color: #f8faff;
}

.pa-level-card.is-excluded {
    opacity: 0.78;
    background: #fafafa;
}

.pa-level-card.is-deficit {
    border-left: 3px solid #f59e0b;
}

.pa-level-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    width: 100%;
    gap: 0.5rem;
    min-width: 0;
}

.pa-level-card__title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
    flex: 1;
}

.pa-card-check-wrap {
    display: flex;
    align-items: center;
    padding-top: 0.2rem;
    cursor: pointer;
}

.pa-level-card__name-box {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.pa-level-card__badges {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    min-width: 0;
}

.pa-level-card__title {
    font-size: 1rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
    margin: 0;
    word-break: break-word;
}

.pa-level-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    flex: 1;
    padding: 0.25rem 0;
    min-width: 0;
}

.pa-level-card__meta-bar {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
    min-width: 0;
}

.pa-level-card__emp {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
}

/* ════ COMPOSITION VISUALIZATION (CARD VIEW) ════ */
.pa-level-comp-box {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.06));
    margin-top: auto;
}

.pa-comp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.74rem;
}

.pa-comp-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 650;
    color: var(--hcis-text-primary, #1e293b);
}

.pa-comp-total-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
}

.pa-comp-total-pill.is-valid {
    background: #d1fae5;
    color: #065f46;
}

.pa-comp-total-pill.is-warning {
    background: #fef3c7;
    color: #92400e;
}

.pa-comp-total-pill.is-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Multi-Segment Stacked Progress Bar */
.pa-comp-bar {
    display: flex;
    width: 100%;
    height: 8px;
    border-radius: 9999px;
    overflow: hidden;
    background: #e2e8f0;
    gap: 1.5px;
}

.pa-comp-bar__segment {
    height: 100%;
    transition: width 0.3s ease;
}

.pa-comp-bar__remaining {
    height: 100%;
    background: repeating-linear-gradient(
        -45deg,
        #f1f5f9,
        #f1f5f9 4px,
        #e2e8f0 4px,
        #e2e8f0 8px
    );
}

/* Raters breakdown list in card */
.pa-comp-raters-list {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    margin-top: 0.2rem;
}

.pa-comp-rater-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.25rem 0.4rem;
    border-radius: 6px;
    font-size: 0.74rem;
    cursor: pointer;
    transition: background-color 0.12s ease;
    user-select: none;
}

.pa-comp-rater-row:hover {
    background: rgba(37, 99, 235, 0.06);
}

.pa-comp-rater-name-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.pa-comp-rater-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

.pa-comp-rater-name {
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-comp-rater-values {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-shrink: 0;
}

.pa-comp-rater-pct {
    font-size: 0.76rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-comp-rater-quota {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-comp-rater-pending {
    font-size: 0.68rem;
    font-weight: 650;
    color: #b45309;
    background: #fef3c7;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
}

.pa-comp-empty-prompt {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.6rem 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #94a3b8);
    font-style: italic;
}

/* Excluded Level Notice Box in Card */
.pa-level-excluded-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    margin-top: auto;
}

.pa-level-excluded-icon {
    color: #64748b;
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.pa-level-excluded-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.74rem;
    color: #475569;
}

.pa-level-excluded-text strong {
    color: #334155;
    font-weight: 700;
}

.pa-level-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.65rem;
    border-top: 1px dashed #e2e8f0;
    margin-top: auto;
    width: 100%;
    min-width: 0;
}

.pa-level-card__footer .hcis-button {
    flex: 1;
}

/* Dialog Responsive Actions (50/50 on mobile) */
.pa-dialog-actions--responsive {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

/* ════ RESPONSIVE BREAKPOINTS (Desktop, Tablet, Mobile) ════ */

/* Responsive Stat Cards: Single-row Horizontal Scroll strictly on Mobile (< 768px) */
@media (max-width: 767.98px) {
    .pa-cockpit-group {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        gap: 0.5rem !important;
        padding: 2px 2px 0.35rem 2px !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        scroll-snap-type: x mandatory !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .pa-cockpit-group::-webkit-scrollbar {
        display: none !important;
    }

    .pa-cockpit-group :deep(.hcis-stat),
    .pa-cockpit-group .hcis-stat {
        flex: 0 0 160px !important;
        min-width: 160px !important;
        max-width: 180px !important;
        scroll-snap-align: start !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
    }

    .pa-skel-cockpit {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        gap: 0.5rem !important;
        padding-bottom: 0.35rem !important;
        scrollbar-width: none !important;
        width: 100% !important;
    }

    .pa-skel-cockpit::-webkit-scrollbar {
        display: none !important;
    }

    .pa-skel-cockpit > * {
        flex: 0 0 160px !important;
        min-width: 160px !important;
    }
}

/* Tablet Breakpoint (768px – 1023px) */
@media (min-width: 768px) and (max-width: 1023px) {
    .pa-jp-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.875rem;
    }
}

/* Mobile Breakpoint (< 768px) */
@media (max-width: 767px) {
    .pa-jp-page {
        gap: 0.75rem;
    }

    .pa-config-card-toggle {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .pa-config-toggle__right {
        width: 100%;
    }

    .pa-config-toggle__right .hcis-button {
        width: 100%;
        justify-content: center;
    }

    .pa-matrix-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.6rem;
    }

    .pa-matrix-toolbar__left {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .pa-view-switch-pills {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .pa-view-pill {
        justify-content: center;
    }

    .pa-jp-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .pa-level-card__title {
        font-size: 0.95rem;
    }

    .pa-dialog-actions--responsive {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }

    .pa-bulk-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.6rem;
    }

    .pa-bulk-bar__actions {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
        gap: 0.4rem;
        margin-left: 0;
    }

    .pa-komposisi-meta-banner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .pa-komposisi-gauge-box {
        align-items: stretch;
        width: 100%;
    }

    .pa-komposisi-gauge-stat {
        justify-content: space-between;
    }
}

/* Extra Small Phones (< 420px) */
@media (max-width: 420px) {
    .pa-level-id-pill {
        font-size: 0.66rem;
        padding: 0.08rem 0.3rem;
    }

    .pa-level-card__footer {
        flex-direction: column;
        gap: 0.4rem;
    }

    .pa-level-card__footer .hcis-button {
        width: 100%;
    }
}
</style>
