<template>
    <div class="cuti-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Pengajuan Shift Mandiri"
            subtitle="Kelola jadwal shift dan lihat riwayat pengajuan"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />
        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs :tabs="tabs" :activeTab="activeTab" @tab-change="handleTabChange">
                    <!-- Tab Kalender -->
                    <template #kalender>
                        <div class="p-6 rounded-4" style="background: var(--surface-soft)">
                            <!-- SUMMARY CARDS (Stitch Style) -->
                            <!-- Harmonized Shift Info Card -->
                            <!-- Redesigned Approver & Policy Card (Compact) -->
                            <div class="stitch-card p-2 mb-3 border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                                <div class="row g-0">
                                    <!-- Left Section: Approver Profile -->
                                    <div
                                        class="col-12 col-md-5 px-3 py-3 py-md-0 pt-md-0 d-flex flex-column justify-content-center position-relative"
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
                                                                max-width: 180px;
                                                                /* flex: 1 1 0px; */
                                                            "
                                                        >
                                                            <h6
                                                                class="nama-approver text-dark fw-bold mb-0.5 text-truncate"
                                                                style="font-size: 0.95rem"
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

                                    <!-- Right Section: Policy Grid -->
                                    <div class="col-12 col-md-7 p-2 rounded-3 bg-light bg-opacity-50">
                                        <div class="row g-2 h-100 align-items-center">
                                            <!-- Backdate Policy -->
                                            <div v-if="config.backdate_max_days > 0" class="col-4">
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
                                                            Backdate
                                                        </div>
                                                        <!-- <div class="fw-bold text-dark" style="font-size: 0.8rem;">{{ config.backdate_max_days }} Hari</div> -->
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5"
                                                            :class="
                                                                config.backdate_needs_approval
                                                                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                                                    : 'bg-success-subtle text-success-emphasis border-success-subtle'
                                                            "
                                                            style="font-size: 0.6rem; padding: 0.25em 0.5em"
                                                        >
                                                            <span class="d-none d-md-block">{{
                                                                config.backdate_needs_approval
                                                                    ? 'Butuh approval'
                                                                    : 'Tidak butuh approval'
                                                            }}</span>
                                                            <span class="d-block d-md-none">{{
                                                                config.backdate_needs_approval ? 'Approval' : 'Auto'
                                                            }}</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Today Policy -->
                                            <div class="col-6">
                                                <div
                                                    class="p-2 rounded-3 bg-white border shadow-sm text-center h-100 d-flex flex-column justify-content-center transition-hover"
                                                >
                                                    <div class="mb-1">
                                                        <i
                                                            class="bi bi-calendar-check text-purple bg-purple-subtle p-1.5 rounded-circle"
                                                            style="
                                                                color: #8b5cf6;
                                                                background-color: #f3e8ff;
                                                                font-size: 0.85rem;
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div class="d-flex flex-column gap-0.5">
                                                        <div
                                                            class="text-muted fw-bold"
                                                            style="font-size: 0.6rem; text-transform: uppercase"
                                                        >
                                                            Hari Ini
                                                        </div>
                                                        <!-- <div class="fw-bold text-dark" style="font-size: 0.8rem;">H-0</div> -->
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5"
                                                            :class="
                                                                config.same_day_needs_approval
                                                                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                                                    : 'bg-success-subtle text-success-emphasis border-success-subtle'
                                                            "
                                                            style="font-size: 0.6rem; padding: 0.25em 0.5em"
                                                        >
                                                            <span class="d-none d-md-block">{{
                                                                config.same_day_needs_approval
                                                                    ? 'Butuh approval'
                                                                    : 'Tidak butuh approval'
                                                            }}</span>
                                                            <span class="d-block d-md-none">{{
                                                                config.same_day_needs_approval ? 'Approval' : 'Auto'
                                                            }}</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Future Policy -->
                                            <div class="col-6">
                                                <div
                                                    class="p-2 rounded-3 bg-white border shadow-sm text-center h-100 d-flex flex-column justify-content-center transition-hover"
                                                >
                                                    <div class="mb-1">
                                                        <i
                                                            class="bi bi-calendar-plus text-info bg-info-subtle p-1.5 rounded-circle"
                                                            style="font-size: 0.85rem"
                                                        ></i>
                                                    </div>
                                                    <div class="d-flex flex-column gap-0.5">
                                                        <div
                                                            class="text-muted fw-bold"
                                                            style="font-size: 0.6rem; text-transform: uppercase"
                                                        >
                                                            Future
                                                        </div>
                                                        <!-- <div class="fw-bold text-dark" style="font-size: 0.8rem;">{{ config.future_max_days }} Hari</div> -->
                                                        <span
                                                            class="badge rounded-pill border fw-semibold mt-0.5"
                                                            :class="
                                                                config.future_needs_approval
                                                                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                                                    : 'bg-success-subtle text-success-emphasis border-success-subtle'
                                                            "
                                                            style="font-size: 0.6rem; padding: 0.25em 0.5em"
                                                        >
                                                            <span class="d-none d-md-block">{{
                                                                config.future_needs_approval
                                                                    ? 'Butuh approval'
                                                                    : 'Tidak butuh approval'
                                                            }}</span>
                                                            <span class="d-block d-md-none">{{
                                                                config.future_needs_approval ? 'Approval' : 'Auto'
                                                            }}</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="date-nav-wrapper stitch-card mb-4 sticky top-0 z-10">
                                <div class="date-nav-left">
                                    <div
                                        class="d-flex align-items-center border bg-white p-1 gap-2"
                                        style="border-radius: 10px"
                                    >
                                        <button
                                            @click="prevWeek"
                                            :disabled="loading"
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
                                                <i class="bi bi-calendar-date tengahkan"></i>
                                            </button>
                                            <el-date-picker
                                                v-model="selectedDate"
                                                type="date"
                                                :clearable="false"
                                                format="YYYY-MM-DD"
                                                value-format="YYYY-MM-DD"
                                                @change="onDateChange"
                                                style="
                                                    position: absolute;
                                                    top: 0;
                                                    left: 0;
                                                    width: 100%;
                                                    height: 100%;
                                                    opacity: 0;
                                                    overflow: hidden;
                                                    min-width: unset;
                                                    padding: 0;
                                                    cursor: pointer;
                                                "
                                            />
                                        </div>

                                        <button
                                            @click="nextWeek"
                                            :disabled="loading"
                                            class="stitch-action-btn"
                                            style="width: 32px; height: 32px"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-calendar-week text-muted"></i>
                                        <span class="text-dark font-bold date-nav-label">
                                            {{ monthYearDisplay }}
                                            <span class="font-medium text-muted">W{{ weekNumber }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="date-nav-right">
                                    <button @click="goToToday" class="stitch-btn secondary today-btn">Hari Ini</button>
                                    <button @click="showWizard = true" class="stitch-btn primary add-shift-btn">
                                        <i class="bi bi-plus-lg"></i>
                                        <span class="add-shift-text">Tambah</span>
                                    </button>
                                </div>
                            </div>

                            <!-- VERTICAL ROSTER LIST (Stitch Style) -->
                            <div class="roster-list" v-if="!loading">
                                <div
                                    v-for="day in weekScheduleData"
                                    :key="day.date"
                                    class="stitch-roster-item"
                                    :class="{
                                        today: day.is_today,
                                        weekend: day.is_weekend && !day.shift,
                                    }"
                                >
                                    <!-- Date Box -->
                                    <div class="stitch-date-box">
                                        <span class="stitch-date-day" :class="{ weekend: day.is_weekend }">{{
                                            day.day_name
                                        }}</span>
                                        <span
                                            class="stitch-date-num"
                                            :style="day.is_weekend && !day.shift ? 'opacity: 0.6' : ''"
                                            >{{ day.day_short }}</span
                                        >
                                    </div>

                                    <!-- Content -->
                                    <div class="roster-content">
                                        <!-- Shift Info -->
                                        <template v-if="day.shift">
                                            <div class="shift-info">
                                                <div
                                                    class="stitch-shift-indicator"
                                                    :class="getShiftIndicatorClass(day.shift.nama)"
                                                ></div>
                                                <div class="shift-details">
                                                    <div class="shift-header">
                                                        <h3 class="shift-name">
                                                            {{ day.shift.nama }}
                                                        </h3>
                                                        <span
                                                            class="stitch-badge"
                                                            :class="
                                                                isCalendarLocked(day)
                                                                    ? 'pending'
                                                                    : day.Flag_Approval === 'T'
                                                                      ? 'rejected'
                                                                      : 'approved'
                                                            "
                                                            >{{
                                                                isCalendarLocked(day)
                                                                    ? 'Menunggu Review'
                                                                    : day.Flag_Approval === 'T'
                                                                      ? 'Ditolak'
                                                                      : 'Disetujui'
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div class="shift-meta">
                                                        <span class="shift-time">
                                                            <i class="bi bi-clock"></i>
                                                            {{ day.shift.jam_masuk }}
                                                            -
                                                            {{ day.shift.jam_keluar }}
                                                        </span>
                                                        <span> • </span>
                                                        <span v-if="day.source" class="shift-source">
                                                            <i
                                                                class="bi tengahkan"
                                                                :class="{
                                                                    'bi-calendar-check': day.source === 'SEMENTARA',
                                                                    'bi-calendar2-check': day.source === 'PERMANEN',
                                                                }"
                                                            ></i>
                                                            {{ day.source === 'SEMENTARA' ? 'Sementara' : 'Permanen' }}
                                                        </span>
                                                    </div>
                                                    <!-- Tukar Hari Badge for Working Day -->
                                                    <div
                                                        v-if="day.tukar_hari && day.tukar_hari.type === 'working'"
                                                        class="mt-1"
                                                    >
                                                        <span
                                                            class="stitch-badge"
                                                            :class="
                                                                day.tukar_hari.flag_approval === 'Y'
                                                                    ? 'swap-approved'
                                                                    : day.tukar_hari.flag_approval === 'T'
                                                                      ? 'rejected'
                                                                      : 'swap-pending'
                                                            "
                                                            style="font-size: 0.7rem"
                                                        >
                                                            <i class="bi bi-arrow-left-right"></i>
                                                            Tukar Hari
                                                            {{
                                                                day.tukar_hari.flag_approval === 'Y'
                                                                    ? ''
                                                                    : day.tukar_hari.flag_approval === 'T'
                                                                      ? '(Ditolak)'
                                                                      : '(Pending)'
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Actions -->
                                            <div class="roster-actions">
                                                <button
                                                    v-if="!isCalendarLocked(day) && canEditDate(day.date)"
                                                    @click="openQuickModal(day)"
                                                    class="stitch-action-btn"
                                                    title="Edit Shift"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <div
                                                    v-if="isCalendarLocked(day)"
                                                    class="text-warning-emphasis bg-warning-subtle px-2 py-1 rounded text-xs fw-medium d-flex align-items-center gap-1"
                                                >
                                                    <i class="bi bi-lock-fill"></i>
                                                    <span>
                                                        Pengajuan Sedang Diproses
                                                        <template v-if="day.pending_no_transaksi">
                                                            ({{ day.pending_no_transaksi }})
                                                        </template>
                                                    </span>
                                                </div>
                                                <!-- <button 
                                                    class="stitch-action-btn danger" 
                                                    title="Hapus Shift"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button> -->
                                            </div>
                                        </template>

                                        <!-- Day Off - Tukar Hari -->
                                        <template v-else-if="day.tukar_hari && day.tukar_hari.type === 'off'">
                                            <div
                                                class="d-flex align-items-center justify-content-between gap-3 p-3 rounded-3 border"
                                                :class="
                                                    day.tukar_hari.flag_approval === 'Y'
                                                        ? 'bg-light border-secondary'
                                                        : 'bg-warning-subtle border-warning'
                                                "
                                                style="height: 100%; width: 100%"
                                            >
                                                <div class="d-flex align-items-center gap-3">
                                                    <div
                                                        class="d-flex align-items-center justify-content-center rounded-circle"
                                                        :class="
                                                            day.tukar_hari.flag_approval === 'Y'
                                                                ? 'bg-secondary-subtle'
                                                                : 'bg-warning'
                                                        "
                                                        style="width: 36px; height: 36px"
                                                    >
                                                        <i
                                                            class="bi bi-calendar-x"
                                                            :class="
                                                                day.tukar_hari.flag_approval === 'Y'
                                                                    ? 'text-secondary'
                                                                    : 'text-white'
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-dark text-sm"
                                                            >Hari Libur (Tukar Hari)</span
                                                        >
                                                        <span class="text-xs text-muted">
                                                            <i class="bi bi-arrow-left-right"></i>
                                                            Ditukar dengan
                                                            {{ formatDateShort(day.tukar_hari.tanggal_bekerja) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <span
                                                    class="stitch-badge"
                                                    :class="
                                                        day.tukar_hari.flag_approval === 'Y'
                                                            ? 'approved'
                                                            : day.tukar_hari.flag_approval === 'T'
                                                              ? 'rejected'
                                                              : 'pending'
                                                    "
                                                >
                                                    {{
                                                        day.tukar_hari.flag_approval === 'Y'
                                                            ? 'Disetujui'
                                                            : day.tukar_hari.flag_approval === 'T'
                                                              ? 'Ditolak'
                                                              : 'Pending'
                                                    }}
                                                </span>
                                            </div>
                                        </template>

                                        <!-- Pending / Locked State -->
                                        <template v-else-if="isCalendarLocked(day)">
                                            <div
                                                class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light border border-warning-subtle text-muted"
                                                style="height: 100%; width: 100%"
                                            >
                                                <div
                                                    class="d-flex align-items-center justify-content-center bg-warning-subtle rounded-circle"
                                                    style="width: 36px; height: 36px"
                                                >
                                                    <i class="bi bi-lock-fill text-warning-emphasis"></i>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-dark text-sm">Sedang Diproses</span>
                                                    <span class="text-xs">Menunggu konfirmasi</span>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Day Off dari Shift Schedule -->
                                        <template v-else-if="day.is_day_off">
                                            <div
                                                class="d-flex align-items-center justify-content-between gap-3 p-3 rounded-3 bg-secondary-subtle border border-secondary text-secondary-emphasis"
                                                style="height: 100%; width: 100%"
                                            >
                                                <div class="d-flex align-items-center gap-3">
                                                    <div
                                                        class="d-flex align-items-center justify-content-center bg-secondary rounded-circle"
                                                        style="width: 36px; height: 36px"
                                                    >
                                                        <i class="bi bi-calendar-x text-white"></i>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-dark text-sm">
                                                            {{ day.status_hari_label || 'Hari Libur' }}
                                                        </span>
                                                        <span class="text-xs text-muted"> Tidak ada jadwal kerja </span>
                                                    </div>
                                                </div>

                                                <div class="roster-actions">
                                                    <button
                                                        @click="openQuickModal(day)"
                                                        class="stitch-action-btn"
                                                        title="Edit Shift"
                                                    >
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <!-- <button
                                                        @click="
                                                            openSwapDayModal(
                                                                true,
                                                                day.date,
                                                            )
                                                        "
                                                        class="stitch-action-btn"
                                                        title="Tukar Hari"
                                                        :disabled="
                                                            isTukarHariDateOutOfRange(
                                                                day.date,
                                                            )
                                                        "
                                                        :class="{
                                                            'opacity-50 cursor-default':
                                                                isTukarHariDateOutOfRange(
                                                                    day.date,
                                                                ),
                                                        }"
                                                    >
                                                        <i
                                                            class="bi bi-arrow-left-right"
                                                        ></i>
                                                    </button> -->
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Empty / Add Shift -->
                                        <template v-else-if="!day.is_weekend">
                                            <!-- Dalam jangkauan: bisa tambah shift -->
                                            <div v-if="canEditDate(day.date)" class="d-flex flex-column gap-2 w-100">
                                                <button
                                                    @click="openQuickModal(day)"
                                                    class="flex-grow-1 stitch-btn primary rounded-3 border-2 border-dashed d-flex align-items-center justify-content-center gap-2"
                                                    style="height: 3rem"
                                                >
                                                    <i class="bi bi-plus-circle"></i>
                                                    <span class="text-sm font-semibold">Tambahkan Shift</span>
                                                </button>
                                                <span class="text-xs text-center text-muted" style="font-size: 0.7rem">
                                                    <i class="bi bi-info-circle"></i>
                                                    Belum ada shift terjadwal
                                                </span>
                                            </div>
                                            <!-- Di luar jangkauan: tidak bisa edit, tapi tampilkan status kosong saja -->
                                            <div
                                                v-else
                                                class="d-flex align-items-center justify-content-center gap-2 p-3 rounded-3 bg-light border text-muted"
                                                style="width: 100%; min-height: 3rem"
                                            >
                                                <i class="bi bi-calendar-x" style="font-size: 1.2rem; opacity: 0.5"></i>
                                                <div class="text-center">
                                                    <div class="fw-medium text-sm">Tidak Ada Shift</div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Weekend -->
                                        <template v-else>
                                            <span
                                                class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 bg-light text-muted fw-medium small"
                                            >
                                                <i class="bi bi-cloud-sun"></i>
                                                Libur Mingguan
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-else class="row justify-content-center fade-in">
                                <div class="col-12">
                                    <div class="loading-state">
                                        <div class="loading-spinner">
                                            <i
                                                class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <h4 class="loading-title">Memuat Jadwal...</h4>
                                        <p class="loading-description">Sedang mengambil data shift</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Riwayat -->
                    <template #riwayat>
                        <div class="p-6 rounded-4" style="background: var(--surface-soft)">
                            <!-- Page Header -->
                            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                                <div class="d-flex flex-column gap-1">
                                    <h1 class="text-dark text-2xl font-extrabold leading-tight tracking-tight">
                                        Riwayat Pengajuan Shift
                                    </h1>
                                    <p class="text-muted text-sm">
                                        Manage and track your shift change requests history.
                                    </p>
                                </div>
                                <button
                                    @click="showWizard = true"
                                    class="stitch-btn primary"
                                    style="box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.3)"
                                >
                                    <i class="bi bi-plus-lg"></i>
                                    <span>New Request</span>
                                </button>
                            </div>

                            <!-- Filter Section (ModernFilter) -->
                            <div class="mb-6">
                                <ModernFilter
                                    :config="historyFilterConfig"
                                    :filters="historyFilter"
                                    @filters-update="handleFilterUpdate"
                                >
                                    <!-- Optional Action Slot -->
                                    <template #actions>
                                        <!-- Add export button or similar if needed -->
                                    </template>
                                </ModernFilter>
                            </div>

                            <!-- Loading State for History -->
                            <div v-if="loadingHistory" class="row justify-content-center fade-in">
                                <div class="col-12">
                                    <div class="loading-state">
                                        <div class="loading-spinner">
                                            <i
                                                class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <h4 class="loading-title">Memuat Riwayat...</h4>
                                        <p class="loading-description">Sedang mengambil data riwayat pengajuan</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Section (Stitch Style) - Desktop -->
                            <div
                                v-else-if="!loadingHistory"
                                class="d-none d-md-block bg-white rounded-3 border shadow-sm overflow-hidden"
                            >
                                <div class="table-responsive">
                                    <table class="stitch-table">
                                        <thead>
                                            <tr>
                                                <th>No Transaksi</th>
                                                <th>Tanggal</th>
                                                <th>Shift</th>
                                                <th>Tipe</th>
                                                <th>Dibuat</th>
                                                <th>Status</th>
                                                <th class="text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="historyData.length === 0">
                                                <td colspan="7" class="text-center py-8 text-muted">
                                                    <div class="flex flex-col items-center gap-2">
                                                        <i class="bi bi-inbox text-3xl text-gray-400"></i>
                                                        <span>Belum ada riwayat pengajuan</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-for="item in historyData" :key="item.No_Transaksi">
                                                <td class="font-medium text-dark">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i
                                                            :class="
                                                                item.transaction_type === 'TUKAR_HARI'
                                                                    ? 'bi bi-arrow-left-right text-warning'
                                                                    : 'bi bi-calendar-check text-primary'
                                                            "
                                                            style="font-size: 1.2rem"
                                                        ></i>
                                                        <span>{{ item.No_Transaksi }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-muted">
                                                    <template v-if="item.transaction_type === 'TUKAR_HARI'">
                                                        1 Tukar Hari
                                                    </template>
                                                    <template v-else>
                                                        {{ item.tanggal_count }}
                                                        Hari
                                                    </template>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span class="text-dark font-medium">{{
                                                            item.shift_display
                                                        }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-muted">
                                                    <span
                                                        class="badge"
                                                        :class="
                                                            item.transaction_type === 'TUKAR_HARI'
                                                                ? 'bg-warning text-dark'
                                                                : 'bg-primary'
                                                        "
                                                    >
                                                        {{
                                                            item.transaction_type === 'TUKAR_HARI'
                                                                ? 'Tukar Hari'
                                                                : item.Target_Table
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="text-muted small">
                                                    {{ formatDateTime(item.Created_At) }}
                                                </td>
                                                <td>
                                                    <span
                                                        class="stitch-badge"
                                                        :class="statusRiwayat(item).kelas"
                                                        :title="item.catatan_batal_admin || null"
                                                    >
                                                        {{ statusRiwayat(item).label }}
                                                    </span>
                                                    <div v-if="item.catatan_batal_admin" class="text-muted small mt-1">
                                                        {{ item.catatan_batal_admin }}
                                                    </div>
                                                </td>
                                                <td
                                                    class="text-right gap-2 d-flex justify-content-end align-items-center"
                                                >
                                                    <button
                                                        @click="viewDetail(item.No_Transaksi)"
                                                        class="stitch-action-btn"
                                                        title="Lihat Detail"
                                                        style="width: 36px; height: 36px"
                                                    >
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button
                                                        v-if="item.Flag_Selesai !== 'Y' && !hasAnyApprovalAction(item)"
                                                        @click="cancelRequest(item.No_Transaksi)"
                                                        class="stitch-btn danger"
                                                        title="Batalkan Pengajuan"
                                                        style="width: 36px; height: 36px"
                                                    >
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile List View -->
                            <div v-if="!loadingHistory" class="d-md-none d-flex flex-column gap-3">
                                <div
                                    v-if="historyData.length === 0"
                                    class="text-center py-5 text-muted bg-white rounded-3 border shadow-sm"
                                >
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="bi bi-inbox text-3xl text-gray-400"></i>
                                        <span>Belum ada riwayat pengajuan</span>
                                    </div>
                                </div>

                                <div
                                    v-for="item in historyData"
                                    :key="'mobile-' + item.No_Transaksi"
                                    class="bg-white rounded-3 border shadow-sm p-3 position-relative"
                                    :style="
                                        item.transaction_type === 'TUKAR_HARI'
                                            ? 'border-left: 4px solid #ffc107 !important;'
                                            : 'border-left: 4px solid #0d6efd !important;'
                                    "
                                >
                                    <!-- Header: No Transaksi & Status -->
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i
                                                :class="
                                                    item.transaction_type === 'TUKAR_HARI'
                                                        ? 'bi bi-arrow-left-right text-warning'
                                                        : 'bi bi-calendar-check text-primary'
                                                "
                                                style="font-size: 1.5rem"
                                            ></i>
                                            <div>
                                                <div class="text-xs text-muted mb-1">No. Transaksi</div>
                                                <div class="font-medium text-dark text-sm">
                                                    {{ item.No_Transaksi }}
                                                </div>
                                            </div>
                                        </div>
                                        <span class="stitch-badge" :class="statusRiwayat(item).kelas">
                                            {{ statusRiwayat(item).label }}
                                        </span>
                                    </div>
                                    <div v-if="item.catatan_batal_admin" class="text-muted small">
                                        {{ item.catatan_batal_admin }}
                                    </div>

                                    <!-- Divider -->
                                    <div class="my-3 border-t border-gray-100"></div>

                                    <!-- Details Grid -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="text-muted small mb-1" style="font-size: 0.75rem">
                                                <template v-if="item.transaction_type === 'TUKAR_HARI'">
                                                    Jenis Transaksi
                                                </template>
                                                <template v-else> Jumlah Hari </template>
                                            </div>
                                            <div class="small text-dark fw-medium">
                                                <template v-if="item.transaction_type === 'TUKAR_HARI'">
                                                    <span class="badge bg-warning text-dark">Tukar Hari</span>
                                                </template>
                                                <template v-else>
                                                    {{ item.tanggal_count }}
                                                    Hari
                                                </template>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small mb-1" style="font-size: 0.75rem">Tipe</div>
                                            <div class="small text-dark fw-medium">
                                                <template v-if="item.transaction_type === 'TUKAR_HARI'"> - </template>
                                                <template v-else>
                                                    {{ item.Target_Table }}
                                                </template>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="text-muted small mb-1" style="font-size: 0.75rem">Dibuat</div>
                                            <div class="small text-dark fw-medium">
                                                {{ formatDateTime(item.Created_At) }}
                                            </div>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="text-muted small mb-1" style="font-size: 0.75rem">Shift</div>
                                            <div class="text-dark fw-medium small">
                                                {{ item.shift_display }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="d-flex justify-content-end gap-2">
                                        <button
                                            v-if="item.Flag_Selesai !== 'Y' && !hasAnyApprovalAction(item)"
                                            @click="cancelRequest(item.No_Transaksi)"
                                            class="stitch-btn danger small px-3 py-1 h-auto d-flex align-items-center"
                                        >
                                            <i class="bi bi-x-circle"></i> Batal
                                        </button>
                                        <button
                                            @click="viewDetail(item.No_Transaksi)"
                                            class="stitch-btn secondary small px-3 py-1 h-auto w-auto d-flex align-items-center"
                                            title="Lihat Detail"
                                        >
                                            <i class="bi bi-eye"></i> Detail
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination (Stitch UI) -->
                            <div
                                v-if="historyPagination.total_pages > 1"
                                class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light"
                            >
                                <button
                                    class="stitch-btn secondary small"
                                    :disabled="!historyPagination.prev_page_url"
                                    @click="fetchHistory(historyPagination.prev_page_url)"
                                >
                                    <i class="bi bi-chevron-left me-1"></i>
                                    <span class="d-none d-md-block">Previous</span>
                                </button>
                                <span class="small text-muted fw-medium">
                                    Halaman
                                    <span class="text-dark">{{ historyPagination.current_page }}</span>
                                    dari
                                    <span class="text-dark">{{
                                        historyPagination.total_pages || historyPagination.last_page || 1
                                    }}</span>
                                </span>
                                <button
                                    class="stitch-btn secondary small"
                                    :disabled="!historyPagination.next_page_url"
                                    @click="fetchHistory(historyPagination.next_page_url)"
                                >
                                    <span class="d-none d-md-block">Next</span>
                                    <i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Quick Modal (Calendar Click) - Stitch Style -->
        <Modal
            :show="showQuickModal"
            :title="`Tambah Shift - ${quickModalData.date ? formatDate(quickModalData.date) : ''}`"
            size="medium"
            @close="showQuickModal = false"
        >
            <template #body>
                <div v-if="!loadingState.quickModal" class="stitch-modal-body" style="padding: 0">
                    <button
                        type="button"
                        class="stitch-btn secondary w-100 mb-3 d-flex align-items-center justify-content-center gap-2"
                        @click="bukaJejakHari(quickModalData.date)"
                    >
                        <HcisIcon name="clock-history" size="sm" /> Lihat riwayat jadwal tanggal ini
                    </button>
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
                                {{ quickModalData.currentShift.jam_masuk }} -
                                {{ quickModalData.currentShift.jam_keluar }}
                            </div>
                        </div>
                    </div>

                    <!-- Toggle jadikan Dayoff-->
                    <!-- <div class="stitch-toggle mb-4">
                        <div class="d-flex flex-column">
                            <span class="text-dark font-semibold text-sm"
                                >Jadikan Dayoff</span
                            >
                            <span class="text-muted text-xs"
                                >Ubah shift hari ini menjadi libur (OFF)</span
                            >
                        </div>
                        <label
                            style="min-width: 100px"
                            class="position-relative d-flex items-center flex-column justify-content-end align-items-end"
                        >
                            <label class="toggle-switch">
                                <input
                                    type="checkbox"
                                    v-model="quickModalData.makeDayoff"
                                    @change="handleDayoffToggle"
                                    :disabled="
                                        quickModalData.hasTukarHari ||
                                        isDateOutOfRange(quickModalData.date)
                                    "
                                />
                                <span
                                    class="slider"
                                    :class="{
                                        disabled:
                                            quickModalData.hasTukarHari ||
                                            isDateOutOfRange(
                                                quickModalData.date,
                                            ),
                                    }"
                                ></span>
                            </label>

                            <span
                                class="toggle-label"
                                :class="{
                                    'text-muted':
                                        quickModalData.hasTukarHari ||
                                        isDateOutOfRange(quickModalData.date),
                                }"
                            >
                                {{
                                    quickModalData.makeDayoff
                                        ? "Day Off"
                                        : "Hari Kerja"
                                }}
                            </span>
                            <span
                                v-if="quickModalData.hasTukarHari"
                                class="text-xs text-muted mt-1"
                                style="font-size: 0.65rem"
                            >
                                (Tukar Hari Aktif)
                            </span>
                            <span
                                v-else-if="
                                    isDateOutOfRange(quickModalData.date)
                                "
                                class="text-xs text-muted mt-1"
                                style="font-size: 0.65rem"
                            >
                                (Di Luar Jangkauan Shift)
                            </span>
                        </label>
                    </div> -->

                    <!-- Button pop up modal tukar Hari -->
                    <div class="mb-4 d-flex">
                        <button
                            style="flex-grow: 1"
                            @click="openSwapDayModal(quickModalData.makeDayoff, quickModalData.date)"
                            class="stitch-btn secondary d-flex align-items-center gap-2"
                            :disabled="quickModalData.hasTukarHari || isTukarHariDateOutOfRange(quickModalData.date)"
                            :class="{
                                'opacity-50 cursor-default':
                                    quickModalData.hasTukarHari || isTukarHariDateOutOfRange(quickModalData.date),
                            }"
                        >
                            <i class="bi bi-arrow-left-right"></i>
                            <span>{{
                                quickModalData.hasTukarHari
                                    ? 'Tukar Hari Sudah Diajukan'
                                    : isTukarHariDateOutOfRange(quickModalData.date)
                                      ? 'Tukar Hari Di Luar Jangkauan'
                                      : 'Tukar Hari dengan Tanggal Lain'
                            }}</span>
                        </button>
                    </div>

                    <div
                        v-if="quickModalData.onlySwapMode"
                        class="flex items-center gap-3 p-3 rounded-lg text-sm mb-4"
                        style="background: #e0f2fe; border: 1px solid #7dd3fc; color: #0c4a6e"
                    >
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            Hari ini adalah hari libur. Untuk kondisi ini, hanya
                            <strong>Tukar Hari</strong> yang dapat diajukan.
                        </span>
                    </div>

                    <!-- Shift Select -->
                    <div class="flex flex-col gap-2 mb-4">
                        <label class="stitch-filter-label">Pilih Shift</label>
                        <div
                            v-if="
                                isDateOutOfRange(quickModalData.date) && !isTukarHariDateOutOfRange(quickModalData.date)
                            "
                            class="flex items-center gap-2 p-2 rounded-lg text-xs mb-2"
                            style="background: #e0f2fe; border: 1px solid #7dd3fc; color: #0c4a6e"
                        >
                            <i class="bi bi-info-circle-fill"></i>
                            <span>
                                Tanggal ini di luar jangkauan shift, tetapi Anda masih dapat mengajukan Tukar Hari.
                            </span>
                        </div>
                        <el-select
                            v-model="quickModalData.newShiftId"
                            filterable
                            clearable
                            :disabled="
                                quickModalData.onlySwapMode ||
                                quickModalData.makeDayoff ||
                                isDateOutOfRange(quickModalData.date)
                            "
                            placeholder="Cari atau pilih shift..."
                            class="el-select-full"
                            popper-class="el-select-popper-high"
                            :loading="loadingState.shiftForDay"
                        >
                            <el-option
                                v-for="shift in shiftOptions"
                                :key="shift.id"
                                :label="getShiftLabel(shift)"
                                :value="shift.id"
                            />
                        </el-select>
                        <div
                            v-if="
                                !loadingState.shiftForDay &&
                                !quickModalData.onlySwapMode &&
                                !quickModalData.makeDayoff &&
                                !isDateOutOfRange(quickModalData.date) &&
                                !shiftOptions.length
                            "
                            class="text-warning small mt-2"
                        >
                            Tidak ada shift tersedia pada tanggal ini sesuai rule shifting. Coba tanggal lain atau
                            hubungi admin HR/Shift Rule.
                        </div>
                        <div class="text-muted text-xs mt-1">
                            Opsi shift bisa berubah per tanggal karena mengikuti rule.
                        </div>
                    </div>

                    <!-- Make Permanent Toggle -->
                    <div class="stitch-toggle mb-4">
                        <div class="d-flex flex-column">
                            <span class="text-dark font-semibold text-sm">Jadikan Permanen</span>
                            <span class="text-muted text-xs"
                                >Buat shift ini dari {{ getDayName(quickModalData.date) }} s.d seterusnya shift
                                ini</span
                            >
                        </div>
                        <label
                            style="min-width: 100px"
                            class="position-relative d-flex items-center flex-column justify-content-end align-items-end"
                        >
                            <label class="toggle-switch">
                                <input
                                    :disabled="
                                        quickModalData.onlySwapMode ||
                                        quickModalData.makeDayoff ||
                                        isDateOutOfRange(quickModalData.date)
                                    "
                                    type="checkbox"
                                    v-model="quickModalData.makePermanent"
                                />
                                <span class="slider"></span>
                            </label>

                            <span class="toggle-label">
                                {{ quickModalData.makePermanent ? 'Permanen' : 'Sementara' }}
                            </span>
                            <!-- <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div> -->
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
                        class="flex items-center gap-3 p-3 rounded-lg text-sm"
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
                        class="flex items-center gap-3 p-3 rounded-lg text-sm"
                        style="background: #fef3c7; border: 1px solid #fcd34d; color: #92400e"
                    >
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>
                            <strong>Perhatian:</strong>
                            {{ quickModalPermanentConflictCount }} shift sementara aktif setelah tanggal ini akan
                            dinonaktifkan.
                        </span>
                    </div>

                    <!-- Reason Input -->
                    <div class="flex flex-col gap-2 mb-4">
                        <div class="flex justify-between items-center">
                            <label class="stitch-filter-label">Alasan <span class="text-danger">*</span></label>
                            <span class="text-xs text-muted">{{ (quickModalData.alasan || '').length }}/200</span>
                        </div>
                        <textarea
                            v-model="quickModalData.alasan"
                            class="stitch-input"
                            rows="3"
                            placeholder="Tulis catatan tambahan untuk atasan..."
                            :disabled="quickModalData.onlySwapMode"
                            maxlength="200"
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
                            <template v-if="isBackdate(quickModalData.date)">
                                Tanggal backdate membutuhkan approval.
                            </template>
                            <template v-else-if="isSameDay(quickModalData.date)">
                                Pengajuan hari ini membutuhkan approval.
                            </template>
                            <template v-else> Pengajuan ini membutuhkan approval. </template>
                        </span>
                    </div>
                </div>
                <div v-else class="row justify-content-center fade-in">
                    <div class="col-12">
                        <div class="loading-state">
                            <div class="loading-spinner">
                                <i class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"></i>
                            </div>
                            <h4 class="loading-title">Memuat Shift...</h4>
                            <p class="loading-description">Sedang mengambil detail shift</p>
                        </div>
                    </div>
                </div>
            </template>
            <template v-if="!loadingState.quickModal" #footer>
                <!-- <div class="stitch-modal-footer" style="margin: -1.5rem; margin-top: 0; border-radius: 0 0 var(--radius) var(--radius);"> -->
                <button @click="showQuickModal = false" class="stitch-btn secondary flex-grow-1 flex-md-grow-0">
                    Batal
                </button>
                <button
                    @click="submitQuickModal"
                    class="stitch-btn primary flex-grow-1 flex-md-grow-0"
                    :disabled="submitting || quickModalData.onlySwapMode"
                    style="box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.3)"
                >
                    <span v-if="submitting">
                        <i class="bi bi-arrow-repeat tengahkan" style="animation: spin 1s linear infinite"></i>
                        Memproses...
                    </span>
                    <span v-else>Simpan Shift</span>
                </button>
                <!-- </div> -->
            </template>
        </Modal>

        <!-- Riwayat jadwal — juga untuk tanggal yang tak bisa diubah lagi -->
        <JejakJadwalHariDrawer
            v-model="jejakHari.buka"
            endpoint="/self-shift/jejak-hari"
            :tanggal="jejakHari.tanggal"
        />

        <!-- Swap Confirmation Modal -->
        <Modal :show="showSwapConfirmModal" title="Konfirmasi Tukar Hari" size="medium" @close="cancelSwapConfirm">
            <template #body>
                <div class="swap-modal-body">
                    <div class="swap-section">
                        <div class="swap-section-label">
                            <i class="bi bi-clipboard-check"></i>
                            Ringkasan Tukar Hari
                        </div>
                        <div class="swap-confirm-grid">
                            <div class="swap-confirm-card">
                                <div class="swap-confirm-title">Tanggal Asal</div>
                                <div class="swap-confirm-date">
                                    {{ formatDate(quickModalData.date) }}
                                </div>
                                <div class="swap-confirm-pill">
                                    <span class="swap-confirm-tag" :class="quickModalData.makeDayoff ? 'off' : 'work'">
                                        {{ quickModalData.makeDayoff ? 'OFF' : 'WORK' }}
                                    </span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="swap-confirm-tag" :class="quickModalData.makeDayoff ? 'work' : 'off'">
                                        {{ quickModalData.makeDayoff ? 'WORK' : 'OFF' }}
                                    </span>
                                </div>
                            </div>
                            <div class="swap-confirm-card">
                                <div class="swap-confirm-title">Tanggal Tukar</div>
                                <div class="swap-confirm-date">
                                    {{ formatDate(selectedSwapDate) }}
                                </div>
                                <div class="swap-confirm-pill">
                                    <span class="swap-confirm-tag" :class="quickModalData.makeDayoff ? 'work' : 'off'">
                                        {{ quickModalData.makeDayoff ? 'WORK' : 'OFF' }}
                                    </span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="swap-confirm-tag" :class="quickModalData.makeDayoff ? 'off' : 'work'">
                                        {{ quickModalData.makeDayoff ? 'OFF' : 'WORK' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="swap-confirm-summary">
                            <!-- <div class="swap-confirm-row">
                                <div class="swap-confirm-label">
                                    Tanggal Bekerja
                                </div>
                                <div class="swap-confirm-value">
                                    {{ formatDate(getSwapWorkingDate()) }}
                                </div>
                                <div class="swap-confirm-meta">
                                    {{ getSelectedSwapShiftLabel() }}
                                </div>
                            </div>
                            <div class="swap-confirm-row">
                                <div class="swap-confirm-label">
                                    Tanggal Libur
                                </div>
                                <div class="swap-confirm-value">
                                    {{ formatDate(getSwapOffDate()) }}
                                </div>
                                <div class="swap-confirm-meta">
                                    <span class="swap-confirm-tag off"
                                        >OFF</span
                                    >
                                </div>
                            </div> -->
                            <div class="swap-confirm-row">
                                <div class="swap-confirm-label">Alasan</div>
                                <div class="swap-confirm-value">
                                    {{ swapReason || '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="stitch-btn secondary flex-grow-1 flex-md-grow-0" @click="cancelSwapConfirm">
                    Periksa Lagi
                </button>
                <button
                    class="stitch-btn primary flex-grow-1 flex-md-grow-0"
                    :disabled="swapSubmitting"
                    @click="confirmSwapDayRequest"
                >
                    <span v-if="swapSubmitting">
                        <i class="bi bi-arrow-repeat"></i>
                        Memproses...
                    </span>
                    <span v-else>Konfirmasi & Kirim</span>
                </button>
            </template>
        </Modal>

        <!-- Tukar Hari Modal - Stitch Style -->
        <Modal :show="showSwapDayModal" title="Request Shift Swap" size="large" @close="closeSwapDayModal">
            <template #body>
                <div class="swap-modal-body">
                    <div class="swap-section">
                        <div class="swap-section-label">
                            <i class="bi bi-calendar-check"></i>
                            Shift Saya yang Ditukar
                        </div>
                        <div class="swap-origin-card">
                            <div class="swap-origin-date">
                                <div class="swap-origin-day">
                                    {{ getDayName(quickModalData.date).substring(0, 3) }}
                                </div>
                                <div class="swap-origin-num">
                                    {{ getDateNumber(quickModalData.date) }}
                                </div>
                            </div>
                            <div class="swap-origin-info">
                                <div class="swap-origin-date-text">
                                    {{ formatDate(quickModalData.date) }}
                                </div>
                                <div class="swap-origin-role">
                                    {{ quickModalData.currentShift ? quickModalData.currentShift.nama : '-' }}
                                </div>
                                <div class="swap-origin-time">
                                    <i class="bi bi-clock"></i>
                                    {{ quickModalData.currentShift ? quickModalData.currentShift.jam_masuk : '--:--' }}
                                    -
                                    {{ quickModalData.currentShift ? quickModalData.currentShift.jam_keluar : '--:--' }}
                                </div>
                            </div>
                            <div class="d-flex justify-content-center align-items-center gap-2 mt-3">
                                <div class="swap-origin-badge" :class="{ off: quickModalData.makeDayoff }">
                                    {{ quickModalData.makeDayoff ? 'OFF' : 'WORK' }}
                                </div>
                                to
                                <div class="swap-origin-badge" :class="{ off: !quickModalData.makeDayoff }">
                                    {{ quickModalData.makeDayoff ? 'WORK' : 'OFF' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="swap-divider">
                        <span class="swap-divider-icon">
                            <i class="bi bi-arrow-down-up"></i>
                        </span>
                    </div>

                    <div class="swap-section">
                        <div class="swap-section-header">
                            <div class="swap-section-label">
                                <i class="bi bi-calendar2-week"></i>
                                {{ !quickModalData.makeDayoff ? 'Pilih Tanggal Kerja' : 'Pilih Tanggal Libur' }}
                            </div>
                            <div class="swap-month-label">
                                {{ swapMonthLabel }}
                            </div>
                        </div>

                        <div class="swap-date-grid">
                            <button
                                v-for="(item, idx) in swapDates"
                                :key="getSwapDateValue(item) || idx"
                                type="button"
                                class="swap-date-item"
                                :class="getSwapDateClass(item)"
                                :disabled="isSwapDateDisabled(item)"
                                @click="selectSwapDate(item, quickModalData.makeDayoff)"
                            >
                                <div v-if="item && item.isActive == false" class="swap-date-tooltip">
                                    {{ getKeteranganSwapDay(item) }}
                                </div>
                                <div class="swap-date-day">
                                    {{ getSwapDateDayLabel(item) }}
                                </div>
                                <div class="swap-date-num">
                                    {{ getSwapDateNumber(item) }}
                                </div>
                                <div class="swap-date-month-year">
                                    {{ getSwapDateMonthYearLabel(item) }}
                                </div>
                                <div class="swap-date-status" :class="getSwapStatusMeta(item).className">
                                    {{ getSwapStatusMeta(item).label }}
                                </div>
                            </button>

                            <div v-if="loadingState.swapDay" class="swap-date-loading">
                                <i class="bi bi-arrow-repeat"></i>
                                Memuat tanggal...
                            </div>

                            <div v-else-if="swapDates.length === 0" class="swap-date-empty">
                                Tidak ada tanggal yang tersedia.
                            </div>
                        </div>
                    </div>

                    <div class="swap-section">
                        <div class="swap-section-label">
                            <i class="bi bi-people"></i>
                            Shift Tersedia untuk
                            {{
                                !quickModalData.makeDayoff
                                    ? `${formatDate(selectedSwapDate)}`
                                    : `${formatDate(quickModalData.date)}`
                            }}
                        </div>

                        <div class="">
                            <div v-if="!loadingState.swapDayShift" class="swap-shift-list">
                                <label
                                    v-for="shift in swapAvailableShifts"
                                    :key="getSwapShiftKey(shift)"
                                    class="swap-shift-item"
                                    :class="{
                                        selected: swapSelectedShiftId === getSwapShiftValue(shift),
                                    }"
                                >
                                    <input
                                        type="radio"
                                        name="swapShift"
                                        :value="getSwapShiftValue(shift)"
                                        v-model="swapSelectedShiftId"
                                    />
                                    <div class="swap-shift-main">
                                        <div class="swap-shift-title">
                                            {{ getSwapShiftName(shift) }}
                                            <span v-if="shift.flag_longshift == 'Y'" class="stitch-badge approved">
                                                + up to
                                                {{ shift.durasi_longshift || 0 }}
                                                Jam Lembur
                                            </span>
                                        </div>
                                        <div class="swap-shift-sub">
                                            <i class="bi bi-clock"></i>
                                            {{ getSwapShiftTime(shift) }}
                                        </div>
                                    </div>
                                    <div class="swap-shift-meta">
                                        <span v-if="isShiftCompatible(shift)" class="swap-chip success"
                                            >Compatible Role</span
                                        >
                                    </div>
                                </label>

                                <div v-if="swapAvailableShifts.length === 0" class="swap-shift-empty">
                                    <template v-if="selectedSwapDate">
                                        Tidak ada shift tersedia pada skenario tukar ini sesuai rule shifting. Coba
                                        tanggal target lain atau hubungi admin HR/Shift Rule.
                                    </template>
                                    <template v-else> Pilih tanggal untuk melihat shift tersedia. </template>
                                </div>
                            </div>
                            <div v-else class="swap-date-loading">
                                <i class="bi bi-arrow-repeat"></i>
                                Memuat shift...
                            </div>
                        </div>
                    </div>

                    <div class="swap-section">
                        <div class="swap-section-label">
                            <i class="bi bi-chat-left-text"></i>
                            Alasan Tukar <span class="text-danger">*</span>
                        </div>
                        <textarea
                            v-model="swapReason"
                            class="swap-reason-input"
                            rows="3"
                            placeholder="Tulis alasan Anda..."
                        ></textarea>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="stitch-btn secondary flex-grow-1 flex-md-grow-0" @click="closeSwapDayModal">
                    Batal
                </button>
                <button
                    class="stitch-btn primary flex-grow-1 flex-md-grow-0"
                    :disabled="swapSubmitting"
                    @click="submitSwapDayRequest"
                    style="box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.3)"
                >
                    Kirim Permintaan
                </button>
            </template>
        </Modal>

        <!-- Wizard Modal - Stitch Style -->
        <Modal :show="showWizard" title="Pengajuan Shift Baru" size="large" @close="showWizard = false">
            <template #body>
                <!-- Stitch Stepper -->
                <div class="stitch-stepper">
                    <div
                        v-for="(step, index) in wizardSteps"
                        :key="index"
                        class="stitch-step"
                        :class="{
                            active: wizardCurrentStep === index,
                            completed: wizardCurrentStep > index,
                        }"
                    >
                        <div class="stitch-step-circle">
                            <i v-if="wizardCurrentStep > index" class="bi bi-check"></i>
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span class="stitch-step-label">{{ step }}</span>
                    </div>
                </div>

                <div style="height: 100%">
                    <!-- Step 1: Pilih Tipe -->
                    <div v-if="wizardCurrentStep === 0">
                        <div class="text-center mb-6">
                            <h3 class="text-dark text-xl font-bold mb-2">Pilih Tipe Perubahan</h3>
                            <p class="text-muted text-sm">
                                Tentukan apakah perubahan shift ini bersifat sementara atau permanen
                            </p>
                        </div>

                        <div class="stitch-type-grid">
                            <div
                                class="stitch-type-card"
                                :class="{
                                    selected: wizardData.targetTable === 'SEMENTARA',
                                }"
                                @click="
                                    wizardData.targetTable = 'SEMENTARA';
                                    this.wizardData.dates = [];
                                "
                            >
                                <div class="stitch-type-icon">
                                    <i class="bi bi-calendar-day"></i>
                                </div>
                                <h4 class="text-dark font-bold text-lg mb-2">Sementara</h4>
                                <p class="text-muted text-sm">
                                    Pergantian shift untuk tanggal tertentu saja. Cocok untuk tukar shift atau lembur
                                    harian.
                                </p>
                            </div>
                            <div
                                class="stitch-type-card"
                                :class="{
                                    selected: wizardData.targetTable === 'PERMANEN',
                                }"
                                @click="
                                    wizardData.targetTable = 'PERMANEN';
                                    this.wizardData.dates = [];
                                "
                            >
                                <div class="stitch-type-icon">
                                    <i class="bi bi-infinity"></i>
                                </div>
                                <h4 class="text-dark font-bold text-lg mb-2">Permanen</h4>
                                <p class="text-muted text-sm">
                                    Shift default baru mulai dari tanggal terpilih. Cocok untuk rotasi shift jangka
                                    panjang.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Pilih Tanggal -->
                    <div v-if="wizardCurrentStep === 1" style="height: 100%">
                        <div class="text-center mb-4">
                            <h3 class="text-dark text-xl font-bold mb-2">Pilih Tanggal</h3>
                            <p class="text-muted text-sm">
                                <template v-if="wizardData.targetTable === 'SEMENTARA'"
                                    >Pilih satu atau lebih tanggal untuk perubahan sementara</template
                                >
                                <template v-else>Pilih tanggal mulai berlaku shift permanen</template>
                            </p>
                        </div>

                        <!-- Element Plus Multiple Date Picker -->
                        <div class="date-picker-wrapper">
                            <el-date-picker
                                v-model="selectedDates"
                                :type="wizardData.targetTable === 'SEMENTARA' ? 'dates' : 'date'"
                                placeholder="Pilih tanggal"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                :disabled-date="disabledDate"
                                :teleported="true"
                                :clearable="true"
                                :popper-options="{ placement: 'bottom-start' }"
                                popper-class="el-date-picker-popper-high"
                                class="el-date-picker-full"
                                @change="onDatePickerChange"
                            />
                        </div>

                        <!-- Wizard Overwrite Toggle -->
                        <div
                            v-if="wizardData.targetTable === 'PERMANEN'"
                            class="d-flex align-items-center justify-content-between px-3 py-2 mb-3 rounded-3"
                            style="background: #f8f9fa; border: 1px solid #e9ecef"
                        >
                            <div class="d-flex flex-column" style="gap: 1px">
                                <span class="fw-medium" style="font-size: 0.8rem; color: #374151">
                                    Timpa shift sementara di depannya
                                </span>
                                <span class="text-muted" style="font-size: 0.68rem">
                                    Nonaktifkan shift sementara setelah tanggal ini
                                </span>
                            </div>
                            <label class="toggle-switch-sm ms-2 mb-0">
                                <input type="checkbox" v-model="wizardOverwriteSementara" />
                                <span class="slider-sm"></span>
                            </label>
                        </div>

                        <div
                            v-if="
                                wizardData.targetTable === 'PERMANEN' &&
                                wizardOverwriteSementara &&
                                wizardPermanentConflictLoading
                            "
                            class="flex items-center gap-3 p-3 rounded-lg text-sm"
                            style="background: #fef3c7; border: 1px solid #fcd34d; color: #92400e"
                        >
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Memeriksa shift sementara yang akan ditimpa...</span>
                        </div>
                        <div
                            v-else-if="
                                wizardData.targetTable === 'PERMANEN' &&
                                wizardOverwriteSementara &&
                                wizardPermanentConflictCount > 0
                            "
                            class="flex items-center gap-3 p-3 rounded-lg text-sm"
                            style="background: #fef3c7; border: 1px solid #fcd34d; color: #92400e"
                        >
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>
                                <strong>Perhatian:</strong>
                                {{ wizardPermanentConflictCount }} shift sementara aktif setelah tanggal ini akan
                                dinonaktifkan.
                            </span>
                        </div>

                        <!-- Selected Dates Summary -->
                        <div v-if="wizardData.dates.length > 0" class="dates-summary-section">
                            <div class="dates-summary-header">
                                <span class="dates-count">Tanggal Terpilih ({{ wizardData.dates.length }})</span>
                                <button
                                    v-if="wizardData.targetTable === 'SEMENTARA'"
                                    @click="clearAllDates"
                                    class="clear-dates-btn"
                                >
                                    <i class="bi bi-trash3"></i>
                                    Hapus
                                </button>
                            </div>
                            <div class="stitch-date-chips">
                                <div v-for="(d, idx) in wizardData.dates" :key="idx" class="stitch-date-chip">
                                    <i class="bi bi-calendar2-check"></i>
                                    <span>{{ formatDateShort(d.tanggal) }}</span>
                                    <button
                                        v-if="wizardData.targetTable === 'SEMENTARA'"
                                        @click="removeWizardDate(idx)"
                                        title="Hapus tanggal"
                                    >
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else
                            style="min-height: 20rem"
                            class="text-center text-muted d-flex justify-content-center align-items-center flex-column"
                        >
                            <i class="bi bi-calendar-x text-4xl mb-2 text-gray-400"></i>
                            <p class="text-sm">Klik pada kalender untuk memilih tanggal</p>
                        </div>
                    </div>

                    <!-- Step 3: Assign Shift -->
                    <div v-if="wizardCurrentStep === 2">
                        <div class="text-center mb-6">
                            <h3 class="text-dark text-xl font-bold mb-2">Assign Shift</h3>
                            <p class="text-muted text-sm">Tentukan shift untuk setiap tanggal yang dipilih</p>
                        </div>

                        <!-- Quick Assign All -->
                        <div
                            class="stitch-card mb-4"
                            style="
                                flex-direction: row;
                                align-items: center;
                                gap: 1rem;
                                background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
                                border-color: var(--primary-light);
                            "
                        >
                            <div class="flex flex-col flex-1">
                                <span class="text-primary font-semibold text-sm">Quick Assign to All</span>
                                <span class="text-muted text-xs">Apply the same shift across all dates</span>
                            </div>
                            <div class="flex-1">
                                <el-select
                                    v-model="applyAllShiftId"
                                    filterable
                                    clearable
                                    placeholder="Select shift..."
                                    class="el-select-full"
                                    popper-class="el-select-popper-high"
                                    :loading="masterLoading"
                                    @visible-change="
                                        (val) => {
                                            if (val) loadMasterShifts();
                                        }
                                    "
                                >
                                    <el-option
                                        v-for="shift in masterShiftOptions"
                                        :key="shift.id"
                                        :label="getShiftLabel(shift)"
                                        :value="shift.id"
                                    />
                                </el-select>
                            </div>
                            <button @click="applyToAll" class="stitch-btn primary" :disabled="!applyAllShiftId">
                                Apply
                            </button>
                        </div>

                        <!-- Per Date Assignment -->
                        <div class="flex flex-col gap-3" style="max-height: 400px; overflow-y: auto">
                            <div v-for="(d, idx) in shortedWizardDates" :key="idx" class="stitch-roster-item">
                                <div class="stitch-date-box" style="min-width: 80px">
                                    <span class="stitch-date-day">{{ getDayName(d.tanggal).substring(0, 3) }}</span>
                                    <span class="stitch-date-num" style="font-size: 1.25rem">{{
                                        d.tanggal?.split('-')[2]
                                    }}</span>
                                </div>
                                <div class="flex-1">
                                    <el-select
                                        v-model="d.id_shift"
                                        filterable
                                        clearable
                                        placeholder="Pilih shift..."
                                        class="el-select-full"
                                        popper-class="el-select-popper-high"
                                        :loading="d.loading"
                                        @visible-change="
                                            (val) => {
                                                if (val) loadWizardShift(d);
                                            }
                                        "
                                    >
                                        <el-option
                                            v-for="shift in d.shiftOptions || []"
                                            :key="shift.id"
                                            :label="getShiftLabel(shift)"
                                            :value="shift.id"
                                        />
                                    </el-select>
                                    <div
                                        v-if="!d.loading && (d.shiftOptions || []).length === 0"
                                        class="text-warning text-xs mt-2"
                                    >
                                        Tanggal ini tidak punya shift yang tersedia sesuai rule.
                                    </div>
                                    <div class="mt-2">
                                        <textarea
                                            v-model="d.alasan"
                                            class="stitch-input"
                                            rows="2"
                                            maxlength="200"
                                            placeholder="Alasan per tanggal (opsional)"
                                            style="height: auto; resize: none"
                                        ></textarea>
                                    </div>
                                </div>
                                <div>
                                    <span v-if="d.id_shift" class="stitch-badge approved">
                                        <i class="bi bi-check"></i> Ready
                                    </span>
                                    <span v-else class="stitch-badge pending"> Pending </span>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted text-xs mt-2">
                            Opsi shift per tanggal mengikuti rule shifting aktif, jadi bisa berbeda antar tanggal.
                        </div>
                    </div>

                    <!-- Step 4: Review -->
                    <div v-if="wizardCurrentStep === 3">
                        <div class="text-center mb-6">
                            <h3 class="text-dark text-xl font-bold mb-2">Review & Submit</h3>
                            <p class="text-muted text-sm">Periksa kembali pengajuan sebelum dikirim</p>
                        </div>

                        <!-- Summary Info -->
                        <div
                            class="flex items-center gap-4 p-4 rounded-lg mb-4"
                            style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)"
                        >
                            <div class="stitch-card-icon blue" style="width: 3rem; height: 3rem">
                                <i class="bi bi-info-circle" style="font-size: 1.5rem"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="text-xs text-muted uppercase tracking-wider">Tipe Pengajuan</span>
                                        <p class="text-dark font-bold text-lg">
                                            {{ wizardData.targetTable }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-muted uppercase tracking-wider">Total Hari</span>
                                        <p class="text-dark font-bold text-lg">
                                            {{ wizardData.dates.length }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Approval Status Groups -->
                        <div class="flex flex-col gap-3 mb-4">
                            <div
                                v-if="needsApprovalDates.length > 0"
                                class="flex items-center gap-3 p-3 rounded-lg"
                                style="background: #fef3c7; border: 1px solid #fcd34d"
                            >
                                <i class="bi bi-exclamation-triangle-fill text-yellow-700"></i>
                                <div>
                                    <span class="text-yellow-700 font-bold text-sm"
                                        >Butuh Approval ({{ needsApprovalDates.length }})</span
                                    >
                                    <p class="text-yellow-700 text-xs opacity-75">
                                        Tanggal backdate akan menunggu persetujuan atasan.
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="directApplyDates.length > 0"
                                class="flex items-center gap-3 p-3 rounded-lg"
                                style="background: #dcfce7; border: 1px solid #86efac"
                            >
                                <i class="bi bi-check-circle-fill text-green-700"></i>
                                <div>
                                    <span class="text-green-700 font-bold text-sm"
                                        >Auto Approve ({{ directApplyDates.length }})</span
                                    >
                                    <p class="text-green-700 text-xs opacity-75">Tanggal future akan langsung aktif.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Reason Input -->
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center">
                                <label class="stitch-filter-label"
                                    >Alasan / Keterangan <span class="text-danger">*</span></label
                                >
                                <span class="text-xs text-muted">{{ (wizardData.alasan || '').length }}/500</span>
                            </div>
                            <textarea
                                v-model="wizardData.alasan"
                                class="stitch-input"
                                rows="3"
                                placeholder="Jelaskan alasan pengajuan perubahan shift ini..."
                                style="height: auto; resize: none"
                                maxlength="500"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <!-- <div class="stitch-modal-footer" style="margin: -1.5rem; margin-top: 0; border-radius: 0 0 var(--radius) var(--radius);"> -->
                <button
                    v-if="wizardCurrentStep > 0"
                    @click="wizardCurrentStep--"
                    class="stitch-btn secondary"
                    style="margin-right: auto; flex-grow: 1; background: var(--surface)"
                >
                    <i class="bi bi-chevron-left tengahkan"></i> Sebelumnya
                </button>

                <button
                    v-if="wizardCurrentStep < 3"
                    @click="wizardNextStep"
                    :disabled="!canProceedWizard"
                    class="stitch-btn primary"
                    style="margin-left: auto; flex-grow: 1"
                >
                    Lanjut <i class="bi bi-chevron-right tengahkan"></i>
                </button>
                <button
                    v-else
                    @click="submitWizard"
                    class="stitch-btn primary"
                    :disabled="submitting"
                    style="
                        margin-left: auto;
                        flex-grow: 1;
                        background: var(--success);
                        box-shadow: 0 4px 12px -2px rgba(16, 185, 129, 0.3);
                    "
                >
                    <span v-if="submitting">
                        <i class="bi bi-arrow-repeat tengahkan" style="animation: spin 1s linear infinite"></i>
                        Memproses...
                    </span>
                    <span v-else> <i class="bi bi-send-check tengahkan"></i> Submit Pengajuan </span>
                </button>
                <!-- </div> -->
            </template>
        </Modal>

        <!-- Detail Modal -->
        <Modal :show="showDetailModal" @close="showDetailModal = false" title="Detail Pengajuan" size="xlarge">
            <template #body>
                <div v-if="loadingDetail" class="">
                    <div class="loading-state">
                        <div class="loading-spinner">
                            <i
                                class="bi d-flex justify-content-center align-items-center bi-arrow-repeat d-flex justify-content-center align-items-center"
                            ></i>
                        </div>
                        <h4 class="loading-title">Memuat Data...</h4>
                        <p class="loading-description">Sedang mengambil data detail</p>
                    </div>
                </div>

                <div v-else-if="detailData && detailData.header" class="detail-modal-content">
                    <!-- Two Column Layout -->
                    <div class="detail-modal-grid">
                        <!-- Left Column: Info & Status -->
                        <div class="detail-left-column">
                            <!-- Transaction Info Card -->
                            <div class="detail-transaksi-card">
                                <div class="detail-transaksi-number">
                                    {{ detailData.header.No_Transaksi }}
                                </div>
                                <div class="detail-transaksi-type">
                                    <span class="type-badge" v-if="detailData.transaction_type === 'TUKAR_HARI'">
                                        <i class="bi bi-arrow-left-right"></i>
                                        Tukar Hari
                                    </span>
                                    <span class="type-badge" v-else>
                                        {{
                                            detailData.header.Target_Table === 'SEMENTARA'
                                                ? 'Shift Sementara'
                                                : 'Shift Permanen'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Status Review -->
                            <div class="detail-status-section">
                                <div class="detail-status-label">STATUS REVIEW</div>
                                <div
                                    class="detail-status-badge"
                                    :class="statusRiwayat(detailData.header).kelas"
                                >
                                    {{ statusRiwayat(detailData.header).label }}
                                </div>
                                <div class="detail-status-date">
                                    <i class="bi bi-calendar3"></i>
                                    Diajukan pada:
                                    {{ formatDateTime(detailData.header.Created_At) }}
                                </div>
                            </div>

                            <!-- Alur Persetujuan -->
                            <div class="detail-approval-section">
                                <div class="detail-section-title"><i class="bi bi-diagram-3"></i> Alur Persetujuan</div>
                                <div class="detail-approval-list">
                                    <div
                                        v-for="(step, idx) in detailData.approval_steps"
                                        :key="idx"
                                        class="detail-approval-item"
                                    >
                                        <div
                                            class="detail-approval-indicator"
                                            :class="{
                                                approved: step.Flag_Approval === 'Y',
                                                rejected: step.Flag_Approval === 'T',
                                                pending:
                                                    getApprovalStepState(detailData.approval_steps, idx, step) ===
                                                    'pending',
                                                skipped:
                                                    getApprovalStepState(detailData.approval_steps, idx, step) ===
                                                    'skipped',
                                            }"
                                        >
                                            <i v-if="step.Flag_Approval === 'Y'" class="bi bi-check"></i>
                                            <i v-else-if="step.Flag_Approval === 'T'" class="bi bi-x"></i>
                                            <i
                                                v-else-if="
                                                    getApprovalStepState(detailData.approval_steps, idx, step) ===
                                                    'skipped'
                                                "
                                                class="bi bi-slash-circle"
                                            ></i>
                                            <i v-else class="bi bi-hourglass-split"></i>
                                        </div>
                                        <div class="detail-approval-info">
                                            <div
                                                class="detail-approval-name"
                                                :class="{
                                                    'text-success': step.Flag_Approval === 'Y',
                                                    'text-danger': step.Flag_Approval === 'T',
                                                    'text-muted':
                                                        getApprovalStepState(detailData.approval_steps, idx, step) ===
                                                        'skipped',
                                                }"
                                            >
                                                {{ step.Nama || step.Nama_Karyawan || 'Approver' }}
                                            </div>
                                            <div class="detail-approval-date" v-if="step.Flag_Approval === 'Y'">
                                                <span class="text-success fw-medium">Disetujui</span>
                                                <span v-if="step.Tanggal_Approval">
                                                    -
                                                    {{ formatDateTime(step.Tanggal_Approval) }}
                                                </span>
                                            </div>
                                            <div class="detail-approval-date" v-else-if="step.Flag_Approval === 'T'">
                                                <span class="text-danger fw-medium">Ditolak</span>
                                                <span v-if="step.Processed_By_Nama" class="text-muted small ms-1">
                                                    oleh
                                                    {{ step.Processed_By_Nama }}
                                                </span>
                                                <span v-if="step.Processed_At">
                                                    -
                                                    {{ formatDateTime(step.Processed_At) }}
                                                </span>
                                            </div>
                                            <div
                                                class="detail-approval-date"
                                                v-else-if="
                                                    getApprovalStepState(detailData.approval_steps, idx, step) ===
                                                    'skipped'
                                                "
                                            >
                                                <span class="text-muted fw-medium">Tidak diproses</span>
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
                                        v-if="!detailData.approval_steps || detailData.approval_steps.length === 0"
                                        class="detail-approval-empty"
                                    >
                                        <i class="bi bi-check-circle text-success"></i>
                                        <span>Auto-approved (tidak memerlukan persetujuan)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Detail Info -->
                        <div class="detail-right-column">
                            <!-- ==== STATUS HARI DETAIL ==== -->
                            <div v-if="detailData.transaction_type === 'STATUS_HARI'" class="detail-info-section">
                                <div class="detail-section-title">
                                    <i class="bi bi-calendar-x"></i> Detail Status Hari
                                </div>

                                <!-- Members with their dates -->
                                <div v-for="(member, memberIdx) in detailData.members" :key="memberIdx" class="mb-4">
                                    <!-- Member Header -->
                                    <div
                                        class="detail-member-header"
                                        style="
                                            background: #f8f9fa;
                                            border-left: 3px solid #0d6efd;
                                            padding: 0.75rem;
                                            margin-bottom: 1rem;
                                            border-radius: 0.375rem;
                                        "
                                    >
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold text-dark mb-0.5">
                                                    {{ member.Nama }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ member.NIK }} -
                                                    {{ member.Nama_Jabatan }}
                                                </div>
                                            </div>
                                            <span class="stitch-badge" :class="getStatusClass(member.Flag_Approval)">
                                                {{ getStatusLabel(member.Flag_Approval) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Alasan per Member -->
                                    <div v-if="member.Alasan" class="detail-info-row mb-3">
                                        <div class="detail-info-label">Alasan</div>
                                        <div class="detail-info-value detail-alasan">
                                            {{ member.Alasan }}
                                        </div>
                                    </div>

                                    <!-- Member's Dates -->
                                    <div v-if="member.dates && member.dates.length">
                                        <div class="small text-muted mb-2">
                                            <i class="bi bi-calendar-x"></i>
                                            Tanggal Pengajuan:
                                        </div>
                                        <div class="detail-date-list">
                                            <div
                                                v-for="(date, dateIdx) in member.dates"
                                                :key="dateIdx"
                                                class="detail-date-item"
                                                style="
                                                    background: #ffffff;
                                                    border: 1px solid #e9ecef;
                                                    border-radius: 0.375rem;
                                                    padding: 0.75rem;
                                                    margin-bottom: 0.5rem;
                                                    display: flex;
                                                    justify-content: space-between;
                                                    align-items: center;
                                                "
                                            >
                                                <div>
                                                    <div class="text-dark fw-medium small">
                                                        {{ formatDate(date.Tanggal) }}
                                                    </div>
                                                    <div class="text-muted small">
                                                        Status:
                                                        {{ date.Status_Hari }}
                                                    </div>
                                                </div>
                                                <span
                                                    class="badge text-uppercase"
                                                    :class="{
                                                        'bg-info': date.Jenis_Pengajuan === 'HARI_H',
                                                        'bg-success': date.Jenis_Pengajuan === 'FUTURE',
                                                        'bg-warning text-dark': date.Jenis_Pengajuan === 'BACKDATE',
                                                    }"
                                                    style="font-size: 0.5rem; border-radius: 10px"
                                                >
                                                    {{ date.Jenis_Pengajuan }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-muted small">
                                        <i class="bi bi-info-circle"></i>
                                        Tidak ada tanggal dalam data ini
                                    </div>

                                    <!-- Divider between members -->
                                    <div
                                        v-if="memberIdx < detailData.members.length - 1"
                                        class="my-3 border-t border-gray-200"
                                    ></div>
                                </div>

                                <!-- General Alasan (if exists) -->
                                <div class="detail-info-row mt-4" v-if="detailData.header.Alasan">
                                    <div class="detail-info-label">Alasan Tambahan</div>
                                    <div class="detail-info-value detail-alasan">
                                        {{ detailData.header.Alasan }}
                                    </div>
                                </div>
                            </div>

                            <!-- ==== TUKAR HARI DETAIL ==== -->
                            <div v-if="detailData.transaction_type === 'TUKAR_HARI'" class="detail-info-section">
                                <div class="detail-section-title">
                                    <i class="bi bi-arrow-left-right"></i>
                                    Detail Tukar Hari
                                </div>

                                <!-- Tanggal Bekerja Card -->
                                <div class="detail-shift-card mb-3 border-primary">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-briefcase-fill text-primary" style="font-size: 1.5rem"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.7rem"
                                                    >Tanggal Bekerja</small
                                                >
                                                <span class="detail-shift-date fw-bold text-primary">
                                                    {{ formatDate(detailData.detail.Tanggal_Bekerja) }}
                                                </span>
                                            </div>
                                        </div>
                                        <span
                                            class="stitch-badge"
                                            :class="getStatusClass(detailData.detail.Flag_Approval)"
                                        >
                                            {{ getStatusLabel(detailData.detail.Flag_Approval) }}
                                        </span>
                                    </div>

                                    <!-- Shift Info -->
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-clock text-primary"></i>
                                            <span class="text-dark fw-semibold">{{
                                                detailData.detail.Nama_Shift
                                            }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 text-muted small">
                                            <i class="bi bi-clock"></i>
                                            {{ detailData.detail.Jam_Masuk?.substring(0, 5) || '--:--' }}
                                            -
                                            {{ detailData.detail.Jam_Keluar?.substring(0, 5) || '--:--' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Swap Icon -->
                                <div class="text-center my-3">
                                    <i class="bi bi-arrow-down-up text-warning" style="font-size: 2rem"></i>
                                </div>

                                <!-- Tanggal Off Card -->
                                <div class="detail-shift-card mb-3 border-danger">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-house-fill text-danger" style="font-size: 1.5rem"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.7rem"
                                                    >Tanggal Off / Libur</small
                                                >
                                                <span class="detail-shift-date fw-bold text-danger">
                                                    {{ formatDate(detailData.detail.Tanggal_Off) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-muted small">
                                        <i class="bi bi-info-circle"></i>
                                        Hari libur ditukar menjadi hari kerja
                                    </div>
                                </div>

                                <!-- Alasan -->
                                <div class="detail-info-row mt-4" v-if="detailData.header.Alasan">
                                    <div class="detail-info-label">Alasan</div>
                                    <div class="detail-info-value detail-alasan">
                                        {{ detailData.header.Alasan }}
                                    </div>
                                </div>
                            </div>

                            <!-- ==== SHIFT TRANSACTION DETAIL (EXISTING) ==== -->
                            <div v-else-if="detailData.transaction_type === 'SHIFT'" class="detail-info-section">
                                <div class="detail-section-title">
                                    <i class="bi bi-calendar-event"></i>
                                    {{ isDetailPermanentShift ? 'Detail Shift Permanen' : 'Detail Shift per Tanggal' }}
                                </div>
                                <!-- 
                                <div
                                    v-if="isDetailPermanentShift"
                                    class="detail-info-row mb-3"
                                >
                                    <div class="detail-info-label">Berlaku</div>
                                    <div class="detail-info-value">
                                        {{
                                            detailPermanentStartDate
                                                ? `Mulai ${formatDate(detailPermanentStartDate)} dan seterusnya`
                                                : "Mulai tanggal pengajuan dan seterusnya"
                                        }}
                                    </div>
                                </div>
                                <div
                                    v-if="isDetailPermanentShift"
                                    class="detail-info-row mb-3"
                                >
                                    <div class="detail-info-label">
                                        Bentrok Shift Sementara
                                    </div>
                                    <div class="detail-info-value">
                                        {{
                                            detailOverwriteSementara
                                                ? "Ditimpa saat pengajuan permanen disetujui"
                                                : "Tidak ditimpa, shift sementara tetap berlaku sesuai tanggal"
                                        }}
                                    </div>
                                </div> -->

                                <div
                                    class="detail-shift-card mb-3"
                                    v-for="(detail, idx) in detailShiftRows"
                                    :key="idx"
                                    :class="{
                                        'border-success': detail.Flag_Approval === 'Y',
                                        'border-danger': detail.Flag_Approval === 'T',
                                        'border-warning': detail.Flag_Approval !== 'Y' && detail.Flag_Approval !== 'T',
                                    }"
                                >
                                    <!-- Date Header with Approval Status -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="detail-shift-date fw-bold">
                                                {{
                                                    isDetailPermanentShift
                                                        ? `${formatDate(detail.Tanggal_Shift)} s.d setelahnya`
                                                        : formatDate(detail.Tanggal_Shift)
                                                }}
                                            </span>
                                            <span
                                                class="badge text-uppercase"
                                                :class="{
                                                    'bg-info': detail.Jenis_Pengajuan === 'HARI_H',
                                                    'bg-success': detail.Jenis_Pengajuan === 'FUTURE',
                                                    'bg-warning text-dark': detail.Jenis_Pengajuan === 'BACKDATE',
                                                }"
                                                style="font-size: 0.55rem; border-radius: 10px"
                                            >
                                                {{ detail.Jenis_Pengajuan }}
                                            </span>
                                        </div>
                                        <span class="stitch-badge" :class="getStatusClass(detail.Flag_Approval)">
                                            {{ getStatusLabel(detail.Flag_Approval) }}
                                        </span>
                                    </div>

                                    <!-- Shift Change Info -->
                                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                        <!-- Previous Shift -->
                                        <div class="d-flex flex-column align-items-start">
                                            <small class="text-muted" style="font-size: 0.7rem">Shift Sebelumnya</small>
                                            <span class="text-muted small tengahkan gap-1">
                                                <i class="bi bi-arrow-left-circle"></i>
                                                {{ detail.Nama_Shift_Sebelumnya || '-' }}
                                            </span>
                                        </div>

                                        <i class="bi bi-arrow-right text-primary mx-2"></i>

                                        <!-- New Shift -->
                                        <div class="d-flex flex-column align-items-start">
                                            <small class="text-muted" style="font-size: 0.7rem">Shift Baru</small>
                                            <span class="text-dark fw-semibold small tengahkan gap-1">
                                                <i class="bi bi-arrow-right-circle text-success"></i>
                                                {{ detail.Nama_Shift || detail.Nama_Shift_Baru || '-' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Time Info -->
                                    <div
                                        style="font-size: 0.7rem"
                                        class="d-flex align-items-center gap-3 text-muted small d-flex justify-content-start align-items-center"
                                    >
                                        <span class="tengahkan gap-1">
                                            <i class="bi bi-info-circle"></i>

                                            {{
                                                detailOverwriteSementara
                                                    ? 'Overwrite shift sementara'
                                                    : 'Tidak overwrite shift sementara'
                                            }}
                                        </span>
                                        <span
                                            v-if="detail.Flag_Applied === 'Y'"
                                            class="badge bg-success-subtle text-success-emphasis d-flex justify-content-center align-items-center"
                                        >
                                            <i class="bi bi-check-circle tengahkan"></i>
                                            Diterapkan
                                        </span>
                                        <span
                                            v-else-if="detail.Flag_Approval === 'T'"
                                            class="badge bg-danger-subtle text-danger-emphasis d-flex justify-content-center align-items-center"
                                        >
                                            <i class="bi bi-x-circle tengahkan"></i>
                                            Tidak Diterapkan
                                        </span>
                                        <span
                                            v-else
                                            class="badge bg-secondary-subtle text-secondary-emphasis d-flex justify-content-center align-items-center"
                                        >
                                            <i class="bi bi-hourglass-split tengahkan"></i>
                                            Menunggu
                                        </span>
                                    </div>

                                    <!-- Rejection Note -->
                                    <div
                                        v-if="detail.Note_Tolak"
                                        class="mt-2 p-2 bg-danger-subtle rounded text-danger-emphasis small"
                                    >
                                        <i class="bi bi-info-circle"></i>
                                        {{ detail.Note_Tolak }}
                                    </div>

                                    <!-- Alasan per Tanggal -->
                                    <div v-if="detail.Alasan" class="mt-2 pt-2 border-t border-gray-200">
                                        <div class="text-muted small mb-1" style="font-size: 0.7rem">
                                            <i class="bi bi-chat-left-text"></i>
                                            {{ detail.Alasan }}
                                        </div>
                                        <!-- <div
                                            class="detail-info-value detail-alasan"
                                            style="max-width: 100%"
                                        >
                                            {{ detail.Alasan }}
                                        </div> -->
                                    </div>
                                </div>

                                <div v-if="detailShiftRows.length === 0" class="detail-approval-empty">
                                    <i class="bi bi-info-circle text-muted"></i>
                                    <span>Detail tanggal pengajuan tidak ditemukan</span>
                                </div>

                                <div class="detail-info-row" v-if="detailData.header.Alasan">
                                    <div class="detail-info-label">Alasan</div>
                                    <div class="detail-info-value detail-alasan">
                                        {{ detailData.header.Alasan }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <!-- <div
                    class="d-flex justify-content-end align-items-center w-full gap-2"
                > -->
                <button class="stitch-btn secondary flex-grow-1 flex-md-grow-0" @click="showDetailModal = false">
                    Tutup
                </button>
                <button
                    v-if="
                        detailData &&
                        detailData.header &&
                        getStatusClass(detailData.header.Flag_Selesai) === 'pending' &&
                        !hasAnyApprovalAction(detailData)
                    "
                    @click="cancelRequest(detailData.header.No_Transaksi)"
                    class="stitch-btn danger flex-grow-1 flex-md-grow-0"
                >
                    Batalkan Pengajuan
                </button>
                <!-- <div v-else> -->

                <!-- </div> -->
                <!-- Spacer -->

                <!-- </div> -->
            </template>
        </Modal>

        <!-- Cancel Confirmation Modal -->
        <Modal :show="showCancelModal" @close="showCancelModal = false" title="Konfirmasi Pembatalan" max-width="md">
            <template #body>
                <div class="p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="bi bi-exclamation-triangle text-3xl text-danger tengahkan"></i>
                    </div>
                    <h3 class="text-lg font-bold text-dark mb-2">Batalkan Pengajuan?</h3>
                    <p class="text-muted text-sm mb-6">
                        Apakah Anda yakin ingin membatalkan pengajuan ini? Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div class="flex gap-3 justify-center">
                        <button @click="showCancelModal = false" class="stitch-btn secondary" :disabled="cancelling">
                            Tidak, Kembali
                        </button>
                        <button @click="confirmCancel" class="stitch-btn danger" :disabled="cancelling">
                            <span v-if="cancelling">
                                <i class="bi bi-arrow-repeat animate-spin tengahkan"></i>
                                Memproses...
                            </span>
                            <span v-else> Ya, Batalkan </span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Hide Default Footer -->
            <template #footer>
                <span></span>
            </template>
        </Modal>

        <!-- Toast Notification (Removed, using Element Plus) -->
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import Badge from '../../../components/ui/badge.vue';
import quickStat from '@/components/legacy/quickStat.vue';
import JejakJadwalHariDrawer from '@/Pages/ESS/Attendance/Shift/Schedule/Components/JejakJadwalHariDrawer.vue';
import { HcisIcon } from '@/components/ui';
import axios from 'axios';
import { ElDatePicker, ElSelect, ElOption, ElMessage, ElMessageBox, ElNotification } from 'element-plus';
import 'element-plus/es/components/date-picker/style/css';
import 'element-plus/es/components/select/style/css';
import 'element-plus/es/components/message/style/css';
import { sw } from 'element-plus/es/locale/index.mjs';

export default {
    components: {
        HeaderKpi,
        ApprovalTabs,
        quickStat,
        JejakJadwalHariDrawer,
        HcisIcon,
        Modal,
        ModernFilter,
        Badge,
        ElDatePicker,
        ElSelect,
        ElOption,
        ElNotification,
    },
    props: {
        weekSchedule: {
            type: Array,
            default: () => [],
        },
        availableShifts: {
            type: Array,
            default: () => [],
        },
        karyawan: {
            type: Object,
            default: () => ({}),
        },
        approverFlows: {
            type: Array,
            default: () => [],
        },
        weekStart: String,
        weekEnd: String,
        config: {
            type: Object,
            default: () => ({
                backdate_max_days: 0,
                future_max_days: 30,
                backdate_needs_approval: true,
                same_day_needs_approval: true,
                future_needs_approval: true,

                tukarhari_backdate_max_days: 30,
                tukarhari_future_max_days: 30,
                tukarhari_backdate_needs_approval: true,
                tukarhari_same_day_needs_approval: true,
                tukarhari_future_needs_approval: true,
            }),
        },
        approver: {
            type: Object,
            default: null,
        },
        serverDate: {
            type: String,
            default: null,
        },
        serverTime: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            jejakHari: { buka: false, tanggal: '' },
            loading: false,
            submitting: false,
            activeTab: 'kalender',
            selectedDate: this.weekStart,
            weekScheduleData: this.weekSchedule,

            // History
            historyData: [],
            historyStats: {
                total: 0,
                pending: 0,
                approved: 0,
            },
            loadingStats: false,
            historyPagination: {},
            historyFilter: {
                search: '',
                status: '',
                transaction_type: '',
                date_range: [],
            },

            historyFilterConfig: {
                searchable: true,
                searchPlaceholder: 'Cari No Transaksi...',
                searchCol: 4,
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                mobileTrigger: true,
                filters: [
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        placeholder: 'Semua Status',
                        options: [
                            // { label: "Pending", value: "pending" },
                            { label: 'Sudah Direview', value: 'Y' },
                            { label: 'Belum Direview', value: 'null' },
                        ],
                        col: 3,
                    },
                    {
                        key: 'transaction_type',
                        label: 'Tipe Transaksi',
                        type: 'select',
                        placeholder: 'Semua Tipe',
                        options: [
                            { label: 'Permanen', value: 'PERMANEN' },
                            { label: 'Sementara', value: 'SEMENTARA' },
                            { label: 'Tukar Hari', value: 'TUKAR_HARI' },
                        ],
                        col: 3,
                    },
                    {
                        key: 'date_range',
                        label: 'Tanggal Shift',
                        type: 'daterange',
                        dateType: 'daterange',
                        valueFormat: 'YYYY-MM-DD',
                        placeholder: 'Pilih rentang tanggal',
                        col: 6,
                    },
                ],
            },
            // Removed raw config here as it is now computed

            // Wizard - Apply All
            masterShiftOptions: [],
            masterLoading: false,

            // Quick Modal
            shiftOptions: [], // New data property for modal options
            lockedDates: [], // Stores pending request dates
            offDatesRange: [], // Stores OFF dates within allowed range
            offDatesRangeKey: null,
            offDatesLoading: false,
            showQuickModal: false,
            quickModalPermanentConflictCount: 0,
            quickModalPermanentConflictLoading: false,
            quickModalData: {
                date: null,
                currentShift: null,
                newShiftId: null,
                makePermanent: false,
                overwriteSementara: false,
                alasan: '',
                is_off_day: false,
                onlySwapMode: false,
                pendingNoTransaksi: null,
            },

            // Wizard
            showWizard: false,
            wizardCurrentStep: 0,
            wizardPermanentConflictCount: 0,
            wizardPermanentConflictLoading: false,
            wizardOverwriteSementara: false,
            wizardSteps: ['Pilih Tipe', 'Pilih Tanggal', 'Assign Shift', 'Review'],
            wizardData: {
                targetTable: 'SEMENTARA',
                dates: [],
                alasan: '',
            },
            wizardTempDate: null,
            applyAllShiftId: null,

            // Toast
            // Toast removed

            // Tabs config
            tabs: [
                {
                    id: 'kalender',
                    label: 'Kalender',
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

            statusOptions: [
                { value: 'pending', label: 'Pending' },
                { value: 'approved', label: 'Disetujui' },
                { value: 'rejected', label: 'Ditolak' },
            ],

            // Detail Modal
            showDetailModal: false,
            detailData: null,
            loadingDetail: false,
            loadingHistory: false,

            // Cancel Modal
            showCancelModal: false,
            cancelTargetId: null,
            cancelling: false,
            loadingState: {
                shiftForDay: false,
                swapDay: false,
                quickModal: false,
                swapDayShift: false,
            },
            showSwapDayModal: false,
            showSwapConfirmModal: false,
            swapDates: [],
            selectedSwapDate: null,
            availableSwapDateShifts: [],
            swapSelectedShiftId: null,
            swapReason: '',
            swapSubmitting: false,
        };
    },

    computed: {
        hasApprover() {
            // return !!(
            //     this.approverInfo &&
            //     (this.approverInfo.nama ||
            //         this.approverInfo.Nama ||
            //         this.approverInfo.Kode_Karyawan)
            // );
            return this.approverFlows.length > 0;
        },
        shortedWizardDates() {
            return [...this.wizardData.dates].sort((a, b) => new Date(a.tanggal) - new Date(b.tanggal));
        },
        monthYearDisplay() {
            if (!this.selectedDate) return '';
            // Use weekSchedule data if available to be more precise about the visible week,
            // but selectedDate (week start) is usually sufficient.
            // If week crosses months, default to the month of the start of the week.
            const date = new Date(this.selectedDate);
            return date.toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            });
        },
        weekNumber() {
            if (!this.selectedDate) return '';
            const date = new Date(this.selectedDate);
            // ISO Week number calculation
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
        },
        filterConfig() {
            return {
                searchable: false, // History filter doesn't need text search usually, or maybe we add it?
                showResultsCount: true,
                showActiveFilters: true,
                showActions: false, // No export for now in history tab implementation
                showReset: true,
                showClearAll: true,
                mobileTrigger: true,
                filters: [
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        placeholder: 'Semua Status',
                        col: 12,
                        options: this.statusOptions,
                    },
                ],
            };
        },
        dayNight() {
            // const hour = new Date().getHours();
            const hour = this.serverTime ? new Date(this.serverTime).getHours() : new Date().getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            return new Date(this.serverDate).toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        minDate() {
            // const d = new Date();
            const d = this.serverDate ? new Date(this.serverDate) : new Date();
            d.setDate(d.getDate() - this.config.backdate_max_days);
            return d.toISOString().split('T')[0];
        },
        maxDate() {
            // const d = new Date();
            const d = this.serverDate ? new Date(this.serverDate) : new Date();
            d.setDate(d.getDate() + this.config.future_max_days);
            return d.toISOString().split('T')[0];
        },
        canProceedWizard() {
            if (this.wizardCurrentStep === 0) {
                return this.wizardData.targetTable;
            }
            if (this.wizardCurrentStep === 1) {
                return this.wizardData.dates.length > 0;
            }

            return true;
        },
        needsApprovalDates() {
            return this.wizardData.dates.filter((d) => {
                const jenisPengajuan = this.getJenisPengajuan(d.tanggal);
                return this.checkNeedsApproval(jenisPengajuan);
            });
        },
        directApplyDates() {
            return this.wizardData.dates.filter((d) => {
                const jenisPengajuan = this.getJenisPengajuan(d.tanggal);
                return !this.checkNeedsApproval(jenisPengajuan);
            });
        },
        quickStatField() {
            return [
                {
                    title: 'Total Pengajuan',
                    value: this.historyStats.total || 0,
                    unit: '',
                    icon: 'bi bi-calendar-check',
                    bgIcon: 'bg-primary',
                    color: 'blue',
                    sentiment: 'primary',
                    size: 'col-4',
                },
                {
                    title: 'Menunggu Review',
                    value: this.historyStats.pending || 0,
                    unit: '',
                    icon: 'bi bi-clock-history',
                    bgIcon: 'bg-warning',
                    color: 'orange',
                    sentiment: 'warning',
                    size: 'col-4',
                },
                {
                    title: 'Sudah Direview',
                    value: this.historyStats.approved || 0,
                    unit: '',
                    icon: 'bi bi-check-circle',
                    bgIcon: 'bg-success',
                    color: 'green',
                    sentiment: 'success',
                    size: 'col-4',
                },
            ];
        },
        // Computed property for Element Plus DatePicker
        selectedDates: {
            get() {
                if (this.wizardData.targetTable === 'SEMENTARA') {
                    // Multiple dates mode - return array of date strings
                    return this.wizardData.dates.map((d) => d.tanggal);
                } else {
                    // Single date mode - return single date string or null
                    return this.wizardData.dates.length > 0 ? this.wizardData.dates[0].tanggal : null;
                }
            },
            set(val) {
                if (this.wizardData.targetTable === 'SEMENTARA') {
                    // Multiple dates mode
                    if (Array.isArray(val)) {
                        this.wizardData.dates = val.map((date) => ({
                            tanggal: date,
                            id_shift: null,
                            alasan: '',
                        }));
                    } else {
                        this.wizardData.dates = [];
                    }
                } else {
                    // Single date mode
                    if (val) {
                        this.wizardData.dates = [{ tanggal: val, id_shift: null, alasan: '' }];
                    } else {
                        this.wizardData.dates = [];
                    }
                }
            },
        },
        swapMonthLabel() {
            const baseDate =
                this.selectedSwapDate || this.getSwapDateValue(this.swapDates[0]) || this.quickModalData.date;
            if (!baseDate) return '';
            const date = new Date(baseDate);
            return date.toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            });
        },
        selectedSwapDay() {
            if (!this.selectedSwapDate) return null;
            return this.swapDates.find((item) => this.getSwapDateValue(item) === this.selectedSwapDate);
        },
        swapAvailableShifts() {
            if (Array.isArray(this.availableSwapDateShifts)) {
                return this.availableSwapDateShifts;
            }
            return this.availableSwapDateShifts?.shifts || [];
        },
        canSubmitSwap() {
            if (!this.selectedSwapDate) return false;
            if (!this.loadingState.swapDayShift && this.swapAvailableShifts.length === 0) {
                return false;
            }
            if (this.swapAvailableShifts.length > 0 && !this.swapSelectedShiftId) {
                return false;
            }
            if (!this.swapReason || !this.swapReason.trim()) return false;
            return true;
        },
        detailShiftRows() {
            if (!this.detailData || this.detailData.transaction_type !== 'SHIFT') {
                return [];
            }

            if (Array.isArray(this.detailData.details) && this.detailData.details.length > 0) {
                return this.detailData.details;
            }

            const members = Array.isArray(this.detailData.members) ? this.detailData.members : [];

            return members.flatMap((member) => {
                const dates = Array.isArray(member?.dates) ? member.dates : [];
                return dates.map((date) => ({
                    ...date,
                    Target_Table: date.Target_Table || member.Target_Table || null,
                    Nama_Shift: date.Nama_Shift || date.Nama_Shift_Baru || '-',
                    Nama_Shift_Sebelumnya: date.Nama_Shift_Sebelumnya || date.Nama_Shift_Lama || '-',
                    Jam_Masuk: date.Jam_Masuk || null,
                    Jam_Keluar: date.Jam_Keluar || null,
                }));
            });
        },
        isDetailPermanentShift() {
            const targetTable =
                this.detailData?.header?.Target_Table ||
                this.detailData?.members?.[0]?.Target_Table ||
                this.detailShiftRows?.[0]?.Target_Table ||
                null;
            return (targetTable || '').toUpperCase() === 'PERMANEN';
        },
        detailPermanentStartDate() {
            if (!this.isDetailPermanentShift || this.detailShiftRows.length < 1) {
                return null;
            }

            const sorted = [...this.detailShiftRows].sort(
                (a, b) => new Date(a?.Tanggal_Shift || 0) - new Date(b?.Tanggal_Shift || 0),
            );

            return sorted[0]?.Tanggal_Shift || null;
        },
        detailOverwriteSementara() {
            const flag = this.detailData?.header?.Flag_Overwrite_Sementara;
            return flag === 'Y';
        },
    },
    watch: {
        'quickModalData.makePermanent'(val) {
            if (!val) {
                this.quickModalPermanentConflictCount = 0;
                this.quickModalData.overwriteSementara = false;
                return;
            }
            if (this.quickModalData.date) {
                this.fetchPermanentConflictCount(this.quickModalData.date);
            }
        },
        'quickModalData.date'(val) {
            if (this.quickModalData.makePermanent && val) {
                this.fetchPermanentConflictCount(val);
            }
        },
        'wizardData.targetTable'(val) {
            if (val === 'PERMANEN') {
                this.updateWizardPermanentConflict();
            } else {
                this.wizardPermanentConflictCount = 0;
            }
        },
        'wizardData.dates': {
            handler() {
                if (this.wizardData.targetTable === 'PERMANEN') {
                    this.updateWizardPermanentConflict();
                }
            },
            deep: true,
        },
    },
    mounted() {
        // Initialize weekScheduleData from prop
        this.weekScheduleData = this.weekSchedule;
        this.fetchStats();
        this.fetchLockedDates();
        this.fetchOffDatesRange();
    },
    methods: {
        truncateText(text, maxLength = 20) {
            if (!text) return '';
            return text.length > maxLength ? text.substring(0, maxLength - 3) + '...' : text;
        },
        handleDayoffToggle(event) {
            this.quickModalData.is_off_day = event.target.checked;
            this.quickModalData.newShiftId = null; // Reset shift selection when toggling day off
        },
        getDateNumber(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.getDate();
        },
        getSwapDateValue(item) {
            if (!item) return null;
            const raw =
                item.tanggal ||
                item.date ||
                item.tgl ||
                item.Tanggal ||
                item.Tanggal_Shift ||
                item.tanggal_shift ||
                null;
            if (!raw) return null;
            return raw.split('T')[0];
        },
        getSwapDateDayLabel(item) {
            const dateStr = this.getSwapDateValue(item);
            if (!dateStr) return '-';
            return this.getDayName(dateStr).substring(0, 3);
        },
        getSwapDateNumber(item) {
            const dateStr = this.getSwapDateValue(item);
            return this.getDateNumber(dateStr);
        },
        getSwapDateMonthYearLabel(item) {
            const dateStr = this.getSwapDateValue(item);
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', {
                month: 'short',
                year: 'numeric',
            });
        },
        getSwapStatusMeta(item) {
            const statusRaw = item.status_hari;
            const Keterangan = item.keterangan?.toUpperCase() || '';
            const locked = item.isActive == false;
            return {
                label: statusRaw || 'WORK',
                className: locked ? 'closed' : 'open',
                keterangan: Keterangan,
            };
        },
        getKeteranganSwapDay(item) {
            const statusMeta = this.getSwapStatusMeta(item);
            return statusMeta.keterangan || 'Disabled';
        },
        isSwapDateDisabled(item) {
            const statusMeta = this.getSwapStatusMeta(item);
            if (statusMeta.className === 'full') return true;
            if (statusMeta.className === 'closed') return true;
            if (item.isActive == false) return true;
            return item?.disabled === true || item?.is_available === false;
        },
        getSwapDateClass(item) {
            const isSelected = this.getSwapDateValue(item) === this.selectedSwapDate;
            const statusMeta = this.getSwapStatusMeta(item);
            return {
                selected: isSelected,
                disabled: this.isSwapDateDisabled(item),
                mine: statusMeta.className === 'mine',
            };
        },
        async selectSwapDate(item, isOffDay) {
            const dateStr = this.getSwapDateValue(item);
            if (isOffDay) {
                this.selectedSwapDate = dateStr;
                this.swapSelectedShiftId = null;
                return;
            }
            try {
                this.loadingState.swapDayShift = true;
                if (!dateStr || this.isSwapDateDisabled(item)) return;
                this.selectedSwapDate = dateStr;
                this.swapSelectedShiftId = null;

                const shiftResult = await this.getShiftForDay(dateStr, false, false);
                this.availableSwapDateShifts = shiftResult?.shifts || [];
            } catch (error) {
                console.error('Error selecting swap date:', error);
                this.showToastMessage('error', 'Gagal memuat shift untuk tanggal yang dipilih.');
            } finally {
                this.loadingState.swapDayShift = false;
            }
        },
        getSwapShiftKey(shift) {
            return shift?.id || shift?.id_shift || shift?.kode || shift?.name;
        },
        getSwapShiftValue(shift) {
            return shift?.id || shift?.id_shift || shift?.kode || null;
        },
        getSwapShiftName(shift) {
            return shift?.nama || shift?.name || shift?.nama_shift || 'Shift';
        },
        getSwapShiftTime(shift) {
            const start = shift?.jam_masuk || shift?.start || '--:--';
            const end = shift?.jam_keluar || shift?.end || '--:--';
            return `${start} - ${end}`;
        },
        isShiftCompatible(shift) {
            return Boolean(shift?.compatible || shift?.is_compatible || shift?.compatible_role);
        },
        submitSwapDayRequest() {
            if (!this.selectedSwapDate) {
                this.showToastMessage('error', 'Pilih tanggal target tukar hari.');
                return;
            }

            if (this.swapAvailableShifts.length > 0 && !this.swapSelectedShiftId) {
                this.showToastMessage('error', 'Pilih shift tujuan untuk tanggal target.');
                return;
            }
            if (!this.loadingState.swapDayShift && this.swapAvailableShifts.length === 0) {
                this.showToastMessage(
                    'warning',
                    'Tidak ada shift tersedia untuk skenario tukar hari ini sesuai rule shifting. Pilih tanggal target lain atau hubungi admin HR/Shift Rule.',
                );
                return;
            }

            if (!this.swapReason || !this.swapReason.trim()) {
                this.showToastMessage('error', 'Alasan tukar hari wajib diisi.');
                return;
            }

            this.showSwapConfirmModal = true;
            this.showSwapDayModal = false;
        },
        cancelSwapConfirm() {
            this.showSwapConfirmModal = false;
            this.showSwapDayModal = true;
        },
        async confirmSwapDayRequest() {
            if (this.swapSubmitting) return;

            if (!this.selectedSwapDate) {
                this.showToastMessage('error', 'Pilih tanggal target tukar hari.');
                this.showSwapConfirmModal = false;
                this.showSwapDayModal = true;
                return;
            }

            if (this.swapAvailableShifts.length > 0 && !this.swapSelectedShiftId) {
                this.showToastMessage('error', 'Pilih shift tujuan untuk tanggal target.');
                this.showSwapConfirmModal = false;
                this.showSwapDayModal = true;
                return;
            }
            if (!this.loadingState.swapDayShift && this.swapAvailableShifts.length === 0) {
                this.showToastMessage(
                    'warning',
                    'Tidak ada shift tersedia untuk skenario tukar hari ini sesuai rule shifting. Pilih tanggal target lain atau hubungi admin HR/Shift Rule.',
                );
                this.showSwapConfirmModal = false;
                this.showSwapDayModal = true;
                return;
            }

            if (!this.swapReason || !this.swapReason.trim()) {
                this.showToastMessage('error', 'Alasan tukar hari wajib diisi.');
                this.showSwapConfirmModal = false;
                this.showSwapDayModal = true;
                return;
            }

            this.swapSubmitting = true;
            try {
                const payload = {
                    date: this.quickModalData.date,
                    swap_date: this.selectedSwapDate,
                    make_dayoff: this.quickModalData.makeDayoff,
                    shift_id: this.swapSelectedShiftId,
                    alasan: this.swapReason.trim(),
                };
                const response = await axios.post('/self-shift/submit-swap-hari', payload);
                if (response.data?.success) {
                    this.showToastMessage(
                        'success',
                        response.data.message || 'Permintaan tukar hari berhasil dikirim.',
                    );
                    this.showSwapConfirmModal = false;
                    this.closeSwapDayModal();
                    this.showQuickModal = false;
                    this.fetchStats();
                    this.fetchWeekSchedule();
                    this.fetchLockedDates();
                } else {
                    this.showToastMessage('error', response.data?.message || 'Gagal memproses tukar hari.');
                    this.showSwapConfirmModal = false;
                    this.showSwapDayModal = true;
                }
            } catch (error) {
                const message = error?.response?.data?.message || 'Gagal memproses tukar hari.';
                this.showToastMessage('error', message);
                this.showSwapConfirmModal = false;
                this.showSwapDayModal = true;
            } finally {
                this.swapSubmitting = false;
            }
        },
        getSwapWorkingDate() {
            return this.quickModalData.makeDayoff ? this.quickModalData.date : this.selectedSwapDate;
        },
        getSwapOffDate() {
            return this.quickModalData.makeDayoff ? this.selectedSwapDate : this.quickModalData.date;
        },
        getSelectedSwapShift() {
            return this.swapAvailableShifts.find((shift) => this.getSwapShiftValue(shift) === this.swapSelectedShiftId);
        },
        getSelectedSwapShiftLabel() {
            const shift = this.getSelectedSwapShift();
            if (!shift) return '-';
            return `${this.getSwapShiftName(shift)} (${this.getSwapShiftTime(shift)})`;
        },
        openSwapDayModal(isDayOff, tanggal) {
            this.showSwapDayModal = true;
            this.showQuickModal = false;
            this.selectedSwapDate = null;
            this.swapSelectedShiftId = null;
            this.swapReason = '';
            this.availableSwapDateShifts = [];
            this.getHariSwap(isDayOff, tanggal);

            if (isDayOff) {
                this.loadingState.swapDayShift = true;
                const dateStr = tanggal;
                this.getShiftForDay(dateStr, false, false)
                    .then((shiftResult) => {
                        this.availableSwapDateShifts = shiftResult?.shifts || [];
                        console.log(
                            'Available shifts for swap day (day off):',
                            this.availableSwapDateShifts,
                            this.swapAvailableShifts,
                        );
                    })
                    .catch((error) => {
                        console.error('Error loading shifts for swap day:', error);
                        this.showToastMessage('error', 'Gagal memuat shift untuk tanggal yang dipilih.');
                        this.availableSwapDateShifts = [];
                    })
                    .finally(() => {
                        this.loadingState.swapDayShift = false;
                    });
            }
        },
        closeSwapDayModal() {
            this.showSwapDayModal = false;
            this.showSwapConfirmModal = false;
            this.showQuickModal = true;
            this.swapDates = [];
            this.selectedSwapDate = null;
            this.swapSelectedShiftId = null;
            this.swapReason = '';
        },
        async getHariSwap(isDayOff, tanggal) {
            try {
                this.loadingState.swapDay = true;
                const response = await axios.get('/self-shift/get-hari-swap', {
                    params: { isDayOff: isDayOff ?? false, tanggal: tanggal },
                });
                const originDate = tanggal ? String(tanggal).split('T')[0] : null;
                const dates = Array.isArray(response?.data?.result?.dates) ? response.data.result.dates : [];

                this.swapDates = originDate
                    ? dates.filter((item) => this.getSwapDateValue(item) !== originDate)
                    : dates;
                console.log('Fetched swap dates:', this.swapDates);
            } catch (error) {
                console.error('Failed to get hari swap', error);
                this.swapDates = [];
            } finally {
                this.loadingState.swapDay = false;
            }
        },
        isDateOutOfRange(dateStr) {
            if (!dateStr) return true;

            const dateToCheck = new Date(dateStr);
            // const today = new Date();
            const today = this.serverDate ? new Date(this.serverDate) : new Date();
            today.setHours(0, 0, 0, 0);
            dateToCheck.setHours(0, 0, 0, 0);

            const minDate = new Date(today);
            minDate.setDate(minDate.getDate() - (this.config?.backdate_max_days || 0));

            const maxDate = new Date(today);
            maxDate.setDate(maxDate.getDate() + (this.config?.future_max_days || 30));

            return dateToCheck < minDate || dateToCheck > maxDate;
        },
        isTukarHariDateOutOfRange(dateStr) {
            if (!dateStr) return true;

            const dateToCheck = new Date(dateStr);
            const today = this.serverDate ? new Date(this.serverDate) : new Date();
            today.setHours(0, 0, 0, 0);
            dateToCheck.setHours(0, 0, 0, 0);

            const minDate = new Date(today);
            minDate.setDate(minDate.getDate() - (this.config?.tukarhari_backdate_max_days || 0));

            const maxDate = new Date(today);
            maxDate.setDate(maxDate.getDate() + (this.config?.tukarhari_future_max_days || 30));

            return dateToCheck < minDate || dateToCheck > maxDate;
        },
        getDateFromYmdLocal(dateStr) {
            if (!dateStr) return null;
            const parts = String(dateStr).split('-').map(Number);
            if (parts.length !== 3 || parts.some((v) => Number.isNaN(v))) {
                return null;
            }
            const [year, month, day] = parts;
            return new Date(year, month - 1, day);
        },
        getLocalDateKey(date) {
            if (!(date instanceof Date)) return null;
            return date.toLocaleDateString('en-CA');
        },
        getOffDatesRangeParams() {
            const baseDate = this.serverDate ? this.getDateFromYmdLocal(this.serverDate) : new Date();
            if (!baseDate) return null;
            baseDate.setHours(0, 0, 0, 0);

            const minDate = new Date(baseDate);
            minDate.setDate(minDate.getDate() - (this.config?.backdate_max_days || 0));

            const maxDate = new Date(baseDate);
            maxDate.setDate(maxDate.getDate() + (this.config?.future_max_days || 30));

            return {
                start_date: this.getLocalDateKey(minDate),
                end_date: this.getLocalDateKey(maxDate),
            };
        },
        async fetchOffDatesRange() {
            const params = this.getOffDatesRangeParams();
            if (!params?.start_date || !params?.end_date) return;

            const rangeKey = `${params.start_date}|${params.end_date}`;
            if (this.offDatesRangeKey === rangeKey || this.offDatesLoading) {
                return;
            }

            this.offDatesLoading = true;
            try {
                const response = await axios.get('/self-shift/off-days', {
                    params,
                });
                if (response.data?.success) {
                    this.offDatesRange = response.data.data?.dates || [];
                    this.offDatesRangeKey = rangeKey;
                }
            } catch (error) {
                console.error('Failed to load OFF dates range', error);
            } finally {
                this.offDatesLoading = false;
            }
        },
        canEditDate(dateStr) {
            // Button edit bisa muncul jika tanggal dalam range shift ATAU tukar hari
            return !this.isDateOutOfRange(dateStr) || !this.isTukarHariDateOutOfRange(dateStr);
        },
        isRejectedOrCancelledDay(day) {
            if (!day || typeof day !== 'object') return false;

            const approvalFlag = day.Flag_Approval ?? day.flag_approval ?? day.tukar_hari?.flag_approval ?? null;
            const selesaiFlag = day.Flag_Selesai ?? day.flag_selesai ?? null;
            const pendingNoTransaksi = String(day.pending_no_transaksi || '').toUpperCase();
            const statusTexts = [day.status, day.request_status, day.transaction_status, day.status_hari]
                .filter(Boolean)
                .map((s) => String(s).toUpperCase());

            const hasCancelKeyword =
                pendingNoTransaksi.includes('BATAL') ||
                statusTexts.some((s) => s.includes('BATAL') || s.includes('CANCEL'));

            return approvalFlag === 'T' || selesaiFlag === 'T' || hasCancelKeyword;
        },
        isCalendarLocked(day) {
            return Boolean(day?.is_locked) && !this.isRejectedOrCancelledDay(day);
        },
        async fetchLockedDates() {
            try {
                const response = await axios.get('/self-shift/locked-dates');
                if (response.data.success) {
                    this.lockedDates = response.data.data;
                }
            } catch (error) {
                console.error('Failed to load locked dates', error);
            }
        },
        async fetchPermanentConflictCount(dateStr) {
            if (!dateStr) return;
            this.quickModalPermanentConflictLoading = true;
            try {
                const response = await axios.get('/self-shift/permanent-conflicts', {
                    params: { date: dateStr },
                });
                if (response.data?.success) {
                    this.quickModalPermanentConflictCount = response.data.data?.count || 0;
                }
            } catch (error) {
                console.error('Failed to load permanent conflict count', error);
            } finally {
                this.quickModalPermanentConflictLoading = false;
            }
        },
        async updateWizardPermanentConflict() {
            const dateStr = this.wizardData.dates?.[0]?.tanggal || null;
            if (!dateStr) {
                this.wizardPermanentConflictCount = 0;
                return;
            }
            this.wizardPermanentConflictLoading = true;
            try {
                const response = await axios.get('/self-shift/permanent-conflicts', {
                    params: { date: dateStr },
                });
                if (response.data?.success) {
                    this.wizardPermanentConflictCount = response.data.data?.count || 0;
                }
            } catch (error) {
                console.error('Failed to load wizard permanent conflicts', error);
            } finally {
                this.wizardPermanentConflictLoading = false;
            }
        },
        goToToday() {
            this.selectedDate = new Date(this.serverDate).toISOString().split('T')[0];
            this.fetchWeekSchedule();
        },
        handleHistoryFilterUpdate(newFilters) {
            this.historyFilter = { ...newFilters };
            this.fetchHistory();
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
            if (tabId === 'riwayat') {
                this.fetchHistory();
            }
        },
        prevWeek() {
            const d = new Date(this.selectedDate);
            d.setDate(d.getDate() - 7);
            this.selectedDate = d.toISOString().split('T')[0];
            this.fetchWeekSchedule();
        },
        nextWeek() {
            const d = new Date(this.selectedDate);
            d.setDate(d.getDate() + 7);
            this.selectedDate = d.toISOString().split('T')[0];
            this.fetchWeekSchedule();
        },
        onDateChange() {
            this.fetchWeekSchedule();
        },
        async fetchWeekSchedule() {
            this.loading = true;
            try {
                const response = await axios.get('/self-shift/schedule', {
                    params: { week_start: this.selectedDate },
                });
                this.weekScheduleData = response.data.data.schedule;
            } catch (error) {
                this.showToastMessage('error', 'Gagal memuat jadwal');
            } finally {
                this.loading = false;
            }
        },
        async fetchStats() {
            this.loadingStats = true;
            try {
                const response = await axios.get('/self-shift/stats');
                if (response.data?.success) {
                    this.historyStats = response.data.result?.stats || {
                        total: 0,
                        pending: 0,
                        approved: 0,
                    };
                }
            } catch (error) {
                console.error('Failed to load self-shift stats', error);
            } finally {
                this.loadingStats = false;
            }
        },
        async fetchHistory(url = null) {
            this.loadingHistory = true;
            try {
                this.historyData = [];
                this.historyPagination = {};
                const [startDate, endDate] = Array.isArray(this.historyFilter?.date_range)
                    ? this.historyFilter.date_range
                    : [null, null];
                const { date_range, ...otherFilters } = this.historyFilter;
                const response = await axios.get(url || '/self-shift/history', {
                    params: {
                        ...otherFilters,
                        start_date: startDate || '',
                        end_date: endDate || '',
                    },
                });
                // console.log(response.data.result);
                if (response.status == 200) {
                    this.historyData = response.data.result.items;
                    this.historyPagination = response.data.pagination || {};
                } else {
                    this.historyData = [];
                    this.historyPagination = {};
                }
            } catch (error) {
                this.showToastMessage('error', 'Gagal memuat riwayat');
            } finally {
                this.loadingHistory = false;
            }
        },
        changeHistoryPage(page) {
            const last = this.historyPagination.total_pages || this.historyPagination.last_page || 1;
            if (page < 1 || page > last) return;
            this.fetchHistory(page);
        },

        isBackdate(dateStr) {
            if (!dateStr) return false;
            // const today = new Date();
            const today = this.serverDate ? new Date(this.serverDate) : new Date();
            today.setHours(0, 0, 0, 0);
            const date = new Date(dateStr);
            return date < today;
        },
        isSameDay(dateStr) {
            if (!dateStr) return false;
            // const today = new Date();
            const today = this.serverDate ? new Date(this.serverDate) : new Date();
            today.setHours(0, 0, 0, 0);
            const date = new Date(dateStr);
            date.setHours(0, 0, 0, 0);
            return date.getTime() === today.getTime();
        },
        getJenisPengajuan(dateStr) {
            // Match backend ShiftRequestService::determineJenisPengajuan
            if (this.isBackdate(dateStr)) {
                return 'BACKDATE';
            } else if (this.isSameDay(dateStr)) {
                return 'HARI_H';
            } else {
                return 'FUTURE';
            }
        },
        checkNeedsApproval(jenisPengajuan) {
            // Match backend ShiftRequestService::checkNeedsApproval
            // Normalize to uppercase and replace hyphens with underscores
            const normalized = String(jenisPengajuan).toUpperCase().replace(/-/g, '_');
            // console.log(
            //     "checkNeedsApproval:",
            //     jenisPengajuan,
            //     "-> normalized:",
            //     normalized,
            //     "config:",
            //     this.config,
            // );
            let result;
            switch (normalized) {
                case 'BACKDATE':
                    result = this.config?.backdate_needs_approval ?? true;
                    break;
                case 'HARI_H':
                    result = this.config?.same_day_needs_approval ?? false;
                    break;
                case 'FUTURE':
                    result = this.config?.future_needs_approval ?? false;
                    break;
                default:
                    result = true;
            }
            // console.log("Result:", result);
            return result;
        },
        // Element Plus DatePicker methods
        disabledDate(time) {
            const date = new Date(time);

            // 1. Check if date is in lockedDates (YYYY-MM-DD comparison)
            const dateStr = date.toLocaleDateString('en-CA');
            if (this.lockedDates.includes(dateStr)) {
                return true;
            }

            // 2. Disable dates that are OFF in the current schedule
            const scheduleItem = this.weekScheduleData.find((d) => d.date === dateStr);
            if (scheduleItem?.status_hari?.startsWith('OFF')) {
                return true;
            }

            // 3. Disable dates that are OFF in the allowed range cache
            if (this.offDatesRange.includes(dateStr)) {
                return true;
            }

            // const today = new Date();
            const today = this.serverDate ? new Date(this.serverDate) : new Date();
            today.setHours(0, 0, 0, 0);

            // Calculate min and max dates based on config
            const minDate = new Date(today);
            minDate.setDate(minDate.getDate() - (this.config?.backdate_max_days || 0));

            const maxDate = new Date(today);
            maxDate.setDate(maxDate.getDate() + (this.config?.future_max_days || 30));

            return date < minDate || date > maxDate;
        },
        onDatePickerChange(val) {
            // This is called when the date picker value changes
            // The selectedDates computed setter handles the sync
            // console.log("Date picker changed:", val);
        },
        clearAllDates() {
            this.wizardData.dates = [];
        },
        getShiftLabel(shift) {
            if (!shift) return '-';
            const nama = shift.nama || 'Unknown Shift';
            const jamMasuk = shift.jam_masuk || '--:--';
            const jamKeluar = shift.jam_keluar || '--:--';
            // longshift handling
            const longshiftInfo = shift.flag_longshift
                ? ` | Longshift : ${parseInt(shift.durasi_longshift ?? 0)} Jam`
                : '';
            return shift.display ?? `${nama} (${jamMasuk} - ${jamKeluar})${longshiftInfo}`;
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },
        formatDateShort(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
            });
        },
        // getJenisPengajuan(dateStr) {
        //     if (!dateStr) return "FUTURE";
        //     const today = new Date();
        //     today.setHours(0, 0, 0, 0);
        //     const date = new Date(dateStr);
        //     date.setHours(0, 0, 0, 0);

        //     if (date < today) return "BACKDATE";
        //     if (date.getTime() === today.getTime()) return "HARI_H";
        //     return "FUTURE";
        // },
        getJenisPengajuanLabel(dateStr) {
            if (!dateStr) return '-';
            const type = this.getJenisPengajuan(dateStr);
            return {
                backdate: '🔴 Backdate',
                'hari-h': '🟡 Hari H',
                future: '🟢 Future',
            }[type];
        },
        /**
         * Status di riwayat & detail. Pengajuan yang dibatalkan karyawan sendiri tak
         * pernah tampil di riwayat, jadi header Status 'Y' di sini selalu berarti
         * dibatalkan admin.
         */
        statusRiwayat(item) {
            if (item?.Status === 'Y') return { kelas: 'rejected', label: 'Dibatalkan Admin' };
            if (item?.Flag_Selesai === 'Y') return { kelas: 'approved', label: 'Sudah Direview' };
            return { kelas: 'pending', label: 'Menunggu Review' };
        },
        getStatusClass(flag) {
            if (flag === 'Y') return 'approved';
            if (flag === 'T') return 'rejected';
            return 'pending';
        },
        getStatusLabel(flag) {
            if (flag === 'Y') return 'Disetujui';
            if (flag === 'T') return 'Ditolak';
            return 'Pending';
        },
        getShiftName(shiftId) {
            const shift = this.availableShifts.find((s) => s.id === shiftId);
            return shift?.nama || '-';
        },
        getInitial(name) {
            if (!name) return '?';
            return name.charAt(0).toUpperCase();
        },
        formatDateTime(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return (
                date.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                }) +
                ', ' +
                date.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                })
            );
        },
        getCurrentApprover(approvalSteps) {
            if (!approvalSteps || approvalSteps.length === 0) return '-';
            const pending = approvalSteps.find((s) => s.Flag_Approval === null);
            if (pending) return pending.Nama || pending.Nama_Karyawan || 'Menunggu';
            // All approved/rejected - return last approver
            const last = approvalSteps[approvalSteps.length - 1];
            return last?.Nama || last?.Nama_Karyawan || '-';
        },
        hasRejectedBeforeStep(approvalSteps, idx) {
            if (!Array.isArray(approvalSteps) || idx <= 0) return false;
            return approvalSteps.slice(0, idx).some((step) => step?.Flag_Approval === 'T');
        },
        getApprovalStepState(approvalSteps, idx, step) {
            if (step?.Flag_Approval === 'Y') return 'approved';
            if (step?.Flag_Approval === 'T') return 'rejected';
            if (this.hasRejectedBeforeStep(approvalSteps, idx)) {
                return 'skipped';
            }
            return 'pending';
        },
        getDayName(dateStr) {
            if (!dateStr) return 'hari ini';
            const date = new Date(dateStr);
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            return days[date.getDay()];
        },
        getShiftColorClass(shiftName) {
            const lower = (shiftName || '').toLowerCase();
            if (lower.includes('pagi') || lower.includes('morning')) return 'bg-success';
            if (lower.includes('siang') || lower.includes('afternoon')) return 'bg-warning';
            if (lower.includes('malam') || lower.includes('night')) return 'bg-primary';
            return 'bg-secondary';
        },
        getShiftIndicatorClass(shiftName) {
            const lower = (shiftName || '').toLowerCase();
            if (lower.includes('pagi') || lower.includes('morning')) return 'green';
            if (lower.includes('siang') || lower.includes('afternoon')) return 'yellow';
            if (lower.includes('malam') || lower.includes('night')) return 'blue';
            return 'purple';
        },
        // Quick Modal
        // openQuickModal(day) {
        //     // console.log(day);
        //     if (!day.date) {
        //         this.showToastMessage("error", "Tanggal tidak valid");
        //         return;
        //     }

        //     // Reset options and fetch new ones
        //     this.shiftOptions = [];
        //     this.getShiftForDay(day.date); // Default: updateGlobal = true

        //     this.quickModalData = {
        //         date: day.date,
        //         currentShift: day.shift,
        //         newShiftId: null,
        //         makePermanent: false,
        //         alasan: "",
        //     };
        //     this.showQuickModal = true;
        // },
        async openQuickModal(day) {
            // console.log(day);
            try {
                if (!day.date) {
                    this.showToastMessage('error', 'Tanggal tidak valid');
                    return;
                }

                // Check if date is out of range (both shift AND tukar hari)
                if (!this.canEditDate(day.date)) {
                    // Tak bisa diubah lagi, tapi riwayat jadwalnya tetap bisa dilihat.
                    this.bukaJejakHari(day.date);
                    this.showToastMessage(
                        'info',
                        `Tanggal ${this.formatDate(day.date)} sudah di luar batas pengajuan (mundur ${this.config.backdate_max_days} hari, maju ${this.config.future_max_days} hari). Berikut riwayat jadwalnya.`,
                    );
                    return;
                }
                this.loadingState.quickModal = true;
                // Reset options and fetch new ones
                this.shiftOptions = [];
                this.quickModalData = {
                    date: day.date,
                    currentShift: day.shift,
                    newShiftId: null,
                    makePermanent: false,
                    overwriteSementara: false,
                    alasan: '',
                    makeDayoff: false,
                    pendingNoTransaksi: day.pending_no_transaksi || null,
                    onlySwapMode: day.is_day_off && !(day.tukar_hari && day.tukar_hari.type === 'off'),
                    hasTukarHari: day.tukar_hari && day.tukar_hari.type === 'working', // Check if this is a working day from tukar hari
                };
                this.quickModalPermanentConflictCount = 0;
                this.showQuickModal = true;
                await this.getShiftForDay(day.date); // Default: updateGlobal = true
                console.log('Opening quick modal for date:', this.quickModalData);
            } catch (error) {
                console.error('Error opening quick modal:', error);
                this.showToastMessage('error', 'Terjadi kesalahan saat membuka modal');
            } finally {
                this.loadingState.quickModal = false;
            }
        },

        async getShiftForDay(date, all = false, updateGlobal = true) {
            try {
                if (updateGlobal) this.loadingState.shiftForDay = true;

                const response = await axios.get(`/self-shift/shift-for-day`, {
                    params: { date, all },
                });

                let result = response.data.result || response.data || {};
                let shifts = [];
                let currentShift = null;
                let is_off_day = false;

                // Handle both new format {shifts, current_shift} and potential old array format
                if (Array.isArray(result)) {
                    shifts = result;
                } else if (result.shifts) {
                    shifts = result.shifts;
                    currentShift = result.current_shift;
                    is_off_day = result.is_off_day || false;
                }

                if (updateGlobal) {
                    this.shiftOptions = shifts;
                    this.quickModalData.makeDayoff = result.is_off_day || false;

                    if (!all && !shifts.length && date) {
                        this.showToastMessage(
                            'warning',
                            `Tidak ada shift tersedia untuk ${this.formatDate(date)} sesuai rule shifting.`,
                        );
                    }
                }

                return {
                    shifts,
                    current_shift: currentShift,
                    makeDayoff: result.is_off_day || false,
                };
            } catch (error) {
                console.error(error);
                this.showToastMessage('error', 'Gagal mengambil data shift');
                return { shifts: [], current_shift: null };
            } finally {
                if (updateGlobal) this.loadingState.shiftForDay = false;
            }
        },

        async loadWizardShift(dateItem) {
            // If already loaded, skip? User requested "loading animasi dlu karena request dulu", so maybe force reload or check if empty
            // Let's check if empty to avoid spamming if user clicks repeatedly, but allow refetch if needed.
            // For now, fetch if empty.
            if (dateItem.shiftOptions && dateItem.shiftOptions.length > 0) return;

            dateItem.loading = true;
            try {
                const result = await this.getShiftForDay(dateItem.tanggal, false, false);
                dateItem.shiftOptions = result.shifts;
                dateItem.id_shift_sebelumnya = result.current_shift;
            } catch (error) {
                console.error('Failed to load wizard shift', error);
            } finally {
                dateItem.loading = false;
            }
        },

        async loadMasterShifts() {
            if (this.masterShiftOptions.length > 0) return;

            this.masterLoading = true;
            try {
                // Pass a dummy date or null, server should handle 'all=true' regardless of date
                // Current controller validate date required, so pass today's date just in case
                // const today = new Date().toISOString().split("T")[0];
                const today = this.serverDate ? this.serverDate : new Date().toISOString().split('T')[0];
                const result = await this.getShiftForDay(today, true, false);
                this.masterShiftOptions = result.shifts;
            } catch (error) {
                console.error('Failed to load master shifts', error);
            } finally {
                this.masterLoading = false;
            }
        },

        async submitQuickModal() {
            if (this.quickModalData.onlySwapMode) {
                this.showToastMessage('warning', 'Untuk hari libur, gunakan tombol Tukar Hari.');
                return;
            }

            if (!this.quickModalData?.date) {
                this.showToastMessage('error', 'Tanggal pengajuan belum dipilih.');
                return;
            }

            if (this.isDateOutOfRange(this.quickModalData.date)) {
                this.showToastMessage('error', 'Tanggal berada di luar jangkauan pengajuan shift.');
                return;
            }

            if (!this.shiftOptions.length) {
                this.showToastMessage(
                    'warning',
                    'Tidak ada pilihan shift untuk tanggal ini sesuai rule. Pilih tanggal lain atau hubungi admin HR/Shift Rule.',
                );
                return;
            }

            if (!this.quickModalData?.newShiftId) {
                this.showToastMessage('error', 'Pilih shift tujuan terlebih dahulu.');
                return;
            }

            if (!this.quickModalData?.alasan || !this.quickModalData.alasan.trim()) {
                this.showToastMessage('error', 'Alasan pengajuan wajib diisi.');
                return;
            }

            this.submitting = true;
            try {
                if (
                    this.quickModalData.makePermanent &&
                    this.quickModalData.overwriteSementara &&
                    this.quickModalPermanentConflictCount > 0
                ) {
                    const confirmed = await this.confirmPermanentOverride(
                        `Ada ${this.quickModalPermanentConflictCount} shift sementara aktif yang akan dinonaktifkan. Lanjutkan?`,
                    );
                    if (!confirmed) {
                        this.submitting = false;
                        return;
                    }
                }
                const payload = {
                    target_table: this.quickModalData.makePermanent ? 'PERMANEN' : 'SEMENTARA',
                    alasan: this.quickModalData.alasan.trim(),
                    overwrite_sementara: this.quickModalData.makePermanent
                        ? this.quickModalData.overwriteSementara
                        : false,
                    dates: [
                        {
                            tanggal: this.quickModalData.date,
                            id_shift: this.quickModalData.newShiftId,
                            id_shift_sebelumnya: this.quickModalData.currentShift?.id || null,
                        },
                    ],
                };

                const response = await axios.post('/self-shift/submit', payload);

                if (response.data.success) {
                    this.showToastMessage('success', response.data.message);
                    this.showQuickModal = false;
                    this.fetchWeekSchedule();
                }
            } catch (error) {
                this.showToastMessage('error', error.response?.data?.message || 'Gagal memproses');
            } finally {
                this.submitting = false;
            }
        },
        // Wizard
        wizardNextStep() {
            if (this.wizardCurrentStep === 1) {
                if (this.wizardData.targetTable === 'PERMANEN') {
                    // For permanent, only one date, add it automatically
                    if (this.wizardTempDate && this.wizardData.dates.length === 0) {
                        this.wizardData.dates.push({
                            tanggal: this.wizardTempDate,
                            id_shift: null,
                            id_shift_sebelumnya: null,
                            alasan: '',

                            shiftOptions: [],
                            loading: false,
                        });
                    }
                }

                // Ensure all dates have correct properties for Step 2
                // This prevents "cannot read property of undefined" when rendering the dropdowns
                this.wizardData.dates.forEach((d) => {
                    if (!d.shiftOptions) d.shiftOptions = [];
                    if (d.loading === undefined) d.loading = false;
                    if (d.alasan === undefined) d.alasan = '';
                });
            }

            if (this.wizardCurrentStep === 2) {
                const hasShift = this.wizardData.dates.every((d) => d.id_shift);
                // console.log("Validation Step 2 (hasAllShifts):", hasShift);
                if (!hasShift) {
                    this.showToastMessage('error', 'Harap pilih shift untuk setiap tanggal');
                    return; // Stop execution, do not proceed
                }
                // If valid, proceed to increment step
            }

            this.wizardCurrentStep++;
        },
        async addWizardDate() {
            if (this.wizardTempDate) {
                // Check if date is pending/locked
                // We need to check against weekSchedule if available, or make a request?
                // For now, let's rely on server validation on submit or user visual cue if we can fetch it.
                // Or better: Fetch lock status for this specific date?
                // Let's optimize: Check local weekScheduleData if date is within range

                const scheduleItem = this.weekScheduleData.find((d) => d.date === this.wizardTempDate);
                if (scheduleItem && scheduleItem.is_locked) {
                    this.showToastMessage(
                        'error',
                        scheduleItem.pending_no_transaksi
                            ? `Tanggal tersebut sedang dalam proses pengajuan. No. Transaksi: ${scheduleItem.pending_no_transaksi}`
                            : 'Tanggal tersebut sedang dalam proses pengajuan.',
                    );
                    return;
                }

                const exists = this.wizardData.dates.find((d) => d.tanggal === this.wizardTempDate);

                if (!exists) {
                    this.wizardData.dates.push({
                        tanggal: this.wizardTempDate,
                        id_shift: null,
                        id_shift_sebelumnya: null,
                        alasan: '',
                        shiftOptions: [],
                        loading: false,
                    });
                }
                this.wizardTempDate = null;
            }
        },
        removeWizardDate(idx) {
            this.wizardData.dates.splice(idx, 1);
        },
        async applyToAll() {
            if (!this.applyAllShiftId) return;

            this.submitting = true; // Show processing state

            try {
                let appliedCount = 0;
                let skippedCount = 0;
                const skippedDates = [];

                // console.log(
                //     "Starting Apply All for Shift ID:",
                //     this.applyAllShiftId,
                // );

                await Promise.all(
                    this.wizardData.dates.map(async (d) => {
                        try {
                            d.loading = true;
                            // Always fetch fresh options to ensure accuracy. Do not rely on cached data.
                            // Pass specific parameters to ensure we get day-specific shifts, NOT all shifts.
                            const result = await this.getShiftForDay(d.tanggal, false, false);
                            d.shiftOptions = result.shifts;
                            if (result.current_shift) {
                                d.id_shift_sebelumnya = result.current_shift;
                            }

                            const freshOptions = result.shifts; // Maintain variable name for checking validity downstream

                            // Debugging: Log options for Saturday/Special days if needed
                            // console.log(`Options for ${d.tanggal}:`, freshOptions.map(s => s.nama));

                            // Check validity
                            const isValid = freshOptions && freshOptions.some((s) => s.id === this.applyAllShiftId);

                            if (isValid) {
                                d.id_shift = this.applyAllShiftId;
                                appliedCount++;
                            } else {
                                console.warn(`Shift ${this.applyAllShiftId} invalid for date ${d.tanggal}`);
                                skippedCount++;
                                skippedDates.push(d.tanggal);
                            }
                        } catch (e) {
                            console.error(`Error loading shift for ${d.tanggal}`, e);
                        } finally {
                            d.loading = false;
                        }
                    }),
                );

                // console.log(
                //     `Apply All Result: Applied ${appliedCount}, Skipped ${skippedCount}`,
                // );

                if (skippedCount > 0) {
                    this.showToastMessage(
                        'warning',
                        `Shift diterapkan pada ${appliedCount} tanggal. ${skippedCount} tanggal dilewati karena shift tidak tersedia. ${this.formatSkippedDatePreview(skippedDates)}`,
                    );
                } else {
                    this.showToastMessage('success', 'Shift diterapkan ke semua tanggal.');
                }
            } catch (error) {
                console.error('Apply all system error', error);
                this.showToastMessage('error', 'Terjadi kesalahan saat menerapkan shift');
            } finally {
                this.submitting = false;
            }
        },
        async submitWizard() {
            if (!this.wizardData?.targetTable) {
                this.showToastMessage('error', 'Pilih tipe perubahan shift terlebih dahulu.');
                return;
            }

            if (!this.wizardData?.dates || this.wizardData.dates.length === 0) {
                this.showToastMessage('error', 'Pilih minimal satu tanggal pengajuan.');
                return;
            }

            const missingShift = this.wizardData.dates.some((d) => !d.id_shift);
            if (missingShift) {
                this.showToastMessage('error', 'Masih ada tanggal yang belum dipilih shift tujuan.');
                return;
            }

            if (!this.wizardData?.alasan || !this.wizardData.alasan.trim()) {
                this.showToastMessage('error', 'Alasan pengajuan wajib diisi.');
                return;
            }

            this.submitting = true;
            try {
                if (
                    this.wizardData.targetTable === 'PERMANEN' &&
                    this.wizardOverwriteSementara &&
                    this.wizardPermanentConflictCount > 0
                ) {
                    const confirmed = await this.confirmPermanentOverride(
                        `Ada ${this.wizardPermanentConflictCount} shift sementara aktif yang akan dinonaktifkan. Lanjutkan?`,
                    );
                    if (!confirmed) {
                        this.submitting = false;
                        return;
                    }
                }
                const payload = {
                    target_table: this.wizardData.targetTable,
                    alasan: this.wizardData.alasan.trim(),
                    overwrite_sementara:
                        this.wizardData.targetTable === 'PERMANEN' ? this.wizardOverwriteSementara : false,
                    dates: this.wizardData.dates,
                };

                const response = await axios.post('/self-shift/submit', payload);

                if (response.data.success) {
                    this.showToastMessage('success', response.data.message);
                    this.showWizard = false;
                    this.activeTab = 'riwayat';
                    this.resetWizard();
                    this.fetchStats();
                    this.fetchHistory();
                    this.fetchWeekSchedule();
                }
            } catch (error) {
                this.showToastMessage('error', error.response?.data?.message || 'Gagal memproses');
            } finally {
                this.submitting = false;
            }
        },
        resetWizard() {
            this.wizardCurrentStep = 0;
            this.wizardData = {
                targetTable: 'SEMENTARA',
                dates: [],
                alasan: '',
            };
            this.wizardTempDate = null;
            this.applyAllShiftId = null;
            this.wizardPermanentConflictCount = 0;
            this.wizardOverwriteSementara = false;
        },
        handleFilterUpdate(newFilters) {
            this.historyFilter = { ...newFilters };
            this.fetchHistory();
        },
        async viewDetail(noTransaksi) {
            this.showDetailModal = true;
            this.loadingDetail = true;
            this.detailData = null;

            try {
                const response = await axios.get(`/self-shift/detail/${noTransaksi}`);
                // console.log("Detail response:", response.data);
                if (response.data.success) {
                    this.detailData = response.data.data;
                }
            } catch (error) {
                console.error('Detail error:', error);
                this.showToastMessage('error', 'Gagal memuat detail transaksi');
                this.showDetailModal = false;
            } finally {
                this.loadingDetail = false;
            }
        },
        hasAnyApprovalAction(item) {
            /**
             * Check if the transaction has received ANY approval action
             * from approvers (approval or rejection).
             * Blocks cancel operations once ANY approver has touched the request.
             *
             * Works with:
             * - History list items: checks item.has_approval_action (backend flag)
             * - Detail modal data: checks item.approval_steps[].Flag_Approval
             * - Fallback: checks item.details[].Flag_Approval and Flag_Selesai
             */
            if (!item) return false;

            // Check has_approval_action flag from backend (highest priority - most reliable)
            if (item.has_approval_action === true) return true;

            // Check approval_steps (from detail modal response)
            if (item.approval_steps && Array.isArray(item.approval_steps)) {
                return item.approval_steps.some((step) => step.Flag_Approval !== null && step.Flag_Approval !== '');
            }

            // Check details array (from history list response)
            if (item.details && Array.isArray(item.details)) {
                return item.details.some((detail) => detail.Flag_Approval !== null && detail.Flag_Approval !== '');
            }

            // Fallback: Check header/wrapper Flag_Selesai as indirect indicator
            // If Flag_Selesai is 'Y' (approved) or 'T' (rejected), approval action occurred
            return item.Flag_Selesai === 'Y' || item.Flag_Selesai === 'T';
        },
        cancelRequest(noTransaksi) {
            this.cancelTargetId = noTransaksi;
            this.showCancelModal = true;
        },
        async confirmCancel() {
            if (!this.cancelTargetId) return;

            this.cancelling = true;
            try {
                const response = await axios.post(`/self-shift/cancel/${this.cancelTargetId}`);
                if (response.data.success) {
                    this.showToastMessage('success', 'Pengajuan berhasil dibatalkan');
                    this.fetchStats();
                    this.fetchHistory();
                    this.showCancelModal = false;
                    this.cancelTargetId = null;
                    // Close detail modal if open and matching
                    if (this.showDetailModal && this.detailData?.header?.No_Transaksi === this.cancelTargetId) {
                        this.showDetailModal = false;
                    }
                }
                this.fetchWeekSchedule(); // Refresh schedule to update locked/pending status
                this.showDetailModal = false; // Ensure detail modal is closed after cancellation
            } catch (error) {
                this.showToastMessage('error', error.response?.data?.message || 'Gagal membatalkan');
            } finally {
                this.cancelling = false;
            }
        },
        formatSkippedDatePreview(dates, limit = 3) {
            if (!Array.isArray(dates) || dates.length === 0) return '';
            const uniq = [...new Set(dates)];
            const preview = uniq
                .slice(0, limit)
                .map((d) => this.formatDateShort(d))
                .join(', ');
            const suffix = uniq.length > limit ? ` +${uniq.length - limit} tanggal lain` : '';
            return `Tanggal yang tidak tersedia: ${preview}${suffix}`;
        },
        bukaJejakHari(tanggal) {
            if (!tanggal) return;
            this.showQuickModal = false;
            this.jejakHari = { buka: true, tanggal: String(tanggal).slice(0, 10) };
        },
        showToastMessage(type, message) {
            ElMessage({
                message: message,
                type: type,
                duration: 3000,
                showClose: true,
            });
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
    },
};
</script>

<style scoped>
/* =========================================
   PREMIUM DESIGN SYSTEM 
   ========================================= */

/* Loading State */
.loading-state {
    background: var(--surface);
    /* border-radius: 16px; */
    padding: 3rem 2rem;
    text-align: center;
    /* box-shadow: var(--shadow);
    border: 1px solid var(--border); */
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

@media (max-width: 900px) {
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

/* Disabled state */
.toggle-switch input:disabled + .slider {
    background-color: #e5e7eb;
    cursor: not-allowed;
    opacity: 0.6;
}

.toggle-switch input:disabled:checked + .slider {
    background-color: #9ca3af;
}

.slider.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Compact Toggle Switch (sm) */
.toggle-switch-sm {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    flex-shrink: 0;
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
    background-color: var(--hcis-amber-500);
}
.toggle-switch-sm input:checked + .slider-sm::before {
    transform: translateX(16px);
}
.toggle-switch-sm input:focus + .slider-sm {
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3);
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
.text-info-emphasis {
    color: #055160 !important;
}
.text-danger-emphasis {
    color: #842029 !important;
}

.border-info {
    border-color: #9eeaf9 !important;
}
.border-danger {
    border-color: #ff0019 !important;
}

.bg-primary {
    background-color: var(--primary) !important;
}
.bg-primary-subtle {
    background-color: var(--hcis-indigo-100) !important;
    color: var(--primary-dark) !important;
}
.bg-info-subtle {
    background-color: #cff4fc !important;
}
.bg-danger-subtle {
    background-color: var(--hcis-legacy-bootstrap-danger) !important;
}
.bg-surface {
    background-color: var(--surface) !important;
}
.bg-light {
    background-color: var(--surface-soft) !important;
}
.bg-secondary-subtle {
    background-color: #e2e3e5 !important;
}

.border-secondary {
    border-color: #b6b7b9 !important;
}

.text-secondary-emphasis {
    color: #495057 !important;
}

.bg-secondary {
    background-color: var(--secondary) !important;
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
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
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

.stitch-badge.swap-approved {
    background: #dbeafe;
    color: #1e40af;
    font-weight: 600;
}

.stitch-badge.swap-pending {
    background: #fed7aa;
    color: #9a3412;
    font-weight: 600;
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

.detail-approval-indicator.skipped {
    background: #e5e7eb;
    color: #6b7280;
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
        padding: 0rem;
        margin-top: 1rem;
        max-height: none;
    }
    .detail-shift-date {
        justify-content: flex-start;
        font-size: 0.775rem;
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
    /* border-radius: 16px; */
    padding: 3rem 2rem;
    text-align: center;
    /* box-shadow: var(--shadow);
    border: 1px solid var(--border); */
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

/* =========================================
   SWAP DAY MODAL
   ========================================= */
.swap-modal-body {
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.swap-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.swap-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.swap-section-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--text-muted);
}

.swap-month-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
}

.swap-origin-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 14px;
    background: white;
    border: 1px solid var(--border);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
}

.swap-origin-date {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    background: var(--hcis-indigo-50);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    flex-shrink: 0;
}

.swap-origin-day {
    font-size: 0.6rem;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--hcis-blue-600);
    font-weight: 700;
}

.swap-origin-num {
    font-size: 1.35rem;
    font-weight: 800;
    color: #1e3a8a;
}

.swap-origin-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.swap-origin-date-text {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.swap-origin-role {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--dark);
}

.swap-origin-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.swap-origin-badge {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
}

.swap-origin-badge.off {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.swap-divider {
    display: flex;
    justify-content: center;
}

.swap-divider-icon {
    width: 38px;
    height: 38px;
    border-radius: 999px;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surface);
    color: var(--primary);
}

.swap-date-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(92px, 1fr));
    gap: 0.75rem;
    max-height: 20rem;
    overflow-y: auto;
    padding-top: 1rem;
}

.swap-date-item {
    border: 1px solid var(--border);
    background: white;
    border-radius: 12px;
    padding: 0.75rem 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.2s ease;
    position: relative;
}

.swap-date-item:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.12);
    transform: translateY(-1px);
}

.swap-date-item.selected {
    border-color: var(--primary);
    background: #eff6ff;
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);
}

.swap-date-item.mine {
    border-color: #93c5fd;
    background: #eff6ff;
}

.swap-date-item.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.swap-date-tooltip {
    position: absolute;
    top: 13px;
    left: 50%;
    transform: translate(-50%, -100%);
    background: #111827;
    color: #f9fafb;
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 0.2px;
    text-transform: uppercase;
    white-space: nowrap;
    box-shadow: 0 6px 12px rgba(17, 24, 39, 0.2);
    pointer-events: none;
    z-index: 2;
}

.swap-date-day {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--text-muted);
    font-weight: 700;
}

