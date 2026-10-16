<template>
    <div class="kpi-enf-container">
        <HeaderKpi
            title="KPI Enforcement & Monitoring"
            subtitle="Pantau pengisian KPI seluruh karyawan & eksekusi auto-cap massal per periode"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />

        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs
                    :tabs="tabs"
                    :activeTab="activeTab"
                    contentPadding="0rem"
                    :headerBgColor="false"
                    @tab-change="handleTabChange"
                >
                    <!-- ============ TAB 1: MONITORING ============ -->
                    <template #monitoring>
                        <div class="kpi-enf-panel" style="padding-top: 0rem">
                            <ApprovalTabs
                                layout="sidebar"
                                title="Monitoring"
                                :tabs="monitorSubTabs"
                                :activeTab="monitorView"
                                contentPadding="0rem"
                                @tab-change="setMonitorView"
                            >
                                <template #submission>
                                    <div class="stitch-card p-3">
                                        <div class="panel-toolbar mb-3">
                                            <div>
                                                <h5 class="text-dark fw-bold m-0" style="font-size: 0.95rem">
                                                    <i class="bi bi-list-check me-2 text-indigo"></i>KPI Submission
                                                    Status
                                                </h5>
                                                <p class="small text-muted m-0" style="font-size: 0.75rem">
                                                    Daftar status pengisian dan persetujuan KPI karyawan
                                                </p>
                                            </div>
                                            <div class="d-flex gap-2 align-items-end flex-wrap">
                                                <div class="sched-select">
                                                    <small>Tahun Periode</small>
                                                    <select
                                                        v-model="selectedSubmissionYear"
                                                        class="form-select gf-select-new"
                                                        @change="onSubmissionYearChange"
                                                    >
                                                        <option value="" disabled>Pilih tahun…</option>
                                                        <option
                                                            v-for="y in submissionYears"
                                                            :key="y.value"
                                                            :value="y.value"
                                                        >
                                                            {{ y.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="sched-select">
                                                    <small>Penjadwalan (opsional)</small>
                                                    <select
                                                        v-model="selectedSubmissionSchedule"
                                                        class="form-select gf-select-new"
                                                        @change="onMonitorScheduleChange"
                                                    >
                                                        <option value="">Semua penjadwalan</option>
                                                        <option
                                                            v-for="s in submissionSchedulesInScope"
                                                            :key="s.value"
                                                            :value="s.value"
                                                        >
                                                            {{ s.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="search-box">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        v-model="monitorSearch"
                                                        type="text"
                                                        class="form-control"
                                                        placeholder="Cari nama / kode karyawan…"
                                                        @keyup.enter="reloadMonitor"
                                                    />
                                                </div>
                                                <button class="stitch-btn ghost" @click="reloadMonitor">
                                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Filter isi monitoring (status + divisi/dept/level/jabatan/lokasi) -->
                                        <div class="filter-card stitch-card mb-3 p-3 bg-white">
                                            <ModernFilter
                                                :config="monitorFilterConfig"
                                                :filters="monitorFilters"
                                                @filters-update="handleMonitorFiltersUpdate"
                                            />
                                        </div>

                                        <!-- Toolbar pilihan pembatalan (muncul saat ada yang dicentang) -->
                                        <CancelToolbar
                                            :count="cancelSelectedIds.length"
                                            :approved="cancelApprovedCount"
                                            :hidden="cancelHiddenCount"
                                            @clear="clearCancelSelection"
                                            @cancel="openCancelBulk"
                                        />

                                        <!-- Submission table -->
                                        <div
                                            class="table-wrap border rounded-3 bg-white overflow-hidden shadow-xs border-0"
                                        >
                                            <ModernDataTable
                                                :data="submissionRows"
                                                :clickable="true"
                                                @row-click="openSubmissionDetail"
                                                :columns="submissionColumns"
                                                :mobile-columns="submissionMobileColumns"
                                                :loading="isLoadingMonitor"
                                                :show-pagination="false"
                                                :show-actions="false"
                                                card-title-key="nama"
                                                card-subtitle-key="kode_karyawan"
                                                card-status-key="status"
                                                empty-message="Tidak ada data submission untuk periode ini."
                                            >
                                                <!-- Selection & aksi batal -->
                                                <template #header-selection>
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :checked="cancelAllChecked"
                                                        :disabled="!cancellableRows.length || isSelectingAll"
                                                        :title="
                                                            cancelSelectedIds.length
                                                                ? 'Bersihkan seluruh pilihan'
                                                                : 'Pilih SEMUA transaksi yang cocok filter ini (lintas halaman)'
                                                        "
                                                        @click.stop
                                                        @change="toggleCancelSelectAll"
                                                    />
                                                </template>
                                                <template #column-selection="{ item }">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :checked="isCancelChecked(item)"
                                                        :disabled="cancelRowId(item) == null"
                                                        :title="
                                                            cancelRowId(item) == null
                                                                ? cancelRowReason(item)
                                                                : 'Pilih untuk dibatalkan'
                                                        "
                                                        @click.stop
                                                        @change="toggleCancelOne(item)"
                                                    />
                                                </template>
                                                <template #column-aksi="{ item }">
                                                    <button
                                                        v-if="cancelRowId(item) != null"
                                                        class="row-cancel-btn"
                                                        title="Batalkan transaksi ini"
                                                        @click.stop="openCancelSingle(item)"
                                                    >
                                                        <i class="bi bi-x-octagon"></i>
                                                    </button>
                                                    <button
                                                        v-else-if="draftCancelId(item) != null"
                                                        class="row-cancel-btn"
                                                        title="Batalkan draft ini"
                                                        :aria-label="`Batalkan draft ${item.nama}`"
                                                        @click.stop="openCancelDraft(item)"
                                                    >
                                                        <i class="bi bi-x-octagon"></i>
                                                    </button>
                                                </template>

                                                <!-- Desktop slots -->
                                                <template #column-karyawan="{ item }">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <div
                                                            class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                            style="
                                                                width: 30px;
                                                                height: 30px;
                                                                font-size: 0.7rem;
                                                                flex-shrink: 0;
                                                            "
                                                        >
                                                            {{ getUserInitials(item.nama) }}
                                                        </div>
                                                        <div class="emp-cell">
                                                            <span class="emp-name" style="font-size: 0.82rem">{{
                                                                item.nama
                                                            }}</span>
                                                            <span
                                                                class="emp-code d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >{{ item.kode_karyawan }}</span
                                                            >
                                                            <span
                                                                v-if="item.tgl_masuk"
                                                                class="emp-join d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >Masuk: {{ tglMasuk(item.tgl_masuk) }}</span
                                                            >
                                                            <span v-if="item.cancellation" class="cancel-trace-badge">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Pernah
                                                                dibatalkan ×{{ item.cancellation.count }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template #column-divisi_departemen="{ item }">
                                                    <span
                                                        class="d-block text-dark fw-medium"
                                                        style="font-size: 0.8rem"
                                                        >{{ item.divisi || '-' }}</span
                                                    >
                                                    <small class="text-muted" style="font-size: 0.7rem">{{
                                                        item.departemen || '-'
                                                    }}</small>
                                                </template>

                                                <template #column-status_approval="{ item }">
                                                    <span
                                                        class="status-pill"
                                                        :class="statusPillClass(item.status)"
                                                        style="font-size: 0.68rem; padding: 0.18rem 0.5rem"
                                                        >{{ item.status_label }}</span
                                                    >
                                                    <small
                                                        v-if="item.jadwal_nama"
                                                        class="d-block text-muted mt-1"
                                                        style="font-size: 0.62rem; line-height: 1.2"
                                                    >
                                                        <i class="bi bi-calendar-check me-1"></i>{{ item.jadwal_nama }}
                                                    </small>
                                                </template>

                                                <!-- Mobile card custom header & status -->
                                                <template #card-header="{ item }">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <input
                                                            type="checkbox"
                                                            class="form-check-input me-1"
                                                            :checked="isCancelChecked(item)"
                                                            :disabled="cancelRowId(item) == null"
                                                            @click.stop
                                                            @change="toggleCancelOne(item)"
                                                        />
                                                        <div
                                                            class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                            style="
                                                                width: 30px;
                                                                height: 30px;
                                                                font-size: 0.7rem;
                                                                flex-shrink: 0;
                                                            "
                                                        >
                                                            {{ getUserInitials(item.nama) }}
                                                        </div>
                                                        <div class="emp-cell text-start">
                                                            <span
                                                                class="emp-name d-block text-dark fw-bold"
                                                                style="font-size: 0.82rem"
                                                                >{{ item.nama }}</span
                                                            >
                                                            <span
                                                                class="emp-code d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >{{ item.kode_karyawan }}</span
                                                            >
                                                            <span
                                                                v-if="item.tgl_masuk"
                                                                class="emp-join d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >Masuk: {{ tglMasuk(item.tgl_masuk) }}</span
                                                            >
                                                            <span v-if="item.cancellation" class="cancel-trace-badge">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Pernah
                                                                dibatalkan ×{{ item.cancellation.count }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template #card-status="{ item }">
                                                    {{ item.status_label }}
                                                </template>

                                                <!-- Custom mobile grid formatters -->
                                                <template #mobile-divisi_departemen="{ item }">
                                                    <span
                                                        class="d-block text-dark fw-medium"
                                                        style="font-size: 0.8rem"
                                                        >{{ item.divisi || '-' }}</span
                                                    >
                                                    <small class="text-muted" style="font-size: 0.7rem">{{
                                                        item.departemen || '-'
                                                    }}</small>
                                                </template>
                                            </ModernDataTable>
                                            <Pagination :meta="monitorMeta" @page="goMonitorPage" />
                                        </div>
                                    </div>
                                </template>

                                <template #performance>
                                    <div class="stitch-card p-3">
                                        <div class="panel-toolbar mb-3">
                                            <div>
                                                <h5 class="text-dark fw-bold m-0" style="font-size: 0.95rem">
                                                    <i class="bi bi-graph-up me-2 text-indigo"></i>Performance Tracking
                                                </h5>
                                                <p class="small text-muted m-0" style="font-size: 0.75rem">
                                                    Pantau nilai KPI akhir, capaian prestasi, serta status cap karyawan
                                                </p>
                                            </div>
                                            <div class="d-flex gap-2 align-items-end flex-wrap">
                                                <div class="sched-select">
                                                    <small>Periode Bulan</small>
                                                    <select
                                                        v-model="selectedPenilaianPeriode"
                                                        class="form-select gf-select-new"
                                                        @change="onPenilaianPeriodeChange"
                                                    >
                                                        <option value="" disabled>Pilih periode…</option>
                                                        <option
                                                            v-for="p in penilaianPeriods"
                                                            :key="p.value"
                                                            :value="p.value"
                                                        >
                                                            {{ p.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <!-- Cakupan milik Performance Tracking: backend-nya
                                                     (getPerformanceTracking, mode=ytd) hanya ada di
                                                     tab ini. Dulu dropdown ini tersasar ke toolbar
                                                     KPI Submission, tempat ia tidak berpengaruh. -->
                                                <div class="sched-select">
                                                    <small>Cakupan</small>
                                                    <select
                                                        v-model="penilaianMode"
                                                        class="form-select gf-select-new"
                                                        @change="onPenilaianModeChange"
                                                    >
                                                        <option value="bulan">Bulanan</option>
                                                        <option value="ytd">YTD (Jan s/d bulan terpilih)</option>
                                                    </select>
                                                </div>
                                                <div class="sched-select">
                                                    <small>Penjadwalan (opsional)</small>
                                                    <select
                                                        v-model="selectedPenilaianSchedule"
                                                        class="form-select gf-select-new"
                                                        @change="onMonitorScheduleChange"
                                                    >
                                                        <option value="">Semua penjadwalan</option>
                                                        <option
                                                            v-for="s in penilaianSchedulesInScope"
                                                            :key="s.value"
                                                            :value="s.value"
                                                        >
                                                            {{ s.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="search-box">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        v-model="monitorSearch"
                                                        type="text"
                                                        class="form-control"
                                                        placeholder="Cari nama / kode karyawan…"
                                                        @keyup.enter="reloadMonitor"
                                                    />
                                                </div>
                                                <button class="stitch-btn ghost" @click="reloadMonitor">
                                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Filter isi monitoring -->
                                        <div class="filter-card stitch-card mb-3 p-3 bg-white">
                                            <ModernFilter
                                                :config="monitorFilterConfig"
                                                :filters="monitorFilters"
                                                @filters-update="handleMonitorFiltersUpdate"
                                            />
                                        </div>

                                        <!-- Toolbar pilihan pembatalan.
                                             Disembunyikan pada mode YTD: barisnya
                                             agregat Jan..bulan terpilih, jadi tak
                                             ada satu transaksi pun untuk dibatalkan. -->
                                        <CancelToolbar
                                            v-if="pembatalanBerlaku"
                                            :count="cancelSelectedIds.length"
                                            :approved="cancelApprovedCount"
                                            :hidden="cancelHiddenCount"
                                            @clear="clearCancelSelection"
                                            @cancel="openCancelBulk"
                                        />
                                        <p v-else class="text-muted small mb-2">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Mode YTD menampilkan akumulasi Januari sampai bulan terpilih. Pembatalan
                                            transaksi dilakukan di cakupan
                                            <b>Bulanan</b>.
                                        </p>

                                        <!-- Performance tracking table -->
                                        <div
                                            class="table-wrap border rounded-3 bg-white overflow-hidden shadow-xs border-0"
                                        >
                                            <ModernDataTable
                                                :data="performanceRows"
                                                :clickable="true"
                                                @row-click="openPerformanceDetail"
                                                :columns="performanceColumns"
                                                :mobile-columns="performanceMobileColumns"
                                                :loading="isLoadingMonitor"
                                                :show-pagination="false"
                                                :show-actions="false"
                                                card-title-key="nama"
                                                card-subtitle-key="kode_karyawan"
                                                card-status-key="status_card"
                                                empty-message="Belum ada performance terisi untuk periode ini."
                                            >
                                                <!-- Selection & aksi batal -->
                                                <template #header-selection>
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :checked="cancelAllChecked"
                                                        :disabled="!cancellableRows.length || isSelectingAll"
                                                        :title="
                                                            cancelSelectedIds.length
                                                                ? 'Bersihkan seluruh pilihan'
                                                                : 'Pilih SEMUA transaksi yang cocok filter ini (lintas halaman)'
                                                        "
                                                        @click.stop
                                                        @change="toggleCancelSelectAll"
                                                    />
                                                </template>
                                                <template #column-selection="{ item }">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :checked="isCancelChecked(item)"
                                                        :disabled="cancelRowId(item) == null"
                                                        :title="
                                                            cancelRowId(item) == null
                                                                ? cancelRowReason(item)
                                                                : 'Pilih untuk dibatalkan'
                                                        "
                                                        @click.stop
                                                        @change="toggleCancelOne(item)"
                                                    />
                                                </template>
                                                <template #column-aksi="{ item }">
                                                    <button
                                                        v-if="cancelRowId(item) != null"
                                                        class="row-cancel-btn"
                                                        title="Batalkan transaksi ini"
                                                        @click.stop="openCancelSingle(item)"
                                                    >
                                                        <i class="bi bi-x-octagon"></i>
                                                    </button>
                                                    <button
                                                        v-else-if="draftCancelId(item) != null"
                                                        class="row-cancel-btn"
                                                        title="Batalkan draft ini"
                                                        :aria-label="`Batalkan draft ${item.nama}`"
                                                        @click.stop="openCancelDraft(item)"
                                                    >
                                                        <i class="bi bi-x-octagon"></i>
                                                    </button>
                                                </template>

                                                <!-- Desktop slots -->
                                                <template #column-karyawan="{ item }">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <div
                                                            class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                            style="
                                                                width: 30px;
                                                                height: 30px;
                                                                font-size: 0.7rem;
                                                                flex-shrink: 0;
                                                            "
                                                        >
                                                            {{ getUserInitials(item.nama) }}
                                                        </div>
                                                        <div class="emp-cell">
                                                            <span class="emp-name" style="font-size: 0.82rem">{{
                                                                item.nama
                                                            }}</span>
                                                            <span
                                                                class="emp-code d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >{{ item.kode_karyawan }}</span
                                                            >
                                                            <span
                                                                v-if="item.tgl_masuk"
                                                                class="emp-join d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >Masuk: {{ tglMasuk(item.tgl_masuk) }}</span
                                                            >
                                                            <span v-if="item.cancellation" class="cancel-trace-badge">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Pernah
                                                                dibatalkan ×{{ item.cancellation.count }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- "Total Score" (judul) = DB total_score_achievement -->
                                                <template #column-score="{ item }">
                                                    <span style="font-size: 0.8rem">{{
                                                        item.id_update_performance || item.is_draft
                                                            ? item.total_score_achievement
                                                            : '—'
                                                    }}</span>
                                                </template>

                                                <!-- "Total Achievement" (diutamakan) = DB total_score (yang di-cap) -->
                                                <template #column-achievement="{ item }">
                                                    <template v-if="item.id_update_performance || item.is_draft">
                                                        <strong style="font-size: 0.85rem; color: #4338ca">{{
                                                            item.total_score
                                                        }}</strong>
                                                        <small
                                                            v-if="item.is_capped"
                                                            class="text-muted d-block"
                                                            style="font-size: 0.7rem"
                                                            >raw: {{ item.total_score_raw }}</small
                                                        >
                                                    </template>
                                                    <span v-else class="text-muted">—</span>
                                                </template>

                                                <template #column-cap="{ item }">
                                                    <span
                                                        v-if="item.is_capped"
                                                        class="status-pill is-capped"
                                                        style="font-size: 0.68rem; padding: 0.18rem 0.5rem"
                                                    >
                                                        Capped {{ item.cap_min }}–{{ item.cap_max }}
                                                    </span>
                                                    <span v-else class="text-muted" style="font-size: 0.8rem">—</span>
                                                </template>

                                                <template #column-status="{ item }">
                                                    <span
                                                        class="status-pill"
                                                        :class="statusPillClass(item.status)"
                                                        style="font-size: 0.68rem; padding: 0.18rem 0.5rem"
                                                    >
                                                        {{ item.status_label }}
                                                    </span>
                                                    <small
                                                        v-if="!item.approved && item.current_approver"
                                                        class="d-block text-muted mt-1"
                                                        style="font-size: 0.68rem; line-height: 1.3"
                                                    >
                                                        <i class="bi bi-person-check me-1 text-indigo"></i>
                                                        {{ item.current_approver.nama }}
                                                        <span class="text-secondary"
                                                            >· Lvl {{ item.current_approver.level }}/{{
                                                                item.current_approver.total
                                                            }}</span
                                                        >
                                                    </small>
                                                    <small
                                                        v-if="item.jadwal_nama"
                                                        class="d-block text-muted mt-1"
                                                        style="font-size: 0.62rem; line-height: 1.2"
                                                    >
                                                        <i class="bi bi-calendar-check me-1"></i>{{ item.jadwal_nama }}
                                                    </small>
                                                </template>

                                                <!-- Mobile card custom header & status -->
                                                <template #card-header="{ item }">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <input
                                                            type="checkbox"
                                                            class="form-check-input me-1"
                                                            :checked="isCancelChecked(item)"
                                                            :disabled="cancelRowId(item) == null"
                                                            @click.stop
                                                            @change="toggleCancelOne(item)"
                                                        />
                                                        <div
                                                            class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                            style="
                                                                width: 30px;
                                                                height: 30px;
                                                                font-size: 0.7rem;
                                                                flex-shrink: 0;
                                                            "
                                                        >
                                                            {{ getUserInitials(item.nama) }}
                                                        </div>
                                                        <div class="emp-cell text-start">
                                                            <span
                                                                class="emp-name d-block text-dark fw-bold"
                                                                style="font-size: 0.82rem"
                                                                >{{ item.nama }}</span
                                                            >
                                                            <span
                                                                class="emp-code d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >{{ item.kode_karyawan }}</span
                                                            >
                                                            <span
                                                                v-if="item.tgl_masuk"
                                                                class="emp-join d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >Masuk: {{ tglMasuk(item.tgl_masuk) }}</span
                                                            >
                                                            <span v-if="item.cancellation" class="cancel-trace-badge">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Pernah
                                                                dibatalkan ×{{ item.cancellation.count }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template #card-status="{ item }">
                                                    {{ item.approved ? 'Disetujui' : item.status_pengajuan || '-' }}
                                                    <small
                                                        v-if="!item.approved && item.current_approver"
                                                        class="d-block"
                                                        style="font-size: 0.62rem; opacity: 0.85"
                                                    >
                                                        {{ item.current_approver.nama }} (Lvl
                                                        {{ item.current_approver.level }}/{{
                                                            item.current_approver.total
                                                        }})
                                                    </small>
                                                </template>

                                                <!-- Custom mobile grid formatters -->
                                                <!-- "Total Achievement" (diutamakan) = DB total_score -->
                                                <template #mobile-achievement="{ item }">
                                                    <strong style="font-size: 0.85rem; color: #4338ca">{{
                                                        item.id_update_performance || item.is_draft
                                                            ? item.total_score
                                                            : '—'
                                                    }}</strong>
                                                    <small
                                                        v-if="item.is_capped"
                                                        class="text-muted d-block"
                                                        style="font-size: 0.7rem"
                                                        >raw: {{ item.total_score_raw }}</small
                                                    >
                                                </template>

                                                <!-- "Total Score" = DB total_score_achievement -->
                                                <template #mobile-score="{ item }">
                                                    <span style="font-size: 0.8rem">{{
                                                        item.id_update_performance || item.is_draft
                                                            ? item.total_score_achievement
                                                            : '—'
                                                    }}</span>
                                                </template>

                                                <template #mobile-cap="{ item }">
                                                    <span
                                                        v-if="item.is_capped"
                                                        class="status-pill is-capped d-inline-block"
                                                        style="font-size: 0.68rem; padding: 0.18rem 0.5rem"
                                                    >
                                                        Capped {{ item.cap_min }}–{{ item.cap_max }}
                                                    </span>
                                                    <span v-else class="text-muted" style="font-size: 0.8rem">—</span>
                                                </template>
                                            </ModernDataTable>
                                            <Pagination :meta="monitorMeta" @page="goMonitorPage" />
                                        </div>
                                    </div>
                                </template>

                                <!-- ====== SUB-TAB: EXPORT ====== -->
                                <template #export>
                                    <div class="stitch-card p-3">
                                        <div class="panel-toolbar mb-3">
                                            <div>
                                                <h5 class="text-dark fw-bold m-0" style="font-size: 0.95rem">
                                                    <i class="bi bi-download me-2 text-indigo"></i>Export Data
                                                    Monitoring
                                                </h5>
                                                <p class="small text-muted m-0" style="font-size: 0.75rem">
                                                    Pilih jenis, periode, filter, lalu centang karyawan untuk di-export
                                                    (Excel / PDF). Proses berjalan di background.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Konfigurasi export -->
                                        <div class="filter-card stitch-card mb-3 p-3 bg-white">
                                            <div class="d-flex gap-3 align-items-end flex-wrap">
                                                <div class="sched-select">
                                                    <small>Jenis Data</small>
                                                    <select
                                                        v-model="exportJenis"
                                                        class="form-select gf-select-new"
                                                        @change="onExportJenisChange"
                                                    >
                                                        <option value="parameter">KPI Parameter (Pengajuan)</option>
                                                        <option value="performance">Update Performance</option>
                                                    </select>
                                                </div>
                                                <div class="sched-select">
                                                    <small>{{
                                                        exportJenis === 'parameter' ? 'Tahun' : 'Periode Bulan'
                                                    }}</small>
                                                    <select
                                                        v-model="exportScope"
                                                        class="form-select gf-select-new"
                                                        @change="loadExportUniverse()"
                                                    >
                                                        <option value="" disabled>Pilih…</option>
                                                        <option
                                                            v-for="o in exportScopeOptions"
                                                            :key="o.value"
                                                            :value="o.value"
                                                        >
                                                            {{ o.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div v-if="exportJenis === 'performance'" class="sched-select">
                                                    <small>Cakupan</small>
                                                    <select
                                                        v-model="exportMode"
                                                        class="form-select gf-select-new"
                                                        @change="loadExportUniverse()"
                                                    >
                                                        <option value="bulan">Bulan terpilih</option>
                                                        <option value="ytd">Akumulasi Jan s/d bulan terpilih</option>
                                                    </select>
                                                </div>
                                                <div class="sched-select">
                                                    <small>Format</small>
                                                    <select v-model="exportFormat" class="form-select gf-select-new">
                                                        <option value="excel">Excel (.xlsx)</option>
                                                        <option value="pdf">PDF (.pdf)</option>
                                                    </select>
                                                </div>
                                                <div class="sched-select flex-grow-1" style="min-width: 200px">
                                                    <small>Nama Export (opsional)</small>
                                                    <input
                                                        v-model="exportNama"
                                                        type="text"
                                                        maxlength="120"
                                                        class="form-control gf-select-new"
                                                        placeholder="mis. KPI Divisi IT Q2"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Filter isi -->
                                        <div class="filter-card stitch-card mb-3 p-3 bg-white">
                                            <ModernFilter
                                                :config="exportFilterConfig"
                                                :filters="exportFilters"
                                                @filters-update="handleExportFiltersUpdate"
                                            />
                                        </div>

                                        <!-- Toolbar pilih + submit -->
                                        <div
                                            class="selection-toolbar-new d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2"
                                        >
                                            <span
                                                class="badge bg-indigo-soft text-indigo px-2.5 py-1.5 fw-bold"
                                                style="font-size: 0.7rem; border: 1px solid #c7d2fe"
                                            >
                                                <template v-if="exportSelectAllMode">
                                                    <strong>Semua</strong> karyawan yang punya transaksi
                                                </template>
                                                <template v-else>
                                                    <strong>{{ exportSelectedIds.length }}</strong> dari
                                                    {{ exportMeta.total_data }} dipilih
                                                </template>
                                            </span>
                                            <button
                                                class="stitch-btn primary"
                                                :disabled="!exportHasSelection || isRequestingExport"
                                                @click="requestExport"
                                            >
                                                <i class="bi bi-file-earmark-arrow-down"></i>
                                                {{ isRequestingExport ? 'Menyiapkan…' : 'Siapkan Export' }}
                                            </button>
                                        </div>

                                        <!-- Tabel karyawan (universe) -->
                                        <div
                                            class="table-wrap border rounded-3 bg-white overflow-hidden shadow-xs border-0"
                                        >
                                            <ModernDataTable
                                                :data="exportRows"
                                                :columns="exportColumns"
                                                :mobile-columns="exportMobileColumns"
                                                :loading="isLoadingExport"
                                                :show-pagination="false"
                                                :show-actions="false"
                                                row-key="kode_karyawan"
                                                empty-message="Tidak ada karyawan untuk scope & filter ini."
                                            >
                                                <template #header-selection>
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :checked="exportAllChecked"
                                                        @change="toggleExportSelectAll"
                                                    />
                                                </template>
                                                <template #column-selection="{ item }">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :checked="isExportChecked(item)"
                                                        :disabled="!exportRowSelectable(item)"
                                                        @change="toggleExportOne(item)"
                                                    />
                                                </template>
                                                <template #column-karyawan="{ item }">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <div
                                                            class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                            style="
                                                                width: 30px;
                                                                height: 30px;
                                                                font-size: 0.7rem;
                                                                flex-shrink: 0;
                                                            "
                                                        >
                                                            {{ getUserInitials(item.nama) }}
                                                        </div>
                                                        <div class="emp-cell">
                                                            <span class="emp-name" style="font-size: 0.82rem">{{
                                                                item.nama
                                                            }}</span>
                                                            <span
                                                                class="emp-code d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >{{ item.kode_karyawan }}</span
                                                            >
                                                            <span
                                                                v-if="item.tgl_masuk"
                                                                class="emp-join d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >Masuk: {{ tglMasuk(item.tgl_masuk) }}</span
                                                            >
                                                        </div>
                                                    </div>
                                                </template>
                                                <template #column-divisi_departemen="{ item }">
                                                    <span
                                                        class="d-block text-dark fw-medium"
                                                        style="font-size: 0.8rem"
                                                        >{{ item.divisi || '-' }}</span
                                                    >
                                                    <small class="text-muted" style="font-size: 0.7rem">{{
                                                        item.departemen || '-'
                                                    }}</small>
                                                </template>
                                                <template #column-status="{ item }">
                                                    <span
                                                        class="status-pill"
                                                        :class="statusPillClass(item.status)"
                                                        style="font-size: 0.68rem; padding: 0.18rem 0.5rem"
                                                        >{{ item.status_label }}</span
                                                    >
                                                </template>
                                            </ModernDataTable>
                                            <Pagination :meta="exportMeta" @page="goExportPage" />
                                        </div>
                                    </div>
                                </template>

                                <!-- ====== SUB-TAB: RIWAYAT PEMBATALAN ====== -->
                                <template #cancellation>
                                    <div class="stitch-card p-3">
                                        <div class="panel-toolbar mb-3">
                                            <div>
                                                <h5 class="text-dark fw-bold m-0" style="font-size: 0.95rem">
                                                    <i class="bi bi-clock-history me-2 text-indigo"></i>Riwayat
                                                    Pembatalan
                                                </h5>
                                                <p class="small text-muted m-0" style="font-size: 0.75rem">
                                                    Buku besar audit — setiap transaksi yang dibatalkan tercatat
                                                    permanen, termasuk yang ikut batal karena cascade.
                                                    <b>Klik baris</b> untuk melihat isi transaksi yang dibatalkan.
                                                </p>
                                            </div>
                                            <div class="d-flex gap-2 align-items-end flex-wrap">
                                                <div class="search-box">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        v-model="historyFilters.search"
                                                        type="text"
                                                        class="form-control"
                                                        placeholder="Cari nama / no. transaksi / batch…"
                                                        @keyup.enter="fetchHistory(1)"
                                                    />
                                                </div>
                                                <button class="stitch-btn ghost" @click="fetchHistory(1)">
                                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                                </button>
                                            </div>
                                        </div>

                                        <div class="filter-card stitch-card mb-3 p-3 bg-white">
                                            <ModernFilter
                                                :config="historyFilterConfig"
                                                :filters="historyFilters"
                                                @filters-update="handleHistoryFiltersUpdate"
                                            />
                                        </div>

                                        <div
                                            class="table-wrap border rounded-3 bg-white overflow-hidden shadow-xs border-0"
                                        >
                                            <ModernDataTable
                                                :data="historyRows"
                                                :columns="historyColumns"
                                                :mobile-columns="historyMobileColumns"
                                                :loading="isLoadingHistory"
                                                :clickable="true"
                                                @row-click="openHistoryDetail"
                                                :show-pagination="false"
                                                :show-actions="false"
                                                card-title-key="nama"
                                                card-subtitle-key="kode_karyawan"
                                                empty-message="Belum ada transaksi yang dibatalkan."
                                            >
                                                <template #column-waktu="{ item }">
                                                    <span style="font-size: 0.78rem">{{
                                                        formatDateTime(item.pada)
                                                    }}</span>
                                                    <small class="d-block text-muted mono" style="font-size: 0.62rem">{{
                                                        item.batch_id
                                                    }}</small>
                                                </template>

                                                <template #column-karyawan="{ item }">
                                                    <div class="d-flex align-items-center gap-2.5">
                                                        <div
                                                            class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                            style="
                                                                width: 30px;
                                                                height: 30px;
                                                                font-size: 0.7rem;
                                                                flex-shrink: 0;
                                                            "
                                                        >
                                                            {{ getUserInitials(item.nama) }}
                                                        </div>
                                                        <div class="emp-cell">
                                                            <span class="emp-name" style="font-size: 0.82rem">{{
                                                                item.nama
                                                            }}</span>
                                                            <span
                                                                class="emp-code d-block text-muted"
                                                                style="font-size: 0.7rem"
                                                                >{{ item.kode_karyawan }}</span
                                                            >
                                                        </div>
                                                    </div>
                                                </template>

                                                <template #column-jenis="{ item }">
                                                    <span class="domain-pill" :class="'d-' + item.domain">{{
                                                        domainLabel(item.domain)
                                                    }}</span>
                                                    <small
                                                        v-if="item.source === 'cascade'"
                                                        class="d-block text-muted"
                                                        style="font-size: 0.62rem"
                                                    >
                                                        <i class="bi bi-arrow-return-right"></i> ikut batal (cascade)
                                                    </small>
                                                </template>

                                                <template #column-no_transaksi="{ item }">
                                                    <span class="mono" style="font-size: 0.72rem">{{
                                                        item.no_transaksi || '—'
                                                    }}</span>
                                                </template>

                                                <template #column-status_sebelum="{ item }">
                                                    <span
                                                        class="status-pill"
                                                        :class="statusPillClass(item.status_sebelum)"
                                                        style="font-size: 0.66rem; padding: 0.18rem 0.5rem"
                                                        >{{ statusSebelumLabel(item.status_sebelum) }}</span
                                                    >
                                                </template>

                                                <template #column-alasan="{ item }">
                                                    <span
                                                        class="reason-cell"
                                                        :title="item.alasan"
                                                        style="font-size: 0.75rem"
                                                        >{{ item.alasan }}</span
                                                    >
                                                </template>
                                            </ModernDataTable>
                                            <Pagination :meta="historyMeta" @page="goHistoryPage" />
                                        </div>
                                    </div>
                                </template>
                            </ApprovalTabs>
                        </div>
                    </template>

                    <!-- ============ TAB 2: ENFORCEMENT ============ -->
                    <template #enforcement>
                        <div class="kpi-enf-panel p-4">
                            <!-- Reusable Enhanced Modern Filter -->
                            <div class="filter-card stitch-card mb-3 p-3 bg-white">
                                <div
                                    class="filter-header mb-2.5 pb-2 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2"
                                >
                                    <span class="fw-bold text-dark text-xs" style="font-size: 0.8rem">
                                        <i class="bi bi-funnel-fill text-indigo me-1"></i> Filter Karyawan Belum Mengisi
                                        KPI
                                    </span>
                                    <div class="d-flex gap-2 align-items-end flex-wrap">
                                        <div class="sched-select">
                                            <small>Periode Bulan</small>
                                            <select
                                                v-model="selectedEnforcePeriode"
                                                class="form-select gf-select-new"
                                                @change="onEnforcePeriodeChange"
                                            >
                                                <option value="" disabled>Pilih periode…</option>
                                                <option v-for="p in penilaianPeriods" :key="p.value" :value="p.value">
                                                    {{ p.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="sched-select">
                                            <small>Target Penjadwalan</small>
                                            <select v-model="selectedEnforceSchedule" class="form-select gf-select-new">
                                                <option value="">Otomatis (sesuai jadwal user)</option>
                                                <option
                                                    v-for="s in enforceSchedulesInScope"
                                                    :key="s.value"
                                                    :value="s.value"
                                                >
                                                    {{ s.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <ModernFilter
                                    :config="filterConfig"
                                    :filters="filters"
                                    :resultsCount="unfilledMeta.total_data || 0"
                                    @filters-update="handleFiltersUpdate"
                                >
                                    <template #actions>
                                        <button
                                            class="stitch-btn primary"
                                            style="height: 32px; font-size: 0.78rem; padding: 0 0.85rem"
                                            @click="reloadUnfilled"
                                        >
                                            <i class="bi bi-arrow-clockwise"></i> Refresh
                                        </button>
                                    </template>
                                </ModernFilter>
                            </div>

                            <div class="selection-toolbar-new d-flex justify-content-between align-items-center mb-2">
                                <div class="selection-info">
                                    <!-- Hitungan dibandingkan dengan TOTAL yang cocok filter, bukan
                                         panjang halaman aktif: centang semua bekerja lintas halaman,
                                         jadi "12 dari 15" pada halaman berisi 15 baris akan menyesatkan. -->
                                    <span
                                        class="badge bg-indigo-soft text-indigo px-2.5 py-1.5 fs-7 fw-bold"
                                        style="font-size: 0.7rem; border: 1px solid #c7d2fe"
                                    >
                                        <strong>{{ selectedIds.length }}</strong> dari
                                        {{ unfilledMeta.total_data || 0 }} dipilih
                                    </span>
                                    <button
                                        v-if="selectedIds.length"
                                        class="selection-clear"
                                        type="button"
                                        @click="clearEnforceSelection"
                                    >
                                        Kosongkan pilihan
                                    </button>
                                    <span v-else class="text-muted ms-2 fw-semibold" style="font-size: 0.75rem">
                                        · Centang judul kolom untuk memilih seluruh hasil filter
                                    </span>
                                </div>
                                <button class="stitch-btn danger" :disabled="!selectedIds.length" @click="openPreview">
                                    <i class="bi bi-shield-exclamation"></i> Enforce Achievement Cap
                                </button>
                            </div>

                            <div class="table-wrap border rounded-3 bg-white overflow-hidden shadow-xs border-0">
                                <ModernDataTable
                                    :data="unfilledRows"
                                    :columns="unfilledColumns"
                                    :mobile-columns="unfilledMobileColumns"
                                    :loading="isLoadingUnfilled"
                                    :show-pagination="false"
                                    :show-actions="false"
                                    card-title-key="nama"
                                    card-subtitle-key="kode_karyawan"
                                    empty-message="Semua karyawan sudah mengisi KPI untuk periode & filter ini. 🎉"
                                    row-key="id_user"
                                >
                                    <!-- Selection checkbox header slot -->
                                    <template #header-selection>
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="allChecked"
                                            :indeterminate="selectionIndeterminate"
                                            :disabled="isSelectingAll"
                                            :title="
                                                allChecked
                                                    ? 'Kosongkan pilihan'
                                                    : 'Pilih seluruh karyawan yang cocok filter ini'
                                            "
                                            @change="toggleSelectAll"
                                        />
                                    </template>

                                    <!-- Selection checkbox column slot -->
                                    <template #column-selection="{ item }">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="isChecked(item.id_user)"
                                            @change="toggleOne(item.id_user)"
                                        />
                                    </template>

                                    <!-- Karyawan desktop slot -->
                                    <template #column-karyawan="{ item }">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div
                                                class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                style="width: 30px; height: 30px; font-size: 0.7rem; flex-shrink: 0"
                                            >
                                                {{ getUserInitials(item.nama) }}
                                            </div>
                                            <div class="emp-cell">
                                                <span class="emp-name" style="font-size: 0.82rem">{{ item.nama }}</span>
                                                <span class="emp-code d-block text-muted" style="font-size: 0.7rem">{{
                                                    item.kode_karyawan
                                                }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Divisi / Departemen desktop slot -->
                                    <template #column-divisi_departemen="{ item }">
                                        <span class="d-block text-dark fw-medium" style="font-size: 0.8rem">{{
                                            item.divisi || '-'
                                        }}</span>
                                        <small class="text-muted" style="font-size: 0.7rem">{{
                                            item.departemen || '-'
                                        }}</small>
                                    </template>

                                    <!-- Mobile card slots -->
                                    <template #card-header="{ item }">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <input
                                                type="checkbox"
                                                class="form-check-input me-1"
                                                :checked="isChecked(item.id_user)"
                                                @change="toggleOne(item.id_user)"
                                            />
                                            <div
                                                class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                                style="width: 30px; height: 30px; font-size: 0.7rem; flex-shrink: 0"
                                            >
                                                {{ getUserInitials(item.nama) }}
                                            </div>
                                            <div class="emp-cell text-start">
                                                <span
                                                    class="emp-name d-block text-dark fw-bold"
                                                    style="font-size: 0.82rem"
                                                    >{{ item.nama }}</span
                                                >
                                                <span class="emp-code d-block text-muted" style="font-size: 0.7rem">{{
                                                    item.kode_karyawan
                                                }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <template #card-status="{ item }">
                                        <span
                                            class="badge bg-secondary-subtle text-secondary border border-secondary fw-semibold py-1 px-2.5"
                                            style="font-size: 0.68rem"
                                        >
                                            Lvl: {{ item.level || '-' }}
                                        </span>
                                    </template>

                                    <template #mobile-divisi_departemen="{ item }">
                                        <span class="d-block text-dark fw-medium" style="font-size: 0.8rem">{{
                                            item.divisi || '-'
                                        }}</span>
                                        <small class="text-muted" style="font-size: 0.7rem">{{
                                            item.departemen || '-'
                                        }}</small>
                                    </template>
                                </ModernDataTable>
                                <Pagination :meta="unfilledMeta" @page="goUnfilledPage" />
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- ============ Detail Modal (Submission / Performance) ============ -->
        <DetailApprovalModal
            :show="showDetailModal"
            :title="detailModalTitle"
            kicker="KPI · Monitoring"
            :header="detailHeader"
            :flowSteps="detailFlowSteps"
            :flowLoading="detailFlowLoading"
            :tampilkanAlur="detailType !== 'performance_ytd'"
            @close="closeDetailModal"
        >
            <template #content>
                <div v-if="detailLoading" class="detail-loading">
                    <i class="bi bi-arrow-repeat spin"></i> Memuat detail…
                </div>

                <!-- Jejak pembatalan: transaksi ini sudah dibatalkan admin. -->
                <div v-else-if="detailHeader.cancellation" class="cancel-trace-banner">
                    <i class="bi bi-x-octagon-fill"></i>
                    <div class="ctb-body">
                        <p class="ctb-title">Transaksi ini dibatalkan</p>
                        <p class="ctb-reason">{{ detailHeader.cancellation.alasan }}</p>
                        <p class="ctb-meta">
                            Oleh <b>{{ detailHeader.cancellation.oleh || '—' }}</b> ·
                            {{ formatDateTime(detailHeader.cancellation.pada) }}
                            <template v-if="detailHeader.cancellation.source === 'cascade'">
                                · <span class="ctb-tag">ikut batal (cascade)</span>
                            </template>
                            <template v-if="detailHeader.cancellation.cascade_count">
                                ·
                                <span class="ctb-tag"
                                    >{{ detailHeader.cancellation.cascade_count }} performance ikut dibatalkan</span
                                >
                            </template>
                        </p>
                        <p class="ctb-batch mono">{{ detailHeader.cancellation.batch_id }}</p>
                    </div>
                </div>

                <!-- ====== SUBMISSION: parameter + target ====== -->
                <template v-if="!detailLoading && detailType === 'submission'">
                    <div class="detail-section-title-r">
                        <i class="bi bi-list-check text-indigo me-1"></i> Parameter KPI Diajukan
                        <span class="badge bg-indigo-soft text-indigo ms-2"
                            >Total Bobot: {{ detailHeader.total_bobot }}</span
                        >
                        <!-- Muncul hanya bila ADA yang layak dikoreksi. Tombol yang
                             selalu ada tapi selalu menemui layar kosong lebih
                             membingungkan daripada tombol yang tidak ada. -->
                        <button
                            v-if="adaTargetBolehDikoreksi"
                            type="button"
                            class="btn btn-sm btn-outline-primary ms-auto"
                            @click="bukaKoreksiTarget"
                        >
                            <i class="bi bi-pencil-square me-1"></i> Perbaiki Target
                        </button>
                    </div>
                    <div v-for="(p, i) in detailParameters" :key="i" class="param-card">
                        <div class="param-card-head">
                            <div>
                                <span class="param-name">{{ p.nama_parameter || '-' }}</span>
                                <span v-if="p.bsc_kategori" class="param-bsc">{{ p.bsc_kategori }}</span>
                            </div>
                            <span class="param-bobot">Bobot {{ p.bobot }}</span>
                        </div>
                        <p v-if="p.deskripsi" class="param-desc">{{ p.deskripsi }}</p>
                        <div class="param-attrs">
                            <span v-if="p.tipe_target"
                                ><b>Target:</b> {{ p.tipe_target
                                }}<template v-if="p.kode_target"> ({{ p.kode_target }})</template></span
                            >
                            <span v-if="p.satuan"><b>Satuan:</b> {{ p.satuan }}</span>
                            <span v-if="p.rumus"
                                ><b>Rumus:</b> <code>{{ p.rumus }}</code></span
                            >
                        </div>
                        <table v-if="p.targets && p.targets.length" class="param-target-table">
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th>Nama Target</th>
                                    <th class="text-end">Nilai Target</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Hanya untuk DIBACA. Penyuntingan punya pintunya
                                     sendiri (tombol "Perbaiki Target") supaya melihat
                                     dan mengubah tidak berbagi permukaan yang sama. -->
                                <tr v-for="(t, ti) in p.targets" :key="ti">
                                    <td class="mono">{{ t.periode || '-' }}</td>
                                    <td>{{ t.nama_target || '-' }}</td>
                                    <td class="text-end fw-semibold">{{ t.nilai_target }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!detailParameters.length" class="text-muted text-center py-3">Tidak ada parameter.</p>
                </template>

                <!-- ====== PERFORMANCE: parameter diupdate user ====== -->
                <template v-if="!detailLoading && detailType === 'performance'">
                    <div class="perf-summary">
                        <!-- Diutamakan: Total Achievement (DB total_score), lalu Total Score (DB total_score_achievement). -->
                        <div class="ps-item">
                            <span>Total Achievement</span><strong>{{ detailHeader.total_score }}</strong>
                        </div>
                        <div class="ps-item">
                            <span>Total Score</span><strong>{{ detailHeader.total_score_achievement }}</strong>
                        </div>
                        <div class="ps-item">
                            <span>Total Bobot</span><strong>{{ detailHeader.total_bobot }}</strong>
                        </div>
                        <div v-if="detailHeader.is_capped" class="ps-item capped">
                            <span>Capped</span><strong>{{ detailHeader.cap_min }}–{{ detailHeader.cap_max }}</strong>
                        </div>
                    </div>
                    <div class="detail-section-title-r">
                        <i class="bi bi-graph-up text-indigo me-1"></i> Parameter Performance (diisi user)
                    </div>
                    <div v-for="(p, i) in detailParameters" :key="i" class="param-card">
                        <div class="param-card-head">
                            <div>
                                <span class="param-name">{{ p.nama_parameter || '-' }}</span>
                                <span v-if="p.bsc_kategori" class="param-bsc">{{ p.bsc_kategori }}</span>
                            </div>
                            <span v-if="p.bobot !== null" class="param-bobot">Bobot {{ p.bobot }}</span>
                        </div>
                        <div class="perf-values">
                            <div class="pv">
                                <span>Target</span
                                ><strong
                                    >{{ p.nilai_target }}<small v-if="p.satuan"> {{ p.satuan }}</small></strong
                                >
                            </div>
                            <div class="pv">
                                <span>Actual</span
                                ><strong
                                    >{{ p.nilai_actual }}<small v-if="p.satuan"> {{ p.satuan }}</small></strong
                                >
                            </div>
                            <!-- Payload meneruskan kolom DB apa adanya, dan kolom itu
                                 tertukar dari namanya: kolom `Score` berisi achievement,
                                 kolom `Achievement` berisi skor berbobot. Label di sini
                                 mengikuti ARTI, sama dengan halaman pengisian dan dengan
                                 header modal ini sendiri. Nama kunci payload sengaja tidak
                                 diubah supaya tidak memutus pemanggil lain. -->
                            <div class="pv achv">
                                <span>Achievement</span><strong>{{ p.score }}%</strong>
                            </div>
                            <div class="pv">
                                <span>Score</span><strong>{{ p.achievement }}</strong>
                            </div>
                            <div v-if="p.is_capped" class="pv">
                                <span>Sebelum penyesuaian</span><strong>{{ p.score_raw }}%</strong>
                            </div>
                        </div>
                        <div class="param-attrs">
                            <span v-if="p.periode_target"
                                ><b>Periode:</b> <span class="mono">{{ p.periode_target }}</span></span
                            >
                            <span v-if="p.rumus"
                                ><b>Rumus:</b> <code>{{ p.rumus }}</code></span
                            >
                        </div>
                        <div v-if="p.alasan_reject" class="param-reject">
                            <i class="bi bi-exclamation-octagon"></i> {{ p.alasan_reject }}
                        </div>
                        <div v-if="p.has_pica && p.pica.length" class="param-pica">
                            <div class="pica-title"><i class="bi bi-tools"></i> PICA</div>
                            <div v-for="(pc, pi) in p.pica" :key="pi" class="pica-item">
                                <div><b>Identifikasi:</b> {{ pc.identifikasi || '-' }}</div>
                                <div><b>Koreksi/Aksi:</b> {{ pc.koreksi_aksi || '-' }}</div>
                                <div v-if="pc.tanggal_pelaksanaan">
                                    <b>Target Selesai:</b> {{ pc.tanggal_pelaksanaan }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="!detailParameters.length" class="text-muted text-center py-3">Tidak ada parameter.</p>
                </template>

                <!-- ====== YTD: ringkasan hitungan dulu, rincian parameter terlipat ====== -->
                <template v-if="!detailLoading && detailType === 'performance_ytd'">
                    <div class="detail-section-title-r">
                        <i class="bi bi-calculator text-indigo me-1"></i> {{ detailHeader.periode_label || 'YTD' }}
                    </div>

                    <p v-if="!detailHeader.ada_data" class="text-muted text-center py-3">
                        Belum ada realisasi terverifikasi pada rentang ini, sehingga belum ada skor YTD.
                    </p>

                    <template v-else>
                        <div class="perf-summary">
                            <div class="ps-item">
                                <span>Skor YTD</span><strong>{{ formatAngkaYtd(detailHeader.skor) }}</strong>
                            </div>
                            <div v-if="detailHeader.keterangan" class="ps-item">
                                <span>Kategori</span
                                ><strong :style="{ color: detailHeader.warna || undefined }">{{
                                    detailHeader.keterangan
                                }}</strong>
                            </div>
                            <div class="ps-item">
                                <span>Σ Skor Berbobot</span
                                ><strong>{{ formatAngkaYtd(detailHeader.sum_weighted) }}</strong>
                            </div>
                            <div class="ps-item">
                                <span>Σ Bobot</span><strong>{{ formatAngkaYtd(detailHeader.total_bobot) }}</strong>
                            </div>
                        </div>

                        <div class="param-attrs">
                            <span
                                ><b>Rumus:</b>
                                <code
                                    >{{ formatAngkaYtd(detailHeader.sum_weighted) }} ÷
                                    {{ formatAngkaYtd(detailHeader.total_bobot) }} × 100 =
                                    {{ formatAngkaYtd(detailHeader.skor) }}</code
                                ></span
                            >
                            <span v-if="detailHeader.bulan_terhitung && detailHeader.bulan_terhitung.length"
                                ><b>Bulan terhitung:</b>
                                {{ detailHeader.bulan_terhitung.map(labelBulanYtd).join(', ') }}
                                ({{ detailHeader.bulan_terhitung.length }} bulan)</span
                            >
                        </div>
                        <p class="text-muted small mb-2">
                            Tiap parameter: Σ aktual dan Σ target hanya dari bulan yang sudah terealisasi,
                            dihitung lewat rumus parameter, lalu capaiannya dijepit
                            {{ detailHeader.cap_param_min }}–{{ detailHeader.cap_param_max }}%.
                        </p>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            :aria-expanded="ytdParameterTerbuka ? 'true' : 'false'"
                            @click="ytdParameterTerbuka = !ytdParameterTerbuka"
                        >
                            <i :class="['bi', ytdParameterTerbuka ? 'bi-chevron-down' : 'bi-chevron-right', 'me-1']"></i>
                            {{ ytdParameterTerbuka ? 'Sembunyikan' : 'Lihat' }} rincian per parameter ({{
                                detailParameters.length
                            }})
                        </button>

                        <div v-if="ytdParameterTerbuka" class="mt-2">
                            <div v-for="p in detailParameters" :key="p.id" class="param-card">
                                <div class="param-card-head">
                                    <div>
                                        <span class="param-name">{{ p.nama_parameter || '-' }}</span>
                                        <span v-if="p.bsc" class="param-bsc">{{ p.bsc }}</span>
                                    </div>
                                    <span class="param-bobot">Bobot {{ formatAngkaYtd(p.bobot) }}</span>
                                </div>
                                <div class="perf-values">
                                    <div class="pv">
                                        <span>Σ Target</span
                                        ><strong
                                            >{{ formatAngkaYtd(p.sum_target)
                                            }}<small v-if="p.satuan"> {{ p.satuan }}</small></strong
                                        >
                                    </div>
                                    <div class="pv">
                                        <span>Σ Aktual</span
                                        ><strong
                                            >{{ formatAngkaYtd(p.sum_actual)
                                            }}<small v-if="p.satuan"> {{ p.satuan }}</small></strong
                                        >
                                    </div>
                                    <div class="pv achv">
                                        <span>Capaian</span><strong>{{ formatAngkaYtd(p.achievement) }}%</strong>
                                    </div>
                                    <div class="pv">
                                        <span>Skor Berbobot</span><strong>{{ formatAngkaYtd(p.score) }}</strong>
                                    </div>
                                </div>
                                <div v-if="p.rumus" class="param-attrs">
                                    <span
                                        ><b>Rumus:</b> <code>{{ p.rumus }}</code></span
                                    >
                                </div>
                            </div>
                        </div>
                    </template>
                </template>
            </template>
        </DetailApprovalModal>

        <!-- ============ Perbaiki Target ============
             Pintu tersendiri, terpisah dari modal detail yang hanya untuk
             dibaca. Menaruh kotak isian di layar baca membuat satu salah klik
             cukup untuk mengubah angka penilaian, dan membuat pemegang izin
             koreksi melihat layar yang nyaris sama dengan yang tidak. -->
        <!-- HcisDialog, BUKAN modalV2-noir. Dua alasan, dan yang kedua tak
             terlihat di layar:

             1. Modal detail memakai HcisDialog (z-index 1050, Teleport ke body).
                modalV2-noir berhenti di 1041 tanpa teleport, jadi ia selalu
                tenggelam di belakangnya.
             2. modalV2-noir menyetel document.body.style position/overflow
                LANGSUNG dan membersihkannya saat ditutup. Ditumpuk di atas
                dialog yang masih terbuka, menutupnya akan membuka gulir milik
                dialog itu. HcisDialog memakai useScrollLock yang berhitung,
                sehingga lapisan kedua tidak merusak lapisan pertama. -->
        <HcisDialog
            :model-value="showKoreksiModal"
            title="Perbaiki Nilai Target"
            description="Penyesuaian nilai target sebelum dilakukan pengisian draf dan penilaian kinerja."
            size="2xl"
            scrollable
            sticky-footer
            glow
            sentiment="primary"
            @close="tutupKoreksiTarget"
        >
            <template #badge>
                <span
                    v-if="koreksiCounts.diubah > 0"
                    class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-semibold"
                >
                    <i class="bi bi-pencil-fill me-1"></i> {{ koreksiCounts.diubah }} Target Diubah
                </span>
                <span v-else class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill">
                    {{ koreksiCounts.boleh }} Dapat Dikoreksi
                </span>
            </template>

            <div class="koreksi-modal-content">
                <!-- Context Info Strip -->
                <div class="koreksi-context-card mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="koreksi-avatar">
                                <i class="bi bi-person-bounding-box text-primary"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="mb-0 fw-bold text-dark">{{ detailHeader.nama || 'Karyawan' }}</h6>
                                    <span class="badge bg-light text-dark border font-monospace small">
                                        {{ detailHeader.kode_karyawan || '-' }}
                                    </span>
                                </div>
                                <div class="text-muted small mt-1 d-flex flex-wrap align-items-center gap-2">
                                    <span
                                        ><i class="bi bi-receipt me-1"></i>No. Transaksi:
                                        <span class="font-monospace fw-semibold">{{
                                            detailHeader.no_transaksi || '-'
                                        }}</span></span
                                    >
                                    <span v-if="detailHeader.unit_kerja" class="text-muted"
                                        >• {{ detailHeader.unit_kerja }}</span
                                    >
                                    <span v-if="detailHeader.periode" class="text-muted"
                                        >• Periode: {{ detailHeader.periode }}</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="koreksi-audit-pill">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            <span>Audit Enforcement Aktif</span>
                        </div>
                    </div>
                </div>

                <!-- Metric Filter Pills & Search Bar -->
                <div class="koreksi-toolbar mb-3">
                    <div class="koreksi-filter-tabs">
                        <button
                            type="button"
                            class="koreksi-tab-btn"
                            :class="{ active: koreksiFilterTab === 'semua' }"
                            @click="koreksiFilterTab = 'semua'"
                        >
                            Semua Target
                            <span class="tab-count">{{ koreksiCounts.semua }}</span>
                        </button>
                        <button
                            type="button"
                            class="koreksi-tab-btn tab-boleh"
                            :class="{ active: koreksiFilterTab === 'boleh' }"
                            @click="koreksiFilterTab = 'boleh'"
                        >
                            <i class="bi bi-check2-circle me-1"></i> Dapat Diubah
                            <span class="tab-count">{{ koreksiCounts.boleh }}</span>
                        </button>
                        <button
                            type="button"
                            class="koreksi-tab-btn tab-diubah"
                            :class="{ active: koreksiFilterTab === 'diubah' }"
                            @click="koreksiFilterTab = 'diubah'"
                        >
                            <i class="bi bi-pencil-square me-1"></i> Telah Diubah
                            <span class="tab-count">{{ koreksiCounts.diubah }}</span>
                        </button>
                        <button
                            type="button"
                            class="koreksi-tab-btn tab-terkunci"
                            :class="{ active: koreksiFilterTab === 'terkunci' }"
                            @click="koreksiFilterTab = 'terkunci'"
                        >
                            <i class="bi bi-lock-fill me-1"></i> Terkunci
                            <span class="tab-count">{{ koreksiCounts.terkunci }}</span>
                        </button>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-grow-1 flex-sm-grow-0">
                        <button
                            type="button"
                            class="btn btn-sm btn-light border text-nowrap d-inline-flex align-items-center gap-1"
                            :title="isAllCollapsed ? 'Buka semua parameter KPI' : 'Tutup semua parameter KPI'"
                            @click="toggleAllParams"
                        >
                            <i class="bi" :class="isAllCollapsed ? 'bi-arrows-expand' : 'bi-arrows-collapse'"></i>
                            <span class="d-none d-md-inline">{{ isAllCollapsed ? 'Buka Semua' : 'Tutup Semua' }}</span>
                        </button>

                        <div class="koreksi-search-input-wrap">
                            <i class="bi bi-search search-icon"></i>
                            <input
                                v-model="koreksiSearchText"
                                type="text"
                                class="form-control form-control-sm koreksi-search-input"
                                placeholder="Cari parameter / periode..."
                            />
                            <button
                                v-if="koreksiSearchText"
                                type="button"
                                class="search-clear-btn"
                                @click="koreksiSearchText = ''"
                            >
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </div>
                        <button
                            v-if="koreksiCounts.diubah > 0"
                            type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 text-nowrap"
                            title="Reset semua perubahan target ke nilai awal"
                            @click="resetSemuaKoreksi"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Reset ({{ koreksiCounts.diubah }})</span>
                        </button>
                    </div>
                </div>

                <!-- Grouped Parameter Cards (Collapsible) -->
                <div class="koreksi-parameters-stack mb-4">
                    <div
                        v-if="parametersKoreksi.length === 0"
                        class="text-center py-5 text-muted bg-light rounded-3 border"
                    >
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                        <p class="mb-1 fw-semibold">Tidak ada target yang cocok dengan filter</p>
                        <small v-if="koreksiSearchText || koreksiFilterTab !== 'semua'">
                            Coba ubah kata kunci pencarian atau tab filter.
                        </small>
                    </div>

                    <div
                        v-for="(param, pIdx) in parametersKoreksi"
                        :key="param.id_parameter"
                        class="koreksi-param-card mb-3"
                        :class="{
                            'is-collapsed': koreksiCollapsedParams[param.id_parameter],
                            'has-changes': param.counts.diubah > 0,
                        }"
                    >
                        <!-- Parameter Card Header (Click to Toggle Collapse) -->
                        <div class="koreksi-param-header" @click="toggleCollapseParam(param.id_parameter)">
                            <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                <div class="param-index-badge">
                                    {{ pIdx + 1 }}
                                </div>
                                <div class="d-flex flex-column min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-bold text-dark param-title">
                                            {{ param.nama_parameter }}
                                        </span>
                                        <span
                                            v-if="param.tipe_target"
                                            class="badge bg-light text-secondary border text-uppercase small"
                                        >
                                            {{ param.tipe_target }}
                                        </span>
                                        <span
                                            v-if="param.bobot"
                                            class="badge bg-primary-subtle text-primary border border-primary-subtle small"
                                        >
                                            Bobot: {{ param.bobot }}%
                                        </span>
                                        <span v-if="param.satuan" class="text-muted small">
                                            Satuan: {{ param.satuan }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Meta & Expand Icon -->
                            <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                                <span
                                    v-if="param.counts.diubah > 0"
                                    class="badge bg-primary text-white rounded-pill px-2 py-1 small fw-semibold"
                                >
                                    <i class="bi bi-pencil-fill me-1"></i> {{ param.counts.diubah }} Diubah
                                </span>
                                <span v-else class="badge bg-light text-muted border rounded-pill px-2 py-1 small">
                                    {{ param.targets.length }} Periode
                                </span>

                                <button
                                    type="button"
                                    class="btn-chevron"
                                    :aria-expanded="!koreksiCollapsedParams[param.id_parameter]"
                                >
                                    <i
                                        class="bi bi-chevron-down chevron-icon"
                                        :class="{ 'rotate-180': !koreksiCollapsedParams[param.id_parameter] }"
                                    ></i>
                                </button>
                            </div>
                        </div>

                        <!-- Parameter Card Body (Table of Periods) -->
                        <div v-show="!koreksiCollapsedParams[param.id_parameter]" class="koreksi-param-body">
                            <table class="table table-hover align-middle mb-0 koreksi-modern-table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px">Periode</th>
                                        <th class="text-end" style="width: 160px">Nilai Saat Ini</th>
                                        <th class="text-end" style="width: 260px">Nilai Baru (Koreksi)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="b in param.targets"
                                        :key="b.id"
                                        :class="{
                                            'row-modified': koreksiTarget[b.id] !== undefined,
                                            'row-locked': !b.boleh,
                                        }"
                                    >
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                                {{ b.periode || '-' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="font-monospace fw-semibold text-secondary">
                                                {{ b.nilai }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div
                                                v-if="b.boleh"
                                                class="d-flex align-items-center justify-content-end gap-2"
                                            >
                                                <!-- Live Delta Badge -->
                                                <div
                                                    v-if="hitungDelta(b)"
                                                    class="delta-badge"
                                                    :class="
                                                        hitungDelta(b).isNaik
                                                            ? 'delta-up'
                                                            : hitungDelta(b).isTurun
                                                              ? 'delta-down'
                                                              : 'delta-same'
                                                    "
                                                >
                                                    <span>{{ hitungDelta(b).diff }}</span>
                                                    <small v-if="hitungDelta(b).diffPercent"
                                                        >({{ hitungDelta(b).diffPercent }})</small
                                                    >
                                                </div>

                                                <!-- Input Field -->
                                                <div class="koreksi-input-group">
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        class="form-control form-control-sm text-end font-monospace koreksi-input-field"
                                                        :class="{ 'is-modified': koreksiTarget[b.id] !== undefined }"
                                                        :value="koreksiTarget[b.id] ?? b.nilai"
                                                        placeholder="Nilai baru"
                                                        @input="catatKoreksi(b, $event.target.value)"
                                                    />
                                                    <!-- Row Reset Action -->
                                                    <button
                                                        v-if="koreksiTarget[b.id] !== undefined"
                                                        type="button"
                                                        class="btn-reset-row"
                                                        title="Kembalikan ke nilai awal"
                                                        @click="resetKoreksiBaris(b)"
                                                    >
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-else class="d-flex align-items-center justify-content-end">
                                                <span
                                                    class="badge bg-light text-muted border d-inline-flex align-items-center gap-1 py-1 px-2"
                                                    :title="b.alasan"
                                                >
                                                    <i class="bi bi-lock-fill text-secondary"></i>
                                                    <span class="text-truncate" style="max-width: 160px">{{
                                                        b.alasan
                                                    }}</span>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Warning / Anomaly Alert Box -->
                <div v-if="koreksiPeringatan.length" class="koreksi-alert-card koreksi-alert-warning mb-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="alert-icon-box text-warning">
                            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading fw-bold mb-1">
                                {{ koreksiPeringatan.length }} Nilai Target Terlihat Menyimpang Signifikan
                            </h6>
                            <p class="small text-muted mb-2">
                                Nilai yang Anda masukkan berbeda jauh dari median periode lainnya. Pastikan nilai ini
                                memang sesuai dengan perubahan target yang diinginkan.
                            </p>
                            <div class="alert-details-table mb-2">
                                <div v-for="w in koreksiPeringatan" :key="w.id_pengajuan_det" class="alert-detail-row">
                                    <span
                                        >Target ID #{{ w.id_pengajuan_det }}: Nilai Baru <b>{{ w.nilai_baru }}</b> (<b
                                            >{{ w.rasio }}x</b
                                        >
                                        dari median <b>{{ w.median }}</b
                                        >)</span
                                    >
                                </div>
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-warning fw-semibold d-inline-flex align-items-center gap-1"
                                :disabled="koreksiMenyimpan"
                                @click="simpanKoreksiTarget(false, true)"
                            >
                                <i class="bi bi-check2-circle"></i>
                                <span>Saya Yakin Nilainya Benar, Lanjutkan Simpan</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Rejection Alert Box -->
                <div v-if="koreksiDitolak.length" class="koreksi-alert-card koreksi-alert-danger mb-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="alert-icon-box text-danger">
                            <i class="bi bi-x-octagon-fill fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading fw-bold mb-1">
                                {{ koreksiDitolak.length }} Target Tidak Layak Dikoreksi
                            </h6>
                            <p class="small text-muted mb-2">
                                Sebagian target tidak dapat diperbarui karena status validasi saat ini.
                            </p>
                            <ul class="mb-2 ps-3 small text-danger">
                                <li v-for="d in koreksiDitolak" :key="d.id_pengajuan_det">
                                    <code>{{ d.kode }}</code
                                    >: {{ d.keterangan }}
                                </li>
                            </ul>
                            <button
                                v-if="Object.keys(koreksiTarget).length"
                                type="button"
                                class="btn btn-sm btn-outline-danger fw-semibold d-inline-flex align-items-center gap-1"
                                :disabled="koreksiMenyimpan"
                                @click="simpanKoreksiTarget(true)"
                            >
                                <i class="bi bi-check2"></i>
                                <span>Terapkan Target yang Layak Saja</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reason / Justification Form Card -->
                <div class="koreksi-reason-card p-3 rounded-3 border bg-light mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-chat-left-text text-primary"></i>
                            Alasan / Sebab Koreksi Target <small class="text-danger">*</small>
                        </label>
                        <span class="text-muted small font-monospace">{{ koreksiSebab.length }}/500</span>
                    </div>

                    <!-- Quick Suggestion Chips -->
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="text-muted small align-self-center me-1">Saran cepat:</span>
                        <button
                            type="button"
                            class="koreksi-chip-btn"
                            @click="setSebabCepat('Salah input / ketik saat proses pengajuan target')"
                        >
                            Salah input saat pengajuan
                        </button>
                        <button
                            type="button"
                            class="koreksi-chip-btn"
                            @click="setSebabCepat('Penyesuaian target berdasarkan arahan RKAP / Anggaran terbaru')"
                        >
                            Penyesuaian target RKAP
                        </button>
                        <button
                            type="button"
                            class="koreksi-chip-btn"
                            @click="setSebabCepat('Revisi target atas kesepakatan Atasan dan Karyawan')"
                        >
                            Revisi kesepakatan Atasan
                        </button>
                        <button
                            type="button"
                            class="koreksi-chip-btn"
                            @click="setSebabCepat('Perubahan strategi dan prioritas unit kerja')"
                        >
                            Perubahan strategi unit
                        </button>
                    </div>

                    <textarea
                        v-model="koreksiSebab"
                        rows="2"
                        maxlength="500"
                        class="form-control form-control-sm koreksi-reason-textarea"
                        placeholder="Tuliskan alasan perbaikan nilai target secara detail (misal: penyesuaian target bulanan sesuai arahan manajemen)..."
                    ></textarea>
                    <div class="text-muted small mt-1 d-flex align-items-center gap-1">
                        <i class="bi bi-info-circle"></i>
                        <span
                            >Alasan ini wajib diisi dan akan dicatat secara permanen pada log audit perbaikan
                            target.</span
                        >
                    </div>
                </div>
            </div>

            <!-- Modal Footer Slot -->
            <template #footer>
                <div class="d-flex flex-wrap align-items-center justify-content-between w-100 gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span
                            v-if="Object.keys(koreksiTarget).length > 0"
                            class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold d-inline-flex align-items-center gap-2"
                        >
                            <i class="bi bi-pencil-fill"></i>
                            <span>{{ Object.keys(koreksiTarget).length }} target siap disimpan</span>
                        </span>
                        <span v-else class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i> Belum ada target yang diubah
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-light border px-3"
                            :disabled="koreksiMenyimpan"
                            @click="tutupKoreksiTarget"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-primary px-4 fw-semibold d-inline-flex align-items-center gap-2"
                            :disabled="koreksiMenyimpan || !koreksiSebab.trim() || !Object.keys(koreksiTarget).length"
                            @click="simpanKoreksiTarget(false)"
                        >
                            <span v-if="koreksiMenyimpan" class="spinner-border spinner-border-sm" role="status"></span>
                            <i v-else class="bi bi-check-lg"></i>
                            <span>{{ koreksiMenyimpan ? 'Menyimpan...' : 'Simpan Koreksi Target' }}</span>
                        </button>
                    </div>
                </div>
            </template>
        </HcisDialog>

        <!-- ============ Pembatalan Transaksi (single & bulk) ============ -->
        <CancelTransactionModal
            :show="cancelModal.show"
            :domain="cancelModal.domain"
            :preview="cancelModal.preview"
            :executing="cancelModal.executing"
            :result="cancelModal.result"
            :conflicts="cancelModal.conflicts"
            @close="closeCancelModal"
            @confirm="confirmCancel"
            @reload="loadCancelPreview"
        />

        <!-- ============ Confirmation & Preview Modal ============ -->
        <Modal
            :show="showPreviewModal"
            title="Konfirmasi Enforce Achievement Cap"
            subtitle="KPI karyawan terpilih akan diisi otomatis, melewati approval."
            size="large"
            :showFooter="true"
            :showConfirm="true"
            confirmText="Ya, Eksekusi Sekarang"
            cancelText="Batal"
            :loading="isEnforcing"
            loadingText="Menjadwalkan eksekusi…"
            :confirmDisabled="isEnforcing || !preview || !preview.employees || !preview.employees.length"
            @close="closePreview"
            @confirm="confirmEnforce"
        >
            <template #body>
                <div class="kl-modal-stack" v-if="preview">
                    <!-- Satu ringkasan, bukan dua kotak yang mengapit daftar dan
                         mengulang isi yang sama. Fakta yang menentukan keputusan
                         (siapa, periode mana, dijepit ke berapa) dipisah dari
                         kalimatnya supaya bisa dibaca sekilas. -->
                    <div class="cap-brief">
                        <div class="cap-brief-lead">
                            <i class="bi bi-shield-exclamation"></i>
                            <div>
                                <p class="cap-brief-title">{{ preview.affected_count }} karyawan akan diisi otomatis</p>
                                <p class="cap-brief-sub">
                                    Semua parameter diisi 0, lalu total achievement dijepit ke batas yang berlaku.
                                    Pengisian melewati approval, berjalan di latar, dan tidak bisa dibatalkan dari layar
                                    ini.
                                </p>
                            </div>
                        </div>
                        <dl class="cap-facts">
                            <div>
                                <dt>Periode</dt>
                                <dd>{{ preview.periode_label }}</dd>
                            </div>
                            <div>
                                <dt>Batas achievement</dt>
                                <dd>{{ preview.simulation.cap_min }} – {{ preview.simulation.cap_max }}</dd>
                            </div>
                            <div>
                                <dt>Nilai parameter</dt>
                                <dd>0 untuk semua</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="preview.employees.length" class="emp-list-head">
                        <span class="emp-list-title">Karyawan terdampak</span>
                        <span v-if="statsOnDemand" class="emp-list-hint">
                            Buka satu baris untuk menghitung simulasi skornya
                        </span>
                    </div>

                    <!-- List karyawan terpilih dengan accordion detail.
                         Saat ada detail yang dibuka, batas tinggi daftar dilepas supaya
                         tabel parameter tidak terjepit di scroll bersarang. -->
                    <div class="emp-list" :class="{ 'has-expanded': expandedEmp !== null }">
                        <div
                            v-for="emp in preview.employees"
                            :key="emp.id_user"
                            class="emp-accordion"
                            :class="{ open: expandedEmp === emp.id_user }"
                        >
                            <button class="emp-acc-head" @click="toggleEmpDetail(emp.id_user)">
                                <div class="emp-acc-info">
                                    <i
                                        class="bi"
                                        :class="expandedEmp === emp.id_user ? 'bi-chevron-down' : 'bi-chevron-right'"
                                    ></i>
                                    <div
                                        class="user-avatar-initials-sm bg-indigo-soft text-indigo d-flex align-items-center justify-content-center fw-bold rounded-circle"
                                        style="width: 28px; height: 28px; font-size: 0.7rem; flex-shrink: 0"
                                    >
                                        {{ getUserInitials(emp.nama) }}
                                    </div>
                                    <div class="emp-cell">
                                        <span class="emp-name">{{ emp.nama }}</span>
                                        <span class="emp-code">{{ emp.kode_karyawan }} · {{ emp.divisi || '-' }}</span>
                                    </div>
                                </div>
                                <div class="emp-acc-scores">
                                    <template
                                        v-if="
                                            empDetailCache[emp.id_user] &&
                                            empDetailCache[emp.id_user].status !== 'error'
                                        "
                                    >
                                        <!-- Diutamakan: Total Achievement (DB total_score, yang di-cap). -->
                                        <span class="score-chip ach">
                                            <span class="sc-label">Achievement</span>
                                            <strong>{{ empDetailCache[emp.id_user].total_score }}</strong>
                                        </span>
                                        <span class="score-chip">
                                            <span class="sc-label">Score</span>
                                            <strong>{{ empDetailCache[emp.id_user].total_score_achievement }}</strong>
                                        </span>
                                        <span
                                            v-if="empDetailCache[emp.id_user].status === 'enforced'"
                                            class="status-pill is-approved"
                                            >Sudah Enforced</span
                                        >
                                        <span
                                            v-else-if="empDetailCache[emp.id_user].is_capped"
                                            class="status-pill is-capped"
                                            >Capped</span
                                        >
                                    </template>
                                    <span
                                        v-else-if="
                                            empDetailCache[emp.id_user] &&
                                            empDetailCache[emp.id_user].status === 'error'
                                        "
                                        class="status-pill is-draft"
                                        >Error</span
                                    >
                                    <span v-else-if="statsOnDemand" class="emp-acc-ondemand"> Lihat simulasi </span>
                                    <span v-else class="text-muted small"
                                        ><i class="bi bi-arrow-repeat spin"></i> menghitung…</span
                                    >
                                </div>
                            </button>

                            <div v-if="expandedEmp === emp.id_user" class="emp-acc-body">
                                <div v-if="loadingEmpDetail === emp.id_user" class="emp-acc-loading">
                                    <i class="bi bi-arrow-repeat spin"></i> Memuat detail parameter…
                                </div>
                                <template v-else-if="empDetailCache[emp.id_user]">
                                    <div v-if="empDetailCache[emp.id_user].status === 'error'" class="emp-acc-error">
                                        <i class="bi bi-exclamation-octagon"></i>
                                        {{ empDetailCache[emp.id_user].message }}
                                    </div>
                                    <template v-else>
                                        <div class="detail-summary">
                                            <span
                                                >Total Bobot:
                                                <strong>{{ empDetailCache[emp.id_user].total_bobot }}</strong></span
                                            >
                                            <span
                                                >Achievement Raw:
                                                <strong>{{ empDetailCache[emp.id_user].total_score_raw }}</strong></span
                                            >
                                            <span
                                                >Total Achievement:
                                                <strong>{{ empDetailCache[emp.id_user].total_score }}</strong></span
                                            >
                                        </div>
                                        <div class="param-table-wrap">
                                            <table class="param-table">
                                                <thead>
                                                    <tr>
                                                        <th>Parameter</th>
                                                        <th>Periode</th>
                                                        <th>Bobot</th>
                                                        <th>Actual</th>
                                                        <th>Score</th>
                                                        <th>Achievement</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(d, i) in empDetailCache[emp.id_user].details || []"
                                                        :key="i"
                                                    >
                                                        <td>
                                                            <span class="d-block fw-semibold">{{
                                                                d.nama_parameter || '-'
                                                            }}</span>
                                                            <small v-if="d.kode_target" class="text-muted"
                                                                >{{ d.tipe_target }} ({{ d.kode_target }})</small
                                                            >
                                                        </td>
                                                        <td>
                                                            <span class="mono">{{ d.periode_target || '-' }}</span>
                                                        </td>
                                                        <td>{{ d.bobot }}</td>
                                                        <td class="zero">{{ d.nilai_actual }}</td>
                                                        <td class="zero">{{ d.score }}</td>
                                                        <td class="zero">{{ d.achievement }}</td>
                                                    </tr>
                                                    <tr v-if="!(empDetailCache[emp.id_user].details || []).length">
                                                        <td colspan="6" class="empty-row">
                                                            Tidak ada parameter relevan.
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="param-count">
                                            {{ (empDetailCache[emp.id_user].details || []).length }} parameter
                                        </p>
                                    </template>
                                </template>
                            </div>
                        </div>
                        <p v-if="!preview.employees.length" class="text-muted text-center py-3 mb-0">
                            Tidak ada karyawan valid untuk dieksekusi.
                        </p>
                    </div>
                </div>
                <div v-else class="emp-acc-loading"><i class="bi bi-arrow-repeat spin"></i> Menyiapkan preview…</div>
            </template>
        </Modal>

        <!-- Daftar export kini terpusat di panel Pusat Aktivitas (topbar) — panel
             floating lokal dihapus agar tidak duplikat. -->

        <!-- ============ Persistent Drive-Style Progress Card ============ -->
        <transition name="drive-pop">
            <div
                v-if="activeBatchId && batchProgress"
                class="drive-progress stitch-card"
                style="bottom: 1.25rem; right: 1.25rem"
            >
                <div class="dp-header">
                    <div class="dp-title">
                        <i class="bi" :class="progressIcon"></i>
                        <span>{{ progressTitle }}</span>
                    </div>
                    <button v-if="isProgressDone" class="dp-close" @click="dismissProgress">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="dp-bar-track">
                    <div
                        class="dp-bar-fill"
                        :class="{ 'is-failed': batchProgress.status === 'failed' }"
                        :style="{ width: batchProgress.percent + '%' }"
                    ></div>
                </div>
                <div class="dp-meta">
                    <span
                        >{{ batchProgress.processed_records }} / {{ batchProgress.total_records }} ({{
                            batchProgress.percent
                        }}%)</span
                    >
                    <span class="dp-counts">
                        <span class="ok"><i class="bi bi-check-circle-fill"></i> {{ batchProgress.success }}</span>
                        <span v-if="batchProgress.failed" class="fail"
                            ><i class="bi bi-x-circle-fill"></i> {{ batchProgress.failed }}</span
                        >
                    </span>
                </div>
            </div>
        </transition>

        <!-- Feedback toast/modal -->
        <Modal
            :show="showFeedback"
            :title="feedback.title"
            size="small"
            :showCancel="false"
            confirmText="Tutup"
            @close="showFeedback = false"
            @confirm="showFeedback = false"
        >
            <template #body>
                <div class="kl-feedback-body" :class="`is-${feedback.type}`">
                    <i class="bi" :class="feedback.type === 'error' ? 'bi-x-octagon-fill' : 'bi-check-circle-fill'"></i>
                    <p class="mb-0">{{ feedback.message }}</p>
                </div>
            </template>
        </Modal>
    </div>
</template>
<script>
import axios from 'axios';
import HeaderKpi from '@/components/legacy/header.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import Pagination from './components/SimplePagination.vue';
import ModernDataTable from '@/components/legacy/table-noir.vue';
import DetailApprovalModal from '@/components/legacy/DetailApprovalModal.vue';
import HcisDialog from '@/components/ui/HcisDialog.vue';
import CancelTransactionModal from './components/CancelTransactionModal.vue';
import CancelToolbar from './components/CancelToolbar.vue';
import { useActivityCenter } from '@/composables/useActivityCenter';
import { tanggalPanjang } from '@/utils/tanggal';

const LS_KEY = 'kpi_enforcement_active_batch';
const POLL_INTERVAL = 4000;
// 'cancelled' = dibatalkan dari panel Tugas & Aktivitas. Tanpa ini halaman terus
// memantau batch itu sebagai "berjalan" dan menahan eksekusi baru.
const DONE_STATUSES = ['completed', 'completed_with_errors', 'failed', 'cancelled'];

export default {
    name: 'KpiEnforcementDashboard',
    components: {
        HeaderKpi,
        ApprovalTabs,
        Modal,
        ModernFilter,
        Pagination,
        ModernDataTable,
        DetailApprovalModal,
        HcisDialog,
        CancelTransactionModal,
        CancelToolbar,
    },
    props: {
        // Monitoring: filter induk = periode/tahun (bukan penjadwalan).
        submissionYears: { type: Array, default: () => [] }, // [{value:'2026',label:'2026'}]
        penilaianPeriods: { type: Array, default: () => [] }, // [{value:'2026-05',label:'Mei 2026'}]
        // Enforcement: tetap pakai penjadwalan penilaian.
        penilaianSchedules: { type: Array, default: () => [] },
    },
    data() {
        return {
            currentDate: new Date(),

            // Monitoring scope induk (tahun utk submission, periode bulan utk performance).
            selectedSubmissionYear: '',
            selectedPenilaianPeriode: '',
            // 'bulan' | 'ytd'. YTD = akumulasi Januari s/d bulan terpilih, angka
            // yang sama dengan dashboard dan `kpi:verify-ytd`.
            penilaianMode: 'bulan',
            // Filter dalam penjadwalan (opsional, '' = Semua).
            selectedSubmissionSchedule: '',
            selectedPenilaianSchedule: '',
            // Daftar penjadwalan dalam scope (diisi via API saat scope dipilih).
            submissionSchedulesInScope: [],
            penilaianSchedulesInScope: [],
            // Export sub-tab.
            exportJenis: 'parameter', // parameter | performance
            exportFormat: 'excel', // excel | pdf
            // Terpisah dari sakelar di tab Monitoring dengan sengaja: kalau
            // cakupan berkas diam-diam mengikuti sakelar tab lain, admin tidak
            // punya cara melihat isi berkasnya sebelum ia terunduh.
            exportMode: 'bulan', // bulan | ytd (hanya berlaku untuk performance)
            exportNama: '', // label opsional yang tampil di panel aktivitas
            exportScope: '', // tahun (parameter) / periode (performance)
            exportFilters: {
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                status: [],
                search: '',
                tgl_masuk: [],
            },
            exportFilterOptions: { lokasi: [], divisi: [], departemen: [], level: [], jabatan: [] },
            exportRows: [],
            exportSelectedIds: [],
            exportMeta: { page: 1, total_page: 1, total_data: 0 },
            exportIdToUserMap: {},
            isLoadingExport: false,
            isRequestingExport: false,
            // Mode "centang semua": pilih SELURUH karyawan (scope+filter) tanpa narik
            // datanya ke client. Worker (Cloud Task) yang resolve id_user-nya nanti.
            // Saat aktif, exportSelectedIds diabaikan utk request (kirim select_all).
            exportSelectAllMode: false,

            // Enforcement: induk = periode bulan, target penjadwalan = override opsional (''=Auto).
            selectedEnforcePeriode: '',
            selectedEnforceSchedule: '',
            enforceSchedulesInScope: [],

            tabs: [
                { id: 'monitoring', icon: 'bi bi-clipboard-data', label: 'KPI Monitoring & Progress', count: null },
                { id: 'enforcement', icon: 'bi bi-shield-check', label: 'KPI Auto-Cap Enforcement', count: null },
            ],
            activeTab: 'monitoring',

            // Sub-tabs sidebar for Monitoring tab
            monitorSubTabs: [
                { id: 'submission', icon: 'bi bi-list-check', label: 'KPI Submission' },
                { id: 'performance', icon: 'bi bi-graph-up', label: 'Performance Tracking' },
                { id: 'export', icon: 'bi bi-download', label: 'Export Data' },
                { id: 'cancellation', icon: 'bi bi-clock-history', label: 'Riwayat Pembatalan' },
            ],

            // ---- Pembatalan transaksi ----
            // Pilihan BERTAHAN lintas halaman, pencarian, dan filter — hanya di-reset
            // saat scope induk berganti (tahun/periode/penjadwalan/sub-tab), karena di
            // luar itu id transaksinya sudah bukan universe yang sama.
            cancelSelectedIds: [],
            // id -> { nama, status }. Perlu disimpan karena baris terpilih bisa berada
            // di halaman lain / tersaring keluar, sehingga tak bisa dibaca dari tabel.
            cancelSelectedMeta: {},
            isSelectingAll: false,
            cancelModal: {
                show: false,
                domain: 'parameter',
                preview: null,
                executing: false,
                result: null,
                conflicts: null,
                ids: [],
            },

            // Sub-tab riwayat pembatalan
            historyRows: [],
            historyMeta: { page: 1, total_page: 1, total_data: 0 },
            historyFilters: { domain: [], source: [], search: '' },
            isLoadingHistory: false,

            // TAB 1
            monitorView: 'submission',
            monitorSearch: '',
            isLoadingMonitor: false,
            submissionRows: [],
            performanceRows: [],
            monitorMeta: { page: 1, total_page: 1, total_data: 0 },
            // Filter ISI monitoring (terpisah dari filter penjadwalan = scope).
            monitorFilters: {
                status: [],
                riwayat: [],
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                tgl_masuk: [],
            },
            monitorFilterOptions: { lokasi: [], divisi: [], departemen: [], level: [], jabatan: [] },

            // TAB 2
            isLoadingUnfilled: false,
            unfilledRows: [],
            unfilledMeta: { page: 1, total_page: 1, total_data: 0 },
            selectedIds: [],
            filters: { lokasi: [], divisi: [], departemen: [], level: [], jabatan: [] },
            filterOptions: { lokasi: [], divisi: [], departemen: [], level: [], jabatan: [] },

            // Detail modal (submission / performance)
            showDetailModal: false,
            detailType: null, // 'submission' | 'performance' | 'performance_ytd'
            // Rincian per parameter pada modal YTD terlipat secara bawaan:
            // ringkasan hitungan total yang ditampilkan lebih dulu.
            ytdParameterTerbuka: false,
            detailLoading: false,
            detailHeader: {},
            detailParameters: [],
            detailItemTerakhir: null,
            // Koreksi target. kelayakanTarget kosong berarti TIDAK ADA sel yang
            // boleh disunting: gagal-tertutup, supaya kegagalan memuat kelayakan
            // tidak diam-diam membuka penyuntingan.
            kelayakanTarget: {},
            koreksiTarget: {},
            koreksiSebab: '',
            koreksiMenyimpan: false,
            koreksiDitolak: [],
            showKoreksiModal: false,
            koreksiPeringatan: [],
            koreksiSearchText: '',
            koreksiFilterTab: 'semua', // 'semua' | 'boleh' | 'diubah' | 'terkunci'
            koreksiCollapsedParams: {},
            detailFlowSteps: [],
            detailFlowLoading: false,

            // Enforcement modal
            showPreviewModal: false,
            // Pilihan besar tidak di-prefetch simulasinya; lihat prefetchAllEmpStats.
            PREFETCH_MAX: 12,
            statsOnDemand: false,
            preview: null,
            isEnforcing: false,
            expandedEmp: null,
            loadingEmpDetail: null,
            empDetailCache: {},

            // Persistent progress
            activeBatchId: null,
            batchProgress: null,
            pollTimer: null,

            // Feedback
            showFeedback: false,
            feedback: { type: 'success', title: '', message: '' },

            // Columns configurations
            submissionColumns: [
                { key: 'selection', title: '', width: '40px', align: 'center' },
                { key: 'karyawan', title: 'Karyawan' },
                { key: 'divisi_departemen', title: 'Divisi / Departemen' },
                { key: 'jabatan', title: 'Jabatan' },
                { key: 'no_transaksi', title: 'No. Transaksi' },
                { key: 'status_approval', title: 'Status Approval', align: 'center', width: '150px' },
                { key: 'aksi', title: '', width: '48px', align: 'center' },
            ],
            historyColumns: [
                { key: 'waktu', title: 'Waktu', width: '150px' },
                { key: 'karyawan', title: 'Karyawan' },
                { key: 'jenis', title: 'Jenis', width: '130px' },
                { key: 'no_transaksi', title: 'No. Transaksi' },
                { key: 'periode', title: 'Periode', width: '100px' },
                { key: 'status_sebelum', title: 'Status Sebelum', align: 'center', width: '130px' },
                { key: 'alasan', title: 'Alasan' },
                { key: 'oleh', title: 'Dibatalkan Oleh', width: '150px' },
            ],
            historyMobileColumns: [
                { key: 'jenis', title: 'Jenis', col: 6 },
                { key: 'periode', title: 'Periode', col: 6 },
                { key: 'no_transaksi', title: 'No. Transaksi', col: 12 },
                { key: 'alasan', title: 'Alasan', col: 12 },
                { key: 'oleh', title: 'Dibatalkan Oleh', col: 12 },
            ],
            submissionMobileColumns: [
                { key: 'divisi_departemen', title: 'Divisi / Departemen', col: 12 },
                { key: 'jabatan', title: 'Jabatan', col: 12 },
                { key: 'no_transaksi', title: 'No. Transaksi', col: 12 },
            ],
            performanceColumns: [
                { key: 'selection', title: '', width: '40px', align: 'center' },
                { key: 'karyawan', title: 'Karyawan' },
                { key: 'divisi', title: 'Divisi' },
                // Diutamakan: Total Achievement (DB total_score). Lalu Total Score (DB total_score_achievement).
                { key: 'achievement', title: 'Total Achievement' },
                { key: 'score', title: 'Total Score' },
                { key: 'cap', title: 'Cap' },
                { key: 'status', title: 'Status', align: 'center', width: '130px' },
                { key: 'aksi', title: '', width: '48px', align: 'center' },
            ],
            performanceMobileColumns: [
                { key: 'divisi', title: 'Divisi', col: 12 },
                { key: 'achievement', title: 'Total Achievement', col: 6 },
                { key: 'score', title: 'Total Score', col: 6 },
                { key: 'cap', title: 'Cap', col: 12 },
            ],
            unfilledColumns: [
                { key: 'selection', title: '', width: '40px', align: 'center' },
                { key: 'karyawan', title: 'Karyawan' },
                { key: 'lokasi', title: 'Lokasi' },
                { key: 'divisi_departemen', title: 'Divisi / Departemen' },
                { key: 'level', title: 'Level' },
                { key: 'jabatan', title: 'Jabatan' },
            ],
            unfilledMobileColumns: [
                { key: 'lokasi', title: 'Lokasi', col: 6 },
                { key: 'level', title: 'Level', col: 6 },
                { key: 'divisi_departemen', title: 'Divisi / Departemen', col: 12 },
                { key: 'jabatan', title: 'Jabatan', col: 12 },
            ],
            exportColumns: [
                { key: 'selection', title: '', width: '40px', align: 'center' },
                { key: 'karyawan', title: 'Karyawan' },
                { key: 'divisi_departemen', title: 'Divisi / Departemen' },
                { key: 'jabatan', title: 'Jabatan' },
                { key: 'status', title: 'Status', align: 'center', width: '160px' },
            ],
            exportMobileColumns: [
                { key: 'divisi_departemen', title: 'Divisi / Departemen', col: 12 },
                { key: 'jabatan', title: 'Jabatan', col: 12 },
                { key: 'status', title: 'Status', col: 12 },
            ],
        };
    },
    computed: {
        /** Ada minimal satu target yang layak dikoreksi. */
        adaTargetBolehDikoreksi() {
            return Object.values(this.kelayakanTarget || {}).some((k) => k?.boleh === true);
        },

        /**
         * Seluruh target pengajuan ini, diratakan jadi satu tabel.
         *
         * Yang terkunci IKUT dibawa beserta alasannya: daftar yang bolong tidak
         * memberi tahu apakah periode itu memang tak ada atau sedang terhalang,
         * dan tidak memberi tahu siapa yang harus dikejar.
         */
        barisKoreksi() {
            return (this.detailParameters || []).flatMap((p) =>
                (p.targets || []).map((t) => {
                    const k = this.kelayakanTarget?.[t.id_pengajuan_det];

                    return {
                        id: t.id_pengajuan_det,
                        parameter: p.nama_parameter,
                        tipe_target: p.tipe_target,
                        kode_target: p.kode_target,
                        satuan: p.satuan,
                        bobot: p.bobot,
                        periode: t.periode,
                        nilai: t.nilai_target,
                        boleh: k?.boleh === true,
                        alasan: k?.keterangan || 'Tidak bisa diubah',
                    };
                }),
            );
        },

        /**
         * Target yang disaring berdasarkan filter tab aktif dan kata kunci pencarian.
         */
        barisKoreksiTersaring() {
            const query = (this.koreksiSearchText || '').toLowerCase().trim();
            const tab = this.koreksiFilterTab || 'semua';

            return this.barisKoreksi.filter((b) => {
                const isDiubah = this.koreksiTarget[b.id] !== undefined;

                if (tab === 'boleh' && !b.boleh) return false;
                if (tab === 'terkunci' && b.boleh) return false;
                if (tab === 'diubah' && !isDiubah) return false;

                if (query) {
                    const paramMatch = (b.parameter || '').toLowerCase().includes(query);
                    const periodeMatch = (b.periode || '').toLowerCase().includes(query);
                    const satuanMatch = (b.satuan || '').toLowerCase().includes(query);
                    const tipeMatch = (b.tipe_target || '').toLowerCase().includes(query);
                    return paramMatch || periodeMatch || satuanMatch || tipeMatch;
                }

                return true;
            });
        },

        /**
         * Target dikelompokkan per Parameter KPI untuk tampilan Collapse/Accordion.
         */
        parametersKoreksi() {
            const query = (this.koreksiSearchText || '').toLowerCase().trim();
            const tab = this.koreksiFilterTab || 'semua';

            return (this.detailParameters || [])
                .map((p, idx) => {
                    const paramId = p.id_parameter ? String(p.id_parameter) : `param_${idx}`;
                    const targets = (p.targets || []).map((t) => {
                        const k = this.kelayakanTarget?.[t.id_pengajuan_det];
                        const isDiubah = this.koreksiTarget[t.id_pengajuan_det] !== undefined;

                        return {
                            id: t.id_pengajuan_det,
                            parameter: p.nama_parameter,
                            tipe_target: p.tipe_target,
                            kode_target: p.kode_target,
                            satuan: p.satuan,
                            bobot: p.bobot,
                            periode: t.periode,
                            nilai: t.nilai_target,
                            boleh: k?.boleh === true,
                            alasan: k?.keterangan || 'Tidak bisa diubah',
                            isDiubah,
                        };
                    });

                    // Filter targets inside this parameter based on tab & query
                    const filteredTargets = targets.filter((t) => {
                        if (tab === 'boleh' && !t.boleh) return false;
                        if (tab === 'terkunci' && t.boleh) return false;
                        if (tab === 'diubah' && !t.isDiubah) return false;

                        if (query) {
                            const paramMatch = (p.nama_parameter || '').toLowerCase().includes(query);
                            const periodeMatch = (t.periode || '').toLowerCase().includes(query);
                            const satuanMatch = (p.satuan || '').toLowerCase().includes(query);
                            const tipeMatch = (p.tipe_target || '').toLowerCase().includes(query);
                            return paramMatch || periodeMatch || satuanMatch || tipeMatch;
                        }

                        return true;
                    });

                    const counts = {
                        total: targets.length,
                        boleh: targets.filter((t) => t.boleh).length,
                        terkunci: targets.filter((t) => !t.boleh).length,
                        diubah: targets.filter((t) => t.isDiubah).length,
                    };

                    return {
                        id_parameter: paramId,
                        nama_parameter: p.nama_parameter,
                        tipe_target: p.tipe_target,
                        kode_target: p.kode_target,
                        satuan: p.satuan,
                        bobot: p.bobot,
                        allTargets: targets,
                        targets: filteredTargets,
                        counts,
                    };
                })
                .filter((p) => p.targets.length > 0);
        },

        /**
         * Apakah seluruh parameter saat ini sedang dalam kondisi collapse / tertutup.
         */
        isAllCollapsed() {
            const list = this.parametersKoreksi;
            if (!list.length) return false;
            return list.every((p) => this.koreksiCollapsedParams[p.id_parameter] === true);
        },

        /**
         * Ringkasan kuantitas target untuk badge dan tab filter.
         */
        koreksiCounts() {
            const list = this.barisKoreksi;
            return {
                semua: list.length,
                boleh: list.filter((b) => b.boleh).length,
                terkunci: list.filter((b) => !b.boleh).length,
                diubah: Object.keys(this.koreksiTarget).length,
            };
        },

        dayNight() {
            const hour = this.currentDate.getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            return this.currentDate.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
        },
        /**
         * "Semua tercentang" diukur terhadap SELURUH hasil filter, bukan halaman
         * aktif. Kalau diukur per halaman, centang judul kolom pada halaman 2 akan
         * terlihat penuh padahal halaman lain belum ikut terpilih.
         */
        allChecked() {
            const total = this.unfilledMeta.total_data || 0;

            return total > 0 && this.selectedIds.length >= total;
        },
        selectionIndeterminate() {
            return this.selectedIds.length > 0 && !this.allChecked;
        },
        detailModalTitle() {
            if (this.detailType === 'performance_ytd') return 'Rincian Perhitungan YTD';

            return this.detailType === 'submission'
                ? 'Detail Pengajuan KPI Parameter'
                : 'Detail Update Performance KPI';
        },
        isProgressDone() {
            return this.batchProgress && DONE_STATUSES.includes(this.batchProgress.status);
        },
        progressIcon() {
            if (!this.batchProgress) return 'bi-arrow-repeat spin';
            if (this.batchProgress.status === 'failed') return 'bi-x-octagon-fill';
            if (this.batchProgress.status === 'completed') return 'bi-check-circle-fill';
            if (this.batchProgress.status === 'completed_with_errors') return 'bi-exclamation-triangle-fill';
            if (this.batchProgress.status === 'cancelled') return 'bi-slash-circle-fill';
            return 'bi-arrow-repeat spin';
        },
        progressTitle() {
            if (!this.batchProgress) return 'Memproses…';
            const s = this.batchProgress.status;
            if (s === 'completed') return 'Eksekusi selesai';
            if (s === 'completed_with_errors') return 'Selesai dengan sebagian gagal';
            if (s === 'failed') return 'Eksekusi gagal';
            if (s === 'cancelled') return 'Eksekusi dibatalkan';
            return 'Memproses enforcement…';
        },
        filterConfig() {
            return {
                searchable: false,
                showResultsCount: false,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: false,
                filters: [
                    {
                        key: 'lokasi',
                        label: 'Lokasi',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: (this.filterOptions?.lokasi || []).map((o) => ({ value: o, label: o })),
                    },
                    {
                        key: 'divisi',
                        label: 'Divisi',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: (this.filterOptions?.divisi || []).map((o) => ({ value: o, label: o })),
                    },
                    {
                        key: 'departemen',
                        label: 'Departemen',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: (this.filterOptions?.departemen || []).map((o) => ({ value: o, label: o })),
                    },
                    {
                        key: 'level',
                        label: 'Level',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: (this.filterOptions?.level || []).map((o) => ({ value: o, label: o })),
                    },
                    {
                        key: 'jabatan',
                        label: 'Jabatan',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: (this.filterOptions?.jabatan || []).map((o) => ({ value: o, label: o })),
                    },
                ],
            };
        },
        // Opsi status berbeda konteks: performance punya Draft (belum difinalisasi),
        // parameter (submission) tidak. "Sedang Berjalan" dilebur ke "Menunggu Approval".
        monitorStatusOptions() {
            const common = [
                { value: 'belum_submit', label: this.monitorView === 'performance' ? 'Belum Update' : 'Belum Submit' },
                { value: 'tidak_submit', label: this.monitorView === 'performance' ? 'Tidak Update' : 'Tidak Submit' },
                { value: 'waiting', label: 'Menunggu Approval' },
                { value: 'rejected', label: 'Ditolak' },
                { value: 'approved', label: 'Disetujui' },
            ];
            if (this.monitorView === 'performance') {
                common.splice(2, 0, { value: 'draft', label: 'Draft' });
                common.push(
                    { value: 'tanpa_target', label: 'Tidak Ada Target' },
                    { value: 'tanpa_parameter', label: 'Tidak Ada Parameter' },
                );
            }
            return common;
        },
        monitorFilterConfig() {
            const opt = (k) => (this.monitorFilterOptions?.[k] || []).map((o) => ({ value: o, label: o }));
            return {
                searchable: false,
                showResultsCount: false,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: false,
                filters: [
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: this.monitorStatusOptions,
                    },
                    {
                        key: 'riwayat',
                        label: 'Riwayat',
                        type: 'select',
                        multiple: true,
                        filterable: false,
                        col: 2,
                        options: [{ value: 'pernah_dibatalkan', label: 'Pernah dibatalkan' }],
                    },
                    {
                        key: 'lokasi',
                        label: 'Lokasi',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: opt('lokasi'),
                    },
                    {
                        key: 'divisi',
                        label: 'Divisi',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: opt('divisi'),
                    },
                    {
                        key: 'departemen',
                        label: 'Departemen',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: opt('departemen'),
                    },
                    {
                        key: 'level',
                        label: 'Level',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: opt('level'),
                    },
                    {
                        key: 'jabatan',
                        label: 'Jabatan',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: opt('jabatan'),
                    },
                    {
                        key: 'tgl_masuk',
                        label: 'Tgl Masuk',
                        type: 'daterange',
                        col: 4,
                        format: 'DD/MM/YYYY',
                        valueFormat: 'YYYY-MM-DD',
                        startPlaceholder: 'Masuk dari',
                        endPlaceholder: 'Sampai',
                    },
                ],
            };
        },
        historyFilterConfig() {
            return {
                searchable: false,
                showResultsCount: false,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: false,
                filters: [
                    {
                        key: 'domain',
                        label: 'Jenis',
                        type: 'select',
                        multiple: true,
                        col: 3,
                        options: [
                            { value: 'parameter', label: 'KPI Parameter' },
                            { value: 'performance', label: 'Update Performance' },
                            { value: 'draft', label: 'Draft Performance' },
                        ],
                    },
                    {
                        key: 'source',
                        label: 'Sumber',
                        type: 'select',
                        multiple: true,
                        col: 3,
                        options: [
                            { value: 'manual', label: 'Dibatalkan langsung' },
                            { value: 'cascade', label: 'Ikut batal (cascade)' },
                        ],
                    },
                ],
            };
        },
        // ----- Pembatalan transaksi -----
        cancelDomain() {
            return this.monitorView === 'performance' ? 'performance' : 'parameter';
        },
        monitorRows() {
            return this.monitorView === 'performance' ? this.performanceRows : this.submissionRows;
        },
        // Hanya baris yang benar-benar PUNYA transaksi final yang bisa dibatalkan.
        // Draft / belum submit / tanpa parameter tidak punya apa pun untuk dibatalkan.
        cancellableRows() {
            return this.monitorRows.filter((r) => this.cancelRowId(r) != null);
        },
        // Perkakas pembatalan tidak berlaku untuk agregat YTD.
        pembatalanBerlaku() {
            return !(this.monitorView === 'performance' && this.penilaianMode === 'ytd');
        },
        // Dihitung dari meta (bukan dari baris yang kebetulan tampil), karena baris
        // terpilih bisa ada di halaman lain atau tersaring keluar oleh filter.
        cancelApprovedCount() {
            return this.cancelSelectedIds.filter((id) => this.cancelSelectedMeta[id]?.status === 'approved').length;
        },
        // Berapa dari yang terpilih sedang tidak terlihat di halaman ini.
        cancelHiddenCount() {
            const visible = this.cancellableRows.map((r) => this.cancelRowId(r));
            return this.cancelSelectedIds.filter((id) => !visible.includes(id)).length;
        },
        cancelAllChecked() {
            return (
                this.cancellableRows.length > 0 &&
                this.cancellableRows.every((r) => this.cancelSelectedIds.includes(this.cancelRowId(r)))
            );
        },
        // ----- Export -----
        exportScopeOptions() {
            return this.exportJenis === 'parameter' ? this.submissionYears : this.penilaianPeriods;
        },
        exportStatusOptions() {
            const common = [
                { value: 'belum_submit', label: this.exportJenis === 'performance' ? 'Belum Update' : 'Belum Submit' },
                { value: 'tidak_submit', label: this.exportJenis === 'performance' ? 'Tidak Update' : 'Tidak Submit' },
                { value: 'waiting', label: 'Menunggu Approval' },
                { value: 'rejected', label: 'Ditolak' },
                { value: 'approved', label: 'Disetujui' },
            ];
            if (this.exportJenis === 'performance') {
                common.splice(2, 0, { value: 'draft', label: 'Draft' });
                common.push(
                    { value: 'tanpa_target', label: 'Tidak Ada Target' },
                    { value: 'tanpa_parameter', label: 'Tidak Ada Parameter' },
                );
            }
            return common;
        },
        exportFilterConfig() {
            const opt = (k) => (this.exportFilterOptions?.[k] || []).map((o) => ({ value: o, label: o }));
            return {
                searchable: true,
                searchPlaceholder: 'Cari nama / kode karyawan…',
                showResultsCount: false,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: false,
                filters: [
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: this.exportStatusOptions,
                    },
                    {
                        key: 'lokasi',
                        label: 'Lokasi',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: opt('lokasi'),
                    },
                    {
                        key: 'divisi',
                        label: 'Divisi',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: opt('divisi'),
                    },
                    {
                        key: 'departemen',
                        label: 'Departemen',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: opt('departemen'),
                    },
                    {
                        key: 'level',
                        label: 'Level',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 2,
                        options: opt('level'),
                    },
                    {
                        key: 'jabatan',
                        label: 'Jabatan',
                        type: 'select',
                        multiple: true,
                        filterable: true,
                        col: 3,
                        options: opt('jabatan'),
                    },
                    {
                        key: 'tgl_masuk',
                        label: 'Tgl Masuk',
                        type: 'daterange',
                        col: 4,
                        format: 'DD/MM/YYYY',
                        valueFormat: 'YYYY-MM-DD',
                        startPlaceholder: 'Masuk dari',
                        endPlaceholder: 'Sampai',
                    },
                ],
            };
        },
        // Hanya karyawan yang punya transaksi yang bisa di-export (ada detail).
        exportSelectableRows() {
            return this.exportRows.filter((r) => this.exportRowSelectable(r));
        },
        exportAllChecked() {
            // Mode "centang semua" lintas halaman -> header selalu tercentang.
            if (this.exportSelectAllMode) return true;
            return (
                this.exportSelectableRows.length > 0 &&
                this.exportSelectableRows.every((r) => this.exportSelectedIds.includes(this.exportRowId(r)))
            );
        },
        // Tombol export aktif bila: mode semua, ATAU ada minimal 1 baris terpilih.
        exportHasSelection() {
            return this.exportSelectAllMode || this.exportSelectedIds.length > 0;
        },
    },
    async mounted() {
        // Auto-pilih scope induk terbaru (tahun submission, periode performance & enforce).
        if (this.submissionYears.length) this.selectedSubmissionYear = this.submissionYears[0].value;
        if (this.penilaianPeriods.length) {
            this.selectedPenilaianPeriode = this.penilaianPeriods[0].value;
            this.selectedEnforcePeriode = this.penilaianPeriods[0].value;
        }
        // selectedEnforceSchedule default '' = Otomatis (target per-user).

        // Muat daftar penjadwalan dalam scope (untuk dropdown).
        await Promise.all([
            this.loadSchedulesInScope('submission'),
            this.loadSchedulesInScope('penilaian'),
            this.loadEnforceSchedulesInScope(),
        ]);
        this.loadMonitorFilterOptions();
        this.reloadMonitor();

        // Resume batch progress yang masih berjalan (persistent)
        const savedBatch = localStorage.getItem(LS_KEY);
        if (savedBatch) {
            this.activeBatchId = savedBatch;
            this.fetchBatchProgress().then(() => this.startPolling());
        }
    },
    beforeUnmount() {
        // Timer mati, tapi localStorage tetap -> resume saat halaman dibuka lagi
        this.stopPolling();
    },
    methods: {
        /** Karyawan.Tgl_Masuk (ISO dari server) → "5 Januari 2021". */
        tglMasuk(v) {
            return tanggalPanjang(v);
        },
        getUserInitials(name) {
            if (!name) return '—';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return parts[0][0].toUpperCase();
        },
        // Kelas pill status (selaras dengan status-pill is-* yang sudah ada).
        statusPillClass(status) {
            const map = {
                approved: 'is-approved',
                waiting: 'is-waiting',
                draft: 'is-draft',
                rejected: 'is-rejected',
                belum_submit: 'is-belum',
                tidak_submit: 'is-tidak',
                tanpa_parameter: 'is-tanpa-param',
                tanpa_target: 'is-tanpa-target',
                cancelled: 'is-cancelled',
            };
            return map[status] || 'is-waiting';
        },

        // ---------- Pembatalan transaksi ----------
        // Id transaksi yang dibatalkan tergantung sub-view aktif. null = tidak ada
        // transaksi (belum submit / draft / tanpa parameter) -> tidak bisa dibatalkan.
        cancelRowId(row) {
            if (this.monitorView === 'performance') {
                return row.is_draft ? null : (row.id_update_performance ?? null);
            }
            return row.id_pengajuan ?? null;
        },
        cancelRowReason(row) {
            // Baris YTD adalah agregat Jan..bulan terpilih, bukan satu transaksi.
            // Tanpa keterangan ini, checkbox yang mati terbaca seperti kerusakan.
            if (row.is_ytd) {
                return (
                    row.cancel_disabled_reason ||
                    'Mode YTD menampilkan akumulasi; pembatalan dilakukan di mode Bulanan.'
                );
            }
            if (row.is_draft) return 'Draft dibatalkan satu per satu lewat ikon di kolom Aksi.';
            return 'Karyawan ini belum punya transaksi pada scope ini.';
        },
        isCancelChecked(row) {
            const id = this.cancelRowId(row);
            return id != null && this.cancelSelectedIds.includes(id);
        },
        toggleCancelOne(row) {
            const id = this.cancelRowId(row);
            if (id == null) return;
            const i = this.cancelSelectedIds.indexOf(id);
            if (i === -1) {
                this.cancelSelectedIds.push(id);
                this.cancelSelectedMeta[id] = { nama: row.nama, status: row.status };
            } else {
                this.cancelSelectedIds.splice(i, 1);
                delete this.cancelSelectedMeta[id];
            }
        },
        /**
         * Centang semua = SELURUH transaksi yang cocok scope + filter saat ini (lintas
         * halaman), bukan cuma yang kebetulan tampil. Id-nya diambil dari server dengan
         * filter yang sama persis dengan tabel.
         */
        async toggleCancelSelectAll() {
            if (this.cancelSelectedIds.length) {
                this.clearCancelSelection();
                return;
            }

            this.isSelectingAll = true;
            try {
                const url =
                    this.monitorView === 'submission'
                        ? '/api/v1/kpi-enforcement/submission-status'
                        : '/api/v1/kpi-enforcement/performance-tracking';
                const { data } = await axios.get(url, {
                    params: {
                        ...this.monitorScopeParams(),
                        search: this.monitorSearch,
                        ...this.monitorFilters,
                        ids_only: 1,
                    },
                });
                const res = data.result;

                if (!res.total) {
                    this.notify(
                        'info',
                        'Tidak ada transaksi',
                        'Tidak ada baris yang punya transaksi untuk dibatalkan.',
                    );
                    return;
                }
                if (res.total > res.max) {
                    this.notify(
                        'error',
                        'Terlalu banyak',
                        `${res.total} transaksi cocok dengan filter ini, melebihi batas ${res.max} per pembatalan. Persempit filter Anda.`,
                    );
                    return;
                }

                this.cancelSelectedIds = res.items.map((i) => i.id);
                this.cancelSelectedMeta = {};
                res.items.forEach((i) => {
                    this.cancelSelectedMeta[i.id] = { nama: i.nama, status: i.status };
                });
            } catch (e) {
                this.notify('error', 'Gagal', 'Tidak dapat mengambil daftar transaksi untuk dicentang semua.');
            } finally {
                this.isSelectingAll = false;
            }
        },
        clearCancelSelection() {
            this.cancelSelectedIds = [];
            this.cancelSelectedMeta = {};
        },
        // Batal single dari ikon baris — tidak mengganggu pilihan bulk yang sedang aktif.
        openCancelSingle(row) {
            const id = this.cancelRowId(row);
            if (id == null) return;
            this.openCancelModal([id]);
        },
        openCancelBulk() {
            if (!this.cancelSelectedIds.length) return;
            this.openCancelModal([...this.cancelSelectedIds]);
        },
        // Draft punya id dari tabel sementara, bukan id transaksi final. Karena
        // itu ia tidak ikut centang massal (id kedua tabel bisa bertabrakan) dan
        // dibatalkan satu per satu dengan domain 'draft'. Baris YTD adalah agregat.
        draftCancelId(row) {
            if (this.monitorView !== 'performance' || !row.is_draft || row.is_ytd) return null;
            return row.id_update_performance_sementara ?? null;
        },
        openCancelDraft(row) {
            const id = this.draftCancelId(row);
            if (id == null) return;
            this.openCancelModal([id], 'draft');
        },
        async openCancelModal(ids, domain = null) {
            this.cancelModal = {
                show: true,
                domain: domain || this.cancelDomain,
                preview: null,
                executing: false,
                result: null,
                conflicts: null,
                ids,
            };
            await this.loadCancelPreview();
        },
        async loadCancelPreview() {
            this.cancelModal.preview = null;
            this.cancelModal.conflicts = null;
            this.cancelModal.result = null;
            try {
                const { data } = await axios.post('/api/v1/kpi-enforcement/cancel/preview', {
                    domain: this.cancelModal.domain,
                    ids: this.cancelModal.ids,
                });
                this.cancelModal.preview = data.result;
            } catch (e) {
                this.cancelModal.show = false;
                this.notify(
                    'error',
                    'Gagal',
                    e?.response?.data?.message || 'Tidak dapat menghitung dampak pembatalan.',
                );
            }
        },
        async confirmCancel(payload) {
            this.cancelModal.executing = true;
            try {
                const { data } = await axios.post('/api/v1/kpi-enforcement/cancel/execute', payload);
                this.cancelModal.result = data.result;
                this.clearCancelSelection();
                this.reloadMonitor();
            } catch (e) {
                // 409 = state berubah sejak preview (approver bertindak duluan).
                // Tidak ada yang terbatalkan; tampilkan konflik & tawarkan muat ulang.
                if (e?.response?.status === 409) {
                    this.cancelModal.conflicts = e.response.data.conflicts || [];
                    this.cancelModal.preview = null;
                } else {
                    this.notify('error', 'Gagal', e?.response?.data?.message || 'Pembatalan gagal diproses.');
                }
            } finally {
                this.cancelModal.executing = false;
            }
        },
        closeCancelModal() {
            if (this.cancelModal.executing) return;
            this.cancelModal.show = false;
        },

        // ---------- Riwayat pembatalan ----------
        async fetchHistory(page = 1) {
            this.isLoadingHistory = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/cancel/history', {
                    params: { page, ...this.historyFilters },
                });
                this.historyRows = data.result || [];
                this.historyMeta = { page, total_page: data.total_page || 1, total_data: data.total_data || 0 };
            } catch (e) {
                this.notify('error', 'Gagal memuat', 'Tidak dapat memuat riwayat pembatalan.');
            } finally {
                this.isLoadingHistory = false;
            }
        },
        goHistoryPage(page) {
            this.fetchHistory(page);
        },
        handleHistoryFiltersUpdate(updated) {
            this.historyFilters = { ...this.historyFilters, ...updated };
            this.fetchHistory(1);
        },
        /**
         * Buka isi transaksi yang sudah dibatalkan dari sub-tab Riwayat.
         *
         * Bisa karena pembatalan kita bersifat soft: barisnya masih utuh, dan endpoint
         * detail memang mencari by primary key tanpa memfilter Flag_Aktif/Status.
         * Dibentuk seperti baris monitoring yang transaksinya sudah tidak aktif, supaya
         * openSubmissionDetail/openPerformanceDetail bisa dipakai ulang apa adanya --
         * keduanya sudah tahu cara jatuh ke `cancellation.last.id` dan menampilkan
         * banner merah jejak pembatalan.
         */
        openHistoryDetail(row) {
            if (row.domain === 'draft') {
                this.notify(
                    'info',
                    'Draft',
                    'Draft belum pernah difinalisasi jadi transaksi, sehingga tidak ada detail parameter yang bisa ditampilkan.',
                );
                return;
            }

            const item = {
                nama: row.nama,
                kode_karyawan: row.kode_karyawan,
                no_transaksi: row.no_transaksi,
                cancellation: { count: 1, last: { id: row.id_transaksi } },
            };

            if (row.domain === 'parameter') this.openSubmissionDetail(item);
            else this.openPerformanceDetail(item);
        },
        domainLabel(domain) {
            const map = {
                parameter: 'KPI Parameter',
                performance: 'Update Performance',
                draft: 'Draft Performance',
            };
            return map[domain] || domain;
        },
        statusSebelumLabel(status) {
            const map = {
                approved: 'Disetujui',
                waiting: 'Menunggu Approval',
                rejected: 'Ditolak',
                draft: 'Draft',
            };
            return map[status] || status || '—';
        },
        formatDateTime(value) {
            if (!value) return '—';
            const d = new Date(value.replace ? value.replace(' ', 'T') : value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        // ---------- Tab & penjadwalan ----------
        handleTabChange(tabId) {
            this.activeTab = tabId;
            if (tabId === 'enforcement' && !this.unfilledRows.length) {
                this.loadFilterOptions();
                this.reloadUnfilled();
            }
        },
        async onEnforcePeriodeChange() {
            // Ganti periode -> reset pilihan, muat ulang target & daftar.
            this.selectedIds = [];
            this.selectedEnforceSchedule = ''; // kembali ke Otomatis
            await this.loadEnforceSchedulesInScope();
            this.loadFilterOptions();
            this.reloadUnfilled();
        },
        async loadEnforceSchedulesInScope() {
            if (!this.selectedEnforcePeriode) return;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/schedules-in-scope', {
                    params: { jenis: 'penilaian', periode: this.selectedEnforcePeriode },
                });
                this.enforceSchedulesInScope = data.result || [];
            } catch (e) {
                /* non-kritis */
            }
        },

        // ---------- TAB 1: scope (tahun/periode) + filter dalam ----------
        setMonitorView(view) {
            this.monitorView = view;
            // Pilihan pembatalan tidak boleh terbawa antar sub-view (id-nya beda domain).
            this.clearCancelSelection();
            if (view === 'cancellation') {
                if (!this.historyRows.length) this.fetchHistory(1);
                return;
            }
            if (view === 'export') {
                // Inisialisasi scope export default (sesuai jenis).
                if (!this.exportScope) {
                    this.exportScope =
                        this.exportJenis === 'parameter'
                            ? this.submissionYears[0]?.value || ''
                            : this.penilaianPeriods[0]?.value || '';
                }
                if (this.exportScope && !this.exportRows.length) this.loadExportUniverse();
                return;
            }
            this.monitorMeta.page = 1;
            this.monitorFilters = {
                status: [],
                riwayat: [],
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                tgl_masuk: [],
            };
            this.loadMonitorFilterOptions();
            this.reloadMonitor();
        },
        async loadSchedulesInScope(jenis) {
            const params = { jenis };
            if (jenis === 'submission') {
                if (!this.selectedSubmissionYear) return;
                params.tahun = this.selectedSubmissionYear;
            } else {
                if (!this.selectedPenilaianPeriode) return;
                params.periode = this.selectedPenilaianPeriode;
            }
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/schedules-in-scope', { params });
                if (jenis === 'submission') this.submissionSchedulesInScope = data.result || [];
                else this.penilaianSchedulesInScope = data.result || [];
            } catch (e) {
                /* non-kritis */
            }
        },
        async onSubmissionYearChange() {
            this.selectedSubmissionSchedule = ''; // reset filter dalam -> Semua
            this.monitorFilters = {
                status: [],
                riwayat: [],
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                tgl_masuk: [],
            };
            this.monitorMeta.page = 1;
            this.clearCancelSelection(); // ganti tahun = universe transaksi berbeda
            await this.loadSchedulesInScope('submission');
            this.loadMonitorFilterOptions();
            this.reloadMonitor();
        },
        async onPenilaianPeriodeChange() {
            this.selectedPenilaianSchedule = '';
            this.monitorFilters = {
                status: [],
                riwayat: [],
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                tgl_masuk: [],
            };
            this.monitorMeta.page = 1;
            this.clearCancelSelection(); // ganti periode = universe transaksi berbeda
            await this.loadSchedulesInScope('penilaian');
            this.loadMonitorFilterOptions();
            this.reloadMonitor();
        },
        onMonitorScheduleChange() {
            // Ganti filter dalam penjadwalan -> reset filter isi & muat ulang.
            this.monitorFilters = {
                status: [],
                riwayat: [],
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                tgl_masuk: [],
            };
            this.monitorMeta.page = 1;
            this.clearCancelSelection(); // ganti scope = universe transaksi berbeda
            this.loadMonitorFilterOptions();
            this.reloadMonitor();
        },
        // Pencarian, filter isi, dan pindah halaman TIDAK menghapus pilihan — id
        // transaksinya tetap sah, cuma barisnya sedang tidak tampil. Pilihan hanya
        // di-reset saat scope induk berganti (lihat clearCancelSelection di bawah).
        reloadMonitor() {
            if (this.monitorView === 'submission') this.fetchSubmission(1);
            else this.fetchPerformance(1);
        },
        goMonitorPage(page) {
            if (this.monitorView === 'submission') this.fetchSubmission(page);
            else this.fetchPerformance(page);
        },
        // Param scope+filter-dalam untuk endpoint monitoring sesuai sub-view.
        monitorScopeParams() {
            if (this.monitorView === 'submission') {
                return {
                    jenis: 'submission',
                    tahun: this.selectedSubmissionYear,
                    id_penjadwalan: this.selectedSubmissionSchedule || undefined,
                };
            }
            return {
                jenis: 'penilaian',
                periode: this.selectedPenilaianPeriode,
                id_penjadwalan: this.selectedPenilaianSchedule || undefined,
            };
        },
        async loadMonitorFilterOptions() {
            const p = this.monitorScopeParams();
            if ((p.jenis === 'submission' && !p.tahun) || (p.jenis === 'penilaian' && !p.periode)) return;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/monitor-filter-options', { params: p });
                this.monitorFilterOptions = data.result || this.monitorFilterOptions;
            } catch (e) {
                /* opsi non-kritis */
            }
        },
        handleMonitorFiltersUpdate(updated) {
            this.monitorFilters = { ...this.monitorFilters, ...updated };
            this.monitorMeta.page = 1;
            this.reloadMonitor();
        },
        async fetchSubmission(page = 1) {
            if (!this.selectedSubmissionYear) return;
            this.isLoadingMonitor = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/submission-status', {
                    params: {
                        tahun: this.selectedSubmissionYear,
                        id_penjadwalan: this.selectedSubmissionSchedule || undefined,
                        search: this.monitorSearch,
                        page,
                        ...this.monitorFilters,
                    },
                });
                this.submissionRows = data.result || [];
                this.monitorMeta = { page, total_page: data.total_page || 1, total_data: data.total_data || 0 };
            } catch (e) {
                this.notify('error', 'Gagal memuat', 'Tidak dapat memuat status submission KPI.');
            } finally {
                this.isLoadingMonitor = false;
            }
        },
        onPenilaianModeChange() {
            // Centang yang sudah terlanjur diambil menunjuk transaksi bulanan;
            // begitu cakupan berubah, id-nya bukan lagi universe yang sama.
            this.clearCancelSelection();
            this.monitorMeta.page = 1;
            this.reloadMonitor();
        },
        async fetchPerformance(page = 1) {
            if (!this.selectedPenilaianPeriode) return;
            this.isLoadingMonitor = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/performance-tracking', {
                    params: {
                        periode: this.selectedPenilaianPeriode,
                        mode: this.penilaianMode,
                        id_penjadwalan: this.selectedPenilaianSchedule || undefined,
                        search: this.monitorSearch,
                        page,
                        ...this.monitorFilters,
                    },
                });
                this.performanceRows = (data.result || []).map((row) => ({
                    ...row,
                    status_card: row.status_label || (row.approved ? 'Disetujui' : 'Pending'),
                }));
                this.monitorMeta = { page, total_page: data.total_page || 1, total_data: data.total_data || 0 };
            } catch (e) {
                this.notify('error', 'Gagal memuat', 'Tidak dapat memuat performance tracking.');
            } finally {
                this.isLoadingMonitor = false;
            }
        },

        // ---------- Detail modal (submission / performance) ----------
        async openSubmissionDetail(item) {
            // Tidak ada pengajuan aktif. Bila ternyata pernah dibatalkan, buka transaksi
            // yang dibatalkan itu -> admin paham kenapa baris ini kosong.
            const idPengajuan = item.id_pengajuan || item.cancellation?.last?.id;
            if (!idPengajuan) {
                this.notify('info', 'Belum Submit', `${item.nama} belum membuat pengajuan KPI untuk penjadwalan ini.`);
                return;
            }
            this.detailType = 'submission';
            // Membuka transaksi yang dibatalkan -> badge "Dibatalkan", bukan status
            // baris (yang kini "Belum Submit" justru KARENA pembatalan itu).
            const isCancelled = !item.id_pengajuan;
            const statusMeta = isCancelled
                ? { status: 'cancelled', status_label: 'Dibatalkan', approved: false }
                : {
                      status: item.status,
                      status_label: item.status_label,
                      approved: item.status === 'approved',
                  };
            this.detailHeader = {
                nama: item.nama,
                kode_karyawan: item.kode_karyawan,
                no_transaksi: item.no_transaksi,
                ...statusMeta,
            };
            this.detailParameters = [];
            this.detailFlowSteps = [];
            // Disimpan supaya detailnya bisa dimuat ulang setelah koreksi tanpa
            // menyusun ulang argumennya.
            this.detailItemTerakhir = item;
            this.showDetailModal = true;
            this.detailLoading = true;
            let noTransaksi = item.no_transaksi;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/submission-detail', {
                    params: { id_pengajuan: idPengajuan },
                });
                this.detailHeader = { ...data.result.header, ...statusMeta };
                this.detailParameters = data.result.parameters;
                noTransaksi = data.result.header.no_transaksi;
                await this.muatKelayakanTarget();
            } catch (e) {
                this.notify('error', 'Gagal', 'Tidak dapat memuat detail pengajuan.');
            } finally {
                this.detailLoading = false;
            }
            // Alur approval (domain parameter) — dimuat paralel.
            this.loadApprovalFlow(noTransaksi, 'parameter');
        },
        // ---- Koreksi nilai target -------------------------------------------

        async muatKelayakanTarget() {
            const ids = this.detailParameters
                .flatMap((p) => p.targets || [])
                .map((t) => t.id_pengajuan_det)
                .filter((v) => Number.isInteger(v) && v > 0);

            this.kelayakanTarget = {};
            this.koreksiTarget = {};
            this.koreksiSebab = '';
            this.koreksiDitolak = [];

            if (!ids.length) return;

            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/target/kelayakan', {
                    params: { ids },
                });
                this.kelayakanTarget = data?.result ?? {};
            } catch (e) {
                // Gagal-tertutup. Tanpa jawaban kelayakan, TIDAK ADA sel yang
                // boleh disunting: lebih baik admin tak bisa mengoreksi daripada
                // mengoreksi target yang ternyata sudah dinilai atau sedang diisi.
                this.kelayakanTarget = {};
                if (e?.response?.status !== 403) {
                    console.error('Gagal mengambil kelayakan koreksi target:', e);
                }
            }
        },

        bukaKoreksiTarget() {
            // Mulai bersih setiap kali dibuka: sisa ketikan dari kunjungan
            // sebelumnya tidak boleh ikut tersimpan diam-diam.
            this.koreksiTarget = {};
            this.koreksiSebab = '';
            this.koreksiDitolak = [];
            this.koreksiPeringatan = [];
            this.koreksiSearchText = '';
            this.koreksiFilterTab = 'semua';
            this.showKoreksiModal = true;
        },

        tutupKoreksiTarget() {
            this.showKoreksiModal = false;
            this.koreksiTarget = {};
            this.koreksiSebab = '';
            this.koreksiDitolak = [];
            this.koreksiPeringatan = [];
            this.koreksiSearchText = '';
            this.koreksiFilterTab = 'semua';
        },

        catatKoreksi(baris, nilai) {
            const bersih = String(nilai).trim();

            // Kembali ke nilai semula = bukan koreksi. Membiarkannya tercatat
            // membuat simpan menulis jejak untuk perubahan yang tidak terjadi.
            if (bersih === '' || Number(bersih) === Number(baris.nilai)) {
                delete this.koreksiTarget[baris.id];
                this.koreksiTarget = { ...this.koreksiTarget };
                return;
            }

            this.koreksiTarget = { ...this.koreksiTarget, [baris.id]: bersih };
        },

        resetKoreksiBaris(baris) {
            if (this.koreksiTarget[baris.id] !== undefined) {
                delete this.koreksiTarget[baris.id];
                this.koreksiTarget = { ...this.koreksiTarget };
            }
        },

        resetSemuaKoreksi() {
            this.koreksiTarget = {};
        },

        setSebabCepat(alasan) {
            this.koreksiSebab = alasan;
        },

        toggleCollapseParam(paramId) {
            this.koreksiCollapsedParams = {
                ...this.koreksiCollapsedParams,
                [paramId]: !this.koreksiCollapsedParams[paramId],
            };
        },

        toggleAllParams() {
            const shouldCollapse = !this.isAllCollapsed;
            const map = {};
            if (shouldCollapse) {
                this.parametersKoreksi.forEach((p) => {
                    map[p.id_parameter] = true;
                });
            }
            this.koreksiCollapsedParams = map;
        },

        hitungDelta(baris) {
            if (this.koreksiTarget[baris.id] === undefined) return null;
            const baru = Number(this.koreksiTarget[baris.id]);
            const lama = Number(baris.nilai);
            if (isNaN(baru) || isNaN(lama)) return null;
            const diff = Number((baru - lama).toFixed(4));
            const diffPercent = lama !== 0 ? ((diff / Math.abs(lama)) * 100).toFixed(1) : null;
            return {
                diff: diff > 0 ? `+${diff}` : `${diff}`,
                diffPercent: diffPercent !== null ? (diff > 0 ? `+${diffPercent}%` : `${diffPercent}%`) : null,
                isNaik: diff > 0,
                isTurun: diff < 0,
                isSama: diff === 0,
            };
        },

        async simpanKoreksiTarget(sebagian = false, abaikanPeringatan = false) {
            const perubahan = Object.entries(this.koreksiTarget).map(([id, nilai]) => ({
                id_pengajuan_det: Number(id),
                nilai_baru: Number(nilai),
            }));

            if (!perubahan.length) return;

            if (!this.koreksiSebab.trim()) {
                this.notify('error', 'Sebab wajib diisi', 'Koreksi target harus menyebutkan alasannya.');
                return;
            }

            this.koreksiMenyimpan = true;
            this.koreksiDitolak = [];
            if (!abaikanPeringatan) this.koreksiPeringatan = [];

            try {
                const params = {};
                if (sebagian) params.sebagian = 1;
                if (abaikanPeringatan) params.abaikan_peringatan = 1;

                const { data } = await axios.post(
                    '/api/v1/kpi-enforcement/target/koreksi',
                    { sebab: this.koreksiSebab.trim(), perubahan },
                    { params },
                );

                this.koreksiPeringatan = [];

                const n = data?.result?.diterapkan?.length ?? 0;
                const ditolak = data?.result?.ditolak ?? [];
                this.koreksiDitolak = ditolak;

                this.notify(
                    'success',
                    'Koreksi diterapkan',
                    ditolak.length ? `${n} target dikoreksi, ${ditolak.length} ditolak.` : `${n} target dikoreksi.`,
                );

                this.showKoreksiModal = false;

                if (this.detailItemTerakhir) {
                    await this.openSubmissionDetail(this.detailItemTerakhir);
                }
            } catch (e) {
                const hasil = e?.response?.data?.result;

                if (e?.response?.status === 422 && hasil?.peringatan?.length) {
                    // Peringatan, BUKAN penolakan. Nilainya tidak ditolak —
                    // hanya menuntut pengakuan sekali, supaya koreksi tidak
                    // melahirkan salah ketik berikutnya.
                    this.koreksiPeringatan = hasil.peringatan;
                    this.notify(
                        'warning',
                        'Nilai baru terlihat menyimpang',
                        'Belum ada yang diterapkan. Periksa perbandingannya di bawah.',
                    );
                } else if (e?.response?.status === 422 && hasil?.ditolak) {
                    // Gagal-tertutup dari server: TIDAK ADA yang diterapkan.
                    // Daftar penolakannya ditampilkan apa adanya, dan penerapan
                    // sebagian hanya terjadi bila admin menekan tombolnya sendiri.
                    this.koreksiDitolak = e.response.data.result.ditolak;
                    this.notify(
                        'error',
                        'Sebagian target tidak layak',
                        'Tidak ada yang diterapkan. Periksa daftar penolakan di bawah.',
                    );
                } else {
                    this.notify('error', 'Gagal', e?.response?.data?.message || 'Koreksi target gagal disimpan.');
                }
            } finally {
                this.koreksiMenyimpan = false;
            }
        },

        async openPerformanceDetailYtd(item) {
            // Baris YTD adalah agregat Jan..bulan terpilih: tidak punya satu
            // transaksi, jadi rinciannya diambil dari sumber yang sama dengan
            // skornya (KpiAkumulasiYtdService), bukan dari detail bulanan.
            this.detailType = 'performance_ytd';
            this.detailHeader = { nama: item.nama, kode_karyawan: item.kode_karyawan };
            this.detailParameters = [];
            this.detailFlowSteps = [];
            this.ytdParameterTerbuka = false;
            this.showDetailModal = true;
            this.detailLoading = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/performance-detail-ytd', {
                    params: { id_user: item.id_user, periode: this.selectedPenilaianPeriode },
                });
                this.detailHeader = data.result.header;
                this.detailParameters = data.result.parameters;
            } catch (e) {
                this.notify('error', 'Gagal', 'Tidak dapat memuat rincian YTD.');
            } finally {
                this.detailLoading = false;
            }
        },
        formatAngkaYtd(nilai) {
            if (nilai === null || nilai === undefined) return '—';
            return Number(nilai).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        },
        labelBulanYtd(periode) {
            const nama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return nama[Number(String(periode).slice(5, 7)) - 1] || periode;
        },
        async openPerformanceDetail(item) {
            if (item.is_ytd) {
                this.openPerformanceDetailYtd(item);
                return;
            }
            // Draft -> sudah diisi tapi belum difinalisasi; belum ada record final
            // sehingga detail (parameter + approval) belum tersedia.
            if (item.is_draft) {
                this.notify(
                    'info',
                    'Masih Draft',
                    `${item.nama} sudah mengisi performance namun belum difinalisasi, sehingga detail belum tersedia.`,
                );
                return;
            }
            // Tidak ada record aktif. Bila pernah dibatalkan, buka transaksi yang
            // dibatalkan itu agar admin paham kenapa baris ini kosong.
            const idUp = item.id_update_performance || item.cancellation?.last?.id;
            if (!idUp) {
                this.notify(
                    'info',
                    'Belum Update',
                    `${item.nama} belum mengisi update performance untuk penjadwalan ini.`,
                );
                return;
            }
            const isCancelled = !item.id_update_performance;
            this.detailType = 'performance';
            this.detailHeader = {
                nama: item.nama,
                kode_karyawan: item.kode_karyawan,
                no_transaksi: item.no_transaksi,
                approved: isCancelled ? false : item.approved,
            };
            this.detailParameters = [];
            this.detailFlowSteps = [];
            this.showDetailModal = true;
            this.detailLoading = true;
            let noTransaksi = item.no_transaksi;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/performance-detail', {
                    params: { id_update_performance: idUp },
                });
                this.detailHeader = data.result.header;
                this.detailParameters = data.result.parameters;
                noTransaksi = data.result.header.no_transaksi;
            } catch (e) {
                this.notify('error', 'Gagal', 'Tidak dapat memuat detail performance.');
            } finally {
                this.detailLoading = false;
            }
            this.loadApprovalFlow(noTransaksi, 'performance');
        },
        async loadApprovalFlow(noTransaksi, domain) {
            if (!noTransaksi) {
                this.detailFlowSteps = [];
                return;
            }
            this.detailFlowLoading = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/approval-flow', {
                    params: { no_transaksi: noTransaksi, domain },
                });
                this.detailFlowSteps = data.result.steps || [];
            } catch (e) {
                this.detailFlowSteps = [];
            } finally {
                this.detailFlowLoading = false;
            }
        },
        closeDetailModal() {
            this.showDetailModal = false;
        },

        // ---------- Export sub-tab ----------
        exportRowId(row) {
            // id_user stabil sebagai identitas baris (semua baris universe punya
            // id_user, termasuk yang belum mengisi). Fallback ke kode bila perlu.
            return row.id_user ?? row.id_pengajuan ?? row.id_update_performance ?? row.kode_karyawan;
        },
        exportRowSelectable(row) {
            // Semua karyawan terjadwal bisa di-export — termasuk yang BELUM/Tidak
            // mengisi (diberi baris status di hasil export). Syaratnya punya id_user.
            return row.id_user != null;
        },
        isExportChecked(row) {
            if (!this.exportRowSelectable(row)) return false;
            // Mode "semua" -> setiap baris selectable tampil tercentang.
            if (this.exportSelectAllMode) return true;
            return this.exportSelectedIds.includes(this.exportRowId(row));
        },
        toggleExportOne(row) {
            if (!this.exportRowSelectable(row)) return;
            // Keluar dari mode "semua" -> materialisasi jadi pilihan eksplisit per
            // baris halaman aktif, lalu toggle baris ini. (Pindah dari "semua" ke
            // pilih sebagian = pakai daftar id eksplisit, bukan kriteria.)
            if (this.exportSelectAllMode) {
                this.exportSelectAllMode = false;
                this.exportSelectedIds = this.exportSelectableRows.map((r) => this.exportRowId(r));
            }
            const id = this.exportRowId(row);
            const i = this.exportSelectedIds.indexOf(id);
            if (i === -1) this.exportSelectedIds.push(id);
            else this.exportSelectedIds.splice(i, 1);
        },
        toggleExportSelectAll() {
            // Instan: cukup nyalakan/matikan mode "semua" (tanpa narik data).
            // Worker yang resolve id_user dari scope+filter saat job diproses.
            if (this.exportSelectAllMode || this.exportAllChecked) {
                this.exportSelectAllMode = false;
                this.exportSelectedIds = [];
            } else {
                this.exportSelectAllMode = true;
                this.exportSelectedIds = [];
            }
        },
        onExportJenisChange() {
            this.exportScope = '';
            this.exportRows = [];
            this.exportSelectedIds = [];
            this.exportSelectAllMode = false;
            this.exportNama = '';
            this.exportMeta = { page: 1, total_page: 1, total_data: 0 };
            this.exportIdToUserMap = {};
            this.exportFilters = {
                lokasi: [],
                divisi: [],
                departemen: [],
                level: [],
                jabatan: [],
                status: [],
                search: '',
                tgl_masuk: [],
            };
        },
        handleExportFiltersUpdate(updated) {
            this.exportFilters = { ...this.exportFilters, ...updated };
            this.exportMeta.page = 1;
            // Filter berubah -> kriteria "semua" ikut berubah; reset agar tidak
            // mengirim mode "semua" dengan filter lama.
            this.exportSelectAllMode = false;
            this.loadExportUniverse(1);
        },
        async loadExportUniverse(page = 1, resetSelections = true) {
            if (!this.exportScope) return;
            this.isLoadingExport = true;
            if (resetSelections) {
                this.exportSelectedIds = [];
                this.exportIdToUserMap = {};
                this.exportSelectAllMode = false;
            }
            try {
                const scopeParam =
                    this.exportJenis === 'parameter' ? { tahun: this.exportScope } : { periode: this.exportScope };
                const { data } = await axios.get('/api/v1/kpi-enforcement/export/universe', {
                    params: {
                        jenis: this.exportJenis,
                        page,
                        ...scopeParam,
                        ...this.exportFilters,
                        ...(this.exportJenis === 'performance' ? { mode: this.exportMode } : {}),
                    },
                });
                this.exportRows = data.result || [];
                this.exportMeta = { page, total_page: data.total_page || 1, total_data: data.total_data || 0 };
                // Build cross-page ID → id_user lookup for requestExport
                (data.result || []).forEach((r) => {
                    this.exportIdToUserMap[this.exportRowId(r)] = r.id_user;
                });
                // Muat opsi filter (reuse monitor-filter-options dgn jenis penjadwalan sesuai).
                this.loadExportFilterOptions();
            } catch (e) {
                this.notify('error', 'Gagal memuat', 'Tidak dapat memuat daftar karyawan untuk export.');
            } finally {
                this.isLoadingExport = false;
            }
        },
        goExportPage(page) {
            this.loadExportUniverse(page, false);
        },
        async loadExportFilterOptions() {
            if (!this.exportScope) return;
            try {
                const jenis = this.exportJenis === 'parameter' ? 'submission' : 'penilaian';
                const scopeParam =
                    this.exportJenis === 'parameter' ? { tahun: this.exportScope } : { periode: this.exportScope };
                const { data } = await axios.get('/api/v1/kpi-enforcement/monitor-filter-options', {
                    params: { jenis, ...scopeParam },
                });
                this.exportFilterOptions = data.result || this.exportFilterOptions;
            } catch (e) {
                /* non-kritis */
            }
        },
        async requestExport() {
            if (!this.exportHasSelection) return;

            // Payload dasar. Mode "semua" -> kirim kriteria (select_all + filter),
            // worker (Cloud Task) yang resolve id_user. Mode manual -> kirim id_users.
            const payload = {
                jenis: this.exportJenis,
                format: this.exportFormat,
                scope_value: this.exportScope,
            };

            // Tanpa baris ini berkasnya berisi satu bulan sementara daftar yang
            // barusan dipilih admin berasal dari akumulasi — dua himpunan berbeda,
            // tanpa satu pun tanda di layar.
            if (this.exportJenis === 'performance') payload.mode = this.exportMode;

            const nama = (this.exportNama || '').trim();
            if (nama) payload.nama = nama;

            if (this.exportSelectAllMode) {
                payload.select_all = true;
                payload.filters = { ...this.exportFilters };
                // Total sudah diketahui dari tabel universe (sudah ikut filter status).
                // Dikirim agar label panel langsung akurat, tanpa hitung-ulang mahal di
                // server (yang tak bisa hitung status & sebabkan "59 -> 5" saat selesai).
                payload.expected_total = this.exportMeta.total_data || 0;
            } else {
                const idUsers = this.exportSelectedIds.map((id) => this.exportIdToUserMap[id]).filter((v) => v != null);
                if (!idUsers.length) {
                    this.notify('error', 'Tidak valid', 'Karyawan terpilih tidak memiliki transaksi untuk di-export.');
                    return;
                }
                payload.id_users = idUsers;
            }

            this.isRequestingExport = true;
            try {
                await axios.post('/api/v1/kpi-enforcement/export/request', payload);
                // Nyalakan polling Activity Center global (ikon awan di topbar) —
                // entri export baru akan muncul & terpantau di panel terpusat.
                useActivityCenter().notifyNewTask();
                this.notify('success', 'Diproses', 'Export sedang disiapkan di background.');
                // Reset pilihan & nama export agar siap untuk request berikutnya.
                this.exportSelectedIds = [];
                this.exportSelectAllMode = false;
                this.exportNama = '';
            } catch (e) {
                this.notify('error', 'Gagal', e?.response?.data?.message || 'Gagal menyiapkan export.');
            } finally {
                this.isRequestingExport = false;
            }
        },
        // Daftar/aksi export (download, retry, dismiss, polling) kini ditangani
        // terpusat oleh Pusat Aktivitas (useActivityCenter) di topbar.

        // ---------- TAB 2 ----------
        async loadFilterOptions() {
            if (!this.selectedEnforcePeriode) return;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/filter-options', {
                    params: { periode: this.selectedEnforcePeriode },
                });
                this.filterOptions = data.result || this.filterOptions;
            } catch (e) {
                /* opsi filter non-kritis */
            }
        },
        reloadUnfilled() {
            this.fetchUnfilled(1);
        },
        goUnfilledPage(page) {
            this.fetchUnfilled(page);
        },
        async fetchUnfilled(page = 1) {
            if (!this.selectedEnforcePeriode) return;
            this.isLoadingUnfilled = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/unfilled', {
                    params: { periode: this.selectedEnforcePeriode, page, ...this.filters },
                });
                this.unfilledRows = data.result || [];
                this.unfilledMeta = { page, total_page: data.total_page || 1, total_data: data.total_data || 0 };
            } catch (e) {
                this.notify('error', 'Gagal memuat', 'Tidak dapat memuat daftar karyawan belum mengisi.');
            } finally {
                this.isLoadingUnfilled = false;
            }
        },
        resetFilters() {
            this.filters = { lokasi: [], divisi: [], departemen: [], level: [], jabatan: [] };
            this.reloadUnfilled();
        },
        handleFiltersUpdate(updatedFilters) {
            this.filters = { ...updatedFilters };
            this.reloadUnfilled();
        },

        // ---------- Selection ----------
        isChecked(id) {
            return this.selectedIds.includes(id);
        },
        toggleOne(id) {
            const i = this.selectedIds.indexOf(id);
            if (i === -1) this.selectedIds.push(id);
            else this.selectedIds.splice(i, 1);
        },
        clearEnforceSelection() {
            this.selectedIds = [];
        },
        /**
         * Centang judul kolom = SELURUH karyawan yang cocok scope + filter saat ini,
         * lintas halaman. Sebelumnya hanya baris yang kebetulan tampil yang ikut,
         * sehingga admin yang mengira sudah memilih semuanya hanya meng-enforce satu
         * halaman — dan sisanya diam-diam tertinggal.
         *
         * Id-nya diambil dari server dengan filter yang sama persis dengan tabel,
         * pola yang sudah dipakai centang-semua di tab Monitoring.
         */
        async toggleSelectAll() {
            // Hanya kosongkan bila SEMUA sudah tercentang. Dengan pilihan sebagian,
            // centang judul kolom artinya "lengkapi jadi semua" — mengosongkannya
            // justru membuang pilihan yang barusan dibuat admin satu per satu.
            if (this.allChecked) {
                this.clearEnforceSelection();

                return;
            }

            this.isSelectingAll = true;
            try {
                const { data } = await axios.get('/api/v1/kpi-enforcement/unfilled', {
                    params: {
                        periode: this.selectedEnforcePeriode,
                        ...this.filters,
                        ids_only: 1,
                    },
                });
                const res = data.result;

                if (!res.total) {
                    this.notify('info', 'Tidak ada karyawan', 'Tidak ada karyawan yang cocok dengan filter ini.');

                    return;
                }
                if (res.total > res.max) {
                    this.notify(
                        'error',
                        'Terlalu banyak',
                        `${res.total} karyawan cocok dengan filter ini, melebihi batas ${res.max} per sekali eksekusi. Persempit filter Anda.`,
                    );

                    return;
                }

                this.selectedIds = res.items.map((i) => i.id);
            } catch (e) {
                this.notify('error', 'Gagal', 'Tidak dapat mengambil daftar karyawan untuk dicentang semua.');
            } finally {
                this.isSelectingAll = false;
            }
        },

        // ---------- Enforcement ----------
        async openPreview() {
            if (!this.selectedIds.length) return;
            this.preview = null;
            this.expandedEmp = null;
            this.loadingEmpDetail = null;
            this.empDetailCache = {};
            this.statsOnDemand = false;
            this.showPreviewModal = true;
            try {
                const { data } = await axios.post('/api/v1/kpi-enforcement/preview', {
                    periode: this.selectedEnforcePeriode,
                    id_users: this.selectedIds,
                });
                this.preview = data.result;
                // Langsung muat stat (score/achievement) tiap karyawan agar tampil
                // di list tanpa harus klik. Accordion detail tetap pakai cache ini.
                this.prefetchAllEmpStats();
            } catch (e) {
                this.showPreviewModal = false;
                this.notify('error', 'Gagal', 'Tidak dapat membuat preview eksekusi.');
            }
        },
        /**
         * Prefetch HEADER (total score/achievement) tiap karyawan agar stat tampil
         * tanpa klik — tapi HANYA untuk pilihan kecil.
         *
         * Tiap karyawan berarti satu permintaan tersendiri. Sejak centang-semua
         * bekerja lintas halaman, satu klik bisa berarti ratusan permintaan
         * sekaligus: modalnya berubah jadi dinding "menghitung…" yang mencair
         * pelan-pelan, dan angka yang sudah tampil pun tidak dibaca orang saat
         * daftarnya sepanjang itu. Di atas ambang ini simulasi dimuat saat baris
         * dibuka, dan daftarnya menyebutkan hal itu.
         */
        async prefetchAllEmpStats() {
            if (!this.preview || !this.preview.employees) return;
            const ids = this.preview.employees.map((e) => e.id_user);

            this.statsOnDemand = ids.length > this.PREFETCH_MAX;
            if (this.statsOnDemand) return;

            const CONCURRENCY = 5;
            for (let i = 0; i < ids.length; i += CONCURRENCY) {
                const batch = ids.slice(i, i + CONCURRENCY).map((id) => this.fetchEmpHeader(id));
                await Promise.allSettled(batch);
            }
        },
        async fetchEmpHeader(idUser) {
            if (this.empDetailCache[idUser]) return; // sudah ada header/detail
            try {
                const { data } = await axios.post('/api/v1/kpi-enforcement/preview-detail', {
                    periode: this.selectedEnforcePeriode,
                    id_user: idUser,
                    with_details: false,
                });
                this.empDetailCache = { ...this.empDetailCache, [idUser]: data.result };
            } catch (e) {
                this.empDetailCache = {
                    ...this.empDetailCache,
                    [idUser]: { status: 'error', message: 'Gagal memuat simulasi.' },
                };
            }
        },
        async toggleEmpDetail(idUser) {
            // Tutup bila sudah terbuka
            if (this.expandedEmp === idUser) {
                this.expandedEmp = null;
                return;
            }
            this.expandedEmp = idUser;

            const cached = this.empDetailCache[idUser];
            // Detail sudah lengkap (punya array details) -> tidak fetch lagi.
            if (cached && cached.details) return;
            // Header error -> tidak ada gunanya minta detail.
            if (cached && cached.status === 'error') return;

            this.loadingEmpDetail = idUser;
            try {
                const { data } = await axios.post('/api/v1/kpi-enforcement/preview-detail', {
                    periode: this.selectedEnforcePeriode,
                    id_user: idUser,
                    with_details: true,
                });
                this.empDetailCache = { ...this.empDetailCache, [idUser]: data.result };
            } catch (e) {
                this.empDetailCache = {
                    ...this.empDetailCache,
                    [idUser]: { ...(cached || {}), status: 'error', message: 'Gagal memuat detail parameter.' },
                };
            } finally {
                if (this.loadingEmpDetail === idUser) this.loadingEmpDetail = null;
            }
        },
        closePreview() {
            if (this.isEnforcing) return;
            this.showPreviewModal = false;
        },
        async confirmEnforce() {
            this.isEnforcing = true;
            try {
                const { data } = await axios.post('/api/v1/kpi-enforcement/enforce', {
                    periode: this.selectedEnforcePeriode,
                    // override target opsional ('' = Otomatis per-user).
                    id_penjadwalan: this.selectedEnforceSchedule || undefined,
                    id_users: this.selectedIds,
                });
                const batchId = data.result.batch_id;
                this.activeBatchId = batchId;
                localStorage.setItem(LS_KEY, batchId);
                this.batchProgress = {
                    batch_id: batchId,
                    status: 'queued',
                    total_records: data.result.total_records,
                    processed_records: 0,
                    success: 0,
                    failed: 0,
                    percent: 0,
                };
                this.showPreviewModal = false;
                this.selectedIds = [];
                this.startPolling();
                this.notify(
                    'success',
                    'Dijadwalkan',
                    `${data.result.total_records} karyawan dijadwalkan untuk auto-cap.`,
                );
            } catch (e) {
                const msg = e?.response?.data?.message || 'Gagal menjadwalkan eksekusi.';
                this.notify('error', 'Gagal', msg);
            } finally {
                this.isEnforcing = false;
            }
        },

        // ---------- Persistent polling ----------
        async fetchBatchProgress() {
            if (!this.activeBatchId) return;
            try {
                const { data } = await axios.get(`/api/v1/kpi-enforcement/batch/${this.activeBatchId}`);
                this.batchProgress = data.result;
                if (this.batchProgress && DONE_STATUSES.includes(this.batchProgress.status)) {
                    this.stopPolling();
                    localStorage.removeItem(LS_KEY);
                    // Refresh tabel terkait agar data tersinkron
                    if (this.activeTab === 'enforcement') this.reloadUnfilled();
                }
            } catch (e) {
                // batch hilang/404 -> bersihkan state persistent
                if (e?.response?.status === 404) {
                    this.stopPolling();
                    localStorage.removeItem(LS_KEY);
                    this.activeBatchId = null;
                    this.batchProgress = null;
                }
            }
        },
        startPolling() {
            this.stopPolling();
            if (!this.activeBatchId) return;
            if (this.batchProgress && DONE_STATUSES.includes(this.batchProgress.status)) return;
            this.pollTimer = setInterval(this.fetchBatchProgress, POLL_INTERVAL);
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        dismissProgress() {
            this.stopPolling();
            localStorage.removeItem(LS_KEY);
            this.activeBatchId = null;
            this.batchProgress = null;
        },

        // ---------- Feedback ----------
        notify(type, title, message) {
            this.feedback = { type, title, message };
            this.showFeedback = true;
        },
    },
};
</script>

