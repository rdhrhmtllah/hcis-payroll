<template>
    <div class="container-fluid px-0">
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
            <HeaderKpi
                title="Update Performance KPI"
                subtitle="Ikuti langkah-langkah untuk memperbarui
                                           indikator kinerja Anda."
                :dayNight="dayNight"
                :formattedDate="formatDateString(currentDate)"
            />
            <div class="mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div
                            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
                        >
                            <div class="flex-grow-1">
                                <input
                                    type="text"
                                    class="form-control form-control-lg search-input"
                                    id="searchPerformance"
                                    v-model="searchQuery"
                                    :placeholder="searchPlaceholder"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <quick-stat
                    :stats="quickStatFieldFinal"
                    :loading="loading.listData"
                />
            </div>

            <div class="mt-n4 mb-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <!-- Periode ditampilkan per anggota pada masing-masing kartu,
                             karena satu anggota bisa punya beberapa periode sekaligus. -->
                        <el-tabs
                            v-model="activeName"
                            class="demo-tabs"
                            @tab-click="handleClick"
                        >
                            <el-tab-pane label="To Update" name="toUpdate">
                                <template #label>
                                    <span class="custom-tabs-label">
                                        <span>To Update</span>
                                        <el-badge
                                            :value="counts.toUpdate"
                                            class="item badge-to-update"
                                        ></el-badge>
                                    </span>
                                </template>
                                <ListTeamPerformanceV1
                                    v-if="!loading.listData"
                                    :items="listData"
                                    :pagination="pagination"
                                    @change-page="fetchListData"
                                    :getActionConfig="getActionConfig"
                                />
                                <div v-else>
                                    <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                                </div>
                            </el-tab-pane>

                            <el-tab-pane label="On Going" name="onGoing">
                                <template #label>
                                    <span class="custom-tabs-label">
                                        <span>On Going</span>
                                        <el-badge
                                            :value="counts.onGoing"
                                            class="item badge-on-going"
                                        ></el-badge>
                                    </span>
                                </template>
                                <ListTeamPerformanceV1
                                    v-if="!loading.listDataOnProgress"
                                    :items="listDataOnProgress"
                                    :pagination="pagination"
                                    @change-page="fetchListDataOnProgress"
                                    :getActionConfig="getActionConfig"
                                    :isOnGoing="true"
                                />
                                <div v-else>
                                    <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                                </div>
                            </el-tab-pane>

                            <el-tab-pane label="Returned" name="returned">
                                <template #label>
                                    <span class="custom-tabs-label">
                                        <span>Returned</span>
                                        <el-badge
                                            :value="counts.returned"
                                            class="item badge-returned"
                                        ></el-badge>
                                    </span>
                                </template>
                                <ListTeamPerformanceV1
                                    v-if="!loading.listDataRejected"
                                    :items="listDataRejected"
                                    :pagination="pagination"
                                    @change-page="fetchListDataRejected"
                                    :getActionConfig="getActionConfig"
                                    :isOnGoing="true"
                                />
                                <div v-else>
                                    <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                                </div>
                            </el-tab-pane>

                            <el-tab-pane label="Belum Parameter" name="belumParameter">
                                <template #label>
                                    <span class="custom-tabs-label">
                                        <span>Belum Parameter</span>
                                        <el-badge
                                            :value="counts.belumParameter"
                                            class="item badge-returned"
                                        ></el-badge>
                                    </span>
                                </template>
                                <ListTeamPerformanceV1
                                    v-if="!loading.listDataBelumParameter"
                                    :items="listDataBelumParameter"
                                    :pagination="pagination"
                                    @change-page="fetchListDataBelumParameter"
                                    :getActionConfig="getActionConfig"
                                />
                                <div v-else>
                                    <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                                </div>
                            </el-tab-pane>
                        </el-tabs>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";
import { HcisSkeleton } from '@/components/ui';
import FormV2 from "../../../components/form/formV2.vue";
import {
    ElNotification,
    ElTabPane,
    ElTabs,
    ElBadge,
    ElTag,
} from "element-plus";
import HeaderKpi from "../components/header.vue";
import ListTeamPerformanceV1 from "./listTeamPerformanceV1.vue";
import quickStat from "@/components/legacy/quickStat.vue";
import { tanggalPanjang } from '@/utils/tanggal';

