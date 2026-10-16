<template>
    <div class="cuti-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Kelola Approver Shift"
            subtitle="Manage approval flow untuk pengajuan shift anggota"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />
        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab Dashboard -->
                    <template #dashboard>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <!-- Summary Cards -->
                            <!-- <div class="row g-3 mb-4">
                                <div class="col-md-4 col-12">
                                    <div class="stitch-card h-100 d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="stitch-card-icon blue">
                                                <i class="bi bi-people" style="font-size: 1.25rem"></i>
                                            </div>
                                            <div>
                                                <p class="text-muted text-sm font-medium mb-0">Total Approver</p>
                                                <p class="text-dark text-2xl font-bold mb-0 pl-1 tracking-tight">
                                                    {{ stats.total_approvers || 0 }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="stitch-card h-100 d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="stitch-card-icon green">
                                                <i class="bi bi-link-45deg" style="font-size: 1.25rem"></i>
                                            </div>
                                            <div>
                                                <p class="text-muted text-sm font-medium mb-0">Total Assignment</p>
                                                <p class="text-dark text-2xl font-bold mb-0 pl-1 tracking-tight">
                                                    {{ stats.total_assignments || 0 }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="stitch-card h-100 d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="stitch-card-icon orange">
                                                <i class="bi bi-exclamation-triangle" style="font-size: 1.25rem"></i>
                                            </div>
                                            <div>
                                                <p class="text-muted text-sm font-medium mb-0">Tanpa Approver</p>
                                                <p class="text-dark text-2xl font-bold mb-0 pl-1 tracking-tight">
                                                    {{ stats.members_without_approver || 0 }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> -->

                            <!-- Transfer Approver Section -->
                            <div class="stitch-card w-100 p-4 mb-4">
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <i class="bi bi-arrow-left-right text-primary"></i>
                                    <h5 class="text-dark font-bold m-0">Transfer Approver</h5>
                                </div>
                                <p class="text-muted small mb-4">
                                    Pindahkan semua anggota dari satu approver ke approver lain dengan sekali klik.
                                </p>
                                
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="stitch-filter-label">Approver Lama</label>
                                        <el-select
                                            v-model="transferData.oldApproverKode"
                                            filterable
                                            clearable
                                            placeholder="Pilih approver lama..."
                                            class="el-select-full"
                                            @change="onTransferOldApproverChange"
                                        >
                                            <el-option
                                                v-for="a in approversWithMembers"
                                                :key="a.Kode_Karyawan"
                                                :label="`${a.Nama} (${a.member_count} anggota)`"
                                                :value="a.Kode_Karyawan"
                                            />
                                        </el-select>
                                    </div>
                                    <div class="col-md-1 d-flex justify-content-center align-items-center">
                                        <i class="bi bi-arrow-right text-primary" style="font-size: 1.5rem"></i>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="stitch-filter-label">Approver Baru</label>
                                        <el-select
                                            v-model="transferData.newApproverKode"
                                            filterable
                                            clearable
                                            placeholder="Pilih approver baru..."
                                            class="el-select-full"
                                            :disabled="!transferData.oldApproverKode"
                                        >
                                            <el-option
                                                v-for="a in availableNewApprovers"
                                                :key="a.Kode_Karyawan"
                                                :label="`${a.Nama} - ${a.nama_jabatan || 'N/A'}`"
                                                :value="a.Kode_Karyawan"
                                            />
                                        </el-select>
                                    </div>
                                    <div class="col-md-3">
                                        <button
                                            @click="executeTransfer"
                                            class="stitch-btn primary w-100 d-flex align-items-center"
                                            :disabled="!transferData.oldApproverKode || !transferData.newApproverKode || transferring"
                                        >
                                            <span v-if="transferring">
                                                <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                                                Processing...
                                            </span>
                                            <span v-else>
                                                <i class="bi bi-arrow-left-right"></i>
                                                Transfer Semua
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                
                                <div v-if="transferData.oldApproverKode" class="mt-3 p-3 bg-warning-subtle rounded border border-warning">
                                    <i class="bi bi-info-circle text-warning"></i>
                                    <span class="text-warning-emphasis small">
                                        {{ getOldApproverMemberCount }} anggota akan dipindahkan ke approver baru.
                                    </span>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div class="stitch-card w-100 p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="text-dark font-bold m-0">
                                        <i class="bi bi-clock-history pe-3"></i>Aktivitas Terakhir
                                    </h5>
                                </div>
                                <div v-if="recentActivity.length === 0" class="d-flex flex-column align-items-center gap-3  text-center py-4 text-muted">
                                    <i class="bi  bi-inbox display-5 "></i>
                                    <p class="small m-0">Belum ada aktivitas.</p>
                                </div>
                                <div v-else class="d-flex flex-column gap-2">
                                    <div v-for="act in recentActivity" :key="act.Id" 
                                         class="d-flex align-items-center gap-3 p-3 bg-light rounded border">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                             :class="{
                                                 'bg-primary-subtle text-primary': act.Jenis_Transaksi === 'ASSIGN',
                                                 'bg-info-subtle text-info': act.Jenis_Transaksi === 'TRANSFER',
                                                 'bg-success-subtle text-success': act.Jenis_Transaksi === 'IMPORT',
                                                 'bg-danger-subtle text-danger': act.Jenis_Transaksi === 'DELETE',
                                             }"
                                             style="width: 36px; height: 36px;">
                                            <i :class="getActivityIcon(act.Jenis_Transaksi)"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="fw-medium text-dark small">{{ getActivityLabel(act) }}</div>
                                            <div class="text-muted small">{{ formatDateTime(act.Created_At) }}</div>
                                        </div>
                                        <span class="badge bg-light text-dark border">{{ act.Total_Affected }} record</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Kelola Approver -->
                    <template #kelola>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                                <div class="d-flex flex-column gap-1">
                                    <h1 class="text-dark text-2xl font-extrabold leading-tight tracking-tight m-0">
                                        Bulk Assign Approver
                                    </h1>
                                    <p class="text-muted text-sm m-0">
                                        Pilih approver, lalu pilih anggota yang akan di-assign.
                                    </p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button @click="showExportModal = true" class="stitch-btn secondary">
                                        <i class="bi bi-download"></i>
                                        <span class="d-none d-md-inline">Export Excel</span>
                                    </button>
                                    <button @click="showImportModal = true" class="stitch-btn secondary">
                                        <i class="bi bi-file-earmark-excel"></i>
                                        <span class="d-none d-md-inline">Import Excel</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 1: Select Approver -->
                            <div class="stitch-card mb-4 p-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                          style="width: 24px; height: 24px;">1</span>
                                    <h6 class="text-dark font-bold m-0">Pilih Approver</h6>
                                </div>
                                <el-select
                                    v-model="bulkAssign.approverKode"
                                    filterable
                                    clearable
                                    placeholder="Cari dan pilih approver..."
                                    class="el-select-full"
                                    @change="loadMembersForAssign"
                                >
                                    <el-option
                                        v-for="a in allApprovers"
                                        :key="a.Kode_Karyawan"
                                        :label="`${a.Nama} (${a.NIK}) - ${a.nama_jabatan || 'N/A'}`"
                                        :value="a.Kode_Karyawan"
                                    />
                                </el-select>
                            </div>

                            <!-- Step 2: Select Members -->
                            <div v-if="bulkAssign.approverKode" class="stitch-card p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                              style="width: 24px; height: 24px;">2</span>
                                        <h6 class="text-dark font-bold m-0">Pilih Anggota</h6>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <el-select
                                            v-model="memberFilter.status"
                                            placeholder="Status"
                                            size="small"
                                            style="width: 150px"
                                            @change="filterMembers"
                                        >
                                            <el-option label="Semua" value="all" />
                                            <el-option label="Belum Ada Approver" value="unassigned" />
                                            <el-option label="Sudah Ada Approver" value="assigned" />
                                        </el-select>
                                        <el-input
                                            v-model="memberFilter.search"
                                            placeholder="Cari nama/NIK..."
                                            size="small"
                                            style="width: 200px"
                                            clearable
                                            @input="debouncedFilterMembers"
                                        >
                                            <template #prefix>
                                                <i class="bi bi-search"></i>
                                            </template>
                                        </el-input>
                                    </div>
                                </div>

                                <!-- Member List -->
                                <div v-if="loadingMembers" class="text-center py-4">
                                    <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                                    <span class="ms-2">Memuat data...</span>
                                </div>
                                <div v-else>
                                    <!-- Select All -->
                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                        <label class="d-flex align-items-center gap-2 cursor-pointer m-0">
                                            <input 
                                                type="checkbox" 
                                                :checked="isAllSelected"
                                                @change="toggleSelectAll"
                                                class="form-check-input"
                                            />
                                            <span class="fw-medium">Pilih Semua ({{ filteredMembers.length }})</span>
                                        </label>
                                        <span class="badge bg-primary">{{ selectedMembers.length }} dipilih</span>
                                    </div>

                                    <!-- Member Grid -->
                                    <div class="member-grid" style="max-height: 400px; overflow-y: auto;">
                                        <div v-for="m in filteredMembers" :key="m.Kode_Karyawan"
                                             class="member-item p-3 rounded border mb-2 cursor-pointer"
                                             :class="{ 'border-primary bg-primary-subtle': isSelected(m.Kode_Karyawan) }"
                                             @click="toggleMember(m.Kode_Karyawan)">
                                            <div class="d-flex align-items-center gap-3">
                                                <input 
                                                    type="checkbox" 
                                                    :checked="isSelected(m.Kode_Karyawan)"
                                                    class="form-check-input"
                                                    @click.stop
                                                    @change="toggleMember(m.Kode_Karyawan)"
                                                />
                                                <div class="flex-1">
                                                    <div class="fw-medium text-dark">{{ m.Nama }}</div>
                                                    <div class="text-muted small">{{ m.NIK }} • {{ m.nama_jabatan || 'N/A' }}</div>
                                                </div>
                                                <span v-if="m.current_approver_nama" 
                                                      class="badge bg-light text-muted border small">
                                                    <i class="bi bi-person-check"></i> {{ m.current_approver_nama }}
                                                </span>
                                                <span v-else class="badge bg-warning-subtle text-warning-emphasis small">
                                                    <i class="bi bi-exclamation-circle"></i> Belum ada
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Button -->
                                    <div class="mt-4 d-flex justify-content-between align-items-center">
                                        <button @click="resetSelection" class="stitch-btn secondary" :disabled="selectedMembers.length === 0">
                                            <i class="bi bi-x-circle"></i> Reset
                                        </button>
                                        <button 
                                            @click="executeBulkAssign"
                                            class="stitch-btn primary"
                                            :disabled="selectedMembers.length === 0 || assigning"
                                        >
                                            <span v-if="assigning">
                                                <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                                                Processing...
                                            </span>
                                            <span v-else>
                                                <i class="bi bi-check2-all"></i>
                                                Assign {{ selectedMembers.length }} Anggota
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Daftar Assignment -->
                    <template #daftar>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <div class="d-flex justify-content-between align-items-end mb-4">
                                <div class="d-flex flex-column gap-1">
                                    <h1 class="text-dark text-2xl font-extrabold leading-tight tracking-tight m-0">
                                        Daftar Assignment
                                    </h1>
                                    <p class="text-muted text-sm m-0">
                                        Semua assignment approver-anggota yang aktif.
                                    </p>
                                </div>
                                <button @click="loadAssignments" class="stitch-action-btn" title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>

                            <!-- Filter -->
                            <div class="mb-4">
                                <ModernFilter
                                    :config="assignmentFilterConfig"
                                    :filters="assignmentFilters"
                                    @filters-update="handleAssignmentFilters"
                                />
                            </div>

                            <!-- Table -->
                            <div v-if="assignmentLoading" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4 class="loading-title">Memuat Data...</h4>
                            </div>

                            <div v-else-if="assignments.length === 0" 
                                 class="d-flex flex-column align-items-center justify-content-center text-center py-5 text-muted bg-white rounded-3 shadow-sm border">
                                <i class="bi bi-inbox display-4 mb-2"></i>
                                <span>Belum ada assignment.</span>
                            </div>

                            <div v-else class="d-none d-md-block bg-white rounded-3 border shadow-sm overflow-hidden">
                                <table class="stitch-table">
                                    <thead>
                                        <tr>
                                            <th>Anggota</th>
                                            <th>Divisi</th>
                                            <th>Jabatan</th>
                                            <th>Approver</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="a in assignments" :key="a.id">
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium text-dark">{{ a.requester_nama }}</span>
                                                    <span class="small text-muted">{{ a.requester_nik }}</span>
                                                </div>
                                            </td>
                                            <td class="text-muted">{{ a.nama_divisi || '-' }}</td>
                                            <td class="text-muted">{{ a.nama_jabatan || '-' }}</td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium text-dark">{{ a.approver_nama }}</span>
                                                    <span class="small text-muted">{{ a.approver_jabatan }}</span>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button @click="editAssignment(a)" class="stitch-action-btn" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button @click="removeAssignment(a)" class="stitch-action-btn text-danger" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="d-md-none d-flex flex-column gap-3">
                                <div v-for="a in assignments" :key="'m-' + a.id" 
                                     class="bg-white rounded-3 border shadow-sm p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark">{{ a.requester_nama }}</div>
                                            <div class="small text-muted">{{ a.requester_nik }}</div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <div class="text-muted small">Divisi</div>
                                            <div class="small fw-medium">{{ a.nama_divisi || '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small">Approver</div>
                                            <div class="small fw-medium">{{ a.approver_nama }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button @click="editAssignment(a)" class="stitch-btn secondary small">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button @click="removeAssignment(a)" class="stitch-btn danger small">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="assignmentPagination.total_pages > 1" 
                                 class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                <button class="stitch-btn secondary small"
                                        :disabled="!assignmentPagination.prev_page_url"
                                        @click="loadAssignments(assignmentPagination.current_page - 1)">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <span class="small text-muted fw-medium">
                                    Halaman {{ assignmentPagination.current_page }} dari {{ assignmentPagination.total_pages }}
                                </span>
                                <button class="stitch-btn secondary small"
                                        :disabled="!assignmentPagination.next_page_url"
                                        @click="loadAssignments(assignmentPagination.current_page + 1)">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Edit Assignment Modal -->
        <Modal
            :show="showEditModal"
            title="Edit Assignment"
            size="medium"
            @close="showEditModal = false"
        >
            <template #body>
                <div v-if="editingAssignment">
                    <div class="mb-4 p-3 bg-light rounded border">
                        <div class="text-muted small mb-1">Anggota</div>
                        <div class="fw-bold text-dark">{{ editingAssignment.requester_nama }}</div>
                        <div class="small text-muted">{{ editingAssignment.requester_nik }}</div>
                    </div>
                    <div class="mb-4">
                        <label class="stitch-filter-label">Approver Baru</label>
                        <el-select
                            v-model="editingAssignment.new_approver_kode"
                            filterable
                            clearable
                            placeholder="Pilih approver..."
                            class="el-select-full"
                        >
                            <el-option
                                v-for="a in allApprovers"
                                :key="a.Kode_Karyawan"
                                :label="`${a.Nama} (${a.NIK})`"
                                :value="a.Kode_Karyawan"
                            />
                        </el-select>
                    </div>
                </div>
            </template>
            <template #footer>
                <button @click="showEditModal = false" class="stitch-btn secondary">Batal</button>
                <button @click="saveEditAssignment" class="stitch-btn primary" :disabled="saving">
                    <span v-if="saving">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Menyimpan...
                    </span>
                    <span v-else>Simpan</span>
                </button>
            </template>
        </Modal>

        <!-- Export Modal -->
        <Modal
            :show="showExportModal"
            title="Export Data Approval Flow"
            size="medium"
            @close="showExportModal = false"
        >
            <template #body>
                <div class="mb-4">
                    <p class="text-muted mb-4">
                        Pilih data yang ingin diexport ke dalam format Excel. File hasil export dapat digunakan kembali untuk import data.
                    </p>
                    
                    <div class="d-flex flex-column gap-3">
                        <!-- Option: All Data -->
                        <div class="form-check stitch-radio-card" :class="{ 'active': exportConfig.type === 'all' }">
                            <input class="form-check-input" type="radio" name="exportType" id="exportAll" value="all" v-model="exportConfig.type">
                            <label class="form-check-label w-100" for="exportAll">
                                <span class="fw-bold d-block">Semua Data</span>
                                <span class="text-muted small">Export seluruh data approval flow yang ada di sistem.</span>
                            </label>
                        </div>

                        <!-- Option: By Approver -->
                        <div class="form-check stitch-radio-card" :class="{ 'active': exportConfig.type === 'approver' }">
                            <input class="form-check-input" type="radio" name="exportType" id="exportApprover" value="approver" v-model="exportConfig.type">
                            <label class="form-check-label w-100" for="exportApprover">
                                <span class="fw-bold d-block">Berdasarkan Approver</span>
                                <span class="text-muted small mb-2 d-block">Export data anggota dibawah naungan approver tertentu.</span>
                                
                                <el-select
                                    v-if="exportConfig.type === 'approver'"
                                    v-model="exportConfig.approverKode"
                                    filterable
                                    placeholder="Pilih Approver"
                                    class="w-100 mt-2"
                                    @click.stop
                                >
                                    <el-option
                                        v-for="a in approversWithMembers"
                                        :key="a.Kode_Karyawan"
                                        :label="`${a.Nama} (${a.member_count}) - ${a.nama_jabatan || 'N/A'}`"
                                        :value="a.Kode_Karyawan"
                                    />
                                </el-select>
                            </label>
                        </div>

                        <!-- Option: By Division -->
                        <div class="form-check stitch-radio-card" :class="{ 'active': exportConfig.type === 'divisi' }">
                            <input class="form-check-input" type="radio" name="exportType" id="exportDivisi" value="divisi" v-model="exportConfig.type">
                            <label class="form-check-label w-100" for="exportDivisi">
                                <span class="fw-bold d-block">Berdasarkan Divisi</span>
                                <span class="text-muted small mb-2 d-block">Export data anggota dalam divisi tertentu.</span>
                                
                                <el-select
                                    v-if="exportConfig.type === 'divisi'"
                                    v-model="exportConfig.divisiId"
                                    filterable
                                    placeholder="Pilih Divisi"
                                    class="w-100 mt-2"
                                    @click.stop
                                >
                                    <el-option
                                        v-for="d in filterOptions.divisi"
                                        :key="d.value"
                                        :label="d.label"
                                        :value="d.value"
                                    />
                                </el-select>
                            </label>
                        </div>

                        <!-- Option: Custom Selection -->
                        <div class="form-check stitch-radio-card" :class="{ 'active': exportConfig.type === 'custom' }">
                            <input class="form-check-input" type="radio" 
                                   name="exportType" id="exportCustom" value="custom" 
                                   v-model="exportConfig.type"
                                   @change="loadMembersForExport">
                            <label class="form-check-label w-100" for="exportCustom">
                                <span class="fw-bold d-block">Item Terpilih</span>
                                <span class="text-muted small">Pilih manual anggota yang ingin diexport.</span>
                                
                                <div v-if="exportConfig.type === 'custom'" class="mt-3 animate__animated animate__fadeIn">
                                    <!-- Search -->
                                    <el-input
                                        v-model="exportMemberFilter.search"
                                        placeholder="Cari nama/NIK..."
                                        size="small"
                                        class="mb-2"
                                        clearable
                                    >
                                        <template #prefix>
                                            <i class="bi bi-search"></i>
                                        </template>
                                    </el-input>

                                    <!-- Loading -->
                                    <div v-if="loadingExportMembers" class="text-center py-3 text-muted small">
                                        <i class="bi bi-arrow-repeat spin me-1"></i> Memuat data...
                                    </div>
                                    
                                    <!-- List -->
                                    <div v-else class="border rounded bg-white overflow-hidden">
                                        <!-- Select All -->
                                        <div class="d-flex justify-content-between align-items-center p-2 bg-light border-bottom">
                                            <label class="d-flex align-items-center gap-2 cursor-pointer m-0 small">
                                                <input 
                                                    type="checkbox" 
                                                    :checked="isAllExportSelected"
                                                    @change="toggleExportSelectAll"
                                                    class="form-check-input"
                                                />
                                                <span class="fw-bold">Pilih Semua ({{ filteredExportMembers.length }})</span>
                                            </label>
                                            <span class="badge bg-primary">{{ exportSelectedMembers.length }}</span>
                                        </div>

                                        <!-- Items -->
                                        <div class="export-member-list" style="max-height: 200px; overflow-y: auto;">
                                            <div v-for="m in filteredExportMembers" :key="m.Kode_Karyawan"
                                                 class="d-flex align-items-center gap-2 p-2 border-bottom hover-bg"
                                                 :class="{ 'bg-primary-subtle border-primary-subtle': isExportSelected(m.Kode_Karyawan) }"
                                                 @click="toggleExportMember(m.Kode_Karyawan)">
                                                <input 
                                                    type="checkbox" 
                                                    :checked="isExportSelected(m.Kode_Karyawan)"
                                                    class="form-check-input mt-0"
                                                    @click.stop
                                                    @change="toggleExportMember(m.Kode_Karyawan)"
                                                />
                                                <div class="flex-1 lh-1">
                                                    <div class="small fw-medium text-dark">{{ m.Nama }}</div>
                                                    <div class="text-muted" style="font-size: 11px;">{{ m.NIK }} • {{ m.nama_jabatan || 'N/A' }}</div>
                                                </div>
                                            </div>
                                            <div v-if="filteredExportMembers.length === 0" class="p-3 text-center text-muted small">
                                                Tidak ada data.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button @click="showExportModal = false" class="stitch-btn secondary">Batal</button>
                <button @click="executeExport" class="stitch-btn primary" :disabled="exporting">
                    <span v-if="exporting">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Exporting...
                    </span>
                    <span v-else>
                        <i class="bi bi-download me-1"></i>
                        Export Excel
                    </span>
                </button>
            </template>
        </Modal>

        <!-- Import Modal -->
        <Modal
            :show="showImportModal"
            :title="importPreviewData.length > 0 ? 'Verifikasi Data Import' : 'Import dari Excel'"
            :size="importPreviewData.length > 0 ? 'large' : 'medium'"
            @close="closeImportModal"
        >
            <template #body>
                <!-- Step 1 & 2: Download Template & Upload File -->
                <div v-if="importPreviewData.length === 0">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-dark fw-medium">1. Download Template</span>
                            <a :href="templateUrl" class="stitch-btn secondary small" target="_blank">
                                <i class="bi bi-download"></i> Download Template
                            </a>
                        </div>
                        <p class="text-muted small">
                            Isi template dengan NIK Anggota dan NIK Approver. Data yang sudah ada akan di-overwrite.
                        </p>
                    </div>

                    <div class="mb-4">
                        <span class="text-dark fw-medium mb-2 d-block">2. Upload File</span>
                        <el-upload
                            ref="uploadRef"
                            :auto-upload="false"
                            :limit="1"
                            accept=".xlsx,.xls"
                            :on-change="handleFileChange"
                            :on-remove="handleFileRemove"
                            drag
                        >
                            <i class="bi bi-cloud-arrow-up display-5 text-primary"></i>
                            <div class="text-muted small mt-2">Klik atau drag file Excel ke sini</div>
                        </el-upload>
                    </div>
                </div>

                <!-- Step 3: Preview & Edit Data -->
                <div v-else>
                    <!-- Summary Cards -->
                    <div class="d-flex gap-3 mb-4">
                        <div class="flex-1 p-3 bg-success-subtle rounded border border-success text-center">
                            <div class="fw-bold text-success display-6">{{ validImportCount }}</div>
                            <div class="small text-success">Valid</div>
                        </div>
                        <div class="flex-1 p-3 bg-danger-subtle rounded border border-danger text-center">
                            <div class="fw-bold text-danger display-6">{{ invalidImportCount }}</div>
                            <div class="small text-danger">Invalid</div>
                        </div>
                        <div class="flex-1 p-3 bg-primary-subtle rounded border border-primary text-center">
                            <div class="fw-bold text-primary display-6">{{ importPreviewData.length }}</div>
                            <div class="small text-primary">Total</div>
                        </div>
                    </div>

                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Review data di bawah. Hapus data yang tidak valid atau tidak diperlukan. Hanya data valid yang bisa diimport.
                    </p>

                    <!-- Preview Table -->
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover border mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="small text-muted" style="width: 50px;">No</th>
                                    <th class="small text-muted">NIK Anggota</th>
                                    <th class="small text-muted">Nama Anggota</th>
                                    <th class="small text-muted">NIK Approver</th>
                                    <th class="small text-muted">Nama Approver</th>
                                    <th class="small text-muted" style="width: 80px;">Status</th>
                                    <th class="small text-muted text-center" style="width: 60px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in importPreviewData" :key="item.id" 
                                    :class="{ 'table-danger': !item.is_valid }">
                                    <td class="small">{{ idx + 1 }}</td>
                                    <td class="small fw-medium">{{ item.nik_anggota }}</td>
                                    <td class="small">{{ item.nama_anggota || '-' }}</td>
                                    <td class="small fw-medium">{{ item.nik_approver }}</td>
                                    <td class="small">{{ item.nama_approver || '-' }}</td>
                                    <td>
                                        <span v-if="item.is_valid" class="badge bg-success-subtle text-success border border-success">
                                            <i class="bi bi-check-circle me-1"></i>Valid
                                        </span>
                                        <span v-else class="badge bg-danger-subtle text-danger border border-danger" 
                                              :title="item.errors.join(', ')">
                                            <i class="bi bi-x-circle me-1"></i>Invalid
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button @click="removeImportRow(idx)" 
                                                class="btn btn-sm btn-outline-danger border-0 p-1"
                                                title="Hapus baris ini">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Error Details -->
                    <div v-if="invalidImportCount > 0" class="mt-3 p-3 bg-warning-subtle rounded border border-warning">
                        <div class="fw-medium text-warning-emphasis mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ invalidImportCount }} baris tidak valid dan tidak akan diimport
                        </div>
                        <div class="small text-muted">
                            Hapus baris yang tidak valid atau perbaiki file Excel dan upload ulang.
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button @click="closeImportModal" class="stitch-btn secondary">
                    {{ importPreviewData.length > 0 ? 'Batal' : 'Tutup' }}
                </button>
                
                <!-- Validate Button -->
                <button 
                    v-if="importPreviewData.length === 0" 
                    @click="validateImport" 
                    class="stitch-btn primary"
                    :disabled="!importFile || validating"
                >
                    <span v-if="validating">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Validating...
                    </span>
                    <span v-else>Validasi Data</span>
                </button>

                <!-- Back & Import Buttons -->
                <template v-else>
                    <button @click="resetImportPreview" class="stitch-btn secondary">
                        <i class="bi bi-arrow-left me-1"></i>Upload Ulang
                    </button>
                    <button 
                        @click="executeImportFromPreview" 
                        class="stitch-btn primary"
                        :disabled="validImportCount === 0 || importing"
                    >
                        <span v-if="importing">
                            <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                            Importing...
                        </span>
                        <span v-else>
                            <i class="bi bi-check-lg me-1"></i>
                            Import {{ validImportCount }} Data
                        </span>
                    </button>
                </template>
            </template>
        </Modal>

        <!-- Confirmation Modals -->
        
        <!-- Confirm Transfer Modal -->
        <Modal
            :show="showConfirmTransfer"
            title="Konfirmasi Transfer"
            size="small"
            @close="showConfirmTransfer = false"
        >
            <template #body>
                <div class="text-center py-3">
                    <div class="mb-3">
                        <i class="bi bi-arrow-left-right text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-dark fw-medium mb-2">Transfer {{ getOldApproverMemberCount }} anggota?</p>
                    <p class="text-muted small m-0">
                        Semua anggota dari approver lama akan dipindahkan ke approver baru.
                    </p>
                </div>
            </template>
            <template #footer>
                <button @click="showConfirmTransfer = false" class="stitch-btn secondary">Batal</button>
                <button @click="doTransfer" class="stitch-btn primary" :disabled="transferring">
                    <span v-if="transferring">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Memproses...
                    </span>
                    <span v-else>Ya, Transfer</span>
                </button>
            </template>
        </Modal>

        <!-- Confirm Bulk Assign Modal -->
        <Modal
            :show="showConfirmBulkAssign"
            title="Konfirmasi Assign"
            size="small"
            @close="showConfirmBulkAssign = false"
        >
            <template #body>
                <div class="text-center py-3">
                    <div class="mb-3">
                        <i class="bi bi-person-plus text-success" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-dark fw-medium mb-2">Assign {{ selectedMembers.length }} anggota?</p>
                    <p class="text-muted small m-0">
                        Anggota terpilih akan di-assign ke approver yang dipilih.
                    </p>
                </div>
            </template>
            <template #footer>
                <button @click="showConfirmBulkAssign = false" class="stitch-btn secondary">Batal</button>
                <button @click="doBulkAssign" class="stitch-btn primary" :disabled="assigning">
                    <span v-if="assigning">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Memproses...
                    </span>
                    <span v-else>Ya, Assign</span>
                </button>
            </template>
        </Modal>

        <!-- Confirm Save Edit Modal -->
        <Modal
            :show="showConfirmSaveEdit"
            title="Konfirmasi Simpan"
            size="small"
            @close="showConfirmSaveEdit = false"
        >
            <template #body>
                <div class="text-center py-3">
                    <div class="mb-3">
                        <i class="bi bi-pencil-square text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-dark fw-medium mb-2">Simpan perubahan?</p>
                    <p class="text-muted small m-0">
                        Data assignment akan diupdate dengan approver baru.
                    </p>
                </div>
            </template>
            <template #footer>
                <button @click="showConfirmSaveEdit = false" class="stitch-btn secondary">Batal</button>
                <button @click="doSaveEdit" class="stitch-btn primary" :disabled="saving">
                    <span v-if="saving">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Menyimpan...
                    </span>
                    <span v-else>Ya, Simpan</span>
                </button>
            </template>
        </Modal>

        <!-- Confirm Import Modal -->
        <Modal
            :show="showConfirmImport"
            title="Konfirmasi Import"
            size="small"
            @close="showConfirmImport = false"
        >
            <template #body>
                <div class="text-center py-3">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-arrow-up text-success" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-dark fw-medium mb-2">Import {{ validImportCount }} data?</p>
                    <p class="text-muted small m-0">
                        Data yang sudah diverifikasi akan diimport ke sistem.
                    </p>
                </div>
            </template>
            <template #footer>
                <button @click="showConfirmImport = false" class="stitch-btn secondary">Batal</button>
                <button @click="doImport" class="stitch-btn primary" :disabled="importing">
                    <span v-if="importing">
                        <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite"></i>
                        Importing...
                    </span>
                    <span v-else>Ya, Import</span>
                </button>
            </template>
        </Modal>

        <!-- Confirm Remove Modal -->
        <Modal
            :show="showConfirmRemove"
            title="Konfirmasi Hapus"
            size="small"
            @close="showConfirmRemove = false"
        >
            <template #body>
                <div class="text-center py-3">
                    <div class="mb-3">
                        <i class="bi bi-trash text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-dark fw-medium mb-2">Hapus assignment ini?</p>
                    <p class="text-muted small m-0" v-if="removingAssignment">
                        Assignment untuk <strong>{{ removingAssignment.requester_nama }}</strong> akan dihapus.
                    </p>
                </div>
            </template>
            <template #footer>
                <button @click="showConfirmRemove = false" class="stitch-btn secondary">Batal</button>
                <button @click="doRemove" class="stitch-btn danger">
                    <i class="bi bi-trash me-1"></i> Ya, Hapus
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import { defineComponent } from "vue";
import HeaderKpi from "@/components/legacy/header.vue";
import ApprovalTabs from "@/components/legacy/toggleSectionV2.vue";
import Modal from "@/components/legacy/modalV2-noir.vue";
import ModernFilter from "@/components/legacy/Filter/modernFilter.vue";
import quickStat from "@/components/legacy/quickStat.vue";
import moment from "moment";
import axios from "axios";
import { ElMessage, ElMessageBox } from "element-plus";
import { debounce } from "lodash";

export default defineComponent({
    name: "ESS/Attendance/Shift/Approval/ApprovalFlowShift",
    components: {
        HeaderKpi,
        ApprovalTabs,
        Modal,
        ModernFilter,
        quickStat,
    },
    props: {
        initialStats: { type: Object, default: () => ({}) },
        filterOptions: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            activeTab: "dashboard",
            stats: this.initialStats,
            
            // Tabs
            tabs: [
                { id: "dashboard", label: "Dashboard", icon: "bi bi-speedometer2", count:null },
                { id: "kelola", label: "Kelola Approver", icon: "bi bi-person-plus", count:null },
                { id: "daftar", label: "Daftar Assignment", icon: "bi bi-list-ul", count:null },
            ],
            
            // Transfer
            transferData: { oldApproverKode: null, newApproverKode: null },
            transferring: false,
            approversWithMembers: [],
            allApprovers: [],
            
            // Bulk Assign
            bulkAssign: { approverKode: null },
            members: [],
            selectedMembers: [],
            memberFilter: { search: "", status: "all" },
            loadingMembers: false,
            assigning: false,
            
            // Assignments
            assignments: [],
            assignmentLoading: false,
            assignmentFilters: { search: "", approver: "", divisi: "" },
            assignmentPagination: { current_page: 1, total_pages: 1 },
            
            // Edit Modal
            showEditModal: false,
            editingAssignment: null,
            saving: false,
            
            // Import
            showImportModal: false,
            importFile: null,
            importValidation: null,
            importPreviewData: [],
            validating: false,
            importing: false,
            
            // Export
            showExportModal: false,
            exportConfig: {
                type: 'all', // all, approver, divisi, custom
                approverKode: null,
                divisiId: null,
            },
            exporting: false,
            exportMembers: [],
            exportSelectedMembers: [],
            exportMemberFilter: { search: "" },
            loadingExportMembers: false,
            
            filterOptions: {
                divisi: [],
            },
            
            // Recent Activity
            recentActivity: [],
            
            // Confirmation Modals
            showConfirmTransfer: false,
            showConfirmBulkAssign: false,
            showConfirmSaveEdit: false,
            showConfirmImport: false,
            showConfirmRemove: false,
            removingAssignment: null,
        };
    },
    computed: {
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? "day" : "night";
        },
         formattedDate() {
            return new Intl.DateTimeFormat("id-ID", {
                weekday: "long",
                day: "numeric",
                month: "long",
                year: "numeric",
            }).format(new Date());
        },
        quickStatField() {
            return [
                {
                    title: "Total Approver",
                    value: this.stats.total_approvers || 0,
                    icon: "bi bi-people",
                    bgIcon: "bg-primary",
                    color: "blue",
                    sentiment: "primary",
                    size: "col-4",
                },
                {
                    title: "Total Assignment",
                    value: this.stats.total_assignments || 0,
                    icon: "bi bi-link-45deg",
                    bgIcon: "bg-success",
                    color: "green",
                    sentiment: "success",
                    size: "col-4",
                },
                {
                    title: "Tanpa Approver",
                    value: this.stats.members_without_approver || 0,
                    icon: "bi bi-exclamation-triangle",
                    bgIcon: "bg-warning",
                    color: "orange",
                    sentiment: "warning",
                    size: "col-4",
                },
            ];
        },
        filteredMembers() {
            let result = this.members;
            if (this.memberFilter.search) {
                const s = this.memberFilter.search.toLowerCase();
                result = result.filter(m => 
                    m.Nama?.toLowerCase().includes(s) || 
                    m.NIK?.toLowerCase().includes(s)
                );
            }
            return result;
        },
        isAllSelected() {
            return this.filteredMembers.length > 0 && 
                   this.filteredMembers.every(m => this.selectedMembers.includes(m.Kode_Karyawan));
        },
        availableNewApprovers() {
            return this.allApprovers.filter(a => a.Kode_Karyawan !== this.transferData.oldApproverKode);
        },
        getOldApproverMemberCount() {
            const approver = this.approversWithMembers.find(a => a.Kode_Karyawan === this.transferData.oldApproverKode);
            return approver?.member_count || 0;
        },
        templateUrl() {
            return "/approval-flow-shift/template";
        },
        validImportCount() {
            return this.importPreviewData.filter(item => item.is_valid).length;
        },
        invalidImportCount() {
            return this.importPreviewData.filter(item => !item.is_valid).length;
        },
        assignmentFilterConfig() {
            return {
                searchable: true,
                searchPlaceholder: "Cari nama/NIK...",
                showResultsCount: true,
                filters: [
                    {
                        key: "approver",
                        label: "Approver",
                        type: "select",
                        filterable: true,
                        options: [
                            { label: "Semua", value: "" },
                            ...(this.filterOptions.approvers || []).map(a => ({
                                label: a.label,
                                value: a.value,
                            })),
                        ],
                        col: 6,
                    },
                    {
                        key: "divisi",
                        label: "Divisi",
                        type: "select",
                        filterable: true,
                        options: [
                            { label: "Semua", value: "" },
                            ...(this.filterOptions.divisi || []).map(d => ({
                                label: d.label,
                                value: d.value,
                            })),
                        ],
                        col: 6,
                    },
                ],
            };
        },
        filteredExportMembers() {
            let result = this.exportMembers;
            if (this.exportMemberFilter.search) {
                const s = this.exportMemberFilter.search.toLowerCase();
                result = result.filter(m => 
                    m.Nama?.toLowerCase().includes(s) || 
                    m.NIK?.toLowerCase().includes(s)
                );
            }
            return result;
        },
        isAllExportSelected() {
            return this.filteredExportMembers.length > 0 && 
                   this.filteredExportMembers.every(m => this.exportSelectedMembers.includes(m.Kode_Karyawan));
        },
    },
    mounted() {
        this.loadStats();
        this.loadApprovers();
        this.loadRecentActivity();
        this.loadFilterOptions();
    },
    methods: {
        handleTabChange(tabId) {
            this.activeTab = tabId;
            if (tabId === "daftar") {
                this.loadAssignments();
            }
        },
        
        // API Calls
        async loadStats() {
            try {
                const res = await axios.get("/approval-flow-shift/stats");
                this.stats = res.data.result || res.data.data || {};
            } catch (e) {
                console.error("Failed to load stats:", e);
            }
        },
        
        async loadApprovers() {
            try {
                const [res1, res2] = await Promise.all([
                    axios.get("/approval-flow-shift/approvers"),
                    axios.get("/approval-flow-shift/approvers"),
                ]);
                this.allApprovers = res1.data.data || [];
                this.approversWithMembers = (res2.data.data || []).filter(a => a.member_count > 0);
            } catch (e) {
                console.error(e);
            }
        },
        
        async loadMembersForAssign() {
            if (!this.bulkAssign.approverKode) return;
            this.loadingMembers = true;
            this.selectedMembers = [];
            try {
                const res = await axios.get("/approval-flow-shift/members", {
                    params: { status: this.memberFilter.status },
                });
                this.members = res.data.data || [];
            } catch (e) {
                console.error(e);
            } finally {
                this.loadingMembers = false;
            }
        },
        
        filterMembers() {
            this.loadMembersForAssign();
        },
        
        debouncedFilterMembers: debounce(function() {
            // Client-side filtering, no API call needed
        }, 300),
        
        async loadAssignments(page = 1) {
            this.assignmentLoading = true;
            try {
                const res = await axios.get("/approval-flow-shift/assignments", {
                    params: {
                        page,
                        ...this.assignmentFilters,
                    },
                });
                this.assignments = res.data.result?.items || [];
                this.assignmentPagination = res.data.pagination || {};
            } catch (e) {
                console.error(e);
            } finally {
                this.assignmentLoading = false;
            }
        },
        
        handleAssignmentFilters(filters) {
            this.assignmentFilters = { ...filters };
            this.loadAssignments(1);
        },
        
        async loadRecentActivity() {
            try {
                const res = await axios.get("/approval-flow-shift/history", { params: { limit: 5 } });
                this.recentActivity = res.data.result?.items || [];
            } catch (e) {
                console.error(e);
            }
        },
        
        async loadFilterOptions() {
            try {
                const res = await axios.get("/approval-flow-shift/filter-options");
                this.filterOptions.divisi = res.data.data.divisi || [];
            } catch (e) {
                console.error(e);
            }
        },
        
        async executeExport() {
            this.exporting = true;
            try {
                const params = {};
                if (this.exportConfig.type === 'approver') {
                    if (!this.exportConfig.approverKode) {
                        ElMessage.warning('Pilih approver terlebih dahulu');
                        this.exporting = false;
                        return;
                    }
                    params.approver_kode = this.exportConfig.approverKode;
                } else if (this.exportConfig.type === 'divisi') {
                    if (!this.exportConfig.divisiId) {
                        ElMessage.warning('Pilih divisi terlebih dahulu');
                        this.exporting = false;
                        return;
                    }
                    params.divisi_id = this.exportConfig.divisiId;
                } else if (this.exportConfig.type === 'custom') {
                    if (this.exportSelectedMembers.length === 0) {
                        ElMessage.warning('Pilih anggota terlebih dahulu');
                        this.exporting = false;
                        return;
                    }
                    params.member_kodes = this.exportSelectedMembers;
                }

                const response = await axios.post('/approval-flow-shift/export', params, {
                    responseType: 'blob'
                });
                
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `export_approval_flow_${new Date().getTime()}.xlsx`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.showExportModal = false;
                ElMessage.success('Export berhasil');
            } catch (e) {
                ElMessage.error('Gagal export data');
                console.error(e);
            } finally {
                this.exporting = false;
            }
        },
        
        async loadMembersForExport() {
            this.loadingExportMembers = true;
            try {
                // Reuse existing endpoint, fetch all members (status=all)
                const res = await axios.get("/approval-flow-shift/members", {
                    params: { status: 'all' },
                });
                this.exportMembers = res.data.data || [];
            } catch (e) {
                console.error("Failed to load export members", e);
            } finally {
                this.loadingExportMembers = false;
            }
        },

        isExportSelected(kode) {
            return this.exportSelectedMembers.includes(kode);
        },

        toggleExportMember(kode) {
            const idx = this.exportSelectedMembers.indexOf(kode);
            if (idx === -1) {
                this.exportSelectedMembers.push(kode);
            } else {
                this.exportSelectedMembers.splice(idx, 1);
            }
        },

        toggleExportSelectAll() {
            if (this.isAllExportSelected) {
                this.exportSelectedMembers = [];
            } else {
                this.exportSelectedMembers = this.filteredExportMembers.map(m => m.Kode_Karyawan);
            }
        },
        
        // Selection
        isSelected(kode) {
            return this.selectedMembers.includes(kode);
        },
        
        toggleMember(kode) {
            const idx = this.selectedMembers.indexOf(kode);
            if (idx === -1) {
                this.selectedMembers.push(kode);
            } else {
                this.selectedMembers.splice(idx, 1);
            }
        },
        
        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedMembers = [];
            } else {
                this.selectedMembers = this.filteredMembers.map(m => m.Kode_Karyawan);
            }
        },
        
        resetSelection() {
            this.selectedMembers = [];
        },
        
        // Actions
        executeBulkAssign() {
            if (this.selectedMembers.length === 0) return;
            this.showConfirmBulkAssign = true;
        },
        
        async doBulkAssign() {
            this.showConfirmBulkAssign = false;
            this.assigning = true;
            try {
                const res = await axios.post("/approval-flow-shift/bulk-assign", {
                    approver_kode: this.bulkAssign.approverKode,
                    member_kodes: this.selectedMembers,
                });
                
                ElMessage.success(res.data.message);
                this.resetSelection();
                this.loadStats();
                this.loadMembersForAssign();
                this.loadApprovers();
                this.loadAssignments();
                this.loadRecentActivity();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal assign");
            } finally {
                this.assigning = false;
            }
        },
        
        onTransferOldApproverChange() {
            this.transferData.newApproverKode = null;
        },
        
        executeTransfer() {
            if (!this.transferData.oldApproverKode || !this.transferData.newApproverKode) return;
            this.showConfirmTransfer = true;
        },
        
        async doTransfer() {
            this.showConfirmTransfer = false;
            this.transferring = true;
            try {
                const res = await axios.post("/approval-flow-shift/transfer", {
                    old_approver_kode: this.transferData.oldApproverKode,
                    new_approver_kode: this.transferData.newApproverKode,
                });
                
                ElMessage.success(res.data.message);
                this.transferData = { oldApproverKode: null, newApproverKode: null };
                this.loadStats();
                this.loadApprovers();
                this.loadAssignments();
                this.loadRecentActivity();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal transfer");
            } finally {
                this.transferring = false;
            }
        },
        
        // Edit/Remove
        editAssignment(a) {
            this.editingAssignment = { ...a, new_approver_kode: a.approver_kode };
            this.showEditModal = true;
        },
        
        saveEditAssignment() {
            if (!this.editingAssignment?.new_approver_kode) return;
            this.showConfirmSaveEdit = true;
        },
        
        async doSaveEdit() {
            this.showConfirmSaveEdit = false;
            this.saving = true;
            try {
                await axios.put(`/approval-flow-shift/update/${this.editingAssignment.id}`, {
                    approver_kode: this.editingAssignment.new_approver_kode,
                });
                
                ElMessage.success("Assignment berhasil diupdate");
                this.showEditModal = false;
                this.loadStats();
                this.loadApprovers();
                this.loadAssignments();
                this.loadRecentActivity();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal update");
            } finally {
                this.saving = false;
            }
        },
        
        removeAssignment(a) {
            this.removingAssignment = a;
            this.showConfirmRemove = true;
        },
        
        async doRemove() {
            if (!this.removingAssignment) return;
            this.showConfirmRemove = false;
            
            try {
                await axios.delete(`/approval-flow-shift/remove/${this.removingAssignment.id}`);
                ElMessage.success("Assignment berhasil dihapus");
                this.loadStats();
                this.loadApprovers();
                this.loadAssignments();
                this.loadRecentActivity();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal hapus");
            } finally {
                this.removingAssignment = null;
            }
        },
        
        // Import
        handleFileChange(file) {
            this.importFile = file.raw;
            this.importValidation = null;
            this.importPreviewData = [];
        },
        
        handleFileRemove() {
            this.importFile = null;
            this.importValidation = null;
            this.importPreviewData = [];
        },
        
        async validateImport() {
            if (!this.importFile) return;
            
            this.validating = true;
            try {
                const formData = new FormData();
                formData.append("file", this.importFile);
                
                const res = await axios.post("/approval-flow-shift/import/validate", formData);
                this.importValidation = res.data.data;
                // Populate preview data for the editable table
                this.importPreviewData = res.data.data?.preview || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal validasi");
            } finally {
                this.validating = false;
            }
        },
        
        removeImportRow(index) {
            this.importPreviewData.splice(index, 1);
        },
        
        resetImportPreview() {
            this.importPreviewData = [];
            this.importValidation = null;
            this.importFile = null;
            if (this.$refs.uploadRef) {
                this.$refs.uploadRef.clearFiles();
            }
        },
        
        executeImportFromPreview() {
            // Only import valid rows
            const validItems = this.importPreviewData.filter(item => item.is_valid);
            
            if (validItems.length === 0) {
                ElMessage.warning("Tidak ada data valid untuk diimport");
                return;
            }
            
            this.showConfirmImport = true;
        },
        
        async doImport() {
            this.showConfirmImport = false;
            const validItems = this.importPreviewData.filter(item => item.is_valid);
            
            this.importing = true;
            try {
                const res = await axios.post("/approval-flow-shift/import/preview", {
                    items: validItems.map(item => ({
                        kode_anggota: item.kode_anggota,
                        kode_approver: item.kode_approver,
                    })),
                });
                
                ElMessage.success(res.data.message);
                this.closeImportModal();
                this.loadStats();
                this.loadApprovers();
                this.loadAssignments();
                this.loadRecentActivity();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal import");
            } finally {
                this.importing = false;
            }
        },
        
        async executeImport() {
            if (!this.importFile) return;
            
            this.importing = true;
            try {
                const formData = new FormData();
                formData.append("file", this.importFile);
                
                const res = await axios.post("/approval-flow-shift/import", formData);
                ElMessage.success(res.data.message);
                this.closeImportModal();
                this.loadStats();
                this.loadApprovers();
                this.loadAssignments();
                this.loadRecentActivity();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal import");
            } finally {
                this.importing = false;
            }
        },
        
        closeImportModal() {
            this.showImportModal = false;
            this.importFile = null;
            this.importValidation = null;
            this.importPreviewData = [];
            if (this.$refs.uploadRef) {
                this.$refs.uploadRef.clearFiles();
            }
        },
        
        // Helpers
        formatDateTime(date) {
            return date ? moment(date).format("DD MMM YYYY, HH:mm") : "-";
        },
        
        getActivityIcon(type) {
            const icons = {
                ASSIGN: "bi bi-person-plus",
                TRANSFER: "bi bi-arrow-left-right",
                IMPORT: "bi bi-file-earmark-arrow-up",
                DELETE: "bi bi-trash",
            };
            return icons[type] || "bi bi-circle";
        },
        
        getActivityLabel(act) {
            const labels = {
                ASSIGN: "Bulk Assign",
                TRANSFER: `Transfer: ${act.old_approver_nama || 'N/A'} → ${act.new_approver_nama || 'N/A'}`,
                IMPORT: `Import: ${act.Import_File_Name || 'Excel'}`,
                DELETE: "Hapus Assignment",
            };
            return labels[act.Jenis_Transaksi] || act.Jenis_Transaksi;
        },
    },
});
</script>

<style scoped>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.bi{
    display: inline-table;

}

.member-grid {
    scrollbar-width: thin;
}

.member-item {
    transition: all 0.2s ease;
}

.member-item:hover {
    background: var(--surface-soft);
}

.cursor-pointer {
    cursor: pointer;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate__fadeIn {
    animation: fadeIn 0.3s ease-out forwards;
}

.spin {
    animation: spin 1s linear infinite;
}

.hover-bg:hover {
    background-color: var(--surface-soft);
}
</style>

<style scoped>
/* =========================================
   PREMIUM DESIGN SYSTEM 
   ========================================= */

   /* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

:root {
    /* Color Palette - User Provided (Indigo Theme) */
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

    /* Gradients (Derived) */
    --gradient-primary: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
}

.tengahkan {
    display: flex;
    justify-content: center;
    align-items: center;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

/* Hide default checkbox */
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

/* Track */
.slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d1d5db; /* gray */
    border-radius: 999px;
    transition: background-color 0.3s ease;
}

/* Thumb */
.slider::before {
    content: "";
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    transition: transform 0.3s ease;
}

/* Checked */
.toggle-switch input:checked + .slider {
    background-color: var(--hcis-indigo-600); /* indigo */
}

.toggle-switch input:checked + .slider::before {
    transform: translateX(24px);
}

/* Optional focus */
.toggle-switch input:focus + .slider {
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
}

/* Label text */
.toggle-label {
    margin-left: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

/* Universal Icon Centering */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* =========================================
   UTILITY CLASSES (Tailwind Mimic)
   ========================================= */
.text-primary {
    color: var(--primary) !important;
}
.text-secondary {
    color: var(--secondary) !important;
}
.text-success {
    color: var(--success) !important;
}
.text-danger {
    color: var(--danger) !important;
}
.text-warning {
    color: var(--warning) !important;
}
.text-muted {
    color: var(--text-muted) !important;
}
.text-dark {
    color: var(--text) !important;
}

.bg-primary {
    background-color: var(--primary) !important;
}
.bg-primary-subtle {
    background-color: var(--hcis-indigo-100) !important;
    color: var(--primary-dark) !important;
}
.bg-surface {
    background-color: var(--surface) !important;
}
.bg-light {
    background-color: var(--surface-soft) !important;
}

.rounded-lg {
    border-radius: var(--radius) !important;
}
.rounded-xl {
    border-radius: var(--radius-lg) !important;
}
.rounded-full {
    border-radius: 9999px !important;
}

.shadow-sm {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
}
.shadow {
    box-shadow: var(--shadow) !important;
}
.shadow-lg {
    box-shadow: var(--shadow-lg) !important;
}

.border {
    border: 1px solid var(--border) !important;
}
.border-0 {
    border: none !important;
}

.flex {
    display: flex !important;
}
.flex-col {
    flex-direction: column !important;
}
.items-center {
    align-items: center !important;
}
.justify-between {
    justify-content: space-between !important;
}
.gap-2 {
    gap: 0.5rem !important;
}
.gap-4 {
    gap: 1rem !important;
}

.p-4 {
    padding: 1rem !important;
}
.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}
.px-4 {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.w-full {
    width: 100% !important;
}
.h-full {
    height: 100% !important;
}

.font-bold {
    font-weight: 700 !important;
}
.font-medium {
    font-weight: 500 !important;
}
.text-sm {
    font-size: 0.875rem !important;
}
.text-xs {
    font-size: 0.75rem !important;
}

/* =========================================
   GLOBAL LAYOUT & TYPOGRAPHY
   ========================================= */

.cuti-app-container {
    background-color: var(--bg-container);
    min-height: 100vh;
    padding-bottom: 2rem;
    /* font-family: 'Inter', system-ui, -apple-system, sans-serif; */
    color: var(--text);
}

.main-content-wrapper {
    /* max-width: 1400px; */
    /* margin: -40px auto 0; Overlap header slightly for premium look */
    /* padding: 0 1.5rem; */
    position: relative;
    z-index: 10;
}

.content-container {
    /* background: var(--surface); */
    /* border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); */
    min-height: 500px;
    overflow: hidden;
    /* border: 1px solid var(--border); */
}

/* =========================================
   COMPONENTS
   ========================================= */

/* Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.btn-modern.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.btn-modern.btn-primary:hover {
    filter: brightness(110%);
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(79, 70, 229, 0.3);
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    transition: all 0.2s;
    background: transparent;
    border: none;
}

.btn-icon:hover {
    background: var(--surface-soft);
    color: var(--primary);
}

/* Filter Section */
.filter-section {
    padding: 1.5rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    margin-bottom: 0 !important; /* Override template mb-4 */
}

.nav-group {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    padding: 0.25rem;
    border-radius: var(--radius-sm);
}

/* =========================================
   CALENDAR GRID SYSTEM
   ========================================= */

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1rem;
    padding: 1.5rem;
    background: var(--surface-soft);
}

.calendar-cell {
    background: var(--surface);
    border-radius: var(--radius);
    min-height: 180px;
    padding: 1rem;
    border: 1px solid var(--border);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
}

.calendar-cell:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow);
    z-index: 2;
}