<style scoped>
.kpi-enf-container {
    --kl-primary: var(--hcis-indigo-500);
    --kl-border: var(--hcis-slate-200);
    --kl-muted: var(--hcis-slate-500);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    /* gap: 1rem; */
}

.stitch-card {
    margin: 1rem;
    background: #fff;
    border: 1px solid var(--kl-border);
    border-radius: 16px;
    box-shadow:
        0 1px 3px rgba(15, 23, 42, 0.06),
        0 1px 2px rgba(15, 23, 42, 0.04);
}

/* Global context/filter style */
.access-context {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    /* margin: 1rem 0; */
    padding: 0.8rem 1rem;
    border: 1px solid #dfe7f3;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.group-context,
.access-context__right,
.mode-badge {
    display: flex;
    align-items: center;
}
.group-context {
    min-width: 0;
    gap: 0.75rem;
}
.group-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: none;
    border-radius: 11px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.group-select-wrapper {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.15rem;
}
.group-select-wrapper small {
    color: var(--hcis-slate-500);
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
/* Dropdown penjadwalan per sub-tab */
.sched-select {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.sched-select > small {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
}
.sched-select .gf-select-new {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 0.4rem 1.6rem 0.4rem 0.6rem;
    font-size: 0.8rem;
    min-width: 240px;
    background-color: #fff;
    background-position: right 0.5rem center;
}
.opt-inactive {
    color: var(--hcis-slate-400);
    font-style: italic;
}

/* ===== Detail modal — kolom kanan (isi transaksi) ===== */
.detail-loading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    justify-content: center;
    color: var(--hcis-slate-500);
}
.detail-section-title-r {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.param-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    margin-bottom: 0.75rem;
    background: #fff;
}
.param-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}
.param-name {
    font-weight: 700;
    color: var(--hcis-slate-800);
    font-size: 0.86rem;
}
.param-bsc {
    display: inline-block;
    margin-left: 0.4rem;
    font-size: 0.66rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-radius: 999px;
    padding: 0.05rem 0.45rem;
    font-weight: 700;
    vertical-align: middle;
}
.param-bobot {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
    border-radius: 8px;
    padding: 0.2rem 0.5rem;
}
.param-desc {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    margin: 0.4rem 0 0.5rem;
}
.param-attrs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.74rem;
    color: var(--hcis-slate-600);
    margin-top: 0.4rem;
}
.param-attrs code {
    background: var(--hcis-slate-100);
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    color: var(--hcis-slate-900);
}
.param-target-table {
    width: 100%;
    margin-top: 0.6rem;
    border-collapse: collapse;
    font-size: 0.75rem;
}
.param-target-table th {
    text-align: left;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    font-weight: 700;
    font-size: 0.66rem;
    text-transform: uppercase;
    padding: 0.35rem 0.5rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}
