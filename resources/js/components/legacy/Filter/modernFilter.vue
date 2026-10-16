<template>
    <div>
        <!-- Mobile Filter Trigger -->
        <Teleport to="body" :disabled="!isMobile">
            <div v-if="isMobile && config.mobileTrigger !== false" class="mobile-filter-trigger">
                <el-button class="btn-filter-mobile fade-in" type="primary" @click="openMobileFilter">
                    <el-icon class="btn-icon"><Filter /></el-icon>
                    <span>Filter</span>
                    <span v-if="activeFilterCount > 0" class="filter-badge">
                        {{ activeFilterCount }}
                    </span>
                </el-button>
            </div>
        </Teleport>

        <!-- Filter Panel -->
        <Teleport to="body" :disabled="!isMobile">
            <div :class="['filter-panel', { 'mobile-open': mobileFilterOpen }]">
                <!-- Quick Chips Bar (Extensible feature) -->
                <div
                    v-if="!isMobile && ((config.quickChips && config.quickChips.length) || $slots.chips || $slots.top)"
                    class="filter-quick-chips mb-3"
                >
                    <slot name="chips">
                        <slot name="top">
                            <div class="quick-chips-wrapper d-flex align-items-center gap-2 flex-wrap">
                                <button
                                    v-for="(chip, idx) in config.quickChips"
                                    :key="chip.id || chip.value || idx"
                                    type="button"
                                    class="quick-chip-btn"
                                    :class="{ active: isChipActive(chip) }"
                                    :style="getChipStyle(chip)"
                                    @click="handleChipClick(chip)"
                                >
                                    <span v-if="chip.icon" class="chip-ico">
                                        <i :class="chip.icon"></i>
                                    </span>
                                    <span class="chip-lbl">{{ chip.label }}</span>
                                    <span v-if="chip.badge !== undefined && chip.badge !== null && chip.badge !== false" class="chip-badge">
                                        {{ chip.badge }}
                                    </span>
                                </button>
                            </div>
                        </slot>
                    </slot>
                </div>

                <!-- Mobile Header -->
                <div v-if="isMobile" class="mobile-filter-header">
                    <div class="mobile-drawer-handle"></div>
                    <div class="mobile-filter-header-content">
                        <div class="d-flex align-items-center gap-2">
                            <div class="mf-ico"><i class="bi bi-sliders"></i></div>
                            <div>
                                <h5 class="mb-0 fw-bold text-dark fs-6">Filter & Saringan</h5>
                                <small class="text-muted" style="font-size: 0.72rem">
                                    {{ activeFilterCount > 0 ? `${activeFilterCount} saringan aktif` : 'Sesuaikan kriteria pencarian' }}
                                </small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <button
                                v-if="activeFilterCount > 0 && config.showReset"
                                type="button"
                                class="btn-reset-text"
                                @click="clearFilters"
                            >
                                Reset
                            </button>
                            <el-button class="btn-close-mobile ms-1" text @click="closeMobileFilter">
                                <el-icon><Close /></el-icon>
                            </el-button>
                        </div>
                    </div>
                </div>

                <!-- Top Row: Search & Top-Level Filter (Desktop) -->
                <div class="filter-search mb-3" v-if="!isMobile && (config.searchable || topLevelFilter)">
                    <div class="row g-3" :class="topLevelFilter ? 'align-items-end' : 'align-items-center'">
                        <!-- Search Input -->
                        <div :class="topLevelFilter ? 'col-12 col-md-9' : 'col-12'" v-if="config.searchable">
                            <el-input
                                v-model="localFilters.search"
                                :placeholder="config.searchPlaceholder || 'Search...'"
                                class="search-input-enhanced"
                                size="large"
                                @input="handleSearchInput"
                            >
                                <template #prefix>
                                    <el-icon><Search /></el-icon>
                                </template>
                                <template v-if="config.commandKey" #suffix>
                                    <button
                                        type="button"
                                        class="filter-cmdk"
                                        :title="config.commandLabel || 'Lompat cepat'"
                                        @click.stop="$emit('command')"
                                    >
                                        <kbd>{{ tombolMod }}</kbd><kbd>K</kbd>
                                    </button>
                                </template>
                            </el-input>
                        </div>

                        <!-- Top Level Filter (e.g. viewToggle) on Desktop -->
                        <div class="col-12 col-md-3" v-if="topLevelFilter">
                            <div class="filter-item">
                                <label
                                    class="filter-label"
                                    v-if="topLevelFilter.label && topLevelFilter.type !== 'viewToggle'"
                                    >{{ topLevelFilter.label }}</label
                                >
                                <component
                                    :is="getFilterComponent(topLevelFilter.type)"
                                    :filter="topLevelFilter"
                                    :value="localFilters[topLevelFilter.key]"
                                    :is-mobile="isMobile"
                                    @update:value="updateFilter(topLevelFilter.key, $event)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Filters - Desktop Grid Layout -->
                <div v-if="!isMobile && gridFilters.length > 0" class="desktop-filters-grid">
                    <div class="filter-grid-row">
                        <div
                            v-for="filter in gridFilters"
                            :key="filter.key"
                            :class="['filter-grid-item', getFilterColClass(filter)]"
                        >
                            <div class="filter-item">
                                <label class="filter-label">{{ filter.label }}</label>
                                <component
                                    :is="getFilterComponent(filter.type)"
                                    :filter="filter"
                                    :value="localFilters[filter.key]"
                                    :is-mobile="isMobile"
                                    @update:value="updateFilter(filter.key, $event)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Filters - Mobile Layout -->
                <div v-else-if="isMobile" class="mobile-filters-container">
                    <div v-if="config.searchable" class="mf-field-card">
                        <label class="filter-label"><i class="bi bi-search me-1 text-primary"></i> Kata Kunci Pencarian</label>
                        <el-input
                            v-model="localFilters.search"
                            :placeholder="config.searchPlaceholder || 'Cari kata kunci...'"
                            class="search-input-enhanced"
                            size="large"
                            clearable
                            @input="handleSearchInput"
                        >
                            <template #prefix>
                                <el-icon><Search /></el-icon>
                            </template>
                        </el-input>
                    </div>

                    <!-- Horizontally Scrollable Quick Chips on Mobile -->
                    <div v-if="config.quickChips && config.quickChips.length" class="mf-quick-chips-scroll">
                        <label class="filter-label mb-2"><i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Saringan Cepat</label>
                        <div class="mobile-chips-row">
                            <button
                                v-for="(chip, idx) in config.quickChips"
                                :key="chip.id || chip.value || idx"
                                type="button"
                                class="quick-chip-btn"
                                :class="{ active: isChipActive(chip) }"
                                :style="getChipStyle(chip)"
                                @click="handleChipClick(chip)"
                            >
                                <span v-if="chip.icon" class="chip-ico"><i :class="chip.icon"></i></span>
                                <span class="chip-lbl">{{ chip.label }}</span>
                                <span v-if="chip.badge !== undefined && chip.badge !== null && chip.badge !== false" class="chip-badge">{{ chip.badge }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-for="filter in config.filters" :key="filter.key" class="mf-field-card">
                        <label class="filter-label">{{ filter.label }}</label>
                        <component
                            :is="getFilterComponent(filter.type)"
                            :filter="filter"
                            :value="localFilters[filter.key]"
                            :is-mobile="isMobile"
                            @update:value="updateFilter(filter.key, $event)"
                        />
                    </div>
                </div>

                <!-- Active Filters Tags (Desktop only or clean block) -->
                <div class="active-filters-tags" v-if="!isMobile && config.showActiveFilters && activeFilterTags.length > 0">
                    <div class="tags-header">
                        <div class="tags-header-left">
                            <el-icon class="tags-icon"><Filter /></el-icon>
                            <span class="tags-label">Active Filters</span>
                            <span class="tags-count">{{ activeFilterTags.length }}</span>
                        </div>
                        <el-button
                            class="btn-clear-tags"
                            text
                            size="small"
                            @click="clearAllTags"
                            v-if="config.showClearAll"
                        >
                            <el-icon style="margin-right: 4px;"><RefreshRight /></el-icon>
                            Clear All
                        </el-button>
                    </div>
                    <div class="tags-container">
                        <el-tag
                            v-for="tag in activeFilterTags"
                            :key="tag.key"
                            closable
                            size="default"
                            class="filter-tag"
                            effect="plain"
                            @close="removeFilter(tag.key)"
                        >
                            <span class="tag-content">
                                <span class="tag-label">{{ tag.label }}:</span>
                                <span class="tag-value" :title="tag.value">{{ truncateTagValue(tag.value) }}</span>
                            </span>
                        </el-tag>
                    </div>
                </div>

                <!-- Filter Actions Desktop -->
                <div class="filter-actions" v-if="!isMobile && config.showActions">
                    <div class="filter-info">
                        <span class="filter-text" v-if="config.showResultsCount">
                            <el-icon><List /></el-icon>
                            Showing {{ resultsCount }} results
                        </span>
                    </div>
                    <div class="filter-buttons">
                        <slot name="actions"></slot>
                        <el-button
                            class="btn-clear-filters"
                            @click="clearFilters"
                            :icon="ikonReset"
                            v-if="config.showReset"
                        >
                            Reset All
                        </el-button>
                    </div>
                </div>

                <!-- Sticky Mobile Footer Bar -->
                <div v-if="isMobile" class="mobile-filter-footer">
                    <button type="button" class="btn-apply-mobile" @click="applyMobileFilters">
                        <i class="bi bi-check-circle-fill me-1.5"></i>
                        Terapkan {{ activeFilterCount > 0 ? `(${activeFilterCount} Saringan)` : '' }}
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Mobile Overlay -->
        <Teleport to="body" :disabled="!isMobile">
            <div v-if="isMobile && mobileFilterOpen" class="mobile-filter-overlay" @click.self="closeMobileFilter"></div>
        </Teleport>
    </div>
</template>

<script>
import { debounce } from 'lodash';
import {
    ElInput,
    ElSelect,
    ElOption,
    ElDatePicker,
    ElCheckbox,
    ElCheckboxGroup,
    ElRadio,
    ElRadioGroup,
    ElSlider,
    ElSwitch,
    ElCascader,
    ElTag,
    ElButton,
    ElIcon,
} from 'element-plus';
// import "element-plus/dist/index.css";

import { Filter, Search, RefreshRight, Close, List } from '@element-plus/icons-vue';

// Komponen Filter untuk Element Plus
const ElementInputFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElInput,
    },
    template: `
        <el-input
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :placeholder="filter.placeholder || 'Enter value...'"
            :size="filter.size || 'default'"
            :clearable="filter.clearable !== false"
        />
    `,
};

const ElementSelectFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElSelect,
        ElOption,
    },
    template: `
        <el-select
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :placeholder="filter.placeholder || 'Select...'"
            :size="filter.size || 'default'"
            :clearable="filter.clearable !== false"
            :multiple="filter.multiple || false"
            :filterable="filter.filterable || false"
            :remote="filter.remote || false"
            :remote-method="filter.remoteMethod"
            :remote-show-suffix="filter.remote || false"
            :loading="filter.loading || false"
            :collapse-tags="filter.multiple || false"
            :collapse-tags-tooltip="filter.multiple || false"
            :max-collapse-tags="filter.maxCollapseTags || 1"
            style="width: 100%"
        >
            <el-option
                v-for="option in filter.options"
                :key="option.value"
                :label="option.label"
                :value="option.value"
                :disabled="option.disabled || false"
            />
        </el-select>
    `,
};

const ElementDatePickerFilter = {
    props: ['filter', 'value', 'isMobile'],
    emits: ['update:value'],
    components: {
        ElDatePicker,
    },
    computed: {
        effectiveType() {
            if (this.filter.dateType) return this.filter.dateType;
            if (this.filter.type === 'daterange') return 'daterange';
            if (this.filter.type === 'monthrange') return 'monthrange';
            return 'date';
        },
        isRangeType() {
            return this.effectiveType === 'daterange' || this.effectiveType === 'monthrange';
        },
        startDate: {
            get() {
                return Array.isArray(this.value) ? this.value[0] || '' : '';
            },
            set(val) {
                const currentEnd = Array.isArray(this.value) ? this.value[1] || '' : '';
                this.$emit('update:value', [val, currentEnd]);
            },
        },
        endDate: {
            get() {
                return Array.isArray(this.value) ? this.value[1] || '' : '';
            },
            set(val) {
                const currentStart = Array.isArray(this.value) ? this.value[0] || '' : '';
                this.$emit('update:value', [currentStart, val]);
            },
        },
    },
    template: `
        <div v-if="isMobile && isRangeType" class="row g-2 w-100 m-0">
            <div class="col-6 p-0 pe-1">
                <el-date-picker
                    v-model="startDate"
                    type="date"
                    :placeholder="filter.startPlaceholder || 'Tgl Mulai'"
                    :size="filter.size || 'default'"
                    :clearable="filter.clearable !== false"
                    :format="filter.format"
                    :value-format="filter.valueFormat || 'YYYY-MM-DD'"
                    style="width: 100%"
                />
            </div>
            <div class="col-6 p-0 ps-1">
                <el-date-picker
                    v-model="endDate"
                    type="date"
                    :placeholder="filter.endPlaceholder || 'Tgl Selesai'"
                    :size="filter.size || 'default'"
                    :clearable="filter.clearable !== false"
                    :format="filter.format"
                    :value-format="filter.valueFormat || 'YYYY-MM-DD'"
                    style="width: 100%"
                />
            </div>
        </div>
        <el-date-picker
            v-else
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :type="effectiveType"
            :placeholder="filter.placeholder || 'Pilih tanggal'"
            :start-placeholder="filter.startPlaceholder || 'Tgl Mulai'"
            :end-placeholder="filter.endPlaceholder || 'Tgl Selesai'"
            :range-separator="filter.rangeSeparator || '-'"
            :size="filter.size || 'default'"
            :clearable="filter.clearable !== false"
            :format="filter.format"
            :value-format="filter.valueFormat"
            style="width: 100%"
        />
    `,
};