.calendar-cell.is-today {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-violet-50), var(--hcis-white));
}

.calendar-cell.is-backdate {
    opacity: 0.7;
    background: #fcfcfc;
    cursor: default;
}

.calendar-cell.is-backdate:hover {
    transform: none;
    box-shadow: none;
    border-color: var(--border);
}

/* Cell Header */
.cell-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.day-name {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
}

.date-circle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text);
}

.date-circle.current-date {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
}

/* Shift Content within Cell */
.shift-info-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.75rem;
    text-align: center;
    margin-top: auto;
}

.shift-time-badge {
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--primary-dark);
    display: inline-block;
    margin-bottom: 0.5rem;
    border: 1px solid var(--border);
}

.shift-name {
    font-weight: 600;
    color: var(--text);
    font-size: 0.875rem;
    display: block;
}

.empty-shift-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0.4;
    transition: 0.2s;
}

.calendar-cell:hover .empty-shift-state {
    opacity: 0.8;
}

.dashed-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px dashed var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.calendar-cell:hover .dashed-circle {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05); /* Tint */
}

/* =========================================
   HISTORY TAB & TABLES
   ========================================= */

.tab-content-improved {
    padding: 0;
}

/* Reuse styling from Reference KuotaManagement */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.modern-table th {
    background: var(--surface-soft);
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}

