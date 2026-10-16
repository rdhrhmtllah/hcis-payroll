<template>
    <div class="permit-status-workspace fade-in">
        <!-- Error Alert if data fetch fails -->
        <HcisAlert
            v-if="fetchError"
            variant="danger"
            appearance="glass"
            title="Gagal Memuat Data Permit"
            :description="fetchError"
            closable
            class="mb-3"
            @close="fetchError = ''"
        >
            <template #actions>
                <HcisButton
                    size="sm"
                    variant="outline"
                    leading-icon="arrow-clockwise"
                    :loading="loading"
                    label="Coba Lagi"
                    @click="fetchData"
                />
            </template>
        </HcisAlert>

        <!-- Main Data Table & View Container -->
        <HcisDataTable
            :title="'Daftar Permit Kendaraan'"
            :subtitle="'Pantau masa berlaku, periksa alur persetujuan, atau batalkan pengajuan permit aktif.'"
            :icon="'car-front-fill'"
            :sentiment="'brand'"
            :variant="'glass'"
            :table-variant="'flat'"
            :columns="columns"
            :data="paginatedData"
            :loading="loading"
            :search="searchQuery"
            :search-placeholder="'Cari nomor plat, model kendaraan, no transaksi...'"
            :command-key="false"
            :quick-chips="quickChips"
            :active-chip-value="activeChip"
            :show-view-toggle="true"
            :view-mode="viewMode"
            :show-pagination="filteredPermits.length > 0"
            :total="filteredPermits.length"
            :page="currentPage"
            :page-size="itemsPerPage"
            :hoverable="true"
            @update:search="onSearchUpdate"
            @chip-click="onChipClick"
            @update:view-mode="onViewModeChange"
            @page-change="changePage"
            @size-change="onSizeChange"
        >
            <!-- Header Actions -->
            <template #headerActions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        size="sm"
                        variant="secondary"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        label="Muat Ulang"
                        class="permit-reload-btn"
                        title="Perbarui daftar permit"
                        aria-label="Muat ulang daftar permit"
                        @click="fetchData"
                    />
                </div>
            </template>

            <!-- Table View: Custom Cells -->
            <!-- Column 1: Kendaraan & Plat -->
            <template #cell-Plat_Kendaraan="{ row }">
                <div class="permit-cell-vehicle">
                    <div class="permit-plate-badge" :title="row.No_Plat_Kendaraan">
                        <span class="permit-plate-badge__text">{{ row.No_Plat_Kendaraan || '-' }}</span>
                    </div>
                    <div class="permit-vehicle-details">
                        <div class="permit-vehicle-name text-truncate">
                            <HcisIcon :name="getVehicleIcon(row.Nama_Vehicle_Type)" size="2xs" class="me-1 text-primary" />
                            <strong class="text-slate-800">{{ row.Nama_Model_Manual || '-' }}</strong>
                        </div>
                        <div class="permit-vehicle-subtext text-xs text-muted text-truncate">
                            <span>{{ row.Nama_Vehicle_Type || 'Kendaraan' }}</span>
                            <span v-if="row.Tahun_Kendaraan"> · {{ row.Tahun_Kendaraan }}</span>
                            <span v-if="row.Warna_Manual"> · {{ row.Warna_Manual }}</span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Column 2: Kategori Permit -->
            <template #cell-Nama_Kategori="{ row }">
                <div class="permit-cell-category">
                    <span
                        class="permit-category-pill"
                        :style="getCategoryStyle(row)"
                        :title="row.Deskripsi_Kategori || row.Nama_Kategori"
                    >
                        <span class="permit-category-pill__dot" :style="{ backgroundColor: row.Kode_Warna || '#6366f1' }" />
                        <span class="permit-category-pill__label">{{ row.Nama_Kategori || '-' }}</span>
                    </span>
                </div>
            </template>

            <!-- Column 3: No. Transaksi & Tanggal Pengajuan -->
            <template #cell-No_Transaksi="{ row }">
                <div class="permit-cell-transaksi">
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="font-tabular fw-bold text-slate-800 text-xs">{{ row.No_Transaksi }}</span>
                        <button
                            type="button"
                            class="permit-copy-btn"
                            title="Salin No. Transaksi"
                            aria-label="Salin nomor transaksi"
                            @click.stop="copyToClipboard(row.No_Transaksi)"
                        >
                            <HcisIcon name="copy" size="2xs" />
                        </button>
                    </div>
                    <div class="text-xs text-muted mt-0.5">
                        <HcisIcon name="calendar3" size="2xs" class="me-1" />
                        {{ formatDate(row.Tanggal_Pengajuan) }}
                    </div>
                    <!-- Izin ini terbit ATAS NAMA pemohon, tetapi bukan untuknya.
                         Tanpa penanda, ia tampil identik dengan permit kendaraan
                         miliknya sendiri di baris sebelah. -->
                    <span v-if="dariKunjungan(row)" class="permit-tamu-badge mt-1">
                        <HcisIcon name="person-badge" size="2xs" />
                        <span>Permit tamu · {{ row.Nama_Tamu || 'Tamu' }}</span>
                    </span>
                </div>
            </template>

            <!-- Column 4: Masa Berlaku & Sisa Hari -->
            <template #cell-Masa_Berlaku="{ row }">
                <div class="permit-cell-validity">
                    <template v-if="row.Status_Permit === 'APPROVED' && row.Tanggal_Berlaku">
                        <div class="text-xs fw-semibold text-slate-700">
                            {{ formatDate(row.Tanggal_Berlaku) }} – {{ formatDate(row.Tanggal_Kadaluarsa) }}
                        </div>
                        <div class="mt-1">
                            <HcisBadge
                                :label="getRemainingBadgeLabel(row)"
                                :variant="getRemainingVariant(row)"
                                :icon="getRemainingIcon(row)"
                                size="xs"
                                appearance="glass"
                            />
                        </div>
                    </template>
                    <template v-else-if="row.Status_Permit === 'PENDING'">
                        <span class="text-xs text-muted fst-italic">Menunggu persetujuan</span>
                    </template>
                    <template v-else-if="row.Status_Permit === 'REJECTED'">
                        <span class="text-xs text-danger fst-italic">Pengajuan ditolak</span>
                    </template>
                    <!-- Dicabut BUKAN kedaluwarsa: masa berlakunya mungkin masih
                         panjang, aksesnya yang dihentikan. Menyamakan keduanya
                         membuat pemohon mengira tinggal menunggu perpanjangan. -->
                    <template v-else-if="row.Status_Permit === 'REVOKED'">
                        <span class="text-xs text-danger fst-italic">Akses dicabut</span>
                    </template>
                    <template v-else-if="row.Status_Permit === 'EXPIRED'">
                        <span class="text-xs text-danger fst-italic">{{ row.Flag_Approval == null ? 'Batas approval lewat' : 'Masa berlaku habis' }}</span>
                    </template>
                    <template v-else>
                        <span class="text-xs text-muted">-</span>
                    </template>
                </div>
            </template>

            <!-- Column 5: Status Permit -->
            <template #cell-Status_Permit="{ row }">
                <HcisBadge
                    :status="row.Status_Permit"
                    :label="getStatusLabel(row.Status_Permit)"
                    :variant="isPermitActive(row) ? 'success' : undefined"
                    size="sm"
                    appearance="glass"
                />
            </template>

            <!-- Column 6: Aksi -->
            <template #cell-actions="{ row }">
                <div class="permit-cell-actions">
                    <HcisButton
                        v-if="row.Status_Permit === 'APPROVED'"
                        size="xs"
                        variant="primary-subtle"
                        leading-icon="person-vcard-fill"
                        label="E-Permit"
                        class="permit-action-btn permit-action-btn--pass"
                        title="Lihat kartu E-Permit digital"
                        @click="$emit('navigate-to-cards')"
                    />
                    <HcisButton
                        size="xs"
                        variant="secondary"
                        leading-icon="eye"
                        label="Detail"
                        class="permit-action-btn permit-action-btn--detail"
                        title="Lihat rincian permit"
                        @click="viewDetail(row)"
                    />
                    <HcisButton
                        v-if="canCancelPermit(row)"
                        size="xs"
                        variant="danger-quiet"
                        leading-icon="slash-circle"
                        label="Batal"
                        class="permit-action-btn permit-action-btn--cancel"
                        title="Batalkan pengajuan ini"
                        @click="openCancelConfirm(row)"
                    />
                </div>
            </template>

            <!-- Empty State Slot inside Table -->
            <template #empty>
                <div class="permit-empty-wrapper py-5">
                    <HcisEmptyState
                        v-if="permits.length === 0"
                        title="Belum Ada Pengajuan Permit"
                        description="Anda belum memiliki riwayat pengajuan permit kendaraan. Lengkapi formulir untuk mengajukan akses kendaraan baru."
                    >
                        <template #actions>
                            <HcisButton
                                variant="primary"
                                size="md"
                                leading-icon="file-earmark-plus"
                                label="Buat Pengajuan Permit"
                                @click="$emit('navigate-to-form')"
                            />
                        </template>
                    </HcisEmptyState>
                    <HcisEmptyState
                        v-else
                        title="Tidak Ada Permit yang Cocok"
                        description="Tidak ditemukan permit yang sesuai dengan filter atau kata kunci pencarian Anda."
                    >
                        <template #actions>
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-counterclockwise"
                                label="Reset Filter & Pencarian"
                                @click="resetFilter"
                            />
                        </template>
                    </HcisEmptyState>
                </div>
            </template>

            <!-- Grid / Card View Slot (Seamlessly integrated with HcisDataTable and Pagination) -->
            <template #grid>
                <div v-if="loading" class="permit-cards-grid">
                    <HcisCard
                        v-for="i in 6"
                        :key="i"
                        variant="glass"
                        size="md"
                        divider
                        class="permit-grid-card"
                    >
                        <template #badge>
                            <HcisSkeleton width="80px" height="1.25rem" rounded="full" />
                        </template>
                        <div class="permit-card-header-bar mb-3">
                            <HcisSkeleton width="120px" height="2rem" rounded="md" />
                            <HcisSkeleton width="90px" height="1.25rem" rounded="full" />
                        </div>
                        <div class="permit-card-spec-grid mb-3">
                            <div v-for="s in 4" :key="s" class="permit-spec-item">
                                <HcisSkeleton width="70px" height="0.65rem" rounded="sm" style="margin-bottom: 0.25rem;" />
                                <HcisSkeleton width="100px" height="0.85rem" rounded="sm" />
                            </div>
                        </div>
                        <template #footer>
                            <div class="permit-card-actions-bar">
                                <HcisSkeleton width="100%" height="2rem" rounded="md" />
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <div v-else-if="paginatedData.length > 0" class="permit-cards-grid">
                    <HcisCard
                        v-for="item in paginatedData"
                        :key="item.Id || item.Id_Transaksi_Permit"
                        :sentiment="getCardSentiment(item.Status_Permit)"
                        variant="glass"
                        size="md"
                        divider
                        hoverable
                        interactive
                        class="permit-grid-card"
                        :class="{ 'is-active-card': isPermitActive(item) }"
                        @click="viewDetail(item)"
                    >
                        <template #badge>
                            <HcisBadge
                                :status="item.Status_Permit"
                                :label="getStatusLabel(item.Status_Permit)"
                                size="xs"
                                appearance="glass"
                            />
                        </template>

                        <!-- Card Header Plate + Category -->
                        <div class="permit-card-header-bar mb-3">
                            <div class="permit-plate-badge permit-plate-badge--lg">
                                <span class="permit-plate-badge__text">{{ item.No_Plat_Kendaraan || '-' }}</span>
                            </div>
                            <span
                                class="permit-category-pill"
                                :style="getCategoryStyle(item)"
                            >
                                <span class="permit-category-pill__dot" :style="{ backgroundColor: item.Kode_Warna || '#6366f1' }" />
                                <span class="permit-category-pill__label">{{ item.Nama_Kategori || '-' }}</span>
                            </span>
                        </div>

                        <!-- Vehicle Specs Summary -->
                        <div class="permit-card-spec-grid mb-3">
                            <div class="permit-spec-item">
                                <span class="permit-spec-item__label">Model Kendaraan</span>
                                <strong class="permit-spec-item__val text-truncate">{{ item.Nama_Model_Manual || '-' }}</strong>
                            </div>
                            <div class="permit-spec-item">
                                <span class="permit-spec-item__label">Tipe & Tahun</span>
                                <span class="permit-spec-item__val text-truncate">
                                    {{ item.Nama_Vehicle_Type || '-' }} <span v-if="item.Tahun_Kendaraan">({{ item.Tahun_Kendaraan }})</span>
                                </span>
                            </div>
                            <div class="permit-spec-item">
                                <span class="permit-spec-item__label">No. Transaksi</span>
                                <span class="permit-spec-item__val font-tabular">{{ item.No_Transaksi || '-' }}</span>
                            </div>
                            <div class="permit-spec-item">
                                <span class="permit-spec-item__label">Diajukan Pada</span>
                                <span class="permit-spec-item__val">{{ formatDate(item.Tanggal_Pengajuan) }}</span>
                            </div>
                        </div>

                        <!-- Penanda yang sama pada tampilan kartu. Yang hanya
                             terpasang di tabel hilang begitu layarnya sempit. -->
                        <div v-if="dariKunjungan(item)" class="permit-tamu-note mb-3">
                            <HcisIcon name="person-badge" size="xs" />
                            <span>
                                Permit untuk tamu <strong>{{ item.Nama_Tamu || '-' }}</strong>, terbit atas
                                nama Anda sebagai PIC kunjungan.
                            </span>
                        </div>

                        <!-- Validity Status Strip -->
                        <div v-if="item.Status_Permit === 'APPROVED' && item.Tanggal_Berlaku" class="permit-card-validity-box p-2 rounded-3 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-xs text-muted fw-bold text-uppercase">Masa Berlaku</span>
                                <HcisBadge
                                    :label="getRemainingBadgeLabel(item)"
                                    :variant="getRemainingVariant(item)"
                                    :icon="getRemainingIcon(item)"
                                    size="xs"
                                    appearance="subtle"
                                />
                            </div>
                            <div class="text-xs text-slate-700 fw-semibold">
                                {{ formatDate(item.Tanggal_Berlaku) }} – {{ formatDate(item.Tanggal_Kadaluarsa) }}
                            </div>
                        </div>

                        <!-- Pencabutan selalu bersebab: verifikator wajib
                             mengisinya. Menampilkan status tanpa sebabnya
                             persis yang membuat pemohon bertanya-tanya. -->
                        <div v-else-if="item.Status_Permit === 'REVOKED'" class="permit-revoked-box p-2 rounded-3 mb-2">
                            <div class="d-flex align-items-center gap-1.5 mb-1">
                                <HcisIcon name="slash-circle-fill" size="2xs" />
                                <span class="text-xs fw-bold text-uppercase">Akses dicabut</span>
                            </div>
                            <p class="permit-revoked-box__text mb-0">
                                {{ item.Alasan_Pencabutan || 'Alasan tidak tercatat.' }}
                            </p>
                            <span v-if="item.Dicabut_Oleh" class="permit-revoked-box__meta">
                                oleh {{ item.Dicabut_Oleh }}<template v-if="item.Dicabut_Pada"> · {{ formatDate(item.Dicabut_Pada) }}</template>
                            </span>
                        </div>

                        <!-- Card Actions -->
                        <template #footer>
                            <div class="permit-card-actions-bar">
                                <HcisButton
                                    size="sm"
                                    variant="secondary"
                                    leading-icon="eye"
                                    label="Lihat Detail"
                                    class="permit-card-btn flex-grow-1"
                                    @click.stop="viewDetail(item)"
                                />
                                <HcisButton
                                    v-if="canCancelPermit(item)"
                                    size="sm"
                                    variant="danger-quiet"
                                    leading-icon="slash-circle"
                                    label="Batalkan"
                                    class="permit-card-btn"
                                    title="Batalkan pengajuan"
                                    @click.stop="openCancelConfirm(item)"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <div v-else class="permit-empty-wrapper py-5">
                    <HcisEmptyState
                        v-if="permits.length === 0"
                        title="Belum Ada Pengajuan Permit"
                        description="Anda belum memiliki riwayat pengajuan permit kendaraan. Lengkapi formulir untuk mengajukan akses kendaraan baru."
                    >
                        <template #actions>
                            <HcisButton
                                variant="primary"
                                size="md"
                                leading-icon="file-earmark-plus"
                                label="Buat Pengajuan Permit"
                                @click="$emit('navigate-to-form')"
                            />
                        </template>
                    </HcisEmptyState>
                    <HcisEmptyState
                        v-else
                        title="Tidak Ada Permit yang Cocok"
                        description="Tidak ditemukan permit yang sesuai dengan filter atau kata kunci pencarian Anda."
                    >
                        <template #actions>
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-counterclockwise"
                                label="Reset Filter & Pencarian"
                                @click="resetFilter"
                            />
                        </template>
                    </HcisEmptyState>
                </div>
            </template>
        </HcisDataTable>

        <!-- ================================================================= -->
        <!-- DETAIL MODAL DIALOG (HCIS DESIGN SYSTEM)                          -->
        <!-- ================================================================= -->
        <HcisDualPaneDialog
            v-model="showModal"
            title="Detail Permit Kendaraan"
            kicker="Visitor · Vehicle Permit"
            icon="car-front-fill"
            sentiment="brand"
            variant="glass"
            pattern="grid"
            size="xl"
            left-width="390px"
            left-tab-label="Identitas & Status"
            left-tab-icon="person-badge"
            right-tab-label="Rincian & Berkas"
            right-tab-icon="file-earmark-text"
            :right-tab-badge="dokumens.length > 0 ? dokumens.length : ''"
            sticky-footer
            :loading="detailLoading"
            loading-text="Mengambil rincian permit..."
            @close="closeModal"
        >
            <!-- Left Pane: Overview Card & Approval Timeline -->
            <template #left>
                <div v-if="permitData">
                    <!-- Hero Permit Identity Card -->
                    <div
                        class="permit-hero-card"
                        :style="getHeroCardStyle(permitData)"
                    >
                        <!-- 1. Header Row: Vehicle Icon + Category/Model Details + Status Badge -->
                        <div class="permit-hero-header">
                            <div class="permit-hero-brand-group">
                                <div class="permit-hero-icon-box shadow-sm">
                                    <HcisIcon :name="getVehicleIcon(permitData.Nama_Vehicle_Type)" size="md" />
                                </div>
                                <div class="permit-hero-brand-meta">
                                    <div>
                                        <span
                                            class="permit-hero-category-pill"
                                            :style="getCategoryBadgeStyle(permitData)"
                                        >
                                            <span class="permit-hero-category-dot" :style="{ backgroundColor: permitData.Kode_Warna || '#10b981' }" />
                                            <span class="permit-hero-category-text text-truncate">{{ permitData.Nama_Kategori || 'Permit Akses' }}</span>
                                        </span>
                                    </div>
                                    <div class="permit-hero-vehicle-type text-truncate">
                                        {{ permitData.Nama_Vehicle_Type || 'Kendaraan' }}
                                        <span v-if="permitData.Nama_Model_Manual" class="fw-semibold text-slate-700">· {{ permitData.Nama_Model_Manual }}</span>
                                    </div>
                                </div>
                            </div>

                            <HcisBadge
                                :status="permitData.Status_Permit"
                                :label="getStatusLabel(permitData.Status_Permit)"
                                size="sm"
                                appearance="subtle"
                                class="flex-shrink-0"
                            />
                        </div>

                        <!-- 2. Middle Section: Plate & Specs Cards -->
                        <div class="permit-hero-details-container">
                            <!-- Primary License Plate Display Card -->
                            <div class="permit-hero-plate-card">
                                <div class="permit-hero-plate-info">
                                    <span class="permit-hero-card-kicker">Nomor Plat Kendaraan</span>
                                    <div class="permit-hero-plate-number font-tabular">
                                        {{ permitData.No_Plat_Kendaraan || '-' }}
                                    </div>
                                </div>
                                <div class="permit-hero-plate-badge-tag">
                                    <HcisIcon :name="getVehicleIcon(permitData.Nama_Vehicle_Type)" size="sm" class="text-slate-400" />
                                </div>
                            </div>

                            <!-- Dual Subpod Row: Tahun & Warna -->
                            <div class="permit-hero-subpod-grid">
                                <div class="permit-hero-subpod">
                                    <span class="permit-hero-card-kicker">Tahun Pembuatan</span>
                                    <div class="permit-hero-subpod-val font-tabular fw-bold text-slate-800">
                                        {{ permitData.Tahun_Kendaraan || '-' }}
                                    </div>
                                </div>
                                <div class="permit-hero-subpod">
                                    <span class="permit-hero-card-kicker">Warna Kendaraan</span>
                                    <div class="permit-hero-subpod-val d-flex align-items-center gap-1.5 fw-bold text-slate-800 text-truncate">
                                        <span
                                            class="permit-color-circle"
                                            :style="{ backgroundColor: getVehicleColorHex(permitData.Warna_Manual) }"
                                        />
                                        <span class="text-truncate">{{ permitData.Warna_Manual || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Bottom Meta Row: Transaction Code + Submission Date -->
                        <div class="permit-hero-meta-bar text-xs">
                            <div class="permit-hero-transaksi font-tabular">
                                <HcisIcon name="receipt" size="xs" class="text-slate-400" />
                                <span>{{ permitData.No_Transaksi }}</span>
                            </div>
                            <div class="permit-hero-date">
                                <HcisIcon name="clock-history" size="xs" class="text-slate-400" />
                                <span>Diajukan: <strong>{{ formatDate(permitData.Tanggal_Pengajuan) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Pemohon Information Box -->
                    <HcisCard
                        title="Pemohon & Departemen"
                        icon="person-badge-fill"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="permit-requester-card">
                            <!-- Avatar & Identity Row -->
                            <div class="permit-requester-header">
                                <div class="permit-requester-avatar">
                                    {{ getInitials(permitData.Nama_Pemohon) }}
                                </div>
                                <div class="permit-requester-identity">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="permit-requester-name text-truncate">
                                            {{ permitData.Nama_Pemohon || '-' }}
                                        </span>
                                        <span v-if="permitData.Kode_Karyawan_Pemohon" class="permit-requester-nik-tag font-tabular">
                                            Kode: {{ permitData.Kode_Karyawan_Pemohon }}
                                        </span>
                                    </div>
                                    <div class="permit-requester-role text-xs">
                                        Pemohon Kendaraan
                                    </div>
                                </div>
                            </div>

                            <!-- Department Spec Pod -->
                            <div class="permit-requester-dept-pod">
                                <div class="permit-requester-dept-icon">
                                    <HcisIcon name="building" size="xs" />
                                </div>
                                <div class="permit-requester-dept-info">
                                    <span class="permit-requester-dept-label">Departemen / Unit</span>
                                    <span class="permit-requester-dept-val">{{ permitData.Departemen || 'Departemen Pemohon' }}</span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Asal-usul pengajuan — hanya untuk izin turunan kunjungan.
                         Bentuknya sengaja senada dengan panel yang dilihat
                         verifikator saat me-review permit yang sama: pemohon dan
                         verifikator yang membaca dua cerita berbeda tentang satu
                         nomor izin akan berdebat tanpa ada yang keliru. -->
                    <HcisCard
                        v-if="dariKunjungan(permitData)"
                        title="Asal Pengajuan"
                        description="Permit ini terbit dari sebuah kunjungan tamu, bukan dari pengajuan Anda sendiri."
                        icon="person-badge"
                        sentiment="warning"
                        variant="glass"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="permit-asal-grid">
                            <div v-for="baris in asalPengajuan" :key="baris.label" class="permit-asal-item">
                                <span class="permit-asal-item__label">{{ baris.label }}</span>
                                <span class="permit-asal-item__val">{{ baris.nilai }}</span>
                            </div>
                        </div>

                        <p class="permit-asal-catatan mb-0 mt-3">
                            Permit ini tidak memakai jatah Anda dan tidak tampil di Pass Digital, karena
                            pemegangnya adalah tamu. Pembatalannya mengikuti pembatalan kunjungannya.
                        </p>
                    </HcisCard>

                    <!-- Jadwal inspeksi kendaraan (DDL-2026-123) -->
                    <HcisCard
                        v-if="inspeksi"
                        title="Jadwal Inspeksi Kendaraan"
                        description="Bawa kendaraan ke pos inspeksi SHE sesuai jadwal tertera."
                        icon="calendar-check-fill"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                        class="mb-3"
                    >
                        <div class="permit-inspeksi-card">
                            <!-- Top Status & Reschedule Quota Bar -->
                            <div class="permit-inspeksi__top">
                                <HcisBadge
                                    :variant="inspeksiStatus.variant"
                                    :label="inspeksiStatus.label"
                                    :pulse="inspeksi.status === 'TERJADWAL' && !inspeksi.kedaluwarsa"
                                    size="sm"
                                    appearance="glass"
                                />
                                <span v-if="inspeksi.status === 'TERJADWAL' && !inspeksi.kedaluwarsa" class="permit-inspeksi__reschedule-quota font-tabular">
                                    <HcisIcon name="arrow-repeat" size="2xs" class="me-1" />
                                    Jadwal Ulang: <strong>{{ inspeksi.jadwal_ulang_terpakai || 0 }} / {{ maksJadwalUlang }}</strong>
                                </span>
                            </div>

                            <!-- Schedule & Location Ticket Card -->
                            <div class="permit-inspeksi__ticket">
                                <div class="permit-inspeksi__ticket-row">
                                    <div class="permit-inspeksi__ticket-icon is-calendar">
                                        <HcisIcon name="calendar2-check-fill" size="sm" />
                                    </div>
                                    <div class="permit-inspeksi__ticket-content">
                                        <span class="permit-inspeksi__ticket-label">Waktu Pemeriksaan Fisik</span>
                                        <div class="permit-inspeksi__datetime-wrap">
                                            <span class="permit-inspeksi__date font-tabular">{{ formatTanggalInspeksi(inspeksi) }}</span>
                                            <span class="permit-inspeksi__time-badge font-tabular">
                                                <HcisIcon name="clock-fill" size="2xs" />
                                                <span>{{ formatJamInspeksi(inspeksi) }} WIB</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="permit-inspeksi__ticket-divider" />

                                <div class="permit-inspeksi__ticket-row">
                                    <div class="permit-inspeksi__ticket-icon is-location">
                                        <HcisIcon name="geo-alt-fill" size="sm" />
                                    </div>
                                    <div class="permit-inspeksi__ticket-content">
                                        <span class="permit-inspeksi__ticket-label">Lokasi Pos Inspeksi</span>
                                        <div class="permit-inspeksi__pos-wrap">
                                            <strong class="permit-inspeksi__pos-name">{{ inspeksi.pos }}</strong>
                                            <span class="permit-inspeksi__pos-tag">· {{ inspeksi.lokasi || 'Pos SHE & Keselamatan' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes & Warning Alerts -->
                            <!-- Kedaluwarsa: tinggal arsip, tidak ada aksi yang ditawarkan. -->
                            <div v-if="inspeksi.kedaluwarsa" class="permit-inspeksi__notice is-danger">
                                <HcisIcon name="hourglass-split" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    <strong>Pengajuan Kedaluwarsa:</strong> Batas waktu persetujuan sudah lewat. Pengajuan ini tidak bisa diubah lagi; silakan ajukan permit baru.
                                </div>
                            </div>
                            <div v-else-if="inspeksi.wajib_pilih_ulang" class="permit-inspeksi__notice is-danger">
                                <HcisIcon name="exclamation-triangle-fill" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    <strong>Tidak Hadir Pada Jadwal:</strong> Anda tercatat belum hadir. Silakan pilih jadwal inspeksi baru agar permohonan dapat diproses.
                                </div>
                            </div>
                            <div v-else-if="inspeksi.gugur" class="permit-inspeksi__notice is-danger">
                                <HcisIcon name="x-circle-fill" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    <strong>Permit Gugur:</strong> Batas ketidakhadiran ({{ inspeksi.tidak_hadir }}x) terlampaui. Silakan ajukan permit baru.
                                </div>
                            </div>
                            <!-- Jadwal lewat tanpa tercatat: keadaannya dikatakan apa adanya.
                                 Tidak ada yang dihukum — sistem tidak tahu siapa yang absen. -->
                            <div v-else-if="inspeksi.lewat_jadwal" class="permit-inspeksi__notice is-warning">
                                <HcisIcon name="clock-history" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    <strong>Jadwal Sudah Lewat:</strong> Hasil inspeksi belum dicatat petugas SHE. Hubungi SHE bila Anda sudah datang<template v-if="inspeksi.boleh_pindah">, atau pilih jadwal baru di bawah (memakai jatah jadwal ulang: {{ inspeksi.jadwal_ulang_terpakai || 0 }} dari {{ maksJadwalUlang }} terpakai)</template><template v-else>. Jatah jadwal ulang Anda sudah habis; pengajuan akan kedaluwarsa bila tidak diputus</template>.
                                </div>
                            </div>
                            <div v-else-if="inspeksi.status === 'TERJADWAL'" class="permit-inspeksi__notice is-info">
                                <HcisIcon name="info-circle-fill" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    Jadwal ulang terpakai <strong>{{ inspeksi.jadwal_ulang_terpakai }} dari {{ maksJadwalUlang }} kali</strong>. Pemindahan jadwal dan pembatalan pengajuan paling lambat <strong>H-1</strong> sebelum waktu pemeriksaan.
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div v-if="inspeksi.boleh_pindah && permitData && permitData.Flag_Approval == null" class="permit-inspeksi__actions-wrap">
                                <HcisButton
                                    size="sm"
                                    :variant="inspeksi.wajib_pilih_ulang || inspeksi.lewat_jadwal ? 'primary' : 'secondary'"
                                    leading-icon="calendar2-week"
                                    :label="inspeksi.wajib_pilih_ulang || inspeksi.lewat_jadwal ? 'Pilih Jadwal Inspeksi Baru' : 'Pindah Jadwal Inspeksi'"
                                    class="w-100 justify-content-center permit-inspeksi__reschedule-btn"
                                    @click="bukaPindahInspeksi"
                                />
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Approval Workflow Section -->
                    <HcisCard
                        title="Alur Persetujuan"
                        description="Tahapan verifikasi otoritas dan catatan approver."
                        icon="diagram-3-fill"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                    >
                        <div v-if="approvals && approvals.length" class="permit-approval-timeline-wrapper">
                            <HcisTimeline
                                :items="approvalTimelineItems"
                                direction="vertical"
                                variant="compact"
                                sentiment="brand"
                                :card="false"
                            />
                        </div>
                        <div v-else class="permit-auto-approved-box p-3 rounded-3 bg-emerald-50 border border-emerald-200 text-center">
                            <HcisIcon name="check-circle-fill" size="lg" class="text-success mb-2" />
                            <div class="fw-bold text-emerald-900 text-sm">Otorisasi Otomatis</div>
                            <div class="text-xs text-emerald-700 mt-1">
                                Permit ini disetujui otomatis oleh sistem tanpa alur persetujuan berjenjang.
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- Right Pane: Vehicle Specs, Documents & Notes -->
            <template #right>
                <div v-if="permitData">
                    <!-- Vehicle Specifications Grid Card -->
                    <HcisCard
                        title="Spesifikasi Kendaraan"
                        description="Rincian data fisik kendaraan sesuai STNK yang terdaftar."
                        icon="card-text"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="permit-specs-grid">
                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Nomor Plat</span>
                                <div class="permit-spec-tile__val font-tabular fw-bold text-slate-900 mt-1">
                                    {{ permitData.No_Plat_Kendaraan || '-' }}
                                </div>
                            </div>

                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Jenis Kendaraan</span>
                                <div class="permit-spec-tile__val d-flex align-items-center gap-1.5 mt-1.5">
                                    <HcisIcon :name="getVehicleIcon(permitData.Nama_Vehicle_Type)" size="xs" class="text-primary" />
                                    <strong>{{ permitData.Nama_Vehicle_Type || '-' }}</strong>
                                </div>
                            </div>

                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Merek / Model</span>
                                <div class="permit-spec-tile__val fw-bold text-slate-800 mt-1.5">
                                    {{ permitData.Nama_Model_Manual || '-' }}
                                </div>
                            </div>

                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Tahun Pembuatan</span>
                                <div class="permit-spec-tile__val font-tabular fw-semibold mt-1.5">
                                    {{ permitData.Tahun_Kendaraan || '-' }}
                                </div>
                            </div>

                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Warna Kendaraan</span>
                                <div class="permit-spec-tile__val d-flex align-items-center gap-1.5 mt-1.5">
                                    <span
                                        class="permit-color-circle"
                                        :style="{ backgroundColor: getVehicleColorHex(permitData.Warna_Manual) }"
                                    />
                                    <span>{{ permitData.Warna_Manual || '-' }}</span>
                                </div>
                            </div>

                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Masa Berlaku</span>
                                <div class="permit-spec-tile__val font-tabular text-xs text-slate-700 mt-1.5">
                                    <span v-if="permitData.Tanggal_Mulai">
                                        {{ formatDate(permitData.Tanggal_Mulai) }} s/d {{ formatDate(permitData.Tanggal_Selesai) }}
                                    </span>
                                    <span v-else class="text-muted fst-italic">
                                        Belum aktif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Alasan / Catatan Pengajuan Callout -->
                        <div v-if="permitData.Keterangan_Pengajuan || permitData.Keterangan" class="permit-reason-callout mt-4">
                            <div class="permit-reason-callout__header">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="permit-reason-callout__icon">
                                        <HcisIcon name="chat-quote-fill" size="xs" />
                                    </span>
                                    <span class="permit-reason-callout__title">Alasan / Catatan Pengajuan</span>
                                </div>
                                <span class="permit-reason-callout__tag">Catatan Pemohon</span>
                            </div>
                            <div class="permit-reason-callout__body mt-1">
                                <p class="permit-reason-callout__text mb-0">
                                    “{{ permitData.Keterangan_Pengajuan || permitData.Keterangan }}”
                                </p>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Rejection Reason Alert (if rejected) -->
                    <HcisAlert
                        v-if="permitData.Status_Permit === 'REJECTED' && (permitData.Alasan_Tolak || getRejectionComment())"
                        variant="danger"
                        appearance="glass"
                        title="Alasan Penolakan Pengajuan"
                        :description="permitData.Alasan_Tolak || getRejectionComment()"
                        icon="exclamation-octagon-fill"
                        class="mb-4"
                    />

                    <!--
                        Pencabutan diberi tempatnya sendiri, bukan menumpang
                        alert penolakan: keduanya berbeda arti. Ditolak berarti
                        pengajuannya tidak pernah hidup; dicabut berarti pernah
                        hidup lalu dihentikan — dan sebabnya WAJIB terbaca
                        pemohon, karena itulah syarat verifikator mencabut.
                    -->
                    <!--
                        Tiga hal berbeda, tiga tempat berbeda.

                        Dulu ketiganya dirangkai jadi satu paragraf: catatan
                        bebas dari pencabutnya, keterangan siapa & kapan, lalu
                        akibatnya di gerbang. Kalimat orang tidak selalu berakhir
                        dengan titik, sehingga catatannya menyambung mulus ke
                        "Dicabut oleh ..." dan terbaca seperti satu kalimat
                        panjang milik sistem — pemohon tak bisa lagi membedakan
                        mana yang ditulis manusia dan mana yang dicatat aplikasi.
                    -->
                    <HcisAlert
                        v-if="permitData.Status_Permit === 'REVOKED'"
                        variant="danger"
                        appearance="glass"
                        title="Akses permit ini telah dicabut"
                        icon="slash-circle-fill"
                        class="mb-4"
                    >
                        <div class="permit-revoked-detail">
                            <figure class="permit-revoked-detail__note">
                                <figcaption class="permit-revoked-detail__note-label">
                                    <HcisIcon name="chat-left-quote-fill" size="2xs" />
                                    <span>Catatan pencabutan</span>
                                </figcaption>
                                <blockquote class="permit-revoked-detail__note-text mb-0">
                                    {{ alasanPencabutan }}
                                </blockquote>
                            </figure>

                            <dl class="permit-revoked-detail__meta">
                                <div class="permit-revoked-detail__meta-item">
                                    <dt>Dicabut oleh</dt>
                                    <dd>{{ permitData.Dicabut_Oleh || 'Tidak tercatat' }}</dd>
                                </div>
                                <div class="permit-revoked-detail__meta-item">
                                    <dt>Tanggal pencabutan</dt>
                                    <dd>{{ permitData.Dicabut_Pada ? formatDate(permitData.Dicabut_Pada) : 'Tidak tercatat' }}</dd>
                                </div>
                            </dl>

                            <p class="permit-revoked-detail__akibat mb-0">
                                <HcisIcon name="info-circle-fill" size="2xs" class="flex-shrink-0" />
                                <span>Kartu E-Permit untuk kendaraan ini sudah tidak berlaku di gerbang.</span>
                            </p>
                        </div>
                    </HcisAlert>

                    <!-- Verification Documents Card -->
                    <HcisCard
                        title="Dokumen Verifikasi"
                        description="Berkas kelengkapan yang diunggah untuk verifikasi keabsahan kendaraan."
                        icon="folder-check"
                        sentiment="teal"
                        variant="glass"
                        size="sm"
                        divider
                    >
                        <div
                            v-if="!dokumens || dokumens.length === 0"
                            class="permit-docs-empty text-center p-4 text-muted border border-dashed rounded-3 bg-slate-50"
                        >
                            <HcisIcon name="file-earmark-x" size="lg" class="text-slate-400 mb-2" />
                            <div class="text-xs fw-semibold">Tidak ada berkas dokumen terlampir</div>
                        </div>

                        <div v-else class="permit-docs-list">
                            <div
                                v-for="(doc, idx) in dokumens"
                                :key="doc.Id_Permit_Dokumen || idx"
                                class="permit-doc-item"
                                @click="openFileViewer(doc)"
                            >
                                <!-- Left: File Type Icon -->
                                <div class="permit-doc-icon-box" :class="getDocIconBg(doc.Jenis_Dokumen)">
                                    <HcisIcon :name="getDocIcon(doc.Jenis_Dokumen)" size="sm" />
                                </div>

                                <!-- Middle: Title, Ext Badge & Filename -->
                                <div class="permit-doc-info">
                                    <div class="permit-doc-main-row">
                                        <span class="permit-doc-title text-truncate" :title="getDocTitle(doc)">
                                            {{ getDocTitle(doc) }}
                                        </span>
                                        <span class="permit-doc-ext-tag">{{ getDocExtension(doc) }}</span>
                                    </div>
                                    <div class="permit-doc-sub-row text-truncate">
                                        <HcisIcon name="paperclip" size="2xs" class="text-slate-400 flex-shrink-0" />
                                        <span class="text-truncate">{{ getDocFileName(doc) || getDocSubtitle(doc) || 'Dokumen Terlampir' }}</span>
                                    </div>
                                </div>

                                <!-- Right: Compact Action Button -->
                                <div class="permit-doc-action">
                                    <button
                                        type="button"
                                        class="permit-doc-view-pill"
                                        title="Buka Pratinjau Dokumen"
                                        @click.stop="openFileViewer(doc)"
                                    >
                                        <HcisIcon name="eye-fill" size="2xs" />
                                        <span class="d-none d-sm-inline">Lihat</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- Dialog Footer -->
            <template #footer-start>
                <div class="permit-detail-footer-start">
                    <KontakBantuanTombol
                        :kontak="kontakBantuan"
                        ringkas
                        judul="Butuh Bantuan?"
                        judul-dialog="Bantuan permit"
                        deskripsi-dialog="Hubungi kontak resmi untuk konsultasi pengajuan, jadwal inspeksi, atau status permit Anda."
                    />
                    <HcisButton
                        v-if="permitData && canCancelPermit(permitData)"
                        variant="danger-quiet"
                        size="md"
                        leading-icon="slash-circle"
                        label="Batalkan Pengajuan"
                        @click="openCancelConfirm(permitData)"
                    />
                </div>
            </template>

            <template #footer>
                <div class="permit-detail-footer-end d-flex align-items-center gap-2">
                    <HcisButton
                        v-if="permitData"
                        variant="secondary"
                        size="md"
                        leading-icon="printer"
                        label="Cetak Bukti"
                        @click="cetakBukti"
                    />
                    <HcisButton
                        v-if="permitData && permitData.Status_Permit === 'APPROVED'"
                        variant="primary"
                        size="md"
                        leading-icon="person-vcard-fill"
                        label="Buka Kartu E-Permit"
                        @click="openCardsTab"
                    />
                    <HcisButton
                        variant="secondary"
                        size="md"
                        leading-icon="x-lg"
                        label="Tutup"
                        class="permit-detail-footer-tutup"
                        @click="closeModal"
                    />
                </div>
            </template>
        </HcisDualPaneDialog>

        <!--
            Lembar cetak. Tidak pernah terlihat di layar: komponennya
            men-teleport dirinya ke <body> dan hanya muncul di @media print.
            Dirender hanya selama modal detail terbuka supaya aturan cetaknya
            tidak menggantung saat tidak ada yang mau dicetak.
        -->
        <PermitBuktiTransaksi
            v-if="showModal && permitData"
            ref="buktiRef"
            :permit="permitData"
            :approvals="approvals"
            :checklists="checklists"
            :dokumens="dokumens"
            :dicetak-oleh="namaPencetak"
        />

        <!-- ================================================================= -->
        <!-- CANCEL CONFIRMATION DIALOG                                        -->
        <!-- ================================================================= -->
        <HcisDialog
            v-model="showCancelModal"
            title="Batalkan Pengajuan Permit?"
            kicker="Konfirmasi Pembatalan"
            description="Pengajuan permit ini akan ditarik dari antrean persetujuan dan tidak dapat diproses lagi."
            icon="exclamation-triangle-fill"
            sentiment="danger"
            variant="glass"
            pattern="grid"
            size="md"
            sticky-footer
            :close-on-overlay="!isCanceling"
            :close-on-esc="!isCanceling"
            :show-close="!isCanceling"
            @close="closeCancelConfirm"
        >
            <div v-if="permitToCancel" class="permit-cancel-preview p-3 rounded-3 bg-red-50 border border-red-200">
                <div class="d-flex align-items-center gap-3">
                    <div class="permit-plate-badge">
                        <span class="permit-plate-badge__text">{{ permitToCancel.No_Plat_Kendaraan || '-' }}</span>
                    </div>
                    <div>
                        <div class="fw-bold text-slate-800 text-sm">{{ permitToCancel.Nama_Model_Manual || '-' }}</div>
                        <div class="text-xs text-muted font-tabular">No. Transaksi: {{ permitToCancel.No_Transaksi }}</div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="secondary"
                        size="md"
                        label="Kembali"
                        :disabled="isCanceling"
                        @click="closeCancelConfirm"
                    />
                    <HcisButton
                        variant="danger"
                        size="md"
                        leading-icon="slash-circle"
                        label="Ya, Batalkan Pengajuan"
                        :loading="isCanceling"
                        @click="confirmCancelPermit"
                    />
                </div>
            </template>
        </HcisDialog>

        <HcisDialog
            v-model="showPindahInspeksi"
            title="Pindah Jadwal Inspeksi"
            subtitle="Pilih tanggal dan slot waktu baru untuk pemeriksaan fisik kendaraan di pos SHE."
            kicker="Visitor · Penjadwalan Ulang"
            icon="calendar2-week-fill"
            sentiment="brand"
            size="lg"
        >
            <SlotInspeksiPicker
                v-model="slotPindah"
                :slots="slotInspeksi"
                :loading="slotLoading"
                :disabled="isPindahInspeksi"
            />
            <template #footer>
                <div class="d-flex justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="ghost"
                        size="md"
                        label="Batal"
                        :disabled="isPindahInspeksi"
                        @click="showPindahInspeksi = false"
                    />
                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="check-circle-fill"
                        label="Konfirmasi Pindah Jadwal"
                        :loading="isPindahInspeksi"
                        :disabled="!slotPindah"
                        @click="simpanPindahInspeksi"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- ================================================================= -->
        <!-- HCIS FILE VIEWER (FULL PREVIEW, ZOOM, ROTATE, DOWNLOAD)           -->
        <!-- ================================================================= -->
        <HcisFileViewer
            v-model="showFileViewer"
            :files="viewerFiles"
            :initial-index="viewerInitialIndex"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import PermitBuktiTransaksi from './PermitBuktiTransaksi.vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDataTable,
    HcisDialog,
    HcisDualPaneDialog,
    HcisEmptyState,
    HcisFileViewer,
    HcisIcon,
    HcisProgress,
    HcisSkeleton,
    HcisTimeline,
} from '@/components/ui';
import { salinTeks } from '@/utils/clipboard';
import SlotInspeksiPicker from '@/Pages/VISITOR/Permit/Inspection/components/SlotInspeksiPicker.vue';
import KontakBantuanTombol from '@/components/SYSTEM/KontakBantuan/KontakBantuanTombol.vue';

const STATUS_INSPEKSI = {
    TERJADWAL: { label: 'Terjadwal', variant: 'info' },
    SELESAI: { label: 'Sudah diinspeksi', variant: 'success' },
    TIDAK_HADIR: { label: 'Tidak hadir', variant: 'danger' },
    DIBATALKAN: { label: 'Dibatalkan', variant: 'neutral' },
    DIPINDAH: { label: 'Dipindah', variant: 'neutral' },
    DIPINDAH_PETUGAS: { label: 'Dipindah petugas', variant: 'neutral' },
};

export default {
    name: 'PermitStatusList',
    components: {
        KontakBantuanTombol,
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDataTable,
        HcisDialog,
        HcisDualPaneDialog,
        HcisEmptyState,
        HcisFileViewer,
        HcisIcon,
        HcisProgress,
        HcisSkeleton,
        HcisTimeline,
        PermitBuktiTransaksi,
        SlotInspeksiPicker,
    },
    props: {
        // Kontak resmi untuk konsultasi permit (VehiclePermitController::index).
        kontakBantuan: { type: Array, default: () => [] },
        /**
         * Dipakai hanya untuk baris "Dicetak ... oleh" pada Bukti Transaksi.
         * Opsional: bila tidak dioper, nama pemohon pada permitnya yang dipakai —
         * endpoint detail hanya mengembalikan permit milik pengguna itu sendiri.
         */
        user: {
            type: Object,
            default: null,
        },
        aturanInspeksi: { type: Object, default: () => ({}) },
    },
    emits: ['permit-canceled', 'navigate-to-form', 'navigate-to-cards'],
    data() {
        return {
            permits: [],
            loading: true,
            fetchError: '',

            // Filter & Search
            activeChip: 'ALL',
            searchQuery: '',
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',
            currentPage: 1,
            itemsPerPage: 10,

            // Detail Modal State
            showModal: false,
            detailLoading: false,
            selectedItem: null,
            permitData: null,
            dokumens: [],
            approvals: [],
            checklists: [],
            inspeksi: null,

            // Pindah jadwal inspeksi
            showPindahInspeksi: false,
            slotInspeksi: [],
            slotLoading: false,
            slotPindah: null,
            isPindahInspeksi: false,

            // In-app Document Viewer State
            showFileViewer: false,
            viewerFiles: [],
            viewerInitialIndex: 0,

            // Cancellation Modal State
            showCancelModal: false,
            permitToCancel: null,
            isCanceling: false,

            columns: [
                { key: 'Plat_Kendaraan', label: 'Kendaraan & Plat', minWidth: '220px' },
                { key: 'Nama_Kategori', label: 'Kategori Permit', width: '160px' },
                { key: 'No_Transaksi', label: 'No. Transaksi', width: '180px' },
                { key: 'Masa_Berlaku', label: 'Masa Berlaku & Sisa', width: '210px' },
                { key: 'Status_Permit', label: 'Status', width: '140px', align: 'center' },
                { key: 'actions', label: 'Aksi', width: '160px', align: 'center' },
            ],
        };
    },
    computed: {
        /**
         * Rincian kunjungan asal izin ini — hanya baris yang benar-benar terisi.
         *
         * Baris kosong berlabel '-' membuat pemohon menduga datanya hilang,
         * padahal memang tidak pernah dikirim: pengajuan lewat aplikasi mitra
         * tidak selalu membawa nomor identitas tamunya.
         */
        asalPengajuan() {
            const p = this.permitData || {};
            const baris = [];

            if (p.Nama_Tamu) baris.push({ label: 'Nama Tamu', nilai: p.Nama_Tamu });
            if (p.Instansi_Tamu) baris.push({ label: 'Instansi / Asal', nilai: p.Instansi_Tamu });
            if (p.No_Identitas_Tamu) {
                baris.push({
                    label: p.Jenis_Identitas_Tamu || 'No. Identitas',
                    nilai: p.No_Identitas_Tamu,
                });
            }
            if (p.No_Transaksi_Tamu) baris.push({ label: 'No. Kunjungan', nilai: p.No_Transaksi_Tamu });
            if (p.Referensi_Mitra) baris.push({ label: 'Referensi Mitra', nilai: p.Referensi_Mitra });
            if (p.Keterangan_Pengajuan) baris.push({ label: 'Keperluan', nilai: p.Keterangan_Pengajuan });

            return baris;
        },
        /**
         * Sebab pencabutan dalam satu kalimat yang bisa dibaca pemohon.
         *
         * Alasannya wajib diisi verifikator, jadi bagian "tidak tercatat" hanya
         * berlaku untuk baris warisan sebelum aturan itu ada — bukan keadaan
         * yang normal.
         */
        /**
         * Catatan bebas milik pencabutnya — ditampilkan APA ADANYA, terkutip.
         *
         * Tidak lagi disambung dengan siapa/kapan dan akibatnya: ketiganya kini
         * punya barisnya sendiri di banner detail, sebab hanya yang satu ini
         * yang ditulis manusia.
         */
        alasanPencabutan() {
            return (this.permitData || {}).Alasan_Pencabutan || 'Alasan tidak tercatat.';
        },
        counts() {
            const list = this.permits || [];
            return {
                total: list.length,
                approved: list.filter((p) => p.Status_Permit === 'APPROVED').length,
                pending: list.filter((p) => p.Status_Permit === 'PENDING').length,
                rejected: list.filter((p) => p.Status_Permit === 'REJECTED').length,
                expired: list.filter((p) => p.Status_Permit === 'EXPIRED').length,
                revoked: list.filter((p) => p.Status_Permit === 'REVOKED').length,
            };
        },
        quickChips() {
            return [
                {
                    id: 'ALL',
                    value: 'ALL',
                    label: 'Semua Permit',
                    badge: this.counts.total,
                },
                {
                    id: 'APPROVED',
                    value: 'APPROVED',
                    label: 'Permit Aktif',
                    color: 'emerald',
                    icon: 'check-circle-fill',
                    badge: this.counts.approved,
                },
                {
                    id: 'PENDING',
                    value: 'PENDING',
                    label: 'Menunggu Approval',
                    color: 'amber',
                    icon: 'hourglass-split',
                    ping: this.counts.pending > 0,
                    badge: this.counts.pending,
                },
                {
                    id: 'REJECTED',
                    value: 'REJECTED',
                    label: 'Ditolak',
                    color: 'red',
                    icon: 'x-circle-fill',
                    badge: this.counts.rejected,
                },
                {
                    id: 'EXPIRED',
                    value: 'EXPIRED',
                    label: 'Kadaluarsa',
                    color: 'slate',
                    icon: 'clock-history',
                    badge: this.counts.expired,
                },
                // Pil ini hanya muncul kalau memang ada yang dicabut: menambah
                // saringan permanen yang selalu nol hanya menambah kebisingan.
                ...(this.counts.revoked > 0
                    ? [{
                        id: 'REVOKED',
                        value: 'REVOKED',
                        label: 'Dicabut',
                        color: 'red',
                        icon: 'slash-circle-fill',
                        badge: this.counts.revoked,
                    }]
                    : []),
            ];
        },
        filteredPermits() {
            let result = this.permits || [];

            // Filter by quick chip status
            if (this.activeChip && this.activeChip !== 'ALL') {
                result = result.filter((p) => p.Status_Permit === this.activeChip);
            }

            // Filter by search query
            if (this.searchQuery && this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                result = result.filter((p) => {
                    const plate = (p.No_Plat_Kendaraan || '').toLowerCase();
                    const model = (p.Nama_Model_Manual || '').toLowerCase();
                    const trans = (p.No_Transaksi || '').toLowerCase();
                    const kategori = (p.Nama_Kategori || '').toLowerCase();
                    const type = (p.Nama_Vehicle_Type || '').toLowerCase();
                    const desc = (p.Keterangan_Pengajuan || p.Keterangan || '').toLowerCase();
                    return (
                        plate.includes(q) ||
                        model.includes(q) ||
                        trans.includes(q) ||
                        kategori.includes(q) ||
                        type.includes(q) ||
                        desc.includes(q)
                    );
                });
            }

            return result;
        },
        paginatedData() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredPermits.slice(start, start + this.itemsPerPage);
        },
        totalPages() {
            return Math.ceil(this.filteredPermits.length / this.itemsPerPage) || 1;
        },
        namaPencetak() {
            return this.user?.Nama || this.permitData?.Nama_Pemohon || '';
        },
        inspeksiStatus() {
            if (this.inspeksi?.gugur) return { label: 'Permit gugur', variant: 'danger' };
            return STATUS_INSPEKSI[this.inspeksi?.status] || { label: this.inspeksi?.status || '-', variant: 'neutral' };
        },
        maksJadwalUlang() {
            return this.aturanInspeksi?.maks_jadwal_ulang ?? 2;
        },
        approvalTimelineItems() {
            if (!this.approvals || !this.approvals.length) return [];
            return this.approvals.map((step, idx) => {
                let status = 'Menunggu';
                let sentiment = 'warning';
                let icon = 'hourglass-split';
                const isExpired = step.Expired_At && new Date(step.Expired_At) < new Date();

                if (step.Flag_Approval === 'Y') {
                    status = 'Disetujui';
                    sentiment = 'success';
                    icon = 'check-circle-fill';
                } else if (step.Flag_Approval === 'T') {
                    status = 'Ditolak';
                    sentiment = 'danger';
                    icon = 'x-circle-fill';
                } else if (isExpired) {
                    status = 'Kadaluarsa';
                    sentiment = 'danger';
                    icon = 'clock-history';
                }

                const approvalDate = step.Tanggal_Approval || step.Updated_At;
                const formattedTime = (step.Flag_Approval || isExpired) && approvalDate
                    ? this.formatDateTime(approvalDate)
                    : null;

                const approverName = step.Nama_Approved_By
                    || step.Nama_Approver
                    || step.Nama_Approver_Karyawan
                    || step.Nama_Role_Functional
                    || step.Approver_Karyawan_Kode
                    || 'Approver Otoritas';

                return {
                    id: step.Id_Approval_Request || idx,
                    title: step.Nama_Approval || `Tahap Verifikasi ${idx + 1}`,
                    name: approverName,
                    status,
                    sentiment,
                    icon,
                    time: formattedTime,
                    description: step.Catatan && step.Catatan.trim() ? step.Catatan.trim() : null,
                    active: !step.Flag_Approval && !isExpired,
                };
            });
        },
    },
    mounted() {
        this.detectInitialViewMode();
        this.fetchData();
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', this.onWindowResize);
        }
    },
    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', this.onWindowResize);
        }
    },
    methods: {
        detectInitialViewMode() {
            if (typeof window !== 'undefined') {
                this.viewMode = window.innerWidth < 768 ? 'grid' : 'table';
            }
        },
        onWindowResize() {
            // Respect user manual toggle if they have interacted, or keep mode aligned
        },
        async fetchData() {
            this.loading = true;
            this.fetchError = '';
            try {
                const { data } = await axios.get('/transaksipermit-my');
                if (data && data.success) {
                    this.permits = data.result || [];
                } else {
                    throw new Error(data?.message || 'Gagal memuat data permit.');
                }
            } catch (e) {
                this.fetchError = e.response?.data?.message || e.message || 'Gagal mengambil permit Anda.';
                ElMessage.error(this.fetchError);
            } finally {
                this.loading = false;
            }
        },
        onSearchUpdate(val) {
            this.searchQuery = val || '';
            this.currentPage = 1;
        },
        onChipClick(chip) {
            this.activeChip = chip.value || 'ALL';
            this.currentPage = 1;
        },
        onViewModeChange(mode) {
            this.viewMode = mode;
        },
        changePage(page) {
            const num = typeof page === 'object' ? page?.page ?? page?.currentPage : page;
            this.currentPage = Number(num) || 1;
        },
        onSizeChange(size) {
            this.itemsPerPage = Number(size) || 10;
            this.currentPage = 1;
        },
        resetFilter() {
            this.activeChip = 'ALL';
            this.searchQuery = '';
            this.currentPage = 1;
        },
        async viewDetail(item) {
            if (!item) return;
            this.selectedItem = item;
            this.showModal = true;
            this.detailLoading = true;
            try {
                const id = item.Id || item.Id_Transaksi_Permit;
                if (!id) {
                    this.closeModal();
                    return;
                }
                const { data } = await axios.get(`/transaksipermit/${id}`);
                if (data && data.success) {
                    this.permitData = data.result.permit || item;
                    this.dokumens = data.result.dokumens || [];
                    this.approvals = data.result.approvals || [];
                    this.checklists = data.result.checklists || [];
                    this.inspeksi = data.result.inspeksi || null;
                } else {
                    throw new Error(data?.message || 'Gagal memuat detail.');
                }
            } catch (e) {
                ElMessage.error('Gagal memuat detail permit');
                this.closeModal();
            } finally {
                this.detailLoading = false;
            }
        },
        /**
         * Mencetak lewat komponen buktinya, bukan window.print() langsung:
         * penukaran judul dokumen dan seluruh aturan halaman ada di sana, dan
         * memanggil print() dari sini akan melewatinya.
         */
        cetakBukti() {
            this.$refs.buktiRef?.cetak();
        },
        closeModal() {
            this.showModal = false;
            this.selectedItem = null;
            this.permitData = null;
            this.dokumens = [];
            this.approvals = [];
            this.checklists = [];
            this.inspeksi = null;
        },
        formatJadwalInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const [tanggal, jam] = inspeksi.mulai.split(' ');
            const [y, m, d] = tanggal.split('-').map(Number);
            const label = new Date(y, m - 1, d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            return `${label}, ${jam}–${String(inspeksi.selesai || '').slice(11, 16)}`;
        },
        formatTanggalInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const [tanggal] = String(inspeksi.mulai).split(' ');
            const [y, m, d] = tanggal.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        formatJamInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const jamMulai = String(inspeksi.mulai).slice(11, 16);
            const jamSelesai = inspeksi.selesai ? String(inspeksi.selesai).slice(11, 16) : '';
            return jamSelesai ? `${jamMulai} – ${jamSelesai}` : jamMulai;
        },
        async bukaPindahInspeksi() {
            this.slotPindah = null;
            this.showPindahInspeksi = true;
            this.slotLoading = true;
            try {
                // Lihat catatan di PermitForm: slot penuh/lewat ikut, bertanda 'keadaan'.
                const { data } = await axios.get('/transaksipermit-slot-inspeksi', { params: { semua: 1 } });
                this.slotInspeksi = data?.result?.slot || [];
            } catch (e) {
                this.slotInspeksi = [];
                ElMessage.error(e?.response?.data?.message || 'Slot inspeksi gagal dimuat.');
            } finally {
                this.slotLoading = false;
            }
        },
        async simpanPindahInspeksi() {
            if (!this.slotPindah || !this.permitData) return;
            this.isPindahInspeksi = true;
            try {
                const id = this.permitData.Id_Transaksi_Permit || this.permitData.Id;
                const { data } = await axios.post(`/transaksipermit/${id}/pindah-inspeksi`, {
                    inspeksi_id_pos: this.slotPindah.id_pos,
                    inspeksi_mulai: this.slotPindah.mulai,
                });
                this.inspeksi = data?.result?.inspeksi || this.inspeksi;
                this.showPindahInspeksi = false;
                ElMessage.success(data?.message || 'Jadwal inspeksi berhasil dipindah.');
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Jadwal inspeksi gagal dipindah.');
                if (e?.response?.data?.error_code === 'VISITOR_PERMIT_SLOT_INVALID') {
                    this.bukaPindahInspeksi();
                }
            } finally {
                this.isPindahInspeksi = false;
            }
        },
        openCardsTab() {
            this.closeModal();
            this.$emit('navigate-to-cards');
        },
        /**
         * Izin turunan kunjungan tamu — terbit atas nama pemohon, bukan untuknya.
         *
         * Dikenali dari TAUTANNYA ke kunjungan, bukan dari kategori permitnya.
         * Kategori COKLAT juga dipakai permit karyawan biasa, jadi mencabangkan
         * lewat kategori akan menandai izin yang salah.
         */
        dariKunjungan(item) {
            return Boolean(item && item.No_Transaksi_Tamu);
        },
        canCancelPermit(item) {
            if (!item) return false;
            // Izin tamu terikat pada kunjungannya. Membatalkannya sendirian
            // meninggalkan kunjungan yang tetap terjadwal tanpa izin gerbang,
            // jadi servernya menolak — dan tombol yang tetap menyala hanya
            // menghasilkan galat yang tidak bisa dimengerti pemakainya.
            if (this.dariKunjungan(item)) return false;
            // Yang sudah selesai — termasuk yang kedaluwarsa — tinggal arsip.
            if (['APPROVED', 'REJECTED', 'REVOKED', 'EXPIRED'].includes(item.Status_Permit)) return false;
            // Paling lambat H-1, sama seperti jadwal ulang; servernya juga menolak.
            // Detail memuat ringkasan inspeksinya terpisah dari barisnya.
            const inspeksi = item.inspeksi ?? (item === this.permitData ? this.inspeksi : null);
            if (inspeksi && inspeksi.boleh_batal === false) return false;

            return item.Status_Permit === 'PENDING' || (item.Flag_Approval === null && item.Status_Permit !== 'REJECTED' && item.Status_Permit !== 'APPROVED');
        },
        openCancelConfirm(item) {
            this.permitToCancel = item;
            this.showCancelModal = true;
        },
        closeCancelConfirm() {
            this.showCancelModal = false;
            this.permitToCancel = null;
        },
        async confirmCancelPermit() {
            if (!this.permitToCancel) return;
            this.isCanceling = true;
            try {
                const id = this.permitToCancel.Id || this.permitToCancel.Id_Transaksi_Permit;
                const { data } = await axios.post(`/transaksipermit/${id}/cancel`);
                if (data && data.success) {
                    ElMessage.success(data.message || 'Pengajuan permit berhasil dibatalkan.');
                    this.closeCancelConfirm();
                    if (this.showModal) this.closeModal();
                    this.fetchData();
                    this.$emit('permit-canceled');
                } else {
                    throw new Error(data?.message || 'Gagal membatalkan pengajuan.');
                }
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Pengajuan permit gagal dibatalkan.';
                ElMessage.error(msg);
            } finally {
                this.isCanceling = false;
            }
        },
        openFileViewer(targetDoc) {
            if (!this.dokumens || !this.dokumens.length) return;

            const targetIdx = this.dokumens.findIndex(
                (d) => d === targetDoc || (targetDoc && d.Id_Permit_Dokumen === targetDoc.Id_Permit_Dokumen)
            );

            this.viewerFiles = this.dokumens.map((d) => {
                const path = d.File_Path || d.Path_File_URL || '';
                const cleanPath = path ? (path.startsWith('http') ? path : `/storage/${path.replace(/^\/?(public\/|storage\/)?/, '')}`) : '';
                const url = d.File_Url || cleanPath;
                const realFileName = this.getDocFileName(d);
                const ext = this.getDocExtension(d).toLowerCase();
                const displayName = realFileName || `${this.getDocTitle(d)}.${ext}`;

                return {
                    url,
                    name: displayName.includes('.') ? displayName : `${displayName}.${ext}`,
                    title: this.getDocTitle(d),
                    ext: ext,
                    allowDownload: true,
                };
            });

            this.viewerInitialIndex = targetIdx >= 0 ? targetIdx : 0;
            this.showFileViewer = true;
        },
        /**
         * Bentuk lamanya menyerah DIAM ketika navigator.clipboard tidak ada —
         * tanpa galat, tanpa pesan. Itu sama merugikannya dengan yang melempar:
         * pemakainya sama-sama menekan tombol yang tidak melakukan apa pun.
         */
        async copyToClipboard(text) {
            const isi = String(text ?? '').trim();
            if (isi === '') return;

            if (await salinTeks(isi)) {
                ElMessage.success(`No. Transaksi ${isi} disalin`);

                return;
            }

            ElMessage.warning(`No. Transaksi gagal disalin otomatis. Salin manual: ${isi}`);
        },
        getVehicleIcon(typeName = '') {
            const norm = (typeName || '').toLowerCase();
            if (norm.includes('motor') || norm.includes('sepeda')) return 'bicycle';
            if (norm.includes('truk') || norm.includes('truck')) return 'truck';
            return 'car-front-fill';
        },
        getVehicleColorHex(colorName = '') {
            const norm = (colorName || '').toLowerCase();
            if (norm.includes('hitam')) return '#1e293b';
            if (norm.includes('putih')) return '#f8fafc';
            if (norm.includes('merah')) return '#dc2626';
            if (norm.includes('biru')) return '#2563eb';
            if (norm.includes('abu') || norm.includes('silver') || norm.includes('perak')) return '#94a3b8';
            if (norm.includes('hijau')) return '#16a34a';
            if (norm.includes('kuning') || norm.includes('gold')) return '#eab308';
            if (norm.includes('coklat') || norm.includes('brown')) return '#854d0e';
            return '#6366f1';
        },
        getHeroCardStyle(permit) {
            if (!permit) return {};
            const color = permit.Kode_Warna || '#10b981';
            return {
                '--hero-color': color,
                '--hero-tint': `${color}14`,
                '--hero-tint-border': `${color}38`,
            };
        },
        getCategoryBadgeStyle(permit) {
            if (!permit) return {};
            const color = permit.Kode_Warna || '#10b981';
            return {
                backgroundColor: `${color}14`,
                borderColor: `${color}40`,
                color: color,
                border: `1px solid ${color}40`,
            };
        },
        getCategoryStyle(row) {
            const color = row.Kode_Warna || '#6366f1';
            return {
                borderColor: `${color}40`,
                backgroundColor: `${color}14`,
                color: color,
            };
        },
        getStatusLabel(status) {
            switch (status) {
                case 'APPROVED':
                    return 'Aktif';
                case 'PENDING':
                    return 'Menunggu Approval';
                case 'REJECTED':
                    return 'Ditolak';
                case 'REVOKED':
                    return 'Dicabut';
                case 'EXPIRED':
                    return 'Expired';
                case 'NO_FLOW':
                    return 'No Flow';
                default:
                    return status || '-';
            }
        },
        getCardSentiment(status) {
            const s = String(status || '').toUpperCase();
            if (['APPROVED', 'ACTIVE', 'AKTIF', 'BERLAKU', 'DISETUJUI'].includes(s)) {
                return 'success';
            }
            if (['PENDING', 'MENUNGGU', 'MENUNGGU_APPROVAL', 'WAITING'].includes(s)) {
                return 'warning';
            }
            if (['REJECTED', 'REVOKED', 'EXPIRED', 'DITOLAK', 'DICABUT', 'KADALUARSA', 'DIBATALKAN', 'CANCELLED'].includes(s)) {
                return 'danger';
            }
            return 'brand';
        },
        isPermitActive(item) {
            const s = String(item?.Status_Permit || '').toUpperCase();
            return ['APPROVED', 'ACTIVE', 'AKTIF', 'BERLAKU', 'DISETUJUI'].includes(s);
        },
        getRemainingDays(item) {
            if (item.Sisa_Hari !== null && item.Sisa_Hari !== undefined && item.Sisa_Hari !== '') {
                return Number(item.Sisa_Hari);
            }
            if (!item.Tanggal_Kadaluarsa) return null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const exp = new Date(item.Tanggal_Kadaluarsa);
            exp.setHours(0, 0, 0, 0);
            return Math.ceil((exp - today) / (1000 * 60 * 60 * 24));
        },
        getRemainingBadgeLabel(item) {
            const days = this.getRemainingDays(item);
            if (days === null || Number.isNaN(days)) return '-';
            if (days < 0) return 'Kadaluarsa';
            if (days === 0) return 'Habis Hari Ini';
            if (days <= 7) return `Sisa ${days} hari (Kritis)`;
            return `Sisa ${days} hari`;
        },
        getRemainingVariant(item) {
            const days = this.getRemainingDays(item);
            if (days === null || Number.isNaN(days)) return 'neutral';
            if (days < 0) return 'danger';
            if (days <= 7) return 'danger';
            if (days <= 30) return 'warning';
            return 'success';
        },
        getRemainingIcon(item) {
            const days = this.getRemainingDays(item);
            if (days === null || Number.isNaN(days)) return 'dash-circle';
            if (days < 0) return 'clock-history';
            if (days <= 30) return 'hourglass-split';
            return 'check-circle-fill';
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            try {
                return new Date(dateStr).toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });
            } catch (e) {
                return dateStr;
            }
        },
        formatDateTime(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                const datePart = d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
                const timePart = d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).replace('.', ':');
                return `${datePart}, ${timePart} WIB`;
            } catch (e) {
                return dateStr;
            }
        },
        getDocTitle(doc) {
            if (!doc) return 'Dokumen';
            const jenis = (doc.Jenis_Dokumen || '').toUpperCase();
            if (jenis === 'FOTO_KENDARAAN') return 'Foto Kendaraan';
            if (jenis === 'STNK') return 'STNK Kendaraan';
            if (jenis === 'SIM') return 'SIM Pemohon';
            return doc.Jenis_Dokumen || doc.File_Name || 'Berkas Dokumen';
        },
        getDocSubtitle(doc) {
            if (!doc) return '';
            const jenis = (doc.Jenis_Dokumen || '').toUpperCase();
            if (jenis === 'FOTO_KENDARAAN') return 'Tampak fisik kendaraan & plat';
            if (jenis === 'STNK') return 'Surat Tanda Nomor Kendaraan';
            if (jenis === 'SIM') return 'Surat Izin Mengemudi pemohon';
            return 'Dokumen kelengkapan permit';
        },
        getDocFileName(doc) {
            if (!doc) return '';
            const path = doc.Path_File_URL || doc.File_Path || doc.File_Url || '';
            if (path) {
                const base = path.split('?')[0].split('#')[0].split('/').pop();
                if (base && base !== doc.Jenis_Dokumen) {
                    return base;
                }
            }
            if (doc.File_Name && doc.File_Name !== doc.Jenis_Dokumen) {
                return doc.File_Name;
            }
            return '';
        },
        getDocExtension(doc) {
            if (!doc) return 'DOC';
            const path = doc.Path_File_URL || doc.File_Path || doc.File_Url || doc.File_Name || '';
            if (path) {
                const clean = path.split('?')[0].split('#')[0];
                const parts = clean.split('.');
                if (parts.length > 1) {
                    const ext = parts.pop().toUpperCase();
                    if (ext.length <= 5 && !ext.includes('/')) {
                        return ext;
                    }
                }
            }
            const jenis = (doc.Jenis_Dokumen || '').toUpperCase();
            if (jenis === 'FOTO_KENDARAAN') return 'JPG';
            if (jenis === 'STNK') return 'PDF';
            if (jenis === 'SIM') return 'JPG';
            return 'DOC';
        },
        getDocIcon(jenis = '') {
            const j = (jenis || '').toUpperCase();
            if (j.includes('KENDARAAN')) return 'car-front-fill';
            if (j.includes('STNK')) return 'file-earmark-richtext-fill';
            if (j.includes('SIM')) return 'person-vcard-fill';
            return 'file-earmark-text-fill';
        },
        getDocIconBg(jenis = '') {
            const j = (jenis || '').toUpperCase();
            if (j.includes('KENDARAAN')) return 'permit-doc-icon--brand';
            if (j.includes('STNK')) return 'permit-doc-icon--teal';
            if (j.includes('SIM')) return 'permit-doc-icon--purple';
            return 'permit-doc-icon--slate';
        },
        getInitials(name) {
            if (!name) return 'K';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.slice(0, 2).toUpperCase();
        },
        getRejectionComment() {
            if (!this.approvals || !this.approvals.length) return '';
            const rejected = this.approvals.find((a) => a.Flag_Approval === 'T' && a.Catatan);
            return rejected ? rejected.Catatan : '';
        },
    },
};
</script>

