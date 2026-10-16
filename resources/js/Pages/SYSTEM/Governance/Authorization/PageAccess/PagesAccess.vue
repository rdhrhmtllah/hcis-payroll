<template>
    <div class="container-fluid px-0 fade-in">
        <!-- Header -->
        <HeaderKpi
            title="Manajemen Akses Halaman"
            subtitle="Kelola hak akses karyawan untuk setiap halaman dalam sistem"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />
        <BannerAksesPeran :modul-baru="modulBaru" :tautan="tautanAksesPeran" />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-md-12">
                <ApprovalTabs
                    :tabs="accessTabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab 1: Daftar Akses Karyawan -->
                    <template #list_access>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-12">
                                        <div class="search-box">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-search"
                                            ></i>
                                            <input
                                                type="text"
                                                v-model="filters.search"
                                                placeholder="Cari karyawan berdasarkan nama atau kode..."
                                                class="search-input"
                                                @input="applyFilters"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-if="loading.employees" class="loading-state">
                                <div class="loading-spinner">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-arrow-repeat"
                                    ></i>
                                </div>
                                <h4 class="loading-title">
                                    Memuat Data Karyawan...
                                </h4>
                                <p class="loading-description">
                                    Harap tunggu sebentar
                                </p>
                            </div>

                            <!-- Employee Access List -->
                            <div
                                v-else-if="paginatedEmployees.length > 0"
                                class="employee-access-list"
                            >
                                <div class="table-responsive">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th>Nama Karyawan</th>
                                                <th>Divisi</th>
                                                <th>Level</th>
                                                <th>Jumlah Akses</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="employee in paginatedEmployees"
                                                :key="employee.id"
                                            >
                                                <td>
                                                    <div
                                                        class="employee-info-cell"
                                                    >
                                                        <div class="avatar-sm">
                                                            {{
                                                                getInitials(
                                                                    employee.nama
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="employee-details"
                                                        >
                                                            <h6
                                                                class="employee-name"
                                                            >
                                                                {{
                                                                    employee.nama
                                                                }}
                                                            </h6>
                                                            <p
                                                                class="employee-code"
                                                            >
                                                                {{
                                                                    employee.kode
                                                                }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ employee.divisi }}</td>
                                                <td>{{ employee.level }}</td>
                                                <td>
                                                    <span
                                                        class="badge-access-count"
                                                    >
                                                        {{
                                                            employee.page_access
                                                                .length
                                                        }}
                                                        Halaman
                                                    </span>
                                                </td>
                                                <td>
                                                    <button
                                                        class="btn-action btn-view"
                                                        @click="
                                                            viewEmployeeAccess(
                                                                employee
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center bi-eye"
                                                        ></i>
                                                        <span>Detail</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="empty-state-admin">
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-people-fill"
                                ></i>
                                <h4>Karyawan Tidak Ditemukan</h4>
                                <p>
                                    Tidak ada data karyawan yang cocok dengan
                                    filter pencarian Anda.
                                </p>
                            </div>

                            <!-- Pagination -->
                            <div
                                v-if="!loading.employees && totalPages > 1"
                                class="pagination-container"
                            >
                                <button
                                    class="page-btn"
                                    @click="currentPage--"
                                    :disabled="currentPage === 1"
                                >
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-chevron-left"
                                    ></i>
                                </button>
                                <div class="page-info">
                                    Halaman {{ currentPage }} dari
                                    {{ totalPages }}
                                </div>
                                <button
                                    class="page-btn"
                                    @click="currentPage++"
                                    :disabled="currentPage === totalPages"
                                >
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-chevron-right"
                                    ></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 2: Kelola Akses -->
                    <template #manage_access>
                        <div class="tab-content-improved">
                            <div class="manage-access-container">
                                <div class="row g-3">
                                    <!-- Langkah 1: Pemilihan Karyawan -->
                                    <div class="col-12">
                                        <div class="selection-card mb-4">
                                            <h6 class="selection-title">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-people-fill me-2"
                                                ></i>
                                                Langkah 1: Pilih Karyawan
                                            </h6>
                                            <p class="selection-description">
                                                Pilih satu atau lebih karyawan
                                                yang akan dikelola hak aksesnya.
                                            </p>
                                            <div class="form-group">
                                                <div
                                                    class="form-check form-switch mb-3"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        role="switch"
                                                        id="grantToAllSwitch"
                                                        v-model="grantToAll"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        for="grantToAllSwitch"
                                                        >Berikan Akses ke Semua
                                                        Karyawan</label
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="form-group"
                                                v-if="!grantToAll"
                                            >
                                                <label class="form-label"
                                                    >Cari & Pilih
                                                    Karyawan</label
                                                >
                                                <el-select
                                                    v-model="selectedEmployees"
                                                    multiple
                                                    filterable
                                                    remote
                                                    :remote-method="
                                                        searchEmployees
                                                    "
                                                    reserve-keyword
                                                    placeholder="Ketik nama atau kode karyawan"
                                                    :loading="loading.search"
                                                    class="w-100 custom-select"
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                >
                                                    <el-option
                                                        v-for="item in employeeOptions"
                                                        :key="item.id"
                                                        :label="item.nama"
                                                        :value="item.id"
                                                    >
                                                        <div
                                                            class="employee-option"
                                                        >
                                                            <span
                                                                class="employee-option-name"
                                                                >{{
                                                                    item.nama
                                                                }}</span
                                                            >
                                                            <span
                                                                class="employee-option-code"
                                                                >{{
                                                                    item.kode
                                                                }}</span
                                                            >
                                                        </div>
                                                    </el-option>
                                                </el-select>
                                                <div
                                                    v-if="
                                                        selectedEmployees.length >
                                                        0
                                                    "
                                                    class="selected-items-summary mt-2"
                                                >
                                                    <div class="summary-header">
                                                        <span
                                                            >{{
                                                                selectedEmployees.length
                                                            }}
                                                            Karyawan
                                                            Terpilih</span
                                                        >
                                                        <button
                                                            class="btn-clear-selection"
                                                            @click="
                                                                selectedEmployees =
                                                                    []
                                                            "
                                                        >
                                                            Bersihkan
                                                        </button>
                                                    </div>
                                                    <div
                                                        class="selected-chips-container"
                                                    >
                                                        <span
                                                            v-for="employeeId in selectedEmployees"
                                                            :key="employeeId"
                                                            class="chip"
                                                        >
                                                            {{
                                                                getEmployeeNameById(
                                                                    employeeId
                                                                )
                                                            }}
                                                            <i
                                                                class="bi d-flex justify-content-center align-items-center bi-x-circle-fill"
                                                                @click="
                                                                    removeEmployee(
                                                                        employeeId
                                                                    )
                                                                "
                                                            ></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="all-employees-info"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-shield-check"
                                                ></i>
                                                <p>
                                                    Akses akan diberikan kepada
                                                    <strong
                                                        >seluruh
                                                        karyawan</strong
                                                    >
                                                    yang terdaftar di sistem.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Kolom Kanan: Pemilihan Halaman -->
                                    <div class="col-md-12">
                                        <div
                                            class="selection-card"
                                            :class="{
                                                'disabled-card':
                                                    !grantToAll &&
                                                    selectedEmployees.length ===
                                                        0,
                                            }"
                                        >
                                            <h6 class="selection-title">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-file-earmark-lock-fill me-2"
                                                ></i>
                                                Langkah 2: Pilih Halaman
                                            </h6>
                                            <p class="selection-description">
                                                Centang halaman yang aksesnya
                                                ingin dikelola untuk karyawan
                                                yang dipilih.
                                            </p>

                                            <!-- [MODIFIED] Search Box for Pages -->
                                            <div class="search-box mb-3">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-search"
                                                ></i>
                                                <input
                                                    type="text"
                                                    v-model="pageSearch"
                                                    placeholder="Cari nama halaman atau modul..."
                                                    class="search-input-sm"
                                                />
                                            </div>

                                            <!-- [NEW] Page Browser UI -->
                                            <div class="page-browser">
                                                <div
                                                    v-if="
                                                        !filteredGroupedPages.length
                                                    "
                                                    class="d-flex flex-column align-items-center justify-content-center text-center py-5"
                                                >
                                                    <transition name="fade">
                                                        <DotLottieVue
                                                            style="
                                                                height: 150px;
                                                                width: 150px;
                                                            "
                                                            autoplay
                                                            loop
                                                            src="/animation/empty-data.json"
                                                        />
                                                    </transition>
                                                    <p
                                                        class="mt-3 mb-0 fw-bold"
                                                    >
                                                        Kamu tidak punya akses
                                                        halaman!
                                                    </p>
                                                </div>
                                                <div
                                                    class="page-groups-container"
                                                >
                                                    <div
                                                        v-for="(
                                                            pages, module
                                                        ) in filteredGroupedPages"
                                                        :key="module"
                                                        class="page-group"
                                                    >
                                                        <div
                                                            class="page-group-header"
                                                        >
                                                            <h6
                                                                class="module-title"
                                                            >
                                                                {{ module }}
                                                            </h6>
                                                            <div
                                                                class="form-check form-switch"
                                                            >
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    :id="`module-${module}`"
                                                                    :checked="
                                                                        isModuleSelected(
                                                                            module
                                                                        )
                                                                    "
                                                                    @change="
                                                                        toggleModuleSelection(
                                                                            module,
                                                                            $event
                                                                        )
                                                                    "
                                                                />
                                                                <label
                                                                    class="form-check-label"
                                                                    :for="`module-${module}`"
                                                                    >Pilih
                                                                    Semua</label
                                                                >
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="page-group-body"
                                                        >
                                                            <div
                                                                v-for="page in pages"
                                                                :key="page.id"
                                                                class="page-item"
                                                            >
                                                                <div
                                                                    class="form-check"
                                                                >
                                                                    <input
                                                                        class="form-check-input"
                                                                        type="checkbox"
                                                                        :value="
                                                                            page.id
                                                                        "
                                                                        :id="`page-${page.id}`"
                                                                        v-model="
                                                                            selectedPages
                                                                        "
                                                                    />
                                                                    <label
                                                                        class="form-check-label"
                                                                        :for="`page-${page.id}`"
                                                                    >
                                                                        {{
                                                                            page.nama
                                                                        }}
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tombol Aksi -->
                                <div
                                    class="action-footer mt-4"
                                    v-if="canUpdate"
                                >
                                    <p class="action-summary">
                                        Anda akan mengelola akses untuk
                                        <span class="fw-bold" v-if="grantToAll"
                                            >SEMUA karyawan</span
                                        >
                                        <span class="fw-bold" v-else>
                                            {{ selectedEmployees.length }}
                                            karyawan
                                        </span>
                                        dan
                                        <span class="fw-bold"
                                            >{{
                                                selectedPages.length
                                            }}
                                            halaman</span
                                        >.
                                    </p>
                                    <div class="action-buttons">
                                        <button
                                            class="btn-modern btn-danger"
                                            @click="updateAccess('revoke')"
                                            :disabled="!canUpdate"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-person-x-fill"
                                            ></i>
                                            Cabut Akses
                                        </button>
                                        <button
                                            class="btn-modern btn-success"
                                            @click="updateAccess('grant')"
                                            :disabled="!canUpdate"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-person-check-fill"
                                            ></i>
                                            Berikan Akses
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Akses Karyawan -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            title="Detail Akses Halaman Karyawan"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="selectedEmployeeDetail" class="detail-content">
                    <div class="detail-employee-header">
                        <div class="avatar-md">
                            {{ getInitials(selectedEmployeeDetail.nama) }}
                        </div>
                        <div class="employee-details">
                            <h5 class="employee-name">
                                {{ selectedEmployeeDetail.nama }}
                            </h5>
                            <p class="employee-meta">
                                {{ selectedEmployeeDetail.kode }} •
                                {{ selectedEmployeeDetail.divisi }} •
                                {{ selectedEmployeeDetail.level }}
                            </p>
                        </div>
                    </div>

                    <div class="detail-access-list">
                        <h6>
                            Daftar Halaman yang Dapat Diakses ({{
                                selectedEmployeeDetail.page_access.length
                            }})
                        </h6>
                        <div
                            v-if="selectedEmployeeDetail.page_access.length > 0"
                            class="access-tags-container"
                        >
                            <span
                                v-for="page in selectedEmployeeDetail.page_access"
                                :key="page.id"
                                class="access-tag"
                            >
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-file-earmark-check me-2"
                                ></i>
                                {{ page.nama }}
                            </span>
                        </div>
                        <div v-else class="empty-state-modal">
                            <i
                                class="bi d-flex justify-content-center align-items-center bi-shield-slash"
                            ></i>
                            <p>
                                Karyawan ini belum memiliki hak akses ke halaman
                                manapun.
                            </p>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeDetailModal"
                    >
                        Tutup
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import BannerAksesPeran from "../AksesPeran/components/BannerAksesPeran.vue";
import { debounce } from "lodash";
import { ElSelect, ElOption, ElNotification, ElLoading } from "element-plus";
import "element-plus/dist/index.css";
import axios from "axios";

import HeaderKpi from "@/components/legacy/header.vue";
import ApprovalTabs from "@/components/legacy/toggleSectionV2.vue";
import Modal from "@/components/legacy/modalV2-noir.vue";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

// --- DUMMY DATA ---
// const allEmployees = ref([{}]);
// --- END DUMMY DATA ---

export default {
    name: "PagesAccess",
    props: {
        modulBaru: { type: Array, default: () => [] },
        tautanAksesPeran: { type: String, default: null },
    },
    components: {
        BannerAksesPeran,
        HeaderKpi,
        ApprovalTabs,
        Modal,
        ElSelect,
        ElOption,
        DotLottieVue,
    },
    data() {
        return {
            currentDate: new Date(),
            activeTab: "list_access",
            loading: {
                employees: false,
                search: false,
                update: false,
            },
            filters: { search: "" },
            allEmployees: [],
            allPages: [],
            employeePageAccess: [],
            currentPage: 1,
            itemsPerPage: 10,
            selectedEmployees: [],
            selectedPages: [],
            employeeOptions: [],
            pageOptions: [],
            showDetailModal: false,
            selectedEmployeeDetail: null,
            grantToAll: false,
            pageSearch: "",
        };
    },
    computed: {
        filteredEmployees() {
            if (!this.filters.search) {
                return this.employeePageAccess;
            }
            const searchTerm = this.filters.search.toLowerCase();
            return this.employeePageAccess.filter(
                (emp) =>
                    emp.nama.toLowerCase().includes(searchTerm) ||
                    emp.kode.toLowerCase().includes(searchTerm)
            );
        },
        totalPages() {
            return Math.ceil(this.filteredEmployees.length / this.itemsPerPage);
        },
        paginatedEmployees() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredEmployees.slice(start, end);
        },
        canUpdate() {
            const employeesSelected =
                this.grantToAll || this.selectedEmployees.length > 0;
            const pagesSelected = this.selectedPages.length > 0;
            return employeesSelected && pagesSelected;
        },
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? "Siang" : "Malam";
        },
        accessTabs() {
            return [
                {
                    id: "list_access",
                    label: "Daftar Akses",
                    icon: "bi bi-list-check",
                    count: this.employeePageAccess?.length,
                },
                {
                    id: "manage_access",
                    label: "Kelola Akses",
                    icon: "bi bi-pencil-square",
                    count: 0,
                },
            ];
        },
        groupedPages() {
            return this.allPages.reduce((acc, page) => {
                const module = page.module;
                if (!acc[module]) {
                    acc[module] = [];
                }
                acc[module].push(page);
                return acc;
            }, {});
        },
        filteredGroupedPages() {
            if (!this.pageSearch) {
                return this.groupedPages;
            }
            const search = this.pageSearch.toLowerCase();
            let filtered = {};
            for (const module in this.groupedPages) {
                const pages = this.groupedPages[module].filter((page) =>
                    page.nama.toLowerCase().includes(search)
                );
                if (pages.length > 0) {
                    filtered[module] = pages;
                }
            }
            return filtered;
        },
    },
    methods: {
        applyFilters: debounce(function () {
            this.currentPage = 1;
        }, 300),

        async searchEmployees(query) {
            if (query) {
                this.loading.search = true;
                try {
                    const response = await axios.get(
                        `/api/v1/page-access/search-employees?query=${query}`
                    );
                    this.employeeOptions = response.data.result;
                } catch (error) {
                    console.error("Error searching employees:", error);
                    this.employeeOptions = [];
                } finally {
                    this.loading.search = false;
                }
            } else {
                this.employeeOptions = [];
            }
        },

        async updateAccess(action) {
            this.loading.update = true;
            const loadingInstance = ElLoading.service({
                lock: true,
                text: "Memperbarui Akses...",
                background: "rgba(0, 0, 0, 0.7)",
            });

            const targetEmployees = this.grantToAll
                ? null
                : this.selectedEmployees;

            const all = this.grantToAll ? true : false;

            try {
                await axios.post("/api/v1/page-access/update-access", {
                    employeeIds: targetEmployees,
                    pageIds: this.selectedPages,
                    action: action,
                    all: all,
                });

                ElNotification({
                    title: "Sukses",
                    message: `Akses berhasil ${
                        action === "grant" ? "diberikan" : "dicabut"
                    }.`,
                    type: "success",
                });

                // Reset form and refetch data
                this.selectedEmployees = [];
                this.selectedPages = [];
                this.grantToAll = false;
                this.fetchInitialData(); // Reload data
            } catch (error) {
                console.error("Error updating access:", error);
                ElNotification({
                    title: "Error",
                    message:
                        error.response?.data?.message ||
                        "Gagal memperbarui akses.",
                    type: "error",
                });
            } finally {
                this.loading.update = false;
                loadingInstance.close();
            }
        },

        viewEmployeeAccess(employee) {
            this.selectedEmployeeDetail = employee;
            this.showDetailModal = true;
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedEmployeeDetail = null;
        },

        toggleModuleSelection(module, event) {
            const modulePageIds = this.groupedPages[module].map((p) => p.id);
            if (event.target.checked) {
                // Tambahkan yang belum ada
                const pagesToAdd = modulePageIds.filter(
                    (id) => !this.selectedPages.includes(id)
                );
                this.selectedPages.push(...pagesToAdd);
            } else {
                // Hapus semua dari modul ini
                this.selectedPages = this.selectedPages.filter(
                    (id) => !modulePageIds.includes(id)
                );
            }
        },
        isModuleSelected(module) {
            const modulePageIds = this.groupedPages[module].map((p) => p.id);
            return modulePageIds.every((id) => this.selectedPages.includes(id));
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },

        formatDateString(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });
        },

        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },

        getEmployeeNameById(id) {
            const employee = this.allEmployees.find((e) => e.id === id);
            return employee ? employee.nama : "Unknown";
        },

        removeEmployee(employeeId) {
            this.selectedEmployees = this.selectedEmployees.filter(
                (id) => id !== employeeId
            );
        },

        removePage(pageId) {
            this.selectedPages = this.selectedPages.filter(
                (id) => id !== pageId
            );
        },

        async fetchInitialData() {
            this.loading.employees = true;
            try {
                const response = await axios.get(
                    "/api/v1/page-access/initial-data"
                );
                this.allPages = response.data.result.allPages;
                this.employeePageAccess =
                    response.data.result.employeePageAccess;
                this.allEmployees = this.employeePageAccess.map((emp) => ({
                    id: emp.id,
                    nama: emp.nama,
                    kode: emp.kode,
                }));
            } catch (error) {
                console.error("Error fetching initial data:", error);
                ElNotification({
                    title: "Error",
                    message: "Gagal memuat data dari server.",
                    type: "error",
                });
            } finally {
                this.loading.employees = false;
            }
        },
    },
    mounted() {
        this.fetchInitialData();
    },
};

