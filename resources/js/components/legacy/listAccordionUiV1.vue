<template>
    <div>
        <div v-if="loading">
            <div v-for="i in 3" :key="i" class="list-item-skeleton mb-3">
                <div class="skeleton skeleton-text skeleton-title"></div>
                <div class="skeleton skeleton-text skeleton-subtitle"></div>
            </div>
        </div>

        <div v-else-if="items.length > 0">
            <div class="accordion" :id="accordionId">
                <div
                    class="accordion-item"
                    v-for="(item, index) in items"
                    :key="item[itemKey] || index"
                >
                    <h2
                        class="accordion-header"
                        :id="`heading-${accordionId}-${item[itemKey] || index}`"
                    >
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            :data-bs-target="`#collapse-${accordionId}-${
                                item[itemKey] || index
                            }`"
                            aria-expanded="false"
                            :aria-controls="`collapse-${accordionId}-${
                                item[itemKey] || index
                            }`"
                        >
                            <slot name="header" :item="item" :index="index">
                                <div
                                    class="d-flex justify-content-between align-items-center w-100"
                                >
                                    <span>
                                        <i class="bi bi-person-fill me-2"></i>
                                        {{ item.Nama || "N/A" }}
                                    </span>
                                </div>
                            </slot>
                        </button>
                    </h2>
                    <div
                        :id="`collapse-${accordionId}-${
                            item[itemKey] || index
                        }`"
                        class="accordion-collapse collapse"
                        :aria-labelledby="`heading-${accordionId}-${
                            item[itemKey] || index
                        }`"
                        :data-bs-parent="`#${accordionId}`"
                    >
                        <div class="accordion-body">
                            <slot
                                name="body"
                                :item="item"
                                :index="index"
                            ></slot>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian Pagination (tidak ada perubahan di sini) -->
            <div
                v-if="!loading && items.length > 0"
                class="align-items-center mt-4 row g-3 text-center text-sm-start"
            >
                <div class="col-sm">
                    <div class="text-muted">
                        Total Data
                        <span class="fw-semibold">{{
                            pagination.totalData
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
                            :class="{ disabled: pagination.page === 1 }"
                        >
                            <a
                                href="#"
                                class="page-link"
                                @click.prevent="changePage(pagination.page - 1)"
                                >←</a
                            >
                        </li>
                        <li
                            class="page-item"
                            v-for="page in visiblePages"
                            :key="page"
                            :class="{ active: page === pagination.page }"
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
                                    pagination.page === pagination.totalPage,
                            }"
                        >
                            <a
                                href="#"
                                class="page-link"
                                @click.prevent="changePage(pagination.page + 1)"
                                >→</a
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            v-else
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
            <p class="mt-3 mb-0 fw-bold">{{ emptyText }}</p>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

export default {
    name: "ListAccordionV1",
    components: {
        DotLottieVue,
    },
    props: {
        accordionId: {
            type: String,
            required: true,
        },
        itemKey: {
            type: String,
            required: true,
        },
        items: {
            type: Array,
            required: true,
        },
        pagination: {
            type: Object,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
        // Pesan empty-state; dibiarkan default agar pemakai lain tidak berubah.
        emptyText: {
            type: String,
            default: "Data Tidak Ditemukan!",
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
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
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
            if (page >= 1 && page <= this.pagination.totalPage) {
                this.$emit("change-page", page);
            }
        },
    },
};
</script>
