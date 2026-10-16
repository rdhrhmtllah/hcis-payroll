<template>
    <div class="container-fluid px-0">
        <HeaderKpi
            title="Penjadwalan Training"
            subtitle="Kelola penjadwalan training serta periode akses peserta pada paket pembelajaran."
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
            buttonAction
            :buttonText="activeTasksCount > 0 ? 'Menunggu Antrean...' : 'Tambah Penjadwalan'"
            :buttonDisabled="activeTasksCount > 0"
            @button-clicked="activeTasksCount === 0 ? openModal() : null"
        />
        <div class="mt-n4 mb-5 mobile-padding-bottom">
            <div class="card shadow-sm border-0 rounded-4 mb-4 d-none d-md-block" style="background-color: #ffffff">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6
                            class="fw-bolder mb-0 text-dark d-flex align-items-center gap-2"
                            style="font-size: 1rem; letter-spacing: 0.3px"
                        >
                            <i class="bi bi-sliders text-primary"></i> Panel Filter
                            <span v-if="activeFilterCount" class="badge rounded-pill text-bg-primary">
                                {{ activeFilterCount }}
                            </span>
                        </h6>
                        <button
                            v-if="activeFilterCount"
                            type="button"
                            class="btn btn-sm btn-outline-secondary rounded-3 d-flex align-items-center gap-1"
                            @click="resetFilters"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                        </button>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Pencarian</label
                            >
                            <div class="position-relative">
                                <div class="position-absolute top-50 start-0 translate-middle-y ms-3 z-1">
                                    <i class="bi bi-search" style="color: #94a3b8"></i>
                                </div>
                                <input
                                    type="search"
                                    class="form-control custom-premium-input ps-5 w-100"
                                    placeholder="Cari Karyawan, Kode, atau Nama Training..."
                                    v-model="searchQuery"
                                    @input="handleSearch"
                                />
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Lokasi Kerja</label
                            >
                            <el-select
                                v-model="filterLokasi"
                                multiple
                                filterable
                                clearable
                                collapse-tags
                                collapse-tags-tooltip
                                placeholder="Pilih Lokasi Kerja..."
                                class="w-100 elegant-el-select"
                                @change="handleSearch"
                            >
                                <el-option
                                    v-for="item in listLokasi"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                ></el-option>
                            </el-select>
                        </div>

                        <!-- Filter Paket Training sementara dinonaktifkan (aktifkan lagi bila diperlukan)
                        <div class="col-12 col-md-6">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Paket Training</label
                            >
                            <el-select
                                v-model="filterMateri"
                                multiple
                                filterable
                                clearable
                                collapse-tags
                                collapse-tags-tooltip
                                placeholder="Pilih Paket Training..."
                                class="w-100 elegant-el-select"
                                @change="handleSearch"
                            >
                                <el-option
                                    v-for="item in listTraining"
                                    :key="item.Id_Master_Paket"
                                    :label="item.Nama_Paket"
                                    :value="item.Id_Master_Paket"
                                ></el-option>
                            </el-select>
                        </div>
                        -->

                        <div class="col-12 col-md-6">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Module / Materi</label
                            >
                            <el-select
                                v-model="filterModule"
                                multiple
                                filterable
                                clearable
                                collapse-tags
                                collapse-tags-tooltip
                                placeholder="Pilih Module..."
                                class="w-100 elegant-el-select"
                                @change="handleSearch"
                            >
                                <el-option
                                    v-for="item in moduleOptions"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                ></el-option>
                            </el-select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Status Akses</label
                            >
                            <el-select
                                v-model="filterStatusAkses"
                                placeholder="Pilih Status Akses..."
                                class="w-100 elegant-el-select"
                                @change="handleSearch"
                            >
                                <el-option label="Semua Status Akses" value="all"></el-option>
                                <el-option label="Akses Aktif (Belum Dicabut)" value="T"></el-option>
                                <el-option label="Akses Telah Dicabut" value="Y"></el-option>
                            </el-select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Mode Tanggal</label
                            >
                            <el-select
                                v-model="filterDateMode"
                                placeholder="Pilih Mode Tanggal..."
                                class="w-100 elegant-el-select"
                                @change="filterDateRange && filterDateRange.length ? handleSearch() : null"
                            >
                                <el-option label="Tanggal Enroll" value="enroll"></el-option>
                                <el-option label="Tanggal Dibuat" value="created"></el-option>
                            </el-select>
                        </div>

                        <div class="col-12">
                            <label
                                class="form-label fw-bold text-muted small mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                                >Rentang Tanggal</label
                            >
                            <el-date-picker
                                v-model="filterDateRange"
                                type="daterange"
                                range-separator="s/d"
                                start-placeholder="Tanggal Awal"
                                end-placeholder="Tanggal Akhir"
                                value-format="YYYY-MM-DD"
                                format="DD MMM YYYY"
                                class="w-100"
                                clearable
                                @change="handleSearch"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div
                        v-if="listData.length > 0 && !loading.listData"
                        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3 px-md-4 mb-4 rounded-4 transition-all"
                        :style="
                            selectedJadwal.length > 0
                                ? 'background-color: #eef2ff; border: 1px solid #c7d2fe; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.08);'
                                : 'background-color: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);'
                        "
                    >
                        <div class="d-flex align-items-center gap-3 mb-3 mb-md-0">
                            <div class="form-check m-0 d-flex align-items-center custom-checkbox-wrapper">
                                <input
                                    class="form-check-input cursor-pointer shadow-none premium-checkbox"
                                    type="checkbox"
                                    id="checkAllJadwal"
                                    :checked="isAllJadwalSelected"
                                    :indeterminate.prop="isIndeterminateJadwal"
                                    @change="handleSelectAll($event.target.checked)"
                                />
                                <label
                                    class="form-check-label fw-bold ms-2 cursor-pointer user-select-none"
                                    for="checkAllJadwal"
                                    :style="selectedJadwal.length > 0 ? 'color: #4338ca;' : 'color: #334155;'"
                                    style="font-size: 0.95rem"
                                >
                                    Pilih Semua
                                    <span
                                        class="fw-medium ms-1"
                                        :style="selectedJadwal.length > 0 ? 'color: #6366f1;' : 'color: #94a3b8;'"
                                        style="font-size: 0.85rem"
                                    >
                                        (Semua Halaman)
                                    </span>
                                </label>
                            </div>

                            <transition name="fade">
                                <div
                                    v-if="selectedJadwal.length > 0"
                                    class="d-flex align-items-center text-white rounded-pill px-3 py-1 shadow-sm"
                                    style="
                                        background-color: #4f46e5;
                                        font-size: 0.8rem;
                                        font-weight: 600;
                                        letter-spacing: 0.5px;
                                    "
                                >
                                    <i class="bi bi-check2-circle me-1" style="font-size: 0.9rem; margin-top: -4px"></i>
                                    {{ selectedJadwal.length }} Terpilih
                                </div>
                            </transition>

                            <div
                                v-if="isFetchingAllIds"
                                class="spinner-border spinner-border-sm text-primary ms-2"
                                role="status"
                            >
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <transition name="slide-fade">
                            <div v-if="selectedJadwal.length > 0" class="d-flex gap-2">
                                <button
                                    @click="processBulkAction('Y')"
                                    :disabled="isBulkLoading"
                                    class="btn btn-sm px-4 py-2 fw-bold d-flex align-items-center gap-2 premium-action-btn btn-danger-soft"
                                >
                                    <i class="bi bi-shield-lock-fill"></i> Cabut Akses
                                </button>
                                <button
                                    @click="processBulkAction('T')"
                                    :disabled="isBulkLoading"
                                    class="btn btn-sm px-4 py-2 fw-bold d-flex align-items-center gap-2 premium-action-btn btn-success-soft"
                                >
                                    <i class="bi bi-shield-check"></i> Buka Akses
                                </button>
                            </div>
                        </transition>
                    </div>

                    <div class="mt-3">
                        <ListAccordionV1
                            v-if="!loading.listData"
                            accordionId="formulaKpiAccordion"
                            itemKey="Id_Penjadwalan"
                            :items="listData"
                            :pagination="pagination"
                            :loading="loading.listData"
                            @change-page="changePage"
                        >
                            <template #header="{ item: jadwal }">
                                <div
                                    class="custom-accordion-header w-100 d-flex justify-content-between align-items-center px-4 py-3"
                                >
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="form-check" @click.stop>
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :value="jadwal.Id_Penjadwalan"
                                                v-model="selectedJadwal"
                                                style="width: 1.2rem; height: 1.2rem; cursor: pointer"
                                            />
                                        </div>
                                        <div
                                            class="header-avatar d-flex justify-content-center align-items-center flex-shrink-0"
                                            style="
                                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                                width: 45px;
                                                height: 45px;
                                                border-radius: 10px;
                                                box-shadow: 0 4px 10px rgba(118, 75, 162, 0.2);
                                            "
                                        >
                                            <i class="bi bi-person text-white fs-4"></i>
                                        </div>

                                        <div class="text-start">
                                            <h5
                                                class="mb-1 fw-bold text-dark"
                                                style="font-size: 1.05rem; letter-spacing: -0.2px"
                                            >
                                                {{ jadwal.Nama_Karyawan }}
                                            </h5>

                                            <div class="d-flex align-items-center flex-wrap gap-2">
                                                <div class="small fw-medium" style="color: #64748b">
                                                    <span style="color: #667eea">
                                                        <i class="bi bi-journal-bookmark-fill me-1"></i
                                                        >{{ jadwal.Nama_Paket }}
                                                    </span>
                                                </div>

                                                <span class="text-muted" style="font-size: 0.5rem">&#9679;</span>

                                                <div
                                                    v-if="jadwal.Flag_Cabut_Akses === 'Y'"
                                                    class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 rounded-pill"
                                                    style="font-size: 0.75rem; font-weight: 500"
                                                >
                                                    <i class="bi bi-shield-lock-fill me-1"></i> Akses Telah Dicabut
                                                </div>
                                                <div
                                                    v-else
                                                    class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill"
                                                    style="font-size: 0.75rem; font-weight: 500"
                                                >
                                                    <i class="bi bi-shield-check me-1"></i> Akses Aktif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ps-4 border-start border-secondary-subtle d-none d-md-block">
                                        <div class="d-flex align-items-start gap-4">
                                            <div class="text-end">
                                                <div
                                                    class="text-muted fw-bold mb-1"
                                                    style="
                                                        font-size: 0.65rem;
                                                        letter-spacing: 1px;
                                                        text-transform: uppercase;
                                                    "
                                                >
                                                    <i class="bi bi-clock-history me-1"></i> WAKTU ENROLL
                                                </div>
                                                <div class="text-dark fw-bold" style="font-size: 0.9rem">
                                                    {{ formatDateTimeFull(jadwal.Waktu_Enroll) }}
                                                </div>
                                            </div>
                                            <div class="text-end border-start border-secondary-subtle ps-4">
                                                <div
                                                    class="text-muted fw-bold mb-1"
                                                    style="
                                                        font-size: 0.65rem;
                                                        letter-spacing: 1px;
                                                        text-transform: uppercase;
                                                    "
                                                >
                                                    <i class="bi bi-calendar-check me-1"></i> DITUGASKAN
                                                </div>
                                                <div class="text-dark fw-bold" style="font-size: 0.9rem">
                                                    {{
                                                        jadwal.Created_At ? formatDateTimeFull(jadwal.Created_At) : '-'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="small text-muted text-end mt-1" style="font-size: 0.75rem">
                                            Assign by: <span class="fw-bold text-dark">{{ jadwal.Asign_By }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template #body="{ item: jadwal }">
                                <div
                                    class="p-3 p-md-4 bg-white"
                                    style="border-top: 1px solid #f1f5f9; max-height: 600px; overflow-y: auto"
                                >
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-3 responsive-action-header"
                                    >
                                        <div
                                            v-if="jadwal.Flag_Cabut_Akses === 'Y'"
                                            class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill align-self-start"
                                        >
                                            <i class="bi bi-shield-lock-fill me-1"></i> Akses Telah Dicabut
                                        </div>
                                        <div
                                            v-else
                                            class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill align-self-start"
                                        >
                                            <i class="bi bi-shield-check me-1"></i> Akses Aktif
                                        </div>

                                        <div class="d-flex gap-2 responsive-action-buttons">
                                            <button
                                                @click="openModal(jadwal)"
                                                class="btn btn-sm px-4 fw-bold shadow-sm"
                                                style="
                                                    background-color: #eef2ff;
                                                    color: #4f46e5;
                                                    border: 1px solid #e0e7ff;
                                                    border-radius: 8px;
                                                    transition: all 0.2s;
                                                "
                                            >
                                                <i class="bi bi-pencil-square me-2"></i>Edit Penjadwalan
                                            </button>

                                            <button
                                                v-if="jadwal.Flag_Selesai === 'T'"
                                                @click="toggleCabutAkses(jadwal)"
                                                class="btn btn-sm px-4 fw-bold shadow-sm text-white"
                                                :class="
                                                    jadwal.Flag_Cabut_Akses === 'Y'
                                                        ? 'bg-success border-success'
                                                        : 'bg-danger border-danger'
                                                "
                                                style="border-radius: 8px; transition: all 0.2s"
                                            >
                                                <i
                                                    :class="
                                                        jadwal.Flag_Cabut_Akses === 'Y'
                                                            ? 'bi bi-unlock-fill me-2'
                                                            : 'bi bi-lock-fill me-2'
                                                    "
                                                ></i>
                                                {{ jadwal.Flag_Cabut_Akses === 'Y' ? 'Buka Akses' : 'Cabut Akses' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        class="mb-4 p-3 rounded-4"
                                        style="background-color: #f8fafc; border: 1px solid #e2e8f0"
                                    >
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6 border-end-md border-secondary-subtle">
                                                <div
                                                    style="
                                                        font-size: 0.7rem;
                                                        color: #64748b;
                                                        font-weight: 700;
                                                        letter-spacing: 1px;
                                                        text-transform: uppercase;
                                                        margin-bottom: 0.4rem;
                                                    "
                                                >
                                                    RUMUS
                                                </div>
                                                <div
                                                    class="font-monospace fw-bold"
                                                    style="color: #4f46e5; font-size: 0.95rem; word-break: break-word"
                                                >
                                                    {{ jadwal.Rumus && jadwal.Rumus !== '-' ? jadwal.Rumus : '-' }}
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6 ps-md-4">
                                                <div
                                                    style="
                                                        font-size: 0.7rem;
                                                        color: #64748b;
                                                        font-weight: 700;
                                                        letter-spacing: 1px;
                                                        text-transform: uppercase;
                                                        margin-bottom: 0.4rem;
                                                    "
                                                >
                                                    DESKRIPSI FORMULA
                                                </div>
                                                <div class="text-dark fw-medium" style="font-size: 0.9rem">
                                                    {{
                                                        jadwal.Deskripsi_Formula && jadwal.Deskripsi_Formula !== '-'
                                                            ? jadwal.Deskripsi_Formula
                                                            : 'Tidak ada deskripsi'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div
                                            class="clean-section-title mb-0"
                                            style="
                                                font-size: 0.8rem;
                                                font-weight: 700;
                                                color: #64748b;
                                                letter-spacing: 1px;
                                                text-transform: uppercase;
                                            "
                                        >
                                            <i class="bi bi-layers-fill me-1" style="color: #667eea"></i> DAFTAR
                                            AKTIVITAS & MODUL
                                        </div>
                                        <div class="d-flex d-md-none gap-3 text-end small">
                                            <div>
                                                <span class="text-muted d-block">Enroll:</span>
                                                <span class="fw-bold">{{
                                                    formatDateTimeFull(jadwal.Waktu_Enroll)
                                                }}</span>
                                            </div>
                                            <div class="border-start ps-3">
                                                <span class="text-muted d-block">Ditugaskan:</span>
                                                <span class="fw-bold">{{
                                                    jadwal.Created_At ? formatDateTimeFull(jadwal.Created_At) : '-'
                                                }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="jadwal.Training_List && jadwal.Training_List.length > 0">
                                        <div
                                            v-for="(training, tIndex) in jadwal.Training_List"
                                            :key="jadwal.Id_Penjadwalan + '-t-' + tIndex"
                                        >
                                            <div
                                                v-for="(sub, sIndex) in training.materi_detail"
                                                :key="jadwal.Id_Penjadwalan + '-s-' + sIndex"
                                                class="mb-4"
                                            >
                                                <div
                                                    class="bg-white rounded-4 shadow-sm"
                                                    style="border: 1px solid #e2e8f0; overflow: hidden"
                                                >
                                                    <div
                                                        class="px-4 py-3"
                                                        style="
                                                            background-color: #f8fafc;
                                                            border-bottom: 1px solid #e2e8f0;
                                                        "
                                                    >
                                                        <h6
                                                            class="mb-0 fw-bold text-dark d-flex align-items-center gap-2"
                                                            style="font-size: 1rem"
                                                        >
                                                            <i class="bi bi-bookmark-star-fill text-primary"></i>
                                                            {{ sub.Nama_Sub }}
                                                        </h6>
                                                    </div>

                                                    <div class="d-flex flex-column">
                                                        <template
                                                            v-for="(act, actIndex) in sub.materi_det"
                                                            :key="act.Id_Penjadwalan_Det"
                                                        >
                                                            <div
                                                                v-if="act.Kode_Aktivitas !== 'MDL'"
                                                                class="p-3 px-3 px-md-4 d-flex align-items-start gap-3 w-100"
                                                                :style="
                                                                    actIndex < sub.materi_det.length - 1 ||
                                                                    act.Kode_Aktivitas === 'MDL'
                                                                        ? 'border-bottom: 1px solid #f1f5f9;'
                                                                        : ''
                                                                "
                                                            >
                                                                <div
                                                                    class="d-flex justify-content-center align-items-center flex-shrink-0 fw-bold shadow-sm text-dark"
                                                                    :class="getDynamicBg(act.Kode_Aktivitas, null)"
                                                                    style="
                                                                        width: 45px;
                                                                        height: 45px;
                                                                        border-radius: 12px;
                                                                        font-size: 0.85rem;
                                                                        letter-spacing: 0.5px;
                                                                    "
                                                                >
                                                                    <span>{{ act.Kode_Aktivitas }}</span>
                                                                </div>

                                                                <div class="flex-grow-1 w-100" style="min-width: 0">
                                                                    <h6
                                                                        class="mb-1 fw-bold text-dark text-truncate"
                                                                        style="font-size: 0.95rem"
                                                                    >
                                                                        {{ act.Nama_Aktivitas }}
                                                                    </h6>
                                                                    <div
                                                                        class="text-secondary fw-medium d-flex align-items-center flex-wrap gap-1"
                                                                        style="font-size: 0.8rem"
                                                                    >
                                                                        <i class="bi bi-clock text-muted"></i>
                                                                        <span
                                                                            v-if="
                                                                                act.Flag_Third_Party === 'Y' &&
                                                                                act.third_party_info
                                                                            "
                                                                        >
                                                                            Mulai:
                                                                            <span class="fw-bold text-dark">{{
                                                                                formatDateTime(
                                                                                    act.third_party_info.Waktu_Mulai,
                                                                                )
                                                                            }}</span>
                                                                            <span
                                                                                class="mx-1 text-muted d-none d-md-inline"
                                                                                >&rarr;</span
                                                                            >
                                                                            <br class="d-md-none" />
                                                                            Akhir:
                                                                            <span class="fw-bold text-dark">{{
                                                                                formatDateTime(
                                                                                    act.third_party_info.Waktu_Akhir,
                                                                                )
                                                                            }}</span>
                                                                        </span>
                                                                        <span v-else>
                                                                            {{
                                                                                formatDateTimeFull(
                                                                                    act.Waktu_Mulai_Akses,
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </div>

                                                                    <div
                                                                        v-if="
                                                                            act.Flag_Third_Party === 'Y' &&
                                                                            act.third_party_info
                                                                        "
                                                                        class="mt-2 p-2 rounded-3"
                                                                        style="
                                                                            background-color: #f8fafc;
                                                                            border: 1px dashed #cbd5e1;
                                                                        "
                                                                    >
                                                                        <div
                                                                            class="d-flex flex-wrap align-items-center gap-2 responsive-third-party"
                                                                        >
                                                                            <div
                                                                                v-if="act.third_party_info.Token"
                                                                                class="d-flex align-items-stretch border rounded-2 third-party-item"
                                                                                style="
                                                                                    background-color: #fff;
                                                                                    height: 32px;
                                                                                    overflow: hidden;
                                                                                "
                                                                            >
                                                                                <span
                                                                                    class="d-flex align-items-center px-2 bg-light text-secondary fw-bold border-end flex-shrink-0"
                                                                                    style="font-size: 0.65rem"
                                                                                    >TOKEN</span
                                                                                >
                                                                                <span
                                                                                    class="d-flex align-items-center px-2 fw-bold text-dark flex-grow-1"
                                                                                    style="
                                                                                        font-family: monospace;
                                                                                        font-size: 0.8rem;
                                                                                        letter-spacing: 1px;
                                                                                    "
                                                                                >
                                                                                    {{
                                                                                        act.third_party_info.Token
                                                                                            ? maskText(
                                                                                                  act.third_party_info
                                                                                                      .Token,
                                                                                              )
                                                                                            : '-'
                                                                                    }}
                                                                                </span>
                                                                                <button
                                                                                    @click="
                                                                                        copyToClipboard(
                                                                                            act.third_party_info.Token,
                                                                                            'Token',
                                                                                        )
                                                                                    "
                                                                                    class="btn btn-sm btn-light border-start text-primary px-3 d-flex align-items-center flex-shrink-0"
                                                                                >
                                                                                    <i
                                                                                        class="bi bi-copy"
                                                                                        style="font-size: 0.75rem"
                                                                                    ></i>
                                                                                </button>
                                                                            </div>
                                                                            <div
                                                                                v-if="act.third_party_info.Otp"
                                                                                class="d-flex align-items-stretch border rounded-2 third-party-item"
                                                                                style="
                                                                                    background-color: #fff;
                                                                                    height: 32px;
                                                                                    overflow: hidden;
                                                                                "
                                                                            >
                                                                                <span
                                                                                    class="d-flex align-items-center px-2 bg-light text-secondary fw-bold border-end flex-shrink-0"
                                                                                    style="font-size: 0.65rem"
                                                                                    >OTP</span
                                                                                >
                                                                                <span
                                                                                    class="d-flex align-items-center px-2 fw-bold text-dark flex-grow-1"
                                                                                    style="
                                                                                        font-family: monospace;
                                                                                        font-size: 0.8rem;
                                                                                        letter-spacing: 1px;
                                                                                    "
                                                                                >
                                                                                    {{
                                                                                        act.third_party_info.Otp
                                                                                            ? maskText(
                                                                                                  act.third_party_info
                                                                                                      .Otp,
                                                                                              )
                                                                                            : '***'
                                                                                    }}
                                                                                </span>
                                                                                <button
                                                                                    @click="
                                                                                        copyToClipboard(
                                                                                            act.third_party_info.Otp,
                                                                                            'OTP',
                                                                                        )
                                                                                    "
                                                                                    class="btn btn-sm btn-light border-start text-primary px-3 d-flex align-items-center flex-shrink-0"
                                                                                >
                                                                                    <i
                                                                                        class="bi bi-copy"
                                                                                        style="font-size: 0.75rem"
                                                                                    ></i>
                                                                                </button>
                                                                            </div>
                                                                            <div
                                                                                class="d-flex flex-wrap align-items-center gap-2 responsive-third-party"
                                                                            >
                                                                                <button
                                                                                    class="btn btn-sm px-3 fw-bold rounded-2 d-flex align-items-center justify-content-center gap-1 ms-auto third-party-btn"
                                                                                    style="
                                                                                        height: 32px;
                                                                                        background-color: #fff1f2;
                                                                                        color: #e11d48;
                                                                                        border: 1px solid #fecdd3;
                                                                                    "
                                                                                    @click="
                                                                                        openReassignModal(
                                                                                            jadwal,
                                                                                            sub,
                                                                                            act,
                                                                                        )
                                                                                    "
                                                                                >
                                                                                    <i
                                                                                        class="bi bi-arrow-clockwise"
                                                                                        style="
                                                                                            font-size: 1rem;
                                                                                            margin-top: -1px;
                                                                                        "
                                                                                    ></i>
                                                                                    Ajukan Ulang Ujian
                                                                                </button>

                                                                                <button
                                                                                    v-if="act.third_party_info.Link"
                                                                                    class="btn btn-sm px-3 fw-bold rounded-2 d-flex align-items-center justify-content-center gap-1 third-party-btn"
                                                                                    style="
                                                                                        height: 32px;
                                                                                        background-color: #eef2ff;
                                                                                        color: #4f46e5;
                                                                                        border: 1px solid #e0e7ff;
                                                                                    "
                                                                                    @click="
                                                                                        copyToClipboard(
                                                                                            act.third_party_info.Link,
                                                                                            'Link Ujian',
                                                                                        )
                                                                                    "
                                                                                >
                                                                                    <i
                                                                                        class="bi bi-link-45deg"
                                                                                        style="
                                                                                            font-size: 1rem;
                                                                                            margin-top: -1px;
                                                                                        "
                                                                                    ></i>
                                                                                    Salin Link
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <template v-else>
                                                                <div
                                                                    v-for="(detail, detIndex) in act.Details"
                                                                    :key="`mdl-${actIndex}-${detIndex}`"
                                                                    class="p-3 px-3 px-md-4 d-flex align-items-start gap-3 w-100"
                                                                    :style="
                                                                        detIndex < act.Details.length - 1 ||
                                                                        actIndex < sub.materi_det.length - 1
                                                                            ? 'border-bottom: 1px solid #f1f5f9;'
                                                                            : ''
                                                                    "
                                                                >
                                                                    <div
                                                                        class="d-flex justify-content-center align-items-center flex-shrink-0 fw-bold shadow-sm text-dark"
                                                                        :class="getDynamicBg('MDL', detail.Tipe_Konten)"
                                                                        style="
                                                                            width: 45px;
                                                                            height: 45px;
                                                                            border-radius: 12px;
                                                                        "
                                                                    >
                                                                        <i
                                                                            :class="getDynamicIcon(detail.Tipe_Konten)"
                                                                            style="font-size: 1.25rem"
                                                                        ></i>
                                                                    </div>
                                                                    <div class="flex-grow-1 w-100" style="min-width: 0">
                                                                        <h6
                                                                            class="mb-1 fw-bold text-dark lh-base text-truncate"
                                                                            style="font-size: 0.95rem"
                                                                        >
                                                                            {{ detail.Nama_Modul }}
                                                                        </h6>
                                                                        <div
                                                                            class="text-secondary fw-medium d-flex align-items-center gap-2"
                                                                            style="font-size: 0.8rem"
                                                                        >
                                                                            <i class="bi bi-clock text-muted"></i>
                                                                            <span>{{
                                                                                formatDateTimeFull(
                                                                                    act.Waktu_Mulai_Akses,
                                                                                )
                                                                            }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="text-center py-5 bg-light rounded-4 border border-dashed">
                                        <i class="bi bi-inbox fs-1 text-secondary mb-3 d-block"></i>
                                        <h6 class="fw-bold text-muted">Belum ada aktivitas pada penjadwalan ini</h6>
                                    </div>
                                </div>
                            </template>
                        </ListAccordionV1>

                        <div v-else>
                            <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <el-dialog
            v-model="showReassignModal"
            :title="`Ajukan Ulang Ujian - ${reassignData.namaAktivitas}`"
            width="550px"
            center
            align-center
            class="custom-modal-reassign shadow-lg border-0 rounded-4 overflow-hidden"
            :close-on-click-modal="false"
        >
            <div
                style="
                    height: 4px;
                    background: linear-gradient(90deg, #4f46e5, #ec4899);
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                "
            ></div>

            <div class="p-2" v-loading="loading.listDataMasterUjian">
                <div
                    class="d-flex align-items-start gap-3 mb-4 p-3 rounded-4"
                    style="background-color: #fff1f2; border: 1px solid #fecdd3"
                >
                    <div
                        class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                        style="width: 36px; height: 36px; background-color: #ffe4e6; color: #e11d48"
                    >
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-danger mb-1" style="font-size: 0.9rem">Perhatian!</h6>
                        <p class="mb-0 text-danger" style="font-size: 0.8rem; line-height: 1.5; opacity: 0.9">
                            Tindakan ini akan membatalkan sesi ujian saat ini dan membuat <b>Token/OTP baru</b> untuk
                            Karyawan <b class="fw-bolder">{{ reassignData.namaKaryawan }}</b
                            >.
                        </p>
                    </div>
                </div>

                <div class="mb-4">
                    <label
                        class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-2"
                        style="font-size: 0.85rem"
                    >
                        <i class="bi bi-journal-text text-primary"></i> Pilih Paket Soal Baru
                    </label>

                    <el-select
                        v-model="reassignData.idPaketSoal"
                        filterable
                        remote
                        reserve-keyword
                        :remote-method="handleSoalRemoteSearch"
                        :loading="loading.listDataMasterUjian"
                        clearable
                        :placeholder="'Pilih Soal untuk ' + reassignData.namaAktivitas + '...'"
                        class="w-100 elegant-select select-warning bg-white rounded-3"
                        size="large"
                    >
                        <el-option
                            v-for="item in listDataMasterUjian"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value"
                            style="height: auto; padding: 0; margin-bottom: 5px"
                        >
                            <div style="padding: 12px 16px; border-bottom: 1px solid #eef1f6">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div style="flex: 1; padding-right: 10px">
                                        <div
                                            style="
                                                font-size: 14px;
                                                font-weight: 700;
                                                color: #2c3e50;
                                                line-height: 1.4;
                                                white-space: normal;
                                            "
                                        >
                                            {{ item.raw.Nama_Ujian ?? '-' }}
                                        </div>
                                    </div>
                                    <span
                                        class="badge"
                                        :class="item.raw.Tingkatan === 'STAFF' ? 'bg-info' : 'bg-primary'"
                                        style="
                                            font-size: 10px;
                                            padding: 4px 8px;
                                            border-radius: 4px;
                                            font-weight: 600;
                                            white-space: nowrap;
                                        "
                                    >
                                        {{ item.raw.Tingkatan }}
                                    </span>
                                </div>
                                <div
                                    class="d-inline-flex align-items-center"
                                    style="
                                        background: #f8f9fa;
                                        padding: 4px 8px;
                                        border-radius: 6px;
                                        border: 1px dashed #dcdfe6;
                                    "
                                >
                                    <span
                                        class="me-2"
                                        style="
                                            font-size: 10px;
                                            color: #909399;
                                            font-weight: 600;
                                            font-family: monospace;
                                        "
                                        >KODE UJIAN:</span
                                    >
                                    <span
                                        style="
                                            font-weight: 700;
                                            font-size: 11px;
                                            color: #409eff;
                                            font-family: monospace;
                                        "
                                    >
                                        {{ item.raw.Kode_Ujian }}
                                    </span>
                                </div>
                                <div
                                    class="d-inline-flex align-items-center ms-2"
                                    style="
                                        background: #fdf6ec;
                                        padding: 4px 8px;
                                        border-radius: 6px;
                                        border: 1px dashed #faecd8;
                                    "
                                >
                                    <span
                                        class="me-2"
                                        style="
                                            font-size: 10px;
                                            color: #e6a23c;
                                            font-weight: 600;
                                            font-family: monospace;
                                        "
                                        >KODE PAKET:</span
                                    >
                                    <span
                                        style="
                                            font-weight: 700;
                                            font-size: 11px;
                                            color: #e6a23c;
                                            font-family: monospace;
                                        "
                                    >
                                        {{ item.raw.Kode_Paket }}
                                    </span>
                                </div>
                            </div>
                        </el-option>
                    </el-select>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6">
                        <label
                            class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-2"
                            style="font-size: 0.85rem"
                        >
                            <i class="bi bi-calendar-play text-primary"></i> Waktu Mulai Baru
                        </label>
                        <el-date-picker
                            v-model="reassignData.waktuMulai"
                            type="datetime"
                            placeholder="Pilih Waktu Mulai"
                            class="w-100"
                            format="DD MMM YYYY, HH:mm"
                            value-format="YYYY-MM-DD HH:mm:ss"
                        />
                    </div>
                    <div class="col-12 col-sm-6">
                        <label
                            class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-2"
                            style="font-size: 0.85rem"
                        >
                            <i class="bi bi-calendar-x text-danger"></i> Waktu Akhir Baru
                        </label>
                        <el-date-picker
                            v-model="reassignData.waktuAkhir"
                            type="datetime"
                            format="DD MMM YYYY HH:mm:ss"
                            value-format="YYYY-MM-DD HH:mm:ss"
                            :disabled-date="disabledDateReassignAkhir"
                            placeholder="Pilih Waktu Akhir"
                        />
                    </div>
                </div>

                <div
                    class="p-3 rounded-4 d-flex justify-content-between align-items-center"
                    style="background-color: #f8fafc; border: 1px dashed #cbd5e1"
                >
                    <div class="d-flex flex-column">
                        <label
                            class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1"
                            style="font-size: 0.9rem"
                        >
                            <i
                                class="bi bi-camera-video-fill"
                                :class="reassignData.flagCamera === 'Y' ? 'text-success' : 'text-secondary'"
                            ></i>
                            Aktifkan Kamera Peserta
                        </label>
                        <span class="text-muted" style="font-size: 0.75rem">
                            Wajibkan peserta menyalakan kamera saat ujian berlangsung.
                        </span>
                    </div>
                    <div>
                        <el-switch
                            v-model="reassignData.flagCamera"
                            inline-prompt
                            active-text="Ya"
                            inactive-text="Tidak"
                            true-value="Y"
                            false-value="T"
                            style="--el-switch-on-color: #10b981; --el-switch-off-color: #94a3b8"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div
                    class="dialog-footer d-flex justify-content-end gap-2 p-2 border-top bg-light"
                    style="margin: -20px; padding: 15px 20px !important"
                >
                    <el-button @click="showReassignModal = false" class="fw-bold rounded-3 px-4 shadow-sm border-0"
                        >Batal</el-button
                    >
                    <el-button
                        type="primary"
                        @click="submitReassign"
                        :loading="loading.reassign"
                        class="fw-bold rounded-3 px-4 shadow-sm"
                        style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); border: none"
                    >
                        <i class="bi bi-send-check me-2" v-if="!loading.reassign"></i> Kirim Ulang Akses
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <button
            class="btn btn-primary rounded-circle shadow-lg d-flex d-md-none align-items-center justify-content-center floating-filter-btn"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileFilterOffcanvas"
            aria-controls="mobileFilterOffcanvas"
        >
            <i class="bi bi-funnel-fill text-white fs-5"></i>
        </button>

        <div
            class="offcanvas offcanvas-bottom rounded-top-4 border-0 shadow-lg"
            tabindex="-1"
            id="mobileFilterOffcanvas"
            style="height: auto; max-height: 85vh"
        >
            <div class="offcanvas-header border-bottom border-light px-4 py-3">
                <h6 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-funnel text-primary"></i> Filter Penjadwalan
                    <span v-if="activeFilterCount" class="badge rounded-pill text-bg-primary">
                        {{ activeFilterCount }}
                    </span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <button
                        v-if="activeFilterCount"
                        type="button"
                        class="btn btn-sm btn-outline-secondary rounded-3"
                        @click="resetFilters"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                    <button
                        type="button"
                        class="btn-close shadow-none"
                        data-bs-dismiss="offcanvas"
                        aria-label="Close"
                    ></button>
                </div>
            </div>
            <div class="offcanvas-body d-flex flex-column gap-4 p-4">
                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                    >
                        Pencarian
                    </label>
                    <div class="position-relative">
                        <div class="position-absolute top-50 start-0 translate-middle-y ms-3 z-1">
                            <i class="bi bi-search" style="color: #94a3b8"></i>
                        </div>
                        <input
                            type="search"
                            class="form-control custom-premium-input ps-5 w-100"
                            placeholder="Cari Karyawan, Kode, atau Training..."
                            v-model="searchQuery"
                            @input="handleSearch"
                        />
                    </div>
                </div>

                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                        >Lokasi Kerja</label
                    >
                    <el-select
                        v-model="filterLokasi"
                        multiple
                        filterable
                        clearable
                        collapse-tags
                        collapse-tags-tooltip
                        placeholder="Pilih Lokasi Kerja"
                        class="w-100 elegant-el-select"
                        size="large"
                        @change="handleSearch"
                    >
                        <el-option
                            v-for="item in listLokasi"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value"
                        ></el-option>
                    </el-select>
                </div>

                <!-- Filter Paket Training sementara dinonaktifkan (aktifkan lagi bila diperlukan)
                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                        >Paket Training</label
                    >
                    <el-select
                        v-model="filterMateri"
                        multiple
                        filterable
                        clearable
                        collapse-tags
                        collapse-tags-tooltip
                        placeholder="Pilih Paket Training"
                        class="w-100 elegant-el-select"
                        size="large"
                        @change="handleSearch"
                    >
                        <el-option
                            v-for="item in listTraining"
                            :key="item.Id_Master_Paket"
                            :label="item.Nama_Paket"
                            :value="item.Id_Master_Paket"
                        ></el-option>
                    </el-select>
                </div>
                -->

                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                        >Module / Materi</label
                    >
                    <el-select
                        v-model="filterModule"
                        multiple
                        filterable
                        clearable
                        collapse-tags
                        collapse-tags-tooltip
                        placeholder="Pilih Module"
                        class="w-100 elegant-el-select"
                        size="large"
                        @change="handleSearch"
                    >
                        <el-option
                            v-for="item in moduleOptions"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value"
                        ></el-option>
                    </el-select>
                </div>

                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                        >Status Akses</label
                    >
                    <select
                        class="form-select custom-premium-input py-2"
                        v-model="filterStatusAkses"
                        @change="handleSearch"
                    >
                        <option value="all">Semua Status Akses</option>
                        <option value="T">Akses Aktif (Belum Dicabut)</option>
                        <option value="Y">Akses Telah Dicabut</option>
                    </select>
                </div>

                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                        >Mode Tanggal</label
                    >
                    <select
                        class="form-select custom-premium-input py-2"
                        v-model="filterDateMode"
                        @change="filterDateRange && filterDateRange.length ? handleSearch() : null"
                    >
                        <option value="enroll">Tanggal Enroll</option>
                        <option value="created">Tanggal Dibuat</option>
                    </select>
                </div>

                <div>
                    <label
                        class="form-label fw-bolder text-uppercase text-muted mb-2"
                        style="font-size: 0.75rem; letter-spacing: 1px"
                        >Rentang Tanggal</label
                    >
                    <el-date-picker
                        v-model="filterDateRange"
                        type="daterange"
                        size="large"
                        range-separator="s/d"
                        start-placeholder="Tanggal Awal"
                        end-placeholder="Tanggal Akhir"
                        value-format="YYYY-MM-DD"
                        format="DD MMM YYYY"
                        class="w-100"
                        clearable
                        @change="handleSearch"
                    />
                </div>
            </div>
        </div>

        <ModalV1
            :show="showModal"
            :title="modalTitle"
            :submitText="modalSubmitText"
            :loading="loading.saveToDatabase || isFetchingMasterData"
            @close="closeModal"
            @submit="handleNextOrSubmit"
        >
            <div
                v-loading="isFetchingMasterData"
                element-loading-text="Memuat Data Master..."
                element-loading-background="rgba(255, 255, 255, 0.8)"
            >
                <el-steps :active="activeStep" finish-status="success" align-center class="mb-5 custom-steps">
                    <el-step title="Data Utama">
                        <template #icon>
                            <div class="step-icon-wrapper">
                                <i
                                    style="margin-top: -14px; margin-left: -4px"
                                    :class="activeStep > 1 ? 'bi bi-check-lg' : 'bi bi-card-checklist'"
                                ></i>
                            </div>
                        </template>
                    </el-step>
                    <el-step title="Detail Aktivitas">
                        <template #icon>
                            <div class="step-icon-wrapper">
                                <i class="bi bi-layers" style="margin-top: -14px; margin-left: -4px"></i>
                            </div>
                        </template>
                    </el-step>
                </el-steps>

                <transition name="fade-slide" mode="out-in">
                    <div v-if="activeStep === 1" key="step1" class="row gx-4 gy-4">
                        <div class="col-12 col-md-6">
                            <div>
                                <label class="form-label fw-bold text-dark d-flex align-items-center gap-2">
                                    <i class="bi bi-journal-bookmark text-primary"></i>
                                    Program Training <small class="text-danger">*</small>
                                </label>
                                <el-select
                                    v-model="formSchedule.training"
                                    filterable
                                    remote
                                    reserve-keyword
                                    :remote-method="handleTrainingRemoteSearch"
                                    :loading="loading.masterTraining"
                                    clearable
                                    placeholder="Ketik untuk mencari Program Training..."
                                    class="w-100 elegant-select"
                                    size="large"
                                    @change="handleTrainingChange"
                                    @clear="handleTrainingClear"
                                >
                                    <el-option
                                        v-for="item in listTraining"
                                        :key="item.Id_Master_Paket"
                                        :label="item.Nama_Paket"
                                        :value="item.Id_Master_Paket"
                                    />
                                    <template v-if="hasMoreTraining" #footer>
                                        <div
                                            class="text-center py-2 px-3 border-top cursor-pointer text-primary small fw-semibold bg-light"
                                            @click.stop="loadMoreTraining"
                                        >
                                            <span v-if="loadingMoreTraining">
                                                <i class="spinner-border spinner-border-sm me-1"></i> Memuat data...
                                            </span>
                                            <span v-else>
                                                <i class="bi bi-chevron-down me-1"></i> Muat lebih banyak ({{ listTraining.length }} dari {{ totalTraining }})
                                            </span>
                                        </div>
                                    </template>
                                </el-select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div>
                                <label class="form-label fw-bold text-dark d-flex align-items-center gap-2">
                                    <i class="bi bi-calendar-event text-primary"></i>
                                    Jam Mulai Akses <small class="text-danger">*</small>
                                </label>
                                <el-date-picker
                                    v-model="formSchedule.waktuMulai"
                                    type="datetime"
                                    placeholder="Tentukan Tanggal & Waktu Mulai"
                                    class="w-100 elegant-date"
                                    size="large"
                                    format="YYYY-MM-DD HH:mm:ss"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                />
                            </div>
                        </div>

                        <div class="col-12 mt-2" v-if="formSchedule.training && formSchedule.waktuMulai">
                            <div
                                v-if="!isEditing"
                                class="p-4 rounded-4"
                                style="background: #f8fafc; border: 1px solid #e2e8f0"
                            >
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-0">
                                        <i class="bi bi-people text-primary"></i>
                                        Pilih Peserta Karyawan <small class="text-danger">*</small>
                                    </label>
                                    <span
                                        class="badge rounded-pill px-3 py-2 shadow-sm"
                                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
                                    >
                                        {{ formSchedule.karyawan.length }} Terpilih
                                    </span>
                                </div>

                                <div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4" v-for="type in ['Semua', 'Karyawan', 'Phl']" :key="type">
                                            <div
                                                class="filter-type-card p-3 rounded-4 cursor-pointer"
                                                :class="{ active: activeEmployeeFilter === type }"
                                                @click="activeEmployeeFilter = type"
                                            >
                                                <div class="d-flex align-items-start gap-3">
                                                    <div
                                                        class="icon-filter-box rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                    >
                                                        <i
                                                            :class="
                                                                type === 'Semua'
                                                                    ? 'bi bi-people-fill'
                                                                    : type === 'Karyawan'
                                                                      ? 'bi bi-person-badge-fill'
                                                                      : 'bi bi-person-gear'
                                                            "
                                                        ></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-bold filter-title">{{ type }}</h6>
                                                        <p class="mb-0 text-muted filter-desc" style="font-size: 11px">
                                                            Filter data {{ type.toLowerCase() }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-3 mb-4 bg-white rounded-3 border shadow-sm">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-12 col-md-3">
                                                <el-checkbox
                                                    v-model="isAllSelectedCheck"
                                                    :indeterminate="isIndeterminate"
                                                    size="large"
                                                    class="fw-bold m-0 text-dark"
                                                >
                                                    Pilih Semua ({{ filteredKaryawan.length }} Data)
                                                </el-checkbox>
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <el-select
                                                    v-model="selectedLevels"
                                                    multiple
                                                    filterable
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    clearable
                                                    size="large"
                                                    placeholder="Filter Level..."
                                                    class="w-100 enterprise-input"
                                                    @change="fetchKaryawan"
                                                >
                                                    <el-option
                                                        v-for="level in listLevels"
                                                        :key="level.Id_Level"
                                                        :label="level.Keterangan"
                                                        :value="level.Id_Level"
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <el-select
                                                    v-model="selectedLokasi"
                                                    multiple
                                                    filterable
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    clearable
                                                    size="large"
                                                    placeholder="Filter Lokasi Kerja..."
                                                    class="w-100 enterprise-input"
                                                    @change="fetchKaryawan"
                                                >
                                                    <el-option
                                                        v-for="lokasi in listLokasiKaryawan"
                                                        :key="lokasi"
                                                        :label="lokasi"
                                                        :value="lokasi"
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <el-input
                                                    v-model="searchEmployee"
                                                    size="large"
                                                    placeholder="Cari nama, kode, divisi..."
                                                    clearable
                                                    class="w-100 enterprise-input"
                                                >
                                                    <template #prefix>
                                                        <i class="bi bi-search text-muted fw-bold"></i>
                                                    </template>
                                                </el-input>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="custom-scrollbar pr-2 pb-2"
                                        style="max-height: 550px; overflow-y: auto; overflow-x: hidden"
                                        v-loading="loading.karyawan"
                                    >
                                        <div class="row g-3" v-if="filteredKaryawan.length > 0">
                                            <div
                                                class="col-12 col-md-6 col-lg-6 d-flex"
                                                v-for="item in filteredKaryawan"
                                                :key="item.value"
                                            >
                                                <div
                                                    class="emp-select-card w-100 card border-1 cursor-pointer transition-all rounded-4 position-relative overflow-hidden"
                                                    :class="
                                                        formSchedule.karyawan.includes(item.value)
                                                            ? 'selected-card border-primary shadow-sm'
                                                            : 'unselected-card'
                                                    "
                                                    @click="toggleKaryawan(item.value)"
                                                >
                                                    <div class="check-indicator position-absolute top-0 end-0 m-3">
                                                        <i
                                                            v-if="formSchedule.karyawan.includes(item.value)"
                                                            class="bi bi-check-circle-fill fs-5 text-primary bg-white rounded-circle shadow-sm"
                                                        ></i>
                                                        <i v-else class="bi bi-circle text-muted opacity-25 fs-5"></i>
                                                    </div>

                                                    <div class="card-body p-3 d-flex flex-column h-100">
                                                        <div
                                                            class="d-flex align-items-start gap-3 mb-3 border-bottom pb-3 pe-4"
                                                        >
                                                            <div
                                                                class="emp-avatar text-white fw-bolder rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                                :style="`width: 48px; height: 48px; font-size: 16px; background: ${item.raw.Total_Assign > 0 ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'}`"
                                                            >
                                                                {{
                                                                    item.raw.Nama
                                                                        ? item.raw.Nama.substring(0, 2).toUpperCase()
                                                                        : 'NA'
                                                                }}
                                                            </div>
                                                            <div class="text-truncate flex-grow-1">
                                                                <h6
                                                                    class="fw-bold mb-1 text-dark text-truncate"
                                                                    style="font-size: 14px"
                                                                    :title="item.raw.Nama"
                                                                >
                                                                    {{ item.raw.Nama }}
                                                                </h6>
                                                                <div class="d-flex flex-column gap-1">
                                                                    <small
                                                                        class="text-muted font-monospace"
                                                                        style="font-size: 11px"
                                                                    >
                                                                        <i
                                                                            class="bi bi-person-vcard text-muted me-1"
                                                                        ></i
                                                                        >{{ item.raw.Kode_Karyawan }}
                                                                    </small>
                                                                    <small
                                                                        class="text-muted font-monospace"
                                                                        style="font-size: 11px"
                                                                    >
                                                                        <i class="bi bi-telephone text-muted me-1"></i
                                                                        >{{ item.raw.HP || 'Belum Ada No HP' }}
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="d-flex flex-column gap-2 flex-grow-1 mb-3"
                                                            style="font-size: 11.5px"
                                                        >
                                                            <div
                                                                class="d-flex justify-content-between align-items-center"
                                                            >
                                                                <span class="text-secondary"
                                                                    ><i class="bi bi-diagram-3 text-muted me-1"></i
                                                                    >Divisi</span
                                                                >
                                                                <span
                                                                    class="fw-bold text-dark text-end text-truncate ms-2"
                                                                    style="max-width: 140px"
                                                                    :title="item.raw.Divisi"
                                                                    >{{ item.raw.Divisi || '-' }}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="d-flex justify-content-between align-items-center"
                                                            >
                                                                <span class="text-secondary"
                                                                    ><i class="bi bi-building text-muted me-1"></i
                                                                    >Dept</span
                                                                >
                                                                <span
                                                                    class="fw-bold text-dark text-end text-truncate ms-2"
                                                                    style="max-width: 140px"
                                                                    :title="item.raw.Departemen"
                                                                    >{{ item.raw.Departemen || '-' }}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="d-flex justify-content-between align-items-center"
                                                            >
                                                                <span class="text-secondary"
                                                                    ><i class="bi bi-star text-muted me-1"></i
                                                                    >Level</span
                                                                >
                                                                <span class="fw-bold text-dark text-end ms-2">{{
                                                                    item.raw.Level || '-'
                                                                }}</span>
                                                            </div>
                                                            <div
                                                                class="d-flex justify-content-between align-items-center"
                                                            >
                                                                <span
                                                                    class="text-secondary"
                                                                    style="color: #764ba2 !important"
                                                                    ><i class="bi bi-person-badge me-1"></i
                                                                    >Jabatan</span
                                                                >
                                                                <span
                                                                    class="fw-bold text-dark text-end text-truncate ms-2"
                                                                    style="max-width: 140px"
                                                                    :title="item.raw.Jabatan"
                                                                    >{{ item.raw.Jabatan || '-' }}</span
                                                                >
                                                            </div>
                                                        </div>

                                                        <div class="mt-auto pt-3 border-top">
                                                            <span
                                                                v-if="item.raw.Total_Assign > 0"
                                                                class="badge rounded-pill px-3 py-2 w-100 d-block text-center shadow-sm"
                                                                style="
                                                                    background-color: #ecfdf5;
                                                                    color: #059669;
                                                                    border: 1px solid #10b981;
                                                                    font-size: 11px;
                                                                "
                                                            >
                                                                <i class="bi bi-check2-all me-1"></i> Sudah Diassign ({{
                                                                    item.raw.Total_Assign
                                                                }}x)
                                                            </span>
                                                            <span
                                                                v-else
                                                                class="badge rounded-pill px-3 py-2 w-100 d-block text-center shadow-sm"
                                                                style="
                                                                    background-color: #fef2f2;
                                                                    color: #dc2626;
                                                                    border: 1px solid #ef4444;
                                                                    font-size: 11px;
                                                                "
                                                            >
                                                                <i class="bi bi-exclamation-circle me-1"></i> Belum
                                                                Pernah Diassign
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-5 bg-white rounded-4 border border-dashed">
                                            <i class="bi bi-search text-muted opacity-50" style="font-size: 3rem"></i>
                                            <p class="mt-3 text-muted fw-medium">
                                                Tidak ada karyawan yang cocok dengan filter atau pencarian Anda.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="p-4 rounded-4 d-flex align-items-center gap-3"
                                style="background: #f0fdf4; border: 1px solid #bbf7d0"
                            >
                                <div
                                    class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm"
                                    style="width: 48px; height: 48px"
                                >
                                    <i class="bi bi-person-check-fill fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                                        {{ formSchedule.namaKaryawanEdit }}
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success"
                                            >Peserta Terpilih</span
                                        >
                                    </h6>
                                    <span class="text-muted" style="font-size: 13px">
                                        <i class="bi bi-person-vcard me-1"></i> {{ formSchedule.karyawan[0] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 text-center py-5" v-else>
                            <div class="p-5 rounded-4 border border-dashed bg-white">
                                <i class="bi bi-arrow-up-circle text-primary opacity-20" style="font-size: 3.5rem"></i>
                                <h5 class="mt-3 fw-bold text-dark">Langkah Pertama</h5>
                                <p class="text-muted">
                                    Silakan pilih <b>Program Training</b> dan <b>Waktu Akses</b> terlebih dahulu untuk
                                    menampilkan daftar karyawan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 2" key="step2" class="row">
                        <div class="col-12 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark">Ringkasan Penjadwalan</h6>
                                <button
                                    class="btn btn-sm btn-light border bg-white shadow-sm d-flex align-items-center gap-2 btn-hover-scale rounded-3 px-3 py-1"
                                    @click="activeStep = 1"
                                >
                                    <i class="bi bi-arrow-left" style="margin-top: -8px"></i> Kembali
                                </button>
                            </div>
                            <div class="bg-white border p-4 rounded-4 shadow-sm summary-box">
                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3">
                                        <span class="text-secondary d-flex align-items-center gap-2">
                                            <i class="bi bi-book text-muted" style="margin-top: -8px"></i> Training
                                        </span>
                                        <span class="fw-bold text-dark">{{ selectedTrainingName }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3">
                                        <span class="text-secondary d-flex align-items-center gap-2">
                                            <i class="bi bi-people text-muted" style="margin-top: -8px"></i> Peserta
                                        </span>
                                        <span class="fw-bold text-primary"
                                            >{{ formSchedule.karyawan.length }} Karyawan</span
                                        >
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-1">
                                        <span class="text-secondary d-flex align-items-center gap-2">
                                            <i class="bi bi-clock text-muted" style="margin-top: -8px"></i> Mulai Akses
                                        </span>
                                        <span class="fw-bold text-success">{{
                                            formSchedule.waktuMulai ? formatDateShort(formSchedule.waktuMulai) : '-'
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark mb-0">Alur Aktivitas & Konfigurasi</h6>
                                <transition name="fade">
                                    <button
                                        v-if="removedItems.length > 0"
                                        @click="undoRemove"
                                        class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 d-flex align-items-center gap-2"
                                    >
                                        <i class="bi bi-arrow-counterclockwise" style="margin-top: -8px"></i> Undo ({{
                                            removedItems.length
                                        }})
                                    </button>
                                </transition>
                            </div>

                            <div class="activity-timeline custom-scrollbar pe-2">
                                <div
                                    v-for="(sub, subIndex) in currentActivities"
                                    :key="subIndex"
                                    class="sub-training-group mb-4 p-3 border border-light rounded-4 bg-white shadow-sm"
                                    style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.02) !important"
                                >
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2"
                                    >
                                        <h6 class="fw-bold text-primary mb-0 d-flex align-items-center gap-2">
                                            <i class="bi bi-diagram-2"></i> {{ sub.Nama_Sub }}
                                        </h6>
                                        <button
                                            @click="removeSub(subIndex)"
                                            class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 d-flex align-items-center gap-1"
                                        >
                                            <i class="bi bi-trash" style="margin-top: -4px"></i> Hapus Sub
                                        </button>
                                    </div>

                                    <div
                                        v-for="(act, actIndex) in sub.Aktivitas"
                                        :key="actIndex"
                                        class="activity-item bg-white mb-3 rounded-4 overflow-hidden"
                                        style="border: 1px solid #ebedf0"
                                        :class="
                                            act.Flag_Third_Party === 'Y'
                                                ? 'is-setup border-warning'
                                                : 'is-default border-primary'
                                        "
                                    >
                                        <div class="p-4 d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-3">
                                                <div
                                                    class="icon-box rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                                                    :class="
                                                        act.Flag_Third_Party === 'Y'
                                                            ? 'bg-warning text-dark'
                                                            : 'bg-primary'
                                                    "
                                                >
                                                    {{ act.Kode_Aktivitas.substring(0, 2) }}
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-bold text-dark fs-6">
                                                        {{ act.Nama_Aktivitas }}
                                                    </h6>
                                                    <small class="text-muted fw-medium">{{ act.Kode_Aktivitas }}</small>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <button
                                                    v-if="act.Kode_Aktivitas !== 'ABS'"
                                                    @click="removeActivity(subIndex, actIndex)"
                                                    class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 me-2 d-flex align-items-center gap-1"
                                                >
                                                    <i class="bi bi-trash" style="margin-top: -4px"></i> Buang
                                                </button>
                                                <span
                                                    v-if="act.Flag_Third_Party === 'Y'"
                                                    class="badge badge-setup text-dark px-3 py-2 rounded-pill shadow-sm d-flex align-items-center gap-1"
                                                >
                                                    <i class="bi bi-gear-fill"></i> Setup Soal
                                                </span>
                                                <span
                                                    v-else
                                                    class="badge badge-default text-success px-3 py-2 rounded-pill d-flex align-items-center gap-1"
                                                >
                                                    <i class="bi bi-check-circle-fill"></i> Default
                                                </span>
                                            </div>
                                        </div>

                                        <transition name="slide-down">
                                            <div v-if="act.Flag_Third_Party === 'Y'" class="px-4 pb-4">
                                                <label
                                                    class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-3"
                                                >
                                                    <i
                                                        style="margin-top: -8px"
                                                        class="bi bi-diagram-3 text-warning"
                                                    ></i>
                                                    Pilih Paket Soal untuk {{ act.Nama_Aktivitas }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <el-select
                                                    v-model="formSchedule.thirdPartyPayloads[act.Kode_Aktivitas]"
                                                    filterable
                                                    clearable
                                                    :placeholder="'Pilih Soal ' + act.Nama_Aktivitas + '...'"
                                                    class="w-100 elegant-select select-warning bg-white rounded-3"
                                                    size="large"
                                                >
                                                    <el-option
                                                        v-for="item in listDataMasterUjian"
                                                        :key="item.value"
                                                        :label="item.label"
                                                        :value="item.value"
                                                        style="height: auto; padding: 0; margin-bottom: 5px"
                                                    >
                                                        <div
                                                            style="padding: 12px 16px; border-bottom: 1px solid #eef1f6"
                                                        >
                                                            <div
                                                                class="d-flex justify-content-between align-items-start mb-2"
                                                            >
                                                                <div style="flex: 1; padding-right: 10px">
                                                                    <div
                                                                        style="
                                                                            font-size: 14px;
                                                                            font-weight: 700;
                                                                            color: #2c3e50;
                                                                            line-height: 1.4;
                                                                        "
                                                                    >
                                                                        {{ item.raw.Nama_Ujian ?? '-' }}
                                                                    </div>
                                                                </div>
                                                                <span
                                                                    class="badge"
                                                                    :class="
                                                                        item.raw.Tingkatan === 'STAFF'
                                                                            ? 'bg-info'
                                                                            : 'bg-primary'
                                                                    "
                                                                    style="
                                                                        font-size: 10px;
                                                                        padding: 4px 8px;
                                                                        border-radius: 4px;
                                                                        font-weight: 600;
                                                                        white-space: nowrap;
                                                                    "
                                                                >
                                                                    {{ item.raw.Tingkatan }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="d-flex align-items-center"
                                                                style="
                                                                    background: #f8f9fa;
                                                                    padding: 6px 10px;
                                                                    border-radius: 6px;
                                                                    border: 1px dashed #dcdfe6;
                                                                "
                                                            >
                                                                <div
                                                                    class="d-flex align-items-center"
                                                                    style="
                                                                        font-size: 11px;
                                                                        color: #606266;
                                                                        font-family: monospace;
                                                                    "
                                                                >
                                                                    <span
                                                                        style="
                                                                            width: 70px;
                                                                            color: #909399;
                                                                            font-weight: 600;
                                                                        "
                                                                        >KODE UJIAN</span
                                                                    >
                                                                    <span style="font-weight: 700; color: #409eff"
                                                                        >: {{ item.raw.Kode_Ujian }}</span
                                                                    >
                                                                </div>
                                                                <div
                                                                    class="d-flex align-items-center"
                                                                    style="
                                                                        font-size: 11px;
                                                                        color: #606266;
                                                                        font-family: monospace;
                                                                    "
                                                                >
                                                                    <span
                                                                        style="
                                                                            width: 70px;
                                                                            color: #909399;
                                                                            font-weight: 600;
                                                                        "
                                                                        >KODE PAKET</span
                                                                    >
                                                                    <span style="font-weight: 700; color: #e6a23c"
                                                                        >: {{ item.raw.Kode_Paket }}</span
                                                                    >
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </el-option>
                                                </el-select>
                                                <div class="row mt-3">
                                                    <div class="col-md-6 mb-2">
                                                        <label
                                                            class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-2"
                                                            style="font-size: 13px"
                                                        >
                                                            <i class="bi bi-calendar-event text-primary"></i> Waktu
                                                            Mulai {{ act.Nama_Aktivitas }}
                                                        </label>
                                                        <el-date-picker
                                                            v-model="
                                                                formSchedule.thirdPartyWaktuMulai[act.Kode_Aktivitas]
                                                            "
                                                            type="datetime"
                                                            placeholder="Waktu Mulai..."
                                                            format="YYYY-MM-DD HH:mm:ss"
                                                            value-format="YYYY-MM-DD HH:mm:ss"
                                                            class="w-100"
                                                            :default-time="defaultTime"
                                                            :disabled-date="disabledDateMulaiThird"
                                                            @change="
                                                                (val) =>
                                                                    validateWaktuMulaiThird(
                                                                        val,
                                                                        act.Kode_Aktivitas,
                                                                        act.Nama_Aktivitas,
                                                                    )
                                                            "
                                                        />
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label
                                                            class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-2"
                                                            style="font-size: 13px"
                                                        >
                                                            <i class="bi bi-calendar-x text-danger"></i> Waktu Akhir
                                                            {{ act.Nama_Aktivitas }}
                                                        </label>
                                                        <el-date-picker
                                                            v-model="
                                                                formSchedule.thirdPartyWaktuAkhir[act.Kode_Aktivitas]
                                                            "
                                                            type="datetime"
                                                            placeholder="Waktu Akhir..."
                                                            format="YYYY-MM-DD HH:mm:ss"
                                                            value-format="YYYY-MM-DD HH:mm:ss"
                                                            class="w-100"
                                                            :default-time="defaultTime"
                                                            :disabled-date="
                                                                (time) =>
                                                                    disabledDateAkhirThird(time, act.Kode_Aktivitas)
                                                            "
                                                            @change="
                                                                (val) =>
                                                                    validateWaktuAkhirThird(
                                                                        val,
                                                                        act.Kode_Aktivitas,
                                                                        act.Nama_Aktivitas,
                                                                    )
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                                <div
                                                    class="mt-3 p-3 rounded-3"
                                                    style="background-color: #fcfcfc; border: 1px dashed #dcdfe6"
                                                >
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="d-flex flex-column">
                                                            <label
                                                                class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1"
                                                                style="font-size: 13px"
                                                            >
                                                                <i class="bi bi-camera-video text-primary"></i>
                                                                Aktifkan Kamera Peserta
                                                            </label>
                                                            <span class="text-muted" style="font-size: 11px">
                                                                Peserta wajib mengaktifkan kamera saat mengerjakan
                                                                {{ act.Nama_Aktivitas }}.
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <el-switch
                                                                v-model="
                                                                    formSchedule.thirdPartyCamera[act.Kode_Aktivitas]
                                                                "
                                                                inline-prompt
                                                                active-text="Ya"
                                                                inactive-text="Tidak"
                                                                style="
                                                                    --el-switch-on-color: #13ce66;
                                                                    --el-switch-off-color: #ff4949;
                                                                "
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </transition>

                                        <transition name="slide-down">
                                            <div
                                                v-if="
                                                    act.Kode_Aktivitas === 'MDL' &&
                                                    act.Details &&
                                                    act.Details.length > 0
                                                "
                                                class="px-4 pb-4"
                                            >
                                                <div class="ps-4 border-start border-2 border-primary ms-3">
                                                    <h6
                                                        class="fw-bold text-dark mb-3 fs-6 d-flex align-items-center gap-2"
                                                    >
                                                        <i class="bi bi-card-list text-primary"></i> Modul Materi
                                                    </h6>

                                                    <div class="d-flex flex-column gap-3 mt-1">
                                                        <div
                                                            v-for="(det, detIndex) in act.Details"
                                                            :key="detIndex"
                                                            class="d-flex align-items-center gap-3 bg-white border px-3 py-3 rounded-4"
                                                            style="
                                                                border-color: #eaecf0 !important;
                                                                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
                                                                transition: all 0.2s ease;
                                                            "
                                                        >
                                                            <div
                                                                class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm text-dark"
                                                                style="
                                                                    width: 42px;
                                                                    height: 42px;
                                                                    font-size: 1.1rem;
                                                                    flex-shrink: 0;
                                                                "
                                                                :class="getDynamicBg('MDL', det.Tipe_Konten)"
                                                            >
                                                                <i :class="getDynamicIcon(det.Tipe_Konten)"></i>
                                                            </div>

                                                            <div class="d-flex flex-column justify-content-center">
                                                                <span
                                                                    class="fw-bold text-dark mb-1"
                                                                    style="font-size: 0.95rem; line-height: 1.3"
                                                                >
                                                                    {{ detIndex + 1 }}. {{ det.Judul_Konten }}
                                                                </span>
                                                                <div
                                                                    class="d-flex align-items-center gap-2"
                                                                    style="font-size: 0.75rem"
                                                                >
                                                                    <span
                                                                        class="badge bg-light text-secondary border px-2 py-1 rounded-2 fw-medium"
                                                                    >
                                                                        {{ det.Nama_Kategori }}
                                                                    </span>
                                                                    <span
                                                                        class="text-muted fw-bold d-flex align-items-center gap-1"
                                                                        style="letter-spacing: 0.3px"
                                                                    >
                                                                        <span
                                                                            style="
                                                                                font-size: 14px;
                                                                                color: #dcdfe6;
                                                                                margin-top: -2px;
                                                                            "
                                                                            >•</span
                                                                        >
                                                                        {{
                                                                            det.Tipe_Konten
                                                                                ? det.Tipe_Konten.toUpperCase()
                                                                                : 'FILE'
                                                                        }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </transition>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark mb-0">Pengaturan Tambahan</h6>
                            </div>
                            <div class="bg-white border p-4 rounded-4 shadow-sm">
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-calculator text-primary" style="margin-top: -2px"></i>
                                        Pilih Rumus Penilaian Akhir
                                    </label>
                                    <el-select
                                        v-model="formSchedule.rumusPenilaian"
                                        placeholder="Pilih formula perhitungan nilai akhir..."
                                        class="w-100 elegant-select"
                                        size="large"
                                        filterable
                                        clearable
                                    >
                                        <el-option
                                            v-for="item in availableFormulas"
                                            :key="item.Id_Master_Rumus"
                                            :label="item.Nama_Formula"
                                            :value="item.Id_Master_Rumus"
                                            style="height: auto; padding: 8px 16px"
                                        >
                                            <div class="d-flex flex-column lh-sm border-bottom pb-2 mb-1">
                                                <span class="fw-bold text-dark" style="font-size: 13px">{{
                                                    item.Nama_Formula
                                                }}</span>
                                                <span
                                                    class="text-primary mt-1"
                                                    style="font-size: 11px; font-family: monospace; font-weight: 600"
                                                    >{{ item.RumusTampil }}</span
                                                >
                                                <span class="text-muted mt-1" style="font-size: 10px">{{
                                                    item.Deskripsi
                                                }}</span>
                                            </div>
                                        </el-option>
                                        <template #empty>
                                            <div class="text-center p-4 text-muted">
                                                <div class="d-flex justify-content-center">
                                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                                </div>
                                                <span style="font-size: 12px"
                                                    >Tidak ada rumus yang cocok dengan aktivitas saat ini.</span
                                                >
                                            </div>
                                        </template>
                                    </el-select>
                                    <div
                                        class="form-text mt-2 text-secondary d-flex gap-1 align-items-start"
                                        style="font-size: 11px"
                                    >
                                        <i class="bi bi-info-circle text-info"></i>
                                        <span
                                            >Sistem hanya menampilkan rumus yang komponen aktivitasnya tersedia di paket
                                            training ini.</span
                                        >
                                    </div>
                                </div>

                                <transition name="slide-down">
                                    <div v-if="hasPreAndPostTest" class="mt-4 pt-3 border-top">
                                        <div
                                            class="d-flex justify-content-between align-items-center p-3 rounded-3"
                                            style="background-color: #f8f9fa; border: 1px dashed #ced4da"
                                        >
                                            <div class="d-flex flex-column">
                                                <label
                                                    class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-1 cursor-pointer"
                                                >
                                                    <i class="bi bi-graph-up-arrow text-success"></i>
                                                    Gunakan Fitur Learning Gain
                                                </label>
                                                <span class="text-muted" style="font-size: 11px">
                                                    Menghitung persentase efektivitas training dari selisih nilai
                                                    Pre-Test dan Post-Test.
                                                </span>
                                            </div>
                                            <div class="ms-3">
                                                <el-switch
                                                    v-model="formSchedule.flagLearningGain"
                                                    :disabled="!isLearningGainFormulaSelected"
                                                    active-value="Y"
                                                    inactive-value="T"
                                                    active-text="Ya"
                                                    inactive-text="Tidak"
                                                    inline-prompt
                                                    style="
                                                        --el-switch-on-color: #13ce66;
                                                        --el-switch-off-color: #ff4949;
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </ModalV1>
        <div
            v-if="uploadTasks.length > 0"
            class="position-fixed"
            style="
                bottom: 24px;
                right: 24px;
                width: 380px;
                z-index: 9999;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                border-radius: 12px;
                background: white;
                overflow: hidden;
                border: 1px solid #e2e8f0;
                transition: all 0.3s ease;
            "
        >
            <div
                class="d-flex justify-content-between align-items-center px-3 py-2 bg-dark text-white"
                style="cursor: pointer"
                @click="isUploadWidgetMinimized = !isUploadWidgetMinimized"
            >
                <span class="fw-bold" style="font-size: 14px">
                    <i class="bi bi-cloud-arrow-up me-2"></i>
                    {{ activeTasksCount > 0 ? `Memproses ${activeTasksCount} Penjadwalan...` : 'Semua Proses Selesai' }}
                </span>
                <div class="d-flex gap-2">
                    <i :class="isUploadWidgetMinimized ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                    <i
                        class="bi bi-x-lg ms-2"
                        @click.stop="uploadTasks = []"
                        v-if="activeTasksCount === 0"
                        title="Tutup"
                    ></i>
                </div>
            </div>

            <div
                v-show="!isUploadWidgetMinimized"
                class="p-0 custom-scrollbar"
                style="max-height: 350px; overflow-y: auto"
            >
                <div v-for="task in uploadTasks" :key="task.id" class="d-flex align-items-center p-3 border-bottom">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between mb-2" style="font-size: 13px">
                            <span class="fw-bold text-dark text-truncate" style="max-width: 230px">{{
                                task.title
                            }}</span>

                            <span v-if="task.status === 'processing'" class="text-primary fw-bold">
                                {{ Math.floor(task.progress) }}%
                            </span>
                            <span v-else-if="task.status === 'completed'" class="text-success fw-bold"
                                ><i class="bi bi-check-circle-fill"></i> Selesai</span
                            >
                            <span v-else-if="task.status === 'error'" class="text-danger fw-bold"
                                ><i class="bi bi-x-circle-fill"></i> Gagal</span
                            >
                        </div>

                        <div class="progress" style="height: 6px; border-radius: 10px">
                            <div
                                class="progress-bar progress-bar-striped progress-bar-animated"
                                :class="{
                                    'bg-primary': task.status === 'processing',
                                    'bg-success': task.status === 'completed',
                                    'bg-danger': task.status === 'error',
                                }"
                                :style="{ width: task.progress + '%' }"
                            ></div>
                        </div>
                        <div v-if="task.status === 'error'" class="mt-1 text-danger" style="font-size: 11px">
                            {{ task.message }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import ModalV1 from '@/components/legacy/modalV1.vue';
import FormV1 from '@/components/legacy/formV1.vue';
import ListAccordionV1 from '@/components/legacy/listAccordionUiV1.vue';
import { HcisSkeleton } from '@/components/ui';
import { ElMessage, ElSteps, ElStep, ElSelect, ElOption, ElDatePicker, ElMessageBox, ElDialog } from 'element-plus';
import axios from 'axios';
import { tanggalPanjang } from '@/utils/tanggal';

export default {
    name: 'TrainingSchedulingPage',
    components: {
        HeaderKpi,
        ModalV1,
        FormV1,
        ElSteps,
        ElStep,
        ElSelect,
        ElOption,
        ElDatePicker,
        ListAccordionV1,
        HcisSkeleton,
        ElMessageBox,
        ElDialog,
    },
    data() {
        return {
            activeEmployeeFilter: 'Semua',
            selectedLevels: [],
            listLevels: [],
            selectedLokasi: [],
            listLokasiKaryawan: [],
            searchEmployee: '',
            listKaryawan: [],
            currentDate: new Date(),
            searchQuery: '',
            searchTimeout: null,
            listData: [],
            listDataMasterUjian: [],
            listMasterRumus: [],
            showModal: false,
            isEditing: false,
            activeStep: 1,
            removedItems: [],
            currentActivities: [],
            defaultTime: new Date(2000, 1, 1, 23, 59, 59),
            showReassignModal: false,
            formSchedule: {
                id_penjadwalan: null,
                karyawan: [],
                namaKaryawanEdit: '',
                training: null,
                waktuMulai: null,
                waktuAkhir: null,
                thirdPartyPayloads: {},
                thirdPartyWaktuMulai: {},
                thirdPartyWaktuAkhir: {},
                thirdPartyCamera: {},
                rumusPenilaian: null,
                flagLearningGain: 'T',
            },
            reassignData: {
                idPenjadwalanDet: null,
                idIntegrasi: null,
                kodeKaryawan: null,
                namaKaryawan: null,
                namaAktivitas: null,
                idPaketSoal: null,
                waktuMulai: null,
                waktuAkhir: null,
                flagCamera: false, // Default false
                oldToken: null,
            },
            pagination: {
                page: 1,
                totalPage: 1,
                per_page: 10,
                totalData: 0,
            },
            loading: {
                listData: false,
                saveToDatabase: false,
                karyawan: false,
                listDataMasterUjian: false,
                masterTraining: false,
                masterRumus: false,
                reassign: false,
            },
            listTraining: [],
            totalTraining: 0,
            hasMoreTraining: false,
            pageTraining: 1,
            trainingSearchQuery: '',
            loadingMoreTraining: false,
            uploadTasks: [],
            isUploadWidgetMinimized: false,
            filterStatusAkses: 'all',
            filterMateri: [],
            filterLokasi: [],
            filterModule: [],
            listLokasi: [],
            filterDateMode: 'enroll',
            filterDateRange: [],
            selectedJadwal: [],
            isBulkLoading: false,
            isFetchingAllIds: false,
        };
    },
    created() {
        this.taskIntervals = {};
    },
    beforeUnmount() {
        Object.keys(this.taskIntervals).forEach((processId) => {
            this.clearTaskIntervals(processId);
        });
    },
    computed: {
        activeTasksCount() {
            return this.uploadTasks.filter((t) => t.status === 'processing').length;
        },
        // Daftar module diturunkan dari listTraining (paket -> Trainings[]) supaya tidak
        // perlu request tambahan; satu module bisa dipakai beberapa paket, jadi di-dedupe.
        moduleOptions() {
            const unique = new Map();
            (this.listTraining || []).forEach((paket) => {
                (paket.Trainings || []).forEach((training) => {
                    if (training.Id_Materi && !unique.has(training.Id_Materi)) {
                        unique.set(training.Id_Materi, {
                            value: training.Id_Materi,
                            label: training.Judul_Training,
                        });
                    }
                });
            });
            return Array.from(unique.values()).sort((a, b) => a.label.localeCompare(b.label));
        },
        activeFilterCount() {
            let count = 0;
            if (this.searchQuery) count++;
            if (this.filterLokasi.length) count++;
            if (this.filterModule.length) count++;
            if (this.filterMateri.length) count++;
            if (this.filterStatusAkses !== 'all') count++;
            if (this.filterDateRange && this.filterDateRange.length) count++;
            return count;
        },
        filteredKaryawan() {
            let filtered = this.listKaryawan;
            if (this.searchEmployee) {
                const s = this.searchEmployee.toLowerCase();
                filtered = filtered.filter(
                    (k) =>
                        (k.raw.Nama && k.raw.Nama.toLowerCase().includes(s)) ||
                        (k.raw.Kode_Karyawan && k.raw.Kode_Karyawan.toLowerCase().includes(s)) ||
                        (k.raw.Divisi && k.raw.Divisi.toLowerCase().includes(s)),
                );
            }
            return filtered;
        },
        isIndeterminate() {
            if (this.filteredKaryawan.length === 0) return false;
            const selectedCount = this.filteredKaryawan.filter((k) =>
                this.formSchedule.karyawan.includes(k.value),
            ).length;
            return selectedCount > 0 && selectedCount < this.filteredKaryawan.length;
        },
        isAllSelectedCheck: {
            get() {
                if (this.filteredKaryawan.length === 0) return false;
                return this.filteredKaryawan.every((k) => this.formSchedule.karyawan.includes(k.value));
            },
            set(val) {
                if (val) {
                    const allFiltered = this.filteredKaryawan;
                    const alreadyAssigned = allFiltered.filter((k) => k.raw.Total_Assign > 0);

                    const notAssigned = allFiltered.filter((k) => !(k.raw.Total_Assign > 0));

                    if (alreadyAssigned.length > 0) {
                        let listHtml = `
                        <div class="text-center mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3.5rem; text-shadow: 0px 4px 10px rgba(251, 191, 36, 0.3);"></i>
                            <h5 class="fw-bolder mt-2 text-dark">Perhatian!</h5>
                        </div>
                        <p class="text-secondary" style="font-size: 13px; line-height: 1.5;">
                            Terdeteksi <b class="text-dark">${alreadyAssigned.length}</b> karyawan sudah pernah dijadwalkan untuk training <b class="text-primary">${this.selectedTrainingName}</b>.
                        </p>
                        <div class="text-start custom-scrollbar" style="max-height: 180px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 8px;">
                    `;

                        alreadyAssigned.forEach((k) => {
                            listHtml += `
                            <div class="d-flex align-items-center gap-2 py-2 px-2 border-bottom" style="font-size: 12.5px;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 26px; height: 26px; background-color: #ecfdf5; color: #10b981;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div class="text-truncate flex-grow-1">
                                    <span class="fw-bold text-dark d-block">${k.raw.Nama}</span>
                                    <span class="text-muted" style="font-size: 11px;">${k.raw.Kode_Karyawan}</span>
                                </div>
                                <span class="badge bg-warning text-dark border">${k.raw.Total_Assign}x</span>
                            </div>
                        `;
                        });

                        listHtml += `
                        </div>
                        <p class="mt-3 mb-0 text-secondary text-center" style="font-size: 13px;">
                            Apakah Anda ingin tetap <b>menjadwalkan ulang</b> mereka bersamaan dengan karyawan lainnya?
                        </p>
                    `;

                        ElMessageBox.confirm(listHtml, '', {
                            dangerouslyUseHTMLString: true,
                            confirmButtonText: 'Ya, Check All (Termasuk yg sudah)',
                            cancelButtonText: 'Tidak, Hanya yg belum',
                            confirmButtonClass: 'el-button--primary fw-bold shadow-sm',
                            cancelButtonClass: 'el-button--default fw-bold bg-light',
                            customClass: 'rounded-4 shadow-lg border-0 modal-bouncing',
                            center: true,
                            showClose: false,
                            closeOnClickModal: false,
                        })
                            .then(() => {
                                const newSelections = allFiltered.map((k) => k.value);
                                this.formSchedule.karyawan = [
                                    ...new Set([...this.formSchedule.karyawan, ...newSelections]),
                                ];
                            })
                            .catch(() => {
                                const newSelections = notAssigned.map((k) => k.value);
                                this.formSchedule.karyawan = [
                                    ...new Set([...this.formSchedule.karyawan, ...newSelections]),
                                ];
                            });
                    } else {
                        const newSelections = allFiltered.map((k) => k.value);
                        this.formSchedule.karyawan = [...new Set([...this.formSchedule.karyawan, ...newSelections])];
                    }
                } else {
                    const filteredValues = this.filteredKaryawan.map((k) => k.value);
                    this.formSchedule.karyawan = this.formSchedule.karyawan.filter(
                        (id) => !filteredValues.includes(id),
                    );
                }
            },
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
        modalTitle() {
            return this.isEditing
                ? `Edit Penjadwalan - ${this.formSchedule.namaKaryawanEdit}`
                : 'Tambah Penjadwalan Baru';
        },
        modalSubmitText() {
            if (this.activeStep === 1) return 'Lanjutkan Konfigurasi';
            return this.isEditing ? 'Simpan Perubahan' : 'Konfirmasi & Simpan';
        },
        selectedTrainingName() {
            const paket = this.listTraining.find((p) => p.Id_Master_Paket === this.formSchedule.training);
            return paket ? paket.Nama_Paket : 'Belum Dipilih';
        },
        availableFormulas() {
            if (!this.currentActivities || this.currentActivities.length === 0) return [];
            let flatAktivitas = [];
            this.currentActivities.forEach((sub) => {
                if (sub.Aktivitas) flatAktivitas.push(...sub.Aktivitas);
            });
            const activeActivityCodes = flatAktivitas.map((act) => act.Kode_Aktivitas);

            return this.listMasterRumus.filter((rumus) => {
                const dibutuhkan = rumus.DaftarKodeDibutuhkan || [];
                return (
                    dibutuhkan.length > 0 &&
                    dibutuhkan.every((kodeDibutuhkan) => activeActivityCodes.includes(kodeDibutuhkan))
                );
            });
        },
        isFetchingMasterData() {
            return (
                this.loading.karyawan ||
                this.loading.listDataMasterUjian ||
                this.loading.masterTraining ||
                this.loading.masterRumus
            );
        },
        hasPreAndPostTest() {
            if (!this.currentActivities || this.currentActivities.length === 0) return false;
            let flatAktivitas = [];
            this.currentActivities.forEach((sub) => {
                if (sub.Aktivitas) flatAktivitas.push(...sub.Aktivitas);
            });
            const activeCodes = flatAktivitas.map((act) => act.Kode_Aktivitas);
            return activeCodes.includes('PRT') && activeCodes.includes('PTT');
        },
        isLearningGainFormulaSelected() {
            if (!this.formSchedule.rumusPenilaian || !this.listMasterRumus.length) return false;

            const selectedFormula = this.listMasterRumus.find(
                (r) => r.Id_Master_Rumus === this.formSchedule.rumusPenilaian,
            );

            return selectedFormula && selectedFormula.Kode_Rumus === 'LGN';
        },
        isAllJadwalSelected() {
            if (this.pagination.totalData === 0) return false;
            return this.selectedJadwal.length === this.pagination.totalData;
        },
        isIndeterminateJadwal() {
            return this.selectedJadwal.length > 0 && this.selectedJadwal.length < this.pagination.totalData;
        },
    },
    watch: {
        uploadTasks: {
            handler(newVal) {
                localStorage.setItem('lms_active_tasks', JSON.stringify(newVal));
            },
            deep: true,
        },
        hasPreAndPostTest(newVal) {
            if (!newVal) {
                this.formSchedule.flagLearningGain = 'T';
            }
        },
        'formSchedule.rumusPenilaian'(newVal) {
            if (this.isLearningGainFormulaSelected) {
                this.formSchedule.flagLearningGain = 'Y';
            } else {
                this.formSchedule.flagLearningGain = 'T';
            }
        },
        activeEmployeeFilter(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.fetchKaryawan();
            }
        },
        // [TAMBAHAN] Watcher untuk memvalidasi tanggal/waktu di reassign modal
        'reassignData.waktuMulai'(newVal) {
            if (newVal && this.reassignData.waktuAkhir) {
                const startTime = new Date(newVal).getTime();
                const endTime = new Date(this.reassignData.waktuAkhir).getTime();

                if (endTime <= startTime) {
                    ElMessage.warning('Waktu Akhir direset karena mendahului atau sama dengan Waktu Mulai.');
                    this.reassignData.waktuAkhir = null;
                }
            }
        },
        'reassignData.waktuAkhir'(newVal) {
            if (newVal && this.reassignData.waktuMulai) {
                const startTime = new Date(this.reassignData.waktuMulai).getTime();
                const endTime = new Date(newVal).getTime();

                if (endTime <= startTime) {
                    ElMessage.error('Waktu Akhir tidak boleh lebih kecil atau sama dengan Waktu Mulai!');
                    // Beri jeda sedikit agar tidak bentrok dengan event update v-model
                    setTimeout(() => {
                        this.reassignData.waktuAkhir = null;
                    }, 50);
                }
            }
        },
    },
    mounted() {
        this.restoreTasks();
        this.getDataCurrent(1);
        this.fetchMasterLevel();
        this.fetchKaryawan();
        this.fetchMasterSoal();
        this.fetchMasterRumus();
        this.fetchMasterTraining();
        this.fetchLokasi();
        this.fetchLokasiKaryawan();
    },
    methods: {
        openReassignModal(jadwal, sub, act) {
            if (!act.third_party_info) {
                ElMessage.error('Data integrasi tidak ditemukan.');
                return;
            }

            this.reassignData = {
                idPenjadwalanDet: act.Id_Penjadwalan_Det,
                kodeKaryawan: jadwal.Kode_Karyawan,
                namaKaryawan: jadwal.Nama_Karyawan,
                namaAktivitas: act.Nama_Aktivitas,
                idPaketSoal: act.third_party_info.Id_Master_Ujian,
                waktuMulai: act.third_party_info.Waktu_Mulai,
                waktuAkhir: act.third_party_info.Waktu_Akhir,
                flagCamera: act.third_party_info.Flag_Camera === 'Y' || act.third_party_info.Flag_Camera === true,
                oldToken: act.third_party_info.Id_Ujian_Token,
            };

            this.showReassignModal = true;
        },

        disabledDateReassignAkhir(time) {
            if (!this.reassignData.waktuMulai) return false;
            const startDate = new Date(this.reassignData.waktuMulai);
            startDate.setHours(0, 0, 0, 0);
            return time.getTime() < startDate.getTime();
        },

        async submitReassign() {
            if (!this.reassignData.idPaketSoal || !this.reassignData.waktuMulai) {
                ElMessage.warning('Paket Soal dan Waktu Mulai wajib diisi!');
                return;
            }

            if (this.reassignData.waktuAkhir) {
                const start = new Date(this.reassignData.waktuMulai).getTime();
                const end = new Date(this.reassignData.waktuAkhir).getTime();
                if (end <= start) {
                    ElMessage.error('Waktu Akhir harus lebih besar dari Waktu Mulai!');
                    return;
                }
            }

            this.loading.reassign = true;
            try {
                const payload = {
                    id_penjadwalan_det: this.reassignData.idPenjadwalanDet,
                    kode_karyawan: this.reassignData.kodeKaryawan,
                    id_master_ujian: this.reassignData.idPaketSoal,
                    waktu_mulai: this.reassignData.waktuMulai,
                    waktu_akhir: this.reassignData.waktuAkhir,
                    flag_camera: this.reassignData.flagCamera, // Ini sekarang sudah dikirim sebagai boolean true/false
                    old_token: this.reassignData.oldToken,
                    User_Agent: this.getUserAgentPayload(),
                };

                const response = await axios.post('/api/v1/lms/penjadwalan/reassign-third-party', payload);

                if (response.data.success) {
                    const queued = response.status === 202 || response.data.result?.status === 'QUEUED';
                    ElMessage[queued ? 'info' : 'success'](
                        queued
                            ? 'Permintaan pengajuan ulang diterima dan sedang diproses.'
                            : 'Berhasil mengajukan ulang ujian!',
                    );
                    this.showReassignModal = false;
                    this.getDataCurrent(this.pagination.page, true);
                }
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal mengajukan ulang ujian.');
            } finally {
                this.loading.reassign = false;
            }
        },
        async handleSelectAll(val) {
            if (val) {
                this.isFetchingAllIds = true;
                try {
                    const response = await axios.get('/api/v1/lms/penjadwalan/all-ids', {
                        params: this.buildFilterParams(),
                    });

                    if (response.data.success) {
                        this.selectedJadwal = response.data.result;
                    }
                } catch (error) {
                    ElMessage.error('Gagal mengambil seluruh data penjadwalan');
                    this.selectedJadwal = [];
                } finally {
                    this.isFetchingAllIds = false;
                }
            } else {
                this.selectedJadwal = [];
            }
        },
        async processBulkAction(action) {
            const isMencabut = action === 'Y';
            const textAction = isMencabut ? 'Mencabut' : 'Membuka';
            const total = this.selectedJadwal.length;

            try {
                await ElMessageBox.confirm(
                    `Apakah Anda yakin ingin <b>${textAction}</b> akses untuk <b class="text-primary">${total} Karyawan</b> yang dipilih?`,
                    'Konfirmasi Aksi Massal',
                    {
                        dangerouslyUseHTMLString: true,
                        confirmButtonText: `Ya, ${textAction}`,
                        cancelButtonText: 'Batal',
                        confirmButtonClass: isMencabut ? 'el-button--danger' : 'el-button--success',
                        type: isMencabut ? 'error' : 'success',
                    },
                );

                this.isBulkLoading = true;

                const response = await axios.post('/api/v1/lms/penjadwalan/bulk-toggle', {
                    ids: this.selectedJadwal,
                    action: action,
                });

                if (response.data.success) {
                    ElMessage.success(response.data.message);
                    this.selectedJadwal = [];
                    this.getDataCurrent(this.pagination.page, true);
                }
            } catch (error) {
                if (error !== 'cancel') {
                    ElMessage.error(error.response?.data?.message || 'Terjadi kesalahan saat memproses data.');
                }
            } finally {
                this.isBulkLoading = false;
            }
        },
        restoreTasks() {
            const storedTasks = localStorage.getItem('lms_active_tasks');
            if (storedTasks) {
                try {
                    this.uploadTasks = JSON.parse(storedTasks);
                    this.uploadTasks.forEach((task) => {
                        if (task.status === 'processing' || task.status === 'completed_waiting') {
                            this.pollProgress(task.id);
                        }
                    });

                    if (this.uploadTasks.length > 0) {
                        this.isUploadWidgetMinimized = false;
                    }
                } catch (e) {
                    localStorage.removeItem('lms_active_tasks');
                }
            }
        },

        trackProgress(processId, title) {
            const exists = this.uploadTasks.find((t) => t.id === processId);
            if (!exists) {
                this.uploadTasks.push({
                    id: processId,
                    title: title,
                    progress: 0,
                    targetProgress: 0,
                    status: 'processing',
                    message: '',
                });
            }
            this.isUploadWidgetMinimized = false;
            this.pollProgress(processId);
        },

        pollProgress(processId) {
            if (this.taskIntervals[processId]) return;

            this.taskIntervals[processId] = { animator: null, fetcher: null };

            this.taskIntervals[processId].animator = setInterval(() => {
                let currentTask = this.uploadTasks.find((t) => t.id === processId);

                if (!currentTask) {
                    this.clearTaskIntervals(processId);
                    return;
                }

                if (currentTask.targetProgress > 0) {
                    if (currentTask.progress < currentTask.targetProgress) {
                        let step = (currentTask.targetProgress - currentTask.progress) * 0.1;
                        if (step < 0.5) step = 0.5;
                        currentTask.progress += step;
                        if (currentTask.progress > currentTask.targetProgress) {
                            currentTask.progress = currentTask.targetProgress;
                        }
                    }
                } else {
                    if (currentTask.progress < 18) {
                        currentTask.progress += 0.15;
                    }
                }

                if (currentTask.progress >= 100 && currentTask.status === 'completed_waiting') {
                    currentTask.status = 'completed';
                    this.clearTaskIntervals(processId);
                    this.getDataCurrent(1, true);
                    ElMessage.success({
                        message: `${currentTask.title} berhasil diselesaikan sepenuhnya!`,
                        type: 'success',
                        duration: 5000,
                    });
                }
            }, 40);

            this.taskIntervals[processId].fetcher = setInterval(async () => {
                try {
                    const res = await axios.get(`/api/v1/lms/penjadwalan/progress/${processId}`);
                    const data = res.data;
                    let currentTask = this.uploadTasks.find((t) => t.id === processId);

                    if (currentTask) {
                        currentTask.targetProgress = data.progress;

                        if (data.status === 'completed' || data.progress >= 100) {
                            currentTask.targetProgress = 100;
                            currentTask.status = 'completed_waiting';
                            clearInterval(this.taskIntervals[processId].fetcher);
                        } else if (data.status === 'error') {
                            currentTask.status = 'error';
                            currentTask.message = data.message || 'Terjadi kesalahan pada Server';
                            this.clearTaskIntervals(processId);
                        }
                    } else {
                        this.clearTaskIntervals(processId);
                    }
                } catch (err) {
                    if (err.response && err.response.status === 404) {
                        let currentTask = this.uploadTasks.find((t) => t.id === processId);
                        if (currentTask && currentTask.status === 'processing') {
                            currentTask.status = 'error';
                            currentTask.message = 'Sesi habis atau antrean gagal ditemukan.';
                        }
                        this.clearTaskIntervals(processId);
                    }
                }
            }, 1500);
        },

        clearTaskIntervals(processId) {
            if (this.taskIntervals[processId]) {
                clearInterval(this.taskIntervals[processId].animator);
                clearInterval(this.taskIntervals[processId].fetcher);
                delete this.taskIntervals[processId];
            }
        },
        toggleKaryawan(id) {
            const idx = this.formSchedule.karyawan.indexOf(id);
            if (idx > -1) {
                this.formSchedule.karyawan.splice(idx, 1);
            } else {
                const emp = this.listKaryawan.find((k) => k.value === id);
                if (emp && emp.raw.Total_Assign > 0) {
                    let htmlMsg = `
                        <div class="text-center mb-3">
                            <i class="bi bi-info-circle-fill text-info" style="font-size: 3.5rem; text-shadow: 0px 4px 10px rgba(14, 165, 233, 0.3);"></i>
                            <h5 class="fw-bolder mt-2 text-dark">Jadwalkan Ulang?</h5>
                        </div>
                        <p class="text-secondary text-center" style="font-size: 13.5px; line-height: 1.6;">
                            Karyawan <b class="text-dark">${emp.raw.Nama}</b> sudah pernah di assign ke training <b class="text-primary">${this.selectedTrainingName}</b> sebanyak <span class="badge bg-warning text-dark border">${emp.raw.Total_Assign}x</span>.
                            <br><br>
                            Apakah Anda yakin tetap ingin menjadwalkan ulang karyawan ini?
                        </p>
                    `;

                    ElMessageBox.confirm(htmlMsg, '', {
                        dangerouslyUseHTMLString: true,
                        confirmButtonText: 'Ya, Jadwalkan Ulang',
                        cancelButtonText: 'Batal',
                        confirmButtonClass: 'el-button--primary fw-bold px-4',
                        cancelButtonClass: 'el-button--default fw-bold px-4',
                        customClass: 'rounded-4 shadow-lg border-0 modal-bouncing',
                        center: true,
                        showClose: false,
                    })
                        .then(() => {
                            this.formSchedule.karyawan.push(id);
                        })
                        .catch(() => {});
                } else {
                    this.formSchedule.karyawan.push(id);
                }
            }
        },
        disabledDateMulaiThird(time) {
            if (!this.formSchedule.waktuMulai) return false;
            const enrollDate = new Date(this.formSchedule.waktuMulai);
            enrollDate.setHours(0, 0, 0, 0);
            return time.getTime() < enrollDate.getTime();
        },
        disabledDateAkhirThird(time, kodeAct) {
            const waktuMulaiRef = this.formSchedule.thirdPartyWaktuMulai[kodeAct] || this.formSchedule.waktuMulai;
            if (!waktuMulaiRef) return false;

            const refDate = new Date(waktuMulaiRef);
            refDate.setHours(0, 0, 0, 0);
            return time.getTime() < refDate.getTime();
        },
        validateWaktuMulaiThird(selectedVal, kodeAct, namaAct) {
            if (!selectedVal || !this.formSchedule.waktuMulai) return;

            const enrollTime = new Date(this.formSchedule.waktuMulai).getTime();
            const selectedTime = new Date(selectedVal).getTime();

            if (selectedTime < enrollTime) {
                ElMessage.error(`Waktu Mulai ${namaAct} tidak boleh mendahului Jam Mulai Akses utama!`);
                this.formSchedule.thirdPartyWaktuMulai[kodeAct] = null;
                return;
            }

            const wAkhir = this.formSchedule.thirdPartyWaktuAkhir[kodeAct];
            if (wAkhir && selectedTime >= new Date(wAkhir).getTime()) {
                ElMessage.warning(`Waktu Akhir ${namaAct} direset otomatis karena mendahului Waktu Mulai yang baru.`);
                this.formSchedule.thirdPartyWaktuAkhir[kodeAct] = null;
            }
        },
        validateWaktuAkhirThird(selectedVal, kodeAct, namaAct) {
            if (!selectedVal) return;

            const enrollTime = new Date(this.formSchedule.waktuMulai).getTime();
            const startTimeRef = this.formSchedule.thirdPartyWaktuMulai[kodeAct];
            const startTime = startTimeRef ? new Date(startTimeRef).getTime() : enrollTime;
            const selectedTime = new Date(selectedVal).getTime();

            if (selectedTime < enrollTime) {
                ElMessage.error(`Waktu Akhir ${namaAct} tidak boleh mendahului Jam Mulai Akses utama!`);
                this.formSchedule.thirdPartyWaktuAkhir[kodeAct] = null;
                return;
            }

            if (selectedTime <= startTime) {
                ElMessage.error(`Waktu Akhir ${namaAct} harus lebih besar dari Waktu Mulai aktivitas ini!`);
                this.formSchedule.thirdPartyWaktuAkhir[kodeAct] = null;
            }
        },
        getDynamicIcon(tipe) {
            if (!tipe) return 'bi bi-file-earmark-text';
            const t = tipe.toLowerCase();
            if (t.includes('pdf')) return 'bi bi-filetype-pdf';
            if (t.includes('video') || t.includes('mp4')) return 'bi bi-play-btn-fill';
            if (t.includes('doc') || t.includes('word')) return 'bi bi-filetype-docx';
            if (t.includes('xls') || t.includes('excel')) return 'bi bi-filetype-xlsx';
            if (t.includes('ppt') || t.includes('powerpoint')) return 'bi bi-filetype-pptx';
            return 'bi bi-file-earmark-text';
        },
        getDynamicBg(kode, tipe) {
            if (kode !== 'MDL') return 'bg-soft-indigo';
            if (!tipe) return 'bg-soft-default';
            const t = tipe.toLowerCase();
            if (t.includes('pdf')) return 'bg-soft-danger';
            if (t.includes('video')) return 'bg-soft-primary';
            if (t.includes('doc')) return 'bg-soft-info';
            if (t.includes('xls')) return 'bg-soft-success';
            if (t.includes('ppt')) return 'bg-soft-warning';
            return 'bg-soft-default';
        },
        maskText(text) {
            if (!text) return '-';
            return '********';
        },
        getUserAgentPayload() {
            return {
                userAgent: navigator.userAgent || null,
                platform: navigator.platform || null,
                language: navigator.language || null,
                screen: {
                    width: window.screen.width,
                    height: window.screen.height,
                },
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
                timestamp: new Date().toISOString(),
            };
        },
        handleTrainingChange() {
            this.formSchedule.karyawan = [];

            if (this.formSchedule.training) {
                this.fetchKaryawan();
                const selectedPaket = this.listTraining.find((p) => p.Id_Master_Paket === this.formSchedule.training);
                let activities = [];
                if (selectedPaket && selectedPaket.Trainings) {
                    selectedPaket.Trainings.forEach((training) => {
                        if (training.Details) {
                            training.Details.forEach((detail) => {
                                let clonedDetail = JSON.parse(JSON.stringify(detail));
                                clonedDetail.Id_Master_Paket_Detail = training.Id_Master_Paket_Detail;
                                activities.push(clonedDetail);
                            });
                        }
                    });
                }
                this.currentActivities = activities;
            } else {
                this.currentActivities = [];
                this.listKaryawan = [];
            }
        },
        handleSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.selectedJadwal = [];
                this.getDataCurrent(1);
            }, 500);
        },
        changePage(page) {
            this.getDataCurrent(page);
        },
        removeSub(subIndex) {
            const removed = this.currentActivities.splice(subIndex, 1)[0];
            this.removedItems.push({ type: 'sub', subIndex, item: removed });
        },
        removeActivity(subIndex, actIndex) {
            const removed = this.currentActivities[subIndex].Aktivitas.splice(actIndex, 1)[0];
            this.removedItems.push({ type: 'act', subIndex, actIndex, item: removed });
        },
        removeMdlDetail(subIndex, actIndex, detIndex) {
            const removed = this.currentActivities[subIndex].Aktivitas[actIndex].Details.splice(detIndex, 1)[0];
            this.removedItems.push({ type: 'mdl_detail', subIndex, actIndex, detIndex, item: removed });
        },
        undoRemove() {
            if (this.removedItems.length === 0) return;
            const last = this.removedItems.pop();

            if (last.type === 'sub') {
                this.currentActivities.splice(last.subIndex, 0, last.item);
            } else if (last.type === 'act') {
                if (this.currentActivities[last.subIndex]) {
                    this.currentActivities[last.subIndex].Aktivitas.splice(last.actIndex, 0, last.item);
                }
            } else if (last.type === 'mdl_detail') {
                if (
                    this.currentActivities[last.subIndex] &&
                    this.currentActivities[last.subIndex].Aktivitas[last.actIndex]
                ) {
                    this.currentActivities[last.subIndex].Aktivitas[last.actIndex].Details.splice(
                        last.detIndex,
                        0,
                        last.item,
                    );
                }
            }
        },
        openModal(item = null) {
            this.activeStep = 1;
            this.removedItems = [];
            this.currentActivities = [];

            if (item) {
                this.isEditing = true;
                this.formSchedule = {
                    id_penjadwalan: item.Id_Penjadwalan,
                    karyawan: [item.Kode_Karyawan],
                    namaKaryawanEdit: item.Nama_Karyawan,
                    training: item.Id_Master_Paket || null,
                    waktuMulai: item.Waktu_Enroll,
                    waktuAkhir: null,
                    thirdPartyPayloads: {},
                    thirdPartyWaktuMulai: {},
                    thirdPartyWaktuAkhir: {},
                    thirdPartyCamera: {},
                    rumusPenilaian: item.Id_Master_Rumus || null,
                    flagLearningGain: item.Flag_Learning_Gain || 'T',
                };
                if (item.Id_Master_Paket && !this.listTraining.some((p) => p.Id_Master_Paket == item.Id_Master_Paket)) {
                    this.fetchMasterTraining('', 1, false);
                }

                if (this.formSchedule.training) {
                    const selectedPaket = this.listTraining.find(
                        (p) => p.Id_Master_Paket == this.formSchedule.training,
                    );
                    let activities = [];
                    if (selectedPaket && selectedPaket.Trainings) {
                        selectedPaket.Trainings.forEach((training) => {
                            if (training.Details) {
                                training.Details.forEach((detail) => {
                                    let clonedDetail = JSON.parse(JSON.stringify(detail));
                                    clonedDetail.Id_Master_Paket_Detail = training.Id_Master_Paket_Detail;
                                    activities.push(clonedDetail);
                                });
                            }
                        });
                    }
                    this.currentActivities = activities;

                    let savedAktivitasIds = [];

                    item.Training_List?.forEach((tr) => {
                        tr.materi_detail?.forEach((sub) => {
                            sub.materi_det?.forEach((act) => {
                                if (act.Id_Materi_Det) {
                                    savedAktivitasIds.push(act.Id_Materi_Det);
                                }
                                if (act.Flag_Third_Party === 'Y' && act.third_party_info) {
                                    this.formSchedule.thirdPartyWaktuMulai[act.Kode_Aktivitas] =
                                        act.third_party_info.Waktu_Mulai;
                                    this.formSchedule.thirdPartyWaktuAkhir[act.Kode_Aktivitas] =
                                        act.third_party_info.Waktu_Akhir;
                                    this.formSchedule.thirdPartyPayloads[act.Kode_Aktivitas] =
                                        act.third_party_info.Id_Master_Ujian;
                                    this.formSchedule.thirdPartyCamera[act.Kode_Aktivitas] =
                                        act.third_party_info.Flag_Camera === 'Y';
                                }
                            });
                        });
                    });

                    for (let i = this.currentActivities.length - 1; i >= 0; i--) {
                        let sub = this.currentActivities[i];
                        if (sub.Aktivitas) {
                            for (let j = sub.Aktivitas.length - 1; j >= 0; j--) {
                                let act = sub.Aktivitas[j];
                                if (!savedAktivitasIds.includes(act.Id_Materi_Det)) {
                                    this.removeActivity(i, j);
                                    continue;
                                }
                                if (act.Kode_Aktivitas === 'MDL' && act.Details) {
                                    for (let k = act.Details.length - 1; k >= 0; k--) {
                                        let det = act.Details[k];
                                        if (!savedAktivitasIds.includes(det.Id_Materi_Det)) {
                                            this.removeMdlDetail(i, j, k);
                                        }
                                    }
                                    if (act.Details.length === 0) {
                                        this.removeActivity(i, j);
                                    }
                                }
                            }
                        }

                        if (!sub.Aktivitas || sub.Aktivitas.length === 0) {
                            this.removeSub(i);
                        }
                    }
                }
            } else {
                this.isEditing = false;
                this.fetchMasterTraining('', 1, false);
                this.formSchedule = {
                    id_penjadwalan: null,
                    karyawan: [],
                    training: null,
                    waktuMulai: null,
                    waktuAkhir: null,
                    thirdPartyPayloads: {},
                    thirdPartyWaktuMulai: {},
                    thirdPartyWaktuAkhir: {},
                    thirdPartyCamera: {},
                    rumusPenilaian: null,
                    flagLearningGain: 'T',
                };
            }
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            setTimeout(() => {
                this.isEditing = false;
                this.activeStep = 1;
                this.formSchedule = {
                    id_penjadwalan: null,
                    karyawan: [],
                    namaKaryawanEdit: '',
                    training: null,
                    waktuMulai: null,
                    waktuAkhir: null,
                    thirdPartyPayloads: {},
                    thirdPartyWaktuMulai: {},
                    thirdPartyWaktuAkhir: {},
                    thirdPartyCamera: {},
                    rumusPenilaian: null,
                    flagLearningGain: 'T',
                };
                this.removedItems = [];
            }, 300);
        },
        handleNextOrSubmit() {
            if (this.activeStep === 1) {
                if (
                    !this.formSchedule.karyawan.length ||
                    !this.formSchedule.training ||
                    !this.formSchedule.waktuMulai
                ) {
                    ElMessage.warning('Lengkapi data utama dengan benar!');
                    return;
                }
                this.activeStep = 2;
            } else {
                this.submitForm();
            }
        },
        formatDateShort(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const options = { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Intl.DateTimeFormat('id-ID', options).format(date);
        },
        formatDateOnly(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Intl.DateTimeFormat('id-ID', options).format(date);
        },
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const options = { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Intl.DateTimeFormat('id-ID', options).format(date).replace(' pukul', ',');
        },
        formatDateTimeFull(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const options = { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Intl.DateTimeFormat('id-ID', options).format(date).replace(' pukul', '');
        },
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        async copyToClipboard(text, labelTitle) {
            if (!text) {
                ElMessage.warning(`${labelTitle} kosong atau tidak tersedia.`);
                return;
            }

            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(text);
                    ElMessage.success(`${labelTitle} berhasil disalin ke clipboard!`);
                } catch (err) {
                    ElMessage.error(`Gagal menyalin ${labelTitle}`);
                }
            } else {
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;

                    textArea.style.position = 'absolute';
                    textArea.style.left = '-999999px';

                    document.body.appendChild(textArea);
                    textArea.select();

                    const successful = document.execCommand('copy');
                    document.body.removeChild(textArea);

                    if (successful) {
                        ElMessage.success(`${labelTitle} berhasil disalin ke clipboard!`);
                    } else {
                        ElMessage.error(`Gagal menyalin ${labelTitle}`);
                    }
                } catch (err) {
                    ElMessage.error(`Gagal menyalin ${labelTitle}`);
                }
            }
        },
        async fetchMasterTraining(search = '', page = 1, append = false) {
            if (append) {
                this.loadingMoreTraining = true;
            } else {
                this.loading.masterTraining = true;
            }
            try {
                const response = await axios.get('/api/v1/lms/penjadwalan/options/master-paket/training', {
                    params: {
                        search: search || undefined,
                        page: page,
                        limit: 20,
                        selected_id: this.formSchedule.training || undefined,
                    },
                });
                if (response.status === 200 && response.data?.result) {
                    const res = response.data.result;
                    const items = Array.isArray(res) ? res : (res.items || []);
                    this.totalTraining = res.total ?? items.length;
                    this.hasMoreTraining = res.has_more ?? false;
                    this.pageTraining = page;
                    this.trainingSearchQuery = search;

                    if (append) {
                        const existingIds = new Set(this.listTraining.map((t) => t.Id_Master_Paket));
                        const newItems = items.filter((t) => !existingIds.has(t.Id_Master_Paket));
                        this.listTraining = [...this.listTraining, ...newItems];
                    } else {
                        this.listTraining = items;
                    }
                }
            } catch (error) {
                if (!append) this.listTraining = [];
            } finally {
                this.loading.masterTraining = false;
                this.loadingMoreTraining = false;
            }
        },
        handleTrainingRemoteSearch(query) {
            clearTimeout(this.trainingSearchDebounce);
            this.trainingSearchDebounce = setTimeout(() => {
                this.fetchMasterTraining(query, 1, false);
            }, 300);
        },
        handleTrainingClear() {
            this.fetchMasterTraining('', 1, false);
        },
        loadMoreTraining() {
            if (this.hasMoreTraining && !this.loadingMoreTraining) {
                this.fetchMasterTraining(this.trainingSearchQuery, this.pageTraining + 1, true);
            }
        },
        async fetchMasterSoal(search = '') {
            this.loading.listDataMasterUjian = true;
            try {
                const response = await axios.get(`/api/v1/lms/penjadwalan/options/kategori-ujian`, {
                    params: {
                        search: search || undefined,
                    },
                });
                if (response.status === 200 && response.data?.result) {
                    const list = Array.isArray(response.data.result) ? response.data.result : [];
                    this.listDataMasterUjian = list.map((item) => ({
                        value: item.Id_Master_Ujian,
                        label: item.Nama_Ujian,
                        raw: item,
                    }));
                }
            } catch (error) {
                this.listDataMasterUjian = [];
            } finally {
                this.loading.listDataMasterUjian = false;
            }
        },
        handleSoalRemoteSearch(query) {
            clearTimeout(this.soalSearchDebounce);
            this.soalSearchDebounce = setTimeout(() => {
                this.fetchMasterSoal(query);
            }, 300);
        },
        async fetchMasterLevel() {
            try {
                const response = await axios.get('/api/v1/lms/penjadwalan/options/level');
                if (response.data.success) {
                    this.listLevels = response.data.result;
                }
            } catch (error) {
                console.error('Gagal mengambil data level', error);
            }
        },
        async fetchKaryawan() {
            if (!this.formSchedule.training) return;
            this.loading.karyawan = true;
            try {
                const response = await axios.get('/api/v1/lms/penjadwalan/options/karyawan-current', {
                    params: {
                        filter: this.activeEmployeeFilter,
                        filter_levels: this.selectedLevels,
                        filter_lokasi: this.selectedLokasi,
                        id_master_paket: this.formSchedule.training,
                    },
                });
                if (response.status === 200 && response.data?.result) {
                    this.listKaryawan = response.data.result.map((item) => ({
                        value: item.Kode_Karyawan,
                        label: `${item.Nama} ~ ${item.HP}`,
                        raw: item,
                    }));
                } else {
                    this.listKaryawan = [];
                }
            } catch (error) {
                this.listKaryawan = [];
            } finally {
                this.loading.karyawan = false;
            }
        },
        // Opsi lokasi kerja untuk filter peserta di modal Tambah Penjadwalan:
        // seluruh cabang karyawan aktif (termasuk yang belum punya penjadwalan).
        async fetchLokasiKaryawan() {
            try {
                const response = await axios.get('/api/v1/lms/penjadwalan/options/lokasi-karyawan');
                if (response.status === 200 && response.data?.result) {
                    this.listLokasiKaryawan = response.data.result.map((item) => item.Lokasi);
                } else {
                    this.listLokasiKaryawan = [];
                }
            } catch (error) {
                this.listLokasiKaryawan = [];
            }
        },
        // Opsi lokasi kerja hanya berisi cabang yang benar-benar punya penjadwalan.
        async fetchLokasi() {
            try {
                const response = await axios.get('/api/v1/lms/penjadwalan/options/lokasi-kerja');
                if (response.status === 200 && response.data?.result) {
                    this.listLokasi = response.data.result.map((item) => ({
                        value: item.Lokasi,
                        label: item.Lokasi,
                    }));
                } else {
                    this.listLokasi = [];
                }
            } catch (error) {
                this.listLokasi = [];
            }
        },
        resetFilters() {
            this.searchQuery = '';
            this.filterLokasi = [];
            this.filterModule = [];
            this.filterMateri = [];
            this.filterStatusAkses = 'all';
            this.filterDateMode = 'enroll';
            this.filterDateRange = [];
            this.handleSearch();
        },
        async fetchMasterRumus() {
            this.loading.masterRumus = true;
            try {
                const response = await axios.get(`/api/v1/lms/penjadwalan/options/formula`);
                if (response.status === 200 && response.data?.result) {
                    this.listMasterRumus = response.data.result;
                }
            } catch (error) {
                this.listMasterRumus = [];
            } finally {
                this.loading.masterRumus = false;
            }
        },
        buildFilterParams() {
            return {
                q: this.searchQuery,
                status: this.filterStatusAkses,
                materi: this.filterMateri.join(','),
                lokasi: this.filterLokasi.join(','),
                module: this.filterModule.join(','),
                date_mode: this.filterDateMode,
                date_start: this.filterDateRange?.[0] || '',
                date_end: this.filterDateRange?.[1] || '',
            };
        },
        async getDataCurrent(page = 1, isSilent = false) {
            if (!isSilent) {
                this.loading.listData = true;
            }
            try {
                const response = await axios.get('/api/v1/lms/penjadwalan/current', {
                    params: {
                        limit: this.pagination.per_page,
                        page: page,
                        ...this.buildFilterParams(),
                    },
                });
                if (response.data.success) {
                    this.listData = response.data.result;
                    const pageInfo = response.data.pagination || response.data;
                    this.pagination = {
                        page: pageInfo.current_page || pageInfo.page || 1,
                        totalPage: pageInfo.total_pages || pageInfo.total_page || 1,
                        per_page: pageInfo.per_page || 10,
                        totalData: pageInfo.total || pageInfo.total_data || 0,
                    };
                }
            } catch (error) {
                this.listData = [];
            } finally {
                if (!isSilent) {
                    this.loading.listData = false;
                }
            }
        },
        async submitForm() {
            let missingConfigs = [];
            let invalidTimeConfigs = [];
            let formattedSubMateri = [];

            const waktuEnroll = new Date(this.formSchedule.waktuMulai).getTime();

            this.currentActivities.forEach((sub, subIndex) => {
                let subData = {
                    Id_Master_Paket_Detail: sub.Id_Master_Paket_Detail,
                    Id_Materi_Detail: sub.Id_Materi_Detail,
                    Nama_Sub: sub.Nama_Sub,
                    Urutan: subIndex + 1,
                    Aktivitas: [],
                };

                if (sub.Aktivitas && sub.Aktivitas.length > 0) {
                    sub.Aktivitas.forEach((act) => {
                        if (act.Flag_Third_Party === 'Y' && !this.formSchedule.thirdPartyPayloads[act.Kode_Aktivitas]) {
                            missingConfigs.push(act.Nama_Aktivitas);
                        }

                        if (act.Kode_Aktivitas === 'MDL' && (!act.Details || act.Details.length === 0)) {
                            return;
                        }

                        let activityData = {
                            id_master_aktivitas: act.Id_Master_Aktivitas || act.Id_Materi_Det,
                            kode_aktivitas: act.Kode_Aktivitas,
                            nama_aktivitas: act.Nama_Aktivitas,
                            flag_third_party: act.Flag_Third_Party || 'T',
                            id_materi_det: act.Id_Materi_Det,
                        };

                        if (act.Kode_Aktivitas === 'MDL' && act.Details) {
                            activityData.detail_modul = act.Details;
                        }

                        if (act.Flag_Third_Party === 'Y') {
                            const wktMulaiThird = this.formSchedule.thirdPartyWaktuMulai[act.Kode_Aktivitas];
                            const wktAkhirThird = this.formSchedule.thirdPartyWaktuAkhir[act.Kode_Aktivitas];
                            const isCameraActive = this.formSchedule.thirdPartyCamera[act.Kode_Aktivitas];

                            if (wktMulaiThird) {
                                const waktuMulaiAktivitas = new Date(wktMulaiThird).getTime();
                                if (waktuMulaiAktivitas < waktuEnroll) {
                                    invalidTimeConfigs.push(act.Nama_Aktivitas);
                                }
                            }

                            activityData.id_paket_soal =
                                this.formSchedule.thirdPartyPayloads[act.Kode_Aktivitas] || null;
                            activityData.waktu_mulai_thirdparty = wktMulaiThird || null;
                            activityData.waktu_akhir_thirdparty = wktAkhirThird || null;
                            activityData.flag_camera = isCameraActive ? 'Y' : null;
                        }
                        subData.Aktivitas.push(activityData);
                    });
                }

                if (subData.Aktivitas.length > 0) {
                    formattedSubMateri.push(subData);
                }
            });

            if (missingConfigs.length > 0) {
                ElMessage.error(`Pilih paket soal untuk aktivitas: ${missingConfigs.join(', ')}`);
                return;
            }

            if (invalidTimeConfigs.length > 0) {
                ElMessage.error(
                    `Waktu mulai untuk aktivitas: ${invalidTimeConfigs.join(', ')} tidak boleh mendahului Jam Mulai Akses utama!`,
                );
                return;
            }

            const payload = {
                id_penjadwalan: this.formSchedule.id_penjadwalan,
                karyawan: this.formSchedule.karyawan,
                id_training: this.formSchedule.training,
                waktu_mulai: this.formSchedule.waktuMulai,
                waktu_akhir: this.formSchedule.waktuAkhir,
                id_rumus_penilaian: this.formSchedule.rumusPenilaian,
                Flag_Learning_Gain: this.formSchedule.flagLearningGain,
                User_Agent: JSON.stringify(this.getUserAgentPayload()),
                sub_materi: formattedSubMateri,
            };

            this.loading.saveToDatabase = true;
            try {
                const endpoint = this.isEditing ? '/api/v1/lms/penjadwalan/update' : '/api/v1/lms/penjadwalan/store';
                const response = await axios.post(endpoint, payload);

                if (response.data.success) {
                    const processId = response.data.result?.process_id || response.data.data?.process_id;
                    if (processId) {
                        this.trackProgress(processId, `Penjadwalan: ${this.selectedTrainingName}`);
                    }

                    if (
                        !this.isEditing &&
                        response.data.result &&
                        (response.data.result.berhasil || response.data.result.sudah_selesai)
                    ) {
                        const resData = response.data.result;
                        let msgHtml = `
                            <style scoped>
                                .laporan-scroll::-webkit-scrollbar { width: 6px; }
                                .laporan-scroll::-webkit-scrollbar-track { background: var(--hcis-slate-50); border-radius: 4px; }
                                .laporan-scroll::-webkit-scrollbar-thumb { background: var(--hcis-slate-300); border-radius: 4px; }
                                .laporan-scroll::-webkit-scrollbar-thumb:hover { background: var(--hcis-slate-400); }
                            </style>
                            <div class="text-start" style="font-family: inherit;">
                                <p class="text-secondary mb-3" style="font-size: 13px;">Berikut adalah rincian hasil penugasan (assign) training kepada karyawan:</p>
                        `;

                        if (resData.berhasil && resData.berhasil.length > 0) {
                            msgHtml += `
                                <div class="mb-3 rounded-3" style="border: 1px solid #dcfce7; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                    <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background-color: #f0fdf4; border-bottom: 1px solid #dcfce7;">
                                        <span class="fw-bold d-flex align-items-center gap-2" style="color: #16a34a; font-size: 13px;">
                                            <i class="bi bi-check-circle-fill" style="margin-top: -8px;"></i> Berhasil Di-assign
                                        </span>
                                        <span class="badge" style="background-color: #16a34a; font-size: 11px;">${resData.berhasil.length} Karyawan</span>
                                    </div>
                                    <div class="p-2 laporan-scroll" style="max-height: 160px; overflow-y: auto; background-color: #ffffff;">
                            `;
                            resData.berhasil.forEach((k) => {
                                msgHtml += `
                                        <div class="d-flex align-items-center gap-2 py-1 px-2 mb-1 rounded" style="background-color: #f8fafc;">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; background-color: #dcfce7; color: #16a34a;">
                                                <i class="bi bi-person-fill" style="font-size: 12px;"></i>
                                            </div>
                                            <span class="fw-medium" style="font-size: 12px; color: #334155;">${k}</span>
                                        </div>
                                `;
                            });
                            msgHtml += `
                                    </div>
                                </div>
                            `;
                        }

                        if (resData.sudah_selesai && resData.sudah_selesai.length > 0) {
                            msgHtml += `
                                <div class="mb-2 rounded-3" style="border: 1px solid #fef08a; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                    <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background-color: #fffbeb; border-bottom: 1px solid #fef08a;">
                                        <span class="fw-bold d-flex align-items-center gap-2" style="color: #d97706; font-size: 13px;">
                                            <i class="bi bi-exclamation-triangle-fill" style="margin-top: -8px;"></i> Dilewati (Sudah Selesai)
                                        </span>
                                        <span class="badge text-dark" style="background-color: #fbbf24; font-size: 11px;">${resData.sudah_selesai.length} Karyawan</span>
                                    </div>
                                    <div class="p-2 laporan-scroll" style="max-height: 160px; overflow-y: auto; background-color: #ffffff;">
                            `;
                            resData.sudah_selesai.forEach((k) => {
                                msgHtml += `
                                        <div class="d-flex align-items-center gap-2 py-1 px-2 mb-1 rounded" style="background-color: #fcfcfc;">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; background-color: #fef08a; color: #d97706;">
                                                <i class="bi bi-person-check-fill" style="font-size: 12px;"></i>
                                            </div>
                                            <span class="fw-medium" style="font-size: 12px; color: #475569;">${k}</span>
                                        </div>
                                `;
                            });
                            msgHtml += `
                                    </div>
                                </div>
                                <div class="mt-2 text-muted d-flex gap-1" style="font-size: 11px; line-height: 1.3;">
                                    <i class="bi bi-info-circle text-warning mt-1"></i>
                                    <span>Karyawan di atas sudah pernah menyelesaikan training ini, sehingga sistem otomatis melewatkannya.</span>
                                </div>
                            `;
                        }

                        msgHtml += `</div>`;

                        ElMessageBox.alert(msgHtml, 'Laporan Penjadwalan', {
                            dangerouslyUseHTMLString: true,
                            confirmButtonText: 'Tutup & Selesai',
                            customClass: 'rounded-4 shadow-lg border-0',
                            callback: () => {
                                if (!processId) {
                                    this.getDataCurrent(this.pagination.page);
                                }
                            },
                        });
                    } else if (this.isEditing && response.data.result?.sinkron_belum_terkirim > 0) {
                        // Tersimpan, tetapi sinkron ke HC Learn belum terkirim: sebutkan, jangan bilang beres.
                        ElMessage({ type: 'warning', message: response.data.message, duration: 8000, showClose: true });

                        if (!processId) {
                            this.getDataCurrent(this.pagination.page);
                        }
                    } else {
                        ElMessage.success(
                            this.isEditing ? 'Penjadwalan berhasil diperbarui' : 'Penjadwalan masuk ke antrean',
                        );

                        if (!processId) {
                            this.getDataCurrent(this.pagination.page);
                        }
                    }

                    this.closeModal();
                }
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal menyimpan data');
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        async toggleCabutAkses(jadwal) {
            const isMencabut = jadwal.Flag_Cabut_Akses !== 'Y';
            const actionText = isMencabut ? 'mencabut' : 'membuka kembali';

            const iconClass = isMencabut ? 'bi-shield-lock-fill' : 'bi-shield-check';
            const iconBgColor = isMencabut ? '#fee2e2' : '#dcfce7';
            const iconColor = isMencabut ? '#dc2626' : '#16a34a';
            const btnConfirmClass = isMencabut ? 'el-button--danger' : 'el-button--success';
            const titleText = isMencabut ? 'Cabut Akses Training?' : 'Buka Akses Training?';

            const customHTML = `
        <div class="text-center px-2 py-3">
            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"
                 style="width: 70px; height: 70px; background-color: ${iconBgColor}; color: ${iconColor};">
                <i class="bi ${iconClass}" style="font-size: 2.2rem; margin-left: -14px;"></i>
            </div>
            
            <h5 class="fw-bolder text-dark mb-2" style="font-size: 1.25rem;">${titleText}</h5>
            
            <p class="text-secondary mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                Apakah Anda yakin ingin <b>${actionText}</b> akses training untuk karyawan <br>
                <span class="text-primary fw-bold" style="font-size: 1.05rem;">${jadwal.Nama_Karyawan}</span>?
            </p>
            
            <div class="p-3 text-start rounded-3" style="background-color: #f8fafc; border: 1px dashed #cbd5e1;">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill text-muted mt-1"></i>
                    <span class="text-muted" style="font-size: 0.85rem; line-height: 1.5;">
                        ${
                            isMencabut
                                ? 'Karyawan <b>tidak akan bisa</b> mengakses materi dan ujian ini lagi sampai akses dibuka kembali.'
                                : 'Karyawan akan dapat <b>kembali mengakses</b> materi dan ujian sesuai jadwal yang berlaku.'
                        }
                    </span>
                </div>
            </div>
        </div>
    `;

            try {
                await ElMessageBox.confirm(customHTML, '', {
                    dangerouslyUseHTMLString: true,
                    confirmButtonText: isMencabut ? 'Ya, Cabut Akses' : 'Ya, Buka Akses',
                    cancelButtonText: 'Batal',
                    confirmButtonClass: `fw-bold px-4 ${btnConfirmClass}`,
                    cancelButtonClass: 'fw-bold px-4 text-muted',
                    center: true,
                    showClose: false,
                    customClass: 'rounded-4 shadow-lg border-0',
                });

                const payload = {
                    id_penjadwalan: jadwal.Id_Penjadwalan,
                };

                const response = await axios.post('/api/v1/lms/penjadwalan/toggle', payload);

                if (response.data.success) {
                    ElMessage({
                        message: response.data.message,
                        type: 'success',
                        icon: isMencabut ? 'Lock' : 'Unlock',
                    });
                    jadwal.Flag_Cabut_Akses = response.data.data.Flag_Cabut_Akses;
                }
            } catch (error) {
                if (error !== 'cancel') {
                    ElMessage.error(error.response?.data?.message || 'Terjadi kesalahan saat memproses data.');
                }
            }
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: #4052d6;
    --primary-light: var(--hcis-indigo-100);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: #f5a623;
    --danger: var(--hcis-red-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    --setup-bg: #fffdf5;
}

/* Memperbaiki tampilan default el-dialog biar lebih modern */
:deep(.custom-modal-reassign .el-dialog__header) {
    padding: 20px 24px;
    margin-right: 0;
    border-bottom: 1px solid var(--hcis-slate-100);
}

:deep(.custom-modal-reassign .el-dialog__title) {
    font-weight: 700;
    color: var(--hcis-slate-800);
    font-size: 1.15rem;
}

:deep(.custom-modal-reassign .el-dialog__body) {
    padding: 24px;
}

/* Memperbaiki dropdown El-Select yang isinya custom HTML */
:deep(.elegant-select .el-select-dropdown__item) {
    height: auto !important;
}

:deep(.elegant-select .el-select__wrapper.is-focused),
:deep(.elegant-select .el-select__wrapper.is-focus),
:deep(.elegant-select .el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px var(--hcis-indigo-600) inset !important;
}

/* Menyamakan Input Biasa dengan Element Plus */
.custom-premium-input {
    background-color: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.5rem;
    min-height: 42px; /* Set fix height */
    font-size: 0.95rem;
    transition: all 0.2s ease-in-out;
}
.custom-premium-input:focus {
    background-color: var(--hcis-white);
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    outline: none;
}

/* Meng-override el-select Element Plus agar senada dengan input Bootstrap */
:deep(.elegant-el-select .el-select__wrapper),
:deep(.elegant-el-select .el-input__wrapper) {
    background-color: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200) !important;
    box-shadow: none !important;
    border-radius: 0.5rem;
    min-height: 42px; /* Set fix height yang sama persis */
}
:deep(.elegant-el-select .el-select__wrapper:hover),
:deep(.elegant-el-select .el-select__wrapper.is-hovering),
:deep(.elegant-el-select .el-select__wrapper.is-focused),
:deep(.elegant-el-select .el-select__wrapper.is-focus),
:deep(.elegant-el-select .el-input__wrapper.is-focus),
:deep(.elegant-el-select .el-input__wrapper:hover) {
    background-color: var(--hcis-white);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1) !important;
    border-color: var(--hcis-indigo-200) !important;
}
:deep(.elegant-el-select .el-tag) {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 4px;
}
:deep(.elegant-el-select .el-tag__close) {
    color: var(--hcis-indigo-600);
}

/* Checkbox Style (Dari kode sebelumnya) */
.custom-checkbox-wrapper .premium-checkbox {
    width: 1.25rem;
    height: 1.25rem;
    border-color: var(--hcis-slate-300);
    border-radius: 4px;
    cursor: pointer;
}
.custom-checkbox-wrapper .premium-checkbox:checked {
    background-color: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
}
.premium-action-btn {
    border-radius: 8px;
    border: 1px solid transparent;
}
.btn-danger-soft {
    background-color: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.btn-success-soft {
    background-color: var(--hcis-green-100);
    color: var(--hcis-green-600);
}

/* Selection Toolbar (The Dynamic Card) */
.selection-toolbar {
    border-radius: 14px;
    transition: all 0.3s ease;
    min-height: 4.5rem;
}
.idle-selection {
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
}
.active-selection {
    background-color: #eff6ff; /* Soft Blue */
    border: 1px solid #bfdbfe;
}

/* Custom Checkbox (Bigger & Cleaner) */
.custom-check-all .form-check-input {
    width: 1.4rem;
    height: 1.4rem;
    border: 2px solid var(--hcis-slate-300);
    border-radius: 6px;
    transition: all 0.2s;
}
.custom-check-all .form-check-input:checked {
    background-color: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
}
.custom-check-all .form-check-input:focus {
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
}

/* Action Buttons */
.custom-action-btn {
    border-radius: 10px;
    font-size: 0.9rem;
    height: 2.75rem;
    transition:
        transform 0.1s,
        box-shadow 0.2s;
}
.custom-action-btn:active {
    transform: scale(0.97);
}

/* CSS Global khusus untuk ElMessageBox agar tidak terpengaruh scoped */
.modal-bouncing {
    animation: modalBounce 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border-radius: 16px !important;
}

@keyframes modalBounce {
    0% {
        transform: scale(0.9);
        opacity: 0;
    }
    50% {
        transform: scale(1.02);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Mengatur container tombol agar renggang dan elegan */
.modal-bouncing .el-message-box__btns {
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    gap: 12px !important;
    margin-top: 15px !important;
}

/* Menghilangkan margin bawaan Element Plus dan merapikan ukuran */
.modal-bouncing .el-message-box__btns .el-button {
    margin: 0 !important;
    border-radius: 8px !important;
    padding: 10px 20px !important;
    height: auto !important;
    transition: all 0.2s ease-in-out !important;
}

.modal-bouncing .el-message-box__btns .el-button:hover {
    transform: translateY(-2px);
}

/* Filter Type Cards */
.filter-type-card {
    background-color: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.filter-type-card:hover {
    background-color: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    transform: translateY(-2px);
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.05),
        0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.filter-type-card .icon-filter-box {
    width: 44px;
    height: 44px;
    background-color: #fff;
    color: var(--hcis-slate-500);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

/* State Filter Aktif dengan Custom Gradient */
.filter-type-card.active {
    /* Background sangat soft agar teks tetap terbaca, senada dengan gradient utama */
    background: linear-gradient(135deg, #f5f7fa 0%, #f3f0f8 100%);
    border-color: var(--hcis-legacy-violet-700);
    box-shadow: 0 4px 12px rgba(118, 75, 162, 0.15);
}

.filter-type-card.active .icon-filter-box {
    /* Warna gradient utamanya dimasukkan ke icon box */
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    color: var(--hcis-white);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

.filter-type-card.active .filter-title {
    color: var(--hcis-legacy-violet-700);
}

.filter-desc {
    font-size: 11.5px;
    line-height: 1.4;
}

/* Input Search Focus */
.outline-none:focus {
    box-shadow: none !important;
    border-color: var(--hcis-legacy-violet-400);
}

/* Employee Cards Grid */
.emp-select-card {
    background: var(--hcis-white);
    border-color: #eaecf0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.emp-select-card:hover {
    border-color: #b3c2ff;
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.05),
        0 4px 6px -2px rgba(0, 0, 0, 0.025);
    transform: translateY(-3px);
}

/* Card Selected State */
.selected-card {
    /* Background dengan bias ungu/biru sangat tipis */
    background: linear-gradient(to bottom right, #f8f7ff, var(--hcis-white));
    border-color: var(--hcis-legacy-violet-400) !important;
    box-shadow: 0 0 0 1px var(--hcis-legacy-violet-400); /* Efek highlight tebal */
}

/* Custom Check Indicator pada Card Terpilih */
.selected-card .bi-check-circle-fill {
    color: var(--hcis-legacy-violet-700) !important; /* Timpa warna icon centang */
}

.border-dashed {
    border-style: dashed !important;
    border-color: var(--hcis-slate-200) !important;
}

/* Custom Scrollbar minimalis agar tidak merusak UI */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}
/* batas dari card karyawan v2 */
.el-message-box.rounded-4 {
    border-radius: 1rem !important;
    padding-bottom: 20px;
}
.el-message-box__header {
    display: none; /* Sembunyikan header bawaan (termasuk title kosong dan tombol X) */
}
.el-message-box__content {
    padding: 10px 20px !important;
}
.el-message-box__btns {
    padding-top: 10px;
    justify-content: center;
    gap: 10px;
}
.el-message-box__btns .el-button {
    border-radius: 8px;
    margin-left: 0 !important; /* Fix gap bawaan element */
}

.activity-card {
    transition: transform 0.2s ease-in-out;
}
.activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}
.input-group-text {
    font-size: 0.85rem;
}
.avatar-icon {
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
}

.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary);
    font-size: 1.2rem;
}
.search-input {
    padding-left: 45px;
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-200);
    height: 50px;
    font-size: 1rem;
    transition: all 0.3s ease;
}
.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-light);
}

.custom-steps :deep(.el-step__title) {
    font-size: 14px;
    font-weight: 600;
    margin-top: 8px;
}
.custom-steps :deep(.el-step__head.is-success) .step-icon-wrapper,
.custom-steps :deep(.el-step__head.is-process) .step-icon-wrapper {
    background: var(--gradient-primary);
    color: white;
    border: none;
    box-shadow: 0 4px 10px rgba(118, 75, 162, 0.4);
}
.custom-steps :deep(.el-step__title.is-success),
.custom-steps :deep(.el-step__title.is-process) {
    color: var(--hcis-legacy-violet-700);
}
.step-icon-wrapper {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: var(--surface-soft);
    color: var(--secondary);
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.elegant-select :deep(.el-select__wrapper),
.elegant-select :deep(.el-input__wrapper),
.elegant-date :deep(.el-input__wrapper) {
    box-shadow: none !important;
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    min-height: 45px;
    transition: all 0.2s;
}
.elegant-select :deep(.el-select__wrapper:hover),
.elegant-select :deep(.el-select__wrapper.is-hovering),
.elegant-select :deep(.el-input__wrapper:hover),
.elegant-date :deep(.el-input__wrapper:hover),
.elegant-select :deep(.el-select__wrapper.is-focused),
.elegant-select :deep(.el-select__wrapper.is-focus),
.elegant-select :deep(.el-input__wrapper.is-focus) {
    background-color: #fff;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.select-warning :deep(.el-select__wrapper.is-focused),
.select-warning :deep(.el-select__wrapper.is-focus),
.select-warning :deep(.el-input__wrapper.is-focus) {
    border-color: var(--warning);
    box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.2) !important;
}

.summary-box {
    border-color: var(--hcis-slate-200) !important;
}

.activity-timeline {
    position: relative;
}

.custom-scrollbar {
    max-height: 420px;
    overflow-y: auto;
    overflow-x: hidden;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: var(--hcis-slate-100);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}

.activity-item {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}
.activity-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -5px rgba(0, 0, 0, 0.08) !important;
}

.activity-item.is-default {
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
}
.activity-item.is-setup {
    background-color: var(--setup-bg);
    border: 2px solid var(--warning);
}

.hover-elevate {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}
.hover-elevate:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

.icon-box {
    width: 48px;
    height: 48px;
    font-size: 1.15rem;
    letter-spacing: 0.5px;
}
.bg-warning {
    background-color: var(--warning) !important;
}
.bg-primary {
    background-color: var(--primary) !important;
}

.badge-setup {
    background-color: var(--warning);
    font-weight: 600;
}
.badge-default {
    background-color: #e6f7ec;
    font-weight: 600;
}

.btn-hover-scale {
    transition: all 0.2s;
}
.btn-hover-scale:hover {
    background-color: var(--hcis-slate-50) !important;
    transform: scale(1.02);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-slide-enter-from {
    opacity: 0;
    transform: translateX(20px);
}
.fade-slide-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}

.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease-out;
    max-height: 500px;
    overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}
</style>

<style scoped>
/* === ANIMATION & HOVER === */
.hover-elevate {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.hover-elevate:hover {
    transform: translateY(-3px);
    box-shadow:
        0 12px 20px -8px rgba(0, 0, 0, 0.08),
        0 4px 6px -3px rgba(0, 0, 0, 0.04) !important;
    border-color: var(--hcis-slate-300) !important;
}

/* === HEADER ACCORDION === */
.custom-accordion-header {
    border-bottom: 1px solid var(--hcis-slate-100);
}
.header-avatar {
    width: 50px;
    height: 50px;
    /* Gradien ringan yang diminta */
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, var(--hcis-indigo-500) 100%);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    flex-shrink: 0;
}

/* === SECTION & BOXES === */
.clean-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    letter-spacing: 1.2px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    text-transform: uppercase;
}
.clean-box {
    background-color: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    color: var(--hcis-slate-600);
    font-size: 0.95rem;
    line-height: 1.6;
    border: 1px dashed var(--hcis-slate-300);
}

/* === ACTIVITY ROW (MODUL/UJIAN) === */
.clean-activity-row {
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
}
/* AVATAR AKTIVITAS (Dynamic) */
.act-avatar-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-shrink: 0;
    font-weight: 800;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}

/* Soft Color Palettes for Dynamic Icons */
.bg-soft-indigo {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-100);
}
.bg-soft-danger {
    background-color: var(--hcis-red-50);
    color: var(--hcis-red-500);
    border: 1px solid var(--hcis-red-100);
}
.bg-soft-primary {
    background-color: #eff6ff;
    color: var(--hcis-blue-500);
    border: 1px solid #dbeafe;
}
.bg-soft-info {
    background-color: var(--hcis-cyan-50);
    color: var(--hcis-cyan-500);
    border: 1px solid var(--hcis-cyan-100);
}
.bg-soft-success {
    background-color: var(--hcis-green-50);
    color: #22c55e;
    border: 1px solid var(--hcis-green-100);
}
.bg-soft-warning {
    background-color: var(--hcis-amber-50);
    color: var(--hcis-amber-500);
    border: 1px solid var(--hcis-amber-100);
}
.bg-soft-default {
    background-color: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-100);
}

/* === SECRETS & TOKENS === */
.clean-secret-wrapper {
    background-color: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 1rem;
    border: 1px dashed var(--hcis-slate-300);
}
.secret-input {
    display: flex;
    align-items: stretch;
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    height: 40px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.sec-lbl {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0 1rem;
    display: flex;
    align-items: center;
    border-right: 1px solid var(--hcis-slate-200);
}
.sec-val {
    font-family: 'Consolas', 'Fira Code', monospace;
    font-weight: bold;
    letter-spacing: 1.5px;
    color: var(--hcis-slate-900);
    padding: 0 1rem;
    display: flex;
    align-items: center;
    min-width: 100px;
}
.sec-btn {
    background-color: var(--hcis-white);
    color: var(--hcis-indigo-500);
    border: none;
    border-left: 1px solid var(--hcis-slate-200);
    padding: 0 1rem;
    transition: all 0.2s;
}
.sec-btn:hover {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}

/* Styling elegan ala enterprise untuk Floating Button */
.floating-filter-btn {
    position: fixed;
    bottom: 2rem;
    right: 1.5rem;
    width: 60px;
    height: 60px;
    z-index: 1040; /* Berada di atas elemen normal, tapi di bawah modal/offcanvas */
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-700) 100%);
    border: none;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.floating-filter-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(79, 70, 229, 0.4) !important;
}

.floating-filter-btn:active {
    transform: translateY(0);
}

/* === MEDIA QUERIES (Sangat Responsive) === */
@media (max-width: 768px) {
    .mobile-padding-bottom {
        padding-bottom: 90px !important;
    }
    .clean-activity-row {
        padding: 1rem;
    }
    .header-avatar {
        width: 40px;
        height: 40px;
    }
    .header-avatar i {
        font-size: 1.2rem !important;
    }
    .act-avatar-box {
        width: 40px;
        height: 40px;
        font-size: 0.75rem;
    }
    .secret-input {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    .clean-secret-wrapper .d-flex {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }
    .btn-open-link-soft {
        width: 100%;
        text-align: center;
        margin-top: 0.5rem;
    }
    /* 1. Header Aksi (Badge Status & Tombol Edit/Cabut) */
    .responsive-action-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 16px;
    }

    /* 2. Group Tombol (Biar full width ke samping) */
    .responsive-action-buttons {
        flex-direction: column;
        width: 100%;
    }

    .responsive-action-buttons button {
        width: 100%;
        justify-content: center; /* Teks di tengah */
        padding-top: 10px;
        padding-bottom: 10px;
    }

    /* 3. Third Party Section (Token, OTP, Link) biar ga nabrak */
    .responsive-third-party {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .responsive-third-party .third-party-item {
        width: 100% !important;
        margin-bottom: 8px;
    }

    .responsive-third-party .third-party-btn {
        width: 100% !important;
        margin-left: 0 !important; /* Reset auto margin dari desktop */
    }

    /* Memastikan teks rumus panjang tidak merusak grid */
    .font-monospace {
        word-break: break-all;
        white-space: normal;
    }
}
</style>
