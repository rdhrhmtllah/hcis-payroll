<template>
    <div class="table-container">
        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
            <div class="loading-spinner"></div>
            <p class="loading-text">Memuat data...</p>
        </div>

        <!-- Table -->
        <div v-else class="table-responsive-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                'header-cell',
                                { sortable: column.sortable },
                            ]"
                            @click="
                                column.sortable ? sortTable(column.key) : null
                            "
                        >
                            <div class="header-content">
                                <span>{{ column.label }}</span>
                                <i
                                    v-if="column.sortable"
                                    class="sort-icon bi"
                                    :class="getSortIcon(column.key)"
                                ></i>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in sortedItems"
                        :key="item.id || index"
                        class="table-row"
                        :class="getRowClass(item)"
                    >
                        <td v-for="column in columns" :key="column.key">
                            <slot
                                :name="`cell(${column.key})`"
                                :item="item"
                                :index="index"
                                :value="getCellValue(item, column.key)"
                            >
                                {{ getCellValue(item, column.key) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State -->
            <div v-if="sortedItems.length === 0" class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-inbox"></i>
                </div>
                <h4 class="empty-title">Tidak Ada Data</h4>
                <p class="empty-description">Belum ada data yang tersedia</p>
            </div>
        </div>

        <!-- Pagination -->
        <Pagination
            v-if="showPagination && paginationEnabled"
            :page="page"
            :totalPages="totalPages"
            @change="$emit('change', $event)"
        />
    </div>
</template>

<script>
import Pagination from "./pagination.vue";

export default {
    name: "Table",
    components: {
        Pagination,
    },
    props: {
        columns: {
            type: Array,
            required: true,
            validator: (cols) => cols.every((col) => col.key && col.label),
        },
        items: {
            type: Array,
            default: () => [],
        },
        loading: {
            type: Boolean,
            default: false,
        },
        page: {
            type: Number,
            default: 1,
        },
        totalPages: {
            type: Number,
            default: 1,
        },
        sortBy: {
            type: String,
            default: "",
        },
        sortDirection: {
            type: String,
            default: "asc",
            validator: (val) => ["asc", "desc"].includes(val),
        },
        paginationEnabled: {
            type: Boolean,
            default: true,
        },
        rowClass: {
            type: [String, Function],
            default: "",
        },
    },
    emits: ["change", "sort"],
    data() {
        return {
            internalSortBy: this.sortBy,
            internalSortDirection: this.sortDirection,
        };
    },
    computed: {
        sortedItems() {
            if (!this.internalSortBy) return this.items;

            return [...this.items].sort((a, b) => {
                const aValue = this.getCellValue(a, this.internalSortBy);
                const bValue = this.getCellValue(b, this.internalSortBy);

                if (this.internalSortDirection === "asc") {
                    return aValue < bValue ? -1 : aValue > bValue ? 1 : 0;
                } else {
                    return aValue > bValue ? -1 : aValue < bValue ? 1 : 0;
                }
            });
        },
        showPagination() {
            return this.paginationEnabled && this.totalPages > 1;
        },
    },
    watch: {
        sortBy(newVal) {
            this.internalSortBy = newVal;
        },
        sortDirection(newVal) {
            this.internalSortDirection = newVal;
        },
    },
    methods: {
        getCellValue(item, key) {
            return key.split(".").reduce((obj, prop) => obj?.[prop], item);
        },
        sortTable(columnKey) {
            if (this.internalSortBy === columnKey) {
                this.internalSortDirection =
                    this.internalSortDirection === "asc" ? "desc" : "asc";
            } else {
                this.internalSortBy = columnKey;
                this.internalSortDirection = "asc";
            }
            this.$emit("sort", {
                sortBy: this.internalSortBy,
                sortDirection: this.internalSortDirection,
            });
        },
        getSortIcon(columnKey) {
            if (this.internalSortBy !== columnKey) {
                return "bi-arrow-down-up";
            }
            return this.internalSortDirection === "asc"
                ? "bi-arrow-up"
                : "bi-arrow-down";
        },
        getRowClass(item) {
            if (typeof this.rowClass === "function") {
                return this.rowClass(item);
            }
            return this.rowClass;
        },
    },
};
</script>

<style scoped>
.table-container {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

.loading-text {
    margin: 0;
    font-size: 0.9rem;
}

.table-responsive-wrapper {
    width: 100%;
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
    background: var(--surface);
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.modern-table th {
    position: sticky;
    top: 0;
    background-color: var(--surface-soft);
    z-index: 10;
    font-weight: 600;
    color: var(--text);
    border-bottom: 2px solid var(--border);
}

.header-cell {
    cursor: default;
}

.header-cell.sortable {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.header-cell.sortable:hover {
    background-color: var(--primary-light);
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.sort-icon {
    font-size: 0.75rem;
    color: var(--text-muted);
    transition: color 0.2s ease;
}

.header-cell.sortable:hover .sort-icon {
    color: var(--primary);
}

.table-row {
    transition: background-color 0.2s ease;
}

.table-row:hover {
    background-color: var(--surface-soft);
}

.table-row:last-child td {
    border-bottom: none;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-muted);
}

.empty-description {
    margin: 0;
    font-size: 0.9rem;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .modern-table {
        font-size: 0.8rem;
    }

    .modern-table th,
    .modern-table td {
        padding: 0.75rem 0.5rem;
    }

    .header-content {
        flex-direction: column;
        gap: 0.25rem;
        align-items: flex-start;
    }

    .sort-icon {
        align-self: flex-end;
    }
}

@media (max-width: 576px) {
    .table-responsive-wrapper {
        border-radius: 8px;
    }

    .modern-table {
        min-width: 600px;
    }

    .empty-state {
        padding: 2rem 1rem;
    }

    .empty-icon {
        font-size: 2.5rem;
    }
}
</style>
