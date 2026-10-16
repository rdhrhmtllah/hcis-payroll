<template>
    <div class="shift-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Self Service Shift"
            subtitle="Kelola jadwal shift, pengajuan tukar, dan koreksi absen"
            :dayNight="dayNight"
            :formattedDate="formattedHeaderDate"
        />

        <quick-stat :stats="quickStats" class="mb-5" />

        <!-- Main Content -->
        <div class="main-content-wrapper main-min-height">
            <div class="content-container">
                <ApprovalTabs
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab 1: Kalender Shift -->
                    <template #calendar>
                        <div class="tab-content-wrapper fade-in-up">
                            <div
                                class="calendar-header-actions mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3"
                            >
                                <div
                                    class="month-nav-container d-flex align-items-center gap-3"
                                >
                                    <button
                                        class="btn btn-icon-nav shadow-sm"
                                        @click="prevMonth"
                                    >
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    <h4
                                        class="section-title-large m-0 text-dark"
                                    >
                                        {{ currentMonthName }}
                                        <span class="text-muted fw-normal">{{
                                            currentYear
                                        }}</span>
                                    </h4>
                                    <button
                                        class="btn btn-icon-nav shadow-sm"
                                        @click="nextMonth"
                                    >
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>

                                <div
                                    class="legend d-flex gap-3 flex-wrap bg-white p-2 rounded-3 shadow-sm border"
                                >
                                    <span class="legend-item"
                                        ><span class="dot shift-morning"></span>
                                        Pagi</span
                                    >
                                    <span class="legend-item"
                                        ><span
                                            class="dot shift-afternoon"
                                        ></span>
                                        Siang</span
                                    >
                                    <span class="legend-item"
                                        ><span class="dot shift-night"></span>
                                        Malam</span
                                    >
                                    <span class="legend-item"
                                        ><span class="dot shift-noshift"></span>
                                        Off</span
                                    >
                                </div>
                            </div>

                            <!-- Calendar Container -->
                            <div class="calendar-wrapper">
                                <el-calendar
                                    v-model="currentDate"
                                    class="custom-calendar"
                                >
                                    <template #date-cell="{ data }">
                                        <div
                                            class="calendar-cell"
                                            :class="[
                                                getShiftClass(data.date),
                                                {
                                                    'is-today': isToday(
                                                        data.date
                                                    ),
                                                    'is-selected': isSelected(
                                                        data.date
                                                    ),
                                                    'is-outside':
                                                        !isCurrentMonth(
                                                            data.date
                                                        ),
                                                },
                                            ]"
                                            @click="handleDateClick(data.date)"
                                        >
                                            <div class="cell-header">
                                                <span class="date-number">{{
                                                    data.day.split("-")[2]
                                                }}</span>
                                                <span
                                                    v-if="isToday(data.date)"
                                                    class="today-badge"
                                                    >HARI INI</span
                                                >
                                            </div>

                                            <div class="shift-content">
                                                <div class="d-none d-md-block">
                                                    <span
                                                        class="shift-name-pill"
                                                        >{{
                                                            getShiftNameSmall(
                                                                data.date
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        class="shift-time-small d-block mt-1"
                                                        >{{
                                                            getShiftTime(
                                                                data.date
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                                <div
                                                    class="d-md-none text-center"
                                                >
                                                    <span
                                                        class="shift-code-mobile"
                                                        >{{
                                                            getShiftCode(
                                                                data.date
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </el-calendar>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 2: Riwayat Perubahan -->
                    <template #riwayat>
                        <div class="tab-content-improved fade-in-up">
                            <div
                                class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"
                            >
                                <h4 class="section-title m-0">
                                    <i
                                        class="bi bi-clock-history me-2 text-primary"
                                    ></i
                                    >Riwayat Pengajuan
                                </h4>
                            </div>

                            <FilterPanel
                                :config="riwayatFilterConfig"
                                :filters="riwayatFilters"
                                :results-count="riwayatData.pagination.total"
                                @filters-update="handleFiltersUpdate"
                                class="mb-3"
                            />

                            <div class="data-table-container">
                                <ModernDataTable
                                    :data="riwayatData.data"
                                    :columns="riwayatColumns"
                                    :loading="isLoading"
                                    :clickable="true"
                                    @row-click="viewHistoryDetail"
                                    :show-pagination="true"
                                    :current-page="
                                        riwayatData.pagination.current_page
                                    "
                                    :total-pages="
                                        Math.ceil(
                                            riwayatData.pagination.total /
                                                riwayatData.pagination.per_page
                                        )
                                    "
                                    :total-data="riwayatData.pagination.total"
                                    card-title-key="No_Transaksi"
                                    card-subtitle-key="TanggalIzin"
                                    card-status-key="status"
                                >
                                    <!-- Custom Type Badge (Backdate/Normal) -->
                                    <template #column-type="{ item }">
                                        <Badge
                                            :type="
                                                item.type === 'Backdate'
                                                    ? 'danger'
                                                    : 'neutral'
                                            "
                                            class="type-badge"
                                        >
                                            {{ item.type }}
                                        </Badge>
                                    </template>

                                    <!-- Custom Status Slot -->
                                    <template #column-status="{ item }">
                                        <Badge
                                            :type="
                                                getStatusBadgeType(item.status)
                                            "
                                            class="status-badge"
                                        >
                                            <i
                                                :class="
                                                    getStatusIcon(item.status)
                                                "
                                                class="me-1"
                                            ></i>
                                            {{ item.status }}
                                        </Badge>
                                    </template>

                                    <!-- Custom shift_awal highlight -->
                                    <template #column-shift_awal="{ item }">
                                        <span class="fw-bold text-muted">{{
                                            item.shift_awal
                                        }}</span>
                                    </template>
                                    <template #column-shift_akhir="{ item }">
                                        <span class="fw-bold text-primary">{{
                                            item.shift_akhir
                                        }}</span>
                                    </template>

                                    <!-- Custom Action Slot -->
                                    <template #column-action="{ item }">
                                        <button
                                            class="btn-icon-action"
                                            @click.stop="
                                                viewHistoryDetail(item)
                                            "
                                            title="Lihat Detail"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </template>

                                    <template #card-status="{ item }">
                                        <Badge
                                            :type="
                                                getStatusBadgeType(item.status)
                                            "
                                        >
                                            <i
                                                :class="
                                                    getStatusIcon(item.status)
                                                "
                                                class="me-1"
                                            ></i>
                                            {{ item.status }}
                                        </Badge>
                                    </template>
                                </ModernDataTable>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- MODAL: UBAH SHIFT (FUTURE/TODAY) -->
        <Modal
            v-if="showModal && !isBackdate"
            :show="showModal"
            title="Ubah Jadwal Shift"
            size="medium"
            @close="closeModal"
        >
            <template #body>
                <div class="form-layout p-2">
                    <div class="alert info mb-4">
                        <i class="bi bi-info-circle me-3 fs-5"></i>
                        <div>
                            <div>Anda sedang mengubah shift untuk tanggal</div>
                            <div class="fw-bold fs-5">
                                {{ formatDateString(selectedDate) }}
                            </div>
                        </div>
                    </div>

                    <div class="current-shift-card mb-4 bounce-hover">
                        <label
                            class="form-label text-muted text-uppercase tracking-wider fs-8"
                            >Shift Saat Ini</label
                        >
                        <div class="d-flex align-items-center mt-2">
                            <div
                                class="shift-icon-box me-3 shadow-sm"
                                :class="
                                    selectedShiftData?.class || 'shift-noshift'
                                "
                            >
                                {{ selectedShiftData?.code || "-" }}
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold text-dark">
                                    {{
                                        selectedShiftData?.name ||
                                        "Tidak ada jadwal"
                                    }}
                                </h5>
                                <div
                                    class="text-muted d-flex align-items-center fs-7"
                                >
                                    <i class="bi bi-clock me-1"></i>
                                    {{ selectedShiftData?.jam || "-" }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label
                            class="form-label fw-bold mb-3 d-flex justify-content-between"
                        >
                            <span>Pilih Shift Baru</span>
                            <span
                                class="text-primary fs-8 cursor-pointer"
                                @click="form.newShiftId = ''"
                                >Reset</span
                            >
                        </label>
                        <div class="shift-grid">
                            <div
                                v-for="shift in shiftTypes"
                                :key="shift.id"
                                class="shift-card-item"
                                :class="{
                                    active: form.newShiftId === shift.id,
                                }"
                                @click="form.newShiftId = shift.id"
                            >
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2"
                                >
                                    <span
                                        class="shift-code-mini"
                                        :class="shift.class"
                                        >{{ shift.code }}</span
                                    >
                                    <div class="radio-indicator">
                                        <div class="radio-dot"></div>
                                    </div>
                                </div>
                                <div class="shift-card-name">
                                    {{ shift.name }}
                                </div>
                                <div class="shift-card-time">
                                    {{ shift.start }} - {{ shift.end }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-secondary" @click="closeModal">
                        Batal
                    </button>
                    <button
                        class="btn btn-primary"
                        @click="saveShiftChange"
                        :disabled="!form.newShiftId || isSubmitting"
                    >
                        <span v-if="isSubmitting"
                            ><i
                                class="bi bi-arrow-repeat animated-spin me-1"
                            ></i>
                            Menyimpan...</span
                        >
                        <span v-else>Simpan Perubahan</span>
                    </button>
                </div>
            </template>
        </Modal>

        <!-- MODAL: KOREKSI SHIFT (BACKDATE) -->
        <Modal
            v-if="showModal && isBackdate"
            :show="showModal"
            title="Pengajuan Koreksi Shift"
            size="medium"
            @close="closeModal"
        >
            <template #body>
                <div class="form-layout p-2">
                    <div class="alert warning mb-4">
                        <i class="bi bi-exclamation-triangle me-3 fs-3"></i>
                        <div>
                            <div class="fw-bold">
                                Perubahan Shift Mundur (Backdate)
                            </div>
                            <div class="fs-8 mt-1">
                                Pengajuan ini memerlukan persetujuan atasan
                                karena tanggal yang dipilih sudah berlalu.
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label
                                class="form-label text-muted fs-8 text-uppercase"
                                >Tanggal</label
                            >
                            <div class="fw-bold fs-6">
                                {{ formatDateString(selectedDate) }}
                            </div>
                        </div>
                        <div class="col-6">
                            <label
                                class="form-label text-muted fs-8 text-uppercase"
                                >Shift Awal</label
                            >
                            <div class="fw-bold fs-6">
                                {{ selectedShiftData?.name || "-" }}
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold"
                            >Pilih Shift Sebenarnya</label
                        >
                        <select
                            class="form-select modern-select"
                            v-model="form.newShiftId"
                        >
                            <option value="" disabled>Pilih Shift...</option>
                            <option
                                v-for="shift in shiftTypes"
                                :key="shift.id"
                                :value="shift.id"
                            >
                                {{ shift.name }} ({{ shift.jam }})
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold"
                            >Alasan Koreksi
                            <span class="text-danger">*</span></label
                        >
                        <textarea
                            class="form-control modern-input"
                            rows="3"
                            v-model="form.reason"
                            placeholder="Contoh: Lupa absen masuk, salah jadwal di sistem..."
                        ></textarea>
                    </div>

                    <div
                        class="approver-box p-3 rounded d-flex align-items-center bg-light border"
                    >
                        <div
                            class="avatar-sm me-3 bg-gradient-primary text-white d-flex align-items-center justify-content-center rounded-circle fs-6 shadow-sm"
                            style="width: 40px; height: 40px"
                        >
                            BS
                        </div>
                        <div>
                            <small
                                class="text-muted d-block uppercase fs-8 tracking-wider"
                                >Menunggu Persetujuan</small
                            >
                            <span class="fw-bold text-dark fs-6">{{
                                form.approver
                            }}</span>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-secondary" @click="closeModal">
                        Batal
                    </button>
                    <button
                        class="btn btn-primary"
                        @click="saveShiftCorrection"
                        :disabled="
                            !form.newShiftId || !form.reason || isSubmitting
                        "
                    >
                        <span v-if="isSubmitting"
                            ><i class="bi bi-send animated-spin me-1"></i>
                            Mengirim...</span
                        >
                        <span v-else>Kirim Pengajuan</span>
                    </button>
                </div>
            </template>
        </Modal>

        <!-- MODAL: DETAIL RIWAYAT -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            title="Detail Pengajuan"
            size="medium"
            @close="closeDetailModal"
        >
            <template #body>
                <div class="detail-container p-2" v-if="selectedHistoryItem">
                    <div class="text-center mb-4">
                        <div class="status-icon-large mb-2">
                            <i
                                class="fs-1"
                                :class="{
                                    'text-success':
                                        selectedHistoryItem.status ===
                                        'approved',
                                    'text-warning':
                                        selectedHistoryItem.status ===
                                        'pending',
                                    'text-danger':
                                        selectedHistoryItem.status ===
                                        'rejected',
                                }"
                            >
                                <i
                                    :class="
                                        getStatusIcon(
                                            selectedHistoryItem.status
                                        )
                                    "
                                ></i>
                            </i>
                        </div>
                        <h4 class="fw-bold text-capitalize mb-1">
                            {{ selectedHistoryItem.status }}
                        </h4>
                        <div class="text-muted fs-7">
                            {{ selectedHistoryItem.No_Transaksi }}
                        </div>
                        <div
                            class="mt-2"
                            v-if="selectedHistoryItem.type === 'Backdate'"
                        >
                            <span
                                class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 rounded-pill"
                                >Backdate Request</span
                            >
                        </div>
                    </div>

                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <small
                                        class="text-muted d-block text-uppercase fs-8"
                                        >Tanggal Shift</small
                                    >
                                    <strong>{{
                                        selectedHistoryItem.TanggalIzin
                                    }}</strong>
                                </div>
                                <div class="col-6 text-end">
                                    <small
                                        class="text-muted d-block text-uppercase fs-8"
                                        >Tipe</small
                                    >
                                    <strong>{{
                                        selectedHistoryItem.type
                                    }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="shift-change-visual d-flex align-items-center justify-content-between mb-4 px-3"
                    >
                        <div class="text-center">
                            <small class="text-muted d-block mb-1">Dari</small>
                            <div
                                class="badge bg-secondary fs-6 px-3 py-2 text-white"
                            >
                                {{ selectedHistoryItem.shift_awal }}
                            </div>
                        </div>
                        <div class="arrow-visual text-muted">
                            <i class="bi bi-arrow-right fs-4"></i>
                        </div>
                        <div class="text-center">
                            <small class="text-muted d-block mb-1"
                                >Menjadi</small
                            >
                            <div
                                class="badge bg-primary fs-6 px-3 py-2 text-white"
                            >
                                {{ selectedHistoryItem.shift_akhir }}
                            </div>
                        </div>
                    </div>

                    <hr class="border-light my-4" />

                    <div class="timeline-box">
                        <h6 class="fw-bold mb-3">Timeline</h6>
                        <div class="timeline-item d-flex gap-3 mb-3">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <div class="fw-bold fs-7">Pengajuan Dibuat</div>
                                <div class="text-muted fs-8">
                                    Oleh Anda - Hari ini
                                </div>
                            </div>
                        </div>
                        <div class="timeline-item d-flex gap-3">
                            <div
                                class="timeline-marker"
                                :class="
                                    selectedHistoryItem.status === 'pending'
                                        ? 'bg-secondary'
                                        : 'bg-success'
                                "
                            ></div>
                            <div class="timeline-content">
                                <div class="fw-bold fs-7">
                                    {{
                                        selectedHistoryItem.status === "pending"
                                            ? "Menunggu Approval"
                                            : "Disetujui"
                                    }}
                                </div>
                                <div class="text-muted fs-8">
                                    {{ form.approver }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button
                    class="btn btn-secondary w-100"
                    @click="closeDetailModal"
                >
                    Tutup
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import HeaderKpi from "@/components/ui/header.vue";
import QuickStat from "@/components/ui/quickStat.vue";
import ApprovalTabs from "@/components/ui/toggleSectionV2.vue";
import ModernDataTable from "@/components/ui/table-noir.vue";
import FilterPanel from "@/components/ui/Filter/modernFilter.vue";
import Modal from "@/components/ui/modalV2-noir.vue";
import Badge from "../../../components/ui/badge.vue";
import { ElCalendar, ElNotification } from "element-plus";

export default {
    name: "SelfServiceShift",
    components: {
        HeaderKpi,
        QuickStat,
        ApprovalTabs,
        ModernDataTable,
        FilterPanel,
        Modal,
        Badge,
        ElCalendar,
    },
    data() {
        return {
            activeTab: "calendar",
            showModal: false,
            showDetailModal: false,
            selectedHistoryItem: null,

            isLoading: false,
            isSubmitting: false,

            currentDate: new Date(),
            selectedDate: null,
            selectedShiftData: null,
            isBackdate: false,

            form: {
                newShiftId: "",
                reason: "",
                approver: "Bpk. Budi Santoso (Manager)",
            },

            // MASTER SHIFTS
            shiftTypes: [
                {
                    id: 1,
                    name: "SHIFT 1 (PAGI)",
                    jam: "08:00 - 17:00",
                    start: "08:00",
                    end: "17:00",
                    code: "P",
                    class: "shift-morning",
                },
                {
                    id: 2,
                    name: "SHIFT 2 (SIANG)",
                    jam: "13:00 - 22:00",
                    start: "13:00",
                    end: "22:00",
                    code: "S",
                    class: "shift-afternoon",
                },
                {
                    id: 3,
                    name: "SHIFT 3 (MALAM)",
                    jam: "22:00 - 07:00",
                    start: "22:00",
                    end: "07:00",
                    code: "M",
                    class: "shift-night",
                },
                {
                    id: 4,
                    name: "OFF",
                    jam: "-",
                    start: "-",
                    end: "-",
                    code: "OFF",
                    class: "shift-noshift",
                },
            ],

            myShifts: [],

            // RIWAYAT DATA - Enriched
            riwayatData: {
                data: [
                    {
                        No_Transaksi: "REQ-2025003",
                        TanggalIzin: "2025-12-14",
                        shift_awal: "PAGI",
                        shift_akhir: "OFF",
                        type: "Backdate",
                        status: "pending",
                    },
                    {
                        No_Transaksi: "REQ-2025001",
                        TanggalIzin: "2025-12-05",
                        shift_awal: "OFF",
                        shift_akhir: "SIANG",
                        type: "Normal",
                        status: "approved",
                    },
                    {
                        No_Transaksi: "REQ-2025002",
                        TanggalIzin: "2025-12-10",
                        shift_awal: "MALAM",
                        shift_akhir: "OFF",
                        type: "Normal",
                        status: "pending",
                    },
                ],
                pagination: { total: 3, current_page: 1, per_page: 10 },
            },

            tabs: [
                {
                    id: "calendar",
                    label: "Kalender Shift",
                    icon: "bi bi-calendar-week",
                },
                {
                    id: "riwayat",
                    label: "Riwayat Perubahan",
                    icon: "bi bi-clock-history",
                },
            ],

            // Updated Columns
            riwayatColumns: [
                {
                    key: "No_Transaksi",
                    title: "No. Request",
                    icon: "bi bi-hash",
                },
                {
                    key: "TanggalIzin",
                    title: "Tanggal Shift",
                    icon: "bi bi-calendar",
                },
                {
                    key: "shift_awal",
                    title: "Shift Awal",
                    icon: "bi bi-arrow-left",
                },
                {
                    key: "shift_akhir",
                    title: "Shift Baru",
                    icon: "bi bi-arrow-right",
                },
                { key: "type", title: "Tipe", icon: "bi bi-tag" },
                {
                    key: "status",
                    title: "Status",
                    icon: "bi bi-info-circle",
                    cellClass: "status-cell",
                },
                { key: "action", title: "Aksi", icon: "bi bi-gear" },
            ],

            // Updated Filters
            riwayatFilterConfig: {
                searchable: true,
                searchPlaceholder: "Cari nomor request...",
                filters: [
                    {
                        key: "date_range",
                        type: "daterange",
                        label: "Range Tanggal",
                        placeholder: "Pilih range",
                    },
                    {
                        key: "type",
                        type: "select",
                        label: "Jenis Perubahan",
                        placeholder: "Semua Tipe",
                        options: [
                            { label: "Normal", value: "Normal" },
                            { label: "Backdate", value: "Backdate" },
                        ],
                    },
                ],
            },
            riwayatFilters: {},
        };
    },
    computed: {
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? "day" : "night";
        },
        formattedHeaderDate() {
            return this.formatDateString(this.currentDate);
        },
        currentMonthName() {
            return this.currentDate.toLocaleDateString("id-ID", {
                month: "long",
            });
        },
        currentYear() {
            return this.currentDate.getFullYear();
        },
        // Updated Stats to be more logical
        quickStats() {
            const activeShifts = this.myShifts.filter(
                (s) => s.code !== "OFF"
            ).length; // Logic based on generated current month
            const offDays = this.myShifts.filter(
                (s) => s.code === "OFF"
            ).length;
            const pendingReq = this.riwayatData.data.filter(
                (x) => x.status === "pending"
            ).length;

            return [
                {
                    title: "Jadwal Shift Aktif",
                    value: activeShifts.toString(),
                    unit: "Hari",
                    icon: "bi bi-calendar-check text-primary",
                    bgIcon: "bg-primary-light",
                    size: "col-4",
                },
                {
                    title: "Libur / Off",
                    value: offDays.toString(),
                    unit: "Hari",
                    icon: "bi bi-cup-hot text-purple",
                    bgIcon: "bg-purple-light",
                    size: "col-4",
                },
                {
                    title: "Permintaan Pending",
                    value: pendingReq.toString(),
                    unit: "Req",
                    icon: "bi bi-hourglass-split text-warning",
                    bgIcon: "bg-warning-light",
                    size: "col-4",
                },
            ];
        },
    },
    watch: {
        currentDate() {
            this.generateDummySchedule(); // Regenerate when month changes
        },
    },
    mounted() {
        this.generateDummySchedule();
    },
    methods: {
        handleTabChange(tab) {
            this.activeTab = tab;
        },
        prevMonth() {
            const d = new Date(this.currentDate);
            d.setMonth(d.getMonth() - 1);
            this.currentDate = d;
        },
        nextMonth() {
            const d = new Date(this.currentDate);
            d.setMonth(d.getMonth() + 1);
            this.currentDate = d;
        },
        generateDummySchedule() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            this.myShifts = [];

            // Seeded random for consistency in demo
            for (let i = 1; i <= daysInMonth; i++) {
                const dateStr = `${year}-${String(month + 1).padStart(
                    2,
                    "0"
                )}-${String(i).padStart(2, "0")}`;
                // Simplified random
                const randomShift =
                    this.shiftTypes[
                        Math.floor((i * 7) % this.shiftTypes.length)
                    ];
                this.myShifts.push({
                    date: dateStr,
                    shiftId: randomShift.id,
                    ...randomShift,
                });
            }
        },
        getShiftForDate(date) {
            if (!date) return null;
            const d = new Date(date);
            const dateStr = `${d.getFullYear()}-${String(
                d.getMonth() + 1
            ).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
            return this.myShifts.find((s) => s.date === dateStr);
        },
        getShiftClass(date) {
            const shift = this.getShiftForDate(date);
            return shift ? shift.class : "";
        },
        getShiftCode(date) {
            const shift = this.getShiftForDate(date);
            return shift ? shift.code : "";
        },
        getShiftNameSmall(date) {
            const shift = this.getShiftForDate(date);
            if (!shift) return "";
            if (shift.code === "OFF") return "OFF";
            const match = shift.name.match(/\((.*?)\)/);
            return match ? match[1] : shift.name;
        },
        getShiftTime(date) {
            const shift = this.getShiftForDate(date);
            return shift ? shift.jam : "";
        },
        isToday(date) {
            const d = new Date(date);
            const today = new Date();
            return (
                d.getDate() === today.getDate() &&
                d.getMonth() === today.getMonth() &&
                d.getFullYear() === today.getFullYear()
            );
        },
        isCurrentMonth(date) {
            const d = new Date(date);
            return d.getMonth() === this.currentDate.getMonth();
        },
        isSelected(date) {
            if (!this.selectedDate) return false;
            const d = new Date(date);
            const s = new Date(this.selectedDate);
            return d.getTime() === s.getTime();
        },
        formatDateString(date) {
            if (!date) return "-";
            return new Date(date).toLocaleDateString("id-ID", {
                weekday: "long",
                day: "numeric",
                month: "long",
                year: "numeric",
            });
        },
        handleDateClick(date) {
            this.selectedDate = date;
            this.selectedShiftData = this.getShiftForDate(date);

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const clicked = new Date(date);
            clicked.setHours(0, 0, 0, 0);

            this.isBackdate = clicked < today;
            this.form.newShiftId = "";
            this.form.reason = "";
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        viewHistoryDetail(item) {
            this.selectedHistoryItem = item;
            this.showDetailModal = true;
        },
        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedHistoryItem = null;
        },
        saveShiftChange() {
            this.isSubmitting = true;
            setTimeout(() => {
                const newShift = this.shiftTypes.find(
                    (s) => s.id === this.form.newShiftId
                );
                const d = new Date(this.selectedDate);
                const dateStr = `${d.getFullYear()}-${String(
                    d.getMonth() + 1
                ).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

                // Update local schedule
                const index = this.myShifts.findIndex(
                    (s) => s.date === dateStr
                );
                if (index !== -1) {
                    this.myShifts[index] = {
                        ...this.myShifts[index],
                        ...newShift,
                        shiftId: newShift.id,
                    };
                }

                // Add to history (Normal)
                this.riwayatData.data.unshift({
                    No_Transaksi: `REQ-${Date.now()}`,
                    TanggalIzin: dateStr,
                    shift_awal: this.selectedShiftData?.name || "-",
                    shift_akhir: newShift.name,
                    type: "Normal",
                    status: "approved",
                });
                this.riwayatData.pagination.total++;

                ElNotification({
                    title: "Berhasil",
                    message: "Jadwal shift berhasil diperbarui",
                    type: "success",
                });
                this.isSubmitting = false;
                this.closeModal();
            }, 600);
        },
        saveShiftCorrection() {
            this.isSubmitting = true;
            setTimeout(() => {
                const newShift = this.shiftTypes.find(
                    (s) => s.id === this.form.newShiftId
                );
                const d = new Date(this.selectedDate);
                const dateStr = `${d.getFullYear()}-${String(
                    d.getMonth() + 1
                ).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

                this.riwayatData.data.unshift({
                    No_Transaksi: `REQ-${Date.now()}`,
                    TanggalIzin: dateStr,
                    shift_awal: this.selectedShiftData
                        ? this.selectedShiftData.name
                        : "-",
                    shift_akhir: newShift.name,
                    type: "Backdate",
                    status: "pending",
                });
                this.riwayatData.pagination.total++;

                ElNotification({
                    title: "Pengajuan Terkirim",
                    message: "Koreksi shift Anda menunggu persetujuan",
                    type: "info",
                });
                this.isSubmitting = false;
                this.closeModal();
            }, 600);
        },
        getStatusBadgeType(status) {
            switch (status) {
                case "approved":
                    return "success";
                case "pending":
                    return "warning";
                case "rejected":
                    return "danger";
                default:
                    return "neutral";
            }
        },
        getStatusIcon(status) {
            switch (status) {
                case "approved":
                    return "bi bi-check-circle-fill";
                case "pending":
                    return "bi bi-hourglass-split";
                case "rejected":
                    return "bi bi-x-circle-fill";
                default:
                    return "bi bi-info-circle";
            }
        },
        handleFiltersUpdate() {
            /* Mock */
        },
    },
};
</script>

<style scoped>
/* Design System Variables */
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
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;
}

.shift-app-container {
    max-width: 1200px;
    padding-bottom: 80px;
}
.main-content-wrapper {
    margin-top: 2rem;
}
.main-min-height {
    min-height: 700px;
    transition: min-height 0.3s ease;
}

.fade-in-up {
    animation: fadeInUp 0.4s ease-out;
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

.glass-panel {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.6);
    padding: 1.5rem;
    box-shadow: 0 10px 40px rgba(31, 38, 135, 0.05);
}

/* Calendar Styling Deep Override */
:deep(.el-calendar) {
    background: transparent !important;
}
:deep(.el-calendar__header) {
    display: none;
}
:deep(.el-calendar__body) {
    padding: 0 !important;
}
:deep(.el-calendar-table) {
    border-spacing: 12px;
    border-collapse: separate;
}
:deep(.el-calendar-table td) {
    border: none !important;
    padding: 0 !important;
}

:deep(.el-calendar-table .el-calendar-day) {
    height: 120px;
    padding: 0;
    border-radius: 18px;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    border: 1px solid rgba(0, 0, 0, 0.03);
    overflow: hidden;
    background: white;
    position: relative;
}
:deep(.el-calendar-table .el-calendar-day:hover) {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    z-index: 5;
    border-color: rgba(0, 0, 0, 0.05);
}
:deep(.el-calendar-table td.is-selected .el-calendar-day) {
    background-color: transparent;
}
:deep(.el-calendar-table .next, .el-calendar-table .prev) {
    opacity: 0.3;
    pointer-events: none;
    filter: grayscale(1);
}

/* Custom Calendar Cell */
.calendar-cell {
    height: 100%;
    width: 100%;
    display: flex;
    flex-direction: column;
    padding: 12px;
    box-sizing: border-box;
}
.calendar-cell.is-outside {
    opacity: 0.4;
    filter: grayscale(0.8);
}
.calendar-cell.is-selected {
    box-shadow: inset 0 0 0 2px #6366f1; /* Primary color highlight */
}

.cell-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}
.date-number {
    font-weight: 700;
    font-size: 1.15rem;
    color: #444;
}
.today-badge {
    font-size: 0.6rem;
    font-weight: 800;
    color: #fff;
    background: #ff5252;
    padding: 2px 6px;
    border-radius: 6px;
}

.shift-name-pill {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(2px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.shift-time-small {
    font-size: 0.75rem;
    font-weight: 500;
    opacity: 0.85;
}

/* Shift Visual Styles Gradient */
:deep(.shift-morning) .el-calendar-day {
    background: linear-gradient(135deg, #fffde7 0%, #fff59d 100%) !important;
    color: #f57f17;
}
.dot.shift-morning {
    background: #fbc02d;
}
:deep(.shift-afternoon) .el-calendar-day {
    background: linear-gradient(135deg, #e3f2fd 0%, #90caf9 100%) !important;
    color: #1565c0;
}
.dot.shift-afternoon {
    background: #2196f3;
}
:deep(.shift-night) .el-calendar-day {
    background: linear-gradient(135deg, #f3e5f5 0%, #ce93d8 100%) !important;
    color: #6a1b9a;
}
.dot.shift-night {
    background: #9c27b0;
}
:deep(.shift-noshift) .el-calendar-day {
    background: #f8f9fa !important;
    color: #adb5bd;
    border: 1px dashed #dee2e6;
}
.dot.shift-noshift {
    background: #ced4da;
}

:deep(.el-calendar-table td.is-today .el-calendar-day) {
    border: 2px solid #ff5252 !important;
}

/* Button & Titles */
.section-title-large {
    font-weight: 800;
    font-size: 1.5rem;
    letter-spacing: -0.5px;
}
.btn-icon-nav {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: white;
    color: #334155;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.btn-icon-nav:hover {
    background: #f1f5f9;
    transform: scale(1.1);
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .main-min-height {
        min-height: auto;
    }
    .glass-panel {
        padding: 1rem 0.5rem;
        border-radius: 16px;
    }
    :deep(.el-calendar-table) {
        border-spacing: 3px;
    }
    :deep(.el-calendar-table .el-calendar-day) {
        height: 75px;
        border-radius: 10px;
    }
    .calendar-cell {
        padding: 4px;
        justify-content: center;
        align-items: center;
    }
    .cell-header {
        position: absolute;
        top: 4px;
        left: 4px;
        width: 100%;
        justify-content: flex-start;
    }
    .date-number {
        font-size: 0.85rem;
    }
    .today-badge {
        display: none;
    }
    .shift-code-mobile {
        font-size: 1.1rem;
        font-weight: 800;
        display: inline-block;
        margin-top: 10px;
    }
    .shift-grid {
        grid-template-columns: 1fr;
    }
    .modal-body {
        padding: 1rem;
    }
}

/* Form Styles */
.modern-input,
.modern-select {
    border: 1px solid #dee2e6;
    padding: 0.8rem 1rem;
    border-radius: 12px;
    font-size: 0.95rem;
    width: 100%;
    transition: all 0.2s;
}
.modern-input:focus {
    border-color: #228be6;
    box-shadow: 0 0 0 3px rgba(34, 139, 230, 0.1);
    outline: none;
}
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 10px;
    font-weight: 600;
    border: none;
}
.btn-primary {
    background: linear-gradient(135deg, #4dabf7 0%, #228be6 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(34, 139, 230, 0.2);
}
.btn-secondary {
    background: #f1f3f5;
    color: #495057;
}
.shift-card-item {
    border: 1px solid #f1f3f5;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}
.shift-card-item:hover,
.bounce-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
}
.shift-card-item.active {
    border-color: #228be6;
    background: #e7f5ff;
}
.radio-indicator {
    width: 20px;
    height: 20px;
    border: 2px solid #ced4da;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.shift-card-item.active .radio-indicator {
    border-color: #228be6;
    background: #228be6;
}
.radio-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    opacity: 0;
    transform: scale(0);
    transition: all 0.2s;
}
.shift-card-item.active .radio-dot {
    opacity: 1;
    transform: scale(1);
}
</style>
