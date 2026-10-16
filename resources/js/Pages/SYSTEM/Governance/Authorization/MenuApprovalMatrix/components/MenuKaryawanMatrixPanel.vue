<template>
    <div class="mkm-container stitch-card p-4">
        <!-- 1. Header & Actions Bar -->
        <header class="mkm-header mb-4">
            <div class="mkm-header-main">
                <div class="mkm-header-icon-box">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h5 class="text-dark fw-bold m-0">Matrix Akses Menu × Karyawan</h5>
                        <span v-if="activeGrupName" class="badge-module-pill">
                            <i class="bi bi-collection-fill me-1"></i>{{ activeGrupName }}
                        </span>
                        <span v-if="data.stats.total_menu" class="badge-count-pill">
                            {{ data.stats.total_menu }} Menu Dinilai
                        </span>
                    </div>
                    <p class="small text-muted m-0">
                        Peta distribusi akses menu per karyawan. Menampilkan menu non-global dengan status penugasan
                        langsung (manual).
                    </p>
                </div>
            </div>

            <div class="mkm-header-actions">
                <!-- Density Toggle -->
                <div class="btn-group btn-group-sm mkm-density-toggle" role="group" aria-label="Kerapatan Tabel">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :class="{ active: !denseMode }"
                        title="Tampilan Normal"
                        @click="denseMode = false"
                    >
                        <i class="bi bi-view-stacked"></i>
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :class="{ active: denseMode }"
                        title="Tampilan Rapat (Dense)"
                        @click="denseMode = true"
                    >
                        <i class="bi bi-grid"></i>
                    </button>
                </div>

                <!-- Refresh Button -->
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                    :disabled="loading"
                    title="Muat ulang data matrix"
                    @click="fetchMatrix"
                >
                    <i class="bi bi-arrow-clockwise" :class="{ 'mkm-spin': loading }"></i>
                    <span class="d-none d-sm-inline">Refresh</span>
                </button>

                <!-- Export Excel Button -->
                <button
                    type="button"
                    class="btn btn-sm btn-success-custom d-inline-flex align-items-center gap-2"
                    :disabled="exporting || loading || !data.total"
                    title="Ekspor data matrix ke Excel via Pusat Aktivitas"
                    @click="exportExcel"
                >
                    <i class="bi" :class="exporting ? 'bi-arrow-repeat mkm-spin' : 'bi-file-earmark-excel-fill'"></i>
                    <span>{{ exporting ? 'Menyiapkan…' : 'Export Excel' }}</span>
                </button>
            </div>
        </header>

        <!-- 2. KPI Metrics Grid -->
        <section class="mkm-kpi-grid mb-4" aria-label="Statistik Matrix Menu">
            <!-- Total Karyawan -->
            <div class="mkm-kpi-card mkm-kpi--indigo">
                <div class="mkm-kpi-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="mkm-kpi-content">
                    <span class="mkm-kpi-label">Total Karyawan</span>
                    <div class="mkm-kpi-value-row">
                        <strong class="mkm-kpi-value">{{ data.stats.karyawan.toLocaleString('id-ID') }}</strong>
                        <span class="mkm-kpi-subtext">orang</span>
                    </div>
                </div>
            </div>

            <!-- Menu Dinilai -->
            <div class="mkm-kpi-card mkm-kpi--sky">
                <div class="mkm-kpi-icon">
                    <i class="bi bi-app-indicator"></i>
                </div>
                <div class="mkm-kpi-content">
                    <span class="mkm-kpi-label">Menu Dinilai</span>
                    <div class="mkm-kpi-value-row">
                        <strong class="mkm-kpi-value">{{ data.stats.total_menu }}</strong>
                        <span class="mkm-kpi-subtext">halaman</span>
                    </div>
                </div>
            </div>

            <!-- Lengkap -->
            <div
                class="mkm-kpi-card mkm-kpi--emerald"
                :class="{ 'is-active-filter': statusFilter === 'lengkap' }"
                role="button"
                tabindex="0"
                title="Klik untuk filter karyawan dengan akses lengkap di semua menu"
                @click="toggleStatusFilter('lengkap')"
            >
                <div class="mkm-kpi-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="mkm-kpi-content">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <span class="mkm-kpi-label">Akses Lengkap</span>
                        <span class="mkm-kpi-rate badge-emerald">{{ percent(data.stats.lengkap) }}%</span>
                    </div>
                    <div class="mkm-kpi-value-row">
                        <strong class="mkm-kpi-value text-emerald">{{
                            data.stats.lengkap.toLocaleString('id-ID')
                        }}</strong>
                        <span class="mkm-kpi-subtext">karyawan</span>
                    </div>
                </div>
            </div>

            <!-- Belum Lengkap -->
            <div
                class="mkm-kpi-card mkm-kpi--amber"
                :class="{ 'is-active-filter': filters.hanya_belum || statusFilter === 'belum_lengkap' }"
                role="button"
                tabindex="0"
                title="Klik untuk filter karyawan yang belum lengkap"
                @click="toggleStatusFilter('belum_lengkap')"
            >
                <div class="mkm-kpi-icon">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="mkm-kpi-content">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <span class="mkm-kpi-label">Belum Lengkap</span>
                        <span class="mkm-kpi-rate badge-amber">{{ percent(data.stats.belum_lengkap) }}%</span>
                    </div>
                    <div class="mkm-kpi-value-row">
                        <strong class="mkm-kpi-value text-amber">{{
                            data.stats.belum_lengkap.toLocaleString('id-ID')
                        }}</strong>
                        <span class="mkm-kpi-subtext">karyawan</span>
                    </div>
                </div>
            </div>

            <!-- Tanpa Akses -->
            <div
                class="mkm-kpi-card mkm-kpi--rose"
                :class="{ 'is-active-filter': statusFilter === 'tanpa_akses' }"
                role="button"
                tabindex="0"
                title="Klik untuk filter karyawan tanpa akses sama sekali"
                @click="toggleStatusFilter('tanpa_akses')"
            >
                <div class="mkm-kpi-icon">
                    <i class="bi bi-slash-circle-fill"></i>
                </div>
                <div class="mkm-kpi-content">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <span class="mkm-kpi-label">Tanpa Akses</span>
                        <span class="mkm-kpi-rate badge-rose">{{ percent(data.stats.tanpa_akses) }}%</span>
                    </div>
                    <div class="mkm-kpi-value-row">
                        <strong class="mkm-kpi-value text-rose">{{
                            data.stats.tanpa_akses.toLocaleString('id-ID')
                        }}</strong>
                        <span class="mkm-kpi-subtext">karyawan</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2b. Progress Bar / Distribution Strip -->
        <div v-if="data.stats.karyawan > 0" class="mkm-progress-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                <span class="fw-semibold text-dark">
                    <i class="bi bi-shield-check me-1 text-indigo"></i>Tingkat Kesiapan Akses Menu
                </span>
                <span>
                    <strong>{{ percent(data.stats.lengkap) }}%</strong> Terpenuhi ({{ data.stats.lengkap }} dari
                    {{ data.stats.karyawan }})
                </span>
            </div>
            <div class="mkm-stacked-bar" title="Distribusi Akses Karyawan">
                <div
                    class="mkm-bar-segment segment-emerald"
                    :style="{ width: `${percent(data.stats.lengkap)}%` }"
                    :title="`Lengkap: ${data.stats.lengkap} (${percent(data.stats.lengkap)}%)`"
                />
                <div
                    class="mkm-bar-segment segment-amber"
                    :style="{ width: `${percent(data.stats.belum_lengkap - data.stats.tanpa_akses)}%` }"
                    :title="`Parsial: ${data.stats.belum_lengkap - data.stats.tanpa_akses} (${percent(data.stats.belum_lengkap - data.stats.tanpa_akses)}%)`"
                />
                <div
                    class="mkm-bar-segment segment-rose"
                    :style="{ width: `${percent(data.stats.tanpa_akses)}%` }"
                    :title="`Tanpa Akses: ${data.stats.tanpa_akses} (${percent(data.stats.tanpa_akses)}%)`"
                />
            </div>
        </div>

        <!-- 3. Official HcisFilterBar Component -->
        <div class="mkm-filterbar-wrapper mb-4">
            <HcisFilterBar
                :search="filters.search"
                search-placeholder="Cari nama karyawan, NIK, atau kode…"
                :filters="hcisFilterDefinitions"
                :filter-values="filters"
                :quick-chips="quickStatusChips"
                :active-chip-value="activeChipValue"
                :results-count="data.total"
                :collapsible="true"
                show-active-tags
                sentiment="brand"
                variant="card"
                @update:search="onSearchUpdate"
                @filter-change="onHcisFilterChange"
                @chip-click="onHcisChipClick"
                @reset="resetFilters"
            />
        </div>

        <!-- 4. Error Alert -->
        <div
            v-if="error"
            class="alert alert-danger mkm-error-alert d-flex justify-content-between align-items-center mb-4"
            role="alert"
        >
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                <div>
                    <strong>Terjadi Kesalahan</strong>
                    <p class="mb-0 small">{{ error }}</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="fetchMatrix">
                <i class="bi bi-arrow-clockwise me-1"></i>Coba Lagi
            </button>
        </div>

        <!-- 5. Main Matrix Grid Table Area -->
        <div v-else class="mkm-main-area">
            <!-- Table Controls & Legend Bar -->
            <div class="mkm-table-toolbar mb-2">
                <div class="mkm-results-info">
                    <span v-if="!loading">
                        Menampilkan <strong>{{ data.rows.length }}</strong> dari
                        <strong>{{ data.total.toLocaleString('id-ID') }}</strong> karyawan
                        <span v-if="filters.menu_page" class="text-indigo fw-semibold">
                            ({{
                                filters.menu_status === 'has_menu' ? 'yang sudah memiliki' : 'yang belum memiliki'
                            }}
                            "{{ activeFilteredMenuName }}")
                        </span>
                        <span v-else-if="adaSaringan" class="text-indigo fw-semibold">(disaring)</span>
                    </span>
                    <span v-else class="text-muted">
                        <i class="bi bi-arrow-repeat mkm-spin me-1"></i>Memuat matrix data…
                    </span>
                </div>

                <div class="mkm-legend-group">
                    <span class="mkm-legend-item">
                        <span class="mkm-legend-badge ya"><i class="bi bi-check-lg"></i></span>
                        <span>Sudah Di-assign</span>
                    </span>
                    <span class="mkm-legend-item">
                        <span class="mkm-legend-badge tidak"><i class="bi bi-dash"></i></span>
                        <span>Belum Di-assign</span>
                    </span>
                    <div class="mkm-divider-vert"></div>
                    <div class="d-flex align-items-center gap-1 small text-muted">
                        <span>Per hal:</span>
                        <select
                            v-model.number="perPage"
                            class="form-select form-select-sm mkm-per-page-select"
                            @change="onPerPageChange"
                        >
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Matrix Scroll Container -->
            <div
                class="mkm-table-wrapper"
                :class="{
                    'is-loading': loading,
                    'is-dense': denseMode,
                }"
                :aria-busy="loading ? 'true' : 'false'"
            >
                <!-- Loading Shimmer Overlay -->
                <div v-if="loading" class="mkm-loading-overlay">
                    <div class="mkm-spinner-box">
                        <div class="spinner-border text-indigo" role="status">
                            <span class="visually-hidden">Memuat...</span>
                        </div>
                        <span class="small fw-semibold text-slate-600 mt-2">Memperbarui Matrix…</span>
                    </div>
                </div>

                <!-- Empty State: No Menus in Group -->
                <div v-if="!loading && !data.menus.length" class="mkm-empty-box">
                    <i class="bi bi-inbox fs-1 text-slate-300 d-block mb-3"></i>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Menu Dinilai</h6>
                    <p class="small text-muted mb-0">
                        Modul ini belum memiliki menu non-global yang dapat di-assign ke karyawan.
                    </p>
                </div>

                <!-- Empty State: No Employees Matched Filters -->
                <div v-else-if="!loading && !data.rows.length" class="mkm-empty-box">
                    <i class="bi bi-person-x fs-1 text-slate-300 d-block mb-3"></i>
                    <h6 class="fw-bold text-dark mb-1">Tidak Ada Karyawan Ditemukan</h6>
                    <p class="small text-muted mb-3">
                        {{
                            filters.menu_page
                                ? `Tidak ada karyawan yang cocok dengan filter menu "${activeFilteredMenuName}" (${filters.menu_status === 'has_menu' ? 'sudah memiliki' : 'belum memiliki'}).`
                                : filters.hanya_belum || statusFilter === 'belum_lengkap'
                                  ? 'Semua karyawan pada kriteria saringan ini sudah memiliki akses lengkap.'
                                  : 'Tidak ada data karyawan yang cocok dengan kriteria pencarian dan saringan Anda.'
                        }}
                    </p>
                    <button
                        v-if="adaSaringan"
                        type="button"
                        class="btn btn-sm btn-primary bg-indigo border-indigo d-inline-flex align-items-center gap-1"
                        @click="resetFilters"
                    >
                        <i class="bi bi-x-circle"></i>
                        <span>Reset Semua Saringan</span>
                    </button>
                </div>

                <!-- The Interactive Matrix Table -->
                <table v-else class="mkm-matrix-table" @mouseleave="clearHover">
                    <thead>
                        <!-- Row 1: Header Category Group -->
                        <tr class="mkm-tr-group">
                            <th rowspan="2" class="mkm-th-sticky-karyawan">
                                <div class="th-karyawan-header">
                                    <i class="bi bi-person-badge-fill me-1 text-indigo"></i>
                                    <span>Karyawan</span>
                                </div>
                            </th>
                            <th rowspan="2" class="mkm-th-sticky-status" title="Jumlah menu yang belum di-assign">
                                <div class="th-status-header">
                                    <span>Belum</span>
                                </div>
                            </th>
                            <th v-for="h in headerGroups" :key="h.label" :colspan="h.count" class="mkm-th-group-header">
                                <div class="group-header-pill">
                                    <span class="group-header-text">{{ h.label || 'UMUM' }}</span>
                                    <span class="group-header-count">{{ h.count }}</span>
                                </div>
                            </th>
                        </tr>

                        <!-- Row 2: Menu Page Columns -->
                        <tr class="mkm-tr-menu">
                            <th
                                v-for="m in data.menus"
                                :key="m.Jenis_page"
                                class="mkm-th-menu-col"
                                :class="{
                                    'is-column-hovered': hoveredPageId === m.Jenis_page,
                                    'is-column-active-filter': filters.menu_page === m.Jenis_page,
                                }"
                                @mouseenter="setHoverCol(m.Jenis_page)"
                            >
                                <el-dropdown trigger="click" @command="handleHeaderMenuCommand($event, m)">
                                    <div class="mkm-menu-col-inner">
                                        <!-- Active Filter Tag on Column Header -->
                                        <span
                                            v-if="filters.menu_page === m.Jenis_page"
                                            class="badge-col-filter-indicator"
                                            :class="
                                                filters.menu_status === 'has_menu'
                                                    ? 'bg-emerald text-white'
                                                    : 'bg-amber text-dark'
                                            "
                                        >
                                            <i class="bi bi-funnel-fill me-0.5"></i
                                            >{{ filters.menu_status === 'has_menu' ? 'Sudah' : 'Belum' }}
                                        </span>

                                        <span class="mkm-menu-title" :title="m.Nama_Menu">{{ m.Nama_Menu }}</span>

                                        <div class="d-flex align-items-center justify-content-center gap-1 mt-0.5">
                                            <span
                                                class="mkm-menu-stat-pill"
                                                :class="m.jumlah_belum > 0 ? 'stat-has-missing' : 'stat-all-complete'"
                                                :title="`Klik kolom untuk filter: ${m.jumlah_belum} dari ${data.total} belum menerima menu ini.`"
                                            >
                                                {{ m.jumlah_belum > 0 ? `${m.jumlah_belum} belum` : 'Lengkap' }}
                                            </span>
                                            <i class="bi bi-chevron-down text-slate-400" style="font-size: 0.6rem"></i>
                                        </div>
                                    </div>

                                    <template #dropdown>
                                        <el-dropdown-menu class="mkm-col-dropdown">
                                            <div class="px-3 py-1.5 border-bottom bg-light">
                                                <small class="fw-bold text-dark d-block">{{ m.Nama_Menu }}</small>
                                                <span class="text-muted text-xxs font-monospace">{{
                                                    m.Jenis_page
                                                }}</span>
                                            </div>
                                            <el-dropdown-item command="filter_missing">
                                                <i class="bi bi-slash-circle text-amber me-1.5"></i>
                                                <span>Filter: Cari yang <strong>Belum Di-assign</strong></span>
                                            </el-dropdown-item>
                                            <el-dropdown-item command="filter_has">
                                                <i class="bi bi-check-circle text-emerald me-1.5"></i>
                                                <span>Filter: Cari yang <strong>Sudah Di-assign</strong></span>
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="filters.menu_page === m.Jenis_page"
                                                divided
                                                command="clear_filter"
                                            >
                                                <i class="bi bi-x-circle text-danger me-1.5"></i>
                                                <span>Hapus Filter Menu Ini</span>
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="r in filteredRows"
                            :key="r.UserID_Web"
                            class="mkm-row"
                            :class="{
                                'is-row-hovered': hoveredUserId === r.UserID_Web,
                            }"
                            @mouseenter="setHoverRow(r.UserID_Web)"
                        >
                            <!-- Column 1: Karyawan Profile (Sticky Left) -->
                            <th scope="row" class="mkm-th-sticky-karyawan mkm-cell-profile" @click="openUserModal(r)">
                                <div class="mkm-profile-card">
                                    <div class="mkm-user-avatar" :style="{ background: getAvatarGradient(r.Nama) }">
                                        {{ getInitials(r.Nama) }}
                                    </div>
                                    <div class="mkm-profile-meta">
                                        <div class="mkm-user-name" :title="r.Nama">
                                            {{ r.Nama }}
                                        </div>
                                        <div
                                            class="mkm-user-sub"
                                            :title="`${r.Kode_Karyawan} · ${r.Divisi} · ${r.Jabatan}`"
                                        >
                                            <span class="user-code">{{ r.Kode_Karyawan }}</span>
                                            <span class="user-bullet">·</span>
                                            <span class="user-dept">{{ r.Divisi || r.Lokasi }}</span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="mkm-row-inspect-btn"
                                        title="Buka rincian akses karyawan"
                                        @click.stop="openUserModal(r)"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </th>

                            <!-- Column 2: Status Belum (Sticky Left) -->
                            <td class="mkm-th-sticky-status mkm-cell-status">
                                <el-tooltip
                                    :content="
                                        r.jumlah_belum === 0
                                            ? 'Akses lengkap (semua menu sudah di-assign)'
                                            : `Belum di-assign ${r.jumlah_belum} dari ${data.menus.length} menu.`
                                    "
                                    placement="right"
                                >
                                    <span
                                        class="mkm-status-badge"
                                        :class="
                                            r.jumlah_belum === 0
                                                ? 'badge-ok'
                                                : r.jumlah_belum === data.menus.length
                                                  ? 'badge-none'
                                                  : 'badge-partial'
                                        "
                                    >
                                        {{ r.jumlah_belum === 0 ? '0' : r.jumlah_belum }}
                                    </span>
                                </el-tooltip>
                            </td>

                            <!-- Matrix Grid Cells -->
                            <td
                                v-for="m in data.menus"
                                :key="m.Jenis_page"
                                class="mkm-matrix-cell"
                                :class="{
                                    'cell-granted': punya(r, m),
                                    'cell-missing': !punya(r, m),
                                    'is-column-hovered': hoveredPageId === m.Jenis_page,
                                    'is-cell-focused': hoveredUserId === r.UserID_Web && hoveredPageId === m.Jenis_page,
                                    'is-cell-filtered-target': filters.menu_page === m.Jenis_page,
                                }"
                                @mouseenter="setHover(r.UserID_Web, m.Jenis_page)"
                                @click="openUserModal(r)"
                            >
                                <el-tooltip
                                    :content="`${r.Nama} — ${m.Nama_Menu}: ${punya(r, m) ? 'SUDAH' : 'BELUM'} di-assign`"
                                    placement="top"
                                    :show-after="200"
                                >
                                    <div class="mkm-cell-indicator">
                                        <i
                                            v-if="punya(r, m)"
                                            class="bi bi-check-lg icon-granted"
                                            aria-label="Sudah"
                                        ></i>
                                        <span v-else class="icon-missing" aria-label="Belum">—</span>
                                    </div>
                                </el-tooltip>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 6. Bottom Pagination Bar -->
            <div v-if="data.total > 0" class="mkm-pagination-bar mt-3 pt-2">
                <div class="small text-muted d-none d-md-block">
                    Menampilkan {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, data.total) }} dari
                    {{ data.total.toLocaleString('id-ID') }} baris
                </div>
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="data.total"
                    :page-sizes="[15, 25, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    background
                    @current-change="fetchMatrix"
                    @size-change="onPerPageChange"
                />
            </div>
        </div>

        <!-- 7. Premium Employee Access Detail Modal (Dialog Version) -->
        <el-dialog
            v-model="modalVisible"
            title="Rincian Hak Akses Menu Karyawan"
            width="min(720px, 94vw)"
            destroy-on-close
            append-to-body
            custom-class="mkm-detail-dialog"
        >
            <div v-if="selectedRowUser" class="mkm-modal-content">
                <!-- User Profile Header Card -->
                <div class="mkm-modal-user-card mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div
                            class="mkm-user-avatar lg"
                            :style="{ background: getAvatarGradient(selectedRowUser.Nama) }"
                        >
                            {{ getInitials(selectedRowUser.Nama) }}
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size: 1rem">
                                    {{ selectedRowUser.Nama }}
                                </h6>
                                <span
                                    class="badge font-monospace bg-light text-secondary border px-2 py-1"
                                    style="font-size: 0.72rem"
                                >
                                    {{ selectedRowUser.Kode_Karyawan }}
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-1.5 mt-1.5">
                                <span class="badge-modal-tag tag-indigo">
                                    <i class="bi bi-diagram-3 me-1"></i>{{ selectedRowUser.Divisi || 'Divisi -' }}
                                </span>
                                <span v-if="selectedRowUser.Jabatan" class="badge-modal-tag tag-slate">
                                    <i class="bi bi-person-workspace me-1"></i>{{ selectedRowUser.Jabatan }}
                                </span>
                                <span v-if="selectedRowUser.Lokasi" class="badge-modal-tag tag-slate">
                                    <i class="bi bi-geo-alt me-1"></i>{{ selectedRowUser.Lokasi }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar Component -->
                    <div class="mkm-modal-progress-strip mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="small fw-semibold text-slate-700">
                                <i class="bi bi-pie-chart-fill text-indigo me-1"></i>Kelengkapan Akses Modul
                            </span>
                            <span
                                class="small fw-bold"
                                :class="selectedRowUser.jumlah_belum === 0 ? 'text-emerald' : 'text-amber'"
                            >
                                {{ data.menus.length - selectedRowUser.jumlah_belum }} dari {{ data.menus.length }} Menu
                                Aktif ({{ modalCompletionPercent }}%)
                            </span>
                        </div>
                        <div class="mkm-modal-progress-bar">
                            <div
                                class="mkm-modal-progress-fill"
                                :class="
                                    selectedRowUser.jumlah_belum === 0
                                        ? 'fill-emerald'
                                        : selectedRowUser.jumlah_belum === data.menus.length
                                          ? 'fill-rose'
                                          : 'fill-amber'
                                "
                                :style="{ width: `${modalCompletionPercent}%` }"
                            />
                        </div>
                    </div>
                </div>

                <!-- Internal Search & Segmented Filter Bar -->
                <div class="mkm-modal-toolbar mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-6 col-12">
                            <el-input
                                v-model="modalMenuSearch"
                                clearable
                                placeholder="Cari nama menu / kode..."
                                size="small"
                                class="mkm-modal-search"
                            >
                                <template #prefix>
                                    <i class="bi bi-search text-muted"></i>
                                </template>
                            </el-input>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="mkm-modal-filter-tabs">
                                <button
                                    type="button"
                                    class="mkm-modal-filter-btn"
                                    :class="{ active: modalStatusFilter === 'all' }"
                                    @click="modalStatusFilter = 'all'"
                                >
                                    Semua ({{ data.menus.length }})
                                </button>
                                <button
                                    type="button"
                                    class="mkm-modal-filter-btn btn-granted"
                                    :class="{ active: modalStatusFilter === 'granted' }"
                                    @click="modalStatusFilter = 'granted'"
                                >
                                    Di-assign ({{ data.menus.length - selectedRowUser.jumlah_belum }})
                                </button>
                                <button
                                    type="button"
                                    class="mkm-modal-filter-btn btn-missing"
                                    :class="{ active: modalStatusFilter === 'missing' }"
                                    @click="modalStatusFilter = 'missing'"
                                >
                                    Belum ({{ selectedRowUser.jumlah_belum }})
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scrollable Menu Group Cards List -->
                <div class="mkm-modal-menu-list">
                    <div v-if="!modalGroupedMenus.length" class="text-center py-4 text-muted small">
                        <i class="bi bi-search fs-3 text-slate-300 d-block mb-1"></i>
                        Tidak ada menu yang cocok dengan pencarian / filter ini.
                    </div>

                    <div v-for="group in modalGroupedMenus" :key="group.label" class="mkm-modal-group-card mb-3">
                        <!-- Group Header -->
                        <div class="mkm-modal-group-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="mkm-group-pill">{{ group.label || 'UMUM' }}</span>
                                <span class="small text-muted">{{ group.menus.length }} menu</span>
                            </div>
                            <span
                                class="badge-modal-group-count"
                                :class="{ 'is-all-granted': group.assignedCount === group.menus.length }"
                            >
                                {{ group.assignedCount }} / {{ group.menus.length }} Aktif
                            </span>
                        </div>

                        <!-- Menu Grid Items in Group -->
                        <div class="mkm-modal-group-grid">
                            <div
                                v-for="item in group.menus"
                                :key="item.Jenis_page"
                                class="mkm-modal-menu-item"
                                :class="punya(selectedRowUser, item) ? 'is-granted' : 'is-missing'"
                            >
                                <div class="d-flex align-items-start gap-2 min-w-0">
                                    <div class="mkm-modal-status-icon mt-0.5">
                                        <i
                                            v-if="punya(selectedRowUser, item)"
                                            class="bi bi-check-circle-fill text-emerald"
                                        ></i>
                                        <i v-else class="bi bi-dash-circle text-slate-300"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="mkm-modal-menu-title text-truncate" :title="item.Nama_Menu">
                                            {{ item.Nama_Menu }}
                                        </div>
                                        <div
                                            class="mkm-modal-menu-code font-monospace text-muted text-truncate"
                                            :title="item.Jenis_page"
                                        >
                                            {{ item.Jenis_page }}
                                        </div>
                                    </div>
                                </div>
                                <span
                                    class="badge-modal-status"
                                    :class="punya(selectedRowUser, item) ? 'status-granted' : 'status-missing'"
                                >
                                    {{ punya(selectedRowUser, item) ? 'Di-assign' : 'Belum' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <template #footer>
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-light border px-3" @click="modalVisible = false">
                        Tutup
                    </button>
                    <button
                        v-if="canAssign && selectedRowUser"
                        type="button"
                        class="btn btn-sm btn-primary bg-indigo border-indigo d-inline-flex align-items-center gap-1.5 px-3 py-1.5"
                        @click="handleAssignUser(selectedRowUser)"
                    >
                        <i class="bi bi-person-gear"></i>
                        <span>Kelola Akses di Tab Single Assign</span>
                    </button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useActivityCenter } from '@/composables/useActivityCenter';
import { HcisFilterBar } from '@/components/ui';

const KOSONG = {
    menus: [],
    rows: [],
    total: 0,
    per_page: 25,
    stats: { karyawan: 0, total_menu: 0, lengkap: 0, belum_lengkap: 0, tanpa_akses: 0 },
};

const saringanAwal = () => ({
    search: '',
    cabang: '',
    divisi_id: '',
    sub_divisi_id: '',
    level_id: '',
    jabatan_id: '',
    menu_page: '',
    menu_status: 'missing_menu', // 'missing_menu' | 'has_menu'
    hanya_belum: false,
});

export default {
    name: 'MenuKaryawanMatrixPanel',
    components: {
        HcisFilterBar,
    },
    props: {
        grupId: { type: Number, required: true },
        canAssign: { type: Boolean, default: false },
        activeGrup: { type: Object, default: () => ({}) },
    },
    emits: ['assign-user'],
    data() {
        return {
            data: { ...KOSONG },
            filters: saringanAwal(),
            options: { cabang: [], divisi: [], subDivisi: [], level: [], jabatan: [] },
            page: 1,
            perPage: 25,
            loading: false,
            exporting: false,
            error: '',
            searchTimer: null,
            requestSeq: 0,

            // UI UX Enhancements state
            denseMode: false,
            statusFilter: null, // null | 'belum_lengkap' | 'lengkap' | 'tanpa_akses'
            hoveredUserId: null,
            hoveredPageId: null,

            // Enhanced Modal Inspector State
            modalVisible: false,
            selectedRowUser: null,
            modalMenuSearch: '',
            modalStatusFilter: 'all', // 'all' | 'granted' | 'missing'
        };
    },
    computed: {
        activeGrupName() {
            return this.activeGrup?.Nama_Grup_Fitur || this.activeGrup?.Kode_Grup_Fitur || '';
        },
        activeFilteredMenuName() {
            if (!this.filters.menu_page) return '';
            const m = this.data.menus.find((menu) => menu.Jenis_page === this.filters.menu_page);
            return m ? m.Nama_Menu : this.filters.menu_page;
        },
        subDivisiOptions() {
            const d = this.filters.divisi_id;
            return d ? this.options.subDivisi.filter((s) => s.divisi_id === d) : this.options.subDivisi;
        },
        jabatanOptions() {
            const l = this.filters.level_id;
            return l ? this.options.jabatan.filter((j) => j.level_id === l) : this.options.jabatan;
        },
        headerGroups() {
            const groups = [];
            this.data.menus.forEach((m) => {
                const last = groups[groups.length - 1];
                if (last && last.label === m.Header_Label) last.count += 1;
                else groups.push({ label: m.Header_Label, count: 1 });
            });
            return groups;
        },
        adaSaringan() {
            const f = this.filters;
            return !!(
                f.search ||
                f.cabang ||
                f.divisi_id ||
                f.sub_divisi_id ||
                f.level_id ||
                f.jabatan_id ||
                f.menu_page ||
                f.hanya_belum ||
                this.statusFilter
            );
        },
        activeChipValue() {
            if (this.filters.hanya_belum || this.statusFilter === 'belum_lengkap') return 'belum_lengkap';
            if (this.statusFilter === 'lengkap') return 'lengkap';
            if (this.statusFilter === 'tanpa_akses') return 'tanpa_akses';
            return 'all';
        },
        quickStatusChips() {
            return [
                {
                    label: 'Semua',
                    value: 'all',
                    badge: this.data.stats.karyawan,
                    icon: 'people-fill',
                },
                {
                    label: 'Belum Lengkap',
                    value: 'belum_lengkap',
                    badge: this.data.stats.belum_lengkap,
                    color: 'amber',
                    icon: 'exclamation-triangle-fill',
                },
                {
                    label: 'Akses Lengkap',
                    value: 'lengkap',
                    badge: this.data.stats.lengkap,
                    color: 'emerald',
                    icon: 'check-circle-fill',
                },
                {
                    label: 'Tanpa Akses',
                    value: 'tanpa_akses',
                    badge: this.data.stats.tanpa_akses,
                    color: 'danger',
                    icon: 'slash-circle-fill',
                },
            ];
        },
        hcisFilterDefinitions() {
            const defs = [
                {
                    key: 'menu_page',
                    label: 'Menu Spesifik',
                    icon: 'app-indicator',
                    type: 'select',
                    placeholder: 'Pilih menu yang ingin dinilai...',
                    options: this.data.menus.map((m) => ({
                        label: `${m.Nama_Menu} (${m.Header_Label || 'Umum'})`,
                        value: m.Jenis_page,
                    })),
                    clearable: true,
                    filterable: true,
                },
            ];

            if (this.filters.menu_page) {
                defs.push({
                    key: 'menu_status',
                    label: 'Status Akses Menu Terpilih',
                    icon: 'check-circle-fill',
                    type: 'select',
                    placeholder: 'Status akses menu',
                    options: [
                        { label: 'Belum Memiliki Menu Ini (Missing)', value: 'missing_menu' },
                        { label: 'Sudah Memiliki Menu Ini (Granted)', value: 'has_menu' },
                    ],
                    clearable: false,
                });
            }

            defs.push(
                {
                    key: 'cabang',
                    label: 'Lokasi Kerja',
                    icon: 'geo-alt-fill',
                    type: 'select',
                    placeholder: 'Semua Lokasi',
                    options: this.options.cabang,
                    clearable: true,
                    filterable: true,
                },
                {
                    key: 'divisi_id',
                    label: 'Divisi',
                    icon: 'diagram-3-fill',
                    type: 'select',
                    placeholder: 'Semua Divisi',
                    options: this.options.divisi,
                    clearable: true,
                    filterable: true,
                },
                {
                    key: 'sub_divisi_id',
                    label: 'Departemen',
                    icon: 'folder2',
                    type: 'select',
                    placeholder: 'Semua Departemen',
                    options: this.subDivisiOptions,
                    clearable: true,
                    filterable: true,
                    disabled: !this.filters.divisi_id && !this.subDivisiOptions.length,
                },
                {
                    key: 'level_id',
                    label: 'Level',
                    icon: 'layers-fill',
                    type: 'select',
                    placeholder: 'Semua Level',
                    options: this.options.level,
                    clearable: true,
                    filterable: true,
                },
                {
                    key: 'jabatan_id',
                    label: 'Jabatan',
                    icon: 'person-workspace',
                    type: 'select',
                    placeholder: 'Semua Jabatan',
                    options: this.jabatanOptions,
                    clearable: true,
                    filterable: true,
                    disabled: !this.filters.level_id && !this.jabatanOptions.length,
                },
            );

            return defs;
        },
        filteredRows() {
            if (!this.statusFilter) return this.data.rows;
            if (this.statusFilter === 'lengkap') {
                return this.data.rows.filter((r) => r.jumlah_belum === 0);
            }
            if (this.statusFilter === 'tanpa_akses') {
                return this.data.rows.filter((r) => r.jumlah_belum === this.data.menus.length);
            }
            if (this.statusFilter === 'belum_lengkap') {
                return this.data.rows.filter((r) => r.jumlah_belum > 0);
            }
            return this.data.rows;
        },
        modalCompletionPercent() {
            if (!this.selectedRowUser || !this.data.menus.length) return 0;
            const granted = this.data.menus.length - this.selectedRowUser.jumlah_belum;
            return Math.round((granted / Math.max(this.data.menus.length, 1)) * 100);
        },
        modalGroupedMenus() {
            if (!this.selectedRowUser || !this.data.menus.length) return [];
            const search = (this.modalMenuSearch || '').trim().toLowerCase();
            const filter = this.modalStatusFilter;

            const groups = {};
            this.data.menus.forEach((m) => {
                const isGranted = this.punya(this.selectedRowUser, m);

                // Filter by Status
                if (filter === 'granted' && !isGranted) return;
                if (filter === 'missing' && isGranted) return;

                // Filter by Search text
                if (search) {
                    const matchName = (m.Nama_Menu || '').toLowerCase().includes(search);
                    const matchSlug = (m.Jenis_page || '').toLowerCase().includes(search);
                    const matchHeader = (m.Header_Label || '').toLowerCase().includes(search);
                    if (!matchName && !matchSlug && !matchHeader) return;
                }

                const label = m.Header_Label || 'UMUM';
                if (!groups[label]) {
                    groups[label] = { label, menus: [], assignedCount: 0 };
                }
                groups[label].menus.push(m);
                if (isGranted) {
                    groups[label].assignedCount++;
                }
            });

            return Object.values(groups);
        },
    },
    watch: {
        grupId: {
            immediate: true,
            handler() {
                this.filters = saringanAwal();
                this.page = 1;
                this.statusFilter = null;
                this.fetchOptions();
                this.fetchMatrix();
            },
        },
    },
    beforeUnmount() {
        clearTimeout(this.searchTimer);
    },
    methods: {
        percent(val) {
            if (!this.data.stats.karyawan) return 0;
            return Math.round((Number(val || 0) / this.data.stats.karyawan) * 100);
        },
        punya(row, menu) {
            if (!row || !row.pages || !menu) return false;
            return row.pages.includes(menu.Jenis_page);
        },
        params() {
            const f = this.filters;
            const p = { hanya_belum: f.hanya_belum ? 1 : 0 };
            [
                'search',
                'cabang',
                'divisi_id',
                'sub_divisi_id',
                'level_id',
                'jabatan_id',
                'menu_page',
                'menu_status',
            ].forEach((k) => {
                if (f[k] !== '' && f[k] !== null && f[k] !== undefined) p[k] = f[k];
            });
            return p;
        },
        async fetchOptions() {
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/filter-options', {
                    params: { grup_id: this.grupId },
                });
                this.options = {
                    cabang: data.cabang || [],
                    divisi: data.divisi || [],
                    subDivisi: data.subDivisi || [],
                    level: data.level || [],
                    jabatan: data.jabatan || [],
                };
            } catch (e) {
                ElMessage.error('Gagal memuat opsi saringan karyawan.');
            }
        },
        async fetchMatrix() {
            const seq = ++this.requestSeq;
            this.loading = true;
            this.error = '';
            try {
                const { data } = await axios.get(
                    `/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/matrix-karyawan`,
                    {
                        params: { ...this.params(), page: this.page, per_page: this.perPage },
                    },
                );
                if (seq !== this.requestSeq) return;
                this.data = { ...KOSONG, ...data };
            } catch (e) {
                if (seq !== this.requestSeq) return;
                this.error = e?.response?.data?.message || 'Gagal memuat matrix menu karyawan.';
            } finally {
                if (seq === this.requestSeq) this.loading = false;
            }
        },
        reload() {
            this.page = 1;
            this.fetchMatrix();
        },
        onPerPageChange(val) {
            this.perPage = Number(val) || 25;
            this.page = 1;
            this.fetchMatrix();
        },
        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(this.reload, 400);
        },
        onSearchUpdate(val) {
            this.filters.search = val || '';
            this.onSearchInput();
        },
        onHcisFilterChange({ key, value }) {
            if (key === 'divisi_id') {
                this.filters.divisi_id = value;
                this.filters.sub_divisi_id = '';
            } else if (key === 'level_id') {
                this.filters.level_id = value;
                this.filters.jabatan_id = '';
            } else if (key === 'menu_page') {
                this.filters.menu_page = value || '';
                if (!value) this.filters.menu_status = 'missing_menu';
            } else {
                this.filters[key] = value;
            }
            this.reload();
        },
        onHcisChipClick(chip) {
            if (!chip || chip.value === 'all') {
                this.statusFilter = null;
                this.filters.hanya_belum = false;
            } else if (chip.value === 'belum_lengkap') {
                this.statusFilter = 'belum_lengkap';
                this.filters.hanya_belum = true;
            } else {
                this.statusFilter = chip.value;
                this.filters.hanya_belum = false;
            }
            this.reload();
        },
        toggleStatusFilter(filterKey) {
            this.onHcisChipClick({ value: this.statusFilter === filterKey ? 'all' : filterKey });
        },
        clearMenuFilter() {
            this.filters.menu_page = '';
            this.filters.menu_status = 'missing_menu';
            this.reload();
        },
        handleHeaderMenuCommand(command, menu) {
            if (command === 'filter_missing') {
                this.filters.menu_page = menu.Jenis_page;
                this.filters.menu_status = 'missing_menu';
                this.reload();
            } else if (command === 'filter_has') {
                this.filters.menu_page = menu.Jenis_page;
                this.filters.menu_status = 'has_menu';
                this.reload();
            } else if (command === 'clear_filter') {
                this.clearMenuFilter();
            }
        },
        resetFilters() {
            this.filters = saringanAwal();
            this.statusFilter = null;
            this.reload();
        },
        async exportExcel() {
            this.exporting = true;
            try {
                await axios.post(
                    `/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/matrix-karyawan/export`,
                    this.params(),
                );
                useActivityCenter().notifyNewTask();
                ElMessage.success(
                    'Ekspor sedang disiapkan. Unduh dari Pusat Aktivitas (ikon awan di atas) saat sudah siap.',
                );
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Gagal memulai ekspor.');
            } finally {
                this.exporting = false;
            }
        },

        // Hover Crosshair interaction
        setHover(userId, pageId) {
            this.hoveredUserId = userId;
            this.hoveredPageId = pageId;
        },
        setHoverRow(userId) {
            this.hoveredUserId = userId;
        },
        setHoverCol(pageId) {
            this.hoveredPageId = pageId;
        },
        clearHover() {
            this.hoveredUserId = null;
            this.hoveredPageId = null;
        },

        // Modal Dialog Inspector
        openUserModal(user) {
            this.selectedRowUser = user;
            this.modalMenuSearch = '';
            this.modalStatusFilter = 'all';
            this.modalVisible = true;
        },
        handleAssignUser(user) {
            this.modalVisible = false;
            this.$emit('assign-user', user);
        },

        // Avatar Initial & Gradient generator
        getInitials(name) {
            if (!name) return '??';
            const parts = name.trim().split(/\s+/);
            if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        },
        getAvatarGradient(name) {
            if (!name) return 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)';
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            const colors = [
                'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)', // Indigo
                'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)', // Blue
                'linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%)', // Sky
                'linear-gradient(135deg, #10b981 0%, #047857 100%)', // Emerald
                'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)', // Purple
                'linear-gradient(135deg, #f59e0b 0%, #b45309 100%)', // Amber
                'linear-gradient(135deg, #ec4899 0%, #be185d 100%)', // Pink
                'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)', // Teal
            ];
            const index = Math.abs(hash) % colors.length;
            return colors[index];
        },
    },
};
</script>