.param-target-table td {
    padding: 0.35rem 0.5rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    color: var(--hcis-slate-700);
}
.perf-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
}
.ps-item {
    flex: 1;
    min-width: 110px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
    display: flex;
    flex-direction: column;
    background: var(--hcis-slate-50);
}
.ps-item span {
    font-size: 0.64rem;
    text-transform: uppercase;
    color: var(--hcis-slate-400);
    font-weight: 700;
}
.ps-item strong {
    font-size: 1rem;
    color: var(--hcis-slate-800);
}
.ps-item.capped {
    background: #fae8ff;
    border-color: #f5d0fe;
}
.ps-item.capped strong {
    color: #a21caf;
}
.perf-values {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.4rem;
    margin: 0.6rem 0;
}
.pv {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 0.35rem 0.5rem;
    display: flex;
    flex-direction: column;
}
.pv span {
    font-size: 0.6rem;
    text-transform: uppercase;
    color: var(--hcis-slate-400);
    font-weight: 700;
}
.pv strong {
    font-size: 0.85rem;
    color: var(--hcis-slate-800);
}
.pv.achv {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
}
.pv.achv strong {
    color: var(--hcis-indigo-700);
}
.param-reject {
    margin-top: 0.5rem;
    font-size: 0.74rem;
    color: var(--hcis-red-700);
    background: var(--hcis-red-50);
    border-radius: 8px;
    padding: 0.4rem 0.6rem;
}
.param-pica {
    margin-top: 0.6rem;
    border-top: 1px dashed var(--hcis-slate-200);
    padding-top: 0.5rem;
}
.pica-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #c2410c;
    margin-bottom: 0.3rem;
}
.pica-item {
    font-size: 0.72rem;
    color: var(--hcis-slate-600);
    background: #fff7ed;
    border-radius: 8px;
    padding: 0.4rem 0.6rem;
    margin-bottom: 0.3rem;
}

