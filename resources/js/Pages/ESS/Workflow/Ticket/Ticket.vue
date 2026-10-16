<template>
    <div class="row d-flex justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-body">
                    <div class="form-header animate-in mb-3 text-center mb-5">
                        <h1>Create Support Ticket</h1>
                    </div>
                    <div class="text-red-500 mt-1" v-if="errors.files">
                        {{ errors.files }}
                    </div>
                    <div class="text-red-500 mt-1" v-if="errors['files.0']">
                        {{ errors["files.0"] }}
                    </div>
                    <form class="from" @submit.prevent="submitForm">
                        <div class="row d-flex justify-content-center">
                            <div class="col-md-8">
                                <!-- <div class="form-group">
                  <label>No Transaksi</label>
                  <div class="input-wrapper auto-number">
                    <input

                      type="text"
                      value="TRX-001"
                      disabled
                    />
                  </div>
                </div> -->

                                <div class="form-group">
                                    <div
                                        class="form-group animate-in mb-3"
                                        style="--delay: 0.1s"
                                    >
                                        <label>Divisi</label>
                                        <div
                                            class="select-wrapper"
                                            :class="{ active: isDivisionOpen }"
                                        >
                                            <select
                                                v-model="selectedDivision"
                                                @focus="isDivisionOpen = true"
                                                @blur="isDivisionOpen = false"
                                                @change="handleDivisionChange"
                                            >
                                                <option value="">
                                                    Select Division
                                                </option>
                                                <option
                                                    v-for="division in dataDivisi"
                                                    :key="division.Id_Division"
                                                    :value="
                                                        division.Id_Division
                                                    "
                                                >
                                                    {{ division.Name }}
                                                </option>
                                            </select>
                                            <div class="select-icon"></div>
                                            <div class="select-highlight"></div>
                                        </div>
                                    </div>

                                    <div
                                        class="form-group animate-in mb-3"
                                        style="--delay: 0.2s"
                                    >
                                        <label>PIC</label>
                                        <div
                                            class="select-wrapper"
                                            :class="{ active: isPICOpen }"
                                        >
                                            <select
                                                v-model="selectedPIC"
                                                @focus="isPICOpen = true"
                                                @blur="isPICOpen = false"
                                                :disabled="!selectedDivision"
                                            >
                                                <option value="">
                                                    Select PIC
                                                </option>
                                                <option
                                                    v-for="(
                                                        person, index
                                                    ) in filteredPeople"
                                                    :key="index"
                                                    :value="person.Id_Users"
                                                >
                                                    {{ person.Username }}
                                                </option>
                                            </select>
                                            <div class="select-icon"></div>
                                            <div class="select-highlight"></div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="form-group animate-in mb-3"
                                    style="--delay: 0.3s"
                                >
                                    <label>CASE</label>
                                    <div
                                        class="select-wrapper"
                                        :class="{ active: isCaseOpen }"
                                    >
                                        <select
                                            v-model="selectedCase"
                                            @focus="isCaseOpen = true"
                                            @blur="isCaseOpen = false"
                                            :disabled="!selectedDivision"
                                        >
                                            <option value="">
                                                Select Case
                                            </option>
                                            <option
                                                v-for="(
                                                    c, index
                                                ) in filteredCases"
                                                :key="index"
                                                :value="c.Id_Case"
                                            >
                                                {{ c.Name }}
                                            </option>
                                        </select>
                                        <div class="select-icon"></div>
                                        <div class="select-highlight"></div>
                                    </div>
                                </div>

                                <!-- Rest of the form remains the same -->
                                <div
                                    class="form-group animate-in mb-3"
                                    style="--delay: 0.4s"
                                >
                                    <label>Keterangan</label>
                                    <div class="textarea-wrapper">
                                        <textarea
                                            class="form-control"
                                            rows="4"
                                            v-model="keterangan"
                                            placeholder="Describe your issue in detail..."
                                            :class="{
                                                'has-content':
                                                    keterangan.length > 0,
                                            }"
                                        ></textarea>
                                        <div class="textarea-highlight"></div>
                                    </div>
                                </div>

                                <!-- <div class="form-group animate-in mb-3" style="--delay: 0.5s">
                  <label>Lampiran</label>
                  <input
                    class="form-control"
                    type="file"
                    multiple
                    @change="handleFileChange"
                    accept=".jpg,.jpeg,.png,.pdf,.xlsx,.xls"
                  />
                </div>
                <ul class="mt-2">
                  <li v-for="(file, index) in files" :key="index">
                    <div class="row">
                      <div class="col-md-6">
                        <div v-if="file.type.startsWith('image/')">
                          <img
                            :src="getImagePreview(file)"
                            style="width: 250px; height: 250px"
                            class="object-cover rounded border"
                          />
                        </div>
                        <div v-else>📄 {{ file.name }}</div>
                      </div>
                      <div class="col-md-6">
                        <button
                          type="button"
                          @click="removeFile(index)"
                          class="text-red-500 btn"
                        >
                          ❌
                        </button>
                      </div>
                    </div>
                  </li>
                </ul> -->
                                <button
                                    type="submit"
                                    class="submit-btn animate-in mb-3"
                                    style="--delay: 0.6s"
                                    :disabled="loadingButton"
                                >
                                    <span class="btn-content">
                                        <span class="btn-text"
                                            >Submit Ticket</span
                                        >
                                        <span class="btn-icon"></span>
                                    </span>
                                    <div class="btn-shine"></div>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { url } from "@/utils/sharedvalue.js";
