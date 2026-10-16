<template>
    <div
        ref="containerRef"
        class="hcis-select"
        :class="[
            `hcis-select--size-${size}`,
            {
                'hcis-select--active': isOpen || isFocused,
                'hcis-select--disabled': disabled,
                'hcis-select--loading': loading,
                'hcis-select--multiple': multiple,
                'hcis-select--has-value': hasValue,
            },
        ]"
    >
        <!-- Trigger Shell -->
        <div
            ref="triggerRef"
            class="hcis-select__trigger"
            :id="id || undefined"
            :class="{
                'hcis-select__trigger--focused': isFocused || isOpen,
                'hcis-select__trigger--disabled': disabled,
                'hcis-select__trigger--loading': loading,
                'hcis-select__trigger--open': isOpen,
            }"
            role="combobox"
            :tabindex="disabled ? -1 : 0"
            :aria-label="resolvedAriaLabel"
            :aria-expanded="isOpen"
            :aria-controls="optionsId"
            aria-haspopup="listbox"
            :aria-disabled="disabled || undefined"
            @click="handleTriggerClick"
            @keydown.enter.prevent="togglePopover"
            @keydown.space.prevent="togglePopover"
            @keydown.down.prevent="openPopover"
            @keydown.escape.prevent="closePopover"
        >
            <span class="hcis-select__icon">
                <HcisIcon v-if="loading" name="arrow-repeat" size="xs" class="hcis-spin-icon text-brand-600" />
                <HcisIcon v-else-if="disabled" name="lock-fill" size="xs" class="hcis-lock-icon text-muted" />
                <HcisIcon v-else-if="leadingIcon" :name="leadingIcon" size="xs" class="text-slate-500" />
                <HcisIcon v-else name="ui-checks-grid" size="xs" class="text-brand-600" />
            </span>

            <div class="hcis-select__display" :class="{ 'hcis-shimmer-pulse': loading }">
                <!-- Multiple Tags View -->
                <template v-if="multiple && Array.isArray(modelValue) && modelValue.length">
                    <div class="hcis-select__tags">
                        <!-- Case 1: All Selected (collapseTags = true and all options are chosen) -->
                        <template v-if="collapseTags && isAllSelected">
                            <span class="hcis-select-tag hcis-select-tag--all">
                                <HcisIcon name="check-all" size="2xs" class="me-1" />
                                <span class="hcis-select-tag-text">{{
                                    selectAllText || `Semua Terpilih (${selectedTags.length})`
                                }}</span>
                                <button
                                    type="button"
                                    class="hcis-tag-remove"
                                    title="Hapus semua"
                                    @click.stop="handleClear"
                                >
                                    <HcisIcon name="x" size="2xs" />
                                </button>
                            </span>
                        </template>

                        <!-- Case 2: Collapsed Tags (collapseTags = true and count > maxCollapseTags) -->
                        <template v-else-if="collapseTags && collapsedCount > 0">
                            <span v-for="val in visibleTags" :key="val.value" class="hcis-select-tag">
                                <HcisIcon
                                    v-if="val.icon"
                                    :name="val.icon"
                                    :color="val.iconColor"
                                    size="2xs"
                                    class="me-1"
                                />
                                <span class="hcis-select-tag-text">{{ val.label }}</span>
                                <button
                                    type="button"
                                    class="hcis-tag-remove"
                                    title="Hapus tag"
                                    @click.stop="removeTag(val.value)"
                                >
                                    <HcisIcon name="x" size="2xs" />
                                </button>
                            </span>
                            <span
                                class="hcis-select-tag hcis-select-tag--count"
                                :title="collapseTagsTooltip ? hiddenTagsText : undefined"
                            >
                                +{{ collapsedCount }}
                            </span>
                        </template>

                        <!-- Case 3: Standard Full Tags List -->
                        <template v-else>
                            <span v-for="val in selectedTags" :key="val.value" class="hcis-select-tag">
                                <HcisIcon
                                    v-if="val.icon"
                                    :name="val.icon"
                                    :color="val.iconColor"
                                    size="2xs"
                                    class="me-1"
                                />
                                <span class="hcis-select-tag-text">{{ val.label }}</span>
                                <button
                                    type="button"
                                    class="hcis-tag-remove"
                                    title="Hapus tag"
                                    @click.stop="removeTag(val.value)"
                                >
                                    <HcisIcon name="x" size="2xs" />
                                </button>
                            </span>
                        </template>
                    </div>
                </template>

                <!-- Single Value / Placeholder View -->
                <template v-else>
                    <span v-if="selectedLabel" class="hcis-display-text" :title="String(selectedLabel)">
                        <HcisIcon
                            v-if="selectedOptionIcon"
                            :name="selectedOptionIcon"
                            :color="selectedOptionColor"
                            size="2xs"
                            class="me-1"
                        />
                        {{ selectedLabel }}
                    </span>
                    <span v-else class="hcis-display-placeholder">
                        {{ placeholder }}
                    </span>
                </template>
            </div>

            <button
                v-if="clearable && hasValue && !disabled && !loading"
                type="button"
                class="hcis-select__clear"
                title="Bersihkan pilihan"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span v-else class="hcis-select__arrow" :class="{ 'is-open': isOpen }" @click.stop="togglePopover">
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- Unified Popover / Mobile Sheet Shell (Always mounts slot so options are registered on both desktop & mobile) -->
        <Teleport to="body" :disabled="teleportTo === false">
            <!-- Desktop Floating Popover -->
            <Transition v-if="!isMobile" name="hcis-date-popover">
                <div
                    v-show="isOpen && !disabled && !loading"
                    ref="popoverRef"
                    class="hcis-select__popover"
                    :style="popoverStyle"
                    role="listbox"
                    :aria-label="resolvedAriaLabel"
                >
                    <!-- Filter Search Input (when filterable = true) -->
                    <div v-if="filterable" class="hcis-select-search">
                        <HcisIcon name="search" size="xs" class="hcis-search-icon" />
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            class="hcis-search-input"
                            placeholder="Cari pilihan..."
                            @input="handleSearchInput"
                            @keydown.down.prevent="focusFirstOption"
                            @keydown.escape.prevent="closePopover"
                        />
                    </div>

                    <!-- Options Slot Container -->
                    <div :id="optionsId" class="hcis-select__options">
                        <slot />
                    </div>
                </div>
            </Transition>

            <!-- Mobile Bottom Sheet (Uses v-show to keep slot mounted and options registered) -->
            <div v-else class="hcis-select-mobile-wrapper">
                <Transition name="hcis-mobile-fade">
                    <div
                        v-show="isOpen && !disabled && !loading"
                        class="hcis-select-mobile-overlay"
                        role="presentation"
                        @click="closePopover"
                    />
                </Transition>
                <Transition name="hcis-mobile-slide">
                    <div
                        v-show="isOpen && !disabled && !loading"
                        ref="mobileDrawerRef"
                        class="hcis-select-mobile-sheet"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="resolvedAriaLabel"
                    >
                        <!-- Mobile Drag Handle -->
                        <div class="hcis-select-mobile-handle" aria-hidden="true" />

                        <!-- Header -->
                        <div class="hcis-select-mobile-header">
                            <span class="hcis-select-mobile-title">{{ placeholder || 'Pilih Opsi' }}</span>
                            <button
                                type="button"
                                class="hcis-select-mobile-close"
                                aria-label="Tutup"
                                @click="closePopover"
                            >
                                <HcisIcon name="x-lg" size="xs" />
                            </button>
                        </div>

                        <!-- Mobile Filter Search -->
                        <div v-if="filterable" class="hcis-select-search hcis-select-search--mobile mb-2">
                            <HcisIcon name="search" size="xs" class="hcis-search-icon" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                class="hcis-search-input"
                                placeholder="Cari pilihan..."
                                @input="handleSearchInput"
                            />
                        </div>

                        <!-- Mobile Options Slot Container -->
                        <div :id="optionsId" class="hcis-select__options hcis-select__options--mobile" role="listbox">
                            <slot />
                        </div>
                    </div>
                </Transition>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, shallowRef, computed, watch, provide, onMounted, onUnmounted, nextTick, useAttrs, useId } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisSelect' });

