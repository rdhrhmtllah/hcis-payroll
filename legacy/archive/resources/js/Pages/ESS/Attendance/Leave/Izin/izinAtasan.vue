<template>
    <div class="container-fluid px-0 pb-4">
        <!-- Header -->
        <HeaderKpi
            title="Persetujuan Izin"
            subtitle="Kelola pengajuan izin dari tim Anda"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <ApprovalTabs
                    :tabs="approvalTabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab Dashboard -->
                    <template #dashboard_content>
                        <div class="tab-content-improved">
                            <div class="row g-3">
                                <!-- Quick Stats -->
                                <div
                                    v-for="stat in dashboardStats"
                                    :key="stat.title"
                                    class="col-md-3 col-6"
                                >
                                    <div class="stat-card-dashboard">
                                        <div
                                            class="stat-icon"
                                            :class="stat.bgClass"
                                        >
                                            <i :class="stat.icon"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h4>{{ stat.value }}</h4>
                                            <p>{{ stat.title }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-3">
                                <!-- Chart Jenis Izin -->
                                <div class="col-lg-6">
                                    <div class="chart-card">
                                        <h6 class="chart-title">
                                            Distribusi Jenis Izin
                                        </h6>
                                        <apexchart
                                            type="donut"
                                            height="350"
                                            :options="leaveTypeChart.options"
                                            :series="leaveTypeChart.series"
                                        ></apexchart>
                                    </div>
                                </div>
                                <!-- Chart Pengajuan per Karyawan -->
                                <div class="col-lg-6">
                                    <div class="chart-card">
                                        <h6 class="chart-title">
                                            Pengajuan per Karyawan (Bulan Ini)
                                        </h6>
                                        <apexchart
                                            type="bar"
                                            height="350"
                                            :options="
                                                requestsByEmployeeChart.options
                                            "
                                            :series="
                                                requestsByEmployeeChart.series
                                            "
                                        ></apexchart>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Pengajuan -->
                    <template #pengajuan_content>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-8">
                                        <div class="search-box">
                                            <i class="bi bi-search"></i>
                                            <input
                                                type="text"
                                                v-model="filters.search"
                                                placeholder="Cari nama karyawan atau jenis izin..."
                                                class="search-input"
                                                @input="applyFilters"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select
                                            v-model="filters.type"
                                            class="filter-select"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Semua Jenis Izin
                                            </option>
                                            <option value="Cuti">Cuti</option>
                                            <option value="Sakit">
                                                Izin Sakit
                                            </option>
                                            <option value="Izin">
                                                Izin Terlambat/Pulang Cepat
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-if="loading.pengajuan" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4>Memuat Pengajuan...</h4>
                            </div>

                            <!-- List Pengajuan -->
                            <div v-else class="pengajuan-list">
                                <div
                                    v-for="item in filteredPengajuan"
                                    :key="item.id"
                                    class="pengajuan-card"
                                >
                                    <div class="pengajuan-header">
                                        <div class="employee-info">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        item.nama_karyawan
                                                    )
                                                }}
                                            </div>
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{ item.nama_karyawan }}
                                                </h6>
                                                <p class="employee-meta">
                                                    {{ item.divisi }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pengajuan-meta">
                                            <Badge :type="getBadgeType(item)">
                                                {{ item.jenis_izin }}
                                            </Badge>
                                            <span class="pengajuan-date">{{
                                                formatDate(item.created_at)
                                            }}</span>
                                        </div>
                                    </div>

                                    <div class="pengajuan-current-approver">
                                        <i class="bi bi-person-check-fill"></i>
                                        <span>
                                            Menunggu Persetujuan:
                                            <strong
                                                >{{
                                                    getCurrentApprover(
                                                        item.approval_flow
                                                    )
                                                }}
                                            </strong>
                                        </span>
                                    </div>

                                    <div class="pengajuan-content">
                                        <div class="pengajuan-details">
                                            <div class="detail-item">
                                                <i class="bi bi-calendar"></i>
                                                <span
                                                    >{{
                                                        formatDate(
                                                            item.tanggal_mulai
                                                        )
                                                    }}
                                                    -
                                                    {{
                                                        formatDate(
                                                            item.tanggal_selesai
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-clock"></i>
                                                <span
                                                    >{{
                                                        item.jumlah_hari
                                                    }}
                                                    hari</span
                                                >
                                            </div>
                                        </div>
                                        <div class="pengajuan-reason">
                                            <p>{{ item.alasan }}</p>
                                        </div>
                                    </div>

                                    <div class="pengajuan-footer">
                                        <div class="pengajuan-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="viewDetail(item)"
                                            >
                                                <i class="bi bi-eye"></i>
                                                <span>Detail</span>
                                            </button>
                                            <button
                                                class="btn-action btn-danger"
                                                @click="rejectPengajuan(item)"
                                            >
                                                <i class="bi bi-x"></i>
                                                <span>Tolak</span>
                                            </button>
                                            <button
                                                class="btn-action btn-success"
                                                @click="approvePengajuan(item)"
                                            >
                                                <i class="bi bi-check"></i>
                                                <span>Setujui</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="
                                    !loading.pengajuan &&
                                    filteredPengajuan.length === 0
                                "
                                class="empty-state-admin"
                            >
                                <i class="bi bi-inbox"></i>
                                <h4>Tidak Ada Pengajuan</h4>
                                <p>
                                    Saat ini tidak ada pengajuan izin yang
                                    memerlukan persetujuan Anda.
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Riwayat -->
                    <template #riwayat_content>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-6">
                                        <div class="search-box">
                                            <i class="bi bi-search"></i>
                                            <input
                                                type="text"
                                                v-model="filtersRiwayat.search"
                                                placeholder="Cari nama karyawan atau jenis izin..."
                                                class="search-input"
                                                @input="applyFiltersRiwayat"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select
                                            v-model="filtersRiwayat.type"
                                            class="filter-select"
                                            @change="applyFiltersRiwayat"
                                        >
                                            <option value="">
                                                Semua Jenis
                                            </option>
                                            <option value="Cuti">Cuti</option>
                                            <option value="Sakit">
                                                Izin Sakit
                                            </option>
                                            <option value="Izin">
                                                Izin Terlambat/Pulang Cepat
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select
                                            v-model="filtersRiwayat.status"
                                            class="filter-select"
                                            @change="applyFiltersRiwayat"
                                        >
                                            <option value="">
                                                Semua Status
                                            </option>
                                            <option value="approved">
                                                Disetujui
                                            </option>
                                            <option value="rejected">
                                                Ditolak
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Riwayat List -->
                            <div v-if="loading.riwayat" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4>Memuat Riwayat...</h4>
                            </div>

                            <div v-else class="table-responsive">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Jenis Izin</th>
                                            <th>Tanggal</th>
                                            <th>Status</th>
                                            <th>Diproses Pada</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in filteredRiwayat"
                                            :key="item.id"
                                        >
                                            <td>
                                                <div class="employee-info">
                                                    <div class="avatar-sm">
                                                        {{
                                                            getInitials(
                                                                item.nama_karyawan
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
                                                                item.nama_karyawan
                                                            }}
                                                        </h6>
                                                        <p
                                                            class="employee-meta"
                                                        >
                                                            {{ item.divisi }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <Badge
                                                    :type="getBadgeType(item)"
                                                >
                                                    {{ item.jenis_izin }}
                                                </Badge>
                                            </td>
                                            <td>
                                                {{
                                                    formatDate(
                                                        item.tanggal_mulai
                                                    )
                                                }}
                                            </td>
                                            <td>
                                                <Badge
                                                    :type="
                                                        item.status ===
                                                        'approved'
                                                            ? 'success'
                                                            : 'danger'
                                                    "
                                                >
                                                    {{
                                                        capitalize(item.status)
                                                    }}
                                                </Badge>
                                            </td>
                                            <td>
                                                {{
                                                    formatDateTime(
                                                        item.processed_at
                                                    )
                                                }}
                                            </td>
                                            <td>
                                                <button
                                                    class="btn-action btn-view"
                                                    @click="viewDetail(item)"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    <span>Detail</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State Riwayat -->
                            <div
                                v-if="
                                    !loading.riwayat &&
                                    filteredRiwayat.length === 0
                                "
                                class="empty-state-admin"
                            >
                                <i class="bi bi-clock-history"></i>
                                <h4>Tidak Ada Riwayat</h4>
                                <p>
                                    Belum ada riwayat pengajuan yang Anda
                                    proses.
                                </p>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Pengajuan -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            title="Detail Pengajuan Izin"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="selectedItem" class="detail-content">
                    <div class="detail-section">
                        <div class="detail-row">
                            <div class="detail-label">Nama Karyawan</div>
                            <div class="detail-value">
                                {{ selectedItem.nama_karyawan }} ({{
                                    selectedItem.divisi
                                }})
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Jenis Izin</div>
                            <div class="detail-value">
                                <Badge :type="getBadgeType(selectedItem)">
                                    {{ selectedItem.jenis_izin }}
                                </Badge>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Periode</div>
                            <div class="detail-value">
                                {{ formatDate(selectedItem.tanggal_mulai) }} -
                                {{ formatDate(selectedItem.tanggal_selesai) }}
                                ({{ selectedItem.jumlah_hari }} hari)
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Alasan</div>
                            <div class="detail-value">
                                {{ selectedItem.alasan }}
                            </div>
                        </div>
                        <!-- Approval Trace -->
                        <div class="detail-row approval-trace-section">
                            <div class="detail-label">Jejak Persetujuan</div>
                            <div class="detail-value">
                                <div class="approval-trace">
                                    <div
                                        v-for="(
                                            approver, index
                                        ) in selectedItem.approval_flow"
                                        :key="index"
                                        class="trace-item"
                                        :class="getTraceStatusClass(approver)"
                                    >
                                        <div class="trace-icon">
                                            <i
                                                :class="getTraceIcon(approver)"
                                            ></i>
                                        </div>
                                        <div class="trace-info">
                                            <div class="trace-name">
                                                {{ approver.name }}
                                            </div>
                                            <div class="trace-date">
                                                {{ formatTraceDate(approver) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="selectedItem.file_pendukung"
                            class="detail-row"
                        >
                            <div class="detail-label">File Pendukung</div>
                            <div class="detail-value">
                                <a
                                    :href="selectedItem.file_pendukung"
                                    target="_blank"
                                    class="file-link"
                                >
                                    <i class="bi bi-paperclip me-2"></i>Lihat
                                    File
                                </a>
                            </div>
                        </div>
                        <div
                            v-if="selectedItem.status !== 'pending'"
                            class="detail-row"
                        >
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <Badge
                                    :type="
                                        selectedItem.status === 'approved'
                                            ? 'success'
                                            : 'danger'
                                    "
                                >
                                    {{ capitalize(selectedItem.status) }}
                                </Badge>
                                <span class="ms-2"
                                    >pada
                                    {{
                                        formatDateTime(
                                            selectedItem.processed_at
                                        )
                                    }}</span
                                >
                            </div>
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
                    <button
                        v-if="selectedItem?.status === 'pending'"
                        class="btn-modern btn-danger"
                        @click="rejectPengajuan(selectedItem)"
                    >
                        Tolak
                    </button>
                    <button
                        v-if="selectedItem?.status === 'pending'"
                        class="btn-modern btn-success"
                        @click="approvePengajuan(selectedItem)"
                    >
                        Setujui
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import HeaderKpi from "@/components/ui/header.vue";
import ApprovalTabs from "@/components/ui/toggleSectionV2.vue";
import Badge from "../../../components/ui/badge.vue";
import Modal from "@/components/ui/modalV2-noir.vue";
import { ElNotification } from "element-plus";
import ApexCharts from "vue3-apexcharts";

export default {
    name: "IzinAtasan",
    components: {
        HeaderKpi,
        ApprovalTabs,
        Badge,
        Modal,
        apexchart: ApexCharts,
    },
    setup() {
        const currentDate = ref(new Date());
        const activeTab = ref("dashboard");

        const pengajuanList = ref([]);
        const riwayatList = ref([]);
        const loading = ref({
            pengajuan: false,
            riwayat: false,
            dashboard: false,
        });

        const filters = ref({ search: "", type: "" });
        const filtersRiwayat = ref({ search: "", type: "", status: "" });

        const showDetailModal = ref(false);
        const selectedItem = ref(null);

        // --- DUMMY DATA & ASYNC FUNCTIONS ---

        const fetchPengajuan = async () => {
            loading.value.pengajuan = true;
            console.log("Fetching pending requests...");
            await new Promise((resolve) => setTimeout(resolve, 1000)); // Simulate network delay

            // Data dummy ini akan menggabungkan 3 jenis izin
            pengajuanList.value = [
                {
                    id: 1,
                    nama_karyawan: "Budi Gunawan",
                    divisi: "Teknologi",
                    jenis_izin: "Cuti",
                    tanggal_mulai: "2025-11-20",
                    tanggal_selesai: "2025-11-21",
                    jumlah_hari: 2,
                    alasan: "Acara keluarga di luar kota.",
                    status: "pending",
                    created_at: "2025-11-15T10:00:00",
                    file_pendukung: null,
                    approval_flow: [
                        {
                            name: "Ahmad Subarjo",
                            status: "Y",
                            date: "2025-11-15T11:00:00",
                        },
                        {
                            name: "Anda",
                            status: "N",
                            date: null,
                        },
                        {
                            name: "HC Department",
                            status: "N",
                            date: null,
                        },
                    ],
                },
                {
                    id: 2,
                    nama_karyawan: "Citra Lestari",
                    divisi: "Marketing",
                    jenis_izin: "Sakit",
                    tanggal_mulai: "2025-11-18",
                    tanggal_selesai: "2025-11-18",
                    jumlah_hari: 1,
                    alasan: "Demam dan flu, perlu istirahat.",
                    status: "pending",
                    created_at: "2025-11-18T08:30:00",
                    file_pendukung: "/dummy/surat-sakit.pdf",
                    approval_flow: [
                        {
                            name: "Anda",
                            status: "N",
                            date: null,
                        },
                        {
                            name: "HC Department",
                            status: "N",
                            date: null,
                        },
                    ],
                },
                {
                    id: 3,
                    nama_karyawan: "Doni Setiawan",
                    divisi: "Operasional",
                    jenis_izin: "Izin", // Izin Terlambat/Pulang Cepat
                    sub_jenis: "Pulang Cepat",
                    tanggal_mulai: "2025-11-19",
                    tanggal_selesai: "2025-11-19",
                    jumlah_hari: 0.5, // Bisa direpresentasikan dalam hari
                    alasan: "Anak sakit dan perlu dijemput dari sekolah.",
                    status: "pending",
                    created_at: "2025-11-19T13:00:00",
                    file_pendukung: null,
                    approval_flow: [
                        {
                            name: "Anda",
                            status: "N",
                            date: null,
                        },
                        {
                            name: "HC Department",
                            status: "N",
                            date: null,
                        },
                    ],
                },
            ];
            loading.value.pengajuan = false;
        };

        const fetchRiwayat = async () => {
            loading.value.riwayat = true;
            console.log("Fetching history...");
            await new Promise((resolve) => setTimeout(resolve, 1000));
            riwayatList.value = [
                {
                    id: 101,
                    nama_karyawan: "Eka Putri",
                    divisi: "Teknologi",
                    jenis_izin: "Cuti",
                    tanggal_mulai: "2025-11-01",
                    tanggal_selesai: "2025-11-01",
                    jumlah_hari: 1,
                    alasan: "Keperluan pribadi.",
                    status: "approved",
                    created_at: "2025-10-28T11:00:00",
                    processed_at: "2025-10-28T15:00:00",
                    approval_flow: [
                        {
                            name: "Anda",
                            status: "Y",
                            date: "2025-10-28T15:00:00",
                        },
                        {
                            name: "HC Department",
                            status: "Y",
                            date: "2025-10-28T16:00:00",
                        },
                    ],
                },
                {
                    id: 102,
                    nama_karyawan: "Fajar Nugraha",
                    divisi: "Teknologi",
                    jenis_izin: "Izin",
                    sub_jenis: "Terlambat",
                    tanggal_mulai: "2025-11-05",
                    tanggal_selesai: "2025-11-05",
                    jumlah_hari: 0.2,
                    alasan: "Ban motor bocor.",
                    status: "rejected",
                    created_at: "2025-11-05T09:00:00",
                    processed_at: "2025-11-05T09:30:00",
                    approval_flow: [
                        {
                            name: "Anda",
                            status: "T",
                            date: "2025-11-05T09:30:00",
                        },
                        {
                            name: "HC Department",
                            status: "N",
                            date: null,
                        },
                    ],
                },
            ];
            loading.value.riwayat = false;
        };

        const fetchDashboardData = async () => {
            loading.value.dashboard = true;
            console.log("Fetching dashboard data...");
            await new Promise((resolve) => setTimeout(resolve, 800));
            // Data dummy untuk chart
            leaveTypeChart.series.value = [45, 25, 30]; // Cuti, Sakit, Izin
            requestsByEmployeeChart.series.value = [
                {
                    name: "Jumlah Pengajuan",
                    data: [5, 3, 8, 2],
                },
            ];
            loading.value.dashboard = false;
        };

        const approvePengajuan = async (item) => {
            console.log(`Approving request ID: ${item.id}`);
            // Simulasi request ke backend
            await new Promise((resolve) => setTimeout(resolve, 500));

            // Hapus dari list pengajuan
            pengajuanList.value = pengajuanList.value.filter(
                (p) => p.id !== item.id
            );

            // Tambahkan ke riwayat
            riwayatList.value.unshift({
                ...item,
                status: "approved",
                processed_at: new Date().toISOString(),
            });

            ElNotification({
                title: "Berhasil",
                message: `Pengajuan dari ${item.nama_karyawan} telah disetujui.`,
                type: "success",
            });
            closeDetailModal();
        };

        const rejectPengajuan = async (item) => {
            const reason = prompt(
                `Masukkan alasan penolakan untuk ${item.nama_karyawan}:`
            );
            if (reason) {
                console.log(`Rejecting request ID: ${item.id} for: ${reason}`);
                await new Promise((resolve) => setTimeout(resolve, 500));

                pengajuanList.value = pengajuanList.value.filter(
                    (p) => p.id !== item.id
                );
                riwayatList.value.unshift({
                    ...item,
                    status: "rejected",
                    alasan_penolakan: reason,
                    processed_at: new Date().toISOString(),
                });

                ElNotification({
                    title: "Berhasil",
                    message: `Pengajuan dari ${item.nama_karyawan} telah ditolak.`,
                    type: "info",
                });
                closeDetailModal();
            }
        };

        // --- COMPUTED PROPERTIES ---

        const dayNight = computed(() => {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        });

        const approvalTabs = computed(() => [
            {
                id: "dashboard",
                label: "Dashboard",
                icon: "bi bi-grid-1x2-fill",
                slot: "dashboard_content",
            },
            {
                id: "pengajuan",
                label: "Pengajuan",
                icon: "bi bi-inbox-fill",
                count: pengajuanList.value.length,
                slot: "pengajuan_content",
            },
            {
                id: "riwayat",
                label: "Riwayat",
                icon: "bi bi-clock-history",
                slot: "riwayat_content",
            },
        ]);

        const filteredPengajuan = computed(() => {
            if (!pengajuanList.value) return [];
            return pengajuanList.value.filter((item) => {
                const searchMatch =
                    filters.value.search === "" ||
                    item.nama_karyawan
                        .toLowerCase()
                        .includes(filters.value.search.toLowerCase()) ||
                    item.jenis_izin
                        .toLowerCase()
                        .includes(filters.value.search.toLowerCase());

                const typeMatch =
                    filters.value.type === "" ||
                    item.jenis_izin === filters.value.type;

                return searchMatch && typeMatch;
            });
        });

        const filteredRiwayat = computed(() => {
            if (!riwayatList.value) return [];
            return riwayatList.value.filter((item) => {
                const searchMatch =
                    filtersRiwayat.value.search === "" ||
                    item.nama_karyawan
                        .toLowerCase()
                        .includes(filtersRiwayat.value.search.toLowerCase()) ||
                    item.jenis_izin
                        .toLowerCase()
                        .includes(filtersRiwayat.value.search.toLowerCase());

                const typeMatch =
                    filtersRiwayat.value.type === "" ||
                    item.jenis_izin === filtersRiwayat.value.type;

                const statusMatch =
                    filtersRiwayat.value.status === "" ||
                    item.status === filtersRiwayat.value.status;

                return searchMatch && typeMatch && statusMatch;
            });
        });

        const dashboardStats = computed(() => [
            {
                title: "Menunggu Persetujuan",
                value: pengajuanList.value.length,
                icon: "bi bi-hourglass-split",
                bgClass: "bg-warning-light",
            },
            {
                title: "Total Tim",
                value: 12, // Dummy
                icon: "bi bi-people-fill",
                bgClass: "bg-primary-light",
            },
            {
                title: "Izin Bulan Ini",
                value: riwayatList.value.length + pengajuanList.value.length, // Dummy
                icon: "bi bi-calendar-check",
                bgClass: "bg-success-light",
            },
            {
                title: "Jenis Izin Populer",
                value: "Cuti", // Dummy
                icon: "bi bi-star-fill",
                bgClass: "bg-info-light",
            },
        ]);

        // --- CHART CONFIG ---
        const leaveTypeChart = {
            series: ref([0, 0, 0]),
            options: {
                chart: {
                    type: "donut",
                },
                labels: ["Cuti", "Sakit", "Izin"],
                responsive: [
                    {
                        breakpoint: 480,
                        options: {
                            chart: {
                                width: 200,
                            },
                            legend: {
                                position: "bottom",
                            },
                        },
                    },
                ],
                colors: ["#6366F1", "#F59E0B", "#10B981"],
                legend: {
                    position: "bottom",
                },
            },
        };

        const requestsByEmployeeChart = {
            series: ref([]),
            options: {
                chart: {
                    type: "bar",
                    height: 350,
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: "55%",
                        endingShape: "rounded",
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ["transparent"],
                },
                xaxis: {
                    categories: [
                        "Budi Gunawan",
                        "Citra Lestari",
                        "Doni Setiawan",
                        "Eka Putri",
                    ],
                },
                yaxis: {
                    title: {
                        text: "Jumlah Pengajuan",
                    },
                },
                fill: {
                    opacity: 1,
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " pengajuan";
                        },
                    },
                },
                colors: ["#6366F1"],
            },
        };

        // --- METHODS ---

        const handleTabChange = (tabId) => {
            activeTab.value = tabId;
            if (tabId === "pengajuan") {
                fetchPengajuan();
            } else if (tabId === "riwayat") {
                fetchRiwayat();
            } else if (
                tabId === "dashboard" &&
                leaveTypeChart.series.value.every((v) => v === 0)
            ) {
                fetchDashboardData();
            }
        };

        const applyFilters = () => {
            // Filtering is handled by computed property
        };

        const applyFiltersRiwayat = () => {
            // Filtering is handled by computed property
        };

        const formatDateString = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });
        };

        const formatDate = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            });
        };

        const formatDateTime = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
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

        const getBadgeType = (item) => {
            const types = {
                Cuti: "primary",
                Sakit: "warning",
                Izin: "info",
            };
            return types[item.jenis_izin] || "secondary";
        };

        const capitalize = (text) => {
            if (!text) return "";
            return text.charAt(0).toUpperCase() + text.slice(1);
        };

        const getCurrentApprover = (flow) => {
            if (!flow) return "Tidak Diketahui";
            const pendingApprover = flow.find((f) => f.status === "N");
            return pendingApprover ? pendingApprover.name : "Selesai";
        };

        const getTraceStatusClass = (approver) => {
            switch (approver.status) {
                case "Y":
                    return "trace-approved";
                case "T":
                    return "trace-rejected";
                case "N":
                    return "trace-pending";
                default:
                    return "";
            }
        };

        const getTraceIcon = (approver) => {
            switch (approver.status) {
                case "Y":
                    return "bi bi-check-circle-fill";
                case "T":
                    return "bi bi-x-circle-fill";
                case "N":
                    return "bi bi-hourglass-split";
                default:
                    return "bi bi-question-circle-fill";
            }
        };

        const formatTraceDate = (approver) => {
            if (approver.status === "N" || !approver.date) return "Menunggu";
            return formatDateTime(approver.date);
        };

        const viewDetail = (item) => {
            selectedItem.value = item;
            showDetailModal.value = true;
        };

        const closeDetailModal = () => {
            showDetailModal.value = false;
            selectedItem.value = null;
        };

        onMounted(() => {
            fetchDashboardData();
            fetchPengajuan();
            fetchRiwayat();
        });

        return {
            currentDate,
            activeTab,
            dayNight,
            approvalTabs,
            pengajuanList,
            riwayatList,
            loading,
            filters,
            filtersRiwayat,
            filteredPengajuan,
            filteredRiwayat,
            showDetailModal,
            selectedItem,
            dashboardStats,
            leaveTypeChart,
            requestsByEmployeeChart,
            handleTabChange,
            applyFilters,
            applyFiltersRiwayat,
            formatDateString,
            formatDate,
            formatDateTime,
            getInitials,
            getBadgeType,
            capitalize,
            viewDetail,
            closeDetailModal,
            approvePengajuan,
            rejectPengajuan,
            getCurrentApprover,
            getTraceStatusClass,
            getTraceIcon,
            formatTraceDate,
        };
    },
};
</script>

