<template>
    <div class="cuti-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Cuti Saya"
            subtitle="Kelola pengajuan cuti dan lihat riwayat"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs :tabs="karyawanTabs" :activeTab="activeTab" @tab-change="handleTabChange">
                    <!-- Tab Ajukan Cuti -->
                    <template #ajukan>
                        <div class="tab-content-wrapper">
                            <div class="form-layout">
                                <!-- Main Form -->
                                <div class="form-main-section">
                                    <div class="form-card">
                                        <div class="form-header">
                                            <div class="form-icon-wrapper">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-send-plus"
                                                ></i>
                                            </div>
                                            <h3 class="form-title">Ajukan Cuti Baru</h3>
                                        </div>

                                        <form @submit.prevent="submitPengajuan" class="modern-form">
                                            <!-- Jenis Cuti -->
                                            <div class="form-field-group">
                                                <label class="form-label">
                                                    <i class="bi bi-calendar form-label-icon"></i>
                                                    Pilih Jenis Cuti
                                                </label>
                                                <v-select
                                                    :options="filteredJenisCuti"
                                                    :searchable="true"
                                                    :filterable="true"
                                                    :clearable="false"
                                                    :disabled="
                                                        !filteredJenisCuti ||
                                                        filteredJenisCuti.length === 0 ||
                                                        loading.jenisCuti
                                                    "
                                                    label="label"
                                                    placeholder="Pilih Jenis Cuti..."
                                                    :reduce="(opt) => opt.value"
                                                    v-model="pengajuanForm.jenis_cuti_id"
                                                    @update:modelValue="selectCutiType"
                                                    :selectable="(option) => !option.disabled"
                                                    class="modern-select"
                                                />
                                                <small class="text-muted d-block mt-1">
                                                    Jenis cuti yang nonaktif berarti syarat belum terpenuhi (masa kerja
                                                    / saldo / jatah hutang habis).
                                                </small>
                                            </div>

                                            <!-- Periode Cuti -->
                                            <div class="form-field-group">
                                                <label class="form-label">
                                                    <i class="bi bi-calendar-range form-label-icon"></i>
                                                    Periode Cuti
                                                </label>
                                                <div @click="openDialog" class="date-picker-trigger">
                                                    <div
                                                        class="date-display"
                                                        :class="{
                                                            disabled: !selectedTypeCuti,
                                                        }"
                                                    >
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center bi-calendar-week"
                                                        ></i>
                                                        <span
                                                            v-if="pengajuanForm?.selectedDates?.length == 0"
                                                            class="placeholder"
                                                        >
                                                            Pilih tanggal cuti
                                                        </span>
                                                        <span v-else class="selected-dates">
                                                            {{
                                                                truncateText(
                                                                    formatHumanReadableDates(
                                                                        pengajuanForm.selectedDates,
                                                                    ),
                                                                    30,
                                                                )
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Alasan Cuti -->
                                            <div class="form-field-group">
                                                <label class="form-label">
                                                    <i class="bi bi-chat-text form-label-icon"></i>
                                                    Alasan Cuti
                                                </label>
                                                <textarea
                                                    v-model="pengajuanForm.alasan"
                                                    class="form-textarea"
                                                    rows="4"
                                                    placeholder="Jelaskan alasan pengajuan cuti..."
                                                    required
                                                ></textarea>
                                            </div>

                                            <!-- Dokumen Pendukung -->
                                            <div class="form-field-group">
                                                <label class="form-label">
                                                    <i class="bi bi-paperclip form-label-icon"></i>
                                                    Dokumen Pendukung
                                                    <span
                                                        class="requirement-badge"
                                                        :class="{
                                                            required: selectedTypeCuti?.dokumen_pendukung,
                                                        }"
                                                    >
                                                        {{ selectedTypeCuti?.dokumen_pendukung ? 'Wajib' : 'Opsional' }}
                                                    </span>
                                                </label>

                                                <div class="file-upload-container">
                                                    <!-- State: Belum ada file -->
                                                    <div
                                                        v-if="!pengajuanForm.file_pendukung"
                                                        class="file-upload-card"
                                                        @click="$refs.fileInput.click()"
                                                        @dragover.prevent
                                                        @drop="handleFileDrop"
                                                    >
                                                        <div class="upload-icon">
                                                            <i class="bi bi-cloud-arrow-up"></i>
                                                        </div>
                                                        <div class="upload-content">
                                                            <h4>Unggah Dokumen Pendukung</h4>
                                                            <p>Seret file ke sini atau klik untuk memilih</p>
                                                            <div class="file-requirements">
                                                                <span class="requirement-item">
                                                                    <i
                                                                        class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                                    ></i>
                                                                    Format: PDF, JPG, PNG
                                                                </span>
                                                                <span class="requirement-item">
                                                                    <i
                                                                        class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                                    ></i>
                                                                    Maksimal: 2MB
                                                                </span>
                                                                <span
                                                                    class="requirement-item"
                                                                    v-if="selectedTypeCuti?.dokumen_pendukung"
                                                                >
                                                                    <i
                                                                        class="bi d-flex justify-content-center align-items-center bi-exclamation-circle"
                                                                    ></i>
                                                                    Dokumen wajib untuk cuti ini
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <input
                                                            type="file"
                                                            ref="fileInput"
                                                            @change="handleFileUpload"
                                                            class="d-none"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                        />
                                                    </div>

                                                    <!-- State: Sudah ada file -->
                                                    <div v-else class="file-preview-card">
                                                        <div class="file-header">
                                                            <div class="file-info">
                                                                <div class="file-icon">
                                                                    <i
                                                                        class="d-flex justify-content-center align-items-center"
                                                                        :class="
                                                                            getFileIcon(pengajuanForm.file_pendukung)
                                                                        "
                                                                    ></i>
                                                                </div>
                                                                <div class="file-details">
                                                                    <h5 class="file-name">
                                                                        {{
                                                                            truncateText(
                                                                                getFileName(
                                                                                    pengajuanForm.file_pendukung,
                                                                                ),
                                                                                15,
                                                                            )
                                                                        }}
                                                                    </h5>
                                                                    <div class="file-meta">
                                                                        <span class="file-size">{{
                                                                            getFileSize(pengajuanForm.file_pendukung)
                                                                        }}</span>
                                                                        <span class="file-type">{{
                                                                            getFileType(pengajuanForm.file_pendukung)
                                                                        }}</span>
                                                                        <span class="upload-status">
                                                                            <i
                                                                                class="bi d-flex justify-content-center align-items-center bi-check-circle-fill"
                                                                            ></i>
                                                                            Siap diupload
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="file-actions">
                                                                <button
                                                                    type="button"
                                                                    @click="previewFile(pengajuanForm.file_pendukung)"
                                                                    class="btn-action btn-preview"
                                                                    title="Pratinjau File"
                                                                >
                                                                    <i
                                                                        class="bi d-flex justify-content-center align-items-center bi-eye"
                                                                    ></i>
                                                                    <!-- <span
                                                                        >Pratinjau</span
                                                                    > -->
                                                                </button>
                                                                <!-- <button
                                                                    type="button"
                                                                    @click="
                                                                        replaceFile
                                                                    "
                                                                    class="btn-action btn-replace"
                                                                    title="Ganti File"
                                                                >
                                                                    <i
                                                                        class="bi d-flex justify-content-center align-items-center bi-arrow-repeat"
                                                                    ></i>
                                                                    <span
                                                                        >Ganti</span
                                                                    >
                                                                </button> -->
                                                                <button
                                                                    type="button"
                                                                    @click="removeFile"
                                                                    class="btn-action btn-remove-action"
                                                                    title="Hapus File"
                                                                >
                                                                    <i
                                                                        class="bi d-flex justify-content-center align-items-center bi-trash"
                                                                    ></i>
                                                                    <!-- <span
                                                                        >Hapus</span
                                                                    > -->
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Progress bar (optional) -->
                                                        <div class="upload-progress" v-if="uploadProgress > 0">
                                                            <div class="progress-bar">
                                                                <div
                                                                    class="progress-fill"
                                                                    :style="{
                                                                        width: uploadProgress + '%',
                                                                    }"
                                                                ></div>
                                                            </div>
                                                            <span class="progress-text">{{ uploadProgress }}%</span>
                                                        </div>
                                                    </div>

                                                    <!-- Validation message -->
                                                    <div v-if="fileError" class="validation-error">
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                        ></i>
                                                        <span>{{ fileError }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Validation Messages -->
                                            <div v-if="validationErrors.length" class="validation-section">
                                                <div v-for="error in validationErrors" :key="error" class="alert error">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                    ></i>
                                                    <span>{{ error }}</span>
                                                </div>
                                            </div>

                                            <!-- Form Actions -->
                                            <div class="form-actions">
                                                <button type="button" class="btn btn-secondary" @click="resetForm">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-arrow-clockwise me-2"
                                                    ></i>
                                                    Reset
                                                </button>
                                                <button
                                                    type="submit"
                                                    class="btn btn-primary"
                                                    :disabled="isSubmitting || !canSubmitPengajuan"
                                                >
                                                    <span
                                                        v-if="isSubmitting"
                                                        class="spinner-border spinner-border-sm me-2"
                                                        role="status"
                                                        aria-hidden="true"
                                                    ></span>
                                                    <i
                                                        v-else
                                                        class="bi d-flex justify-content-center align-items-center bi-send me-2"
                                                    ></i>
                                                    {{ isSubmitting ? 'Mengajukan...' : 'Ajukan Cuti' }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Sidebar -->
                                <div class="form-sidebar">
                                    <!-- Semua kartu sidebar: hanya desktop; di mobile dipindah ke sticky bar + modal -->
                                    <div class="hutang-cards-desktop">
                                    <!-- Durasi Cuti -->
                                    <div class="cuti-duration-card cuti-highlight">
                                        <div class="cuti-info-header">
                                            <i class="bi d-flex justify-content-center align-items-center bi-clock"></i>
                                            <h4>Durasi Cuti</h4>
                                        </div>
                                        <div class="cuti-duration-display">
                                            <span class="cuti-duration-value">{{ pengajuanForm.jumlah_hari }}</span>
                                            <span class="cuti-duration-label">Hari</span>
                                        </div>

                                        <!-- Hutang Cuti Warning -->
                                        <div
                                            v-if="
                                                showHutangWarning &&
                                                pengajuanForm.jumlah_hari > 0 &&
                                                selectedTypeCuti &&
                                                selectedTypeCuti?.memotong_saldo == true
                                            "
                                            class="cuti-hutang-warning"
                                        >
                                            <div class="cuti-warning-header">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                ></i>
                                                <span>Perhitungan Hutang Cuti</span>
                                            </div>
                                            <div class="cuti-hutang-details">
                                                <div class="cuti-hutang-item">
                                                    <span class="cuti-label">Sisa Cuti:</span>
                                                    <span class="cuti-value">{{ sisaCuti }} hari</span>
                                                </div>
                                                <div
                                                    v-if="hutangLimitInfo.bisaHutang && hutangLimitInfo.maks !== null"
                                                    class="cuti-hutang-item"
                                                >
                                                    <span class="cuti-label">Sisa Jatah Hutang:</span>
                                                    <span
                                                        class="cuti-value"
                                                        :class="
                                                            hutangLimitInfo.exceeds ? 'cuti-negative' : 'cuti-positive'
                                                        "
                                                        >{{ hutangLimitInfo.sisaJatah }} hari</span
                                                    >
                                                </div>
                                                <div class="cuti-hutang-item">
                                                    <span class="cuti-label">Pengajuan:</span>
                                                    <span class="cuti-value">{{ pengajuanForm.jumlah_hari }} hari</span>
                                                </div>
                                                <div class="cuti-hutang-item cuti-highlight-item">
                                                    <span class="cuti-label">Akan Berhutang:</span>
                                                    <span class="cuti-value cuti-negative"
                                                        >{{ hutangCalculation.akan_berhutang }} hari</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="cuti-warning-message"
                                                :class="{
                                                    'is-error':
                                                        hutangLimitInfo.exceeds || hutangLimitInfo.blockedNoDebt,
                                                }"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center"
                                                    :class="
                                                        hutangLimitInfo.exceeds || hutangLimitInfo.blockedNoDebt
                                                            ? 'bi-x-octagon'
                                                            : 'bi-info-circle'
                                                    "
                                                ></i>
                                                <small v-if="hutangLimitInfo.blockedNoDebt"
                                                    >Jenis cuti ini <strong>tidak boleh hutang</strong>. Kurangi
                                                    pengajuan hingga maksimal {{ sisaCuti }} hari.</small
                                                >
                                                <small v-else-if="hutangLimitInfo.exceeds"
                                                    >Melebihi batas hutang cuti.
                                                    <strong
                                                        >Kurangi
                                                        {{ hutangLimitInfo.akanBerhutang - hutangLimitInfo.sisaJatah }}
                                                        hari</strong
                                                    >
                                                    atau ubah tanggal.</small
                                                >
                                                <small v-else
                                                    >Pengajuan ini akan otomatis mengurangi saldo cuti yang akan datang.
                                                    Masih dalam batas jatah hutang.</small
                                                >
                                            </div>
                                        </div>

                                        <!-- Cuti Cukup Info -->
                                        <div
                                            v-else-if="
                                                showCutiCukup &&
                                                pengajuanForm.jumlah_hari > 0 &&
                                                selectedTypeCuti &&
                                                selectedTypeCuti?.memotong_saldo == true
                                            "
                                            class="cuti-sufficient-info"
                                        >
                                            <div class="cuti-sufficient-header">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span>Cuti Tersedia</span>
                                            </div>
                                            <div class="cuti-sufficient-details">
                                                <div class="cuti-sufficient-item">
                                                    <span class="cuti-label">Sisa Cuti:</span>
                                                    <span class="cuti-value">{{ sisaCuti }} hari</span>
                                                </div>
                                                <div class="cuti-sufficient-item">
                                                    <span class="cuti-label">Setelah Cuti:</span>
                                                    <span class="cuti-value cuti-positive"
                                                        >{{ hutangCalculation.sisa_setelah_cuti }} hari</span
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Empty State -->
                                        <div
                                            v-else-if="pengajuanForm.jumlah_hari === 0 && selectedTypeCuti"
                                            class="cuti-empty-state d-flex flex-column justify-content-center align-items-center"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-calendar-x"
                                            ></i>
                                            <span>Pilih tanggal cuti untuk melihat durasi</span>
                                        </div>
                                        <div
                                            v-else-if="
                                                pengajuanForm.jumlah_hari > 0 &&
                                                !selectedTypeCuti?.memotong_saldo &&
                                                selectedTypeCuti
                                            "
                                            class="cuti-empty-state d-flex flex-column justify-content-center align-items-center"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-calendar-x"
                                            ></i>
                                            <span>Cuti ini tidak memotong jatah cuti anda</span>
                                        </div>
                                        <div
                                            v-else
                                            class="cuti-empty-state d-flex flex-column justify-content-center align-items-center"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-calendar-x"
                                            ></i>
                                            <span>Pilih jenis cuti </span>
                                        </div>
                                    </div>

                                    <!-- Ketentuan Hutang Cuti -->
                                    <div
                                        v-if="selectedTypeCuti && selectedTypeCuti.memotong_saldo"
                                        class="info-card hutang-rule-card"
                                    >
                                        <div class="info-header">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-credit-card-2-front"
                                            ></i>
                                            <h4>
                                                <el-tooltip
                                                    content="Hutang cuti = mengajukan cuti melebihi saldo. Saldo jadi minus dan dilunasi dari jatah cuti berikutnya."
                                                    placement="top"
                                                >
                                                    <span
                                                        >Ketentuan Hutang Cuti
                                                        <i class="bi bi-question-circle-fill hutang-rule-help"></i
                                                    ></span>
                                                </el-tooltip>
                                            </h4>
                                            <span
                                                class="hutang-rule-badge"
                                                :class="hutangLimitInfo.bisaHutang ? 'is-allowed' : 'is-blocked'"
                                            >
                                                {{ hutangLimitInfo.bisaHutang ? 'Boleh Hutang' : 'Tidak Boleh Hutang' }}
                                            </span>
                                        </div>

                                        <!-- Tidak boleh hutang -->
                                        <div v-if="!hutangLimitInfo.bisaHutang" class="alert info">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                            ></i>
                                            <small
                                                >Jenis cuti ini <strong>tidak boleh hutang</strong>. Pengajuan tidak
                                                boleh melebihi sisa saldo Anda (<strong
                                                    >{{ sisaCuti }} {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                >).</small
                                            >
                                        </div>

                                        <!-- Boleh hutang tanpa batas -->
                                        <div v-else-if="hutangLimitInfo.maks === null" class="alert info">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                            ></i>
                                            <small
                                                >Boleh mengajukan melebihi saldo (hutang)
                                                <strong>tanpa batas jumlah</strong>.</small
                                            >
                                        </div>

                                        <!-- Boleh hutang dengan batas -->
                                        <div v-else class="hutang-rule-body">
                                            <p class="hutang-rule-desc">
                                                Boleh mengajukan melebihi saldo (hutang) maksimal
                                                <strong
                                                    >{{ hutangLimitInfo.maks }}
                                                    {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                >
                                                {{ hutangLimitInfo.periodeLabel }}.
                                            </p>

                                            <!-- Kalimat ringkas: kenapa jatah sudah/masih tersisa -->
                                            <div
                                                v-if="hutangSummary"
                                                class="hutang-headline"
                                                :class="{ 'is-habis': hutangLimitInfo.sisaJatah <= 0 }"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center"
                                                    :class="
                                                        hutangLimitInfo.sisaJatah <= 0
                                                            ? 'bi-exclamation-octagon-fill'
                                                            : 'bi-check-circle-fill'
                                                    "
                                                ></i>
                                                <span>{{ hutangSummary }}</span>
                                            </div>

                                            <div class="hutang-rule-quota">
                                                <div class="hutang-rule-quota-row">
                                                    <span>Jatah hutang terpakai tahun ini</span>
                                                    <span :class="{ 'text-danger-strong': hutangQuotaPercent >= 100 }"
                                                        ><strong>{{ hutangLimitInfo.terpakai }}</strong>
                                                        {{ selectedTypeCuti.satuan || 'hari' }}</span
                                                    >
                                                </div>
                                                <div class="hutang-rule-bar">
                                                    <div
                                                        class="hutang-rule-bar-fill"
                                                        :class="{ 'is-full': hutangQuotaPercent >= 100 }"
                                                        :style="{ width: hutangQuotaPercent + '%' }"
                                                    ></div>
                                                </div>
                                                <div class="hutang-rule-quota-foot">
                                                    Batas
                                                    <strong
                                                        >{{ hutangLimitInfo.maks }}
                                                        {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                    >
                                                    per tahun · Sisa jatah
                                                    <strong>{{ hutangLimitInfo.sisaJatah }}</strong>
                                                    <span v-if="hutangLimitInfo.sisaJatah <= 0" class="hutang-habis-tag"
                                                        >HABIS</span
                                                    >
                                                </div>
                                            </div>
                                            <div v-if="hutangLimitInfo.mode === 'PERIODIK'" class="hutang-rule-note">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-arrow-repeat"
                                                ></i>
                                                <small>
                                                    Jatah kembali penuh
                                                    <strong v-if="hutangUsage.data && hutangUsage.data.reset_date">{{
                                                        formatTanggalIndo(hutangUsage.data.reset_date)
                                                    }}</strong>
                                                    <strong v-else>di periode berikutnya</strong>.
                                                    {{ hutangLimitInfo.perhitunganLabel }}
                                                </small>
                                            </div>

                                            <!-- Semua pinjaman lunas tapi jatah tetap terpakai (khas GROSS) -->
                                            <!-- <div
                                                v-if="
                                                    hutangUsage.data &&
                                                    hutangUsage.data.semua_lunas &&
                                                    hutangUsage.data.terpakai > 0 &&
                                                    hutangLimitInfo.perhitungan === 'GROSS'
                                                "
                                                class="hutang-rule-note is-amber"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                                ></i>
                                                <small
                                                    >Semua pinjaman Anda <strong>sudah lunas</strong> (hutang
                                                    berjalan 0), namun karena perhitungan <strong>GROSS</strong>,
                                                    pinjaman yang sudah lunas tetap mengurangi jatah tahun ini.</small
                                                >
                                            </div> -->

                                            <!-- Rincian pemakaian jatah -->
                                            <div
                                                v-if="
                                                    hutangUsage.data &&
                                                    hutangUsage.data.events &&
                                                    hutangUsage.data.events.length
                                                "
                                                class="hutang-rincian"
                                            >
                                                <button
                                                    type="button"
                                                    class="hutang-rincian-toggle"
                                                    @click="showHutangRincian = !showHutangRincian"
                                                >
                                                    <i
                                                        class="bi"
                                                        :class="
                                                            showHutangRincian ? 'bi-chevron-down' : 'bi-chevron-right'
                                                        "
                                                    ></i>
                                                    Rincian jatah hutang {{ hutangUsage.data.tahun }} ({{
                                                        hutangUsage.data.events.length
                                                    }})
                                                </button>
                                                <div v-show="showHutangRincian" class="hutang-rincian-list">
                                                    <div class="hutang-rincian-note">
                                                        <i class="bi bi-info-circle"></i>
                                                        <span
                                                            >Hutang yang dihitung untuk jatah tahun ini. Cuti
                                                            bersama yang <strong>dibatalkan</strong> tidak
                                                            ditampilkan karena tidak dihitung sebagai hutang.</span
                                                        >
                                                    </div>
                                                    <div
                                                        v-for="(ev, i) in hutangUsage.data.events"
                                                        :key="i"
                                                        class="hutang-rincian-item"
                                                    >
                                                        <div class="hutang-rincian-main">
                                                            <span class="hutang-rincian-judul"
                                                                >{{ ev.konteks || ev.sumber
                                                                }}<span
                                                                    v-if="ev.pending"
                                                                    class="hutang-rincian-pending-tag"
                                                                    >Menunggu persetujuan</span
                                                                ></span
                                                            >
                                                            <span class="hutang-rincian-meta">
                                                                <template v-if="ev.konteks"
                                                                    >{{ ev.sumber
                                                                    }}<template v-if="ev.ref"> · {{ ev.ref }}</template>
                                                                    · </template
                                                                ><template v-else-if="ev.ref">{{ ev.ref }} · </template
                                                                >dicatat {{ formatTanggalIndo(ev.tanggal) }}
                                                            </span>
                                                        </div>
                                                        <span
                                                            class="hutang-rincian-jml"
                                                            :class="{ 'is-credit': ev.jumlah < 0 }"
                                                            >{{ ev.jumlah }}
                                                            {{ selectedTypeCuti.satuan || 'hari' }}</span
                                                        >
                                                    </div>
                                                    <div class="hutang-rincian-total">
                                                        <span>Total jatah hutang terpakai</span>
                                                        <span
                                                            ><strong
                                                                >{{ hutangUsage.data.terpakai }}
                                                                {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                            >
                                                            / {{ hutangUsage.data.maks }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="hutangUsage.loading" class="hutang-rincian-loading">
                                                <span class="spinner-border spinner-border-sm"></span> Memuat rincian...
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Alur Approval -->
                                    <div v-if="selectedTypeCuti" class="info-card approval-flow-card">
                                        <div class="approval-flow-header">
                                            <div class="approval-flow-title-wrap">
                                                <div class="approval-flow-icon">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-diagram-3"
                                                    ></i>
                                                </div>
                                                <div>
                                                    <h4>Alur Approval</h4>
                                                    <p>{{ selectedTypeCuti?.nama || 'Jenis cuti terpilih' }}</p>
                                                </div>
                                            </div>
                                            <span
                                                class="approval-flow-badge"
                                                :class="{ active: selectedTypeCuti?.perlu_persetujuan_khusus }"
                                            >
                                                {{
                                                    selectedTypeCuti?.perlu_persetujuan_khusus
                                                        ? 'Wajib Approval'
                                                        : 'Tanpa Approval Khusus'
                                                }}
                                            </span>
                                        </div>

                                        <div v-if="approvalFlowPreview.loading" class="approval-flow-loading">
                                            <div
                                                class="spinner-border spinner-border-sm text-primary"
                                                role="status"
                                            ></div>
                                            <span>Memuat alur approval...</span>
                                        </div>

                                        <div
                                            v-else-if="approvalFlowPreview.items.length > 0"
                                            class="approval-flow-timeline"
                                        >
                                            <div class="approval-flow-rail"></div>
                                            <div
                                                v-for="(approver, index) in approvalFlowPreview.items"
                                                :key="`${approver.order_flow}-${index}`"
                                                class="approval-flow-node"
                                            >
                                                <div class="approval-flow-node-badge">
                                                    <span class="approval-flow-node-number">{{ index + 1 }}</span>
                                                </div>
                                                <div class="approval-flow-node-body">
                                                    <div
                                                        class="approval-flow-node-title"
                                                        :class="{ 'is-missing': approver.isMissing }"
                                                    >
                                                        <h5>
                                                            {{ approver.name }}
                                                            <span
                                                                v-if="approver.isAdminHC"
                                                                class="approval-flow-node-admin"
                                                            >
                                                                Admin HC
                                                            </span>
                                                        </h5>
                                                        <span
                                                            v-if="approver.isMissing"
                                                            class="approval-flow-node-missing-tag"
                                                        >
                                                            Belum ada approver
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    approvalFlowPreview.hasWarning &&
                                                    approvalFlowPreview.warnings.length
                                                "
                                                class="approval-flow-warning"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                ></i>
                                                <div>
                                                    <strong
                                                        >Alur approval belum memenuhi syarat<template
                                                            v-if="approvalShortfallCount > 0"
                                                        >
                                                            — kurang {{ approvalShortfallCount }} tahap</template
                                                        ></strong
                                                    >
                                                    <p
                                                        v-for="(warning, wIndex) in approvalFlowPreview.warnings"
                                                        :key="`approval-warning-${wIndex}`"
                                                        class="mb-1"
                                                    >
                                                        {{ warning }}
                                                    </p>
                                                    <p class="mb-0 approval-flow-warning-note">
                                                        Pengajuan belum bisa dikirim sampai alur approval lengkap.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="approval-flow-empty">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                            ></i>
                                            <div>
                                                <strong>{{
                                                    selectedTypeCuti?.perlu_persetujuan_khusus
                                                        ? 'Alur approval belum tersedia'
                                                        : 'Cuti ini tidak memerlukan approval khusus'
                                                }}</strong>
                                                <p>
                                                    {{
                                                        selectedTypeCuti?.perlu_persetujuan_khusus
                                                            ? 'Konfigurasi approval untuk jenis cuti ini belum lengkap.'
                                                            : 'Pengajuan akan langsung diproses sesuai aturan cuti yang berlaku.'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Informasi Cuti Bersama -->
                                    <div v-if="cutiBersamaInfo" class="info-card">
                                        <div class="info-header">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                            ></i>
                                            <h4>Informasi</h4>
                                        </div>
                                        <div class="alert info">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-calendar-event"
                                            ></i>
                                            <p>{{ cutiBersamaInfo }}</p>
                                        </div>
                                    </div>

                                    <!-- Tips -->
                                    <div class="info-card tips">
                                        <div class="info-header">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-lightbulb"
                                            ></i>
                                            <h4>Tips Pengajuan Cuti</h4>
                                        </div>
                                        <div class="tips-list">
                                            <div class="tip-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span>Gunakan cuti tahunan sebelum akhir tahun</span>
                                            </div>
                                            <div class="tip-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span
                                                    v-if="
                                                        !selectedTypeCuti?.batas_hari_pengajuan &&
                                                        selectedTypeCuti?.batas_hari_pengajuan !== 0
                                                    "
                                                >
                                                    Silakan pilih jenis cuti terlebih dahulu.
                                                </span>

                                                <span v-else-if="selectedTypeCuti?.batas_hari_pengajuan === 0">
                                                    Pengajuan cuti dapat dilakukan mulai hari ini sampai kedepan.
                                                </span>

                                                <span v-else>
                                                    Pengajuan cuti ini dapat dilakukan hingga
                                                    {{ Math.abs(selectedTypeCuti?.batas_hari_pengajuan) }}
                                                    hari dari tanggal cuti ({{
                                                        selectedTypeCuti?.batas_hari_pengajuan > 0
                                                            ? 'sebelum'
                                                            : 'sesudah'
                                                    }}
                                                    tanggal pelaksanaan).
                                                </span>
                                            </div>
                                            <div class="tip-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span>Periksa saldo cuti secara berkala</span>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                    <!-- /sidebar-cards-desktop -->
                                </div>
                            </div>
                        </div>

                        <!-- ===== MOBILE: Sticky bar ringkas (adaptif untuk semua jenis cuti) ===== -->
                        <div
                            v-if="activeTab === 'ajukan' && selectedTypeCuti"
                            class="cuti-hutang-mobilebar"
                        >
                            <div class="cuti-hutang-mobilebar-info" :class="mobileSummaryBar.cls">
                                <i class="bi" :class="mobileSummaryBar.icon"></i>
                                <span>{{ mobileSummaryBar.text }}</span>
                            </div>
                            <button
                                type="button"
                                class="cuti-hutang-mobilebar-btn"
                                @click="showMobileSummaryModal = true"
                            >
                                <i class="bi bi-info-circle"></i>
                                Detail
                            </button>
                        </div>

                        <!-- ===== MOBILE: Modal info lengkap hutang cuti ===== -->
                        <Modal
                            v-if="showMobileHutangModal"
                            :show="showMobileHutangModal"
                            title="Ketentuan Hutang Cuti"
                            size="large"
                            @close="showMobileHutangModal = false"
                        >
                            <template #body>
                                <div class="hutang-cards-mobilemodal">
                                    <!-- Durasi Cuti -->
                                    <div class="cuti-duration-card cuti-highlight">
                                        <div class="cuti-info-header">
                                            <i class="bi d-flex justify-content-center align-items-center bi-clock"></i>
                                            <h4>Durasi Cuti</h4>
                                        </div>
                                        <div class="cuti-duration-display">
                                            <span class="cuti-duration-value">{{ pengajuanForm.jumlah_hari }}</span>
                                            <span class="cuti-duration-label">Hari</span>
                                        </div>

                                        <div
                                            v-if="
                                                showHutangWarning &&
                                                pengajuanForm.jumlah_hari > 0 &&
                                                selectedTypeCuti &&
                                                selectedTypeCuti?.memotong_saldo == true
                                            "
                                            class="cuti-hutang-warning"
                                        >
                                            <div class="cuti-warning-header">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                ></i>
                                                <span>Perhitungan Hutang Cuti</span>
                                            </div>
                                            <div class="cuti-hutang-details">
                                                <div class="cuti-hutang-item">
                                                    <span class="cuti-label">Sisa Cuti:</span>
                                                    <span class="cuti-value">{{ sisaCuti }} hari</span>
                                                </div>
                                                <div
                                                    v-if="hutangLimitInfo.bisaHutang && hutangLimitInfo.maks !== null"
                                                    class="cuti-hutang-item"
                                                >
                                                    <span class="cuti-label">Sisa Jatah Hutang:</span>
                                                    <span
                                                        class="cuti-value"
                                                        :class="
                                                            hutangLimitInfo.exceeds ? 'cuti-negative' : 'cuti-positive'
                                                        "
                                                        >{{ hutangLimitInfo.sisaJatah }} hari</span
                                                    >
                                                </div>
                                                <div class="cuti-hutang-item">
                                                    <span class="cuti-label">Pengajuan:</span>
                                                    <span class="cuti-value">{{ pengajuanForm.jumlah_hari }} hari</span>
                                                </div>
                                                <div class="cuti-hutang-item cuti-highlight-item">
                                                    <span class="cuti-label">Akan Berhutang:</span>
                                                    <span class="cuti-value cuti-negative"
                                                        >{{ hutangCalculation.akan_berhutang }} hari</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="cuti-warning-message"
                                                :class="{
                                                    'is-error':
                                                        hutangLimitInfo.exceeds || hutangLimitInfo.blockedNoDebt,
                                                }"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center"
                                                    :class="
                                                        hutangLimitInfo.exceeds || hutangLimitInfo.blockedNoDebt
                                                            ? 'bi-x-octagon'
                                                            : 'bi-info-circle'
                                                    "
                                                ></i>
                                                <small v-if="hutangLimitInfo.blockedNoDebt"
                                                    >Jenis cuti ini <strong>tidak boleh hutang</strong>. Kurangi
                                                    pengajuan hingga maksimal {{ sisaCuti }} hari.</small
                                                >
                                                <small v-else-if="hutangLimitInfo.exceeds"
                                                    >Melebihi batas hutang cuti.
                                                    <strong
                                                        >Kurangi
                                                        {{ hutangLimitInfo.akanBerhutang - hutangLimitInfo.sisaJatah }}
                                                        hari</strong
                                                    >
                                                    atau ubah tanggal.</small
                                                >
                                                <small v-else
                                                    >Pengajuan ini akan otomatis mengurangi saldo cuti yang akan datang.
                                                    Masih dalam batas jatah hutang.</small
                                                >
                                            </div>
                                        </div>

                                        <div
                                            v-else-if="
                                                showCutiCukup &&
                                                pengajuanForm.jumlah_hari > 0 &&
                                                selectedTypeCuti &&
                                                selectedTypeCuti?.memotong_saldo == true
                                            "
                                            class="cuti-sufficient-info"
                                        >
                                            <div class="cuti-sufficient-header">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span>Cuti Tersedia</span>
                                            </div>
                                            <div class="cuti-sufficient-details">
                                                <div class="cuti-sufficient-item">
                                                    <span class="cuti-label">Sisa Cuti:</span>
                                                    <span class="cuti-value">{{ sisaCuti }} hari</span>
                                                </div>
                                                <div class="cuti-sufficient-item">
                                                    <span class="cuti-label">Setelah Cuti:</span>
                                                    <span class="cuti-value cuti-positive"
                                                        >{{ hutangCalculation.sisa_setelah_cuti }} hari</span
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else-if="pengajuanForm.jumlah_hari === 0 && selectedTypeCuti"
                                            class="cuti-empty-state d-flex flex-column justify-content-center align-items-center"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-calendar-x"
                                            ></i>
                                            <span>Pilih tanggal cuti untuk melihat durasi</span>
                                        </div>
                                    </div>

                                    <!-- Ketentuan Hutang Cuti -->
                                    <div
                                        v-if="selectedTypeCuti && selectedTypeCuti.memotong_saldo"
                                        class="info-card hutang-rule-card"
                                    >
                                        <div class="info-header">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-credit-card-2-front"
                                            ></i>
                                            <h4>Ketentuan Hutang Cuti</h4>
                                            <span
                                                class="hutang-rule-badge"
                                                :class="hutangLimitInfo.bisaHutang ? 'is-allowed' : 'is-blocked'"
                                            >
                                                {{ hutangLimitInfo.bisaHutang ? 'Boleh Hutang' : 'Tidak Boleh Hutang' }}
                                            </span>
                                        </div>

                                        <div v-if="!hutangLimitInfo.bisaHutang" class="alert info">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                            ></i>
                                            <small
                                                >Jenis cuti ini <strong>tidak boleh hutang</strong>. Pengajuan tidak
                                                boleh melebihi sisa saldo Anda (<strong
                                                    >{{ sisaCuti }} {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                >).</small
                                            >
                                        </div>

                                        <div v-else-if="hutangLimitInfo.maks === null" class="alert info">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                            ></i>
                                            <small
                                                >Boleh mengajukan melebihi saldo (hutang)
                                                <strong>tanpa batas jumlah</strong>.</small
                                            >
                                        </div>

                                        <div v-else class="hutang-rule-body">
                                            <p class="hutang-rule-desc">
                                                Boleh mengajukan melebihi saldo (hutang) maksimal
                                                <strong
                                                    >{{ hutangLimitInfo.maks }}
                                                    {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                >
                                                {{ hutangLimitInfo.periodeLabel }}.
                                            </p>

                                            <div
                                                v-if="hutangSummary"
                                                class="hutang-headline"
                                                :class="{ 'is-habis': hutangLimitInfo.sisaJatah <= 0 }"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center"
                                                    :class="
                                                        hutangLimitInfo.sisaJatah <= 0
                                                            ? 'bi-exclamation-octagon-fill'
                                                            : 'bi-check-circle-fill'
                                                    "
                                                ></i>
                                                <span>{{ hutangSummary }}</span>
                                            </div>

                                            <div class="hutang-rule-quota">
                                                <div class="hutang-rule-quota-row">
                                                    <span>Jatah hutang terpakai tahun ini</span>
                                                    <span :class="{ 'text-danger-strong': hutangQuotaPercent >= 100 }"
                                                        ><strong>{{ hutangLimitInfo.terpakai }}</strong>
                                                        {{ selectedTypeCuti.satuan || 'hari' }}</span
                                                    >
                                                </div>
                                                <div class="hutang-rule-bar">
                                                    <div
                                                        class="hutang-rule-bar-fill"
                                                        :class="{ 'is-full': hutangQuotaPercent >= 100 }"
                                                        :style="{ width: hutangQuotaPercent + '%' }"
                                                    ></div>
                                                </div>
                                                <div class="hutang-rule-quota-foot">
                                                    Batas
                                                    <strong
                                                        >{{ hutangLimitInfo.maks }}
                                                        {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                    >
                                                    per tahun · Sisa jatah
                                                    <strong>{{ hutangLimitInfo.sisaJatah }}</strong>
                                                    <span v-if="hutangLimitInfo.sisaJatah <= 0" class="hutang-habis-tag"
                                                        >HABIS</span
                                                    >
                                                </div>
                                            </div>

                                            <div v-if="hutangLimitInfo.mode === 'PERIODIK'" class="hutang-rule-note">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-arrow-repeat"
                                                ></i>
                                                <small>
                                                    Jatah kembali penuh
                                                    <strong v-if="hutangUsage.data && hutangUsage.data.reset_date">{{
                                                        formatTanggalIndo(hutangUsage.data.reset_date)
                                                    }}</strong>
                                                    <strong v-else>di periode berikutnya</strong>.
                                                    {{ hutangLimitInfo.perhitunganLabel }}
                                                </small>
                                            </div>

                                            <div
                                                v-if="
                                                    hutangUsage.data &&
                                                    hutangUsage.data.events &&
                                                    hutangUsage.data.events.length
                                                "
                                                class="hutang-rincian"
                                            >
                                                <button
                                                    type="button"
                                                    class="hutang-rincian-toggle"
                                                    @click="showHutangRincian = !showHutangRincian"
                                                >
                                                    <i
                                                        class="bi"
                                                        :class="
                                                            showHutangRincian ? 'bi-chevron-down' : 'bi-chevron-right'
                                                        "
                                                    ></i>
                                                    Rincian jatah hutang {{ hutangUsage.data.tahun }} ({{
                                                        hutangUsage.data.events.length
                                                    }})
                                                </button>
                                                <div v-show="showHutangRincian" class="hutang-rincian-list">
                                                    <div class="hutang-rincian-note">
                                                        <i class="bi bi-info-circle"></i>
                                                        <span
                                                            >Hutang yang dihitung untuk jatah tahun ini. Cuti
                                                            bersama yang <strong>dibatalkan</strong> tidak
                                                            ditampilkan karena tidak dihitung sebagai hutang.</span
                                                        >
                                                    </div>
                                                    <div
                                                        v-for="(ev, i) in hutangUsage.data.events"
                                                        :key="i"
                                                        class="hutang-rincian-item"
                                                    >
                                                        <div class="hutang-rincian-main">
                                                            <span class="hutang-rincian-judul"
                                                                >{{ ev.konteks || ev.sumber
                                                                }}<span
                                                                    v-if="ev.pending"
                                                                    class="hutang-rincian-pending-tag"
                                                                    >Menunggu persetujuan</span
                                                                ></span
                                                            >
                                                            <span class="hutang-rincian-meta">
                                                                <template v-if="ev.konteks"
                                                                    >{{ ev.sumber
                                                                    }}<template v-if="ev.ref"> · {{ ev.ref }}</template>
                                                                    · </template
                                                                ><template v-else-if="ev.ref">{{ ev.ref }} · </template
                                                                >dicatat {{ formatTanggalIndo(ev.tanggal) }}
                                                            </span>
                                                        </div>
                                                        <span
                                                            class="hutang-rincian-jml"
                                                            :class="{ 'is-credit': ev.jumlah < 0 }"
                                                            >{{ ev.jumlah }}
                                                            {{ selectedTypeCuti.satuan || 'hari' }}</span
                                                        >
                                                    </div>
                                                    <div class="hutang-rincian-total">
                                                        <span>Total jatah hutang terpakai</span>
                                                        <span
                                                            ><strong
                                                                >{{ hutangUsage.data.terpakai }}
                                                                {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                            >
                                                            / {{ hutangUsage.data.maks }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="hutangUsage.loading" class="hutang-rincian-loading">
                                                <span class="spinner-border spinner-border-sm"></span> Memuat rincian...
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Alur Approval -->
                                    <div v-if="selectedTypeCuti" class="info-card approval-flow-card">
                                        <div class="approval-flow-header">
                                            <div class="approval-flow-title-wrap">
                                                <div class="approval-flow-icon">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-diagram-3"
                                                    ></i>
                                                </div>
                                                <div>
                                                    <h4>Alur Approval</h4>
                                                    <p>{{ selectedTypeCuti?.nama || 'Jenis cuti terpilih' }}</p>
                                                </div>
                                            </div>
                                            <span
                                                class="approval-flow-badge"
                                                :class="{ active: selectedTypeCuti?.perlu_persetujuan_khusus }"
                                            >
                                                {{
                                                    selectedTypeCuti?.perlu_persetujuan_khusus
                                                        ? 'Wajib Approval'
                                                        : 'Tanpa Approval Khusus'
                                                }}
                                            </span>
                                        </div>

                                        <div v-if="approvalFlowPreview.loading" class="approval-flow-loading">
                                            <div
                                                class="spinner-border spinner-border-sm text-primary"
                                                role="status"
                                            ></div>
                                            <span>Memuat alur approval...</span>
                                        </div>

                                        <div
                                            v-else-if="approvalFlowPreview.items.length > 0"
                                            class="approval-flow-timeline"
                                        >
                                            <div class="approval-flow-rail"></div>
                                            <div
                                                v-for="(approver, index) in approvalFlowPreview.items"
                                                :key="`${approver.order_flow}-${index}`"
                                                class="approval-flow-node"
                                            >
                                                <div class="approval-flow-node-badge">
                                                    <span class="approval-flow-node-number">{{ index + 1 }}</span>
                                                </div>
                                                <div class="approval-flow-node-body">
                                                    <div
                                                        class="approval-flow-node-title"
                                                        :class="{ 'is-missing': approver.isMissing }"
                                                    >
                                                        <h5>
                                                            {{ approver.name }}
                                                            <span
                                                                v-if="approver.isAdminHC"
                                                                class="approval-flow-node-admin"
                                                            >
                                                                Admin HC
                                                            </span>
                                                        </h5>
                                                        <span
                                                            v-if="approver.isMissing"
                                                            class="approval-flow-node-missing-tag"
                                                        >
                                                            Belum ada approver
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    approvalFlowPreview.hasWarning &&
                                                    approvalFlowPreview.warnings.length
                                                "
                                                class="approval-flow-warning"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                ></i>
                                                <div>
                                                    <strong
                                                        >Alur approval belum memenuhi syarat<template
                                                            v-if="approvalShortfallCount > 0"
                                                        >
                                                            — kurang {{ approvalShortfallCount }} tahap</template
                                                        ></strong
                                                    >
                                                    <p
                                                        v-for="(warning, wIndex) in approvalFlowPreview.warnings"
                                                        :key="`approval-warning-${wIndex}`"
                                                        class="mb-1"
                                                    >
                                                        {{ warning }}
                                                    </p>
                                                    <p class="mb-0 approval-flow-warning-note">
                                                        Pengajuan belum bisa dikirim sampai alur approval lengkap.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else class="approval-flow-empty">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                            ></i>
                                            <div>
                                                <strong>{{
                                                    selectedTypeCuti?.perlu_persetujuan_khusus
                                                        ? 'Alur approval belum tersedia'
                                                        : 'Cuti ini tidak memerlukan approval khusus'
                                                }}</strong>
                                                <p>
                                                    {{
                                                        selectedTypeCuti?.perlu_persetujuan_khusus
                                                            ? 'Konfigurasi approval untuk jenis cuti ini belum lengkap.'
                                                            : 'Pengajuan akan langsung diproses sesuai aturan cuti yang berlaku.'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Informasi Cuti Bersama -->
                                    <div v-if="cutiBersamaInfo" class="info-card">
                                        <div class="info-header">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-info-circle"
                                            ></i>
                                            <h4>Informasi</h4>
                                        </div>
                                        <div class="alert info">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-calendar-event"
                                            ></i>
                                            <p>{{ cutiBersamaInfo }}</p>
                                        </div>
                                    </div>

                                    <!-- Tips -->
                                    <div class="info-card tips">
                                        <div class="info-header">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-lightbulb"
                                            ></i>
                                            <h4>Tips Pengajuan Cuti</h4>
                                        </div>
                                        <div class="tips-list">
                                            <div class="tip-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span>Gunakan cuti tahunan sebelum akhir tahun</span>
                                            </div>
                                            <div class="tip-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span
                                                    v-if="
                                                        !selectedTypeCuti?.batas_hari_pengajuan &&
                                                        selectedTypeCuti?.batas_hari_pengajuan !== 0
                                                    "
                                                >
                                                    Silakan pilih jenis cuti terlebih dahulu.
                                                </span>

                                                <span v-else-if="selectedTypeCuti?.batas_hari_pengajuan === 0">
                                                    Pengajuan cuti dapat dilakukan mulai hari ini sampai kedepan.
                                                </span>

                                                <span v-else>
                                                    Pengajuan cuti ini dapat dilakukan hingga
                                                    {{ Math.abs(selectedTypeCuti?.batas_hari_pengajuan) }}
                                                    hari dari tanggal cuti ({{
                                                        selectedTypeCuti?.batas_hari_pengajuan > 0
                                                            ? 'sebelum'
                                                            : 'sesudah'
                                                    }}
                                                    tanggal pelaksanaan).
                                                </span>
                                            </div>
                                            <div class="tip-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                                <span>Periksa saldo cuti secara berkala</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Modal>

                        <!-- ===== MOBILE: Modal ringkasan cuti (adaptif approval + hutang) ===== -->
                        <Modal
                            v-if="showMobileSummaryModal"
                            :show="showMobileSummaryModal"
                            title="Ringkasan Cuti"
                            size="large"
                            @close="showMobileSummaryModal = false"
                        >
                            <template #body>
                                <div class="cuti-summary-modal">
                                    <!-- Bagian Approval -->
                                    <section
                                        v-if="selectedTypeCuti && selectedTypeCuti.perlu_persetujuan_khusus"
                                        class="cuti-summary-section"
                                    >
                                        <div class="cuti-summary-section-head">
                                            <div class="cuti-summary-section-title">
                                                <i class="bi bi-diagram-3"></i>
                                                <span>Alur Approval</span>
                                            </div>
                                            <span
                                                class="cuti-summary-pill"
                                                :class="
                                                    approvalFlowPreview.isValid && !approvalFlowPreview.hasWarning
                                                        ? 'is-ok'
                                                        : 'is-error'
                                                "
                                            >
                                                {{
                                                    approvalFlowPreview.isValid && !approvalFlowPreview.hasWarning
                                                        ? 'Siap'
                                                        : 'Belum lengkap'
                                                }}
                                            </span>
                                        </div>

                                        <div v-if="approvalFlowPreview.loading" class="cuti-summary-muted">
                                            Memuat alur approval…
                                        </div>

                                        <template v-else>
                                            <ol
                                                v-if="approvalFlowPreview.steps.length"
                                                class="cuti-summary-steps"
                                            >
                                                <li
                                                    v-for="(step, sIndex) in approvalFlowPreview.steps"
                                                    :key="`sum-step-${sIndex}`"
                                                    class="cuti-summary-step"
                                                    :class="{ 'is-invalid': step.is_valid === false }"
                                                >
                                                    <span class="cuti-summary-step-idx">{{ sIndex + 1 }}</span>
                                                    <div class="cuti-summary-step-body">
                                                        <div class="cuti-summary-step-title">
                                                            {{ step.nama_approval || `Tahap ${sIndex + 1}` }}
                                                        </div>
                                                        <div v-if="step.warning" class="cuti-summary-step-warn">
                                                            {{ step.warning }}
                                                        </div>
                                                        <div
                                                            v-else-if="step.approvers && step.approvers.length"
                                                            class="cuti-summary-step-people"
                                                        >
                                                            {{ step.approvers.map((a) => a.nama).join(', ') }}
                                                        </div>
                                                    </div>
                                                    <i
                                                        class="bi cuti-summary-step-icon"
                                                        :class="
                                                            step.is_valid === false
                                                                ? 'bi-x-circle-fill is-error'
                                                                : 'bi-check-circle-fill is-ok'
                                                        "
                                                    ></i>
                                                </li>
                                            </ol>

                                            <div
                                                v-if="approvalFlowPreview.hasWarning"
                                                class="cuti-summary-alert is-error"
                                            >
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                <div>
                                                    <strong>Pengajuan belum bisa dikirim</strong>
                                                    <p
                                                        v-for="(w, wI) in approvalFlowPreview.warnings"
                                                        :key="`sum-w-${wI}`"
                                                    >
                                                        {{ w }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div v-else class="cuti-summary-alert is-ok">
                                                <i class="bi bi-check-circle-fill"></i>
                                                <div><strong>Alur approval sudah lengkap.</strong></div>
                                            </div>
                                        </template>
                                    </section>

                                    <!-- Bagian Saldo / Hutang -->
                                    <section
                                        v-if="selectedTypeCuti && selectedTypeCuti.memotong_saldo"
                                        class="cuti-summary-section"
                                    >
                                        <div class="cuti-summary-section-head">
                                            <div class="cuti-summary-section-title">
                                                <i class="bi bi-wallet2"></i>
                                                <span>Saldo &amp; Hutang Cuti</span>
                                            </div>
                                        </div>

                                        <div class="cuti-summary-statusline" :class="mobileBarStatus.cls">
                                            <i class="bi" :class="mobileBarStatus.icon"></i>
                                            <span>{{ mobileBarStatus.text }}</span>
                                        </div>

                                        <div class="cuti-summary-stats">
                                            <div class="cuti-summary-stat">
                                                <span>Saldo cuti</span>
                                                <strong>{{ sisaCuti }} {{ selectedTypeCuti.satuan || 'hari' }}</strong>
                                            </div>
                                            <div v-if="hutangLimitInfo.bisaHutang" class="cuti-summary-stat">
                                                <span>Sisa jatah hutang</span>
                                                <strong
                                                    >{{ hutangLimitInfo.sisaJatah ?? '—' }}
                                                    {{ selectedTypeCuti.satuan || 'hari' }}</strong
                                                >
                                            </div>
                                        </div>

                                        <p v-if="hutangSummary" class="cuti-summary-note">{{ hutangSummary }}</p>

                                        <button
                                            type="button"
                                            class="cuti-summary-link"
                                            @click="
                                                showMobileSummaryModal = false;
                                                showMobileHutangModal = true;
                                            "
                                        >
                                            <i class="bi bi-calculator"></i>
                                            Lihat perhitungan lengkap
                                        </button>
                                    </section>

                                    <!-- Tidak perlu approval & tidak memotong saldo -->
                                    <div
                                        v-if="
                                            selectedTypeCuti &&
                                            !selectedTypeCuti.perlu_persetujuan_khusus &&
                                            !selectedTypeCuti.memotong_saldo
                                        "
                                        class="cuti-summary-alert is-ok"
                                    >
                                        <i class="bi bi-info-circle-fill"></i>
                                        <div>
                                            <strong>Cuti ini tidak perlu approval khusus.</strong>
                                            <p>Pengajuan akan langsung diproses sesuai aturan yang berlaku.</p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Modal>
                    </template>

                    <!-- Tab Riwayat -->
                    <template #riwayat>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <FilterPanel
                                :config="riwayatFilterConfig"
                                :filters="riwayatFilters"
                                :results-count="riwayatData.pagination?.total || 0"
                                @filters-update="handleRiwayatFiltersUpdate"
                            />

                            <!-- Loading state: hanya saat benar-benar sedang memuat -->
                            <div v-if="loading.riwayat" class="row justify-content-center fade-in">
                                <div class="col-12">
                                    <div class="loading-state">
                                        <div class="loading-spinner">
                                            <div
                                                class="spinner-border text-primary"
                                                role="status"
                                                style="width: 2.5rem; height: 2.5rem"
                                            >
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                        <h4 class="loading-title">Memuat Data Cuti...</h4>
                                        <p class="loading-description">Sedang mengambil data cuti</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Modern Data Table -->
                            <div v-else-if="filteredRiwayatData.length > 0" class="data-table-container">
                                <ModernDataTable
                                    :data="filteredRiwayatData"
                                    :columns="riwayatColumns"
                                    :mobile-columns="riwayatMobileColumns"
                                    :loading="loading.riwayat"
                                    :clickable="true"
                                    :show-pagination="true"
                                    :current-page="riwayatData?.pagination?.current_page"
                                    :total-pages="riwayatData?.pagination?.total_pages"
                                    :total-data="riwayatData?.pagination?.total"
                                    :full-pagination="riwayatData?.pagination"
                                    card-title-key="requester_Nama"
                                    card-subtitle-key="Tipe_Izin"
                                    card-status-key="status"
                                    empty-message="Belum ada riwayat pengajuan yang sesuai dengan filter Anda."
                                    @row-click="viewRiwayatDetail"
                                    @page-change="handleRiwayatPageChange"
                                    @empty-action="loadRiwayat"
                                >
                                    <!-- Custom slot untuk kolom Karyawan -->
                                    <template #column-requester_Nama="{ item }">
                                        <div class="employee-info-cell">
                                            <div class="avatar-sm">
                                                {{ getInitials(item?.requester_Nama) }}
                                            </div>
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{ item?.requester_Nama }}
                                                </h6>
                                                <p class="employee-dept">
                                                    {{ item?.department || '-' }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Custom slot untuk kolom Jenis Izin -->
                                    <template #column-Tipe_Izin="{ item }">
                                        <Badge :type="getJenisIzinBadgeType(item)" class="badge-sm">
                                            {{ getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom slot untuk kolom Status -->
                                    <template #column-status="{ item }">
                                        <Badge :type="getStatusBadgeType(item?.status)" class="status-badge">
                                            <i :class="getStatusIcon(item?.status)" class="me-1"></i>
                                            {{ getStatusText(item?.status) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom slot untuk kolom Aksi -->
                                    <template #column-actions="{ item }">
                                        <div class="action-cell">
                                            <button
                                                class="btn-action btn-view"
                                                @click.stop="viewRiwayatDetail(item)"
                                                v-tooltip="'Lihat Detail'"
                                            >
                                                <i class="bi bi-eye"></i>
                                                <!-- <span class="action-text"
                                                >Detail</span
                                            > -->
                                            </button>
                                            <button
                                                v-if="item?.status == 'pending' && item?.currentFlow == '1'"
                                                class="btn-action btn-view btn-cancel"
                                                @click.stop="batalTransaksi(item)"
                                                v-tooltip="'Batalkan transaksi'"
                                            >
                                                <i class="bi bi-trash"></i>
                                                <!-- <span class="action-text"
                                                >Detail</span
                                            > -->
                                            </button>
                                        </div>
                                    </template>

                                    <!-- Custom header untuk card view -->
                                    <template #card-header="{ item }">
                                        <div class="card-title">
                                            {{ item?.requester_Nama }}
                                        </div>
                                        <div class="card-subtitle">
                                            {{ getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin) }}
                                        </div>
                                    </template>

                                    <!-- Custom status untuk card view -->
                                    <template #card-status="{ item }">
                                        <Badge :type="getStatusBadgeType(item?.status)">
                                            <i :class="getStatusIcon(item?.status)" class="me-1"></i>
                                            {{ getStatusText(item?.status) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom value untuk mobile view -->
                                    <template #mobile-TanggalIzin="{ item }">
                                        {{ formatRiwayatDate(item) }}
                                    </template>

                                    <template #mobile-tanggalReal="{ item }">
                                        {{ item?.tanggalReal?.jumlah_hari || '1' }}
                                        hari
                                    </template>

                                    <template #mobile-No_Transaksi="{ item }">
                                        <code class="transaction-id-sm">{{ item?.No_Transaksi }}</code>
                                    </template>

                                    <template #mobile-Alasan="{ item }">
                                        {{ truncateText(item?.Alasan, 60) }}
                                    </template>

                                    <!-- Custom actions untuk mobile -->
                                    <template #card-actions="{ item }">
                                        <button
                                            class="card-btn btn-view mobile-btn"
                                            @click.stop="viewRiwayatDetail(item)"
                                        >
                                            <i class="bi bi-eye"></i>
                                            <span>Lihat Detail</span>
                                        </button>
                                        <button
                                            v-if="item?.status == 'pending' && item?.currentFlow == '1'"
                                            class="card-btn btn-view mobile-btn btn-cancel"
                                            @click.stop="batalTransaksi(item)"
                                        >
                                            <i class="bi bi-trash"></i>
                                            <span>Batalkan Transaksi</span>
                                        </button>
                                    </template>
                                </ModernDataTable>
                            </div>

                            <!-- Empty state: sudah selesai memuat & memang tidak ada riwayat -->
                            <div
                                v-else
                                class="empty-state h-100 d-flex justify-content-center align-items-center flex-column"
                            >
                                <div class="empty-icon mb-4">
                                    <DotLottieVue
                                        style="height: 150px; width: 150px"
                                        autoplay
                                        loop
                                        src="/animation/empty-data.json"
                                    />
                                </div>
                                <h4>Tidak Ada Data</h4>
                                <p>Belum ada pengajuan yang ditambahkan</p>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Riwayat -->
        <!-- <el-dialog
            v-model="showDetailModal"
            :title="isMobile ? '' : 'Detail Pengajuan Cuti'"
            @close="closeDetailModal"
            :width="isMobile ? '100%' : '90%'"
            :fullscreen="isMobile"
            :lock-scroll="true"
            destroy-on-close
            class="detail-modal"
            :class="{ 'mobile-fullscreen': isMobile }"
        >
            <template #header v-if="isMobile">
                <div class="mobile-modal-header">
                    <button
                        class="btn-back d-none d-md-block"
                        @click="closeDetailModal"
                    >
                        <i
                            class="bi d-flex justify-content-center align-items-center bi-arrow-left"
                        ></i>
                    </button>
                    <h3 class="mobile-modal-title">Detail Pengajuan</h3>
                    <div class="header-spacer"></div>
                </div>
            </template>

            <div v-if="detailLoading" class="loading-skeleton">
                <div class="skeleton-line large"></div>
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line small"></div>
                <div class="skeleton-line full"></div>
            </div>

            <div v-else-if="selectedRiwayat" class="detail-content">
                <div class="detail-grid">
                    <div class="detail-main">
                        <div class="detail-section">
                            <div class="detail-item">
                                <label class="detail-label">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-calendar me-2"
                                    ></i>
                                    Jenis Cuti
                                </label>
                                <div class="detail-value">
                                    {{ selectedRiwayat.jenis_cuti }}
                                </div>
                            </div>
                            <div class="detail-item">
                                <label class="detail-label">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-calendar-range me-2"
                                    ></i>
                                    Periode
                                </label>

                                <div class="detail-value">
                                    {{
                                        formatTanggalFromAgg(
                                            selectedRiwayat.tanggal_cuti
                                        )
                                    }}
                                    <span class="detail-meta"
                                        >({{
                                            selectedRiwayat.jumlah_hari
                                        }}
                                        hari)</span
                                    >
                                </div>
                            </div>
                            <div class="detail-item">
                                <label class="detail-label">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-info-circle me-2"
                                    ></i>
                                    Status
                                </label>
                                <div class="detail-value">
                                    <Badge
                                        :type="
                                            getStatusBadgeType(
                                                selectedRiwayat.status
                                            )
                                        "
                                    >
                                        {{
                                            getStatusText(
                                                selectedRiwayat.status
                                            )
                                        }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="detail-item">
                                <label class="detail-label">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-chat-text me-2"
                                    ></i>
                                    Alasan
                                </label>
                                <div class="detail-value reason-text">
                                    {{ selectedRiwayat.alasan }}
                                </div>
                            </div>
                            <div
                                v-if="selectedRiwayat.file_pendukung_url"
                                class="detail-item"
                            >
                                <label class="detail-label">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-paperclip me-2"
                                    ></i>
                                    File Pendukung
                                </label>
                                <div class="detail-value">
                                    <a
                                        :href="
                                            selectedRiwayat.file_pendukung_url
                                        "
                                        target="_blank"
                                        class="file-link"
                                    >
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-download me-2"
                                        ></i>
                                        Unduh File
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="detail-sidebar"
                        v-if="
                            selectedRiwayat?.approvers &&
                            selectedRiwayat?.approvers?.length > 0
                        "
                    >
                        <div class="approval-flow">
                            <h4 class="flow-title">
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-diagram-3 me-2"
                                ></i>
                                Alur Persetujuan
                            </h4>
                            <el-steps
                                direction="vertical"
                                :active="
                                    getActiveStep(selectedRiwayat.approvers)
                                "
                                finish-status="success"
                            >
                                <el-step
                                    v-for="approver in selectedRiwayat.approvers"
                                    :key="approver.Request_Order_Flow"
                                    :title="approver.approver_name || 'Grup HC'"
                                    :description="getStepDescription(approver)"
                                    :status="getStepStatus(approver)"
                                >
                                    <template #icon>
                                        <i :class="getStepIcon(approver)"></i>
                                    </template>
                                </el-step>
                            </el-steps>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer v-if="!isMobile">
                <div class="modal-actions">
                    <button class="btn btn-secondary" @click="closeDetailModal">
                        Tutup
                    </button>
                </div>
            </template>
        </el-dialog> -->

        <!-- Modal Konfirmasi Pembatalan -->
        <!-- <el-dialog
            v-model="showCancelModal"
            title="Konfirmasi Pembatalan"
            width="400px"
            align-center
            :lock-scroll="true"
            destroy-on-close
            class="cancel-modal"
        >
            <div
                class="confirmation-content d-flex flex-column d-flex justify-content-center align-items-center"
            >
                <i
                    class="bi mb-4 d-flex justify-content-center align-items-center bi-exclamation-triangle warning-icon"
                ></i>
                <p>Apakah Anda yakin ingin membatalkan pengajuan cuti ini?</p>
            </div>
            <template #footer>
                <div class="confirmation-actions">
                    <button
                        class="btn btn-secondary"
                        @click="showCancelModal = false"
                    >
                        Batal
                    </button>
                    <button
                        class="btn btn-danger"
                        @click="confirmCancelPengajuan"
                    >
                        Ya, Batalkan
                    </button>
                </div>
            </template>
        </el-dialog> -->

        <!-- Modal DatePicker -->
        <el-dialog
            v-model="dialogVisible"
            :title="
                selectedTypeCuti?.tipe_select_tanggal == 'RANGE'
                    ? 'Pilih Range Tanggal Mulai dan Selesai'
                    : 'Pilih satu atau beberapa Tanggal'
            "
            width="480px"
            align-center
            :lock-scroll="true"
            :close-on-click-modal="false"
            :show-close="false"
        >
            <div class="calendar-lazy-wrapper" :class="{ 'is-loading': loading.calendarData }">
                <el-calendar v-model="pengajuanForm.calendarDate" :disabled-date="disableCalendarDates">
                    <template #date-cell="{ data }">
                        <el-tooltip
                            :disabled="loading.calendarData || !getSpecialDateTooltip(data.date)"
                            :content="getSpecialDateTooltip(data.date)"
                            placement="top"
                            popper-class="dayoff-calendar-tooltip"
                            :show-after="120"
                        >
                            <div
                                class="cell-date-wrapper"
                                :class="getCellClass(data)"
                                @click.stop="handleDateClick(data)"
                            >
                                <div
                                    class="date-cell"
                                    :class="{
                                        'is-disabled-custom':
                                            isCalendarDateBlockedForSelection(data.date) || loading.calendarData,
                                    }"
                                >
                                    {{ data.day.split('-')[2] }}
                                </div>
                            </div>
                        </el-tooltip>
                    </template>
                </el-calendar>
                <div v-if="loading.calendarData" class="calendar-loading-overlay">
                    <div class="calendar-loading-card">
                        <div
                            class="spinner-border mb-2"
                            style="color: #4f46e5; width: 1.5rem; height: 1.5rem"
                            role="status"
                        >
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <strong>Memuat kalender</strong>
                        <span>Sedang sinkron data libur, izin, dan day off.</span>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="dialog-footer gap-3 d-flex justify-content-between footerModal">
                    <div>
                        <div class="px-2 rounded d-flex justify-content-center align-items-center bg-primary">
                            <div v-if="!pengajuanForm.selectedDates" class="text-white fontFooter">Pilih Tanggal</div>
                            <!-- <div
                                v-if="tempStartDate && !tempEndDate"
                                class="text-white fontFooter"
                            >
                                Pilih Tanggal Selesai
                            </div> -->
                            <div v-else class="text-white fontFooter">{{ pengajuanForm.jumlah_hari }} Hari dipilih</div>
                        </div>
                        <!-- <div
                        style="font-size: 0.8rem"
                        class="text-danger text-start"
                    >
                        Klik 2 kali jika ingin 1 hari!
                    </div> -->
                    </div>
                    <div class="d-flex gap-2">
                        <el-button
                            style="cursor: pointer !important"
                            @click="closeDatePicker()"
                            class="btn-modern btn-secondary p-1 rounded-3 btnModal"
                            >Batal</el-button
                        >
                        <el-button
                            style="cursor: pointer !important"
                            class="btn-modern btn-success py-1 rounded-3 px-1 btnModal"
                            :disabled="pengajuanForm.jumlah_hari == 0 || loading.calendarData"
                            @click="confirmDate('multiple')"
                        >
                            Pilih Tanggal
                        </el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            :title="`Detail Pengajuan - ${selectedRiwayat?.No_Transaksi || ''}`"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="detailLoading" class="loading">
                    <div class="loading-state">
                        <div class="loading-spinner">
                            <div
                                class="spinner-border text-primary"
                                role="status"
                                style="width: 2.5rem; height: 2.5rem"
                            >
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <h4 class="loading-title">Memuat Data...</h4>
                        <p class="loading-description">Sedang mengambil data detail</p>
                    </div>
                </div>
                <div v-else-if="selectedRiwayat" class="detail-content-v2">
                    <div class="row">
                        <!-- Kolom Kiri: Info Karyawan & Status -->
                        <div class="col-md-5 detail-left-col mb-3">
                            <div class="detail-employee-info">
                                <div class="avatar-lg">
                                    {{ getInitials(selectedRiwayat.requester_Nama) }}
                                </div>
                                <h5 class="employee-name">
                                    {{ selectedRiwayat.requester_Nama }}
                                </h5>
                                <p class="employee-meta">
                                    {{ selectedRiwayat.Tipe_Izin2 || selectedRiwayat.Tipe_Izin }}
                                </p>
                                <p class="employee-meta text-muted">
                                    No. Transaksi:
                                    {{ selectedRiwayat.No_Transaksi }}
                                </p>
                            </div>
                            <div class="detail-status-section">
                                <h6 class="section-title">Status Pengajuan</h6>
                                <Badge
                                    :type="
                                        selectedRiwayat.status === 'pending'
                                            ? 'warning'
                                            : selectedRiwayat.status === 'approved'
                                              ? 'success'
                                              : 'danger'
                                    "
                                    class="w-100 justify-content-center"
                                >
                                    {{ getStatusText(selectedRiwayat.status) }}
                                </Badge>
                                <small class="text-muted mt-2 d-block">
                                    Diajukan pada:
                                    {{ formatDateTime(selectedRiwayat.tanggal_create) }}
                                </small>
                            </div>
                            <div
                                class="detail-sidebar"
                                v-if="selectedRiwayat?.approvers && selectedRiwayat?.approvers?.length > 0"
                            >
                                <div class="approval-flow">
                                    <h4 class="flow-title">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-diagram-3 me-2"
                                        ></i>
                                        Alur Persetujuan
                                    </h4>
                                    <el-steps
                                        direction="vertical"
                                        :active="getActiveStep(selectedRiwayat.approvers)"
                                        finish-status="success"
                                    >
                                        <el-step
                                            v-for="approver in selectedRiwayat.approvers"
                                            :key="approver.Request_Order_Flow"
                                            :title="approver.approver_name || 'Grup HC'"
                                            :description="getStepDescription(approver)"
                                            :status="getStepStatus(approver)"
                                        >
                                            <template #icon>
                                                <i :class="getStepIcon(approver)"></i>
                                            </template>
                                        </el-step>
                                    </el-steps>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Detail Izin & Jejak Persetujuan -->
                        <div class="col-md-7">
                            <div class="detail-izin-info">
                                <h6 class="section-title">Detail Izin</h6>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Jenis Izin</span>
                                        <span class="value">{{
                                            selectedRiwayat?.Tipe_Izin2 || selectedRiwayat?.Tipe_Izin
                                        }}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Tanggal Pengajuan</span>
                                        <span class="value">
                                            {{ selectedRiwayat?.tanggalReal.tanggal }}
                                        </span>
                                    </div>
                                    <!-- <div class="info-item">
                                        <span class="label"
                                            >Tanggal Selesai</span
                                        >
                                        <span class="value">
                                            {{
                                                formatDate(
                                                    selectedRiwayat?.Tanggal_Selesai
                                                )
                                            }}
                                        </span>
                                    </div> -->
                                    <div class="info-item">
                                        <span class="label">Jumlah Hari</span>
                                        <span class="value"
                                            >{{ selectedRiwayat?.tanggalReal?.jumlahHari || '1' }} hari</span
                                        >
                                    </div>
                                    <div class="info-item full-width">
                                        <span class="label">Alasan</span>
                                        <p class="value-reason">
                                            {{ selectedRiwayat?.Alasan }}
                                        </p>
                                    </div>
                                    <div v-if="selectedRiwayat?.Lampiran_Url" class="info-item">
                                        <span class="label">Lampiran</span>
                                        <a :href="selectedRiwayat?.Lampiran_Url" target="_blank" class="file-link">
                                            <i class="bi bi-paperclip me-2"></i>Lihat File
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Approval Flow -->
                            <div class="approval-trace-section-v2 mt-4">
                                <h6 class="section-title">Status Persetujuan</h6>
                                <div class="approval-status-info">
                                    <div class="status-item">
                                        <span class="status-label">Approver Saat Ini:</span>
                                        <span class="status-value">{{
                                            selectedRiwayat?.Approver || 'Tidak tersedia'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Status Saat Ini:</span>
                                        <span class="status-value">{{
                                            selectedRiwayat?.Status_Approval_Saat_Ini || 'Pending'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Approver Sebelumnya:</span>
                                        <span class="status-value">{{
                                            selectedRiwayat?.Status_Approval_Langkah_Sebelumnya || 'Tidak ada'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Urutan Flow:</span>
                                        <span class="status-value"
                                            >{{ selectedRiwayat?.order_flow }} / {{ selectedRiwayat?.MaxFlow }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-state text-center py-4">
                    <i class="bi bi-exclamation-circle fs-1 text-muted d-block mb-2"></i>
                    <h5 class="mb-1">Detail pengajuan tidak tersedia</h5>
                    <p class="text-muted mb-0">Data riwayat belum berhasil dimuat atau sudah tidak ditemukan.</p>
                </div>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeDetailModal">Tutup</button>
            </template>
        </Modal>

        <Modal
            v-if="showSubmitPreviewModal"
            :show="showSubmitPreviewModal"
            title="Konfirmasi Pengajuan Cuti"
            size="medium"
            @close="closeSubmitPreview"
        >
            <template #body>
                <div class="submit-preview-content">
                    <div class="submit-preview-alert">
                        <i class="bi bi-info-circle"></i>
                        <span>Pastikan data pengajuan sudah benar sebelum dikirim ke approver.</span>
                    </div>

                    <div class="submit-preview-grid">
                        <div class="submit-preview-item">
                            <span class="submit-preview-label">Jenis Cuti</span>
                            <strong>{{ submitPreview.jenisCuti }}</strong>
                        </div>
                        <div class="submit-preview-item">
                            <span class="submit-preview-label">Jumlah Hari</span>
                            <strong>{{ submitPreview.jumlahHari }} hari</strong>
                        </div>
                        <div class="submit-preview-item full-width">
                            <span class="submit-preview-label">Tanggal Diajukan</span>
                            <strong>{{ submitPreview.tanggal }}</strong>
                        </div>
                        <div v-if="submitPreview.requestedRange" class="submit-preview-item full-width">
                            <span class="submit-preview-label">Range Dipilih</span>
                            <strong>{{ submitPreview.requestedRange }}</strong>
                        </div>
                        <div class="submit-preview-item">
                            <span class="submit-preview-label">Memotong Saldo</span>
                            <strong>{{ submitPreview.memotongSaldo }}</strong>
                        </div>
                        <div class="submit-preview-item">
                            <span class="submit-preview-label">Estimasi Sisa/Hutang</span>
                            <strong>{{ submitPreview.saldoImpact }}</strong>
                        </div>
                        <div class="submit-preview-item full-width">
                            <span class="submit-preview-label">Alasan</span>
                            <p>{{ submitPreview.alasan }}</p>
                        </div>
                        <div class="submit-preview-item full-width">
                            <span class="submit-preview-label">Dokumen Pendukung</span>
                            <strong>{{ submitPreview.fileName }}</strong>
                            <button
                                v-if="pengajuanForm.file_pendukung"
                                type="button"
                                class="submit-preview-link"
                                @click="previewFile(pengajuanForm.file_pendukung)"
                            >
                                <i class="bi bi-eye me-1"></i>
                                Lihat Dokumen
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" :disabled="isSubmitting" @click="closeSubmitPreview">
                    Periksa Lagi
                </button>
                <button
                    class="btn-modern btn-primary"
                    :disabled="isSubmitting || !canSubmitPengajuan"
                    @click="confirmSubmitPengajuan"
                >
                    <span
                        v-if="isSubmitting"
                        class="spinner-border spinner-border-sm me-2"
                        role="status"
                        aria-hidden="true"
                    ></span>
                    {{ isSubmitting ? 'Mengirim...' : 'Kirim Pengajuan' }}
                </button>
            </template>
        </Modal>

        <Modal
            v-if="showCancelModal"
            :show="showCancelModal"
            title="Konfirmasi Pembatalan"
            size="medium"
            @close="closeCancelModal()"
        >
            <template #body>
                <div class="confirmation-content d-flex flex-column d-flex justify-content-center align-items-center">
                    <i
                        class="bi mb-4 d-flex justify-content-center align-items-center bi-exclamation-triangle warning-icon"
                    ></i>
                    <p>Apakah Anda yakin ingin membatalkan pengajuan cuti ini?</p>
                </div>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeCancelModal()">Batal</button>
                <button class="btn-modern btn-danger" @click="confirmCancel(pengajuanToCancel)">
                    <span v-if="!loading.destroy">
                        <i class="bi d-flex justify-content-center align-items-center bi-trash"></i>
                    </span>
                    <lord-icon
                        v-else
                        src="https://cdn.lordicon.com/ibckyoan.json"
                        trigger="loop"
                        delay="0"
                        colors="primary:#ffffff"
                        style="width: 25px; height: 25px"
                    />
                    <span v-if="!loading.destroy" class="ms-2"> Ya, Batalkan </span>
                    <span v-else class="ms-2"> Memuat... </span>
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import HeaderKpi from '@/components/legacy/header.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import Table from '../../../../components/ui/table.vue';
import Badge from '../../../../components/ui/badge.vue';
import DateTimePicker from '../../../../components/ui/DatePicker.vue';
import quickStat from '@/components/legacy/quickStat.vue';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import vSelect from 'vue-select';
import axios from 'axios';
import { ElMessage, ElNotification, ElDialog, ElTooltip, ElCalendar, ElButton, ElSteps, ElStep } from 'element-plus';
import 'element-plus/dist/index.css';
import ModernDataTable from '@/components/legacy/table-noir.vue';
import FilterPanel from '@/components/legacy/Filter/modernFilter.vue';
// import { clearConfig } from "dompurify";

export default {
    name: 'KaryawanCutiOptimized',
    components: {
        ApprovalTabs,
        DotLottieVue,
        ElButton,
        HeaderKpi,
        Modal,
        ModernDataTable,
        Table,
        Badge,
        DateTimePicker,
        vSelect,
        quickStat,
        ElMessage,
        ElNotification,
        ElDialog,
        FilterPanel,
        ElTooltip,
        ElCalendar,
        ElSteps,
        ElStep,
    },
    data() {
        return {
            riwayatFilters: {
                search: '',
                status: '',
                tanggal: '', // Pastikan ini ada dan sesuai dengan key di config
                jenis_izin: '',
            },

            riwayatData: {
                data: [],
                pagination: {
                    total: 0,
                    per_page: 10,
                    current_page: 1,
                    total_pages: 1,
                    first_page_url: null,
                    last_page_url: null,
                    next_page_url: null,
                    prev_page_url: null,
                    from: 0,
                    to: 0,
                    links: [],
                },
            },
            riwayatColumns: [
                {
                    key: 'No_Transaksi',
                    title: 'No. Transaksi',
                    icon: 'bi bi-hash',
                    showCount: true,
                    sticky: true,

                    cellClass: 'employee-cell',
                },
                // {
                //     key: "requester_Nama",
                //     title: "Karyawan",
                //     icon: "bi bi-person",
                //     sticky: true,
                //     formatter: (value, item) => item.requester_Nama,
                //     cellClass: "employee-cell",
                // },
                {
                    key: 'Tipe_Izin',
                    title: 'Jenis Izin',
                    icon: 'bi bi-tag',
                    formatter: (value, item) => this.getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin || ''),
                },
                {
                    key: 'TanggalIzin',
                    title: 'Tanggal',
                    icon: 'bi bi-calendar',
                    type: 'date',
                    formatter: (value, item) => this.formatRiwayatDate(item),
                },
                {
                    key: 'tanggalReal',
                    title: 'Durasi',
                    icon: 'bi bi-clock',
                    formatter: (value, item) => `${item?.tanggalReal?.jumlahHari || '1'} hari`,
                },
                {
                    key: 'status',
                    title: 'Status',
                    icon: 'bi bi-check-circle',
                    formatter: (value, item) => this.getStatusText(item?.status),
                    cellClass: 'status-cell',
                },
                {
                    key: 'actions',
                    title: 'Aksi',
                    icon: 'bi bi-three-dots',
                    showInMobile: false,
                },
            ],

            // Mobile columns untuk card view
            riwayatMobileColumns: [
                {
                    key: 'No_Transaksi',
                    title: 'No. Transaksi',
                    icon: 'bi bi-hash',
                },
                {
                    key: 'TanggalIzin',
                    title: 'Tanggal',
                    icon: 'bi bi-calendar',
                },
                {
                    key: 'tanggalReal',
                    title: 'Durasi',
                    icon: 'bi bi-clock',
                },
                {
                    key: 'Alasan',
                    title: 'Alasan',
                    icon: 'bi bi-chat-left-text',
                },
            ],
            fileError: '',
            uploadProgress: 0,
            tempEndDate: null,
            tempStartDate: null,
            serverTime: null,
            dialogVisible: false,
            specialDates: [],
            dayOffDates: [],
            TanggalPulangTerlambat: [],
            TanggalSakitIzin: [],
            TanggalCuti: [],
            TanggalCutiBersama: [],
            TanggalKonversiLemburPending: [],
            sisaCuti: 0,
            hutangCuti: 0,
            // Dihitung server lewat /check-saldo. JANGAN diturunkan dari
            // riwayatData: data itu ter-paginasi dan ikut tersaring, jadi
            // apa pun yang dihitung darinya hanya menghitung halaman aktif.
            pengajuanPending: 0,
            hutangUsage: { loading: false, data: null },
            showHutangRincian: false,
            showMobileHutangModal: false,
            showMobileSummaryModal: false,
            dateRange: null,
            currentDate: new Date(),
            activeTab: 'ajukan',
            filters: {
                searchQuery: '',
                status: '',
                perPage: 10,
                tanggalFilter: null,
                currentPage: 1,
            },

            // Form data
            pengajuanForm: {
                jenis_cuti_id: '',
                dateRange: null,
                requestedRange: null,
                selectedDates: [],
                jumlah_hari: 0,
                alasan: '',
                file_pendukung: null,
                calendarDate: new Date(),
                hasSelectedDates: false,
            },

            // UI state
            isSubmitting: false,
            validationErrors: [],
            detailLoading: false,
            saldoWarning: '',
            cutiBersamaInfo: '',
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),
            approvalFlowPreview: {
                loading: false,
                steps: [],
                items: [],
                isValid: true,
                hasWarning: false,
                warnings: [],
                jenisCutiId: null,
                nama: '',
                perluPersetujuan: false,
            },

            // Modals
            showDetailModal: false,
            showCancelModal: false,
            showSubmitPreviewModal: false,
            selectedRiwayat: null,
            pengajuanToCancel: null,
            selectedTypeCuti: null,

            // Data (akan diisi dengan dummy data)
            jenisCutiList: [],
            riwayatList: [],
            loading: {
                riwayat: false,
                detail: false,
                destroy: false,
                jenisCuti: false,
                dataCurrent: false,
                calendarData: false,
            },
            saldoCuti: [],
            saldoDetail: [],
            calendarEvents: [],
            calendarLoadedRanges: [],
            calendarDataRequestId: 0,
        };
    },
    props: {
        durasiMasuk: { type: Number, default: 0 },
        specialDate: { type: Array, default: [] },
        gender: { type: String, default: 'Laki-Laki' },
    },
    computed: {
        jenisIzinOptions() {
            const jenisCutiList = Array.isArray(this.jenisCutiList)
                ? this.jenisCutiList.filter((item) => item && typeof item === 'object')
                : [];

            return jenisCutiList.map((jenis) => ({
                value: jenis.id,
                label: jenis.nama,
            }));
        },

        riwayatFilterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari nomor transaksi, nama, atau alasan...',
                searchCol: 3,
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: true,
                filters: [
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        col: 6,
                        options: [
                            { value: '', label: 'Semua Status' },
                            { value: 'expired', label: 'Expired' },
                            { value: 'approved', label: 'Disetujui' },
                            { value: 'rejected', label: 'Ditolak' },
                            { value: 'pending', label: 'Menunggu' },
                        ],
                    },
                    {
                        key: 'jenis_izin',
                        label: 'Jenis Izin',
                        type: 'select',
                        col: 6,
                        options: this.jenisIzinOptions, // ← pakai computed
                    },
                ],
            };
        },
        filteredRiwayatData() {
            return Array.isArray(this.riwayatData?.data)
                ? this.riwayatData.data.filter((item) => item && typeof item === 'object')
                : []; // Default ke server-side filtering
        },
        hutangCalculation() {
            if (this.pengajuanForm.jumlah_hari === 0) {
                return {
                    akan_berhutang: 0,
                    sisa_setelah_cuti: this.sisaCuti,
                    durasi_cuti: 0,
                };
            }

            const sisaSetelahCuti = this.sisaCuti - this.pengajuanForm.jumlah_hari;

            return {
                akan_berhutang: sisaSetelahCuti < 0 ? Math.abs(sisaSetelahCuti) : 0,
                sisa_setelah_cuti: sisaSetelahCuti >= 0 ? sisaSetelahCuti : 0,
                durasi_cuti: this.pengajuanForm.jumlah_hari,
            };
        },

        // Ringkasan ketentuan hutang cuti untuk jenis cuti terpilih (sumber kebenaran UI)
        hutangLimitInfo() {
            const t = this.selectedTypeCuti;
            const empty = {
                applicable: false,
                bisaHutang: false,
                maks: null,
                mode: null,
                basis: null,
                perhitungan: null,
                terpakai: null,
                sisaJatah: null,
                akanBerhutang: 0,
                exceeds: false,
                blockedNoDebt: false,
                periodeLabel: '',
                perhitunganLabel: '',
            };
            if (!t || !t.memotong_saldo) return empty;

            const num = (v) => (v === null || v === undefined ? null : Number(v));
            const bisaHutang = !!t.bisa_hutang_cuti;
            const maks = num(t.maks_hutang_cuti);
            const mode = t.hutang_cuti_mode || 'OUTSTANDING';
            const basis = t.hutang_cuti_basis_periode || 'KALENDER';
            const perhitungan = t.hutang_periodik_perhitungan || 'GROSS';
            const terpakai = num(t.hutang_terpakai);
            const sisaJatah = num(t.sisa_jatah_hutang);
            const akanBerhutang = this.hutangCalculation.akan_berhutang;

            const periodeLabel =
                mode === 'PERIODIK'
                    ? basis === 'JOIN_DATE'
                        ? 'per tahun kerja (dihitung dari tanggal masuk)'
                        : 'per tahun kalender (Jan–Des)'
                    : 'total berjalan (berkurang saat dilunasi)';

            const perhitunganLabel =
                mode === 'PERIODIK'
                    ? perhitungan === 'NETTO'
                        ? 'Melunasi hutang menambah kembali jatah di periode ini.'
                        : 'Hutang yang sudah lunas tetap dihitung; hanya yang dibatalkan (rollback) yang tidak.'
                    : '';

            const exceeds = bisaHutang && maks !== null && maks > 0 && sisaJatah !== null && akanBerhutang > sisaJatah;
            const blockedNoDebt = !bisaHutang && akanBerhutang > 0;

            return {
                applicable: true,
                bisaHutang,
                maks,
                mode,
                basis,
                perhitungan,
                terpakai,
                sisaJatah,
                akanBerhutang,
                exceeds,
                blockedNoDebt,
                periodeLabel,
                perhitunganLabel,
            };
        },

        // Persen progress hutang terpakai (untuk bar)
        hutangQuotaPercent() {
            const i = this.hutangLimitInfo;
            if (!i.maks || i.terpakai === null) return 0;
            return Math.min(100, Math.max(0, Math.round((i.terpakai / i.maks) * 100)));
        },

        // Kalimat ringkas yang menjelaskan situasi jatah hutang ke user (bahasa mudah)
        hutangSummary() {
            const u = this.hutangUsage.data;
            if (!u || u.maks === null || u.maks === undefined) return '';
            const sat = u.jenis?.satuan || 'hari';
            const nettoNote = u.perhitungan === 'NETTO';

            if (u.terpakai >= u.maks) {
                let s = `Tahun ${u.tahun} jatah hutang cuti Anda terpakai ${u.terpakai} dari ${u.maks} ${sat} — sudah habis.`;
                s += nettoNote
                    ? ' Lunasi hutang untuk menambah kembali jatah.'
                    : ` Jatah kembali penuh ${this.formatTanggalIndo(u.reset_date)}.`;
                return s;
            }
            if (u.terpakai > 0) {
                return `Tahun ${u.tahun} jatah hutang cuti terpakai ${u.terpakai} dari ${u.maks} ${sat}. Sisa ${u.sisa} ${sat}.`;
            }
            return `Anda belum memakai jatah hutang cuti tahun ${u.tahun}. Jatah penuh ${u.maks} ${sat}.`;
        },

        // Ringkasan status untuk sticky bar hutang di mobile.
        // Info HUTANG hanya ditonjolkan saat pengajuan akan menembus saldo (akan berhutang)
        // atau saat saldo memang sudah habis. Selama saldo masih ada, tampilkan SALDO (bukan hutang).
        mobileBarStatus() {
            const i = this.hutangLimitInfo;
            const sat = this.selectedTypeCuti?.satuan || 'hari';
            const saldo = Number(this.sisaCuti) || 0;

            // 1) Sudah pilih tanggal & pengajuan akan jadi hutang → status hutang (paling relevan)
            if (i.blockedNoDebt) {
                return { text: 'Saldo tak cukup · tidak boleh hutang', cls: 'is-error', icon: 'bi-x-octagon-fill' };
            }
            if (i.exceeds) {
                return { text: 'Hutang melebihi batas jatah', cls: 'is-error', icon: 'bi-x-octagon-fill' };
            }
            if (i.akanBerhutang > 0) {
                return {
                    text: `Akan berhutang ${i.akanBerhutang} ${sat}`,
                    cls: 'is-warning',
                    icon: 'bi-exclamation-triangle-fill',
                };
            }

            // 2) Masih punya saldo & belum akan hutang → tampilkan SALDO, bukan info hutang
            if (saldo > 0) {
                return { text: `Saldo cuti Anda: ${saldo} ${sat}`, cls: 'is-ok', icon: 'bi-wallet2' };
            }

            // 3) Saldo habis → barulah info jatah hutang jadi relevan
            if (i.bisaHutang && i.maks !== null) {
                if (i.sisaJatah <= 0) {
                    return { text: 'Saldo habis · jatah hutang HABIS', cls: 'is-error', icon: 'bi-exclamation-octagon-fill' };
                }
                return {
                    text: `Saldo habis · sisa jatah hutang ${i.sisaJatah} ${sat}`,
                    cls: 'is-warning',
                    icon: 'bi-info-circle-fill',
                };
            }
            if (i.bisaHutang) {
                return { text: 'Saldo habis · boleh hutang cuti', cls: 'is-warning', icon: 'bi-info-circle-fill' };
            }
            return { text: 'Saldo cuti habis', cls: 'is-error', icon: 'bi-x-octagon-fill' };
        },

        // Status ringkas untuk sticky bar mobile — berlaku untuk SEMUA jenis cuti,
        // menyesuaikan konteks: masalah approval > hutang/saldo > approval siap > tanpa approval.
        mobileSummaryBar() {
            const perluApproval = !!this.selectedTypeCuti?.perlu_persetujuan_khusus;
            const approvalBermasalah =
                perluApproval && (!this.approvalFlowPreview.isValid || this.approvalFlowPreview.hasWarning);

            // 1) Masalah approval selalu paling penting (memblokir submit)
            if (approvalBermasalah) {
                const kurang = this.approvalShortfallCount;
                return {
                    text: kurang > 0 ? `Approval kurang ${kurang} tahap` : 'Alur approval belum lengkap',
                    cls: 'is-error',
                    icon: 'bi-exclamation-triangle-fill',
                };
            }

            // 2) Jenis cuti memotong saldo → tampilkan status hutang/saldo yang sudah ada
            if (this.selectedTypeCuti?.memotong_saldo) {
                return this.mobileBarStatus;
            }

            // 3) Perlu approval & sudah siap
            if (perluApproval) {
                const n = Array.isArray(this.approvalFlowPreview.steps)
                    ? this.approvalFlowPreview.steps.length
                    : 0;
                return {
                    text: n > 0 ? `Alur approval siap · ${n} tahap` : 'Alur approval siap',
                    cls: 'is-ok',
                    icon: 'bi-check-circle-fill',
                };
            }

            // 4) Tidak memotong saldo & tanpa approval khusus
            return {
                text: 'Cuti ini tidak perlu approval khusus',
                cls: 'is-ok',
                icon: 'bi-check-circle-fill',
            };
        },

        // Tampilkan warning hutang
        showHutangWarning() {
            return this.pengajuanForm.jumlah_hari > 0 && this.hutangCalculation.akan_berhutang > 0;
        },

        // Tampilkan info cuti cukup
        showCutiCukup() {
            return this.pengajuanForm.jumlah_hari > 0 && this.hutangCalculation.akan_berhutang === 0;
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
        isMobile() {
            return window.innerWidth < 768;
        },

        quickStatField() {
            return [
                {
                    title: 'Saldo Cuti',
                    value: this.sisaCuti,
                    unit: 'Hari',
                    icon: 'bi bi-calendar-check text-success',
                    bgIcon: 'bg-primary-light',
                    size: 'col-4',
                },
                {
                    title: 'Hutang Cuti',
                    value: this.hutangCuti,
                    unit: 'Hari',
                    icon: 'bi bi-credit-card text-danger',
                    bgIcon: 'bg-danger-light',
                    size: 'col-4',
                },
                {
                    title: 'Pengajuan Pending',
                    value: this.pengajuanPending,
                    unit: '',
                    icon: 'bi bi-clock-history text-warning',
                    bgIcon: 'bg-warning-light',
                    size: 'col-4',
                },
            ];
        },

        karyawanTabs() {
            return [
                {
                    id: 'ajukan',
                    label: 'Ajukan Cuti',
                    icon: 'bi bi-send-plus d-flex justify-content-center align-items-center',
                    count: null,
                },
                {
                    id: 'riwayat',
                    label: 'Riwayat',
                    icon: 'bi bi-clock-history d-flex justify-content-center align-items-center',
                    count: this.riwayatData?.pagination?.total || 0,
                },
            ];
        },

        filteredJenisCuti() {
            const jenisCutiList = Array.isArray(this.jenisCutiList)
                ? this.jenisCutiList.filter((item) => item && typeof item === 'object')
                : [];

            if (!this.filters.searchQuery) {
                return jenisCutiList.map((jenis) => {
                    const isMasaKerjaKurang = this.durasiMasuk < Number(jenis.minimal_masa_kerja || 0);
                    const genderKhusus = jenis.gender_spesifik && jenis.gender_spesifik != this.gender;

                    // Saldo/hutang TIDAK lagi men-disable pilihan. Biarkan user memilih agar bisa
                    // melihat penjelasan di kartu "Ketentuan Hutang Cuti" + guard saat submit.
                    // Hanya syarat keras (masa kerja & gender) yang mengunci pilihan.
                    const isDisabled = isMasaKerjaKurang || genderKhusus;

                    let keterangan = '';
                    if (isMasaKerjaKurang) {
                        keterangan = ` (Min. ${jenis.minimal_masa_kerja} Bulan Kerja)`;
                    } else if (genderKhusus) {
                        keterangan = ` (Hanya untuk ${jenis.gender_spesifik})`;
                    } else if (jenis.memotong_saldo && this.sisaCuti <= 0) {
                        keterangan = jenis.bisa_hutang_cuti ? ` (Saldo habis — bisa hutang)` : ` (Saldo habis)`;
                    }

                    return {
                        value: jenis.id,
                        label: `${jenis.nama || '-'}${keterangan}`,
                        original: jenis,
                        disabled: isDisabled,
                    };
                });
            }

            const query = this.filters.searchQuery.toLowerCase();

            return jenisCutiList
                .filter(
                    (jenis) =>
                        this.durasiMasuk >= Number(jenis.minimal_masa_kerja || 0) &&
                        (String(jenis.nama || '')
                            .toLowerCase()
                            .includes(query) ||
                            String(jenis.deskripsi || '')
                                .toLowerCase()
                                .includes(query)),
                )
                .map((jenis) => {
                    const sisaCuti = this.getSisaCuti(jenis.id);
                    // Saldo/hutang hanya informatif — tidak men-disable pilihan.
                    let keteranganSisa = jenis.memotong_saldo ? `(Sisa: ${sisaCuti} ${jenis.satuan || 'hari'})` : '';
                    if (jenis.memotong_saldo && sisaCuti !== null && sisaCuti <= 0) {
                        keteranganSisa = jenis.bisa_hutang_cuti ? `(Sisa: HABIS — bisa hutang)` : `(Sisa: HABIS)`;
                    }
                    return {
                        value: jenis.id,
                        label: `${jenis.nama || '-'} ${keteranganSisa}`.trim(),
                        original: jenis,
                        disabled: false,
                    };
                });

            // return this.jenisCutiList;
        },

        filteredRiwayat() {
            let filtered = this.riwayatData.data || [];
            // if (this.filters.status) {
            //     filtered = filtered.filter(
            //         (item) => item.status === this.filters.status
            //     );
            // }
            return filtered;
        },

        isFormValid() {
            return (
                this.pengajuanForm.jenis_cuti_id &&
                this.pengajuanForm.dateRange &&
                this.pengajuanForm.jumlah_hari > 0 &&
                this.pengajuanForm.alasan &&
                this.validationErrors.length === 0
            );
        },
        canSubmitPengajuan() {
            if (this.approvalFlowPreview.loading) return false;

            if (!this.selectedTypeCuti?.perlu_persetujuan_khusus) {
                return true;
            }

            return this.approvalFlowPreview.isValid && !this.approvalFlowPreview.hasWarning;
        },
        approvalShortfallCount() {
            const steps = Array.isArray(this.approvalFlowPreview.steps) ? this.approvalFlowPreview.steps : [];
            return steps.filter((step) => (step?.shortfall ?? 0) > 0 || step?.is_valid === false).length;
        },
        submitPreview() {
            const selectedDates = Array.isArray(this.pengajuanForm.selectedDates)
                ? this.pengajuanForm.selectedDates.map((date) => this.formatDateToYYYYMMDD(date))
                : [];
            const requestedRange =
                Array.isArray(this.pengajuanForm.requestedRange) && this.pengajuanForm.requestedRange.length === 2
                    ? this.formatHumanReadableDates(this.pengajuanForm.requestedRange)
                    : '';

            let saldoImpact = 'Tidak memotong saldo';
            if (this.selectedTypeCuti?.memotong_saldo) {
                saldoImpact =
                    this.hutangCalculation.akan_berhutang > 0
                        ? `Akan hutang ${this.hutangCalculation.akan_berhutang} hari`
                        : `Sisa ${this.hutangCalculation.sisa_setelah_cuti} hari`;
            }

            return {
                jenisCuti: this.selectedTypeCuti?.nama || '-',
                jumlahHari: this.pengajuanForm.jumlah_hari || selectedDates.length || 0,
                tanggal: selectedDates.length ? this.formatHumanReadableDates(selectedDates) : '-',
                requestedRange,
                memotongSaldo: this.selectedTypeCuti?.memotong_saldo ? 'Ya' : 'Tidak',
                saldoImpact,
                alasan: String(this.pengajuanForm.alasan || '').trim() || '-',
                fileName: this.pengajuanForm.file_pendukung
                    ? this.getFileName(this.pengajuanForm.file_pendukung)
                    : this.selectedTypeCuti?.dokumen_pendukung
                      ? 'Belum ada dokumen'
                      : 'Tidak ada dokumen',
            };
        },

        // Calendar computations
        weekdays() {
            return ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        },

        currentMonthYear() {
            return new Date(this.currentYear, this.currentMonth).toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            });
        },

        calendarDays() {
            const days = [];
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            const startDay = firstDay.getDay();
            const daysInMonth = lastDay.getDate();

            // Previous month days
            const prevMonthLastDay = new Date(this.currentYear, this.currentMonth, 0).getDate();
            for (let i = startDay - 1; i >= 0; i--) {
                days.push({
                    day: prevMonthLastDay - i,
                    date: new Date(this.currentYear, this.currentMonth - 1, prevMonthLastDay - i),
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
                return eventDate.getMonth() === this.currentMonth && eventDate.getFullYear() === this.currentYear;
            });
        },

        // Summary computations
        totalCutiTersedia() {
            return this.saldoDetail.reduce((total, saldo) => total + saldo.sisa, 0);
        },

        totalCutiDigunakan() {
            return this.saldoDetail.reduce((total, saldo) => total + saldo.digunakan, 0);
        },

        totalCutiKedaluwarsa() {
            const now = new Date();
            return this.saldoDetail
                .filter((saldo) => saldo.expired_date && new Date(saldo.expired_date) > now)
                .reduce((total, saldo) => total + saldo.sisa, 0);
        },
    },
    methods: {
        formatHumanReadableDates(dateArray) {
            if (!dateArray || dateArray.length === 0) return '';

            // 1. Parsing, Sorting, dan Hapus Duplikat
            const dates = dateArray.map((d) => new Date(d)).sort((a, b) => a - b); // Urutkan ascending

            // 2. Kelompokkan berdasarkan Tahun -> Bulan
            const groups = {};

            dates.forEach((d) => {
                const year = d.getFullYear();
                const monthIndex = d.getMonth(); // 0 = Jan, 11 = Dec
                const day = d.getDate();

                if (!groups[year]) groups[year] = {};
                if (!groups[year][monthIndex]) groups[year][monthIndex] = [];

                // Hindari duplikat tanggal di array yang sama
                if (!groups[year][monthIndex].includes(day)) {
                    groups[year][monthIndex].push(day);
                }
            });

            const resultParts = [];

            // 3. Loop per Tahun
            Object.keys(groups)
                .sort()
                .forEach((year) => {
                    const monthsInYear = groups[year];
                    const yearParts = [];

                    // 4. Loop per Bulan dalam Tahun tersebut
                    Object.keys(monthsInYear)
                        .sort((a, b) => a - b)
                        .forEach((monthIndex) => {
                            const days = monthsInYear[monthIndex]; // Array tanggal [21, 22, 23, 26]

                            // Logika Range (misal: 21, 22, 23 jadi "21 - 23")
                            const dayRanges = [];
                            let start = days[0];
                            let prev = days[0];

                            for (let i = 1; i < days.length; i++) {
                                if (days[i] === prev + 1) {
                                    prev = days[i]; // Lanjut range
                                } else {
                                    // Range terputus, simpan yang sebelumnya
                                    if (start === prev) {
                                        dayRanges.push(`${start}`);
                                    } else {
                                        dayRanges.push(`${start} - ${prev}`);
                                    }
                                    start = days[i];
                                    prev = days[i];
                                }
                            }
                            // Push range terakhir
                            if (start === prev) {
                                dayRanges.push(`${start}`);
                            } else {
                                dayRanges.push(`${start} - ${prev}`);
                            }

                            // Ambil Nama Bulan
                            const monthName = new Date(year, monthIndex, 1).toLocaleString('id-ID', { month: 'long' });

                            // Gabungkan tanggal dengan koma, lalu tempel nama bulan
                            // Contoh: "21 - 23, 26 Oktober"
                            yearParts.push(`${dayRanges.join(', ')} ${monthName}`);
                        });

                    // 5. Gabungkan semua bulan dalam tahun itu, lalu tempel Tahun di akhir
                    // Contoh: "21 Oktober, 25 Desember 2025"
                    resultParts.push(`${yearParts.join(', ')} ${year}`);
                });

            // 6. Gabungkan antar tahun dengan koma
            return resultParts.join(', ');
        },
        batalTransaksi(item) {
            this.showCancelModal = true;
            this.pengajuanToCancel = item;
        },
        async confirmCancel(item) {
            this.loading.destroy = true;
            try {
                const response = await axios.post('/api/v1/cuti/destroy', {
                    no_transaksi: item.No_Transaksi,
                });
                if (response.status == 200) {
                    ElNotification({
                        title: 'Sukses',
                        message: 'Pengajuan cuti berhasil dibatalkan.',
                        type: 'success',
                        duration: 3000,
                    });
                    this.loadRiwayat();
                }
            } catch (error) {
                let Elmess = 'Terjadi kesalahan saat membatalkan pengajuan.';
                if (error.response && error.response.data && error.response.data.message) {
                    Elmess = error.response.data.message;
                }
                ElNotification({
                    title: 'Error',
                    message: Elmess,
                    type: 'error',
                });
            } finally {
                this.closeCancelModal();
                this.loading.destroy = false;
            }
        },
        closeCancelModal() {
            this.showCancelModal = false;
            this.pengajuanToCancel = null;
        },
        closeDatePicker() {
            this.pengajuanForm.calendarDate = null;
            this.pengajuanForm.selectedDates = [];
            this.pengajuanForm.dateRange = null;
            this.pengajuanForm.requestedRange = null;
            this.pengajuanForm.jumlah_hari = 0;
            this.dialogVisible = null;
        },
        getCalendarWindowForDate(dateInput = new Date()) {
            const baseDate = dateInput ? new Date(dateInput) : new Date();
            const year = baseDate.getFullYear();
            const month = baseDate.getMonth();
            const start = new Date(year, month, 1);
            const end = new Date(year, month + 1, 0);

            start.setDate(start.getDate() - start.getDay());
            end.setDate(end.getDate() + (6 - end.getDay()));

            return {
                start: this.formatDateToYYYYMMDD(start),
                end: this.formatDateToYYYYMMDD(end),
            };
        },
        isCalendarRangeLoaded(start, end) {
            return this.calendarLoadedRanges.some((range) => range.start <= start && range.end >= end);
        },
        markCalendarRangeLoaded(start, end) {
            this.calendarLoadedRanges.push({ start, end });
        },
        mergeByKey(existing, incoming, keyResolver) {
            const map = new Map();

            [...(existing || []), ...(incoming || [])].forEach((item) => {
                if (!item) return;
                map.set(keyResolver(item), item);
            });

            return Array.from(map.values());
        },
        dateIsInsideRange(date, start, end) {
            const formatted = this.formatDateToYYYYMMDD(date);
            return formatted >= start && formatted <= end;
        },
        cutiBersamaOverlapsRange(item, start, end) {
            const itemStart = this.formatDateToYYYYMMDD(item.Tanggal_Mulai);
            const itemEnd = this.formatDateToYYYYMMDD(item.Tanggal_Selesai || item.Tanggal_Mulai);
            return itemStart <= end && itemEnd >= start;
        },
        pruneCalendarDataForRange(start, end) {
            this.specialDates = (this.specialDates || []).filter(
                (item) => !this.dateIsInsideRange(item.date || item.tanggal, start, end),
            );
            this.TanggalCuti = (this.TanggalCuti || []).filter(
                (item) => !this.dateIsInsideRange(item.Tanggal_Cuti, start, end),
            );
            this.TanggalSakitIzin = (this.TanggalSakitIzin || []).filter(
                (item) => !this.dateIsInsideRange(item.Tanggal_Sakit_Izin, start, end),
            );
            this.TanggalPulangTerlambat = (this.TanggalPulangTerlambat || []).filter(
                (item) => !this.dateIsInsideRange(this.getTerlambatPulangDate(item), start, end),
            );
            this.TanggalCutiBersama = (this.TanggalCutiBersama || []).filter(
                (item) => !this.cutiBersamaOverlapsRange(item, start, end),
            );
            this.dayOffDates = (this.dayOffDates || []).filter(
                (item) => !this.dateIsInsideRange(item.date, start, end),
            );
            this.TanggalKonversiLemburPending = (this.TanggalKonversiLemburPending || []).filter(
                (item) => !this.dateIsInsideRange(item.date, start, end),
            );
        },
        mergeCalendarData(data) {
            const tanggalLibur = (data.tanggalLibur || []).map((date) => ({
                tanggal: date.Tanggal,
                date: date.Tanggal,
                description: date.Nama_Hari_Libur ? date.Nama_Hari_Libur : date.No_Transaksi,
            }));

            this.specialDates = this.mergeByKey(this.specialDates, tanggalLibur, (item) =>
                this.formatDateToYYYYMMDD(item.date || item.tanggal),
            );
            this.TanggalCuti = this.mergeByKey(
                this.TanggalCuti,
                data.tanggalCuti || [],
                (item) => `${item.No_Transaksi || '-'}-${this.formatDateToYYYYMMDD(item.Tanggal_Cuti)}`,
            );
            this.TanggalSakitIzin = this.mergeByKey(
                this.TanggalSakitIzin,
                data.tanggalSakitIzin || [],
                (item) =>
                    `${item.No_Transaksi || '-'}-${item.Jenis || '-'}-${this.formatDateToYYYYMMDD(item.Tanggal_Sakit_Izin)}`,
            );
            this.TanggalPulangTerlambat = this.mergeByKey(
                this.TanggalPulangTerlambat,
                data.tanggalTerlambatPulangCepat || [],
                (item) =>
                    `${item.No_Transaksi || '-'}-${item.Jenis || '-'}-${this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(item))}`,
            );
            this.TanggalCutiBersama = this.mergeByKey(
                this.TanggalCutiBersama,
                data.tanggalCutiBersama || [],
                (item) =>
                    `${item.Id_Cuti_Bersama || '-'}-${this.formatDateToYYYYMMDD(item.Tanggal_Mulai)}-${this.formatDateToYYYYMMDD(item.Tanggal_Selesai)}`,
            );
            this.dayOffDates = this.mergeByKey(this.dayOffDates, data.tanggalDayOff || [], (item) =>
                this.formatDateToYYYYMMDD(item.date),
            );
            this.TanggalKonversiLemburPending = this.mergeByKey(
                this.TanggalKonversiLemburPending,
                data.tanggalKonversiLemburPending || [],
                (item) => this.formatDateToYYYYMMDD(item.date),
            );
        },
        async ensureCalendarDataForRange(start, end) {
            const normalizedStart = this.formatDateToYYYYMMDD(start);
            const normalizedEnd = this.formatDateToYYYYMMDD(end);

            if (this.isCalendarRangeLoaded(normalizedStart, normalizedEnd)) {
                return;
            }

            await this.getCurrentSpecialDate({
                start: normalizedStart,
                end: normalizedEnd,
            });
        },
        async loadCalendarDataForDate(date = null) {
            const window = this.getCalendarWindowForDate(date || this.pengajuanForm.calendarDate || new Date());
            await this.ensureCalendarDataForRange(window.start, window.end);
        },
        async getCurrentSpecialDate(range = null) {
            const window = range || this.getCalendarWindowForDate(this.pengajuanForm.calendarDate || new Date());
            const requestId = ++this.calendarDataRequestId;

            try {
                this.loading.calendarData = true;
                this.loading.dataCurrent = true;
                const response = await axios.get(`/api/v1/cuti/dataCurrent`, {
                    params: {
                        start: window.start,
                        end: window.end,
                    },
                });
                let allDate = response.data.result || [];

                if (requestId === this.calendarDataRequestId) {
                    this.pruneCalendarDataForRange(window.start, window.end);
                    this.mergeCalendarData(allDate);
                    this.markCalendarRangeLoaded(window.start, window.end);
                    this.pruneBlockedSelectedDates({ notify: true });
                }
            } catch (error) {
                console.error('Error fetching current special dates:', error);
            } finally {
                if (requestId === this.calendarDataRequestId) {
                    this.loading.dataCurrent = false;
                    this.loading.calendarData = false;
                }
            }
        },
        // handleDateClick(data) {
        //     // Raw date dari input (Date object)
        //     const rawDate = new Date(data.date);

        //     // Tanggal dalam format YYYY-MM-DD (string)
        //     const formattedDate = this.formatDateToYYYYMMDD(rawDate);

        //     // ==========================================
        //     // 1. VALIDASI BLOKIR (GUARD CLAUSES)
        //     // ==========================================

        //     // Cek Tanggal Lampau
        //     if (this.disablePastDates(rawDate)) {
        //         ElMessage.warning("Tanggal sudah lewat, tidak dapat dipilih.");
        //         return;
        //     }

        //     // Cek Hari Minggu
        //     if (rawDate.getDay() === 0) {
        //         ElMessage.warning("Tidak dapat memilih hari Minggu.");
        //         return;
        //     }

        //     // Cek Cuti Bersama
        //     if (this.isCutiBersama(formattedDate)) {
        //         ElMessage.warning("Tanggal tersebut adalah Cuti Bersama.");
        //         return;
        //     }

        //     // Cek Libur Nasional
        //     if (this.isSpecialDate(formattedDate)) {
        //         ElMessage.warning(
        //             "Tanggal tersebut adalah Hari Libur Nasional."
        //         );
        //         return;
        //     }

        //     // Cek Cuti Pribadi (Sudah pernah diajukan)
        //     if (this.isCutiPribadi(formattedDate)) {
        //         ElMessage.warning(
        //             "Anda sudah mengajukan cuti pada tanggal ini."
        //         );
        //         return;
        //     }

        //     // Cek Sakit / Izin
        //     if (this.isSakit(formattedDate) || this.isIzin(formattedDate)) {
        //         console.log("masuk ke handle sakit");
        //         alert("MASUK SAKIT/IZIN");
        //         ElMessage.warning("Anda tercatat Sakit/Izin pada tanggal ini.");
        //         return false;
        //     }

        //     // ==========================================
        //     // 2. LOGIKA PILIH / HAPUS TANGGAL
        //     // ==========================================

        //     // Check apakah tanggal ini sudah dipilih sebelumnya
        //     // const existingIndex = this.pengajuanForm.selectedDates.findIndex(
        //     //     (d) => d === formattedDate
        //     // );

        //     // if (existingIndex !== -1) {
        //     //     // Hapus tanggal
        //     //     this.pengajuanForm.selectedDates.splice(existingIndex, 1);
        //     // } else {
        //     //     // Tambah tanggal baru jika belum melebihi batas
        //     //     if (
        //     //         this.pengajuanForm.selectedDates.length + 1 >
        //     //         this.selectedTypeCuti.maksimum_pengajuan
        //     //     ) {
        //     //         ElMessage.warning(
        //     //             `Anda hanya bisa memilih maks. ${this.selectedTypeCuti.maksimum_pengajuan} hari untuk jenis cuti ini`
        //     //         );
        //     //         return;
        //     //     } else {
        //     //         this.pengajuanForm.selectedDates.push(formattedDate);
        //     //     }
        //     // }

        //     // // Update jumlah hari dan flag status form
        //     // this.pengajuanForm.jumlah_hari =
        //     //     this.pengajuanForm.selectedDates.length;
        //     // this.pengajuanForm.hasSelectedDates =
        //     //     this.pengajuanForm.jumlah_hari > 0;
        // },

        // Cek apakah pengajuan sejumlah `requestedDays` hari akan melebihi batas hutang cuti.
        // Return string pesan jika melebihi, atau null bila aman / tak terbatas.
        exceedsHutangLimit(requestedDays) {
            const jenis = this.selectedTypeCuti;
            if (!jenis || !jenis.memotong_saldo || !jenis.bisa_hutang_cuti) return null;

            const sisaJatah = jenis.sisa_jatah_hutang;
            if (sisaJatah === null || sisaJatah === undefined) return null; // tak terbatas

            const projectedDebt = Math.max(0, requestedDays - (this.sisaCuti || 0));
            if (projectedDebt > sisaJatah) {
                const periode = jenis.hutang_cuti_mode === 'PERIODIK' ? ' periode ini' : '';
                return `Melebihi batas hutang cuti${periode}. Perlu hutang ${projectedDebt} hari, sisa jatah hutang ${sisaJatah} hari.`;
            }
            return null;
        },

        async handleDateClick(date) {
            if (this.loading.calendarData) {
                ElMessage.info('Kalender sedang dimuat, tunggu sebentar.');
                return false;
            }

            const formatted = this.formatDateToYYYYMMDD(date.date);

            const tipe_select = this.selectedTypeCuti.tipe_select_tanggal;
            const bisaHutang = this.selectedTypeCuti.bisa_hutang_cuti;

            let existingIndex = this.pengajuanForm.selectedDates.findIndex((d) => d === formatted);
            const selectionBlock = this.getCalendarSelectionBlock(formatted);
            if (selectionBlock.blocked) {
                if (existingIndex !== -1) {
                    this.pengajuanForm.selectedDates.splice(existingIndex, 1);
                    this.pengajuanForm.requestedRange = null;
                    this.syncSelectedDateState();
                }

                ElMessage.warning(selectionBlock.message);
                return false;
            }

            this.pruneBlockedSelectedDates({ notify: true });
            existingIndex = this.pengajuanForm.selectedDates.findIndex((d) => d === formatted);

            // ==========================================================
            //  MODE RANGE SELECT
            // ==========================================================
            // console.log(tipe_select);
            if (tipe_select === 'RANGE') {
                // console.log("masuk range");

                // Guard saat MEMULAI pilihan baru (0 atau sudah 2 tanggal → klik ini jadi 1 hari).
                // Saat length === 1 (menyelesaikan range) validasi ada di bawah (per range.length).
                const memulaiBaru = this.pengajuanForm.selectedDates.length !== 1;
                if (memulaiBaru) {
                    if (!bisaHutang && this.selectedTypeCuti.memotong_saldo && 1 > this.sisaCuti) {
                        ElMessage.warning(`Sisa Cuti anda tinggal ${this.sisaCuti}, tidak bisa hutang cuti.`);
                        return false;
                    }
                    const hutangMsgFirst = this.exceedsHutangLimit(1);
                    if (hutangMsgFirst) {
                        ElMessage.warning(hutangMsgFirst);
                        return false;
                    }
                }

                // ---- Jika sudah ada 2 tanggal → reset jadi start baru
                if (this.pengajuanForm.selectedDates.length >= 2) {
                    this.pengajuanForm.selectedDates = [formatted];
                    this.pengajuanForm.requestedRange = null;
                }

                // ---- Kalau sudah punya 1 tanggal → generate range
                else if (this.pengajuanForm.selectedDates.length === 1) {
                    const dateStart = this.pengajuanForm.selectedDates[0];

                    let start = new Date(dateStart);
                    let end = new Date(formatted);

                    // Urutkan agar selalu start <= end
                    if (start > end) {
                        const tmp = start;
                        start = end;
                        end = tmp;
                    }

                    const requestedRange = [this.formatDateToYYYYMMDD(start), this.formatDateToYYYYMMDD(end)];

                    await this.ensureCalendarDataForRange(
                        this.formatDateToYYYYMMDD(start),
                        this.formatDateToYYYYMMDD(end),
                    );

                    const rangeResult = this.buildSelectableDatesInRange(start, end);
                    const range = rangeResult.selectedDates;

                    if (rangeResult.blockedPastDate) {
                        ElMessage.warning('Range mengandung tanggal yang belum boleh diajukan.');

                        this.pengajuanForm.selectedDates = [dateStart];
                        this.pengajuanForm.jumlah_hari = 1;
                        this.pengajuanForm.hasSelectedDates = true;
                        return false;
                    }

                    if (range.length === 0) {
                        ElMessage.warning('Tidak ada tanggal kerja yang bisa diajukan dalam range ini.');

                        this.pengajuanForm.selectedDates = [dateStart];
                        this.pengajuanForm.jumlah_hari = 1;
                        this.pengajuanForm.hasSelectedDates = true;
                        return false;
                    }

                    // === VALIDASI SISA CUTI
                    if (!bisaHutang && this.selectedTypeCuti.memotong_saldo && range.length > this.sisaCuti) {
                        ElMessage.warning(`Range melebihi sisa cuti (${this.sisaCuti}). Tidak bisa hutang cuti.`);

                        // Reset hanya tanggal awal
                        this.pengajuanForm.selectedDates = [dateStart];
                        this.pengajuanForm.jumlah_hari = 1;
                        this.pengajuanForm.hasSelectedDates = true;
                        return false;
                    }

                    // === VALIDASI BATAS HUTANG CUTI (maks / periodik)
                    const hutangMsgRange = this.exceedsHutangLimit(range.length);
                    if (hutangMsgRange) {
                        ElMessage.warning(hutangMsgRange);

                        // Reset hanya tanggal awal
                        this.pengajuanForm.selectedDates = [dateStart];
                        this.pengajuanForm.jumlah_hari = 1;
                        this.pengajuanForm.hasSelectedDates = true;
                        return false;
                    }

                    // === VALIDASI MAKS PENGAJUAN
                    if (range.length > this.selectedTypeCuti.maksimum_pengajuan) {
                        ElMessage.warning(
                            `Maksimum ${this.selectedTypeCuti.maksimum_pengajuan} hari untuk jenis cuti ini`,
                        );

                        // Reset hanya tanggal awal
                        this.pengajuanForm.selectedDates = [dateStart];
                        this.pengajuanForm.jumlah_hari = 1;
                        this.pengajuanForm.hasSelectedDates = true;
                        return false;
                    }
                    // === Jika semua aman, commit range
                    this.pengajuanForm.selectedDates = range;
                    this.pengajuanForm.requestedRange = requestedRange;
                    if (rangeResult.skippedDates.length > 0) {
                        ElMessage.info(
                            `${range.length} hari kerja dipilih, ${rangeResult.skippedDates.length} tanggal libur/day off dilewati.`,
                        );
                    }
                }
                // Tanggal pertama dipilih range
                else {
                    if (this.disablePastDates(formatted)) {
                        ElMessage.warning('Tanggal sudah lewat, tidak dapat dipilih.');
                        return false;
                    }

                    const check = this.isSpecialDate(formatted);

                    // === VALIDASI BLOK TANGGAL
                    if (check.isBlocked) {
                        ElMessage.warning(this.getBlockedDateMessage(check, formatted));
                        return false;
                    }
                    this.pengajuanForm.selectedDates = [formatted];
                    this.pengajuanForm.requestedRange = null;
                }

                // UPDATE UI
                this.pengajuanForm.jumlah_hari = this.pengajuanForm.selectedDates.length;
                this.pengajuanForm.hasSelectedDates = this.pengajuanForm.jumlah_hari > 0;

                return true;
            }

            // ==========================================================
            //  MODE MULTIPLE SELECT
            // ==========================================================
            else {
                // Klik tanggal yang sudah dipilih → unselect
                if (existingIndex !== -1) {
                    this.pengajuanForm.selectedDates.splice(existingIndex, 1);
                    this.pengajuanForm.requestedRange = null;
                } else {
                    if (this.disablePastDates(formatted)) {
                        ElMessage.warning('Tanggal sudah lewat, tidak dapat dipilih.');
                        return false;
                    }
                    const check = this.isSpecialDate(formatted);

                    // === VALIDASI BLOK TANGGAL
                    if (check.isBlocked) {
                        ElMessage.warning(this.getBlockedDateMessage(check, formatted));
                        return false;
                    }

                    // === VALIDASI SISA CUTI
                    if (
                        !bisaHutang &&
                        this.selectedTypeCuti.memotong_saldo &&
                        this.pengajuanForm.selectedDates.length + 1 > this.sisaCuti
                    ) {
                        ElMessage.warning(`Sisa Cuti anda tinggal ${this.sisaCuti}, tidak bisa hutang cuti.`);
                        return false;
                    }

                    // === VALIDASI BATAS HUTANG CUTI (maks / periodik)
                    const hutangMsgMulti = this.exceedsHutangLimit(this.pengajuanForm.selectedDates.length + 1);
                    if (hutangMsgMulti) {
                        ElMessage.warning(hutangMsgMulti);
                        return false;
                    }

                    // === VALIDASI MAKS PENGAJUAN
                    if (this.pengajuanForm.selectedDates.length + 1 > this.selectedTypeCuti.maksimum_pengajuan) {
                        ElMessage.warning(
                            `Maksimum ${this.selectedTypeCuti.maksimum_pengajuan} hari untuk jenis cuti ini`,
                        );
                        return false;
                    }

                    // Jika aman → push tanggal
                    this.pengajuanForm.selectedDates.push(formatted);
                    this.pengajuanForm.requestedRange = null;
                }
                // UPDATE UI
                this.pengajuanForm.jumlah_hari = this.pengajuanForm.selectedDates.length;
                this.pengajuanForm.hasSelectedDates = this.pengajuanForm.jumlah_hari > 0;
                return true;
            }
        },
        getDateCheck(formatted) {
            const found = this.specialDates.find((d) => d.tanggal === formatted);

            return {
                isBlocked: found ? found.isBlocked : false,
                isNationalHoliday: found?.isNationalHoliday || false,
                isCutiBersama: found?.isCutiBersama || false,
                isCutiPribadi: found?.isCutiPribadi || false,
                isSakit: found?.isSakit || false,
                isIzin: found?.isIzin || false,
                isPulangCepat: found?.isPulangCepat || false,
                isTerlambat: found?.isTerlambat || false,
            };
        },
        findDayOffEntry(date) {
            const formatted = this.formatDateToYYYYMMDD(date);
            return (this.dayOffDates || []).find((item) => item?.date === formatted);
        },
        isDayOffDate(date) {
            return !!this.findDayOffEntry(date);
        },
        findKonversiPendingEntry(date) {
            const formatted = this.formatDateToYYYYMMDD(date);
            return (this.TanggalKonversiLemburPending || []).find((item) => item?.date === formatted);
        },
        isKonversiPendingDate(date) {
            return !!this.findKonversiPendingEntry(date);
        },
        formatDayOffSuffix(text) {
            return String(text || '')
                .replace(/[_-]+/g, ' ')
                .split(' ')
                .filter(Boolean)
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
        },
        getDayOffDisplayLabel(date) {
            const dayOff = this.findDayOffEntry(date);
            if (!dayOff) {
                return '';
            }

            const formattedDate = this.formatDateToYYYYMMDD(date);
            const holidayInfo = this.specialDates.find((sd) => this.formatDateToYYYYMMDD(sd.date) === formattedDate);
            const holidayName = holidayInfo?.description || '';
            const rawStatus = String(dayOff.status_hari || 'OFF').replace(/_/g, '-');

            if (/^OFF$/i.test(rawStatus)) {
                return 'Day Off';
            }

            const suffix = rawStatus.replace(/^OFF[-\s]*/i, '').trim();
            const normalizedSuffix = suffix.toLowerCase().replace(/[_\s]+/g, '-');

            if (normalizedSuffix === 'hari-libur' || normalizedSuffix === 'hari libur') {
                return holidayName ? `Hari Libur - ${holidayName}` : 'Hari Libur';
            }

            const prettySuffix = this.formatDayOffSuffix(suffix);
            return prettySuffix ? `Day Off - ${prettySuffix}` : 'Day Off';
        },
        getBlockedDateMessage(check, date) {
            if (check.isDayOff) {
                return `${this.getDayOffDisplayLabel(date)}. Jika tetap perlu cuti, ajukan perubahan ke WORK ke atasan.`;
            }

            if (check.isNationalHoliday) {
                return this.getSpecialDateTooltip(date) || 'Hari Libur.';
            }

            if (check.isCutiBersama) return 'Tanggal Cuti Bersama.';
            if (check.isCutiPribadi) return 'Sudah pernah ajukan cuti.';
            if (check.isSakit) return 'Anda tercatat Sakit.';
            if (check.isIzin) return 'Anda tercatat Izin.';
            if (check.isPulangCepat) return 'Anda tercatat Izin pulang cepat.';
            if (check.isTerlambat) return 'Anda tercatat Izin terlambat.';
            if (check.isKonversiPending) {
                const pending = this.findKonversiPendingEntry(date);
                return pending?.tooltip || 'Konversi Lembur Day off';
            }

            return 'Tanggal tidak diperbolehkan.';
        },
        syncSelectedDateState() {
            this.pengajuanForm.jumlah_hari = this.pengajuanForm.selectedDates.length;
            this.pengajuanForm.hasSelectedDates = this.pengajuanForm.jumlah_hari > 0;
        },
        getCalendarSelectionBlock(date) {
            const formatted = this.formatDateToYYYYMMDD(date);

            if (this.disablePastDates(formatted)) {
                return {
                    blocked: true,
                    message: 'Tanggal sudah lewat, tidak dapat dipilih.',
                };
            }

            const check = this.isSpecialDate(formatted);
            if (check.isBlocked) {
                return {
                    blocked: true,
                    message: this.getBlockedDateMessage(check, formatted),
                };
            }

            return {
                blocked: false,
                message: '',
            };
        },
        isCalendarDateBlockedForSelection(date) {
            return this.getCalendarSelectionBlock(date).blocked;
        },
        disableCalendarDates(date) {
            return this.isCalendarDateBlockedForSelection(date);
        },
        pruneBlockedSelectedDates({ notify = false } = {}) {
            if (!Array.isArray(this.pengajuanForm.selectedDates) || this.pengajuanForm.selectedDates.length === 0) {
                return false;
            }

            const validSelectedDates = this.pengajuanForm.selectedDates.filter(
                (date) => !this.isCalendarDateBlockedForSelection(date),
            );

            if (validSelectedDates.length === this.pengajuanForm.selectedDates.length) {
                return false;
            }

            this.pengajuanForm.selectedDates = validSelectedDates;
            this.pengajuanForm.requestedRange = null;
            this.syncSelectedDateState();

            if (notify) {
                ElMessage.warning('Tanggal off/libur yang tidak valid sudah dihapus dari pilihan.');
            }

            return true;
        },
        buildSelectableDatesInRange(start, end) {
            const selectedDates = [];
            const skippedDates = [];
            const loop = new Date(start);

            while (loop <= end) {
                const current = this.formatDateToYYYYMMDD(loop);

                if (this.disablePastDates(loop)) {
                    return {
                        selectedDates,
                        skippedDates,
                        blockedPastDate: true,
                    };
                }

                const check = this.isSpecialDate(current);
                if (check.isBlocked) {
                    skippedDates.push({
                        date: current,
                        reason: this.getBlockedDateMessage(check, current),
                    });
                } else {
                    selectedDates.push(current);
                }

                loop.setDate(loop.getDate() + 1);
            }

            return {
                selectedDates,
                skippedDates,
                blockedPastDate: false,
            };
        },
        getTerlambatPulangDate(item) {
            return item?.Tanggal_Aktual_Izin || item?.Tanggal_Hari_Kerja || item?.Tanggal_Masuk_Pulang;
        },

        // async handleDateClick(data) {
        //     const rawDate = new Date(data.date);
        //     const formatted = this.formatDateToYYYYMMDD(rawDate);

        //     const check = this.isSpecialDate(formatted);

        //     // ==============================
        //     // GUARD VALIDASI
        //     // ==============================
        //     if (this.disablePastDates(rawDate)) {
        //         ElMessage.warning("Tanggal sudah lewat.");
        //         return false;
        //     }

        //     if (rawDate.getDay() === 0) {
        //         ElMessage.warning("Tidak bisa memilih hari Minggu.");
        //         return false;
        //     }

        //     // ==============================
        //     // BLOK EVENT (SAKIT/IZIN/DLL)
        //     // ==============================
        //     if (check.isBlocked) {
        //         let message = "Tanggal tidak diperbolehkan.";

        //         if (check.isNationalHoliday) message = "Hari Libur Nasional.";
        //         else if (check.isCutiBersama) message = "Tanggal Cuti Bersama.";
        //         else if (check.isCutiPribadi)
        //             message = "Sudah pernah ajukan cuti.";
        //         else if (check.isSakit) message = "Anda tercatat Sakit.";
        //         else if (check.isIzin) message = "Anda tercatat Izin.";
        //         else if (check.isPulangCepat) message = "Tanggal pulang cepat.";
        //         else if (check.isTerlambat) message = "Tanggal terlambat.";

        //         ElMessage.warning(message);
        //         return false; // RETURN supaya tidak lanjut seleksi
        //     }

        //     // ==============================
        //     // SELECT / UNSELECT TANGGAL
        //     // ==============================
        //     const existingIndex = this.pengajuanForm.selectedDates.findIndex(
        //         (d) => d === formatted
        //     );

        //     const bisaHutang = this.selectedTypeCuti.bisa_hutang_cuti;
        //     const tipe_select = this.tipe_select_tanggal;

        //     if(tipe_select == 'MULTIPLE')

        //     if (existingIndex !== -1) {
        //         this.pengajuanForm.selectedDates.splice(existingIndex, 1);
        //     } else {
        //         if (
        //             !bisaHutang &&
        //             this.selectedTypeCuti.memotong_saldo &&
        //             this.pengajuanForm.selectedDates.length + 1 > this.sisaCuti
        //         ) {
        //             ElMessage.warning(
        //                 `Sisa Cuti anda habis hanya bisa pakai ${this.sisaCuti} hari sisa, Jenis Cuti ini tidak bisa hutang cuti...`
        //             );
        //             return false;
        //         }

        //         if (
        //             this.pengajuanForm.selectedDates.length + 1 >
        //             this.selectedTypeCuti.maksimum_pengajuan
        //         ) {
        //             ElMessage.warning(
        //                 `Anda hanya bisa memilih maks. ${this.selectedTypeCuti.maksimum_pengajuan} hari untuk jenis cuti ini`
        //             );
        //             return false;
        //         }

        //         this.pengajuanForm.selectedDates.push(formatted);
        //     }

        //     this.pengajuanForm.jumlah_hari =
        //         this.pengajuanForm.selectedDates.length;
        //     this.pengajuanForm.hasSelectedDates =
        //         this.pengajuanForm.jumlah_hari > 0;

        //     return true;
        // },

        // isSpecialDate(date) {
        //     const formattedDate = this.formatDateToYYYYMMDD(date);

        //     return this.specialDates.some(
        //         (sd) => this.formatDateToYYYYMMDD(sd.date) === formattedDate
        //     );
        // },
        // isSpecialDate(date) {
        //     const formatted = this.formatDateToYYYYMMDD(date);

        //     // 1. Libur Nasional
        //     const isNationalHoliday = this.specialDates.some(
        //         (sd) => this.formatDateToYYYYMMDD(sd.date) === formatted
        //     );

        //     // 2. Cuti Bersama
        //     const isCutiBersama = this.TanggalCutiBersama.some((item) => {
        //         const start = this.formatDateToYYYYMMDD(item.Tanggal_Mulai);
        //         const end = this.formatDateToYYYYMMDD(item.Tanggal_Selesai);
        //         return formatted >= start && formatted <= end;
        //     });

        //     // 3. Cuti Pribadi
        //     const isCutiPribadi = this.TanggalCuti.some(
        //         (sd) => this.formatDateToYYYYMMDD(sd.Tanggal_Cuti) === formatted
        //     );

        //     // 4. Sakit
        //     const isSakit = this.TanggalSakitIzin.some((sd) => {
        //         const dbDate = this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin);
        //         return sd.Jenis === "sakit" && dbDate === formatted;
        //     });

        //     // 5. Izin
        //     const isIzin = this.TanggalSakitIzin.some((sd) => {
        //         const dbDate = this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin);
        //         return sd.Jenis === "izinFull" && dbDate === formatted;
        //     });

        //     // 6. Pulang Cepat
        //     const isPulangCepat = this.TanggalPulangTerlambat.some((sd) => {
        //         const dbDate = this.formatDateToYYYYMMDD(
        //             sd.Tanggal_Masuk_Pulang
        //         );
        //         return sd.Jenis === "pulangCepat" && dbDate === formatted;
        //     });

        //     // 7. Terlambat
        //     const isTerlambat = this.TanggalPulangTerlambat.some((sd) => {
        //         const dbDate = this.formatDateToYYYYMMDD(
        //             sd.Tanggal_Masuk_Pulang
        //         );
        //         return sd.Jenis === "terlambat" && dbDate === formatted;
        //     });

        //     return {
        //         isNationalHoliday,
        //         isCutiBersama,
        //         isCutiPribadi,
        //         isSakit,
        //         isIzin,
        //         isPulangCepat,
        //         isTerlambat,
        //         isBlocked:
        //             isNationalHoliday ||
        //             isCutiBersama ||
        //             isCutiPribadi ||
        //             isSakit ||
        //             isIzin ||
        //             isPulangCepat ||
        //             isTerlambat,
        //     };
        // },

        isSpecialDate(date) {
            const formatted = this.formatDateToYYYYMMDD(date);
            const isDayOff = this.isDayOffDate(formatted);

            const isNationalHoliday = this.specialDates.some((sd) => this.formatDateToYYYYMMDD(sd.date) === formatted);

            const isCutiBersama = this.TanggalCutiBersama.some((item) => {
                const start = this.formatDateToYYYYMMDD(item.Tanggal_Mulai);
                const end = this.formatDateToYYYYMMDD(item.Tanggal_Selesai);
                return formatted >= start && formatted <= end;
            });

            const isCutiPribadi = this.TanggalCuti.some((sd) => {
                const dbDate = this.formatDateToYYYYMMDD(sd.Tanggal_Cuti);
                return dbDate === formatted;
            });

            const isSakit = this.TanggalSakitIzin.some((sd) => {
                const dbDate = this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin);
                return sd.Jenis === 'sakit' && dbDate === formatted;
            });

            const isIzin = this.TanggalSakitIzin.some((sd) => {
                const dbDate = this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin);
                return sd.Jenis === 'izinFull' && dbDate === formatted;
            });

            const isPulangCepat = this.TanggalPulangTerlambat.some((sd) => {
                const dbDate = this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(sd));
                return sd.Jenis === 'pulangCepat' && dbDate === formatted;
            });

            const isTerlambat = this.TanggalPulangTerlambat.some((sd) => {
                const dbDate = this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(sd));
                return sd.Jenis === 'terlambat' && dbDate === formatted;
            });

            const isKonversiPending = this.isKonversiPendingDate(formatted);

            return {
                isNationalHoliday,
                isDayOff,
                isCutiBersama,
                isCutiPribadi,
                isSakit,
                isIzin,
                isPulangCepat,
                isTerlambat,
                isKonversiPending,

                isBlocked:
                    isDayOff ||
                    isNationalHoliday ||
                    isCutiBersama ||
                    isCutiPribadi ||
                    isSakit ||
                    isIzin ||
                    isPulangCepat ||
                    isTerlambat ||
                    isKonversiPending,
            };
        },
        getSpecialDateTooltip(dateInput) {
            const date = new Date(dateInput);
            const formattedDate = this.formatDateToYYYYMMDD(date);

            if (this.isDayOffDate(formattedDate)) {
                return `${this.getDayOffDisplayLabel(formattedDate)}. Jika tetap perlu cuti, ajukan perubahan ke WORK ke atasan.`;
            }

            if (this.isKonversiPendingDate(formattedDate)) {
                const pending = this.findKonversiPendingEntry(formattedDate);
                return pending?.tooltip || 'Konversi Lembur Day off';
            }

            const cutiBersama = this.TanggalCutiBersama.find((item) => {
                const start = this.formatDateToYYYYMMDD(item.Tanggal_Mulai);
                const end = this.formatDateToYYYYMMDD(item.Tanggal_Selesai);
                return formattedDate >= start && formattedDate <= end;
            });
            if (cutiBersama) {
                return cutiBersama.Nama_Hari_Libur || 'Cuti Bersama';
            }

            const cutiPribadi = this.TanggalCuti.find(
                (sd) => this.formatDateToYYYYMMDD(sd.Tanggal_Cuti) === formattedDate,
            );
            if (cutiPribadi) {
                return cutiPribadi ? 'Cuti - ' + (cutiPribadi.No_Transaksi || 'Pribadi') : 'Cuti Pribadi';
            }

            const sakit =
                this.TanggalSakitIzin.find((sd) => {
                    if (sd.Jenis !== 'sakit') return false;

                    const start = this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin);

                    return formattedDate == start;
                }) || null;
            if (sakit) {
                return `Sakit - ${sakit?.No_Transaksi ?? 'Transaksi'}`;
            }

            const izin = this.TanggalSakitIzin.find(
                (sd) => sd.Jenis == 'izinFull' && this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin) === formattedDate,
            );
            if (izin) {
                return `Izin - ${izin?.No_Transaksi ?? 'Transaksi'}`;
            }

            const pulangCepat = this.TanggalPulangTerlambat.find(
                (sd) =>
                    sd.Jenis == 'pulangCepat' &&
                    this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(sd)) === formattedDate,
            );
            if (pulangCepat) {
                return `Pulang Cepat - ${pulangCepat?.No_Transaksi ?? 'Transaksi'}`;
            }

            // 6. Cek Terlambat
            const terlambat = this.TanggalPulangTerlambat.find(
                (sd) =>
                    sd.Jenis == 'terlambat' &&
                    this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(sd)) === formattedDate,
            );
            if (terlambat) {
                return `Terlambat - ${terlambat?.No_Transaksi ?? 'Transaksi'}`;
            }

            // 7. Special dates (Libur Nasional)
            const specialDay = this.specialDates.find((sd) => this.formatDateToYYYYMMDD(sd.date) === formattedDate);
            if (specialDay) {
                return specialDay.description || specialDay?.Nama_Hari_Libur;
            }

            return '';
        },

        getCellClass(data) {
            const date = new Date(data.date);
            const formatted = this.formatDateToYYYYMMDD(date);
            const check = this.isSpecialDate(formatted);

            const isSelected = this.pengajuanForm.selectedDates.includes(formatted);
            const isBlockedForSelection = this.isCalendarDateBlockedForSelection(formatted);

            return {
                'is-selected': isSelected,
                'is-today': data.isToday,
                'is-current-month': data.isCurrentMonth,
                'is-disabled-custom': isBlockedForSelection,
                'is-weekend': date.getDay() === 0,

                'is-dayoff-date': check.isDayOff,
                'is-special-dates': check.isNationalHoliday,
                'is-cuti-bersama': check.isCutiBersama,
                'is-cuti-pribadi': check.isCutiPribadi,
                'is-sakit': check.isSakit,
                'is-izin': check.isIzin,
                'is-pulang-cepat': check.isPulangCepat,
                'is-terlambat': check.isTerlambat,
                'is-konversi-pending': check.isKonversiPending,
            };
        },

        formatDateToYYYYMMDD(date) {
            if (!date) return '';
            let d = new Date(date);

            // Set jam, menit, detik, dan milidetik menjadi nol
            d.setHours(0, 0, 0, 0);

            // Jika input string 'YYYY-MM-DD' murni, biarkan saja (tidak terkonversi timezone)
            if (typeof date === 'string' && date.length === 10) {
                return date;
            }

            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        },
        disablePastDates(date) {
            let batasPengajuan = Number(this.selectedTypeCuti?.batas_hari_pengajuan) || 0;

            // Tentukan hari ini (Server Time) & reset jam
            const today = new Date(this.serverTime);
            today.setHours(0, 0, 0, 0);

            // Jika 7  => today + 7 hari (Masa depan)
            // Jika -7 => today - 7 hari (Masa lalu)
            const minAllowedDate = new Date(today);
            minAllowedDate.setDate(today.getDate() + batasPengajuan);

            // Normalisasi tanggal yang sedang dirender datepicker
            const d = new Date(date);
            d.setHours(0, 0, 0, 0);

            return d < minAllowedDate;
        },
        isCutiBersama(date) {
            const formatted = this.formatDateToYYYYMMDD(date);

            return this.TanggalCutiBersama.some((item) => {
                const start = this.formatDateToYYYYMMDD(item.Tanggal_Mulai);
                const end = this.formatDateToYYYYMMDD(item.Tanggal_Selesai);
                return formatted >= start && formatted <= end;
            });
        },
        isCutiPribadi(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            return this.TanggalCuti.some((sd) => this.formatDateToYYYYMMDD(sd.Tanggal_Cuti) == formattedDate);
        },
        isIzin(date) {
            const formatted = this.formatDateToYYYYMMDD(date);

            return this.TanggalSakitIzin.some(
                (sd) => sd.Jenis == 'izinFull' && this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin) == formatted,
            );
        },
        isSakit(date) {
            const formatted = this.formatDateToYYYYMMDD(date);
            return this.TanggalSakitIzin.some((sd) => {
                const dbDate = this.formatDateToYYYYMMDD(sd.Tanggal_Sakit_Izin);
                return sd.Jenis == 'sakit' && dbDate == formatted;
            });
        },
        isPulangCepat(date) {
            const formatted = this.formatDateToYYYYMMDD(date);

            return this.TanggalPulangTerlambat.some(
                (sd) =>
                    sd.Jenis == 'pulangCepat' &&
                    this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(sd)) == formatted,
            );
        },
        isTerlambat(date) {
            const formatted = this.formatDateToYYYYMMDD(date);
            // console.log("ini terlambat", this.TanggalPulangTerlambat);
            return this.TanggalPulangTerlambat.some(
                (sd) =>
                    sd.Jenis == 'terlambat' && this.formatDateToYYYYMMDD(this.getTerlambatPulangDate(sd)) == formatted,
            );
        },
        handleRiwayatFiltersUpdate(updatedFilters) {
            this.riwayatFilters = { ...this.riwayatFilters, ...updatedFilters };
            this.filters.currentPage = 1; // Reset ke halaman pertama
            this.loadRiwayat();
        },
        handleRiwayatPageChange(page) {
            this.loadRiwayat(page);
        },

        // Handle search dengan debounce
        handleSearchRiwayat() {
            if (this.searchTimerRiwayat) {
                clearTimeout(this.searchTimerRiwayat);
            }
            this.searchTimerRiwayat = setTimeout(() => {
                this.filters.currentPage = 1;
                this.loadRiwayat();
            }, 500);
        },

        // Apply filters untuk riwayat
        applyFiltersRiwayat() {
            this.filters.currentPage = 1;
            this.loadRiwayat();
        },

        // Format tanggal untuk riwayat
        formatRiwayatDate(item) {
            if (!item?.TanggalIzin) return '-';

            const startDate = this.formatDate(item?.TanggalIzin);
            if (item?.Tanggal_Selesai && item?.Tanggal_Selesai !== item?.TanggalIzin) {
                const endDate = this.formatDate(item?.Tanggal_Selesai);
                return `${startDate} - ${endDate}`;
            }
            return startDate;
        },

        // Helper methods untuk badges dan icons
        getJenisIzinBadgeType(item) {
            const jenis = item?.Tipe_Izin2 || item?.Tipe_Izin || item?.jenis_cuti;
            const jenisText = String(jenis || '');

            if (jenisText.includes('Cuti')) return 'info';
            if (jenisText.includes('Sakit')) return 'warning';
            if (jenisText.includes('Izin')) return 'primary';
            return 'secondary';
        },

        getJenisIzinText(jenis) {
            const jenisMap = {
                cuti: 'Cuti',
                sakit: 'Izin Sakit',
                izinFull: 'Izin Pribadi',
                pulangCepat: 'Pulang Cepat',
                terlambat: 'Izin Terlambat',
            };
            return jenisMap[jenis] || jenis;
        },

        getStatusIcon(status) {
            const iconMap = {
                approved: 'bi bi-check-circle',
                rejected: 'bi bi-x-circle',
                expired: 'bi bi-x-circle',
                pending: 'bi bi-clock',
            };
            return iconMap[status] || 'bi bi-question-circle';
        },

        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase()
                .substring(0, 2);
        },
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        formatTanggalFromAgg(aggString) {
            if (!aggString || typeof aggString !== 'string') return '';

            // Mapping bulan Indonesia → angka (0-based untuk Date)
            const bulanMap = {
                januari: 0,
                februari: 1,
                maret: 2,
                april: 3,
                mei: 4,
                juni: 5,
                juli: 6,
                agustus: 7,
                september: 8,
                oktober: 9,
                november: 10,
                desember: 11,
            };

            // Fungsi parse manual format "DD MMMM YYYY"
            const parseTanggal = (str) => {
                const parts = str.trim().split(' ');

                // Jika format dari SQL dd-mm-yyyy
                if (/^\d{2}-\d{2}-\d{4}$/.test(str)) {
                    const [dd, mm, yyyy] = str.split('-');
                    return new Date(yyyy, Number(mm) - 1, dd);
                }

                // Format "01 Desember 2025"
                const dd = parseInt(parts[0], 10);
                const bulan = bulanMap[parts[1].toLowerCase()];
                const yyyy = parseInt(parts[2], 10);

                return new Date(yyyy, bulan, dd);
            };

            // Pecah STRING_AGG → array tanggal
            const dates = aggString
                .split(',')
                .map((s) => parseTanggal(s))
                .sort((a, b) => a - b);

            // Helper format output
            const formatID = (date) =>
                date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                });

            // Cek apakah range (tanggal berurutan semua)
            let isRange = true;
            for (let i = 0; i < dates.length - 1; i++) {
                const next = new Date(dates[i]);
                next.setDate(next.getDate() + 1);
                if (next.getTime() !== dates[i + 1].getTime()) {
                    isRange = false;
                    break;
                }
            }

            // Jika RANGE
            if (isRange && dates.length > 1) {
                const first = dates[0];
                const last = dates[dates.length - 1];

                const fd = String(first.getDate()).padStart(2, '0');
                const ld = String(last.getDate()).padStart(2, '0');

                const fM = first.toLocaleString('id-ID', { month: 'long' });
                const lM = last.toLocaleString('id-ID', { month: 'long' });

                const fY = first.getFullYear();
                const lY = last.getFullYear();

                if (fM === lM && fY === lY) return `${fd}–${ld} ${fM} ${fY}`;
                if (fY === lY) return `${fd} ${fM}–${ld} ${lM} ${fY}`;
                return `${fd} ${fM} ${fY}–${ld} ${lM} ${lY}`;
            }

            // MULTIPLE
            return dates.map((d) => formatID(d)).join(', ');
        },
        truncateText(text, max) {
            if (!text) return '';
            return text.length > max ? text.substr(0, max) + '...' : text;
        },
        handleFileUpload(event) {
            const file = event.target.files[0];
            this.fileError = '';

            if (!file) return;

            // Validasi ukuran file (2MB)
            if (file.size > 2 * 1024 * 1024) {
                this.fileError = 'Ukuran file melebihi 2MB';
                return;
            }

            // Validasi tipe file
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                this.fileError = 'Format file tidak didukung. Hanya PDF, JPG, dan PNG yang diizinkan';
                return;
            }

            // Set file ke form
            this.pengajuanForm.file_pendukung = file;

            // Reset input file untuk mengizinkan memilih file yang sama lagi
            this.$nextTick(() => {
                if (this.$refs.fileInput) {
                    this.$refs.fileInput.value = '';
                }
            });
        },

        // Handle file drop
        handleFileDrop(event) {
            event.preventDefault();
            const file = event.dataTransfer.files[0];
            if (file) {
                const inputEvent = { target: { files: [file] } };
                this.handleFileUpload(inputEvent);
            }
        },

        // Ganti file
        replaceFile() {
            this.removeFile();
            // Trigger click setelah delay kecil untuk memastikan state sudah diupdate
            this.$nextTick(() => {
                this.$refs.fileInput.click();
            });
        },

        // Hapus file
        removeFile() {
            this.pengajuanForm.file_pendukung = null;
            this.fileError = '';
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },

        // Preview file
        previewFile(file) {
            if (!file) return;

            if (file instanceof File) {
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            } else if (file.url) {
                window.open(file.url, '_blank');
            }
        },

        // Helper methods untuk file info
        getFileIcon(file) {
            if (!file) return 'bi bi-file-earmark';

            const type = file.type || '';
            if (type.includes('pdf')) return 'bi bi-file-earmark-pdf';
            if (type.includes('image')) return 'bi bi-file-earmark-image';
            return 'bi bi-file-earmark';
        },

        getFileName(file) {
            if (!file) return '';
            return file.name || 'File pendukung';
        },

        getFileSize(file) {
            if (!file || !file.size) return '';

            const sizeInMB = file.size / (1024 * 1024);
            return sizeInMB < 1 ? Math.round(file.size / 1024) + ' KB' : sizeInMB.toFixed(1) + ' MB';
        },

        getFileType(file) {
            if (!file) return '';

            const type = file.type || '';
            if (type.includes('pdf')) return 'PDF';
            if (type.includes('jpeg') || type.includes('jpg')) return 'JPEG';
            if (type.includes('png')) return 'PNG';
            return 'File';
        },
        showCancelConfirmation(item) {
            this.pengajuanToCancel = item;
            this.showCancelModal = true;
        },

        // Format date utilities
        formatDateString(date, withTime = false) {
            if (!date) return '';
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            };
            if (withTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
            }
            return new Date(date).toLocaleDateString('id-ID', options);
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },

        formatDateCompact(date) {
            if (!date) return '';
            const d = new Date(date);
            const day = d.getDate();
            const month = d.toLocaleDateString('id-ID', { month: 'short' });
            return `${day} ${month}`;
        },

        formatDateRelative(date) {
            if (!date) return '';
            const now = new Date();
            const target = new Date(date);
            const diffMs = now - target;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return 'Hari ini';
            if (diffDays === 1) return 'Kemarin';
            if (diffDays < 7) return `${diffDays} hari lalu`;
            if (diffDays < 30) return `${Math.floor(diffDays / 7)} minggu lalu`;
            if (diffDays < 365) return `${Math.floor(diffDays / 30)} bulan lalu`;
            return `${Math.floor(diffDays / 365)} tahun lalu`;
        },

        // Badge type helpers
        getStatusBadgeType(status) {
            const types = {
                pending: 'warning',
                approved: 'success',
                rejected: 'danger',
                expired: 'danger',
                cancelled: 'secondary',
            };
            return types[status] || 'secondary';
        },

        getStatusText(status) {
            const texts = {
                pending: 'Menunggu',
                approved: 'Disetujui',
                rejected: 'Ditolak',
                expired: 'Ditolak sistem/Expired',
                cancelled: 'Dibatalkan',
            };
            return texts[status] || status;
        },

        // Icon helpers
        getCutiTypeIcon(jenisNama) {
            const icons = {
                tahunan: 'bi bi-calendar-heart',
                sakit: 'bi bi-heart-pulse',
                melahirkan: 'bi bi-gender-female',
                menikah: 'bi bi-heart',
                keluarga: 'bi bi-people',
                izin: 'bi bi-three-dots',
                lainnya: 'bi bi-calendar',
            };

            const lowerNama = (jenisNama || '').toLowerCase();
            for (const [key, icon] of Object.entries(icons)) {
                if (lowerNama.includes(key)) {
                    return icon;
                }
            }
            return icons.lainnya;
        },

        // Approval flow helpers
        getStepStatus(approver) {
            if (approver.Flag_Approval === 'Y') return 'success';
            if (approver.Flag_Approval === 'P') return 'expired';
            if (approver.Flag_Approval === 'T') return 'error';
            return 'process';
        },

        getStepIcon(approver) {
            const status = this.getStepStatus(approver);
            const icons = {
                success: 'bi bi-check-circle-fill',
                error: 'bi bi-x-circle-fill',
                expired: 'bi bi-x-circle-fill',
                process: 'bi bi-hourglass-split',
            };
            return icons[status] || icons.process;
        },

        getStepDescription(approver) {
            if (approver.Flag_Approval === 'P') {
                return 'Ditolak sistem/Expired';
            }

            if (approver.Flag_Approval === 'T') {
                const approvedInfo = approver.Tanggal_Approved
                    ? `${this.formatDateString(approver.Tanggal_Approved, true)} oleh ${approver.approver_name || 'Sistem'}`
                    : 'Ditolak';
                const rejectReason = this.getApprovalRejectReason(approver);

                return rejectReason
                    ? `${approvedInfo}. Alasan: ${rejectReason}`
                    : `${approvedInfo}. Alasan belum diisi.`;
            }

            if (approver.Tanggal_Approved) {
                return `${this.formatDateString(
                    approver.Tanggal_Approved,
                    true,
                )} oleh ${approver.approver_name || 'Sistem'}`;
            }
            return 'Menunggu persetujuan';
        },
        getApprovalRejectReason(approver) {
            return String(
                approver?.Catatan_Approval ||
                    approver?.Keterangan_Approval ||
                    approver?.Catatan ||
                    approver?.Keterangan ||
                    approver?.alasan_tolak ||
                    approver?.alasan ||
                    '',
            ).trim();
        },

        getOverallStatus(approvers) {
            if (!approvers || approvers.length === 0) return 'pending';

            if (approvers.every((a) => a.Flag_Approval === 'P')) return 'expired';
            // Check if any rejected
            if (approvers.some((a) => a.Flag_Approval === 'T')) return 'rejected';

            // Check if all approved
            if (approvers.every((a) => a.Flag_Approval === 'Y')) return 'approved';

            return 'pending';
        },

        getActiveStep(approvers) {
            if (!approvers) return 0;

            // Find the last approved step
            for (let i = approvers.length - 1; i >= 0; i--) {
                if (approvers[i].Flag_Approval === 'Y') {
                    return i + 1;
                }
            }
            return 0;
        },

        // Validation
        validateForm() {
            this.validationErrors = [];

            // Validate jenis cuti
            if (!this.pengajuanForm.jenis_cuti_id) {
                this.validationErrors.push('Pilih jenis cuti');
            }

            // Validate tanggal
            if (this.pengajuanForm.selectedDates.length === 0) {
                this.validationErrors.push('Pilih minimal satu tanggal');
            }

            // Validate alasan
            if (!this.pengajuanForm.alasan || this.pengajuanForm.alasan.trim().length < 3) {
                this.validationErrors.push('Alasan cuti minimal 3 karakter');
            }

            // Validate dokumen (if required)
            const selectedType = this.jenisCutiList.find((j) => j.id === this.pengajuanForm.jenis_cuti_id);
            if (selectedType?.dokumen_pendukung && !this.pengajuanForm.file_pendukung) {
                this.validationErrors.push('Dokumen pendukung wajib untuk jenis cuti ini');
            }

            // Validate batas hutang cuti (UX preventif; server tetap sumber kebenaran)
            const hutang = this.hutangLimitInfo;
            if (hutang.blockedNoDebt) {
                this.validationErrors.push(
                    `Jenis cuti ini tidak boleh hutang. Kurangi pengajuan hingga maksimal ${this.sisaCuti} hari.`,
                );
            } else if (hutang.exceeds) {
                this.validationErrors.push(
                    `Melebihi batas hutang cuti (sisa jatah ${hutang.sisaJatah} hari). Kurangi ${
                        hutang.akanBerhutang - hutang.sisaJatah
                    } hari.`,
                );
            }
        },

        getSisaCuti(jenisCutiId) {
            const saldo = this.saldoCuti.find((s) => s.jenis_cuti_id === jenisCutiId);
            return saldo ? saldo.sisa : 0;
        },

        // Event handlers
        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.loadTabData();
        },

        handleSearch() {
            if (this.activeTab === 'riwayat') {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.loadRiwayat();
                }, 500);
            }
        },

        selectCutiType(selectedOption) {
            const jenis = selectedOption;

            // console.log(selectedOption);
            let cariCuti = this.jenisCutiList.find((j) => j.id === jenis);
            // console.log(this.jenisCutiList);

            this.selectedTypeCuti = cariCuti;
            // console.log(this.selectedTypeCuti);

            this.loadApprovalFlowPreview(jenis);
            this.loadHutangUsage(jenis, cariCuti);

            this.pengajuanForm.jenis_cuti_id = jenis;
            this.closeDatePicker();
            // this.validateForm();
        },

        // Ambil rincian pemakaian jatah hutang periode berjalan (panel transparansi)
        async loadHutangUsage(jenisCutiId, jenis) {
            this.showHutangRincian = false;
            this.hutangUsage = { loading: false, data: null };

            // Hanya relevan untuk jenis yang memotong saldo & boleh hutang dengan batas.
            if (!jenis || !jenis.memotong_saldo || !jenis.bisa_hutang_cuti) return;

            try {
                this.hutangUsage.loading = true;
                const response = await axios.get(`/api/v1/cuti/jenis-cuti/${jenisCutiId}/hutang-usage`);
                if (response.data.status === 200) {
                    this.hutangUsage.data = response.data.result;
                }
            } catch (error) {
                console.error('Error loading hutang usage:', error);
            } finally {
                this.hutangUsage.loading = false;
            }
        },

        // Format tanggal "YYYY-MM-DD" -> "27 Jan 2026" (ramah dibaca)
        formatTanggalIndo(dateStr) {
            if (!dateStr) return '-';
            const bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return dateStr;
            return `${d.getDate()} ${bln[d.getMonth()]} ${d.getFullYear()}`;
        },

        async openDialog() {
            if (!this.selectedTypeCuti) {
                ElMessage.warning('Pilih jenis cuti terlebih dahulu');
                return;
            }
            // Convert dateRange to selectedDates if we don't have selectedDates yet
            // if (
            //     this.pengajuanForm.dateRange &&
            //     this.pengajuanForm.selectedDates.length === 0
            // ) {
            //     const [startStr, endStr] = this.pengajuanForm.dateRange;
            //     const startDate = new Date(startStr + "T00:00:00");
            //     const endDate = new Date(endStr + "T23:59:59");

            //     const selectedDates = [];
            //     const currentDate = new Date(startDate);

            //     while (currentDate <= endDate) {
            //         selectedDates.push(new Date(currentDate));
            //         currentDate.setDate(currentDate.getDate() + 1);
            //     }

            //     this.pengajuanForm.selectedDates = selectedDates;
            //     this.pengajuanForm.hasSelectedDates = true;
            // }
            this.dialogVisible = true;
            await this.loadCalendarDataForDate(this.pengajuanForm.calendarDate || new Date());
        },

        handleCancel() {
            this.dialogVisible = false;
            this.pengajuanForm.hasSelectedDates = false;
        },

        confirmDate(jenis = 'multiple') {
            this.pruneBlockedSelectedDates({ notify: true });

            if (jenis === 'multiple') {
                // Update dateRange based on selectedDates
                if (this.pengajuanForm.selectedDates.length > 0) {
                    const sortedDates = [...this.pengajuanForm.selectedDates].sort((a, b) => new Date(a) - new Date(b));
                    const startDate = sortedDates[0];
                    const endDate = sortedDates[sortedDates.length - 1];

                    this.pengajuanForm.dateRange = [this.formatDateString(startDate), this.formatDateString(endDate)];
                    this.pengajuanForm.jumlah_hari = this.pengajuanForm.selectedDates.length;
                } else {
                    ElMessage.warning('Pilih minimal satu tanggal kerja yang valid.');
                    return;
                }
            }

            // Reset hasSelectedDates flag
            this.pengajuanForm.hasSelectedDates = false;
            this.dialogVisible = false;
            // this.validateForm();
        },

        // handleDateClick(data) {
        //     if (this.disablePastDates(data.date)) {
        //         ElMessage.warning("Tanggal tersebut tidak dapat dipilih");
        //         return;
        //     }

        //     const date = new Date(data.date);
        //     const existingIndex = this.pengajuanForm.selectedDates.findIndex(
        //         (d) => d.toDateString() === date.toDateString()
        //     );

        //     if (existingIndex !== -1) {
        //         // Remove existing date
        //         this.pengajuanForm.selectedDates.splice(existingIndex, 1);
        //     } else {
        //         // Add new date
        //         if (
        //             this.pengajuanForm.selectedDates.length + 1 >
        //             this.selectedTypeCuti.maksimum_pengajuan
        //         ) {
        //             ElMessage.warning(
        //                 `Anda hanya bisa memilih maks.${this.selectedTypeCuti.maksimum_pengajuan} hari untuk jenis cuti ini`
        //             );
        //         } else {
        //             this.pengajuanForm.selectedDates.push(date);
        //         }
        //     }

        //     this.pengajuanForm.jumlah_hari =
        //         this.pengajuanForm.selectedDates.length;
        //     this.pengajuanForm.hasSelectedDates = true;
        // },
        // handleDateClick(data) {
        //     const date = new Date(data.date);

        //     // ============================================================
        //     // 1. VALIDASI BLOKIR (Cek kondisi terlarang di sini)
        //     // ============================================================

        //     // Cek Tanggal Lampau
        //     if (this.disablePastDates(data.date)) {
        //         ElMessage.warning(
        //             "Tanggal tersebut tidak dapat dipilih (Sudah lewat)."
        //         );
        //         return;
        //     }

        //     // Cek Weekend (Minggu) - Sesuai snippet Anda (0 = Minggu)
        //     // Jika Sabtu juga libur, gunakan: (date.getDay() === 0 || date.getDay() === 6)
        //     if (date.getDay() === 0) {
        //         ElMessage.warning("Tidak bisa memilih hari Minggu.");
        //         return;
        //     }

        //     // Cek Special Dates (Hari Besar / Libur Nasional)
        //     if (this.isSpecialDate(date)) {
        //         ElMessage.warning("Tidak bisa memilih Hari Libur Nasional.");
        //         return;
        //     }

        //     // Cek Cuti Bersama
        //     if (this.isCutiBersama(date)) {
        //         ElMessage.warning("Tidak bisa memilih tanggal Cuti Bersama.");
        //         return;
        //     }

        //     // Cek Cuti Pribadi (Tanggal yang sudah diajukan cuti sebelumnya)
        //     if (this.isCutiPribadi(date)) {
        //         ElMessage.warning("Anda sudah mengajukan cuti di tanggal ini.");
        //         return;
        //     }

        //     // ============================================================
        //     // 2. LOGIKA PILIH / HAPUS TANGGAL (Jalan jika lolos validasi)
        //     // ============================================================

        //     const existingIndex = this.pengajuanForm.selectedDates.findIndex(
        //         (d) => d.toDateString() === date.toDateString()
        //     );

        //     if (existingIndex !== -1) {
        //         // Hapus tanggal (Unselect)
        //         this.pengajuanForm.selectedDates.splice(existingIndex, 1);
        //     } else {
        //         // Tambah tanggal baru
        //         if (
        //             this.pengajuanForm.selectedDates.length + 1 >
        //             this.selectedTypeCuti.maksimum_pengajuan
        //         ) {
        //             ElMessage.warning(
        //                 `Anda hanya bisa memilih maks.${this.selectedTypeCuti.maksimum_pengajuan} hari untuk jenis cuti ini`
        //             );
        //         } else {
        //             this.pengajuanForm.selectedDates.push(date);
        //         }
        //     }

        //     // Update state jumlah hari
        //     this.pengajuanForm.jumlah_hari =
        //         this.pengajuanForm.selectedDates.length;
        //     this.pengajuanForm.hasSelectedDates = true;
        // },

        // handleDateClick(data) {
        //     const clickedDate = new Date(data.date);

        //     // 1. Cek apakah tanggal yang diklik adalah tanggal yang di-disable
        //     if (this.disablePastDates(clickedDate)) {
        //         ElMessage({
        //             message: "Tanggal ini tidak bisa dipilih.",
        //             type: "warning",
        //         });
        //         return;
        //     }

        //     // --- Klik pertama ---
        //     if (!this.tempStartDate && !this.tempEndDate) {
        //         this.tempStartDate = clickedDate;
        //         this.tempEndDate = clickedDate; // start & end sama
        //         return;
        //     }

        //     // --- Klik kedua ---
        //     if (
        //         this.tempStartDate &&
        //         this.tempEndDate &&
        //         this.tempStartDate.getTime() === this.tempEndDate.getTime()
        //     ) {
        //         const startDate = new Date(this.tempStartDate);
        //         const endDate = new Date(clickedDate);

        //         // Tukar jika tanggal kedua < tanggal pertama
        //         let currentStart = startDate;
        //         let currentEnd = endDate;
        //         if (currentEnd.getTime() < currentStart.getTime()) {
        //             [currentStart, currentEnd] = [currentEnd, currentStart];
        //         }

        //         // Cek apakah ada tanggal disable di tengah rentang
        //         let isDisabledInRange = false;
        //         const currentDate = new Date(currentStart);
        //         currentDate.setDate(currentStart.getDate() + 1); // mulai dari hari setelah tanggal awal

        //         while (currentDate < currentEnd) {
        //             if (this.disablePastDates(currentDate)) {
        //                 isDisabledInRange = true;
        //                 break;
        //             }
        //             currentDate.setDate(currentDate.getDate() + 1);
        //         }

        //         if (isDisabledInRange) {
        //             ElMessage({
        //                 message:
        //                     "Tidak bisa memilih rentang tanggal yang mengandung tanggal tidak bisa dipilih.",
        //                 type: "warning",
        //             });
        //             this.tempStartDate = null;
        //             this.tempEndDate = null;
        //         } else {
        //             this.tempStartDate = currentStart;
        //             this.tempEndDate = currentEnd;
        //         }

        //         return;
        //     }

        //     // --- Klik ketiga atau reset baru (jika sebelumnya sudah range penuh) ---
        //     if (this.tempStartDate && this.tempEndDate) {
        //         this.tempStartDate = clickedDate;
        //         this.tempEndDate = clickedDate;
        //     }
        // },

        async getDataCurrent() {
            try {
                const response = await axios.get(`/api/v1/cuti/current`);
                const data = response.data.result;
                this.sisaCuti = data.sisa_cuti;
                this.hutangCuti = data.hutang_cuti;
                this.saldoDetail = data.saldo_detail;
                this.jenisCutiList = data.jenis_cuti;
                this.specialDates = data.special_dates;
                // this.calendarEvents = data.calendar_events;
            } catch (error) {
                console.error('Error fetching current data:', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal memuat data saldo dan jenis cuti.',
                    type: 'error',
                });
            }
        },
        // Form submission
        async submitPengajuan() {
            this.pruneBlockedSelectedDates({ notify: true });
            this.validateForm();
            if (!this.ensureApprovalFlowReady(true)) {
                return;
            }
            if (this.validationErrors.length > 0 || this.isSubmitting || !this.isFormValid) {
                return;
            }

            this.showSubmitPreviewModal = true;
        },
        closeSubmitPreview() {
            if (this.isSubmitting) {
                return;
            }

            this.showSubmitPreviewModal = false;
        },
        buildPengajuanPayload() {
            const payload = new FormData();
            payload.append('jenis_cuti_id', this.pengajuanForm.jenis_cuti_id);
            payload.append('alasan', this.pengajuanForm.alasan);

            if (this.pengajuanForm.file_pendukung) {
                payload.append('file_pendukung', this.pengajuanForm.file_pendukung);
            }

            if (this.pengajuanForm.selectedDates.length > 0) {
                this.pengajuanForm.selectedDates.forEach((date, index) => {
                    payload.append(`selected_dates[${index}]`, this.formatDateToYYYYMMDD(date));
                });

                if (
                    Array.isArray(this.pengajuanForm.requestedRange) &&
                    this.pengajuanForm.requestedRange.length === 2
                ) {
                    payload.append('requested_date_from', this.pengajuanForm.requestedRange[0]);
                    payload.append('requested_date_to', this.pengajuanForm.requestedRange[1]);
                }

                payload.append('jumlah_hari', this.pengajuanForm.jumlah_hari);
            } else {
                const [startStr, endStr] = this.pengajuanForm.dateRange;
                payload.append('tanggal_mulai', startStr);
                payload.append('tanggal_selesai', endStr);
                payload.append('jumlah_hari', 1);
            }

            return payload;
        },
        async confirmSubmitPengajuan() {
            this.pruneBlockedSelectedDates({ notify: true });
            this.validateForm();
            if (!this.ensureApprovalFlowReady(true)) {
                this.showSubmitPreviewModal = false;
                return;
            }
            if (this.validationErrors.length > 0 || this.isSubmitting || !this.isFormValid) {
                this.showSubmitPreviewModal = false;
                return;
            }

            this.isSubmitting = true;

            try {
                const payload = this.buildPengajuanPayload();
                const response = await axios.post('/api/v1/cuti/store', payload, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                });

                if (response.data.status) {
                    this.showSubmitPreviewModal = false;
                    ElNotification.success(response.data.message || 'Pengajuan cuti berhasil diajukan!');
                    this.resetForm();
                    this.activeTab = 'riwayat';

                    await this.loadRiwayat();
                    await this.loadSaldoCuti();
                    await this.getCurrentSpecialDate();
                } else {
                    ElMessage.error('Gagal mengajukan cuti: ' + (response.data.message || 'Terjadi kesalahan'));
                }
            } catch (error) {
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }
                errorMessage = this.normalizeSubmitErrorMessage(errorMessage);

                ElNotification({
                    title: 'Pengajuan belum bisa dikirim',
                    message: errorMessage,
                    type: 'error',
                    duration: 8000,
                });
            } finally {
                this.isSubmitting = false;
            }
        },
        normalizeSubmitErrorMessage(message) {
            const rawMessage = String(message || '').trim();
            const technicalApprovalKeywords = [
                'Resolver gagal menemukan approver',
                'Source_Type=',
                'Source_Reference=',
                'HRIS_Approval_Flow',
                'Konfigurasi approval cuti belum lengkap',
            ];

            if (technicalApprovalKeywords.some((keyword) => rawMessage.includes(keyword))) {
                return 'Pengajuan belum bisa dikirim karena alur approval cuti Anda belum tersedia. Silakan hubungi HC/Admin untuk mengecek data atasan atau konfigurasi approval jenis cuti ini.';
            }

            return rawMessage || 'Terjadi Kesalahan';
        },
        ensureApprovalFlowReady(notify = false) {
            if (!this.selectedTypeCuti?.perlu_persetujuan_khusus) {
                return true;
            }

            if (this.approvalFlowPreview.loading) {
                if (notify) {
                    ElMessage.warning('Alur approval sedang dicek, tunggu beberapa detik lalu coba lagi.');
                }
                return false;
            }

            if (!this.approvalFlowPreview.isValid || this.approvalFlowPreview.hasWarning) {
                if (notify) {
                    const firstWarning = this.approvalFlowPreview.warnings?.[0];
                    ElMessage.warning(
                        firstWarning ||
                            'Pengajuan belum bisa dikirim karena masih ada tahap approval yang belum memiliki approver.',
                    );
                }
                return false;
            }

            return true;
        },

        // async cancelPengajuan(item) {
        //     try {
        //         this.loading.destroy = true;
        //         const response = await axios.post(`/api/v1/cuti/destroy`, {
        //             id: item.No_Transaksi,
        //         });

        //         if (response.status == 200) {
        //             ElNotification.success(
        //                 "Pengajuan cuti berhasil dibatalkan"
        //             );
        //             await this.loadRiwayat();
        //             await this.loadSaldoCuti();
        //         } else {
        //             ElMessage.error("Gagal membatalkan pengajuan");
        //         }
        //     } catch (error) {
        //         console.log(error);
        //         let errorMessage = "Terjadi Kesalahan";
        //         if (
        //             error.response &&
        //             error.response.data &&
        //             error.response.data.message
        //         ) {
        //             errorMessage = error.response.data.message;
        //         }

        //         ElMessage({
        //             message: errorMessage,
        //             type: "error",
        //         });
        //     } finally {
        //         this.loading.destroy = false;
        //     }
        // },

        // Reset form
        resetForm() {
            this.showSubmitPreviewModal = false;
            this.pengajuanForm = {
                jenis_cuti_id: '',
                dateRange: null,
                requestedRange: null,
                selectedDates: [],
                jumlah_hari: 0,
                alasan: '',
                file_pendukung: null,
                calendarDate: new Date(),
                hasSelectedDates: false,
            };
            this.selectedTypeCuti = null;
            this.approvalFlowPreview = {
                loading: false,
                steps: [],
                items: [],
                isValid: true,
                hasWarning: false,
                warnings: [],
                jenisCutiId: null,
                nama: '',
                perluPersetujuan: false,
            };
            this.validationErrors = [];
        },

        // API calls
        async loadJenisCuti() {
            try {
                this.loading.jenisCuti = true;
                const response = await axios.get('/api/v1/cuti/jenis-cuti');
                if (response.data.status === 200) {
                    const rawJenisCuti = Array.isArray(response.data.result) ? response.data.result : [];

                    this.jenisCutiList = rawJenisCuti.filter((item) => item && typeof item === 'object');
                } else {
                    console.error('Failed to load jenis cuti');
                }
            } catch (error) {
                console.error('Error loading jenis cuti:', error);
            } finally {
                this.loading.jenisCuti = false;
            }
        },
        async loadApprovalFlowPreview(jenisCutiId) {
            if (!jenisCutiId) {
                this.approvalFlowPreview = {
                    loading: false,
                    steps: [],
                    items: [],
                    isValid: true,
                    hasWarning: false,
                    warnings: [],
                    jenisCutiId: null,
                    nama: '',
                    perluPersetujuan: false,
                };
                return;
            }

            this.approvalFlowPreview.loading = true;
            this.approvalFlowPreview.steps = [];
            this.approvalFlowPreview.items = [];
            this.approvalFlowPreview.jenisCutiId = jenisCutiId;
            this.approvalFlowPreview.nama = this.selectedTypeCuti?.nama || '';
            this.approvalFlowPreview.perluPersetujuan = !!this.selectedTypeCuti?.perlu_persetujuan_khusus;

            try {
                const response = await axios.get(`/api/v1/cuti/jenis-cuti/${jenisCutiId}/approval-flow`);
                const result = response?.data?.result || {};
                const items = Array.isArray(result.steps)
                    ? result.steps.flatMap((step, stepIndex) => {
                          const approvers = Array.isArray(step.approvers) ? step.approvers : [];

                          if (approvers.length === 0) {
                              return [
                                  {
                                      order_flow: step.order_flow ?? stepIndex + 1,
                                      name: `Approver untuk ${step.nama_approval || `Tahap ${stepIndex + 1}`} belum ditemukan`,
                                      isAdminHC: false,
                                      isMissing: true,
                                  },
                              ];
                          }

                          return approvers.map((approver, approverIndex) => ({
                              order_flow: step.order_flow ?? stepIndex + 1,
                              name: approver.nama || `Approver ${stepIndex + 1}.${approverIndex + 1}`,
                              isAdminHC: String(approver.nama || '')
                                  .toLowerCase()
                                  .includes('admin hc'),
                              isMissing: false,
                          }));
                      })
                    : [];

                const warnings = Array.isArray(result.warnings)
                    ? result.warnings.filter((warning) => String(warning || '').trim() !== '')
                    : [];

                this.approvalFlowPreview = {
                    loading: false,
                    steps: Array.isArray(result.steps) ? result.steps : [],
                    items,
                    isValid: !!result.is_valid,
                    hasWarning: warnings.length > 0,
                    warnings,
                    jenisCutiId,
                    nama: result.jenis_cuti?.nama || this.selectedTypeCuti?.nama || '',
                    perluPersetujuan: !!result.jenis_cuti?.perlu_persetujuan_khusus,
                };
            } catch (error) {
                console.error('Error loading approval flow preview:', error);
                this.approvalFlowPreview = {
                    loading: false,
                    steps: [],
                    items: [],
                    isValid: false,
                    hasWarning: true,
                    warnings: ['Gagal memuat alur approval. Coba refresh halaman atau hubungi HC/Admin.'],
                    jenisCutiId,
                    nama: this.selectedTypeCuti?.nama || '',
                    perluPersetujuan: !!this.selectedTypeCuti?.perlu_persetujuan_khusus,
                };
            }
        },
        resetRiwayatFilters() {
            this.riwayatFilters = {
                search: '',
                status: '',
                tanggal: '',
                jenis_izin: '',
            };
            this.filters.currentPage = 1;
            this.loadRiwayat();
        },

        /**
         * Ambil nomor halaman dari apa pun yang dikirim pemanggil.
         *
         * Komponen tabel bersama meng-emit `next_page_url` dari server bila ada,
         * dan nomor halaman bila tidak. Dua bentuk itu diterima di sini supaya
         * kontrak komponennya — yang dipakai banyak halaman lain — tidak perlu
         * diubah, tetapi permintaannya tetap kita rakit sendiri.
         */
        nomorHalamanRiwayat(sumber) {
            if (sumber === null || sumber === undefined || sumber === '') {
                return Number(this.filters.currentPage) || 1;
            }
            if (typeof sumber === 'number') {
                return sumber > 0 ? sumber : 1;
            }
            const cocok = String(sumber).match(/[?&]page=(\d+)/);
            if (cocok) {
                return Number(cocok[1]) || 1;
            }
            const angka = Number(sumber);
            return Number.isFinite(angka) && angka > 0 ? angka : 1;
        },

        /**
         * `halaman` boleh nomor, boleh URL dari server, boleh kosong.
         *
         * Sebelumnya parameter ini diperlakukan sebagai URL dan diteruskan
         * mentah-mentah ke axios. Dua akibatnya: permintaan keluar ke URL
         * ABSOLUT bikinan server (host/skema-nya ikut apa pun yang dilihat
         * Laravel di balik proxy, tidak selalu sama dengan origin halaman), dan
         * seluruh saringan aktif hilang begitu pindah halaman karena URL itu
         * hanya membawa `page`. Sementara `filters.currentPage` yang dijaga
         * rapi di klien tidak pernah ikut terkirim sama sekali.
         *
         * Sekarang permintaannya selalu dirakit sendiri: jalur relatif (satu
         * origin dengan halaman), plus `page` dan `limit` yang eksplisit.
         */
        async loadRiwayat(halaman = null) {
            try {
                this.loading.riwayat = true;
                const page = this.nomorHalamanRiwayat(halaman);
                this.filters.currentPage = page;

                const params = new URLSearchParams();
                if (this.riwayatFilters.search) {
                    params.append('search_query', this.riwayatFilters.search);
                }
                if (this.riwayatFilters.status) {
                    params.append('status_filter', this.riwayatFilters.status);
                }
                if (this.riwayatFilters.tanggal) {
                    params.append('tanggalFilter', this.riwayatFilters.tanggal);
                }
                if (this.riwayatFilters.jenis_izin) {
                    params.append('jenis_izin_filter', this.riwayatFilters.jenis_izin);
                }

                // const params = new URLSearchParams({
                //     // page: this.filters.currentPage || 1,
                //     // limit: this.filters.perPage || 10,
                //     search_query: this.filters.searchQuery || "",
                //     status_filter: this.filters.status || "",
                // });

                if (this.filters.searchQuery) {
                    params.append('search_query', this.filters.searchQuery);
                }
                if (this.filters.status) {
                    params.append('status_filter', this.filters.status);
                }
                if (this.filters.tanggalFilter) {
                    params.append('tanggalFilter', this.filters.tanggalFilter);
                }

                // console.log(params);

                params.append('page', String(page));
                params.append('limit', String(Number(this.filters.perPage) || 10));

                const endpoint = `/api/v1/cuti/riwayat?${params.toString()}`;

                // console.log(endpoint);
                const response = await axios.get(endpoint);

                if (response.data.status === 200) {
                    const rawRiwayat = Array.isArray(response.data.result) ? response.data.result : [];

                    this.riwayatData.data = rawRiwayat.filter((item) => item && typeof item === 'object');
                    this.riwayatData.pagination = response.data.pagination;
                    this.loading.riwayat = false;
                } else {
                    console.error('Failed to load riwayat:', response.data);
                }
            } catch (error) {
                console.error('Error loading riwayat:', error);
            } finally {
                this.loading.riwayat = false;
            }
        },

        // Pagination methods
        async changePage(page) {
            // Backend mengirim `total_pages`; `last_page` tidak pernah ada di
            // payload sehingga perbandingannya dulu selalu `page > undefined`
            // alias false, dan batas atasnya tidak pernah menjaga apa pun.
            const terakhir = Number(this.riwayatData?.pagination?.total_pages) || 1;
            if (page < 1 || page > terakhir) return;

            await this.loadRiwayat(page);

            // Scroll to top setelah ganti halaman
            this.$nextTick(() => {
                const container = this.$el.querySelector('.riwayat-container');
                if (container) {
                    container.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }
            });
        },

        getVisiblePages() {
            if (!this.riwayatData.pagination) return [];

            const current = this.riwayatData?.pagination?.current_page;
            const last = this.riwayatData?.pagination?.last_page;
            const delta = 2; // Number of pages to show before and after current page

            const range = [];
            const rangeWithDots = [];
            let l;

            for (let i = 1; i <= last; i++) {
                if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
                    range.push(i);
                }
            }

            range.forEach((i) => {
                if (l) {
                    if (i - l === 2) {
                        rangeWithDots.push(l + 1);
                    } else if (i - l !== 1) {
                        rangeWithDots.push('...');
                    }
                }
                rangeWithDots.push(i);
                l = i;
            });

            return rangeWithDots.filter((page) => page !== '...' || !rangeWithDots.includes(page - 1));
        },
        getStatusCount(status) {
            return this.riwayatData?.data?.filter((item) => item.status === status).length;
        },

        async loadSaldoCuti() {
            try {
                const response = await axios.get('/api/v1/cuti/check-saldo');
                if (response.data.status === 200) {
                    this.sisaCuti = response.data.result.saldo;
                    this.hutangCuti = response.data.result.hutang;
                    this.pengajuanPending = Number(response.data.result.pending) || 0;
                } else {
                    console.error('Failed to load saldo cuti');
                    this.sisaCuti = 0;
                    this.hutangCuti = 0;
                    this.pengajuanPending = 0;
                }
            } catch (error) {
                console.error('Error loading saldo:', error);
            }
        },

        async loadTabData() {
            switch (this.activeTab) {
                case 'ajukan':
                    await Promise.all([this.loadJenisCuti(), this.loadSaldoCuti()]);
                    break;
                case 'riwayat':
                    await this.loadRiwayat();
                    break;
            }
        },

        refreshData() {
            ElNotification.info('Memperbarui data...');
            this.loadTabData();
        },

        // Load server time
        async getTimeServer() {
            try {
                const response = await axios.get('/getTime');
                this.serverTime = new Date(response.data.time);
            } catch (error) {
                console.error('Failed to get server time:', error);
                this.serverTime = new Date();
            }
        },

        // Modal handlers
        async viewRiwayatDetail(item) {
            this.selectedRiwayat = null;
            this.detailLoading = true;
            this.showDetailModal = true;

            try {
                const response = await axios.get(`/api/v1/cuti/riwayat/${item.No_Transaksi}`);
                if (response.data.status === 200) {
                    this.selectedRiwayat = response.data.result;
                } else {
                    ElMessage.error('Gagal memuat detail riwayat');
                }
            } catch (error) {
                console.error('Error loading detail:', error);
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedRiwayat = null;
        },

        confirmCancelPengajuan() {
            this.showCancelModal = false;
            this.cancelPengajuan(this.pengajuanToCancel);
        },

        // Calendar utilities
        getPendingCount() {
            return 0;
        },

        // Mobile detection
        get isMobile() {
            return window.innerWidth < 768;
        },
    },
    mounted() {
        this.getTimeServer();
        this.getCurrentSpecialDate();
        this.loadTabData();
    },
    watch: {
        activeTab: {
            handler(newTab) {
                if (newTab === 'riwayat') {
                    // Reset filters ketika pindah ke tab riwayat
                    this.riwayatFilters = {
                        search: '',
                        status: '',
                        tanggal: '',
                        jenis_izin: '',
                    };
                    this.filters.currentPage = 1;
                }
                this.filters.status = ''; // Reset status filter when tab changes
                this.filters.searchQuery = ''; // Reset search when tab changes
                this.filters.currentPage = 1; // Reset page when tab changes
            },
        },
        'filters.searchQuery': {
            handler() {
                if (this.activeTab === 'riwayat') {
                    this.filters.currentPage = 1; // Reset page when searching
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.loadRiwayat();
                    }, 500);
                }
            },
        },
        // "filters.status": {
        //     handler() {
        //         if (this.activeTab === "riwayat") {
        //             this.filters.currentPage = 1; // Reset page when filter changes
        //             this.loadRiwayat();
        //         }
        //     },
        // },
        'filters.perPage': {
            handler() {
                if (this.activeTab === 'riwayat') {
                    this.filters.currentPage = 1; // Reset page when per page changes
                    this.loadRiwayat();
                }
            },
        },
        'pengajuanForm.calendarDate': {
            handler(newDate) {
                if (!newDate || !this.dialogVisible) {
                    return;
                }

                this.loadCalendarDataForDate(newDate);
            },
        },
    },
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