const props = defineProps({
    id: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
    modelValue: { type: [String, Number, Boolean, Array, Object], default: '' },
    placeholder: { type: String, default: 'Pilih opsi...' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    filterable: { type: Boolean, default: true },
    multiple: { type: Boolean, default: false },
    collapseTags: { type: Boolean, default: true },
    maxCollapseTags: { type: Number, default: 1 },
    collapseTagsTooltip: { type: Boolean, default: true },
    selectAllText: { type: String, default: '' },
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
    leadingIcon: { type: String, default: '' },
    selectedIcon: { type: String, default: '' },
    selectedIconColor: { type: String, default: '' },
    teleportTo: { type: [String, Boolean], default: 'body' },
    popoverMinWidth: { type: [Number, String], default: 280 },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear', 'search']);
const attrs = useAttrs();
const optionsId = `hcis-select-options-${useId()}`;

const containerRef = ref(null);
const triggerRef = ref(null);
const popoverRef = ref(null);
const mobileDrawerRef = ref(null);
const searchInputRef = ref(null);

const isOpen = ref(false);
const isFocused = ref(false);
const isMobile = ref(false);
const searchQuery = ref('');
const registeredOptions = shallowRef(new Map());
const cachedOptionLabels = new Map();

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    width: '18rem',
    zIndex: '99999',
});

const hasValue = computed(() => {
    if (props.multiple) {
        return Array.isArray(props.modelValue) && props.modelValue.length > 0;
    }
    return props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined;
});

const resolvedAriaLabel = computed(() => props.ariaLabel || attrs['aria-label'] || props.placeholder || 'Pilih opsi');

const findOption = (val) => {
    if (val === null || val === undefined || val === '') return null;
    if (registeredOptions.value.has(val)) {
        return registeredOptions.value.get(val);
    }
    const strVal = String(val);
    if (registeredOptions.value.has(strVal)) {
        return registeredOptions.value.get(strVal);
    }
    if (cachedOptionLabels.has(val)) {
        return cachedOptionLabels.get(val);
    }
    if (cachedOptionLabels.has(strVal)) {
        return cachedOptionLabels.get(strVal);
    }
    const lowerVal = strVal.toLowerCase().trim();
    for (const opt of registeredOptions.value.values()) {
        if (String(opt.value) === strVal || String(opt.value).toLowerCase().trim() === lowerVal) {
            return opt;
        }
        if (String(opt.label).toLowerCase().trim() === lowerVal) {
            return opt;
        }
    }
    for (const opt of cachedOptionLabels.values()) {
        if (String(opt.value) === strVal || String(opt.value).toLowerCase().trim() === lowerVal) {
            return opt;
        }
        if (String(opt.label).toLowerCase().trim() === lowerVal) {
            return opt;
        }
    }
    return null;
};

const selectedOption = computed(() => {
    if (props.multiple || !hasValue.value) return null;
    return findOption(props.modelValue);
});

const selectedLabel = computed(() => {
    if (props.multiple || !hasValue.value) return '';
    const option = findOption(props.modelValue);
    if (option && option.label !== undefined && option.label !== null && option.label !== '') {
        return option.label;
    }
    if (typeof props.modelValue === 'string' || typeof props.modelValue === 'number') {
        return String(props.modelValue);
    }
    return '';
});

const selectedOptionIcon = computed(() => selectedOption.value?.icon || props.selectedIcon || '');
const selectedOptionColor = computed(() => selectedOption.value?.iconColor || props.selectedIconColor || '');

const selectedTags = computed(() => {
    if (!props.multiple || !Array.isArray(props.modelValue)) return [];
    return props.modelValue.map((v) => {
        const option = findOption(v);
        return {
            value: v,
            label: option ? option.label : v,
            icon: option?.icon,
            iconColor: option?.iconColor,
            badge: option?.badge,
            badgeVariant: option?.badgeVariant,
        };
    });
});

const isAllSelected = computed(() => {
    if (!props.multiple || !Array.isArray(props.modelValue)) return false;
    const totalOptions = registeredOptions.value.size;
    return totalOptions > 1 && props.modelValue.length >= totalOptions;
});

const visibleTags = computed(() => {
    if (!props.multiple || !Array.isArray(props.modelValue)) return [];
    return selectedTags.value.slice(0, props.maxCollapseTags);
});

const collapsedCount = computed(() => {
    if (!props.multiple || !Array.isArray(props.modelValue)) return 0;
    return Math.max(0, props.modelValue.length - props.maxCollapseTags);
});

const hiddenTagsText = computed(() => {
    if (!props.multiple || !Array.isArray(props.modelValue)) return '';
    return selectedTags.value
        .slice(props.maxCollapseTags)
        .map((t) => t.label)
        .join(', ');
});

function checkMobile() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
}

