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
                                            class="bi bi-bullseye d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Target KPI
                                        </h2>
                                        <p class="subtitle">
                                            Kelola KPI bulanan, triwulanan, dan
                                            tahunan untuk memantau pencapaian
                                            kinerja
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
                                            >Tambah Target KPI</span
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

                        <ListAccordionV1
                            v-if="!loading.listData"
                            accordionId="jadwalAccordion"
                            itemKey="_key"
                            :items="
                                listData.map((jadwal, index) => ({
                                    ...jadwal,
                                    _key: jadwal.Id_Master_Penjadwalan || index,
                                }))
                            "
                            :pagination="pagination"
                            :loading="loading.listData"
                            @change-page="changePage"
                        >
                            <template #header="{ item: jadwal }">
                                <div class="schedule-header">
                                    <div class="schedule-info">
                                        <div
                                            class="schedule-icon"
                                            :class="getScheduleIcon().bgClass"
                                        >
                                            <i
                                                :class="
                                                    getScheduleIcon().iconClass
                                                "
                                                class="mt-customFransIcon"
                                            ></i>
                                        </div>
                                        <div>
                                            <div class="schedule-name">
                                                {{ jadwal.Nama_Target }}
                                            </div>
                                            <div class="schedule-details">
                                                Dibuat:
                                                {{
                                                    formatDateString(
                                                        jadwal.Tanggal
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template #body="{ item: jadwal }">
                                <div class="schedule-body">
                                    <div class="schedule-description">
                                        <h6 class="detail-title">
                                            <i class="bi bi-card-text"></i>
                                            Deskripsi
                                        </h6>
                                        <p>{{ jadwal.Deskripsi }}</p>
                                    </div>

                                    <div class="schedule-actions">
                                        <button
                                            @click="editData(jadwal)"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            <i
                                                class="bi bi-pencil-square me-2"
                                            ></i>
                                            Edit Target KPI
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </ListAccordionV1>
                        <div v-else>
                            <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
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
                    <p class="title-evopalembang">Panduan Kode Target</p>
                    <p class="subtitle-evopalembang">
                        Gunakan kode berikut untuk menentukan periode target
                        performa:
                    </p>
                    <ul class="list-evopalembang">
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-et"
                                >Y</span
                            >
                            (Yearly) : Target tahunan
                        </li>
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-gd"
                                >M</span
                            >
                            (Monthly) : Target bulanan
                        </li>
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-sy"
                                >Q</span
                            >
                            (Quarterly) : Target per kuartal
                        </li>
                        <li>
                            <span
                                class="code-badge-evopalembang evopalembang-code-np"
                                >S</span
                            >
                            (Semiannual / Year to Date) : Target setiap 6 bulan
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
import { HcisSkeleton } from '@/components/ui';
import FormV2 from "../../components/form/formV2.vue";
import { ElNotification } from "element-plus";
import { tanggalPanjang, tanggalWaktu } from '@/utils/tanggal';

export default {
    components: {
        ListAccordionV1,
        ModalV1,
        FormV2,
        HcisSkeleton,
    },
    data() {
        return {
            searchQuery: "",
            showModal: false,
            isEditing: false,
            currentDate: new Date(),
            listData: [],
            formSingle: {
                Id_Master_Target: null,
                Nama_Target: "",
                Kode_Target: "",
                Deskripsi: "",
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
            return this.isEditing ? "Edit Target KPI" : "Tambah Target KPI";
        },
        modalSubmitText() {
            return this.isEditing ? "Simpan Perubahan" : "Simpan";
        },

        formFields() {
            return [
                {
                    name: "Kode_Target",
                    label: "Kode Target",
                    type: "text",
                    placeholder: "Contoh: Y (Yearly)",
                    icon: "bi bi-type",
                    col: "col-12",
                    maxlength: 1,
                    disabled: this.isEditing ? true : false,
                    filter: (val) => val.substring(0, 1),
                },
                {
                    name: "Nama_Target",
                    label: "Target",
                    type: "text",
                    placeholder: "Yearly",
                    icon: "bi bi-bullseye",
                    col: "col-12",
                },

                {
                    name: "Deskripsi",
                    label: "Deskripsi Target",
                    type: "textarea",
                    placeholder: "Tuliskan deskripsi Target...",
                    icon: "bi bi-card-text",
                    col: "col-12",
                    rows: 3,
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
                const response = await axios.get("/api/v1/target-kpi/current", {
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
            if (this.isEditing) {
                const form = this.formSingle;
                if (!form.Nama_Target || !form.Deskripsi) {
                    ElNotification({
                        title: "Error",
                        message: "Semua field wajib diisi.",
                        type: "error",
                    });
                    return;
                }
                this.loading.editToDatabase = true;
                try {
                    const url = `/api/v1/target-kpi/update/${this.formSingle.Id_Master_Target}`;
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
                        !form.Kode_Target ||
                        !form.Nama_Target ||
                        !form.Deskripsi
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
                    const url = "/api/v1/target-kpi/store";
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
                Kode_Target: "",
                Nama_Target: "",
                Deskripsi: "",
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
                Id_Master_Target: item.Id_Master_Target,
                Nama_Target: item.Nama_Target,
                Deskripsi: item.Deskripsi,
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
                Id_Master_Target: null,
                Nama_Target: "",
                Kode_Target: "",
                Deskripsi: "",
            };

            this.forms = [
                {
                    Nama_Target: "",
                    Kode_Target: "",
                    Deskripsi: "",
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

.alert-evopalembang {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    background-color: #e7f3fe;
    border-left: 5px solid #2196f3;
    border-radius: 8px;
    color: #0d47a1;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
        "Helvetica Neue", Arial, sans-serif;
}

.alert-evopalembang .icon-evopalembang {
    font-size: 1.5rem;
    margin-right: 1rem;
    color: #2196f3;
    line-height: 1.2;
}

.alert-evopalembang .content-evopalembang {
    flex-grow: 1;
}

.alert-evopalembang .title-evopalembang {
    font-weight: 600;
    font-size: 1rem;
    margin: 0 0 0.25rem 0;
}

.alert-evopalembang .subtitle-evopalembang {
    font-size: 0.9rem;
    margin: 0;
}

.alert-evopalembang .list-evopalembang {
    list-style: none;
    padding-left: 0;
    margin: 0.75rem 0 0 0;
    font-size: 0.85rem;
}

.alert-evopalembang .list-evopalembang li {
    margin-bottom: 0.4rem;
}

.alert-evopalembang .code-badge-evopalembang {
    display: inline-block;
    padding: 0.2em 0.6em;
    border-radius: 4px;
    font-weight: 700;
    text-align: center;
    min-width: 50px;
    margin-right: 10px;
}
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
