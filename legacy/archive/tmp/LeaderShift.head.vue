<template>
    <div class="cuti-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Leader Shift Management"
            subtitle="Kelola jadwal shift anggota tim Anda"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />

        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab: Kalender -->
                    <template #kalender>
                        <div
                            class="p-4 rounded-4"
                            style="background: var(--surface-soft)"
                        >
                            <!-- 1. Approver & Policy Section (SelfShift Style) -->
                            <div
                                class="stitch-card p-2 mb-4 border-0 shadow-sm rounded-4 overflow-hidden bg-white"
                            >
                                <div class="row g-0">
                                    <!-- Left Section: Approver Profile -->
                                    <div
                                        class="col-12 col-md-5 p-3 d-flex flex-column justify-content-center position-relative"
                                    >
                                        <div
                                            class="d-flex align-items-center gap-3"
                                        >
                                            <!-- Icon -->
                                            <div
                                                class="position-relative flex-shrink-0"
                                            >
                                                <div
                                                    class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                                    :style="`width: 48px; height: 48px; background: ${approverInfo ? '#eff6ff' : '#fff7ed'}; color: ${approverInfo ? '#3b82f6' : '#f59e0b'};`"
                                                >
                                                    <i
                                                        :class="
                                                            approverInfo
                                                                ? 'bi bi-person-check-fill'
                                                                : 'bi bi-exclamation-triangle-fill'
                                                        "
                                                        style="
                                                            font-size: 1.25rem;
                                                        "
                                                    ></i>
                                                </div>
                                                <div
                                                    v-if="approverInfo"
                                                    class="position-absolute bg-white rounded-circle p-0.5"
                                                    style="
                                                        bottom: -1px;
                                                        right: -1px;
                                                    "
                                                >
                                                    <i
                                                        class="bi bi-check-circle-fill text-success"
                                                        style="font-size: 12px"
                                                    ></i>
                                                </div>
                                            </div>

                                            <!-- Info -->
                                            <div
                                                class="d-flex flex-column"
                                                style="min-width: 0"
                                            >
                                                <span
                                                    class="text-uppercase text-muted fw-bold mb-0.5"
                                                    style="
                                                        font-size: 0.6rem;
                                                        letter-spacing: 0.5px;
                                                    "
                                                >
                                                    Approver (Backdate)
                                                </span>
                                                <div
                                                    v-if="approverInfo"
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
                                                </div>
                                                <div v-else class="lh-1">
                                                    <h6
                                                        class="text-dark fw-bold mb-0.5"
                                                        style="
                                                            font-size: 0.95rem;
                                                        "
                                                    >
                                                        Belum Ditentukan
                                                    </h6>
                                                    <small
                                                        class="text-warning fw-medium"
                                                        style="
                                                            font-size: 0.75rem;
                                                        "
                                                        >Hubungi HRD</small
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Section: Info/Legend -->
                                    <div
                                        class="col-12 col-md-7 p-2 rounded-3 bg-light bg-opacity-50"
                                    >
                                        <div
                                            class="row g-2 h-100 align-items-center"
                                        >
                                            <!-- Backdate Legend -->
                                            <div class="col-6">
                                                <div
                                                    class="p-2 rounded-3 bg-white border shadow-sm text-center h-100 d-flex flex-column justify-content-center transition-hover"
                                                >
                                                    <div class="mb-1">
                                                        <i
                                                            class="bi bi-clock-history text-primary bg-primary-subtle p-1.5 rounded-circle"
                                                            style="
                                                                font-size: 0.85rem;
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div
                                                        class="d-flex flex-column gap-0.5"
                                                    >
                                                        <div
                                                            class="text-muted fw-bold"
                                                            style="
                                                                font-size: 0.6rem;
                                                                text-transform: uppercase;
                                                            "
                                                        >
                                                            Backdate
                                                        </div>
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5 bg-warning-subtle text-warning-emphasis border-warning-subtle"
                                                            style="
                                                                font-size: 0.6rem;
                                                                padding: 0.25em
                                                                    0.5em;
                                                            "
                                                        >
                                                            Butuh Approval
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Future Legend -->
                                            <div class="col-6">
                                                <div
                                                    class="p-2 rounded-3 bg-white border shadow-sm text-center h-100 d-flex flex-column justify-content-center transition-hover"
                                                >
                                                    <div class="mb-1">
                                                        <i
                                                            class="bi bi-lightning-charge text-success bg-success-subtle p-1.5 rounded-circle"
                                                            style="
                                                                font-size: 0.85rem;
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div
                                                        class="d-flex flex-column gap-0.5"
                                                    >
                                                        <div
                                                            class="text-muted fw-bold"
                                                            style="
                                                                font-size: 0.6rem;
                                                                text-transform: uppercase;
                                                            "
                                                        >
                                                            Hari H / Depan
                                                        </div>
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5 bg-success-subtle text-success-emphasis border-success-subtle"
                                                            style="
                                                                font-size: 0.6rem;
                                                                padding: 0.25em
                                                                    0.5em;
                                                            "
                                                        >
                                                            Langsung Aktif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Control Panel & Week Navigation (Stitch Style) -->
                            <div
                                class="date-nav-wrapper stitch-card mb-4 sticky top-0 z-10"
                            >
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
                                        <div
                                            class="position-relative"
                                            style="width: 32px; height: 32px"
                                        >
                                            <button
                                                class="stitch-action-btn w-100 h-100 d-flex align-items-center justify-content-center"
                                            >
                                                <i
                                                    class="bi bi-calendar-date"
                                                ></i>
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

                                    <div
                                        class="d-flex align-items-center gap-2"
                                    >
                                        <!-- <i class="bi bi-calendar-week text-muted"></i> -->
                                        <div class="d-flex flex-column lh-1">
                                            <span
                                                class="text-dark fw-bold date-nav-label"
                                            >
                                                {{ weekRangeDisplay }}
                                            </span>
                                            <span
                                                class="text-muted text-xs ms-1"
                                                >{{ monthYearDisplay }}</span
                                            >
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
                                        style="
                                            min-width: 200px;
                                            max-width: 300px;
                                            width: 100%;
                                        "
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
                                    <button
                                        @click="openBulkModal"
                                        class="stitch-btn primary add-shift-btn"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                        <span class="add-shift-text"
                                            >Bulk Assign</span
                                        >
                                    </button>
                                </div>
                            </div>

                            <!-- 3. Calendar Grid (Table Version) -->
                            <div
                                class="stitch-card p-0 border-0 shadow-sm overflow-hidden bg-white"
                            >
                                <!-- Loading State -->
                                <div
                                    v-if="loading.schedule || loading.team"
                                    class="p-5 text-center loading-state border-0 shadow-none"
                                >
                                    <div class="loading-spinner">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <h4 class="loading-title">
                                        Memuat Jadwal...
                                    </h4>
                                    <p class="loading-description">
                                        Sedang mengambil data shift tim
                                    </p>
                                </div>

                                <div v-else>
                                    <!-- TABLE VIEW (Desktop Default) -->
                                    <div
                                        v-if="viewMode === 'table'"
                                        class="d-none d-md-block"
                                    >
                                        <div class="stitch-table-wrapper">
                                            <table class="stitch-table">
                                                <thead>
                                                    <tr>
                                                        <th
                                                            class="sticky-col text-start"
                                                            style="
                                                                width: 250px;
                                                                min-width: 250px;
                                                            "
                                                        >
                                                            <div
                                                                class="d-flex align-items-center gap-2"
                                                            >
                                                                <i
                                                                    class="bi bi-people text-primary"
                                                                ></i>
                                                                <span
                                                                    >Anggota Tim
                                                                    ({{
                                                                        paginatedMembers.length
                                                                    }}/{{ schedulePagination.total }})</span
                                                                >
                                                            </div>
                                                        </th>
                                                        <th
                                                            v-for="day in weekDays"
                                                            :key="day.date"
                                                            class="text-center"
                                                            :class="{
                                                                'bg-primary-subtle':
                                                                    day.isToday,
                                                            }"
                                                            style="
                                                                min-width: 140px;
                                                            "
                                                        >
                                                            <div
                                                                class="d-flex flex-column align-items-center"
                                                            >
                                                                <span
                                                                    class="text-xs fw-bold text-muted text-uppercase mb-1"
                                                                    >{{
                                                                        day.name
                                                                    }}</span
                                                                >
                                                                <div
                                                                    class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-sm"
                                                                    :class="
                                                                        day.isToday
                                                                            ? 'bg-primary text-white shadow-sm'
                                                                            : 'text-dark'
                                                                    "
                                                                    style="
                                                                        width: 32px;
                                                                        height: 32px;
                                                                    "
                                                                >
                                                                    {{
                                                                        day.day
                                                                    }}
                                                                </div>
                                                                <span
                                                                    class="text-xxs text-muted mt-1"
                                                                    style="
                                                                        font-size: 0.65rem;
                                                                    "
                                                                    >{{
                                                                        day.month
                                                                    }}
                                                                    {{
                                                                        day.year
                                                                    }}</span
                                                                >
                                                            </div>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-if="
                                                            paginatedMembers.length ===
                                                            0
                                                        "
                                                    >
                                                        <td
                                                            :colspan="
                                                                weekDays.length +
                                                                1
                                                            "
                                                            class="text-center p-5 text-muted"
                                                        >
                                                            <div
                                                                class="mb-2 opacity-50"
                                                            >
                                                                <i
                                                                    class="bi bi-people fs-1"
                                                                ></i>
                                                            </div>
                                                            Tidak ada anggota
                                                            ditemukan
                                                        </td>
                                                    </tr>
                                                    <tr
                                                        v-for="member in paginatedMembers"
                                                        :key="
                                                            member.Kode_Karyawan
                                                        "
                                                        class="member-row transition-colors"
                                                    >
                                                        <!-- Sticky Member Info -->
                                                        <td
                                                            class="sticky-col bg-white"
                                                        >
                                                            <div
                                                                class="d-flex align-items-center gap-3"
                                                            >
                                                                <div
                                                                    class="avatar-circle bg-primary-subtle text-primary fw-bold ring-1 ring-primary-light flex-shrink-0"
                                                                    style="
                                                                        width: 42px;
                                                                        height: 42px;
                                                                    "
                                                                >
                                                                    {{
                                                                        getInitials(
                                                                            member.Nama,
                                                                        )
                                                                    }}
                                                                </div>
                                                                <div
                                                                    style="
                                                                        min-width: 0;
                                                                    "
                                                                >
                                                                    <div
                                                                        class="fw-bold text-dark text-truncate text-sm mb-0.5"
                                                                    >
                                                                        {{
                                                                            member.Nama
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="text-muted text-xs text-truncate"
                                                                    >
                                                                        {{
                                                                            member.NIK
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="text-xs text-muted text-truncate opacity-75"
                                                                    >
                                                                        {{
                                                                            truncateText(
                                                                                member.nama_jabatan,
                                                                                15,
                                                                            ) ||
                                                                            "-"
                                                                        }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <!-- Days Cells -->
                                                        <td
                                                            v-for="day in weekDays"
                                                            :key="day.date"
                                                            class="p-2 cursor-pointer position-relative shift-cell-hover"
                                                            :class="{
                                                                'bg-primary-subtle-light':
                                                                    day.isToday,
                                                            }"
                                                            @click="
                                                                openQuickModal(
                                                                    member,
                                                                    day,
                                                                )
                                                            "
                                                        >
                                                            <div
                                                                class="h-100 w-100 d-flex flex-column align-items-center justify-content-center rounded-3 p-2 transition-all shift-content-wrapper"
                                                                style="
                                                                    min-height: 80px;
                                                                "
                                                            >
                                                                <template
                                                                    v-if="
                                                                        getShift(
                                                                            member.Kode_Karyawan,
                                                                            day.date,
                                                                        )?.shift
                                                                    "
                                                                >
                                                                    <div
                                                                        class="badge w-100 text-truncate text-center mb-1 py-1.5 shadow-sm border-0"
                                                                        :class="
                                                                            getShiftClass(
                                                                                getShift(
                                                                                    member.Kode_Karyawan,
                                                                                    day.date,
                                                                                )
                                                                                    .shift
                                                                                    .nama,
                                                                            )
                                                                        "
                                                                        style="
                                                                            font-size: 0.75rem;
                                                                            letter-spacing: 0.02em;
                                                                        "
                                                                    >
                                                                        {{
                                                                            getShift(
                                                                                member.Kode_Karyawan,
                                                                                day.date,
                                                                            )
                                                                                .shift
                                                                                .nama
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="d-flex align-items-center gap-1 text-muted text-xs fw-medium bg-white px-2 py-0.5 rounded-pill border shadow-sm"
                                                                    >
                                                                        <i
                                                                            class="bi bi-clock"
                                                                        ></i>
                                                                        {{
                                                                            formatTime(
                                                                                getShift(
                                                                                    member.Kode_Karyawan,
                                                                                    day.date,
                                                                                )
                                                                                    .shift
                                                                                    .jam_masuk,
                                                                            )
                                                                        }}
                                                                        -
                                                                        {{
                                                                            formatTime(
                                                                                getShift(
                                                                                    member.Kode_Karyawan,
                                                                                    day.date,
                                                                                )
                                                                                    .shift
                                                                                    .jam_keluar,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </template>

                                                                <!-- Empty State with Add Icon on Hover -->
                                                                <template
                                                                    v-else
                                                                >
                                                                    <div
                                                                        class="empty-placeholder text-muted opacity-25"
                                                                    >
                                                                        <i
                                                                            class="bi bi-dash-lg"
                                                                        ></i>
                                                                    </div>
                                                                    <div
                                                                        class="add-overlay position-absolute top-50 start-50 translate-middle"
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
                                                                            <i
                                                                                class="bi bi-plus-lg"
                                                                            ></i>
                                                                        </div>
                                                                    </div>
                                                                </template>
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
                                            Halaman {{ schedulePagination.page }} dari {{ totalSchedulePages }}
                                            ({{ schedulePagination.total }} anggota)
                                        </span>
                                        <div class="d-flex gap-2">
                                            <button
                                                class="stitch-action-btn"
                                                :disabled="schedulePagination.page <= 1 || loading.schedule"
                                                @click="prevSchedulePage"
                                            >
                                                <i class="bi bi-chevron-left"></i>
                                                Sebelumnya
                                            </button>
                                            <button
                                                class="stitch-action-btn stitch-primary"
                                                :disabled="schedulePagination.page >= totalSchedulePages || loading.schedule"
                                                @click="nextSchedulePage"
                                            >
                                                Selanjutnya
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- CARD VIEW (Mobile Default) -->
                                    <div
                                        v-show="
                                            viewMode === 'card' ||
                                            (isMobile && viewMode !== 'table')
                                        "
                                        class="d-md-none bg-surface-soft p-3"
                                    >
                                        <div
                                            v-for="member in paginatedMembers"
                                            :key="member.Kode_Karyawan"
                                            class="stitch-card mb-3 p-0 overflow-hidden border shadow-sm"
                                        >
                                            <!-- Card Header -->
                                            <div
                                                class="d-flex align-items-center gap-3 p-3 bg-white border-bottom"
                                            >
                                                <div
                                                    class="avatar-circle bg-primary text-white fw-bold shadow-sm"
                                                    style="
                                                        width: 42px;
                                                        height: 42px;
                                                        font-size: 1rem;
                                                    "
                                                >
                                                    {{
                                                        getInitials(member.Nama)
                                                    }}
                                                </div>
                                                <div>
                                                    <h6
                                                        class="fw-bold mb-0 text-dark"
                                                    >
                                                        {{ member.Nama }}
                                                    </h6>
                                                    <div
                                                        class="text-muted text-xs"
                                                    >
                                                        {{ member.NIK }} •
                                                        {{
                                                            member.nama_jabatan ||
                                                            "Anggota"
                                                        }}
                                                    </div>
                                                </div>
                                                <button
                                                    class="btn btn-sm btn-light ms-auto rounded-circle"
                                                    @click="
                                                        toggleMemberCard(
                                                            member.Kode_Karyawan,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="bi"
                                                        :class="
                                                            expandedMembers.includes(
                                                                member.Kode_Karyawan,
                                                            )
                                                                ? 'bi-chevron-up'
                                                                : 'bi-chevron-down'
                                                        "
                                                    ></i>
                                                </button>
                                            </div>

                                            <!-- Card Body: Horizontal Scroll Days -->
                                            <div
                                                v-show="
                                                    expandedMembers.includes(
                                                        member.Kode_Karyawan,
                                                    )
                                                "
                                                class="p-3 bg-surface-soft"
                                            >
                                                <div
                                                    class="d-flex gap-3 overflow-x-auto pb-2"
                                                    style="
                                                        scroll-snap-type: x
                                                            mandatory;
                                                    "
                                                >
                                                    <div
                                                        v-for="day in weekDays"
                                                        :key="day.date"
                                                        class="mobile-day-card flex-shrink-0 bg-white rounded-3 border p-2 text-center clickable"
                                                        style="
                                                            width: 100px;
                                                            scroll-snap-align: start;
                                                        "
                                                        :class="{
                                                            'border-primary shadow-sm':
                                                                day.isToday,
                                                        }"
                                                        @click="
                                                            openQuickModal(
                                                                member,
                                                                day,
                                                            )
                                                        "
                                                    >
                                                        <div
                                                            class="text-xs text-uppercase fw-bold text-muted mb-1"
                                                        >
                                                            {{ day.name }}
                                                        </div>
                                                        <div
                                                            class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
                                                            :class="
                                                                day.isToday
                                                                    ? 'bg-primary text-white'
                                                                    : 'text-dark fw-bold'
                                                            "
                                                            style="
                                                                width: 24px;
                                                                height: 24px;
                                                                font-size: 0.8rem;
                                                            "
                                                        >
                                                            {{ day.day }}
                                                        </div>

                                                        <!-- Shift Badge -->
                                                        <div
                                                            v-if="
                                                                getShift(
                                                                    member.Kode_Karyawan,
                                                                    day.date,
                                                                )?.shift
                                                            "
                                                            class="w-100"
                                                        >
                                                            <div
                                                                class="badge w-100 text-truncate mb-1 py-1"
                                                                :class="
                                                                    getShiftClass(
                                                                        getShift(
                                                                            member.Kode_Karyawan,
                                                                            day.date,
                                                                        ).shift
                                                                            .nama,
                                                                    )
                                                                "
                                                                style="
                                                                    font-size: 0.65rem;
                                                                "
                                                            >
                                                                {{
                                                                    getShift(
                                                                        member.Kode_Karyawan,
                                                                        day.date,
                                                                    ).shift.nama
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-xxs text-muted fw-medium"
                                                            >
                                                                {{
                                                                    formatTime(
                                                                        getShift(
                                                                            member.Kode_Karyawan,
                                                                            day.date,
                                                                        ).shift
                                                                            .jam_masuk,
                                                                    )
                                                                }}
                                                            </div>
                                                        </div>
                                                        <div
                                                            v-else
                                                            class="text-center py-2 text-muted opacity-25"
                                                        >
                                                            <i
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
                        <div
                            class="p-4 rounded-4"
                            style="background: var(--surface-soft)"
                        >
                            <div
                                class="d-flex justify-content-between align-items-end mb-4"
                            >
                                <div>
                                    <h2 class="h5 fw-bold text-dark mb-1">
                                        Riwayat Pengajuan
                                    </h2>
                                    <p class="text-muted text-sm mb-0">
                                        Pantau status pengajuan perubahan shift
                                        Anda
                                    </p>
                                </div>
                                <button
                                    @click="loadHistory"
                                    class="btn btn-light shadow-sm btn-sm rounded-pill px-3"
                                >
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Refresh
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
                            <div
                                v-if="loading.history"
                                class="text-center p-5 loading-state"
                            >
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4 class="loading-title">Memuat Riwayat...</h4>
                            </div>

                            <div
                                v-else-if="historyItems.length === 0"
                                class="text-center p-5 text-muted bg-white rounded-3 shadow-sm border"
                            >
                                <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                                Belum ada riwayat pengajuan.
                            </div>

                            <div v-else>
                                <!-- DESKTOP TABLE VIEW -->
                                <div
                                    class="d-none d-md-block bg-white rounded-3 border shadow-sm overflow-hidden"
                                >
                                    <table class="stitch-table">
                                        <thead>
                                            <tr>
                                                <th>Karyawan</th>
                                                <th>No Transaksi</th>
                                                <th>Shift Awal</th>
                                                <th>Shift Baru</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="item in historyItems"
                                                :key="item.No_Transaksi"
                                                class="stitch-table-row"
                                            >
                                                <td>
                                                    <div
                                                        class="d-flex align-items-center gap-3"
                                                    >
                                                        <div
                                                            class="avatar-circle-sm bg-light text-dark border fw-bold"
                                                            style="
                                                                width: 32px;
                                                                height: 32px;
                                                                font-size: 0.75rem;
                                                            "
                                                        >
                                                            {{
                                                                getInitials(
                                                                    item.Nama_Member,
                                                                )
                                                            }}
                                                        </div>
                                                        <div>
                                                            <div
                                                                class="fw-bold text-dark text-sm"
                                                            >
                                                                {{
                                                                    item.Nama_Member
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-xs text-muted"
                                                            >
                                                                {{
                                                                    item.NIK_Member
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="text-muted font-mono text-xs"
                                                        >{{
                                                            item.No_Transaksi
                                                        }}</span
                                                    >
                                                </td>
                                                <td>
                                                    <div
                                                        class="text-sm text-dark"
                                                    >
                                                        {{
                                                            item.Shift_Awal ||
                                                            "-"
                                                        }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="text-sm fw-bold text-primary"
                                                    >
                                                        {{
                                                            item.Shift_Baru ||
                                                            "-"
                                                        }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="d-flex flex-column"
                                                    >
                                                        <span
                                                            class="text-sm text-dark fw-medium"
                                                            >{{
                                                                item.tanggal_count
                                                            }}
                                                            Hari</span
                                                        >
                                                        <span
                                                            class="text-xs text-muted"
                                                            >{{
                                                                formatDateShort(
                                                                    item.Created_At,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badgem rounded-pill px-2 py-1 text-xs fw-bold"
                                                        :class="
                                                            getStatusColor(
                                                                item,
                                                                'badge',
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            getStatusLabel(item)
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <!-- Add actions if needed, e.g. View Detail -->
                                                    <button
                                                        class="btn btn-sm btn-light rounded-circle text-muted"
                                                    >
                                                        <i
                                                            class="bi bi-chevron-right"
                                                        ></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MOBILE CARD VIEW -->
                                <div class="d-md-none d-flex flex-column gap-3">
                                    <div
                                        v-for="item in historyItems"
                                        :key="item.No_Transaksi"
                                        class="stitch-card p-3 bg-white border-0 shadow-sm hover-shadow transition-all"
                                    >
                                        <div
                                            class="d-flex justify-content-between align-items-start"
                                        >
                                            <div
                                                class="d-flex align-items-center gap-3"
                                            >
                                                <div
                                                    class="rounded-3 d-flex align-items-center justify-content-center fw-bold"
                                                    :class="
                                                        getStatusColor(
                                                            item,
                                                            'bg',
                                                        )
                                                    "
                                                    style="
                                                        width: 48px;
                                                        height: 48px;
                                                        font-size: 1.2rem;
                                                    "
                                                >
                                                    {{
                                                        item.Nama_Member.charAt(
                                                            0,
                                                        )
                                                    }}
                                                </div>
                                                <div>
                                                    <div
                                                        class="fw-bold text-dark"
                                                    >
                                                        {{ item.Nama_Member }}
                                                    </div>
                                                    <div
                                                        class="text-muted small"
                                                    >
                                                        {{ item.NIK_Member }} •
                                                        {{ item.No_Transaksi }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span
                                                    class="badgem rounded-pill mb-1 px-2 py-1 text-xs fw-bold"
                                                    :class="
                                                        getStatusColor(
                                                            item,
                                                            'badge',
                                                        )
                                                    "
                                                >
                                                    {{ getStatusLabel(item) }}
                                                </span>
                                                <div
                                                    class="text-muted text-xs mt-1"
                                                >
                                                    {{
                                                        formatDateFull(
                                                            item.Created_At,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="mt-3 pt-3 border-top d-flex gap-4"
                                        >
                                            <div
                                                class="d-flex flex-column text-center"
                                            >
                                                <span
                                                    class="text-muted text-xxs text-uppercase fw-bold"
                                                    >Tanggal</span
                                                >
                                                <span class="fw-bold text-dark"
                                                    >{{
                                                        item.tanggal_count
                                                    }}
                                                    Hari</span
                                                >
                                            </div>
                                            <div
                                                class="d-flex flex-column text-center"
                                            >
                                                <span
                                                    class="text-muted text-xxs text-uppercase fw-bold"
                                                    >Jenis</span
                                                >
                                                <span
                                                    class="fw-bold text-dark"
                                                    >{{
                                                        item.Jenis_Pengajuan ||
                                                        "-"
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="hasMoreHistory"
                                    class="text-center mt-3"
                                >
                                    <button
                                        @click="loadMoreHistory"
                                        class="btn btn-outline-primary rounded-pill px-4 btn-modern"
                                    >
                                        Muat Lebih Banyak
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- ================= CUSTOM MODALS ================= -->

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
                    <div
                        class="stitch-stepper px-5 py-4 bg-white border-bottom"
                    >
                        <div
                            v-for="step in 3"
                            :key="step"
                            class="stitch-step"
                            :class="{
                                active: bulkModal.step === step,
                                completed: bulkModal.step > step,
                            }"
                        >
                            <div class="stitch-step-circle">
                                <i
                                    v-if="bulkModal.step > step"
                                    class="bi bi-check-lg"
                                ></i>
                                <span v-else>{{ step }}</span>
                            </div>
                            <span class="stitch-step-label">{{
                                [
                                    "Pilih Anggota",
                                    "Pilih Tanggal",
                                    "Atur Shift",
                                ][step - 1]
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="p-4"
                        style="min-height: 350px; background: #f8f9fa"
                    >
                        <!-- Step 1: Select Members -->
                        <div v-if="bulkModal.step === 1" class="fade-in">
                            <div class="stitch-card p-3 mb-3 border bg-white">
                                <div
                                    class="d-flex align-items-center gap-2 mb-2"
                                >
                                    <i class="bi bi-search text-muted"></i>
                                    <input
                                        v-model="bulkModal.search"
                                        class="form-control border-0 p-0"
                                        placeholder="Cari anggota untuk dipilih..."
                                        style="box-shadow: none"
                                    />
                                </div>
                            </div>

                            <div
                                class="d-flex justify-content-between align-items-center mb-2 px-1"
                            >
                                <span class="text-sm fw-bold text-dark"
                                    >{{
                                        bulkModal.selectedMembers.length
                                    }}
                                    anggota terpilih</span
                                >
                                <button
                                    @click="toggleSelectAllBulk"
                                    class="btn btn-sm text-primary p-0 fw-bold"
                                >
                                    {{
                                        isAllSelected
                                            ? "Unselect All"
                                            : "Select All"
                                    }}
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
                                    @click="
                                        toggleBulkMember(member.Kode_Karyawan)
                                    "
                                >
                                    <div
                                        class="form-check m-0 pointer-events-none"
                                    >
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            :checked="
                                                bulkModal.selectedMembers.includes(
                                                    member.Kode_Karyawan,
                                                )
                                            "
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
                                            {{ member.nama_jabatan || "-" }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Select Dates -->
                        <div v-if="bulkModal.step === 2" class="fade-in">
                            <div class="mb-3">
                                <label
                                    class="form-label fw-bold small text-muted text-uppercase"
                                    >Pilih Tanggal</label
                                >
                                <el-date-picker
                                    v-model="bulkModal.dates"
                                    type="dates"
                                    placeholder="Klik untuk memilih tanggal"
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
                                <span class="small fw-bold"
                                    >Tanggal backdate memerlukan approval.</span
                                >
                            </div>

                            <label
                                class="form-label fw-bold small text-muted text-uppercase mb-2"
                                >Tanggal Terpilih ({{
                                    bulkModal.dates.length
                                }})</label
                            >
                            <div
                                class="stitch-date-chips bg-white p-3 rounded-3 border"
                                style="min-height: 100px"
                            >
                                <span
                                    v-for="(date, i) in bulkModal.dates"
                                    :key="i"
                                    class="stitch-date-chip"
                                >
                                    <i class="bi bi-calendar-event"></i>
                                    <span>{{ formatDateShort(date) }}</span>
                                    <button @click="removeBulkDate(i)">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </span>
                                <span
                                    v-if="bulkModal.dates.length === 0"
                                    class="text-muted small fst-italic"
                                    >Belum ada tanggal dipilih</span
                                >
                            </div>
                        </div>

                        <!-- Step 3: Shift & Details -->
                        <div v-if="bulkModal.step === 3" class="fade-in">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label
                                        class="form-label fw-bold small text-muted text-uppercase"
                                        >Pilih Shift</label
                                    >
                                    <div
                                        class="shift-grid-selector d-grid gap-2"
                                        style="
                                            grid-template-columns: repeat(
                                                auto-fill,
                                                minmax(140px, 1fr)
                                            );
                                        "
                                    >
                                        <div
                                            v-for="shift in availableShifts"
                                            :key="shift.ID_Shift"
                                            class="stitch-card p-2 cursor-pointer border hover-shadow transition-all text-center"
                                            :class="
                                                bulkModal.shiftId ===
                                                shift.ID_Shift
                                                    ? 'border-primary ring-1 ring-primary-light bg-primary-subtle'
                                                    : ''
                                            "
                                            @click="
                                                bulkModal.shiftId =
                                                    shift.ID_Shift
                                            "
                                        >
                                            <div
                                                class="fw-bold text-dark text-truncate"
                                            >
                                                {{ shift.Nama }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                {{
                                                    formatTime(shift.Jam_Masuk)
                                                }}
                                                -
                                                {{
                                                    formatTime(shift.Jam_Pulang)
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label
                                        class="form-label fw-bold small text-muted text-uppercase"
                                        >Jenis Perubahan</label
                                    >
                                    <div class="d-flex gap-3">
                                        <div
                                            class="stitch-card p-3 flex-1 cursor-pointer transition-all d-flex align-items-center gap-3 border"
                                            :class="
                                                bulkModal.jenis === 'SEMENTARA'
                                                    ? 'border-primary bg-primary-subtle'
                                                    : ''
                                            "
                                            @click="
                                                bulkModal.jenis = 'SEMENTARA'
                                            "
                                        >
                                            <div
                                                class="rounded-circle bg-white p-2 text-primary shadow-sm"
                                            >
                                                <i
                                                    class="bi bi-clock-history"
                                                ></i>
                                            </div>
                                            <div class="lh-1">
                                                <div
                                                    class="fw-bold text-sm text-dark"
                                                >
                                                    Sementara
                                                </div>
                                                <small
                                                    class="text-muted text-xs"
                                                    >Hanya tanggal
                                                    terpilih</small
                                                >
                                            </div>
                                        </div>
                                        <div
                                            class="stitch-card p-3 flex-1 cursor-pointer transition-all d-flex align-items-center gap-3 border"
                                            :class="
                                                bulkModal.jenis === 'PERMANEN'
                                                    ? 'border-primary bg-primary-subtle'
                                                    : ''
                                            "
                                            @click="
                                                bulkModal.jenis = 'PERMANEN'
                                            "
                                        >
                                            <div
                                                class="rounded-circle bg-white p-2 text-primary shadow-sm"
                                            >
                                                <i
                                                    class="bi bi-arrow-repeat"
                                                ></i>
                                            </div>
                                            <div class="lh-1">
                                                <div
                                                    class="fw-bold text-sm text-dark"
                                                >
                                                    Permanen
                                                </div>
                                                <small
                                                    class="text-muted text-xs"
                                                    >Berlaku seterusnya</small
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label
                                        class="form-label fw-bold small text-muted text-uppercase"
                                        >Alasan</label
                                    >
                                    <textarea
                                        v-model="bulkModal.alasan"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Tulis alasan pengajuan..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div
                    class="w-100 d-flex justify-content-between align-items-center"
                >
                    <button
                        v-if="bulkModal.step > 1"
                        class="btn-modern btn-secondary"
                        @click="bulkModal.step--"
                    >
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </button>
                    <div v-else></div>

                    <button
                        v-if="bulkModal.step < 3"
                        class="btn-modern btn-primary"
                        @click="bulkModal.step++"
                        :disabled="!canProceedBulk"
                    >
                        Lanjut <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <button
                        v-else
                        class="btn-modern btn-primary bg-success border-success"
                        @click="submitBulk"
                        :disabled="!canSubmitBulk || loading.submit"
                    >
                        {{
                            loading.submit ? "Memproses..." : "Submit Pengajuan"
                        }}
                        <i class="bi bi-check-lg ms-1"></i>
                    </button>
                </div>
            </template>
        </Modal>
    </div>
    <!-- 1. Quick Modal (Calendar Click) - Stitch Style -->
    <Modal
        :show="showQuickModal"
        :title="`Edit Shift - ${quickModalData.date ? formatDate(quickModalData.date) : ''}`"
        size="medium"
        @close="showQuickModal = false"
    >
        <template #body>
            <div class="stitch-modal-body" style="padding: 0">
                <!-- Current Shift Display -->
                <div
                    v-if="quickModalData.currentShift"
                    class="flex items-center gap-3 p-4 rounded-lg border mb-4"
                    style="background: var(--surface-soft)"
                >
                    <div class="stitch-card-icon blue">
                        <i class="bi bi-clock" style="font-size: 1.25rem"></i>
                    </div>
                    <div>
                        <div class="text-dark font-bold">
                            {{ quickModalData.currentShift.nama }}
                        </div>
                        <div class="text-muted text-sm">
                            {{
                                formatTime(
                                    quickModalData.currentShift.jam_masuk,
                                )
                            }}
                            -
                            {{
                                formatTime(
                                    quickModalData.currentShift.jam_keluar ||
                                        quickModalData.currentShift.jam_pulang,
                                )
                            }}
                        </div>
                    </div>
                </div>

                <!-- Shift Select -->
                <div class="flex flex-col gap-2 mb-4">
                    <label class="stitch-filter-label">Pilih Shift</label>
                    <el-select
                        v-model="quickModalData.newShiftId"
                        filterable
                        clearable
                        placeholder="Cari atau pilih shift..."
                        class="el-select-full"
                        popper-class="el-select-popper-high"
                        :loading="loading.shifts"
                    >
                        <el-option
                            v-for="shift in shiftOptions"
                            :key="shift.value"
                            :label="getShiftLabel(shift)"
                            :value="shift.value"
                        />
                    </el-select>
                </div>

                <!-- Make Permanent Toggle -->
                <div class="stitch-toggle mb-4">
                    <div class="d-flex flex-column">
                        <span class="text-dark font-semibold text-sm"
                            >Jadikan Permanen</span
                        >
                        <span class="text-muted text-xs"
                            >Buat shift ini dari
                            {{
                                quickModalData.date
                                    ? getDayName(quickModalData.date)
                                    : ""
                            }}
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
                            />
                            <span class="slider"></span>
                        </label>
                        <span class="toggle-label">
                            {{
                                quickModalData.makePermanent
                                    ? "Permanen"
                                    : "Sementara"
                            }}
                        </span>
                    </label>
                </div>

                <!-- Reason Input -->
                <div class="flex flex-col gap-2 mb-4">
                    <div class="flex justify-between items-center">
                        <label class="stitch-filter-label"
                            >Alasan (Opsional)</label
                        >
                        <span class="text-xs text-muted"
                            >Maks. 200 karakter</span
                        >
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
                    v-if="
                        quickModalData.date &&
                        checkNeedsApproval(
                            getJenisPengajuan(quickModalData.date),
                        )
                    "
                    class="flex items-center gap-3 p-3 rounded-lg text-sm"
                    style="
                        background: #fef3c7;
                        border: 1px solid #fcd34d;
                        color: #92400e;
                    "
                >
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>
                        <strong>Perhatian:</strong>
                        <template v-if="isBackdate(quickModalData.date)"
                            >Tanggal backdate membutuhkan approval.</template
                        >
                        <template v-else
                            >Pengajuan ini membutuhkan approval.</template
                        >
                    </span>
                </div>
            </div>
        </template>
        <template #footer>
            <button
                @click="showQuickModal = false"
                class="stitch-btn secondary flex-grow-1 flex-md-grow-0"
            >
                Batal
            </button>
            <button
                @click="submitQuickModal"
                class="stitch-btn primary flex-grow-1 flex-md-grow-0"
                :disabled="!quickModalData.newShiftId || loading.submit"
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
import HeaderKpi from "../Pages/components/ui/header.vue";
import ApprovalTabs from "../Pages/KPI/components/ui/toggleSectionV2.vue";
import Modal from "../Pages/KPI/components/ui/modalV2-noir.vue";
import ModernFilter from "../Pages/components/ui/Filter/modernFilter.vue";
import Badge from "../Pages/components/ui/badge.vue";
import QuickStat from "../Pages/KPI/components/ui/quickStat.vue";
import {
    ElDatePicker,
    ElSelect,
    ElOption,
    ElMessage,
    ElInput,
} from "element-plus";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/select/style/css";
import "element-plus/es/components/input/style/css";
import axios from "axios";
import { debounce } from "lodash";
import dayjs from "dayjs";
import "dayjs/locale/id";

dayjs.locale("id");

export default {
    name: "LeaderShift",
    components: {
        HeaderKpi,
        QuickStat,
        ApprovalTabs,
        Modal,
        Badge,
        ModernFilter,
        ElDatePicker,
        ElSelect,
        ElOption,
        ElInput,
    },
    props: {
        serverTime: String,
        serverDate: String,
        approverInfo: Object,
        initialStats: Object,
    },
    data() {
        return {
            dayNight: "Siang",
            formattedDate: "",
            selectedDate: "",
            activeTab: "kalender",
            viewMode: "table", // 'table' or 'card'
            isMobile: window.innerWidth < 768,
            tabs: [
                {
                    id: "kalender",
                    label: "Kalender Tim",
                    icon: "bi-calendar-week",
                },
                { id: "riwayat", label: "Riwayat", icon: "bi-clock-history" },
            ],

            // Modern Filter Config for History
            historyFilterConfig: {
                searchable: true,
                searchPlaceholder: "Cari No. Transaksi atau Nama...",
                filters: [
                    {
                        key: "status",
                        label: "Status",
                        type: "select",
                        options: [
                            { label: "Semua", value: "all" },
                            { label: "Pending", value: "pending" },
                            { label: "Disetujui", value: "approved" },
                            { label: "Ditolak", value: "rejected" },
                        ],
                    },
                ],
            },

            // State
            loading: {
                team: false,
                schedule: false,
                shifts: false,
                submit: false,
                history: false,
            },

            // Data
            team: [],
            searchMember: "",
            searchTimer: null, // For debounced search
            startDate: "", // For week navigation
            weekDays: [],
            weekSchedule: {}, // { 'KODE': { '2024-01-01': shiftObject } }
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
                alasan: "",
            },

            // Standard Quick Modal (SelfShift Style)
            showQuickModal: false,
            quickModalData: {
                date: null,
                currentShift: null,
                newShiftId: null,
                makePermanent: false,
                alasan: "",
                member: null, // Additional context for LeaderShift
            },
            lockedDates: [],
            shiftOptions: [], // For select dropdown

            activeFilters: {
                search: "",
                shift: "",
            },

            // Bulk Modal
            bulkModal: {
                visible: false,
                step: 1,
                search: "",
                selectedMembers: [], // Array of Kodes
                dates: [],
                shiftId: null,
                jenis: "SEMENTARA",
                alasan: "",
            },

            // History
            historyItems: [],
            historyFilters: { status: "all", search: "" },
            historyPagination: { page: 1, limit: 10, total: 0 },
        };
    },
    computed: {
        quickStatField() {
            return [
                {
                    title: "Total Tim",
                    value: this.initialStats?.team_count || 0,
                    icon: "bi-people",
                    size: "col-4",
                    color: "blue",
                },
                {
                    title: "Pending",
                    value: this.initialStats?.pending_count || 0,
                    icon: "bi-hourglass-split",
                    size: "col-4",
                    color: "orange",
                },
                {
                    title: "Approved",
                    value: this.initialStats?.approved_month || 0,
                    icon: "bi-check-circle",
                    size: "col-4",
                    color: "green",
                },
            ];
        },
        filteredMembers() {
            let list = [...this.team];

            // 1. Filter by Search
            if (this.searchMember) {
                const q = this.searchMember.toLowerCase();
                list = list.filter(
                    (m) =>
                        m.Nama?.toLowerCase().includes(q) ||
                        m.NIK?.toLowerCase().includes(q),
                );
            }

            // 2. Filter by Shift (Show member if they have this shift on ANY visible day)
            if (this.activeFilters.shift) {
                const targetShiftId = this.activeFilters.shift;
                list = list.filter((m) => {
                    const schedule = this.weekSchedule[m.Kode_Karyawan];
                    if (!schedule) return false;
                    // Check if any day in current week has this shift
                    return Object.values(schedule).some(
                        (s) => s.shift && s.shift.id === targetShiftId,
                    );
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
            if (this.weekDays.length === 0) return "-";
            const start = this.weekDays[0].date;
            const end = this.weekDays[6].date;
            return `${dayjs(start).format("D MMM")} - ${dayjs(end).format("D MMM YYYY")}`;
        },
        weekRangeDisplay() {
            return this.formatWeekRange;
        },
        monthYearDisplay() {
            if (this.weekDays.length === 0) return "-";
            const start = this.weekDays[0];
            return `${start.month} ${start.year}`;
        },
        // Bulk Computed
        filteredBulkMembers() {
            const list = !this.bulkModal.search
                ? [...this.team]
                : this.team.filter(
                      (m) =>
                          m.Nama?.toLowerCase().includes(
                              this.bulkModal.search.toLowerCase(),
                          ) ||
                          m.NIK?.toLowerCase().includes(
                              this.bulkModal.search.toLowerCase(),
                          ),
                  );
            return list.sort((a, b) => a.Nama.localeCompare(b.Nama));
        },
        isAllSelected() {
            if (this.filteredBulkMembers.length === 0) return false;
            return this.filteredBulkMembers.every((m) =>
                this.bulkModal.selectedMembers.includes(m.Kode_Karyawan),
            );
        },
        hasBackdateBulk() {
            if (!this.bulkModal.dates.length) return false;
            const today = dayjs().format("YYYY-MM-DD");
            return this.bulkModal.dates.some((d) => d < today);
        },
        canProceedBulk() {
            if (this.bulkModal.step === 1)
                return this.bulkModal.selectedMembers.length > 0;
            if (this.bulkModal.step === 2)
                return this.bulkModal.dates.length > 0;
            return false;
        },
        canSubmitBulk() {
            return (
                this.bulkModal.shiftId &&
                this.bulkModal.dates.length > 0 &&
                this.bulkModal.selectedMembers.length > 0
            );
        },
        hasMoreHistory() {
            return this.historyItems.length < this.historyPagination.total;
        },
        inLoadingTime(){
        return this.loading.team || this.loading.shifts || this.loading.schedule || this.loading.history;
        },
    },
    mounted() {
        this.initDateTime();
        this.startDate = this.serverDate
            ? dayjs(this.serverDate).format("YYYY-MM-DD")
            : dayjs().format("YYYY-MM-DD");
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
        window.addEventListener("resize", this.handleResize);
        this.handleResize();
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
    },
    
    methods: {
        truncateText(text, length) {
            if (text.length > length) {
                return text.substring(0, length) + "...";
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
            this.dayNight = hour >= 6 && hour < 18 ? "Siang" : "Malam";
            this.formattedDate = now.toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            if (this.isMobile && this.viewMode === "table") {
                this.viewMode = "card";
            } else if (!this.isMobile && this.viewMode === "card") {
                this.viewMode = "table";
            }
        },
        handleTabChange(tab) {
            this.activeTab = tab;
            if (tab === "riwayat" && this.historyItems.length === 0)
                this.loadHistory();
        },

        // --- Data Loading ---
        async loadTeam() {
            this.loading.team = true;
            try {
                const res = await axios.get("/leader-shift/team");
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
                const res = await axios.get("/leader-shift/shifts");
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
        openQuickModal(member, day) {
            console.log("openQuickModal called", { member, day });
            try {
                // Validation
                if (!member || !day) return;
                // if (this.isDateOutOfRange(day.date)) { ... }

                const current = this.getShift(member.Kode_Karyawan, day.date);
                console.log("Current Shift Data:", current);

                // Handle different ID casing if found (id vs ID_Shift)
                const shiftId =
                    current && current.shift
                        ? current.shift.id || current.shift.ID_Shift
                        : null;

                this.quickModalData = {
                    date: day.date,
                    currentShift: current ? current.shift : null, // { id, nama, ... }
                    newShiftId: shiftId,
                    makePermanent: false,
                    alasan: "",
                    member: member,
                };

                this.showQuickModal = true;
                console.log("showQuickModal set to true");
            } catch (error) {
                console.error("Error in openQuickModal:", error);
                ElMessage.error("Terjadi kesalahan saat membuka modal");
            }
        },

        async submitQuickModal() {
            this.loading.submit = true;
            try {
                await axios.post("/leader-shift/submit", {
                    members: [this.quickModalData.member.Kode_Karyawan],
                    dates: [
                        {
                            tanggal: this.quickModalData.date,
                            id_shift: this.quickModalData.newShiftId,
                        },
                    ],
                    jenis: this.quickModalData.makePermanent
                        ? "PERMANEN"
                        : "SEMENTARA",
                    alasan: this.quickModalData.alasan,
                });
                ElMessage.success("Shift berhasil diupdate");
                this.showQuickModal = false;
                this.loadSchedule();
            } catch (e) {
                ElMessage.error(
                    e.response?.data?.message || "Gagal update shift",
                );
            } finally {
                this.loading.submit = false;
            }
        },

        // --- Helpers ---
        getShiftLabel(shift) {
            if (!shift) return "-";
            // Handle both object structure styles if needed
            const name = shift.Nama || shift.nama;
            const inTime = this.formatTime(shift.Jam_Masuk || shift.jam_masuk);
            const outTime = this.formatTime(
                shift.Jam_Pulang ||
                    shift.Jam_Keluar ||
                    shift.jam_keluar ||
                    shift.jam_pulang,
            );
            return `${name} (${inTime} - ${outTime})`;
        },
        getShiftName(shiftId) {
            const s = this.availableShifts.find(
                (x) => x.ID_Shift === shiftId || x.id === shiftId,
            );
            return s ? s.Nama || s.nama : "-";
        },
        isBackdate(dateStr) {
            return dayjs(dateStr).isBefore(dayjs(), "day");
        },
        isSameDay(dateStr) {
            return dayjs(dateStr).isSame(dayjs(), "day");
        },
        getJenisPengajuan(dateStr) {
            if (this.isBackdate(dateStr)) return "BACKDATE";
            if (this.isSameDay(dateStr)) return "HARI_H";
            return "FUTURE";
        },
        checkNeedsApproval(jenis) {
            // Leader shift might have different rules, assuming backdate always needs approval
            if (jenis === "BACKDATE") return true;
            return false;
        },
        formatDate(dateStr) {
            return dayjs(dateStr).format("DD MMM YYYY");
        },
        getDayName(dateStr) {
            return dayjs(dateStr).format("dddd");
        },

        // --- Week & Grid Logic ---
        generateWeekDays() {
            // const start = dayjs(this.startDate).startOf("week").add(1, "day"); // Monday
            const today = dayjs(this.startDate);

            // day(): 0=Min, 1=Sen, ... 6=Sab
            const mondayOffset = (today.day() + 6) % 7;
            const start = today.subtract(mondayOffset, "day"); // selalu jatuh ke Senin

            this.weekDays = Array.from({ length: 7 }, (_, i) => {
                const d = start.add(i, "day");
                return {
                    date: d.format("YYYY-MM-DD"),
                    day: d.format("D"),
                    name: d.format("ddd"),
                    month: d.format("MMM"),
                    year: d.format("YYYY"),
                    isToday:
                        d.format("YYYY-MM-DD") === dayjs().format("YYYY-MM-DD"),
                };
            });
            this.startDate = start.format("YYYY-MM-DD");
        },
        onWeekChange() {
            this.pickerVisible = false;
            this.generateWeekDays();
            this.loadSchedule(true); // Reset page on week change
        },
        changeWeek() {
            this.onWeekChange();
        },
        prevWeek() {
            this.startDate = dayjs(this.startDate)
                .subtract(1, "week")
                .format("YYYY-MM-DD");
            this.generateWeekDays();
            this.loadSchedule(true); // Reset page on week change
        },
        nextWeek() {
            this.startDate = dayjs(this.startDate)
                .add(1, "week")
                .format("YYYY-MM-DD");
            this.generateWeekDays();
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
                const res = await axios.get("/leader-shift/week-schedule", {
                    params: {
                        start_date: start,
                        days: 7,
                        page: this.schedulePagination.page,
                        limit: this.schedulePagination.limit,
                        search: this.searchMember || undefined,
                    },
                });
                this.weekSchedule = res.data.result.schedules || {};
                // Store members from API response
                this.paginatedMembersData = res.data.result.members || [];
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
        getShiftClass(shiftName) {
            if (!shiftName) return "shift-noshift";
            const n = shiftName.toLowerCase();

            // Match userShift colors
            if (
                n.includes("off") ||
                n.includes("libur") ||
                n.includes("noshift")
            )
                return "shift-noshift";
            if (n.includes("pagi") || n.endsWith("p") || n.endsWith("1"))
                return "shift-morning";
            if (n.includes("siang") || n.endsWith("2"))
                return "shift-afternoon";
            if (n.includes("malam") || n.endsWith("m") || n.endsWith("3"))
                return "shift-night";

            return "shift-afternoon"; // Default fallback
        },
        formatTime(time) {
            if (!time) return "";
            return time.substring(0, 5);
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
                search: "",
                selectedMembers: [],
                dates: [],
                shiftId: null,
                jenis: "SEMENTARA",
                alasan: "",
            };
        },
        closeBulkModal() {
            this.bulkModal.visible = false;
        },
        toggleBulkMember(kode) {
            const idx = this.bulkModal.selectedMembers.indexOf(kode);
            if (idx > -1) this.bulkModal.selectedMembers.splice(idx, 1);
            else this.bulkModal.selectedMembers.push(kode);
        },
        toggleSelectAllBulk() {
            if (this.isAllSelected) {
                const filteredKodes = this.filteredBulkMembers.map(
                    (m) => m.Kode_Karyawan,
                );
                this.bulkModal.selectedMembers =
                    this.bulkModal.selectedMembers.filter(
                        (k) => !filteredKodes.includes(k),
                    );
            } else {
                this.filteredBulkMembers.forEach((m) => {
                    if (
                        !this.bulkModal.selectedMembers.includes(
                            m.Kode_Karyawan,
                        )
                    ) {
                        this.bulkModal.selectedMembers.push(m.Kode_Karyawan);
                    }
                });
            }
        },
        removeBulkDate(index) {
            this.bulkModal.dates.splice(index, 1);
        },
        disabledDate(date) {
            return false;
        },
        async submitBulk() {
            this.loading.submit = true;
            try {
                const dates = this.bulkModal.dates.map((d) => ({
                    tanggal: d,
                    id_shift: this.bulkModal.shiftId,
                }));

                await axios.post("/leader-shift/submit", {
                    members: this.bulkModal.selectedMembers,
                    dates,
                    jenis: this.bulkModal.jenis,
                    alasan: this.bulkModal.alasan,
                });

                this.$message.success("Pengajuan bulk berhasil disubmit");
                this.closeBulkModal();
                this.loadSchedule();
            } catch (e) {
                this.$message.error(
                    e.response?.data?.message || "Gagal submit bulk",
                );
            } finally {
                this.loading.submit = false;
            }
        },

        // --- History ---
        async loadHistory() {
            this.loading.history = true;
            try {
                const res = await axios.get("/leader-shift/history", {
                    params: {
                        page: this.historyPagination.page,
                        limit: this.historyPagination.limit,
                        status: this.historyFilters.status,
                        search: this.historyFilters.search,
                    },
                });
                if (this.historyPagination.page === 1)
                    this.historyItems = res.data.result.items;
                else this.historyItems.push(...res.data.result.items);
                this.historyPagination.total = res.data.pagination.total;
            } catch (e) {
                console.error(e);
            } finally {
                this.loading.history = false;
            }
        },
        handleHistoryFilters(filters) {
            this.historyFilters = filters;
            this.historyPagination.page = 1; // Reset page
            this.loadHistory();
        },
        loadMoreHistory() {
            this.historyPagination.page++;
            this.loadHistory();
        },
        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .slice(0, 2)
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },
        formatDateShort(date) {
            return dayjs(date).format("D MMM");
        },
        formatDateFull(date) {
            return dayjs(date).format("D MMM YYYY, HH:mm");
        },
        getStatusColor(item, type) {
            const status = item.Status;

            if (status === "APPROVED" || item.Flag_Selesai === "Y") {
                return type === "bg"
                    ? "bg-success-subtle text-success"
                    : "bg-success-subtle text-success border-success";
            }
            if (status === "REJECTED") {
                return type === "bg"
                    ? "bg-danger-subtle text-danger"
                    : "bg-danger-subtle text-danger border-danger";
            }
            return type === "bg"
                ? "bg-warning-subtle text-warning-emphasis"
                : "bg-warning-subtle text-warning-emphasis border-warning";
        },
        getStatusLabel(item) {
            if (item.Flag_Selesai === "Y") return "Selesai";
            if (item.Status === "APPROVED") return "Disetujui";
            if (item.Status === "REJECTED") return "Ditolak";
            return "Menunggu Approval";
        },
    },
};
</script>

<style scoped>
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

/* Colors */
.bg-primary-subtle {
    background-color: #eff6ff !important;
    color: #1e40af !important;
}
.bg-primary-subtle-light {
    background-color: #f8fafc !important;
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
    background: #4f46e5;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.btn-modern.btn-primary:hover {
    background: #4338ca;
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
    border-color: #4f46e5;
    color: #4f46e5;
    background: #eef2ff;
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
    background-color: #f8fafc;
}

.shift-content .add-icon {
    display: none;
}

.shift-cell:hover .add-icon {
    display: block;
    color: #4f46e5;
    background: #eef2ff;
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
    content: "";
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
    border-color: #4f46e5;
    background: #4f46e5;
    color: white;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
}

.stitch-step.completed .stitch-step-circle {
    border-color: #10b981;
    background: #10b981;
    color: white;
}

.stitch-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
}

.stitch-step.active .stitch-step-label {
    color: #4f46e5;
}
.stitch-step.completed .stitch-step-label {
    color: #10b981;
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
    color: #dc2626;
}

/* LOADING STATE */
.loading-spinner {
    font-size: 2rem;
    color: #4f46e5;
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
    background-color: #f8fafc;
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
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.stitch-btn.primary {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
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

.stitch-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #4b5563;
    border-radius: 8px;
    transition: all 0.2s;
    cursor: pointer;
}

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

<style>
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
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;

    /* Gradients (Derived) */
    --gradient-primary: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
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
    content: "";
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
    background-color: #4f46e5; /* indigo */
}

.toggle-switch input:checked + .slider::before {
    transform: translateX(24px);
}

/* Optional focus */
.toggle-switch input:focus + .slider {
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
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
    background-color: #e0e7ff !important;
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
    background: linear-gradient(to bottom right, #f5f3ff, #ffffff);
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
    content: "";
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
    background: linear-gradient(to bottom right, #eef2ff, #ffffff);
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
    background: #d1fae5;
    color: #065f46;
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
    background-color: #f0fdf4 !important;
}
.bg-yellow-50 {
    background-color: #fefce8 !important;
}
.bg-red-50 {
    background-color: #fef2f2 !important;
}
.bg-gray-50 {
    background-color: #f9fafb !important;
}
.bg-gray-100 {
    background-color: #f3f4f6 !important;
}

.text-green-600 {
    color: #16a34a !important;
}
.text-green-700 {
    color: #15803d !important;
}
.text-yellow-700 {
    color: #a16207 !important;
}
.text-red-600 {
    color: #dc2626 !important;
}
.text-red-700 {
    color: #b91c1c !important;
}
.text-purple-600 {
    color: #9333ea !important;
}
.text-orange-600 {
    color: #ea580c !important;
}
.text-gray-400 {
    color: #9ca3af !important;
}
.text-gray-500 {
    color: #6b7280 !important;
}

.border-green-200 {
    border-color: #bbf7d0 !important;
}
.border-yellow-200 {
    border-color: #fef08a !important;
}
.border-red-200 {
    border-color: #fecaca !important;
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
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
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
    color: #9333ea;
}
.stitch-card-icon.orange {
    background: #fff7ed;
    color: #ea580c;
}
.stitch-card-icon.green {
    background: #f0fdf4;
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
    background: #9333ea;
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
    background: #ffedd5;
    color: #c2410c;
}

/* Stitch Action Buttons */
.stitch-action-btn {
    width: 40px;
    height: 40px;
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

.stitch-action-btn.danger:hover {
    border-color: #fecaca;
    background: #fef2f2;
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
    background: #dcfce7;
    color: #166534;
}

.stitch-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.stitch-badge.rejected {
    background: #fee2e2;
    color: #991b1b;
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
    content: "";
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
    background: linear-gradient(to bottom right, #eef2ff, var(--surface));
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
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-dates-btn:hover {
    background: #fee2e2;
    border-color: #fca5a5;
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
    background: #fee2e2;
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

.stitch-btn.primary:hover {
    background: var(--primary-dark);
}

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
    padding: 1.5rem;
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
    background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
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
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.detail-status-badge.pending {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.detail-status-badge.rejected {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

.stitch-table th {
    background: var(--surface-soft);
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 1px solid var(--border-dark, #e2e8f0);
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
    background: var(--surface-soft);
}

.member-row:hover .sticky-col {
    background: var(--surface-soft);
}

.member-row:hover td {
    background: var(--surface-soft);
}

.member-row:last-child td {
    border-bottom: none;
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
    background: linear-gradient(135deg, #fef3c7, #fcd34d);
    color: #92400e;
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, #a78bfa);
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
    background: linear-gradient(135deg, #fef3c7, #fcd34d);
    color: #92400e;
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, #a78bfa);
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