/* Modern Data Table Custom Styles */
.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.is-expired {
    color: #dc3545 !important;
    font-weight: 600;
}

.employee-details {
    min-width: 0;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.875rem;
    line-height: 1.3;
}

.employee-dept {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
    font-size: 0.75rem;
}

.badge-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
}

.action-cell {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-view:hover {
    background: #bbdefb;
    transform: translateY(-1px);
}

.btn-cancel {
    background: #ffebee;
    color: #d32f2f;
}

.action-text {
    font-size: 0.7rem;
}

.transaction-id-sm {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.7rem;
    background: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    color: #495057;
}

/* Loading spin animation */
.loading-spin {
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

/* Refresh button */
.btn-refresh {
    padding: 0.75rem 1rem;
    border: 1px solid #007bff;
    background: white;
    color: #007bff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.btn-refresh:hover:not(:disabled) {
    background: #007bff;
    color: white;
}

.btn-refresh:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Mobile card improvements */
.mobile-btn {
    flex: 1;
    justify-content: center;
    padding: 0.75rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .employee-info-cell {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }

    .action-cell {
        justify-content: center;
    }

    .filter-section .row > div {
        margin-bottom: 0.5rem;
    }
}

.date-display {
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

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background-color: var(--surface);
    color: var(--text-muted);
    font-size: 1rem;
    transition: var(--transition);
    cursor: pointer;
}

.pagination-btn:hover:not(:disabled) {
    background-color: var(--surface-soft);
    color: var(--text);
    border-color: var(--primary-light);
}

.pagination-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pagination-btn.active {
    background-color: var(--primary);
    color: var(--light);
    border-color: var(--primary);
}

.pagination-btn.active:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
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

/* File Upload Container */
.file-upload-container {
    margin-top: 0.5rem;
}

/* Upload Card */
.file-upload-card {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--surface-soft);
    position: relative;
    overflow: hidden;
}

.file-upload-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.02);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.file-upload-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.05), transparent);
    transition: left 0.5s ease;
}

