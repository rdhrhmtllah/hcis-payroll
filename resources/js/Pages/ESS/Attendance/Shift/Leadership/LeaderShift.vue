<template>
    <div class="cuti-app-container">
        <!-- Header -->
        <HeaderKpi :title="pageTitle" :subtitle="pageSubtitle" :dayNight="dayNight" :formattedDate="formattedDate" />

        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs :tabs="tabs" :activeTab="activeTab" @tab-change="handleTabChange">
                    <!-- Tab: Kalender -->
                    <template #kalender>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <!-- 1. Approver & Policy Section (SelfShift Style) -->
                            <div
                                v-if="!isAdmin && !hideApproverPolicySection"
                                class="stitch-card p-2 mb-4 border-0 shadow-sm rounded-4 overflow-hidden bg-white"
                            >
                                <div class="row g-0">
                                    <!-- Left Section: Approver Profile -->
                                    <div
                                        class="col-12 col-md-5 px-3 pt-3 pt-md-0 d-flex flex-column justify-content-center position-relative"
                                    >
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- Icon -->
                                            <div class="avatar-approver position-relative flex-shrink-0">
                                                <div
                                                    class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                                    :style="`width: 48px; height: 48px; background: ${hasApprover ? '#eff6ff' : '#fff7ed'}; color: ${hasApprover ? '#3b82f6' : '#f59e0b'};`"
                                                >
                                                    <i
                                                        :class="
                                                            hasApprover
                                                                ? 'bi bi-person-check-fill'
                                                                : 'bi bi-exclamation-triangle-fill'
                                                        "
                                                        style="font-size: 1.25rem"
                                                    ></i>
                                                </div>
                                                <div
                                                    v-if="hasApprover"
                                                    class="position-absolute bg-white rounded-circle p-0.5"
                                                    style="bottom: -1px; right: -1px"
                                                >
                                                    <i
                                                        class="bi bi-check-circle-fill text-success"
                                                        style="font-size: 12px"
                                                    ></i>
                                                </div>
                                            </div>

                                            <!-- Info -->
                                            <div class="d-flex flex-column" style="min-width: 0">
                                                <div v-if="hasApprover">
                                                    <span
                                                        class="text-uppercase text-muted fw-bold mb-0.5 d-flex justify-content-start align-items-center gap-2"
                                                        style="font-size: 0.6rem; letter-spacing: 0.5px"
                                                    >
                                                        <i
                                                            class="d-flex d-md-none"
                                                            :class="
                                                                hasApprover
                                                                    ? 'bi bi-person-check-fill'
                                                                    : 'bi bi-exclamation-triangle-fill'
                                                            "
                                                            style="font-size: 1.25rem"
                                                        ></i>
                                                        Approver
                                                    </span>
                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                        <div
                                                            v-for="(approver, index) in approverFlows.slice(0, 2)"
                                                            :key="index"
                                                            class="approver-card d-flex flex-column align-items-start lh-1 p-2 rounded-3 bg-light border shadow-sm"
                                                            style="
                                                                min-width: 120px;
                                                                max-width: 240px;
                                                                /* flex: 1 1 0px; */
                                                            "
                                                        >
                                                            <h6
                                                                class="nama-approver text-dark fw-bold mb-0.5 text-truncate"
                                                                style="font-size: 0.85rem"
                                                            >
                                                                {{ truncateText(approver.Nama, 20) }}
                                                            </h6>
                                                            <span
                                                                class="jabatan-approver text-muted small text-truncate"
                                                                style="font-size: 0.75rem"
                                                            >
                                                                {{ truncateText(approver.Jabatan, 20) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-if="approverFlows.length > 2"
                                                        class="mt-1 text-xs text-muted"
                                                    >
                                                        +{{ approverFlows.length - 2 }}
                                                        lainnya
                                                    </div>
                                                </div>

                                                <!-- <div
                                                    v-if="hasApprover"
                                                    class="lh-1"
                                                >
                                                    <h6
                                                        class="text-dark fw-bold mb-0.5 text-truncate"
                                                        style="
                                                            font-size: 0.95rem;
                                                        "
                                                    >
                                                        {{ approverInfo.nama }}
                                                    </h6>
                                                    <span
                                                        class="text-muted small text-truncate"
                                                        style="
                                                            font-size: 0.75rem;
                                                        "
                                                    >
                                                        {{
                                                            approverInfo.jabatan
                                                        }}
                                                    </span>
                                                </div> -->
                                                <div v-else class="lh-1">
                                                    <h6
                                                        class="text-warning-emphasis fw-bold mb-1"
                                                        style="font-size: 0.9rem"
                                                    >
                                                        Approver belum ada
                                                    </h6>
                                                    <span class="text-muted" style="font-size: 0.72rem">
                                                        Pengajuan yang butuh approval tidak bisa diproses sebelum
                                                        approver ditetapkan.
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Section: Policy Legend -->
                                    <div class="col-12 col-md-7 p-3">
                                        <div class="d-flex gap-md-2 gap-1 flex-wrap h-100">
                                            <div v-if="approvalConfig.needsApproval.length > 0" class="grow-config">
                                                <div
                                                    class="p-2 rounded-3 bg-white border shadow-sm text-center h-100 d-flex flex-column justify-content-center transition-hover"
                                                >
                                                    <div class="mb-1">
                                                        <i
                                                            class="bi bi-clock-history text-primary bg-primary-subtle p-1.5 rounded-circle"
                                                            style="font-size: 0.85rem"
                                                        ></i>
                                                    </div>
                                                    <div class="d-flex flex-column gap-0.5">
                                                        <div
                                                            class="text-muted fw-bold"
                                                            style="font-size: 0.6rem; text-transform: uppercase"
                                                        >
                                                            {{ this.approvalConfig.needsApproval.join(', ') }}
                                                        </div>
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5"
                                                            :class="
                                                                hasApprover
                                                                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                                                    : 'bg-danger-subtle text-danger border-danger-subtle'
                                                            "
                                                            style="font-size: 0.6rem; padding: 0.25em 0.5em"
                                                        >
                                                            {{ hasApprover ? 'Butuh Approval' : 'Approver Belum Ada' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Future Legend -->
                                            <div v-if="approvalConfig.noNeedsApproval.length > 0" class="grow-config">
                                                <div
                                                    class="p-2 rounded-3 bg-white border shadow-sm text-center h-100 d-flex flex-column justify-content-center transition-hover"
                                                >
                                                    <div class="mb-1">
                                                        <i
                                                            class="bi bi-lightning-charge text-success bg-success-subtle p-1.5 rounded-circle"
                                                            style="font-size: 0.85rem"
                                                        ></i>
                                                    </div>
                                                    <div class="d-flex flex-column gap-0.5">
                                                        <div
                                                            class="text-muted fw-bold"
                                                            style="font-size: 0.6rem; text-transform: uppercase"
                                                        >
                                                            {{ this.approvalConfig.noNeedsApproval.join(', ') }}
                                                        </div>
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5 bg-success-subtle text-success-emphasis border-success-subtle"
                                                            style="font-size: 0.6rem; padding: 0.25em 0.5em"
                                                        >
                                                            Langsung Aktif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- <div
                                                v-if="
                                                    requesterSkipLevel !== null
                                                "
                                                class="w-100 mt-1"
                                            >
                                                <div
                                                    class="p-2 rounded-3 bg-light border text-start"
                                                >
                                                    <div
                                                        class="text-muted fw-bold mb-1"
                                                        style="
                                                            font-size: 0.6rem;
                                                            text-transform: uppercase;
                                                        "
                                                    >
                                                        Skip Rule
                                                    </div>
                                                    <span
                                                        class="badge rounded-pill border bg-info-subtle text-info-emphasis border-info-subtle fw-semibold"
                                                        style="
                                                            font-size: 0.65rem;
                                                            padding: 0.3em 0.55em;
                                                        "
                                                    >
                                                        skip_if_requester_level_id:
                                                        {{
                                                            requesterSkipLevel
                                                        }}
                                                    </span>
                                                </div>
                                            </div> -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Control Panel & Week Navigation (Stitch Style) -->
                            <div class="date-nav-wrapper stitch-card mb-4 sticky top-0 z-10">
                                <!-- Left: Date Navigation -->
                                <div class="date-nav-left">
                                    <div
                                        class="d-flex align-items-center border bg-white p-1 gap-2 rounded-2"
                                        style="border-radius: 10px"
                                    >
                                        <button
                                            @click="prevWeek"
                                            :disabled="inLoadingTime"
                                            class="stitch-action-btn"
                                            style="width: 32px; height: 32px"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </button>

                                        <!-- Date Picker Trigger -->
                                        <div class="position-relative" style="width: 32px; height: 32px">
                                            <button
                                                class="stitch-action-btn w-100 h-100 d-flex align-items-center justify-content-center"
                                            >
                                                <i class="bi bi-calendar-date"></i>
                                            </button>
                                            <el-date-picker
                                                v-model="selectedDate"
                                                type="date"
                                                :clearable="false"
                                                format="YYYY-MM-DD"
                                                :disabled="inLoadingTime"
                                                value-format="YYYY-MM-DD"
                                                @change="changeWeek"
                                                style="
                                                    position: absolute;
                                                    top: 0;
                                                    left: 0;
                                                    width: 100%;
                                                    height: 100%;
                                                    opacity: 0;
                                                    cursor: pointer;
                                                "
                                            />
                                        </div>

                                        <button
                                            @click="nextWeek"
                                            :disabled="inLoadingTime"
                                            class="stitch-action-btn"
                                            style="width: 32px; height: 32px"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <!-- <i class="bi bi-calendar-week text-muted"></i> -->
                                        <div class="d-flex flex-column lh-1">
                                            <span class="text-dark fw-bold date-nav-label">
                                                {{ weekRangeDisplay }}
                                            </span>
                                            <span class="text-muted text-xs ms-1">{{ monthYearDisplay }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Center: Search & Filter (Adapted) -->
                                <div
                                    class="d-flex align-items-center gap-2 flex-grow-1 justify-content-end"
                                    style="min-width: 0"
                                >
                                    <div
                                        class="position-relative"
                                        style="min-width: 200px; max-width: 300px; width: 100%"
                                    >
                                        <i
                                            class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"
                                        ></i>
                                        <input
                                            v-model="searchMember"
                                            @input="debouncedSearch"
                                            type="text"
                                            class="form-control ps-5 rounded-pill border shadow-none bg-light"
                                            placeholder="Cari anggota..."
                                            style="font-size: 0.9rem"
                                        />
                                    </div>
                                    <!-- <div style="min-width: 150px; width: 100%; max-width: 200px;">
                                         <el-select v-model="activeFilters.shift" placeholder="Semua Shift" class="w-100" size="large">
                                            <el-option label="Semua Shift" value="" />
                                            <el-option v-for="shift in shiftOptions" :key="shift.ID_Shift" :label="shift.Nama" :value="shift.ID_Shift" />
                                        </el-select>
                                    </div> -->
                                </div>

                                <!-- Right: Actions -->
                                <div class="date-nav-right">
                                    <!-- <button @click="goToToday" class="stitch-btn secondary today-btn d-none d-md-block">
                                        Hari Ini
                                    </button> -->
                                    <button @click="openBulkModal" class="stitch-btn primary add-shift-btn">
                                        <i class="bi bi-plus-lg"></i>
                                        <span class="add-shift-text">Bulk Assign</span>
                                    </button>
                                </div>
                            </div>

                            <!-- 3. Calendar Grid (Table Version) -->
                            <div class="stitch-card p-0 border-0 shadow-sm overflow-hidden bg-white">
                                <!-- Loading State -->
                                <div
                                    v-if="loading.schedule || loading.team"
                                    class="p-5 text-center loading-state border-0 shadow-none"
                                >
                                    <div class="loading-spinner">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <h4 class="loading-title">Memuat Jadwal...</h4>
                                    <p class="loading-description">Sedang mengambil data shift tim</p>
                                </div>

                                <div v-else>
                                    <!-- TABLE VIEW (Desktop Default) -->
                                    <div v-if="viewMode === 'table'" class="d-none d-md-block">
                                        <div class="stitch-table-wrapper">
                                            <table class="stitch-table">
                                                <thead>
                                                    <tr>
                                                        <th
                                                            class="sticky-col text-start"
                                                            style="width: 250px; min-width: 250px"
                                                        >
                                                            <div class="d-flex align-items-center gap-2">
                                                                <i class="bi bi-people text-primary"></i>
                                                                <span
                                                                    >Anggota Tim ({{ paginatedMembers.length }}/{{
                                                                        schedulePagination.total
                                                                    }})</span
                                                                >
                                                            </div>
                                                        </th>
                                                        <th
                                                            v-for="day in weekDays"
                                                            :key="day.date"
                                                            class="text-center day-col"
                                                            :class="{
                                                                'bg-primary-subtle': day.isToday,
                                                            }"
                                                        >
                                                            <div class="d-flex flex-column align-items-center">
                                                                <span
                                                                    class="text-xs fw-bold text-muted text-uppercase mb-1"
                                                                    >{{ day.name }}</span
                                                                >
                                                                <div
                                                                    class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-sm"
                                                                    :class="
                                                                        day.isToday
                                                                            ? 'bg-primary text-white shadow-sm'
                                                                            : 'text-dark'
                                                                    "
                                                                    style="width: 32px; height: 32px"
                                                                >
                                                                    {{ day.day }}
                                                                </div>
                                                                <span
                                                                    class="text-xxs text-muted mt-1"
                                                                    style="font-size: 0.65rem"
                                                                    >{{ day.month }} {{ day.year }}</span
                                                                >
                                                            </div>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-if="paginatedMembers.length === 0">
                                                        <td
                                                            :colspan="weekDays.length + 1"
                                                            class="text-center p-5 text-muted"
                                                        >
                                                            <div class="mb-2 opacity-50">
                                                                <i class="bi bi-people fs-1"></i>
                                                            </div>
                                                            Tidak ada anggota ditemukan
                                                        </td>
                                                    </tr>
                                                    <tr
                                                        v-for="member in paginatedMembers"
                                                        :key="member.Kode_Karyawan"
                                                        class="member-row transition-colors"
                                                    >
                                                        <!-- Sticky Member Info -->
                                                        <td class="sticky-col bg-white">
                                                            <div
                                                                class="d-flex align-items-center justify-content-between gap-3"
                                                            >
                                                                <div
                                                                    class="avatar-circle bg-primary-subtle text-primary fw-bold ring-1 ring-primary-light flex-shrink-0"
                                                                    style="width: 42px; height: 42px"
                                                                >
                                                                    {{ getInitials(member.Nama) }}
                                                                </div>
                                                                <div class="flex-1" style="min-width: 0">
                                                                    <div
                                                                        class="fw-bold text-dark text-truncate text-sm mb-0.5"
                                                                    >
                                                                        {{ member.Nama }}
                                                                    </div>
                                                                    <!-- <div
                                                                        class="text-muted text-xs text-truncate"
                                                                    >
                                                                        {{
                                                                            member.NIK
                                                                        }}
                                                                    </div> -->
                                                                    <div
                                                                        class="text-xs text-muted text-truncate opacity-75"
                                                                    >
                                                                        {{
                                                                            truncateText(member.nama_jabatan, 15) || '-'
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="mt-1"
                                                                        v-if="
                                                                            shouldShowWeeklyHoursBadge(
                                                                                member.Kode_Karyawan,
                                                                            )
                                                                        "
                                                                    >
                                                                        <span
                                                                            class="badge rounded-pill"
                                                                            :class="
                                                                                getWeeklyHoursBadgeClass(
                                                                                    member.Kode_Karyawan,
                                                                                )
                                                                            "
                                                                        >
                                                                            {{
                                                                                formatWeeklyHoursWithOvertime(
                                                                                    member.Kode_Karyawan,
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    v-if="
                                                                        !loading.schedule &&
                                                                        !memberHasPermanentShift(member)
                                                                    "
                                                                    class="no-permanent-badge"
                                                                    title="Karyawan ini belum memiliki shift permanen"
                                                                >
                                                                    <i class="bi bi-exclamation-circle"></i>
                                                                    <span>Belum Permanen</span>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <!-- Days Cells -->
                                                        <td
                                                            v-for="day in weekDays"
                                                            :key="day.date"
                                                            class="p-1 cursor-pointer position-relative shift-cell-hover day-cell"
                                                            :class="{
                                                                'bg-primary-subtle-light': day.isToday,
                                                            }"
                                                            @click="openQuickModal(member, day)"
                                                        >
                                                            <div
                                                                class="h-100 w-100 d-flex flex-column align-items-center justify-content-center rounded-3 p-2 transition-all shift-content-wrapper"
                                                                style="min-height: 80px"
                                                            >
                                                                <template
                                                                    v-if="
                                                                        getShift(member.Kode_Karyawan, day.date)
                                                                            ?.status_hari
                                                                    "
                                                                >
                                                                    <span
                                                                        v-if="
                                                                            getStatusHariMainLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                            ).startsWith('OFF') &&
                                                                            !getShift(member.Kode_Karyawan, day.date)
                                                                                ?.is_pending_shift
                                                                        "
                                                                        class="mb-1 status-pill"
                                                                        :class="
                                                                            getStatusHariBadgeClass(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                            )
                                                                        "
                                                                        :title="
                                                                            getStatusHariLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari_label,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            getStatusHariMainLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                            )
                                                                        }}
                                                                    </span>
                                                                    <div
                                                                        v-if="
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                ?.source &&
                                                                            !getShift(member.Kode_Karyawan, day.date)
                                                                                ?.is_day_off
                                                                        "
                                                                        class="shift-source mb-1"
                                                                        :class="
                                                                            getShiftSourceClass(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .source,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            getShiftSourceLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .source,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        v-if="
                                                                            getStatusHariSubLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari_label,
                                                                            )
                                                                        "
                                                                        class="status-subtext"
                                                                        :title="
                                                                            getStatusHariSubLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari_label,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            getStatusHariSubLabel(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari,
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .status_hari_label,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </template>
                                                                <template
                                                                    v-if="
                                                                        getShift(member.Kode_Karyawan, day.date)
                                                                            ?.shift &&
                                                                        (!getShift(member.Kode_Karyawan, day.date)
                                                                            ?.is_day_off ||
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                ?.is_pending_shift)
                                                                    "
                                                                >
                                                                    <div
                                                                        class="shift-badge w-100 text-truncate text-center mb-1 py-1.5 shadow-sm position-relative"
                                                                        :class="
                                                                            getShiftClass(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .shift.nama,
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .shift.nama
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="shift-time-pill d-flex align-items-center gap-1 text-muted fw-medium bg-white border"
                                                                    >
                                                                        <i class="bi bi-clock"></i>
                                                                        {{
                                                                            formatTime(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .shift.jam_masuk,
                                                                            )
                                                                        }}
                                                                        -
                                                                        {{
                                                                            formatTime(
                                                                                getShift(member.Kode_Karyawan, day.date)
                                                                                    .shift.jam_keluar,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </template>

                                                                <!-- Empty State with Add Icon on Hover -->
                                                                <template v-else>
                                                                    <div
                                                                        class="empty-placeholder text-muted opacity-25"
                                                                        v-if="
                                                                            !getShift(member.Kode_Karyawan, day.date)
                                                                                ?.is_day_off
                                                                        "
                                                                    >
                                                                        <i class="bi bi-dash-lg"></i>
                                                                    </div>
                                                                    <div
                                                                        class="add-overlay position-absolute top-50 start-50 translate-middle"
                                                                        v-if="
                                                                            !getShift(member.Kode_Karyawan, day.date)
                                                                                ?.is_day_off
                                                                        "
                                                                    >
                                                                        <div
                                                                            class="btn btn-sm btn-primary rounded-circle shadow-sm"
                                                                            style="
                                                                                width: 32px;
                                                                                height: 32px;
                                                                                display: flex;
                                                                                align-items: center;
                                                                                justify-content: center;
                                                                            "
                                                                        >
                                                                            <i class="bi bi-plus-lg"></i>
                                                                        </div>
                                                                    </div>
                                                                </template>

                                                                <!-- Pending Approval Badge -->
                                                                <div
                                                                    v-if="
                                                                        getShift(member.Kode_Karyawan, day.date)
                                                                            ?.is_pending_shift ||
                                                                        getShift(member.Kode_Karyawan, day.date)
                                                                            ?.pending_status_hari
                                                                    "
                                                                    class="pending-approval-badge"
                                                                    :title="
                                                                        getShift(member.Kode_Karyawan, day.date)
                                                                            ?.is_pending_shift
                                                                            ? 'Pergantian shift menunggu approval'
                                                                            : 'Perubahan status hari menunggu approval'
                                                                    "
                                                                >
                                                                    <span class="pending-dot"></span>
                                                                    <span class="pending-text">Pending</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Pagination Controls -->
                                    <div
                                        v-if="totalSchedulePages > 1"
                                        class="d-flex align-items-center justify-content-between p-3 border-top bg-white"
                                    >
                                        <span class="text-muted text-sm">
                                            Halaman
                                            {{ schedulePagination.page }} dari {{ totalSchedulePages }} ({{
                                                schedulePagination.total
                                            }}
                                            anggota)
                                        </span>
                                        <div class="d-flex gap-2">
                                            <button
                                                class="stitch-action-btn"
                                                :disabled="schedulePagination.page <= 1 || loading.schedule"
                                                @click="prevSchedulePage"
                                            >
                                                <i class="bi bi-chevron-left"></i>
                                                <!-- Sebelumnya -->
                                            </button>
                                            <button
                                                class="stitch-action-btn stitch-primary"
                                                :disabled="
                                                    schedulePagination.page >= totalSchedulePages || loading.schedule
                                                "
                                                @click="nextSchedulePage"
                                            >
                                                <!-- Selanjutnya -->
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- CARD VIEW (Mobile Default) -->
                                    <div
                                        v-show="viewMode === 'card' || (isMobile && viewMode !== 'table')"
                                        class="d-md-none bg-surface-soft p-3"
                                    >
                                        <div
                                            v-for="member in paginatedMembers"
                                            :key="member.Kode_Karyawan"
                                            class="stitch-card mobile-member-card mb-3 p-0 overflow-hidden border shadow-sm"
                                            :class="{
                                                'is-expanded': expandedMembers.includes(member.Kode_Karyawan),
                                            }"
                                        >
                                            <!-- Card Header -->
                                            <div
                                                class="d-flex align-items-center gap-3 p-3 bg-white border-bottom mobile-member-header"
                                                :class="{
                                                    'is-expanded': expandedMembers.includes(member.Kode_Karyawan),
                                                }"
                                            >
                                                <div
                                                    class="avatar-circle bg-primary text-white fw-bold shadow-sm mobile-member-avatar"
                                                    style="width: 42px; height: 42px; font-size: 1rem"
                                                >
                                                    {{ getInitials(member.Nama) }}
                                                </div>
                                                <div class="mobile-member-info">
                                                    <h6 class="fw-bold mb-0 text-dark">
                                                        {{ member.Nama }}
                                                    </h6>
                                                    <div class="text-muted text-xs">
                                                        <!-- {{ member.NIK }} • -->
                                                        {{ member.nama_jabatan || 'Anggota' }}
                                                    </div>
                                                    <div
                                                        class="mobile-member-divider"
                                                        v-if="shouldShowWeeklyHoursBadge(member.Kode_Karyawan)"
                                                    ></div>
                                                    <div
                                                        class="mobile-member-badge"
                                                        v-if="shouldShowWeeklyHoursBadge(member.Kode_Karyawan)"
                                                        :class="getWeeklyHoursBadgeClass(member.Kode_Karyawan)"
                                                    >
                                                        {{ formatWeeklyHoursWithOvertime(member.Kode_Karyawan) }}
                                                    </div>
                                                </div>
                                                <button
                                                    class="btn btn-sm btn-light ms-auto rounded-circle mobile-toggle-btn"
                                                    @click="toggleMemberCard(member.Kode_Karyawan)"
                                                >
                                                    <i
                                                        class="bi"
                                                        :class="
                                                            expandedMembers.includes(member.Kode_Karyawan)
                                                                ? 'bi-chevron-up'
                                                                : 'bi-chevron-down'
                                                        "
                                                    ></i>
                                                </button>
                                            </div>

                                            <!-- Card Body: Horizontal Scroll Days -->
                                            <div
                                                v-show="expandedMembers.includes(member.Kode_Karyawan)"
                                                class="p-3 bg-surface-soft"
                                            >
                                                <div class="mobile-week-grid">
                                                    <div
                                                        v-for="day in weekDays"
                                                        :key="day.date"
                                                        class="mobile-day-card bg-white rounded-3 border p-2 text-center clickable"
                                                        :class="{
                                                            'border-primary shadow-sm': day.isToday,
                                                        }"
                                                        @click="openQuickModal(member, day)"
                                                    >
                                                        <div class="text-xs text-uppercase fw-bold text-muted mb-1">
                                                            {{ day.name }}
                                                        </div>
                                                        <div
                                                            class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
                                                            :class="
                                                                day.isToday
                                                                    ? 'bg-primary text-white'
                                                                    : 'text-dark fw-bold'
                                                            "
                                                            style="width: 24px; height: 24px; font-size: 0.8rem"
                                                        >
                                                            {{ day.day }}
                                                        </div>

                                                        <!-- Shift Badge -->
                                                        <div
                                                            v-if="getShift(member.Kode_Karyawan, day.date)?.shift"
                                                            class="w-100"
                                                        >
                                                            <div
                                                                v-if="
                                                                    getShift(member.Kode_Karyawan, day.date)
                                                                        ?.status_hari
                                                                "
                                                                class="text-center mb-1"
                                                            >
                                                                <span
                                                                    class="status-pill"
                                                                    :class="
                                                                        getStatusHariBadgeClass(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                        )
                                                                    "
                                                                    :title="
                                                                        getStatusHariLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getStatusHariMainLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                        )
                                                                    }}
                                                                </span>
                                                                <div
                                                                    v-if="
                                                                        getStatusHariSubLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    "
                                                                    class="status-subtext"
                                                                    :title="
                                                                        getStatusHariSubLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getStatusHariSubLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    }}
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="shift-badge w-100 text-truncate mb-1 py-1 position-relative"
                                                                :class="
                                                                    getShiftClass(
                                                                        getShift(member.Kode_Karyawan, day.date).shift
                                                                            .nama,
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    getShift(member.Kode_Karyawan, day.date).shift.nama
                                                                }}
                                                            </div>
                                                            <div class="text-xxs text-muted fw-medium">
                                                                {{
                                                                    formatTime(
                                                                        getShift(member.Kode_Karyawan, day.date).shift
                                                                            .jam_masuk,
                                                                    )
                                                                }}
                                                            </div>
                                                            <div
                                                                v-if="getShift(member.Kode_Karyawan, day.date)?.source"
                                                                class="shift-source mt-1"
                                                                :class="
                                                                    getShiftSourceClass(
                                                                        getShift(member.Kode_Karyawan, day.date).source,
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    getShiftSourceLabel(
                                                                        getShift(member.Kode_Karyawan, day.date).source,
                                                                    )
                                                                }}
                                                            </div>
                                                        </div>
                                                        <div v-else class="text-center py-2 text-muted opacity-25">
                                                            <div
                                                                v-if="
                                                                    getShift(member.Kode_Karyawan, day.date)
                                                                        ?.status_hari
                                                                "
                                                                class="mb-1"
                                                            >
                                                                <span
                                                                    class="status-pill"
                                                                    :class="
                                                                        getStatusHariBadgeClass(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                        )
                                                                    "
                                                                    :title="
                                                                        getStatusHariLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getStatusHariMainLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                        )
                                                                    }}
                                                                </span>
                                                                <div
                                                                    v-if="
                                                                        getStatusHariSubLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    "
                                                                    class="status-subtext"
                                                                    :title="
                                                                        getStatusHariSubLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getStatusHariSubLabel(
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari,
                                                                            getShift(member.Kode_Karyawan, day.date)
                                                                                .status_hari_label,
                                                                        )
                                                                    }}
                                                                </div>
                                                            </div>
                                                            <i
                                                                v-if="
                                                                    !getShift(member.Kode_Karyawan, day.date)
                                                                        ?.is_day_off
                                                                "
                                                                class="bi bi-plus-circle"
                                                            ></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab: Riwayat -->
                    <template #riwayat>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <div class="d-flex justify-content-between align-items-end mb-4">
                                <div>
                                    <h2 class="h5 fw-bold text-dark mb-1">Riwayat Pengajuan</h2>
                                    <p class="text-muted text-sm mb-0">Pantau status pengajuan perubahan shift Anda</p>
                                </div>
                                <button @click="loadHistory" class="btn btn-surface shadow-sm btn-sm rounded-2 p-2">
                                    <i
                                        class="bi bi-arrow-clockwise me-1 d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>

                            <!-- Filter -->
                            <div class="mb-4">
                                <ModernFilter
                                    :config="historyFilterConfig"
                                    :filters="historyFilters"
                                    @filters-update="handleHistoryFilters"
                                />
                            </div>

                            <!-- History List -->
                            <div v-if="loading.history" class="text-center p-5 loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4 class="loading-title">Memuat Riwayat...</h4>
                            </div>

                            <div
                                v-else-if="historyItems.length === 0"
                                style="min-height: 200px"
                                class="text-center p-5 text-muted bg-white rounded-3 shadow-sm border d-flex justify-content-center align-items-center flex-column"
                            >
                                <i
                                    class="bi bi-inbox fs-1 mb-2 d-block d-flex justify-content-center align-items-center"
                                ></i>
                                <span> Belum ada riwayat pengajuan. </span>
                            </div>

                            <div v-else>
                                <!-- DESKTOP TABLE VIEW (SelfShift Style) -->
                                <div class="d-none d-md-block stitch-table-wrapper mb-4">
                                    <table class="stitch-table">
                                        <thead>
                                            <tr>
                                                <th>No Transaksi</th>
                                                <th class="text-center">Durasi</th>
                                                <!-- <th>Keterangan</th> -->
                                                <th>Tipe</th>
                                                <th>Dibuat</th>
                                                <th>Status</th>
                                                <th class="">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in historyItems" :key="item.No_Transaksi">
                                                <td class="font-medium text-dark">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i
                                                            :class="getHistoryIcon(item.transaction_type)"
                                                            style="font-size: 1.2rem"
                                                        ></i>
                                                        <span class="font-mono">{{ item.No_Transaksi }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-muted text-center">
                                                    <span>{{ getHistoryDurationText(item) }}</span>
                                                </td>
                                                <!-- <td>
                                                    <span
                                                        class="text-dark font-medium text-sm"
                                                        >{{
                                                            item.Keterangan ||
                                                            "-"
                                                        }}</span
                                                    >
                                                </td> -->
                                                <td class="text-muted">
                                                    <span
                                                        class="badge rounded-pill"
                                                        :class="getTransactionTypeClass(item.transaction_type)"
                                                    >
                                                        {{ getTransactionTypeLabel(item.transaction_type) }}
                                                    </span>
                                                </td>
                                                <td class="text-muted small">
                                                    {{ formatDateFull(item.Created_At) }}
                                                </td>
                                                <td>
                                                    <span class="stitch-badge" :class="getHistoryStatusClass(item)">
                                                        {{ getStatusLabel(item) }}
                                                    </span>
                                                    <div v-if="item.catatan_batal_admin" class="text-muted small mt-1">
                                                        {{ item.catatan_batal_admin }}
                                                    </div>
                                                </td>
                                                <td class="text-end d-flex gap-2">
                                                    <button
                                                        @click="openHistoryDetail(item)"
                                                        class="stitch-action-btn primary"
                                                        title="Lihat Detail"
                                                    >
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button
                                                        v-if="item.can_cancel"
                                                        @click="
                                                            openCancelModal({
                                                                scope: 'header',
                                                                no_transaksi: item.No_Transaksi,
                                                            })
                                                        "
                                                        class="stitch-action-btn danger"
                                                        title="Batalkan"
                                                    >
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MOBILE CARD VIEW (SelfShift Style) -->
                                <div class="d-md-none d-flex flex-column gap-3">
                                    <div
                                        v-for="item in historyItems"
                                        :key="'mobile-' + item.No_Transaksi"
                                        class="bg-white rounded-3 border shadow-sm p-3 position-relative"
                                        :style="
                                            item.transaction_type === 'TUKAR_HARI'
                                                ? 'border-left: 4px solid #ffc107 !important;'
                                                : 'border-left: 4px solid #0d6efd !important;'
                                        "
                                    >
                                        <!-- Header: Icon, No Transaksi, Status -->
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i
                                                    :class="getHistoryIcon(item.transaction_type)"
                                                    style="font-size: 1.5rem"
                                                ></i>
                                                <div>
                                                    <div class="text-xs text-muted mb-1">No. Transaksi</div>
                                                    <div class="font-medium text-dark text-sm font-mono">
                                                        {{ item.No_Transaksi }}
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="stitch-badge" :class="getHistoryStatusClass(item)">
                                                {{ getStatusLabel(item) }}
                                            </span>
                                        </div>
                                        <div v-if="item.catatan_batal_admin" class="text-muted small mb-2">
                                            {{ item.catatan_batal_admin }}
                                        </div>

                                        <div class="my-3 border-top border-light"></div>

                                        <!-- Details Grid -->
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <div class="text-muted small mb-1" style="font-size: 0.75rem">
                                                    {{
                                                        item.transaction_type === 'TUKAR_HARI'
                                                            ? 'Jenis Transaksi'
                                                            : 'Jumlah Hari'
                                                    }}
                                                </div>
                                                <div class="small text-dark fw-medium">
                                                    <span
                                                        v-if="item.transaction_type === 'TUKAR_HARI'"
                                                        class="badge bg-warning text-dark"
                                                        >Tukar Hari</span
                                                    >
                                                    <span v-else>{{ getHistoryDurationText(item) }}</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small mb-1" style="font-size: 0.75rem">Tipe</div>
                                                <div class="small text-dark fw-medium">
                                                    {{ getTransactionTypeLabel(item.transaction_type) }}
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="text-muted small mb-1" style="font-size: 0.75rem">
                                                    Keterangan
                                                </div>
                                                <div class="small text-dark fw-medium">
                                                    {{ item.Keterangan || '-' }}
                                                </div>
                                            </div>
                                            <div class="col-12 mt-2">
                                                <div class="text-muted small mb-1" style="font-size: 0.75rem">
                                                    Dibuat
                                                </div>
                                                <div class="small text-dark fw-medium">
                                                    {{ formatDateFull(item.Created_At) }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="d-flex justify-content-end gap-2">
                                            <button
                                                @click="openHistoryDetail(item)"
                                                class="stitch-action-btn"
                                                title="Lihat Detail"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button
                                                v-if="item.can_cancel"
                                                @click="
                                                    openCancelModal({
                                                        scope: 'header',
                                                        no_transaksi: item.No_Transaksi,
                                                    })
                                                "
                                                class="stitch-action-btn danger"
                                                title="Batalkan"
                                            >
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- PAGINATION (SelfShift Style) -->
                                <div
                                    v-if="historyPagination.total_pages > 1"
                                    class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light"
                                >
                                    <button
                                        class="stitch-btn secondary small"
                                        :disabled="historyPagination.page <= 1"
                                        @click="goHistoryPage(historyPagination.page - 1)"
                                    >
                                        <i class="bi bi-chevron-left me-1"></i>
                                        <span class="d-none d-md-block">Previous</span>
                                    </button>
                                    <span class="small text-muted fw-medium">
                                        Halaman
                                        <span class="text-dark">{{ historyPagination.page }}</span>
                                        dari
                                        <span class="text-dark">{{ historyPagination.total_pages }}</span>
                                    </span>
                                    <button
                                        class="stitch-btn secondary small"
                                        :disabled="historyPagination.page >= historyPagination.total_pages"
                                        @click="goHistoryPage(historyPagination.page + 1)"
                                    >
                                        <span class="d-none d-md-block">Next</span>
                                        <i class="bi bi-chevron-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- ================= CUSTOM MODALS ================= -->

        <!-- History Detail Modal -->
        <Modal :show="historyDetailModal.visible" title="Detail Transaksi" @close="closeHistoryDetail" size="xlarge">
            <template #body>
                <!-- Loading -->
                <div v-if="historyDetailModal.loading" class="text-center p-5">
                    <div class="loading-spinner">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <p class="text-muted mt-2">Memuat detail...</p>
                </div>

                <!-- Content -->
                <div v-else-if="historyDetailModal.data" class="">
                    <div class="detail-modal-grid">
                        <!-- LEFT COLUMN: Status & Approval -->
                        <div class="detail-left-column">
                            <!-- New Header Card -->
                            <div
                                class="detail-header-card bg-primary text-white rounded-4 p-4 mb-4 text-center position-relative overflow-hidden shadow-sm"
                            >
                                <div class="position-relative z-1">
                                    <h3 class="fw-bold mb-2 text-white" style="font-size: 1.25rem">
                                        {{ historyDetailModal.data.header?.No_Transaksi }}
                                    </h3>
                                    <span class="type-badge badge rounded-pill">
                                        {{ getDetailTransactionTypeLabel(historyDetailModal.data) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Status Section (Moved) -->
                            <div class="detail-status-section text-center mb-4">
                                <div class="detail-status-label text-muted small fw-bold mb-2 letter-spacing-1">
                                    STATUS REVIEW
                                </div>
                                <div
                                    class="detail-status-badge d-inline-block px-4 py-2 rounded-pill fw-bold shadow-sm"
                                    :class="getHistoryStatusClass(historyDetailModal.data.header)"
                                >
                                    {{
                                        historyDetailModal.data.header.Flag_Selesai === 'Y'
                                            ? 'SELESAI'
                                            : 'MENUNGGU REVIEW'
                                    }}
                                </div>
                                <div class="detail-status-date mt-3 text-muted small">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Diajukan:
                                    {{ formatDate(historyDetailModal.data.header.Created_At) }}
                                </div>
                            </div>

                            <!-- Approval Section -->
                            <div class="detail-approval-section">
                                <div class="detail-section-title mb-3">
                                    <i class="bi bi-diagram-3 text-primary"></i>
                                    Alur Persetujuan
                                </div>
                                <div class="detail-approval-list">
                                    <div
                                        v-for="(step, idx) in historyDetailModal.data.approval_steps"
                                        :key="idx"
                                        class="detail-approval-item"
                                    >
                                        <div
                                            class="detail-approval-indicator"
                                            :class="{
                                                approved: step.Flag_Approval === 'Y',
                                                rejected: step.Flag_Approval === 'T',
                                                pending: step.Flag_Approval === null,
                                            }"
                                        >
                                            <i v-if="step.Flag_Approval === 'Y'" class="bi bi-check"></i>
                                            <i v-else-if="step.Flag_Approval === 'T'" class="bi bi-x"></i>
                                            <i v-else class="bi bi-hourglass-split"></i>
                                        </div>
                                        <div class="detail-approval-info">
                                            <div
                                                class="detail-approval-name"
                                                :class="{
                                                    'text-success': step.Flag_Approval === 'Y',
                                                    'text-danger': step.Flag_Approval === 'T',
                                                }"
                                            >
                                                {{ step.Nama || step.Nama_Karyawan || 'Approver' }}
                                            </div>
                                            <div class="detail-approval-date" v-if="step.Flag_Approval === 'Y'">
                                                <span class="text-success fw-medium">Disetujui</span>
                                                <span v-if="step.Tanggal_Approval">
                                                    -
                                                    {{ formatDate(step.Tanggal_Approval) }}</span
                                                >
                                            </div>
                                            <div class="detail-approval-date" v-else-if="step.Flag_Approval === 'T'">
                                                <span class="text-danger fw-medium">Ditolak</span>
                                                <span v-if="step.Tanggal_Approval">
                                                    -
                                                    {{ formatDate(step.Tanggal_Approval) }}</span
                                                >
                                            </div>
                                            <div class="detail-approval-date" v-else>
                                                <span class="text-warning fw-medium">Menunggu review</span>
                                            </div>
                                            <div v-if="step.Catatan" class="text-muted small mt-1">
                                                <i class="bi bi-chat-left-text"></i>
                                                {{ step.Catatan }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            !historyDetailModal.data.approval_steps ||
                                            historyDetailModal.data.approval_steps.length === 0
                                        "
                                        class="detail-approval-empty text-center p-3 rounded bg-light border-dashed"
                                    >
                                        <i class="bi bi-check-circle-fill text-success fs-4 mb-2 d-block"></i>
                                        <span class="fw-bold text-success">Auto Approved</span>
                                        <div class="text-muted text-xs">Request doesn't need approval</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Details -->
                        <div class="detail-right-column">
                            <!-- CASE 1: TUKAR HARI (Single Detail) -->
                            <div
                                v-if="historyDetailModal.data.transaction_type === 'TUKAR_HARI'"
                                class="detail-info-section"
                            >
                                <div class="detail-section-title">
                                    <i class="bi bi-arrow-left-right"></i>
                                    Detail Tukar Hari
                                </div>

                                <div class="detail-shift-card mb-3 border-primary">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-briefcase-fill text-primary" style="font-size: 1.5rem"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.7rem"
                                                    >Tanggal Bekerja</small
                                                >
                                                <span class="detail-shift-date fw-bold text-primary">
                                                    {{
                                                        formatDate(
                                                            historyDetailModal.data.detail?.Tanggal_Bekerja ||
                                                                historyDetailModal.data.header.Tanggal_Mulai,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-clock text-primary"></i>
                                            <span class="text-dark fw-semibold">{{
                                                historyDetailModal.data.detail?.Nama_Shift || '-'
                                            }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 text-muted small">
                                            <i class="bi bi-clock"></i>
                                            {{ historyDetailModal.data.detail?.Jam_Masuk || '--:--' }}
                                            -
                                            {{ historyDetailModal.data.detail?.Jam_Keluar || '--:--' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center my-3">
                                    <i class="bi bi-arrow-down-up text-warning" style="font-size: 2rem"></i>
                                </div>

                                <div class="detail-shift-card mb-3 border-danger">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-house-fill text-danger" style="font-size: 1.5rem"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.7rem"
                                                    >Tanggal Off / Libur</small
                                                >
                                                <span class="detail-shift-date fw-bold text-danger">
                                                    {{
                                                        formatDate(
                                                            historyDetailModal.data.detail?.Tanggal_Off ||
                                                                historyDetailModal.data.header.Tanggal_Selesai,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-info-circle"></i> Hari libur ditukar menjadi hari kerja
                                    </div>
                                </div>

                                <div class="detail-info-row mt-4">
                                    <div class="detail-label">Alasan</div>
                                    <div class="detail-value">
                                        {{ historyDetailModal.data.header.Keterangan || '-' }}
                                    </div>
                                </div>
                            </div>

                            <!-- CASE 2: SHIFT / STATUS HARI (Member List) -->
                            <div v-else class="detail-info-section">
                                <div class="detail-section-title">
                                    <i class="bi bi-people"></i>
                                    {{ getDetailMembersSectionTitle() }}
                                    ({{ historyDetailModal.data.members?.length || 0 }})
                                </div>
                                <div
                                    v-for="(member, idx) in historyDetailModal.data.members"
                                    :key="idx"
                                    class="stitch-card mb-3 p-3"
                                >
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark">
                                                {{ member.Nama }}
                                            </div>
                                            <div class="text-muted text-xs d-flex align-items-center flex-wrap gap-1">
                                                <span>{{ member.Nama_Jabatan }}</span>
                                                <!-- Target Badge -->
                                                <span
                                                    v-if="member.Target_Table === 'PERMANEN'"
                                                    class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1"
                                                    style="font-size: 0.65rem"
                                                    >PERMANEN</span
                                                >
                                                <span
                                                    v-else-if="member.Target_Table === 'SEMENTARA'"
                                                    class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1"
                                                    style="font-size: 0.65rem"
                                                    >SEMENTARA</span
                                                >
                                            </div>
                                            <!-- Aggregate Status -->
                                            <div class="mt-2" v-if="member.dates && member.dates.length > 0">
                                                <span
                                                    class="badge rounded-pill border"
                                                    :class="getAggregateMemberStatusClass(member)"
                                                >
                                                    {{ getAggregateMemberStatusLabel(member) }}
                                                </span>
                                            </div>
                                            <div
                                                v-if="isMemberPermanent(member)"
                                                class="mt-2 text-xs text-muted d-flex flex-column gap-1"
                                            >
                                                <span>
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    {{
                                                        getMemberPermanentStartDate(member)
                                                            ? `Berlaku: mulai ${formatDate(getMemberPermanentStartDate(member))} dan seterusnya`
                                                            : 'Berlaku: mulai tanggal pengajuan dan seterusnya'
                                                    }}
                                                </span>
                                                <span>
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    {{
                                                        isHistoryDetailOverwriteSementara()
                                                            ? 'Bentrok shift sementara: ditimpa'
                                                            : 'Bentrok shift sementara: tidak ditimpa'
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button
                                                v-if="canCancelMember(member)"
                                                class="stitch-action-btn danger"
                                                @click="
                                                    openCancelModal({
                                                        scope: 'detail',
                                                        no_transaksi: historyDetailModal.data.header?.No_Transaksi,
                                                        detail_id: member.Id,
                                                    })
                                                "
                                                title="Batalkan"
                                            >
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                            <button class="stitch-btn icon-only small" @click="toggleDetailMember(idx)">
                                                <i
                                                    class="bi"
                                                    :class="
                                                        historyDetailModal.expandedMembers.includes(idx)
                                                            ? 'bi-chevron-up'
                                                            : 'bi-chevron-down'
                                                    "
                                                ></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Collapsible Dates -->
                                    <div
                                        v-if="historyDetailModal.expandedMembers.includes(idx)"
                                        class="mt-3 border-top pt-2"
                                    >
                                        <div class="text-xs text-muted fw-bold mb-2 text-uppercase">Detail Tanggal</div>
                                        <div
                                            v-if="member.dates && member.dates.length > 0"
                                            class="d-flex flex-column gap-2"
                                        >
                                            <div
                                                v-for="(date, dIdx) in member.dates"
                                                :key="dIdx"
                                                class="d-flex justify-content-between align-items-center p-2 rounded bg-light border-0"
                                            >
                                                <div class="d-flex align-items-center gap-2 w-100 px-2 py-1 rounded">
                                                    <!-- <div
                                                        class="stitch-date-box small"
                                                    >
                                                        <span class="day">{{
                                                            getDayName(
                                                                date.Tanggal ||
                                                                    date.Tanggal_Shift,
                                                            ).substring(0, 3)
                                                        }}</span>
                                                        <span class="num">{{
                                                            getDateNumber(
                                                                date.Tanggal ||
                                                                    date.Tanggal_Shift,
                                                            )
                                                        }}</span>
                                                    </div> -->
                                                    <div class="flex-grow-1">
                                                        <div class="text-xs text-primary mb-1 fw-bold">
                                                            {{ getMemberDateDisplayLabel(member, date) }}
                                                        </div>
                                                        <div
                                                            class="small fw-bold d-flex align-items-center gap-2 flex-wrap"
                                                        >
                                                            <!-- Status Hari -->
                                                            <span
                                                                v-if="
                                                                    historyDetailModal.data.transaction_type ===
                                                                    'STATUS_HARI'
                                                                "
                                                            >
                                                                {{ date.Status_Hari || '-' }}
                                                            </span>
                                                            <!-- Shift -->
                                                            <span v-else>
                                                                <span v-if="date.Nama_Shift_Baru">
                                                                    {{ date.Nama_Shift_Lama || 'Off' }}
                                                                    <i
                                                                        class="bi bi-arrow-right text-muted mx-1"
                                                                        style="font-size: 0.8em"
                                                                    ></i>
                                                                    {{ date.Nama_Shift_Baru }}
                                                                </span>
                                                                <span v-else>
                                                                    {{ date.Nama_Shift || '-' }}
                                                                </span>
                                                            </span>
                                                        </div>
                                                        <div class="text-xs text-muted d-flex gap-2 mt-1 flex-wrap">
                                                            <span
                                                                v-if="date.Jenis_Pengajuan === 'BACKDATE'"
                                                                class="badge bg-danger-subtle text-danger border border-danger-subtle px-1"
                                                                style="font-size: 0.65rem"
                                                                >Backdate</span
                                                            >
                                                            <span
                                                                v-else-if="date.Jenis_Pengajuan === 'FUTURE'"
                                                                class="badge bg-success-subtle text-success border border-success-subtle px-1"
                                                                style="font-size: 0.65rem"
                                                                >Future</span
                                                            >

                                                            <span
                                                                v-if="date.Needs_Approval === 'Y'"
                                                                class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-1"
                                                                style="font-size: 0.65rem"
                                                                >Req. Approval</span
                                                            >
                                                            <span
                                                                v-else
                                                                class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-1"
                                                                style="font-size: 0.65rem"
                                                                >Auto</span
                                                            >
                                                        </div>
                                                        <!-- <div
                                                            class="text-xs text-muted mt-1"
                                                        >
                                                            {{
                                                                date.Jam_Masuk ||
                                                                "--:--"
                                                            }}
                                                            -
                                                            {{
                                                                date.Jam_Keluar ||
                                                                "--:--"
                                                            }}
                                                        </div> -->
                                                    </div>
                                                    <!-- Flag-Approval(null Pending) -->
                                                    <div v-if="date.Flag_Approval !== undefined" class="ms-auto">
                                                        <span
                                                            v-if="date.Status === 'Y'"
                                                            class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"
                                                            style="font-size: 0.65rem"
                                                            >Canceled</span
                                                        >
                                                        <span
                                                            v-else-if="date.Flag_Approval === 'Y'"
                                                            class="badge bg-success-subtle text-success border border-success-subtle"
                                                            style="font-size: 0.65rem"
                                                            >Approved</span
                                                        >
                                                        <span
                                                            v-else-if="date.Flag_Approval === 'T'"
                                                            class="badge bg-danger-subtle text-danger border border-danger-subtle"
                                                            style="font-size: 0.65rem"
                                                            >Rejected</span
                                                        >
                                                        <span
                                                            v-else
                                                            class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"
                                                            style="font-size: 0.65rem"
                                                            >Pending</span
                                                        >
                                                    </div>
                                                    <div class="ms-2">
                                                        <button
                                                            v-if="canCancelDate(date)"
                                                            class="stitch-action-btn danger"
                                                            @click="
                                                                openCancelModal({
                                                                    scope: 'det',
                                                                    no_transaksi:
                                                                        historyDetailModal.data.header?.No_Transaksi,
                                                                    det_id: date.Id,
                                                                })
                                                            "
                                                            title="Batalkan Tanggal"
                                                        >
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center text-muted small py-2">
                                            Tidak ada detail tanggal
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-info-row mt-4 pt-3 border-top">
                                    <div class="detail-label">Keterangan</div>
                                    <div class="detail-value">
                                        {{ historyDetailModal.data.header.Alasan || '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center p-5 text-muted">
                    <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                    Data tidak ditemukan
                </div>
            </template>
        </Modal>

        <!-- 2. Bulk Assign Wizard Modal (Stitch Style) -->
        <Modal
            :show="bulkModal.visible"
            title="Bulk Assign Shift"
            :subtitle="'Atur jadwal untuk banyak anggota sekaligus'"
            @close="closeBulkModal"
            size="large"
        >
            <template #body>
                <div class="p-0">
                    <!-- Stepper -->
                    <div class="stitch-stepper px-5 py-4 bg-white border-bottom">
                        <div
                            v-for="step in 4"
                            :key="step"
                            class="stitch-step"
                            :class="{
                                active: bulkModal.step === step,
                                completed: bulkModal.step > step,
                            }"
                        >
                            <div class="stitch-step-circle">
                                <i v-if="bulkModal.step > step" class="bi bi-check-lg"></i>
                                <span v-else>{{ step }}</span>
                            </div>
                            <span class="stitch-step-label">{{
                                ['Aksi & Anggota', 'Mode & Tanggal', 'Atur Detail', 'Review'][step - 1]
                            }}</span>
                        </div>
                    </div>

                    <div class="p-4" style="min-height: 350px; background: #f8f9fa">
                        <!-- Step 1: Select Action & Members -->
                        <div v-if="bulkModal.step === 1" class="fade-in">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Jenis Aksi</label>
                                <div class="d-flex gap-3">
                                    <div
                                        class="stitch-card p-3 flex-1 cursor-pointer transition-all d-flex align-items-center gap-3 border"
                                        :class="
                                            bulkModal.actionType === 'SHIFT' ? 'border-primary bg-primary-subtle' : ''
                                        "
                                        @click="bulkModal.actionType = 'SHIFT'"
                                    >
                                        <div class="rounded-circle bg-white p-2 text-primary shadow-sm">
                                            <i class="bi bi-clock"></i>
                                        </div>
                                        <div class="lh-1">
                                            <div class="fw-bold text-sm text-dark">Assign Shift</div>
                                            <small class="text-muted text-xs">Atur jam kerja</small>
                                        </div>
                                    </div>
                                    <div
                                        class="stitch-card p-3 flex-1 cursor-pointer transition-all d-flex align-items-center gap-3 border"
                                        :class="
                                            bulkModal.actionType === 'OFFDAY' ? 'border-primary bg-primary-subtle' : ''
                                        "
                                        @click="bulkModal.actionType = 'OFFDAY'"
                                    >
                                        <div class="rounded-circle bg-white p-2 text-primary shadow-sm">
                                            <i class="bi bi-moon-stars"></i>
                                        </div>
                                        <div class="lh-1">
                                            <div class="fw-bold text-sm text-dark">Set Day Off</div>
                                            <small class="text-muted text-xs">Libur khusus tim</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="bulkModal.actionType" class="mt-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Pilih Anggota</label>
                                <div class="stitch-card p-3 mb-3 border bg-white">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-search text-muted"></i>
                                        <input
                                            v-model="bulkModal.search"
                                            class="form-control border-0 p-0"
                                            placeholder="Cari anggota untuk dipilih..."
                                            style="box-shadow: none"
                                        />
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                    <span class="text-sm fw-bold text-dark"
                                        >{{ bulkModal.selectedMembers.length }} anggota terpilih</span
                                    >
                                    <button @click="toggleSelectAllBulk" class="btn btn-sm text-primary p-0 fw-bold">
                                        {{ isAllSelected ? 'Unselect All' : 'Select All' }}
                                    </button>
                                </div>

                                <div
                                    class="member-list-scroll rounded-3 border bg-white overflow-auto"
                                    style="max-height: 280px; overflow-y: auto"
                                >
                                    <div
                                        v-for="member in filteredBulkMembers"
                                        :key="member.Kode_Karyawan"
                                        class="d-flex align-items-center gap-3 p-3 border-bottom hover-bg-light cursor-pointer transition-all"
                                        @click="toggleBulkMember(member.Kode_Karyawan)"
                                    >
                                        <div class="form-check m-0 pointer-events-none">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :checked="bulkModal.selectedMembers.includes(member.Kode_Karyawan)"
                                            />
                                        </div>
                                        <div
                                            class="avatar-circle bg-light text-dark fw-bold border"
                                            style="width: 40px; height: 40px"
                                        >
                                            {{ getInitials(member.Nama) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-sm text-dark">
                                                {{ member.Nama }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                {{ member.NIK }} •
                                                {{ member.nama_jabatan || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-3 rounded-3 border bg-white text-muted text-sm">
                                Pilih jenis aksi terlebih dulu, lalu pilih anggota.
                            </div>
                        </div>

                        <!-- Step 2: Mode & Tanggal -->
                        <div v-if="bulkModal.step === 2" class="fade-in">
                            <div class="mb-3" v-if="bulkModal.actionType === 'SHIFT'">
                                <label class="form-label fw-bold small text-muted text-uppercase"
                                    >Pilih Mode Shift</label
                                >
                                <div class="d-flex gap-3">
                                    <div
                                        class="stitch-card p-3 flex-1 cursor-pointer transition-all d-flex align-items-center gap-3 border"
                                        :class="
                                            bulkModal.jenis === 'SEMENTARA' ? 'border-primary bg-primary-subtle' : ''
                                        "
                                        @click="bulkModal.jenis = 'SEMENTARA'"
                                    >
                                        <div class="rounded-circle bg-white p-2 text-primary shadow-sm">
                                            <i class="bi bi-clock-history"></i>
                                        </div>
                                        <div class="lh-1">
                                            <div class="fw-bold text-sm text-dark">Sementara</div>
                                            <small class="text-muted text-xs">Bisa pilih banyak tanggal</small>
                                        </div>
                                    </div>
                                    <div
                                        class="stitch-card p-3 flex-1 cursor-pointer transition-all d-flex align-items-center gap-3 border"
                                        :class="
                                            bulkModal.jenis === 'PERMANEN' ? 'border-primary bg-primary-subtle' : ''
                                        "
                                        @click="bulkModal.jenis = 'PERMANEN'"
                                    >
                                        <div class="rounded-circle bg-white p-2 text-primary shadow-sm">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </div>
                                        <div class="lh-1">
                                            <div class="fw-bold text-sm text-dark">Permanen</div>
                                            <small class="text-muted text-xs">Satu tanggal berlaku seterusnya</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3" v-if="bulkModal.actionType === 'OFFDAY'">
                                <label class="form-label fw-bold small text-muted text-uppercase">Mode Day Off</label>
                                <el-select v-model="bulkModal.offdayMode" class="w-100">
                                    <el-option label="Tanggal Terpilih" value="SINGLE" />
                                    <el-option label="Range Tanggal" value="RANGE" />
                                    <el-option label="Mulai Hari Ini" value="PERMANEN" />
                                </el-select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Pilih Tanggal</label>

                                <el-date-picker
                                    v-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'SEMENTARA'"
                                    v-model="bulkModal.dates"
                                    type="dates"
                                    placeholder="Klik untuk memilih tanggal"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="w-100 el-date-picker-full"
                                    :disabled-date="disabledDate"
                                />
                                <el-date-picker
                                    v-else-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'PERMANEN'"
                                    v-model="bulkModal.permanentDate"
                                    type="date"
                                    placeholder="Pilih tanggal permanen"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="w-100 el-date-picker-full"
                                    :disabled-date="disabledDate"
                                />
                                <el-date-picker
                                    v-else-if="bulkModal.actionType === 'OFFDAY' && bulkModal.offdayMode === 'SINGLE'"
                                    v-model="bulkModal.dates"
                                    type="dates"
                                    placeholder="Klik untuk memilih tanggal"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="w-100 el-date-picker-full"
                                    :disabled-date="disabledDate"
                                />
                                <el-date-picker
                                    v-else-if="bulkModal.offdayMode === 'RANGE'"
                                    v-model="bulkModal.offdayRange"
                                    type="daterange"
                                    placeholder="Pilih range tanggal"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="w-100 el-date-picker-full"
                                    :disabled-date="disabledDate"
                                />
                                <el-date-picker
                                    v-else
                                    v-model="bulkModal.offdayStart"
                                    type="date"
                                    placeholder="Pilih tanggal mulai"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="w-100 el-date-picker-full"
                                    :disabled-date="disabledDate"
                                />
                            </div>

                            <div
                                v-if="hasBackdateBulk"
                                class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-3 border-warning-subtle bg-warning-subtle text-warning-emphasis"
                            >
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span class="small fw-bold">Beberapa tanggal memerlukan approval atasan.</span>
                            </div>

                            <label
                                v-if="
                                    (bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'SEMENTARA') ||
                                    (bulkModal.actionType === 'OFFDAY' && bulkModal.offdayMode === 'SINGLE')
                                "
                                class="form-label fw-bold small text-muted text-uppercase mb-2"
                                >Tanggal Terpilih ({{ bulkModal.dates.length }})</label
                            >
                            <div
                                v-if="
                                    (bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'SEMENTARA') ||
                                    (bulkModal.actionType === 'OFFDAY' && bulkModal.offdayMode === 'SINGLE')
                                "
                                class="stitch-date-chips bg-white p-3 rounded-3 border"
                                style="min-height: 100px"
                            >
                                <span v-for="(date, i) in bulkModal.dates" :key="i" class="stitch-date-chip">
                                    <i class="bi bi-calendar-event"></i>
                                    <span>{{ formatDateShort(date) }}</span>
                                    <button @click="removeBulkDate(i)">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </span>
                                <span v-if="bulkModal.dates.length === 0" class="text-muted small fst-italic"
                                    >Belum ada tanggal dipilih</span
                                >
                            </div>
                        </div>

                        <!-- Step 3: Atur Detail -->
                        <div v-if="bulkModal.step === 3" class="fade-in">
                            <div
                                v-if="hasBackdateBulk"
                                class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-3 border-warning-subtle bg-warning-subtle text-warning-emphasis"
                            >
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span class="small fw-bold">Beberapa tanggal memerlukan approval atasan.</span>
                            </div>

                            <div
                                v-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'SEMENTARA'"
                                class="mt-3"
                            >
                                <div class="stitch-card p-3 border bg-white mb-3">
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem">
                                            Apply ke semua anggota
                                        </div>
                                        <div class="flex-1">
                                            <el-select
                                                v-model="bulkModal.applyAllShiftId"
                                                filterable
                                                clearable
                                                placeholder="Pilih shift"
                                                class="el-select-full"
                                                popper-class="el-select-popper-high"
                                                :disabled="!bulkModal.dates.length"
                                                @visible-change="
                                                    (val) => {
                                                        if (val && bulkModal.dates[0])
                                                            ensureBulkShiftOptionsForDate(bulkModal.dates[0]);
                                                    }
                                                "
                                            >
                                                <el-option
                                                    v-for="shift in getCachedShiftOptions(bulkModal.dates[0])"
                                                    :key="shift.value"
                                                    :label="shift.label"
                                                    :value="shift.value"
                                                />
                                            </el-select>
                                            <div
                                                v-if="
                                                    bulkModal.dates[0] &&
                                                    hasCachedShiftOptions(bulkModal.dates[0]) &&
                                                    !bulkShiftOptionsLoading[
                                                        getShiftOptionCacheKey(bulkModal.dates[0])
                                                    ] &&
                                                    !getCachedShiftOptions(bulkModal.dates[0]).length
                                                "
                                                class="text-warning text-xs mt-2"
                                            >
                                                Tidak ada shift yang sama-sama tersedia untuk semua anggota terpilih di
                                                tanggal ini.
                                            </div>
                                        </div>
                                        <button
                                            class="stitch-btn primary"
                                            :disabled="!bulkModal.applyAllShiftId"
                                            @click="applyBulkShiftToAllDates"
                                        >
                                            Apply
                                        </button>
                                    </div>
                                </div>
                                <div class="text-muted text-xs mt-2">
                                    Daftar shift mengikuti rule tiap karyawan dan hari, jadi bisa berbeda antar
                                    anggota/tanggal.
                                </div>

                                <div class="d-flex flex-column gap-3" style="max-height: 360px; overflow-y: auto">
                                    <div
                                        v-for="member in bulkModal.memberDateItems"
                                        :key="member.kode"
                                        class="stitch-roster-item"
                                    >
                                        <div class="stitch-date-box">
                                            <span class="stitch-date-day">
                                                {{ getMemberNameByKode(member.kode) }}
                                            </span>
                                            <span class="text-xs text-muted">
                                                {{ member.kode }}
                                            </span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-xs text-muted">
                                                {{ getBulkMemberCompletion(member.kode).filled }}/{{
                                                    getBulkMemberCompletion(member.kode).total
                                                }}
                                                tanggal terisi
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                v-if="
                                                    getBulkMemberCompletion(member.kode).filled ===
                                                        getBulkMemberCompletion(member.kode).total &&
                                                    getBulkMemberCompletion(member.kode).total > 0
                                                "
                                                class="stitch-badge approved"
                                            >
                                                <i class="bi bi-check"></i>
                                                Ready
                                            </span>
                                            <span v-else class="stitch-badge pending"> Pending </span>
                                            <button
                                                class="stitch-btn secondary"
                                                :disabled="!bulkModal.dates.length"
                                                @click="openBulkMemberModal(member.kode)"
                                            >
                                                Atur
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="!bulkModal.memberDateItems.length" class="text-muted small fst-italic">
                                        Belum ada anggota dipilih
                                    </div>
                                </div>
                            </div>

                            <div v-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'PERMANEN'" class="mt-3">
                                <div class="stitch-card p-3 border bg-white mb-3">
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem">
                                            Apply ke semua anggota
                                        </div>
                                        <div class="flex-1">
                                            <el-select
                                                v-model="bulkModal.applyAllMemberShiftId"
                                                filterable
                                                clearable
                                                placeholder="Pilih shift"
                                                class="el-select-full"
                                                popper-class="el-select-popper-high"
                                                :disabled="!bulkModal.permanentDate"
                                                @visible-change="
                                                    (val) => {
                                                        if (val) loadBulkPermanentShiftOptions();
                                                    }
                                                "
                                            >
                                                <el-option
                                                    v-for="shift in bulkPermanentShiftOptions"
                                                    :key="shift.value"
                                                    :label="shift.label"
                                                    :value="shift.value"
                                                />
                                            </el-select>
                                            <div
                                                v-if="
                                                    bulkModal.permanentDate &&
                                                    bulkPermanentShiftOptionsLoaded &&
                                                    !bulkPermanentShiftOptionsLoading &&
                                                    !bulkPermanentShiftOptions.length
                                                "
                                                class="text-warning text-xs mt-2"
                                            >
                                                Tidak ada shift bersama untuk seluruh anggota terpilih pada tanggal
                                                permanen ini.
                                            </div>
                                        </div>
                                        <button
                                            class="stitch-btn primary"
                                            :disabled="!bulkModal.applyAllMemberShiftId || !bulkModal.permanentDate"
                                            @click="applyBulkShiftToAllMembers"
                                        >
                                            Apply
                                        </button>
                                    </div>
                                </div>
                                <div class="text-muted text-xs mt-2">
                                    Opsi permanen adalah irisan shift yang valid untuk anggota terpilih pada tanggal
                                    mulai.
                                </div>

                                <div class="d-flex flex-column gap-3" style="max-height: 360px; overflow-y: auto">
                                    <div
                                        v-for="item in bulkModal.memberShiftItems"
                                        :key="item.kode"
                                        class="stitch-roster-item"
                                    >
                                        <div class="stitch-date-box">
                                            <span class="stitch-date-day">
                                                {{ getMemberNameByKode(item.kode) }}
                                            </span>
                                            <span class="text-xs text-muted">
                                                {{ item.kode }}
                                            </span>
                                        </div>
                                        <div class="flex-1">
                                            <el-select
                                                v-model="item.shiftId"
                                                filterable
                                                clearable
                                                placeholder="Pilih shift..."
                                                class="el-select-full"
                                                popper-class="el-select-popper-high"
                                                :disabled="!bulkModal.permanentDate"
                                            >
                                                <el-option
                                                    v-for="shift in bulkPermanentShiftOptions"
                                                    :key="shift.value"
                                                    :label="shift.label"
                                                    :value="shift.value"
                                                />
                                            </el-select>
                                        </div>
                                        <div>
                                            <span v-if="item.shiftId" class="stitch-badge approved">
                                                <i class="bi bi-check"></i>
                                                Ready
                                            </span>
                                            <span v-else class="stitch-badge pending"> Pending </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="bulkModal.memberShiftItems.length === 0"
                                        class="text-muted small fst-italic"
                                    >
                                        Belum ada anggota dipilih
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-3">
                                <div
                                    class="col-12"
                                    v-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'PERMANEN'"
                                >
                                    <!-- Overwrite Toggle (compact) -->
                                    <div
                                        class="d-flex align-items-center justify-content-between px-3 py-2 rounded-3 border mb-2"
                                        style="background: #f8f9fa"
                                    >
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-layers text-muted" style="font-size: 0.85rem"></i>
                                            <span class="text-dark text-xs fw-semibold"
                                                >Timpa shift sementara di depannya</span
                                            >
                                        </div>
                                        <label class="toggle-switch-sm mb-0">
                                            <input type="checkbox" v-model="bulkOverwriteSementara" />
                                            <span class="slider-sm"></span>
                                        </label>
                                    </div>

                                    <div
                                        v-if="bulkOverwriteSementara && bulkPermanentConflictLoading"
                                        class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-0 border-warning-subtle bg-warning-subtle text-warning-emphasis"
                                    >
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span class="small fw-bold"
                                            >Memeriksa shift sementara yang akan ditimpa...</span
                                        >
                                    </div>
                                    <div
                                        v-else-if="bulkOverwriteSementara && bulkPermanentConflictTotal > 0"
                                        class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-0 border-warning-subtle bg-warning-subtle text-warning-emphasis"
                                    >
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span class="small fw-bold"
                                            >{{ bulkPermanentConflictTotal }} shift sementara aktif setelah tanggal
                                            terpilih akan dinonaktifkan.</span
                                        >
                                    </div>
                                    <div
                                        v-else-if="
                                            bulkOverwriteSementara &&
                                            bulkModal.permanentDate &&
                                            !bulkPermanentConflictLoading &&
                                            bulkPermanentConflictTotal === 0
                                        "
                                        class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-0 border-success-subtle bg-success-subtle text-success-emphasis"
                                    >
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span class="small fw-bold">Tidak ada shift sementara yang akan ditimpa.</span>
                                    </div>
                                    <!-- <div
                                        v-if="
                                            !bulkPermanentConflictLoading &&
                                            bulkPermanentConflictTotal > 0
                                        "
                                        class="mt-2 p-2 rounded-3 border bg-white"
                                    >
                                        <div class="row g-2">
                                            <div class="col-12 col-md-6">
                                                <div
                                                    class="text-muted text-uppercase fw-bold"
                                                    style="font-size: 0.65rem"
                                                >
                                                    Per Tanggal
                                                </div>
                                                <div
                                                    v-for="item in bulkPermanentConflictByDate"
                                                    :key="item.date"
                                                    class="d-flex justify-content-between text-xs"
                                                >
                                                    <span>{{
                                                        formatDateShort(
                                                            item.date,
                                                        )
                                                    }}</span>
                                                    <span class="fw-bold">{{
                                                        item.count
                                                    }}</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div
                                                    class="text-muted text-uppercase fw-bold"
                                                    style="font-size: 0.65rem"
                                                >
                                                    Per Karyawan
                                                </div>
                                                <div
                                                    v-for="item in bulkPermanentConflictByMember"
                                                    :key="item.member"
                                                    class="d-flex justify-content-between text-xs"
                                                >
                                                    <span>
                                                        {{
                                                            getMemberNameByKode(
                                                                item.member,
                                                            )
                                                        }}
                                                    </span>
                                                    <span class="fw-bold">{{
                                                        item.count
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div> -->
                                    <div
                                        v-if="
                                            bulkOverwriteSementara &&
                                            !bulkPermanentConflictLoading &&
                                            bulkPermanentConflictTotal > 0
                                        "
                                        class="mt-2 p-2 rounded-3 border bg-white"
                                    >
                                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem">
                                            Catatan per orang
                                        </div>
                                        <div
                                            v-for="item in bulkPermanentConflictByMember"
                                            :key="item.member"
                                            class="d-flex justify-content-between text-xs"
                                        >
                                            <span>
                                                {{ getMemberNameByKode(item.member) }}
                                            </span>
                                            <span class="fw-bold"> Menimpa {{ item.count }} shift </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12" v-if="bulkModal.actionType === 'OFFDAY'">
                                    <div class="p-3 rounded-3 border bg-white">
                                        <div class="fw-bold text-dark mb-1">Ringkasan Offday</div>
                                        <div class="text-xs text-muted">Mode: {{ bulkModal.offdayMode }}</div>
                                        <div class="text-xs text-muted" v-if="bulkModal.offdayMode === 'RANGE'">
                                            {{
                                                bulkModal.offdayRange?.[0]
                                                    ? formatDateShort(bulkModal.offdayRange[0])
                                                    : '-'
                                            }}
                                            -
                                            {{
                                                bulkModal.offdayRange?.[1]
                                                    ? formatDateShort(bulkModal.offdayRange[1])
                                                    : '-'
                                            }}
                                        </div>
                                        <div class="text-xs text-muted" v-else-if="bulkModal.offdayMode === 'PERMANEN'">
                                            Mulai:
                                            {{ bulkModal.offdayStart ? formatDateShort(bulkModal.offdayStart) : '-' }}
                                        </div>
                                        <div
                                            v-if="bulkModal.offdayMode === 'PERMANEN'"
                                            class="text-xs mt-2 p-2 rounded-2 bg-primary-subtle text-primary-emphasis border border-primary-subtle"
                                        >
                                            Offday akan mengikuti hari yang sama setiap minggu mulai tanggal di atas dan
                                            berlaku seterusnya.
                                        </div>
                                        <div class="text-xs text-muted" v-else>
                                            {{ bulkModal.dates.length }} tanggal terpilih
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Alasan</label>
                                    <textarea
                                        v-model="bulkModal.alasan"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Tulis alasan pengajuan..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review -->
                        <div v-if="bulkModal.step === 4" class="fade-in">
                            <div class="p-4 rounded-3 bg-white border">
                                <h6 class="fw-bold mb-3 text-primary">
                                    <i class="bi bi-clipboard-check me-2"></i>Ringkasan Pengajuan
                                </h6>

                                <!-- Action Type -->
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem">
                                        Jenis Aksi
                                    </div>
                                    <div class="fw-bold text-dark">
                                        {{ bulkModal.actionType === 'SHIFT' ? 'Atur Shift' : 'Atur Day Off' }}
                                    </div>
                                </div>

                                <!-- Mode & Dates -->
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem">
                                        Mode & Tanggal
                                    </div>
                                    <div v-if="bulkModal.actionType === 'SHIFT'" class="text-dark">
                                        <div class="fw-bold mb-1">
                                            {{ bulkModal.jenis }}
                                        </div>
                                        <div class="text-muted small">
                                            <span v-if="bulkModal.jenis === 'SEMENTARA'"
                                                >{{ bulkModal.dates.length }} tanggal terpilih</span
                                            >
                                            <span v-else
                                                >Mulai:
                                                {{
                                                    bulkModal.permanentDate
                                                        ? formatDateShort(bulkModal.permanentDate)
                                                        : '-'
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                    <div v-else class="text-dark">
                                        <div class="fw-bold mb-1">
                                            {{ bulkModal.offdayMode }}
                                        </div>
                                        <div class="text-muted small">
                                            <span v-if="bulkModal.offdayMode === 'SINGLE'"
                                                >{{ bulkModal.dates.length }} tanggal</span
                                            >
                                            <span v-else-if="bulkModal.offdayMode === 'RANGE'">
                                                {{
                                                    bulkModal.offdayRange?.[0]
                                                        ? formatDateShort(bulkModal.offdayRange[0])
                                                        : '-'
                                                }}
                                                -
                                                {{
                                                    bulkModal.offdayRange?.[1]
                                                        ? formatDateShort(bulkModal.offdayRange[1])
                                                        : '-'
                                                }}
                                            </span>
                                            <span v-else
                                                >Mulai:
                                                {{
                                                    bulkModal.offdayStart ? formatDateShort(bulkModal.offdayStart) : '-'
                                                }}
                                                <span class="d-block mt-1"
                                                    >Berlaku setiap
                                                    {{ getDayName(bulkModal.offdayStart) || 'hari yang sama' }}
                                                    dan seterusnya</span
                                                ></span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Members -->
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem">
                                        Anggota ({{ bulkModal.selectedMembers.length }})
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span
                                            v-for="kode in bulkModal.selectedMembers"
                                            :key="kode"
                                            class="badge bg-primary-subtle text-primary px-2 py-1"
                                        >
                                            {{ getMemberNameByKode(kode) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Detail Assignment Preview -->
                                <div
                                    v-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'SEMENTARA'"
                                    class="mb-3 pb-3 border-bottom"
                                >
                                    <div class="text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem">
                                        Detail Shift per Anggota
                                    </div>
                                    <div class="text-muted small">
                                        <div
                                            v-for="member in bulkModal.memberDateItems"
                                            :key="member.kode"
                                            class="mb-1"
                                        >
                                            <span class="fw-bold">{{ getMemberNameByKode(member.kode) }}:</span>
                                            {{ getBulkMemberCompletion(member.kode).filled }}/{{
                                                getBulkMemberCompletion(member.kode).total
                                            }}
                                            shift terisi
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="bulkModal.actionType === 'SHIFT' && bulkModal.jenis === 'PERMANEN'"
                                    class="mb-3 pb-3 border-bottom"
                                >
                                    <div class="text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem">
                                        Detail Shift Permanen
                                    </div>
                                    <div class="text-muted small">
                                        <div v-for="item in bulkModal.memberShiftItems" :key="item.kode" class="mb-1">
                                            <span class="fw-bold">{{ getMemberNameByKode(item.kode) }}:</span>
                                            {{ item.shiftId ? getShiftNameById(item.shiftId) : 'Belum dipilih' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Warnings -->
                                <div v-if="hasBackdateBulk" class="mb-3 pb-3 border-bottom">
                                    <div
                                        class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-0 border-warning-subtle bg-warning-subtle text-warning-emphasis"
                                    >
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span class="small fw-bold">Beberapa tanggal memerlukan approval atasan</span>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        bulkModal.actionType === 'SHIFT' &&
                                        bulkModal.jenis === 'PERMANEN' &&
                                        bulkOverwriteSementara &&
                                        bulkPermanentConflictTotal > 0
                                    "
                                    class="mb-3 pb-3 border-bottom"
                                >
                                    <div
                                        class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-0 border-warning-subtle bg-warning-subtle text-warning-emphasis"
                                    >
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span class="small fw-bold"
                                            >{{ bulkPermanentConflictTotal }} shift sementara akan ditimpa</span
                                        >
                                    </div>
                                </div>

                                <!-- Alasan -->
                                <div>
                                    <div class="text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem">
                                        Alasan
                                    </div>
                                    <div class="text-dark">
                                        {{ bulkModal.alasan || '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="w-100 d-flex justify-content-between align-items-center">
                    <button v-if="bulkModal.step > 1" class="btn-modern btn-secondary" @click="bulkModal.step--">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </button>
                    <div v-else></div>

                    <button
                        v-if="bulkModal.step < 4"
                        class="btn-modern btn-primary"
                        @click="proceedBulkStep"
                        :disabled="!canProceedBulk"
                    >
                        Lanjut <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <button
                        v-else
                        class="btn-modern btn-primary bg-success border-success"
                        @click="confirmSubmitBulk"
                        :disabled="!canSubmitBulk || loading.submit"
                    >
                        {{ loading.submit ? 'Memproses...' : 'Submit Pengajuan' }}
                        <i class="bi bi-check-lg ms-1"></i>
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Confirmation Modal -->
        <div class="modal-confirmation-wrapper">
            <Modal
                :show="confirmModal.visible"
                :title="confirmModal.title || 'Konfirmasi Perubahan'"
                size="medium"
                :z-index="10050"
                @close="handleConfirmModalClose"
            >
                <template #body>
                    <div class="confirmation-content">
                        <!-- Loading State -->
                        <div v-if="confirmModal.isSubmitting" class="text-center p-4">
                            <div class="loading-spinner" style="width: 48px; height: 48px; margin: 0 auto">
                                <i class="bi bi-arrow-repeat" style="font-size: 1.5rem"></i>
                            </div>
                            <p class="text-muted mt-3 fw-medium">Menyimpan perubahan...</p>
                        </div>

                        <!-- Content (hidden during submission) -->
                        <div v-if="!confirmModal.isSubmitting" class="confirmation-content-wrapper">
                            <div class="confirmation-message">
                                {{ confirmModal.message }}
                            </div>

                            <!-- Detail Box for Shift Change -->
                            <div v-if="confirmModal.details" class="confirmation-details-box">
                                <div class="detail-row">
                                    <span class="detail-label">Dari:</span>
                                    <span class="detail-value"
                                        >{{ confirmModal.details.from.name }} ({{
                                            confirmModal.details.from.time
                                        }})</span
                                    >
                                </div>

                                <div class="detail-row">
                                    <span class="detail-label">Ke:</span>
                                    <span class="detail-value"
                                        >{{ confirmModal.details.to.name }} ({{ confirmModal.details.to.time }})</span
                                    >
                                </div>

                                <div class="detail-row">
                                    <span class="detail-label">Jenis:</span>
                                    <span class="detail-value">{{ confirmModal.details.jenis }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <template #footer>
                    <div class="confirmation-footer">
                        <button
                            class="stitch-btn secondary flex-grow-1 flex-md-grow-0"
                            @click="closeConfirmModal"
                            :disabled="confirmModal.isSubmitting"
                        >
                            <i class="bi bi-x-circle me-1"></i>
                            Batal
                        </button>
                        <button
                            class="stitch-btn success flex-grow-1 flex-md-grow-0"
                            @click="handleConfirmSubmit"
                            :disabled="confirmModal.isSubmitting"
                        >
                            <span v-if="confirmModal.isSubmitting">
                                <i class="bi bi-arrow-repeat spin-animation me-1"></i>
                                Memproses...
                            </span>
                            <span v-else :disabled="!canSubmitBulk">
                                <i class="bi bi-check-circle me-1"></i>
                                Ya, Lanjutkan
                            </span>
                        </button>
                    </div>
                </template>
            </Modal>
        </div>

        <!-- Cancel Confirmation Modal -->
        <div class="modal-confirmation-wrapper">
            <Modal :show="cancelModal.visible" :title="cancelModal.title" size="medium" @close="closeCancelModal">
                <template #body>
                    <div class="confirmation-content">
                        <div class="confirmation-message">
                            {{ cancelModal.message }}
                        </div>
                    </div>
                </template>
                <template #footer>
                    <div class="confirmation-footer">
                        <button class="stitch-btn secondary flex-grow-1 flex-md-grow-0" @click="closeCancelModal">
                            <i class="bi bi-x-circle me-1"></i>
                            Batal
                        </button>
                        <button
                            class="stitch-btn danger flex-grow-1 flex-md-grow-0"
                            @click="confirmCancelHistory"
                            :disabled="loading.cancelHistory"
                        >
                            <span v-if="loading.cancelHistory">
                                <i class="bi bi-arrow-repeat spin-animation me-1"></i>
                                Memproses...
                            </span>
                            <span v-else>
                                <i class="bi bi-check-circle me-1"></i>
                                Ya, Batalkan
                            </span>
                        </button>
                    </div>
                </template>
            </Modal>
        </div>

        <Modal
            :show="bulkMemberModal.visible"
            :title="`Atur Shift Sementara`"
            :subtitle="bulkMemberModal.kode ? `Fokus: ${getMemberNameByKode(bulkMemberModal.kode)}` : ''"
            size="large"
            @close="closeBulkMemberModal"
        >
            <template #body>
                <div class="p-4" style="background: #f8f9fa">
                    <div v-if="!bulkMemberModal.kode" class="text-muted small fst-italic">
                        Pilih anggota untuk mengatur shift sementara.
                    </div>
                    <div v-else>
                        <div class="stitch-card p-3 border bg-white mb-3">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem">
                                    Apply ke semua tanggal
                                </div>
                                <div class="flex-1">
                                    <el-select
                                        v-model="bulkMemberModal.applyAllShiftId"
                                        filterable
                                        clearable
                                        placeholder="Pilih shift"
                                        class="el-select-full"
                                        popper-class="el-select-popper-high"
                                        :disabled="!bulkModal.dates.length"
                                        @visible-change="
                                            (val) => {
                                                if (val && bulkModal.dates[0])
                                                    getShiftOptionsForDate(bulkModal.dates[0], bulkMemberModal.kode);
                                            }
                                        "
                                    >
                                        <el-option
                                            v-for="shift in getCachedShiftOptions(
                                                bulkModal.dates[0],
                                                bulkMemberModal.kode,
                                            )"
                                            :key="shift.value"
                                            :label="shift.label"
                                            :value="shift.value"
                                        />
                                    </el-select>
                                    <div
                                        v-if="
                                            bulkModal.dates[0] &&
                                            hasCachedShiftOptions(bulkModal.dates[0], bulkMemberModal.kode) &&
                                            !bulkShiftOptionsLoading[
                                                getShiftOptionCacheKey(bulkModal.dates[0], bulkMemberModal.kode)
                                            ] &&
                                            !getCachedShiftOptions(bulkModal.dates[0], bulkMemberModal.kode).length
                                        "
                                        class="text-warning text-xs mt-2"
                                    >
                                        Tidak ada shift tersedia untuk anggota ini di tanggal tersebut.
                                    </div>
                                </div>
                                <button
                                    class="stitch-btn primary"
                                    :disabled="!bulkMemberModal.applyAllShiftId"
                                    @click="applyBulkShiftToMemberDates"
                                >
                                    Apply
                                </button>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-3" style="max-height: 420px; overflow-y: auto">
                            <div
                                v-for="item in (getBulkMemberDateItems(bulkMemberModal.kode) || {}).dates"
                                :key="item.tanggal"
                                class="stitch-roster-item"
                            >
                                <div class="stitch-date-box">
                                    <span class="stitch-date-day">
                                        {{ formatDateShort(item.tanggal) }}
                                    </span>
                                    <span class="text-xs text-muted">
                                        {{ item.tanggal }}
                                    </span>
                                </div>
                                <div class="flex-1">
                                    <el-select
                                        v-model="item.id_shift"
                                        filterable
                                        clearable
                                        placeholder="Pilih shift..."
                                        class="el-select-full"
                                        popper-class="el-select-popper-high"
                                        @visible-change="
                                            (val) => {
                                                if (val) getShiftOptionsForDate(item.tanggal, bulkMemberModal.kode);
                                            }
                                        "
                                    >
                                        <el-option
                                            v-for="shift in getCachedShiftOptions(item.tanggal, bulkMemberModal.kode)"
                                            :key="shift.value"
                                            :label="shift.label"
                                            :value="shift.value"
                                        />
                                    </el-select>
                                </div>
                                <div>
                                    <span v-if="item.id_shift" class="stitch-badge approved">
                                        <i class="bi bi-check"></i>
                                        Ready
                                    </span>
                                    <span v-else class="stitch-badge pending"> Pending </span>
                                </div>
                            </div>
                            <div
                                v-if="((getBulkMemberDateItems(bulkMemberModal.kode) || {}).dates || []).length === 0"
                                class="text-muted small fst-italic"
                            >
                                Belum ada tanggal dipilih
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="stitch-btn secondary" @click="closeBulkMemberModal">Tutup</button>
            </template>
        </Modal>
    </div>
    <!-- Riwayat jadwal — pintu tim: server menolak karyawan di luar tim/cakupan -->
    <JejakJadwalHariDrawer
        v-model="jejakHari.buka"
        :endpoint="`/${routePrefix}/jejak-hari`"
        :kode="jejakHari.kode"
        :tanggal="jejakHari.tanggal"
    />
    <!-- 1. Quick Modal (Calendar Click) - Stitch Style -->
    <Modal
        :show="showQuickModal"
        :title="`Edit Shift - ${quickModalData.date ? formatDate(quickModalData.date) : ''}`"
        size="medium"
        @close="showQuickModal = false"
    >
        <template #body>
            <div v-if="loading.quickModal" class="p-4 text-center">
                <div class="loading-spinner" style="width: 48px; height: 48px">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div class="text-muted small mt-2">Memuat detail shift...</div>
            </div>
            <div v-else class="stitch-modal-body" style="padding: 0">
                <button
                    type="button"
                    class="stitch-btn secondary w-100 mb-3 d-flex align-items-center justify-content-center gap-2"
                    @click="bukaJejakHari(quickModalData.member, quickModalData.date)"
                >
                    <HcisIcon name="clock-history" size="sm" /> Lihat riwayat jadwal {{ quickModalData.member?.Nama || 'karyawan ini' }} tanggal ini
                </button>
                <!-- Active Override Alert -->
                <div
                    v-if="quickModalData.activeOverride"
                    class="d-flex flex-column gap-2 p-3 mb-4 rounded-lg border border-warning shadow-sm"
                    style="background-color: #fffbeb"
                >
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill text-warning"></i>
                            <span class="text-dark font-bold text-sm">Override Aktif</span>
                        </div>
                        <span class="badge bg-warning text-dark border border-warning-subtle" style="font-size: 0.7rem">
                            {{ quickModalData.activeOverride.status_hari }}
                        </span>
                    </div>

                    <div class="text-xs text-muted">
                        Ini adalah hari
                        {{ quickModalData.activeOverride.status_hari }}.
                        <span v-if="quickModalData.activeOverride.keterangan"
                            >{{ quickModalData.activeOverride.keterangan }}.</span
                        >
                        Berlaku sejak
                        {{ formatDateUI(quickModalData.activeOverride.tgl_mulai) }}
                        {{
                            quickModalData.activeOverride.tgl_selesai
                                ? ' s.d. ' + formatDateUI(quickModalData.activeOverride.tgl_selesai)
                                : '(Permanen)'
                        }}.
                    </div>

                    <div class="mt-2 text-end">
                        <el-button
                            type="danger"
                            size="small"
                            plain
                            @click="stopActiveOverride"
                            :loading="quickModalData.loadingOverride"
                        >
                            <i class="bi bi-stop-circle me-1"></i> Akhiri Override
                        </el-button>
                    </div>
                </div>

                <!-- Pending Alert -->
                <div
                    v-if="quickModalData.isPending"
                    class="p-3 rounded-lg border flex items-center gap-3"
                    style="background: #fff7ed; border-color: #fdba74; color: #c2410c"
                >
                    <i class="bi bi-exclamation-circle-fill text-lg"></i>
                    <div class="text-sm font-medium">
                        Pengajuan shift/offday untuk tanggal ini sedang dalam proses approval. Anda tidak dapat
                        mengubahnya sampai proses selesai.
                        <div v-if="quickPendingNoTransaksiLabel" class="mt-1 text-xs">
                            No. Transaksi: {{ quickPendingNoTransaksiLabel }}
                        </div>
                        <div v-if="quickPendingRequesterLabel" class="mt-1 text-xs">
                            Diajukan oleh: {{ quickPendingRequesterLabel }}
                        </div>
                    </div>
                </div>

                <!-- Current Shift Display -->
                <div
                    v-if="(quickModalCurrentShift || quickModalData.newShiftId) && !quickModalData.isDayOff"
                    class="flex items-center gap-3 p-4 rounded-lg border mb-4"
                    style="background: var(--surface-soft)"
                >
                    <div class="stitch-card-icon blue">
                        <i class="bi bi-clock" style="font-size: 1.25rem"></i>
                    </div>
                    <div>
                        <div class="text-dark font-bold">
                            {{ getShiftDisplayName(quickModalCurrentShift || getShiftById(quickModalData.newShiftId)) }}
                        </div>
                        <div class="text-muted text-sm">
                            {{
                                getShiftDisplayTimes(quickModalCurrentShift || getShiftById(quickModalData.newShiftId))
                            }}
                        </div>
                    </div>
                </div>

                <div v-if="quickModalData.statusHari" class="mb-4">
                    <span
                        :class="getStatusHariBadgeClass(quickModalData.statusHari)"
                        style="font-size: 0.75rem; padding: 0.25rem 0.5rem"
                    >
                        {{ getStatusHariLabel(quickModalData.statusHari, quickModalData.statusHariLabel) }}
                    </span>
                </div>

                <div class="stitch-toggle mb-4">
                    <div class="d-flex flex-column">
                        <span class="text-dark font-semibold text-sm">Jadikan Day Off</span>
                        <span class="text-muted text-xs">Atur hari libur khusus untuk karyawan</span>
                    </div>
                    <label
                        class="position-relative d-flex items-center flex-column justify-content-end align-items-end"
                        style="min-width: 100px"
                    >
                        <label class="toggle-switch">
                            <input
                                type="checkbox"
                                v-model="quickModalData.isDayOff"
                                :disabled="
                                    quickModalData.isPending ||
                                    isLiburNasional(quickModalData.statusHari, quickModalData.statusHariLabel)
                                "
                            />
                            <span class="slider"></span>
                        </label>
                        <span class="toggle-label">
                            {{ quickModalData.isDayOff ? 'Day Off' : 'Shift' }}
                        </span>
                    </label>
                </div>

                <div v-if="shouldShowOffdayModeSelector" class="mb-4">
                    <label class="stitch-filter-label">Mode Day Off</label>
                    <el-select
                        v-model="quickModalData.offdayMode"
                        class="el-select-full"
                        :disabled="quickModalData.isPending"
                    >
                        <el-option label="Satu Tanggal" value="SINGLE" />
                        <el-option label="Range Tanggal" value="RANGE" />
                        <el-option label="Mulai Hari Ini" value="PERMANEN" />
                    </el-select>

                    <div class="mt-3" v-if="quickModalData.offdayMode === 'RANGE'">
                        <el-date-picker
                            v-model="quickModalData.offdayRange"
                            type="daterange"
                            format="DD MMM YYYY"
                            value-format="YYYY-MM-DD"
                            class="w-100 el-date-picker-full"
                        />
                    </div>

                    <div class="mt-3" v-else-if="quickModalData.offdayMode === 'PERMANEN'">
                        <el-date-picker
                            v-model="quickModalData.offdayStart"
                            type="date"
                            format="DD MMM YYYY"
                            value-format="YYYY-MM-DD"
                            class="w-100 el-date-picker-full"
                        />
                    </div>
                </div>

                <!-- Shift Select -->
                <div v-if="!quickModalData.isDayOff" class="flex flex-col gap-2 mb-4">
                    <label class="stitch-filter-label">Pilih Shift</label>
                    <el-select
                        v-model="quickModalData.newShiftId"
                        filterable
                        clearable
                        placeholder="Cari atau pilih shift..."
                        class="el-select-full"
                        popper-class="el-select-popper-high"
                        :loading="loading.shifts"
                        :disabled="quickModalData.isPending"
                    >
                        <el-option
                            v-for="shift in shiftOptions"
                            :key="shift.value"
                            :label="shift.display"
                            :value="shift.value"
                        />
                    </el-select>
                    <div v-if="!loading.shifts && !shiftOptions.length" class="text-warning small mt-2">
                        Shift tidak tersedia untuk karyawan ini pada tanggal ini (sesuai rule shifting). Coba tanggal
                        lain atau hubungi admin HR/Shift Rule.
                    </div>
                    <div class="text-muted text-xs mt-1">
                        Opsi shift bisa berbeda per karyawan dan per tanggal karena mengikuti rule.
                    </div>
                </div>

                <!-- Make Permanent Toggle -->
                <div class="stitch-toggle mb-4" v-if="!quickModalData.isDayOff">
                    <div class="d-flex flex-column">
                        <span class="text-dark font-semibold text-sm">Jadikan Permanen</span>
                        <span class="text-muted text-xs"
                            >Buat shift ini dari
                            {{ quickModalData.date ? getDayName(quickModalData.date) : '' }}
                            s.d seterusnya</span
                        >
                    </div>
                    <label
                        class="position-relative d-flex items-center flex-column justify-content-end align-items-end"
                        style="min-width: 100px"
                    >
                        <label class="toggle-switch">
                            <input
                                type="checkbox"
                                v-model="quickModalData.makePermanent"
                                :disabled="quickModalData.isPending"
                            />
                            <span class="slider"></span>
                        </label>
                        <span class="toggle-label">
                            {{ quickModalData.makePermanent ? 'Permanen' : 'Sementara' }}
                        </span>
                    </label>
                </div>

                <!-- Overwrite Sementara Toggle (compact, appears when permanent is ON) -->
                <div
                    v-if="quickModalData.makePermanent"
                    class="d-flex align-items-center justify-content-between px-3 py-2 mb-4 rounded-3 border"
                    style="background: #f8f9fa"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-layers text-muted" style="font-size: 0.85rem"></i>
                        <span class="text-dark text-xs fw-semibold">Timpa shift sementara di depannya</span>
                    </div>
                    <label class="toggle-switch-sm mb-0">
                        <input type="checkbox" v-model="quickModalData.overwriteSementara" />
                        <span class="slider-sm"></span>
                    </label>
                </div>

                <div
                    v-if="
                        quickModalData.makePermanent &&
                        quickModalData.overwriteSementara &&
                        quickModalPermanentConflictLoading
                    "
                    class="flex items-center gap-3 p-3 rounded-lg text-sm mb-4"
                    style="background: #fef3c7; border: 1px solid #fcd34d; color: #92400e"
                >
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Memeriksa shift sementara yang akan ditimpa...</span>
                </div>
                <div
                    v-else-if="
                        quickModalData.makePermanent &&
                        quickModalData.overwriteSementara &&
                        quickModalPermanentConflictCount > 0
                    "
                    class="flex items-center gap-3 p-3 rounded-lg text-sm mb-4"
                    style="background: #fef3c7; border: 1px solid #fcd34d; color: #92400e"
                >
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        <strong>Perhatian:</strong>
                        {{ quickModalPermanentConflictCount }} shift sementara aktif setelah tanggal ini akan
                        dinonaktifkan.
                    </span>
                </div>

                <div
                    v-if="isLiburNasional(quickModalData.statusHari, quickModalData.statusHariLabel)"
                    class="flex items-center gap-3 p-3 rounded-lg text-sm mb-4"
                    style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b"
                >
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        Tanggal ini adalah <strong>Libur Nasional</strong>. Perubahan hanya bisa dilakukan lewat tukar
                        hari.
                    </span>
                </div>

                <!-- Reason Input -->
                <div class="flex flex-col gap-2 mb-4">
                    <div class="flex justify-between items-center">
                        <label class="stitch-filter-label"
                            >Alasan <span v-if="!isAdmin" class="text-danger">*</span></label
                        >
                        <span class="text-xs text-muted">Maks. 200 karakter</span>
                    </div>
                    <textarea
                        v-model="quickModalData.alasan"
                        class="stitch-input"
                        rows="3"
                        placeholder="Tulis catatan tambahan..."
                        style="height: auto; resize: none"
                    ></textarea>
                </div>

                <!-- Approval Warning -->
                <div
                    v-if="quickModalData.date && checkNeedsApproval(getJenisPengajuan(quickModalData.date))"
                    class="flex items-center gap-3 p-3 rounded-lg text-sm"
                    style="background: #fef3c7; border: 1px solid #fcd34d; color: #92400e"
                >
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        <strong>Perhatian:</strong>
                        <template v-if="isBackdate(quickModalData.date)"
                            >Tanggal backdate membutuhkan approval.</template
                        >
                        <template v-else>Pengajuan ini membutuhkan approval.</template>
                    </span>
                </div>
            </div>
        </template>
        <template #footer>
            <button @click="showQuickModal = false" class="stitch-btn secondary flex-grow-1 flex-md-grow-0">
                Batal
            </button>
            <button
                @click="submitQuickModal"
                class="stitch-btn primary flex-grow-1 flex-md-grow-0"
                :disabled="loading.submit || quickModalData.isPending"
            >
                <span v-if="loading.submit">
                    <i class="bi bi-arrow-repeat spin-animation me-1"></i>
                    Memproses...
                </span>
                <span v-else>Simpan Shift</span>
            </button>
        </template>
    </Modal>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import Badge from '../../../components/ui/badge.vue';
import QuickStat from '@/components/legacy/quickStat.vue';
import JejakJadwalHariDrawer from '@/Pages/ESS/Attendance/Shift/Schedule/Components/JejakJadwalHariDrawer.vue';
import { HcisIcon } from '@/components/ui';
import { ElDatePicker, ElSelect, ElOption, ElMessage, ElInput, ElMessageBox } from 'element-plus';
import { h } from 'vue';
import 'element-plus/es/components/date-picker/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/input/style/css';
import axios from 'axios';
import { debounce } from 'lodash';
import dayjs from 'dayjs';
import 'dayjs/locale/id';

dayjs.locale('id');

export default {
    name: 'ESS/Attendance/Shift/Leadership/LeaderShift',
    components: {
        HeaderKpi,
        QuickStat,
        JejakJadwalHariDrawer,
        HcisIcon,
        ApprovalTabs,
        Modal,
        Badge,
        ModernFilter,
        ElDatePicker,
        ElSelect,
        ElOption,
        ElMessage,
        ElInput,
    },
    props: {
        serverTime: String,
        serverDate: String,
        loginKaryawanKode: String,
        approverInfo: Object,
        initialStats: Object,
        config: {
            type: Object,
            default: () => ({
                backdate_needs_approval: true,
                same_day_needs_approval: true,
                future_needs_approval: true,
                backdate_max_days: 7,
                same_days: true,
                future_max_days: 30,
            }),
        },
        isAdmin: {
            type: Boolean,
            default: false,
        },
        pageTitle: {
            type: String,
            default: 'Leader Shift Management',
        },
        pageSubtitle: {
            type: String,
            default: 'Kelola jadwal shift tim Anda',
        },
        approverFlows: {
            type: Array,
            default: () => [],
        },
        approverPolicy: {
            type: Object,
            default: null,
        },
        hideApproverPolicySection: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            dayNight: 'Siang',
            formattedDate: '',
            selectedDate: '',
            activeTab: 'kalender',
            viewMode: 'table', // 'table' or 'card'
            isMobile: window.innerWidth < 768,
            tabs: [
                {
                    id: 'kalender',
                    label: 'Kalender Tim',
                    icon: 'bi-calendar-week',
                    count: null,
                },
                {
                    id: 'riwayat',
                    label: 'Riwayat',
                    icon: 'bi-clock-history',
                    count: null,
                },
            ],

            // Modern Filter Config for History
            historyFilterConfig: {
                searchable: true,
                searchPlaceholder: 'Cari No. Transaksi atau Nama...',
                filters: [
                    {
                        key: 'created_at',
                        label: 'Tanggal Dibuat',
                        type: 'daterange',
                        dateType: 'daterange',
                        valueFormat: 'YYYY-MM-DD',
                        format: 'DD MMM YYYY',
                        placeholder: 'Pilih rentang tanggal',
                        value: 'created_at',
                        col: 4,
                    },
                    {
                        key: 'transaction_type',
                        label: 'Jenis Transaksi',
                        type: 'select',
                        options: [
                            { label: 'Semua', value: 'all' },
                            { label: 'Status Hari', value: 'STATUS_HARI' },
                            { label: 'Perubahan Shift', value: 'LEADER_SHIFT' },
                        ],
                        col: 4,
                    },
                    {
                        key: 'status',
                        label: 'Status Approval',
                        type: 'select',
                        options: [
                            { label: 'Semua', value: 'all' },
                            { label: 'Belum Direview', value: 'pending' },
                            { label: 'Sudah Direview', value: 'approved' },
                        ],
                        col: 4,
                    },
                ],
            },
            historyFilters: {
                created_at: null,
                transaction_type: 'all',
                status: 'all',
            },

            // State
            loading: {
                team: false,
                schedule: false,
                shifts: false,
                submit: false,
                history: false,
                quickModal: false,
                cancelHistory: false,
            },

            // Data
            team: [],
            searchMember: '',
            searchTimer: null, // For debounced search
            startDate: '', // For week navigation
            weekDays: [],
            weekSchedule: {}, // { 'KODE': { '2024-01-01': shiftObject } }
            weeklyHourSummary: {}, // { 'KODE': { total_hours, target_hours, delta_hours, status } }
            paginatedMembersData: [], // Members for current page from API
            schedulePagination: {
                page: 1,
                limit: 10,
                total: 0,
            },
            availableShifts: [],

            // Picker State
            pickerVisible: false,
            expandedMembers: [], // For mobile accordion

            // Quick Edit Modal (Legacy - to be removed)
            quickEdit: {
                visible: false,
                member: null,
                day: null,
                shiftId: null,
                alasan: '',
            },

            // Standard Quick Modal (SelfShift Style)
            showQuickModal: false,
            jejakHari: { buka: false, kode: '', tanggal: '' },
            quickModalData: {
                date: null,
                currentShift: null,
                newShiftId: null,
                makePermanent: false,
                overwriteSementara: false,
                alasan: '',
                member: null, // Additional context for LeaderShift
                statusHari: null,
                statusHariLabel: null,
                isDayOff: false,
                offdayMode: 'SINGLE',
                offdayStart: null,
                offdayEnd: null,
                offdayRange: [],
                activeOverride: null,
                loadingOverride: false,
            },
            quickModalPermanentConflictCount: 0,
            quickModalPermanentConflictLoading: false,
            lockedDates: [],
            shiftOptions: [], // For select dropdown
            shiftOptionsByDay: {},

            activeFilters: {
                search: '',
                shift: '',
            },

            // Bulk Modal
            bulkModal: {
                visible: false,
                step: 1, // 1: Action+Members | 2: Mode+Date | 3: Details | 4: Review
                search: '',
                selectedMembers: [], // Array of Kodes
                dates: [], // SEMENTARA dates
                permanentDate: null, // PERMANEN single date
                dateItems: [], // [{ tanggal, id_shift, shiftOptions, loading }]
                memberDateItems: [], // [{ kode, dates: [{ tanggal, id_shift }] }]
                memberShiftItems: [], // [{ kode, shiftId }]
                applyAllShiftId: null, // apply to all dates (SEMENTARA)
                applyAllMemberShiftId: null, // apply to all members (PERMANEN)
                jenis: 'SEMENTARA',
                alasan: '',
                actionType: null, // SHIFT | OFFDAY
                offdayMode: 'SINGLE', // SINGLE | RANGE | PERMANEN
                offdayRange: [],
                offdayStart: null,
            },
            bulkMemberModal: {
                visible: false,
                kode: null,
                applyAllShiftId: null,
            },
            confirmModal: {
                visible: false,
                title: '',
                message: '',
                details: null, // Object with shift change details
                action: null, // 'quick' or 'bulk'
                isSubmitting: false, // Track submission state
            },
            cancelModal: {
                visible: false,
                title: 'Konfirmasi Pembatalan',
                message: '',
                payload: null,
            },
            bulkShiftOptionsByDate: {},
            bulkShiftOptionsLoading: {},
            bulkPermanentShiftOptions: [],
            bulkPermanentShiftOptionsLoading: false,
            bulkPermanentShiftOptionsLoaded: false,
            bulkPermanentConflictTotal: 0,
            bulkPermanentConflictLoading: false,
            bulkPermanentConflictByDate: [],
            bulkPermanentConflictByMember: [],
            lastMissingPermanentAlertKey: null,
            bulkOverwriteSementara: false,

            // History
            historyItems: [],
            // historyFilters: { status: "all", search: "" },
            historyPagination: { page: 1, limit: 10, total: 0, total_pages: 1 },
            widgetStats: {
                team_count: 0,
                pending_count: 0,
                approved_month: 0,
            },

            // History Detail Modal
            historyDetailModal: {
                visible: false,
                loading: false,
                data: null,
                expandedMembers: [],
            },
        };
    },
    computed: {
        routePrefix() {
            return this.isAdmin ? 'leader-shift/admin' : 'leader-shift';
        },
        approvalConfig() {
            const map = [
                { key: 'BACKDATE', value: this.config.backdate_needs_approval },
                { key: 'SAME_DAY', value: this.config.same_day_needs_approval },
                { key: 'FUTURE', value: this.config.future_needs_approval },
            ];

            return {
                needsApproval: map.filter((item) => item.value).map((item) => item.key),

                noNeedsApproval: map.filter((item) => !item.value).map((item) => item.key),
            };
        },
        hasApprover() {
            // return !!(
            //     this.approverInfo &&
            //     (this.approverInfo.nama ||
            //         this.approverInfo.Nama ||
            //         this.approverInfo.Kode_Karyawan)
            // );

            // console.log("approverFlows:", this.approverFlows);
            // console.log("approverPolicy:", this.approverPolicy);
            return this.approverFlows && this.approverFlows.length > 0;
        },
        requesterSkipLevel() {
            const raw = this.approverPolicy?.skip_if_requester_level_id;
            if (raw === null || raw === undefined || raw === '') return null;
            const parsed = Number(raw);
            return Number.isNaN(parsed) ? null : parsed;
        },
        quickPendingRequesterLabel() {
            return this.getQuickPendingRequesterLabel();
        },
        quickPendingNoTransaksiLabel() {
            return this.quickModalData?.pendingNoTransaksi || '';
        },
        quickModalCurrentShift() {
            return this.quickModalData.currentShift || this.getShiftById(this.quickModalData.newShiftId);
        },
        quickStatField() {
            return [
                {
                    title: 'Total Tim',
                    value: this.widgetStats?.team_count || 0,
                    icon: 'bi-people',
                    bgIcon: 'bg-primary',
                    size: 'col-4',
                    color: 'blue',
                    unit: '',
                    class: '',
                },
                {
                    title: 'Pending',
                    value: this.widgetStats?.pending_count || 0,
                    icon: 'bi-hourglass-split',
                    bgIcon: 'bg-warning',
                    size: 'col-4',
                    color: 'orange',
                    unit: '',
                    class: '',
                },
                {
                    title: 'Approved',
                    value: this.widgetStats?.approved_month || 0,
                    icon: 'bi-check-circle',
                    bgIcon: 'bg-success',
                    size: 'col-4',
                    color: 'green',
                    unit: '',
                    class: '',
                },
            ];
        },
        filteredMembers() {
            let list = [...this.team];

            // 1. Filter by Search
            if (this.searchMember) {
                const q = this.searchMember.toLowerCase();
                list = list.filter((m) => m.Nama?.toLowerCase().includes(q) || m.NIK?.toLowerCase().includes(q));
            }

            // 2. Filter by Shift (Show member if they have this shift on ANY visible day)
            if (this.activeFilters.shift) {
                const targetShiftId = this.activeFilters.shift;
                list = list.filter((m) => {
                    const schedule = this.weekSchedule[m.Kode_Karyawan];
                    if (!schedule) return false;
                    // Check if any day in current week has this shift
                    return Object.values(schedule).some((s) => s.shift && s.shift.id === targetShiftId);
                });
            }

            return list.sort((a, b) => a.Nama.localeCompare(b.Nama));
        },
        // Get member data from API response (paginated)
        paginatedMembers() {
            return this.paginatedMembersData;
        },
        totalSchedulePages() {
            return Math.ceil(this.schedulePagination.total / this.schedulePagination.limit) || 1;
        },
        formatWeekRange() {
            if (this.weekDays.length === 0) return '-';
            const start = this.weekDays[0].date;
            const end = this.weekDays[6].date;
            return `${dayjs(start).format('D MMM')} - ${dayjs(end).format('D MMM YYYY')}`;
        },
        weekRangeDisplay() {
            return this.formatWeekRange;
        },
        monthYearDisplay() {
            if (this.weekDays.length === 0) return '-';
            const start = this.weekDays[0];
            return `${start.month} ${start.year}`;
        },
        // Bulk Computed
        filteredBulkMembers() {
            const list = !this.bulkModal.search
                ? [...this.team]
                : this.team.filter(
                      (m) =>
                          m.Nama?.toLowerCase().includes(this.bulkModal.search.toLowerCase()) ||
                          m.NIK?.toLowerCase().includes(this.bulkModal.search.toLowerCase()),
                  );
            return list.sort((a, b) => a.Nama.localeCompare(b.Nama));
        },
        shouldShowOffdayModeSelector() {
            // Tampil Mode Day Off HANYA jika:
            // 1. isDayOff toggle ON (user sedang mengaktifkan day off)
            // 2. status_hari bukan OFF (hari belum memang day off)
            if (!this.quickModalData.isDayOff) {
                return false; // Jika toggle OFF, jangan tampil
            }

            // Check apakah hari sudah OFF
            const statusHari = this.quickModalData.statusHari;
            if (statusHari && typeof statusHari === 'string' && statusHari.startsWith('OFF')) {
                return false; // Hari sudah OFF, jangan tampil
            }

            return true; // Hari WORK dan toggle ON = tampil Mode Day Off
        },
        isAllSelected() {
            if (this.filteredBulkMembers.length === 0) return false;
            return this.filteredBulkMembers.every((m) => this.bulkModal.selectedMembers.includes(m.Kode_Karyawan));
        },
        hasBackdateBulk() {
            const today = dayjs().format('YYYY-MM-DD');
            if (this.bulkModal.actionType === 'OFFDAY') {
                if (this.bulkModal.offdayMode === 'RANGE') {
                    return this.bulkModal.offdayRange?.length === 2 && this.bulkModal.offdayRange[0] < today;
                }
                if (this.bulkModal.offdayMode === 'PERMANEN') {
                    return this.bulkModal.offdayStart && this.bulkModal.offdayStart < today;
                }
            }
            // For shifts, use config-based approval check
            if (this.bulkModal.jenis === 'PERMANEN') {
                if (!this.bulkModal.permanentDate) return false;
                const jenis = this.getJenisPengajuan(this.bulkModal.permanentDate);
                return this.checkNeedsApproval(jenis);
            }
            if (!this.bulkModal.dates.length) return false;
            return this.bulkModal.dates.some((d) => {
                const jenis = this.getJenisPengajuan(d);
                return this.checkNeedsApproval(jenis);
            });
        },
        hasBulkPermanentDate() {
            return !!this.bulkModal.permanentDate;
        },
        canProceedBulk() {
            if (this.bulkModal.step === 1) {
                return !!this.bulkModal.actionType;
            }
            if (this.bulkModal.step === 2) {
                return this.bulkModal.selectedMembers.length > 0;
            }
            if (this.bulkModal.step === 3) {
                if (this.bulkModal.actionType === 'OFFDAY') {
                    if (this.bulkModal.offdayMode === 'RANGE') {
                        return this.bulkModal.offdayRange?.length === 2;
                    }
                    if (this.bulkModal.offdayMode === 'PERMANEN') {
                        return !!this.bulkModal.offdayStart;
                    }
                    return this.bulkModal.dates.length > 0;
                }

                if (this.bulkModal.jenis === 'PERMANEN') {
                    return !!this.bulkModal.permanentDate && this.bulkModal.memberShiftItems.length > 0;
                }
                return this.bulkModal.dates.length > 0;
            }
            return true;
        },
        canSubmitBulk() {
            if (this.bulkModal.actionType === 'OFFDAY') {
                if (this.bulkModal.offdayMode === 'RANGE') {
                    return this.bulkModal.offdayRange?.length === 2 && this.bulkModal.selectedMembers.length > 0;
                }
                if (this.bulkModal.offdayMode === 'PERMANEN') {
                    return !!this.bulkModal.offdayStart && this.bulkModal.selectedMembers.length > 0;
                }
                return this.bulkModal.dates.length > 0 && this.bulkModal.selectedMembers.length > 0;
            }
            if (this.bulkModal.jenis === 'PERMANEN') {
                if (!this.bulkModal.permanentDate) return false;
                return this.bulkModal.memberShiftItems.every((item) => !!item.shiftId);
            }
            if (!this.bulkModal.memberDateItems.length) return false;
            return this.bulkModal.memberDateItems.every((member) => member.dates.every((item) => !!item.id_shift));
        },
        hasMoreHistory() {
            return this.historyPagination.page < this.historyPagination.total_pages;
        },
        inLoadingTime() {
            return this.loading.team || this.loading.shifts || this.loading.schedule || this.loading.history;
        },
    },
    watch: {
        'quickModalData.isDayOff'(val) {
            if (val) {
                this.quickModalData.newShiftId = null;
                this.quickModalData.makePermanent = false;
            }
        },
        'quickModalData.makePermanent'(val) {
            if (!val) {
                this.quickModalPermanentConflictCount = 0;
                this.quickModalData.overwriteSementara = false;
                return;
            }
            if (this.quickModalData.overwriteSementara) {
                this.updateQuickModalPermanentConflicts();
            }
        },
        'quickModalData.overwriteSementara'(val) {
            if (val && this.quickModalData.makePermanent) {
                this.updateQuickModalPermanentConflicts();
            } else {
                this.quickModalPermanentConflictCount = 0;
            }
        },
        'quickModalData.date'() {
            if (this.quickModalData.makePermanent && this.quickModalData.overwriteSementara) {
                this.updateQuickModalPermanentConflicts();
            }
        },
        'quickModalData.member'() {
            if (this.quickModalData.makePermanent && this.quickModalData.overwriteSementara) {
                this.updateQuickModalPermanentConflicts();
            }
        },
        'bulkModal.actionType'(val) {
            if (val === 'OFFDAY') {
                this.bulkModal.jenis = 'SEMENTARA';
                this.bulkModal.dates = [];
                this.bulkModal.permanentDate = null;
                this.bulkModal.dateItems = [];
                this.bulkModal.memberDateItems = [];
                this.bulkModal.applyAllShiftId = null;
                this.bulkModal.memberShiftItems = [];
                this.bulkModal.applyAllMemberShiftId = null;
                this.bulkMemberModal = {
                    visible: false,
                    kode: null,
                    applyAllShiftId: null,
                };
            }
            if (val === 'SHIFT') {
                this.bulkModal.offdayMode = 'SINGLE';
                this.bulkModal.offdayRange = [];
                this.bulkModal.offdayStart = null;
            }
        },
        'bulkModal.offdayMode'() {
            this.bulkModal.offdayRange = [];
            this.bulkModal.offdayStart = null;
        },
        'bulkModal.jenis'(val) {
            if (val === 'PERMANEN') {
                this.bulkModal.dates = [];
                this.bulkModal.dateItems = [];
                this.bulkModal.memberDateItems = [];
                this.bulkModal.applyAllShiftId = null;
                this.updateBulkPermanentConflicts();
            } else {
                this.bulkModal.permanentDate = null;
                this.bulkModal.memberShiftItems = [];
                this.bulkModal.applyAllMemberShiftId = null;
                this.bulkPermanentConflictTotal = 0;
                this.bulkMemberModal = {
                    visible: false,
                    kode: null,
                    applyAllShiftId: null,
                };
            }
        },
        'bulkModal.dates': {
            handler() {
                if (this.bulkModal.jenis === 'SEMENTARA') {
                    this.bulkShiftOptionsByDate = {};
                    this.syncBulkDateItems();
                    this.syncBulkMemberDateItems();
                }
            },
            deep: true,
        },
        'bulkModal.permanentDate'(val) {
            if (this.bulkModal.jenis === 'PERMANEN') {
                this.bulkPermanentShiftOptions = [];
                this.bulkPermanentShiftOptionsLoaded = false;
                this.bulkPermanentShiftOptionsLoading = false;
                this.bulkModal.memberShiftItems = this.bulkModal.selectedMembers.map((kode) => ({
                    kode,
                    shiftId: null,
                }));
                if (val) {
                    this.updateBulkPermanentConflicts();
                }
            }
        },
        'bulkModal.selectedMembers': {
            handler() {
                this.bulkShiftOptionsByDate = {};
                this.bulkPermanentShiftOptions = [];
                this.bulkPermanentShiftOptionsLoaded = false;
                this.bulkPermanentShiftOptionsLoading = false;
                if (this.bulkModal.jenis === 'PERMANEN') {
                    this.bulkModal.memberShiftItems = this.bulkModal.selectedMembers.map((kode) => {
                        const existing = this.bulkModal.memberShiftItems.find((item) => item.kode === kode) || null;
                        return {
                            kode,
                            shiftId: existing?.shiftId || null,
                        };
                    });
                    this.updateBulkPermanentConflicts();
                }
                if (this.bulkModal.jenis === 'SEMENTARA') {
                    this.syncBulkDateItems();
                    this.syncBulkMemberDateItems();
                }
            },
            deep: true,
        },
    },
    mounted() {
        // console.log("=== DEBUG LEADER SHIFT ===");
        // console.log("initialStats:", this.initialStats);
        // console.log("approverInfo:", this.approverInfo);
        // console.log("config:", this.config);

        this.widgetStats = {
            team_count: this.initialStats?.team_count || 0,
            pending_count: this.initialStats?.pending_count || 0,
            approved_month: this.initialStats?.approved_month || 0,
        };

        this.initDateTime();
        this.startDate = this.getWeekStartDate(this.serverDate || dayjs());
        this.syncSelectedDate();
        this.loadWidgetStats();
        this.loadTeam().then(() => {
            this.generateWeekDays();
            this.loadSchedule();
        });
        this.loadShifts();

        this.debouncedLoadHistory = debounce(() => {
            this.historyPagination.page = 1;
            this.loadHistory();
        }, 500);

        // Window Resize Listener for Responsive ViewMode
        window.addEventListener('resize', this.handleResize);
        this.handleResize();
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },

    methods: {
        async loadWidgetStats() {
            try {
                const res = await axios.get(`/${this.routePrefix}/stats`);
                const result = res?.data?.result || {};
                this.widgetStats = {
                    team_count: Number(result.team_count || 0),
                    pending_count: Number(result.pending_count || 0),
                    approved_month: Number(result.approved_month || 0),
                };
            } catch (e) {
                console.error('Failed to load widget stats', e);
            }
        },
        getWeekStartDate(date) {
            const d = dayjs(date);
            const mondayOffset = (d.day() + 6) % 7;
            return d.subtract(mondayOffset, 'day').format('YYYY-MM-DD');
        },
        syncSelectedDate(date = this.startDate) {
            this.selectedDate = dayjs(date).format('YYYY-MM-DD');
        },
        setCalendarWeek(date) {
            const weekStart = this.getWeekStartDate(date);
            this.startDate = weekStart;
            this.syncSelectedDate(weekStart);
            this.generateWeekDays();
        },
        getBulkRefreshAnchorDate() {
            if (this.bulkModal.actionType === 'SHIFT') {
                if (this.bulkModal.jenis === 'PERMANEN' && this.bulkModal.permanentDate) {
                    return this.bulkModal.permanentDate;
                }

                if (this.bulkModal.dates?.length) {
                    return [...this.bulkModal.dates].sort()[0];
                }
            }

            if (this.bulkModal.actionType === 'OFFDAY') {
                if (this.bulkModal.offdayMode === 'RANGE') {
                    return this.bulkModal.offdayRange?.[0] || null;
                }

                if (this.bulkModal.offdayMode === 'PERMANEN') {
                    return this.bulkModal.offdayStart || null;
                }

                if (this.bulkModal.dates?.length) {
                    return [...this.bulkModal.dates].sort()[0];
                }
            }

            return null;
        },
        canSubmitBulk() {
            if (this.bulkModal.actionType === 'OFFDAY') {
                if (this.bulkModal.offdayMode === 'RANGE') {
                    return this.bulkModal.offdayRange?.length === 2 && this.bulkModal.selectedMembers.length > 0;
                }
                if (this.bulkModal.offdayMode === 'PERMANEN') {
                    return !!this.bulkModal.offdayStart && this.bulkModal.selectedMembers.length > 0;
                }
                return this.bulkModal.dates.length > 0 && this.bulkModal.selectedMembers.length > 0;
            }
            if (this.bulkModal.jenis === 'PERMANEN') {
                if (!this.bulkModal.permanentDate) return false;
                return this.bulkModal.memberShiftItems.every((item) => !!item.shiftId);
            }
            if (!this.bulkModal.memberDateItems.length) return false;
            return this.bulkModal.memberDateItems.every((member) => member.dates.every((item) => !!item.id_shift));

            // Alasan Harus Diisi
            if (!this.bulkModal.alasan.trim()) return false;
            return true;
        },
        truncateText(text, length) {
            if (text.length > length) {
                return text.substring(0, length) + '...';
            }
            return text;
        },
        debouncedSearch() {
            // Clear existing timer
            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }
            // Set new timer - call loadSchedule after 300ms delay
            this.searchTimer = setTimeout(() => {
                this.loadSchedule(true); // Reset to page 1 when searching
            }, 300);
        },
        initDateTime() {
            const now = new Date();
            const hour = now.getHours();
            this.dayNight = hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
            this.formattedDate = now.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile && this.viewMode === 'table') {
                this.viewMode = 'card';
            } else if (!this.isMobile && this.viewMode === 'card') {
                this.viewMode = 'table';
            }
        },
        handleTabChange(tab) {
            this.activeTab = tab;
            if (tab === 'riwayat' && this.historyItems.length === 0) this.loadHistory();
        },

        // --- Data Loading ---
        async loadTeam() {
            this.loading.team = true;
            try {
                const res = await axios.get(`/${this.routePrefix}/team`);
                this.team = res.data.result || [];
            } catch (e) {
                console.error(e);
            } finally {
                this.loading.team = false;
            }
        },
        async loadShifts() {
            if (this.availableShifts.length) return;
            try {
                const res = await axios.get('/leader-shift/shifts');
                this.availableShifts = res.data.result || [];

                // Populate Modal Options with proper value field for ElSelect
                this.shiftOptions = this.availableShifts.map((shift) => ({
                    ...shift,
                    id: shift.ID_Shift || shift.id,
                    label: shift.Nama || shift.nama,
                    value: shift.ID_Shift || shift.id,
                }));
            } catch (e) {
                console.error(e);
            }
        },

        // --- Standardized Quick Modal ---
        async openQuickModal(member, day) {
            // console.log("openQuickModal called", { member, day });
            if (!member || !day) return;

            const current = this.getShift(member.Kode_Karyawan, day.date);
            const statusHari = current?.status_hari || null;
            const statusHariLabel = current?.status_hari_label || null;
            const currentShift = current?.shift || null;
            const currentShiftId = currentShift?.ID_Shift || currentShift?.id || null;

            this.quickModalData = {
                date: day.date,
                currentShift: currentShift,
                currentShiftId: currentShiftId,
                newShiftId: currentShiftId,
                makePermanent: false,
                overwriteSementara: false,
                alasan: '',
                member: member,
                statusHari: statusHari,
                statusHariLabel: statusHariLabel,
                isDayOff: current?.is_day_off || false,
                offdayMode: 'SINGLE',
                offdayStart: day.date,
                offdayEnd: day.date,
                offdayRange: [day.date, day.date],
                isPending: current?.is_pending_shift || current?.pending_status_hari || false,
                pendingNoTransaksi: current?.pending_no_transaksi || null,
                pendingRequesterKode: current?.pending_requester_kode || null,
                pendingRequesterNama: current?.pending_requester_nama || null,
                pendingRequesterJabatan: current?.pending_requester_jabatan || null,
            };
            this.quickModalPermanentConflictCount = 0;

            this.showQuickModal = true;
            this.loading.quickModal = true;
            this.quickModalData.loadingOverride = true;
            this.quickModalData.activeOverride = null;
            try {
                await Promise.all([
                    this.getShiftForDay(member.Kode_Karyawan, day.date),
                    // this.getActiveOverrideForDate(member.Kode_Karyawan, day.date)
                ]);
            } catch (error) {
                console.error('Error in openQuickModal:', error);
                ElMessage.error('Terjadi kesalahan saat membuka modal');
            } finally {
                this.loading.quickModal = false;
                this.quickModalData.loadingOverride = false;
            }
            // console.log("Quick Modal Data:", this.quickModalData);
        },

        async getActiveOverrideForDate(memberKode, date) {
            try {
                const response = await axios.get(`/${this.routePrefix}/override-for-date`, {
                    params: { member: memberKode, tanggal: date },
                });

                if (response.data.status === 'success' && response.data.result.has_override) {
                    this.quickModalData.activeOverride = response.data.result.active_override;
                } else {
                    this.quickModalData.activeOverride = null;
                }
            } catch (error) {
                console.error('Failed to fetch active override:', error);
                this.quickModalData.activeOverride = null;
            }
        },

        async stopActiveOverride() {
            if (!this.quickModalData.activeOverride) return;

            const confirm = await ElMessageBox.confirm(
                `Anda yakin ingin mengakhiri override "${this.quickModalData.activeOverride.keterangan || this.quickModalData.activeOverride.status_hari}" mulai tanggal ${this.formatDate(this.quickModalData.date)}?`,
                'Konfirmasi Akhiri Override',
                {
                    confirmButtonText: 'Ya, Akhiri',
                    cancelButtonText: 'Batal',
                    type: 'warning',
                },
            ).catch(() => false);

            if (!confirm) return;

            this.quickModalData.loadingOverride = true;
            try {
                const response = await axios.post(`/${this.routePrefix}/stop-override`, {
                    id: this.quickModalData.activeOverride.id,
                    stop_date: this.quickModalData.date,
                });

                if (response.data.status === 'success') {
                    ElMessage.success('Override berhasil diakhiri');
                    this.showQuickModal = false;
                    this.fetchData(); // Refresh UI
                } else {
                    throw new Error(response.data.message || 'Gagal mengakhiri override');
                }
            } catch (error) {
                console.error('Error stopping override:', error);
                ElMessage.error(error?.response?.data?.message || error.message || 'Gagal mengakhiri override');
            } finally {
                this.quickModalData.loadingOverride = false;
            }
        },

        async getShiftForDay(memberKode, date, all = false) {
            try {
                const cacheKey = `${memberKode || 'ALL_MEMBER'}:${all ? 'ALL' : date}`;
                if (this.shiftOptionsByDay[cacheKey]) {
                    this.shiftOptions = this.shiftOptionsByDay[cacheKey];
                    this.syncQuickModalShiftSelection();
                    return { shifts: this.shiftOptions };
                }

                this.loading.shifts = true;
                const response = await axios.get('/leader-shift/shifts', {
                    params: all ? { member: memberKode } : { tanggal: date, member: memberKode },
                });

                const shifts = response.data.result || response.data || [];
                this.shiftOptions = shifts.map((shift) => ({
                    ...shift,
                    id: shift.ID_Shift || shift.id,
                    label: shift.Nama || shift.nama,
                    value: shift.ID_Shift || shift.id,
                }));
                this.syncQuickModalShiftSelection();

                this.shiftOptionsByDay[cacheKey] = this.shiftOptions;
                if (!this.shiftOptions.length && memberKode && date) {
                    const nama = this.getMemberNameByKode(memberKode) || memberKode;
                    ElMessage.info(
                        `Tidak ada shift tersedia untuk ${nama} pada ${this.formatDateShort(date)} sesuai rule shifting.`,
                    );
                }

                return { shifts };
            } catch (error) {
                console.error(error);
                ElMessage.error('Gagal mengambil data shift');
                return { shifts: [] };
            } finally {
                this.loading.shifts = false;
            }
        },
        syncQuickModalShiftSelection() {
            const selectedShiftId = this.quickModalData?.newShiftId;
            if (!selectedShiftId) return;

            const existsInOptions = this.shiftOptions.some(
                (shift) =>
                    String(shift.value) === String(selectedShiftId) ||
                    String(shift.id) === String(selectedShiftId) ||
                    String(shift.ID_Shift) === String(selectedShiftId),
            );

            if (!existsInOptions) {
                this.quickModalData.newShiftId = null;
            }
        },
        checkSubmitQuickModal() {
            const data = this.quickModalData;

            // ===============================
            // VALIDASI UMUM
            // ===============================
            // ❌ Tanggal harus dipilih (kecuali MODE RANGE/PERMANEN)
            if (!data.date && data.offdayMode !== 'RANGE' && data.offdayMode !== 'PERMANEN') {
                ElMessage.warning('Tanggal harus dipilih.');
                return false;
            }

            // ❌ Karyawan harus dipilih
            if (!data.member || !data.member.Kode_Karyawan) {
                ElMessage.warning('Data karyawan tidak valid.');
                return false;
            }

            // ❌ Pengajuan sedang pending approval
            if (data.isPending) {
                ElMessage.warning('Terdapat pengajuan yang masih menunggu approval untuk tanggal ini.');
                return false;
            }

            // ===============================
            // MODE OFFDAY
            // ===============================
            if (data.isDayOff) {
                // ❌ Hari Libur Nasional
                if (this.isLiburNasional(data.statusHari, data.statusHariLabel)) {
                    ElMessage.warning('Tanggal tersebut adalah hari libur nasional.');
                    return false;
                }

                // ❌ Sudah OFF sebelumnya
                if (data.statusHari && data.statusHari.startsWith('OFF')) {
                    ElMessage.warning('Hari tersebut sudah berstatus OFF.');
                    return false;
                }

                // ❌ Alasan kosong
                if (!data.alasan || data.alasan.trim() === '') {
                    ElMessage.warning('Alasan OFF wajib diisi.');
                    return false;
                }

                // ❌ Alasan terlalu pendek
                if (data.alasan.trim().length < 5) {
                    ElMessage.warning('Alasan OFF minimal 5 karakter.');
                    return false;
                }

                // ===============================
                // MODE RANGE
                // ===============================
                if (data.offdayMode === 'RANGE') {
                    if (!data.offdayRange || data.offdayRange.length !== 2) {
                        ElMessage.warning('Silakan pilih rentang tanggal OFF.');
                        return false;
                    }
                    // ❌ Tanggal akhir sebelum tanggal awal
                    if (dayjs(data.offdayRange[1]).isBefore(dayjs(data.offdayRange[0]), 'day')) {
                        ElMessage.warning('Tanggal akhir tidak boleh sebelum tanggal awal.');
                        return false;
                    }
                    return true;
                }

                // ===============================
                // MODE PERMANEN
                // ===============================
                if (data.offdayMode === 'PERMANEN') {
                    if (!(data.offdayStart || data.date)) {
                        ElMessage.warning('Tanggal mulai OFF permanen harus dipilih.');
                        return false;
                    }
                    return true;
                }

                // ===============================
                // MODE SINGLE DATE
                // ===============================
                if (!data.date) {
                    ElMessage.warning('Tanggal OFF harus dipilih.');
                    return false;
                }

                return true;
            }

            // ===============================
            // MODE GANTI SHIFT
            // ===============================
            // ❌ Shift pengganti harus dipilih
            if (!this.loading.shifts && this.shiftOptions.length === 0) {
                ElMessage.warning(
                    'Tidak ada pilihan shift untuk karyawan/tanggal ini (sesuai rule). Pilih tanggal lain atau hubungi admin HR/Shift Rule.',
                );
                return false;
            }

            if (!data.newShiftId) {
                ElMessage.warning('Shift pengganti harus dipilih.');
                return false;
            }

            // ❌ Shift yang existing dan yang dipilih sama
            if (data.currentShiftId && data.newShiftId === data.currentShiftId) {
                ElMessage.warning('Shift yang dipilih sama dengan shift saat ini.');
                return false;
            }

            // ❌ Alasan wajib hanya untuk non-admin
            if (!this.isAdmin) {
                if (!data.alasan || data.alasan.trim() === '') {
                    ElMessage.warning('Alasan perubahan shift wajib diisi.');
                    return false;
                }

                if (data.alasan.trim().length < 5) {
                    ElMessage.warning('Alasan perubahan shift minimal 5 karakter.');
                    return false;
                }
            }

            return true;
        },

        async submitQuickModal() {
            // Validasi input
            if (!this.checkSubmitQuickModal()) {
                return;
            }

            // Prepare konfirmasi message
            let confirmMessage = '';
            const memberName = this.quickModalData.member?.Nama || 'Karyawan';
            const tanggal = this.formatDate(this.quickModalData.date);

            if (this.quickModalData.isDayOff) {
                // Mode Day Off
                const mode = this.quickModalData.offdayMode;
                this.confirmModal.title = `Atur Day Off untuk ${memberName}?`;

                if (mode === 'RANGE') {
                    const start = this.formatDate(this.quickModalData.offdayRange[0]);
                    const end = this.formatDate(this.quickModalData.offdayRange[1]);
                    confirmMessage = `Periode: ${start} sampai ${end}`;
                } else if (mode === 'PERMANEN') {
                    const start = this.formatDate(this.quickModalData.offdayStart || this.quickModalData.date);
                    confirmMessage = `Mulai: ${start} (Permanen)`;
                } else {
                    confirmMessage = `Tanggal: ${tanggal}`;
                }

                this.confirmModal.details = null;
            } else {
                // Mode Ganti Shift
                const currentShift = this.quickModalCurrentShift;
                const newShift = this.getShiftById(this.quickModalData.newShiftId);
                const currentShiftName = this.getShiftDisplayName(currentShift) || '-';
                const newShiftName = this.getShiftDisplayName(newShift) || '-';
                const currentShiftTime = this.getShiftDisplayTimes(currentShift) || '-';
                const newShiftTime = this.getShiftDisplayTimes(newShift) || '-';
                const jenis = this.quickModalData.makePermanent ? 'PERMANEN' : 'SEMENTARA';

                // this.confirmModal.title = `Ubah shift ${memberName}?`;
                // this.confirmModal.title = `Ubah shift?`;
                confirmMessage = `Tanggal: ${tanggal}  ${jenis == 'PERMANEN' && this.quickModalData.overwriteSementara ? ' | Akan menonaktifkan shift sementara setelahnya' : ''} `;
                this.confirmModal.details = {
                    from: {
                        name: currentShiftName,
                        time: currentShiftTime,
                    },
                    to: {
                        name: newShiftName,
                        time: newShiftTime,
                    },
                    jenis: jenis,
                };
            }

            // Tampilkan confirmation modal
            this.confirmModal.message = confirmMessage;
            this.confirmModal.action = 'quick';
            this.confirmModal.visible = true;
        },

        async executeQuickSubmit() {
            this.loading.submit = true;
            try {
                // if (
                //     this.quickModalData.makePermanent &&
                //     this.quickModalPermanentConflictCount > 0
                // ) {
                //     const confirmed = await this.confirmPermanentOverride(
                //         `Ada ${this.quickModalPermanentConflictCount} shift sementara aktif yang akan dinonaktifkan. Lanjutkan?`,
                //     );
                //     if (!confirmed) {
                //         this.loading.submit = false;
                //         return;
                //     }
                // }
                if (this.quickModalData.isDayOff) {
                    await this.submitQuickOffday();
                    ElMessage.success('Offday berhasil disimpan');
                } else {
                    await axios.post(`/${this.routePrefix}/submit`, {
                        members: [
                            {
                                kode: this.quickModalData.member.Kode_Karyawan,
                                id_shift: this.quickModalData.newShiftId,
                                dates: [
                                    {
                                        tanggal: this.quickModalData.date,
                                        id_shift: this.quickModalData.newShiftId,
                                    },
                                ],
                            },
                        ],
                        dates: [
                            {
                                tanggal: this.quickModalData.date,
                            },
                        ],
                        jenis: this.quickModalData.makePermanent ? 'PERMANEN' : 'SEMENTARA',
                        alasan: this.quickModalData.alasan,
                        overwrite_sementara: this.quickModalData.makePermanent
                            ? this.quickModalData.overwriteSementara
                            : false,
                    });
                    ElMessage.success('Shift berhasil diupdate');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal update shift');
                throw e; // Re-throw to let handleConfirmSubmit catch it
            } finally {
                // Close quick modal after submission (success or error)
                this.showQuickModal = false;
                this.loading.submit = false;
            }

            // Reload data after successful submission (outside try-catch finally)
            this.loadSchedule();
            this.loadWidgetStats();
        },
        async submitQuickOffday() {
            const mode = this.quickModalData.offdayMode;
            const payload = {
                members: [this.quickModalData.member.Kode_Karyawan],
                mode: mode,
                alasan: this.quickModalData.alasan,
            };

            if (mode === 'SINGLE') {
                payload.dates = [this.quickModalData.date];
            } else if (mode === 'RANGE') {
                payload.start_date = this.quickModalData.offdayRange?.[0] || null;
                payload.end_date = this.quickModalData.offdayRange?.[1] || null;
            } else {
                payload.start_date =
                    this.quickModalData.offdayStart || this.quickModalData.date || dayjs().format('YYYY-MM-DD');
            }

            await axios.post(`/${this.routePrefix}/offday`, payload);
        },
        // --- Helpers ---
        getShiftById(shiftId) {
            if (!shiftId) return null;
            const target = String(shiftId);
            return (
                this.shiftOptions.find(
                    (shift) =>
                        String(shift.value) === target ||
                        String(shift.id) === target ||
                        String(shift.ID_Shift) === target,
                ) || null
            );
        },
        getShiftDisplayName(shift) {
            if (!shift) return '-';
            return shift.Nama || shift.nama || '-';
        },
        getShiftDisplayTimes(shift) {
            if (!shift) return '-';
            const inTime = this.formatTime(shift.Jam_Masuk || shift.jam_masuk);
            const outTime = this.formatTime(
                shift.Jam_Pulang || shift.Jam_Keluar || shift.jam_keluar || shift.jam_pulang,
            );
            if (!inTime && !outTime) return '-';
            return `${inTime || '-'} - ${outTime || '-'}`;
        },
        getShiftLabel(shift) {
            if (!shift) return '-';
            // Handle both object structure styles if needed
            const name = shift.Nama || shift.nama;
            const inTime = this.formatTime(shift.Jam_Masuk || shift.jam_masuk);
            const outTime = this.formatTime(
                shift.Jam_Pulang || shift.Jam_Keluar || shift.jam_keluar || shift.jam_pulang,
            );
            return `${name} (${inTime} - ${outTime})`;
        },
        getShiftSourceClass(source) {
            if (!source) return '';
            const normalized = String(source).toUpperCase();
            if (normalized === 'PERMANEN') return 'source-permanen';
            if (normalized === 'SEMENTARA') return 'source-sementara';
            return '';
        },
        getShiftSourceLabel(source) {
            if (!source) return '';
            const normalized = String(source).toUpperCase();
            if (normalized === 'PERMANEN') return 'Permanen';
            if (normalized === 'SEMENTARA') return 'Sementara';
            return String(source);
        },
        normalizeBooleanFlag(value) {
            if (value === true || value === 1) return true;
            if (value === false || value === 0 || value == null) return false;
            const normalized = String(value).trim().toUpperCase();
            return ['Y', 'YES', 'TRUE', '1'].includes(normalized);
        },
        memberHasPermanentShift(member) {
            if (!member) return false;

            const targetTable = String(member?.Target_Table || '').toUpperCase();
            if (targetTable === 'PERMANEN') return true;

            const directFlags = [
                member?.has_permanent_shift,
                member?.Has_Permanent_Shift,
                member?.is_permanent,
                member?.Is_Permanent,
            ];
            if (directFlags.some((flag) => this.normalizeBooleanFlag(flag))) {
                return true;
            }

            const memberKode = member?.Kode_Karyawan;
            if (!memberKode) return false;

            const schedule = this.weekSchedule?.[memberKode] || {};
            return Object.values(schedule).some((dayItem) => {
                const source = String(dayItem?.source || '').toUpperCase();
                return source === 'PERMANEN';
            });
        },
        getShiftName(shiftId) {
            const s = this.availableShifts.find((x) => x.ID_Shift === shiftId || x.id === shiftId);
            return s ? s.Nama || s.nama : '-';
        },
        isBackdate(dateStr) {
            return dayjs(dateStr).isBefore(dayjs(), 'day');
        },
        isSameDay(dateStr) {
            return dayjs(dateStr).isSame(dayjs(), 'day');
        },
        getJenisPengajuan(dateStr) {
            if (this.isBackdate(dateStr)) return 'BACKDATE';
            if (this.isSameDay(dateStr)) return 'SAME_DAY';
            return 'FUTURE';
        },
        checkNeedsApproval(jenis) {
            // Use config from backend props
            if (jenis === 'BACKDATE') return !!this.config?.backdate_needs_approval;
            if (jenis === 'SAME_DAY') return !!this.config?.same_day_needs_approval;
            if (jenis === 'FUTURE') return !!this.config?.future_needs_approval;
            return true; // Default: needs approval
        },
        canSubmitQuickModal() {
            // Pure function - NO SIDE EFFECTS (no ElMessage)
            // This is called in :disabled binding which is reactive

            if (this.quickModalData.isDayOff) {
                // Hari Libur Nasional
                if (this.isLiburNasional(this.quickModalData.statusHari, this.quickModalData.statusHariLabel)) {
                    return false;
                }

                // Sudah OFF sebelumnya
                if (this.quickModalData.statusHari && this.quickModalData.statusHari.startsWith('OFF')) {
                    return false;
                }

                // Alasan kosong
                if (!this.quickModalData.alasan || this.quickModalData.alasan.trim() === '') {
                    return false;
                }

                // Validasi per mode
                if (this.quickModalData.offdayMode === 'RANGE') {
                    return this.quickModalData.offdayRange?.length === 2;
                }

                if (this.quickModalData.offdayMode === 'PERMANEN') {
                    return !!(this.quickModalData.offdayStart || this.quickModalData.date);
                }

                return !!this.quickModalData.date;
            }

            // MODE GANTI SHIFT
            // Shift pengganti harus dipilih
            if (!this.quickModalData.newShiftId) {
                return false;
            }

            // Shift yang existing dan yang dipilih sama
            if (
                this.quickModalData.currentShiftId &&
                this.quickModalData.newShiftId === this.quickModalData.currentShiftId
            ) {
                return false;
            }

            // Alasan kosong untuk ganti shift
            if (!this.quickModalData.alasan || this.quickModalData.alasan.trim() === '') {
                return false;
            }

            return true;
        },
        formatDate(dateStr) {
            return dayjs(dateStr).format('DD MMM YYYY');
        },
        getDayName(dateStr) {
            return dayjs(dateStr).format('dddd');
        },
        getDateNumber(dateStr) {
            return dayjs(dateStr).format('D');
        },
        getDetailTransactionTypeLabel(data) {
            if (data.transaction_type === 'TUKAR_HARI') return 'Tukar Hari';
            if (data.transaction_type === 'STATUS_HARI') {
                const hasPermanentMember = (data.members || []).some((member) => this.isMemberPermanent(member));
                return hasPermanentMember ? 'Status Hari Permanen' : 'Status Hari';
            }
            if (data.transaction_type === 'SHIFT') {
                const target = data.members && data.members[0] ? data.members[0].Target_Table : '';
                if (target === 'PERMANEN') return 'Shift Permanen';
                if (target === 'SEMENTARA') return 'Shift Sementara';
                return 'Shift';
            }
            return 'Pengajuan';
        },
        getDetailMembersSectionTitle() {
            const data = this.historyDetailModal?.data;
            if (!data) return 'Daftar Karyawan';
            if (data.transaction_type === 'STATUS_HARI') {
                const hasPermanentMember = (data.members || []).some((member) => this.isMemberPermanent(member));
                return hasPermanentMember
                    ? 'Detail Status Hari Permanen per Anggota'
                    : 'Detail Status Hari per Anggota';
            }
            if (data.transaction_type === 'SHIFT') {
                const hasPermanentMember = (data.members || []).some((member) => this.isMemberPermanent(member));
                return hasPermanentMember ? 'Detail Shift Permanen per Anggota' : 'Detail Shift per Anggota';
            }
            return 'Daftar Karyawan';
        },
        isMemberPermanent(member) {
            const target = member?.Target_Table || '';
            return target.toUpperCase() === 'PERMANEN';
        },
        isHistoryDetailOverwriteSementara() {
            const flag = this.historyDetailModal?.data?.header?.Flag_Overwrite_Sementara;
            return flag === 'Y';
        },
        getMemberPermanentStartDate(member) {
            if (!member?.dates || member.dates.length < 1) return null;

            const sorted = [...member.dates].sort(
                (a, b) => new Date(a?.Tanggal_Shift || a?.Tanggal || 0) - new Date(b?.Tanggal_Shift || b?.Tanggal || 0),
            );

            return sorted[0]?.Tanggal_Shift || sorted[0]?.Tanggal || null;
        },
        getMemberDateDisplayLabel(member, date) {
            const tanggal = date?.Tanggal || date?.Tanggal_Shift;
            if (!tanggal) return '-';
            if (
                ['SHIFT', 'STATUS_HARI'].includes(this.historyDetailModal?.data?.transaction_type) &&
                this.isMemberPermanent(member)
            ) {
                return `Mulai Berlaku: ${this.formatDate(tanggal)}`;
            }
            return this.formatDate(tanggal);
        },
        getAggregateMemberStatusClass(member) {
            if (member?.Status === 'Y') return 'bg-secondary-subtle text-secondary border-secondary-subtle';
            if (!member.dates || member.dates.length === 0) return 'bg-light text-muted';

            // Filter out canceled dates (Status="Y")
            const activeDates = member.dates.filter((d) => d.Status !== 'Y');

            // If no active dates remain after filtering, treat as "All Approved"
            if (activeDates.length === 0) return 'bg-success-subtle text-success border-success-subtle';

            const allApproved = activeDates.every((d) => d.Flag_Approval === 'Y');
            const anyRejected = activeDates.some((d) => d.Flag_Approval === 'T');
            const allRejected = activeDates.every((d) => d.Flag_Approval === 'T');

            if (allApproved) return 'bg-success-subtle text-success border-success-subtle';
            if (allRejected) return 'bg-danger-subtle text-danger border-danger-subtle';
            if (anyRejected) return 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
            return 'bg-secondary-subtle text-secondary border-secondary-subtle';
        },
        getAggregateMemberStatusLabel(member) {
            if (member?.Status === 'Y') return 'Canceled';
            if (!member.dates || member.dates.length === 0) return 'No Data';

            // Filter out canceled dates (Status="Y")
            const activeDates = member.dates.filter((d) => d.Status !== 'Y');

            // If no active dates remain after filtering, treat as "All Approved"
            if (activeDates.length === 0) return 'All Approved';

            const allApproved = activeDates.every((d) => d.Flag_Approval === 'Y');
            const anyRejected = activeDates.some((d) => d.Flag_Approval === 'T');
            const allRejected = activeDates.every((d) => d.Flag_Approval === 'T');

            if (allApproved) return 'All Approved';
            if (allRejected) return 'All Rejected';
            if (anyRejected) return 'Partial / Rejected';
            return 'Pending Review';
        },

        // --- Week & Grid Logic ---
        // generateWeekDays() {
        //     // Mulai dari tanggal yang dipilih (this.startDate), bukan selalu Senin
        //     const start = dayjs(this.startDate);
        //     this.weekDays = Array.from({ length: 7 }, (_, i) => {
        //         const d = start.add(i, "day");
        //         return {
        //             date: d.format("YYYY-MM-DD"),
        //             day: d.format("D"),
        //             name: d.format("ddd"),
        //             month: d.format("MMM"),
        //             year: d.format("YYYY"),
        //             isToday:
        //                 d.format("YYYY-MM-DD") === dayjs().format("YYYY-MM-DD"),
        //         };
        //     });
        //     // Tidak perlu update this.startDate ke Senin, biarkan sesuai tanggal yang dipilih
        // },
        generateWeekDays() {
            const selected = dayjs(this.startDate);

            // day(): 0=Sunday, 1=Monday, ..., 6=Saturday
            const dayIndex = selected.day();

            // Hitung mundur ke Senin (ISO style)
            const monday =
                dayIndex === 0
                    ? selected.subtract(6, 'day') // kalau Minggu
                    : selected.subtract(dayIndex - 1, 'day');

            this.weekDays = Array.from({ length: 7 }, (_, i) => {
                const d = monday.add(i, 'day');

                return {
                    date: d.format('YYYY-MM-DD'),
                    day: d.format('D'),
                    name: d.format('ddd'),
                    month: d.format('MMM'),
                    year: d.format('YYYY'),
                    isToday: d.format('YYYY-MM-DD') === dayjs().format('YYYY-MM-DD'),
                };
            });
        },
        onWeekChange() {
            this.pickerVisible = false;
            this.generateWeekDays();
            this.loadSchedule(true); // Reset page on week change
        },
        changeWeek() {
            // Saat user pilih tanggal dari picker, update startDate ke tanggal yang dipilih
            if (this.selectedDate) {
                this.startDate = dayjs(this.selectedDate).format('YYYY-MM-DD');
            }
            this.onWeekChange();
        },
        prevWeek() {
            // Geser mundur 1 minggu, tetap ke hari Senin
            const prev = dayjs(this.startDate).subtract(1, 'week');
            this.setCalendarWeek(prev);
            this.loadSchedule(true); // Reset page on week change
        },
        nextWeek() {
            // Geser maju 1 minggu, tetap ke hari Senin
            const next = dayjs(this.startDate).add(1, 'week');
            this.setCalendarWeek(next);
            this.loadSchedule(true); // Reset page on week change
        },
        // Schedule pagination methods
        prevSchedulePage() {
            if (this.schedulePagination.page > 1) {
                this.schedulePagination.page--;
                this.loadSchedule();
            }
        },
        nextSchedulePage() {
            if (this.schedulePagination.page < this.totalSchedulePages) {
                this.schedulePagination.page++;
                this.loadSchedule();
            }
        },
        async loadScheduleForMembers(memberKodes) {
            if (!memberKodes || memberKodes.length === 0) return;

            try {
                const start = this.weekDays[0]?.date || this.startDate;
                const res = await axios.get(`/${this.routePrefix}/week-schedule`, {
                    params: {
                        start_date: start,
                        days: 7,
                        members: memberKodes, // Pass specific members
                        page: 1,
                        limit: 100, // High limit to ensure all members are loaded
                    },
                });
                // Merge with existing weekSchedule to preserve other members' data
                const newSchedules = res.data.result.schedules || {};
                const newSummary = res.data.result.work_summary || {};
                this.weekSchedule = {
                    ...this.weekSchedule,
                    ...newSchedules, // Overwrite with fresh data for requested members
                };
                this.weeklyHourSummary = {
                    ...this.weeklyHourSummary,
                    ...newSummary,
                };
            } catch (e) {
                console.error('Error loading specific members schedule:', e);
            }
        },
        goToSchedulePage(page) {
            if (page >= 1 && page <= this.totalSchedulePages) {
                this.schedulePagination.page = page;
                this.loadSchedule();
            }
        },
        async loadSchedule(resetPage = false) {
            if (resetPage) {
                this.schedulePagination.page = 1;
            }
            this.loading.schedule = true;
            try {
                const start = this.weekDays[0]?.date || this.startDate;
                const res = await axios.get(`/${this.routePrefix}/week-schedule`, {
                    params: {
                        start_date: start,
                        days: 7,
                        page: this.schedulePagination.page,
                        limit: this.schedulePagination.limit,
                        search: this.searchMember || undefined,
                    },
                });
                this.weekSchedule = res.data.result.schedules || {};
                this.weeklyHourSummary = res.data.result.work_summary || {};
                // Store members from API response
                this.paginatedMembersData = res.data.result.members || [];

                const missingPermanentMembers = this.paginatedMembersData.filter(
                    (member) => member?.has_permanent_shift === false,
                );
                if (missingPermanentMembers.length > 0) {
                    const alertKey = `${this.schedulePagination.page}:${missingPermanentMembers
                        .map((member) => member.Kode_Karyawan)
                        .join(',')}`;
                    if (this.lastMissingPermanentAlertKey !== alertKey) {
                        const previewNames = missingPermanentMembers
                            .slice(0, 3)
                            .map((member) => member.Nama)
                            .join(', ');
                        const remainingCount =
                            missingPermanentMembers.length > 3 ? ` +${missingPermanentMembers.length - 3} lainnya` : '';

                        ElMessage.warning(
                            `Ada ${missingPermanentMembers.length} karyawan tanpa shift permanen aktif (HRIS_Shift_Per_Karyawan, Periode <= hari ini): ${previewNames}${remainingCount}`,
                        );
                        this.lastMissingPermanentAlertKey = alertKey;
                    }
                }
                // Update pagination from response
                if (res.data.pagination) {
                    this.schedulePagination.total = res.data.pagination.total || 0;
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading.schedule = false;
            }
        },
        getShift(memberKode, date) {
            return this.weekSchedule[memberKode]?.[date] || null;
        },
        getQuickPendingRequesterLabel() {
            const requesterKode = this.quickModalData?.pendingRequesterKode;
            if (!requesterKode) return '';
            if (this.loginKaryawanKode && String(requesterKode) === String(this.loginKaryawanKode)) {
                return '';
            }

            const requesterNama = this.quickModalData?.pendingRequesterNama || requesterKode;
            const requesterJabatan = this.quickModalData?.pendingRequesterJabatan || '';

            return requesterJabatan ? `${requesterNama} (${requesterJabatan})` : `${requesterNama}`;
        },
        getWeeklySummary(memberKode) {
            return (
                this.weeklyHourSummary?.[memberKode] || {
                    total_hours: 0,
                    target_hours: 0,
                    delta_hours: 0,
                    status: 'MEET',
                }
            );
        },
        getWeeklyHours(memberKode) {
            return Number(this.getWeeklySummary(memberKode).total_hours || 0);
        },
        getWeeklyTargetHours(memberKode) {
            return Number(this.getWeeklySummary(memberKode).target_hours ?? 0);
        },
        shouldShowWeeklyHoursBadge(memberKode) {
            return this.getWeeklyTargetHours(memberKode) > 0;
        },
        getWeeklyHoursBadgeClass(memberKode) {
            const status = this.getWeeklySummary(memberKode).status;
            if (status === 'OVER') {
                return 'bg-primary-subtle text-primary';
            }
            if (status === 'MEET') {
                return 'bg-success-subtle text-success-emphasis';
            }
            return 'bg-danger-subtle text-danger';
        },
        formatWeeklyHours(memberKode) {
            return this.getWeeklyHours(memberKode).toFixed(1);
        },
        getWeeklyOvertimeHours(memberKode) {
            const schedule = this.weekSchedule[memberKode] || {};
            let totalOvertime = 0;
            this.weekDays.forEach((day) => {
                const item = schedule[day.date];
                if (item?.durasi_lembur) {
                    totalOvertime += Number(item.durasi_lembur);
                }
            });
            return totalOvertime;
        },
        formatWeeklyHoursWithOvertime(memberKode) {
            const hours = this.formatWeeklyHours(memberKode);
            const target = this.getWeeklyTargetHours(memberKode);

            if (target <= 0) {
                return '';
            }

            const overtime = this.getWeeklyOvertimeHours(memberKode);

            if (overtime > 0) {
                return `(${hours}/${target} JAM + ${overtime.toFixed(1)} Jam)`;
            }
            return `(${hours}/${target} JAM)`;
        },
        getWorkdayCount(memberKode) {
            const schedule = this.weekSchedule[memberKode] || {};
            let count = 0;
            this.weekDays.forEach((day) => {
                const item = schedule[day.date];
                if (item?.status_hari?.startsWith('WORK')) {
                    count += 1;
                }
            });
            return count;
        },
        getShiftClass(shiftName) {
            if (!shiftName) return 'shift-noshift';
            const n = shiftName.toLowerCase();

            // Match userShift colors
            if (n.includes('off') || n.includes('libur') || n.includes('noshift')) return 'shift-noshift';
            if (n.includes('pagi') || n.endsWith('p') || n.endsWith('1')) return 'shift-morning';
            if (n.includes('siang') || n.endsWith('2')) return 'shift-afternoon';
            if (n.includes('malam') || n.endsWith('m') || n.endsWith('3')) return 'shift-night';

            return 'shift-afternoon'; // Default fallback
        },
        getStatusHariLabel(statusHari, statusHariLabel = null) {
            if (statusHariLabel) return statusHariLabel;
            if (!statusHari) return '';
            if (statusHari === 'OFF') return 'OFF';
            if (statusHari === 'WORK') return 'WORK';
            return statusHari;
        },
        getStatusHariMainLabel(statusHari) {
            if (!statusHari) return '';
            if (statusHari === 'OFF') return 'OFF';
            if (statusHari === 'WORK') return 'WORK';
            return statusHari;
        },
        getStatusHariSubLabel(statusHari, statusHariLabel = null) {
            if (!statusHari) return '';
            if (statusHariLabel) return statusHariLabel;
            return '';
        },
        getTransactionTypeLabel(type) {
            if (type === 'SHIFT') return 'Tukar Shift';
            if (type === 'STATUS_HARI') return 'Offday / Libur';
            return type || 'Pengajuan';
        },
        getTransactionTypeClass(type) {
            if (type === 'SHIFT') return 'bg-primary-subtle text-primary';
            if (type === 'STATUS_HARI') return 'bg-warning-subtle text-warning';
            return 'bg-secondary-subtle text-secondary';
        },
        getStatusHariBadgeClass(statusHari) {
            if (!statusHari) return 'badge bg-light text-muted';
            if (statusHari.startsWith('OFF')) return 'badge bg-danger-subtle text-danger';
            return 'badge bg-success-subtle text-success';
        },
        isLiburNasional(statusHari, statusHariLabel = null) {
            if (!statusHariLabel) return false;
            return statusHariLabel.toLowerCase().includes('libur nasional');
        },
        formatTime(time) {
            if (!time) return '';
            return time.substring(0, 5);
        },
        isLongshift(shift) {
            if (!shift) return false;
            return shift.flag_longshift === 'Y' && Number(shift.durasi_lembur || 0) > 0;
        },
        formatLongshiftDuration(durasi) {
            const num = Number(durasi || 0);
            if (num <= 0) return '';
            return num.toFixed(1);
        },
        toggleMemberCard(kode) {
            const idx = this.expandedMembers.indexOf(kode);
            if (idx === -1) this.expandedMembers.push(kode);
            else this.expandedMembers.splice(idx, 1);
        },

        // --- Quick Edit (Legacy Methods Removed) ---
        // New standard 'openQuickModal' and 'submitQuickModal' are used instead.

        // --- Bulk Assign ---
        openBulkModal() {
            this.bulkModal = {
                visible: true,
                step: 1,
                search: '',
                selectedMembers: [],
                dates: [],
                permanentDate: null,
                dateItems: [],
                memberDateItems: [],
                memberShiftItems: [],
                applyAllShiftId: null,
                applyAllMemberShiftId: null,
                jenis: 'SEMENTARA',
                alasan: '',
                actionType: null,
                offdayMode: 'SINGLE',
                offdayRange: [],
                offdayStart: null,
            };
            this.bulkMemberModal = {
                visible: false,
                kode: null,
                applyAllShiftId: null,
            };
            this.bulkShiftOptionsByDate = {};
            this.bulkShiftOptionsLoading = {};
            this.bulkPermanentShiftOptions = [];
            this.bulkPermanentShiftOptionsLoaded = false;
            this.bulkPermanentShiftOptionsLoading = false;
            this.bulkOverwriteSementara = false;
        },
        closeBulkModal() {
            this.bulkModal.visible = false;
            this.closeBulkMemberModal();
        },
        toggleBulkMember(kode) {
            const idx = this.bulkModal.selectedMembers.indexOf(kode);
            if (idx > -1) this.bulkModal.selectedMembers.splice(idx, 1);
            else this.bulkModal.selectedMembers.push(kode);
        },
        toggleSelectAllBulk() {
            if (this.isAllSelected) {
                const filteredKodes = this.filteredBulkMembers.map((m) => m.Kode_Karyawan);
                this.bulkModal.selectedMembers = this.bulkModal.selectedMembers.filter(
                    (k) => !filteredKodes.includes(k),
                );
            } else {
                this.filteredBulkMembers.forEach((m) => {
                    if (!this.bulkModal.selectedMembers.includes(m.Kode_Karyawan)) {
                        this.bulkModal.selectedMembers.push(m.Kode_Karyawan);
                    }
                });
            }
        },
        removeBulkDate(index) {
            this.bulkModal.dates.splice(index, 1);
        },
        disabledDate(date) {
            if (this.bulkModal.actionType !== 'SHIFT') return false;
            if (!this.bulkModal.selectedMembers.length) return false;

            const dateStr = dayjs(date).format('YYYY-MM-DD');
            return this.bulkModal.selectedMembers.some((kode) => {
                const day = this.weekSchedule[kode]?.[dateStr];
                return day?.status_hari?.startsWith('OFF-Hari_Libur');
            });
        },
        buildShiftOptionsFromShifts(shifts) {
            return (shifts || []).map((shift) => ({
                ...shift,
                id: shift.ID_Shift || shift.id,
                label: shift.display || shift.Nama || shift.nama || 'Shift',
                value: shift.ID_Shift || shift.id,
            }));
        },
        getShiftOptionCacheKey(dateStr, memberKode = null) {
            if (!dateStr) return '';
            return memberKode ? `${dateStr}|member:${memberKode}` : dateStr;
        },
        getCachedShiftOptions(dateStr, memberKode = null) {
            const key = this.getShiftOptionCacheKey(dateStr, memberKode);
            return this.bulkShiftOptionsByDate[key] || [];
        },
        hasCachedShiftOptions(dateStr, memberKode = null) {
            const key = this.getShiftOptionCacheKey(dateStr, memberKode);
            if (!key) return false;
            return Object.prototype.hasOwnProperty.call(this.bulkShiftOptionsByDate, key);
        },
        formatBulkSkippedDetails(skippedDetails, limit = 3) {
            if (!Array.isArray(skippedDetails) || skippedDetails.length === 0) {
                return '';
            }

            const preview = skippedDetails.slice(0, limit).map((item) => {
                const nama = this.getMemberNameByKode(item.kode) || item.kode;
                return `${nama} (${item.tanggal})`;
            });

            const suffix = skippedDetails.length > limit ? ` +${skippedDetails.length - limit} lainnya` : '';

            return `${preview.join(', ')}${suffix}`;
        },
        async getShiftOptionsForDate(dateStr, memberKode = null, memberKodes = []) {
            const membersKey =
                Array.isArray(memberKodes) && memberKodes.length ? [...new Set(memberKodes)].sort().join(',') : '';
            const cacheKey = this.getShiftOptionCacheKey(dateStr, memberKode);

            if (this.bulkShiftOptionsByDate[cacheKey]) {
                return this.bulkShiftOptionsByDate[cacheKey];
            }

            this.bulkShiftOptionsLoading = {
                ...this.bulkShiftOptionsLoading,
                [cacheKey]: true,
            };
            try {
                const params = { tanggal: dateStr };
                if (memberKode) {
                    params.member = memberKode;
                } else if (membersKey) {
                    params.members = [...new Set(memberKodes)];
                }

                const response = await axios.get('/leader-shift/shifts', {
                    params,
                });
                const shifts = response.data.result || response.data || [];
                const options = this.buildShiftOptionsFromShifts(shifts);
                this.bulkShiftOptionsByDate = {
                    ...this.bulkShiftOptionsByDate,
                    [cacheKey]: options,
                };
                return options;
            } catch (error) {
                console.error('Failed to load shift options', error);
                return [];
            } finally {
                this.bulkShiftOptionsLoading = {
                    ...this.bulkShiftOptionsLoading,
                    [cacheKey]: false,
                };
            }
        },
        async loadBulkShiftOptions(dateItem) {
            if (!dateItem?.tanggal) return;
            if (dateItem.shiftOptions?.length) return;
            dateItem.loading = true;
            try {
                const options = await this.getShiftOptionsForDate(dateItem.tanggal);
                dateItem.shiftOptions = options;
            } finally {
                dateItem.loading = false;
            }
        },
        async loadBulkPermanentShiftOptions() {
            if (this.bulkPermanentShiftOptions.length) return;
            this.bulkPermanentShiftOptionsLoading = true;
            try {
                const params = this.bulkModal.permanentDate
                    ? {
                          tanggal: this.bulkModal.permanentDate,
                          members: this.bulkModal.selectedMembers || [],
                      }
                    : {};
                const response = await axios.get('/leader-shift/shifts', {
                    params,
                });
                const shifts = response.data.result || response.data || [];
                this.bulkPermanentShiftOptions = this.buildShiftOptionsFromShifts(shifts);
                this.bulkPermanentShiftOptionsLoaded = true;
            } catch (error) {
                console.error('Failed to load permanent shifts', error);
            } finally {
                this.bulkPermanentShiftOptionsLoading = false;
            }
        },
        syncBulkDateItems() {
            const uniqueDates = Array.from(new Set(this.bulkModal.dates || []));
            const existingMap = new Map((this.bulkModal.dateItems || []).map((item) => [item.tanggal, item]));

            this.bulkModal.dateItems = uniqueDates.map((tanggal) => {
                const existing = existingMap.get(tanggal);
                return {
                    tanggal,
                    id_shift: existing?.id_shift || null,
                    shiftOptions: existing?.shiftOptions || [],
                    loading: existing?.loading || false,
                };
            });
        },
        syncBulkMemberDateItems() {
            const uniqueDates = Array.from(new Set(this.bulkModal.dates || []));
            const memberMap = new Map((this.bulkModal.memberDateItems || []).map((item) => [item.kode, item]));

            this.bulkModal.memberDateItems = this.bulkModal.selectedMembers.map((kode) => {
                const existing = memberMap.get(kode);
                const dateMap = new Map((existing?.dates || []).map((item) => [item.tanggal, item]));
                return {
                    kode,
                    dates: uniqueDates.map((tanggal) => ({
                        tanggal,
                        id_shift: dateMap.get(tanggal)?.id_shift || null,
                    })),
                };
            });
        },
        getBulkMemberDateItems(kode) {
            return (this.bulkModal.memberDateItems || []).find((item) => item.kode === kode);
        },
        getBulkMemberCompletion(kode) {
            const member = this.getBulkMemberDateItems(kode);
            const total = member?.dates?.length || 0;
            const filled = (member?.dates || []).filter((item) => !!item.id_shift).length;
            return { total, filled };
        },
        async ensureBulkShiftOptionsForDate(dateStr) {
            if (!dateStr) return [];
            return this.getShiftOptionsForDate(dateStr, null, this.bulkModal.selectedMembers || []);
        },
        async preloadBulkShiftOptionsForDates() {
            const dates = Array.from(new Set(this.bulkModal.dates || []));
            if (!dates.length) return;
            await Promise.all(dates.map((dateStr) => this.getShiftOptionsForDate(dateStr, this.bulkMemberModal.kode)));
        },
        openBulkMemberModal(kode) {
            this.bulkMemberModal = {
                visible: true,
                kode,
                applyAllShiftId: null,
            };
            this.preloadBulkShiftOptionsForDates();
        },
        closeBulkMemberModal() {
            this.bulkMemberModal.visible = false;
            this.bulkMemberModal.kode = null;
            this.bulkMemberModal.applyAllShiftId = null;
        },
        async applyBulkShiftToAllDates() {
            const shiftId = this.bulkModal.applyAllShiftId;
            if (!shiftId) return;

            let appliedCount = 0;
            let skippedCount = 0;
            const skippedDetails = [];

            await Promise.all(
                (this.bulkModal.memberDateItems || []).flatMap((member) =>
                    (member.dates || []).map(async (item) => {
                        if (!item.tanggal) return;
                        const options = await this.getShiftOptionsForDate(item.tanggal, member.kode);
                        const isValid = options.some((opt) => String(opt.value) === String(shiftId));
                        if (isValid) {
                            item.id_shift = shiftId;
                            appliedCount += 1;
                        } else {
                            item.id_shift = null;
                            skippedCount += 1;
                            skippedDetails.push({
                                kode: member.kode,
                                tanggal: item.tanggal,
                            });
                        }
                    }),
                ),
            );

            if (skippedCount > 0) {
                const detailText = this.formatBulkSkippedDetails(skippedDetails);
                ElMessage.warning(
                    `Shift diterapkan pada ${appliedCount} tanggal. ${skippedCount} tanggal dilewati karena shift tidak tersedia untuk sebagian anggota. ${detailText}`,
                );
            } else if (appliedCount > 0) {
                ElMessage.success('Shift diterapkan ke semua tanggal.');
            }
        },
        async applyBulkShiftToMemberDates() {
            const shiftId = this.bulkMemberModal.applyAllShiftId;
            if (!shiftId || !this.bulkMemberModal.kode) return;
            const member = this.getBulkMemberDateItems(this.bulkMemberModal.kode);
            if (!member) return;

            let appliedCount = 0;
            let skippedCount = 0;
            const skippedDetails = [];

            await Promise.all(
                (member.dates || []).map(async (item) => {
                    if (!item.tanggal) return;
                    const options = await this.getShiftOptionsForDate(item.tanggal, member.kode);
                    const isValid = options.some((opt) => String(opt.value) === String(shiftId));
                    if (isValid) {
                        item.id_shift = shiftId;
                        appliedCount += 1;
                    } else {
                        item.id_shift = null;
                        skippedCount += 1;
                        skippedDetails.push({
                            kode: member.kode,
                            tanggal: item.tanggal,
                        });
                    }
                }),
            );

            if (skippedCount > 0) {
                const detailText = this.formatBulkSkippedDetails(skippedDetails);
                ElMessage.warning(
                    `Shift diterapkan pada ${appliedCount} tanggal. ${skippedCount} tanggal dilewati karena shift tidak tersedia. ${detailText}`,
                );
            } else if (appliedCount > 0) {
                ElMessage.success('Shift diterapkan ke semua tanggal.');
            }
        },
        applyBulkShiftToAllMembers() {
            const shiftId = this.bulkModal.applyAllMemberShiftId;
            if (!shiftId) return;
            if (!this.bulkPermanentShiftOptions.length) {
                this.loadBulkPermanentShiftOptions();
            }
            const isValid = this.bulkPermanentShiftOptions.some((opt) => String(opt.value) === String(shiftId));
            if (!isValid) {
                ElMessage.warning('Shift tidak tersedia untuk tanggal permanen ini.');
                return;
            }
            this.bulkModal.memberShiftItems = this.bulkModal.memberShiftItems.map((item) => ({
                ...item,
                shiftId,
            }));
        },
        validateBulkStep(step) {
            /**
             * Validasi per Step dengan rule berbeda untuk setiap tipe aksi
             *
             * STEP 1: Pemilihan Aksi & Anggota
             * - Jenis aksi: SHIFT atau OFFDAY (WAJIB dipilih)
             * - Minimal 1 anggota harus dipilih
             *
             * STEP 2: Pengaturan Tanggal & Mode
             * SHIFT SEMENTARA:
             *   - Minimal 1 tanggal harus dipilih
             *   - Bisa pilih banyak tanggal sekaligus
             * SHIFT PERMANEN:
             *   - 1 tanggal harus dipilih sebagai tanggal mulai berlaku permanen
             *   - Akan menimpa semua shift sementara setelah tanggal ini
             * OFFDAY SINGLE:
             *   - Minimal 1 tanggal libur harus dipilih
             * OFFDAY RANGE:
             *   - Tanggal mulai & tanggal akhir HARUS LENGKAP
             * OFFDAY PERMANEN:
             *   - Tanggal mulai libur permanen harus dipilih
             *
             * STEP 3: Pengisian Data Detail & Alasan
             * SHIFT SEMENTARA:
             *   - SETIAP member HARUS punya shift untuk SETIAP tanggal yang dipilih
             *   - Jika ada member yang belum lengkap, validasi akan gagal
             * SHIFT PERMANEN:
             *   - SETIAP member HARUS dipilihkan shift-nya
             *   - Jika ada member yang belum ada shift, validasi akan gagal
             * OFFDAY:
             *   - Tidak perlu validasi (data automatic generate)
             *   - Opsi alasan boleh kosong
             */
            const errors = [];

            if (step === 1) {
                if (!this.bulkModal.actionType) {
                    errors.push('⚠️ Jenis aksi harus dipilih terlebih dulu (SHIFT atau OFFDAY)');
                }
                if (this.bulkModal.selectedMembers.length === 0) {
                    errors.push('⚠️ Minimal 1 anggota harus dipilih');
                }
            } else if (step === 2) {
                // Validate tanggal dan mode berdasarkan actionType
                if (this.bulkModal.actionType === 'SHIFT') {
                    if (this.bulkModal.jenis === 'SEMENTARA') {
                        if (!this.bulkModal.dates || this.bulkModal.dates.length === 0) {
                            errors.push('⚠️ Minimal 1 tanggal harus dipilih untuk Shift Sementara');
                        }
                    } else if (this.bulkModal.jenis === 'PERMANEN') {
                        if (!this.bulkModal.permanentDate) {
                            errors.push('⚠️ Tanggal permanen harus dipilih sebagai tanggal mulai berlaku');
                        }
                    }
                } else if (this.bulkModal.actionType === 'OFFDAY') {
                    if (this.bulkModal.offdayMode === 'SINGLE') {
                        if (!this.bulkModal.dates || this.bulkModal.dates.length === 0) {
                            errors.push('⚠️ Minimal 1 tanggal libur harus dipilih');
                        }
                    } else if (this.bulkModal.offdayMode === 'RANGE') {
                        if (!this.bulkModal.offdayRange || this.bulkModal.offdayRange.length !== 2) {
                            errors.push('⚠️ Range tanggal harus lengkap: tanggal mulai & tanggal akhir');
                        }
                    } else if (this.bulkModal.offdayMode === 'PERMANEN') {
                        if (!this.bulkModal.offdayStart) {
                            errors.push('⚠️ Tanggal mulai libur permanen harus dipilih');
                        }
                    }
                }
            } else if (step === 3) {
                // Validasi pengisian data detail
                if (this.bulkModal.actionType === 'SHIFT') {
                    if (this.bulkModal.jenis === 'SEMENTARA') {
                        const incomplete = this.bulkModal.memberDateItems.filter((member) => {
                            const filled = member.dates.filter((d) => !!d.id_shift).length;
                            return filled < member.dates.length;
                        });
                        if (incomplete.length > 0) {
                            const names = incomplete.map((m) => this.getMemberNameByKode(m.kode)).join(', ');
                            errors.push(`⚠️ Shift belum lengkap untuk: ${names}`);
                            errors.push('💡 Pastikan setiap member memiliki shift untuk SETIAP tanggal yang dipilih');
                        }
                    } else if (this.bulkModal.jenis === 'PERMANEN') {
                        const incomplete = this.bulkModal.memberShiftItems.filter((item) => !item.shiftId);
                        if (incomplete.length > 0) {
                            const names = incomplete.map((m) => this.getMemberNameByKode(m.kode)).join(', ');
                            errors.push(`⚠️ Shift belum dipilih untuk: ${names}`);
                        }
                    }
                }

                // Validasi Alasan
                if (!this.bulkModal.alasan || this.bulkModal.alasan.trim() === '') {
                    errors.push('⚠️ Alasan pengajuan tidak boleh kosong');
                }
            }

            return { valid: errors.length === 0, errors };
        },
        showValidationErrors(errors) {
            if (!errors || errors.length === 0) return;

            const message = errors.join('\n');
            ElMessage({
                message: message,
                type: 'error',
                duration: 5000,
            });
        },
        proceedBulkStep() {
            if (!this.canProceedBulk) {
                this.showValidationErrors(['Tombol Lanjutkan tidak dapat diklik. Periksa kembali data Anda.']);
                return;
            }

            const validation = this.validateBulkStep(this.bulkModal.step);
            if (!validation.valid) {
                this.showValidationErrors(validation.errors);
                return;
            }

            this.bulkModal.step++;
        },
        confirmSubmitBulk() {
            // Validasi step 3 sebelum submit
            const validation = this.validateBulkStep(3);
            if (!validation.valid) {
                this.showValidationErrors(validation.errors);
                return;
            }

            // Validasi final canSubmitBulk
            if (!this.canSubmitBulk) {
                this.showValidationErrors(['⚠️ Data tidak lengkap. Periksa kembali semua field.']);
                return;
            }

            const memberCount = this.bulkModal.selectedMembers.length;
            const dateCount = this.bulkModal.jenis === 'PERMANEN' ? 1 : this.bulkModal.dates.length;

            let message = `Konfirmasi submit pengajuan untuk ${memberCount} anggota`;
            if (this.bulkModal.jenis === 'PERMANEN') {
                message += ` dengan shift permanen mulai ${this.formatDateShort(this.bulkModal.permanentDate)}?`;
            } else {
                message += ` untuk ${dateCount} tanggal?`;
            }

            this.confirmModal.message = message;
            this.confirmModal.action = 'bulk';
            this.confirmModal.visible = true;
        },

        async handleConfirmSubmit() {
            // Set submitting flag - keeps modal visible and loading
            this.confirmModal.isSubmitting = true;

            try {
                // Execute action based on type
                if (this.confirmModal.action === 'quick') {
                    await this.executeQuickSubmit();
                } else if (this.confirmModal.action === 'bulk') {
                    await this.submitBulk();
                }
                // Close modal only after successful submission
                this.confirmModal.visible = false;
            } catch (error) {
                // Keep modal open on error, user can retry
                console.error('Submission error:', error);
            } finally {
                this.confirmModal.isSubmitting = false;
                this.confirmModal.action = null;
            }
        },

        handleConfirmModalClose() {
            // Only allow closing if not currently submitting
            if (this.confirmModal.isSubmitting) {
                return; // Prevent closing during submission
            }
            this.closeConfirmModal();
        },
        closeConfirmModal() {
            this.confirmModal.visible = false;
            this.confirmModal.action = null;
            this.confirmModal.details = null;
            this.confirmModal.title = '';
            this.confirmModal.message = '';
        },

        getShiftNameById(shiftId) {
            const shift = this.bulkPermanentShiftOptions.find((s) => s.value === shiftId);
            return shift ? shift.label : 'N/A';
        },

        async submitBulk() {
            this.loading.submit = true;
            try {
                const refreshAnchorDate = this.getBulkRefreshAnchorDate() || this.startDate;

                if (
                    this.bulkModal.actionType === 'SHIFT' &&
                    this.bulkModal.jenis === 'PERMANEN' &&
                    this.bulkOverwriteSementara &&
                    this.bulkPermanentConflictTotal > 0
                ) {
                    const confirmed = await this.confirmPermanentOverride(
                        `Ada ${this.bulkPermanentConflictTotal} shift sementara aktif yang akan dinonaktifkan. Lanjutkan?`,
                    );
                    if (!confirmed) {
                        this.loading.submit = false;
                        return;
                    }
                }

                // Store selected members before modal closes
                const selectedMembersCopy = [...this.bulkModal.selectedMembers];

                if (this.bulkModal.actionType === 'OFFDAY') {
                    await this.submitBulkOffday();
                    this.$message.success('Pengajuan offday berhasil disubmit');
                } else {
                    const dates =
                        this.bulkModal.jenis === 'PERMANEN'
                            ? [{ tanggal: this.bulkModal.permanentDate }]
                            : this.bulkModal.dates.map((tanggal) => ({
                                  tanggal,
                              }));

                    const members =
                        this.bulkModal.jenis === 'PERMANEN'
                            ? this.bulkModal.memberShiftItems.map((item) => ({
                                  kode: item.kode,
                                  id_shift: item.shiftId,
                              }))
                            : this.bulkModal.memberDateItems.map((member) => ({
                                  kode: member.kode,
                                  dates: (member.dates || []).map((item) => ({
                                      tanggal: item.tanggal,
                                      id_shift: item.id_shift,
                                  })),
                              }));

                    await axios.post(`/${this.routePrefix}/submit`, {
                        members,
                        dates,
                        jenis: this.bulkModal.jenis,
                        alasan: this.bulkModal.alasan,
                        overwrite_sementara: this.bulkModal.jenis === 'PERMANEN' ? this.bulkOverwriteSementara : false,
                    });

                    this.$message.success('Pengajuan bulk berhasil disubmit');
                }

                this.closeBulkModal();
                this.setCalendarWeek(refreshAnchorDate);
                // Load full schedule and ensure selected members' data is refreshed
                await this.loadSchedule(true);
                this.loadWidgetStats();
                // If members were selected, reload their specific data to show updated disabled dates
                if (selectedMembersCopy.length > 0) {
                    await this.loadScheduleForMembers(selectedMembersCopy);
                }
            } catch (e) {
                this.$message.error(e.response?.data?.message || 'Gagal submit bulk');
                throw e; // Re-throw to let handleConfirmSubmit catch it
            } finally {
                this.loading.submit = false;
            }
        },
        async submitBulkOffday() {
            const mode = this.bulkModal.offdayMode;
            const payload = {
                members: this.bulkModal.selectedMembers,
                mode: mode,
                alasan: this.bulkModal.alasan,
            };

            if (mode === 'SINGLE') {
                payload.dates = this.bulkModal.dates;
            } else if (mode === 'RANGE') {
                payload.start_date = this.bulkModal.offdayRange?.[0] || null;
                payload.end_date = this.bulkModal.offdayRange?.[1] || null;
            } else {
                payload.start_date = this.bulkModal.offdayStart || dayjs().format('YYYY-MM-DD');
            }

            await axios.post(`/${this.routePrefix}/offday`, payload);
        },
        async confirmPermanentOverride(message) {
            try {
                await ElMessageBox.confirm(message, 'Konfirmasi', {
                    confirmButtonText: 'Lanjutkan',
                    cancelButtonText: 'Batal',
                    type: 'warning',
                });
                return true;
            } catch (error) {
                return false;
            }
        },
        async fetchPermanentConflicts(members, dates) {
            if (!members?.length || !dates?.length) return null;
            const response = await axios.post('/leader-shift/permanent-conflicts', {
                members,
                dates,
            });
            return response.data?.result || response.data?.data || null;
        },
        async updateQuickModalPermanentConflicts() {
            const memberKode = this.quickModalData.member?.Kode_Karyawan;
            const dateStr = this.quickModalData.date;
            if (!memberKode || !dateStr || !this.quickModalData.makePermanent) {
                return;
            }
            this.quickModalPermanentConflictLoading = true;
            try {
                const result = await this.fetchPermanentConflicts([memberKode], [dateStr]);
                this.quickModalPermanentConflictCount = result?.details?.[0]?.count || 0;
            } catch (error) {
                console.error('Failed to load permanent conflict count', error);
            } finally {
                this.quickModalPermanentConflictLoading = false;
            }
        },
        async updateBulkPermanentConflicts() {
            if (this.bulkModal.actionType !== 'SHIFT') {
                this.bulkPermanentConflictTotal = 0;
                this.bulkPermanentConflictByDate = [];
                this.bulkPermanentConflictByMember = [];
                return;
            }
            if (this.bulkModal.jenis !== 'PERMANEN') {
                this.bulkPermanentConflictTotal = 0;
                this.bulkPermanentConflictByDate = [];
                this.bulkPermanentConflictByMember = [];
                return;
            }

            const members = this.bulkModal.selectedMembers || [];
            const dates = this.bulkModal.permanentDate ? [this.bulkModal.permanentDate] : [];
            if (!members.length || !dates.length) {
                this.bulkPermanentConflictTotal = 0;
                this.bulkPermanentConflictByDate = [];
                this.bulkPermanentConflictByMember = [];
                return;
            }

            this.bulkPermanentConflictLoading = true;
            try {
                const result = await this.fetchPermanentConflicts(members, dates);
                this.bulkPermanentConflictTotal = result?.total || 0;
                this.bulkPermanentConflictByDate = result?.by_date || [];
                this.bulkPermanentConflictByMember = result?.by_member || [];
            } catch (error) {
                console.error('Failed to load bulk permanent conflicts', error);
            } finally {
                this.bulkPermanentConflictLoading = false;
            }
        },

        // --- History ---
        async loadHistory(url = null) {
            if (typeof url !== 'string') url = null;
            this.loading.history = true;
            try {
                const { startDate, endDate } = this.getHistoryCreatedRange();
                const params = {
                    page: this.historyPagination.page,
                    limit: this.historyPagination.limit,
                    search: this.historyFilters.search || '',
                    start_date: startDate || '',
                    end_date: endDate || '',
                    status: this.historyFilters.status || 'all',
                    transaction_type: this.historyFilters.transaction_type || 'all',
                };

                const res = await axios.get(url || `/${this.routePrefix}/history`, {
                    params: params,
                });
                // successWithPaginationV2: result = items array, pagination = metadata
                this.historyItems = res.data.result || [];
                this.historyPagination.total = res.data.pagination?.total || 0;
                this.historyPagination.total_pages = res.data.pagination?.total_pages || 1;
                this.historyPagination.page = res.data.pagination?.current_page || 1;
            } catch (e) {
                console.error(e);
            } finally {
                this.loading.history = false;
            }
        },
        handleHistoryFilters(filters) {
            this.historyFilters = filters;
            this.historyPagination.page = 1;
            this.loadHistory();
        },
        getHistoryCreatedRange() {
            const createdAt = this.historyFilters?.created_at;
            if (Array.isArray(createdAt) && createdAt.length === 2) {
                return {
                    startDate: createdAt[0] || '',
                    endDate: createdAt[1] || '',
                };
            }

            if (createdAt) {
                return {
                    startDate: createdAt,
                    endDate: createdAt,
                };
            }

            return { startDate: '', endDate: '' };
        },
        goHistoryPage(page) {
            if (page < 1 || page > this.historyPagination.total_pages) return;
            this.historyPagination.page = page;
            this.loadHistory();
        },
        async openHistoryDetail(item) {
            this.historyDetailModal.visible = true;
            this.historyDetailModal.loading = true;
            this.historyDetailModal.data = null;
            this.historyDetailModal.expandedMembers = [];

            // console.log("Fetching detail for:", item.No_Transaksi);

            try {
                const res = await axios.get(`/leader-shift/detail/${item.No_Transaksi}`);

                // console.log("Response data:", res.data);

                if (res.data && res.data.result) {
                    this.historyDetailModal.data = res.data.result;
                    this.historyDetailModal.loading = false;
                } else {
                    throw new Error('Invalid data structure received from server');
                }
            } catch (e) {
                console.error('Failed to load detail:', e);
                const errorMsg = e.response?.data?.message || e.message || 'Gagal memuat detail transaksi';
                this.$message.error(errorMsg);
                this.historyDetailModal.visible = false;
                this.historyDetailModal.loading = false;
            }
        },
        isPendingDet(date) {
            if (!date || date.Status === 'Y') return false;
            const needsApproval = date.Needs_Approval === 'Y';
            const isApproved = date.Flag_Approval === 'Y';
            const isRejected = date.Flag_Approval === 'T';
            const isApplied = date.Flag_Applied === 'Y';

            return needsApproval && !isApproved && !isRejected && !isApplied;
        },
        canCancelHeaderFromDetail(data) {
            if (!data?.members || data.members.length === 0) return false;
            if (data.header?.Status === 'Y') return false;
            return data.members.every((member) => this.canCancelMember(member));
        },
        canCancelMember(member) {
            if (!member || member.Status === 'Y') return false;
            if (!member.dates || member.dates.length === 0) return false;
            return member.dates.every((date) => this.isPendingDet(date));
        },
        canCancelDate(date) {
            return this.isPendingDet(date);
        },
        openCancelModal(payload) {
            if (!payload?.no_transaksi) return;
            const scopeLabel =
                payload.scope === 'header'
                    ? 'transaksi'
                    : payload.scope === 'detail'
                      ? 'pengajuan karyawan'
                      : 'tanggal';
            this.cancelModal.title = 'Konfirmasi Pembatalan';
            this.cancelModal.message = `Yakin ingin membatalkan ${scopeLabel} ini?`;
            this.cancelModal.payload = payload;
            this.cancelModal.visible = true;
        },
        closeCancelModal() {
            this.cancelModal.visible = false;
            this.cancelModal.payload = null;
            this.cancelModal.message = '';
        },
        async confirmCancelHistory() {
            const payload = this.cancelModal.payload;
            if (!payload) return;

            try {
                this.loading.cancelHistory = true;
                await axios.post('/leader-shift/cancel', payload);
                ElMessage.success('Pengajuan berhasil dibatalkan.');
                this.closeCancelModal();
                this.loadHistory();
                this.loadWidgetStats();
                if (this.historyDetailModal.visible) {
                    const noTransaksi = this.historyDetailModal.data?.header?.No_Transaksi;
                    if (noTransaksi) {
                        await this.openHistoryDetail({
                            No_Transaksi: noTransaksi,
                        });
                    }
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal membatalkan pengajuan.');
            } finally {
                this.loading.cancelHistory = false;
            }
        },
        bukaJejakHari(member, tanggal) {
            if (!member?.Kode_Karyawan || !tanggal) return;
            this.showQuickModal = false;
            this.jejakHari = { buka: true, kode: member.Kode_Karyawan, tanggal: String(tanggal).slice(0, 10) };
        },
        getHistoryStatusClass(item) {
            if (item.Status === 'Y') return 'cancelled';
            if (item.Flag_Selesai === 'Y' || item.Status === 'APPROVED') return 'approved';
            if (item.Status === 'REJECTED') return 'rejected';
            return 'pending';
        },
        getHistoryIcon(type) {
            return type === 'TUKAR_HARI' ? 'bi bi-arrow-left-right text-warning' : 'bi bi-calendar-check text-primary';
        },
        getHistoryDurationText(item) {
            if (item?.transaction_type === 'TUKAR_HARI') {
                return '1 Tukar Hari';
            }

            const countCandidates = [
                item?.tanggal_count,
                item?.total_tanggal,
                item?.total_hari,
                item?.jumlah_hari,
                item?.Hari,
                item?.hari,
            ].filter((value) => typeof value === 'number' && value >= 0);

            const count = countCandidates.length ? countCandidates[0] : null;
            if (count === null) return '-';
            return `${count} Hari`;
        },
        toggleDetailMember(index) {
            const idx = this.historyDetailModal.expandedMembers.indexOf(index);
            if (idx > -1) {
                this.historyDetailModal.expandedMembers.splice(idx, 1);
            } else {
                this.historyDetailModal.expandedMembers.push(index);
            }
        },
        closeHistoryDetail() {
            this.historyDetailModal.visible = false;
            this.historyDetailModal.data = null;
            this.historyDetailModal.expandedMembers = [];
        },
        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .slice(0, 2)
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },
        getMemberNameByKode(kode) {
            const member = this.team.find((m) => m.Kode_Karyawan === kode);
            return member?.Nama || kode || '-';
        },
        formatDateShort(date) {
            return dayjs(date).format('D MMM');
        },
        formatDateFull(date) {
            return dayjs(date).format('D MMM YYYY, HH:mm');
        },
        getStatusColor(item, type) {
            const status = item.Status;

            if (status === 'APPROVED' || item.Flag_Selesai === 'Y') {
                return type === 'bg'
                    ? 'bg-success-subtle text-success'
                    : 'bg-success-subtle text-success border-success';
            }
            if (status === 'REJECTED') {
                return type === 'bg' ? 'bg-danger-subtle text-danger' : 'bg-danger-subtle text-danger border-danger';
            }
            return type === 'bg'
                ? 'bg-warning-subtle text-warning-emphasis'
                : 'bg-warning-subtle text-warning-emphasis border-warning';
        },
        getStatusLabel(item) {
            // Batal oleh leader sendiri tak tampil di riwayat; yang tampil = batal admin.
            if (item.Status === 'Y') return item.dibatalkan_admin ? 'Dibatalkan Admin' : 'Dibatalkan';
            if (item.Flag_Selesai === 'Y') return 'Selesai';
            if (item.Status === 'APPROVED') return 'Disetujui';
            if (item.Status === 'REJECTED') return 'Ditolak';
            return 'Menunggu Approval';
        },
    },
};
</script>

<style scoped>
:root {
    /* Color Palette - User Provided (Indigo Theme) */
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 10px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;

    /* Gradients (Derived) */
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

/* =========================================
   PREMIUM STITCH DESIGN SYSTEM
   ========================================= */

/* UTILITIES */
.cursor-pointer {
    cursor: pointer;
}
.transition-all {
    transition: all 0.2s ease;
}
.text-xxs {
    font-size: 0.65rem;
}
.text-xs {
    font-size: 0.75rem;
}
.text-sm {
    font-size: 0.875rem;
}
.font-bold {
    font-weight: 700;
}
.font-medium {
    font-weight: 500;
}
.fw-bold {
    font-weight: 700 !important;
}

.btn-surface-soft {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    transition: all 0.2s ease;
}

.btn-surface-soft:hover,
.btn-surface-soft.active {
    background: var(--surface);
    border-color: var(--primary);
    color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn-surface {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    transition: all 0.2s ease;
}

.btn-surface:hover,
.btn-surface.active {
    background: var(--surface-soft);
    border-color: var(--primary);
    color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
/* Colors */
.bg-primary-subtle {
    background-color: #eff6ff !important;
    color: #1e40af !important;
}
.bg-primary-subtle-light {
    background-color: var(--hcis-slate-50) !important;
}
.bg-surface-soft {
    background-color: #f8f9fa !important;
}
.hover-bg-light:hover {
    background-color: #f8f9fa !important;
}
.hover-shadow:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}

.grow-config {
    flex-grow: 1;
}

/* BUTTONS */
.btn-modern {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.btn-modern.btn-primary {
    background: var(--hcis-indigo-600);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.btn-modern.btn-primary:hover {
    background: var(--hcis-indigo-700);
    transform: translateY(-1px);
}

.btn-modern.btn-secondary {
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #374151;
}

.btn-modern.btn-secondary:hover {
    background: #f9fafb;
    border-color: #d1d5db;
}

.stitch-action-btn {
    border: 1px solid #e5e7eb;
    background: white;
    color: #6b7280;
    transition: all 0.2s;
}

.stitch-action-btn:hover {
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}

/* CARDS */
.stitch-card {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* CALENDAR GRID */
.calendar-grid-wrapper {
    overflow-x: auto;
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 12px;
}

.calendar-header-row {
    display: flex;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.calendar-row {
    display: flex;
    background: white;
}

.shift-cell {
    min-height: 80px;
    transition: background 0.1s;
}

.shift-cell:hover {
    background-color: var(--hcis-slate-50);
}

.shift-content .add-icon {
    display: none;
}

.shift-cell:hover .add-icon {
    display: block;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}

/* MOBILE CARD VIEW */
.mobile-day-card {
    transition: all 0.2s;
}
.mobile-day-card.clickable:active {
    transform: scale(0.95);
}

/* STEPPER */
.stitch-stepper {
    display: flex;
    justify-content: space-between;
    position: relative;
    max-width: 500px;
    margin: 0 auto;
}

.stitch-stepper::before {
    content: '';
    position: absolute;
    top: 16px;
    left: 40px;
    right: 40px;
    height: 2px;
    background: #e5e7eb;
    z-index: 0;
}

.stitch-step {
    position: relative;
    z-index: 1;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.stitch-step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: white;
    border: 2px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #9ca3af;
    transition: all 0.3s;
}

.stitch-step.active .stitch-step-circle {
    border-color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-600);
    color: white;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
}

.stitch-step.completed .stitch-step-circle {
    border-color: var(--hcis-emerald-500);
    background: var(--hcis-emerald-500);
    color: white;
}

.stitch-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
}

.stitch-step.active .stitch-step-label {
    color: var(--hcis-indigo-600);
}
.stitch-step.completed .stitch-step-label {
    color: var(--hcis-emerald-500);
}

.stitch-date-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.5rem;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 6px;
    color: #1e40af;
    font-size: 0.75rem;
    font-weight: 600;
    margin: 0.25rem;
}

.stitch-date-chip button {
    background: none;
    border: none;
    padding: 0;
    color: #1e40af;
    opacity: 0.5;
    cursor: pointer;
}
.stitch-date-chip button:hover {
    opacity: 1;
    color: var(--hcis-red-600);
}

/* LOADING STATE */
.loading-spinner {
    font-size: 2rem;
    color: var(--hcis-indigo-600);
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    100% {
        transform: rotate(360deg);
    }
}

/* Element Plus Overrides */
.el-date-picker-full {
    width: 100% !important;
}

@media (max-width: 1200px) {
    .calendar-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .avatar-approver {
        display: none;
    }

    .nama-approver {
        font-size: 0.75rem !important;
    }

    .approver-card {
        max-width: none !important;
        flex: 1 !important;
    }

    .jabatan-approver {
        font-size: 0.55rem !important;
    }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .stitch-stepper {
        padding: 1rem !important;
    }
    .stitch-step-label {
        display: none;
    }
    .btn-modern {
        width: 100%;
        text-align: center;
    }
    .modal-footer {
        flex-direction: column-reverse;
        gap: 0.5rem;
    }
}

/* TABLE STYLES (Stitch) */
.stitch-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
}

.stitch-table th {
    padding: 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #6b7280;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.stitch-table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.875rem;
}

.stitch-table-row:last-child td {
    border-bottom: none;
}

.stitch-table-row:hover td {
    background-color: var(--hcis-slate-50);
}

.badgem {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.avatar-circle-sm {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

/* DATE NAV & STITCH BUTTONS (SelfShift Style) */
.date-nav-wrapper {
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    border: 1px solid #f0f0f0;
}

.date-nav-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.date-nav-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-label {
    font-size: 1rem;
    letter-spacing: -0.01em;
}

.stitch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.5s ease;
    border: none;
    cursor: pointer;
}

.stitch-btn.primary {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-700) 100%);
    color: white;
    box-shadow:
        0 4px 6px -1px rgba(67, 56, 202, 0.3),
        0 2px 4px -1px rgba(67, 56, 202, 0.1);
}

.stitch-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow:
        0 6px 8px -1px rgba(67, 56, 202, 0.4),
        0 4px 6px -1px rgba(67, 56, 202, 0.2);
}

.stitch-btn.primary:active {
    transform: translateY(0);
}

.stitch-btn.secondary {
    background: white;
    color: #4b5563;
    border: 1px solid #e5e7eb;
}

.stitch-btn.secondary:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #111827;
}

.stitch-btn.success {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: white;
    box-shadow:
        0 4px 6px -1px rgba(16, 185, 129, 0.3),
        0 2px 4px -1px rgba(16, 185, 129, 0.1);
}

.stitch-btn.success:hover {
    transform: translateY(-1px);
    box-shadow:
        0 6px 8px -1px rgba(16, 185, 129, 0.4),
        0 4px 6px -1px rgba(16, 185, 129, 0.2);
}

.stitch-btn.success:active {
    transform: translateY(0);
}

.stitch-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

/* .stitch-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #4b5563;
    border-radius: 8px;
    transition: all 0.2s;
    cursor: pointer;
} */

.stitch-action-btn:hover {
    background: #f3f4f6;
    color: #111827;
}

.stitch-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .date-nav-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        padding: 1rem;
    }

    .date-nav-left {
        justify-content: space-between;
        width: 100%;
    }

    .date-nav-right {
        width: 100%;
    }

    .stitch-btn {
        width: 100%;
    }
}
</style>

<style scoped>
/* =========================================
   MODAL CONFIRMATION Z-INDEX OVERRIDE
   ========================================= */
.modal-confirmation-wrapper .modal-overlay {
    z-index: 9999 !important;
}

/* =========================================
   CONFIRMATION MODAL STYLING
   ========================================= */
.confirmation-content {
    padding: 1rem;
}

.confirmation-message {
    font-size: 0.95rem;
    line-height: 1.5;
    color: #374151;
    margin-bottom: 1.25rem;
}

/* Confirmation Details Box */
.confirmation-details-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 0.75rem;
}

.detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.4rem 0;
    font-size: 0.9rem;
}

.detail-label {
    font-weight: 600;
    color: #495057;
    min-width: 45px;
}

.detail-value {
    color: #212529;
    flex: 1;
    text-align: right;
}

.confirmation-footer {
    display: flex;
    gap: 0.75rem;
    width: 100%;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .confirmation-footer {
        flex-direction: column-reverse;
    }

    .confirmation-footer .stitch-btn {
        width: 100%;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .detail-value {
        text-align: left;
    }
}

/* =========================================
   PREMIUM DESIGN SYSTEM 
   ========================================= */

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
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

:root {
    /* Color Palette - User Provided (Indigo Theme) */
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;

    /* Gradients (Derived) */
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.tengahkan {
    display: flex;
    justify-content: center;
    align-items: center;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

/* Hide default checkbox */
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

/* Track */
.slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d1d5db; /* gray */
    border-radius: 999px;
    transition: background-color 0.3s ease;
}

/* Thumb */
.slider::before {
    content: '';
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    transition: transform 0.3s ease;
}

/* Checked */
.toggle-switch input:checked + .slider {
    background-color: var(--hcis-indigo-600); /* indigo */
}

.toggle-switch input:checked + .slider::before {
    transform: translateX(24px);
}

/* Optional focus */
.toggle-switch input:focus + .slider {
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
}

/* Compact toggle (small) */
.toggle-switch-sm {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    cursor: pointer;
}
.toggle-switch-sm input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider-sm {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d1d5db;
    border-radius: 999px;
    transition: background-color 0.3s ease;
}
.slider-sm::before {
    content: '';
    position: absolute;
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
}
.toggle-switch-sm input:checked + .slider-sm {
    background-color: var(--hcis-indigo-600);
}
.toggle-switch-sm input:checked + .slider-sm::before {
    transform: translateX(16px);
}

/* Label text */
.toggle-label {
    margin-left: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

/* Universal Icon Centering */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* =========================================
   UTILITY CLASSES (Tailwind Mimic)
   ========================================= */
.text-primary {
    color: var(--primary) !important;
}
.text-secondary {
    color: var(--secondary) !important;
}
.text-success {
    color: var(--success) !important;
}
.text-danger {
    color: var(--danger) !important;
}
.text-warning {
    color: var(--warning) !important;
}
.text-muted {
    color: var(--text-muted) !important;
}
.text-dark {
    color: var(--text) !important;
}

.bg-primary {
    background-color: var(--primary) !important;
}
.bg-primary-subtle {
    background-color: var(--hcis-indigo-100) !important;
    color: var(--primary-dark) !important;
}
.bg-surface {
    background-color: var(--surface) !important;
}
.bg-light {
    background-color: var(--surface-soft) !important;
}

.rounded-lg {
    border-radius: var(--radius) !important;
}
.rounded-xl {
    border-radius: var(--radius-lg) !important;
}
.rounded-full {
    border-radius: 9999px !important;
}

.shadow-sm {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
}
.shadow {
    box-shadow: var(--shadow) !important;
}
.shadow-lg {
    box-shadow: var(--shadow-lg) !important;
}

.border {
    border: 1px solid var(--border) !important;
}
.border-0 {
    border: none !important;
}

.flex {
    display: flex !important;
}
.flex-col {
    flex-direction: column !important;
}
.items-center {
    align-items: center !important;
}
.justify-between {
    justify-content: space-between !important;
}
.gap-2 {
    gap: 0.5rem !important;
}
.gap-4 {
    gap: 1rem !important;
}

.p-4 {
    padding: 1rem !important;
}
.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}
.px-4 {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.w-full {
    width: 100% !important;
}
.h-full {
    height: 100% !important;
}

.font-bold {
    font-weight: 700 !important;
}
.font-medium {
    font-weight: 500 !important;
}
.text-sm {
    font-size: 0.875rem !important;
}
.text-xs {
    font-size: 0.75rem !important;
}

/* =========================================
   GLOBAL LAYOUT & TYPOGRAPHY
   ========================================= */

.cuti-app-container {
    background-color: var(--bg-container);
    min-height: 100vh;
    padding-bottom: 2rem;
    /* font-family: 'Inter', system-ui, -apple-system, sans-serif; */
    color: var(--text);
}

.main-content-wrapper {
    /* max-width: 1400px; */
    /* margin: -40px auto 0; Overlap header slightly for premium look */
    /* padding: 0 1.5rem; */
    position: relative;
    z-index: 10;
}

.content-container {
    /* background: var(--surface); */
    /* border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); */
    min-height: 500px;
    overflow: hidden;
    /* border: 1px solid var(--border); */
}

/* =========================================
   COMPONENTS
   ========================================= */

/* Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.btn-modern.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.btn-modern.btn-primary:hover {
    filter: brightness(110%);
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(79, 70, 229, 0.3);
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    transition: all 0.2s;
    background: transparent;
    border: none;
}

.btn-icon:hover {
    background: var(--surface-soft);
    color: var(--primary);
}

/* Filter Section */
.filter-section {
    padding: 1.5rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    margin-bottom: 0 !important; /* Override template mb-4 */
}

.nav-group {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    padding: 0.25rem;
    border-radius: var(--radius-sm);
}

/* =========================================
   CALENDAR GRID SYSTEM
   ========================================= */

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1rem;
    padding: 1.5rem;
    background: var(--surface-soft);
}

.calendar-cell {
    background: var(--surface);
    border-radius: var(--radius);
    min-height: 180px;
    padding: 1rem;
    border: 1px solid var(--border);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
}

.calendar-cell:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow);
    z-index: 2;
}

.calendar-cell.is-today {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-violet-50), var(--hcis-white));
}

.calendar-cell.is-backdate {
    opacity: 0.7;
    background: #fcfcfc;
    cursor: default;
}

.calendar-cell.is-backdate:hover {
    transform: none;
    box-shadow: none;
    border-color: var(--border);
}

/* Cell Header */
.cell-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.day-name {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
}

.date-circle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text);
}

.date-circle.current-date {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
}

/* Shift Content within Cell */
.shift-info-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.75rem;
    text-align: center;
    margin-top: auto;
}

.shift-time-badge {
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--primary-dark);
    display: inline-block;
    margin-bottom: 0.5rem;
    border: 1px solid var(--border);
}

.shift-name {
    font-weight: 600;
    color: var(--text);
    font-size: 0.875rem;
    display: block;
}

.empty-shift-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0.4;
    transition: 0.2s;
}

.calendar-cell:hover .empty-shift-state {
    opacity: 0.8;
}

.dashed-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px dashed var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.calendar-cell:hover .dashed-circle {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05); /* Tint */
}

/* =========================================
   HISTORY TAB & TABLES
   ========================================= */

.tab-content-improved {
    padding: 0;
}

/* Reuse styling from Reference KuotaManagement */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.modern-table th {
    background: var(--surface-soft);
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}

.modern-table tr:hover td {
    background-color: var(--surface-soft);
}

/* =========================================
   MODALS & FORMS
   ========================================= */

.detail-row {
    display: flex;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.detail-label {
    width: 140px;
    color: var(--text-muted);
    font-weight: 500;
}

.detail-value {
    flex: 1;
    color: var(--text);
    font-weight: 500;
}

/* Responsive */
@media (max-width: 1024px) {
    .calendar-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    /* .main-content-wrapper {
        margin-top: -20px;
        padding: 0 1rem;
    } */

    .calendar-grid {
        grid-template-columns: repeat(2, 1fr);
        padding: 1rem;
        gap: 0.5rem;
    }

    .calendar-cell {
        min-height: 140px;
    }

    .filter-section {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .calendar-grid {
        grid-template-columns: 1fr;
    }
}
/* =========================================
   MODALS & FORMS EXTRA
   ========================================= */

/* Form Groups & Inputs */
.form-field-group {
    margin-bottom: 1.25rem;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    display: block;
}

.form-label-sm {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.35rem;
    display: block;
}

.form-control,
.form-textarea {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.625rem 0.875rem;
    font-size: 0.9rem;
    color: var(--text);
    background: var(--surface);
    transition: all 0.2s;
    width: 100%;
}

.form-control:focus,
.form-textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); /* Primary ring */
    outline: none;
}

/* Custom Checkbox Card */
.form-check-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
}

.form-check-card:hover {
    border-color: var(--primary-light);
    background: white;
}

.form-check-input {
    margin-top: 0.25rem;
    cursor: pointer;
}

/* v-select Overrides for Premium Look */
.modern-select .vs__dropdown-toggle {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 4px 0; /* Adjust internal padding */
    background: var(--surface);
    transition: all 0.2s;
}

.modern-select .vs__search::placeholder {
    color: var(--text-light);
}

.modern-select.vs--open .vs__dropdown-toggle {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    border-bottom-color: transparent;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}

.modern-select .vs__dropdown-menu {
    border: 1px solid var(--primary);
    border-top: none;
    box-shadow: var(--shadow-lg);
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
    padding: 0;
}

.modern-select .vs__dropdown-option {
    padding: 8px 12px;
    color: var(--text);
}

.modern-select .vs__dropdown-option--highlight {
    background: var(--primary);
    color: white;
}

/* Wizard Steps */
.wizard-steps {
    display: flex;
    padding: 0;
    margin-bottom: 2rem;
    position: relative;
}

.step {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.5rem;
    opacity: 0.6;
    transition: all 0.3s;
    position: relative;
    z-index: 1;
}

.step::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--border);
    z-index: -1;
}

.step:last-child::after {
    display: none;
}

.step.active,
.step.completed {
    opacity: 1;
}

.step.completed::after {
    background: var(--success);
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--surface);
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--text-muted);
    transition: all 0.3s;
}

.step.active .step-number {
    border-color: var(--primary);
    background: var(--primary);
    color: white;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
}

.step.completed .step-number {
    border-color: var(--success);
    background: var(--success);
    color: white;
}

.step-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block; /* Force display */
}

/* Type Selection Cards */
.type-selection {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.type-card {
    padding: 2rem 1.5rem;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.type-card.selected {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-indigo-50), var(--hcis-white));
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.type-card .icon-wrapper {
    width: 64px;
    height: 64px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    transition: all 0.2s;
}

.type-card.selected .icon-wrapper {
    background: var(--primary);
    color: white;
}

.type-card h6 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text);
}

/* Date Selection List */
.selected-dates-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selected-date-item {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    border-radius: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s;
}

.selected-date-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-sm);
}

/* Review Summary */
.review-summary {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

/* =========================================
   VERTICAL ROSTER LIST STYLES
   ========================================= */
.vertical-roster-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: all 0.2s ease;
    gap: 1rem;
}

.vertical-roster-card:hover {
    box-shadow: var(--shadow);
    z-index: 10;
}

.vertical-roster-card.opacity-60 {
    opacity: 0.6;
}

.date-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 70px;
    padding-right: 1rem;
    border-right: 1px solid var(--border);
}

.content-col {
    flex: 1;
}

.actions-col {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.badge-status {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-800);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    text-transform: uppercase;
}

.add-shift-btn-dashed {
    width: 100%;
    height: 3rem;
    border: 2px dashed var(--border);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--text-muted);
    background: transparent;
    transition: all 0.2s;
}

.add-shift-btn-dashed:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05); /* Tint */
}

@media (max-width: 640px) {
    .vertical-roster-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .date-col {
        border-right: none;
        border-bottom: 1px solid var(--border);
        width: 100%;
        padding-right: 0;
        padding-bottom: 0.5rem;
        flex-direction: row;
        justify-content: space-between;
    }

    .actions-col {
        width: 100%;
        justify-content: flex-end;
        border-top: 1px solid var(--border);
        padding-top: 0.5rem;
    }
}

/* =========================================
   STITCH DESIGN SYSTEM - COMPREHENSIVE STYLES
   ========================================= */

/* Additional Utility Classes */
.grid {
    display: grid !important;
}
.grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
}
.grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
}
.grid-cols-7 {
    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
}
.grid-cols-12 {
    grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
}
.col-span-1 {
    grid-column: span 1 / span 1 !important;
}
.col-span-2 {
    grid-column: span 2 / span 2 !important;
}
.col-span-3 {
    grid-column: span 3 / span 3 !important;
}
.col-span-5 {
    grid-column: span 5 / span 5 !important;
}

.gap-3 {
    gap: 0.75rem !important;
}
.gap-6 {
    gap: 1.5rem !important;
}
.gap-8 {
    gap: 2rem !important;
}

.p-1 {
    padding: 0.25rem !important;
}
.p-2 {
    padding: 0.5rem !important;
}
.p-5 {
    padding: 1.25rem !important;
}
.p-6 {
    padding: 1.5rem !important;
}
.p-8 {
    padding: 2rem !important;
}
.px-3 {
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
}
.px-6 {
    padding-left: 1.5rem !important;
    padding-right: 1.5rem !important;
}
.py-3 {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}
.py-4 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.pl-1 {
    padding-left: 0.25rem !important;
}

.mb-1 {
    margin-bottom: 0.25rem !important;
}
.mb-2 {
    margin-bottom: 0.5rem !important;
}
.mb-4 {
    margin-bottom: 1rem !important;
}
.mb-6 {
    margin-bottom: 1.5rem !important;
}
.mt-auto {
    margin-top: auto !important;
}

.text-lg {
    font-size: 1.125rem !important;
    line-height: 1.75rem !important;
}
.text-xl {
    font-size: 1.25rem !important;
    line-height: 1.75rem !important;
}
.text-2xl {
    font-size: 1.5rem !important;
    line-height: 2rem !important;
}
.text-3xl {
    font-size: 1.875rem !important;
    line-height: 2.25rem !important;
}
.font-semibold {
    font-weight: 600 !important;
}
.font-extrabold {
    font-weight: 800 !important;
}
.uppercase {
    text-transform: uppercase !important;
}
.tracking-wider {
    letter-spacing: 0.05em !important;
}
.tracking-tight {
    letter-spacing: -0.025em !important;
}
.leading-tight {
    line-height: 1.25 !important;
}

.text-left {
    text-align: left !important;
}
.text-center {
    text-align: center !important;
}
.text-right {
    text-align: right !important;
}

.flex-1 {
    flex: 1 1 0% !important;
}
.flex-wrap {
    flex-wrap: wrap !important;
}
.items-start {
    align-items: flex-start !important;
}
.items-end {
    align-items: flex-end !important;
}
.justify-center {
    justify-content: center !important;
}
.justify-end {
    justify-content: flex-end !important;
}

.relative {
    position: relative !important;
}
.absolute {
    position: absolute !important;
}
.sticky {
    position: sticky !important;
}
.top-0 {
    top: 0 !important;
}
.z-10 {
    z-index: 10 !important;
}
.z-50 {
    z-index: 50 !important;
}

.overflow-hidden {
    overflow: hidden !important;
}
.overflow-x-auto {
    overflow-x: auto !important;
}

.transition-all {
    transition: all 0.2s ease !important;
}
.transition-colors {
    transition:
        color 0.2s,
        background-color 0.2s,
        border-color 0.2s !important;
}

.cursor-pointer {
    cursor: pointer !important;
}
.select-none {
    user-select: none !important;
}

.opacity-50 {
    opacity: 0.5 !important;
}
.opacity-60 {
    opacity: 0.6 !important;
}

.w-8 {
    width: 2rem !important;
}
.w-10 {
    width: 2.5rem !important;
}
.w-12 {
    width: 3rem !important;
}
.h-8 {
    height: 2rem !important;
}
.h-10 {
    height: 2.5rem !important;
}
.h-11 {
    height: 2.75rem !important;
}
.h-12 {
    height: 3rem !important;
}
.min-w-70 {
    min-width: 70px !important;
}

.hidden {
    display: none !important;
}

/* Color Extensions */
.bg-success {
    background-color: var(--success) !important;
}
.bg-warning {
    background-color: var(--warning) !important;
}
.bg-secondary {
    background-color: var(--secondary) !important;
}

.bg-blue-50 {
    background-color: #eff6ff !important;
}
.bg-purple-50 {
    background-color: #faf5ff !important;
}
.bg-orange-50 {
    background-color: #fff7ed !important;
}
.bg-green-50 {
    background-color: var(--hcis-green-50) !important;
}
.bg-yellow-50 {
    background-color: var(--hcis-yellow-50) !important;
}
.bg-red-50 {
    background-color: var(--hcis-red-50) !important;
}
.bg-gray-50 {
    background-color: #f9fafb !important;
}
.bg-gray-100 {
    background-color: #f3f4f6 !important;
}

.text-green-600 {
    color: var(--hcis-green-600) !important;
}
.text-green-700 {
    color: var(--hcis-green-700) !important;
}
.text-yellow-700 {
    color: var(--hcis-yellow-700) !important;
}
.text-red-600 {
    color: var(--hcis-red-600) !important;
}
.text-red-700 {
    color: var(--hcis-red-700) !important;
}
.text-purple-600 {
    color: var(--hcis-purple-600) !important;
}
.text-orange-600 {
    color: var(--hcis-orange-600) !important;
}
.text-gray-400 {
    color: #9ca3af !important;
}
.text-gray-500 {
    color: #6b7280 !important;
}

.border-green-200 {
    border-color: var(--hcis-green-200) !important;
}
.border-yellow-200 {
    border-color: var(--hcis-yellow-200) !important;
}
.border-red-200 {
    border-color: var(--hcis-red-200) !important;
}
.border-primary {
    border-color: var(--primary) !important;
}

.ring-1 {
    box-shadow: 0 0 0 1px !important;
}
.ring-primary-light {
    --tw-ring-color: var(--primary-light) !important;
    box-shadow: 0 0 0 2px var(--primary-light) !important;
}

/* =========================================
   STITCH CARD COMPONENTS
   ========================================= */
/* Summary Cards Grid - Responsive */
.summary-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 991px) {
    .summary-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 575px) {
    .summary-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* Date Navigation Responsive */
.date-nav-wrapper {
    display: flex;
    flex-direction: row !important;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.date-nav-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-label {
    font-size: 0.9375rem;
}

.add-shift-btn {
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.3);
}

@media (max-width: 640px) {
    .date-nav-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.75rem !important;
    }

    .date-nav-left {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .date-nav-left > div:first-child {
        width: 100%;
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
        width: 100%;
        border-radius: 12px;
    }

    .date-nav-wrapper .form-control {
        height: 40px;
        border-radius: 12px;
    }

    .date-nav-wrapper .add-shift-btn {
        height: 42px;
        font-size: 0.9rem;
    }

    .date-nav-label {
        font-size: 0.8125rem;
    }
}

@media (max-width: 400px) {
    .today-btn {
        display: none !important;
    }

    .date-nav-right {
        grid-template-columns: 1fr;
    }
}

.stitch-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-radius: var(--radius);
    background: var(--surface);
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
}

