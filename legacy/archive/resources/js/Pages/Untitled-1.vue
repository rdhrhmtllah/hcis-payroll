<template>
    <div
        class="container-fluid dashboard-container"
        :class="{ 'dark-mode': darkMode }"
    >
        <!-- Header Section with Bootstrap components -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="position-relative">
                        <h2 class="mb-1 fw-bold ms-3">
                            <span
                                class="title-indicator"
                                :class="{ pulse: isLoading }"
                            ></span>
                            {{ title || "Laporan Global Review" }}
                        </h2>
                        <p class="text-muted ms-3 mb-0">Dashboard Analytics</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div
                    class="d-flex justify-content-md-end justify-content-start mt-3 mt-md-0 gap-2"
                >
                    <button
                        class="btn btn-light btn-icon"
                        @click="toggleDarkMode"
                        :title="darkMode ? 'Mode Terang' : 'Mode Gelap'"
                    >
                        <i :class="darkMode ? 'bi bi-sun' : 'bi bi-moon'"></i>
                    </button>
                    <button
                        class="btn btn-light btn-icon"
                        @click="exportToCSV"
                        title="Unduh CSV"
                    >
                        <i class="bi bi-file-earmark-arrow-down"></i>
                    </button>
                    <button
                        class="btn btn-outline-secondary"
                        @click="printReport"
                    >
                        <i class="bi bi-printer me-2"></i> Cetak
                    </button>
                    <button
                        class="btn btn-primary"
                        @click="saveReport"
                        :disabled="isLoading"
                    >
                        <span
                            v-if="isLoading"
                            class="spinner-border spinner-border-sm me-2"
                            role="status"
                            aria-hidden="true"
                        ></span>
                        <i v-else class="bi bi-save me-2"></i>
                        {{ isLoading ? "Menyimpan..." : "Simpan" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards using Bootstrap cards -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3 mb-4">
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Pengguna</h6>
                            <h3 class="mb-0 fw-bold">
                                {{ processedData.length }}
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon blue">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Kategori</h6>
                            <h3 class="mb-0 fw-bold">
                                {{ uniqueCategories.length }}
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon green">
                            <i class="bi bi-star"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Skor Rata-rata</h6>
                            <h3 class="mb-0 fw-bold">{{ averageScore }}</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon purple">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Update Terakhir</h6>
                            <h3 class="mb-0 fw-bold">{{ formattedDate }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Report Card -->
        <div class="card mb-4 main-card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-lg-4">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 me-3">Ringkasan Global Review</h5>
                            <span
                                class="badge bg-primary rounded-pill px-3 py-2"
                            >
                                Periode: {{ periodMonth || "Mei" }}
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-8 mt-3 mt-lg-0">
                        <div class="row g-2">
                            <div class="col">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search text-muted"></i>
                                    </span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Cari pengguna..."
                                        v-model="searchTerm"
                                    />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select
                                    class="form-select"
                                    v-model="selectedCategory"
                                >
                                    <option value="">Semua Kategori</option>
                                    <option
                                        v-for="category in uniqueCategories"
                                        :key="category"
                                        :value="category"
                                    >
                                        {{ category }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Collapsible Report Info -->
                <div
                    class="card mb-4"
                    :class="{ 'border-primary': showReportInfo }"
                >
                    <div
                        class="card-header d-flex justify-content-between align-items-center"
                        role="button"
                        @click="toggleReportInfo"
                    >
                        <h6 class="mb-0 fw-bold">
                            {{ kodePeriode || "Informasi Periode" }}
                        </h6>
                        <i
                            class="bi"
                            :class="
                                showReportInfo
                                    ? 'bi-chevron-up'
                                    : 'bi-chevron-down'
                            "
                        ></i>
                    </div>

                    <div class="collapse" :class="{ show: showReportInfo }">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="d-flex align-items-center">
                                        <div
                                            class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center"
                                        >
                                            <i
                                                class="bi bi-calendar text-primary"
                                            ></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="text-muted small">
                                                Periode
                                            </div>
                                            <div class="fw-bold">
                                                {{ keteranganPeriode || "-" }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="d-flex align-items-center">
                                        <div
                                            class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center"
                                        >
                                            <i
                                                class="bi bi-clock text-primary"
                                            ></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="text-muted small">
                                                Tanggal
                                            </div>
                                            <div class="fw-bold">
                                                {{
                                                    tanggal ||
                                                    "Tidak ada tanggal"
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div
                                            class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center"
                                        >
                                            <i
                                                class="bi bi-building text-primary"
                                            ></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="text-muted small">
                                                Divisi
                                            </div>
                                            <div class="fw-bold">
                                                {{ division || "-" }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="position-relative">
                    <!-- Loading Spinner -->
                    <div class="text-center py-5" v-if="isLoading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Memuat data...</p>
                    </div>

                    <!-- Data Table -->
                    <div
                        v-else-if="filteredData.length"
                        class="table-responsive"
                    >
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Nama Users</th>
                                    <th
                                        scope="col"
                                        v-for="category in uniqueCategories"
                                        :key="category"
                                        class="text-center"
                                    >
                                        {{ category }}
                                    </th>
                                    <th scope="col" class="text-center">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in paginatedData"
                                    :key="index"
                                >
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar">
                                                {{ getInitials(item.user) }}
                                            </div>
                                            <span class="ms-2">{{
                                                item.user
                                            }}</span>
                                        </div>
                                    </td>
                                    <td
                                        v-for="category in uniqueCategories"
                                        :key="category"
                                        class="text-center"
                                    >
                                        <span
                                            class="badge rounded-pill score-badge"
                                            :class="
                                                getScoreClass(item[category])
                                            "
                                        >
                                            {{
                                                item[category] === "-"
                                                    ? "-"
                                                    : item[category].toFixed(1)
                                            }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Detail"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Bootstrap Pagination -->
                        <div
                            class="d-flex justify-content-between align-items-center mt-3"
                        >
                            <div>
                                <span class="text-muted">
                                    Menampilkan {{ paginatedData.length }} dari
                                    {{ filteredData.length }} data
                                </span>
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <li
                                        class="page-item"
                                        :class="{ disabled: currentPage === 1 }"
                                    >
                                        <a
                                            class="page-link"
                                            href="#"
                                            @click.prevent="currentPage--"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <li
                                        v-for="page in totalPages"
                                        :key="page"
                                        class="page-item"
                                        :class="{
                                            active: currentPage === page,
                                        }"
                                    >
                                        <a
                                            class="page-link"
                                            href="#"
                                            @click.prevent="currentPage = page"
                                            >{{ page }}</a
                                        >
                                    </li>
                                    <li
                                        class="page-item"
                                        :class="{
                                            disabled:
                                                currentPage === totalPages,
                                        }"
                                    >
                                        <a
                                            class="page-link"
                                            href="#"
                                            @click.prevent="currentPage++"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="text-center py-5 border rounded bg-light"
                    >
                        <i
                            class="bi bi-exclamation-circle display-1 text-muted"
                        ></i>
                        <h5 class="mt-3">Tidak ada data tersedia</h5>
                        <p class="text-muted">
                            Tidak ditemukan data yang sesuai dengan filter Anda
                        </p>
                        <button
                            class="btn btn-outline-secondary"
                            @click="resetFilters"
                        >
                            <i class="bi bi-arrow-counterclockwise me-2"></i
                            >Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-footer text-muted text-center">
                <small
                    >&copy; {{ new Date().getFullYear() }} Global Review
                    System</small
                >
            </div>
        </div>

        <!-- Bootstrap Toast Notifications -->
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div
                class="toast align-items-center text-white border-0"
                role="alert"
                aria-live="assertive"
                aria-atomic="true"
                :class="{ show: showToast }"
                data-bs-delay="3000"
            >
                <div class="d-flex">
                    <div class="toast-body">
                        <i :class="toastIcon" class="bi me-2"></i>
                        {{ toastMessage }}
                    </div>
                    <button
                        type="button"
                        class="btn-close btn-close-white me-2 m-auto"
                        @click="closeToast"
                        aria-label="Close"
                    ></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "GlobalReviewReport",
    props: {
        title: {
            type: String,
            default: "Laporan Global Review",
        },
        kodePeriode: {
            type: String,
            default: "",
        },
        keteranganPeriode: {
            type: String,
            default: "",
        },
        division: {
            type: String,
            default: "",
        },
        tanggal: {
            type: String,
            default: "",
        },
        periodMonth: {
            type: String,
            default: "Mei",
        },
        dataTable: {
            type: Array,
            required: true,
            default: () => [],
        },
    },
    data() {
        return {
            isLoading: false,
            darkMode: false,
            searchTerm: "",
            selectedCategory: "",
            showReportInfo: true,
            showToast: false,
            toastMessage: "",
            toastType: "success",
            toastTimeout: null,
            currentPage: 1,
            itemsPerPage: 10,
        };
    },
    computed: {
        uniqueCategories() {
            const categories = new Set();

            if (!this.dataTable || !this.dataTable.length) {
                return [];
            }

            this.dataTable.forEach((row) => {
                if (Array.isArray(row.sub_category)) {
                    row.sub_category.forEach((sub) => {
                        if (sub && sub.category) {
                            categories.add(sub.category);
                        }
                    });
                }
            });

            return Array.from(categories);
        },

        processedData() {
            if (!this.dataTable || !this.dataTable.length) {
                return [];
            }

            return this.dataTable.map((row) => {
                // Start with user property
                const processedRow = {
                    user: row.user || "Tidak diketahui",
                };

                // Set default value for all categories
                this.uniqueCategories.forEach((category) => {
                    processedRow[category] = "-";
                });

                // Fill in values from sub_categories
                if (Array.isArray(row.sub_category)) {
                    row.sub_category.forEach((sub) => {
                        if (sub && sub.category) {
                            // Process the score value
                            let scoreValue = "-";

                            if (sub.score !== undefined && sub.score !== null) {
                                // Try to convert to float if it's a string
                                if (typeof sub.score === "string") {
                                    const parsedScore = parseFloat(sub.score);
                                    scoreValue = isNaN(parsedScore)
                                        ? "-"
                                        : parsedScore;
                                } else if (typeof sub.score === "number") {
                                    scoreValue = sub.score;
                                }
                            }

                            processedRow[sub.category] = scoreValue;
                        }
                    });
                }
                return processedRow;
            });
        },

        filteredData() {
            if (!this.processedData.length) return [];

            return this.processedData.filter((item) => {
                // Apply search filter
                const matchesSearch =
                    this.searchTerm === "" ||
                    item.user
                        .toLowerCase()
                        .includes(this.searchTerm.toLowerCase());

                // Apply category filter
                const matchesCategory =
                    this.selectedCategory === "" ||
                    (item[this.selectedCategory] !== "-" &&
                        item[this.selectedCategory] !== undefined);

                return matchesSearch && matchesCategory;
            });
        },

        totalPages() {
            return Math.ceil(this.filteredData.length / this.itemsPerPage);
        },

        paginatedData() {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage;
            const endIndex = startIndex + this.itemsPerPage;
            return this.filteredData.slice(startIndex, endIndex);
        },

        averageScore() {
            let totalScore = 0;
            let count = 0;

            this.processedData.forEach((row) => {
                this.uniqueCategories.forEach((category) => {
                    if (
                        row[category] !== "-" &&
                        typeof row[category] === "number"
                    ) {
                        totalScore += row[category];
                        count++;
                    }
                });
            });

            return count > 0 ? (totalScore / count).toFixed(1) : "-";
        },

        formattedDate() {
            if (this.tanggal) {
                return this.tanggal;
            }
            const now = new Date();
            return `${now.getDate()}/${
                now.getMonth() + 1
            }/${now.getFullYear()}`;
        },

        toastIcon() {
            switch (this.toastType) {
                case "success":
                    return "bi-check-circle-fill";
                case "danger":
                    return "bi-exclamation-circle-fill";
                case "warning":
                    return "bi-exclamation-triangle-fill";
                default:
                    return "bi-info-circle-fill";
            }
        },

        toastBgClass() {
            return `bg-${this.toastType}`;
        },
    },
    methods: {
        printReport() {
            window.print();
            this.showNotification("Mencetak laporan...", "info");
        },

        saveReport() {
            this.isLoading = true;

            // Simulate saving process
            setTimeout(() => {
                this.isLoading = false;
                this.$emit("report-saved", true);
                this.showNotification("Laporan berhasil disimpan!", "success");
            }, 1500);
        },

        exportToCSV() {
            this.showNotification("Mengunduh file CSV...", "info");

            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";

            // Add headers
            let headers = ["Nama Users"];
            this.uniqueCategories.forEach((category) => headers.push(category));
            csvContent += headers.join(",") + "\n";

            // Add data rows
            this.processedData.forEach((row) => {
                let dataRow = [row.user];
                this.uniqueCategories.forEach((category) => {
                    dataRow.push(row[category]);
                });
                csvContent += dataRow.join(",") + "\n";
            });

            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute(
                "download",
                `global_review_${this.periodMonth}.csv`
            );
            document.body.appendChild(link);

            // Trigger download and cleanup
            link.click();
            document.body.removeChild(link);

            setTimeout(() => {
                this.showNotification("File CSV berhasil diunduh!", "success");
            }, 1000);
        },

        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            document.body.classList.toggle("dark-mode", this.darkMode);
            this.showNotification(
                `Mode ${this.darkMode ? "Gelap" : "Terang"} diaktifkan`,
                "info"
            );
        },

        toggleReportInfo() {
            this.showReportInfo = !this.showReportInfo;
        },

        resetFilters() {
            this.searchTerm = "";
            this.selectedCategory = "";
            this.currentPage = 1;
            this.showNotification("Filter direset", "info");
        },

        getInitials(name) {
            if (!name || name === "Tidak diketahui") return "?";
            return name
                .split(" ")
                .map((word) => word[0])
                .join("")
                .substring(0, 2)
                .toUpperCase();
        },

        getScoreClass(score) {
            if (score === "-" || typeof score !== "number")
                return "bg-secondary";
            if (score >= 8) return "bg-success";
            if (score >= 6) return "bg-primary";
            if (score >= 4) return "bg-warning";
            return "bg-danger";
        },

        showNotification(message, type = "success") {
            // Clear existing timeout if any
            if (this.toastTimeout) {
                clearTimeout(this.toastTimeout);
            }

            this.toastMessage = message;
            this.toastType = type;
            this.showToast = true;

            // Auto-close after 3 seconds
            this.toastTimeout = setTimeout(() => {
                this.closeToast();
            }, 3000);
        },

        closeToast() {
            this.showToast = false;
        },
    },
    watch: {
        searchTerm() {
            this.currentPage = 1;
        },
        selectedCategory() {
            this.currentPage = 1;
        },
    },
};
</script>

<style scoped>
/* Base styles for light/dark mode */
.dashboard-container {
    padding: 1.5rem 1rem;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}


/* Title indicator styling */
.title-indicator {
    position: absolute;
    left: 0;
    top: 0.2rem;
    bottom: 0.2rem;
    width: 4px;
    background-color: #0d6efd;
    border-radius: 2px;
}

.title-indicator.pulse {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        opacity: 1;
    }
}

/* Custom card styling */
.main-card {
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.summary-card {
    transition: all 0.3s ease;
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

/* Summary icons */
.summary-icon {
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    border-radius: 0.5rem;
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}

.summary-icon.blue {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}

.summary-icon.green {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
}

.summary-icon.purple {
    background-color: rgba(111, 66, 193, 0.1);
    color: #6f42c1;
}

/* Button icon styling */
.btn-icon {
    width: 38px;
    height: 38px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* User avatar in table */
.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: #0d6efd;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Score badge styling */
.score-badge {
    min-width: 40px;
    font-weight: 500;
}

/* Fix for table responsive on mobile */
@media (max-width: 768px) {
    .table-responsive {
        border: 0;
    }
}

/* Print styles */
@media print {
    .dashboard-container {
        padding: 0;
        background-color: white !important;
    }

    .no-print {
        display: none !important;
    }

    .main-card {
        box-shadow: none;
        border: none;
    }

    .card {
        break-inside: avoid;
    }
}
</style>
