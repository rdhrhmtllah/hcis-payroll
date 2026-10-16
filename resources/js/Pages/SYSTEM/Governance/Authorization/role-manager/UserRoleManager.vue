<template>
    <div class="user-role-manager">
        <div v-if="!canManageUsers" class="alert alert-warning py-2 px-3 mb-3">
            Anda tidak memiliki group kelolaan, sehingga perubahan role pengguna dinonaktifkan.
        </div>

        <!-- Filter Bar -->
        <ModernFilter
            :config="filterConfig"
            :filters="filters"
            :resultsCount="pagination.total"
            @filters-update="onFilterUpdate"
        />

        <!-- Users Table -->
        <ModernDataTable
            :columns="tableColumns"
            :data="users"
            :loading="loading"
            :showPagination="true"
            :currentPage="pagination.current_page"
            :totalPages="pagination.last_page"
            :totalData="pagination.total"
            :fullPagination="pagination"
            rowKey="id"
            emptyMessage="Tidak ada user ditemukan. Coba ubah filter pencarian."
            @page-change="onPageChange"
            @row-click="$emit('manage-user', $event)"
            :clickable="canManageUsers"
        >
            <!-- Kolom Nama -->
            <template #column-nama="{ item }">
                <div class="user-info-cell">
                    <div class="user-avatar" :class="getAvatarClass(item.status)">
                        {{ getInitials(item.nama) }}
                    </div>
                    <div>
                        <div class="user-name">{{ item.nama }}</div>
                        <small class="user-code text-muted">{{ item.kode || '-' }}</small>
                    </div>
                </div>
            </template>

            <!-- Kolom Status -->
            <template #column-status="{ item }">
                <span class="status-badge" :class="`status-${item.status}`">
                    <i :class="getStatusIcon(item.status)"></i>
                    {{ getStatusLabel(item.status) }}
                </span>
            </template>

            <!-- Kolom Roles -->
            <template #column-roles="{ item }">
                <div class="role-tags" v-if="item.roles && item.roles.length > 0">
                    <span v-for="(role, idx) in item.roles" :key="idx" class="role-tag">{{
                        role.name || role.code
                    }}</span>
                </div>
                <span v-else class="text-muted small">Belum ada role</span>
            </template>

            <!-- Kolom Aksi -->
            <template #column-actions="{ item }">
                <button class="btn-manage" @click.stop="openUserDetail(item)" :disabled="!canManageUsers">
                    <i class="bi bi-gear me-1"></i> Kelola
                </button>
            </template>
        </ModernDataTable>

        <!-- User Detail Panel (Modal) -->
        <UserRoleDetailPanel
            v-if="selectedUser"
            :show="showDetailPanel"
            :user="selectedUser"
            :allowedModules="allowedModules"
            @close="closeDetailPanel"
            @save="onSaveRoles"
        />
    </div>
</template>

<script>
import ModernDataTable from '@/components/legacy/table-noir.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import UserRoleDetailPanel from './UserRoleDetailPanel.vue';