.modern-table tr:hover td {
    background-color: var(--surface-soft);
}

/* =========================================
   MODALS & FORMS
   ========================================= */

.detail-row {
    display: flex;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.detail-label {
    width: 140px;
    color: var(--text-muted);
    font-weight: 500;
}

.detail-value {
    flex: 1;
    color: var(--text);
    font-weight: 500;
}

/* Responsive */
@media (max-width: 1024px) {
    .calendar-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    /* .main-content-wrapper {
        margin-top: -20px;
        padding: 0 1rem;
    } */

    .calendar-grid {
        grid-template-columns: repeat(2, 1fr);
        padding: 1rem;
        gap: 0.5rem;
    }

    .calendar-cell {
        min-height: 140px;
    }

    .filter-section {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .calendar-grid {
        grid-template-columns: 1fr;
    }
}
/* =========================================
   MODALS & FORMS EXTRA
   ========================================= */

/* Form Groups & Inputs */
.form-field-group {
    margin-bottom: 1.25rem;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    display: block;
}

.form-label-sm {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.35rem;
    display: block;
}

.form-control,
.form-textarea {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.625rem 0.875rem;
    font-size: 0.9rem;
    color: var(--text);
    background: var(--surface);
    transition: all 0.2s;
    width: 100%;
}

.form-control:focus,
.form-textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

/* Stitch Radio Card */
.stitch-radio-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    transition: all 0.2s;
    cursor: pointer;
    background: var(--surface);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.stitch-radio-card:hover {
    background: var(--surface-soft);
    border-color: var(--primary-light);
}

.stitch-radio-card.active {
    background: var(--hcis-indigo-50);
    border-color: var(--primary);
    box-shadow: 0 0 0 1px var(--primary);
}

.stitch-radio-card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--surface-soft);
}