const ElementButtonFilter = {
    props: ['filter'],
    components: {
        ElButton,
        ElIcon,
    },
    methods: {
        handleClick() {
            if (typeof this.filter?.action === 'function') {
                this.filter.action();
            }
        },
    },
    template: `
        <el-button
            :type="filter.buttonClass === 'btn-primary' ? 'primary' : 'default'"
            :size="filter.size || 'default'"
            class="btn-filter-action w-100"
            @click="handleClick"
        >
            <i
                v-if="filter.buttonIcon"
                :class="filter.buttonIcon + ' me-2 d-flex justify-content-center align-items-center'"
            ></i>
            {{ filter.buttonText || 'Action' }}
        </el-button>
    `,
};

const ElementCheckboxGroupFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElCheckbox,
    },
    template: `
        <el-checkbox-group
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
        >
            <el-checkbox
                v-for="option in filter.options"
                :key="option.value"
                :label="option.value"
            >
                {{ option.label }}
            </el-checkbox>
        </el-checkbox-group>
    `,
};

const ElementRadioGroupFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElRadio,
    },
    template: `
        <el-radio-group
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
        >
            <el-radio
                v-for="option in filter.options"
                :key="option.value"
                :label="option.value"
            >
                {{ option.label }}
            </el-radio>
        </el-radio-group>
    `,
};

const ElementSliderFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElSlider,
    },
    template: `
        <div class="slider-filter">
            <el-slider
                :model-value="value"
                @update:model-value="$emit('update:value', $event)"
                :min="filter.min || 0"
                :max="filter.max || 100"
                :step="filter.step || 1"
                :show-input="filter.showInput !== false"
                :range="filter.range || false"
            />
            <div v-if="filter.showValue" class="slider-value">
                Current: {{ value }}
            </div>
        </div>
    `,
};

const ElementSwitchFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElSwitch,
    },
    template: `
        <el-switch
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :active-text="filter.activeText || 'Yes'"
            :inactive-text="filter.inactiveText || 'No'"
        />
    `,
};

const ElementCascaderFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    components: {
        ElCascader,
    },
    template: `
        <el-cascader
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :options="filter.options"
            :placeholder="filter.placeholder || 'Select...'"
            :size="filter.size || 'default'"
            :clearable="filter.clearable !== false"
            :show-all-levels="filter.showAllLevels !== false"
            style="width: 100%"
        />
    `,
};

