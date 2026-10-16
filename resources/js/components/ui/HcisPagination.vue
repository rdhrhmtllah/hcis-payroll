<template>
    <nav
        class="hcis-pagination"
        :class="[
            `hcis-pagination--${size}`,
            {
                'hcis-pagination--background': background,
                'hcis-pagination--disabled': disabled,
            },
        ]"
        aria-label="Navigasi Halaman"
    >
        <!-- Mode Mobile Compact Collapsed (Otomatis tampil di HP) -->
        <div class="hcis-pagination__mobile-summary">
            <button
                type="button"
                class="hcis-pagination__btn hcis-pagination__btn--prev"
                :disabled="disabled || currentPage <= 1"
                aria-label="Halaman sebelumnya"
                @click="goToPage(currentPage - 1)"
            >
                <HcisIcon name="chevron-left" size="xs" />
            </button>

            <span class="hcis-pagination__mobile-text">
                Halaman <strong>{{ currentPage }}</strong> dari <strong>{{ totalPages }}</strong>
            </span>

            <button
                type="button"
                class="hcis-pagination__btn hcis-pagination__btn--next"
                :disabled="disabled || currentPage >= totalPages"
                aria-label="Halaman berikutnya"
                @click="goToPage(currentPage + 1)"
            >
                <HcisIcon name="chevron-right" size="xs" />
            </button>
        </div>

        <!-- Mode Desktop & Tablet Lengkap -->
        <div class="hcis-pagination__desktop">
            <!-- 1. Total Count Pill Badge -->
            <div v-if="layoutItems.includes('total')" class="hcis-pagination__total">
                <span v-if="total > 0" class="hcis-pagination__total-badge">
                    Menampilkan <strong>{{ startItem }}-{{ endItem }}</strong> dari <strong>{{ formattedTotal }}</strong> data
                </span>
                <span v-else class="hcis-pagination__total-badge">0 data</span>
            </div>

            <!-- 2. Custom Owned Page Sizes Dropdown -->
            <div v-if="layoutItems.includes('sizes')" ref="sizeDropdownRef" class="hcis-pagination__sizes">
                <div class="hcis-pagination__size-wrapper">
                    <button
                        type="button"
                        class="hcis-pagination__size-trigger"
                        :disabled="disabled"
                        aria-label="Pilih jumlah data per halaman"
                        @click="isSizeOpen = !isSizeOpen"
                    >
                        <span>{{ pageSize }} baris / hal</span>
                        <HcisIcon
                            name="chevron-down"
                            size="2xs"
                            class="hcis-pagination__size-icon"
                            :class="{ 'is-open': isSizeOpen }"
                        />
                    </button>

                    <transition name="hcis-pagination-pop">
                        <ul v-if="isSizeOpen" class="hcis-pagination__size-menu" role="listbox">
                            <li
                                v-for="sizeOption in pageSizes"
                                :key="sizeOption"
                                class="hcis-pagination__size-option"
                                :class="{ 'is-selected': sizeOption === pageSize }"
                                role="option"
                                :aria-selected="sizeOption === pageSize"
                                @click="selectPageSize(sizeOption)"
                            >
                                <span>{{ sizeOption }} baris / hal</span>
                                <HcisIcon v-if="sizeOption === pageSize" name="check2" size="xs" />
                            </li>
                        </ul>
                    </transition>
                </div>
            </div>

            <!-- 3. Prev Button -->
            <button
                v-if="layoutItems.includes('prev')"
                type="button"
                class="hcis-pagination__btn hcis-pagination__btn--prev"
                :disabled="disabled || currentPage <= 1"
                aria-label="Halaman sebelumnya"
                @click="goToPage(currentPage - 1)"
            >
                <HcisIcon name="chevron-left" size="xs" />
            </button>

            <!-- 4. Pager List (Page Numbers & Ellipsis) -->
            <ul v-if="layoutItems.includes('pager')" class="hcis-pagination__pager">
                <li v-for="(item, idx) in pagerItems" :key="idx">
                    <!-- Ellipsis Prev / Next -->
                    <button
                        v-if="item.type === 'prev-ellipsis'"
                        type="button"
                        class="hcis-pagination__item hcis-pagination__item--ellipsis"
                        :disabled="disabled"
                        title="Lompat 5 halaman ke belakang"
                        @click="goToPage(Math.max(1, currentPage - 5))"
                    >
                        <span class="hcis-pagination__dots">•••</span>
                    </button>

                    <button
                        v-else-if="item.type === 'next-ellipsis'"
                        type="button"
                        class="hcis-pagination__item hcis-pagination__item--ellipsis"
                        :disabled="disabled"
                        title="Lompat 5 halaman ke depan"
                        @click="goToPage(Math.min(totalPages, currentPage + 5))"
                    >
                        <span class="hcis-pagination__dots">•••</span>
                    </button>

                    <!-- Page Number Button -->
                    <button
                        v-else
                        type="button"
                        class="hcis-pagination__item"
                        :class="{ 'is-active': item.page === currentPage }"
                        :disabled="disabled"
                        :aria-current="item.page === currentPage ? 'page' : undefined"
                        @click="goToPage(item.page)"
                    >
                        {{ item.page }}
                    </button>
                </li>
            </ul>

            <!-- 5. Next Button -->
            <button
                v-if="layoutItems.includes('next')"
                type="button"
                class="hcis-pagination__btn hcis-pagination__btn--next"
                :disabled="disabled || currentPage >= totalPages"
                aria-label="Halaman berikutnya"
                @click="goToPage(currentPage + 1)"
            >
                <HcisIcon name="chevron-right" size="xs" />
            </button>

            <!-- 6. Jumper (Ke Halaman N) -->
            <div v-if="layoutItems.includes('jumper')" class="hcis-pagination__jumper">
                <span class="hcis-pagination__jumper-label">Ke hal.</span>
                <div class="hcis-pagination__jumper-box">
                    <input
                        type="number"
                        min="1"
                        :max="totalPages"
                        :value="jumperInput"
                        class="hcis-pagination__jumper-input"
                        :disabled="disabled"
                        aria-label="Nomor halaman tujuan"
                        @input="jumperInput = $event.target.value"
                        @keydown.enter="handleJumperSubmit"
                        @blur="handleJumperSubmit"
                    />
                    <button
                        type="button"
                        class="hcis-pagination__jumper-btn"
                        :disabled="disabled"
                        title="Lompat ke halaman"
                        @click="handleJumperSubmit"
                    >
                        <HcisIcon name="arrow-right-short" size="xs" />
                    </button>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisPagination' });

