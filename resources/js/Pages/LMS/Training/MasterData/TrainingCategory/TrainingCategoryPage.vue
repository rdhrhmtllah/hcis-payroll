<template>
    <div class="container-fluid px-0">
        <HeaderKpi
            title="Kategori Training"
            subtitle="Kelola dan perbarui kategori training yang tersedia dalam sistem."
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
            buttonAction
            buttonText="Tambah Kategori"
            @button-clicked="openModal()"
        />
        <div class="mt-n4 mb-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="col-12 mb-3">
                        <div class="search-box">
                            <i
                                class="bi bi-search custom-T d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="search"
                                class="form-control search-input"
                                placeholder="Cari Result..."
                                v-model="searchQuery"
                                @input="handleSearch"
                            />
                        </div>
                    </div>

                    <TabelComponents
                        :items="listData"
                        :headers="tableHeaders"
                        :pagination="pagination"
                        :loading="loading.listData"
                        @change-page="changePage"
                    >
                        <template #cell(nomor)="{ index }">
                            {{
                                (pagination.current_page - 1) *
                                    pagination.per_page +
                                index +
                                1
                            }}
                        </template>

                        <template #cell(Kode_Kategori)="{ item }">
                            <span class="badge bg-light text-dark border">
                                {{ item.Kode_Kategori }}
                            </span>
                        </template>

                        <template #cell(Nama_Kategori)="{ item }">
                            {{ item.Nama_Kategori }}
                        </template>

                        <template #cell(aksi)="{ item }">
                            <div class="btn-group btn-group-sm" role="group">
                                <button
                                    @click="openModal(item)"
                                    class="btn btn-action"
                                    title="Edit Data"
                                >
                                    <i
                                        class="bi bi-pencil-square text-warning"
                                    ></i>
                                </button>
                            </div>
                        </template>
                    </TabelComponents>
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
            <div v-if="!isEditing" class="mb-3 d-flex justify-content-end">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    @click="addFormRow"
                >
                    <i class="bi bi-plus"></i> Tambah Baris Form
                </button>
            </div>

            <div
                v-for="(formData, index) in multipleForm"
                :key="index"
                class="mb-4 position-relative border p-3 rounded"
            >
                <div
                    v-if="!isEditing && multipleForm.length > 1"
                    class="position-absolute top-0 end-0 mt-2 me-2"
                >
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger border-0"
                        @click="removeFormRow(index)"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>

                <h6
                    v-if="!isEditing && multipleForm.length > 1"
                    class="mb-3 text-muted"
                >
                    Data ke-{{ index + 1 }}
                </h6>
                <FormV1 v-model="multipleForm[index]" :fields="formFields" />
            </div>
        </ModalV1>
    </div>
</template>

<script>
import HeaderKpi from "@/components/legacy/header.vue";
import ModalV1 from "@/components/legacy/modalV1.vue";
import TabelComponents from "../../../../LMS/components/ui/tableUi.vue";
import FormV1 from "@/components/legacy/formV1.vue";
import { ElMessage } from "element-plus";
import axios from "axios";
import { tanggalPanjang } from '@/utils/tanggal';

