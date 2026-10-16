<template>
    <div class="row g-4 konversi-calendar-layout">
        <div class="col-12 col-xl-8">
            <div class="calendar-head mb-3">
                <div>
                    <h2 class="calendar-title fs-5 mb-1">Pilih Tanggal Lembur</h2>
                    <p class="calendar-subtitle mb-0">Pilih tanggal lembur yang akan dikonversi</p>
                </div>
                <div class="calendar-legend">
                    <span class="cal-chip cal-chip-eligible">Eligible</span>
                    <span class="cal-chip cal-chip-applied">Applied</span>
                    <span class="cal-chip cal-chip-submitted">Submitted</span>
                    <span class="cal-chip cal-chip-expired">Expired</span>
                </div>
            </div>

            <div class="calendar-month-bar mb-3">
                <h3 class="calendar-month mb-0">{{ currentMonthName }} {{ currentYear }}</h3>
                <div class="d-flex gap-2">
                    <button
                        class="stitch-btn secondary btn-sm calendar-today-btn"
                        @click="goToToday"
                        :disabled="isCalendarLoading"
                        aria-label="Go to Today"
                    >
                        Hari Ini
                    </button>
                    <button
                        class="stitch-btn secondary btn-sm calendar-nav-btn"
                        @click="prevMonth"
                        :disabled="isCalendarLoading"
                        aria-label="Prev Month"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button
                        class="stitch-btn secondary btn-sm calendar-nav-btn"
                        @click="nextMonth"
                        :disabled="isCalendarLoading"
                        aria-label="Next Month"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="calendar-grid-header" v-if="!isCalendarLoading">
                <div v-for="day in weekDays" :key="day" class="calendar-weekday">
                    {{ day }}
                </div>
            </div>

            <div class="calendar-grid-body" v-if="!isCalendarLoading">
                <button
                    v-for="(date, index) in calendarDays"
                    :key="index"
                    type="button"
                    class="calendar-day"
                    :class="getDateClasses(date)"
                    @click="handleDateClick(date)"
                >
                    <div class="calendar-day-top">
                        <div class="calendar-day-number-wrap">
                            <span class="calendar-day-number">{{ date.day }}</span>
                            <span class="calendar-day-datehint">{{ formatMonth(date.date) }}</span>
                        </div>
                        <div class="calendar-day-meta">
                            <span class="calendar-day-worktype" :class="date.is_off ? 'text-danger' : ''">
                                {{ date.is_off ? 'OFF' : 'WORK' }}
                            </span>
                            <span
                                v-if="hasAttendance(date) && !['submitted', 'applied'].includes(date.status)"
                                class="calendar-day-attendance"
                                title="Ada log check-in/check-out"
                            >
                                <i class="bi bi-fingerprint"></i>
                            </span>
                        </div>
                    </div>
                    <div class="calendar-day-bottom">
                        <div
                            v-if="date.status === 'eligible' || date.status === 'converted'"
                            class="calendar-day-hours"
                        >
                            {{ date.duration_label }}
                        </div>
                        <div
                            v-if="statusLabel(date.status)"
                            class="calendar-day-status"
                            :class="`status-${date.status}`"
                        >
                            {{ statusLabel(date.status) }}
                        </div>
                        <div v-if="getDateMarkers(date).length" class="calendar-day-markers">
                            <span
                                v-for="marker in getDateMarkers(date)"
                                :key="`${date.date}-${marker.label}`"
                                class="day-marker"
                                :class="`marker-${marker.type}`"
                                :title="marker.tooltip || marker.label"
                            >
                                {{ marker.label }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>

            <div v-else class="calendar-skeleton-grid" aria-hidden="true">
                <div v-for="n in 35" :key="`calendar-skel-${n}`" class="calendar-skeleton-day">
                    <div class="calendar-skeleton-top">
                        <span class="calendar-skeleton-line short"></span>
                        <span class="calendar-skeleton-dot"></span>
                    </div>
                    <span class="calendar-skeleton-line medium"></span>
                    <span class="calendar-skeleton-line long"></span>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <transition name="dp-fade">
                <div v-if="selectedDate && !showFormModal" class="detail-panel h-100 kl-sticky-panel">
                    <div class="detail-head">
                        <div class="detail-head-text">
                            <span
                                v-if="statusLabel(selectedDate.status)"
                                class="status-pill mb-1"
                                :class="`status-pill-${selectedDate.status}`"
                            >
                                {{ statusLabel(selectedDate.status) }}
                            </span>
                            <h4 class="mb-0">Detail Konversi</h4>
                            <p class="mb-0 detail-date-label">
                                {{ formatDate(selectedDate.date) }}
                            </p>
                        </div>
                        <button class="detail-close-btn" @click="selectedDate = null" title="Tutup">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <div class="detail-card-row mb-3">
                        <div class="premium-info-card">
                            <div class="icon-wrap icon-blue">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <small>Total Jam Lembur</small>
                                <h5 class="mb-0 fw-bold text-dark">
                                    {{ selectedDurationLabel }}
                                </h5>
                                <p class="mb-0 text-muted small fw-medium">
                                    {{ selectedTimeRange }}
                                </p>
                            </div>
                        </div>

                        <div v-if="selectedDate.status === 'eligible'" class="premium-info-card">
                            <div class="icon-wrap icon-orange">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <div>
                                <small>Valid Until</small>
                                <h5 class="mb-0 fw-bold text-dark">
                                    {{ selectedDate.valid_until_label || '-' }}
                                </h5>
                                <p class="mb-0 text-warning small fw-medium" v-if="selectedDate.days_to_expire >= 0">
                                    Kadaluarsa dlm
                                    {{ selectedDate.days_to_expire }} hari
                                </p>
                                <p class="mb-0 text-danger small fw-medium" v-else>Sudah kadaluarsa</p>
                            </div>
                        </div>
                    </div>

                    <div class="rule-box mb-3">
                        <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>Ketentuan Konversi</h6>
                        <ul class="mb-0 ps-3">
                            <li>
                                {{ selectedDate.rule_label || conversionRule.label }}
                            </li>
                        </ul>
                    </div>

                    <div class="detail-info-box mb-3">
                        <h6 class="mb-2"><i class="bi bi-calendar3 me-2"></i>Informasi Tanggal</h6>
                        <div class="detail-info-grid">
                            <div class="detail-info-item">
                                <small>Status Hari</small>
                                <span>{{ formatStatusHari(selectedDate.status_hari) }}</span>
                            </div>
                            <div class="detail-info-item">
                                <small>Log Absensi</small>
                                <span>
                                    {{
                                        selectedDate.has_attendance
                                            ? `Ada (${selectedDate.log_count || 0} log)`
                                            : 'Tidak ada'
                                    }}
                                </span>
                            </div>
                            <div class="detail-info-item">
                                <small>Check In</small>
                                <span>{{ formatTimeOnly(selectedDate.check_in_auto) }}</span>
                            </div>
                            <div class="detail-info-item">
                                <small>Check Out</small>
                                <span>{{ formatTimeOnly(selectedDate.check_out_auto) }}</span>
                            </div>
                            <div class="detail-info-item full">
                                <small>Info Transaksi</small>
                                <span>{{ selectedDate.approval_label || 'Belum ada transaksi' }}</span>
                            </div>
                            <div v-if="selectedDate.jenis_konversi === 'DAY_OFF'" class="detail-info-item full">
                                <small>Target OFF</small>
                                <span>{{ formatDayMonth(selectedDate.target_off) }}</span>
                            </div>
                            <div v-else-if="selectedDate.jenis_konversi === 'TO_CUTI'" class="detail-info-item full">
                                <small>Target Konversi</small>
                                <span>Bayar hutang cuti</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedRelatedTransactions.length" class="related-box mb-3">
                        <h6 class="mb-3"><i class="bi bi-tags me-2"></i>Transaksi Terkait</h6>
                        <div
                            v-for="item in selectedRelatedTransactions"
                            :key="`${selectedDate.date}-${item.jenis}-${item.no_transaksi}-${item.status}`"
                            class="related-item"
                        >
                            <div class="related-main">
                                <span class="related-badge" :class="`related-${getTransactionTypeClass(item)}`">
                                    {{ item.jenis_label }}
                                </span>
                                <span
                                    class="status-pill status-pill-soft"
                                    :class="`status-pill-${getTransactionStatusClass(item)}`"
                                >
                                    {{ item.status_label }}
                                </span>
                            </div>
                            <small class="related-summary">
                                {{ item.summary || item.jenis_label }}
                            </small>
                        </div>
                    </div>

                    <div v-if="['submitted', 'applied'].includes(selectedDate.status)" class="approval-box mb-3">
                        <h6 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>Alur Persetujuan</h6>
                        <div v-for="(step, idx) in selectedApprovalSteps" :key="idx" class="approval-step">
                            <div class="approval-left">
                                <span class="approval-dot" :class="`approval-${step.status}`"></span>
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        {{ step.step }}
                                    </p>
                                    <small class="text-muted">{{ step.actor }}</small>
                                    <small v-if="step.approved_at || step.note" class="approval-meta d-block">
                                        <span v-if="step.approved_at">
                                            {{
                                                step.status === 'approved'
                                                    ? `Disetujui ${step.approved_at}`
                                                    : step.status === 'rejected'
                                                      ? `Ditolak ${step.approved_at}`
                                                      : step.approved_at
                                            }}
                                        </span>
                                        <span v-if="step.note">
                                            {{ step.approved_at ? ' - ' : '' }}{{ step.note }}
                                        </span>
                                    </small>
                                </div>
                            </div>
                            <span class="status-pill status-pill-soft" :class="`status-pill-${step.status}`">
                                {{ step.status_label || step.status }}
                            </span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button
                            v-if="hasAnyAllowedConversionType && (canApplyOff || canDirectApplyWork)"
                            type="button"
                            class="stitch-btn primary w-100"
                            @click="openFormModal"
                            :disabled="modalLogsLoading || !canStartSubmission"
                        >
                            <span v-if="modalLogsLoading || conversionContextLoading" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-send"></i>
                            <template v-if="isForcedToCuti">
                                Ajukan Bayar Hutang Cuti
                            </template>
                            <template v-else>
                                Ajukan Konversi &mdash;
                                {{ conversionRule.day_off_reward }} Hari Off
                            </template>
                        </button>
                        <div v-if="conversionContextLoading" class="submission-guard-note">
                            Memverifikasi status hutang cuti sebelum pengajuan dibuka.
                        </div>
                        <div v-else-if="conversionContextError" class="submission-guard-note is-error">
                            <span>{{ conversionContextError }}</span>
                            <button type="button" class="guard-retry-btn" @click="retryConversionContext">
                                Coba Lagi
                            </button>
                        </div>
                    </div>
                </div>
            </transition>

            <transition name="dp-fade">
                <div v-if="!selectedDate" class="detail-empty">
                    <div class="detail-empty-visual">
                        <i class="bi bi-calendar2-week"></i>
                    </div>
                    <p class="detail-empty-title">Pilih Tanggal</p>
                    <p class="detail-empty-hint">
                        Klik tanggal pada kalender untuk melihat detail &amp; mengajukan konversi lembur.
                    </p>
                </div>
            </transition>
        </div>

        <ApplicationFormModal
            v-if="showFormModal"
            :loading="loading"
            :show="showFormModal"
            :data="formData"
            :isOff="formData.is_off === true"
            :allowedConversionTypes="modalAllowedConversionTypes"
            :rawLogs="modalRawLogs"
            :logsLoading="modalLogsLoading"
            :minimumHoursFromParent="conversionRule.minimal_hours || 6"
            :conversionContext="modalConversionContext"
            :conversionContextLoading="modalConversionContextLoading"
            @close="showFormModal = false"
            @success="handleFormSuccess"
        />
    </div>
</template>

<script>
import moment from 'moment';
import axios from 'axios';
import ApplicationFormModal from './ApplicationFormModal.vue';

export default {
    name: 'ConversionCalendar',
    emits: ['submit-success'],
    components: {
        ApplicationFormModal,
    },
    props: {
        userCode: String,
        allowedConversionTypes: {
            type: Array,
            default: () => [],
        },
        conversionContext: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        const initialConversionContext = this.conversionContext || {};
        const hasInitialConversionContext = Object.keys(initialConversionContext).length > 0;

        return {
            currentMonth: moment(),
            calendarDays: [],
            calendarMeta: {},
            conversionRule: {
                minimal_hours: 6,
                day_off_reward: 1,
                valid_days: 90,
                label: 'Min. 6 jam lembur = 1 hari libur',
            },
            selectedDate: null,
            showFormModal: false,
            formData: {},
            isCalendarLoading: false,
            modalRawLogs: [],
            modalLogsLoading: false,
            weekDays: ['SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB', 'MIN'],
            liveConversionContext: initialConversionContext,
            modalConversionContext: initialConversionContext,
            modalConversionContextLoading: false,
            modalAllowedConversionTypes: [],
            conversionContextLoading: false,
            conversionContextResolved: hasInitialConversionContext,
            conversionContextError: '',
            conversionContextRequest: null,
        };
    },
    computed: {
        currentMonthName() {
            return this.currentMonth.format('MMMM');
        },
        currentYear() {
            return this.currentMonth.format('YYYY');
        },
        allowedConversionTypeList() {
            const contextTypes = Array.isArray(this.liveConversionContext?.available_conversion_types)
                ? this.liveConversionContext.available_conversion_types
                : [];
            const types = Array.isArray(this.allowedConversionTypes) ? this.allowedConversionTypes : [];

            const normalized = (contextTypes.length ? contextTypes : types)
                .map((value) => String(value || '').toUpperCase())
                .filter((value) => ['DAY_OFF', 'MATERIAL', 'TO_CUTI'].includes(value));

            return normalized.length ? [...new Set(normalized)] : ['DAY_OFF', 'MATERIAL'];
        },
        hasAnyAllowedConversionType() {
            return this.allowedConversionTypeList.length > 0;
        },
        canStartSubmission() {
            return this.conversionContextResolved === true && !this.conversionContextLoading && !this.conversionContextError;
        },
        isForcedToCuti() {
            return this.liveConversionContext?.is_forced_to_cuti === true;
        },
        selectedDurationLabel() {
            return this.selectedDate?.duration_label || '-';
        },
        selectedTimeRange() {
            return this.selectedDate?.time_range || '-';
        },
        selectedApprovalSteps() {
            return this.selectedDate?.approval_steps || [];
        },
        selectedRelatedTransactions() {
            return Array.isArray(this.selectedDate?.related_transactions) ? this.selectedDate.related_transactions : [];
        },
        canApplyOff() {
            if (!this.selectedDate || !this.hasAnyAllowedConversionType) return false;
            if (this.selectedDate.is_off !== true) return false;
            if (['submitted', 'applied', 'expired'].includes(this.selectedDate.status)) return false;
            return this.selectedDate.can_select_log_off === true && this.selectedDate.has_attendance === true;
        },
        canDirectApplyWork() {
            if (!this.selectedDate || this.selectedDate.is_off || !this.hasAnyAllowedConversionType) {
                return false;
            }

            return this.isWorkDateEligibleForSubmission(this.selectedDate);
        },
    },
    methods: {
        normalizeAllowedConversionTypes(types = []) {
            const values = Array.isArray(types) ? types : [];

            const normalized = values
                .map((value) => String(value || '').toUpperCase())
                .filter((value) => ['DAY_OFF', 'MATERIAL', 'TO_CUTI'].includes(value));

            return normalized.length ? [...new Set(normalized)] : [];
        },
        resolveAllowedConversionTypes(context = null) {
            const contextTypes = this.normalizeAllowedConversionTypes(context?.available_conversion_types || []);
            if (contextTypes.length) {
                return contextTypes;
            }

            const propTypes = this.normalizeAllowedConversionTypes(this.allowedConversionTypes);
            return propTypes.length ? propTypes : ['DAY_OFF', 'MATERIAL'];
        },
        applyResolvedConversionContext(context = {}) {
            const nextContext = context && typeof context === 'object' ? context : {};
            const nextTypes = this.resolveAllowedConversionTypes(nextContext);

            this.liveConversionContext = { ...nextContext };
            this.modalConversionContext = { ...nextContext };
            this.modalAllowedConversionTypes = nextTypes;
            this.conversionContextResolved = true;
            this.conversionContextError = '';

            if (this.showFormModal) {
                this.formData = {
                    ...this.formData,
                    jenis_konversi: this.resolveDefaultConversionType(nextTypes),
                };
            }
        },
        ensureConversionContextLoaded({ force = false } = {}) {
            if (this.conversionContextRequest && !force) {
                return this.conversionContextRequest;
            }

            this.conversionContextLoading = true;
            this.conversionContextError = '';

            const request = axios
                .get('/konversi-lembur/conversion-context')
                .then((response) => {
                    const nextContext = response?.data?.data || {};
                    this.applyResolvedConversionContext(nextContext);
                    return nextContext;
                })
                .catch((error) => {
                    console.error('Failed to load conversion context', error);
                    this.conversionContextResolved = false;
                    this.conversionContextError =
                        error?.response?.data?.message ||
                        'Gagal memverifikasi status hutang cuti. Pengajuan sementara dikunci sampai pengecekan berhasil.';
                    throw error;
                })
                .finally(() => {
                    this.conversionContextLoading = false;
                    this.conversionContextRequest = null;
                });

            this.conversionContextRequest = request;
            return request;
        },
        retryConversionContext() {
            this.ensureConversionContextLoaded({ force: true }).catch(() => {});
        },
        decorateDateForDisplay(dateObj) {
            if (!dateObj) {
                return dateObj;
            }

            if (dateObj.is_off !== true && dateObj.status === 'work' && this.isWorkDateEligibleForSubmission(dateObj)) {
                return {
                    ...dateObj,
                    status: 'eligible',
                    is_eligible: true,
                    approval_label: dateObj.approval_label || 'Siap Diajukan',
                };
            }

            return dateObj;
        },
        getMinimumEligibleMinutes() {
            return Math.round(Number(this.conversionRule?.minimal_hours || 6) * 60);
        },
        isWorkDateEligibleForSubmission(dateObj) {
            if (!dateObj || dateObj.is_off === true) {
                return false;
            }

            if (['submitted', 'applied', 'expired'].includes(dateObj.status)) {
                return false;
            }

            const hasAutomaticAttendance = !!dateObj.check_in_auto && !!dateObj.check_out_auto;
            const durationMinutes = Number(dateObj.duration_minutes || 0);

            return (
                hasAutomaticAttendance &&
                dateObj.has_attendance === true &&
                durationMinutes >= this.getMinimumEligibleMinutes()
            );
        },
        statusLabel(status) {
            switch (status) {
                case 'eligible':
                    return 'Eligible';
                case 'applied':
                    return 'Applied';
                case 'submitted':
                    return 'Submitted';
                case 'converted':
                    return 'Converted';
                case 'expired':
                    return 'Expired';
                case 'off':
                    return '';
                case 'work':
                    return '';
                case 'approved':
                    return 'Approved';
                case 'pending':
                    return 'Pending';
                case 'upcoming':
                    return 'Upcoming';
                default:
                    return '';
            }
        },
        generateCalendar() {
            const monthStart = this.currentMonth.clone().startOf('month');
            const monthEnd = this.currentMonth.clone().endOf('month');
            const mondayBasedOffset = (monthStart.isoWeekday() + 6) % 7;
            const days = [];

            for (let i = 0; i < mondayBasedOffset; i++) {
                const dateObj = monthStart.clone().subtract(mondayBasedOffset - i, 'days');
                days.push(this.buildDayObject(dateObj, false));
            }

            for (let i = 1; i <= monthEnd.date(); i++) {
                const dateObj = monthStart.clone().date(i);
                days.push(this.buildDayObject(dateObj, true));
            }

            while (days.length % 7 !== 0 || days.length < 35) {
                const nextDate = monthEnd.clone().add(days.length - (mondayBasedOffset + monthEnd.date()) + 1, 'days');
                days.push(this.buildDayObject(nextDate, false));
            }

            this.selectedDate = null;
            this.calendarDays = days;
            this.fetchCalendarStatus();
        },
        buildDayObject(dateObj, isCurrentMonth) {
            const date = dateObj.format('YYYY-MM-DD');
            const fallbackStatus = dateObj.day() === 0 || dateObj.day() === 6 ? 'off' : 'work';

            return {
                day: dateObj.date(),
                date,
                isCurrentMonth,
                status: fallbackStatus,
                is_off: fallbackStatus === 'off',
                duration_label: '-',
                approval_steps: [],
            };
        },
        async fetchCalendarStatus() {
            this.isCalendarLoading = true;
            try {
                const response = await axios.get('/konversi-lembur/calendar', {
                    params: {
                        month: this.currentMonth.month() + 1,
                        year: this.currentMonth.year(),
                    },
                });

                const payload = response?.data?.data || {};
                const serverDates = Array.isArray(payload?.dates) ? payload.dates : [];
                if (payload?.conversion_context && Object.keys(payload.conversion_context).length > 0) {
                    this.applyResolvedConversionContext(payload.conversion_context);
                }
                this.calendarMeta = serverDates.reduce((acc, item) => {
                    acc[item.date] = this.decorateDateForDisplay(item);
                    return acc;
                }, {});
                this.conversionRule = payload?.rule || this.conversionRule;

                this.calendarDays = this.calendarDays.map((day) => {
                    const serverItem = this.calendarMeta[day.date];
                    return serverItem ? this.decorateDateForDisplay({ ...day, ...serverItem }) : day;
                });
            } catch (error) {
                console.error('Failed to fetch calendar status', error);
            } finally {
                this.isCalendarLoading = false;
            }
        },
        handleDateClick(dateObj) {
            if (!dateObj?.isCurrentMonth) {
                return;
            }

            const merged = {
                ...dateObj,
                ...(this.calendarMeta[dateObj.date] || {}),
            };

            this.selectedDate = this.decorateDateForDisplay(merged);
        },
        async fetchRawLogsForModal(date) {
            this.modalLogsLoading = true;
            this.modalRawLogs = [];
            try {
                const response = await axios.get('/konversi-lembur/logs', {
                    params: { date, conversion_type: 'DAY_OFF' },
                });
                const payload = response?.data?.data || {};
                this.modalRawLogs = Array.isArray(payload?.raw_logs) ? payload.raw_logs : [];
            } catch (error) {
                console.error('Failed to fetch logs for modal', error);
                this.modalRawLogs = [];
            } finally {
                this.modalLogsLoading = false;
            }
        },
        openFormModal() {
            if (!this.canStartSubmission) {
                return;
            }

            const initialTypes = this.resolveAllowedConversionTypes(this.liveConversionContext);
            const defaultConversionType = this.resolveDefaultConversionType(initialTypes);

            this.modalConversionContextLoading = false;
            this.modalConversionContext = { ...this.liveConversionContext };
            this.modalAllowedConversionTypes = initialTypes;

            // OFF: fetch raw logs, user will pick check-in/out inside the modal
            if (this.selectedDate?.is_off) {
                this.formData = {
                    tanggal_lembur: this.selectedDate.date,
                    check_in: '',
                    check_out: '',
                    durasi: '-',
                    durasi_jam: 0,
                    tanggal_off: '',
                    estimated_day_off: this.selectedDate.estimated_day_off ?? 1,
                    is_off: true,
                    checktime: [],
                    jenis_konversi: defaultConversionType,
                };
                // Fetch logs in background, modal will show loading
                this.fetchRawLogsForModal(this.selectedDate.date);
                // this.getChecktime(this.selectedDate.date);
                this.showFormModal = true;
            } else {
                // WORK: pakai checkin/out otomatis dari hari tersebut
                if (!this.canDirectApplyWork) return;

                const durationMinutes = Number(this.selectedDate.duration_minutes || 0);
                this.formData = {
                    tanggal_lembur: this.selectedDate.date,
                    check_in: this.selectedDate.check_in_auto,
                    check_out: this.selectedDate.check_out_auto,
                    durasi: this.selectedDate.duration_label,
                    durasi_jam: durationMinutes / 60,
                    tanggal_off: '',
                    estimated_day_off: this.selectedDate.estimated_day_off ?? 1,
                    is_off: false,
                    checktime: [],
                    jenis_konversi: defaultConversionType,
                };
                this.showFormModal = true;
            }
        },
        resolveDefaultConversionType(types = null) {
            const allowedTypes = Array.isArray(types) ? types : this.allowedConversionTypeList;
            if (allowedTypes.length === 1 && allowedTypes[0]) {
                return allowedTypes[0];
            }

            return 'DAY_OFF';
        },
        async getChecktime(date) {
            const response = await axios.get('/konversi-lembur/get-checktime', {
                params: { date },
            });

            const payload = response?.data?.data || {};
            this.formData = {
                ...this.formData,
                checktime: payload.checktime,
            };
        },
        handleFormSuccess() {
            this.$emit('submit-success');
            this.selectedDate = null;
            this.modalRawLogs = [];
            this.fetchCalendarStatus();
            window.dispatchEvent(new Event('konversi-lembur:refresh-stats'));
            window.dispatchEvent(new Event('konversi-lembur:refresh-history'));
        },
        prevMonth() {
            this.currentMonth = moment(this.currentMonth).subtract(1, 'month');
            this.generateCalendar();
        },
        nextMonth() {
            this.currentMonth = moment(this.currentMonth).add(1, 'month');
            this.generateCalendar();
        },
        goToToday() {
            this.currentMonth = moment();
            this.generateCalendar();
        },
        formatStatusHari(status) {
            if (!status) return '-';
            return status
                .split('-')
                .map((part) => {
                    // Keep short prefixes like OFF in uppercase, otherwise capitalize words
                    if (part.toUpperCase() === 'OFF') return 'OFF';
                    return part
                        .split('_')
                        .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
                        .join(' ');
                })
                .join(' - ');
        },
        formatDate(date) {
            return moment(date).locale('id').format('dddd, DD MMMM YYYY');
        },
        formatDayMonth(date) {
            return date ? moment(date).locale('id').format('DD MMM') : '-';
        },
        formatMonth(date) {
            return date ? moment(date).locale('id').format('MMM') : '-';
        },
        formatTimeOnly(dateTime) {
            return dateTime ? moment(dateTime).format('HH:mm') : '-';
        },
        getTransactionStatusClass(item) {
            const status = String(item?.status || '').toUpperCase();
            if (status === 'APPROVED' || status === 'APPLIED') return 'approved';
            if (status === 'PENDING' || status === 'CURRENT') return 'pending';
            if (status === 'REJECTED') return 'rejected';
            return 'upcoming';
        },
        getTransactionTypeClass(item) {
            const jenis = String(item?.jenis || '').toUpperCase();
            if (jenis === 'CUTI') return 'cuti';
            if (jenis === 'SAKIT_IZIN') return 'sakit';
            if (jenis === 'TUKAR_HARI') return 'swap';
            if (jenis === 'KONVERSI_LEMBUR') return 'conversion';
            if (jenis === 'TERLAMBAT') return 'late';
            return 'generic';
        },
        getTransactionMarkerLabel(item) {
            const jenis = String(item?.jenis || '').toUpperCase();
            const status = String(item?.status || '').toUpperCase();
            const suffix = status === 'PENDING' ? ' (P)' : '';

            if (jenis === 'KONVERSI_LEMBUR') {
                const summary = String(item?.summary || '').toLowerCase();
                if (summary.includes('hutang cuti')) {
                    return `Bayar Cuti${suffix}`;
                }
                if (
                    summary.includes('material')
                ) {
                    return `Konv Uang${suffix}`;
                }

                return `Konv Off${suffix}`;
            }

            if (jenis === 'TUKAR_HARI') return `Tukar Hari${suffix}`;
            if (jenis === 'SAKIT_IZIN') return `Sakit/Izin${suffix}`;
            if (jenis === 'CUTI') return `Cuti${suffix}`;
            if (jenis === 'TERLAMBAT') return `Terlambat${suffix}`;

            return `${item?.jenis_label || 'Transaksi'}${suffix}`;
        },
        hasAttendance(dateObj) {
            return dateObj?.has_attendance === true;
        },
        getDateMarkers(dateObj) {
            const markers = [];
            const conversionMarkerLabel = dateObj?.conversion_summary
                ? this.getTransactionMarkerLabel({
                      jenis: 'KONVERSI_LEMBUR',
                      status: dateObj?.status === 'submitted' ? 'PENDING' : 'APPROVED',
                      summary: dateObj.conversion_summary,
                  })
                : null;

            if (dateObj?.is_applied || dateObj?.status === 'applied') {
                markers.push({
                    label: conversionMarkerLabel || 'Sudah applied',
                    type: 'applied',
                    tooltip: dateObj?.conversion_summary || 'Sudah applied',
                });
            } else if (dateObj?.is_submitted || dateObj?.status === 'submitted') {
                markers.push({
                    label: conversionMarkerLabel || 'Submitted',
                    type: 'submitted',
                    tooltip: dateObj?.approval_label || dateObj?.conversion_summary || 'Menunggu persetujuan',
                });
            } else if (dateObj?.status === 'expired') {
                markers.push({ label: 'Melewati batas', type: 'expired' });
            } else if (dateObj?.status === 'eligible' || this.isWorkDateEligibleForSubmission(dateObj)) {
                markers.push({ label: 'Siap diajukan', type: 'eligible' });
            }

            if (
                dateObj?.has_attendance &&
                !['submitted', 'applied', 'eligible'].includes(dateObj?.status) &&
                !this.isWorkDateEligibleForSubmission(dateObj)
            ) {
                markers.push({ label: 'Checkin/out', type: 'attendance' });
            }

            const relatedTransactions = Array.isArray(dateObj?.related_transactions)
                ? dateObj.related_transactions
                : [];

            relatedTransactions.forEach((item) => {
                if (
                    String(item?.jenis || '').toUpperCase() === 'KONVERSI_LEMBUR' &&
                    ['submitted', 'applied'].includes(dateObj?.status)
                ) {
                    return;
                }

                markers.push({
                    label: this.getTransactionMarkerLabel(item),
                    type: this.getTransactionStatusClass(item),
                    tooltip: `${item.jenis_label} - ${item.status_label}${item.summary ? `: ${item.summary}` : ''}`,
                });
            });

            return markers.slice(0, 3);
        },
        isDateClickable(date) {
            if (!date?.isCurrentMonth) {
                return false;
            }

            const item = { ...date, ...(this.calendarMeta[date.date] || {}) };
            const clickableWork =
                item.is_off !== true &&
                (this.isWorkDateEligibleForSubmission(item) ||
                    ['submitted', 'applied', 'expired'].includes(item.status));
            const clickableOff =
                item.is_off === true &&
                (item.can_select_log_off === true || ['submitted', 'applied', 'expired'].includes(item.status));

            return clickableWork || clickableOff;
        },
        isToday(date) {
            return !!date?.date && moment(date.date).isSame(moment(), 'day');
        },
        getDateClasses(date) {
            return {
                'is-outside': !date.isCurrentMonth,
                'is-disabled': !this.isDateClickable(date),
                'is-today': this.isToday(date),
                'is-selected': this.selectedDate?.date === date.date,
                'is-eligible': date.status === 'eligible' || this.isWorkDateEligibleForSubmission(date),
                'is-applied': date.status === 'applied',
                'is-submitted': date.status === 'submitted',
                'is-converted': date.status === 'converted',
                'is-expired': date.status === 'expired',
            };
        },
    },
    mounted() {
        this.generateCalendar();
    },
    watch: {
        conversionContext: {
            handler(value) {
                const nextContext = value || {};
                if (Object.keys(nextContext).length > 0) {
                    this.applyResolvedConversionContext(nextContext);
                } else {
                    this.liveConversionContext = nextContext;
                    if (!this.showFormModal) {
                        this.modalConversionContext = nextContext;
                        this.modalAllowedConversionTypes = this.resolveAllowedConversionTypes(nextContext);
                    }
                }
            },
            deep: true,
        },
    },
};
</script>

<style scoped>
.konversi-calendar-layout {
    --k-border: var(--hcis-slate-200);
    --k-muted: var(--hcis-slate-500);
    --k-title: var(--hcis-slate-800);
    --k-success: #22c55e;
    --k-warning: #eab308;
    --k-danger: var(--hcis-red-400);
    --k-primary: var(--hcis-indigo-500);
}

.calendar-head {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-start;
}

.calendar-title {
    color: var(--k-title);
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.calendar-subtitle {
    color: var(--k-muted);
    font-size: 0.88rem;
}

/* Legend chips */
.calendar-legend {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.cal-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 20px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.cal-chip::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    margin-right: 0.35rem;
    display: inline-block;
}

.cal-chip-eligible {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.cal-chip-eligible::before {
    background: #22c55e;
}
.cal-chip-applied {
    background: var(--hcis-cyan-100);
    color: var(--hcis-cyan-700);
}
.cal-chip-applied::before {
    background: var(--hcis-cyan-500);
}
.cal-chip-submitted {
    background: var(--hcis-yellow-100);
    color: var(--hcis-yellow-700);
}
.cal-chip-submitted::before {
    background: #eab308;
}
.cal-chip-expired {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}
.cal-chip-expired::before {
    background: var(--hcis-red-500);
}

.calendar-month-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--hcis-slate-50);
    border: 1px solid var(--k-border);
    border-radius: 12px;
    padding: 0.6rem 0.85rem;
}

.calendar-month {
    font-size: 0.95rem;
    color: var(--hcis-slate-800);
    font-weight: 700;
    letter-spacing: -0.01em;
}

.calendar-nav-btn {
    border: 1px solid var(--k-border);
    border-radius: 8px;
    transition: all 0.15s ease;
}

.calendar-today-btn {
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 8px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-weight: 700;
    padding-inline: 0.9rem;
    transition: all 0.15s ease;
}

.calendar-nav-btn:hover {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-300);
    color: var(--hcis-indigo-600);
}

