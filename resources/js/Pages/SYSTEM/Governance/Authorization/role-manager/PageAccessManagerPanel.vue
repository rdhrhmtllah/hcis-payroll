<template>
    <div class="page-access-manager-panel">
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-menu-button-wide me-2"></i>Page Access Manager</h6>
            <div class="toolbar-actions">
                <button class="btn-tab" :class="{ active: activeStep === 'master' }" @click="activeStep = 'master'">
                    Step 1: Master
                </button>
                <button class="btn-tab" :class="{ active: activeStep === 'sort' }" @click="activeStep = 'sort'">
                    Step 2: Sort Angka
                </button>
                <button class="btn-tab" :class="{ active: activeStep === 'header' }" @click="activeStep = 'header'">
                    Step 3: Edit Header
                </button>
                <button class="btn-add" @click="openCreate"><i class="bi bi-plus-lg me-1"></i>Tambah Menu/Page</button>
            </div>
        </div>

        <div v-if="activeStep === 'master'" class="step-wrapper">
            <div class="filter-bar mb-3">
                <input
                    v-model="search"
                    class="form-control form-control-sm"
                    placeholder="Cari jenis page, nama menu, header..."
                    @keyup.enter="fetchMenus"
                />
                <select v-model="filterGlobal" class="form-select form-select-sm" @change="fetchMenus">
                    <option value="">Semua Global Flag</option>
                    <option value="Y">Global Menu (Y)</option>
                    <option value="T">Bukan Global (T)</option>
                </select>
                <select v-model="filterHeader" class="form-select form-select-sm" @change="fetchMenus">
                    <option value="">Semua Header</option>
                    <option v-for="h in headers" :key="h.Header_Code" :value="h.Header_Code">
                        {{ h.Header_Label || h.Header_Code }}
                    </option>
                </select>
                <button class="btn-refresh" @click="fetchMenus" :disabled="loading">
                    <i class="bi bi-arrow-clockwise" :class="{ spin: loading }"></i>
                </button>
            </div>

            <div v-if="loading" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <p class="text-muted small mt-2">Memuat menu...</p>
            </div>

            <div v-else class="table-responsive table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jenis Page</th>
                            <th>Menu</th>
                            <th>Header</th>
                            <th>Global</th>
                            <th>Sort</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in menus" :key="item.Id_Menu">
                            <td>
                                <code>{{ item.Jenis_page }}</code>
                                <div class="small text-muted">{{ item.Url_Menu || '-' }}</div>
                            </td>
                            <td>
                                <div class="menu-cell">
                                    <i :class="normalizeIcon(item.Icon_Menu, 'bi bi-grid-1x2')"></i>
                                    <span>{{ item.Nama_Menu }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="menu-cell">
                                    <i :class="normalizeIcon(item.Header_Icon, 'bi bi-folder2-open')"></i>
                                    <span>{{ item.Header_Label || item.Header_Code }}</span>
                                </div>
                                <small class="text-muted">{{ item.Header_Code }}</small>
                            </td>
                            <td>
                                <span :class="item.Is_Global_Menu === 'Y' ? 'badge bg-success' : 'badge bg-secondary'">
                                    {{ item.Is_Global_Menu }}
                                </span>
                            </td>
                            <td>
                                <small>H: {{ item.Sort_Header || '-' }} | M: {{ item.Sort_Menu || '-' }}</small>
                            </td>
                            <td class="text-end">
                                <button class="btn-icon-action" @click="openEdit(item)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="menus.length === 0">
                            <td colspan="6" class="text-center text-muted py-4">Belum ada data menu.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="activeStep === 'header'" class="step-wrapper">
            <div class="sort-note">
                <i class="bi bi-info-circle me-1"></i>
                Edit header di Step 3 akan mengubah semua menu/page yang menggunakan header tersebut.
            </div>

            <div class="filter-bar mt-2 mb-3">
                <input
                    v-model="headerStepSearch"
                    class="form-control form-control-sm"
                    placeholder="Cari header code atau label..."
                />
                <select v-model="headerStepFilter" class="form-select form-select-sm">
                    <option value="">Semua Header</option>
                    <option v-for="h in headers" :key="h.Header_Code" :value="h.Header_Code">
                        {{ h.Header_Label || h.Header_Code }}
                    </option>
                </select>
            </div>

            <div class="table-responsive table-wrap mt-2">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Code</th>
                            <th>Sort</th>
                            <th>Menu Count</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="h in filteredHeadersForStep3" :key="h.Header_Code">
                            <td>
                                <div class="menu-cell">
                                    <i :class="normalizeIcon(h.Header_Icon, 'bi bi-folder2-open')"></i>
                                    <span>{{ h.Header_Label || '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <code>{{ h.Header_Code }}</code>
                            </td>
                            <td>{{ h.Sort_Header || '-' }}</td>
                            <td>{{ h.menu_count || '-' }}</td>
                            <td class="text-end">
                                <button class="btn-icon-action" @click="openHeaderEdit(h)" title="Edit Header">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredHeadersForStep3.length === 0">
                            <td colspan="5" class="text-center text-muted py-4">Belum ada header.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="activeStep === 'sort'" class="step-wrapper">
            <div class="sort-note">
                <i class="bi bi-info-circle me-1"></i>
                Urutan harus unik per header. Gunakan angka berbeda untuk mencegah konflik.
            </div>

            <div class="filter-bar mt-2 mb-3">
                <input
                    v-model="sortStepSearch"
                    class="form-control form-control-sm"
                    placeholder="Cari menu atau jenis page..."
                />
                <select v-model="sortStepFilterHeader" class="form-select form-select-sm">
                    <option value="">Semua Header</option>
                    <option v-for="h in headers" :key="h.Header_Code" :value="h.Header_Code">
                        {{ h.Header_Label || h.Header_Code }}
                    </option>
                </select>
            </div>

            <div class="table-responsive table-wrap mt-2">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Menu</th>
                            <th width="140">Sort Header</th>
                            <th width="140">Sort Menu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in filteredSortableRows"
                            :key="row.Id_Menu"
                            :class="{ 'row-conflict': isSortConflictRow(row) }"
                        >
                            <td class="">
                                <div class="menu-cell">
                                    <i :class="normalizeIcon(row.Header_Icon, 'bi bi-folder2-open')"></i>
                                    <span>{{ row.Header_Label || row.Header_Code }}</span>
                                </div>
                                <small class="text-muted">{{ row.Header_Code }}</small>
                            </td>
                            <td>
                                <div class="menu-cell">
                                    <i :class="normalizeIcon(row.Icon_Menu, 'bi bi-grid-1x2')"></i>
                                    <span>{{ row.Nama_Menu }}</span>
                                </div>
                            </td>
                            <td>
                                <input
                                    v-model.number="row.Sort_Header"
                                    type="number"
                                    min="1"
                                    class="form-control form-control-sm"
                                />
                            </td>
                            <td>
                                <input
                                    v-model.number="row.Sort_Menu"
                                    type="number"
                                    min="1"
                                    class="form-control form-control-sm"
                                />
                            </td>
                        </tr>
                        <tr v-if="filteredSortableRows.length === 0">
                            <td colspan="4" class="text-center text-muted py-4">Tidak ada data sesuai filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="step-actions mt-3">
                <button class="btn-back" @click="resetSortDraft" :disabled="savingSort">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Draft
                </button>
                <button class="btn-next" @click="saveSort" :disabled="savingSort || filteredSortableRows.length === 0">
                    <span v-if="savingSort"><i class="bi bi-arrow-repeat spin"></i> Menyimpan...</span>
                    <span v-else><i class="bi bi-check2 me-1"></i>Simpan Urutan</span>
                </button>
            </div>

            <div v-if="sortConflicts.length > 0" class="conflict-box mt-3">
                <div class="conflict-title"><i class="bi bi-exclamation-triangle me-1"></i>Konflik Urutan</div>
                <div v-for="(c, idx) in sortConflicts" :key="idx" class="conflict-item">
                    {{ formatConflict(c) }}
                </div>
            </div>
        </div>

        <Modal
            :show="showForm"
            :title="editItem ? 'Edit Menu/Page Access' : 'Tambah Menu/Page Access'"
            size="large"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            :confirmText="editItem ? 'Simpan' : 'Buat'"
            :loading="saving"
            @close="closeForm"
            @confirm="saveItem"
        >
            <template #body>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label-sm">Jenis Page</label>
                        <input
                            v-model="form.Jenis_page"
                            class="form-control form-control-sm"
                            placeholder="contoh: dashboardStaff"
                        />
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">Kode Perusahaan</label>
                        <input v-model="form.Kode_Perusahaan" class="form-control form-control-sm" placeholder="001" />
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">Nama Menu</label>
                        <input v-model="form.Nama_Menu" class="form-control form-control-sm" placeholder="Nama menu" />
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">URL Menu</label>
                        <input
                            v-model="form.Url_Menu"
                            class="form-control form-control-sm"
                            placeholder="/dashboard-my-performance"
                        />
                    </div>

                    <div class="form-group icon-group">
                        <label class="form-label-sm">Icon Menu</label>
                        <div class="icon-input-wrap">
                            <button class="btn-icon-pick" @click="openIconPicker('Icon_Menu')" type="button">
                                <i :class="normalizeIcon(form.Icon_Menu, 'bi bi-grid-1x2')"></i>
                            </button>
                            <input
                                v-model="form.Icon_Menu"
                                class="form-control form-control-sm"
                                placeholder="bi bi-grid-1x2 / bi-grid-1x2"
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Global Menu</label>
                        <select v-model="form.Is_Global_Menu" class="form-select form-select-sm">
                            <option value="T">T - Bukan Global</option>
                            <option value="Y">Y - Global</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label-sm">Header Mode</label>
                        <div class="header-mode">
                            <label>
                                <input type="radio" v-model="useExistingHeader" :value="true" />
                                Gunakan Header Existing
                            </label>
                            <label>
                                <input type="radio" v-model="useExistingHeader" :value="false" />
                                Buat/Ubah Header Manual
                            </label>
                        </div>
                    </div>

                    <div class="form-group full-width" v-if="useExistingHeader">
                        <label class="form-label-sm">Pilih Header</label>
                        <select
                            v-model="selectedHeaderCode"
                            class="form-select form-select-sm"
                            @change="applySelectedHeader"
                        >
                            <option value="">Pilih header...</option>
                            <option v-for="h in headers" :key="h.Header_Code" :value="h.Header_Code">
                                {{ h.Header_Label || h.Header_Code }} ({{ h.Header_Code }})
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Header Label</label>
                        <input
                            v-model="form.Header_Label"
                            :disabled="headerFieldsDisabled"
                            class="form-control form-control-sm"
                            placeholder="Dashboard"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Header Code</label>
                        <input
                            v-model="form.Header_Code"
                            :disabled="headerFieldsDisabled"
                            class="form-control form-control-sm"
                            placeholder="dashboard"
                        />
                    </div>

                    <div class="form-group icon-group">
                        <label class="form-label-sm">Header Icon</label>
                        <div class="icon-input-wrap">
                            <button
                                class="btn-icon-pick"
                                @click="openIconPicker('Header_Icon')"
                                type="button"
                                :disabled="headerFieldsDisabled"
                            >
                                <i :class="normalizeIcon(form.Header_Icon, 'bi bi-folder2-open')"></i>
                            </button>
                            <input
                                v-model="form.Header_Icon"
                                :disabled="headerFieldsDisabled"
                                class="form-control form-control-sm"
                                placeholder="bi bi-folder2-open"
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Sort Header</label>
                        <input
                            v-model.number="form.Sort_Header"
                            :disabled="headerFieldsDisabled"
                            type="number"
                            min="1"
                            class="form-control form-control-sm"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Sort Menu</label>
                        <input
                            v-model.number="form.Sort_Menu"
                            type="number"
                            min="1"
                            class="form-control form-control-sm"
                        />
                        <small class="text-muted">Kosongkan untuk auto paling bawah.</small>
                    </div>
                </div>
            </template>
        </Modal>

        <Modal
            :show="showHeaderModal"
            title="Edit Header (Mass Update)"
            size="large"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            confirmText="Simpan Header"
            :loading="savingHeader"
            @close="showHeaderModal = false"
            @confirm="saveHeaderEdit"
        >
            <template #body>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label-sm">Header Code Lama</label>
                        <input :value="headerEdit.originalCode" class="form-control form-control-sm" disabled />
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">Header Code Baru</label>
                        <input v-model="headerEdit.Header_Code" class="form-control form-control-sm" />
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">Header Label</label>
                        <input v-model="headerEdit.Header_Label" class="form-control form-control-sm" />
                    </div>
                    <div class="form-group icon-group">
                        <label class="form-label-sm">Header Icon</label>
                        <div class="icon-input-wrap">
                            <button class="btn-icon-pick" @click="openHeaderIconPicker" type="button">
                                <i :class="normalizeIcon(headerEdit.Header_Icon, 'bi bi-folder2-open')"></i>
                            </button>
                            <input v-model="headerEdit.Header_Icon" class="form-control form-control-sm" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">Sort Header</label>
                        <input
                            v-model.number="headerEdit.Sort_Header"
                            type="number"
                            min="1"
                            class="form-control form-control-sm"
                        />
                    </div>
                </div>
            </template>
        </Modal>

        <Modal
            :show="showIconPicker"
            title="Pilih Icon Bootstrap"
            size="large"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            confirmText="Pakai Icon"
            @close="showIconPicker = false"
            @confirm="applyPickedIcon"
        >
            <template #body>
                <div class="icon-picker-body">
                    <input
                        v-model="iconSearch"
                        class="form-control form-control-sm mb-2"
                        placeholder="Cari icon... contoh: house, people, shield"
                    />
                    <div class="icon-grid">
                        <button
                            v-for="icon in filteredIcons"
                            :key="icon"
                            type="button"
                            class="icon-item"
                            :class="{ active: pickedIcon === icon }"
                            @click="pickedIcon = icon"
                        >
                            <i :class="icon"></i>
                            <small>{{ icon }}</small>
                        </button>
                    </div>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { ElMessage, ElNotification } from 'element-plus';
import Modal from '@/components/legacy/modalV2-noir.vue';

const BOOTSTRAP_ICONS = [
    'bi bi-house-door',
    'bi bi-grid-1x2',
    'bi bi-grid-3x3-gap',
    'bi bi-speedometer2',
    'bi bi-people',
    'bi bi-person',
    'bi bi-person-badge',
    'bi bi-person-check',
    'bi bi-person-lines-fill',
    'bi bi-collection',
    'bi bi-link-45deg',
    'bi bi-toggles',
    'bi bi-shield-lock',
    'bi bi-shield-check',
    'bi bi-key',
    'bi bi-lock',
    'bi bi-unlock',
    'bi bi-gear',
    'bi bi-gear-wide-connected',
    'bi bi-sliders',
    'bi bi-tools',
    'bi bi-wrench',
    'bi bi-building',
    'bi bi-diagram-3',
    'bi bi-bar-chart',
    'bi bi-pie-chart',
    'bi bi-graph-up-arrow',
    'bi bi-clipboard-data',
    'bi bi-kanban',
    'bi bi-journal-text',
    'bi bi-file-earmark-text',
    'bi bi-folder2-open',
    'bi bi-folder',
    'bi bi-archive',
    'bi bi-bookmark',
    'bi bi-bookmark-star',
    'bi bi-book',
    'bi bi-award',
    'bi bi-trophy',
    'bi bi-star',
    'bi bi-bell',
    'bi bi-megaphone',
    'bi bi-chat-left-text',
    'bi bi-envelope',
    'bi bi-send',
    'bi bi-telephone',
    'bi bi-calendar3',
    'bi bi-clock',
    'bi bi-alarm',
    'bi bi-check2-square',
    'bi bi-check-circle',
    'bi bi-exclamation-triangle',
    'bi bi-info-circle',
    'bi bi-x-circle',
    'bi bi-question-circle',
    'bi bi-search',
    'bi bi-funnel',
    'bi bi-filter-square',
    'bi bi-database',
    'bi bi-database-gear',
    'bi bi-hdd',
    'bi bi-server',
    'bi bi-cloud',
    'bi bi-cloud-upload',
    'bi bi-cloud-download',
    'bi bi-eye',
    'bi bi-eye-slash',
    'bi bi-pencil',
    'bi bi-pencil-square',
    'bi bi-trash',
    'bi bi-plus-circle',
    'bi bi-dash-circle',
    'bi bi-arrow-repeat',
    'bi bi-arrow-up-down',
    'bi bi-chevron-up',
    'bi bi-chevron-down',
    'bi bi-chevron-left',
    'bi bi-chevron-right',
    'bi bi-list',
    'bi bi-list-ul',
    'bi bi-menu-button-wide',
    'bi bi-layout-text-window',
    'bi bi-window',
    'bi bi-columns-gap',
    'bi bi-card-list',
    'bi bi-ui-checks-grid',
    'bi bi-app-indicator',
    'bi bi-lightning',
    'bi bi-activity',
    'bi bi-cpu',
    'bi bi-command',
    'bi bi-code-slash',
    'bi bi-patch-check',
    'bi bi-safe2',
    'bi bi-signpost-2',
    'bi bi-map',
    'bi bi-globe2',
    'bi bi-box-seam',
    'bi bi-briefcase',
    'bi bi-wallet2',
];

export default {
    name: 'PageAccessManagerPanel',
    components: {
        Modal,
    },
    data() {
        return {
            activeStep: 'master',
            loading: false,
            saving: false,
            savingSort: false,
            showForm: false,
            showIconPicker: false,
            showHeaderModal: false,
            savingHeader: false,
            iconTargetField: null,
            iconSearch: '',
            pickedIcon: '',
            editItem: null,
            menus: [],
            headers: [],
            search: '',
            filterGlobal: '',
            filterHeader: '',
            sortStepSearch: '',
            sortStepFilterHeader: '',
            headerStepSearch: '',
            headerStepFilter: '',
            useExistingHeader: true,
            selectedHeaderCode: '',
            sortableRows: [],
            sortConflicts: [],
            headerEdit: {
                originalCode: '',
                Header_Code: '',
                Header_Label: '',
                Header_Icon: 'bi bi-folder2-open',
                Sort_Header: null,
            },
            form: {
                Jenis_page: '',
                Kode_Perusahaan: '001',
                Nama_Menu: '',
                Icon_Menu: 'bi bi-grid-1x2',
                Url_Menu: '',
                Header_Code: '',
                Header_Label: '',
                Header_Icon: 'bi bi-folder2-open',
                Sort_Header: null,
                Is_Global_Menu: 'T',
                Sort_Menu: null,
            },
            allIcons: BOOTSTRAP_ICONS,
        };
    },
    computed: {
        filteredIcons() {
            const s = this.iconSearch.toLowerCase().trim();
            if (!s) return this.allIcons;
            return this.allIcons.filter((icon) => icon.toLowerCase().includes(s));
        },
        filteredSortableRows() {
            const search = this.sortStepSearch.toLowerCase().trim();

            return this.sortableRows.filter((row) => {
                const matchHeader = !this.sortStepFilterHeader || row.Header_Code === this.sortStepFilterHeader;
                if (!matchHeader) return false;
                if (!search) return true;

                return [
                    row.Nama_Menu,
                    row.Jenis_page,
                    row.Header_Label,
                    row.Header_Code,
                    row.Url_Menu,
                ].some((value) => String(value || '').toLowerCase().includes(search));
            });
        },
        filteredHeadersForStep3() {
            const search = this.headerStepSearch.toLowerCase().trim();

            return this.headers.filter((header) => {
                const matchHeader = !this.headerStepFilter || header.Header_Code === this.headerStepFilter;
                if (!matchHeader) return false;
                if (!search) return true;

                return [header.Header_Label, header.Header_Code].some((value) =>
                    String(value || '').toLowerCase().includes(search),
                );
            });
        },
        headerFieldsDisabled() {
            return this.useExistingHeader && !!this.selectedHeaderCode;
        },
    },
    methods: {
        createDefaultForm() {
            return {
                Jenis_page: '',
                Kode_Perusahaan: '001',
                Nama_Menu: '',
                Icon_Menu: 'bi bi-grid-1x2',
                Url_Menu: '',
                Header_Code: '',
                Header_Label: '',
                Header_Icon: 'bi bi-folder2-open',
                Sort_Header: null,
                Is_Global_Menu: 'T',
                Sort_Menu: null,
            };
        },
        clearSortConflicts() {
            this.sortConflicts = [];
        },
        closeForm() {
            this.showForm = false;
            this.editItem = null;
            this.selectedHeaderCode = '';
            this.useExistingHeader = true;
            this.form = this.createDefaultForm();
            this.clearSortConflicts();
        },
        normalizeIcon(icon, fallback) {
            const value = String(icon || '').trim();
            if (!value) return fallback;
            if (value.startsWith('bi bi-')) return value;
            if (value.startsWith('bi-')) return `bi ${value}`;
            if (value.startsWith('bi ')) return value;
            return `bi bi-${value.replace(/^\-+/, '')}`;
        },
        openCreate() {
            this.editItem = null;
            this.useExistingHeader = true;
            this.selectedHeaderCode = '';
            this.form = this.createDefaultForm();
            this.clearSortConflicts();
            this.showForm = true;
        },
        openEdit(item) {
            this.editItem = item;
            this.useExistingHeader = true;
            this.selectedHeaderCode = item.Header_Code || '';
            this.clearSortConflicts();
            this.form = {
                Jenis_page: item.Jenis_page || '',
                Kode_Perusahaan: item.Kode_Perusahaan || '001',
                Nama_Menu: item.Nama_Menu || '',
                Icon_Menu: this.normalizeIcon(item.Icon_Menu, 'bi bi-grid-1x2'),
                Url_Menu: item.Url_Menu || '',
                Header_Code: item.Header_Code || '',
                Header_Label: item.Header_Label || '',
                Header_Icon: this.normalizeIcon(item.Header_Icon, 'bi bi-folder2-open'),
                Sort_Header: item.Sort_Header || null,
                Is_Global_Menu: item.Is_Global_Menu || 'T',
                Sort_Menu: item.Sort_Menu || null,
            };
            this.showForm = true;
        },
        openHeaderEdit(header) {
            this.headerEdit = {
                originalCode: header.Header_Code || '',
                Header_Code: header.Header_Code || '',
                Header_Label: header.Header_Label || '',
                Header_Icon: this.normalizeIcon(header.Header_Icon, 'bi bi-folder2-open'),
                Sort_Header: header.Sort_Header || null,
            };
            this.showHeaderModal = true;
        },
        openHeaderIconPicker() {
            this.iconTargetField = '__header_modal_icon';
            this.iconSearch = '';
            this.pickedIcon = this.normalizeIcon(this.headerEdit.Header_Icon, 'bi bi-folder2-open');
            this.showIconPicker = true;
        },
        applySelectedHeader() {
            const found = this.headers.find((h) => h.Header_Code === this.selectedHeaderCode);
            if (!found) return;
            this.form.Header_Code = found.Header_Code || '';
            this.form.Header_Label = found.Header_Label || '';
            this.form.Header_Icon = this.normalizeIcon(found.Header_Icon, 'bi bi-folder2-open');
            this.form.Sort_Header = found.Sort_Header || null;
        },
        async fetchHeaders() {
            try {
                const res = await fetch('/api/v1/system-auth/page-access/headers');
                const data = await res.json();
                this.headers = data.data || [];
            } catch (e) {
                console.error(e);
            }
        },
        async fetchMenus() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search.trim()) params.append('search', this.search.trim());
                if (this.filterGlobal) params.append('is_global', this.filterGlobal);
                if (this.filterHeader) params.append('header_code', this.filterHeader);

                const res = await fetch(`/api/v1/system-auth/page-access/menus?${params.toString()}`);
                const data = await res.json();
                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal memuat menu.');
                    this.menus = [];
                    this.sortableRows = [];
                    return;
                }

                this.menus = data.data || [];
                this.sortableRows = (this.menus || []).map((m) => ({ ...m }));
                this.headers = Array.isArray(data.headers) ? data.headers : this.headers;
            } catch (e) {
                console.error(e);
                this.menus = [];
                this.sortableRows = [];
                ElNotification({ title: 'Gagal', message: 'Gagal memuat menu page access.', type: 'error' });
            } finally {
                this.loading = false;
            }
        },
        buildPayload() {
            const payload = {
                Jenis_page: this.form.Jenis_page,
                Kode_Perusahaan: this.form.Kode_Perusahaan,
                Nama_Menu: this.form.Nama_Menu,
                Icon_Menu: this.form.Icon_Menu,
                Url_Menu: this.form.Url_Menu,
                Header_Code: this.form.Header_Code,
                Header_Label: this.form.Header_Label,
                Header_Icon: this.form.Header_Icon,
                Sort_Header: this.form.Sort_Header,
                Is_Global_Menu: this.form.Is_Global_Menu,
                Sort_Menu: this.form.Sort_Menu,
            };

            if (this.useExistingHeader && this.selectedHeaderCode) {
                const h = this.headers.find((x) => x.Header_Code === this.selectedHeaderCode);
                if (h) {
                    payload.Header_Code = h.Header_Code;
                    payload.Header_Label = h.Header_Label || payload.Header_Label;
                    payload.Header_Icon = h.Header_Icon || payload.Header_Icon;
                    payload.Sort_Header = h.Sort_Header || payload.Sort_Header;
                }
            }

            return payload;
        },
        async saveItem() {
            this.saving = true;
            try {
                const isEdit = !!this.editItem;
                const endpoint = isEdit
                    ? `/api/v1/system-auth/page-access/menus/${this.editItem.Id_Menu}`
                    : '/api/v1/system-auth/page-access/menus';

                const res = await fetch(endpoint, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(this.buildPayload()),
                });
                const data = await res.json();
                if (!res.ok) {
                    const msg = data.message || 'Gagal menyimpan menu.';
                    if (Array.isArray(data.conflicts) && data.conflicts.length) {
                        this.sortConflicts = data.conflicts;
                    } else {
                        this.clearSortConflicts();
                    }
                    ElMessage.error(msg);
                    return;
                }

                this.closeForm();
                ElNotification({ title: 'Berhasil', message: data.message || 'Data tersimpan.', type: 'success' });
                await this.fetchMenus();
                await this.fetchHeaders();
            } catch (e) {
                console.error(e);
                ElNotification({ title: 'Gagal', message: 'Gagal menyimpan menu.', type: 'error' });
            } finally {
                this.saving = false;
            }
        },
        async saveHeaderEdit() {
            this.savingHeader = true;
            try {
                const oldCode = this.headerEdit.originalCode;
                const res = await fetch(`/api/v1/system-auth/page-access/headers/${encodeURIComponent(oldCode)}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        Header_Code: this.headerEdit.Header_Code,
                        Header_Label: this.headerEdit.Header_Label,
                        Header_Icon: this.headerEdit.Header_Icon,
                        Sort_Header: this.headerEdit.Sort_Header,
                    }),
                });

                const data = await res.json();
                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal menyimpan header.');
                    return;
                }

                this.showHeaderModal = false;
                ElNotification({
                    title: 'Berhasil',
                    message: data.message || 'Header berhasil diperbarui.',
                    type: 'success',
                });

                await this.fetchHeaders();
                await this.fetchMenus();
            } catch (e) {
                console.error(e);
                ElNotification({ title: 'Gagal', message: 'Gagal menyimpan header.', type: 'error' });
            } finally {
                this.savingHeader = false;
            }
        },
        async saveSort() {
            this.savingSort = true;
            this.sortConflicts = [];
            const rows = this.sortableRows.map((row) => ({
                Id_Menu: row.Id_Menu,
                Header_Code: row.Header_Code,
                Header_Label: row.Header_Label,
                Header_Icon: row.Header_Icon,
                Sort_Header: row.Sort_Header,
                Sort_Menu: row.Sort_Menu,
            }));

            try {
                const validateRes = await fetch('/api/v1/system-auth/page-access/sort/validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ rows }),
                });
                const validateData = await validateRes.json();

                if (!validateRes.ok || !validateData.valid) {
                    this.sortConflicts = validateData.conflicts || [];
                    ElMessage.error(validateData.message || 'Ada konflik sort.');
                    return;
                }

                const applyRes = await fetch('/api/v1/system-auth/page-access/sort/apply', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ rows }),
                });
                const applyData = await applyRes.json();
                if (!applyRes.ok) {
                    this.sortConflicts = applyData.conflicts || [];
                    ElMessage.error(applyData.message || 'Gagal simpan sort.');
                    return;
                }

                ElNotification({ title: 'Berhasil', message: applyData.message || 'Sort tersimpan.', type: 'success' });
                await this.fetchMenus();
            } catch (e) {
                console.error(e);
                ElNotification({ title: 'Gagal', message: 'Gagal menyimpan sort.', type: 'error' });
            } finally {
                this.savingSort = false;
            }
        },
        resetSortDraft() {
            this.sortableRows = (this.menus || []).map((m) => ({ ...m }));
            this.sortConflicts = [];
        },
        formatConflict(conflict) {
            if (conflict.type === 'menu_sort_conflict') {
                return `Sort Menu ${conflict.sort_menu} pada header ${conflict.header_code} bentrok dengan menu ${conflict.conflict_menu_name || conflict.conflict_menu_id}.`;
            }
            if (conflict.type === 'header_sort_conflict') {
                return `Sort Header ${conflict.sort_header} pada header ${conflict.header_code} bentrok dengan menu ${conflict.conflict_menu_name || conflict.conflict_menu_id}.`;
            }
            if (conflict.type === 'menu_sort_duplicate_payload') {
                return `Draft memiliki Sort Menu duplikat ${conflict.sort_menu} pada header ${conflict.header_code}.`;
            }
            if (conflict.type === 'header_sort_duplicate_payload') {
                return `Draft memiliki Sort Header duplikat ${conflict.sort_header} pada header ${conflict.header_code}.`;
            }
            return JSON.stringify(conflict);
        },
        isSortConflictRow(row) {
            return this.sortConflicts.some((conflict) => {
                if (Array.isArray(conflict.menu_ids)) {
                    return conflict.menu_ids.includes(row.Id_Menu);
                }
                return Number(conflict.conflict_menu_id) === Number(row.Id_Menu);
            });
        },
        openIconPicker(field) {
            this.iconTargetField = field;
            this.iconSearch = '';
            this.pickedIcon = this.normalizeIcon(this.form[field], 'bi bi-grid-1x2');
            this.showIconPicker = true;
        },
        applyPickedIcon() {
            if (!this.iconTargetField || !this.pickedIcon) {
                this.showIconPicker = false;
                return;
            }
            if (this.iconTargetField === '__header_modal_icon') {
                this.headerEdit.Header_Icon = this.pickedIcon;
                this.showIconPicker = false;
                return;
            }
            this.form[this.iconTargetField] = this.pickedIcon;
            this.showIconPicker = false;
        },
    },
    async mounted() {
        await this.fetchHeaders();
        await this.fetchMenus();
    },
};
</script>

<style scoped>
.panel-toolbar {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.btn-refresh {
    border: 1px solid var(--hcis-slate-300);
    background: #fff;
    border-radius: 8px;
    padding: 0.38rem 0.65rem;
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
}

.panel-subtitle {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}
.toolbar-actions {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}
.btn-tab {
    border: 1px solid var(--hcis-slate-300);
    background: #fff;
    border-radius: 8px;
    padding: 0.38rem 0.65rem;
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
}
.btn-tab.active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-700);
}
.btn-add {
    border: none;
    border-radius: 8px;
    padding: 0.42rem 0.75rem;
    background: linear-gradient(90deg, var(--hcis-indigo-600), var(--hcis-indigo-500));
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
}
.filter-bar {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 180px 220px 40px;
    gap: 0.45rem;
}
.table-wrap {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: #fff;
}
.menu-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-icon-action {
    border: 1px solid #dbeafe;
    background: #eff6ff;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: #1d4ed8;
}
.sort-note {
    border-radius: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 0.5rem 0.65rem;
    font-size: 0.75rem;
    color: #1e3a8a;
}
.row-conflict {
    background: var(--hcis-red-50);
}
.step-actions {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}
.btn-back,
.btn-next {
    display: inline-flex;
    align-items: center;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.btn-back {
    border: 1px solid var(--hcis-slate-300);
    background: #fff;
    color: var(--hcis-slate-700);
}
.btn-next {
    border: none;
    background: linear-gradient(90deg, var(--hcis-sky-500), var(--hcis-sky-600));
    color: #fff;
}
.conflict-box {
    border-radius: 10px;
    border: 1px solid var(--hcis-red-200);
    background: var(--hcis-red-50);
    padding: 0.6rem;
}
.conflict-title {
    font-weight: 700;
    font-size: 0.78rem;
    color: var(--hcis-red-800);
    margin-bottom: 0.35rem;
}
.conflict-item {
    font-size: 0.72rem;
    color: var(--hcis-red-900);
    margin-bottom: 0.2rem;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.form-group.full-width {
    grid-column: 1 / -1;
}
.form-label-sm {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
}
.header-mode {
    display: flex;
    gap: 1rem;
    font-size: 0.78rem;
    color: var(--hcis-slate-700);
}
.icon-input-wrap {
    display: grid;
    grid-template-columns: 42px 1fr;
    gap: 0.4rem;
}
.btn-icon-pick {
    border: 1px solid var(--hcis-slate-300);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-700);
}
.icon-picker-body {
    max-height: 60vh;
    overflow: hidden;
}
.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.45rem;
    max-height: 48vh;
    overflow-y: auto;
    padding-right: 0.15rem;
}
.icon-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    padding: 0.45rem;
    color: var(--hcis-slate-700);
}
.icon-item i {
    font-size: 1.05rem;
}
.icon-item small {
    font-size: 0.62rem;
    color: var(--hcis-slate-500);
    text-align: center;
    line-height: 1.2;
}
.icon-item.active {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}
@media (max-width: 900px) {
    .filter-bar {
        grid-template-columns: 1fr;
    }
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
