<template>
    <div class="tamu-history-workspace fade-in">
        <!-- Error Alert if data fetch fails -->
        <HcisAlert
            v-if="fetchError"
            variant="danger"
            appearance="glass"
            title="Gagal Memuat Riwayat Tamu"
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
                    label="Coba Lagi"
                    :loading="loading"
                    @click="fetchData"
                />
            </template>
        </HcisAlert>


        <!-- Main Data Table & View Container (Default Design System Brand) -->
        <HcisDataTable
            :title="title"
            icon="journal-text"
            sentiment="brand"
            variant="glass"
            table-variant="flat"
            :columns="columns"
            :data="tableData"
            :loading="loading"
            :search="searchQuery"
            search-placeholder="Cari nama tamu, instansi, PIC tujuan, No. Transaksi, plat nomor..."
            :command-key="false"
            :quick-chips="quickChips"
            :active-chip-value="activeChip"
            :filters="filterDefinitions"
            :filter-values="filterValues"
            :show-view-toggle="true"
            :view-mode="viewMode"
            :show-pagination="totalElements > 0"
            :total="totalElements"
            :page="currentPage"
            :page-size="itemsPerPage"
            :hoverable="true"
            @update:search="onSearchUpdate"
            @chip-click="onChipClick"
            @filter-change="onFilterChange"
            @reset="resetFilter"
            @update:view-mode="onViewModeChange"
            @page-change="changePage"
        >
            <!-- Header Actions Slot: Date Presets, Stepper, & Muat Ulang -->
            <template #headerActions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Time Horizon Presets (When in scheduleMode) -->
                    <div v-if="scheduleMode" class="tamu-horizon-group" role="group" aria-label="Filter periode kedatangan">
                        <button
                            type="button"
                            class="tamu-horizon-btn"
                            :class="{ 'is-active': activeTimePreset === 'TODAY' }"
                            @click="setTimePreset('TODAY')"
                        >
                            Hari Ini
                        </button>
                        <button
                            type="button"
                            class="tamu-horizon-btn"
                            :class="{ 'is-active': activeTimePreset === 'TOMORROW' }"
                            @click="setTimePreset('TOMORROW')"
                        >
                            Besok
                        </button>
                        <button
                            type="button"
                            class="tamu-horizon-btn"
                            :class="{ 'is-active': activeTimePreset === 'SEVEN_DAYS' }"
                            @click="setTimePreset('SEVEN_DAYS')"
                        >
                            7 Hari
                        </button>
                        <button
                            type="button"
                            class="tamu-horizon-btn"
                            :class="{ 'is-active': activeTimePreset === 'FOURTEEN_DAYS' }"
                            @click="setTimePreset('FOURTEEN_DAYS')"
                        >
                            14 Hari
                        </button>
                        <button
                            type="button"
                            class="tamu-horizon-btn"
                            :class="{ 'is-active': activeTimePreset === 'THIRTY_DAYS' }"
                            @click="setTimePreset('THIRTY_DAYS')"
                        >
                            30 Hari
                        </button>
                        <button
                            type="button"
                            class="tamu-horizon-btn"
                            :class="{ 'is-active': activeTimePreset === 'ALL' }"
                            @click="setTimePreset('ALL')"
                        >
                            Semua
                        </button>
                    </div>

                    <!-- Stepper Periode Sebelumnya / Berikutnya -->
                    <div v-if="scheduleMode && activeTimePreset !== 'ALL'" class="tamu-stepper">
                        <button
                            type="button"
                            class="tamu-stepper-btn"
                            title="Periode Sebelumnya"
                            @click="geserPeriode(-1)"
                        >
                            <HcisIcon name="chevron-left" size="xs" />
                        </button>
                        <span class="tamu-stepper-label font-tabular">{{ labelRentangSingkat }}</span>
                        <button
                            type="button"
                            class="tamu-stepper-btn"
                            title="Periode Berikutnya"
                            @click="geserPeriode(1)"
                        >
                            <HcisIcon name="chevron-right" size="xs" />
                        </button>
                    </div>

                    <HcisButton
                        size="sm"
                        variant="secondary"
                        leading-icon="arrow-clockwise"
                        label="Muat Ulang"
                        class="tamu-reload-btn"
                        :loading="loading"
                        title="Perbarui data buku tamu"
                        @click="fetchData"
                    />
                </div>
            </template>

            <!-- Table View: Custom Cells -->
            <!-- Column 1: Tamu & Instansi -->
            <template #cell-Tamu_Instansi="{ row }">
                <div class="tamu-cell-guest">
                    <HcisAvatar
                        :name="row.Nama_Tamu || row.Nama_Lengkap_Tamu"
                        size="md"
                        variant="brand"
                        shape="circle"
                        class="flex-shrink-0"
                    />
                    <div class="tamu-cell-text-block min-w-0">
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="text-slate-900 text-sm fw-bold text-truncate">
                                {{ row.Nama_Tamu || row.Nama_Lengkap_Tamu }}
                            </span>
                            <span v-if="row.Jumlah_Tamu > 1" class="tamu-mini-pill flex-shrink-0" title="Jumlah rombongan">
                                +{{ row.Jumlah_Tamu - 1 }} org
                            </span>
                            <span v-if="row.Kategori_Label || row.Kategori" class="tamu-kategori-tag flex-shrink-0" :title="`Kategori: ${row.Kategori_Label || row.Kategori}`">
                                {{ row.Kategori_Label || row.Kategori }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-1.5 text-xs text-slate-500 text-truncate mt-0.5">
                            <HcisIcon name="buildings" size="2xs" class="text-slate-400 flex-shrink-0" />
                            <span class="text-slate-700 text-truncate">{{ row.Instansi_Asal || row.Instansi_Atau_Asal || 'Umum' }}</span>
                            <template v-if="row.No_HP || row.Nomor_Handphone">
                                <span class="text-slate-300">·</span>
                                <span class="text-slate-500 font-tabular font-mono text-2xs flex-shrink-0">
                                    {{ row.No_HP || row.Nomor_Handphone }}
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Column 2: PIC & Keperluan -->
            <template #cell-PIC_Keperluan="{ row }">
                <div class="tamu-cell-text-block min-w-0">
                    <div class="d-flex align-items-center gap-1.5 text-xs text-slate-900 fw-bold text-truncate">
                        <HcisIcon name="person-badge-fill" size="2xs" class="text-primary flex-shrink-0" />
                        <span class="text-truncate">{{ row.PIC_Tujuan || row.Nama_PIC || '-' }}</span>
                    </div>
                    <div class="mt-1">
                        <span v-if="row.Deksripsi_Keperluan" class="tamu-purpose-chip text-truncate" :title="row.Deksripsi_Keperluan">
                            {{ row.Deksripsi_Keperluan }}
                        </span>
                        <span v-else class="text-slate-400 text-xs fst-italic">-</span>
                    </div>
                </div>
            </template>

            <!-- Column 3: Jadwal & Kendaraan -->
            <template #cell-Jadwal_Kendaraan="{ row }">
                <div class="tamu-cell-schedule">
                    <div class="d-flex align-items-center gap-1.5 text-xs text-slate-900 fw-bold font-tabular text-nowrap">
                        <HcisIcon name="calendar3" size="2xs" class="text-slate-400 flex-shrink-0" />
                        <span>{{ formatJadwalDate(row) }}</span>
                        <span
                            v-if="getDateBadge(row.Tanggal_Kunjungan || row.Waktu_Mulai_Kunjungan)"
                            class="tamu-date-pill flex-shrink-0"
                            :class="`is-${getDateBadge(row.Tanggal_Kunjungan || row.Waktu_Mulai_Kunjungan).variant}`"
                        >
                            {{ getDateBadge(row.Tanggal_Kunjungan || row.Waktu_Mulai_Kunjungan).label }}
                        </span>
                    </div>
                    <div v-if="formatJadwalTime(row)" class="d-flex align-items-center gap-1 text-2xs text-slate-500 font-tabular mt-0.5 text-nowrap">
                        <HcisIcon name="clock" size="2xs" class="text-slate-400 flex-shrink-0" />
                        <span>{{ formatJadwalTime(row) }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 mt-1">
                        <span v-if="row.No_Plat_Kendaraan" class="tamu-plate-badge" :title="row.No_Plat_Kendaraan">
                            {{ row.No_Plat_Kendaraan }}
                        </span>
                        <span v-else class="text-2xs text-slate-400 d-inline-flex align-items-center gap-1">
                            <HcisIcon name="person-walking" size="2xs" />
                            <span>Tanpa Kendaraan</span>
                        </span>
                    </div>
                </div>
            </template>

            <!-- Column 4: Kehadiran (Masuk / Keluar) -->
            <template #cell-Log_Kehadiran="{ row }">
                <div class="tamu-cell-presence">
                    <!-- If currently CHECKED_IN -->
                    <template v-if="row.Status_Kunjungan === 'CHECKED_IN'">
                        <div class="d-flex align-items-center gap-1.5 text-primary text-xs fw-bold font-tabular text-nowrap">
                            <HcisIcon name="box-arrow-in-right" size="2xs" class="flex-shrink-0" />
                            <span>Masuk: {{ formatTime(row.Waktu_Aktual_Masuk) }}</span>
                        </div>
                        <div class="d-inline-flex align-items-center gap-1 text-2xs text-emerald-600 font-semibold mt-0.5 text-nowrap">
                            <span class="tamu-pulse-dot flex-shrink-0" />
                            <span>{{ getElapsedPresenceTime(row.Waktu_Aktual_Masuk) }}</span>
                        </div>
                    </template>

                    <!-- If CHECKED_OUT -->
                    <template v-else-if="row.Status_Kunjungan === 'CHECKED_OUT'">
                        <div class="d-flex align-items-center gap-1 text-slate-800 text-xs font-tabular fw-semibold text-nowrap">
                            <span>{{ formatTimeShort(row.Waktu_Aktual_Masuk) || '-' }}</span>
                            <span class="text-slate-400">→</span>
                            <span>{{ formatTimeShort(row.Waktu_Aktual_Keluar) || '-' }}</span>
                            <span class="text-2xs text-slate-400 font-normal">WIB</span>
                        </div>
                        <div v-if="getVisitDuration(row.Waktu_Aktual_Masuk, row.Waktu_Aktual_Keluar)" class="text-2xs text-slate-500 font-tabular mt-0.5 text-nowrap">
                            Durasi: <strong>{{ getVisitDuration(row.Waktu_Aktual_Masuk, row.Waktu_Aktual_Keluar) }}</strong>
                        </div>
                    </template>

                    <!-- Other states -->
                    <template v-else>
                        <span class="text-slate-400 text-xs fst-italic">Belum hadir</span>
                    </template>
                </div>
            </template>

            <!-- Column 5: Status Kunjungan -->
            <template #cell-Status_Kunjungan="{ row }">
                <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                    <HcisBadge
                        :label="getStatusBadgeLabel(row.Status_Kunjungan, row)"
                        :variant="getStatusBadgeVariant(row.Status_Kunjungan, row)"
                        :icon="row.Status_Kunjungan === 'CHECKED_IN' ? null : getStatusBadgeIcon(row.Status_Kunjungan, row)"
                        :pulse="row.Status_Kunjungan === 'CHECKED_IN' || isItemPendingApproval(row)"
                        size="sm"
                        appearance="glass"
                    />
                    <!-- Status permit coklat sengaja ditampilkan terpisah dari
                         status kunjungan: keduanya bisa berbeda. Kunjungan yang
                         masih SCHEDULED boleh punya permit yang sudah habis
                         jendelanya, dan tamunya akan ditahan di gerbang. -->
                    <span
                        v-if="row.Status_Permit_Coklat"
                        class="tamu-permit-badge"
                        :data-status="row.Status_Permit_Coklat"
                    >{{ labelPermitCoklat(row.Status_Permit_Coklat) }}</span>
                </div>
            </template>

            <!-- Column 6: Aksi -->
            <template #cell-actions="{ row }">
                <div class="tamu-cell-actions d-flex align-items-center justify-content-center gap-1">
                    <HcisButton
                        size="xs"
                        variant="secondary"
                        leading-icon="eye"
                        label="Detail"
                        title="Lihat detail registrasi tamu"
                        @click="viewDetail(row)"
                    />


                    <!-- Cancel button if pending approval and eligible -->
                    <HcisButton
                        v-if="canCancelTamu(row)"
                        size="xs"
                        variant="ghost"
                        leading-icon="x-circle"
                        label="Batal"
                        class="text-danger"
                        title="Batalkan pengajuan tamu ini"
                        @click="openCancelModal(row)"
                    />
                </div>
            </template>

            <!-- Empty State Slot inside Table -->
            <template #empty>
                <div class="tamu-empty-wrapper py-5">
                    <HcisEmptyState
                        v-if="tableData.length === 0 && !searchQuery && activeChip === 'ALL' && !dateRange?.length"
                        :title="readOnly ? 'Belum Ada Jadwal Tamu' : 'Belum Ada Riwayat Kunjungan Tamu'"
                        :description="readOnly ? 'Daftar jadwal kedatangan tamu untuk departemen Anda akan tercatat di sini.' : 'Daftar kunjungan tamu yang Anda daftarkan akan tercatat di sini beserta status persetujuan dan log kehadirannya.'"
                    >
                        <template v-if="!readOnly" #actions>
                            <HcisButton
                                variant="primary"
                                size="md"
                                leading-icon="person-plus-fill"
                                label="Daftarkan Tamu Baru"
                                @click="$emit('navigate-to-form')"
                            />
                        </template>
                    </HcisEmptyState>
                    <HcisEmptyState
                        v-else
                        title="Tidak Ada Data yang Cocok"
                        description="Tidak ditemukan riwayat tamu yang sesuai dengan filter atau kata kunci pencarian Anda."
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
                <div v-if="loading" class="tamu-cards-grid">
                    <HcisCard
                        v-for="i in 6"
                        :key="i"
                        variant="glass"
                        size="md"
                        divider
                        class="tamu-grid-card"
                    >
                        <template #badge>
                            <HcisSkeleton width="80px" height="1.25rem" rounded="full" />
                        </template>
                        <div class="d-flex align-items-center gap-2.5 mb-3">
                            <HcisSkeleton width="2.75rem" height="2.75rem" rounded="full" />
                            <div class="flex-grow-1">
                                <HcisSkeleton width="120px" height="1rem" rounded="sm" class="mb-1" />
                                <HcisSkeleton width="80px" height="0.75rem" rounded="sm" />
                            </div>
                        </div>
                        <div class="tamu-card-spec-grid mb-3">
                            <div v-for="s in 4" :key="s" class="tamu-spec-item">
                                <HcisSkeleton width="60px" height="0.65rem" rounded="sm" class="mb-1" />
                                <HcisSkeleton width="90px" height="0.85rem" rounded="sm" />
                            </div>
                        </div>
                        <template #footer>
                            <div class="tamu-card-actions-bar">
                                <HcisSkeleton width="100%" height="2rem" rounded="md" />
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <!-- If in scheduleMode, group cards by Day (Timeline Grouping) -->
                <div v-else-if="scheduleMode && groupedByDay.length > 0" class="tamu-schedule-timeline">
                    <section
                        v-for="dayGroup in groupedByDay"
                        :key="dayGroup.date"
                        class="tamu-day-section mb-4"
                    >
                        <!-- Day Header Divider -->
                        <div class="tamu-day-header">
                            <div class="tamu-day-header-left">
                                <div class="tamu-day-calendar-icon">
                                    <HcisIcon name="calendar2-date-fill" size="xs" />
                                </div>
                                <h3 class="tamu-day-title">
                                    {{ dayGroup.formattedDate }}
                                </h3>
                                <span v-if="dayGroup.isToday" class="tamu-day-today-tag">
                                    <span class="tamu-pulse-dot" />
                                    <span>Hari Ini</span>
                                </span>
                                <span v-else-if="dayGroup.isTomorrow" class="tamu-day-tomorrow-tag">
                                    Besok
                                </span>
                            </div>
                            <div class="tamu-day-header-right">
                                <span class="tamu-day-summary-badge font-tabular">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <span>{{ dayGroup.totalTamu }} Tamu</span>
                                    <span class="text-muted">({{ dayGroup.items.length }} Kunjungan)</span>
                                </span>
                            </div>
                        </div>

                        <!-- Cards Grid for this day -->
                        <div class="tamu-cards-grid">
                            <HcisCard
                                v-for="item in dayGroup.items"
                                :key="item.Id || item.Id_Transaksi_Tamu"
                                :sentiment="getCardSentiment(item)"
                                variant="glass"
                                size="md"
                                divider
                                hoverable
                                interactive
                                class="tamu-grid-card"
                                :class="{ 'is-active-card': isGuestActive(item) }"
                                @click="viewDetail(item)"
                            >
                                <template #badge>
                                    <HcisBadge
                                        :label="getStatusBadgeLabel(item.Status_Kunjungan, item)"
                                        :variant="getStatusBadgeVariant(item.Status_Kunjungan, item)"
                                        :icon="getStatusBadgeIcon(item.Status_Kunjungan, item)"
                                        :pulse="item.Status_Kunjungan === 'CHECKED_IN' || isItemPendingApproval(item)"
                                        size="xs"
                                        appearance="glass"
                                    />
                                    <span
                                        v-if="item.Status_Permit_Coklat"
                                        class="tamu-permit-badge"
                                        :data-status="item.Status_Permit_Coklat"
                                    >{{ labelPermitCoklat(item.Status_Permit_Coklat) }}</span>
                                </template>

                                <!-- Card Header: Avatar + Guest Name + Instansi Pill -->
                                <div class="tamu-card-header-bar mb-3">
                                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                                        <HcisAvatar
                                            :name="item.Nama_Tamu || item.Nama_Lengkap_Tamu"
                                            size="md"
                                            variant="brand"
                                            shape="circle"
                                            class="flex-shrink-0"
                                        />
                                        <div class="overflow-hidden min-w-0">
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <strong class="text-slate-900 text-sm fw-bold text-truncate">
                                                    {{ item.Nama_Tamu || item.Nama_Lengkap_Tamu }}
                                                </strong>
                                                <span v-if="item.Jumlah_Tamu > 1" class="tamu-mini-pill" title="Jumlah rombongan">
                                                    +{{ item.Jumlah_Tamu - 1 }} org
                                                </span>
                                            </div>
                                            <div class="text-xs text-muted text-truncate mt-0.5">
                                                <HcisIcon name="buildings" size="2xs" class="text-slate-400 me-1" />
                                                <span>{{ item.Instansi_Asal || item.Instansi_Atau_Asal || 'Umum' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Specs 2x2 Grid -->
                                <div class="tamu-card-spec-grid mb-3">
                                    <div class="tamu-spec-item">
                                        <span class="tamu-spec-item__label">PIC Tujuan</span>
                                        <strong class="tamu-spec-item__val text-truncate text-slate-800">
                                            {{ item.PIC_Tujuan || '-' }}
                                        </strong>
                                    </div>
                                    <div class="tamu-spec-item">
                                        <span class="tamu-spec-item__label">Waktu Kunjungan</span>
                                        <span class="tamu-spec-item__val font-tabular text-slate-800 fw-semibold">
                                            {{ labelJadwalRingkas(item) }}
                                        </span>
                                    </div>
                                    <div class="tamu-spec-item">
                                        <span class="tamu-spec-item__label">Keperluan</span>
                                        <span class="tamu-spec-item__val text-truncate text-slate-700" :title="item.Deksripsi_Keperluan">
                                            {{ item.Deksripsi_Keperluan || '-' }}
                                        </span>
                                    </div>
                                    <div class="tamu-spec-item">
                                        <span class="tamu-spec-item__label">Transportasi</span>
                                        <span v-if="item.No_Plat_Kendaraan" class="tamu-plate-badge text-2xs">
                                            {{ item.No_Plat_Kendaraan }}
                                        </span>
                                        <span v-else class="tamu-spec-item__val text-slate-500 d-inline-flex align-items-center gap-1">
                                            <HcisIcon name="person-walking" size="2xs" />
                                            <span>Tanpa Kendaraan</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Active Presence Bar (if CHECKED_IN) -->
                                <div v-if="item.Status_Kunjungan === 'CHECKED_IN'" class="tamu-card-presence-box p-2 rounded-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-2xs text-muted fw-bold text-uppercase">Waktu Hadir</span>
                                        <span class="text-2xs text-primary font-medium d-inline-flex align-items-center gap-1">
                                            <span class="tamu-pulse-dot" />
                                            <span>{{ getElapsedPresenceTime(item.Waktu_Aktual_Masuk) }}</span>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-800 fw-bold font-tabular">
                                        Masuk: {{ formatTime(item.Waktu_Aktual_Masuk) }}
                                    </div>
                                </div>

                                <!-- Card Footer Action Buttons -->
                                <template #footer>
                                    <div class="tamu-card-actions-bar" @click.stop>
                                        <HcisButton
                                            size="sm"
                                            variant="secondary"
                                            leading-icon="eye"
                                            label="Lihat Detail"
                                            class="tamu-card-btn flex-grow-1"
                                            @click="viewDetail(item)"
                                        />
                                    </div>
                                </template>
                            </HcisCard>
                        </div>
                    </section>
                </div>

                <!-- Standard Flat Grid (Non-Schedule Mode) -->
                <div v-else-if="tableData.length > 0" class="tamu-cards-grid">
                    <HcisCard
                        v-for="item in tableData"
                        :key="item.Id || item.Id_Transaksi_Tamu"
                        :sentiment="getCardSentiment(item)"
                        variant="glass"
                        size="md"
                        divider
                        hoverable
                        interactive
                        class="tamu-grid-card"
                        :class="{ 'is-active-card': isGuestActive(item) }"
                        @click="viewDetail(item)"
                    >
                        <template #badge>
                            <HcisBadge
                                :label="getStatusBadgeLabel(item.Status_Kunjungan, item)"
                                :variant="getStatusBadgeVariant(item.Status_Kunjungan, item)"
                                :icon="getStatusBadgeIcon(item.Status_Kunjungan, item)"
                                :pulse="item.Status_Kunjungan === 'CHECKED_IN' || isItemPendingApproval(item)"
                                size="xs"
                                appearance="glass"
                            />
                            <span
                                v-if="item.Status_Permit_Coklat"
                                class="tamu-permit-badge"
                                :data-status="item.Status_Permit_Coklat"
                            >{{ labelPermitCoklat(item.Status_Permit_Coklat) }}</span>
                        </template>

                        <!-- Card Header: Avatar + Guest Name + Instansi Pill -->
                        <div class="tamu-card-header-bar mb-3">
                            <div class="d-flex align-items-center gap-2.5 min-w-0">
                                <HcisAvatar
                                    :name="item.Nama_Tamu || item.Nama_Lengkap_Tamu"
                                    size="md"
                                    variant="brand"
                                    shape="circle"
                                    class="flex-shrink-0"
                                />
                                <div class="overflow-hidden min-w-0">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <strong class="text-slate-900 text-sm fw-bold text-truncate">
                                            {{ item.Nama_Tamu || item.Nama_Lengkap_Tamu }}
                                        </strong>
                                        <span v-if="item.Jumlah_Tamu > 1" class="tamu-mini-pill" title="Jumlah rombongan">
                                            +{{ item.Jumlah_Tamu - 1 }} org
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted text-truncate mt-0.5">
                                        <HcisIcon name="buildings" size="2xs" class="text-slate-400 me-1" />
                                        <span>{{ item.Instansi_Asal || item.Instansi_Atau_Asal || 'Umum' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Specs 2x2 Grid -->
                        <div class="tamu-card-spec-grid mb-3">
                            <div class="tamu-spec-item">
                                <span class="tamu-spec-item__label">PIC Tujuan</span>
                                <strong class="tamu-spec-item__val text-truncate text-slate-800">
                                    {{ item.PIC_Tujuan || '-' }}
                                </strong>
                            </div>
                            <div class="tamu-spec-item">
                                <span class="tamu-spec-item__label">Tgl Kunjungan</span>
                                <span class="tamu-spec-item__val font-tabular text-slate-800 fw-semibold">
                                    {{ formatDate(item.Tanggal_Kunjungan) }}
                                </span>
                            </div>
                            <div class="tamu-spec-item">
                                <span class="tamu-spec-item__label">Keperluan</span>
                                <span class="tamu-spec-item__val text-truncate text-slate-700" :title="item.Deksripsi_Keperluan">
                                    {{ item.Deksripsi_Keperluan || '-' }}
                                </span>
                            </div>
                            <div class="tamu-spec-item">
                                <span class="tamu-spec-item__label">Transportasi</span>
                                <span v-if="item.No_Plat_Kendaraan" class="tamu-plate-badge text-2xs">
                                    {{ item.No_Plat_Kendaraan }}
                                </span>
                                <span v-else class="tamu-spec-item__val text-slate-500 d-inline-flex align-items-center gap-1">
                                    <HcisIcon name="person-walking" size="2xs" />
                                    <span>Tanpa Kendaraan</span>
                                </span>
                            </div>
                        </div>

                        <!-- Active Presence Bar (if CHECKED_IN) -->
                        <div v-if="item.Status_Kunjungan === 'CHECKED_IN'" class="tamu-card-presence-box p-2 rounded-3 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-2xs text-muted fw-bold text-uppercase">Waktu Hadir</span>
                                <span class="text-2xs text-primary font-medium d-inline-flex align-items-center gap-1">
                                    <span class="tamu-pulse-dot" />
                                    <span>{{ getElapsedPresenceTime(item.Waktu_Aktual_Masuk) }}</span>
                                </span>
                            </div>
                            <div class="text-xs text-slate-800 fw-bold font-tabular">
                                Masuk: {{ formatTime(item.Waktu_Aktual_Masuk) }}
                            </div>
                        </div>

                        <!-- Card Footer Action Buttons -->
                        <template #footer>
                            <div class="tamu-card-actions-bar" @click.stop>
                                <HcisButton
                                    size="sm"
                                    variant="secondary"
                                    leading-icon="eye"
                                    label="Lihat Detail"
                                    class="tamu-card-btn flex-grow-1"
                                    @click="viewDetail(item)"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>
            </template>
        </HcisDataTable>

        <!-- ================================================================= -->
        <!-- DETAIL MODAL DUAL PANE (HCIS DUAL PANE DIALOG - DEFAULT BRAND)    -->
        <!-- ================================================================= -->
        <HcisDualPaneDialog
            v-model="showModal"
            title="Detail Registrasi & Kunjungan Tamu"
            kicker="Visitor · Guest Management"
            description="Rincian lengkap identitas tamu, tujuan kunjungan, alur persetujuan, dan log kehadiran."
            icon="person-vcard-fill"
            sentiment="brand"
            variant="glass"
            pattern="grid"
            size="xl"
            left-width="390px"
            left-tab-label="Identitas & Status"
            left-tab-icon="person-vcard-fill"
            right-tab-label="Rincian & Log"
            right-tab-icon="journal-text"
            sticky-footer
            :loading="detailLoading"
            loading-text="Mengambil rincian data tamu..."
            @close="closeModal"
        >
            <!-- Left Pane: Hero Guest Card, PIC Pod & Approval Timeline -->
            <template #left>
                <div v-if="tamuData">
                    <!-- Hero Tamu Identity Card (HCIS Brand Design System) -->
                    <div class="tamu-hero-card">
                        <!-- 1. Top Bar: Category Pill & Status Badge -->
                        <div class="tamu-hero-topbar">
                            <span class="tamu-hero-category-pill">
                                <span class="tamu-hero-category-dot" />
                                <span class="tamu-hero-category-text">
                                    {{ tamuData.Tipe_Tamu === 'B2B' ? 'Tamu Mitra Kerja (B2B)' : 'Tamu Regular' }}
                                </span>
                            </span>

                            <HcisBadge
                                :label="getStatusBadgeLabel(tamuData.Status_Kunjungan, tamuData)"
                                :variant="getStatusBadgeVariant(tamuData.Status_Kunjungan, tamuData)"
                                :icon="getStatusBadgeIcon(tamuData.Status_Kunjungan, tamuData)"
                                :pulse="tamuData.Status_Kunjungan === 'CHECKED_IN' || isItemPendingApproval(tamuData)"
                                size="sm"
                                appearance="subtle"
                                class="flex-shrink-0"
                            />
                        </div>

                        <!-- 2. Guest Identity Section: Avatar + Name + Organization / Party -->
                        <div class="tamu-hero-profile">
                            <div class="tamu-hero-avatar-box shadow-sm">
                                <HcisAvatar
                                    :name="tamuData.Nama_Tamu || tamuData.Nama_Lengkap_Tamu"
                                    size="lg"
                                    shape="circle"
                                    variant="brand"
                                />
                            </div>
                            <div class="tamu-hero-profile-info">
                                <h3 class="tamu-hero-name" :title="tamuData.Nama_Tamu || tamuData.Nama_Lengkap_Tamu">
                                    {{ tamuData.Nama_Tamu || tamuData.Nama_Lengkap_Tamu }}
                                </h3>
                                <div class="tamu-hero-profile-meta">
                                    <span class="tamu-hero-instansi">
                                        <HcisIcon name="buildings" size="2xs" class="text-slate-400 flex-shrink-0" />
                                        <span>{{ tamuData.Instansi_Asal || tamuData.Instansi_Atau_Asal || 'Kunjungan Umum / Pribadi' }}</span>
                                    </span>
                                    <span v-if="tamuData.Jumlah_Tamu > 1" class="tamu-hero-party-count">
                                        <HcisIcon name="people-fill" size="2xs" class="text-slate-400 flex-shrink-0" />
                                        <span>Total {{ tamuData.Jumlah_Tamu }} Orang</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Transaction Box & Presence Subpods -->
                        <div class="tamu-hero-details-container">
                            <!-- Primary Transaction Number Display Card -->
                            <div class="tamu-hero-trx-card">
                                <div class="tamu-hero-trx-info">
                                    <span class="tamu-hero-card-kicker">
                                        <HcisIcon name="receipt" size="2xs" class="text-slate-400" />
                                        <span>Nomor Registrasi Tamu</span>
                                    </span>
                                    <div class="tamu-hero-trx-number font-tabular font-mono">
                                        {{ tamuData.No_Transaksi || '-' }}
                                    </div>
                                </div>
                                <button
                                    v-if="tamuData.No_Transaksi"
                                    type="button"
                                    class="tamu-copy-btn"
                                    title="Salin nomor transaksi"
                                    @click="copyToClipboard(tamuData.No_Transaksi)"
                                >
                                    <HcisIcon name="copy" size="xs" />
                                </button>
                            </div>

                            <!-- Dual Subpod Row: Check-In & Check-Out Times -->
                            <div class="tamu-hero-subpod-grid">
                                <div class="tamu-hero-subpod">
                                    <div class="tamu-hero-card-kicker">
                                        <HcisIcon name="box-arrow-in-right" size="2xs" class="text-primary flex-shrink-0" />
                                        <span>Check-In</span>
                                    </div>
                                    <div class="tamu-hero-subpod-val font-tabular">
                                        <span v-if="tamuData.Waktu_Aktual_Masuk" class="text-slate-900 fw-bold">
                                            {{ formatDateTime(tamuData.Waktu_Aktual_Masuk) }}
                                        </span>
                                        <span v-else class="text-slate-400 fw-medium text-xs">
                                            Belum Masuk
                                        </span>
                                    </div>
                                </div>
                                <div class="tamu-hero-subpod">
                                    <div class="tamu-hero-card-kicker">
                                        <HcisIcon name="box-arrow-right" size="2xs" class="text-rose-500 flex-shrink-0" />
                                        <span>Check-Out</span>
                                    </div>
                                    <div class="tamu-hero-subpod-val font-tabular">
                                        <span v-if="tamuData.Waktu_Aktual_Keluar" class="text-slate-900 fw-bold">
                                            {{ formatDateTime(tamuData.Waktu_Aktual_Keluar) }}
                                        </span>
                                        <span v-else class="text-slate-400 fw-medium text-xs">
                                            Belum Keluar
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Bottom Meta Row: Submission Date & Active Duration -->
                        <div class="tamu-hero-meta-bar text-xs">
                            <div class="tamu-hero-date">
                                <HcisIcon name="calendar3" size="xs" class="text-slate-400" />
                                <span>Diajukan: <strong>{{ formatDateTime(tamuData.Created_At) }}</strong></span>
                            </div>
                            <div v-if="tamuData.Status_Kunjungan === 'CHECKED_IN'" class="tamu-hero-duration">
                                <span class="tamu-pulse-dot me-1" />
                                <span class="fw-bold text-primary">{{ getElapsedPresenceTime(tamuData.Waktu_Aktual_Masuk) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- PIC Tujuan Information Card (Matches Permit Requester Card) -->
                    <HcisCard
                        title="PIC & Penanggung Jawab"
                        icon="person-badge-fill"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="tamu-pic-card">
                            <div class="tamu-pic-header">
                                <div class="tamu-pic-avatar">
                                    {{ getInitials(tamuData.PIC_Tujuan) }}
                                </div>
                                <div class="tamu-pic-identity">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="tamu-pic-name text-truncate">
                                            {{ tamuData.PIC_Tujuan || '-' }}
                                        </span>
                                        <span v-if="tamuData.Kode_Karyawan_PIC" class="tamu-pic-nik-tag font-tabular">
                                            Kode: {{ tamuData.Kode_Karyawan_PIC }}
                                        </span>
                                    </div>
                                    <div class="tamu-pic-role text-xs">
                                        PIC / Karyawan yang Dikunjungi
                                    </div>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Approval Workflow Section (Matches Permit Approval Card) -->
                    <HcisCard
                        title="Alur Persetujuan"
                        description="Tahapan verifikasi otoritas dan catatan approver."
                        icon="diagram-3-fill"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                    >
                        <div v-if="isApprovalRequired(tamuData)" class="tamu-approval-timeline-wrapper">
                            <HcisTimeline
                                v-if="approvalTimelineItems.length"
                                :items="approvalTimelineItems"
                                direction="vertical"
                                variant="compact"
                                sentiment="brand"
                                :card="false"
                            />
                            <div v-else class="text-center p-3 text-muted text-xs border rounded-3 bg-slate-50">
                                <HcisIcon name="hourglass-split" size="sm" class="mb-1 text-warning d-block" />
                                <span>Alur persetujuan sedang disiapkan.</span>
                            </div>
                        </div>
                        <div v-else-if="tamuData.Tipe_Tamu === 'B2B' || tamuData.Status_Permit_Coklat" class="tamu-auto-approved-box p-3 rounded-3 border text-center" :class="isItemPendingApproval(tamuData) ? 'bg-amber-50/60 border-amber-200' : 'bg-slate-50 border-slate-200'">
                            <div v-if="isItemPendingApproval(tamuData)">
                                <HcisIcon name="hourglass-split" size="lg" class="text-amber-500 mb-2" />
                                <div class="fw-bold text-slate-900 text-sm">Menunggu Persetujuan Permit</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    Izin akses gerbang (Permit Coklat) sedang diproses oleh verifikator/approver sebelum kunjungan dapat dilakukan.
                                </div>
                            </div>
                            <div v-else-if="tamuData.Status_Permit_Coklat === 'DITOLAK' || tamuData.Status_Approval === 'REJECTED'">
                                <HcisIcon name="slash-circle" size="lg" class="text-danger mb-2" />
                                <div class="fw-bold text-slate-900 text-sm">Permit Akses Ditolak</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    Pengajuan izin akses gerbang ditolak oleh verifikator.
                                </div>
                            </div>
                            <div v-else>
                                <HcisIcon name="check-circle-fill" size="lg" class="text-success mb-2" />
                                <div class="fw-bold text-slate-900 text-sm">Izin Akses Disetujui</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    Izin akses gerbang (Permit Coklat) telah diterbitkan dan diverifikasi.
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-top border-slate-200/60 d-flex justify-content-center">
                                <HcisButton
                                    size="xs"
                                    variant="secondary"
                                    leading-icon="shield-lock-fill"
                                    label="Lihat Detail Permit"
                                    @click="openPermitModal(tamuData)"
                                />
                            </div>
                        </div>
                        <div v-else class="tamu-auto-approved-box p-3 rounded-3 bg-slate-50 border text-center">
                            <HcisIcon name="check-circle-fill" size="lg" class="text-primary mb-2" />
                            <div class="fw-bold text-slate-900 text-sm">Otorisasi Langsung</div>
                            <div class="text-xs text-slate-600 mt-1">
                                Transaksi tamu ini disetujui otomatis tanpa alur persetujuan berjenjang.
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- Right Pane: Visitor Specifications, Details & Logistics -->
            <template #right>
                <div v-if="tamuData">
                    <!-- Cancellation / Rejection Alert Banner -->
                    <HcisAlert
                        v-if="isKunjunganBerakhirTanpaHadir(tamuData)"
                        variant="danger"
                        appearance="glass"
                        icon="slash-circle"
                        :title="judulBerakhir(tamuData)"
                        :description="getRejectionReason(tamuData) || 'Pengajuan kunjungan ini telah dibatalkan atau tidak dilanjutkan.'"
                        class="mb-4"
                    />

                    <!-- Permit Akses Gerbang (Permit Coklat) Banner -->
                    <HcisAlert
                        v-if="(tamuData.Tipe_Tamu === 'B2B' || tamuData.Status_Permit_Coklat) && permitBannerInfo"
                        :variant="permitBannerInfo.variant"
                        appearance="soft"
                        size="sm"
                        :title="permitBannerInfo.title"
                        :description="permitBannerInfo.desc"
                        :badge="permitBannerInfo.statusLabel"
                        :icon="permitBannerInfo.icon"
                        class="mb-4"
                    >
                        <template #actions>
                            <HcisButton
                                size="xs"
                                variant="outline"
                                leading-icon="shield-lock-fill"
                                label="Lihat Detail Permit"
                                @click="openPermitModal(tamuData)"
                            />
                        </template>
                    </HcisAlert>

                    <!-- Guest Specifications Grid Card -->
                    <HcisCard
                        title="Informasi Kunjungan"
                        description="Rincian identitas tamu, instansi, kontak, dan jadwal kunjungan."
                        icon="person-vcard-fill"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <!-- Visitor Profile Header Strip -->
                        <div class="tamu-visitor-strip mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2.5 min-w-0">
                                    <div class="tamu-visitor-avatar">
                                        <HcisIcon name="person-fill" size="sm" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="tamu-visitor-name text-truncate">
                                            {{ tamuData.Nama_Tamu || tamuData.Nama_Lengkap_Tamu || '-' }}
                                        </div>
                                        <div class="tamu-visitor-instansi text-truncate">
                                            <HcisIcon name="buildings" size="2xs" class="text-slate-400" />
                                            <span>{{ tamuData.Instansi_Asal || tamuData.Instansi_Atau_Asal || 'Umum / Personal' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                    <HcisBadge
                                        :label="tamuData.Tipe_Tamu === 'B2B' ? 'Tamu Mitra Kerja' : 'Tamu Regular'"
                                        :variant="tamuData.Tipe_Tamu === 'B2B' ? 'brand' : 'neutral'"
                                        size="xs"
                                        appearance="subtle"
                                    />
                                    <span v-if="tamuData.Jumlah_Tamu > 1" class="tamu-mini-pill">
                                        {{ tamuData.Jumlah_Tamu }} Orang
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 4-Tile Detailed Spec Grid -->
                        <div class="tamu-specs-grid">
                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="person-badge" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Identitas Resmi (PIC)</span>
                                </div>
                                <div class="tamu-spec-tile__val d-flex align-items-center justify-content-between gap-1 font-tabular">
                                    <div class="d-flex align-items-center gap-1.5 min-w-0">
                                        <span class="tamu-id-type-badge font-mono fw-bold">
                                            {{ tamuData.Jenis_Identitas || 'KTP' }}
                                        </span>
                                        <span class="font-mono fw-bold text-slate-900 text-truncate">
                                            {{ tamuData.No_Identitas || '-' }}
                                        </span>
                                    </div>
                                    <button
                                        v-if="tamuData.No_Identitas"
                                        type="button"
                                        class="tamu-tile-copy-btn"
                                        title="Salin nomor identitas"
                                        @click="copyText(tamuData.No_Identitas, 'Nomor identitas')"
                                    >
                                        <HcisIcon name="copy" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="telephone-fill" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Nomor Kontak / WhatsApp</span>
                                </div>
                                <div class="tamu-spec-tile__val d-flex align-items-center justify-content-between gap-1 font-tabular">
                                    <a
                                        v-if="tamuData.No_HP || tamuData.Nomor_Handphone"
                                        :href="`tel:${tamuData.No_HP || tamuData.Nomor_Handphone}`"
                                        class="text-primary text-decoration-none fw-semibold"
                                    >
                                        {{ tamuData.No_HP || tamuData.Nomor_Handphone }}
                                    </a>
                                    <span v-else class="text-muted">-</span>
                                    <button
                                        v-if="tamuData.No_HP || tamuData.Nomor_Handphone"
                                        type="button"
                                        class="tamu-tile-copy-btn"
                                        title="Salin nomor telepon"
                                        @click="copyText(tamuData.No_HP || tamuData.Nomor_Handphone, 'Nomor telepon')"
                                    >
                                        <HcisIcon name="copy" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="calendar-event-fill" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Kategori Kunjungan</span>
                                </div>
                                <div class="tamu-spec-tile__val font-tabular text-slate-800 fw-semibold">
                                    {{ tamuData.Tamu_Kategori === 'MITRA' ? 'Mitra (lebih dari 24 jam)' : 'Visitor (maks. 24 jam)' }}
                                </div>
                            </div>

                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="clock-fill" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Estimasi Masuk – Keluar</span>
                                </div>
                                <div class="tamu-spec-tile__val font-tabular">
                                    <template v-if="jendelaKunjunganDetail(tamuData)">
                                        <!-- Same day visit -->
                                        <div v-if="jendelaKunjunganDetail(tamuData).isSameDay" class="tamu-jendela-compact">
                                            <div class="fw-bold text-slate-900">{{ jendelaKunjunganDetail(tamuData).dateStr }}</div>
                                            <div class="text-xs text-slate-600 font-medium mt-0.5">
                                                {{ jendelaKunjunganDetail(tamuData).timeRangeStr }}
                                            </div>
                                        </div>
                                        <!-- Multi-day visit -->
                                        <div v-else class="tamu-jendela-timeline">
                                            <div class="tamu-jendela-row">
                                                <span class="tamu-jendela-dot is-in" title="Estimasi Masuk" />
                                                <div class="tamu-jendela-text">
                                                    <span>{{ jendelaKunjunganDetail(tamuData).startDateStr }}</span>
                                                    <span class="text-slate-500 ms-1">{{ jendelaKunjunganDetail(tamuData).startTimeStr }}</span>
                                                </div>
                                            </div>
                                            <div class="tamu-jendela-connector" />
                                            <div class="tamu-jendela-row">
                                                <span class="tamu-jendela-dot is-out" title="Estimasi Keluar" />
                                                <div class="tamu-jendela-text">
                                                    <span>{{ jendelaKunjunganDetail(tamuData).endDateStr }}</span>
                                                    <span class="text-slate-500 ms-1">{{ jendelaKunjunganDetail(tamuData).endTimeStr }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <span v-else class="text-slate-700">
                                        {{ labelJendela(tamuData) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Keperluan Kunjungan Callout (Default Brand Theme) -->
                        <div v-if="tamuData.Deksripsi_Keperluan" class="tamu-reason-callout mt-3">
                            <div class="tamu-reason-callout__header">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="tamu-reason-callout__icon">
                                        <HcisIcon name="chat-quote-fill" size="xs" />
                                    </span>
                                    <span class="tamu-reason-callout__title">Keperluan Kunjungan</span>
                                </div>
                                <span class="tamu-reason-callout__tag">Catatan Tamu</span>
                            </div>
                            <div class="tamu-reason-callout__body mt-1">
                                <p class="tamu-reason-callout__text mb-0">
                                    “{{ tamuData.Deksripsi_Keperluan }}”
                                </p>
                            </div>
                        </div>

                        <!-- Keterangan Tambahan jika ada -->
                        <div v-if="tamuData.Keterangan_Tambahan || tamuData.Keterangan" class="tamu-note-callout mt-3">
                            <div class="d-flex align-items-center gap-1.5 mb-1 text-slate-700 fw-bold text-xs">
                                <HcisIcon name="info-circle" size="xs" class="text-slate-400" />
                                <span>Keterangan Tambahan</span>
                            </div>
                            <p class="text-xs text-slate-600 mb-0">
                                {{ tamuData.Keterangan_Tambahan || tamuData.Keterangan }}
                            </p>
                        </div>
                    </HcisCard>

                    <!-- Transport & Party Specifications Card -->
                    <HcisCard
                        title="Transportasi & Rombongan"
                        description="Informasi sarana kendaraan dan daftar anggota rombongan tamu."
                        icon="people"
                        sentiment="brand"
                        variant="glass"
                        size="sm"
                        divider
                    >
                        <!-- 1. Moda Kedatangan & Kendaraan -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="tamu-spec-tile__label">Moda Kedatangan</span>
                                <span v-if="tamuData.No_Plat_Kendaraan" class="text-2xs text-muted">Area Parkir Tamu</span>
                            </div>

                            <div v-if="tamuData.No_Plat_Kendaraan" class="tamu-transport-card">
                                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="tamu-transport-icon-box">
                                            <HcisIcon :name="getVehicleIcon(tamuData.Jenis_Kendaraan)" size="md" />
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-900 text-sm">
                                                {{ tamuData.Jenis_Kendaraan || 'Kendaraan Bermotor' }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                Tercatat parkir di area fasilitas perusahaan
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tamu-plate-badge tamu-plate-badge--lg">
                                        {{ tamuData.No_Plat_Kendaraan }}
                                    </div>
                                </div>
                                <div
                                    v-if="tamuData.Foto_Kendaraan_Url"
                                    class="tamu-foto-kendaraan-wrap mt-3"
                                    role="button"
                                    tabindex="0"
                                    title="Klik untuk melihat dan memperbesar foto kendaraan"
                                    @click="bukaFotoKendaraan(tamuData)"
                                >
                                    <img
                                        :src="tamuData.Foto_Kendaraan_Url"
                                        alt="Foto kendaraan tamu"
                                        class="tamu-foto-kendaraan"
                                        loading="lazy"
                                    />
                                    <div class="tamu-foto-overlay">
                                        <HcisIcon name="zoom-in" size="sm" />
                                        <span>Klik untuk memperbesar</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="tamu-transport-empty">
                                <HcisIcon name="person-walking" size="md" class="text-slate-400 mb-1" />
                                <div class="fw-semibold text-slate-700 text-xs">Tanpa Kendaraan Pribadi</div>
                                <div class="text-2xs text-slate-400">Tamu berkunjung sebagai pejalan kaki, taksi/ojek online, atau transportasi umum.</div>
                            </div>
                        </div>

                        <!-- 2. Rombongan & Anggota Tamu -->
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="tamu-spec-tile__label">Daftar Anggota Rombongan</span>
                                <span class="tamu-mini-pill">
                                    Total: {{ tamuData.Jumlah_Tamu || 1 }} Orang
                                </span>
                            </div>

                            <div v-if="bacaDaftarAnggota(tamuData.Daftar_Anggota_Json).length" class="tamu-party-list">
                                <div
                                    v-for="(a, i) in bacaDaftarAnggota(tamuData.Daftar_Anggota_Json)"
                                    :key="i"
                                    class="tamu-party-row"
                                >
                                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                                        <span class="tamu-party-index">#{{ i + 1 }}</span>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-slate-900 text-xs text-truncate">
                                                {{ a.nama }}
                                            </div>
                                            <div v-if="a.no_identitas" class="d-flex align-items-center gap-1.5 mt-0.5">
                                                <span class="tamu-id-type-badge font-mono">
                                                    {{ a.jenis_identitas || 'ID' }}
                                                </span>
                                                <span class="font-mono text-xs text-slate-600">
                                                    {{ a.no_identitas }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        v-if="a.no_identitas"
                                        type="button"
                                        class="tamu-tile-copy-btn"
                                        title="Salin nomor identitas"
                                        @click="copyText(a.no_identitas, 'Nomor identitas anggota')"
                                    >
                                        <HcisIcon name="copy" size="2xs" />
                                    </button>
                                </div>
                            </div>
                            <div v-else class="tamu-transport-empty p-3">
                                <span v-if="(tamuData.Jumlah_Tamu || 1) <= 1" class="text-xs text-slate-600">
                                    Kunjungan tunggal (1 orang tanpa rombongan tambahan)
                                </span>
                                <span v-else class="text-xs text-slate-600">
                                    {{ tamuData.Jumlah_Tamu || 1 }} orang (daftar nama tidak dikumpulkan)
                                </span>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- Dialog Footer -->
            <template #footer-start>
                <HcisButton
                    v-if="tamuData && canCancelTamu(tamuData)"
                    variant="danger-quiet"
                    size="md"
                    leading-icon="slash-circle"
                    label="Batalkan Pengajuan"
                    @click="openCancelModal(tamuData)"
                />
            </template>

            <template #footer>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="secondary"
                        size="md"
                        leading-icon="x-lg"
                        label="Tutup"
                        @click="closeModal"
                    />
                </div>
            </template>
        </HcisDualPaneDialog>

        <!-- Cancel Request Confirmation Dialog -->
        <HcisDialog
            v-model="showCancelModal"
            title="Konfirmasi Pembatalan Pengajuan"
            kicker="Visitor · Guest Management"
            description="Pengajuan tamu yang dibatalkan tidak akan diproses oleh approver."
            icon="slash-circle"
            sentiment="danger"
            variant="glass"
            pattern="grid"
            size="md"
            sticky-footer
            :close-on-overlay="!cancelLoading"
            :close-on-esc="!cancelLoading"
            @close="closeCancelModal"
        >
            <div v-if="itemToCancel" class="tamu-confirm-box p-3 rounded-3 border bg-slate-50">
                <p class="text-xs text-slate-700 mb-2">
                    Apakah Anda yakin ingin membatalkan pengajuan kunjungan untuk tamu
                    <strong>{{ itemToCancel.Nama_Tamu || itemToCancel.Nama_Lengkap_Tamu }}</strong>?
                </p>
                <div class="text-2xs text-muted">
                    No. Transaksi: <span class="font-tabular font-mono">{{ itemToCancel.No_Transaksi }}</span>
                </div>
            </div>
            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton
                        size="md"
                        variant="secondary"
                        label="Kembali"
                        :disabled="cancelLoading"
                        @click="closeCancelModal"
                    />
                    <HcisButton
                        size="md"
                        variant="danger"
                        leading-icon="slash-circle"
                        label="Ya, Batalkan Pengajuan"
                        :loading="cancelLoading"
                        @click="confirmCancel"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- Permit Detail Dialog -->
        <PermitReviewModal
            v-if="showPermitModal"
            :show="showPermitModal"
            :permit-id="selectedPermitId"
            :detail-endpoint="selectedPermitEndpoint"
            read-only
            @close="closePermitModal"
        />

        <!-- HCIS File Viewer (Vehicle Photo / Documents) -->
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
import { ElMessage, ElNotification } from 'element-plus';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDataTable,
    HcisDatePicker,
    HcisDialog,
    HcisDualPaneDialog,
    HcisEmptyState,
    HcisFileViewer,
    HcisIcon,
    HcisTimeline,
} from '@/components/ui';
import { salinTeks } from '@/utils/clipboard';
import PermitReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue';
import { bacaDaftarAnggota } from '../daftarAnggota';

const NAMA_BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default {
    name: 'TamuHistory',
    components: {
        HcisAlert,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDataTable,
        HcisDatePicker,
        HcisDialog,
        HcisDualPaneDialog,
        HcisEmptyState,
        HcisFileViewer,
        HcisIcon,
        HcisTimeline,
        PermitReviewModal,
    },
    emits: ['tamu-updated', 'tamu-canceled', 'navigate-to-form'],
    props: {
        // Ditetapkan halaman (rute): riwayat Regular dan B2B terpisah.
        tipe: { type: String, default: 'REGULAR' },
        apiPrefix: { type: String, default: 'transaksitamu' },
        title: { type: String, default: 'Buku Tamu & Riwayat' },
        readOnly: { type: Boolean, default: false },
        scheduleMode: { type: Boolean, default: false },
        kategoriOptions: { type: Array, default: () => [] },
    },
    data() {
        return {
            tableData: [],
            loading: false,
            currentPage: 1,
            totalPages: 1,
            totalElements: 0,
            itemsPerPage: 15,
            fetchError: '',

            // Search & Filter State
            searchQuery: '',
            activeChip: 'ALL',
            startDate: '',
            endDate: '',
            selectedKategori: '',
            selectedKendaraan: '',
            activeTimePreset: this.scheduleMode ? 'TODAY' : 'ALL',
            filterValues: {
                kategori: '',
                kendaraan: '',
                dateRange: [],
            },
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',

            // Detail Modal
            showModal: false,
            detailLoading: false,
            selectedItem: null,
            tamuData: null,

            // File Viewer (Vehicle Photo / Documents)
            showFileViewer: false,
            viewerFiles: [],
            viewerInitialIndex: 0,

            // Permit Detail Modal
            showPermitModal: false,
            selectedPermitId: null,
            selectedPermitEndpoint: '/transaksipermit',

            // Cancel confirmation modal
            showCancelModal: false,
            cancelLoading: false,
            itemToCancel: null,
        };
    },
    computed: {
        filterDefinitions() {
            const defs = [];
            if (this.scheduleMode && this.kategoriOptions.length > 1) {
                defs.push({
                    key: 'kategori',
                    label: 'Kategori Tamu',
                    type: 'select',
                    options: [
                        { value: '', label: 'Semua Kategori' },
                        ...this.kategoriOptions.map((k) => ({ value: k.kode, label: k.label })),
                    ],
                });
            }

            defs.push({
                key: 'kendaraan',
                label: 'Moda Transportasi',
                type: 'select',
                options: [
                    { value: '', label: 'Semua Moda Transportasi' },
                    { value: 'BERKENDARA', label: 'Berkendara (Ada Plat Nomor)' },
                    { value: 'TANPA_KENDARAAN', label: 'Tanpa Kendaraan (Pejalan Kaki)' },
                ],
            });

            defs.push({
                key: 'dateRange',
                label: 'Rentang Tanggal Kustom',
                type: 'daterange',
            });

            return defs;
        },
        labelRentangSingkat() {
            if (!this.startDate && !this.endDate) return 'Semua Periode';
            if (this.startDate === this.endDate) {
                return this.formatDateDisplay(this.startDate);
            }
            return `${this.formatDateDisplay(this.startDate, true)} - ${this.formatDateDisplay(this.endDate)}`;
        },
        groupedByDay() {
            if (!Array.isArray(this.tableData) || !this.tableData.length) return [];

            const todayStr = this.formatDateISO(new Date());
            const dTom = new Date();
            dTom.setDate(dTom.getDate() + 1);
            const tomorrowStr = this.formatDateISO(dTom);

            const map = new Map();
            for (const item of this.tableData) {
                const rawDate = item.Tanggal_Kunjungan || item.Waktu_Mulai_Kunjungan || item.Created_At;
                const dateKey = rawDate ? String(rawDate).split('T')[0].split(' ')[0] : 'Lainnya';
                if (!map.has(dateKey)) {
                    map.set(dateKey, []);
                }
                map.get(dateKey).push(item);
            }

            const groups = [];
            for (const [date, items] of map.entries()) {
                const totalTamu = items.reduce((sum, i) => sum + (parseInt(i.Jumlah_Tamu, 10) || 1), 0);
                const isToday = date === todayStr;
                const isTomorrow = date === tomorrowStr;
                const formattedDate = this.formatDayDateHeader(date);

                groups.push({
                    date,
                    formattedDate,
                    isToday,
                    isTomorrow,
                    totalTamu,
                    items,
                });
            }

            return groups;
        },
        columns() {
            return [
                { key: 'Tamu_Instansi', label: 'Tamu & Instansi', minWidth: '230px' },
                { key: 'PIC_Keperluan', label: 'PIC & Keperluan', minWidth: '180px' },
                { key: 'Jadwal_Kendaraan', label: 'Jadwal & Kendaraan', minWidth: '190px' },
                { key: 'Log_Kehadiran', label: 'Kehadiran', minWidth: '175px' },
                { key: 'Status_Kunjungan', label: 'Status', width: '150px', align: 'center' },
                { key: 'actions', label: 'Aksi', width: '110px', align: 'center' },
            ];
        },
        activeGuestsCount() {
            return this.tableData.filter((item) => item.Status_Kunjungan === 'CHECKED_IN').length;
        },
        quickChips() {
            const checkedInCount = this.tableData.filter((i) => i.Status_Kunjungan === 'CHECKED_IN').length;
            const pendingCount = this.tableData.filter((i) => this.isItemPendingApproval(i)).length;
            const scheduledCount = this.tableData.filter((i) => this.isItemScheduled(i)).length;
            const checkedOutCount = this.tableData.filter((i) => i.Status_Kunjungan === 'CHECKED_OUT').length;
            const cancelledCount = this.tableData.filter((i) => this.isKunjunganBerakhirTanpaHadir(i)).length;

            if (this.scheduleMode) {
                return [
                    {
                        id: 'ALL',
                        value: 'ALL',
                        label: 'Semua Agenda',
                        icon: 'calendar3-range',
                        badge: this.totalElements || this.tableData.length,
                    },
                    {
                        id: 'CHECKED_IN',
                        value: 'CHECKED_IN',
                        label: 'Check-In',
                        icon: 'geo-alt-fill',
                        color: 'brand',
                        ping: checkedInCount > 0,
                        badge: checkedInCount,
                    },
                    {
                        id: 'SCHEDULED',
                        value: 'SCHEDULED',
                        label: 'Scheduled',
                        icon: 'calendar-check',
                        color: 'success',
                        badge: scheduledCount,
                    },
                    {
                        id: 'PENDING_APPROVAL',
                        value: 'PENDING_APPROVAL',
                        label: 'Menunggu Approval',
                        icon: 'hourglass-split',
                        color: 'warning',
                        badge: pendingCount,
                    },
                    {
                        id: 'CHECKED_OUT',
                        value: 'CHECKED_OUT',
                        label: 'Check-Out',
                        icon: 'box-arrow-right',
                        color: 'neutral',
                        badge: checkedOutCount,
                    },
                    {
                        id: 'BATAL',
                        value: 'BATAL',
                        label: 'Cancelled',
                        icon: 'slash-circle',
                        color: 'danger',
                        badge: cancelledCount,
                    },
                ];
            }

            return [
                {
                    id: 'ALL',
                    value: 'ALL',
                    label: 'Semua Riwayat',
                    icon: 'people-fill',
                    badge: this.totalElements || this.tableData.length,
                },
                {
                    id: 'CHECKED_IN',
                    value: 'CHECKED_IN',
                    label: 'Check-In',
                    icon: 'geo-alt-fill',
                    color: 'brand',
                    ping: checkedInCount > 0,
                    badge: checkedInCount,
                },
                {
                    id: 'PENDING_APPROVAL',
                    value: 'PENDING_APPROVAL',
                    label: 'Menunggu Approval',
                    icon: 'hourglass-split',
                    color: 'warning',
                    badge: pendingCount,
                },
                {
                    id: 'SCHEDULED',
                    value: 'SCHEDULED',
                    label: 'Scheduled',
                    icon: 'calendar-check',
                    color: 'brand',
                    badge: scheduledCount,
                },
                {
                    id: 'CHECKED_OUT',
                    value: 'CHECKED_OUT',
                    label: 'Check-Out',
                    icon: 'box-arrow-right',
                    color: 'neutral',
                    badge: checkedOutCount,
                },
                {
                    // Rejected, Withdrawn, Revoked, Cancelled — lihat getList() 'BATAL'.
                    id: 'BATAL',
                    value: 'BATAL',
                    label: 'Cancelled',
                    icon: 'slash-circle',
                    color: 'danger',
                    badge: cancelledCount,
                },
            ];
        },
        approvalTimelineItems() {
            if (!this.tamuData || !Array.isArray(this.tamuData.Approvals)) {
                return [];
            }
            return this.tamuData.Approvals.map((step) => {
                let statusLabel = 'Menunggu Review';
                let sentiment = 'warning';
                let icon = 'hourglass-split';

                if (this.isApprovalStepExpired(step)) {
                    statusLabel = 'Kadaluarsa';
                    sentiment = 'danger';
                    icon = 'clock-history';
                } else if (step.Flag_Approval === 'Y') {
                    statusLabel = 'Disetujui';
                    sentiment = 'success';
                    icon = 'check-circle-fill';
                } else if (step.Flag_Approval === 'T') {
                    statusLabel = 'Ditolak';
                    sentiment = 'danger';
                    icon = 'x-circle-fill';
                }

                let timeStr = '';
                if (step.Tanggal_Approval) {
                    timeStr = this.formatDateTime(step.Tanggal_Approval);
                } else if (step.Expired_At) {
                    timeStr = `Batas: ${this.formatDateTime(step.Expired_At)}`;
                }

                const meta = [];
                if (step.Tanggal_Approval && step.Flag_Approval === 'Y') {
                    meta.push({
                        label: 'Disetujui pada',
                        value: this.formatDateTime(step.Tanggal_Approval),
                    });
                } else if (step.Tanggal_Approval && step.Flag_Approval === 'T') {
                    meta.push({
                        label: 'Ditolak pada',
                        value: this.formatDateTime(step.Tanggal_Approval),
                    });
                } else if (step.Expired_At && this.isApprovalStepExpired(step)) {
                    meta.push({
                        label: 'Batas respon',
                        value: this.formatDateTime(step.Expired_At),
                    });
                }

                return {
                    title: step.Nama_Approval || 'Persetujuan Kunjungan',
                    name: step.Nama_Approver || step.Approver_Karyawan_Kode || 'Approver',
                    role: step.Nama_Approval || 'Approver',
                    status: statusLabel,
                    sentiment,
                    icon,
                    time: timeStr,
                    meta,
                    description: step.Catatan ? `"${step.Catatan}"` : '',
                };
            });
        },
        permitBannerInfo() {
            if (!this.tamuData) return null;
            const isMitra = this.tamuData.Tipe_Tamu === 'B2B';
            const statusPermit = String(this.tamuData.Status_Permit_Coklat || '').toUpperCase();
            const statusKunjungan = String(this.tamuData.Status_Kunjungan || '').toUpperCase();
            const statusApproval = String(this.tamuData.Status_Approval || '').toUpperCase();

            // 1. Ditolak (Permit Ditolak / Approval Ditolak / Kunjungan Ditolak)
            if (
                statusPermit === 'DITOLAK' ||
                statusPermit === 'REJECTED' ||
                statusApproval === 'REJECTED' ||
                statusKunjungan === 'REJECTED'
            ) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: isMitra
                        ? 'Izin akses gerbang tidak disetujui untuk kunjungan mitra kerja ini.'
                        : 'Izin akses gerbang tidak disetujui.',
                    statusLabel: 'Ditolak',
                    variant: 'danger',
                    icon: 'shield-x',
                };
            }

            // 2. Dibatalkan (Permit Dibatalkan / Kunjungan Dibatalkan)
            if (
                statusPermit === 'DIBATALKAN' ||
                statusPermit === 'CANCELED' ||
                ['WITHDRAWN', 'REVOKED'].includes(statusKunjungan)
            ) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Izin akses gerbang dibatalkan.',
                    statusLabel: 'Dibatalkan',
                    variant: 'neutral',
                    icon: 'shield-slash',
                };
            }

            // 3. Sedang Aktif di Area (Checked-In)
            if (statusKunjungan === 'CHECKED_IN') {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Tamu mitra kerja telah check-in di pos gerbang dan berada di dalam area.',
                    statusLabel: 'Sedang Aktif',
                    variant: 'brand',
                    icon: 'shield-check',
                };
            }

            // 4. Kunjungan Selesai (Checked-Out)
            if (statusKunjungan === 'CHECKED_OUT') {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Kunjungan telah selesai dan akses gerbang telah ditutup.',
                    statusLabel: 'Selesai',
                    variant: 'success',
                    icon: 'shield-check',
                };
            }

            // 5. Permit Kadaluarsa (Expired). Masa berlaku izin ditentukan
            //    jendela permit dari admin: kunjungan yang tanggalnya lewat
            //    TIDAK membuat izin yang masih AKTIF terbaca kadaluarsa.
            if (statusPermit === 'EXPIRED' || (statusKunjungan === 'CANCELLED' && statusPermit !== 'AKTIF')) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Masa berlaku izin akses gerbang telah habis.',
                    statusLabel: 'Kadaluarsa',
                    variant: 'neutral',
                    icon: 'clock-history',
                };
            }

            // 6. Menunggu Verifikasi / Pending (Jika permit atau kunjungan masih pending / belum disetujui)
            const isPending =
                statusKunjungan === 'PENDING_APPROVAL' ||
                statusApproval === 'PENDING' ||
                statusApproval === 'MENUNGGU_APPROVAL' ||
                statusPermit === 'MENUNGGU_APPROVAL' ||
                statusPermit === 'PENDING' ||
                statusPermit === '' ||
                statusPermit === 'TANPA_AKSES';

            if (isPending) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: isMitra
                        ? 'Kunjungan mitra kerja memerlukan verifikasi PIC sebelum izin akses gerbang diterbitkan.'
                        : 'Otorisasi akses gerbang memerlukan verifikasi sebelum kunjungan.',
                    statusLabel: 'Menunggu Verifikasi',
                    variant: 'warning',
                    icon: 'clock-history',
                };
            }

            // 7. Sudah Disetujui / Terbit (Hanya jika permit terkonfirmasi AKTIF/APPROVED dan tidak pending)
            if (
                statusPermit === 'AKTIF' ||
                statusPermit === 'APPROVED' ||
                statusPermit === 'DISETUJUI' ||
                statusPermit === 'SUDAH_DIBUAT' ||
                (statusKunjungan === 'SCHEDULED' && statusApproval === 'APPROVED')
            ) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Izin akses gerbang telah disetujui dan siap diverifikasi di pos gerbang.',
                    statusLabel: 'Disetujui',
                    variant: 'success',
                    icon: 'shield-check',
                };
            }

            // Fallback: Default Menunggu Verifikasi
            return {
                title: 'Izin Akses Gerbang (Permit Coklat)',
                desc: isMitra
                    ? 'Kunjungan mitra kerja memerlukan verifikasi PIC sebelum izin akses gerbang diterbitkan.'
                    : 'Otorisasi akses gerbang memerlukan verifikasi sebelum kunjungan.',
                statusLabel: 'Menunggu Verifikasi',
                variant: 'warning',
                icon: 'clock-history',
            };
        },
    },
    mounted() {
        this.detectInitialViewMode();
        if (this.scheduleMode) {
            const todayStr = this.formatDateISO(new Date());
            this.startDate = todayStr;
            this.endDate = todayStr;
            this.activeTimePreset = 'TODAY';
            this.filterValues.dateRange = [todayStr, todayStr];
        }
        this.fetchData();
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', this.detectInitialViewMode);
        }
    },
    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', this.detectInitialViewMode);
        }
    },
    methods: {
        bacaDaftarAnggota,
        detectInitialViewMode() {
            if (typeof window !== 'undefined') {
                this.viewMode = window.innerWidth < 768 ? 'grid' : 'table';
            }
        },
        onViewModeChange(mode) {
            this.viewMode = mode;
        },
        formatDateISO(d) {
            if (!(d instanceof Date) || isNaN(d.getTime())) return '';
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        formatDateDisplay(dateStr, hideYearIfSame = false) {
            if (!dateStr) return '';
            try {
                const parts = String(dateStr).split('T')[0].split(' ')[0].split('-');
                if (parts.length === 3) {
                    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    return d.toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'short',
                        ...(hideYearIfSame ? {} : { year: 'numeric' }),
                    });
                }
                return dateStr;
            } catch {
                return dateStr;
            }
        },
        formatDayDateHeader(dateStr) {
            if (!dateStr || dateStr === 'Lainnya') return 'Tanggal Belum Ditentukan';
            try {
                const parts = String(dateStr).split('T')[0].split(' ')[0].split('-');
                if (parts.length === 3) {
                    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    return d.toLocaleDateString('id-ID', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                    });
                }
                return dateStr;
            } catch {
                return dateStr;
            }
        },
        async fetchData() {
            this.loading = true;
            this.fetchError = '';
            try {
                const params = {
                    page: this.currentPage,
                    limit: this.itemsPerPage,
                    search: this.searchQuery || '',
                    status: this.activeChip === 'ALL' ? '' : this.activeChip,
                };

                if (this.selectedKategori) {
                    params.kategori = this.selectedKategori;
                }
                if (this.selectedKendaraan) {
                    params.kendaraan = this.selectedKendaraan;
                }
                if (this.startDate) {
                    params.date_from = this.startDate;
                }
                if (this.endDate) {
                    params.date_to = this.endDate;
                }

                const { data } = await axios.get(`/${this.apiPrefix}-list`, { params });

                if (data.success) {
                    this.tableData = data.result.data || data.result || [];

                    if (data.pagination) {
                        this.totalPages = data.pagination.total_pages || Math.ceil(data.pagination.total / this.itemsPerPage) || 1;
                        this.totalElements = data.pagination.total || 0;
                    } else if (data.result && typeof data.result.current_page !== 'undefined') {
                        this.totalPages = data.result.last_page || 1;
                        this.totalElements = data.result.total || 0;
                    } else {
                        this.totalElements = this.tableData.length;
                    }
                } else {
                    this.fetchError = data.message || 'Gagal memuat riwayat tamu.';
                }
            } catch (e) {
                this.fetchError = 'Gagal memuat data buku tamu. Silakan periksa jaringan dan coba lagi.';
            } finally {
                this.loading = false;
            }
        },

        setTimePreset(preset) {
            this.activeTimePreset = preset;
            const today = new Date();
            const todayStr = this.formatDateISO(today);

            if (preset === 'TODAY') {
                this.startDate = todayStr;
                this.endDate = todayStr;
            } else if (preset === 'TOMORROW') {
                const d = new Date(today);
                d.setDate(d.getDate() + 1);
                const tomorrowStr = this.formatDateISO(d);
                this.startDate = tomorrowStr;
                this.endDate = tomorrowStr;
            } else if (preset === 'SEVEN_DAYS') {
                const end = new Date(today);
                end.setDate(end.getDate() + 6);
                this.startDate = todayStr;
                this.endDate = this.formatDateISO(end);
            } else if (preset === 'FOURTEEN_DAYS') {
                const end = new Date(today);
                end.setDate(end.getDate() + 13);
                this.startDate = todayStr;
                this.endDate = this.formatDateISO(end);
            } else if (preset === 'THIRTY_DAYS') {
                const end = new Date(today);
                end.setDate(end.getDate() + 29);
                this.startDate = todayStr;
                this.endDate = this.formatDateISO(end);
            } else if (preset === 'ALL') {
                this.startDate = '';
                this.endDate = '';
            }

            this.filterValues.dateRange = this.startDate && this.endDate ? [this.startDate, this.endDate] : [];
            this.currentPage = 1;
            this.fetchData();
        },

        geserPeriode(arah) {
            if (!this.startDate && !this.endDate) {
                this.setTimePreset('TODAY');
                return;
            }
            const sParts = this.startDate.split('-');
            const eParts = (this.endDate || this.startDate).split('-');
            const start = new Date(parseInt(sParts[0], 10), parseInt(sParts[1], 10) - 1, parseInt(sParts[2], 10));
            const end = new Date(parseInt(eParts[0], 10), parseInt(eParts[1], 10) - 1, parseInt(eParts[2], 10));

            const diffTime = Math.abs(end.getTime() - start.getTime());
            const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24)) + 1;

            let shiftDays = 1;
            if (this.activeTimePreset === 'SEVEN_DAYS' || diffDays === 7) {
                shiftDays = 7;
            } else if (this.activeTimePreset === 'FOURTEEN_DAYS' || diffDays === 14) {
                shiftDays = 14;
            } else if (this.activeTimePreset === 'THIRTY_DAYS' || diffDays === 30) {
                shiftDays = 30;
            } else if (diffDays > 1) {
                shiftDays = diffDays;
            }

            start.setDate(start.getDate() + arah * shiftDays);
            end.setDate(end.getDate() + arah * shiftDays);

            this.startDate = this.formatDateISO(start);
            this.endDate = this.formatDateISO(end);

            const todayStr = this.formatDateISO(new Date());
            const dTom = new Date();
            dTom.setDate(dTom.getDate() + 1);
            const tomorrowStr = this.formatDateISO(dTom);

            if (this.startDate === todayStr && this.endDate === todayStr) {
                this.activeTimePreset = 'TODAY';
            } else if (this.startDate === tomorrowStr && this.endDate === tomorrowStr) {
                this.activeTimePreset = 'TOMORROW';
            } else {
                this.activeTimePreset = 'CUSTOM';
            }

            this.filterValues.dateRange = [this.startDate, this.endDate];
            this.currentPage = 1;
            this.fetchData();
        },

        onFilterChange(values) {
            this.filterValues = { ...this.filterValues, ...values };
            if (values.kategori !== undefined) {
                this.selectedKategori = values.kategori;
            }
            if (values.kendaraan !== undefined) {
                this.selectedKendaraan = values.kendaraan;
            }
            if (values.dateRange !== undefined) {
                if (Array.isArray(values.dateRange) && values.dateRange.length === 2 && values.dateRange[0] && values.dateRange[1]) {
                    this.startDate = values.dateRange[0];
                    this.endDate = values.dateRange[1];
                    this.activeTimePreset = 'CUSTOM';
                } else {
                    this.startDate = '';
                    this.endDate = '';
                    this.activeTimePreset = 'ALL';
                }
            }
            this.currentPage = 1;
            this.fetchData();
        },

        onCategoryChange() {
            this.currentPage = 1;
            this.fetchData();
        },

        getDateBadge(dateStr) {
            if (!dateStr) return null;
            const today = this.formatDateISO(new Date());
            const d = new Date();
            d.setDate(d.getDate() + 1);
            const tomorrow = this.formatDateISO(d);
            const targetDate = String(dateStr).split('T')[0].split(' ')[0];
            if (targetDate === today) {
                return { label: 'Hari Ini', variant: 'success' };
            }
            if (targetDate === tomorrow) {
                return { label: 'Besok', variant: 'brand' };
            }
            return null;
        },

        onSearchUpdate(val) {
            this.searchQuery = val;
            this.currentPage = 1;
            this.fetchData();
        },

        onChipClick(chip) {
            this.activeChip = chip.value || chip.id || 'ALL';
            this.currentPage = 1;
            this.fetchData();
        },

        resetFilter() {
            this.searchQuery = '';
            this.activeChip = 'ALL';
            this.selectedKategori = '';
            this.selectedKendaraan = '';
            if (this.scheduleMode) {
                this.setTimePreset('TODAY');
            } else {
                this.setTimePreset('ALL');
            }
            this.filterValues = {
                kategori: '',
                kendaraan: '',
                dateRange: this.startDate && this.endDate ? [this.startDate, this.endDate] : [],
            };
            this.currentPage = 1;
            this.fetchData();
        },

        changePage(page) {
            this.currentPage = page;
            this.fetchData();
        },

        async viewDetail(item) {
            this.selectedItem = item;
            this.showModal = true;
            this.detailLoading = true;
            try {
                const { data } = await axios.get(`/${this.apiPrefix}/${item.Id || item.Id_Transaksi_Tamu}`);
                if (data.success) {
                    this.tamuData = data.result;
                } else {
                    ElMessage.error(data.message || 'Gagal memuat rincian tamu.');
                    this.closeModal();
                }
            } catch (e) {
                ElMessage.error('Gagal memuat rincian tamu.');
                this.closeModal();
            } finally {
                this.detailLoading = false;
            }
        },

        closeModal() {
            this.showModal = false;
            this.selectedItem = null;
            this.tamuData = null;
        },

        openPermitModal(item) {
            const target = item || this.tamuData;
            if (!target) return;
            const permitId = target.Id_Transaksi_Permit || target.No_Transaksi_Permit || target.Id_Permit || target.No_Permit || target.No_Transaksi;
            this.selectedPermitId = permitId;
            this.selectedPermitEndpoint = '/transaksipermit';
            this.showPermitModal = true;
        },

        closePermitModal() {
            this.showPermitModal = false;
            this.selectedPermitId = null;
        },

        openCancelModal(item) {
            this.itemToCancel = item;
            this.showCancelModal = true;
        },

        closeCancelModal() {
            this.showCancelModal = false;
            this.itemToCancel = null;
        },

        async confirmCancel() {
            if (!this.itemToCancel) return;
            this.cancelLoading = true;
            try {
                const itemId = this.itemToCancel.Id || this.itemToCancel.Id_Transaksi_Tamu;
                const { data } = await axios.post(`/${this.apiPrefix}/cancel/${itemId}`);

                if (data.success) {
                    ElNotification({
                        title: 'Pengajuan Dibatalkan',
                        message: data.message || 'Pengajuan tamu berhasil dibatalkan.',
                        type: 'success',
                    });

                    this.closeCancelModal();
                    if (this.showModal) {
                        this.closeModal();
                    }
                    this.fetchData();
                    this.$emit('tamu-canceled');
                    this.$emit('tamu-updated');
                } else {
                    ElNotification({
                        title: 'Gagal Membatalkan',
                        message: data.message || 'Pengajuan tidak dapat dibatalkan.',
                        type: 'error',
                    });
                }
            } catch (error) {
                ElNotification({
                    title: 'Gagal Membatalkan',
                    message: error?.response?.data?.message || 'Pengajuan tidak dapat dibatalkan.',
                    type: 'error',
                });
            } finally {
                this.cancelLoading = false;
            }
        },

        async copyToClipboard(text) {
            await this.salin(text, 'No. Transaksi');
        },

        async copyText(text, label = 'Teks') {
            await this.salin(text, label);
        },

        /**
         * Satu pintu menyalin, dan kegagalannya TIDAK BOLEH diam.
         *
         * Bentuk lamanya memanggil `.then()` pada hasil rantai opsional; di
         * konteks tak aman (http) hasilnya `undefined`, jadi yang terjadi bukan
         * "tidak tersalin" melainkan TypeError yang jatuh ke konsol. Bagi
         * pemakainya tombolnya sekadar mati tanpa sebab.
         */
        async salin(text, label = 'Teks') {
            const isi = String(text ?? '').trim();
            if (isi === '') return;

            if (await salinTeks(isi)) {
                ElMessage.success({ message: `${label} "${isi}" disalin.`, duration: 2000 });

                return;
            }

            ElMessage.warning({
                message: `${label} gagal disalin otomatis. Salin manual: ${isi}`,
                duration: 4000,
            });
        },

        getVehicleIcon(jenis) {
            const j = String(jenis || '').toLowerCase();
            if (j.includes('motor') || j.includes('roda 2') || j.includes('sepeda')) return 'bicycle';
            if (j.includes('truk') || j.includes('truck')) return 'truck';
            if (j.includes('bus')) return 'bus-front';
            return 'car-front-fill';
        },

        getRejectionReason(item) {
            if (!item) return '';
            return item.Alasan_Tolak || item.Alasan_Pembatalan || item.Catatan_Penolakan || item.Alasan || '';
        },

        getInitials(name) {
            if (!name) return 'PIC';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.slice(0, 2).toUpperCase();
        },

        // Helper formatting methods (dd MM YYYY HH:MM:SS)
        formatDate(dateStr) {
            if (!dateStr) return '-';
            try {
                const cleanStr = typeof dateStr === 'string' ? dateStr.replace(' ', 'T') : dateStr;
                const date = new Date(cleanStr);
                if (Number.isNaN(date.getTime())) return String(dateStr);
                const d = String(date.getDate()).padStart(2, '0');
                const m = NAMA_BULAN[date.getMonth()] || String(date.getMonth() + 1).padStart(2, '0');
                const y = date.getFullYear();
                return `${d} ${m} ${y}`;
            } catch (e) {
                return String(dateStr);
            }
        },

        formatTime(dateStr, includeSeconds = false) {
            if (!dateStr) return '';
            try {
                const cleanStr = typeof dateStr === 'string' ? dateStr.replace(' ', 'T') : dateStr;
                const date = new Date(cleanStr);
                if (Number.isNaN(date.getTime())) return '';
                const hh = String(date.getHours()).padStart(2, '0');
                const mm = String(date.getMinutes()).padStart(2, '0');
                const ss = String(date.getSeconds()).padStart(2, '0');
                if (includeSeconds) {
                    return `${hh}:${mm}:${ss} WIB`;
                }
                return `${hh}:${mm} WIB`;
            } catch (e) {
                return '';
            }
        },

        formatTimeShort(dateStr) {
            if (!dateStr) return '';
            try {
                const cleanStr = typeof dateStr === 'string' ? dateStr.replace(' ', 'T') : dateStr;
                const date = new Date(cleanStr);
                if (Number.isNaN(date.getTime())) return '';
                const hh = String(date.getHours()).padStart(2, '0');
                const mm = String(date.getMinutes()).padStart(2, '0');
                return `${hh}:${mm}`;
            } catch (e) {
                return '';
            }
        },

        formatJadwalDate(row) {
            if (!row) return '-';
            if (row.Waktu_Mulai_Kunjungan && row.Waktu_Selesai_Kunjungan) {
                const detail = this.jendelaKunjunganDetail(row);
                if (detail) {
                    if (detail.isSameDay) {
                        return detail.dateStr;
                    }
                    const startParts = detail.startDateStr.split(' ');
                    const endParts = detail.endDateStr.split(' ');
                    if (startParts[2] === endParts[2]) {
                        if (startParts[1] === endParts[1]) {
                            return `${startParts[0]} – ${endParts[0]} ${startParts[1]} ${startParts[2]}`;
                        }
                        return `${startParts[0]} ${startParts[1]} – ${endParts[0]} ${endParts[1]} ${startParts[2]}`;
                    }
                    return `${detail.startDateStr} – ${detail.endDateStr}`;
                }
            }
            const raw = row.Tanggal_Kunjungan || row.Waktu_Mulai_Kunjungan || row.Created_At;
            return this.formatDate(raw);
        },

        formatJadwalTime(row) {
            if (!row) return null;
            if (row.Waktu_Mulai_Kunjungan && row.Waktu_Selesai_Kunjungan) {
                const detail = this.jendelaKunjunganDetail(row);
                if (detail) {
                    const startH = detail.startTimeStr.replace(' WIB', '');
                    const endH = detail.endTimeStr.replace(' WIB', '');
                    return `${startH} – ${endH} WIB`;
                }
            }
            if (row.Waktu_Mulai_Kunjungan) {
                return this.formatTime(row.Waktu_Mulai_Kunjungan, false);
            }
            return row.Waktu_Estimasi_Kunjungan || null;
        },

        formatDateTime(dateStr, includeSeconds = false) {
            if (!dateStr) return '-';
            try {
                const cleanStr = typeof dateStr === 'string' ? dateStr.replace(' ', 'T') : dateStr;
                const date = new Date(cleanStr);
                if (Number.isNaN(date.getTime())) return String(dateStr);
                const d = String(date.getDate()).padStart(2, '0');
                const m = NAMA_BULAN[date.getMonth()] || String(date.getMonth() + 1).padStart(2, '0');
                const y = date.getFullYear();
                const hh = String(date.getHours()).padStart(2, '0');
                const mm = String(date.getMinutes()).padStart(2, '0');
                const ss = String(date.getSeconds()).padStart(2, '0');

                if (includeSeconds) {
                    return `${d} ${m} ${y} ${hh}:${mm}:${ss} WIB`;
                }
                return `${d} ${m} ${y} ${hh}:${mm} WIB`;
            } catch (e) {
                return String(dateStr);
            }
        },

        getElapsedPresenceTime(checkInStr) {
            if (!checkInStr) return 'Di area';
            const cleanStr = typeof checkInStr === 'string' ? checkInStr.replace(' ', 'T') : checkInStr;
            const checkIn = new Date(cleanStr);
            if (Number.isNaN(checkIn.getTime())) return 'Di area';
            const diffMs = Date.now() - checkIn.getTime();
            if (diffMs < 0) return 'Baru masuk';
            const diffMins = Math.floor(diffMs / (1000 * 60));
            if (diffMins < 60) return `${diffMins} mnt di area`;
            const hours = Math.floor(diffMins / 60);
            const mins = diffMins % 60;
            return `${hours}j ${mins}m di area`;
        },

        getVisitDuration(inStr, outStr) {
            if (!inStr || !outStr) return null;
            const inClean = typeof inStr === 'string' ? inStr.replace(' ', 'T') : inStr;
            const outClean = typeof outStr === 'string' ? outStr.replace(' ', 'T') : outStr;
            const inDate = new Date(inClean);
            const outDate = new Date(outClean);
            if (Number.isNaN(inDate.getTime()) || Number.isNaN(outDate.getTime())) return null;
            const diffMs = outDate.getTime() - inDate.getTime();
            if (diffMs <= 0) return '0 mnt';
            const diffMins = Math.floor(diffMs / (1000 * 60));
            if (diffMins < 60) return `${diffMins} mnt`;
            const hours = Math.floor(diffMins / 60);
            const mins = diffMins % 60;
            return `${hours}j ${mins}m`;
        },

        /**
         * Rincian jendela kedatangan & kepulangan yang terstruktur rapi untuk UI card & timeline.
         */
        jendelaKunjunganDetail(row) {
            if (!row || !row.Waktu_Mulai_Kunjungan || !row.Waktu_Selesai_Kunjungan) return null;
            try {
                const cleanStart = typeof row.Waktu_Mulai_Kunjungan === 'string' ? row.Waktu_Mulai_Kunjungan.replace(' ', 'T') : row.Waktu_Mulai_Kunjungan;
                const cleanEnd = typeof row.Waktu_Selesai_Kunjungan === 'string' ? row.Waktu_Selesai_Kunjungan.replace(' ', 'T') : row.Waktu_Selesai_Kunjungan;
                const dStart = new Date(cleanStart);
                const dEnd = new Date(cleanEnd);

                if (Number.isNaN(dStart.getTime()) || Number.isNaN(dEnd.getTime())) return null;

                const startD = String(dStart.getDate()).padStart(2, '0');
                const startM = NAMA_BULAN[dStart.getMonth()] || String(dStart.getMonth() + 1).padStart(2, '0');
                const startY = dStart.getFullYear();
                const startH = String(dStart.getHours()).padStart(2, '0');
                const startMin = String(dStart.getMinutes()).padStart(2, '0');

                const endD = String(dEnd.getDate()).padStart(2, '0');
                const endM = NAMA_BULAN[dEnd.getMonth()] || String(dEnd.getMonth() + 1).padStart(2, '0');
                const endY = dEnd.getFullYear();
                const endH = String(dEnd.getHours()).padStart(2, '0');
                const endMin = String(dEnd.getMinutes()).padStart(2, '0');

                const isSameDay = startD === endD && startM === endM && startY === endY;

                return {
                    isSameDay,
                    dateStr: `${startD} ${startM} ${startY}`,
                    timeRangeStr: `${startH}:${startMin} – ${endH}:${endMin} WIB`,
                    startDateStr: `${startD} ${startM} ${startY}`,
                    startTimeStr: `${startH}:${startMin} WIB`,
                    endDateStr: `${endD} ${endM} ${endY}`,
                    endTimeStr: `${endH}:${endMin} WIB`,
                };
            } catch {
                return null;
            }
        },

        /**
         * Jendela jam kunjungan, dengan format tanggal dan jam yang mudah dibaca tanpa detik.
         */
        labelJendela(row) {
            if (!row) return '-';

            const detail = this.jendelaKunjunganDetail(row);
            if (detail) {
                if (detail.isSameDay) {
                    return `${detail.dateStr} (${detail.timeRangeStr})`;
                }
                return `${detail.startDateStr} ${detail.startTimeStr} s/d ${detail.endDateStr} ${detail.endTimeStr}`;
            }

            if (row.Waktu_Mulai_Kunjungan) {
                return this.formatDateTime(row.Waktu_Mulai_Kunjungan, false);
            }

            return row.Waktu_Estimasi_Kunjungan || '-';
        },

        /** Kunjungan yang berakhir tanpa pernah terjadi (ditolak, ditarik, dicabut, tidak hadir). */
        isKunjunganBerakhirTanpaHadir(item) {
            return ['REJECTED', 'WITHDRAWN', 'REVOKED', 'CANCELLED'].includes(String(item?.Status_Kunjungan || '').toUpperCase());
        },

        judulBerakhir(item) {
            switch (String(item?.Status_Kunjungan || '').toUpperCase()) {
                case 'REJECTED':
                    return 'Pengajuan Kunjungan Ditolak';
                case 'WITHDRAWN':
                    return 'Pengajuan Ditarik oleh Pemohon';
                case 'REVOKED':
                    return 'Kunjungan Dicabut Verifikator';
                default:
                    return 'Tamu Tidak Hadir hingga Estimasi Keluar';
            }
        },

        /** Kolom jadwal di tabel: estimasi masuk, atau tanggal untuk data lama. */
        labelJadwalRingkas(row) {
            if (row?.Waktu_Mulai_Kunjungan) {
                return this.formatDateTime(row.Waktu_Mulai_Kunjungan, false);
            }
            return this.formatDate(row?.Tanggal_Kunjungan);
        },

        labelPermitCoklat(status) {
            switch (status) {
                case 'MENUNGGU_APPROVAL':
                    return 'Permit menunggu approval';
                case 'DITOLAK':
                    return 'Permit ditolak';
                case 'TANPA_AKSES':
                    return 'Permit belum terbit';
                case 'AKTIF':
                    return 'Permit berlaku';
                case 'EXPIRED':
                    return 'Permit habis';
                case 'DIBATALKAN':
                    return 'Permit dicabut';
                default:
                    return '';
            }
        },

        isItemPendingApproval(item) {
            if (!item) return false;
            const status = String(item.Status_Kunjungan || '').toUpperCase();
            if (['CHECKED_IN', 'CHECKED_OUT', 'KELUAR_TAK_TERCATAT', 'REJECTED', 'WITHDRAWN', 'REVOKED', 'CANCELLED'].includes(status)) {
                return false;
            }
            if (status === 'PENDING_APPROVAL' || String(item.Status_Approval || '').toUpperCase() === 'PENDING') {
                return true;
            }
            if (item.Tipe_Tamu === 'B2B' || item.Status_Permit_Coklat) {
                const permitStatus = String(item.Status_Permit_Coklat || '').toUpperCase();
                if (['MENUNGGU_APPROVAL', 'TANPA_AKSES', 'PENDING', ''].includes(permitStatus)) {
                    return true;
                }
            }
            return false;
        },

        isItemScheduled(item) {
            if (!item) return false;
            if (this.isItemPendingApproval(item)) return false;
            const status = String(item.Status_Kunjungan || '').toUpperCase();
            return status === 'SCHEDULED';
        },

        getStatusBadgeLabel(status, item = null) {
            if (item && this.isItemPendingApproval(item)) {
                return item.Tipe_Tamu === 'B2B' ? 'Menunggu Approval Permit' : 'Menunggu Approval';
            }
            switch (status) {
                case 'CHECKED_IN':
                    return 'Check-In';
                case 'CHECKED_OUT':
                    return 'Check-Out';
                case 'KELUAR_TAK_TERCATAT':
                    return 'Keluar Tak Tercatat';
                case 'PENDING_APPROVAL':
                    return 'Menunggu Approval';
                case 'SCHEDULED':
                    return 'Scheduled';
                case 'CANCELLED':
                    return 'Cancelled';
                case 'REJECTED':
                    return 'Ditolak';
                case 'WITHDRAWN':
                    return 'Ditarik Pemohon';
                case 'REVOKED':
                    return 'Dicabut';
                default:
                    return status || 'Draft';
            }
        },

        getStatusBadgeVariant(status, item = null) {
            if (item && this.isItemPendingApproval(item)) {
                return 'warning';
            }
            switch (status) {
                case 'CHECKED_IN':
                case 'ACTIVE':
                    return 'brand';
                case 'SCHEDULED':
                case 'APPROVED':
                    return 'info';
                case 'CHECKED_OUT':
                case 'WITHDRAWN':
                    return 'neutral';
                case 'PENDING_APPROVAL':
                case 'PENDING':
                case 'KELUAR_TAK_TERCATAT':
                    return 'warning';
                case 'CANCELLED':
                case 'REVOKED':
                case 'REJECTED':
                    return 'danger';
                default:
                    return 'neutral';
            }
        },

        getStatusBadgeIcon(status, item = null) {
            if (item && this.isItemPendingApproval(item)) {
                return 'hourglass-split';
            }
            switch (status) {
                case 'CHECKED_IN':
                case 'ACTIVE':
                    return 'box-arrow-in-right';
                case 'CHECKED_OUT':
                    return 'box-arrow-right';
                case 'KELUAR_TAK_TERCATAT':
                    return 'question-circle';
                case 'PENDING_APPROVAL':
                case 'PENDING':
                    return 'hourglass-split';
                case 'SCHEDULED':
                case 'APPROVED':
                    return 'calendar-check';
                case 'CANCELLED':
                    return 'clock-history';
                case 'WITHDRAWN':
                case 'REVOKED':
                case 'REJECTED':
                    return 'slash-circle';
                default:
                    return 'file-earmark-text';
            }
        },

        getCardSentiment(itemOrStatus) {
            const item = typeof itemOrStatus === 'object' && itemOrStatus !== null ? itemOrStatus : null;
            if (item && this.isItemPendingApproval(item)) {
                return 'warning';
            }
            const status = item ? item.Status_Kunjungan : itemOrStatus;
            const s = String(status || '').toUpperCase();
            if (['CHECKED_IN', 'SCHEDULED', 'APPROVED'].includes(s)) {
                return 'success';
            }
            if (['PENDING_APPROVAL', 'PENDING'].includes(s)) {
                return 'warning';
            }
            if (['CHECKED_OUT', 'WITHDRAWN'].includes(s)) {
                return 'neutral';
            }
            if (['REJECTED', 'REVOKED', 'CANCELLED'].includes(s)) {
                return 'danger';
            }
            return 'neutral';
        },

        isGuestActive(item) {
            if (!item) return false;
            if (this.isItemPendingApproval(item)) return false;
            const s = String(item?.Status_Kunjungan || '').toUpperCase();
            return s === 'CHECKED_IN';
        },

        isApprovalRequired(item) {
            return String(item?.Needs_Approval || 'T').toUpperCase() === 'Y';
        },

        isApprovalStepExpired(step) {
            if (!step || step.Flag_Approval !== null || !step.Expired_At) return false;
            return new Date(step.Expired_At).getTime() < Date.now() || Number(step.Is_Expired) === 1;
        },

        hasApprovedStep(item) {
            return (item?.Approvals || []).some((step) => step.Flag_Approval === 'Y');
        },

        isApprovalExpired(item) {
            return (
                item?.Status_Approval === 'EXPIRED' ||
                (item?.Approvals || []).some((step) => this.isApprovalStepExpired(step))
            );
        },

        canCancelTamu(item) {
            if (this.readOnly) return false;
            return (
                item?.Status_Kunjungan === 'PENDING_APPROVAL' &&
                this.isApprovalRequired(item) &&
                !this.hasApprovedStep(item) &&
                !this.isApprovalExpired(item)
            );
        },

        getApprovalSummaryText(status) {
            switch (status) {
                case 'APPROVED':
                    return 'Disetujui';
                case 'REJECTED':
                    return 'Ditolak';
                case 'PENDING':
                    return 'Menunggu Approval';
                case 'EXPIRED':
                    return 'Expired';
                case 'NO_FLOW':
                    return 'Flow Tidak Tersedia';
                default:
                    return status || 'Langsung Aktif';
            }
        },

        bukaFotoKendaraan(data) {
            if (!data || !data.Foto_Kendaraan_Url) return;

            const plat = (data.No_Plat_Kendaraan || 'Kendaraan Tamu').replace(/\s+/g, '');
            const jenis = data.Jenis_Kendaraan || 'Kendaraan';
            const url = data.Foto_Kendaraan_Url;
            const cleanPath = String(url).split('?')[0].split('#')[0];
            const rawExt = cleanPath.includes('.') ? cleanPath.split('.').pop().toLowerCase() : 'jpg';
            const validExts = ['jpg', 'jpeg', 'png', 'webp', 'jfif', 'pjpeg', 'svg', 'bmp', 'avif'];
            const ext = validExts.includes(rawExt) ? rawExt : 'jpg';

            this.viewerFiles = [{
                url: url,
                name: `foto-kendaraan-${plat}.${ext}`,
                title: `Foto Kendaraan - ${data.No_Plat_Kendaraan || plat} (${jenis})`,
                type: `image/${ext === 'jpg' ? 'jpeg' : ext}`,
                ext: ext,
                allowDownload: true,
                allowPrint: true,
            }];
            this.viewerInitialIndex = 0;
            this.showFileViewer = true;
        },
    },
};
</script>

