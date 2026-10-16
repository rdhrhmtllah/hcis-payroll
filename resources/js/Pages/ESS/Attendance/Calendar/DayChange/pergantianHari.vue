<template>
    <div class="employee-shift-swap-container">
        <!-- Header -->
        <HeaderKpi
            title="Tukar Shift Saya"
            subtitle="Ajukan penukaran shift dengan atasan Anda"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Quota Badge -->
        <div class="quota-badge-wrapper mb-4">
            <Badge type="info" class="glassmorphism p-3">
                <i class="bi bi-arrow-left-right me-2"></i>
                <strong>Sisa Kuota Tukar:</strong> 2/3 bulan ini
            </Badge>
        </div>

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab 1: Ajukan Tukar -->
                    <template #ajukan>
                        <div class="tab-content-wrapper">
                            <div class="schedule-header mb-4">
                                <h4 class="mb-3">
                                    <i class="bi bi-calendar-week me-2"></i>
                                    Jadwal Saya 14 Hari Kedepan
                                </h4>
                                <div class="date-range-info">
                                    {{ formatDateRange() }}
                                </div>
                            </div>

                            <!-- Schedule Card Grid -->
                            <div v-if="myShifts.length > 0" class="schedule-grid">
                                <div
                                    v-for="shift in myShifts"
                                    :key="shift.id"
                                    class="schedule-card-wrapper"
                                >
                                    <div
                                        class="schedule-card glassmorphism"
                                        :class="{
                                            'work-shift': shift.status === 'WORK',
                                            'off-shift': shift.status === 'OFF'
                                        }"
                                        @click="shift.status === 'WORK' ? openSwapWizard(shift) : null"
                                        :style="{
                                            cursor: shift.status === 'WORK' ? 'pointer' : 'not-allowed',
                                            opacity: shift.status === 'OFF' ? 0.7 : 1
                                        }"
                                    >
                                        <div class="card-header">
                                            <div class="date-display">
                                                <div class="day-name">
                                                    {{ getDayName(shift.date) }}
                                                </div>
                                                <div class="date-number">
                                                    {{ getDateNumber(shift.date) }}
                                                </div>
                                            </div>
                                            <Badge
                                                :type="shift.status === 'WORK' ? 'warning' : 'success'"
                                                class="shift-status-badge"
                                            >
                                                {{ shift.status === 'WORK' ? 'Kerja' : 'Libur' }}
                                            </Badge>
                                        </div>
                                        
                                        <div class="card-content">
                                            <div class="shift-info">
                                                <i class="bi bi-clock me-2"></i>
                                                <span class="shift-name">{{ shift.shiftName }}</span>
                                            </div>
                                            <div class="shift-hours">
                                                {{ shift.startTime }} - {{ shift.endTime }}
                                            </div>
                                        </div>

                                        <!-- Action Button for WORK shifts -->
                                        <div v-if="shift.status === 'WORK'" class="card-footer">
                                            <button class="btn-swap">
                                                <i class="bi bi-arrow-left-right me-2"></i>
                                                Tukar Shift Ini
                                            </button>
                                        </div>
                                        <div v-else class="card-footer">
                                            <span class="text-muted">Hari Libur</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="empty-state">
                                <i class="bi bi-calendar-x empty-icon"></i>
                                <h4>Tidak Ada Jadwal</h4>
                                <p>Belum ada jadwal yang tersedia untuk 14 hari kedepan</p>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 2: Riwayat Saya -->
                    <template #riwayat>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <div class="filter-section mb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="search-field">
                                            <i class="bi bi-search"></i>
                                            <input
                                                type="text"
                                                v-model="historyFilters.search"
                                                @input="handleSearch"
                                                class="search-input"
                                                placeholder="Cari no. tiket atau alasan..."
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select
                                            v-model="historyFilters.status"
                                            @change="applyFilters"
                                            class="filter-select"
                                        >
                                            <option value="">Semua Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="approved">Disetujui</option>
                                            <option value="rejected">Ditolak</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button
                                            @click="resetFilters"
                                            class="btn-modern btn-secondary w-100"
                                        >
                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                            Reset
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Modern Data Table -->
                            <ModernDataTable
                                :data="filteredHistory"
                                :columns="historyColumns"
                                :mobile-columns="historyMobileColumns"
                                :loading="loading.history"
                                :clickable="true"
                                :show-pagination="true"
                                :current-page="historyPagination.currentPage"
                                :total-pages="historyPagination.totalPages"
                                :total-data="filteredHistory.length"
                                empty-message="Belum ada riwayat penukaran shift"
                                @row-click="viewHistoryDetail"
                                @page-change="handlePageChange"
                            >
                                <!-- Custom slot for No. Tiket -->
                                <template #column-ticketNo="{ item }">
                                    <code class="ticket-number">{{ item.ticketNo }}</code>
                                </template>

                                <!-- Custom slot for Status -->
                                <template #column-status="{ item }">
                                    <Badge :type="getStatusBadgeType(item.status)">
                                        <i :class="getStatusIcon(item.status)" class="me-1"></i>
                                        {{ getStatusText(item.status) }}
                                    </Badge>
                                </template>

                                <!-- Custom slot for Actions -->
                                <template #column-actions="{ item }">
                                    <div class="action-cell">
                                        <button
                                            class="btn-action btn-view"
                                            @click.stop="viewHistoryDetail(item)"
                                            v-tooltip="'Lihat Detail'"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </template>

                                <!-- Custom card header -->
                                <template #card-header="{ item }">
                                    <div class="card-title">
                                        No. Tiket: {{ item.ticketNo }}
                                    </div>
                                    <div class="card-subtitle">
                                        {{ formatDate(item.originDate) }} → {{ formatDate(item.targetDate) }}
                                    </div>
                                </template>

                                <!-- Custom status for card view -->
                                <template #card-status="{ item }">
                                    <Badge :type="getStatusBadgeType(item.status)">
                                        {{ getStatusText(item.status) }}
                                    </Badge>
                                </template>

                                <!-- Custom values for mobile view -->
                                <template #mobile-ticketNo="{ item }">
                                    <code>{{ item.ticketNo }}</code>
                                </template>

                                <template #mobile-originDate="{ item }">
                                    {{ formatDateCompact(item.originDate) }}
                                </template>

                                <template #mobile-targetDate="{ item }">
                                    {{ formatDateCompact(item.targetDate) }}
                                </template>

                                <template #mobile-reason="{ item }">
                                    {{ truncateText(item.reason, 60) }}
                                </template>

                                <!-- Custom actions for mobile -->
                                <template #card-actions="{ item }">
                                    <button
                                        class="card-btn btn-view mobile-btn"
                                        @click.stop="viewHistoryDetail(item)"
                                    >
                                        <i class="bi bi-eye"></i>
                                        <span>Lihat Detail</span>
                                    </button>
                                </template>
                            </ModernDataTable>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Swap Wizard Modal -->
        <Modal
            v-if="showSwapWizard"
            :show="showSwapWizard"
            title="Wizard Tukar Shift"
            size="large"
            @close="closeSwapWizard"
        >
            <template #body>
                <!-- Step Indicator -->
                <div class="wizard-steps mb-4">
                    <div class="step-progress">
                        <div
                            class="step-progress-bar"
                            :style="{ width: stepProgress }"
                        ></div>
                    </div>
                    <div class="step-indicators">
                        <div
                            v-for="step in steps"
                            :key="step.id"
                            class="step-item"
                            :class="{
                                'active': currentStep === step.id,
                                'completed': currentStep > step.id
                            }"
                            @click="currentStep >= step.id ? goToStep(step.id) : null"
                        >
                            <div class="step-number">
                                <span v-if="currentStep > step.id">
                                    <i class="bi bi-check"></i>
                                </span>
                                <span v-else>{{ step.number }}</span>
                            </div>
                            <div class="step-label">{{ step.label }}</div>
                        </div>
                    </div>
                </div>

                <!-- Step Content -->
                <div class="step-content">
                    <!-- Step 1: Confirm Origin -->
                    <div v-if="currentStep === 1" class="step-1">
                        <div class="step-header mb-4">
                            <h5><i class="bi bi-calendar-check me-2"></i> Konfirmasi Hari Kerja Asal</h5>
                            <p class="text-muted">Anda akan menukar shift dari hari berikut:</p>
                        </div>

                        <div class="origin-card glassmorphism p-4">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center">
                                    <div class="date-circle">
                                        <div class="day-name">{{ getDayName(selectedShift.date) }}</div>
                                        <div class="date-number">{{ getDateNumber(selectedShift.date) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <div class="shift-details">
                                        <h6 class="mb-2">
                                            <i class="bi bi-arrow-right-circle text-primary me-2"></i>
                                            Shift yang Akan Ditukar
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="detail-item">
                                                    <span class="label">Tanggal:</span>
                                                    <span class="value">{{ formatDate(selectedShift.date) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="label">Shift:</span>
                                                    <span class="value">{{ selectedShift.shiftName }}</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="detail-item">
                                                    <span class="label">Jam Kerja:</span>
                                                    <span class="value">{{ selectedShift.startTime }} - {{ selectedShift.endTime }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="label">Status:</span>
                                                    <Badge type="warning">Hari Kerja</Badge>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="confirmation-text mt-4 p-3 bg-light rounded">
                            <i class="bi bi-info-circle text-info me-2"></i>
                            <span>
                                Anda menukar <strong>KELUAR</strong> dari Shift {{ selectedShift.shiftName }}
                                pada tanggal {{ formatDate(selectedShift.date) }}
                            </span>
                        </div>
                    </div>

                    <!-- Step 2: Select Target -->
                    <div v-if="currentStep === 2" class="step-2">
                        <div class="step-header mb-4">
                            <h5><i class="bi bi-calendar-plus me-2"></i> Pilih Hari Libur Tujuan</h5>
                            <p class="text-muted">Pilih tanggal dimana Anda ingin mengganti hari libur:</p>
                        </div>

                        <!-- Date Picker -->
                        <div class="date-picker-container mb-4">
                            <div class="calendar-wrapper">
                                <el-date-picker
                                    v-model="swapForm.targetDate"
                                    type="date"
                                    placeholder="Pilih tanggal libur"
                                    :disabled-date="disableNonOffDays"
                                    :shortcuts="dateShortcuts"
                                    size="large"
                                    class="w-100"
                                    @change="checkDateConflict"
                                />
                            </div>
                        </div>

                        <!-- Conflict Alert -->
                        <div v-if="hasConflict" class="conflict-alert alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Perhatian!</strong> Tanggal yang Anda pilih sudah memiliki pengajuan cuti.
                            Apakah Anda yakin ingin melanjutkan?
                        </div>

                        <!-- Available Off Days List -->
                        <div class="off-days-list mt-4">
                            <h6 class="mb-3">
                                <i class="bi bi-calendar2-week me-2"></i>
                                Hari Libur Saya yang Tersedia
                            </h6>
                            <div class="row g-3">
                                <div
                                    v-for="offDay in availableOffDays"
                                    :key="offDay.id"
                                    class="col-md-4"
                                >
                                    <div
                                        class="off-day-card"
                                        :class="{ 'selected': isDateSelected(offDay.date) }"
                                        @click="selectOffDay(offDay)"
                                    >
                                        <div class="off-day-date">
                                            {{ formatDateCompact(offDay.date) }}
                                        </div>
                                        <div class="off-day-name">
                                            {{ getDayName(offDay.date) }}
                                        </div>
                                        <div v-if="isWeekend(offDay.date)" class="weekend-badge">
                                            <i class="bi bi-star-fill"></i> Weekend
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Reason & Summary -->
                    <div v-if="currentStep === 3" class="step-3">
                        <div class="step-header mb-4">
                            <h5><i class="bi bi-clipboard-check me-2"></i> Ringkasan & Alasan</h5>
                            <p class="text-muted">Tinjau perubahan dan berikan alasan penukaran:</p>
                        </div>

                        <!-- Before vs After Comparison -->
                        <div class="comparison-section mb-4">
                            <h6 class="mb-3">Perbandingan Sebelum & Sesudah</h6>
                            <div class="comparison-cards row g-4">
                                <div class="col-md-6">
                                    <div class="comparison-card before">
                                        <div class="card-header bg-primary text-white">
                                            <i class="bi bi-arrow-left-circle me-2"></i>
                                            Sebelum
                                        </div>
                                        <div class="card-body">
                                            <div class="comparison-item">
                                                <span class="label">{{ formatDate(selectedShift.date) }}</span>
                                                <Badge type="warning">Kerja</Badge>
                                            </div>
                                            <div class="comparison-item">
                                                <span class="label">{{ selectedShift.shiftName }}</span>
                                                <span class="time">{{ selectedShift.startTime }} - {{ selectedShift.endTime }}</span>
                                            </div>
                                            <div class="comparison-item">
                                                <span class="label">Status</span>
                                                <span class="status working">Bekerja</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="comparison-card after">
                                        <div class="card-header bg-success text-white">
                                            <i class="bi bi-arrow-right-circle me-2"></i>
                                            Sesudah
                                        </div>
                                        <div class="card-body">
                                            <div class="comparison-item">
                                                <span class="label">{{ formatDate(swapForm.targetDate) }}</span>
                                                <Badge type="success">Libur</Badge>
                                            </div>
                                            <div class="comparison-item">
                                                <span class="label">Shift Tujuan</span>
                                                <span class="time">-</span>
                                            </div>
                                            <div class="comparison-item">
                                                <span class="label">Status</span>
                                                <span class="status off">Libur</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reason Input -->
                        <div class="reason-section mb-4">
                            <label class="form-label">
                                <i class="bi bi-chat-text me-2"></i>
                                Alasan Penukaran
                            </label>
                            <textarea
                                v-model="swapForm.reason"
                                class="form-control"
                                rows="4"
                                placeholder="Jelaskan alasan Anda ingin menukar shift..."
                                :maxlength="500"
                            ></textarea>
                            <div class="char-counter text-end mt-2">
                                {{ swapForm.reason.length }}/500 karakter
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="summary-section">
                            <h6 class="mb-3">
                                <i class="bi bi-list-check me-2"></i>
                                Ringkasan Pengajuan
                            </h6>
                            <div class="summary-list">
                                <div class="summary-item">
                                    <span class="label">No. Tiket:</span>
                                    <span class="value">{{ generateTicketNumber() }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Tanggal Asal:</span>
                                    <span class="value">{{ formatDate(selectedShift.date) }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Tanggal Tujuan:</span>
                                    <span class="value">{{ formatDate(swapForm.targetDate) }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Alasan:</span>
                                    <span class="value">{{ truncateText(swapForm.reason, 100) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wizard-footer">
                    <button
                        v-if="currentStep > 1"
                        class="btn-modern btn-secondary me-2"
                        @click="previousStep"
                    >
                        <i class="bi bi-arrow-left me-2"></i>
                        Kembali
                    </button>
                    <button
                        v-if="currentStep < 3"
                        class="btn-modern btn-primary"
                        @click="nextStep"
                        :disabled="!canProceedToNextStep"
                    >
                        Lanjut
                        <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                    <button
                        v-if="currentStep === 3"
                        class="btn-modern btn-success"
                        @click="submitSwapRequest"
                        :disabled="isSubmitting"
                    >
                        <i v-if="isSubmitting" class="bi bi-arrow-repeat animated-spin me-2"></i>
                        <i v-else class="bi bi-send-check me-2"></i>
                        {{ isSubmitting ? 'Mengajukan...' : 'Ajukan Tukar Shift' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- History Detail Modal -->
        <Modal
            v-if="showHistoryDetail"
            :show="showHistoryDetail"
            :title="`Detail Tukar Shift - ${selectedHistory?.ticketNo || ''}`"
            size="medium"
            @close="closeHistoryDetail"
        >
            <template #body>
                <div v-if="selectedHistory" class="history-detail-content">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="detail-section">
                                <h6 class="section-title mb-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Informasi Tiket
                                </h6>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="label">No. Tiket:</span>
                                        <span class="value">{{ selectedHistory.ticketNo }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Status:</span>
                                        <Badge :type="getStatusBadgeType(selectedHistory.status)">
                                            {{ getStatusText(selectedHistory.status) }}
                                        </Badge>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Tanggal Pengajuan:</span>
                                        <span class="value">{{ formatDateTime(selectedHistory.createdAt) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-section">
                                <h6 class="section-title mb-3">
                                    <i class="bi bi-person-check me-2"></i>
                                    Status Persetujuan
                                </h6>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="label">Atasan:</span>
                                        <span class="value">{{ selectedHistory.supervisor || 'Belum ditentukan' }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Tanggal Respons:</span>
                                        <span class="value">{{ selectedHistory.responseDate ? formatDate(selectedHistory.responseDate) : 'Belum ada' }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Catatan:</span>
                                        <span class="value">{{ selectedHistory.notes || 'Tidak ada catatan' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section mb-4">
                        <h6 class="section-title mb-3">
                            <i class="bi bi-arrow-left-right me-2"></i>
                            Detail Penukaran
                        </h6>
                        <div class="swap-details-comparison">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="swap-origin p-3 bg-light rounded h-100">
                                        <h6 class="mb-3 text-primary">
                                            <i class="bi bi-box-arrow-left me-2"></i>
                                            Asal
                                        </h6>
                                        <div class="swap-item mb-2">
                                            <span class="swap-label">Tanggal:</span>
                                            <span class="swap-value">{{ formatDate(selectedHistory.originDate) }}</span>
                                        </div>
                                        <div class="swap-item mb-2">
                                            <span class="swap-label">Shift:</span>
                                            <span class="swap-value">{{ selectedHistory.originShift }}</span>
                                        </div>
                                        <div class="swap-item">
                                            <span class="swap-label">Jam:</span>
                                            <span class="swap-value">{{ selectedHistory.originTime }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="swap-target p-3 bg-light rounded h-100">
                                        <h6 class="mb-3 text-success">
                                            <i class="bi bi-box-arrow-right me-2"></i>
                                            Tujuan
                                        </h6>
                                        <div class="swap-item mb-2">
                                            <span class="swap-label">Tanggal:</span>
                                            <span class="swap-value">{{ formatDate(selectedHistory.targetDate) }}</span>
                                        </div>
                                        <div class="swap-item mb-2">
                                            <span class="swap-label">Shift:</span>
                                            <span class="swap-value">Libur</span>
                                        </div>
                                        <div class="swap-item">
                                            <span class="swap-label">Jam:</span>
                                            <span class="swap-value">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h6 class="section-title mb-3">
                            <i class="bi bi-chat-text me-2"></i>
                            Alasan
                        </h6>
                        <div class="reason-box p-3 bg-light rounded">
                            {{ selectedHistory.reason }}
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeHistoryDetail">
                    Tutup
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import HeaderKpi from "@/components/legacy/header.vue";
import ApprovalTabs from "@/components/legacy/toggleSectionV2.vue";
import Modal from "@/components/legacy/modalV2-noir.vue";
import ModernDataTable from "@/components/legacy/table-noir.vue";
import Badge from "../../../components/ui/badge.vue";
import { ElDatePicker, ElMessage, ElNotification } from 'element-plus';
import 'element-plus/dist/index.css';

export default {
    name: "EmployeeShiftSwap",
    components: {
        HeaderKpi,
        ApprovalTabs,
        Modal,
        ModernDataTable,
        Badge,
        ElDatePicker
    },
    data() {
        return {
            // UI State
            currentDate: new Date(),
            activeTab: 'ajukan',
            showSwapWizard: false,
            showHistoryDetail: false,
            currentStep: 1,
            isSubmitting: false,
            hasConflict: false,
            
            // Selected Data
            selectedShift: null,
            selectedHistory: null,
            
            // Form Data
            swapForm: {
                originDate: null,
                targetDate: null,
                reason: ''
            },
            
            // Filters
            historyFilters: {
                search: '',
                status: ''
            },
            
            // Pagination
            historyPagination: {
                currentPage: 1,
                totalPages: 1,
                perPage: 10
            },
            
            // Mock Data - My Shifts (Next 14 days)
            myShifts: [
                { id: 1, date: this.addDays(1), shiftName: 'Pagi', startTime: '08:00', endTime: '17:00', status: 'WORK' },
                { id: 2, date: this.addDays(2), shiftName: 'Siang', startTime: '13:00', endTime: '22:00', status: 'WORK' },
                { id: 3, date: this.addDays(3), shiftName: 'Libur', startTime: '-', endTime: '-', status: 'OFF' },
                { id: 4, date: this.addDays(4), shiftName: 'Pagi', startTime: '08:00', endTime: '17:00', status: 'WORK' },
                { id: 5, date: this.addDays(5), shiftName: 'Malam', startTime: '22:00', endTime: '06:00', status: 'WORK' },
                { id: 6, date: this.addDays(6), shiftName: 'Libur', startTime: '-', endTime: '-', status: 'OFF' },
                { id: 7, date: this.addDays(7), shiftName: 'Libur', startTime: '-', endTime: '-', status: 'OFF' },
                { id: 8, date: this.addDays(8), shiftName: 'Pagi', startTime: '08:00', endTime: '17:00', status: 'WORK' },
                { id: 9, date: this.addDays(9), shiftName: 'Siang', startTime: '13:00', endTime: '22:00', status: 'WORK' },
                { id: 10, date: this.addDays(10), shiftName: 'Libur', startTime: '-', endTime: '-', status: 'OFF' },
                { id: 11, date: this.addDays(11), shiftName: 'Pagi', startTime: '08:00', endTime: '17:00', status: 'WORK' },
                { id: 12, date: this.addDays(12), shiftName: 'Malam', startTime: '22:00', endTime: '06:00', status: 'WORK' },
                { id: 13, date: this.addDays(13), shiftName: 'Libur', startTime: '-', endTime: '-', status: 'OFF' },
                { id: 14, date: this.addDays(14), shiftName: 'Libur', startTime: '-', endTime: '-', status: 'OFF' }
            ],
            
            // Mock Data - Swap History
            swapHistory: [
                { id: 1, ticketNo: 'TS-2024-00123', originDate: this.addDays(-7), targetDate: this.addDays(-5), originShift: 'Pagi', originTime: '08:00-17:00', reason: 'Ada keperluan keluarga', status: 'approved', createdAt: this.addDays(-7, true), supervisor: 'Budi Santoso', responseDate: this.addDays(-6), notes: 'Disetujui, harap koordinasi dengan rekan' },
                { id: 2, ticketNo: 'TS-2024-00124', originDate: this.addDays(-5), targetDate: this.addDays(-3), originShift: 'Malam', originTime: '22:00-06:00', reason: 'Kesehatan kurang fit', status: 'rejected', createdAt: this.addDays(-5, true), supervisor: 'Budi Santoso', responseDate: this.addDays(-4), notes: 'Ditolak, sudah ada pengajuan lain di tanggal tersebut' },
                { id: 3, ticketNo: 'TS-2024-00125', originDate: this.addDays(-3), targetDate: this.addDays(-2), originShift: 'Siang', originTime: '13:00-22:00', reason: 'Acara keluarga penting', status: 'pending', createdAt: this.addDays(-3, true), supervisor: null, responseDate: null, notes: null },
                { id: 4, ticketNo: 'TS-2024-00126', originDate: this.addDays(-10), targetDate: this.addDays(-8), originShift: 'Pagi', originTime: '08:00-17:00', reason: 'Mengikuti pelatihan', status: 'approved', createdAt: this.addDays(-10, true), supervisor: 'Budi Santoso', responseDate: this.addDays(-9), notes: 'Disetujui dengan catatan' },
                { id: 5, ticketNo: 'TS-2024-00127', originDate: this.addDays(-12), targetDate: this.addDays(-9), originShift: 'Malam', originTime: '22:00-06:00', reason: 'Ibadah keluarga', status: 'approved', createdAt: this.addDays(-12, true), supervisor: 'Budi Santoso', responseDate: this.addDays(-11), notes: 'Disetujui' }
            ],
            
            // Available Off Days (mock)
            availableOffDays: [
                { id: 1, date: this.addDays(3) },
                { id: 2, date: this.addDays(6) },
                { id: 3, date: this.addDays(7) },
                { id: 4, date: this.addDays(10) },
                { id: 5, date: this.addDays(13) },
                { id: 6, date: this.addDays(14) }
            ],
            
            // Date picker shortcuts
            dateShortcuts: [
                { text: 'Besok', value: () => this.addDays(1) },
                { text: 'Minggu Depan', value: () => this.addDays(7) },
                { text: '2 Minggu', value: () => this.addDays(14) }
            ],
            
            // Loading states
            loading: {
                history: false
            }
        };
    },
    computed: {
        // Tabs configuration
        tabs() {
            return [
                {
                    id: 'ajukan',
                    label: 'Ajukan Tukar',
                    icon: 'bi bi-arrow-left-right d-flex justify-content-center align-items-center',
                    count: 0
                },
                {
                    id: 'riwayat',
                    label: 'Riwayat Saya',
                    icon: 'bi bi-clock-history d-flex justify-content-center align-items-center',
                    count: this.swapHistory.length
                }
            ];
        },
        
        // Table columns for history
        historyColumns() {
            return [
                {
                    key: 'ticketNo',
                    title: 'No. Tiket',
                    icon: 'bi bi-hash',
                    sticky: true
                },
                {
                    key: 'originDate',
                    title: 'Tanggal Asal',
                    icon: 'bi bi-calendar-minus',
                    formatter: (value) => this.formatDate(value)
                },
                {
                    key: 'targetDate',
                    title: 'Tanggal Tujuan',
                    icon: 'bi bi-calendar-plus',
                    formatter: (value) => this.formatDate(value)
                },
                {
                    key: 'reason',
                    title: 'Alasan',
                    icon: 'bi bi-chat-text',
                    formatter: (value) => this.truncateText(value, 50)
                },
                {
                    key: 'status',
                    title: 'Status',
                    icon: 'bi bi-check-circle',
                    formatter: (value) => this.getStatusText(value)
                },
                {
                    key: 'actions',
                    title: 'Aksi',
                    icon: 'bi bi-three-dots',
                    showInMobile: false
                }
            ];
        },
        
        // Mobile columns for history
        historyMobileColumns() {
            return [
                { key: 'ticketNo', title: 'No. Tiket', icon: 'bi bi-hash' },
                { key: 'originDate', title: 'Asal', icon: 'bi bi-calendar-minus' },
                { key: 'targetDate', title: 'Tujuan', icon: 'bi bi-calendar-plus' },
                { key: 'reason', title: 'Alasan', icon: 'bi bi-chat-text' }
            ];
        },
        
        // Filtered history based on filters
        filteredHistory() {
            let filtered = [...this.swapHistory];
            
            if (this.historyFilters.search) {
                const search = this.historyFilters.search.toLowerCase();
                filtered = filtered.filter(item => 
                    item.ticketNo.toLowerCase().includes(search) ||
                    item.reason.toLowerCase().includes(search)
                );
            }
            
            if (this.historyFilters.status) {
                filtered = filtered.filter(item => item.status === this.historyFilters.status);
            }
            
            return filtered;
        },
        
        // Step progress calculation
        stepProgress() {
            return `${(this.currentStep / 3) * 100}%`;
        },
        
        // Wizard steps
        steps() {
            return [
                { id: 1, number: 1, label: 'Konfirmasi Asal' },
                { id: 2, number: 2, label: 'Pilih Tujuan' },
                { id: 3, number: 3, label: 'Ringkasan' }
            ];
        },
        
        // Day/Night indicator
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        
        // Check if can proceed to next step
        canProceedToNextStep() {
            switch (this.currentStep) {
                case 1:
                    return !!this.selectedShift;
                case 2:
                    return !!this.swapForm.targetDate;
                case 3:
                    return !!this.swapForm.reason.trim();
                default:
                    return false;
            }
        }
    },
    methods: {
        // Date utilities
        addDays(days, includeTime = false) {
            const date = new Date();
            date.setDate(date.getDate() + days);
            if (!includeTime) {
                date.setHours(0, 0, 0, 0);
            }
            return date;
        },
        
        formatDate(date) {
            if (!date) return '';
            return date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        },
        
        formatDateCompact(date) {
            if (!date) return '';
            const d = new Date(date);
            const day = d.getDate();
            const month = d.toLocaleDateString('id-ID', { month: 'short' });
            return `${day} ${month}`;
        },
        
        formatDateTime(date) {
            if (!date) return '';
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        formatDateString(date, withTime = false) {
            if (!date) return '';
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            if (withTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
            }
            return new Date(date).toLocaleDateString('id-ID', options);
        },
        
        getDayName(date) {
            return date.toLocaleDateString('id-ID', { weekday: 'long' });
        },
        
        getDateNumber(date) {
            return date.getDate();
        },
        
        formatDateRange() {
            const start = this.myShifts[0]?.date;
            const end = this.myShifts[this.myShifts.length - 1]?.date;
            if (start && end) {
                return `${this.formatDate(start)} - ${this.formatDate(end)}`;
            }
            return '';
        },
        
        isWeekend(date) {
            const day = date.getDay();
            return day === 0 || day === 6;
        },
        
        // Tab handlers
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },
        
        // Swap Wizard methods
        openSwapWizard(shift) {
            this.selectedShift = shift;
            this.swapForm.originDate = shift.date;
            this.showSwapWizard = true;
            this.currentStep = 1;
            this.hasConflict = false;
        },
        
        closeSwapWizard() {
            this.showSwapWizard = false;
            this.selectedShift = null;
            this.swapForm = { originDate: null, targetDate: null, reason: '' };
            this.currentStep = 1;
            this.hasConflict = false;
        },
        
        nextStep() {
            if (this.currentStep < 3) {
                this.currentStep++;
            }
        },
        
        previousStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
            }
        },
        
        goToStep(step) {
            if (step <= this.currentStep) {
                this.currentStep = step;
            }
        },
        
        // Date picker validation
        disableNonOffDays(date) {
            // Only enable dates that are OFF days
            const dateString = date.toDateString();
            return !this.availableOffDays.some(offDay => 
                offDay.date.toDateString() === dateString
            );
        },
        
        checkDateConflict(date) {
            // Mock conflict check - simulate conflict on weekends
            if (date && this.isWeekend(date)) {
                this.hasConflict = true;
            } else {
                this.hasConflict = false;
            }
        },
        
        selectOffDay(offDay) {
            this.swapForm.targetDate = offDay.date;
            this.checkDateConflict(offDay.date);
        },
        
        isDateSelected(date) {
            return this.swapForm.targetDate && 
                   date.toDateString() === this.swapForm.targetDate.toDateString();
        },
        
        // Generate mock ticket number
        generateTicketNumber() {
            const random = Math.floor(Math.random() * 1000);
            return `TS-${new Date().getFullYear()}-${random.toString().padStart(5, '0')}`;
        },
        
        // Submit swap request
        async submitSwapRequest() {
            this.isSubmitting = true;
            
            try {
                // Simulate API call delay
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                // Create new history entry
                const newHistory = {
                    id: this.swapHistory.length + 1,
                    ticketNo: this.generateTicketNumber(),
                    originDate: this.selectedShift.date,
                    targetDate: this.swapForm.targetDate,
                    originShift: this.selectedShift.shiftName,
                    originTime: `${this.selectedShift.startTime}-${this.selectedShift.endTime}`,
                    reason: this.swapForm.reason,
                    status: 'pending',
                    createdAt: new Date(),
                    supervisor: null,
                    responseDate: null,
                    notes: null
                };
                
                this.swapHistory.unshift(newHistory);
                
                ElNotification({
                    title: 'Sukses',
                    message: 'Pengajuan tukar shift berhasil diajukan!',
                    type: 'success',
                    duration: 3000
                });
                
                this.closeSwapWizard();
                this.activeTab = 'riwayat';
                
            } catch (error) {
                ElMessage.error('Gagal mengajukan tukar shift. Silakan coba lagi.');
            } finally {
                this.isSubmitting = false;
            }
        },
        
        // History methods
        viewHistoryDetail(item) {
            this.selectedHistory = item;
            this.showHistoryDetail = true;
        },
        
        closeHistoryDetail() {
            this.showHistoryDetail = false;
            this.selectedHistory = null;
        },
        
        handleSearch() {
            // Debounce search if needed
            this.historyPagination.currentPage = 1;
        },
        
        applyFilters() {
            this.historyPagination.currentPage = 1;
        },
        
        resetFilters() {
            this.historyFilters = {
                search: '',
                status: ''
            };
            this.historyPagination.currentPage = 1;
        },
        
        handlePageChange(page) {
            this.historyPagination.currentPage = page;
        },
        
        // Status utilities
        getStatusBadgeType(status) {
            const types = {
                pending: 'warning',
                approved: 'success',
                rejected: 'danger'
            };
            return types[status] || 'secondary';
        },
        
        getStatusIcon(status) {
            const icons = {
                pending: 'bi bi-clock',
                approved: 'bi bi-check-circle',
                rejected: 'bi bi-x-circle'
            };
            return icons[status] || 'bi bi-question-circle';
        },
        
        getStatusText(status) {
            const texts = {
                pending: 'Menunggu',
                approved: 'Disetujui',
                rejected: 'Ditolak'
            };
            return texts[status] || status;
        },
        
        // Utility methods
        truncateText(text, maxLength) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        }
    }
};
</script>

<style scoped>
/* Design System Variables */
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
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;
}

/* Container */
.employee-shift-swap-container {
    min-height: 100vh;
    background: var(--light);
}

.main-content-wrapper {
    padding: 1rem 0;
}

.content-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Quota Badge */
.quota-badge-wrapper {
    text-align: center;
}

.glassmorphism {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* Schedule Grid */
.schedule-header {
    text-align: center;
}

.schedule-header h4 {
    color: var(--dark);
    font-weight: 600;
}

.date-range-info {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-top: 1.5rem;
}

.schedule-card {
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    min-height: 180px;
}

.schedule-card.work-shift {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.schedule-card.off-shift {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.schedule-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.25rem;
}

.date-display {
    text-align: center;
}

.day-name {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
}

.date-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1;
}

.shift-status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
}

.card-content {
    margin-bottom: 1.25rem;
}

.shift-info {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.shift-info i {
    color: var(--primary);
    font-size: 1.1rem;
}

.shift-name {
    font-weight: 600;
    color: var(--dark);
    font-size: 1.1rem;
}

.shift-hours {
    color: var(--text-muted);
    font-size: 0.9rem;
    padding-left: 1.75rem;
}

.card-footer {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.btn-swap {
    background: var(--warning);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-swap:hover {
    background: var(--hcis-amber-600);
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-state p {
    margin-bottom: 2rem;
}

/* Filter Section */
.filter-section {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.search-field {
    position: relative;
    display: flex;
    align-items: center;
}

.search-field i {
    position: absolute;
    left: 1rem;
    color: var(--text-muted);
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: var(--transition);
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

/* Table Customizations */
.ticket-number {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.85rem;
    background: var(--surface-soft);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    color: var(--dark);
}

.action-cell {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
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

.btn-view {
    color: var(--info);
}

.btn-view:hover {
    background: rgba(6, 182, 212, 0.1);
}

/* Wizard Steps */
.wizard-steps {
    position: relative;
    margin-bottom: 2rem;
}

.step-progress {
    height: 3px;
    background: var(--border);
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    z-index: 1;
}

.step-progress-bar {
    height: 100%;
    background: var(--primary);
    transition: var(--transition);
}

.step-indicators {
    display: flex;
    justify-content: space-between;
    position: relative;
    z-index: 2;
}

.step-item {
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    flex: 1;
}

.step-item.completed {
    cursor: pointer;
}

.step-number {
    width: 30px;
    height: 30px;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-weight: 600;
    color: var(--text-muted);
    transition: var(--transition);
}

.step-item.active .step-number {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: scale(1.1);
}

.step-item.completed .step-number {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.step-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 500;
}

.step-item.active .step-label {
    color: var(--primary);
    font-weight: 600;
}

.step-item.completed .step-label {
    color: var(--success);
}

/* Step 1 Styles */
.origin-card {
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
}

.date-circle {
    width: 70px;
    height: 70px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    margin: 0 auto;
}

.date-circle .day-name {
    font-size: 0.7rem;
    opacity: 0.9;
}

.date-circle .date-number {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.shift-details h6 {
    color: var(--dark);
    font-weight: 600;
}

.detail-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}

.detail-item .label {
    width: 100px;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-item .value {
    color: var(--dark);
    font-weight: 500;
}

.confirmation-text {
    font-size: 0.95rem;
}

/* Step 2 Styles */
.date-picker-container {
    position: relative;
}

.calendar-wrapper {
    position: relative;
    z-index: 100;
}

:deep(.el-date-editor) {
    width: 100% !important;
}

:deep(.el-date-editor .el-input__wrapper) {
    border-radius: var(--radius) !important;
    border: 2px solid var(--border) !important;
}

:deep(.el-date-editor .el-input__wrapper:hover) {
    border-color: var(--primary) !important;
}

:deep(.el-date-editor .el-input__wrapper.is-focus) {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}

.conflict-alert {
    border-left: 4px solid var(--warning);
}

.off-days-list h6 {
    color: var(--dark);
    font-weight: 600;
}

.off-day-card {
    background: var(--surface-soft);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    min-height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.off-day-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.off-day-card.selected {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary-dark);
}

.off-day-date {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.off-day-name {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.weekend-badge {
    font-size: 0.75rem;
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

/* Step 3 Styles */
.comparison-section h6 {
    color: var(--dark);
    font-weight: 600;
}

.comparison-card {
    border-radius: var(--radius);
    overflow: hidden;
    height: 100%;
    box-shadow: var(--shadow);
}

.comparison-card .card-header {
    padding: 0.75rem 1rem;
    margin-bottom: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.comparison-card .card-body {
    padding: 1.5rem;
}

.comparison-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.comparison-item:last-child {
    margin-bottom: 0;
    border-bottom: none;
}

.comparison-item .label {
    color: var(--text);
    font-weight: 500;
}

.comparison-item .time {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.status.working {
    color: var(--warning);
    font-weight: 600;
}

.status.off {
    color: var(--success);
    font-weight: 600;
}

.reason-section label {
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}

.form-control {
    width: 100%;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.char-counter {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.summary-section h6 {
    color: var(--dark);
    font-weight: 600;
}

.summary-list {
    background: var(--surface-soft);
    border-radius: var(--radius);
    padding: 1.5rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.summary-item:last-child {
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}

.summary-item .label {
    color: var(--text-muted);
    font-weight: 500;
    min-width: 120px;
}

.summary-item .value {
    color: var(--dark);
    font-weight: 500;
    text-align: right;
    flex: 1;
}

/* Wizard Footer */
.wizard-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    position: relative;
    overflow: hidden;
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
    background: var(--primary);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: var(--hcis-emerald-600);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

/* History Detail */
.history-detail-content {
    padding: 0.5rem 0;
}

.detail-section {
    margin-bottom: 1.5rem;
}

.section-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
}

.detail-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.detail-item .label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-item .value {
    color: var(--dark);
    font-weight: 500;
    text-align: right;
}

.swap-details-comparison .swap-origin,
.swap-details-comparison .swap-target {
    height: 100%;
}

.swap-details-comparison h6 {
    font-weight: 600;
}

.swap-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.swap-item:last-child {
    margin-bottom: 0;
    border-bottom: none;
}

.swap-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.swap-value {
    color: var(--dark);
    font-weight: 500;
}

.reason-box {
    line-height: 1.6;
    color: var(--text);
    background: var(--surface-soft);
    border-radius: var(--radius);
    padding: 1rem;
}

/* Animations */
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

/* Responsive Design */
@media (max-width: 1200px) {
    .schedule-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

@media (max-width: 992px) {
    .schedule-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    
    .step-indicators {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .step-item {
        flex: 0 0 calc(50% - 0.5rem);
    }
}

@media (max-width: 768px) {
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-section .row > div {
        margin-bottom: 0.75rem;
    }
    
    .filter-section .row > div:last-child {
        margin-bottom: 0;
    }
    
    .step-item {
        flex: 0 0 100%;
        margin-bottom: 1rem;
    }
    
    .step-item:last-child {
        margin-bottom: 0;
    }
    
    .step-progress {
        display: none;
    }
    
    .comparison-cards {
        flex-direction: column;
    }
    
    .comparison-cards .col-md-6 {
        width: 100%;
        margin-bottom: 1rem;
    }
    
    .comparison-cards .col-md-6:last-child {
        margin-bottom: 0;
    }
    
    .wizard-footer {
        flex-direction: column;
    }
    
    .wizard-footer button {
        width: 100%;
        justify-content: center;
    }

    .history-detail-content .row {
        flex-direction: column;
    }
    
    .history-detail-content .col-md-6 {
        width: 100%;
        margin-bottom: 1rem;
    }
    
    .history-detail-content .col-md-6:last-child {
        margin-bottom: 0;
    }
}

@media (max-width: 576px) {
    .origin-card .row {
        flex-direction: column;
        text-align: center;
    }
    
    .origin-card .col-md-2 {
        margin-bottom: 1rem;
    }
    
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .detail-item .label {
        width: 100%;
    }
    
    .detail-item .value {
        width: 100%;
        text-align: left;
    }

    .swap-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}
</style>

<style scoped>
/* Element Plus Overrides for Date Picker */
.el-picker__popper {
    z-index: 9999 !important;
}

.el-date-table td.disabled .cell {
    background-color: #f5f7fa !important;
    color: #c0c4cc !important;
    cursor: not-allowed !important;
}

.el-date-table td.current:not(.disabled) .cell {
    color: var(--hcis-indigo-500) !important;
}

.el-date-table td.selected .cell {
    background-color: var(--hcis-indigo-500) !important;
    color: white !important;
}

.el-date-table td.start-date .cell,
.el-date-table td.end-date .cell {
    background-color: var(--hcis-indigo-500) !important;
    color: white !important;
}

.el-date-table td.in-range .cell {
    background-color: rgba(99, 102, 241, 0.1) !important;
}

/* Weekend styling */
.el-date-table td .cell.weekend {
    color: var(--hcis-red-500) !important;
}

.el-date-table td.disabled .cell.weekend {
    color: rgba(239, 68, 68, 0.5) !important;
}

/* Tooltip styles */
.el-tooltip__popper {
    max-width: 300px;
    font-size: 0.85rem;
    line-height: 1.4;
}
</style>