<style scoped>
/*
 * Footer dialog detail permit.
 *
 * Tidak scoped: dialog di-teleport ke <body>. Disaring lewat kelas milik
 * halaman ini (permit-detail-footer-*), jadi dialog lain tidak terpengaruh.
 */
:global(.permit-detail-footer-start),
:global(.permit-detail-footer-end) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Tombol bantuan selaras dengan HcisButton md di footer yang sama */
:global(.permit-detail-footer-start .kontak-bantuan-tombol) {
    height: 2.375rem !important;
    min-height: 2.375rem !important;
    border-radius: var(--hcis-radius-lg, 10px) !important;
    padding: 0 1rem 0 0.85rem !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.45rem !important;
    background: #ffffff !important;
    border: 1px solid var(--hcis-slate-300, #cbd5e1) !important;
    color: var(--hcis-slate-700, #334155) !important;
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
    transition: all 0.15s ease !important;
}

:global(.permit-detail-footer-start .kontak-bantuan-tombol:hover) {
    background: var(--hcis-slate-50, #f8fafc) !important;
    border-color: var(--hcis-slate-400, #94a3b8) !important;
    color: var(--hcis-slate-900, #0f172a) !important;
}

:global(.permit-detail-footer-start .kontak-bantuan-tombol .kontak-bantuan-tombol__teks) {
    display: inline-flex !important;
    align-items: center !important;
    margin: 0 !important;
}

:global(.permit-detail-footer-start .kontak-bantuan-tombol .kontak-bantuan-tombol__judul) {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: inherit !important;
    line-height: 1 !important;
}

:global(.permit-detail-footer-start .kontak-bantuan-tombol .kontak-bantuan-tombol__ikon) {
    margin: 0 !important;
    font-size: 1rem !important;
}

/* Tablet & layar menengah: jaga agar footer tetap terpisah kiri-kanan rapi */
@media (min-width: 641px) and (max-width: 991.98px) {
    :global(.hcis-dual-pane__footer-wrapper:has(.permit-detail-footer-end)) {
        justify-content: space-between !important;
    }

    :global(.hcis-dual-pane__footer-wrapper:has(.permit-detail-footer-end) .hcis-dual-pane__footer-end) {
        width: auto !important;
        flex: 0 0 auto !important;
        margin-left: auto !important;
        justify-content: flex-end !important;
    }
}

/* Layar HP: dua baris selebar layar dan "Tutup" disembunyikan (✕ di kepala dialog sudah ada) */
@media (max-width: 640px) {
    :global(.hcis-dual-pane__footer-wrapper:has(.permit-detail-footer-end)) {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }

    :global(.hcis-dual-pane__footer-wrapper:has(.permit-detail-footer-end) .hcis-dual-pane__footer-start),
    :global(.hcis-dual-pane__footer-wrapper:has(.permit-detail-footer-end) .hcis-dual-pane__footer-end) {
        width: 100% !important;
    }

    :global(.permit-detail-footer-start),
    :global(.permit-detail-footer-end) {
        display: flex !important;
        width: 100%;
        gap: 0.5rem;
    }

    :global(.permit-detail-footer-start > *),
    :global(.permit-detail-footer-end > *) {
        flex: 1 1 0;
        min-width: 0;
    }

    :global(.permit-detail-footer-start .kontak-bantuan-tombol),
    :global(.permit-detail-footer-start .hcis-btn),
    :global(.permit-detail-footer-end .hcis-btn) {
        width: 100%;
        justify-content: center;
    }

    :global(.permit-detail-footer-end .permit-detail-footer-tutup) {
        display: none !important;
    }
}
</style>

<style scoped>
/* ── Penanda izin milik tamu ─────────────────────────────────────────────
   Dipakai di tabel, kartu, dan detail. Satu kosakata visual untuk satu
   fakta; penanda yang berbeda bentuk di tiap tampilan terbaca sebagai hal
   yang berbeda. */
.permit-tamu-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.125rem 0.4rem;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.3;
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
    max-width: 100%;
}

.permit-tamu-note {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.5rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    line-height: 1.45;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
}

.permit-asal-grid {
    display: grid;
    gap: 0.625rem;
}

.permit-asal-item {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.permit-asal-item__label {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--hcis-text-muted, #64748b);
}

.permit-asal-item__val {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
    word-break: break-word;
}

.permit-asal-catatan {
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #64748b);
}

.permit-revoked-box {
    background: var(--hcis-danger-50, var(--hcis-red-50));
    border: 1px solid var(--hcis-danger-200, var(--hcis-red-200));
    color: var(--hcis-danger-700, var(--hcis-red-700));
}

.permit-revoked-box__text {
    font-size: 0.75rem;
    line-height: 1.35;
    color: var(--hcis-danger-700, var(--hcis-red-700));
}

.permit-revoked-box__meta {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.6875rem;
    color: var(--hcis-danger-600, var(--hcis-red-600));
    opacity: 0.85;
}

/* Banner pencabutan di modal detail: catatan, keterangan, akibat. */
.permit-revoked-detail {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.permit-revoked-detail__note {
    margin: 0;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    border-inline-start: 3px solid var(--hcis-danger-400, var(--hcis-red-400));
    background: var(--hcis-danger-50, var(--hcis-red-50));
}

.permit-revoked-detail__note-label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.2rem;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-danger-600, var(--hcis-red-600));
}

.permit-revoked-detail__note-text {
    font-size: 0.8125rem;
    line-height: 1.5;
    font-style: italic;
    color: var(--hcis-danger-800, var(--hcis-red-800));
    overflow-wrap: anywhere;
}

.permit-revoked-detail__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 1.5rem;
    margin: 0;
}

.permit-revoked-detail__meta-item dt {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-danger-600, var(--hcis-red-600));
}

.permit-revoked-detail__meta-item dd {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-danger-800, var(--hcis-red-800));
}

.permit-revoked-detail__akibat {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--hcis-danger-700, var(--hcis-red-700));
}