<style scoped>
/* ==========================================================================
   Design Tokens & Modern Layout
   ========================================================================== */
.mkm-container {
    background: #fff;
    border-radius: 16px;
    position: relative;
}

/* 1. Header & Actions */
.mkm-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
}
.mkm-header-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.mkm-header-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #e0e7ff 0%, #ede9fe 100%);
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.12);
}
.badge-module-pill {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #4338ca;
    display: inline-flex;
    align-items: center;
}
.badge-count-pill {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
}
.mkm-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.btn-success-custom {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s;
}
.btn-success-custom:hover:not(:disabled) {
    background: #059669;
    border-color: #059669;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
}
.mkm-density-toggle .btn {
    padding: 0.25rem 0.6rem;
    font-size: 0.85rem;
}

/* 2. KPI Cards Grid */
.mkm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 0.85rem;
}
.mkm-kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}
.mkm-kpi-card[role='button'] {
    cursor: pointer;
}
.mkm-kpi-card[role='button']:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
    border-color: #cbd5e1;
}
.mkm-kpi-card.is-active-filter {
    border-width: 2px;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.mkm-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.mkm-kpi-content {
    flex-grow: 1;
    min-width: 0;
}
.mkm-kpi-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.15rem;
}
.mkm-kpi-value-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}
.mkm-kpi-value {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.1;
    color: #0f172a;
}
.mkm-kpi-subtext {
    font-size: 0.72rem;
    color: #94a3b8;
}
.mkm-kpi-rate {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
}

