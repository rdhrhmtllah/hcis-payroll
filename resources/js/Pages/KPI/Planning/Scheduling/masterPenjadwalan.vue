<template>
    <div class="container-fluid px-0">
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
            <div class="mb-3">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div
                                        class="icon-container d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-calendar-event d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Penjadwalan KPI
                                        </h2>
                                        <p class="subtitle">
                                            Buat jadwal KPI dan pantau tenggat
                                            serta progres evaluasi kinerja
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div
                                    class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                >
                                    <div
                                        class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3"
                                    >
                                        <div class="date-item">
                                            <i
                                                v-if="dayNight == 'Siang'"
                                                class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <i
                                                v-else
                                                class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <span class="fs-md-6 text-header">{{
                                                formatDateString(currentDate)
                                            }}</span>
                                        </div>
                                    </div>
                                    <button
                                        class="btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                        @click="openModal"
                                    >
                                        <i
                                            class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                        ></i>
                                        <span class="text-header"
                                            >Tambah Penjadwalan KPI</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-n4 mb-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="col-12 mb-3">
                            <div class="arsip-tabs" role="tablist">
                                <button
                                    type="button"
                                    role="tab"
                                    class="arsip-tab"
                                    :class="{
                                        'is-selected': activeTab === 'aktif',
                                    }"
                                    :aria-selected="activeTab === 'aktif'"
                                    @click="switchTab('aktif')"
                                >
                                    <i class="bi bi-calendar-event"></i>
                                    <span>Aktif</span>
                                    <span class="arsip-tab-count">{{
                                        tabCounts.aktif
                                    }}</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    class="arsip-tab"
                                    :class="{
                                        'is-selected': activeTab === 'arsip',
                                    }"
                                    :aria-selected="activeTab === 'arsip'"
                                    @click="switchTab('arsip')"
                                >
                                    <i class="bi bi-archive"></i>
                                    <span>Arsip</span>
                                    <span class="arsip-tab-count">{{
                                        tabCounts.arsip
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="activeTab === 'arsip'"
                            class="arsip-note mb-3"
                        >
                            <i class="bi bi-info-circle-fill"></i>
                            <span>
                                Jadwal di arsip hanya bisa dilihat. Datanya tidak
                                dihapus — pulihkan dulu bila ingin mengubah atau
                                mengaktifkannya kembali.
                            </span>
                        </div>

                        <div class="col-12 mb-3">
                            <div class="search-box">
                                <i class="bi bi-search custom-T"></i>
                                <input
                                    type="search"
                                    class="form-control search-input"
                                    placeholder="Cari..."
                                    v-model="searchQuery"
                                />
                            </div>
                        </div>

                        <ListAccordionV1
                            v-if="!loading.listData"
                            accordionId="jadwalAccordion"
                            itemKey="_key"
                            :items="
                                listData.map((jadwal, index) => ({
                                    ...jadwal,
                                    _key: jadwal.Id_Master_Penjadwalan || index,
                                }))
                            "
                            :pagination="pagination"
                            :loading="loading.listData"
                            :emptyText="
                                activeTab === 'arsip'
                                    ? 'Belum ada jadwal yang diarsipkan.'
                                    : 'Data Tidak Ditemukan!'
                            "
                            @change-page="changePage"
                        >
                            <template #header="{ item: jadwal }">
                                <div class="schedule-header">
                                    <div class="schedule-info">
                                        <div
                                            class="schedule-icon"
                                            :class="
                                                getScheduleIcon(jadwal).bgClass
                                            "
                                        >
                                            <i
                                                :class="
                                                    getScheduleIcon(jadwal)
                                                        .iconClass
                                                "
                                                class="mt-customFransIcon"
                                            ></i>
                                        </div>
                                        <div>
                                            <div
                                                class="mt-2 d-flex align-items-center"
                                            >
                                                <span
                                                    v-if="
                                                        jadwal.Flag_Perorangan ===
                                                        'Y'
                                                    "
                                                    class="badge rounded-pill bg-purple-soft text-purple border border-purple-200 px-2 py-1 fw-normal d-flex align-items-center gap-1"
                                                    style="font-size: 0.75rem"
                                                >
                                                    <i
                                                        class="bi bi-person-badge-fill"
                                                    ></i>
                                                    Perorangan
                                                </span>
                                                <span
                                                    v-else
                                                    class="badge rounded-pill bg-blue-soft text-blue border border-blue-200 px-2 py-1 fw-normal d-flex align-items-center gap-1"
                                                    style="font-size: 0.75rem"
                                                >
                                                    <i
                                                        class="bi bi-globe-americas"
                                                    ></i>
                                                    Global
                                                </span>
                                            </div>
                                            <div class="schedule-name">
                                                {{ jadwal.Nama_Kegiatan }}
                                            </div>
                                            <div class="schedule-details">
                                                Tahun:
                                                <strong>{{
                                                    jadwal.Tahun
                                                }}</strong>
                                                • Dibuat:
                                                {{
                                                    formatDateString(
                                                        jadwal.Tanggal,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="schedule-status">
                                        <span
                                            v-if="isArsip(jadwal)"
                                            class="badge-status-arsip"
                                        >
                                            <i class="bi-archive-fill"></i>
                                            Arsip
                                        </span>
                                        <span
                                            v-else
                                            :class="
                                                jadwal.Flag_Aktif === 'Y'
                                                    ? 'badge-status-active'
                                                    : 'badge-status-inactive'
                                            "
                                        >
                                            <i
                                                :class="
                                                    jadwal.Flag_Aktif === 'Y'
                                                        ? 'bi-check-circle-fill'
                                                        : 'bi-x-circle-fill'
                                                "
                                            ></i>
                                            {{
                                                jadwal.Flag_Aktif === "Y"
                                                    ? "Aktif"
                                                    : "Non-Aktif"
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <template #body="{ item: jadwal }">
                                <div class="schedule-body">
                                    <div class="schedule-description">
                                        <h6 class="detail-title">
                                            <i class="bi bi-card-text"></i>
                                            Deskripsi
                                        </h6>
                                        <p>{{ jadwal.Deskripsi }}</p>
                                    </div>

                                    <div
                                        v-if="jadwal.Alasan_Pembukaan"
                                        class="schedule-reason mt-3"
                                    >
                                        <h6 class="detail-title text-danger">
                                            <i
                                                class="bi bi-chat-square-quote-fill"
                                            ></i>
                                            Alasan Pembukaan
                                        </h6>
                                        <div
                                            class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-10"
                                        >
                                            <p
                                                class="mb-0 text-danger small fw-medium fst-italic"
                                            >
                                                "{{ jadwal.Alasan_Pembukaan }}"
                                            </p>
                                        </div>
                                    </div>

                                    <div class="schedule-timing mt-3">
                                        <h6 class="detail-title">
                                            <i class="bi bi-clock-history"></i>
                                            Rentang Waktu
                                        </h6>
                                        <div
                                            class="timing-item"
                                            v-if="jadwal.periode_label"
                                        >
                                            <i
                                                class="bi bi-calendar-week-fill text-primary mt-customIconAccordion"
                                            ></i>
                                            <div>
                                                <strong>Periode:</strong>
                                                <span>{{
                                                    jadwal.periode_label
                                                }}</span>
                                            </div>
                                        </div>
                                        <div class="timing-item">
                                            <i
                                                class="bi bi-calendar-check-fill text-success mt-customIconAccordion"
                                            ></i>
                                            <div>
                                                <strong>Mulai:</strong>
                                                <span>{{
                                                    formatDateTimeString(
                                                        jadwal.Jadwal_Mulai,
                                                    )
                                                }}</span>
                                            </div>
                                        </div>
                                        <div class="timing-item">
                                            <i
                                                class="bi bi-calendar-x-fill text-danger mt-customIconAccordion"
                                            ></i>
                                            <div>
                                                <strong>Akhir:</strong>
                                                <span>{{
                                                    formatDateTimeString(
                                                        jadwal.Jadwal_Akhir,
                                                    )
                                                }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="schedule-flags">
                                        <h6 class="detail-title">
                                            <i class="bi bi-flag-fill"></i>
                                            Jenis Kegiatan Aktif
                                        </h6>
                                        <div class="flags-grid">
                                            <span
                                                class="flag-item"
                                                :class="{
                                                    active:
                                                        jadwal.Flag_Form_KPI ===
                                                        'Y',
                                                }"
                                            >
                                                <i
                                                    class="bi bi-input-cursor-text mt-customFransIco"
                                                ></i>
                                                Form KPI
                                            </span>
                                            <span
                                                class="flag-item"
                                                :class="{
                                                    active:
                                                        jadwal.Flag_Update_KPI ===
                                                        'Y',
                                                }"
                                            >
                                                <i
                                                    class="bi bi-arrow-repeat mt-customFransIco"
                                                ></i>
                                                Update KPI
                                            </span>
                                            <span
                                                class="flag-item"
                                                :class="{
                                                    active:
                                                        jadwal.Flag_Penilaian_KPI ===
                                                        'Y',
                                                }"
                                            >
                                                <i
                                                    class="bi bi-clipboard2-check"
                                                ></i>
                                                Penilaian
                                            </span>
                                            <span
                                                class="flag-item"
                                                :class="{
                                                    active:
                                                        jadwal.Flag_Batas_Verifikasi ===
                                                        'Y',
                                                }"
                                            >
                                                <i
                                                    class="bi bi-shield-check"
                                                ></i>
                                                Verifikasi
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="schedule-participants mt-4 pt-3 border-top"
                                    >
                                        <div
                                            class="d-flex align-items-center justify-content-between mb-3"
                                        >
                                            <h6 class="detail-title mb-0">
                                                <i
                                                    class="bi bi-people-fill"
                                                ></i>
                                                Target Peserta
                                            </h6>

                                            <span
                                                v-if="
                                                    jadwal.Flag_Perorangan ===
                                                    'Y'
                                                "
                                                class="badge bg-purple-soft text-purple border border-purple-200"
                                            >
                                                <i
                                                    class="bi bi-person-badge-fill me-1"
                                                ></i>
                                                Perorangan
                                            </span>
                                            <span
                                                v-else
                                                class="badge bg-blue-soft text-blue border border-blue-200"
                                            >
                                                <i
                                                    class="bi bi-globe-americas me-1"
                                                ></i>
                                                Global / Semua
                                            </span>
                                        </div>

                                        <div
                                            v-if="
                                                jadwal.Flag_Perorangan === 'Y'
                                            "
                                        >
                                            <div
                                                class="participant-scroll-wrapper custom-scrollbar pe-2"
                                            >
                                                <div class="row g-3">
                                                    <div
                                                        v-for="(
                                                            p, index
                                                        ) in jadwal.peserta"
                                                        :key="index"
                                                        class="col-12 col-md-6"
                                                    >
                                                        <div
                                                            class="card-modern h-100 p-3 border rounded bg-white shadow-sm position-relative overflow-hidden"
                                                        >
                                                            <div
                                                                class="d-flex align-items-start position-relative z-1"
                                                                :style="{
                                                                    opacity:
                                                                        p.Flag_Aktif ===
                                                                        'Y'
                                                                            ? '1'
                                                                            : '0.6',
                                                                }"
                                                            >
                                                                <div
                                                                    class="avatar-modern me-3 rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                                                                    :style="{
                                                                        backgroundColor:
                                                                            getAvatarColor(
                                                                                p.Nama_Karyawan,
                                                                            ),
                                                                        width: '45px',
                                                                        height: '45px',
                                                                        fontSize:
                                                                            '1.1rem',
                                                                    }"
                                                                >
                                                                    {{
                                                                        getInitials(
                                                                            p.Nama_Karyawan,
                                                                        )
                                                                    }}
                                                                </div>

                                                                <div
                                                                    class="flex-grow-1 overflow-hidden"
                                                                >
                                                                    <div
                                                                        class="d-flex justify-content-between align-items-start mb-1"
                                                                    >
                                                                        <span
                                                                            class="badge bg-light text-dark border"
                                                                        >
                                                                            {{
                                                                                p.Kode_Karyawan
                                                                            }}
                                                                        </span>

                                                                        <div
                                                                            class="form-check form-switch custom-switch-sm"
                                                                            style="
                                                                                min-height: auto;
                                                                            "
                                                                        >
                                                                            <input
                                                                                class="form-check-input cursor-pointer"
                                                                                type="checkbox"
                                                                                role="switch"
                                                                                :id="`switch-peserta-${p.Id_Perorangan}`"
                                                                                :checked="
                                                                                    p.Flag_Aktif ===
                                                                                    'Y'
                                                                                "
                                                                                @change="
                                                                                    handleTogglePeserta(
                                                                                        p,
                                                                                    )
                                                                                "
                                                                            />
                                                                            <label
                                                                                class="form-check-label ms-1"
                                                                                :for="`switch-peserta-${p.Id_Perorangan}`"
                                                                                style="
                                                                                    font-size: 0.7rem;
                                                                                "
                                                                            >
                                                                                {{
                                                                                    p.Flag_Aktif ===
                                                                                    "Y"
                                                                                        ? "Aktif"
                                                                                        : "Off"
                                                                                }}
                                                                            </label>
                                                                        </div>
                                                                    </div>

                                                                    <div
                                                                        class="fw-bold text-truncate text-dark mb-1"
                                                                        style="
                                                                            font-size: 0.95rem;
                                                                        "
                                                                    >
                                                                        {{
                                                                            p.Nama_Karyawan
                                                                        }}
                                                                    </div>

                                                                    <div
                                                                        class="d-flex flex-wrap gap-1"
                                                                    >
                                                                        <span
                                                                            class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary"
                                                                            style="
                                                                                font-size: 0.7rem;
                                                                            "
                                                                        >
                                                                            <i
                                                                                class="bi bi-layers-fill me-1"
                                                                            ></i>
                                                                            {{
                                                                                p.Jabatan
                                                                            }}
                                                                        </span>
                                                                        <span
                                                                            class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary"
                                                                            style="
                                                                                font-size: 0.7rem;
                                                                            "
                                                                        >
                                                                            <i
                                                                                class="bi bi-building me-1"
                                                                            ></i>
                                                                            {{
                                                                                p.Nama_Sub_Divisi
                                                                            }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    jadwal.peserta &&
                                                    jadwal.peserta.length > 0
                                                "
                                                class="text-end mt-2"
                                            >
                                                <small
                                                    class="text-muted fst-italic"
                                                    style="font-size: 0.75rem"
                                                >
                                                    Total:
                                                    {{ jadwal.peserta.length }}
                                                    Karyawan
                                                </small>
                                            </div>

                                            <div
                                                v-if="
                                                    !jadwal.peserta ||
                                                    jadwal.peserta.length === 0
                                                "
                                                class="col-12 mt-2"
                                            >
                                                <div
                                                    class="alert alert-warning d-flex align-items-center"
                                                    role="alert"
                                                >
                                                    <i
                                                        class="bi bi-exclamation-triangle-fill me-2"
                                                    ></i>
                                                    <div>
                                                        Data peserta tidak
                                                        ditemukan untuk jadwal
                                                        perorangan ini.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-else>
                                            <div
                                                class="p-3 rounded-3 bg-light-primary border border-primary-100 d-flex align-items-start"
                                            >
                                                <div class="me-3 mt-1">
                                                    <div
                                                        class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center shadow-sm"
                                                        style="
                                                            width: 40px;
                                                            height: 40px;
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-building-check fs-5"
                                                        ></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6
                                                        class="fw-bold text-dark mb-1"
                                                    >
                                                        Jadwal Global
                                                    </h6>
                                                    <p
                                                        class="text-secondary small mb-0 lh-sm"
                                                    >
                                                        Jadwal ini berlaku
                                                        secara umum untuk
                                                        seluruh karyawan yang
                                                        memenuhi kriteria
                                                        periode
                                                        <span
                                                            class="fw-bold text-dark"
                                                        >
                                                            {{
                                                                jadwal.Periode
                                                                    ? formatMonthYear(
                                                                          jadwal.Periode,
                                                                      )
                                                                    : "Tahun " +
                                                                      jadwal.Tahun
                                                            }} </span
                                                        >.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="isArsip(jadwal)"
                                        class="schedule-actions-modern mt-4"
                                    >
                                        <div class="action-group">
                                            <span class="action-label text-muted">
                                                <i
                                                    class="bi bi-archive-fill me-2"
                                                ></i>
                                                Jadwal Diarsipkan
                                            </span>
                                        </div>

                                        <button
                                            @click="handlePulihkan(jadwal)"
                                            class="btn btn-primary btn-edit-modern"
                                        >
                                            <i
                                                class="bi bi-arrow-counterclockwise"
                                            ></i>
                                            <span>Pulihkan</span>
                                        </button>
                                    </div>
                                    <div v-else class="schedule-actions-modern mt-4">
                                        <div class="action-group">
                                            <label
                                                class="switch-modern"
                                                :for="`switch-${jadwal.Id_Master_Penjadwalan}`"
                                            >
                                                <input
                                                    type="checkbox"
                                                    role="switch"
                                                    :id="`switch-${jadwal.Id_Master_Penjadwalan}`"
                                                    :checked="
                                                        jadwal.Flag_Aktif ===
                                                        'Y'
                                                    "
                                                    @change.stop="
                                                        handleToggleStatus(
                                                            jadwal,
                                                        )
                                                    "
                                                />
                                                <span class="slider"></span>
                                            </label>
                                            <span
                                                class="action-label"
                                                :class="
                                                    jadwal.Flag_Aktif === 'Y'
                                                        ? 'text-success'
                                                        : 'text-muted'
                                                "
                                            >
                                                {{
                                                    jadwal.Flag_Aktif === "Y"
                                                        ? "Jadwal Aktif"
                                                        : "Jadwal Nonaktif"
                                                }}
                                            </span>
                                        </div>

                                        <div class="schedule-actions-buttons">
                                            <!-- Arsip hanya untuk jadwal yang sudah
                                                 dinonaktifkan; saat masih aktif tombolnya
                                                 tidak dirender agar aturannya jelas. -->
                                            <button
                                                v-if="jadwal.Flag_Aktif === 'T'"
                                                @click="handleArsipkan(jadwal)"
                                                class="btn btn-arsip-modern"
                                            >
                                                <i class="bi bi-archive"></i>
                                                <span>Arsipkan</span>
                                            </button>
                                            <button
                                                @click="editData(jadwal)"
                                                class="btn btn-primary btn-edit-modern"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                                <span>Edit</span>
                                            </button>
                                        </div>
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
        <ModalV1
            size="xl"
            :close-on-overlay="false"
            :show="showModal"
            :title="modalTitle"
            :submitText="modalSubmitText"
            :loading="loading.saveToDatabase || loading.editToDatabase"
            @close="closeModal"
            @submit="submitForm"
        >
            <div>
                <div class="settings-card mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box me-2">
                            <i class="bi bi-sliders text-white"></i>
                        </div>
                        <h6 class="fw-bold m-0 text-dark">
                            Pengaturan Kegiatan
                        </h6>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <div
                                class="form-group-interactive h-100"
                                :class="{ 'is-active': form.is_perorangan }"
                                @click="togglePerorangan"
                            >
                                <i
                                    class="bi icon"
                                    :class="
                                        form.is_perorangan
                                            ? 'bi-person-badge-fill'
                                            : 'bi-people-fill'
                                    "
                                ></i>
                                <div class="description">
                                    <div class="label-title">
                                        {{
                                            form.is_perorangan
                                                ? "Penjadwalan Perorangan"
                                                : "Penjadwalan General"
                                        }}
                                    </div>
                                    <div class="label-info">
                                        {{
                                            form.is_perorangan
                                                ? "Target Individu Tertentu"
                                                : "Seluruh Karyawan"
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="form-check form-switch pointer-events-none"
                                >
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        :checked="form.is_perorangan"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div
                                class="custom-select-wrapper h-100 d-flex flex-column justify-content-center px-2"
                            >
                                <label
                                    class="form-label fw-bold text-muted small mb-2"
                                >
                                    Jenis Kegiatan
                                    <span class="text-danger">*</span>
                                </label>

                                <el-select
                                    v-model="form.jenis_kegiatan_global"
                                    placeholder="-- Pilih Jenis Kegiatan --"
                                    :disabled="isEditing"
                                    size="large"
                                    class="w-100 modern-select"
                                    effect="light"
                                    @change="handleJenisChange"
                                >
                                    <el-option
                                        label="Pengisian KPI"
                                        value="pengisian"
                                    >
                                        <span style="float: left"
                                            >Pengisian KPI</span
                                        >
                                        <span
                                            style="
                                                float: right;
                                                color: var(
                                                    --el-text-color-secondary
                                                );
                                                font-size: 13px;
                                            "
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </span>
                                    </el-option>
                                    <el-option
                                        label="Update Performance (Penilaian)"
                                        value="penilaian"
                                    >
                                        <span style="float: left"
                                            >Update Performance</span
                                        >
                                        <span
                                            style="
                                                float: right;
                                                color: var(
                                                    --el-text-color-secondary
                                                );
                                                font-size: 13px;
                                            "
                                        >
                                            <i class="bi bi-clipboard-data"></i>
                                        </span>
                                    </el-option>
                                    <!--
                                        "Batas Approval (Verifikasi)" DIHILANGKAN.

                                        Flag_Batas_Verifikasi ditulis ke database tapi
                                        tidak dibaca satu baris kode pun — tidak ada
                                        mekanisme batas waktu approval di mana pun.
                                        (KpiEnforcementService menegakkan batas PENGISIAN
                                        penilaian, bukan batas approval.)

                                        Menawarkannya berarti menjanjikan sesuatu yang
                                        tidak ada: admin menyetel tanggal, mengira approval
                                        tertutup pada tanggal itu, dan tidak ada yang
                                        memberi tahu bahwa tidak terjadi apa-apa.

                                        Untuk memulihkan saat fiturnya dibangun: kembalikan
                                        el-option ini dan tambahkan lagi 'verifikasi' pada
                                        aturan in: di PenjadwalanKpiController store()/update().
                                    -->
                                </el-select>
                            </div>
                        </div>

                        <transition name="fade">
                            <div class="col-md-12" v-if="showKeteranganStatus">
                                <div
                                    class="custom-select-wrapper h-100 d-flex flex-column justify-content-center px-2 border-top pt-3 mt-1"
                                >
                                    <label
                                        class="form-label fw-bold text-primary small mb-2"
                                    >
                                        <i
                                            class="bi bi-exclamation-circle-fill me-1"
                                        ></i>
                                        Keterangan Khusus
                                        <span class="text-danger">*</span>
                                    </label>

                                    <el-select
                                        v-model="form.keterangan_status"
                                        placeholder="-- Pilih Alasan Pembukaan --"
                                        size="large"
                                        class="w-100 modern-select"
                                        effect="light"
                                    >
                                        <el-option
                                            label="Terlambat Mengisi"
                                            value="Terlambat Mengisi"
                                        >
                                            <span style="float: left"
                                                >Terlambat Mengisi</span
                                            >
                                            <span
                                                style="
                                                    float: right;
                                                    color: var(
                                                        --el-text-color-secondary
                                                    );
                                                    font-size: 13px;
                                                "
                                            >
                                                <i class="bi bi-alarm"></i>
                                            </span>
                                        </el-option>
                                        <el-option
                                            label="Karyawan Baru (Baru Join)"
                                            value="Karyawan Baru Join"
                                        >
                                            <span style="float: left"
                                                >Karyawan Baru Join</span
                                            >
                                            <span
                                                style="
                                                    float: right;
                                                    color: var(
                                                        --el-text-color-secondary
                                                    );
                                                    font-size: 13px;
                                                "
                                            >
                                                <i
                                                    class="bi bi-person-plus"
                                                ></i>
                                            </span>
                                        </el-option>
                                    </el-select>

                                    <div
                                        v-if="!form.keterangan_status"
                                        class="form-text text-danger fst-italic mt-2"
                                        style="font-size: 0.75rem"
                                    >
                                        <i class="bi bi-arrow-up"></i> Wajib
                                        memilih keterangan alasan.
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <transition name="fade">
                    <div v-if="isFormReady">
                        <div v-if="!isEditing">
                            <div
                                v-for="(formItem, index) in forms"
                                :key="index"
                                class="mb-3 p-3 border rounded position-relative bg-white shadow-sm"
                            >
                                <button
                                    v-if="forms.length > 1"
                                    @click="removeFormRow(index)"
                                    class="btn btn-sm btn-danger position-absolute"
                                    style="top: 10px; right: 10px; z-index: 10"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                                <FormV2
                                    v-model="forms[index]"
                                    :fields="formFields"
                                    @update:modelValue="handleFormChange"
                                />
                            </div>

                            <div class="d-grid mb-3" v-if="!form.is_perorangan">
                                <button
                                    @click="addFormRow"
                                    class="btn btn-outline-primary border-dashed"
                                >
                                    <i class="bi bi-plus-circle-dotted"></i>
                                    Tambah Baris Kegiatan
                                </button>
                            </div>
                        </div>
                        <div v-else>
                            <FormV2
                                v-model="formSingle"
                                :fields="formFields"
                                @update:modelValue="handleFormChange"
                            />
                        </div>

                        <div
                            v-if="form.is_perorangan"
                            class="employee-selector-area border-top pt-4 mt-4"
                        >
                            <div
                                class="d-flex justify-content-between align-items-end mb-4"
                            >
                                <div>
                                    <h6
                                        class="fw-bold mb-1"
                                        style="
                                            color: var(--c-text-main);
                                            font-size: 1.15rem;
                                        "
                                    >
                                        <i
                                            class="bi bi-people-fill me-2 text-primary"
                                        ></i
                                        >Target Peserta
                                    </h6>
                                    <div
                                        style="
                                            color: var(--c-text-sub);
                                            font-size: 0.9rem;
                                        "
                                    >
                                        {{
                                            isSpecialCase
                                                ? "Pilih karyawan dan tentukan periode."
                                                : "Tentukan karyawan wajib untuk periode penilaian ini."
                                        }}
                                    </div>
                                </div>

                                <div
                                    class="d-flex flex-column align-items-end gap-2"
                                >
                                    <div
                                        v-if="
                                            isPeriodSelected && !isSpecialCase
                                        "
                                        class="badge-pill"
                                        style="
                                            background: #eff6ff;
                                            color: #1d4ed8;
                                            border: 1px solid #dbeafe;
                                            font-size: 0.6rem;
                                            padding: 4px 8px;
                                            font-weight: 600;
                                        "
                                    >
                                        <i
                                            class="bi bi-calendar-check-fill me-2"
                                        ></i>
                                        Periode: {{ periodLabel }}
                                    </div>
                                    <div
                                        class="badge-pill kpi"
                                        style="
                                            font-size: 0.9rem;
                                            padding: 8px 16px;
                                        "
                                    >
                                        <i class="bi bi-check2-square me-2"></i>
                                        {{ selectedKaryawanIDs.length }}
                                        Selected
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="loading.karyawan"
                                class="text-center py-5"
                            >
                                <div
                                    class="spinner-border text-primary mb-3"
                                    style="width: 2.5rem; height: 2.5rem"
                                    role="status"
                                ></div>
                                <div class="text-muted fw-bold">
                                    Memuat Direktori Karyawan...
                                </div>
                            </div>

                            <div v-else>
                                <div class="toolbar-modern mb-4">
                                    <div class="search-wrapper">
                                        <i
                                            class="bi bi-search search-icon-float"
                                            style="margin-top: -4px"
                                        ></i>
                                        <input
                                            type="search"
                                            class="search-input-modern"
                                            placeholder="Cari Nama, Kode Karyawan..."
                                            v-model="searchKaryawan"
                                        />
                                    </div>
                                    <label
                                        class="check-all-wrapper user-select-none"
                                        for="checkAll"
                                    >
                                        <input
                                            class="form-check-input me-3"
                                            type="checkbox"
                                            id="checkAll"
                                            v-model="selectAll"
                                            @change="toggleSelectAll"
                                            style="transform: scale(1.2)"
                                        />
                                        <span
                                            class="fw-bold"
                                            style="
                                                color: var(--c-text-main);
                                                font-size: 0.9rem;
                                            "
                                        >
                                            Pilih Semua ({{
                                                filteredKaryawan.filter(
                                                    (k) =>
                                                        !k.is_filled &&
                                                        !k.is_scheduled_admin,
                                                ).length
                                            }})
                                        </span>
                                    </label>
                                </div>

                                <div
                                    class="employee-list-container custom-scrollbar"
                                >
                                    <div
                                        v-if="filteredKaryawan.length === 0"
                                        class="text-center py-5 border rounded-4 d-flex flex-column align-items-center justify-content-center"
                                        style="
                                            background: #f8fafc;
                                            border-style: dashed !important;
                                            min-height: 250px;
                                        "
                                    >
                                        <div
                                            v-if="!form.tahun || !form.periode"
                                            class="animate__animated animate__fadeIn"
                                        >
                                            <div class="mb-3">
                                                <div
                                                    class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 p-4"
                                                >
                                                    <i
                                                        class="bi bi-calendar-x text-warning"
                                                        style="font-size: 2rem"
                                                    ></i>
                                                </div>
                                            </div>
                                            <h6 class="text-dark fw-bold mb-2">
                                                Tahun Atau Periode Belum Dipilih
                                            </h6>
                                            <p
                                                class="text-muted mb-0 px-4"
                                                style="
                                                    font-size: 0.9rem;
                                                    max-width: 300px;
                                                "
                                            >
                                                Mohon pilih
                                                <strong class="text-dark"
                                                    >Tahun</strong
                                                >
                                                Atau
                                                <strong class="text-dark"
                                                    >Periode</strong
                                                >
                                                terlebih dahulu untuk
                                                menampilkan daftar karyawan.
                                            </p>
                                        </div>

                                        <div
                                            v-else
                                            class="animate__animated animate__fadeIn"
                                        >
                                            <div class="mb-3">
                                                <i
                                                    class="bi bi-emoji-expressionless display-4 text-muted opacity-25"
                                                ></i>
                                            </div>
                                            <h6 class="text-dark fw-bold mb-1">
                                                Data Tidak Ditemukan
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                Tidak ada data karyawan
                                                ditemukan untuk periode <br />
                                                <span
                                                    class="badge bg-light text-dark border mt-2"
                                                >
                                                    {{ form.periode }}
                                                    {{ form.tahun }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div
                                            v-for="karyawan in filteredKaryawan"
                                            :key="karyawan.kode_karyawan"
                                            :class="
                                                isSpecialCase
                                                    ? 'col-12'
                                                    : 'col-12 col-md-6'
                                            "
                                        >
                                            <label
                                                class="card-modern h-100"
                                                :class="{
                                                    selected:
                                                        selectedKaryawanIDs.includes(
                                                            karyawan.kode_karyawan,
                                                        ) &&
                                                        !karyawan.is_filled &&
                                                        !karyawan.is_scheduled_admin,
                                                    disabled:
                                                        karyawan.is_filled ||
                                                        karyawan.is_scheduled_admin,
                                                }"
                                            >
                                                <div
                                                    class="d-flex align-items-start"
                                                >
                                                    <div
                                                        class="me-3 pt-1"
                                                        v-if="
                                                            !karyawan.is_filled &&
                                                            !karyawan.is_scheduled_admin
                                                        "
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            class="custom-checkbox-xl"
                                                            :value="
                                                                karyawan.kode_karyawan
                                                            "
                                                            v-model="
                                                                selectedKaryawanIDs
                                                            "
                                                        />
                                                    </div>

                                                    <div
                                                        class="me-3 pt-1 text-secondary"
                                                        v-else-if="
                                                            karyawan.is_scheduled_admin
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-lock-fill"
                                                            style="
                                                                font-size: 1.2rem;
                                                            "
                                                        ></i>
                                                    </div>

                                                    <div
                                                        class="avatar-modern me-3"
                                                    >
                                                        {{
                                                            getInitials(
                                                                karyawan.nama_karyawan,
                                                            )
                                                        }}
                                                    </div>

                                                    <div
                                                        class="flex-grow-1 overflow-hidden"
                                                    >
                                                        <div
                                                            class="meta-row d-flex justify-content-between"
                                                        >
                                                            <span
                                                                class="badge bg-primary"
                                                            >
                                                                {{
                                                                    karyawan.kode_karyawan
                                                                }}
                                                            </span>
                                                            <span
                                                                v-if="
                                                                    karyawan.Tgl_Join &&
                                                                    form.keterangan_status ===
                                                                        'Karyawan Baru Join'
                                                                "
                                                                class="badge bg-info text-dark"
                                                            >
                                                                Join:
                                                                {{
                                                                    formatDateString(
                                                                        karyawan.Tgl_Join,
                                                                    )
                                                                }}
                                                            </span>
                                                        </div>

                                                        <div
                                                            class="fw-bold text-truncate mt-1"
                                                            style="
                                                                color: var(
                                                                    --c-text-main
                                                                );
                                                                font-size: 1rem;
                                                            "
                                                        >
                                                            {{
                                                                karyawan.nama_karyawan
                                                            }}
                                                        </div>

                                                        <!-- Kode karyawan: pembeda satu-satunya ketika
                                                             nama, level, dan divisi kebetulan sama.
                                                             Datanya sudah lama ada di sini — dipakai
                                                             untuk :key dan pembuangan duplikat — hanya
                                                             tak pernah ditampilkan. -->
                                                        <div
                                                            v-if="karyawan.kode_karyawan"
                                                            class="kode-karyawan-pilih"
                                                        >
                                                            {{
                                                                karyawan.kode_karyawan
                                                            }}
                                                        </div>

                                                        <div
                                                            class="d-flex flex-wrap mb-2 mt-1"
                                                            style="
                                                                margin-left: -10px;
                                                            "
                                                        >
                                                            <span
                                                                class="badge-pill level"
                                                            >
                                                                <i
                                                                    class="bi bi-layers-fill"
                                                                ></i>
                                                                {{
                                                                    karyawan.nama_level
                                                                }}
                                                            </span>
                                                            <span
                                                                class="badge-pill level"
                                                            >
                                                                <i
                                                                    class="bi bi-building meta-icon"
                                                                ></i>
                                                                {{
                                                                    karyawan.nama_divisi
                                                                }}
                                                            </span>
                                                        </div>

                                                        <div
                                                            v-if="
                                                                karyawan.is_filled
                                                            "
                                                            class="mt-2 pt-2 border-top"
                                                        >
                                                            <div
                                                                class="d-flex gap-2 w-100 mb-2"
                                                            >
                                                                <span
                                                                    class="badge-pill success flex-fill justify-content-center"
                                                                >
                                                                    <i
                                                                        class="bi bi-check-circle-fill"
                                                                    ></i>
                                                                    Selesai
                                                                    <span
                                                                        v-if="
                                                                            !(
                                                                                form.is_perorangan &&
                                                                                form.jenis_kegiatan_global ===
                                                                                    'pengisian'
                                                                            )
                                                                        "
                                                                    >
                                                                        ({{
                                                                            karyawan.score_achievement
                                                                        }}%)
                                                                    </span>
                                                                </span>
                                                            </div>

                                                            <div
                                                                v-if="
                                                                    (karyawan.performance_keterangan &&
                                                                        karyawan.performance_keterangan !==
                                                                            '-') ||
                                                                    karyawan.has_pica
                                                                "
                                                                class="d-flex flex-wrap gap-2 mt-1"
                                                            >
                                                                <span
                                                                    v-if="
                                                                        karyawan.performance_keterangan &&
                                                                        karyawan.performance_keterangan !==
                                                                            '-'
                                                                    "
                                                                    class="badge d-flex align-items-center"
                                                                    :style="{
                                                                        backgroundColor:
                                                                            'white',
                                                                        border: `1px solid ${karyawan.performance_warna}`,
                                                                        color: karyawan.performance_warna,
                                                                        fontSize:
                                                                            '0.75rem',
                                                                        fontWeight:
                                                                            '600',
                                                                        padding:
                                                                            '5px 10px',
                                                                        borderRadius:
                                                                            '50rem',
                                                                    }"
                                                                >
                                                                    <i
                                                                        class="bi bi-circle-fill me-2"
                                                                        :style="{
                                                                            fontSize:
                                                                                '0.5rem',
                                                                            color: karyawan.performance_warna,
                                                                        }"
                                                                    ></i>
                                                                    {{
                                                                        karyawan.performance_keterangan
                                                                    }}
                                                                </span>

                                                                <span
                                                                    v-if="
                                                                        karyawan.has_pica
                                                                    "
                                                                    class="badge d-flex align-items-center text-danger"
                                                                    style="
                                                                        background-color: #fef2f2;
                                                                        border: 1px
                                                                            solid
                                                                            #fecaca;
                                                                        font-size: 0.75rem;
                                                                        padding: 5px
                                                                            10px;
                                                                        borderradius: 50rem;
                                                                    "
                                                                >
                                                                    <i
                                                                        class="bi bi-exclamation-triangle-fill me-1"
                                                                    ></i>
                                                                    Ada PICA
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div
                                                            v-else-if="
                                                                karyawan.is_scheduled_admin
                                                            "
                                                            class="mt-2 pt-2 border-top"
                                                        >
                                                            <div
                                                                class="p-2 rounded w-100 d-flex flex-column align-items-center justify-content-center text-center"
                                                                style="
                                                                    background-color: #f3f4f6;
                                                                    border: 1px
                                                                        dashed
                                                                        #d1d5db;
                                                                "
                                                            >
                                                                <div
                                                                    class="d-flex align-items-center mb-1"
                                                                >
                                                                    <i
                                                                        class="bi bi-shield-lock-fill text-secondary me-2"
                                                                    ></i>
                                                                    <span
                                                                        style="
                                                                            font-size: 0.7rem;
                                                                            font-weight: bold;
                                                                            color: #4b5563;
                                                                            text-transform: uppercase;
                                                                        "
                                                                    >
                                                                        Sudah
                                                                        Terjadwal
                                                                    </span>
                                                                </div>

                                                                <div
                                                                    v-if="
                                                                        karyawan.jadwal_admin_range
                                                                    "
                                                                    style="
                                                                        font-size: 0.7rem;
                                                                        color: #dc2626;
                                                                    "
                                                                >
                                                                    <i
                                                                        class="bi bi-calendar-range me-1"
                                                                    ></i>
                                                                    <span
                                                                        class="fw-bold"
                                                                        >{{
                                                                            karyawan.jadwal_admin_range
                                                                        }}</span
                                                                    >
                                                                </div>

                                                                <!--
                                                                    Status dipisah per jalur. Tanpa ini,
                                                                    karyawan yang jalur personalnya
                                                                    selesai tapi jalur timnya belum
                                                                    tampak "Selesai" seluruhnya.
                                                                -->
                                                                <div
                                                                    v-if="ringkasanJalur(karyawan)"
                                                                    class="ringkasan-jalur-kpi"
                                                                >
                                                                    {{ ringkasanJalur(karyawan) }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div
                                                            v-if="
                                                                isSpecialCase &&
                                                                selectedKaryawanIDs.includes(
                                                                    karyawan.kode_karyawan,
                                                                )
                                                            "
                                                            class="mt-2 p-3 bg-light-info border rounded-3"
                                                            :class="{
                                                                'bg-light-info':
                                                                    !isKaryawanLocked(
                                                                        karyawan,
                                                                    ),
                                                                'bg-light':
                                                                    isKaryawanLocked(
                                                                        karyawan,
                                                                    ),
                                                                'opacity-50 pointer-events-none':
                                                                    isKaryawanLocked(
                                                                        karyawan,
                                                                    ),
                                                            }"
                                                            style="
                                                                background-color: #f0f9ff;
                                                                border: 1px
                                                                    dashed
                                                                    #bae6fd;
                                                            "
                                                            @click.stop.prevent
                                                        >
                                                            <div class="mb-2">
                                                                <label
                                                                    class="form-label fw-bold text-dark small mb-1"
                                                                >
                                                                    <i
                                                                        class="bi bi-calendar-week me-1"
                                                                    ></i>
                                                                    {{
                                                                        form.keterangan_status ===
                                                                        "Karyawan Baru Join"
                                                                            ? "Start Periode"
                                                                            : "Periode"
                                                                    }}
                                                                    <span
                                                                        class="text-danger"
                                                                        >*</span
                                                                    >
                                                                </label>
                                                                <el-select
                                                                    v-model="
                                                                        karyawan.custom_periode
                                                                    "
                                                                    :placeholder="
                                                                        form.keterangan_status ===
                                                                        'Karyawan Baru Join'
                                                                            ? 'Start Bulan'
                                                                            : 'Pilih Bulan'
                                                                    "
                                                                    size="default"
                                                                    class="w-100"
                                                                    :disabled="
                                                                        isKaryawanLocked(
                                                                            karyawan,
                                                                        )
                                                                    "
                                                                >
                                                                    <el-option
                                                                        v-for="m in monthsList"
                                                                        :key="
                                                                            m.value
                                                                        "
                                                                        :label="
                                                                            m.label
                                                                        "
                                                                        :value="
                                                                            m.value
                                                                        "
                                                                    />
                                                                </el-select>
                                                            </div>
                                                            <div
                                                                v-if="
                                                                    form.keterangan_status ===
                                                                        'Karyawan Baru Join' &&
                                                                    karyawan.Tgl_Join
                                                                "
                                                            >
                                                                <el-alert
                                                                    type="warning"
                                                                    :closable="
                                                                        false
                                                                    "
                                                                    show-icon
                                                                    class="mt-2"
                                                                    style="
                                                                        padding: 8px;
                                                                        border: 1px
                                                                            solid
                                                                            #fcd34d;
                                                                        background-color: #fffbeb;
                                                                    "
                                                                >
                                                                    <template
                                                                        #default
                                                                    >
                                                                        <div
                                                                            class="lh-sm"
                                                                            style="
                                                                                font-size: 0.8rem;
                                                                                color: #92400e;
                                                                            "
                                                                        >
                                                                            <strong
                                                                                >Info:</strong
                                                                            >
                                                                            Tgl
                                                                            Join
                                                                            {{
                                                                                formatDateString(
                                                                                    karyawan.Tgl_Join,
                                                                                )
                                                                            }}.<br />
                                                                            Disarankan:
                                                                            <strong
                                                                                >Periode
                                                                                {{
                                                                                    getRecommendedPeriodLabel(
                                                                                        karyawan.Tgl_Join,
                                                                                    )
                                                                                }}</strong
                                                                            >.
                                                                        </div>
                                                                    </template>
                                                                </el-alert>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-5">
                        <i
                            class="bi bi-arrow-up-circle display-1 text-muted opacity-25 mb-3"
                        ></i>
                        <h5 class="text-muted">
                            {{
                                showKeteranganStatus
                                    ? "Silahkan Pilih Keterangan Khusus Terlebih Dahulu"
                                    : "Silahkan Pilih Jenis Kegiatan Terlebih Dahulu"
                            }}
                        </h5>
                    </div>
                </transition>
            </div>
        </ModalV1>
    </div>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";
import ListAccordionV1 from "@/components/legacy/listAccordionUiV1.vue";
import ModalV1 from "@/components/legacy/modalV1.vue";
import FormV2 from "../../components/form/formV2.vue";
import {
    ElMessage,
    ElMessageBox,
    ElNotification,
    ElSelect,
    ElOption,
    ElAlert,
} from "element-plus";
import { HcisSkeleton } from '@/components/ui';
import { tanggalPanjang, tanggalWaktu } from '@/utils/tanggal';
export default {
    components: {
        ListAccordionV1,
        ModalV1,
        FormV2,
        HcisSkeleton,
        ElSelect,
        ElOption,
        ElAlert,
    },
    data() {
        return {
            participantPeriods: {},
            searchQuery: "",
            // Tab daftar: 'aktif' = jadwal belum diarsipkan, 'arsip' = terarsip.
            activeTab: "aktif",
            tabCounts: { aktif: 0, arsip: 0 },
            loadingArsip: false,
            showModal: false,
            isEditing: false,
            currentDate: new Date(),
            listData: [],
            form: {
                is_perorangan: false,
                jenis_kegiatan_global: "",
                keterangan_status: "",
            },
            selectAll: false,
            karyawanList: [],
            searchKaryawan: "",
            selectedKaryawanIDs: [],
            formSingle: {
                Id_Master_Penjadwalan: null,
                Nama_Kegiatan: "",
                Tahun: new Date().getFullYear().toString(),
                Deskripsi: "",
                Jadwal_Mulai: "",
                Jadwal_Akhir: "",
                Jenis_Kegiatan: "",
                Alasan_Pembykaan: "",
                Periode: "",
                Flag_Perorangan: "T",
            },
            forms: [],
            loading: {
                listData: true,
                saveToDatabase: false,
                editToDatabase: false,
                karyawan: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            monthsList: [
                { value: "1", label: "Januari" },
                { value: "2", label: "Februari" },
                { value: "3", label: "Maret" },
                { value: "4", label: "April" },
                { value: "5", label: "Mei" },
                { value: "6", label: "Juni" },
                { value: "7", label: "Juli" },
                { value: "8", label: "Agustus" },
                { value: "9", label: "September" },
                { value: "10", label: "Oktober" },
                { value: "11", label: "November" },
                { value: "12", label: "Desember" },
            ],
            avatarColors: {},
        };
    },
    computed: {
        modalTitle() {
            return this.isEditing
                ? "Edit Penjadwalan KPI"
                : "Tambah Penjadwalan KPI";
        },
        modalSubmitText() {
            return this.isEditing ? "Simpan Perubahan" : "Simpan";
        },
        isSpecialCase() {
            return (
                this.form.is_perorangan &&
                this.form.jenis_kegiatan_global === "pengisian" &&
                ["Terlambat Mengisi", "Karyawan Baru Join"].includes(
                    this.form.keterangan_status,
                )
            );
        },
        isPeriodSelected() {
            const currentForm = this.isEditing
                ? this.formSingle
                : this.forms[0];
            return currentForm && currentForm.Periode && currentForm.Tahun;
        },
        filteredKaryawan() {
            const lowerSearch = this.searchKaryawan.trim().toLowerCase();
            if (!lowerSearch) return this.karyawanList;
            return this.karyawanList.filter((k) => {
                // Null-safe: satu baris dengan nama/kode null jangan sampai
                // membuat seluruh filter throw dan daftar jadi "nyangkut".
                const nama = (k.nama_karyawan || "").toLowerCase();
                const kode = (k.kode_karyawan || "").toLowerCase();
                return (
                    nama.includes(lowerSearch) || kode.includes(lowerSearch)
                );
            });
        },
        formFields() {
            const dynamicCol = "col-6";

            let fields = [
                {
                    name: "Periode",
                    label: "Periode Bulan",
                    type: "select",
                    placeholder: "Pilih Bulan",
                    col: "col-12",
                    options: this.monthsList,
                },
                {
                    name: "Tahun",
                    label: "Tahun",
                    type: "text",
                    placeholder: "2026",
                    icon: "bi bi-arrow-down-left-square",
                    col: dynamicCol,
                    filter: (val) => val.replace(/[^0-9]/g, ""),
                },
                {
                    name: "Nama_Kegiatan",
                    label: "Nama Kegiatan",
                    type: "text",
                    placeholder: "Nama Kegiatan....",
                    icon: "bi bi-arrow-down-left-square",
                    col: dynamicCol,
                },
                {
                    name: "Deskripsi",
                    label: "Deskripsi Kegiatan",
                    type: "textarea",
                    placeholder: "Tuliskan deskripsi kegiatan...",
                    icon: "bi bi-textarea-t",
                    col: "col-12",
                    rows: 3,
                },
                {
                    name: "Jadwal_Mulai",
                    label: "Jadwal Mulai",
                    type: "datetime-local",
                    icon: "bi bi-calendar-event",
                    col: dynamicCol,
                },
                {
                    name: "Jadwal_Akhir",
                    label: "Jadwal Akhir",
                    type: "datetime-local",
                    icon: "bi bi-calendar2-week",
                    col: dynamicCol,
                },
            ];

            if (this.isSpecialCase) {
                fields = fields.filter((f) => f.name !== "Periode");
            }

            if (
                this.form.is_perorangan &&
                this.form.jenis_kegiatan_global !== "pengisian"
            ) {
                fields.push({
                    name: "Alasan_Pembukaan",
                    label: "Alasan Pembukaan",
                    type: "textarea",
                    placeholder:
                        "Jelaskan alasan pembukaan jadwal khusus ini...",
                    icon: "bi bi-chat-left-quote",
                    col: "col-12",
                    rows: 2,
                    required: true,
                });
            }

            return fields;
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        periodLabel() {
            const currentForm = this.isEditing
                ? this.formSingle
                : this.forms[0];

            if (!currentForm || !currentForm.Periode || !currentForm.Tahun)
                return "-";

            const monthNames = [
                "",
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ];
            const monthIndex = parseInt(currentForm.Periode);
            return `${monthNames[monthIndex] || ""} ${currentForm.Tahun}`;
        },
        showKeteranganStatus() {
            return (
                this.form.jenis_kegiatan_global === "pengisian" &&
                this.form.is_perorangan
            );
        },
        isFormReady() {
            if (!this.form.jenis_kegiatan_global) return false;
            if (this.showKeteranganStatus) {
                return !!this.form.keterangan_status;
            }
            return true;
        },
    },
    mounted() {
        this.fetchListData();
        this.resetForm();
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        searchKaryawan() {
            this.selectAll = false;
        },
        "form.keterangan_status"(newVal) {
            if (this.isSpecialCase) {
                if (this.forms[0].Tahun.length === 4) {
                    this.fetchKaryawanData();
                }
            }
        },
        "forms.0.Tahun"(newVal) {
            if (this.isSpecialCase && newVal.length === 4) {
                this.debouncedFetchKaryawan();
            }
        },
        "formSingle.Tahun"(newVal) {
            if (this.isEditing && this.isSpecialCase && newVal.length === 4) {
                this.debouncedFetchKaryawan();
            }
        },
    },
    methods: {
        isKaryawanLocked(karyawan) {
            // Aturan kunci hidup di backend (is_locked): terkunci hanya bila tidak
            // ada jalur (personal/tim) yang masih menyisakan pekerjaan. Pengajuan
            // yang DITOLAK tidak mengunci, karena justru butuh jendela dibuka lagi.
            if (typeof karyawan.is_locked === "boolean") {
                return karyawan.is_locked;
            }

            return karyawan.is_filled || karyawan.is_scheduled_admin;
        },
        ringkasanJalur(karyawan) {
            if (!Array.isArray(karyawan.jalur) || karyawan.jalur.length === 0) {
                return "";
            }

            return karyawan.jalur
                .map((j) => `${j.jalur === "tim" ? "Tim" : "Personal"}: ${j.status_kpi}`)
                .join(" · ");
        },
        async handleTogglePeserta(peserta) {
            const oldStatus = peserta.Flag_Aktif;
            peserta.Flag_Aktif = oldStatus === "Y" ? "T" : "Y";

            try {
                const response = await axios.post(
                    "/api/v1/penjadwalan-kpi/toggle-switch-peserta",
                    {
                        id: peserta.Id_Perorangan,
                    },
                );

                if (response.data.success) {
                    peserta.Flag_Aktif = response.data.result.new_status;
                    ElMessage.success("Status peserta berhasil diperbarui");
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                peserta.Flag_Aktif = oldStatus;
                ElMessage.error("Gagal mengubah status peserta");
            }
        },
        formatMonthYear(periodeString) {
            if (!periodeString) return "-";
            try {
                const [year, month] = periodeString.split("-");
                const date = new Date(year, month - 1);
                return date.toLocaleDateString("id-ID", {
                    month: "long",
                    year: "numeric",
                });
            } catch (e) {
                return periodeString;
            }
        },
        getRecommendedPeriod(dateString) {
            if (!dateString) return null;
            try {
                const joinDate = new Date(dateString);
                const nextMonthDate = new Date(
                    joinDate.getFullYear(),
                    joinDate.getMonth() + 1,
                    1,
                );
                return (nextMonthDate.getMonth() + 1).toString();
            } catch (e) {
                return null;
            }
        },
        getRecommendedPeriodLabel(dateString) {
            if (!dateString) return "-";
            try {
                const joinDate = new Date(dateString);
                const nextMonthDate = new Date(
                    joinDate.getFullYear(),
                    joinDate.getMonth() + 1,
                    1,
                );
                const monthNames = [
                    "Januari",
                    "Februari",
                    "Maret",
                    "April",
                    "Mei",
                    "Juni",
                    "Juli",
                    "Agustus",
                    "September",
                    "Oktober",
                    "November",
                    "Desember",
                ];
                return `${monthNames[nextMonthDate.getMonth()]}`;
            } catch (e) {
                return "-";
            }
        },
        async fetchKaryawanData() {
            const dataSumber = this.isEditing ? this.formSingle : this.forms[0];
            const tahun = dataSumber.Tahun;

            if (!tahun || tahun.length !== 4) return;
            if (!this.isSpecialCase && !dataSumber.Periode && !this.isEditing)
                return;

            this.loading.karyawan = true;
            this.karyawanList = [];

            try {
                let url = "/api/v1/penjadwalan-kpi/current/karyawan";
                let params = { periode: dataSumber.Periode, tahun: tahun };

                if (this.isSpecialCase) {
                    url = "/api/v1/penjadwalan-kpi/current/karyawan-not-kpi";
                    params = { tahun: tahun };
                }

                const response = await axios.get(url, { params });

                if (response.data.success) {
                    // Safety net: buang duplikat kode_karyawan agar :key unik
                    // (hindari card ganda & filter pencarian yang "nyangkut").
                    const seenKode = new Set();
                    const uniqueResult = response.data.result.filter((k) => {
                        if (seenKode.has(k.kode_karyawan)) return false;
                        seenKode.add(k.kode_karyawan);
                        return true;
                    });

                    this.karyawanList = uniqueResult.map((k) => {
                        let finalPeriode = ""; // Variabel untuk periode final
                        const joinDate = k.Tgl_Join || k.tgl_masuk;

                        // LOGIKA 1: Cek apakah sedang Edit dan data tersimpan ada di participantPeriods?
                        if (
                            this.isEditing &&
                            this.participantPeriods[k.kode_karyawan]
                        ) {
                            finalPeriode =
                                this.participantPeriods[k.kode_karyawan];
                        }
                        // LOGIKA 2: Jika bukan edit/tidak ada, cek apakah status "Karyawan Baru" (Auto Suggest)
                        else if (
                            this.form.keterangan_status === "Karyawan Baru Join"
                        ) {
                            finalPeriode = this.getRecommendedPeriod(joinDate);
                        }

                        return {
                            ...k,
                            Tgl_Join: joinDate,
                            // Pasang periode yang sudah ditemukan tadi (Entah dari DB atau Auto Suggest)
                            custom_periode: finalPeriode || "",
                            is_checked: this.selectedKaryawanIDs.includes(
                                k.kode_karyawan,
                            ),
                        };
                    });
                }
            } catch (error) {
                // ... error handling (tetap sama) ...
                console.error(error);
            } finally {
                this.loading.karyawan = false;
            }
        },
        toggleSelectAll() {
            if (this.selectAll) {
                const eligible = this.filteredKaryawan.filter(
                    (k) => !k.is_filled,
                );
                const ids = eligible.map((k) => k.kode_karyawan);
                this.selectedKaryawanIDs = [
                    ...new Set([...this.selectedKaryawanIDs, ...ids]),
                ];
            } else {
                const visibleIDs = this.filteredKaryawan.map(
                    (k) => k.kode_karyawan,
                );
                this.selectedKaryawanIDs = this.selectedKaryawanIDs.filter(
                    (id) => !visibleIDs.includes(id),
                );
            }
        },
        togglePerorangan() {
            this.form.is_perorangan = !this.form.is_perorangan;
            this.form.keterangan_status = "";
            if (this.form.is_perorangan && !this.isEditing) {
                const firstItem = this.forms[0];
                this.forms = [firstItem];
                this.selectedKaryawanIDs = [];
            }
        },
        handleJenisChange() {
            this.form.keterangan_status = "";
        },
        handleFormChange(newVal) {
            if (this.form.is_perorangan) {
                const currentData = this.isEditing
                    ? this.formSingle
                    : this.forms[0];
                const isTahunValid =
                    currentData.Tahun && currentData.Tahun.length === 4;

                if (this.isSpecialCase) {
                    if (isTahunValid) {
                        this.debouncedFetchKaryawan();
                    }
                } else {
                    if (isTahunValid && currentData.Periode) {
                        this.debouncedFetchKaryawan();
                    }
                }
            }
        },
        debouncedFetchKaryawan: debounce(function () {
            this.fetchKaryawanData();
        }, 800),
        getInitials(name) {
            if (!name) return "?";
            const parts = name.split(" ");
            if (parts.length === 1)
                return parts[0].substring(0, 2).toUpperCase();
            return (parts[0][0] + parts[1][0]).toUpperCase();
        },
        getAvatarColor(name) {
            if (this.avatarColors[name]) return this.avatarColors[name];
            const colors = [
                "#3498db",
                "#e74c3c",
                "#9b59b6",
                "#f1c40f",
                "#1abc9c",
                "#e67e22",
                "#34495e",
            ];
            const color = colors[Math.floor(Math.random() * colors.length)];
            this.avatarColors[name] = color;
            return color;
        },
        async handleToggleStatus(jadwal) {
            const originalStatus = jadwal.Flag_Aktif;
            jadwal.Flag_Aktif = originalStatus === "Y" ? "T" : "Y";

            try {
                const response = await axios.post(
                    "/api/v1/penjadwalan-kpi/toggle-switch",
                    {
                        id: jadwal.Id_Master_Penjadwalan,
                    },
                );

                if (response.data.success) {
                    jadwal.Flag_Aktif = response.data.result.new_status;
                    this.fetchListData(1);
                } else {
                    jadwal.Flag_Aktif = originalStatus;
                    throw new Error(response.data.message);
                }
            } catch (error) {
                jadwal.Flag_Aktif = originalStatus;
                ElMessage.error(
                    error?.response?.data?.message || "Gagal mengubah status",
                );
            }
        },
        isArsip(jadwal) {
            return jadwal?.Flag_Arsip === "Y";
        },
        switchTab(tab) {
            if (this.activeTab === tab) return;
            this.activeTab = tab;
            this.listData = [];
            this.pagination.page = 1;
            this.fetchListData(1);
        },
        async handleArsipkan(jadwal) {
            try {
                await ElMessageBox.confirm(
                    `Jadwal "${jadwal.Nama_Kegiatan}" akan dipindahkan ke tab Arsip. Datanya tidak dihapus dan tetap bisa dilihat serta dipulihkan kapan saja.`,
                    "Arsipkan Jadwal?",
                    {
                        confirmButtonText: "Ya, Arsipkan",
                        cancelButtonText: "Batal",
                        type: "warning",
                    },
                );
            } catch (e) {
                return; // dibatalkan pengguna
            }

            if (this.loadingArsip) return;
            this.loadingArsip = true;

            try {
                const response = await axios.post(
                    "/api/v1/penjadwalan-kpi/arsipkan",
                    { id: jadwal.Id_Master_Penjadwalan },
                );

                if (!response.data.success) {
                    throw new Error(response.data.message);
                }

                ElMessage.success(response.data.message);
                this.fetchListData(1);
            } catch (error) {
                ElMessage.error(
                    error?.response?.data?.message ||
                        error?.message ||
                        "Gagal mengarsipkan jadwal",
                );
            } finally {
                this.loadingArsip = false;
            }
        },
        async handlePulihkan(jadwal) {
            try {
                await ElMessageBox.confirm(
                    `Jadwal "${jadwal.Nama_Kegiatan}" akan dikembalikan ke daftar utama dengan status Non-Aktif.`,
                    "Pulihkan Jadwal?",
                    {
                        confirmButtonText: "Ya, Pulihkan",
                        cancelButtonText: "Batal",
                        type: "info",
                    },
                );
            } catch (e) {
                return; // dibatalkan pengguna
            }

            if (this.loadingArsip) return;
            this.loadingArsip = true;

            try {
                const response = await axios.post(
                    "/api/v1/penjadwalan-kpi/pulihkan",
                    { id: jadwal.Id_Master_Penjadwalan },
                );

                if (!response.data.success) {
                    throw new Error(response.data.message);
                }

                ElMessage.success(response.data.message);
                this.fetchListData(1);
            } catch (error) {
                ElMessage.error(
                    error?.response?.data?.message ||
                        error?.message ||
                        "Gagal memulihkan jadwal",
                );
            } finally {
                this.loadingArsip = false;
            }
        },
        async fetchListData(page = 1) {
            this.loading.listData = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    q: this.searchQuery,
                    status: this.activeTab,
                };
                const response = await axios.get(
                    "/api/v1/penjadwalan-kpi/current",
                    { params },
                );

                if (response.data?.tab_counts) {
                    this.tabCounts = response.data.tab_counts;
                }

                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                    this.pagination.page = page;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                } else {
                    this.listData = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                this.listData = [];
                console.error("Failed to fetch list data:", error);
            } finally {
                this.loading.listData = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchListData(1);
        }, 500),

        async submitForm() {
            if (!this.isFormReady) return;

            const flagPeroranganValue = this.form.is_perorangan ? "Y" : "T";

            const validateItem = (item) => {
                if (
                    !item.Nama_Kegiatan ||
                    !item.Tahun ||
                    !item.Deskripsi ||
                    !item.Jadwal_Mulai ||
                    !item.Jadwal_Akhir
                )
                    return false;

                if (!this.isSpecialCase && !item.Periode) return false;

                if (
                    this.form.is_perorangan &&
                    this.form.jenis_kegiatan_global !== "pengisian"
                ) {
                    if (!item.Alasan_Pembukaan) {
                        ElNotification({
                            title: "Validasi Gagal",
                            message: "Alasan Pembukaan wajib diisi.",
                            type: "error",
                        });
                        return false;
                    }
                    const alasanBersih = item.Alasan_Pembukaan.replace(
                        /\s/g,
                        "",
                    );
                    if (alasanBersih.length < 10) {
                        ElNotification({
                            title: "Validasi Gagal",
                            message: "Alasan Pembukaan min. 10 karakter.",
                            type: "error",
                        });
                        return false;
                    }
                }
                return true;
            };

            const buildPayloads = (baseItem) => {
                let payloads = [];

                if (this.isSpecialCase) {
                    if (this.selectedKaryawanIDs.length === 0) {
                        ElNotification({
                            title: "Error",
                            message: "Pilih minimal satu karyawan.",
                            type: "error",
                        });
                        return null;
                    }

                    let pesertaDetail = [];
                    for (const kodeKaryawan of this.selectedKaryawanIDs) {
                        const employee = this.karyawanList.find(
                            (k) => k.kode_karyawan === kodeKaryawan,
                        );

                        if (!employee || !employee.custom_periode) {
                            ElNotification({
                                title: "Validasi Gagal",
                                message: `Periode belum dipilih untuk karyawan ${kodeKaryawan}`,
                                type: "error",
                            });
                            return null;
                        }

                        pesertaDetail.push({
                            Kode_Karyawan: kodeKaryawan,
                            Custom_Periode: employee.custom_periode,
                        });
                    }

                    let singlePayload = {
                        ...baseItem,
                        Flag_Perorangan: flagPeroranganValue,
                        Jenis_Kegiatan: this.form.jenis_kegiatan_global,
                        Alasan_Pembukaan: this.form.keterangan_status,
                        Peserta: pesertaDetail,
                        Periode: null,
                    };
                    payloads.push(singlePayload);
                } else {
                    let standardPayload = {
                        ...baseItem,
                        Flag_Perorangan: flagPeroranganValue,
                        Jenis_Kegiatan: this.form.jenis_kegiatan_global,
                    };

                    if (this.form.is_perorangan) {
                        if (this.showKeteranganStatus) {
                            standardPayload.Alasan_Pembukaan =
                                this.form.keterangan_status;
                        }

                        standardPayload.Peserta = this.selectedKaryawanIDs.map(
                            (id) => ({
                                Kode_Karyawan: id,
                                Custom_Periode: baseItem.Periode,
                            }),
                        );

                        if (standardPayload.Peserta.length === 0) {
                            ElNotification({
                                title: "Error",
                                message: "Pilih minimal satu karyawan.",
                                type: "error",
                            });
                            return null;
                        }
                    } else {
                        standardPayload.Peserta = [];
                        standardPayload.Alasan_Pembukaan = null;
                    }
                    payloads.push(standardPayload);
                }

                return payloads;
            };

            let finalPayloads = [];

            if (this.isEditing) {
                const payloads = buildPayloads(this.formSingle);
                if (!payloads) return;

                if (payloads.length > 1) {
                    ElNotification({
                        title: "Error",
                        message:
                            "Edit mode tidak support multi-periode saat ini.",
                        type: "error",
                    });
                    return;
                }

                if (!validateItem(payloads[0])) return;

                this.loading.editToDatabase = true;
                try {
                    const url = `/api/v1/penjadwalan-kpi/update/${this.formSingle.Id_Master_Penjadwalan}`;
                    const response = await axios.put(url, payloads[0]);
                    this.handleSuccess(response);
                } catch (error) {
                    this.handleError(error);
                } finally {
                    this.loading.editToDatabase = false;
                }
            } else {
                for (let item of this.forms) {
                    if (!validateItem(item)) return;
                    const payloads = buildPayloads(item);
                    if (!payloads) return;
                    finalPayloads = [...finalPayloads, ...payloads];
                }

                this.loading.saveToDatabase = true;
                try {
                    const url = "/api/v1/penjadwalan-kpi/store";
                    const response = await axios.post(url, finalPayloads);
                    this.handleSuccess(response);
                } catch (error) {
                    this.handleError(error);
                } finally {
                    this.loading.saveToDatabase = false;
                }
            }
        },
        handleSuccess(response) {
            if (response.data.success) {
                ElNotification({
                    title: "Sukses",
                    message: response.data.message || "Data berhasil disimpan!",
                    type: "success",
                });
                this.closeModal();
                this.fetchListData(this.pagination.page);
            } else {
                throw new Error(
                    response.data.message || "Gagal menyimpan data",
                );
            }
        },
        handleError(error) {
            ElNotification({
                title: "Error",
                message:
                    error.response?.data?.message ||
                    "Terjadi kesalahan saat menyimpan.",
                type: "error",
            });
        },
        addFormRow() {
            this.forms.push({
                Nama_Kegiatan: "",
                Tahun: new Date().getFullYear().toString(),
                Deskripsi: "",
                Jadwal_Mulai: "",
                Jadwal_Akhir: "",
                Periode: "",
                Flag_Perorangan: "T",
            });
        },
        removeFormRow(index) {
            if (this.forms.length > 1) this.forms.splice(index, 1);
        },
        getJenisKegiatanFromFlags(item) {
            if (item.Flag_Form_KPI === "Y") return "pengisian";
            if (item.Flag_Update_KPI === "Y") return "update";
            if (item.Flag_Penilaian_KPI === "Y") return "penilaian";
            if (item.Flag_Batas_Verifikasi === "Y") return "verifikasi";
            return null;
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchListData(page);
            }
        },
        editData(item) {
            this.isEditing = true;
            this.form.is_perorangan = item.Flag_Perorangan === "Y";
            this.form.jenis_kegiatan_global =
                this.getJenisKegiatanFromFlags(item);

            let alasan = item.Alasan_Pembukaan || "";
            if (!alasan && item.peserta && item.peserta.length > 0) {
                alasan = item.peserta[0].Keterangan || "";
            }
            this.form.keterangan_status = alasan;

            this.formSingle = {
                Id_Master_Penjadwalan: item.Id_Master_Penjadwalan,
                Nama_Kegiatan: item.Nama_Kegiatan,
                Tahun: item.Tahun,
                Deskripsi: item.Deskripsi,
                Jadwal_Mulai: item.Jadwal_Mulai,
                Jadwal_Akhir: item.Jadwal_Akhir,
                Alasan_Pembukaan: alasan,
                // PERBAIKAN DI SINI:
                // Menggunakan parseInt untuk mengubah "01" menjadi 1, lalu toString menjadi "1"
                Periode: item.Periode
                    ? parseInt(item.Periode.split("-")[1]).toString()
                    : "",
                Flag_Perorangan: item.Flag_Perorangan,
            };

            if (this.form.is_perorangan) {
                this.selectedKaryawanIDs = [];
                this.participantPeriods = {};

                if (item.peserta && Array.isArray(item.peserta)) {
                    this.selectedKaryawanIDs = [];
                    this.participantPeriods = {}; // Reset

                    item.peserta.forEach((p) => {
                        this.selectedKaryawanIDs.push(p.Kode_Karyawan);

                        if (p.Periode) {
                            const parts = p.Periode.split("-"); // ["2025", "01"]
                            if (parts.length === 2) {
                                const cleanMonth = parseInt(
                                    parts[1],
                                ).toString();

                                this.participantPeriods[p.Kode_Karyawan] =
                                    cleanMonth;
                            }
                        }
                    });
                }
                this.$nextTick(() => {
                    this.fetchKaryawanData();
                });
            }

            this.showModal = true;
        },
        openModal() {
            this.isEditing = false;
            this.resetForm();
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            const baseForm = {
                Id_Master_Penjadwalan: null,
                Nama_Kegiatan: "",
                Tahun: new Date().getFullYear().toString(),
                Deskripsi: "",
                Jadwal_Mulai: "",
                Jadwal_Akhir: "",
                Alasan_Pembukaan: "",
                Periode: "",
                Flag_Perorangan: "T",
            };

            this.formSingle = { ...baseForm };
            this.forms = [{ ...baseForm }];

            this.form.is_perorangan = false;
            this.form.jenis_kegiatan_global = "";
            this.form.keterangan_status = "";
            this.selectedKaryawanIDs = [];
            this.karyawanList = [];
            this.selectAll = false;
            this.isEditing = false;
        },
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        formatDateTimeString(dateString) {
            return tanggalWaktu(dateString);
        },
        getScheduleIcon(jadwal) {
            if (jadwal.Flag_Form_KPI === "Y")
                return {
                    iconClass: "bi bi-input-cursor-text",
                    bgClass: "bg-primary-soft",
                };
            if (jadwal.Flag_Update_KPI === "Y")
                return {
                    iconClass: "bi bi-arrow-repeat",
                    bgClass: "bg-warning-soft",
                };
            if (jadwal.Flag_Penilaian_KPI === "Y")
                return {
                    iconClass: "bi bi-clipboard2-check",
                    bgClass: "bg-success-soft",
                };
            if (jadwal.Flag_Batas_Verifikasi === "Y")
                return {
                    iconClass: "bi bi-shield-check",
                    bgClass: "bg-info-soft",
                };
            return {
                iconClass: "bi bi-calendar-event",
                bgClass: "bg-secondary-soft",
            };
        },
    },
};
</script>

<style scoped>
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
}
/* Container Scroll Wrapper */
.participant-scroll-wrapper {
    max-height: 400px; /* Batas tinggi, setelah ini akan scroll */
    overflow-y: auto; /* Scroll vertikal aktif */
    overflow-x: hidden; /* Hilangkan scroll horizontal */
    padding-bottom: 5px; /* Sedikit jarak di bawah */
}

/* Badge Soft Colors */
.bg-purple-soft {
    background-color: var(--hcis-purple-100);
}
.text-purple {
    color: var(--hcis-purple-600);
}
.border-purple-200 {
    border-color: #e9d5ff !important;
}

.bg-blue-soft {
    background-color: #eff6ff;
}
.text-blue {
    color: var(--hcis-blue-600);
}
.border-blue-200 {
    border-color: #bfdbfe !important;
}

.bg-warning-soft {
    background-color: var(--hcis-yellow-50);
}
.text-warning {
    color: #ca8a04;
}
.border-warning-200 {
    border-color: var(--hcis-yellow-200) !important;
}

/* Opacity Helper */
.opacity-10 {
    opacity: 0.1;
}
/* Card Container yang lebih modern & soft */
.settings-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-100); /* Border sangat tipis */
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.03),
        0 4px 6px -2px rgba(0, 0, 0, 0.01); /* Shadow lembut */
    position: relative;
    overflow: hidden;
}

