<template>
    <div class="access-distribution-panel">
        <div v-if="!canDistribute" class="alert alert-warning py-2 px-3 mb-3">
            Anda tidak memiliki group kelolaan untuk distribusi akses.
        </div>

        <!-- Step Indicator -->
        <div class="steps-bar mb-4">
            <div
                v-for="(s, i) in steps"
                :key="i"
                class="step"
                :class="{ active: currentStep === i, completed: currentStep > i }"
            >
                <div class="step-icon">{{ currentStep > i ? '✓' : i + 1 }}</div>
                <div class="step-label">{{ s }}</div>
            </div>
        </div>

        <!-- STEP 1: Targeting -->
        <div v-if="currentStep === 0" class="step-content">
            <h6 class="step-title"><i class="bi bi-crosshair me-2"></i>Pilih Target Distribusi</h6>

            <div class="mode-selector mb-3">
                <label class="mode-option" :class="{ selected: mode === 'individual' }" @click="mode = 'individual'">
                    <i class="bi bi-person"></i>
                    <span>Individu</span>
                    <small>Pilih user satu per satu</small>
                </label>
                <label
                    class="mode-option"
                    :class="{ selected: mode === 'bulk' }"
                    @click="
                        mode = 'bulk';
                        fetchBulkTargets(1);
                    "
                >
                    <i class="bi bi-people-fill"></i>
                    <span>Bulk Filter</span>
                    <small>Filter berdasarkan divisi / level / jabatan</small>
                </label>
            </div>

            <!-- Individual -->
            <div v-if="mode === 'individual'" class="filter-section">
                <label class="form-label-sm">Cari & Pilih User</label>
                <input
                    v-model="userSearch"
                    class="form-control form-control-sm mb-2"
                    placeholder="Ketik nama atau kode karyawan..."
                    @input="searchUsers"
                />
                <div v-if="userSearchLoading" class="search-loading-hint">
                    <i class="bi bi-arrow-repeat spin"></i>
                    <span>Mencari user...</span>
                </div>
                <div class="user-search-results" v-if="userSearchResults.length > 0">
                    <div
                        v-for="u in userSearchResults"
                        :key="u.id"
                        class="user-result-item"
                        @click="addUser(u)"
                        :class="{ disabled: selectedUserIds.includes(u.id) }"
                    >
                        <span class="user-result-name">{{ u.nama }}</span>
                        <span class="user-result-code">{{ u.kode }}</span>
                    </div>
                </div>
            </div>

            <!-- Bulk -->
            <div v-if="mode === 'bulk'" class="filter-section">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label-sm">Divisi</label>
                        <el-select
                            v-model="filterDivisi"
                            class="w-100"
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            placeholder="Pilih divisi"
                        >
                            <el-option
                                v-for="d in filters.divisi"
                                :key="d.ID_Divisi"
                                :value="d.ID_Divisi"
                                :label="d.nama_divisi"
                            />
                        </el-select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-sm">Level</label>
                        <el-select
                            v-model="filterLevel"
                            class="w-100"
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            placeholder="Pilih level"
                        >
                            <el-option
                                v-for="l in filters.level"
                                :key="l.ID_Level"
                                :value="l.ID_Level"
                                :label="l.nama_level"
                            />
                        </el-select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-sm">Jabatan</label>
                        <el-select
                            v-model="filterJabatan"
                            class="w-100"
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            placeholder="Pilih jabatan"
                        >
                            <el-option
                                v-for="j in filters.jabatan"
                                :key="j.ID_Jabatan"
                                :value="j.ID_Jabatan"
                                :label="j.nama_jabatan"
                            />
                        </el-select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-sm">Cari Nama / Kode</label>
                        <input
                            v-model="bulkSearch"
                            class="form-control form-control-sm"
                            placeholder="Opsional"
                            @keyup.enter="fetchBulkTargets(1)"
                        />
                    </div>
                </div>

                <div class="bulk-actions mt-2">
                    <button class="btn-back btn-compact" @click="fetchBulkTargets(1)" :disabled="bulkLoading">
                        <i class="bi bi-funnel me-1"></i> Terapkan Filter
                    </button>
                    <button class="btn-back btn-compact" @click="saveCurrentPreset" :disabled="bulkLoading">
                        <i class="bi bi-bookmark-plus me-1"></i> Simpan Preset
                    </button>
                    <el-select
                        v-model="activePresetKey"
                        class="preset-select"
                        placeholder="Pilih preset"
                        clearable
                        :disabled="bulkPresets.length === 0 || bulkLoading"
                    >
                        <el-option
                            v-for="preset in bulkPresets"
                            :key="preset.key"
                            :label="preset.name"
                            :value="preset.key"
                        />
                    </el-select>
                    <button
                        class="btn-back btn-compact"
                        @click="applySelectedPreset"
                        :disabled="!activePresetKey || bulkLoading"
                    >
                        <i class="bi bi-bookmark-check me-1"></i> Pakai Preset
                    </button>
                    <button
                        class="btn-back btn-compact"
                        @click="selectAllFilteredUsers"
                        :disabled="bulkLoading || bulkMeta.total === 0"
                    >
                        <i class="bi bi-check2-square me-1"></i>
                        Pilih Semua Sesuai Filter
                    </button>
                </div>

                <div v-if="bulkLoading" class="search-loading-hint mt-2">
                    <i class="bi bi-arrow-repeat spin"></i>
                    <span>Memuat target user...</span>
                </div>

                <div v-else class="bulk-results mt-2">
                    <div class="bulk-results-head">
                        <span>Hasil filter: {{ bulkMeta.total }} user</span>
                        <span class="text-muted">Terpilih di halaman ini: {{ selectedOnCurrentPageCount }}</span>
                    </div>

                    <div v-if="bulkTargetResults.length === 0" class="bulk-empty">Tidak ada user untuk filter ini.</div>

                    <label
                        v-for="u in bulkTargetResults"
                        :key="u.id"
                        class="bulk-user-row"
                        :class="{ selected: selectedUserIds.includes(u.id) }"
                    >
                        <input
                            type="checkbox"
                            class="form-check-input"
                            :checked="selectedUserIds.includes(u.id)"
                            @change="toggleBulkUserSelection(u, $event.target.checked)"
                        />
                        <div class="bulk-user-main">
                            <div class="bulk-user-name">{{ u.nama }}</div>
                            <div class="bulk-user-meta">
                                {{ u.kode || '-' }} • {{ u.divisi || '-' }} • {{ u.level || '-' }} •
                                {{ u.jabatan || '-' }}
                            </div>
                        </div>
                        <span v-if="selectedUserIds.includes(u.id)" class="already-picked">Sudah dipilih</span>
                    </label>
                </div>

                <div v-if="bulkMeta.last_page > 1" class="bulk-pagination mt-2">
                    <button
                        class="btn-back btn-compact"
                        :disabled="bulkMeta.current_page <= 1 || bulkLoading"
                        @click="fetchBulkTargets(bulkMeta.current_page - 1)"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="bulk-page-info"> Halaman {{ bulkMeta.current_page }} / {{ bulkMeta.last_page }} </span>
                    <button
                        class="btn-back btn-compact"
                        :disabled="bulkMeta.current_page >= bulkMeta.last_page || bulkLoading"
                        @click="fetchBulkTargets(bulkMeta.current_page + 1)"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="selected-users-panel mt-3" v-if="selectedUsers.length > 0">
                <div class="selected-users-head">
                    <div class="selected-users-title">Daftar User Terpilih</div>
                    <div class="selected-users-actions">
                        <span class="selected-users-count">{{ selectedUsers.length }} user</span>
                        <button class="btn-link-mini" @click="clearSelectedUsers">Kosongkan</button>
                    </div>
                </div>
                <div class="selected-users">
                    <span
                        v-for="u in selectedUsers"
                        :key="u.id"
                        class="selected-user-tag"
                        :class="{ 'tag-highlight': highlightedUserIds.includes(u.id) }"
                    >
                        {{ u.nama }} ({{ u.kode || '-' }})
                        <i class="bi bi-x" @click="removeUser(u.id)"></i>
                    </span>
                </div>
            </div>

            <div class="step-actions mt-3">
                <button class="btn-next" @click="currentStep = 1" :disabled="!canProceedStep1 || !canDistribute">
                    Lanjut: Pilih Menu <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: Select Menu -->
        <div v-if="currentStep === 1" class="step-content">
            <h6 class="step-title"><i class="bi bi-menu-button-wide me-2"></i>Pilih Menu untuk Distribusi</h6>

            <div v-if="currentAccessLoading" class="search-loading-hint">
                <i class="bi bi-arrow-repeat spin"></i>
                <span>Memuat akses existing user terpilih...</span>
            </div>

            <div v-if="existingAccessChips.length > 0" class="existing-chip-panel mb-2">
                <div class="existing-chip-title">
                    <i class="bi bi-check-circle me-1"></i>
                    Akses existing ({{ existingAccessModeLabel }}): {{ existingAccessChips.length }}
                </div>
                <div class="existing-chip-list">
                    <span v-for="chip in existingAccessChips" :key="chip.Jenis_page" class="existing-chip-item">
                        <i :class="normalizeIcon(chip.Icon_Menu)"></i>
                        <span>{{ chip.Nama_Menu || chip.Jenis_page }}</span>
                    </span>
                </div>
            </div>

            <div class="search-bar mb-3">
                <i class="bi bi-search"></i>
                <input
                    v-model="menuSearch"
                    class="form-control form-control-sm"
                    placeholder="Cari menu / jenis page / header..."
                />
            </div>

            <div class="menu-select-list">
                <div v-for="(menus, header) in groupedFilteredMenus" :key="header" class="menu-header-group">
                    <div class="menu-header-title">{{ header || 'Lainnya' }}</div>
                    <label
                        v-for="m in menus"
                        :key="m.Jenis_page"
                        class="menu-select-item"
                        :class="{
                            'menu-checked': selectedPages.includes(m.Jenis_page),
                            'menu-owned': intersectionExistingPages.includes(m.Jenis_page),
                        }"
                    >
                        <input type="checkbox" :value="m.Jenis_page" v-model="selectedPages" class="form-check-input" />
                        <div class="menu-select-info">
                            <span class="menu-select-name">
                                <i :class="normalizeIcon(m.Icon_Menu)"></i>
                                {{ m.Nama_Menu }}
                            </span>
                            <span class="menu-select-meta">
                                <span v-if="m.cap_count > 0" class="cap-badge">{{ m.cap_count }} cap</span>
                                <span v-else class="cap-badge cap-none">no cap</span>
                                <span v-if="m.Kode_Grup_Fitur" class="grup-badge">{{ m.Kode_Grup_Fitur }}</span>
                                <span
                                    v-if="intersectionExistingPages.includes(m.Jenis_page)"
                                    class="grup-badge owned-badge"
                                >
                                    existing
                                </span>
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="step-actions mt-3">
                <button class="btn-back" @click="currentStep = 0"><i class="bi bi-arrow-left me-1"></i> Kembali</button>
                <button
                    class="btn-next"
                    @click="doPreview"
                    :disabled="selectedPages.length === 0 || previewing || !canDistribute"
                >
                    <span v-if="previewing"><i class="bi bi-arrow-repeat spin"></i> Loading...</span>
                    <span v-else>Preview & Konfirmasi <i class="bi bi-arrow-right ms-1"></i></span>
                </button>
            </div>
        </div>

        <!-- STEP 3: Preview & Execute -->
        <div v-if="currentStep === 2" class="step-content">
            <h6 class="step-title"><i class="bi bi-clipboard-check me-2"></i>Review & Eksekusi</h6>

            <div class="preview-cards" v-if="preview">
                <div class="preview-card">
                    <div class="preview-value">{{ preview.user_count }}</div>
                    <div class="preview-label">Target User</div>
                </div>
                <div class="preview-card">
                    <div class="preview-value">{{ preview.menu_count }}</div>
                    <div class="preview-label">Menu Dipilih</div>
                </div>
                <div class="preview-card preview-new">
                    <div class="preview-value">{{ preview.new_records }}</div>
                    <div class="preview-label">Record Baru</div>
                </div>
                <div class="preview-card preview-skip">
                    <div class="preview-value">{{ preview.existing_skip }}</div>
                    <div class="preview-label">Di-skip (sudah ada)</div>
                </div>
            </div>

            <!-- Progress -->
            <div v-if="distributing" class="progress-section mt-3">
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" :style="{ width: progressPercent + '%' }"></div>
                </div>
                <div class="progress-text">{{ progressText }}</div>
            </div>

            <!-- Result -->
            <div v-if="result" class="result-card mt-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 1.5rem"></i>
                <div>
                    <div class="result-title">Distribusi Selesai</div>
                    <div class="result-detail">
                        {{ result.created }} akses dibuat, {{ result.skipped }} di-skip,
                        {{ result.menu_access_created }} menu access dibuat
                    </div>
                </div>
            </div>

            <div class="step-actions mt-3">
                <button class="btn-back" @click="currentStep = 1" :disabled="distributing">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </button>
                <button
                    v-if="!result"
                    class="btn-execute"
                    @click="executeDistribution"
                    :disabled="distributing || !preview || preview.new_records === 0 || !canDistribute"
                >
                    <i class="bi bi-send me-1"></i> Distribusikan Akses
                </button>
                <button v-else class="btn-next" @click="resetAll">
                    <i class="bi bi-arrow-repeat me-1"></i> Distribusi Lagi
                </button>
            </div>
        </div>

        <!-- Seed Existing -->
        <div class="seed-section mt-4">
            <div class="seed-card">
                <div class="seed-info">
                    <i class="bi bi-database-gear"></i>
                    <div>
                        <div class="seed-title">Seed Data Existing</div>
                        <div class="seed-desc">
                            Generate N_HRIS_Role_Menu_Access untuk semua Page Access yang belum punya menu access
                            record.
                        </div>
                    </div>
                </div>
                <button class="btn-seed" @click="seedExisting" :disabled="seeding || !canDistribute">
                    <span v-if="seeding"><i class="bi bi-arrow-repeat spin"></i></span>
                    <span v-else><i class="bi bi-magic me-1"></i>Seed</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus';