export default {
    // Tidak ada prop periode lagi. Dulu halaman ini menerima satu periode
    // global dari controller, tapi sejak satu anggota bisa punya beberapa
    // periode sekaligus, periodenya ditampilkan sebagai badge PER KARTU.
    // Propnya sendiri sudah lama tidak dirender — hanya tersisa sebagai
    // kewajiban yang memaksa controller menghitung sesuatu yang tak dipakai.
    components: {
        FormV2,
        HcisSkeleton,
        HeaderKpi,
        quickStat,
        ElTabs,
        ElBadge,
        ElTabPane,
        ListTeamPerformanceV1,
        ElTag,
    },
    data() {
        return {
            searchQuery: "",
            activeName: "toUpdate",
            counts: {
                toUpdate: 0,
                onGoing: 0,
                returned: 0,
                belumParameter: 0,
            },
            currentDate: new Date(),
            listData: [],
            listDataOnProgress: [],
            listDataRejected: [],
            listDataBelumParameter: [],
            listCountWidget: null,
            form: {
                Id_Master_Target: null,
                Nama_Target: "",
                Deskripsi: "",
            },
            loading: {
                listData: true,
                listCountWidget: true,
                listDataRejected: true,
                listDataOnProgress: true,
                listDataBelumParameter: true,
                saveToDatabase: false,
                editToDatabase: false,
            },
            pagination: {
                page: 1,
                limit: 20,
                totalPage: 0,
                totalData: 0,
            },
        };
    },
    computed: {
        modalTitle() {
            return this.isEditing ? "Edit Target KPI" : "Tambah Target KPI";
        },
        modalSubmitText() {
            return this.isEditing ? "Simpan Perubahan" : "Simpan";
        },
        formFields() {
            return [
                {
                    name: "Kode_Target",
                    label: "Kode Target",
                    type: "text",
                    placeholder: "Contoh: Y (Yearly)",
                    icon: "bi bi-type",
                    col: "col-12",
                    maxlength: 1,
                    disabled: this.isEditing ? true : false,
                    filter: (val) => val.substring(0, 1),
                },
                {
                    name: "Nama_Target",
                    label: "Target",
                    type: "text",
                    placeholder: "Yearly",
                    icon: "bi bi-bullseye",
                    col: "col-12",
                },

                {
                    name: "Deskripsi",
                    label: "Deskripsi Target",
                    type: "textarea",
                    placeholder: "Tuliskan deskripsi Target...",
                    icon: "bi bi-card-text",
                    col: "col-12",
                    rows: 3,
                },
            ];
        },
        quickStatFieldFinal() {
            return this.quickStatField;
        },
        quickStatField() {
            const widget = this.listCountWidget || {};

            return [
                {
                    title: "To Update",
                    value: widget.to_update ?? 0,
                    icon: "bi bi-hourglass-split",
                    bgIcon: "bg-warning text-warning",
                    size: "col-4",
                },
                {
                    title: "Ongoing",
                    value: widget.on_progress ?? 0,
                    icon: "bi bi-gear",
                    bgIcon: "bg-info text-info",
                    size: "col-4",
                },
                {
                    title: "Returned",
                    value: widget.rejected ?? 0,
                    icon: "bi bi-arrow-counterclockwise",
                    bgIcon: "bg-danger text-danger",
                    size: "col-4",
                },
            ];
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        searchPlaceholder() {
            if (this.activeName === "onGoing") {
                return "Cari performance on going...";
            }
            if (this.activeName === "returned") {
                return "Cari performance returned...";
            }
            return "Cari performance to update...";
        },
    },
    mounted() {
        this.fetchListData();
        this.fetchWidgetData();
    },
    methods: {
        async fetchWidgetData() {
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/list-Kpi-team/widget-count"
                );

                if (response.status === 200 && response.data?.result) {
                    const result = response.data.result;
                    this.listCountWidget = result;
                    this.counts = {
                        toUpdate: result.to_update ?? 0,
                        onGoing: result.on_progress ?? 0,
                        returned: result.rejected ?? 0,
                        belumParameter: result.belum_parameter ?? 0,
                    };
                } else {
                    this.listCountWidget = null;
                    this.counts = {
                        toUpdate: 0,
                        onGoing: 0,
                        returned: 0,
                        belumParameter: 0,
                    };
                }
            } catch (error) {
                this.listCountWidget = null;
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memuat data KPI tim",
                    type: "error",
                });
            } finally {
                this.loading.listCountWidget = false;
            }
        },
        async fetchListData(page = 1) {
            this.loading.listData = true;
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/list-Kpi-team",
                    {
                        params: {
                            limit: this.pagination.limit,
                            page,
                            q: this.searchQuery,
                        },
                    }
                );

                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                    this.pagination.page = response.data.page || page;
                    this.pagination.totalPage = response.data.total_page || 0;
                    this.pagination.totalData = response.data.total_data || 0;
                } else {
                    this.listData = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.listData = [];
                console.error("Failed to fetch list data:", error);
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memuat data KPI tim",
                    type: "error",
                });
            } finally {
                this.loading.listData = false;
            }
        },

        async fetchListDataOnProgress(page = 1) {
            this.loading.listDataOnProgress = true;
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/list-Kpi-team/on-progress",
                    {
                        params: {
                            limit: this.pagination.limit,
                            page,
                            q: this.searchQuery,
                        },
                    }
                );

                if (response.status === 200 && response.data?.result) {
                    this.listDataOnProgress = response.data.result;
                    this.pagination.page = response.data.page || page;
                    this.pagination.totalPage = response.data.total_page || 0;
                    this.pagination.totalData = response.data.total_data || 0;
                } else {
                    this.listDataOnProgress = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.listDataOnProgress = [];
                console.error("Failed to fetch list data:", error);
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memuat data KPI tim",
                    type: "error",
                });
            } finally {
                this.loading.listDataOnProgress = false;
            }
        },

        async fetchListDataRejected(page = 1) {
            this.loading.listDataRejected = true;
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/list-Kpi-team/rejected",
                    {
                        params: {
                            limit: this.pagination.limit,
                            page,
                            q: this.searchQuery,
                        },
                    }
                );

                if (response.status === 200 && response.data?.result) {
                    this.listDataRejected = response.data.result;
                    this.pagination.page = response.data.page || page;
                    this.pagination.totalPage = response.data.total_page || 0;
                    this.pagination.totalData = response.data.total_data || 0;
                } else {
                    this.listDataRejected = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.listDataRejected = [];
                console.error("Failed to fetch list data:", error);
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memuat data KPI tim",
                    type: "error",
                });
            } finally {
                this.loading.listDataRejected = false;
            }
        },
        async fetchListDataBelumParameter(page = 1) {
            this.loading.listDataBelumParameter = true;
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/list-Kpi-team/belum-parameter",
                    {
                        params: {
                            limit: this.pagination.limit,
                            page,
                            q: this.searchQuery,
                        },
                    }
                );

                if (response.status === 200 && response.data?.result) {
                    this.listDataBelumParameter = response.data.result;
                    this.pagination.page = response.data.page || page;
                    this.pagination.totalPage = response.data.total_page || 0;
                    this.pagination.totalData = response.data.total_data || 0;
                } else {
                    this.listDataBelumParameter = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.listDataBelumParameter = [];
                console.error("Failed to fetch list data:", error);
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memuat data KPI tim",
                    type: "error",
                });
            } finally {
                this.loading.listDataBelumParameter = false;
            }
        },
        async fetchDataForActiveTab(page = 1) {
            let apiUrl = "";
            let dataProperty = "";
            let loadingProperty = "";

            if (this.activeName === "onGoing") {
                apiUrl = "/api/v1/update-performance/list-Kpi-team/on-progress";
                dataProperty = "listDataOnProgress";
                loadingProperty = "listDataOnProgress";
            } else if (this.activeName === "returned") {
                apiUrl = "/api/v1/update-performance/list-Kpi-team/rejected";
                dataProperty = "listDataRejected";
                loadingProperty = "listDataRejected";
            } else if (this.activeName === "belumParameter") {
                apiUrl = "/api/v1/update-performance/list-Kpi-team/belum-parameter";
                dataProperty = "listDataBelumParameter";
                loadingProperty = "listDataBelumParameter";
            } else {
                apiUrl = "/api/v1/update-performance/list-Kpi-team";
                dataProperty = "listData";
                loadingProperty = "listData";
            }

            this.loading[loadingProperty] = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    search: this.searchQuery,
                };

                const response = await axios.get(apiUrl, { params: params });

                if (response.status === 200 && response.data?.result) {
                    this[dataProperty] = response.data.result;
                    this.pagination.page = response.data.page || page;
                    this.pagination.totalPage = response.data.total_page || 0;
                    this.pagination.totalData = response.data.total_data || 0;
                } else {
                    this[dataProperty] = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this[dataProperty] = [];
                console.error(
                    `Failed to fetch list data for ${this.activeName}:`,
                    error
                );
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memuat data KPI tim",
                    type: "error",
                });
            } finally {
                this.loading[loadingProperty] = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchDataForActiveTab(1);
        }, 500),

        getActionConfig(item) {
            // Tab "Belum Parameter" murni informasional. Halaman update
            // performance tidak punya apa pun untuk ditampilkan bagi anggota
            // tanpa parameter, jadi tombol ke sana hanya menjanjikan sesuatu
            // yang tidak ada.
            if (item.is_belum_parameter) {
                return null;
            }

            if (item.is_on_going) {
                return {
                    label: "Detail",
                    icon: "bi bi-eye-fill",
                    class: "btn-primary",
                };
            }

            return {
                label: "Update Performance",
                icon: "bi bi-pencil-square",
                class: "btn-primary",
            };
        },
        getGenderIcon(gender) {
            if (gender === "Laki-Laki")
                return { class: "fas fa-mars", color: "#3498db" };
            if (gender === "Perempuan")
                return { class: "fas fa-venus", color: "#e91e63" };
            return { class: "fas fa-genderless", color: "#95a5a6" };
        },
        formatDate(dateString) {
            if (!dateString) return "N/A";
            const options = { year: "numeric", month: "long", day: "numeric" };
            return new Date(dateString).toLocaleDateString("id-ID", options);
        },
        formatPhoneNumber(phone) {
            if (!phone || !phone.startsWith("62")) return phone;
            const match = phone.match(/^(\d{2})(\d{3,4})(\d{4})(\d*)$/);
            return match
                ? `+${match[1]} ${match[2]}-${match[3]}-${match[4]}`
                : phone;
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchListData(page);
            }
        },

        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },

        getScheduleIcon() {
            return {
                iconClass: "bi bi-bullseye",
                bgClass: "bg-info-soft",
            };
        },
        handleClick(tab) {
            if (tab.props.name === "onGoing") {
                this.fetchListDataOnProgress(1);
            } else if (tab.props.name === "toUpdate") {
                this.fetchListData(1);
            } else if (tab.props.name === "returned") {
                this.fetchListDataRejected(1);
            } else if (tab.props.name === "belumParameter") {
                this.fetchListDataBelumParameter(1);
            }
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.custom-tag {
    background: var(--gradient-primary) !important;
    color: var(--hcis-white) !important; /* biar text-nya putih */
    border: none !important; /* hilangin border default */
}

.demo-tabs > .el-tabs__content {
    padding: 32px;
    color: #6b778c;
    font-size: 32px;
    font-weight: 600;
}

.custom-tabs-label .el-badge {
    margin-left: 8px;
}
.item {
    margin-top: -2px;
}

.badge-to-update :deep(.el-badge__content) {
    background-color: #ffc107;
    color: black; /* Mengubah warna teks agar terbaca di atas background kuning */
}

.badge-on-going :deep(.el-badge__content) {
    background-color: #0dcaf0;
}

.badge-returned :deep(.el-badge__content) {
    background-color: #dc3545;
}

.search-input {
    border-radius: 50px;
    padding: 0.75rem 1.25rem;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}
.search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.status-filter {
    border-radius: 50px;
    min-width: 180px;
    padding: 0.5rem 1rem;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}
.status-filter:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}
</style>