.stitch-radio-card .form-check-input {
    margin-top: 0.25rem;
}


/* Custom Checkbox Card */
.form-check-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
}

.form-check-card:hover {
    border-color: var(--primary-light);
    background: white;
}

.form-check-input {
    margin-top: 0.25rem;
    cursor: pointer;
}

/* v-select Overrides for Premium Look */
.modern-select .vs__dropdown-toggle {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 4px 0; /* Adjust internal padding */
    background: var(--surface);
    transition: all 0.2s;
}

.modern-select .vs__search::placeholder {
    color: var(--text-light);
}

.modern-select.vs--open .vs__dropdown-toggle {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    border-bottom-color: transparent;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}

.modern-select .vs__dropdown-menu {
    border: 1px solid var(--primary);
    border-top: none;
    box-shadow: var(--shadow-lg);
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
    padding: 0;
}

.modern-select .vs__dropdown-option {
    padding: 8px 12px;
    color: var(--text);
}

.modern-select .vs__dropdown-option--highlight {
    background: var(--primary);
    color: white;
}

/* Wizard Steps */
.wizard-steps {
    display: flex;
    padding: 0;
    margin-bottom: 2rem;
    position: relative;
}

.step {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.5rem;
    opacity: 0.6;
    transition: all 0.3s;
    position: relative;
    z-index: 1;
}

