<template>
    <div class="cb-app-container">
        <HeaderKpi
            title="Cuti Bersama"
            subtitle="Kelola pemotongan cuti bersama karyawan"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <div class="cb-stats-shell">
            <QuickStat :stats="quickStatItems" />
        </div>

        <div class="cb-main-wrapper">
            <section class="cb-panel-card">
                <div class="cb-panel-header">
                    <div class="cb-panel-header-info">
                        <div class="cb-panel-header-icon">
                            <i class="bi bi-calendar2-check"></i>
                        </div>
                        <div>
                            <h2 class="cb-panel-title">Daftar Cuti Bersama</h2>
                            <p class="cb-panel-subtitle">
                                Kelola draft, eksekusi, dan rollback cuti bersama dengan kontrol yang lebih terstruktur.
                            </p>
                        </div>
                    </div>
                    <div class="cb-panel-actions">
                        <button type="button" class="cb-btn cb-btn-ghost" @click="loadList">
                            <i class="bi bi-arrow-clockwise"></i>
                            <span>Refresh</span>
                        </button>
                        <button type="button" class="cb-btn cb-btn-primary" @click="openWizard()">
                            <i class="bi bi-plus-circle"></i>
                            <span>Buat Cuti Bersama</span>
                        </button>
                    </div>
                </div>

                <div class="cb-list-filter-wrap">
                    <ModernFilter
                        :config="listFilterConfig"
                        :filters="listFilters"
                        @filters-update="handleListFilters"
                    />
                </div>

                <!-- <div class="cb-list-meta" v-if="cbList.length > 0">
                    <span class="cb-meta-chip">
                        <i class="bi bi-calendar2-week"></i>
                        <strong>{{ cbList.length }}</strong>
                        <small>Total</small>
                    </span>
                    <span class="cb-meta-chip success">
                        <i class="bi bi-check2-circle"></i>
                        <strong>{{ executedCount }}</strong>
                        <small>Executed</small>
                    </span>
                    <span class="cb-meta-chip muted">
                        <i class="bi bi-pencil-square"></i>
                        <strong>{{ draftCount }}</strong>
                        <small>Draft</small>
                    </span>
                    <span class="cb-meta-chip info">
                        <i class="bi bi-people"></i>
                        <strong>{{ impactedCount }}</strong>
                        <small>Terdampak</small>
                    </span>
                </div> -->

                <div v-if="loading.list" class="cb-loading-state">
                    <div class="cb-loading-spinner">
                        <i class="bi bi-arrow-repeat spin"></i>
                    </div>
                    <p class="cb-loading-title">Memuat data cuti bersama...</p>
                    <p class="cb-loading-description">Mohon tunggu, data sedang disinkronkan.</p>
                </div>

                <div v-else-if="cbList.length === 0" class="cb-empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <template v-if="hasActiveListFilters">
                        <h5>Data Tidak Ditemukan</h5>
                        <p>Tidak ada cuti bersama yang cocok dengan kata kunci atau rentang tanggal yang dipilih.</p>
                        <button type="button" class="cb-btn cb-btn-secondary" @click="resetListFilters">
                            <i class="bi bi-funnel"></i>
                            <span>Reset Filter</span>
                        </button>
                    </template>
                    <template v-else>
                        <h5>Belum Ada Cuti Bersama</h5>
                        <p>Mulai buat cuti bersama pertama.</p>
                        <button type="button" class="cb-btn cb-btn-primary" @click="openWizard()">
                            <i class="bi bi-plus-circle"></i>
                            <span>Buat Cuti Bersama</span>
                        </button>
                    </template>
                </div>

                <div v-else class="cb-table-wrapper">
                    <table class="table cb-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Tanggal</th>
                                <th class="text-center">Hari</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Jadwal / Waktu</th>
                                <th class="text-center">Terdampak</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in cbList" :key="item.Id_Cuti_Bersama">
                                <td data-label="Nama">
                                    <div class="cb-name-cell">
                                        <strong>{{ item.Nama_Cuti_Bersama }}</strong>
                                    </div>
                                </td>
                                <td data-label="Tanggal">
                                    {{ formatDate(item.Tanggal_Mulai) }} -
                                    {{ formatDate(item.Tanggal_Selesai) }}
                                </td>
                                <td class="text-center" data-label="Hari">
                                    <span class="cb-pill cb-pill-primary">
                                        {{ item.Jumlah_Hari }}
                                    </span>
                                </td>
                                <td class="text-center" data-label="Status">
                                    <span :class="['cb-pill', executionBadgeClass(item)]">
                                        {{ executionBadgeLabel(item) }}
                                    </span>
                                    <small v-if="executionStatusReason(item)" class="cb-muted d-block mt-1">
                                        {{ executionStatusReason(item) }}
                                    </small>
                                </td>
                                <td class="text-center" data-label="Jadwal / Waktu">
                                    <div class="cb-name-cell cb-time-cell">
                                        <strong>{{ executionTimeLabel(item) }}</strong>
                                        <small>{{ formatDateTime(executionTimeValue(item)) }}</small>
                                    </div>
                                </td>
                                <td class="text-center" data-label="Terdampak">
                                    <span v-if="item.Flag_Executed === 'Y'" class="cb-pill cb-pill-dark">
                                        {{ item.Total_Karyawan_Terdampak }}
                                    </span>
                                    <span v-else class="cb-muted">-</span>
                                </td>
                                <td class="text-center" data-label="Aksi">
                                    <div class="cb-action-group">
                                        <button
                                            type="button"
                                            class="cb-action-btn cb-view"
                                            title="Detail"
                                            aria-label="Lihat detail"
                                            @click="openDetail(item)"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button
                                            type="button"
                                            v-if="canModifyItem(item)"
                                            class="cb-action-btn cb-edit"
                                            title="Edit"
                                            aria-label="Edit cuti bersama"
                                            @click="openWizard(item)"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button
                                            type="button"
                                            v-if="canExecuteItem(item)"
                                            class="cb-action-btn cb-execute"
                                            :title="executeButtonTitle(item)"
                                            aria-label="Eksekusi cuti bersama"
                                            @click="startExecute(item)"
                                        >
                                            <i class="bi bi-lightning-charge"></i>
                                        </button>
                                        <button
                                            type="button"
                                            v-if="canCancelItem(item)"
                                            class="cb-action-btn cb-cancel"
                                            :title="cancelButtonTitle(item)"
                                            aria-label="Batalkan jadwal cuti bersama"
                                            @click="confirmCancelSchedule(item)"
                                        >
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                        <button
                                            type="button"
                                            v-if="canRollbackItem(item)"
                                            class="cb-action-btn cb-rollback"
                                            title="Rollback"
                                            aria-label="Rollback cuti bersama"
                                            @click="openRollback(item)"
                                        >
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                        <button
                                            type="button"
                                            v-if="canModifyItem(item)"
                                            class="cb-action-btn cb-delete"
                                            title="Hapus"
                                            aria-label="Hapus cuti bersama"
                                            @click="confirmDelete(item)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="listPagination.total_pages > 1" class="cb-pagination-bar">
                        <div class="cb-pagination-info">
                            <span class="cb-pagination-text">
                                Menampilkan <strong>{{ listPageStart }}-{{ listPageEnd }}</strong> dari <strong>{{ listPagination.total }}</strong> data
                            </span>
                        </div>
                        <div class="cb-pagination-controls">
                            <button
                                type="button"
                                class="cb-pagination-btn"
                                :disabled="loading.list || listPagination.current_page <= 1"
                                @click="changeListPage(listPagination.current_page - 1)"
                                title="Halaman sebelumnya"
                            >
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span class="cb-page-indicator">
                                {{ listPagination.current_page }} / {{ listPagination.total_pages }}
                            </span>
                            <button
                                type="button"
                                class="cb-pagination-btn"
                                :disabled="loading.list || listPagination.current_page >= listPagination.total_pages"
                                @click="changeListPage(listPagination.current_page + 1)"
                                title="Halaman berikutnya"
                            >
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <Modal
            :show="showWizard"
            :title="wizardTitle"
            size="large"
            bodyClass="cb-wizard-modal-shell"
            @close="showWizard = false"
        >
            <template #body>
                <div class="cb-modal-body cb-modal-body-wizard">
                    <div v-if="loading.wizard_init" class="cb-wizard-loading">
                        <i class="bi bi-arrow-repeat spin"></i>
                        <h6>Menyiapkan data form...</h6>
                        <p>Modal sudah dibuka, data sedang diambil secara async.</p>
                    </div>

                    <template v-else>
                        <div class="cb-wizard-steps mb-4" role="list" aria-label="Tahapan cuti bersama">
                            <div
                                v-for="(s, i) in wizardSteps"
                                :key="i"
                                :class="[
                                    'cb-wizard-step',
                                    {
                                        active: wizardStep === i,
                                    },
                                ]"
                                role="listitem"
                                :aria-current="wizardStep === i ? 'step' : undefined"
                            >
                                <div class="cb-step-number" :title="s">
                                    {{ i + 1 }}
                                </div>
                                <div class="cb-step-label">{{ s }}</div>
                                <div v-if="i < wizardSteps.length - 1" class="cb-step-connector"></div>
                            </div>
                        </div>

                        <div class="cb-step-head">
                            <div class="cb-step-head-icon">
                                <i :class="activeStepMeta.icon"></i>
                            </div>
                            <div>
                                <h6>{{ activeStepMeta.title }}</h6>
                                <p>{{ activeStepMeta.desc }}</p>
                            </div>
                        </div>

                        <div v-if="wizardValidationHint" class="cb-warning-inline">
                            <i class="bi bi-info-circle"></i>
                            <span>{{ wizardValidationHint }}</span>
                        </div>

                        <div v-if="wizardStep === 0" class="cb-form-grid">
                            <div class="cb-field">
                                <label class="cb-label">Nama Cuti Bersama <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.nama"
                                    class="form-control cb-input"
                                    placeholder="Contoh: Cuti Bersama Lebaran 2026"
                                />
                            </div>
                            <div class="cb-field">
                                <label class="cb-label">Pilih Banyak Tanggal <span class="text-danger">*</span></label>
                                <el-date-picker
                                    v-model="form.selected_dates"
                                    type="dates"
                                    value-format="YYYY-MM-DD"
                                    format="DD/MM/YYYY"
                                    placeholder="Pilih satu atau lebih tanggal"
                                    class="cb-date-picker"
                                    style="width: 100%"
                                    :clearable="true"
                                    :editable="false"
                                    popper-class="cb-date-popper"
                                    @change="handleSelectedDatesChange"
                                />
                            </div>
                            <div class="cb-field full">
                                <label class="cb-label">Keterangan</label>
                                <textarea
                                    v-model="form.keterangan"
                                    class="form-control cb-input cb-textarea"
                                    rows="3"
                                    placeholder="Catatan tambahan untuk informasi cuti bersama (opsional)"
                                ></textarea>
                            </div>
                        </div>

                        <div v-if="wizardStep === 1">
                            <div class="cb-target-mode-card mb-3">
                                <label class="cb-check-row">
                                    <input type="checkbox" v-model="useUnitFilter" @change="onTargetModeChange" />
                                    <span>
                                        Gunakan Filter Unit
                                        <small>Centang cabang/divisi/sub divisi untuk mempersempit target.</small>
                                    </span>
                                </label>
                                <label class="cb-check-row">
                                    <input type="checkbox" v-model="useSpecificTarget" @change="onTargetModeChange" />
                                    <span>
                                        Tambahkan Karyawan Spesifik
                                        <small>Pilihan karyawan ini bisa dikombinasikan dengan Filter Unit.</small>
                                    </span>
                                </label>
                            </div>

                            <div v-if="useUnitFilter" class="cb-form-grid cb-filter-checkbox-grid">
                                <div class="cb-field full">
                                    <label class="cb-label">Cabang</label>
                                    <div class="cb-checkbox-board">
                                        <label v-for="c in filterOptions.cabang" :key="c" class="cb-check-item">
                                            <input
                                                type="checkbox"
                                                :value="c"
                                                v-model="form.filter_cabang"
                                                @change="queueTargetExpectation"
                                            />
                                            <span>{{ c }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="cb-field">
                                    <label class="cb-label">Divisi</label>
                                    <div class="cb-checkbox-board">
                                        <label
                                            v-for="d in filterOptions.divisi"
                                            :key="d.ID_Divisi"
                                            class="cb-check-item"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="d.ID_Divisi"
                                                v-model="form.filter_divisi"
                                                @change="onDivisiSelectionChange"
                                            />
                                            <span>{{ d.nama_divisi }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="cb-field">
                                    <label class="cb-label">Sub Divisi</label>
                                    <div class="cb-checkbox-board">
                                        <label
                                            v-for="sd in filteredSubDivisi"
                                            :key="sd.ID_Sub_Divisi"
                                            class="cb-check-item"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="sd.ID_Sub_Divisi"
                                                v-model="form.filter_sub_divisi"
                                                @change="queueTargetExpectation"
                                            />
                                            <span>{{ sd.nama_sub_divisi }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div v-if="useSpecificTarget" class="cb-field mt-3">
                                <label class="cb-label">Cari Karyawan</label>
                                <input
                                    type="text"
                                    v-model="karyawanSearch"
                                    class="form-control cb-input"
                                    placeholder="Ketik nama atau kode..."
                                    @input="debounceSearchKaryawan"
                                />
                                <small class="cb-help"
                                    >Karyawan spesifik digabung dengan filter unit. Jika karyawan yang dipilih sudah
                                    masuk hasil filter unit, total terdampak bisa tetap sama.</small
                                >
                                <div class="cb-search-results" v-if="searchResultsUnique.length > 0">
                                    <div
                                        v-for="k in searchResultsUnique"
                                        :key="k.Kode_Karyawan"
                                        class="cb-search-item"
                                        tabindex="0"
                                        role="button"
                                        @click="addSpecificKaryawan(k)"
                                        @keydown.enter="addSpecificKaryawan(k)"
                                        @keydown.space.prevent="addSpecificKaryawan(k)"
                                    >
                                        <strong>{{ k.Nama }}</strong>
                                        <small>({{ k.Kode_Karyawan }})</small>
                                        <span>{{ k.nama_divisi }}</span>
                                    </div>
                                </div>
                                <div v-if="selectedSpecificKaryawan.length > 0" class="cb-selected-chips">
                                    <span v-for="k in selectedSpecificKaryawan" :key="k.Kode_Karyawan" class="cb-chip">
                                        {{ k.Nama }} ({{ k.Kode_Karyawan }})
                                        <i class="bi bi-x" @click="removeSpecificKaryawan(k.Kode_Karyawan)"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="cb-target-preview mt-3">
                                <div class="cb-target-preview-head">
                                    <strong>Expected Hasil Filter Tujuan</strong>
                                    <span class="cb-pill cb-pill-info">{{ targetModeLabel }}</span>
                                </div>
                                <div class="cb-target-preview-meta">
                                    <span
                                        >Cabang: <strong>{{ form.filter_cabang.length }}</strong></span
                                    >
                                    <span
                                        >Divisi: <strong>{{ form.filter_divisi.length }}</strong></span
                                    >
                                    <span
                                        >Sub Divisi: <strong>{{ form.filter_sub_divisi.length }}</strong></span
                                    >
                                    <span
                                        >Karyawan Spesifik: <strong>{{ selectedSpecificKaryawan.length }}</strong></span
                                    >
                                </div>
                                <p v-if="targetExpectation.loading" class="cb-muted mb-0">
                                    Menghitung estimasi target...
                                </p>
                                <p v-else-if="targetExpectation.error" class="text-danger mb-0">
                                    {{ targetExpectation.error }}
                                </p>
                                <p v-else class="mb-0">
                                    Estimasi karyawan terdampak: <strong>{{ targetExpectation.total ?? '-' }}</strong>
                                </p>
                                <p v-if="useUnitFilter && useSpecificTarget" class="cb-help mb-0 mt-1">
                                    Mode ini menggunakan gabungan target unit dan karyawan spesifik (`OR`).
                                </p>
                            </div>
                        </div>

                        <div v-if="wizardStep === 2">
                            <div class="cb-field">
                                <label class="cb-label">Cari Karyawan Target untuk Dikecualikan</label>
                                <input
                                    type="text"
                                    v-model="exclusionSearch"
                                    class="form-control cb-input"
                                    placeholder="Ketik nama atau kode target karyawan..."
                                    @input="debounceSearchExclusion"
                                />
                                <small class="cb-help"
                                    >Daftar ini mengikuti target yang dipilih di step sebelumnya, bukan seluruh karyawan
                                    aktif.</small
                                >
                            </div>
                            <div class="cb-search-results" v-if="exclusionResults.length > 0">
                                <div
                                    v-for="k in exclusionResults"
                                    :key="k.Kode_Karyawan"
                                    class="cb-search-item"
                                    tabindex="0"
                                    role="button"
                                    @click="addExclusion(k)"
                                    @keydown.enter="addExclusion(k)"
                                    @keydown.space.prevent="addExclusion(k)"
                                >
                                    <strong>{{ k.Nama }}</strong>
                                    <small>({{ k.Kode_Karyawan }})</small>
                                </div>
                            </div>
                            <div v-if="form.exclusions.length > 0" class="mt-3">
                                <h6 class="cb-section-title">Daftar Pengecualian ({{ form.exclusions.length }})</h6>
                                <div
                                    v-for="(exc, i) in form.exclusions"
                                    :key="exc.kode_karyawan"
                                    class="cb-exclusion-item"
                                >
                                    <div class="cb-exclusion-main">
                                        <span class="fw-semibold">{{ exc.nama }}</span>
                                        <small class="cb-muted">({{ exc.kode_karyawan }})</small>
                                    </div>
                                    <input
                                        type="text"
                                        v-model="exc.alasan"
                                        class="form-control form-control-sm cb-input"
                                        placeholder="Alasan (opsional)"
                                    />
                                    <button
                                        type="button"
                                        class="cb-action-btn cb-delete"
                                        @click="form.exclusions.splice(i, 1)"
                                    >
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="cb-empty-inline">
                                <i class="bi bi-person-check"></i>
                                <p>Belum ada pengecualian. Semua karyawan sesuai target akan terdampak.</p>
                            </div>
                        </div>

                        <div v-if="wizardStep === 3">
                            <div v-if="loading.preview" class="cb-loading-inline">
                                <i class="bi bi-arrow-repeat spin"></i>
                                <p>Menghitung dampak...</p>
                            </div>
                            <div v-else-if="previewData">
                                <div class="cb-review-card">
                                    <div class="cb-review-grid">
                                        <span class="cb-review-chip">
                                            <i class="bi bi-diagram-3"></i>
                                            Target: <strong>{{ targetModeLabel }}</strong>
                                        </span>
                                        <span class="cb-review-chip">
                                            <i class="bi bi-calendar2-week"></i>
                                            Durasi: <strong>{{ computedDays }} hari</strong>
                                        </span>
                                        <span class="cb-review-chip">
                                            <i class="bi bi-person-x"></i>
                                            Pengecualian: <strong>{{ form.exclusions.length }}</strong>
                                        </span>
                                    </div>

                                    <div class="cb-execution-plan">
                                        <div class="cb-target-preview-head">
                                            <strong>Rencana Eksekusi</strong>
                                            <span class="cb-pill cb-pill-info">{{ submitExecutionLabel }}</span>
                                        </div>
                                        <div class="cb-target-switch">
                                            <button
                                                type="button"
                                                :class="['cb-chip-btn', submitExecutionMode === 'draft' && 'active']"
                                                @click="setSubmitExecutionMode('draft')"
                                            >
                                                Simpan Draft
                                            </button>
                                            <button
                                                type="button"
                                                :class="['cb-chip-btn', submitExecutionMode === 'now' && 'active']"
                                                @click="setSubmitExecutionMode('now')"
                                            >
                                                Simpan & Execute
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'cb-chip-btn',
                                                    submitExecutionMode === 'scheduled' && 'active',
                                                ]"
                                                @click="setSubmitExecutionMode('scheduled')"
                                            >
                                                Simpan & Jadwalkan
                                            </button>
                                        </div>
                                        <p class="cb-help mb-0">
                                            Pilih draft jika ingin menyimpan dulu. Pilih terjadwal jika eksekusi harus
                                            jalan di tanggal dan jam tertentu.
                                        </p>
                                        <div v-if="submitExecutionMode === 'scheduled'" class="cb-field mt-3">
                                            <label class="cb-label">Tanggal & Jam Eksekusi</label>
                                            <input
                                                type="datetime-local"
                                                v-model="submitScheduledAt"
                                                class="form-control cb-input"
                                                :min="minScheduleAt"
                                            />
                                        </div>
                                    </div>

                                    <label class="cb-confirm-check">
                                        <input type="checkbox" v-model="previewConfirmed" />
                                        <span>Saya sudah cek preview dan siap menyimpan cuti bersama ini</span>
                                    </label>
                                </div>

                                <div class="cb-preview-grid">
                                    <div class="cb-preview-card success">
                                        <span class="cb-preview-value">{{
                                            formatInteger(previewData.saldo_cukup)
                                        }}</span>
                                        <span class="cb-preview-label">Saldo Cukup</span>
                                    </div>
                                    <div class="cb-preview-card warning">
                                        <span class="cb-preview-value">{{
                                            formatInteger(previewData.saldo_kurang)
                                        }}</span>
                                        <span class="cb-preview-label">Akan Hutang</span>
                                    </div>
                                    <div class="cb-preview-card danger">
                                        <span class="cb-preview-value">{{
                                            formatInteger(previewData.sudah_hutang)
                                        }}</span>
                                        <span class="cb-preview-label">Sudah Ada Hutang Cuti</span>
                                    </div>
                                </div>

                                <div class="cb-info-banner mt-3">
                                    <i class="bi bi-people"></i>
                                    <span
                                        >Total terdampak:
                                        <strong>{{ previewData.total_terdampak }}</strong>
                                        | Dikecualikan:
                                        <strong>{{ previewData.total_excluded }}</strong></span
                                    >
                                </div>

                                <div
                                    v-if="previewOverlapSummary.has_conflict"
                                    :class="[
                                        'cb-overlap-banner',
                                        previewOverlapSummary.has_blocking_conflict ? 'blocking' : 'warning',
                                    ]"
                                >
                                    <div class="cb-overlap-head">
                                        <i
                                            :class="
                                                previewOverlapSummary.has_blocking_conflict
                                                    ? 'bi bi-exclamation-octagon'
                                                    : 'bi bi-exclamation-triangle'
                                            "
                                        ></i>
                                        <strong>
                                            {{
                                                previewOverlapSummary.has_blocking_conflict
                                                    ? 'Ditemukan konflik overlap yang memblokir execute'
                                                    : 'Ditemukan potensi overlap target dan tanggal'
                                            }}
                                        </strong>
                                    </div>
                                    <p class="mb-2">
                                        Konflik: <strong>{{ previewOverlapSummary.total_conflicts }}</strong> |
                                        Blocking: <strong>{{ previewOverlapSummary.blocking_conflicts }}</strong>
                                    </p>
                                    <ul class="cb-overlap-list mb-0">
                                        <li
                                            v-for="conflict in previewOverlapSummary.conflicts.slice(0, 3)"
                                            :key="conflict.id_cuti_bersama"
                                        >
                                            <strong>{{ conflict.nama_cuti_bersama }}</strong>
                                            ({{ conflict.status_label }}) - Tanggal bentrok:
                                            {{ (conflict.overlap_dates || []).slice(0, 3).join(', ') || '-' }} -
                                            Karyawan bentrok: {{ conflict.overlap_karyawan_count }}
                                        </li>
                                    </ul>
                                </div>

                                <div class="cb-table-wrapper mt-3" v-if="previewData.sample_detail?.length">
                                    <div
                                        v-if="previewConflictSelectableKodes.length"
                                        class="cb-detail-action-bar px-2 pt-2"
                                    >
                                        <label class="cb-check-item mb-0">
                                            <input
                                                type="checkbox"
                                                :checked="previewConflictAllSelected"
                                                @change="toggleSelectAllPreviewConflicts($event)"
                                            />
                                            <span>Pilih semua konflik</span>
                                        </label>
                                        <button
                                            type="button"
                                            class="cb-btn cb-btn-warning cb-btn-inline mt-0"
                                            :disabled="!previewConflictSelectedCount || loading.preview"
                                            @click="bulkAddPreviewConflictsToExclusion"
                                        >
                                            <i class="bi bi-person-x"></i>
                                            <span>Kecualikan Terpilih ({{ previewConflictSelectedCount }})</span>
                                        </button>
                                    </div>
                                    <table class="table cb-table cb-table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Divisi</th>
                                                <th class="text-center">Sisa</th>
                                                <th class="text-center">Setelah</th>
                                                <th class="text-center">Kecualikan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="d in previewSampleDetailRows"
                                                :key="d.kode_karyawan"
                                                :class="{
                                                    'cb-preview-conflict-row': previewConflictKodeSet.has(
                                                        d.kode_karyawan,
                                                    ),
                                                }"
                                            >
                                                <td>
                                                    {{ d.nama }}
                                                    <span
                                                        v-if="previewConflictKodeSet.has(d.kode_karyawan)"
                                                        class="cb-preview-conflict-pill"
                                                    >
                                                        Konflik
                                                    </span>
                                                    <small
                                                        v-if="previewConflictKodeSet.has(d.kode_karyawan)"
                                                        class="cb-muted d-block mt-1"
                                                    >
                                                        Bentrok tanggal:
                                                        {{ formatPreviewConflictDates(d.kode_karyawan) }}
                                                    </small>
                                                </td>
                                                <td>{{ d.divisi }}</td>
                                                <td class="text-center">{{ formatInteger(d.sisa_cuti) }}</td>
                                                <td class="text-center">
                                                    <span
                                                        :class="
                                                            d.saldo_setelah < 0 ? 'text-danger fw-bold' : 'text-success'
                                                        "
                                                        >{{ formatInteger(d.saldo_setelah) }}</span
                                                    >
                                                </td>
                                                <td class="text-center">
                                                    <input
                                                        v-if="previewConflictKodeSet.has(d.kode_karyawan)"
                                                        type="checkbox"
                                                        :value="d.kode_karyawan"
                                                        v-model="previewConflictSelectedKodes"
                                                        :disabled="
                                                            exclusionKodeSet.has(d.kode_karyawan) || loading.preview
                                                        "
                                                    />
                                                    <span
                                                        v-if="
                                                            previewConflictKodeSet.has(d.kode_karyawan) &&
                                                            exclusionKodeSet.has(d.kode_karyawan)
                                                        "
                                                        class="cb-muted ms-2"
                                                    >
                                                        Sudah dikecualikan
                                                    </span>
                                                    <span v-else class="cb-muted">-</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <template #footer>
                <!-- <div class="cb-modal-footer cb-modal-footer-wizard"> -->
                <button
                    type="button"
                    class="cb-btn cb-btn-secondary"
                    @click="wizardStep > 0 ? wizardStep-- : (showWizard = false)"
                >
                    {{ wizardStep > 0 ? 'Kembali' : 'Batal' }}
                </button>
                <button
                    type="button"
                    v-if="wizardStep < 3"
                    class="cb-btn cb-btn-primary"
                    @click="nextWizardStep"
                    :disabled="loading.wizard_init"
                >
                    Lanjut
                    <i class="bi bi-arrow-right"></i>
                </button>
                <button
                    type="button"
                    v-else
                    class="cb-btn cb-btn-success"
                    @click="submitForm"
                    :disabled="loading.wizard_init || loading.submit || !canSubmitWizard"
                >
                    <i v-if="loading.submit" class="bi bi-arrow-repeat spin"></i>
                    <span>{{ submitButtonLabel }}</span>
                </button>
                <!-- </div> -->
            </template>
        </Modal>

        <Modal :show="showExecuteModal" title="Eksekusi Cuti Bersama" size="medium" @close="showExecuteModal = false">
            <template #body>
                <div class="cb-modal-body">
                    <div class="cb-warning-banner">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div>
                            <strong>Perhatian!</strong>
                            <p>Aksi ini akan memotong saldo cuti karyawan secara nyata.</p>
                        </div>
                    </div>
                    <div v-if="loading.execute_prep" class="cb-loading-inline">
                        <i class="bi bi-arrow-repeat spin"></i>
                        <p>Memuat detail cuti bersama yang akan dieksekusi...</p>
                    </div>
                    <template v-else-if="executeTarget">
                        <div class="cb-exec-target">
                            <strong>{{ executeTarget.Nama_Cuti_Bersama }}</strong>
                            <span>{{ executeTarget.Jumlah_Hari }} hari</span>
                        </div>
                        <div class="cb-exec-summary-card">
                            <div class="cb-exec-summary-grid">
                                <div class="cb-exec-summary-item">
                                    <span class="cb-exec-summary-label">Status</span>
                                    <span :class="['cb-pill', executionBadgeClass(executeTarget)]">
                                        {{ executionBadgeLabel(executeTarget) }}
                                    </span>
                                </div>
                                <div class="cb-exec-summary-item">
                                    <span class="cb-exec-summary-label">Range Tanggal</span>
                                    <strong>
                                        {{ formatDate(executeTarget.Tanggal_Mulai) }} -
                                        {{ formatDate(executeTarget.Tanggal_Selesai) }}
                                    </strong>
                                </div>
                                <div class="cb-exec-summary-item">
                                    <span class="cb-exec-summary-label">Terdampak</span>
                                    <strong>{{ executeTarget.Total_Karyawan_Terdampak ?? '-' }} karyawan</strong>
                                </div>
                                <div class="cb-exec-summary-item" v-if="executionTimeValue(executeTarget)">
                                    <span class="cb-exec-summary-label">{{ executionTimeLabel(executeTarget) }}</span>
                                    <strong>{{ formatDateTime(executionTimeValue(executeTarget)) }}</strong>
                                </div>
                            </div>

                            <div
                                v-if="executeExecutionDates.length"
                                class="cb-detail-date-highlight cb-detail-date-highlight-compact"
                            >
                                <span class="cb-detail-date-label">Tanggal pelaksanaan cuti bersama</span>
                                <div class="cb-detail-date-chips">
                                    <span
                                        v-for="date in executeExecutionDates"
                                        :key="date"
                                        class="cb-chip cb-chip-date cb-chip-date-highlight"
                                    >
                                        <i class="bi bi-calendar-event"></i>
                                        {{ formatDate(date) }}
                                    </span>
                                </div>
                            </div>

                            <p v-if="executeTarget.Keterangan" class="cb-exec-summary-note">
                                <span class="cb-exec-summary-label">Keterangan</span>
                                {{ executeTarget.Keterangan }}
                            </p>
                        </div>
                    </template>
                    <div class="cb-field">
                        <label class="cb-label">Mode Eksekusi</label>
                        <div class="cb-target-switch">
                            <button
                                type="button"
                                :class="['cb-chip-btn', executeMode === 'now' && 'active']"
                                @click="executeMode = 'now'"
                            >
                                Langsung
                            </button>
                            <button
                                type="button"
                                :class="['cb-chip-btn', executeMode === 'scheduled' && 'active']"
                                @click="executeMode = 'scheduled'"
                            >
                                Jadwalkan
                            </button>
                        </div>
                    </div>
                    <div v-if="executeMode === 'scheduled'" class="cb-field">
                        <label class="cb-label">Tanggal & Jam Eksekusi</label>
                        <input
                            type="datetime-local"
                            v-model="scheduledAt"
                            class="form-control cb-input"
                            :min="minScheduleAt"
                        />
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="cb-modal-footer">
                    <button type="button" class="cb-btn cb-btn-secondary" @click="showExecuteModal = false">
                        Batal
                    </button>
                    <button
                        type="button"
                        class="cb-btn cb-btn-danger"
                        @click="doExecute"
                        :disabled="loading.execute || (executeMode === 'scheduled' && !scheduledAt)"
                    >
                        <i v-if="loading.execute" class="bi bi-arrow-repeat spin"></i>
                        <i v-else class="bi bi-lightning-charge"></i>
                        <span>{{ executeMode === 'scheduled' ? 'Jadwalkan' : 'Eksekusi Sekarang' }}</span>
                    </button>
                </div>
            </template>
        </Modal>

        <Modal :show="showDetailModal" title="Detail Cuti Bersama" size="large" @close="showDetailModal = false">
            <template #body>
                <div v-if="loading.detail" class="cb-modal-body cb-detail-loading-shell">
                    <div class="cb-detail-loader">
                        <div class="cb-detail-loader-orb">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <h6>Memuat detail cuti bersama...</h6>
                            <p>Menyiapkan data karyawan terdampak, pengecualian, dan log eksekusi.</p>
                        </div>
                    </div>

                    <div class="cb-detail-skeleton">
                        <div class="cb-detail-skeleton-top">
                            <span class="cb-skeleton cb-skeleton-title"></span>
                            <span class="cb-skeleton cb-skeleton-pill"></span>
                        </div>
                        <div class="cb-detail-skeleton-meta">
                            <span class="cb-skeleton cb-skeleton-line short"></span>
                            <span class="cb-skeleton cb-skeleton-line medium"></span>
                        </div>
                        <div class="cb-detail-skeleton-tabs">
                            <span class="cb-skeleton cb-skeleton-tab active"></span>
                            <span class="cb-skeleton cb-skeleton-tab"></span>
                            <span class="cb-skeleton cb-skeleton-tab"></span>
                        </div>
                        <div class="cb-detail-skeleton-table">
                            <div v-for="row in 4" :key="row" class="cb-detail-skeleton-row">
                                <span class="cb-skeleton cb-skeleton-line medium"></span>
                                <span class="cb-skeleton cb-skeleton-line short"></span>
                                <span class="cb-skeleton cb-skeleton-line short"></span>
                                <span class="cb-skeleton cb-skeleton-line short"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="detailData" class="cb-modal-body">
                    <div class="cb-detail-head">
                        <div>
                            <h6>{{ detailData.cuti_bersama?.Nama_Cuti_Bersama }}</h6>
                            <p>{{ detailData.cuti_bersama?.Jumlah_Hari }} hari</p>
                            <div v-if="detailExecutionDates.length" class="cb-detail-date-highlight">
                                <span class="cb-detail-date-label">Tanggal pelaksanaan</span>
                                <div class="cb-detail-date-chips">
                                    <span
                                        v-for="date in detailExecutionDates"
                                        :key="date"
                                        class="cb-chip cb-chip-date cb-chip-date-highlight"
                                    >
                                        <i class="bi bi-calendar-event"></i>
                                        {{ formatDate(date) }}
                                    </span>
                                </div>
                            </div>
                            <p v-if="executionTimeValue(detailData.cuti_bersama)">
                                {{ executionTimeLabel(detailData.cuti_bersama) }}:
                                {{ formatDateTime(executionTimeValue(detailData.cuti_bersama)) }}
                            </p>
                        </div>
                        <span :class="['cb-pill', executionBadgeClass(detailData.cuti_bersama)]">
                            {{ executionBadgeLabel(detailData.cuti_bersama) }}
                        </span>
                    </div>

                    <ApprovalTabs :tabs="detailTabs" :activeTab="detailTab" @tab-change="detailTab = $event">
                        <template #karyawan>
                            <div class="cb-tab-pane" :key="`karyawan-${detailTab}`">
                                <div v-if="loading.detail" class="cb-loading-inline">
                                    <i class="bi bi-arrow-repeat spin"></i>
                                </div>
                                <div v-else-if="detailData.details?.length" class="cb-table-wrapper">
                                    <div class="cb-field px-2 pt-2">
                                        <input
                                            type="text"
                                            v-model.trim="detailImpactSearch"
                                            class="form-control cb-input"
                                            placeholder="Cari nama, divisi, atau sub divisi..."
                                        />
                                    </div>
                                    <div v-if="detailCanManageExclusions" class="cb-detail-action-bar">
                                        <label class="cb-check-item mb-0">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    detailImpactVisibleSelectedCount > 0 &&
                                                    detailImpactVisibleSelectedCount ===
                                                        detailImpactedFilteredRows.length
                                                "
                                                @change="toggleSelectAllImpacted($event)"
                                            />
                                            <span>Pilih semua</span>
                                        </label>
                                        <button
                                            type="button"
                                            class="cb-btn cb-btn-warning cb-btn-inline"
                                            :disabled="
                                                !detailSelectedImpactKodes.length || loading.detail_exclusion_action
                                            "
                                            @click="bulkExcludeImpactedKaryawan"
                                        >
                                            <i class="bi bi-person-x"></i>
                                            <span>Kecualikan Terpilih ({{ detailSelectedImpactKodes.length }})</span>
                                        </button>
                                    </div>
                                    <table class="table cb-table cb-table-sm">
                                        <thead>
                                            <tr>
                                                <th v-if="detailCanManageExclusions" class="text-center">Pilih</th>
                                                <th>Nama</th>
                                                <th>Divisi</th>
                                                <th class="text-center">Sebelum</th>
                                                <th class="text-center">Sesudah</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="d in detailImpactedFilteredRows" :key="d.Id_Detail">
                                                <td
                                                    v-if="detailCanManageExclusions"
                                                    class="text-center"
                                                    data-label="Pilih"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="d.Kode_Karyawan"
                                                        v-model="detailSelectedImpactKodes"
                                                        :disabled="loading.detail_exclusion_action"
                                                    />
                                                </td>
                                                <td data-label="Nama">
                                                    {{ d.Nama_Karyawan }}<br />
                                                    <small class="cb-muted">{{ d.Kode_Karyawan }}</small>
                                                </td>
                                                <td data-label="Divisi">{{ d.Divisi }}</td>
                                                <td class="text-center" data-label="Sebelum">
                                                    {{ formatInteger(d.Saldo_Sebelum) }}
                                                </td>
                                                <td class="text-center" data-label="Sesudah">
                                                    <span
                                                        :class="
                                                            parseFloat(d.Saldo_Sesudah) < 0 ? 'text-danger fw-bold' : ''
                                                        "
                                                        >{{ formatInteger(d.Saldo_Sesudah) }}</span
                                                    >
                                                </td>
                                                <td class="text-center" data-label="Status">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <span :class="['cb-pill', statusBadge(detailStatusValue(d))]">
                                                            {{ formatStatusLabel(detailStatusValue(d)) }}
                                                        </span>
                                                        <small
                                                            v-if="detailStatusReason(d)"
                                                            class="cb-muted text-center"
                                                        >
                                                            {{ detailStatusReason(d) }}
                                                        </small>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!detailImpactedFilteredRows.length">
                                                <td
                                                    :colspan="detailCanManageExclusions ? 6 : 5"
                                                    class="text-center py-3"
                                                >
                                                    Tidak ada data karyawan terdampak yang cocok dengan pencarian.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="cb-empty-inline">
                                    <i class="bi bi-table"></i>
                                    <p>Belum ada data pemotongan</p>
                                    <button
                                        type="button"
                                        class="cb-btn cb-btn-secondary cb-btn-inline"
                                        @click="reloadDetail"
                                    >
                                        <i class="bi bi-arrow-clockwise"></i>
                                        <span>Muat Ulang</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template #exclusion>
                            <div class="cb-tab-pane" :key="`exclusion-${detailTab}`">
                                <div v-if="detailData.exclusions?.length" class="cb-table-wrapper">
                                    <div class="cb-field px-2 pt-2">
                                        <input
                                            type="text"
                                            v-model.trim="detailExclusionSearch"
                                            class="form-control cb-input"
                                            placeholder="Cari nama, divisi, atau sub divisi..."
                                        />
                                    </div>
                                    <div v-if="detailCanManageExclusions" class="cb-detail-action-bar">
                                        <label class="cb-check-item mb-0">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    detailExclusionVisibleSelectedCount > 0 &&
                                                    detailExclusionVisibleSelectedCount ===
                                                        detailExclusionSelectableCount
                                                "
                                                @change="toggleSelectAllExclusions($event)"
                                            />
                                            <span>Pilih semua yang tidak konflik</span>
                                        </label>
                                        <button
                                            type="button"
                                            class="cb-btn cb-btn-primary cb-btn-inline"
                                            :disabled="
                                                !detailSelectedExclusionKodes.length || loading.detail_exclusion_action
                                            "
                                            @click="bulkIncludeExclusions"
                                        >
                                            <i class="bi bi-person-check"></i>
                                            <span>Sertakan Kembali ({{ detailSelectedExclusionKodes.length }})</span>
                                        </button>
                                    </div>
                                    <table class="table cb-table cb-table-sm">
                                        <thead>
                                            <tr>
                                                <th v-if="detailCanManageExclusions" class="text-center">Pilih</th>
                                                <th>Karyawan</th>
                                                <th>Alasan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="e in detailExclusionFilteredRows" :key="e.Id_Exclusion">
                                                <td
                                                    v-if="detailCanManageExclusions"
                                                    class="text-center"
                                                    data-label="Pilih"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="e.Kode_Karyawan"
                                                        v-model="detailSelectedExclusionKodes"
                                                        :disabled="
                                                            loading.detail_exclusion_action ||
                                                            isExclusionConflict(e.Kode_Karyawan)
                                                        "
                                                    />
                                                </td>
                                                <td data-label="Karyawan">
                                                    <span>{{ e.Nama_Karyawan || '-' }}</span>
                                                    <small class="cb-muted">({{ e.Kode_Karyawan }})</small>
                                                    <small class="cb-muted d-block" v-if="e.Divisi || e.Sub_Divisi">
                                                        {{ e.Divisi || '-' }}
                                                        <span v-if="e.Sub_Divisi">/ {{ e.Sub_Divisi }}</span>
                                                    </small>
                                                    <span
                                                        v-if="isExclusionConflict(e.Kode_Karyawan)"
                                                        class="cb-preview-conflict-pill ms-2"
                                                    >
                                                        Konflik - tidak bisa disertakan
                                                    </span>
                                                </td>
                                                <td data-label="Alasan">{{ e.Alasan || '-' }}</td>
                                            </tr>
                                            <tr v-if="!detailExclusionFilteredRows.length">
                                                <td
                                                    :colspan="detailCanManageExclusions ? 3 : 2"
                                                    class="text-center py-3"
                                                >
                                                    Tidak ada data pengecualian yang cocok dengan pencarian.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="cb-empty-inline">
                                    <i class="bi bi-person-x"></i>
                                    <p>Tidak ada pengecualian</p>
                                    <button
                                        type="button"
                                        class="cb-btn cb-btn-secondary cb-btn-inline"
                                        @click="reloadDetail"
                                    >
                                        <i class="bi bi-arrow-clockwise"></i>
                                        <span>Muat Ulang</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template #logs>
                            <div class="cb-tab-pane" :key="`logs-${detailTab}`">
                                <div v-if="detailData.task_logs?.length" class="cb-table-wrapper">
                                    <table class="table cb-table cb-table-sm">
                                        <thead>
                                            <tr>
                                                <th>Aksi</th>
                                                <th>Hasil</th>
                                                <th>Waktu</th>
                                                <th>Ringkasan</th>
                                                <th>Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="l in detailData.task_logs" :key="l.Id_Log">
                                                <td data-label="Aksi">
                                                    <strong>{{ l.admin_action_label || 'Proses Cuti Bersama' }}</strong>
                                                    <small class="cb-muted d-block mt-1">
                                                        {{ l.admin_action_detail || '-' }}
                                                    </small>
                                                </td>
                                                <td data-label="Hasil">
                                                    <span
                                                        :class="[
                                                            'cb-pill',
                                                            l.admin_badge_class || statusBadge(l.Status),
                                                        ]"
                                                        >{{ l.admin_status_label || formatStatusLabel(l.Status) }}</span
                                                    >
                                                </td>
                                                <td data-label="Waktu">
                                                    <div>Permintaan: {{ formatDateTime(l.Created_At) }}</div>
                                                    <div v-if="l.Scheduled_At">
                                                        Jadwal: {{ formatDateTime(l.Scheduled_At) }}
                                                    </div>
                                                    <div v-if="l.Started_At">
                                                        Mulai: {{ formatDateTime(l.Started_At) }}
                                                    </div>
                                                    <div v-if="l.Completed_At">
                                                        Selesai: {{ formatDateTime(l.Completed_At) }}
                                                    </div>
                                                    <small class="cb-muted d-block mt-1">
                                                        {{
                                                            l.Duration_Seconds
                                                                ? 'Durasi ' + l.Duration_Seconds + ' detik'
                                                                : 'Durasi belum tersedia'
                                                        }}
                                                    </small>
                                                </td>
                                                <td data-label="Ringkasan">
                                                    <div>{{ l.admin_summary || '-' }}</div>
                                                    <small class="cb-muted d-block mt-1">
                                                        {{ l.admin_progress_text || '-' }}
                                                    </small>
                                                </td>
                                                <td data-label="Catatan">
                                                    <span v-if="l.admin_note">{{ l.admin_note }}</span>
                                                    <span v-else class="cb-muted">Tidak ada catatan tambahan.</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="cb-empty-inline">
                                    <i class="bi bi-journal-text"></i>
                                    <p>Belum ada riwayat proses.</p>
                                    <button
                                        type="button"
                                        class="cb-btn cb-btn-secondary cb-btn-inline"
                                        @click="reloadDetail"
                                    >
                                        <i class="bi bi-arrow-clockwise"></i>
                                        <span>Muat Ulang</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </ApprovalTabs>
                </div>

                <div v-else class="cb-modal-body">
                    <div class="cb-empty-inline">
                        <i class="bi bi-inbox"></i>
                        <p>Detail belum tersedia.</p>
                        <button
                            type="button"
                            class="cb-btn cb-btn-secondary cb-btn-inline"
                            @click="showDetailModal = false"
                        >
                            <i class="bi bi-x-circle"></i>
                            <span>Tutup</span>
                        </button>
                    </div>
                </div>
            </template>
        </Modal>

        <Modal :show="showRollbackModal" title="Rollback Cuti Bersama" size="medium" @close="showRollbackModal = false">
            <template #body>
                <div class="cb-modal-body">
                    <div class="cb-warning-banner cb-warning-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div>
                            <strong>Aksi Rollback</strong>
                            <p>Rollback akan mengembalikan saldo cuti yang sudah dipotong.</p>
                        </div>
                    </div>
                    <div v-if="loading.rollback_options" class="cb-loading-inline">
                        <i class="bi bi-arrow-repeat spin"></i>
                        <p>Memuat target rollback dari transaksi eksekusi...</p>
                    </div>
                    <div v-else class="cb-field">
                        <label class="cb-label">Target Rollback</label>
                        <p class="cb-help mb-2">
                            Daftar ini hanya berasal dari target eksekusi transaksi cuti bersama ini.
                        </p>

                        <div class="cb-target-mode-card mb-3">
                            <label class="cb-check-row">
                                <input type="radio" value="all" v-model="rollbackForm.scope" />
                                <span>
                                    Semua Target Aktif
                                    <small>Rollback semua target yang masih berstatus aktif pada transaksi ini.</small>
                                </span>
                            </label>
                            <label class="cb-check-row">
                                <input type="radio" value="divisi" v-model="rollbackForm.scope" />
                                <span>
                                    Per Divisi
                                    <small>Pilih satu atau lebih divisi untuk rollback parsial.</small>
                                </span>
                            </label>
                            <label class="cb-check-row">
                                <input type="radio" value="sub_divisi" v-model="rollbackForm.scope" />
                                <span>
                                    Per Sub Divisi
                                    <small>Pilih sub divisi tertentu tanpa menyentuh target lain.</small>
                                </span>
                            </label>
                            <label class="cb-check-row">
                                <input type="radio" value="karyawan" v-model="rollbackForm.scope" />
                                <span>
                                    Per Karyawan
                                    <small>Pilih karyawan tertentu untuk rollback yang paling granular.</small>
                                </span>
                            </label>
                        </div>

                        <div v-if="rollbackForm.scope === 'all'" class="cb-empty-inline">
                            <i class="bi bi-people"></i>
                            <p>
                                Semua target aktif akan di-rollback.
                                <br />
                                <small class="cb-muted"
                                    >Total target aktif: {{ rollbackOptions.total_success || 0 }}</small
                                >
                            </p>
                        </div>

                        <div v-else-if="rollbackForm.scope === 'divisi'" class="cb-field">
                            <div v-if="rollbackDivisiItems.length" class="cb-checkbox-board">
                                <label v-for="item in rollbackDivisiItems" :key="item.value" class="cb-check-item">
                                    <input
                                        type="checkbox"
                                        :value="item.value"
                                        v-model="rollbackForm.divisi"
                                        :disabled="loading.rollback"
                                    />
                                    <span>{{ item.label }}</span>
                                </label>
                            </div>
                            <div v-else class="cb-empty-inline">
                                <i class="bi bi-diagram-3"></i>
                                <p>Tidak ada opsi divisi yang bisa di-rollback.</p>
                            </div>
                        </div>

                        <div v-else-if="rollbackForm.scope === 'sub_divisi'" class="cb-field">
                            <div v-if="rollbackSubDivisiItems.length" class="cb-checkbox-board">
                                <label v-for="item in rollbackSubDivisiItems" :key="item.value" class="cb-check-item">
                                    <input
                                        type="checkbox"
                                        :value="item.value"
                                        v-model="rollbackForm.sub_divisi"
                                        :disabled="loading.rollback"
                                    />
                                    <span>{{ item.label }}</span>
                                </label>
                            </div>
                            <div v-else class="cb-empty-inline">
                                <i class="bi bi-diagram-2"></i>
                                <p>Tidak ada opsi sub divisi yang bisa di-rollback.</p>
                            </div>
                        </div>

                        <div
                            v-if="rollbackForm.scope === 'karyawan' && rollbackKaryawanItems.length"
                            class="cb-field mb-2"
                        >
                            <input
                                type="text"
                                v-model.trim="rollbackSearch"
                                class="form-control cb-input"
                                placeholder="Cari kode, nama, divisi, atau sub divisi..."
                            />
                        </div>

                        <div
                            v-if="rollbackForm.scope === 'karyawan' && rollbackCurrentTotal > 0"
                            class="cb-detail-action-bar mb-2"
                        >
                            <label class="cb-check-item mb-0">
                                <input
                                    type="checkbox"
                                    :checked="
                                        rollbackVisibleSelectedCount > 0 &&
                                        rollbackVisibleSelectedCount === rollbackCurrentTotal
                                    "
                                    @change="toggleRollbackSelectAll($event)"
                                />
                                <span>Pilih semua</span>
                            </label>
                            <small class="cb-muted">Dipilih: {{ rollbackSelectedCount }}</small>
                        </div>

                        <div
                            v-if="rollbackForm.scope === 'karyawan' && rollbackKaryawanItems.length"
                            class="cb-table-wrapper"
                        >
                            <table class="table cb-table cb-table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center">Pilih</th>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Divisi</th>
                                        <th>Sub Divisi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in rollbackFilteredItems" :key="item.value">
                                        <td class="text-center" data-label="Pilih">
                                            <input
                                                type="checkbox"
                                                :value="item.value"
                                                v-model="rollbackForm.kode_karyawan"
                                                :disabled="loading.rollback"
                                            />
                                        </td>
                                        <td data-label="Kode">{{ item.value }}</td>
                                        <td data-label="Nama">{{ item.nama_karyawan || '-' }}</td>
                                        <td data-label="Divisi">{{ item.divisi || '-' }}</td>
                                        <td data-label="Sub Divisi">{{ item.sub_divisi || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else-if="rollbackForm.scope === 'karyawan' && rollbackSearch" class="cb-empty-inline">
                            <i class="bi bi-search"></i>
                            <p>
                                Tidak ada hasil untuk pencarian
                                <strong>{{ rollbackSearch }}</strong>
                            </p>
                        </div>

                        <div
                            v-else-if="
                                rollbackForm.scope === 'karyawan' &&
                                !loading.rollback_options &&
                                !rollbackKaryawanItems.length
                            "
                            class="cb-empty-inline"
                        >
                            <i class="bi bi-inbox"></i>
                            <p>
                                Tidak ada data target rollback.
                                <br />
                                <small class="cb-muted"
                                    >Pastikan transaksi ini punya detail eksekusi berstatus SUCCESS.</small
                                >
                            </p>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="cb-modal-footer">
                    <button type="button" class="cb-btn cb-btn-secondary" @click="showRollbackModal = false">
                        Batal
                    </button>
                    <button
                        type="button"
                        class="cb-btn cb-btn-warning"
                        @click="doRollback"
                        :disabled="loading.rollback || loading.rollback_options || !canSubmitRollback"
                    >
                        <i v-if="loading.rollback" class="bi bi-arrow-repeat spin"></i>
                        <span>Rollback</span>
                    </button>
                </div>
            </template>
        </Modal>

        <Modal
            v-if="showConfirmActionModal"
            :show="showConfirmActionModal"
            :title="confirmAction.title"
            size="medium"
            @close="closeConfirmActionModal"
        >
            <template #body>
                <div class="cb-confirmation-content text-center py-4">
                    <i :class="['cb-confirmation-icon', confirmAction.iconClass]"></i>
                    <p class="cb-confirmation-text mb-0">{{ confirmAction.body }}</p>
                </div>
            </template>

            <template #footer>
                <div class="cb-modal-footer">
                    <button
                        type="button"
                        class="cb-btn cb-btn-secondary"
                        @click="closeConfirmActionModal"
                        :disabled="confirmAction.loading"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :class="['cb-btn', confirmAction.buttonClass]"
                        @click="confirmActionHandler"
                        :disabled="confirmAction.loading"
                    >
                        <i v-if="confirmAction.loading" class="bi bi-arrow-repeat spin"></i>
                        <i v-else :class="confirmAction.buttonIcon"></i>
                        <span>{{ confirmAction.confirmText }}</span>
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import QuickStat from '@/components/legacy/quickStat.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import axios from 'axios';
import { debounce } from 'lodash';
import { ElMessage } from 'element-plus';

export default {
    name: 'CutiBersamaAdvanced',
    components: { HeaderKpi, Modal, QuickStat, ApprovalTabs, ModernFilter },
    data() {
        return {
            dayNight: new Date().getHours() >= 6 && new Date().getHours() < 18 ? 'Siang' : 'Malam',
            currentDate: new Date(),
            listFilters: {
                search: '',
                tanggal: [],
                status: '',
            },
            listPagination: {
                current_page: 1,
                per_page: 10,
                total: 0,
                total_pages: 1,
            },
            loading: {
                list: false,
                preview: false,
                submit: false,
                execute: false,
                execute_prep: false,
                detail: false,
                detail_exclusion_action: false,
                rollback_options: false,
                rollback: false,
                wizard_init: false,
            },
            cbList: [],
            showWizard: false,
            wizardStep: 0,
            wizardSteps: ['Info Dasar', 'Target', 'Pengecualian', 'Preview'],
            editingId: null,
            useUnitFilter: false,
            useSpecificTarget: false,
            form: this.getEmptyForm(),
            karyawanSearch: '',
            searchResults: [],
            selectedSpecificKaryawan: [],
            exclusionSearch: '',
            exclusionResults: [],
            filterOptions: { cabang: [], divisi: [], sub_divisi: [], jenis_cuti: [] },
            targetExpectation: {
                loading: false,
                total: null,
                error: '',
            },
            targetExpectationReqId: 0,
            previewData: null,
            previewConfirmed: false,
            previewConflictSelectedKodes: [],
            showExecuteModal: false,
            executeTarget: null,
            executeMode: 'now',
            scheduledAt: '',
            submitExecutionMode: 'draft',
            submitScheduledAt: '',
            showDetailModal: false,
            detailData: null,
            detailTab: 'karyawan',
            detailSelectedImpactKodes: [],
            detailSelectedExclusionKodes: [],
            detailImpactSearch: '',
            detailExclusionSearch: '',
            showRollbackModal: false,
            rollbackTarget: null,
            rollbackTargetId: null,
            rollbackForm: { scope: 'karyawan', kode_karyawan: [], divisi: [], sub_divisi: [] },
            rollbackOptions: { total_success: 0, karyawan: [], divisi: [], sub_divisi: [] },
            rollbackSearch: '',
            showConfirmActionModal: false,
            confirmAction: {
                type: '',
                title: '',
                body: '',
                confirmText: 'Konfirmasi',
                buttonClass: 'cb-btn-primary',
                buttonIcon: 'bi bi-check-lg',
                iconClass: 'bi bi-exclamation-triangle text-warning',
                payload: null,
                loading: false,
            },
        };
    },
    computed: {
        wizardTitle() {
            return this.editingId ? 'Edit Cuti Bersama' : 'Buat Cuti Bersama';
        },
        computedDays() {
            return this.form.selected_dates?.length || 0;
        },
        canProceedWizard() {
            if (this.wizardStep === 0) return this.form.nama && this.form.selected_dates?.length > 0;
            if (this.wizardStep === 1 && this.useSpecificTarget) return this.form.filter_kode_karyawan.length > 0;
            return true;
        },
        wizardValidationHint() {
            if (this.wizardStep === 0 && !this.canProceedWizard) {
                return 'Isi nama lalu pilih minimal 1 tanggal cuti bersama.';
            }
            if (this.wizardStep === 1 && this.useSpecificTarget && !this.form.filter_kode_karyawan.length) {
                return 'Tambahkan minimal 1 karyawan jika memilih mode target spesifik.';
            }
            if (this.wizardStep === 3 && this.previewImpactedCount <= 0) {
                return 'Tidak ada karyawan terdampak. Periksa target/filter atau kurangi pengecualian sebelum simpan.';
            }
            if (this.wizardStep === 3 && this.previewOverlapSummary.has_conflict) {
                return 'Masih ada konflik overlap. Kecualikan semua karyawan konflik terlebih dahulu sebelum simpan.';
            }
            if (this.wizardStep === 3 && this.submitExecutionMode === 'scheduled' && !this.submitScheduledAt) {
                return 'Pilih tanggal dan jam jika ingin menjadwalkan eksekusi dari form.';
            }
            if (this.wizardStep === 3 && !this.previewConfirmed) {
                return 'Centang konfirmasi sebelum simpan agar data tidak keliru.';
            }
            return '';
        },
        canSubmitWizard() {
            if (this.previewImpactedCount <= 0) return false;
            if (!this.previewConfirmed) return false;
            if (this.previewOverlapSummary.has_conflict) return false;
            if (this.submitExecutionMode === 'scheduled' && !this.submitScheduledAt) return false;
            return true;
        },
        previewImpactedCount() {
            return Number(this.previewData?.total_terdampak || 0);
        },
        previewOverlapSummary() {
            const summary = this.previewData?.overlap_summary;
            if (!summary || typeof summary !== 'object') {
                return {
                    has_conflict: false,
                    has_blocking_conflict: false,
                    total_conflicts: 0,
                    blocking_conflicts: 0,
                    conflicts: [],
                };
            }

            return {
                has_conflict: Boolean(summary.has_conflict),
                has_blocking_conflict: Boolean(summary.has_blocking_conflict),
                total_conflicts: Number(summary.total_conflicts || 0),
                blocking_conflicts: Number(summary.blocking_conflicts || 0),
                conflicts: Array.isArray(summary.conflicts) ? summary.conflicts : [],
            };
        },
        hasBlockingPreviewOverlap() {
            return this.previewOverlapSummary.has_blocking_conflict;
        },
        previewConflictKodeSet() {
            const set = new Set();
            for (const conflict of this.previewOverlapSummary.conflicts || []) {
                for (const kode of conflict.overlap_karyawan_kodes || []) {
                    if (kode) set.add(kode);
                }
            }
            return set;
        },
        previewConflictDatesByKode() {
            const map = {};

            for (const conflict of this.previewOverlapSummary.conflicts || []) {
                const dates = Array.isArray(conflict?.overlap_dates) ? conflict.overlap_dates : [];
                const kodes = Array.isArray(conflict?.overlap_karyawan_kodes) ? conflict.overlap_karyawan_kodes : [];

                for (const kode of kodes) {
                    if (!kode) continue;
                    if (!Array.isArray(map[kode])) {
                        map[kode] = [];
                    }
                    map[kode].push(...dates);
                }
            }

            Object.keys(map).forEach((kode) => {
                map[kode] = [...new Set((map[kode] || []).filter(Boolean))].sort();
            });

            return map;
        },
        previewSampleDetailRows() {
            const rows = Array.isArray(this.previewData?.sample_detail) ? [...this.previewData.sample_detail] : [];
            rows.sort((a, b) => {
                const aConflict = this.previewConflictKodeSet.has(a?.kode_karyawan) ? 1 : 0;
                const bConflict = this.previewConflictKodeSet.has(b?.kode_karyawan) ? 1 : 0;
                if (aConflict !== bConflict) return bConflict - aConflict;
                return String(a?.nama || '').localeCompare(String(b?.nama || ''), 'id');
            });
            return rows;
        },
        previewConflictSelectableKodes() {
            return this.previewSampleDetailRows
                .map((row) => row?.kode_karyawan)
                .filter((kode) => kode && this.previewConflictKodeSet.has(kode) && !this.exclusionKodeSet.has(kode));
        },
        previewConflictSelectedCount() {
            return this.previewConflictSelectedKodes.length;
        },
        previewConflictAllSelected() {
            const total = this.previewConflictSelectableKodes.length;
            return total > 0 && this.previewConflictSelectedCount === total;
        },
        exclusionKodeSet() {
            return new Set((this.form.exclusions || []).map((item) => item.kode_karyawan));
        },
        submitExecutionLabel() {
            if (this.submitExecutionMode === 'scheduled') return 'Terjadwal';
            if (this.submitExecutionMode === 'now') return 'Execute Segera';
            return 'Draft';
        },
        submitButtonLabel() {
            if (this.submitExecutionMode === 'scheduled') {
                return this.editingId ? 'Simpan & Jadwalkan' : 'Buat & Jadwalkan';
            }
            if (this.submitExecutionMode === 'now') {
                return this.editingId ? 'Simpan & Execute' : 'Buat & Execute';
            }
            return this.editingId ? 'Simpan' : 'Simpan Cuti Bersama';
        },
        minScheduleAt() {
            return this.toDateTimeLocalValue(new Date());
        },
        activeStepMeta() {
            return (
                [
                    {
                        title: 'Info Dasar',
                        desc: 'Lengkapi informasi utama cuti bersama agar data mudah dipahami semua pihak.',
                        icon: 'bi bi-card-checklist',
                    },
                    {
                        title: 'Target Karyawan',
                        desc: 'Tentukan siapa saja yang akan terdampak, bisa semua, per filter, atau spesifik.',
                        icon: 'bi bi-people',
                    },
                    {
                        title: 'Pengecualian',
                        desc: 'Tambahkan karyawan yang dikecualikan agar eksekusi tidak salah sasaran.',
                        icon: 'bi bi-person-x',
                    },
                    {
                        title: 'Preview & Konfirmasi',
                        desc: 'Review dampak saldo cuti lalu konfirmasi sebelum data disimpan.',
                        icon: 'bi bi-shield-check',
                    },
                ][this.wizardStep] || {}
            );
        },
        targetModeLabel() {
            if (this.useUnitFilter && this.useSpecificTarget) return 'Filter Unit + Spesifik Karyawan';
            if (this.useUnitFilter) return 'Filter Unit';
            if (this.useSpecificTarget) return 'Spesifik Karyawan';
            return 'Semua Karyawan';
        },
        sortedSelectedDates() {
            return [...(this.form.selected_dates || [])].sort();
        },
        filteredSubDivisi() {
            if (!this.form.filter_divisi.length) return this.filterOptions.sub_divisi;
            return this.filterOptions.sub_divisi.filter((sd) => this.form.filter_divisi.includes(sd.ID_Divisi));
        },
        searchResultsUnique() {
            return this.uniqueKaryawanByKode(this.searchResults);
        },
        quickStatItems() {
            const total = this.cbList.length;
            const executed = this.cbList.filter((c) => c.Flag_Executed === 'Y').length;
            const totalImpacted = this.cbList.reduce((s, c) => s + (c.Total_Karyawan_Terdampak || 0), 0);

            return [
                {
                    title: 'Total CB',
                    value: total,
                    icon: 'bi bi-calendar-event',
                    bgIcon: 'bg-primary',
                    size: 'col-4',
                },
                {
                    title: 'Executed',
                    value: executed,
                    icon: 'bi bi-check-circle',
                    bgIcon: 'bg-success',
                    size: 'col-4',
                },
                {
                    title: 'Draft',
                    value: total - executed,
                    icon: 'bi bi-pencil',
                    bgIcon: 'bg-warning',
                    size: 'col-4',
                },
                // {
                //     title: 'Terdampak',
                //     value: totalImpacted,
                //     icon: 'bi bi-people',
                //     bgIcon: 'bg-info',
                //     size: 'col-6 col-md-3',
                // },
            ];
        },
        listFilterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari nama cuti bersama...',
                showResultsCount: false,
                showActiveFilters: true,
                showActions: false,
                showReset: true,
                showClearAll: true,
                filters: [
                    {
                        key: 'tanggal',
                        label: 'Rentang Tanggal Berlangsung',
                        type: 'daterange',
                        col: 6,
                        placeholder: 'Pilih rentang tanggal cuti bersama',
                        dateType: 'daterange',
                        format: 'DD/MM/YYYY',
                        valueFormat: 'YYYY-MM-DD',
                    },
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        col: 6,
                        placeholder: 'Semua Status',
                        options: [
                            { label: 'Draft', value: 'draft' },
                            { label: 'Queued', value: 'queued' },
                            { label: 'Scheduled', value: 'scheduled' },
                            { label: 'Cancelled', value: 'cancelled' },
                            { label: 'Processing', value: 'processing' },
                            { label: 'Executed', value: 'executed' },
                            { label: 'Completed', value: 'completed' },
                            { label: 'Failed', value: 'failed' },
                            { label: 'Rolled Back', value: 'rolled_back' },
                        ],
                    },
                ],
            };
        },
        hasActiveListFilters() {
            const searchActive = Boolean(String(this.listFilters?.search || '').trim());
            const tanggal = Array.isArray(this.listFilters?.tanggal) ? this.listFilters.tanggal : [];
            const dateActive = tanggal.length === 2 && Boolean(tanggal[0]) && Boolean(tanggal[1]);
            const statusActive = Boolean(String(this.listFilters?.status || '').trim());

            return searchActive || dateActive || statusActive;
        },
        detailTabs() {
            return [
                {
                    id: 'karyawan',
                    label: 'Karyawan Terdampak',
                    icon: 'bi bi-people',
                    count: this.detailData?.detail_pagination?.total || this.detailData?.details?.length || 0,
                },
                {
                    id: 'exclusion',
                    label: 'Pengecualian',
                    icon: 'bi bi-person-x',
                    count: this.detailData?.exclusions?.length || 0,
                },
                {
                    id: 'logs',
                    label: 'Riwayat Proses',
                    icon: 'bi bi-journal-text',
                    count: this.detailData?.task_logs?.length || 0,
                },
            ];
        },
        detailCanManageExclusions() {
            return Boolean(this.detailData?.can_manage_exclusions);
        },
        detailExclusionConflictMap() {
            const source = this.detailData?.exclusion_conflicts_by_kode;
            return source && typeof source === 'object' ? source : {};
        },
        detailImpactedFilteredRows() {
            const rows = Array.isArray(this.detailData?.details) ? this.detailData.details : [];
            const keyword = String(this.detailImpactSearch || '')
                .trim()
                .toLowerCase();

            if (!keyword) return rows;

            return rows.filter((row) => {
                const haystacks = [
                    row?.Nama_Karyawan,
                    row?.Kode_Karyawan,
                    row?.Divisi,
                    row?.Sub_Divisi,
                    row?.nama_sub_divisi,
                ]
                    .filter(Boolean)
                    .map((value) => String(value).toLowerCase());

                return haystacks.some((value) => value.includes(keyword));
            });
        },
        detailImpactVisibleSelectedCount() {
            const visibleSet = new Set(this.detailImpactedFilteredRows.map((row) => row.Kode_Karyawan).filter(Boolean));
            return this.detailSelectedImpactKodes.filter((kode) => visibleSet.has(kode)).length;
        },
        detailExclusionFilteredRows() {
            const rows = Array.isArray(this.detailData?.exclusions) ? this.detailData.exclusions : [];
            const keyword = String(this.detailExclusionSearch || '')
                .trim()
                .toLowerCase();

            if (!keyword) return rows;

            return rows.filter((row) => {
                const haystacks = [row?.Nama_Karyawan, row?.Kode_Karyawan, row?.Divisi, row?.Sub_Divisi]
                    .filter(Boolean)
                    .map((value) => String(value).toLowerCase());

                return haystacks.some((value) => value.includes(keyword));
            });
        },
        detailExclusionVisibleSelectedCount() {
            const visibleSet = new Set(
                this.detailExclusionFilteredRows.map((row) => row.Kode_Karyawan).filter(Boolean),
            );
            return this.detailSelectedExclusionKodes.filter((kode) => visibleSet.has(kode)).length;
        },
        draftCount() {
            return this.cbList.length - this.executedCount;
        },
        impactedCount() {
            return this.cbList.reduce((s, c) => s + (c.Total_Karyawan_Terdampak || 0), 0);
        },
        detailExecutionDates() {
            return this.resolveExecutionDatesForDisplay(this.detailData?.cuti_bersama);
        },
        executeExecutionDates() {
            return this.resolveExecutionDatesForDisplay(this.executeTarget);
        },
        rollbackKaryawanItems() {
            const source = Array.isArray(this.rollbackOptions.karyawan)
                ? this.rollbackOptions.karyawan
                : this.rollbackOptions.karyawan && typeof this.rollbackOptions.karyawan === 'object'
                  ? Object.values(this.rollbackOptions.karyawan)
                  : [];

            const items = source
                .map((k) => this.normalizeRollbackKaryawanItem(k))
                .map((item) => {
                    if (!item) return null;
                    return {
                        value: item.kode_karyawan,
                        nama_karyawan: item.nama_karyawan || item.kode_karyawan,
                        divisi: item.divisi || '',
                        sub_divisi: item.sub_divisi || '',
                    };
                })
                .filter((item) => item && item.value);

            // console.log(items);
            return items;
        },
        rollbackDivisiItems() {
            const source = Array.isArray(this.rollbackOptions.divisi)
                ? this.rollbackOptions.divisi
                : this.rollbackOptions.divisi && typeof this.rollbackOptions.divisi === 'object'
                  ? Object.values(this.rollbackOptions.divisi)
                  : [];

            return source
                .map((item) => String(item || '').trim())
                .filter(Boolean)
                .map((item) => ({ value: item, label: item }));
        },
        rollbackSubDivisiItems() {
            const source = Array.isArray(this.rollbackOptions.sub_divisi)
                ? this.rollbackOptions.sub_divisi
                : this.rollbackOptions.sub_divisi && typeof this.rollbackOptions.sub_divisi === 'object'
                  ? Object.values(this.rollbackOptions.sub_divisi)
                  : [];

            return source
                .map((item) => String(item || '').trim())
                .filter(Boolean)
                .map((item) => ({ value: item, label: item }));
        },
        rollbackFilteredItems() {
            const keyword = String(this.rollbackSearch || '')
                .trim()
                .toLowerCase();
            if (!keyword) return this.rollbackKaryawanItems;

            return this.rollbackKaryawanItems.filter((item) => {
                const haystacks = [item.value, item.nama_karyawan, item.divisi, item.sub_divisi]
                    .filter(Boolean)
                    .map((value) => String(value).toLowerCase());

                return haystacks.some((value) => value.includes(keyword));
            });
        },
        rollbackCurrentTotal() {
            if (this.rollbackForm.scope === 'divisi') return this.rollbackDivisiItems.length;
            if (this.rollbackForm.scope === 'sub_divisi') return this.rollbackSubDivisiItems.length;
            if (this.rollbackForm.scope === 'all') return Number(this.rollbackOptions.total_success || 0);
            return this.rollbackFilteredItems.length;
        },
        rollbackSelectedCount() {
            if (this.rollbackForm.scope === 'divisi') return this.rollbackForm.divisi.length;
            if (this.rollbackForm.scope === 'sub_divisi') return this.rollbackForm.sub_divisi.length;
            if (this.rollbackForm.scope === 'all') return Number(this.rollbackOptions.total_success || 0);
            return this.rollbackForm.kode_karyawan.length;
        },
        rollbackVisibleSelectedCount() {
            if (this.rollbackForm.scope !== 'karyawan') return this.rollbackSelectedCount;
            const visibleKodeSet = new Set(this.rollbackFilteredItems.map((item) => item.value));
            return this.rollbackForm.kode_karyawan.filter((kode) => visibleKodeSet.has(kode)).length;
        },
        canSubmitRollback() {
            if (this.rollbackForm.scope === 'all') return Number(this.rollbackOptions.total_success || 0) > 0;
            return this.rollbackSelectedCount > 0;
        },
        listPageStart() {
            if (!this.listPagination.total) return 0;
            return (this.listPagination.current_page - 1) * this.listPagination.per_page + 1;
        },
        listPageEnd() {
            if (!this.listPagination.total) return 0;
            return Math.min(this.listPagination.current_page * this.listPagination.per_page, this.listPagination.total);
        },
    },
    methods: {
        getEmptyForm() {
            return {
                nama: '',
                tanggal_mulai: '',
                tanggal_selesai: '',
                selected_dates: [],
                potong_ke_tipe_cuti: 'Tahunan',
                keterangan: '',
                filter_cabang: [],
                filter_divisi: [],
                filter_sub_divisi: [],
                filter_kode_karyawan: [],
                exclusions: [],
            };
        },
        formatDate(d) {
            return d
                ? new Date(d).toLocaleDateString('id-ID', {
                      day: '2-digit',
                      month: 'short',
                      year: 'numeric',
                  })
                : '-';
        },
        formatDateTime(d) {
            return d ? new Date(d).toLocaleString('id-ID') : '-';
        },
        formatDateString(d) {
            return new Date(d).toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
        },
        formatPreviewConflictDates(kodeKaryawan) {
            const dates = this.previewConflictDatesByKode?.[kodeKaryawan] || [];
            if (!dates.length) return '-';

            const shownDates = dates.slice(0, 3).map((date) => this.formatDate(date));
            const suffix = dates.length > 3 ? ` (+${dates.length - 3} lainnya)` : '';

            return `${shownDates.join(', ')}${suffix}`;
        },
        formatInteger(value) {
            const numericValue = Number(value);
            return Number.isFinite(numericValue) ? Math.trunc(numericValue) : 0;
        },
        toDateTimeLocalValue(value) {
            const date = value ? new Date(value) : new Date();
            if (Number.isNaN(date.getTime())) return '';

            const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
            return localDate.toISOString().slice(0, 16);
        },
        statusBadge(s) {
            return (
                {
                    PREVIEW: 'cb-pill-info',
                    SUCCESS: 'cb-pill-success',
                    FAILED: 'cb-pill-danger',
                    ROLLED_BACK: 'cb-pill-warning',
                    PARTIAL_ROLLBACK: 'cb-pill-warning',
                    ROLLBACK_FAILED: 'cb-pill-danger',
                    ROLLBACK_QUEUED: 'cb-pill-info',
                    ROLLBACK_PROCESSING: 'cb-pill-primary',
                    EXCLUDED: 'cb-pill-muted',
                    DISPATCHED: 'cb-pill-info',
                    CANCELLED: 'cb-pill-warning',
                    PROCESSING: 'cb-pill-primary',
                    COMPLETED: 'cb-pill-success',
                }[s] || 'cb-pill-muted'
            );
        },
        formatStatusLabel(s) {
            return (
                {
                    PREVIEW: 'Preview Target',
                    SUCCESS: 'Success',
                    FAILED: 'Failed',
                    ROLLED_BACK: 'Rolled Back',
                    PARTIAL_ROLLBACK: 'Partial Rollback',
                    ROLLBACK_FAILED: 'Rollback Failed',
                    ROLLBACK_QUEUED: 'Rollback Queued',
                    ROLLBACK_PROCESSING: 'Rollback Processing',
                    EXCLUDED: 'Excluded',
                    DISPATCHED: 'Dispatched',
                    CANCELLED: 'Cancelled',
                    PROCESSING: 'Processing',
                    COMPLETED: 'Completed',
                }[s] ||
                s ||
                '-'
            );
        },
        detailStatusValue(detail) {
            return detail?.Runtime_Status || detail?.Status || '-';
        },
        detailStatusReason(detail) {
            return detail?.Runtime_Status_Reason || '';
        },
        executionBadgeClass(item) {
            const status = item?.execution_snapshot?.status;
            if (status === 'EXECUTED' || status === 'COMPLETED') return 'cb-pill-success';
            if (status === 'PROCESSING') return 'cb-pill-primary';
            if (status === 'ROLLBACK_PROCESSING') return 'cb-pill-primary';
            if (status === 'CANCELLED') return 'cb-pill-warning';
            if (status === 'FAILED') return 'cb-pill-danger';
            if (status === 'ROLLBACK_FAILED') return 'cb-pill-danger';
            if (status === 'ROLLED_BACK') return 'cb-pill-warning';
            if (status === 'PARTIAL_ROLLBACK') return 'cb-pill-warning';
            if (status === 'ROLLBACK_QUEUED') return 'cb-pill-info';
            if (status === 'SCHEDULED' || status === 'QUEUED') return 'cb-pill-info';
            return 'cb-pill-muted';
        },
        executionBadgeLabel(item) {
            return item?.execution_snapshot?.label || 'Draft';
        },
        executionTimeValue(item) {
            return item?.execution_snapshot?.time || item?.Executed_At || item?.Scheduled_At || null;
        },
        executionTimeLabel(item) {
            return item?.execution_snapshot?.time_label || 'Waktu';
        },
        executionStatusReason(item) {
            return item?.execution_snapshot?.reason || '';
        },
        canExecuteItem(item) {
            return Boolean(item?.execution_snapshot?.can_execute);
        },
        canModifyItem(item) {
            return Boolean(item?.execution_snapshot?.can_modify);
        },
        canCancelItem(item) {
            const status = String(item?.execution_snapshot?.status || '')
                .trim()
                .toUpperCase();

            return status === 'SCHEDULED' || status === 'QUEUED';
        },
        canRollbackItem(item) {
            const status = String(item?.execution_snapshot?.status || '')
                .trim()
                .toUpperCase();
            return (
                status === 'EXECUTED' ||
                status === 'COMPLETED' ||
                status === 'PARTIAL_ROLLBACK' ||
                status === 'ROLLBACK_FAILED'
            );
        },
        executeButtonTitle(item) {
            if (this.canExecuteItem(item)) return 'Eksekusi cuti bersama';
            return this.executionStatusReason(item) || 'Tidak bisa dieksekusi';
        },
        cancelButtonTitle(item) {
            if (this.canCancelItem(item)) return 'Batalkan jadwal execute';
            return this.executionStatusReason(item) || 'Tidak bisa dibatalkan';
        },
        setSubmitExecutionMode(mode) {
            this.submitExecutionMode = mode;
            if (mode !== 'scheduled') {
                this.submitScheduledAt = '';
            }
        },
        resolveCutiBersamaId(source) {
            if (!source || typeof source !== 'object') return null;

            return (
                source.Id_Cuti_Bersama ||
                source.id_cuti_bersama ||
                source.id ||
                source.Id ||
                source?.cuti_bersama?.Id_Cuti_Bersama ||
                null
            );
        },
        validateScheduledAt(value, label = 'eksekusi') {
            if (!value) {
                ElMessage.warning(`Pilih tanggal dan jam ${label} terlebih dahulu.`);
                return false;
            }

            const scheduledDate = new Date(value);
            if (Number.isNaN(scheduledDate.getTime()) || scheduledDate.getTime() <= Date.now()) {
                ElMessage.warning(`Tanggal dan jam ${label} harus lebih besar dari waktu sekarang.`);
                return false;
            }

            return true;
        },
        resolveApiResult(payload) {
            if (!payload || typeof payload !== 'object') return null;
            return payload.data ?? payload.result ?? null;
        },
        handleListFilters(updated) {
            this.listFilters = {
                ...this.listFilters,
                ...(updated || {}),
            };
            this.listPagination.current_page = 1;
            this.loadList();
        },
        resetListFilters() {
            this.listFilters = {
                search: '',
                tanggal: [],
                status: '',
            };
            this.listPagination.current_page = 1;
            this.loadList();
        },
        changeListPage(page) {
            const normalizedPage = Number(page || 1);
            if (!Number.isFinite(normalizedPage)) return;
            if (normalizedPage < 1 || normalizedPage > this.listPagination.total_pages) return;
            this.listPagination.current_page = normalizedPage;
            this.loadList();
        },
        async loadList() {
            this.loading.list = true;
            try {
                const tanggalRange = Array.isArray(this.listFilters?.tanggal) ? this.listFilters.tanggal : [];
                const search = String(this.listFilters?.search || '').trim();
                const status = String(this.listFilters?.status || '').trim();
                const { data } = await axios.get('/api/v1/cuti-bersama-adv/list', {
                    params: {
                        page: this.listPagination.current_page,
                        search: search || undefined,
                        status: status || undefined,
                        start_date: tanggalRange.length === 2 ? tanggalRange[0] : undefined,
                        end_date: tanggalRange.length === 2 ? tanggalRange[1] : undefined,
                    },
                });
                const result = this.resolveApiResult(data) || {};
                this.cbList = result.list || [];
                this.listPagination = {
                    ...this.listPagination,
                    ...(result.pagination || {}),
                };
            } catch (e) {
                ElMessage.error('Gagal memuat data');
            }
            this.loading.list = false;
        },
        async loadFilterOptions() {
            try {
                const { data } = await axios.get('/api/v1/cuti-bersama-adv/filter-options');
                const source = this.resolveApiResult(data) || {};
                this.filterOptions = {
                    cabang: Array.isArray(source.cabang) ? source.cabang : [],
                    divisi: Array.isArray(source.divisi) ? source.divisi : [],
                    sub_divisi: Array.isArray(source.sub_divisi) ? source.sub_divisi : [],
                    jenis_cuti: Array.isArray(source.jenis_cuti) ? source.jenis_cuti : [],
                };
            } catch (e) {
                console.error(e);
                this.filterOptions = {
                    ...this.filterOptions,
                    jenis_cuti: this.filterOptions.jenis_cuti?.length
                        ? this.filterOptions.jenis_cuti
                        : [{ Id_Jenis_Cuti: 'fallback', Nama_Jenis_Cuti: 'Tahunan' }],
                };
            }
        },
        async ensureFilterOptionsLoaded(forceReload = false) {
            const hasTargetOptions =
                (this.filterOptions.cabang?.length || 0) > 0 ||
                (this.filterOptions.divisi?.length || 0) > 0 ||
                (this.filterOptions.sub_divisi?.length || 0) > 0;
            const hasJenisCuti = (this.filterOptions.jenis_cuti?.length || 0) > 0;

            if (forceReload || !hasTargetOptions || !hasJenisCuti) {
                await this.loadFilterOptions();
            }

            if (!this.filterOptions.jenis_cuti?.length) {
                this.filterOptions.jenis_cuti = [{ Id_Jenis_Cuti: 'fallback', Nama_Jenis_Cuti: 'Tahunan' }];
            }
        },
        async openWizard(item = null) {
            this.wizardStep = 0;
            this.useUnitFilter = false;
            this.useSpecificTarget = false;
            this.previewData = null;
            this.previewConfirmed = false;
            this.previewConflictSelectedKodes = [];
            this.targetExpectation = { loading: false, total: null, error: '' };
            this.selectedSpecificKaryawan = [];
            this.submitExecutionMode = 'draft';
            this.submitScheduledAt = '';
            this.showWizard = true;
            this.loading.wizard_init = true;

            try {
                await this.ensureFilterOptionsLoaded();

                if (item) {
                    const id = item.Id_Cuti_Bersama;
                    this.editingId = id;

                    let source = { ...item };
                    let preloadedExclusions = [];

                    try {
                        const { data } = await axios.get(`/api/v1/cuti-bersama-adv/detail/${id}`);
                        const detail = data?.data || {};
                        const cb = detail.cuti_bersama || {};

                        source = {
                            ...source,
                            ...cb,
                            selected_dates_parsed: cb.selected_dates_parsed ?? source.selected_dates_parsed,
                            filter_cabang_parsed: this.normalizeArrayField(
                                cb.filter_cabang_parsed ?? cb.Filter_Cabang ?? source.filter_cabang_parsed,
                            ),
                            filter_divisi_parsed: this.normalizeArrayField(
                                cb.filter_divisi_parsed ?? cb.Filter_Divisi ?? source.filter_divisi_parsed,
                            ),
                            filter_sub_divisi_parsed: this.normalizeArrayField(
                                cb.filter_sub_divisi_parsed ?? cb.Filter_Sub_Divisi ?? source.filter_sub_divisi_parsed,
                            ),
                            filter_kode_karyawan_parsed: this.normalizeArrayField(
                                cb.filter_kode_karyawan_parsed ??
                                    cb.Filter_Kode_Karyawan ??
                                    source.filter_kode_karyawan_parsed,
                            ),
                        };

                        preloadedExclusions = (detail.exclusions || []).map((exc) => ({
                            kode_karyawan: exc.Kode_Karyawan,
                            nama: exc.Nama_Karyawan || exc.Kode_Karyawan,
                            alasan: exc.Alasan || '',
                        }));
                    } catch (e) {
                        ElMessage.warning('Detail edit tidak lengkap, memakai data list terakhir.');
                    }

                    const tanggalMulai = source.Tanggal_Mulai?.split('T')[0] || '';
                    const tanggalSelesai = source.Tanggal_Selesai?.split('T')[0] || '';
                    const selectedDatesRaw = this.normalizeArrayField(source.selected_dates_parsed);
                    const selectedDates = selectedDatesRaw.length
                        ? selectedDatesRaw.map((date) => String(date).split('T')[0])
                        : this.buildDateRange(tanggalMulai, tanggalSelesai);

                    this.form = {
                        nama: source.Nama_Cuti_Bersama || '',
                        tanggal_mulai: tanggalMulai,
                        tanggal_selesai: tanggalSelesai,
                        selected_dates: selectedDates,
                        potong_ke_tipe_cuti: source.Potong_Ke_Tipe_Cuti || 'Tahunan',
                        keterangan: source.Keterangan || '',
                        filter_cabang: this.normalizeArrayField(source.filter_cabang_parsed),
                        filter_divisi: this.normalizeArrayField(source.filter_divisi_parsed),
                        filter_sub_divisi: this.normalizeArrayField(source.filter_sub_divisi_parsed),
                        filter_kode_karyawan: this.normalizeArrayField(source.filter_kode_karyawan_parsed),
                        exclusions: preloadedExclusions,
                    };
                    this.selectedSpecificKaryawan = this.uniqueKaryawanByKode(
                        this.form.filter_kode_karyawan.map((kode) => ({
                            Kode_Karyawan: kode,
                            Nama: kode,
                            nama_divisi: '-',
                        })),
                    );
                } else {
                    this.editingId = null;
                    this.form = this.getEmptyForm();
                }

                this.useUnitFilter =
                    this.form.filter_cabang.length > 0 ||
                    this.form.filter_divisi.length > 0 ||
                    this.form.filter_sub_divisi.length > 0;
                this.useSpecificTarget = this.form.filter_kode_karyawan.length > 0;

                this.syncSelectedDates();
                this.queueTargetExpectation();
            } finally {
                this.loading.wizard_init = false;
            }
        },
        uniqueKaryawanByKode(list = []) {
            const seen = new Set();
            return (list || []).filter((item) => {
                const kode = item?.Kode_Karyawan;
                if (!kode || seen.has(kode)) return false;
                seen.add(kode);
                return true;
            });
        },
        onTargetModeChange() {
            if (!this.useUnitFilter) {
                this.form.filter_cabang = [];
                this.form.filter_divisi = [];
                this.form.filter_sub_divisi = [];
            }

            if (!this.useSpecificTarget) {
                this.form.filter_kode_karyawan = [];
                this.selectedSpecificKaryawan = [];
            }

            this.queueTargetExpectation();
        },
        onDivisiSelectionChange() {
            const allowedSubDivisi = new Set(this.filteredSubDivisi.map((sd) => sd.ID_Sub_Divisi));
            this.form.filter_sub_divisi = this.form.filter_sub_divisi.filter((id) => allowedSubDivisi.has(id));
            this.queueTargetExpectation();
        },
        queueTargetExpectation: debounce(function () {
            this.updateTargetExpectation();
        }, 450),
        async updateTargetExpectation() {
            if (this.wizardStep !== 1) return;

            const reqId = ++this.targetExpectationReqId;
            this.targetExpectation.loading = true;
            this.targetExpectation.error = '';

            try {
                const payload = {
                    id_cuti_bersama: this.editingId,
                    tanggal_mulai: this.form.tanggal_mulai,
                    tanggal_selesai: this.form.tanggal_selesai,
                    selected_dates: this.form.selected_dates,
                    jumlah_hari: this.computedDays,
                    filter_cabang: this.useUnitFilter ? this.form.filter_cabang : [],
                    filter_divisi: this.useUnitFilter ? this.form.filter_divisi : [],
                    filter_sub_divisi: this.useUnitFilter ? this.form.filter_sub_divisi : [],
                    filter_kode_karyawan: this.useSpecificTarget ? this.form.filter_kode_karyawan : [],
                    exclusions: [],
                };

                const { data } = await axios.post('/api/v1/cuti-bersama-adv/preview', payload);
                const result = this.resolveApiResult(data) || {};

                if (reqId !== this.targetExpectationReqId) return;
                this.targetExpectation.total = result.total_terdampak ?? 0;
            } catch (e) {
                if (reqId !== this.targetExpectationReqId) return;
                this.targetExpectation.error = 'Estimasi target belum bisa dihitung sekarang.';
                this.targetExpectation.total = null;
            } finally {
                if (reqId === this.targetExpectationReqId) {
                    this.targetExpectation.loading = false;
                }
            }
        },
        normalizeArrayField(value) {
            if (Array.isArray(value)) return value;
            if (typeof value === 'string' && value.trim()) {
                try {
                    const parsed = JSON.parse(value);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }
            return [];
        },
        handleSelectedDatesChange() {
            this.syncSelectedDates();
        },
        clearSelectedDates() {
            this.form.selected_dates = [];
            this.syncSelectedDates();
        },
        removeSelectedDate(date) {
            this.form.selected_dates = (this.form.selected_dates || []).filter((item) => item !== date);
            this.syncSelectedDates();
        },
        syncSelectedDates() {
            const normalized = [...new Set((this.form.selected_dates || []).filter(Boolean))].sort();
            this.form.selected_dates = normalized;
            this.form.tanggal_mulai = normalized[0] || '';
            this.form.tanggal_selesai = normalized[normalized.length - 1] || '';
        },
        buildDateRange(start, end) {
            if (!start || !end) return [];
            const s = new Date(start);
            const e = new Date(end);
            if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) return [];
            const from = s <= e ? s : e;
            const to = s <= e ? e : s;
            const dates = [];
            for (const d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
                const iso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
                dates.push(iso);
            }
            return dates;
        },
        resolveExecutionDatesForDisplay(source) {
            if (!source || typeof source !== 'object') return [];

            const selectedDates = this.normalizeArrayField(source.selected_dates_parsed ?? source.Selected_Dates)
                .map((date) => String(date).split('T')[0])
                .filter(Boolean);

            if (selectedDates.length) {
                return [...new Set(selectedDates)].sort();
            }

            const tanggalMulai = source.Tanggal_Mulai ? String(source.Tanggal_Mulai).split('T')[0] : '';
            const tanggalSelesai = source.Tanggal_Selesai ? String(source.Tanggal_Selesai).split('T')[0] : '';

            return this.buildDateRange(tanggalMulai, tanggalSelesai);
        },
        async nextWizardStep() {
            if (!this.canProceedWizard) {
                ElMessage.warning(this.wizardValidationHint || 'Lengkapi data yang wajib sebelum lanjut.');
                return;
            }

            if (this.wizardStep === 0) {
                await this.ensureFilterOptionsLoaded();
            }

            this.syncSelectedDates();

            if (this.wizardStep === 1 && !this.useUnitFilter) {
                this.form.filter_cabang = [];
                this.form.filter_divisi = [];
                this.form.filter_sub_divisi = [];
            }

            if (this.wizardStep === 1 && !this.useSpecificTarget) {
                this.form.filter_kode_karyawan = [];
                this.selectedSpecificKaryawan = [];
            }
            this.wizardStep++;
            if (this.wizardStep === 1) {
                this.queueTargetExpectation();
            }
            if (this.wizardStep === 2) {
                await this.loadExclusionCandidates();
            }
            if (this.wizardStep === 3) {
                this.previewConfirmed = false;
                await this.loadPreview();
            }
        },
        async loadPreview() {
            this.syncSelectedDates();
            this.loading.preview = true;
            try {
                const { data } = await axios.post('/api/v1/cuti-bersama-adv/preview', {
                    id_cuti_bersama: this.editingId,
                    tanggal_mulai: this.form.tanggal_mulai,
                    tanggal_selesai: this.form.tanggal_selesai,
                    selected_dates: this.form.selected_dates,
                    jumlah_hari: this.computedDays,
                    filter_cabang: this.form.filter_cabang,
                    filter_divisi: this.form.filter_divisi,
                    filter_sub_divisi: this.form.filter_sub_divisi,
                    filter_kode_karyawan: this.form.filter_kode_karyawan,
                    exclusions: this.form.exclusions,
                });
                this.previewData = this.resolveApiResult(data);
                this.syncPreviewConflictSelection();
            } catch (e) {
                ElMessage.error('Gagal memuat preview');
            }
            this.loading.preview = false;
        },
        async submitForm() {
            if (this.previewImpactedCount <= 0) {
                ElMessage.warning('Tidak ada karyawan terdampak. Data tidak bisa disimpan.');
                return;
            }

            if (this.previewOverlapSummary.has_conflict) {
                ElMessage.warning(
                    'Masih ada konflik target/tanggal. Kecualikan semua karyawan konflik dulu, lalu simpan ulang.',
                );
                return;
            }

            if (!this.previewConfirmed) {
                ElMessage.warning('Konfirmasi preview dulu sebelum menyimpan.');
                return;
            }

            if (
                this.submitExecutionMode === 'scheduled' &&
                !this.validateScheduledAt(this.submitScheduledAt, 'eksekusi')
            ) {
                return;
            }

            this.syncSelectedDates();

            this.loading.submit = true;
            try {
                const payload = {
                    ...this.form,
                    execution_mode: this.submitExecutionMode,
                    scheduled_at: this.submitExecutionMode === 'scheduled' ? this.submitScheduledAt : null,
                };

                if (this.editingId) {
                    const { data } = await axios.put(`/api/v1/cuti-bersama-adv/update/${this.editingId}`, payload);
                    ElMessage.success(data.message || 'Berhasil diperbarui');
                } else {
                    const { data } = await axios.post('/api/v1/cuti-bersama-adv/store', payload);
                    ElMessage.success(data.message || 'Berhasil dibuat');
                }
                this.showWizard = false;
                await this.loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan');
            }
            this.loading.submit = false;
        },
        openConfirmAction(options = {}) {
            this.confirmAction = {
                type: options.type || '',
                title: options.title || 'Konfirmasi',
                body: options.body || 'Apakah Anda yakin ingin melanjutkan aksi ini?',
                confirmText: options.confirmText || 'Konfirmasi',
                buttonClass: options.buttonClass || 'cb-btn-primary',
                buttonIcon: options.buttonIcon || 'bi bi-check-lg',
                iconClass: options.iconClass || 'bi bi-exclamation-triangle text-warning',
                payload: options.payload ?? null,
                loading: false,
            };
            this.showConfirmActionModal = true;
        },
        closeConfirmActionModal() {
            if (this.confirmAction.loading) return;
            this.showConfirmActionModal = false;
            this.confirmAction = {
                type: '',
                title: '',
                body: '',
                confirmText: 'Konfirmasi',
                buttonClass: 'cb-btn-primary',
                buttonIcon: 'bi bi-check-lg',
                iconClass: 'bi bi-exclamation-triangle text-warning',
                payload: null,
                loading: false,
            };
        },
        async confirmActionHandler() {
            const { type, payload } = this.confirmAction;
            if (!type) return;

            this.confirmAction.loading = true;

            try {
                if (type === 'delete') {
                    await this.performDelete(payload);
                } else if (type === 'bulk-exclude') {
                    await this.performBulkExcludeImpactedKaryawan(payload);
                } else if (type === 'bulk-include') {
                    await this.performBulkIncludeExclusions(payload);
                } else if (type === 'rollback') {
                    await this.performRollback(payload);
                } else if (type === 'cancel-schedule') {
                    await this.performCancelSchedule(payload);
                }
            } finally {
                this.confirmAction.loading = false;
            }

            this.closeConfirmActionModal();
        },
        async confirmDelete(item) {
            this.openConfirmAction({
                type: 'delete',
                title: 'Hapus Cuti Bersama',
                body: `Apakah Anda yakin ingin menghapus "${item.Nama_Cuti_Bersama}"?`,
                confirmText: 'Hapus',
                buttonClass: 'cb-btn-danger',
                buttonIcon: 'bi bi-trash',
                iconClass: 'bi bi-exclamation-triangle text-danger',
                payload: item,
            });
        },
        async performDelete(item) {
            try {
                await axios.delete(`/api/v1/cuti-bersama-adv/delete/${item.Id_Cuti_Bersama}`);
                ElMessage.success('Dihapus');
                await this.loadList();
                this.closeConfirmActionModal();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal');
            }
        },
        async startExecute(item) {
            if (!this.canExecuteItem(item)) return;
            this.loading.execute_prep = true;
            this.executeTarget = item;
            this.executeMode = 'now';
            this.scheduledAt = '';
            this.showExecuteModal = true;

            try {
                const { data } = await axios.get(`/api/v1/cuti-bersama-adv/detail/${item.Id_Cuti_Bersama}`);
                const detail = this.resolveApiResult(data) || {};
                const cb = detail.cuti_bersama || {};

                this.executeTarget = {
                    ...item,
                    ...cb,
                    selected_dates_parsed: cb.selected_dates_parsed ?? item.selected_dates_parsed,
                };
            } catch (e) {
                ElMessage.warning('Detail cuti bersama tidak lengkap, memakai data daftar yang tersedia.');
            } finally {
                this.loading.execute_prep = false;
            }
        },
        async doExecute() {
            if (this.executeMode === 'scheduled' && !this.validateScheduledAt(this.scheduledAt, 'eksekusi')) {
                return;
            }

            this.loading.execute = true;
            try {
                const payload = {};
                if (this.executeMode === 'scheduled' && this.scheduledAt) payload.scheduled_at = this.scheduledAt;
                const { data } = await axios.post(
                    `/api/v1/cuti-bersama-adv/execute/${this.executeTarget.Id_Cuti_Bersama}`,
                    payload,
                );
                ElMessage.success(data.message || 'Dispatch berhasil');
                this.showExecuteModal = false;
                this.loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal');
            }
            this.loading.execute = false;
        },
        confirmCancelSchedule(item) {
            if (!this.canCancelItem(item)) return;

            this.openConfirmAction({
                type: 'cancel-schedule',
                title: 'Batalkan Jadwal Eksekusi',
                body: `Batalkan jadwal execute untuk "${item.Nama_Cuti_Bersama}"?`,
                confirmText: 'Batalkan Jadwal',
                buttonClass: 'cb-btn-warning',
                buttonIcon: 'bi bi-x-circle',
                iconClass: 'bi bi-calendar-x text-warning',
                payload: item,
            });
        },
        async performCancelSchedule(item) {
            try {
                const { data } = await axios.post(`/api/v1/cuti-bersama-adv/cancel-execute/${item.Id_Cuti_Bersama}`);
                const result = this.resolveApiResult(data) || {};
                const deleteStatus = String(result.cloud_task_delete_status || '')
                    .trim()
                    .toLowerCase();
                const baseMessage = data.message || 'Jadwal eksekusi dibatalkan';
                const fullMessage = `${baseMessage}${result.cloud_task_note ? ` (${result.cloud_task_note})` : ''}`;

                if (deleteStatus === 'delete_error') {
                    ElMessage.warning(fullMessage);
                } else if (
                    deleteStatus === 'not_found' ||
                    deleteStatus === 'skipped_by_design' ||
                    deleteStatus === 'skipped'
                ) {
                    ElMessage.success(fullMessage);
                } else {
                    ElMessage.success(fullMessage);
                }

                await this.loadList();
                this.closeConfirmActionModal();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal membatalkan jadwal execute');
            }
        },
        async openDetail(item) {
            this.detailTab = 'karyawan';
            this.detailData = null;
            this.detailSelectedImpactKodes = [];
            this.detailSelectedExclusionKodes = [];
            this.detailImpactSearch = '';
            this.detailExclusionSearch = '';
            this.showDetailModal = true;
            this.loading.detail = true;
            try {
                const { data } = await axios.get(`/api/v1/cuti-bersama-adv/detail/${item.Id_Cuti_Bersama}`, {
                    params: {
                        page: 1,
                        per_page: 5000,
                    },
                });
                this.detailData = this.resolveApiResult(data);
            } catch (e) {
                ElMessage.error('Gagal memuat detail');
            }
            this.loading.detail = false;
        },
        async reloadDetail() {
            const id = this.detailData?.cuti_bersama?.Id_Cuti_Bersama;
            if (!id) return;
            await this.openDetail({ Id_Cuti_Bersama: id });
        },
        isExclusionConflict(kodeKaryawan) {
            const summary = this.detailExclusionConflictMap?.[kodeKaryawan];
            return Boolean(summary?.has_conflict);
        },
        toggleSelectAllImpacted(event) {
            if (!this.detailCanManageExclusions) return;

            const visibleKodes = this.detailImpactedFilteredRows.map((row) => row.Kode_Karyawan).filter(Boolean);

            if (event?.target?.checked) {
                const merged = new Set(this.detailSelectedImpactKodes);
                visibleKodes.forEach((kode) => merged.add(kode));
                this.detailSelectedImpactKodes = Array.from(merged);
                return;
            }

            const visibleSet = new Set(visibleKodes);
            this.detailSelectedImpactKodes = this.detailSelectedImpactKodes.filter((kode) => !visibleSet.has(kode));
        },
        toggleSelectAllExclusions(event) {
            if (!this.detailCanManageExclusions) return;

            const selectableVisibleKodes = this.detailExclusionFilteredRows
                .map((row) => row.Kode_Karyawan)
                .filter((kode) => kode && !this.isExclusionConflict(kode));

            if (event?.target?.checked) {
                const merged = new Set(this.detailSelectedExclusionKodes);
                selectableVisibleKodes.forEach((kode) => merged.add(kode));
                this.detailSelectedExclusionKodes = Array.from(merged);
                return;
            }

            const visibleSet = new Set(selectableVisibleKodes);
            this.detailSelectedExclusionKodes = this.detailSelectedExclusionKodes.filter(
                (kode) => !visibleSet.has(kode),
            );
        },
        async bulkExcludeImpactedKaryawan() {
            const id = this.detailData?.cuti_bersama?.Id_Cuti_Bersama;

            if (!id || !this.detailCanManageExclusions) return;
            if (!this.detailSelectedImpactKodes.length) {
                ElMessage.warning('Pilih minimal 1 karyawan terdampak untuk dikecualikan.');
                return;
            }

            this.openConfirmAction({
                type: 'bulk-exclude',
                title: 'Konfirmasi Pengecualian',
                body: `Kecualikan ${this.detailSelectedImpactKodes.length} karyawan dari cuti bersama ini?`,
                confirmText: 'Kecualikan',
                buttonClass: 'cb-btn-warning',
                buttonIcon: 'bi bi-person-x',
                iconClass: 'bi bi-exclamation-triangle text-warning',
                payload: { id },
            });
        },

        async performBulkExcludeImpactedKaryawan({ id }) {
            this.loading.detail_exclusion_action = true;
            try {
                const { data } = await axios.post(`/api/v1/cuti-bersama-adv/exclusion/bulk-add/${id}`, {
                    kode_karyawan: this.detailSelectedImpactKodes,
                    alasan: 'Dikecualikan dari tab detail karyawan terdampak',
                });
                ElMessage.success(data.message || 'Pengecualian berhasil ditambahkan.');
                this.detailSelectedImpactKodes = [];
                await this.reloadDetail();
                await this.loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menambahkan pengecualian.');
            }
            this.loading.detail_exclusion_action = false;
        },
        async bulkIncludeExclusions() {
            const id = this.detailData?.cuti_bersama?.Id_Cuti_Bersama;

            if (!id || !this.detailCanManageExclusions) return;
            if (!this.detailSelectedExclusionKodes.length) {
                ElMessage.warning('Pilih minimal 1 karyawan pengecualian untuk disertakan kembali.');
                return;
            }

            const conflictSelected = this.detailSelectedExclusionKodes.filter((kode) => this.isExclusionConflict(kode));
            if (conflictSelected.length) {
                ElMessage.warning('Masih ada karyawan konflik di pilihan. Hapus dari pilihan sebelum submit.');
                return;
            }

            this.openConfirmAction({
                type: 'bulk-include',
                title: 'Sertakan Kembali Karyawan',
                body: `Sertakan kembali ${this.detailSelectedExclusionKodes.length} karyawan ke target cuti bersama?`,
                confirmText: 'Sertakan Kembali',
                buttonClass: 'cb-btn-primary',
                buttonIcon: 'bi bi-person-check',
                iconClass: 'bi bi-arrow-repeat text-primary',
                payload: { id },
            });
        },

        async performBulkIncludeExclusions({ id }) {
            this.loading.detail_exclusion_action = true;
            try {
                const { data } = await axios.post(`/api/v1/cuti-bersama-adv/exclusion/bulk-remove/${id}`, {
                    kode_karyawan: this.detailSelectedExclusionKodes,
                });

                const result = this.resolveApiResult(data) || {};
                if (Number(result.blocked || 0) > 0) {
                    ElMessage.warning(
                        `${result.included || 0} disertakan kembali, ${result.blocked || 0} ditolak karena konflik.`,
                    );
                } else {
                    ElMessage.success(data.message || 'Karyawan berhasil disertakan kembali.');
                }

                this.detailSelectedExclusionKodes = [];
                await this.reloadDetail();
                await this.loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyertakan kembali karyawan.');
            }
            this.loading.detail_exclusion_action = false;
        },
        openRollback(item) {
            const resolvedId = this.resolveCutiBersamaId(item) || this.resolveCutiBersamaId(this.detailData);

            if (!resolvedId) {
                ElMessage.error('ID transaksi cuti bersama tidak ditemukan. Muat ulang daftar lalu coba lagi.');
                return;
            }

            this.rollbackTargetId = resolvedId;
            this.rollbackTarget = {
                ...(item || {}),
                Id_Cuti_Bersama: resolvedId,
            };
            this.rollbackForm = {
                scope: 'karyawan',
                kode_karyawan: [],
                divisi: [],
                sub_divisi: [],
            };
            this.rollbackSearch = '';
            this.rollbackOptions = { total_success: 0, karyawan: [], divisi: [], sub_divisi: [] };
            this.showRollbackModal = true;
            this.loadRollbackOptions();
        },
        toggleRollbackSelectAll(event) {
            const checked = Boolean(event?.target?.checked);
            if (!checked) {
                const visibleKodeSet = new Set(this.rollbackFilteredItems.map((item) => item.value));
                this.rollbackForm.kode_karyawan = this.rollbackForm.kode_karyawan.filter(
                    (kode) => !visibleKodeSet.has(kode),
                );
                return;
            }

            const merged = new Set(this.rollbackForm.kode_karyawan);
            this.rollbackFilteredItems.forEach((item) => merged.add(item.value));
            this.rollbackForm.kode_karyawan = Array.from(merged);
        },
        getFirstDefinedValue(source, keys = []) {
            if (!source || typeof source !== 'object') return undefined;

            for (const key of keys) {
                if (Object.prototype.hasOwnProperty.call(source, key) && source[key] !== undefined) {
                    return source[key];
                }
            }

            return undefined;
        },
        normalizeRollbackKaryawanItem(item) {
            if (typeof item === 'string' || typeof item === 'number') {
                const kode = String(item).trim();
                if (!kode) return null;

                return {
                    kode_karyawan: kode,
                    nama_karyawan: kode,
                    divisi: '',
                    sub_divisi: '',
                };
            }

            if (!item || typeof item !== 'object') return null;

            const kode = this.getFirstDefinedValue(item, [
                'kode_karyawan',
                'Kode_Karyawan',
                'kode',
                'Kode',
                'value',
                'Value',
                'id',
                'Id',
            ]);
            const nama = this.getFirstDefinedValue(item, ['nama_karyawan', 'Nama_Karyawan', 'nama', 'Nama']);
            const divisi = this.getFirstDefinedValue(item, ['divisi', 'Divisi', 'nama_divisi']);
            const subDivisi = this.getFirstDefinedValue(item, [
                'sub_divisi',
                'Sub_Divisi',
                'subDivisi',
                'nama_sub_divisi',
            ]);

            const normalizedKode = kode ? String(kode).trim() : '';
            if (!normalizedKode) return null;

            return {
                kode_karyawan: normalizedKode,
                nama_karyawan: nama ? String(nama).trim() : normalizedKode,
                divisi: divisi ? String(divisi).trim() : '',
                sub_divisi: subDivisi ? String(subDivisi).trim() : '',
            };
        },
        normalizeRollbackGroupValues(value, fallbackKeys = []) {
            const list = Array.isArray(value) ? value : value && typeof value === 'object' ? Object.values(value) : [];

            return list
                .map((item) => {
                    if (typeof item === 'string' || typeof item === 'number') {
                        return String(item).trim();
                    }

                    if (item && typeof item === 'object') {
                        const extracted = this.getFirstDefinedValue(item, fallbackKeys);
                        return extracted ? String(extracted).trim() : '';
                    }

                    return '';
                })
                .filter(Boolean);
        },
        normalizeRollbackOptionsPayload(raw) {
            if (typeof raw === 'string') {
                try {
                    raw = JSON.parse(raw);
                } catch (e) {
                    raw = {};
                }
            }

            const sources = [raw, raw?.data, raw?.result, raw?.payload, raw?.options].filter(
                (item) => item && typeof item === 'object',
            );

            const sourceWithKeys = sources.find((item) => {
                return (
                    item.karyawan !== undefined ||
                    item.Karyawan !== undefined ||
                    item.kode_karyawan !== undefined ||
                    item.divisi !== undefined ||
                    item.Divisi !== undefined ||
                    item.sub_divisi !== undefined ||
                    item.subdivisi !== undefined
                );
            });

            const source = sourceWithKeys || raw || {};

            const karyawanRaw =
                this.getFirstDefinedValue(source, ['karyawan', 'Karyawan', 'kode_karyawan', 'Kode_Karyawan']) || [];
            const karyawanList = Array.isArray(karyawanRaw)
                ? karyawanRaw
                : karyawanRaw && typeof karyawanRaw === 'object'
                  ? Object.values(karyawanRaw)
                  : [];

            const seenKode = new Set();
            const karyawan = karyawanList
                .map((item) => this.normalizeRollbackKaryawanItem(item))
                .filter((item) => {
                    if (!item || !item.kode_karyawan) return false;
                    if (seenKode.has(item.kode_karyawan)) return false;
                    seenKode.add(item.kode_karyawan);
                    return true;
                });

            let divisi = this.normalizeRollbackGroupValues(
                this.getFirstDefinedValue(source, ['divisi', 'Divisi', 'division']),
                ['divisi', 'Divisi', 'nama_divisi', 'division'],
            );

            let subDivisi = this.normalizeRollbackGroupValues(
                this.getFirstDefinedValue(source, ['sub_divisi', 'Sub_Divisi', 'subdivisi', 'subDivisi']),
                ['sub_divisi', 'Sub_Divisi', 'subdivisi', 'subDivisi', 'nama_sub_divisi'],
            );

            if (!divisi.length) {
                divisi = [...new Set(karyawan.map((item) => item.divisi).filter(Boolean))];
            }

            if (!subDivisi.length) {
                subDivisi = [...new Set(karyawan.map((item) => item.sub_divisi).filter(Boolean))];
            }

            const totalSuccessRaw = this.getFirstDefinedValue(source, ['total_success', 'totalSuccess', 'total']);
            const totalSuccess = Number(totalSuccessRaw || 0);

            return {
                total_success: totalSuccess || karyawan.length,
                karyawan,
                divisi: [...new Set(divisi)].sort(),
                sub_divisi: [...new Set(subDivisi)].sort(),
            };
        },
        async loadRollbackOptionsFromDetail(id) {
            const { data } = await axios.get(`/api/v1/cuti-bersama-adv/detail/${id}`, {
                params: { page: 1, per_page: 5000 },
            });

            const detail = this.resolveApiResult(data) || {};
            const successRows = (Array.isArray(detail.details) ? detail.details : []).filter((row) => {
                const status = String(row?.Status || '')
                    .trim()
                    .toUpperCase();
                return status === 'SUCCESS' || status === 'COMPLETED';
            });

            const karyawan = successRows
                .map((row) => ({
                    kode_karyawan: row.Kode_Karyawan,
                    nama_karyawan: row.Nama_Karyawan,
                    divisi: row.Divisi,
                    sub_divisi: row.Sub_Divisi,
                }))
                .filter((row) => row.kode_karyawan);

            const divisi = [...new Set(karyawan.map((row) => row.divisi).filter(Boolean))].sort();
            const subDivisi = [...new Set(karyawan.map((row) => row.sub_divisi).filter(Boolean))].sort();

            return {
                total_success: karyawan.length,
                karyawan,
                divisi,
                sub_divisi: subDivisi,
            };
        },
        async loadRollbackOptions() {
            const id = this.rollbackTargetId || this.resolveCutiBersamaId(this.rollbackTarget);
            if (!id) return;

            this.loading.rollback_options = true;
            try {
                const { data } = await axios.get(`/api/v1/cuti-bersama-adv/rollback-options/${id}`);
                const firstLevel = this.resolveApiResult(data) || data?.result || data?.data || {};
                let normalized = this.normalizeRollbackOptionsPayload(firstLevel);

                if (!normalized.karyawan.length && firstLevel && typeof firstLevel === 'object') {
                    const directKaryawan = Array.isArray(firstLevel.karyawan)
                        ? firstLevel.karyawan
                        : firstLevel.karyawan && typeof firstLevel.karyawan === 'object'
                          ? Object.values(firstLevel.karyawan)
                          : [];

                    if (directKaryawan.length) {
                        normalized = this.normalizeRollbackOptionsPayload({
                            total_success: firstLevel.total_success || directKaryawan.length,
                            karyawan: directKaryawan,
                            divisi: firstLevel.divisi || [],
                            sub_divisi: firstLevel.sub_divisi || [],
                        });
                    }
                }

                if (!normalized.total_success && !normalized.karyawan.length) {
                    normalized = await this.loadRollbackOptionsFromDetail(id);
                }

                this.rollbackOptions = normalized;
            } catch (e) {
                try {
                    const fallback = await this.loadRollbackOptionsFromDetail(id);
                    this.rollbackOptions = fallback;

                    ElMessage.warning(
                        'Rollback options endpoint belum tersedia, memakai fallback dari detail eksekusi.',
                    );
                } catch (fallbackError) {
                    ElMessage.error(e.response?.data?.message || 'Gagal memuat target rollback.');
                    this.rollbackOptions = { total_success: 0, karyawan: [], divisi: [], sub_divisi: [] };
                }
            }
            this.loading.rollback_options = false;
        },
        async doRollback() {
            const targetId = this.rollbackTargetId || this.resolveCutiBersamaId(this.rollbackTarget);

            if (!targetId) {
                ElMessage.error('ID transaksi rollback tidak ditemukan. Tutup modal lalu coba lagi.');
                return;
            }

            if (!this.canSubmitRollback) {
                ElMessage.warning('Pilih minimal satu target rollback.');
                return;
            }

            this.openConfirmAction({
                type: 'rollback',
                title: 'Konfirmasi Rollback',
                body: this.getRollbackConfirmationBody(),
                confirmText: 'Rollback',
                buttonClass: 'cb-btn-warning',
                buttonIcon: 'bi bi-arrow-counterclockwise',
                iconClass: 'bi bi-exclamation-triangle text-danger',
                payload: { targetId },
            });
        },

        async performRollback({ targetId }) {
            this.loading.rollback = true;
            try {
                const payload = this.buildRollbackPayload();

                const { data } = await axios.post(`/api/v1/cuti-bersama-adv/rollback/${targetId}`, payload);
                const result = this.resolveApiResult(data) || {};
                ElMessage.success(data.message || result.message || 'Rollback berhasil dikirim dan sedang diproses.');
                this.showRollbackModal = false;

                // Refresh berjalan di background agar modal konfirmasi cepat tertutup.
                this.loadList();
                if (String(this.detailData?.cuti_bersama?.Id_Cuti_Bersama || '') === String(targetId)) {
                    this.reloadDetail();
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal');
            }
            this.loading.rollback = false;
        },
        buildRollbackPayload() {
            const scope = this.rollbackForm.scope || 'karyawan';

            if (scope === 'all') {
                return { scope: 'all' };
            }

            if (scope === 'divisi') {
                return {
                    scope: 'divisi',
                    divisi: [...this.rollbackForm.divisi],
                };
            }

            if (scope === 'sub_divisi') {
                return {
                    scope: 'sub_divisi',
                    sub_divisi: [...this.rollbackForm.sub_divisi],
                };
            }

            return {
                scope: 'karyawan',
                kode_karyawan: [...this.rollbackForm.kode_karyawan],
            };
        },
        getRollbackConfirmationBody() {
            const scope = this.rollbackForm.scope || 'karyawan';

            if (scope === 'all') {
                return `Rollback semua target aktif (${this.rollbackSelectedCount} karyawan)?`;
            }

            if (scope === 'divisi') {
                return `Rollback ${this.rollbackSelectedCount} divisi terpilih?`;
            }

            if (scope === 'sub_divisi') {
                return `Rollback ${this.rollbackSelectedCount} sub divisi terpilih?`;
            }

            return `Rollback ${this.rollbackSelectedCount} karyawan terpilih?`;
        },
        debounceSearchKaryawan: debounce(function () {
            this.doSearch(this.karyawanSearch, 'searchResults');
        }, 400),
        debounceSearchExclusion: debounce(function () {
            this.loadExclusionCandidates(this.exclusionSearch);
        }, 400),
        async loadExclusionCandidates(q = '') {
            try {
                const { data } = await axios.get('/api/v1/cuti-bersama-adv/search-karyawan', {
                    params: {
                        q,
                        target_only: true,
                        filter_cabang: this.useUnitFilter ? this.form.filter_cabang : [],
                        filter_divisi: this.useUnitFilter ? this.form.filter_divisi : [],
                        filter_sub_divisi: this.useUnitFilter ? this.form.filter_sub_divisi : [],
                        filter_kode_karyawan: this.useSpecificTarget ? this.form.filter_kode_karyawan : [],
                        exclude_kode_karyawan: (this.form.exclusions || []).map((item) => item.kode_karyawan),
                    },
                });
                this.exclusionResults = this.uniqueKaryawanByKode(this.resolveApiResult(data) || []);
            } catch (e) {
                console.error(e);
                this.exclusionResults = [];
            }
        },
        async doSearch(q, target) {
            if (!q || q.length < 2) {
                this[target] = [];
                return;
            }
            try {
                const params = { q };

                if (target === 'searchResults') {
                    params.exclude_kode_karyawan = this.form.filter_kode_karyawan || [];
                }

                const { data } = await axios.get('/api/v1/cuti-bersama-adv/search-karyawan', { params });
                this[target] = this.resolveApiResult(data) || [];
            } catch (e) {
                console.error(e);
            }
        },
        syncPreviewConflictSelection() {
            const allowed = new Set(this.previewConflictSelectableKodes);
            this.previewConflictSelectedKodes = this.previewConflictSelectedKodes.filter((kode) => allowed.has(kode));
        },
        toggleSelectAllPreviewConflicts(event) {
            if (event?.target?.checked) {
                this.previewConflictSelectedKodes = [...this.previewConflictSelectableKodes];
                return;
            }

            this.previewConflictSelectedKodes = [];
        },
        async bulkAddPreviewConflictsToExclusion() {
            if (!this.previewConflictSelectedKodes.length) {
                ElMessage.warning('Pilih minimal 1 karyawan konflik untuk dikecualikan.');
                return;
            }

            const rowsByKode = new Map(this.previewSampleDetailRows.map((row) => [row?.kode_karyawan, row]));
            let added = 0;

            for (const kode of this.previewConflictSelectedKodes) {
                if (!kode || this.exclusionKodeSet.has(kode)) continue;
                const row = rowsByKode.get(kode) || {};

                this.form.exclusions.push({
                    kode_karyawan: kode,
                    nama: row?.nama || kode,
                    alasan: 'Auto dari konflik overlap preview',
                });
                added += 1;
            }

            this.previewConflictSelectedKodes = [];

            if (!added) {
                ElMessage.info('Semua pilihan sudah ada di daftar pengecualian.');
                return;
            }

            ElMessage.success(`${added} karyawan konflik ditambahkan ke daftar pengecualian.`);
            await this.loadPreview();
        },
        addSpecificKaryawan(k) {
            this.useSpecificTarget = true;
            if (!this.form.filter_kode_karyawan.includes(k.Kode_Karyawan))
                this.form.filter_kode_karyawan.push(k.Kode_Karyawan);
            this.selectedSpecificKaryawan = this.uniqueKaryawanByKode([...this.selectedSpecificKaryawan, k]);
            this.searchResults = [];
            this.karyawanSearch = '';
            this.queueTargetExpectation();
        },
        removeSpecificKaryawan(kode) {
            this.form.filter_kode_karyawan = this.form.filter_kode_karyawan.filter((k) => k !== kode);
            this.selectedSpecificKaryawan = this.selectedSpecificKaryawan.filter((k) => k.Kode_Karyawan !== kode);
            this.queueTargetExpectation();
        },
        addExclusion(k) {
            if (!this.form.exclusions.find((e) => e.kode_karyawan === k.Kode_Karyawan))
                this.form.exclusions.push({
                    kode_karyawan: k.Kode_Karyawan,
                    nama: k.Nama,
                    alasan: '',
                });
            this.exclusionSearch = '';
            this.loadExclusionCandidates();
        },
        async addPreviewConflictToExclusion(row) {
            const kode = row?.kode_karyawan;
            const nama = row?.nama || kode;

            if (!kode) return;
            if (this.exclusionKodeSet.has(kode)) return;

            this.form.exclusions.push({
                kode_karyawan: kode,
                nama,
                alasan: 'Auto dari konflik overlap preview',
            });

            ElMessage.success(`${nama} ditambahkan ke daftar pengecualian.`);
            await this.loadPreview();
        },
    },
    mounted() {
        this.loadList();
        this.loadFilterOptions();
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}
</style>
<style scoped>
.cb-app-container {
    --cb-bg: var(--hcis-slate-100);
    --cb-surface: var(--hcis-white);
    --cb-surface-soft: var(--hcis-slate-50);
    --cb-border: var(--hcis-slate-200);
    --cb-text: var(--hcis-slate-800);
    --cb-muted: var(--hcis-slate-500);
    --cb-primary: var(--hcis-indigo-600);
    --cb-primary-soft: var(--hcis-indigo-50);
    --cb-success: var(--hcis-green-600);
    --cb-success-soft: #ecfdf3;
    --cb-warning: var(--hcis-amber-600);
    --cb-warning-soft: var(--hcis-amber-50);
    --cb-danger: var(--hcis-red-600);
    --cb-danger-soft: var(--hcis-red-50);
    --cb-info: var(--hcis-cyan-600);
    --cb-info-soft: var(--hcis-cyan-50);
    --cb-dark: var(--hcis-slate-900);
    --cb-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.08);
    --cb-shadow-md: 0 6px 18px rgba(15, 23, 42, 0.08);
    --cb-radius: 14px;
}