<style scoped>
.tamu-foto-kendaraan-wrap {
    position: relative;
    border-radius: var(--hcis-radius-md, 0.5rem);
    overflow: hidden;
    cursor: pointer;
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.08));
    background: var(--hcis-slate-900, #0f172a);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.tamu-foto-kendaraan-wrap:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px -3px rgba(15, 23, 42, 0.15);
}

.tamu-foto-kendaraan {
    width: 100%;
    max-height: 240px;
    object-fit: cover;
    display: block;
    border-radius: var(--hcis-radius-md, 0.5rem);
    transition: transform 0.3s ease, filter 0.3s ease;
}

.tamu-foto-kendaraan-wrap:hover .tamu-foto-kendaraan {
    transform: scale(1.02);
    filter: brightness(0.92);
}

.tamu-foto-overlay {
    position: absolute;
    bottom: 0.65rem;
    right: 0.65rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 600;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: background 0.2s ease;
}

.tamu-foto-kendaraan-wrap:hover .tamu-foto-overlay {
    background: rgba(79, 70, 229, 0.9);
}

.tamu-history-workspace {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    position: relative;
    box-sizing: border-box;
}

.tamu-history-workspace :deep(.hcis-container) {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    box-sizing: border-box;
}

.tamu-history-workspace :deep(.hcis-data-table__wrapper) {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.tamu-history-workspace :deep(.hcis-table-container) {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: auto;
    box-sizing: border-box;
}

.tamu-history-workspace :deep(.hcis-container__header) {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 0.85rem 1.15rem !important;
    gap: 0.75rem !important;
}

.tamu-history-workspace :deep(.hcis-container__header-main) {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 0.65rem !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
    overflow: hidden !important;
}

.tamu-history-workspace :deep(.hcis-container__header-text) {
    display: flex !important;
    align-items: center !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
    overflow: hidden !important;
}

.tamu-history-workspace :deep(.hcis-container__header-title-row) {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    min-width: 0 !important;
    width: 100% !important;
}

.tamu-history-workspace :deep(.hcis-container__title) {
    margin: 0 !important;
    font-size: 1rem !important;
    font-weight: 800 !important;
    color: var(--hcis-slate-900, var(--hcis-slate-900)) !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    min-width: 0 !important;
}

.tamu-history-workspace :deep(.hcis-container__header-actions) {
    display: flex !important;
    align-items: center !important;
    flex-shrink: 0 !important;
    flex-wrap: nowrap !important;
    margin: 0 !important;
}

/* Cell Layouts - Compact, balanced, neat */
.tamu-cell-guest {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    min-width: 0;
}

.tamu-cell-text-block {
    min-width: 0;
    line-height: 1.35;
}

.tamu-cell-schedule {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.35;
}

.tamu-cell-presence {
    min-width: 0;
    line-height: 1.35;
}

/* Mini party pill badge (Default Brand) */
.tamu-mini-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.05rem 0.35rem;
}