/* Roster List Container */
.roster-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

@media (max-width: 575px) {
    .roster-list {
        gap: 0.5rem;
    }
}

.stitch-card-icon {
    padding: 0.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stitch-card-icon.blue {
    background: #eff6ff;
    color: var(--primary);
}
.stitch-card-icon.purple {
    background: #faf5ff;
    color: var(--hcis-purple-600);
}
.stitch-card-icon.orange {
    background: #fff7ed;
    color: var(--hcis-orange-600);
}
.stitch-card-icon.green {
    background: var(--hcis-green-50);
    color: var(--success);
}

/* =========================================
   STITCH ROSTER LIST
   ========================================= */
.stitch-roster-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s ease;
}

.stitch-roster-item:hover {
    box-shadow: var(--shadow);
}

.stitch-roster-item.today {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
}

.stitch-roster-item.weekend {
    background: var(--surface-soft);
    opacity: 0.7;
}

/* Roster Content Layout */
.roster-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.shift-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.shift-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.shift-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.shift-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.shift-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-muted);
    font-size: 0.8125rem;
    font-weight: 500;
}

.shift-time,
.shift-location {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.roster-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Mobile Roster Styles */
@media (max-width: 640px) {
    .stitch-roster-item {
        flex-direction: column;
        align-items: stretch;
        padding: 0.75rem;
        gap: 0.75rem;
    }

    .stitch-date-box {
        display: none;
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 0.5rem;
        padding: 0 0 0.5rem 0;
        border-right: none;
        border-bottom: 1px solid var(--border);
        min-width: unset;
    }

    .stitch-date-day {
        font-size: 0.625rem;
    }

    .stitch-date-num {
        font-size: 1.25rem;
    }

    .roster-content {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .shift-info {
        flex: 1;
    }

    .stitch-shift-indicator {
        width: 4px;
        height: 100%;
        min-height: 36px;
    }

    .shift-name {
        font-size: 0.9375rem;
    }

    .shift-meta {
        font-size: 0.75rem;
        gap: 0.75rem;
    }

    .hide-mobile {
        display: none !important;
    }

    .roster-actions {
        justify-content: flex-end;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    .roster-actions .stitch-action-btn {
        width: 32px;
        height: 32px;
    }
}

.stitch-date-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    padding-right: 1rem;
    /* border-right: 1px solid var(--border); */
}

.stitch-date-day {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stitch-date-day.weekend {
    color: var(--danger);
}

.stitch-date-num {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
}

.stitch-shift-indicator {
    width: 6px;
    height: 48px;
    border-radius: 9999px;
}

.stitch-shift-indicator.green {
    background: var(--success);
}
.stitch-shift-indicator.yellow {
    background: var(--warning);
}
.stitch-shift-indicator.blue {
    background: var(--primary);
}
.stitch-shift-indicator.purple {
    background: var(--hcis-purple-600);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.status-pill-wrap {
    white-space: normal;
    line-height: 1.1;
    text-align: center;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
}

.status-pill.badge {
    border: none;
}

.status-pill.bg-danger-subtle {
    background: #fff1f2 !important;
    color: var(--hcis-rose-600) !important;
    border-color: #fecdd3 !important;
}

.status-pill.bg-success-subtle {
    background: #ecfdf3 !important;
    color: var(--hcis-green-700) !important;
    border-color: var(--hcis-green-200) !important;
}

.shift-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shift-time-pill {
    font-size: 0.55rem;
    padding: 0.2rem 0.45rem;
    border-radius: 0.4rem;
    box-shadow: none;
}

.status-subtext {
    font-size: 0.6rem;
    color: var(--hcis-red-600);
    line-height: 1.1;
    text-align: center;
    max-width: 100%;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-week-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
}

.mobile-day-card {
    min-height: 92px;
}

.mobile-day-card .status-pill {
    font-size: 0.5rem;
    padding: 0.15rem 0.35rem;
}

.mobile-day-card .shift-badge {
    font-size: 0.65rem;
}

@media (max-width: 480px) {
    .mobile-week-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .mobile-day-card {
        min-height: 86px;
        padding: 0.4rem !important;
    }
}

@media (max-width: 640px) {
    .mobile-member-card {
        border-radius: 16px;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }

    .mobile-member-card.is-expanded {
        border-color: #dbeafe;
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.12);
    }

    .mobile-member-header {
        padding: 0.75rem !important;
        gap: 0.75rem !important;
    }

    .mobile-member-header.is-expanded {
        background: var(--hcis-slate-50);
    }

    .mobile-member-avatar {
        display: none !important;
    }

    .mobile-member-info h6 {
        font-size: 0.95rem;
        line-height: 1.2;
    }

    .mobile-member-info {
        flex: 1;
        min-width: 0;
    }

    .mobile-member-info .text-xs {
        font-size: 0.7rem;
        line-height: 1.2;
        margin-top: 0.15rem;
    }

    .mobile-member-divider {
        height: 1px;
        width: 100%;
        background: #e5e7eb;
        margin: 0.45rem 0;
    }

    .mobile-member-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.6rem;
        font-weight: 700;
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 9999px;
        padding: 0.15rem 0.5rem;
        width: fit-content;
    }

    .mobile-toggle-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 9999px;
        background: var(--hcis-slate-50);
        border: 1px solid var(--hcis-slate-200);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }

    .mobile-toggle-btn i {
        font-size: 1.05rem;
        color: var(--hcis-slate-600);
        transition: transform 0.2s ease;
    }

    .mobile-member-card.is-expanded .mobile-toggle-btn i {
        transform: rotate(180deg);
    }

    .mobile-member-card + .mobile-member-card {
        margin-top: 0.75rem;
    }

    .mobile-week-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.6rem;
    }

    .mobile-day-card {
        min-height: 110px;
        padding: 0.6rem !important;
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    }

    .mobile-day-card .text-xxs {
        font-size: 0.6rem;
    }

    .mobile-day-card .shift-badge {
        font-size: 0.7rem;
    }

    .mobile-day-card .status-subtext {
        font-size: 0.55rem;
        line-height: 1.15;
    }
}

