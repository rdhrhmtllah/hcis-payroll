<template>
    <!-- Header -->
    <div class="row d-flex align-items-center justify-content-center mb-3 fade-in">
        <div class="col-md-11">
            <div class="modern-header">
                <div class="header-background"></div>
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-sm-8">
                            <div class="header-info">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <i class="bi bi-person-badge d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div class="text-content">
                                    <h2 class="title fw-bold text-white text-start">
                                        Halo,
                                        {{ capitalize(namaKaryawan?.split(' ').slice(0, 2).join(' ')) }}
                                    </h2>
                                    <p class="subtitle">Halaman menambahkan karyawan team</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <div class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3">
                                    <div class="date-item">
                                        <i
                                            v-if="dayNight == 'Siang'"
                                            class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                        ></i>
                                        <i v-else class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"></i>
                                        <span class="fs-md-6 text-header">{{ formatDateString(currentDate) }}</span>
                                    </div>
                                </div>
                                <button
                                    @click="
                                        searchQuery = '';
                                        showAssignModal = true;
                                    "
                                    @disabed="allKaryawan.length < 1"
                                    class="btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                >
                                    <i
                                        class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="text-header">Tugaskan Team</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Control Panel -->
    <div class="row d-flex justify-content-center mb-1 mb-md-3 fade-in">
        <div class="col-md-11">
            <div class="control-panel">
                <div class="search-filter-section">
                    <div class="d-md-flex flex-grow-1 row gap-0 gap-md-2 px-2 px-md-0">
                        <div class="d-md-none d-block flex-grow-1 col-6 col-md-12 p-1">
                            <input
                                v-if="!selectedDateFilter"
                                v-model="selectedDateFilter"
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                style="cursor: pointer"
                                title="Pilih Tanggal"
                            />
                            <div
                                v-else
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                title="Pilih Tanggal"
                                style="cursor: pointer"
                            >
                                {{ formatDateToDMY(selectedDateFilter) }}
                                <span
                                    @click="selectedDateFilter = null"
                                    v-tooltip="'Hapus filter'"
                                    class="closeFilter p-1 rounded-3 ms-2"
                                    ><i class="bi bi-x"></i
                                ></span>
                            </div>
                        </div>

                        <div class="search-container d-md-flex d-none pe-0">
                            <i
                                class="ms-2 bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari izin..."
                                v-model="searchQuery"
                                @focus="showSearchSuggestions = true"
                                @blur="hideSuggestions"
                            />
                            <div class="search-results-count" v-if="searchQuery">{{ dataRecord.length }} hasil</div>
                        </div>
                    </div>

                    <div class="filters-container">
                        <div class="search-container d-block d-md-none">
                            <i class="bi bi-search search-icon d-flex justify-content-center align-items-center"></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari izin..."
                                v-model="searchQuery"
                                @focus="showSearchSuggestions = true"
                                @blur="hideSuggestions"
                            />
                            <div class="search-results-count" v-if="searchQuery">{{ dataRecord.length }} hasil</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row d-flex justify-content-center fade-in">
        <div class="col-md-11">
            <div class="content-container">
                <!-- Loading -->
                <div v-if="loadingData" class="loading-container">
                    <div class="loading-animation">
                        <div class="loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <p class="loading-text">Memuat data Karyawan...</p>
                </div>

                <!-- Not Found -->
                <div v-else-if="dataRecord.length === 0 && !loadingData" class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-inbox d-flex justify-content-center align-items-center"></i>
                    </div>
                    <h4>Karyawan tidak ditemukan</h4>
                    <p>Karyawan yang dicari belum ada!</p>
                </div>

                <transition v-else name="fade-switch" mode="out-in">
                    <!-- Table -->
                    <div class="table-view-container" key="table">
                        <div class="table-responsive-wrapper">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th class="header-cell sticky-column">
                                            <div class="header-cell-content py-2">
                                                <i
                                                    class="bi bi-people-fill me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span
                                                    >Nama <small class="fw-light">({{ totalData }})</small></span
                                                >
                                            </div>
                                        </th>

                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-collection-fill me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Jabatan</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-clipboard-check-fill me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Approver</span>
                                            </div>
                                        </th>

                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-people-fill me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Jumlah Team</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-person-vcard me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>User Web</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-person-rolodex me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Tergabung Team</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in dataRecord"
                                        :key="item.Kode_Karyawan"
                                        @click="showDetail(item)"
                                        class="table-row"
                                    >
                                        <td class="sticky-column">
                                            <div class="transaction-cell">
                                                <div class="transaction-id">
                                                    {{ item.namaUser }}
                                                </div>
                                                <div class="transaction-type">
                                                    {{ item.nama_divisi }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-table-cell">
                                                {{ item.nama_jabatan }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="reason-cell">
                                                {{ approversInfoFromString(item.Approvers).total }}
                                                Orang
                                            </div>
                                        </td>

                                        <td>
                                            <div v-if="item.Team != 0" class="reason-cell">
                                                {{ item.Team }}
                                            </div>
                                            <div v-else class="reason-cell text-denger">Belum ada</div>
                                        </td>
                                        <td>
                                            <div v-if="item.UserID_Web != 0" class="reason-cell">
                                                {{ item.UserID_Web }}
                                            </div>
                                            <div v-else class="reason-cell text-denger">Belum ada</div>
                                        </td>
                                        <td>
                                            <div v-if="tergabungTeam(item).total != 0" class="reason-cell">
                                                {{ tergabungTeam(item).total }}
                                            </div>
                                            <div v-else class="reason-cell text-denger">Belum ada</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="pagination-container" v-if="lastPage > 1">
                            <button
                                @click="getData(prevLink)"
                                class="page-btn"
                                :disabled="currentPage === 1 || prevLink == null"
                            >
                                <i class="bi bi-chevron-left d-flex justify-content-center align-items-center"></i>
                            </button>
                            <div class="page-info">Halaman {{ currentPage }} dari {{ lastPage }}</div>
                            <button @click="getData(nextLink)" class="page-btn" :disabled="currentPage === lastPage">
                                <i class="bi bi-chevron-right d-flex justify-content-center align-items-center"></i>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>

    <div class="modal-overlay" :class="{ show: showAssignModal }">
        <div class="modal-container assign-modal" @click.stop>
            <!-- Header with progress indicator -->
            <div class="modal-header">
                <div class="header-title-section">
                    <h3>
                        <i class="bi bi-calendar-plus me-2 d-flex justify-content-center align-items-center"></i>
                        Prefrences karyawan website
                    </h3>
                    <p class="header-subtitle">Input Karyawan Team, Create user Karyawan, dan Karyawan Approver</p>
                </div>
                <button class="modal-close" @click="closeAssignModal" aria-label="Close">
                    <i class="bi bi-x-lg d-flex justify-content-center align-items-center"></i>
                </button>
            </div>

            <div class="modal-body approval">
                <div v-if="assignStep === 1" class="step-content">
                    <div class="form-section">
                        <!-- Jenis Izin -->
                        <h4 class="section-title">
                            <i class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Jenis Submit
                        </h4>

                        <div class="form-group">
                            <div class="permission-type-options row g-2">
                                <div class="col-12 col-md-4">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected: assignSubmit === 'team',
                                        }"
                                        @click="assignSubmit = 'team'"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Karyawan Team</h6>
                                            <p class="text-muted small">Input Karyawan Team</p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i class="bi bi-check-circle-fill" v-if="assignSubmit === 'team'"></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected: assignSubmit === 'websiteUser',
                                        }"
                                        @click="assignSubmit = 'websiteUser'"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-person-circle"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Karyawan Webiste User</h6>
                                            <p class="text-muted small">Input karyawan user website</p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="assignSubmit === 'websiteUser'"
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected: assignSubmit === 'approver',
                                        }"
                                        @click="assignSubmit = 'approver'"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-card-checklist"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Karyawan Approver</h6>
                                            <p class="text-muted small">Input karyawan Approver</p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i class="bi bi-check-circle-fill" v-if="assignSubmit === 'approver'"></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-people me-2"></i>
                            Pilih Karyawan
                        </h4>

                        <!-- Search box -->
                        <div class="search-container assign-search">
                            <i class="bi bi-search search-icon d-flex justify-content-center align-items-center"></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari nama karyawan..."
                                v-model="assignSearchQuery"
                                @focus="showAssignDropdown = true"
                                @blur="hideAssignDropdown"
                            />
                        </div>

                        <!-- Dropdown suggestion -->
                        <ul
                            v-if="showAssignDropdown && suggestionAssign.length"
                            class="dropdown-suggestion p-0 max-w-fit"
                        >
                            <li
                                v-for="employee in suggestionAssign"
                                :key="employee.Kode_Karyawan"
                                @mousedown.prevent="
                                    selectedAssignEmployees = employee;
                                    // console.log(selectedAssignEmployees);
                                    showAssignDropdown = false;
                                "
                                class="dropdown-item max-w-fit"
                            >
                                {{ truncateText(employee.Nama, 20) }}
                                <span class="opacity-50">
                                    {{ employee.nama_divisi }} | {{ employee.Kode_Karyawan }} |
                                    {{ employee.HP }}
                                </span>
                            </li>
                        </ul>

                        <!-- Selected Employee -->
                        <div v-if="selectedAssignEmployees" class="mt-3 selected-employee">
                            <strong>Dipilih:</strong>
                            {{ selectedAssignEmployees.Nama }}
                        </div>
                    </div>
                </div>

                <div v-if="assignStep === 2" class="step-content">
                    <!-- Karyawan Team -->
                    <div v-if="assignSubmit == 'team'">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="bi bi-gear me-2"></i>
                                Leader Team
                            </h4>

                            <!-- Time Selection (for early leave/late arrival) -->

                            <!-- Description -->
                            <div class="form-group mb-3">
                                <!-- <label class="form-label">Team Leader</label> -->
                                <label class="form-mark">{{ selectedAssignEmployees.Nama }}</label>
                            </div>
                        </div>
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="bi bi-people me-2 d-flex justify-content-center align-items-center"></i>
                                Pilih Team
                            </h4>
                            <div class="form-group team mb-3">
                                <!-- Search Employee -->

                                <div class="row">
                                    <!-- LIST KARYAWAN -->
                                    <div class="col-md-6">
                                        <div class="form-label">Yang tersedia</div>
                                        <div class="search-container assign-search">
                                            <i
                                                class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                                            ></i>
                                            <input
                                                type="text"
                                                class="search-input"
                                                placeholder="Cari nama karyawan..."
                                                v-model="yangTersediaSearch"
                                            />
                                        </div>
                                        <div class="employee-selection-list">
                                            <div
                                                v-for="employee in availableList"
                                                :key="employee.Kode_Karyawan"
                                                class="employee-selection-item"
                                                :class="{
                                                    selected: selectedAssignEmployees == employee,
                                                }"
                                                @click="
                                                    addToTeam(employee)
                                                    // console.log(newMembers);
                                                "
                                            >
                                                <!-- <div class="employee-checkbox">
                                                    <i
                                                        class="bi bi-check d-flex justify-content-center align-items-center"
                                                        v-if="
                                                            selectedAssignEmployees ==
                                                            employee
                                                        "
                                                    ></i>
                                                </div> -->
                                                <div class="employee-avatar">
                                                    {{ getInitials(employee.Nama) }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name" style="line-height: 1">
                                                        {{ employee.Nama }}
                                                        <br />
                                                        <span
                                                            style="
                                                                line-height: 1;
                                                                font-size: 10px;
                                                                margin: 0;
                                                                padding: 0;
                                                                font-color: gray;
                                                            "
                                                            >{{ employee.Kode_Karyawan }}</span
                                                        >
                                                    </div>

                                                    <div class="employee-department">
                                                        {{
                                                            employee.nama_divisi?.toUpperCase() +
                                                            ' | ' +
                                                            employee.nama_jabatan
                                                        }}
                                                    </div>
                                                    <!-- <div class="" style="font-size: 0.6rem">
                                                    {{ console.log(employee) }}
                                                </div> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- YANG SUDAH ADA -->
                                    <div class="col-md-6">
                                        <div class="form-label">Tergabung</div>

                                        <div class="search-container assign-search">
                                            <i
                                                class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                                            ></i>
                                            <input
                                                type="text"
                                                class="search-input"
                                                placeholder="Cari nama karyawan..."
                                                v-model="tergabungSearch"
                                            />
                                        </div>
                                        <div class="employee-selection-list">
                                            <div
                                                v-for="employee in haveTeam"
                                                :key="employee.Kode_Karyawan"
                                                class="employee-selection-item"
                                                :class="{
                                                    selected: selectedAssignEmployees == employee,
                                                }"
                                                @click="
                                                    removeFromTeam(employee)
                                                    // console.log(removedMembers);
                                                "
                                            >
                                                <div class="employee-checkbox rounded-4 bg">
                                                    <i
                                                        class="bi bi-x tex-white d-flex justify-content-center align-items-center"
                                                    ></i>
                                                </div>
                                                <div class="employee-avatar">
                                                    {{ getInitials(employee.Nama) }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name" style="line-height: 1">
                                                        {{ employee.Nama }}
                                                        <br />
                                                        <span
                                                            style="
                                                                font-size: 10px;
                                                                margin: 0;
                                                                padding: 0;
                                                                font-color: gray;
                                                            "
                                                            >{{ employee.Kode_Karyawan }}</span
                                                        >
                                                    </div>

                                                    <div class="employee-department">
                                                        {{
                                                            employee.nama_divisi?.toUpperCase() +
                                                            ' | ' +
                                                            employee.nama_jabatan
                                                        }}
                                                    </div>
                                                    <!-- <div class="" style="font-size: 0.6rem">
                                                    {{ console.log(employee) }}
                                                </div> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="assignSubmit == 'websiteUser'">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="bi bi-gear me-2"></i>
                                Karyawan yang akan diberi User
                            </h4>

                            <!-- Time Selection (for early leave/late arrival) -->

                            <!-- Description -->
                            <div class="form-group mb-3">
                                <!-- <label class="form-label">Team Leader</label> -->
                                <label class="form-mark">{{ selectedAssignEmployees.Nama }}</label>
                            </div>
                        </div>
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="bi bi-gear me-2"></i>
                                SetingUser
                            </h4>
                            <div class="row">
                                <div class="col-md-8 py-3">
                                    <div class="form-group">
                                        <label class="form-label">Username</label>
                                        <input
                                            v-model="AssignUsername"
                                            @input="filterUsername"
                                            :disabled="hasExistingUsername()"
                                            type="text"
                                            class="form-control"
                                            :class="{
                                                'border-danger':
                                                    (!usernameValid && usernameError) ||
                                                    (alredyUsername && !hasExistingUsername()),
                                            }"
                                            placeholder="Hanya huruf, angka, dan simbol _ % !"
                                        />

                                        <!-- Error message untuk validasi format -->
                                        <span class="text-danger text-sm" v-if="usernameError && !usernameValid">
                                            {{ usernameError }}
                                        </span>

                                        <!-- Error message untuk username sudah ada -->
                                        <span
                                            class="text-danger text-sm"
                                            v-else-if="alredyUsername && !hasExistingUsername()"
                                        >
                                            Username sudah ada!
                                        </span>

                                        <!-- Helper text (opsional) -->
                                        <small class="form-text text-muted">
                                            Format: huruf, angka, dan simbol _ % ! (minimal 3 karakter)
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4 py-3">
                                    <div class="form-group">
                                        <label class="form-label mb-3">Extras</label>
                                        <!-- <div class="d-flex align-items-center">
                                            <div
                                                class="form-check form-switch me-3"
                                            >
                                                <input
                                                    style="
                                                        cursor: pointer !important;
                                                    "
                                                    disabled
                                                    class="form-check-input cursor-pointer"
                                                    type="checkbox"
                                                    id="whatsappSwitch"
                                                    v-model="isWhatsappUser"
                                                />
                                            </div>
                                            <label
                                                class="form-check-label"
                                                for="whatsappSwitch"
                                            >
                                                Kirim WhatsApp
                                            </label>
                                        </div> -->
                                        <div class="d-flex align-items-center">
                                            <button
                                                :disabled="!selectedAssignEmployees.Username"
                                                @click="
                                                    showAlertModalFC(
                                                        'Anda yakin untuk mengirim ulang user dan password user ini?',
                                                        'kirimUlangUser',
                                                        'Kirim',
                                                    )
                                                "
                                                style="cursor: pointer !important"
                                                class="me-3 btn-primary px-2 py-2 btn-modern d-flex align-items-center"
                                            >
                                                <i
                                                    v-if="!loadingSimpan"
                                                    class="bi bi-send me-2 d-flex justify-content-center align-items-center"
                                                ></i>

                                                <i v-else class="spinner-border me-2 spinner-border-sm ms-2"></i>
                                                Send
                                            </button>
                                            <label
                                                class="form-check-label d-flex align-items-center mb-0"
                                                for="whatsappSwitch"
                                            >
                                                Reset Password
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group user">
                                <div class="access-panel-header">
                                    <div>
                                        <label class="form-label mb-1">Akses diberi</label>
                                        <p class="access-panel-subtitle mb-0">
                                            Kelompokkan akses per fungsi agar lebih mudah dibaca saat menu bertambah.
                                        </p>
                                    </div>
                                    <div class="access-summary-pill">{{ accessSelectionCount }} akses dipilih</div>
                                </div>
                                <div class="access-toolbar">
                                    <div class="search-container assign-search access-search">
                                        <i
                                            class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                                        ></i>
                                        <input
                                            type="text"
                                            class="search-input"
                                            placeholder="Cari akses page..."
                                            v-model="accessSearchQuery"
                                        />
                                    </div>
                                    <div class="access-toolbar-actions">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light access-action-btn"
                                            @click="selectAllVisibleAccess"
                                        >
                                            Pilih semua
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light access-action-btn"
                                            @click="clearAllAccess"
                                        >
                                            Reset
                                        </button>
                                    </div>
                                </div>

                                <div v-if="selectedAccessMenus.length" class="selected-access-list">
                                    <span
                                        v-for="menu in selectedAccessMenus"
                                        :key="menu.pageKey"
                                        class="selected-access-chip"
                                    >
                                        <i :class="[menu.icon, 'me-2']"></i>
                                        {{ menu.name }}
                                    </span>
                                </div>

                                <div v-if="filteredMenuGroups.length" class="access-groups">
                                    <div v-for="group in filteredMenuGroups" :key="group.key" class="access-group-card">
                                        <div class="access-group-header">
                                            <div>
                                                <h6 class="mb-1">
                                                    {{ group.label }}
                                                </h6>
                                                <small>
                                                    {{ group.items.length }}
                                                    menu
                                                </small>
                                            </div>
                                        </div>

                                        <div class="access-option-grid">
                                            <button
                                                v-for="menu in group.items"
                                                :key="menu.pageKey"
                                                type="button"
                                                class="access-option"
                                                :class="{
                                                    selected: isActivePages(menu.pageKey),
                                                }"
                                                @click="toggleAccess(menu.pageKey)"
                                            >
                                                <span class="access-option-main">
                                                    <span class="access-icon">
                                                        <i :class="[menu.icon]"></i>
                                                    </span>
                                                    <span class="access-copy">
                                                        <span class="access-name">{{ menu.name }}</span>
                                                        <small class="access-meta">{{ menu.categoryLabel }}</small>
                                                    </span>
                                                </span>
                                                <span class="access-check">
                                                    <i class="bi bi-check-lg"></i>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="access-empty-state">
                                    Tidak ada akses yang cocok dengan pencarian.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="assignSubmit == 'approver'">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="bi bi-gear me-2"></i>
                                Karyawan yang akan diberi approver
                            </h4>

                            <!-- Time Selection (for early leave/late arrival) -->

                            <!-- Description -->
                            <div class="form-group mb-3">
                                <!-- <label class="form-label">Team Leader</label> -->
                                <label class="form-mark">{{ selectedAssignEmployees.Nama }}</label>
                            </div>
                        </div>
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="bi bi-person-plus me-2"></i>
                                Pilih Approver
                            </h4>

                            <div class="form-group AssignApproval mb-3">
                                <div v-for="(item, idx) in formFields" :key="idx" class="approver-field mb-3">
                                    <label class="form-label">Approver {{ idx + 1 }}</label>
                                    <div class="position-relative">
                                        <input
                                            type="text"
                                            v-model="item.Nama"
                                            @input="item.showDropdown = true"
                                            @focus="item.showDropdown = true"
                                            @blur="hideDropdown(idx)"
                                            class="form-control"
                                            placeholder="Ketik nama karyawan..."
                                        />

                                        <!-- Dropdown suggestion -->
                                        <ul
                                            v-if="item.showDropdown && suggestionFilter(item.Nama, idx).length"
                                            class="dropdown-suggestion"
                                        >
                                            <li
                                                v-for="(karyawan, kIdx) in suggestionFilter(item.Nama, idx)"
                                                :key="kIdx"
                                                @mousedown.prevent="selectKaryawan(idx, karyawan)"
                                                class="dropdown-item"
                                            >
                                                {{ karyawan.Nama }}
                                                <span class="opacity-50">
                                                    |
                                                    {{ karyawan.nama_divisi }}</span
                                                >
                                            </li>
                                        </ul>
                                        <button
                                            @click="removeField(idx)"
                                            class="btn-remove closeRounded rounded-3 me-2"
                                        >
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Tombol tambah -->
                                <div class="add-field">
                                    <button @click="addField" class="btn-add">
                                        <i class="bi bi-plus me-1"></i> Tambah
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="footer-actions">
                    <button v-if="assignStep > 1" class="btn-modern btn-secondary" @click="prevAssignStep">
                        <i class="bi bi-arrow-left me-2 d-flex justify-content-center align-items-center"></i>
                        Kembali
                    </button>

                    <button class="btn-modern btn-secondary" @click="closeAssignModal">Batal</button>

                    <button
                        v-if="assignStep < 2"
                        class="btn-modern btn-primary"
                        @click="nextAssignStep"
                        :disabled="!canProceedToNextStep"
                    >
                        Lanjut
                        <i
                            v-if="!isLoadingAssign"
                            class="bi bi-arrow-right ms-2 d-flex justify-content-center align-items-center"
                        ></i>
                        <i v-if="isLoadingAssign" class="spinner-border spinner-border-sm ms-2"></i>
                    </button>

                    <button
                        v-if="assignStep === 2"
                        :disabled="loadingSimpan"
                        class="btn-modern btn-success"
                        @click="showAlertModalFC('Anda yakin ingin menyimpan data ini?', 'simpanAssign', 'Simpan')"
                    >
                        <i
                            v-if="!loadingSimpan"
                            class="bi bi-check-lg me-2 d-flex justify-content-center align-items-center"
                        ></i>

                        <span v-else class="spinner-border spinner-border-sm me-2"></span>Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- details modal -->

    <el-dialog
        v-model="showDetailModal"
        :title="`Detail Karyawan - ${currentDetail?.Nama || ''}`"
        width="70%"
        :close="closeDetailModal"
        :fullscreen="isMobile()"
        class="detail-modal"
        :lock-scroll="true"
    >
        <div v-if="currentDetail" class="detail-modal-content">
            <div class="detail-grid">
                <!-- Basic Information -->
                <div class="detail-item">
                    <span class="detail-label">Nama:</span>
                    <span class="detail-value highlight">
                        {{ currentDetail.namaUser || 'TIDAK ADA' }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Divisi:</span>
                    <span class="detail-value">
                        {{ currentDetail.nama_divisi || 'TIDAK ADA' }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Jabatan:</span>
                    <span class="detail-value">
                        {{ currentDetail.nama_jabatan || 'TIDAK ADA' }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">ID WEB:</span>
                    <span class="detail-value">
                        {{ currentDetail.UserID_Web || 'TIDAK ADA' }}
                    </span>
                </div>

                <!-- Approvers Section -->
                <div class="detail-item full-width">
                    <span class="detail-label">Approver:</span>
                    <div class="scrollable-container">
                        <template v-if="!currentApprovers.length">
                            <el-tag type="info" size="medium">TIDAK ADA</el-tag>
                        </template>
                        <template v-else>
                            <el-tag
                                v-for="(approver, index) in currentApprovers"
                                :key="index"
                                type="success"
                                size="medium"
                                class="approver-tag"
                            >
                                {{ approver }}
                            </el-tag>
                        </template>
                    </div>
                </div>

                <!-- Teams Membership Section -->
                <div class="detail-item full-width">
                    <span class="detail-label">Tergabung pada tim:</span>
                    <div class="scrollable-container">
                        <template v-if="!currentTeamMemberships.length">
                            <el-tag type="info" size="medium">TIDAK ADA</el-tag>
                        </template>
                        <template v-else>
                            <el-tag
                                v-for="(team, index) in currentTeamMemberships"
                                :key="index"
                                :type="getTagType(index)"
                                size="medium"
                                class="membership-tag"
                            >
                                {{ team }}
                            </el-tag>
                        </template>
                    </div>
                </div>

                <!-- Approvers Section -->
                <div class="detail-item full-width">
                    <span class="detail-label">Mengapprove:</span>
                    <div class="scrollable-container">
                        <template v-if="!currentMengapprove.length">
                            <el-tag type="info" size="medium">TIDAK ADA</el-tag>
                        </template>
                        <template v-else>
                            <el-tag
                                v-for="(requester, index) in currentMengapprove"
                                :key="index"
                                type="success"
                                size="medium"
                                class="approver-tag"
                            >
                                {{ requester }}
                            </el-tag>
                        </template>
                    </div>
                </div>

                <button
                    v-if="currentMengapprove?.length > 0"
                    @click="handleClickPindah()"
                    style="width: fit-content"
                    class="btn-modern btn-danger text-white"
                >
                    Alihkan Approval
                </button>

                <div class="detail-item full-width">
                    <span class="detail-label">Team:</span>
                    <div class="scrollable-container">
                        <template v-if="haveTeamModalResult?.length == 0">
                            <el-tag type="info" size="medium">TIDAK ADA</el-tag>
                        </template>
                        <template v-else>
                            <el-tag
                                v-for="(requester, index) in haveTeamModalResult"
                                :key="index"
                                type="success"
                                size="medium"
                                class="approver-tag"
                                v-tooltip="requester.Nama"
                            >
                                {{ truncateText(requester.Nama, 10) }} |
                                {{ requester.nama_divisi }}
                            </el-tag>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="modal-footer-actions p-0 pt-3">
                <button class="btn-modern btn-secondary" @click="closeDetailModal">Tutup</button>
            </div>
        </template>
    </el-dialog>

    <!-- alert modal -->
    <el-dialog
        v-model="showAlertModal"
        title="Konfirmasi menyimpan data"
        width="400px"
        center
        :close="closeAlertModal"
        :lock-scroll="true"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                {{ Pesan }}
            </p>
            <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button class="btn-modern btn-secondary" @click="closeAlertModal">Batal</button>
                <button class="btn-modern btn-success" @click="fnc">
                    {{ buttonAlert }}
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- modal pindah approval -->
    <el-dialog
        v-model="showpindahModal"
        title="Pindahkan approval"
        width="1000px"
        center
        :lock-scroll="true"
        :close="closePindahModal"
    >
        <div class="modal-content-wrapper">
            <div class="transfer-approval-container">
                <div class="transfer-flow">
                    <!-- Current Approver -->
                    <div class="approver-section current-approver">
                        <label class="section-label">Approval Sebelumnya</label>
                        <div class="approver-card">
                            <div class="user-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div class="user-info">
                                <div class="user-name">
                                    {{ currentDetail?.namaUser }}
                                </div>
                                <div class="user-status">Current Approver</div>
                            </div>
                        </div>
                    </div>

                    <div class="transfer-arrow">
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </div>

                    <!-- New Approver -->
                    <div class="approver-section new-approver">
                        <label class="section-label">Approval Baru</label>
                        <div class="position-relative">
                            <el-autocomplete
                                v-model="new_Approver.Nama"
                                :fetch-suggestions="querySearchPindah"
                                placeholder="Pilih approval baru"
                                popper-class="my-autocomplete"
                                clearable
                                @select="selectKaryawanPindah"
                                class="w-100"
                            >
                                <!-- 🔹 Icon di sebelah kanan input -->
                                <template #suffix>
                                    <el-icon class="el-input__icon" @click="handleIconClick">
                                        <edit />
                                    </el-icon>
                                </template>

                                <!-- 🔹 Tampilan item suggestion -->
                                <template #default="{ item }">
                                    <div class="value text-gray-800 font-medium">
                                        {{ item.value }}
                                    </div>
                                    <span class="link text-gray-500 text-sm">{{ item.nama_divisi }}</span>
                                </template>
                            </el-autocomplete>

                            <div class="search-hint">Ketik nama karyawan untuk mencari</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn-secondary btn-modern" @click="closePindahModal">Batal</button>
                    <button
                        class="btn-primary btn-modern"
                        :class="{
                            'text-black': !new_Approver?.Kode_Karyawan || loadingSimpan,
                        }"
                        :disabled="!new_Approver?.Kode_Karyawan || loadingSimpan"
                        @click="ubahApproval"
                    >
                        <i
                            v-if="!loadingSimpan"
                            class="bi bi-check-lg d-flex justify-content-center align-items-center"
                        ></i>
                        <lord-icon
                            v-else
                            src="https://cdn.lordicon.com/ibckyoan.json"
                            trigger="loop"
                            delay="0"
                            colors="primary:#000000"
                            style="width: 25px; height: 25px"
                        />
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </el-dialog>

    <!-- modal pindah approval -->
    <!-- <el-dialog
        v-model="showpindahModal"
        title="n approval"
        width="1000px"
        center
        :lock-scroll="true"
        :close="closePindahModal"
    >
        <div class="modal-content-wrapper">
            <div class="transfer-approval-container">
                <div class="transfer-flow">
                    <div class="approver-section current-approver">
                        <label class="section-label">Approval Sebelumnya</label>
                        <div class="approver-card">
                            <div class="user-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div class="user-info">
                                <div class="user-name">
                                    {{ currentDetail?.namaUser }}
                                </div>
                                <div class="user-status">Current Approver</div>
                            </div>
                        </div>
                    </div>

                    <div class="transfer-arrow">
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </div>

                    <div class="approver-section new-approver">
                        <label class="section-label">Approval Baru</label>
                        <div class="position-relative">
                            <el-autocomplete
                                v-model="new_Approver.Nama"
                                :fetch-suggestions="querySearchPindah"
                                placeholder="Pilih approval baru"
                                popper-class="my-autocomplete"
                                clearable
                                @select="selectKaryawanPindah"
                                class="w-100"
                            >
                                <template #suffix>
                                    <el-icon
                                        class="el-input__icon"
                                        @click="handleIconClick"
                                    >
                                        <edit />
                                    </el-icon>
                                </template>

                                <template #default="{ item }">
                                    <div
                                        class="value text-gray-800 font-medium"
                                    >
                                        {{ item.value }}
                                    </div>
                                    <span class="link text-gray-500 text-sm">{{
                                        item.nama_divisi
                                    }}</span>
                                </template>
                            </el-autocomplete>

                            <div class="search-hint">
                                Ketik nama karyawan untuk mencari
                            </div>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button
                        class="btn-secondary btn-modern"
                        @click="closePindahModal"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-primary btn-modern"
                        :class="{
                            'text-black':
                                !new_Approver?.Kode_Karyawan || loadingSimpan,
                        }"
                        :disabled="
                            !new_Approver?.Kode_Karyawan || loadingSimpan
                        "
                        @click="ubahApproval"
                    >
                        <i
                            v-if="!loadingSimpan"
                            class="bi bi-check-lg d-flex justify-content-center align-items-center"
                        ></i>
                        <lord-icon
                            v-else
                            src="https://cdn.lordicon.com/ibckyoan.json"
                            trigger="loop"
                            delay="0"
                            colors="primary:#000000"
                            style="width: 25px; height: 25px"
                        />
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </el-dialog> -->

    <!-- Dialog show akun -->
    <el-dialog
        v-model="showUserModal"
        title="Detail Akun Baru"
        width="500px"
        center
        :lock-scroll="true"
        :close-on-click-modal="false"
        :show-close="false"
    >
        <div class="modal-content-wrapper">
            <div class="user-detail-container">
                <!-- User Info Card -->
                <div class="user-info-card">
                    <div class="card-header">
                        <i class="bi bi-person-check-fill d-flex justify-content-center align-items-center"></i>
                        <h3 class="text-white">Akun Berhasil Dibuat</h3>
                    </div>

                    <div class="user-details">
                        <div class="detail-row">
                            <span class="label">Nama</span>
                            <span class="value">{{ newUserData.nama }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Divisi</span>
                            <span class="value">{{ newUserData.divisi }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Username</span>
                            <span class="value">{{ newUserData.username }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Password</span>
                            <span class="value password-field">
                                {{ newUserData.password }}
                                <button class="copy-btn" @click="copyPassword">
                                    <i class="bi bi-clipboard d-flex justify-content-center align-items-center"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Button -->
                <div class="action-section">
                    <a :href="whatsappLink" target="_blank" class="whatsapp-btn">
                        <i class="bi bi-whatsapp d-flex justify-content-center align-items-center"></i>
                        Kirim via WhatsApp
                    </a>
                </div>

                <!-- Close Button -->
                <div class="modal-actions">
                    <button class="btn-primary btn-modern" @click="closeUserModal">Tutup</button>
                </div>
            </div>
        </div>
    </el-dialog>
</template>

<script>
import axios from 'axios';
import _, { truncate } from 'lodash';

import { Edit } from '@element-plus/icons-vue';
import { ElMessage, ElNotification, ElDialog, ElTag, ElAutocomplete } from 'element-plus';
import 'element-plus/dist/index.css';
export default {
    props: {
        namaKaryawan: String,
        // allKaryawan: Array,
        // karyawanTeam: Array,
        // ApprovalFlow: Array,
        // PageAccess: Array,
    },
    components: {
        // Dipakai sebagai <edit /> di slot #suffix; tanpa pendaftaran ini ikonnya
        // tidak pernah muncul dan Vue memperingati komponen yang tak terselesaikan.
        Edit,
        ElMessage,
        ElNotification,
        ElDialog,
        ElTag,
        ElAutocomplete,
    },
    data() {
        return {
            windowWidth: window.innerWidth,
            showUserModal: false,
            newUserData: {
                nama: '',
                divisi: '',
                username: '',
                password: '',
                hp: '',
            },
            haveTeamModalResult: [],
            buttonAlert: '',
            new_Approver: [],
            showpindahModal: false,
            showDropdownPindah: false,

            // details Modal

            allKaryawan: [],
            assignableKaryawan: [],
            permanentKaryawan: [],
            employeeVisibilityScope: 'ALL',
            karyawanTeam: [],
            ApprovalFlow: [],
            PageAccess: [],
            mengapproveData: [],

            showDetailModal: false,

            menuList: [],
            AssignActivePages: [],
            activePages: [],
            accessSearchQuery: '',
            showAlertModal: false,
            Pesan: null,
            fnc: null,
            // user
            AssignUsername: '',
            usernameError: '',
            usernameValid: true,
            isWhatsappUser: false,
            AssignPageAccess: [],

            // Approver Karyawan
            removedApprover: [],
            formFields: [],

            // Karyawan Team
            yangTersediaSearch: '',
            tergabungSearch: '',
            newMembers: [],
            removedMembers: [],
            assignSearchQuery: null,

            selectedAssignEmployees: null,

            showAssignModal: false,
            assignSubmit: null,
            assignStep: 1,
            loadingSimpan: false,
            searchQuery: '',
            debouncedSearch: null,
            dataRecord: [],
            totalPages: 0,
            currentPage: 0,
            lastPage: 0,
            viewMode: 'table',

            paginate: [],
            totalData: 0,
            currentDate: new Date(),
            nextLink: null,
            prevLink: null,
            dayNight: 'Siang',
            loadingData: false,
            tagTypes: ['', 'success', 'info', 'warning', 'danger'],

            /* Empat properti di bawah ini DIPAKAI template tetapi tidak pernah
               dideklarasikan. Menulis ke properti yang tak ada di data() tidak
               membuatnya reaktif, jadi kerusakannya diam: filter tanggal
               di-v-model ke sana dan tak pernah berlaku, dropdown assign tak
               pernah menutup lewat state, dan modal detail tak ikut berubah
               saat currentDetail diisi (17 pemakaian di <script>). */
            selectedDateFilter: null,
            isLoadingAssign: false,
            showAssignDropdown: false,
            currentDetail: null,
        };
    },
    computed: {
        whatsappLink() {
            const message = `Halo, akun Anda telah berhasil dibuat.\n\nDetail Akun:\nNama: ${this.newUserData.nama}\nDivisi: ${this.newUserData.divisi}\nUsername: ${this.newUserData.username}\nPassword: ${this.newUserData.password}\n\nSilakan login dan ubah password Anda untuk keamanan.`;
            const encodedMessage = encodeURIComponent(message);
            return `https://wa.me/${this.newUserData.hp}/?text=${encodedMessage}`;
        },
        filteredTeams() {
            if (!this.selectedItem?.teams) return [];
            return this.selectedItem.teams.filter((team) => team.toLowerCase().includes(this.teamSearch.toLowerCase()));
        },
        getApproval() {
            const Kode_Karyawan = this.selectedAssignEmployees.Kode_Karyawan;
            if (!Kode_Karyawan) return [];

            const approvalFlowData = this.ApprovalFlow.find((item) => item.Requester === Kode_Karyawan);

            if (!approvalFlowData || !approvalFlowData.Approver) return [];

            // 1. Buat lookup untuk data karyawan
            const karyawanLookup = this.allKaryawan.reduce((acc, curr) => {
                acc[curr.Kode_Karyawan] = curr;
                return acc;
            }, {});

            // 2. Urutkan berdasarkan order_flow dan gabungkan data
            return approvalFlowData.Approver.sort((a, b) => a.order_flow - b.order_flow)
                .map((approver) => ({
                    ...karyawanLookup[approver.Kode_Karyawan],
                    order_flow: approver.order_flow,
                }))
                .filter(Boolean);
        },

        alredyUsername() {
            if (this.AssignUsername == '') return false;
            let filtered = this.allKaryawan.find(
                (emp) => emp.Username?.toLowerCase() == this.AssignUsername.toLowerCase(),
            );
            return !!filtered; // true kalau ketemu, false kalau tidak
        },
        teamList() {
            // 1. cari nama leader
            const leaderName = this.selectedAssignEmployees.Kode_Karyawan;

            if (!leaderName) return [];

            let leaderObj = this.karyawanTeam.find((item) => {
                if (!item?.LeaderTeam || typeof item.LeaderTeam !== 'string') return false;

                const itemLeader = item.LeaderTeam.trim().toUpperCase();
                const searchLeader = leaderName.trim().toUpperCase();

                return (
                    itemLeader === searchLeader ||
                    itemLeader.includes(searchLeader) ||
                    searchLeader.includes(itemLeader)
                );
            });

            // 2. kalau nggak ketemu, tambahkan leader baru
            if (!leaderObj) {
                leaderObj = {
                    LeaderTeam: leaderName,
                    Team: [],
                };
                this.karyawanTeam.push(leaderObj);
            }

            // 3. kembalikan daftar anggota team
            return leaderObj.Team;
        },

        availableList() {
            return this.permanentKaryawan.filter((k) => {
                // Filter untuk menghapus karyawan yang sama dengan yang dipilih
                if (k.Kode_Karyawan === this.selectedAssignEmployees.Kode_Karyawan) return false;

                // Filter untuk menghapus karyawan yang sudah ada di teamList
                if (this.teamList.some((memberName) => memberName == k.Kode_Karyawan)) return false;

                // Filter berdasarkan pencarian
                if (this.yangTersediaSearch) {
                    const keyword = this.yangTersediaSearch.toLowerCase();
                    return (
                        (k.Nama && k.Nama.toLowerCase().includes(keyword)) ||
                        (k.Kode_Karyawan && k.Kode_Karyawan.toLowerCase().includes(keyword))
                    );
                }

                return true;
            });
        },

        haveTeam() {
            return this.allKaryawan.filter((k) => {
                // Jangan tampilkan karyawan yang sama dengan yang dipilih
                if (k.Kode_Karyawan === this.selectedAssignEmployees.Kode_Karyawan) return false;

                // Pastikan hanya yang ada di teamList
                if (!this.teamList.some((memberName) => memberName == k.Kode_Karyawan)) return false;

                // Kalau ada searchQuery, filter lagi
                if (this.tergabungSearch) {
                    const keyword = this.tergabungSearch.toLowerCase();
                    return (
                        (k.Nama && k.Nama.toLowerCase().includes(keyword)) ||
                        (k.Kode_Karyawan && k.Kode_Karyawan.toLowerCase().includes(keyword))
                    );
                }

                return true;
            });
        },

        filteredAssignEmployees() {
            let filtered = this.assignableKaryawan;

            if (this.assignSearchQuery) {
                filtered = filtered.filter((employee) =>
                    employee.Nama.toLowerCase().includes(this.assignSearchQuery.toLowerCase()),
                );
            }
            // console.log(filtered[0].Nama);
            return filtered;
        },
        suggestionAssign() {
            if (!this.assignSearchQuery) return [];
            return this.assignableKaryawan.filter((employee) =>
                employee.Nama.toLowerCase().includes(this.assignSearchQuery.toLowerCase()),
            );
        },
        accessSelectionCount() {
            return this.activePages.length;
        },
        selectedAccessMenus() {
            return this.menuList.filter((menu) => this.activePages.includes(menu.pageKey));
        },
        filteredMenuGroups() {
            const keyword = this.accessSearchQuery.trim().toLowerCase();
            const filteredMenus = this.menuList.filter((menu) => {
                if (!keyword) return true;

                return [menu.name, menu.categoryLabel, menu.pageKey]
                    .filter(Boolean)
                    .some((value) => value.toLowerCase().includes(keyword));
            });

            const grouped = filteredMenus.reduce((groups, menu) => {
                const key = menu.category || 'lainnya';

                if (!groups[key]) {
                    groups[key] = {
                        key,
                        label: menu.categoryLabel || 'Lainnya',
                        sortHeader: Number(menu.sortHeader || 999),
                        items: [],
                    };
                }

                groups[key].items.push(menu);
                return groups;
            }, {});

            return Object.values(grouped).sort((a, b) => (a.sortHeader || 999) - (b.sortHeader || 999));
        },
    },
    created() {
        this.debouncedSearch = _.debounce(this.getData, 500);
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        const currentHour = new Date().getHours();
        this.dayNight = currentHour >= 6 && currentHour < 18 ? 'Siang' : 'Malam';
        this.getData('/add-karyawan-team/getData');
        this.getAllData();
    },
    methods: {
        processHaveTeamModal() {
            // 1. Reset data dulu
            this.haveTeamModalResult = [];

            // 2. Validasi
            if (!this.currentDetail || !this.currentDetail.Kode_Karyawan || !this.allKaryawan) return;

            const leaderName = String(this.currentDetail.Kode_Karyawan).trim().toUpperCase();

            // 3. Cari Leader di karyawanTeam
            let leaderObj = this.karyawanTeam.find((item) => {
                if (!item?.LeaderTeam) return false;
                return String(item.LeaderTeam).trim().toUpperCase() === leaderName;
            });

            // Jika bukan leader atau tidak punya tim, stop
            if (!leaderObj || !leaderObj.Team) return;

            let members = leaderObj.Team;

            // 4. Normalisasi Data Team (Handle String/JSON/Array)
            let memberIds = [];

            if (Array.isArray(members)) {
                memberIds = members.map((m) => {
                    if (typeof m === 'object' && m !== null) {
                        return String(m.Kode_Karyawan || m.id || m).trim();
                    }
                    return String(m).trim();
                });
            } else if (typeof members === 'string') {
                try {
                    // Coba parse JSON
                    let parsed = JSON.parse(members);
                    if (Array.isArray(parsed)) {
                        memberIds = parsed.map((m) => String(m).trim());
                    }
                } catch (e) {
                    // Fallback jika string biasa dipisah koma/pipe
                    if (members.includes(',')) {
                        memberIds = members.split(',').map((m) => m.trim());
                    } else if (members.includes('|')) {
                        memberIds = members.split('|').map((m) => m.trim());
                    } else {
                        memberIds = [members.trim()];
                    }
                }
            }

            // 5. Filter All Karyawan berdasarkan ID yang ditemukan
            this.haveTeamModalResult = this.allKaryawan.filter((k) => {
                // Skip diri sendiri (Leader)
                if (String(k.Kode_Karyawan).trim() === leaderName) return false;

                // Cek apakah ID karyawan ada di list memberIds
                return memberIds.includes(String(k.Kode_Karyawan).trim());
            });
        },
        validateUsernameInput(value) {
            // Regex untuk validasi
            const regex = /^[a-zA-Z0-9_%!]*$/;

            if (!regex.test(value)) {
                this.usernameError = 'Hanya karakter a-z, A-Z, 0-9, _, %, ! yang diperbolehkan';
                this.usernameValid = false;
                return false;
            }

            // Validasi panjang (opsional)
            if (value.length > 0 && value.length < 3) {
                this.usernameError = 'Username minimal 3 karakter';
                this.usernameValid = false;
                return false;
            }

            if (value.length > 15) {
                this.usernameError = 'Username Max 15';
                this.usernameValid = false;
                return false;
            }

            this.usernameError = '';
            this.usernameValid = true;
            return true;
        },

        // Method untuk filter input
        filterUsername(event) {
            const value = event.target.value;
            this.validateUsernameInput(value);

            // Jika tidak valid, filter karakter
            if (!this.usernameValid) {
                // Hanya izinkan karakter yang valid
                let filtered = value.replace(/[^a-zA-Z0-9_%!]/g, '');
                this.AssignUsername = filtered;
            }
        },

        // Validasi final sebelum submit
        validateUsernameBeforeSubmit() {
            if (!this.isCreatedUsername()) {
                this.usernameError = '';
                this.usernameValid = true;
                return true;
            }

            const regex = /^[a-zA-Z0-9_%!]+$/;
            const username = this.AssignUsername.trim();

            if (!username) {
                this.usernameError = 'Username tidak boleh kosong';
                this.usernameValid = false;
                return false;
            }

            if (!regex.test(username)) {
                this.usernameError = 'Format username tidak valid';
                this.usernameValid = false;
                return false;
            }

            if (username.length > 0 && username.length < 3) {
                this.usernameError = 'Username minimal 3 karakter';
                this.usernameValid = false;
                return false;
            }

            if (username.length > 15) {
                this.usernameError = 'Username Max 15';
                this.usernameValid = false;
                return false;
            }

            if (this.alredyUsername) {
                this.usernameError = 'Username sudah ada!';
                this.usernameValid = false;
                return false;
            }

            this.AssignUsername = username;
            this.usernameError = '';
            this.usernameValid = true;
            return true;
        },

        showUserCreationModal(userData) {
            this.newUserData = { ...userData };
            this.showUserModal = true;
        },

        closeUserModal() {
            this.showUserModal = false;
            this.newUserData = {
                nama: '',
                divisi: '',
                username: '',
                password: '',
            };
        },

        copyPassword() {
            const password = this.newUserData.password;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard
                    .writeText(password)
                    .then(() => {
                        ElMessage({
                            message: 'Password berhasil disalin!',
                            type: 'success',
                        });
                    })
                    .catch((err) => console.error('Gagal menyalin password: ', err));
            } else {
                // Fallback lama
                const textArea = document.createElement('textarea');
                textArea.value = password;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    ElMessage({
                        message: 'Password berhasil disalin!',
                        type: 'success',
                    });
                } catch (err) {
                    console.error('Gagal menyalin password: ', err);
                }
                document.body.removeChild(textArea);
            }
        },

        handleClickPindah() {
            if (!this.currentDetail) return;

            this.showpindahModal = true;
        },
        async ubahApproval() {
            if (!this.currentDetail.Kode_Karyawan) return;

            this.loadingSimpan = true;

            try {
                const res = await axios.post('/add-karyawan-team/ubahApproval', {
                    oldApprover: this.currentDetail.Kode_Karyawan,
                    newApprover: this.new_Approver.Kode_Karyawan,
                });

                if (res.status == 200) {
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Berhasil Mengalihkan Approver!',
                        type: 'success',
                    });
                    this.closePindahModal();
                    this.getData('/add-karyawan-team/getData');
                    this.getAllData();
                }
            } catch (error) {
                console.log(error);
                ElMessage({
                    message: error.response?.data?.message || 'Terjadi error!',
                    type: 'error',
                });
            } finally {
                this.loadingSimpan = false;
            }
        },
        async getAllData() {
            this.loadingData = true;
            try {
                const res = await axios.get('/add-karyawan-team/getAllData');

                if (res.status == 200) {
                    this.allKaryawan = res.data.allKaryawan;
                    this.assignableKaryawan = Array.isArray(res.data.assignableKaryawan)
                        ? res.data.assignableKaryawan
                        : res.data.allKaryawan || [];
                    this.permanentKaryawan = Array.isArray(res.data.permanentKaryawan)
                        ? res.data.permanentKaryawan
                        : (res.data.allKaryawan || []).filter((k) =>
                              String(k?.Status_Kontrak || '')
                                  .trim()
                                  .toUpperCase()
                                  .includes('PKWTT'),
                          );
                    this.employeeVisibilityScope = String(res.data.employeeVisibilityScope || 'ALL').toUpperCase();
                    this.karyawanTeam = res.data.karyawanTeam;
                    this.ApprovalFlow = res.data.ApprovalFlow;
                    this.mengapproveData = res.data.mengapprove;
                    this.PageAccess = res.data.PageAccess;
                    this.menuList = this.normalizeAccessMenus(res.data.menuAccessList || []);
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingData = false;
            }
        },
        normalizeAccessMenus(rawMenus) {
            if (!Array.isArray(rawMenus)) {
                return [];
            }

            const normalizeIcon = (icon) => {
                const value = String(icon || '').trim();
                if (!value) return 'bi bi-grid';
                if (value.startsWith('bi bi-')) return value;
                if (value.startsWith('bi-')) return `bi ${value}`;
                return value;
            };

            const isGlobalMenu = (menu) =>
                String(menu?.Is_Global_Menu || '')
                    .trim()
                    .toUpperCase() === 'Y';

            const normalized = rawMenus
                .map((menu) => {
                    const pageKey = String(menu?.Jenis_page || '').trim();
                    const name = String(menu?.Nama_Menu || pageKey || '').trim();
                    const categoryLabel = String(menu?.Header_Label || 'Lainnya').trim() || 'Lainnya';
                    const headerCode = String(menu?.Header_Code || '').trim();
                    const sourceText = `${pageKey} ${name} ${categoryLabel} ${headerCode}`.toLowerCase();

                    // Sembunyikan menu yang tidak bisa di-delegate oleh user saat ini
                    // (SYSTEM_ONLY / RESTRICTED tanpa role yang sesuai)
                    const canDelegate = menu?.can_delegate !== false;

                    if (!pageKey || isGlobalMenu(menu) || sourceText.includes('administrator') || !canDelegate) {
                        return null;
                    }

                    return {
                        name,
                        pageKey,
                        icon: normalizeIcon(menu?.Icon_Menu) || this.getMenuIcon(pageKey, name, categoryLabel),
                        category: this.slugifyCategory(headerCode || categoryLabel),
                        categoryLabel,
                        sortHeader: Number(menu?.Sort_Header || 999),
                        sortMenu: menu?.Sort_Menu,
                        idMenu: Number(menu?.Id_Menu || 0),
                    };
                })
                .filter(Boolean);

            const seen = new Set();
            return normalized
                .filter((menu) => {
                    const key = menu.pageKey.toLowerCase();
                    if (seen.has(key)) {
                        return false;
                    }
                    seen.add(key);
                    return true;
                })
                .sort((a, b) => {
                    const headerDiff = (a.sortHeader || 999) - (b.sortHeader || 999);
                    if (headerDiff !== 0) return headerDiff;

                    const aHasSort = a.sortMenu !== null && a.sortMenu !== undefined && a.sortMenu !== '';
                    const bHasSort = b.sortMenu !== null && b.sortMenu !== undefined && b.sortMenu !== '';
                    if (aHasSort && bHasSort) {
                        const menuSortDiff = Number(a.sortMenu) - Number(b.sortMenu);
                        if (menuSortDiff !== 0) return menuSortDiff;
                    }

                    if (aHasSort !== bHasSort) return aHasSort ? -1 : 1;
                    return (a.idMenu || 0) - (b.idMenu || 0);
                });
        },
        slugifyCategory(value) {
            return (
                String(value || 'lainnya')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '') || 'lainnya'
            );
        },
        getMenuIcon(pageKey, menuName, categoryLabel) {
            const source = `${pageKey} ${menuName} ${categoryLabel}`.toLowerCase();

            if (source.includes('approval') || source.includes('approve')) return 'bi bi-check2-square';
            if (source.includes('shift')) return 'bi bi-arrow-left-right';
            if (source.includes('lembur') || source.includes('overtime')) return 'bi bi-clock';
            if (source.includes('cuti')) return 'bi bi-calendar-event';
            if (source.includes('izin')) return 'bi bi-person-walking';
            if (source.includes('absensi')) return 'bi bi-calendar-check';
            if (source.includes('lms') || source.includes('training')) return 'bi bi-journal-bookmark';
            if (source.includes('kpi')) return 'bi bi-graph-up-arrow';

            return 'bi bi-grid';
        },
        getTagType(index) {
            return this.tagTypes[index % this.tagTypes.length];
        },
        showDetail(item) {
            // Reset semua data sebelum menampilkan yang baru
            this.currentDetail = null;
            this.currentApprovers = [];
            this.currentTeamMemberships = [];
            this.currentMengapprove = [];
            this.haveTeamModalResult = [];

            // Set timeout kecil untuk memastikan reactivity bekerja
            this.$nextTick(() => {
                this.currentDetail = { ...item }; // Clone object untuk menghindari reference
                this.processApprovers();
                this.processTeamMemberships();
                this.processMengapprove();
                this.processHaveTeamModal();
                this.showDetailModal = true;
            });
        },

        closeDetailModal() {
            this.currentDetail = null;
            this.currentApprovers = [];
            this.currentTeamMemberships = [];
            this.currentMengapprove = [];
            this.showDetailModal = false;
        },
        closePindahModal() {
            this.new_Approver = {
                Nama: '',
                Kode_Karyawan: '',
                nama_divisi: '',
            };
            this.searchQuery = '';
            this.closeDetailModal();
            this.showpindahModal = false;
        },

        processApprovers() {
            if (!this.currentDetail?.Approvers) {
                this.currentApprovers = [];
                return;
            }

            const approversStr = this.currentDetail.Approvers;
            if (!approversStr || approversStr.trim() === '' || approversStr === 'TIDAK ADA') {
                this.currentApprovers = [];
            } else {
                this.currentApprovers = approversStr
                    .split('|')
                    .map((name) => name.trim())
                    .filter((name) => name);
            }
        },

        processTeamMemberships() {
            // console.log(this.currentDetail);
            if (!this.currentDetail) {
                this.currentTeamMemberships = [];
                return;
            }

            const karyawanNama = this.currentDetail.Kode_Karyawan || this.currentDetail['ID WEB'];
            if (!karyawanNama) {
                this.currentTeamMemberships = [];
                return;
            }

            const hasil = [];
            this.karyawanTeam.forEach((teamObj) => {
                if (teamObj.Team.includes(karyawanNama)) {
                    hasil.push(teamObj.LeaderTeam);
                }
            });

            this.currentTeamMemberships = hasil;
        },
        processMengapprove() {
            if (!this.currentDetail) {
                this.currentMengapprove = [];
                return;
            }

            const karyawanNama = this.currentDetail.Kode_Karyawan;
            if (!karyawanNama) {
                this.currentMengapprove = [];
                return;
            }

            // Cari data approver yang sesuai
            const hasil = this.mengapproveData.find((t) => t.Approver === karyawanNama);

            // console.log(hasil);

            if (hasil && hasil.Requester && hasil.Requester.length) {
                // Ambil hanya nama requester
                this.currentMengapprove = hasil.Requester.map((req) => req.Kode_Karyawan_Requester);
            } else {
                this.currentMengapprove = [];
            }
        },

        tergabungTeam(user) {
            const karyawanNama = user.Kode_Karyawan; // atau Kode_Karyawan kalau datanya pakai kode
            const hasil = [];

            this.karyawanTeam.forEach((teamObj) => {
                if (teamObj.Team.includes(karyawanNama)) {
                    hasil.push(teamObj.LeaderTeam);
                }
            });
            // console.log(hasil);
            return {
                total: hasil?.length || 0,
                data: hasil || [],
            };
        },
        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        hideAssignDropdown() {
            // kasih delay supaya @mousedown di <li> keburu eksekusi
            setTimeout(() => {
                this.showAssignDropdown = false;
            }, 200);
        },

        isMobile() {
            return this.windowWidth < 768;
        },
        closeAssignModal() {
            this.showAssignModal = false;
            this.selectedAssignEmployees = null;

            this.AssignActivePages = [];
            this.activePages = [];
            this.showAlertModal = false;
            this.Pesan = null;
            this.fnc = null;
            // user
            this.AssignUsername = '';
            this.usernameError = '';
            this.usernameValid = true;
            this.isWhatsappUser = false;
            this.AssignPageAccess = [];
            this.accessSearchQuery = '';

            // Approver Karyawan
            this.removedApprover = [];
            this.formFields = [];

            // Karyawan Team
            this.yangTersediaSearch = '';
            this.tergabungSearch = '';
            this.newMembers = [];
            this.removedMembers = [];
            this.assignSearchQuery = null;
            this.assignStep = 1;
        },
        isActivePages(pageKey) {
            const res = this.activePages.includes(String(pageKey).trim());
            // console.log("isActive check", pageKey, res, this.activePages);
            return res;
        },
        getAccessUser() {
            const UserID_AbsenK = this.selectedAssignEmployees?.UserID_Web || null;

            if (!UserID_AbsenK) return null;

            const userPageAccess = this.PageAccess.find((item) => item.UserID_Web == UserID_AbsenK) || null;
            // console.log(userPageAccess);
            return userPageAccess;
        },
        loadAccess() {
            const access = this.getAccessUser();
            this.activePages = Array.isArray(access?.Jenis_page) ? [...access.Jenis_page] : [];
        },
        toggleAccess(pageKey) {
            const idx = this.activePages.indexOf(pageKey);
            if (idx > -1) {
                // hapus
                this.activePages.splice(idx, 1);
            } else {
                // tambah
                this.activePages.push(pageKey);
            }
            // trik supaya Vue sadar array berubah
            this.activePages = [...this.activePages];
        },
        selectAllVisibleAccess() {
            const visiblePageKeys = this.filteredMenuGroups.flatMap((group) => group.items.map((menu) => menu.pageKey));

            this.activePages = [...new Set([...this.activePages, ...visiblePageKeys])];
        },
        clearAllAccess() {
            this.activePages = [];
        },
        parsePageAccess(page) {
            if (!page) return '';

            const normalizedPage = String(page).trim().toLowerCase();
            const menuMatch = this.menuList.find(
                (menu) => String(menu.pageKey).trim().toLowerCase() === normalizedPage,
            );

            if (menuMatch) {
                return {
                    title: menuMatch.name,
                    icon: menuMatch.icon,
                };
            }

            if (normalizedPage === 'overtimemanagement') {
                return {
                    title: 'Lembur',
                    icon: 'bi bi-clock',
                };
            }

            return {
                title: String(page),
                icon: 'bi bi-grid',
            };
        },
        approversInfoFromString(approversStr) {
            if (!approversStr || approversStr.trim() === '' || approversStr === 'TIDAK ADA') {
                return {
                    total: 0,
                    list: 'TIDAK ADA',
                };
            }

            const approversArray = approversStr.split('|').map((name) => name.trim());

            return {
                total: approversArray.length,
                list: approversArray.join(' | '),
            };
        },
        normalizeOrderFlow() {
            // Urutkan berdasarkan order_flow yang ada
            this.formFields.sort((a, b) => a.order_flow - b.order_flow);

            // Perbarui order_flow secara berurutan
            this.formFields.forEach((field, idx) => {
                field.order_flow = idx + 1;
            });
        },
        getSimpanfnc() {
            if (!this.assignSubmit) {
                this.closeAlertModal;

                ElMessage({
                    message: 'Form belum lengkap!.',
                    type: 'warning',
                });
            }
        },
        isSameApprover() {},
        showAlertModalFC(pesan, jenis, button) {
            this.Pesan = pesan;
            // this.fnc = fnc;
            if (this.assignSubmit == 'team') {
                this.fnc = () => this.submitTeam();
                this.buttonAlert = button;
                this.showAlertModal = true;
            } else if (this.assignSubmit == 'websiteUser') {
                if (jenis == 'kirimUlangUser') {
                    this.fnc = () => this.kirimUlangUser();
                    this.buttonAlert = button;
                    this.showAlertModal = true;
                } else {
                    if (!this.validateUsernameBeforeSubmit()) {
                        ElMessage({
                            message: this.usernameError || 'Username belum sesuai!.',
                            type: 'warning',
                        });
                        this.showAlertModal = false;
                    } else {
                        this.fnc = () => this.submitUser();
                        this.buttonAlert = button;
                        this.showAlertModal = true;
                    }
                }
            } else if (this.assignSubmit == 'approver') {
                this.fnc = () => this.submitApprover();
                this.buttonAlert = button;

                this.showAlertModal = true;
            }
        },
        hasExistingUsername() {
            const existingUsername = this.selectedAssignEmployees?.Username;
            if (existingUsername === null || existingUsername === undefined) return false;

            const normalizedUsername = String(existingUsername).trim();
            return normalizedUsername !== '' && normalizedUsername !== '-';
        },
        isCreatedUsername() {
            if (!this.hasExistingUsername()) {
                return this.AssignUsername;
            }

            return null;
        },
        async submitTeam() {
            this.closeAlertModal();

            try {
                this.loadingSimpan = true;
                const response = await axios.post('/add-karyawan-team/simpanTeam', {
                    Kode_Karyawan: this.selectedAssignEmployees.Kode_Karyawan,
                    newMembers: this.newMembers,
                    removedMembers: this.removedMembers,
                });
                if (response.status == 200) {
                    this.getData('/add-karyawan-team/getData');
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Data berhasil disimpan!',
                        type: 'success',
                    });
                }
            } catch (error) {
                let errorMessage = 'Terjadi error!.';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.getData('/add-karyawan-team/getData');
                this.getAllData();
                this.closeAssignModal();

                this.loadingSimpan = false;
            }
        },
        async submitUser() {
            try {
                this.closeAlertModal();
                this.loadingSimpan = true;
                const response = await axios.post('/add-karyawan-team/simpanUser', {
                    Kode_Karyawan: this.selectedAssignEmployees.Kode_Karyawan,
                    Username: this.isCreatedUsername(),
                    Pages: this.activePages,
                });
                if (response.status == 200) {
                    this.getData('/add-karyawan-team/getData');

                    // console.log(response.dataAkun.nama);
                    let popup = response.data.isNewUser;
                    if (popup == true) {
                        this.showUserCreationModal({
                            nama: response.data.dataAkun.nama,
                            divisi: response.data.dataAkun.divisi,
                            username: response.data.dataAkun.username,
                            password: response.data.dataAkun.password,
                            hp: response.data.dataAkun.HP,
                        });
                    }

                    ElNotification({
                        title: 'Berhasil',
                        message: 'Data berhasil disimpan!',
                        type: 'success',
                    });
                }
            } catch (error) {
                let errorMessage = 'Terjadi error!.';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.getData('/add-karyawan-team/getData');
                this.getAllData();
                this.closeAssignModal();
                this.loadingSimpan = false;
            }
        },
        async kirimUlangUser() {
            try {
                this.closeAlertModal();
                this.loadingSimpan = true;
                const response = await axios.post('/add-karyawan-team/kirimUlangUser', {
                    Kode_Karyawan: this.selectedAssignEmployees.Kode_Karyawan,
                    Username: this.isCreatedUsername(),
                });
                if (response.status == 200) {
                    this.getData('/add-karyawan-team/getData');

                    let popup = response.data.isNewUser;
                    if (popup == true) {
                        this.showUserCreationModal({
                            nama: response.data.dataAkun.nama,
                            divisi: response.data.dataAkun.divisi,
                            username: response.data.dataAkun.username,
                            password: response.data.dataAkun.password,
                            hp: response.data.dataAkun.HP,
                        });
                    }

                    ElNotification({
                        title: 'Berhasil',
                        message: 'Data berhasil disimpan!',
                        type: 'success',
                    });
                }
            } catch (error) {
                let errorMessage = 'Terjadi error!.';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.getData('/add-karyawan-team/getData');
                this.getAllData();
                this.closeAssignModal();
                this.loadingSimpan = false;
            }
        },
        async submitApprover() {
            this.closeAlertModal();

            try {
                this.loadingSimpan = true;
                const response = await axios.post('/add-karyawan-team/simpanApproval', {
                    Requester: this.selectedAssignEmployees,
                    Approver: this.formFields || [],
                });
                if (response.status == 200) {
                    this.getData('/add-karyawan-team/getData');
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Data berhasil disimpan!',
                        type: 'success',
                    });
                }
            } catch (error) {
                console.log(error);

                ElMessage({
                    message: error.response?.data?.message || 'Terjadi error!',
                    type: 'error',
                });
            } finally {
                this.getData('/add-karyawan-team/getData');
                this.getAllData();
                this.closeAssignModal();
                this.loadingSimpan = false;
            }
        },
        closeAlertModal() {
            this.Pesan = null;
            this.fnc = null;
            this.showAlertModal = false;
        },
        loadExistingApprovers() {
            const existingApprovers = this.getApproval; // Dari fungsi getApproval() yang sudah dimodifikasi
            this.formFields = existingApprovers.map((approver) => ({
                Nama: approver.Nama,
                Kode_Karyawan: approver.Kode_Karyawan,
                order_flow: approver.order_flow, // Pastikan ini ada dari getApproval()
                showDropDown: false,
            }));

            // Jika tidak ada data, inisialisasi dengan order_flow = 1
            if (this.formFields.length === 0) {
                this.formFields.push({
                    Nama: '',
                    Kode_Karyawan: '',
                    order_flow: 1,
                    showDropDown: false,
                });
            }
        },
        suggestionFilter(query, currentIndex) {
            const excludedNames = [
                this.selectedAssignEmployees.Nama,
                ...this.formFields
                    .filter((_, idx) => idx !== currentIndex)
                    .map((field) => field.Nama.trim())
                    .filter((name) => name !== ''),
            ];

            return this.allKaryawan.filter((k) => {
                const namaLower = k.Nama.toLowerCase();
                const queryLower = query.toLowerCase();

                return namaLower.includes(queryLower) && !excludedNames.includes(k.Nama);
            });
        },
        querySearchPindah(queryString, cb) {
            const results = queryString
                ? this.allKaryawan.filter((k) => k.Nama.toLowerCase().includes(queryString.toLowerCase()))
                : this.allKaryawan;

            // Buang data yang sama dengan currentDetail.Nama
            const filteredResults = results.filter((k) => k.Nama !== this.currentDetail.namaUser);

            cb(
                filteredResults.map((k) => ({
                    value: k.Nama,
                    Kode_Karyawan: k.Kode_Karyawan,
                    nama_divisi: k.nama_divisi,
                })),
            );
        },

        selectKaryawanPindah(selected) {
            this.new_Approver = {
                Nama: selected.value,
                Kode_Karyawan: selected.Kode_Karyawan,
                nama_divisi: selected.nama_divisi,
            };
        },

        handleIconClick() {
            console.log('Edit clicked', this.new_Approver);
        },

        // Update method selectKaryawan untuk menerima currentIndex
        selectKaryawan(currentIndex, karyawan) {
            this.formFields[currentIndex].Nama = karyawan.Nama;
            this.formFields[currentIndex].Kode_Karyawan = karyawan.Kode_Karyawan;
            this.formFields[currentIndex].showDropdown = false;
        },

        hideDropdown(idx) {
            setTimeout(() => {
                this.formFields[idx].showDropdown = false;
            }, 200);
        },
        addField() {
            const nextOrder =
                this.formFields.length > 0 ? Math.max(...this.formFields.map((f) => f.order_flow)) + 1 : 1;

            this.formFields.push({
                Nama: '',
                Kode_Karyawan: '',
                order_flow: nextOrder,
                showDropDown: false,
            });
        },
        removeField(index) {
            this.formFields.splice(index, 1);
            this.normalizeOrderFlow(); // Normalisasi ulang urutan
        },

        addToTeam(employee) {
            if (!this.teamList.includes(employee.Kode_Karyawan)) {
                // Simpan nama karyawan di teamList
                this.teamList.push(employee.Kode_Karyawan);

                // Simpan di array khusus "baru ditambahkan"
                this.newMembers.push(employee);
            }
        },
        removeFromTeam(employee) {
            const index = this.teamList.indexOf(employee.Kode_Karyawan);
            if (index !== -1) {
                this.teamList.splice(index, 1);

                // Simpan di array khusus "baru dihapus"
                this.removedMembers.push(employee);
            }
        },
        prevAssignStep() {
            if (this.assignStep == 2) {
                if (this.assignStep == 2 && this.assignSubmit == 'websiteUser') {
                    this.AssignUsername = '';
                    this.activePages = [];
                    this.assignStep--;
                }
                if (this.assignStep == 2 && this.assignSubmit == 'team') {
                    this.newMembers = [];
                    this.removedMembers = [];
                    this.assignStep--;
                }
                if (this.assignStep == 2 && this.assignSubmit == 'approver') {
                    this.formFields = [];
                    this.assignStep--;
                }
            } else {
                this.assignStep--;
            }
        },
        nextAssignStep() {
            if (this.canProceedToNextStep()) {
                if (this.assignStep == 1 && this.assignSubmit == 'approver') {
                    this.loadExistingApprovers();
                    this.assignStep++;
                } else if (this.assignStep == 1 && this.assignSubmit == 'websiteUser') {
                    this.usernameError = '';
                    this.usernameValid = true;
                    if (this.selectedAssignEmployees.Username) {
                        this.AssignUsername = this.selectedAssignEmployees.Username;
                    }
                    this.loadAccess();
                    this.assignStep++;
                } else {
                    this.assignStep++;
                }
            } else {
                ElMessage({
                    message: 'Tolong lengkapi dulu form nya!.',
                    type: 'warning',
                });
            }
        },
        canProceedToNextStep() {
            if (this.assignStep === 1) {
                return this.assignSubmit !== null && this.selectedAssignEmployees !== null;
            }
            if (this.assignStep === 2) {
                if (this.assignStep == 2 && this.assignSubmit == 'websiteUser') {
                    return this.AssignUsername !== null && this.PageAccess !== [];
                }

                return this.AssignUsername !== '';
            }
            if (this.assignStep === 3) {
                if (this.assignIzinType === 'sakit' && this.AssignDateRange[0] != this.AssignDateRange[1]) {
                    return this.AssignFile !== null;
                }
                return true;
            }
            return true;
        },
        getInitials(name) {
            // console.log(name);
            return name
                .split(' ')
                .map((word) => word[0])
                .join('')
                .substring(0, 2)
                .toUpperCase();
        },

        async getData(url = null) {
            this.loadingData = true;
            try {
                const params = new URLSearchParams();
                if (this.searchQuery) {
                    params.append('search', this.searchQuery);
                }

                const endpoint = url || `/add-karyawan-team/getData?${params.toString()}`;

                const res = await axios.get(endpoint);

                // console.log(res.data.data.data);
                this.dataRecord = res.data.data.data;
                this.totalPages = res.data.data.last_page;
                this.lastPage = res.data.data.last_page;
                this.nextLink = res.data.data.next_page_url;
                this.prevLink = res.data.data.prev_page_url;
                this.currentPage = res.data.data.current_page;
                this.paginate = res.data.data.links;
                this.totalData = res.data.data.total;
            } catch (error) {
                console.error(error);
            } finally {
                this.loadingData = false; // Pasti dijalankan setelah request selesai
            }
        },
        formatDateString(dateString) {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                const options = {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                };
                let dating = new Intl.DateTimeFormat('id-ID', options).format(date);
                // console.log(dating);
                return dating;
            } catch (error) {
                console.error('Error formatting date:', error);
                return dateString; // Fallback
            }
        },
        capitalize(value) {
            if (!value) return '';
            const words = value.split(' ');
            const capitalizedWords = words.map((word) => {
                if (!word) return '';
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            });
            return capitalizedWords.join(' ');
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
    --danger-light: var(--hcis-legacy-bootstrap-danger); /* Merah muda, lebih terang untuk latar belakang atau efek disabled */
    --gray-light: var(--hcis-legacy-bootstrap-gray);
    --el-slider-main-bg-color: var(--hcis-indigo-500);
    --el-input-border-radius: 12px;
}

