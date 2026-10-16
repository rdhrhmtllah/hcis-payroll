<template>
    <HcisField
        :id="id"
        :label="label"
        :hint="hint"
        :error="error"
        :success="success"
        :required="required"
        :disabled="disabled"
        :size="size"
        :variant="variant"
    >
        <div
            ref="containerRef"
            class="hcis-phone-input__control"
            :class="[
                `hcis-phone-input__control--${size}`,
                {
                    'hcis-phone-input__control--disabled': disabled,
                    'hcis-phone-input__control--open': isOpen,
                },
            ]"
            role="group"
            :aria-label="ariaLabel || label || 'Nomor HP atau telepon'"
        >
            <!-- Country Selector Trigger Button -->
            <div class="hcis-phone-input__prefix">
                <button
                    ref="triggerBtnRef"
                    type="button"
                    class="hcis-phone-prefix-btn"
                    :disabled="disabled"
                    :aria-expanded="isOpen"
                    :aria-label="`Pilih kode negara telepon, saat ini ${activeCountry.name} (${selectedCountryCode})`"
                    @click.stop="toggleDropdown"
                    @keydown.escape="closeDropdown"
                >
                    <span class="hcis-phone-flag-box" aria-hidden="true" v-html="activeCountry.svg" />
                    <span class="hcis-phone-dial-code font-tabular fw-bold">{{ selectedCountryCode }}</span>
                    <HcisIcon name="chevron-down" size="2xs" class="hcis-phone-chevron" :class="{ 'is-open': isOpen }" />
                </button>
            </div>

            <!-- Visual Sub-Pixel Divider -->
            <span class="hcis-phone-input__divider" aria-hidden="true" />

            <!-- Phone Number Input & Counter -->
            <div class="hcis-phone-input__body">
                <input
                    :id="id"
                    ref="inputRef"
                    v-model="nationalNumber"
                    class="hcis-phone-input__number font-tabular"
                    type="tel"
                    inputmode="numeric"
                    autocomplete="tel-national"
                    :placeholder="resolvedPlaceholder"
                    :disabled="disabled"
                    :readonly="readonly"
                    :maxlength="resolvedMaxDigits"
                    :aria-label="numberAriaLabel || label"
                    @input="handleInput"
                    @focus="closeDropdown"
                />
                <span
                    v-if="showCounter && nationalNumber"
                    class="hcis-phone-input__counter font-tabular"
                    :title="`Jumlah angka: ${nationalNumber.length} dari maksimal ${resolvedMaxDigits}`"
                >
                    {{ nationalNumber.length }}/{{ resolvedMaxDigits }}
                </span>
            </div>
        </div>

        <!-- Teleported Country Popover (Immune to overflow:hidden and z-index issues) -->
        <Teleport to="body">
            <Transition name="hcis-phone-popover">
                <div
                    v-if="isOpen"
                    ref="dropdownRef"
                    class="hcis-phone-dropdown shadow-xl"
                    :style="popoverStyle"
                    role="listbox"
                    @click.stop
                >
                    <!-- Search Bar within Dropdown -->
                    <div class="hcis-phone-search-box">
                        <HcisIcon name="search" size="xs" class="text-slate-400 flex-shrink-0" />
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            class="hcis-phone-search-input"
                            placeholder="Cari negara atau kode (+62, dll)..."
                            @keydown.escape="closeDropdown"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="hcis-phone-clear-search"
                            @click="searchQuery = ''"
                        >
                            <HcisIcon name="x-circle-fill" size="2xs" />
                        </button>
                    </div>

                    <!-- Countries Scrollable List -->
                    <div class="hcis-phone-list">
                        <div
                            v-for="c in filteredCountries"
                            :key="c.code"
                            class="hcis-phone-option"
                            :class="{ 'is-active': c.code === selectedCountryCode }"
                            role="option"
                            :aria-selected="c.code === selectedCountryCode"
                            @click="selectCountry(c)"
                        >
                            <div class="hcis-phone-country-info">
                                <span class="hcis-phone-flag-box flex-shrink-0" v-html="c.svg" />
                                <span class="hcis-phone-country-name text-truncate">{{ c.name }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                <span class="hcis-phone-country-code font-tabular font-mono fw-bold">{{ c.code }}</span>
                                <HcisIcon
                                    v-if="c.code === selectedCountryCode"
                                    name="check2"
                                    size="xs"
                                    class="text-primary"
                                />
                            </div>
                        </div>

                        <div v-if="filteredCountries.length === 0" class="hcis-phone-empty text-muted text-xs p-3 text-center">
                            Tidak ditemukan negara yang cocok.
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </HcisField>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import HcisField from './HcisField.vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisPhoneInput' });