/* Shift Source Badge */
.shift-source {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    /* padding: 0.125rem 0.5rem; */
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: capitalize;
}

.shift-source.source-permanen {
    background: #dbeafe;
    color: #1e40af;
}

.shift-source.source-sementara {
    background: var(--hcis-orange-100);
    color: #c2410c;
}

.no-permanent-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.35rem 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid #fed7aa;
    background: #fff7ed;
    color: #c2410c;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    white-space: nowrap;
    line-height: 1;
}

/* Stitch Action Buttons */
.stitch-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stitch-action-btn:hover {
    border-color: var(--primary-light);
    background: #eff6ff;
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stitch-action-btn.danger {
    border-color: var(--hcis-red-200);
    background: #fddede;
    color: var(--danger);
}

.stitch-action-btn.secondary {
    border-color: #e5e7eb;
    background: #f9fafb;
    color: var(--text-muted);
}

.stitch-action-btn.danger:hover {
    border-color: var(--hcis-red-200);
    background: #fdd0d0;
    color: var(--danger);
}

.stitch-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stitch-badge.approved {
    background: var(--hcis-green-100);
    color: #166534;
}

.stitch-badge.pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.stitch-badge.rejected {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

.stitch-badge.cancelled {
    background: #e5e7eb;
    color: #374151;
}

/* =========================================
   STITCH FILTER & TABLE
   ========================================= */
.stitch-filter {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
}

.stitch-filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.375rem;
}

.stitch-input {
    width: 100%;
    height: 2.75rem;
    padding: 0 0.75rem;
    background: var(--surface-soft);
    border: 1px solid transparent;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: var(--text);
    transition: all 0.2s;
}

.stitch-input:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.stitch-input::placeholder {
    color: var(--text-muted);
}

.stitch-table {
    width: 100%;
    text-align: left;
    border-collapse: collapse;
}

.stitch-table thead tr {
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.stitch-table th {
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stitch-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s;
}

.stitch-table tbody tr:hover {
    background: var(--surface-soft);
}

.stitch-table td {
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: var(--text);
}

/* =========================================
   ELEMENT PLUS DATE PICKER CUSTOMIZATION
   ========================================= */
.date-picker-wrapper {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
}

.el-date-picker-full {
    width: 100% !important;
}

.el-date-picker-full .el-input__wrapper {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-date-picker-full .el-input__wrapper:hover,
.el-date-picker-full .el-input__wrapper.is-focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-date-picker-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-date-picker-full .el-input__prefix,
.el-date-picker-full .el-input__suffix {
    color: var(--text-muted);
}

/* Popper/Dropdown Styles */
.el-picker__popper {
    border-radius: var(--radius) !important;
    border: 1px solid var(--border) !important;
    box-shadow: var(--shadow-lg) !important;
}

.el-picker-panel {
    border: none !important;
}

.el-date-picker__header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border);
}

.el-date-picker__header-label {
    font-weight: 600;
    color: var(--text);
}

.el-picker-panel__icon-btn {
    color: var(--text-muted);
}

.el-picker-panel__icon-btn:hover {
    color: var(--primary);
}

.el-date-table th {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
}

.el-date-table td.available:hover {
    color: var(--primary);
}

.el-date-table td.today .el-date-table-cell__text {
    color: var(--primary);
    font-weight: 700;
}

.el-date-table td.current:not(.disabled) .el-date-table-cell__text,
.el-date-table td.selected .el-date-table-cell__text {
    background-color: var(--primary) !important;
    color: white !important;
    border-radius: 50%;
}

.el-date-table td.disabled .el-date-table-cell__text {
    color: var(--text-muted);
    opacity: 0.4;
}

/* =========================================
   ELEMENT PLUS SELECT CUSTOMIZATION
   ========================================= */
.el-select-full {
    width: 100% !important;
}

.el-select-full .el-input__wrapper {
    padding: 0.5rem 0.875rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-select-full .el-input__wrapper:hover,
.el-select-full .el-input.is-focus .el-input__wrapper {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-select-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-select-full .el-input__suffix {
    color: var(--text-muted);
}

/* High z-index for select popper (teleported to body) */
.el-select-popper-high {
    z-index: 99999 !important;
}

.el-select-popper-high .el-select-dropdown__item {
    font-size: 0.875rem;
    padding: 0.625rem 1rem;
}

.el-select-popper-high .el-select-dropdown__item.selected {
    color: var(--primary);
    font-weight: 600;
}

.el-select-popper-high .el-select-dropdown__item:hover {
    background: var(--surface-soft);
}

/* High z-index for date picker popper (teleported to body) */
.el-date-picker-popper-high {
    z-index: 99999 !important;
}

/* =========================================
   STITCH WIZARD STEPPER
   ========================================= */
.stitch-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    max-width: 600px;
    margin: 0 auto 2rem;
}

.stitch-stepper::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--border);
    transform: translateY(-50%);
    z-index: 0;
}