/* ID type badge in modal specs (Default Brand) */
.tamu-id-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.12rem 0.45rem;
    font-size: 0.6875rem;
    font-weight: 800;
    color: var(--hcis-brand-800, var(--hcis-indigo-800));
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    border-radius: var(--hcis-radius-xs, 4px);
    letter-spacing: 0.04em;
    line-height: 1.2;
}

/* Purpose subtle chip */
.tamu-purpose-chip {
    display: inline-block;
    max-width: 100%;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    border-radius: var(--hcis-radius-sm, 4px);
    padding: 0.1rem 0.4rem;
    font-size: 0.72rem;
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

/* Monospace Plate Capsule */
.tamu-permit-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
    color: #795548;
    background: rgba(121, 85, 72, 0.1);
    border: 1px solid rgba(121, 85, 72, 0.22);
}

.tamu-permit-badge[data-status='EXPIRED'] {
    color: #64748b;
    background: rgba(100, 116, 139, 0.1);
    border-color: rgba(100, 116, 139, 0.22);
}

.tamu-permit-badge[data-status='DIBATALKAN'],
.tamu-permit-badge[data-status='DITOLAK'] {
    color: #b91c1c;
    background: rgba(185, 28, 28, 0.09);
    border-color: rgba(185, 28, 28, 0.2);
}

