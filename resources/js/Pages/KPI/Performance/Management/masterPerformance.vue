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
                                            class="bi bi-speedometer2 d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Performance Range
                                        </h2>
                                        <p class="subtitle">
                                            Penilaian kinerja berdasarkan skor
                                        </p>
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
                                            >Tambah Performance Range</span
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
                                    placeholder="Cari..."
                                    v-model="searchQuery"
                                />
                            </div>
                        </div>

                        <TabelUiV1
                            v-if="!loading.listData"
                            :items="listData"
                            :headers="tableHeaders"
                            :pagination="pagination"
                            :loading="loading.listData"
                            @change-page="changePage"
                        >
                            <template #cell(nomor)="{ index }">
                                {{
                                    (pagination.page - 1) * pagination.limit +
                                    index +
                                    1
                                }}
                            </template>

                            <template #cell(Warna)="{ item }">
                                <div class="d-flex justify-content-center">
                                    <div
                                        style="
                                            width: 25px;
                                            height: 25px;
                                            border-radius: 50%;
                                            border: 1px solid #e0e0e0;
                                        "
                                        :style="{ backgroundColor: item.Warna }"
                                    ></div>
                                </div>
                            </template>

                            <template #cell(Range_Awal)="{ item }">
                                {{ item.Range_Awal }}
                            </template>

                            <template #cell(Range_Akhir)="{ item }">
                                {{ item.Range_Akhir }}
                            </template>

                            <template #cell(Keterangan)="{ item }">
                                <span
                                    class="badge fw-semibold px-3 py-2"
                                    :style="{
                                        backgroundColor: item.Warna,
                                        color: '#fff',
                                    }"
                                >
                                    {{ item.Keterangan }}
                                </span>
                            </template>

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
                        <div v-else>
                            <SkeletonTable />
                        </div>
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
            <div class="alert-evopalembang mb-4">
                <div class="icon-evopalembang">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="content-evopalembang">
                    <p class="title-evopalembang">Panduan Kode Performa</p>
                    <p class="subtitle-evopalembang">
                        Gunakan skor untuk menentukan kode performa karyawan.
                        Ini panduannya:
                    </p>
                    <ul class="list-evopalembang">
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-et"
                                >ET</span
                            >
                            (Excellent) : Skor >= 100
                        </li>
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-gd"
                                >GD</span
                            >
                            (Good) : Skor 90-99
                        </li>
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-sy"
                                >SY</span
                            >
                            (Satisfactory) : Skor 75-89
                        </li>
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-np"
                                >NP</span
                            >
                            (Not Perform) : <span>Skor &lt; 75</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div v-if="!isEditing">
                <div
                    v-for="(formItem, index) in forms"
                    :key="index"
                    class="mb-3 p-3 border rounded position-relative"
                >
                    <button
                        v-if="forms.length > 1"
                        @click="removeFormRow(index)"
                        class="btn btn-sm btn-danger position-absolute"
                        style="top: 10px; right: 10px; z-index: 10"
                    >
                        <i class="bi bi-trash"></i>
                    </button>

                    <FormV2 v-model="forms[index]" :fields="formFields" />
                </div>
                <div class="d-grid">
                    <button @click="addFormRow" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle-dotted"></i> Tambah Baris
                    </button>
                </div>
            </div>
            <div v-else>
                <FormV2 v-model="formSingle" :fields="formFields" />
            </div>
        </ModalV1>
    </div>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";
import ListAccordionV1 from "@/components/legacy/listAccordionUiV1.vue";
import ModalV1 from "@/components/legacy/modalV1.vue";
import SkeletonTable from "../../components/ui/skeletonTable.vue";
import FormV2 from "../../components/form/formV2.vue";
import { ElNotification } from "element-plus";
import TabelUiV1 from "../../components/ui/TabelUiV1.vue";
import { tanggalPanjang, tanggalWaktu } from '@/utils/tanggal';