.stitch-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.stitch-step-circle {
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    background: var(--surface);
    border: 2px solid var(--border);
    color: var(--text-muted);
    box-shadow: 0 0 0 4px var(--surface);
}

.stitch-step.completed .stitch-step-circle {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.stitch-step.active .stitch-step-circle {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow:
        0 0 0 4px var(--surface),
        0 0 0 6px rgba(99, 102, 241, 0.2);
}

.stitch-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
}

.stitch-step.completed .stitch-step-label {
    color: var(--success);
}

.stitch-step.active .stitch-step-label {
    color: var(--primary);
    font-weight: 700;
}

/* =========================================
   STITCH TYPE CARDS (Wizard Step 1)
   ========================================= */
.stitch-type-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
}

.stitch-type-card {
    padding: 2rem 1.5rem;
    border-radius: 1rem;
    border: 2px solid var(--border);
    background: var(--surface);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stitch-type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stitch-type-card.selected {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-indigo-50), var(--surface));
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.stitch-type-icon {
    width: 5rem;
    height: 5rem;
    border-radius: 9999px;
    background: var(--surface-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    transition: all 0.2s;
}

.stitch-type-card.selected .stitch-type-icon {
    background: var(--primary);
    color: white;
}

.stitch-type-card .stitch-type-icon i {
    font-size: 2.5rem;
}

/* =========================================
   STITCH DATE CHIPS (Wizard Step 2)
   ========================================= */
/* Dates Summary Section */
.dates-summary-section {
    margin-top: 1rem;
}

.dates-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    gap: 0.5rem;
}