/* =========================================================================
   WORKSPACE & ENTRY ANIMATION
   ========================================================================= */
.permit-status-workspace {
    width: 100%;
    position: relative;
}

.fade-in {
    animation: permitWorkspaceFade 0.25s ease-out;
}

@keyframes permitWorkspaceFade {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* =========================================================================
   LICENSE PLATE BADGE (HCIS SIGNATURE AUTOMOTIVE BADGE)
   ========================================================================= */
.permit-plate-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%);
    color: var(--hcis-slate-50);
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 800;
    font-size: 0.8125rem;
    letter-spacing: 0.08em;
    padding: 0.28rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1.5px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    text-transform: uppercase;
    white-space: nowrap;
}

.permit-plate-badge--lg {
    font-size: 1rem;
    padding: 0.4rem 0.9rem;
    border-radius: var(--hcis-radius-lg, 10px);
}

.permit-cell-vehicle {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 0.75rem);
}

.permit-vehicle-details {
    min-width: 0;
}

.permit-vehicle-name {
    font-size: 0.8125rem;
    line-height: 1.35;
}

/* =========================================================================
   CATEGORY PILL WITH GLOW
   ========================================================================= */
.permit-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: var(--hcis-radius-full, 9999px);
    border: 1px solid transparent;
    font-size: 0.75rem;
    font-weight: 750;
    line-height: 1.2;
    white-space: nowrap;
}

