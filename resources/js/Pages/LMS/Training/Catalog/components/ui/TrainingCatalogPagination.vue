<template>
    <div class="catalog-pagination" v-if="totalPages > 1">
        <div class="pagination-meta">
            <span>Items per Page</span>
            <strong>{{ itemsPerPage }}</strong>
        </div>

        <div class="pagination-controls">
            <button
                type="button"
                class="nav-link"
                :disabled="currentPage === 1"
                @click="$emit('page-change', currentPage - 1)"
            >
                <i class="bi bi-chevron-left"></i>
                Previous
            </button>

            <button
                v-for="page in visiblePages"
                :key="page"
                type="button"
                class="page-link"
                :class="{ active: currentPage === page }"
                @click="$emit('page-change', page)"
            >
                {{ page }}
            </button>

            <button
                type="button"
                class="nav-link next"
                :disabled="currentPage === totalPages"
                @click="$emit('page-change', currentPage + 1)"
            >
                Next
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: "TrainingCatalogPagination",
    props: {
        currentPage: {
            type: Number,
            default: 1,
        },
        totalPages: {
            type: Number,
            default: 1,
        },
        itemsPerPage: {
            type: Number,
            default: 10,
        },
    },
    emits: ["page-change"],
    computed: {
        visiblePages() {
            if (this.totalPages <= 4) {
                return Array.from(
                    { length: this.totalPages },
                    (_, index) => index + 1,
                );
            }

            const start = Math.max(1, this.currentPage - 1);
            const end = Math.min(this.totalPages, start + 3);
            const adjustedStart = Math.max(1, end - 3);

            return Array.from(
                { length: end - adjustedStart + 1 },
                (_, index) => adjustedStart + index,
            );
        },
    },
};
</script>

<style scoped>
.catalog-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1.75rem;
}

.pagination-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--hcis-slate-500);
    font-size: 0.9rem;
}

.pagination-meta strong {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.nav-link,
.page-link {
    min-width: 2.15rem;
    height: 2.15rem;
    border-radius: 10px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-600);
    font-size: 0.9rem;
    font-weight: 600;
}

.nav-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.35rem;
}

.page-link.active {
    background: #d6a449;
    color: var(--hcis-white);
}

.nav-link.next {
    color: #d6a449;
}

.nav-link:disabled,
.page-link:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
</style>