// Comprehensive Vector SVG Flags for crisp, OS-independent rendering
const COUNTRIES = [
    {
        code: '+62',
        country: 'ID',
        name: 'Indonesia',
        maxDigits: 13,
        placeholder: '812 3456 7890',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="7" fill="#e11d48"/><rect y="7" width="20" height="7" fill="#ffffff"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+60',
        country: 'MY',
        name: 'Malaysia',
        maxDigits: 11,
        placeholder: '12 345 6789',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#ffffff"/><g fill="#dc2626"><rect width="20" height="1"/><rect y="2" width="20" height="1"/><rect y="4" width="20" height="1"/><rect y="6" width="20" height="1"/><rect y="8" width="20" height="1"/><rect y="10" width="20" height="1"/><rect y="12" width="20" height="1"/></g><rect width="10" height="7" fill="#1e3a8a"/><path d="M5.5 1.5a2 2 0 1 0 0 4 2.5 2.5 0 1 1 0-4z" fill="#facc15"/><polygon points="7,3.5 7.6,2.2 8.8,3 7.8,4 8.5,5.2 7.2,4.8 6.5,6 6.2,4.6 4.9,4.7 5.8,3.6" fill="#facc15"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+65',
        country: 'SG',
        name: 'Singapura',
        maxDigits: 9,
        placeholder: '8123 4567',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="7" fill="#e11d48"/><rect y="7" width="20" height="7" fill="#ffffff"/><path d="M4.5 1.5a2 2 0 1 0 0 4 2.5 2.5 0 1 1 0-4z" fill="#ffffff"/><circle cx="6" cy="2.2" r="0.4" fill="#fff"/><circle cx="6.8" cy="3" r="0.4" fill="#fff"/><circle cx="6.5" cy="4" r="0.4" fill="#fff"/><circle cx="5.5" cy="4.2" r="0.4" fill="#fff"/><circle cx="5.2" cy="3" r="0.4" fill="#fff"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+66',
        country: 'TH',
        name: 'Thailand',
        maxDigits: 10,
        placeholder: '81 234 5678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#dc2626"/><rect y="2.33" width="20" height="9.34" fill="#ffffff"/><rect y="4.66" width="20" height="4.68" fill="#1e3a8a"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+63',
        country: 'PH',
        name: 'Filipina',
        maxDigits: 11,
        placeholder: '912 345 6789',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="7" fill="#2563eb"/><rect y="7" width="20" height="7" fill="#dc2626"/><polygon points="0,0 10,7 0,14" fill="#ffffff"/><circle cx="3.2" cy="7" r="1.2" fill="#facc15"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+81',
        country: 'JP',
        name: 'Jepang',
        maxDigits: 11,
        placeholder: '90 1234 5678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#ffffff"/><circle cx="10" cy="7" r="4.2" fill="#be123c"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+82',
        country: 'KR',
        name: 'Korea Selatan',
        maxDigits: 11,
        placeholder: '10 1234 5678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#ffffff"/><circle cx="10" cy="7" r="3.2" fill="#dc2626"/><path d="M10 7a1.6 1.6 0 0 0 0 3.2 3.2 3.2 0 0 1 0-6.4 1.6 1.6 0 0 1 0 3.2z" fill="#2563eb"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+86',
        country: 'CN',
        name: 'Tiongkok',
        maxDigits: 12,
        placeholder: '138 1234 5678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#dc2626"/><polygon points="4,2.5 4.5,4 3.2,3 4.8,3 3.5,4" fill="#facc15"/><circle cx="7" cy="1.8" r="0.4" fill="#facc15"/><circle cx="8" cy="3" r="0.4" fill="#facc15"/><circle cx="8" cy="4.5" r="0.4" fill="#facc15"/><circle cx="7" cy="5.8" r="0.4" fill="#facc15"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+61',
        country: 'AU',
        name: 'Australia',
        maxDigits: 10,
        placeholder: '412 345 678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#1e3a8a"/><g><polygon points="0,0 8,5.6 0,5.6" fill="#dc2626"/><polygon points="0,0 8,0 8,5.6" fill="#ffffff"/><polygon points="0,2.4 8,2.4 8,3.2 0,3.2" fill="#dc2626"/><polygon points="3.6,0 4.4,0 4.4,5.6 3.6,5.6" fill="#dc2626"/></g><circle cx="4" cy="9.5" r="1.3" fill="#ffffff"/><circle cx="15" cy="3" r="0.5" fill="#ffffff"/><circle cx="17" cy="5" r="0.5" fill="#ffffff"/><circle cx="16" cy="8" r="0.5" fill="#ffffff"/><circle cx="13.5" cy="6" r="0.5" fill="#ffffff"/><circle cx="15" cy="11" r="0.8" fill="#ffffff"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+1',
        country: 'US',
        name: 'Amerika Serikat / Kanada',
        maxDigits: 10,
        placeholder: '202 555 0123',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#ffffff"/><g fill="#dc2626"><rect width="20" height="1.07"/><rect y="2.14" width="20" height="1.07"/><rect y="4.28" width="20" height="1.07"/><rect y="6.42" width="20" height="1.07"/><rect y="8.56" width="20" height="1.07"/><rect y="10.7" width="20" height="1.07"/><rect y="12.84" width="20" height="1.16"/></g><rect width="8" height="7.5" fill="#1e3a8a"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+44',
        country: 'GB',
        name: 'Inggris (UK)',
        maxDigits: 11,
        placeholder: '7911 123456',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#1e3a8a"/><polygon points="0,0 20,14 18,14 0,1.4" fill="#fff"/><polygon points="20,0 0,14 2,14 20,1.4" fill="#fff"/><polygon points="0,0 20,14 19,14 0,0.7" fill="#dc2626"/><polygon points="20,0 0,14 1,14 20,0.7" fill="#dc2626"/><rect x="8" width="4" height="14" fill="#fff"/><rect y="5" width="20" height="4" fill="#fff"/><rect x="9" width="2" height="14" fill="#dc2626"/><rect y="6" width="20" height="2" fill="#dc2626"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+966',
        country: 'SA',
        name: 'Arab Saudi',
        maxDigits: 10,
        placeholder: '50 123 4567',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="14" fill="#15803d"/><rect x="3.5" y="8" width="13" height="1.2" fill="#fff" rx="0.5"/><rect x="4.5" y="4.5" width="11" height="2" fill="#fff" opacity="0.85" rx="0.5"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+971',
        country: 'AE',
        name: 'Uni Emirat Arab',
        maxDigits: 10,
        placeholder: '50 123 4567',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="4.66" fill="#15803d"/><rect y="4.66" width="20" height="4.66" fill="#ffffff"/><rect y="9.32" width="20" height="4.68" fill="#0f172a"/><rect width="5" height="14" fill="#dc2626"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+91',
        country: 'IN',
        name: 'India',
        maxDigits: 11,
        placeholder: '98123 45678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="4.66" fill="#f97316"/><rect y="4.66" width="20" height="4.66" fill="#ffffff"/><rect y="9.32" width="20" height="4.68" fill="#15803d"/><circle cx="10" cy="7" r="1.8" fill="none" stroke="#1e3a8a" stroke-width="0.8"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
    {
        code: '+49',
        country: 'DE',
        name: 'Jerman',
        maxDigits: 12,
        placeholder: '151 12345678',
        svg: `<svg viewBox="0 0 20 14" width="20" height="14" class="hcis-flag-svg"><rect width="20" height="4.66" fill="#0f172a"/><rect y="4.66" width="20" height="4.66" fill="#dc2626"/><rect y="9.32" width="20" height="4.68" fill="#eab308"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="1" rx="2"/></svg>`,
    },
];

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    countryCode: { type: String, default: '+62' },
    countryOptions: { type: Array, default: () => [] },
    label: { type: String, default: 'Nomor HP atau telepon' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'outline' },
    ariaLabel: { type: String, default: '' },
    numberAriaLabel: { type: String, default: 'Nomor telepon' },
    placeholder: { type: String, default: '' },
    maxDigits: { type: [Number, String], default: null },
    showCounter: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'update:countryCode', 'update:nationalNumber', 'change']);

