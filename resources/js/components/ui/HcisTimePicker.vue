<template>
    <div
        ref="containerRef"
        class="hcis-time-picker"
        :class="[
            `hcis-time-picker--size-${size}`,
            {
                'hcis-time-picker--active': isOpen || isFocused,
                'hcis-time-picker--disabled': disabled,
                'hcis-time-picker--has-value': !!modelValue || !!inputValue,
            }
        ]"
    >
        <!-- Input Trigger Shell (Direct Typing Supported) -->
        <div
            ref="triggerRef"
            class="hcis-time-picker__trigger"
            :class="{
                'hcis-time-picker__trigger--focused': isFocused || isOpen,
                'hcis-time-picker__trigger--disabled': disabled,
                'hcis-time-picker__trigger--open': isOpen,
            }"
            @click="handleTriggerClick"
        >
            <span class="hcis-time-picker__icon">
                <HcisIcon name="clock-history" size="xs" />
            </span>

            <input
                ref="inputRef"
                v-model="inputValue"
                type="text"
                class="hcis-time-picker__input"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                maxlength="8"
                aria-label="Isian Jam"
                @focus="handleInputFocus"
                @blur="handleInputBlur"
                @input="handleTextInput"
                @keydown="handleKeyDown"
            />

            <button
                v-if="clearable && (modelValue || inputValue) && !disabled"
                type="button"
                class="hcis-time-picker__clear"
                title="Hapus jam"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span v-else class="hcis-time-picker__arrow" :class="{ 'is-open': isOpen }" @click.stop="togglePopover">
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- Popover Panel Pemilih Jam (Teleported to Body) -->
        <Teleport to="body" :disabled="teleportTo === false">
            <Transition name="hcis-time-popover">
                <div
                    v-if="isOpen"
                    ref="popoverRef"
                    class="hcis-time-picker__popover"
                    :style="popoverStyle"
                    role="dialog"
                    aria-label="Pemilih Jam"
                >
                    <!-- Quick Preset Chips -->
                    <div v-if="showPresets && presets && presets.length" class="hcis-time-picker__presets">
                        <div class="hcis-date-picker__presets-header">
                            <span class="hcis-date-picker__presets-label">
                                <HcisIcon name="lightning-charge-fill" size="2xs" class="me-1" /> Preset Jam
                            </span>
                        </div>
                        <div class="hcis-date-picker__chips">
                            <button
                                v-for="p in presets"
                                :key="p.value"
                                type="button"
                                class="hcis-date-picker__chip"
                                :class="{ 'hcis-date-picker__chip--active': inputValue === p.value || formattedString === p.value }"
                                @click="selectPreset(p.value)"
                            >
                                <HcisIcon v-if="inputValue === p.value || formattedString === p.value" name="check-lg" size="2xs" class="me-1" />
                                {{ p.label || p.value }}
                            </button>
                        </div>
                    </div>

                    <!-- Quick Step Adjustment (-15m, -5m, +5m, +15m) -->
                    <div class="hcis-time-picker__quick-adjust">
                        <button type="button" class="hcis-time-picker__step-btn" title="Kurang 1 Jam" @click="adjustHours(-1)">-1j</button>
                        <button type="button" class="hcis-time-picker__step-btn" title="Kurang 15 Menit" @click="adjustMinutes(-15)">-15m</button>
                        <button type="button" class="hcis-time-picker__step-btn" title="Kurang 5 Menit" @click="adjustMinutes(-5)">-5m</button>
                        <span class="hcis-time-picker__adjust-divider" />
                        <button type="button" class="hcis-time-picker__step-btn" title="Tambah 5 Menit" @click="adjustMinutes(5)">+5m</button>
                        <button type="button" class="hcis-time-picker__step-btn" title="Tambah 15 Menit" @click="adjustMinutes(15)">+15m</button>
                        <button type="button" class="hcis-time-picker__step-btn" title="Tambah 1 Jam" @click="adjustHours(1)">+1j</button>
                    </div>

                    <!-- Column Spinners -->
                    <div class="hcis-time-picker__spinners">
                        <!-- Column Jam -->
                        <div class="hcis-time-picker__column">
                            <div class="hcis-time-picker__col-header">Jam</div>
                            <div ref="hoursListRef" class="hcis-time-picker__list">
                                <button
                                    v-for="h in 24"
                                    :key="h - 1"
                                    type="button"
                                    class="hcis-time-picker__item"
                                    :class="{ 'hcis-time-picker__item--selected': selectedHour === (h - 1) }"
                                    @click="setHour(h - 1)"
                                >
                                    {{ padZero(h - 1) }}
                                </button>
                            </div>
                        </div>

                        <div class="hcis-time-picker__col-separator">:</div>

                        <!-- Column Menit -->
                        <div class="hcis-time-picker__column">
                            <div class="hcis-time-picker__col-header">Menit</div>
                            <div ref="minutesListRef" class="hcis-time-picker__list">
                                <button
                                    v-for="m in minuteItems"
                                    :key="m"
                                    type="button"
                                    class="hcis-time-picker__item"
                                    :class="{ 'hcis-time-picker__item--selected': selectedMinute === m }"
                                    @click="setMinute(m)"
                                >
                                    {{ padZero(m) }}
                                </button>
                            </div>
                        </div>

                        <!-- Column Detik (Optional) -->
                        <template v-if="useSeconds">
                            <div class="hcis-time-picker__col-separator">:</div>

                            <!-- Column Detik -->
                            <div class="hcis-time-picker__column">
                                <div class="hcis-time-picker__col-header">Detik</div>
                                <div ref="secondsListRef" class="hcis-time-picker__list">
                                    <button
                                        v-for="s in 60"
                                        :key="s - 1"
                                        type="button"
                                        class="hcis-time-picker__item"
                                        :class="{ 'hcis-time-picker__item--selected': selectedSecond === (s - 1) }"
                                        @click="setSecond(s - 1)"
                                    >
                                        {{ padZero(s - 1) }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer Action Bar -->
                    <div class="hcis-time-picker__footer">
                        <button type="button" class="hcis-time-picker__btn-now" @click="selectNow">
                            Sekarang
                        </button>
                        <button type="button" class="hcis-time-picker__btn-confirm" @click="confirmSelection">
                            OK
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisTimePicker' });

const props = defineProps({
    modelValue: { type: [String, Date], default: '' },
    placeholder: { type: String, default: 'HH:mm' },
    format: { type: String, default: 'HH:mm' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    useSeconds: { type: Boolean, default: false },
    minuteStep: { type: Number, default: 1 },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    teleportTo: { type: [String, Boolean], default: 'body' },
    showPresets: { type: Boolean, default: true },
    presets: {
        type: Array,
        default: () => [
            { label: '08:00 (Masuk Pagi)', value: '08:00' },
            { label: '09:00 (Masuk Normal)', value: '09:00' },
            { label: '12:00 (Istirahat)', value: '12:00' },
            { label: '17:00 (Pulang Normal)', value: '17:00' },
            { label: '18:00 (Mulai Lembur)', value: '18:00' },
        ],
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear']);

const containerRef = ref(null);
const triggerRef = ref(null);
const inputRef = ref(null);
const popoverRef = ref(null);
const hoursListRef = ref(null);
const minutesListRef = ref(null);
const secondsListRef = ref(null);
const isOpen = ref(false);
const isFocused = ref(false);
const inputValue = ref('');

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    width: '22rem',
    zIndex: '99999',
});

const selectedHour = ref(8);
const selectedMinute = ref(0);
const selectedSecond = ref(0);

const padZero = (n) => String(n).padStart(2, '0');

const minuteItems = computed(() => {
    const items = [];
    const step = props.minuteStep > 0 ? props.minuteStep : 1;
    for (let i = 0; i < 60; i += step) {
        items.push(i);
    }
    return items;
});

const parseValue = (val) => {
    if (!val) {
        const now = new Date();
        selectedHour.value = now.getHours();
        selectedMinute.value = Math.floor(now.getMinutes() / props.minuteStep) * props.minuteStep;
        selectedSecond.value = now.getSeconds();
        inputValue.value = '';
        return;
    }

    if (val instanceof Date) {
        selectedHour.value = val.getHours();
        selectedMinute.value = val.getMinutes();
        selectedSecond.value = val.getSeconds();
        inputValue.value = formattedString.value;
        return;
    }

    if (typeof val === 'string') {
        const parts = val.split(':');
        if (parts.length >= 2) {
            selectedHour.value = Math.min(23, Math.max(0, parseInt(parts[0], 10) || 0));
            selectedMinute.value = Math.min(59, Math.max(0, parseInt(parts[1], 10) || 0));
            if (parts[2]) {
                selectedSecond.value = Math.min(59, Math.max(0, parseInt(parts[2], 10) || 0));
            }
        }
        inputValue.value = val;
    }
};

watch(() => props.modelValue, parseValue, { immediate: true });

const formattedString = computed(() => {
    const hh = padZero(selectedHour.value);
    const mm = padZero(selectedMinute.value);
    const ss = padZero(selectedSecond.value);
    return props.useSeconds ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
});

const updatePopoverPosition = () => {
    if (!isOpen.value || (!triggerRef.value && !containerRef.value)) return;
    const target = triggerRef.value || containerRef.value;
    const rect = target.getBoundingClientRect();
    const popoverWidth = 352; // 22rem = 352px
    const popoverHeight = 310;

    let top = rect.bottom + 6;
    if (rect.bottom + popoverHeight > window.innerHeight && rect.top > popoverHeight) {
        top = rect.top - popoverHeight - 6;
    }

    let left = rect.left;
    if (left + popoverWidth > window.innerWidth - 12) {
        left = Math.max(12, window.innerWidth - popoverWidth - 12);
    }

    popoverStyle.value = {
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
        width: '22rem',
        zIndex: '99999',
    };
};

const openPopover = () => {
    if (props.disabled) return;
    parseValue(props.modelValue);
    isOpen.value = true;
    nextTick(() => {
        updatePopoverPosition();
        scrollToSelected();
    });
};

const closePopover = () => {
    isOpen.value = false;
};

const togglePopover = () => {
    if (isOpen.value) {
        closePopover();
    } else {
        openPopover();
    }
};

const handleTriggerClick = () => {
    if (props.disabled) return;
    if (inputRef.value) {
        inputRef.value.focus();
    }
    openPopover();
};

const handleInputFocus = () => {
    isFocused.value = true;
    openPopover();
};

const handleInputBlur = () => {
    isFocused.value = false;
    validateAndFormatInput();
};

const handleTextInput = (e) => {
    let raw = e.target.value.replace(/[^0-9:]/g, '');

    // Auto-colon insertion if user types 2 digits without colon e.g. "08" -> "08:"
    if (raw.length === 2 && !raw.includes(':')) {
        raw = raw + ':';
    }

    inputValue.value = raw;

    // Realtime parse and update spinners if valid format e.g. "08:30"
    const parts = raw.split(':');
    if (parts.length >= 2 && parts[0].length === 2 && parts[1].length === 2) {
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        if (!isNaN(h) && h >= 0 && h <= 23 && !isNaN(m) && m >= 0 && m <= 59) {
            selectedHour.value = h;
            selectedMinute.value = m;
            emitValue();
            scrollToSelected();
        }
    }
};

const validateAndFormatInput = () => {
    if (!inputValue.value) {
        emit('update:modelValue', '');
        emit('change', '');
        return;
    }

    const parts = inputValue.value.split(':');
    let h = parseInt(parts[0], 10) || 0;
    let m = parseInt(parts[1], 10) || 0;

    h = Math.min(23, Math.max(0, h));
    m = Math.min(59, Math.max(0, m));

    selectedHour.value = h;
    selectedMinute.value = m;
    const finalVal = formattedString.value;
    inputValue.value = finalVal;
    emitValue();
};

const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        validateAndFormatInput();
        closePopover();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closePopover();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const cursor = inputRef.value ? inputRef.value.selectionStart : 0;
        if (cursor <= 2) {
            adjustHours(1);
        } else {
            adjustMinutes(1);
        }
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        const cursor = inputRef.value ? inputRef.value.selectionStart : 0;
        if (cursor <= 2) {
            adjustHours(-1);
        } else {
            adjustMinutes(-1);
        }
    }
};

const scrollToSelected = () => {
    nextTick(() => {
        const scrollCol = (el, selectedVal, step = 1) => {
            if (!el) return;
            const targetIndex = Math.floor(selectedVal / step);
            const childBtn = el.children[targetIndex];
            if (childBtn) {
                const childOffsetTop = (childBtn.offsetParent === el)
                    ? childBtn.offsetTop
                    : (childBtn.offsetTop - el.offsetTop);
                const targetTop = childOffsetTop - (el.clientHeight / 2) + (childBtn.offsetHeight / 2);
                if (typeof el.scrollTo === 'function') {
                    el.scrollTo({
                        top: targetTop,
                        behavior: 'smooth',
                    });
                } else {
                    el.scrollTop = targetTop;
                }
            }
        };
        scrollCol(hoursListRef.value, selectedHour.value);
        scrollCol(minutesListRef.value, selectedMinute.value, props.minuteStep);
        if (props.useSeconds) {
            scrollCol(secondsListRef.value, selectedSecond.value);
        }
    });
};

const emitValue = () => {
    const val = formattedString.value;
    inputValue.value = val;
    emit('update:modelValue', val);
    emit('change', val);
};

const setHour = (h) => {
    selectedHour.value = h;
    emitValue();
    scrollToSelected();
};

const setMinute = (m) => {
    selectedMinute.value = m;
    emitValue();
    scrollToSelected();
};

const setSecond = (s) => {
    selectedSecond.value = s;
    emitValue();
    scrollToSelected();
};

const adjustHours = (delta) => {
    let newH = (selectedHour.value + delta) % 24;
    if (newH < 0) newH += 24;
    selectedHour.value = newH;
    emitValue();
    scrollToSelected();
};

const adjustMinutes = (delta) => {
    let newM = selectedMinute.value + delta;
    if (newM >= 60) {
        adjustHours(Math.floor(newM / 60));
        newM %= 60;
    } else if (newM < 0) {
        adjustHours(Math.floor(newM / 60));
        newM = (newM % 60 + 60) % 60;
    }
    selectedMinute.value = newM;
    emitValue();
    scrollToSelected();
};

const selectPreset = (valStr) => {
    parseValue(valStr);
    confirmSelection();
};

const setNow = () => {
    const now = new Date();
    selectedHour.value = now.getHours();
    selectedMinute.value = now.getMinutes();
    selectedSecond.value = now.getSeconds();
    confirmSelection();
};

const confirmSelection = () => {
    emitValue();
    closePopover();
};

const handleClear = () => {
    inputValue.value = '';
    emit('update:modelValue', '');
    emit('change', '');
    emit('clear');
};

const handleClickOutside = (e) => {
    if (!isOpen.value) return;
    const isClickInsideContainer = containerRef.value && containerRef.value.contains(e.target);
    const isClickInsidePopover = popoverRef.value && popoverRef.value.contains(e.target);
    if (!isClickInsideContainer && !isClickInsidePopover) {
        closePopover();
    }
};

watch(isOpen, (val) => {
    if (val) {
        window.addEventListener('scroll', updatePopoverPosition, true);
        window.addEventListener('resize', updatePopoverPosition);
    } else {
        window.removeEventListener('scroll', updatePopoverPosition, true);
        window.removeEventListener('resize', updatePopoverPosition);
    }
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', updatePopoverPosition, true);
    window.removeEventListener('resize', updatePopoverPosition);
});
</script>

<style>
.hcis-time-picker {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 18rem;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-time-picker--disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* Trigger Input Box Shell */
/* Sizes */
.hcis-time-picker--size-sm .hcis-time-picker__trigger {
    height: 2.0rem;
    padding: 0 0.55rem;
    font-size: 0.8125rem;
    border-radius: 8px;
}

.hcis-time-picker--size-md .hcis-time-picker__trigger {
    height: 2.375rem;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    border-radius: var(--hcis-radius-md, 9px);
}

.hcis-time-picker--size-lg .hcis-time-picker__trigger {
    height: 2.75rem;
    padding: 0 1.0rem;
    font-size: 0.9375rem;
    border-radius: var(--hcis-radius-xl, 12px);
}

.hcis-time-picker__trigger {
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
}

.hcis-time-picker__trigger:hover:not(.hcis-time-picker--disabled) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 2px 8px -1px rgba(99, 102, 241, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.hcis-time-picker__trigger--focused,
.hcis-time-picker__trigger--open,
.hcis-time-picker--active .hcis-time-picker__trigger {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18), 0 2px 8px rgba(79, 70, 229, 0.08) !important;
    background: var(--hcis-white) !important;
}

.hcis-time-picker--disabled .hcis-time-picker__trigger {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
}

.hcis-time-picker--disabled .hcis-time-picker__input {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    -webkit-text-fill-color: var(--hcis-slate-500, var(--hcis-slate-500));
    cursor: not-allowed;
}

.hcis-time-picker__icon {
    display: flex;
    align-items: center;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    margin-right: 0.5rem;
    transition: transform 180ms ease;
}

.hcis-time-picker:hover .hcis-time-picker__icon {
    transform: scale(1.08);
}

.hcis-time-picker__input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: inherit;
    font-weight: 550;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-variant-numeric: tabular-nums;
}

.hcis-time-picker__input::placeholder {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-weight: 450;
}

.hcis-time-picker__clear,
.hcis-time-picker__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0 0.125rem;
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1), color 150ms ease;
}