/* Menunggu keputusan verifikator: sengaja sewarna peringatan, bukan sewarna
   permit hidup. Tamunya terjadwal, izin gerbangnya belum ada. */
.tamu-permit-badge[data-status='MENUNGGU_APPROVAL'] {
    color: var(--hcis-amber-700);
    background: rgb(var(--hcis-amber-700-rgb) / 0.1);
    border-color: rgb(var(--hcis-amber-700-rgb) / 0.22);
}

.tamu-permit-badge[data-status='TANPA_AKSES'] {
    color: var(--hcis-slate-500);
    background: rgb(var(--hcis-slate-500-rgb) / 0.1);
    border-color: rgb(var(--hcis-slate-500-rgb) / 0.22);
}

.tamu-plate-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%);
    color: var(--hcis-slate-50);
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 800;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    padding: 0.2rem 0.55rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1.5px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    text-transform: uppercase;
    white-space: nowrap;
}

.tamu-plate-badge--lg {
    font-size: 0.9375rem;
    padding: 0.35rem 0.8rem;
    border-radius: var(--hcis-radius-lg, 10px);
}

/* Live Presence Pulse Dot */
.tamu-pulse-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    background-color: var(--hcis-success-500, var(--hcis-emerald-500));
    border-radius: 50%;
    animation: tamu-presence-pulse 1.5s infinite;
}

