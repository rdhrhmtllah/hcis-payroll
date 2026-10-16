<template>
    <div class="vt-panel">
        <ToggleSection
            layout="sidebar"
            title="Tamu"
            storageKey="verifikator-tamu"
            :tabs="subTabs"
            :activeTab="activeSubTab"
            @tab-change="onSubTabChange"
        >
            <!-- ============================================================= -->
            <!-- 1. TAB: ANTREAN VERIFIKASI TAMU                               -->
            <!-- ============================================================= -->
            <template #antrian>
                <div class="vt-content fade-in">
                    <!-- Subheader -->
                    <div class="vt-subhead">
                        <div class="vt-subhead-left">
                            <div class="d-flex align-items-center gap-2">
                                <h2 class="vt-subhead-title">Antrean Verifikasi Tamu</h2>
                                <HcisBadge
                                    v-if="!antrianLoading"
                                    :variant="antrianTotal > 0 ? 'warning' : 'neutral'"
                                    appearance="soft"
                                    size="sm"
                                    :dot="antrianTotal > 0"
                                    :pulse="antrianTotal > 0"
                                >
                                    {{ antrianTotal > 0 ? `${antrianTotal} Tamu Menunggu` : 'Semua Antrean Selesai' }}
                                </HcisBadge>
                            </div>
                            <p class="vt-subhead-desc">
                                Validasi identitas tamu, instansi asal, penanggung jawab (PIC), dan spesifikasi kendaraan sebelum disetujui.
                            </p>
                        </div>
                        <div class="vt-subhead-actions">
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                :loading="antrianLoading"
                                title="Segarkan data antrean tamu"
                                @click="fetchAntrian"
                            >
                                Segarkan
                            </HcisButton>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="vt-filter-section">
                        <HcisFilterBar
                            :search="antrianFilters.search"
                            search-placeholder="Cari nama tamu, instansi, PIC, no. transaksi..."
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
                    <div class="vt-main-surface">
                        <!-- Loading Skeleton -->
                        <div v-if="antrianLoading" class="vt-state-wrap vt-state-loading">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <HcisSkeleton type="text" width="30%" height="1.25rem" />
                                <HcisSkeleton type="text" width="15%" height="1.25rem" />
                            </div>
                            <HcisSkeleton type="text" :lines="5" height="1rem" class="mb-2" />
                            <HcisSkeleton type="text" :lines="3" height="1rem" />
                        </div>

                        <!-- Error State -->
                        <div v-else-if="antrianError" class="vt-state-wrap">
                            <HcisAlert
                                variant="danger"
                                appearance="soft"
                                size="md"
                                title="Gagal memuat antrean tamu"
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
                        <div v-else-if="!antrianData.length" class="vt-state-wrap">
                            <HcisEmptyState
                                v-if="antrianFilters.search || antrianFilters.date_from || antrianFilters.date_to"
                                title="Tidak ada antrean yang cocok"
                                description="Coba ubah kata kunci pencarian atau sesuaikan rentang tanggal kunjungan."
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
                                description="Saat ini tidak ada pendaftaran kunjungan tamu baru yang membutuhkan verifikasi."
                                icon="patch-check-fill"
                                variant="flat"
                                size="md"
                            />
                        </div>

                        <!-- Data List -->
                        <template v-else>
                            <!-- Desktop Table -->
                            <div class="vt-table-responsive d-none d-lg-block">
                                <table class="vt-table">
                                    <thead>
                                        <tr>
                                            <th class="vt-col-tamu">Informasi Tamu</th>
                                            <th class="vt-col-tujuan">Tujuan & PIC</th>
                                            <th class="vt-col-kendaraan">Kendaraan & Plat</th>
                                            <th class="vt-col-jadwal">Jadwal Kunjungan</th>
                                            <th class="vt-col-jumlah">Jumlah</th>
                                            <th class="vt-col-aksi text-end">Aksi Verifikasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in antrianData"
                                            :key="row.Id_Transaksi_Tamu"
                                            class="vt-table-row"
                                            @click="openReview(row)"
                                        >
                                            <!-- Tamu & Instansi -->
                                            <td>
                                                <div class="vt-user-cell">
                                                    <HcisAvatar
                                                        :name="row.Nama_Tamu"
                                                        size="sm"
                                                        shape="circle"
                                                        class="flex-shrink-0"
                                                    />
                                                    <div class="vt-user-info">
                                                        <div class="vt-user-name" :title="row.Nama_Tamu">
                                                            {{ row.Nama_Tamu || '-' }}
                                                        </div>
                                                        <div class="vt-user-meta">
                                                            <span class="vt-instansi-pill" :title="row.Instansi_Asal">
                                                                <HcisIcon name="building" size="2xs" class="me-1" />
                                                                {{ row.Instansi_Asal || 'Umum' }}
                                                            </span>
                                                            <span v-if="row.No_HP" class="vt-sep-dot">·</span>
                                                            <span v-if="row.No_HP" class="vt-phone-text font-tabular">
                                                                {{ row.No_HP }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Tujuan & PIC -->
                                            <td>
                                                <div class="vt-tujuan-cell">
                                                    <div class="vt-pic-name">
                                                        <HcisIcon name="person-badge" size="2xs" class="text-primary me-1" />
                                                        {{ row.Nama_PIC || row.Kode_Karyawan_PIC || '-' }}
                                                    </div>
                                                    <div class="vt-pic-dept text-xs text-muted">
                                                        {{ row.Divisi_PIC || '-' }}
                                                    </div>
                                                    <div v-if="row.Keperluan" class="vt-keperluan-text text-xs text-slate-600 mt-1" :title="row.Keperluan">
                                                        “{{ truncate(row.Keperluan, 35) }}”
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Kendaraan & Plat -->
                                            <td>
                                                <div v-if="row.No_Plat_Kendaraan" class="vt-kendaraan-cell">
                                                    <span class="vt-plate-badge">{{ row.No_Plat_Kendaraan }}</span>
                                                    <span v-if="row.Jenis_Kendaraan" class="vt-jenis-kendaraan text-xs text-muted mt-1">
                                                        {{ row.Jenis_Kendaraan }}
                                                    </span>
                                                </div>
                                                <span v-else class="text-xs text-muted italic">Tanpa Kendaraan</span>
                                            </td>

                                            <!-- Jadwal Kunjungan -->
                                            <td>
                                                <div class="vt-jadwal-cell font-tabular">
                                                    <div class="vt-tgl-text fw-semibold">
                                                        {{ formatDate(row.Tanggal_Kunjungan) }}
                                                    </div>
                                                    <div class="vt-trx-mono text-xs text-muted">
                                                        {{ row.No_Transaksi }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Jumlah Tamu -->
                                            <td>
                                                <span class="vt-jumlah-pill font-tabular">
                                                    <HcisIcon name="people-fill" size="2xs" class="me-1" />
                                                    {{ row.Jumlah_Tamu || 1 }} Orang
                                                </span>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-end" @click.stop>
                                                <HcisButton
                                                    variant="secondary"
                                                    size="sm"
                                                    leading-icon="search"
                                                    label="Review"
                                                    @click="openReview(row)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Cards -->
                            <div class="vt-mobile-cards d-lg-none">
                                <HcisCard
                                    v-for="row in antrianData"
                                    :key="row.Id_Transaksi_Tamu"
                                    variant="default"
                                    size="sm"
                                    hoverable
                                    interactive
                                    class="vt-grid-card"
                                    @click="openReview(row)"
                                >
                                    <div class="vt-card-content">
                                        <!-- Dedicated Top Bar: Instansi Pill + No. Transaksi -->
                                        <div class="d-flex align-items-center justify-content-between gap-2 min-w-0">
                                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                                <span class="vt-instansi-pill text-2xs">{{ row.Instansi_Asal || 'Umum' }}</span>
                                                <span v-if="row.Jumlah_Tamu > 1" class="vt-mini-pill flex-shrink-0">
                                                    +{{ row.Jumlah_Tamu - 1 }} org
                                                </span>
                                            </div>
                                            <span class="text-xs font-tabular text-slate-500 fw-semibold flex-shrink-0">
                                                #{{ row.No_Transaksi }}
                                            </span>
                                        </div>

                                        <!-- Identity Row: Avatar + Tamu Info -->
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <HcisAvatar
                                                :name="row.Nama_Tamu"
                                                size="md"
                                                variant="brand"
                                                shape="circle"
                                                class="flex-shrink-0"
                                            />
                                            <div class="overflow-hidden min-w-0 flex-grow-1">
                                                <strong class="text-slate-900 text-sm fw-bold text-truncate d-block">
                                                    {{ row.Nama_Tamu || '-' }}
                                                </strong>
                                                <div class="vt-identity-meta">
                                                    <span v-if="row.No_HP" class="font-tabular text-truncate">
                                                        <HcisIcon name="telephone" size="2xs" class="me-1 text-slate-400" />{{ row.No_HP }}
                                                    </span>
                                                    <span v-else class="text-slate-400">Tanpa Kontak</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2x2 Specs Grid -->
                                        <div class="vt-card-spec-grid">
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">PIC Tujuan</span>
                                                <strong class="vt-spec-item__val text-truncate text-slate-800" :title="row.Nama_PIC">
                                                    {{ row.Nama_PIC || row.Kode_Karyawan_PIC || '-' }}
                                                </strong>
                                            </div>
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">Divisi PIC</span>
                                                <span class="vt-spec-item__val text-truncate text-slate-700">
                                                    {{ row.Divisi_PIC || '-' }}
                                                </span>
                                            </div>
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">Tgl Kunjungan</span>
                                                <span class="vt-spec-item__val font-tabular text-slate-800">
                                                    {{ formatDate(row.Tanggal_Kunjungan) }}
                                                </span>
                                            </div>
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">Kendaraan</span>
                                                <span v-if="row.No_Plat_Kendaraan" class="vt-plate-badge text-2xs font-tabular">
                                                    {{ row.No_Plat_Kendaraan }}
                                                </span>
                                                <span v-else class="vt-spec-item__val text-slate-500">Tanpa Kendaraan</span>
                                            </div>
                                        </div>

                                        <div v-if="row.Keperluan" class="vt-keperluan-quote p-2 rounded-3 text-xs">
                                            “{{ truncate(row.Keperluan, 50) }}”
                                        </div>

                                        <!-- Action Button -->
                                        <HcisButton
                                            label="Review Kunjungan"
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
                    <div v-if="antrianTotal > antrianLimit" class="vt-pagination-section">
                        <HcisPagination
                            :current-page="antrianPage"
                            :page-size="antrianLimit"
                            :total="antrianTotal"
                            layout="total, prev, pager, next"
                            size="sm"
                            @current-change="onAntrianPageChange"
                        />
                    </div>
                </div>
            </template>

            <!-- ============================================================= -->
            <!-- 2. TAB: RIWAYAT KUNJUNGAN & LOG KEHADIRAN                      -->
            <!-- ============================================================= -->
            <template #riwayat>
                <div class="vt-content fade-in">
                    <!-- Subheader -->
                    <div class="vt-subhead">
                        <div class="vt-subhead-left">
                            <div class="d-flex align-items-center gap-2">
                                <h2 class="vt-subhead-title">Riwayat Verifikasi Tamu</h2>
                                <HcisBadge
                                    v-if="!riwayatLoading"
                                    variant="neutral"
                                    appearance="soft"
                                    size="sm"
                                >
                                    Total {{ riwayatTotal }} Riwayat
                                </HcisBadge>
                            </div>
                            <p class="vt-subhead-desc">
                                Arsip data riwayat kunjungan tamu, status persetujuan, dan catatan jam check-in/check-out aktual.
                            </p>
                        </div>
                        <div class="vt-subhead-actions">
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                :loading="riwayatLoading"
                                title="Segarkan data riwayat tamu"
                                @click="fetchRiwayat"
                            >
                                Segarkan
                            </HcisButton>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="vt-filter-section">
                        <HcisFilterBar
                            :search="riwayatFilters.search"
                            search-placeholder="Cari nama tamu, instansi, PIC, no. transaksi..."
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
                    <div class="vt-main-surface">
                        <!-- Loading Skeleton -->
                        <div v-if="riwayatLoading" class="vt-state-wrap vt-state-loading">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <HcisSkeleton type="text" width="30%" height="1.25rem" />
                                <HcisSkeleton type="text" width="15%" height="1.25rem" />
                            </div>
                            <HcisSkeleton type="text" :lines="5" height="1rem" class="mb-2" />
                            <HcisSkeleton type="text" :lines="3" height="1rem" />
                        </div>

                        <!-- Error State -->
                        <div v-else-if="riwayatError" class="vt-state-wrap">
                            <HcisAlert
                                variant="danger"
                                appearance="soft"
                                size="md"
                                title="Gagal memuat riwayat tamu"
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
                        <div v-else-if="!riwayatData.length" class="vt-state-wrap">
                            <HcisEmptyState
                                v-if="riwayatFilters.search || riwayatFilters.status || riwayatFilters.date_from || riwayatFilters.date_to"
                                title="Tidak ada riwayat yang cocok"
                                description="Coba ubah kata kunci pencarian atau sesuaikan opsi filter status/tanggal."
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
                                title="Belum ada riwayat kunjungan tamu"
                                description="Kunjungan tamu yang telah diverifikasi atau selesai akan tersimpan di riwayat ini."
                                icon="archive"
                                variant="flat"
                                size="md"
                            />
                        </div>

                        <!-- Data List -->
                        <template v-else>
                            <!-- Desktop Table -->
                            <div class="vt-table-responsive d-none d-lg-block">
                                <table class="vt-table">
                                    <thead>
                                        <tr>
                                            <th class="vt-col-tamu">Informasi Tamu</th>
                                            <th class="vt-col-tujuan">Tujuan & PIC</th>
                                            <th class="vt-col-jadwal">Tgl Kunjungan</th>
                                            <th class="vt-col-status">Status Kunjungan</th>
                                            <th class="vt-col-log">Log Kehadiran</th>
                                            <th class="vt-col-aksi text-end">Rincian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in riwayatData"
                                            :key="row.Id_Transaksi_Tamu"
                                            class="vt-table-row"
                                            @click="openReview(row)"
                                        >
                                            <!-- Tamu & Instansi -->
                                            <td>
                                                <div class="vt-user-cell">
                                                    <HcisAvatar
                                                        :name="row.Nama_Tamu"
                                                        size="sm"
                                                        shape="circle"
                                                        class="flex-shrink-0"
                                                    />
                                                    <div class="vt-user-info">
                                                        <div class="vt-user-name" :title="row.Nama_Tamu">
                                                            {{ row.Nama_Tamu || '-' }}
                                                        </div>
                                                        <div class="vt-user-meta">
                                                            <span class="vt-instansi-pill" :title="row.Instansi_Asal">
                                                                <HcisIcon name="building" size="2xs" class="me-1" />
                                                                {{ row.Instansi_Asal || 'Umum' }}
                                                            </span>
                                                            <span v-if="row.Jumlah_Tamu > 1" class="vt-sep-dot">·</span>
                                                            <span v-if="row.Jumlah_Tamu > 1" class="vt-phone-text">
                                                                {{ row.Jumlah_Tamu }} Orang
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Tujuan & PIC -->
                                            <td>
                                                <div class="vt-tujuan-cell">
                                                    <div class="vt-pic-name">
                                                        <HcisIcon name="person-badge" size="2xs" class="text-primary me-1" />
                                                        {{ row.Nama_PIC || row.Kode_Karyawan_PIC || '-' }}
                                                    </div>
                                                    <div class="vt-pic-dept text-xs text-muted">
                                                        {{ row.Divisi_PIC || '-' }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Jadwal Kunjungan -->
                                            <td>
                                                <div class="vt-jadwal-cell font-tabular">
                                                    <div class="vt-tgl-text fw-semibold">
                                                        {{ formatDate(row.Tanggal_Kunjungan) }}
                                                    </div>
                                                    <div class="vt-trx-mono text-xs text-muted">
                                                        {{ row.No_Transaksi }}
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Status -->
                                            <td>
                                                <HcisStatusBadge
                                                    :label="statusLabel(row.Status_Kunjungan)"
                                                    :variant="statusBadgeVariant(row.Status_Kunjungan)"
                                                    :icon="statusBadgeIcon(row.Status_Kunjungan)"
                                                    size="sm"
                                                    appearance="subtle"
                                                />
                                            </td>

                                            <!-- Log Kehadiran -->
                                            <td>
                                                <div class="vt-presence-log font-tabular text-xs">
                                                    <div v-if="row.Waktu_Aktual_Masuk" class="d-flex align-items-center gap-1 text-slate-700">
                                                        <HcisIcon name="box-arrow-in-right" size="2xs" class="text-primary" />
                                                        <span>Masuk: {{ formatTimeOnly(row.Waktu_Aktual_Masuk) }}</span>
                                                    </div>
                                                    <div v-if="row.Waktu_Aktual_Keluar" class="d-flex align-items-center gap-1 text-slate-700 mt-0.5">
                                                        <HcisIcon name="box-arrow-right" size="2xs" class="text-rose-600" />
                                                        <span>Keluar: {{ formatTimeOnly(row.Waktu_Aktual_Keluar) }}</span>
                                                    </div>
                                                    <span v-if="!row.Waktu_Aktual_Masuk && !row.Waktu_Aktual_Keluar" class="text-muted italic">
                                                        Belum Check-In
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-end" @click.stop>
                                                <HcisButton
                                                    variant="secondary"
                                                    size="sm"
                                                    leading-icon="eye"
                                                    label="Detail"
                                                    @click="openReview(row)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Cards -->
                            <div class="vt-mobile-cards d-lg-none">
                                <HcisCard
                                    v-for="row in riwayatData"
                                    :key="row.Id_Transaksi_Tamu"
                                    variant="default"
                                    :sentiment="isTamuActive(row) ? 'success' : undefined"
                                    size="sm"
                                    hoverable
                                    interactive
                                    class="vt-grid-card"
                                    :class="{ 'is-active-card': isTamuActive(row) }"
                                    @click="openReview(row)"
                                >
                                    <div class="vt-card-content">
                                        <!-- Dedicated Top Bar: Instansi Pill & Group Pill + Status Badge -->
                                        <div class="d-flex align-items-center justify-content-between gap-2 min-w-0">
                                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                                <span class="vt-instansi-pill text-2xs">{{ row.Instansi_Asal || 'Umum' }}</span>
                                                <span v-if="row.Jumlah_Tamu > 1" class="vt-mini-pill flex-shrink-0">
                                                    +{{ row.Jumlah_Tamu - 1 }} org
                                                </span>
                                            </div>

                                            <HcisStatusBadge
                                                :label="statusLabel(row.Status_Kunjungan)"
                                                :variant="statusBadgeVariant(row.Status_Kunjungan)"
                                                :icon="statusBadgeIcon(row.Status_Kunjungan)"
                                                size="xs"
                                            />
                                        </div>

                                        <!-- Identity Row: Avatar + Tamu Info -->
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <HcisAvatar
                                                :name="row.Nama_Tamu"
                                                size="md"
                                                variant="brand"
                                                shape="circle"
                                                class="flex-shrink-0"
                                            />
                                            <div class="overflow-hidden min-w-0 flex-grow-1">
                                                <strong class="text-slate-900 text-sm fw-bold text-truncate d-block">
                                                    {{ row.Nama_Tamu || '-' }}
                                                </strong>
                                                <div class="vt-identity-meta">
                                                    <span v-if="row.No_HP" class="font-tabular text-truncate">
                                                        <HcisIcon name="telephone" size="2xs" class="me-1 text-slate-400" />{{ row.No_HP }}
                                                    </span>
                                                    <span v-else class="text-slate-400">Tanpa Kontak</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2x2 Specs Grid -->
                                        <div class="vt-card-spec-grid">
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">PIC Tujuan</span>
                                                <strong class="vt-spec-item__val text-truncate text-slate-800" :title="row.Nama_PIC">
                                                    {{ row.Nama_PIC || row.Kode_Karyawan_PIC || '-' }}
                                                </strong>
                                            </div>
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">Divisi PIC</span>
                                                <span class="vt-spec-item__val text-truncate text-slate-700">
                                                    {{ row.Divisi_PIC || '-' }}
                                                </span>
                                            </div>
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">Tgl Kunjungan</span>
                                                <span class="vt-spec-item__val font-tabular text-slate-800">
                                                    {{ formatDate(row.Tanggal_Kunjungan) }}
                                                </span>
                                            </div>
                                            <div class="vt-spec-item">
                                                <span class="vt-spec-item__label">No. Transaksi</span>
                                                <span class="vt-spec-item__val font-tabular text-slate-600">
                                                    #{{ row.No_Transaksi }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Presence Strip if available -->
                                        <div v-if="row.Waktu_Aktual_Masuk || row.Waktu_Aktual_Keluar" class="vt-presence-box p-2 rounded-2 font-tabular">
                                            <div class="d-flex justify-content-between align-items-center text-xs">
                                                <div v-if="row.Waktu_Aktual_Masuk" class="d-flex align-items-center gap-1.5 min-w-0 text-slate-700">
                                                    <HcisIcon name="box-arrow-in-right" size="xs" class="text-primary flex-shrink-0" />
                                                    <span class="text-truncate">Masuk: <strong>{{ formatTimeOnly(row.Waktu_Aktual_Masuk) }}</strong></span>
                                                </div>
                                                <div v-if="row.Waktu_Aktual_Keluar" class="d-flex align-items-center gap-1.5 min-w-0 ms-auto text-slate-700">
                                                    <HcisIcon name="box-arrow-right" size="xs" class="text-rose-600 flex-shrink-0" />
                                                    <span class="text-truncate">Keluar: <strong>{{ formatTimeOnly(row.Waktu_Aktual_Keluar) }}</strong></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Button -->
                                        <HcisButton
                                            label="Lihat Detail Kunjungan"
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
                    <div v-if="riwayatTotal > riwayatLimit" class="vt-pagination-section">
                        <HcisPagination
                            :current-page="riwayatPage"
                            :page-size="riwayatLimit"
                            :total="riwayatTotal"
                            layout="total, prev, pager, next"
                            size="sm"
                            @current-change="onRiwayatPageChange"
                        />
                    </div>
                </div>
            </template>
        </ToggleSection>

        <!-- Modern Review / Detail Modal -->
        <TamuReviewModal
            v-if="showReviewModal"
            :show="showReviewModal"
            :tamu-id="selectedId"
            :detail-endpoint="`/${apiPrefix}`"
            :action-endpoint="`/${apiPrefix}`"
            :boleh-buka-permit="bolehBukaPermit"
            @close="showReviewModal = false"
            @approved="onActionDone"
            @rejected="onActionDone"
        />
    </div>
</template>

<script>
import axios from 'axios';
import ToggleSection from '@/components/VISITOR/VisitorTabsCompat.vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisStatusBadge,
} from '@/components/ui';
import TamuReviewModal from './TamuReviewModal.vue';

export default {
    name: 'VerifikatorTamuPanel',
    components: {
        ToggleSection,
        TamuReviewModal,
        HcisAlert,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisEmptyState,
        HcisFilterBar,
        HcisIcon,
        HcisPagination,
        HcisSkeleton,
        HcisStatusBadge,
    },
    emits: ['stats-updated'],
    props: {
        bolehBukaPermit: { type: Boolean, default: false },
        // Ditetapkan halaman (rute): antrean Regular dan B2B terpisah.
        tipe: { type: String, default: 'REGULAR' },
        apiPrefix: { type: String, default: 'verifikator-tamu' },
    },
    data() {
        return {
            activeSubTab: 'antrian',
            subTabs: [
                { id: 'antrian', label: 'Antrean', icon: 'bi bi-hourglass-split', count: null },
                { id: 'riwayat', label: 'Riwayat', icon: 'bi bi-clock-history', count: null },
            ],

            antrianData: [],
            antrianLoading: false,
            antrianPage: 1,
            antrianLimit: 15,
            antrianTotal: 0,
            antrianTotalPages: 1,
            antrianFilters: { search: '', date_from: '', date_to: '' },
            antrianError: '',

            riwayatData: [],
            riwayatLoading: false,
            riwayatPage: 1,
            riwayatLimit: 15,
            riwayatTotal: 0,
            riwayatTotalPages: 1,
            riwayatFilters: { search: '', status: '', date_from: '', date_to: '' },
            riwayatError: '',

            showReviewModal: false,
            selectedId: null,

            _antrianTimer: null,
            _riwayatTimer: null,
        };
    },
    computed: {
        antrianFilterConfig() {
            return [
                { key: 'date_from', label: 'Dari Tanggal', type: 'date', placeholder: 'Pilih tanggal awal' },
                { key: 'date_to', label: 'Sampai Tanggal', type: 'date', placeholder: 'Pilih tanggal akhir' },
            ];
        },
        riwayatFilterConfig() {
            return [
                {
                    key: 'status',
                    label: 'Status Kunjungan',
                    type: 'select',
                    placeholder: 'Semua status',
                    options: [
                        { value: 'SCHEDULED', label: 'Scheduled' },
                        { value: 'CHECKED_IN', label: 'Check-In' },
                        { value: 'CHECKED_OUT', label: 'Check-Out' },
                        { value: 'KELUAR_TAK_TERCATAT', label: 'Keluar Tak Tercatat' },
                        { value: 'CANCELLED', label: 'Cancelled' },
                        { value: 'REJECTED', label: 'Ditolak' },
                        { value: 'WITHDRAWN', label: 'Ditarik Pemohon' },
                        { value: 'REVOKED', label: 'Dicabut' },
                    ],
                },
                { key: 'date_from', label: 'Dari Tanggal', type: 'date', placeholder: 'Pilih tanggal awal' },
                { key: 'date_to', label: 'Sampai Tanggal', type: 'date', placeholder: 'Pilih tanggal akhir' },
            ];
        },
    },
    mounted() {
        this.fetchAntrian();
    },
    beforeUnmount() {
        // Pencarian yang masih menunggu debounce tidak boleh memuat ulang daftar sesudah panel ditutup.
        clearTimeout(this._antrianTimer);
        clearTimeout(this._riwayatTimer);
    },
    methods: {
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
            Object.assign(this.antrianFilters, { search: '', date_from: '', date_to: '' });
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
            Object.assign(this.riwayatFilters, { search: '', status: '', date_from: '', date_to: '' });
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
                    date_from: this.antrianFilters.date_from,
                    date_to: this.antrianFilters.date_to,
                };
                const { data: json } = await axios.get(`/${this.apiPrefix}-list`, { params });
                if (json.success) {
                    this.antrianData = Array.isArray(json.result) ? json.result : (json.result?.data || []);
                    this.antrianTotal = json.pagination?.total ?? (json.result?.total || 0);
                    this.antrianTotalPages = json.pagination?.total_pages ?? (json.result?.last_page || 1);

                    // Update SubTab counter badge
                    const antrianTab = this.subTabs.find((t) => t.id === 'antrian');
                    if (antrianTab) {
                        antrianTab.count = this.antrianTotal || null;
                    }
                } else {
                    this.antrianError = json.message || 'Gagal memuat data antrean tamu.';
                }
            } catch (e) {
                this.antrianError = 'Terjadi kesalahan jaringan saat memuat antrean tamu.';
                console.error(e);
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
                    date_from: this.riwayatFilters.date_from,
                    date_to: this.riwayatFilters.date_to,
                };
                const { data: json } = await axios.get(`/${this.apiPrefix}-list`, { params });
                if (json.success) {
                    this.riwayatData = Array.isArray(json.result) ? json.result : (json.result?.data || []);
                    this.riwayatTotal = json.pagination?.total ?? (json.result?.total || 0);
                    this.riwayatTotalPages = json.pagination?.total_pages ?? (json.result?.last_page || 1);

                    const riwayatTab = this.subTabs.find((t) => t.id === 'riwayat');
                    if (riwayatTab) {
                        riwayatTab.count = this.riwayatTotal || null;
                    }
                } else {
                    this.riwayatError = json.message || 'Gagal memuat data riwayat tamu.';
                }
            } catch (e) {
                this.riwayatError = 'Terjadi kesalahan jaringan saat memuat riwayat tamu.';
                console.error(e);
            } finally {
                this.riwayatLoading = false;
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
            this.selectedId = row.Id_Transaksi_Tamu || row.Id;
            this.showReviewModal = true;
        },

        onActionDone() {
            this.showReviewModal = false;
            this.antrianPage = 1;
            this.riwayatPage = 1;
            this.fetchAntrian();
            this.fetchRiwayat();
            this.$emit('stats-updated');
        },

        truncate(text, len) {
            if (!text) return '-';
            return text.length > len ? text.slice(0, len) + '…' : text;
        },

        formatDate(val) {
            if (!val) return '-';
            try {
                return new Date(val).toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });
            } catch (e) {
                return val;
            }
        },

        formatTimeOnly(val) {
            if (!val) return '';
            try {
                const d = new Date(val);
                if (isNaN(d.getTime())) return val;
                return d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).replace('.', ':') + ' WIB';
            } catch (e) {
                return val;
            }
        },

        statusLabel(s) {
            return (
                {
                    PENDING_APPROVAL: 'Menunggu Approval',
                    SCHEDULED: 'Scheduled',
                    CHECKED_IN: 'Check-In',
                    CHECKED_OUT: 'Check-Out',
                    KELUAR_TAK_TERCATAT: 'Keluar Tak Tercatat',
                    CANCELLED: 'Cancelled',
                    REJECTED: 'Ditolak',
                    WITHDRAWN: 'Ditarik Pemohon',
                    REVOKED: 'Dicabut',
                }[s] || s || 'Menunggu'
            );
        },

        statusBadgeVariant(s) {
            const st = String(s || '').toUpperCase();
            if (st === 'CHECKED_IN') {
                return 'brand';
            }
            if (st === 'SCHEDULED') {
                return 'info';
            }
            if (st === 'CHECKED_OUT') {
                return 'neutral';
            }
            if (st === 'KELUAR_TAK_TERCATAT') {
                return 'warning';
            }
            if (st === 'PENDING_APPROVAL') {
                return 'warning';
            }
            if (['REJECTED', 'REVOKED', 'CANCELLED'].includes(st)) {
                return 'danger';
            }
            return 'neutral';
        },

        statusBadgeIcon(s) {
            return (
                {
                    PENDING_APPROVAL: 'hourglass-split',
                    SCHEDULED: 'calendar-check',
                    CHECKED_IN: 'box-arrow-in-right',
                    CHECKED_OUT: 'box-arrow-right',
                    KELUAR_TAK_TERCATAT: 'question-circle',
                    REJECTED: 'x-circle-fill',
                    WITHDRAWN: 'arrow-counterclockwise',
                    REVOKED: 'slash-circle',
                    CANCELLED: 'clock-history',
                }[s] || 'dash-circle'
            );
        },

        isTamuActive(row) {
            const s = String(row?.Status_Kunjungan || '').toUpperCase();
            return ['CHECKED_IN', 'SCHEDULED'].includes(s);
        },
    },
};
</script>