.step::after {
    content: "";
    position: absolute;
    top: 14px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--border);
    z-index: -1;
}

.step:last-child::after {
    display: none;
}

.step.active,
.step.completed {
    opacity: 1;
}

.step.completed::after {
    background: var(--success);
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--surface);
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--text-muted);
    transition: all 0.3s;
}

.step.active .step-number {
    border-color: var(--primary);
    background: var(--primary);
    color: white;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
}

.step.completed .step-number {
    border-color: var(--success);
    background: var(--success);
    color: white;
}

.step-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block; /* Force display */
}

/* Type Selection Cards */
.type-selection {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.type-card {
    padding: 2rem 1.5rem;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.type-card.selected {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-indigo-50), var(--hcis-white));
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.type-card .icon-wrapper {
    width: 64px;
    height: 64px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    transition: all 0.2s;
}

.type-card.selected .icon-wrapper {
    background: var(--primary);
    color: white;
}

.type-card h6 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text);
}

/* Date Selection List */
.selected-dates-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selected-date-item {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    border-radius: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s;
}

.selected-date-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-sm);
}

/* Review Summary */
.review-summary {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

/* =========================================
   VERTICAL ROSTER LIST STYLES
   ========================================= */
.vertical-roster-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: all 0.2s ease;
    gap: 1rem;
}

