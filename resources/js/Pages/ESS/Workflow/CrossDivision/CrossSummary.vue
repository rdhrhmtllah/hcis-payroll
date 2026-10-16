<template>
    <div>
        <div class="section">
            <div class="row" id="basic-table">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between">
                                <h4 class="card-title">
                                    {{ title }}
                                </h4>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="text-center">
                                    <h4>{{ dataPeriode[0].judulPeriode }}</h4>
                                    <p>{{ tanggal }}</p>
                                </div>
                                <div class="assessment-container">
                                    <div
                                        v-for="(item, index) in dataSummary"
                                        :key="index"
                                        class="assessment-section mb-4"
                                    >
                                        <div
                                            class="section-header d-flex justify-content-between align-items-center bg-light p-3 rounded"
                                            @click="toggleSection(index)"
                                        >
                                            <h5 class="mb-0">
                                                {{ item.category }}
                                            </h5>
                                            <i
                                                class="bi bi-chevron-down transition-transform"
                                                :style="{
                                                    transform: sectionStates[
                                                        index
                                                    ]
                                                        ? 'rotate(180deg)'
                                                        : 'rotate(0deg)',
                                                }"
                                            ></i>
                                        </div>
                                        <div
                                            v-show="sectionStates[index]"
                                            class="collapse show"
                                        >
                                            <div class="table-container mt-3">
                                                <div
                                                    class="table-wrapper"
                                                    ref="tableWrapper"
                                                >
                                                    <table class="table">
                                                        <thead>
                                                            <tr
                                                                class="text-center"
                                                            >
                                                                <th>
                                                                    Name User
                                                                </th>

                                                                <th>
                                                                    Cross Review
                                                                    (Score)
                                                                </th>
                                                                <th>
                                                                    Description
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                class="text-center"
                                                                v-for="(
                                                                    user1,
                                                                    uIndex
                                                                ) in item.sub_category"
                                                                :key="uIndex"
                                                            >
                                                                <td>
                                                                    {{
                                                                        user1.Name
                                                                    }}
                                                                </td>

                                                                <td>
                                                                    <span
                                                                        class="avg-score-badge"
                                                                        :class="
                                                                            getScoreClass(
                                                                                user1.AVGScore
                                                                            )
                                                                        "
                                                                        >{{
                                                                            user1.AVGScore
                                                                        }}</span
                                                                    >
                                                                </td>
                                                                <td>
                                                                    <span
                                                                        class=""
                                                                        :class="
                                                                            getScoreClass(
                                                                                user1.AVGScore
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            getStatusScore(
                                                                                user1.AVGScore
                                                                            )
                                                                        }}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button
                            @click="exportCustomExcel"
                            class="btn btn-success"
                        >
                            Export to Excel
                        </button>
                        <a href="/summary-review" class="btn btn-danger"
                            >Back</a
                        >
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
        data: Array,
        dataSummary: Array,
        dataPeriode: Array,
        tanggal: String,
    },
    data() {
        return {
            form: {
                tanggal_mulai: "",
                tanggal_akhir: "",
                ratings: {},
                criteria_ids: "",
                user_ids: "",
            },
            sectionStates: {},
            completedRatings: 0,
            totalRatings: 0,
            isFormComplete: false,
            isDateValid: true,
        };
    },
    computed: {
        completionPercentage() {
            return this.totalRatings === 0
                ? 0
                : Math.round((this.completedRatings / this.totalRatings) * 100);
        },
    },
    created() {
        // Inisialisasi sectionStates dengan objek yang sudah terisi
        const initialSectionStates = {};
        this.data.forEach((_, index) => {
            initialSectionStates[index] = true;
        });
        this.sectionStates = initialSectionStates;
    },
    mounted() {
        // Hitung total ratings

        // Set up scroll synchronization untuk kolom tetap
        if (this.$refs.tableWrapper) {
            // Jika tableWrapper adalah array
            if (Array.isArray(this.$refs.tableWrapper)) {
                this.$refs.tableWrapper.forEach((wrapper) => {
                    wrapper.addEventListener("scroll", this.handleTableScroll);
                });
            }
            // Jika tableWrapper adalah elemen tunggal
            else if (this.$refs.tableWrapper) {
                this.$refs.tableWrapper.addEventListener(
                    "scroll",
                    this.handleTableScroll
                );
            }
        }
    },
    beforeDestroy() {
        // Bersihkan event listeners
        if (this.$refs.tableWrapper) {
            // Jika tableWrapper adalah array
            if (Array.isArray(this.$refs.tableWrapper)) {
                this.$refs.tableWrapper.forEach((wrapper) => {
                    wrapper.removeEventListener(
                        "scroll",
                        this.handleTableScroll
                    );
                });
            }
            // Jika tableWrapper adalah elemen tunggal
            else if (this.$refs.tableWrapper) {
                this.$refs.tableWrapper.removeEventListener(
                    "scroll",
                    this.handleTableScroll
                );
            }
        }
    },
    methods: {
        async exportCustomExcel() {
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Summary");

            worksheet.mergeCells(`A1:M1`);
            worksheet.getCell("A1").value = this.dataPeriode[0].division;
            worksheet.mergeCells(`A2:M2`);
            worksheet.getCell("A2").value = this.dataPeriode[0].judulPeriode;
            worksheet.mergeCells(`A3:M3`);
            worksheet.getCell("A3").value = this.tanggal;

            // Baris 1 Header Kategori Besar
            worksheet.mergeCells("A4:A5"); // <-- merge User Name
            worksheet.getCell("A4").value = "User Name";
            worksheet.getCell("A4").alignment = {
                vertical: "middle",
                horizontal: "center",
            };

            worksheet.mergeCells("B4:C4");
            worksheet.getCell("B4").value = "Kolaborasi & Komunikasi";
            worksheet.getCell("B4").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };

            worksheet.mergeCells("D4:E4");
            worksheet.getCell("D4").value = "Kualitas Output";
            worksheet.getCell("D4").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };
            worksheet.mergeCells("F4:G4");
            worksheet.getCell("F4").value =
                "Pemahaman Terhadap Proses Divisi Lain";
            worksheet.getCell("F4").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };
            worksheet.mergeCells("H4:I4");
            worksheet.getCell("H4").value = "Proses & Kualitas Kerja";
            worksheet.getCell("H4").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };
            worksheet.mergeCells("J4:K4");
            worksheet.getCell("J4").value = "Responsivitas";
            worksheet.getCell("J4").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };
            worksheet.mergeCells("L4:M4");
            worksheet.getCell("L4").value = "Sikap Profesional & Supportif";
            worksheet.getCell("L4").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFF9C4" },
            };

            // Baris 2 Sub Header
            worksheet.getCell("B5").value = "Score";
            worksheet.getCell("C5").value = "Description";
            worksheet.getCell("D5").value = "Score";
            worksheet.getCell("E5").value = "Description";
            worksheet.getCell("F5").value = "Score";
            worksheet.getCell("G5").value = "Description";
            worksheet.getCell("H5").value = "Score";
            worksheet.getCell("I5").value = "Description";
            worksheet.getCell("J5").value = "Score";
            worksheet.getCell("K5").value = "Description";
            worksheet.getCell("L5").value = "Score";
            worksheet.getCell("M5").value = "Description";

            let currentRow = 6; // data mulai dari row ke-6

            // Mapping user
            const userMap = {};

            this.dataSummary.forEach((categoryObj) => {
                const categoryName = categoryObj.category;
                categoryObj.sub_category.forEach((userObj) => {
                    const userName = userObj.Name;
                    if (!userMap[userName]) {
                        userMap[userName] = { Name: userName };
                    }
                    userMap[userName][categoryName] = parseFloat(
                        userObj.AVGScore
                    );
                });
            });

            // Masukkan Data
            Object.values(userMap).forEach((user) => {
                worksheet.addRow([
                    user.Name,
                    user["Kolaborasi & Komunikasi"] || "",
                    this.getStatusScore(user["Kolaborasi & Komunikasi"]) || "",
                    user["Kualitas Output"] || "",
                    this.getStatusScore(user["Kualitas Output"]) || "",
                    user["Pemahaman Terhadap Proses Divisi Lain"] || "",
                    this.getStatusScore(
                        user["Pemahaman Terhadap Proses Divisi Lain"]
                    ) || "",
                    user["Proses & Kualitas Kerja"] || "",
                    this.getStatusScore(user["Proses & Kualitas Kerja"]) || "",
                    user["Resposivitas"] || "",
                    this.getStatusScore(user["Resposivitas"]) || "",
                    user["Sikap Profesional & Supportif"] || "",
                    this.getStatusScore(
                        user["Sikap Profesional & Supportif"]
                    ) || "",
                ]);
                currentRow++;
            });

            // Styling
            worksheet.columns = new Array(13).fill({ width: 20 });

            worksheet.eachRow((row, rowIndex) => {
                row.eachCell((cell) => {
                    cell.alignment = {
                        horizontal: "center",
                        vertical: "middle",
                    };
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };

                    // Header lebih tebal
                    if (rowIndex <= 5) {
                        cell.font = { bold: true };
                    }
                });
            });

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], {
                type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            });

            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `cross-review-${this.dataPeriode[0].judulPeriode}-${this.dataPeriode[0].division}.xlsx`;
            link.click();
        },
        validateDates() {
            if (!this.form.tanggal_mulai || !this.form.tanggal_akhir)
                return false;

            const today = new Date().toISOString().split("T")[0];
            return this.form.tanggal_mulai >= today;
        },
        toggleSection(index) {
            // Gunakan cara langsung untuk memperbarui sectionStates
            this.sectionStates[index] = !this.sectionStates[index];
            // Memaksa Vue untuk merender ulang
            this.sectionStates = { ...this.sectionStates };
        },
        handleTableScroll(e) {
            const scrollTop = e.target.scrollTop;
            const fixedCells = e.target
                .closest(".table-container")
                .querySelectorAll(".fixed-column");

            fixedCells.forEach((cell) => {
                cell.style.transform = `translateY(-${scrollTop}px)`;
            });
        },

        updateCompletion(userId, criteriaId) {
            const key = `${userId}_${criteriaId}`;
            const value = this.form.ratings[key];

            // Hitung ulang dari awal
            let count = 0;
            this.data.forEach((category) => {
                category.sub_category.forEach((criteria) => {
                    this.user.forEach((user) => {
                        const k = `${user.Id_Users}_${criteria.id}`;
                        if (this.form.ratings[k]) count++;
                    });
                });
            });

            this.completedRatings = count;
        },
        getScoreClass(score) {
            if (score >= 4.5) return "excellent";
            if (score >= 4.0) return "good";
            if (score >= 3.0) return "average";
            return "poor";
        },
        getStatusScore(score) {
            if (score == 5) return "Sangat Baik";
            if (score < 5 && score >= 4) return "Baik";
            if (score < 4 && score >= 3) return "Cukup";
            if (score < 3 && score >= 2) return "Buruk";
            if (score < 2) return "Sangat Buruk";
            return "Error, Nilai Diluar Jangkauan";
        },
    },
};
</script>

