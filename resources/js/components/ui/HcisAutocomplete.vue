<template>
    <div
        ref="containerRef"
        class="hcis-autocomplete"
        :class="[
            `hcis-autocomplete--size-${size}`,
            {
                'hcis-autocomplete--active': isOpen,
                'hcis-autocomplete--disabled': disabled,
                'hcis-autocomplete--loading': loading,
                'hcis-autocomplete--has-value': !!modelValue,
            }
        ]"
    >
        <!-- Input Shell -->
        <div
            ref="triggerRef"
            class="hcis-autocomplete__trigger"
            :class="{
                'hcis-autocomplete__trigger--focused': isFocused || isOpen,
                'hcis-autocomplete__trigger--disabled': disabled,
            }"
        >
            <span v-if="prefixIcon || $slots.prefix" class="hcis-autocomplete__prefix">
                <slot name="prefix">
                    <HcisIcon :name="prefixIcon" size="sm" />
                </slot>
            </span>

            <input
                ref="inputRef"
                type="text"
                class="hcis-autocomplete__input"
                :value="displayString"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                aria-autocomplete="list"
                :aria-expanded="isOpen"
                role="combobox"
                @focus="handleFocus"
                @blur="handleBlur"
                @input="handleInput"
                @keydown="handleKeyDown"
            />

            <span v-if="loading" class="hcis-autocomplete__spinner">
                <HcisIcon name="arrow-repeat" spin size="sm" />
            </span>

            <button
                v-else-if="clearable && modelValue && !disabled"
                type="button"
                class="hcis-autocomplete__clear"
                title="Hapus isian"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="sm" />
            </button>

            <span v-else-if="suffixIcon || $slots.suffix" class="hcis-autocomplete__suffix">
                <slot name="suffix">
                    <HcisIcon :name="suffixIcon" size="sm" />
                </slot>
            </span>
        </div>

        <!-- Suggestion Popover Dropdown (Teleported to Body) -->
        <Teleport to="body" :disabled="teleportTo === false">
            <Transition name="hcis-autocomplete-popover">
                <div
                    v-if="isOpen && (suggestions.length > 0 || loading || $slots.empty)"
                    ref="popoverRef"
                    class="hcis-autocomplete__popover"
                    :style="popoverStyle"
                    role="listbox"
                >
                    <!-- Loading Skeleton / Spinner State -->
                    <div v-if="loading" class="hcis-autocomplete__loading-state">
                        <HcisIcon name="arrow-repeat" spin size="sm" /> Memuat saran...
                    </div>

                    <!-- Suggestions List -->
                    <div v-else-if="suggestions.length > 0" ref="listRef" class="hcis-autocomplete__list">
                        <div
                            v-for="(item, idx) in suggestions"
                            :key="getItemKey(item, idx)"
                            class="hcis-autocomplete__item"
                            :class="{
                                'hcis-autocomplete__item--active': highlightedIndex === idx,
                                'hcis-autocomplete__item--selected': isItemSelected(item),
                            }"
                            role="option"
                            :aria-selected="highlightedIndex === idx"
                            @mouseenter="highlightedIndex = idx"
                            @click="selectItem(item)"
                        >
                            <!-- Custom Scoped Item Slot -->
                            <slot :item="item" :index="idx">
                                <span class="hcis-autocomplete__item-label">
                                    {{ getItemLabel(item) }}
                                </span>
                            </slot>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="hcis-autocomplete__empty-state">
                        <slot name="empty">
                            <i class="bi bi-search" /> Tidak ada hasil ditemukan
                        </slot>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import HcisIcon from './HcisIcon.vue';

const props = defineProps({
    modelValue: { type: [String, Number, Object], default: '' },
    fetchSuggestions: { type: Function, default: null },
    suggestionsList: { type: Array, default: () => [] },
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'value' },
    placeholder: { type: String, default: 'Ketik untuk mencari...' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    triggerOnFocus: { type: Boolean, default: true },
    debounce: { type: Number, default: 200 },
    prefixIcon: { type: String, default: '' },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    teleportTo: { type: [String, Boolean], default: 'body' },
});

const emit = defineEmits(['update:modelValue', 'change', 'select', 'clear', 'focus', 'blur']);

const containerRef = ref(null);
const triggerRef = ref(null);
const inputRef = ref(null);
const popoverRef = ref(null);
const listRef = ref(null);

const isOpen = ref(false);
const isFocused = ref(false);
const loading = ref(false);
const suggestions = ref([]);
const highlightedIndex = ref(0);
let debounceTimer = null;

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    width: '100%',
    zIndex: '99999',
});

