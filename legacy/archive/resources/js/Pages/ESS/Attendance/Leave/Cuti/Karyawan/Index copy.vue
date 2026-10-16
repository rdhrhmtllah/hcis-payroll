<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <HeaderKpi
            title="Cuti Saya"
            subtitle="Kelola pengajuan cuti dan lihat riwayat"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-lg-11">
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
                                    <div class="form-section-modern">
                                        <h5 class="form-title-modern">
                                            <i class="bi bi-send-plus me-2"></i>
                                            Ajukan Cuti Baru
                                        </h5>

                                        <!-- Jenis Cuti Cards -->
                                        <div class="cuti-type-cards mb-4">
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
                                        </div>

                                        <!-- Form Pengajuan -->
                                        <form
                                            @submit.prevent="submitPengajuan"
                                            class="modern-form"
                                        >
                                            <div class="row g-3">
                                                <div class="col-md-6">
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
                                                </div>

                                                <div class="col-12">
                                                    <div
                                                        class="duration-display"
                                                    >
                                                        <div
                                                            class="duration-card"
                                                        >
                                                            <i
                                                                class="bi bi-clock"
                                                            ></i>
                                                            <div
                                                                class="duration-content"
                                                            >
                                                                <span
                                                                    class="duration-label"
                                                                    >Durasi
                                                                    Cuti</span
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
                                        <!-- Ringkasan Saldo -->
                                        <div
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
                                        </div>

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
            <!-- Modal content remains the same -->
        </Modal>
    </div>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import ApprovalTabs from "@/components/ui/toggleSectionV2.vue";
import HeaderKpi from "@/components/ui/header.vue";
import Modal from "@/components/ui/modalV2-noir.vue";
// import Table from "../../../../components/ui/table.vue";
// import Badge from "../../../../components/ui/badge.vue";
import DatePicker from "../../../../components/ui/DatePicker.vue";

export default {
    name: "KaryawanCutiOptimized",
    components: {
        ApprovalTabs,
        HeaderKpi,
        Modal,
        Table,
        Badge,
        DatePicker,
    },
    data() {
        return {
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
            if (!this.searchQuery) return this.jenisCutiList;
            return this.jenisCutiList.filter((jenis) =>
                jenis.nama
                    .toLowerCase()
                    .includes(this.searchQuery.toLowerCase())
            );
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
            // Implementation remains the same
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

        // Existing methods remain the same but optimized
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
                    const response = await axios.get("/api/leave-balance", {
                        params: {
                            jenis_cuti_id: this.pengajuanForm.jenis_cuti_id,
                            jumlah_hari: this.pengajuanForm.jumlah_hari,
                        },
                    });

                    if (response.data.saldo_cukup) {
                        this.saldoWarning = "";
                    } else {
                        this.saldoWarning = `Saldo tidak cukup. Sisa cuti: ${response.data.sisa_cuti} hari, butuh: ${this.pengajuanForm.jumlah_hari} hari`;
                    }
                } catch (error) {
                    console.error("Error checking saldo:", error);
                }
            }
        },

        async checkOverlapCutiBersama(start, end) {
            try {
                const response = await axios.get("/api/check-cuti-bersama", {
                    params: {
                        start: start.toISOString().split("T")[0],
                        end: end.toISOString().split("T")[0],
                    },
                });

                if (response.data.has_overlap) {
                    this.cutiBersamaInfo = `Periode cuti Anda tumpang tindih dengan cuti bersama: ${response.data.cuti_bersama_list.join(
                        ", "
                    )}`;
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
                const formData = new FormData();
                Object.keys(this.pengajuanForm).forEach((key) => {
                    if (this.pengajuanForm[key] !== null) {
                        formData.append(key, this.pengajuanForm[key]);
                    }
                });

                await axios.post("/transaksi-cuti", formData, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                });

                alert("Pengajuan cuti berhasil dikirim");
                this.resetForm();
                this.activeTab = "riwayat";
                this.loadRiwayat();
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

        // API Methods
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
            const response = await axios.get("/api/jenis-cuti");
            this.jenisCutiList = response.data;
        },

        async loadSaldoCuti() {
            const response = await axios.get("/api/leave-balance");
            this.saldoCuti = response.data;
        },

        async loadSaldoDetail() {
            const response = await axios.get("/api/leave-balance-detail");
            this.saldoDetail = response.data;
        },

        async loadRiwayat() {
            const response = await axios.get("/transaksi-cuti", {
                params: { my: true },
            });
            this.riwayatList = response.data.data;
        },

        async loadCalendarEvents() {
            const response = await axios.get("/api/calendar-events", {
                params: {
                    month: this.currentMonth + 1,
                    year: this.currentYear,
                },
            });
            this.calendarEvents = response.data;
        },

        refreshData() {
            this.loadTabData();
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
                    await axios.delete(`/transaksi-cuti/${pengajuan.id}`);
                    await this.loadRiwayat();
                } catch (error) {
                    console.error("Error cancelling pengajuan:", error);
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

<style scoped>
/* Improved Tab Content */
.tab-content-improved {
    padding: 0;
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
    margin-bottom: 2rem;
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