.permit-category-pill__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 6px currentColor;
}

/* =========================================================================
   TRANSACTION CODE & COPY BUTTON
   ========================================================================= */
.permit-copy-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.permit-copy-btn:hover {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
}

/* =========================================================================
   ACTIONS CELL & BUTTONS
   ========================================================================= */
.permit-cell-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    white-space: nowrap;
    flex-wrap: nowrap;
}

:deep(.permit-action-btn) {
    font-weight: 600;
    white-space: nowrap;
}

:deep(.permit-action-btn--detail) {
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

:deep(.permit-action-btn--detail:hover) {
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    border-color: var(--hcis-brand-300, var(--hcis-indigo-300));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.permit-card-actions-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

:deep(.permit-card-btn) {
    font-weight: 600;
}

/* =========================================================================
   GRID / CARD VIEW STYLING
   ========================================================================= */
.permit-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--hcis-space-4, 1rem);
}

.permit-grid-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.permit-grid-card:hover {
    transform: translateY(-3px);
}

.permit-grid-card.is-active-card,
.permit-grid-card.hcis-card--sentiment-success {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(16, 185, 129, 0.22), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(5, 150, 105, 0.16), transparent 55%),
        linear-gradient(225deg, rgba(236, 253, 245, 0.96) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(209, 250, 229, 0.75) 100%) !important;
    border: 1.5px solid rgba(16, 185, 129, 0.45) !important;
    border-left: 4.5px solid var(--hcis-success-500, var(--hcis-emerald-500)) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
}

