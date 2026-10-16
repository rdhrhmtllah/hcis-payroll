<template>
    <div class="permission-matrix-panel">
        <div v-if="!canManageMatrix" class="alert alert-warning py-2 px-3 mb-3">
            Anda tidak memiliki group kelolaan untuk mengubah permission matrix.
        </div>

        <div class="user-selector-card mb-3">
            <div class="user-selector-header">
                <div>
                    <h6 class="panel-subtitle mb-1"><i class="bi bi-person-check me-2"></i>Pilih User</h6>
                    <p class="user-selector-hint mb-0">Pilih orang dulu, baru matrix menu dan aksi akan ditampilkan.</p>
                </div>
                <div v-if="selectedUser" class="selected-user-chip">
                    <span>{{ selectedUser.nama }}</span>
                    <button type="button" class="clear-user-btn" @click="clearSelectedUser">×</button>
                </div>
            </div>

            <div class="user-search-wrap">
                <el-select
                    v-model="selectedUserId"
                    class="user-search-select"
                    filterable
                    remote
                    reserve-keyword
                    clearable
                    placeholder="Cari user berdasarkan nama / kode..."
                    :remote-method="debouncedUserSearch"
                    :loading="userSearchLoading"
                    loading-text="Sedang mencari user..."
                    no-data-text="Ketik minimal 2 huruf"
                    :disabled="!canManageMatrix"
                    @change="handleUserChange"
                    @clear="clearSelectedUser"
                >
                    <el-option
                        v-for="user in userResults"
                        :key="user.id"
                        :label="`${user.nama} (${user.kode || '-'})`"
                        :value="user.id"
                    >
                        <div class="user-option">
                            <div class="user-option-name">{{ user.nama }}</div>
                            <div class="user-option-meta">{{ user.kode || '-' }} • {{ user.divisi || '-' }}</div>
                        </div>
                    </el-option>
                </el-select>

                <div v-if="userSearchLoading" class="search-loading-line">
                    <i class="bi bi-arrow-repeat spin"></i>
                    <span>Mencari user...</span>
                </div>
            </div>
        </div>

        <section v-if="selectedUser && accessPath" class="access-path-section mb-3">
            <div class="access-path-heading">
                <div>
                    <span class="eyebrow">JALUR OTORISASI USER</span>
                    <h6>User → Role → Resource Capability → Target Scope</h6>
                    <p>Lihat sumber akses sebenarnya sebelum mengubah permission menu individual.</p>
                </div>
                <div class="path-summary">
                    <div class="summary-item">
                        <strong>{{ accessPath.summary?.page_count || 0 }}</strong>
                        <span>Page Access</span>
                    </div>
                    <div class="summary-item">
                        <strong>{{ accessPath.summary?.role_count || 0 }}</strong>
                        <span>Role Aktif</span>
                    </div>
                    <div class="summary-item">
                        <strong>{{ accessPath.summary?.resource_count || 0 }}</strong>
                        <span>Capability</span>
                    </div>
                    <div class="summary-item" :class="{ warning: accessPath.summary?.incomplete_role_count > 0 }">
                        <strong>{{ accessPath.summary?.incomplete_role_count || 0 }}</strong>
                        <span>Perlu Dilengkapi</span>
                    </div>
                </div>
            </div>

            <div v-if="accessPath.roles?.length" class="role-path-list">
                <article v-for="role in accessPath.roles" :key="role.id" class="role-path-card">
                    <div class="role-identity">
                        <div class="role-icon"><i class="bi bi-person-badge"></i></div>
                        <div class="role-title">
                            <div class="role-module">{{ role.module_code || 'GLOBAL' }}</div>
                            <strong>{{ role.name }}</strong>
                            <code>{{ role.code }}</code>
                        </div>
                        <span class="readiness-badge" :class="{ ready: role.readiness?.ready }">
                            {{ role.readiness?.ready ? 'Konfigurasi lengkap' : 'Perlu dilengkapi' }}
                        </span>
                    </div>

                    <div class="role-path-flow">
                        <div class="path-column">
                            <div class="path-column-label">
                                <i class="bi bi-shield-check"></i>
                                Resource Capability
                                <span>{{ role.resource_count }}</span>
                            </div>
                            <div v-if="role.resources?.length" class="chip-list">
                                <span
                                    v-for="resource in role.resources"
                                    :key="`${resource.Context_Code}-${resource.Jenis_page}-${resource.Resource_Type}-${resource.Resource_Code}`"
                                    class="access-chip capability-chip"
                                    :class="{ invalid: resource.master_registered === false }"
                                    :title="resource.Jenis_page || 'Tanpa konteks page'"
                                >
                                    <small>{{ resource.Resource_Type }}</small>
                                    {{ resource.Resource_Code }}
                                    <small v-if="resource.master_registered === false">Tidak terdaftar di master</small>
                                </span>
                            </div>
                            <div v-else class="missing-config">
                                <i class="bi bi-exclamation-triangle"></i>
                                Role belum memiliki resource capability.
                            </div>
                        </div>

                        <div class="path-arrow"><i class="bi bi-arrow-right"></i></div>

                        <div class="path-column">
                            <div class="path-column-label">
                                <i class="bi bi-funnel"></i>
                                Target Scope
                                <span>{{ role.scope_count }}</span>
                            </div>
                            <div v-if="role.scopes?.length" class="chip-list">
                                <span
                                    v-for="scopeItem in role.scopes"
                                    :key="`${role.id}-${scopeItem.Scope_Code}`"
                                    class="access-chip scope-chip"
                                    :title="scopeItem.Scope_Code"
                                >
                                    <small>{{ scopeItem.Scope_Code }}</small>
                                    {{ scopeItem.Scope_Name }}
                                </span>
                            </div>
                            <div v-else class="missing-config">
                                <i class="bi bi-exclamation-triangle"></i>
                                Role belum memiliki target scope.
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="missing-config">
                <i class="bi bi-exclamation-triangle"></i>
                User belum memiliki role aktif pada grup yang dapat Anda kelola.
            </div>
        </section>

        <div class="panel-toolbar">
            <div>
                <h6 class="panel-subtitle"><i class="bi bi-grid-3x3-gap me-2"></i>Permission Page Access</h6>
                <p class="matrix-helper mb-0">Mengatur aksi generik pada menu individual, bukan capability yang berasal dari role.</p>
            </div>
            <div class="toolbar-actions">
                <div v-if="selectedUser" class="matrix-context-pill">
                    <i class="bi bi-person-badge me-1"></i>
                    <span>Matrix untuk {{ selectedUser.nama }}</span>
                </div>
                <el-select
                    v-model="filterPage"
                    class="select-page"
                    filterable
                    clearable
                    placeholder="Filter menu"
                    no-data-text="Tidak ada menu"
                    @change="fetchMatrix(1)"
                    :disabled="!selectedUser"
                >
                    <el-option :value="null" label="Semua Menu" />
                    <el-option
                        v-for="m in menuOptions"
                        :key="m.Jenis_page"
                        :value="m.Jenis_page"
                        :label="`${m.Header_Label} — ${m.Nama_Menu}`"
                    />
                </el-select>
            </div>
        </div>

        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <p class="text-muted small mt-2">Memuat data matrix...</p>
        </div>

        <div v-else-if="matrix.length === 0" class="empty-state">
            <i class="bi bi-x-circle"></i>
            <p v-if="!selectedUser">Pilih user terlebih dahulu.</p>
            <p v-else>Data tidak ditemukan.</p>
        </div>

        <div v-else>
            <!-- Save Bar -->
            <div class="save-bar mb-3" v-if="Object.keys(pendingChanges).length > 0">
                <span class="change-note">
                    <i class="bi bi-pencil-fill"></i> {{ Object.keys(pendingChanges).length }} perubahan belum disimpan
                </span>
                <button class="btn-save" @click="bulkSave" :disabled="saving">
                    <span v-if="saving"><i class="bi bi-arrow-repeat spin"></i> Menyimpan...</span>
                    <span v-else><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</span>
                </button>
            </div>

            <!-- Matrix Table -->
            <div class="matrix-table-wrapper">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="col-menu">Menu</th>
                            <th v-for="a in actions" :key="a.Id_Klasifikasi_Actions" class="col-action text-center">
                                <i :class="getAksiIcon(a.Nama_Aksi)"></i>
                                <div>{{ a.Nama_Aksi }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in matrix" :key="row.id_page_access">
                            <td class="col-menu">
                                <div class="menu-cell">
                                    <span class="menu-badge">{{ row.jenis_page }}</span>
                                </div>
                            </td>
                            <td v-for="action in row.actions" :key="action.id_aksi" class="col-action text-center">
                                <div
                                    v-if="!action.has_capability"
                                    class="no-cap"
                                    title="Capability tidak diaktifkan untuk menu ini"
                                >
                                    <i class="bi bi-dash"></i>
                                </div>
                                <div v-else class="checkbox-wrapper">
                                    <input
                                        type="checkbox"
                                        class="form-check-input matrix-checkbox"
                                        :checked="
                                            getCheckboxState(row.id_page_access, action.id_aksi, action.flag_diizinkan)
                                        "
                                        @change="
                                            toggleCheckbox(row.id_page_access, action.id_aksi, $event.target.checked)
                                        "
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-bar mt-3" v-if="pagination.last_page > 1">
                <span class="page-info">
                    Menampilkan menu {{ pagination.current_page }} dari {{ pagination.last_page }} untuk user yang
                    dipilih (Total: {{ pagination.total }})
                </span>
                <div class="page-controls">
                    <button
                        class="btn-page"
                        :disabled="pagination.current_page === 1"
                        @click="changePage(pagination.current_page - 1)"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button
                        class="btn-page"
                        :disabled="pagination.current_page === pagination.last_page"
                        @click="changePage(pagination.current_page + 1)"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus';

export default {
    name: 'PermissionMatrixPanel',
    props: {
        scope: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            matrix: [],
            actions: [],
            menuOptions: [],
            pagination: { current_page: 1, last_page: 1, total: 0 },
            loading: false,
            saving: false,
            filterPage: null,
            selectedUserId: null,
            selectedUser: null,
            userResults: [],
            userSearchLoading: false,
            userSearchTimeout: null,
            accessPath: null,
            pendingChanges: {}, // mapping: { "id_page_access|id_aksi": "Y" or "T" }
        };
    },
    computed: {
        canManageMatrix() {
            if (this.scope?.isSystemAdmin) {
                return true;
            }
            return Array.isArray(this.scope?.manageableGroupIds) && this.scope.manageableGroupIds.length > 0;
        },
    },
    methods: {
        clearPendingChanges() {
            this.pendingChanges = {};
        },

        async searchUsers(query) {
            if (!this.canManageMatrix) {
                this.userResults = [];
                this.userSearchLoading = false;
                return;
            }

            const trimmedQuery = (query || '').trim();
            if (trimmedQuery.length < 2) {
                this.userResults = [];
                this.userSearchLoading = false;
                return;
            }

            this.userSearchLoading = true;
            try {
                const res = await fetch(
                    `/api/v1/admin/role-manager/users?search=${encodeURIComponent(trimmedQuery)}&page=1`,
                );
                const data = await res.json();
                this.userResults = (data.data || []).map((user) => ({
                    id: user.id,
                    nama: user.nama,
                    kode: user.kode,
                    divisi: user.divisi,
                    status: user.status,
                }));
            } catch (e) {
                console.error(e);
                this.userResults = [];
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal mencari user.',
                    type: 'error',
                });
            } finally {
                this.userSearchLoading = false;
            }
        },

        debouncedUserSearch(query) {
            const trimmedQuery = (query || '').trim();
            this.userSearchLoading = trimmedQuery.length >= 2;
            clearTimeout(this.userSearchTimeout);
            this.userSearchTimeout = setTimeout(() => {
                this.searchUsers(query);
            }, 300);
        },

        handleUserChange(userId) {
            this.selectedUserId = userId;
            this.selectedUser = this.userResults.find((user) => user.id === userId) || null;
            this.userResults = [];
            this.filterPage = null;
            this.clearPendingChanges();
            if (this.selectedUserId) {
                this.fetchMatrix(1);
            } else {
                this.matrix = [];
                this.actions = [];
                this.menuOptions = [];
                this.accessPath = null;
                this.pagination = { current_page: 1, last_page: 1, total: 0 };
            }
        },

        clearSelectedUser() {
            this.selectedUserId = null;
            this.selectedUser = null;
            this.userResults = [];
            this.matrix = [];
            this.actions = [];
            this.menuOptions = [];
            this.accessPath = null;
            this.pagination = { current_page: 1, last_page: 1, total: 0 };
            this.clearPendingChanges();
        },

        async fetchMatrix(page = 1) {
            if (!this.canManageMatrix) {
                this.matrix = [];
                this.actions = [];
                return;
            }

            if (!this.selectedUserId) {
                this.matrix = [];
                this.actions = [];
                this.menuOptions = [];
                this.accessPath = null;
                this.pagination = { current_page: 1, last_page: 1, total: 0 };
                return;
            }

            this.loading = true;
            try {
                let url = `/api/v1/system-auth/permission-matrix?page=${page}&userId=${encodeURIComponent(this.selectedUserId)}`;
                if (this.filterPage) url += `&pageFilter=${encodeURIComponent(this.filterPage)}`;

                const res = await fetch(url);
                const data = await res.json();
                this.matrix = data.matrix || [];
                this.actions = data.actions || [];
                this.menuOptions = data.menuOptions || [];
                this.selectedUser = data.selectedUser || this.selectedUser;
                this.accessPath = data.accessPath || null;
                this.pagination = data.pagination || this.pagination;
            } catch (e) {
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal memuat matrix permission.',
                    type: 'error',
                });
            } finally {
                this.loading = false;
            }
        },
        async changePage(p) {
            if (p >= 1 && p <= this.pagination.last_page) {
                // Warning jika ada belum di-save
                if (Object.keys(this.pendingChanges).length > 0) {
                    try {
                        await ElMessageBox.confirm(
                            'Ada perubahan yang belum disimpan. Lanjut pindah halaman dan buang perubahan ini?',
                            'Konfirmasi',
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
                    this.clearPendingChanges();
                }
                this.fetchMatrix(p);
            }
        },
        getCheckboxState(pageAccessId, actionId, originalFlag) {
            const key = `${pageAccessId}|${actionId}`;
            if (this.pendingChanges[key]) {
                return this.pendingChanges[key] === 'Y';
            }
            return originalFlag === 'Y';
        },
        toggleCheckbox(pageAccessId, actionId, isChecked) {
            const key = `${pageAccessId}|${actionId}`;
            const newVal = isChecked ? 'Y' : 'T';

            // Find original
            const row = this.matrix.find((r) => r.id_page_access === pageAccessId);
            const act = row?.actions.find((a) => a.id_aksi === actionId);

            if (act && act.flag_diizinkan === newVal) {
                // Reverted to original
                delete this.pendingChanges[key];
            } else {
                this.pendingChanges[key] = newVal;
            }
        },
        async bulkSave() {
            if (!this.canManageMatrix) return;
            if (Object.keys(this.pendingChanges).length === 0) return;
            this.saving = true;

            const payload = Object.entries(this.pendingChanges).map(([key, val]) => {
                const [pa, act] = key.split('|');
                return {
                    id_page_access: parseInt(pa),
                    id_aksi: parseInt(act),
                    flag_diizinkan: val,
                };
            });

            try {
                const res = await fetch('/api/v1/system-auth/permission-matrix/bulk-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ changes: payload }),
                });
                const data = await res.json();
                if (!res.ok) {
                    ElMessage.error(data.message || 'Gagal menyimpan matrix.');
                    return;
                }

                // Clear pending & refresh
                this.clearPendingChanges();
                this.fetchMatrix(this.pagination.current_page);
                ElNotification({
                    title: 'Berhasil',
                    message: data.message || 'Matrix berhasil disimpan.',
                    type: 'success',
                });
            } catch (e) {
                console.error(e);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal menyimpan matrix.',
                    type: 'error',
                });
            } finally {
                this.saving = false;
            }
        },
        getAksiIcon(nama) {
            const map = {
                CREATE: 'bi bi-plus-circle',
                VIEW: 'bi bi-eye',
                EDIT: 'bi bi-pencil-square',
                DELETE: 'bi bi-trash',
                APPROVE: 'bi bi-check2-square',
                PRINT: 'bi bi-printer',
                SUMMARY: 'bi bi-bar-chart',
            };
            return map[nama] || 'bi bi-gear';
        },
    },
    mounted() {
        if (this.canManageMatrix) {
            this.userResults = [];
        }
    },
};
</script>

