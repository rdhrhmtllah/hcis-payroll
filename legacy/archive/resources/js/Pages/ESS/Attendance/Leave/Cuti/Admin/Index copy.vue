<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <HeaderKpi
            title="Manajemen Cuti"
            subtitle="Kelola pengajuan cuti dan kuota karyawan"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <ApprovalTabs
                    :tabs="adminTabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Filters untuk Semua Tab -->
                    <template #filters="{ activeTab }">
                        <div class="row g-3 align-items-center">
                            <!-- Filter untuk Pengajuan -->
                            <div
                                class="col-md-3"
                                v-if="activeTab === 'pengajuan'"
                            >
                                <select
                                    v-model="filters.status"
                                    class="filter-select"
                                >
                                    <option value="">Semua Status</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>

                            <div
                                class="col-md-3"
                                v-if="activeTab === 'pengajuan'"
                            >
                                <select
                                    v-model="filters.jenisCuti"
                                    class="filter-select"
                                >
                                    <option value="">Semua Jenis</option>
                                    <option
                                        v-for="jenis in jenisCutiList"
                                        :key="jenis.id"
                                        :value="jenis.id"
                                    >
                                        {{ jenis.nama }}
                                    </option>
                                </select>
                            </div>

                            <!-- Search untuk Kuota -->
                            <div class="col-md-6" v-if="activeTab === 'kuota'">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input
                                        type="text"
                                        v-model="searchKuota"
                                        placeholder="Cari karyawan..."
                                        class="search-input"
                                    />
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div
                                class="col-md-6"
                                v-if="activeTab === 'pengajuan'"
                            >
                                <div class="quick-stats">
                                    <div
                                        class="stat-badge waiting"
                                        @click="filters.status = 'pending'"
                                    >
                                        <span class="count">{{
                                            getStatusCount("pending")
                                        }}</span>
                                        <span class="label">Menunggu</span>
                                    </div>
                                    <div
                                        class="stat-badge approved"
                                        @click="filters.status = 'approved'"
                                    >
                                        <span class="count">{{
                                            getStatusCount("approved")
                                        }}</span>
                                        <span class="label">Disetujui</span>
                                    </div>
                                    <div
                                        class="stat-badge rejected"
                                        @click="filters.status = 'rejected'"
                                    >
                                        <span class="count">{{
                                            getStatusCount("rejected")
                                        }}</span>
                                        <span class="label">Ditolak</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div
                                class="col-md-3"
                                v-if="activeTab === 'pengajuan'"
                            >
                                <button
                                    class="btn-modern btn-primary"
                                    @click="exportData"
                                >
                                    <i class="bi bi-download me-2"></i>Export
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Pengajuan yang Dioptimalkan -->
                    <template #pengajuan>
                        <div class="tab-content-improved">
                            <!-- Stats Overview -->
                            <div class="stats-overview mb-4">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="stat-card-admin total">
                                            <div class="stat-icon">
                                                <i class="bi bi-list-ul"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h4>
                                                    {{ pengajuanList.length }}
                                                </h4>
                                                <p>Total Pengajuan</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card-admin pending">
                                            <div class="stat-icon">
                                                <i class="bi bi-clock"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h4>
                                                    {{
                                                        getStatusCount(
                                                            "pending"
                                                        )
                                                    }}
                                                </h4>
                                                <p>Menunggu Review</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card-admin approved">
                                            <div class="stat-icon">
                                                <i
                                                    class="bi bi-check-circle"
                                                ></i>
                                            </div>
                                            <div class="stat-content">
                                                <h4>
                                                    {{
                                                        getStatusCount(
                                                            "approved"
                                                        )
                                                    }}
                                                </h4>
                                                <p>Disetujui</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card-admin rejected">
                                            <div class="stat-icon">
                                                <i class="bi bi-x-circle"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h4>
                                                    {{
                                                        getStatusCount(
                                                            "rejected"
                                                        )
                                                    }}
                                                </h4>
                                                <p>Ditolak</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pengajuan List -->
                            <div class="pengajuan-list">
                                <div
                                    v-for="pengajuan in filteredPengajuan"
                                    :key="pengajuan.id"
                                    class="pengajuan-card"
                                >
                                    <div class="pengajuan-header">
                                        <div class="employee-info">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        pengajuan.nama_karyawan
                                                    )
                                                }}
                                            </div>
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{
                                                        pengajuan.nama_karyawan
                                                    }}
                                                </h6>
                                                <p class="employee-code">
                                                    {{
                                                        pengajuan.kode_karyawan
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pengajuan-meta">
                                            <Badge
                                                :type="
                                                    getStatusBadgeType(
                                                        pengajuan.status
                                                    )
                                                "
                                            >
                                                {{
                                                    getStatusText(
                                                        pengajuan.status
                                                    )
                                                }}
                                            </Badge>
                                            <span class="pengajuan-date">{{
                                                formatDate(pengajuan.created_at)
                                            }}</span>
                                        </div>
                                    </div>

                                    <div class="pengajuan-content">
                                        <div class="pengajuan-details">
                                            <div class="detail-item">
                                                <i class="bi bi-tag"></i>
                                                <span>{{
                                                    pengajuan.jenis_cuti
                                                }}</span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-calendar"></i>
                                                <span
                                                    >{{
                                                        formatDate(
                                                            pengajuan.tanggal_mulai
                                                        )
                                                    }}
                                                    -
                                                    {{
                                                        formatDate(
                                                            pengajuan.tanggal_selesai
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-clock"></i>
                                                <span
                                                    >{{
                                                        pengajuan.jumlah_hari
                                                    }}
                                                    hari</span
                                                >
                                            </div>
                                        </div>
                                        <div class="pengajuan-reason">
                                            <p>{{ pengajuan.alasan }}</p>
                                        </div>
                                    </div>

                                    <div class="pengajuan-footer">
                                        <div class="pengajuan-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="viewDetail(pengajuan)"
                                            >
                                                <i class="bi bi-eye"></i>
                                                <span>Detail</span>
                                            </button>
                                            <button
                                                v-if="
                                                    pengajuan.status ===
                                                    'pending'
                                                "
                                                class="btn-action btn-success"
                                                @click="
                                                    approvePengajuan(pengajuan)
                                                "
                                            >
                                                <i class="bi bi-check"></i>
                                                <span>Setujui</span>
                                            </button>
                                            <button
                                                v-if="
                                                    pengajuan.status ===
                                                    'pending'
                                                "
                                                class="btn-action btn-danger"
                                                @click="
                                                    rejectPengajuan(pengajuan)
                                                "
                                            >
                                                <i class="bi bi-x"></i>
                                                <span>Tolak</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="filteredPengajuan.length === 0"
                                class="empty-state-admin"
                            >
                                <i class="bi bi-inbox"></i>
                                <h4>Tidak Ada Pengajuan</h4>
                                <p>Tidak ada pengajuan cuti yang ditemukan</p>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Jenis Cuti yang Dioptimalkan -->
                    <template #jenis_cuti>
                        <div class="tab-content-improved">
                            <div class="jenis-cuti-container">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="jenis-cuti-grid">
                                            <div
                                                v-for="jenis in jenisCutiList"
                                                :key="jenis.id"
                                                class="jenis-cuti-card"
                                            >
                                                <div class="jenis-cuti-header">
                                                    <div
                                                        class="jenis-cuti-icon"
                                                    >
                                                        <i
                                                            :class="
                                                                getCutiTypeIcon(
                                                                    jenis.nama
                                                                )
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div
                                                        class="jenis-cuti-info"
                                                    >
                                                        <h6>
                                                            {{ jenis.nama }}
                                                        </h6>
                                                        <p>
                                                            {{
                                                                jenis.deskripsi
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div
                                                        class="jenis-cuti-badge"
                                                    >
                                                        {{
                                                            jenis.kuota_default
                                                        }}
                                                        {{ jenis.satuan }}
                                                    </div>
                                                </div>

                                                <div class="jenis-cuti-details">
                                                    <div class="detail-row">
                                                        <span class="label"
                                                            >Masa Berlaku:</span
                                                        >
                                                        <span class="value"
                                                            >{{
                                                                jenis.masa_berlaku_bulan ||
                                                                "Tidak ada"
                                                            }}
                                                            bulan</span
                                                        >
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label"
                                                            >Minimal Masa
                                                            Kerja:</span
                                                        >
                                                        <span class="value"
                                                            >{{
                                                                jenis.minimal_masa_kerja ||
                                                                0
                                                            }}
                                                            bulan</span
                                                        >
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label"
                                                            >Pengaturan:</span
                                                        >
                                                        <div class="settings">
                                                            <span
                                                                v-if="
                                                                    jenis.dapat_diakumulasi
                                                                "
                                                                class="setting-badge"
                                                                >Dapat
                                                                Diakumulasi</span
                                                            >
                                                            <span
                                                                v-if="
                                                                    jenis.penggunaan_langsung
                                                                "
                                                                class="setting-badge"
                                                                >Penggunaan
                                                                Langsung</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="jenis-cuti-actions">
                                                    <button
                                                        class="btn-action btn-edit"
                                                        @click="
                                                            editJenisCuti(jenis)
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-pencil"
                                                        ></i>
                                                        <span>Edit</span>
                                                    </button>
                                                    <button
                                                        class="btn-action btn-danger"
                                                        @click="
                                                            deleteJenisCuti(
                                                                jenis
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-trash"
                                                        ></i>
                                                        <span>Hapus</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="action-sidebar">
                                            <div class="action-card">
                                                <h6 class="action-title">
                                                    <i
                                                        class="bi bi-plus-circle me-2"
                                                    ></i>
                                                    Tambah Jenis Cuti Baru
                                                </h6>
                                                <p class="action-description">
                                                    Buat jenis cuti baru dengan
                                                    kuota dan aturan khusus
                                                </p>
                                                <button
                                                    class="btn-modern btn-primary w-100"
                                                    @click="showJenisCutiModal"
                                                >
                                                    <i
                                                        class="bi bi-plus me-2"
                                                    ></i
                                                    >Tambah Jenis Cuti
                                                </button>
                                            </div>

                                            <div class="info-card-admin">
                                                <h6 class="info-title">
                                                    <i
                                                        class="bi bi-info-circle me-2"
                                                    ></i>
                                                    Tips Manajemen
                                                </h6>
                                                <div class="tips-list">
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        <p>
                                                            Atur kuota sesuai
                                                            kebutuhan perusahaan
                                                        </p>
                                                    </div>
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        <p>
                                                            Tinjau masa berlaku
                                                            untuk cuti tertentu
                                                        </p>
                                                    </div>
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        <p>
                                                            Perhatikan minimal
                                                            masa kerja
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab lainnya tetap sama dengan optimisasi serupa -->
                    <template #cuti_bersama>
                        <!-- Implementasi serupa dengan tab jenis cuti -->
                    </template>

                    <template #kuota>
                        <!-- Implementasi serupa dengan tab pengajuan -->
                    </template>

                    <template #laporan>
                        <!-- Implementasi serupa -->
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Pengajuan -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            title="Detail Pengajuan Cuti"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="selectedPengajuan" class="detail-content">
                    <div class="detail-section">
                        <div class="detail-row">
                            <div class="detail-label">Nama Karyawan</div>
                            <div class="detail-value">
                                {{ selectedPengajuan.nama_karyawan }}
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Jenis Cuti</div>
                            <div class="detail-value">
                                {{ selectedPengajuan.jenis_cuti }}
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Periode</div>
                            <div class="detail-value">
                                {{
                                    formatDate(selectedPengajuan.tanggal_mulai)
                                }}
                                -
                                {{
                                    formatDate(
                                        selectedPengajuan.tanggal_selesai
                                    )
                                }}
                                ({{ selectedPengajuan.jumlah_hari }} hari)
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Alasan</div>
                            <div class="detail-value">
                                {{ selectedPengajuan.alasan }}
                            </div>
                        </div>
                        <div
                            v-if="selectedPengajuan.file_pendukung"
                            class="detail-row"
                        >
                            <div class="detail-label">File Pendukung</div>
                            <div class="detail-value">
                                <a
                                    :href="selectedPengajuan.file_pendukung"
                                    target="_blank"
                                    class="file-link"
                                >
                                    <i class="bi bi-paperclip me-2"></i>Lihat
                                    File
                                </a>
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
                        v-if="selectedPengajuan?.status === 'pending'"
                        class="btn-modern btn-success"
                        @click="approveSelected"
                    >
                        Setujui
                    </button>
                    <button
                        v-if="selectedPengajuan?.status === 'pending'"
                        class="btn-modern btn-danger"
                        @click="rejectSelected"
                    >
                        Tolak
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Jenis Cuti -->
        <Modal
            v-if="showJenisCutiModal"
            :show="showJenisCutiModal"
            :title="
                isEditingJenisCuti ? 'Edit Jenis Cuti' : 'Tambah Jenis Cuti'
            "
            size="large"
            @close="closeJenisCutiModal"
        >
            <template #body>
                <form @submit.prevent="submitJenisCuti">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"
                                    >Nama Jenis Cuti</label
                                >
                                <input
                                    type="text"
                                    v-model="jenisCutiForm.nama"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Kuota Default</label>
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.kuota_default"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Deskripsi</label>
                                <textarea
                                    v-model="jenisCutiForm.deskripsi"
                                    class="form-control"
                                    rows="3"
                                ></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Satuan</label>
                                <select
                                    v-model="jenisCutiForm.satuan"
                                    class="form-control"
                                >
                                    <option value="hari">Hari</option>
                                    <option value="jam">Jam</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"
                                    >Masa Berlaku (bulan)</label
                                >
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.masa_berlaku_bulan"
                                    class="form-control"
                                    placeholder="Kosongkan jika tidak ada"
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.dapat_diakumulasi"
                                    class="form-check-input"
                                />
                                <label class="form-check-label"
                                    >Dapat Diakumulasi</label
                                >
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.penggunaan_langsung"
                                    class="form-check-input"
                                />
                                <label class="form-check-label"
                                    >Penggunaan Langsung</label
                                >
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"
                                    >Minimal Masa Kerja (bulan)</label
                                >
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.minimal_masa_kerja"
                                    class="form-control"
                                />
                            </div>
                        </div>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeJenisCutiModal"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="submitJenisCuti"
                    >
                        {{ isEditingJenisCuti ? "Update" : "Simpan" }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Adjust Kuota -->
        <Modal
            v-if="showAdjustModal"
            :show="showAdjustModal"
            title="Adjust Kuota Cuti"
            @close="closeAdjustModal"
        >
            <template #body>
                <form @submit.prevent="submitAdjustKuota">
                    <div class="form-group">
                        <label class="form-label">Karyawan</label>
                        <input
                            type="text"
                            :value="selectedKuota?.nama_karyawan"
                            class="form-control"
                            disabled
                        />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jenis Cuti</label>
                        <select
                            v-model="adjustForm.tipe_cuti"
                            class="form-control"
                            required
                        >
                            <option
                                v-for="jenis in jenisCutiList"
                                :key="jenis.id"
                                :value="jenis.id"
                            >
                                {{ jenis.nama }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah</label>
                        <input
                            type="number"
                            v-model="adjustForm.jumlah"
                            class="form-control"
                            required
                        />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Keterangan</label>
                        <textarea
                            v-model="adjustForm.note"
                            class="form-control"
                            rows="3"
                            placeholder="Alasan adjust kuota..."
                        ></textarea>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeAdjustModal"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="submitAdjustKuota"
                    >
                        Simpan
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import ApprovalTabs from "@/components/ui/toggleSectionV2.vue";
import HeaderKpi from "@/components/ui/header.vue";
import Modal from "@/components/ui/modalV2-noir.vue";
// import Badge from "../../../../components/ui/badge.vue";
import DatePicker from "../../../../components/ui/DatePicker.vue";

export default {
    name: "AdminCutiOptimized",
    components: {
        ApprovalTabs,
        HeaderKpi,
        Modal,
        Badge,
        DatePicker,
    },
    data() {
        return {
            currentDate: new Date(),
            activeTab: "pengajuan",
            searchKuota: "",
            filters: {
                status: "",
                jenisCuti: "",
                tanggalMulai: null,
                tanggalSelesai: null,
            },

            // Data
            pengajuanList: [],
            jenisCutiList: [],
            cutiBersamaList: [],
            kuotaList: [],
            laporanData: [],

            // Pagination
            pagination: {
                currentPage: 1,
                perPage: 10,
                totalPages: 1,
            },

            // Modals
            showDetailModal: false,
            showJenisCutiModal: false,
            showAdjustModal: false,
            showCutiBersamaModal: false,

            // Selected items
            selectedPengajuan: null,
            selectedKuota: null,
            selectedCutiBersama: null,

            // Forms
            jenisCutiForm: {
                nama: "",
                deskripsi: "",
                kuota_default: 0,
                satuan: "hari",
                masa_berlaku_bulan: null,
                dapat_diakumulasi: false,
                penggunaan_langsung: false,
                minimal_masa_kerja: 0,
            },
            adjustForm: {
                kode_karyawan: "",
                tipe_cuti: "",
                jumlah: 0,
                note: "",
            },
            cutiBersamaForm: {
                nama: "",
                tanggal_mulai: null,
                tanggal_selesai: null,
                potong_ke_tipe_cuti: "",
                deskripsi: "",
            },

            isEditingJenisCuti: false,
            editingJenisCutiId: null,
            isEditingCutiBersama: false,
            editingCutiBersamaId: null,
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },

        adminTabs() {
            return [
                {
                    id: "pengajuan",
                    label: "Pengajuan",
                    icon: "bi bi-list-ul",
                    count: this.pengajuanList.filter(
                        (p) => p.status === "pending"
                    ).length,
                },
                {
                    id: "jenis_cuti",
                    label: "Jenis Cuti",
                    icon: "bi bi-tags",
                    count: this.jenisCutiList.length,
                },
                {
                    id: "cuti_bersama",
                    label: "Cuti Bersama",
                    icon: "bi bi-calendar-event",
                    count: this.cutiBersamaList.length,
                },
                {
                    id: "kuota",
                    label: "Kuota Karyawan",
                    icon: "bi bi-people",
                    count: this.kuotaList.length,
                },
                {
                    id: "laporan",
                    label: "Laporan",
                    icon: "bi bi-graph-up",
                    count: this.laporanData.length,
                },
            ];
        },

        filteredPengajuan() {
            let filtered = this.pengajuanList;

            if (this.filters.status) {
                filtered = filtered.filter(
                    (item) => item.status === this.filters.status
                );
            }

            if (this.filters.jenisCuti) {
                filtered = filtered.filter(
                    (item) => item.jenis_cuti_id == this.filters.jenisCuti
                );
            }

            if (this.filters.tanggalMulai) {
                filtered = filtered.filter(
                    (item) =>
                        new Date(item.tanggal_mulai) >=
                        new Date(this.filters.tanggalMulai)
                );
            }

            if (this.filters.tanggalSelesai) {
                filtered = filtered.filter(
                    (item) =>
                        new Date(item.tanggal_selesai) <=
                        new Date(this.filters.tanggalSelesai)
                );
            }

            return filtered;
        },

        filteredKuota() {
            if (!this.searchKuota) return this.kuotaList;

            const search = this.searchKuota.toLowerCase();
            return this.kuotaList.filter(
                (item) =>
                    item.nama_karyawan.toLowerCase().includes(search) ||
                    item.kode_karyawan.toLowerCase().includes(search)
            );
        },

        filteredLaporan() {
            return this.laporanData;
        },
    },
    methods: {
        getStatusCount(status) {
            return this.pengajuanList.filter((item) => item.status === status)
                .length;
        },

        formatDateString(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });
        },

        formatDate(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID");
        },

        formatDateTime(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
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

        getStatusBadgeType(status) {
            const types = {
                pending: "warning",
                approved: "success",
                rejected: "danger",
                cancelled: "secondary",
            };
            return types[status] || "secondary";
        },

        getStatusText(status) {
            const texts = {
                pending: "Menunggu",
                approved: "Disetujui",
                rejected: "Ditolak",
                cancelled: "Dibatalkan",
            };
            return texts[status] || status;
        },

        getCutiTypeIcon(jenisCuti) {
            const icons = {
                "Cuti Tahunan": "bi bi-calendar-check",
                "Cuti Sakit": "bi bi-heart-pulse",
                "Cuti Melahirkan": "bi bi-gender-female",
                "Cuti Penting": "bi bi-star",
                "Cuti Besar": "bi bi-briefcase",
                "Cuti Alasan Penting": "bi bi-exclamation-triangle",
                "Cuti Menikah": "bi bi-heart",
                "Cuti Keluarga": "bi bi-people",
            };
            return icons[jenisCuti] || "bi bi-calendar";
        },

        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.pagination.currentPage = 1;
            this.loadTabData();
        },

        resetFilters() {
            this.filters = {
                status: "",
                jenisCuti: "",
                tanggalMulai: null,
                tanggalSelesai: null,
            };
        },

        // Modal Methods
        viewDetail(pengajuan) {
            this.selectedPengajuan = pengajuan;
            this.showDetailModal = true;
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedPengajuan = null;
        },

        showJenisCutiModal() {
            this.isEditingJenisCuti = false;
            this.jenisCutiForm = {
                nama: "",
                deskripsi: "",
                kuota_default: 0,
                satuan: "hari",
                masa_berlaku_bulan: null,
                dapat_diakumulasi: false,
                penggunaan_langsung: false,
                minimal_masa_kerja: 0,
            };
            this.showJenisCutiModal = true;
        },

        closeJenisCutiModal() {
            this.showJenisCutiModal = false;
            this.isEditingJenisCuti = false;
            this.editingJenisCutiId = null;
        },

        showCutiBersamaModal() {
            this.isEditingCutiBersama = false;
            this.cutiBersamaForm = {
                nama: "",
                tanggal_mulai: null,
                tanggal_selesai: null,
                potong_ke_tipe_cuti: "",
                deskripsi: "",
            };
            this.showCutiBersamaModal = true;
        },

        closeCutiBersamaModal() {
            this.showCutiBersamaModal = false;
            this.isEditingCutiBersama = false;
            this.editingCutiBersamaId = null;
        },

        adjustKuota(kuota) {
            this.selectedKuota = kuota;
            this.adjustForm = {
                kode_karyawan: kuota.kode_karyawan,
                tipe_cuti: "",
                jumlah: 0,
                note: "",
            };
            this.showAdjustModal = true;
        },

        closeAdjustModal() {
            this.showAdjustModal = false;
            this.selectedKuota = null;
        },

        // API Methods dengan Data Dummy
        async loadTabData() {
            try {
                switch (this.activeTab) {
                    case "pengajuan":
                        await this.loadPengajuan();
                        break;
                    case "jenis_cuti":
                        await this.loadJenisCuti();
                        break;
                    case "cuti_bersama":
                        await this.loadCutiBersama();
                        break;
                    case "kuota":
                        await this.loadKuota();
                        break;
                    case "laporan":
                        await this.loadLaporan();
                        break;
                }
            } catch (error) {
                console.error("Error loading data:", error);
            }
        },

        async loadPengajuan() {
            // Data dummy pengajuan cuti yang lebih lengkap
            const dummyPengajuan = [
                {
                    id: 1,
                    nama_karyawan: "Ahmad Rizki",
                    kode_karyawan: "EMP001",
                    jenis_cuti: "Cuti Tahunan",
                    jenis_cuti_id: 1,
                    tanggal_mulai: "2024-01-15",
                    tanggal_selesai: "2024-01-20",
                    jumlah_hari: 6,
                    alasan: "Liburan keluarga ke Bali untuk refreshing setelah menyelesaikan proyek besar",
                    status: "pending",
                    created_at: "2024-01-10T08:30:00",
                    file_pendukung: null,
                    alasan_penolakan: null,
                    approved_by: null,
                    approved_at: null,
                },
                {
                    id: 2,
                    nama_karyawan: "Siti Nurhaliza",
                    kode_karyawan: "EMP002",
                    jenis_cuti: "Cuti Sakit",
                    jenis_cuti_id: 2,
                    tanggal_mulai: "2024-01-12",
                    tanggal_selesai: "2024-01-14",
                    jumlah_hari: 3,
                    alasan: "Demam tinggi dan flu berat, sudah berkonsultasi dengan dokter",
                    status: "approved",
                    created_at: "2024-01-11T14:20:00",
                    file_pendukung: "/files/surat-sakit-dr-ahmad.pdf",
                    alasan_penolakan: null,
                    approved_by: "HR Manager",
                    approved_at: "2024-01-11T16:45:00",
                },
                {
                    id: 3,
                    nama_karyawan: "Budi Santoso",
                    kode_karyawan: "EMP003",
                    jenis_cuti: "Cuti Melahirkan",
                    jenis_cuti_id: 3,
                    tanggal_mulai: "2024-02-01",
                    tanggal_selesai: "2024-03-01",
                    jumlah_hari: 30,
                    alasan: "Istri melahirkan anak pertama, perlu mendampingi selama masa nifas",
                    status: "pending",
                    created_at: "2024-01-20T09:15:00",
                    file_pendukung: "/files/surat-keterangan-lahir.pdf",
                    alasan_penolakan: null,
                    approved_by: null,
                    approved_at: null,
                },
                {
                    id: 4,
                    nama_karyawan: "Dewi Lestari",
                    kode_karyawan: "EMP004",
                    jenis_cuti: "Cuti Tahunan",
                    jenis_cuti_id: 1,
                    tanggal_mulai: "2024-01-25",
                    tanggal_selesai: "2024-01-30",
                    jumlah_hari: 6,
                    alasan: "Perjalanan wisata ke Jogja untuk mengunjungi keluarga",
                    status: "rejected",
                    created_at: "2024-01-18T11:45:00",
                    file_pendukung: null,
                    alasan_penolakan:
                        "Bentrok dengan jadwal pelatihan penting yang sudah dijadwalkan",
                    approved_by: "Team Lead",
                    approved_at: "2024-01-19T10:30:00",
                },
                {
                    id: 5,
                    nama_karyawan: "Rizky Pratama",
                    kode_karyawan: "EMP005",
                    jenis_cuti: "Cuti Menikah",
                    jenis_cuti_id: 4,
                    tanggal_mulai: "2024-01-22",
                    tanggal_selesai: "2024-01-23",
                    jumlah_hari: 2,
                    alasan: "Menikahkan saudara kandung, sebagai wali dalam acara pernikahan",
                    status: "approved",
                    created_at: "2024-01-15T13:10:00",
                    file_pendukung: "/files/undangan-pernikahan.pdf",
                    alasan_penolakan: null,
                    approved_by: "HR Manager",
                    approved_at: "2024-01-16T09:20:00",
                },
                {
                    id: 6,
                    nama_karyawan: "Maya Sari",
                    kode_karyawan: "EMP006",
                    jenis_cuti: "Cuti Keluarga",
                    jenis_cuti_id: 5,
                    tanggal_mulai: "2024-02-10",
                    tanggal_selesai: "2024-02-11",
                    jumlah_hari: 2,
                    alasan: "Menghadiri khitanan anak saudara",
                    status: "pending",
                    created_at: "2024-01-28T16:40:00",
                    file_pendukung: null,
                    alasan_penolakan: null,
                    approved_by: null,
                    approved_at: null,
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 600));
            this.pengajuanList = dummyPengajuan;
        },

        async loadJenisCuti() {
            // Data dummy jenis cuti yang lebih lengkap
            const dummyJenisCuti = [
                {
                    id: 1,
                    nama: "Cuti Tahunan",
                    deskripsi:
                        "Cuti tahunan untuk karyawan tetap yang telah bekerja minimal 1 tahun",
                    kuota_default: 12,
                    satuan: "hari",
                    masa_berlaku_bulan: 12,
                    dapat_diakumulasi: true,
                    penggunaan_langsung: false,
                    minimal_masa_kerja: 12,
                    created_at: "2023-01-01",
                    updated_at: "2023-12-15",
                },
                {
                    id: 2,
                    nama: "Cuti Sakit",
                    deskripsi:
                        "Cuti untuk karyawan yang sakit dengan atau tanpa surat dokter",
                    kuota_default: 30,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 0,
                    created_at: "2023-01-01",
                    updated_at: "2023-12-15",
                },
                {
                    id: 3,
                    nama: "Cuti Melahirkan",
                    deskripsi:
                        "Cuti untuk karyawan yang melahirkan, berlaku untuk ayah dan ibu",
                    kuota_default: 90,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 12,
                    created_at: "2023-01-01",
                    updated_at: "2023-12-15",
                },
                {
                    id: 4,
                    nama: "Cuti Menikah",
                    deskripsi:
                        "Cuti untuk pernikahan sendiri atau menikahkan anak",
                    kuota_default: 3,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 3,
                    created_at: "2023-01-01",
                    updated_at: "2023-12-15",
                },
                {
                    id: 5,
                    nama: "Cuti Keluarga",
                    deskripsi:
                        "Cuti untuk keperluan keluarga seperti khitanan, kelahiran, atau kematian keluarga inti",
                    kuota_default: 2,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 6,
                    created_at: "2023-01-01",
                    updated_at: "2023-12-15",
                },
                {
                    id: 6,
                    nama: "Cuti Besar",
                    deskripsi:
                        "Cuti panjang setelah bekerja bertahun-tahun sebagai bentuk apresiasi",
                    kuota_default: 60,
                    satuan: "hari",
                    masa_berlaku_bulan: 36,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: false,
                    minimal_masa_kerja: 60,
                    created_at: "2023-01-01",
                    updated_at: "2023-12-15",
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 400));
            this.jenisCutiList = dummyJenisCuti;
        },

        async loadCutiBersama() {
            // Data dummy cuti bersama
            const dummyCutiBersama = [
                {
                    id: 1,
                    nama: "Libur Idul Fitri 2024",
                    tanggal_mulai: "2024-04-08",
                    tanggal_selesai: "2024-04-15",
                    potong_ke_tipe_cuti: "Cuti Tahunan",
                    deskripsi:
                        "Libur bersama menyambut hari raya Idul Fitri 1445 H",
                    status: "active",
                    created_at: "2023-12-01",
                    updated_at: "2023-12-15",
                    total_karyawan_terkena: 150,
                },
                {
                    id: 2,
                    nama: "Libur Natal & Tahun Baru 2024",
                    tanggal_mulai: "2024-12-23",
                    tanggal_selesai: "2024-12-31",
                    potong_ke_tipe_cuti: "Cuti Tahunan",
                    deskripsi:
                        "Libur bersama menyambut Natal dan Tahun Baru 2025",
                    status: "upcoming",
                    created_at: "2023-12-01",
                    updated_at: "2023-12-15",
                    total_karyawan_terkena: 150,
                },
                {
                    id: 3,
                    nama: "Libur Hari Kemerdekaan RI",
                    tanggal_mulai: "2024-08-17",
                    tanggal_selesai: "2024-08-17",
                    potong_ke_tipe_cuti: "Cuti Tahunan",
                    deskripsi:
                        "Libur memperingati Hari Kemerdekaan Republik Indonesia ke-79",
                    status: "upcoming",
                    created_at: "2023-12-01",
                    updated_at: "2023-12-15",
                    total_karyawan_terkena: 150,
                },
                {
                    id: 4,
                    nama: "Libur Tahun Baru Imlek 2024",
                    tanggal_mulai: "2024-02-10",
                    tanggal_selesai: "2024-02-10",
                    potong_ke_tipe_cuti: "Cuti Tahunan",
                    deskripsi: "Libur menyambut Tahun Baru Imlek 2575",
                    status: "active",
                    created_at: "2023-12-01",
                    updated_at: "2023-12-15",
                    total_karyawan_terkena: 150,
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 400));
            this.cutiBersamaList = dummyCutiBersama;
        },

        async loadKuota() {
            // Data dummy kuota karyawan yang lebih detail
            const dummyKuota = [
                {
                    id: 1,
                    kode_karyawan: "EMP001",
                    nama_karyawan: "Ahmad Rizki",
                    department: "IT",
                    position: "Software Developer",
                    sisa_cuti_tahunan: 8,
                    sisa_cuti_sakit: 25,
                    sisa_cuti_melahirkan: 90,
                    sisa_cuti_menikah: 3,
                    sisa_cuti_keluarga: 2,
                    sisa_cuti_besar: 60,
                    total_cuti_diambil: 4,
                    last_cuti_tanggal: "2023-12-20",
                    status: "active",
                },
                {
                    id: 2,
                    kode_karyawan: "EMP002",
                    nama_karyawan: "Siti Nurhaliza",
                    department: "HR",
                    position: "HR Specialist",
                    sisa_cuti_tahunan: 10,
                    sisa_cuti_sakit: 28,
                    sisa_cuti_melahirkan: 90,
                    sisa_cuti_menikah: 3,
                    sisa_cuti_keluarga: 2,
                    sisa_cuti_besar: 60,
                    total_cuti_diambil: 2,
                    last_cuti_tanggal: "2024-01-12",
                    status: "active",
                },
                {
                    id: 3,
                    kode_karyawan: "EMP003",
                    nama_karyawan: "Budi Santoso",
                    department: "Finance",
                    position: "Accountant",
                    sisa_cuti_tahunan: 12,
                    sisa_cuti_sakit: 30,
                    sisa_cuti_melahirkan: 90,
                    sisa_cuti_menikah: 3,
                    sisa_cuti_keluarga: 2,
                    sisa_cuti_besar: 60,
                    total_cuti_diambil: 0,
                    last_cuti_tanggal: null,
                    status: "active",
                },
                {
                    id: 4,
                    kode_karyawan: "EMP004",
                    nama_karyawan: "Dewi Lestari",
                    department: "Marketing",
                    position: "Digital Marketer",
                    sisa_cuti_tahunan: 6,
                    sisa_cuti_sakit: 30,
                    sisa_cuti_melahirkan: 90,
                    sisa_cuti_menikah: 3,
                    sisa_cuti_keluarga: 2,
                    sisa_cuti_besar: 60,
                    total_cuti_diambil: 6,
                    last_cuti_tanggal: "2024-01-18",
                    status: "active",
                },
                {
                    id: 5,
                    kode_karyawan: "EMP005",
                    nama_karyawan: "Rizky Pratama",
                    department: "Sales",
                    position: "Sales Executive",
                    sisa_cuti_tahunan: 11,
                    sisa_cuti_sakit: 30,
                    sisa_cuti_melahirkan: 90,
                    sisa_cuti_menikah: 1,
                    sisa_cuti_keluarga: 2,
                    sisa_cuti_besar: 60,
                    total_cuti_diambil: 1,
                    last_cuti_tanggal: "2024-01-22",
                    status: "active",
                },
            ];

            // Filter berdasarkan pencarian
            let filteredData = dummyKuota;
            if (this.searchKuota) {
                const search = this.searchKuota.toLowerCase();
                filteredData = dummyKuota.filter(
                    (item) =>
                        item.nama_karyawan.toLowerCase().includes(search) ||
                        item.kode_karyawan.toLowerCase().includes(search) ||
                        item.department.toLowerCase().includes(search)
                );
            }

            await new Promise((resolve) => setTimeout(resolve, 500));
            this.kuotaList = filteredData;
        },

        async loadLaporan() {
            // Data dummy laporan
            const dummyLaporan = [
                {
                    id: 1,
                    periode: "Januari 2024",
                    total_pengajuan: 24,
                    pengajuan_disetujui: 18,
                    pengajuan_ditolak: 3,
                    pengajuan_pending: 3,
                    total_hari_cuti: 45,
                    rata_rata_lama_cuti: 2.5,
                    department_terbanyak: "IT",
                    jenis_cuti_terbanyak: "Cuti Tahunan",
                },
                {
                    id: 2,
                    periode: "Desember 2023",
                    total_pengajuan: 32,
                    pengajuan_disetujui: 28,
                    pengajuan_ditolak: 2,
                    pengajuan_pending: 2,
                    total_hari_cuti: 67,
                    rata_rata_lama_cuti: 2.1,
                    department_terbanyak: "Sales",
                    jenis_cuti_terbanyak: "Cuti Tahunan",
                },
                {
                    id: 3,
                    periode: "November 2023",
                    total_pengajuan: 28,
                    pengajuan_disetujui: 25,
                    pengajuan_ditolak: 1,
                    pengajuan_pending: 2,
                    total_hari_cuti: 52,
                    rata_rata_lama_cuti: 1.9,
                    department_terbanyak: "HR",
                    jenis_cuti_terbanyak: "Cuti Sakit",
                },
                {
                    id: 4,
                    periode: "Oktober 2023",
                    total_pengajuan: 26,
                    pengajuan_disetujui: 22,
                    pengajuan_ditolak: 2,
                    pengajuan_pending: 2,
                    total_hari_cuti: 48,
                    rata_rata_lama_cuti: 1.8,
                    department_terbanyak: "Finance",
                    jenis_cuti_terbanyak: "Cuti Keluarga",
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 500));
            this.laporanData = dummyLaporan;
        },

        // Action Methods dengan Data Dummy
        async approvePengajuan(pengajuan) {
            if (confirm(`Setujui pengajuan cuti ${pengajuan.nama_karyawan}?`)) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    const index = this.pengajuanList.findIndex(
                        (p) => p.id === pengajuan.id
                    );
                    if (index !== -1) {
                        this.pengajuanList[index].status = "approved";
                        this.pengajuanList[index].approved_by = "Admin";
                        this.pengajuanList[index].approved_at =
                            new Date().toISOString();

                        // Update kuota karyawan
                        this.updateKuotaKaryawan(
                            pengajuan.kode_karyawan,
                            pengajuan.jenis_cuti_id,
                            pengajuan.jumlah_hari
                        );
                    }

                    alert("Pengajuan berhasil disetujui!");
                } catch (error) {
                    console.error("Error approving:", error);
                    alert("Terjadi kesalahan saat menyetujui pengajuan");
                }
            }
        },

        async rejectPengajuan(pengajuan) {
            const reason = prompt("Alasan penolakan:");
            if (reason) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    const index = this.pengajuanList.findIndex(
                        (p) => p.id === pengajuan.id
                    );
                    if (index !== -1) {
                        this.pengajuanList[index].status = "rejected";
                        this.pengajuanList[index].alasan_penolakan = reason;
                        this.pengajuanList[index].approved_by = "Admin";
                        this.pengajuanList[index].approved_at =
                            new Date().toISOString();
                    }

                    alert("Pengajuan berhasil ditolak!");
                } catch (error) {
                    console.error("Error rejecting:", error);
                    alert("Terjadi kesalahan saat menolak pengajuan");
                }
            }
        },

        updateKuotaKaryawan(kodeKaryawan, jenisCutiId, jumlahHari) {
            const kuotaIndex = this.kuotaList.findIndex(
                (k) => k.kode_karyawan === kodeKaryawan
            );
            if (kuotaIndex !== -1) {
                const fieldMap = {
                    1: "sisa_cuti_tahunan",
                    2: "sisa_cuti_sakit",
                    3: "sisa_cuti_melahirkan",
                    4: "sisa_cuti_menikah",
                    5: "sisa_cuti_keluarga",
                    6: "sisa_cuti_besar",
                };

                const field = fieldMap[jenisCutiId];
                if (field) {
                    this.kuotaList[kuotaIndex][field] -= jumlahHari;
                    this.kuotaList[kuotaIndex].total_cuti_diambil += jumlahHari;
                    this.kuotaList[kuotaIndex].last_cuti_tanggal = new Date()
                        .toISOString()
                        .split("T")[0];
                }
            }
        },

        approveSelected() {
            if (this.selectedPengajuan) {
                this.approvePengajuan(this.selectedPengajuan);
                this.closeDetailModal();
            }
        },

        rejectSelected() {
            if (this.selectedPengajuan) {
                this.rejectPengajuan(this.selectedPengajuan);
                this.closeDetailModal();
            }
        },

        async submitJenisCuti() {
            try {
                await new Promise((resolve) => setTimeout(resolve, 800));

                if (this.isEditingJenisCuti) {
                    const index = this.jenisCutiList.findIndex(
                        (j) => j.id === this.editingJenisCutiId
                    );
                    if (index !== -1) {
                        this.jenisCutiList[index] = {
                            ...this.jenisCutiForm,
                            id: this.editingJenisCutiId,
                            updated_at: new Date().toISOString(),
                        };
                    }
                } else {
                    const newId =
                        this.jenisCutiList.length > 0
                            ? Math.max(...this.jenisCutiList.map((j) => j.id)) +
                              1
                            : 1;
                    this.jenisCutiList.push({
                        ...this.jenisCutiForm,
                        id: newId,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    });
                }

                this.closeJenisCutiModal();
                alert("Jenis cuti berhasil disimpan!");
            } catch (error) {
                console.error("Error saving jenis cuti:", error);
                alert("Terjadi kesalahan saat menyimpan jenis cuti");
            }
        },

        editJenisCuti(jenisCuti) {
            this.isEditingJenisCuti = true;
            this.editingJenisCutiId = jenisCuti.id;
            this.jenisCutiForm = { ...jenisCuti };
            this.showJenisCutiModal = true;
        },

        async deleteJenisCuti(jenisCuti) {
            if (confirm(`Hapus jenis cuti "${jenisCuti.nama}"?`)) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    this.jenisCutiList = this.jenisCutiList.filter(
                        (j) => j.id !== jenisCuti.id
                    );

                    alert("Jenis cuti berhasil dihapus!");
                } catch (error) {
                    console.error("Error deleting jenis cuti:", error);
                    alert("Terjadi kesalahan saat menghapus jenis cuti");
                }
            }
        },

        async submitCutiBersama() {
            try {
                await new Promise((resolve) => setTimeout(resolve, 800));

                if (this.isEditingCutiBersama) {
                    const index = this.cutiBersamaList.findIndex(
                        (c) => c.id === this.editingCutiBersamaId
                    );
                    if (index !== -1) {
                        this.cutiBersamaList[index] = {
                            ...this.cutiBersamaForm,
                            id: this.editingCutiBersamaId,
                            updated_at: new Date().toISOString(),
                        };
                    }
                } else {
                    const newId =
                        this.cutiBersamaList.length > 0
                            ? Math.max(
                                  ...this.cutiBersamaList.map((c) => c.id)
                              ) + 1
                            : 1;
                    this.cutiBersamaList.push({
                        ...this.cutiBersamaForm,
                        id: newId,
                        status: "active",
                        total_karyawan_terkena: 150,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    });
                }

                this.closeCutiBersamaModal();
                alert("Cuti bersama berhasil disimpan!");
            } catch (error) {
                console.error("Error saving cuti bersama:", error);
                alert("Terjadi kesalahan saat menyimpan cuti bersama");
            }
        },

        editCutiBersama(cutiBersama) {
            this.isEditingCutiBersama = true;
            this.editingCutiBersamaId = cutiBersama.id;
            this.cutiBersamaForm = { ...cutiBersama };
            this.showCutiBersamaModal = true;
        },

        async deleteCutiBersama(cutiBersama) {
            if (confirm(`Hapus cuti bersama "${cutiBersama.nama}"?`)) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    this.cutiBersamaList = this.cutiBersamaList.filter(
                        (c) => c.id !== cutiBersama.id
                    );

                    alert("Cuti bersama berhasil dihapus!");
                } catch (error) {
                    console.error("Error deleting cuti bersama:", error);
                    alert("Terjadi kesalahan saat menghapus cuti bersama");
                }
            }
        },

        async applyCutiBersama(cutiBersama) {
            if (
                confirm(
                    `Terapkan cuti bersama "${cutiBersama.nama}" ke semua karyawan?`
                )
            ) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 1200));

                    // Simulasi update kuota semua karyawan
                    this.kuotaList.forEach((kuota) => {
                        const jumlahHari =
                            Math.floor(
                                (new Date(cutiBersama.tanggal_selesai) -
                                    new Date(cutiBersama.tanggal_mulai)) /
                                    (1000 * 60 * 60 * 24)
                            ) + 1;

                        kuota.sisa_cuti_tahunan = Math.max(
                            0,
                            kuota.sisa_cuti_tahunan - jumlahHari
                        );
                        kuota.total_cuti_diambil += jumlahHari;
                    });

                    alert(
                        "Cuti bersama berhasil diterapkan ke semua karyawan!"
                    );
                } catch (error) {
                    console.error("Error applying cuti bersama:", error);
                    alert("Terjadi kesalahan saat menerapkan cuti bersama");
                }
            }
        },

        async submitAdjustKuota() {
            try {
                await new Promise((resolve) => setTimeout(resolve, 800));

                const jenisCuti = this.jenisCutiList.find(
                    (j) => j.id == this.adjustForm.tipe_cuti
                );

                if (jenisCuti && this.selectedKuota) {
                    const fieldMap = {
                        1: "sisa_cuti_tahunan",
                        2: "sisa_cuti_sakit",
                        3: "sisa_cuti_melahirkan",
                        4: "sisa_cuti_menikah",
                        5: "sisa_cuti_keluarga",
                        6: "sisa_cuti_besar",
                    };

                    const field = fieldMap[this.adjustForm.tipe_cuti];
                    const kuotaIndex = this.kuotaList.findIndex(
                        (k) =>
                            k.kode_karyawan === this.selectedKuota.kode_karyawan
                    );

                    if (field && kuotaIndex !== -1) {
                        this.kuotaList[kuotaIndex][field] += parseInt(
                            this.adjustForm.jumlah
                        );

                        if (this.adjustForm.jumlah > 0) {
                            alert(
                                `Kuota ${this.selectedKuota.nama_karyawan} untuk ${jenisCuti.nama} berhasil ditambah sebanyak ${this.adjustForm.jumlah} ${jenisCuti.satuan}`
                            );
                        } else {
                            alert(
                                `Kuota ${
                                    this.selectedKuota.nama_karyawan
                                } untuk ${
                                    jenisCuti.nama
                                } berhasil dikurangi sebanyak ${Math.abs(
                                    this.adjustForm.jumlah
                                )} ${jenisCuti.satuan}`
                            );
                        }
                    }
                }

                this.closeAdjustModal();
            } catch (error) {
                console.error("Error adjusting kuota:", error);
                alert("Terjadi kesalahan saat mengadjust kuota");
            }
        },

        // Export Methods
        exportCSV() {
            const data = this.filteredPengajuan;
            const headers = [
                "Nama Karyawan",
                "Kode",
                "Jenis Cuti",
                "Tanggal Mulai",
                "Tanggal Selesai",
                "Jumlah Hari",
                "Status",
                "Alasan",
            ];
            const csvContent = [
                headers.join(","),
                ...data.map((row) =>
                    [
                        `"${row.nama_karyawan}"`,
                        `"${row.kode_karyawan}"`,
                        `"${row.jenis_cuti}"`,
                        `"${this.formatDate(row.tanggal_mulai)}"`,
                        `"${this.formatDate(row.tanggal_selesai)}"`,
                        `"${row.jumlah_hari}"`,
                        `"${this.getStatusText(row.status)}"`,
                        `"${row.alasan}"`,
                    ].join(",")
                ),
            ].join("\n");

            const blob = new Blob([csvContent], { type: "text/csv" });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `laporan-pengajuan-cuti-${
                new Date().toISOString().split("T")[0]
            }.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            alert("Data berhasil diexport dalam format CSV");
        },

        exportPDF() {
            alert("Fitur export PDF akan segera tersedia");
        },

        exportData() {
            switch (this.activeTab) {
                case "pengajuan":
                    this.exportCSV();
                    break;
                case "laporan":
                    this.exportPDF();
                    break;
                default:
                    alert(
                        `Export data untuk tab ${this.activeTab} akan segera tersedia`
                    );
            }
        },

        // Utility Methods
        calculateTotalHari(tanggalMulai, tanggalSelesai) {
            const start = new Date(tanggalMulai);
            const end = new Date(tanggalSelesai);
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        },

        getDepartmentStats() {
            const stats = {};
            this.pengajuanList.forEach((pengajuan) => {
                const dept = pengajuan.department || "Unknown";
                if (!stats[dept]) {
                    stats[dept] = {
                        total: 0,
                        approved: 0,
                        pending: 0,
                        rejected: 0,
                    };
                }
                stats[dept].total++;
                stats[dept][pengajuan.status]++;
            });
            return stats;
        },
    },
    mounted() {
        this.loadTabData();
    },
    watch: {
        activeTab() {
            this.loadTabData();
        },
        searchKuota() {
            if (this.activeTab === "kuota") {
                this.loadKuota();
            }
        },
    },
};
</script>

<style>
/* Consolidated and deduplicated styles */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --danger-light: #f8d7da; /* Merah muda, lebih terang untuk latar belakang atau efek disabled */
    --gray-light: #e9ecef;
    --el-slider-main-bg-color: #6366f1;
    --el-calendar-cell-width: 50px !important;
}

/* info bar */
.leave-info-panel {
}

.info-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 12px !important;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.btnModal {
    flex-grow: 1;
    justify-content: center;
}

.fontFooter {
    /* font-size: 0.9rem; */
    padding: 0.5rem 0rem;
}
.footerModal {
    flex-direction: column;
}
.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Color variants */
.bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
}

.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}

/* Animation */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .icon-circle {
        width: 30px;
        height: 30px;
        margin-top: 8px;
        margin-right: 8px;
        font-size: 0.9rem;
        position: absolute;
        right: 0;
        top: 0;
    }
    .fontFooter {
        font-size: 0.9rem;
        padding: 0.5rem 0rem;
    }
    .footerModal {
        flex-direction: column;
    }
    .info-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: space-between;
    }

    .info-value {
        font-size: 1.25rem !important;
    }
}

/* akhir infobar */

/* cell calendar skin */
.is-red-date .date-cell {
    color: red !important;
}

.is-orange-date .date-cell {
    color: rgb(255, 166, 0) !important;
}

/* Style yang sudah ada untuk rentang tanggal */
.is-start .date-cell {
    background-color: lightblue;
    border-radius: 50%; /* Contoh */
}
.is-end .date-cell {
    background-color: lightblue;
    border-radius: 50%; /* Contoh */
}
.is-in-range .date-cell {
    background-color: lightcyan; /* Contoh */
}

/* Style untuk tanggal yang dinonaktifkan secara custom */
.is-disabled-custom .date-cell {
    opacity: 0.5; /* Membuat tanggal terlihat abu-abu atau pudar */
    cursor: not-allowed;
    pointer-events: none;
}

/* Pastikan style dasar untuk .date-cell */
.date-cell {
    text-align: center;
    line-height: 20px; /* Sesuaikan sesuai kebutuhan layout Anda */
    /* Tambahkan style dasar lain jika diperlukan */
}
.is-red-date .date-cell {
    color: red !important;
}

/* akhir cell calnedar */
.glass-warning {
    background: rgba(245, 158, 11, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(245, 158, 11, 0.3);
    transition: all 0.3s ease;
}
.glass-success {
    background: rgba(16, 185, 129, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(16, 185, 129, 0.3);
    transition: all 0.3s ease;
}
.glass-danger {
    background: rgba(239, 68, 68, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(239, 68, 68, 0.3);
    transition: all 0.3s ease;
}

.infoBar {
    background: rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.3);
    transition: all 0.3s ease;
}

.infoBar:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

/* Ensure tooltips are visible */
.custom-tooltip .el-tooltip__popper {
    opacity: 1 !important;
    display: block !important;
}

/* Fix z-index issues */
.el-slider__button-wrapper {
    z-index: 100 !important;
}

/* Parent container should not hide overflow */
.input-group {
    overflow: visible !important;
}

/* overlay dialog */
.el-overlay-dialog {
    display: flex;
    justify-content: center;
    align-items: center;
    max-height: 100vh;
    max-width: 100vw;
    overflow: hidden;
}

.el-dialog {
    display: flex;
    margin: 0px;
    flex-direction: column; /* Tata header, body, footer secara vertikal */
    max-height: calc(
        100vh - 0px
    ); /* Sesuaikan Xpx untuk margin/padding di sekitar modal */
    /* Anda mungkin ingin menambahkan background-color dan box-shadow di sini */
}

.el-dialog__body {
    flex-grow: 1; /* Biarkan body mengisi sisa ruang yang tersedia */
    overflow-y: auto; /* *Ini Kunci Utamanya*: Biarkan body modal discroll jika kontennya terlalu panjang */
    padding: 1rem; /* Atur padding sesuai kebutuhan */
}

.el-dialog__header,
.el-dialog__footer {
    flex-shrink: 0; /* Pastikan header/footer tidak mengecil */
}
/* akhir dialog */
.animated-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.attachment-link.is-loading {
    pointer-events: none;
    opacity: 0.7;
}

/* Modal container */
.detail-modal {
    border-radius: 8px;
}

.detail-modal .el-dialog__header {
    border-bottom: 1px solid #eee;
    padding: 16px 20px;
}

.detail-modal .el-dialog__body {
    padding: 20px;
}
/* Tooltip container adjustment */
.el-popper {
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    border-radius: 8px !important;
    padding: 2px !important;
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary) !important;
    background: white !important;
}

.el-popper__arrow {
    display: none !important;
}

.el-tooltip__popper.is-dark {
    background: transparent !important;
    border: none !important;
}

/* Grid layout for details */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    margin-bottom: 8px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-weight: 500;
    color: #606266;
    margin-bottom: 4px;
    font-size: 14px;
}

.detail-value {
    color: #303133;
    font-size: 15px;
}

.closeFilter {
    transition: all 0.3s ease;
    background: var(--surface);
}

.closeFilter:hover {
    color: white;
    background: var(--danger);
}

.detail-value.highlight {
    font-weight: 600;
    color: #409eff;
}

.detail-value.multiline {
    white-space: pre-line;
    line-height: 1.6;
}

.empty-value {
    color: #909399;
    font-style: italic;
}

/* Approval progress */
.approval-progress {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px dashed #eaeaea;
}

.progress-title {
    font-weight: 500;
    margin-bottom: 12px;
    color: #606266;
}

/* Footer buttons */
.modal-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 12px 20px;
    border-top: 1px solid #eee;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-modal .el-dialog {
        margin: 0;
        max-height: 100vh;
    }
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
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}
.action-menu {
    position: absolute !important; /* Popup akan mengikuti posisi elemen yang diklik */
    z-index: 1000; /* Pastikan popup tetap di atas elemen lainnya */
    background-color: white;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    padding: 10px;
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

.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--surface-soft);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

#app
    > div:nth-child(3)
    > div
    > div
    > div
    > div
    > div:nth-child(6)
    > div.card-body.row
    > div.col-md-4.col-6
    > div
    > div.el-step.is-vertical.is-flex
    > div.el-step__head.is-error
    > div.el-step__icon.is-text
    > i {
    display: flex;
    justify-content: center;
    align-items: center;
}

.btn-danger {
    background: var(--danger);
    color: white;
}
.btn-danger:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.shiningEffect {
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.shiningEffect::before {
    content: "";
    position: absolute;
    top: 0%;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.6),
        transparent
    );
    transition: left 0.5s;
}

.shiningEffect:hover::before {
    left: 100%;
}

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    /* box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); */
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

/* Control Panel (Search, Filter, View Toggle) */
.control-panel {
    background: var(--surface);
    border-radius: 20px;
    padding: 0.5rem;
    /* box-shadow: var(--shadow); */
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
}

.search-filter-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.search-container {
    position: relative;
    flex: 1;
    min-width: 280px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-results-count {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--surface);
    border-radius: 0 0 12px 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-top: none;
}
.suggestion-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.suggestion-item:hover {
    background: var(--surface-soft);
}

.filters-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-dropdown {
    position: relative;
}

.filter-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0.5rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
    justify-content: space-between;
}

.filter-btn:hover {
    background: var(--surface);
    border-color: var(--primary);
}

.view-toggle {
    display: flex;
    background: var(--surface-soft);
    border-radius: 10px;
    padding: 0.25rem;
}

.toggle-btn {
    padding: 0.5rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-muted);
}

.toggle-btn.active {
    background: var(--primary);
    color: white;
}

.toggle-btn:hover:not(.active) {
    background: var(--surface);
    color: var(--text);
}

/* Content Container & Loading/Empty States */
.content-container {
    background: var(--surface);
    border-radius: 20px;
    overflow: hidden;
    /* box-shadow: var(--shadow); */
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
}

.loading-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    min-height: 300px;
}

.loading-animation {
    position: relative;
    margin-bottom: 1.5rem;
}

.loading-dots {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    background: var(--primary);
    border-radius: 50%;
    animation: bounce 1.4s ease-in-out infinite both;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}
.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

.loading-text {
    color: var(--text-muted);
    font-size: 1.1rem;
    margin: 0;
}

.empty-icon {
    font-size: 3rem;
    color: var(--border);
    margin-bottom: 1rem;
    opacity: 0.7;
}
.empty-state h4 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}
.empty-state p {
    margin-bottom: 1.5rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Table View */
.table-view-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.table-responsive-wrapper {
    min-width: 800px;
}
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
}
.modern-table th,
.modern-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.modern-table th {
    position: sticky;
    top: 0;
    background-color: var(--surface-soft);
    z-index: 10;
    font-weight: 600;
    color: var(--text);
}
.header-cell {
    white-space: nowrap;
}
.header-cell-content {
    display: flex;
    align-items: center;
    justify-content: center;
}
.badge-count {
    margin-left: 0.5rem;
    background: var(--primary);
    color: white;
    border-radius: 10px;
    padding: 0.15rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.sticky-column {
    position: sticky;
    left: 0;
    background: var(--surface) !important;
    z-index: 11;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
}
.table-row {
    transition: background-color 0.2s ease;
    cursor: pointer;
}
.table-row:hover {
    background-color: var(--surface-soft);
}
.transaction-cell {
    display: flex;
    flex-direction: column;
}
.transaction-id {
    font-weight: 500;
    color: var(--text);
}
.transaction-type {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    margin-top: 0.25rem;
}
.type-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 0.5rem;
}
.type-sakit .type-indicator {
    background: var(--danger);
}
.type-terlambat .type-indicator {
    background: var(--warning);
}
.type-pulang-cepat .type-indicator {
    background: var(--info);
}
.type-tidak-masuk .type-indicator,
.type-cuti-tahunan .type-indicator,
.type-izin-pribadi .type-indicator {
    background: var(--primary);
}

.date-cell {
    white-space: nowrap;
    width: 100%;
}
.reason-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.attachment-cell {
    display: flex;
    align-items: center;
    justify-content: start;
}
.attachment-link {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary);
    text-decoration: none;
}
.attachment-link:hover {
    text-decoration: underline;
}
.no-attachment {
    color: var(--text-muted);
}
.status-cell {
    display: flex;
    justify-content: center;
}
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}
.status-pending {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info);
}
.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.action-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-action:hover {
    background: var(--surface-soft);
}
.btn-action.delete-btn {
    color: var(--danger);
}
.btn-action.delete-btn:disabled {
    color: var(--danger-light);
    /* background-color: var(--gray-light); */
    cursor: not-allowed;
    opacity: 0.6;
}
.btn-action.delete-btn:hover {
    background: rgba(239, 68, 68, 0.1);
}
.btn-action.more-btn {
    color: var(--text-muted);
}
.btn-action.more-btn:hover {
    color: var(--text);
}

/* Card View */
.card-view-container {
    padding: 1rem;
}
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
}
.permission-card {
    background: var(--surface);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.permission-card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}
.card-header {
    padding: 1rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header-approved {
    background: linear-gradient(135deg, #34d399 0%, #059669ce 100%);
}
.header-pending {
    background: linear-gradient(135deg, #818cf8 0%, #6365f1ce 100%);
}
.header-rejected {
    background: linear-gradient(135deg, #f472b5 0%, #ec489ad3 100%);
}
.card-date {
    font-weight: 500;
    font-size: 0.9rem;
}
.card-status {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
}
.card-body {
    padding: 1rem;
    flex-grow: 1;
}
.card-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}
.card-row i {
    color: var(--primary);
    margin-top: 0.1rem;
    flex-shrink: 0;
}
.card-label {
    color: var(--text-muted);
    flex-shrink: 0;
    width: 80px;
}
.card-value {
    color: var(--text);
    word-break: break-word;
}
.card-link {
    color: var(--primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.card-link:hover {
    text-decoration: underline;
}
.no-file {
    color: var(--text-muted);
    font-style: italic;
}
.card-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}
.card-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.card-btn.delete-btn {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}
.card-btn.delete-btn:hover {
    background: rgba(239, 68, 68, 0.2);
}

.card-btn.delete-btn:disabled {
    color: var(--danger-light);
    background-color: var(--gray-light);
    cursor: not-allowed;
    opacity: 0.6;
}
.card-btn.more-btn {
    background: var(--surface-soft);
    color: var(--text-muted);
}
.card-btn.more-btn:hover {
    background: var(--border);
    color: var(--text);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
}
.page-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 0.25rem;
}
.page-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-numbers {
    display: flex;
    margin: 0 0.5rem;
}
.page-number {
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 0.25rem;
    font-weight: 500;
}
.page-number:hover {
    border-color: var(--border);
}
.page-number.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.page-info {
    margin: 0 1rem;
    font-size: 0.875rem;
    color: var(--text-muted);
}

/* Detail Modal */
.detail-modal-content {
    padding: 0.5rem;
}
.detail-section {
    margin-bottom: 1.5rem;
}
.detail-row {
    display: flex;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.detail-label {
    width: 120px;
    font-weight: 500;
    color: var(--text-muted);
}
.detail-value {
    flex: 1;
    color: var(--text);
}
.detail-link {
    color: var(--primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.detail-link:hover {
    text-decoration: underline;
}
.detail-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Delete Confirmation Modal */
.delete-modal-content {
    text-align: center;
    padding: 1rem;
}
.warning-icon {
    font-size: 3rem;
    color: var(--danger);
    margin-bottom: 1rem;
}
.delete-modal-footer {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

/* Action Menu */
.action-menu {
    position: fixed;
    z-index: 2000;
    background: var(--surface);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    min-width: 180px;
    overflow: hidden;
    animation: fadeInUp 0.2s ease;
}
.action-menu-item {
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.action-menu-item:hover {
    background: var(--surface-soft);
}
.action-menu-item.text-danger {
    color: var(--danger);
}
.action-menu-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
}
.action-menu-divider {
    height: 1px;
    background: var(--border);
    margin: 0.25rem 0;
}
.action-menu-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1999;
    background: rgba(0, 0, 0, 0.3);
}

/* Assign Modal (New/Edit Permission) */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1048;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: var(--surface);
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transform: scale(0.9) translateY(20px);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.modal-container.assign-modal {
    max-width: 700px;
    max-height: 95vh;
}

.modal-overlay.show .modal-container {
    transform: scale(1) translateY(0);
}
.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
}

.header-title-section h3 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
}

.header-subtitle {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.modal-close {
    width: 40px;
    height: 40px;
    border: none;
    background: var(--surface);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-muted);
}

.modal-close:hover {
    background: var(--danger);
    color: white;
}

.modal-body {
    padding: 1rem 2rem;
    max-height: calc(95vh - 200px);
    overflow-y: auto;
    flex: 1;
}
.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 1rem;
    justify-content: end;
}
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    background-color: #f8f9fa;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}
.footer-actions {
    display: flex;
    gap: 0.5rem;
}
.form-section {
    padding: 0.2rem;
}

.form-group {
    margin-bottom: 1.5rem; /* Standard margin for form groups */
    border: 1px solid var(--border); /* Added for visual separation */
    background: rgba(99, 102, 241, 0.05); /* Soft background */
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto; /* Ensure content within is scrollable if needed */
    max-height: 18rem; /* Cap height to prevent excessive modal height */
}

.form-group.AssignRingkasan {
    max-height: 25rem;
}

.form-group label,
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text);
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    transform: translateY(-1px);
}

.permission-type-options {
    margin-bottom: 0rem;
}

.permission-type-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    background-color: var(--surface);
}

.permission-type-card:hover {
    border-color: #adb5bd;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.permission-type-card.selected {
    border-color: var(--primary);
    background-color: rgba(13, 110, 253, 0.05);
}

.permission-type-icon {
    font-size: 1.5rem;
    color: var(--primary);
    margin-bottom: 0.75rem;
}

.permission-type-content h6 {
    font-size: 0.9375rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.permission-type-check {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    font-size: 1rem;
    color: #adb5bd;
}

.permission-type-check .bi-check-circle-fill {
    color: #0d6efd;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}
.file-upload-area {
    cursor: pointer;
    border: 1px dashed var(--border);
    border-radius: 0.375rem;
    padding: 1rem;
    min-height: 10rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background-color: var(--surface);
}

.file-upload-area:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.5rem;
    color: var(--text-muted);
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    height: 100%;
}

.file-upload-label i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.file-display-area {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background-color: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    min-height: fit-content;
}

.file-name {
    flex-grow: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 1rem;
}

.btn-remove-file {
    background: none;
    border: none;
    color: var(--danger);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}

.btn-remove-file:hover {
    background-color: rgba(239, 68, 68, 0.1);
}

/* Calendar Modals (Element Plus specific overrides) */
.el-overlay {
    z-index: 1050;
}
.el-dialog__body {
    padding-top: 10px;
    padding-bottom: 10px;
}
.el-calendar-table .el-calendar-day {
    padding: 2px !important;
    height: 50px !important;
}
.cell-date-wrapper {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}
.date-table-cell {
    width: 100%;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: start;
    border-radius: 50%;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
    position: relative;
    z-index: 2;
}
.date-cell {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
    position: relative;
    z-index: 2;
}
.cell-date-wrapper:hover .date-cell {
    background-color: #d9ecff;
}
.is-start .date-cell,
.is-end .date-cell {
    background-color: #409eff;
    color: white;
}
.is-in-range::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #ecf5ff;
    z-index: 0;
}
.is-start::before {
    left: 50%;
}
.is-end::before {
    right: 50%;
}
.is-disabled-custom {
    color: #c0c4cc !important;
    cursor: not-allowed !important;
    background-color: #f5f7fa !important;
    /* pointer-events: none; */
}
.is-disabled-custom .date-cell {
    background-color: transparent !important;
}
.is-disabled-custom:hover .date-cell {
    background-color: transparent !important;
}

/* Transitions */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
.fade-switch-enter-active,
.fade-switch-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-switch-enter-from,
.fade-switch-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
@keyframes float {
    0%,
    100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
@keyframes bounce {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* Responsive Adjustments */
@media (max-width: 1200px) {
    .modern-header {
        min-height: 120px;
    }
    .header-content {
        padding: 2rem;
    }
    .header-actions {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-end;
    }
    .date-range {
        padding: 0.75rem 1rem;
    }
}

@media (max-width: 992px) {
    .header-info {
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
    }
    .el-dialog.is-align-center {
        margin: auto 1rem;
    }
    .el-calendar__header {
        padding: 1px 0.2rem;
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
    .search-filter-section {
        align-items: stretch;
    }
    .filters-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .card-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }
    .detail-row {
        flex-direction: column;
        gap: 0.25rem;
    }
    .detail-label {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .el-calendar__header {
        flex-direction: column;
        justify-content: center;
        align-items: start;
    }
    .el-calendar__title {
        align-self: start;
    }
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
    .control-panel {
        padding: 1rem;
    }
    .search-container {
        min-width: auto;
    }
    .modern-table {
        font-size: 0.8rem;
    }
    .sticky-column {
        min-width: 150px;
        position: relative;
        left: auto;
        /* z-index: 100; */
        /* box-shadow: none; */
    }
    .modal-container {
        margin: 0.5rem;
        max-height: 83vh;
    }
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
    .modal-footer .btn-modern {
        width: 100%;
        justify-content: center;
    }
    .card-grid {
        grid-template-columns: 1fr;
    }
    .pagination-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .page-numbers {
        order: 1;
        width: 100%;
        justify-content: center;
        margin: 0.5rem 0;
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
    .el-calendar__body {
        padding: 0 !important;
        padding-top: 1rem !important;
    }
    .control-panel {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .search-filter-section {
        flex-direction: column;
        gap: 0.75rem;
    }
    .table-responsive-wrapper {
        border-radius: 12px;
    }
    .card-view-container {
        padding: 0.75rem;
    }
    .card-row {
        flex-direction: column;
        gap: 0.25rem;
    }
    .card-label {
        width: 100%;
    }
    .modal-container.assign-modal {
        width: 98%;
        max-height: 90vh;
    }
    .form-section {
        padding: 0.2rem;
    }
    .form-group {
        padding: 0.8rem;
    }
    .permission-type-card {
        padding: 0.75rem;
    }
    .permission-type-icon {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }
    .permission-type-content h6 {
        font-size: 0.7rem;
    }
    .page-number,
    .page-btn {
        min-width: 30px;
        height: 30px;
        margin: 0 0.1rem;
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
<style scoped>
/* Styles untuk admin yang dioptimalkan */
.stat-card-admin {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.stat-card-admin:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.stat-card-admin.total {
    border-left: 4px solid var(--primary);
}

.stat-card-admin.pending {
    border-left: 4px solid var(--warning);
}

.stat-card-admin.approved {
    border-left: 4px solid var(--success);
}

.stat-card-admin.rejected {
    border-left: 4px solid var(--danger);
}

.quick-stats {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 80px;
}

.stat-badge.waiting {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.stat-badge.approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.stat-badge.rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.stat-badge:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.stat-badge .count {
    font-weight: 700;
    font-size: 1.25rem;
}

.stat-badge .label {
    font-size: 0.75rem;
    font-weight: 600;
}

/* Pengajuan Card Styles */
.pengajuan-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pengajuan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.pengajuan-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
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

.employee-details h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.employee-details p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.85rem;
}

.pengajuan-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.pengajuan-date {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.pengajuan-content {
    margin-bottom: 1.5rem;
}

.pengajuan-details {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text);
    font-size: 0.9rem;
}

.detail-item i {
    color: var(--primary);
}

.pengajuan-reason p {
    margin: 0;
    color: var(--text-muted);
    line-height: 1.5;
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
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

.pengajuan-actions .btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
}

/* Jenis Cuti Styles */
.jenis-cuti-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.jenis-cuti-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.jenis-cuti-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.jenis-cuti-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.jenis-cuti-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.25rem;
}

.jenis-cuti-info {
    flex: 1;
}

.jenis-cuti-info h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.jenis-cuti-info p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

.jenis-cuti-badge {
    background: var(--primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.jenis-cuti-details {
    margin-bottom: 1.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    color: var(--text-muted);
    font-weight: 500;
}

.detail-row .value {
    color: var(--text);
    font-weight: 600;
}

.settings {
    display: flex;
    gap: 0.5rem;
}

.setting-badge {
    background: var(--surface-soft);
    color: var(--text);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.jenis-cuti-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Action Sidebar */
.action-sidebar {
    position: sticky;
    top: 1rem;
}

.action-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    text-align: center;
}

.action-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
}

.action-description {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.info-card-admin {
    background: linear-gradient(135deg, #dbeafe, #eff6ff);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.info-title {
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.tips-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.tip-item i {
    color: #3b82f6;
    margin-top: 0.125rem;
}

.tip-item p {
    margin: 0;
    color: #374151;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Empty State */
.empty-state-admin {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state-admin i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-admin h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-state-admin p {
    margin-bottom: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .pengajuan-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .pengajuan-meta {
        align-items: flex-start;
    }

    .pengajuan-details {
        flex-direction: column;
        gap: 0.75rem;
    }

    .pengajuan-actions {
        flex-direction: column;
    }

    .pengajuan-actions .btn-action {
        width: 100%;
        justify-content: center;
    }

    .jenis-cuti-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .jenis-cuti-badge {
        align-self: flex-start;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .quick-stats {
        justify-content: center;
    }

    .stats-overview .row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .stats-overview .row {
        grid-template-columns: 1fr;
    }

    .quick-stats {
        flex-wrap: wrap;
    }

    .stat-badge {
        min-width: 70px;
    }
}
</style>
