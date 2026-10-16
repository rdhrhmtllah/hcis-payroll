<template>
    <div>
        <div class="section">
            <div class="row" id="basic-table">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ title }}</h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <form
                                    @submit.prevent="submitForm"
                                    id="assessmentForm"
                                >
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

                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for=""
                                                        >Pilih Division</label
                                                    >
                                                    <select
                                                        @change="getPeriode()"
                                                        class="form-select"
                                                        v-model="form.division"
                                                    >
                                                        <option value="">
                                                            Select Divisi
                                                        </option>
                                                        <option
                                                            v-for="(
                                                                data, index
                                                            ) in data_division"
                                                            :key="index"
                                                            :value="
                                                                data.Id_Division
                                                            "
                                                        >
                                                            {{ data.Name }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for=""
                                                        >Pilih Periode</label
                                                    >
                                                    <select
                                                        :disabled="
                                                            !dataPeriode.length
                                                        "
                                                        @change="
                                                            getReviewUser()
                                                        "
                                                        class="form-select"
                                                        v-model="form.periode"
                                                    >
                                                        <option value="">
                                                            {{
                                                                !dataPeriode.length
                                                                    ? "Periode Belum Ada"
                                                                    : "Select Periode"
                                                            }}
                                                        </option>
                                                        <option
                                                            v-for="(
                                                                c, index
                                                            ) in dataPeriode"
                                                            :key="index"
                                                            :value="
                                                                c.Id_Periode
                                                            "
                                                        >
                                                            {{ c.Kode_Periode }}
                                                            ({{ c.Keterangan }})
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-for="(item, index) in data"
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
                                                        transform:
                                                            sectionStates[index]
                                                                ? 'rotate(180deg)'
                                                                : 'rotate(0deg)',
                                                    }"
                                                ></i>
                                            </div>
                                            <div
                                                v-show="sectionStates[index]"
                                                class="collapse show"
                                            >
                                                <div
                                                    class="table-container mt-3"
                                                >
                                                    <div
                                                        class="table-wrapper"
                                                        ref="tableWrapper"
                                                    >
                                                        <table
                                                            class="table table-bordered assessment-table"
                                                        >
                                                            <thead>
                                                                <tr>
                                                                    <th
                                                                        class="fixed-column"
                                                                    >
                                                                        Nama
                                                                        User
                                                                    </th>
                                                                    <th
                                                                        v-for="(
                                                                            criteria,
                                                                            cIndex
                                                                        ) in item.sub_category"
                                                                        :key="
                                                                            cIndex
                                                                        "
                                                                        class="scrollable-column text-center"
                                                                    >
                                                                        {{
                                                                            criteria.name
                                                                        }}
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="(
                                                                        user1,
                                                                        uIndex
                                                                    ) in user"
                                                                    :key="
                                                                        uIndex
                                                                    "
                                                                >
                                                                    <td
                                                                        class="fixed-column"
                                                                    >
                                                                        <span
                                                                            class="user-name"
                                                                            >{{
                                                                                user1.Username
                                                                            }}</span
                                                                        >
                                                                    </td>
                                                                    <td
                                                                        v-for="(
                                                                            criteria,
                                                                            cIndex
                                                                        ) in item.sub_category"
                                                                        :key="
                                                                            cIndex
                                                                        "
                                                                        class="scrollable-column"
                                                                    >
                                                                        <div
                                                                            class="rating"
                                                                            :data-user="
                                                                                user1.Id_Users
                                                                            "
                                                                            :data-criteria="
                                                                                criteria.id
                                                                            "
                                                                        >
                                                                            <template
                                                                                v-for="i in 5"
                                                                            >
                                                                                <input
                                                                                    type="radio"
                                                                                    :name="`ratings_${user1.Id_Users}_${criteria.id}`"
                                                                                    :value="
                                                                                        6 -
                                                                                        i
                                                                                    "
                                                                                    :id="`rating_${
                                                                                        user1.Id_Users
                                                                                    }_${
                                                                                        criteria.id
                                                                                    }_${
                                                                                        6 -
                                                                                        i
                                                                                    }`"
                                                                                    class="rating-input"
                                                                                    required
                                                                                    v-model="
                                                                                        form
                                                                                            .ratings[
                                                                                            `${user1.Id_Users}_${criteria.id}`
                                                                                        ]
                                                                                    "
                                                                                    @change="
                                                                                        updateCompletion(
                                                                                            user1.Id_Users,
                                                                                            criteria.id
                                                                                        )
                                                                                    "
                                                                                />
                                                                                <label
                                                                                    :for="`rating_${
                                                                                        user1.Id_Users
                                                                                    }_${
                                                                                        criteria.id
                                                                                    }_${
                                                                                        6 -
                                                                                        i
                                                                                    }`"
                                                                                    :title="`${
                                                                                        6 -
                                                                                        i
                                                                                    } stars`"
                                                                                    >★</label
                                                                                >
                                                                            </template>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!data.length" class="row">
                                            <div
                                                class="col-12 text-center text-muted my-4"
                                            >
                                                Belum Ada Global Review Pada
                                                Periode Ini
                                            </div>
                                        </div>

                                        <div
                                            class="d-flex justify-content-between align-items-center mt-4"
                                        >
                                            <span
                                                class="text-muted"
                                                id="completionStatus"
                                                >{{ completionPercentage }}%
                                                completed</span
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-primary"
                                                id="submitBtn"
                                                :disabled="!isFormComplete"
                                            >
                                                Submit Penilaian
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { url } from "@/utils/sharedvalue.js";
import Swal from "sweetalert2";
import axios from "axios";
export default {
    props: {
        title: String,
        // data: Array,
        // user: Array,

        data_division: Array,
    },
    data() {
        return {
            form: {
                tanggal_mulai: "",
                tanggal_akhir: "",
                periode: "",
                division: "",
                ratings: {},
                criteria_ids: "",
                user_ids: "",
            },
            data: [],
            user: [],
            sectionStates: {},
            completedRatings: 0,
            dataPeriode: [],
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
        async getPeriode() {
            if (this.form.division == "") return;
            this.data = [];
            this.user = [];
            this.totalRatings = 0;
            try {
                const response = await axios.get(
                    `${url}getPeriode-review/${this.form.division}`
                );

                // console.log(response);
                if (response.status == 200) {
                    this.dataPeriode = response.data.data;
                } else {
                    this.data_periode = [];
                    Swal.fire("Terjadi kesalahan");
                }
            } catch (error) {
                console.log(error);
            }
        },
        async getReviewUser() {
            if (this.form.division == "") return;
            if (this.form.periode == "") return;
            this.data = [];
            this.user = [];
            this.totalRatings = 0;
            try {
                const response = await axios.get(
                    `${url}getReview-user/${this.form.division}`
                );

                // console.log(response);
                if (response.status == 200) {
                    this.data = response.data.data;
                    this.user = response.data.user;
                    this.totalRatings = this.calculateTotalRatings();
                } else {
                    this.data_periode = [];
                    Swal.fire("Terjadi kesalahan");
                }
            } catch (error) {
                console.log(error);
            }
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
            console.log(this.data);
            this.data.forEach((category) => {
                console.log(category + " jumlah ");
                total += category.sub_category.length * this.user.length;
            });

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
        submitForm() {
            // this.isDateValid = this.validateDates(); // panggil validasi
            if (this.form.division == "") {
                Swal.fire("info", "Periode harus di pilih", "error");
                return;
            }
            if (this.form.periode == "") {
                Swal.fire("info", "Periode harus di pilih", "error");
                return;
            }

            // if (!this.isDateValid) {
            //   alert(
            //     "Tanggal mulai harus diisi dan tidak boleh kurang dari hari ini."
            //   );
            //   return;
            // }
            if (this.completedRatings === this.totalRatings) {
                if (
                    confirm("Apakah Anda yakin ingin mengirim penilaian ini?")
                ) {
                    // Proses data formulir untuk pengiriman

                    const formData = {
                        // tanggal_mulai: this.form.tanggal_mulai,
                        // tanggal_akhir: this.form.tanggal_akhir,
                        periode: this.form.periode,
                        division: this.form.division,
                        ratings: {},
                    };

                    // Konversi ratings dari format key kita ke format objek bersarang
                    Object.keys(this.form.ratings).forEach((key) => {
                        const [userId, criteriaId] = key.split("_");
                        if (!formData.ratings[userId]) {
                            formData.ratings[userId] = {};
                        }
                        formData.ratings[userId][criteriaId] =
                            this.form.ratings[key];
                    });

                    // console.log(formData);

                    // Kirim ke server
                    //   this.$inertia.post("/assestmentGlobalReview", formData);
                    axios
                        .post("/assestmentGlobalReview", formData)
                        .then((response) => {
                            Swal.fire(
                                "Sukses",
                                "Data berhasil disimpan!",
                                "success"
                            ).then(() => {
                                window.location.reload();
                            });
                        })
                        .catch((error) => {
                            if (error.response) {
                                // Kalo error dari server (misal 422, 500, dll)
                                const msg =
                                    error.response.data?.message ||
                                    "Terjadi kesalahan";
                                Swal.fire("Error", msg, "error");
                            } else {
                                // Kalo error jaringan atau lainnya
                                Swal.fire(
                                    "Error",
                                    "Gagal terhubung ke server",
                                    "error"
                                );
                            }
                        });
                    // this.$inertia.post("/assestmentGlobalReview", formData, {
                    //   onSuccess: () => {
                    //     alert("Data berhasil disimpan!");
                    //     window.location.reload(); // langsung refresh
                    //   },
                    //   onError: (e) => {
                    //     Swal.fire('error', e, 'error')
                    //     return
                    //   }
                    // });
                }
            } else {
                alert("Harap lengkapi semua penilaian sebelum mengirim.");
            }
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

/* Table Styling */
.table-container {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
    background: #fff;
}

.table-wrapper {
    overflow-x: auto;
    margin-left: 200px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #345;
    padding: 1rem;
    white-space: nowrap;
    border-bottom: 2px solid var(--hcis-legacy-bootstrap-gray);
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: var(--hcis-legacy-bootstrap-gray);
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