.dates-count {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text);
}

.clear-dates-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--danger);
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-dates-btn:hover {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
}

.clear-dates-btn i {
    font-size: 0.625rem;
}

.stitch-date-chips {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    padding: 0.25rem;
}

.stitch-date-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.5rem;
    background: var(--surface);
    border: 1px solid var(--primary-light);
    border-radius: 0.375rem;
    color: var(--primary);
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    white-space: nowrap;
    overflow: hidden;
}

.stitch-date-chip i {
    font-size: 0.625rem;
    flex-shrink: 0;
}

.stitch-date-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.stitch-date-chip button {
    background: transparent;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.125rem;
    border-radius: 9999px;
    transition: all 0.2s;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.stitch-date-chip button:hover {
    background: var(--hcis-red-100);
    color: var(--danger);
}

/* Mobile: Single column for date chips */
@media (max-width: 480px) {
    .stitch-date-chips {
        grid-template-columns: 1fr;
        max-height: 180px;
    }

    .stitch-type-card .stitch-type-icon i {
        font-size: 1.5rem;
    }

    .stitch-date-chip {
        font-size: 0.6875rem;
        padding: 0.3rem 0.4rem;
    }
}

/* =========================================
   STITCH MODAL ENHANCEMENTS
   ========================================= */
.stitch-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stitch-modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text);
}