export default {
    name: "TrainingCategoryPage",
    components: {
        HeaderKpi,
        TabelComponents,
        ModalV1,
        FormV1,
    },
    data() {
        return {
            currentDate: new Date(),
            searchQuery: "",
            searchTimeout: null,
            listData: [],
            pagination: {
                current_page: 1,
                total_pages: 1,
                per_page: 10,
                total: 0,
            },
            loading: {
                listData: false,
                saveToDatabase: false,
                editToDatabase: false,
            },
            showModal: false,
            isEditing: false,
            multipleForm: [
                {
                    Id_Training_Kategori: null,
                    Kode_Kategori: "",
                    Nama_Kategori: "",
                },
            ],
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        modalTitle() {
            return this.isEditing
                ? "Edit Kategori Training"
                : "Tambah Kategori Training";
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
                    text: "Kode Kategori",
                    value: "Kode_Kategori",
                    class: "text-center",
                },
                {
                    text: "Nama Kategori",
                    value: "Nama_Kategori",
                    class: "text-center",
                },
                {
                    text: "Aksi",
                    value: "aksi",
                    class: "text-center",
                    style: "width: 10%;",
                },
            ];
        },
        formFields() {
            return [
                {
                    name: "Kode_Kategori",
                    label: "Kode Kategori",
                    type: "text",
                    placeholder: "Contoh: KAT-001",
                    icon: "bi bi-upc-scan",
                    col: "col-12",
                },
                {
                    name: "Nama_Kategori",
                    label: "Nama Kategori",
                    type: "text",
                    placeholder: "Masukkan Nama Kategori...",
                    icon: "bi bi-card-text",
                    col: "col-12",
                },
            ];
        },
    },
    mounted() {
        this.getDataCurrent(1);
    },
    methods: {
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        handleSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.getDataCurrent(1);
            }, 500);
        },
        changePage(page) {
            this.getDataCurrent(page);
        },
        async getDataCurrent(page = 1) {
            this.loading.listData = true;
            try {
                const response = await axios.get(
                    "/api/v1/training/kategori/current",
                    {
                        params: {
                            limit: this.pagination.per_page,
                            page: page,
                            q: this.searchQuery,
                        },
                    },
                );

                if (response.data.success) {
                    this.listData = response.data.result;
                    this.pagination = {
                        current_page:
                            response.data.page ||
                            response.data.pagination?.current_page ||
                            1,
                        total_pages:
                            response.data.total_page ||
                            response.data.pagination?.total_pages ||
                            1,
                        per_page: this.pagination.per_page,
                        total:
                            response.data.total_data ||
                            response.data.pagination?.total ||
                            0,
                    };
                }
            } catch (error) {
                if (error.response?.status === 404) {
                    this.listData = [];
                    this.pagination.total = 0;
                    this.pagination.total_pages = 1;
                    this.pagination.current_page = 1;
                } else {
                    ElMessage.error(
                        error.response?.data?.message ||
                            "Terjadi kesalahan sistem",
                    );
                }
            } finally {
                this.loading.listData = false;
            }
        },
        addFormRow() {
            this.multipleForm.push({
                Id_Training_Kategori: null,
                Kode_Kategori: "",
                Nama_Kategori: "",
            });
        },
        removeFormRow(index) {
            if (this.multipleForm.length > 1) {
                this.multipleForm.splice(index, 1);
            }
        },
        openModal(item = null) {
            if (item) {
                this.isEditing = true;
                this.multipleForm = [
                    {
                        Id_Training_Kategori: item.Id_Training_Kategori,
                        Kode_Kategori: item.Kode_Kategori,
                        Nama_Kategori: item.Nama_Kategori,
                    },
                ];
            } else {
                this.isEditing = false;
                this.multipleForm = [
                    {
                        Id_Training_Kategori: null,
                        Kode_Kategori: "",
                        Nama_Kategori: "",
                    },
                ];
            }
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            setTimeout(() => {
                this.isEditing = false;
                this.multipleForm = [
                    {
                        Id_Training_Kategori: null,
                        Kode_Kategori: "",
                        Nama_Kategori: "",
                    },
                ];
            }, 300);
        },
        async submitForm() {
            const isValid = this.multipleForm.every(
                (form) =>
                    form.Kode_Kategori.trim() !== "" &&
                    form.Nama_Kategori.trim() !== "",
            );

            if (!isValid) {
                ElMessage.warning(
                    "Harap lengkapi semua form yang wajib diisi pada setiap baris!",
                );
                return;
            }

            if (this.isEditing) {
                this.loading.editToDatabase = true;
                try {
                    const singleData = this.multipleForm[0];
                    const response = await axios.put(
                        `/api/v1/training/kategori/update/${singleData.Id_Training_Kategori}`,
                        {
                            Kode_Kategori: singleData.Kode_Kategori,
                            Nama_Kategori: singleData.Nama_Kategori,
                        },
                    );

                    if (response.data.success) {
                        ElMessage.success(response.data.message);
                        this.closeModal();
                        this.getDataCurrent(this.pagination.current_page);
                    }
                } catch (error) {
                    ElMessage.error(
                        error.response?.data?.message ||
                            "Terjadi kesalahan saat mengupdate data",
                    );
                } finally {
                    this.loading.editToDatabase = false;
                }
            } else {
                this.loading.saveToDatabase = true;
                try {
                    const payload = {
                        data: this.multipleForm.map((form) => ({
                            Kode_Kategori: form.Kode_Kategori,
                            Nama_Kategori: form.Nama_Kategori,
                        })),
                    };
                    const response = await axios.post(
                        "/api/v1/training/kategori/store",
                        payload,
                    );

                    if (response.data.success) {
                        ElMessage.success(response.data.message);
                        this.closeModal();
                        this.getDataCurrent(1);
                    }
                } catch (error) {
                    ElMessage.error(
                        error.response?.data?.message ||
                            "Terjadi kesalahan saat menyimpan data",
                    );
                } finally {
                    this.loading.saveToDatabase = false;
                }
            }
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
    background: var(--gradient-primary);
    opacity: 0.95;
    display: flex;
    justify-content: center;
    align-items: center;
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