<style scoped>
.vt-panel {
    width: 100%;
}

.vt-content {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

/* =========================================================================
   SUBHEADER
   ========================================================================= */
.vt-subhead {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--hcis-space-3, 0.75rem);
    flex-wrap: wrap;
}

.vt-subhead-left {
    min-width: 0;
    flex: 1 1 300px;
}

.vt-subhead-title {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0;
    line-height: 1.3;
}

.vt-subhead-desc {
    font-size: 0.8125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin: 0.25rem 0 0;
    line-height: 1.4;
}

.vt-subhead-actions {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
}

/* =========================================================================
   SURFACE CONTAINER
   ========================================================================= */
.vt-main-surface {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-xl, 12px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.vt-state-wrap {
    padding: var(--hcis-space-6, 1.5rem);
}

.vt-state-loading {
    padding: var(--hcis-space-5, 1.25rem);
}

/* =========================================================================
   DESKTOP TABLE
   ========================================================================= */
.vt-table-responsive {
    overflow-x: auto;
    width: 100%;
}

.vt-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.vt-table thead {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

.vt-table th {
    padding: var(--hcis-space-3, 0.75rem) var(--hcis-space-4, 1rem);
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    white-space: nowrap;
}

.vt-table td {
    padding: var(--hcis-space-3, 0.75rem) var(--hcis-space-4, 1rem);
    vertical-align: middle;
    border-bottom: 1px solid var(--hcis-slate-100, var(--hcis-slate-100));
}

.vt-table-row {
    transition: background-color 0.15s ease;
    cursor: pointer;
}

.vt-table-row:hover {
    background-color: var(--hcis-slate-50, var(--hcis-slate-50));
}

/* Cells */
.vt-user-cell {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 0.75rem);
    min-width: 0;
}

.vt-user-info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.vt-user-name {
    font-weight: 700;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
}

.vt-user-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.vt-instansi-pill {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.6875rem;
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.vt-phone-text {
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.71875rem;
}

.vt-sep-dot {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.vt-pic-name {
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    display: flex;
    align-items: center;
}

.vt-plate-badge {
    display: inline-block;
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
    font-size: 0.72rem;
    padding: 0.125rem 0.45rem;
    border-radius: 6px;
    letter-spacing: 0.05em;
}

.vt-jumlah-pill {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.71875rem;
    font-weight: 700;
    white-space: nowrap;
}

/* =========================================================================
   MOBILE CARDS (Clean HcisCard Standard)
   ========================================================================= */
.vt-mobile-cards {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-3, 0.75rem) 0;
    width: 100%;
}

.vt-grid-card {
    transition: all 0.15s ease;
    width: 100%;
    box-sizing: border-box;
}

.vt-grid-card.is-active-card {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(16, 185, 129, 0.18), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(5, 150, 105, 0.12), transparent 55%),
        linear-gradient(225deg, rgba(236, 253, 245, 0.9) 0%, rgba(255, 255, 255, 0.98) 35%, rgba(255, 255, 255, 0.95) 65%, rgba(209, 250, 229, 0.6) 100%) !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.12), 0 1px 4px -1px rgba(16, 185, 129, 0.06) !important;
}