const getItemLabel = (item) => {
    if (item === null || item === undefined) return '';
    if (typeof item === 'object') {
        return item[props.labelKey] || item[props.valueKey] || item.label || '';
    }
    return String(item);
};

const getItemValue = (item) => {
    if (item === null || item === undefined) return '';
    if (typeof item === 'object') {
        return item[props.valueKey] || item[props.labelKey] || item.value || '';
    }
    return String(item);
};

const getItemKey = (item, idx) => {
    if (typeof item === 'object' && item.id !== undefined) return item.id;
    return `${getItemValue(item)}-${idx}`;
};

const displayString = computed(() => {
    return getItemLabel(props.modelValue);
});

const isItemSelected = (item) => {
    return getItemValue(item) === getItemValue(props.modelValue);
};

const updatePopoverPosition = () => {
    if (!isOpen.value || (!triggerRef.value && !containerRef.value)) return;
    const target = triggerRef.value || containerRef.value;
    const rect = target.getBoundingClientRect();
    const popoverWidth = Math.max(rect.width, 240);
    const popoverHeight = 260;

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
        width: `${popoverWidth}px`,
        zIndex: '99999',
    };
};

const loadSuggestions = (queryString = '') => {
    if (props.fetchSuggestions) {
        loading.value = true;
        props.fetchSuggestions(queryString, (res) => {
            loading.value = false;
            if (Array.isArray(res)) {
                suggestions.value = res;
            } else {
                suggestions.value = [];
            }
            highlightedIndex.value = 0;
            if (isFocused.value && (suggestions.value.length > 0 || props.triggerOnFocus)) {
                isOpen.value = true;
                nextTick(updatePopoverPosition);
            }
        });
    } else if (Array.isArray(props.suggestionsList)) {
        if (!queryString) {
            suggestions.value = props.suggestionsList;
        } else {
            const q = queryString.toLowerCase();
            suggestions.value = props.suggestionsList.filter((item) => {
                const label = getItemLabel(item).toLowerCase();
                return label.includes(q);
            });
        }
        highlightedIndex.value = 0;
        if (isFocused.value) {
            isOpen.value = true;
            nextTick(updatePopoverPosition);
        }
    }
};

const handleInput = (e) => {
    const val = e.target.value;
    emit('update:modelValue', val);
    emit('change', val);

    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        loadSuggestions(val);
    }, props.debounce);
};

const handleFocus = (e) => {
    isFocused.value = true;
    emit('focus', e);
    if (props.triggerOnFocus) {
        loadSuggestions(getItemLabel(props.modelValue));
    }
};

const handleBlur = (e) => {
    emit('blur', e);
    setTimeout(() => {
        isFocused.value = false;
        isOpen.value = false;
    }, 150);
};

const selectItem = (item) => {
    const val = typeof item === 'object' ? item : item;
    emit('update:modelValue', val);
    emit('change', val);
    emit('select', item);
    isOpen.value = false;
};

const scrollHighlightedIntoView = () => {
    nextTick(() => {
        if (!listRef.value) return;
        const activeChild = listRef.value.children[highlightedIndex.value];
        if (activeChild) {
            const el = listRef.value;
            const targetTop = activeChild.offsetTop - el.clientHeight / 2 + activeChild.offsetHeight / 2;
            if (typeof el.scrollTo === 'function') {
                el.scrollTo({ top: targetTop, behavior: 'smooth' });
            } else {
                el.scrollTop = targetTop;
            }
        }
    });
};