export default {
    components: {
        ListAccordionV1,
        ModalV1,
        FormV2,
        SkeletonTable,
        TabelUiV1,
    },
    data() {
        return {
            searchQuery: "",
            showModal: false,
            isEditing: false,
            currentDate: new Date(),
            listData: [],
            formSingle: {
                Id_Master_Performance: null,
                Range_Awal: "",
                Range_Akhir: "",
                Keterangan: "",
                Warna: "",
            },
            forms: [],
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
            return this.isEditing
                ? "Edit Performance Range KPI"
                : "Tambah Performance Range KPI";
        },
        modalSubmitText() {
            return this.isEditing ? "Simpan Perubahan" : "Simpan";
        },

        tableHeaders() {
            return [
                {
                    text: "No",
                    value: "nomor",
                    class: "text-center",
                    style: "width: 5%;",
                },
                { text: "Warna", value: "Warna", class: "text-center" },
                {
                    text: "Range Awal",
                    value: "Range_Awal",
                    class: "text-center",
                },
                {
                    text: "Range Akhir",
                    value: "Range_Akhir",
                    class: "text-center",
                },
                {
                    text: "Keterangan",
                    value: "Keterangan",
                    class: "text-center",
                },

                { text: "Aksi", value: "aksi", class: "text-center" },
            ];
        },
        formFields() {
            return [
                {
                    name: "Kode",
                    label: "Kode Performance",
                    type: "text",
                    placeholder: "Masukkan Kode",
                    icon: "bi bi-arrow-down-left-circle",
                    col: "col-6",
                    disabled: this.isEditing ? true : false,
                },
                {
                    name: "Range_Awal",
                    label: "Range Awal",
                    type: "text",
                    placeholder: "Masukkan angka awal",
                    icon: "bi bi-arrow-down-left-circle",
                    col: "col-6",
                    filter: (val) => val.replace(/[^0-9]/g, ""),
                },
                {
                    name: "Range_Akhir",
                    label: "Range Akhir",
                    type: "text",
                    placeholder: "Masukkan angka akhir",
                    icon: "bi bi-arrow-up-right-circle",
                    col: "col-6",
                    filter: (val) => val.replace(/[^0-9]/g, ""),
                },
                {
                    name: "Warna",
                    label: "Warna",
                    type: "color",
                    icon: "bi bi-palette",
                    predefine: [
                        "#ff4500",
                        "#ff8c00",
                        "#ffd700",
                        "#90ee90",
                        "#00ced1",
                        "#1e90ff",
                        "#c71585",
                        "rgba(255, 69, 0, 0.68)",
                    ],
                    col: "col-6",
                },
                {
                    name: "Keterangan",
                    label: "Keterangan",
                    type: "text",
                    placeholder: "Tuliskan keterangan...",
                    icon: "bi bi-card-text",
                    col: "col-12",
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
        this.resetForm();
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
                const response = await axios.get(
                    "/api/v1/performance-kpi/current",
                    {
                        params,
                    }
                );

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
            if (this.isEditing) {
                const form = this.formSingle;
                if (
                    form.Range_Awal === null ||
                    form.Range_Awal === undefined ||
                    !form.Range_Akhir ||
                    !form.Keterangan ||
                    !form.Warna
                ) {
                    ElNotification({
                        title: "Error",
                        message: "Semua field wajib diisi.",
                        type: "error",
                    });
                    return;
                }
                this.loading.editToDatabase = true;
                try {
                    const url = `/api/v1/performance-kpi/update/${this.formSingle.Id_Master_Performance}`;
                    const response = await axios.put(url, this.formSingle);
                    this.handleSuccess(response);
                } catch (error) {
                    this.handleError(error);
                } finally {
                    this.loading.editToDatabase = false;
                }
            } else {
                for (const form of this.forms) {
                    if (
                        form.Range_Awal === null ||
                        form.Range_Awal === undefined ||
                        !form.Range_Akhir ||
                        !form.Keterangan ||
                        !form.Warna
                    ) {
                        ElNotification({
                            title: "Error",
                            message: "Semua field wajib diisi.",
                            type: "error",
                        });
                        return;
                    }
                }
                this.loading.saveToDatabase = true;
                try {
                    const url = "/api/v1/performance-kpi/store";
                    const response = await axios.post(url, this.forms);
                    this.handleSuccess(response);
                } catch (error) {
                    this.handleError(error);
                } finally {
                    this.loading.saveToDatabase = false;
                }
            }
        },

        handleSuccess(response) {
            if (response.data.success) {
                ElNotification({
                    title: "Sukses",
                    message: response.data.message || "Data berhasil disimpan!",
                    type: "success",
                });
                this.closeModal();
                this.fetchListData(this.pagination.page);
            } else {
                throw new Error(
                    response.data.message || "Gagal menyimpan data"
                );
            }
        },
        handleError(error) {
            console.error("Error during form submission:", error);
            ElNotification({
                title: "Error",
                message:
                    error.response?.data?.message ||
                    "Terjadi kesalahan saat menyimpan.",
                type: "error",
            });
        },
        addFormRow() {
            this.forms.push({
                Range_Awal: "",
                Range_Akhir: "",
                Keterangan: "",
                Warna: "",
            });
        },

        removeFormRow(index) {
            if (this.forms.length > 1) {
                this.forms.splice(index, 1);
            }
        },
        editData(item) {
            this.isEditing = true;

            this.formSingle = {
                Id_Master_Performance: item.Id_Master_Performance,
                Range_Awal: item.Range_Awal,
                Kode: item.Kode,
                Range_Akhir: item.Range_Akhir,
                Keterangan: item.Keterangan,
                Warna: item.Warna,
            };

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
            this.formSingle = {
                Id_Master_Performance: null,
                Range_Awal: "",
                Range_Akhir: "",
                Keterangan: "",
                Warna: "",
            };

            this.forms = [
                {
                    Range_Awal: "",
                    Range_Akhir: "",
                    Keterangan: "",
                    Warna: "",
                },
            ];
            this.isEditing = false;
        },

        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        formatDateTimeString(dateString) {
            return tanggalWaktu(dateString);
        },
        getScheduleIcon() {
            return {
                iconClass: "bi bi-bullseye",
                bgClass: "bg-info-soft",
            };
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

/* Header & Status Badge (Tidak berubah dari sebelumnya) */
.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 0.5rem 0;
}
.schedule-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.schedule-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.schedule-icon.bg-primary-soft {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
}
.schedule-icon.bg-warning-soft {
    background-color: var(--hcis-amber-50);
    color: var(--hcis-amber-500);
}
.schedule-icon.bg-success-soft {
    background-color: var(--hcis-green-50);
    color: var(--hcis-emerald-500);
}
.schedule-icon.bg-info-soft {
    background-color: var(--hcis-cyan-50);
    color: var(--hcis-cyan-500);
}
.schedule-icon.bg-secondary-soft {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}

.schedule-name {
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.schedule-details {
    font-size: 0.85rem;
    color: var(--hcis-slate-500);
}

.badge-status-active,
.badge-status-inactive {
    display: inline-flex;
    align-items: center;
    padding: 0.35em 0.8em;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 9999px;
}
.badge-status-active {
    background-color: var(--hcis-green-100);
    color: #166534;
}
.badge-status-inactive {
    background-color: var(--hcis-red-100);
    color: var(--hcis-red-800);
}
.badge-status-active i,
.badge-status-inactive i {
    margin-right: 0.4rem;
}

/* Body & Aksi (Ada Perubahan) */
.schedule-body {
    padding: 1.25rem;
    border-top: 1px solid var(--hcis-slate-200);
    display: grid;
    gap: 1.5rem;
    background-color: var(--hcis-slate-50);
}
.detail-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.schedule-description p {
    color: var(--hcis-slate-700);
    margin-bottom: 0;
    font-size: 0.95rem;
}
.schedule-timing {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.timing-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.timing-item i {
    font-size: 1.25rem;
}
.timing-item strong {
    color: var(--hcis-slate-600);
}
.timing-item span {
    margin-left: 0.5rem;
    color: var(--hcis-slate-500);
    font-family: monospace;
    font-size: 0.9rem;
}
.flags-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.flag-item {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease-in-out;
    border: 1px solid var(--hcis-slate-200);
}
.flag-item.active {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-weight: 600;
    border-color: var(--hcis-indigo-200);
}
.flag-item.active i {
    color: var(--hcis-indigo-500);
}

.schedule-actions {
    margin-top: 0.5rem;
    padding-top: 1.5rem;
    border-top: 1px dashed var(--hcis-slate-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.action-toggle.form-switch {
    padding-left: 3.5em; /* Memberi ruang untuk label */
}
.action-toggle .form-check-input {
    width: 3em;
    height: 1.5em;
    cursor: pointer;
}
.action-toggle .form-check-input:checked {
    background-color: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
}
.action-toggle .form-check-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--hcis-slate-600);
    cursor: pointer;
}
/* end according */

.mt-customFransIcon {
    margin-top: -10px;
    margin-left: -10px;
}

.mt-customIconAccordion {
    margin-top: -14px;
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

/* Kontainer utama alert */
.alert-evopalembang {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    background-color: #e7f3fe;
    /* Warna biru yang sangat soft */
    border-left: 5px solid #2196f3;
    /* Aksen garis biru di kiri */
    border-radius: 8px;
    color: #0d47a1;
    /* Warna teks biru tua agar kontras */
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
        "Helvetica Neue", Arial, sans-serif;
}

/* Styling untuk ikon di sebelah kiri */
.alert-evopalembang .icon-evopalembang {
    font-size: 1.5rem;
    /* Ukuran ikon */
    margin-right: 1rem;
    color: #2196f3;
    /* Warna ikon sama dengan border */
    line-height: 1.2;
}

/* Kontainer untuk semua teks */
.alert-evopalembang .content-evopalembang {
    flex-grow: 1;
}

/* Judul alert */
.alert-evopalembang .title-evopalembang {
    font-weight: 600;
    font-size: 1rem;
    margin: 0 0 0.25rem 0;
}

/* Sub-judul atau deskripsi */
.alert-evopalembang .subtitle-evopalembang {
    font-size: 0.9rem;
    margin: 0;
}

/* List untuk panduan kode */
.alert-evopalembang .list-evopalembang {
    list-style: none;
    padding-left: 0;
    margin: 0.75rem 0 0 0;
    font-size: 0.85rem;
}

.alert-evopalembang .list-evopalembang li {
    margin-bottom: 0.4rem;
}

/* Styling untuk badge/label kode (ET, GD, dll) */
.alert-evopalembang .code-badge-evopalembang {
    display: inline-block;
    padding: 0.2em 0.6em;
    border-radius: 4px;
    font-weight: 700;
    text-align: center;
    min-width: 50px;
    margin-right: 10px;
}

/* Varian warna untuk setiap kode performa */
.evopalembang-code-et {
    background-color: #d4edda;
    color: #155724;
}

.evopalembang-code-gd {
    background-color: #cce5ff;
    color: #004085;
}

.evopalembang-code-sy {
    background-color: #fff3cd;
    color: #856404;
}

.evopalembang-code-np {
    background-color: var(--hcis-legacy-bootstrap-danger);
    color: #721c24;
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