/*
    setup() {
        const currentDate = ref(new Date());
        const activeTab = ref('list_access');
        const loading = ref({
            employees: false,
            pages: false,
            search: false,
            update: false,
        });

        // --- Tab 1: List Access ---
        const filters = ref({ search: "" });
        const currentPage = ref(1);
        const itemsPerPage = ref(10);

        const filteredEmployees = computed(() => {
            if (!filters.value.search) { //
                return employeePageAccess.value;
            }
            const searchTerm = filters.value.search.toLowerCase();
            return employeePageAccess.value.filter(emp =>
                emp.nama.toLowerCase().includes(searchTerm) ||
                emp.kode.toLowerCase().includes(searchTerm)
            );
        });

        const totalPages = computed(() => {
            return Math.ceil(filteredEmployees.value.length / itemsPerPage.value); //
        });

        const paginatedEmployees = computed(() => {
            const start = (currentPage.value - 1) * itemsPerPage.value; //
            const end = start + itemsPerPage.value; //
            return filteredEmployees.value.slice(start, end);
        });

        const applyFilters = debounce(() => {
            currentPage.value = 1;
        }, 300);

        // --- Tab 2: Manage Access ---
        const selectedEmployees = ref([]); //
        const selectedPages = ref([]); //
        const employeeOptions = ref([]); //
        const pageOptions = ref([]); //

        const searchEmployees = (query) => {
            if (query) {
                loading.value.search = true;
                setTimeout(() => {
                    loading.value.search = false;
                    employeeOptions.value = allEmployees.value.filter(item =>
                        item.nama.toLowerCase().includes(query.toLowerCase()) ||
                        item.kode.toLowerCase().includes(query.toLowerCase())
                    );
                }, 200);
            } else {
                employeeOptions.value = [];
            }
        };

        const searchPages = (query) => {
            if (query) {
                loading.value.search = true;
                setTimeout(() => {
                    loading.value.search = false;
                    pageOptions.value = allPages.value.filter(item =>
                        item.nama.toLowerCase().includes(query.toLowerCase()) ||
                        item.module.toLowerCase().includes(query.toLowerCase())
                    );
                }, 200);
            } else {
                pageOptions.value = [];
            }
        };

        const canUpdate = computed(() => {
            return selectedEmployees.value.length > 0 && selectedPages.value.length > 0; //
        });

        const updateAccess = (action) => {
            loading.value.update = true;
            console.log(`Performing ${action} action...`);
            console.log('Selected Employees:', selectedEmployees.value); //
            console.log('Selected Pages:', selectedPages.value); //

            // --- SIMULASI API CALL ---
            setTimeout(() => {
                // Logika untuk grant/revoke
                selectedEmployees.value.forEach(employeeId => {
                    const employee = employeePageAccess.value.find(e => e.id === employeeId);
                    if (employee) {
                        selectedPages.value.forEach(pageId => {
                            const page = allPages.value.find(p => p.id === pageId);
                            if (!page) return;

                            const hasAccess = employee.page_access.some(p => p.id === pageId);

                            if (action === 'grant' && !hasAccess) {
                                employee.page_access.push({ id: page.id, nama: page.nama });
                            } else if (action === 'revoke' && hasAccess) {
                                employee.page_access = employee.page_access.filter(p => p.id !== pageId);
                            }
                        });
                    }
                });

                ElNotification({
                    title: 'Sukses',
                    message: `Akses berhasil ${action === 'grant' ? 'diberikan' : 'dicabut'}.`,
                    type: 'success',
                });

                // Reset form
                selectedEmployees.value = [];
                selectedPages.value = [];
                loading.value.update = false;
            }, 1000);
        };

        // --- Modal Detail ---
        const showDetailModal = ref(false); //
        const selectedEmployeeDetail = ref(null); //

        const viewEmployeeAccess = (employee) => {
            selectedEmployeeDetail.value = employee;
            showDetailModal.value = true;
        };

        const closeDetailModal = () => {
            showDetailModal.value = false;
            selectedEmployeeDetail.value = null;
        };

        // --- Computed & Methods ---
        const dayNight = computed(() => {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        });

        const accessTabs = computed(() => [
            { id: 'list_access', label: 'Daftar Akses', icon: 'bi bi-list-check', count: employeePageAccess.value.length }, //
            { id: 'manage_access', label: 'Kelola Akses', icon: 'bi bi-pencil-square', count: 0 }, //
        ]);

        const handleTabChange = (tabId) => {
            activeTab.value = tabId; //
        };

        const formatDateString = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });
        };

        const getInitials = (name) => {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        };

        onMounted(() => {
            // Simulasi fetch data awal
            loading.value.employees = true;
            setTimeout(() => {
                loading.value.employees = false;
            }, 500);
        });

        return {
            currentDate,
            dayNight,
            formatDateString,
            getInitials,
            accessTabs,
            activeTab,
            handleTabChange,
            loading,
            filters,
            applyFilters,
            paginatedEmployees,
            currentPage,
            totalPages,
            selectedEmployees,
            selectedPages,
            employeeOptions,
            pageOptions,
            searchEmployees,
            searchPages,
            canUpdate,
            updateAccess,
            showDetailModal,
            selectedEmployeeDetail,
            viewEmployeeAccess,
            closeDetailModal,
        };
    }
};*/
</script>

