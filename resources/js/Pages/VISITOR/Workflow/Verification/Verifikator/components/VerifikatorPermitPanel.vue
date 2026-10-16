<template>
    <div class="vp-panel">
        <ToggleSection
            layout="sidebar"
            title="Permit Kendaraan"
            storage-key="verifikator-permit"
            :tabs="subTabs"
            :active-tab="activeSubTab"
            @tab-change="onSubTabChange"
        >
            <!-- ============================================================= -->
            <!-- 1. TAB ANTREAN (PENDING REVIEW)                               -->
            <!-- ============================================================= -->
            <template #antrian>
                <div class="vp-content">
                    <!-- Panel Subheader -->
                    <div class="vp-panel-header">
                        <div class="vp-panel-header__info">
                            <div class="vp-panel-header__title-row">
                                <h3 class="vp-panel-header__title">Antrean Verifikasi Permit</h3>
                                <HcisBadge
                                    v-if="!antrianLoading"
                                    :variant="antrianTotal > 0 ? 'warning' : 'neutral'"
                                    appearance="soft"
                                    size="sm"
                                    :dot="antrianTotal > 0"
                                    :pulse="antrianTotal > 0"
                                >
                                    {{ antrianTotal > 0 ? `${antrianTotal} Pengajuan Menunggu` : 'Semua Antrean Selesai' }}
                                </HcisBadge>
                            </div>
                            <p class="vp-panel-header__desc">
                                Validasi kelengkapan berkas, foto fisik, dan checklist kendaraan pemohon sebelum disetujui.
                            </p>
                        </div>
                        <div class="vp-panel-header__actions">
                            <HcisButton
                                variant="ghost"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                :loading="antrianLoading"
                                title="Segarkan data antrean"
                                @click="fetchAntrian"
                            >
                                Segarkan
                            </HcisButton>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="vp-filter-section">
                        <HcisFilterBar
                            :search="antrianFilters.search"
                            search-placeholder="Cari nama pemohon, kode, no. transaksi, plat, model..."
                            :filters="antrianFilterConfig"
                            :filter-values="antrianFilters"
                            variant="flat"
                            sentiment="brand"
                            size="sm"
                            :collapsible="true"
                            show-active-tags
                            @update:search="onAntrianSearch"
                            @filter-change="onAntrianFilterChange"
                            @reset="resetAntrianFilters"
                        />
                    </div>

                    <!-- Main Content Area -->
                    <div class="vp-main-surface">
                        <!-- Loading Skeleton -->
                        <div v-if="antrianLoading" class="vp-state-wrap vp-state-loading">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <HcisSkeleton type="text" width="30%" height="1.25rem" />
                                <HcisSkeleton type="text" width="15%" height="1.25rem" />
                            </div>
                            <HcisSkeleton type="text" :lines="5" height="1rem" class="mb-2" />
                            <HcisSkeleton type="text" :lines="3" height="1rem" />
                        </div>

                        <!-- Error State -->
                        <div v-else-if="antrianError" class="vp-state-wrap">
                            <HcisAlert
                                variant="danger"
                                appearance="soft"
                                size="md"
                                title="Gagal memuat antrean permit"
                                :description="antrianError"
                            >
                                <template #actions>
                                    <HcisButton
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="arrow-clockwise"
                                        @click="fetchAntrian"
                                    >
                                        Coba Lagi
                                    </HcisButton>
                                </template>
                            </HcisAlert>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="!antrianData.length" class="vp-state-wrap">
                            <HcisEmptyState
                                v-if="antrianFilters.search || antrianFilters.tipe"
                                title="Tidak ada antrean yang cocok"
                                description="Coba ubah kata kunci pencarian atau sesuaikan filter tipe kendaraan."
                                icon="search"
                                variant="flat"
                                size="md"
                            >
                                <template #actions>
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="arrow-counterclockwise"
                                        @click="resetAntrianFilters"
                                    >
                                        Reset Filter
                                    </HcisButton>
                                </template>
                            </HcisEmptyState>
                            <HcisEmptyState
                                v-else
                                title="Semua antrean telah diproses"
                                description="Saat ini tidak ada pengajuan permit kendaraan baru yang membutuhkan verifikasi."
                                icon="patch-check-fill"
                                variant="flat"
                                size="md"
                            />
                        </div>

                        <!-- Data List -->
                        <template v-else>
                            <!-- Desktop Table -->
                            <div class="vp-table-responsive d-none d-lg-block">
                                <table class="vp-table">
                                    <thead>
                                        <tr>
                                            <th class="vp-col-pemohon">Pemohon & Kode</th>
                                            <th class="vp-col-kategori">Kategori Permit</th>
                                            <th class="vp-col-kendaraan">No. Transaksi & Kendaraan</th>
                                            <th class="vp-col-waktu">Plat & Tgl Pengajuan</th>
                                            <th class="vp-col-aksi text-end">Aksi Verifikasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in antrianData"
                                            :key="row.Id_Transaksi_Permit"
                                            class="vp-table-row"
                                            @click="openReview(row)"
                                        >
                                            <!-- Pemohon & Kode -->
                                            <td>
                                                <div class="vp-user-cell">
                                                    <HcisAvatar
                                                        :name="row.Nama_Pemohon_Tampil || row.Nama_Pemohon"
                                                        size="sm"
                                                        shape="rounded"
                                                        class="flex-shrink-0"
                                                    />
                                                    <div class="vp-user-info">
                                                        <div class="vp-user-name" :title="row.Nama_Pemohon_Tampil || row.Nama_Pemohon">
                                                            {{ row.Nama_Pemohon_Tampil || row.Nama_Pemohon || '-' }}
                                                        </div>
                                                        <div class="vp-user-meta">
                                                            <!-- Label siap-baca dari server. 'API#233' adalah kunci
                                                                 internal dan tidak berarti apa-apa bagi admin. -->
                                                            <span
                                                                class="vp-mono-badge"
                                                                :class="{ 'vp-mono-badge--aplikasi': row.Permit_Untuk_Tamu === 'Y' }"
                                                            >{{ row.Label_Kode_Pemohon || row.Kode_Karyawan_Pemohon || '-' }}</span>
                                                            <span
                                                                v-if="row.No_Transaksi_Tamu"
                                                                class="vp-tamu-link-pill"
                                                                title="Klik untuk melihat detail registrasi tamu"
                                                                @click.stop="openTamuModal(row.No_Transaksi_Tamu)"
                                                            >
                                                                <i class="bi bi-link-45deg me-0.5" />#{{ row.No_Transaksi_Tamu }}
                                                            </span>
                                                            <template v-if="row.Permit_Untuk_Tamu !== 'Y'">
                                                                <span class="vp-sep-dot">·</span>
                                                                <span
                                                                    class="vp-dept-text"
                                                                    :title="row.Keterangan_Pemohon_Tampil || row.Departemen"
                                                                >{{ row.Keterangan_Pemohon_Tampil || row.Departemen || '-' }}</span>
                                                            </template>
                                                            <!-- Tanpa lencana ini verifikator membuka pengajuan yang
                                                                 tidak punya foto STNK/SIM dan menyimpulkan pemohonnya
                                                                 lalai, lalu menolaknya. -->
                                                            <span
                                                                v-if="row.Sumber_Pengajuan === 'API'"
                                                                class="vp-mitra-badge"
                                                                :title="'Diajukan lewat API oleh ' + (row.Nama_Mitra || 'mitra')"
                                                             >{{ row.Nama_Mitra || 'Mitra API' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Kategori Permit -->
                                            <td>
                                                <div class="vp-kategori-cell">
                                                    <span
                                                        class="vp-kategori-pill"
                                                        :style="getCategoryPillStyle(row.Kode_Warna)"
                                                    >
                                                        <span
                                                            class="vp-kategori-dot"
                                                            :style="{ background: row.Kode_Warna || '#6366f1' }"
                                                        />
                                                        <span class="vp-kategori-text">{{ row.Nama_Kategori || 'Permit' }}</span>
                                                    </span>
                                                    <div v-if="row.Deskripsi_Kategori" class="vp-kategori-desc" :title="row.Deskripsi_Kategori">
                                                        {{ row.Deskripsi_Kategori }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- No. Transaksi & Kendaraan -->
                                            <td>
                                                <div class="vp-vehicle-cell">
                                                    <div class="vp-tx-code" :title="`No. Transaksi: ${row.No_Transaksi}`">
                                                        {{ row.No_Transaksi }}
                                                    </div>
                                                    <div class="vp-vehicle-name">
                                                        {{ row.Nama_Model_Manual || '-' }}
                                                        <span class="vp-sep-dot">·</span>
                                                        <span class="vp-vehicle-type">{{ row.Nama_Vehicle_Type || '-' }}</span>
                                                    </div>
                                                    <div v-if="row.Warna_Manual || row.Tahun_Kendaraan" class="vp-vehicle-sub">
                                                        {{ row.Warna_Manual || '-' }}
                                                        <span v-if="row.Tahun_Kendaraan">({{ row.Tahun_Kendaraan }})</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Plat & Tgl Pengajuan -->
                                            <td>
                                                <div class="vp-date-cell">
                                                    <div class="vp-plate-badge" :title="row.No_Plat_Kendaraan">
                                                        <i class="bi bi-card-text me-1 text-slate-400" />
                                                        <span>{{ row.No_Plat_Kendaraan || '-' }}</span>
                                                    </div>
                                                    <div class="vp-date-main mt-0.5">
                                                        <i class="bi bi-calendar-event me-1 text-slate-400" />
                                                        <span>{{ formatDate(row.Tanggal_Pengajuan) }}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-end" @click.stop>
                                                <HcisButton
                                                    label="Review Permit"
                                                    leading-icon="clipboard-check-fill"
                                                    size="sm"
                                                    variant="primary"
                                                    @click="openReview(row)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile & Tablet Cards -->
                            <div class="vp-card-list d-lg-none">
                                <HcisCard
                                    v-for="row in antrianData"
                                    :key="row.Id_Transaksi_Permit"
                                    variant="default"
                                    size="sm"
                                    hoverable
                                    interactive
                                    class="vp-grid-card"
                                    @click="openReview(row)"
                                >
                                    <div class="vp-card-content">
                                        <!-- Dedicated Top Bar: Category Pill + Transaksi Code -->
                                        <div class="d-flex align-items-center justify-content-between gap-2 min-w-0">
                                            <span
                                                class="vp-kategori-pill"
                                                :style="getCategoryPillStyle(row.Kode_Warna)"
                                            >
                                                <span
                                                    class="vp-kategori-dot"
                                                    :style="{ background: row.Kode_Warna || '#6366f1' }"
                                                />
                                                <span class="vp-kategori-text">{{ row.Nama_Kategori || 'Permit' }}</span>
                                            </span>
                                            <span class="vp-tx-code" :title="`No. Transaksi: ${row.No_Transaksi}`">
                                                {{ row.No_Transaksi }}
                                            </span>
                                        </div>

                                        <!-- Identity Row: Avatar + Pemohon Info -->
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <HcisAvatar
                                                :name="row.Nama_Pemohon_Tampil || row.Nama_Pemohon"
                                                size="md"
                                                shape="rounded"
                                                class="flex-shrink-0"
                                            />
                                            <div class="min-w-0 flex-grow-1 overflow-hidden">
                                                <strong
                                                    class="text-slate-900 text-sm fw-bold text-truncate d-block"
                                                    :title="row.Nama_Pemohon_Tampil || row.Nama_Pemohon"
                                                >
                                                    {{ row.Nama_Pemohon_Tampil || row.Nama_Pemohon || '-' }}
                                                </strong>
                                                <div class="text-xs text-muted text-truncate d-flex align-items-center gap-2 mt-0.5">
                                                    <span
                                                        :class="row.Permit_Untuk_Tamu === 'Y' ? 'vp-mono-badge vp-mono-badge--aplikasi' : 'font-tabular'"
                                                    >{{ row.Label_Kode_Pemohon || row.Kode_Karyawan_Pemohon || '-' }}</span>
                                                    <span
                                                        v-if="row.No_Transaksi_Tamu"
                                                        class="vp-tamu-link-pill"
                                                        title="Klik untuk melihat detail registrasi tamu"
                                                        @click.stop="openTamuModal(row.No_Transaksi_Tamu)"
                                                    >
                                                        <i class="bi bi-link-45deg me-0.5" />#{{ row.No_Transaksi_Tamu }}
                                                    </span>
                                                    <template v-if="row.Permit_Untuk_Tamu !== 'Y'">
                                                        <span>·</span>
                                                        <span class="text-truncate">{{ row.Keterangan_Pemohon_Tampil || row.Departemen || '-' }}</span>
                                                    </template>
                                                    <span
                                                        v-if="row.Sumber_Pengajuan === 'API'"
                                                        class="vp-mitra-badge"
                                                    >{{ row.Nama_Mitra || 'Mitra API' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2x2 Specs Grid -->
                                        <div class="vp-card-spec-grid">
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Plat Nomor</span>
                                                <span class="vp-plate-badge text-truncate font-tabular">
                                                    {{ row.No_Plat_Kendaraan || '-' }}
                                                </span>
                                            </div>
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Model & Tipe</span>
                                                <strong class="vp-spec-item__val text-truncate text-slate-800" :title="`${row.Nama_Model_Manual || '-'} · ${row.Nama_Vehicle_Type || '-'}`">
                                                    {{ row.Nama_Model_Manual || '-' }}
                                                </strong>
                                            </div>
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Tgl Pengajuan</span>
                                                <span class="vp-spec-item__val font-tabular text-slate-800">
                                                    {{ formatDate(row.Tanggal_Pengajuan) }}
                                                </span>
                                            </div>
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Jenis Kendaraan</span>
                                                <span class="vp-spec-item__val font-tabular text-slate-700">
                                                    {{ row.Nama_Vehicle_Type || '-' }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Action Button -->
                                        <HcisButton
                                            label="Review Permit"
                                            leading-icon="clipboard-check-fill"
                                            size="sm"
                                            variant="primary"
                                            block
                                            @click.stop="openReview(row)"
                                        />
                                    </div>
                                </HcisCard>
                            </div>
                        </template>
                    </div>

                    <!-- Pagination -->
                    <div v-if="antrianTotal > 0" class="vp-pagination-wrap">
                        <div class="vp-pagination-info">
                            Menampilkan <strong>{{ antrianFrom }}</strong> - <strong>{{ antrianTo }}</strong> dari <strong>{{ antrianTotal }}</strong> data antrean
                        </div>
                        <HcisPagination
                            v-if="antrianTotal > antrianLimit"
                            :current-page="antrianPage"
                            :page-size="antrianLimit"
                            :total="antrianTotal"
                            layout="prev, pager, next"
                            size="sm"
                            @current-change="onAntrianPageChange"
                        />
                    </div>
                </div>
            </template>

            <!-- ============================================================= -->
            <!-- 2. TAB RIWAYAT (PROCESSED PERMITS)                           -->
            <!-- ============================================================= -->
            <template #riwayat>
                <div class="vp-content">
                    <!-- Panel Subheader -->
                    <div class="vp-panel-header">
                        <div class="vp-panel-header__info">
                            <div class="vp-panel-header__title-row">
                                <h3 class="vp-panel-header__title">Riwayat & Log Permit</h3>
                                <HcisBadge
                                    v-if="!riwayatLoading"
                                    variant="neutral"
                                    appearance="soft"
                                    size="sm"
                                >
                                    {{ riwayatTotal }} Total Riwayat
                                </HcisBadge>
                            </div>
                            <p class="vp-panel-header__desc">
                                Arsip keputusan permit kendaraan yang telah disetujui, ditolak, maupun kadaluarsa.
                            </p>
                        </div>
                        <div class="vp-panel-header__actions">
                            <HcisButton
                                variant="ghost"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                :loading="riwayatLoading"
                                title="Segarkan data riwayat"
                                @click="fetchRiwayat"
                            >
                                Segarkan
                            </HcisButton>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="vp-filter-section">
                        <HcisFilterBar
                            :search="riwayatFilters.search"
                            search-placeholder="Cari nama pemohon, kode, no. transaksi, plat..."
                            :filters="riwayatFilterConfig"
                            :filter-values="riwayatFilters"
                            variant="flat"
                            sentiment="brand"
                            size="sm"
                            :collapsible="true"
                            show-active-tags
                            @update:search="onRiwayatSearch"
                            @filter-change="onRiwayatFilterChange"
                            @reset="resetRiwayatFilters"
                        />
                    </div>

                    <!-- Main Content Area -->
                    <div class="vp-main-surface">
                        <!-- Loading Skeleton -->
                        <div v-if="riwayatLoading" class="vp-state-wrap vp-state-loading">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <HcisSkeleton type="text" width="30%" height="1.25rem" />
                                <HcisSkeleton type="text" width="15%" height="1.25rem" />
                            </div>
                            <HcisSkeleton type="text" :lines="5" height="1rem" class="mb-2" />
                            <HcisSkeleton type="text" :lines="3" height="1rem" />
                        </div>

                        <!-- Error State -->
                        <div v-else-if="riwayatError" class="vp-state-wrap">
                            <HcisAlert
                                variant="danger"
                                appearance="soft"
                                size="md"
                                title="Gagal memuat riwayat permit"
                                :description="riwayatError"
                            >
                                <template #actions>
                                    <HcisButton
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="arrow-clockwise"
                                        @click="fetchRiwayat"
                                    >
                                        Coba Lagi
                                    </HcisButton>
                                </template>
                            </HcisAlert>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="!riwayatData.length" class="vp-state-wrap">
                            <HcisEmptyState
                                v-if="riwayatFilters.search || riwayatFilters.status || riwayatFilters.tipe"
                                title="Tidak ada riwayat yang cocok"
                                description="Coba ubah kata kunci pencarian atau sesuaikan filter status."
                                icon="search"
                                variant="flat"
                                size="md"
                            >
                                <template #actions>
                                    <HcisButton
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="arrow-counterclockwise"
                                        @click="resetRiwayatFilters"
                                    >
                                        Reset Filter
                                    </HcisButton>
                                </template>
                            </HcisEmptyState>
                            <HcisEmptyState
                                v-else
                                title="Belum ada riwayat permit"
                                description="Permit yang sudah diverifikasi atau diproses akan tercatat di riwayat ini."
                                icon="archive"
                                variant="flat"
                                size="md"
                            />
                        </div>

                        <!-- Data List -->
                        <template v-else>
                            <!-- Desktop Table -->
                            <div class="vp-table-responsive d-none d-lg-block">
                                <table class="vp-table">
                                    <thead>
                                        <tr>
                                            <th class="vp-col-pemohon">Pemohon & Kode</th>
                                            <th class="vp-col-kategori">Kategori</th>
                                            <th class="vp-col-kendaraan">No. Transaksi & Kendaraan</th>
                                            <th class="vp-col-status">Status & Masa Berlaku</th>
                                            <th class="vp-col-waktu">Plat & Tgl Pengajuan</th>
                                            <th class="vp-col-aksi text-end">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in riwayatData"
                                            :key="row.Id_Transaksi_Permit"
                                            class="vp-table-row"
                                            @click="openReview(row)"
                                        >
                                            <!-- Pemohon & Kode -->
                                            <td>
                                                <div class="vp-user-cell">
                                                    <HcisAvatar
                                                        :name="row.Nama_Pemohon_Tampil || row.Nama_Pemohon"
                                                        size="sm"
                                                        shape="rounded"
                                                        class="flex-shrink-0"
                                                    />
                                                    <div class="vp-user-info">
                                                        <div class="vp-user-name" :title="row.Nama_Pemohon_Tampil || row.Nama_Pemohon">
                                                            {{ row.Nama_Pemohon_Tampil || row.Nama_Pemohon || '-' }}
                                                        </div>
                                                        <div class="vp-user-meta">
                                                            <span
                                                                class="vp-mono-badge"
                                                                :class="{ 'vp-mono-badge--aplikasi': row.Permit_Untuk_Tamu === 'Y' }"
                                                            >{{ row.Label_Kode_Pemohon || row.Kode_Karyawan_Pemohon || '-' }}</span>
                                                            <span
                                                                v-if="row.No_Transaksi_Tamu"
                                                                class="vp-tamu-link-pill"
                                                                title="Klik untuk melihat detail registrasi tamu"
                                                                @click.stop="openTamuModal(row.No_Transaksi_Tamu)"
                                                            >
                                                                <i class="bi bi-link-45deg me-0.5" />#{{ row.No_Transaksi_Tamu }}
                                                            </span>
                                                            <template v-if="row.Permit_Untuk_Tamu !== 'Y'">
                                                                <span class="vp-sep-dot">·</span>
                                                                <span
                                                                    class="vp-dept-text"
                                                                    :title="row.Keterangan_Pemohon_Tampil || row.Departemen"
                                                                >{{ row.Keterangan_Pemohon_Tampil || row.Departemen || '-' }}</span>
                                                            </template>
                                                            <!-- Tanpa lencana ini verifikator membuka pengajuan yang
                                                                 tidak punya foto STNK/SIM dan menyimpulkan pemohonnya
                                                                 lalai, lalu menolaknya. -->
                                                            <span
                                                                v-if="row.Sumber_Pengajuan === 'API'"
                                                                class="vp-mitra-badge"
                                                                :title="'Diajukan lewat API oleh ' + (row.Nama_Mitra || 'mitra')"
                                                            >{{ row.Nama_Mitra || 'Mitra API' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Kategori Permit -->
                                            <td>
                                                <div class="vp-kategori-cell">
                                                    <span
                                                        class="vp-kategori-pill"
                                                        :style="getCategoryPillStyle(row.Kode_Warna)"
                                                    >
                                                        <span
                                                            class="vp-kategori-dot"
                                                            :style="{ background: row.Kode_Warna || '#6366f1' }"
                                                        />
                                                        <span class="vp-kategori-text">{{ row.Nama_Kategori || 'Permit' }}</span>
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- No. Transaksi & Kendaraan -->
                                            <td>
                                                <div class="vp-vehicle-cell">
                                                    <div class="vp-tx-code" :title="`No. Transaksi: ${row.No_Transaksi}`">
                                                        {{ row.No_Transaksi }}
                                                    </div>
                                                    <div class="vp-vehicle-name">
                                                        {{ row.Nama_Model_Manual || '-' }}
                                                        <span class="vp-sep-dot">·</span>
                                                        <span class="vp-vehicle-type">{{ row.Nama_Vehicle_Type || '-' }}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Status & Masa Berlaku -->
                                            <td>
                                                <div class="vp-status-cell">
                                                    <HcisStatusBadge
                                                        :label="statusLabel(row.Status_Permit)"
                                                        :variant="statusBadgeVariant(row.Status_Permit)"
                                                        :icon="statusBadgeIcon(row.Status_Permit)"
                                                        size="sm"
                                                    />
                                                    <!-- Detail Masa Berlaku if Approved -->
                                                    <div
                                                        v-if="row.Tanggal_Mulai_Berlaku && row.Tanggal_Kadaluarsa"
                                                        class="vp-validity-meta"
                                                    >
                                                        <div class="vp-validity-range">
                                                            <i class="bi bi-calendar-check me-1 text-slate-400" />
                                                            <span>{{ formatDate(row.Tanggal_Mulai_Berlaku) }} - {{ formatDate(row.Tanggal_Kadaluarsa) }}</span>
                                                        </div>
                                                        <span
                                                            v-if="row.Sisa_Hari !== null && row.Status_Permit === 'APPROVED'"
                                                            class="vp-days-pill"
                                                            :class="`vp-days-pill--${sisaMasaBerlaku(row).nada}`"
                                                        >
                                                            {{ sisaMasaBerlaku(row).label }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Plat & Tgl Pengajuan -->
                                            <td>
                                                <div class="vp-date-cell">
                                                    <div class="vp-plate-badge" :title="row.No_Plat_Kendaraan">
                                                        <i class="bi bi-card-text me-1 text-slate-400" />
                                                        <span>{{ row.No_Plat_Kendaraan || '-' }}</span>
                                                    </div>
                                                    <div class="vp-date-main mt-0.5">
                                                        <i class="bi bi-calendar-event me-1 text-slate-400" />
                                                        <span>{{ formatDate(row.Tanggal_Pengajuan) }}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-end" @click.stop>
                                                <HcisButton
                                                    label="Detail"
                                                    leading-icon="eye"
                                                    size="sm"
                                                    variant="secondary"
                                                    @click="openReview(row)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile & Tablet Cards -->
                            <div class="vp-card-list d-lg-none">
                                <HcisCard
                                    v-for="row in riwayatData"
                                    :key="row.Id_Transaksi_Permit"
                                    variant="default"
                                    :sentiment="isPermitActive(row) ? 'success' : undefined"
                                    size="sm"
                                    hoverable
                                    interactive
                                    class="vp-grid-card"
                                    :class="{ 'is-active-card': isPermitActive(row) }"
                                    @click="openReview(row)"
                                >
                                    <div class="vp-card-content">
                                        <!-- Dedicated Top Bar: Category Pill + Status Badge -->
                                        <div class="d-flex align-items-center justify-content-between gap-2 min-w-0">
                                             <span
                                                 class="vp-kategori-pill"
                                                 :style="getCategoryPillStyle(row.Kode_Warna)"
                                             >
                                                 <span class="vp-kategori-dot" :style="{ background: row.Kode_Warna || '#6366f1' }" />
                                                 <span class="vp-kategori-text">{{ row.Nama_Kategori || 'Permit' }}</span>
                                             </span>

                                             <HcisStatusBadge
                                                 :label="statusLabel(row.Status_Permit)"
                                                 :variant="statusBadgeVariant(row.Status_Permit)"
                                                 :icon="statusBadgeIcon(row.Status_Permit)"
                                                 size="xs"
                                             />
                                        </div>

                                        <!-- Identity Row: Avatar + Pemohon Info -->
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <HcisAvatar
                                                :name="row.Nama_Pemohon_Tampil || row.Nama_Pemohon"
                                                size="md"
                                                shape="rounded"
                                                class="flex-shrink-0"
                                            />
                                            <div class="min-w-0 flex-grow-1 overflow-hidden">
                                                <strong
                                                    class="text-slate-900 text-sm fw-bold text-truncate d-block"
                                                    :title="row.Nama_Pemohon_Tampil || row.Nama_Pemohon"
                                                >
                                                    {{ row.Nama_Pemohon_Tampil || row.Nama_Pemohon || '-' }}
                                                </strong>
                                                <div class="text-xs text-muted text-truncate d-flex align-items-center gap-2 mt-0.5">
                                                    <span
                                                        :class="row.Permit_Untuk_Tamu === 'Y' ? 'vp-mono-badge vp-mono-badge--aplikasi' : 'font-tabular'"
                                                    >{{ row.Label_Kode_Pemohon || row.Kode_Karyawan_Pemohon || '-' }}</span>
                                                    <span
                                                        v-if="row.No_Transaksi_Tamu"
                                                        class="vp-tamu-link-pill"
                                                        title="Klik untuk melihat detail registrasi tamu"
                                                        @click.stop="openTamuModal(row.No_Transaksi_Tamu)"
                                                    >
                                                        <i class="bi bi-link-45deg me-0.5" />#{{ row.No_Transaksi_Tamu }}
                                                    </span>
                                                    <template v-if="row.Permit_Untuk_Tamu !== 'Y'">
                                                        <span>·</span>
                                                        <span class="text-truncate">{{ row.Keterangan_Pemohon_Tampil || row.Departemen || '-' }}</span>
                                                    </template>
                                                    <span
                                                        v-if="row.Sumber_Pengajuan === 'API'"
                                                        class="vp-mitra-badge"
                                                    >{{ row.Nama_Mitra || 'Mitra API' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2x2 Specs Grid -->
                                        <div class="vp-card-spec-grid">
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Plat Nomor</span>
                                                <span class="vp-plate-badge text-truncate font-tabular">
                                                    {{ row.No_Plat_Kendaraan || '-' }}
                                                </span>
                                            </div>
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Model & Tipe</span>
                                                <strong class="vp-spec-item__val text-truncate text-slate-800" :title="`${row.Nama_Model_Manual || '-'} · ${row.Nama_Vehicle_Type || '-'}`">
                                                    {{ row.Nama_Model_Manual || '-' }}
                                                </strong>
                                            </div>
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">Tgl Pengajuan</span>
                                                <span class="vp-spec-item__val font-tabular text-slate-800">
                                                    {{ formatDate(row.Tanggal_Pengajuan) }}
                                                </span>
                                            </div>
                                            <div class="vp-spec-item">
                                                <span class="vp-spec-item__label">No. Transaksi</span>
                                                <span class="vp-spec-item__val font-tabular text-slate-800 fw-bold">
                                                    {{ row.No_Transaksi }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Validity Box if approved -->
                                        <div
                                            v-if="row.Tanggal_Mulai_Berlaku && row.Tanggal_Kadaluarsa"
                                            class="vp-validity-meta-box p-2 rounded-2"
                                        >
                                            <div class="d-flex align-items-center justify-content-between gap-2 min-w-0">
                                                <div class="d-flex align-items-center gap-1.5 text-xs text-slate-700 font-tabular min-w-0 flex-grow-1 overflow-hidden">
                                                    <HcisIcon name="calendar-check" size="xs" class="text-slate-400 flex-shrink-0" />
                                                    <span class="text-truncate">{{ formatDate(row.Tanggal_Mulai_Berlaku) }} - {{ formatDate(row.Tanggal_Kadaluarsa) }}</span>
                                                </div>
                                                <span
                                                    v-if="row.Sisa_Hari !== null && row.Status_Permit === 'APPROVED'"
                                                    class="vp-days-pill flex-shrink-0"
                                                    :class="`vp-days-pill--${sisaMasaBerlaku(row).nada}`"
                                                >
                                                    {{ sisaMasaBerlaku(row).label }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Action Button -->
                                        <HcisButton
                                            label="Lihat Detail Permit"
                                            leading-icon="eye"
                                            size="sm"
                                            variant="secondary"
                                            block
                                            @click.stop="openReview(row)"
                                        />
                                    </div>
                                </HcisCard>
                            </div>
                        </template>
                    </div>

                    <!-- Pagination -->
                    <div v-if="riwayatTotal > 0" class="vp-pagination-wrap">
                        <div class="vp-pagination-info">
                            Menampilkan <strong>{{ riwayatFrom }}</strong> - <strong>{{ riwayatTo }}</strong> dari <strong>{{ riwayatTotal }}</strong> data riwayat
                        </div>
                        <HcisPagination
                            v-if="riwayatTotal > riwayatLimit"
                            :current-page="riwayatPage"
                            :page-size="riwayatLimit"
                            :total="riwayatTotal"
                            layout="prev, pager, next"
                            size="sm"
                            @current-change="onRiwayatPageChange"
                        />
                    </div>
                </div>
            </template>
        </ToggleSection>

        <!-- Modal Review & Detail Permit -->
        <PermitReviewModal
            v-if="showModal"
            :show="showModal"
            :permit-id="selectedId"
            cetak-tersedia
            @close="showModal = false"
            @approved="onActionDone"
            @rejected="onActionDone"
        />

        <!-- Modal Detail Tamu -->
        <TamuReviewModal
            v-if="showTamuModal"
            :show="showTamuModal"
            :tamu-id="selectedTamuId"
            detail-endpoint="/laporanvisitor-permit-tamu"
            :read-only="true"
            @close="showTamuModal = false"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { defineAsyncComponent } from 'vue';
import ToggleSection from '@/components/VISITOR/VisitorTabsCompat.vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisPagination,
    HcisSkeleton,
    HcisStatusBadge,
} from '@/components/ui';
import PermitReviewModal from './PermitReviewModal.vue';

export default {
    name: 'VerifikatorPermitPanel',
    components: {
        ToggleSection,
        PermitReviewModal,
        TamuReviewModal: defineAsyncComponent(() => import('./TamuReviewModal.vue')),
        HcisAlert,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisEmptyState,
        HcisFilterBar,
        HcisPagination,
        HcisSkeleton,
        HcisStatusBadge,
    },
    emits: ['stats-updated'],
    data() {
        return {
            activeSubTab: 'antrian',
            subTabs: [
                { id: 'antrian', label: 'Antrean Review', icon: 'hourglass-split', count: null },
                { id: 'riwayat', label: 'Riwayat Permit', icon: 'clock-history', count: null },
            ],

            // Antrian state
            antrianData: [],
            antrianLoading: false,
            antrianPage: 1,
            antrianLimit: 15,
            antrianTotal: 0,
            antrianTotalPages: 1,
            antrianFrom: 0,
            antrianTo: 0,
            antrianFilters: { search: '', tipe: '' },
            antrianError: '',

            // Riwayat state
            riwayatData: [],
            riwayatLoading: false,
            riwayatPage: 1,
            riwayatLimit: 15,
            riwayatTotal: 0,
            riwayatTotalPages: 1,
            riwayatFrom: 0,
            riwayatTo: 0,
            riwayatFilters: { search: '', status: '', tipe: '' },
            riwayatError: '',

            // Modal Review
            showModal: false,
            selectedId: null,

            // Modal Detail Tamu
            showTamuModal: false,
            selectedTamuId: null,

            // Master Vehicle Types
            vehicleTypes: [],

            // Debounce timers
            _antrianTimer: null,
            _riwayatTimer: null,
        };
    },
    computed: {
        vehicleTypeOptions() {
            return this.vehicleTypes.map((value) => ({ value, label: value }));
        },
        antrianFilterConfig() {
            return [
                {
                    key: 'tipe',
                    label: 'Tipe kendaraan',
                    type: 'select',
                    placeholder: 'Semua tipe',
                    options: this.vehicleTypeOptions,
                },
            ];
        },
        riwayatFilterConfig() {
            return [
                {
                    key: 'status',
                    label: 'Status permit',
                    type: 'select',
                    placeholder: 'Semua status',
                    options: [
                        { value: 'APPROVED', label: 'Disetujui' },
                        { value: 'REJECTED', label: 'Ditolak' },
                        { value: 'EXPIRED', label: 'Expired' },
                        { value: 'VALIDITY_REQUIRED', label: 'Perlu Set Validitas' },
                        // Riwayat kini memuat yang dicabut: verifikator perlu
                        // bisa menemukan kembali apa yang pernah ia cabut.
                        { value: 'REVOKED', label: 'Dicabut' },
                    ],
                },
                {
                    key: 'tipe',
                    label: 'Tipe kendaraan',
                    type: 'select',
                    placeholder: 'Semua tipe',
                    options: this.vehicleTypeOptions,
                },
            ];
        },
    },
    mounted() {
        this.fetchAntrian();
        this.loadVehicleTypes();
    },
    beforeUnmount() {
        // Pencarian yang masih menunggu debounce tidak boleh memuat ulang daftar sesudah panel ditutup.
        clearTimeout(this._antrianTimer);
        clearTimeout(this._riwayatTimer);
    },
    methods: {
        /**
         * Pil sisa masa berlaku untuk permit APPROVED.
         *
         * Sisa_Hari = DATEDIFF(day, hari ini, Tanggal_Kadaluarsa), jadi di hari
         * TERAKHIR nilainya 0 padahal izinnya masih berlaku sampai jam
         * berakhirnya (mis. 23:59). Dulu 0 ditampilkan "Expired" — permit
         * sehari yang baru disetujui langsung terbaca kedaluwarsa (29 Sep 2026).
         * Status_Permit APPROVED sudah menjamin belum lewat; yang kedaluwarsa
         * berstatus EXPIRED dan pil ini tidak dirender.
         */
        sisaMasaBerlaku(row) {
            const sisa = Number(row?.Sisa_Hari);
            if (sisa > 0) return { label: `Sisa ${sisa} hari`, nada: 'active' };
            if (sisa < 0) return { label: 'Expired', nada: 'expired' };
            const jam = String(row?.Waktu_Berakhir || '').slice(11, 16);
            return { label: jam ? `Berakhir hari ini ${jam}` : 'Berakhir hari ini', nada: 'today' };
        },
        onSubTabChange(tab) {
            this.activeSubTab = tab;
            if (tab === 'antrian' && !this.antrianData.length) this.fetchAntrian();
            if (tab === 'riwayat' && !this.riwayatData.length) this.fetchRiwayat();
        },

        onAntrianSearch(value) {
            this.antrianFilters.search = value;
            this.debouncedFetchAntrian();
        },

        onAntrianFilterChange({ key, value }) {
            this.antrianFilters[key] = value || '';
            this.antrianPage = 1;
            this.fetchAntrian();
        },

        resetAntrianFilters() {
            this.antrianFilters.search = '';
            this.antrianFilters.tipe = '';
            this.antrianPage = 1;
            this.fetchAntrian();
        },

        onRiwayatSearch(value) {
            this.riwayatFilters.search = value;
            this.debouncedFetchRiwayat();
        },

        onRiwayatFilterChange({ key, value }) {
            this.riwayatFilters[key] = value || '';
            this.riwayatPage = 1;
            this.fetchRiwayat();
        },

        resetRiwayatFilters() {
            this.riwayatFilters.search = '';
            this.riwayatFilters.status = '';
            this.riwayatFilters.tipe = '';
            this.riwayatPage = 1;
            this.fetchRiwayat();
        },

        onAntrianPageChange(page) {
            this.antrianPage = page;
            this.fetchAntrian();
        },

        onRiwayatPageChange(page) {
            this.riwayatPage = page;
            this.fetchRiwayat();
        },

        async fetchAntrian() {
            this.antrianLoading = true;
            this.antrianError = '';
            try {
                const params = {
                    mode: 'antrian',
                    page: this.antrianPage,
                    limit: this.antrianLimit,
                    search: this.antrianFilters.search,
                    tipe: this.antrianFilters.tipe,
                };
                const { data: json } = await axios.get('/verifikator-permit-list', { params });
                if (json.success) {
                    this.antrianData = json.result;
                    this.antrianTotal = json.pagination.total;
                    this.antrianTotalPages = json.pagination.total_pages;
                    this.antrianFrom = json.pagination.from;
                    this.antrianTo = json.pagination.to;
                    this.subTabs[0].count = json.pagination.total || null;
                } else {
                    this.antrianError = json.message || 'Terjadi kendala saat memuat antrean.';
                }
            } catch (e) {
                this.antrianError = e.response?.data?.message || e.message || 'Periksa koneksi lalu coba lagi.';
                console.error('fetchAntrian error', e);
            } finally {
                this.antrianLoading = false;
            }
        },

        async fetchRiwayat() {
            this.riwayatLoading = true;
            this.riwayatError = '';
            try {
                const params = {
                    mode: 'riwayat',
                    page: this.riwayatPage,
                    limit: this.riwayatLimit,
                    search: this.riwayatFilters.search,
                    status: this.riwayatFilters.status,
                    tipe: this.riwayatFilters.tipe,
                };
                const { data: json } = await axios.get('/verifikator-permit-list', { params });
                if (json.success) {
                    this.riwayatData = json.result;
                    this.riwayatTotal = json.pagination.total;
                    this.riwayatTotalPages = json.pagination.total_pages;
                    this.riwayatFrom = json.pagination.from;
                    this.riwayatTo = json.pagination.to;
                    this.subTabs[1].count = json.pagination.total || null;
                } else {
                    this.riwayatError = json.message || 'Terjadi kendala saat memuat riwayat.';
                }
            } catch (e) {
                this.riwayatError = e.response?.data?.message || e.message || 'Periksa koneksi lalu coba lagi.';
                console.error('fetchRiwayat error', e);
            } finally {
                this.riwayatLoading = false;
            }
        },

        async loadVehicleTypes() {
            try {
                const { data: json } = await axios.get('/transaksipermit-master');
                if (json.success && json.result.vehicle_types) {
                    this.vehicleTypes = json.result.vehicle_types.map((t) => t.Nama_Tipe_Kendaraan);
                }
            } catch (e) {
                // silently fail
            }
        },

        debouncedFetchAntrian() {
            clearTimeout(this._antrianTimer);
            this._antrianTimer = setTimeout(() => {
                this.antrianPage = 1;
                this.fetchAntrian();
            }, 400);
        },

        debouncedFetchRiwayat() {
            clearTimeout(this._riwayatTimer);
            this._riwayatTimer = setTimeout(() => {
                this.riwayatPage = 1;
                this.fetchRiwayat();
            }, 400);
        },

        openReview(row) {
            this.showModal = false;
            this.selectedId = row.Id_Transaksi_Permit;
            this.$nextTick(() => {
                this.showModal = true;
            });
        },

        openTamuModal(tamuId) {
            if (!tamuId) return;
            this.showTamuModal = false;
            this.selectedTamuId = tamuId;
            this.$nextTick(() => {
                this.showTamuModal = true;
            });
        },

        onActionDone() {
            this.showModal = false;
            this.antrianPage = 1;
            this.riwayatPage = 1;
            this.fetchAntrian();
            this.fetchRiwayat();
            this.$emit('stats-updated');
        },

        getCategoryPillStyle(hex) {
            if (!hex) {
                return {
                    background: 'var(--hcis-slate-100, #f1f5f9)',
                    color: 'var(--hcis-slate-700, #334155)',
                    borderColor: 'var(--hcis-slate-300, #cbd5e1)',
                };
            }
            return {
                background: this.hexToRgba(hex, 0.1),
                color: hex,
                borderColor: this.hexToRgba(hex, 0.28),
            };
        },

        hexToRgba(hex, alpha) {
            if (!hex) return `rgba(100,116,139,${alpha})`;
            let c = hex.replace('#', '');
            if (c.length === 3) {
                c = c.split('').map((char) => char + char).join('');
            }
            const r = parseInt(c.slice(0, 2), 16) || 100;
            const g = parseInt(c.slice(2, 4), 16) || 116;
            const b = parseInt(c.slice(4, 6), 16) || 139;
            return `rgba(${r},${g},${b},${alpha})`;
        },

        formatDate(val) {
            if (!val) return '-';
            return new Date(val).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },

        statusLabel(s) {
            return (
                {
                    APPROVED: 'Disetujui',
                    REJECTED: 'Ditolak',
                    EXPIRED: 'Expired',
                    VALIDITY_REQUIRED: 'Perlu Validitas',
                    REVOKED: 'Dicabut',
                    NO_FLOW: 'No Flow',
                    PENDING: 'Menunggu Approval',
                }[s] || s || '-'
            );
        },

        statusBadgeVariant(s) {
            return {
                APPROVED: 'success',
                REJECTED: 'danger',
                EXPIRED: 'danger',
                VALIDITY_REQUIRED: 'warning',
                REVOKED: 'danger',
                PENDING: 'warning',
                NO_FLOW: 'neutral',
            }[s] || 'neutral';
        },

        statusBadgeIcon(s) {
            return {
                APPROVED: 'check-circle-fill',
                REJECTED: 'x-circle-fill',
                EXPIRED: 'clock-fill',
                VALIDITY_REQUIRED: 'calendar-event',
                REVOKED: 'slash-circle-fill',
                PENDING: 'hourglass-split',
                NO_FLOW: 'slash-circle',
            }[s] || 'info-circle';
        },

        isPermitActive(row) {
            const s = String(row?.Status_Permit || '').toUpperCase();
            return ['APPROVED', 'ACTIVE', 'AKTIF', 'BERLAKU', 'DISETUJUI'].includes(s);
        },
    },
};
</script>

<style scoped>
.vp-mitra-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
    color: #0369a1;
    background: rgba(3, 105, 161, 0.1);
    border: 1px solid rgba(3, 105, 161, 0.22);
}

.vp-panel {
    width: 100%;
}

.vp-content {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
    padding: var(--hcis-space-2, 0.5rem) 0;
}

/* ==========================================================================
   Header Section
   ========================================================================== */
.vp-panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--hcis-space-3, 0.75rem);
    padding-bottom: var(--hcis-space-3, 0.75rem);
    border-bottom: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

.vp-panel-header__title-row {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    flex-wrap: wrap;
}

.vp-panel-header__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    letter-spacing: -0.01em;
}

.vp-panel-header__desc {
    margin: var(--hcis-space-1, 0.25rem) 0 0;
    font-size: 0.8125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    line-height: 1.4;
}

.vp-panel-header__actions {
    flex-shrink: 0;
}

/* ==========================================================================
   Filter Section
   ========================================================================== */
.vp-filter-section {
    width: 100%;
}

/* ==========================================================================
   Main Surface & Table
   ========================================================================== */
.vp-main-surface {
    position: relative;
    width: 100%;
    background: var(--hcis-white, var(--hcis-white));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-xl, 12px);
    overflow: hidden;
    box-shadow: var(--hcis-shadow-xs, 0 1px 2px rgba(0, 0, 0, 0.04));
}

.vp-state-wrap {
    padding: var(--hcis-space-8, 2rem) var(--hcis-space-4, 1rem);
}

.vp-state-loading {
    padding: var(--hcis-space-6, 1.5rem);
}

.vp-table-responsive {
    width: 100%;
    overflow-x: auto;
}

.vp-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.84rem;
}

.vp-table thead th {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    white-space: nowrap;
}

.vp-table-row {
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.1s ease;
}

.vp-table-row td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-100, var(--hcis-slate-100));
    vertical-align: middle;
}

.vp-table-row:last-child td {
    border-bottom: none;
}

.vp-table-row:hover td {
    background-color: var(--hcis-slate-50, var(--hcis-slate-50));
}

/* ==========================================================================
   Table Columns Layout
   ========================================================================== */
.vp-col-pemohon { width: 28%; min-width: 200px; }
.vp-col-kategori { width: 20%; min-width: 150px; }
.vp-col-kendaraan { width: 24%; min-width: 180px; }
.vp-col-status { width: 22%; min-width: 170px; }
.vp-col-waktu { width: 16%; min-width: 130px; }
.vp-col-aksi { width: 12%; min-width: 120px; }

/* ==========================================================================
   Cell Specific Styling
   ========================================================================== */
.vp-user-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.vp-user-info {
    min-width: 0;
}

.vp-user-name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vp-user-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin-top: 0.15rem;
}

.vp-mono-badge {
    font-family: var(--hcis-font-mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    font-weight: 600;
}

.vp-tamu-link-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.08rem 0.4rem;
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono, monospace);
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-700, #4338ca);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.25;
}

.vp-tamu-link-pill:hover {
    background: var(--hcis-brand-100, #e0e7ff);
    border-color: var(--hcis-brand-300, #a5b4fc);
    color: var(--hcis-brand-800, #3730a3);
    transform: translateY(-1px);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.15);
}

.vp-tamu-link-pill:active {
    transform: translateY(0);
}

.vp-dept-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 130px;
}

.vp-sep-dot {
    color: var(--hcis-slate-300, var(--hcis-slate-300));
}

/* Kategori */
.vp-kategori-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    align-items: flex-start;
}

.vp-kategori-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid;
    line-height: 1.2;
    white-space: nowrap;
    max-width: 55%;
    overflow: hidden;
}

.vp-kategori-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vp-kategori-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.vp-kategori-desc {
    font-size: 0.7rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* No. Transaksi & Kendaraan */
.vp-vehicle-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
}

.vp-tx-code {
    display: inline-flex;
    align-items: center;
    font-family: var(--hcis-font-mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
    font-size: 0.75rem;
    font-weight: 750;
    letter-spacing: 0.02em;
    color: var(--hcis-brand-700, #4338ca);
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    border-radius: 6px;
    padding: 0.12rem 0.45rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 170px;
    box-shadow: 0 1px 2px rgba(99, 102, 241, 0.04);
}

.vp-vehicle-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.vp-vehicle-type {
    font-size: 0.72rem;
    font-weight: 500;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.vp-vehicle-sub {
    font-size: 0.7rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

/* Plat & Tgl Pengajuan */
.vp-date-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
}

.vp-plate-badge {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-slate-900, #0f172a);
    color: #ffffff;
    border: 1px solid var(--hcis-slate-700, #334155);
    border-radius: 6px;
    padding: 0.15rem 0.5rem;
    font-family: var(--hcis-font-mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
    font-size: 0.75rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}

.vp-date-main {
    font-size: 0.78125rem;
    font-weight: 500;
    color: var(--hcis-slate-600, #475569);
    white-space: nowrap;
    display: flex;
    align-items: center;
}

/* Status & Validity */
.vp-status-cell {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: flex-start;
}

.vp-validity-meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.7rem;
}

.vp-validity-range {
    display: flex;
    align-items: center;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    white-space: nowrap;
}

.vp-days-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.18rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    width: auto;
    max-width: fit-content;
    box-sizing: border-box;
    flex-shrink: 0;
}

.vp-days-pill--active {
    background: var(--hcis-success-50, var(--hcis-green-50));
    color: var(--hcis-success-700, var(--hcis-green-700));
    border: 1px solid var(--hcis-success-200, var(--hcis-green-200));
}

.vp-days-pill--today {
    background: var(--hcis-warning-50, var(--hcis-amber-50));
    color: var(--hcis-warning-700, var(--hcis-amber-700));
    border: 1px solid var(--hcis-warning-200, var(--hcis-amber-200));
}

.vp-days-pill--expired {
    background: var(--hcis-danger-50, var(--hcis-red-50));
    color: var(--hcis-danger-700, var(--hcis-red-700));
    border: 1px solid var(--hcis-danger-200, var(--hcis-red-200));
}

/* ==========================================================================
   Mobile Cards List (Clean HcisCard Standard)
   ========================================================================== */
.vp-card-list {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-3, 0.75rem) 0;
    width: 100%;
}

.vp-grid-card {
    transition: all 0.15s ease;
    width: 100%;
    box-sizing: border-box;
}

.vp-grid-card.is-active-card {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(16, 185, 129, 0.18), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(5, 150, 105, 0.12), transparent 55%),
        linear-gradient(225deg, rgba(236, 253, 245, 0.9) 0%, rgba(255, 255, 255, 0.98) 35%, rgba(255, 255, 255, 0.95) 65%, rgba(209, 250, 229, 0.6) 100%) !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.12), 0 1px 4px -1px rgba(16, 185, 129, 0.06) !important;
}

.vp-grid-card.is-active-card:hover {
    border-color: rgba(16, 185, 129, 0.6) !important;
    box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.22), 0 4px 10px -2px rgba(16, 185, 129, 0.12) !important;
    transform: translateY(-2px);
}

.vp-card-list {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-2, 0.5rem) 0;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.vp-grid-card {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    transition: all 0.15s ease;
}

.vp-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    width: 100%;
    box-sizing: border-box;
}

.vp-card-spec-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.625rem 0.875rem;
    padding: 0.625rem 0.75rem;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.vp-spec-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.vp-spec-item__label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    line-height: 1.2;
}