.gf-select-new {
    border: none;
    background: transparent;
    padding: 0 1.5rem 0 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #172554;
    cursor: pointer;
    outline: none;
    box-shadow: none !important;
    width: auto;
    min-width: 220px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236366f1'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right center;
    background-size: 0.95rem;
}
.gf-select-new:focus {
    background-color: transparent;
    color: #172554;
}
.access-context__right {
    justify-content: flex-end;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.mode-badge {
    min-height: 30px;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #172554;
    color: #fff;
}

.main-content-wrapper {
    display: flex;
    justify-content: center;
}
.content-container {
    width: 100%;
}

/* Tab 2: New filter designs */
.filter-card {
    border: 1px solid var(--kl-border);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.filter-grid-new {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: end;
}
.filter-actions-new {
    display: flex;
    gap: 0.5rem;
    align-items: end;
}

/* Helper styles for initials avatar */
.user-avatar-initials-sm {
    flex-shrink: 0;
    font-family: inherit;
    font-weight: 700;
}
.bg-indigo-soft {
    background-color: var(--hcis-indigo-50) !important;
}
.text-indigo {
    color: var(--hcis-indigo-600) !important;
}
.fs-7 {
    font-size: 0.75rem;
}
.shadow-xs {
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

/* Panels */
.kpi-enf-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-top: 0.5rem;
}

.panel-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.selection-toolbar-new {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 0.25rem 0;
    background: transparent;
    border: none;
    margin-bottom: 0.5rem;
}
.search-box {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 360px;
}
.search-box i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--kl-muted);
}
.search-box .form-control {
    padding-left: 2.2rem;
    border-radius: 8px;
    font-size: 0.8rem;
    height: 34px;
}
.selection-info {
    font-size: 0.85rem;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}