<style scoped>
.access-path-section {
    border: 1px solid #dbeafe;
    border-radius: 16px;
    background: linear-gradient(145deg, #f8fbff 0%, var(--hcis-white) 62%);
    padding: 1rem;
}
.access-path-heading {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.eyebrow {
    display: block;
    color: var(--hcis-indigo-600);
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    margin-bottom: 0.25rem;
}
.access-path-heading h6 {
    color: #172554;
    font-size: 1rem;
    font-weight: 750;
    margin: 0;
}
.access-path-heading p,
.matrix-helper {
    color: var(--hcis-slate-500);
    font-size: 0.74rem;
    margin-top: 0.25rem;
}
.path-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(88px, 1fr));
    gap: 0.45rem;
}
.summary-item {
    min-width: 88px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: #fff;
    padding: 0.5rem 0.65rem;
}
.summary-item strong,
.summary-item span {
    display: block;
}
.summary-item strong {
    color: #1e3a8a;
    font-size: 1rem;
}
.summary-item span {
    color: var(--hcis-slate-500);
    font-size: 0.65rem;
    margin-top: 0.08rem;
}
.summary-item.warning {
    border-color: #fed7aa;
    background: #fff7ed;
}
.summary-item.warning strong {
    color: #c2410c;
}
.role-path-list {
    display: grid;
    gap: 0.65rem;
}
.role-path-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 13px;
    background: #fff;
    padding: 0.75rem;
}
.role-identity {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}
.role-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.role-title {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.role-title strong {
    color: #172554;
    font-size: 0.82rem;
}
.role-title code {
    color: var(--hcis-slate-500);
    font-size: 0.66rem;
}
.role-module {
    color: var(--hcis-indigo-600);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.06em;
}
.readiness-badge {
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 0.64rem;
    font-weight: 700;
    padding: 0.28rem 0.55rem;
    white-space: nowrap;
}
.readiness-badge.ready {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
}
.role-path-flow {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 24px minmax(0, 1fr);
    align-items: stretch;
    gap: 0.5rem;
    padding-top: 0.65rem;
}
.path-column {
    border-radius: 10px;
    background: var(--hcis-slate-50);
    padding: 0.6rem;
    min-width: 0;
}
.path-column-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-700);
    font-size: 0.7rem;
    font-weight: 750;
    margin-bottom: 0.45rem;
}
.path-column-label span {
    margin-left: auto;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    padding: 0.08rem 0.4rem;
}
.path-arrow {
    display: grid;
    place-items: center;
    color: var(--hcis-slate-400);
}
.chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.access-chip {
    display: inline-flex;
    flex-direction: column;
    gap: 0.08rem;
    max-width: 100%;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.3rem 0.45rem;
    overflow-wrap: anywhere;
    font-size: 0.67rem;
    font-weight: 650;
}
.access-chip small {
    font-size: 0.55rem;
    font-weight: 750;
    opacity: 0.7;
    text-transform: uppercase;
}
.capability-chip {
    border-color: var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
}
.capability-chip.invalid {
    border-color: var(--hcis-red-300);
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
}
.scope-chip {
    border-color: var(--hcis-emerald-200);
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
}
.missing-config {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px dashed var(--hcis-orange-300);
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
    padding: 0.45rem 0.55rem;
    font-size: 0.68rem;
}
.panel-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.panel-subtitle {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}
.user-selector-card {
    border: 1px solid #dbeafe;
    background: linear-gradient(180deg, #eff6ff 0%, var(--hcis-white) 100%);
    border-radius: 14px;
    padding: 0.9rem 1rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
}
.user-selector-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}
.user-selector-hint {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
}
.selected-user-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.6rem;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.78rem;
    font-weight: 600;
}
.clear-user-btn {
    width: 20px;
    height: 20px;
    border: none;
    border-radius: 50%;
    background: rgba(29, 78, 216, 0.12);
    color: #1d4ed8;
    line-height: 1;
    padding: 0;
}
.user-search-wrap {
    position: relative;
    margin-top: 0.85rem;
}
.user-search-box {
    width: 100%;
}
.user-search-select {
    width: 100%;
}
.user-option {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    width: 100%;
}
.user-option-name {
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--hcis-slate-900);
}
.user-option-meta {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.search-loading-line {
    margin-top: 0.4rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.toolbar-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}
.matrix-context-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.select-page {
    min-width: 250px;
    border-radius: 8px;
}
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
}
.search-box input {
    padding-left: 2rem;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
    width: 200px;
}

