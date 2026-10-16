<template>
    <Modal v-if="show" :show="show" title="Pengajuan Konversi Lembur" size="medium" @close="$emit('close')">
        <template #body>
            <!-- Dynamic Stepper -->
            <div class="stepper-wrap mb-4">
                <div
                    v-for="(step, idx) in steps"
                    :key="step.id"
                    class="step-item"
                    :class="{
                        active: currentStepIndex === idx,
                        done: currentStepIndex > idx,
                    }"
                >
                    <div class="step-indicator">
                        <i v-if="currentStepIndex > idx" class="bi bi-check-lg text-white"></i>
                        <span v-else>{{ idx + 1 }}</span>
                    </div>
                    <span class="step-label">{{ step.label }}</span>
                    <div v-if="idx < steps.length - 1" class="step-line"></div>
                </div>
            </div>

            <!-- ─── Step: Select Check-in/out (OFF day only) ─── -->
            <template v-if="currentStepId === 'select_checkinout'">
                <div class="step-header-card stitch-card mb-4 shadow-sm border rounded-4 bg-white">
                    <div class="step-header-icon bg-primary-subtle text-primary">
                        <i class="bi bi-fingerprint"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem">Pilih Check-in & Check-out</h6>
                        <small class="text-muted" style="font-size: 0.72rem">
                            Pilih waktu check-in dan check-out dari log absensi hari OFF ini.
                        </small>
                    </div>
                </div>

                <div v-if="logsLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.88rem">Memuat data log absensi...</p>
                </div>

                <div
                    v-else-if="rawLogs.length === 0"
                    class="empty-log-state py-5 border rounded-4 bg-light text-center"
                >
                    <i class="bi bi-clock-history text-muted mb-2" style="font-size: 2rem"></i>
                    <p class="mb-0 text-muted" style="font-size: 0.88rem">Tidak ada checktime pada tanggal ini.</p>
                </div>

                <div v-else class="log-picker-form">
                    <div class="row g-3">
                        <!-- Check In Options -->
                        <div class="col-12 col-md-6">
                            <label
                                class="form-label text-uppercase fw-bold text-muted fw-bold"
                                style="font-size: 0.68rem"
                            >
                                Check-in <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex flex-column gap-2">
                                <label
                                    v-for="item in uniqueRawLogs"
                                    :key="`in-${item.datetime}`"
                                    class="log-option-card transition-hover"
                                    :class="{
                                        selected: offSelectedCheckIn === item.datetime,
                                    }"
                                >
                                    <input
                                        type="radio"
                                        :value="item.datetime"
                                        v-model="offSelectedCheckIn"
                                        class="d-none"
                                    />
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-box-arrow-in-right text-primary"></i>
                                            <span class="fw-bold text-dark" style="font-size: 0.88rem">{{
                                                item.time
                                            }}</span>
                                        </div>
                                        <span
                                            v-if="item.type"
                                            class="badge rounded-pill bg-light text-secondary border"
                                            style="font-size: 0.65rem"
                                        >
                                            {{ item.type }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Check Out Options -->
                        <div class="col-12 col-md-6">
                            <label
                                class="form-label text-uppercase fw-bold text-muted fw-bold"
                                style="font-size: 0.68rem"
                            >
                                Check-out <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex flex-column gap-2">
                                <label
                                    v-for="item in checkoutCandidates"
                                    :key="`out-${item.datetime}`"
                                    class="log-option-card transition-hover"
                                    :class="{
                                        selected: offSelectedCheckOut === item.datetime,
                                    }"
                                >
                                    <input
                                        type="radio"
                                        :value="item.datetime"
                                        v-model="offSelectedCheckOut"
                                        class="d-none"
                                    />
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-box-arrow-right text-warning"></i>
                                            <span class="fw-bold text-dark" style="font-size: 0.88rem">{{
                                                item.time
                                            }}</span>
                                        </div>
                                        <span
                                            v-if="item.type"
                                            class="badge rounded-pill bg-light text-secondary border"
                                            style="font-size: 0.65rem"
                                        >
                                            {{ item.type }}
                                        </span>
                                    </div>
                                </label>
                                <div
                                    v-if="!offSelectedCheckIn"
                                    class="text-muted text-center p-3 border rounded-3 bg-light"
                                    style="font-size: 0.75rem"
                                >
                                    Pilih check-in terlebih dahulu
                                </div>
                                <div
                                    v-else-if="checkoutCandidates.length === 0"
                                    class="text-danger text-center p-3 border rounded-3 bg-danger-subtle border-danger-subtle"
                                    style="font-size: 0.75rem"
                                >
                                    Tidak ada log setelah waktu check-in.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div
                        v-if="offSelectedCheckIn && offSelectedCheckOut"
                        class="stitch-card mt-4 p-3 bg-primary-subtle border-primary-subtle rounded-3 shadow-sm d-flex justify-content-between align-items-center"
                    >
                        <div class="d-flex flex-column">
                            <span class="text-uppercase fw-bold text-primary" style="font-size: 0.68rem"
                                >Durasi Terhitung</span
                            >
                            <span class="fs-5 fw-bold text-primary-emphasis">{{ offDurationLabel }}</span>
                        </div>
                        <div>
                            <span
                                class="badge rounded-pill px-3 py-2 border shadow-sm"
                                :class="
                                    offIsEligible
                                        ? 'bg-success text-white border-success'
                                        : 'bg-danger text-white border-danger'
                                "
                                style="font-size: 0.75rem"
                            >
                                <i :class="offIsEligible ? 'bi bi-check-circle me-1' : 'bi bi-x-circle me-1'"></i>
                                {{
                                    offIsEligible
                                        ? `Eligible (>= ${minimumHours} jam)`
                                        : `Non Eligible (< ${minimumHours} jam)`
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── Step: Eligibility Check ─── -->
            <template v-if="currentStepId === 'eligibility'">
                <div class="step-header-card stitch-card mb-4 shadow-sm border rounded-4 bg-white">
                    <div class="step-header-icon bg-success-subtle text-success">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem">Cek Kelayakan Lembur</h6>
                        <small class="text-muted" style="font-size: 0.72rem">
                            {{
                                isOff
                                    ? 'Durasi dihitung dari check-in dan check-out yang Anda pilih.'
                                    : 'Durasi dihitung otomatis oleh sistem dari log absensi.'
                            }}
                        </small>
                    </div>
                </div>

                <div class="eligibility-card rounded-4 border bg-white shadow-sm p-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="info-summary-box h-100">
                                <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem">
                                    Tanggal Lembur
                                </div>
                                <div class="fw-bold text-dark" style="font-size: 0.88rem">
                                    {{ formatDate(form.tanggal_lembur) }}
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-summary-box h-100">
                                <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem">
                                    Status Hari
                                </div>
                                <div>
                                    <span
                                        class="badge rounded-pill border fw-semibold"
                                        :class="
                                            isOff
                                                ? 'bg-danger-subtle text-danger border-danger-subtle'
                                                : 'bg-primary-subtle text-primary border-primary-subtle'
                                        "
                                        style="font-size: 0.75rem"
                                    >
                                        {{ isOff ? 'OFF' : 'WORK' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-summary-box h-100">
                                <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem">
                                    Check In
                                </div>
                                <div class="fw-bold text-dark" style="font-size: 0.88rem">
                                    {{ formatTimeOnly(form.check_in) }}
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-summary-box h-100">
                                <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem">
                                    Check Out
                                </div>
                                <div class="fw-bold text-dark" style="font-size: 0.88rem">
                                    {{ formatTimeOnly(form.check_out) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="elig-result mt-4 pt-3 border-top text-center">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                            <i class="bi bi-clock-history text-primary fs-4"></i>
                            <span class="fs-4 fw-bolder text-dark">{{ eligibilityDurationLabel }}</span>
                        </div>
                        <span
                            class="badge rounded-pill border fw-semibold px-3 py-2 shadow-sm"
                            :class="
                                computedIsEligible
                                    ? 'bg-success text-white border-success'
                                    : 'bg-danger text-white border-danger'
                            "
                            style="font-size: 0.85rem"
                        >
                            <i :class="computedIsEligible ? 'bi bi-check-circle me-1' : 'bi bi-x-circle me-1'"></i>
                            {{ computedIsEligible ? 'Eligible untuk Konversi' : 'Tidak Eligible' }}
                        </span>
                        <div
                            v-if="!computedIsEligible"
                            class="text-danger mt-2"
                            style="font-size: 0.75rem; font-weight: 500"
                        >
                            Durasi lembur kurang dari {{ minimumHours }} jam. Tidak bisa melanjutkan.
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── Step: Pilih Metode ─── -->
            <template v-if="currentStepId === 'metode'">
                <div class="step-header-card stitch-card mb-4 shadow-sm border rounded-4 bg-white">
                    <div
                        class="step-header-icon bg-indigo-subtle text-indigo"
                        style="color: #4f46e5; background-color: #e0e7ff"
                    >
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem">Pilih Metode Konversi</h6>
                        <small class="text-muted" style="font-size: 0.72rem">
                            {{ isForcedToCuti ? forcedMessage : 'Pilih apakah ingin dikonversi menjadi hari libur atau uang.' }}
                        </small>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="info-summary-box h-100">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem">
                                Tanggal Lembur
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.88rem">
                                {{ formatDate(form.tanggal_lembur) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="info-summary-box h-100">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem">
                                Durasi Lembur
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.88rem">
                                {{ eligibilityDurationLabel }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-uppercase fw-bold text-muted fw-bold" style="font-size: 0.68rem">
                        Pilih Konversi <span class="text-danger">*</span>
                    </label>
                    <div v-if="isForcedToCuti && !conversionContextLoading" class="forced-cuti-note mb-3">
                        <div class="forced-cuti-note__icon">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <div class="forced-cuti-note__title">Pilihan otomatis disesuaikan</div>
                            <div class="forced-cuti-note__text">
                                {{ forcedMessage }}
                            </div>
                        </div>
                    </div>
                    <div v-if="conversionContextLoading" class="conversion-context-loader">
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                        <div>
                            <div class="conversion-context-loader__title">Memeriksa opsi konversi terbaru</div>
                            <div class="conversion-context-loader__text">
                                Sistem sedang mengecek status hutang cuti Anda agar pilihan konversi yang tampil tetap akurat.
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="metode-grid"
                        :class="{ 'single-option': allowedConversionTypeList.length === 1 }"
                    >
                        <label
                            v-if="canChooseDayOff"
                            class="metode-option transition-hover"
                            :class="{
                                selected: form.jenis_konversi === 'DAY_OFF',
                            }"
                        >
                            <input type="radio" value="DAY_OFF" v-model="form.jenis_konversi" class="d-none" />
                            <div class="metode-icon-wrap mb-2">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <span class="metode-label">Day Off</span>
                            <small>Ganti dengan hari libur</small>
                            <div class="selection-ring"></div>
                        </label>
                        <label
                            v-if="canChooseMaterial"
                            class="metode-option transition-hover"
                            :class="{
                                selected: form.jenis_konversi === 'MATERIAL',
                            }"
                        >
                            <input type="radio" value="MATERIAL" v-model="form.jenis_konversi" class="d-none" />
                            <div class="metode-icon-wrap mb-2">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <span class="metode-label">Uang / Material</span>
                            <small>Kompensasi berupa uang</small>
                            <div class="selection-ring"></div>
                        </label>
                        <label
                            v-if="canChooseToCuti"
                            class="metode-option transition-hover locked-option"
                            :class="{
                                selected: form.jenis_konversi === 'TO_CUTI',
                            }"
                        >
                            <input type="radio" value="TO_CUTI" v-model="form.jenis_konversi" class="d-none" />
                            <div class="metode-icon-wrap mb-2">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <span class="metode-label">Bayar Hutang Cuti</span>
                            <small>Otomatis mengurangi hutang cuti yang masih aktif</small>
                            <div class="selection-ring"></div>
                        </label>
                    </div>
                    <div
                        v-if="!conversionContextLoading && allowedConversionTypeList.length < 2"
                        class="text-muted mt-2"
                        style="font-size: 0.72rem"
                    >
                        {{
                            isForcedToCuti
                                ? `Saat ini hanya tersedia Bayar Hutang Cuti karena hutang cuti Anda masih ${leaveDebtDays} hari.`
                                : 'Tipe konversi disesuaikan dengan level Anda.'
                        }}
                    </div>
                </div>

                <div class="approval-preview-card mb-3">
                    <div class="approval-preview-head">
                        <h6 class="mb-0">Rute Approval</h6>
                        <!-- <small v-if="approvalCodeLabel" class="text-muted">
                            {{ approvalCodeLabel }}
                        </small> -->
                    </div>

                    <small v-if="approvalPreviewLoading" class="text-muted d-block mt-2"> Memuat approver... </small>

                    <small v-else-if="conversionContextLoading" class="text-muted d-block mt-2">
                        Menunggu hasil pengecekan metode konversi...
                    </small>

                    <small v-else-if="approvalPreviewError" class="text-danger d-block mt-2">
                        {{ approvalPreviewError }}
                    </small>

                    <div v-else-if="!conversionNeedsApproval" class="approval-direct-note mt-2">
                        Pengajuan tipe ini tidak membutuhkan approval dan akan langsung diterapkan.
                    </div>

                    <div v-else class="approval-preview-steps mt-2">
                        <div
                            v-for="approver in approverList"
                            :key="`${approver.order}-${approver.kode}`"
                            class="approval-preview-step"
                        >
                            <div class="approval-step-left">
                                <div class="approval-step-order">
                                    {{ approver.order }}
                                </div>
                                <div class="d-flex flex-column" style="min-width: 0">
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem">
                                        {{ approver.nama }}
                                    </div>
                                    <div class="text-muted text-truncate" style="font-size: 0.72rem">
                                        {{ approver.jabatan || '-' }}
                                    </div>
                                </div>
                            </div>
                            <span
                                class="badge rounded-pill bg-light text-secondary border px-2 py-1"
                                style="font-size: 0.65rem"
                            >
                                Step {{ approver.approval_order }}
                            </span>
                        </div>

                        <div v-if="!approverList.length" class="approval-empty-note">
                            Approver belum ditemukan untuk konfigurasi ini.
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── Step: Detail ─── -->
            <template v-if="currentStepId === 'detail'">
                <div class="step-header-card stitch-card mb-4 shadow-sm border rounded-4 bg-white">
                    <div class="step-header-icon bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem">Lengkapi Detail Pengajuan</h6>
                        <small class="text-muted" style="font-size: 0.72rem">
                            {{
                                form.jenis_konversi === 'DAY_OFF'
                                    ? 'Pilih tanggal day off, isi alasan dan lampiran.'
                                    : form.jenis_konversi === 'TO_CUTI'
                                      ? 'Isi alasan dan lampiran jika ada. Hutang cuti akan otomatis dikurangi setelah approval final.'
                                    : 'Isi alasan dan lampiran jika ada.'
                            }}
                        </small>
                    </div>
                </div>

                <div v-if="form.jenis_konversi === 'DAY_OFF'" class="mb-4">
                    <label class="form-label text-uppercase fw-bold text-muted fw-bold" style="font-size: 0.68rem">
                        Tanggal Target OFF <span class="text-danger">*</span>
                    </label>
                    <div class="offdate-picker-card bg-white border rounded-4 p-3 shadow-sm">
                        <div
                            class="offdate-header pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center"
                        >
                            <button
                                type="button"
                                class="stitch-action-btn"
                                style="width: 32px; height: 32px"
                                @click="prevWorkMonth"
                                :disabled="!canGoPrevWorkMonth"
                            >
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <div class="offdate-month-label fw-bold text-dark" style="font-size: 0.9rem">
                                {{ workMonthLabel }}
                            </div>
                            <button
                                type="button"
                                class="stitch-action-btn"
                                style="width: 32px; height: 32px"
                                @click="nextWorkMonth"
                                :disabled="!canGoNextWorkMonth"
                            >
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>

                        <div v-if="!offOptionsLoading && workDateItemsInMonth.length" class="swap-date-grid">
                            <button
                                v-for="item in workDateItemsInMonth"
                                :key="item.date"
                                type="button"
                                class="swap-date-item transition-hover"
                                :class="getWorkDateClasses(item)"
                                :disabled="isWorkDateDisabled(item)"
                                :title="getWorkDateTooltip(item)"
                                @click="selectWorkDate(item)"
                            >
                                <div v-if="isWorkDateDisabled(item)" class="swap-date-tooltip">
                                    {{ getWorkDateTooltip(item) }}
                                </div>
                                <span class="swap-date-day">{{ formatDayShort(item.date) }}</span>
                                <span class="swap-date-num">{{ formatDayNumber(item.date) }}</span>
                                <span class="swap-date-month-year">{{ formatMonthYearShort(item.date) }}</span>
                                <span
                                    class="badge rounded-pill mt-1"
                                    :class="
                                        isWorkDateDisabled(item)
                                            ? 'bg-danger-subtle text-danger border border-danger-subtle'
                                            : 'bg-success-subtle text-success-emphasis border border-success-subtle'
                                    "
                                    style="font-size: 0.55rem; padding: 0.3em 0.6em"
                                >
                                    {{ isWorkDateDisabled(item) ? item.primary_type_label || 'Dipakai' : 'WORK' }}
                                </span>
                            </button>
                        </div>

                        <div v-else-if="!offOptionsLoading" class="text-center p-4 border border-dashed rounded-3">
                            <i class="bi bi-calendar-x text-muted mb-2 fs-3"></i>
                            <p class="mb-0 text-muted" style="font-size: 0.8rem">
                                Tidak ada tanggal WORK yang tersedia pada bulan ini.
                            </p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted" style="font-size: 0.72rem"
                            >Tanggal yang dipakai transaksi lain akan otomatis dikunci.</small
                        >
                        <small v-if="offOptionsLoading" class="text-primary"
                            ><span class="spinner-border spinner-border-sm me-1"></span>Memuat opsi date...</small
                        >
                    </div>

                    <small v-if="offOptionsError" class="text-danger d-block mt-1">{{ offOptionsError }}</small>
                    <div v-if="form.errors.tanggal_off" class="text-danger small mt-1">
                        {{ form.errors.tanggal_off }}
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-uppercase fw-bold text-muted fw-bold" style="font-size: 0.68rem">
                        Alasan / Keterangan <span class="text-danger">*</span>
                    </label>
                    <div class="input-group-custom">
                        <textarea
                            v-model="form.alasan"
                            rows="3"
                            class="form-control focus-ring"
                            placeholder="Jelaskan alasan pengajuan konversi lembur..."
                            style="font-size: 0.88rem; padding: 0.85rem"
                        ></textarea>
                    </div>
                    <div v-if="form.errors.alasan" class="text-danger small mt-1">
                        {{ form.errors.alasan }}
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-uppercase fw-bold text-muted fw-bold" style="font-size: 0.68rem">
                        Upload Lampiran
                        <span class="text-muted" style="text-transform: none; font-weight: 500">(Optional)</span>
                    </label>
                    <div class="file-upload-wrapper position-relative">
                        <input
                            type="file"
                            class="form-control"
                            style="font-size: 0.88rem; padding: 0.6rem"
                            accept=".pdf,.png,.jpg,.jpeg"
                            @change="handleFileUpload"
                        />
                        <div class="mt-2 text-muted" style="font-size: 0.72rem">
                            <i class="bi bi-info-circle me-1"></i> Format diterima: PNG, JPG, PDF (Max 10MB)
                        </div>
                        <div v-if="form.errors.lampiran" class="text-danger small mt-1">
                            {{ form.errors.lampiran }}
                        </div>
                    </div>
                </div>

                <div v-if="submitError" class="alert alert-danger py-2 mb-0">
                    {{ submitError }}
                </div>
            </template>

            <!-- ─── Step: Review ─── -->
            <template v-if="currentStepId === 'review'">
                <div class="step-header-card stitch-card mb-4 shadow-sm border rounded-4 bg-white">
                    <div class="step-header-icon bg-success-subtle text-success">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem">Review Pengajuan</h6>
                        <small class="text-muted" style="font-size: 0.72rem">
                            Periksa kembali data pengajuan Anda sebelum di-submit.
                        </small>
                    </div>
                </div>

                <div class="stitch-card p-3 border rounded-4 bg-white shadow-sm">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Tanggal Lembur
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem">
                                {{ formatDate(form.tanggal_lembur) }}
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Status Hari
                            </div>
                            <div>
                                <span
                                    class="badge rounded-pill border fw-semibold"
                                    :class="
                                        isOff
                                            ? 'bg-danger-subtle text-danger border-danger-subtle'
                                            : 'bg-primary-subtle text-primary border-primary-subtle'
                                    "
                                    style="font-size: 0.7rem"
                                >
                                    {{ isOff ? 'OFF' : 'WORK' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Jenis Konversi
                            </div>
                            <div>
                                <span
                                    class="badge rounded-pill border fw-semibold"
                                    :class="
                                        form.jenis_konversi === 'MATERIAL'
                                            ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                            : form.jenis_konversi === 'TO_CUTI'
                                              ? 'bg-info-subtle text-info-emphasis border-info-subtle'
                                              : 'bg-success-subtle text-success-emphasis border-success-subtle'
                                    "
                                    style="font-size: 0.7rem"
                                >
                                    <i
                                        :class="
                                            form.jenis_konversi === 'MATERIAL'
                                                ? 'bi bi-wallet2 me-1'
                                                : form.jenis_konversi === 'TO_CUTI'
                                                  ? 'bi bi-journal-check me-1'
                                                  : 'bi bi-calendar-x me-1'
                                        "
                                    ></i>
                                    {{ getConversionTypeLabel(form.jenis_konversi) }}
                                </span>
                            </div>
                        </div>

                        <div class="col-6 col-md-4" v-if="form.jenis_konversi === 'DAY_OFF'">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Target OFF
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem">
                                {{ formatDate(form.tanggal_off) }}
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Check In
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem">
                                {{ formatTimeOnly(form.check_in) }}
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Check Out
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem">
                                {{ formatTimeOnly(form.check_out) }}
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Durasi Total
                            </div>
                            <div class="fw-bold text-primary" style="font-size: 0.95rem">
                                {{ eligibilityDurationLabel }}
                            </div>
                        </div>
                        <div class="col-6 col-md-4" v-if="form.jenis_konversi === 'DAY_OFF'">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Estimasi Hari Libur
                            </div>
                            <div class="fw-bold text-success" style="font-size: 0.95rem">
                                +{{ estimatedDayOff }} Hari
                            </div>
                        </div>
                        <div class="col-6 col-md-4" v-if="form.jenis_konversi === 'TO_CUTI'">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Pengurangan Hutang
                            </div>
                            <div class="fw-bold text-info" style="font-size: 0.95rem">
                                -{{ estimatedDayOff }} Hari
                            </div>
                        </div>

                        <div class="col-12 mt-2 pt-2 border-top">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Alasan Pengajuan
                            </div>
                            <div
                                class="text-dark bg-white p-2 border rounded-3 text-break"
                                style="font-size: 0.85rem; min-height: 48px"
                            >
                                {{ form.alasan || '-' }}
                            </div>
                        </div>

                        <div class="col-12" v-if="form.lampiran">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.65rem">
                                Lampiran
                            </div>
                            <div class="d-flex align-items-center gap-2 bg-white p-2 border rounded-3">
                                <i class="bi bi-file-earmark-text text-primary"></i>
                                <span class="text-dark fw-medium text-truncate" style="font-size: 0.85rem">{{
                                    form.lampiran.name
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="submitError" class="alert alert-danger py-2 mb-0 mt-3">
                    {{ submitError }}
                </div>
            </template>
        </template>

        <template #footer>
            <div class="d-flex justify-content-end gap-2 w-100">
                <button type="button" class="stitch-btn secondary rounded-3" @click="$emit('close')">Batal</button>

                <button
                    v-if="currentStepIndex > 0"
                    type="button"
                    class="stitch-btn secondary rounded-3"
                    @click="prevStep"
                    :disabled="submitting"
                >
                    <i class="bi bi-chevron-left"></i>
                    Kembali
                </button>

                <button
                    v-if="currentStepIndex < steps.length - 1"
                    type="button"
                    class="stitch-btn primary rounded-3"
                    @click="nextStep"
                    :disabled="!canProceedCurrentStep || submitting"
                >
                    Lanjut
                    <i class="bi bi-chevron-right"></i>
                </button>

                <button
                    v-if="currentStepId === 'review'"
                    type="button"
                    class="stitch-btn primary rounded-3 w-100 px-4"
                    @click="submit"
                    :disabled="submitting"
                >
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="bi bi-send me-1"></i>
                    <span class="submit-label-mobile">Ajukan</span>
                    <span class="submit-label-desktop">
                        {{
                            form.jenis_konversi === 'MATERIAL'
                                ? 'Ajukan Material / Uang'
                                : form.jenis_konversi === 'TO_CUTI'
                                  ? 'Ajukan Bayar Hutang Cuti'
                                  : 'Ajukan Hari Pengganti'
                        }}
                    </span>
                </button>
            </div>
        </template>
    </Modal>
</template>

<script>
import moment from 'moment';
import axios from 'axios';
import Modal from '@/components/legacy/modalV2-noir.vue';

export default {
    name: 'ApplicationFormModal',
    components: {
        Modal,
    },
    props: {
        show: Boolean,
        data: Object,
        isOff: { type: Boolean, default: false },
        allowedConversionTypes: {
            type: Array,
            default: () => [],
        },
        conversionContext: {
            type: Object,
            default: () => ({}),
        },
        conversionContextLoading: { type: Boolean, default: false },
        rawLogs: { type: Array, default: () => [] },
        logsLoading: { type: Boolean, default: false },
        minimumHoursFromParent: { type: Number, default: 6 },
    },
    emits: ['close', 'success'],
    data() {
        return {
            currentStepIndex: 0,
            form: {
                tanggal_lembur: this.data?.tanggal_lembur ?? '',
                tanggal_off: this.data?.tanggal_off ?? '',
                check_in: this.data?.check_in ?? '',
                check_out: this.data?.check_out ?? '',
                durasi_jam: this.data?.durasi_jam ?? 0,
                alasan: '',
                lampiran: null,
                jenis_konversi: (() => {
                    const types = Array.isArray(this.allowedConversionTypes) ? this.allowedConversionTypes : [];
                    const normalized = types
                        .map((value) => String(value || '').toUpperCase())
                        .filter((value) => ['DAY_OFF', 'MATERIAL', 'TO_CUTI'].includes(value));
                    const hasDayOff = normalized.includes('DAY_OFF');
                    const hasMaterial = normalized.includes('MATERIAL');
                    const hasToCuti = normalized.includes('TO_CUTI');

                    if (hasToCuti) {
                        return 'TO_CUTI';
                    }

                    if (hasMaterial && !hasDayOff) {
                        return 'MATERIAL';
                    }

                    return 'DAY_OFF';
                })(),
                errors: {},
            },
            offSelectedCheckIn: '',
            offSelectedCheckOut: '',
            workDates: [],
            workDateItems: [],
            offOptionsLoading: false,
            offOptionsError: '',
            offRangeStart: moment().format('YYYY-MM-DD'),
            offRangeEnd: moment().add(6, 'months').format('YYYY-MM-DD'),
            selectedWorkMonth: moment().startOf('month'),
            approvalPreviewLoading: false,
            approvalPreviewError: '',
            approvalPreview: {
                jenis_konversi: 'DAY_OFF',
                approval_code: '',
                needs_approval: true,
                approvers: [],
            },
            submitError: '',
            submitting: false,
        };
    },
    computed: {
        steps() {
            if (this.isOff) {
                return [
                    { id: 'select_checkinout', label: 'Pilih Log' },
                    { id: 'eligibility', label: 'Kelayakan' },
                    { id: 'metode', label: 'Metode' },
                    { id: 'detail', label: 'Detail' },
                    { id: 'review', label: 'Review' },
                ];
            }
            return [
                { id: 'eligibility', label: 'Kelayakan' },
                { id: 'metode', label: 'Metode' },
                { id: 'detail', label: 'Detail' },
                { id: 'review', label: 'Review' },
            ];
        },
        currentStepId() {
            return this.steps[this.currentStepIndex]?.id;
        },
        allowedConversionTypeList() {
            const types = Array.isArray(this.allowedConversionTypes) ? this.allowedConversionTypes : [];

            const normalized = types
                .map((value) => String(value || '').toUpperCase())
                .filter((value) => ['DAY_OFF', 'MATERIAL', 'TO_CUTI'].includes(value));

            return normalized.length ? [...new Set(normalized)] : [];
        },
        canChooseDayOff() {
            return this.allowedConversionTypeList.includes('DAY_OFF');
        },
        canChooseMaterial() {
            return this.allowedConversionTypeList.includes('MATERIAL');
        },
        canChooseToCuti() {
            return this.allowedConversionTypeList.includes('TO_CUTI');
        },
        defaultConversionType() {
            if (this.canChooseToCuti) {
                return 'TO_CUTI';
            }

            if (this.canChooseMaterial && !this.canChooseDayOff) {
                return 'MATERIAL';
            }

            return 'DAY_OFF';
        },
        isForcedToCuti() {
            return this.conversionContext?.is_forced_to_cuti === true || this.canChooseToCuti;
        },
        leaveDebtDays() {
            return Number(this.conversionContext?.hutang_cuti ?? 0);
        },
        forcedMessage() {
            return (
                this.conversionContext?.forced_message ||
                'Anda masih memiliki hutang cuti, sehingga konversi lembur saat ini diprioritaskan untuk mengurangi hutang cuti terlebih dahulu.'
            );
        },
        minimumHours() {
            return this.minimumHoursFromParent || 6;
        },
        uniqueRawLogs() {
            const seen = new Set();
            return (Array.isArray(this.rawLogs) ? this.rawLogs : []).filter((item) => {
                const key = String(item?.datetime || '').trim();
                if (!key || seen.has(key)) return false;
                seen.add(key);
                return true;
            });
        },
        checkoutCandidates() {
            if (!this.offSelectedCheckIn) return [];
            const inMoment = moment(this.offSelectedCheckIn);
            return this.uniqueRawLogs.filter((item) => moment(item.datetime).isAfter(inMoment));
        },
        offDurationMinutes() {
            if (!this.offSelectedCheckIn || !this.offSelectedCheckOut) return 0;
            const inM = moment(this.offSelectedCheckIn);
            const outM = moment(this.offSelectedCheckOut);
            if (!outM.isAfter(inM)) return 0;
            return outM.diff(inM, 'minutes');
        },
        offDurationLabel() {
            const hours = this.offDurationMinutes / 60;
            return `${hours.toFixed(1)} Jam`;
        },
        offIsEligible() {
            return this.offDurationMinutes >= this.minimumHours * 60;
        },
        eligibilityDurationMinutes() {
            if (!this.isOff) {
                return Math.round(Number(this.form.durasi_jam || 0) * 60);
            }

            if (!this.form.check_in || !this.form.check_out) return 0;
            const inM = moment(this.form.check_in);
            const outM = moment(this.form.check_out);
            if (!outM.isAfter(inM)) return 0;
            return outM.diff(inM, 'minutes');
        },
        eligibilityDurationLabel() {
            const hours = this.eligibilityDurationMinutes / 60;
            return `${hours.toFixed(1)} Jam`;
        },
        computedIsEligible() {
            return this.eligibilityDurationMinutes >= this.minimumHours * 60;
        },
        estimatedDayOff() {
            return Number(this.data?.estimated_day_off ?? 1);
        },
        minWorkMonth() {
            return moment(this.offRangeStart, 'YYYY-MM-DD').startOf('month');
        },
        maxWorkMonth() {
            return moment(this.offRangeEnd, 'YYYY-MM-DD').startOf('month');
        },
        workMonthLabel() {
            return this.selectedWorkMonth.locale('id').format('MMMM YYYY');
        },
        workDatesInMonth() {
            const key = this.selectedWorkMonth.format('YYYY-MM');
            return this.workDates.filter((d) => d.startsWith(`${key}-`));
        },
        workDateItemsInMonth() {
            const key = this.selectedWorkMonth.format('YYYY-MM');
            return this.workDateItems.filter((item) => String(item?.date || '').startsWith(`${key}-`));
        },
        canGoPrevWorkMonth() {
            return this.selectedWorkMonth.isAfter(this.minWorkMonth, 'month');
        },
        canGoNextWorkMonth() {
            return this.selectedWorkMonth.isBefore(this.maxWorkMonth, 'month');
        },
        approverList() {
            return Array.isArray(this.approvalPreview?.approvers) ? this.approvalPreview.approvers : [];
        },
        conversionNeedsApproval() {
            return this.approvalPreview?.needs_approval === true;
        },
        approvalCodeLabel() {
            const code = this.approvalPreview?.approval_code;
            return code ? `Kode: ${code}` : '';
        },
        canProceedCurrentStep() {
            const stepId = this.currentStepId;

            if (stepId === 'select_checkinout') {
                return !!this.offSelectedCheckIn && !!this.offSelectedCheckOut && this.offIsEligible;
            }

            if (stepId === 'eligibility') {
                return this.computedIsEligible;
            }

            if (stepId === 'metode') {
                return !this.conversionContextLoading && !!this.form.jenis_konversi;
            }

            if (stepId === 'detail') {
                if (this.form.jenis_konversi === 'DAY_OFF' && !this.form.tanggal_off) {
                    return false;
                }
                return !!String(this.form.alasan || '').trim();
            }

            return true;
        },
    },
    methods: {
        isConversionTypeAllowed(type) {
            return this.allowedConversionTypeList.includes(String(type || '').toUpperCase());
        },
        sanitizeConversionType(type) {
            if (!this.allowedConversionTypeList.length) {
                return '';
            }

            const normalized = String(type || '').toUpperCase();
            if (this.isConversionTypeAllowed(normalized)) {
                return normalized;
            }

            return this.defaultConversionType;
        },
        getConversionTypeLabel(type) {
            const normalized = this.sanitizeConversionType(type);
            if (normalized === 'MATERIAL') return 'Material / Uang';
            if (normalized === 'TO_CUTI') return 'Bayar Hutang Cuti';
            return 'Day Off';
        },
        nextStep() {
            if (!this.canProceedCurrentStep) return;
            if (this.currentStepIndex >= this.steps.length - 1) return;

            if (this.currentStepId === 'select_checkinout') {
                this.form.check_in = this.offSelectedCheckIn;
                this.form.check_out = this.offSelectedCheckOut;
                this.form.durasi_jam = this.offDurationMinutes / 60;
            }

            this.currentStepIndex += 1;
        },
        prevStep() {
            if (this.currentStepIndex <= 0) return;
            this.currentStepIndex -= 1;
        },
        async loadDayOffOptions() {
            this.offOptionsLoading = true;
            this.offOptionsError = '';

            try {
                const response = await axios.get('/konversi-lembur/day-off-options', {
                    params: {
                        start_date: this.offRangeStart,
                        end_date: this.offRangeEnd,
                    },
                });

                const payload = response?.data?.data || {};
                const workDateList = Array.isArray(payload.work_dates) ? payload.work_dates : [];
                const workDateItems = Array.isArray(payload.work_date_items) ? payload.work_date_items : [];

                this.offRangeStart = payload.start_date || this.offRangeStart;
                this.offRangeEnd = payload.end_date || this.offRangeEnd;
                this.workDates = workDateList;
                this.workDateItems = workDateItems;

                const selectedItem = workDateItems.find((item) => item?.date === this.form.tanggal_off);
                if (selectedItem?.disabled === true) {
                    this.form.tanggal_off = '';
                }

                if (this.form.jenis_konversi === 'DAY_OFF' && !this.form.tanggal_off && workDateList.length) {
                    this.form.tanggal_off = workDateList[0];
                }

                if (this.form.tanggal_off) {
                    this.selectedWorkMonth = moment(this.form.tanggal_off, 'YYYY-MM-DD').startOf('month');
                } else {
                    this.selectedWorkMonth = moment(this.offRangeStart, 'YYYY-MM-DD').startOf('month');
                }
            } catch (error) {
                const backendMessage = error?.response?.data?.message || error?.response?.data?.error;
                this.offOptionsError = backendMessage || 'Gagal memuat opsi tanggal day off.';
                this.workDates = [];
                this.workDateItems = [];
            } finally {
                this.offOptionsLoading = false;
            }
        },
        async loadApprovalPreview() {
            if (this.conversionContextLoading || !this.allowedConversionTypeList.length) {
                this.approvalPreviewLoading = false;
                this.approvalPreviewError = '';
                this.approvalPreview = {
                    jenis_konversi: '',
                    approval_code: '',
                    needs_approval: true,
                    approvers: [],
                };
                return;
            }

            this.approvalPreviewLoading = true;
            this.approvalPreviewError = '';

            const jenisKonversi = this.sanitizeConversionType(this.form.jenis_konversi);
            this.form.jenis_konversi = jenisKonversi;

            try {
                const response = await axios.get('/konversi-lembur/approval-preview', {
                    params: {
                        jenis_konversi: jenisKonversi,
                    },
                });

                this.approvalPreview = response?.data?.data || {
                    jenis_konversi: jenisKonversi,
                    approval_code: '',
                    needs_approval: true,
                    approvers: [],
                };
            } catch (error) {
                const backendMessage = error?.response?.data?.message || error?.response?.data?.error;
                this.approvalPreviewError = backendMessage || 'Gagal memuat rute approval.';
                this.approvalPreview = {
                    jenis_konversi: jenisKonversi,
                    approval_code: '',
                    needs_approval: true,
                    approvers: [],
                };
            } finally {
                this.approvalPreviewLoading = false;
            }
        },
        prevWorkMonth() {
            if (!this.canGoPrevWorkMonth) return;
            this.selectedWorkMonth = this.selectedWorkMonth.clone().subtract(1, 'month');
        },
        nextWorkMonth() {
            if (!this.canGoNextWorkMonth) return;
            this.selectedWorkMonth = this.selectedWorkMonth.clone().add(1, 'month');
        },
        isWorkDateDisabled(item) {
            return item?.disabled === true;
        },
        getWorkDateTooltip(item) {
            if (!item) return '';
            if (item.tooltip) return item.tooltip;
            return this.isWorkDateDisabled(item) ? 'Tanggal ini tidak tersedia.' : '';
        },
        getWorkDateClasses(item) {
            return {
                selected: this.form.tanggal_off === item?.date,
                disabled: this.isWorkDateDisabled(item),
            };
        },
        selectWorkDate(item) {
            const dateStr = typeof item === 'string' ? item : item?.date;
            if (!dateStr) return;
            if (typeof item === 'object' && this.isWorkDateDisabled(item)) return;
            this.form.tanggal_off = dateStr;
        },
        formatDayShort(dateStr) {
            return moment(dateStr, 'YYYY-MM-DD').locale('id').format('ddd').toUpperCase();
        },
        formatDayNumber(dateStr) {
            return moment(dateStr, 'YYYY-MM-DD').format('D');
        },
        formatMonthYearShort(dateStr) {
            return moment(dateStr, 'YYYY-MM-DD').locale('id').format('MMM YYYY');
        },
        handleFileUpload(e) {
            const file = e.target.files?.[0] || null;
            const allowedTypes = ['application/pdf', 'image/png', 'image/jpg', 'image/jpeg'];
            const maxSize = 10 * 1024 * 1024;

            this.form.errors = {
                ...(this.form.errors || {}),
                lampiran: '',
            };

            if (!file) {
                this.form.lampiran = null;
                return;
            }

            if (!allowedTypes.includes(file.type)) {
                this.form.lampiran = null;
                this.form.errors.lampiran = 'Lampiran hanya boleh PDF, PNG, JPG, atau JPEG.';
                e.target.value = '';
                return;
            }

            if (file.size > maxSize) {
                this.form.lampiran = null;
                this.form.errors.lampiran = 'Ukuran lampiran maksimal 10 MB.';
                e.target.value = '';
                return;
            }

            this.form.lampiran = file;
        },
        formatDate(date) {
            return date ? moment(date).format('DD MMM YYYY') : '-';
        },
        formatTimeOnly(dateTime) {
            return dateTime ? moment(dateTime).format('HH:mm') : '-';
        },
        syncConversionTypeFromAvailability() {
            if (this.conversionContextLoading) {
                this.form.jenis_konversi = '';
                return;
            }

            this.form.jenis_konversi = this.sanitizeConversionType(
                this.form.jenis_konversi || this.defaultConversionType,
            );
        },
        submit() {
            this.submitError = '';
            this.form.errors = {};

            this.form.jenis_konversi = this.sanitizeConversionType(this.form.jenis_konversi);

            if (!this.form.tanggal_lembur) {
                this.submitError = 'Tanggal lembur belum terisi. Silakan pilih ulang.';
                return;
            }

            if (this.form.jenis_konversi === 'DAY_OFF' && !this.form.tanggal_off) {
                this.submitError = 'Tanggal target OFF wajib dipilih.';
                return;
            }

            if (!this.form.check_in || !this.form.check_out) {
                this.submitError = 'Data check-in/check-out belum lengkap. Silakan pilih tanggal/log lain.';
                return;
            }

            if (this.form.errors?.lampiran) {
                this.submitError = this.form.errors.lampiran;
                return;
            }

            this.form.tanggal_lembur = moment(this.form.tanggal_lembur).format('YYYY-MM-DD');
            this.form.check_in = moment(this.form.check_in).format('YYYY-MM-DD HH:mm:ss');
            this.form.check_out = moment(this.form.check_out).format('YYYY-MM-DD HH:mm:ss');

            let submitUrl = '/konversi-lembur/store';
            try {
                if (typeof route === 'function') {
                    submitUrl = route('konversi-lembur.store');
                }
            } catch (e) {
                submitUrl = '/konversi-lembur/store';
            }

            // JANGAN sertakan _token / X-CSRF-TOKEN dari <meta> (basi setelah
            // session regenerate -> 419). Andalkan cookie XSRF-TOKEN otomatis;
            // interceptor 419 global menangani refresh & retry.
            const payload = new FormData();

            payload.append('tanggal_lembur', this.form.tanggal_lembur);
            payload.append('tanggal_off', this.form.tanggal_off || '');
            payload.append('check_in', this.form.check_in);
            payload.append('check_out', this.form.check_out);
            payload.append('durasi_jam', String(this.form.durasi_jam ?? 0));
            payload.append('alasan', this.form.alasan || '');
            payload.append('jenis_konversi', this.form.jenis_konversi || this.defaultConversionType);
            if (this.form.lampiran instanceof File) {
                payload.append('lampiran', this.form.lampiran);
            }

            this.submitting = true;
            axios
                .post(submitUrl, payload, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(() => {
                    this.$emit('success');
                    this.$emit('close');
                    this.form = {
                        tanggal_lembur: '',
                        tanggal_off: '',
                        check_in: '',
                        check_out: '',
                        durasi_jam: 0,
                        alasan: '',
                        lampiran: null,
                        jenis_konversi: this.defaultConversionType,
                        errors: {},
                    };
                    this.submitError = '';
                })
                .catch((error) => {
                    const fieldErrors = error?.response?.data?.errors || {};
                    if (Object.keys(fieldErrors).length > 0) {
                        this.form.errors = fieldErrors;
                    }

                    const firstErrorRaw = Object.values(fieldErrors || {}).find(Boolean);
                    const firstError = Array.isArray(firstErrorRaw) ? firstErrorRaw[0] : firstErrorRaw;
                    const backendMessage = error?.response?.data?.message || error?.response?.data?.error;

                    this.submitError =
                        firstError || backendMessage || 'Pengajuan gagal diproses. Periksa isian lalu coba lagi.';
                })
                .finally(() => {
                    this.submitting = false;
                });
        },
    },
    watch: {
        data: {
            handler(val) {
                this.form.tanggal_lembur = val?.tanggal_lembur ?? '';
                this.form.tanggal_off = val?.tanggal_off ?? '';
                this.form.check_in = val?.check_in ?? '';
                this.form.check_out = val?.check_out ?? '';
                this.form.durasi_jam = val?.durasi_jam ?? 0;
                this.form.jenis_konversi = this.sanitizeConversionType(val?.jenis_konversi ?? this.form.jenis_konversi);

                this.selectedWorkMonth = moment(this.form.tanggal_off || this.offRangeStart, 'YYYY-MM-DD').startOf(
                    'month',
                );
            },
            immediate: true,
            deep: true,
        },
        allowedConversionTypes: {
            handler() {
                this.syncConversionTypeFromAvailability();
            },
            immediate: true,
            deep: true,
        },
        'form.jenis_konversi': function (value) {
            if (this.conversionContextLoading || !value) {
                return;
            }
            this.loadApprovalPreview();
            if (value === 'MATERIAL' || value === 'TO_CUTI') {
                this.form.tanggal_off = '';
            } else {
                this.loadDayOffOptions();
            }
        },
        conversionContextLoading(value) {
            if (value) {
                this.form.jenis_konversi = '';
                return;
            }

            this.syncConversionTypeFromAvailability();
        },
        offSelectedCheckIn(value) {
            if (!value) {
                this.offSelectedCheckOut = '';
                return;
            }

            if (this.offSelectedCheckOut && !moment(this.offSelectedCheckOut).isAfter(moment(value))) {
                this.offSelectedCheckOut = '';
            }
        },
        show: {
            handler(value) {
                if (value) {
                    this.currentStepIndex = 0;
                    this.submitError = '';
                    this.offSelectedCheckIn = '';
                    this.offSelectedCheckOut = '';
                    this.syncConversionTypeFromAvailability();
                    this.loadApprovalPreview();
                    if (!this.conversionContextLoading && this.form.jenis_konversi === 'DAY_OFF') {
                        this.loadDayOffOptions();
                    }
                }
            },
            immediate: true,
        },
    },
};
</script>

<style scoped>
/* ─── Base Modal Overrides ─── */
.apply-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1060;
    display: grid;
    place-items: center;
    background: rgba(15, 23, 42, 0.45);
    padding: 1rem;
    backdrop-filter: blur(4px);
}

.forced-cuti-note {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.85rem 0.95rem;
    border: 1px solid #dbeafe;
    background: linear-gradient(135deg, #eff6ff 0%, var(--hcis-slate-50) 100%);
    border-radius: 16px;
}

.forced-cuti-note__icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #dbeafe;
    color: var(--hcis-blue-600);
    flex-shrink: 0;
}

.forced-cuti-note__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #1d4ed8;
    margin-bottom: 0.2rem;
}

.forced-cuti-note__text {
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    line-height: 1.55;
}

.locked-option {
    position: relative;
}

.apply-modal-card {
    width: min(720px, 100%);
    max-height: 90vh;
    border-radius: 16px;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
}

.apply-modal-header,
.apply-modal-footer {
    padding: 1rem 1.4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.apply-modal-header {
    border-bottom: 1px solid var(--hcis-slate-100);
}
.apply-modal-footer {
    border-top: 1px solid var(--hcis-slate-100);
    background: var(--hcis-slate-50);
    border-bottom-left-radius: 16px;
    border-bottom-right-radius: 16px;
}

.apply-modal-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.apply-modal-body {
    padding: 1.4rem;
    overflow-y: auto;
}

/* ─── Stitch Utilities ─── */
.stitch-card {
    border: 1px dashed rgba(0, 0, 0, 0.1);
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stitch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.55rem 1.25rem;
    font-size: 0.88rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.stitch-btn.primary {
    background: var(--hcis-blue-500);
    color: #fff;
    border-color: var(--hcis-blue-600);
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.25);
}
.stitch-btn.primary:hover:not(:disabled) {
    background: var(--hcis-blue-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
}

.stitch-btn.secondary {
    background: #fff;
    color: var(--hcis-slate-600);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.stitch-btn.secondary:hover:not(:disabled) {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-400);
    color: var(--hcis-slate-900);
}
.stitch-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.submit-label-mobile {
    display: none;
}

.submit-label-desktop {
    display: inline;
}

.transition-hover {
    transition: all 0.2s ease;
}

/* ─── Modern Form Controls ─── */
.form-control,
textarea.form-control,
input.form-control {
    background-color: var(--hcis-white) !important;
    border: 1.5px solid var(--hcis-slate-300) !important;
    color: var(--hcis-slate-800) !important;
    border-radius: 12px !important;
    font-size: 0.88rem !important;
    padding: 0.75rem 1rem !important;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.04) !important;
    transition: all 0.2s ease !important;
}

.form-control:hover:not(:disabled) {
    border-color: var(--hcis-slate-400) !important;
}

.form-control:focus,
.form-control.focus-ring:focus {
    background-color: var(--hcis-white) !important;
    border-color: var(--hcis-indigo-500) !important;
    box-shadow: 0 0 0 3.5px rgba(99, 102, 241, 0.15) !important;
    outline: none !important;
    color: var(--hcis-slate-900) !important;
}

.form-control::placeholder {
    color: var(--hcis-slate-400) !important;
    opacity: 1 !important;
}

.form-control:disabled,
.form-control[readonly] {
    background-color: var(--hcis-slate-50) !important;
    color: var(--hcis-slate-500) !important;
    border-color: var(--hcis-slate-200) !important;
    cursor: not-allowed !important;
}

/* File Upload Enhancement */
.file-upload-wrapper input[type="file"].form-control {
    padding: 0.6rem 0.85rem !important;
    cursor: pointer !important;
    background-color: var(--hcis-white) !important;
}

.file-upload-wrapper input[type="file"]::file-selector-button {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    margin-right: 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
}

.file-upload-wrapper input[type="file"]::file-selector-button:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-900);
    border-color: var(--hcis-slate-400);
}

/* Info Summary Box in Steps */
.info-summary-box {
    background: var(--hcis-white) !important;
    border: 1px solid var(--hcis-slate-200) !important;
    border-radius: 12px !important;
    padding: 0.75rem 0.85rem !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
}

/* ─── Stepper ─── */
.stepper-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1rem;
}

.step-item {
    display: flex;
    align-items: center;
    position: relative;
}

.step-indicator {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
    border: 2px solid var(--hcis-slate-200);
    transition: all 0.3s ease;
    z-index: 2;
}

.step-label {
    display: none;
}

.step-line {
    width: 40px;
    height: 3px;
    background: var(--hcis-slate-200);
    margin: 0 8px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.step-item.active .step-indicator {
    background: var(--hcis-blue-500);
    border-color: var(--hcis-blue-500);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}

.step-item.active .step-line {
    background: #eff6ff;
}

.step-item.done .step-indicator {
    background: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
    color: #fff;
}

.step-item.done .step-line {
    background: var(--hcis-emerald-500);
}

.step-item.active::after {
    content: attr(data-label);
    position: absolute;
    top: 35px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-blue-500);
    white-space: nowrap;
}

/* ─── Step Header Card ─── */
.step-header-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
}

.step-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* ─── Log Option Card (Check in/out) ─── */
.log-option-card {
    display: block;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
    padding: 0.8rem 1rem;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.log-option-card:hover {
    border-color: #bfdbfe;
    background: var(--hcis-slate-50);
}

.log-option-card.selected {
    border-color: var(--hcis-blue-500);
    background: #eff6ff;
    box-shadow: inset 0 0 0 1px var(--hcis-blue-500);
}

/* ─── Metode Grid ─── */
.metode-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.metode-grid.single-option {
    grid-template-columns: 1fr;
}

.metode-option {
    position: relative;
    border: 1.5px solid var(--hcis-slate-200);
    background: #fff;
    border-radius: 16px;
    padding: 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
    text-align: center;
    overflow: hidden;
}

.metode-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.2s ease;
}

.metode-label {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--hcis-slate-900);
}

.metode-option small {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}

.selection-ring {
    position: absolute;
    inset: 0;
    border: 2px solid transparent;
    border-radius: 16px;
    transition: all 0.2s ease;
    pointer-events: none;
}

.metode-option:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}
.metode-option:hover .metode-icon-wrap {
    background: var(--hcis-slate-200);
}

.metode-option.selected {
    border-color: transparent;
    background: #eff6ff;
}
.metode-option.selected .selection-ring {
    border-color: var(--hcis-blue-500);
}
.metode-option.selected .metode-icon-wrap {
    background: var(--hcis-blue-500);
    color: #fff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* ─── Approval Preview ─── */
.approval-preview-step {
    border-bottom: 1px solid var(--hcis-slate-100);
    padding: 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.approval-preview-step:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.approval-step-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.approval-step-order {
    min-width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #eff6ff;
    color: var(--hcis-blue-600);
    border: 1px dashed rgba(37, 99, 235, 0.3);
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* ─── Off Date Picker ─── */
.stitch-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    color: var(--hcis-slate-600);
    transition: all 0.2s ease;
}
.stitch-action-btn:hover:not(:disabled) {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-900);
}
.stitch-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.swap-date-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 0.5rem;
    max-height: 260px;
    overflow-y: auto;
    padding: 0.2rem;
}

.swap-date-item {
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    border-radius: 12px;
    padding: 0.6rem 0.4rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.16rem;
    cursor: pointer;
    position: relative;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.swap-date-item:hover {
    border-color: #bfdbfe;
    background: var(--hcis-slate-50);
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
}

.swap-date-item:disabled,
.swap-date-item.disabled {
    cursor: not-allowed;
    opacity: 0.92;
    background: #fff7f7;
    border-color: var(--hcis-red-200);
    box-shadow: none;
}

.swap-date-item:disabled:hover,
.swap-date-item.disabled:hover {
    transform: none;
    background: #fff7f7;
    border-color: var(--hcis-red-200);
    box-shadow: none;
}

.swap-date-item.selected {
    border-color: var(--hcis-blue-500);
    background: #eff6ff;
    box-shadow:
        inset 0 0 0 1px var(--hcis-blue-500),
        0 4px 8px rgba(59, 130, 246, 0.15);
}

.swap-date-tooltip {
    position: absolute;
    top: 0.45rem;
    left: 50%;
    transform: translateX(-50%);
    max-width: calc(100% - 0.75rem);
    background: rgba(127, 29, 29, 0.92);
    color: #fff;
    font-size: 0.46rem;
    line-height: 1.2;
    padding: 0.18rem 0.35rem;
    border-radius: 999px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    pointer-events: none;
}

.swap-date-day {
    font-size: 0.6rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
    text-transform: uppercase;
}

.swap-date-num {
    font-size: 1.25rem;
    color: var(--hcis-slate-900);
    font-weight: 800;
    line-height: 1;
    margin: 0.1rem 0;
}

.swap-date-month-year {
    font-size: 0.55rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
    text-transform: uppercase;
}

.border-dashed {
    border-style: dashed !important;
}

.conversion-context-loader {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    padding: 1rem;
    border: 1px solid #dbe7ff;
    border-radius: 1rem;
    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
}

.conversion-context-loader__title {
    color: #1f2937;
    font-size: 0.88rem;
    font-weight: 700;
    margin-bottom: 0.2rem;
}

.conversion-context-loader__text {
    color: var(--hcis-slate-500);
    font-size: 0.76rem;
    line-height: 1.5;
}

@media (max-width: 575.98px) {
    .submit-label-mobile {
        display: inline;
    }

    .submit-label-desktop {
        display: none;
    }
}
</style>
