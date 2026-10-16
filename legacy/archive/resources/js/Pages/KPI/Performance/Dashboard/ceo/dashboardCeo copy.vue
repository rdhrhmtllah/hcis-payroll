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
                                v-model="selectedDivision"
                                filterable
                                placeholder="Division"
                                style="width: 100%"
                                multiple
                                clearable
                                @change="handleDivisionChange"
                            >
                                <el-option
                                    v-for="item in divisions"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="
                                        selectedDivision.includes('ALL') &&
                                        item.value !== 'ALL'
                                    "
                                />
                            </el-select>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="form-group">
                            <el-select
                                v-model="selectedDepartment"
                                filterable
                                :placeholder="departmentPlaceholder"
                                style="width: 100%"
                                multiple
                                clearable
                                :disabled="isDepartmentDisabled"
                                :loading="loading.departments"
                                @change="handleDepartmentChange"
                            >
                                <el-option
                                    v-for="item in departments"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="
                                        selectedDepartment.includes('ALL') &&
                                        item.value !== 'ALL'
                                    "
                                />
                            </el-select>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="form-group">
                            <el-select
                                v-model="selectedLevel"
                                placeholder="Level"
                                filterable
                                multiple
                                @change="handleLevelChange"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="item in level"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="
                                        selectedLevel.includes('ALL') &&
                                        item.value !== 'ALL'
                                    "
                                />
                            </el-select>
                        </div>
                    </div>
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
                                <div class="col-4">
                                    <div class="form-group">
                                        <el-select
                                            v-model="selectedDivision"
                                            filterable
                                            placeholder="Division"
                                            style="width: 100%"
                                            multiple
                                            clearable
                                            @change="handleDivisionChange"
                                        >
                                            <el-option
                                                v-for="item in divisions"
                                                :key="item.value"
                                                :label="item.label"
                                                :value="item.value"
                                                :disabled="
                                                    selectedDivision.includes(
                                                        'ALL'
                                                    ) && item.value !== 'ALL'
                                                "
                                            />
                                        </el-select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <el-select
                                            v-model="selectedDepartment"
                                            filterable
                                            :placeholder="departmentPlaceholder"
                                            style="width: 100%"
                                            multiple
                                            clearable
                                            :disabled="isDepartmentDisabled"
                                            :loading="loading.departments"
                                            @change="handleDepartmentChange"
                                        >
                                            <el-option
                                                v-for="item in departments"
                                                :key="item.value"
                                                :label="item.label"
                                                :value="item.value"
                                                :disabled="
                                                    selectedDepartment.includes(
                                                        'ALL'
                                                    ) && item.value !== 'ALL'
                                                "
                                            />
                                        </el-select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div
                                        class="form-group mb-0"
                                        style="width: 100%"
                                    >
                                        <el-select
                                            v-model="selectedLevel"
                                            placeholder="Level"
                                            filterable
                                            multiple
                                            @change="handleLevelChange"
                                        >
                                            <el-option
                                                v-for="item in level"
                                                :key="item.value"
                                                :label="item.label"
                                                :value="item.value"
                                                :disabled="
                                                    selectedLevel.includes(
                                                        'ALL'
                                                    ) && item.value !== 'ALL'
                                                "
                                            />
                                        </el-select>
                                    </div>
                                </div>
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

            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="widget-row mb-4">
                            <div class="widget-card">
                                <div class="widget-content">
                                    <div
                                        class="d-flex align-items-center justify-content-between mb-2"
                                    >
                                        <div
                                            class="widget-icon-wrapper bg-soft-primary"
                                        >
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <h4 class="widget-value mb-0">
                                            {{ listDataWidget.Employee ?? 0 }}
                                        </h4>
                                    </div>
                                    <p class="widget-title mb-0">
                                        Total Employee
                                    </p>
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
                                            <i
                                                class="bi bi-graph-up-arrow text-success"
                                            ></i>
                                        </div>
                                        <h4 class="widget-value mb-0">
                                            {{ listDataWidget.On_Target ?? 0 }}
                                        </h4>
                                    </div>
                                    <p class="widget-title mb-0">On Target</p>
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
                                                class="bi bi-graph-down-arrow text-danger"
                                            ></i>
                                        </div>
                                        <h4 class="widget-value mb-0">
                                            {{
                                                listDataWidget.Under_Target ?? 0
                                            }}
                                        </h4>
                                    </div>
                                    <p class="widget-title mb-0">
                                        Under Target
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12 mb-3">
                <CardBarChartUi
                    :title="chartTitleBar"
                    :chart-options="chartBarOptions"
                    :chart-series="chartBarSeries"
                    :legends="chartLegends"
                ></CardBarChartUi>
            </div>
            <div class="col-lg-12 mb-3">
                <CardRecentKpiUpdateUiV1
                    :title="kpiUpdatesTitle"
                    :items="kpiUpdatesList"
                    :loading="
                        loading.kpiUpdatesList || loading.isSearchingOrSorting
                    "
                    @view-detail="openDetailModal"
                >
                    <template #header-actions>
                        <div class="form-group mb-0 selectedRecentCari">
                            <el-input
                                v-model="filteredSearch"
                                clearable
                                placeholder="Name,Dep,Div..."
                            />
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <span
                                @click="changeSortDirection('asc')"
                                :class="{
                                    'active-sort':
                                        statusSortDirection === 'asc',
                                }"
                                style="
                                    user-select: none;
                                    font-weight: bold;
                                    line-height: 0.6;
                                    cursor: pointer;
                                "
                                >^</span
                            >
                            <span
                                @click="changeSortDirection('desc')"
                                :class="{
                                    'active-sort':
                                        statusSortDirection === 'desc',
                                }"
                                style="
                                    user-select: none;
                                    font-weight: bold;
                                    line-height: 0.9;
                                    cursor: pointer;
                                "
                                >v</span
                            >
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
                </CardRecentKpiUpdateUiV1>
            </div>
            <div class="col-12 mb-3">
                <CardHeatmapChartUi
                    :title="heatmapTitle"
                    :chart-options="heatmapOptions"
                    :chart-series="heatmapSeries"
                ></CardHeatmapChartUi>
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
                    <div class="col-lg-7">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="flex-shrink-0 me-3">
                                        <div
                                            class="d-flex align-items-center justify-content-center rounded-circle employee-card__avatar"
                                        >
                                            <i class="fa fa-user-tie"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div
                                            class="d-flex align-items-center justify-content-between"
                                        >
                                            <h5 class="mb-0 fw-bold">
                                                {{
                                                    kpiRecentUpdateHeaders.Nama_Lengkap ||
                                                    "..."
                                                }}
                                            </h5>
                                            <p class="text-muted mb-0">
                                                <span
                                                    class="badge badge--division"
                                                >
                                                    {{
                                                        kpiRecentUpdateHeaders.Periode ||
                                                        "..."
                                                    }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <hr />

                                <div class="row mb-2">
                                    <div class="col-sm-4 col-5 text-muted">
                                        Departemen
                                    </div>
                                    <div class="col-sm-8 col-7 fw-semibold">
                                        {{
                                            kpiRecentUpdateHeaders.Nama_Departemen ||
                                            "-"
                                        }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-4 col-5 text-muted">
                                        Posisi
                                    </div>
                                    <div class="col-sm-8 col-7 fw-semibold">
                                        {{
                                            kpiRecentUpdateHeaders.Nama_Jabatan ||
                                            "-"
                                        }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-4 col-5 text-muted">
                                        Email
                                    </div>
                                    <div class="col-sm-8 col-7 fw-semibold">
                                        {{
                                            kpiRecentUpdateHeaders.Email || "-"
                                        }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-4 col-5 text-muted">
                                        Kontak
                                    </div>
                                    <div class="col-sm-8 col-7 fw-semibold">
                                        {{
                                            formatKontak(
                                                kpiRecentUpdateHeaders.Kontak
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
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
                                    :height="150"
                                />

                                <div class="row w-100">
                                    <div class="col-6">
                                        <div class="kpi-stat-box">
                                            <span class="stat-label"
                                                >TARGET SCORE</span
                                            >
                                            <div class="stat-value">
                                                <h6 class="text-primary">
                                                    100%
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="kpi-stat-box">
                                            <span class="stat-label"
                                                >STATUS</span
                                            >
                                            <div class="stat-value">
                                                <h6
                                                    :style="{
                                                        color: kpiRecentUpdateHeaders.Warna,
                                                    }"
                                                >
                                                    {{
                                                        kpiRecentUpdateHeaders.Status
                                                    }}
                                                </h6>
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
import cardRecentKpiUpdateUiV1 from "../../../components/ui/dashboard/cardRecentKpiUpdateUiV1.vue";
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
        CardRecentKpiUpdateUiV1: cardRecentKpiUpdateUiV1,
        CardTopPerformanceUiV1: cardTopPerformanceUiV1,
        CardUnderPerformanceUiV1: cardUnderPerformanceUiV1,
        CardHeatmapChartUi: cardChartHeatmapUiV1,
        RadialChart: RadialChartUiV1,
        FinalReview: finalReviewUiV1,
    },
    data() {
        const currentYear = new Date().getFullYear();
        const years = Array.from({ length: 5 }, (_, i) => ({
            label: (currentYear - 2 + i).toString(),
            value: (currentYear - 2 + i).toString(),
        }));

        return {
            agreementChecked: false,
            isSearchingOrSorting: false,
            isModalVisible: false,
            isDepartmentDisabled: false,
            selectedDepartmentForLine: null,
            selectedYearForLine: null,
            statusSortDirection: null,
            listDataWidget: {},
            kpiRecentUpdateHeaders: {},
            kpiItemsExample: [],
            radialChartSeries: [0],
            kpiUpdatesTitle: "Recent KPI Updates",
            filteredSearch: "",
            radialChartTitle: "Overall Achievement",
            radialChartOptions: {
                chart: {
                    height: 200,
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
            heatmapTitle: "Grafik Trend Performance",
            heatmapSeries: [],
            heatmapDetails: [],
            heatmapOptions: {
                chart: {
                    height: 800,
                    type: "heatmap",
                    toolbar: {
                        show: true,
                    },
                    animations: {
                        enabled: true,
                        easing: "easeinout",
                        speed: 800,
                        animateGradually: {
                            enabled: true,
                            delay: 150,
                        },
                        dynamicAnimation: {
                            enabled: true,
                            speed: 350,
                        },
                    },
                    noData: {
                        text: "Tidak ada data kinerja untuk ditampilkan",
                        align: "center",
                        verticalAlign: "middle",
                        style: {
                            color: "#666",
                            fontSize: "14px",
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    style: {
                        fontSize: "10px",
                        colors: ["#333"],
                    },
                    formatter: (val) => {
                        if (val === -1) {
                            return "N/A";
                        }
                        return val + "%";
                    },
                },
                plotOptions: {
                    heatmap: {
                        enableShades: false,
                        shadeIntensity: 0,
                        radius: 0,
                        useFillColorAsStroke: false,
                        colorScale: {
                            ranges: [
                                {
                                    from: -1,
                                    to: -1,
                                    color: "#BDC3C7",
                                    name: "Tidak Ada Data",
                                },
                                {
                                    from: 0,
                                    to: 74.9999, // mendukung desimal`,
                                    color: "#d91010",
                                    name: "Not Perform",
                                },
                                {
                                    from: 75,
                                    to: 89.9999, // mendukung desimal
                                    color: "#F1C40F",
                                    name: "Satisfactory",
                                },
                                {
                                    from: 90,
                                    to: 99.9999, // mendukung desimal
                                    color: "#007BFF",
                                    name: "Good",
                                },
                                {
                                    from: 100,
                                    to: 9999,
                                    color: "#2ECC71",
                                    name: "Excellent",
                                },
                            ],
                        },
                    },
                },
                tooltip: {
                    enabled: true,
                    custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                        const score = series[seriesIndex][dataPointIndex];
                        const employeeName = w.globals.seriesNames[seriesIndex];
                        const month = w.globals.labels[dataPointIndex];
                        const details = this.heatmapDetails[seriesIndex];

                        if (!details) {
                            return `<div class="arrow_box p-2">Data tidak lengkap</div>`;
                        }

                        if (score === -1) {
                            return `
                                <div class="arrow_box p-2" style="background-color: #FFF; color: #000; border: 1px solid #CCC; border-radius: 5px;">
                                    <div style="font-weight: bold; margin-bottom: 5px;">${employeeName}</div>
                                    <hr style="margin: 4px 0;">
                                    <div><strong>Periode:</strong> ${month}</div>
                                    <div><strong>Status:</strong> <span style="font-weight: bold;">Tidak Ada Data</span></div>
                                </div>
                            `;
                        }

                        return `
                            <div class="arrow_box p-2" style="background-color: #FFF; color: #000; border: 1px solid #CCC; border-radius: 5px;">
                                <div style="font-weight: bold; margin-bottom: 5px;">${employeeName}</div>
                                <div><strong>Departemen:</strong> ${
                                    details.departemen || "-"
                                }</div>
                            
                                <div><strong>Jabatan:</strong> ${
                                    details.jabatan || "-"
                                }</div>
                                <hr style="margin: 4px 0;">
                                <div><strong>Achievement:</strong> <span style="font-weight: bold;">${score}</span></div>
                            </div>
                        `;
                    },
                },
                xaxis: {
                    type: "category",
                    categories: [],
                },
                stroke: {
                    width: 1,
                },
                legend: {
                    position: "bottom",
                    fontSize: "10px",
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 2,
                    },
                },
            },
            chartTitleBar: "Grafik Employee Performance",
            chartBarSeries: [],
            chartBarOptions: {
                chart: {
                    type: "bar",
                    height: 300,
                    toolbar: {
                        show: true,
                        tools: { download: true, fullscreen: true },
                    },
                    margin: { top: 30 },
                    animations: {
                        enabled: true,
                        easing: "easeout",
                        speed: 800,
                        dynamicAnimation: { enabled: true, speed: 450 },
                    },
                },
                plotOptions: {
                    bar: {
                        distributed: true,
                        horizontal: false,
                        borderRadius: 4,
                        columnWidth: "50%",
                        colors: {
                            ranges: [
                                { from: 0, to: 74, color: "#E74C3C" },
                                { from: 75, to: 89, color: "#F1C40F" },
                                { from: 90, to: 99, color: "#007BFF" },
                                { from: 100, to: 150, color: "#2ECC71" },
                            ],
                        },
                    },
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                stroke: { show: true, width: 2, colors: ["transparent"] },
                xaxis: { categories: [] },
                yaxis: { title: { text: "Achievement" } },
                fill: { opacity: 1 },
                tooltip: {
                    custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                        if (
                            !this.chartBarDetails ||
                            !this.chartBarDetails[dataPointIndex]
                        ) {
                            return "";
                        }

                        const details = this.chartBarDetails[dataPointIndex];
                        const score = series[seriesIndex][dataPointIndex];
                        return `
                            <div class="arrow_box">
                                <div style="padding: 8px;">
                                    <span><strong>${details.nama}</strong></span><br>
                                    <span>Jabatan: ${details.jabatan}</span><br>
                                    <span>Departemen: ${details.departemen}</span><br>
                                    <hr style="margin: 4px 0;">
                                    <span>Achievement: <strong>${score}</strong></span>
                                </div>
                            </div>
                        `;
                    },
                },
                annotations: {
                    yaxis: [
                        {
                            y: 100,
                            borderColor: "#546E7A",
                            strokeDashArray: 4,
                            label: {
                                borderColor: "#546E7A",
                                style: { color: "#fff", background: "#546E7A" },
                                text: "Target 100%",
                                position: "right",
                                offsetX: 5,
                            },
                        },
                    ],
                },
            },
            divisions: [],
            departments: [],
            months: [
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
                { label: "Full-Year", value: "ALL" },
            ],
            level: [],
            kpiUpdatesList: [],
            topPerformersList: [],
            underPerformersList: [],
            years,
            selectedYear: currentYear.toString(),
            selectedMonth: "ALL",
            selectedDivision: ["ALL"],
            selectedDepartment: ["ALL"],
            selectedLevel: ["ALL"],
            topPerformersTitle: "Top 3 Performers",
            selectedLevelForTop: ["ALL"],
            underPerformersTitle: "Under 3 Performers",
            selectedLevelForUnder: ["ALL"],
            chartLegends: [
                { color: "#2ECC71", text: "Excellent (≥100)" },
                { color: "#007BFF", text: "Good (90-99)" },
                { color: "#F1C40F", text: "Satisfactory (75-89)" },
                { color: "#E74C3C", text: "Not Perform (<75)" },
            ],

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
            return "Detail Employee Performance";
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
        async fetchListDivisi() {
            this.loading.divisions = true;
            try {
                const response = await axios.get(
                    "/dashboard-ceo/option/list-divisi"
                );
                if (response.status === 200 && response.data?.result) {
                    const divisiList = response.data.result.map((item) => ({
                        value: item.Id_Divisi,
                        label: `${item.Nama_Divisi}`,
                    }));

                    this.divisions = [
                        { value: "ALL", label: "All" },
                        ...divisiList,
                    ];
                }
            } catch (error) {
                console.error("Gagal mengambil data satuan:", error);
                this.divisions = [];
                this.departments = [];
            } finally {
                this.loading.divisions = false;
            }
        },
        async handleDivisionChange(currentSelection) {
            if (
                currentSelection.includes("ALL") &&
                currentSelection.length > 1
            ) {
                this.selectedDivision = ["ALL"];
                return;
            }

            this.selectedDepartment = [];
            this.departments = [];

            if (this.selectedDivision.length > 0) {
                this.isDepartmentDisabled = false;
                await this.fetchListDepartemen();

                if (this.selectedDivision.includes("ALL")) {
                    this.selectedDepartment = ["ALL"];
                }
            } else {
                this.isDepartmentDisabled = true;
            }
        },
        async fetchListDepartemen() {
            if (!this.selectedDivision || this.selectedDivision.length === 0) {
                this.departments = [];
                return;
            }

            this.loading.departments = true;
            try {
                const divisionIds = this.selectedDivision.join(",");

                const response = await axios.get(
                    `/dashboard-ceo/option/list-departemen/${divisionIds}`
                );

                let departmentList = [];
                if (response.status === 200 && response.data?.result) {
                    departmentList = response.data.result.map((item) => ({
                        value: item.ID_Sub_Divisi,
                        label: `${item.nama_sub_divisi}`,
                    }));
                }

                if (this.selectedDivision.includes("ALL")) {
                    this.departments = [
                        { value: "ALL", label: "All" },
                        ...departmentList,
                    ];
                } else {
                    this.departments = departmentList;
                }
            } catch (error) {
                console.error("Gagal mengambil data departemen:", error);
                this.departments = [];
            } finally {
                this.loading.departments = false;
            }
        },
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
                    divisi: this.selectedDivision.join(","),
                    departemen: this.selectedDepartment.join(","),
                    level: this.selectedLevel.join(","),
                    bulan: this.selectedMonth,
                    tahun: this.selectedYear,
                    levelTop: this.selectedLevelForTop.join(","),
                    levelUnder: this.selectedLevelForUnder.join(","),
                };

                if (this.statusSortDirection) {
                    params.sort_status = this.statusSortDirection;
                }
                const response = await axios.get("/api/v1/dashboard-ceo/all", {
                    params,
                });

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

                    const chartData = response.data.result.Chart_Bar;
                    if (chartData && chartData.labels && chartData.series) {
                        this.chartBarSeries = [
                            {
                                name: "Achievement",
                                data: chartData.series,
                            },
                        ];

                        this.chartBarDetails = chartData.details || [];

                        this.chartBarOptions = {
                            ...this.chartBarOptions,
                            xaxis: {
                                ...this.chartBarOptions.xaxis,

                                categories: chartData.labels,
                            },
                        };
                    }

                    const heatmapData = response.data.result.Chart_Heatmap;
                    if (
                        heatmapData &&
                        heatmapData.series &&
                        heatmapData.categories
                    ) {
                        this.heatmapSeries = heatmapData.series;
                        this.heatmapDetails = heatmapData.details;
                        this.heatmapOptions = {
                            ...this.heatmapOptions,
                            xaxis: {
                                ...this.heatmapOptions.xaxis,
                                categories: heatmapData.categories,
                            },
                        };
                    } else {
                        this.heatmapSeries = [];
                        this.heatmapDetails = [];
                        this.heatmapOptions = {
                            ...this.heatmapOptions,
                            xaxis: {
                                ...this.heatmapOptions.xaxis,
                                categories: [],
                            },
                        };
                    }
                } else {
                    this.kpiUpdatesList = [];
                    this.topPerformersList = [];
                    this.underPerformersList = [];
                    this.listDataWidget = {};
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                    this.chartBarSeries = [];
                    this.chartBarOptions.xaxis.categories = [];
                    this.heatmapSeries = [];
                    this.heatmapDetails = [];
                }
            } catch (error) {
                this.kpiUpdatesList = [];
                console.error("Gagal mengambil data list:", error);
            } finally {
                this.loading.kpiUpdatesList = false;
            }
        },
        async fetchRecentKpiOnly(page = 1) {
            this.loading.isSearchingOrSorting = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    q: this.filteredSearch,
                    divisi: this.selectedDivision.join(","),
                    departemen: this.selectedDepartment.join(","),
                    level: this.selectedLevel.join(","),
                    bulan: this.selectedMonth,
                    tahun: this.selectedYear,
                    sort_status: this.statusSortDirection,
                };

                const response = await axios.get("/api/v1/dashboard-ceo/all", {
                    params,
                });

                if (response.status === 200 && response.data?.result) {
                    const recentUpdateData =
                        response.data.result.Recent_Kpi_Update;
                    this.kpiUpdatesList = recentUpdateData.data;
                    this.pagination.page = page;
                    this.pagination.totalPage = recentUpdateData.total_page;
                    this.pagination.totalData = recentUpdateData.total_data;
                }
            } catch (error) {
                console.error(
                    "Gagal mengambil data list (search/sort):",
                    error
                );
            } finally {
                this.loading.isSearchingOrSorting = false;
            }
        },
        async fetchDetailRecentKpi(item) {
            this.loading.detailKPI = true;
            this.kpiRecentUpdateHeaders = {};
            this.kpiItemsExample = [];

            try {
                const response = await axios.get(
                    `/api/v1/dashboard-ceo/detail/${item.Id_Pengajuan}/${item.Id_Update_Performance}`
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
            this.fetchRecentKpiOnly(1);
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
        handleDepartmentChange(currentSelection) {
            if (
                currentSelection.includes("ALL") &&
                currentSelection.length > 1
            ) {
                this.selectedDepartment = ["ALL"];
            }
        },
        handleLevelChange(currentSelection) {
            if (
                currentSelection.includes("ALL") &&
                currentSelection.length > 1
            ) {
                this.selectedLevel = ["ALL"];
            }
        },
        handleLevelChangeTop(currentSelection) {
            if (
                currentSelection.includes("ALL") &&
                currentSelection.length > 1
            ) {
                this.selectedLevelForTop = ["ALL"];
            }
        },
        handleLevelChangeUnder(currentSelection) {
            if (
                currentSelection.includes("ALL") &&
                currentSelection.length > 1
            ) {
                this.selectedLevelForUnder = ["ALL"];
            }
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
    },
    async mounted() {
        await this.fetchListDivisi();
        await this.fetchListLevel();
        await this.fetchListDepartemen();
    },
    watch: {
        filteredSearch: debounce(function () {
            this.fetchRecentKpiOnly(1); // <-- GANTI ke method baru
        }, 500),
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

@keyframes shimmer {
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
    animation: shimmer 2s linear infinite;
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

.mt-iconCustomTunnel {
    margin-top: -30px;
}

.stat-value h6 {
    font-size: 0.8rem;
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

/* ... semua variabel dan style dasar Anda yang lain ... */
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
    font-size: 1.4rem;
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