.calendar-today-btn:hover {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-300);
    color: var(--hcis-indigo-800);
}

.calendar-grid-header,
.calendar-grid-body {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.75rem;
}

.calendar-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.75rem;
}

.calendar-skeleton-day {
    border: 1.5px solid var(--k-border);
    border-radius: 12px;
    background: #fff;
    min-height: 128px;
    height: 128px;
    padding: 0.62rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.calendar-skeleton-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.calendar-skeleton-line,
.calendar-skeleton-dot {
    background: linear-gradient(90deg, var(--hcis-slate-200) 25%, var(--hcis-slate-100) 50%, var(--hcis-slate-200) 75%);
    background-size: 220% 100%;
    animation: calendarShimmer 1.2s infinite linear;
}

.calendar-skeleton-line {
    display: block;
    height: 10px;
    border-radius: 8px;
}

.calendar-skeleton-line.short {
    width: 30%;
}

.calendar-skeleton-line.medium {
    width: 48%;
}

.calendar-skeleton-line.long {
    width: 66%;
}

.calendar-skeleton-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
}

@keyframes calendarShimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

.calendar-weekday {
    text-align: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    padding: 0.25rem 0;
}

.calendar-day {
    border: 1.5px solid var(--k-border);
    border-radius: 12px;
    background: #fff;
    min-height: 128px;
    height: 128px;
    padding: 0.62rem;
    text-align: left;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.18s ease;
    cursor: pointer;
    overflow: hidden;
}