<style scoped>
/* Custom Element Plus Select */
.el-select-dropdown__item {
    height: auto;
    padding: 8px 15px;
}

.employee-option,
.page-option {
    display: flex;
    flex-direction: column;
}

.employee-option-name,
.page-option-name {
    font-weight: 500;
    color: var(--text, var(--hcis-slate-700));
}

.employee-option-code,
.page-option-module {
    font-size: 0.8rem;
    color: var(--text-muted, var(--hcis-slate-500));
}

.el-select.custom-select .el-input__wrapper {
    min-height: 48px;
    border-radius: 10px;
    border: 2px solid var(--border, var(--hcis-slate-200));
    transition: all 0.3s ease;
}

.el-select.custom-select .el-input__wrapper.is-focus {
    border-color: var(--primary, var(--hcis-indigo-500));
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>

<style scoped>
/* Menggunakan variabel CSS dari file lain untuk konsistensi */
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
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --radius: 12px;
}

.tab-content-improved {
    padding: 1.5rem;
    background-color: var(--surface-soft);
    border-radius: var(--radius);
}

/* Filter & Search Section */
.filter-section {
    background: var(--surface);
    padding: 1rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.search-box {
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Employee List Table */
.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.modern-table th {
    background-color: var(--surface-soft);
    font-weight: 600;
    color: var(--text-muted);
}

.modern-table tr:hover {
    background-color: var(--surface-soft);
}

.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.employee-details .employee-name {
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.employee-details .employee-code {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin: 0;
}

.badge-access-count {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Manage Access Tab */
.manage-access-container {
    background: var(--surface);
    padding: 2rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.selection-card {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    height: 100%;
    transition: all 0.3s ease;
}

.selection-card.disabled-card {
    opacity: 0.5;
    pointer-events: none;
    background: #d6d6d6;
}

.selection-title {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.selection-description {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.all-employees-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem 1rem;
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    background-color: var(--surface);
    color: var(--text-muted);
}

.all-employees-info i {
    font-size: 2.5rem;
    color: var(--success);
    margin-bottom: 1rem;
}

.all-employees-info p {
    margin: 0;
    font-size: 0.9rem;
}

.selected-items-summary {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.btn-clear-selection {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-weight: 600;
}

.selected-chips-container {
    max-height: 150px;
    overflow-y: auto;
}
.form-group {
    margin-bottom: 1rem;
}

.page-browser {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.page-groups-container {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.page-group {
    margin-bottom: 1rem;
}

.page-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.module-title {
    font-weight: 600;
    color: var(--text);
    margin: 0;
    font-size: 1rem;
}

.page-group-header .form-check {
    margin: 0;
}

.page-group-body {
    padding: 0 0.75rem;
}

.page-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.page-item:last-child {
    border-bottom: none;
}

.page-item .form-check-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.page-browser {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.page-groups-container {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.page-group {
    margin-bottom: 1rem;
}

.page-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.module-title {
    font-weight: 600;
    color: var(--text);
    margin: 0;
    font-size: 1rem;
}

.page-group-header .form-check {
    margin: 0;
}

.page-group-body {
    padding: 0 0.75rem;
}

.page-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.page-item:last-child {
    margin-left: 0;
    border-bottom: none;
}

.page-item .form-check-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.chip {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 16px;
    font-size: 0.85rem;
    font-weight: 500;
    margin: 0.25rem;
}

.chip i {
    margin-left: 0.5rem;
    cursor: pointer;
    opacity: 0.7;
}
.chip i:hover {
    opacity: 1;
}
.action-footer {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-summary {
    color: var(--text-muted);
    margin: 0;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

/* Buttons */
.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-view:hover {
    background: var(--border);
}

.btn-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-success {
    background: var(--success);
    color: white;
}
.btn-success:hover:not(:disabled) {
    background: var(--hcis-emerald-600);
}

.btn-danger {
    background: var(--danger);
    color: white;
}
.btn-danger:hover:not(:disabled) {
    background: var(--hcis-red-600);
}

.btn-secondary {
    background: var(--secondary);
    color: white;
}
.btn-secondary:hover:not(:disabled) {
    background: var(--hcis-slate-600);
}

/* Modal Detail */
.detail-employee-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.avatar-md {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.5rem;
}

.detail-access-list h6 {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 1rem;
}

.access-tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.access-tag {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.empty-state-modal {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.empty-state-modal i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

/* General States */
.loading-state,
.empty-state-admin {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
    text-align: center;
}

.loading-spinner .bi-arrow-repeat {
    font-size: 2.5rem;
    color: var(--primary);
    animation: spin 1.5s linear infinite;
}

.empty-state-admin i {
    font-size: 4rem;
    opacity: 0.5;
    margin-bottom: 1rem;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
}

.page-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    cursor: pointer;
    transition: all 0.2s ease;
}
.page-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    margin: 0 1rem;
    font-size: 0.9rem;
    color: var(--text-muted);
}

@media (max-width: 991px) {
    .action-footer {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    .action-buttons {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .tab-content-improved {
        padding: 1rem;
    }
    .manage-access-container {
        padding: 1.5rem;
    }
    .modern-table {
        display: block;
        overflow-x: auto;
    }
}
</style>
