<template>
    <Modal
        :show="show"
        :title="isEdit ? 'Edit Role' : 'Tambah Role Baru'"
        :subtitle="isEdit ? `Editing: ${role?.code}` : 'Buat role fungsional baru'"
        size="medium"
        :showFooter="true"
        :showCancel="true"
        :showConfirm="true"
        :confirmText="isEdit ? 'Simpan Perubahan' : 'Buat Role'"
        :confirmDisabled="!isFormValid"
        :loading="saving"
        loadingText="Menyimpan..."
        @close="$emit('close')"
        @confirm="save"
    >
        <template #body>
            <!-- Role Code -->
            <div class="form-group">
                <label class="form-label">Role Code <span class="required">*</span></label>
                <input v-model="form.Role_Code" type="text" class="form-input" placeholder="e.g. HCIS_ADMIN_OPS" />
                <small class="form-hint">Kode unik untuk role, gunakan UPPER_SNAKE_CASE</small>
            </div>

            <!-- Role Name -->
            <div class="form-group">
                <label class="form-label">Role Name <span class="required">*</span></label>
                <input
                    v-model="form.Role_Name"
                    type="text"
                    class="form-input"
                    placeholder="e.g. Admin Operasional HCIS"
                />
            </div>

            <!-- Module -->
            <div class="form-group" v-if="!isEdit">
                <label class="form-label">Modul <span class="required">*</span></label>
                <select v-model="form.Id_Grup_Fitur" class="form-input">
                    <option value="">-- Pilih Modul --</option>
                    <option v-for="m in allowedModules" :key="m.id" :value="m.id">
                        {{ m.kode || m.nama }}
                    </option>
                </select>
            </div>

            <!-- Deskripsi -->
            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea
                    v-model="form.Deskripsi"
                    class="form-input form-textarea"
                    rows="2"
                    placeholder="Deskripsi singkat role ini..."
                ></textarea>
            </div>

            <!-- Scopes -->
            <div class="form-group">
                <label class="form-label">Data Scopes</label>
                <div class="checkbox-grid" v-if="allScopes.length > 0">
                    <label v-for="s in allScopes" :key="s.id" class="checkbox-item">
                        <input type="checkbox" :value="s.id" v-model="form.scopeIds" />
                        <span class="checkbox-label">
                            <strong>{{ s.name }}</strong>
                            <small>{{ s.value }}</small>
                        </span>
                    </label>
                </div>
                <small v-else class="text-muted">Belum ada scope terdaftar</small>
            </div>

            <!-- Permissions -->
            <div class="form-group">
                <label class="form-label">Permissions</label>
                <div class="tag-input-area">
                    <div class="tag-list">
                        <span v-for="(code, idx) in form.permissionCodes" :key="idx" class="tag-item">
                            {{ code }}
                            <button class="tag-remove" @click="removePermission(idx)">×</button>
                        </span>
                    </div>
                    <div class="tag-input-row">
                        <input
                            v-model="newPermission"
                            type="text"
                            class="form-input"
                            placeholder="e.g. ACCESS_ADMIN_PANEL"
                            @keydown.enter.prevent="addPermission"
                        />
                        <button class="btn-add-tag" @click="addPermission" :disabled="!newPermission.trim()">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from '@/components/legacy/modalV2-noir.vue';