.vertical-roster-card:hover {
    box-shadow: var(--shadow);
    z-index: 10;
}

.vertical-roster-card.opacity-60 {
    opacity: 0.6;
}

.date-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 70px;
    padding-right: 1rem;
    border-right: 1px solid var(--border);
}

.content-col {
    flex: 1;
}

.actions-col {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.badge-status {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-800);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    text-transform: uppercase;
}

.add-shift-btn-dashed {
    width: 100%;
    height: 3rem;
    border: 2px dashed var(--border);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--text-muted);
    background: transparent;
    transition: all 0.2s;
}

.add-shift-btn-dashed:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05); /* Tint */
}

@media (max-width: 640px) {
    .vertical-roster-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .date-col {
        border-right: none;
        border-bottom: 1px solid var(--border);
        width: 100%;
        padding-right: 0;
        padding-bottom: 0.5rem;
        flex-direction: row;
        justify-content: space-between;
    }

    .actions-col {
        width: 100%;
        justify-content: flex-end;
        border-top: 1px solid var(--border);
        padding-top: 0.5rem;
    }
}

/* =========================================
   STITCH DESIGN SYSTEM - COMPREHENSIVE STYLES
   ========================================= */

/* Additional Utility Classes */
.grid {
    display: grid !important;
}
.grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
}
.grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
}
.grid-cols-7 {
    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
}
.grid-cols-12 {
    grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
}
.col-span-1 {
    grid-column: span 1 / span 1 !important;
}
.col-span-2 {
    grid-column: span 2 / span 2 !important;
}
.col-span-3 {
    grid-column: span 3 / span 3 !important;
}
.col-span-5 {
    grid-column: span 5 / span 5 !important;
}

.gap-3 {
    gap: 0.75rem !important;
}
.gap-6 {
    gap: 1.5rem !important;
}
.gap-8 {
    gap: 2rem !important;
}

.p-1 {
    padding: 0.25rem !important;
}
.p-2 {
    padding: 0.5rem !important;
}
.p-5 {
    padding: 1.25rem !important;
}
.p-6 {
    padding: 1.5rem !important;
}
.p-8 {
    padding: 2rem !important;
}
.px-3 {
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
}
.px-6 {
    padding-left: 1.5rem !important;
    padding-right: 1.5rem !important;
}
.py-3 {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}
.py-4 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.pl-1 {
    padding-left: 0.25rem !important;
}

.mb-1 {
    margin-bottom: 0.25rem !important;
}
.mb-2 {
    margin-bottom: 0.5rem !important;
}
.mb-4 {
    margin-bottom: 1rem !important;
}
.mb-6 {
    margin-bottom: 1.5rem !important;
}
.mt-auto {
    margin-top: auto !important;
}

.text-lg {
    font-size: 1.125rem !important;
    line-height: 1.75rem !important;
}
.text-xl {
    font-size: 1.25rem !important;
    line-height: 1.75rem !important;
}
.text-2xl {
    font-size: 1.5rem !important;
    line-height: 2rem !important;
}
.text-3xl {
    font-size: 1.875rem !important;
    line-height: 2.25rem !important;
}
.font-semibold {
    font-weight: 600 !important;
}
.font-extrabold {
    font-weight: 800 !important;
}
.uppercase {
    text-transform: uppercase !important;
}
.tracking-wider {
    letter-spacing: 0.05em !important;
}
.tracking-tight {
    letter-spacing: -0.025em !important;
}
.leading-tight {
    line-height: 1.25 !important;
}

.text-left {
    text-align: left !important;
}
.text-center {
    text-align: center !important;
}
.text-right {
    text-align: right !important;
}

.flex-1 {
    flex: 1 1 0% !important;
}
.flex-wrap {
    flex-wrap: wrap !important;
}
.items-start {
    align-items: flex-start !important;
}
.items-end {
    align-items: flex-end !important;
}
.justify-center {
    justify-content: center !important;
}
.justify-end {
    justify-content: flex-end !important;
}

.relative {
    position: relative !important;
}
.absolute {
    position: absolute !important;
}
.sticky {
    position: sticky !important;
}
.top-0 {
    top: 0 !important;
}
.z-10 {
    z-index: 10 !important;
}
.z-50 {
    z-index: 50 !important;
}

.overflow-hidden {
    overflow: hidden !important;
}
.overflow-x-auto {
    overflow-x: auto !important;
}

.transition-all {
    transition: all 0.2s ease !important;
}
.transition-colors {
    transition:
        color 0.2s,
        background-color 0.2s,
        border-color 0.2s !important;
}

.cursor-pointer {
    cursor: pointer !important;
}
.select-none {
    user-select: none !important;
}

.opacity-50 {
    opacity: 0.5 !important;
}
.opacity-60 {
    opacity: 0.6 !important;
}

.w-8 {
    width: 2rem !important;
}
.w-10 {
    width: 2.5rem !important;
}
.w-12 {
    width: 3rem !important;
}
.h-8 {
    height: 2rem !important;
}
.h-10 {
    height: 2.5rem !important;
}
.h-11 {
    height: 2.75rem !important;
}
.h-12 {
    height: 3rem !important;
}
.min-w-70 {
    min-width: 70px !important;
}

.hidden {
    display: none !important;
}

/* Color Extensions */
.bg-success {
    background-color: var(--success) !important;
}
.bg-warning {
    background-color: var(--warning) !important;
}
.bg-secondary {
    background-color: var(--secondary) !important;
}

.bg-blue-50 {
    background-color: #eff6ff !important;
}
.bg-purple-50 {
    background-color: #faf5ff !important;
}
.bg-orange-50 {
    background-color: #fff7ed !important;
}
.bg-green-50 {
    background-color: var(--hcis-green-50) !important;
}
.bg-yellow-50 {
    background-color: var(--hcis-yellow-50) !important;
}
.bg-red-50 {
    background-color: var(--hcis-red-50) !important;
}
.bg-gray-50 {
    background-color: #f9fafb !important;
}
.bg-gray-100 {
    background-color: #f3f4f6 !important;
}