.cb-app-container {
    min-height: 100vh;
    /* background:
        radial-gradient(circle at 15% -10%, #dbeafe 0%, transparent 30%),
        radial-gradient(circle at 90% -20%, var(--hcis-indigo-100) 0%, transparent 26%), var(--cb-bg);
    color: var(--cb-text); */
    padding-bottom: 2rem;
}

.cb-stats-shell {
    padding: 0 0.5rem;
}

.cb-detail-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.cb-panel-card {
    background: var(--cb-surface);
    border: 1px solid var(--cb-border);
    border-radius: var(--cb-radius);
    box-shadow: var(--cb-shadow-md);
    padding: 1.25rem;
    animation: cbFadeIn 0.28s ease-out;
}

.cb-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.cb-panel-header-info {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.cb-panel-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--cb-primary-soft);
    color: var(--cb-primary);
    font-size: 1.1rem;
}

.cb-panel-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
}

.cb-panel-subtitle {
    margin: 0.15rem 0 0;
    color: var(--cb-muted);
    font-size: 0.84rem;
}

.cb-list-filter-wrap {
    margin-bottom: 1rem;
}

.cb-list-filter-wrap :deep(.filter-panel) {
    border-radius: 14px;
    border: 1px solid var(--cb-border);
    background: var(--cb-surface-soft);
}