/* KPI Color Variants */
.mkm-kpi--indigo .mkm-kpi-icon {
    background: #e0e7ff;
    color: #4f46e5;
}
.mkm-kpi--sky .mkm-kpi-icon {
    background: #e0f2fe;
    color: #0284c7;
}
.mkm-kpi--emerald .mkm-kpi-icon {
    background: #dcfce7;
    color: #15803d;
}
.mkm-kpi--emerald.is-active-filter {
    border-color: #10b981;
    background: #f0fdf4;
}
.mkm-kpi--amber .mkm-kpi-icon {
    background: #fef3c7;
    color: #b45309;
}
.mkm-kpi--amber.is-active-filter {
    border-color: #f59e0b;
    background: #fffbeb;
}
.mkm-kpi--rose .mkm-kpi-icon {
    background: #ffe4e6;
    color: #e11d48;
}
.mkm-kpi--rose.is-active-filter {
    border-color: #f43f5e;
    background: #fff1f2;
}

.text-emerald {
    color: #15803d !important;
}
.text-amber {
    color: #b45309 !important;
}
.text-rose {
    color: #be123c !important;
}
.bg-emerald-soft {
    background: #dcfce7 !important;
}
.bg-amber-soft {
    background: #fef3c7 !important;
}
.badge-emerald {
    background: #dcfce7;
    color: #15803d;
}
.badge-amber {
    background: #fef3c7;
    color: #b45309;
}
.badge-rose {
    background: #ffe4e6;
    color: #be123c;
}