.calendar-day:not(.is-outside):hover {
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.13);
    transform: translateY(-1px);
}

.calendar-day.is-disabled {
    cursor: pointer;
    opacity: 0.78;
}

.calendar-day.is-today {
    border-color: var(--hcis-indigo-600);
    box-shadow:
        inset 0 0 0 1px rgba(79, 70, 229, 0.22),
        0 10px 24px rgba(79, 70, 229, 0.12);
    position: relative;
}

.calendar-day.is-today::after {
    content: '';
    position: absolute;
    top: 0.55rem;
    right: 0.55rem;
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: var(--hcis-indigo-600);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.14);
}

.calendar-day.is-today .calendar-day-number {
    color: var(--hcis-indigo-900);
    background: rgba(79, 70, 229, 0.12);
    border: 1px solid rgba(79, 70, 229, 0.2);
    border-radius: 999px;
    min-width: 2rem;
    min-height: 2rem;
    padding: 0.2rem 0.45rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.calendar-day.is-today .calendar-day-datehint,
.calendar-day.is-today .calendar-day-worktype {
    color: var(--hcis-indigo-600);
}

.calendar-day.is-selected {
    border-color: var(--k-primary);
    box-shadow:
        0 0 0 3px rgba(99, 102, 241, 0.18),
        0 6px 18px rgba(99, 102, 241, 0.12);
    background: #fafaff;
}

.calendar-day.is-today.is-selected {
    box-shadow:
        0 0 0 3px rgba(99, 102, 241, 0.2),
        inset 0 0 0 1px rgba(79, 70, 229, 0.2),
        0 10px 26px rgba(79, 70, 229, 0.16);
}

.calendar-day.is-outside {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-300);
}