/* Table */
.matrix-table-wrapper {
    overflow-x: auto;
    max-width: 100%;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: #fff;
    max-height: 500px;
    overflow-y: auto;
}
.matrix-table {
    width: 100%;
    min-width: 1040px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
}
.matrix-table th {
    padding: 0.5rem 0.75rem;
    background: var(--hcis-slate-50);
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    border-bottom: 1px solid var(--hcis-slate-200);
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
}
.matrix-table td {
    padding: 0.45rem 0.75rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    vertical-align: top;
}
.matrix-table tr:hover td {
    background: var(--hcis-slate-50);
}

/* Sticky columns */
.col-user {
    width: 180px;
    min-width: 180px;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.col-menu {
    width: 360px;
    min-width: 360px;
    max-width: 360px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.col-action {
    width: 84px;
    min-width: 84px;
}

.col-action i {
    font-size: 1.1rem;
    color: var(--hcis-indigo-500);
}
.col-action div {
    font-size: 0.65rem;
    margin-top: 0.2rem;
    white-space: nowrap;
}
.menu-cell {
    display: flex;
    flex-direction: column;
    gap: 0.08rem;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--hcis-slate-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-id {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    font-family: monospace;
}
.menu-badge {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 0.45rem 0.6rem;
    background: var(--hcis-slate-100);
    border-radius: 6px;
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    font-family: monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.matrix-table .checkbox-wrapper,
.matrix-table .no-cap {
    padding-top: 0.35rem;
}

/* Checkbox */
.checkbox-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
}
.matrix-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.matrix-checkbox:checked {
    background-color: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
}
.no-cap {
    color: var(--hcis-slate-300);
    font-size: 1.2rem;
}

/* Save Bar */
.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 1rem;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.change-note {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-amber-600);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-save {
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
    transition: background 0.2s;
}
.btn-save:hover {
    background: var(--hcis-indigo-600);
}
.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Pagination */
.pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.page-info {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}
.page-controls {
    display: flex;
    gap: 0.3rem;
}
.btn-page {
    width: 30px;
    height: 30px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 6px;
    background: #fff;
    color: var(--hcis-slate-500);
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.2s;
}
.btn-page:hover:not(:disabled) {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}
.btn-page:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--hcis-slate-400);
}
.empty-state i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 0.5rem;
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

@media (max-width: 900px) {
    .access-path-heading {
        flex-direction: column;
    }
    .path-summary {
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .access-path-section {
        padding: 0.75rem;
    }
    .role-identity {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .readiness-badge {
        margin-left: 42px;
    }
    .role-path-flow {
        grid-template-columns: 1fr;
    }
    .path-arrow {
        transform: rotate(90deg);
    }
    .toolbar-actions,
    .select-page {
        width: 100%;
    }
}
</style>
