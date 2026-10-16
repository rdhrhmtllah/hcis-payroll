<template>
    <div class="container-fluid page-wrapper">
        <!-- Mobile Filter Button -->
        <button
            class="btn btn-lg rounded-circle shadow-lg d-lg-none mobile-filter-trigger"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#filterOffcanvas"
            aria-controls="filterOffcanvas"
        >
            <i class="bi bi-funnel-fill iconMty"></i>
        </button>

        <!-- Mobile Filter Offcanvas -->
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

        <!-- Main Content -->
        <div class="row">
            <!-- Filter Section (Desktop) -->
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

            <!-- Widgets Section -->
            <div class="col-12 col-lg-6 order-1 order-lg-2">
                <div
                    v-if="
                        listDataWidget &&
                        listDataWidget.Performance &&
                        !loading.widgets
                    "
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

                    <!-- <div class="widget-card">
                        <div class="widget-content">
                            <div
                                class="d-flex align-items-center justify-content-between mb-2"
                            >
                                <div
                                    class="widget-icon-wrapper bg-soft-success"
                                >
                                    <i class="bi bi-bullseye text-success"></i>
                                </div>
                                <h4 class="widget-value mb-0">
                                    {{ calculateTargetScore() }}%
                                </h4>
                            </div>
                            <p class="widget-title mb-0">Target Score</p>
                        </div>
                    </div> -->
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
                    <div v-for="n in 2" :key="n" class="widget-card skeleton">
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

        <!-- Charts and KPI Section -->
        <div class="row">
            <!-- Performance Chart -->
            <div class="col-md-8 col-12 mb-4">
                <div class="card h-100">
                    <div
                        class="card-header d-flex justify-content-between align-items-center"
                    >
                        <h5 class="mb-0">My Performance</h5>
                        <div class="chart-period-badge">
                            <span class="badge bg-primary"
                                >{{ selectedMonthLabel }}
                                {{ selectedYear }}</span
                            >
                        </div>
                    </div>
                    <div class="card-body">
                        <apexchart
                            type="line"
                            height="350"
                            :options="chartLineOptions"
                            :series="chartLineSeries"
                        ></apexchart>
                    </div>
                </div>
            </div>

            <!-- Current KPI Section -->
            <div class="col-md-4 col-12 mb-4">
                <div class="card h-100">
                    <div
                        class="card-header d-flex justify-content-between align-items-center"
                    >
                        <h5 class="mb-0">Parameter KPI</h5>
                        <div class="kpi-count-badge">
                            <span class="badge bg-secondary"
                                >{{ kpiList.length }} KPIs</span
                            >
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="!loading.kpiList" class="kpi-list-container">
                            <div
                                v-for="kpi in kpiList"
                                :key="kpi.Id_Pengajuan_Detail"
                                class="kpi-item"
                            >
                                <div class="kpi-header">
                                    <h6 class="kpi-name">{{ kpi.name }}</h6>
                                    <span
                                        class="kpi-badge"
                                        :class="getStatusClass(kpi.Keterangan)"
                                    >
                                        {{ kpi.Keterangan }}
                                    </span>
                                </div>
                                <div class="kpi-progress">
                                    <div class="progress">
                                        <div
                                            class="progress-bar"
                                            :class="
                                                getProgressBarClass(kpi.score)
                                            "
                                            :style="{
                                                width: kpi.score + '%',
                                                background: kpi.Warna,
                                            }"
                                        ></div>
                                    </div>
                                    <div class="kpi-stats">
                                        <div>
                                            <div>
                                                Target :
                                                <span class="target">{{
                                                    convertValueUnit(
                                                        kpi.Total_Target,
                                                        kpi.Format_Input,
                                                        kpi.Simbol_Satuan
                                                    )
                                                }}</span>
                                            </div>
                                            <!-- <span class="separator">/</span> -->
                                            <div>
                                                Actual :
                                                <span class="actual">{{
                                                    convertValueUnit(
                                                        kpi.Total_Actual,
                                                        kpi.Format_Input,
                                                        kpi.Simbol_Satuan
                                                    )
                                                }}</span>
                                            </div>
                                        </div>

                                        <span class="achievement"
                                            >{{ kpi.score || 0 }}%</span
                                        >
                                    </div>
                                </div>
                                <div class="kpi-period">
                                    <small class="text-muted">{{
                                        formatPeriod(kpi.Periode)
                                    }}</small>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="kpi-list-container flex-column d-flex gap-2 p-2"
                        >
                            <div
                                v-for="n in 3"
                                :key="n"
                                class="kpi-item skeleton"
                            >
                                <div class="kpi-header">
                                    <div
                                        class="skeleton skeleton-text skeleton-title w-75"
                                    ></div>
                                    <div
                                        class="skeleton skeleton-text w-25"
                                    ></div>
                                </div>
                                <div class="kpi-progress">
                                    <div
                                        class="skeleton skeleton-text w-100"
                                    ></div>
                                    <div
                                        class="skeleton skeleton-text w-50 mt-1"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PICA Section -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div
                        class="card-header d-flex justify-content-between align-items-center"
                    >
                        <h5 class="mb-0">
                            PICA (Problem Identification & Corrective Action)
                        </h5>
                        <div class="pica-count-badge">
                            <span class="badge bg-info"
                                >{{ picaList.length }} Items</span
                            >
                        </div>
                    </div>
                    <div class="card-body">
                        <div v-if="!loading.picaList">
                            <div
                                v-if="picaList.length > 0"
                                class="pica-container"
                            >
                                <div
                                    v-for="pica in picaList"
                                    :key="pica.id"
                                    class="pica-item"
                                >
                                    <div class="pica-header">
                                        <div class="pica-title">
                                            <h6 class="mb-1">
                                                {{ pica.kpiName }}
                                            </h6>
                                            <small class="text-muted">{{
                                                pica.periode
                                            }}</small>
                                        </div>
                                        <span
                                            class="pica-status"

                                            style="color:white"
                                           :style="{ background: pica.warna }"
                                        >
                                            {{ pica.status }}
                                        </span>
                                    </div>
                                    <div class="pica-content">
                                        <div class="pica-section">
                                            <label class="pica-label"
                                                >Problem Identification:</label
                                            >
                                            <div class="pica-text-wrapper">
                                                <p class="pica-text">
                                                    {{
                                                        pica.identifikasi ||
                                                        "Not specified"
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pica-section">
                                            <label class="pica-label"
                                                >Corrective Action:</label
                                            >
                                            <div class="pica-text-wrapper">
                                                <p class="pica-text">
                                                    {{
                                                        pica.koreksi_aksi ||
                                                        "Not specified"
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pica-footer">
                                        <small class="text-muted">
                                            Due Date:
                                            {{ formatDate(pica.tanggal) }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-clipboard-check"></i>
                                </div>
                                <h6>No PICA Data Available</h6>
                                <p class="text-muted">
                                    There are no problem identification and
                                    corrective action records for the selected
                                    period.
                                </p>
                            </div>
                        </div>
                        <div v-else class="pica-container">
                            <div
                                v-for="n in 3"
                                :key="n"
                                class="pica-item skeleton"
                            >
                                <div class="pica-header">
                                    <div
                                        class="skeleton skeleton-text skeleton-title w-50"
                                    ></div>
                                    <div
                                        class="skeleton skeleton-text w-25"
                                    ></div>
                                </div>
                                <div class="pica-content">
                                    <div
                                        class="skeleton skeleton-text w-100 mb-2"
                                    ></div>
                                    <div
                                        class="skeleton skeleton-text w-75"
                                    ></div>
                                </div>
                                <div
                                    class="skeleton skeleton-text w-25 mt-2"
                                ></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <modalUiV2
            :show="isModalVisible"
            :title="modalTitle"
            :is-read-only="true"
            @close="closeDetailModal"
        >
            <div v-if="loading.detailKPI" class="skeleton-wrapper">
                <!-- Skeleton content remains the same -->
            </div>
            <div v-else>
                <!-- Modal content remains the same -->
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
            picaSearch: "",
            statusSortDirection: null,
            radialChartSeries: [0],
            radialChartTitle: "Overall Achievement",
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
                    categories: [],
                    labels: {
                        style: {
                            fontSize: "10px",
                            colors: "#333",
                            fontWeight: 500,
                        },
                    },
                },
                yaxis: {
                    title: { text: "Performance Score" },
                    min: 0,
                    max: 150,
                },
                legend: { show: true, fontSize: "9px", offsetY: 20 },
                tooltip: {
                    y: {
                        formatter: (val) => val + "%",
                    },
                },
                grid: {
                    borderColor: "#e7e7e7",
                    strokeDashArray: 5,
                },
                colors: ["#0d6efd"],
                annotations: {
                    yaxis: [
                        {
                            y: 100,
                            borderColor: this.rgbaToHex(this.warnaNetral),
                            label: {
                                borderColor: this.rgbaToHex(this.warnaNetral),
                                style: {
                                    color: "#fff",
                                    background: this.rgbaToHex(
                                        this.warnaNetral
                                    ),
                                },
                                text: "Target 100%",
                            },
                        },
                    ],
                },
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
            kpiList: [],
            picaList: [],
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
                widgets: false,
                kpiList: false,
                picaList: false,
            },
            pagination: {
                page: 1,
                limit: 5,
                totalPage: 0,
                totalData: 0,
            },
            picaPagination: {
                page: 1,
                limit: 5,
                totalPage: 0,
                totalData: 0,
            },
        };
    },
    props: {
        warnaNetral: { type: String, default: "rgba(46, 204, 113, 1)" },
    },
    computed: {
        modalTitle() {
            return "Detail My Performance";
        },
        selectedMonthLabel() {
            const month = this.months.find(
                (m) => m.value === this.selectedMonth
            );
            return month ? month.label : "Full-Year";
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
        visiblePicaPages() {
            const total = this.picaPagination.totalPage;
            const current = this.picaPagination.page;
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
        rgbaToHex(rgba) {
            const match = rgba.match(
                /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/
            );

            if (!match) return rgba;

            let [_, r, g, b, a] = match;
            r = parseInt(r);
            g = parseInt(g);
            b = parseInt(b);
            a = a !== undefined ? Math.round(parseFloat(a) * 255) : 255;

            const toHex = (n) => n.toString(16).padStart(2, "0");

            return `#${toHex(r)}${toHex(g)}${toHex(b)}${
                a < 255 ? toHex(a) : ""
            }`;
        },
        convertValueUnit(value, format, simbol = null) {
            if (!format || !value) return "Undefined";

            let simbolSatuan = simbol ? simbol?.trim() : "";
            // console.log(simbolSatuan);
            let decimals = 2;
            let result = "";

            if (format == "currency") {
                result = new Intl.NumberFormat("id-ID").format(value);
            } else if (format == "percentage") {
                const numericValue = parseFloat(value) || 0;
                const strValue = value.toString();

                const userInputDecimal =
                    strValue.includes(".") || strValue.includes(",");

                if (!userInputDecimal && numericValue % 1 === 0) {
                    result = `${numericValue}`;
                }

                result = `${numericValue.toFixed(decimals)}`;
            } else {
                const numericValue = parseFloat(value) || 0;
                result = new Intl.NumberFormat("id-ID", {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(numericValue);
            }

            return format == "currency"
                ? simbolSatuan + ". " + result
                : result + " " + simbolSatuan;
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
            // Set loading states for affected components
            this.loading.widgets = true;
            this.loading.kpiList = true;
            this.loading.picaList = true;
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
                    this.processApiResponse(response.data.result, page);
                } else {
                    this.resetData();
                }
            } catch (error) {
                this.kpiUpdatesList = [];
                console.error("Gagal mengambil data list:", error);
            } finally {
                // Clear loading states after data is processed
                setTimeout(() => {
                    this.loading.widgets = false;
                    this.loading.kpiList = false;
                    this.loading.picaList = false;
                    this.loading.kpiUpdatesList = false;
                }, 500);
            }
        },

        processApiResponse(result, page) {
            this.kpiUpdatesList = result.Recent_Kpi_Update.data;
            this.listDataWidget = result.Widget;
            this.topPerformersList = result.Top_3_Performers;
            this.underPerformersList = result.Under_3_Performers;

            // Process KPI list with calculated scores
            this.kpiList = (result.kpis || []).map((kpi) => ({
                ...kpi,
                isActive: kpi.Total_Target == 0 ? false : true
                // score: this.calculateKpiScore(kpi),

            }));

            this.pagination.page = page;
            this.pagination.totalPage = result.Recent_Kpi_Update.total_page;
            this.pagination.totalData = result.Recent_Kpi_Update.total_data;

            this.$nextTick(() => {
                this.selectedMonth = result.selectedMonth;
                this.selectedYear = result.selectedYear;
            });

            const selectedYearNum = parseInt(this.selectedYear, 10);
            this.years = Array.from({ length: 5 }, (_, i) => ({
                label: (selectedYearNum - 2 + i).toString(),
                value: (selectedYearNum - 2 + i).toString(),
            }));

            // Process chart data
            const chartLineData = result.Chart_Line;
            if (chartLineData && chartLineData.labels && chartLineData.series) {
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

            // Process PICA data
            this.picaList = this.processPicaData(result.Recent_Kpi_Update.data);
        },

        calculateKpiScore(kpi) {
            const actual = parseFloat(kpi.Total_Actual || 0);
            const target = parseFloat(kpi.Total_Target || 1);
            return Math.round((actual / target) * 100);
        },

        formatDate(dateString) {
            if (!dateString) return "N/A";
            const options = {
                year: "numeric",
                month: "long",
                day: "numeric",
            };
            return new Date(dateString).toLocaleDateString("id-ID", options);
        },

        resetData() {
            this.kpiUpdatesList = [];
            this.listDataWidget = {};
            this.pagination.totalData = 0;
            this.pagination.totalPage = 0;
            this.kpiList = [];
            this.picaList = [];
        },

        processPicaData(recentUpdates) {
            return recentUpdates.map((update) => ({
                id: update.id,
                kpiName: update.name,
                identifikasi: update.identifikasi,
                koreksi_aksi: update.koreksi_aksi,
                tanggal: update.Tanggal_Pelaksanaan,
                periode: update.periode,
                status: update.Keterangan,
                warna: update.Warna
            }));
        },

        getPicaStatus(identifikasi, koreksi) {
            if (identifikasi && koreksi) return "Completed";
            if (identifikasi && !koreksi) return "Needs Action";
            return "Pending";
        },

        calculateTargetScore() {
            if (!this.kpiList.length) return 0;

            const totalTarget = this.kpiList.reduce((sum, kpi) => {
                return sum + parseFloat(kpi.Total_Target || 0);
            }, 0);

            const totalActual = this.kpiList.reduce((sum, kpi) => {
                return sum + parseFloat(kpi.Total_Actual || 0);
            }, 0);

            return totalTarget > 0
                ? Math.round((totalActual / totalTarget) * 100)
                : 0;
        },

        getStatusClass(status) {
            switch (status?.toLowerCase()) {
                case "excellent":
                    return "status-excellent";
                case "good":
                    return "status-good";
                case "satisfactory":
                    return "status-satisfactory";
                default:
                    return "status-default";
            }
        },

        getProgressBarClass(achievement) {
            if (achievement >= 95) return "progress-excellent";
            if (achievement >= 85) return "progress-good";
            if (achievement >= 75) return "progress-satisfactory";
            return "progress-poor";
        },

        getPicaStatusClass(status) {
            switch (status?.toLowerCase()) {
                case "completed":
                    return "pica-completed";
                case "needs action":
                    return "pica-pending";
                default:
                    return "pica-default";
            }
        },

        formatPeriod(period) {
            if (!period) return "";
            const [year, month] = period.split("-");
            const monthNames = [
                "Jan",
                "Feb",
                "Mar",
                "Apr",
                "May",
                "Jun",
                "Jul",
                "Aug",
                "Sep",
                "Oct",
                "Nov",
                "Dec",
            ];
            return `${monthNames[parseInt(month) - 1]} ${year}`;
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
                this.fetchListData(this.pagination.page + 1);
            }
        },

        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchListData(this.pagination.page - 1);
            }
        },

        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchListData(page);
            }
        },

        nextPicaPage() {
            if (this.picaPagination.page < this.picaPagination.totalPage) {
                this.picaPagination.page++;
                this.fetchPicaData();
            }
        },

        prevPicaPage() {
            if (this.picaPagination.page > 1) {
                this.picaPagination.page--;
                this.fetchPicaData();
            }
        },

        changePicaPage(page) {
            if (page !== this.picaPagination.page) {
                this.picaPagination.page = page;
                this.fetchPicaData();
            }
        },

        async fetchPicaData() {
            this.loading.picaList = true;
            try {
                // Your PICA API call here
            } catch (error) {
                console.error("Failed to fetch PICA data:", error);
            } finally {
                this.loading.picaList = false;
            }
        },

        openDetailModal(item) {
            this.fetchDetailRecentKpi(item);
            this.isModalVisible = true;
        },

        openPicaDetail(pica) {
            console.log("Opening PICA detail:", pica);
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
/* Your existing CSS styles with additional KPI styles */
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

/* KPI List Styles */
.kpi-list-container {
    max-height: 400px;
    overflow-y: auto;
}

.kpi-item {
    padding: 1rem;
    border-bottom: 1px solid #f1f3f4;
    transition: background-color 0.2s ease;
}

.kpi-item:hover {
    background-color: #f8f9fa;
}

.kpi-item:last-child {
    border-bottom: none;
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.kpi-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
    flex: 1;
    padding-right: 0.5rem;
}

.kpi-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-excellent {
    background-color: #d1fae5;
    color: #065f46;
}

.status-good {
    background-color: #dbeafe;
    color: #1e40af;
}

.status-satisfactory {
    background-color: #fef3c7;
    color: #92400e;
}

.status-default {
    background-color: #f3f4f6;
    color: #6b7280;
}

.kpi-progress {
    margin-bottom: 0.5rem;
}

.progress {
    height: 6px;
    background-color: #f1f3f4;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.progress-bar {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-excellent {
    background-color: #10b981;
}

.progress-good {
    background-color: #3b82f6;
}

.progress-satisfactory {
    background-color: #f59e0b;
}

.progress-poor {
    background-color: #ef4444;
}

.kpi-stats {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
}

.kpi-stats .actual {
    font-weight: 600;
    color: #374151;
}

.kpi-stats .target {
    color: #6b7280;
}

.kpi-stats .separator {
    color: #9ca3af;
}

.kpi-stats .achievement {
    margin-left: auto;
    font-weight: 600;
    color: #374151;
}

.kpi-period {
    font-size: 0.75rem;
}

/* Improved PICA Styles */
.pica-container {
    display: grid;
    gap: 1.25rem;
}

.pica-item {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    background: white;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.pica-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.pica-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f3f4;
}

.pica-title h6 {
    margin: 0;
    color: #374151;
    font-weight: 600;
    font-size: 1rem;
}

.pica-title small {
    font-size: 0.8rem;
    color: #6b7280;
}

.pica-status {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pica-completed {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.pica-pending {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.pica-default {
    background-color: #f3f4f6;
    color: #6b7280;
    border: 1px solid #e5e7eb;
}

.pica-content {
    display: grid;
    gap: 1.25rem;
}

.pica-section {
    margin-bottom: 0;
}

.pica-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    display: block;
}

.pica-text-wrapper {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid #e5e7eb;
    transition: all 0.3s ease;
}

.pica-item:hover .pica-text-wrapper {
    border-left-color: #3b82f6;
    background: #f0f9ff;
}

.pica-text {
    font-size: 0.875rem;
    color: #4b5563;
    margin: 0;
    line-height: 1.5;
    word-wrap: break-word;
}

.pica-footer {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f3f4;
    text-align: right;
}

.pica-footer small {
    font-size: 0.8rem;
    color: #9ca3af;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-icon {
    font-size: 3rem;
    color: #d1d5db;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h6 {
    color: #374151;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    max-width: 400px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Loading States */
.kpi-item.skeleton,
.pica-item.skeleton {
    padding: 1.5rem;
    border-radius: 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
}

/* Your existing responsive styles remain the same */
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
    /* background-image: linear-gradient(
        to right,
        #e0e0e0 0%,
        #f0f0f0 20%,
        #e0e0e0 40%,
        #e0e0e0 100%
    ); */
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
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;

    background: white !important;
}
.filter-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);

    /* box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); */
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

.page-wrapper.container-fluid {
    overflow-x: hidden;
}

.page-wrapper > .row > [class*="col-"] {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.widget-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.widget-card {
    flex: 1 1 calc(33.333% - 11px);
    min-width: 180px;
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

    .pica-header {
        flex-direction: column;
        gap: 0.75rem;
        align-items: flex-start;
    }

    .pica-status {
        align-self: flex-start;
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

    .kpi-header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .pica-item {
        padding: 1rem;
    }

    .pica-content {
        gap: 1rem;
    }

    .pica-text-wrapper {
        padding: 0.75rem;
    }
}
</style>