.permit-grid-card.is-active-card:hover,
.permit-grid-card.hcis-card--sentiment-success:hover {
    border-color: rgba(16, 185, 129, 0.65) !important;
    box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
}

.permit-card-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.permit-card-spec-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem 0.85rem;
    padding: 0.75rem;
    background: rgba(248, 250, 252, 0.7);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
}

.permit-spec-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.permit-spec-item__label {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    margin-bottom: 0.15rem;
}

.permit-spec-item__val {
    font-size: 0.8125rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.permit-card-validity-box {
    background: var(--hcis-green-50);
    border: 1px solid var(--hcis-green-200);
}

/* =========================================================================
   DETAIL MODAL HERO CARD & SECTIONS
   ========================================================================= */

.permit-hero-card {
    background: linear-gradient(135deg, var(--hero-tint, rgba(16, 185, 129, 0.06)) 0%, var(--hcis-white) 50%, var(--hcis-slate-50) 100%);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-2xl, 16px);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.04);
    position: relative;
    overflow: hidden;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}

.permit-hero-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.permit-hero-brand-group {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    overflow: hidden;
}

.permit-hero-brand-meta {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    overflow: hidden;
}

.permit-hero-icon-box {
    width: 3rem;
    height: 3rem;
    border-radius: var(--hcis-radius-xl, 12px);
    background: var(--hero-tint, var(--hcis-emerald-50));
    border: 1px solid var(--hero-tint-border, var(--hcis-emerald-200));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hero-color, var(--hcis-emerald-500));
    flex-shrink: 0;
}