import Swal from "sweetalert2";
export default {
    name: "ModernTicketForm",
    props: {
        dataDivisi: Object,
        data: Array,
    },
    data() {
        return {
            loadingButton: false,
            logo: "/logo/logo.png",
            selectedDivision: "",
            selectedPIC: "",
            selectedCase: "",
            keterangan: "",
            selectedFile: [],
            files: [],
            isDivisionOpen: false,
            isPICOpen: false,
            isCaseOpen: false,
            errors: {},
            // Data for PIC and Cases that will be fetched
            allPeople: [],
            allCases: [],

            // Loading states (only for PIC and Cases)
            isLoadingPICs: false,
            isLoadingCases: false,

            // Error handling
            errors: {
                division: "",
                pic: "",
                case: "",
                api: "",
            },

            // API configuration
            apiBaseUrl: "http://localhost:8000/api",
        };
    },
    computed: {
        filteredPeople() {
            if (!this.selectedDivision) return [];
            return this.allPeople;
        },
        filteredCases() {
            if (!this.selectedDivision) return [];
            return this.allCases;
        },
    },
    methods: {
        async handleDivisionChange() {
            if (!this.selectedDivision) {
                this.resetDependentFields();
                return;
            }

            this.resetDependentFields();
            await Promise.all([
                this.fetchPICsByDivision(),
                // this.fetchCasesByDivision(),
            ]);
        },

        async simpanTicket() {},

        async fetchPICsByDivision() {
            if (!this.selectedDivision) return;

            this.isLoadingPICs = true;
            this.errors.pic = "";
            this.allPeople = [];
            this.allCases = [];

            try {
                const response = await axios.get(
                    `${url}getPICTicket/${this.selectedDivision}`
                );
                this.allPeople = response.data.data;
                this.allCases = response.data.cases;
            } catch (error) {
                this.handleApiError(error, "pic");
            } finally {
                this.isLoadingPICs = false;
            }
        },

        async submitForm() {
            // alert("hei")
            this.loadingButton = true;
            if (!this.validateForm()) return;
            console.log(this.selectedPIC);
            const formData = new FormData();
            formData.append("division_id", this.selectedDivision);
            formData.append("pic_id", this.selectedPIC);
            formData.append("case_id", this.selectedCase);
            formData.append("description", this.keterangan);
            this.files.forEach((file) => {
                formData.append("files[]", file);
            });

            axios
                .post("/simpanTicket", formData)
                .then((response) => {
                    Swal.fire(
                        "success",
                        "Ticket berhasil di simpan",
                        "success"
                    );
                    console.log(response);
                    //   window.location.reload(); // langsung refresh
                })
                .catch((error) => {
                    if (error.response && error.response.status === 422) {
                        console.log(error.response.data.message);
                        Swal.fire("Error", error.response.data.errors, "error");
                        // this.errors = error.response.data.errors;
                    } else {
                        Swal.fire(
                            "error",
                            "Terjadi kesalahan" + error,
                            "error"
                        );
                    }
                })
                .finally(() => {
                    this.loadingButton = false;
                });
        },

        // Helper Methods
        resetDependentFields() {
            this.selectedPIC = "";
            this.selectedCase = "";
            this.allPeople = [];
            this.allCases = [];
        },

        resetForm() {
            this.selectedDivision = "";
            this.resetDependentFields();
            this.keterangan = "";
            this.selectedFile = null;
        },

        validateForm() {
            let isValid = true;

            this.errors = {
                division: "",
                pic: "",
                case: "",
                keterangan: "",
            };

            if (this.selectedDivision == "") {
                console.log("Masuk sini");
                this.errors.division = "Please select a division";
                Swal.fire("error", this.errors.division, "error");
                return null;
            }
            if (this.selectedPIC == "") {
                this.errors.pic = "Please select a PIC";
                Swal.fire("error", this.errors.pic, "error");
                return null;
            }
            if (this.selectedCase == "") {
                this.errors.case = "Please select a case";
                Swal.fire("error", this.errors.case, "error");
                return null;
            }

            if (this.keterangan == "") {
                Swal.fire("error", "Keterangan harus di isi", "error");
                return null;
            }

            return isValid;
        },

        handleApiError(error, field) {
            console.error(`Error in ${field}:`, error);

            if (error.response) {
                switch (error.response.status) {
                    case 401:
                        this.errors[field] =
                            "Session expired. Please login again.";
                        break;
                    case 403:
                        this.errors[field] =
                            "You don't have permission to perform this action.";
                        break;
                    case 422:
                        this.errors[field] =
                            error.response.data.message || "Invalid input.";
                        break;
                    default:
                        this.errors[field] =
                            "An error occurred. Please try again.";
                }
            } else if (error.request) {
                this.errors[field] =
                    "Network error. Please check your connection.";
            } else {
                this.errors[field] = "An unexpected error occurred.";
            }
        },

        handleFileChange(event) {
            // this.files = Array.from(event.target.files);
            const newFiles = Array.from(event.target.files);
            this.files = [...this.files, ...newFiles];
        },
        removeFile(index) {
            this.files.splice(index, 1);
        },
        getImagePreview(file) {
            return URL.createObjectURL(file);
        },
    },
};
</script>