.calendar-day.is-eligible {
    background: var(--hcis-green-50);
    border-color: #86efac;
}

.calendar-day.is-applied {
    background: #ecf8ff;
    border-color: var(--hcis-cyan-500);
}

.calendar-day.is-submitted {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-300);
}

.calendar-day.is-converted {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-300);
}

.calendar-day.is-expired {
    background: #fff1f2;
    border-color: #fda4af;
}

.calendar-day-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.35rem;
}

.calendar-day-number-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.calendar-day-number {
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    line-height: 1;
}

.calendar-day-datehint {
    font-size: 0.56rem;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.calendar-day-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.calendar-day-worktype {
    font-size: 0.5rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    letter-spacing: 0.02em;
}

.calendar-day-attendance {
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.52rem;
}

.calendar-day-bottom {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.calendar-day-hours {
    color: var(--hcis-green-600);
    font-weight: 700;
    font-size: 0.6rem;
    line-height: 1.2;
    letter-spacing: 0.01em;
}

.calendar-day-status {
    font-size: 0.45rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
    line-height: 1.2;
}

.calendar-day-markers {
    display: flex;
    flex-direction: column;
    gap: 0.14rem;
    margin-top: 0.04rem;
    min-width: 0;
}

.day-marker {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    justify-content: center;
    text-align: center;
    padding: 0.13rem 0.32rem;
    border-radius: 999px;
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-day.is-eligible .calendar-day-hours {
    color: var(--hcis-green-700);
    font-weight: 800;
}

.day-marker.marker-eligible {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.day-marker.marker-submitted {
    background: var(--hcis-yellow-100);
    color: var(--hcis-yellow-700);
}

.day-marker.marker-applied {
    background: var(--hcis-cyan-100);
    color: var(--hcis-cyan-700);
}

.day-marker.marker-expired {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.day-marker.marker-attendance {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}

.day-marker.marker-approved {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.day-marker.marker-pending {
    background: var(--hcis-orange-100);
    color: #c2410c;
}

.day-marker.marker-rejected {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.day-marker.marker-upcoming {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.detail-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.detail-info-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.6rem;
    background: var(--hcis-slate-50);
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-100);
}

.detail-info-item.full {
    grid-column: 1 / -1;
}

.detail-info-item small {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
    font-weight: 700;
}

.detail-info-item span {
    font-size: 0.85rem;
    color: var(--hcis-slate-800);
    font-weight: 700;
}

.calendar-day-status.status-eligible {
    color: var(--hcis-green-600);
}
.calendar-day-status.status-applied {
    color: var(--hcis-cyan-600);
}
.calendar-day-status.status-submitted {
    color: #ca8a04;
}
.calendar-day-status.status-converted {
    color: #ca8a04;
}
.calendar-day-status.status-expired {
    color: var(--hcis-red-500);
}

.detail-panel {
    border: 1px solid var(--k-border);
    border-radius: 16px;
    background: #fff;
    padding: 0 1.1rem 1.1rem;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
}

/* Fade transition for detail panel */
.dp-fade-enter-active,
.dp-fade-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.dp-fade-enter-from,
.dp-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.detail-empty {
    border: 1.5px dashed var(--k-border);
    border-radius: 16px;
    background: var(--hcis-slate-50);
    min-height: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400);
    text-align: center;
    padding: 2rem 1.5rem;
    gap: 0.5rem;
}

.detail-empty-visual {
    width: 72px;
    height: 72px;
    background: var(--hcis-indigo-50);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
}

.detail-empty-visual i {
    font-size: 2rem;
    color: var(--hcis-indigo-300);
}

.detail-empty-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    margin: 0;
}

.detail-empty-hint {
    font-size: 0.84rem;
    color: var(--hcis-slate-400);
    margin: 0;
    max-width: 220px;
    line-height: 1.5;
}

.detail-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-top: 1.1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--k-border);
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 10;
}

.detail-head-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.detail-head h4 {
    margin: 0;
    font-weight: 800;
    font-size: 1rem;
    color: var(--k-title);
    letter-spacing: -0.01em;
}

.detail-date-label {
    color: var(--k-muted);
    font-size: 0.83rem;
}

.detail-close-btn {
    background: var(--hcis-slate-100);
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400);
    font-size: 1rem;
    flex-shrink: 0;
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s;
}

.detail-close-btn:hover {
    background: var(--hcis-red-100);
    color: var(--hcis-red-500);
}

.status-pill {
    border-radius: 999px;
    padding: 0.25rem 0.6rem;
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    height: fit-content;
}

.status-pill-soft {
    font-size: 0.65rem;
}

.status-pill-eligible {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.status-pill-applied {
    background: var(--hcis-cyan-100);
    color: var(--hcis-cyan-700);
}
.status-pill-submitted {
    background: var(--hcis-yellow-100);
    color: var(--hcis-yellow-700);
}
.status-pill-converted,
.status-pill-approved {
    background: var(--hcis-yellow-100);
    color: var(--hcis-yellow-700);
}
.status-pill-expired {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}
.status-pill-pending {
    background: var(--hcis-orange-100);
    color: #c2410c;
}
.status-pill-upcoming {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}
.status-pill-off,
.status-pill-work {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}

.submission-guard-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.75rem 0.9rem;
    border-radius: 0.95rem;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.78rem;
    font-weight: 600;
    text-align: center;
}

.submission-guard-note.is-error {
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
}

.guard-retry-btn {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: underline;
    padding: 0;
}

.detail-card-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.premium-info-card {
    background: #fff;
    border: 1px solid var(--k-border);
    border-radius: 16px;
    padding: 1.15rem;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}

.premium-info-card:hover {
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
    border-color: var(--hcis-slate-300);
}

.premium-info-card small {
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.02em;
    margin-bottom: 0.25rem;
    display: block;
}

.icon-wrap {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.icon-blue {
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}

.icon-orange {
    color: var(--hcis-orange-600);
    background: var(--hcis-orange-100);
}

.rule-box,
.approval-box,
.related-box,
.detail-info-box {
    background: #fff;
    border: 1px solid var(--k-border);
    border-radius: 16px;
    padding: 1.15rem;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
    margin-bottom: 1rem;
}

.rule-box:hover,
.approval-box:hover,
.related-box:hover,
.detail-info-box:hover {
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.05);
    border-color: var(--hcis-slate-300);
}

.rule-box h6,
.approval-box h6,
.related-box h6,
.detail-info-box h6 {
    color: var(--hcis-slate-800);
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    margin-bottom: 1rem !important;
}

.rule-box h6 i {
    color: var(--hcis-sky-500);
    background: var(--hcis-sky-100);
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-right: 0.6rem !important;
}

.detail-info-box h6 i {
    color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-right: 0.6rem !important;
}

.related-box h6 i {
    color: var(--hcis-pink-500);
    background: #fce7f3;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-right: 0.6rem !important;
}

.approval-box h6 i {
    color: var(--hcis-amber-500);
    background: var(--hcis-amber-100);
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-right: 0.6rem !important;
}

.related-item {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.55rem 0.6rem;
    border-radius: 8px;
}

.related-item:hover {
    background: var(--hcis-slate-50);
}

.related-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.related-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.22rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
}