.file-upload-card:hover::before {
    left: 100%;
}

.upload-icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.upload-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.upload-content p {
    margin: 0 0 1.5rem 0;
    color: var(--text-muted);
}

.file-requirements {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.requirement-item i {
    color: var(--success);
}

/* File Preview Card */
.file-preview-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    background: var(--surface);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
}

.file-info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 1;
}

.file-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.file-details {
    flex: 1;
}

.file-name {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
    word-break: break-word;
}

.file-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.file-size,
.file-type {
    padding: 0.25rem 0.75rem;
    background: var(--surface-soft);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}

.upload-status {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--success);
    font-size: 0.8rem;
    font-weight: 500;
}

.upload-status i {
    font-size: 0.9rem;
}

/* File Actions */
.file-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-preview {
    background: var(--info);
    color: white;
}

.btn-preview:hover {
    background: var(--hcis-cyan-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
}

.btn-replace {
    background: var(--warning);
    color: white;
}

.btn-replace:hover {
    background: var(--hcis-amber-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-remove-action {
    background: var(--danger);
    color: white;
}

.btn-remove-action:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Upload Progress */
.upload-progress {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background: var(--surface-soft);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #22c55e);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
    min-width: 40px;
}

/* Validation & Requirements */
.requirement-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.requirement-badge.required {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.requirement-badge:not(.required) {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.validation-error {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--danger);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: rgba(239, 68, 68, 0.05);
    border-radius: var(--radius);
    border-left: 3px solid var(--danger);
}

/* Responsive Design */
@media (max-width: 768px) {
    .file-upload-card {
        padding: 2rem 1.5rem;
    }

    .file-header {
        flex-direction: column;
        gap: 1rem;
    }

    .file-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .file-meta {
        gap: 0.5rem;
    }

    .btn-action span {
        display: none;
    }

    .btn-action {
        padding: 0.5rem;
    }
}

/* Base Styles */
.cuti-app-container {
    min-height: 100vh;
    /* background: var(--light); */
}

.main-content-wrapper {
    padding: 0.5rem 0;
}

.content-container {
    /* max-width: 1400px; */
    margin: 0 auto;
    /* padding: 0 1rem; */
}

/* Tab Content */
.tab-content-wrapper {
    /* padding: 1.5rem 0; */
}

/* Form Layout */
.form-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: start;
}

.form-main-section {
    min-height: 500px;
}

.form-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.form-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--surface-soft);
}

.form-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.form-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

/* Form Elements */
.form-field-group {
    margin-bottom: 1.5rem;
}

/*
 * Label form dan ikonnya.
 *
 * Dulu ada DUA aturan .form-label di berkas ini; yang belakangan memakai
 * `display: block` sehingga menimpa yang ini, dan ikon di dalamnya — yang
 * memakai utilitas `.d-flex` Bootstrap (ber-!important, jadi tak bisa ditimpa
 * dari sini) — ikut menjadi kotak setinggi barisnya sendiri. Itu sebabnya ikon
 * tidak pernah sebaris dengan teks labelnya.
 *
 * Sekarang aturannya tunggal, dan ikonnya diberi lebar tetap supaya teks
 * keempat label mulai di titik x yang sama meski lebar glifnya berbeda-beda.
 */
.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    line-height: 1.35;
}