export default {
    name: 'AccessDistributionPanel',
    props: {
        scope: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            steps: ['Pilih Target', 'Pilih Menu', 'Review & Eksekusi'],
            currentStep: 0,
            mode: 'individual',
            // Individual
            userSearch: '',
            userSearchResults: [],
            userSearchLoading: false,
            selectedUsers: [],
            searchTimeout: null,
            // Bulk
            filters: { divisi: [], level: [], jabatan: [], menus: [] },
            filterDivisi: [],
            filterLevel: [],
            filterJabatan: [],
            bulkSearch: '',
            bulkTargetResults: [],
            bulkLoading: false,
            bulkMeta: { total: 0, current_page: 1, last_page: 1, per_page: 10 },
            bulkPage: 1,
            bulkPerPage: 10,
            bulkPresets: [],
            activePresetKey: null,
            distributionGroupId: null,
            highlightedUserIds: [],
            // Menus
            menuSearch: '',
            selectedPages: [],
            currentAccessByUser: {},
            currentAccessLoading: false,
            // Preview
            previewing: false,
            preview: null,
            // Execute
            distributing: false,
            progressPercent: 0,
            progressText: '',
            result: null,
            // Seed
            seeding: false,
        };
    },
    computed: {
        canDistribute() {
            if (this.scope?.isSystemAdmin) {
                return true;
            }
            return Array.isArray(this.scope?.manageableGroupIds)
                && this.scope.manageableGroupIds.length > 0;
        },
        selectedUserIds() {
            return this.selectedUsers.map((u) => u.id);
        },
        distributionGroupOptions() {
            const allowed = this.scope?.isSystemAdmin
                ? null
                : new Set((this.scope?.manageableGroupIds || []).map((id) => Number(id)));
            const groups = new Map();
            (this.filters.menus || []).forEach((menu) => {
                const id = Number(menu.Id_Grup_Fitur ?? menu.id_grup_fitur ?? menu.idGrupFitur);
                if (!Number.isFinite(id) || (allowed && !allowed.has(id))) return;
                groups.set(id, menu.Kode_Grup_Fitur || `Grup ${id}`);
            });
            return Array.from(groups, ([id, label]) => ({ id, label }));
        },
        canProceedStep1() {
            return this.selectedUsers.length > 0;
        },
        selectedOnCurrentPageCount() {
            return this.bulkTargetResults.filter((u) => this.selectedUserIds.includes(u.id)).length;
        },
        filteredMenus() {
            let menus = this.filters.menus || [];

            if (
                !this.scope?.isSystemAdmin &&
                Array.isArray(this.scope?.manageableGroupIds) &&
                this.scope.manageableGroupIds.length
            ) {
                const allowedGroups = this.scope.manageableGroupIds.map((id) => Number(id));
                menus = menus.filter((m) => {
                    const groupId = Number(m.Id_Grup_Fitur ?? m.id_grup_fitur ?? m.idGrupFitur);
                    return Number.isFinite(groupId) && allowedGroups.includes(groupId);
                });
            }

            if (!this.menuSearch) return menus;
            const s = this.menuSearch.toLowerCase();
            return menus.filter(
                (m) =>
                    m.Nama_Menu?.toLowerCase().includes(s) ||
                    m.Jenis_page?.toLowerCase().includes(s) ||
                    m.Header_Label?.toLowerCase().includes(s),
            );
        },
        intersectionExistingPages() {
            const userIds = this.selectedUserIds;
            if (userIds.length === 0) {
                return [];
            }

            const firstPages = new Set(
                (this.currentAccessByUser[String(userIds[0])] || []).map((item) => item.Jenis_page),
            );

            if (userIds.length === 1) {
                return Array.from(firstPages);
            }

            return Array.from(firstPages).filter((page) => {
                return userIds.every((uid) => {
                    const list = this.currentAccessByUser[String(uid)] || [];
                    return list.some((item) => item.Jenis_page === page);
                });
            });
        },
        unionExistingPages() {
            const set = new Set();
            this.selectedUserIds.forEach((uid) => {
                const list = this.currentAccessByUser[String(uid)] || [];
                list.forEach((item) => set.add(item.Jenis_page));
            });
            return Array.from(set);
        },
        existingAccessChips() {
            const menuMap = new Map((this.filters.menus || []).map((m) => [m.Jenis_page, m]));
            const useIntersection = this.selectedUserIds.length > 1;
            const pages = useIntersection ? this.intersectionExistingPages : this.unionExistingPages;

            return pages.map((page) => {
                const menu = menuMap.get(page);
                return {
                    Jenis_page: page,
                    Nama_Menu: menu?.Nama_Menu || page,
                    Icon_Menu: menu?.Icon_Menu || 'bi bi-grid-1x2',
                };
            });
        },
        existingAccessModeLabel() {
            return this.selectedUserIds.length > 1 ? 'intersection semua user' : 'milik user';
        },
        groupedFilteredMenus() {
            return this.filteredMenus.reduce((acc, m) => {
                const h = m.Header_Label || '';
                if (!acc[h]) acc[h] = [];
                acc[h].push(m);
                return acc;
            }, {});
        },
    },
    methods: {
        normalizeIcon(icon) {
            const value = String(icon || '').trim();
            if (!value) return 'bi bi-grid-1x2';
            if (value.startsWith('bi bi-')) return value;
            if (value.startsWith('bi-')) return `bi ${value}`;
            if (value.startsWith('bi ')) return value;
            return `bi bi-${value.replace(/^\-+/, '')}`;
        },
        async fetchFilters() {
            try {
                const res = await fetch('/api/v1/system-auth/distribution/filters');
                const data = await res.json();
                this.filters = data;
            } catch (e) {
                console.error(e);
            }
        },
        normalizeUser(user) {
            return {
                id: Number(user.id),
                nama: user.nama,
                kode: user.kode,
                divisi: user.divisi,
                level: user.level,
                jabatan: user.jabatan,
            };
        },
        searchUsers() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(async () => {
                if (this.userSearch.length < 2) {
                    this.userSearchResults = [];
                    this.userSearchLoading = false;
                    return;
                }
                this.userSearchLoading = true;
                try {
                    const params = new URLSearchParams({
                        grup_id: String(this.distributionGroupId ?? 0),
                        search: this.userSearch,
                        per_page: '10',
                    });
                    const res = await fetch(`/api/v1/system-auth/distribution/targets?${params.toString()}`);
                    const data = await res.json();
                    this.userSearchResults = (data.data || []).map((u) => this.normalizeUser(u));
                } catch (e) {
                    console.error(e);
                    ElNotification({
                        title: 'Gagal',
                        message: 'Gagal mencari user.',
                        type: 'error',
                    });
                } finally {
                    this.userSearchLoading = false;
                }
            }, 300);
        },
        addUser(user) {
            const normalized = this.normalizeUser(user);
            if (!this.selectedUserIds.includes(normalized.id)) {
                this.selectedUsers.push(normalized);
            }
            this.userSearch = '';
            this.userSearchResults = [];
        },
        removeUser(id) {
            this.selectedUsers = this.selectedUsers.filter((u) => u.id !== id);
        },
        clearSelectedUsers() {
            this.selectedUsers = [];
            this.currentAccessByUser = {};
            this.selectedPages = [];
        },
        async fetchCurrentAccess() {
            if (this.selectedUserIds.length === 0) {
                this.currentAccessByUser = {};
                return;
            }

            this.currentAccessLoading = true;
            try {
                const res = await fetch('/api/v1/system-auth/distribution/current-access', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ grup_id: this.distributionGroupId ?? 0, user_ids: this.selectedUserIds }),
                });

                const data = await res.json();
                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal memuat akses existing user.');
                    this.currentAccessByUser = {};
                    return;
                }

                this.currentAccessByUser = data.accessByUser || {};
                this.syncSelectionWithExisting();
            } catch (e) {
                console.error(e);
                this.currentAccessByUser = {};
            } finally {
                this.currentAccessLoading = false;
            }
        },
        syncSelectionWithExisting() {
            if (!Array.isArray(this.intersectionExistingPages) || this.intersectionExistingPages.length === 0) {
                return;
            }

            const merged = new Set(this.selectedPages || []);
            this.intersectionExistingPages.forEach((page) => merged.add(page));
            this.selectedPages = Array.from(merged);
        },
        async fetchBulkTargets(page = 1) {
            this.bulkLoading = true;
            this.bulkPage = page;
            try {
                const params = new URLSearchParams();
                params.append('grup_id', String(this.distributionGroupId ?? 0));
                this.filterDivisi.forEach((id) => params.append('filter_divisi[]', id));
                this.filterLevel.forEach((id) => params.append('filter_level[]', id));
                this.filterJabatan.forEach((id) => params.append('filter_jabatan[]', id));
                if (this.bulkSearch.trim()) {
                    params.append('search', this.bulkSearch.trim());
                }
                params.append('per_page', String(this.bulkPerPage));
                params.append('page', String(this.bulkPage));

                const res = await fetch(`/api/v1/system-auth/distribution/targets?${params.toString()}`);
                const data = await res.json();

                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal memuat target user.');
                    this.bulkTargetResults = [];
                    this.bulkMeta = { total: 0, current_page: 1, last_page: 1, per_page: this.bulkPerPage };
                    return;
                }

                this.bulkTargetResults = (data.data || []).map((u) => this.normalizeUser(u));
                this.bulkMeta = data.meta || {
                    total: this.bulkTargetResults.length,
                    current_page: this.bulkPage,
                    last_page: 1,
                    per_page: this.bulkPerPage,
                };
            } catch (e) {
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal memuat target user.',
                    type: 'error',
                });
                this.bulkTargetResults = [];
                this.bulkMeta = { total: 0, current_page: 1, last_page: 1, per_page: this.bulkPerPage };
            } finally {
                this.bulkLoading = false;
            }
        },
        toggleBulkUserSelection(user, isChecked) {
            if (isChecked) {
                this.addUser(user);
                return;
            }
            this.removeUser(user.id);
        },
        highlightNewUsers(newlyAddedIds) {
            if (!newlyAddedIds.length) {
                return;
            }

            this.highlightedUserIds = [...new Set([...this.highlightedUserIds, ...newlyAddedIds])];
            setTimeout(() => {
                this.highlightedUserIds = this.highlightedUserIds.filter((id) => !newlyAddedIds.includes(id));
            }, 2200);
        },
        async selectAllFilteredUsers() {
            this.bulkLoading = true;
            try {
                const params = new URLSearchParams();
                params.append('grup_id', String(this.distributionGroupId ?? 0));
                this.filterDivisi.forEach((id) => params.append('filter_divisi[]', id));
                this.filterLevel.forEach((id) => params.append('filter_level[]', id));
                this.filterJabatan.forEach((id) => params.append('filter_jabatan[]', id));
                if (this.bulkSearch.trim()) {
                    params.append('search', this.bulkSearch.trim());
                }
                params.append('all', '1');

                const res = await fetch(`/api/v1/system-auth/distribution/targets?${params.toString()}`);
                const data = await res.json();

                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal mengambil semua user sesuai filter.');
                    return;
                }

                const allFilteredUsers = (data.data || []).map((u) => this.normalizeUser(u));
                const existingIds = new Set(this.selectedUserIds);
                const newlyAddedIds = [];

                allFilteredUsers.forEach((u) => {
                    if (!existingIds.has(u.id)) {
                        this.selectedUsers.push(u);
                        existingIds.add(u.id);
                        newlyAddedIds.push(u.id);
                    }
                });

                this.highlightNewUsers(newlyAddedIds);
                if (newlyAddedIds.length > 0) {
                    ElMessage.success(`${newlyAddedIds.length} user ditambahkan dari hasil filter.`);
                } else {
                    ElMessage.info('Semua user hasil filter sudah ada di daftar target.');
                }
            } catch (e) {
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal memilih semua user dari filter aktif.',
                    type: 'error',
                });
            } finally {
                this.bulkLoading = false;
            }
        },
        loadBulkPresets() {
            try {
                const raw = localStorage.getItem('access_distribution_bulk_presets');
                this.bulkPresets = raw ? JSON.parse(raw) : [];
            } catch {
                this.bulkPresets = [];
            }
        },
        persistBulkPresets() {
            localStorage.setItem('access_distribution_bulk_presets', JSON.stringify(this.bulkPresets));
        },
        async saveCurrentPreset() {
            try {
                const { value } = await ElMessageBox.prompt('Masukkan nama preset filter', 'Simpan Preset', {
                    confirmButtonText: 'Simpan',
                    cancelButtonText: 'Batal',
                    inputPlaceholder: 'Contoh: Divisi Finance Level Staff',
                    inputValidator: (v) => (v && v.trim().length >= 3 ? true : 'Minimal 3 karakter'),
                });

                const name = value.trim();
                const key = name
                    .toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^a-z0-9\-]/g, '');
                const preset = {
                    key,
                    name,
                    filterDivisi: [...this.filterDivisi],
                    filterLevel: [...this.filterLevel],
                    filterJabatan: [...this.filterJabatan],
                    bulkSearch: this.bulkSearch,
                };

                const existingIndex = this.bulkPresets.findIndex((p) => p.key === key);
                if (existingIndex >= 0) {
                    this.bulkPresets.splice(existingIndex, 1, preset);
                } else {
                    this.bulkPresets.push(preset);
                }

                this.persistBulkPresets();
                this.activePresetKey = preset.key;
                ElMessage.success('Preset filter berhasil disimpan.');
            } catch {
                // user cancelled
            }
        },
        applySelectedPreset() {
            const preset = this.bulkPresets.find((p) => p.key === this.activePresetKey);
            if (!preset) {
                return;
            }

            this.filterDivisi = [...(preset.filterDivisi || [])];
            this.filterLevel = [...(preset.filterLevel || [])];
            this.filterJabatan = [...(preset.filterJabatan || [])];
            this.bulkSearch = preset.bulkSearch || '';
            this.fetchBulkTargets(1);
        },
        buildPayload() {
            return {
                grup_id: this.distributionGroupId ?? 0,
                mode: 'individual',
                user_ids: this.selectedUserIds,
                filter_divisi: this.filterDivisi,
                filter_level: this.filterLevel,
                filter_jabatan: this.filterJabatan,
                pages: this.selectedPages,
            };
        },
        async doPreview() {
            if (!this.canDistribute) {
                return;
            }
            this.previewing = true;
            try {
                const res = await fetch('/api/v1/system-auth/distribution/preview', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.buildPayload()),
                });
                const data = await res.json();
                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal mengambil preview distribusi.');
                    return;
                }
                this.preview = data;
                this.result = null;
                this.currentStep = 2;
            } catch (e) {
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal mengambil preview distribusi.',
                    type: 'error',
                });
            } finally {
                this.previewing = false;
            }
        },
        async executeDistribution() {
            if (!this.canDistribute) {
                return;
            }
            this.distributing = true;
            this.progressPercent = 0;
            this.progressText = 'Memulai distribusi...';

            // Simulate progress since we don't have real-time updates
            const interval = setInterval(() => {
                if (this.progressPercent < 90) {
                    this.progressPercent += Math.random() * 15;
                    if (this.progressPercent > 90) this.progressPercent = 90;
                }
            }, 400);

            try {
                this.progressText = 'Membuat akses dan menu permissions...';
                const res = await fetch('/api/v1/system-auth/distribution/execute', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.buildPayload()),
                });
                const data = await res.json();
                clearInterval(interval);

                if (!res.ok) {
                    this.progressPercent = 0;
                    this.progressText = '';
                    ElMessage.error(data.message || 'Gagal distribusi.');
                    return;
                }

                this.progressPercent = 100;
                this.progressText = 'Selesai!';
                this.result = data;
                ElNotification({
                    title: 'Berhasil',
                    message: data.message || 'Distribusi berhasil dijalankan.',
                    type: 'success',
                });
            } catch (e) {
                clearInterval(interval);
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal distribusi.',
                    type: 'error',
                });
            } finally {
                this.distributing = false;
            }
        },
        async seedExisting() {
            if (!this.canDistribute) {
                return;
            }
            try {
                await ElMessageBox.confirm(
                    'Seed akan membuat N_HRIS_Role_Menu_Access untuk semua HRIS_Page_Access yang belum punya record. Lanjutkan?',
                    'Konfirmasi Seed',
                    {
                        confirmButtonText: 'Lanjut',
                        cancelButtonText: 'Batal',
                        type: 'warning',
                        center: true,
                        distinguishCancelAndClose: true,
                    },
                );
            } catch {
                return;
            }
            this.seeding = true;
            try {
                const res = await fetch('/api/v1/system-auth/distribution/seed-existing', {
                    method: 'POST',
                    headers: {},
                });
                const data = await res.json();
                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal seed.');
                    return;
                }
                ElNotification({
                    title: 'Berhasil',
                    message: `${data.message || 'Seed selesai.'} (${data.menu_access_created || 0} records dibuat)`,
                    type: 'success',
                });
            } catch (e) {
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal seed.',
                    type: 'error',
                });
            } finally {
                this.seeding = false;
            }
        },
        resetAll() {
            this.currentStep = 0;
            this.preview = null;
            this.result = null;
            this.progressPercent = 0;
            this.progressText = '';
            this.selectedPages = [];
        },
    },
    mounted() {
        this.fetchFilters();
        this.loadBulkPresets();
    },
    watch: {
        selectedUsers: {
            deep: true,
            handler() {
                this.fetchCurrentAccess();
            },
        },
    },
};
</script>

