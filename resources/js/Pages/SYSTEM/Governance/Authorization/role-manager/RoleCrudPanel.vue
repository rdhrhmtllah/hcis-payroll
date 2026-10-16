<template>
    <div class="role-crud-panel">
        <!-- Header + Add -->
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-shield-check me-2"></i>Master Roles</h6>
            <button class="btn-add" @click="openCreateModal">
                <i class="bi bi-plus-lg me-1"></i> Tambah Role
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loadingData" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <p class="text-muted small mt-2">Memuat data role...</p>
        </div>

        <!-- Role List by Module -->
        <div v-else>
            <div v-for="(roles, moduleName) in roleGroups" :key="moduleName" class="module-section">
                <div class="module-section-header">
                    <span class="module-label">{{ moduleName }}</span>
                    <span class="module-count">{{ roles.length }} role</span>
                </div>
                <div class="role-list">
                    <div v-for="role in roles" :key="role.id" class="role-item">
                        <div class="role-item-main">
                            <div>
                                <div class="role-item-name">{{ role.name }}</div>
                                <div class="role-item-code">{{ role.code }}</div>
                                <div class="role-item-desc" v-if="role.deskripsi">{{ role.deskripsi }}</div>
                            </div>
                            <div class="role-item-stats">
                                <span class="stat-chip"><i class="bi bi-people"></i> {{ role.user_count }}</span>
                                <span class="stat-chip"><i class="bi bi-funnel"></i> {{ role.scope_count }}</span>
                                <span class="stat-chip"><i class="bi bi-key"></i> {{ role.permission_count }}</span>
                            </div>
                        </div>
                        <div class="role-item-actions">
                            <button class="btn-icon-action" @click="viewRoleDetail(role)" title="Detail">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-icon-action" @click="openEditModal(role)" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-icon-action btn-icon-danger" @click="confirmDelete(role)" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="Object.keys(roleGroups).length === 0" class="empty-state">
                <i class="bi bi-shield-slash"></i>
                <p>Belum ada role. Klik "Tambah Role" untuk memulai.</p>
            </div>
        </div>

        <!-- Role Form Modal -->
        <RoleFormModal
            :show="showFormModal"
            :role="editingRole"
            :allowedModules="allowedModules"
            :isEdit="isEditing"
            @close="closeFormModal"
            @saved="onRoleSaved"
        />

        <!-- Role Detail Modal -->
        <Modal
            :show="showDetailModal"
            :title="detailRole?.name || 'Detail Role'"
            :subtitle="detailRole?.code || ''"
            size="large"
            @close="showDetailModal = false"
        >
            <template #body>
                <div v-if="loadingDetail" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>
                <div v-else-if="roleDetail">
                    <!-- Scopes -->
                    <div class="detail-section">
                        <h6 class="detail-section-title"><i class="bi bi-funnel me-1"></i> Scopes ({{ roleDetail.scopes.length }})</h6>
                        <div v-if="roleDetail.scopes.length === 0" class="text-muted small">Tidak ada scope terdaftar</div>
                        <div v-else class="detail-chips">
                            <span v-for="s in roleDetail.scopes" :key="s.Id_Scope_Definition" class="detail-chip chip-scope">
                                {{ s.Scope_Name }} <small class="text-muted">{{ s.Scope_Value }}</small>
                            </span>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class="detail-section">
                        <h6 class="detail-section-title"><i class="bi bi-key me-1"></i> Permissions ({{ roleDetail.permissions.length }})</h6>
                        <div v-if="roleDetail.permissions.length === 0" class="text-muted small">Tidak ada permission</div>
                        <div v-else class="detail-chips">
                            <span v-for="p in roleDetail.permissions" :key="p.Id_Role_Permission" class="detail-chip chip-perm">
                                {{ p.Permission_Code }}
                            </span>
                        </div>
                    </div>

                    <!-- Users -->
                    <div class="detail-section">
                        <h6 class="detail-section-title"><i class="bi bi-people me-1"></i> Users ({{ roleDetail.users.length }})</h6>
                        <div v-if="roleDetail.users.length === 0" class="text-muted small">Belum ada user yang diassign</div>
                        <div v-else class="detail-user-list">
                            <div v-for="u in roleDetail.users" :key="u.id" class="detail-user-item">
                                <span class="detail-user-name">{{ u.nama }}</span>
                                <span class="detail-user-code">{{ u.kode }}</span>
                                <small class="text-muted">{{ formatDate(u.Assigned_At) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal
            :show="showDeleteModal"
            title="Hapus Role"
            subtitle="Apakah Anda yakin ingin menghapus role ini?"
            size="small"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            confirmText="Hapus"
            :loading="deleting"
            @close="showDeleteModal = false"
            @confirm="executeDelete"
        >
            <template #body>
                <div class="delete-confirm-body">
                    <div class="delete-role-name">{{ deletingRole?.name }}</div>
                    <small class="text-muted">{{ deletingRole?.code }} — {{ deletingRole?.module }}</small>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import Modal from "@/components/legacy/modalV2-noir.vue";
import RoleFormModal from "./RoleFormModal.vue";

export default {
    name: "RoleCrudPanel",
    components: { Modal, RoleFormModal },
    props: {
        allowedModules: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
    },
    data() {
        return {
            loadingData: false,
            loadingDetail: false,
            deleting: false,
            roleGroups: {},
            showFormModal: false,
            editingRole: null,
            isEditing: false,
            showDetailModal: false,
            detailRole: null,
            roleDetail: null,
            showDeleteModal: false,
            deletingRole: null,
        };
    },
    methods: {
        async fetchRoles() {
            this.loadingData = true;
            try {
                const res = await fetch('/api/v1/admin/role-manager/roles');
                const data = await res.json();
                this.roleGroups = data.roles || {};
            } catch (e) { console.error(e); }
            finally { this.loadingData = false; }
        },

        openCreateModal() {
            this.isEditing = false;
            this.editingRole = null;
            this.showFormModal = true;
        },

        openEditModal(role) {
            this.isEditing = true;
            this.editingRole = { ...role };
            this.showFormModal = true;
        },

        closeFormModal() {
            this.showFormModal = false;
            this.editingRole = null;
        },

        onRoleSaved() {
            this.closeFormModal();
            this.fetchRoles();
        },

        async viewRoleDetail(role) {
            this.detailRole = role;
            this.showDetailModal = true;
            this.loadingDetail = true;
            try {
                const res = await fetch(`/api/v1/admin/role-manager/roles/${role.id}/detail`);
                this.roleDetail = await res.json();
            } catch (e) { console.error(e); }
            finally { this.loadingDetail = false; }
        },

        confirmDelete(role) {
            this.deletingRole = role;
            this.showDeleteModal = true;
        },

        async executeDelete() {
            if (!this.deletingRole) return;
            this.deleting = true;
            try {
                const res = await fetch(`/api/v1/admin/role-manager/roles/${this.deletingRole.id}`, {
                    method: 'DELETE',
                    headers: {},
                });
                const data = await res.json();
                if (!res.ok) { alert(data.message); return; }
                this.showDeleteModal = false;
                this.deletingRole = null;
                this.fetchRoles();
            } catch (e) { console.error(e); alert('Gagal menghapus role.'); }
            finally { this.deleting = false; }
        },

        formatDate(d) {
            if (!d) return '-';
            return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
    },
    mounted() { this.fetchRoles(); },
};
</script>

<style scoped>
.panel-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1rem;
}
.panel-subtitle {
    margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--hcis-slate-700);
    display: flex; align-items: center;
}
.btn-add {
    display: inline-flex; align-items: center; padding: 0.45rem 1rem;
    border: none; border-radius: 10px; background: var(--hcis-indigo-500); color: #fff;
    font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;
}
.btn-add:hover { background: var(--hcis-indigo-600); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }

.module-section { margin-bottom: 1.25rem; }
.module-section-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.5rem 0; border-bottom: 2px solid var(--hcis-slate-100); margin-bottom: 0.5rem;
}
.module-label { font-weight: 700; font-size: 0.85rem; color: var(--hcis-indigo-500); }
.module-count { font-size: 0.72rem; color: var(--hcis-slate-400); }