/* 2b. Progress Bar */
.mkm-progress-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.65rem 0.95rem;
}
.mkm-stacked-bar {
    display: flex;
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.mkm-bar-segment {
    height: 100%;
    transition: width 0.4s ease;
}
.segment-emerald {
    background: #10b981;
}
.segment-amber {
    background: #f59e0b;
}
.segment-rose {
    background: #f43f5e;
}

/* 3. Filterbar Wrapper */
.mkm-filterbar-wrapper :deep(.hcis-filter-bar) {
    margin-bottom: 0;
}

/* 4. Table Toolbar & Legend */
.mkm-table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.25rem 0.2rem;
}
.mkm-results-info {
    font-size: 0.78rem;
    color: #475569;
}
.mkm-legend-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.mkm-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
}
.mkm-legend-badge {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}
.mkm-legend-badge.ya {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.mkm-legend-badge.tidak {
    background: #f1f5f9;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
}
.mkm-divider-vert {
    width: 1px;
    height: 16px;
    background: #cbd5e1;
}
.mkm-per-page-select {
    width: auto;
    display: inline-block;
    padding: 0.15rem 1.6rem 0.15rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 6px;
}

/* 5. Matrix Table Container */
.mkm-table-wrapper {
    overflow: auto;
    max-height: 62vh;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background: #fff;
    position: relative;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
}
.mkm-table-wrapper.is-loading {
    min-height: 180px;
}
.mkm-loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(2px);
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
}
.mkm-spinner-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}
.mkm-empty-box {
    padding: 3.5rem 1rem;
    text-align: center;
}