const containerRef = ref(null);
const triggerBtnRef = ref(null);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
const inputRef = ref(null);

const isOpen = ref(false);
const searchQuery = ref('');
const selectedCountryCode = ref(props.countryCode || '+62');
const nationalNumber = ref('');

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    width: '280px',
    zIndex: '999999',
});

const allCountries = computed(() => {
    if (props.countryOptions && props.countryOptions.length) {
        return props.countryOptions.map((item) => {
            if (typeof item === 'string') {
                return {
                    code: item,
                    name: item,
                    maxDigits: 15,
                    placeholder: 'Nomor telepon',
                    svg: `<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#e2e8f0" rx="2"/></svg>`,
                };
            }
            return {
                code: item.code || item.value || '+62',
                country: item.country || item.id || '',
                name: item.name || item.label || '',
                maxDigits: item.maxDigits || 15,
                placeholder: item.placeholder || '',
                svg: item.svg || `<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#e2e8f0" rx="2"/></svg>`,
            };
        });
    }
    return COUNTRIES;
});

const activeCountry = computed(() => {
    return (
        allCountries.value.find((c) => c.code === selectedCountryCode.value) ||
        allCountries.value[0] ||
        COUNTRIES[0]
    );
});

const filteredCountries = computed(() => {
    if (!searchQuery.value) return allCountries.value;
    const q = searchQuery.value.toLowerCase().trim();
    return allCountries.value.filter((c) => {
        return (
            c.name.toLowerCase().includes(q) ||
            c.code.toLowerCase().includes(q) ||
            (c.country && c.country.toLowerCase().includes(q))
        );
    });
});