export default {
    name: 'RoleFormModal',
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
        role: { type: Object, default: null },
        allowedModules: { type: Array, default: () => [] },
        isEdit: { type: Boolean, default: false },
    },
    emits: ['close', 'saved'],
    data() {
        return {
            saving: false,
            allScopes: [],
            newPermission: '',
            form: {
                Role_Code: '',
                Role_Name: '',
                Deskripsi: '',
                Id_Grup_Fitur: '',
                scopeIds: [],
                permissionCodes: [],
            },
        };
    },
    computed: {
        isFormValid() {
            return this.form.Role_Code.trim() && this.form.Role_Name.trim() && (this.isEdit || this.form.Id_Grup_Fitur);
        },
    },
    watch: {
        show(val) {
            if (val) {
                this.fetchScopes();
                if (this.isEdit && this.role) {
                    this.form = {
                        Role_Code: this.role.code || '',
                        Role_Name: this.role.name || '',
                        Deskripsi: this.role.deskripsi || '',
                        Id_Grup_Fitur: this.role.module_id || '',
                        scopeIds: [],
                        permissionCodes: [],
                    };
                    this.loadRoleDetail();
                } else {
                    this.resetForm();
                }
            }
        },
    },
    methods: {
        resetForm() {
            this.form = {
                Role_Code: '',
                Role_Name: '',
                Deskripsi: '',
                Id_Grup_Fitur: '',
                scopeIds: [],
                permissionCodes: [],
            };
            this.newPermission = '';
        },

        async fetchScopes() {
            try {
                const res = await fetch('/api/v1/admin/role-manager/scopes');
                const data = await res.json();
                this.allScopes = data.scopes || [];
            } catch (e) {
                console.error(e);
            }
        },

        async loadRoleDetail() {
            if (!this.role?.id) return;
            try {
                const res = await fetch(`/api/v1/admin/role-manager/roles/${this.role.id}/detail`);
                const data = await res.json();

                // Normalize scope ids from various payload shapes
                this.form.scopeIds = (data.scopes || [])
                    .map((s) => Number(s.id ?? s.Id_Scope ?? s.ID_SCOPE ?? s.Id_Scope_Definition))
                    .filter((id) => Number.isFinite(id));

                // Normalize permission code keys from various payload shapes
                this.form.permissionCodes = (data.permissions || [])
                    .map((p) => (p.Permission_Code ?? p.permission_code ?? p.PERMISSION_CODE ?? '').toString().trim())
                    .filter((code) => code.length > 0);
            } catch (e) {
                console.error(e);
            }
        },

        addPermission() {
            const code = this.newPermission.trim().toUpperCase().replace(/\s+/g, '_');
            if (code && !this.form.permissionCodes.includes(code)) {
                this.form.permissionCodes.push(code);
                this.newPermission = '';
            }
        },

        removePermission(idx) {
            this.form.permissionCodes.splice(idx, 1);
        },

        async save() {
            if (!this.isFormValid) return;
            this.saving = true;
            try {
                const url = this.isEdit
                    ? `/api/v1/admin/role-manager/roles/${this.role.id}`
                    : '/api/v1/admin/role-manager/roles';

                const res = await fetch(url, {
                    method: this.isEdit ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });

                const data = await res.json();
                if (!res.ok) {
                    alert(data.message || 'Gagal.');
                    return;
                }
                this.$emit('saved');
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan.');
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
.form-label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--hcis-slate-700);
    margin-bottom: 0.3rem;
}
.required {
    color: var(--hcis-red-500);
}
.form-input {
    width: 100%;
    padding: 0.6rem 0.85rem;
    border: 2px solid var(--hcis-slate-200);
    border-radius: 10px;
    font-size: 0.85rem;
    transition: all 0.2s;
    background: #fff;
}
.form-input:focus {
    outline: none;
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.form-textarea {
    resize: vertical;
    min-height: 60px;
}
.form-hint {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    margin-top: 0.2rem;
    display: block;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.4rem;
}
.checkbox-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}
.checkbox-item:hover {
    border-color: var(--hcis-indigo-200);
    background: #fafbff;
}
.checkbox-item input {
    margin-top: 0.15rem;
}
.checkbox-label {
    display: flex;
    flex-direction: column;
}
.checkbox-label strong {
    font-size: 0.78rem;
    color: var(--hcis-slate-800);
}
.checkbox-label small {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
}

.tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-bottom: 0.4rem;
}
.tag-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}
.tag-remove {
    background: none;
    border: none;
    color: var(--hcis-indigo-500);
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
    line-height: 1;
}
.tag-remove:hover {
    color: var(--hcis-red-500);
}
.tag-input-row {
    display: flex;
    gap: 0.4rem;
}
.btn-add-tag {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    flex-shrink: 0;
    border: 2px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: #fff;
    color: var(--hcis-indigo-500);
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
}
.btn-add-tag:hover:not(:disabled) {
    background: var(--hcis-indigo-500);
    color: #fff;
    border-color: var(--hcis-indigo-500);
}
.btn-add-tag:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
</style>