/* 5b. Table Grid Specs */
.mkm-matrix-table {
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.78rem;
    min-width: 100%;
}
.mkm-matrix-table th,
.mkm-matrix-table td {
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
    padding: 0.45rem 0.6rem;
    white-space: nowrap;
    background: #fff;
}
.is-dense .mkm-matrix-table th,
.is-dense .mkm-matrix-table td {
    padding: 0.3rem 0.45rem;
}

/* Sticky Headers & Columns */
.mkm-matrix-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
}
.mkm-matrix-table thead tr:nth-child(2) th {
    top: 36px;
    z-index: 10;
}
.mkm-th-sticky-karyawan {
    position: sticky;
    left: 0;
    z-index: 12 !important;
    min-width: 250px;
    max-width: 290px;
    background: #f8fafc !important;
    box-shadow: 2px 0 4px -1px rgba(0, 0, 0, 0.08);
}
.mkm-matrix-table tbody .mkm-th-sticky-karyawan {
    background: #fff !important;
    z-index: 5 !important;
    cursor: pointer;
}
.mkm-matrix-table tbody .mkm-row:hover .mkm-th-sticky-karyawan,
.mkm-matrix-table tbody .mkm-row.is-row-hovered .mkm-th-sticky-karyawan {
    background: #f1f5f9 !important;
}