const props = defineProps({
    page: { type: Number, default: undefined },
    currentPage: { type: Number, default: undefined },
    modelValue: { type: Number, default: undefined },
    pageSize: { type: Number, default: 10 },
    total: { type: Number, required: true, default: 0 },
    pageSizes: {
        type: Array,
        default: () => [10, 24, 25, 50, 100],
    },
    layout: {
        type: String,
        default: 'total, sizes, prev, pager, next',
    },
    disabled: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (val) => ['sm', 'md'].includes(val),
    },
    background: { type: Boolean, default: true },
});

const emit = defineEmits([
    'update:page',
    'update:currentPage',
    'update:modelValue',
    'update:pageSize',
    'page-change',
    'change',
    'current-change',
    'size-change',
    'page-size-change',
]);

// Sinkronisasi Halaman Aktif (Mendukung v-model, v-model:current-page, :page, :current-page)
const internalPage = ref(1);

const resolvedPropPage = computed(() => {
    if (typeof props.page === 'number' && !isNaN(props.page)) return props.page;
    if (typeof props.currentPage === 'number' && !isNaN(props.currentPage)) return props.currentPage;
    if (typeof props.modelValue === 'number' && !isNaN(props.modelValue)) return props.modelValue;
    return undefined;
});

const currentPage = computed(() => {
    return resolvedPropPage.value !== undefined ? resolvedPropPage.value : internalPage.value;
});

const jumperInput = ref(currentPage.value);
watch(currentPage, (val) => {
    jumperInput.value = val;
    internalPage.value = val;
});

// Penangan Custom Size Dropdown Popover
const isSizeOpen = ref(false);
const sizeDropdownRef = ref(null);

const selectPageSize = (sizeOption) => {
    if (props.disabled) return;
    isSizeOpen.value = false;
    emit('update:pageSize', sizeOption);
    emit('size-change', sizeOption);
    emit('page-size-change', sizeOption);
    goToPage(1);
};

const handleDocumentClick = (e) => {
    if (sizeDropdownRef.value && !sizeDropdownRef.value.contains(e.target)) {
        isSizeOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
});

// Perhitungan Matematika Paginasi
const totalPages = computed(() => {
    if (!props.total || props.total <= 0) return 1;
    return Math.ceil(props.total / props.pageSize) || 1;
});

const startItem = computed(() => {
    if (props.total <= 0) return 0;
    return (currentPage.value - 1) * props.pageSize + 1;
});

const endItem = computed(() => {
    return Math.min(currentPage.value * props.pageSize, props.total);
});

const formattedTotal = computed(() => {
    return props.total.toLocaleString('id-ID');
});

const layoutItems = computed(() => {
    return props.layout.split(',').map((item) => item.trim());
});