/* Buttons */
.stitch-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    height: 34px;
}
.stitch-btn.primary {
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    border: none;
}
.stitch-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
}
.stitch-btn.danger {
    background: linear-gradient(135deg, var(--hcis-rose-500) 0%, var(--hcis-rose-600) 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
    border: none;
}
.stitch-btn.danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(225, 29, 72, 0.35);
}
.stitch-btn.ghost {
    background: #fff;
    color: var(--hcis-slate-600);
    border-color: var(--kl-border);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}
.stitch-btn.ghost:hover {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-800);
    border-color: var(--hcis-slate-300);
}
.stitch-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* Table */
.table-wrap {
    overflow-x: auto;
}
.kpi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}
.kpi-table thead th {
    text-align: left;
    padding: 0.7rem 0.85rem;
    background: var(--hcis-slate-50);
    color: var(--kl-muted);
    font-weight: 700;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--kl-border);
    white-space: nowrap;
}
.kpi-table tbody td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    vertical-align: middle;
    color: var(--hcis-slate-700);
}
.kpi-table tbody tr {
    transition: background-color 0.2s ease;
}
.kpi-table tbody tr:hover {
    background-color: var(--hcis-slate-50);
}
.kpi-table tbody tr.selected {
    background: var(--hcis-indigo-50);
}
.kpi-table tbody tr.selected:hover {
    background-color: var(--hcis-indigo-100);
}
.check-col {
    width: 44px;
    text-align: center;
}
.selection-clear {
    margin-left: 0.5rem;
    padding: 0;
    border: none;
    background: none;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--kl-primary);
    text-decoration: underline;
    text-underline-offset: 2px;
}
.selection-clear:hover {
    color: var(--hcis-indigo-700);
}
.emp-cell {
    display: flex;
    flex-direction: column;
}
.emp-name {
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.emp-code {
    font-size: 0.75rem;
    color: var(--kl-muted);
}
.mono {
    font-family: monospace;
    font-size: 0.8rem;
}
.empty-row {
    text-align: center;
    padding: 2.5rem 1rem !important;
    color: var(--kl-muted);
}

/* Status pills */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
    border: 1px solid transparent;
}
.status-pill.is-approved {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    border-color: var(--hcis-green-200);
}
.status-pill.is-in_progress {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.status-pill.is-waiting {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
    border-color: var(--hcis-amber-200);
}
.status-pill.is-draft {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border-color: var(--hcis-slate-200);
}
.status-pill.is-rejected {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
}
.status-pill.is-belum {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}
.status-pill.is-tidak {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
    border: 1px dashed var(--hcis-slate-300);
}
.status-pill.is-tanpa-param {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-200);
}
.status-pill.is-tanpa-target {
    background: var(--hcis-cyan-50);
    color: var(--hcis-cyan-600);
    border-color: var(--hcis-cyan-200);
}
.status-pill.is-capped {
    background: #fae8ff;
    color: #a21caf;
    border-color: #f5d0fe;
}