.stitch-modal-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.stitch-modal-footer {
    padding: 1rem 1.5rem;
    background: var(--surface-soft);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

.stitch-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.stitch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    font-weight: 700;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.stitch-btn.primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
}
/* 
.stitch-btn.primary:hover {
    background: var(--primary-dark);
} */

.stitch-btn.secondary {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.stitch-btn.secondary:hover {
    background: var(--surface-soft);
    color: var(--text);
}

.stitch-btn.danger {
    background: var(--danger);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
}

.stitch-btn.danger:hover {
    background: var(--danger-dark);
    color: black;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    transition: all 0.2s;
    /* border: 0.1px solid var(--text-muted); */
}

/* =========================================
   RESPONSIVE OVERRIDES - TABLET (768px+)
   ========================================= */
@media (min-width: 768px) {
    .md\:grid-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .md\:grid-cols-12 {
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
    }
    .md\:col-span-1 {
        grid-column: span 1 / span 1 !important;
    }
    .md\:col-span-2 {
        grid-column: span 2 / span 2 !important;
    }
    .md\:col-span-5 {
        grid-column: span 5 / span 5 !important;
    }
    .md\:flex {
        display: flex !important;
    }
}

/* =========================================
   RESPONSIVE OVERRIDES - MOBILE (< 768px)
   ========================================= */
@media (max-width: 767px) {
    /* Tab Content Padding */
    .p-6 {
        padding: 1rem !important;
    }

    /* Summary Cards - Single Column */
    .grid.grid-cols-3 {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .stitch-card {
        padding: 1rem;
        gap: 0.25rem;
    }

    .stitch-card .text-2xl {
        font-size: 1.5rem !important;
    }

    .stitch-card-icon {
        width: 36px;
        height: 36px;
    }

    /* Date Navigation - Stack on Mobile */
    .stitch-card.sticky {
        flex-direction: row !important;
        gap: 0.75rem !important;
        padding: 0.75rem !important;
    }

    .stitch-card.sticky > div:first-child {
        flex-wrap: wrap;
        width: 100%;
        justify-content: space-between;
    }

    .stitch-card.sticky .text-lg {
        font-size: 0.875rem !important;
    }

    .stitch-card.sticky .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Hide secondary button on mobile */
    .stitch-btn.secondary.hidden.sm\:block {
        display: none !important;
    }

    /* Action Buttons - Smaller */
    .stitch-action-btn {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.875rem !important;
    }

    /* Roster Items - Vertical Layout */
    .stitch-roster-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.875rem;
    }

    .stitch-date-box {
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 0.75rem;
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding: 0 0 0.75rem 0;
        min-width: unset;
    }

    .stitch-date-day {
        font-size: 0.65rem;
    }

    .stitch-date-num {
        font-size: 1.25rem !important;
    }

    .stitch-roster-item .flex.items-center.gap-3:not(.stitch-date-box) {
        flex: 1;
    }

    .stitch-roster-item .flex.items-center.gap-2:last-child {
        justify-content: flex-end;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    .stitch-shift-indicator {
        width: 4px;
        height: 32px;
    }

    .stitch-roster-item .text-lg {
        font-size: 0.9375rem !important;
    }

    .stitch-roster-item .text-sm {
        font-size: 0.75rem !important;
    }

    /* Filter Section - Stack on Mobile */
    .stitch-filter {
        padding: 0.875rem !important;
    }

    .stitch-filter .grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.75rem !important;
    }

    .stitch-filter input,
    .stitch-filter select {
        width: 100% !important;
    }

    /* Table - Card-like rows on mobile */
    .stitch-table {
        display: block;
    }

    .stitch-table thead {
        display: none;
    }

    .stitch-table tbody {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .stitch-table tr {
        display: flex;
        flex-direction: column;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.75rem;
    }

    .stitch-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.25rem 0;
        border: none;
    }

    .stitch-table td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .stitch-table td.text-right {
        justify-content: flex-end;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    /* Wizard Steps - Numbers only on mobile */
    .stitch-stepper {
        gap: 0.25rem;
    }

    .stitch-step-label {
        display: none;
    }

    .stitch-step-circle {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }

    .stitch-stepper::before {
        top: 16px;
    }

    /* Type Selection - Single Column */
    .stitch-type-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .stitch-type-card {
        padding: 1rem;
    }

    .stitch-type-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }

    /* Date Chips - Responsive */
    .stitch-date-chips {
        gap: 0.5rem;
    }

    .stitch-date-chip {
        padding: 0.375rem 0.625rem;
        font-size: 0.75rem;
    }

    /* Modal Footer - Stack buttons on mobile */
    .stitch-modal-footer {
        flex-direction: column-reverse;
        gap: 0.5rem;
    }

    .stitch-modal-footer .stitch-btn {
        width: 100%;
        justify-content: center;
        margin: 0 !important;
    }

    /* History Page Header */
    .flex.flex-wrap.justify-between.items-end.gap-4.mb-6 {
        flex-direction: column;
        align-items: stretch !important;
    }

    .flex.flex-wrap.justify-between.items-end.gap-4.mb-6 .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Text sizes on mobile */
    .text-2xl {
        font-size: 1.25rem !important;
    }

    .text-xl {
        font-size: 1rem !important;
    }

    .text-lg {
        font-size: 0.9375rem !important;
    }

    /* Gaps on mobile */
    .gap-4 {
        gap: 0.75rem !important;
    }
    .gap-6 {
        gap: 1rem !important;
    }
    .mb-6 {
        margin-bottom: 1rem !important;
    }
    .mb-4 {
        margin-bottom: 0.75rem !important;
    }
}

/* =========================================
   RESPONSIVE OVERRIDES - SMALL MOBILE (< 480px)
   ========================================= */
@media (max-width: 479px) {
    .stitch-card {
        padding: 0.75rem;
        flex-direction: column;
    }

    .stitch-roster-item {
        padding: 0.75rem;
    }

    .stitch-date-num {
        font-size: 1.125rem !important;
    }

    .stitch-badge {
        font-size: 0.5rem;
        padding: 0.1rem 0.375rem;
    }

    .stitch-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
    }

    .stitch-action-btn {
        width: 28px !important;
        height: 28px !important;
    }
}