<style scoped>
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 0.25rem;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ddd;
    transition: all 0.2s ease;
}

.rating label:hover,
.rating label:hover ~ label,
.rating input:checked ~ label {
    color: #ffd700;
    transform: scale(1.1);
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
}

.card {
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    background: linear-gradient(to bottom, var(--hcis-white), #fafbfc);
    transition: all 0.3s ease;
}

.card-header {
    background: transparent;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.5rem 2rem;
}

.card-title {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.5rem;
    margin: 0;
}

.card-body {
    padding: 2rem;
}

/* Section Header */
.section-header {
    background: linear-gradient(to right, #f8f9fa, #f1f3f5);
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.section-header:hover {
    background: linear-gradient(to right, #f1f3f5, var(--hcis-legacy-bootstrap-gray));
    transform: translateY(-1px);
}

.section-header h5 {
    color: #345;
    font-weight: 600;
}

/* Badge Styling untuk Nilai */
.score-badge {
    display: inline-block;
    background-color: #3498db;
    color: white;
    border-radius: 4px;
    padding: 3px 8px;
    font-weight: bold;
    margin-left: 6px;
}

.ticket-count {
    display: inline-block;
    font-weight: bold;
}

.review-badge {
    display: inline-block;
    background-color: #27ae60;
    color: white;
    border-radius: 4px;
    padding: 3px 12px;
    font-weight: bold;
}

.avg-score-badge {
    display: inline-block;
    color: white;
    border-radius: 4px;
    padding: 3px 10px;
    font-weight: bold;
}

.avg-score-badge.excellent {
    background-color: #27ae60;
}

.avg-score-badge.good {
    background-color: #3498db;
}

.avg-score-badge.average {
    background-color: #f39c12;
}

.avg-score-badge.poor {
    background-color: #e74c3c;
}

.is-invalid {
    border: 1px solid red;
}
.fixed-column {
    position: absolute;
    width: 200px;
    left: 0;
    background: #fff;
    border-right: 2px solid var(--hcis-legacy-bootstrap-gray);
    z-index: 1;
    padding: 1rem;
    font-weight: 500;
    color: #345;
}

@media (max-width: 768px) {
    .table-wrapper {
        margin-left: 150px;
    }

    .fixed-column {
        width: 150px;
    }

    .rating label {
        font-size: 1.25rem;
    }
}
</style>
