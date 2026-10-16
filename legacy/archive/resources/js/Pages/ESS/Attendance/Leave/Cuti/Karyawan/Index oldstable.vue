<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <HeaderKpi
            title="Cuti Saya"
            subtitle="Kelola pengajuan cuti dan lihat riwayat"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <ApprovalTabs
                    :tabs="karyawanTabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Filters untuk Semua Tab -->
                    <template #filters="{ activeTab }">
                        <div class="row g-3 align-items-center">
                            <!-- Filter untuk Tab Ajukan -->
                            <div class="col-md-4" v-if="activeTab === 'ajukan'">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input
                                        type="text"
                                        v-model="searchQuery"
                                        placeholder="Cari jenis cuti..."
                                        class="search-input"
                                    />
                                </div>
                            </div>

                            <!-- Filter untuk Tab Riwayat -->
                            <div
                                class="col-md-3"
                                v-if="activeTab === 'riwayat'"
                            >
                                <select
                                    v-model="statusFilter"
                                    class="filter-select"
                                >
                                    <option value="">Semua Status</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>

                            <!-- Info Saldo -->
                            <div class="col-md-5" v-if="activeTab === 'ajukan'">
                                <div class="saldo-quick-info">
                                    <div
                                        class="saldo-item"
                                        v-for="saldo in saldoCuti"
                                        :key="saldo.jenis_cuti_id"
                                    >
                                        <span class="saldo-label"
                                            >{{ saldo.nama_jenis }}:</span
                                        >
                                        <span class="saldo-value"
                                            >{{ saldo.sisa }}
                                            {{ saldo.satuan }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div
                                class="col-md-4"
                                v-if="activeTab === 'riwayat'"
                            >
                                <button
                                    class="btn-modern btn-primary"
                                    @click="refreshData"
                                >
                                    <i class="bi bi-arrow-clockwise me-2"></i
                                    >Refresh
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Ajukan Cuti dengan Desain yang Lebih Baik -->
                    <template #ajukan>
                        <div class="tab-content-improved">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="form-group-optimized">
                                        <h5 class="form-title-modern">
                                            <i class="bi bi-send-plus me-2"></i>
                                            Ajukan Cuti Baru
                                        </h5>

                                        <!-- Jenis Cuti Cards -->
                                        <!-- <div class="cuti-type-cards mb-4">
                                            <div class="row g-3">
                                                <div
                                                    class="col-md-6"
                                                    v-for="jenis in filteredJenisCuti"
                                                    :key="jenis.id"
                                                >
                                                    <div
                                                        class="cuti-type-card"
                                                        :class="{
                                                            selected:
                                                                pengajuanForm.jenis_cuti_id ===
                                                                jenis.id,
                                                        }"
                                                        @click="
                                                            selectCutiType(
                                                                jenis
                                                            )
                                                        "
                                                    >
                                                        <div
                                                            class="cuti-type-icon"
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
                                                            class="cuti-type-content"
                                                        >
                                                            <h6>
                                                                {{ jenis.nama }}
                                                            </h6>
                                                            <p>
                                                                Sisa:
                                                                {{
                                                                    getSisaCuti(
                                                                        jenis.id
                                                                    )
                                                                }}
                                                                {{
                                                                    jenis.satuan
                                                                }}
                                                            </p>
                                                            <small
                                                                class="text-muted"
                                                                >{{
                                                                    jenis.deskripsi
                                                                }}</small
                                                            >
                                                        </div>
                                                        <div
                                                            class="cuti-type-check"
                                                        >
                                                            <i
                                                                class="bi bi-check-circle-fill"
                                                                v-if="
                                                                    pengajuanForm.jenis_cuti_id ===
                                                                    jenis.id
                                                                "
                                                            ></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> -->

                                        <!-- Form Pengajuan -->
                                        <form
                                            @submit.prevent="submitPengajuan"
                                            class="modern-form"
                                        >
                                            <div class="">
                                                <label
                                                    class="form-label-modern"
                                                >
                                                    <i
                                                        class="bi bi-calendar me-2"
                                                    ></i
                                                    >Pilih Jenis Cuti
                                                </label>
                                                <v-select
                                                    :options="filteredJenisCuti"
                                                    :searchable="true"
                                                    :filterable="true"
                                                    :clearable="false"
                                                    label="label"
                                                    placeholder="Pilih Jenis Cuti..."
                                                    :reduce="
                                                        (opt) => opt.jenis.id
                                                    "
                                                    v-model="
                                                        pengajuanForm.jenis_cuti_id
                                                    "
                                                    @update:modelValue="
                                                        selectCutiType(jenis)
                                                    "
                                                    class="target-scale-select"
                                                />
                                            </div>
                                            <div class="row mt-3">
                                                <!-- <div class="col-md-6">
                                                    <div
                                                        class="form-group-modern"
                                                    >
                                                        <label
                                                            class="form-label-modern"
                                                        >
                                                            <i
                                                                class="bi bi-calendar me-2"
                                                            ></i
                                                            >Tanggal Mulai
                                                        </label>
                                                        <DatePicker
                                                            v-model="
                                                                pengajuanForm.tanggal_mulai
                                                            "
                                                            placeholder="Pilih tanggal mulai"
                                                            class="form-control-modern"
                                                            required
                                                            @update:modelValue="
                                                                calculateHari
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div
                                                        class="form-group-modern"
                                                    >
                                                        <label
                                                            class="form-label-modern"
                                                        >
                                                            <i
                                                                class="bi bi-calendar-check me-2"
                                                            ></i
                                                            >Tanggal Selesai
                                                        </label>
                                                        <DatePicker
                                                            v-model="
                                                                pengajuanForm.tanggal_selesai
                                                            "
                                                            placeholder="Pilih tanggal selesai"
                                                            class="form-control-modern"
                                                            required
                                                            @update:modelValue="
                                                                calculateHari
                                                            "
                                                        />
                                                    </div>
                                                </div> -->
                                                <div class="col-12 mb-3">
                                                    <DateTimePicker
                                                        v-model="
                                                            pengajuanForm.tanggal_selesai
                                                        "
                                                        mode="date"
                                                        showClear="true"
                                                        selection-mode="multiple"
                                                        placeholder="Pilih rentang waktu"
                                                        @change="
                                                            handleRangeChange
                                                        "
                                                    />
                                                </div>
                                                <div class="col-12">
                                                    <div
                                                        class="form-group-modern"
                                                    >
                                                        <label
                                                            class="form-label-modern"
                                                        >
                                                            <i
                                                                class="bi bi-chat-text me-2"
                                                            ></i
                                                            >Alasan Cuti
                                                        </label>
                                                        <textarea
                                                            v-model="
                                                                pengajuanForm.alasan
                                                            "
                                                            class="form-control-modern"
                                                            rows="4"
                                                            placeholder="Jelaskan alasan pengajuan cuti..."
                                                            required
                                                        ></textarea>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div
                                                        class="form-group-modern"
                                                    >
                                                        <label
                                                            class="form-label-modern"
                                                        >
                                                            <i
                                                                class="bi bi-paperclip me-2"
                                                            ></i
                                                            >Dokumen Pendukung
                                                            (Opsional)
                                                        </label>
                                                        <div
                                                            class="file-upload-area"
                                                            @click="
                                                                $refs.fileInput.click()
                                                            "
                                                        >
                                                            <i
                                                                class="bi bi-cloud-upload"
                                                            ></i>
                                                            <p>
                                                                Klik untuk
                                                                mengunggah file
                                                            </p>
                                                            <small
                                                                >Format: PDF,
                                                                JPG, PNG (Maks.
                                                                2MB)</small
                                                            >
                                                            <input
                                                                type="file"
                                                                ref="fileInput"
                                                                @change="
                                                                    handleFileUpload
                                                                "
                                                                class="d-none"
                                                                accept=".pdf,.jpg,.jpeg,.png"
                                                            />
                                                        </div>
                                                        <div
                                                            v-if="
                                                                pengajuanForm.file_pendukung
                                                            "
                                                            class="file-preview"
                                                        >
                                                            <i
                                                                class="bi bi-file-earmark-text"
                                                            ></i>
                                                            <span>{{
                                                                pengajuanForm
                                                                    .file_pendukung
                                                                    .name
                                                            }}</span>
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    removeFile
                                                                "
                                                                class="btn-remove-file"
                                                            >
                                                                <i
                                                                    class="bi bi-x"
                                                                ></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-actions-modern">
                                                <button
                                                    type="button"
                                                    class="btn-modern btn-secondary"
                                                    @click="resetForm"
                                                >
                                                    <i
                                                        class="bi bi-arrow-clockwise me-2"
                                                    ></i
                                                    >Reset
                                                </button>
                                                <button
                                                    type="submit"
                                                    class="btn-modern btn-primary"
                                                    :disabled="!isFormValid"
                                                >
                                                    <i
                                                        class="bi bi-send me-2"
                                                    ></i
                                                    >Ajukan Cuti
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Sidebar Info -->
                                <div class="col-lg-4">
                                    <div class="info-sidebar-modern">
                                        <div class="col-12">
                                            <div class="duration-display">
                                                <div class="duration-card">
                                                    <i
                                                        class="bi bi-clock d-flex justify-content-center align-items-center me-2"
                                                    ></i>
                                                    <div
                                                        class="duration-content"
                                                    >
                                                        <span
                                                            class="duration-label"
                                                            >Durasi Cuti</span
                                                        >
                                                        <span
                                                            class="duration-value"
                                                            >{{
                                                                pengajuanForm.jumlah_hari
                                                            }}
                                                            Hari</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Ringkasan Saldo -->
                                        <!-- <div
                                            class="info-card-modern saldo-summary"
                                        >
                                            <h6 class="info-title-modern">
                                                <i
                                                    class="bi bi-wallet2 me-2"
                                                ></i
                                                >Ringkasan Saldo
                                            </h6>
                                            <div class="saldo-chart">
                                                <div
                                                    v-for="saldo in saldoCuti"
                                                    :key="saldo.jenis_cuti_id"
                                                    class="saldo-progress-item"
                                                >
                                                    <div class="saldo-info">
                                                        <span
                                                            class="saldo-name"
                                                            >{{
                                                                saldo.nama_jenis
                                                            }}</span
                                                        >
                                                        <span
                                                            class="saldo-numbers"
                                                            >{{ saldo.sisa }}/{{
                                                                saldo.kuota
                                                            }}
                                                            {{
                                                                saldo.satuan
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="progress-bar-modern"
                                                    >
                                                        <div
                                                            class="progress-fill"
                                                            :style="{
                                                                width:
                                                                    calculateProgress(
                                                                        saldo
                                                                    ) + '%',
                                                            }"
                                                            :class="
                                                                getProgressClass(
                                                                    saldo
                                                                )
                                                            "
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> -->

                                        <!-- Info Cuti Bersama -->
                                        <div
                                            class="info-card-modern"
                                            v-if="cutiBersamaInfo"
                                        >
                                            <h6 class="info-title-modern">
                                                <i
                                                    class="bi bi-info-circle me-2"
                                                ></i
                                                >Informasi
                                            </h6>
                                            <div class="alert-modern info">
                                                <i
                                                    class="bi bi-calendar-event"
                                                ></i>
                                                <p>{{ cutiBersamaInfo }}</p>
                                            </div>
                                        </div>

                                        <!-- Quick Actions -->
                                        <div class="info-card-modern">
                                            <h6 class="info-title-modern">
                                                <i
                                                    class="bi bi-lightning me-2"
                                                ></i
                                                >Quick Actions
                                            </h6>
                                            <div class="quick-actions">
                                                <button
                                                    class="quick-action-btn"
                                                    @click="
                                                        activeTab = 'kalender'
                                                    "
                                                >
                                                    <i
                                                        class="bi bi-calendar"
                                                    ></i>
                                                    <span>Lihat Kalender</span>
                                                </button>
                                                <button
                                                    class="quick-action-btn"
                                                    @click="
                                                        activeTab = 'riwayat'
                                                    "
                                                >
                                                    <i
                                                        class="bi bi-clock-history"
                                                    ></i>
                                                    <span>Riwayat Cuti</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="tips-card">
                                            <h6 class="tips-title">
                                                <i
                                                    class="bi bi-lightbulb me-2"
                                                ></i
                                                >Tips
                                            </h6>
                                            <div class="tips-content">
                                                <div class="tip-item">
                                                    <i
                                                        class="bi bi-check-circle"
                                                    ></i>
                                                    <p>
                                                        Gunakan cuti tahunan
                                                        sebelum akhir tahun
                                                    </p>
                                                </div>
                                                <div class="tip-item">
                                                    <i
                                                        class="bi bi-check-circle"
                                                    ></i>
                                                    <p>
                                                        Ajukan cuti minimal 3
                                                        hari sebelumnya
                                                    </p>
                                                </div>
                                                <div class="tip-item">
                                                    <i
                                                        class="bi bi-check-circle"
                                                    ></i>
                                                    <p>
                                                        Periksa saldo cuti
                                                        secara berkala
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Riwayat yang Dioptimalkan -->
                    <template #riwayat>
                        <div class="tab-content-improved">
                            <div class="riwayat-container">
                                <!-- Stats Cards -->
                                <div class="stats-cards mb-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="stat-card total">
                                                <div class="stat-icon">
                                                    <i
                                                        class="bi bi-list-ul"
                                                    ></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h4>
                                                        {{ riwayatList.length }}
                                                    </h4>
                                                    <p>Total Pengajuan</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card approved">
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
                                            <div class="stat-card pending">
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
                                                    <p>Menunggu</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card rejected">
                                                <div class="stat-icon">
                                                    <i
                                                        class="bi bi-x-circle"
                                                    ></i>
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

                                <!-- Riwayat List -->
                                <div class="riwayat-list">
                                    <div
                                        v-for="item in filteredRiwayat"
                                        :key="item.id"
                                        class="riwayat-card"
                                    >
                                        <div class="riwayat-header">
                                            <div class="riwayat-type">
                                                <i
                                                    :class="
                                                        getCutiTypeIcon(
                                                            item.jenis_cuti
                                                        )
                                                    "
                                                ></i>
                                                <span>{{
                                                    item.jenis_cuti
                                                }}</span>
                                            </div>
                                            <Badge
                                                :type="
                                                    getStatusBadgeType(
                                                        item.status
                                                    )
                                                "
                                            >
                                                {{ getStatusText(item.status) }}
                                            </Badge>
                                        </div>

                                        <div class="riwayat-content">
                                            <div class="riwayat-dates">
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
                                                <span class="duration"
                                                    >({{
                                                        item.jumlah_hari
                                                    }}
                                                    hari)</span
                                                >
                                            </div>
                                            <div class="riwayat-reason">
                                                <p>{{ item.alasan }}</p>
                                            </div>
                                        </div>

                                        <div class="riwayat-footer">
                                            <div class="riwayat-meta">
                                                <small
                                                    >Diajukan:
                                                    {{
                                                        formatDate(
                                                            item.created_at
                                                        )
                                                    }}</small
                                                >
                                            </div>
                                            <div class="riwayat-actions">
                                                <button
                                                    class="btn-action btn-view"
                                                    @click="
                                                        viewRiwayatDetail(item)
                                                    "
                                                >
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button
                                                    v-if="
                                                        item.status ===
                                                        'pending'
                                                    "
                                                    class="btn-action btn-danger"
                                                    @click="
                                                        cancelPengajuan(item)
                                                    "
                                                >
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty State -->
                                <div
                                    v-if="filteredRiwayat.length === 0"
                                    class="empty-state-modern"
                                >
                                    <i class="bi bi-inbox"></i>
                                    <h4>Belum Ada Riwayat</h4>
                                    <p>Anda belum memiliki pengajuan cuti</p>
                                    <button
                                        class="btn-modern btn-primary"
                                        @click="activeTab = 'ajukan'"
                                    >
                                        <i class="bi bi-plus me-2"></i>Ajukan
                                        Cuti Pertama
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Saldo yang Dioptimalkan -->
                    <template #saldo>
                        <div class="tab-content-improved">
                            <div class="saldo-container">
                                <div class="row">
                                    <!-- Saldo Cards -->
                                    <div class="col-lg-8">
                                        <div class="saldo-cards-grid">
                                            <div
                                                v-for="saldo in saldoDetail"
                                                :key="saldo.id"
                                                class="saldo-card-detailed"
                                            >
                                                <div class="saldo-header">
                                                    <div class="saldo-icon">
                                                        <i
                                                            :class="
                                                                getCutiTypeIcon(
                                                                    saldo.nama_jenis
                                                                )
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div class="saldo-info">
                                                        <h6>
                                                            {{
                                                                saldo.nama_jenis
                                                            }}
                                                        </h6>
                                                        <p>
                                                            {{
                                                                saldo.deskripsi
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div class="saldo-badge">
                                                        {{ saldo.sisa }}
                                                        {{ saldo.satuan }}
                                                    </div>
                                                </div>

                                                <div
                                                    class="saldo-progress-detailed"
                                                >
                                                    <div class="progress-info">
                                                        <span
                                                            >Digunakan:
                                                            {{
                                                                saldo.digunakan
                                                            }}</span
                                                        >
                                                        <span
                                                            >Total:
                                                            {{
                                                                saldo.kuota
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="progress-bar-detailed"
                                                    >
                                                        <div
                                                            class="progress-fill"
                                                            :style="{
                                                                width:
                                                                    saldo.persentase +
                                                                    '%',
                                                            }"
                                                            :class="
                                                                getProgressClass(
                                                                    saldo
                                                                )
                                                            "
                                                        ></div>
                                                    </div>
                                                    <div
                                                        class="progress-percentage"
                                                    >
                                                        {{ saldo.persentase }}%
                                                        digunakan
                                                    </div>
                                                </div>

                                                <div
                                                    class="saldo-footer"
                                                    v-if="saldo.expired_date"
                                                >
                                                    <i class="bi bi-clock"></i>
                                                    <span
                                                        >Kedaluwarsa:
                                                        {{
                                                            formatDate(
                                                                saldo.expired_date
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ringkasan -->
                                    <div class="col-lg-4">
                                        <div class="summary-sidebar">
                                            <div class="summary-card">
                                                <h6 class="summary-title">
                                                    <i
                                                        class="bi bi-graph-up me-2"
                                                    ></i
                                                    >Ringkasan
                                                </h6>
                                                <div class="summary-stats">
                                                    <div class="summary-stat">
                                                        <div
                                                            class="stat-icon total"
                                                        >
                                                            <i
                                                                class="bi calendar-check"
                                                            ></i>
                                                        </div>
                                                        <div
                                                            class="stat-content"
                                                        >
                                                            <span
                                                                class="stat-value"
                                                                >{{
                                                                    totalCutiTersedia
                                                                }}</span
                                                            >
                                                            <span
                                                                class="stat-label"
                                                                >Hari
                                                                Tersedia</span
                                                            >
                                                        </div>
                                                    </div>
                                                    <div class="summary-stat">
                                                        <div
                                                            class="stat-icon used"
                                                        >
                                                            <i
                                                                class="bi bi-calendar-plus"
                                                            ></i>
                                                        </div>
                                                        <div
                                                            class="stat-content"
                                                        >
                                                            <span
                                                                class="stat-value"
                                                                >{{
                                                                    totalCutiDigunakan
                                                                }}</span
                                                            >
                                                            <span
                                                                class="stat-label"
                                                                >Hari
                                                                Digunakan</span
                                                            >
                                                        </div>
                                                    </div>
                                                    <div class="summary-stat">
                                                        <div
                                                            class="stat-icon expiring"
                                                        >
                                                            <i
                                                                class="bi bi-clock"
                                                            ></i>
                                                        </div>
                                                        <div
                                                            class="stat-content"
                                                        >
                                                            <span
                                                                class="stat-value"
                                                                >{{
                                                                    totalCutiKedaluwarsa
                                                                }}</span
                                                            >
                                                            <span
                                                                class="stat-label"
                                                                >Akan
                                                                Kedaluwarsa</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tips-card">
                                                <h6 class="tips-title">
                                                    <i
                                                        class="bi bi-lightbulb me-2"
                                                    ></i
                                                    >Tips
                                                </h6>
                                                <div class="tips-content">
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        <p>
                                                            Gunakan cuti tahunan
                                                            sebelum akhir tahun
                                                        </p>
                                                    </div>
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        <p>
                                                            Ajukan cuti minimal
                                                            3 hari sebelumnya
                                                        </p>
                                                    </div>
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        <p>
                                                            Periksa saldo cuti
                                                            secara berkala
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

                    <!-- Tab Kalender yang Dioptimalkan -->
                    <template #kalender>
                        <div class="tab-content-improved">
                            <div class="calendar-container-modern">
                                <!-- Calendar Navigation -->
                                <div class="calendar-header-modern">
                                    <div class="calendar-nav">
                                        <button
                                            class="btn-calendar-nav"
                                            @click="prevMonth"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <h4 class="calendar-title">
                                            {{ currentMonthYear }}
                                        </h4>
                                        <button
                                            class="btn-calendar-nav"
                                            @click="nextMonth"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div class="calendar-views">
                                        <button
                                            class="view-btn"
                                            :class="{
                                                active:
                                                    calendarView === 'month',
                                            }"
                                            @click="calendarView = 'month'"
                                        >
                                            Bulan
                                        </button>
                                        <button
                                            class="view-btn"
                                            :class="{
                                                active: calendarView === 'list',
                                            }"
                                            @click="calendarView = 'list'"
                                        >
                                            Daftar
                                        </button>
                                    </div>
                                </div>

                                <!-- Month View -->
                                <div
                                    v-if="calendarView === 'month'"
                                    class="calendar-month-view"
                                >
                                    <div class="calendar-weekdays">
                                        <div
                                            v-for="day in weekdays"
                                            :key="day"
                                            class="weekday"
                                        >
                                            {{ day }}
                                        </div>
                                    </div>
                                    <div class="calendar-days-grid">
                                        <div
                                            v-for="day in calendarDays"
                                            :key="day.date"
                                            :class="[
                                                'calendar-day',
                                                {
                                                    'other-month':
                                                        !day.isCurrentMonth,
                                                    today: day.isToday,
                                                    'cuti-bersama':
                                                        day.isCutiBersama,
                                                    'cuti-pribadi':
                                                        day.isCutiPribadi,
                                                    selected: isDateSelected(
                                                        day.date
                                                    ),
                                                },
                                            ]"
                                            @click="selectDate(day)"
                                        >
                                            <span class="day-number">{{
                                                day.day
                                            }}</span>
                                            <div class="day-events">
                                                <div
                                                    v-if="day.isCutiBersama"
                                                    class="event-dot bersama"
                                                ></div>
                                                <div
                                                    v-if="day.isCutiPribadi"
                                                    class="event-dot pribadi"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- List View -->
                                <div v-else class="calendar-list-view">
                                    <div class="events-list-modern">
                                        <div
                                            v-for="event in currentMonthEvents"
                                            :key="event.id"
                                            class="event-item-modern"
                                        >
                                            <div class="event-date">
                                                <div class="event-day">
                                                    {{
                                                        formatEventDay(
                                                            event.tanggal_mulai
                                                        )
                                                    }}
                                                </div>
                                                <div class="event-month">
                                                    {{
                                                        formatEventMonth(
                                                            event.tanggal_mulai
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div class="event-content">
                                                <h6 class="event-title">
                                                    {{ event.judul }}
                                                </h6>
                                                <p class="event-description">
                                                    {{ event.deskripsi }}
                                                </p>
                                                <div class="event-meta">
                                                    <span
                                                        class="event-type"
                                                        :class="event.tipe"
                                                    >
                                                        {{
                                                            event.tipe ===
                                                            "bersama"
                                                                ? "Cuti Bersama"
                                                                : "Cuti Pribadi"
                                                        }}
                                                    </span>
                                                    <span
                                                        class="event-duration"
                                                        v-if="
                                                            event.tanggal_mulai !==
                                                            event.tanggal_selesai
                                                        "
                                                    >
                                                        Sampai
                                                        {{
                                                            formatDate(
                                                                event.tanggal_selesai
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Legend -->
                                <div class="calendar-legend-modern">
                                    <div class="legend-item">
                                        <div class="legend-color today"></div>
                                        <span>Hari Ini</span>
                                    </div>
                                    <div class="legend-item">
                                        <div
                                            class="legend-color cuti-bersama"
                                        ></div>
                                        <span>Cuti Bersama</span>
                                    </div>
                                    <div class="legend-item">
                                        <div
                                            class="legend-color cuti-pribadi"
                                        ></div>
                                        <span>Cuti Pribadi</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Riwayat -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            title="Detail Pengajuan Cuti"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="selectedRiwayat" class="detail-content">
                    <div class="detail-section">
                        <div class="detail-row">
                            <div class="detail-label">Jenis Cuti</div>
                            <div class="detail-value">
                                {{ selectedRiwayat.jenis_cuti }}
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Periode</div>
                            <div class="detail-value">
                                {{ formatDate(selectedRiwayat.tanggal_mulai) }}
                                -
                                {{
                                    formatDate(selectedRiwayat.tanggal_selesai)
                                }}
                                ({{ selectedRiwayat.jumlah_hari }} hari)
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <Badge
                                    :type="
                                        getStatusBadgeType(
                                            selectedRiwayat.status
                                        )
                                    "
                                >
                                    {{ getStatusText(selectedRiwayat.status) }}
                                </Badge>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Alasan</div>
                            <div class="detail-value">
                                {{ selectedRiwayat.alasan }}
                            </div>
                        </div>
                        <div v-if="selectedRiwayat.catatan" class="detail-row">
                            <div class="detail-label">Catatan</div>
                            <div class="detail-value">
                                {{ selectedRiwayat.catatan }}
                            </div>
                        </div>
                        <div
                            v-if="selectedRiwayat.file_pendukung"
                            class="detail-row"
                        >
                            <div class="detail-label">File Pendukung</div>
                            <div class="detail-value">
                                <a
                                    :href="selectedRiwayat.file_pendukung"
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
import Table from "../../../../components/ui/table.vue";
import Badge from "../../../../components/ui/badge.vue";
import DateTimePicker from "../../../../components/ui/DatePicker.vue";
import quickStat from "@/components/ui/quickStat.vue";
import vSelect from "vue-select";
export default {
    name: "KaryawanCutiOptimized",
    components: {
        ApprovalTabs,
        HeaderKpi,
        Modal,
        Table,
        Badge,
        DateTimePicker,
        vSelect,
        quickStat,
    },
    data() {
        return {
            dateRange: null,
            currentDate: new Date(),
            activeTab: "ajukan",
            calendarView: "month",
            searchQuery: "",
            statusFilter: "",

            // Form data
            pengajuanForm: {
                jenis_cuti_id: "",
                tanggal_mulai: null,
                tanggal_selesai: null,
                jumlah_hari: 0,
                alasan: "",
                file_pendukung: null,
            },

            // Data
            jenisCutiList: [],
            riwayatList: [],
            saldoCuti: [],
            saldoDetail: [],
            calendarEvents: [],

            // UI state
            saldoWarning: "",
            cutiBersamaInfo: "",

            // Calendar
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),

            // Modals
            showDetailModal: false,
            selectedRiwayat: null,
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },

        quickStatField() {
            return [
                {
                    title: "Saldo Cuti",
                    value: this.totalCutiTersedia,
                    unit: "",
                    icon: "bi bi-people",
                    bgIcon: "bg-success",
                    size: "col-4",
                },
                {
                    title: "Hutang Cuti",
                    value: this.totalCutiTersedia,
                    unit: "",
                    icon: "bi bi-file-earmark",
                    bgIcon: "bg-danger",
                    size: "col-4",
                },
                {
                    title: "Pengajuan",
                    value: this.riwayatList.length,
                    unit: "",
                    icon: "bi bi-check-circle",
                    bgIcon: "bg-warning",
                    size: "col-4",
                },
            ];
        },

        karyawanTabs() {
            return [
                {
                    id: "ajukan",
                    label: "Ajukan Cuti",
                    icon: "bi bi-send-plus",
                    count: this.getPendingCount(),
                },
                {
                    id: "riwayat",
                    label: "Riwayat",
                    icon: "bi bi-clock-history",
                    count: this.riwayatList.length,
                },
                {
                    id: "saldo",
                    label: "Saldo Saya",
                    icon: "bi bi-wallet2",
                    count: this.saldoCuti.length,
                },
                {
                    id: "kalender",
                    label: "Kalender",
                    icon: "bi bi-calendar",
                    count: this.currentMonthEvents.length,
                },
            ];
        },

        filteredJenisCuti() {
            return this.jenisCutiList.map((jenis) => ({
                value: jenis.id,
                label: jenis.nama,
            }));
        },

        filteredRiwayat() {
            let filtered = this.riwayatList;
            if (this.statusFilter) {
                filtered = filtered.filter(
                    (item) => item.status === this.statusFilter
                );
            }
            return filtered;
        },

        isFormValid() {
            return (
                this.pengajuanForm.jenis_cuti_id &&
                this.pengajuanForm.tanggal_mulai &&
                this.pengajuanForm.tanggal_selesai &&
                this.pengajuanForm.jumlah_hari > 0 &&
                this.pengajuanForm.alasan &&
                !this.saldoWarning
            );
        },

        // Calendar computations
        weekdays() {
            return ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];
        },

        currentMonthYear() {
            return new Date(
                this.currentYear,
                this.currentMonth
            ).toLocaleDateString("id-ID", {
                month: "long",
                year: "numeric",
            });
        },

        calendarDays() {
            const days = [];
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(
                this.currentYear,
                this.currentMonth + 1,
                0
            );
            const startDay = firstDay.getDay();
            const daysInMonth = lastDay.getDate();

            // Previous month days
            const prevMonthLastDay = new Date(
                this.currentYear,
                this.currentMonth,
                0
            ).getDate();
            for (let i = startDay - 1; i >= 0; i--) {
                days.push({
                    day: prevMonthLastDay - i,
                    date: new Date(
                        this.currentYear,
                        this.currentMonth - 1,
                        prevMonthLastDay - i
                    ),
                    isCurrentMonth: false,
                    isToday: false,
                    isCutiBersama: false,
                    isCutiPribadi: false,
                });
            }

            // Current month days
            const today = new Date();
            for (let i = 1; i <= daysInMonth; i++) {
                const date = new Date(this.currentYear, this.currentMonth, i);
                const isToday = date.toDateString() === today.toDateString();
                const isCutiBersama = this.isCutiBersama(date);
                const isCutiPribadi = this.isCutiPribadi(date);

                days.push({
                    day: i,
                    date: date,
                    isCurrentMonth: true,
                    isToday: isToday,
                    isCutiBersama: isCutiBersama,
                    isCutiPribadi: isCutiPribadi,
                });
            }

            // Next month days
            const totalCells = 42;
            const nextMonthDays = totalCells - days.length;
            for (let i = 1; i <= nextMonthDays; i++) {
                days.push({
                    day: i,
                    date: new Date(this.currentYear, this.currentMonth + 1, i),
                    isCurrentMonth: false,
                    isToday: false,
                    isCutiBersama: false,
                    isCutiPribadi: false,
                });
            }

            return days;
        },

        currentMonthEvents() {
            return this.calendarEvents.filter((event) => {
                const eventDate = new Date(event.tanggal_mulai);
                return (
                    eventDate.getMonth() === this.currentMonth &&
                    eventDate.getFullYear() === this.currentYear
                );
            });
        },

        // Summary computations
        totalCutiTersedia() {
            return this.saldoDetail.reduce(
                (total, saldo) => total + saldo.sisa,
                0
            );
        },

        totalCutiDigunakan() {
            return this.saldoDetail.reduce(
                (total, saldo) => total + saldo.digunakan,
                0
            );
        },

        totalCutiKedaluwarsa() {
            const now = new Date();
            return this.saldoDetail
                .filter(
                    (saldo) =>
                        saldo.expired_date && new Date(saldo.expired_date) > now
                )
                .reduce((total, saldo) => total + saldo.sisa, 0);
        },
    },
    methods: {
        handleRangeChange(range) {
            console.log("Range selected:", range);
            this.dateRange = range;
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
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });
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

        getSisaCuti(jenisCutiId) {
            const saldo = this.saldoCuti.find(
                (s) => s.jenis_cuti_id === jenisCutiId
            );
            return saldo ? saldo.sisa : 0;
        },

        getCutiTypeIcon(jenisNama) {
            const icons = {
                Tahunan: "bi bi-calendar-heart",
                Sakit: "bi bi-heart-pulse",
                Melahirkan: "bi bi-gender-female",
                Menikah: "bi bi-heart",
                Keluarga: "bi bi-people",
                Izin: "bi bi-clock",
                Lainnya: "bi bi-three-dots",
            };

            for (const [key, icon] of Object.entries(icons)) {
                if (jenisNama.toLowerCase().includes(key.toLowerCase())) {
                    return icon;
                }
            }
            return "bi bi-calendar";
        },

        calculateProgress(saldo) {
            return (saldo.digunakan / saldo.kuota) * 100 || 0;
        },

        getProgressClass(saldo) {
            const percentage = this.calculateProgress(saldo);
            if (percentage < 30) return "safe";
            if (percentage < 70) return "warning";
            return "danger";
        },

        getStatusCount(status) {
            return this.riwayatList.filter((item) => item.status === status)
                .length;
        },

        getPendingCount() {
            return this.riwayatList.filter((item) => item.status === "pending")
                .length;
        },

        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.loadTabData();
        },

        selectCutiType(jenis) {
            this.pengajuanForm.jenis_cuti_id = jenis.id;
            this.checkSaldo();
        },

        // File upload methods
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    alert("File terlalu besar. Maksimal 2MB.");
                    event.target.value = "";
                    return;
                }
                this.pengajuanForm.file_pendukung = file;
            }
        },

        removeFile() {
            this.pengajuanForm.file_pendukung = null;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = "";
            }
        },

        // Calculation methods
        calculateHari() {
            if (
                this.pengajuanForm.tanggal_mulai &&
                this.pengajuanForm.tanggal_selesai
            ) {
                const start = new Date(this.pengajuanForm.tanggal_mulai);
                const end = new Date(this.pengajuanForm.tanggal_selesai);
                const diffTime = Math.abs(end - start);
                const diffDays =
                    Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                this.pengajuanForm.jumlah_hari = diffDays;

                this.checkOverlapCutiBersama(start, end);
                this.checkSaldo();
            }
        },

        async checkSaldo() {
            if (
                this.pengajuanForm.jenis_cuti_id &&
                this.pengajuanForm.jumlah_hari > 0
            ) {
                try {
                    // Simulasi API call dengan data dummy
                    await new Promise((resolve) => setTimeout(resolve, 300));

                    const jenisCuti = this.jenisCutiList.find(
                        (j) => j.id === this.pengajuanForm.jenis_cuti_id
                    );
                    const saldo = this.saldoCuti.find(
                        (s) =>
                            s.jenis_cuti_id === this.pengajuanForm.jenis_cuti_id
                    );

                    if (saldo && this.pengajuanForm.jumlah_hari <= saldo.sisa) {
                        this.saldoWarning = "";
                    } else {
                        const sisa = saldo ? saldo.sisa : 0;
                        this.saldoWarning = `Saldo tidak cukup. Sisa cuti ${jenisCuti?.nama}: ${sisa} ${jenisCuti?.satuan}, butuh: ${this.pengajuanForm.jumlah_hari} ${jenisCuti?.satuan}`;
                    }
                } catch (error) {
                    console.error("Error checking saldo:", error);
                }
            }
        },

        async checkOverlapCutiBersama(start, end) {
            try {
                // Simulasi API call dengan data dummy
                await new Promise((resolve) => setTimeout(resolve, 200));

                const cutiBersamaEvents = this.calendarEvents.filter(
                    (event) => event.tipe === "bersama"
                );
                const overlappingEvents = cutiBersamaEvents.filter((event) => {
                    const eventStart = new Date(event.tanggal_mulai);
                    const eventEnd = new Date(event.tanggal_selesai);
                    return start <= eventEnd && end >= eventStart;
                });

                if (overlappingEvents.length > 0) {
                    this.cutiBersamaInfo = `Periode cuti Anda tumpang tindih dengan cuti bersama: ${overlappingEvents
                        .map((e) => e.judul)
                        .join(", ")}`;
                } else {
                    this.cutiBersamaInfo = "";
                }
            } catch (error) {
                console.error("Error checking cuti bersama:", error);
            }
        },

        resetForm() {
            this.pengajuanForm = {
                jenis_cuti_id: "",
                tanggal_mulai: null,
                tanggal_selesai: null,
                jumlah_hari: 0,
                alasan: "",
                file_pendukung: null,
            };
            this.saldoWarning = "";
            this.cutiBersamaInfo = "";
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = "";
            }
        },

        async submitPengajuan() {
            try {
                // Simulasi API call
                await new Promise((resolve) => setTimeout(resolve, 1500));

                // Tambah ke riwayat lokal
                const jenisCuti = this.jenisCutiList.find(
                    (j) => j.id === this.pengajuanForm.jenis_cuti_id
                );
                const newPengajuan = {
                    id: Date.now(),
                    jenis_cuti: jenisCuti?.nama || "Cuti",
                    tanggal_mulai: this.pengajuanForm.tanggal_mulai,
                    tanggal_selesai: this.pengajuanForm.tanggal_selesai,
                    jumlah_hari: this.pengajuanForm.jumlah_hari,
                    alasan: this.pengajuanForm.alasan,
                    status: "pending",
                    created_at: new Date().toISOString().split("T")[0],
                    file_pendukung: this.pengajuanForm.file_pendukung
                        ? "/files/" + this.pengajuanForm.file_pendukung.name
                        : null,
                };

                this.riwayatList.unshift(newPengajuan);

                alert("Pengajuan cuti berhasil dikirim");
                this.resetForm();
                this.activeTab = "riwayat";
            } catch (error) {
                console.error("Error submitting pengajuan:", error);
                alert("Gagal mengajukan cuti. Silakan coba lagi.");
            }
        },

        // Calendar methods
        prevMonth() {
            if (this.currentMonth === 0) {
                this.currentMonth = 11;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
            this.loadCalendarEvents();
        },

        nextMonth() {
            if (this.currentMonth === 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
            this.loadCalendarEvents();
        },

        isCutiBersama(date) {
            return this.calendarEvents.some(
                (event) =>
                    event.tipe === "bersama" &&
                    new Date(event.tanggal_mulai) <= date &&
                    new Date(event.tanggal_selesai) >= date
            );
        },

        isCutiPribadi(date) {
            return this.calendarEvents.some(
                (event) =>
                    event.tipe === "pribadi" &&
                    new Date(event.tanggal_mulai) <= date &&
                    new Date(event.tanggal_selesai) >= date
            );
        },

        selectDate(day) {
            if (day.isCurrentMonth) {
                // Handle date selection for calendar
                console.log("Selected date:", day.date);
            }
        },

        isDateSelected(date) {
            // Implementation for date selection
            return false;
        },

        formatEventDay(date) {
            return new Date(date).getDate();
        },

        formatEventMonth(date) {
            return new Date(date).toLocaleDateString("id-ID", {
                month: "short",
            });
        },

        // API Methods dengan Data Dummy
        async loadTabData() {
            try {
                switch (this.activeTab) {
                    case "ajukan":
                        await this.loadJenisCuti();
                        await this.loadSaldoCuti();
                        break;
                    case "riwayat":
                        await this.loadRiwayat();
                        break;
                    case "saldo":
                        await this.loadSaldoDetail();
                        break;
                    case "kalender":
                        await this.loadCalendarEvents();
                        break;
                }
            } catch (error) {
                console.error("Error loading data:", error);
            }
        },

        async loadJenisCuti() {
            // Data dummy jenis cuti
            const dummyJenisCuti = [
                {
                    id: 1,
                    nama: "Cuti Tahunan",
                    deskripsi: "Cuti tahunan untuk karyawan tetap",
                    kuota_default: 12,
                    satuan: "hari",
                    masa_berlaku_bulan: 12,
                    dapat_diakumulasi: true,
                    penggunaan_langsung: false,
                    minimal_masa_kerja: 12,
                },
                {
                    id: 2,
                    nama: "Cuti Sakit",
                    deskripsi: "Cuti untuk karyawan yang sakit",
                    kuota_default: 30,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 0,
                },
                {
                    id: 3,
                    nama: "Cuti Melahirkan",
                    deskripsi: "Cuti untuk karyawan yang melahirkan",
                    kuota_default: 90,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 12,
                },
                {
                    id: 4,
                    nama: "Cuti Menikah",
                    deskripsi: "Cuti untuk pernikahan sendiri",
                    kuota_default: 3,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 3,
                },
                {
                    id: 5,
                    nama: "Cuti Keluarga",
                    deskripsi: "Cuti untuk keperluan keluarga",
                    kuota_default: 2,
                    satuan: "hari",
                    masa_berlaku_bulan: null,
                    dapat_diakumulasi: false,
                    penggunaan_langsung: true,
                    minimal_masa_kerja: 6,
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 500));
            this.jenisCutiList = dummyJenisCuti;
        },

        async loadSaldoCuti() {
            // Data dummy saldo cuti
            const dummySaldoCuti = [
                {
                    jenis_cuti_id: 1,
                    nama_jenis: "Cuti Tahunan",
                    sisa: 8,
                    kuota: 12,
                    satuan: "hari",
                },
                {
                    jenis_cuti_id: 2,
                    nama_jenis: "Cuti Sakit",
                    sisa: 25,
                    kuota: 30,
                    satuan: "hari",
                },
                {
                    jenis_cuti_id: 3,
                    nama_jenis: "Cuti Melahirkan",
                    sisa: 90,
                    kuota: 90,
                    satuan: "hari",
                },
                {
                    jenis_cuti_id: 4,
                    nama_jenis: "Cuti Menikah",
                    sisa: 3,
                    kuota: 3,
                    satuan: "hari",
                },
                {
                    jenis_cuti_id: 5,
                    nama_jenis: "Cuti Keluarga",
                    sisa: 2,
                    kuota: 2,
                    satuan: "hari",
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 400));
            this.saldoCuti = dummySaldoCuti;
        },

        async loadSaldoDetail() {
            // Data dummy saldo detail
            const dummySaldoDetail = [
                {
                    id: 1,
                    nama_jenis: "Cuti Tahunan",
                    deskripsi: "Cuti tahunan untuk karyawan tetap",
                    sisa: 8,
                    digunakan: 4,
                    kuota: 12,
                    satuan: "hari",
                    persentase: 33,
                    expired_date: "2024-12-31",
                },
                {
                    id: 2,
                    nama_jenis: "Cuti Sakit",
                    deskripsi: "Cuti untuk karyawan yang sakit",
                    sisa: 25,
                    digunakan: 5,
                    kuota: 30,
                    satuan: "hari",
                    persentase: 17,
                    expired_date: null,
                },
                {
                    id: 3,
                    nama_jenis: "Cuti Melahirkan",
                    deskripsi: "Cuti untuk karyawan yang melahirkan",
                    sisa: 90,
                    digunakan: 0,
                    kuota: 90,
                    satuan: "hari",
                    persentase: 0,
                    expired_date: null,
                },
                {
                    id: 4,
                    nama_jenis: "Cuti Menikah",
                    deskripsi: "Cuti untuk pernikahan sendiri",
                    sisa: 3,
                    digunakan: 0,
                    kuota: 3,
                    satuan: "hari",
                    persentase: 0,
                    expired_date: null,
                },
                {
                    id: 5,
                    nama_jenis: "Cuti Keluarga",
                    deskripsi: "Cuti untuk keperluan keluarga",
                    sisa: 2,
                    digunakan: 0,
                    kuota: 2,
                    satuan: "hari",
                    persentase: 0,
                    expired_date: null,
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 400));
            this.saldoDetail = dummySaldoDetail;
        },

        async loadRiwayat() {
            // Data dummy riwayat cuti
            const dummyRiwayat = [
                {
                    id: 1,
                    jenis_cuti: "Cuti Tahunan",
                    tanggal_mulai: "2024-01-15",
                    tanggal_selesai: "2024-01-20",
                    jumlah_hari: 6,
                    alasan: "Liburan keluarga ke Bali",
                    status: "approved",
                    created_at: "2024-01-10",
                    file_pendukung: null,
                    catatan: "Disetujui oleh HRD",
                },
                {
                    id: 2,
                    jenis_cuti: "Cuti Sakit",
                    tanggal_mulai: "2024-01-08",
                    tanggal_selesai: "2024-01-09",
                    jumlah_hari: 2,
                    alasan: "Demam tinggi dan flu",
                    status: "approved",
                    created_at: "2024-01-07",
                    file_pendukung: "/files/surat-dokter.pdf",
                    catatan: "Lampiran surat dokter sudah diterima",
                },
                {
                    id: 3,
                    jenis_cuti: "Cuti Tahunan",
                    tanggal_mulai: "2024-02-01",
                    tanggal_selesai: "2024-02-05",
                    jumlah_hari: 5,
                    alasan: "Perjalanan wisata ke Jogja",
                    status: "pending",
                    created_at: "2024-01-25",
                    file_pendukung: null,
                    catatan: null,
                },
                {
                    id: 4,
                    jenis_cuti: "Cuti Keluarga",
                    tanggal_mulai: "2023-12-20",
                    tanggal_selesai: "2023-12-21",
                    jumlah_hari: 2,
                    alasan: "Menghadiri pernikahan saudara",
                    status: "approved",
                    created_at: "2023-12-15",
                    file_pendukung: "/files/undangan.pdf",
                    catatan: "Disetujui atasan langsung",
                },
                {
                    id: 5,
                    jenis_cuti: "Cuti Tahunan",
                    tanggal_mulai: "2023-11-10",
                    tanggal_selesai: "2023-11-15",
                    jumlah_hari: 6,
                    alasan: "Cuti mendadak untuk urusan keluarga",
                    status: "rejected",
                    created_at: "2023-11-05",
                    file_pendukung: null,
                    catatan:
                        "Ditolak karena bentrok dengan jadwal proyek penting",
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 600));
            this.riwayatList = dummyRiwayat;
        },

        async loadCalendarEvents() {
            // Data dummy calendar events
            const dummyCalendarEvents = [
                {
                    id: 1,
                    judul: "Libur Idul Fitri",
                    deskripsi: "Cuti bersama hari raya Idul Fitri",
                    tanggal_mulai: "2024-04-08",
                    tanggal_selesai: "2024-04-15",
                    tipe: "bersama",
                },
                {
                    id: 2,
                    judul: "Cuti Pribadi - Liburan",
                    deskripsi: "Cuti pribadi untuk liburan keluarga",
                    tanggal_mulai: "2024-01-15",
                    tanggal_selesai: "2024-01-20",
                    tipe: "pribadi",
                },
                {
                    id: 3,
                    judul: "Cuti Sakit",
                    deskripsi: "Cuti sakit karena demam",
                    tanggal_mulai: "2024-01-08",
                    tanggal_selesai: "2024-01-09",
                    tipe: "pribadi",
                },
                {
                    id: 4,
                    judul: "Libur Natal & Tahun Baru",
                    deskripsi: "Cuti bersama Natal dan Tahun Baru",
                    tanggal_mulai: "2024-12-23",
                    tanggal_selesai: "2024-12-31",
                    tipe: "bersama",
                },
                {
                    id: 5,
                    judul: "Libur Hari Kemerdekaan",
                    deskripsi: "Cuti bersama Hari Kemerdekaan RI",
                    tanggal_mulai: "2024-08-17",
                    tanggal_selesai: "2024-08-17",
                    tipe: "bersama",
                },
                {
                    id: 6,
                    judul: "Cuti Tahunan",
                    deskripsi: "Cuti pribadi untuk refreshing",
                    tanggal_mulai: "2024-02-01",
                    tanggal_selesai: "2024-02-05",
                    tipe: "pribadi",
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 400));
            this.calendarEvents = dummyCalendarEvents;
        },

        refreshData() {
            this.loadTabData();
            alert("Data berhasil diperbarui");
        },

        // Action methods
        viewRiwayatDetail(riwayat) {
            this.selectedRiwayat = riwayat;
            this.showDetailModal = true;
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedRiwayat = null;
        },

        async cancelPengajuan(pengajuan) {
            if (confirm("Batalkan pengajuan cuti ini?")) {
                try {
                    // Simulasi API call
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    // Update status di data lokal
                    const index = this.riwayatList.findIndex(
                        (r) => r.id === pengajuan.id
                    );
                    if (index !== -1) {
                        this.riwayatList[index].status = "cancelled";
                        this.riwayatList[index].catatan =
                            "Dibatalkan oleh karyawan";
                    }

                    alert("Pengajuan cuti berhasil dibatalkan");
                } catch (error) {
                    console.error("Error cancelling pengajuan:", error);
                    alert("Gagal membatalkan pengajuan");
                }
            }
        },
    },
    mounted() {
        this.loadTabData();
    },
    watch: {
        activeTab() {
            this.loadTabData();
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

.form-group-optimized {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label-optimized {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-input-optimized,
.form-select-optimized,
.form-textarea-optimized {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
    color: var(--text);
    width: 100%;
}
/*
.vs__dropdown-toggle {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    font-size: 0.9rem !important;
    transition: all 0.3s ease !important;
    background: var(--surface) !important;
    color: var(--text) !important;
    width: 100%;
}
/*
.v-select .vs__dropdown-toggle {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
} */
:deep(.vs__dropdown-toggle) {
    padding: 0.62rem !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    font-size: 0.9rem !important;
}
:deep(.v-select .vs__dropdown-toggle),
:deep(.v-select .vs__selected),
:deep(.v-select .vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
}

.form-input-optimized:focus,
.form-select-optimized:focus,
.form-textarea-optimized:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.form-textarea-optimized {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.weight-control-optimized {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.weight-slider-optimized {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: var(--border);
    outline: none;
    -webkit-appearance: none;
}

.weight-slider-optimized::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.weight-value {
    min-width: 50px;
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.loading-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
    margin-top: 0.25rem;
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
/* Improved Tab Content */
.tab-content-improved {
    padding: 0;
}

/* Reset dan styling komprehensif */
:deep(.v-select) {
    width: 100%;
    font-family: inherit;
}

:deep(.vs__dropdown-toggle) {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 14px;
    transition: all 0.3s ease;
    min-height: 48px;
    display: flex;
    align-items: center;
}

:deep(.vs__selected) {
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
}

:deep(.vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
}

:deep(.vs__search::placeholder) {
    color: var(--text);
    opacity: 0.6;
}

:deep(.vs__selected-options) {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
}

:deep(.vs__actions) {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Focus states */
:deep(.v-select.focused .vs__dropdown-toggle),
:deep(.vs--open .vs__dropdown-toggle) {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Dropdown menu */
:deep(.vs__dropdown-menu) {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 10px;
    margin-top: 4px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

:deep(.vs__dropdown-option) {
    padding: 0.75rem 1rem;
    color: var(--text);
    font-size: 14px;
}

:deep(.vs__dropdown-option--highlight) {
    background: var(--primary);
    color: white;
}

/* Modern Form Styles */
.form-section-modern {
    background: var(--surface);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid var(--border);
}

.form-title-modern {
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    font-size: 1.25rem;
}

/* Cuti Type Cards */
.cuti-type-cards {
    margin-bottom: 2rem;
}

.cuti-type-card {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    height: 100%;
}

.cuti-type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.cuti-type-card.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.cuti-type-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    color: var(--primary);
    font-size: 1.5rem;
}

.cuti-type-content h6 {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.cuti-type-check {
    position: absolute;
    top: 1rem;
    right: 1rem;
    color: var(--success);
    font-size: 1.25rem;
}

/* Modern Form Elements */
.modern-form {
    margin-top: 2rem;
}

.form-group-modern {
    margin-bottom: 1.5rem;
}

.form-label-modern {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-control-modern {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Duration Display */
.duration-display {
    margin: 1.5rem 0;
}

.duration-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.duration-card i {
    font-size: 2rem;
}

.duration-content {
    flex: 1;
}

.duration-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.9;
}

.duration-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
}

/* File Upload */
.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 10px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--surface-soft);
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.file-upload-area i {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.file-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.btn-remove-file {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    margin-left: auto;
}

/* Form Actions */
.form-actions-modern {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

/* Info Sidebar */
.info-sidebar-modern {
    position: sticky;
    top: 1rem;
}

.info-card-modern {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
}

.info-title-modern {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

/* Saldo Summary */
.saldo-summary {
    background: linear-gradient(135deg, var(--surface-soft), #f8fafc);
}

.saldo-chart {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.saldo-progress-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.saldo-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.saldo-name {
    font-weight: 500;
    color: var(--text);
}

.saldo-numbers {
    font-weight: 600;
    color: var(--primary);
}

.progress-bar-modern {
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-fill.safe {
    background: var(--success);
}

.progress-fill.warning {
    background: var(--warning);
}

.progress-fill.danger {
    background: var(--danger);
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 1.25rem;
    color: var(--primary);
}

/* Stats Cards */
.stats-cards {
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-card.total .stat-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.stat-card.approved .stat-icon {
    background: linear-gradient(135deg, var(--success), #059669);
}

.stat-card.pending .stat-icon {
    background: linear-gradient(135deg, var(--warning), #d97706);
}

.stat-card.rejected .stat-icon {
    background: linear-gradient(135deg, var(--danger), #dc2626);
}

.stat-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.stat-content p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Riwayat List */
.riwayat-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.riwayat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.riwayat-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.riwayat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.riwayat-type {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    color: var(--dark);
}

.riwayat-content {
    margin-bottom: 1rem;
}

.riwayat-dates {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    color: var(--text);
}

.riwayat-dates .duration {
    color: var(--primary);
    font-weight: 600;
}

.riwayat-reason p {
    margin: 0;
    color: var(--text-muted);
    line-height: 1.5;
}

.riwayat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.riwayat-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Empty State */
.empty-state-modern {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state-modern i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-modern h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-state-modern p {
    margin-bottom: 2rem;
}

/* Saldo Cards */
.saldo-cards-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.saldo-card-detailed {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.saldo-card-detailed:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.saldo-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.saldo-icon {
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

.saldo-info {
    flex: 1;
}

.saldo-info h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.saldo-info p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

.saldo-badge {
    background: var(--primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.saldo-progress-detailed {
    margin-bottom: 1rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: var(--text);
}

.progress-bar-detailed {
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-percentage {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.saldo-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    color: var(--warning);
    font-size: 0.85rem;
}

/* Summary Sidebar */
.summary-sidebar {
    position: sticky;
    top: 1rem;
}

.summary-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
}

.summary-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.summary-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.summary-stat {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 10px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-icon.total {
    background: var(--primary);
}

.stat-icon.used {
    background: var(--success);
}

.stat-icon.expiring {
    background: var(--warning);
}

.stat-content {
    flex: 1;
}

.stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.tips-card {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.tips-title {
    font-weight: 600;
    color: #856404;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.tips-content {
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
    color: #d97706;
    margin-top: 0.125rem;
}

.tip-item p {
    margin: 0;
    color: #856404;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Calendar Modern */
.calendar-container-modern {
    background: var(--surface);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid var(--border);
}

.calendar-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.btn-calendar-nav {
    width: 40px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-calendar-nav:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.calendar-title {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    min-width: 200px;
    text-align: center;
}

.calendar-views {
    display: flex;
    gap: 0.5rem;
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 0.25rem;
}

.view-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: var(--text-muted);
}

.view-btn.active {
    background: var(--primary);
    color: white;
}

/* Calendar Month View */
.calendar-month-view {
    margin-bottom: 2rem;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.weekday {
    text-align: center;
    font-weight: 600;
    color: var(--text-muted);
    padding: 0.75rem;
    font-size: 0.9rem;
}

.calendar-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
}

.calendar-day {
    aspect-ratio: 1;
    border: 1px solid var(--border);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.calendar-day:hover {
    background: var(--surface-soft);
}

.calendar-day.today {
    background: var(--primary-light);
    border-color: var(--primary);
}

.calendar-day.cuti-bersama {
    background: rgba(6, 182, 212, 0.1);
    border-color: rgba(6, 182, 212, 0.3);
}

.calendar-day.cuti-pribadi {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.calendar-day.selected {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.calendar-day.other-month {
    opacity: 0.3;
}

.day-number {
    font-weight: 600;
    font-size: 0.9rem;
}

.day-events {
    position: absolute;
    bottom: 4px;
    display: flex;
    gap: 2px;
}

.event-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.event-dot.bersama {
    background: var(--info);
}

.event-dot.pribadi {
    background: var(--success);
}

/* Calendar List View */
.events-list-modern {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.event-item-modern {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.event-item-modern:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.event-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 0.75rem;
}

.event-day {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

.event-month {
    font-size: 0.8rem;
    color: var(--text-muted);
    text-transform: uppercase;
}

.event-content {
    flex: 1;
}

.event-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.event-description {
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.event-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.event-type {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.event-type.bersama {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info);
}

.event-type.pribadi {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.event-duration {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Calendar Legend */
.calendar-legend-modern {
    display: flex;
    justify-content: center;
    gap: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.legend-color.today {
    background: var(--primary-light);
}

.legend-color.cuti-bersama {
    background: rgba(6, 182, 212, 0.3);
}

.legend-color.cuti-pribadi {
    background: rgba(16, 185, 129, 0.3);
}

/* Alert Modern */
.alert-modern {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 8px;
    background: var(--surface-soft);
}

.alert-modern.info {
    background: rgba(6, 182, 212, 0.1);
    border-left: 4px solid var(--info);
}

.alert-modern i {
    color: var(--info);
    margin-top: 0.125rem;
}

.alert-modern p {
    margin: 0;
    color: var(--text);
    line-height: 1.5;
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-section-modern {
        padding: 1.5rem;
    }

    .cuti-type-card {
        padding: 1rem;
    }

    .duration-card {
        padding: 1rem;
        flex-direction: column;
        text-align: center;
    }

    .form-actions-modern {
        flex-direction: column;
    }

    .stats-cards .row {
        grid-template-columns: repeat(2, 1fr);
    }

    .calendar-header-modern {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .calendar-nav {
        justify-content: center;
    }

    .calendar-views {
        justify-content: center;
    }

    .quick-actions {
        grid-template-columns: 1fr;
    }

    .riwayat-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .event-item-modern {
        flex-direction: column;
    }

    .event-date {
        flex-direction: row;
        gap: 1rem;
        min-width: auto;
    }

    .calendar-legend-modern {
        flex-wrap: wrap;
        gap: 1rem;
    }
}

@media (max-width: 576px) {
    .stats-cards .row {
        grid-template-columns: 1fr;
    }

    .calendar-weekdays,
    .calendar-days-grid {
        gap: 0.25rem;
    }

    .weekday,
    .calendar-day {
        font-size: 0.8rem;
    }

    .saldo-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .saldo-badge {
        align-self: flex-start;
    }
}
</style>