.hcis-time-picker__arrow.is-open,
.hcis-time-picker--active .hcis-time-picker__arrow {
    transform: rotate(180deg);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-time-picker__clear:hover {
    color: var(--hcis-danger-600, var(--hcis-red-600));
    transform: scale(1.1);
}

/* ── Popover Panel (Luxury Acrylic Crystal Surface) ─────────────────── */
.hcis-time-picker__popover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.9) inset,
        0 16px 40px -6px rgba(15, 23, 42, 0.12),
        0 28px 64px -12px rgba(15, 23, 42, 0.08);
    padding: 1.125rem;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Presets Bar */
.hcis-time-picker__presets {
    margin-bottom: 0.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-time-picker__presets-label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.hcis-time-picker__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.hcis-time-picker__chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.25rem 0.625rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(241, 245, 249, 0.8);
    color: var(--hcis-slate-700);
    font-size: 0.725rem;
    font-weight: 600;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-time-picker__chip:hover {
    background: rgba(238, 242, 255, 0.95);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
    transform: translateY(-1px);
}

.hcis-time-picker__chip--active {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-800) 100%) !important;
    border-color: transparent !important;
    color: var(--hcis-white) !important;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}

/* Quick Step Adjustment Bar */
.hcis-time-picker__quick-adjust {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px;
    background: rgba(241, 245, 249, 0.85);
    border-radius: 8px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    margin-bottom: 0.25rem;
}

