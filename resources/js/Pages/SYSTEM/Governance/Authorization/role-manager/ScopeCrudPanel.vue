<template>
    <div class="scope-crud-panel">
        <!-- Header + Add -->
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-funnel me-2"></i>Data Scope Definitions</h6>
            <button class="btn-add" @click="openCreate">
                <i class="bi bi-plus-lg me-1"></i> Tambah Scope
            </button>
        </div>

        <div v-if="loadingData" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else>
            <div v-for="scope in scopes" :key="scope.id" class="scope-item">
                <div class="scope-main">
                    <div class="scope-name">{{ scope.name }}</div>
                    <div class="scope-meta">
                        <span class="scope-value" v-if="scope.value"><i class="bi bi-tag"></i> {{ scope.value }}</span>
                        <span class="scope-filter" v-if="scope.filter_column"><i class="bi bi-filter"></i> {{ scope.filter_column }}</span>
                    </div>
                    <div class="scope-desc" v-if="scope.deskripsi">{{ scope.deskripsi }}</div>
                    <div class="scope-usage" v-if="scope.used_by_roles && scope.used_by_roles.length > 0">
                        <span class="usage-label">Dipakai oleh:</span>
                        <span v-for="r in scope.used_by_roles.slice(0,5)" :key="r.Role_Code" class="usage-chip">{{ r.Role_Name }}</span>
                        <span v-if="scope.used_by_roles.length > 5" class="usage-chip usage-more">+{{ scope.used_by_roles.length - 5 }}</span>
                    </div>
                </div>
                <div class="scope-actions">
                    <button class="btn-icon-action" @click="openEdit(scope)" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn-icon-action btn-icon-danger" @click="confirmDelete(scope)" title="Hapus"><i class="bi bi-trash"></i></button>
                </div>
            </div>

            <div v-if="scopes.length === 0" class="empty-state">
                <i class="bi bi-funnel"></i>
                <p>Belum ada scope. Klik "Tambah Scope" untuk memulai.</p>
            </div>
        </div>

        <!-- Form Modal -->
        <Modal
            :show="showForm"
            :title="isEditing ? 'Edit Scope' : 'Tambah Scope Baru'"
            size="medium"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            :confirmText="isEditing ? 'Simpan' : 'Buat'"
            :confirmDisabled="!form.Scope_Name.trim()"
            :loading="saving"
            @close="showForm = false"
            @confirm="save"
        >
            <template #body>
                <div class="form-group">
                    <label class="form-label">Scope Name <span class="required">*</span></label>
                    <input v-model="form.Scope_Name" class="form-input" placeholder="e.g. Div_HR_Only" />
                </div>
                <div class="form-group">
                    <label class="form-label">Scope Value</label>
                    <input v-model="form.Scope_Value" class="form-input" placeholder="e.g. ID_Divisi = 5" />
                </div>
                <div class="form-group">
                    <label class="form-label">Filter Column</label>
                    <input v-model="form.Filter_Column" class="form-input" placeholder="e.g. ID_Divisi" />
                </div>
                <div class="form-group">
                    <label class="form-label">Custom SQL Condition</label>
                    <textarea v-model="form.Custom_Condition" class="form-input form-textarea" rows="2" placeholder="e.g. ID_Divisi IN (5, 6, 7)"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea v-model="form.Deskripsi" class="form-input form-textarea" rows="2" placeholder="Deskripsi scope..."></textarea>
                </div>
            </template>
        </Modal>

        <!-- Delete Confirmation -->
        <Modal
            :show="showDeleteModal"
            title="Hapus Scope"
            size="small"
            :showFooter="true" :showCancel="true" :showConfirm="true" confirmText="Hapus"
            :loading="deleting"
            @close="showDeleteModal = false"
            @confirm="executeDelete"
        >
            <template #body>
                <div class="delete-confirm-body">
                    <p>Hapus scope <strong>{{ deletingScope?.name }}</strong>?</p>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import Modal from "@/components/legacy/modalV2-noir.vue";