.form-label-icon {
    flex: 0 0 auto;
    width: 1.15em;
    text-align: center;
    font-size: 1em;
    line-height: 1;
    opacity: 0.85;
}

/* Badge "Wajib/Opsional" ikut sebaris, tidak menarik tinggi label. */
.form-label .requirement-badge {
    margin-left: auto;
}

.form-textarea {
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

.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.date-picker-trigger {
    cursor: pointer;
}

.date-display {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    transition: var(--transition);
}

.date-display.disabled {
    background: #eaeaea !important;
    cursor: not-allowed;
}

.date-display:hover {
    border-color: var(--primary);
}

.placeholder {
    color: var(--text-muted);
    background: none;
}

.selected-dates {
    color: var(--text);
    font-weight: 500;
}

/* File Upload */
.file-upload-wrapper {
    margin-top: 0.5rem;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
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
    border-radius: var(--radius);
    padding: 1rem;
    margin-top: 1rem;
}

.file-name {
    flex: 1;
    font-weight: 500;
}

.btn-remove {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.btn-remove:hover {
    background: var(--danger);
    color: white;
}

/* Validation & Alerts */
.validation-section {
    margin: 1.5rem 0;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 0.75rem;
}

.alert.error {
    background: rgba(239, 68, 68, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
}

.alert.info {
    background: rgba(6, 182, 212, 0.1);
    border-left: 4px solid var(--info);
    color: var(--info);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

/* Sidebar */
.form-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: 1rem;
}

.info-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.info-card.highlight {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.info-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.duration-display {
    text-align: center;
}

.duration-value {
    display: block;
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}

.duration-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.5rem;
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
    color: var(--warning);
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.tip-item span {
    font-size: 0.9rem;
    line-height: 1.4;
    color: var(--text);
}

.info-card.highlight .tip-item span {
    color: rgba(255, 255, 255, 0.9);
}

/* Riwayat Styles */
.riwayat-container {
    /* padding: 1rem 0; */
}

/* .filter-section {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
} */

.filter-grid {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 1rem;
    margin-bottom: 1rem;
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
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: var(--transition);
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.filter-info {
    color: var(--text-muted);
    font-weight: 500;
}

/* Stats Overview */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-card.total {
    border-left: 4px solid var(--primary);
}

.stat-card.approved {
    border-left: 4px solid var(--success);
}

.stat-card.pending {
    border-left: 4px solid var(--warning);
}

.stat-card.rejected {
    border-left: 4px solid var(--danger);
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
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-600));
}

.stat-card.pending .stat-icon {
    background: linear-gradient(135deg, var(--warning), var(--hcis-amber-600));
}

.stat-card.rejected .stat-icon {
    background: linear-gradient(135deg, var(--danger), var(--hcis-red-600));
}

.stat-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.stat-content p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Riwayat List */
.riwayat-list {
    margin-bottom: 2rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state i {
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

.riwayat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

.riwayat-item {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    transition: var(--transition);
    box-shadow: var(--shadow);
}

.riwayat-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.riwayat-item.status-approved {
    border-left: 4px solid var(--success);
}

.riwayat-item.status-pending {
    border-left: 4px solid var(--warning);
}

.riwayat-item.status-rejected {
    border-left: 4px solid var(--danger);
}

.riwayat-item.status-cancelled {
    border-left: 4px solid var(--secondary);
}

.riwayat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.cuti-type {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.type-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.type-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.duration {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge {
    font-size: 0.8rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

.riwayat-content {
    margin-bottom: 1.5rem;
}

.date-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: var(--radius);
    margin-bottom: 1rem;
}

.date-info i {
    color: var(--info);
    font-size: 1.1rem;
}

.date-details {
    flex: 1;
}

.date-range {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.date-full {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.reason-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.reason-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.reason-label i {
    color: var(--primary);
}

.reason-text {
    margin: 0;
    color: var(--text);
    line-height: 1.6;
    font-size: 0.9rem;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: var(--radius);
    border-left: 3px solid var(--primary);
}

.riwayat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.meta-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.meta-info i {
    color: var(--primary);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}

.btn-action.primary {
    background: var(--primary);
    color: white;
}

.btn-action.primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-action.danger {
    background: var(--danger);
    color: white;
}

.btn-action.danger:hover {
    background: #c23030;
    transform: translateY(-1px);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
}

.pagination-info {
    color: var(--text-muted);
    font-size: 0.9rem;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    width: 40px;
    height: 40px;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
}

.pagination-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-numbers {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-number {
    min-width: 40px;
    height: 40px;
    border: 2px solid transparent;
    background: transparent;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-weight: 500;
    color: var(--text);
}

.pagination-number:hover {
    border-color: var(--border);
    background: var(--surface-soft);
}

.pagination-number.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Modal Styles */
.mobile-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
}

.btn-back {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text);
    cursor: pointer;
    padding: 0.5rem;
}

.mobile-modal-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.header-spacer {
    width: 40px;
}

.btn-confirm {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
}

.submit-preview-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.submit-preview-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border: 1px solid rgba(79, 70, 229, 0.16);
    border-radius: 8px;
    background: rgba(79, 70, 229, 0.06);
    color: var(--hcis-slate-700);
    font-size: 0.92rem;
    line-height: 1.45;
}

.submit-preview-alert i {
    color: var(--hcis-indigo-600);
    font-size: 1.05rem;
    margin-top: 0.1rem;
}

.submit-preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.submit-preview-item {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.85rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
}

.submit-preview-item.full-width {
    grid-column: 1 / -1;
}

.submit-preview-label {
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
}

.submit-preview-item strong,
.submit-preview-item p {
    margin: 0;
    color: var(--dark);
    font-size: 0.96rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.submit-preview-link {
    margin-top: 0.55rem;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    border: 1px solid rgba(79, 70, 229, 0.22);
    background: rgba(79, 70, 229, 0.08);
    color: var(--primary-dark);
    font-size: 0.82rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.submit-preview-link:hover {
    background: rgba(79, 70, 229, 0.14);
    border-color: rgba(79, 70, 229, 0.35);
}

/* Detail Modal */
.detail-content {
    padding: 0.5rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.detail-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-value {
    color: var(--text);
    font-size: 1rem;
}

.detail-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.reason-text {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
    line-height: 1.6;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    padding: 0.5rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border-radius: var(--radius);
    transition: var(--transition);
}

.file-link:hover {
    background: var(--primary);
    color: white;
}

.approval-flow {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.flow-title {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

/* Confirmation Modal */
.confirmation-content {
    text-align: center;
    padding: 1rem;
}

.warning-icon {
    font-size: 3rem;
    color: var(--warning);
    margin-bottom: 1rem;
}

.confirmation-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Loading Skeleton */
.loading-skeleton {
    padding: 2rem;
}

.skeleton-line {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.skeleton-line.large {
    height: 2rem;
    width: 60%;
}

.skeleton-line.medium {
    height: 1.5rem;
    width: 80%;
}

.skeleton-line.small {
    height: 1rem;
    width: 40%;
}

.skeleton-line.full {
    height: 4rem;
    width: 100%;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Button Styles */
.btn {
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

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-primary {
    background: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: 1px solid var(--danger);
}

.btn-danger:hover:not(:disabled) {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-outline {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-outline:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-1px);
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

/* Mobile Responsive */
@media (max-width: 1024px) {
    .form-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .form-sidebar {
        position: static;
    }

    .riwayat-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    /* .content-container {
        padding: 0 0.75rem;
    } */

    .mobile-modal-header {
        padding: 0;
        border: none;
    }
    .form-card {
        padding: 1.5rem;
    }

    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }

    .riwayat-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .riwayat-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .action-buttons {
        width: 100%;
        justify-content: flex-end;
    }

    .pagination {
        /* flex-direction: column; */
        gap: 1rem;
        text-align: center;
    }

    .detail-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .submit-preview-grid {
        grid-template-columns: 1fr;
    }

    .mobile-fullscreen {
        width: 100% !important;
        margin: 0 !important;
        max-height: 100vh;
    }
}

@media (max-width: 480px) {
    /* .content-container {
        padding: 0 0.5rem;
    } */

    .form-card {
        padding: 1rem;
    }

    .stats-overview {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .riwayat-item {
        padding: 1rem;
    }

    .cuti-type {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}

/* Utility Classes */
.d-none {
    display: none !important;
}

.me-2 {
    margin-right: 0.5rem;
}

.cursor-pointer {
    cursor: pointer;
}

.text-center {
    text-align: center;
}

.justify-content-center {
    justify-content: center;
}

.align-items-center {
    align-items: center;
}

.d-flex {
    display: flex;
}

.w-100 {
    width: 100%;
}

/* V-Select Custom Styles */
:deep(.modern-select .vs__dropdown-toggle) {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    min-height: 48px;
}

:deep(.modern-select.vs--open .vs__dropdown-toggle) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

:deep(.modern-select .vs__dropdown-menu) {
    border: 2px solid var(--border);
    border-radius: var(--radius);
    margin-top: 4px;
    box-shadow: var(--shadow);
}

:deep(.modern-select .vs__dropdown-option) {
    padding: 0.75rem 1rem;
    color: var(--text);
    font-size: 0.95rem;
}

:deep(.modern-select .vs__dropdown-option--highlight) {
    background: var(--primary);
    color: white;
}

/* Element Plus Overrides */
/* :deep(.el-dialog) {
    border-radius: var(--radius-lg);
    overflow: hidden;
}

:deep(.el-dialog__header) {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    margin: 0;
}

:deep(.el-dialog__body) {
    padding: 1.5rem;
}

:deep(.el-dialog__footer) {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

:deep(.el-calendar) {
    border: none;
}

:deep(.el-calendar__header) {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

:deep(.el-calendar__body) {
    padding: 1rem;
}

:deep(.el-step__head) {
    width: 32px;
    height: 32px;
}

:deep(.el-step__title) {
    font-size: 0.9rem;
    font-weight: 600;
}

:deep(.el-step__description) {
    font-size: 0.8rem;
    color: var(--text-muted);
} */
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

/* .is-selected {
    background-color: #409eff !important;
    color: white;
    border-radius: 4px;
} */

.is-terlambat {
    background-color: rgba(239, 68, 68, 0.15); /* Merah transparan */
    color: var(--hcis-red-600); /* Merah lebih gelap */
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.is-pulang-cepat {
    background-color: rgba(245, 158, 11, 0.15); /* Kuning transparan */
    color: var(--hcis-amber-600); /* Kuning lebih gelap */
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.is-sakit {
    background-color: rgba(100, 116, 139, 0.15); /* Secondary transparan */
    color: var(--hcis-slate-600); /* Secondary lebih gelap */
    border: 1px solid rgba(100, 116, 139, 0.2);
}

.is-izin {
    background-color: rgba(6, 182, 212, 0.15); /* Info transparan */
    color: var(--hcis-cyan-600); /* Info lebih gelap */
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.is-cuti-bersama {
    background-color: rgba(253, 246, 236, 0.8); /* Orange sangat transparan */
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: var(--hcis-amber-700); /* Orange gelap */
}

.is-cuti-pribadi {
    background-color: rgba(34, 197, 94, 0.15); /* Hijau transparan */
    color: var(--hcis-green-600); /* Hijau lebih gelap */
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.is-special-dates {
    background-color: rgba(254, 240, 240, 0.8); /* Merah sangat transparan */
    color: var(--hcis-red-600); /* Merah gelap */
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Optional: Tambahkan efek hover untuk interaktivitas */
.is-terlambat:hover,
.is-pulang-cepat:hover,
.is-sakit:hover,
.is-izin:hover,
.is-cuti-bersama:hover,
.is-special-dates:hover,
.is-cuti-pribadi:hover {
    opacity: 0.9;
    transition: opacity 0.2s ease;
}

/* Pastikan div pembungkus memenuhi kotak kalender */
.calendar-cell-content {
    height: 100%;
    width: 100%;
    padding: 5px;
    box-sizing: border-box;
}

/* Detail Modal Styles */
.detail-content-v2 {
    padding: 0.5rem 0;
}

.detail-left-col {
    border-right: 1px solid var(--hcis-legacy-bootstrap-gray);
    padding-right: 2rem;
}

.detail-employee-info {
    text-align: center;
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.detail-status-section {
    margin-bottom: 1rem;
    text-align: center;
}

.section-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
}

.info-item.full-width {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
}

.info-item .label {
    font-weight: 500;
    color: #6c757d;
    font-size: 0.875rem;
    min-width: 120px;
}

.info-item .value {
    color: #495057;
    font-weight: 500;
    text-align: right;
    flex: 1;
}

.value-reason {
    color: #495057;
    line-height: 1.5;
    margin: 0;
    font-size: 0.9rem;
}

.file-link {
    color: #007bff;
    text-decoration: none;
    font-size: 0.875rem;
}

.file-link:hover {
    text-decoration: underline;
}

.approval-status-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.status-label {
    font-weight: 500;
    color: #6c757d;
    font-size: 0.875rem;
}

.status-value {
    color: #495057;
    font-weight: 500;
    font-size: 0.875rem;
}

/* Modal Footer */
.modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 1rem 0 0 0;
    border-top: 1px solid var(--hcis-legacy-bootstrap-gray);
}

.btn-modern {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.avatar-sm,
.avatar-lg {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #007bff;
    color: white;
    font-weight: 600;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.8rem;
}

.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.employee-details {
    flex: 1;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.employee-meta {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
    font-size: 0.8rem;
}

/* Cuti Duration Card */
.cuti-duration-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.cuti-duration-card.cuti-highlight {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
}

.cuti-info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.cuti-info-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.cuti-duration-card.cuti-highlight .cuti-info-header h4 {
    color: white;
}

.cuti-duration-card.cuti-highlight .cuti-info-header i {
    color: white;
}

/* Duration Display */
.cuti-duration-display {
    text-align: center;
    padding: 1rem 0;
}

.cuti-duration-value {
    display: block;
    font-size: 3rem;
    font-weight: 800;
    line-height: 1;
    color: inherit;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    margin-bottom: 0.25rem;
}

.cuti-duration-card.cuti-highlight .cuti-duration-value {
    color: white;
}

.cuti-duration-label {
    display: block;
    font-size: 1rem;
    color: inherit;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
}

.cuti-duration-card.cuti-highlight .cuti-duration-label {
    color: rgba(255, 255, 255, 0.9);
}

/* Hutang Warning Styles */
.cuti-hutang-warning {
    margin-top: 1.5rem;
    padding: 1.25rem;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: var(--radius);
    animation: cuti-slideDown 0.3s ease;
}

@keyframes cuti-slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.cuti-warning-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--warning);
    font-weight: 600;
    font-size: 0.95rem;
}

.cuti-warning-header i {
    font-size: 1.1rem;
}

.cuti-hutang-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.cuti-hutang-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(245, 158, 11, 0.1);
}

.cuti-hutang-item.cuti-highlight-item {
    background: rgba(245, 158, 11, 0.05);
    margin: 0 -0.5rem;
    padding: 0.75rem 0.5rem;
    border-radius: var(--radius);
    border-bottom: none;
}

.cuti-label {
    color: var(--text);
    font-weight: 500;
    font-size: 0.9rem;
}

.cuti-duration-card.cuti-highlight .cuti-label {
    color: rgba(255, 255, 255, 0.9);
}

.cuti-value {
    font-weight: 600;
    font-size: 0.9rem;
}

.cuti-duration-card.cuti-highlight .cuti-value {
    color: white;
}

.cuti-value.cuti-negative {
    color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.cuti-duration-card.cuti-highlight .cuti-value.cuti-negative {
    background: rgba(239, 68, 68, 0.3);
    color: var(--hcis-red-200);
    border-color: rgba(239, 68, 68, 0.5);
}

.cuti-warning-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(245, 158, 11, 0.1);
    border-radius: var(--radius);
    border-left: 3px solid var(--warning);
}

.cuti-warning-message i {
    color: var(--warning);
    font-size: 0.9rem;
}

.cuti-warning-message small {
    color: var(--warning);
    font-size: 0.8rem;
    font-weight: 500;
}

/* Warning message dalam kondisi error (melebihi batas / tidak boleh hutang) */
.cuti-warning-message.is-error {
    background: rgba(239, 68, 68, 0.1);
    border-left-color: var(--danger);
}
.cuti-warning-message.is-error i,
.cuti-warning-message.is-error small {
    color: var(--danger);
}

/* ===== Kartu Ketentuan Hutang Cuti ===== */
.hutang-rule-card .info-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.hutang-rule-help {
    font-size: 0.75rem;
    color: var(--text-muted, var(--hcis-slate-400));
    cursor: help;
}
.hutang-rule-badge {
    margin-left: auto;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
}
.hutang-rule-badge.is-allowed {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-600);
}
.hutang-rule-badge.is-blocked {
    background: rgba(100, 116, 139, 0.15);
    color: var(--hcis-slate-600);
}
.hutang-rule-body {
    margin-top: 0.5rem;
}
.hutang-rule-desc {
    font-size: 0.82rem;
    color: var(--text);
    margin: 0 0 0.75rem;
    line-height: 1.5;
}
.hutang-rule-quota {
    background: var(--surface-2, rgba(148, 163, 184, 0.08));
    border-radius: var(--radius);
    padding: 0.75rem;
}
.hutang-rule-quota-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.78rem;
    color: var(--text);
    margin-bottom: 0.4rem;
}
.hutang-rule-bar {
    height: 8px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.25);
    overflow: hidden;
}
.hutang-rule-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--primary, var(--hcis-blue-500));
    transition: width 0.3s ease;
}
.hutang-rule-bar-fill.is-full {
    background: var(--danger, var(--hcis-red-500));
}
.hutang-rule-quota-foot {
    margin-top: 0.45rem;
    font-size: 0.78rem;
    color: var(--text);
}
.hutang-rule-note {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    margin-top: 0.65rem;
    padding: 0.5rem 0.65rem;
    background: rgba(59, 130, 246, 0.08);
    border-left: 3px solid var(--info, var(--hcis-cyan-500));
    border-radius: var(--radius);
}
.hutang-rule-note i {
    color: var(--info, var(--hcis-cyan-500));
    font-size: 0.85rem;
    margin-top: 0.1rem;
}
.hutang-rule-note small {
    font-size: 0.75rem;
    color: var(--text);
    line-height: 1.45;
}
.hutang-rule-note.is-amber {
    background: rgba(245, 158, 11, 0.1);
    border-left-color: var(--warning, var(--hcis-amber-500));
}
.hutang-rule-note.is-amber i {
    color: var(--warning, var(--hcis-amber-500));
}

/* Rincian pemakaian jatah hutang */
.hutang-rincian {
    margin-top: 0.65rem;
}
.hutang-rincian-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: transparent;
    border: none;
    padding: 0.25rem 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--primary, var(--hcis-blue-500));
    cursor: pointer;
}
.hutang-rincian-toggle i {
    font-size: 0.8rem;
}
.hutang-rincian-list {
    margin-top: 0.4rem;
    border: 1px solid var(--border, rgba(148, 163, 184, 0.25));
    border-radius: var(--radius);
    overflow: hidden;
}
.hutang-rincian-item {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.6rem;
    font-size: 0.75rem;
    color: var(--text);
    border-bottom: 1px solid var(--border, rgba(148, 163, 184, 0.18));
}
.hutang-rincian-item:last-of-type {
    border-bottom: none;
}
.hutang-rincian-main {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.hutang-rincian-judul {
    font-weight: 600;
    color: var(--text);
}
.hutang-rincian-meta {
    font-size: 0.68rem;
    color: var(--text-muted, var(--hcis-slate-400));
    line-height: 1.35;
}
.hutang-rincian-jml {
    font-weight: 700;
    color: var(--danger, var(--hcis-red-500));
    white-space: nowrap;
}
.hutang-rincian-jml.is-credit {
    color: var(--success, var(--hcis-emerald-500));
}

/* Kalimat ringkas (headline) */
.hutang-headline {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.75rem;
    margin-bottom: 0.75rem;
    border-radius: var(--radius);
    background: rgba(16, 185, 129, 0.1);
    border-left: 3px solid var(--success, var(--hcis-emerald-500));
    font-size: 0.8rem;
    line-height: 1.5;
    color: var(--text);
}
.hutang-headline i {
    color: var(--success, var(--hcis-emerald-500));
    font-size: 0.95rem;
    margin-top: 0.1rem;
}
.hutang-headline.is-habis {
    background: rgba(239, 68, 68, 0.1);
    border-left-color: var(--danger, var(--hcis-red-500));
}
.hutang-headline.is-habis i {
    color: var(--danger, var(--hcis-red-500));
}
.text-danger-strong {
    color: var(--danger, var(--hcis-red-500));
    font-weight: 700;
}
.hutang-habis-tag {
    display: inline-block;
    margin-left: 0.35rem;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
    background: var(--danger, var(--hcis-red-500));
    color: #fff;
}
.hutang-rincian-total {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0.6rem;
    font-size: 0.78rem;
    background: var(--surface-2, rgba(148, 163, 184, 0.08));
    border-top: 1px solid var(--border, rgba(148, 163, 184, 0.25));
}
.hutang-rincian-loading {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-muted, var(--hcis-slate-400));
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.hutang-rincian-pending-tag {
    display: inline-block;
    margin-left: 0.4rem;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 700;
    vertical-align: middle;
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning, var(--hcis-amber-500));
    white-space: nowrap;
}
.hutang-rincian-note {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.5rem 0.6rem;
    font-size: 0.7rem;
    line-height: 1.4;
    color: var(--text-muted, var(--hcis-slate-400));
    background: rgba(59, 130, 246, 0.06);
    border-bottom: 1px solid var(--border, rgba(148, 163, 184, 0.18));
}
.hutang-rincian-note i {
    margin-top: 0.1rem;
    color: var(--info, var(--hcis-cyan-500));
}

/* ===== Mobile: sticky bar hutang cuti + modal ===== */
.cuti-hutang-mobilebar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1030;
    display: none; /* default desktop: sembunyi; ditampilkan di media query mobile */
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.9rem;
    padding-bottom: calc(0.6rem + env(safe-area-inset-bottom, 0px));
    background: var(--surface);
    border-top: 1px solid var(--border);
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.12);
}
.cuti-hutang-mobilebar-info {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1;
    min-width: 0;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.3;
    color: var(--text);
}
.cuti-hutang-mobilebar-info span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cuti-hutang-mobilebar-info i {
    font-size: 1rem;
    flex-shrink: 0;
    color: var(--info, var(--hcis-cyan-500));
}
.cuti-hutang-mobilebar-info.is-warning,
.cuti-hutang-mobilebar-info.is-warning i {
    color: var(--warning, var(--hcis-amber-500));
}
.cuti-hutang-mobilebar-info.is-error,
.cuti-hutang-mobilebar-info.is-error i {
    color: var(--danger, var(--hcis-red-500));
}
.cuti-hutang-mobilebar-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border: none;
    border-radius: 999px;
    background: var(--primary, var(--hcis-blue-500));
    color: #fff;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
}
.hutang-cards-mobilemodal .cuti-duration-card,
.hutang-cards-mobilemodal .info-card {
    margin-bottom: 1rem;
}
.hutang-cards-mobilemodal .info-card:last-child,
.hutang-cards-mobilemodal .cuti-duration-card:last-child {
    margin-bottom: 0;
}
/* Desktop: beri jarak antar kartu di dalam wrapper (form-sidebar gap tak lagi kena karena 1 anak) */
.hutang-cards-desktop {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Status OK pada sticky bar mobile */
.cuti-hutang-mobilebar-info.is-ok,
.cuti-hutang-mobilebar-info.is-ok i {
    color: var(--success, var(--hcis-emerald-500));
}

/* Mobile: tampilkan sticky bar, sembunyikan sidebar, beri ruang submit */
@media (max-width: 991.98px) {
    .cuti-hutang-mobilebar {
        display: flex;
    }
    .hutang-cards-desktop {
        display: none;
    }
    .form-main-section {
        padding-bottom: 84px;
    }
}

/* ===== Mobile: Modal ringkasan cuti (approval + hutang) ===== */
.cuti-summary-modal {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.cuti-summary-section {
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius, 12px);
    padding: 1rem;
    background: var(--surface, #fff);
}
.cuti-summary-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
}
.cuti-summary-section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text, #111827);
}
.cuti-summary-section-title i {
    color: var(--primary, var(--hcis-blue-500));
}
.cuti-summary-pill {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    white-space: nowrap;
}
.cuti-summary-pill.is-ok {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-600);
}
.cuti-summary-pill.is-error {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-600);
}
.cuti-summary-muted {
    color: var(--text-muted, #6b7280);
    font-size: 0.85rem;
}
.cuti-summary-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.cuti-summary-step {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}
.cuti-summary-step-idx {
    flex-shrink: 0;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 999px;
    background: var(--primary-light, #e0edff);
    color: var(--primary, var(--hcis-blue-500));
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cuti-summary-step-body {
    flex: 1;
    min-width: 0;
}
.cuti-summary-step-title {
    font-weight: 600;
    color: var(--text, #111827);
    font-size: 0.9rem;
}
.cuti-summary-step-people {
    font-size: 0.8rem;
    color: var(--text-muted, #6b7280);
}
.cuti-summary-step-warn {
    font-size: 0.8rem;
    color: var(--hcis-red-600);
    margin-top: 0.15rem;
}
.cuti-summary-step-icon {
    font-size: 1.1rem;
    flex-shrink: 0;
}
.cuti-summary-step-icon.is-ok {
    color: var(--hcis-emerald-500);
}
.cuti-summary-step-icon.is-error {
    color: var(--hcis-red-500);
}
.cuti-summary-alert {
    display: flex;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border-radius: 10px;
    margin-top: 0.85rem;
}
.cuti-summary-alert i {
    font-size: 1.1rem;
    flex-shrink: 0;
}
.cuti-summary-alert p {
    margin: 0.15rem 0 0;
    font-size: 0.82rem;
}
.cuti-summary-alert.is-ok {
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.25);
    color: var(--hcis-emerald-800);
}
.cuti-summary-alert.is-ok i {
    color: var(--hcis-emerald-500);
}
.cuti-summary-alert.is-error {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: var(--hcis-red-800);
}
.cuti-summary-alert.is-error i {
    color: var(--hcis-red-500);
}
.cuti-summary-statusline {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}
.cuti-summary-statusline.is-ok,
.cuti-summary-statusline.is-ok i {
    color: var(--hcis-emerald-600);
}
.cuti-summary-statusline.is-warning,
.cuti-summary-statusline.is-warning i {
    color: var(--hcis-amber-600);
}
.cuti-summary-statusline.is-error,
.cuti-summary-statusline.is-error i {
    color: var(--hcis-red-600);
}
.cuti-summary-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}
.cuti-summary-stat {
    background: var(--surface-alt, var(--hcis-slate-50));
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    padding: 0.6rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.cuti-summary-stat span {
    font-size: 0.72rem;
    color: var(--text-muted, #6b7280);
}
.cuti-summary-stat strong {
    font-size: 1rem;
    color: var(--text, #111827);
}
.cuti-summary-note {
    font-size: 0.82rem;
    color: var(--text-muted, #6b7280);
    margin: 0.75rem 0 0;
}
.cuti-summary-link {
    margin-top: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: none;
    border: none;
    color: var(--primary, var(--hcis-blue-500));
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0;
}

/* Cuti Cukup Info Styles */
.cuti-sufficient-info {
    margin-top: 1.5rem;
    padding: 1.25rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: var(--radius);
    animation: cuti-slideDown 0.3s ease;
}

.cuti-sufficient-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--success);
    font-weight: 600;
    font-size: 0.95rem;
}

.cuti-sufficient-header i {
    font-size: 1.1rem;
}

.cuti-sufficient-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.cuti-sufficient-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(16, 185, 129, 0.1);
}

.cuti-value.cuti-positive {
    color: var(--success);
    background: rgba(16, 185, 129, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.cuti-duration-card.cuti-highlight .cuti-value.cuti-positive {
    background: rgba(16, 185, 129, 0.3);
    color: var(--hcis-green-200);
    border-color: rgba(16, 185, 129, 0.5);
}

/* Empty State */
.cuti-empty-state {
    margin-top: 1.5rem;
    padding: 1.5rem;
    text-align: center;
    color: var(--text-muted);
    background: rgba(100, 116, 139, 0.05);
    border-radius: var(--radius);
    border: 1px dashed var(--border);
}

.cuti-duration-card.cuti-highlight .cuti-empty-state {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.8);
}

.cuti-empty-state i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    display: block;
    opacity: 0.5;
}

.cuti-empty-state span {
    font-size: 0.9rem;
}

/* Approval Flow Card */
.approval-flow-card {
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(79, 70, 229, 0.14);
    background: linear-gradient(180deg, rgba(79, 70, 229, 0.04), rgba(79, 70, 229, 0.02)), var(--surface);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.approval-flow-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.approval-flow-title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.approval-flow-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.16), rgba(99, 102, 241, 0.1));
    color: var(--primary);
}

.approval-flow-title-wrap h4 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text);
}

.approval-flow-title-wrap p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1.4;
}

.approval-flow-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    background: rgba(100, 116, 139, 0.12);
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}

.approval-flow-badge.active {
    background: rgba(79, 70, 229, 0.12);
    color: var(--primary);
}

.approval-flow-loading,
.approval-flow-empty {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--radius);
    background: rgba(100, 116, 139, 0.06);
    color: var(--text-muted);
}

.approval-flow-loading {
    justify-content: flex-start;
}

.approval-flow-empty i {
    font-size: 1.3rem;
    color: var(--primary);
    flex-shrink: 0;
}

.approval-flow-empty strong {
    display: block;
    color: var(--text);
    margin-bottom: 0.15rem;
    font-size: 0.9rem;
}

.approval-flow-empty p {
    margin: 0;
    font-size: 0.82rem;
    line-height: 1.45;
}

.approval-flow-timeline {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    padding: 0.35rem 0 0.1rem;
}

.approval-flow-rail {
    position: absolute;
    left: 18px;
    top: 8px;
    bottom: 72px;
    width: 2px;
    background: linear-gradient(180deg, rgba(79, 70, 229, 0.45), rgba(79, 70, 229, 0.08));
}

.approval-flow-node {
    position: relative;
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
    padding-left: 2.45rem;
}

.approval-flow-node-badge {
    position: absolute;
    left: 0;
    top: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    box-shadow: 0 10px 18px rgba(79, 70, 229, 0.22);
    color: white;
}

.approval-flow-node-number {
    font-size: 0.8rem;
    font-weight: 800;
}

.approval-flow-node-arrow {
    position: absolute;
    right: -23px;
    top: 9px;
    color: rgba(79, 70, 229, 0.5);
    font-size: 0.95rem;
}

.approval-flow-node-body {
    width: 100%;
    padding: 0.95rem 1rem;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.16);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.approval-flow-node-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.45rem;
}

.approval-flow-node-title h5 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text);
}

.approval-flow-node-title.is-missing h5 {
    color: var(--hcis-amber-700);
}

.approval-flow-node-missing-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.15);
    color: var(--hcis-amber-800);
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}

