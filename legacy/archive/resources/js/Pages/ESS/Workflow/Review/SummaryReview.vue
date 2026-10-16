<template>
    <div>
        <div class="section">
            <div class="row" id="basic-table">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between">
                                <!-- <h4 class="card-title">{{ title }}</h4> -->
                                <!-- <a href="/summary-review" class="btn btn-danger">Back</a> -->
                                <h4 class="card-title">
                                    No Review {{ no_review }}
                                </h4>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="text-center">
                                    <h4>{{ periode }}</h4>
                                    <p>{{ tanggal }}</p>
                                </div>
                                <div class="assessment-container">
                                    <!-- <div class="row mb-2">
                    <div class="col-md-4">
                      <label for="">Mulai</label>
                      <input
                        type="date"
                        v-model="form.tanggal_mulai"
                        class="form-control"
                        :class="{ 'is-invalid': !isDateValid }"
                      />
                    </div>
                    <div class="col-md-4">
                      <label for="">Sampai</label>
                      <input
                        type="date"
                        v-model="form.tanggal_akhir"
                        class="form-control"
                      />
                    </div>
                  </div> -->

                                    <div
                                        v-for="(item, index) in dataKPI"
                                        :key="index"
                                        class="assessment-section mb-4"
                                    >
                                        <div
                                            class="section-header d-flex justify-content-between align-items-center bg-light p-3 rounded"
                                            @click="toggleSection(index)"
                                        >
                                            <h5 class="mb-0">
                                                {{ item.case_category }}
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
                                                                    Nama User
                                                                </th>
                                                                <th>
                                                                    Total Ticket
                                                                    (Score)
                                                                </th>
                                                                <th>
                                                                    Global
                                                                    Review
                                                                    (Score)
                                                                </th>
                                                                <th>
                                                                    AVG Score
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
                                                                        user1.name
                                                                    }}
                                                                </td>
                                                                <td>
                                                                    <span
                                                                        class="ticket-count"
                                                                        >{{
                                                                            user1.Total_Ticket
                                                                        }}
                                                                        Ticket</span
                                                                    >
                                                                    <span></span>
                                                                    <span
                                                                        class="score-badge"
                                                                        >{{
                                                                            user1.Total_Score_Ticket
                                                                        }}</span
                                                                    >
                                                                </td>
                                                                <td>
                                                                    <span
                                                                        class="review-badge"
                                                                        >{{
                                                                            user1.AVG
                                                                        }}</span
                                                                    >
                                                                </td>
                                                                <td>
                                                                    <span
                                                                        class="avg-score-badge"
                                                                        :class="
                                                                            getScoreClass(
                                                                                4.8
                                                                            )
                                                                        "
                                                                    >
                                                                        {{
                                                                            user1.Total_Keseluruhan
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

                                    <div
                                        class="d-flex justify-content-between align-items-center mt-4"
                                    >
                                        <span
                                            class="text-muted"
                                            id="completionStatus"
                                            >{{ completionPercentage }}% Total
                                            Keseluruhan Nilai</span
                                        >
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
        user: Array,
        no_review: String,
        periode: String,
        tanggal: String,
        dataKPI: String,
        division: String,
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
        console.log(this.data);
        this.data.forEach((_, index) => {
            initialSectionStates[index] = true;
        });
        this.sectionStates = initialSectionStates;
    },
    mounted() {
        // Hitung total ratings
        this.totalRatings = this.calculateTotalRatings();

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

            let currentRow = 5;
            worksheet.mergeCells("A1:E1");
            worksheet.getCell("A1").value = this.division;

            worksheet.mergeCells("A2:E2");
            worksheet.getCell("A2").value = this.periode;

            worksheet.mergeCells("A3:E3");
            worksheet.getCell("A3").value = this.tanggal;
            worksheet.addRow([
                "User Name",
                "Global Score",
                "Total Ticket",
                "Total Score Tiket",
                "Total Keseluruhan",
            ]);

            // Perulangan untuk data user
            this.dataKPI.forEach((item) => {
                // Title Category
                worksheet.mergeCells(`A${currentRow}:E${currentRow}`);
                worksheet.getCell(`A${currentRow}`).value = item.case_category;
                worksheet.getCell(`A${currentRow}`).fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "FFF9C4" },
                };
                worksheet.getCell(`A${currentRow}`).alignment = {
                    horizontal: "left",
                    verticalL: "middle",
                };
                currentRow++;
                // User Score
                item.sub_category.forEach((user) => {
                    worksheet.addRow([
                        user.name,
                        user.AVG,
                        user.Total_Ticket,
                        user.Total_Score_Ticket,
                        user.Total_Keseluruhan,
                    ]);
                    currentRow++;
                });
            });

            worksheet.eachRow((row, idx) => {
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

                    // if (idx >= 5 && idx % 2 != 0) {
                    //     (cell.fill = {
                    //         type: "pattern",
                    //         pattren: "solid",
                    //         fgColor: { argb: "FFFF00" },
                    //     }),
                    //         (cell.alignment = {
                    //             horizontal: "left",
                    //             verticalL: "middle",
                    //         });
                    // }
                });
            });

            worksheet.columns = [
                { width: 25 },
                { width: 25 },
                { width: 25 },
                { width: 25 },
                { width: 25 },
            ];

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], {
                type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            });

            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `Global-review-summary-${this.no_review}.xlsx`;
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
        calculateTotalRatings() {
            let total = 0;
            // console.log(this.user.length)
            this.data.forEach((category) => {
                total += category.sub_category.length * this.user.length;
            });
            //   console.log(total)
            return total;
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
            this.isFormComplete = this.completedRatings === this.totalRatings;
        },
        getScoreClass(score) {
            if (score >= 4.5) return "excellent";
            if (score >= 4.0) return "good";
            if (score >= 3.0) return "average";
            return "poor";
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
    background: linear-gradient(to bottom, #ffffff, #fafbfc);
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
    border: 1px solid #e9ecef;
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.section-header:hover {
    background: linear-gradient(to right, #f1f3f5, #e9ecef);
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
    border-right: 2px solid #e9ecef;
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
