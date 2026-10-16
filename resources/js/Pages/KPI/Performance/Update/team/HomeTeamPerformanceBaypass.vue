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
            <div>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <div
                            class="d-flex align-items-center justify-content-between mb-3"
                        >
                            <h5 class="card-title mb-0 me-3">
                                <i class="bi bi-calendar-date me-2"></i
                                >Pengaturan Periode
                            </h5>

                            <div
                                class="d-flex gap-2 align-items-center"
                                style="min-width: 50%"
                            >
                                <el-select
                                    v-model="selectedScheduleId"
                                    placeholder="Semua penjadwalan (opsional)"
                                    size="large"
                                    class="w-100"
                                    filterable
                                    clearable
                                    :loading="loading.scheduleList"
                                >
                                    <el-option
                                        v-for="item in scheduleList"
                                        :key="item.Id_Master_Penjadwalan"
                                        :label="
                                            item.Nama_Kegiatan +
                                            ' (' +
                                            item.Tahun +
                                            ')'
                                        "
                                        :value="item.Id_Master_Penjadwalan"
                                    >
                                        <span style="float: left">{{
                                            item.Nama_Kegiatan
                                        }}</span>
                                        <span
                                            style="
                                                float: right;
                                                color: #8492a6;
                                                font-size: 13px;
                                                margin-left: 10px;
                                            "
                                            >{{ item.Tahun }}</span
                                        >
                                    </el-option>
                                </el-select>

                                <el-select
                                    v-model="periodForm.bulan"
                                    placeholder="Pilih Bulan"
                                    size="large"
                                    class="w-100"
                                    filterable
                                >
                                    <el-option
                                        v-for="item in monthOptions"
                                        :key="item.value"
                                        :label="item.label"
                                        :value="item.value"
                                    >
                                    </el-option>
                                </el-select>

                                <el-select
                                    v-model="periodForm.tahun"
                                    placeholder="Pilih Tahun"
                                    size="large"
                                    class="w-50"
                                >
                                    <el-option
                                        v-for="item in yearOptions"
                                        :key="item"
                                        :label="item"
                                        :value="item"
                                    >
                                    </el-option>
                                </el-select>

                                <el-button
                                    type="primary"
                                    size="large"
                                    :loading="loading.savePeriod"
                                    @click="updatePeriod"
                                >
                                    <i class="bi bi-save me-1"></i> Update
                                </el-button>
                            </div>
                        </div>
                        <div
                            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
                        >
                            <div class="flex-grow-1">
                                <input
                                    type="search"
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

            <div class="mb-5">
                <div class="modern-alert mb-4 d-flex align-items-start">
                    <div class="alert-icon-wrapper">
                        <i class="bi bi-info-circle-fill"></i>
                    </div>
                    <div class="alert-content ms-3">
                        <h6 class="alert-title fw-bold mb-1">
                            Menunggu Finalisasi Performance
                        </h6>
                        <p class="alert-desc mb-0">
                            Data di bawah ini belum diperbarui. Karena ini
                            adalah halaman <strong>By Pass</strong>, setiap
                            update yang Anda lakukan akan langsung dianggap
                            <span class="badge-text">Final / Cleared</span> dan
                            otomatis hilang dari daftar ini.
                        </p>
                    </div>
                </div>
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-end mb-3">
                            <el-tag
                                class="custom-tag"
                                effect="dark"
                                type="warning"
                            >
                                <i class="bi bi-shield-check me-1"></i>
                                By Pass Mode
                            </el-tag>
                        </div>

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
    ElSelect,
    ElAlert,
    ElOption,
    ElButton,
} from "element-plus";
import HeaderKpi from "../components/header.vue";
import ListTeamPerformanceV1 from "./listTeamPerformanceByPass.vue";
import quickStat from "@/components/legacy/quickStat.vue";
import { tanggalPanjang } from '@/utils/tanggal';