const ElementViewToggleFilter = {
    props: ['filter', 'value'],
    emits: ['update:value'],
    template: `
        <div class="view-toggles d-flex p-1 border w-100" style="border-radius: var(--radius); height: 42px; background: #f8fafc; border-color: var(--border) !important;">
            <button
                v-for="option in filter.options"
                :key="option.value"
                class="btn btn-sm flex-fill d-flex align-items-center justify-content-center m-0"
                :class="value === option.value ? 'bg-white shadow-sm font-semibold text-dark' : 'bg-transparent text-muted'"
                style="border-radius: calc(var(--radius) - 2px); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border: none; padding: 0 12px;"
                @click="$emit('update:value', option.value)"
            >
                <i v-if="option.icon" :class="option.icon" :style="{ marginRight: option.label ? '6px' : '0' }"></i>
                <span v-if="option.label">{{ option.label }}</span>
            </button>
        </div>
    `,
};

export default {
    name: 'EnhancedFilterPanel',
    components: {
        // Element Plus Components
        ElInput,
        ElSelect,
        ElOption,
        ElDatePicker,
        ElCheckbox,
        ElCheckboxGroup,
        ElRadio,
        ElRadioGroup,
        ElSlider,
        ElSwitch,
        ElCascader,
        ElTag,
        ElButton,
        ElIcon,

        // Icons
        Filter,
        Search,
        RefreshRight,
        Close,
        List,

        // Komponen filter custom
        ElementInputFilter,
        ElementSelectFilter,
        ElementDatePickerFilter,
        ElementButtonFilter,
        ElementCheckboxGroupFilter,
        ElementRadioGroupFilter,
        ElementSliderFilter,
        ElementSwitchFilter,
        ElementCascaderFilter,
        ElementViewToggleFilter,
    },
    emits: ['filters-update', 'search', 'reset', 'clear-all', 'command'],
    props: {
        config: {
            type: Object,
            required: true,
            default: () => ({
                searchable: true,
                searchPlaceholder: 'Search...',
                searchCol: 3, // Default 3 kolom untuk search
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: true,
                filters: [],
            }),
        },
        filters: {
            type: Object,
            /* Bukan required. Sebagian pemakai — KontenPickerModal salah
               satunya — memang tak punya nilai awal untuk diberikan, dan
               komponen ini sudah menanganinya sejak dulu: localFilters lahir
               dari { ...this.filters }, yang untuk undefined menghasilkan objek
               kosong. Jadi "required" hanya menghasilkan peringatan pada
               keadaan yang justru berjalan benar — dan peringatan yang selalu
               muncul membuat yang sungguhan ikut terabaikan. */
            default: () => ({}),
        },
        resultsCount: {
            type: Number,
            default: 0,
        },
    },
    data() {
        return {
            isMobile: false,
            mobileFilterOpen: false,
            localFilters: { ...this.filters },
        };
    },
    computed: {
        /* Dipakai sebagai NILAI prop `icon`, bukan sebagai tag. Pendaftaran di
           `components:` hanya melayani bentuk tag <RefreshRight />; sebuah
           `:icon="..."` diselesaikan terhadap instance, dan di sana namanya
           tidak pernah ada — itulah sumber peringatan "was accessed during
           render but is not defined on instance". */
        ikonReset() {
            return RefreshRight;
        },
        tombolMod() {
            return /mac|iphone|ipad/i.test(navigator.userAgent) ? '⌘' : 'Ctrl';
        },
        topLevelFilter() {
            return (this.config?.filters || []).find((f) => f.type === 'viewToggle' || f.layout === 'top');
        },
        gridFilters() {
            const list = this.config?.filters || [];
            if (this.isMobile) return list;
            return list.filter((f) => f.key !== this.topLevelFilter?.key);
        },
        activeFilterCount() {
            let count = 0;
            if (this.localFilters?.search?.trim()) count++;

            (this.config?.filters || []).forEach((filter) => {
                const value = this.localFilters ? this.localFilters[filter.key] : undefined;
                if (this.hasFilterValue(value, filter.type)) {
                    count++;
                }
            });

            return count;
        },

        activeFilterTags() {
            const tags = [];

            // Search tag
            if (this.localFilters?.search?.trim()) {
                tags.push({
                    key: 'search',
                    label: 'Search',
                    value: this.localFilters.search,
                });
            }

            // Filter tags
            (this.config?.filters || []).forEach((filter) => {
                const value = this.localFilters ? this.localFilters[filter.key] : undefined;
                if (this.hasFilterValue(value, filter.type)) {
                    let displayValue = this.getFilterDisplayValue(value, filter);
                    if (displayValue) {
                        tags.push({
                            key: filter.key,
                            label: filter.label,
                            value: displayValue,
                        });
                    }
                }
            });

            return tags;
        },
    },
    methods: {
        isChipActive(chip) {
            if (typeof chip.active === 'function') {
                return chip.active(this.localFilters);
            }
            if (!chip.key) return false;
            const val = this.localFilters[chip.key];
            if (Array.isArray(val)) {
                return val.includes(chip.value);
            }
            if (chip.value === 'SEMUA' || chip.value === '' || chip.value === null) {
                return (!val || val === 'SEMUA' || val === '');
            }
            return val === chip.value;
        },
        getChipStyle(chip) {
            if (!chip.color) return {};
            const isActive = this.isChipActive(chip);
            return {
                '--chip-color': chip.color,
                ...(isActive ? { borderColor: chip.color, color: chip.color, backgroundColor: `${chip.color}15` } : {})
            };
        },
        handleChipClick(chip) {
            if (typeof chip.onClick === 'function') {
                chip.onClick(this.localFilters, this);
                return;
            }
            if (!chip.key) return;

            let nextVal;
            const currentVal = this.localFilters[chip.key];

            if (chip.toggleable) {
                nextVal = currentVal === chip.value ? (chip.defaultValue ?? '') : chip.value;
            } else {
                nextVal = chip.value;
            }

            this.updateFilter(chip.key, nextVal);
        },
        /**
         * Ctrl/Cmd+K dimiliki komponen ini supaya halaman tak perlu memasang
         * listener sendiri — dua pemilik akan saling membatalkan (toggle ganda).
         * preventDefault dipasang karena sebagian peramban memakai Cmd+K untuk
         * bilah pencarian bawaannya.
         */
        handleCommandKey(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                this.$emit('command');
            }
        },
        getFilterColClass(filter) {
            // Prioritas: class responsive custom (contoh: "col-md-2 col-6")
            if (typeof filter.colClass === 'string' && filter.colClass.trim()) {
                return filter.colClass.trim();
            }

            const col = Number(filter.col || 12);
            const safeCol = Number.isFinite(col) ? Math.min(12, Math.max(1, Math.round(col))) : 12;

            // Default: full di mobile, sesuai col di desktop
            return `col-12 col-md-${safeCol}`;
        },

        getFilterComponent(type) {
            const components = {
                input: 'ElementInputFilter',
                select: 'ElementSelectFilter',
                date: 'ElementDatePickerFilter',
                checkbox: 'ElementCheckboxGroupFilter',
                radio: 'ElementRadioGroupFilter',
                viewToggle: 'ElementViewToggleFilter',
                slider: 'ElementSliderFilter',
                switch: 'ElementSwitchFilter',
                cascader: 'ElementCascaderFilter',
                // Kompatibilitas dengan tipe lama
                multiselect: 'ElementSelectFilter',
                daterange: 'ElementDatePickerFilter',
                dateselect: 'ElementDatePickerFilter',
                button: 'ElementButtonFilter',
            };
            return components[type] || 'ElementInputFilter';
        },

        hasFilterValue(value, type) {
            if (type === 'button' || type === 'viewToggle') return false;
            if (value === null || value === undefined || value === '') return false;

            // Nilai array (multi-select, checkbox, daterange, slider): kosong = tidak aktif.
            if (Array.isArray(value)) {
                if (type === 'daterange') return value.length === 2 && value[0] && value[1];
                if (type === 'slider') return value[0] !== value[1];
                return value.length > 0;
            }

            return true;
        },

        getFilterDisplayValue(value, filter) {
            if (!value) return '';

            switch (filter.type) {
                case 'select':
                case 'multiselect':
                    if (Array.isArray(value)) {
                        const selectedOptions = filter.options?.filter((opt) => value.includes(opt.value));
                        return selectedOptions.map((opt) => opt.label).join(', ');
                    } else {
                        const option = filter.options?.find((opt) => opt.value === value);
                        return option?.label || value;
                    }

                case 'checkbox':
                    if (!Array.isArray(value) || value.length === 0) return '';
                    const selectedCheckboxes = filter.options?.filter((opt) => value.includes(opt.value));
                    return selectedCheckboxes.map((opt) => opt.label).join(', ');

                case 'radio':
                case 'viewToggle':
                    const radioOption = filter.options?.find((opt) => opt.value === value);
                    return radioOption?.label || value;

                case 'date':
                case 'dateselect':
                    return this.formatDate(value);

                case 'daterange':
                    if (!Array.isArray(value) || value.length !== 2) return '';
                    return `${this.formatDate(value[0])} - ${this.formatDate(value[1])}`;

                case 'slider':
                    if (Array.isArray(value)) {
                        return `${value[0]} - ${value[1]}`;
                    }
                    return String(value);

                case 'switch':
                    return value ? filter.activeText || 'Yes' : filter.inactiveText || 'No';

                case 'cascader':
                    if (Array.isArray(value)) {
                        return value.join(' / ');
                    }
                    return String(value);

                default:
                    return String(value);
            }
        },

        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        },

        handleSearchInput(value) {
            this.localFilters.search = value;
            this.debouncedEmitUpdate();
        },

        updateFilter(key, value) {
            this.localFilters[key] = value;
            if (!this.isMobile) {
                this.debouncedEmitUpdate();
            }
        },

        removeFilter(key) {
            const filterConfig = (this.config?.filters || []).find((f) => f.key === key);
            if (filterConfig) {
                const defaultValue = this.getDefaultFilterValue(filterConfig.type, filterConfig.multiple);
                this.localFilters[key] = defaultValue;
            } else if (key === 'search') {
                this.localFilters.search = '';
            }

            this.$emit('filters-update', { ...this.localFilters });
        },

        clearAllTags() {
            const clearedFilters = { ...this.localFilters };

            this.activeFilterTags.forEach((tag) => {
                if (tag.key === 'search') {
                    clearedFilters.search = '';
                } else {
                    const filterConfig = (this.config?.filters || []).find((f) => f.key === tag.key);
                    if (filterConfig) {
                        clearedFilters[tag.key] = this.getDefaultFilterValue(filterConfig.type, filterConfig.multiple);
                    }
                }
            });

            this.localFilters = { ...clearedFilters };
            this.$emit('filters-update', clearedFilters);
        },

        getDefaultFilterValue(type, multiple = false) {
            if (multiple) return []; // select multi-pilih dikosongkan ke array
            switch (type) {
                case 'checkbox':
                case 'multiselect':
                case 'daterange':
                    return [];
                case 'slider':
                    return [0, 100];
                case 'switch':
                    return false;
                default:
                    return '';
            }
        },

        clearFilters() {
            const clearedFilters = {
                search: this.config?.searchable ? '' : undefined,
            };

            (this.config?.filters || []).forEach((filter) => {
                clearedFilters[filter.key] = this.getDefaultFilterValue(filter.type, filter.multiple);
            });

            this.localFilters = { ...clearedFilters };
            this.$emit('filters-update', clearedFilters);

            if (this.isMobile) {
                this.closeMobileFilter();
            }
        },

        applyMobileFilters() {
            this.$emit('filters-update', { ...this.localFilters });
            this.closeMobileFilter();
        },

        openMobileFilter() {
            this.mobileFilterOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeMobileFilter() {
            this.mobileFilterOpen = false;
            document.body.style.overflow = '';
        },

        handleResize() {
            this.isMobile = window.innerWidth < 768;
        },

        truncateTagValue(value, maxLength = 40) {
            if (!value) return '';
            const str = String(value);
            if (str.length <= maxLength) return str;
            return str.substring(0, maxLength) + '...';
        },

        debouncedEmitUpdate: debounce(function () {
            this.$emit('filters-update', { ...this.localFilters });
        }, 500),
    },
    watch: {
        filters: {
            handler(newFilters) {
                this.localFilters = { ...newFilters };
            },
            deep: true,
        },
    },
    mounted() {
        this.handleResize();
        window.addEventListener('resize', this.handleResize);
        if (this.config.commandKey) window.addEventListener('keydown', this.handleCommandKey);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('keydown', this.handleCommandKey);
        document.body.style.overflow = '';
    },
};
</script>