.vp-spec-item__val {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
}

.vp-validity-meta-box {
    background: rgba(241, 245, 249, 0.75);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow: hidden;
}

/* ==========================================================================
   Pagination Footer
   ========================================================================== */
.vp-pagination-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-3, 0.75rem);
    flex-wrap: wrap;
    padding: var(--hcis-space-3, 0.75rem) 0;
}

.vp-pagination-info {
    font-size: 0.78rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.vp-pagination-info strong {
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

@media (max-width: 640px) {
    .vp-panel-header {
        flex-wrap: nowrap !important;
        align-items: center !important;
        padding-bottom: 0.5rem;
    }

    .vp-panel-header__info {
        min-width: 0;
        flex: 1 1 auto;
    }

    .vp-panel-header__title-row {
        flex-wrap: nowrap !important;
        overflow: hidden;
        gap: 0.35rem;
    }

    .vp-panel-header__title {
        font-size: 0.92rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .vp-panel-header__desc {
        display: none !important;
    }

    .vp-panel-header__actions {
        flex-shrink: 0;
    }

    .vp-panel-header__actions :deep(.hcis-btn__text) {
        display: none !important;
    }

    .vp-panel-header__actions :deep(.hcis-btn) {
        width: 2rem !important;
        height: 2rem !important;
        padding: 0 !important;
        justify-content: center !important;
    }

    .vp-pagination-wrap {
        padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)) !important;
    }

    .vp-card-list {
        padding-bottom: calc(4rem + env(safe-area-inset-bottom, 0px)) !important;
    }
}

/* Pemohon berupa aplikasi mitra: bukan nomor, jadi tidak bergaya mono. */
.vp-mono-badge--aplikasi {
    font-family: inherit;
    letter-spacing: 0;
}
</style>
