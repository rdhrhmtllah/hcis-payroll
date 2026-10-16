<template>
    <div class="container-fluid px-0 fade-in">
        <!-- Header -->
        <HeaderKpi
            title="Kelola Alur Persetujuan KPI"
            subtitle="Atur dan kelola alur persetujuan KPI untuk karyawan dan tim"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Main Content -->
        <div class="row justify-content-center mt-4">
            <div class="col-lg-11">
                <!-- Tab Navigation -->
                <TabToggle
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab 1: Individual Approval Flow -->
                    <template #individual>
                        <div class="tab-content-wrapper">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="feature-card">
                                        <div class="feature-header">
                                            <div class="feature-icon">
                                                <i
                                                    class="bi bi-person-gear"
                                                ></i>
                                            </div>
                                            <div class="feature-info">
                                                <h5>
                                                    Atur Alur Persetujuan
                                                    Individual
                                                </h5>
                                                <p>
                                                    Kelola alur persetujuan
                                                    untuk setiap karyawan secara
                                                    spesifik
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Employee Selection -->
                                        <div class="form-section">
                                            <label class="form-label">
                                                <i
                                                    class="bi bi-person-check me-2"
                                                ></i>
                                                Pilih Karyawan
                                            </label>
                                            <el-select
                                                v-model="selectedRequester"
                                                filterable
                                                remote
                                                :remote-method="searchRequester"
                                                reserve-keyword
                                                placeholder="Ketik nama atau kode karyawan..."
                                                :loading="loading.requester"
                                                class="w-100 modern-select"
                                                @change="handleRequesterChange"
                                                value-key="hashed_id"
                                            >
                                                <el-option
                                                    v-for="item in requesterOptions"
                                                    :key="item.hashed_id"
                                                    :label="item.Nama"
                                                    :value="item"
                                                >
                                                    <div
                                                        class="employee-option"
                                                    >
                                                        <span
                                                            class="employee-option-name"
                                                            >{{
                                                                item.Nama
                                                            }}</span
                                                        >
                                                        <span
                                                            class="employee-option-code"
                                                        >
                                                            {{
                                                                item.Kode_Karyawan
                                                            }}
                                                            •
                                                            {{
                                                                item.nama_jabatan
                                                            }}
                                                        </span>
                                                    </div>
                                                </el-option>
                                            </el-select>
                                        </div>

                                        <!-- Current Approval Flow -->
                                        <div
                                            class="flow-section"
                                            v-if="selectedRequester"
                                        >
                                            <div class="section-header">
                                                <h6>
                                                    Alur Persetujuan Saat Ini
                                                </h6>
                                                <span class="employee-badge">
                                                    {{ selectedRequester.Nama }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="loading.flow"
                                                class="loading-state"
                                            >
                                                <div
                                                    class="spinner-border text-primary"
                                                    role="status"
                                                ></div>
                                                <span
                                                    >Memuat alur
                                                    persetujuan...</span
                                                >
                                            </div>

                                            <div v-else>
                                                <div
                                                    v-if="
                                                        approverList.length > 0
                                                    "
                                                    class="approval-flow"
                                                >
                                                    <draggable
                                                        v-model="approverList"
                                                        item-key="hashed_id"
                                                        handle=".drag-handle"
                                                        ghost-class="ghost"
                                                        @change="onFlowChange"
                                                    >
                                                        <template
                                                            #item="{
                                                                element,
                                                                index,
                                                            }"
                                                        >
                                                            <div
                                                                class="approval-step"
                                                            >
                                                                <div
                                                                    class="step-header"
                                                                >
                                                                    <div
                                                                        class="step-number"
                                                                    >
                                                                        {{
                                                                            index +
                                                                            1
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="step-info"
                                                                    >
                                                                        <h6>
                                                                            {{
                                                                                element.Nama_Approver
                                                                            }}
                                                                        </h6>
                                                                        <p>
                                                                            {{
                                                                                element.Jabatan_Approver
                                                                            }}
                                                                            •
                                                                            {{
                                                                                element.Divisi_Approver
                                                                            }}
                                                                        </p>
                                                                    </div>
                                                                    <div
                                                                        class="step-actions"
                                                                    >
                                                                        <i
                                                                            class="bi bi-grip-vertical drag-handle"
                                                                        ></i>
                                                                        <button
                                                                            class="btn-icon btn-danger"
                                                                            @click="
                                                                                removeApprover(
                                                                                    index
                                                                                )
                                                                            "
                                                                        >
                                                                            <i
                                                                                class="bi bi-trash"
                                                                            ></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </draggable>
                                                </div>

                                                <div v-else class="empty-flow">
                                                    <i
                                                        class="bi bi-diagram-3"
                                                    ></i>
                                                    <p>
                                                        Belum ada alur
                                                        persetujuan
                                                    </p>
                                                    <small
                                                        >Tambah approver untuk
                                                        memulai</small
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <!-- Add Approver Panel -->
                                    <div class="side-panel">
                                        <div class="panel-header">
                                            <i class="bi bi-person-plus"></i>
                                            <h6>Tambah Approver</h6>
                                        </div>
                                        <div class="panel-content">
                                            <el-select
                                                v-model="newApprover"
                                                filterable
                                                remote
                                                :remote-method="searchApprover"
                                                reserve-keyword
                                                placeholder="Cari approver..."
                                                :loading="loading.approver"
                                                class="w-100 modern-select"
                                                @change="addApprover"
                                                value-key="hashed_id"
                                            >
                                                <el-option
                                                    v-for="item in approverOptions"
                                                    :key="item.hashed_id"
                                                    :label="item.Nama"
                                                    :value="item"
                                                >
                                                    <div
                                                        class="employee-option"
                                                    >
                                                        <span
                                                            class="employee-option-name"
                                                            >{{
                                                                item.Nama
                                                            }}</span
                                                        >
                                                        <span
                                                            class="employee-option-code"
                                                        >
                                                            {{
                                                                item.Kode_Karyawan
                                                            }}
                                                            •
                                                            {{
                                                                item.nama_jabatan
                                                            }}
                                                        </span>
                                                    </div>
                                                </el-option>
                                            </el-select>

                                            <div class="quick-actions mt-3">
                                                <h6>Quick Actions</h6>
                                                <div class="action-buttons">
                                                    <button
                                                        class="btn-action btn-outline"
                                                        @click="
                                                            addDirectManager
                                                        "
                                                        :disabled="
                                                            !selectedRequester
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-person-badge"
                                                        ></i>
                                                        Atasan Langsung
                                                    </button>
                                                    <button
                                                        class="btn-action btn-outline"
                                                        @click="
                                                            addDepartmentHead
                                                        "
                                                        :disabled="
                                                            !selectedRequester
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-person-vcard"
                                                        ></i>
                                                        Kepala Dept
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Save Action -->
                                    <div
                                        class="action-card"
                                        v-if="selectedRequester"
                                    >
                                        <div class="action-content">
                                            <div class="action-info">
                                                <i
                                                    class="bi bi-info-circle"
                                                ></i>
                                                <div>
                                                    <strong
                                                        >Ready to Save</strong
                                                    >
                                                    <small
                                                        >{{
                                                            approverList.length
                                                        }}
                                                        approver dalam
                                                        alur</small
                                                    >
                                                </div>
                                            </div>
                                            <button
                                                class="btn-primary btn-block"
                                                @click="saveIndividualFlow"
                                                :disabled="
                                                    !canSaveIndividual ||
                                                    loading.saving
                                                "
                                            >
                                                <i
                                                    v-if="!loading.saving"
                                                    class="bi bi-check-circle"
                                                ></i>
                                                <div
                                                    v-else
                                                    class="spinner-border spinner-border-sm"
                                                ></div>
                                                Simpan Alur Persetujuan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 2: Bulk Approval Management -->
                    <template #bulk>
                        <div class="tab-content-wrapper">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="feature-card">
                                        <div class="feature-header">
                                            <div class="feature-icon bulk">
                                                <i
                                                    class="bi bi-people-fill"
                                                ></i>
                                            </div>
                                            <div class="feature-info">
                                                <h5>
                                                    Kelola Alur Persetujuan
                                                    Massal
                                                </h5>
                                                <p>
                                                    Atur alur persetujuan untuk
                                                    banyak karyawan sekaligus
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Employee Selection for Bulk -->
                                        <div class="form-section">
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label class="form-label">
                                                        <i
                                                            class="bi bi-people me-2"
                                                        ></i>
                                                        Pilih Karyawan
                                                    </label>
                                                    <el-select
                                                        v-model="
                                                            selectedEmployees
                                                        "
                                                        filterable
                                                        remote
                                                        multiple
                                                        collapse-tags
                                                        :remote-method="
                                                            searchRequester
                                                        "
                                                        reserve-keyword
                                                        placeholder="Pilih karyawan untuk alur massal..."
                                                        :loading="
                                                            loading.requester
                                                        "
                                                        class="w-100 modern-select"
                                                        value-key="hashed_id"
                                                    >
                                                        <el-option
                                                            v-for="item in requesterOptions"
                                                            :key="
                                                                item.hashed_id
                                                            "
                                                            :label="item.Nama"
                                                            :value="item"
                                                        >
                                                            <div
                                                                class="employee-option"
                                                            >
                                                                <span
                                                                    class="employee-option-name"
                                                                    >{{
                                                                        item.Nama
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="employee-option-code"
                                                                >
                                                                    {{
                                                                        item.Kode_Karyawan
                                                                    }}
                                                                    •
                                                                    {{
                                                                        item.nama_jabatan
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </el-option>
                                                    </el-select>
                                                </div>
                                            </div>

                                            <!-- Selected Employees Preview -->
                                            <div
                                                v-if="
                                                    selectedEmployees.length > 0
                                                "
                                                class="selected-preview"
                                            >
                                                <div class="preview-header">
                                                    <span
                                                        >{{
                                                            selectedEmployees.length
                                                        }}
                                                        karyawan terpilih</span
                                                    >
                                                    <button
                                                        class="btn-clear"
                                                        @click="
                                                            clearBulkSelection
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-x-circle"
                                                        ></i>
                                                        Bersihkan
                                                    </button>
                                                </div>
                                                <div class="employees-grid">
                                                    <div
                                                        v-for="employee in selectedEmployees"
                                                        :key="
                                                            employee.hashed_id
                                                        "
                                                        class="employee-card"
                                                    >
                                                        <div
                                                            class="employee-avatar"
                                                        >
                                                            {{
                                                                getInitials(
                                                                    employee.Nama
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="employee-details"
                                                        >
                                                            <strong>{{
                                                                employee.Nama
                                                            }}</strong>
                                                            <small>{{
                                                                employee.nama_jabatan
                                                            }}</small>
                                                        </div>
                                                        <button
                                                            class="btn-remove"
                                                            @click="
                                                                removeFromBulkSelection(
                                                                    employee
                                                                )
                                                            "
                                                        >
                                                            <i
                                                                class="bi bi-x"
                                                            ></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bulk Approver Setup -->
                                        <div
                                            class="form-section"
                                            v-if="selectedEmployees.length > 0"
                                        >
                                            <label class="form-label">
                                                <i
                                                    class="bi bi-diagram-3 me-2"
                                                ></i>
                                                Atur Alur Approver untuk Semua
                                            </label>
                                            <div class="bulk-flow-setup">
                                                <div class="flow-controls">
                                                    <el-select
                                                        v-model="bulkApprover"
                                                        filterable
                                                        remote
                                                        :remote-method="
                                                            searchApprover
                                                        "
                                                        reserve-keyword
                                                        placeholder="Pilih approver untuk ditambahkan..."
                                                        class="modern-select"
                                                    >
                                                        <el-option
                                                            v-for="item in approverOptions"
                                                            :key="
                                                                item.hashed_id
                                                            "
                                                            :label="item.Nama"
                                                            :value="item"
                                                        >
                                                            <div
                                                                class="employee-option"
                                                            >
                                                                <span
                                                                    class="employee-option-name"
                                                                    >{{
                                                                        item.Nama
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="employee-option-code"
                                                                >
                                                                    {{
                                                                        item.Kode_Karyawan
                                                                    }}
                                                                    •
                                                                    {{
                                                                        item.nama_jabatan
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </el-option>
                                                    </el-select>
                                                    <button
                                                        class="btn-primary"
                                                        @click="addBulkApprover"
                                                        :disabled="
                                                            !bulkApprover
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-plus-circle"
                                                        ></i>
                                                        Tambah
                                                    </button>
                                                </div>

                                                <!-- Bulk Approver List -->
                                                <div
                                                    v-if="
                                                        bulkApproverList.length >
                                                        0
                                                    "
                                                    class="bulk-approver-list"
                                                >
                                                    <div class="list-header">
                                                        <span
                                                            >Urutan
                                                            Approver</span
                                                        >
                                                        <small
                                                            >Drag untuk
                                                            mengurutkan</small
                                                        >
                                                    </div>
                                                    <draggable
                                                        v-model="
                                                            bulkApproverList
                                                        "
                                                        item-key="hashed_id"
                                                        handle=".drag-handle"
                                                        ghost-class="ghost"
                                                    >
                                                        <template
                                                            #item="{
                                                                element,
                                                                index,
                                                            }"
                                                        >
                                                            <div
                                                                class="approver-item"
                                                            >
                                                                <div
                                                                    class="approver-info"
                                                                >
                                                                    <i
                                                                        class="bi bi-grip-vertical drag-handle"
                                                                    ></i>
                                                                    <span
                                                                        class="approver-order"
                                                                        >{{
                                                                            index +
                                                                            1
                                                                        }}</span
                                                                    >
                                                                    <div
                                                                        class="avatar-sm"
                                                                    >
                                                                        {{
                                                                            getInitials(
                                                                                element.Nama_Approver
                                                                            )
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="approver-details"
                                                                    >
                                                                        <h6>
                                                                            {{
                                                                                element.Nama_Approver
                                                                            }}
                                                                        </h6>
                                                                        <p>
                                                                            {{
                                                                                element.Jabatan_Approver
                                                                            }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <button
                                                                    class="btn-icon btn-danger"
                                                                    @click="
                                                                        removeBulkApprover(
                                                                            index
                                                                        )
                                                                    "
                                                                >
                                                                    <i
                                                                        class="bi bi-trash"
                                                                    ></i>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </draggable>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bulk Action Footer -->
                            <div
                                class="action-footer"
                                v-if="selectedEmployees.length > 0"
                            >
                                <div class="action-summary">
                                    <i class="bi bi-info-circle"></i>
                                    <div>
                                        <strong>Aksi Massal</strong>
                                        <small
                                            >Akan diterapkan ke
                                            {{
                                                selectedEmployees.length
                                            }}
                                            karyawan</small
                                        >
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <button
                                        class="btn-secondary"
                                        @click="clearBulkSelection"
                                    >
                                        <i class="bi bi-x-circle"></i> Batal
                                    </button>
                                    <button
                                        class="btn-primary"
                                        @click="saveBulkFlow"
                                        :disabled="
                                            !canSaveBulk || loading.saving
                                        "
                                    >
                                        <i
                                            v-if="!loading.saving"
                                            class="bi bi-check-all"
                                        ></i>
                                        <div
                                            v-else
                                            class="spinner-border spinner-border-sm"
                                        ></div>
                                        Simpan untuk Semua
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 3: Team Transfer -->
                    <template #transfer>
                        <div class="tab-content-wrapper">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="feature-card">
                                        <div class="feature-header">
                                            <div class="feature-icon transfer">
                                                <i
                                                    class="bi bi-arrow-left-right"
                                                ></i>
                                            </div>
                                            <div class="feature-info">
                                                <h5>
                                                    Transfer Alur Persetujuan
                                                    Tim
                                                </h5>
                                                <p>
                                                    Pindahkan alur persetujuan
                                                    dari satu leader ke leader
                                                    lainnya
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Transfer Configuration -->
                                        <div class="transfer-configuration">
                                            <div class="config-row">
                                                <div class="config-group">
                                                    <label class="form-label">
                                                        <i
                                                            class="bi bi-person-down me-2"
                                                        ></i>
                                                        Leader Asal
                                                    </label>
                                                    <el-select
                                                        v-model="sourceLeader"
                                                        filterable
                                                        remote
                                                        :remote-method="
                                                            searchLeader
                                                        "
                                                        reserve-keyword
                                                        placeholder="Pilih leader asal..."
                                                        class="w-100 modern-select"
                                                        value-key="hashed_id"
                                                        @change="
                                                            loadTeamMembers
                                                        "
                                                    >
                                                        <el-option
                                                            v-for="item in leaderOptions"
                                                            :key="
                                                                item.hashed_id
                                                            "
                                                            :label="item.Nama"
                                                            :value="item"
                                                        >
                                                            <div
                                                                class="employee-option"
                                                            >
                                                                <span
                                                                    class="employee-option-name"
                                                                    >{{
                                                                        item.Nama
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="employee-option-code"
                                                                >
                                                                    {{
                                                                        item.Kode_Karyawan
                                                                    }}
                                                                    •
                                                                    {{
                                                                        item.nama_jabatan
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </el-option>
                                                    </el-select>
                                                </div>

                                                <div class="config-group">
                                                    <label class="form-label">
                                                        <i
                                                            class="bi bi-person-up me-2"
                                                        ></i>
                                                        Leader Tujuan
                                                    </label>
                                                    <el-select
                                                        v-model="targetLeader"
                                                        filterable
                                                        remote
                                                        :remote-method="
                                                            searchLeader
                                                        "
                                                        reserve-keyword
                                                        placeholder="Pilih leader tujuan..."
                                                        class="w-100 modern-select"
                                                        value-key="hashed_id"
                                                    >
                                                        <el-option
                                                            v-for="item in leaderOptions"
                                                            :key="
                                                                item.hashed_id
                                                            "
                                                            :label="item.Nama"
                                                            :value="item"
                                                        >
                                                            <div
                                                                class="employee-option"
                                                            >
                                                                <span
                                                                    class="employee-option-name"
                                                                    >{{
                                                                        item.Nama
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="employee-option-code"
                                                                >
                                                                    {{
                                                                        item.Kode_Karyawan
                                                                    }}
                                                                    •
                                                                    {{
                                                                        item.nama_jabatan
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </el-option>
                                                    </el-select>
                                                </div>
                                            </div>

                                            <!-- Team Members Preview -->
                                            <div
                                                v-if="teamMembers.length > 0"
                                                class="team-preview"
                                            >
                                                <div class="preview-header">
                                                    <span
                                                        >Anggota Tim
                                                        {{
                                                            sourceLeader.Nama
                                                        }}</span
                                                    >
                                                    <small
                                                        >{{
                                                            teamMembers.length
                                                        }}
                                                        anggota</small
                                                    >
                                                </div>
                                                <div class="members-list">
                                                    <div
                                                        v-for="member in teamMembers"
                                                        :key="member.hashed_id"
                                                        class="member-item"
                                                    >
                                                        <div
                                                            class="member-avatar"
                                                        >
                                                            {{
                                                                getInitials(
                                                                    member.Nama
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="member-details"
                                                        >
                                                            <strong>{{
                                                                member.Nama
                                                            }}</strong>
                                                            <small>{{
                                                                member.nama_jabatan
                                                            }}</small>
                                                        </div>
                                                        <div
                                                            class="member-status"
                                                        >
                                                            <i
                                                                class="bi bi-check-circle-fill text-success"
                                                            ></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <!-- Transfer Preview -->
                                    <div
                                        class="transfer-preview"
                                        v-if="sourceLeader && targetLeader"
                                    >
                                        <div class="preview-card">
                                            <div class="preview-header">
                                                <h6>Preview Transfer</h6>
                                            </div>
                                            <div class="preview-content">
                                                <div class="transfer-flow">
                                                    <div
                                                        class="flow-item source"
                                                    >
                                                        <div
                                                            class="flow-avatar"
                                                        >
                                                            {{
                                                                getInitials(
                                                                    sourceLeader.Nama
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="flow-details"
                                                        >
                                                            <strong>{{
                                                                sourceLeader.Nama
                                                            }}</strong>
                                                            <small
                                                                >Leader
                                                                Asal</small
                                                            >
                                                        </div>
                                                        <i
                                                            class="bi bi-arrow-right transfer-arrow"
                                                        ></i>
                                                    </div>

                                                    <div
                                                        class="flow-item target"
                                                    >
                                                        <div
                                                            class="flow-avatar"
                                                        >
                                                            {{
                                                                getInitials(
                                                                    targetLeader.Nama
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="flow-details"
                                                        >
                                                            <strong>{{
                                                                targetLeader.Nama
                                                            }}</strong>
                                                            <small
                                                                >Leader
                                                                Tujuan</small
                                                            >
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="transfer-stats">
                                                    <div class="stat-item">
                                                        <i
                                                            class="bi bi-people"
                                                        ></i>
                                                        <div>
                                                            <strong>{{
                                                                teamMembers.length
                                                            }}</strong>
                                                            <small
                                                                >Anggota
                                                                Tim</small
                                                            >
                                                        </div>
                                                    </div>
                                                    <div class="stat-item">
                                                        <i
                                                            class="bi bi-diagram-3"
                                                        ></i>
                                                        <div>
                                                            <strong>{{
                                                                getApprovalFlowCount()
                                                            }}</strong>
                                                            <small
                                                                >Alur
                                                                Persetujuan</small
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Transfer Action -->
                                        <div class="transfer-action">
                                            <button
                                                class="btn-primary btn-block"
                                                @click="executeTransfer"
                                                :disabled="
                                                    !canTransfer ||
                                                    loading.transfer
                                                "
                                            >
                                                <i
                                                    v-if="!loading.transfer"
                                                    class="bi bi-arrow-left-right"
                                                ></i>
                                                <div
                                                    v-else
                                                    class="spinner-border spinner-border-sm"
                                                ></div>
                                                Eksekusi Transfer
                                            </button>
                                            <small class="action-note">
                                                Semua alur persetujuan akan
                                                dipindahkan ke
                                                {{ targetLeader.Nama }}
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-else class="empty-transfer">
                                        <i class="bi bi-arrow-left-right"></i>
                                        <h6>Pilih Leader Asal dan Tujuan</h6>
                                        <p>
                                            Pilih kedua leader untuk memulai
                                            proses transfer
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </TabToggle>
            </div>
        </div>
    </div>
</template>

<script>
import { ElSelect, ElOption, ElNotification, ElMessageBox } from "element-plus";
import draggable from "vuedraggable";
import axios from "axios";
import HeaderKpi from "@/components/legacy/header.vue";
import TabToggle from "../../components/ui/toggleUiV2.vue";

export default {
    name: "ApprovalKPIFlow",
    components: {
        HeaderKpi,
        TabToggle,
        ElSelect,
        ElOption,
        draggable,
    },
    data() {
        return {
            currentDate: new Date(),
            activeTab: "individual",
            tabs: [
                {
                    id: "individual",
                    label: "Individual",
                    icon: "bi bi-person-gear",
                    count: null,
                },
                {
                    id: "bulk",
                    label: "Massal",
                    icon: "bi bi-people-fill",
                    count: null,
                },
                {
                    id: "transfer",
                    label: "Transfer Tim",
                    icon: "bi bi-arrow-left-right",
                    count: null,
                },
            ],
            loading: {
                requester: false,
                approver: false,
                flow: false,
                saving: false,
                transfer: false,
                team: false,
            },
            // Individual Flow
            selectedRequester: null,
            requesterOptions: [],
            approverList: [],
            newApprover: null,
            approverOptions: [],
            // Bulk Flow
            selectedEmployees: [],
            bulkApprover: null,
            bulkApproverList: [],
            // Team Transfer
            sourceLeader: null,
            targetLeader: null,
            leaderOptions: [],
            teamMembers: [],
            searchTimeout: null,
        };
    },
    computed: {
        /* Dipindah dari `methods` ke `computed`. Template mengirimnya sebagai
           prop (`:dayNight="dayNight"`); di `methods` yang terkirim adalah
           FUNGSI-nya, bukan hasilnya — header menerima Function padahal
           menuntut String 'Siang'/'Malam'. */
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        canSaveIndividual() {
            return this.selectedRequester && this.approverList.length > 0;
        },
        canSaveBulk() {
            return (
                this.selectedEmployees.length > 0 &&
                this.bulkApproverList.length > 0
            );
        },
        canTransfer() {
            return (
                this.sourceLeader &&
                this.targetLeader &&
                this.sourceLeader.hashed_id !== this.targetLeader.hashed_id &&
                this.teamMembers.length > 0
            );
        },
    },
    methods: {
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

        // Search Methods
        async searchRequester(query) {
            if (query && query.length >= 2) {
                this.loading.requester = true;
                try {
                    const response = await axios.get(
                        `/api/v1/approval-kpi-admin/search-employees?q=${query}`
                    );
                    this.requesterOptions = response.data.result || [];
                } catch (error) {
                    console.error("Error searching requester:", error);
                    this.requesterOptions = [];
                } finally {
                    this.loading.requester = false;
                }
            } else {
                this.requesterOptions = [];
            }
        },

        async searchApprover(query) {
            if (query && query.length >= 2) {
                this.loading.approver = true;
                try {
                    const response = await axios.get(
                        `/api/v1/approval-kpi-admin/search-employees?q=${query}`
                    );
                    this.approverOptions = response.data.result || [];
                } catch (error) {
                    console.error("Error searching approver:", error);
                    this.approverOptions = [];
                } finally {
                    this.loading.approver = false;
                }
            } else {
                this.approverOptions = [];
            }
        },

        async searchLeader(query) {
            if (query && query.length >= 2) {
                try {
                    const response = await axios.get(
                        `/api/v1/approval-kpi-admin/search-leaders?q=${query}`
                    );
                    this.leaderOptions = response.data.result || [];
                } catch (error) {
                    console.error("Error searching leader:", error);
                    this.leaderOptions = [];
                }
            } else {
                this.leaderOptions = [];
            }
        },

        // Individual Flow Methods
        async handleRequesterChange() {
            if (this.selectedRequester) {
                await this.loadApprovalFlow();
            } else {
                this.approverList = [];
            }
        },

        async loadApprovalFlow() {
            if (!this.selectedRequester) return;

            this.loading.flow = true;
            try {
                const response = await axios.get(
                    `/api/v1/approval-kpi-admin/flow/${this.selectedRequester.hashed_id}`
                );
                this.approverList = response.data.result || [];
            } catch (error) {
                console.error("Error loading approval flow:", error);
                this.approverList = [];
            } finally {
                this.loading.flow = false;
            }
        },

        addApprover(approver) {
            if (!approver) return;

            const isDuplicate = this.approverList.some(
                (item) => item.hashed_id === approver.hashed_id
            );

            if (isDuplicate) {
                ElNotification({
                    title: "Peringatan",
                    message: "Approver sudah ditambahkan",
                    type: "warning",
                });
                return;
            }

            const newApprover = {
                hashed_id: approver.hashed_id,
                Nama_Approver: approver.Nama,
                Jabatan_Approver: approver.nama_jabatan,
                Divisi_Approver: approver.nama_divisi || "-",
            };

            this.approverList.push(newApprover);
            this.newApprover = null;
            this.approverOptions = [];
        },

        removeApprover(index) {
            this.approverList.splice(index, 1);
        },

        onFlowChange() {
            // Optional: Add any logic when flow order changes
        },

        // Quick Actions
        async addDirectManager() {
            if (!this.selectedRequester) return;

            try {
                const response = await axios.get(
                    `/api/v1/approval-kpi-admin/direct-manager/${this.selectedRequester.hashed_id}`
                );
                const manager = response.data.result;
                if (manager) {
                    this.addApprover(manager);
                }
            } catch (error) {
                ElNotification({
                    title: "Info",
                    message: "Atasan langsung tidak ditemukan",
                    type: "info",
                });
            }
        },

        async addDepartmentHead() {
            if (!this.selectedRequester) return;

            try {
                const response = await axios.get(
                    `/api/v1/approval-kpi-admin/department-head/${this.selectedRequester.hashed_id}`
                );
                const deptHead = response.data.result;
                if (deptHead) {
                    this.addApprover(deptHead);
                }
            } catch (error) {
                ElNotification({
                    title: "Info",
                    message: "Kepala departemen tidak ditemukan",
                    type: "info",
                });
            }
        },

        // Bulk Flow Methods
        addBulkApprover() {
            if (!this.bulkApprover) return;

            const isDuplicate = this.bulkApproverList.some(
                (item) => item.hashed_id === this.bulkApprover.hashed_id
            );

            if (isDuplicate) {
                ElNotification({
                    title: "Peringatan",
                    message: "Approver sudah ditambahkan",
                    type: "warning",
                });
                return;
            }

            const newApprover = {
                hashed_id: this.bulkApprover.hashed_id,
                Nama_Approver: this.bulkApprover.Nama,
                Jabatan_Approver: this.bulkApprover.nama_jabatan,
                Divisi_Approver: this.bulkApprover.nama_divisi || "-",
            };

            this.bulkApproverList.push(newApprover);
            this.bulkApprover = null;
        },

        removeBulkApprover(index) {
            this.bulkApproverList.splice(index, 1);
        },

        removeFromBulkSelection(employee) {
            this.selectedEmployees = this.selectedEmployees.filter(
                (emp) => emp.hashed_id !== employee.hashed_id
            );
        },

        clearBulkSelection() {
            this.selectedEmployees = [];
            this.bulkApproverList = [];
        },

        // Team Transfer Methods
        async loadTeamMembers() {
            if (!this.sourceLeader) {
                this.teamMembers = [];
                return;
            }

            this.loading.team = true;
            try {
                const response = await axios.get(
                    `/api/v1/approval-kpi-admin/team-members/${this.sourceLeader.hashed_id}`
                );
                this.teamMembers = response.data.result || [];
            } catch (error) {
                console.error("Error loading team members:", error);
                this.teamMembers = [];
            } finally {
                this.loading.team = false;
            }
        },

        getApprovalFlowCount() {
            // This would typically come from API
            return this.teamMembers.length; // Simplified
        },

        async executeTransfer() {
            if (!this.canTransfer) return;

            try {
                await ElMessageBox.confirm(
                    `Anda akan memindahkan ${this.teamMembers.length} anggota tim dari ${this.sourceLeader.Nama} ke ${this.targetLeader.Nama}?`,
                    "Konfirmasi Transfer Tim",
                    {
                        confirmButtonText: "Ya, Transfer",
                        cancelButtonText: "Batal",
                        type: "warning",
                        confirmButtonClass: "btn-primary",
                        cancelButtonClass: "btn-secondary",
                    }
                );

                this.loading.transfer = true;

                const payload = {
                    source_leader_id: this.sourceLeader.hashed_id,
                    target_leader_id: this.targetLeader.hashed_id,
                    team_member_ids: this.teamMembers.map(
                        (member) => member.hashed_id
                    ),
                };

                await axios.post(
                    "/api/v1/approval-kpi-admin/transfer-team",
                    payload
                );

                ElNotification({
                    title: "Sukses",
                    message: `Berhasil memindahkan ${this.teamMembers.length} anggota tim ke ${this.targetLeader.Nama}`,
                    type: "success",
                });

                // Reset form
                this.sourceLeader = null;
                this.targetLeader = null;
                this.teamMembers = [];
            } catch (error) {
                if (error !== "cancel") {
                    console.error("Error transferring team:", error);
                    ElNotification({
                        title: "Error",
                        message: "Gagal melakukan transfer tim",
                        type: "error",
                    });
                }
            } finally {
                this.loading.transfer = false;
            }
        },

        // Save Methods
        async saveIndividualFlow() {
            if (!this.canSaveIndividual) return;

            this.loading.saving = true;
            try {
                const payload = {
                    requester_id: this.selectedRequester.hashed_id,
                    approvers: this.approverList.map((approver, index) => ({
                        approver_id: approver.hashed_id,
                        order: index + 1,
                    })),
                };

                await axios.post("/api/v1/approval-kpi-admin/save", payload);

                ElNotification({
                    title: "Sukses",
                    message: "Alur persetujuan berhasil disimpan",
                    type: "success",
                });
            } catch (error) {
                console.error("Error saving approval flow:", error);
                ElNotification({
                    title: "Error",
                    message: "Gagal menyimpan alur persetujuan",
                    type: "error",
                });
            } finally {
                this.loading.saving = false;
            }
        },

        async saveBulkFlow() {
            if (!this.canSaveBulk) return;

            this.loading.saving = true;
            try {
                const payload = {
                    employee_ids: this.selectedEmployees.map(
                        (emp) => emp.hashed_id
                    ),
                    approvers: this.bulkApproverList.map((approver, index) => ({
                        approver_id: approver.hashed_id,
                        order: index + 1,
                    })),
                };

                await axios.post(
                    "/api/v1/approval-kpi-admin/bulk-save",
                    payload
                );

                ElNotification({
                    title: "Sukses",
                    message: `Alur persetujuan berhasil disimpan untuk ${this.selectedEmployees.length} karyawan`,
                    type: "success",
                });

                this.clearBulkSelection();
            } catch (error) {
                console.error("Error saving bulk flow:", error);
                ElNotification({
                    title: "Error",
                    message: "Gagal menyimpan alur persetujuan massal",
                    type: "error",
                });
            } finally {
                this.loading.saving = false;
            }
        },
    },
    beforeDestroy() {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
    },
};
</script>

<style scoped>
/* Enhanced CSS Variables */
:root {
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
}

/* Main Layout */
.tab-content-wrapper {
    padding: 2rem 0;
}

.feature-card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.feature-header {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--surface-soft) 0%, var(--hcis-slate-50) 100%);
    border-bottom: 1px solid var(--border);
}

.feature-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-right: 1rem;
    background: var(--primary);
}

.feature-icon.bulk {
    background: var(--success);
}

.feature-icon.transfer {
    background: var(--info);
}

.feature-info h5 {
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.feature-info p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Form Sections */
.form-section {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-child {
    border-bottom: none;
}

.form-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-label i {
    color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 768px) {
    .form-row {
        grid-template-columns: 1fr 1fr;
    }
}

/* Modern Select Styles */
:deep(.modern-select .el-select__wrapper) {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    box-shadow: none !important;
}

:deep(.modern-select .el-select__wrapper:hover) {
    border-color: var(--primary-light);
}

:deep(.modern-select .el-select__wrapper.is-focus) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}

:deep(.modern-select .el-input__inner) {
    color: var(--text);
    font-size: 0.9rem;
}

/* Employee Option */
.employee-option {
    display: flex;
    flex-direction: column;
    padding: 0.5rem 0;
}

.employee-option-name {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.employee-option-code {
    font-size: 0.8rem;
    color: var(--text-muted);
}

/* Flow Sections */
.flow-section {
    margin-top: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-header h6 {
    color: var(--dark);
    font-weight: 600;
    margin: 0;
}

.employee-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Approval Flow */
.approval-flow {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.approval-step {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.approval-step:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.step-header {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.step-number {
    background: var(--primary);
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 600;
    flex-shrink: 0;
}

.step-info {
    flex: 1;
}

.step-info h6 {
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.step-info p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.85rem;
}

.step-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.drag-handle {
    color: var(--text-muted);
    cursor: grab;
    font-size: 1.1rem;
}

/* Side Panel */
.side-panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1rem;
}

.panel-header {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.panel-header i {
    color: var(--primary);
    font-size: 1.2rem;
    margin-right: 0.75rem;
}

.panel-header h6 {
    color: var(--dark);
    margin: 0;
    font-weight: 600;
}

.panel-content {
    padding: 1.25rem;
}

.quick-actions h6 {
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Buttons */
.btn-primary {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-secondary {
    background: var(--secondary);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: var(--hcis-slate-600);
}

.btn-block {
    width: 100%;
    justify-content: center;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.btn-danger:hover {
    background: var(--danger);
    color: white;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Action Cards */
.action-card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
}

.action-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.action-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--success);
    color: white;
    border-radius: 8px;
}

.action-info i {
    font-size: 1.2rem;
}

/* Bulk Operations */
.selected-preview {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1.25rem;
    margin-top: 1rem;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.btn-clear {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.employees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}

.employee-card {
    display: flex;
    align-items: center;
    background: var(--surface);
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    gap: 0.75rem;
}

.employee-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.employee-details {
    flex: 1;
}

.employee-details strong {
    display: block;
    font-size: 0.9rem;
    color: var(--dark);
}

.employee-details small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.btn-remove {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
}

.btn-remove:hover {
    color: var(--danger);
}

/* Bulk Flow Setup */
.bulk-flow-setup {
    margin-top: 1rem;
}

.flow-controls {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.flow-controls .modern-select {
    flex: 1;
}

.bulk-approver-list {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1.25rem;
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    font-weight: 600;
    color: var(--dark);
}

.list-header small {
    color: var(--text-muted);
    font-weight: normal;
}

.approver-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--surface);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    border: 1px solid var(--border);
}

.approver-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.approver-order {
    background: var(--primary);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Action Footer */
.action-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--surface);
    padding: 1.5rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-top: 2rem;
}

.action-summary {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.action-summary i {
    color: var(--info);
    font-size: 1.2rem;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

/* Transfer Configuration */
.transfer-configuration {
    padding: 1.5rem;
}

.config-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (min-width: 768px) {
    .config-row {
        grid-template-columns: 1fr 1fr;
    }
}

.team-preview {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1.25rem;
}

.members-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 300px;
    overflow-y: auto;
}

.member-item {
    display: flex;
    align-items: center;
    background: var(--surface);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    gap: 0.75rem;
}

.member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--success);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 600;
}

.member-details {
    flex: 1;
}

.member-status {
    color: var(--success);
}

/* Transfer Preview */
.transfer-preview {
    height: 100%;
}

.preview-card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1rem;
}

.preview-content {
    padding: 1.5rem;
}

.transfer-flow {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.flow-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
}

.flow-item.source {
    background: rgba(239, 68, 68, 0.05);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.flow-item.target {
    background: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.flow-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    flex-shrink: 0;
}

.flow-item.source .flow-avatar {
    background: var(--danger);
}

.flow-item.target .flow-avatar {
    background: var(--success);
}

.flow-details {
    flex: 1;
}

.flow-details strong {
    display: block;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.flow-details small {
    color: var(--text-muted);
}

.transfer-arrow {
    color: var(--primary);
    font-size: 1.2rem;
}

.transfer-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
}

.stat-item i {
    font-size: 1.5rem;
    color: var(--primary);
}

.stat-item strong {
    display: block;
    color: var(--dark);
    font-size: 1.2rem;
}

.stat-item small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.transfer-action {
    text-align: center;
}

.action-note {
    display: block;
    margin-top: 0.75rem;
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Empty States */
.empty-flow,
.empty-transfer {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
}

.empty-flow i,
.empty-transfer i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-flow p,
.empty-transfer h6 {
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

.empty-flow small,
.empty-transfer p {
    margin: 0;
    font-size: 0.9rem;
}

.loading-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem;
    color: var(--text-muted);
}

/* Responsive Design */
@media (max-width: 768px) {
    .tab-content-wrapper {
        padding: 1rem 0;
    }

    .feature-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .feature-icon {
        margin-right: 0;
    }

    .action-footer {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .flow-controls {
        flex-direction: column;
    }

    .step-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .step-actions {
        align-self: flex-end;
    }
}

@media (max-width: 576px) {
    .form-section {
        padding: 1rem;
    }

    .employees-grid {
        grid-template-columns: 1fr;
    }

    .transfer-stats {
        grid-template-columns: 1fr;
    }
}

/* Animation */
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

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --surface: var(--hcis-slate-800);
        --surface-soft: var(--hcis-slate-700);
        --dark: var(--hcis-slate-50);
        --text: var(--hcis-slate-200);
        --text-muted: var(--hcis-slate-400);
        --border: var(--hcis-slate-600);
        --light: var(--hcis-slate-900);
    }
}
</style>

<style scoped>
/* Consolidated and deduplicated styles */
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
    --danger-light: var(--hcis-legacy-bootstrap-danger); /* Merah muda, lebih terang untuk latar belakang atau efek disabled */
    --gray-light: var(--hcis-legacy-bootstrap-gray);
    --el-slider-main-bg-color: var(--hcis-indigo-500);
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
    background: var(--hcis-emerald-600);
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
    background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, #059669ce 100%);
}
.header-pending {
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, #6365f1ce 100%);
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
    background-color: var(--hcis-legacy-bootstrap-gray);
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