<style scoped>
/* CSS Variables */
:root {
    --primary: #5b6abf;
    --primary-light: #eef0ff;
    --primary-dark: #3d4a99;
    --surface: #ffffff;
    --surface-soft: #f7f8fc;
    --border: #e2e5f1;
    --dark: #1e2340;
    --text: #4a5068;
    --text-muted: #8b90a8;
    --danger: #ef4444;
    --success: #22c55e;
    --shadow: 0 1px 3px 0 rgba(30, 35, 64, 0.04), 0 1px 2px -1px rgba(30, 35, 64, 0.06);
    --shadow-lg: 0 10px 25px -5px rgba(30, 35, 64, 0.08), 0 8px 10px -6px rgba(30, 35, 64, 0.04);
    --radius: 10px;
    --radius-lg: 14px;
    --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mobile Filter Trigger */
.mobile-filter-trigger {
    position: fixed;
    bottom: 1.5rem;
    right: 1.25rem;
    z-index: 1050;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.btn-filter-mobile {
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    height: 44px;
    padding: 0 1.25rem 0 1rem;
    border-radius: 999px !important;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: #ffffff !important;
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    position: relative;
    box-shadow: 0 12px 28px -6px rgba(79, 70, 229, 0.55), 0 4px 12px rgba(15, 23, 42, 0.15) !important;
    backdrop-filter: blur(8px);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, background 0.2s ease !important;
    cursor: pointer;
    animation: fabBounceIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.btn-filter-mobile:hover,
.btn-filter-mobile:focus {
    background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%) !important;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 18px 36px -6px rgba(79, 70, 229, 0.65), 0 6px 16px rgba(15, 23, 42, 0.2) !important;
}

.btn-filter-mobile:active {
    transform: translateY(0) scale(0.96);
    box-shadow: 0 8px 20px -4px rgba(79, 70, 229, 0.5) !important;
}

.btn-icon {
    font-size: 1.05rem;
    display: inline-flex;
    align-items: center;
}

.filter-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: #ffffff;
    border: 2px solid #ffffff;
    border-radius: 999px;
    min-width: 21px;
    height: 21px;
    padding: 0 5px;
    font-size: 0.7rem;
    font-weight: 800;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.55);
    animation: badgePulse 2s infinite;
}