/* Dekorasi gradasi halus di latar belakang */
.settings-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--hcis-indigo-300) 100%);
}

/* Icon Box Header */
.icon-box {
    width: 32px;
    height: 32px;
    background: var(--primary); /* Atau warna primary kamu */
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
}

/* Interaktif Card (Kiri) - Mempercantik yang sudah ada */
.form-group-interactive {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    transition: all 0.3s ease;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
}

.form-group-interactive:hover {
    border-color: var(--primary-light, var(--hcis-indigo-300));
    background: var(--hcis-white);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.form-group-interactive.is-active {
    background: #eff6ff; /* Biru sangat muda */
    border-color: var(--primary);
}

.form-group-interactive .icon {
    font-size: 1.5rem;
    color: var(--hcis-slate-500);
    transition: color 0.3s;
}

.form-group-interactive.is-active .icon {
    color: var(--primary);
}

/* Overwrite Element Plus Select agar lebih clean */
:deep(.el-select .el-input__wrapper) {
    border-radius: 12px;
    padding: 4px 12px;
    box-shadow: 0 0 0 1px var(--hcis-slate-200) inset !important;
}

:deep(.el-select .el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px var(--primary-light, var(--hcis-indigo-300)) inset !important;
}
.custom-scrollbar {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 5px;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #aaa;
}

/* Employee Card Styles */
.employee-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}
.employee-card:hover {
    border-color: #3498db;
    background-color: #f8fcfd;
}