<style scoped>
/* Steps Bar */
.steps-bar {
    display: flex;
    gap: 0;
}
.step {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    position: relative;
}
.step:first-child {
    border-radius: 10px 0 0 10px;
}
.step:last-child {
    border-radius: 0 10px 10px 0;
}
.step.active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-500);
}
.step.completed {
    background: var(--hcis-emerald-50);
    border-color: var(--hcis-emerald-500);
}
.step-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
}
.step.active .step-icon {
    background: var(--hcis-indigo-500);
    color: #fff;
}
.step.completed .step-icon {
    background: var(--hcis-emerald-500);
    color: #fff;
}
.step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
}
.step.active .step-label {
    color: var(--hcis-indigo-500);
}
.step.completed .step-label {
    color: var(--hcis-emerald-600);
}
.step-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

/* Mode Selector */
.mode-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
.mode-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    border: 2px solid var(--hcis-slate-200);
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}
.mode-option:hover {
    border-color: var(--hcis-indigo-200);
}
.mode-option.selected {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
}
.mode-option i {
    font-size: 1.5rem;
    color: var(--hcis-slate-500);
    margin-bottom: 0.3rem;
}
.mode-option.selected i {
    color: var(--hcis-indigo-500);
}
.mode-option span {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--hcis-slate-700);
}
.mode-option small {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    margin-top: 0.15rem;
}