.form-control.border-danger {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.bi {
    display: inline-table;
}

.text-sm {
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
}

/* Modal User */

/* User Detail Modal Styles */
.user-detail-container {
    padding: 0 10px;
}

.user-info-card {
    background: var(--surface);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-header {
    background: var(--gradient-primary);
    color: white;
    padding: 20px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.card-header i {
    font-size: 2rem;
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.user-details {
    padding: 24px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.label {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.9rem;
}

.value {
    color: var(--text);
    font-weight: 600;
    text-align: left;
}

.password-field {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Courier New', monospace;
    background: var(--surface-soft);
    padding: 4px 8px;
    border-radius: 6px;
}

.copy-btn {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.copy-btn:hover {
    background: var(--primary-dark);
}

.copy-btn i {
    font-size: 0.8rem;
}

/* WhatsApp Button */
.action-section {
    margin-bottom: 24px;
}

.whatsapp-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 20px;
    background: #25d366;
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
}

.whatsapp-btn:hover {
    background: #128c7e;
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    color: white;
}

/* Modal Actions */
.modal-actions {
    display: flex;
    justify-content: center;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

/* Responsive Design */
@media (max-width: 640px) {
    .user-detail-container {
        padding: 0 5px;
    }

    .card-header {
        padding: 16px;
    }

    .card-header h3 {
        font-size: 1.1rem;
    }

    .user-details {
        padding: 16px;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .value {
        text-align: left;
    }

    .password-field {
        width: 100%;
        justify-content: space-between;
    }
}

/* Animation for modal */
.el-dialog__wrapper {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Akhir Modal User */

/* Tambahkan ke stylesheet Anda */
.modal-content-wrapper {
    padding: 0 1rem;
}

.transfer-approval-container {
    max-width: 800px;
    margin: 0 auto;
}

.transfer-flow {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 2rem;
    margin-bottom: 2rem;
}

.approver-section {
    flex: 1;
}

.section-label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: #4a5568;
}

.approver-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 1rem;
    background: var(--hcis-slate-50);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 80px;
}

.user-avatar {
    font-size: 1.5rem;
    color: #718096;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: #2d3748;
}

.user-status {
    font-size: 0.875rem;
    color: #718096;
}

.transfer-arrow {
    margin-top: 2.5rem;
    font-size: 1.5rem;
    color: #4299e1;
}

.approver-search .el-input__inner {
    height: 48px;
    border-radius: 8px;
}

.search-icon {
    color: #a0aec0;
}

.search-hint {
    font-size: 0.75rem;
    color: #718096;
    margin-top: 0.25rem;
    margin-left: 0.5rem;
}

.suggestion-item {
    padding: 0.5rem;
    border-bottom: 1px solid #f1f1f1;
}

.suggestion-name {
    font-weight: 500;
    color: #2d3748;
}

.suggestion-department {
    font-size: 0.875rem;
    color: #718096;
}

.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--hcis-slate-200);
}

.btn-modern {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: #4299e1;
    border: none;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #3182ce;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
}

.btn-secondary {
    background: white;
    border: 1px solid var(--hcis-slate-200);
    color: #4a5568;
}

.btn-secondary:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .transfer-flow {
        flex-direction: column;
        gap: 1rem;
    }
    .approver-section {
        width: 100%;
    }

    .transfer-arrow {
        margin-top: 0;
        transform: rotate(90deg);
        align-self: center;
    }

    .action-buttons {
        flex-direction: column;
    }
}

/* detail style */
.detail-modal-content {
    padding: 20px;
}

.el-autocomplete-suggestion li {
    line-height: 25px;
}

.form-group {
    position: relative;
}

.el-input__wrapper {
    height: 50px;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.el-input__wrapper.is-focus {
    box-shadow: 0 0 0 2px var(--primary) inset;
}

.el-range-editor.is-active,
.el-range-editor.is-active:hover {
    box-shadow: 0 0 0 2px var(--primary) inset;
}

.el-input__wrapper.is-active,
.el-input__wrapper.is-active:hover {
    box-shadow: 0 0 0 2px var(--primary) inset !important;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-weight: bold;
    color: #606266;
}

.detail-value {
    color: #303133;
}

.highlight {
    font-weight: bold;
    color: #409eff;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.search-input {
    width: 200px;
}

.scrollable-container {
    max-height: 150px;
    overflow-y: auto;
    padding: 8px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.team-tag,
.approver-tag,
.membership-tag {
    margin: 2px;
}
/* akhir detail */
.form-mark {
    background: var(--surface);
    width: 100%;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 0 !important;
}

.selection-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}
.btn-link {
    background: none;
    border: none;
    color: var(--primary);
    padding: 0.5rem;
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}
.employee-department {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}
.employee-avatar {
    min-width: 40px;
    max-width: 40px;
    min-height: 35px;

    max-height: 40px;
    border-radius: 30%;
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
}

.employee-avatar.large {
    width: 60px;
    height: 60px;
    font-size: 1.1rem;
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

.employee-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
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
    justify-content: start;
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
    min-width: 15rem;
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
.text-danger {
    color: var(--danger) !important;
}
.reason-cell {
    max-width: 200px;
    white-space: nowrap;
    /* color: var(--danger); */
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
/* .card-header {
    padding: 1rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
} */
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

.closeFilter {
    transition: all 0.3s ease;
    background: var(--surface);
}

.closeFilter:hover {
    color: white;
    background: var(--danger);
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
/* button */
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
/* akhir button */

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
    .employee-avatar {
        width: 50px;
    }
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
    .employee-details {
        gap: 0.15rem;
    }
    .employee-avatar {
        width: 35px;
        height: 35px;
        font-size: 0.75rem;
    }
    .employee-avatar.large {
        width: 50px;
        height: 50px;
        font-size: 1rem;
    }
    .employee-selection-item {
        padding: 0.75rem;
    }

    .employee-avatar.large {
        width: 50px;
        height: 50px;
        font-size: 1rem;
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
    .access-panel-header,
    .access-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .access-toolbar-actions {
        width: 100%;
    }
    .access-action-btn {
        flex: 1;
    }
    .access-option-grid {
        grid-template-columns: 1fr;
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
    .selected-access-list {
        gap: 0.4rem;
    }
    .selected-access-chip {
        width: 100%;
        justify-content: center;
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
}

.selected-karyawan {
    font-size: 1.1rem;
    margin-bottom: 25px;
    border-left: 4px solid #0d6efd;
}

.approver-field {
    position: relative;
}

.form-control {
    padding-right: 35px;
}

.dropdown-suggestion {
    position: absolute;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.dropdown-item {
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #f8f9fa;
}

.btn-remove {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
}

.btn-remove:hover {
    color: #dc3545;
}

.btn-add {
    width: 100%;
    padding: 8px;
    background: #f8f9fa;
    border: 1px dashed #adb5bd;
    border-radius: 4px;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add:hover {
    background: var(--hcis-legacy-bootstrap-gray);
    border-color: #6c757d;
}

.form-actions {
    border-top: 1px solid #eee;
    padding-top: 20px;
}
/* Employee Selection */
.assign-search {
    margin-bottom: 1.5rem;
}

.closeRounded {
    transition: all 0.3s ease;
}

.closeRounded:hover {
    background: var(--danger);
    color: white !important;
}

.employee-selection-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.employee-selection-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
}

.employee-selection-item:last-child {
    border-bottom: none;
}

.employee-selection-item:hover {
    background: var(--surface-soft);
}

.employee-selection-item.selected {
    background: rgba(99, 102, 241, 0.1);
    border-color: var(--primary);
}

.employee-checkbox {
    width: 24px;
    height: 24px;
    border: 2px solid var(--border);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-weight: bold;
    transition: all 0.3s ease;
}

.employee-checkbox.rounded-4:hover {
    background: var(--danger);
    color: white;
    border-color: var(--red);
}

.employee-selection-item.selected .employee-checkbox {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.employee-department {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.selection-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.selected-count {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: var(--primary);
    font-weight: 500;
    font-size: 0.9rem;
}
/* info bar */
.info-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 12px !important;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
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
/* .card-header {
    padding: 1rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
} */
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
    max-width: 1300px;
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

.modal-body.approval {
    max-height: none;
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
.access-panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.access-panel-subtitle {
    color: #6b7280;
    font-size: 0.88rem;
}

.access-summary-pill {
    flex-shrink: 0;
    padding: 0.55rem 0.9rem;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--hcis-indigo-50), var(--hcis-sky-100));
    color: #1e3a8a;
    font-weight: 600;
    font-size: 0.85rem;
}

.access-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.access-search {
    flex: 1;
    min-width: 0;
}

.access-toolbar-actions {
    display: flex;
    gap: 0.5rem;
}

.access-action-btn {
    border-radius: 999px;
    padding-inline: 0.9rem;
    border: 1px solid #d7def3;
    background: #fff;
}

.selected-access-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-bottom: 1rem;
}

.selected-access-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    background: var(--hcis-emerald-50);
    color: #166534;
    font-size: 0.84rem;
    font-weight: 600;
}

.access-groups {
    display: grid;
    gap: 1rem;
}

.access-group-card {
    border: 1px solid #d7def3;
    border-radius: 16px;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.72);
}

.access-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.9rem;
}

.access-group-header h6 {
    font-weight: 700;
    color: var(--hcis-slate-700);
}

.access-group-header small {
    color: var(--hcis-slate-500);
}

.access-option-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
}

.access-option {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 0.95rem;
    border: 1px solid #d7def3;
    border-radius: 14px;
    background: #fff;
    cursor: pointer;
    transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        background-color 0.18s ease;
}

.access-option:hover {
    transform: translateY(-1px);
    border-color: #93c5fd;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.08);
}

.access-option.selected {
    background: linear-gradient(135deg, var(--hcis-emerald-50), #dbeafe);
    border-color: #60a5fa;
    box-shadow: 0 12px 26px rgba(59, 130, 246, 0.12);
}

.access-option-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.access-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(59, 130, 246, 0.12);
    color: var(--hcis-blue-600);
    flex-shrink: 0;
}

.access-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
}

.access-name {
    font-weight: 600;
    color: var(--hcis-slate-700);
    text-align: left;
}

.access-meta {
    color: var(--hcis-slate-500);
    text-align: left;
}

.access-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1px solid var(--hcis-slate-300);
    color: transparent;
    flex-shrink: 0;
}

.access-option.selected .access-check {
    background: var(--hcis-blue-600);
    border-color: var(--hcis-blue-600);
    color: #fff;
}

.access-empty-state {
    padding: 1rem;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.6);
    color: var(--hcis-slate-500);
    text-align: center;
}

.form-group {
    margin-bottom: 1.5rem; /* Standard margin for form groups */
    border: 1px solid var(--border); /* Added for visual separation */
    background: rgba(99, 102, 241, 0.05); /* Soft background */
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto; /* Ensure content within is scrollable if needed */
    /* max-height: 18rem; Cap height to prevent excessive modal height */
}

.form-group.AssignRingkasan {
    max-height: 25rem;
}
.form-group.AssignApproval {
    min-height: 10rem;
    overflow: visible;
}

.form-group.team {
    max-height: 45rem;
}

.form-group.user {
    max-height: 30rem;
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
    padding: 2px;
    height: 50px;
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