/* =========================================
   DETAIL MODAL - TWO COLUMN LAYOUT
   ========================================= */
.detail-modal-content {
    padding: 0;
}

.detail-modal-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    min-height: 420px;
}

.detail-left-column {
    background: var(--surface-soft);
    padding: 1.25rem;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.detail-right-column {
    padding: 0 1.5rem 0 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow-y: auto;
    max-height: 500px;
}

/* Transaction Card - No Transaksi Highlight */
.detail-transaksi-card {
    text-align: center;
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
}

.detail-transaksi-number {
    font-size: 1.25rem;
    font-weight: 800;
    color: white;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}

.detail-transaksi-type {
    margin-top: 0.5rem;
}

.type-badge {
    display: inline-block;
    padding: 0.375rem 0.875rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    backdrop-filter: blur(4px);
}

/* Status Section */
.detail-status-section {
    text-align: center;
}

.detail-status-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}

.detail-status-badge {
    display: inline-block;
    padding: 0.625rem 2rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-status-badge.approved {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.detail-status-badge.pending {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.detail-status-badge.rejected {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.detail-status-date {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

.detail-status-date i {
    font-size: 0.875rem;
}

/* Approval Section */
.detail-approval-section {
    background: white;
    border-radius: var(--radius);
    padding: 1rem;
    border: 1px solid var(--border);
}

.detail-section-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
    display: inline-block;
}

.detail-section-title i {
    color: var(--primary);
    margin-right: 0.25rem;
}

.detail-approval-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-approval-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.detail-approval-indicator {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.detail-approval-indicator.approved {
    background: var(--success);
    color: white;
}

.detail-approval-indicator.rejected {
    background: var(--danger);
    color: white;
}

.detail-approval-indicator.pending {
    background: var(--border);
    color: var(--text-muted);
}

.detail-approval-info {
    flex: 1;
    min-width: 0;
}

.detail-approval-name {
    font-size: 0.875rem;
    font-weight: 600;
}

.detail-approval-name.text-success {
    color: var(--success);
}

.detail-approval-name.text-danger {
    color: var(--danger);
}

.detail-approval-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.detail-approval-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--success);
}

/* Right Column Info Section */
.detail-info-section {
    background: white;
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.detail-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.detail-info-row:first-child {
    padding-top: 0;
}

.detail-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.detail-info-label {
    font-size: 0.8125rem;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 1rem;
}

.detail-info-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--dark);
    text-align: right;
}

.detail-shift-date {
    font-weight: 700;
    color: var(--dark);
    font-size: 0.9375rem;
}

.detail-shift-name {
    color: var(--primary);
    font-size: 0.8125rem;
    font-weight: 500;
}

.detail-shift-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    margin-top: 0.25rem;
}

.detail-alasan {
    font-style: italic;
    max-width: 220px;
    word-wrap: break-word;
    color: var(--text);
}

/* Detail Shift Card - Per-date card styling */
.detail-shift-card {
    background: white;
    border-radius: var(--radius);
    padding: 0.875rem 1rem;
    border: 1px solid var(--border);
    border-left-width: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.detail-shift-card:hover {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.detail-shift-card.border-success {
    border-left-color: var(--success);
}

.detail-shift-card.border-danger {
    border-left-color: var(--danger);
}

.detail-shift-card.border-warning {
    border-left-color: var(--warning);
}

/* Status Text Colors */
.status-text-approved {
    color: var(--success);
    font-weight: 600;
}

.status-text-pending {
    color: var(--warning);
    font-weight: 600;
}

.status-text-rejected {
    color: var(--danger);
    font-weight: 600;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .detail-modal-grid {
        grid-template-columns: 1fr;
    }

    .detail-left-column {
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .detail-right-column {
        max-height: none;
        padding: 0;
        margin-top: 1rem;
    }

    .detail-info-row {
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-info-value {
        text-align: left;
    }

    .detail-shift-time {
        justify-content: flex-start;
    }

    .detail-alasan {
        max-width: 100%;
    }
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Fade In Animation */
.fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
/* STITCH TABLE (UserShift Style + Premium) */
.stitch-table-wrapper {
    overflow-x: auto;
    width: 100%;
    /* border: 1px solid var(--border); */
    border-radius: var(--radius);
    background: white;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.stitch-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.stitch-table th,
.stitch-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.day-col,
.day-cell {
    width: 120px;
    min-width: 120px;
}

.stitch-table th {
    background: var(--surface-soft);
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 1px solid var(--border-dark, var(--hcis-slate-200));
}

.sticky-col {
    position: sticky;
    left: 0;
    background: white;
    z-index: 10;
    border-right: 1px solid var(--border);
    transition: all 0.2s;
}

.stitch-table th.sticky-col {
    z-index: 30; /* Higher than normal th and body sticky col */
    /* background: var(--surface-soft); */
}

.member-row:hover .sticky-col {
    background: var(--surface-soft);
}

@media (max-width: 1200px) {
    .day-col,
    .day-cell {
        width: 105px;
        min-width: 105px;
    }

    .stitch-table th,
    .stitch-table td {
        padding: 0.75rem;
    }
}

@media (max-width: 1024px) {
    .day-col,
    .day-cell {
        width: 96px;
        min-width: 96px;
    }
}

.member-row:hover td {
    background: var(--surface-soft);
}

.member-row:last-child td {
    border-bottom: none;
}

/* Pending Approval Badge in Calendar Cells */
.pending-approval-badge {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.3);
    font-size: 10px;
    font-weight: 600;
    color: var(--hcis-amber-600);
    line-height: 1;
    cursor: default;
    transition: all 0.2s ease;
}

.pending-approval-badge:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.5);
}

.pending-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-amber-500);
    animation: pendingPulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

.pending-text {
    white-space: nowrap;
}

@keyframes pendingPulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.4;
        transform: scale(0.8);
    }
}

/* Shift Cell Interactions */
.shift-cell-hover .add-overlay {
    opacity: 0;
    transform: translate(-50%, -40%);
    transition: all 0.2s ease;
}

.shift-cell-hover:hover .add-overlay {
    opacity: 1;
    transform: translate(-50%, -50%);
}

.shift-cell-hover:hover .empty-placeholder {
    opacity: 0;
}

.shift-content-wrapper {
    border: 1px solid transparent;
}

.shift-cell-hover:hover .shift-content-wrapper {
    background: white;
    border-color: var(--primary-light);
    box-shadow: var(--shadow-sm);
}

/* COLORFUL SHIFT BADGES (userShift.vue style) */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
    color: #5b21b6;
    border: none !important;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    border: none !important;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border) !important;
}

/* COLORFUL SHIFT BADGES (userShift.vue style) */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
    color: #5b21b6;
    border: none !important;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    border: none !important;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border) !important;
}
</style>