.related-summary {
    color: var(--hcis-slate-500);
    line-height: 1.35;
}

.related-cuti {
    background: #dbeafe;
    color: #1d4ed8;
}

.related-sakit {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.related-swap {
    background: #ede9fe;
    color: var(--hcis-violet-700);
}

.related-conversion {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.related-late {
    background: var(--hcis-orange-100);
    color: #c2410c;
}

.related-generic {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.rule-box li {
    color: var(--hcis-slate-600);
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.5;
}

.approval-step {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: center;
    padding: 0.55rem 0.6rem;
    border-radius: 8px;
    transition: background 0.15s;
}

.approval-step:hover {
    background: var(--hcis-slate-50);
}

.approval-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.approval-meta {
    margin-top: 0.15rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    line-height: 1.35;
}

.approval-dot {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    border: 2px solid var(--hcis-slate-300);
}

.approval-approved {
    border-color: #22c55e;
    background: var(--hcis-green-100);
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
}
.approval-pending {
    border-color: #fb923c;
    background: var(--hcis-orange-100);
}
.approval-upcoming {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-100);
}
.approval-rejected {
    border-color: var(--hcis-red-500);
    background: var(--hcis-red-100);
}

/* Selected log preview */
.selected-log-preview {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: var(--hcis-green-50);
    border: 1px solid #86efac;
    border-radius: 10px;
    padding: 0.55rem 0.75rem;
}

.slp-icon {
    color: var(--hcis-green-600);
    font-size: 1.1rem;
    flex-shrink: 0;
}

.slp-info {
    display: flex;
    flex-direction: column;
}

.slp-info small {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-green-700);
}

.slp-info span {
    font-size: 0.85rem;
    font-weight: 600;
    color: #166534;
}

/* CTA Buttons */
.cta-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    border: none;
    border-radius: 10px;
    padding: 0.65rem 1rem;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
}