@keyframes fabBounceIn {
    0% { opacity: 0; transform: scale(0.7) translateY(12px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

@keyframes badgePulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
    50% { box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
}

/* Filter Panel */
.filter-panel {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.25rem;
    transition: var(--transition);
    border: 1px solid var(--border);
}

/* Search Section */
.filter-cmdk {
    display: inline-flex; align-items: center; gap: 2px;
    border: 0; background: transparent; cursor: pointer; padding: 0;
}
/* `color` WAJIB eksplisit: Bootstrap menyetel `kbd { color: var(--bs-body-bg) }`
   alias putih, sehingga tanpa ini isinya putih di atas putih dan yang tampak
   hanya kotak kosong. */
.filter-cmdk kbd {
    display: inline-block; background: #fff; color: #94a3b8;
    border: 1px solid #dbe1ea; border-radius: 4px;
    padding: 0 0.3rem; font-size: 0.66rem; line-height: 1.6;
    font-family: inherit; font-weight: 700;
}
.filter-cmdk:hover kbd { color: #4338ca; border-color: #c7d2fe; background: #eef2ff; }

.filter-search {
    margin-bottom: 0.75rem;
}

.search-input-enhanced {
    width: 100%;
}

/* Desktop Grid Layout — CSS Grid */
.desktop-filters-grid {
    margin-bottom: 0.5rem;
}

.filter-grid-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1rem;
    align-items: flex-end;
}

.filter-grid-item {
    min-width: 0;
}

/* Map col-md-X to flex widths */
.filter-grid-item.col-12.col-md-1 { flex: 0 0 calc(8.333% - 1rem); }
.filter-grid-item.col-12.col-md-2 { flex: 0 0 calc(16.666% - 1rem); min-width: 140px; }
.filter-grid-item.col-12.col-md-3 { flex: 0 0 calc(25% - 1rem); min-width: 170px; }
.filter-grid-item.col-12.col-md-4 { flex: 0 0 calc(33.333% - 0.75rem); min-width: 200px; }
.filter-grid-item.col-12.col-md-5 { flex: 0 0 calc(41.666% - 0.75rem); }
.filter-grid-item.col-12.col-md-6 { flex: 0 0 calc(50% - 0.5rem); }
.filter-grid-item.col-12.col-md-7 { flex: 0 0 calc(58.333% - 0.5rem); }
.filter-grid-item.col-12.col-md-8 { flex: 0 0 calc(66.666% - 0.5rem); }
.filter-grid-item.col-12.col-md-9 { flex: 0 0 calc(75% - 0.25rem); }
.filter-grid-item.col-12.col-md-10 { flex: 0 0 calc(83.333% - 0.25rem); }
.filter-grid-item.col-12.col-md-11 { flex: 0 0 calc(91.666% - 0.25rem); }
.filter-grid-item.col-12.col-md-12 { flex: 0 0 100%; }

/* Fallback — also support raw col-md-X and col-X classes */
.filter-grid-item.col-2,
.filter-grid-item.col-md-2 { flex: 0 0 calc(16.666% - 1rem); min-width: 140px; }
.filter-grid-item.col-3,
.filter-grid-item.col-md-3 { flex: 0 0 calc(25% - 1rem); min-width: 170px; }
.filter-grid-item.col-4,
.filter-grid-item.col-md-4 { flex: 0 0 calc(33.333% - 0.75rem); min-width: 200px; }
.filter-grid-item.col-6,
.filter-grid-item.col-md-6 { flex: 0 0 calc(50% - 0.5rem); }

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.filter-label {
    font-weight: 600;
    color: var(--text);
    font-size: 0.8rem;
    letter-spacing: 0.01em;
    line-height: 1.4;
    text-transform: capitalize;
}

.btn-filter-action {
    height: 40px;
    font-weight: 600;
}

/* Mobile Styles */
@media (max-width: 767px) {
    .mobile-filter-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(6px);
        z-index: 1055;
        animation: mobileOverlayFadeIn 0.25s ease-out;
    }

    .filter-panel {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        max-height: 85vh;
        margin: 0;
        border-radius: 24px 24px 0 0;
        z-index: 1060;
        overflow-y: auto;
        overflow-x: hidden !important;
        transform: translateY(100%);
        transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
        padding: 0.5rem 1.25rem 1.25rem;
        border: none;
        box-shadow: 0 -12px 40px rgba(15, 23, 42, 0.22);
        background: #ffffff;
        display: flex;
        flex-direction: column;
    }

    .filter-panel.mobile-open {
        transform: translateY(0);
    }

    .mobile-drawer-handle {
        width: 38px;
        height: 5px;
        background: #cbd5e1;
        border-radius: 999px;
        margin: 0.25rem auto 0.65rem;
        flex-shrink: 0;
    }

    .mobile-filter-header {
        position: sticky;
        top: -0.5rem;
        z-index: 10;
        background: #ffffff;
        padding-top: 0.15rem;
        padding-bottom: 0.85rem;
        margin-bottom: 0.85rem;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
    }

    .mobile-filter-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .mf-ico {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(99, 102, 241, 0.1);
        color: #4f46e5;
        display: grid;
        place-items: center;
        font-size: 1rem;
    }

    .btn-reset-text {
        font-size: 0.78rem;
        font-weight: 700;
        color: #6366f1;
        background: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        padding: 0.22rem 0.65rem;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .btn-reset-text:active {
        background: rgba(99, 102, 241, 0.2);
    }

    .btn-close-mobile {
        color: #94a3b8;
        border: none;
        background: transparent;
        font-size: 1.1rem;
    }

    .mobile-filters-container {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        margin-bottom: 0.75rem;
    }

    .mf-field-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.85rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .mf-field-card .filter-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.1rem;
        display: flex;
        align-items: center;
    }

    .mf-quick-chips-scroll {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.75rem 0.85rem;
    }

    .mobile-chips-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        -webkit-overflow-scrolling: touch;
    }
    .mobile-chips-row::-webkit-scrollbar {
        display: none;
    }
    .mobile-chips-row .quick-chip-btn {
        flex-shrink: 0;
        white-space: nowrap;
    }

    .filter-grid-item {
        flex: 0 0 100% !important;
        min-width: 0 !important;
    }

    .mobile-filter-footer {
        position: sticky;
        bottom: -1.25rem;
        background: #ffffff;
        padding-top: 0.85rem;
        padding-bottom: 0.5rem;
        margin-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
        z-index: 10;
        flex-shrink: 0;
    }

    .btn-apply-mobile {
        width: 100%;
        height: 46px;
        border-radius: 12px !important;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%) !important;
        border: none !important;
        font-weight: 700 !important;
        font-size: 0.92rem !important;
        color: #ffffff !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 8px 20px -2px rgba(79, 70, 229, 0.45) !important;
        transition: transform 0.15s ease !important;
        cursor: pointer;
    }
    .btn-apply-mobile:active {
        transform: scale(0.98);
    }

    /* Clean Mobile Popper Adjustments */
    .el-picker__popper,
    .el-select__popper {
        max-width: 95vw !important;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25) !important;
        border-radius: 12px !important;
    }
}

