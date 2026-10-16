<template>
    <div>
        <!-- Mobile Filter Trigger -->
        <div v-if="isMobile" class="mobile-filter-trigger">
            <el-button
                class="btn-filter-mobile"
                type="primary"
                @click="openMobileFilter"
            >
                <el-icon class="btn-icon"><Filter /></el-icon>
                <span>Filter</span>
                <span v-if="activeFilterCount > 0" class="filter-badge">
                    {{ activeFilterCount }}
                </span>
            </el-button>
        </div>

        <!-- Filter Panel -->
        <div :class="['filter-panel', { 'mobile-open': mobileFilterOpen }]">
            <!-- Mobile Header -->
            <div v-if="isMobile" class="mobile-filter-header">
                <div class="mobile-filter-header-content">
                    <h5>Filters</h5>
                    <el-button
                        class="btn-close-mobile"
                        text
                        @click="closeMobileFilter"
                    >
                        <el-icon><Close /></el-icon>
                    </el-button>
                </div>
            </div>

            <!-- Search Input -->
            <div class="filter-search" v-if="config.searchable">
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
                </el-input>
            </div>

            <!-- Dynamic Filters - Desktop Grid Layout -->
            <div v-if="!isMobile" class="desktop-filters-grid">
                <div
                    class="filter-row"
                    v-for="(row, rowIndex) in desktopFilterRows"
                    :key="rowIndex"
                >
                    <div
                        class="filter-item"
                        v-for="filter in row"
                        :key="filter.key"
                    >
                        <label class="filter-label">{{ filter.label }}</label>
                        <component
                            :is="getFilterComponent(filter.type)"
                            :filter="filter"
                            :value="filters[filter.key]"
                            @update:value="updateFilter(filter.key, $event)"
                        />
                    </div>
                </div>
            </div>

            <!-- Dynamic Filters - Mobile Layout -->
            <div v-else class="mobile-filters-container">
                <div
                    class="filter-group"
                    v-for="filter in config.filters"
                    :key="filter.key"
                >
                    <label class="filter-label">{{ filter.label }}</label>
                    <component
                        :is="getFilterComponent(filter.type)"
                        :filter="filter"
                        :value="filters[filter.key]"
                        @update:value="updateFilter(filter.key, $event)"
                    />
                </div>
            </div>

            <!-- Active Filters Tags -->
            <div class="active-filters-tags" v-if="activeFilterTags.length > 0">
                <div class="tags-header">
                    <span class="tags-label">Active Filters</span>
                    <el-button
                        class="btn-clear-tags"
                        text
                        size="small"
                        @click="clearAllTags"
                    >
                        Clear All
                    </el-button>
                </div>
                <div class="tags-container">
                    <el-tag
                        v-for="tag in activeFilterTags"
                        :key="tag.key"
                        closable
                        size="large"
                        class="filter-tag"
                        @close="removeFilter(tag.key)"
                    >
                        <span class="tag-content">
                            <span class="tag-label">{{ tag.label }}:</span>
                            <span class="tag-value">{{ tag.value }}</span>
                        </span>
                    </el-tag>
                </div>
            </div>

            <!-- Filter Actions -->
            <div class="filter-actions">
                <div class="filter-info">
                    <span class="filter-text" v-if="config.showResultsCount">
                        <el-icon><List /></el-icon>
                        Showing {{ resultsCount }} results
                    </span>
                </div>
                <div class="filter-buttons">
                    <el-button
                        class="btn-clear-filters"
                        @click="clearFilters"
                        :icon="RefreshRight"
                    >
                        Reset All
                    </el-button>
                    <el-button
                        v-if="isMobile"
                        type="primary"
                        class="btn-apply-mobile"
                        @click="applyMobileFilters"
                    >
                        Apply Filters
                    </el-button>
                </div>
            </div>
        </div>

        <!-- Mobile Overlay -->
        <div
            v-if="isMobile && mobileFilterOpen"
            class="mobile-filter-overlay"
            @click="closeMobileFilter"
        ></div>
    </div>
</template>

<script>
// Import spesifik yang dibutuhkan saja
import { debounce } from "lodash";