/* Filter */
.filter-section {
    padding: 0.75rem;
    background: var(--hcis-slate-50);
    border-radius: 10px;
    border: 1px solid var(--hcis-slate-200);
}
.form-label-sm {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    margin-bottom: 0.25rem;
    display: block;
}

/* User Search */
.user-search-results {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
}
.search-loading-hint {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: -0.15rem;
    margin-bottom: 0.5rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

.bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}
.btn-compact {
    padding: 0.35rem 0.7rem;
    font-size: 0.72rem;
}
.preset-select {
    min-width: 180px;
}
.bulk-results {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: #fff;
    max-height: 260px;
    overflow-y: auto;
}
.bulk-results-head {
    position: sticky;
    top: 0;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0.6rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--hcis-slate-200);
    font-size: 0.72rem;
    color: var(--hcis-slate-600);
}
.bulk-empty {
    padding: 0.75rem;
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
}
.bulk-user-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.6rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    cursor: pointer;
}
.bulk-user-row:last-child {
    border-bottom: none;
}
.bulk-user-row:hover {
    background: var(--hcis-slate-50);
}
.bulk-user-row.selected {
    background: var(--hcis-indigo-50);
}
.bulk-user-main {
    flex: 1;
    min-width: 0;
}
.bulk-user-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.bulk-user-meta {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.already-picked {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-100);
    border-radius: 999px;
    padding: 0.15rem 0.4rem;
}