function updatePopoverPosition() {
    if (!isOpen.value) return;
    checkMobile();
    if (isMobile.value || !triggerRef.value) return;

    const rect = triggerRef.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    // Use measured height when available, otherwise sensible default
    const measuredHeight = popoverRef.value ? popoverRef.value.getBoundingClientRect().height : 0;
    const popoverHeight = measuredHeight && measuredHeight > 20 ? measuredHeight : 220;
    const minW = props.popoverMinWidth !== '' && props.popoverMinWidth !== null ? Number(props.popoverMinWidth) : 280;
    const width = Math.max(rect.width, isNaN(minW) ? 280 : minW);

    let left = rect.left;
    if (left + width > viewportWidth - 16) {
        left = Math.max(16, viewportWidth - width - 16);
    }

    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;
    let top = rect.bottom + 6;

    // If space below is constrained and space above is larger, open directly above trigger
    if (spaceBelow < popoverHeight && (spaceAbove > spaceBelow || spaceAbove > popoverHeight)) {
        top = Math.max(8, rect.top - popoverHeight - 6);
    } else if (top + popoverHeight > viewportHeight - 16) {
        top = Math.max(8, viewportHeight - popoverHeight - 16);
    }

    popoverStyle.value = {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(Math.max(8, left))}px`,
        width: `${Math.round(Math.min(width, viewportWidth - 32))}px`,
        maxWidth: 'calc(100vw - 32px)',
        zIndex: '999999',
    };
}

function togglePopover() {
    if (props.disabled || props.loading) return;
    if (isOpen.value) closePopover();
    else openPopover();
}

function openPopover() {
    if (props.disabled || props.loading) return;
    searchQuery.value = '';
    isOpen.value = true;
    updatePopoverPosition();
    nextTick(() => {
        updatePopoverPosition();
        if (props.filterable && searchInputRef.value) {
            searchInputRef.value.focus();
        }
    });
}

function handleSearchInput(e) {
    const val = e?.target?.value ?? searchQuery.value;
    searchQuery.value = val;
    emit('search', val);
}

function focusFirstOption() {
    const container = popoverRef.value;
    if (!container) return;
    const firstOption = container.querySelector('.hcis-select-option:not(.is-disabled)[tabindex="0"]');
    if (firstOption && typeof firstOption.focus === 'function') {
        firstOption.focus();
    }
}

function closePopover() {
    isOpen.value = false;
}

function handleTriggerClick() {
    if (!isOpen.value && !props.disabled && !props.loading) openPopover();
}

function handleClear() {
    if (props.disabled || props.loading) return;
    const emptyVal = props.multiple ? [] : '';
    emit('update:modelValue', emptyVal);
    emit('change', emptyVal);
    emit('clear');
}

function selectOption(option) {
    if (props.multiple) {
        const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
        const idx = current.indexOf(option.value);
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(option.value);
        }
        emit('update:modelValue', current);
        emit('change', current);
    } else {
        registeredOptions.value.set(option.value, option);
        emit('update:modelValue', option.value);
        emit('change', option.value);
        closePopover();
    }
}

function removeTag(val) {
    if (props.disabled || props.loading) return;
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    const idx = current.indexOf(val);
    if (idx >= 0) {
        current.splice(idx, 1);
        emit('update:modelValue', current);
        emit('change', current);
    }
}

let isSyncPending = false;

function scheduleOptionsUpdate() {
    if (isSyncPending) return;
    isSyncPending = true;
    queueMicrotask(() => {
        isSyncPending = false;
        registeredOptions.value = new Map(registeredOptions.value);
    });
}

function registerOption(opt) {
    if (!opt || opt.value === undefined || opt.value === null) return;
    registeredOptions.value.set(opt.value, opt);
    registeredOptions.value.set(String(opt.value), opt);
    cachedOptionLabels.set(opt.value, opt);
    cachedOptionLabels.set(String(opt.value), opt);
    scheduleOptionsUpdate();
}

function unregisterOption(val) {
    if (val === undefined || val === null) return;
    registeredOptions.value.delete(val);
    registeredOptions.value.delete(String(val));
    scheduleOptionsUpdate();
}

provide('hcisSelect', {
    props,
    modelValue: computed(() => props.modelValue),
    selectOption,
    searchQuery: computed(() => searchQuery.value),
    registerOption,
    unregisterOption,
});

function handleGlobalClick(e) {
    if (
        containerRef.value &&
        !containerRef.value.contains(e.target) &&
        popoverRef.value &&
        !popoverRef.value.contains(e.target) &&
        (!mobileDrawerRef.value || !mobileDrawerRef.value.contains(e.target))
    ) {
        closePopover();
    }
}

function bindPositionListeners() {
    if (typeof window === 'undefined') return;
    window.addEventListener('resize', updatePopoverPosition);
    window.addEventListener('scroll', updatePopoverPosition, true);
}

function unbindPositionListeners() {
    if (typeof window === 'undefined') return;
    window.removeEventListener('resize', updatePopoverPosition);
    window.removeEventListener('scroll', updatePopoverPosition, true);
}

watch(
    () => isOpen.value,
    (open) => {
        if (open) {
            bindPositionListeners();
        } else {
            unbindPositionListeners();
        }
    }
);

onMounted(() => {
    checkMobile();
    if (typeof window !== 'undefined') {
        window.addEventListener('click', handleGlobalClick, true);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleGlobalClick, true);
        unbindPositionListeners();
    }
});
</script>

<style scoped>
.hcis-select {
    position: relative;
    display: inline-block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

/* Sizes */
.hcis-select--size-sm .hcis-select__trigger {
    min-height: 2rem;
    height: 2rem;
    padding: 0.15rem 0.55rem;
    font-size: 0.8125rem;
    border-radius: 8px;
}

.hcis-select--size-md .hcis-select__trigger {
    min-height: 2.375rem;
    height: 2.375rem;
    padding: 0.35rem 0.75rem;
    font-size: 0.875rem;
    border-radius: var(--hcis-radius-md, 9px);
}

.hcis-select--size-lg .hcis-select__trigger {
    min-height: 2.75rem;
    height: 2.75rem;
    padding: 0.45rem 1rem;
    font-size: 0.9375rem;
    border-radius: var(--hcis-radius-xl, 12px);
}

/* Trigger Shell */
.hcis-select__trigger {
    display: flex;
    align-items: center;
    width: 100%;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    background: linear-gradient(180deg, var(--hcis-white) 0%, #fcfdfe 100%);
    box-shadow:
        0 1px 2px 0 rgba(15, 23, 42, 0.04),
        inset 0 1px 1px 0 rgba(15, 23, 42, 0.02);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
    user-select: none;
}

.hcis-select__trigger:hover:not(.hcis-select__trigger--disabled):not(.hcis-select__trigger--loading) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow:
        0 2px 6px -1px rgba(99, 102, 241, 0.1),
        inset 0 1px 1px 0 rgba(255, 255, 255, 0.8);
}

.hcis-select__trigger--focused,
.hcis-select__trigger--open {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow:
        0 0 0 3px var(--hcis-focus-ring),
        0 1px 2px 0 rgba(79, 70, 229, 0.1) !important;
    background-color: var(--hcis-surface, var(--hcis-white)) !important;
}

.hcis-select__trigger--disabled {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
}

.hcis-select__trigger--disabled .hcis-display-text {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    font-weight: 500;
}

.hcis-select__trigger--disabled .hcis-display-placeholder {
    color: var(--hcis-slate-400, var(--hcis-slate-400)) !important;
}

.hcis-select__trigger--disabled .hcis-select__icon,
.hcis-select__trigger--disabled .hcis-select__arrow {
    color: var(--hcis-slate-400, var(--hcis-slate-400)) !important;
}

.hcis-select__trigger--loading {
    cursor: wait !important;
    border-color: var(--hcis-brand-300);
}

.hcis-spin-icon {
    animation: hcis-spin 1s linear infinite;
}

@keyframes hcis-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.hcis-shimmer-pulse {
    animation: hcis-pulse-glow 1.5s ease-in-out infinite;
}

@keyframes hcis-pulse-glow {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.45;
    }
}

.hcis-select__icon {
    display: flex;
    align-items: center;
    margin-right: 0.5rem;
}

.hcis-select__display {
    flex: 1;
    min-width: 0;
}

.hcis-display-text {
    font-size: inherit;
    font-weight: 600;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-display-placeholder {
    font-size: inherit;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-weight: 500;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-select__tags {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.25rem;
    align-items: center;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
}

.hcis-select-tag {
    display: inline-flex;
    align-items: center;
    max-width: calc(100% - 2.5rem);
    min-width: 0;
    padding: 0.12rem 0.45rem;
    border-radius: var(--hcis-radius-md, 6px);
    background: rgba(238, 242, 255, 0.9);
    border: 1px solid rgba(199, 210, 254, 0.8);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    font-size: 0.75rem;
    font-weight: 650;
    letter-spacing: -0.01em;
    flex-shrink: 1;
    overflow: hidden;
}

.hcis-select-tag-text,
.hcis-select-tag > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 120px;
    min-width: 0;
    display: inline-block;
}

.hcis-select-tag--all {
    background: rgba(236, 253, 245, 0.95);
    border-color: rgba(167, 243, 208, 0.9);
    color: var(--hcis-emerald-700, var(--hcis-emerald-700));
    font-weight: 700;
}

.hcis-select-tag--all .hcis-tag-remove {
    color: var(--hcis-emerald-700, var(--hcis-emerald-700));
}

.hcis-select-tag--count {
    background: rgba(241, 245, 249, 0.95);
    border-color: rgba(203, 213, 225, 0.9);
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-weight: 750;
    padding: 0.12rem 0.45rem;
    cursor: default;
    flex-shrink: 0;
    white-space: nowrap;
}

.hcis-tag-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    cursor: pointer;
    margin-left: 0.25rem;
    padding: 0;
    opacity: 0.75;
    transition: opacity 120ms ease;
}

.hcis-tag-remove:hover {
    opacity: 1;
    color: var(--hcis-danger-600, var(--hcis-red-600));
}

.hcis-select__clear,
.hcis-select__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0 0.125rem;
    transition:
        transform 200ms cubic-bezier(0.16, 1, 0.3, 1),
        color 150ms ease;
}

.hcis-select__arrow.is-open {
    transform: rotate(180deg);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-select__clear:hover {
    color: var(--hcis-danger-600, var(--hcis-red-600));
}

/* Popover Panel */
.hcis-select__popover {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow:
        0 16px 36px -4px rgba(15, 23, 42, 0.14),
        0 4px 12px -2px rgba(15, 23, 42, 0.06),
        0 0 0 1px rgba(255, 255, 255, 0.8) inset;
    padding: 0.5rem;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.hcis-select-search {
    position: relative;
    display: flex;
    align-items: center;
}

.hcis-search-icon {
    position: absolute;
    left: 0.625rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.hcis-search-input {
    width: 100%;
    padding: 0.4rem 0.625rem 0.4rem 2rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    background: rgba(248, 250, 252, 0.85);
    font-size: 0.8125rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    outline: none;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-search-input:focus {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: var(--hcis-white);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.hcis-select__options {
    max-height: 220px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    scrollbar-width: thin;
}

.hcis-select__options--mobile {
    max-height: 55vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 0.5rem;
}

/* Mobile Bottom Sheet Drawer */
.hcis-select-mobile-wrapper {
    position: relative;
    z-index: 999998;
}

.hcis-select-mobile-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999998;
}

.hcis-select-mobile-sheet {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    max-height: 85vh;
    background: var(--hcis-white, #ffffff);
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    box-shadow: 0 -10px 40px rgba(15, 23, 42, 0.16);
    z-index: 999999;
    padding: 0.75rem 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.hcis-select-mobile-handle {
    width: 36px;
    height: 4px;
    border-radius: 999px;
    background: var(--hcis-slate-300, #cbd5e1);
    margin: 0 auto 0.75rem;
    flex-shrink: 0;
}

.hcis-select-mobile-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    flex-shrink: 0;
}

.hcis-select-mobile-title {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
}

.hcis-select-mobile-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-500, #64748b);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 150ms ease, color 150ms ease;
    padding: 0;
}

.hcis-select-mobile-close:hover {
    background: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-800, #1e293b);
}

.hcis-select-search--mobile {
    margin-bottom: 0.625rem;
    flex-shrink: 0;
}

.hcis-mobile-fade-enter-active,
.hcis-mobile-fade-leave-active {
    transition: opacity 200ms ease;
}

.hcis-mobile-fade-enter-from,
.hcis-mobile-fade-leave-to {
    opacity: 0;
}

.hcis-mobile-slide-enter-active,
.hcis-mobile-slide-leave-active {
    transition: transform 260ms cubic-bezier(0.16, 1, 0.3, 1), opacity 200ms ease;
}

.hcis-mobile-slide-enter-from,
.hcis-mobile-slide-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
