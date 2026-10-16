<template>
    <div>
        <div class="section">
            <div class="row" id="basic-table">
                <div class="col-12">
                    <div class="card shadow rounded-4">
                        <div class="card-header rounded-4">
                            <h4 class="card-title m-0">{{ title }}</h4>
                        </div>
                        <hr class="m-0 p-0" />
                        <div class="card-content">
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Pilih Division</label>
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
                                                    ) in division"
                                                    :key="index"
                                                    :value="data.Id_Division"
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
                                            <label for="">Pilih Periode</label>
                                            <select
                                                disabled
                                                class="form-select"
                                                v-model="form.periode"
                                            >
                                                <option
                                                    v-if="!dataPeriode.length"
                                                    value=""
                                                >
                                                    Periode Belum Ada
                                                </option>
                                                <option
                                                    v-for="(
                                                        c, index
                                                    ) in dataPeriode"
                                                    :key="index"
                                                    :value="c.Id_Periode"
                                                >
                                                    {{ c.Kode_Periode }} ({{
                                                        c.Keterangan
                                                    }})
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Pilih User</label>
                                            <div v-if="loading">
                                                <div
                                                    class="form-select disabled placeholder-wave"
                                                    style="height: 38px"
                                                >
                                                    Loading....
                                                </div>
                                            </div>
                                            <select
                                                v-else
                                                :disabled="!dataUser.length"
                                                @change="getReviewCross()"
                                                class="form-select"
                                                v-model="form.user"
                                            >
                                                <option value="">
                                                    {{
                                                        !dataUser.length
                                                            ? "User Belum Ada"
                                                            : "Select User"
                                                    }}
                                                </option>
                                                <option
                                                    v-for="(
                                                        c, index
                                                    ) in dataUser"
                                                    :key="index"
                                                    :value="c.id_user"
                                                >
                                                    {{ c.Nama }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!data.length" class="row">
                                    <div
                                        class="col-12 text-center text-muted my-4"
                                    >
                                        Belum Ada Cross Review Pada Periode Ini
                                    </div>
                                </div>
                                <form
                                    @submit.prevent="submitForm"
                                    id="assesmentForm"
                                >
                                    <div class="assessment-container">
                                        <div class="">
                                            <div
                                                class="assesment-section mb-4"
                                                v-for="(item, index) in data"
                                                :key="index"
                                            >
                                                <div
                                                    class="section-header d-flex justify-content-between align-items-center bg-light p-3 rounded"
                                                    @click="
                                                        toggleSection(index)
                                                    "
                                                >
                                                    <h5>
                                                        {{ item.category }}
                                                    </h5>
                                                    <i
                                                        class="bi bi-chevron-down transition-transform"
                                                        :style="{
                                                            transform:
                                                                sectionStates[
                                                                    index
                                                                ]
                                                                    ? 'rotate(180deg)'
                                                                    : 'rotate(0deg)',
                                                        }"
                                                    ></i>
                                                </div>
                                                <div
                                                    v-show="
                                                        sectionStates[index]
                                                    "
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
                                                                                criteria.Name
                                                                            }}
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <tr
                                                                        v-for="(
                                                                            user,
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
                                                                                    user.Nama
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
                                                                                    user.id_user
                                                                                "
                                                                                :data-criteria="
                                                                                    criteria.id
                                                                                "
                                                                            >
                                                                                <template
                                                                                    v-for="i in 5"
                                                                                    :key="
                                                                                        i
                                                                                    "
                                                                                >
                                                                                    <input
                                                                                        type="radio"
                                                                                        :name="`rating_${user.id_user}_${criteria.id}`"
                                                                                        :value="
                                                                                            6 -
                                                                                            i
                                                                                        "
                                                                                        :id="`rating_${
                                                                                            user.id_user
                                                                                        }_${
                                                                                            criteria.id
                                                                                        }_${
                                                                                            6 -
                                                                                            i
                                                                                        }`"
                                                                                        class="rating_input"
                                                                                        v-model="
                                                                                            form
                                                                                                .ratings[
                                                                                                `${user.id_user}_${criteria.id}`
                                                                                            ]
                                                                                        "
                                                                                        required
                                                                                        @change="
                                                                                            updateCompletion(
                                                                                                user.id_user,
                                                                                                criteria.id
                                                                                            )
                                                                                        "
                                                                                    />
                                                                                    <label
                                                                                        :for="`rating_${
                                                                                            user.id_user
                                                                                        }_${
                                                                                            criteria.id
                                                                                        }_${
                                                                                            6 -
                                                                                            i
                                                                                        }`"
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
                                            <div
                                                v-show="data.length"
                                                class="my-3"
                                            >
                                                <label for="form-label"
                                                    >Keterangan Penilaian</label
                                                >
                                                <textarea
                                                    name="Keterangan"
                                                    @input="hendelKeterangan"
                                                    class="form-control"
                                                ></textarea>
                                            </div>
                                            <div
                                                class="d-flex justify-content-between align-items-center mt-4"
                                            >
                                                <span
                                                    class="text-muted"
                                                    id="completionStatus"
                                                >
                                                    {{ persenSelesai }}%
                                                    Complated
                                                </span>
                                                <button
                                                    type="submit"
                                                    class="btn btn-primary"
                                                    id="submitBtn"
                                                    :disabled="!isFormComplete"
                                                >
                                                    Submit Reviews
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <input
                                        type="hidden"
                                        name="Ticket_Id"
                                        value="{{ $item->Id_Ticket }}"
                                    />
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
import Skeleton from "primevue/skeleton";
import { url } from "@/utils/sharedvalue.js";
import Swal from "sweetalert2";
import axios from "axios";
export default {
    components: {
        Skeleton,
    },
    props: {
        title: String,
        category: Object,
        division: Object,
        Review: Object,
    },

    data() {
        return {
            form: {
                tanggal_mulai: "",
                tanggal_akhir: "",
                periode: "",
                Keterangan: "",
                user: "",
                division: "",
                ratings: {},
                criteria_ids: "",
                user_ids: "",
            },
            loading: true,
            data: [],
            user: [],
            sectionStates: {},
            completedRatings: 0,
            dataPeriode: [],
            dataUser: [],
            totalRatings: 0,
            isFormComplete: false,
            isDateValid: true,
        };
    },
    watch: {
        dataPeriode(newVal) {
            if (newVal.length === 1) {
                this.form.periode = newVal[0].Id_Periode;

                setTimeout(() => {
                    this.getUserCross();
                    this.loading = false;
                }, 2000);
            }
        },
    },
    computed: {
        /* Dulu bernama `completedRatings` — sama persis dengan properti di data().
           Di Vue data menang atas computed, jadi computed ini TIDAK PERNAH
           dievaluasi dan template menampilkan jumlah penilaian mentah diikuti
           tanda persen (mis. "3%" untuk 3 dari 12), bukan persentasenya. */
        persenSelesai() {
            return this.totalRatings === 0
                ? 0
                : Math.round((this.completedRatings / this.totalRatings) * 100);
        },
    },
    created() {
        const initialSectionStates = {};
        this.data.forEach((_, index) => {
            initialSectionStates[index] = true;
        });
        this.sectionStates = initialSectionStates;
    },
    mounted() {
        // Hitung total ratings

        if (this.dataPeriode.length === 1) {
            this.form.periode = this.dataPeriode[0].Id_Periode;

            setTimeout(() => {
                this.getUserCross();
                this.loading = false;
            }, 2000);
        }

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
        hendelKeterangan(v) {
            this.form.Keterangan = v.target.value;
        },
        async getPeriode() {
            if (this.form.division == "") return;
            this.data = [];
            this.user = [];
            this.totalRatings = 0;
            // alert(this.form.division);
            try {
                const response = await axios.get(
                    `${url}getPeriode/${this.form.division}`
                );
                if (response.status == 200) {
                    this.dataPeriode = response.data.data;
                } else {
                    this.data_periode = [];
                    Swal.fire("Terjadi kesalahan oo");
                }
            } catch (error) {}
        },
        async getUserCross() {
            if (this.form.division == "") return;
            if (this.form.periode == "") return;
            this.data = [];
            this.user = [];
            this.totalRatings = 0;

            try {
                const response = await axios.get(
                    `${url}getUser/${this.form.division}/${this.form.periode}`
                );
                console.log(response.status);
                if (response.status == 200) {
                    // this.data = response.data.data;
                    this.dataUser = response.data.user;
                    // this.totalRatings = this.calculateTotalRatings();

                    // console.log(this.form.periode);
                } else {
                    this.data = [];
                    Swal.fire("Terjadi Kesalahan Server!");
                }
            } catch (error) {
                console.log(error);
                Swal.fire("info", error.response.data.message, "info");
            }
        },
        async getReviewCross() {
            if (this.form.division == "") return;
            if (this.form.periode == "") return;
            if (this.form.user == "") return;
            this.data = [];
            this.user = [];
            this.totalRatings = 0;
            console.log(this.form.user);

            try {
                const response = await axios.get(
                    `${url}getReview/${this.form.periode}/${this.form.user}`
                );
                console.log(response.status);
                if (response.status == 200) {
                    this.data = response.data.data;
                    this.user = response.data.user;
                    this.totalRatings = this.calculateTotalRatings();

                    // console.log(this.form.periode);
                } else {
                    this.data = [];
                    Swal.fire("Terjadi Kesalahan Server!");
                }
            } catch (error) {
                console.log(error);
                Swal.fire("info", error.response.data.message, "info");
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
            this.data.forEach((category) => {
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
                        const k = `${user.id_user}_${criteria.id}`;

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
                    const formData = {
                        division: this.form.division,
                        periode: this.form.periode,
                        Keterangan: this.form.Keterangan,
                        ratings: {},
                    };

                    Object.keys(this.form.ratings).forEach((key) => {
                        const [userId, criteriaId] = key.split("_");
                        if (!formData.ratings[userId]) {
                            formData.ratings[userId] = {};
                        }
                        formData.ratings[userId][criteriaId] =
                            this.form.ratings[key];
                    });

                    axios
                        .post("/storeCrossReview", formData)
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