/* Desktop Styles */
@media (min-width: 768px) {
    .mobile-filter-trigger {
        display: none;
    }

    .filter-panel {
        padding: 1.25rem 1.5rem;
    }
}

/* Active Filters Tags */
.active-filters-tags {
    margin-top: 1rem;
    margin-bottom: 0.25rem;
    padding: 0.875rem 1rem;
    background: linear-gradient(135deg, var(--surface-soft) 0%, #f0f1fa 100%);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.tags-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.625rem;
}

.tags-header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tags-icon {
    color: var(--primary);
    font-size: 0.9rem;
}

.tags-label {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.8rem;
    letter-spacing: 0.01em;
}

.tags-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    line-height: 1;
}

.btn-clear-tags {
    color: var(--primary);
    font-size: 0.775rem;
    font-weight: 600;
    padding: 0;
    height: auto;
    display: flex;
    align-items: center;
    transition: var(--transition);
}

.btn-clear-tags:hover {
    color: var(--primary-dark);
}

.tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.filter-tag {
    background: var(--surface) !important;
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    transition: var(--transition);
    border-radius: 20px !important;
    max-width: 280px;
    padding: 0 10px !important;
    height: 28px !important;
    line-height: 26px !important;
}

.filter-tag:hover {
    border-color: var(--primary) !important;
    background: var(--primary-light) !important;
    color: var(--primary-dark) !important;
    box-shadow: 0 1px 4px rgba(91, 106, 191, 0.15);
}

