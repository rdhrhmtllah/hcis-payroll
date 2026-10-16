<template>
    <Modal
        :show="show"
        :title="`Kelola Role — ${user?.nama || ''}`"
        :subtitle="`${user?.kode || ''} • ${user?.divisi || '-'}`"
        size="large"
        :loading="saving"
        loadingText="Menyimpan role..."
        :showFooter="true"
        :showCancel="true"
        :showConfirm="true"
        cancelText="Batal"
        confirmText="Simpan Perubahan"
        :confirmDisabled="!hasChanges"
        @close="$emit('close')"
        @confirm="saveRoles"
    >
        <template #body>
            <!-- Loading -->
            <div v-if="loading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 small">Memuat data role...</p>
            </div>

            <!-- Role Assignment -->
            <div v-else>
                <!-- Current status -->
                <div class="current-status-bar mb-3">
                    <span class="status-badge" :class="`status-${user?.status}`">
                        {{
                            user?.status === 'migrated'
                                ? '✅ Migrated'
                                : user?.status === 'legacy'
                                  ? '⚠️ Legacy'
                                  : '➖ Belum Ada'
                        }}
                    </span>
                    <span class="change-indicator" v-if="hasChanges">
                        <i class="bi bi-pencil-fill"></i> Ada perubahan belum disimpan
                    </span>
                </div>

                <div class="assigned-roles-summary mb-3">
                    <div class="assigned-roles-header">
                        <span class="assigned-roles-title">Role Saat Ini</span>
                        <span class="assigned-roles-count">{{ selectedRoleChips.length }} role</span>
                    </div>
                    <div v-if="selectedRoleChips.length > 0" class="assigned-role-chips">
                        <span v-for="role in selectedRoleChips" :key="`assigned-${role.id}`" class="assigned-role-chip">
                            {{ role.module }} • {{ role.code }}
                        </span>
                    </div>
                    <div v-else class="text-muted small">Belum ada role yang diberikan</div>
                </div>

                <!-- Role Groups by Module -->
                <div v-for="(roles, moduleName) in roleGroups" :key="moduleName" class="module-role-group">
                    <div class="module-group-header">
                        <div class="module-group-title">
                            <i :class="getModuleIcon(moduleName)"></i>
                            <span>{{ moduleName }}</span>
                        </div>
                        <span class="module-group-count"> {{ getAssignedCount(roles) }}/{{ roles.length }} </span>
                    </div>

                    <div class="role-grid">
                        <div
                            v-for="role in roles"
                            :key="role.id"
                            class="role-card"
                            :class="{ 'role-selected': selectedRoleIds.includes(role.id) }"
                            @click="toggleRole(role.id)"
                        >
                            <div class="role-card-header">
                                <div class="role-checkbox">
                                    <i
                                        :class="
                                            selectedRoleIds.includes(role.id)
                                                ? 'bi bi-check-circle-fill text-primary'
                                                : 'bi bi-circle'
                                        "
                                    ></i>
                                </div>
                                <div class="role-info">
                                    <div class="role-name">{{ role.name }}</div>
                                    <div class="role-code">{{ role.code }}</div>
                                </div>
                            </div>

                            <div class="role-description" v-if="role.deskripsi">
                                {{ role.deskripsi }}
                            </div>

                            <div class="role-meta">
                                <span v-if="role.scopes && role.scopes.length > 0" class="meta-badge meta-scope">
                                    <i class="bi bi-funnel"></i> {{ role.scopes.length }} scope
                                </span>
                                <span
                                    v-if="role.permissions && role.permissions.length > 0"
                                    class="meta-badge meta-perm"
                                >
                                    <i class="bi bi-key"></i> {{ role.permissions.length }} permission
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(roleGroups).length === 0" class="empty-roles text-center py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem; color: #94a3b8"></i>
                    <p class="text-muted mt-2">Tidak ada role yang tersedia</p>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from '@/components/legacy/modalV2-noir.vue';