.bulk-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.45rem;
}
.bulk-page-info {
    font-size: 0.72rem;
    color: var(--hcis-slate-600);
    font-weight: 600;
}

.selected-users-panel {
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 10px;
    background: linear-gradient(180deg, var(--hcis-indigo-50) 0%, var(--hcis-white) 100%);
    padding: 0.65rem;
}
.selected-users-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.45rem;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.selected-users-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-indigo-800);
}
.selected-users-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.selected-users-count {
    font-size: 0.72rem;
    color: var(--hcis-indigo-700);
    font-weight: 600;
}
.btn-link-mini {
    border: none;
    background: transparent;
    font-size: 0.72rem;
    color: var(--hcis-red-500);
    font-weight: 600;
    padding: 0;
}

.user-result-item {
    display: flex;
    justify-content: space-between;
    padding: 0.45rem 0.75rem;
    cursor: pointer;
    transition: background 0.15s;
    font-size: 0.82rem;
}
.user-result-item:hover {
    background: var(--hcis-indigo-50);
}
.user-result-item.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.user-result-name {
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.user-result-code {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-family: monospace;
}
.selected-users {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    max-height: 8rem;
    overflow-y: auto;
    padding-right: 0.15rem;
}
.selected-user-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    background: var(--hcis-indigo-50);
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-indigo-500);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}
.selected-user-tag.tag-highlight {
    background: var(--hcis-green-100);
    color: #166534;
    box-shadow: 0 0 0 1px #86efac inset;
    transform: translateY(-1px);
}
.selected-user-tag i {
    cursor: pointer;
    font-size: 0.85rem;
}
.selected-user-tag i:hover {
    color: var(--hcis-red-500);
}