.cb-panel-actions {
    display: flex;
    align-items: end;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.cb-year-filter {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.cb-label {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--cb-muted);
}

.cb-select,
.cb-input {
    border: 1px solid var(--cb-border);
    border-radius: 10px;
    background: var(--cb-surface);
    color: var(--cb-text);
    min-height: 42px;
}

.cb-select[multiple] {
    min-height: 142px;
    padding-top: 0.35rem;
    padding-bottom: 0.35rem;
}

.cb-date-picker {
    width: 100%;
    display: block;
}

.cb-date-picker :deep(.el-date-editor),
.cb-date-picker :deep(.el-input),
.cb-date-picker :deep(.el-date-editor.el-input) {
    width: 100% !important;
}

.cb-date-picker :deep(.el-input__wrapper) {
    width: 100%;
    min-height: 42px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 0 0 1px var(--cb-border) inset !important;
}

.cb-date-picker :deep(.el-input__wrapper:hover),
.cb-date-picker :deep(.el-input__wrapper.is-focus) {
    box-shadow:
        0 0 0 1px var(--cb-primary) inset,
        0 0 0 3px rgba(79, 70, 229, 0.15) !important;
}

.cb-date-picker :deep(.el-input__inner) {
    color: var(--cb-text);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

:global(.cb-date-popper) {
    border-radius: 12px !important;
    border: 1px solid var(--hcis-slate-200) !important;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12) !important;
}

:global(.cb-date-popper .el-picker-panel) {
    border: 0 !important;
}

:global(.cb-date-popper .el-date-picker__header-label) {
    font-weight: 700;
    color: var(--hcis-slate-800);
}

:global(.cb-date-popper .el-date-table td.current:not(.disabled) .el-date-table-cell__text),
:global(.cb-date-popper .el-date-table td.selected .el-date-table-cell__text) {
    background: var(--hcis-indigo-600) !important;
    color: #fff !important;
}

:global(.cb-date-popper .el-date-table td.available:hover .el-date-table-cell__text) {
    color: var(--hcis-indigo-600) !important;
}

:global(.cb-date-popper .el-date-table th) {
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
    font-weight: 700;
}

.cb-select:focus,
.cb-input:focus {
    border-color: var(--cb-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.cb-btn {
    border: 1px solid transparent;
    border-radius: 10px;
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0 0.95rem;
    font-size: 0.86rem;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
}

.cb-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.cb-btn-primary {
    background: var(--cb-primary);
    color: #fff;
}

.cb-btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 8px 14px rgba(79, 70, 229, 0.22);
}

.cb-btn-secondary {
    border-color: var(--cb-border);
    background: var(--cb-surface);
    color: var(--cb-muted);
}

.cb-btn-secondary:hover:not(:disabled) {
    border-color: var(--hcis-slate-300);
    color: var(--cb-text);
    background: var(--cb-surface-soft);
}

.cb-btn-success {
    background: var(--cb-success);
    color: #fff;
}

.cb-btn-danger {
    background: var(--cb-danger);
    color: #fff;
}

.cb-btn-warning {
    background: var(--cb-warning);
    color: #fff;
}

.cb-btn-ghost {
    border-color: var(--cb-border);
    background: #fff;
    color: var(--cb-muted);
}

.cb-btn-ghost:hover:not(:disabled) {
    background: var(--cb-surface-soft);
    color: var(--cb-text);
}

.cb-btn-inline {
    width: auto !important;
    min-height: 34px;
    font-size: 0.78rem;
    margin-top: 0.65rem;
}

.cb-list-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-bottom: 1rem;
}

.cb-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid var(--cb-border);
    background: #fff;
    color: var(--cb-muted);
    font-size: 0.75rem;
}

