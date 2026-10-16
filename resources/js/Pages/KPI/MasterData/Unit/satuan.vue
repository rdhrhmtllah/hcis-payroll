<template>
    <div class="container-fluid px-0">
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
            <div class="mb-3">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div
                                        class="icon-container d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-1-circle-fill d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Satuan
                                        </h2>
                                        <p class="subtitle">Manajemen Satuan</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div
                                    class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                >
                                    <div
                                        class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3"
                                    >
                                        <div class="date-item">
                                            <i
                                                v-if="dayNight == 'Siang'"
                                                class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <i
                                                v-else
                                                class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <span class="fs-md-6 text-header">{{
                                                formatDateString(currentDate)
                                            }}</span>
                                        </div>
                                    </div>
                                    <button
                                        class="btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                        @click="openModal"
                                    >
                                        <i
                                            class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                        ></i>
                                        <span class="text-header"
                                            >Tambah Satuan</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-n4 mb-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="col-12 mb-3">
                            <div class="search-box">
                                <i class="bi bi-search custom-T"></i>
                                <input
                                    type="search"
                                    class="form-control search-input"
                                    placeholder="Cari Result..."
                                    v-model="searchQuery"
                                />
                            </div>
                        </div>

                        <TabelUiV1
                            :items="listData"
                            :headers="tableHeaders"
                            :pagination="pagination"
                            :loading="loading.listData"
                            @change-page="changePage"
                        >
                            <!-- nomor -->
                            <template #cell(nomor)="{ index }">
                                {{
                                    (pagination.page - 1) * pagination.limit +
                                    index +
                                    1
                                }}
                            </template>

                            <!-- Nama Kegiatan -->
                            <template #cell(Nama_Satuan)="{ item }">
                                {{ item.Nama_Satuan }}
                            </template>

                            <!-- Simbol_Satuan -->
                            <template #cell(Simbol_Satuan)="{ item }">
                                {{ item.Simbol_Satuan }}
                            </template>

                            <!-- Tanggal -->
                            <template #cell(Tanggal)="{ item }">
                                {{ formatDateString(item.Tanggal) }}
                            </template>

                            <!-- Jam -->
                            <template #cell(Jam)="{ item }">
                                {{ item.Jam }}
                            </template>

                            <template #cell(Flag_Aktif)="{ item }">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    disabled
                                    :checked="item.Flag_Aktif === 'Y'"
                                />
                            </template>

                            <!-- aksi -->
                            <template #cell(aksi)="{ item }">
                                <div
                                    class="btn-group btn-group-sm"
                                    role="group"
                                >
                                    <button
                                        @click="editData(item)"
                                        class="btn btn-action"
                                    >
                                        <i
                                            class="bi bi-pencil-square text-warning"
                                        ></i>
                                    </button>
                                </div>
                            </template>
                        </TabelUiV1>
                    </div>
                </div>
            </div>
        </div>

        <ModalV1
            :show="showModal"
            :title="modalTitle"
            :submitText="modalSubmitText"
            :loading="loading.saveToDatabase || loading.editToDatabase"
            @close="closeModal"
            @submit="submitForm"
        >
            <FormV1 v-model="form" :fields="formFields" />
        </ModalV1>
    </div>
</template>

<script>
import axios from "axios";
import Swal from "sweetalert2";
import { debounce } from "lodash";
import TabelUiV1 from "../../components/ui/TabelUiV1.vue";
import ModalV1 from "@/components/legacy/modalV1.vue";
import FormV1 from "@/components/legacy/formV1.vue";
import { tanggalPanjang } from '@/utils/tanggal';

