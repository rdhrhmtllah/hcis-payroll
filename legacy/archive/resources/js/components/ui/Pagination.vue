<template>
    <div class="pagination-container" v-if="totalPages > 1">
        <button
            class="page-btn"
            @click="changePage(currentPage - 1)"
            :disabled="currentPage === 1"
        >
            <i class="bi bi-chevron-left"></i>
        </button>
        <div class="page-numbers">
            <button
                v-for="page in pages"
                :key="page"
                class="page-number"
                :class="{ active: page === currentPage }"
                @click="changePage(page)"
            >
                {{ page }}
            </button>
        </div>
        <button
            class="page-btn"
            @click="changePage(currentPage + 1)"
            :disabled="currentPage === totalPages"
        >
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</template>

<script>
export default {
    name: "Pagination",
    props: {
        currentPage: {
            type: Number,
            required: true,
        },
        totalPages: {
            type: Number,
            required: true,
        },
        maxVisiblePages: {
            type: Number,
            default: 5,
        },
    },
    computed: {
        pages() {
            const pages = [];
            let startPage = 1;
            let endPage = this.totalPages;

            if (this.totalPages > this.maxVisiblePages) {
                let maxPagesBeforeCurrentPage = Math.floor(
                    this.maxVisiblePages / 2
                );
                let maxPagesAfterCurrentPage = Math.ceil(
                    this.maxVisiblePages / 2
                ) - 1;

                if (this.currentPage <= maxPagesBeforeCurrentPage) {
                    startPage = 1;
                    endPage = this.maxVisiblePages;
                } else if (
                    this.currentPage + maxPagesAfterCurrentPage >=
                    this.totalPages
                ) {
                    startPage = this.totalPages - this.maxVisiblePages + 1;
                    endPage = this.totalPages;
                } else {
                    startPage = this.currentPage - maxPagesBeforeCurrentPage;
                    endPage = this.currentPage + maxPagesAfterCurrentPage;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }

            return pages;
        },
    },
    methods: {
        changePage(page) {
            if (page > 0 && page <= this.totalPages && page !== this.currentPage) {
                this.$emit("page-change", page);
            }
        },
    },
};
</script>

<style scoped>
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--hcis-slate-200);
}
.page-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 0.2s;
    transition-timing-function: ease;
    margin: 0 0.25rem;
}
.page-btn:hover:not(:disabled) {
    background: var(--hcis-brand-500);
    color: white;
    border-color: var(--hcis-brand-500);
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-numbers {
    display: flex;
    margin: 0 0.5rem;
}
.page-number {
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 0.2s;
    transition-timing-function: ease;
    margin: 0 0.25rem;
    font-weight: 500;
}
.page-number:hover {
    border-color: var(--hcis-slate-200);
}
.page-number.active {
    background: var(--hcis-brand-500);
    color: white;
    border-color: var(--hcis-brand-500);
}
</style>