export default {
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
        ElSelect,
        ElOption,
        ElButton,
        ElAlert,
    },
    data() {
        return {
            scheduleList: [],
            selectedScheduleId: null,
            periodForm: {
                bulan: null,
                tahun: null,
            },

            yearOptions: [2025, 2026],
            searchQuery: "",
            activeName: "toUpdate",
            counts: {
                toUpdate: 0,
                onGoing: 0,
                returned: 0,
            },
            currentDate: new Date(),
            listData: [],
            listDataOnProgress: [],
            listDataRejected: [],
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
        monthOptions() {
            const allMonths = [
                { value: 1, label: "Januari" },
                { value: 2, label: "Februari" },
                { value: 3, label: "Maret" },
                { value: 4, label: "April" },
                { value: 5, label: "Mei" },
                { value: 6, label: "Juni" },
                { value: 7, label: "Juli" },
                { value: 8, label: "Agustus" },
                { value: 9, label: "September" },
                { value: 10, label: "Oktober" },
                { value: 11, label: "November" },
                { value: 12, label: "Desember" },
            ];

            if (this.periodForm.tahun === 2025) {
                return allMonths.filter((m) => m.value >= 9);
            }
            return allMonths;
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
    created() {
        this.initPeriodForm();
    },
    mounted() {
        this.fetchListData();
        this.fetchScheduleList();
    },
    methods: {
        async fetchScheduleList() {
            this.loading.scheduleList = true;
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/by-pass/master-penjadwalan"
                );
                if (response.status === 200 && response.data?.result) {
                    this.scheduleList = response.data.result;

                    // Ambil dari local storage
                    const savedId = localStorage.getItem(
                        "selected_schedule_hash"
                    );

                    if (savedId) {
                        // PERBAIKAN DI SINI:
                        // Gunakan String() untuk membandingkan agar tipe data sama (String vs String)
                        const exists = this.scheduleList.find(
                            (item) =>
                                String(item.Id_Master_Penjadwalan) ===
                                String(savedId)
                        );

                        if (exists) {
                            // Gunakan nextTick untuk memastikan list sudah ter-render sebelum value dipilih
                            this.$nextTick(() => {
                                // Ambil ID langsung dari object yang ditemukan (exists)
                                // agar tipe datanya 100% cocok dengan :value di el-option
                                this.selectedScheduleId =
                                    exists.Id_Master_Penjadwalan;
                            });
                        } else {
                            // Jika ID di storage tidak ada di list (kadaluarsa), hapus.
                            localStorage.removeItem("selected_schedule_hash");
                        }
                    }
                } else {
                    this.scheduleList = [];
                }
            } catch (error) {
                console.error("Gagal mengambil master penjadwalan:", error);
                this.scheduleList = [];
            } finally {
                this.loading.scheduleList = false;
            }
        },
        initPeriodForm() {
            const now = new Date();
            const savedMonth = localStorage.getItem("selected_period_month");
            const savedYear = localStorage.getItem("selected_period_year");

            if (savedMonth && savedYear) {
                this.periodForm.tahun = parseInt(savedYear);
                if (
                    this.periodForm.tahun === 2025 &&
                    parseInt(savedMonth) < 9
                ) {
                    this.periodForm.bulan = 9;
                } else {
                    this.periodForm.bulan = parseInt(savedMonth);
                }
            } else {
                this.periodForm.tahun = 2025;
                if (now.getFullYear() === 2025) {
                    const currentM = now.getMonth() + 1;
                    this.periodForm.bulan = currentM < 9 ? 9 : currentM;
                } else if (now.getFullYear() === 2026) {
                    this.periodForm.bulan = now.getMonth() + 1;
                } else {
                    this.periodForm.bulan = 9;
                }
            }
        },
        async updatePeriod() {
            if (!this.periodForm.bulan || !this.periodForm.tahun) {
                ElNotification({
                    title: "Peringatan",
                    message: "Bulan dan Tahun wajib dipilih!",
                    type: "warning",
                });
                return;
            }

            // Penjadwalan OPSIONAL: acuan by-pass global menurut periode. Kalau
            // tidak dipilih, kirim null dan biarkan server menurunkan jadwalnya
            // dari bulan+tahun yang dipilih.
            //
            // Tidak ada lagi jalan keluar di antara spinner dan try/finally. Dulu
            // ada, dan karena `return`-nya di luar try, tombol Update berputar
            // selamanya tanpa satu pun pesan.
            //
            // String() dipakai agar sama dengan cara fetchScheduleList mencocokkan
            // id yang tersimpan; membandingkan mentah dengan === pernah meleset
            // hanya karena beda tipe.
            const selectedItem = this.scheduleList.find(
                (item) =>
                    String(item.Id_Master_Penjadwalan) ===
                    String(this.selectedScheduleId)
            );

            this.loading.savePeriod = true;
            try {
                const response = await axios.post(
                    "/api/v1/update-performance/by-pass/jadwal",
                    {
                        id_master_penjadwalan:
                            selectedItem?.Id_Master_Penjadwalan ?? null,
                        bulan: this.periodForm.bulan,
                        tahun: this.periodForm.tahun,
                    }
                );

                if (response.status === 200) {
                    ElNotification({
                        title: "Sukses",
                        message: "Periode berhasil diperbarui.",
                        type: "success",
                    });

                    this.fetchListData();
                }
            } catch (error) {
                console.error("Gagal update periode:", error);
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal update periode server",
                    type: "error",
                });
            } finally {
                this.loading.savePeriod = false;
            }
        },
        async fetchListData(page = 1) {
            this.loading.listData = true;
            try {
                const response = await axios.get(
                    "/api/v1/update-performance/list-Kpi-bypass",
                    {
                        params: {
                            limit: this.pagination.limit,
                            page,
                            search: this.searchQuery, // Ubah 'q' menjadi 'search'
                            bulan: this.periodForm.bulan,
                            tahun: this.periodForm.tahun,
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
        async fetchDataForActiveTab(page = 1) {
            let apiUrl = "";
            let dataProperty = "";
            let loadingProperty = "";
            // Object params default
            let params = {
                limit: this.pagination.limit,
                page,
                search: this.searchQuery,
            };

            if (this.activeName === "onGoing") {
                apiUrl = "/api/v1/update-performance/list-Kpi-team/on-progress";
                dataProperty = "listDataOnProgress";
                loadingProperty = "listDataOnProgress";
            } else if (this.activeName === "returned") {
                apiUrl = "/api/v1/update-performance/list-Kpi-team/rejected";
                dataProperty = "listDataRejected";
                loadingProperty = "listDataRejected";
            } else {
                // Default / toUpdate (BY PASS MODE)
                apiUrl = "/api/v1/update-performance/list-Kpi-bypass";
                dataProperty = "listData";
                loadingProperty = "listData";

                // Khusus untuk Bypass, tambahkan Bulan dan Tahun ke params
                params.bulan = this.periodForm.bulan;
                params.tahun = this.periodForm.tahun;
            }

            this.loading[loadingProperty] = true;
            try {
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
            }
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        selectedScheduleId(val) {
            if (val) {
                localStorage.setItem("selected_schedule_hash", val);
            } else {
                localStorage.removeItem("selected_schedule_hash");
            }
        },
        "periodForm.bulan"(val) {
            if (val) {
                localStorage.setItem("selected_period_month", val);
            }
        },
        "periodForm.tahun"(val) {
            if (val) {
                localStorage.setItem("selected_period_year", val);
            }
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
/* CSS untuk Modern Enterprise Alert */
.modern-alert {
    background-color: var(--hcis-slate-50); /* Slate-50: Background sangat soft abu-biru */
    border: 1px solid var(--hcis-slate-200); /* Slate-200: Border tipis */
    border-left: 4px solid var(--hcis-blue-500); /* Blue-500: Aksen biru modern di kiri */
    border-radius: 8px;
    padding: 1.25rem;
    color: var(--hcis-slate-700); /* Slate-700: Warna text agar nyaman di mata */
    transition: all 0.3s ease;
}

.modern-alert:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05),
        0 2px 4px -1px rgba(0, 0, 0, 0.03);
    border-color: var(--hcis-slate-300);
}

.alert-icon-wrapper {
    font-size: 1.3rem;
    color: var(--hcis-blue-600); /* Blue-600 */
    margin-top: -2px; /* Micro adjustment biar sejajar sama title */
}

.alert-title {
    color: var(--hcis-slate-900); /* Slate-900: Judul gelap tegas */
    font-size: 1rem;
    letter-spacing: -0.01em;
}

.alert-desc {
    font-size: 0.9rem;
    line-height: 1.5;
    color: var(--hcis-slate-600); /* Slate-600 */
}

.badge-text {
    background-color: #dbeafe; /* Blue-100 */
    color: #1e40af; /* Blue-800 */
    padding: 0px 4px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.85em;
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