.text-green-600 {
    color: var(--hcis-green-600) !important;
}
.text-green-700 {
    color: var(--hcis-green-700) !important;
}
.text-yellow-700 {
    color: var(--hcis-yellow-700) !important;
}
.text-red-600 {
    color: var(--hcis-red-600) !important;
}
.text-red-700 {
    color: var(--hcis-red-700) !important;
}
.text-purple-600 {
    color: var(--hcis-purple-600) !important;
}
.text-orange-600 {
    color: var(--hcis-orange-600) !important;
}
.text-gray-400 {
    color: #9ca3af !important;
}
.text-gray-500 {
    color: #6b7280 !important;
}

.border-green-200 {
    border-color: var(--hcis-green-200) !important;
}
.border-yellow-200 {
    border-color: var(--hcis-yellow-200) !important;
}
.border-red-200 {
    border-color: var(--hcis-red-200) !important;
}
.border-primary {
    border-color: var(--primary) !important;
}

.ring-1 {
    box-shadow: 0 0 0 1px !important;
}
.ring-primary-light {
    --tw-ring-color: var(--primary-light) !important;
    box-shadow: 0 0 0 2px var(--primary-light) !important;
}

/* =========================================
   STITCH CARD COMPONENTS
   ========================================= */
/* Summary Cards Grid - Responsive */
.summary-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 991px) {
    .summary-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 575px) {
    .summary-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* Date Navigation Responsive */
.date-nav-wrapper {
    display: flex;
    flex-direction: row !important;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.date-nav-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-label {
    font-size: 0.9375rem;
}

.add-shift-btn {
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.3);
}

@media (max-width: 640px) {
    .date-nav-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.75rem !important;
    }

    .date-nav-left {
        width: 100%;
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
    }

    .date-nav-label {
        font-size: 0.8125rem;
    }
}

@media (max-width: 400px) {
    .today-btn {
        display: none !important;
    }

    .date-nav-right {
        grid-template-columns: 1fr;
    }
}

.stitch-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-radius: var(--radius);
    background: var(--surface);
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
}

/* Roster List Container */
.roster-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

@media (max-width: 575px) {
    .roster-list {
        gap: 0.5rem;
    }
}

.stitch-card-icon {
    padding: 0.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stitch-card-icon.blue {
    background: #eff6ff;
    color: var(--primary);
}
.stitch-card-icon.purple {
    background: #faf5ff;
    color: var(--hcis-purple-600);
}
.stitch-card-icon.orange {
    background: #fff7ed;
    color: var(--hcis-orange-600);
}
.stitch-card-icon.green {
    background: var(--hcis-green-50);
    color: var(--success);
}

/* =========================================
   STITCH ROSTER LIST
   ========================================= */
.stitch-roster-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s ease;
}

.stitch-roster-item:hover {
    box-shadow: var(--shadow);
}

.stitch-roster-item.today {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
}

.stitch-roster-item.weekend {
    background: var(--surface-soft);
    opacity: 0.7;
}

/* Roster Content Layout */
.roster-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.shift-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.shift-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.shift-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.shift-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.shift-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-muted);
    font-size: 0.8125rem;
    font-weight: 500;
}

.shift-time,
.shift-location {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.roster-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Mobile Roster Styles */
@media (max-width: 640px) {
    .stitch-roster-item {
        flex-direction: column;
        align-items: stretch;
        padding: 0.75rem;
        gap: 0.75rem;
    }

    .stitch-date-box {
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 0.5rem;
        padding: 0 0 0.5rem 0;
        border-right: none;
        border-bottom: 1px solid var(--border);
        min-width: unset;
    }

    .stitch-date-day {
        font-size: 0.625rem;
    }

    .stitch-date-num {
        font-size: 1.25rem;
    }

    .roster-content {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .shift-info {
        flex: 1;
    }

    .stitch-shift-indicator {
        width: 4px;
        height: 100%;
        min-height: 36px;
    }

    .shift-name {
        font-size: 0.9375rem;
    }

    .shift-meta {
        font-size: 0.75rem;
        gap: 0.75rem;
    }

    .hide-mobile {
        display: none !important;
    }

    .roster-actions {
        justify-content: flex-end;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    .roster-actions .stitch-action-btn {
        width: 32px;
        height: 32px;
    }
}

.stitch-date-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    padding-right: 1rem;
    /* border-right: 1px solid var(--border); */
}

.stitch-date-day {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stitch-date-day.weekend {
    color: var(--danger);
}

.stitch-date-num {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
}

.stitch-shift-indicator {
    width: 6px;
    height: 48px;
    border-radius: 9999px;
}

.stitch-shift-indicator.green {
    background: var(--success);
}
.stitch-shift-indicator.yellow {
    background: var(--warning);
}
.stitch-shift-indicator.blue {
    background: var(--primary);
}
.stitch-shift-indicator.purple {
    background: var(--hcis-purple-600);
}

/* Shift Source Badge */
.shift-source {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    /* padding: 0.125rem 0.5rem; */
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: capitalize;
}

.shift-source.source-permanen {
    background: #dbeafe;
    color: #1e40af;
}

.shift-source.source-sementara {
    background: var(--hcis-orange-100);
    color: #c2410c;
}

/* Stitch Action Buttons */
.stitch-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stitch-action-btn:hover {
    border-color: var(--primary-light);
    background: #eff6ff;
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stitch-action-btn.danger:hover {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
    color: var(--danger);
}

.stitch-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stitch-badge.approved {
    background: var(--hcis-green-100);
    color: #166534;
}

.stitch-badge.pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.stitch-badge.rejected {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

/* =========================================
   STITCH FILTER & TABLE
   ========================================= */
.stitch-filter {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
}

.stitch-filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.375rem;
}

.stitch-input {
    width: 100%;
    height: 2.75rem;
    padding: 0 0.75rem;
    background: var(--surface-soft);
    border: 1px solid transparent;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: var(--text);
    transition: all 0.2s;
}

.stitch-input:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.stitch-input::placeholder {
    color: var(--text-muted);
}

.stitch-table {
    width: 100%;
    text-align: left;
    border-collapse: collapse;
}

.stitch-table thead tr {
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.stitch-table th {
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stitch-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s;
}

.stitch-table tbody tr:hover {
    background: var(--surface-soft);
}

.stitch-table td {
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: var(--text);
}

/* =========================================
   ELEMENT PLUS DATE PICKER CUSTOMIZATION
   ========================================= */
.date-picker-wrapper {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
}

.el-date-picker-full {
    width: 100% !important;
}

.el-date-picker-full .el-input__wrapper {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-date-picker-full .el-input__wrapper:hover,
.el-date-picker-full .el-input__wrapper.is-focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-date-picker-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-date-picker-full .el-input__prefix,
.el-date-picker-full .el-input__suffix {
    color: var(--text-muted);
}

/* Popper/Dropdown Styles */
.el-picker__popper {
    border-radius: var(--radius) !important;
    border: 1px solid var(--border) !important;
    box-shadow: var(--shadow-lg) !important;
}

.el-picker-panel {
    border: none !important;
}

.el-date-picker__header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border);
}

.el-date-picker__header-label {
    font-weight: 600;
    color: var(--text);
}

.el-picker-panel__icon-btn {
    color: var(--text-muted);
}

.el-picker-panel__icon-btn:hover {
    color: var(--primary);
}

.el-date-table th {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
}

.el-date-table td.available:hover {
    color: var(--primary);
}

.el-date-table td.today .el-date-table-cell__text {
    color: var(--primary);
    font-weight: 700;
}

.el-date-table td.current:not(.disabled) .el-date-table-cell__text,
.el-date-table td.selected .el-date-table-cell__text {
    background-color: var(--primary) !important;
    color: white !important;
    border-radius: 50%;
}

.el-date-table td.disabled .el-date-table-cell__text {
    color: var(--text-muted);
    opacity: 0.4;
}

/* =========================================
   ELEMENT PLUS SELECT CUSTOMIZATION
   ========================================= */
.el-select-full {
    width: 100% !important;
}

.el-select-full .el-input__wrapper {
    padding: 0.5rem 0.875rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-select-full .el-input__wrapper:hover,
.el-select-full .el-input.is-focus .el-input__wrapper {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-select-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-select-full .el-input__suffix {
    color: var(--text-muted);
}

/* High z-index for select popper (teleported to body) */
.el-select-popper-high {
    z-index: 99999 !important;
}

.el-select-popper-high .el-select-dropdown__item {
    font-size: 0.875rem;
    padding: 0.625rem 1rem;
}

.el-select-popper-high .el-select-dropdown__item.selected {
    color: var(--primary);
    font-weight: 600;
}

.el-select-popper-high .el-select-dropdown__item:hover {
    background: var(--surface-soft);
}

/* High z-index for date picker popper (teleported to body) */
.el-date-picker-popper-high {
    z-index: 99999 !important;
}

/* =========================================
   STITCH WIZARD STEPPER
   ========================================= */
.stitch-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    max-width: 600px;
    margin: 0 auto 2rem;
}

.stitch-stepper::before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--border);
    transform: translateY(-50%);
    z-index: 0;
}

.stitch-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.stitch-step-circle {
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    background: var(--surface);
    border: 2px solid var(--border);
    color: var(--text-muted);
    box-shadow: 0 0 0 4px var(--surface);
}

.stitch-step.completed .stitch-step-circle {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.stitch-step.active .stitch-step-circle {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow:
        0 0 0 4px var(--surface),
        0 0 0 6px rgba(99, 102, 241, 0.2);
}

.stitch-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
}

.stitch-step.completed .stitch-step-label {
    color: var(--success);
}

.stitch-step.active .stitch-step-label {
    color: var(--primary);
    font-weight: 700;
}

/* =========================================
   STITCH TYPE CARDS (Wizard Step 1)
   ========================================= */
.stitch-type-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
}

.stitch-type-card {
    padding: 2rem 1.5rem;
    border-radius: 1rem;
    border: 2px solid var(--border);
    background: var(--surface);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stitch-type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stitch-type-card.selected {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-indigo-50), var(--surface));
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.stitch-type-icon {
    width: 5rem;
    height: 5rem;
    border-radius: 9999px;
    background: var(--surface-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    transition: all 0.2s;
}

.stitch-type-card.selected .stitch-type-icon {
    background: var(--primary);
    color: white;
}

.stitch-type-card .stitch-type-icon i {
    font-size: 2.5rem;
}

/* =========================================
   STITCH DATE CHIPS (Wizard Step 2)
   ========================================= */
/* Dates Summary Section */
.dates-summary-section {
    margin-top: 1rem;
}

.dates-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    gap: 0.5rem;
}

.dates-count {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text);
}

.clear-dates-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--danger);
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-dates-btn:hover {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
}

.clear-dates-btn i {
    font-size: 0.625rem;
}

.stitch-date-chips {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    padding: 0.25rem;
}