<style scoped>
/* Menggunakan variabel CSS dari file lain untuk konsistensi */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #e0e7ff;
    --secondary: #64748b;
    --success: #10b981;
    --success-light: #d1fae5;
    --warning: #f59e0b;
    --warning-light: #fef3c7;
    --danger: #ef4444;
    --info: #3b82f6;
    --info-light: #dbeafe;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --transition: all 0.3s ease;
}

.tab-content-improved {
    padding: 1.5rem;
    background-color: var(--surface-soft);
}

/* Dashboard Styles */
.stat-card-dashboard {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    height: 100%;
}
.stat-card-dashboard:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.stat-icon.bg-warning-light {
    background-color: var(--warning-light);
    color: var(--warning);
}
.stat-icon.bg-primary-light {
    background-color: var(--primary-light);
    color: var(--primary);
}
.stat-icon.bg-success-light {
    background-color: var(--success-light);
    color: var(--success);
}
.stat-icon.bg-info-light {
    background-color: var(--info-light);
    color: var(--info);
}
.stat-content h4 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: var(--dark);
}
.stat-content p {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.chart-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    height: 100%;
}
.chart-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1.5rem;
}

/* Filter & Search Section */
.filter-section {
    background: var(--surface);
    padding: 1rem;
    border-radius: 12px;
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
.search-input,
.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    transition: var(--transition);
}
.search-input {
    padding-left: 2.5rem;
}
.search-input:focus,
.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Pengajuan List Styles */
.pengajuan-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}
.pengajuan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.pengajuan-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-lg);
    transform: translateY(-5px);
}
.pengajuan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.employee-info {
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
.employee-name {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}
.employee-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0;
}
.pengajuan-meta {
    text-align: right;
}
.pengajuan-date {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: block;
    margin-top: 0.25rem;
}
.pengajuan-content {
    flex-grow: 1;
    margin-bottom: 1.5rem;
}
.pengajuan-details {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    color: var(--text);
    font-size: 0.9rem;
}
.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.detail-item i {
    color: var(--primary);
}
.pengajuan-reason {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    font-style: italic;
    color: var(--text-muted);
}
.pengajuan-footer {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}
.pengajuan-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
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
    transition: var(--transition);
}
.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}
.btn-view:hover {
    background: var(--border);
}
.btn-success {
    background: var(--success-light);
    color: var(--success);
}
.btn-success:hover {
    background: var(--success);
    color: white;
}
.btn-danger {
    background: #fee2e2;
    color: var(--danger);
}
.btn-danger:hover {
    background: var(--danger);
    color: white;
}