export default {
    name: "ScopeCrudPanel",
    components: { Modal },
    data() {
        return {
            scopes: [],
            loadingData: false, saving: false, deleting: false,
            showForm: false, isEditing: false, editingScopeId: null,
            showDeleteModal: false, deletingScope: null,
            form: { Scope_Name: '', Scope_Value: '', Filter_Column: '', Custom_Condition: '', Deskripsi: '' },
        };
    },
    methods: {
        async fetchScopes() {
            this.loadingData = true;
            try {
                const res = await fetch('/api/v1/admin/role-manager/scopes');
                const data = await res.json();
                this.scopes = data.scopes || [];
            } catch (e) { console.error(e); }
            finally { this.loadingData = false; }
        },
        openCreate() {
            this.isEditing = false;
            this.editingScopeId = null;
            this.form = { Scope_Name: '', Scope_Value: '', Filter_Column: '', Custom_Condition: '', Deskripsi: '' };
            this.showForm = true;
        },
        openEdit(scope) {
            this.isEditing = true;
            this.editingScopeId = scope.id;
            this.form = {
                Scope_Name: scope.name || '',
                Scope_Value: scope.value || '',
                Filter_Column: scope.filter_column || '',
                Custom_Condition: scope.custom_condition || '',
                Deskripsi: scope.deskripsi || '',
            };
            this.showForm = true;
        },
        async save() {
            this.saving = true;
            try {
                const url = this.isEditing ? `/api/v1/admin/role-manager/scopes/${this.editingScopeId}` : '/api/v1/admin/role-manager/scopes';
                const res = await fetch(url, {
                    method: this.isEditing ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) { alert(data.message); return; }
                this.showForm = false;
                this.fetchScopes();
            } catch (e) { console.error(e); alert('Gagal menyimpan scope.'); }
            finally { this.saving = false; }
        },
        confirmDelete(scope) { this.deletingScope = scope; this.showDeleteModal = true; },
        async executeDelete() {
            this.deleting = true;
            try {
                const res = await fetch(`/api/v1/admin/role-manager/scopes/${this.deletingScope.id}`, {
                    method: 'DELETE',
                    headers: {},
                });
                const data = await res.json();
                if (!res.ok) { alert(data.message); return; }
                this.showDeleteModal = false;
                this.fetchScopes();
            } catch (e) { console.error(e); }
            finally { this.deleting = false; }
        },
    },
    mounted() { this.fetchScopes(); },
};
</script>

<style scoped>
.panel-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.panel-subtitle { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--hcis-slate-700); display: flex; align-items: center; }
.btn-add { display: inline-flex; align-items: center; padding: 0.45rem 1rem; border: none; border-radius: 10px; background: var(--hcis-emerald-500); color: #fff; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
.btn-add:hover { background: var(--hcis-emerald-600); transform: translateY(-1px); }

.scope-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.85rem 1rem; border: 1px solid var(--hcis-slate-200); border-radius: 10px; margin-bottom: 0.4rem; background: #fff; transition: all 0.2s; }
.scope-item:hover { border-color: var(--hcis-emerald-200); background: var(--hcis-green-50); }
.scope-main { flex: 1; min-width: 0; }
.scope-name { font-weight: 600; font-size: 0.85rem; color: var(--hcis-slate-800); }
.scope-meta { display: flex; gap: 0.75rem; margin-top: 0.25rem; }
.scope-value, .scope-filter { font-size: 0.72rem; color: var(--hcis-slate-500); display: flex; align-items: center; gap: 0.25rem; }
.scope-desc { font-size: 0.72rem; color: var(--hcis-slate-400); margin-top: 0.25rem; }
.scope-usage { display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; margin-top: 0.35rem; }
.usage-label { font-size: 0.68rem; color: var(--hcis-slate-400); margin-right: 0.2rem; }
.usage-chip { font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 4px; background: var(--hcis-indigo-50); color: var(--hcis-indigo-500); font-weight: 500; }
.usage-more { background: var(--hcis-slate-100); color: var(--hcis-slate-500); }
.scope-actions { display: flex; gap: 0.3rem; margin-left: 0.75rem; flex-shrink: 0; }
.btn-icon-action { width: 30px; height: 30px; border: 1px solid var(--hcis-slate-200); border-radius: 8px; background: #fff; color: var(--hcis-slate-500); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; transition: all 0.2s; }
.btn-icon-action:hover { background: var(--hcis-emerald-500); color: #fff; border-color: var(--hcis-emerald-500); }
.btn-icon-danger:hover { background: var(--hcis-red-500); border-color: var(--hcis-red-500); }
.empty-state { text-align: center; padding: 2rem; color: var(--hcis-slate-400); }
.empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }

.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-weight: 600; font-size: 0.8rem; color: var(--hcis-slate-700); margin-bottom: 0.3rem; }
.required { color: var(--hcis-red-500); }
.form-input { width: 100%; padding: 0.6rem 0.85rem; border: 2px solid var(--hcis-slate-200); border-radius: 10px; font-size: 0.85rem; transition: all 0.2s; }
.form-input:focus { outline: none; border-color: var(--hcis-emerald-500); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
.form-textarea { resize: vertical; min-height: 60px; }
.delete-confirm-body { text-align: center; padding: 1rem; }
</style>