.mkm-th-sticky-status {
    position: sticky;
    left: 250px;
    z-index: 11 !important;
    min-width: 70px;
    max-width: 75px;
    text-align: center;
    background: #f8fafc !important;
    box-shadow: 2px 0 4px -1px rgba(0, 0, 0, 0.08);
}
.mkm-matrix-table tbody .mkm-th-sticky-status {
    background: #fff !important;
    z-index: 4 !important;
}
.mkm-matrix-table tbody .mkm-row:hover .mkm-th-sticky-status,
.mkm-matrix-table tbody .mkm-row.is-row-hovered .mkm-th-sticky-status {
    background: #f1f5f9 !important;
}

/* Category Group Header */
.mkm-th-group-header {
    background: #f1f5f9 !important;
    border-bottom: 2px solid #cbd5e1 !important;
    padding: 0.35rem 0.5rem !important;
}
.group-header-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #e2e8f0;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #334155;
    text-transform: uppercase;
}
.group-header-count {
    background: #cbd5e1;
    color: #1e293b;
    border-radius: 999px;
    padding: 0.05rem 0.35rem;
    font-size: 0.65rem;
}

/* Menu Column Header */
.mkm-th-menu-col {
    min-width: 110px;
    max-width: 150px;
    text-align: center;
    vertical-align: bottom;
    background: #f8fafc !important;
    transition: all 0.15s;
    cursor: pointer;
}
.mkm-th-menu-col:hover,
.mkm-th-menu-col.is-column-hovered {
    background: #ede9fe !important;
    color: #4338ca !important;
}
.mkm-th-menu-col.is-column-active-filter {
    background: #e0e7ff !important;
    box-shadow: inset 0 3px 0 #4f46e5;
}
.mkm-menu-col-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    width: 100%;
}
.badge-col-filter-indicator {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.bg-emerald {
    background: #10b981 !important;
}
.bg-amber {
    background: #f59e0b !important;
}
.mkm-menu-title {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.25;
    white-space: normal;
    text-align: center;
    max-height: 2.5em;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mkm-menu-stat-pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    transition: transform 0.15s;
}
.mkm-th-menu-col:hover .mkm-menu-stat-pill {
    transform: scale(1.05);
}
.stat-has-missing {
    background: #fef3c7;
    color: #b45309;
}
.stat-all-complete {
    background: #dcfce7;
    color: #15803d;
}

/* Row & Profile Cell */
.mkm-profile-card {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    position: relative;
}
.mkm-user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: #fff;
    font-weight: 700;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.mkm-user-avatar.lg {
    width: 44px;
    height: 44px;
    font-size: 1.05rem;
    border-radius: 12px;
}
.mkm-profile-meta {
    min-width: 0;
    flex-grow: 1;
}
.mkm-user-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.78rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.mkm-user-sub {
    font-size: 0.68rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.user-code {
    font-family: monospace;
    font-weight: 600;
    color: #475569;
}
.user-bullet {
    color: #cbd5e1;
}
.mkm-row-inspect-btn {
    opacity: 0;
    border: none;
    background: #e0e7ff;
    color: #4338ca;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
.mkm-row:hover .mkm-row-inspect-btn {
    opacity: 1;
}

/* Status Badge in Column 2 */
.mkm-status-badge {
    display: inline-block;
    min-width: 2rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.72rem;
    line-height: 1;
}
.badge-ok {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.badge-partial {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}
.badge-none {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* Matrix Cells */
.mkm-matrix-cell {
    text-align: center;
    cursor: pointer;
    transition: background-color 0.15s;
}
.mkm-cell-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    margin: 0 auto;
    border-radius: 6px;
    transition: transform 0.15s;
}
.mkm-matrix-cell:hover .mkm-cell-indicator {
    transform: scale(1.15);
}
.cell-granted .icon-granted {
    color: #10b981;
    font-size: 1rem;
    font-weight: 900;
}
.cell-granted {
    background: #f0fdf4 !important;
}
.cell-missing .icon-missing {
    color: #cbd5e1;
    font-weight: 700;
    font-size: 0.85rem;
}

/* Crosshair Hovering (Row + Column Highlighting) */
.mkm-matrix-table tbody .mkm-row.is-row-hovered td,
.mkm-matrix-table tbody .mkm-row:hover td {
    background: #f8fafc;
}
.mkm-matrix-table tbody .mkm-row.is-row-hovered .cell-granted,
.mkm-matrix-table tbody .mkm-row:hover .cell-granted {
    background: #dcfce7 !important;
}
.mkm-matrix-table td.is-column-hovered {
    background: #f5f3ff !important;
}
.mkm-matrix-table td.cell-granted.is-column-hovered {
    background: #d1fae5 !important;
}
.mkm-matrix-cell.is-cell-focused {
    background: #ede9fe !important;
    outline: 2px solid #818cf8;
    outline-offset: -2px;
}
.mkm-matrix-cell.is-cell-filtered-target {
    border-left: 2px solid #818cf8;
    border-right: 2px solid #818cf8;
}

/* 6. Pagination Bar */
.mkm-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

/* 7. Enhanced Modal Dialog Styling */
:deep(.mkm-detail-dialog) {
    border-radius: 16px;
    overflow: hidden;
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
:deep(.mkm-detail-dialog .el-dialog__header) {
    margin-right: 0;
    padding: 1.15rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    color: #0f172a;
    background: #f8fafc;
}
:deep(.mkm-detail-dialog .el-dialog__body) {
    padding: 1.25rem 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
}
:deep(.mkm-detail-dialog .el-dialog__footer) {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.mkm-modal-content {
    font-size: 0.85rem;
}
.mkm-modal-user-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.15rem;
}
.badge-modal-tag {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
}
.tag-indigo {
    background: #e0e7ff;
    color: #4338ca;
}
.tag-slate {
    background: #e2e8f0;
    color: #475569;
}
.mkm-modal-progress-bar {
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.mkm-modal-progress-fill {
    height: 100%;
    transition: width 0.3s ease;
}
.fill-emerald {
    background: #10b981;
}
.fill-amber {
    background: #f59e0b;
}
.fill-rose {
    background: #f43f5e;
}

/* Modal Toolbar & Tabs */
.mkm-modal-toolbar {
    background: #fff;
}
.mkm-modal-search :deep(.el-input__wrapper) {
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.mkm-modal-filter-tabs {
    display: flex;
    background: #f1f5f9;
    border-radius: 8px;
    padding: 2px;
    gap: 2px;
}
.mkm-modal-filter-btn {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.25rem 0.45rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    transition: all 0.15s;
    text-align: center;
}
.mkm-modal-filter-btn.active {
    background: #fff;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}
.mkm-modal-filter-btn.btn-granted.active {
    color: #15803d;
}
.mkm-modal-filter-btn.btn-missing.active {
    color: #b45309;
}

/* Modal Group Cards */
.mkm-modal-group-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.mkm-modal-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    padding: 0.5rem 0.85rem;
    border-bottom: 1px solid #e2e8f0;
}
.mkm-group-pill {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #334155;
    background: #e2e8f0;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}
.badge-modal-group-count {
    font-size: 0.7rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
}
.badge-modal-group-count.is-all-granted {
    color: #15803d;
    background: #dcfce7;
}

/* Modal Group Grid */
.mkm-modal-group-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1px;
    background: #f1f5f9;
}
.mkm-modal-menu-item {
    background: #fff;
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    transition: background 0.15s;
}
.mkm-modal-menu-item.is-granted {
    background: #fafdfb;
}
.mkm-modal-menu-item:hover {
    background: #f8fafc;
}
.mkm-modal-menu-title {
    font-size: 0.78rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.25;
}
.mkm-modal-menu-code {
    font-size: 0.65rem;
    color: #94a3b8;
}
.badge-modal-status {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    white-space: nowrap;
    flex-shrink: 0;
}
.status-granted {
    background: #dcfce7;
    color: #15803d;
}
.status-missing {
    background: #f1f5f9;
    color: #94a3b8;
}

/* Animations & Spinners */
.mkm-spin {
    display: inline-block;
    animation: mkm-spin-anim 0.8s linear infinite;
}
@keyframes mkm-spin-anim {
    to {
        transform: rotate(360deg);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .mkm-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .mkm-header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    .mkm-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .mkm-th-sticky-karyawan {
        min-width: 180px;
        max-width: 200px;
    }
    .mkm-th-sticky-status {
        left: 180px;
    }
    .mkm-modal-group-grid {
        grid-template-columns: 1fr;
    }
}
</style>