.role-list { display: flex; flex-direction: column; gap: 0.4rem; }
.role-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 1rem; border: 1px solid var(--hcis-slate-200); border-radius: 10px;
    background: #fff; transition: all 0.2s;
}
.role-item:hover { border-color: var(--hcis-indigo-200); background: #fafbff; }
.role-item-main { flex: 1; min-width: 0; }
.role-item-name { font-weight: 600; font-size: 0.82rem; color: var(--hcis-slate-800); }
.role-item-code { font-size: 0.7rem; color: var(--hcis-slate-400); font-family: monospace; }
.role-item-desc { font-size: 0.72rem; color: var(--hcis-slate-500); margin-top: 0.2rem; }
.role-item-stats { display: flex; gap: 0.4rem; margin-top: 0.3rem; }
.stat-chip {
    font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 6px;
    background: var(--hcis-slate-100); color: var(--hcis-slate-500); display: inline-flex; align-items: center; gap: 0.2rem;
}
.role-item-actions { display: flex; gap: 0.3rem; margin-left: 0.75rem; }
.btn-icon-action {
    width: 30px; height: 30px; border: 1px solid var(--hcis-slate-200); border-radius: 8px;
    background: #fff; color: var(--hcis-slate-500); cursor: pointer; display: flex;
    align-items: center; justify-content: center; font-size: 0.8rem; transition: all 0.2s;
}
.btn-icon-action:hover { background: var(--hcis-indigo-500); color: #fff; border-color: var(--hcis-indigo-500); }
.btn-icon-danger:hover { background: var(--hcis-red-500); border-color: var(--hcis-red-500); }

.empty-state { text-align: center; padding: 2rem; color: var(--hcis-slate-400); }
.empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }

/* Detail Modal styles */
.detail-section { margin-bottom: 1.25rem; }
.detail-section-title { font-size: 0.85rem; font-weight: 600; color: var(--hcis-slate-700); margin: 0 0 0.5rem; display: flex; align-items: center; }
.detail-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.detail-chip { padding: 0.2rem 0.6rem; border-radius: 8px; font-size: 0.75rem; font-weight: 600; }
.chip-scope { background: var(--hcis-emerald-50); color: var(--hcis-emerald-600); }
.chip-perm { background: #eff6ff; color: var(--hcis-blue-600); }
.detail-user-list { display: flex; flex-direction: column; gap: 0.3rem; }
.detail-user-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.4rem 0.5rem; border-radius: 6px; }
.detail-user-item:hover { background: var(--hcis-slate-50); }
.detail-user-name { font-weight: 600; font-size: 0.8rem; color: var(--hcis-slate-800); }
.detail-user-code { font-size: 0.72rem; color: var(--hcis-slate-400); font-family: monospace; }

.delete-confirm-body { text-align: center; padding: 1rem; }
.delete-role-name { font-weight: 700; font-size: 1.1rem; color: var(--hcis-red-500); margin-bottom: 0.25rem; }

@media (max-width: 768px) {
    .role-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    .role-item-actions { margin-left: 0; }
}
</style>