.hcis-time-picker__step-btn {
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--hcis-slate-600);
    font-size: 0.725rem;
    font-weight: 700;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-time-picker__step-btn:hover {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.hcis-time-picker__adjust-divider {
    width: 1px;
    height: 12px;
    background: rgba(203, 213, 225, 0.8);
    margin: 0 2px;
}

/* Spinners Columns */
.hcis-time-picker__spinners {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 12rem;
    gap: 6px;
    padding: 6px 4px;
    background: rgba(248, 250, 252, 0.85);
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-time-picker__column {
    flex: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.hcis-time-picker__col-header {
    text-align: center;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    padding: 2px 0 6px 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.hcis-time-picker__list {
    position: relative;
    flex: 1;
    overflow-y: auto;
    scrollbar-width: thin;
    padding: 4.5rem 6px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    scroll-behavior: smooth;
    -webkit-mask-image: linear-gradient(to bottom, transparent, black 15%, black 85%, transparent);
    mask-image: linear-gradient(to bottom, transparent, black 15%, black 85%, transparent);
}

.hcis-time-picker__col-separator {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--hcis-indigo-400);
    margin-top: 1.25rem;
}

.hcis-time-picker__item {
    height: 2.125rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--hcis-slate-700);
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
    flex-shrink: 0;
}

.hcis-time-picker__item:not(.hcis-time-picker__item--selected):hover {
    background-color: rgba(238, 242, 255, 0.9) !important;
    color: var(--hcis-indigo-700) !important;
}

.hcis-time-picker__item--selected,
.hcis-time-picker__item--selected:hover,
.hcis-time-picker__item--selected:focus,
.hcis-time-picker__item--selected:active {
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%) !important;
    color: var(--hcis-white) !important;
    font-weight: 800 !important;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35) !important;
}