@keyframes tamu-presence-pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

/* Grid Cards */
.tamu-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--hcis-space-4, 1rem);
}

.tamu-grid-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}

.tamu-grid-card:hover {
    transform: translateY(-3px);
}

.tamu-grid-card.is-active-card,
.tamu-grid-card.hcis-card--sentiment-success {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(16, 185, 129, 0.22), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(5, 150, 105, 0.16), transparent 55%),
        linear-gradient(225deg, rgba(236, 253, 245, 0.96) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(209, 250, 229, 0.75) 100%) !important;
    border: 1.5px solid rgba(16, 185, 129, 0.45) !important;
    border-left: 4.5px solid var(--hcis-success-500, var(--hcis-emerald-500)) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
}

.tamu-grid-card.is-active-card:hover,
.tamu-grid-card.hcis-card--sentiment-success:hover {
    border-color: rgba(16, 185, 129, 0.65) !important;
    box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
}

.tamu-card-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.tamu-card-spec-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem 0.85rem;
    padding: 0.75rem;
    background: rgba(248, 250, 252, 0.7);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
}

.tamu-spec-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.tamu-spec-item__label {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    margin-bottom: 0.15rem;
}

.tamu-spec-item__val {
    font-size: 0.8125rem;
    line-height: 1.3;
}