/* Riwayat Table */
.table-responsive {
    overflow-x: auto;
}
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
    color: var(--text);
}
.modern-table tr:hover {
    background-color: var(--surface-soft);
}

/* Modal Styles */
.detail-content {
    padding: 1rem;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}
.detail-label {
    font-weight: 600;
    color: var(--text-muted);
}
.detail-value {
    color: var(--dark);
    text-align: right;
}
.approval-trace-section {
    flex-direction: column;
    align-items: flex-start;
}
.approval-trace {
    width: 100%;
    margin-top: 0.5rem;
}
.trace-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: var(--transition);
}
.trace-item.trace-approved {
    background-color: var(--success-light);
}
.trace-item.trace-rejected {
    background-color: #fee2e2;
}
.trace-item.trace-pending {
    background-color: var(--surface-soft);
    border: 1px solid var(--border);
}
.trace-icon {
    font-size: 1.25rem;
}
.trace-approved .trace-icon {
    color: var(--success);
}
.trace-rejected .trace-icon {
    color: var(--danger);
}
.trace-pending .trace-icon {
    color: var(--warning);
}
.trace-info {
    flex-grow: 1;
}
.trace-name {
    font-weight: 600;
    color: var(--dark);
}
.trace-date {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.file-link {
    color: var(--primary);
    text-decoration: none;
}
.file-link:hover {
    text-decoration: underline;
}
.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
.btn-modern {
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}
.btn-modern.btn-secondary {
    background: var(--surface-soft);
    color: var(--text);
}
.btn-modern.btn-secondary:hover {
    background: var(--border);
}
.btn-modern.btn-success {
    background: var(--success);
    color: white;
}
.btn-modern.btn-danger {
    background: var(--danger);
    color: white;
}

/* Loading & Empty States */
.loading-state,
.empty-state-admin {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem;
    text-align: center;
    color: var(--text-muted);
}
.loading-spinner {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
    animation: spin 1.5s linear infinite;
}
.empty-state-admin i {
    font-size: 4rem;
    color: var(--border);
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

@media (max-width: 768px) {
    .pengajuan-list {
        grid-template-columns: 1fr;
    }
    .stat-card-dashboard {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
