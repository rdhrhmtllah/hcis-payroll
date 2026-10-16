<template>
    <div class="resource-access-panel">
        <div v-if="!canManageResourceAccess" class="alert alert-warning py-2 px-3 mb-3">
            Anda tidak memiliki group kelolaan untuk mengubah resource access.
        </div>

        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-shield-lock me-2"></i>Role Resource Access</h6>
            <button class="btn-add" @click="openCreate" :disabled="!canManageResourceAccess">
                <i class="bi bi-plus-lg me-1"></i> Tambah Resource
            </button>
        </div>

        <div class="filter-bar mb-3">
            <select v-model="filterContext" class="form-select form-select-sm" @change="fetchData(1)">
                <option :value="''">Semua Context</option>
                <option v-for="ctx in contexts" :key="ctx" :value="ctx">{{ ctx }}</option>
            </select>

            <select
                v-if="hasJenisPageColumn !== false"
                v-model="filterJenisPage"
                class="form-select form-select-sm"
                @change="fetchData(1)"
            >
                <option :value="''">Semua Page</option>
                <option v-for="menu in menus" :key="menu.Jenis_page" :value="menu.Jenis_page">
                    {{ menu.Nama_Menu }} ({{ menu.Jenis_page }})
                </option>
            </select>

            <select v-model="filterType" class="form-select form-select-sm" @change="fetchData(1)">
                <option :value="''">Semua Tipe</option>
                <option v-for="type in resourceTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                </option>
            </select>

            <select v-model="filterRole" class="form-select form-select-sm" @change="fetchData(1)">
                <option :value="''">Semua Role</option>
                <option v-for="role in roles" :key="role.Id_Role" :value="role.Id_Role">
                    {{ role.Role_Code }} — {{ role.Role_Name }}
                </option>
            </select>

            <input
                v-model="searchTerm"
                class="form-control form-control-sm"
                placeholder="Cari resource / role..."
                @keyup.enter="fetchData(1)"
            />

            <button class="btn-refresh" @click="fetchData(1)" :disabled="loading">
                <i class="bi bi-arrow-clockwise" :class="{ spin: loading }"></i>
            </button>
        </div>

        <div v-if="hasJenisPageColumn === false" class="alert alert-info py-2 px-3 mb-3">
            Kolom <strong>Jenis_page</strong> belum terdeteksi di table resource access. Panel tetap bisa dipakai,
            tetapi rekomendasi terbaik adalah menambahkan kolom tersebut agar resource bisa dipetakan ke page secara
            presisi.
        </div>

        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <p class="text-muted small mt-2">Memuat resource access...</p>
        </div>

        <div v-else>
            <div class="table-responsive resource-table-wrapper">
                <table class="table table-sm align-middle resource-table mb-0">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Context</th>
                            <th v-if="hasJenisPageColumn !== false">Page</th>
                            <th>Type</th>
                            <th>Resource Code</th>
                            <th>Resource Name</th>
                            <th class="text-center">Master</th>
                            <th class="text-center">Allow</th>
                            <th class="text-center">Active</th>
                            <th class="text-center">Sort</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.Id_Role_Resource_Access">
                            <td>
                                <div class="fw-semibold">{{ item.Role_Code }}</div>
                                <small class="text-muted">{{ item.Role_Name }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ item.Context_Code }}</span>
                            </td>
                            <td v-if="hasJenisPageColumn !== false">
                                <div class="fw-semibold">{{ item.Jenis_page || '-' }}</div>
                                <small class="text-muted">{{ item.menu_name || item.menu_header || '-' }}</small>
                            </td>
                            <td>
                                <span class="resource-type-pill">{{ item.Resource_Type }}</span>
                            </td>
                            <td>
                                <code class="resource-code">{{ item.Resource_Code }}</code>
                            </td>
                            <td>{{ item.Resource_Name || '-' }}</td>
                            <td class="text-center">
                                <span :class="item.master_registered ? 'badge bg-success' : 'badge bg-danger'">
                                    {{ item.master_registered ? 'Terdaftar' : 'Tidak Valid' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span :class="item.Flag_Diizinkan === 'Y' ? 'badge bg-success' : 'badge bg-secondary'">
                                    {{ item.Flag_Diizinkan }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span :class="item.Flag_Aktif === 'Y' ? 'badge bg-primary' : 'badge bg-secondary'">
                                    {{ item.Flag_Aktif }}
                                </span>
                            </td>
                            <td class="text-center">{{ item.Sort_Order ?? 0 }}</td>
                            <td class="text-end">
                                <button class="btn-icon-action me-1" @click="openEdit(item)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button
                                    class="btn-icon-action btn-icon-danger"
                                    @click="confirmDelete(item)"
                                    title="Hapus"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="items.length === 0">
                            <td :colspan="tableColspan" class="text-center text-muted py-4">
                                Tidak ada data resource access.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar mt-3" v-if="pagination.last_page > 1">
                <span class="page-info">
                    Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} • Total
                    {{ pagination.total }} data
                </span>
                <div class="page-controls">
                    <button
                        class="btn-page"
                        :disabled="pagination.current_page === 1"
                        @click="fetchData(pagination.current_page - 1)"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button
                        class="btn-page"
                        :disabled="pagination.current_page === pagination.last_page"
                        @click="fetchData(pagination.current_page + 1)"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <Modal
            :show="showForm"
            :title="editItem ? 'Edit Resource Access' : 'Tambah Resource Access'"
            size="large"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            :confirmText="editItem ? 'Simpan' : 'Buat'"
            :loading="saving"
            @close="showForm = false"
            @confirm="saveItem"
        >
            <template #body>
                <div class="resource-form-grid">
                    <div class="form-group">
                        <label class="form-label-sm">Role Functional</label>
                        <el-select
                            v-model="formData.Id_Role_Functional"
                            class="w-100"
                            filterable
                            placeholder="Pilih Role"
                        >
                            <el-option
                                v-for="role in roles"
                                :key="role.Id_Role"
                                :value="role.Id_Role"
                                :label="`${role.Role_Code} — ${role.Role_Name}`"
                            />
                        </el-select>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Context Code</label>
                        <input
                            v-model="formData.Context_Code"
                            class="form-control form-control-sm"
                            placeholder="Contoh: admin"
                        />
                    </div>

                    <div class="form-group" v-if="hasJenisPageColumn !== false">
                        <label class="form-label-sm">Jenis Page</label>
                        <el-select
                            v-model="formData.Jenis_page"
                            class="w-100"
                            filterable
                            clearable
                            placeholder="Pilih Jenis Page"
                        >
                            <el-option
                                v-for="menu in menus"
                                :key="menu.Jenis_page"
                                :value="menu.Jenis_page"
                                :label="`${menu.Header_Label || '-'} — ${menu.Nama_Menu} (${menu.Jenis_page})`"
                            />
                        </el-select>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Resource Type</label>
                        <el-select v-model="formData.Resource_Type" class="w-100" filterable placeholder="Pilih Type">
                            <el-option
                                v-for="type in resourceTypes"
                                :key="type.value"
                                :value="type.value"
                                :label="type.label"
                            />
                        </el-select>
                    </div>

                    <div class="form-group resource-code-wrap">
                        <label class="form-label-sm">Resource Code (Input Tag)</label>
                        <el-select
                            v-model="formData.Resource_Codes"
                            class="w-100"
                            multiple
                            filterable
                            default-first-option
                            clearable
                            placeholder="Pilih resource dari master"
                        >
                            <el-option
                                v-for="resource in availableResourceCodes"
                                :key="`${resource.resource_type}-${resource.resource_code}`"
                                :label="`${resource.resource_name} (${resource.resource_code})`"
                                :value="resource.resource_code"
                            />
                        </el-select>
                        <small class="text-muted">
                            Hanya resource yang terdaftar pada master authorization dapat disimpan.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Resource Name (Auto)</label>
                        <input
                            class="form-control form-control-sm"
                            :value="derivedResourceNamePreview"
                            readonly
                            placeholder="Auto dari resource code"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Flag Diizinkan</label>
                        <select v-model="formData.Flag_Diizinkan" class="form-select form-select-sm">
                            <option value="Y">Y</option>
                            <option value="T">T</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Flag Aktif</label>
                        <select v-model="formData.Flag_Aktif" class="form-select form-select-sm">
                            <option value="Y">Y</option>
                            <option value="T">T</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Sort Order</label>
                        <input
                            v-model.number="formData.Sort_Order"
                            type="number"
                            class="form-control form-control-sm"
                            min="0"
                        />
                    </div>
                </div>
            </template>
        </Modal>

        <Modal
            :show="showDelete"
            title="Hapus Resource Access"
            size="small"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            confirmText="Hapus"
            :loading="deleting"
            @close="showDelete = false"
            @confirm="executeDelete"
        >
            <template #body>
                <div class="text-center py-2">
                    <div class="delete-name">{{ deleteItem?.Resource_Code }}</div>
                    <small class="text-muted">
                        {{ deleteItem?.Role_Code }} • {{ deleteItem?.Context_Code }} • {{ deleteItem?.Resource_Type }}
                    </small>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import Modal from '@/components/legacy/modalV2-noir.vue';

export default {
    name: 'ResourceAccessPanel',
    components: { Modal },
    props: {
        scope: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            loading: false,
            saving: false,
            deleting: false,
            showForm: false,
            showDelete: false,
            editItem: null,
            deleteItem: null,
            items: [],
            roles: [],
            contexts: ['admin'],
            resourceTypes: [
                { value: 'tab', label: 'Tab' },
                { value: 'capability', label: 'Capability Aksi' },
                { value: 'page', label: 'Page' },
                { value: 'menu', label: 'Menu' },
                { value: 'feature', label: 'Feature' },
                { value: 'menu-delegation', label: 'Delegasi Menu' },
            ],
            menus: [],
            resourceRegistry: [],
            hasJenisPageColumn: true,
            filterContext: '',
            filterJenisPage: 'systemAuthManagerPage',
            filterType: '',
            filterRole: '',
            searchTerm: '',
            pagination: { current_page: 1, last_page: 1, total: 0, per_page: 20 },
            accessScope: null,
            formData: {
                id: null,
                Id_Role_Functional: null,
                Context_Code: 'admin',
                Jenis_page: 'systemAuthManagerPage',
                Resource_Type: 'tab',
                Resource_Code: '',
                Resource_Codes: [],
                Resource_Name: '',
                Flag_Diizinkan: 'Y',
                Flag_Aktif: 'Y',
                Sort_Order: 0,
            },
        };
    },
    computed: {
        canManageResourceAccess() {
            const effectiveScope = this.scope && Object.keys(this.scope).length ? this.scope : this.accessScope;
            if (effectiveScope?.isSystemAdmin) {
                return true;
            }
            return Array.isArray(effectiveScope?.manageableGroupIds) && effectiveScope.manageableGroupIds.length > 0;
        },
        tableColspan() {
            return this.hasJenisPageColumn === false ? 10 : 11;
        },
        derivedResourceNamePreview() {
            if (!Array.isArray(this.formData.Resource_Codes) || this.formData.Resource_Codes.length === 0) {
                return '';
            }

            return this.formData.Resource_Codes.map((code) => this.buildResourceNameFromCode(code))
                .filter(Boolean)
                .join(' | ');
        },
        availableResourceCodes() {
            const page = this.formData.Jenis_page || '';
            const type = this.formData.Resource_Type || '';

            if (['page', 'menu', 'menu-delegation'].includes(type)) {
                return this.menus.map((menu) => ({
                    resource_type: type,
                    resource_code: menu.Jenis_page,
                    resource_name: `${menu.Header_Label || '-'} — ${menu.Nama_Menu}`,
                }));
            }

            return this.resourceRegistry.filter(
                (resource) => resource.jenis_page === page && resource.resource_type === type,
            );
        },
    },
    methods: {
        emptyForm() {
            return {
                id: null,
                Id_Role_Functional: null,
                Context_Code: 'admin',
                Jenis_page: 'systemAuthManagerPage',
                Resource_Type: 'tab',
                Resource_Code: '',
                Resource_Codes: [],
                Resource_Name: '',
                Flag_Diizinkan: 'Y',
                Flag_Aktif: 'Y',
                Sort_Order: 0,
            };
        },
        async fetchFilters() {
            try {
                const res = await fetch('/api/v1/system-auth/resource-access/filters');
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Gagal memuat filter');
                }
                this.roles = data.roles || [];
                this.contexts = data.contexts?.length ? data.contexts : ['admin'];
                this.resourceTypes = data.resourceTypes || this.resourceTypes;
                this.menus = data.menus || [];
                this.resourceRegistry = data.resourceRegistry || [];
                this.hasJenisPageColumn = data.hasJenisPageColumn;
                this.accessScope = data.scope || null;
                if (!this.contexts.includes('admin')) {
                    this.contexts.unshift('admin');
                }
                if (!this.formData.Context_Code) {
                    this.formData.Context_Code = this.contexts[0] || 'admin';
                }
                if (this.hasJenisPageColumn !== false) {
                    const hasDefaultPage = this.menus.some((m) => m.Jenis_page === 'systemAuthManagerPage');
                    if (!this.filterJenisPage && hasDefaultPage) {
                        this.filterJenisPage = 'systemAuthManagerPage';
                    }
                    if (!this.formData.Jenis_page && hasDefaultPage) {
                        this.formData.Jenis_page = 'systemAuthManagerPage';
                    }
                }
            } catch (e) {
                console.error(e);
            }
        },
        buildQuery(page = 1) {
            const params = new URLSearchParams();
            params.set('page', String(page));
            params.set('per_page', String(this.pagination.per_page || 20));
            if (this.filterContext) params.set('context_code', this.filterContext);
            if (this.filterJenisPage) params.set('jenis_page', this.filterJenisPage);
            if (this.filterType) params.set('resource_type', this.filterType);
            if (this.filterRole) params.set('role_id', this.filterRole);
            if (this.searchTerm.trim()) params.set('search', this.searchTerm.trim());
            return params.toString();
        },
        async fetchData(page = 1) {
            this.loading = true;
            try {
                const res = await fetch(`/api/v1/system-auth/resource-access?${this.buildQuery(page)}`);
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Gagal memuat resource access');
                }
                this.items = data.data || [];
                this.pagination = data.pagination || this.pagination;
            } catch (e) {
                console.error(e);
                this.items = [];
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editItem = null;
            this.formData = this.emptyForm();
            this.formData.Context_Code = this.contexts[0] || 'admin';
            this.showForm = true;
        },
        openEdit(item) {
            this.editItem = item;
            this.formData = {
                id: item.Id_Role_Resource_Access,
                Id_Role_Functional: item.Id_Role_Functional,
                Context_Code: item.Context_Code || 'admin',
                Jenis_page: item.Jenis_page || '',
                Resource_Type: item.Resource_Type,
                Resource_Code: item.Resource_Code,
                Resource_Codes: [item.Resource_Code],
                Resource_Name: item.Resource_Name || '',
                Flag_Diizinkan: item.Flag_Diizinkan || 'Y',
                Flag_Aktif: item.Flag_Aktif || 'Y',
                Sort_Order: item.Sort_Order ?? 0,
            };
            this.showForm = true;
        },
        buildResourceNameFromCode(code) {
            return String(code || '')
                .trim()
                .replace(/\./g, ' ')
                .toUpperCase();
        },
        async saveItem() {
            this.saving = true;
            try {
                const url = this.editItem
                    ? `/api/v1/system-auth/resource-access/${this.editItem.Id_Role_Resource_Access}`
                    : '/api/v1/system-auth/resource-access';
                const method = this.editItem ? 'PUT' : 'POST';

                const codes = Array.isArray(this.formData.Resource_Codes)
                    ? this.formData.Resource_Codes.map((c) => String(c || '').trim()).filter(Boolean)
                    : [];

                const payload = {
                    ...this.formData,
                    Context_Code: String(this.formData.Context_Code || '').trim(),
                    Resource_Codes: codes,
                    Resource_Code: codes[0] || this.formData.Resource_Code || '',
                    Resource_Name: this.buildResourceNameFromCode(codes[0] || this.formData.Resource_Code || ''),
                };

                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message || 'Gagal menyimpan');
                    return;
                }
                this.showForm = false;
                await this.fetchData(this.pagination.current_page || 1);
            } catch (e) {
                console.error(e);
                alert('Gagal menyimpan resource access.');
            } finally {
                this.saving = false;
            }
        },
        confirmDelete(item) {
            this.deleteItem = item;
            this.showDelete = true;
        },
        async executeDelete() {
            if (!this.deleteItem) return;
            this.deleting = true;
            try {
                const res = await fetch(
                    `/api/v1/system-auth/resource-access/${this.deleteItem.Id_Role_Resource_Access}`,
                    {
                        method: 'DELETE',
                        headers: {},
                    },
                );
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message || 'Gagal menghapus');
                    return;
                }
                this.showDelete = false;
                await this.fetchData(this.pagination.current_page || 1);
            } catch (e) {
                console.error(e);
                alert('Gagal menghapus resource access.');
            } finally {
                this.deleting = false;
            }
        },
    },
    mounted() {
        this.fetchFilters();
        this.fetchData(1);
    },
};
</script>

