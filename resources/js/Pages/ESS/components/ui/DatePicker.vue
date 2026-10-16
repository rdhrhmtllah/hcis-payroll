<template>
    <div class="datetimepicker-container">
        <!-- Input Field -->
        <div class="datetimepicker-input-wrapper">
            <i class="datetimepicker-icon" :class="modeIcon"></i>
            <input
                ref="dateInput"
                type="text"
                :value="formattedValue"
                :placeholder="placeholder"
                :disabled="disabled"
                readonly
                class="datetimepicker-input"
                @click="togglePicker"
                @focus="onFocus"
                @blur="onBlur"
            />
            <button
                v-if="showClear && hasValue"
                class="datetimepicker-clear"
                @click="clearSelection"
                type="button"
            >
                <i class="bi bi-x"></i>
            </button>
        </div>

        <!-- Desktop Modal -->
        <div
            v-if="showPicker && !isMobile"
            class="datetimepicker-modal"
            v-click-outside="closePicker"
        >
            <div class="datetimepicker-modal-content">
                <!-- Header -->
                <div class="datetimepicker-header">
                    <div class="datetimepicker-mode-selector">
                        <button
                            v-for="mode in availableModes"
                            :key="mode.value"
                            :class="[
                                'mode-btn',
                                { active: selectedMode === mode.value },
                            ]"
                            @click="selectedMode = mode.value"
                        >
                            {{ mode.label }}
                        </button>
                    </div>
                    <div class="header-actions">
                        <button
                            v-if="hasValue"
                            class="clear-in-picker-btn"
                            @click="clearSelection"
                            type="button"
                        >
                            <i class="bi bi-x-circle"></i>
                            Clear
                        </button>
                        <button
                            class="datetimepicker-close"
                            @click="closePicker"
                        >
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>

                <!-- Date Selection -->
                <div
                    v-if="selectedMode.includes('date')"
                    class="datetimepicker-date-section"
                >
                    <div class="calendar-header">
                        <button
                            class="calendar-nav-btn"
                            @click="previousMonth"
                            type="button"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <div class="calendar-month-year">
                            <select
                                v-model="currentMonth"
                                class="calendar-select"
                            >
                                <option
                                    v-for="(month, index) in months"
                                    :key="index"
                                    :value="index"
                                >
                                    {{ month }}
                                </option>
                            </select>
                            <select
                                v-model="currentYear"
                                class="calendar-select"
                            >
                                <option
                                    v-for="year in yearRange"
                                    :key="year"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>
                        </div>

                        <button
                            class="calendar-nav-btn"
                            @click="nextMonth"
                            type="button"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div class="calendar-weekdays">
                        <div
                            v-for="day in weekdays"
                            :key="day"
                            class="calendar-weekday"
                        >
                            {{ day }}
                        </div>
                    </div>

                    <div class="calendar-days">
                        <div
                            v-for="day in calendarDays"
                            :key="day.date"
                            :class="[
                                'calendar-day',
                                {
                                    'other-month': !day.isCurrentMonth,
                                    today: day.isToday,
                                    selected: isDateSelected(day.date),
                                    'in-range': isDateInRange(day.date),
                                    disabled: day.isDisabled,
                                    'has-event': day.hasEvent,
                                    'start-date': isStartDate(day.date),
                                    'end-date': isEndDate(day.date),
                                },
                            ]"
                            @click="selectDate(day)"
                            @mouseenter="hoverDate = day.date"
                            @mouseleave="hoverDate = null"
                        >
                            {{ day.day }}
                            <div
                                v-if="day.hasEvent"
                                class="event-indicator"
                            ></div>
                        </div>
                    </div>

                    <!-- Date Tooltip -->
                    <div
                        v-if="hoverDate && getEventForDate(hoverDate)"
                        class="date-tooltip"
                        :style="tooltipPosition"
                    >
                        <div class="tooltip-content">
                            <strong>{{
                                formatDate(hoverDate, "dd MMMM yyyy")
                            }}</strong>
                            <div class="event-list">
                                <div
                                    v-for="event in getEventForDate(hoverDate)"
                                    :key="event.id"
                                    class="event-item"
                                    :style="{ color: event.color }"
                                >
                                    <i :class="event.icon"></i>
                                    {{ event.title }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Time Selection -->
                <div
                    v-if="selectedMode.includes('time')"
                    class="datetimepicker-time-section"
                >
                    <div class="time-selection">
                        <!-- Single Time Mode -->
                        <div
                            v-if="selectionMode === 'single'"
                            class="time-picker"
                        >
                            <div class="time-inputs">
                                <div class="time-input-group">
                                    <label>Jam</label>
                                    <select
                                        v-model="selectedHour"
                                        class="time-select"
                                    >
                                        <option
                                            v-for="h in hours"
                                            :key="h"
                                            :value="h"
                                        >
                                            {{ h.toString().padStart(2, "0") }}
                                        </option>
                                    </select>
                                </div>
                                <div class="time-input-group">
                                    <label>Menit</label>
                                    <select
                                        v-model="selectedMinute"
                                        class="time-select"
                                    >
                                        <option
                                            v-for="m in minutes"
                                            :key="m"
                                            :value="m"
                                        >
                                            {{ m.toString().padStart(2, "0") }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Range Time Mode -->
                        <div
                            v-if="selectionMode === 'range'"
                            class="time-range-picker"
                        >
                            <div class="time-range-inputs">
                                <div class="time-range-group">
                                    <h4>Mulai</h4>
                                    <div class="time-inputs">
                                        <div class="time-input-group">
                                            <label>Jam</label>
                                            <select
                                                v-model="startHour"
                                                class="time-select"
                                            >
                                                <option
                                                    v-for="h in hours"
                                                    :key="h"
                                                    :value="h"
                                                >
                                                    {{
                                                        h
                                                            .toString()
                                                            .padStart(2, "0")
                                                    }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="time-input-group">
                                            <label>Menit</label>
                                            <select
                                                v-model="startMinute"
                                                class="time-select"
                                            >
                                                <option
                                                    v-for="m in minutes"
                                                    :key="m"
                                                    :value="m"
                                                >
                                                    {{
                                                        m
                                                            .toString()
                                                            .padStart(2, "0")
                                                    }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="time-range-group">
                                    <h4>Selesai</h4>
                                    <div class="time-inputs">
                                        <div class="time-input-group">
                                            <label>Jam</label>
                                            <select
                                                v-model="endHour"
                                                class="time-select"
                                            >
                                                <option
                                                    v-for="h in hours"
                                                    :key="h"
                                                    :value="h"
                                                >
                                                    {{
                                                        h
                                                            .toString()
                                                            .padStart(2, "0")
                                                    }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="time-input-group">
                                            <label>Menit</label>
                                            <select
                                                v-model="endMinute"
                                                class="time-select"
                                            >
                                                <option
                                                    v-for="m in minutes"
                                                    :key="m"
                                                    :value="m"
                                                >
                                                    {{
                                                        m
                                                            .toString()
                                                            .padStart(2, "0")
                                                    }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="datetimepicker-footer">
                    <div class="selection-display">
                        <div v-if="selectionMode === 'single' && selectedDate">
                            <strong>Dipilih:</strong>
                            {{ formatDate(selectedDate, displayFormat) }}
                        </div>
                        <div
                            v-else-if="
                                selectionMode === 'range' &&
                                (startDate || endDate)
                            "
                        >
                            <strong>Rentang:</strong>
                            {{ getRangeDisplayText() }}
                        </div>
                        <div
                            v-else-if="
                                selectionMode === 'multiple' &&
                                selectedDates.length > 0
                            "
                        >
                            <strong
                                >{{ selectedDates.length }} tanggal
                                dipilih</strong
                            >
                        </div>
                        <div v-else>
                            <strong>Belum ada yang dipilih</strong>
                        </div>
                    </div>
                    <div class="footer-actions">
                        <button
                            v-if="showTodayButton"
                            class="today-btn"
                            @click="selectToday"
                            type="button"
                        >
                            Hari Ini
                        </button>
                        <button
                            class="apply-btn"
                            @click="applySelection"
                            :disabled="!hasValue"
                            type="button"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Bottom Sheet -->
        <div
            v-if="showPicker && isMobile"
            class="datetimepicker-bottom-sheet"
            @click="closePicker"
        >
            <div class="bottom-sheet-content" @click.stop>
                <div class="bottom-sheet-header">
                    <h3>Pilih Tanggal & Waktu</h3>
                    <div class="mobile-header-actions">
                        <button
                            v-if="hasValue"
                            class="clear-in-picker-btn"
                            @click="clearSelection"
                            type="button"
                        >
                            <i class="bi bi-x-circle"></i>
                        </button>
                        <button class="bottom-sheet-close" @click="closePicker">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>

                <div class="bottom-sheet-body">
                    <!-- Mode Selector -->
                    <div class="mobile-mode-selector">
                        <button
                            v-for="mode in availableModes"
                            :key="mode.value"
                            :class="[
                                'mode-btn',
                                { active: selectedMode === mode.value },
                            ]"
                            @click="selectedMode = mode.value"
                        >
                            {{ mode.label }}
                        </button>
                    </div>

                    <!-- Date Selection -->
                    <div
                        v-if="selectedMode.includes('date')"
                        class="mobile-date-section"
                    >
                        <div class="mobile-calendar-header">
                            <button
                                class="calendar-nav-btn"
                                @click="previousMonth"
                                type="button"
                            >
                                <i class="bi bi-chevron-left"></i>
                            </button>

                            <div class="calendar-month-year">
                                <span class="month-year-display">
                                    {{ months[currentMonth] }} {{ currentYear }}
                                </span>
                            </div>

                            <button
                                class="calendar-nav-btn"
                                @click="nextMonth"
                                type="button"
                            >
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>

                        <div class="mobile-calendar-days">
                            <div
                                v-for="day in calendarDays"
                                :key="day.date"
                                :class="[
                                    'calendar-day',
                                    {
                                        'other-month': !day.isCurrentMonth,
                                        today: day.isToday,
                                        selected: isDateSelected(day.date),
                                        'in-range': isDateInRange(day.date),
                                        disabled: day.isDisabled,
                                        'has-event': day.hasEvent,
                                        'start-date': isStartDate(day.date),
                                        'end-date': isEndDate(day.date),
                                    },
                                ]"
                                @click="selectDate(day)"
                                @touchstart="touchStart(day)"
                                @touchend="touchEnd"
                            >
                                {{ day.day }}
                                <div
                                    v-if="day.hasEvent"
                                    class="event-indicator"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Time Selection -->
                    <div
                        v-if="selectedMode.includes('time')"
                        class="mobile-time-section"
                    >
                        <div class="mobile-time-picker">
                            <div class="time-wheel-container">
                                <div class="time-wheel">
                                    <div class="wheel-label">Jam</div>
                                    <div class="wheel-scroll">
                                        <div
                                            v-for="h in hours"
                                            :key="h"
                                            :class="[
                                                'wheel-item',
                                                { active: selectedHour === h },
                                            ]"
                                            @click="selectedHour = h"
                                        >
                                            {{ h.toString().padStart(2, "0") }}
                                        </div>
                                    </div>
                                </div>
                                <div class="time-wheel">
                                    <div class="wheel-label">Menit</div>
                                    <div class="wheel-scroll">
                                        <div
                                            v-for="m in minutes"
                                            :key="m"
                                            :class="[
                                                'wheel-item',
                                                {
                                                    active:
                                                        selectedMinute === m,
                                                },
                                            ]"
                                            @click="selectedMinute = m"
                                        >
                                            {{ m.toString().padStart(2, "0") }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bottom-sheet-footer">
                    <div class="mobile-selection-display">
                        <small>{{ getRangeDisplayText() }}</small>
                    </div>
                    <div class="mobile-footer-actions">
                        <button
                            v-if="showTodayButton"
                            class="today-btn"
                            @click="selectToday"
                            type="button"
                        >
                            Hari Ini
                        </button>
                        <button
                            class="apply-btn"
                            @click="applySelection"
                            :disabled="!hasValue"
                            type="button"
                        >
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {
    format,
    parse,
    isValid,
    startOfMonth,
    endOfMonth,
    startOfWeek,
    endOfWeek,
    eachDayOfInterval,
    isSameDay,
    isToday,
    isBefore,
    isAfter,
    addMonths,
    subMonths,
    addDays,
    setHours,
    setMinutes,
    isWithinInterval,
    isSameMonth,
    isSameYear,
} from "date-fns";
import { id } from "date-fns/locale";

export default {
    name: "DateTimePicker",
    directives: {
        "click-outside": {
            beforeMount(el, binding) {
                el.clickOutsideEvent = function (event) {
                    if (
                        el === event.target ||
                        el.contains(event.target) ||
                        (event.composedPath && event.composedPath().includes(el))
                    ) {
                        return;
                    }
                    binding.value(event);
                };
                setTimeout(() => {
                    document.addEventListener("click", el.clickOutsideEvent);
                }, 50);
            },
            unmounted(el) {
                if (el.clickOutsideEvent) {
                    document.removeEventListener("click", el.clickOutsideEvent);
                }
            },
        },
    },
    props: {
        modelValue: {
            type: [Date, String, Number, Array, Object],
            default: null,
        },
        placeholder: {
            type: String,
            default: "Pilih tanggal dan waktu",
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        format: {
            type: String,
            default: "dd/MM/yyyy HH:mm",
        },
        displayFormat: {
            type: String,
            default: "dd/MM/yyyy HH:mm",
        },
        mode: {
            type: String,
            default: "datetime", // 'date', 'time', 'datetime'
            validator: (value) => ["date", "time", "datetime"].includes(value),
        },
        selectionMode: {
            type: String,
            default: "single", // 'single', 'range', 'multiple'
            validator: (value) =>
                ["single", "range", "multiple"].includes(value),
        },
        minDate: {
            type: Date,
            default: null,
        },
        maxDate: {
            type: Date,
            default: null,
        },
        disabledDates: {
            type: Array,
            default: () => [],
        },
        events: {
            type: Array,
            default: () => [],
        },
        showClear: {
            type: Boolean,
            default: true,
        },
        showTodayButton: {
            type: Boolean,
            default: true,
        },
        timeStep: {
            type: Number,
            default: 30,
        },
    },
    emits: ["update:modelValue", "change", "focus", "blur"],
    data() {
        const today = new Date();
        return {
            showPicker: false,
            selectedMode: this.mode,
            currentMonth: today.getMonth(),
            currentYear: today.getFullYear(),

            // Date selection
            selectedDate: null,
            startDate: null,
            endDate: null,
            selectedDates: [],

            // Time selection
            selectedHour: today.getHours(),
            selectedMinute:
                Math.floor(today.getMinutes() / this.timeStep) * this.timeStep,
            startHour: today.getHours(),
            startMinute:
                Math.floor(today.getMinutes() / this.timeStep) * this.timeStep,
            endHour: today.getHours() + 1,
            endMinute:
                Math.floor(today.getMinutes() / this.timeStep) * this.timeStep,

            // UI state
            hoverDate: null,
            tooltipPosition: { top: "0", left: "0" },
            touchStartTime: null,

            // Constants
            months: [
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ],
            weekdays: ["M", "S", "S", "R", "K", "J", "S"],
        };
    },
    computed: {
        // Mode availability
        availableModes() {
            const modes = [];
            if (this.mode.includes("date"))
                modes.push({ value: "date", label: "Tanggal" });
            if (this.mode.includes("time"))
                modes.push({ value: "time", label: "Waktu" });
            if (this.mode === "datetime")
                modes.push({ value: "datetime", label: "Tanggal & Waktu" });
            return modes;
        },

        // Icon based on mode
        modeIcon() {
            switch (this.mode) {
                case "date":
                    return "bi bi-calendar";
                case "time":
                    return "bi bi-clock";
                case "datetime":
                    return "bi bi-calendar-event";
                default:
                    return "bi bi-calendar";
            }
        },

        // Check if mobile device - FIXED DETECTION
        isMobile() {
            // Improved mobile detection
            return (
                window.innerWidth <= 768 ||
                /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                    navigator.userAgent
                )
            );
        },

        // Formatted display value - IMPROVED RANGE DISPLAY
        formattedValue() {
            if (!this.hasValue) return "";

            if (this.selectionMode === "single") {
                return this.formatDate(this.selectedDate, this.format);
            } else if (this.selectionMode === "range") {
                return this.getRangeDisplayText();
            } else if (this.selectionMode === "multiple") {
                if (this.selectedDates.length > 0) {
                    if (this.selectedDates.length === 1) {
                        return this.formatDate(
                            this.selectedDates[0],
                            this.format
                        );
                    } else {
                        return `${this.selectedDates.length} tanggal dipilih`;
                    }
                }
            }
            return "";
        },

        // Check if has value
        hasValue() {
            if (this.selectionMode === "single") return !!this.selectedDate;
            if (this.selectionMode === "range")
                return !!(this.startDate || this.endDate);
            if (this.selectionMode === "multiple")
                return this.selectedDates.length > 0;
            return false;
        },

        // Calendar days with events
        calendarDays() {
            const monthStart = startOfMonth(
                new Date(this.currentYear, this.currentMonth)
            );
            const monthEnd = endOfMonth(monthStart);
            const calendarStart = startOfWeek(monthStart, { weekStartsOn: 1 });
            const calendarEnd = endOfWeek(monthEnd, { weekStartsOn: 1 });

            const days = eachDayOfInterval({
                start: calendarStart,
                end: calendarEnd,
            });

            return days.map((date) => ({
                date: date,
                day: date.getDate(),
                isCurrentMonth: date.getMonth() === this.currentMonth,
                isToday: isToday(date),
                isDisabled: this.isDateDisabled(date),
                hasEvent: this.hasEventOnDate(date),
            }));
        },

        // Year range for dropdown
        yearRange() {
            const currentYear = new Date().getFullYear();
            const years = [];
            for (let i = currentYear - 10; i <= currentYear + 10; i++) {
                years.push(i);
            }
            return years;
        },

        // Hours for time selection
        hours() {
            return Array.from({ length: 24 }, (_, i) => i);
        },

        // Minutes for time selection
        minutes() {
            const steps = 60 / this.timeStep;
            return Array.from({ length: steps }, (_, i) => i * this.timeStep);
        },
    },
    watch: {
        modelValue: {
            handler(newValue) {
                this.parseExternalValue(newValue);
            },
            immediate: true,
        },
        currentMonth() {
            this.ensureValidView();
        },
        currentYear() {
            this.ensureValidView();
        },
        mode(newMode) {
            this.selectedMode = newMode;
        },
    },
    methods: {
        /** Dipisah jadi metode supaya punya rujukan tetap untuk removeEventListener. */
        onDocumentMouseMove(e) {
            if (this.hoverDate) {
                this.tooltipPosition = {
                    top: `${e.clientY + 10}px`,
                    left: `${e.clientX + 10}px`,
                };
            }
        },

        // Parse external value based on selection mode
        parseExternalValue(value) {
            if (!value) {
                this.resetSelection();
                return;
            }

            if (this.selectionMode === "single") {
                this.selectedDate = this.parseDate(value);
                if (this.selectedDate) {
                    this.currentMonth = this.selectedDate.getMonth();
                    this.currentYear = this.selectedDate.getFullYear();
                    this.selectedHour = this.selectedDate.getHours();
                    this.selectedMinute = this.selectedDate.getMinutes();
                }
            } else if (this.selectionMode === "range") {
                if (value.start && value.end) {
                    this.startDate = this.parseDate(value.start);
                    this.endDate = this.parseDate(value.end);
                    if (this.startDate) {
                        this.currentMonth = this.startDate.getMonth();
                        this.currentYear = this.startDate.getFullYear();
                    }
                } else if (value.start && !value.end) {
                    this.startDate = this.parseDate(value.start);
                    this.endDate = null;
                }
            } else if (this.selectionMode === "multiple") {
                if (Array.isArray(value)) {
                    this.selectedDates = value
                        .map((d) => this.parseDate(d))
                        .filter((d) => d);
                    if (this.selectedDates.length > 0) {
                        this.currentMonth = this.selectedDates[0].getMonth();
                        this.currentYear = this.selectedDates[0].getFullYear();
                    }
                }
            }
        },

        // Reset selection
        resetSelection() {
            this.selectedDate = null;
            this.startDate = null;
            this.endDate = null;
            this.selectedDates = [];

            const today = new Date();
            this.selectedHour = today.getHours();
            this.selectedMinute =
                Math.floor(today.getMinutes() / this.timeStep) * this.timeStep;
            this.startHour = today.getHours();
            this.startMinute =
                Math.floor(today.getMinutes() / this.timeStep) * this.timeStep;
            this.endHour = today.getHours() + 1;
            this.endMinute =
                Math.floor(today.getMinutes() / this.timeStep) * this.timeStep;
        },

        // Parse date from various formats
        parseDate(date) {
            if (!date) return null;
            if (date instanceof Date) return date;
            if (typeof date === "string" || typeof date === "number") {
                const parsed = new Date(date);
                return isValid(parsed) ? parsed : null;
            }
            return null;
        },

        // Format date with locale
        formatDate(date, formatStr) {
            if (!date) return "";
            return format(date, formatStr, { locale: id });
        },

        // IMPROVED: Get range display text with smart formatting
        getRangeDisplayText() {
            if (this.selectionMode !== "range") return "";

            if (this.startDate && this.endDate) {
                // Same date - show only once
                if (isSameDay(this.startDate, this.endDate)) {
                    return this.formatDate(this.startDate, this.format);
                }

                // Same year and month
                if (
                    isSameYear(this.startDate, this.endDate) &&
                    isSameMonth(this.startDate, this.endDate)
                ) {
                    return `${this.formatDate(
                        this.startDate,
                        "dd"
                    )} - ${this.formatDate(this.endDate, "dd MMM yyyy")}`;
                }

                // Same year, different month
                if (isSameYear(this.startDate, this.endDate)) {
                    return `${this.formatDate(
                        this.startDate,
                        "dd MMM"
                    )} - ${this.formatDate(this.endDate, "dd MMM yyyy")}`;
                }

                // Different year
                return `${this.formatDate(
                    this.startDate,
                    "dd MMM yyyy"
                )} - ${this.formatDate(this.endDate, "dd MMM yyyy")}`;
            } else if (this.startDate) {
                return `${this.formatDate(
                    this.startDate,
                    this.format
                )} - Pilih tanggal akhir`;
            } else if (this.endDate) {
                return `Pilih tanggal awal - ${this.formatDate(
                    this.endDate,
                    this.format
                )}`;
            }

            return "";
        },

        // Check if date is disabled
        isDateDisabled(date) {
            // Check min/max dates
            if (this.minDate && isBefore(date, this.minDate)) return true;
            if (this.maxDate && isAfter(date, this.maxDate)) return true;

            // Check disabled dates
            if (
                this.disabledDates.some((disabledDate) =>
                    isSameDay(date, this.parseDate(disabledDate))
                )
            )
                return true;

            return false;
        },

        // Check if date has event
        hasEventOnDate(date) {
            return this.events.some((event) =>
                isSameDay(date, this.parseDate(event.date))
            );
        },

        // Get events for a specific date
        getEventForDate(date) {
            return this.events.filter((event) =>
                isSameDay(date, this.parseDate(event.date))
            );
        },

        // Check if date is selected (based on selection mode)
        isDateSelected(date) {
            if (this.selectionMode === "single") {
                return this.selectedDate && isSameDay(date, this.selectedDate);
            } else if (this.selectionMode === "range") {
                return (
                    (this.startDate && isSameDay(date, this.startDate)) ||
                    (this.endDate && isSameDay(date, this.endDate))
                );
            } else if (this.selectionMode === "multiple") {
                return this.selectedDates.some((d) => isSameDay(date, d));
            }
            return false;
        },

        // Check if date is in range (for range selection)
        isDateInRange(date) {
            if (
                this.selectionMode !== "range" ||
                !this.startDate ||
                !this.endDate
            )
                return false;
            return isWithinInterval(date, {
                start: this.startDate,
                end: this.endDate,
            });
        },

        // Check if date is start date
        isStartDate(date) {
            return (
                this.selectionMode === "range" &&
                this.startDate &&
                isSameDay(date, this.startDate)
            );
        },

        // Check if date is end date
        isEndDate(date) {
            return (
                this.selectionMode === "range" &&
                this.endDate &&
                isSameDay(date, this.endDate)
            );
        },

        // Ensure current view is within min/max constraints
        ensureValidView() {
            const currentView = new Date(this.currentYear, this.currentMonth);

            if (
                this.minDate &&
                isBefore(currentView, startOfMonth(this.minDate))
            ) {
                this.currentMonth = this.minDate.getMonth();
                this.currentYear = this.minDate.getFullYear();
            }

            if (
                this.maxDate &&
                isAfter(currentView, startOfMonth(this.maxDate))
            ) {
                this.currentMonth = this.maxDate.getMonth();
                this.currentYear = this.maxDate.getFullYear();
            }
        },

        // FIXED: Toggle picker visibility for both desktop and mobile
        togglePicker() {
            if (this.disabled) return;

            console.log(
                "Toggle picker - Mobile:",
                this.isMobile,
                "Show:",
                this.showPicker
            );

            this.showPicker = !this.showPicker;
            if (this.showPicker) {
                this.$nextTick(() => {
                    this.ensureValidView();
                    // Force re-render untuk memastikan modal muncul
                    this.$forceUpdate();
                });
            }
        },

        // Close picker
        closePicker() {
            this.showPicker = false;
            this.hoverDate = null;
        },

        // IMPROVED: Select date with better range handling
        selectDate(day) {
            if (day.isDisabled) return;

            const date = day.date;

            if (this.selectionMode === "single") {
                this.selectedDate = date;
                if (this.selectedMode === "date") {
                    this.applySelection();
                }
            } else if (this.selectionMode === "range") {
                // Jika belum ada start date, set sebagai start
                if (!this.startDate) {
                    this.startDate = date;
                    this.endDate = null;
                }
                // Jika sudah ada start date tapi belum ada end date
                else if (this.startDate && !this.endDate) {
                    // Jika memilih tanggal yang sama dengan start date, reset
                    if (isSameDay(date, this.startDate)) {
                        this.startDate = null;
                    }
                    // Jika memilih tanggal setelah start date, set sebagai end date
                    else if (isAfter(date, this.startDate)) {
                        this.endDate = date;
                        if (this.selectedMode === "date") {
                            this.applySelection();
                        }
                    }
                    // Jika memilih tanggal sebelum start date, tukar posisi
                    else {
                        this.endDate = this.startDate;
                        this.startDate = date;
                        if (this.selectedMode === "date") {
                            this.applySelection();
                        }
                    }
                }
                // Jika sudah lengkap, reset dan mulai baru
                else if (this.startDate && this.endDate) {
                    this.startDate = date;
                    this.endDate = null;
                }
            } else if (this.selectionMode === "multiple") {
                const index = this.selectedDates.findIndex((d) =>
                    isSameDay(d, date)
                );
                if (index >= 0) {
                    // Remove if already selected
                    this.selectedDates.splice(index, 1);
                } else {
                    // Add to selection
                    this.selectedDates.push(date);
                }
            }
        },

        // Select today
        selectToday() {
            const today = new Date();
            if (!this.isDateDisabled(today)) {
                if (this.selectionMode === "single") {
                    this.selectedDate = today;
                } else if (this.selectionMode === "range") {
                    if (!this.startDate) {
                        this.startDate = today;
                    } else if (!this.endDate) {
                        this.endDate = today;
                    } else {
                        this.startDate = today;
                        this.endDate = null;
                    }
                } else if (this.selectionMode === "multiple") {
                    const index = this.selectedDates.findIndex((d) =>
                        isSameDay(d, today)
                    );
                    if (index === -1) {
                        this.selectedDates.push(today);
                    }
                }

                // Also update time to current time
                this.selectedHour = today.getHours();
                this.selectedMinute =
                    Math.floor(today.getMinutes() / this.timeStep) *
                    this.timeStep;
                this.startHour = today.getHours();
                this.startMinute =
                    Math.floor(today.getMinutes() / this.timeStep) *
                    this.timeStep;
                this.endHour = today.getHours() + 1;
                this.endMinute =
                    Math.floor(today.getMinutes() / this.timeStep) *
                    this.timeStep;
            }
        },

        // Apply selection and emit events
        applySelection() {
            let value = null;

            if (this.selectionMode === "single") {
                if (this.selectedDate) {
                    value = this.selectedMode.includes("time")
                        ? setMinutes(
                              setHours(this.selectedDate, this.selectedHour),
                              this.selectedMinute
                          )
                        : this.selectedDate;
                }
            } else if (this.selectionMode === "range") {
                if (this.startDate) {
                    const start = this.selectedMode.includes("time")
                        ? setMinutes(
                              setHours(this.startDate, this.startHour),
                              this.startMinute
                          )
                        : this.startDate;

                    const end = this.endDate
                        ? this.selectedMode.includes("time")
                            ? setMinutes(
                                  setHours(this.endDate, this.endHour),
                                  this.endMinute
                              )
                            : this.endDate
                        : null;

                    value = { start, end };
                }
            } else if (this.selectionMode === "multiple") {
                value = this.selectedDates;
            }

            this.$emit("update:modelValue", value);
            this.$emit("change", value);
            this.closePicker();
        },

        // Clear selection
        clearSelection() {
            this.resetSelection();
            this.$emit("update:modelValue", null);
            this.$emit("change", null);
            this.closePicker();
        },

        // Navigation
        previousMonth() {
            const newDate = subMonths(
                new Date(this.currentYear, this.currentMonth),
                1
            );
            this.currentMonth = newDate.getMonth();
            this.currentYear = newDate.getFullYear();
        },

        nextMonth() {
            const newDate = addMonths(
                new Date(this.currentYear, this.currentMonth),
                1
            );
            this.currentMonth = newDate.getMonth();
            this.currentYear = newDate.getFullYear();
        },

        // Touch events for mobile
        touchStart(day) {
            this.touchStartTime = new Date().getTime();
            this.hoverDate = day.date;
        },

        touchEnd() {
            const touchEndTime = new Date().getTime();
            // If touch was longer than 500ms, consider it a long press for tooltip
            if (touchEndTime - this.touchStartTime > 500) {
                // Show tooltip on mobile for long press
            }
            this.touchStartTime = null;
        },

        // Event handlers
        onFocus(event) {
            this.$emit("focus", event);
        },

        onBlur(event) {
            this.$emit("blur", event);
        },
    },
    mounted() {
        // Parse initial value
        this.parseExternalValue(this.modelValue);

        /*
         * Handler-nya WAJIB berupa rujukan bernama. Sebelumnya berupa arrow
         * inline, sehingga tidak ada nilai yang bisa diberikan ke
         * removeEventListener -- listener-nya tak mungkin dilepas, bukan
         * sekadar lupa dilepas. Komponen ini dipakai di 28 halaman dan
         * mousemove menyala terus-menerus, jadi tiap instance yang pernah
         * di-mount meninggalkan satu handler permanen di document.
         */
        if (!this.isMobile) {
            document.addEventListener("mousemove", this.onDocumentMouseMove);
        }

        // Debug info
        console.log("DateTimePicker mounted - Mobile:", this.isMobile);
    },
    beforeUnmount() {
        document.removeEventListener("mousemove", this.onDocumentMouseMove);
    },
};
</script>

<style scoped>
/* Base Styles */
.datetimepicker-container {
    position: relative;
    margin-top: -10rem;
    width: 100%;
}

.datetimepicker-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.datetimepicker-icon {
    position: absolute;
    left: 1rem;
    color: var(--text-muted);
    z-index: 2;
    pointer-events: none;
}

.datetimepicker-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.9rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.datetimepicker-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.datetimepicker-input:disabled {
    background: var(--surface-soft);
    color: var(--text-muted);
    cursor: not-allowed;
}

.datetimepicker-clear {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.datetimepicker-clear:hover {
    background: var(--surface-soft);
    color: var(--danger);
}

/* Desktop Modal - FIXED Z-INDEX */
.datetimepicker-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: fadeIn 0.2s ease;
}

.datetimepicker-modal-content {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow-xl);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: auto;
    animation: scaleIn 0.2s ease;
}

.datetimepicker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.datetimepicker-mode-selector {
    display: flex;
    gap: 0.5rem;
}

.mode-btn {
    padding: 0.5rem 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.mode-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.clear-in-picker-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
}

.clear-in-picker-btn:hover {
    background: var(--danger-light);
    color: var(--danger);
    border-color: var(--danger);
}

.datetimepicker-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.datetimepicker-close:hover {
    background: var(--surface-soft);
    color: var(--text);
}

/* Date Section */
.datetimepicker-date-section {
    padding: 1.5rem;
    position: relative;
}

.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.calendar-nav-btn {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 6px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--text);
}

.calendar-nav-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.calendar-month-year {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.calendar-select {
    padding: 0.25rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-select:focus {
    outline: none;
    border-color: var(--primary);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.calendar-weekday {
    text-align: center;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.75rem;
    padding: 0.5rem 0;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
    font-weight: 500;
    position: relative;
}

.calendar-day:not(.other-month):not(.disabled):hover {
    background: var(--primary-light);
    color: var(--primary);
}

.calendar-day.today {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
}

.calendar-day.selected {
    background: var(--primary);
    color: white;
    font-weight: 600;
}

.calendar-day.in-range {
    background: var(--primary-light);
    color: var(--primary);
}

.calendar-day.start-date {
    background: var(--primary);
    color: white;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.calendar-day.end-date {
    background: var(--primary);
    color: white;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.calendar-day.other-month {
    color: var(--text-muted);
    opacity: 0.5;
}

.calendar-day.disabled {
    color: var(--text-muted);
    opacity: 0.3;
    cursor: not-allowed;
}

.calendar-day.disabled:hover {
    background: transparent;
    color: var(--text-muted);
}

.calendar-day.has-event::after {
    content: "";
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--accent);
}

.event-indicator {
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--accent);
}

/* Date Tooltip */
.date-tooltip {
    position: fixed;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: var(--shadow-lg);
    padding: 1rem;
    z-index: 10001;
    max-width: 250px;
    animation: fadeIn 0.2s ease;
}

.tooltip-content {
    font-size: 0.8rem;
}

.event-list {
    margin-top: 0.5rem;
}

.event-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0;
}

/* Time Section */
.datetimepicker-time-section {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

.time-selection {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.time-inputs {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.time-input-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.time-input-group label {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}

.time-select {
    padding: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 80px;
}

.time-select:focus {
    outline: none;
    border-color: var(--primary);
}

.time-range-picker {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.time-range-inputs {
    display: flex;
    gap: 2rem;
    justify-content: center;
}

.time-range-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.time-range-group h4 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text);
    text-align: center;
}

/* Footer */
.datetimepicker-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

.selection-display {
    font-size: 0.9rem;
    color: var(--text);
}

.footer-actions {
    display: flex;
    gap: 0.5rem;
}

.today-btn,
.apply-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.today-btn {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
}

.today-btn:hover {
    background: var(--surface-soft);
}

.apply-btn {
    background: var(--primary);
    border: 1px solid var(--primary);
    color: white;
}

.apply-btn:hover:not(:disabled) {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
}

.apply-btn:disabled {
    background: var(--text-muted);
    border-color: var(--text-muted);
    cursor: not-allowed;
    opacity: 0.6;
}

/* Mobile Bottom Sheet */
.datetimepicker-bottom-sheet {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: flex-end;
    animation: fadeIn 0.2s ease;
}

.bottom-sheet-content {
    background: var(--surface);
    border-radius: 16px 16px 0 0;
    width: 100%;
    max-height: 80vh;
    overflow: auto;
    animation: slideUp 0.3s ease;
}

.bottom-sheet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.bottom-sheet-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text);
}

.mobile-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bottom-sheet-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.bottom-sheet-close:hover {
    background: var(--surface-soft);
    color: var(--text);
}

.bottom-sheet-body {
    padding: 1rem;
}

.mobile-mode-selector {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.mobile-date-section {
    margin-bottom: 1rem;
}

.mobile-calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.month-year-display {
    font-weight: 600;
    color: var(--text);
}

.mobile-calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
}

.mobile-time-section {
    margin-top: 1rem;
}

.mobile-time-picker {
    padding: 1rem 0;
}

.time-wheel-container {
    display: flex;
    justify-content: center;
    gap: 2rem;
}

.time-wheel {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.wheel-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.wheel-scroll {
    height: 150px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem;
    background: var(--surface-soft);
}

.wheel-item {
    padding: 0.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: 4px;
    font-size: 0.9rem;
}

.wheel-item.active {
    background: var(--primary);
    color: white;
}

.wheel-item:not(.active):hover {
    background: var(--primary-light);
    color: var(--primary);
}

.bottom-sheet-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    background: var(--surface-soft);
}

.mobile-selection-display {
    flex: 1;
}

.mobile-selection-display small {
    color: var(--text-muted);
}

.mobile-footer-actions {
    display: flex;
    gap: 0.5rem;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .datetimepicker-modal-content {
        width: 95%;
        margin: 1rem;
    }

    .time-range-inputs {
        flex-direction: column;
        gap: 1rem;
    }

    .datetimepicker-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .footer-actions {
        justify-content: space-between;
    }

    .bottom-sheet-footer {
        flex-direction: column;
        gap: 1rem;
    }

    .mobile-footer-actions {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 480px) {
    .calendar-days,
    .mobile-calendar-days {
        gap: 0.125rem;
    }

    .calendar-day {
        font-size: 0.75rem;
    }

    .time-wheel-container {
        gap: 1rem;
    }

    .wheel-item {
        padding: 0.5rem;
        font-size: 0.8rem;
    }

    .datetimepicker-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .header-actions {
        justify-content: space-between;
    }
}
</style>
