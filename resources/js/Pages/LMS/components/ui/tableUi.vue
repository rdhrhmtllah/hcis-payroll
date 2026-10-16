<template>
    <div>
        <div v-if="loading" class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th
                            v-for="(header, index) in headers"
                            :key="index"
                            :class="header.class"
                            :style="header.style"
                        >
                            {{ header.text }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in 3" :key="i">
                        <td
                            v-for="(header, j) in headers"
                            :key="j"
                            :class="header.class"
                        >
                            <div class="skeleton skeleton-text"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else>
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th
                                v-for="(header, index) in headers"
                                :key="index"
                                :class="header.class"
                                :style="header.style"
                            >
                                {{ header.text }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, index) in items"
                            :key="item.id || index"
                        >
                            <td v-for="header in headers" :key="header.value">
                                <slot
                                    :name="`cell(${header.value})`"
                                    :item="item"
                                    :index="index"
                                >
                                    {{ item[header.value] }}
                                </slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="items.length"
                class="align-items-center mt-2 row g-3 text-center text-sm-start"
            >
                <div class="col-sm">
                    <div class="text-muted">
                        Total Data
                        <span class="fw-semibold">{{
                            pagination.total || 0
                        }}</span>
                        Hasil
                    </div>
                </div>
                <div class="col-sm-auto">
                    <ul
                        class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                    >
                        <li
                            class="page-item"
                            :class="{ disabled: pagination.current_page === 1 }"
                        >
                            <a
                                href="#"
                                class="page-link"
                                @click.prevent="
                                    changePage(pagination.current_page - 1)
                                "
                                >←</a
                            >
                        </li>
                        <li
                            class="page-item"
                            v-for="page in visiblePages"
                            :key="page"
                            :class="{
                                active: page === pagination.current_page,
                            }"
                        >
                            <a
                                href="#"
                                class="page-link"
                                @click.prevent="changePage(page)"
                                >{{ page }}</a
                            >
                        </li>
                        <li
                            class="page-item"
                            :class="{
                                disabled:
                                    pagination.current_page ===
                                    pagination.total_pages,
                            }"
                        >
                            <a
                                href="#"
                                class="page-link"
                                @click.prevent="
                                    changePage(pagination.current_page + 1)
                                "
                                >→</a
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-if="!items.length"
                class="d-flex flex-column align-items-center justify-content-center text-center"
            >
                <transition name="fade">
                    <DotLottieVue
                        v-if="dotReady"
                        style="height: 100px; width: 100px"
                        autoplay
                        loop
                        src="/animation/empty-data.json"
                    />
                </transition>
                <p class="mt-3 mb-0 fw-bold">Data Tidak Ditemukan!</p>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

export default {
    name: "TabelUiV1",
    components: {
        DotLottieVue,
    },
    props: {
        items: {
            type: Array,
            required: true,
        },
        headers: {
            type: Array,
            required: true,
        },
        pagination: {
            type: Object,
            required: true,
            default: () => ({
                current_page: 1,
                total_pages: 1,
                total: 0,
            }),
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["change-page"],
    data() {
        return {
            dotReady: false,
        };
    },
    computed: {
        visiblePages() {
            // Menggunakan key dari pagination terbaru (total_pages dan current_page)
            const total = this.pagination.total_pages || 1;
            const current = this.pagination.current_page || 1;
            let start = Math.max(1, current - 2);
            let end = Math.min(total, current + 2);

            if (end - start < 4) {
                if (start === 1) {
                    end = Math.min(5, total);
                } else if (end === total) {
                    start = Math.max(1, total - 4);
                }
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
    },
    mounted() {
        setTimeout(() => {
            this.dotReady = true;
        }, 100);
    },
    methods: {
        changePage(page) {
            // Validasi dengan total_pages dari format terbaru
            if (page >= 1 && page <= this.pagination.total_pages) {
                this.$emit("change-page", page);
            }
        },
    },
};
</script>

<style scoped>
.skeleton {
    background-color: var(--hcis-slate-200);
    border-radius: 4px;
    display: inline-block;
    position: relative;
    overflow: hidden;
}

.skeleton::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.5),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.skeleton-text {
    height: 12px;
    width: 80%;
    margin: 4px 0;
}

/* Search Box */
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-input {
    padding-left: 40px;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
}

/* Table */
.table thead th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    border-bottom-width: 2px;
}
.table-hover tbody tr:hover {
    background-color: var(--surface-soft);
}
</style>