export default {
    name: 'UserRoleManager',
    components: { ModernDataTable, ModernFilter, UserRoleDetailPanel },
    props: {
        allowedModules: { type: Array, default: () => [] },
        showModuleFilter: { type: Boolean, default: false },
        scope: { type: Object, default: () => ({}) },
    },
    emits: ['manage-user', 'save-roles'],
    data() {
        return {
            users: [],
            loading: false,
            filters: {
                search: '',
                module: '',
                status: '',
            },
            pagination: {
                current_page: 1,
                last_page: 1,
                total: 0,
                prev_page_url: null,
                next_page_url: null,
            },
            selectedUser: null,
            showDetailPanel: false,
            tableColumns: [
                { key: 'nama', title: 'Nama', icon: 'bi bi-person', showCount: true },
                { key: 'divisi', title: 'Divisi', icon: 'bi bi-building' },
                { key: 'status', title: 'Status', icon: 'bi bi-shield', align: 'center', width: '140px' },
                { key: 'roles', title: 'Roles', icon: 'bi bi-key' },
                { key: 'actions', title: 'Aksi', align: 'center', width: '120px' },
            ],
        };
    },
    computed: {
        canManageUsers() {
            if (this.scope?.isSystemAdmin) {
                return true;
            }
            return Array.isArray(this.scope?.manageableGroupIds) && this.scope.manageableGroupIds.length > 0;
        },
        filterConfig() {
            const filters = [];

            if (this.showModuleFilter && this.allowedModules.length > 1) {
                filters.push({
                    key: 'module',
                    label: 'Modul',
                    type: 'select',
                    col: 6,
                    placeholder: 'Semua Modul',
                    clearable: true,
                    options: this.allowedModules.map((m) => ({
                        value: m.id,
                        label: m.kode || m.nama,
                    })),
                });
            }

            filters.push({
                key: 'status',
                label: 'Status Migrasi',
                type: 'select',
                col: 6,
                placeholder: 'Semua Status',
                clearable: true,
                options: [
                    { value: 'migrated', label: '✅ Migrated' },
                    { value: 'legacy', label: '⚠️ Legacy Only' },
                    { value: 'none', label: '➖ Belum Ada Akses' },
                ],
            });

            return {
                searchable: true,
                searchPlaceholder: 'Cari nama atau kode karyawan...',
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                showResultsCount: true,
                filters,
            };
        },
    },
    methods: {
        async fetchUsers(page = 1) {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    page: page,
                    search: this.filters.search || '',
                });
                if (this.filters.module) params.append('module', this.filters.module);
                if (
                    !this.scope?.isSystemAdmin &&
                    Array.isArray(this.scope?.manageableGroupIds) &&
                    this.scope.manageableGroupIds.length
                ) {
                    params.append('manageable_groups', this.scope.manageableGroupIds.join(','));
                }

                const response = await fetch(`/api/v1/admin/role-manager/users?${params}`);
                const data = await response.json();

                let users = data.data || [];

                // Client-side status filter
                if (this.filters.status) {
                    users = users.filter((u) => u.status === this.filters.status);
                }

                this.users = users;
                this.pagination = data.pagination || this.pagination;
            } catch (e) {
                console.error('Failed to fetch users:', e);
            } finally {
                this.loading = false;
            }
        },

        onFilterUpdate(newFilters) {
            this.filters = { ...newFilters };
            this.fetchUsers(1);
        },

        onPageChange(url) {
            if (typeof url === 'string' && url.includes('page=')) {
                const pageMatch = url.match(/page=(\d+)/);
                if (pageMatch) this.fetchUsers(parseInt(pageMatch[1]));
            } else if (typeof url === 'number') {
                this.fetchUsers(url);
            }
        },

        openUserDetail(user) {
            if (!this.canManageUsers) {
                return;
            }
            this.selectedUser = user;
            this.showDetailPanel = true;
        },

        closeDetailPanel() {
            this.showDetailPanel = false;
            this.selectedUser = null;
        },

        async onSaveRoles(payload) {
            this.$emit('save-roles', payload);
            this.closeDetailPanel();
            await this.fetchUsers(this.pagination.current_page);
        },

        getInitials(name) {
            if (!name) return '?';
            return name
                .split(' ')
                .map((n) => n[0])
                .slice(0, 2)
                .join('')
                .toUpperCase();
        },

        getAvatarClass(status) {
            return {
                'avatar-migrated': status === 'migrated',
                'avatar-legacy': status === 'legacy',
                'avatar-none': status === 'none',
            };
        },

        getStatusIcon(status) {
            const icons = {
                migrated: 'bi bi-check-circle-fill',
                legacy: 'bi bi-exclamation-triangle-fill',
                none: 'bi bi-dash-circle',
            };
            return icons[status] || 'bi bi-question-circle';
        },

        getStatusLabel(status) {
            const labels = { migrated: 'Migrated', legacy: 'Legacy', none: 'No Access' };
            return labels[status] || status;
        },
    },
    mounted() {
        this.fetchUsers();
    },
};
</script>

<style scoped>
.user-role-manager {
    animation: fadeIn 0.3s ease;
}

.user-info-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

.avatar-migrated {
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
}
.avatar-legacy {
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
}
.avatar-none {
    background: linear-gradient(135deg, var(--hcis-slate-400), var(--hcis-slate-500));
}

.user-name {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--hcis-slate-800);
    line-height: 1.3;
}

.user-code {
    font-size: 0.72rem;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
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

/* Role Tags */
.role-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.role-tag {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
}

.role-more {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}

/* Manage Button */
.btn-manage {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-manage:hover {
    background: var(--hcis-indigo-500);
    color: #fff;
    border-color: var(--hcis-indigo-500);
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

@media (max-width: 768px) {
    .role-tags {
        gap: 0.2rem;
    }
    .role-tag {
        font-size: 0.65rem;
        padding: 0.1rem 0.35rem;
    }
}
</style>
