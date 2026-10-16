<template>
    <div class="list-performance-container">
        <div v-if="items && items.length > 0">
            <div
                v-for="item in items"
                :key="item.id"
                class="card team-member-card mb-3 shadow-sm"
            >
                <div class="card-body p-3">
                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <div class="employee-profile-header">
                            <div class="profile-main">
                                <div class="avatar-placeholder">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="profile-identity">
                                    <div class="d-flex gap-2">
                                        <span v-if="item.periode">
                                            <el-tag type="info">
                                                <i class="bi bi-calendar"></i>
                                                {{ item.periode }}</el-tag
                                            >
                                        </span>
                                        <span
                                            v-if="
                                                isOnGoing && item.no_transaksi
                                            "
                                        >
                                            <el-tag type="warning">{{
                                                item.no_transaksi
                                            }}</el-tag>
                                        </span>
                                        <span v-if="isOnGoing && item.tanggal">
                                            <el-tag type="primary">
                                                {{
                                                    formatDateString(
                                                        item.tanggal
                                                    )
                                                }}
                                                {{ item.jam }}</el-tag
                                            >
                                        </span>
                                    </div>

                                    <h5 class="profile-name mb-0">
                                        {{ item.nama_lengkap }}
                                        <!-- <i
                                            :class="
                                                getGenderIcon(
                                                    item.jenis_kelamin
                                                ).class
                                            "
                                            :style="{
                                                color: getGenderIcon(
                                                    item.jenis_kelamin
                                                ).color,
                                            }"
                                            :title="item.jenis_kelamin"
                                        ></i> -->
                                    </h5>
                                    <p class="profile-title text-muted mb-1">
                                        {{ item.nama_jabatan }}
                                    </p>
                                    <!-- Kode karyawan: satu-satunya pembeda ketika nama DAN
                                         divisi kebetulan sama. Ditaruh dekat nama karena di
                                         situlah orang memutuskan siapa yang dipilih. -->
                                    <p
                                        v-if="item.kode_karyawan"
                                        class="profile-kode text-muted mb-1"
                                    >
                                        {{ item.kode_karyawan }}
                                    </p>
                                    <div class="detail-row badges">
                                        <span class="badge division">{{
                                            item.nama_divisi
                                        }}</span>
                                        <span class="badge subdivision">{{
                                            item.nama_sub_divisi
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="action-button">
                            <button
                                v-if="getActionConfig"
                                :class="[
                                    'btn',
                                    actionConfig(item).class || 'btn-primary',
                                    'btn-sm',
                                ]"
                                @click="goToDetailPage(item)"
                            >
                                <i
                                    v-if="actionConfig(item).icon"
                                    :class="[actionConfig(item).icon, 'me-1']"
                                ></i>
                                {{ actionConfig(item).label || "Detail" }}
                            </button>
                            <button
                                v-else
                                class="btn btn-primary btn-sm"
                                @click="goToDetailPage(item)"
                            >
                                <i class="bi bi-eye-fill me-1"></i>
                                Detail
                            </button>
                        </div>
                    </div>

                    <template v-if="isOnGoing">
                        <hr class="my-3" />

                        <div class="performance-stats d-flex flex-wrap gap-3">
                            <div class="stat-item">
                                <small class="text-muted d-block"
                                    >Achievement</small
                                >
                                <div class="d-flex align-items-center mt-1">
                                    <i
                                        class="fas fa-trophy text-warning me-2"
                                    ></i>
                                    <h5 class="mb-0 fw-bold text-primary">
                                        {{
                                            item.total_score !== undefined
                                                ? item.total_score
                                                : "-"
                                        }}
                                    </h5>
                                </div>
                            </div>

                            <div class="stat-item">
                                <small class="text-muted d-block"
                                    >Total Achieved Weight</small
                                >
                                <div class="d-flex align-items-center mt-1">
                                    <i
                                        class="fas fa-bullseye text-success me-2"
                                    ></i>
                                    <h5 class="mb-0 fw-bold text-primary">
                                        {{
                                            item.total_bobot !== undefined
                                                ? item.total_bobot
                                                : "-"
                                        }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="col-sm-auto mt-4">
                <ul
                    class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-end mb-0"
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
                            disabled: pagination.page === pagination.totalPage,
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
        <div
            v-else
            class="d-flex flex-column align-items-center justify-content-center text-center"
        >
            <transition name="fade">
                <DotLottieVue
                    style="height: 100px; width: 100px"
                    autoplay
                    loop
                    src="/animation/empty-data.json"
                />
            </transition>
            <p class="mt-3 mb-0 fw-bold">Data Tidak Ditemukan!</p>
        </div>
    </div>
</template>

<script>
import { ElTag } from "element-plus";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { tanggalPanjang } from '@/utils/tanggal';
export default {
    name: "ListTeamPerformanceV1",
    props: {
        items: {
            type: Array,
            required: true,
            default: () => [],
        },
        pagination: {
            type: Object,
            required: true,
            default: () => ({
                page: 1,
                totalPage: 1,
                totalData: 0,
                limit: 20,
            }),
        },
        getActionConfig: {
            type: Function,
            required: false,
            default: null,
        },
        isOnGoing: {
            type: Boolean,
            default: false,
        },
    },
    components: {
        DotLottieVue,
        ElTag,
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
    methods: {
        goToDetailPage(item) {
            if (item && item.id_user) {
                // Bawa periode yang sedang dipilih di dropdown. Baris ini muncul
                // KARENA periode itu belum terisi, jadi detailnya harus dibuka
                // pada periode yang sama — kalau tidak, halaman menebak sendiri
                // dan membuka bulan lain (pilih April, terbuka Mei).
                //
                // schedule_hash didahulukan bila ada: penunjukan satu baris
                // jadwal lebih spesifik daripada sekadar periode.
                let jadwal = "";
                if (item.schedule_hash) {
                    jadwal = `?schedule=${item.schedule_hash}`;
                } else if (item.periode_raw) {
                    jadwal = `?periode=${item.periode_raw}`;
                }

                window.location.href = `/update-performance/by-pass/${item.id_user}${jadwal}`;
            }
        },

        getGenderIcon(gender) {
            if (gender === "Laki-Laki")
                return { class: "fas fa-mars", color: "#3498db" };
            if (gender === "Perempuan")
                return { class: "fas fa-venus", color: "#e91e63" };
            return { class: "fas fa-genderless", color: "#95a5a6" };
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.totalPage) {
                this.$emit("change-page", page);
            }
        },
        actionConfig(item) {
            return this.getActionConfig ? this.getActionConfig(item) : {};
        },
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --division-bg: #4a90e2;
    --division-text: var(--hcis-white);
    --subdivision-bg: #e8f3ff;
    --border-color: var(--hcis-legacy-bootstrap-gray);
    --subdivision-text: #4a90e2;
}
.team-member-card {
    border: 0;
    transition: all 0.3s ease-in-out;
}
.achievement-section h5 {
    font-weight: bold;
    color: #0d6efd;
}

.team-member-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

.employee-profile-header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.profile-main {
    display: flex;
    align-items: center;
    gap: 1rem; /* Jarak antara avatar dan identitas */
}

.avatar-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--gradient-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.profile-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #343a40;
}

.profile-name i {
    font-size: 1rem;
    margin-left: 5px;
}

.profile-title {
    font-size: 0.9rem;
}

/* Kode karyawan dibuat monospace + tabular supaya dua kode yang mirip
   terbaca bedanya sekali lihat, bukan setelah dieja. */
.profile-kode {
    font-size: 0.78rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}

.badges .badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
}

.badge.level {
    background-color: #fd7e14;
} /* Oranye */
.badge.division {
    background-color: var(--division-bg);
    color: var(--division-text);
} /* Biru */
.badge.subdivision {
    background-color: var(--subdivision-bg);
    color: var(--subdivision-text);
} /* Hijau */

.action-button .btn {
    white-space: nowrap;
}

/* Responsive */
@media (max-width: 768px) {
    .card-body {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem;
    }
    .action-button {
        width: 100%;
    }
    .action-button .btn {
        width: 100%;
    }
}
</style>
