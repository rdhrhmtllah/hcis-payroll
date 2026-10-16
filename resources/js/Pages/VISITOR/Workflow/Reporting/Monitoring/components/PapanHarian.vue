<template>
    <div class="papan-harian">
        <HcisAlert
            v-if="pesanGalat"
            variant="danger"
            appearance="glass"
            title="Aksi tidak dapat dijalankan"
            :description="pesanGalat"
            closable
            class="mb-3"
            @close="pesanGalat = ''"
        />

        <!-- Ringkasan Statistik Eksekutif (4 Kolom Desktop, 2x2 Mobile) -->
        <div class="papan-harian__stats mb-3">
            <HcisStatGroup :cols="kartuRingkas.length" gap="sm" appearance="crystal">
                <HcisStatCard
                    v-for="kartu in kartuRingkas"
                    :key="kartu.label"
                    :label="kartu.label"
                    :value="kartu.value"
                    :suffix="kartu.suffix"
                    :icon="kartu.icon"
                    :sentiment="kartu.sentiment"
                    :caption="kartu.caption"
                    :loading="memuat || menuju === kartu.tautan"
                    :interactive="Boolean(kartu.tautan)"
                    layout="compact"
                    appearance="crystal"
                    @click="kartu.tautan && bukaTautan(kartu.tautan)"
                />
            </HcisStatGroup>
        </div>

        <!-- ══ SEKSI 1: TABEL / KARTU KEADAAN TAMU HARI INI (Langsung di bawah ringkasan) ══ -->
        <HcisCard
            v-if="domain !== 'permit'"
            title="Tamu hari ini"
            description="Daftar kunjungan terjadwal, sedang di dalam area, dan yang belum checkout."
            icon="people-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            divider
            class="mb-3"
        >
            <template #actions>
                <div class="papan-harian__header-tools">
                    <!-- Tombol Pengalih Tampilan Tabel vs Kartu -->
                    <div class="papan-harian__view-toggle">
                        <button
                            type="button"
                            class="papan-harian__toggle-btn"
                            :class="{ 'is-active': viewMode === 'table' }"
                            title="Tampilan Tabel"
                            @click="viewMode = 'table'"
                        >
                            <HcisIcon name="table" size="xs" />
                        </button>
                        <button
                            type="button"
                            class="papan-harian__toggle-btn"
                            :class="{ 'is-active': viewMode === 'grid' }"
                            title="Tampilan Kartu"
                            @click="viewMode = 'grid'"
                        >
                            <HcisIcon name="grid-fill" size="xs" />
                        </button>
                    </div>

                    <HcisBadge
                        :label="`${paginasi.total} Tamu`"
                        variant="brand"
                        size="sm"
                        appearance="subtle"
                    />
                </div>
            </template>

            <!-- Search & Quick Filter Bar inside Card Body (Anti Cramped Header) -->
            <div class="papan-harian__body-toolbar mb-3">
                <HcisField
                    id="papan-cari-tamu"
                    v-model="cari"
                    placeholder="Cari nama tamu, instansi, PIC, plat nomor, atau status…"
                    leading-icon="search"
                    size="sm"
                    clearable
                    class="papan-harian__cari"
                />
            </div>

            <!-- TAMPILAN 1: Mode Tabel (Desktop & Tablet Lebar) -->
            <div v-if="viewMode === 'table'" class="papan-harian__table-wrapper">
                <HcisTable
                    :columns="KOLOM_TAMU"
                    :rows="barisTamu"
                    row-key="No_Transaksi"
                    :loading="memuatTamu"
                    sentiment="brand"
                    remote-sort
                    :sort-key="urut"
                    :sort-order="arah"
                    :empty-title="cari ? 'Tidak ada yang cocok dengan pencarian' : 'Tidak ada tamu yang perlu dipantau'"
                    :empty-description="cari ? 'Silakan sesuaikan kata kunci pencarian Anda.' : 'Kunjungan tamu hari ini akan otomatis muncul di sini.'"
                    dense
                    interactive
                    @sort="gantiUrutan"
                    @row-click="bukaDetailTamu"
                >
                    <template #cell-nama="{ row }">
                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <HcisAvatar
                                :name="row.Nama_Lengkap_Tamu"
                                size="xs"
                                shape="circle"
                                :variant="warnaStatus(row.Status_Kehadiran)"
                                class="flex-shrink-0"
                            />
                            <div class="min-w-0 flex-grow-1">
                                <strong class="text-truncate d-block text-slate-900 fw-bold">{{ row.Nama_Lengkap_Tamu || '-' }}</strong>
                                <div class="d-flex align-items-center gap-1 mt-0.5 flex-wrap">
                                    <span class="text-2xs text-muted font-tabular">#{{ row.No_Transaksi }}</span>
                                    <!-- Tipe dan asal: dua tamu yang perjalanannya
                                         sama sekali berbeda tampil identik tanpa ini. -->
                                    <span class="ph-tag" :class="'ph-tag--' + tipeVarian(row.Tipe_Tamu)">
                                        {{ labelTipe(row.Tipe_Tamu) }}
                                    </span>
                                    <span
                                        v-if="row.Sumber_Kunjungan === 'MITRA'"
                                        class="ph-tag ph-tag--mitra"
                                        :title="row.Nama_Mitra ? ('Didaftarkan aplikasi mitra: ' + row.Nama_Mitra) : 'Didaftarkan aplikasi mitra'"
                                    >
                                        Mitra
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #cell-Instansi_Atau_Asal="{ row }">
                        <div class="d-flex align-items-center gap-1.5 min-w-0 text-slate-700" :title="row.Instansi_Atau_Asal">
                            <HcisIcon name="building" size="2xs" class="text-slate-400 flex-shrink-0" />
                            <span class="text-truncate text-xs fw-medium">{{ row.Instansi_Atau_Asal || 'Umum / Pribadi' }}</span>
                        </div>
                    </template>

                    <template #cell-Nama_PIC="{ row }">
                        <div class="d-flex align-items-center gap-1.5 min-w-0 text-slate-700" :title="row.Nama_PIC">
                            <HcisIcon name="person-badge" size="2xs" class="text-slate-400 flex-shrink-0" />
                            <span class="text-truncate text-xs fw-medium">{{ row.Nama_PIC || '-' }}</span>
                        </div>
                    </template>

                    <template #cell-Jumlah_Tamu="{ row }">
                        <!-- Angkanya saja tidak menolong satpam: yang ia butuhkan
                             adalah SIAPA yang datang. Manifesnya sudah tersimpan
                             sejak registrasi, hanya belum pernah ditampilkan. -->
                        <button
                            v-if="punyaManifes(row)"
                            type="button"
                            class="ph-mini-pill ph-mini-pill--klik font-tabular"
                            :title="'Lihat ' + row.Daftar_Anggota.length + ' anggota rombongan'"
                            @click.stop="bukaManifes(row)"
                        >
                            {{ row.Jumlah_Tamu || 1 }}
                            <HcisIcon name="people-fill" size="2xs" />
                        </button>
                        <span v-else class="ph-mini-pill font-tabular">{{ row.Jumlah_Tamu || 1 }}</span>
                    </template>

                    <template #cell-status="{ row }">
                        <HcisBadge
                            :label="row.Status_Kehadiran"
                            :variant="warnaStatus(row.Status_Kehadiran)"
                            :pulse="row.Status_Kehadiran === 'Di dalam'"
                            size="xs"
                            appearance="subtle"
                        />
                    </template>

                    <template #cell-masuk="{ row }">
                        <span class="papan-harian__waktu">{{ jam(row.Waktu_Aktual_Masuk) }}</span>
                    </template>

                    <template #cell-keluar="{ row }">
                        <span class="papan-harian__waktu">{{ jam(row.Waktu_Aktual_Keluar) }}</span>
                    </template>

                    <template #cell-aksi="{ row }">
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <!-- Lompat ke izin gerbang yang menahannya. Tanpa ini
                                 admin harus menyalin nomor kunjungan, pindah
                                 halaman, lalu mencarinya sendiri. -->
                            <HcisButton
                                v-if="row.Tautan_Permit && bolehBukaPermit"
                                :variant="varianIzin(row.Status_Permit_Coklat)"
                                size="xs"
                                leading-icon="shield-lock"
                                :title="'Permit ' + (row.Status_Permit_Coklat || '').toLowerCase() + ' — buka di meja verifikator permit'"
                                @click.stop="bukaTautan(row.Tautan_Permit)"
                            >
                                {{ labelIzin(row.Status_Permit_Coklat) }}
                            </HcisButton>
                            <!-- Tanpa hak membuka permit, keadaannya TETAP
                                 ditampilkan — hanya lompatannya yang tidak ada.
                                 Menyembunyikan keduanya membuat admin tamu tidak
                                 tahu kenapa tamunya tertahan. -->
                            <span
                                v-else-if="row.Status_Permit_Coklat"
                                class="ph-tag"
                                :class="'ph-tag--izin-' + varianIzin(row.Status_Permit_Coklat)"
                                :title="'Permit: ' + labelIzin(row.Status_Permit_Coklat)"
                            >
                                {{ labelIzin(row.Status_Permit_Coklat) }}
                            </span>
                            <HcisButton
                                label="Detail"
                                variant="secondary"
                                size="xs"
                                leading-icon="eye"
                                @click.stop="bukaDetailTamu(row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>

            <!-- TAMPILAN 2: Mode Kartu Grid Responsif (Mobile & Tablet) -->
            <div v-else class="papan-tamu-grid-wrap">
                <!-- Skeleton Loading Cards -->
                <div v-if="memuatTamu && !barisTamu.length" class="papan-tamu-grid">
                    <div v-for="n in 4" :key="`skel-${n}`" class="papan-tamu-card papan-tamu-card--skeleton">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <HcisSkeleton width="45%" height="1.1rem" rounded="sm" />
                            <HcisSkeleton width="22%" height="1.1rem" rounded="full" />
                        </div>
                        <div class="d-flex align-items-center gap-2.5 my-1">
                            <HcisSkeleton width="2.25rem" height="2.25rem" rounded="full" class="flex-shrink-0" />
                            <div class="flex-grow-1 min-w-0">
                                <HcisSkeleton width="65%" height="1rem" rounded="sm" class="mb-1" />
                                <HcisSkeleton width="40%" height="0.8rem" rounded="sm" />
                            </div>
                        </div>
                        <div class="papan-tamu-card__specs-panel">
                            <HcisSkeleton height="1.8rem" rounded="sm" class="mb-1" />
                            <HcisSkeleton height="1.6rem" rounded="sm" />
                        </div>
                        <HcisSkeleton height="2.25rem" rounded="md" />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="!memuatTamu && !barisTamu.length" class="py-4">
                    <HcisEmptyState
                        :title="cari ? 'Tidak ada yang cocok dengan pencarian' : 'Tidak ada tamu yang perlu dipantau'"
                        description="Data kunjungan tamu hari ini akan tampil di sini secara real-time."
                        icon="people"
                    />
                </div>

                <!-- Live Card Grid Items -->
                <div v-else class="papan-tamu-grid">
                    <div
                        v-for="row in barisTamu"
                        :key="row.No_Transaksi"
                        class="papan-tamu-card"
                        :class="[
                            'papan-tamu-card--' + warnaStatus(row.Status_Kehadiran),
                            { 'is-active-presence': row.Status_Kehadiran === 'Di dalam' }
                        ]"
                        @click="bukaDetailTamu(row)"
                    >
                        <!-- 1. Top Meta Bar: Trx, Badges & Status -->
                        <div class="papan-tamu-card__top-meta">
                            <div class="papan-tamu-card__tags-group">
                                <span class="papan-tamu-card__trx font-tabular" :title="`No Transaksi: ${row.No_Transaksi}`">
                                    #{{ row.No_Transaksi }}
                                </span>
                                <span class="ph-tag" :class="'ph-tag--' + tipeVarian(row.Tipe_Tamu)">
                                    <HcisIcon :name="row.Tipe_Tamu === 'B2B' ? 'truck' : 'person-fill'" size="2xs" />
                                    <span>{{ labelTipe(row.Tipe_Tamu) }}</span>
                                </span>
                                <span
                                    v-if="row.Sumber_Kunjungan === 'MITRA'"
                                    class="ph-tag ph-tag--mitra"
                                    :title="row.Nama_Mitra ? ('Didaftarkan aplikasi mitra: ' + row.Nama_Mitra) : 'Didaftarkan aplikasi mitra'"
                                >
                                    <HcisIcon name="link-45deg" size="2xs" />
                                    <span>Mitra</span>
                                </span>
                                <button
                                    v-if="punyaManifes(row)"
                                    type="button"
                                    class="ph-mini-pill ph-mini-pill--klik font-tabular"
                                    :title="`Lihat ${row.Daftar_Anggota?.length || row.Jumlah_Tamu} anggota rombongan`"
                                    @click.stop="bukaManifes(row)"
                                >
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <span>{{ row.Jumlah_Tamu }} org</span>
                                </button>
                                <span v-else-if="row.Jumlah_Tamu > 1" class="ph-mini-pill font-tabular">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <span>{{ row.Jumlah_Tamu }} org</span>
                                </span>
                            </div>

                            <HcisBadge
                                :label="row.Status_Kehadiran"
                                :variant="warnaStatus(row.Status_Kehadiran)"
                                :pulse="row.Status_Kehadiran === 'Di dalam'"
                                size="xs"
                                appearance="subtle"
                                class="papan-tamu-card__status-badge flex-shrink-0"
                            />
                        </div>

                        <!-- 2. Guest Identity: Avatar + Nama + Instansi/Asal -->
                        <div class="papan-tamu-card__identity">
                            <HcisAvatar
                                :name="row.Nama_Lengkap_Tamu"
                                size="sm"
                                shape="circle"
                                :variant="warnaStatus(row.Status_Kehadiran)"
                                class="papan-tamu-card__avatar flex-shrink-0"
                            />
                            <div class="papan-tamu-card__identity-info min-w-0 flex-grow-1">
                                <strong class="papan-tamu-card__name text-truncate d-block" :title="row.Nama_Lengkap_Tamu">
                                    {{ row.Nama_Lengkap_Tamu || '-' }}
                                </strong>
                                <div class="papan-tamu-card__instansi text-truncate" :title="row.Instansi_Atau_Asal">
                                    <HcisIcon name="building" size="2xs" class="text-slate-400 flex-shrink-0" />
                                    <span class="text-truncate">{{ row.Instansi_Atau_Asal || 'Umum / Pribadi' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Live Presence Banner / Warning Banner -->
                        <div
                            v-if="row.Status_Kehadiran === 'Di dalam'"
                            class="papan-tamu-card__presence-strip papan-tamu-card__presence-strip--inside"
                        >
                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                <span class="papan-tamu-card__pulse-dot" />
                                <span class="papan-tamu-card__presence-text text-truncate">Sedang di dalam area</span>
                            </div>
                            <span v-if="row.Waktu_Aktual_Masuk" class="papan-tamu-card__presence-sub font-tabular ms-auto flex-shrink-0">
                                Sejak {{ jam(row.Waktu_Aktual_Masuk) }}
                            </span>
                        </div>
                        <div
                            v-else-if="row.Status_Kehadiran === 'Belum selesai'"
                            class="papan-tamu-card__presence-strip papan-tamu-card__presence-strip--warning"
                        >
                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                <HcisIcon name="exclamation-circle-fill" size="2xs" class="text-rose-500 flex-shrink-0" />
                                <span class="papan-tamu-card__presence-text text-rose-700 text-truncate">Belum checkout</span>
                            </div>
                            <span v-if="row.Waktu_Aktual_Masuk" class="papan-tamu-card__presence-sub font-tabular ms-auto flex-shrink-0">
                                Masuk: {{ jam(row.Waktu_Aktual_Masuk) }}
                            </span>
                        </div>
                        <div
                            v-else-if="row.Status_Kehadiran === 'Keluar tak tercatat'"
                            class="papan-tamu-card__presence-strip papan-tamu-card__presence-strip--muted"
                        >
                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                <HcisIcon name="clock-history" size="2xs" class="text-amber-500 flex-shrink-0" />
                                <span class="papan-tamu-card__presence-text text-amber-700 text-truncate">Keluar tak tercatat</span>
                            </div>
                        </div>

                        <!-- 4. Key Specs & Timing Panel -->
                        <div class="papan-tamu-card__specs-panel">
                            <!-- PIC & Kategori/Permit -->
                            <div class="papan-tamu-card__specs-row">
                                <div class="papan-tamu-card__spec-cell">
                                    <span class="papan-tamu-card__spec-kicker">
                                        <HcisIcon name="person-badge" size="2xs" class="text-slate-400" />
                                        <span>PIC Tujuan</span>
                                    </span>
                                    <span class="papan-tamu-card__spec-val text-truncate" :title="row.Nama_PIC">
                                        {{ row.Nama_PIC || '-' }}
                                    </span>
                                </div>
                                <div class="papan-tamu-card__spec-cell">
                                    <template v-if="row.Status_Permit_Coklat">
                                        <span class="papan-tamu-card__spec-kicker">
                                            <HcisIcon name="shield-lock" size="2xs" class="text-slate-400" />
                                            <span>Permit Gate</span>
                                        </span>
                                        <div class="d-flex align-items-center gap-1 mt-0.5">
                                            <span
                                                class="ph-tag font-medium"
                                                :class="'ph-tag--izin-' + varianIzin(row.Status_Permit_Coklat)"
                                            >
                                                {{ labelIzin(row.Status_Permit_Coklat) }}
                                            </span>
                                            <span v-if="row.No_Transaksi_Permit" class="text-2xs text-muted font-tabular">
                                                #{{ row.No_Transaksi_Permit }}
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="papan-tamu-card__spec-kicker">
                                            <HcisIcon name="tag" size="2xs" class="text-slate-400" />
                                            <span>Kategori</span>
                                        </span>
                                        <span class="papan-tamu-card__spec-val text-truncate" :title="row.Tamu_Kategori">
                                            {{ row.Tamu_Kategori || (row.Tipe_Tamu === 'B2B' ? 'Tamu Bisnis' : 'Reguler') }}
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- Timing Bar -->
                            <div class="papan-tamu-card__timing-bar">
                                <div class="papan-tamu-card__timing-point">
                                    <span class="papan-tamu-card__timing-label">
                                        <HcisIcon name="box-arrow-in-right" size="2xs" class="text-indigo-500" />
                                        <span>{{ row.Waktu_Aktual_Masuk ? 'Check-in' : 'Est. Masuk' }}</span>
                                    </span>
                                    <span
                                        class="papan-tamu-card__timing-val font-tabular"
                                        :class="{ 'text-indigo-600 fw-bold': row.Waktu_Aktual_Masuk }"
                                    >
                                        {{ row.Waktu_Aktual_Masuk ? jam(row.Waktu_Aktual_Masuk) : (jam(row.Estimasi_Masuk) !== '-' ? jam(row.Estimasi_Masuk) : '-') }}
                                    </span>
                                </div>

                                <div class="papan-tamu-card__timing-arrow">
                                    <HcisIcon name="arrow-right" size="2xs" class="text-slate-300" />
                                </div>

                                <div class="papan-tamu-card__timing-point text-end">
                                    <span class="papan-tamu-card__timing-label justify-content-end">
                                        <HcisIcon name="box-arrow-right" size="2xs" class="text-rose-500" />
                                        <span>{{ row.Waktu_Aktual_Keluar ? 'Check-out' : 'Est. Keluar' }}</span>
                                    </span>
                                    <span
                                        class="papan-tamu-card__timing-val font-tabular"
                                        :class="{ 'text-rose-600 fw-bold': row.Waktu_Aktual_Keluar }"
                                    >
                                        {{ row.Waktu_Aktual_Keluar ? jam(row.Waktu_Aktual_Keluar) : (jam(row.Estimasi_Keluar) !== '-' ? jam(row.Estimasi_Keluar) : '-') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Card Action Footer -->
                        <div class="papan-tamu-card__footer">
                            <HcisButton
                                label="Detail Kunjungan"
                                variant="secondary"
                                size="sm"
                                leading-icon="eye"
                                class="papan-tamu-card__btn-action"
                                @click.stop="bukaDetailTamu(row)"
                            />
                            <HcisButton
                                v-if="row.Tautan_Permit && bolehBukaPermit"
                                :label="'Permit ' + labelIzin(row.Status_Permit_Coklat)"
                                :variant="varianIzin(row.Status_Permit_Coklat)"
                                size="sm"
                                leading-icon="shield-lock"
                                class="papan-tamu-card__btn-action"
                                :title="'Buka permit ' + (row.Status_Permit_Coklat || '').toLowerCase() + ' di verifikator permit'"
                                @click.stop="bukaTautan(row.Tautan_Permit)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <HcisPagination
                    v-if="paginasi.total > paginasi.per_page"
                    :model-value="paginasi.current_page"
                    :page-size="paginasi.per_page"
                    :total="paginasi.total"
                    @update:model-value="gantiHalaman"
                    @update:page-size="gantiUkuranHalaman"
                />
            </template>
        </HcisCard>

        <!-- Semua kelompok kosong: satu pesan, bukan empat kartu kosong. -->
        <HcisCard
            v-if="tidakAdaTindakan"
            variant="glass"
            size="md"
            sentiment="success"
            class="mb-3"
        >
            <div class="papan-harian__bersih">
                <HcisIcon name="check-circle-fill" size="lg" class="text-emerald-500" />
                <div>
                    <strong class="d-block text-slate-800">{{ pesanBersih.judul }}</strong>
                    <span class="text-sm text-muted">{{ pesanBersih.keterangan }}</span>
                </div>
            </div>
        </HcisCard>

        <!-- ══ SEKSI 2: SLOT ANALISIS & PERSETUJUAN (Antrean Keputusan & Tren Heatmap) ══ -->
        <slot name="antara" />

        <!-- ══ SEKSI 3: BLOK KEADAAN PERMIT (hanya tampil jika bukan domain tamu) ══ -->
        <div v-if="blokPermit.length" class="papan-harian__section-title mt-4 mb-2">
            <HcisIcon name="car-front-fill" size="sm" class="text-indigo-600 me-2" />
            <h3 class="d-inline-block text-sm fw-bold text-slate-800 m-0">Keadaan Permit Kendaraan</h3>
        </div>

        <HcisCard
            v-for="blok in blokPermit"
            :key="blok.kunci"
            :title="blok.judul"
            :description="blok.keterangan"
            :icon="blok.ikon"
            :sentiment="blok.sentiment"
            variant="glass"
            size="md"
            divider
            class="mb-3"
        >
            <template #actions>
                <HcisBadge
                    :label="`${blok.baris.length} Permit`"
                    :variant="blok.sentiment"
                    size="sm"
                    appearance="subtle"
                />
            </template>

            <!-- Tampilan Desktop: Tabel HcisTable -->
            <div class="d-none d-md-block">
                <HcisTable
                    :columns="KOLOM_PERMIT"
                    :rows="blok.baris"
                    row-key="No_Transaksi"
                    :loading="memuat"
                    :sentiment="blok.sentiment"
                    :empty-title="blok.kosong"
                    dense
                    interactive
                    @row-click="bukaDetailPermit"
                >
                    <template #cell-Nama_Pemohon="{ row }">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <HcisAvatar
                                :name="row.Nama_Pemegang || row.Nama_Pemohon"
                                size="xs"
                                shape="circle"
                                :variant="row.Jenis_Permit === 'KARYAWAN' ? 'brand' : 'warning'"
                            />
                            <div class="min-w-0">
                                <!-- PEMEGANG izin. Izin tamu diajukan atas nama
                                     PIC, dan papan gerbang yang menampilkan nama
                                     PIC membuat satpam mencari orang yang memang
                                     tidak akan datang. -->
                                <strong class="text-truncate d-block text-slate-800">
                                    {{ row.Nama_Pemegang || row.Nama_Pemohon || '-' }}
                                </strong>
                                <div class="d-flex align-items-center gap-1 flex-wrap mt-0.5">
                                    <span class="text-2xs text-muted font-tabular">#{{ row.No_Transaksi }}</span>
                                    <span class="ph-tag" :class="'ph-tag--asal-' + asalVarian(row.Jenis_Permit)">
                                        {{ labelAsal(row.Jenis_Permit) }}
                                    </span>
                                    <span
                                        v-if="row.Instansi_Pemegang"
                                        class="text-2xs text-muted text-truncate"
                                        :title="row.Instansi_Pemegang"
                                    >
                                        {{ row.Instansi_Pemegang }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #cell-No_Plat_Kendaraan="{ row }">
                        <span class="ph-plate-badge font-tabular">{{ row.No_Plat_Kendaraan || '-' }}</span>
                    </template>
                    <template #cell-Snapshot_Kategori_Permit="{ row }">
                        <span class="ph-kategori-pill text-truncate">{{ row.Snapshot_Kategori_Permit || 'Umum' }}</span>
                    </template>
                    <template #cell-Tanggal_Mulai_Berlaku_Ymd="{ row }">
                        <span class="font-tabular text-xs">{{ row.Tanggal_Mulai_Berlaku_Ymd || '-' }}</span>
                    </template>
                    <template #cell-Tanggal_Kadaluarsa_Ymd="{ row }">
                        <!-- Jam ikut ditampilkan bila izinnya punya jendela:
                             "kedaluwarsa hari ini" pukul 08:00 dan pukul 23:00
                             adalah dua tingkat kegentingan yang berbeda, dan
                             yang pertama sudah mati saat papannya dibaca. -->
                        <div class="d-flex flex-column">
                            <span class="font-tabular text-xs">{{ row.Tanggal_Kadaluarsa_Ymd || '-' }}</span>
                            <span v-if="jamJendela(row.Waktu_Berakhir)" class="text-2xs text-muted font-tabular">
                                s/d {{ jamJendela(row.Waktu_Berakhir) }}
                            </span>
                        </div>
                    </template>
                    <template #cell-aksi="{ row }">
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <!-- Arah balik dari tautan di papan tamu: dari izin
                                 yang bermasalah langsung ke kunjungan asalnya. -->
                            <HcisButton
                                v-if="row.Tautan_Tamu"
                                variant="ghost"
                                size="xs"
                                leading-icon="people"
                                title="Buka kunjungan tamu asal permit ini"
                                @click.stop="bukaTautan(row.Tautan_Tamu)"
                            >
                                Kunjungan
                            </HcisButton>
                            <HcisButton
                                label="Detail"
                                variant="secondary"
                                size="xs"
                                leading-icon="eye"
                                @click.stop="bukaDetailPermit(row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>

            <!-- Tampilan Mobile & Tablet Kecil: Compact Card List -->
            <div class="d-md-none">
                <div v-if="!blok.baris.length" class="py-3">
                    <HcisEmptyState :title="blok.kosong" size="sm" icon="check-circle" />
                </div>
                <div v-else class="ph-mobile-list">
                    <div
                        v-for="row in blok.baris"
                        :key="row.No_Transaksi"
                        class="ph-mobile-card ph-mobile-card--interactive"
                        role="button"
                        tabindex="0"
                        @click="bukaDetailPermit(row)"
                        @keydown.enter="bukaDetailPermit(row)"
                    >
                        <div class="d-flex align-items-center justify-content-between gap-2 min-w-0 mb-1.5">
                            <span class="ph-plate-badge font-tabular">{{ row.No_Plat_Kendaraan || '-' }}</span>
                            <div class="d-flex align-items-center gap-1 min-w-0">
                                <span class="ph-kategori-pill text-truncate">{{ row.Snapshot_Kategori_Permit || 'Umum' }}</span>
                                <HcisIcon name="chevron-right" size="2xs" class="text-slate-400" />
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <HcisAvatar
                                :name="row.Nama_Pemohon"
                                size="sm"
                                variant="brand"
                                shape="circle"
                                class="flex-shrink-0"
                            />
                            <div class="overflow-hidden min-w-0 flex-grow-1">
                                <strong class="text-slate-900 text-xs fw-bold text-truncate d-block">
                                    {{ row.Nama_Pemohon || '-' }}
                                </strong>
                                <div class="text-2xs text-slate-500 text-truncate d-flex align-items-center gap-1.5 mt-0.5 font-tabular">
                                    <HcisIcon name="calendar-range" size="2xs" class="text-slate-400" />
                                    <span>{{ row.Tanggal_Mulai_Berlaku_Ymd || '-' }} s/d {{ row.Tanggal_Kadaluarsa_Ymd || '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </HcisCard>

        <!-- Modal Detail Tamu dari Papan Harian (Dual-Pane Dialog) -->
        <TamuReviewModal
            v-if="dialogTamuDetail"
            :show="dialogTamuDetail"
            :tamu-id="idTamuDetail"
            :detail-endpoint="endpointTamu.detail"
            read-only
            :boleh-buka-permit="bolehBukaPermit"
            @close="dialogTamuDetail = false"
        />

        <!-- Manifes rombongan. Sengaja dialog ringan, bukan halaman: yang
             ditanyakan satpam di pos cuma "siapa saja yang ikut". -->
        <HcisDialog
            v-model="dialogManifes"
            :title="'Rombongan ' + (manifesAktif?.Nama_Lengkap_Tamu || '')"
            :description="manifesAktif?.Instansi_Atau_Asal || ''"
            kicker="Visitor · Monitoring Tamu"
            icon="people-fill"
            sentiment="purple"
            size="sm"
        >
            <div class="ph-manifes">
                    <p class="ph-manifes__ringkas">
                        Tercatat <strong>{{ manifesAktif?.Jumlah_Tamu || 1 }} orang</strong> pada kunjungan
                        <span class="font-tabular">#{{ manifesAktif?.No_Transaksi }}</span>.
                    </p>

                    <ol class="ph-manifes__daftar">
                        <li class="ph-manifes__item ph-manifes__item--utama">
                            <HcisIcon name="person-fill" size="xs" />
                            <span>{{ manifesAktif?.Nama_Lengkap_Tamu || '-' }}</span>
                            <span class="ph-manifes__peran">Tamu utama</span>
                        </li>
                        <li
                            v-for="(anggota, i) in (manifesAktif?.Daftar_Anggota || [])"
                            :key="i"
                            class="ph-manifes__item"
                        >
                            <HcisIcon name="person" size="xs" />
                            <span>{{ anggota.nama }}</span>
                        </li>
                    </ol>

                    <!-- Selisih antara angka dan daftar bukan kesalahan yang
                         perlu disembunyikan: registrasi lama tidak mengumpulkan
                         nama anggota sama sekali, dan menyamarkannya membuat
                         admin mengira ada nama yang hilang. -->
                <p v-if="selisihRombongan(manifesAktif) > 0" class="ph-manifes__catatan">
                    {{ selisihRombongan(manifesAktif) }} orang lainnya tidak tercatat namanya saat registrasi.
                </p>
            </div>
        </HcisDialog>

        <!-- Modal Detail Permit dari Papan Harian -->
        <PermitReviewModal
            v-if="dialogPermitDetail"
            :show="dialogPermitDetail"
            :permit-id="idPermitDetail"
            detail-endpoint="/laporanvisitor-detail-permit"
            read-only
            :cetak-tersedia="bolehBukaPermit"
            @close="dialogPermitDetail = false"
        />
    </div>
</template>

<script>
import axios from 'axios';
import TamuReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue';
import PermitReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisButton,
    HcisField,
    HcisBadge,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisTable,
} from '@/components/ui';

export default {
    inject: {
        // Disediakan halaman Monitoring Tamu (Regular / B2B). Bawaannya
        // endpoint Regular, sehingga pemakai lain (halaman permit, uji) tidak
        // perlu menyediakannya.
        endpointTamu: {
            from: 'visitorTamuEndpoints',
            default: () => ({
                laporan: '/laporanvisitor-tamu',
                detail: '/laporanvisitor-detail-tamu',
                papan_harian: '/laporanvisitor-papan-harian',
                papan_tamu: '/laporanvisitor-papan-tamu',
                antrian: '/laporanvisitor-antrian',
                verifikator: '/verifikator-visitor/tamu',
            }),
        },
    },
    name: 'PapanHarian',
    components: {
        TamuReviewModal,
        PermitReviewModal,
        HcisAlert,
        HcisAvatar,
        HcisButton,
        HcisField,
        HcisBadge,
        HcisCard,
        HcisDialog,
        HcisEmptyState,
        HcisIcon,
        HcisPagination,
        HcisSkeleton,
        HcisStatCard,
        HcisStatGroup,
        HcisTable,
    },
    props: {
        // 'tamu' | 'permit' | null (keduanya). Diteruskan ke server supaya
        // kelompok milik domain tetangga tidak dikueri maupun dikirim.
        domain: { type: String, default: null },
        needsApproval: { type: Boolean, default: null },
        // Boleh tidaknya melompat ke meja permit. Dari server; lihat
        // LaporanVisitorController::indexTamu().
        bolehBukaPermit: { type: Boolean, default: false },
    },
    data() {
        return {
            memuat: true,
            pesanGalat: '',
            papan: this.papanKosong(),
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',
            dialogTamuDetail: false,
            dialogManifes: false,
            manifesAktif: null,
            idTamuDetail: null,
            dialogPermitDetail: false,
            idPermitDetail: null,
            // `key` di sini adalah KUNCI URUT yang dikenal server
            KOLOM_TAMU: [
                { key: 'nama', label: 'Nama tamu', sortable: true, width: '220px' },
                { key: 'Instansi_Atau_Asal', label: 'Instansi / Asal', sortable: false },
                { key: 'Nama_PIC', label: 'PIC Tujuan', sortable: false, width: '180px' },
                { key: 'Jumlah_Tamu', label: 'Jml', width: '64px', align: 'center' },
                { key: 'status', label: 'Status', width: '130px', sortable: true },
                { key: 'masuk', label: 'Check-in', width: '96px', sortable: true },
                { key: 'keluar', label: 'Check-out', width: '96px', sortable: true },
                { key: 'aksi', label: 'Aksi', width: '90px', align: 'right' },
            ],
            barisTamu: [],
            memuatTamu: true,
            cari: '',
            urut: 'status',
            arah: 'asc',
            paginasi: { total: 0, current_page: 1, per_page: 10, total_pages: 1 },
            jedaCari: null,
            menuju: '',
            KOLOM_PERMIT: [
                { key: 'Nama_Pemohon', label: 'Pemegang permit' },
                { key: 'No_Plat_Kendaraan', label: 'Plat', width: '130px' },
                { key: 'Snapshot_Kategori_Permit', label: 'Kategori', width: '130px' },
                { key: 'Tanggal_Mulai_Berlaku_Ymd', label: 'Mulai', width: '120px' },
                { key: 'Tanggal_Kadaluarsa_Ymd', label: 'Kedaluwarsa', width: '130px' },
                { key: 'aksi', label: '', width: '90px', align: 'right' },
            ],
        };
    },
    computed: {
        isNeedsApproval() {
            if (this.needsApproval !== null) {
                return this.needsApproval;
            }
            if (this.papan?.needs_approval !== undefined) {
                return Boolean(this.papan.needs_approval);
            }
            return this.domain === 'permit';
        },
        tanggalLabel() {
            if (this.papan?.tanggal) {
                return `Papan Kehadiran per ${this.papan.tanggal}`;
            }
            return 'Papan Kehadiran Hari Ini';
        },
        kartuRingkas() {
            const r = this.papan.ringkasan || {};
            if (this.domain === 'permit') {
                const kartuPermit = [];
                if (this.isNeedsApproval) {
                    kartuPermit.push({
                        label: 'Menunggu Approval',
                        value: r.permit_menunggu_review || 0,
                        suffix: 'Permit',
                        icon: 'hourglass-split',
                        sentiment: r.permit_menunggu_review ? 'warning' : 'neutral',
                        caption: r.permit_menunggu_review ? 'Klik untuk verifikasi' : 'Tidak ada yang menunggu keputusan',
                        tautan: r.permit_menunggu_review ? '/verifikator-visitor?jenis=permit' : null,
                    });
                }
                kartuPermit.push(
                    { label: 'Kedaluwarsa hari ini', value: r.permit_kedaluwarsa_hari_ini || 0, suffix: 'Permit', icon: 'calendar-x', sentiment: r.permit_kedaluwarsa_hari_ini ? 'danger' : 'neutral', caption: 'Masa berlakunya berakhir hari ini' },
                    { label: 'Segera kedaluwarsa', value: r.permit_segera_kedaluwarsa || 0, suffix: 'Permit', icon: 'calendar-event', sentiment: r.permit_segera_kedaluwarsa ? 'warning' : 'neutral', caption: 'Dalam 30 hari ke depan' },
                );
                return kartuPermit;
            }

            const orang = (kunci) => {
                const n = r[`${kunci}_orang`];
                return n ? `${n} orang` : '—';
            };

            const kartuTamu = [
                { label: 'Harus datang hari ini', value: r.harus_datang || 0, suffix: 'Kunjungan', icon: 'calendar-check', sentiment: 'brand', caption: orang('harus_datang') },
                { label: 'Check-In (Aktif)', value: r.sedang_di_dalam || 0, suffix: 'Kunjungan', icon: 'door-open', sentiment: 'success', caption: orang('sedang_di_dalam') },
                { label: 'Belum selesai', value: r.menginap || 0, suffix: 'Kunjungan', icon: 'exclamation-circle', sentiment: r.menginap ? 'danger' : 'neutral', caption: orang('menginap') },
            ];

            if (this.isNeedsApproval) {
                kartuTamu.push({
                    label: 'Tertahan approval',
                    value: r.menunggu_approval_hari_ini || 0,
                    suffix: 'Kunjungan',
                    icon: 'hourglass-split',
                    sentiment: r.menunggu_approval_hari_ini ? 'warning' : 'neutral',
                    caption: r.menunggu_approval_hari_ini ? 'Klik untuk verifikasi' : orang('menunggu_approval_hari_ini'),
                    tautan: r.menunggu_approval_hari_ini ? this.endpointTamu.verifikator : null,
                });
            }

            return kartuTamu;
        },
        tidakAdaTindakan() {
            return ! this.memuat && ! this.memuatTamu && this.barisTamu.length === 0 && this.blokPermit.length === 0;
        },
        pesanBersih() {
            if (this.domain === 'permit') {
                return {
                    judul: 'Tidak ada permit yang perlu ditindak',
                    keterangan: 'Tidak ada yang menunggu keputusan, kedaluwarsa hari ini, maupun mendekati batas.',
                };
            }

            return {
                judul: 'Tidak ada yang perlu ditindak hari ini',
                keterangan: 'Tidak ada kunjungan terjadwal yang menunggu, tamu di dalam area, maupun yang tertinggal dari hari sebelumnya.',
            };
        },
        blokPermit() {
            return this.blokPermitSemua.filter((b) => b.baris.length > 0);
        },
        blokPermitSemua() {
            if (this.domain === 'tamu') return [];

            return [
                {
                    kunci: 'permit_kedaluwarsa_hari_ini',
                    judul: 'Permit kedaluwarsa hari ini',
                    keterangan: 'Masa berlakunya berakhir hari ini.',
                    ikon: 'calendar-x',
                    sentiment: 'danger',
                    kosong: 'Tidak ada permit yang berakhir hari ini',
                    baris: this.papan.permit_kedaluwarsa_hari_ini || [],
                },
                {
                    kunci: 'permit_segera_kedaluwarsa',
                    judul: 'Segera kedaluwarsa (30 hari)',
                    keterangan: 'Perlu diperpanjang sebelum masa berlakunya habis.',
                    ikon: 'hourglass-split',
                    sentiment: 'warning',
                    kosong: 'Tidak ada yang mendekati kedaluwarsa',
                    baris: this.papan.permit_segera_kedaluwarsa || [],
                },
            ];
        },
    },
    watch: {
        cari() {
            clearTimeout(this.jedaCari);
            this.jedaCari = setTimeout(() => {
                this.paginasi.current_page = 1;
                this.muatTamu();
            }, 350);
        },
    },
    beforeUnmount() {
        clearTimeout(this.jedaCari);
    },
    mounted() {
        this.muat();
        this.muatTamu();
    },
    methods: {
        papanKosong() {
            return {
                tanggal: null,
                ringkasan: {},
                harus_datang: [],
                menunggu_approval_hari_ini: [],
                sedang_di_dalam: [],
                menginap: [],
                permit_kedaluwarsa_hari_ini: [],
                permit_segera_kedaluwarsa: [],
            };
        },
        /**
         * Berapa orang yang jumlahnya tercatat tetapi namanya tidak.
         *
         * Tamu utama sudah terhitung satu, jadi yang dibandingkan adalah sisa
         * rombongannya.
         */
        selisihRombongan(row) {
            const jumlah = Number(row?.Jumlah_Tamu || 1);
            const bernama = (row?.Daftar_Anggota || []).length;

            return Math.max(0, jumlah - 1 - bernama);
        },
        punyaManifes(row) {
            return Boolean(row && Array.isArray(row.Daftar_Anggota) && row.Daftar_Anggota.length);
        },
        bukaManifes(row) {
            this.manifesAktif = row;
            this.dialogManifes = true;
        },
        labelAsal(asal) {
            return {
                KARYAWAN: 'Karyawan',
                TAMU: 'Permit tamu',
                MITRA: 'Mitra',
            }[asal] || 'Karyawan';
        },
        asalVarian(asal) {
            if (asal === 'TAMU') return 'tamu';
            if (asal === 'MITRA') return 'mitra';

            return 'karyawan';
        },
        /**
         * Jam dari jendela berlaku, atau kosong bila izinnya tidak punya.
         *
         * Izin bulanan karyawan tidak berjendela jam; menempelkan "00:00"
         * padanya hanya derau yang harus diabaikan pembacanya.
         */
        jamJendela(waktu) {
            return waktu ? String(waktu).slice(11, 16) : '';
        },
        labelTipe(tipe) {
            return String(tipe || 'REGULAR').toUpperCase() === 'B2B' ? 'B2B' : 'Reguler';
        },
        tipeVarian(tipe) {
            return String(tipe || 'REGULAR').toUpperCase() === 'B2B' ? 'b2b' : 'reguler';
        },
        /**
         * Kosakata izin gerbang dipendekkan untuk baris, TIDAK diterjemahkan
         * ulang: nilai yang sama dipakai judul, tooltip, dan kelas gaya, jadi
         * dua terjemahan berbeda akan terbaca sebagai dua keadaan berbeda.
         */
        labelIzin(status) {
            return {
                AKTIF: 'Aktif',
                MENUNGGU: 'Menunggu',
                DITOLAK: 'Ditolak',
                DICABUT: 'Dicabut',
                KEDALUWARSA: 'Kedaluwarsa',
            }[status] || 'Tidak ada';
        },
        varianIzin(status) {
            if (status === 'AKTIF') return 'success';
            if (status === 'MENUNGGU') return 'warning';
            if (status === 'DITOLAK' || status === 'DICABUT') return 'danger';

            return 'secondary';
        },
        warnaStatus(status) {
            if (status === 'Belum selesai') return 'danger';
            if (status === 'Keluar tak tercatat') return 'warning';
            if (status === 'Di dalam') return 'success';

            return 'brand';
        },
        gantiUrutan({ key, order }) {
            this.urut = key;
            this.arah = order;
            this.paginasi.current_page = 1;
            this.muatTamu();
        },
        gantiUkuranHalaman(ukuran) {
            this.paginasi.per_page = ukuran;
            this.paginasi.current_page = 1;
            this.muatTamu();
        },
        gantiHalaman(halaman) {
            this.paginasi.current_page = halaman;
            this.muatTamu();
        },
        async muatTamu() {
            if (this.domain === 'permit') return;

            this.memuatTamu = true;
            try {
                const { data } = await axios.get(this.endpointTamu.papan_tamu, {
                    params: {
                        page: this.paginasi.current_page,
                        limit: this.paginasi.per_page,
                        cari: this.cari || undefined,
                        urut: this.urut,
                        arah: this.arah,
                    },
                });
                if (data && data.success) {
                    this.barisTamu = data.result?.baris ?? [];
                    this.paginasi = data.result?.paginasi ?? this.paginasi;
                }
            } catch (error) {
                this.pesanGalat = error?.response?.data?.message || 'Gagal memuat daftar tamu.';
            } finally {
                this.memuatTamu = false;
            }
        },
        bukaDetailTamu(sumber) {
            // HcisTable memancarkan { event, row, index }; tombol Detail memanggil
            // dengan barisnya LANGSUNG. Menerima satu bentuk saja membuat klik
            // pada BARIS membuka modal kosong tanpa satu pun galat — null adalah
            // nilai yang sah bagi prop-nya, jadi tidak ada yang mengeluh.
            const row = sumber?.row ?? sumber;
            if (!row) return;
            this.idTamuDetail = row.Id ?? row.Id_Transaksi_Tamu ?? row.No_Transaksi ?? null;
            this.dialogTamuDetail = true;
        },
        bukaTautan(tautan) {
            this.menuju = tautan;
            window.location.href = tautan;
        },
        jam(waktu) {
            return waktu ? waktu.slice(11, 16) : '-';
        },
        bukaDetailPermit(sumber) {
            const row = sumber?.row ?? sumber;
            if (!row) return;
            this.idPermitDetail = row.Id ?? row.Id_Transaksi_Permit ?? row.No_Transaksi ?? null;
            this.dialogPermitDetail = true;
        },
        async muat() {
            this.memuat = true;
            try {
                const { data } = await axios.get(this.endpointTamu.papan_harian, {
                    params: this.domain ? { domain: this.domain } : {},
                });
                if (data && data.success) {
                    this.papan = { ...this.papanKosong(), ...(data.result || {}) };
                }
            } catch (error) {
                this.pesanGalat = error?.response?.data?.message || 'Gagal memuat papan harian.';
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
/* ── Penanda tipe, asal, dan keadaan permit ──────────────────────────────
   Satu bentuk untuk satu fakta, dipakai di tabel maupun kartu. Penanda yang
   berbentuk lain di tiap tampilan terbaca sebagai hal yang berbeda. */
.ph-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.ph-tag--b2b {
    color: #3730a3;
    background: #e0e7ff;
    border: 1px solid #c7d2fe;
}

.ph-tag--reguler {
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.ph-tag--mitra,
.ph-tag--asal-mitra {
    color: #9a3412;
    background: #ffedd5;
    border: 1px solid #fed7aa;
}

.ph-tag--asal-karyawan {
    color: #3730a3;
    background: #e0e7ff;
    border: 1px solid #c7d2fe;
}

.ph-tag--asal-tamu {
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
}

.ph-tag--izin-success {
    color: #065f46;
    background: #d1fae5;
    border: 1px solid #a7f3d0;
}

.ph-tag--izin-warning {
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
}

.ph-tag--izin-danger {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.ph-tag--izin-secondary {
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.ph-mini-pill--klik {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    border: 1px solid var(--hcis-slate-300, #cbd5e1);
    background: var(--hcis-slate-50, #f8fafc);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.ph-mini-pill--klik:hover {
    background: var(--hcis-indigo-50, #eef2ff);
    border-color: var(--hcis-indigo-300, #a5b4fc);
}

/* ── Manifes rombongan ───────────────────────────────────────────────── */
.ph-manifes__ringkas {
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, #64748b);
    margin-bottom: 0.75rem;
}

.ph-manifes__daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.ph-manifes__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.625rem;
    border-radius: 0.5rem;
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.ph-manifes__item--utama {
    background: #eef2ff;
    border-color: #c7d2fe;
}

.ph-manifes__peran {
    margin-left: auto;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #4338ca;
}

.ph-manifes__catatan {
    margin: 0.75rem 0 0;
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #64748b);
}

.papan-tamu-card__action-btn--izin.is-mati {
    cursor: default;
    opacity: 0.85;
}

.papan-harian {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.papan-harian__header-tools {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.papan-harian__view-toggle {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 2px;
    gap: 2px;
}

.papan-harian__toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    color: var(--hcis-slate-500, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
}

.papan-harian__toggle-btn:hover {
    color: var(--hcis-slate-800, #1e293b);
}

.papan-harian__toggle-btn.is-active {
    background: var(--hcis-white, #ffffff);
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.papan-harian__body-toolbar {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.papan-harian__cari {
    width: 100%;
    min-width: 0;
}

.papan-harian__table-wrapper {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
}

/* Card Grid View for Mobile & Tablets */
.papan-tamu-grid-wrap {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.papan-tamu-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .papan-tamu-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1200px) {
    .papan-tamu-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.papan-tamu-card {
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-width: 0;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.papan-tamu-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -3px rgba(0, 0, 0, 0.08);
    border-color: var(--hcis-slate-300, #cbd5e1);
}

/* Active Guest Emerald Presence Styling */
.papan-tamu-card.is-active-presence {
    border-left: 4px solid var(--hcis-success-500, #10b981) !important;
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.92) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(236, 253, 245, 0.8) 100%) !important;
    border-color: rgba(16, 185, 129, 0.35) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.12) !important;
}

.papan-tamu-card--danger {
    border-left: 4px solid var(--hcis-danger-500, #ef4444) !important;
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.92) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(254, 226, 226, 0.8) 100%) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}

.papan-tamu-card--warning {
    border-left: 4px solid var(--hcis-warning-500, #f59e0b) !important;
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.92) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(254, 243, 199, 0.8) 100%) !important;
    border-color: rgba(245, 158, 11, 0.3) !important;
}

.papan-tamu-card--brand {
    border-left: 4px solid var(--hcis-brand-500, #6366f1);
}

/* 1. Top Meta Row */
.papan-tamu-card__top-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
    width: 100%;
}

.papan-tamu-card__tags-group {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    min-width: 0;
}

.papan-tamu-card__trx {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 4px;
    padding: 0.1rem 0.35rem;
    line-height: 1.25;
}

.papan-tamu-card__manifest-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    color: #4338ca;
    border-radius: 999px;
    padding: 0.05rem 0.4rem;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1.4;
    cursor: pointer;
    transition: all 0.15s ease;
}

.papan-tamu-card__manifest-btn:hover {
    background: #e0e7ff;
    border-color: #a5b4fc;
}

.papan-tamu-card__status-badge {
    flex-shrink: 0;
}

/* 2. Identity Row (Avatar + Name + Instansi) */
.papan-tamu-card__identity {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    width: 100%;
}

.papan-tamu-card__avatar {
    flex-shrink: 0;
}

.papan-tamu-card__identity-info {
    min-width: 0;
    flex-grow: 1;
}

.papan-tamu-card__name {
    font-size: 0.90625rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
    letter-spacing: -0.01em;
    line-height: 1.3;
}

.papan-tamu-card__instansi {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-slate-600, #475569);
    margin-top: 0.15rem;
    min-width: 0;
}

/* 3. Presence Strips */
.papan-tamu-card__presence-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    font-size: 0.71875rem;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.papan-tamu-card__presence-strip--inside {
    background: rgba(236, 253, 245, 0.9);
    border: 1px solid rgba(167, 243, 208, 0.85);
    color: #065f46;
}

.papan-tamu-card__presence-strip--warning {
    background: rgba(254, 242, 242, 0.9);
    border: 1px solid rgba(254, 202, 202, 0.85);
    color: #991b1b;
}

.papan-tamu-card__presence-strip--muted {
    background: rgba(254, 249, 195, 0.75);
    border: 1px solid rgba(253, 230, 138, 0.85);
    color: #854d0e;
}

.papan-tamu-card__pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-success-500, #10b981);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    animation: livePulse 1.8s infinite;
    flex-shrink: 0;
}

.papan-tamu-card__presence-text {
    font-weight: 700;
}

.papan-tamu-card__presence-sub {
    font-size: 0.6875rem;
    font-weight: 600;
    opacity: 0.85;
}

/* 4. Specs & Timing Panel */
.papan-tamu-card__specs-panel {
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.55rem 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.papan-tamu-card__specs-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    min-width: 0;
}

.papan-tamu-card__spec-cell {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    min-width: 0;
}

.papan-tamu-card__spec-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.625rem;
    font-weight: 700;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.papan-tamu-card__spec-val {
    font-size: 0.78125rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
}

.papan-tamu-card__timing-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px dashed var(--hcis-slate-200, #e2e8f0);
    min-width: 0;
    width: 100%;
}

.papan-tamu-card__timing-point {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.papan-tamu-card__timing-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.625rem;
    font-weight: 650;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
}

.papan-tamu-card__timing-val {
    font-size: 0.78125rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
}

.papan-tamu-card__timing-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 0 0.25rem;
}

/* 5. Footer Actions */
.papan-tamu-card__footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    margin-top: 0.1rem;
}

.papan-tamu-card__btn-action {
    flex: 1 1 0;
    min-width: 0;
    width: 100%;
}

/* Mobile-specific adjustments */
@media (max-width: 575.98px) {
    .papan-tamu-card {
        padding: 0.75rem 0.8rem;
        gap: 0.55rem;
        border-radius: 10px;
    }

    .papan-tamu-card__specs-panel {
        padding: 0.5rem 0.55rem;
        gap: 0.45rem;
    }

    .papan-tamu-card__footer {
        flex-direction: row;
        gap: 0.4rem;
    }
}

.papan-harian__bersih {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-2, 0.5rem) 0;
}

.papan-harian__waktu {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    font-size: 0.8125rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

.ph-plate-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    font-weight: 700;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
}

.ph-kategori-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    max-width: 140px;
}

.ph-mini-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.35rem;
    padding: 0 0.4rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-700, #334155);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.ph-mobile-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ph-mobile-card {
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.65rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
    box-sizing: border-box;
    transition: all 0.18s ease;
}

.ph-mobile-card--interactive {
    cursor: pointer;
}

.ph-mobile-card--interactive:hover,
.ph-mobile-card--interactive:focus-visible {
    background: #ffffff;
    border-color: var(--hcis-brand-300, #a5b4fc);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}

.ph-mobile-card--interactive:active {
    transform: scale(0.99);
}

.font-tabular {
    font-variant-numeric: tabular-nums;
}

@keyframes livePulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.6;
        transform: scale(0.9);
    }
}

/* Responsive Stat Cards: Single-row Horizontal Scroll on Mobile & Tablet */
@media (max-width: 991.98px) {
    .papan-harian__stats :deep(.hcis-stat-group) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        gap: 0.65rem !important;
        padding: 2px 2px 0.35rem 2px !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        scroll-snap-type: x mandatory !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .papan-harian__stats :deep(.hcis-stat-group)::-webkit-scrollbar {
        display: none !important;
    }

    .papan-harian__stats :deep(.hcis-stat) {
        flex: 0 0 148px !important;
        min-width: 148px !important;
        max-width: 148px !important;
        scroll-snap-align: start !important;
        padding: 0.65rem 0.75rem !important;
        min-height: 5rem !important;
        height: auto !important;
        border-radius: 12px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    .papan-harian__stats :deep(.hcis-stat__compact-wrap) {
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        height: 100% !important;
        width: 100% !important;
        overflow: hidden !important;
    }

    .papan-harian__stats :deep(.hcis-stat__compact-wrap > div:first-child) {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.4rem !important;
        width: 100% !important;
        margin-bottom: 0.35rem !important;
        overflow: hidden !important;
    }

    .papan-harian__stats :deep(.hcis-stat__icon-box) {
        width: 1.65rem !important;
        height: 1.65rem !important;
        font-size: 0.8125rem !important;
        border-radius: 6px !important;
        flex-shrink: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .papan-harian__stats :deep(.hcis-stat__label) {
        font-size: 0.6875rem !important;
        font-weight: 700 !important;
        line-height: 1.25 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        color: var(--hcis-slate-700, #334155) !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .papan-harian__stats :deep(.hcis-stat__value-row) {
        display: flex !important;
        align-items: baseline !important;
        justify-content: flex-start !important;
        margin-top: auto !important;
        width: 100% !important;
    }

    .papan-harian__stats :deep(.hcis-stat__value) {
        font-size: 1.2rem !important;
        font-weight: 850 !important;
        line-height: 1 !important;
    }

    .papan-harian__stats :deep(.hcis-stat__suffix) {
        display: inline !important;
        font-size: 0.65625rem !important;
        font-weight: 600 !important;
        color: var(--hcis-slate-500, #64748b) !important;
        margin-left: 0.25rem !important;
    }
}
</style>
