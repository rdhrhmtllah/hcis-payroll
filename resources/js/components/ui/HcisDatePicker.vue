<template>
    <div
        ref="containerRef"
        class="hcis-date-picker"
        :class="[
            `hcis-date-picker--size-${size}`,
            `hcis-date-picker--type-${effectiveType}`,
            {
                'hcis-date-picker--active': isOpen || isFocused,
                'hcis-date-picker--disabled': disabled,
                'hcis-date-picker--loading': loading,
                'hcis-date-picker--has-value': hasValue,
                'hcis-date-picker--is-range': isRangeMode || isDateTimeRangeMode,
            }
        ]"
    >
        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 1: SINGLE DATE TRIGGER (Direct Typing & Calendar Popover)
             ═══════════════════════════════════════════════════════════════════ -->
        <div
            v-if="effectiveType === 'date'"
            ref="triggerRef"
            class="hcis-date-picker__trigger"
            :class="{
                'hcis-date-picker__trigger--focused': isFocused || isOpen,
                'hcis-date-picker__trigger--disabled': disabled,
                'hcis-date-picker__trigger--open': isOpen,
            }"
            @click.stop="handleTriggerClick"
        >
            <span class="hcis-date-picker__icon">
                <HcisIcon v-if="loading" name="arrow-repeat" size="xs" class="hcis-spin-icon" />
                <HcisIcon v-else-if="disabled" name="lock-fill" size="xs" />
                <HcisIcon v-else name="calendar3" size="xs" />
            </span>

            <!-- Format baca (mis. '24 Desember 2024'): hanya tampilan, nilai tetap
                 YYYY-MM-DD. Tidak bisa diketik karena bentuknya tak bisa diurai
                 balik dengan andal — tanggal dipilih dari kalender. -->
            <input
                v-if="formatBaca"
                ref="inputRef"
                :value="formatTanggalPanjang(inputValue)"
                type="text"
                class="hcis-date-picker__input"
                :placeholder="placeholder || 'Pilih tanggal'"
                :disabled="disabled"
                readonly
                aria-label="Isian Tanggal"
                @focus="handleInputFocus"
                @blur="handleInputBlur"
                @click.stop="handleInputClick"
                @keydown="handleKeyDown"
            />
            <input
                v-else
                ref="inputRef"
                v-model="inputValue"
                type="text"
                class="hcis-date-picker__input"
                :placeholder="placeholder || 'YYYY-MM-DD'"
                :disabled="disabled"
                :readonly="readonly"
                maxlength="10"
                aria-label="Isian Tanggal"
                @focus="handleInputFocus"
                @blur="handleInputBlur"
                @click.stop="handleInputClick"
                @input="handleTextInput"
                @keydown="handleKeyDown"
            />

            <button
                v-if="clearable && (modelValue || inputValue) && !disabled && !loading"
                type="button"
                class="hcis-date-picker__clear"
                title="Hapus tanggal"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span v-else class="hcis-date-picker__arrow" :class="{ 'is-open': isOpen }" @click.stop="togglePopover">
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 2: DATE RANGE / DATETIME RANGE TRIGGER (Dual Capsule Pills UX)
             ═══════════════════════════════════════════════════════════════════ -->
        <div
            v-else-if="isRangeMode || isDateTimeRangeMode"
            ref="triggerRef"
            class="hcis-date-picker__trigger hcis-date-picker__trigger--range"
            :class="{
                'hcis-date-picker__trigger--focused': isFocused || isOpen,
                'hcis-date-picker__trigger--disabled': disabled,
                'hcis-date-picker__trigger--loading': loading,
                'hcis-date-picker__trigger--open': isOpen,
            }"
            @click.stop="handleTriggerClick"
        >
            <span class="hcis-date-picker__icon">
                <HcisIcon v-if="loading" name="arrow-repeat" size="xs" class="hcis-spin-icon" />
                <HcisIcon v-else-if="disabled" name="lock-fill" size="xs" />
                <HcisIcon v-else name="calendar-range-fill" size="xs" />
            </span>

            <div class="hcis-date-picker__range-inputs">
                <!-- Start Date Capsule -->
                <div class="hcis-trigger-pill" :class="{ 'is-active': isOpen && (!rangeStartDate || isSelectingRange) }">
                    <span class="hcis-pill-text" :class="{ 'is-placeholder': !rangeStartDate }">
                        {{ formatReadableDate(rangeStartDate) || startPlaceholder }}
                    </span>
                </div>

                <span class="hcis-range-arrow">
                    <HcisIcon name="arrow-right" size="2xs" />
                </span>

                <!-- End Date Capsule -->
                <div class="hcis-trigger-pill" :class="{ 'is-active': isOpen && rangeStartDate && !isSelectingRange }">
                    <span class="hcis-pill-text" :class="{ 'is-placeholder': !rangeEndDate }">
                        {{ formatReadableDate(rangeEndDate) || (rangeStartDate ? rangeStartDate : endPlaceholder) }}
                    </span>
                </div>

                <span v-if="rangeDaysCount > 0 && !loading" class="hcis-trigger-badge">
                    {{ rangeDaysCount }} hari
                </span>
            </div>

            <button
                v-if="clearable && (rangeStartDate || rangeEndDate) && !disabled && !loading"
                type="button"
                class="hcis-date-picker__clear"
                title="Bersihkan rentang tanggal"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span v-else class="hcis-date-picker__arrow" :class="{ 'is-open': isOpen }" @click.stop="togglePopover">
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 3: SINGLE DATETIME TRIGGER (Date + Time Capsule UX)
             ═══════════════════════════════════════════════════════════════════ -->
        <div
            v-else-if="isDateTimeMode"
            ref="triggerRef"
            class="hcis-date-picker__trigger hcis-date-picker__trigger--datetime"
            :class="{
                'hcis-date-picker__trigger--focused': isFocused || isOpen,
                'hcis-date-picker__trigger--disabled': disabled,
                'hcis-date-picker__trigger--loading': loading,
                'hcis-date-picker__trigger--open': isOpen,
            }"
            @click.stop="handleTriggerClick"
        >
            <span class="hcis-date-picker__icon">
                <HcisIcon v-if="loading" name="arrow-repeat" size="xs" class="hcis-spin-icon" />
                <HcisIcon v-else-if="disabled" name="lock-fill" size="xs" />
                <HcisIcon v-else name="calendar-event-fill" size="xs" />
            </span>

            <div class="hcis-date-picker__dt-inputs">
                <div class="hcis-dt-pill" :class="{ 'is-active': isOpen && dtActiveTab === 'date' }">
                    <HcisIcon name="calendar3" size="2xs" class="me-1 text-brand-600" />
                    <span class="hcis-dt-pill-text" :class="{ 'is-placeholder': !dtDisplayDate }">
                        {{ formatReadableDate(dtDisplayDate) || 'Pilih Tanggal' }}
                    </span>
                </div>

                <span class="hcis-dt-sep">•</span>

                <div class="hcis-dt-pill" :class="{ 'is-active': isOpen && dtActiveTab === 'time' }">
                    <HcisIcon name="clock-fill" size="2xs" class="me-1 text-brand-600" />
                    <span class="hcis-dt-pill-text" :class="{ 'is-placeholder': !dtDisplayTime }">
                        {{ dtDisplayTime || '00:00' }}
                    </span>
                </div>
            </div>

            <button
                v-if="clearable && modelValue && !disabled && !loading"
                type="button"
                class="hcis-date-picker__clear"
                title="Bersihkan tanggal & waktu"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span v-else class="hcis-date-picker__arrow" :class="{ 'is-open': isOpen }" @click.stop="togglePopover">
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             DESKTOP POPOVER PANEL (TELEPORTED TO BODY - FOR NON-MOBILE)
             ═══════════════════════════════════════════════════════════════════ -->
        <Teleport v-if="!isMobile" to="body" :disabled="teleportTo === false">
            <Transition name="hcis-date-popover">
                <div
                    v-if="isOpen && !disabled && !loading"
                    ref="popoverRef"
                    class="hcis-date-picker__popover"
                    :class="[
                        `hcis-date-picker__popover--${effectiveType}`,
                        { 'hcis-date-picker__popover--range': isRangeMode || isDateTimeRangeMode }
                    ]"
                    :style="popoverStyle"
                    role="dialog"
                    aria-label="Pemilih Tanggal"
                    @click.stop
                >
                    <!-- ── 1. Quick Preset Chips Bar ─────────────────────── -->
                    <div v-if="showPresets && activePresetsList && activePresetsList.length" class="hcis-date-picker__presets">
                        <div class="hcis-date-picker__presets-header">
                            <span class="hcis-date-picker__presets-label">
                                <HcisIcon name="lightning-charge-fill" size="2xs" class="me-1" />
                                {{ isRangeMode ? 'Preset Rentang Tanggal' : 'Preset Cepat' }}
                            </span>
                        </div>
                        <div class="hcis-date-picker__chips">
                            <button
                                v-for="p in activePresetsList"
                                :key="p.key || p.value || p.label"
                                type="button"
                                class="hcis-date-picker__chip"
                                :class="{ 'hcis-date-picker__chip--active': isPresetActive(p) }"
                                @click="applyPreset(p)"
                            >
                                <HcisIcon v-if="isPresetActive(p)" name="check-lg" size="2xs" class="me-1" />
                                {{ p.label || p.value }}
                            </button>
                        </div>
                    </div>

                    <!-- ── 2A. SINGLE DATE CALENDAR PANEL ────────────────── -->
                    <template v-if="effectiveType === 'date'">
                        <!-- DAYS VIEW -->
                        <template v-if="viewMode === 'days'">
                            <div class="hcis-date-picker__nav">
                                <div class="hcis-date-picker__nav-group">
                                    <button type="button" class="hcis-date-picker__nav-btn" title="Tahun Sebelumnya" @click="changeYear(-1)">
                                        <HcisIcon name="chevron-double-left" size="2xs" />
                                    </button>
                                    <button type="button" class="hcis-date-picker__nav-btn" title="Bulan Sebelumnya" @click="changeMonth(-1)">
                                        <HcisIcon name="chevron-left" size="2xs" />
                                    </button>
                                </div>

                                <div class="hcis-date-picker__current-month d-inline-flex align-items-center gap-1">
                                    <button
                                        type="button"
                                        class="hcis-date-picker__header-btn"
                                        title="Pilih Bulan"
                                        @click="viewMode = 'months'"
                                    >
                                        {{ monthNames[currentMonth] }}
                                    </button>
                                    <button
                                        type="button"
                                        class="hcis-date-picker__header-btn"
                                        title="Pilih Tahun"
                                        @click="openYearsView"
                                    >
                                        {{ currentYear }}
                                    </button>
                                </div>

                                <div class="hcis-date-picker__nav-group">
                                    <button type="button" class="hcis-date-picker__nav-btn" title="Bulan Berikutnya" @click="changeMonth(1)">
                                        <HcisIcon name="chevron-right" size="2xs" />
                                    </button>
                                    <button type="button" class="hcis-date-picker__nav-btn" title="Tahun Berikutnya" @click="changeYear(1)">
                                        <HcisIcon name="chevron-double-right" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <div class="hcis-date-picker__weekdays">
                                <span v-for="w in weekDays" :key="w" class="hcis-date-picker__weekday">{{ w }}</span>
                            </div>

                            <div class="hcis-date-picker__days-grid">
                                <button
                                    v-for="(day, idx) in singleCalendarDays"
                                    :key="idx"
                                    type="button"
                                    class="hcis-date-picker__day-cell"
                                    :class="{
                                        'hcis-date-picker__day-cell--other-month': day.otherMonth,
                                        'hcis-date-picker__day-cell--today': day.isToday,
                                        'hcis-date-picker__day-cell--selected': day.isSelected,
                                        'hcis-date-picker__day-cell--disabled': day.isDisabled,
                                    }"
                                    :disabled="day.isDisabled"
                                    @click="selectSingleDate(day)"
                                >
                                    {{ day.dateNumber }}
                                </button>
                            </div>
                        </template>

                        <!-- MONTHS VIEW -->
                        <template v-else-if="viewMode === 'months'">
                            <div class="hcis-date-picker__nav">
                                <button type="button" class="hcis-date-picker__nav-btn" title="Tahun Sebelumnya" @click="changeYear(-1)">
                                    <HcisIcon name="chevron-left" size="2xs" />
                                </button>

                                <button
                                    type="button"
                                    class="hcis-date-picker__header-btn hcis-date-picker__header-btn--active"
                                    title="Pilih Tahun"
                                    @click="openYearsView"
                                >
                                    {{ currentYear }}
                                    <HcisIcon name="chevron-down" size="2xs" class="ms-1" />
                                </button>

                                <button type="button" class="hcis-date-picker__nav-btn" title="Tahun Berikutnya" @click="changeYear(1)">
                                    <HcisIcon name="chevron-right" size="2xs" />
                                </button>
                            </div>

                            <div class="hcis-date-picker__months-grid">
                                <button
                                    v-for="(mName, mIdx) in monthNames"
                                    :key="mName"
                                    type="button"
                                    class="hcis-date-picker__grid-cell"
                                    :class="{ 'is-selected': mIdx === currentMonth }"
                                    @click="selectMonth(mIdx)"
                                >
                                    {{ mName.slice(0, 3) }}
                                </button>
                            </div>
                        </template>

                        <!-- YEARS VIEW (Decade Grid) -->
                        <template v-else-if="viewMode === 'years'">
                            <div class="hcis-date-picker__nav">
                                <button type="button" class="hcis-date-picker__nav-btn" title="Dekade Sebelumnya" @click="decadeStartYear -= 12">
                                    <HcisIcon name="chevron-double-left" size="2xs" />
                                </button>

                                <span class="hcis-date-picker__current-month fw-bold">
                                    {{ decadeStartYear }} - {{ decadeStartYear + 11 }}
                                </span>

                                <button type="button" class="hcis-date-picker__nav-btn" title="Dekade Berikutnya" @click="decadeStartYear += 12">
                                    <HcisIcon name="chevron-double-right" size="2xs" />
                                </button>
                            </div>

                            <div class="hcis-date-picker__years-grid">
                                <button
                                    v-for="y in decadeYears"
                                    :key="y"
                                    type="button"
                                    class="hcis-date-picker__grid-cell"
                                    :class="{ 'is-selected': y === currentYear }"
                                    @click="selectYear(y)"
                                >
                                    {{ y }}
                                </button>
                            </div>
                        </template>
                    </template>

                    <!-- ── 2B. DUAL SIDE-BY-SIDE CALENDARS FOR DATERANGE ─── -->
                    <template v-else-if="isRangeMode">
                        <div class="hcis-dual-calendars">
                            <!-- Left Calendar Panel (Start Month) -->
                            <div class="hcis-calendar-panel">
                                <div class="hcis-calendar-nav">
                                    <button type="button" class="hcis-nav-btn" title="Tahun Sebelumnya" @click="changeRangeYear(-1)">
                                        <HcisIcon name="chevron-double-left" size="2xs" />
                                    </button>
                                    <button type="button" class="hcis-nav-btn" title="Bulan Sebelumnya" @click="changeRangeMonth(-1)">
                                        <HcisIcon name="chevron-left" size="2xs" />
                                    </button>

                                    <span class="hcis-current-month">
                                        {{ monthNames[leftMonth] }} {{ leftYear }}
                                    </span>

                                    <div class="hcis-nav-spacer" />
                                </div>

                                <div class="hcis-weekdays">
                                    <span v-for="w in weekDays" :key="w" class="hcis-weekday">{{ w }}</span>
                                </div>

                                <div class="hcis-days-grid">
                                    <button
                                        v-for="(day, idx) in leftCalendarDays"
                                        :key="idx"
                                        type="button"
                                        class="hcis-day-cell"
                                        :class="getRangeDayClasses(day)"
                                        @click="onRangeDayClick(day)"
                                        @mouseenter="onRangeDayMouseEnter(day)"
                                    >
                                        {{ day.dateNumber }}
                                    </button>
                                </div>
                            </div>

                            <!-- Right Calendar Panel (End Month) -->
                            <div class="hcis-calendar-panel">
                                <div class="hcis-calendar-nav">
                                    <div class="hcis-nav-spacer" />

                                    <span class="hcis-current-month">
                                        {{ monthNames[rightMonth] }} {{ rightYear }}
                                    </span>

                                    <button type="button" class="hcis-nav-btn" title="Bulan Berikutnya" @click="changeRangeMonth(1)">
                                        <HcisIcon name="chevron-right" size="2xs" />
                                    </button>
                                    <button type="button" class="hcis-nav-btn" title="Tahun Berikutnya" @click="changeRangeYear(1)">
                                        <HcisIcon name="chevron-double-right" size="2xs" />
                                    </button>
                                </div>

                                <div class="hcis-weekdays">
                                    <span v-for="w in weekDays" :key="w" class="hcis-weekday">{{ w }}</span>
                                </div>

                                <div class="hcis-days-grid">
                                    <button
                                        v-for="(day, idx) in rightCalendarDays"
                                        :key="idx"
                                        type="button"
                                        class="hcis-day-cell"
                                        :class="getRangeDayClasses(day)"
                                        @click="onRangeDayClick(day)"
                                        @mouseenter="onRangeDayMouseEnter(day)"
                                    >
                                        {{ day.dateNumber }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ── 2C. COMBINED SIDE-BY-SIDE CALENDAR + TIME FOR DATETIME -->
                    <template v-else-if="isDateTimeMode">
                        <div class="hcis-dt-combined-view">
                            <!-- Left: Calendar -->
                            <div class="hcis-dt-calendar-side">
                                <div class="hcis-calendar-nav">
                                    <button type="button" class="hcis-nav-btn" title="Tahun Sebelumnya" @click="changeYear(-1)">
                                        <HcisIcon name="chevron-double-left" size="2xs" />
                                    </button>
                                    <button type="button" class="hcis-nav-btn" title="Bulan Sebelumnya" @click="changeMonth(-1)">
                                        <HcisIcon name="chevron-left" size="2xs" />
                                    </button>

                                    <span class="hcis-current-month">
                                        {{ monthNames[currentMonth] }} {{ currentYear }}
                                    </span>

                                    <button type="button" class="hcis-nav-btn" title="Bulan Berikutnya" @click="changeMonth(1)">
                                        <HcisIcon name="chevron-right" size="2xs" />
                                    </button>
                                    <button type="button" class="hcis-nav-btn" title="Tahun Berikutnya" @click="changeYear(1)">
                                        <HcisIcon name="chevron-double-right" size="2xs" />
                                    </button>
                                </div>

                                <div class="hcis-weekdays">
                                    <span v-for="w in weekDays" :key="w" class="hcis-weekday">{{ w }}</span>
                                </div>

                                <div class="hcis-days-grid">
                                    <button
                                        v-for="(day, idx) in singleCalendarDays"
                                        :key="idx"
                                        type="button"
                                        class="hcis-day-cell"
                                        :class="{
                                            'is-other-month': day.otherMonth,
                                            'is-today': day.isToday,
                                            /* is-start, seperti versi ponsel: `is-selected`
                                               tak punya aturan CSS untuk sel hari, sehingga
                                               tanggal yang diklik tak terlihat terpilih. */
                                            'is-start': day.dateString === dtSelectedDate,
                                        }"
                                        @click="selectDtDate(day)"
                                    >
                                        {{ day.dateNumber }}
                                    </button>
                                </div>
                            </div>

                            <!-- Right: Time Columns -->
                            <div class="hcis-dt-time-side">
                                <div class="hcis-time-side-header">
                                    <HcisIcon name="clock-history" size="2xs" class="me-1 text-brand-600" />
                                    Pilih Jam: <strong>{{ padZero(dtHour) }}:{{ padZero(dtMinute) }}</strong>
                                </div>

                                <div class="hcis-time-selectors">
                                    <div class="hcis-time-col">
                                        <div class="hcis-time-col-header">Jam</div>
                                        <div class="hcis-time-col-list">
                                            <button
                                                v-for="h in 24"
                                                :key="h - 1"
                                                type="button"
                                                class="hcis-time-item"
                                                :class="{ 'is-selected': dtHour === (h - 1) }"
                                                @click="dtHour = (h - 1)"
                                            >
                                                {{ padZero(h - 1) }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="hcis-time-col">
                                        <div class="hcis-time-col-header">Menit</div>
                                        <div class="hcis-time-col-list">
                                            <button
                                                v-for="m in pilihanMenit"
                                                :key="m"
                                                type="button"
                                                class="hcis-time-item"
                                                :class="{ 'is-selected': dtMinute === m }"
                                                @click="dtMinute = m"
                                            >
                                                {{ padZero(m) }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ── 3. FOOTER ACTIONS & READOUT ───────────────────── -->
                    <div class="hcis-date-picker__footer">
                        <div v-if="effectiveType === 'date'" class="d-flex align-items-center">
                            <button type="button" class="hcis-date-picker__btn-today" @click="setSingleToday">
                                <HcisIcon name="calendar-event" size="xs" class="me-1" /> Hari Ini
                            </button>
                        </div>

                        <div v-else-if="isRangeMode" class="hcis-range-readout">
                            <span v-if="tempStart" class="hcis-readout-text">
                                <span class="hcis-date-val">{{ formatReadableDate(tempStart) }}</span>
                                <template v-if="tempEnd && tempEnd !== tempStart">
                                    <span class="hcis-date-sep">s/d</span>
                                    <span class="hcis-date-val">{{ formatReadableDate(tempEnd) }}</span>
                                </template>
                                <span v-if="calculatedRangeDays > 0" class="hcis-readout-badge">
                                    {{ calculatedRangeDays }} hari
                                </span>
                            </span>
                            <span v-else class="hcis-readout-placeholder">Pilih tanggal atau rentang</span>
                        </div>

                        <div v-else-if="isDateTimeMode" class="hcis-dt-readout">
                            <span class="hcis-dt-readout-text">
                                {{ formatReadableDate(dtSelectedDate) || 'Pilih tanggal' }}, {{ padZero(dtHour) }}:{{ padZero(dtMinute) }}
                            </span>
                        </div>

                        <div class="hcis-date-picker__footer-actions">
                            <button type="button" class="hcis-date-picker__btn-cancel" @click="closePopover">
                                Batal
                            </button>
                            <button
                                type="button"
                                class="hcis-date-picker__btn-confirm"
                                :disabled="isConfirmDisabled"
                                @click="confirmSelection"
                            >
                                Terapkan
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════════════
             MOBILE VIEW: SIGNATURE HCIS DIALOG (Modal Bottom-Sheet)
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisDialog
            v-if="isMobile"
            v-model="isOpen"
            :title="mobileDialogTitle"
            :description="mobileDialogSubtitle"
            :icon="mobileDialogIcon"
            size="sm"
            sentiment="brand"
            variant="glass"
            pattern="grid"
            :show-close="true"
        >
            <div class="hcis-mobile-dialog-content">
                <!-- 1. Quick Presets Chips -->
                <div v-if="showPresets && activePresetsList && activePresetsList.length" class="hcis-date-picker__presets mb-3">
                    <div class="hcis-date-picker__presets-header">
                        <span class="hcis-date-picker__presets-label">
                            <HcisIcon name="lightning-charge-fill" size="2xs" class="me-1 text-brand-600" />
                            Preset Cepat
                        </span>
                    </div>
                    <div class="hcis-date-picker__chips">
                        <button
                            v-for="p in activePresetsList"
                            :key="p.key || p.value || p.label"
                            type="button"
                            class="hcis-date-picker__chip"
                            :class="{ 'hcis-date-picker__chip--active': isPresetActive(p) }"
                            @click="applyPreset(p)"
                        >
                            <HcisIcon v-if="isPresetActive(p)" name="check-lg" size="2xs" class="me-1" />
                            {{ p.label || p.value }}
                        </button>
                    </div>
                </div>

                <!-- 2. Single Date Mobile Calendar -->
                <div v-if="effectiveType === 'date'" class="hcis-calendar-panel">
                    <!-- DAYS VIEW -->
                    <template v-if="viewMode === 'days'">
                        <div class="hcis-calendar-nav">
                            <div class="hcis-date-picker__nav-group">
                                <button type="button" class="hcis-nav-btn" title="Tahun Sebelumnya" @click="changeYear(-1)">
                                    <HcisIcon name="chevron-double-left" size="2xs" />
                                </button>
                                <button type="button" class="hcis-nav-btn" title="Bulan Sebelumnya" @click="changeMonth(-1)">
                                    <HcisIcon name="chevron-left" size="2xs" />
                                </button>
                            </div>

                            <div class="hcis-current-month d-inline-flex align-items-center gap-1">
                                <button
                                    type="button"
                                    class="hcis-date-picker__header-btn"
                                    title="Pilih Bulan"
                                    @click="viewMode = 'months'"
                                >
                                    {{ monthNames[currentMonth] }}
                                </button>
                                <button
                                    type="button"
                                    class="hcis-date-picker__header-btn"
                                    title="Pilih Tahun"
                                    @click="openYearsView"
                                >
                                    {{ currentYear }}
                                </button>
                            </div>

                            <div class="hcis-date-picker__nav-group">
                                <button type="button" class="hcis-nav-btn" title="Bulan Berikutnya" @click="changeMonth(1)">
                                    <HcisIcon name="chevron-right" size="2xs" />
                                </button>
                                <button type="button" class="hcis-nav-btn" title="Tahun Berikutnya" @click="changeYear(1)">
                                    <HcisIcon name="chevron-double-right" size="2xs" />
                                </button>
                            </div>
                        </div>

                        <div class="hcis-weekdays">
                            <span v-for="w in weekDays" :key="w" class="hcis-weekday">{{ w }}</span>
                        </div>

                        <div class="hcis-days-grid">
                            <button
                                v-for="(day, idx) in singleCalendarDays"
                                :key="idx"
                                type="button"
                                class="hcis-day-cell"
                                :class="{
                                    'is-other-month': day.otherMonth,
                                    'is-today': day.isToday,
                                    'is-start': day.dateString === selectedDate,
                                }"
                                @click="selectSingleDate(day)"
                            >
                                {{ day.dateNumber }}
                            </button>
                        </div>
                    </template>

                    <!-- MONTHS VIEW -->
                    <template v-else-if="viewMode === 'months'">
                        <div class="hcis-calendar-nav">
                            <button type="button" class="hcis-nav-btn" title="Tahun Sebelumnya" @click="changeYear(-1)">
                                <HcisIcon name="chevron-left" size="2xs" />
                            </button>

                            <button
                                type="button"
                                class="hcis-date-picker__header-btn hcis-date-picker__header-btn--active"
                                title="Pilih Tahun"
                                @click="openYearsView"
                            >
                                {{ currentYear }}
                                <HcisIcon name="chevron-down" size="2xs" class="ms-1" />
                            </button>

                            <button type="button" class="hcis-nav-btn" title="Tahun Berikutnya" @click="changeYear(1)">
                                <HcisIcon name="chevron-right" size="2xs" />
                            </button>
                        </div>

                        <div class="hcis-date-picker__months-grid">
                            <button
                                v-for="(mName, mIdx) in monthNames"
                                :key="mName"
                                type="button"
                                class="hcis-date-picker__grid-cell"
                                :class="{ 'is-selected': mIdx === currentMonth }"
                                @click="selectMonth(mIdx)"
                            >
                                {{ mName.slice(0, 3) }}
                            </button>
                        </div>
                    </template>

                    <!-- YEARS VIEW -->
                    <template v-else-if="viewMode === 'years'">
                        <div class="hcis-calendar-nav">
                            <button type="button" class="hcis-nav-btn" title="Dekade Sebelumnya" @click="decadeStartYear -= 12">
                                <HcisIcon name="chevron-double-left" size="2xs" />
                            </button>

                            <span class="hcis-current-month fw-bold">
                                {{ decadeStartYear }} - {{ decadeStartYear + 11 }}
                            </span>

                            <button type="button" class="hcis-nav-btn" title="Dekade Berikutnya" @click="decadeStartYear += 12">
                                <HcisIcon name="chevron-double-right" size="2xs" />
                            </button>
                        </div>

                        <div class="hcis-date-picker__years-grid">
                            <button
                                v-for="y in decadeYears"
                                :key="y"
                                type="button"
                                class="hcis-date-picker__grid-cell"
                                :class="{ 'is-selected': y === currentYear }"
                                @click="selectYear(y)"
                            >
                                {{ y }}
                            </button>
                        </div>
                    </template>
                </div>

                <!-- 3. Date Range Mobile Calendar -->
                <div v-else-if="isRangeMode" class="hcis-calendar-panel">
                    <div class="hcis-calendar-nav">
                        <div class="hcis-date-picker__nav-group">
                            <button type="button" class="hcis-nav-btn" title="Tahun Sebelumnya" @click="changeRangeYear(-1)">
                                <HcisIcon name="chevron-double-left" size="2xs" />
                            </button>
                            <button type="button" class="hcis-nav-btn" title="Bulan Sebelumnya" @click="changeRangeMonth(-1)">
                                <HcisIcon name="chevron-left" size="2xs" />
                            </button>
                        </div>

                        <span class="hcis-current-month">
                            {{ monthNames[leftMonth] }} {{ leftYear }}
                        </span>

                        <div class="hcis-date-picker__nav-group">
                            <button type="button" class="hcis-nav-btn" title="Bulan Berikutnya" @click="changeRangeMonth(1)">
                                <HcisIcon name="chevron-right" size="2xs" />
                            </button>
                            <button type="button" class="hcis-nav-btn" title="Tahun Berikutnya" @click="changeRangeYear(1)">
                                <HcisIcon name="chevron-double-right" size="2xs" />
                            </button>
                        </div>
                    </div>

                    <div class="hcis-weekdays">
                        <span v-for="w in weekDays" :key="w" class="hcis-weekday">{{ w }}</span>
                    </div>

                    <div class="hcis-days-grid">
                        <button
                            v-for="(day, idx) in leftCalendarDays"
                            :key="idx"
                            type="button"
                            class="hcis-day-cell"
                            :class="getRangeDayClasses(day)"
                            @click="onRangeDayClick(day)"
                        >
                            {{ day.dateNumber }}
                        </button>
                    </div>
                </div>

                <!-- 4. DateTime Mobile Calendar + Time Selectors -->
                <div v-else-if="isDateTimeMode" class="hcis-mobile-dt-stack">
                    <div class="hcis-calendar-panel">
                        <div class="hcis-calendar-nav">
                            <button type="button" class="hcis-nav-btn" @click="changeYear(-1)">
                                <HcisIcon name="chevron-double-left" size="2xs" />
                            </button>
                            <button type="button" class="hcis-nav-btn" @click="changeMonth(-1)">
                                <HcisIcon name="chevron-left" size="2xs" />
                            </button>
                            <span class="hcis-current-month">
                                {{ monthNames[currentMonth] }} {{ currentYear }}
                            </span>
                            <button type="button" class="hcis-nav-btn" @click="changeMonth(1)">
                                <HcisIcon name="chevron-right" size="2xs" />
                            </button>
                            <button type="button" class="hcis-nav-btn" @click="changeYear(1)">
                                <HcisIcon name="chevron-double-right" size="2xs" />
                            </button>
                        </div>
                        <div class="hcis-weekdays">
                            <span v-for="w in weekDays" :key="w" class="hcis-weekday">{{ w }}</span>
                        </div>
                        <div class="hcis-days-grid">
                            <button
                                v-for="(day, idx) in singleCalendarDays"
                                :key="idx"
                                type="button"
                                class="hcis-day-cell"
                                :class="{
                                    'is-other-month': day.otherMonth,
                                    'is-today': day.isToday,
                                    'is-start': day.dateString === dtSelectedDate,
                                }"
                                @click="selectDtDate(day)"
                            >
                                {{ day.dateNumber }}
                            </button>
                        </div>
                    </div>

                    <div class="hcis-mobile-time-picker mt-3">
                        <div class="hcis-time-side-header mb-2">
                            <HcisIcon name="clock-fill" size="2xs" class="me-1 text-brand-600" />
                            Waktu: <strong>{{ padZero(dtHour) }}:{{ padZero(dtMinute) }}</strong>
                        </div>
                        <div class="hcis-time-selectors">
                            <div class="hcis-time-col">
                                <div class="hcis-time-col-header">Jam</div>
                                <div class="hcis-time-col-list">
                                    <button
                                        v-for="h in 24"
                                        :key="h - 1"
                                        type="button"
                                        class="hcis-time-item"
                                        :class="{ 'is-selected': dtHour === (h - 1) }"
                                        @click="dtHour = (h - 1)"
                                    >
                                        {{ padZero(h - 1) }}
                                    </button>
                                </div>
                            </div>
                            <div class="hcis-time-col">
                                <div class="hcis-time-col-header">Menit</div>
                                <div class="hcis-time-col-list">
                                    <button
                                        v-for="m in pilihanMenit"
                                        :key="m"
                                        type="button"
                                        class="hcis-time-item"
                                        :class="{ 'is-selected': dtMinute === m }"
                                        @click="dtMinute = m"
                                    >
                                        {{ padZero(m) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="hcis-date-picker__footer hcis-date-picker__footer--dialog">
                    <div v-if="effectiveType === 'date'" class="d-flex align-items-center">
                        <button type="button" class="hcis-date-picker__btn-today" @click="setSingleToday">
                            <HcisIcon name="calendar-event" size="xs" class="me-1" /> Hari Ini
                        </button>
                    </div>

                    <div v-else-if="isRangeMode" class="hcis-range-readout">
                        <span v-if="tempStart" class="hcis-readout-text">
                            <span class="hcis-date-val">{{ formatReadableDate(tempStart) }}</span>
                            <template v-if="tempEnd && tempEnd !== tempStart">
                                <span class="hcis-date-sep">s/d</span>
                                <span class="hcis-date-val">{{ formatReadableDate(tempEnd) }}</span>
                            </template>
                            <span v-if="calculatedRangeDays > 0" class="hcis-readout-badge">
                                {{ calculatedRangeDays }} hari
                            </span>
                        </span>
                    </div>

                    <div v-else-if="isDateTimeMode" class="hcis-dt-readout">
                        <span class="hcis-dt-readout-text">
                            {{ formatReadableDate(dtSelectedDate) || 'Pilih tanggal' }}, {{ padZero(dtHour) }}:{{ padZero(dtMinute) }}
                        </span>
                    </div>

                    <div class="hcis-date-picker__footer-actions ms-auto">
                        <button type="button" class="hcis-date-picker__btn-cancel" @click="closePopover">
                            Batal
                        </button>
                        <button
                            type="button"
                            class="hcis-date-picker__btn-confirm"
                            :disabled="isConfirmDisabled"
                            @click="confirmSelection"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisDialog from './HcisDialog.vue';

defineOptions({ name: 'HcisDatePicker' });

const props = defineProps({
    modelValue: {
        type: [String, Array, Date, Object],
        default: '',
    },
    type: {
        type: String,
        default: 'date',
        validator: (v) => ['date', 'daterange', 'datetime', 'datetimerange', 'month', 'year', 'birthdate'].includes(v),
    },
    defaultView: {
        type: String,
        default: 'days',
        validator: (v) => ['days', 'months', 'years'].includes(v),
    },
    yearFirst: {
        type: Boolean,
        default: false,
    },
    isRange: { type: Boolean, default: false },
    range: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    startPlaceholder: { type: String, default: 'Tanggal Mulai' },
    endPlaceholder: { type: String, default: 'Tanggal Selesai' },
    rangeSeparator: { type: String, default: 's/d' },
    format: { type: String, default: 'YYYY-MM-DD' },
    valueFormat: { type: String, default: 'YYYY-MM-DD' },
    clearable: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    teleportTo: { type: [String, Boolean], default: 'body' },
    showPresets: { type: Boolean, default: true },
    presets: { type: Array, default: null },
    disabledDate: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear']);

// ── DOM References & Global State ─────────────────────────────────────────
const containerRef = ref(null);
const triggerRef = ref(null);
const inputRef = ref(null);
const popoverRef = ref(null);
const isOpen = ref(false);
const isFocused = ref(false);
const isMobile = ref(false);

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    zIndex: '99999',
});

// ── Mode Determination ───────────────────────────────────────────────────
const effectiveType = computed(() => {
    if (props.isRange || props.range || props.type === 'daterange') return 'daterange';
    if (props.type === 'datetimerange') return 'datetimerange';
    if (props.type === 'datetime') return 'datetime';
    return 'date';
});

const isRangeMode = computed(() => effectiveType.value === 'daterange');
const isDateTimeMode = computed(() => effectiveType.value === 'datetime');
const isDateTimeRangeMode = computed(() => effectiveType.value === 'datetimerange');

const mobileDialogTitle = computed(() => {
    if (isRangeMode.value) return 'Pilih Rentang Tanggal';
    if (isDateTimeMode.value) return 'Pilih Tanggal & Jam';
    if (props.type === 'month') return 'Pilih Bulan & Tahun';
    if (props.type === 'year') return 'Pilih Tahun';
    return 'Pilih Tanggal';
});

const mobileDialogSubtitle = computed(() => {
    if (isRangeMode.value) return 'Tentukan tanggal mulai dan tanggal selesai';
    if (isDateTimeMode.value) return 'Pilih tanggal kalender dan waktu pelaksanaan';
    if (props.type === 'month') return 'Tentukan bulan dan tahun pelaksanaan';
    return 'Pilih tanggal efektif dari kalender';
});

const mobileDialogIcon = computed(() => {
    if (isRangeMode.value) return 'calendar-range-fill';
    if (isDateTimeMode.value) return 'calendar-event-fill';
    return 'calendar3';
});

// ── Helpers & Constants ──────────────────────────────────────────────────
const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const weekDays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const padZero = (n) => String(n).padStart(2, '0');

const formatYMD = (year, month, day) => {
    return `${year}-${padZero(month + 1)}-${padZero(day)}`;
};

const formatReadableDate = (dateStr) => {
    if (!dateStr || typeof dateStr !== 'string') return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const mShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${d} ${mShort[m] || ''} ${y}`;
};

/**
 * Satu-satunya format tampilan selain baku yang didukung. Prop `format` dulu
 * diterima tapi diabaikan; mendukung pola bebas butuh pengurai penuh, jadi
 * yang dikenali hanya bentuk yang memang dipakai.
 */
const formatBaca = computed(() => {
    if (effectiveType.value === 'date' && props.format === 'D MMMM YYYY') return true;
    if (props.type === 'month' && (props.format === 'MMMM YYYY' || props.format === 'D MMMM YYYY')) return true;
    return false;
});

const BULAN_PANJANG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const formatTanggalPanjang = (dateStr) => {
    const raw = String(dateStr ?? '');
    const cocokHari = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);
    if (cocokHari) {
        const bulan = BULAN_PANJANG[Number(cocokHari[2]) - 1];
        return bulan ? `${Number(cocokHari[3])} ${bulan} ${cocokHari[1]}` : raw;
    }
    const cocokBulan = /^(\d{4})-(\d{2})$/.exec(raw);
    if (cocokBulan) {
        const bulan = BULAN_PANJANG[Number(cocokBulan[2]) - 1];
        return bulan ? `${bulan} ${cocokBulan[1]}` : raw;
    }
    return raw;
};

// ── 1. Single Date State & Methods ────────────────────────────────────────
const now = new Date();
const currentYear = ref(now.getFullYear());
const currentMonth = ref(now.getMonth());
const selectedDate = ref('');
const inputValue = ref('');
const viewMode = ref(props.defaultView || 'days');
const decadeStartYear = ref(Math.floor(now.getFullYear() / 12) * 12);

const decadeYears = computed(() => {
    const years = [];
    for (let i = 0; i < 12; i++) {
        years.push(decadeStartYear.value + i);
    }
    return years;
});

function openYearsView() {
    decadeStartYear.value = Math.floor(currentYear.value / 12) * 12;
    viewMode.value = 'years';
}

function selectYear(year) {
    currentYear.value = year;
    if (props.type === 'year') {
        const val = String(year);
        if (tandaiSaja(val)) return;
        selectedDate.value = val;
        inputValue.value = val;
        emit('update:modelValue', val);
        emit('change', val);
        closePopover();
        return;
    }
    viewMode.value = 'months';
}

function selectMonth(monthIdx) {
    currentMonth.value = monthIdx;
    if (props.type === 'month') {
        const ym = `${currentYear.value}-${padZero(monthIdx + 1)}`;
        if (tandaiSaja(ym)) return;
        selectedDate.value = ym;
        inputValue.value = ym;
        emit('update:modelValue', ym);
        emit('change', ym);
        closePopover();
        return;
    }
    viewMode.value = 'days';
}

const singleCalendarDays = computed(() => {
    const y = currentYear.value;
    const m = currentMonth.value;
    const firstDayIndex = new Date(y, m, 1).getDay();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const daysInPrevMonth = new Date(y, m, 0).getDate();

    const days = [];
    const todayStr = formatYMD(now.getFullYear(), now.getMonth(), now.getDate());

    // Prev month overflow days
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const prevM = m === 0 ? 11 : m - 1;
        const prevY = m === 0 ? y - 1 : y;
        const dateStr = formatYMD(prevY, prevM, d);
        days.push({
            dateNumber: d,
            dateString: dateStr,
            otherMonth: true,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isDisabled: props.disabledDate ? props.disabledDate(new Date(prevY, prevM, d)) : false,
        });
    }

    // Current month days
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = formatYMD(y, m, d);
        days.push({
            dateNumber: d,
            dateString: dateStr,
            otherMonth: false,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isDisabled: props.disabledDate ? props.disabledDate(new Date(y, m, d)) : false,
        });
    }

    // Next month overflow days
    const totalCells = days.length <= 35 ? 35 : 42;
    const remaining = totalCells - days.length;
    for (let d = 1; d <= remaining; d++) {
        const nextM = m === 11 ? 0 : m + 1;
        const nextY = m === 11 ? y + 1 : y;
        const dateStr = formatYMD(nextY, nextM, d);
        days.push({
            dateNumber: d,
            dateString: dateStr,
            otherMonth: true,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isDisabled: props.disabledDate ? props.disabledDate(new Date(nextY, nextM, d)) : false,
        });
    }

    return days;
});

function changeMonth(delta) {
    let m = currentMonth.value + delta;
    let y = currentYear.value;
    if (m > 11) { m = 0; y++; }
    else if (m < 0) { m = 11; y--; }
    currentMonth.value = m;
    currentYear.value = y;
}

function changeYear(delta) {
    currentYear.value += delta;
}

/**
 * Di ponsel pilihan baru BERLAKU lewat Terapkan: mengetuk tanggal, preset,
 * bulan, atau tahun hanya menandainya. Menutup seketika di layar sempit
 * membuat salah ketuk langsung tersimpan, dan Batal tak ada artinya.
 */
function tandaiSaja(nilai) {
    if (!isMobile.value) return false;
    selectedDate.value = nilai;

    // Preset bisa jatuh di bulan lain; tanpa berpindah, tandanya tak terlihat.
    const bagian = /^(\d{4})-(\d{2})-\d{2}$/.exec(nilai);
    if (bagian) {
        currentYear.value = Number(bagian[1]);
        currentMonth.value = Number(bagian[2]) - 1;
        viewMode.value = 'days';
    }

    const bagianBulan = /^(\d{4})-(\d{2})$/.exec(nilai);
    if (bagianBulan && props.type === 'month') {
        currentYear.value = Number(bagianBulan[1]);
        currentMonth.value = Number(bagianBulan[2]) - 1;
        viewMode.value = 'months';
    }

    return true;
}

function selectSingleDate(day) {
    if (day.isDisabled) return;
    if (tandaiSaja(day.dateString)) return;
    selectedDate.value = day.dateString;
    inputValue.value = day.dateString;
    emit('update:modelValue', day.dateString);
    emit('change', day.dateString);
    closePopover();
}

function setSingleToday() {
    const todayStr = formatYMD(now.getFullYear(), now.getMonth(), now.getDate());
    selectedDate.value = todayStr;
    currentYear.value = now.getFullYear();
    currentMonth.value = now.getMonth();
    viewMode.value = 'days';
    if (tandaiSaja(todayStr)) return;
    inputValue.value = todayStr;
    emit('update:modelValue', todayStr);
    emit('change', todayStr);
    closePopover();
}

// ── 2. Date Range State & Methods ─────────────────────────────────────────
const leftYear = ref(now.getFullYear());
const leftMonth = ref(now.getMonth());
const rightYear = computed(() => (leftMonth.value === 11 ? leftYear.value + 1 : leftYear.value));
const rightMonth = computed(() => (leftMonth.value === 11 ? 0 : leftMonth.value + 1));

const tempStart = ref('');
const tempEnd = ref('');
const hoverDate = ref('');
const isSelectingRange = ref(false);
const activePresetKey = ref('');

const rangeStartDate = computed(() => {
    if (Array.isArray(props.modelValue) && props.modelValue[0]) return props.modelValue[0];
    return '';
});

const rangeEndDate = computed(() => {
    if (Array.isArray(props.modelValue) && props.modelValue[1]) return props.modelValue[1];
    return '';
});

const rangeDaysCount = computed(() => {
    if (!rangeStartDate.value || !rangeEndDate.value) return 0;
    const diff = new Date(rangeEndDate.value) - new Date(rangeStartDate.value);
    return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24)) + 1);
});

const calculatedRangeDays = computed(() => {
    if (!tempStart.value) return 0;
    const end = tempEnd.value || hoverDate.value || tempStart.value;
    const s = tempStart.value < end ? tempStart.value : end;
    const e = tempStart.value < end ? end : tempStart.value;
    const diff = new Date(e) - new Date(s);
    return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24)) + 1);
});

function createMonthGrid(y, m) {
    const firstDayIndex = new Date(y, m, 1).getDay();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const daysInPrevMonth = new Date(y, m, 0).getDate();
    const days = [];
    const todayStr = formatYMD(now.getFullYear(), now.getMonth(), now.getDate());

    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const prevM = m === 0 ? 11 : m - 1;
        const prevY = m === 0 ? y - 1 : y;
        days.push({ dateNumber: d, dateString: formatYMD(prevY, prevM, d), otherMonth: true, isToday: false });
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = formatYMD(y, m, d);
        days.push({ dateNumber: d, dateString: dateStr, otherMonth: false, isToday: dateStr === todayStr });
    }

    const remaining = 42 - days.length;
    for (let d = 1; d <= remaining; d++) {
        const nextM = m === 11 ? 0 : m + 1;
        const nextY = m === 11 ? y + 1 : y;
        days.push({ dateNumber: d, dateString: formatYMD(nextY, nextM, d), otherMonth: true, isToday: false });
    }
    return days;
}

const leftCalendarDays = computed(() => createMonthGrid(leftYear.value, leftMonth.value));
const rightCalendarDays = computed(() => createMonthGrid(rightYear.value, rightMonth.value));

function changeRangeMonth(delta) {
    let m = leftMonth.value + delta;
    let y = leftYear.value;
    if (m > 11) { m = 0; y++; }
    else if (m < 0) { m = 11; y--; }
    leftMonth.value = m;
    leftYear.value = y;
}

function changeRangeYear(delta) {
    leftYear.value += delta;
}

function getRangeDayClasses(day) {
    const ds = day.dateString;
    const start = tempStart.value;
    const end = tempEnd.value || (isSelectingRange.value ? hoverDate.value : '');

    const isStart = start && ds === start;
    const isEnd = end && ds === end;
    const isRange = start && end && ((ds > start && ds < end) || (ds > end && ds < start));

    return {
        'is-other-month': day.otherMonth,
        'is-today': day.isToday,
        'is-start': isStart,
        'is-end': isEnd,
        'is-range': isRange,
    };
}

function onRangeDayClick(day) {
    const ds = day.dateString;
    if (!isSelectingRange.value || !tempStart.value) {
        tempStart.value = ds;
        tempEnd.value = '';
        isSelectingRange.value = true;
        activePresetKey.value = '';
    } else {
        if (ds < tempStart.value) {
            tempEnd.value = tempStart.value;
            tempStart.value = ds;
        } else {
            tempEnd.value = ds;
        }
        isSelectingRange.value = false;
    }
}

function onRangeDayMouseEnter(day) {
    if (isSelectingRange.value && tempStart.value) {
        hoverDate.value = day.dateString;
    }
}

// ── 3. DateTime State & Methods ───────────────────────────────────────────
const dtActiveTab = ref('date');
const dtSelectedDate = ref('');
const dtHour = ref(8);
const dtMinute = ref(0);

/**
 * Menit yang bisa dipilih: kelipatan 5 ditambah :59, supaya akhir hari
 * (23:59) — batas berlaku izin sehari — bisa dipilih (permintaan user
 * 29 Sep 2026; sebelumnya mentok di 23:55). Menit tersimpan di luar daftar
 * (mis. :13 dari data lama) ikut ditampilkan agar tetap terlihat terpilih.
 */
const MENIT_DASAR = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 59];
const pilihanMenit = computed(() =>
    MENIT_DASAR.includes(dtMinute.value)
        ? MENIT_DASAR
        : [...MENIT_DASAR, dtMinute.value].sort((a, b) => a - b),
);

const dtDisplayDate = computed(() => {
    if (typeof props.modelValue === 'string' && props.modelValue) {
        return props.modelValue.split(' ')[0] || '';
    }
    return '';
});

const dtDisplayTime = computed(() => {
    if (typeof props.modelValue === 'string' && props.modelValue && props.modelValue.includes(' ')) {
        const t = props.modelValue.split(' ')[1];
        return t.substring(0, 5);
    }
    return '';
});

function selectDtDate(day) {
    if (day.isDisabled) return;
    dtSelectedDate.value = day.dateString;
    selectedDate.value = day.dateString;
}

// ── 4. Presets Management ────────────────────────────────────────────────
const defaultSinglePresets = [
    { label: 'Hari Ini', value: formatYMD(now.getFullYear(), now.getMonth(), now.getDate()) },
    { label: 'Besok', value: formatYMD(now.getFullYear(), now.getMonth(), now.getDate() + 1) },
    { label: 'Akhir Bulan', value: formatYMD(now.getFullYear(), now.getMonth() + 1, 0) },
];

const defaultRangePresets = [
    { key: 'today', label: 'Hari Ini', getRange: () => { const t = formatYMD(now.getFullYear(), now.getMonth(), now.getDate()); return [t, t]; } },
    { key: '7days', label: '7 Hari Terakhir', getRange: () => {
        const d1 = new Date(); d1.setDate(d1.getDate() - 6);
        return [formatYMD(d1.getFullYear(), d1.getMonth(), d1.getDate()), formatYMD(now.getFullYear(), now.getMonth(), now.getDate())];
    }},
    { key: '30days', label: '30 Hari Terakhir', getRange: () => {
        const d1 = new Date(); d1.setDate(d1.getDate() - 29);
        return [formatYMD(d1.getFullYear(), d1.getMonth(), d1.getDate()), formatYMD(now.getFullYear(), now.getMonth(), now.getDate())];
    }},
    { key: 'thisMonth', label: 'Bulan Ini', getRange: () => {
        return [formatYMD(now.getFullYear(), now.getMonth(), 1), formatYMD(now.getFullYear(), now.getMonth() + 1, 0)];
    }},
    { key: 'lastMonth', label: 'Bulan Lalu', getRange: () => {
        return [formatYMD(now.getFullYear(), now.getMonth() - 1, 1), formatYMD(now.getFullYear(), now.getMonth(), 0)];
    }},
];

const activePresetsList = computed(() => {
    if (props.presets && Array.isArray(props.presets)) return props.presets;
    if (isRangeMode.value || isDateTimeRangeMode.value) return defaultRangePresets;
    return defaultSinglePresets;
});

function isPresetActive(p) {
    if (isRangeMode.value) {
        return activePresetKey.value === p.key;
    }
    // Di ponsel yang ditandai belum berlaku, jadi nilai lama di input tidak ikut menyala.
    if (isMobile.value) return selectedDate.value === p.value;
    return inputValue.value === p.value || selectedDate.value === p.value;
}

function applyPreset(p) {
    if (isRangeMode.value) {
        if (p.getRange) {
            const [s, e] = p.getRange();
            tempStart.value = s;
            tempEnd.value = e;
            activePresetKey.value = p.key;
            isSelectingRange.value = false;
        }
    } else {
        if (tandaiSaja(p.value)) return;
        selectedDate.value = p.value;
        inputValue.value = p.value;
        emit('update:modelValue', p.value);
        emit('change', p.value);
        closePopover();
    }
}

// ── 5. Trigger & Popover Mechanics ────────────────────────────────────────
const hasValue = computed(() => {
    if (isRangeMode.value || isDateTimeRangeMode.value) {
        return !!(rangeStartDate.value || rangeEndDate.value);
    }
    return !!(props.modelValue || inputValue.value);
});

const isConfirmDisabled = computed(() => {
    if (isRangeMode.value) return !tempStart.value;
    if (isDateTimeMode.value) return !dtSelectedDate.value;
    return false;
});

function handleTriggerClick() {
    if (props.disabled || props.loading) return;
    if (!isOpen.value) {
        openPopover();
    } else if (effectiveType.value !== 'date') {
        closePopover();
    }
}

function handleInputClick() {
    if (props.disabled || props.loading) return;
    if (!isOpen.value) {
        openPopover();
    }
}

function togglePopover() {
    if (props.disabled || props.loading) return;
    if (isOpen.value) closePopover();
    else openPopover();
}

/**
 * Gulirkan kolom Jam & Menit ke butir terpilih.
 *
 * Daftarnya hanya setinggi enam butir; tanpa ini jam terpilih (mis. 08)
 * berada di luar pandangan dan kolomnya tampak tak punya pilihan aktif.
 * Hanya daftarnya yang digulir — bukan halaman atau modal di belakangnya.
 */
function gulirKeWaktuTerpilih() {
    const wadah = isMobile.value ? document.querySelector('.hcis-mobile-dialog-content') : popoverRef.value;
    if (!wadah) return;
    wadah.querySelectorAll('.hcis-time-col-list').forEach((daftar) => {
        const butir = daftar.querySelector('.hcis-time-item.is-selected');
        if (!butir) return;
        daftar.scrollTop = Math.max(0, butir.offsetTop - daftar.clientHeight / 2 + butir.offsetHeight / 2);
    });
}

function openPopover() {
    if (props.disabled || props.loading) return;
    isOpen.value = true;
    updatePopoverPosition();
    nextTick(() => {
        updatePopoverPosition();
        if (isDateTimeMode.value) gulirKeWaktuTerpilih();
    });

    if (isRangeMode.value) {
        tempStart.value = rangeStartDate.value;
        tempEnd.value = rangeEndDate.value;
        isSelectingRange.value = false;
        if (rangeStartDate.value) {
            const parts = rangeStartDate.value.split('-');
            leftYear.value = parseInt(parts[0], 10);
            leftMonth.value = parseInt(parts[1], 10) - 1;
        }
    } else if (isDateTimeMode.value) {
        if (dtDisplayDate.value) {
            dtSelectedDate.value = dtDisplayDate.value;
            const parts = dtDisplayDate.value.split('-');
            currentYear.value = parseInt(parts[0], 10);
            currentMonth.value = parseInt(parts[1], 10) - 1;
        }
        if (dtDisplayTime.value) {
            const tParts = dtDisplayTime.value.split(':');
            dtHour.value = parseInt(tParts[0], 10) || 0;
            dtMinute.value = parseInt(tParts[1], 10) || 0;
        }
    } else {
        // Ponsel: tanda yang dibatalkan (Batal/✕) tidak boleh terbawa ke
        // pembukaan berikutnya — mulai lagi dari nilai yang benar-benar berlaku.
        if (isMobile.value) {
            selectedDate.value = typeof props.modelValue === 'string' ? props.modelValue : '';
        }
        if (props.type === 'month') {
            viewMode.value = 'months';
            if (props.modelValue && typeof props.modelValue === 'string') {
                const parts = props.modelValue.split('-');
                if (parts.length >= 2) {
                    const y = parseInt(parts[0], 10);
                    const m = parseInt(parts[1], 10) - 1;
                    if (!isNaN(y)) currentYear.value = y;
                    if (!isNaN(m) && m >= 0 && m <= 11) currentMonth.value = m;
                    selectedDate.value = props.modelValue;
                    inputValue.value = props.modelValue;
                }
            }
        } else if (props.modelValue && typeof props.modelValue === 'string') {
            const parts = props.modelValue.split('-');
            if (parts.length === 3) {
                currentYear.value = parseInt(parts[0], 10);
                currentMonth.value = parseInt(parts[1], 10) - 1;
                selectedDate.value = props.modelValue;
                inputValue.value = props.modelValue;
                viewMode.value = 'days';
            }
        } else if (props.type === 'birthdate' || props.yearFirst) {
            // Default to ~25 years ago for realistic birth date decade picker
            currentYear.value = now.getFullYear() - 25;
            decadeStartYear.value = Math.floor(currentYear.value / 12) * 12;
            viewMode.value = 'years';
        } else if (props.type === 'year') {
            openYearsView();
        } else {
            viewMode.value = props.defaultView || 'days';
        }
    }
}

function closePopover() {
    isOpen.value = false;
    isSelectingRange.value = false;
}

function confirmSelection() {
    if (isRangeMode.value) {
        const s = tempStart.value;
        const e = tempEnd.value || tempStart.value;
        const finalRange = s <= e ? [s, e] : [e, s];
        emit('update:modelValue', finalRange);
        emit('change', finalRange);
    } else if (isDateTimeMode.value) {
        const d = dtSelectedDate.value || formatYMD(now.getFullYear(), now.getMonth(), now.getDate());
        const timeStr = `${padZero(dtHour.value)}:${padZero(dtMinute.value)}:00`;
        const val = `${d} ${timeStr}`;
        emit('update:modelValue', val);
        emit('change', val);
    } else {
        const val = selectedDate.value || inputValue.value;
        inputValue.value = val;
        emit('update:modelValue', val);
        emit('change', val);
    }
    closePopover();
}

function handleClear() {
    if (isRangeMode.value || isDateTimeRangeMode.value) {
        tempStart.value = '';
        tempEnd.value = '';
        emit('update:modelValue', ['', '']);
        emit('change', ['', '']);
    } else {
        selectedDate.value = '';
        inputValue.value = '';
        emit('update:modelValue', '');
        emit('change', '');
    }
    emit('clear');
}

function handleInputFocus() {
    isFocused.value = true;
    // Di ponsel kalendernya HcisDialog, yang saat ditutup MENGEMBALIKAN fokus
    // ke input ini. Membuka dari fokus membuat Terapkan/✕/Batal langsung
    // membuka ulang dialognya — tak bisa ditutup. Ketukan sudah membukanya
    // lewat handleInputClick/handleTriggerClick.
    if (isMobile.value) return;
    if (!isOpen.value) openPopover();
}

function handleInputBlur() {
    isFocused.value = false;
}

function handleTextInput(e) {
    const val = e.target.value;
    if (val.length === 10) {
        selectedDate.value = val;
        emit('update:modelValue', val);
        emit('change', val);
    }
}

function handleKeyDown(e) {
    if (e.key === 'Enter') confirmSelection();
    else if (e.key === 'Escape') closePopover();
}

function updatePopoverPosition() {
    if (!isOpen.value || !triggerRef.value || typeof window === 'undefined') return;
    const rect = triggerRef.value.getBoundingClientRect();
    const gap = 6;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    // Calculate initial target width
    let width = isRangeMode.value ? 620 : (isDateTimeMode.value ? 520 : 310);
    if (width > viewportWidth - 32) {
        width = viewportWidth - 32;
    }

    // Estimate or measure height
    let height = isRangeMode.value ? 390 : (isDateTimeMode.value ? 430 : 350);
    if (props.showPresets && activePresetsList.value && activePresetsList.value.length) {
        height += 48;
    }
    if (popoverRef.value) {
        const pRect = popoverRef.value.getBoundingClientRect();
        if (pRect.height > 0) height = pRect.height;
        if (pRect.width > 0) width = Math.min(pRect.width, viewportWidth - 32);
    }

    // ── 1. Horizontal Placement (Left Alignment vs Right Alignment vs Boundary Clamp) ──
    let left = rect.left;
    // If opening to the right overflows the viewport:
    if (left + width > viewportWidth - 16) {
        // Try aligning right edge of popover with right edge of trigger
        left = rect.right - width;
    }
    // If still overflowing right or off-screen to the left:
    if (left + width > viewportWidth - 16) {
        left = viewportWidth - width - 16;
    }
    if (left < 16) {
        left = 16;
    }

    // ── 2. Vertical Placement (Smart Flip Above/Below) ──
    const spaceBelow = viewportHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    let top = rect.bottom + gap;

    // If not enough space below AND there is more space above, open ABOVE the trigger
    if (spaceBelow < height && spaceAbove > spaceBelow) {
        top = Math.max(16, rect.top - height - gap);
    } else {
        // Otherwise open below, but clamp so it never overflows the bottom of the viewport
        if (top + height > viewportHeight - 16) {
            top = Math.max(16, viewportHeight - height - 16);
        }
    }

    popoverStyle.value = {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
        width: `${Math.round(width)}px`,
        maxWidth: 'calc(100vw - 32px)',
        maxHeight: 'calc(100vh - 32px)',
        overflowY: 'auto',
        zIndex: '999999',
    };
}

function checkMobile() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth <= 768 || window.matchMedia('(max-width: 768px)').matches;
    }
}

function handleGlobalClick(e) {
    if (!isOpen.value) return;
    // Di ponsel kalendernya HcisDialog yang di-teleport keluar wadah ini, jadi
    // setiap ketukan di dalamnya terbaca "di luar" dan menutupnya. Dialog
    // sudah punya penutupnya sendiri (✕, Batal, tirai, Esc).
    if (isMobile.value) return;
    const target = e.target;
    if (!target) return;

    // 1. Check if click is inside container (including SVG / detached elements via composedPath or closest)
    if (containerRef.value) {
        if (containerRef.value === target || containerRef.value.contains(target)) return;
        if (e.composedPath && e.composedPath().includes(containerRef.value)) return;
        if (target.closest && (target.closest('.hcis-date-picker') === containerRef.value || target.closest('.hcis-date-picker__trigger'))) return;
    }

    // 2. Check if click is inside popover (including composedPath or closest)
    if (popoverRef.value) {
        if (popoverRef.value === target || popoverRef.value.contains(target)) return;
        if (e.composedPath && e.composedPath().includes(popoverRef.value)) return;
        if (target.closest && target.closest('.hcis-date-picker__popover')) return;
    }

    // 3. Prevent closing if the clicked node was disconnected during Vue reactive re-render
    if (!target.isConnected) {
        return;
    }

    closePopover();
}

watch(
    () => props.modelValue,
    (val) => {
        if (!val) {
            selectedDate.value = '';
            inputValue.value = '';
        } else if (typeof val === 'string') {
            inputValue.value = val;
            selectedDate.value = val;
            if (props.type === 'month' && val.includes('-')) {
                const parts = val.split('-');
                if (parts.length >= 2) {
                    const y = parseInt(parts[0], 10);
                    const m = parseInt(parts[1], 10) - 1;
                    if (!isNaN(y)) currentYear.value = y;
                    if (!isNaN(m) && m >= 0 && m <= 11) currentMonth.value = m;
                }
            }
        }
    },
    { immediate: true }
);

onMounted(() => {
    checkMobile();
    if (typeof window !== 'undefined') {
        window.addEventListener('click', handleGlobalClick);
        window.addEventListener('resize', () => { checkMobile(); updatePopoverPosition(); });
        window.addEventListener('scroll', updatePopoverPosition, true);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleGlobalClick);
        window.removeEventListener('resize', updatePopoverPosition);
        window.removeEventListener('scroll', updatePopoverPosition, true);
    }
});
</script>

<style scoped>
.hcis-date-picker {
    position: relative;
    display: inline-block;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

/* ── Sizes ───────────────────────────────────────────────────────────── */
.hcis-date-picker--size-sm .hcis-date-picker__trigger {
    height: 2.0rem;
    padding: 0 0.55rem;
    font-size: 0.8125rem;
    border-radius: 8px;
}

.hcis-date-picker--size-md .hcis-date-picker__trigger {
    height: 2.375rem;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    border-radius: var(--hcis-radius-md, 9px);
}

.hcis-date-picker--size-lg .hcis-date-picker__trigger {
    height: 2.75rem;
    padding: 0 1.0rem;
    font-size: 0.9375rem;
    border-radius: var(--hcis-radius-xl, 12px);
}

/* ── Trigger Shell ───────────────────────────────────────────────────── */
.hcis-date-picker__trigger {
    display: flex;
    align-items: center;
    width: 100%;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
    box-sizing: border-box;
}

.hcis-date-picker__trigger:hover:not(.hcis-date-picker__trigger--disabled) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 2px 8px -1px rgba(99, 102, 241, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.hcis-date-picker__trigger--focused,
.hcis-date-picker__trigger--open,
.hcis-date-picker--active .hcis-date-picker__trigger {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18), 0 2px 8px rgba(79, 70, 229, 0.08) !important;
    background: var(--hcis-white) !important;
}

.hcis-date-picker__trigger--disabled {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
}

.hcis-date-picker__icon {
    display: flex;
    align-items: center;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    margin-right: 0.5rem;
    transition: transform 180ms ease;
    flex-shrink: 0;
}

.hcis-date-picker:hover .hcis-date-picker__icon {
    transform: scale(1.08);
}

.hcis-date-picker__input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: inherit;
    font-weight: 550;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-variant-numeric: tabular-nums;
    min-width: 0;
}

.hcis-date-picker__input::placeholder {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-weight: 450;
}

/* ── Range Dual Pill Shell ───────────────────────────────────────────── */
.hcis-date-picker__range-inputs,
.hcis-date-picker__dt-inputs {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    gap: 0.35rem;
}

.hcis-trigger-pill,
.hcis-dt-pill {
    display: inline-flex;
    align-items: center;
    font-size: inherit;
    font-weight: 600;
    color: var(--hcis-slate-800);
    padding: 0.1rem 0.25rem;
    border-radius: 6px;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-trigger-pill.is-active,
.hcis-dt-pill.is-active {
    color: var(--hcis-indigo-600);
    background: rgba(238, 242, 255, 0.9);
}

.hcis-pill-text.is-placeholder,
.hcis-dt-pill-text.is-placeholder {
    color: var(--hcis-slate-400);
    font-weight: 450;
}

.hcis-range-arrow,
.hcis-dt-sep {
    display: flex;
    align-items: center;
    color: var(--hcis-slate-400);
    font-size: 0.7rem;
    padding: 0 0.1rem;
    flex-shrink: 0;
}

.hcis-trigger-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    background: rgba(238, 242, 255, 0.95);
    color: var(--hcis-indigo-700);
    font-size: 0.6875rem;
    font-weight: 700;
    margin-left: auto;
    white-space: nowrap;
    border: 1px solid rgba(199, 210, 254, 0.8);
    flex-shrink: 0;
}

.hcis-date-picker__clear,
.hcis-date-picker__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0 0.125rem;
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1), color 150ms ease;
    flex-shrink: 0;
}

.hcis-date-picker__arrow.is-open {
    transform: rotate(180deg);
    color: var(--hcis-indigo-600);
}

.hcis-date-picker__clear:hover {
    color: var(--hcis-red-600);
    transform: scale(1.1);
}

/* ── Popover Panel (Luxury Acrylic Crystal Surface) ─────────────────── */
.hcis-date-picker__popover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.9) inset,
        0 16px 44px -6px rgba(15, 23, 42, 0.12),
        0 32px 72px -12px rgba(15, 23, 42, 0.08);
    padding: 1.125rem;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Presets Bar */
.hcis-date-picker__presets {
    margin-bottom: 0.25rem;
    padding-bottom: 0.375rem;
}

.hcis-date-picker__presets-label {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.hcis-date-picker__chips {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
    margin-top: 0.375rem;
}

.hcis-date-picker__chip {
    padding: 0.25rem 0.625rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(241, 245, 249, 0.85);
    color: var(--hcis-slate-700);
    font-size: 0.725rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-date-picker__chip:hover {
    background: rgba(238, 242, 255, 0.95);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
    transform: translateY(-1px);
}

.hcis-date-picker__chip--active {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-800) 100%) !important;
    border-color: transparent !important;
    color: var(--hcis-white) !important;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}

/* Nav Header */
.hcis-date-picker__nav,
.hcis-calendar-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.25rem 0;
}

.hcis-date-picker__nav-group {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.hcis-date-picker__nav-btn,
.hcis-nav-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 8px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.9);
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: ease;
}

.hcis-date-picker__nav-btn:hover,
.hcis-nav-btn:hover {
    background: rgba(238, 242, 255, 0.95);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
    transform: scale(1.05);
}

.hcis-date-picker__current-month,
.hcis-current-month {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

.hcis-nav-spacer {
    width: 1.875rem;
    height: 1.875rem;
}

/* Weekdays */
.hcis-date-picker__weekdays,
.hcis-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    padding: 0.25rem 0;
}

.hcis-date-picker__weekday,
.hcis-weekday {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Days Grid */
.hcis-date-picker__days-grid,
.hcis-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.125rem;
}

.hcis-date-picker__day-cell,
.hcis-day-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 2.125rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-800);
    font-size: 0.8125rem;
    font-weight: 550;
    cursor: pointer;
    transition-property: var(--hcis-transition-props), border-radius, font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-date-picker__day-cell:hover:not(.hcis-date-picker__day-cell--disabled):not(.hcis-date-picker__day-cell--selected),
.hcis-day-cell:hover:not(.is-other-month):not(.is-start):not(.is-end) {
    background: rgba(238, 242, 255, 0.9);
    color: var(--hcis-indigo-700);
    border-color: rgba(199, 210, 254, 0.8);
}

.hcis-date-picker__day-cell--other-month,
.hcis-day-cell.is-other-month {
    color: var(--hcis-slate-400);
    opacity: 0.4;
}

.hcis-date-picker__day-cell--today,
.hcis-day-cell.is-today {
    font-weight: 800;
    color: var(--hcis-indigo-600);
    border-color: rgba(129, 140, 248, 0.6);
    background: rgba(238, 242, 255, 0.4);
}

.hcis-date-picker__day-cell--selected,
.hcis-day-cell.is-start,
.hcis-day-cell.is-end {
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%) !important;
    color: var(--hcis-white) !important;
    font-weight: 800;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35);
    border-color: transparent !important;
    opacity: 1 !important;
}

.hcis-day-cell.is-range {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95) 0%, rgba(224, 231, 255, 0.9) 100%) !important;
    color: var(--hcis-indigo-900) !important;
    font-weight: 650;
    border-radius: 0;
    opacity: 0.95;
}

/* Dual Side-by-Side Calendars */
.hcis-dual-calendars {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}

.hcis-calendar-panel {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Combined Side-by-Side View (Calendar + Time) */
.hcis-dt-combined-view {
    display: grid;
    grid-template-columns: 1fr 12.5rem;
    gap: 1.25rem;
}

.hcis-dt-calendar-side {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.hcis-dt-time-side {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-left: 1px solid rgba(226, 232, 240, 0.9);
    padding-left: 1.125rem;
}

.hcis-time-side-header {
    font-size: 0.775rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    padding-bottom: 0.375rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-time-selectors {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.hcis-time-col {
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 10px;
    overflow: hidden;
    background: rgba(248, 250, 252, 0.6);
}

.hcis-time-col-header {
    background: rgba(241, 245, 249, 0.9);
    padding: 0.3125rem;
    text-align: center;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-time-col-list {
    /* Wadah posisi butir, supaya offsetTop butir terukur dari daftar ini. */
    position: relative;
    max-height: 180px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    padding: 0.25rem;
    gap: 0.15rem;
    scrollbar-width: thin;
}

.hcis-time-item {
    padding: 0.35rem 0.25rem;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--hcis-slate-700);
    font-size: 0.775rem;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-time-item:hover:not(.is-selected) {
    background: rgba(238, 242, 255, 0.9);
    color: var(--hcis-indigo-700);
}

.hcis-time-item.is-selected {
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%) !important;
    color: var(--hcis-white) !important;
    font-weight: 800;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);
}

/* ── Footer ──────────────────────────────────────────────────────────── */
.hcis-date-picker__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.625rem;
    border-top: 1px solid rgba(226, 232, 240, 0.9);
    gap: 0.75rem;
}

.hcis-date-picker__footer--dialog {
    border-top: none;
    padding-top: 0;
    width: 100%;
}

.hcis-date-picker__btn-today {
    display: inline-flex;
    align-items: center;
    border: none;
    background: transparent;
    color: var(--hcis-indigo-600);
    font-size: 0.775rem;
    font-weight: 700;
    cursor: pointer;
    transition: color 150ms ease, transform 150ms ease;
}

.hcis-date-picker__btn-today:hover {
    color: var(--hcis-indigo-800);
    transform: translateX(2px);
}

.hcis-range-readout,
.hcis-dt-readout {
    display: flex;
    align-items: center;
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
}

.hcis-readout-text,
.hcis-dt-readout-text {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.775rem;
    color: var(--hcis-slate-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-date-val {
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.hcis-date-sep {
    font-size: 0.725rem;
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.hcis-readout-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.175rem 0.5rem;
    border-radius: 6px;
    background: rgba(238, 242, 255, 0.95);
    color: var(--hcis-indigo-700);
    font-size: 0.725rem;
    font-weight: 700;
    white-space: nowrap;
    margin-left: 0.25rem;
    border: 1px solid rgba(199, 210, 254, 0.8);
}

.hcis-readout-placeholder {
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
    white-space: nowrap;
}

.hcis-date-picker__footer-actions {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
}

.hcis-date-picker__btn-cancel,
.hcis-date-picker__btn-confirm {
    padding: 0.375rem 0.8125rem;
    border-radius: 8px;
    font-size: 0.775rem;
    font-weight: 600;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: ease;
    white-space: nowrap;
}

.hcis-date-picker__btn-cancel {
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.9);
    color: var(--hcis-slate-500);
}

.hcis-date-picker__btn-cancel:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
}

.hcis-date-picker__btn-confirm {
    border: none;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-800) 100%);
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.hcis-date-picker__btn-confirm:hover:not(:disabled) {
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
    transform: translateY(-1px);
}

.hcis-date-picker__btn-confirm:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.hcis-mobile-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* ── Month & Year Picker Views (Drilldown / Fast Birthdate Selector) ── */
.hcis-date-picker__header-btn {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 0.15rem 0.4rem;
    border-radius: var(--hcis-radius-sm, 6px);
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-weight: 800;
    font-size: 0.875rem;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-date-picker__header-btn:hover,
.hcis-date-picker__header-btn--active {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.25);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-date-picker__months-grid,
.hcis-date-picker__years-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    padding: 8px 4px;
    min-height: 200px;
    align-content: center;
}

.hcis-date-picker__grid-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 4px;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-size: 0.8125rem;
    font-weight: 700;
    cursor: pointer;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-date-picker__grid-cell:hover:not(:disabled) {
    background: var(--hcis-indigo-50);
    border-color: rgba(99, 102, 241, 0.45);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
}

.hcis-date-picker__grid-cell.is-selected {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%) !important;
    border-color: var(--hcis-indigo-700) !important;
    color: var(--hcis-white) !important;
    font-weight: 800 !important;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.35) !important;
}

.hcis-date-picker__grid-cell:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    background: var(--hcis-slate-100);
    border-color: transparent;
}
</style>
