<template>
    <div class="training-toolbar-wrap">
        <div class="catalog-breadcrumbs">
            <button
                type="button"
                class="crumb-link"
                @click="$emit('category-change', 'All')"
            >
                Training
            </button>
            <span class="crumb-separator">/</span>
            <span class="crumb-current">Training Catalog</span>
        </div>

        <div class="category-row">
            <button
                v-for="category in categoryOptions"
                :key="category.label"
                type="button"
                class="category-chip"
                :class="{ active: activeCategory === category.label }"
                @click="$emit('category-change', category.label)"
            >
                <span>{{ category.label }}</span>
                <span class="category-count">{{ category.count }}</span>
            </button>
        </div>

        <div class="training-toolbar">
            <div class="toolbar-meta">
                <div class="results-pill">
                    <i class="bi bi-journal-richtext"></i>
                    <span>{{ totalResults }} pelatihan tersedia</span>
                </div>
            </div>

            <div class="toolbar-actions">
                <label class="toolbar-select">
                    <i class="bi bi-funnel"></i>
                    <select
                        :value="activeStatus"
                        @change="$emit('status-change', $event.target.value)"
                    >
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </label>

                <label class="toolbar-select">
                    <i class="bi bi-sort-down"></i>
                    <select
                        :value="activeSort"
                        @change="$emit('sort-change', $event.target.value)"
                    >
                        <option
                            v-for="sort in sorts"
                            :key="sort.value"
                            :value="sort.value"
                        >
                            {{ sort.label }}
                        </option>
                    </select>
                </label>

                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input
                        class="search-input"
                        type="text"
                        placeholder="Search"
                        :value="searchQuery"
                        @input="$emit('search', $event.target.value)"
                    />
                </div>

                <label class="toolbar-select compact">
                    <span class="compact-label">Show</span>
                    <select
                        :value="itemsPerPage"
                        @change="
                            $emit(
                                'per-page-change',
                                Number($event.target.value),
                            )
                        "
                    >
                        <option
                            v-for="size in perPageOptions"
                            :key="size"
                            :value="size"
                        >
                            {{ size }}
                        </option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "TrainingCatalogToolbar",
    props: {
        categories: {
            type: Array,
            default: () => [],
        },
        activeCategory: {
            type: String,
            default: "All",
        },
        activeStatus: {
            type: String,
            default: "all",
        },
        activeSort: {
            type: String,
            default: "featured",
        },
        searchQuery: {
            type: String,
            default: "",
        },
        itemsPerPage: {
            type: Number,
            default: 10,
        },
        totalResults: {
            type: Number,
            default: 0,
        },
    },
    emits: [
        "search",
        "category-change",
        "status-change",
        "sort-change",
        "per-page-change",
    ],
    data() {
        return {
            statuses: [
                { label: "All Status", value: "all" },
                { label: "Belum Terdaftar", value: "not-started" },
                { label: "Kelas Berlangsung", value: "in-progress" },
                { label: "Kelas Selesai", value: "completed" },
            ],
            sorts: [
                { label: "Sort by", value: "featured" },
                { label: "Title A-Z", value: "title-asc" },
                { label: "Progress Tertinggi", value: "progress-desc" },
                { label: "Terbaru", value: "newest" },
            ],
            perPageOptions: [6, 8, 10, 12],
        };
    },
    computed: {
        categoryOptions() {
            const normalized = this.categories.map((category) => ({
                label: category.label,
                count: category.count || 0,
            }));

            return [{ label: "All", count: this.totalResults }, ...normalized];
        },
    },
};
</script>

<style scoped>
.training-toolbar-wrap {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.catalog-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.92rem;
}

.crumb-link,
.crumb-current {
    color: #1e293b;
    font-weight: 700;
}

.crumb-link {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
}

.crumb-separator {
    color: #94a3b8;
}

.category-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.2rem;
}

.category-row::-webkit-scrollbar {
    height: 0;
}

.category-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 0.95rem;
    font-weight: 500;
    padding: 0.1rem 0;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.category-chip.active {
    color: #d6a449;
    border-bottom-color: #d6a449;
}

.category-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.2rem;
    height: 1.2rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0 0.3rem;
}

.category-chip.active .category-count {
    background: rgba(214, 164, 73, 0.12);
    color: #b7791f;
}

.training-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
    border-radius: 16px;
    padding: 12px 16px;
    gap: 16px;
    flex-wrap: wrap;
}

.toolbar-meta {
    display: flex;
    align-items: center;
}

.results-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border-radius: 999px;
    padding: 0.65rem 0.95rem;
    background: #f8fafc;
    border: 1px solid #edf2f7;
    color: #475569;
    font-size: 0.88rem;
    font-weight: 600;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    flex: 1;
    justify-content: flex-end;
}

.toolbar-select {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    min-height: 42px;
    padding: 0 0.9rem;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
}

.toolbar-select select {
    border: none;
    background: transparent;
    color: inherit;
    font-size: 0.9rem;
    font-weight: 500;
    outline: none;
    cursor: pointer;
}

.toolbar-select.compact {
    background: #f8fafc;
}

.compact-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #64748b;
}

.search-wrapper {
    position: relative;
    min-width: 220px;
    flex: 1 1 220px;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.95rem;
}

.search-input {
    width: 100%;
    height: 42px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 50px;
    padding: 0 16px 0 42px;
    font-size: 0.9rem;
    color: #1e293b;
    transition: all 0.2s;
    outline: none;
}

.search-input:focus {
    background: #ffffff;
    border-color: #5338ff;
    box-shadow: 0 0 0 3px rgba(83, 56, 255, 0.1);
}

.search-input::placeholder {
    color: #94a3b8;
}

@media (max-width: 768px) {
    .training-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-actions {
        justify-content: stretch;
    }

    .toolbar-select,
    .search-wrapper {
        width: 100%;
    }

    .toolbar-select select {
        width: 100%;
    }
}
</style>