export default {
    name: 'UserRoleDetailPanel',
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
        user: { type: Object, default: null },
        allowedModules: { type: Array, default: () => [] },
    },
    emits: ['close', 'save'],
    data() {
        return {
            loading: false,
            saving: false,
            roleGroups: {},
            selectedRoleIds: [],
            originalRoleIds: [],
        };
    },
    computed: {
        hasChanges() {
            const current = [...this.selectedRoleIds].sort();
            const original = [...this.originalRoleIds].sort();
            return JSON.stringify(current) !== JSON.stringify(original);
        },
        selectedRoleChips() {
            const selectedIds = new Set(this.selectedRoleIds.map((id) => Number(id)));
            const chips = [];

            Object.entries(this.roleGroups || {}).forEach(([moduleName, roles]) => {
                (roles || []).forEach((role) => {
                    if (selectedIds.has(Number(role.id))) {
                        chips.push({
                            id: Number(role.id),
                            code: role.code || '-',
                            name: role.name || '-',
                            module: moduleName,
                        });
                    }
                });
            });

            return chips.sort((a, b) => a.module.localeCompare(b.module) || a.code.localeCompare(b.code));
        },
    },
    watch: {
        show(val) {
            if (val && this.user) {
                this.fetchUserRoles();
            }
        },
    },
    mounted() {
        // Component is v-if rendered, so show is already true when mounted
        if (this.show && this.user) {
            this.fetchUserRoles();
        }
    },
    methods: {
        normalizeRoleGroups(rawGroups) {
            const groups = rawGroups || {};
            return Object.fromEntries(
                Object.entries(groups).map(([moduleName, roles]) => [
                    moduleName,
                    (roles || []).map((role) => ({
                        ...role,
                        id: Number(role.id),
                    })),
                ]),
            );
        },

        normalizeAssignedIds(rawAssignedIds) {
            if (Array.isArray(rawAssignedIds)) {
                return rawAssignedIds.map((id) => Number(id));
            }

            if (rawAssignedIds && typeof rawAssignedIds === 'object') {
                return Object.values(rawAssignedIds).map((id) => Number(id));
            }

            return [];
        },

        async fetchUserRoles() {
            if (!this.user?.id) return;
            this.loading = true;
            try {
                const res = await fetch(`/api/v1/admin/role-manager/user-roles/${this.user.id}`);
                const data = await res.json();

                if (!res.ok) {
                    console.error('API error:', data.message);
                    this.roleGroups = {};
                    return;
                }

                this.roleGroups = this.normalizeRoleGroups(data.roles);
                this.selectedRoleIds = this.normalizeAssignedIds(data.assignedIds);
                this.originalRoleIds = [...this.selectedRoleIds];
            } catch (e) {
                console.error('Failed to fetch user roles:', e);
            } finally {
                this.loading = false;
            }
        },

        toggleRole(roleId) {
            const normalizedRoleId = Number(roleId);
            const idx = this.selectedRoleIds.indexOf(normalizedRoleId);
            if (idx >= 0) {
                this.selectedRoleIds.splice(idx, 1);
            } else {
                this.selectedRoleIds.push(normalizedRoleId);
            }
        },

        async saveRoles() {
            if (!this.user?.id || !this.hasChanges) return;

            this.saving = true;
            try {
                const res = await fetch(`/api/v1/admin/role-manager/user-roles/${this.user.id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ roleIds: this.selectedRoleIds }),
                });

                const data = await res.json();
                if (res.ok) {
                    this.$emit('save', { userId: this.user.id, roleIds: this.selectedRoleIds });
                } else {
                    alert(data.message || 'Gagal menyimpan.');
                }
            } catch (e) {
                console.error('Failed to save roles:', e);
                alert('Terjadi kesalahan saat menyimpan.');
            } finally {
                this.saving = false;
            }
        },

        getAssignedCount(roles) {
            return roles.filter((r) => this.selectedRoleIds.includes(r.id)).length;
        },

        getModuleIcon(kode) {
            const icons = {
                HCIS: 'bi bi-building',
                KPI: 'bi bi-graph-up-arrow',
                LMS: 'bi bi-mortarboard',
                VISITOR: 'bi bi-person-badge',
            };
            return icons[kode] || 'bi bi-box';
        },
    },
};
</script>

<style scoped>
/* Current Status */
.current-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-slate-50);
    border-radius: 10px;
    border: 1px solid var(--hcis-slate-200);
}

.status-badge {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
}

.status-migrated {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.status-legacy {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
}
.status-none {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}

/* Assigned role chips */
.assigned-roles-summary {
    padding: 0.75rem 1rem;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
}

.assigned-roles-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.assigned-roles-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
}

.assigned-roles-count {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
}

.assigned-role-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    max-height: 120px;
    overflow-y: auto;
}

.assigned-role-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border: 1px solid var(--hcis-indigo-200);
}

.change-indicator {
    font-size: 0.75rem;
    color: var(--hcis-amber-500);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    animation: pulse 2s infinite;
}

/* Module Group */
.module-role-group {
    margin-bottom: 1.25rem;
}

.module-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.6rem;
    padding: 0.5rem 0;
    border-bottom: 2px solid var(--hcis-slate-100);
}

.module-group-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--hcis-slate-800);
}

.module-group-title i {
    color: var(--hcis-indigo-500);
}

.module-group-count {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
}

/* Role Grid */
.role-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 0.6rem;
}

.role-card {
    border: 2px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}

.role-card:hover {
    border-color: var(--hcis-indigo-200);
    background: #fafbff;
}

.role-selected {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.role-card-header {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
}

.role-checkbox i {
    font-size: 1.1rem;
    color: var(--hcis-slate-300);
    transition: color 0.2s;
}

.role-selected .role-checkbox i {
    color: var(--hcis-indigo-500);
}

.role-name {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--hcis-slate-800);
    line-height: 1.3;
}

.role-code {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    font-family: monospace;
}

.role-description {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    margin-top: 0.4rem;
    line-height: 1.4;
}

.role-meta {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

.meta-badge {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.meta-scope {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.meta-perm {
    background: #eff6ff;
    color: var(--hcis-blue-600);
}

/* Empty */
.empty-roles {
    color: var(--hcis-slate-400);
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

@media (max-width: 768px) {
    .role-grid {
        grid-template-columns: 1fr;
    }
    .current-status-bar {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
}
</style>