/* Menu Select */
.search-bar {
    position: relative;
}
.search-bar i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-400);
}
.search-bar input {
    padding-left: 2rem;
    border-radius: 8px;
}
.menu-select-list {
    max-height: 400px;
    overflow-y: auto;
}
.menu-header-title {
    font-weight: 700;
    font-size: 0.78rem;
    color: var(--hcis-indigo-500);
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--hcis-slate-100);
}
.menu-select-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}
.menu-select-item:hover {
    background: var(--hcis-slate-50);
}
.menu-checked {
    background: var(--hcis-indigo-50);
}
.menu-owned {
    border: 1px dashed #93c5fd;
}
.menu-select-info {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.menu-select-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.menu-select-meta {
    display: flex;
    gap: 0.3rem;
}
.cap-badge {
    font-size: 0.6rem;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    font-weight: 600;
}
.cap-none {
    background: var(--hcis-red-50);
    color: var(--hcis-red-600);
}
.grup-badge {
    font-size: 0.6rem;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.owned-badge {
    background: #dbeafe;
    color: #1d4ed8;
}

.existing-chip-panel {
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    background: #eff6ff;
    padding: 0.55rem;
}
.existing-chip-title {
    font-size: 0.73rem;
    font-weight: 700;
    color: #1e3a8a;
    margin-bottom: 0.45rem;
}
.existing-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.existing-chip-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
    background: #fff;
    color: #1e3a8a;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.18rem 0.5rem;
}
.form-check-input {
    width: 15px;
    height: 15px;
    cursor: pointer;
    flex-shrink: 0;
}
.form-check-input:checked {
    background-color: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
}

/* Preview */
.preview-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}
.preview-card {
    padding: 1rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    text-align: center;
}
.preview-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}
.preview-label {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.preview-new {
    background: var(--hcis-emerald-50);
    border-color: var(--hcis-emerald-200);
}
.preview-new .preview-value {
    color: var(--hcis-emerald-600);
}
.preview-skip {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}
.preview-skip .preview-value {
    color: var(--hcis-amber-600);
}

/* Progress */
.progress-bar-container {
    height: 8px;
    background: var(--hcis-slate-200);
    border-radius: 4px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
    border-radius: 4px;
    transition: width 0.3s ease;
}
.progress-text {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    margin-top: 0.3rem;
    text-align: center;
}

/* Result */
.result-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 10px;
}
.result-title {
    font-weight: 700;
    color: var(--hcis-emerald-600);
}
.result-detail {
    font-size: 0.78rem;
    color: var(--hcis-slate-600);
}