const resolvedMaxDigits = computed(() => {
    if (props.maxDigits !== null && props.maxDigits !== undefined && props.maxDigits !== '') {
        return Number(props.maxDigits);
    }
    return activeCountry.value?.maxDigits || 13;
});

const resolvedPlaceholder = computed(() => {
    if (props.placeholder) return props.placeholder;
    return activeCountry.value?.placeholder || '812 3456 7890';
});

const sanitizeDigits = (val) => {
    let clean = String(val || '').replace(/[^0-9]/g, '');
    // If starts with 0, strip leading 0 (e.g. 0812 -> 812)
    if (clean.startsWith('0')) {
        clean = clean.replace(/^0+/, '');
    }
    return clean.slice(0, resolvedMaxDigits.value);
};

const parseIncomingValue = (val) => {
    if (!val) {
        nationalNumber.value = '';
        return;
    }

    const str = String(val).trim();
    for (const c of allCountries.value) {
        if (str.startsWith(c.code)) {
            selectedCountryCode.value = c.code;
            const remaining = str.slice(c.code.length);
            nationalNumber.value = sanitizeDigits(remaining);
            return;
        }
    }

    nationalNumber.value = sanitizeDigits(str);
};

watch(
    () => props.modelValue,
    (val) => {
        const currentCombined = nationalNumber.value
            ? `${selectedCountryCode.value} ${nationalNumber.value}`
            : '';
        if (val !== currentCombined && val !== nationalNumber.value) {
            parseIncomingValue(val);
        }
    },
    { immediate: true },
);

watch(
    () => props.countryCode,
    (val) => {
        if (val && val !== selectedCountryCode.value) {
            selectedCountryCode.value = val;
            emitChanges();
        }
    },
);

watch(selectedCountryCode, () => {
    if (nationalNumber.value.length > resolvedMaxDigits.value) {
        nationalNumber.value = nationalNumber.value.slice(0, resolvedMaxDigits.value);
    }
    emitChanges();
});