.permit-hero-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.2rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.permit-hero-category-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hero-color, var(--hcis-emerald-500));
    box-shadow: 0 0 6px var(--hero-color, var(--hcis-emerald-500));
}

.permit-hero-category-text {
    font-weight: 750;
}

.permit-hero-vehicle-type {
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-weight: 500;
    font-size: 0.75rem;
    line-height: 1.25;
}

/* Middle Specs Container */
.permit-hero-details-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.permit-hero-plate-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.875rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.permit-hero-plate-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.permit-hero-plate-badge-tag {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-hero-subpod-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.permit-hero-subpod {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.75rem 0.875rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.permit-hero-card-kicker {
    display: block;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 750;
    font-size: 0.625rem;
    line-height: 1.2;
}

.permit-hero-plate-number {
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.2;
}

.permit-hero-subpod-val {
    font-size: 0.8125rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.25;
}

.permit-hero-meta-bar {
    border-top: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    padding-top: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.permit-hero-transaksi {
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.permit-hero-date {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* =========================================================================
   REQUESTER & DEPARTMENT PROFILE CARD
   ========================================================================= */
.permit-requester-card {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.permit-requester-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.permit-requester-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--hcis-radius-xl, 12px);
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9375rem;
    letter-spacing: 0.04em;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.1);
}

.permit-requester-identity {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex-grow: 1;
}

.permit-requester-name {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
}

.permit-requester-nik-tag {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
}

.permit-requester-role {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.permit-requester-dept-pod {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.permit-requester-dept-icon {
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-requester-dept-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    overflow: hidden;
}

.permit-requester-dept-label {
    font-size: 0.625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    line-height: 1.2;
}

.permit-requester-dept-val {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* =========================================================================
   VEHICLE SPECS GRID IN MODAL
   ========================================================================= */
.permit-specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.875rem 1rem;
}

.permit-spec-tile {
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

.permit-spec-tile__label {
    font-size: 0.6875rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.permit-spec-tile__val {
    font-size: 0.8125rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.permit-color-circle {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.2);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
}

/* =========================================================================
   REASON / NOTES CALLOUT
   ========================================================================= */
.permit-reason-callout {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(241, 245, 249, 0.85) 100%);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-left: 3.5px solid var(--hcis-brand-500, var(--hcis-indigo-500));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 1rem 1.15rem;
}

.permit-reason-callout__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.4rem;
}

.permit-reason-callout__icon {
    color: var(--hcis-brand-500, var(--hcis-indigo-500));
    display: inline-flex;
}

.permit-reason-callout__title {
    font-size: 0.75rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.permit-reason-callout__tag {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
}

.permit-reason-callout__text {
    font-size: 0.875rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.55;
    font-style: italic;
    word-break: break-word;
}

/* =========================================================================
   DOCUMENT ATTACHMENTS LIST (COMPACT ROW DESIGN)
   ========================================================================= */
.permit-docs-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permit-doc-item {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.55rem 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.permit-doc-item:hover {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    background: var(--hcis-slate-50);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.12);
}

.permit-doc-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-doc-icon--brand {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-200);
}

.permit-doc-icon--teal {
    background: #f0fdfa;
    color: #0d9488;
    border: 1px solid #99f6e4;
}

.permit-doc-icon--purple {
    background: #faf5ff;
    color: var(--hcis-violet-600);
    border: 1px solid #e9d5ff;
}

.permit-doc-icon--slate {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

.permit-doc-info {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.permit-doc-main-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.permit-doc-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.25;
}

.permit-doc-ext-tag {
    font-size: 0.5625rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
    flex-shrink: 0;
    line-height: 1.2;
}

.permit-doc-sub-row {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    display: flex;
    align-items: center;
    gap: 0.25rem;
    line-height: 1.2;
}

.permit-doc-action {
    flex-shrink: 0;
}

.permit-doc-view-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    height: 1.85rem;
    padding: 0 0.65rem;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-family: inherit;
    font-size: 0.6875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.permit-doc-view-pill:hover {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-color: var(--hcis-indigo-200);
}

/* =========================================================================
   TYPOGRAPHY & TABULAR NUMBERS UTILITY
   ========================================================================= */
.font-tabular {
    font-variant-numeric: tabular-nums;
}

.text-slate-800 {
    color: var(--hcis-slate-800, var(--hcis-slate-800)) !important;
}

.text-slate-700 {
    color: var(--hcis-slate-700, var(--hcis-slate-700)) !important;
}

.text-slate-600 {
    color: var(--hcis-slate-600, var(--hcis-slate-600)) !important;
}

.text-slate-500 {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
}

.text-xs {
    font-size: 0.75rem;
}

.gap-1\.5 {
    gap: 0.375rem !important;
}

/* =========================================================================
   APPROVAL TIMELINE IN DUAL-PANE SIDEBAR
   ========================================================================= */
.permit-approval-timeline-wrapper :deep(.hcis-timeline-card__head) {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-card__actor) {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    width: 100%;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-actor-info) {
    min-width: 0;
    flex: 1;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-time-badge) {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin-left: 2.75rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    white-space: nowrap;
}

/* =========================================================================
   RESPONSIVE MEDIA QUERIES (TABLET & MOBILE)
   ========================================================================= */
@media (max-width: 1024px) {
    .permit-cards-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 0.85rem;
    }
}

@media (max-width: 640px) {
    .permit-status-workspace :deep(.hcis-container__header) {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        padding: 0.75rem 0.85rem !important;
    }
    .permit-status-workspace :deep(.hcis-container__header-main) {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        gap: 0.5rem !important;
    }
    .permit-status-workspace :deep(.hcis-container__header-icon) {
        width: 2rem !important;
        height: 2rem !important;
        font-size: 0.95rem !important;
        border-radius: 6px !important;
    }
    .permit-status-workspace :deep(.hcis-container__title) {
        font-size: 0.875rem !important;
        font-weight: 800 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .permit-status-workspace :deep(.hcis-container__header-actions) {
        width: auto !important;
        flex-shrink: 0 !important;
        margin-top: 0 !important;
    }
    .permit-status-workspace :deep(.hcis-container__subtitle) {
        display: none !important;
    }
    .permit-reload-btn :deep(.hcis-btn__text) {
        display: none !important;
    }
    .permit-reload-btn {
        width: 2rem !important;
        height: 2rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 6px !important;
    }
    .permit-reload-btn :deep(.hcis-btn__icon) {
        margin: 0 !important;
    }
    .permit-status-workspace :deep(.hcis-filter-bar__main) {
        flex-wrap: nowrap !important;
        gap: 0.45rem !important;
    }
    .permit-status-workspace :deep(.hcis-filter-bar__search) {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }
    .permit-status-workspace :deep(.hcis-filter-bar__actions) {
        flex-shrink: 0 !important;
        flex-wrap: nowrap !important;
        gap: 0.35rem !important;
    }
    .permit-status-workspace :deep(.hcis-filter-reset-btn) {
        width: 2.25rem !important;
        height: 2.25rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        flex-shrink: 0 !important;
    }
    .permit-status-workspace :deep(.hcis-filter-reset-btn span) {
        display: none !important;
    }
    .permit-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .permit-card-header-bar {
        gap: 0.35rem;
        margin-bottom: 0.5rem !important;
    }
    .permit-card-spec-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem 0.6rem;
        padding: 0.5rem 0.6rem;
        border-radius: 8px;
    }
    .permit-spec-item__label {
        font-size: 0.5625rem;
        font-weight: 750;
    }
    .permit-spec-item__val {
        font-size: 0.71875rem;
        font-weight: 700;
    }
    .permit-card-validity-box {
        padding: 0.4rem 0.55rem;
        border-radius: 6px;
    }
    .permit-card-actions-bar {
        gap: 0.35rem;
    }
    .permit-card-actions-bar :deep(.hcis-btn) {
        height: 2.15rem !important;
        font-size: 0.75rem !important;
        font-weight: 750 !important;
    }
    .permit-plate-badge {
        padding: 0.2rem 0.45rem;
        font-size: 0.75rem;
        border-radius: 5px;
    }
    .permit-plate-badge--lg {
        padding: 0.25rem 0.55rem;
        font-size: 0.8125rem;
    }
    .permit-category-pill {
        font-size: 0.6875rem;
        padding: 0.15rem 0.45rem;
    }
    .permit-cell-actions :deep(.hcis-btn) {
        height: 1.85rem !important;
        padding: 0 0.45rem !important;
        font-size: 0.6875rem !important;
    }
    /* Detail Modal Mobile Optimizations */
    .permit-hero-card {
        padding: 0.75rem 0.85rem !important;
        border-radius: 10px !important;
        margin-bottom: 0.65rem !important;
    }
    .permit-hero-header {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        margin-bottom: 0.65rem !important;
    }
    .permit-hero-brand-group {
        gap: 0.55rem !important;
    }
    .permit-hero-icon-box {
        width: 2.25rem !important;
        height: 2.25rem !important;
        border-radius: 8px !important;
        font-size: 0.95rem !important;
    }
    .permit-hero-category-pill {
        font-size: 0.59375rem !important;
        padding: 0.12rem 0.45rem !important;
    }
    .permit-hero-vehicle-type {
        font-size: 0.6875rem !important;
    }
    .permit-hero-plate-card {
        padding: 0.5rem 0.65rem !important;
        border-radius: 8px !important;
    }
    .permit-hero-plate-number {
        font-size: 1.15rem !important;
        letter-spacing: 0.05em !important;
    }
    .permit-hero-subpod-grid {
        gap: 0.4rem !important;
    }
    .permit-hero-subpod {
        padding: 0.4rem 0.55rem !important;
        border-radius: 6px !important;
    }
    .permit-hero-subpod-val {
        font-size: 0.75rem !important;
    }
    .permit-hero-meta-bar {
        padding-top: 0.45rem !important;
        margin-top: 0.45rem !important;
        font-size: 0.65625rem !important;
        gap: 0.4rem !important;
    }
    .permit-requester-card {
        gap: 0.5rem !important;
    }
    .permit-requester-header {
        gap: 0.5rem !important;
    }
    .permit-requester-avatar {
        width: 2.15rem !important;
        height: 2.15rem !important;
        font-size: 0.78125rem !important;
        border-radius: 8px !important;
    }
    .permit-requester-name {
        font-size: 0.8125rem !important;
    }
    .permit-requester-nik-tag {
        font-size: 0.625rem !important;
        padding: 0.08rem 0.35rem !important;
    }
    .permit-requester-dept-pod {
        padding: 0.4rem 0.55rem !important;
        border-radius: 6px !important;
        gap: 0.45rem !important;
    }
    .permit-requester-dept-icon {
        width: 1.75rem !important;
        height: 1.75rem !important;
        border-radius: 6px !important;
    }
    .permit-requester-dept-val {
        font-size: 0.75rem !important;
    }
    .permit-specs-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 0.45rem !important;
    }
    .permit-spec-tile {
        padding: 0.45rem 0.55rem !important;
        border-radius: 6px !important;
    }
    .permit-spec-tile__label {
        font-size: 0.5625rem !important;
    }
    .permit-spec-tile__val {
        font-size: 0.75rem !important;
    }
    .permit-docs-list {
        gap: 0.4rem !important;
    }
    .permit-doc-item {
        padding: 0.45rem 0.6rem !important;
        border-radius: 8px !important;
        gap: 0.5rem !important;
    }
    .permit-doc-icon-box {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 6px !important;
        font-size: 0.85rem !important;
    }
    .permit-doc-title {
        font-size: 0.75rem !important;
    }
    .permit-doc-ext-tag {
        font-size: 0.53125rem !important;
        padding: 0.05rem 0.25rem !important;
    }
    .permit-doc-sub-row {
        font-size: 0.625rem !important;
    }
    .permit-doc-view-pill {
        height: 1.75rem !important;
        padding: 0 0.5rem !important;
        font-size: 0.625rem !important;
    }
    .permit-reason-callout {
        padding: 0.55rem 0.7rem !important;
        border-radius: 8px !important;
    }
    .permit-reason-callout__title {
        font-size: 0.6875rem !important;
    }
    .permit-reason-callout__text {
        font-size: 0.75rem !important;
    }
    .permit-approval-timeline-wrapper :deep(.hcis-timeline-time-badge) {
        margin-left: 0;
        margin-top: 0.25rem;
    }
}

@media (max-width: 380px) {
    .permit-status-workspace :deep(.hcis-container__header) {
        padding: 0.55rem 0.65rem !important;
    }
    .permit-status-workspace :deep(.hcis-container__title) {
        font-size: 0.8125rem !important;
    }
    .permit-status-workspace :deep(.hcis-container__header-icon) {
        width: 1.75rem !important;
        height: 1.75rem !important;
        border-radius: 5px !important;
    }
    .permit-status-workspace :deep(.hcis-filter-bar__main) {
        gap: 0.35rem !important;
    }
    .permit-status-workspace :deep(.hcis-filter-reset-btn) {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 6px !important;
    }
    .permit-card-item {
        padding: 0.65rem 0.75rem !important;
    }
    .permit-card-header {
        gap: 0.35rem;
    }
    .permit-card-spec-grid {
        grid-template-columns: 1fr !important;
        gap: 0.35rem !important;
        padding: 0.45rem 0.55rem !important;
    }
    .permit-plate-badge {
        padding: 0.15rem 0.35rem !important;
        font-size: 0.71875rem !important;
    }
    .permit-plate-badge--lg {
        padding: 0.2rem 0.45rem !important;
        font-size: 0.75rem !important;
    }
    .permit-category-pill {
        font-size: 0.625rem !important;
        padding: 0.12rem 0.35rem !important;
    }
    .permit-hero-badge {
        width: 2rem;
        height: 2rem;
    }
    .permit-hero-title {
        font-size: 0.875rem;
    }
    .permit-hero-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.45rem !important;
    }
    .permit-hero-plate-number {
        font-size: 1rem !important;
    }
    .permit-specs-grid {
        grid-template-columns: 1fr !important;
        gap: 0.35rem !important;
    }
    .permit-card-actions-bar {
        flex-direction: column;
        width: 100%;
    }
    .permit-card-actions-bar :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}

/* ══ PERMIT INSPECTION SCHEDULE CARD ══ */
.permit-inspeksi-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
}

.permit-inspeksi__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.permit-inspeksi__reschedule-quota {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
}

.permit-inspeksi__reschedule-quota strong {
    color: var(--hcis-brand-700, #4338ca);
    font-weight: 800;
}

.permit-inspeksi__ticket {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.95rem 1.05rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.75) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1.5px solid var(--hcis-brand-100, #e0e7ff);
    box-shadow: 0 2px 8px -2px rgba(79, 70, 229, 0.06);
}

.permit-inspeksi__ticket-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.permit-inspeksi__ticket-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 8px;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}

.permit-inspeksi__ticket-icon.is-calendar {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.permit-inspeksi__ticket-icon.is-location {
    background: #ffffff;
    color: var(--hcis-emerald-600, #059669);
    border: 1px solid var(--hcis-emerald-200, #a7f3d0);
}

.permit-inspeksi__ticket-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1 1 auto;
}

.permit-inspeksi__ticket-label {
    font-size: 0.65625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.2;
}

.permit-inspeksi__datetime-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.permit-inspeksi__date {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-inspeksi__time-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    font-size: 0.71875rem;
    font-weight: 750;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.25);
}

.permit-inspeksi__ticket-divider {
    height: 1px;
    background: var(--hcis-brand-100, #e0e7ff);
    margin: 0.1rem 0;
}

.permit-inspeksi__pos-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.permit-inspeksi__pos-name {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
}

.permit-inspeksi__pos-tag {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, #64748b);
    font-weight: 600;
}

.permit-inspeksi__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.75rem;
    line-height: 1.45;
}

.permit-inspeksi__notice.is-info {
    background: #f8fafc;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    color: var(--hcis-slate-700, #334155);
}

.permit-inspeksi__notice.is-info .hcis-icon {
    color: var(--hcis-brand-500, #6366f1);
    margin-top: 0.1rem;
}

.permit-inspeksi__notice.is-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.permit-inspeksi__notice.is-warning .hcis-icon {
    color: #d97706;
    margin-top: 0.1rem;
}

.permit-inspeksi__notice.is-danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.permit-inspeksi__notice.is-danger .hcis-icon {
    color: #dc2626;
    margin-top: 0.1rem;
}

.permit-inspeksi__notice-text {
    flex: 1 1 auto;
    min-width: 0;
}

.permit-inspeksi__notice-text strong {
    color: inherit;
    font-weight: 750;
}

.permit-inspeksi__actions-wrap {
    width: 100%;
}

.permit-inspeksi__reschedule-btn {
    font-weight: 750 !important;
    height: 2.35rem !important;
}
</style>