.cb-meta-chip strong {
    color: var(--cb-text);
    font-size: 0.8rem;
}

.cb-meta-chip.success {
    background: var(--cb-success-soft);
    border-color: var(--hcis-green-200);
    color: #166534;
}

.cb-meta-chip.muted {
    background: var(--hcis-slate-50);
}

.cb-meta-chip.info {
    background: var(--cb-info-soft);
    border-color: var(--hcis-cyan-200);
    color: var(--hcis-cyan-700);
}

.cb-loading-state,
.cb-empty-state {
    border: 1px dashed var(--cb-border);
    border-radius: var(--cb-radius);
    background: var(--cb-surface-soft);
    padding: 2rem 1rem;
    text-align: center;
}

.cb-loading-spinner {
    width: 64px;
    height: 64px;
    margin: 0 auto 0.8rem;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--cb-primary-soft);
    color: var(--cb-primary);
    font-size: 1.4rem;
}

.cb-loading-title {
    margin: 0;
    font-weight: 700;
}

.cb-loading-description {
    margin: 0.2rem 0 0;
    color: var(--cb-muted);
    font-size: 0.84rem;
}

.cb-empty-state i {
    font-size: 2rem;
    color: var(--cb-muted);
}

.cb-empty-state h5 {
    margin-top: 0.8rem;
    margin-bottom: 0.3rem;
}