.approval-flow-step {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    padding: 0.95rem;
    border-radius: 18px;
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.16);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.approval-flow-step-index {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-size: 0.85rem;
    font-weight: 800;
    box-shadow: 0 10px 18px rgba(79, 70, 229, 0.22);
}

.approval-flow-step-content {
    min-width: 0;
    flex: 1;
}

.approval-flow-step-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.55rem;
}

.approval-flow-step-top h5 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text);
}

.approval-flow-step-source {
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: rgba(100, 116, 139, 0.1);
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.approval-flow-step-approvers {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.approval-flow-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.08);
    color: var(--primary-dark);
    font-size: 0.8rem;
    font-weight: 600;
}

.approval-flow-chip.group {
    background: rgba(16, 185, 129, 0.1);
    color: #0f766e;
}

.approval-flow-chip i {
    font-size: 0.85rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .cuti-duration-card {
        padding: 1.25rem;
    }
    .approval-flow-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .approval-flow-step {
        padding: 0.85rem;
    }

    .approval-flow-step-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .approval-flow-badge {
        width: 100%;
        justify-content: center;
    }

    .detail-left-col {
        /* border-right: 1px solid var(--hcis-legacy-bootstrap-gray); */
        padding: 0rem;
        border: none;
    }

    .cuti-duration-value {
        font-size: 2.5rem;
    }

    .cuti-hutang-details,
    .cuti-sufficient-details {
        gap: 0.5rem;
    }

    .cuti-hutang-item,
    .cuti-sufficient-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .cuti-hutang-warning,
    .cuti-sufficient-info {
        padding: 1rem;
    }

    .cuti-warning-header,
    .cuti-sufficient-header {
        font-size: 0.9rem;
    }
}