/* Loading */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 240px;
    gap: 0.75rem;
    color: var(--kl-muted);
}
.loading-spinner {
    width: 56px;
    height: 56px;
    background: var(--hcis-indigo-50);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--kl-primary);
}
.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Empty period */
.empty-period {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    gap: 0.75rem;
    color: var(--kl-muted);
}
.empty-period i {
    font-size: 2.5rem;
    color: var(--hcis-slate-300);
}

/* Modal simulation grid */
.kl-modal-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
/* Ringkasan tunggal di kepala modal enforce. Kalimatnya menjelaskan akibat,
   daftar faktanya menjawab "siapa, kapan, sampai berapa" tanpa harus dibaca
   sebagai kalimat. */
.cap-brief {
    border-radius: 12px;
    border: 1px solid rgba(245, 158, 11, 0.22);
    background: rgba(245, 158, 11, 0.05);
    overflow: hidden;
}
.cap-brief-lead {
    display: flex;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
}
.cap-brief-lead > i {
    color: var(--hcis-amber-600);
    font-size: 1.25rem;
    line-height: 1.2;
}
.cap-brief-title {
    margin: 0 0 0.2rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.cap-brief-sub {
    margin: 0;
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--kl-muted);
    max-width: 62ch;
}
.cap-facts {
    display: flex;
    flex-wrap: wrap;
    gap: 0 2rem;
    margin: 0;
    padding: 0.6rem 1rem 0.7rem 3.1rem;
    border-top: 1px solid rgba(245, 158, 11, 0.18);
    background: rgba(255, 255, 255, 0.55);
}
.cap-facts dt {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--kl-muted);
}
.cap-facts dd {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.emp-list-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: -0.35rem;
}
.emp-list-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
}
.emp-list-hint {
    font-size: 0.72rem;
    color: var(--kl-muted);
}
.emp-acc-ondemand {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--kl-primary);
}
@media (max-width: 575.98px) {
    .cap-facts {
        padding-left: 1rem;
        gap: 0.35rem 1.25rem;
    }
}
/* Employee list + accordion in preview modal */
.emp-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 52vh;
    overflow-y: auto;
    padding-right: 2px;
}
/* Saat satu detail dibuka, daftar berhenti jadi area scroll sendiri: seluruh isi
   mengalir ke .modal-body yang sudah scrollable. Tanpa ini tabel parameter yang
   panjang terpotong di dalam scroll bersarang setinggi 52vh. */