// Algoritma Pager (Angka Halaman & Ellipsis)
const pagerItems = computed(() => {
    const pageCount = totalPages.value;
    const current = currentPage.value;
    const items = [];

    if (pageCount <= 7) {
        for (let i = 1; i <= pageCount; i++) {
            items.push({ type: 'page', page: i });
        }
        return items;
    }

    let showPrevMore = false;
    let showNextMore = false;

    if (current > 4) {
        showPrevMore = true;
    }
    if (current < pageCount - 3) {
        showNextMore = true;
    }

    items.push({ type: 'page', page: 1 });

    if (showPrevMore) {
        items.push({ type: 'prev-ellipsis' });
    }

    let start = current - 1;
    let end = current + 1;

    if (current <= 4) {
        start = 2;
        end = 5;
    } else if (current >= pageCount - 3) {
        start = pageCount - 4;
        end = pageCount - 1;
    }

    for (let i = start; i <= end; i++) {
        if (i > 1 && i < pageCount) {
            items.push({ type: 'page', page: i });
        }
    }

    if (showNextMore) {
        items.push({ type: 'next-ellipsis' });
    }

    items.push({ type: 'page', page: pageCount });

    return items;
});

// Aksi Navigasi
const goToPage = (page) => {
    if (props.disabled) return;
    const targetPage = Math.max(1, Math.min(page, totalPages.value));
    if (targetPage !== currentPage.value) {
        internalPage.value = targetPage;
        emit('update:modelValue', targetPage);
        emit('update:currentPage', targetPage);
        emit('update:page', targetPage);
        emit('change', { page: targetPage, pageSize: props.pageSize });
        emit('current-change', targetPage);
        emit('page-change', targetPage);
    }
};

const handleJumperSubmit = () => {
    const val = parseInt(jumperInput.value, 10);
    if (!isNaN(val)) {
        goToPage(val);
    } else {
        jumperInput.value = currentPage.value;
    }
};
</script>

<style scoped>
/* Container Paginasi Mandiri HCIS Premium */
.hcis-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    font-family: var(--hcis-font-sans, inherit);
    font-size: var(--hcis-font-size-sm, 0.875rem);
    color: var(--hcis-text, var(--hcis-slate-700));
    user-select: none;
}

.hcis-pagination--disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* Mode Desktop Layout */
.hcis-pagination__desktop {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 12px);
    width: 100%;
    flex-wrap: wrap;
}

/* Total Data Count Pill Badge */
.hcis-pagination__total {
    margin-right: auto;
}

/*
 * Latarnya BUKAN surface-soft: itu warna latar halaman, jadi lencananya
 * hilang sama sekali (1,00:1) dan tepinya slate-100 hanya 1,05:1. Lencana
 * yang tidak terlihat sama saja dengan tidak ada.
 */
.hcis-pagination__total-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--hcis-space-1) var(--hcis-space-3);
    border-radius: var(--hcis-radius-pill);
    background-color: var(--hcis-surface);
    border: 1px solid var(--hcis-border-medium);
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-medium);
}
.hcis-pagination__total-badge strong {
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-weight: 700;
    margin: 0 3px;
}

/* Custom Size Dropdown Trigger & Floating Glass Menu */
.hcis-pagination__sizes {
    position: relative;
}

.hcis-pagination__size-wrapper {
    position: relative;
    display: inline-block;
}

.hcis-pagination__size-trigger {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 12px;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-medium);
    background-color: var(--hcis-surface);
    color: var(--hcis-text-strong);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: ease;
    box-shadow: 0 1px 2px rgb(var(--hcis-slate-900-rgb) / 0.05);
}