.stitch-date-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.5rem;
    background: var(--surface);
    border: 1px solid var(--primary-light);
    border-radius: 0.375rem;
    color: var(--primary);
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    white-space: nowrap;
    overflow: hidden;
}

.stitch-date-chip i {
    font-size: 0.625rem;
    flex-shrink: 0;
}

.stitch-date-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.stitch-date-chip button {
    background: transparent;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.125rem;
    border-radius: 9999px;
    transition: all 0.2s;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.stitch-date-chip button:hover {
    background: var(--hcis-red-100);
    color: var(--danger);
}

/* Mobile: Single column for date chips */
@media (max-width: 480px) {
    .stitch-date-chips {
        grid-template-columns: 1fr;
        max-height: 180px;
    }

    .stitch-type-card .stitch-type-icon i {
        font-size: 1.5rem;
    }


    .stitch-date-chip {
        font-size: 0.6875rem;
        padding: 0.3rem 0.4rem;
    }
}

/* =========================================
   STITCH MODAL ENHANCEMENTS
   ========================================= */
.stitch-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stitch-modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text);
}

.stitch-modal-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.stitch-modal-footer {
    padding: 1rem 1.5rem;
    background: var(--surface-soft);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

.stitch-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.stitch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    font-weight: 700;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.stitch-btn.primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
}

.stitch-btn.primary:hover {
    background: var(--primary-dark);
}

.stitch-btn.secondary {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.stitch-btn.secondary:hover {
    background: var(--surface-soft);
    color: var(--text);
}

.stitch-btn.danger {
    background: var(--danger);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
}

.stitch-btn.danger:hover {
    background: var(--danger-dark);
    color: black;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    transition: all 0.2s;
    /* border: 0.1px solid var(--text-muted); */
}

/* =========================================
   RESPONSIVE OVERRIDES - TABLET (768px+)
   ========================================= */
@media (min-width: 768px) {
    .md\:grid-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .md\:grid-cols-12 {
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
    }
    .md\:col-span-1 {
        grid-column: span 1 / span 1 !important;
    }
    .md\:col-span-2 {
        grid-column: span 2 / span 2 !important;
    }
    .md\:col-span-5 {
        grid-column: span 5 / span 5 !important;
    }
    .md\:flex {
        display: flex !important;
    }
}

/* =========================================
   RESPONSIVE OVERRIDES - MOBILE (< 768px)
   ========================================= */
@media (max-width: 767px) {
    /* Tab Content Padding */
    .p-6 {
        padding: 1rem !important;
    }

    /* Summary Cards - Single Column */
    .grid.grid-cols-3 {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .stitch-card {
        padding: 1rem;
        gap: 0.25rem;
    }

    .stitch-card .text-2xl {
        font-size: 1.5rem !important;
    }

    .stitch-card-icon {
        width: 36px;
        height: 36px;
    }

    /* Date Navigation - Stack on Mobile */
    .stitch-card.sticky {
        flex-direction: row !important;
        gap: 0.75rem !important;
        padding: 0.75rem !important;
    }

    .stitch-card.sticky > div:first-child {
        flex-wrap: wrap;
        width: 100%;
        justify-content: space-between;
    }

    .stitch-card.sticky .text-lg {
        font-size: 0.875rem !important;
    }

    .stitch-card.sticky .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Hide secondary button on mobile */
    .stitch-btn.secondary.hidden.sm\:block {
        display: none !important;
    }

    /* Action Buttons - Smaller */
    .stitch-action-btn {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.875rem !important;
    }

    /* Roster Items - Vertical Layout */
    .stitch-roster-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.875rem;
    }

    .stitch-date-box {
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 0.75rem;
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding: 0 0 0.75rem 0;
        min-width: unset;
    }

    .stitch-date-day {
        font-size: 0.65rem;
    }

    .stitch-date-num {
        font-size: 1.25rem !important;
    }

    .stitch-roster-item .flex.items-center.gap-3:not(.stitch-date-box) {
        flex: 1;
    }

    .stitch-roster-item .flex.items-center.gap-2:last-child {
        justify-content: flex-end;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    .stitch-shift-indicator {
        width: 4px;
        height: 32px;
    }

    .stitch-roster-item .text-lg {
        font-size: 0.9375rem !important;
    }

    .stitch-roster-item .text-sm {
        font-size: 0.75rem !important;
    }

    /* Filter Section - Stack on Mobile */
    .stitch-filter {
        padding: 0.875rem !important;
    }

    .stitch-filter .grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.75rem !important;
    }

    .stitch-filter input,
    .stitch-filter select {
        width: 100% !important;
    }

    /* Table - Card-like rows on mobile */
    .stitch-table {
        display: block;
    }

    .stitch-table thead {
        display: none;
    }

    .stitch-table tbody {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .stitch-table tr {
        display: flex;
        flex-direction: column;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.75rem;
    }

    .stitch-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.25rem 0;
        border: none;
    }

    .stitch-table td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .stitch-table td.text-right {
        justify-content: flex-end;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    /* Wizard Steps - Numbers only on mobile */
    .stitch-stepper {
        gap: 0.25rem;
    }

    .stitch-step-label {
        display: none;
    }

    .stitch-step-circle {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }

    .stitch-stepper::before {
        top: 16px;
    }

    /* Type Selection - Single Column */
    .stitch-type-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .stitch-type-card {
        padding: 1rem;
    }

    .stitch-type-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }

    /* Date Chips - Responsive */
    .stitch-date-chips {
        gap: 0.5rem;
    }

    .stitch-date-chip {
        padding: 0.375rem 0.625rem;
        font-size: 0.75rem;
    }

    /* Modal Footer - Stack buttons on mobile */
    .stitch-modal-footer {
        flex-direction: column-reverse;
        gap: 0.5rem;
    }

    .stitch-modal-footer .stitch-btn {
        width: 100%;
        justify-content: center;
        margin: 0 !important;
    }

    /* History Page Header */
    .flex.flex-wrap.justify-between.items-end.gap-4.mb-6 {
        flex-direction: column;
        align-items: stretch !important;
    }

    .flex.flex-wrap.justify-between.items-end.gap-4.mb-6 .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Text sizes on mobile */
    .text-2xl {
        font-size: 1.25rem !important;
    }

    .text-xl {
        font-size: 1rem !important;
    }

    .text-lg {
        font-size: 0.9375rem !important;
    }

    /* Gaps on mobile */
    .gap-4 {
        gap: 0.75rem !important;
    }
    .gap-6 {
        gap: 1rem !important;
    }
    .mb-6 {
        margin-bottom: 1rem !important;
    }
    .mb-4 {
        margin-bottom: 0.75rem !important;
    }
}

/* =========================================
   RESPONSIVE OVERRIDES - SMALL MOBILE (< 480px)
   ========================================= */
@media (max-width: 479px) {
    .stitch-card {
        padding: 0.75rem;
        flex-direction: column;
    }

    .stitch-roster-item {
        padding: 0.75rem;
    }

    .stitch-date-num {
        font-size: 1.125rem !important;
    }

    .stitch-badge {
        font-size: 0.5rem;
        padding: 0.1rem 0.375rem;
    }

    .stitch-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
    }

    .stitch-action-btn {
        width: 28px !important;
        height: 28px !important;
    }
}

/* =========================================
   DETAIL MODAL - TWO COLUMN LAYOUT
   ========================================= */
.detail-modal-content {
    padding: 0;
}

.detail-modal-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    min-height: 420px;
}

.detail-left-column {
    background: var(--surface-soft);
    padding: 1.25rem;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.detail-right-column {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow-y: auto;
    max-height: 500px;
}

/* Transaction Card - No Transaksi Highlight */
.detail-transaksi-card {
    text-align: center;
    padding: 1.25rem;
    background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
}

.detail-transaksi-number {
    font-size: 1.25rem;
    font-weight: 800;
    color: white;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}

.detail-transaksi-type {
    margin-top: 0.5rem;
}

.type-badge {
    display: inline-block;
    padding: 0.375rem 0.875rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    backdrop-filter: blur(4px);
}

/* Status Section */
.detail-status-section {
    text-align: center;
}

.detail-status-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}

.detail-status-badge {
    display: inline-block;
    padding: 0.625rem 2rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-status-badge.approved {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.detail-status-badge.pending {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.detail-status-badge.rejected {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.detail-status-date {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

.detail-status-date i {
    font-size: 0.875rem;
}

/* Approval Section */
.detail-approval-section {
    background: white;
    border-radius: var(--radius);
    padding: 1rem;
    border: 1px solid var(--border);
}

.detail-section-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
    display: inline-block;
}

.detail-section-title i {
    color: var(--primary);
    margin-right: 0.25rem;
}

.detail-approval-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-approval-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.detail-approval-indicator {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.detail-approval-indicator.approved {
    background: var(--success);
    color: white;
}

.detail-approval-indicator.rejected {
    background: var(--danger);
    color: white;
}

.detail-approval-indicator.pending {
    background: var(--border);
    color: var(--text-muted);
}

.detail-approval-info {
    flex: 1;
    min-width: 0;
}

.detail-approval-name {
    font-size: 0.875rem;
    font-weight: 600;
}

.detail-approval-name.text-success {
    color: var(--success);
}

.detail-approval-name.text-danger {
    color: var(--danger);
}

.detail-approval-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.detail-approval-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--success);
}

/* Right Column Info Section */
.detail-info-section {
    background: white;
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.detail-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.detail-info-row:first-child {
    padding-top: 0;
}

.detail-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.detail-info-label {
    font-size: 0.8125rem;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 1rem;
}

.detail-info-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--dark);
    text-align: right;
}

.detail-shift-date {
    font-weight: 700;
    color: var(--dark);
    font-size: 0.9375rem;
}

.detail-shift-name {
    color: var(--primary);
    font-size: 0.8125rem;
    font-weight: 500;
}

.detail-shift-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    margin-top: 0.25rem;
}

.detail-alasan {
    font-style: italic;
    max-width: 220px;
    word-wrap: break-word;
    color: var(--text);
}

/* Detail Shift Card - Per-date card styling */
.detail-shift-card {
    background: white;
    border-radius: var(--radius);
    padding: 0.875rem 1rem;
    border: 1px solid var(--border);
    border-left-width: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.detail-shift-card:hover {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.detail-shift-card.border-success {
    border-left-color: var(--success);
}

.detail-shift-card.border-danger {
    border-left-color: var(--danger);
}

.detail-shift-card.border-warning {
    border-left-color: var(--warning);
}

/* Status Text Colors */
.status-text-approved {
    color: var(--success);
    font-weight: 600;
}

.status-text-pending {
    color: var(--warning);
    font-weight: 600;
}

.status-text-rejected {
    color: var(--danger);
    font-weight: 600;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .detail-modal-grid {
        grid-template-columns: 1fr;
    }

    .detail-left-column {
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .detail-right-column {
        max-height: none;
    }

    .detail-info-row {
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-info-value {
        text-align: left;
    }

    .detail-shift-time {
        justify-content: flex-start;
    }

    .detail-alasan {
        max-width: 100%;
    }
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Fade In Animation */
.fade-in {
    animation: fadeIn 0.3s ease-in-out;
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