/* Additional Utility Classes untuk Cuti */
.cuti-text-center {
    text-align: center;
}

.cuti-mt-1 {
    margin-top: 0.5rem;
}

.cuti-mt-2 {
    margin-top: 1rem;
}

.cuti-mb-1 {
    margin-bottom: 0.5rem;
}

.cuti-p-1 {
    padding: 0.5rem;
}

.cuti-p-2 {
    padding: 1rem;
}

/* info bar */
.leave-info-panel {
}

/* Selected Date Styles */
.cell-date-wrapper.is-selected .date-cell {
    /* background: linear-gradient(135deg, var(--primary), var(--primary-dark)); */
    background: var(--primary);
    color: white;
    font-weight: 700;
    /* box-shadow: var(--shadow); */

    /* box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); */
    transform: scale(1.05);
    border: 2px solid white;
    position: relative;
    z-index: 2;
}

/* Hover effect for selected dates */
.cell-date-wrapper.is-selected .date-cell:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--hcis-indigo-700));
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.6);
}

/* Optional: Add a subtle pulse animation for selected dates */
@keyframes selectedPulse {
    0% {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
    50% {
        box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
    }
    100% {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
}

.cell-date-wrapper.is-selected .date-cell {
    animation: selectedPulse 2s ease-in-out infinite;
}

/* Alternative simpler version without animation */
/*
.cell-date-wrapper.is-selected .date-cell {
    background: var(--primary);
    color: white;
    font-weight: 600;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}
*/

/* For better visual hierarchy with other states */
.cell-date-wrapper.is-selected.is-today .date-cell {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-color: white;
}

.cell-date-wrapper.is-selected.is-weekend .date-cell {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.cell-date-wrapper.is-weekend .date-cell {
    /* color:  */
    color: var(--danger);
}

/* Ensure selected dates stand out from disabled dates */
.cell-date-wrapper.is-selected .date-cell.is-disabled {
    background: var(--secondary) !important;
    color: var(--light) !important;
    opacity: 0.6;
    transform: none;
    box-shadow: none;
    animation: none;
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

.approval-flow-node-admin {
    display: inline-flex;
    align-items: center;
    margin-left: 0.45rem;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--primary-dark);
    font-size: 0.72rem;
    font-weight: 800;
    vertical-align: middle;
}

.approval-flow-warning {
    margin-top: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    padding: 0.8rem 0.95rem;
    border-radius: 14px;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.22);
}

.approval-flow-warning i {
    color: var(--hcis-amber-600);
    margin-top: 0.1rem;
}

.approval-flow-warning strong {
    display: block;
    color: var(--hcis-amber-800);
    font-size: 0.84rem;
    margin-bottom: 0.12rem;
}

.approval-flow-warning p {
    margin: 0;
    color: var(--hcis-amber-800);
    font-size: 0.8rem;
    line-height: 1.4;
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
    .el-dialog__body {
        padding: 0 !important;
    }
    .el-dialog {
        margin-bottom: 0 !important;
    }
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

.is-dayoff-date .date-cell {
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

.dayoff-calendar-tooltip {
    max-width: 240px !important;
    white-space: normal !important;
    word-break: break-word;
    line-height: 1.35 !important;
    text-align: left;
    padding: 8px 10px !important;
    font-size: 12px !important;
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
    /* overflow: hidden; */
}

.el-dialog {
    display: flex;
    margin: 0px;
    flex-direction: column; /* Tata header, body, footer secara vertikal */
    max-height: calc(100vh - 0px); /* Sesuaikan Xpx untuk margin/padding di sekitar modal */
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
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
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
    content: '';
    position: absolute;
    top: 0%;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
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
    content: '';
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
    /* background: var(--surface); */
    border-radius: 20px;
    /* overflow: hidden; */
    /* box-shadow: var(--shadow); */
    /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); */
    /* border: 1px solid var(--border); */
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
.header-expired {
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
    /* background: rgba(255, 255, 255, 0.2); */
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

.form-group label {
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
.calendar-lazy-wrapper {
    position: relative;
    min-height: 360px;
}
.calendar-lazy-wrapper.is-loading {
    cursor: wait;
}
.calendar-lazy-wrapper.is-loading .cell-date-wrapper {
    pointer-events: none;
}
.calendar-loading-overlay {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(2px);
}
.calendar-loading-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    min-width: 190px;
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: 12px;
    padding: 1rem;
    background: var(--hcis-white);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
    color: var(--hcis-slate-700);
    text-align: center;
}
.calendar-loading-card i {
    color: var(--hcis-indigo-600);
    font-size: 1.3rem;
}
.calendar-loading-card strong {
    color: var(--hcis-slate-800);
    font-size: 0.95rem;
}
.calendar-loading-card span {
    color: var(--hcis-slate-500);
    font-size: 0.78rem;
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
    transition:
        background-color 0.2s,
        color 0.2s;
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
    transition:
        background-color 0.2s,
        color 0.2s;
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
    content: '';
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
.cell-date-wrapper.is-disabled-custom {
    cursor: not-allowed !important;
}
.is-disabled-custom .date-cell {
    background-color: transparent !important;
    cursor: not-allowed !important;
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
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
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
    /* .el-dialog.is-align-center {
        margin: auto 1rem;
    } */
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
        align-self: center;
        font-size: 15px;
        font-weight: 600;
    }
    .el-dialog {
        border-top-left-radius: 20px;
        border-top-right-radius: 20px;
    }
    .el-calendar__button-group {
        width: 100%;
    }

    .el-button-group {
        width: 100%;
        display: flex;
    }

    .el-button.el-button--small {
        flex: 1;
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
        /* margin: 0.5rem; */
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