function updatePopoverPosition() {
    if (!isOpen.value || !triggerBtnRef.value) return;
    const rect = triggerBtnRef.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const popoverWidth = Math.min(290, viewportWidth - 32);
    const popoverHeight = 290;

    let left = rect.left;
    if (left + popoverWidth > viewportWidth - 16) {
        left = Math.max(16, viewportWidth - popoverWidth - 16);
    }

    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;
    let top = rect.bottom + 6;

    if (spaceBelow < popoverHeight && spaceAbove > spaceBelow) {
        top = Math.max(8, rect.top - popoverHeight - 6);
    }

    popoverStyle.value = {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(Math.max(8, left))}px`,
        width: `${Math.round(popoverWidth)}px`,
        zIndex: '999999',
    };
}

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        updatePopoverPosition();
        nextTick(() => {
            updatePopoverPosition();
            searchInputRef.value?.focus();
        });
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

const selectCountry = (country) => {
    selectedCountryCode.value = country.code;
    isOpen.value = false;
    emitChanges();
    nextTick(() => inputRef.value?.focus());
};

const handleInput = (e) => {
    const raw = e?.target?.value ?? nationalNumber.value;
    nationalNumber.value = sanitizeDigits(raw);
    emitChanges();
};

const emitChanges = () => {
    const digits = nationalNumber.value;
    const combined = digits ? `${selectedCountryCode.value} ${digits}` : '';
    const rawE164 = digits ? `${selectedCountryCode.value}${digits}` : '';

    emit('update:modelValue', combined);
    emit('update:countryCode', selectedCountryCode.value);
    emit('update:nationalNumber', digits);
    emit('change', {
        value: combined,
        e164: rawE164,
        countryCode: selectedCountryCode.value,
        nationalNumber: digits,
        country: activeCountry.value,
    });
};

const handleClickOutside = (e) => {
    if (
        containerRef.value &&
        !containerRef.value.contains(e.target) &&
        dropdownRef.value &&
        !dropdownRef.value.contains(e.target)
    ) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('scroll', updatePopoverPosition, true);
    window.addEventListener('resize', updatePopoverPosition);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', updatePopoverPosition, true);
    window.removeEventListener('resize', updatePopoverPosition);
});
</script>

<style scoped>
.hcis-phone-input__control {
    display: flex;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    position: relative;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-surface, var(--hcis-white));
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        inset 0 1px 1px rgba(15, 23, 42, 0.02);
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        background-color 180ms ease;
}

.hcis-phone-input__control:focus-within,
.hcis-phone-input__control--open {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow:
        0 0 0 3px var(--hcis-focus-ring, rgba(99, 102, 241, 0.18)),
        0 1px 2px rgba(79, 70, 229, 0.1);
}

.hcis-phone-input__control--disabled {
    cursor: not-allowed;
    opacity: 0.65;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.hcis-phone-input__prefix {
    position: relative;
    flex: 0 0 auto;
    min-width: 0;
}

.hcis-phone-prefix-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    height: 100%;
    min-height: 2.375rem;
    padding: 0 0.65rem 0 0.75rem;
    border: 0;
    background: transparent;
    cursor: pointer;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font: inherit;
    font-size: 0.875rem;
    border-radius: var(--hcis-radius-md, 8px) 0 0 var(--hcis-radius-md, 8px);
    transition: background-color 150ms ease;
    user-select: none;
}

.hcis-phone-prefix-btn:hover:not(:disabled) {
    background-color: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.hcis-phone-flag-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

:deep(.hcis-flag-svg) {
    display: inline-block;
    vertical-align: middle;
    border-radius: 2px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.hcis-phone-dial-code {
    font-size: 0.8125rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.hcis-phone-chevron {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    transition: transform 200ms ease;
}

.hcis-phone-chevron.is-open {
    transform: rotate(180deg);
}

.hcis-phone-input__divider {
    flex: 0 0 1px;
    margin: 0.45rem 0;
    background: var(--hcis-border, var(--hcis-slate-200));
}

.hcis-phone-input__body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    position: relative;
}

.hcis-phone-input__number {
    flex: 1 1 auto;
    min-width: 0;
    height: 2.375rem;
    padding: 0 0.75rem;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font: inherit;
    font-size: 0.875rem;
    font-weight: 650;
    letter-spacing: 0.04em;
    transition: background-color 180ms ease;
}

.hcis-phone-input__number::placeholder {
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    font-weight: 500;
    letter-spacing: normal;
}

.hcis-phone-input__number:focus {
    background: rgba(99, 102, 241, 0.03);
}

.hcis-phone-input__counter {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    margin-right: 0.5rem;
    flex-shrink: 0;
    user-select: none;
}

.hcis-phone-input__control--sm .hcis-phone-input__number,
.hcis-phone-input__control--sm .hcis-phone-prefix-btn {
    height: 2rem;
    min-height: 2rem;
    font-size: 0.8125rem;
}

.hcis-phone-input__control--lg .hcis-phone-input__number,
.hcis-phone-input__control--lg .hcis-phone-prefix-btn {
    height: 2.75rem;
    min-height: 2.75rem;
    font-size: 0.9375rem;
}
</style>

<!-- Non-scoped styles for teleported dropdown -->
<style>
.hcis-phone-dropdown {
    max-height: 290px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-lg, 10px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
}

.hcis-phone-search-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border, var(--hcis-slate-200));
}

.hcis-phone-search-input {
    flex: 1 1 auto;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.hcis-phone-clear-search {
    border: 0;
    background: transparent;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    cursor: pointer;
    padding: 0;
    display: inline-flex;
}

.hcis-phone-list {
    overflow-y: auto;
    max-height: 230px;
    padding: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hcis-phone-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    border-radius: var(--hcis-radius-md, 6px);
    cursor: pointer;
    font-size: 0.8125rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    transition: background-color 120ms ease, color 120ms ease;
}

.hcis-phone-option:hover {
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.hcis-phone-option.is-active {
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    font-weight: 700;
}

.hcis-phone-country-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
    min-width: 0;
}

.hcis-phone-country-name {
    font-weight: 600;
    line-height: 1.25;
}

.hcis-phone-country-code {
    font-size: 0.75rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

/* Transitions */
.hcis-phone-popover-enter-active,
.hcis-phone-popover-leave-active {
    transition: opacity 150ms ease, transform 150ms ease;
}

.hcis-phone-popover-enter-from,
.hcis-phone-popover-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