/* Actions */
.step-actions {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-500);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-back:hover {
    background: var(--hcis-slate-100);
}
.btn-next {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1rem;
    border: none;
    border-radius: 8px;
    background: var(--hcis-indigo-500);
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
    margin-left: auto;
}
.btn-next:hover {
    background: var(--hcis-indigo-600);
}
.btn-next:disabled,
.btn-back:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-execute {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1.25rem;
    border: none;
    border-radius: 8px;
    background: var(--hcis-emerald-500);
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    margin-left: auto;
}
.btn-execute:hover {
    background: var(--hcis-emerald-600);
}
.btn-execute:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Seed */
.seed-section {
    border-top: 1px solid var(--hcis-slate-200);
    padding-top: 1rem;
}
.seed-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
}
.seed-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.seed-info > i {
    font-size: 1.3rem;
    color: var(--hcis-indigo-500);
}
.seed-title {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--hcis-slate-700);
}
.seed-desc {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.btn-seed {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 0.85rem;
    border: 1px solid var(--hcis-indigo-500);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-indigo-500);
    font-weight: 600;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-seed:hover {
    background: var(--hcis-indigo-500);
    color: #fff;
}
.btn-seed:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

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

@media (max-width: 768px) {
    .mode-selector {
        grid-template-columns: 1fr;
    }
    .preview-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .steps-bar {
        flex-direction: column;
    }
    .step {
        border-radius: 8px !important;
    }
}
</style>
