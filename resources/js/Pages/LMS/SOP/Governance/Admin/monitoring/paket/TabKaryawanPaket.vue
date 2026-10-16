<template>
    <div class="sop-stack tkp-container">
        <!-- ══ 1. CONTEXT BANNER PERINGATAN KETERLAMBATAN (JIKA ADA OVERDUE) ══ -->
        <div v-if="jumlahTerlambat > 0" class="sop-fade-up mb-3">
            <HcisContextBanner
                variant="glass"
                sentiment="danger"
                title="Terdapat Karyawan Melewati Batas Tenggat Waktu"
                :subtitle="`Ada ${jumlahTerlambat} karyawan penerima paket dokumen ini yang berstatus terlambat. Segera kirimkan pengingat untuk mempercepat penyelesaian.`"
                kicker="AUDIT KEPATUHAN"
                icon="exclamation-octagon-fill"
                scope-variant="danger"
                :scope-label="`${jumlahTerlambat} Terlambat`"
                scope-radar
            >
                <template #actions>
                    <HcisButton
                        variant="danger"
                        size="xs"
                        leading-icon="bell-fill"
                        :label="`Ingatkan Semua Karyawan Terlambat (${jumlahTerlambat})`"
                        :loading="mengingatkan"
                        @click="ingatkanSemuaTerlambat"
                    />
                </template>
            </HcisContextBanner>
        </div>

        <!-- ══ 2. INTERACTIVE FILTER BAR & VIEW TOGGLE (HCIS DESIGN SYSTEM) ══ -->
        <div class="sop-fade-up mb-3">
            <HcisFilterBar
                :search="filterState.search"
                :filters="filterBarConfig"
                :filter-values="filterState"
                :quick-chips="quickChipsConfig"
                :active-chip-value="filterState.status"
                :results-count="filteredAndSortedKaryawan.length"
                :show-view-toggle="true"
                :view-mode="viewMode"
                search-placeholder="Cari nama karyawan, NIK, unit, jabatan, lokasi..."
                variant="glass"
                sentiment="brand"
                collapsible
                allow-export
                @update:search="onSearchChange"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilters"
                @export="exportCsv"
                @update:view-mode="viewMode = $event"
                @view-mode-change="viewMode = $event"
            />
        </div>

        <!-- ══ 3. MAIN CONTENT CONTAINER (TABLE / GRID VIEW) ══ -->
        <div class="sop-fade-up">
            <!-- ── A. TAMPILAN TABEL HCIS ── -->
            <HcisCard
                v-if="viewMode === 'table'"
                variant="glass"
                sentiment="brand"
                size="sm"
                class="tkp-main-card"
            >
                <HcisTable
                    :columns="tableColumns"
                    :rows="paginatedKaryawan"
                    row-key="Kode_Karyawan"
                    :loading="loading"
                    hoverable
                    interactive
                    selectable
                    :selected-keys="selectedKaryawanKeys"
                    variant="glass"
                    sentiment="brand"
                    :empty-title="karyawanList.length === 0 ? 'Belum Ada Karyawan Ditugaskan' : 'Tidak Ada Karyawan yang Cocok'"
                    :empty-description="karyawanList.length === 0 ? 'Paket Dokumen ini belum didistribusikan ke karyawan manapun.' : 'Silakan sesuaikan kata kunci pencarian atau filter yang aktif.'"
                    @update:selected-keys="selectedKaryawanKeys = $event"
                    @row-click="onRowClick"
                >
                    <!-- Bulk Actions in Table Header -->
                    <template #bulk-actions="{ selectedKeys: keys, clear }">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                label="Pilih Halaman Ini"
                                @click="pilihSemuaHalaman"
                            />
                            <HcisButton
                                v-if="adaYangBelumSelesaiDiHalaman"
                                variant="secondary"
                                size="xs"
                                label="Pilih Yang Belum Selesai"
                                @click="pilihBelumSelesaiHalaman"
                            />
                            <HcisButton
                                variant="warning"
                                size="xs"
                                leading-icon="bell-fill"
                                :label="`Kirim Pengingat (${selectedKaryawanKeys.length})`"
                                :loading="mengingatkan"
                                @click="ingatkanTerpilih"
                            />
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="x-lg"
                                label="Batal"
                                @click="clear"
                            />
                        </div>
                    </template>

                    <!-- Kolom Profil Karyawan -->
                    <template #cell-karyawan="{ row }">
                        <div class="d-flex align-items-center gap-3 py-1.5 min-w-0">
                            <div class="position-relative flex-shrink-0">
                                <HcisAvatar
                                    :name="row.Nama || row.Kode_Karyawan"
                                    size="md"
                                    shape="squircle"
                                    variant="brand"
                                />
                                <span
                                    v-if="row.Is_Terlambat"
                                    class="tkp-pulse-dot danger"
                                    title="Melewati batas tenggat waktu penugasan"
                                ></span>
                            </div>
                            <div class="min-w-0 flex-grow-1 ps-1">
                                <div
                                    class="fw-bold text-dark text-truncate tkp-nama-link"
                                    :title="row.Nama || row.Kode_Karyawan"
                                    @click.stop="openDrawer(row)"
                                >
                                    {{ row.Nama || row.Kode_Karyawan }}
                                </div>
                                <div class="d-flex align-items-center gap-1.5 small text-muted font-monospace mt-0.5">
                                    <span>{{ row.Kode_Karyawan }}</span>
                                    <span v-if="row.No_Induk_Karyawan" class="text-slate-400">·</span>
                                    <span v-if="row.No_Induk_Karyawan">{{ row.No_Induk_Karyawan }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Kolom Unit Kerja & Lokasi -->
                    <template #cell-unit="{ row }">
                        <div class="min-w-0 d-flex flex-column align-items-start gap-1">
                            <div class="fw-semibold small text-dark text-truncate" :title="row.Departemen || '—'">
                                {{ row.Departemen || '—' }}
                            </div>
                            <div v-if="row.Divisi" class="small text-muted text-truncate" :title="row.Divisi">
                                {{ row.Divisi }}
                            </div>
                            <div v-if="row.Lokasi" class="mt-0.5">
                                <HcisBadge
                                    :label="row.Lokasi"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                    icon="geo-alt"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Kolom Jabatan & Level -->
                    <template #cell-jabatan="{ row }">
                        <div class="min-w-0 d-flex flex-column align-items-start gap-1">
                            <div class="small fw-medium text-dark text-truncate" :title="row.Jabatan || '—'">
                                {{ row.Jabatan || '—' }}
                            </div>
                            <div v-if="row.Level" class="mt-0.5">
                                <HcisBadge
                                    :label="`Level ${row.Level}`"
                                    variant="brand"
                                    appearance="subtle"
                                    size="2xs"
                                    icon="award"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Kolom Progres Kepatuhan -->
                    <template #cell-progres="{ row }">
                        <div class="d-flex flex-column gap-1 min-w-0" style="max-width: 170px;">
                            <div class="d-flex align-items-center justify-content-between font-scale-xs">
                                <span class="text-muted">
                                    <strong class="text-dark">{{ row.Item_Selesai || 0 }}</strong> / {{ row.Total_Item || 0 }} Item
                                </span>
                                <span class="fw-bold font-monospace" :class="persenTextClass(row.Persen)">
                                    {{ row.Persen || 0 }}%
                                </span>
                            </div>
                            <HcisProgress
                                :value="row.Persen || 0"
                                :sentiment="progressSentiment(row.Persen)"
                                size="xs"
                                animated
                            />
                            <div v-if="row.Item_Dibuka && row.Item_Dibuka > row.Item_Selesai" class="d-flex align-items-center gap-1 font-scale-2xs text-muted text-truncate mt-0.5">
                                <HcisIcon name="eye" size="2xs" class="text-slate-400" />
                                <span>{{ row.Item_Dibuka }} item dibuka</span>
                            </div>
                        </div>
                    </template>

                    <!-- Kolom Status Penugasan -->
                    <template #cell-status="{ row }">
                        <div class="d-flex flex-column align-items-start gap-1">
                            <StatusPill
                                :variant="statusPillVariant(row.Status_Penugasan)"
                                :text="statusLabel(row.Status_Penugasan)"
                            />
                            <div v-if="row.Is_Terlambat">
                                <HcisBadge
                                    label="Terlambat"
                                    variant="danger"
                                    size="xs"
                                    icon="exclamation-triangle-fill"
                                    pulse
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Kolom Tenggat / Selesai -->
                    <template #cell-tenggat="{ row }">
                        <div v-if="row.Tanggal_Selesai" class="d-flex flex-column align-items-start gap-1">
                            <HcisBadge
                                :label="row.Tanggal_Selesai"
                                variant="success"
                                appearance="subtle"
                                size="xs"
                                icon="check-circle-fill"
                            />
                            <span class="font-scale-2xs text-muted">Tuntas Diselesaikan</span>
                        </div>
                        <div v-else-if="row.Tanggal_Deadline" class="d-flex flex-column align-items-start gap-1">
                            <HcisBadge
                                :label="row.Tanggal_Deadline"
                                :variant="row.Is_Terlambat ? 'danger' : 'neutral'"
                                :appearance="row.Is_Terlambat ? 'solid' : 'subtle'"
                                size="xs"
                                :icon="row.Is_Terlambat ? 'alarm-fill' : 'calendar3'"
                                :pulse="row.Is_Terlambat"
                            />
                            <span class="font-scale-2xs" :class="row.Is_Terlambat ? 'text-danger fw-semibold' : 'text-muted'">
                                {{ row.Is_Terlambat ? 'Lewat batas akhir' : 'Batas penyelesaian' }}
                            </span>
                        </div>
                        <div v-else class="d-flex align-items-start">
                            <HcisBadge
                                label="Tanpa Tenggat"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                                icon="infinity"
                            />
                        </div>
                    </template>

                    <!-- Kolom Aksi Cepat -->
                    <template #cell-aksi="{ row }">
                        <div class="tkp-action-btn-group" @click.stop>
                            <!-- Tombol Intip Audit Drawer -->
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                leading-icon="clock-history"
                                label="Audit"
                                title="Intip Jejak Audit & Riwayat Baca"
                                @click="openDrawer(row)"
                            />

                            <!-- Tombol Detail Halaman dokumen Karyawan -->
                            <HcisButton
                                :href="urlMonitoringKaryawanPaket(row)"
                                variant="primary"
                                size="xs"
                                icon-only
                                leading-icon="box-arrow-up-right"
                                title="Buka Halaman Monitoring Dokumen Karyawan Ini"
                                aria-label="Detail Dokumen"
                            />

                            <!-- Tombol Kirim Pengingat Individual -->
                            <HcisButton
                                v-if="(row.Status_Penugasan || '').toUpperCase() !== 'SELESAI' || row.Is_Terlambat"
                                variant="warning"
                                size="xs"
                                icon-only
                                leading-icon="bell-fill"
                                title="Kirim Notifikasi Pengingat"
                                aria-label="Ingatkan Karyawan"
                                class="tkp-btn-bell"
                                @click="ingatkanSatuKaryawan(row)"
                            />
                        </div>
                    </template>
                </HcisTable>

                <!-- Integrated Card Footer Pagination -->
                <template v-if="filteredAndSortedKaryawan.length > 0" #footer>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100 py-1">
                        <span class="text-muted small">
                            Menampilkan <strong>{{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredAndSortedKaryawan.length) }}</strong> dari <strong>{{ filteredAndSortedKaryawan.length }}</strong> karyawan
                            <template v-if="filteredAndSortedKaryawan.length !== karyawanList.length">
                                (difilter dari total {{ karyawanList.length }})
                            </template>
                        </span>
                        <HcisPagination
                            :total="filteredAndSortedKaryawan.length"
                            :page-size="pageSize"
                            :current-page="currentPage"
                            :page-sizes="[10, 15, 25, 50, 100]"
                            layout="sizes, prev, pager, next, jumper"
                            background
                            size="sm"
                            @page-change="onPageChange"
                            @update:current-page="onPageChange"
                            @size-change="onPageSizeChange"
                            @update:page-size="onPageSizeChange"
                        />
                    </div>
                </template>
            </HcisCard>

            <!-- ── B. TAMPILAN GRID KARTU (CARD VIEW) ── -->
            <div v-else class="tkp-grid-container">
                <div v-if="paginatedKaryawan.length === 0" class="my-5">
                    <HcisEmptyState
                        :title="karyawanList.length === 0 ? 'Belum Ada Karyawan Ditugaskan' : 'Tidak Ada Karyawan yang Cocok'"
                        :description="karyawanList.length === 0 ? 'Paket Dokumen ini belum didistribusikan ke karyawan manapun.' : 'Silakan sesuaikan kata kunci pencarian atau filter yang aktif.'"
                        icon="search"
                        variant="dashed"
                        sentiment="brand"
                    >
                        <template #actions>
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                label="Reset Filter"
                                @click="resetFilters"
                            />
                        </template>
                    </HcisEmptyState>
                </div>

                <div v-else class="row g-3">
                    <div
                        v-for="row in paginatedKaryawan"
                        :key="row.Kode_Karyawan"
                        class="col-12 col-md-6 col-xl-4"
                    >
                        <div
                            class="tkp-grid-card"
                            :class="{
                                'is-selected': isKaryawanSelected(row.Kode_Karyawan),
                                'is-late': row.Is_Terlambat,
                                'is-done': (row.Status_Penugasan || '').toUpperCase() === 'SELESAI',
                            }"
                        >
                            <!-- Card Header -->
                            <div class="tkp-card-header">
                                <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                    <div class="position-relative flex-shrink-0">
                                        <HcisAvatar
                                            :name="row.Nama || row.Kode_Karyawan"
                                            size="md"
                                            shape="squircle"
                                            variant="brand"
                                        />
                                        <span
                                            v-if="row.Is_Terlambat"
                                            class="tkp-pulse-dot danger"
                                            title="Melewati batas tenggat waktu penugasan"
                                        ></span>
                                    </div>
                                    <div class="min-w-0 flex-grow-1 ps-1">
                                        <div
                                            class="fw-bold text-dark text-truncate tkp-nama-link"
                                            :title="row.Nama || row.Kode_Karyawan"
                                            @click="openDrawer(row)"
                                        >
                                            {{ row.Nama || row.Kode_Karyawan }}
                                        </div>
                                        <div class="small text-muted font-monospace text-truncate mt-0.5">
                                            {{ row.No_Induk_Karyawan || row.Kode_Karyawan }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-shrink-0 ms-2">
                                    <HcisCheckbox
                                        :model-value="isKaryawanSelected(row.Kode_Karyawan)"
                                        size="sm"
                                        @update:model-value="toggleSelectKaryawan(row.Kode_Karyawan)"
                                    />
                                </div>
                            </div>

                            <!-- Card Body Meta -->
                            <div class="tkp-card-body">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap mb-2.5">
                                    <HcisBadge
                                        v-if="row.Departemen"
                                        :label="row.Departemen"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                        icon="building"
                                        class="text-truncate max-w-100"
                                    />
                                    <HcisBadge
                                        v-if="row.Jabatan"
                                        :label="row.Jabatan"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                        icon="person-badge"
                                        class="text-truncate max-w-100"
                                    />
                                    <HcisBadge
                                        v-if="row.Lokasi"
                                        :label="row.Lokasi"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                        icon="geo-alt"
                                        class="text-truncate"
                                    />
                                </div>

                                <!-- Progress Box -->
                                <div class="tkp-card-progres-box p-2.5 rounded-3 bg-slate-50 border border-slate-100 mb-2.5">
                                    <div class="d-flex align-items-center justify-content-between small mb-1.5">
                                        <span class="text-secondary fw-semibold font-scale-xs">
                                            {{ row.Item_Selesai || 0 }} / {{ row.Total_Item || 0 }} Prosedur Selesai
                                        </span>
                                        <span class="fw-bold font-scale-xs font-monospace" :class="persenTextClass(row.Persen)">
                                            {{ row.Persen || 0 }}%
                                        </span>
                                    </div>
                                    <HcisProgress
                                        :value="row.Persen || 0"
                                        :sentiment="progressSentiment(row.Persen)"
                                        size="sm"
                                        animated
                                    />
                                </div>

                                <!-- Status and Deadline Row -->
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 small">
                                    <div>
                                        <StatusPill
                                            :variant="statusPillVariant(row.Status_Penugasan)"
                                            :text="statusLabel(row.Status_Penugasan)"
                                        />
                                    </div>
                                    <div class="text-end">
                                        <template v-if="row.Tanggal_Selesai">
                                            <HcisBadge
                                                :label="row.Tanggal_Selesai"
                                                variant="success"
                                                appearance="subtle"
                                                size="xs"
                                                icon="check2-circle"
                                            />
                                        </template>
                                        <template v-else-if="row.Tanggal_Deadline">
                                            <HcisBadge
                                                :label="row.Tanggal_Deadline"
                                                :variant="row.Is_Terlambat ? 'danger' : 'neutral'"
                                                :appearance="row.Is_Terlambat ? 'solid' : 'subtle'"
                                                size="xs"
                                                :icon="row.Is_Terlambat ? 'alarm-fill' : 'calendar3'"
                                                :pulse="row.Is_Terlambat"
                                            />
                                        </template>
                                        <template v-else>
                                            <HcisBadge
                                                label="Tanpa Tenggat"
                                                variant="neutral"
                                                appearance="subtle"
                                                size="xs"
                                                icon="infinity"
                                            />
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="tkp-card-footer">
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="clock-history"
                                    label="Jejak Audit"
                                    @click="openDrawer(row)"
                                />
                                <div class="d-flex align-items-center gap-2">
                                    <HcisButton
                                        v-if="(row.Status_Penugasan || '').toUpperCase() !== 'SELESAI' || row.Is_Terlambat"
                                        variant="warning"
                                        size="xs"
                                        icon-only
                                        leading-icon="bell-fill"
                                        title="Kirim Notifikasi Pengingat"
                                        @click="ingatkanSatuKaryawan(row)"
                                    />
                                    <HcisButton
                                        :href="urlMonitoringKaryawanPaket(row)"
                                        variant="primary"
                                        size="xs"
                                        leading-icon="box-arrow-up-right"
                                        label="Detail Dokumen"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid View Footer Pagination -->
                <div v-if="filteredAndSortedKaryawan.length > 0" class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-muted small">
                        Menampilkan <strong>{{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredAndSortedKaryawan.length) }}</strong> dari <strong>{{ filteredAndSortedKaryawan.length }}</strong> karyawan
                    </span>
                    <HcisPagination
                        :total="filteredAndSortedKaryawan.length"
                        :page-size="pageSize"
                        :current-page="currentPage"
                        :page-sizes="[12, 24, 48, 96]"
                        layout="sizes, prev, pager, next, jumper"
                        background
                        size="sm"
                        @page-change="onPageChange"
                        @update:current-page="onPageChange"
                        @size-change="onPageSizeChange"
                        @update:page-size="onPageSizeChange"
                    />
                </div>
            </div>
        </div>

        <!-- ══ 5. FLOATING BULK SELECTION ACTION DOCK ══ -->
        <transition name="sop-fade-up">
            <div v-if="selectedKaryawanKeys.length > 0" class="tkp-dock-floating">
                <div class="tkp-dock-inner">
                    <div class="tkp-dock-info">
                        <span class="tkp-dock-badge">{{ selectedKaryawanKeys.length }}</span>
                        <span class="tkp-dock-text">Karyawan Terpilih</span>
                    </div>
                    <div class="tkp-dock-actions">
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            label="Pilih Halaman Ini"
                            @click="pilihSemuaHalaman"
                        />
                        <HcisButton
                            variant="warning"
                            size="sm"
                            leading-icon="bell-fill"
                            :label="`Kirim Pengingat (${selectedKaryawanKeys.length})`"
                            :loading="mengingatkan"
                            @click="ingatkanTerpilih"
                        />
                        <button
                            type="button"
                            class="tkp-dock-btn-close"
                            title="Batal pilih semua"
                            @click="selectedKaryawanKeys = []"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ══ MODAL KONFIRMASI PENGINGAT (HCIS DIALOG RESMI) ══ -->
        <HcisDialog
            v-model="dialogPengingat.show"
            :title="dialogPengingat.daftarKaryawan.length === 1 ? 'Kirim Pengingat Dokumen' : dialogPengingat.judul || 'Kirim Notifikasi Pengingat Dokumen'"
            kicker="KONFIRMASI PENGINGAT"
            icon="bell-fill"
            :sentiment="dialogPengingat.sentiment || 'warning'"
            variant="glass"
            :size="dialogPengingat.daftarKaryawan.length > 1 ? 'md' : 'sm'"
            header-divider
        >
            <!-- Skenario 1 Karyawan Tunggal -->
            <div v-if="dialogPengingat.daftarKaryawan.length === 1" class="d-flex flex-column gap-3 py-1">
                <HcisCard
                    size="xs"
                    variant="flat"
                    :sentiment="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'danger' : 'brand'"
                >
                    <div class="d-flex align-items-center gap-3">
                        <HcisAvatar
                            :name="dialogPengingat.daftarKaryawan[0].Nama || dialogPengingat.daftarKaryawan[0].Kode_Karyawan"
                            size="md"
                            shape="squircle"
                            :variant="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'danger' : 'brand'"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-0.5">
                                <h6 class="mb-0 fw-bold font-scale-xs text-dark text-truncate">
                                    {{ dialogPengingat.daftarKaryawan[0].Nama || dialogPengingat.daftarKaryawan[0].Kode_Karyawan }}
                                </h6>
                                <HcisBadge
                                    :label="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'Terlambat' : 'Belum Selesai'"
                                    :variant="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'danger' : 'warning'"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                            <div class="font-scale-2xs text-muted font-monospace mb-1">
                                NIK: {{ dialogPengingat.daftarKaryawan[0].No_Induk_Karyawan || dialogPengingat.daftarKaryawan[0].Kode_Karyawan }}
                            </div>
                            <div class="font-scale-2xs text-muted">
                                {{ dialogPengingat.daftarKaryawan[0].Departemen || dialogPengingat.daftarKaryawan[0].Divisi || 'Semua Unit' }}
                                <span v-if="dialogPengingat.daftarKaryawan[0].Jabatan">· {{ dialogPengingat.daftarKaryawan[0].Jabatan }}</span>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <p class="font-scale-xs text-muted mb-0">
                    Kirimkan notifikasi pengingat penugasan paket dokumen <strong>"{{ paket?.Nama_Paket || 'Paket Dokumen' }}"</strong> kepada karyawan di atas?
                </p>
            </div>

            <!-- Skenario Banyak Karyawan (Massal) -->
            <div v-else class="d-flex flex-column gap-3 py-1">
                <p class="font-scale-xs text-muted mb-0">
                    Kirimkan notifikasi pengingat penugasan paket dokumen <strong>"{{ paket?.Nama_Paket || 'Paket Dokumen' }}"</strong> kepada <strong>{{ dialogPengingat.daftarKaryawan.length }}</strong> karyawan yang belum selesai?
                </p>

                <HcisCard
                    size="xs"
                    variant="default"
                    sentiment="neutral"
                    :title="`Daftar Penerima (${dialogPengingat.daftarKaryawan.length} Karyawan)`"
                    :badge="dialogPengingat.daftarKaryawan.filter(k => k.Is_Terlambat).length > 0 ? `${dialogPengingat.daftarKaryawan.filter(k => k.Is_Terlambat).length} Terlambat` : null"
                    badge-variant="danger"
                >
                    <div class="d-flex flex-column gap-1.5 overflow-y-auto" style="max-height: 200px;">
                        <div
                            v-for="k in dialogPengingat.daftarKaryawan"
                            :key="k.Kode_Karyawan"
                            class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-slate-50 border border-slate-100"
                        >
                            <div class="d-flex align-items-center gap-2.5 min-w-0">
                                <HcisAvatar
                                    :name="k.Nama || k.Kode_Karyawan"
                                    size="xs"
                                    shape="squircle"
                                    :variant="k.Is_Terlambat ? 'danger' : 'brand'"
                                />
                                <div class="min-w-0">
                                    <div class="font-scale-xs fw-bold text-dark text-truncate">{{ k.Nama || k.Kode_Karyawan }}</div>
                                    <div class="font-scale-2xs text-muted">{{ k.Departemen || k.Divisi || k.No_Induk_Karyawan }}</div>
                                </div>
                            </div>
                            <HcisBadge
                                :label="k.Is_Terlambat ? 'Terlambat' : 'Belum Selesai'"
                                :variant="k.Is_Terlambat ? 'danger' : 'warning'"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>
                    </div>
                </HcisCard>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        :disabled="mengingatkan"
                        @click="dialogPengingat.show = false"
                    />
                    <HcisButton
                        :variant="dialogPengingat.sentiment === 'danger' ? 'danger' : 'warning'"
                        size="sm"
                        leading-icon="bell-fill"
                        label="Kirim Notifikasi"
                        :loading="mengingatkan"
                        @click="eksekusiKirimPengingat"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisButton,
    HcisBadge,
    HcisAvatar,
    HcisCheckbox,
    HcisProgress,
    HcisFilterBar,
    HcisPagination,
    HcisCard,
    HcisTable,
    HcisIcon,
    HcisContextBanner,
    HcisEmptyState,
    HcisDialog,
} from '@/components/ui';
import StatusPill from '@/Pages/LMS/SOP/Shared/components/ui/StatusPill.vue';
import { STATUS_PAKET } from '@/Pages/LMS/SOP/Shared/support/constants.js';

