<template>
    <div class="perm-crud-panel">
        <!-- Header -->
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-key me-2"></i>Permission Codes</h6>
        </div>

        <div v-if="loadingData" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else>
            <p class="info-note mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Permission codes dibuat saat membuat/mengedit Role. Daftar ini menampilkan semua permission yang terdaftar dan role mana yang menggunakannya.
            </p>

            <div v-for="perm in permissions" :key="perm.code" class="perm-item">
                <div class="perm-main">
                    <div class="perm-code">{{ perm.code }}</div>
                    <div class="perm-usage">
                        <span class="usage-label">Dipakai oleh {{ perm.usage_count }} role:</span>
                        <span v-for="r in perm.used_by_roles.slice(0, 5)" :key="r.role_code" class="usage-chip">
                            {{ r.role_name }}
                        </span>
                        <span v-if="perm.used_by_roles.length > 5" class="usage-chip usage-more">
                            +{{ perm.used_by_roles.length - 5 }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="permissions.length === 0" class="empty-state">
                <i class="bi bi-key"></i>
                <p>Belum ada permission terdaftar. Buat role terlebih dahulu dan assign permission.</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "PermissionCrudPanel",
    data() {
        return { permissions: [], loadingData: false };
    },
    methods: {
        async fetchPermissions() {
            this.loadingData = true;
            try {
                const res = await fetch('/api/v1/admin/role-manager/permissions-list');
                const data = await res.json();
                this.permissions = data.permissions || [];
            } catch (e) { console.error(e); }
            finally { this.loadingData = false; }
        },
    },
    mounted() { this.fetchPermissions(); },
};
</script>

<style scoped>
.panel-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.panel-subtitle { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--hcis-slate-700); display: flex; align-items: center; }
.info-note { font-size: 0.78rem; color: var(--hcis-slate-500); padding: 0.6rem 0.85rem; background: var(--hcis-slate-50); border-radius: 8px; border: 1px solid var(--hcis-slate-200); }

.perm-item { padding: 0.75rem 1rem; border: 1px solid var(--hcis-slate-200); border-radius: 10px; margin-bottom: 0.4rem; background: #fff; transition: all 0.2s; }
.perm-item:hover { border-color: #bfdbfe; background: #eff6ff; }
.perm-code { font-weight: 700; font-size: 0.85rem; color: var(--hcis-blue-600); font-family: monospace; }
.perm-usage { display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; margin-top: 0.3rem; }
.usage-label { font-size: 0.7rem; color: var(--hcis-slate-400); margin-right: 0.2rem; }
.usage-chip { font-size: 0.65rem; padding: 0.12rem 0.4rem; border-radius: 4px; background: var(--hcis-indigo-50); color: var(--hcis-indigo-500); font-weight: 500; }
.usage-more { background: var(--hcis-slate-100); color: var(--hcis-slate-500); }

.empty-state { text-align: center; padding: 2rem; color: var(--hcis-slate-400); }
.empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
</style>