.vt-grid-card.is-active-card:hover {
    border-color: rgba(16, 185, 129, 0.6) !important;
    box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.22), 0 4px 10px -2px rgba(16, 185, 129, 0.12) !important;
    transform: translateY(-2px);
}

.vt-mobile-cards {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-2, 0.5rem) 0;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.vt-grid-card {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    transition: all 0.15s ease;
}

.vt-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    width: 100%;
    box-sizing: border-box;
}

.vt-identity-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    min-width: 0;
    width: 100%;
    overflow: hidden;
    margin-top: 0.125rem;
}

.vt-identity-meta > span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    display: inline-block;
    max-width: 100%;
}

.vt-card-spec-grid {
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

.vt-spec-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.vt-spec-item__label {
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

.vt-spec-item__val {
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

.vt-keperluan-quote {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-style: italic;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.vt-presence-box {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.72rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow: hidden;
}

.vt-mini-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.4rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
}

.vt-presence-box {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    font-size: 0.72rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

/* =========================================================================
   PAGINATION
   ========================================================================= */
.vt-pagination-section {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--hcis-space-2, 0.5rem);
}

.font-tabular {
    font-variant-numeric: tabular-nums;
}

@media (max-width: 640px) {
    .vt-subhead {
        flex-wrap: nowrap !important;
        align-items: center !important;
        padding-bottom: 0.5rem;
    }

    .vt-subhead-left {
        min-width: 0;
        flex: 1 1 auto;
    }

    .vt-subhead-left > .d-flex {
        flex-wrap: nowrap !important;
        overflow: hidden;
        gap: 0.35rem;
    }

    .vt-subhead-title {
        font-size: 0.92rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .vt-subhead-desc {
        display: none !important;
    }

    .vt-subhead-actions {
        flex-shrink: 0;
    }

    .vt-subhead-actions :deep(.hcis-btn__text) {
        display: none !important;
    }

    .vt-subhead-actions :deep(.hcis-btn) {
        width: 2rem !important;
        height: 2rem !important;
        padding: 0 !important;
        justify-content: center !important;
    }

    .vt-pagination-section {
        padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)) !important;
    }

    .vt-mobile-cards {
        padding-bottom: calc(4rem + env(safe-area-inset-bottom, 0px)) !important;
    }
}
</style>