/* Footer Popover */
.hcis-time-picker__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.5rem;
    padding-top: 0.625rem;
    border-top: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-time-picker__btn-now {
    border: none;
    background: transparent;
    color: var(--hcis-indigo-600);
    font-size: 0.775rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 6px;
    border-radius: 6px;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-time-picker__btn-now:hover {
    background: rgba(238, 242, 255, 0.9);
    color: var(--hcis-indigo-800);
    transform: translateX(2px);
}

.hcis-time-picker__footer-actions {
    display: flex;
    gap: 6px;
}

.hcis-time-picker__btn-cancel,
.hcis-time-picker__btn-confirm {
    padding: 0.375rem 0.8125rem;
    border-radius: 8px;
    font-size: 0.775rem;
    font-weight: 600;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: ease;
}

.hcis-time-picker__btn-cancel {
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.9);
    color: var(--hcis-slate-500);
}

.hcis-time-picker__btn-cancel:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
}

.hcis-time-picker__btn-confirm {
    border: none;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-800) 100%);
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.hcis-time-picker__btn-confirm:hover {
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
    transform: translateY(-1px);
}

/* Transition Popover */
.hcis-time-popover-enter-active {
    transition: opacity 180ms ease, transform 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-time-popover-leave-active {
    transition: opacity 140ms ease, transform 140ms cubic-bezier(0.4, 0, 1, 1);
}

.hcis-time-popover-enter-from,
.hcis-time-popover-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.96);
}
</style>