.emp-list.has-expanded {
    max-height: none;
    overflow: visible;
    padding-right: 0;
}
.emp-accordion {
    border: 1px solid var(--kl-border);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    transition: all 0.25s ease;
    /*
     * WAJIB. .emp-list adalah flex column ber-max-height, dan baris ini punya
     * overflow:hidden — yang membuat min-height:auto miliknya meluruh jadi 0.
     * Tanpa flex-shrink:0, flexbox MEMERAS seluruh baris agar muat dalam 52vh:
     * pada 15 baris tiap baris masih ~45px dan terlihat wajar, pada 40 baris
     * tiap baris tinggal ~4px dan daftarnya tampak kosong melompong meski
     * seluruh datanya ada. Cacat ini baru bisa muncul setelah "centang semua"
     * bekerja lintas halaman, karena sebelumnya pilihan tak pernah melebihi
     * satu halaman.
     */
    flex-shrink: 0;
}
.emp-accordion.open {
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.08);
}
.emp-acc-head {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background-color 0.2s ease;
}
.emp-acc-head:hover {
    background: var(--hcis-slate-50);
}
.emp-acc-info {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}
.emp-acc-info > i {
    color: var(--kl-primary);
    font-size: 0.8rem;
}
.emp-acc-scores {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.score-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    line-height: 1;
}
.score-chip.ach {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
}
.score-chip .sc-label {
    font-size: 0.62rem;
    text-transform: uppercase;
    color: var(--kl-muted);
    font-weight: 700;
}
.score-chip strong {
    font-size: 0.78rem;
    color: var(--hcis-slate-800);
}
.score-chip.ach .sc-label {
    color: var(--hcis-indigo-400);
}
.score-chip.ach strong {
    color: var(--hcis-indigo-600);
}
.emp-acc-body {
    padding: 0.75rem 1rem 1rem;
    border-top: 1px dashed var(--kl-border);
    background: #fafbff;
}
.emp-acc-loading,
.emp-acc-error {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.65rem;
    color: var(--kl-muted);
    font-size: 0.8rem;
}
.emp-acc-error {
    color: var(--hcis-red-700);
}
.detail-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1.2rem;
    font-size: 0.75rem;
    padding: 0.55rem 0.75rem;
    background: var(--hcis-slate-50);
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
    margin-bottom: 0.65rem;
    color: var(--kl-muted);
}
.detail-summary strong {
    color: var(--hcis-slate-800);
}
/* Modal medium hanya 600px sementara tabel punya 6 kolom — beri scroll horizontal
   di wrapper, bukan overflow:hidden di tabel yang memotong kolom kanan. */