.hcis-pagination__size-trigger:hover:not(:disabled) {
    border-color: var(--hcis-brand-400, var(--hcis-brand-400));
    background-color: var(--hcis-brand-50, var(--hcis-violet-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-pagination__size-icon {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    transition: transform 200ms var(--hcis-ease);
}

.hcis-pagination__size-icon.is-open {
    transform: rotate(180deg);
}

/* Floating Glass Menu Popover */
.hcis-pagination__size-menu {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    z-index: 100;
    min-width: 145px;
    margin: 0;
    padding: 6px;
    list-style: none;
    background: rgb(var(--hcis-white-rgb) / 0.96);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid var(--hcis-border-medium);
    border-radius: var(--hcis-radius-md);
    box-shadow: var(--hcis-shadow-lg);
}

.hcis-pagination__size-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 10px;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text, var(--hcis-slate-700));
    cursor: pointer;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-pagination__size-option:hover {
    background-color: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-pagination__size-option.is-selected {
    background-color: var(--hcis-brand-100, var(--hcis-brand-100));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
    font-weight: 700;
}

/* Pager List & Items Ultra 3D Smooth */
.hcis-pagination__pager {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.hcis-pagination__item,
.hcis-pagination__btn {
    min-width: 2.25rem;
    height: 2.25rem;
    padding: 0 6px;
    border-radius: var(--hcis-radius-md);
    /*
     * Tepinya terlihat sejak diam, bukan baru saat disorot. Sebelumnya
     * transparent di atas halaman polos: deretan angka melayang tanpa batas,
     * dan tidak ada yang menandai bahwa itu tombol sampai kursor lewat.
     */
    border: 1px solid var(--hcis-border-medium);
    background: var(--hcis-surface);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-bold);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: var(--hcis-duration-press);
    transition-timing-function: var(--hcis-ease);
}

/* Varian bertumpuk: latarnya diredupkan satu tingkat, tepinya tetap terlihat.
   Sebelumnya surface-soft + border-subtle — keduanya warna latar halaman. */
.hcis-pagination--background .hcis-pagination__item,
.hcis-pagination--background .hcis-pagination__btn {
    background-color: var(--hcis-surface-soft);
    border-color: var(--hcis-border-medium);
}

.hcis-pagination__item:hover:not(:disabled):not(.is-active),
.hcis-pagination__btn:hover:not(:disabled) {
    background-color: var(--hcis-brand-50, var(--hcis-violet-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    border-color: var(--hcis-brand-200, var(--hcis-brand-200));
    transform: translateY(-1.5px);
    box-shadow: 0 2px 6px -1px rgb(var(--hcis-brand-600-rgb) / 0.12);
}

/* Item Aktif */
.hcis-pagination__item.is-active {
    background: linear-gradient(135deg, var(--hcis-brand-600, var(--hcis-brand-600)), var(--hcis-brand-700, var(--hcis-brand-700))) !important;
    color: var(--hcis-white) !important;
    border-color: transparent !important;
    font-weight: 700;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgb(var(--hcis-brand-600-rgb) / 0.45), 0 0 0 1px rgb(var(--hcis-white-rgb) / 0.3) inset;
}

.hcis-pagination__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

.hcis-pagination__item--ellipsis {
    cursor: pointer;
}
.hcis-pagination__dots {
    letter-spacing: -1px;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
}

/* Jumper Box & Input */
.hcis-pagination__jumper {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-pagination__jumper-box {
    display: inline-flex;
    align-items: center;
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border, var(--hcis-slate-300));
    background-color: var(--hcis-surface, var(--hcis-white));
    overflow: hidden;
    height: 2.25rem;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: ease;
}
.hcis-pagination__jumper-box:focus-within {
    border-color: var(--hcis-brand-600, var(--hcis-brand-600));
    box-shadow: 0 0 0 3px var(--hcis-brand-100, rgb(var(--hcis-brand-500-rgb) / 0.2));
}

.hcis-pagination__jumper-input {
    width: 2.75rem;
    height: 100%;
    padding: 0 4px;
    text-align: center;
    border: none;
    background: transparent;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-size: 0.875rem;
    font-weight: 700;
    outline: none;
}

.hcis-pagination__jumper-btn {
    width: 1.75rem;
    height: 100%;
    border: none;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-left: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}
.hcis-pagination__jumper-btn:hover:not(:disabled) {
    background-color: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

/* Transisi Dropdown Popover */
.hcis-pagination-pop-enter-active,
.hcis-pagination-pop-leave-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: var(--hcis-ease);
}
.hcis-pagination-pop-enter-from,
.hcis-pagination-pop-leave-to {
    opacity: 0;
    transform: translateY(6px) scale(0.95);
}

/* Size Variants */
.hcis-pagination--sm .hcis-pagination__item,
.hcis-pagination--sm .hcis-pagination__btn {
    min-width: 1.75rem;
    height: 1.75rem;
    font-size: 0.8125rem;
    border-radius: 6px;
}
.hcis-pagination--sm .hcis-pagination__size-trigger,
.hcis-pagination--sm .hcis-pagination__jumper-box {
    height: 1.75rem;
    font-size: 0.775rem;
}

/* Mobile Collapsed Mode */
.hcis-pagination__mobile-summary {
    display: none;
}

@media (max-width: 640px) {
    .hcis-pagination {
        padding: 0;
    }

    .hcis-pagination__desktop {
        display: none !important;
    }

    .hcis-pagination__mobile-summary {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 2px 0;
        gap: 0.5rem;
    }

    .hcis-pagination__mobile-text {
        font-size: 0.78125rem;
        color: var(--hcis-text-muted, var(--hcis-slate-600));
        text-align: center;
        line-height: 1.2;
    }

    .hcis-pagination__mobile-text strong {
        color: var(--hcis-text-strong, var(--hcis-slate-900));
        font-weight: 700;
    }

    .hcis-pagination--sm .hcis-pagination__mobile-text {
        font-size: 0.71875rem;
    }

    .hcis-pagination--sm .hcis-pagination__btn {
        min-width: 1.85rem;
        height: 1.85rem;
    }
}
</style>