.cb-empty-state p {
    color: var(--cb-muted);
    margin-bottom: 1rem;
}

.cb-table-wrapper {
    overflow: auto;
    border: 1px solid var(--cb-border);
    border-radius: 12px;
    background: #fff;
    padding: 10px;
}

.cb-table {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.cb-table thead th {
    background: var(--cb-surface-soft);
    color: var(--cb-muted);
    text-transform: uppercase;
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    font-weight: 700;
    padding: 0.85rem 0.9rem;
    border-bottom: 1px solid var(--cb-border);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
}

.cb-table td {
    padding: 0.85rem 0.9rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    vertical-align: middle;
    font-size: 0.87rem;
}

.cb-table tbody tr:hover td {
    background: #fafbff;
}

.cb-table tbody tr:last-child td {
    border-bottom: 0;
}

.cb-name-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.cb-name-cell small,
.cb-muted {
    color: var(--cb-muted);
}

.cb-action-group {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.cb-action-btn {
    width: 32px;
    height: 32px;
    border: 1px solid var(--cb-border);
    border-radius: 9px;
    background: #fff;
    color: var(--cb-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.cb-action-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.cb-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--cb-shadow-sm);
}

.cb-view:hover {
    color: #1d4ed8;
    background: #eff6ff;
}

.cb-edit:hover {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-50);
}

.cb-execute:hover {
    color: #166534;
    background: #ecfdf3;
}

.cb-cancel:hover {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-50);
}