/* Disabled Card (Sudah Mengisi) */
.employee-card.disabled-card {
    background-color: #f8f9fa; /* Abu-abu muda */
    border-color: #eee;
    opacity: 0.9; /* Sedikit transparan tapi tetap jelas */
}
.employee-card.disabled-card .text-dark {
    color: #495057 !important; /* Tetap gelap agar terbaca */
}

/* Avatar Styles */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    flex-shrink: 0;
}

/* Checkbox Size */
.custom-checkbox {
    width: 1.2em;
    height: 1.2em;
    cursor: pointer;
}

/* Badge Soft */
.bg-danger-soft {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}
.bg-secondary-soft {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

/* Performance Dot */
.performance-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

/* -- LAYOUT AKSI MODERN -- */
.schedule-actions-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--hcis-legacy-bootstrap-gray);
}

.action-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.action-label {
    font-weight: 600;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

/* -- SWITCH TOGGLE MODERN -- */
.switch-modern {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
    cursor: pointer;
}

.switch-modern input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #dc3545; /* DIUBAH: Dari abu-abu menjadi merah */
    border-radius: 34px;
    transition: 0.4s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    border-radius: 50%;
    transition: 0.4s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

input:checked + .slider {
    background-color: #198754; /* DIUBAH: Dari biru menjadi hijau */
}

input:focus + .slider {
    box-shadow: 0 0 1px #198754; /* DIUBAH: Warna focus disamakan hijau */
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Bagian ikon tidak perlu diubah, sudah cocok */
.slider::after {
    font-family: "bootstrap-icons";
    content: "\F62A"; /* Ikon 'x-lg' */
    position: absolute;
    right: 9px;
    top: 7px;
    color: white;
    font-size: 16px;
    transition: opacity 0.4s ease;
    opacity: 1;
}

input:checked + .slider::after {
    font-family: "bootstrap-icons";
    content: "\F26E"; /* Ikon 'check-lg' */
    left: 9px;
    opacity: 1;
}

input:not(:checked) + .slider::after {
    right: 9px;
}

/* Header & Status Badge (Tidak berubah dari sebelumnya) */
.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 0.5rem 0;
}
.schedule-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.schedule-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.schedule-icon.bg-primary-soft {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
}
.schedule-icon.bg-warning-soft {
    background-color: var(--hcis-amber-50);
    color: var(--hcis-amber-500);
}
.schedule-icon.bg-success-soft {
    background-color: var(--hcis-green-50);
    color: var(--hcis-emerald-500);
}
.schedule-icon.bg-info-soft {
    background-color: var(--hcis-cyan-50);
    color: var(--hcis-cyan-500);
}
.schedule-icon.bg-secondary-soft {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}

.schedule-name {
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.schedule-details {
    font-size: 0.85rem;
    color: var(--hcis-slate-500);
}

.badge-status-active,
.badge-status-inactive {
    display: inline-flex;
    align-items: center;
    padding: 0.35em 0.8em;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 9999px;
}
.badge-status-active {
    background-color: var(--hcis-green-100);
    color: #166534;
}
.badge-status-inactive {
    background-color: var(--hcis-red-100);
    color: var(--hcis-red-800);
}
.badge-status-arsip {
    display: inline-flex;
    align-items: center;
    padding: 0.35em 0.8em;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 9999px;
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.badge-status-active i,
.badge-status-inactive i,
.badge-status-arsip i {
    margin-right: 0.4rem;
}

/* -- TAB AKTIF / ARSIP -- */
.arsip-tabs {
    display: inline-flex;
    gap: 0.35rem;
    padding: 0.25rem;
    background-color: var(--hcis-slate-100);
    border-radius: 9999px;
}
.arsip-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    border: 0;
    border-radius: 9999px;
    background: transparent;
    color: var(--hcis-slate-500);
    font-size: 0.875rem;
    font-weight: 600;
    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}
.arsip-tab:hover {
    color: var(--hcis-slate-700);
}
.arsip-tab.is-selected {
    background-color: var(--hcis-white);
    color: var(--hcis-slate-800);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
}
.arsip-tab-count {
    min-width: 1.5rem;
    padding: 0 0.4rem;
    border-radius: 9999px;
    background-color: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    line-height: 1.35rem;
    text-align: center;
}
.arsip-tab.is-selected .arsip-tab-count {
    background-color: var(--hcis-indigo-100);
    color: var(--hcis-indigo-800);
}

.arsip-note {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    background-color: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    font-size: 0.85rem;
}
.arsip-note i {
    color: var(--hcis-slate-500);
    margin-top: 0.1rem;
}

/* Tombol aksi jadwal: metrik seragam, ikon sejajar teks.
   Sengaja tanpa .mt-customFransIcon (hack margin -10px) supaya ikon tidak
   terdorong ke pojok kiri-atas tombol. */
.schedule-actions-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.schedule-actions-modern .btn-arsip-modern,
.schedule-actions-modern .btn-edit-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 2.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.25;
    white-space: nowrap;
}
.schedule-actions-modern .btn-arsip-modern i,
.schedule-actions-modern .btn-edit-modern i {
    font-size: 1rem;
    line-height: 1;
    /* netralkan hack ikon global bila kelas tsb ikut terpasang */
    margin: 0;
}

.btn-arsip-modern {
    border: 1px solid var(--hcis-slate-300);
    background-color: var(--hcis-white);
    color: var(--hcis-slate-600);
    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
}
.btn-arsip-modern:hover,
.btn-arsip-modern:focus-visible {
    background-color: var(--hcis-slate-100);
    border-color: var(--hcis-slate-400);
    color: var(--hcis-slate-800);
}

/* Body & Aksi (Ada Perubahan) */
.schedule-body {
    padding: 1.25rem;
    border-top: 1px solid var(--hcis-slate-200);
    display: grid;
    gap: 1.5rem;
    background-color: var(--hcis-slate-50);
}
.detail-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.schedule-description p {
    color: var(--hcis-slate-700);
    margin-bottom: 0;
    font-size: 0.95rem;
}
.schedule-timing {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.timing-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.timing-item i {
    font-size: 1.25rem;
}
.timing-item strong {
    color: var(--hcis-slate-600);
}
.timing-item span {
    margin-left: 0.5rem;
    color: var(--hcis-slate-500);
    font-family: monospace;
    font-size: 0.9rem;
}
.flags-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.flag-item {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease-in-out;
    border: 1px solid var(--hcis-slate-200);
}
.flag-item.active {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-weight: 600;
    border-color: var(--hcis-indigo-200);
}
.flag-item.active i {
    color: var(--hcis-indigo-500);
}

.schedule-actions {
    margin-top: 0.5rem;
    padding-top: 1.5rem;
    border-top: 1px dashed var(--hcis-slate-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.action-toggle.form-switch {
    padding-left: 3.5em; /* Memberi ruang untuk label */
}
.action-toggle .form-check-input {
    width: 3em;
    height: 1.5em;
    cursor: pointer;
}
.action-toggle .form-check-input:checked {
    background-color: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
}
.action-toggle .form-check-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--hcis-slate-600);
    cursor: pointer;
}
/* end according */

.mt-customFransIcon {
    margin-top: -10px;
    margin-left: -10px;
}

.mt-customIconAccordion {
    margin-top: -14px;
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
    color: white !important;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
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

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
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

/* Tombol Aksi di Tabel */
.btn-action {
    color: var(--secondary);
    background-color: transparent;
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}
.btn-action:hover {
    background-color: var(--surface-soft);
    color: var(--primary);
}

/* Search Box */
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-input {
    padding-left: 40px;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
}

/* Tombol di Modal (diasumsikan digunakan oleh komponen ModalV1) */
.btn-primary {
    background-color: var(--primary);
    border-color: var(--primary);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
}
.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}
.btn-outline-secondary {
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border-color: var(--border);
    color: var(--text-muted);
}
.btn-outline-secondary:hover {
    background-color: var(--surface-soft);
    color: var(--dark);
}

@media (max-width: 1200px) {
    .modern-header {
        min-height: 120px;
    }
}

@media (max-width: 992px) {
    .header-info {
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
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
}

@media (max-width: 768px) {
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

/* Status KPI dipisah per jalur (personal / tim) di kartu karyawan. */
.ringkasan-jalur-kpi {
    margin-top: 0.15rem;
    font-size: 0.68rem;
    line-height: 1.3;
    opacity: 0.75;
}
</style>

<style scoped>
.toolbar-modern {
    --c-primary: var(--hcis-indigo-500);
    --c-primary-soft: var(--hcis-indigo-50);
    --c-text-main: var(--hcis-slate-900);
    --c-text-sub: var(--hcis-slate-500);
    --c-border: var(--hcis-slate-200);
    --c-bg-hover: var(--hcis-slate-50);
    --c-success-bg: var(--hcis-green-100);
    --c-success-text: var(--hcis-green-700);
    --c-warning-bg: var(--hcis-amber-100);
    --c-warning-text: var(--hcis-amber-700);
    --c-info-bg: var(--hcis-sky-100);
    --c-info-text: var(--hcis-sky-700);
    --c-purple-bg: var(--hcis-purple-100);
    --c-purple-text: var(--hcis-purple-700);
    --c-slate-bg: var(--hcis-slate-100);
    --c-slate-text: var(--hcis-slate-600);
}

.toolbar-modern {
    background: white;
    padding: 0.85rem;
    border-radius: 16px;
    border: 1px solid var(--c-border);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.search-wrapper {
    flex-grow: 1;
    position: relative;
}

.search-input-modern {
    width: 100%;
    padding: 12px 12px 12px 46px;
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-100);
    background-color: var(--hcis-slate-50);
    color: var(--c-text-main);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.search-input-modern:focus {
    background-color: white;
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    outline: none;
}

.search-icon-float {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--c-text-sub);
    font-size: 1.1rem;
    pointer-events: none;
}

.check-all-wrapper {
    display: flex;
    align-items: center;
    padding: 10px 20px;
    background-color: white;
    border-radius: 12px;
    border: 1px solid var(--c-border);
    cursor: pointer;
    transition: all 0.2s ease;
}

.check-all-wrapper:hover {
    background-color: var(--c-bg-hover);
    border-color: var(--hcis-slate-300);
}

.avatar-modern {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
    box-shadow: 0 8px 16px -4px rgba(118, 75, 162, 0.3);
}

.card-modern {
    background: var(--hcis-white);
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 16px;
    padding: 1.25rem;
    cursor: pointer;
    position: relative;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition:
        transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        border-color 0.2s;
    box-shadow:
        0 1px 2px rgba(0, 0, 0, 0.04),
        0 4px 6px rgba(0, 0, 0, 0.03);
}

.card-modern:hover {
    border-color: var(--hcis-indigo-400);
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08);
}

.card-modern.selected {
    background: #fcfcff;
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 1px var(--hcis-indigo-500);
}

.card-modern.disabled {
    background-color: var(--hcis-slate-50);
    cursor: default;
    opacity: 1;
}

.card-modern.disabled:hover {
    transform: none;
    box-shadow: none;
    border-color: rgba(0, 0, 0, 0.06);
    cursor: not-allowed;
}

.card-modern.disabled .avatar-modern {
    filter: grayscale(100%);
    opacity: 0.4;
    box-shadow: none;
}

.card-modern.disabled .fw-bold.text-truncate,
.card-modern.disabled .meta-row,
.card-modern.disabled .badge-pill.level {
    filter: grayscale(100%);
    opacity: 0.6;
    color: var(--hcis-slate-400) !important;
}

/* Kode karyawan pada kartu pemilih. Monospace + tabular supaya dua kode yang
   mirip terbaca bedanya sekali lihat, bukan setelah dieja huruf per huruf. */
.kode-karyawan-pilih {
    font-size: 0.76rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
    opacity: 0.75;
    margin-top: 2px;
}

.badge-pill {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-transform: uppercase;
    line-height: 1;
}

.badge-pill.level {
    background-color: var(--c-purple-bg);
    color: var(--c-purple-text);
}

.badge-pill.kpi {
    background-color: var(--c-info-bg);
    color: var(--c-info-text);
    font-family: monospace;
    font-size: 0.75rem;
}

.badge-pill.success {
    background-color: var(--c-success-bg);
    color: var(--c-success-text);
}

/* Override for disabled state specifically - Full Solid Badge */
.card-modern.disabled .badge-pill.success {
    background-color: #22c55e !important;
    color: white !important;
    opacity: 1 !important;
    filter: none !important;
    box-shadow: 0 2px 4px rgba(34, 197, 94, 0.3);
}

.badge-pill.warning {
    background-color: var(--c-warning-bg);
    color: var(--c-warning-text);
}

/* Override for disabled state specifically - Full Solid Badge */
.card-modern.disabled .badge-pill.warning {
    background-color: var(--hcis-amber-500) !important;
    color: white !important;
    opacity: 1 !important;
    filter: none !important;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.meta-row {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--c-text-sub);
    font-size: 0.8rem;
    margin-top: 4px;
}

.meta-icon {
    color: var(--hcis-slate-400);
    font-size: 1rem;
}

.custom-checkbox-xl {
    appearance: none;
    width: 24px;
    height: 24px;
    border: 2px solid var(--hcis-slate-300);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
    background: white;
}

.custom-checkbox-xl:checked {
    background-color: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
}

.custom-scrollbar {
    max-height: 550px;
    padding: 6px;
}

.divider-dashed {
    border-top: 1px dashed var(--c-border);
    margin: 0.75rem 0;
}
</style>