.tag-content {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 240px;
    overflow: hidden;
}

.tag-label {
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--primary-dark);
    white-space: nowrap;
    flex-shrink: 0;
}

.tag-value {
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
    display: inline-block;
}

/* Filter Actions */
.filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    margin-top: 0.5rem;
    border-top: 1px solid var(--border);
}

.filter-info {
    flex: 1;
}

.filter-text {
    font-weight: 400;
    color: var(--text-muted);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-buttons {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    align-items: center;
}

.btn-clear-filters {
    border-color: var(--border);
    color: var(--text-muted);
    transition: var(--transition);
}

.btn-clear-filters:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-apply-mobile {
    width: 100%;
}

/* Mobile Overlay */
.mobile-filter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(30, 35, 64, 0.4);
    z-index: 999;
    backdrop-filter: blur(4px);
}

/* Animation */
@keyframes slideInBottom {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.filter-panel.mobile-open {
    animation: slideInBottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Slider Filter Styles */
.slider-filter {
    padding: 0.5rem 0;
}

.slider-value {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
    text-align: center;
}

/* Custom Element Plus Styles - Safe & Clean */
:deep(.el-input),
:deep(.el-select),
:deep(.el-date-editor),
:deep(.el-cascader) {
    width: 100% !important;
    vertical-align: middle !important;
}

:deep(.el-input__wrapper),
:deep(.el-select__wrapper),
:deep(.el-select .el-input__wrapper),
:deep(.el-date-editor.el-input__wrapper),
:deep(.el-range-editor.el-input__wrapper) {
    border-radius: 10px !important;
    min-height: 40px !important;
    height: 40px !important;
    box-sizing: border-box !important;
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

:deep(.el-input__wrapper:hover),
:deep(.el-select__wrapper:hover),
:deep(.el-select .el-input__wrapper:hover),
:deep(.el-date-editor.el-input__wrapper:hover),
:deep(.el-range-editor.el-input__wrapper:hover) {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 1px #6366f1 !important;
}

:deep(.el-input__wrapper.is-focus),
:deep(.el-select__wrapper.is-focused),
:deep(.el-select .el-input__wrapper.is-focus),
:deep(.el-date-editor.el-input__wrapper.is-active),
:deep(.el-range-editor.el-input__wrapper.is-active) {
    border-color: #4f46e5 !important;
    box-shadow: 0 0 0 1px #4f46e5, 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
}

/* Collapse tags styling for multi-select */
:deep(.el-select .el-select__tags) {
    flex-wrap: nowrap !important;
    overflow: hidden;
}

:deep(.el-select .el-tag) {
    max-width: 120px !important;
    border-radius: 6px !important;
}

:deep(.el-select .el-tag .el-tag__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.el-select .el-select__tags-text) {
    max-width: 80px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
}

:deep(.el-checkbox-group) {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

:deep(.el-radio-group) {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

:deep(.el-tag) {
    cursor: pointer;
    transition: var(--transition);
}

:deep(.el-tag .el-tag__close) {
    color: inherit;
    margin-left: 0.25rem;
    transition: var(--transition);
}

:deep(.el-tag .el-tag__close:hover) {
    background-color: rgba(91, 106, 191, 0.15);
    color: var(--primary-dark);
}

:deep(.el-button) {
    border-radius: var(--radius);
    font-weight: 500;
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .filter-grid-item {
        min-width: 130px !important;
    }
}

@media (max-width: 768px) {
    .filter-buttons {
        gap: 0px;
    }
    .el-button + .el-button {
        margin-left: 0px;
    }
}

@media (max-width: 768px) {
    .filter-actions {
        flex-wrap: wrap;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        margin-top: 12px;
    }

    .filter-info {
        min-width: 120px;
    }

    .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        flex: 1;
        justify-content: flex-end;
    }

    .filter-buttons > * {
        flex: 0 1 auto;
    }

    .filter-buttons .btn-apply-mobile {
        flex: 1 1 100%;
        order: 10;
        margin-top: 4px;
    }

    .active-filters-tags {
        margin-top: 0.75rem;
    }

    .filter-tag {
        max-width: 220px;
    }

    .tag-value {
        max-width: 120px;
    }
}

/* Scrollbar Styling */
.filter-panel::-webkit-scrollbar {
    width: 4px;
}

.filter-panel::-webkit-scrollbar-track {
    background: var(--surface-soft);
}

.filter-panel::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 2px;
}

.filter-panel::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* Quick Chips Feature */
.filter-quick-chips {
    width: 100%;
}
.quick-chips-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.quick-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    user-select: none;
}
.quick-chip-btn:hover {
    color: #334155;
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateY(-1px);
}
.quick-chip-btn.active {
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
    border-color: #6366f1;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
}
.quick-chip-btn .chip-ico {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}
.quick-chip-btn .chip-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    background: #ffe4e6;
    color: #be123c;
    border: 1px solid #fecaca;
    line-height: 1;
}
</style>