.cb-rollback:hover {
    color: #c2410c;
    background: #fff7ed;
}

.cb-delete:hover {
    color: var(--hcis-red-700);
    background: var(--hcis-red-50);
}

.cb-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border: 1px solid transparent;
    line-height: 1.2;
}

.cb-pill-primary {
    background: var(--cb-primary-soft);
    color: var(--cb-primary);
    border-color: var(--hcis-indigo-200);
}

.cb-pill-success {
    background: var(--cb-success-soft);
    color: var(--hcis-green-700);
    border-color: var(--hcis-green-200);
}

.cb-pill-warning {
    background: var(--cb-warning-soft);
    color: var(--hcis-amber-700);
    border-color: var(--hcis-amber-300);
}

.cb-pill-danger {
    background: var(--cb-danger-soft);
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
}

.cb-pill-info {
    background: var(--cb-info-soft);
    color: var(--hcis-cyan-700);
    border-color: var(--hcis-cyan-200);
}

.cb-pill-muted {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border-color: var(--hcis-slate-200);
}

.cb-pill-dark {
    background: var(--hcis-slate-900);
    color: #fff;
}

.cb-modal-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.cb-modal-body-wizard {
    gap: 0.9rem;
}

.cb-wizard-loading {
    border: 1px dashed var(--cb-border);
    border-radius: 12px;
    background: var(--cb-surface-soft);
    min-height: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.45rem;
    color: var(--cb-muted);
}