const API = '/api/v1/sop/admin/monitoring';

export default {
    name: 'TabKaryawanPaket',
    components: {
        HcisButton,
        HcisBadge,
        HcisAvatar,
        HcisCheckbox,
        HcisProgress,
        HcisFilterBar,
        HcisPagination,
        HcisCard,
        HcisTable,
        HcisIcon,
        HcisContextBanner,
        HcisEmptyState,
        HcisDialog,
        StatusPill,
    },
    props: {
        karyawanList: {
            type: Array,
            default: () => [],
        },
        paket: {
            type: Object,
            default: () => ({}),
        },
        kpi: {
            type: Object,
            default: () => ({}),
        },
        idPaket: {
            type: String,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['open-drawer', 'refresh'],
    data() {
        return {
            viewMode: 'table',
            mengingatkan: false,
            currentPage: 1,
            pageSize: 15,
            selectedKaryawanKeys: [],
            filterState: {
                search: '',
                status: 'all',
                dept: [],
                divisi: [],
                jabatan: [],
                lokasi: [],
                tenggat: 'all',
                sort_by: 'default',
            },
            tableColumns: [
                { key: 'karyawan', label: 'Karyawan', width: '320px', sortable: true },
                { key: 'unit', label: 'Unit Kerja & Lokasi', width: '220px', sortable: true },
                { key: 'jabatan', label: 'Jabatan & Level', width: '180px', sortable: true },
                { key: 'progres', label: 'Progres Kepatuhan', width: '190px', sortable: true },
                { key: 'status', label: 'Status', width: '140px', sortable: true },
                { key: 'tenggat', label: 'Tenggat / Selesai', width: '170px', sortable: true },
                { key: 'aksi', label: 'Aksi Cepat', width: '175px', align: 'right' },
            ],
            dialogPengingat: {
                show: false,
                daftarKaryawan: [],
                judul: '',
                pesan: '',
                sentiment: 'warning',
            },
        };
    },
    computed: {
        jumlahTerlambat() {
            return (this.karyawanList || []).filter((k) => k.Is_Terlambat).length;
        },
        kpiSummary() {
            const list = this.karyawanList || [];
            const total = list.length;
            const selesai = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'SELESAI' || (k.Persen || 0) >= 100).length;
            const progress = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'PROGRESS' && !k.Is_Terlambat).length;
            const belum = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'BELUM' && !k.Is_Terlambat).length;
            const terlambat = list.filter((k) => k.Is_Terlambat).length;
            const avgPersen = total > 0 ? Math.round(list.reduce((acc, k) => acc + (Number(k.Persen) || 0), 0) / total) : 0;
            const pctSelesai = total > 0 ? Math.round((selesai / total) * 100) : 0;

            return {
                total,
                selesai,
                progress,
                belum,
                terlambat,
                avgPersen,
                pctSelesai,
            };
        },
        kpiStats() {
            const s = this.kpiSummary;
            return [
                {
                    label: 'Target Karyawan',
                    value: s.total,
                    suffix: 'Penerima',
                    sentiment: 'brand',
                    icon: 'people-fill',
                    pattern: 'grid',
                    caption: 'Total karyawan terdaftar penugasan paket',
                },
                {
                    label: 'Kepatuhan Selesai',
                    value: s.selesai,
                    suffix: `(${s.pctSelesai}%)`,
                    sentiment: 'success',
                    icon: 'check-circle-fill',
                    pattern: 'dots',
                    caption: 'Karyawan telah tuntas 100% membaca & konfirmasi',
                },
                {
                    label: 'Sedang Berjalan',
                    value: s.progress,
                    suffix: 'Karyawan',
                    sentiment: 'warning',
                    icon: 'hourglass-split',
                    pattern: 'stripes',
                    caption: 'Masih dalam proses membaca seluruh item',
                },
                {
                    label: 'Terlambat (Overdue)',
                    value: s.terlambat,
                    suffix: 'Karyawan',
                    sentiment: s.terlambat > 0 ? 'danger' : 'neutral',
                    icon: 'exclamation-octagon-fill',
                    pattern: 'crosshair',
                    caption: 'Telah melewati batas tanggal tenggat penugasan',
                },
            ];
        },
        quickChipsConfig() {
            const s = this.kpiSummary;
            return [
                { label: 'Semua Karyawan', value: 'all', badge: s.total, icon: 'people-fill' },
                { label: 'Tuntas (100%)', value: 'selesai', badge: s.selesai, icon: 'check-circle-fill', color: 'success' },
                { label: 'Sedang Berjalan', value: 'progress', badge: s.progress, icon: 'hourglass-split', color: 'warning' },
                { label: 'Belum Mulai', value: 'belum', badge: s.belum, icon: 'dash-circle', color: 'neutral' },
                {
                    label: 'Terlambat',
                    value: 'terlambat',
                    badge: s.terlambat,
                    icon: 'exclamation-triangle-fill',
                    color: 'danger',
                    ping: s.terlambat > 0,
                },
            ];
        },
        uniqueDeptOptions() {
            const list = this.karyawanList || [];
            const depts = Array.from(new Set(list.map((k) => k.Departemen).filter(Boolean))).sort();
            return depts.map((d) => ({
                label: d,
                value: d,
            }));
        },
        uniqueDivisiOptions() {
            const list = this.karyawanList || [];
            const divs = Array.from(new Set(list.map((k) => k.Divisi).filter(Boolean))).sort();
            return divs.map((d) => ({
                label: d,
                value: d,
            }));
        },
        uniqueJabatanOptions() {
            const list = this.karyawanList || [];
            const jabs = Array.from(new Set(list.map((k) => k.Jabatan).filter(Boolean))).sort();
            return jabs.map((j) => ({
                label: j,
                value: j,
            }));
        },
        uniqueLokasiOptions() {
            const list = this.karyawanList || [];
            const loks = Array.from(new Set(list.map((k) => k.Lokasi).filter(Boolean))).sort();
            return loks.map((l) => ({
                label: l,
                value: l,
            }));
        },
        filterBarConfig() {
            return [
                {
                    key: 'dept',
                    label: 'Sub Divisi / Departemen',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Sub Divisi',
                    icon: 'building',
                    col: 3,
                    options: this.uniqueDeptOptions,
                },
                {
                    key: 'divisi',
                    label: 'Divisi',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Divisi',
                    icon: 'diagram-3',
                    col: 3,
                    options: this.uniqueDivisiOptions,
                },
                {
                    key: 'jabatan',
                    label: 'Jabatan',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Jabatan',
                    icon: 'person-badge',
                    col: 3,
                    options: this.uniqueJabatanOptions,
                },
                {
                    key: 'lokasi',
                    label: 'Lokasi Kerja',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Lokasi',
                    icon: 'geo-alt',
                    col: 3,
                    options: this.uniqueLokasiOptions,
                },
                {
                    key: 'tenggat',
                    label: 'Filter Tenggat',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Status Tenggat',
                    icon: 'calendar3',
                    col: 3,
                    options: [
                        { label: 'Semua Status Tenggat', value: 'all' },
                        { label: 'Terlambat (Overdue)', value: 'terlambat' },
                        { label: 'Tuntas Tepat Waktu', value: 'tepat_waktu' },
                        { label: 'Memiliki Deadline', value: 'ada_tenggat' },
                        { label: 'Tanpa Batas Tenggat', value: 'tanpa_tenggat' },
                    ],
                },
                {
                    key: 'sort_by',
                    label: 'Urutan Tampilan',
                    type: 'select',
                    placeholder: 'Urutan Default',
                    icon: 'sort-down',
                    col: 3,
                    options: [
                        { label: 'Urutan Default', value: 'default' },
                        { label: 'Nama (A – Z)', value: 'nama_asc' },
                        { label: 'Nama (Z – A)', value: 'nama_desc' },
                        { label: 'Progres Kepatuhan Tertinggi', value: 'progres_desc' },
                        { label: 'Progres Kepatuhan Terendah', value: 'progres_asc' },
                        { label: 'Prioritaskan Terlambat', value: 'terlambat_first' },
                    ],
                },
            ];
        },
        filteredAndSortedKaryawan() {
            let list = [...(this.karyawanList || [])];

            // 1. Search query filter
            if (this.filterState.search) {
                const q = this.filterState.search.toLowerCase().trim();
                list = list.filter(
                    (k) =>
                        (k.Nama || '').toLowerCase().includes(q) ||
                        (k.Kode_Karyawan || '').toLowerCase().includes(q) ||
                        (k.No_Induk_Karyawan || '').toLowerCase().includes(q) ||
                        (k.Departemen || '').toLowerCase().includes(q) ||
                        (k.Divisi || '').toLowerCase().includes(q) ||
                        (k.Jabatan || '').toLowerCase().includes(q) ||
                        (k.Lokasi || '').toLowerCase().includes(q),
                );
            }

            // 2. Status quick chip filter
            if (this.filterState.status && this.filterState.status !== 'all') {
                const s = this.filterState.status;
                if (s === 'selesai') {
                    list = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'SELESAI' || (k.Persen || 0) >= 100);
                } else if (s === 'progress') {
                    list = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'PROGRESS' && !k.Is_Terlambat);
                } else if (s === 'belum') {
                    list = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'BELUM' && !k.Is_Terlambat);
                } else if (s === 'terlambat') {
                    list = list.filter((k) => k.Is_Terlambat);
                }
            }

            // 3. Departemen multi filter
            if (Array.isArray(this.filterState.dept) && this.filterState.dept.length > 0) {
                list = list.filter((k) => this.filterState.dept.includes(k.Departemen));
            }

            // 4. Divisi multi filter
            if (Array.isArray(this.filterState.divisi) && this.filterState.divisi.length > 0) {
                list = list.filter((k) => this.filterState.divisi.includes(k.Divisi));
            }

            // 5. Jabatan multi filter
            if (Array.isArray(this.filterState.jabatan) && this.filterState.jabatan.length > 0) {
                list = list.filter((k) => this.filterState.jabatan.includes(k.Jabatan));
            }

            // 6. Lokasi multi filter
            if (Array.isArray(this.filterState.lokasi) && this.filterState.lokasi.length > 0) {
                list = list.filter((k) => this.filterState.lokasi.includes(k.Lokasi));
            }

            // 7. Tenggat single filter
            if (this.filterState.tenggat && this.filterState.tenggat !== 'all') {
                const t = this.filterState.tenggat;
                if (t === 'terlambat') {
                    list = list.filter((k) => k.Is_Terlambat);
                } else if (t === 'tepat_waktu') {
                    list = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'SELESAI' && !k.Is_Terlambat);
                } else if (t === 'ada_tenggat') {
                    list = list.filter((k) => !!k.Tanggal_Deadline);
                } else if (t === 'tanpa_tenggat') {
                    list = list.filter((k) => !k.Tanggal_Deadline || k.Tipe_Tenggat === 'SAMPAI_DICABUT');
                }
            }

            // 8. Sorting
            const sortBy = this.filterState.sort_by;
            if (sortBy === 'nama_asc') {
                list.sort((a, b) => (a.Nama || '').localeCompare(b.Nama || ''));
            } else if (sortBy === 'nama_desc') {
                list.sort((a, b) => (b.Nama || '').localeCompare(a.Nama || ''));
            } else if (sortBy === 'progres_desc') {
                list.sort((a, b) => (b.Persen || 0) - (a.Persen || 0));
            } else if (sortBy === 'progres_asc') {
                list.sort((a, b) => (a.Persen || 0) - (b.Persen || 0));
            } else if (sortBy === 'terlambat_first') {
                list.sort((a, b) => (b.Is_Terlambat ? 1 : 0) - (a.Is_Terlambat ? 1 : 0));
            }

            return list;
        },
        paginatedKaryawan() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredAndSortedKaryawan.slice(start, start + this.pageSize);
        },
        adaYangBelumSelesaiDiHalaman() {
            return this.paginatedKaryawan.some(
                (k) => (k.Status_Penugasan || '').toUpperCase() !== 'SELESAI' || k.Is_Terlambat,
            );
        },
    },
    watch: {
        'filterState.search'() {
            this.currentPage = 1;
        },
        'filterState.status'() {
            this.currentPage = 1;
        },
        'filterState.dept'() {
            this.currentPage = 1;
        },
        'filterState.divisi'() {
            this.currentPage = 1;
        },
        'filterState.jabatan'() {
            this.currentPage = 1;
        },
        'filterState.lokasi'() {
            this.currentPage = 1;
        },
        'filterState.tenggat'() {
            this.currentPage = 1;
        },
        'filterState.sort_by'() {
            this.currentPage = 1;
        },
    },
    methods: {
        onSearchChange(val) {
            this.filterState.search = val;
        },
        onChipClick(chip) {
            this.filterState.status = chip.value || chip.id || 'all';
        },
        onFilterChange(payload) {
            if (payload && payload.key) {
                this.filterState[payload.key] = payload.value;
            } else if (payload && payload.values) {
                this.filterState = { ...this.filterState, ...payload.values };
            } else if (typeof payload === 'object') {
                this.filterState = { ...this.filterState, ...payload };
            }
            this.currentPage = 1;
        },
        resetFilters() {
            this.filterState = {
                search: '',
                status: 'all',
                dept: [],
                divisi: [],
                jabatan: [],
                lokasi: [],
                tenggat: 'all',
                sort_by: 'default',
            };
            this.currentPage = 1;
        },
        onPageChange(p) {
            this.currentPage = p;
        },
        onPageSizeChange(size) {
            this.pageSize = size;
            this.currentPage = 1;
        },
        onRowClick(row) {
            this.openDrawer(row);
        },
        openDrawer(row) {
            this.$emit('open-drawer', row);
        },
        isKaryawanSelected(kode) {
            return this.selectedKaryawanKeys.includes(kode);
        },
        toggleSelectKaryawan(kode) {
            const idx = this.selectedKaryawanKeys.indexOf(kode);
            if (idx > -1) {
                this.selectedKaryawanKeys.splice(idx, 1);
            } else {
                this.selectedKaryawanKeys.push(kode);
            }
        },
        pilihSemuaHalaman() {
            const pageKeys = this.paginatedKaryawan.map((k) => k.Kode_Karyawan);
            const combined = Array.from(new Set([...this.selectedKaryawanKeys, ...pageKeys]));
            this.selectedKaryawanKeys = combined;
        },
        pilihBelumSelesaiHalaman() {
            const pageKeys = this.paginatedKaryawan
                .filter((k) => (k.Status_Penugasan || '').toUpperCase() !== 'SELESAI' || k.Is_Terlambat)
                .map((k) => k.Kode_Karyawan);
            const combined = Array.from(new Set([...this.selectedKaryawanKeys, ...pageKeys]));
            this.selectedKaryawanKeys = combined;
        },
        urlMonitoringKaryawanPaket(k) {
            const token = k?.Token || k?.Kode_Karyawan || '';
            return `/sop/admin/monitoring/karyawan/${token}/paket/${this.idPaket}`;
        },
        statusLabel(s) {
            return (STATUS_PAKET[s] || {}).label || s || 'Belum Selesai';
        },
        statusPillVariant(s) {
            const raw = String(s || '').toUpperCase();
            if (raw === 'SELESAI' || raw === 'LULUS') return 'ok';
            if (raw === 'PROGRESS' || raw === 'PROGRES' || raw === 'PARTIAL') return 'warn';
            if (raw === 'TERLAMBAT') return 'bad';
            return 'idle';
        },
        persenTextClass(p) {
            const val = Number(p) || 0;
            return val >= 80 ? 'text-emerald-600' : val >= 40 ? 'text-indigo-600' : 'text-rose-600';
        },
        progressSentiment(persen) {
            const p = Number(persen) || 0;
            if (p >= 100) return 'emerald';
            if (p >= 60) return 'brand';
            if (p > 0) return 'amber';
            return 'danger';
        },
        meterColorFillClass(persen) {
            const p = Number(persen) || 0;
            if (p >= 100) return 'is-emerald';
            if (p >= 80) return 'is-indigo';
            if (p >= 40) return 'is-amber';
            return 'is-danger';
        },
        ingatkanSatuKaryawan(k) {
            this.dialogPengingat = {
                show: true,
                daftarKaryawan: [k],
                judul: `Ingatkan ${k.Nama || k.Kode_Karyawan}`,
                pesan: `Notifikasi pengingat penugasan untuk paket dokumen "${this.paket?.Nama_Paket || 'Paket Dokumen'}" akan dikirimkan kepada ${k.Nama || k.Kode_Karyawan}.`,
                sentiment: k.Is_Terlambat ? 'danger' : 'warning',
            };
        },
        ingatkanTerpilih() {
            const list = (this.karyawanList || []).filter((k) => this.selectedKaryawanKeys.includes(k.Kode_Karyawan));
            if (!list.length) return;

            this.dialogPengingat = {
                show: true,
                daftarKaryawan: list,
                judul: `Kirim Pengingat ke ${list.length} Karyawan Terpilih`,
                pesan: `Notifikasi penugasan untuk paket dokumen "${this.paket?.Nama_Paket || 'Paket Dokumen'}" akan dikirimkan ke ${list.length} karyawan terpilih.`,
                sentiment: 'warning',
            };
        },
        ingatkanSemuaTerlambat() {
            const listTerlambat = (this.karyawanList || []).filter((k) => k.Is_Terlambat);
            if (!listTerlambat.length) {
                ElMessage.info('Tidak ada karyawan yang terlambat untuk paket dokumen ini.');
                return;
            }

            this.dialogPengingat = {
                show: true,
                daftarKaryawan: listTerlambat,
                judul: `Ingatkan Seluruh ${listTerlambat.length} Karyawan Terlambat`,
                pesan: `Pemberitahuan mendesak mengenai batas akhir penugasan dokumen "${this.paket?.Nama_Paket || 'Paket Dokumen'}" yang terlewati akan dikirimkan kepada ${listTerlambat.length} karyawan.`,
                sentiment: 'danger',
            };
        },
        async eksekusiKirimPengingat() {
            const kodes = this.dialogPengingat.daftarKaryawan.map((k) => k.Kode_Karyawan);
            if (!kodes.length) return;

            this.mengingatkan = true;
            try {
                const r = await axios.post(`${API}/ingatkan`, {
                    kodes,
                    id_paket: this.idPaket,
                    pesan: `Mohon segera menyelesaikan pembacaan paket dokumen "${this.paket?.Nama_Paket || 'Paket Dokumen'}".`,
                });
                ElMessage.success(r.data?.message || `Pengingat berhasil dikirim ke ${kodes.length} karyawan.`);
                this.selectedKaryawanKeys = [];
                this.dialogPengingat.show = false;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengirimkan pengingat.');
            } finally {
                this.mengingatkan = false;
            }
        },
        exportCsv() {
            const list = this.filteredAndSortedKaryawan;
            if (!list.length) {
                ElMessage.warning('Tidak ada data karyawan untuk diekspor.');
                return;
            }

            const header = [
                'No',
                'Kode Karyawan',
                'NIK',
                'Nama Karyawan',
                'Departemen',
                'Divisi',
                'Jabatan',
                'Level',
                'Lokasi',
                'Status Penugasan',
                'Item Selesai',
                'Total Item',
                'Persentase (%)',
                'Terlambat',
                'Tanggal Deadline',
                'Tanggal Selesai',
            ];

            const escapeCsv = (val) => {
                if (val === null || val === undefined) return '""';
                const s = String(val).replace(/"/g, '""');
                return `"${s}"`;
            };

            const rows = list.map((k, idx) => [
                idx + 1,
                escapeCsv(k.Kode_Karyawan),
                escapeCsv(k.No_Induk_Karyawan),
                escapeCsv(k.Nama),
                escapeCsv(k.Departemen),
                escapeCsv(k.Divisi),
                escapeCsv(k.Jabatan),
                escapeCsv(k.Level),
                escapeCsv(k.Lokasi),
                escapeCsv(k.Status_Penugasan || k.Status_Paket),
                k.Item_Selesai || 0,
                k.Total_Item || 0,
                k.Persen || 0,
                k.Is_Terlambat ? 'Ya' : 'Tidak',
                escapeCsv(k.Tanggal_Deadline),
                escapeCsv(k.Tanggal_Selesai),
            ]);

            const csvContent = '\uFEFF' + [header.join(','), ...rows.map((r) => r.join(','))].join('\r\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const safeName = (this.paket?.Nama_Paket || 'Paket_SOP').replace(/[^a-zA-Z0-9_-]/g, '_');
            a.href = url;
            a.download = `Monitoring_Penerima_${safeName}_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            ElMessage.success('Data penerima paket berhasil diekspor ke format CSV.');
        },
    },
};
</script>

<style scoped>
.tkp-container {
    display: flex;
    flex-direction: column;
}

.tkp-stat-group {
    border-radius: 1rem;
}

.tkp-main-card {
    border-radius: 1rem;
    overflow: hidden;
}

.tkp-nama-link {
    cursor: pointer;
    transition: color 0.15s ease;
}

.tkp-nama-link:hover {
    color: var(--bs-primary, var(--hcis-indigo-600)) !important;
    text-decoration: underline;
}

/* Pulse Dot Keterlambatan */
.tkp-pulse-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 9px;
    height: 9px;
    border-radius: 9999px;
    border: 2px solid var(--hcis-white);
}

.tkp-pulse-dot.danger {
    background-color: var(--hcis-red-500);
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.4);
    animation: tkp-pulse 1.8s infinite cubic-bezier(0.4, 0, 0.6, 1);
}

@keyframes tkp-pulse {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.3);
        opacity: 0.7;
    }
}

/* Linear Meter Bar */
.tkp-meter-bar-track {
    width: 100%;
    height: 6px;
    background-color: var(--hcis-slate-100);
    border-radius: 9999px;
    overflow: hidden;
}

.tkp-meter-bar-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.4s ease, background-color 0.4s ease;
}

.tkp-meter-bar-fill.is-emerald {
    background-color: var(--hcis-emerald-500);
}

.tkp-meter-bar-fill.is-indigo {
    background-color: var(--hcis-indigo-500);
}

.tkp-meter-bar-fill.is-amber {
    background-color: var(--hcis-amber-500);
}

.tkp-meter-bar-fill.is-danger {
    background-color: var(--hcis-red-500);
}

/* Grid Card Styling */
.tkp-grid-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.875rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.tkp-grid-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.07);
    border-color: var(--hcis-slate-300);
}

.tkp-grid-card.is-selected {
    border-color: var(--hcis-indigo-500);
    background-color: #fafafe;
    box-shadow: 0 0 0 1px var(--hcis-indigo-500), 0 8px 16px -4px rgba(99, 102, 241, 0.12);
}

.tkp-grid-card.is-late {
    border-left: 3px solid var(--hcis-red-500);
}

.tkp-grid-card.is-done {
    border-left: 3px solid var(--hcis-emerald-500);
}

.tkp-card-header {
    padding: 1rem 1.125rem 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.tkp-card-checkbox {
    cursor: pointer;
    width: 1.125rem;
    height: 1.125rem;
}

.tkp-card-body {
    padding: 0.875rem 1.125rem;
    flex-grow: 1;
}

.tkp-card-footer {
    padding: 0.75rem 1.125rem;
    background-color: var(--hcis-slate-50);
    border-top: 1px solid var(--hcis-slate-100);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Floating Action Dock for Bulk Selection */
.tkp-dock-floating {
    position: fixed;
    bottom: 1.75rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1040;
    max-width: 90vw;
}

.tkp-dock-inner {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.625rem 1rem 0.625rem 1.25rem;
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    border-radius: 9999px;
    box-shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.tkp-dock-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tkp-dock-badge {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    font-weight: 700;
    font-size: 0.8125rem;
    padding: 0.125rem 0.625rem;
    border-radius: 9999px;
}

.tkp-dock-text {
    font-size: 0.875rem;
    font-weight: 600;
}

.tkp-dock-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tkp-dock-btn-close {
    background: transparent;
    border: none;
    color: var(--hcis-slate-400);
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    cursor: pointer;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s ease;
}

.tkp-dock-btn-close:hover {
    color: var(--hcis-white);
}

/* Action Button Group in Table */
.tkp-action-btn-group {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem; /* 8px clean, modern spacing */
    white-space: nowrap;
}

.tkp-action-btn-group :deep(.hcis-btn) {
    flex-shrink: 0;
}

.tkp-btn-bell {
    background: var(--hcis-amber-50) !important;
    border-color: var(--hcis-amber-100) !important;
    color: var(--hcis-amber-600) !important;
    transition: all 0.15s ease !important;
}

.tkp-btn-bell:hover {
    background: var(--hcis-amber-100) !important;
    border-color: var(--hcis-amber-200) !important;
    color: var(--hcis-amber-700) !important;
    transform: scale(1.05);
}

.font-scale-xs {
    font-size: 0.8125rem;
}

.font-scale-2xs {
    font-size: 0.75rem;
}
</style>
