<template>
    <div class="pagination-container">
        <div class="pagination-info">
            Menampilkan {{ startItem }}-{{ endItem }} dari {{ totalItems }} data
        </div>

        <div class="pagination-controls">
            <button
                class="pagination-btn pagination-prev"
                :disabled="currentPage === 1"
                @click="goToPage(currentPage - 1)"
            >
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="pagination-pages">
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    class="pagination-page"
                    :class="{
                        active: page === currentPage,
                        ellipsis: page === '...',
                    }"
                    :disabled="page === '...'"
                    @click="page !== '...' && goToPage(page)"
                >
                    {{ page }}
                </button>
            </div>

            <button
                class="pagination-btn pagination-next"
                :disabled="currentPage === totalPages"
                @click="goToPage(currentPage + 1)"
            >
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <div v-if="showPageSize" class="pagination-size">
            <select v-model="internalPageSize" class="page-size-select">
                <option value="10">10 / halaman</option>
                <option value="25">25 / halaman</option>
                <option value="50">50 / halaman</option>
                <option value="100">100 / halaman</option>
            </select>
        </div>
    </div>
</template>

<script>
export default {
    name: "Pagination",
    props: {
        page: {
            type: Number,
            default: 1,
        },
        totalPages: {
            type: Number,
            default: 1,
        },
        totalItems: {
            type: Number,
            default: 0,
        },
        pageSize: {
            type: Number,
            default: 10,
        },
        showPageSize: {
            type: Boolean,
            default: false,
        },
        maxVisiblePages: {
            type: Number,
            default: 5,
        },
    },
    emits: ["change", "page-size-change"],
    data() {
        return {
            currentPage: this.page,
            internalPageSize: this.pageSize,
        };
    },
    computed: {
        startItem() {
            return (this.currentPage - 1) * this.internalPageSize + 1;
        },
        endItem() {
            const end = this.currentPage * this.internalPageSize;
            return end > this.totalItems ? this.totalItems : end;
        },
        visiblePages() {
            const pages = [];
            const half = Math.floor(this.maxVisiblePages / 2);
            let start = Math.max(1, this.currentPage - half);
            let end = Math.min(
                this.totalPages,
                start + this.maxVisiblePages - 1
            );

            // Adjust start if we're near the end
            if (end - start + 1 < this.maxVisiblePages) {
                start = Math.max(1, end - this.maxVisiblePages + 1);
            }

            // First page and ellipsis
            if (start > 1) {
                pages.push(1);
                if (start > 2) {
                    pages.push("...");
                }
            }

            // Page numbers
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            // Last page and ellipsis
            if (end < this.totalPages) {
                if (end < this.totalPages - 1) {
                    pages.push("...");
                }
                pages.push(this.totalPages);
            }

            return pages;
        },
    },
    watch: {
        page(newPage) {
            this.currentPage = newPage;
        },
        internalPageSize(newSize) {
            this.$emit("page-size-change", parseInt(newSize));
            // Reset to first page when page size changes
            this.currentPage = 1;
            this.$emit("change", 1);
        },
    },
    methods: {
        goToPage(page) {
            if (page < 1 || page > this.totalPages || page === this.currentPage)
                return;

            this.currentPage = page;
            this.$emit("change", page);
        },
    },
};
</script>

<style scoped>
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-top: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text);
}

.pagination-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-pages {
    display: flex;
    gap: 0.25rem;
}

.pagination-page {
    min-width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text);
}

.pagination-page:hover:not(.active):not(.ellipsis):not(:disabled) {
    background: var(--surface-soft);
    border-color: var(--primary-light);
}

.pagination-page.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-page.ellipsis {
    cursor: default;
    background: transparent;
    border: none;
    min-width: 24px;
}

.pagination-page:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.pagination-size {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-size-select {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.page-size-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .pagination-controls {
        order: -1;
    }

    .pagination-info {
        order: 1;
    }

    .pagination-size {
        order: 2;
    }
}

@media (max-width: 576px) {
    .pagination-pages {
        gap: 0.125rem;
    }

    .pagination-page,
    .pagination-btn {
        min-width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }

    .page-size-select {
        padding: 0.375rem 0.5rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .pagination-pages {
        flex-wrap: wrap;
        justify-content: center;
    }

    .pagination-page.ellipsis {
        display: none;
    }
}

/* Animation */
.pagination-page {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Focus styles for accessibility */
.pagination-btn:focus-visible,
.pagination-page:focus-visible,
.page-size-select:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
</style>