.swap-date-num {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--dark);
}

.swap-date-month-year {
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 0.2px;
    text-transform: uppercase;
    color: var(--text-muted);
}

.swap-date-status {
    font-size: 0.55rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-radius: 999px;
    padding: 0.15rem 0.45rem;
}

.swap-date-status.open {
    background: var(--hcis-green-100);
    color: #166534;
}

.swap-date-status.full {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

.swap-date-status.closed {
    background: #e5e7eb;
    color: #374151;
}

.swap-date-status.mine {
    background: #dbeafe;
    color: #1d4ed8;
}

.swap-date-loading,
.swap-date-empty,
.swap-shift-empty {
    border: 1px dashed var(--border);
    border-radius: 12px;
    padding: 0.75rem;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.swap-date-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.swap-date-loading .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.swap-shift-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 300px;
    overflow-y: auto;
}

.swap-shift-item {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: white;
    transition: all 0.2s ease;
}

.swap-shift-item input {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.swap-shift-item.selected {
    border-color: var(--primary);
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.12);
    background: var(--hcis-slate-50);
}

.swap-shift-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.swap-shift-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--dark);
    align-content: center;
    display: flex;
    gap: 0.5rem;
}

.swap-shift-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.swap-chip {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.swap-chip.success {
    background: var(--hcis-green-100);
    color: #166534;
}

.swap-reason-input {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.75rem;
    font-size: 0.85rem;
    width: 100%;
    resize: none;
    background: white;
}

.swap-reason-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.12);
}

.swap-confirm-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.swap-confirm-card {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    background: white;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.swap-confirm-title {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--text-muted);
}

.swap-confirm-date {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--dark);
}

.swap-confirm-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.65rem;
    color: var(--text-muted);
}

.swap-confirm-tag {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
}

.swap-confirm-tag.off {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.swap-confirm-tag.work {
    background: var(--hcis-green-100);
    color: #166534;
}

.swap-confirm-summary {
    margin-top: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.swap-confirm-row {
    display: grid;
    grid-template-columns: 140px minmax(0, 1fr);
    gap: 0.35rem 0.75rem;
    align-items: center;
}

.swap-confirm-row .swap-confirm-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 700;
    color: var(--text-muted);
}

.swap-confirm-row .swap-confirm-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--dark);
}

.swap-confirm-row .swap-confirm-meta {
    grid-column: 2 / -1;
    font-size: 0.75rem;
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .swap-confirm-grid {
        grid-template-columns: 1fr;
    }

    .swap-confirm-row {
        grid-template-columns: 1fr;
    }

    .swap-confirm-row .swap-confirm-meta {
        grid-column: auto;
    }
}

@media (max-width: 768px) {
    .swap-origin-card {
        flex-direction: row;
        align-items: center;
    }

    .swap-section-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
