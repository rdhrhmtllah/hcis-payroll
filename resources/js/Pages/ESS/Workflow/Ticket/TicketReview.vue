<template>
  <div>
    <div class="section">
      <div class="row" id="basic-table">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h4 class="card-title">No Ticket : {{ no_ticket }}</h4>
            </div>
            <div class="card-content">
              <div class="card-body">
                <form @submit.prevent="submitForm" id="assessmentForm">
                  <table class="table table-bordered assessment-table">
                    <thead>
                      <tr>
                        <th
                          v-for="(criteria, cIndex) in data"
                          :key="cIndex"
                          class="scrollable-column text-center"
                        >
                          {{ criteria.Name }}
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td
                          v-for="(criteria, cIndex) in data"
                          :key="cIndex"
                          class="scrollable-column"
                        >
                          <div class="rating" :data-criteria="criteria.Name">
                            <template v-for="i in 5">
                              <input
                                type="radio"
                                :name="`ratings_${criteria.Id_Evaluation}`"
                                :value="6 - i"
                                :id="`rating_${criteria.Id_Evaluation}_${
                                  6 - i
                                }`"
                                class="rating-input"
                                required
                                v-model="
                                  form.ratings[`${criteria.Id_Evaluation}`]
                                "
                                @change="
                                  updateCompletion(criteria.Id_Evaluation)
                                "
                              />
                              <label
                                :for="`rating_${criteria.Id_Evaluation}_${
                                  6 - i
                                }`"
                                :title="`${6 - i} stars`"
                                >★</label
                              >
                            </template>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>

                  <div
                    class="d-flex justify-content-between align-items-center mt-4"
                  >
                    <span class="text-muted" id="completionStatus"
                      >{{ completionPercentage }}% completed</span
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
import Swal from "sweetalert2";
import axios from "axios";
export default {
  props: {
    title: String,
    data: Array,
    no_ticket: String,
    Ticket_Id: String,
  },
  data() {
    return {
      form: {
        tanggal_mulai: "",
        tanggal_akhir: "",
        periode: "",
        ratings: {},
        criteria_ids: "",
        user_ids: "",
      },
      sectionStates: {},
      completedRatings: 0,
      totalRatings: 0,
      isFormComplete: false,
      isDateValid: true,
      hasRatings: 0,
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
          wrapper.removeEventListener("scroll", this.handleTableScroll);
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
    validateDates() {
      if (!this.form.tanggal_mulai || !this.form.tanggal_akhir) return false;

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
      //   this.data.forEach((category) => {
      //     console.log(category)
      //     total += category.length;
      //   });
      // console.log(total)
      total = this.data.length;

      return total;
    },
    updateCompletion(criteriaId) {
      let count = 0;
      //   this.hasRatings ++

      count = Object.keys(this.form.ratings).length;

      this.completedRatings = count;
      console.log(this.completedRatings + " - " + this.totalRatings);
      this.isFormComplete = this.completedRatings === this.totalRatings;
    },
    submitForm() {
      if (this.completedRatings === this.totalRatings) {
        if (confirm("Apakah Anda yakin ingin mengirim penilaian ini?")) {
          // Proses data formulir untuk pengiriman

          const formData = {
            // tanggal_mulai: this.form.tanggal_mulai,
            // tanggal_akhir: this.form.tanggal_akhir,
            no_ticket: this.no_ticket,
            id_ticket: this.Ticket_Id,
            ratings: {},
          };

          // Konversi ratings dari format key kita ke format objek bersarang
          Object.keys(this.form.ratings).forEach((key) => {
            const criteriaId = key;

            formData.ratings[criteriaId] = this.form.ratings[key];
          });

          axios
            .post("/simpan_review_ticket", formData)
            .then((response) => {
              Swal.fire("Sukses", response.data.pesan, "success").then(() => {
                window.location.href = "/displayTicket"; // Ganti ini sesuai route Laravel kamu
              });
            })
            .catch((error) => {
              if (error.response) {
                // Kalo error dari server (misal 422, 500, dll)
                const msg = error.response.data?.message || "Terjadi kesalahan";
                Swal.fire("Error", msg, "error");
              } else {
                // Kalo error jaringan atau lainnya
                Swal.fire("Error", "Gagal terhubung ke server", "error");
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