<style scoped>
:root {
    --primary: #ffb347;
    --primary-light: #ffe4b5;
    --secondary: #fff8dc;
    --accent: #ff9999;
    --text-primary: #2d3748;
    --text-secondary: #4a5568;
    --background: #fff8f4;
    --input-bg: var(--hcis-white);
    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.page-container {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--background) 0%, #fff5f5 100%);
    padding: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ticket-container {
    width: 100%;
    max-width: 800px;
    background: var(--input-bg);
    border-radius: 30px;
    box-shadow: var(--shadow-lg), 0 0 0 1px rgba(255, 179, 71, 0.05);
    overflow: hidden;
    margin: 2rem;
}

.logo-section {
    text-align: center;
    padding: 3rem 2rem;
    background: linear-gradient(120deg, var(--primary-light), var(--secondary));
    position: relative;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.logo {
    height: 80px;
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
    transition: transform 0.3s ease;
}

.content-section {
    padding: 4rem;
}

.form-header {
    text-align: center;
    margin-bottom: 4rem;
    position: relative;
}

.form-header h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    background: linear-gradient(120deg, var(--primary), var(--accent));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.form-header::after {
    content: "";
    display: block;
    width: 100px;
    height: 4px;
    background: linear-gradient(to right, var(--primary), var(--accent));
    margin: 1rem auto;
    border-radius: 2px;
}

.form-group {
    margin-bottom: 2.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2.5rem;
}

label {
    display: block;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
    font-size: 1rem;
    letter-spacing: 0.5px;
}

input[type="text"],
select,
textarea {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid var(--hcis-slate-200);
    border-radius: 15px;
    font-size: 1rem;
    transition: all 0.2s ease;
    box-shadow: var(--shadow-sm);
}

input[type="text"]:disabled {
    border-color: var(--hcis-slate-200);
    color: var(--text-secondary);
}

select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234A5568'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1.25rem center;
    background-size: 1.5em;
    padding-right: 3rem;
}

textarea {
    min-height: 150px;
    resize: vertical;
    margin-bottom: 2rem;
}

.file-upload {
    border: 2px dashed var(--hcis-slate-200);
    border-radius: 15px;
    padding: 3rem 2rem;
    text-align: center;
    background: var(--secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 3rem;
}

.file-upload:hover {
    border-color: var(--primary);
    background: var(--primary-light);
}

.submit-btn {
    width: 100%;
    padding: 1.25rem;
    background: linear-gradient(120deg, var(--primary), var(--accent));
    border: none;
    border-radius: 15px;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
    margin-top: 1rem;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.submit-btn::after {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 45%,
        rgba(255, 255, 255, 0.2) 48%,
        rgba(255, 255, 255, 0.4) 50%,
        rgba(255, 255, 255, 0.2) 52%,
        transparent 55%
    );
    transform: rotate(45deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%) rotate(45deg);
    }
    100% {
        transform: translateX(100%) rotate(45deg);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-container {
        padding: 1.5rem;
    }

    .ticket-container {
        margin: 1rem;
    }

    .content-section {
        padding: 2.5rem;
    }

    .form-header h1 {
        font-size: 2.5rem;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}

@media (max-width: 480px) {
    .content-section {
        padding: 2rem;
    }

    .form-header h1 {
        font-size: 2rem;
    }

    .file-upload {
        padding: 2rem 1.5rem;
    }
}
</style>
