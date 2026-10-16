<template>
    <div class="container-fluid page-wrapper">
        <button
            class="btn btn-lg rounded-circle shadow-lg d-lg-none mobile-filter-trigger"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#filterOffcanvas"
            aria-controls="filterOffcanvas"
        >
            <i class="bi bi-funnel-fill iconMty"></i>
        </button>

        <div
            class="offcanvas offcanvas-bottom filter-bottom-sheet"
            tabindex="-1"
            id="filterOffcanvas"
            aria-labelledby="filterOffcanvasLabel"
        >
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="filterOffcanvasLabel">
                    <i class="bi bi-funnel-fill me-2"></i>Dashboard Filters
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"
                ></button>
            </div>
            <div class="offcanvas-body">
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <div class="form-group">
                            <el-select
                                v-model="selectedMonth"
                                filterable
                                placeholder="Month"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="item in months"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <el-select
                                v-model="selectedYear"
                                filterable
                                placeholder="Year"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="item in years"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-lg-6 order-2 order-lg-1">
                <div class="d-none d-lg-block">
                    <div class="card filter-card">
                        <div class="card-body d-flex flex-column">
                            <div>
                                <div class="filter-header">
                                    <i
                                        class="bi bi-funnel-fill mt-iconCustomTunnel"
                                    ></i>
                                    <h6 class="mb-3 ms-2">Dashboard Filters</h6>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <el-select
                                            v-model="selectedMonth"
                                            filterable
                                            placeholder="Month"
                                            style="width: 100%"
                                        >
                                            <el-option
                                                v-for="item in months"
                                                :key="item.value"
                                                :label="item.label"
                                                :value="item.value"
                                            />
                                        </el-select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <el-select
                                            v-model="selectedYear"
                                            filterable
                                            placeholder="Year"
                                            style="width: 100%"
                                        >
                                            <el-option
                                                v-for="item in years"
                                                :key="item.value"
                                                :label="item.label"
                                                :value="item.value"
                                            />
                                        </el-select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6 order-1 order-lg-2">
                <div
                    v-if="listDataWidget && listDataWidget.Performance"
                    class="widget-row mb-4"
                >
                    <div class="widget-card">
                        <div class="widget-content">
                            <div
                                class="d-flex align-items-center justify-content-between mb-2"
                            >
                                <div
                                    class="widget-icon-wrapper bg-soft-primary"
                                >
                                    <i
                                        class="bi bi-speedometer2 text-primary"
                                    ></i>
                                </div>
                                <h4
                                    class="widget-value mb-0"
                                    :style="{
                                        color: listDataWidget.Performance.Warna,
                                    }"
                                >
                                    {{
                                        listDataWidget.Performance.Keterangan ??
                                        ""
                                    }}
                                </h4>
                            </div>
                            <p class="widget-title mb-0">Performance</p>
                        </div>
                    </div>

                    <div class="widget-card">
                        <div class="widget-content">
                            <div
                                class="d-flex align-items-center justify-content-between mb-2"
                            >
                                <div
                                    class="widget-icon-wrapper bg-soft-success"
                                >
                                    <i class="bi bi-bullseye text-success"></i>
                                </div>
                                <h4 class="widget-value mb-0">100%</h4>
                            </div>
                            <p class="widget-title mb-0">Target Score</p>
                        </div>
                    </div>
                    <div class="widget-card">
                        <div class="widget-content">
                            <div
                                class="d-flex align-items-center justify-content-between mb-2"
                            >
                                <div
                                    class="widget-icon-wrapper bg-soft-warning"
                                >
                                    <i
                                        class="bi bi-trophy-fill text-warning"
                                    ></i>
                                </div>
                                <h4 class="widget-value mb-0">
                                    {{
                                        formatScore(
                                            listDataWidget.Achievement
                                        ) ?? 0
                                    }}%
                                </h4>
                            </div>
                            <p class="widget-title mb-0">Achievement</p>
                        </div>
                    </div>
                </div>
                <div v-else class="widget-row mb-4">
                    <div v-for="n in 3" :key="n" class="widget-card skeleton">
                        <div class="widget-content">
                            <div
                                class="d-flex align-items-center justify-content-between mb-2"
                            >
                                <div class="skeleton-item skeleton-icon"></div>
                                <div class="skeleton-item skeleton-value"></div>
                            </div>
                            <div class="skeleton-item skeleton-title"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 col-12 mb-3 order-1 order-lg-2">
                <CardRecentKpiUpdateUiV2
                    :title="kpiUpdatesTitle"
                    :items="kpiUpdatesList"
                    :loading="loading.kpiUpdatesList"
                    @view-detail="openDetailModal"
                >
                    <template #header-actions>
                        <div class="form-group mb-0 selectedRecentCari">
                            <el-input
                                v-model="filteredSearch"
                                clearable
                                placeholder="Cari Bulan...."
                            />
                        </div>
                    </template>
                    <template #footer>
                        <ul
                            class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                        >
                            <li
                                class="page-item"
                                :class="{ disabled: pagination.page === 1 }"
                            >
                                <a href="#" class="page-link" @click="prevPage"
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
                                    @click="changePage(page)"
                                    >{{ page }}</a
                                >
                            </li>
                            <li
                                class="page-item"
                                :class="{
                                    disabled:
                                        pagination.page ===
                                        pagination.totalPage,
                                }"
                            >
                                <a href="#" class="page-link" @click="nextPage"
                                    >→</a
                                >
                            </li>
                        </ul>
                    </template>
                </CardRecentKpiUpdateUiV2>
            </div>
            <div class="col-md-8 col-12 mb-3 order-2 order-lg-1">
                <CardLineChartUi
                    title="My Performance"
                    :chart-options="chartLineOptions"
                    :chart-series="chartLineSeries"
                >
                </CardLineChartUi>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12 mb-3 order-1 order-lg-2">
                <CardRecentKpiUpdateUiV2
                    :title="kpiUpdatesTitle"
                    :items="kpiUpdatesList"
                    :loading="loading.kpiUpdatesList"
                    @view-detail="openDetailModal"
                >
                    <template #header-actions>
                        <div class="form-group mb-0 selectedRecentCari">
                            <el-input
                                v-model="filteredSearch"
                                clearable
                                placeholder="Cari Bulan...."
                            />
                        </div>
                    </template>
                    <template #footer>
                        <ul
                            class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                        >
                            <li
                                class="page-item"
                                :class="{ disabled: pagination.page === 1 }"
                            >
                                <a href="#" class="page-link" @click="prevPage"
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
                                    @click="changePage(page)"
                                    >{{ page }}</a
                                >
                            </li>
                            <li
                                class="page-item"
                                :class="{
                                    disabled:
                                        pagination.page ===
                                        pagination.totalPage,
                                }"
                            >
                                <a href="#" class="page-link" @click="nextPage"
                                    >→</a
                                >
                            </li>
                        </ul>
                    </template>
                </CardRecentKpiUpdateUiV2>
            </div>
        </div>

        <modalUiV2
            :show="isModalVisible"
            :title="modalTitle"
            :is-read-only="true"
            @close="closeDetailModal"
        >
            <div v-if="loading.detailKPI" class="skeleton-wrapper">
                <div class="row mb-3">
                    <div class="col-lg-7">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="flex-shrink-0 me-3">
                                        <div
                                            class="skeleton skeleton-avatar"
                                        ></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div
                                            class="skeleton skeleton-text skeleton-title"
                                        ></div>
                                        <div
                                            class="skeleton skeleton-text skeleton-subtitle mt-2"
                                        ></div>
                                    </div>
                                </div>
                                <hr />
                                <div v-for="i in 4" :key="i" class="row mb-3">
                                    <div class="col-sm-4 col-5">
                                        <div
                                            class="skeleton skeleton-text"
                                        ></div>
                                    </div>
                                    <div class="col-sm-8 col-7">
                                        <div
                                            class="skeleton skeleton-text"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card h-100 border-0 shadow-sm">
                            <div
                                class="card-body p-4 d-flex flex-column justify-content-center align-items-center"
                            >
                                <div
                                    class="skeleton skeleton-text skeleton-title w-100 mb-4"
                                ></div>
                                <div class="skeleton skeleton-chart mb-4"></div>
                                <div class="row w-100">
                                    <div class="col-6">
                                        <div
                                            class="skeleton skeleton-box"
                                        ></div>
                                    </div>
                                    <div class="col-6">
                                        <div
                                            class="skeleton skeleton-box"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="skeleton skeleton-final-review"></div>
            </div>

            <div v-else>
                <div class="row mb-3">
                    <div class="col-lg-12">
                        <div class="card h-100 border-0 shadow-sm h-100">
                            <div
                                class="card-body p-4 d-flex flex-column justify-content-center align-items-center"
                            >
                                <h5 class="fw-bold mb-4 align-self-start">
                                    Performance Summary
                                </h5>

                                <RadialChart
                                    :chart-series="radialChartSeries"
                                    :chart-options="radialChartOptions"
                                    :height="350"
                                />

                                <div class="row w-100">
                                    <div class="col-6">
                                        <div class="kpi-stat-box">
                                            <span class="stat-label"
                                                >TARGET SCORE</span
                                            >
                                            <div
                                                class="stat-value text-primary"
                                            >
                                                100%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="kpi-stat-box">
                                            <span class="stat-label"
                                                >STATUS</span
                                            >
                                            <div
                                                class="stat-value"
                                                :style="{
                                                    color: kpiRecentUpdateHeaders.Warna,
                                                }"
                                            >
                                                {{
                                                    kpiRecentUpdateHeaders.Status
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3 review-section">
                    <h4>KPI's</h4>
                    <FinalReview
                        :kpi-items="kpiItemsExample"
                        :requiresPica="true"
                        v-model="agreementChecked"
                        :statusPengajuan="'proses'"
                    />
                </div>
            </div>
        </modalUiV2>
    </div>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";
import { ElSelect, ElOption, ElInput } from "element-plus";
import ApexChart from "vue3-apexcharts";
import cardChartBarUiV1 from "../../../components/ui/dashboard/cardChartBarUiV1.vue";
import cardChartLineUiV1 from "../../../components/ui/dashboard/cardChartLineUiV1.vue";
import cardRecentKpiUpdateUiV2 from "../../../components/ui/dashboard/cardRecentKpiUpdateUiV2.vue";
import cardTopPerformanceUiV1 from "../../../components/ui/dashboard/cardTopPerformanceUiV1.vue";
import cardUnderPerformanceUiV1 from "../../../components/ui/dashboard/cardUnderPerformanceUiV1.vue";
import cardChartHeatmapUiV1 from "../../../components/ui/dashboard/cardChartHeatmapUiV1.vue";
import modalUiV2 from "../../../components/ui/modalUiV2.vue";
import RadialChartUiV1 from "../../../components/ui/dashboard/RadialChartUiV1.vue";
import finalReviewUiV1 from "../../../components/ui/finalReviewUiV1.vue";

export default {
    name: "DashboardKpi",
    components: {
        ElSelect,
        ElOption,
        ElInput,
        modalUiV2,
        apexchart: ApexChart,
        CardBarChartUi: cardChartBarUiV1,
        CardLineChartUi: cardChartLineUiV1,
        CardRecentKpiUpdateUiV2: cardRecentKpiUpdateUiV2,
        CardTopPerformanceUiV1: cardTopPerformanceUiV1,
        CardUnderPerformanceUiV1: cardUnderPerformanceUiV1,
        CardHeatmapChartUi: cardChartHeatmapUiV1,
        RadialChart: RadialChartUiV1,
        FinalReview: finalReviewUiV1,
    },
    data() {
        return {
            listDataWidget: {},
            selectedMonth: null,
            selectedYear: null,
            agreementChecked: false,
            kpiRecentUpdateHeaders: {},
            kpiItemsExample: [],
            isModalVisible: false,
            filteredSearch: "",
            statusSortDirection: null,
            radialChartSeries: [0],
            radialChartTitle: "Overall Achievement",
            radialChartSeries: [0],
            radialChartOptions: {
                chart: {
                    height: 500,
                    type: "radialBar",
                },
                plotOptions: {
                    radialBar: {
                        dataLabels: {
                            show: true,
                            name: {
                                offsetY: 20,
                                show: true,
                                color: "#888",
                                fontSize: "9px",
                                formatter: function (seriesName) {
                                    return "Achievement";
                                },
                            },
                            value: {
                                offsetY: -10,
                                color: "#111",
                                fontSize: "20px",
                                show: true,
                                formatter: function (val) {
                                    return val + "%";
                                },
                            },
                        },
                    },
                },
                stroke: {
                    lineCap: "round",
                },
                labels: ["Achievement"],
                colors: ["#008FFB"],
            },
            lineChartTitle: "Trend",
            chartLineSeries: [
                {
                    name: "Achievement",
                    data: [],
                },
            ],
            chartLineOptions: {
                chart: {
                    height: 311,
                    type: "line",
                    zoom: { enabled: false },
                    toolbar: { show: true },
                    animations: {
                        enabled: true,
                        easing: "easeinout",
                        speed: 1000,
                        dynamicAnimation: { enabled: true, speed: 500 },
                    },
                },
                dataLabels: { enabled: false },
                stroke: { curve: "smooth", width: 2 },
                markers: { size: 5, hover: { sizeOffset: 2 } },
                xaxis: {
                    categories: [], // Awalnya kosong, akan diisi dari API
                    labels: {
                        style: {
                            fontSize: "10px",
                            colors: "#333",
                            fontWeight: 500,
                        },
                    },
                },
                yaxis: { title: { text: "Performance Score" } },
                legend: { show: true, fontSize: "9px", offsetY: 20 },
                tooltip: { y: { formatter: (val) => val } }, // Disesuaikan, mungkin tidak perlu '%'
                grid: {
                    borderColor: "#e7e7e7",
                    strokeDashArray: 5,
                },
                colors: ["#0d6efd", "#ffc107"],
            },
            divisions: [],
            departments: [],
            months: [
                { label: "Full-Year", value: "all" },
                { label: "Januari", value: "01" },
                { label: "Februari", value: "02" },
                { label: "Maret", value: "03" },
                { label: "April", value: "04" },
                { label: "Mei", value: "05" },
                { label: "Juni", value: "06" },
                { label: "Juli", value: "07" },
                { label: "Agustus", value: "08" },
                { label: "September", value: "09" },
                { label: "Oktober", value: "10" },
                { label: "November", value: "11" },
                { label: "Desember", value: "12" },
            ],
            level: [],
            kpiUpdatesList: [],
            topPerformersList: [],
            underPerformersList: [],
            years: [],
            kpiUpdatesTitle: "Recent KPI Updates",
            selectedDivision: ["ALL"],
            selectedDepartment: ["ALL"],
            selectedLevel: ["ALL"],
            selectedDepartmentForLine: null,
            selectedYearForLine: null,
            topPerformersTitle: "Top 3 Performers",
            selectedLevelForTop: ["ALL"],
            underPerformersTitle: "Under 3 Performers",
            selectedLevelForUnder: ["ALL"],

            isDepartmentDisabled: false,
            loading: {
                divisions: true,
                departments: false,
                detailKPI: false,
                kpiUpdatesList: true,
                departments: true,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
        };
    },
    computed: {
        modalTitle() {
            return "Detail My Performance";
        },
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = current - 2;
            let end = current + 2;

            if (start < 1) {
                start = 1;
                end = Math.min(5, total);
            }

            if (end > total) {
                end = total;
                start = Math.max(1, total - 4);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
        departmentPlaceholder() {
            return this.isDepartmentDisabled
                ? "Select a Division first"
                : "Departement";
        },
        activeFilters() {
            return {
                div: this.selectedDivision,
                dept: this.selectedDepartment,
                lvl: this.selectedLevel,
                mon: this.selectedMonth,
                yr: this.selectedYear,
                lvltp: this.selectedLevelForTop,
                lvlur: this.selectedLevelForUnder,
            };
        },
    },
    methods: {
        async fetchListLevel() {
            this.loading.level = true;
            try {
                const response = await axios.get(
                    "/dashboard-ceo/option/list-level"
                );
                if (response.status === 200 && response.data?.result) {
                    const levelList = response.data.result.map((item) => ({
                        value: item.Id_Level,
                        label: `${item.nama_level}`,
                    }));

                    this.level = [{ value: "ALL", label: "All" }, ...levelList];
                }
            } catch (error) {
                console.error("Gagal mengambil data satuan:", error);
                this.level = [];
            } finally {
                this.loading.level = false;
            }
        },
        async fetchListData(page = 1) {
            this.loading.kpiUpdatesList = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    q: this.filteredSearch,
                    bulan: this.selectedMonth,
                    tahun: this.selectedYear,
                };

                if (this.statusSortDirection) {
                    params.sort_status = this.statusSortDirection;
                }
                const response = await axios.get(
                    "/api/v1/dashboard-ceo/my-performance",
                    {
                        params,
                    }
                );
                if (response.status === 200 && response.data?.result) {
                    this.kpiUpdatesList =
                        response.data.result.Recent_Kpi_Update.data;
                    this.listDataWidget = response.data.result.Widget;
                    this.topPerformersList =
                        response.data.result.Top_3_Performers;
                    this.underPerformersList =
                        response.data.result.Under_3_Performers;
                    this.pagination.page = page;
                    this.pagination.totalPage =
                        response.data.result.Recent_Kpi_Update.total_page;
                    this.pagination.totalData =
                        response.data.result.Recent_Kpi_Update.total_data;

                    this.$nextTick(() => {
                        this.selectedMonth = response.data.result.selectedMonth;
                        this.selectedYear = response.data.result.selectedYear;
                    });
                    const chartLineData = response.data.result.Chart_Line;
                    if (
                        chartLineData &&
                        chartLineData.labels &&
                        chartLineData.series
                    ) {
                        this.chartLineSeries = [
                            {
                                name: "Rata-rata Kinerja",
                                data: chartLineData.series,
                            },
                        ];
                        this.chartLineOptions = {
                            ...this.chartLineOptions,
                            xaxis: {
                                ...this.chartLineOptions.xaxis,
                                categories: chartLineData.labels,
                            },
                        };
                    }
                } else {
                    this.kpiUpdatesList = [];

                    this.listDataWidget = {};
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.kpiUpdatesList = [];
                console.error("Gagal mengambil data list:", error);
            } finally {
                this.loading.kpiUpdatesList = false;
            }
        },
        async fetchDetailRecentKpi(item) {
            this.loading.detailKPI = true;
            this.kpiRecentUpdateHeaders = {};
            this.kpiItemsExample = [];

            try {
                const response = await axios.get(
                    `/api/v1/dashboard-ceo/detail-my-performance/${item.Id_Pengajuan}/${item.Id_Update_Performance}`
                );

                if (response.status === 200 && response.data?.result) {
                    const header = response.data.result.header;
                    this.kpiRecentUpdateHeaders = header;
                    this.kpiItemsExample = response.data.result.details;

                    const achievementValue =
                        header?.Achievement !== undefined &&
                        header?.Achievement !== null
                            ? parseFloat(header.Achievement)
                            : 0;
                    this.radialChartSeries = [achievementValue];

                    if (header?.Warna) {
                        this.radialChartOptions = {
                            ...this.radialChartOptions,
                            colors: [header.Warna],
                        };
                    } else {
                        this.radialChartOptions = {
                            ...this.radialChartOptions,
                            colors: ["#008FFB"],
                        };
                    }
                }
            } catch (error) {
                this.kpiRecentUpdateHeaders = {};
                this.kpiItemsExample = [];
                this.radialChartSeries = [0];
                this.radialChartOptions = {
                    ...this.radialChartOptions,
                    colors: ["#008FFB"],
                };
                console.error("Gagal mengambil data list:", error);
            } finally {
                this.loading.detailKPI = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchListData(1);
        }, 500),
        changeSortDirection(direction) {
            if (this.statusSortDirection === direction) {
                this.statusSortDirection = null;
            } else {
                this.statusSortDirection = direction;
            }
            this.fetchListData(1);
        },
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchListData(
                    this.pagination.page + 1,
                    this.filteredSearch
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchListData(
                    this.pagination.page - 1,
                    this.filteredSearch
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchListData(page, this.filteredSearch);
            }
        },
        openDetailModal(item) {
            this.fetchDetailRecentKpi(item);
            this.isModalVisible = true;
        },
        closeDetailModal() {
            this.isModalVisible = false;
        },

        formatKontak(kontak) {
            if (!kontak) return "";

            let clean = kontak.toString().replace(/[\s-]/g, "");

            if (clean.startsWith("+62")) {
                return clean;
            }

            if (clean.startsWith("62")) {
                return "+" + clean;
            }

            if (clean.startsWith("0")) {
                return "+62" + clean.substring(1);
            }

            return clean;
        },
        formatScore(value) {
            if (typeof value !== "number" || !isFinite(value)) {
                return 0;
            }
            if (Number.isInteger(value)) {
                return value;
            }

            let rounded = parseFloat(value.toFixed(1));

            if (Number.isInteger(rounded)) {
                return rounded;
            }

            return rounded;
        },
    },

    watch: {
        filteredSearch() {
            this.debouncedSearch();
        },
        activeFilters: {
            handler() {
                this.fetchListData(1);
            },
            deep: true,
            immediate: true,
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary-color: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #e0e7ff;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-400: #9ca3af;
    --gray-600: #4b5563;
    --gray-800: #1f2937;
    --text-color: #374151;
    --white-color: #ffffff;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --border-radius: 0.75rem;
    --transition-speed: 0.3s;
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --avatar-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --division-bg: #4a90e2;
    --division-text: #ffffff;
    --subdivision-bg: #e8f3ff;
    --border-color: #e9ecef;
    --subdivision-text: #4a90e2;

    --tag-excellent-bg: #e6f7f2;
    --tag-excellent-text: #00875a;
    --tag-excellent-border: #c3e8de;

    --tag-good-bg: #eaf3ff;
    --tag-good-text: #2a75d9;
    --tag-good-border: #cce0ff;

    --tag-warning-bg: #fffbe6;
    --tag-warning-text: #f5a623;
    --tag-warning-border: #ffedb3;

    --tag-satisfactory-bg: #fffbe6;
    --tag-satisfactory-text: #f5a623;
    --tag-satisfactory-border: #ffedb3;

    --tag-danger-bg: #fee2e2;
    --tag-danger-text: #dc2626;
    --tag-danger-border: #fecaca;
}

@keyframes shimmerRecentUpdate {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

.skeleton {
    background-color: #e0e0e0;
    border-radius: 4px;
    background-image: linear-gradient(
        to right,
        #e0e0e0 0%,
        #f0f0f0 20%,
        #e0e0e0 40%,
        #e0e0e0 100%
    );
    background-repeat: no-repeat;
    background-size: 2000px 104px;
    animation: shimmerRecentUpdate 2s linear infinite;
    display: inline-block;
}

.skeleton-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
}

.skeleton-text {
    width: 100%;
    height: 16px;
}

.skeleton-title {
    width: 60%;
    height: 24px;
}

.skeleton-subtitle {
    width: 80%;
}

.skeleton-chart {
    width: 150px;
    height: 150px;
    border-radius: 50%;
}

.skeleton-box {
    width: 100%;
    height: 60px;
    border-radius: 8px;
}

.skeleton-final-review {
    width: 100%;
    height: 200px;
    border-radius: 8px;
}

.widget-card.skeleton {
    background-color: #ffffff;
    border: 1px solid #e0e0e0;
}

.skeleton-item {
    background-color: #e0e0e0;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.skeleton-item::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    transform: translateX(-100%);
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.5),
        transparent
    );
    animation: shimmer-animation 1.5s infinite;
}

@keyframes shimmer-animation {
    100% {
        transform: translateX(100%);
    }
}

.skeleton-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
}

.skeleton-value {
    width: 40%;
    height: 28px;
}

.skeleton-title {
    width: 60%;
    height: 16px;
}

.widget-row {
    display: flex;
    gap: 1.5rem;
    width: 100%;
}

.widget-card {
    flex: 1;
}

.widget-content {
    width: 100%;
}

.kpi-stat-box {
    background-color: #fff;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.kpi-stat-box .stat-label {
    display: block;
    font-size: 0.75rem;
    color: #888;
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.kpi-stat-box .stat-value {
    font-size: 1.25rem;
    font-weight: 600;
}

.employee-card__avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--avatar-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 600;
    flex-shrink: 0;
}

h6 {
    color: var(--gray-800);
}

.active-sort {
    color: #007bff;
}

.badge {
    padding: 2px 8px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge--division {
    background-color: var(--division-bg);
    color: var(--division-text);
}
.mt-iconCustomTunnel {
    margin-top: -30px;
}
.mt-customPeformers {
    margin-top: -12px;
}
.card {
    background: white !important;
}
.filter-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}
.filter-header {
    display: flex;
    align-items: center;
}
.widget-content {
    width: 100%;
}
.widget-title {
    font-size: 0.84rem;
    color: #6c757d;
    font-weight: 500;
    margin-top: 2px;
}
.widget-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #212529;
}
.widget-icon-wrapper {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.bg-soft-primary {
    background-color: #e8f0ff !important;
}
.bg-soft-success {
    background-color: #eaf8f1 !important;
}
.bg-soft-warning {
    background-color: #fff8e5 !important;
}
.text-success {
    color: #198754 !important;
}
.text-danger {
    color: #dc3545 !important;
}
/*
==================================================
 [PERBAIKAN] LOGIKA RESPONSIVE & LAYOUT
==================================================
*/
/* 1. Pembungkus Halaman untuk Kontrol Penuh */
.page-wrapper.container-fluid {
    overflow-x: hidden; /* Mencegah seluruh halaman bisa di-scroll */
}

.page-wrapper > .row > [class*="col-"] {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

/* 2. Style Default untuk Widget (Desktop) */
.widget-row {
    display: flex;
    flex-wrap: wrap; /* [PENTING] Widget akan turun baris jika tidak muat */
    gap: 16px;
}
.widget-card {
    flex: 1 1 calc(33.333% - 11px); /* Tumbuh dan menyusut, 3 per baris */
    min-width: 180px; /* Batas minimal agar tidak terlalu kecil */
    background-color: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    cursor: pointer;
}
.widget-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
}

/* 3. Style untuk Tombol Filter & Bottom Sheet (Mobile) */
.mobile-filter-trigger {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background: var(--gradient-primary);
    color: white;
    border: none;
    z-index: 1045;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    animation: pop-in 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.iconMty {
    transform: translate(1px, 1px);
}
@keyframes pop-in {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.filter-bottom-sheet.offcanvas-bottom {
    height: auto;
    max-height: 70vh;
    border-top-left-radius: 1.5rem;
    border-top-right-radius: 1.5rem;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.15);
}
.filter-bottom-sheet .offcanvas-header {
    border-bottom: 1px solid var(--gray-200);
    padding: 1rem 1.5rem;
}
.filter-bottom-sheet .offcanvas-title {
    color: var(--gray-800);
    font-weight: 600;
}
.filter-bottom-sheet .offcanvas-body {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
}

.iconMty {
    margin-left: -5px;
    margin-top: -10px;
}

/*
==================================================
 MEDIA QUERIES (Aturan Khusus untuk Layar Kecil)
==================================================
*/
/* Tampilan Tablet & Mobile (di bawah 992px) */
@media (max-width: 991.98px) {
    .mt-customPeformers {
        margin-top: 0 !important;
    }
    .widget-row {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 15px;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .widget-row::-webkit-scrollbar {
        display: none;
    }
    .widget-row .widget-card {
        flex: 0 0 auto;
        width: 190px;
    }
}

@media (max-width: 575.98px) {
    .widget-row .widget-card {
        width: 170px;
    }
    .widget-value {
        font-size: 1.25rem;
    }
    .widget-title {
        font-size: 0.8rem;
    }
    .mobile-filter-trigger {
        bottom: 20px;
        right: 20px;
    }
    .filter-bottom-sheet.offcanvas-bottom {
        max-height: 80vh;
    }
    .selectedRecentCari {
        width: 120px;
    }
}
</style>
<!-- <style scoped>
*,
*::before,
*::after {
    --primary-color: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #e0e7ff;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-400: #9ca3af;
    --gray-600: #4b5563;
    --gray-800: #1f2937;
    --text-color: #374151;
    --white-color: #ffffff;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --border-radius: 0.75rem;
    --transition-speed: 0.3s;
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --avatar-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --division-bg: #4a90e2;
    --division-text: #ffffff;
    --subdivision-bg: #e8f3ff;
    --border-color: #e9ecef;
    --subdivision-text: #4a90e2;

    --tag-excellent-bg: #e6f7f2;
    --tag-excellent-text: #00875a;
    --tag-excellent-border: #c3e8de;

    --tag-good-bg: #eaf3ff;
    --tag-good-text: #2a75d9;
    --tag-good-border: #cce0ff;

    --tag-warning-bg: #fffbe6;
    --tag-warning-text: #f5a623;
    --tag-warning-border: #ffedb3;

    --tag-satisfactory-bg: #fffbe6;
    --tag-satisfactory-text: #f5a623;
    --tag-satisfactory-border: #ffedb3;

    --tag-danger-bg: #fee2e2;
    --tag-danger-text: #dc2626;
    --tag-danger-border: #fecaca;
}

h6 {
    color: var(--gray-800);
}

.active-sort {
    color: #007bff;
}

.badge {
    padding: 2px 8px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge--division {
    background-color: var(--division-bg);
    color: var(--division-text);
}

.review-section {
    margin-top: 2.5rem;
}
.review-section h4 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.mt-customPeformers {
    margin-top: -12px;
}

.tag-excellent {
    background-color: var(--tag-excellent-bg) !important;
    color: var(--tag-excellent-text) !important;
    border-color: var(--tag-excellent-border) !important;
}

.kpi-stat-box {
    background-color: #fff;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.kpi-stat-box .stat-label {
    display: block;
    font-size: 0.75rem;
    color: #888;
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.kpi-stat-box .stat-value {
    font-size: 1.25rem;
    font-weight: 600;
}

.employee-card__avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--avatar-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 600;
    flex-shrink: 0;
}

.legend-color-box {
    width: 15px;
    height: 15px;
    display: inline-block;
    margin-right: 8px;
    border-radius: 3px;
    vertical-align: middle;
}

.card {
    background: white !important;
}

.mt-iconCustomTunnel {
    margin-top: -30px;
}
.filter-card {
    color: white;
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.filter-header {
    display: flex;
    align-items: center;
}

.filter-header .bi {
    color: var(--gray-800);
    font-size: 1.25rem;
}

.filter-header h6 {
    color: var(--gray-800);
    font-weight: 600;
    font-size: 1.1rem;
}

.card-text {
    color: var(--text-color);
    font-size: 0.9rem;
}

.form-label {
    font-weight: 500;
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}
.bg-soft-primary {
    background-color: rgba(102, 126, 234, 0.1);
}
.text-primary {
    color: #667eea !important;
}

.bg-soft-success {
    background-color: rgba(25, 135, 84, 0.1);
}
.text-success {
    color: #198754 !important;
}

.bg-soft-warning {
    background-color: rgba(255, 193, 7, 0.1);
}
.text-warning {
    color: #ffc107 !important;
}
.widget-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: flex-start;
}

.widget-card {
    flex: 1 1 calc(33.333% - 16px);
    background-color: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.25s ease;
    cursor: pointer;
    min-width: 180px;
}

.widget-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
    border-color: #dee2e6;
}

.widget-content {
    width: 100%;
}

.left-section {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.widget-icon-wrapper {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.widget-icon-wrapper .bi {
    font-size: 1.5rem;
    color: #0d6efd;
}

.bg-soft-primary {
    background-color: #e8f0ff !important;
}
.bg-soft-success {
    background-color: #eaf8f1 !important;
}
.bg-soft-warning {
    background-color: #fff8e5 !important;
}

.widget-title {
    font-size: 0.84rem;
    color: #6c757d;
    font-weight: 500;
    margin-top: 2px;
}

.widget-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: #212529;
    letter-spacing: 0.3px;
}

.selectedRecent {
    width: 100px;
}
.selectedRecentCari {
    width: 150px;
}

.skeleton-box {
    background-color: #e0e0e0;
    border-radius: 4px;
    animation: pulse-bg 1.5s infinite;
}

@keyframes pulse-bg {
    0% {
        background-color: #e0e0e0;
    }
    50% {
        background-color: #f0f0f0;
    }
    100% {
        background-color: #e0e0e0;
    }
}

.skeleton-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
}

.skeleton-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
}

.skeleton-line-sm {
    height: 12px;
}
.skeleton-line-md {
    height: 16px;
}
.skeleton-line-lg {
    height: 24px;
}
.skeleton-line-xl {
    height: 28px;
}

.review-item-card {
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1.5rem;
}
.review-card-main {
    width: 100%;
}
.review-card-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}
.info-block {
    flex: 1;
    padding: 0 10px;
}

.iconMty {
    margin-left: -5px;
    margin-top: -10px;
}

.page-wrapper.container-fluid {
    overflow-x: hidden;
}

/* [FIX] Sesuaikan .row di dalam .page-wrapper agar pas */
.page-wrapper > .row {
    margin-left: -0.5rem;
    margin-right: -0.5rem;
}
.page-wrapper > .row > [class*="col-"] {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.mobile-filter-trigger {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background: var(--gradient-primary);
    color: white;
    z-index: 1045;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    animation: pop-in 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes pop-in {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.filter-bottom-sheet.offcanvas-bottom {
    height: auto;
    max-height: 70vh;
    border-top-left-radius: 1.5rem;
    border-top-right-radius: 1.5rem;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.15);
}

.filter-bottom-sheet .offcanvas-header {
    border-bottom: 1px solid var(--gray-200);
    padding: 1rem 1.5rem;
}

.filter-bottom-sheet .offcanvas-title {
    color: var(--gray-800);
    font-weight: 600;
}

.filter-bottom-sheet .offcanvas-body {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
}

.widget-row-scroll {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 15px;
    margin-left: -8px;
    margin-right: -8px;
}

.widget-row-scroll::-webkit-scrollbar {
    display: none;
}
.widget-row-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.widget-row-scroll .widget-card {
    flex: 0 0 auto;
    width: calc(33.333% - 16px);
    margin: 0 8px;
    background-color: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.25s ease;
    cursor: pointer;
}
.widget-row-scroll .widget-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
}

.widget-row:not(.widget-row-scroll) .widget-card {
    flex: 1 1 calc(33.333% - 16px);
    background-color: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.25s ease;
    cursor: pointer;
    min-width: 180px;
}
.widget-row:not(.widget-row-scroll) {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: flex-start;
}

@media (max-width: 991.98px) {
    .mt-customPeformers {
        margin-top: 0 !important;
    }
    .widget-row-scroll .widget-card {
        width: 190px;
    }
}

@media (max-width: 575.98px) {
    .widget-row-scroll .widget-card {
        width: 170px;
    }
    .widget-value {
        font-size: 1.25rem;
    }
    .widget-title {
        font-size: 0.8rem;
    }
    .mobile-filter-trigger {
        bottom: 20px;
        right: 20px;
    }
    .filter-bottom-sheet.offcanvas-bottom {
        max-height: 80vh;
    }
    .selectedRecentCari {
        width: 120px;
    }
}
</style> -->
