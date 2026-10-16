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
                                            class="bi bi-diagram-3 d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Manajemen Akses Level
                                        </h2>
                                        <p class="subtitle">
                                            Atur dan kelola Akses Level yang
                                            digunakan dalam KPI.
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
                                            >Tambah Akses Level</span
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
                                                <el-tag type="primary">
                                                    {{
                                                        jadwal.Username
                                                    }}</el-tag
                                                >
                                            </div>
                                            <div class="schedule-name">
                                                Rumus: {{ jadwal.Nama_Lengkap }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template #body="{ item: jadwal }">
                                <div class="unit-container">
                                    <div class="section-title">
                                        <i
                                            class="bi bi-tags-fill me-2 mt-customIconAccordion"
                                        ></i>
                                        <h6>Level yang Digunakan</h6>
                                    </div>

                                    <div
                                        v-if="
                                            jadwal.satuan &&
                                            jadwal.satuan.length > 0
                                        "
                                        class="unit-list"
                                    >
                                        <div
                                            v-for="satuan in jadwal.satuan"
                                            :key="satuan.Id_User_Akses_Level"
                                            class="unit-card"
                                        >
                                            <div class="unit-info">
                                                <span class="unit-name">{{
                                                    satuan.nama_level
                                                }}</span>
                                            </div>

                                            <div class="unit-actions">
                                                <button
                                                    @click="editData(satuan)"
                                                    class="btn btn-icon btn-light"
                                                    title="Edit Satuan"
                                                >
                                                    <i
                                                        class="bi bi-pencil-square"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="empty-state">
                                        <p>
                                            Belum ada satuan yang dikonfigurasi.
                                        </p>
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
            <FormV2 v-model="form" :fields="formFields" />
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
import { ElNotification, ElTag } from "element-plus";
import { tanggalPanjang, tanggalWaktu } from '@/utils/tanggal';

export default {
    components: {
        ListAccordionV1,
        ModalV1,
        FormV2,
        HcisSkeleton,
        ElTag,
    },
    data() {
        return {
            searchQuery: "",
            showModal: false,
            isEditing: false,
            currentDate: new Date(),
            listData: [],
            listDataUsers: [],
            listDataLevel: [],
            form: {
                Id_Master_Satuan_Formula: null,
                Id_Master_Formula: "",
                Id_Master_Satuan: [],
            },
            loading: {
                listData: true,
                saveToDatabase: false,
                editToDatabase: false,
                listDataUsers: false,
                listDataLevel: false,
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
                ? "Edit User Akses Level"
                : "Tambah User Akses Level";
        },
        modalSubmitText() {
            return this.isEditing ? "Simpan Perubahan" : "Simpan";
        },
        formFields() {
            return [
                {
                    name: "Id_User",
                    label: "Pengguna",
                    type: "select",
                    placeholder: "Pilih Pengguna",
                    options: this.listDataUsers,
                    col: "col-12",
                    disabled: this.isEditing,
                },
                {
                    name: "Id_Level",
                    label: "Level",
                    type: "select",
                    placeholder: "Pilih Level",
                    multiple: !this.isEditing,
                    options: this.listDataLevel,
                    col: "col-12",
                },
            ];
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
    },
    async mounted() {
        await this.fetchListData();
        await this.fetchListUsers();
        await this.fetchListLevel();
    },
    methods: {
        async fetchListUsers() {
            this.loading.listDataUsers = true;
            try {
                const response = await axios.get(
                    "/dashboard-ceo/option/list-user"
                );
                if (response.status === 200 && response.data?.result) {
                    this.listDataUsers = response.data.result.map((item) => ({
                        value: item.Id_Users,
                        label: `${item.Username} (${item.Nama})`,
                    }));
                }
            } catch (error) {
                console.error("Gagal mengambil data formula:", error);
                this.listDataUsers = [];
            } finally {
                this.loading.listDataUsers = false;
            }
        },
        async fetchListLevel() {
            this.loading.listDataLevel = true;
            try {
                const response = await axios.get(
                    "/akses-user/level/list-level"
                );
                if (response.status === 200 && response.data?.result) {
                    const divisiList = response.data.result.map((item) => ({
                        value: item.ID_Level,
                        label: `${item.nama_level}`,
                    }));
                    this.listDataLevel = [
                        { value: "ALL", label: "🌐 Semua Level" },
                        ...divisiList,
                    ];
                }
            } catch (error) {
                console.error("Gagal mengambil data satuan:", error);
                this.listDataLevel = [];
            } finally {
                this.loading.listDataLevel = false;
            }
        },
        async fetchListData(page = 1) {
            this.loading.listData = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    q: this.searchQuery,
                };
                const response = await axios.get("/akses-user/level/current", {
                    params,
                });
                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                    this.pagination.page = page;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                }
            } catch (error) {
                console.error("Gagal mengambil list data:", error);
                this.listData = [];
            } finally {
                this.loading.listData = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchListData(1);
        }, 500),
        async submitForm() {
            if (this.isEditing) {
                await this.updateData();
            } else {
                await this.saveData();
            }
        },
        async saveData() {
            this.loading.saveToDatabase = true;
            try {
                const response = await axios.post(
                    "/akses-user/level/store",
                    this.form
                );
                if (response.data.success) {
                    ElNotification({
                        title: "Sukses",
                        message: response.data.message,
                        type: "success",
                    });
                    this.closeModal();
                    await this.fetchListData(1);
                }
            } catch (error) {
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message || "Gagal menyimpan data",
                    type: "error",
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        async updateData() {
            this.loading.editToDatabase = true;
            try {
                const url = `/api/v1/satuan-formula/update/${this.form.Id_Master_Satuan_Formula}`;
                const response = await axios.put(url, this.form);
                if (response.data.success) {
                    ElNotification({
                        title: "Sukses",
                        message: response.data.message,
                        type: "success",
                    });
                    this.closeModal();
                    await this.fetchListData(this.pagination.page);
                }
            } catch (error) {
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memperbarui data",
                    type: "error",
                });
            } finally {
                this.loading.editToDatabase = false;
            }
        },
        editData(satuan) {
            this.isEditing = true;
            this.form = {
                Id_User_Akses_Level: satuan.Id_User_Akses_Level,
                Id_Level: satuan.Id_Level,
                Id_User: satuan.Id_User,
            };
            this.showModal = true;
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
                Id_User_Akses_Divisi: null,
                Id_Level: [],
                Id_User: null,
            };
            this.isEditing = false;
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchListData(page);
            }
        },
        formatDateTimeString(dateString) {
            return tanggalWaktu(dateString);
        },
        getScheduleIcon() {
            return {
                iconClass: "bi bi-diagram-3",
                bgClass: "bg-info-soft",
            };
        },
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        "form.Id_Level"(newVal) {
            if (newVal && newVal.includes("ALL")) {
                const allLevelValues = this.listDataLevel
                    .filter((d) => d.value !== "ALL")
                    .map((d) => d.value);
                this.form.Id_Level = allLevelValues;
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

/* Container Utama */
.unit-container {
    padding: 1rem;
}

.section-title {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    color: #333;
    font-weight: 600;
}

.section-title i {
    color: var(
        --bs-primary,
        #0d6efd
    ); /* Menggunakan variabel Bootstrap jika ada */
}

/* Layout Card untuk setiap item */
.unit-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    margin-bottom: 0.75rem;
    transition: box-shadow 0.2s ease-in-out, transform 0.2s ease;
}

.unit-card:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

/* Informasi Satuan */
.unit-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.unit-name {
    font-weight: 600;
    font-size: 1.05rem;
    color: #212529;
}

.unit-symbol {
    background-color: #f1f3f5;
    color: #495057;
    padding: 0.25rem 0.6rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Grup Aksi */
.unit-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

/* Styling untuk toggle switch bawaan Bootstrap 5 */
.form-check-input {
    cursor: pointer;
    width: 2.5em;
    height: 1.25em;
}

.form-check-input:checked {
    background-color: var(--bs-success, #198754);
    border-color: var(--bs-success, #198754);
}

.form-check-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.form-check-input:checked + .form-check-label {
    color: #212529;
}

/* Tombol ikon agar lebih minimalis */
.btn-icon {
    background: transparent;
    border: none;
    color: #6c757d;
    font-size: 1.2rem;
    transition: color 0.2s ease;
}

.btn-icon:hover {
    color: var(--bs-primary, #0d6efd);
}

/* Pesan jika data kosong */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
    background-color: #f8f9fa;
    border-radius: 0.75rem;
}

/* -- LAYOUT AKSI MODERN -- */
.schedule-actions-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--hcis-legacy-bootstrap-gray);
}

.action-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.action-label {
    font-weight: 600;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

/* -- SWITCH TOGGLE MODERN -- */
.switch-modern {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
    cursor: pointer;
}

.switch-modern input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #dc3545; /* DIUBAH: Dari abu-abu menjadi merah */
    border-radius: 34px;
    transition: 0.4s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    border-radius: 50%;
    transition: 0.4s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

input:checked + .slider {
    background-color: #198754; /* DIUBAH: Dari biru menjadi hijau */
}

input:focus + .slider {
    box-shadow: 0 0 1px #198754; /* DIUBAH: Warna focus disamakan hijau */
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Bagian ikon tidak perlu diubah, sudah cocok */
.slider::after {
    font-family: "bootstrap-icons";
    content: "\F62A"; /* Ikon 'x-lg' */
    position: absolute;
    right: 9px;
    top: 7px;
    color: white;
    font-size: 16px;
    transition: opacity 0.4s ease;
    opacity: 1;
}

input:checked + .slider::after {
    font-family: "bootstrap-icons";
    content: "\F26E"; /* Ikon 'check-lg' */
    left: 9px;
    opacity: 1;
}

input:not(:checked) + .slider::after {
    right: 9px;
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