// Komponen Filter untuk Element Plus
const ElementInputFilter = {
    props: ["filter", "value"],
    emits: ["update:value"],
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
    props: ["filter", "value"],
    emits: ["update:value"],
    template: `
        <el-select
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :placeholder="filter.placeholder || 'Select...'"
            :size="filter.size || 'default'"
            :clearable="filter.clearable !== false"
            :multiple="filter.multiple || false"
            :filterable="filter.filterable || false"
            style="width: 100%"
        >
            <el-option
                v-for="option in filter.options"
                :key="option.value"
                :label="option.label"
                :value="option.value"
            />
        </el-select>
    `,
};

const ElementDatePickerFilter = {
    props: ["filter", "value"],
    emits: ["update:value"],
    template: `
        <el-date-picker
            :model-value="value"
            @update:model-value="$emit('update:value', $event)"
            :type="filter.dateType || 'date'"
            :placeholder="filter.placeholder || 'Select date'"
            :size="filter.size || 'default'"
            :clearable="filter.clearable !== false"
            :format="filter.format"
            :value-format="filter.valueFormat"
            style="width: 100%"
        />
    `,
};

const ElementCheckboxGroupFilter = {
    props: ["filter", "value"],
    emits: ["update:value"],
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
    props: ["filter", "value"],
    emits: ["update:value"],
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
    props: ["filter", "value"],
    emits: ["update:value"],
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
    props: ["filter", "value"],
    emits: ["update:value"],
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
    props: ["filter", "value"],
    emits: ["update:value"],
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

export default {
    name: "EnhancedFilterPanel",
    components: {
        // Hanya komponen yang benar-benar digunakan
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

        // Icons yang digunakan
        Filter,
        Search,
        RefreshRight,
        Close,
        List,

        // Komponen filter custom
        ElementInputFilter,
        ElementSelectFilter,
        ElementDatePickerFilter,
        ElementCheckboxGroupFilter,
        ElementRadioGroupFilter,
        ElementSliderFilter,
        ElementSwitchFilter,
        ElementCascaderFilter,
    },
    props: {
        config: {
            type: Object,
            required: true,
            default: () => ({
                searchable: true,
                searchPlaceholder: "Search...",
                showResultsCount: true,
                filters: [],
            }),
        },
        filters: {
            type: Object,
            required: true,
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
        activeFilterCount() {
            let count = 0;
            if (this.localFilters.search?.trim()) count++;

            this.config.filters.forEach((filter) => {
                const value = this.localFilters[filter.key];
                if (this.hasFilterValue(value, filter.type)) {
                    count++;
                }
            });

            return count;
        },

        activeFilterTags() {
            const tags = [];

            // Search tag
            if (this.localFilters.search?.trim()) {
                tags.push({
                    key: "search",
                    label: "Search",
                    value: this.localFilters.search,
                });
            }

            // Filter tags
            this.config.filters.forEach((filter) => {
                const value = this.localFilters[filter.key];
                if (this.hasFilterValue(value, filter.type)) {
                    let displayValue = this.getFilterDisplayValue(
                        value,
                        filter
                    );
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

        desktopFilterRows() {
            const rows = [];
            const filtersPerRow = 3;

            for (
                let i = 0;
                i < this.config.filters.length;
                i += filtersPerRow
            ) {
                rows.push(this.config.filters.slice(i, i + filtersPerRow));
            }

            return rows;
        },
    },
    methods: {
        getFilterComponent(type) {
            const components = {
                input: "ElementInputFilter",
                select: "ElementSelectFilter",
                date: "ElementDatePickerFilter",
                checkbox: "ElementCheckboxGroupFilter",
                radio: "ElementRadioGroupFilter",
                slider: "ElementSliderFilter",
                switch: "ElementSwitchFilter",
                cascader: "ElementCascaderFilter",
                // Kompatibilitas dengan tipe lama
                multiselect: "ElementSelectFilter",
                daterange: "ElementDatePickerFilter",
                dateselect: "ElementDatePickerFilter",
            };
            return components[type] || "ElementInputFilter";
        },

        hasFilterValue(value, type) {
            if (value === null || value === undefined || value === "")
                return false;

            if (type === "checkbox" || type === "multiselect") {
                return Array.isArray(value) && value.length > 0;
            }

            if (type === "daterange") {
                return (
                    Array.isArray(value) &&
                    value.length === 2 &&
                    value[0] &&
                    value[1]
                );
            }

            if (type === "slider" && Array.isArray(value)) {
                return value[0] !== value[1];
            }

            return true;
        },

        getFilterDisplayValue(value, filter) {
            if (!value) return "";

            switch (filter.type) {
                case "select":
                case "multiselect":
                    if (Array.isArray(value)) {
                        const selectedOptions = filter.options?.filter((opt) =>
                            value.includes(opt.value)
                        );
                        return selectedOptions
                            .map((opt) => opt.label)
                            .join(", ");
                    } else {
                        const option = filter.options?.find(
                            (opt) => opt.value === value
                        );
                        return option?.label || value;
                    }

                case "checkbox":
                    if (!Array.isArray(value) || value.length === 0) return "";
                    const selectedCheckboxes = filter.options?.filter((opt) =>
                        value.includes(opt.value)
                    );
                    return selectedCheckboxes
                        .map((opt) => opt.label)
                        .join(", ");

                case "radio":
                    const radioOption = filter.options?.find(
                        (opt) => opt.value === value
                    );
                    return radioOption?.label || value;

                case "date":
                case "dateselect":
                    return this.formatDate(value);

                case "daterange":
                    if (!Array.isArray(value) || value.length !== 2) return "";
                    return `${this.formatDate(value[0])} - ${this.formatDate(
                        value[1]
                    )}`;

                case "slider":
                    if (Array.isArray(value)) {
                        return `${value[0]} - ${value[1]}`;
                    }
                    return String(value);

                case "switch":
                    return value
                        ? filter.activeText || "Yes"
                        : filter.inactiveText || "No";

                case "cascader":
                    if (Array.isArray(value)) {
                        return value.join(" / ");
                    }
                    return String(value);

                default:
                    return String(value);
            }
        },

        formatDate(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
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
            const filterConfig = this.config.filters.find((f) => f.key === key);
            if (filterConfig) {
                const defaultValue = this.getDefaultFilterValue(
                    filterConfig.type
                );
                this.localFilters[key] = defaultValue;
            } else if (key === "search") {
                this.localFilters.search = "";
            }

            this.$emit("filters-update", { ...this.localFilters });
        },

        clearAllTags() {
            const clearedFilters = { ...this.localFilters };

            this.activeFilterTags.forEach((tag) => {
                if (tag.key === "search") {
                    clearedFilters.search = "";
                } else {
                    const filterConfig = this.config.filters.find(
                        (f) => f.key === tag.key
                    );
                    if (filterConfig) {
                        clearedFilters[tag.key] = this.getDefaultFilterValue(
                            filterConfig.type
                        );
                    }
                }
            });

            this.localFilters = { ...clearedFilters };
            this.$emit("filters-update", clearedFilters);
        },

        getDefaultFilterValue(type) {
            switch (type) {
                case "checkbox":
                case "multiselect":
                case "daterange":
                    return [];
                case "slider":
                    return [0, 100];
                case "switch":
                    return false;
                default:
                    return "";
            }
        },

        clearFilters() {
            const clearedFilters = {
                search: this.config.searchable ? "" : undefined,
            };

            this.config.filters.forEach((filter) => {
                clearedFilters[filter.key] = this.getDefaultFilterValue(
                    filter.type
                );
            });

            this.localFilters = { ...clearedFilters };
            this.$emit("filters-update", clearedFilters);

            if (this.isMobile) {
                this.closeMobileFilter();
            }
        },

        applyMobileFilters() {
            this.$emit("filters-update", { ...this.localFilters });
            this.closeMobileFilter();
        },

        openMobileFilter() {
            this.mobileFilterOpen = true;
            document.body.style.overflow = "hidden";
        },

        closeMobileFilter() {
            this.mobileFilterOpen = false;
            document.body.style.overflow = "";
        },

        handleResize() {
            this.isMobile = window.innerWidth < 768;
        },

        debouncedEmitUpdate: debounce(function () {
            this.$emit("filters-update", { ...this.localFilters });
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
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
        document.body.style.overflow = "";
    },
};
</script>

<style scoped>
/* CSS Variables - Sesuaikan dengan tema Anda */
:root {
    --primary: #1890ff;
    --primary-light: #e6f7ff;
    --primary-dark: #096dd9;
    --surface: #ffffff;
    --surface-soft: #fafafa;
    --border: #d9d9d9;
    --dark: #262626;
    --text: #595959;
    --text-muted: #8c8c8c;
    --danger: #ff4d4f;
    --success: #52c41a;
    --shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03),
        0 1px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px 0 rgba(0, 0, 0, 0.02);
    --shadow-lg: 0 8px 16px 0 rgba(0, 0, 0, 0.08),
        0 4px 8px 0 rgba(0, 0, 0, 0.04);
    --radius: 6px;
    --radius-lg: 8px;
    --transition: all 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
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
}

/* Filter Panel */
.filter-panel {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
    transition: var(--transition);
    border: 1px solid var(--border);
}

/* Search Section */
.filter-search {
    margin-bottom: 1.5rem;
}

.search-input-enhanced {
    width: 100%;
}

/* Desktop Grid Layout */
.desktop-filters-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    align-items: end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-weight: 500;
    color: var(--dark);
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    line-height: 1.4;
}

/* Mobile Styles */
@media (max-width: 767px) {
    .filter-panel {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 85vh;
        margin: 0;
        border-radius: 16px 16px 0 0;
        z-index: 1000;
        overflow-y: auto;
        transform: translateY(100%);
        transition: transform 0.3s ease;
        padding: 1rem 1.25rem 2rem;
        border: none;
        border-top: 1px solid var(--border);
    }

    .filter-panel.mobile-open {
        transform: translateY(0);
    }

    .mobile-filter-header {
        position: sticky;
        top: 0;
        background: var(--surface);
        z-index: 10;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .mobile-filter-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .mobile-filter-header h5 {
        margin: 0;
        color: var(--dark);
        font-size: 1.25rem;
        font-weight: 600;
    }

    .btn-close-mobile {
        color: var(--text-muted);
        border: none;
        background: transparent;
    }

    .mobile-filters-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
}

/* Desktop Styles */
@media (min-width: 768px) {
    .mobile-filter-trigger {
        display: none;
    }

    .filter-panel {
        padding: 1.5rem;
    }
}

/* Active Filters Tags */
.active-filters-tags {
    margin: 1.5rem 0;
    padding: 1.25rem;
    background: var(--surface-soft);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.tags-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.tags-label {
    font-weight: 500;
    color: var(--dark);
    font-size: 0.875rem;
}

.btn-clear-tags {
    color: var(--primary);
    font-size: 0.8rem;
    font-weight: 500;
    padding: 0;
    height: auto;
}

.tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-tag {
    background: var(--primary-light);
    border: 1px solid var(--primary-light);
    color: var(--primary-dark);
    transition: var(--transition);
    border-radius: 4px;
}

.filter-tag:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
}

.tag-content {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.tag-label {
    font-weight: 500;
    font-size: 0.8rem;
}

.tag-value {
    font-size: 0.8rem;
}

/* Filter Actions */
.filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border);
}

.filter-info {
    flex: 1;
}

.filter-text {
    font-weight: 400;
    color: var(--text-muted);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-buttons {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.btn-clear-filters {
    border-color: var(--border);
    color: var(--text-muted);
}

.btn-clear-filters:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-apply-mobile {
    width: 100%;
    margin-top: 1rem;
}

/* Mobile Overlay */
.mobile-filter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.45);
    z-index: 999;
    backdrop-filter: blur(2px);
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

/* Custom Element Plus Styles untuk konsistensi tema */
:deep(.el-input__wrapper) {
    border-radius: var(--radius) !important;
    box-shadow: 0 0 0 1px var(--border) !important;
    transition: var(--transition) !important;
}

:deep(.el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px var(--primary) !important;
}

:deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px var(--primary), 0 0 0 3px rgba(24, 144, 255, 0.1) !important;
}

:deep(.el-select .el-input__wrapper) {
    border-radius: var(--radius) !important;
}

:deep(.el-date-editor .el-input__wrapper) {
    border-radius: var(--radius) !important;
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
    border-radius: var(--radius);
}

:deep(.el-tag .el-tag__close) {
    color: inherit;
    margin-left: 0.25rem;
}

:deep(.el-tag .el-tag__close:hover) {
    background-color: rgba(255, 255, 255, 0.3);
    color: inherit;
}

:deep(.el-button) {
    border-radius: var(--radius);
    font-weight: 500;
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .filter-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .filter-row {
        grid-template-columns: 1fr;
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
</style>