const handleKeyDown = (e) => {
    if (!isOpen.value && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        isOpen.value = true;
        loadSuggestions(getItemLabel(props.modelValue));
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (suggestions.value.length > 0) {
            highlightedIndex.value = (highlightedIndex.value + 1) % suggestions.value.length;
            scrollHighlightedIntoView();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (suggestions.value.length > 0) {
            highlightedIndex.value =
                (highlightedIndex.value - 1 + suggestions.value.length) % suggestions.value.length;
            scrollHighlightedIntoView();
        }
    } else if (e.key === 'Enter') {
        if (isOpen.value && suggestions.value.length > 0) {
            e.preventDefault();
            selectItem(suggestions.value[highlightedIndex.value]);
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        isOpen.value = false;
    }
};

const handleClear = () => {
    emit('update:modelValue', '');
    emit('change', '');
    emit('clear');
    suggestions.value = [];
    isOpen.value = false;
};

const handleClickOutside = (e) => {
    if (!isOpen.value) return;
    const isInsideContainer = containerRef.value && containerRef.value.contains(e.target);
    const isInsidePopover = popoverRef.value && popoverRef.value.contains(e.target);
    if (!isInsideContainer && !isInsidePopover) {
        isOpen.value = false;
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
    if (debounceTimer) clearTimeout(debounceTimer);
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', updatePopoverPosition, true);
    window.removeEventListener('resize', updatePopoverPosition);
});
</script>

<style>
.hcis-autocomplete {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 22rem;
    font-family: var(--hcis-font-body, inherit);
}

/* Sizes */
.hcis-autocomplete--size-sm .hcis-autocomplete__trigger {
    height: 2.0rem;
    padding: 0 0.55rem;
    font-size: 0.8125rem;
    border-radius: 8px;
}

.hcis-autocomplete--size-md .hcis-autocomplete__trigger {
    height: 2.375rem;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    border-radius: var(--hcis-radius-md, 9px);
}

.hcis-autocomplete--size-lg .hcis-autocomplete__trigger {
    height: 2.75rem;
    padding: 0 1.0rem;
    font-size: 0.9375rem;
    border-radius: var(--hcis-radius-xl, 12px);
}

/* Input Shell Trigger */
.hcis-autocomplete__trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    background: linear-gradient(180deg, var(--hcis-white) 0%, #fcfdfe 100%);
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04), inset 0 1px 1px 0 rgba(15, 23, 42, 0.02);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    cursor: text;
}

.hcis-autocomplete__trigger:hover:not(.hcis-autocomplete--disabled) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 2px 6px -1px rgba(99, 102, 241, 0.1), inset 0 1px 1px 0 rgba(255, 255, 255, 0.8);
}

.hcis-autocomplete--active .hcis-autocomplete__trigger,
.hcis-autocomplete__trigger--focused {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgba(79, 70, 229, 0.1) !important;
    background-color: var(--hcis-surface, var(--hcis-white)) !important;
}

.hcis-autocomplete--disabled .hcis-autocomplete__trigger {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
}

.hcis-autocomplete--disabled .hcis-autocomplete__input {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    -webkit-text-fill-color: var(--hcis-slate-500, var(--hcis-slate-500));
    cursor: not-allowed;
}

.hcis-autocomplete__input {
    flex: 1;
    width: 100%;
    height: 100%;
    border: none;
    outline: none;
    background: transparent;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-size: inherit;
    font-weight: 550;
    padding: 0;
}

.hcis-autocomplete__input::placeholder {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-weight: 450;
}

.hcis-autocomplete__prefix,
.hcis-autocomplete__suffix {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
}

.hcis-autocomplete__prefix {
    margin-right: 0.5rem;
}

.hcis-autocomplete__suffix {
    margin-left: 0.5rem;
}

.hcis-autocomplete__clear {
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    transition: color 150ms;
    margin-left: 0.375rem;
}

.hcis-autocomplete__clear:hover {
    color: var(--hcis-danger-500);
}

.hcis-autocomplete__spinner {
    color: var(--hcis-brand-500, var(--hcis-brand-500));
    font-size: 0.9rem;
}

/*
 * Kelas .spin dan keyframe-nya dihapus: ia salinan tangan dari
 * <HcisIcon spin>, dan salinan itulah yang membuatnya luput dari daftar
 * reduced-motion — .hcis-icon--spin sudah terdaftar, .spin tidak pernah.
 */

/* Popover Panel Modal Dropdown (Teleported to Body) */
.hcis-autocomplete__popover {
    background-color: var(--hcis-surface, var(--hcis-white));
    border-radius: var(--hcis-radius-xl);
    border: 1px solid var(--hcis-border);
    box-shadow: var(--hcis-shadow-popover);
    padding: 6px;
    max-height: 16rem;
    overflow: hidden;
    contain: layout paint;
    isolation: isolate;
}

.hcis-autocomplete__list {
    max-height: 15rem;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgb(var(--hcis-slate-300-rgb) / 0.8) transparent;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

/* Suggestion Item Styling */
.hcis-autocomplete__item {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--hcis-text, var(--hcis-slate-700));
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: var(--hcis-ease);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.hcis-autocomplete__item--active {
    background-color: var(--hcis-brand-50, var(--hcis-brand-50)) !important;
    color: var(--hcis-brand-700, var(--hcis-brand-700)) !important;
}

.hcis-autocomplete__item--selected {
    background-color: var(--hcis-brand-600, var(--hcis-brand-600)) !important;
    color: var(--hcis-white) !important;
    font-weight: 700 !important;
}

.hcis-autocomplete__loading-state,
.hcis-autocomplete__empty-state {
    padding: 1rem;
    text-align: center;
    font-size: 0.825rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

/* Transition Popover */
.hcis-autocomplete-popover-enter-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-autocomplete-popover-leave-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: cubic-bezier(0.4, 0, 1, 1);
}

.hcis-autocomplete-popover-enter-from,
.hcis-autocomplete-popover-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}
</style>