<style scoped>
.resource-access-panel {
    animation: fadeIn 0.3s ease;
}

.panel-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.panel-subtitle {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}

.btn-add,
.btn-refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.45rem 0.9rem;
    border: none;
    border-radius: 10px;
    background: var(--hcis-indigo-500);
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add:hover,
.btn-refresh:hover {
    background: var(--hcis-indigo-600);
    transform: translateY(-1px);
}

.btn-add:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.filter-bar {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1.2fr 1.5fr auto;
    gap: 0.5rem;
}

.resource-table-wrapper {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.resource-table thead th {
    background: var(--hcis-slate-50);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    border-bottom: 1px solid var(--hcis-slate-200);
    white-space: nowrap;
}

.resource-table tbody td {
    font-size: 0.8rem;
    vertical-align: middle;
}

.resource-type-pill {
    display: inline-flex;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
}

.resource-code {
    font-size: 0.72rem;
    color: var(--hcis-slate-900);
    background: var(--hcis-slate-50);
    padding: 0.1rem 0.35rem;
    border-radius: 6px;
    word-break: break-word;
}

.btn-icon-action {
    width: 30px;
    height: 30px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-500);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.btn-icon-action:hover {
    background: var(--hcis-indigo-500);
    color: #fff;
    border-color: var(--hcis-indigo-500);
}

.btn-icon-danger:hover {
    background: var(--hcis-red-500);
    border-color: var(--hcis-red-500);
}

.resource-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.resource-code-wrap {
    grid-column: span 2;
}

.form-label-sm {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    margin-bottom: 0.25rem;
    display: block;
}

.delete-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--hcis-red-500);
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

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 992px) {
    .filter-bar {
        grid-template-columns: 1fr 1fr;
    }

    .resource-form-grid {
        grid-template-columns: 1fr;
    }

    .resource-code-wrap {
        grid-column: span 1;
    }
}

@media (max-width: 768px) {
    .filter-bar {
        grid-template-columns: 1fr;
    }
}
</style>