.cta-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.cta-btn-primary {
    background: var(--hcis-indigo-500);
    color: #fff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.cta-btn-primary:hover:not(:disabled) {
    background: var(--hcis-indigo-600);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.38);
}

.cta-btn-success {
    background: #22c55e;
    color: #fff;
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.cta-btn-success:hover:not(:disabled) {
    background: var(--hcis-green-600);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(34, 197, 94, 0.38);
}

@media (max-width: 991.98px) {
    .calendar-title {
        font-size: 1.35rem;
    }

    .calendar-grid-header,
    .calendar-grid-body {
        gap: 0.5rem;
    }

    .calendar-day {
        min-height: 112px;
        height: 112px;
        padding: 0.52rem;
    }

    .calendar-day-number {
        font-size: 1rem;
    }

    .calendar-day-worktype {
        font-size: 0.48rem;
    }

    .calendar-day-hours {
        font-size: 0.78rem;
    }

    .calendar-day-status {
        font-size: 0.6rem;
    }

    .day-marker {
        font-size: 0.5rem;
    }
}

@media (max-width: 575.98px) {
    .calendar-head {
        gap: 0.75rem;
        flex-direction: column;
        align-items: flex-start;
    }
    .calendar-legend {
        gap: 0.4rem;
    }
    .cal-chip {
        font-size: 0.65rem;
        padding: 0.2rem 0.5rem;
    }
    .calendar-grid-header,
    .calendar-grid-body {
        gap: 0.2rem;
    }
    .calendar-weekday {
        font-size: 0.65rem;
    }
    .calendar-day {
        min-height: 60px;
        height: auto;
        padding: 0.3rem 0.15rem;
        border-radius: 8px;
        justify-content: flex-start;
        align-items: center;
        gap: 0.15rem;
    }
    .calendar-day-top {
        flex-direction: column;
        align-items: center;
        gap: 0.15rem;
        width: 100%;
    }
    .calendar-day-number {
        font-size: 0.9rem;
    }
    .calendar-day-worktype,
    .calendar-day-status,
    .calendar-day-datehint {
        display: none;
    }
    .calendar-day-hours {
        font-size: 0.55rem;
    }
    .calendar-day-attendance {
        width: 12px;
        height: 12px;
        font-size: 0.45rem;
        margin: 0 auto;
    }
    .calendar-day-meta {
        justify-content: center;
        margin: 0;
    }
    .calendar-day-bottom {
        width: 100%;
        align-items: center;
        gap: 0.15rem;
    }
    .calendar-day-markers {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.15rem;
        margin-top: 0;
    }
    .day-marker {
        width: 6px;
        height: 6px;
        min-width: 6px;
        border-radius: 50%;
        padding: 0;
        font-size: 0;
        line-height: 0;
        color: transparent !important;
        white-space: nowrap;
        overflow: hidden;
    }
    .calendar-skeleton-day {
        min-height: 60px;
        height: 60px;
        padding: 0.3rem 0.15rem;
        border-radius: 8px;
        gap: 0.15rem;
    }
    .calendar-skeleton-dot {
        width: 10px;
        height: 10px;
    }
    .calendar-skeleton-line {
        height: 6px;
    }
    .detail-info-grid {
        grid-template-columns: 1fr;
    }
}

/* --- Sticky Panel (Mobile) --- */
@media (max-width: 1199px) {
    .kl-sticky-panel {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1040;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
        max-height: 75vh;
        overflow-y: auto;
        border-top: 1px solid var(--k-border);
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        background: #fff;
        padding: 0 1.25rem 2rem 1.25rem;
        transform: translateY(0);
        will-change: transform;
    }
    .kl-sticky-panel .detail-head {
        padding-top: 1.7rem;
    }
    .kl-sticky-panel .detail-head::before {
        content: '';
        position: absolute;
        top: 0.6rem;
        left: 50%;
        transform: translateX(-50%);
        width: 40px;
        height: 5px;
        background: var(--hcis-slate-300);
        border-radius: 4px;
    }
    .konversi-calendar-layout {
        padding-bottom: 6rem;
    }
    .dp-fade-enter-from,
    .dp-fade-leave-to {
        opacity: 0;
        transform: translateY(100%);
    }
    .dp-fade-enter-active,
    .dp-fade-leave-active {
        transition:
            transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
            opacity 0.35s ease;
    }
}
</style>