.cb-wizard-loading i {
    font-size: 1.4rem;
    color: var(--cb-primary);
}

.cb-wizard-loading h6 {
    margin: 0;
    color: var(--cb-text);
    font-weight: 700;
}

.cb-wizard-loading p {
    margin: 0;
    font-size: 0.83rem;
}

.cb-step-head {
    border: 1px solid var(--cb-border);
    border-radius: 12px;
    background: var(--hcis-white);
    padding: 0.75rem 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
}

.cb-step-head-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-cyan-50);
    color: var(--hcis-cyan-600);
    flex-shrink: 0;
}

.cb-step-head h6 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
}

.cb-step-head p {
    margin: 0.18rem 0 0;
    font-size: 0.78rem;
    color: var(--cb-muted);
}

.cb-warning-inline {
    border: 1px dashed var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
    color: var(--hcis-indigo-700);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
}

.cb-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.cb-wizard-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    padding: 0.1rem 0.4rem;
}

.cb-wizard-step {
    display: flex;
    align-items: center;
    position: relative;
}

.cb-wizard-step.active {
    z-index: 2;
}

.cb-step-number {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    border: 2px solid #d7dfeb;
    background: #edf2f7;
    color: #8a97ab;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1;
    box-shadow: 0 0 0 4px var(--hcis-slate-50);
    transition: all 0.2s ease;
}

.cb-wizard-step.active .cb-step-number {
    border-color: var(--hcis-blue-500);
    background: linear-gradient(135deg, var(--hcis-blue-500) 0%, var(--hcis-blue-600) 100%);
    color: #fff;
    box-shadow:
        0 0 0 4px var(--hcis-slate-50),
        0 0 0 6px rgba(59, 130, 246, 0.16);
}

.cb-step-label {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.cb-step-connector {
    width: 44px;
    height: 3px;
    border-radius: 999px;
    background: #dbe3ef;
    margin: 0 0.65rem;
}

.cb-modal-footer-wizard {
    padding-top: 0.35rem;
}

.cb-modal-footer-wizard .cb-btn {
    min-height: 42px;
    border-radius: 10px;
    padding: 0 1rem;
}

.cb-modal-footer-wizard .cb-btn-secondary {
    background: var(--hcis-slate-50);
    border-color: #dbe3ef;
    color: var(--hcis-slate-500);
}

.cb-modal-footer-wizard .cb-btn-primary {
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-400) 100%);
    border-color: transparent;
}

.cb-modal-footer-wizard .cb-btn-primary:hover:not(:disabled) {
    box-shadow: 0 10px 18px rgba(79, 70, 229, 0.28);
}

.cb-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.cb-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 10px;
}

.cb-field.full {
    grid-column: 1 / -1;
}

.cb-form-grid > .full {
    grid-column: 1 / -1;
}

.cb-el-select {
    width: 100%;
}

.cb-el-select :deep(.el-select__wrapper) {
    min-height: 42px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 0 0 1px var(--cb-border) inset !important;
}

.cb-el-select :deep(.el-select__wrapper:hover),
.cb-el-select :deep(.el-select__wrapper.is-focused) {
    box-shadow:
        0 0 0 1px var(--cb-primary) inset,
        0 0 0 3px rgba(79, 70, 229, 0.15) !important;
}

.cb-el-select :deep(.el-select__placeholder),
.cb-el-select :deep(.el-select__selected-item) {
    color: var(--cb-text);
    font-size: 0.9rem;
}

.cb-help {
    font-size: 0.75rem;
    color: var(--cb-muted);
}

.cb-date-summary {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.55rem;
}

.cb-clear-dates {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--hcis-red-200);
    border-radius: 999px;
    background: #fff1f2;
    color: var(--hcis-rose-700);
    min-height: 30px;
    padding: 0 0.7rem;
    font-size: 0.74rem;
    font-weight: 700;
}

.cb-clear-dates:hover {
    background: var(--hcis-rose-100);
}

.cb-textarea {
    min-height: 84px;
    resize: vertical;
}

.cb-info-banner {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.84rem;
    margin-top: 0.35rem;
}

.cb-overlap-banner {
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    margin-top: 0.65rem;
    font-size: 0.82rem;
}

.cb-overlap-banner.warning {
    border: 1px solid var(--hcis-amber-300);
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
}

.cb-overlap-banner.blocking {
    border: 1px solid var(--hcis-red-200);
    background: var(--hcis-red-50);
    color: var(--hcis-red-800);
}

.cb-overlap-head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.3rem;
}

.cb-overlap-list {
    padding-left: 1rem;
    margin: 0;
}

.cb-overlap-list li {
    margin-bottom: 0.2rem;
}

.cb-preview-conflict-row td {
    background: #fff7ed;
    border-bottom-color: #fed7aa;
}

.cb-preview-conflict-pill {
    display: inline-flex;
    align-items: center;
    margin-left: 0.4rem;
    border: 1px solid var(--hcis-orange-300);
    background: var(--hcis-orange-100);
    color: #9a3412;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.cb-target-switch {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.cb-target-mode-card {
    border: 1px solid var(--cb-border);
    border-radius: 12px;
    background: #fff;
    padding: 0.65rem 0.75rem;
    display: grid;
    gap: 0.55rem;
}

.cb-check-row {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    cursor: pointer;
    color: var(--hcis-slate-700);
    font-size: 0.83rem;
}

.cb-check-row input {
    margin-top: 0.1rem;
    accent-color: var(--hcis-indigo-600);
}

.cb-check-row small {
    display: block;
    color: var(--cb-muted);
    font-size: 0.74rem;
    margin-top: 0.08rem;
}

.cb-filter-checkbox-grid {
    gap: 0.75rem;
}

.cb-checkbox-board {
    border: 1px solid var(--cb-border);
    border-radius: 10px;
    background: #fff;
    min-height: 130px;
    max-height: 220px;
    overflow: auto;
    padding: 0.45rem;
    display: grid;
    gap: 0.35rem;
    align-content: start;
}

.cb-check-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid #eef2f7;
    border-radius: 9px;
    padding: 0.35rem 0.45rem;
    cursor: pointer;
    font-size: 0.8rem;
}

.cb-check-item:hover {
    background: var(--hcis-slate-50);
}

.cb-check-item input {
    accent-color: var(--hcis-indigo-600);
}

.cb-target-preview {
    border: 1px dashed var(--hcis-indigo-200);
    border-radius: 12px;
    background: var(--hcis-indigo-50);
    padding: 0.7rem 0.8rem;
}

.cb-target-preview-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.cb-target-preview-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-bottom: 0.35rem;
    font-size: 0.78rem;
    color: var(--hcis-slate-600);
}

.cb-chip-btn {
    border: 1px solid var(--cb-border);
    border-radius: 9999px;
    background: #fff;
    color: var(--cb-muted);
    min-height: 40px;
    padding: 0 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.cb-tab-pane {
    animation: cbFadeIn 0.2s ease;
}

:deep(.approval-tabs-container) {
    border: 1px solid var(--cb-border);
    border-radius: 12px;
    box-shadow: none;
    margin-top: 0.25rem;
    margin-bottom: 0;
}

:deep(.tabs-header) {
    background: var(--cb-surface-soft);
    padding: 0.4rem;
    gap: 0.3rem;
}

:deep(.tab-button) {
    font-size: 0.8rem;
    border: 1px solid transparent;
}

:deep(.tab-button.active) {
    background: var(--cb-primary);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

:deep(.tab-button:not(.active) .tab-badge) {
    background: #fff;
}

:deep(.tab-content) {
    padding: 1rem;
}

.cb-chip-btn.active {
    background: var(--cb-primary-soft);
    color: var(--cb-primary);
    border-color: var(--hcis-indigo-200);
}

.cb-chip-btn:hover {
    border-color: var(--hcis-slate-300);
}

.cb-search-results {
    border: 1px solid var(--cb-border);
    border-radius: 10px;
    overflow: auto;
    max-height: 220px;
    margin-top: 0.45rem;
    background: #fff;
}

.cb-search-item {
    padding: 0.65rem 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    cursor: pointer;
}

.cb-search-item:last-child {
    border-bottom: none;
}

.cb-search-item:hover {
    background: var(--hcis-slate-50);
}

.cb-btn:focus-visible,
.cb-action-btn:focus-visible,
.cb-chip-btn:focus-visible,
.cb-select:focus-visible,
.cb-input:focus-visible,
.cb-search-item:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
    border-color: var(--cb-primary);
}

.cb-search-item small,
.cb-search-item span {
    color: var(--cb-muted);
    font-size: 0.76rem;
}

.cb-selected-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.65rem;
}

.cb-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--cb-primary-soft);
    color: var(--cb-primary);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
}

.cb-chip-date {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.cb-chip-date i {
    color: var(--hcis-blue-500);
}

.cb-chip i {
    cursor: pointer;
}

.cb-section-title {
    margin: 0 0 0.5rem;
    font-size: 0.9rem;
    font-weight: 700;
}

.cb-exclusion-item {
    display: grid;
    grid-template-columns: 1fr minmax(140px, 220px) auto;
    gap: 0.55rem;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.cb-exclusion-item:last-child {
    border-bottom: none;
}

.cb-exclusion-main {
    display: flex;
    gap: 0.3rem;
    align-items: center;
    min-width: 0;
}

.cb-empty-inline {
    border: 1px dashed var(--cb-border);
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    color: var(--cb-muted);
}

.cb-empty-inline i {
    font-size: 1.25rem;
}

.cb-empty-inline p {
    margin: 0.25rem 0 0;
}

.cb-loading-inline {
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    color: var(--cb-muted);
}

.cb-detail-loading-shell {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    min-height: 430px;
    background:
        radial-gradient(circle at top right, rgba(79, 70, 229, 0.12), transparent 28%),
        linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
}

.cb-detail-loader {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(79, 70, 229, 0.12);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

.cb-detail-loader h6 {
    margin: 0 0 0.2rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--cb-text);
}

.cb-detail-loader p {
    margin: 0;
    color: var(--cb-muted);
    font-size: 0.88rem;
}

.cb-detail-loader-orb {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    color: #fff;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--cb-primary), var(--hcis-blue-600));
    box-shadow: 0 16px 28px rgba(79, 70, 229, 0.28);
    animation: cbLoaderPulse 1.4s ease-in-out infinite;
}

.cb-detail-skeleton {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1rem 0;
}

.cb-detail-skeleton-top,
.cb-detail-skeleton-meta,
.cb-detail-skeleton-tabs,
.cb-detail-skeleton-row {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.cb-detail-skeleton-top {
    justify-content: space-between;
}

.cb-detail-skeleton-meta {
    flex-direction: column;
    align-items: flex-start;
}

.cb-detail-skeleton-tabs {
    padding-top: 0.25rem;
}

.cb-detail-skeleton-table {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid var(--cb-border);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.82);
}

.cb-detail-skeleton-row {
    display: grid;
    grid-template-columns: 2fr 1.3fr 0.8fr 0.8fr;
}

.cb-skeleton {
    position: relative;
    overflow: hidden;
    display: inline-flex;
    border-radius: 999px;
    background: var(--hcis-slate-200);
}

.cb-skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.75), transparent);
    animation: cbSkeletonShimmer 1.25s ease-in-out infinite;
}

.cb-skeleton-title {
    width: 240px;
    height: 20px;
}

.cb-skeleton-pill {
    width: 110px;
    height: 34px;
}

.cb-skeleton-line {
    height: 14px;
}

.cb-skeleton-line.short {
    width: 92px;
}

.cb-skeleton-line.medium {
    width: 180px;
}

.cb-skeleton-tab {
    width: 118px;
    height: 34px;
    border-radius: 12px;
}

.cb-skeleton-tab.active {
    width: 140px;
}

.cb-preview-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.cb-preview-card {
    border-radius: 12px;
    border: 1px solid var(--cb-border);
    background: var(--cb-surface-soft);
    text-align: center;
    padding: 0.8rem 0.6rem;
}

.cb-preview-card.success {
    background: var(--cb-success-soft);
    border-color: var(--hcis-green-200);
}

.cb-preview-card.warning {
    background: var(--cb-warning-soft);
    border-color: var(--hcis-amber-300);
}

.cb-preview-card.danger {
    background: var(--cb-danger-soft);
    border-color: var(--hcis-red-200);
}

.cb-preview-value {
    display: block;
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1;
}

.cb-preview-label {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--cb-muted);
}