.param-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
}
.param-table {
    width: 100%;
    min-width: 480px;
    border-collapse: collapse;
    font-size: 0.75rem;
    background: #fff;
}
.param-count {
    margin: 0.4rem 0 0;
    font-size: 0.68rem;
    color: var(--kl-muted);
    text-align: right;
}
.param-table thead th {
    text-align: left;
    padding: 0.45rem 0.65rem;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid var(--hcis-slate-200);
}
.param-table tbody td {
    padding: 0.45rem 0.65rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    color: var(--hcis-slate-700);
}
.param-table tbody tr:last-child td {
    border-bottom: none;
}
.param-table td.zero {
    font-weight: 700;
    color: var(--hcis-slate-400);
}
/* Feedback */
.kl-feedback-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    text-align: center;
    padding: 1rem 0.5rem;
}
.kl-feedback-body i {
    font-size: 2.5rem;
}
.kl-feedback-body.is-success i {
    color: var(--hcis-green-600);
}
.kl-feedback-body.is-error i {
    color: var(--hcis-red-600);
}

/* Drive-style progress card */
.drive-progress {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    width: 340px;
    max-width: calc(100vw - 2rem);
    padding: 1rem 1.1rem;
    z-index: 1500;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
}

/* Export panel (daftar export terakhir) */
.export-panel {
    width: 360px;
}
.export-list {
    margin-top: 0.65rem;
    max-height: 320px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.export-item {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 0.6rem 0.7rem;
    background: var(--hcis-slate-50);
}
.export-item-top {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}
.export-item-icon {
    font-size: 1rem;
    margin-top: 1px;
}
.export-item-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.export-item-label {
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--hcis-slate-800);
}
.export-item-sub {
    font-size: 0.7rem;
    color: var(--hcis-slate-600);
}
.export-item-time {
    font-size: 0.66rem;
    color: var(--hcis-slate-400);
}
.export-item-dismiss {
    border: none;
    background: transparent;
    color: var(--hcis-slate-300);
    cursor: pointer;
    font-size: 0.9rem;
    line-height: 1;
}
.export-item-dismiss:hover {
    color: var(--hcis-red-500);
}
.export-item-progress {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.export-item-pct {
    font-size: 0.66rem;
    color: var(--hcis-slate-500);
    white-space: nowrap;
}
.export-item-btn {
    width: 100%;
    justify-content: center;
    margin-top: 0.5rem;
    padding: 0.4rem 0.75rem;
    font-size: 0.78rem;
}
.export-item-expired {
    display: block;
    margin-top: 0.45rem;
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
}
.dp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.65rem;
}
.dp-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--hcis-slate-800);
}
.dp-title i {
    color: var(--kl-primary);
}
.dp-close {
    border: none;
    background: transparent;
    color: var(--kl-muted);
    cursor: pointer;
}
.dp-bar-track {
    height: 8px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    overflow: hidden;
}
.dp-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    transition: width 0.4s ease;
}
.dp-bar-fill.is-failed {
    background: linear-gradient(135deg, var(--hcis-rose-500) 0%, var(--hcis-rose-600) 100%);
}
.dp-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.55rem;
    font-size: 0.78rem;
    color: var(--kl-muted);
}
.dp-counts {
    display: flex;
    gap: 0.6rem;
}
.dp-counts .ok {
    color: var(--hcis-green-600);
}
.dp-counts .fail {
    color: var(--hcis-red-600);
}

.drive-pop-enter-active,
.drive-pop-leave-active {
    transition: all 0.25s ease;
}
.drive-pop-enter-from,
.drive-pop-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

@media (max-width: 768px) {
    .kpi-enf-container {
        padding: 0.65rem;
    }
    .access-context {
        flex-direction: column;
        align-items: stretch;
    }
    .gf-select-new {
        width: 100%;
    }
    .panel-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .search-box {
        max-width: 100%;
    }
    .filter-grid-new {
        grid-template-columns: 1fr;
    }
    .filter-actions-new {
        flex-direction: column;
        align-items: stretch;
    }
    .drive-progress {
        right: 0.65rem;
        left: 0.65rem;
        width: auto;
    }
}

.filter-card :deep(.filter-panel) {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
}

/* ===================== Pembatalan transaksi ===================== */

/* Bootstrap Icons merender glyph lewat `::before` yang inline-block + digeser turun
   (`vertical-align: -.125em`), jadi ikon selalu meleset di dalam tombol/badge yang
   di-center. Menjadikan <i> flex container membuat ::before jadi flex item, sehingga
   vertical-align tidak berlaku lagi dan ikon center pada kedua sumbu.
   Sengaja dibatasi ke elemen pembatalan saja — tidak menyapu seluruh .bi di halaman
   ini agar ikon lama (search box, header panel) tidak ikut berubah. */
.row-cancel-btn .bi,
.cancel-trace-badge .bi,
.cancel-trace-banner .bi,
.domain-pill .bi,
.reason-cell .bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.row-cancel-btn .bi::before,
.cancel-trace-badge .bi::before,
.cancel-trace-banner .bi::before,
.domain-pill .bi::before,
.reason-cell .bi::before {
    display: block;
    line-height: 1;
}

.status-pill.is-cancelled {
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-500);
}

/* Tombol batal per-baris (single) */
.row-cancel-btn {
    display: inline-grid;
    place-items: center;
    width: 28px;
    height: 28px;
    padding: 0;
    line-height: 1;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-400);
    font-size: 0.78rem;
    cursor: pointer;
    transition: 0.15s;
}
.row-cancel-btn:hover {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-600);
}

/* Jejak "pernah dibatalkan" pada baris yang kini Belum Submit */
.cancel-trace-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    margin-top: 0.15rem;
    padding: 0.08rem 0.35rem;
    border: 1px solid var(--hcis-red-200);
    border-radius: 6px;
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
    font-size: 0.6rem;
    font-weight: 700;
    white-space: nowrap;
}

/* Banner jejak pembatalan di modal detail */
.cancel-trace-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    margin-bottom: 0.9rem;
    padding: 0.75rem 0.85rem;
    border: 1px solid var(--hcis-red-200);
    border-radius: 12px;
    background: var(--hcis-red-50);
    color: var(--hcis-red-800);
}
/* Tinggi disamakan dgn tinggi baris judul di sebelahnya supaya ikon sejajar optis
   dengan judul, bukan menempel ke tepi atas kotak. */
.cancel-trace-banner > i {
    height: 1.2rem;
    font-size: 1.1rem;
    flex: none;
}
.cancel-trace-banner .ctb-body {
    min-width: 0;
}
.cancel-trace-banner .ctb-title {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 800;
}
.cancel-trace-banner .ctb-reason {
    margin: 0.15rem 0;
    font-size: 0.8rem;
    color: var(--hcis-red-900);
    white-space: pre-wrap;
}
.cancel-trace-banner .ctb-meta {
    margin: 0;
    font-size: 0.7rem;
    opacity: 0.9;
}
.cancel-trace-banner .ctb-tag {
    padding: 0.05rem 0.3rem;
    border-radius: 5px;
    background: var(--hcis-red-100);
    font-weight: 700;
}
.cancel-trace-banner .ctb-batch {
    margin: 0.25rem 0 0;
    font-size: 0.65rem;
    opacity: 0.65;
}

/* Riwayat pembatalan */
.domain-pill {
    display: inline-block;
    padding: 0.16rem 0.45rem;
    border-radius: 999px;
    border: 1px solid;
    font-size: 0.65rem;
    font-weight: 700;
    white-space: nowrap;
}
.domain-pill.d-parameter {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
}
.domain-pill.d-performance {
    background: #fff7ed;
    border-color: #fed7aa;
    color: #9a3412;
}
.domain-pill.d-draft {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-600);
}
.reason-cell {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: var(--hcis-slate-600);
}

@media (max-width: 767.98px) {
    /* Toolbar pembatalan jadi bar tetap di bawah -> beri ruang agar tidak menutupi
       baris terakhir tabel. */
    .kpi-enf-panel {
        padding-bottom: 4.5rem;
    }
}

/* ==================== KOREKSI TARGET MODAL STYLES ==================== */
.koreksi-modal-content {
    display: flex;
    flex-direction: column;
}

.koreksi-context-card {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(243, 244, 246, 0.7) 100%);
    border: 1px solid rgba(79, 70, 229, 0.15);
    border-radius: 0.75rem;
    padding: 0.875rem 1rem;
}

.koreksi-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.5rem;
    background: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.koreksi-audit-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-slate-600);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.koreksi-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.koreksi-filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    background: var(--hcis-slate-100);
    padding: 0.25rem;
    border-radius: 0.5rem;
}

.koreksi-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.35rem 0.65rem;
    border-radius: 0.375rem;
    border: none;
    background: transparent;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--hcis-slate-500);
    transition: all 0.15s ease;
    cursor: pointer;
}

.koreksi-tab-btn:hover {
    color: var(--hcis-slate-800);
    background: rgba(255, 255, 255, 0.6);
}

.koreksi-tab-btn.active {
    background: var(--hcis-white);
    color: var(--hcis-slate-900);
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.koreksi-tab-btn.active.tab-boleh {
    color: var(--hcis-emerald-600);
}

.koreksi-tab-btn.active.tab-diubah {
    color: var(--hcis-indigo-600);
}

.koreksi-tab-btn .tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.25rem;
    height: 1.25rem;
    padding: 0 0.3rem;
    border-radius: 9999px;
    background: rgba(100, 116, 139, 0.12);
    font-size: 0.7rem;
}

.koreksi-tab-btn.active .tab-count {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
}

.koreksi-tab-btn.active.tab-boleh .tab-count {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-800);
}

.koreksi-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 180px;
}

.koreksi-search-input-wrap .search-icon {
    position: absolute;
    left: 0.625rem;
    color: var(--hcis-slate-400);
    pointer-events: none;
    font-size: 0.8rem;
}

.koreksi-search-input-wrap .search-clear-btn {
    position: absolute;
    right: 0.5rem;
    background: none;
    border: none;
    padding: 0;
    color: var(--hcis-slate-400);
    cursor: pointer;
    font-size: 0.85rem;
}

.koreksi-search-input-wrap .search-clear-btn:hover {
    color: var(--hcis-slate-500);
}

.koreksi-search-input {
    padding-left: 2rem !important;
    padding-right: 1.75rem !important;
    border-radius: 0.375rem !important;
    border: 1px solid var(--hcis-slate-300) !important;
    background-color: var(--hcis-white) !important;
    font-size: 0.8125rem !important;
}

.koreksi-search-input:focus {
    border-color: var(--hcis-indigo-500) !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15) !important;
}

/* Collapsible Parameter Card Layout */
.koreksi-parameters-stack {
    display: flex;
    flex-direction: column;
}

.koreksi-param-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.625rem;
    background: var(--hcis-white);
    overflow: hidden;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.koreksi-param-card:hover {
    border-color: var(--hcis-slate-300);
}

.koreksi-param-card.has-changes {
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 0 0 1px var(--hcis-indigo-200);
}

.koreksi-param-header {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--hcis-slate-200);
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s ease;
}

.koreksi-param-card.is-collapsed .koreksi-param-header {
    border-bottom: none;
}

.koreksi-param-header:hover {
    background: var(--hcis-slate-100);
}

.param-index-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.625rem;
    height: 1.625rem;
    border-radius: 9999px;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.koreksi-param-card.has-changes .param-index-badge {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
}

.param-title {
    font-size: 0.9rem;
    color: var(--hcis-slate-800);
}

.btn-chevron {
    background: none;
    border: none;
    padding: 0.25rem;
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 0.25rem;
    transition: color 0.15s ease;
}

.btn-chevron:hover {
    color: var(--hcis-slate-800);
    background: rgba(0, 0, 0, 0.05);
}

.chevron-icon {
    transition: transform 0.2s ease;
    font-size: 0.85rem;
}

.chevron-icon.rotate-180 {
    transform: rotate(-180deg);
}

.koreksi-param-body {
    background: var(--hcis-white);
}

.koreksi-table-wrapper {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.625rem;
    overflow: hidden;
    background: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.koreksi-modern-table thead th {
    background-color: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.625rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}

.koreksi-modern-table tbody td {
    padding: 0.625rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    font-size: 0.85rem;
}

.koreksi-modern-table tbody tr:last-child td {
    border-bottom: none;
}

.koreksi-modern-table tbody tr.row-modified {
    background-color: rgba(99, 102, 241, 0.04);
}

.koreksi-modern-table tbody tr.row-locked {
    background-color: var(--hcis-zinc-50);
    opacity: 0.85;
}

.koreksi-input-group {
    position: relative;
    display: inline-flex;
    align-items: center;
    max-width: 140px;
}

.koreksi-input-field {
    width: 100%;
    padding: 0.3rem 0.5rem;
    border: 1px solid var(--hcis-slate-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: all 0.15s ease;
}

.koreksi-input-field:focus {
    border-color: var(--hcis-indigo-600);
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
    background-color: var(--hcis-white);
}

.koreksi-input-field.is-modified {
    border-color: var(--hcis-indigo-600);
    background-color: var(--hcis-violet-50);
    font-weight: 700;
    color: var(--hcis-indigo-700);
}

.btn-reset-row {
    position: absolute;
    right: -1.75rem;
    background: none;
    border: none;
    color: var(--hcis-slate-400);
    padding: 0.2rem;
    cursor: pointer;
    font-size: 0.9rem;
    transition: color 0.15s;
}

.btn-reset-row:hover {
    color: var(--hcis-red-500);
}

.delta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.725rem;
    font-weight: 600;
    padding: 0.2rem 0.45rem;
    border-radius: 0.25rem;
    font-family: monospace;
}

.delta-up {
    background-color: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border: 1px solid var(--hcis-emerald-200);
}

.delta-down {
    background-color: #fff1f2;
    color: var(--hcis-rose-600);
    border: 1px solid #fecdd3;
}

.delta-same {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
}

.koreksi-chip-btn {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition: all 0.15s ease;
}

.koreksi-chip-btn:hover {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-300);
    color: var(--hcis-indigo-700);
}

.koreksi-reason-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.koreksi-reason-textarea {
    resize: vertical;
    border-radius: 0.375rem;
    border: 1px solid var(--hcis-slate-300);
    font-size: 0.85rem;
}

.koreksi-reason-textarea:focus {
    border-color: var(--hcis-indigo-600);
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
    background: var(--hcis-white);
}

.koreksi-alert-card {
    padding: 0.875rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid transparent;
}

.koreksi-alert-warning {
    background-color: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.koreksi-alert-danger {
    background-color: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
}

.alert-details-table {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
}

.alert-detail-row {
    padding: 0.2rem 0;
}
</style>
