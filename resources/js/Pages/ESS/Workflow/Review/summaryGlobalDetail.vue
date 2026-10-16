<template>
    <div class="container-fluid">
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <div class="d-flex">
                    <div class="position-relative">
                        <h2 class="mb-1 fw-bold ms-3">
                            <span class="garisDepan"></span>
                            {{ title }}
                        </h2>
                        <p class="text-muted ms-3 mb-0">
                            Dashboard Review Detail
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div
                    class="d-flex justify-content-start justify-content-md-end mt-3 mt-md-0 gap-2"
                >
                    <button
                        @click="exportCustomExcel"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-printer"></i> Export
                    </button>
                    <a href="/summary-review" class="btn btn-danger">
                        <i class="bi bi-backspace"></i>

                        Back
                    </a>
                </div>
            </div>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3 mb-4">
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total User</h6>
                            <h4 class="mb-0 fw-bold">
                                {{
                                    `${processedData.length} Divisi ${division}`
                                }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon">
                            <i class="bi bi-star"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Kategori Evaluasi</h6>
                            <h4 class="mb-0 fw-bold">
                                {{ categories.length }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon">
                            <i class="bi bi-building-add"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Skor Total Divisi</h6>
                            <h4 class="mb-0 fw-bold">
                                {{ averageDivisionScore }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card summary-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="summary-icon">
                            <i class="bi bi-calendar"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Update Review</h6>
                            <h4 class="mb-0 fw-bold fs-6 fs-sm-3">
                                {{ tanggal }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-4 main-card">
            <div class="card-header">
                <div class="row align-items-center justify-content-md-between">
                    <div class="col-lg-4">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 me-2">Ringkasan Global Review</h5>
                            <span
                                class="badge bg-primary rounded-pill px-3 py-2"
                                >Periode: {{ kodePeriode }}</span
                            >
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-12 mt-3 mt-lg-0">
                        <div class="row g-2">
                            <div class="col">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search text-muted"></i>
                                    </span>
                                    <input
                                        type="text"
                                        placeholder="Cari User"
                                        class="form-control"
                                        v-model="searchTerm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- collapse hide -->
                <!-- <div
                    class="card mb-4 transitionCol"
                    :class="{ 'border-primary': showCollapse }"
                >
                    <div
                        class="card-header border d-flex justify-content-between align-items-center"
                        role="button"
                        @click="toggleCollapse"
                    >
                        <h6 class="fw-bold">{{ kodePeriode }}</h6>
                        <i
                            class="bi"
                            :class="
                                showCollapse
                                    ? 'bi-chevron-up'
                                    : 'bi-chevron-down'
                            "
                        ></i>
                    </div>
                    <div
                        class="collapse transitionCol"
                        :class="{ show: showCollapse }"
                    >
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div
                                        class="d-flex align-items-center gap-2"
                                    >
                                        <div
                                            class="p-3 rounded bg-light d-flex align-items-center justify-content-center"
                                        >
                                            <i
                                                class="bi bi-calendar text-primary"
                                            ></i>
                                        </div>
                                        <div class="">
                                            <text-muted class="small"
                                                >Periode</text-muted
                                            >
                                            <div class="fw-bold">
                                                {{ keteranganPeriode }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div
                                        class="d-flex align-items-center gap-2"
                                    >
                                        <div
                                            class="p-3 rounded bg-light d-flex align-items-center justify-content-center"
                                        >
                                            <i
                                                class="bi bi-calendar text-primary"
                                            ></i>
                                        </div>
                                        <div class="">
                                            <text-muted class="small"
                                                >Tanggal</text-muted
                                            >
                                            <div class="fw-bold">
                                                {{ tanggal }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div
                                        class="d-flex align-items-center gap-2"
                                    >
                                        <div
                                            class="p-3 rounded bg-light d-flex align-items-center justify-content-center"
                                        >
                                            <i
                                                class="bi bi-calendar text-primary"
                                            ></i>
                                        </div>
                                        <div class="">
                                            <text-muted class="small"
                                                >Divisi</text-muted
                                            >
                                            <div class="fw-bold">
                                                {{ division }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
                <div class="position-relative">
                    <!-- Loading Spinner -->
                    <div class="text-center py-5" v-if="isLoading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Memuat data...</p>
                    </div>
                    <!--  -->
                    <div
                        v-else-if="filteredData.length"
                        class="table-responsive"
                    >
                        <table
                            id="TabelEx"
                            class="table table-striped table-hover"
                        >
                            <thead class="table-light">
                                <tr>
                                    <th class="small text-center" scope="col">
                                        Nama User
                                    </th>
                                    <th
                                        scope="col"
                                        v-for="category in categories"
                                        :key="category"
                                        class="text-center small"
                                    >
                                        {{ category }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in paginatedData"
                                    :key="index"
                                >
                                    <td class="d-flex align-items-center">
                                        {{ item.user }}
                                    </td>
                                    <td
                                        v-for="category in categories"
                                        :key="category"
                                        class="text-center"
                                    >
                                        <span
                                            class="badge rounded score-badge"
                                            :class="
                                                getScoreClass(item[category])
                                            "
                                        >
                                            {{
                                                item[category] === "-"
                                                    ? "-"
                                                    : item[category]
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            class="d-flex justify-content-between align-items-center mt-3"
                        >
                            <div class="">
                                <span class="text-muted">
                                    Menampilkan {{ paginatedData.length }} dari
                                    {{ filteredData.length }} data
                                </span>
                                <nav>
                                    <ul class="pagination">
                                        <li
                                            class="page-item"
                                            :class="{
                                                disabled: currentPage === 1,
                                            }"
                                        >
                                            <a
                                                href="#"
                                                class="page-link"
                                                @click.prevent="currentPage--"
                                            >
                                                <i
                                                    class="bi bi-chevron-left"
                                                ></i>
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
                                                href="#"
                                                @click.prevent="
                                                    currentPage = page
                                                "
                                                class="page-link"
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
                                                href="#"
                                                class="page-link"
                                                @click.prevent="currentPage++"
                                            >
                                                <i
                                                    class="bi bi-chevron-right"
                                                ></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>

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
                            @click="resetFilter()"
                        >
                            <i class="bi bi-arrow-counterclockwise me-2"></i
                            >Reset Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ExcelJS from "exceljs";

export default {
    props: {
        title: String,
        no_review: String,
        dataTable: Array,
        kodePeriode: String,
        keteranganPeriode: String,
        division: String,
        ticketCount: Array,
        tanggal: String,
    },
    data() {
        return {
            searchTerm: "",
            itemPerPage: 20,
            currentPage: 1,
            showCollapse: false,
        };
    },
    computed: {
        categories() {
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
            // console.log(Array.from(categories));
            return Array.from(categories);
        },
        processedData() {
            if (!this.dataTable || !this.dataTable.length) {
                return [];
            }

            return this.dataTable.map((row) => {
                const processedRow = {
                    user: row.user,
                };

                this.categories.forEach((category) => {
                    processedRow[category] = "-";
                });

                if (Array.isArray(row.sub_category)) {
                    row.sub_category.forEach((sub) => {
                        if (sub && sub.category) {
                            let scoreValue = "-";

                            if (sub.score !== undefined && sub.score !== null) {
                                if (typeof sub.score === "string") {
                                    for (const ticket of this.ticketCount) {
                                        const parsedScore = parseFloat(
                                            sub.score
                                        );

                                        if (
                                            sub.category == "ESS/Workflow/Ticket/Ticket" &&
                                            row.id == ticket["id"]
                                        ) {
                                            const score =
                                                parsedScore.toFixed(1);
                                            scoreValue = `${ticket["score"]}/${score} Score `;
                                            break; // berhenti setelah ketemu yang cocok
                                        } else {
                                            scoreValue = isNaN(parsedScore)
                                                ? "-"
                                                : parsedScore;
                                        }
                                    }
                                } else if (typeof sub.score === "number") {
                                    this.ticketCount.forEach((ticket) => {
                                        // if (sub.category == "ESS/Workflow/Ticket/Ticket") {
                                        console.log(ticket);
                                        // }
                                    });
                                    scoreValue = sub.score;
                                }
                            }

                            processedRow[sub.category] = scoreValue;
                        }
                    });
                }
                // console.log(processedRow);
                return processedRow;
            });
        },
        filteredData() {
            if (!this.processedData.length) return [];

            return this.processedData.filter((item) => {
                const matchesSearch =
                    this.searchTerm == "" ||
                    item.user
                        .toLowerCase()
                        .includes(this.searchTerm.toLowerCase());

                return matchesSearch;
            });
        },

        totalPages() {
            return Math.ceil(this.filteredData.length / this.itemPerPage);
        },

        paginatedData() {
            const startIndex = (this.currentPage - 1) * this.itemPerPage;
            const endIndex = startIndex + this.itemPerPage;
            // console.log(this.filteredData.slice(startIndex, endIndex));
            return this.filteredData.slice(startIndex, endIndex);
        },

        averageDivisionScore() {
            let totalScore = 0;
            let count = 0;

            this.processedData.forEach((row) => {
                this.categories.forEach((category) => {
                    if (
                        row[category] !== "-" &&
                        typeof row[category] === "number"
                    ) {
                        totalScore += row["Total Score"];
                        count++;
                    }
                });
            });
            return count > 0 ? (totalScore / count).toFixed(1) : "-";
        },
    },
    methods: {
        async exportCustomExcel() {
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Data Tabel");

            // Inisialisasi alphabet untuk penentuan kolom
            const alphabet = Array.from({ length: 26 }, (_, i) =>
                String.fromCharCode(65 + i)
            );

            const totalColumns = 2 + this.categories.length; // "No", "Nama User", + kategori
            const menampung = [];
            for (let i = 0; i < totalColumns; i++) {
                menampung.push(alphabet[i]);
            }

            worksheet.mergeCells(
                `${menampung[0]}1:${menampung[menampung.length - 1]}1`
            );
            worksheet.getCell(
                `${menampung[0]}1`
            ).value = `Summary Global Review ${this.no_review}`;
            worksheet.getCell(`${menampung[0]}1`).fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };

            // Baris header kolom (baris 2)
            worksheet.addRow(["No", "Nama User", ...this.categories]);

            // Tambahkan data user
            this.dataTable.forEach((user) => {
                const row = [
                    user.id,
                    user.user,
                    ...this.categories.map((cat) => {
                        const item = user.sub_category.find(
                            (s) => s.category === cat
                        );
                        return item ? parseFloat(item.score).toFixed(1) : "";
                    }),
                ];
                worksheet.addRow(row);
            });

            // Styling semua baris
            worksheet.eachRow((row) => {
                row.eachCell((cell) => {
                    cell.alignment = {
                        horizontal: "center",
                        vertical: "middle",
                    };
                    cell.border = {
                        top: { style: "thin", color: { argb: "000000" } },
                        left: { style: "thin", color: { argb: "000000" } },
                        bottom: { style: "thin", color: { argb: "000000" } },
                        right: { style: "thin", color: { argb: "000000" } },
                    };
                });
            });

            worksheet.columns = new Array(totalColumns).fill({ width: 25 });

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], {
                type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            });

            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `Global-review-summary-${this.no_review}.xlsx`;
            link.click();
        },

        toggleCollapse() {
            this.showCollapse = !this.showCollapse;
        },
        resetFilter() {
            this.searchTerm = "";
            this.currentPage = 1;
        },
        getScoreClass(score) {
            if (typeof score === "string" && score.includes("/")) {
                const parsed = parseFloat(score.split("/")[1].split(" ")[0]);

                if (isNaN(parsed)) return "bg-secondary";

                if (parsed >= 5) return "bg-success";
                if (parsed >= 4) return "bg-primary";
                if (parsed >= 3) return "bg-warning";
                return "bg-danger";
            } else {
                if (score === "-" || typeof score !== "number")
                    return "bg-secondary";
                if (score >= 5) return "bg-success";
                if (score >= 4) return "bg-primary";
                if (score >= 3) return "bg-warning";
                return "bg-danger";
            }
        },
    },
    watch: {
        searchTerm() {
            this.currentPage = 1;
        },
    },
};
</script>

<style scoped>
.garisDepan {
    position: absolute;
    left: 0;
    top: 0.2rem;
    bottom: 0.2rem;
    width: 4px;
    background-color: #0d6efd;
    border-radius: 2px;
}
.showCollapse {
    transition: all 0.3s ease;
}
.summary-card {
    transition: all 0.3s ease;
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.summary-card:hover {
    transform: translateY(-5px);
    /* rumus Hover x3 dari induk nyo */
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.summary-icon {
    width: 3rem;
    height: 3rem;
    background-color: rgba(13, 110, 253, 0.1);
    /* rumus border radius /6 dari induk nyo */
    border-radius: 0.5rem;
    font-size: 1.5rem;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #0d6efd;
}
</style>