.cb-review-card {
    border: 1px solid var(--cb-border);
    border-radius: 12px;
    background: #fff;
    padding: 0.7rem 0.8rem;
    margin-bottom: 0.65rem;
}

.cb-execution-plan {
    border-top: 1px solid #eef2f7;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
}

.cb-review-grid {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-bottom: 0.6rem;
}

.cb-review-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--cb-border);
    border-radius: 999px;
    padding: 0.22rem 0.55rem;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.74rem;
}

.cb-review-chip strong {
    color: var(--hcis-slate-900);
}

.cb-confirm-check {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--hcis-slate-700);
    cursor: pointer;
}

.cb-confirm-check input {
    margin-top: 0.12rem;
}

.cb-warning-banner {
    border: 1px solid var(--hcis-amber-300);
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
}

.cb-warning-banner p {
    margin: 0.1rem 0 0;
    font-size: 0.82rem;
}

.cb-warning-danger {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
}

.cb-confirmation-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.85rem;
    text-align: center;
    min-height: 150px;
}

.cb-confirmation-icon {
    font-size: 2.25rem;
    line-height: 1;
}

.cb-confirmation-text {
    color: var(--cb-text);
    font-size: 0.9rem;
    line-height: 1.55;
    max-width: 440px;
}

.cb-exec-target {
    border: 1px solid var(--cb-border);
    background: var(--cb-surface-soft);
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.6rem;
}

.cb-exec-target span {
    color: var(--cb-muted);
    font-size: 0.83rem;
}

.cb-exec-summary-card {
    border: 1px solid var(--cb-border);
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    border-radius: 12px;
    padding: 0.85rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.cb-exec-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.cb-exec-summary-item {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
    min-width: 0;
}

.cb-exec-summary-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--cb-muted);
}

.cb-exec-summary-item strong {
    color: var(--cb-text);
    font-size: 0.84rem;
    line-height: 1.5;
}

.cb-exec-summary-note {
    margin: 0;
    padding: 0.7rem 0.75rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    font-size: 0.82rem;
    line-height: 1.5;
    display: flex;
    flex-direction: column;
    gap: 0.24rem;
}

.cb-detail-date-highlight-compact {
    margin-top: 0;
}

.cb-detail-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid var(--cb-border);
    background: var(--cb-surface-soft);
    border-radius: 10px;
}

.cb-app-container :deep(.modal-container) {
    /* border: 1px solid var(--cb-border); */
    border-radius: 14px;
    /* box-shadow: 0 20px 42px rgba(15, 23, 42, 0.2); */
}

.cb-app-container :deep(.modal-header) {
    /* background: linear-gradient(180deg, var(--hcis-slate-50) 0%, var(--hcis-white) 100%); */
    border-bottom: 1px solid var(--cb-border);
    padding: 0.85rem 1rem;
}

.cb-app-container :deep(.modal-title) {
    font-size: 0.96rem;
    font-weight: 700;
    color: var(--cb-text);
}

.cb-app-container :deep(.modal-body) {
    padding: 1rem;
}

.cb-app-container :deep(.modal-body.cb-wizard-modal-shell) {
    background: var(--hcis-slate-50);
    padding: 0.9rem 1rem 1rem;
}

.cb-app-container :deep(.modal-footer) {
    border-top: 1px solid var(--cb-border);
    background: #eef2f7;
    padding: 0.7rem 1rem;
}

.cb-detail-head h6 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
}

.cb-detail-head p {
    margin: 0.15rem 0 0;
    color: var(--cb-muted);
    font-size: 0.8rem;
}

.cb-detail-date-highlight {
    margin-top: 0.55rem;
}

.cb-detail-date-label {
    display: inline-flex;
    align-items: center;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--cb-muted);
    margin-bottom: 0.35rem;
}

.cb-detail-date-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.cb-chip-date-highlight {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-color: #93c5fd;
    color: #1d4ed8;
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.12);
}

.cb-table-sm th,
.cb-table-sm td {
    padding: 0.65rem 0.75rem;
    font-size: 0.8rem;
}

/* ─── Pagination Styles ─── */
.cb-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.2rem 1rem;
    background: linear-gradient(to bottom, #fafbfc, var(--hcis-slate-100));
    border-top: 1px solid var(--cb-border);
    border-radius: 0 0 12px 12px;
    gap: 1.5rem;
    margin-top: -1px;
}

.cb-pagination-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.cb-pagination-text {
    color: var(--cb-muted);
    font-size: 0.85rem;
    font-weight: 500;
    letter-spacing: 0.02em;
}

.cb-pagination-text strong {
    color: var(--cb-text);
    font-weight: 700;
}

.cb-pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.cb-pagination-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--cb-border);
    border-radius: 8px;
    background: #fff;
    color: var(--cb-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.cb-pagination-btn:hover:not(:disabled) {
    color: var(--cb-primary);
    border-color: var(--cb-primary);
    background: var(--cb-primary-soft);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
}

.cb-pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
}

.cb-page-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    height: 36px;
    padding: 0 0.75rem;
    background: #fff;
    border: 1px solid var(--cb-border);
    border-radius: 8px;
    color: var(--cb-text);
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.spin {
    animation: spin 0.5s linear infinite;
    display: inline-table;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes cbSkeletonShimmer {
    100% {
        transform: translateX(100%);
    }
}

@keyframes cbLoaderPulse {
    0%,
    100% {
        transform: translateY(0) scale(1);
        box-shadow: 0 16px 28px rgba(79, 70, 229, 0.28);
    }
    50% {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 20px 32px rgba(79, 70, 229, 0.34);
    }
}

@keyframes cbFadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cb-panel-card,
    .cb-btn,
    .cb-action-btn,
    .cb-chip-btn,
    .spin {
        animation: none !important;
        transition: none !important;
    }
}

@media (max-width: 991px) {
    .cb-main-wrapper {
        padding: 0 0.8rem;
    }

    .cb-panel-card {
        padding: 1rem;
    }

    .cb-preview-grid {
        grid-template-columns: 1fr;
    }

    .cb-exclusion-item {
        grid-template-columns: 1fr;
    }

    .cb-exec-summary-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767px) {
    .cb-panel-header {
        align-items: stretch;
    }

    .cb-panel-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .cb-list-meta {
        margin-bottom: 0.75rem;
    }

    .cb-meta-chip {
        width: 100%;
        justify-content: space-between;
    }

    .cb-year-filter {
        width: 100%;
    }

    .cb-btn {
        width: 100%;
    }

    .cb-pagination-bar {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }

    .cb-pagination-info {
        width: 100%;
        justify-content: center;
        order: -1;
    }

    .cb-pagination-controls {
        width: 100%;
        justify-content: center;
    }

    .cb-form-grid {
        grid-template-columns: 1fr;
    }

    .cb-target-switch {
        display: grid;
        grid-template-columns: 1fr;
    }

    .cb-select[multiple] {
        min-height: 120px;
    }

    .cb-step-head {
        align-items: flex-start;
    }

    .cb-review-grid {
        flex-direction: column;
    }

    .cb-step-number {
        width: 30px;
        height: 30px;
        font-size: 0.82rem;
    }

    .cb-step-connector {
        width: 26px;
        margin: 0 0.35rem;
    }

    .cb-modal-footer {
        flex-direction: column;
    }

    .cb-modal-footer .cb-btn {
        width: 100%;
    }

    .cb-table thead {
        display: none;
    }

    .cb-table,
    .cb-table tbody,
    .cb-table tr,
    .cb-table td {
        display: block;
        width: 100%;
    }

    .cb-table tr {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        margin-bottom: 0.5rem;
        padding: 0.75rem 0.8rem;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.05);
    }

    .cb-table tr:last-child {
        margin-bottom: 0;
    }

    .cb-table td {
        border: none;
        padding: 0.5rem 0;
        display: grid;
        grid-template-columns: minmax(96px, 112px) minmax(0, 1fr);
        align-items: start;
        gap: 0.75rem;
        text-align: left !important;
    }

    .cb-table td::before {
        content: attr(data-label);
        color: var(--cb-muted);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        text-align: left;
        line-height: 1.45;
        padding-top: 0.15rem;
    }

    .cb-table td > * {
        min-width: 0;
    }

    .cb-table td.text-center,
    .cb-table td .cb-name-cell,
    .cb-table td .cb-time-cell {
        text-align: left !important;
        align-items: flex-start;
    }

    .cb-table td[data-label='Aksi'] {
        grid-template-columns: 1fr;
        gap: 0.45rem;
    }

    .cb-table td[data-label='Aksi']::before {
        padding-top: 0;
    }

    .cb-table td[data-label='Pilih'] {
        grid-template-columns: minmax(96px, 112px) auto;
        align-items: center;
    }

    .cb-action-group {
        justify-content: flex-start;
        flex-wrap: wrap;
        width: 100%;
    }
}

@media (max-width: 420px) {
    .cb-main-wrapper {
        padding: 0 0.55rem;
    }

    .cb-panel-card {
        padding: 0.8rem;
    }

    .cb-pagination-bar {
        padding: 0.8rem;
        gap: 0.8rem;
    }

    .cb-pagination-text {
        font-size: 0.78rem;
    }

    .cb-pagination-btn {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }

    .cb-page-indicator {
        min-width: 50px;
        height: 32px;
        font-size: 0.78rem;
    }

    .cb-detail-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .cb-detail-loader {
        flex-direction: column;
        align-items: flex-start;
    }

    .cb-detail-skeleton-row {
        grid-template-columns: 1fr;
    }

    :deep(.tab-button) {
        font-size: 0.74rem;
        padding: 0.55rem 0.65rem;
    }

    .cb-preview-card {
        padding: 0.7rem 0.5rem;
    }

    .cb-preview-value {
        font-size: 1.22rem;
    }

    .cb-empty-inline {
        padding: 0.85rem 0.7rem;
    }

    .cb-step-label {
        font-size: 0.66rem;
    }
}
</style>

<style scoped>
/* ─── Design Tokens ─── */
:root {
    --kl-bg: var(--hcis-slate-100);
    --kl-surface: var(--hcis-white);
    --kl-border: var(--hcis-slate-200);
    --kl-text: var(--hcis-slate-800);
    --kl-muted: var(--hcis-slate-500);
    --kl-primary: var(--hcis-indigo-500);
    --kl-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    --kl-shadow-md: 0 4px 12px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
    --kl-radius: 16px;
}

/* ─── App Shell ─── */
.kl-app-container {
    background: var(--kl-bg);
    min-height: 100vh;
    padding-bottom: 3rem;
    color: var(--kl-text);
}

/* ─── Stats Row ─── */
.kl-stats-row {
    display: flex;
    gap: 1rem;
    padding: 0 1.5rem 1.25rem;
    flex-wrap: wrap;
}

.kl-stat-card {
    flex: 1 1 180px;
    background: var(--kl-surface);
    border: 1px solid var(--kl-border);
    border-radius: var(--kl-radius);
    box-shadow: var(--kl-shadow-sm);
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    transition:
        box-shadow 0.2s,
        transform 0.2s;
}

.kl-stat-card:hover {
    box-shadow: var(--kl-shadow-md);
    transform: translateY(-2px);
}

.kl-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.kl-accent-indigo .kl-stat-icon {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.kl-accent-amber .kl-stat-icon {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
}
.kl-accent-green .kl-stat-icon {
    background: var(--hcis-green-50);
    color: var(--hcis-green-600);
}

.kl-stat-body {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.kl-stat-label {
    font-size: 0.78rem;
    color: var(--kl-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.kl-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--kl-text);
    line-height: 1;
}

/* ─── Main Wrapper ─── */
/* .kl-main-wrapper {
    padding: 0 1.5rem;
} */

/* ─── Tab Panel ─── */
.kl-tab-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ─── Panel Header ─── */
.kl-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.kl-panel-header-info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.kl-panel-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.kl-icon-primary {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.kl-icon-amber {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
}

.kl-panel-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--kl-text);
    margin: 0;
}

.kl-panel-subtitle {
    margin: 0.15rem 0 0;
    color: var(--kl-muted);
    font-size: 0.87rem;
}

/* ─── Refresh Button ─── */
.kl-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid var(--kl-border);
    background: var(--kl-surface);
    border-radius: 10px;
    padding: 0.5rem 0.9rem;
    color: var(--kl-muted);
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.kl-refresh-btn:hover {
    color: var(--kl-primary);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
    background: #faf9ff;
}

.kl-refresh-btn i {
    font-size: 0.95rem;
}

/* ─── Cards ─── */
.kl-panel-card {
    background: var(--kl-surface);
    border: 1px solid var(--kl-border);
    border-radius: var(--kl-radius);
    box-shadow: var(--kl-shadow-md);
    padding: 1.5rem;
}

.kl-panel-card.kl-no-pad {
    padding: 0;
    overflow: hidden;
}

.kl-filter-card {
    background: var(--kl-surface);
    border: 1px solid var(--kl-border);
    border-radius: var(--kl-radius);
    box-shadow: var(--kl-shadow-sm);
    padding: 1rem 1.25rem;
}

/* ─── Responsive ─── */
@media (max-width: 768px) {
    .kl-stats-row {
        padding: 0 1rem 1rem;
    }
    /* .kl-main-wrapper {
        padding: 0 1rem;
    } */
    .kl-panel-card {
        padding: 1rem;
    }
    .kl-stat-value {
        font-size: 1.5rem;
    }
}

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

*,
*::before,
*::after {
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
    --surface: #ffff;
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
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
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
    content: '';
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

/* Compact toggle (small) */
.toggle-switch-sm {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    cursor: pointer;
}
.toggle-switch-sm input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider-sm {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d1d5db;
    border-radius: 999px;
    transition: background-color 0.3s ease;
}
.slider-sm::before {
    content: '';
    position: absolute;
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
}
.toggle-switch-sm input:checked + .slider-sm {
    background-color: var(--hcis-indigo-600);
}
.toggle-switch-sm input:checked + .slider-sm::before {
    transform: translateX(16px);
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
    background-color: var(--light) !important;
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

    .text-xs {
        font-size: 0.55rem !important;
    }

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
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); /* Primary ring */
    outline: none;
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
    content: '';
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
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .date-nav-left > div:first-child {
        width: 100%;
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
        width: 100%;
        border-radius: 12px;
    }

    .date-nav-wrapper .form-control {
        height: 40px;
        border-radius: 12px;
    }

    .date-nav-wrapper .add-shift-btn {
        height: 42px;
        font-size: 0.9rem;
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
        display: none;
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

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.status-pill-wrap {
    white-space: normal;
    line-height: 1.1;
    text-align: center;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
}

.status-pill.badge {
    border: none;
}

.status-pill.bg-danger-subtle {
    background: #fff1f2 !important;
    color: var(--hcis-rose-600) !important;
    border-color: #fecdd3 !important;
}

.status-pill.bg-success-subtle {
    background: #ecfdf3 !important;
    color: var(--hcis-green-700) !important;
    border-color: var(--hcis-green-200) !important;
}

.shift-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shift-time-pill {
    font-size: 0.55rem;
    padding: 0.2rem 0.45rem;
    border-radius: 0.4rem;
    box-shadow: none;
}

.status-subtext {
    font-size: 0.6rem;
    color: var(--hcis-red-600);
    line-height: 1.1;
    text-align: center;
    max-width: 100%;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-week-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
}

.mobile-day-card {
    min-height: 92px;
}

.mobile-day-card .status-pill {
    font-size: 0.5rem;
    padding: 0.15rem 0.35rem;
}

.mobile-day-card .shift-badge {
    font-size: 0.65rem;
}

@media (max-width: 480px) {
    .mobile-week-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .mobile-day-card {
        min-height: 86px;
        padding: 0.4rem !important;
    }
}

@media (max-width: 640px) {
    .mobile-member-card {
        border-radius: 16px;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }

    .mobile-member-card.is-expanded {
        border-color: #dbeafe;
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.12);
    }

    .mobile-member-header {
        padding: 0.75rem !important;
        gap: 0.75rem !important;
    }

    .mobile-member-header.is-expanded {
        background: var(--hcis-slate-50);
    }

    .mobile-member-avatar {
        display: none !important;
    }

    .mobile-member-info h6 {
        font-size: 0.95rem;
        line-height: 1.2;
    }

    .mobile-member-info {
        flex: 1;
        min-width: 0;
    }

    .mobile-member-info .text-xs {
        font-size: 0.7rem;
        line-height: 1.2;
        margin-top: 0.15rem;
    }

    .mobile-member-divider {
        height: 1px;
        width: 100%;
        background: #e5e7eb;
        margin: 0.45rem 0;
    }

    .mobile-member-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.6rem;
        font-weight: 700;
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 9999px;
        padding: 0.15rem 0.5rem;
        width: fit-content;
    }

    .mobile-toggle-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 9999px;
        background: var(--hcis-slate-50);
        border: 1px solid var(--hcis-slate-200);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }

    .mobile-toggle-btn i {
        font-size: 1.05rem;
        color: var(--hcis-slate-600);
        transition: transform 0.2s ease;
    }

    .mobile-member-card.is-expanded .mobile-toggle-btn i {
        transform: rotate(180deg);
    }

    .mobile-member-card + .mobile-member-card {
        margin-top: 0.75rem;
    }

    .mobile-week-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.6rem;
    }

    .mobile-day-card {
        min-height: 110px;
        padding: 0.6rem !important;
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    }

    .mobile-day-card .text-xxs {
        font-size: 0.6rem;
    }

    .mobile-day-card .shift-badge {
        font-size: 0.7rem;
    }

    .mobile-day-card .status-subtext {
        font-size: 0.55rem;
        line-height: 1.15;
    }
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
    width: 32px;
    height: 32px;
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

.stitch-action-btn.danger {
    border-color: var(--hcis-red-200);
    background: #fddede;
    color: var(--danger);
}

.stitch-action-btn.secondary {
    border-color: #e5e7eb;
    background: #f9fafb;
    color: var(--text-muted);
}

.stitch-action-btn.danger:hover {
    border-color: var(--hcis-red-200);
    background: #fdd0d0;
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

.stitch-badge.cancelled {
    background: #e5e7eb;
    color: #374151;
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
    content: '';
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
/* 
.stitch-btn.primary:hover {
    background: var(--primary-dark);
} */

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
    padding: 0 1.5rem 0 1.25rem;
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
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
        padding: 0;
        margin-top: 1rem;
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
/* STITCH TABLE (UserShift Style + Premium) */
.stitch-table-wrapper {
    overflow-x: auto;
    width: 100%;
    /* border: 1px solid var(--border); */
    border-radius: var(--radius);
    background: white;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.stitch-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.stitch-table th,
.stitch-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.day-col,
.day-cell {
    width: 120px;
    min-width: 120px;
}

.stitch-table th {
    background: var(--surface-soft);
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 1px solid var(--border-dark, var(--hcis-slate-200));
}

.sticky-col {
    position: sticky;
    left: 0;
    background: white;
    z-index: 10;
    border-right: 1px solid var(--border);
    transition: all 0.2s;
}

.stitch-table th.sticky-col {
    z-index: 30; /* Higher than normal th and body sticky col */
    /* background: var(--surface-soft); */
}

.member-row:hover .sticky-col {
    background: var(--surface-soft);
}

@media (max-width: 1200px) {
    .day-col,
    .day-cell {
        width: 105px;
        min-width: 105px;
    }

    .stitch-table th,
    .stitch-table td {
        padding: 0.75rem;
    }
}

@media (max-width: 1024px) {
    .day-col,
    .day-cell {
        width: 96px;
        min-width: 96px;
    }
}

.member-row:hover td {
    background: var(--surface-soft);
}

.member-row:last-child td {
    border-bottom: none;
}

/* Pending Approval Badge in Calendar Cells */
.pending-approval-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.3);
    font-size: 10px;
    font-weight: 600;
    color: var(--hcis-amber-600);
    line-height: 1;
    cursor: default;
    transition: all 0.2s ease;
}

.pending-approval-badge:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.5);
}

.pending-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-amber-500);
    animation: pendingPulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

.pending-text {
    white-space: nowrap;
}

@keyframes pendingPulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.4;
        transform: scale(0.8);
    }
}

/* Shift Cell Interactions */
.shift-cell-hover .add-overlay {
    opacity: 0;
    transform: translate(-50%, -40%);
    transition: all 0.2s ease;
}

.shift-cell-hover:hover .add-overlay {
    opacity: 1;
    transform: translate(-50%, -50%);
}

.shift-cell-hover:hover .empty-placeholder {
    opacity: 0;
}

.shift-content-wrapper {
    border: 1px solid transparent;
}

.shift-cell-hover:hover .shift-content-wrapper {
    background: white;
    border-color: var(--primary-light);
    box-shadow: var(--shadow-sm);
}

/* COLORFUL SHIFT BADGES (userShift.vue style) */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
    color: #5b21b6;
    border: none !important;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    border: none !important;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border) !important;
}

/* COLORFUL SHIFT BADGES (userShift.vue style) */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
    color: #5b21b6;
    border: none !important;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    border: none !important;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border) !important;
}
</style>