export default {
    components: {
        TabelUiV1,
        ModalV1,
        FormV1,
    },
    data() {
        return {
            searchQuery: "",
            showModal: false,
            isEditing: false,
            currentDate: new Date(),
            listData: [],
            form: {
                id: null,
                Nama_Satuan: "",
                Simbol_Satuan: "",
            },
            loading: {
                listData: true,
                saveToDatabase: false,
                editToDatabase: false,
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
            return this.isEditing ? "Edit Satuan" : "Tambah Satuan";
        },
        modalSubmitText() {
            return this.isEditing ? "Simpan Perubahan" : "Simpan";
        },
        tableHeaders() {
            return [
                {
                    text: "No.",
                    value: "nomor",
                    class: "text-center",
                    style: "width: 5%;",
                },
                {
                    text: "Nama Satuan",
                    value: "Nama_Satuan",
                    class: "text-center",
                },
                {
                    text: "Simbol Satuan",
                    value: "Simbol_Satuan",
                    class: "text-center",
                },
                { text: "Tanggal", value: "Tanggal", class: "text-center" },
                { text: "Jam", value: "Jam", class: "text-center" },
                { text: "Aktif", value: "Flag_Aktif", class: "text-center" },
                { text: "Aksi", value: "aksi", class: "text-center" },
            ];
        },

        formFields() {
            return [
                {
                    name: "Nama_Satuan",
                    label: "Nama Satuan",
                    type: "text",
                    placeholder: "Nama Satuan....",
                    icon: "bi bi-arrow-down-left-square",
                    col: "col-8",
                },
                {
                    name: "Simbol_Satuan",
                    label: "Simbol Satuan",
                    type: "text",
                    placeholder: "Simbol satuan....",
                    icon: "bi bi-arrow-down-left-square",
                    col: "col-4",
                },
            ];
        },

        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
    },
    mounted() {
        this.fetchListData();
    },
    methods: {
        async fetchListData(page = 1) {
            this.loading.listData = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    q: this.searchQuery,
                };
                const response = await axios.get("/api/v1/satuan/current", {
                    params,
                });

                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                    this.pagination.page = page;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                } else {
                    this.listData = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.listData = [];
                console.error("Failed to fetch list data:", error);
            } finally {
                this.loading.listData = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchListData(1);
        }, 500),
        async submitForm() {
            if (!this.form.Nama_Satuan || !this.form.Simbol_Satuan) {
                Swal.fire(
                    "Validasi Gagal",
                    "Semua field wajib diisi.",
                    "warning"
                );
                return;
            }

            const loadingKey = this.isEditing
                ? "editToDatabase"
                : "saveToDatabase";
            this.loading[loadingKey] = true;

            try {
                const url = this.isEditing
                    ? `/api/v1/satuan/update/${this.form.Id_Master_Satuan}`
                    : "/api/v1/satuan/store";

                const method = this.isEditing ? "put" : "post";

                const response = await axios[method](url, this.form);

                if (response.data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: response.data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    this.closeModal();
                    this.fetchListData(this.pagination.page);
                } else {
                    throw new Error(
                        response.data.message || "Gagal menyimpan data"
                    );
                }
            } catch (error) {
                Swal.fire(
                    "Error",
                    error?.response?.data?.message || "Terjadi kesalahan",
                    "error"
                );
            } finally {
                this.loading[loadingKey] = false;
            }
        },

        editData(item) {
            this.isEditing = true;
            this.form = { ...item, id: item.Id_Range_Score };
            this.showModal = true;
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchListData(page);
            }
        },
        openModal() {
            this.isEditing = false;
            this.resetForm();
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            this.form = {
                id: null,
                Nama_Satuan: "",
                Simbol_Satuan: "",
            };
            this.isEditing = false;
        },

        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

/* General Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white !important;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s;
}

.btn-modern:hover::before {
    left: 100%;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    min-height: 140px;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gradient-primary);
    opacity: 0.95;
}

.header-background::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.1"><circle cx="30" cy="30" r="2"/></g></svg>');
    animation: float 20s ease-in-out infinite;
}

.header-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    color: white;
}

.header-info {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 1.5rem;
}

.icon-container {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.icon-container i {
    font-size: 2.2rem;
    z-index: 2;
}

.title {
    font-size: 2rem;
    font-weight: 400;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: -0.025em;
}

.subtitle {
    text-align: start;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
    font-weight: 400;
}

.header-actions {
    height: 100%;
}

.date-range {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.date-item {
    text-align: center;
    color: white;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(20px);
}

.btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    color: var(--text);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Tombol Aksi di Tabel */
.btn-action {
    color: var(--secondary);
    background-color: transparent;
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}
.btn-action:hover {
    background-color: var(--surface-soft);
    color: var(--primary);
}

/* Search Box */
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-input {
    padding-left: 40px;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
}

/* Tombol di Modal (diasumsikan digunakan oleh komponen ModalV1) */
.btn-primary {
    background-color: var(--primary);
    border-color: var(--primary);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
}
.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}
.btn-outline-secondary {
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border-color: var(--border);
    color: var(--text-muted);
}
.btn-outline-secondary:hover {
    background-color: var(--surface-soft);
    color: var(--dark);
}

/* Media Queries */
@media (max-width: 1200px) {
    .modern-header {
        min-height: 120px;
    }
}

@media (max-width: 992px) {
    .header-info {
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
    }

    .icon-container {
        width: 80px;
        height: 80px;
        padding: 1rem;
    }
    .title {
        font-size: 1.5rem;
    }
    .subtitle {
        font-size: 1rem;
    }
}

@media (max-width: 768px) {
    .modern-header {
        min-height: 100px;
    }
    .header-content {
        padding: 1rem;
    }
    .header-info {
        gap: 0.75rem;
    }
    .icon-container {
        width: 50px;
        height: 50px;
    }
    .icon-container i {
        font-size: 1.5rem;
    }
    .title {
        font-size: 1.25rem;
    }
    .subtitle {
        font-size: 0.9rem;
    }
    .header-actions {
        flex-direction: row;
        justify-content: center;
        flex-wrap: wrap;
    }
    .date-range {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    .btn-modern {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 576px) {
    .header-content {
        padding: 1rem;
    }
    .title {
        font-size: 1.1rem;
    }
    .subtitle {
        font-size: 0.85rem;
    }
    .header-actions {
        flex-direction: column;
        width: 100%;
    }
    .date-range,
    .btn-modern {
        width: 100%;
        justify-content: center;
    }
}
</style>