.tamu-card-presence-box {
    background: rgba(236, 253, 245, 0.75);
    border: 1px solid rgba(167, 243, 208, 0.75);
}

.tamu-card-actions-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

:deep(.tamu-card-btn) {
    font-weight: 600;
}

/* =========================================================================
   DETAIL MODAL DUAL PANE HERO CARD & SPEC TILES (DEFAULT HCIS BRAND)
   ========================================================================= */

.tamu-hero-card {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04) 0%, var(--hcis-white) 45%, var(--hcis-slate-50) 100%);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-2xl, 16px);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.03);
    position: relative;
    overflow: hidden;
    color: var(--hcis-slate-800);
    padding: 1.125rem;
    margin-bottom: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.tamu-hero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--hcis-brand-500, #6366f1), var(--hcis-brand-400, #818cf8));
}

.tamu-hero-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.tamu-hero-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.03em;
    background-color: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-700, #4338ca);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.tamu-hero-category-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 6px var(--hcis-brand-400, #818cf8);
    flex-shrink: 0;
}

.tamu-hero-category-text {
    font-weight: 750;
    white-space: nowrap;
}

.tamu-hero-profile {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
}

.tamu-hero-avatar-box {
    flex-shrink: 0;
    border-radius: 50%;
    border: 2px solid var(--hcis-white);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.tamu-hero-profile-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.tamu-hero-name {
    color: var(--hcis-slate-900);
    font-weight: 750;
    font-size: 1.0625rem;
    line-height: 1.3;
    margin: 0;
    word-break: break-word;
}

.tamu-hero-profile-meta {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
}

.tamu-hero-instansi,
.tamu-hero-party-count {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Middle Specs Container */
.tamu-hero-details-container {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.tamu-hero-trx-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.75rem 0.875rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.tamu-hero-trx-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.tamu-hero-card-kicker {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
    font-size: 0.625rem;
    line-height: 1.2;
    white-space: nowrap;
}

.tamu-hero-trx-number {
    font-size: 1.125rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-900);
    line-height: 1.2;
}

.tamu-hero-subpod-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.625rem;
}

.tamu-hero-subpod {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.625rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.tamu-hero-subpod-val {
    font-size: 0.8125rem;
    line-height: 1.25;
}

.tamu-hero-meta-bar {
    border-top: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-500);
    padding-top: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.625rem;
    flex-wrap: wrap;
}

.tamu-hero-date {
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.tamu-copy-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    border-radius: var(--hcis-radius-md, 8px);
    cursor: pointer;
    transition: all 0.15s ease;
}

.tamu-copy-btn:hover {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    border-color: var(--hcis-brand-300, var(--hcis-indigo-300));
}

/* PIC Card */
.tamu-pic-card {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.tamu-pic-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.tamu-pic-avatar {
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

.tamu-pic-identity {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex-grow: 1;
}

.tamu-pic-name {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
}

.tamu-pic-nik-tag {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
}

.tamu-pic-role {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

/* Visitor Strip & Specs Grid in Modal */
.tamu-visitor-strip {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.75rem 0.875rem;
}

.tamu-visitor-avatar {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tamu-visitor-name {
    font-size: 0.9375rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.25;
}

.tamu-visitor-instansi {
    font-size: 0.75rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.15rem;
}

.tamu-specs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem 0.875rem;
}

.tamu-spec-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.75rem 0.875rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    transition: all 0.15s ease;
}

.tamu-spec-tile:hover {
    background: var(--hcis-white, #ffffff);
    border-color: var(--hcis-brand-200, #c7d2fe);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.tamu-spec-tile__header {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}

.tamu-spec-tile__icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    display: inline-flex;
    flex-shrink: 0;
}

.tamu-spec-tile__label {
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.tamu-spec-tile__val {
    font-size: 0.8125rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.35;
}

.tamu-jendela-compact {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.tamu-jendela-timeline {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    position: relative;
    padding-left: 0.1rem;
    min-width: 0;
}

.tamu-jendela-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    line-height: 1.25;
    min-width: 0;
}

.tamu-jendela-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.tamu-jendela-dot.is-in {
    background-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 2px var(--hcis-brand-100, #e0e7ff);
}

.tamu-jendela-dot.is-out {
    background-color: var(--hcis-rose-500, #f43f5e);
    box-shadow: 0 0 0 2px var(--hcis-rose-100, #ffe4e6);
}

.tamu-jendela-connector {
    width: 1.5px;
    height: 6px;
    background-color: var(--hcis-slate-300, #cbd5e1);
    margin-left: 2.25px;
}

.tamu-jendela-text {
    font-weight: 700;
    color: var(--hcis-slate-900, #0f172a);
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    min-width: 0;
}

/* Reason Callout */
.tamu-reason-callout {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(241, 245, 249, 0.85) 100%);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-left: 3.5px solid var(--hcis-brand-500, var(--hcis-indigo-500));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 1rem 1.15rem;
}

.tamu-reason-callout__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.4rem;
}

.tamu-reason-callout__icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    display: inline-flex;
}

.tamu-reason-callout__title {
    font-size: 0.75rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.tamu-reason-callout__tag {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
}

.tamu-reason-callout__text {
    font-size: 0.875rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.55;
    font-style: italic;
    word-break: break-word;
}

.tamu-confirm-box {
    background-color: var(--hcis-surface-soft, var(--hcis-slate-50));
}

/* =========================================================================
   RIGHT PANE SPECIFICATIONS, PERMIT BANNER, TRANSPORT & PARTY LIST
   ========================================================================= */

.tamu-permit-highlight-banner {
    background: linear-gradient(135deg, rgba(121, 85, 72, 0.08) 0%, rgba(255, 255, 255, 0.95) 100%);
    border: 1px solid rgba(121, 85, 72, 0.25);
    border-left: 4px solid #795548;
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.75rem 1rem;
}

.tamu-permit-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(121, 85, 72, 0.12);
    color: #795548;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tamu-tile-copy-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    background: var(--hcis-white, #ffffff);
    color: var(--hcis-slate-400, #94a3b8);
    border-radius: var(--hcis-radius-sm, 4px);
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.tamu-tile-copy-btn:hover {
    color: var(--hcis-brand-600, #4f46e5);
    background: var(--hcis-brand-50, #eef2ff);
    border-color: var(--hcis-brand-300, #a5b4fc);
}

.tamu-note-callout {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.75rem 0.875rem;
}

.tamu-transport-card {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.9) 0%, rgba(241, 245, 249, 0.7) 100%);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.875rem 1rem;
}

.tamu-transport-icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-600, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tamu-transport-empty {
    padding: 1rem;
    text-align: center;
    border: 1px dashed var(--hcis-slate-300, #cbd5e1);
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-slate-50, #f8fafc);
}

.tamu-party-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tamu-party-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    transition: background 0.15s ease, border-color 0.15s ease;
}

.tamu-party-row:hover {
    background: var(--hcis-slate-50, #f8fafc);
    border-color: var(--hcis-slate-300, #cbd5e1);
}

.tamu-party-index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 800;
    color: var(--hcis-slate-500, #64748b);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-xs, 4px);
    padding: 0.15rem 0.35rem;
    font-family: var(--hcis-font-mono, monospace);
    flex-shrink: 0;
}

/* =========================================================================
   RESPONSIVE MEDIA QUERIES (TABLET & MOBILE)
   ========================================================================= */
@media (max-width: 1024px) {
    .tamu-cards-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 0.85rem;
    }
}

@media (max-width: 640px) {
    .tamu-history-workspace {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    .tamu-history-workspace :deep(.hcis-container__header) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.45rem !important;
        padding: 0.65rem 0.75rem !important;
    }

    .tamu-history-workspace :deep(.hcis-container__header-main) {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.45rem !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
        overflow: hidden !important;
    }

    .tamu-history-workspace :deep(.hcis-container__header-icon) {
        width: 1.85rem !important;
        height: 1.85rem !important;
        font-size: 0.875rem !important;
        border-radius: 6px !important;
        flex-shrink: 0 !important;
    }

    .tamu-history-workspace :deep(.hcis-container__header-text) {
        display: flex !important;
        flex-direction: column !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
        overflow: hidden !important;
    }

    .tamu-history-workspace :deep(.hcis-container__header-title-row) {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        min-width: 0 !important;
        width: 100% !important;
    }

    .tamu-history-workspace :deep(.hcis-container__title) {
        font-size: 0.8125rem !important;
        font-weight: 800 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
    }

    .tamu-history-workspace :deep(.hcis-container__subtitle) {
        display: none !important;
    }

    .tamu-history-workspace :deep(.hcis-container__header-actions) {
        display: flex !important;
        align-items: center !important;
        width: auto !important;
        flex-shrink: 0 !important;
        flex-wrap: nowrap !important;
        margin: 0 !important;
    }

    .tamu-date-filter-box {
        display: none !important;
    }

    .tamu-reload-btn :deep(.hcis-btn__text) {
        display: none !important;
    }

    .tamu-reload-btn {
        width: 1.85rem !important;
        height: 1.85rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 6px !important;
        flex-shrink: 0 !important;
    }

    .tamu-reload-btn :deep(.hcis-btn__icon) {
        margin: 0 !important;
    }

    .tamu-history-workspace :deep(.hcis-filter-bar__main) {
        flex-wrap: nowrap !important;
        gap: 0.45rem !important;
    }

    .tamu-history-workspace :deep(.hcis-filter-bar__search) {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .tamu-history-workspace :deep(.hcis-filter-bar__actions) {
        flex-shrink: 0 !important;
        flex-wrap: nowrap !important;
        gap: 0.35rem !important;
    }

    .tamu-history-workspace :deep(.hcis-filter-reset-btn) {
        width: 2.25rem !important;
        height: 2.25rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        flex-shrink: 0 !important;
    }

    .tamu-history-workspace :deep(.hcis-filter-reset-btn span) {
        display: none !important;
    }

    .tamu-cards-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem;
        width: 100%;
    }

    .tamu-card-header-bar {
        gap: 0.35rem;
        margin-bottom: 0.5rem !important;
    }

    .tamu-card-spec-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem 0.6rem;
        padding: 0.5rem 0.6rem;
        border-radius: 8px;
    }

    .tamu-spec-item__label {
        font-size: 0.5625rem;
        font-weight: 750;
    }

    .tamu-spec-item__val {
        font-size: 0.71875rem;
        font-weight: 700;
    }

    .tamu-card-presence-box {
        padding: 0.4rem 0.55rem;
        border-radius: 6px;
    }

    .tamu-card-actions-bar {
        gap: 0.35rem;
    }

    .tamu-card-actions-bar :deep(.hcis-btn) {
        height: 2.15rem !important;
        font-size: 0.75rem !important;
        font-weight: 750 !important;
    }

    .tamu-plate-badge {
        padding: 0.2rem 0.45rem;
        font-size: 0.75rem;
        border-radius: 5px;
    }

    .tamu-plate-badge--lg {
        padding: 0.25rem 0.55rem;
        font-size: 0.8125rem;
    }

    .tamu-cell-actions :deep(.hcis-btn) {
        height: 1.85rem !important;
        padding: 0 0.45rem !important;
        font-size: 0.6875rem !important;
    }

    /* Detail Modal Mobile Optimizations */
    .tamu-hero-card {
        padding: 0.75rem 0.85rem !important;
        border-radius: 10px !important;
        margin-bottom: 0.65rem !important;
    }

    .tamu-hero-header {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        margin-bottom: 0.65rem !important;
    }

    .tamu-hero-brand-group {
        gap: 0.55rem !important;
    }

    .tamu-hero-icon-box {
        width: 2.25rem !important;
        height: 2.25rem !important;
        border-radius: 8px !important;
        font-size: 0.95rem !important;
    }

    .tamu-hero-category-pill {
        font-size: 0.59375rem !important;
        padding: 0.12rem 0.45rem !important;
    }

    .tamu-hero-name {
        font-size: 0.8125rem !important;
    }

    .tamu-hero-trx-card {
        padding: 0.5rem 0.65rem !important;
        border-radius: 8px !important;
    }

    .tamu-hero-trx-number {
        font-size: 1.15rem !important;
        letter-spacing: 0.05em !important;
    }

    .tamu-hero-subpod-grid {
        gap: 0.4rem !important;
    }

    .tamu-hero-subpod {
        padding: 0.4rem 0.55rem !important;
        border-radius: 6px !important;
    }

    .tamu-hero-subpod-val {
        font-size: 0.75rem !important;
    }

    .tamu-hero-meta-bar {
        padding-top: 0.45rem !important;
        margin-top: 0.45rem !important;
        font-size: 0.65625rem !important;
        gap: 0.4rem !important;
    }

    .tamu-pic-card {
        gap: 0.5rem !important;
    }

    .tamu-pic-header {
        gap: 0.5rem !important;
    }

    .tamu-pic-avatar {
        width: 2.15rem !important;
        height: 2.15rem !important;
        font-size: 0.78125rem !important;
        border-radius: 8px !important;
    }

    .tamu-pic-name {
        font-size: 0.8125rem !important;
    }

    .tamu-pic-nik-tag {
        font-size: 0.625rem !important;
        padding: 0.08rem 0.35rem !important;
    }

    .tamu-visitor-strip {
        padding: 0.55rem 0.65rem !important;
        border-radius: 8px !important;
    }

    .tamu-visitor-avatar {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 6px !important;
    }

    .tamu-visitor-name {
        font-size: 0.8125rem !important;
    }

    .tamu-visitor-instansi {
        font-size: 0.6875rem !important;
    }

    .tamu-specs-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 0.45rem !important;
    }

    .tamu-spec-tile {
        padding: 0.45rem 0.55rem !important;
        border-radius: 6px !important;
    }

    .tamu-spec-tile__label {
        font-size: 0.5625rem !important;
    }

    .tamu-spec-tile__val {
        font-size: 0.75rem !important;
    }

    .tamu-jendela-row {
        font-size: 0.6875rem !important;
        gap: 0.35rem !important;
    }

    .tamu-jendela-connector {
        height: 4px !important;
    }

    .tamu-reason-callout {
        padding: 0.55rem 0.7rem !important;
        border-radius: 8px !important;
    }

    .tamu-reason-callout__title {
        font-size: 0.6875rem !important;
    }

    .tamu-reason-callout__text {
        font-size: 0.75rem !important;
    }

    .tamu-permit-highlight-banner {
        padding: 0.55rem 0.75rem !important;
        border-radius: 8px !important;
    }

    .tamu-permit-icon-box {
        width: 1.85rem !important;
        height: 1.85rem !important;
        border-radius: 6px !important;
    }

    .tamu-transport-card {
        padding: 0.65rem 0.75rem !important;
        border-radius: 8px !important;
    }

    .tamu-transport-icon-box {
        width: 2rem !important;
        height: 2rem !important;
        border-radius: 6px !important;
    }

    .tamu-party-row {
        padding: 0.45rem 0.6rem !important;
        border-radius: 6px !important;
    }
}

@media (max-width: 380px) {
    .tamu-history-workspace :deep(.hcis-container__header) {
        padding: 0.55rem 0.65rem !important;
    }
    .tamu-history-workspace :deep(.hcis-container__title) {
        font-size: 0.8125rem !important;
    }
    .tamu-history-workspace :deep(.hcis-container__header-icon) {
        width: 1.75rem !important;
        height: 1.75rem !important;
        border-radius: 5px !important;
    }
    .tamu-history-workspace :deep(.hcis-filter-bar__main) {
        gap: 0.35rem !important;
    }
    .tamu-history-workspace :deep(.hcis-filter-reset-btn) {
        width: 2rem !important;
        height: 2rem !important;
    }
}

/* ══ Schedule Mode Specific Styles ══ */
.tamu-category-filter {
    display: inline-flex;
    align-items: center;
}

.tamu-category-select {
    padding: 0.28rem 0.65rem;
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.12));
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 600;
    cursor: pointer;
    outline: none;
    transition: all 0.15s ease;
}

.tamu-category-select:focus {
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.tamu-horizon-group {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-surface-sunken, #f1f5f9);
    padding: 2px;
    border-radius: var(--hcis-radius-md, 0.5rem);
    gap: 2px;
}

.tamu-horizon-btn {
    padding: 0.25rem 0.6rem;
    border: none;
    border-radius: calc(var(--hcis-radius-md, 0.5rem) - 2px);
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.tamu-horizon-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
}

.tamu-horizon-btn.is-active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    font-weight: 750;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.tamu-date-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.tamu-date-pill.is-success {
    background: var(--hcis-success-50, #ecfdf5);
    border: 1px solid var(--hcis-success-200, #a7f3d0);
    color: var(--hcis-success-700, #047857);
}

.tamu-date-pill.is-brand {
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-700, #4338ca);
}

.tamu-kategori-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-surface-sunken, #f1f5f9);
    color: var(--hcis-slate-600, #475569);
    font-size: 0.625rem;
    font-weight: 700;
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.06));
}

/* ══ Schedule Stepper ══ */
.tamu-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.35rem;
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.1));
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-surface-sunken, #f8fafc);
}

.tamu-stepper-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border: none;
    border-radius: var(--hcis-radius-sm, 0.25rem);
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
}

.tamu-stepper-btn:hover {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.tamu-stepper-label {
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    padding: 0 0.25rem;
    white-space: nowrap;
}

/* ══ Schedule Day Section & Headers ══ */
.tamu-schedule-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.tamu-day-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tamu-day-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.875rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-surface-raised, #ffffff);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.08));
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.tamu-day-header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.tamu-day-calendar-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: var(--hcis-radius-sm, 0.25rem);
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
}

.tamu-day-title {
    margin: 0;
    font-size: var(--hcis-font-size-sm, 0.875rem);
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.tamu-day-today-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: var(--hcis-success-50, #ecfdf5);
    border: 1px solid var(--hcis-success-200, #a7f3d0);
    color: var(--hcis-success-700, #047857);
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.02em;
}

.tamu-day-tomorrow-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-700, #4338ca);
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.02em;
}

.tamu-day-summary-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 0.25rem);
    background: var(--hcis-surface-sunken, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.06));
}
</style>
