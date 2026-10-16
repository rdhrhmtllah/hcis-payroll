<template>
    <div class="monitoring-panel">
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-activity me-2"></i>System Monitoring & Metrics</h6>
            <button class="btn-refresh" @click="fetchData" :disabled="loading">
                <i class="bi bi-arrow-clockwise" :class="{ spin: loading }"></i> Refresh
            </button>
        </div>

        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <p class="text-muted small mt-2">Memuat metrics...</p>
        </div>

        <div v-if="!loading" class="legacy-summary mb-4">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="summary-card summary-total">
                        <i class="bi bi-people-fill"></i>
                        <div>
                            <div class="summary-value">{{ dashboardUsers }}</div>
                            <div class="summary-label">Total User Terassign</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="summary-card summary-legacy">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <div class="summary-value">{{ legacyCount }}</div>
                            <div class="summary-label">User Legacy Only</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="summary-card summary-roles">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <div class="summary-value">{{ dashboardRoles }}</div>
                            <div class="summary-label">Total Role Aktif</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="activity-section">
                <div class="section-header">
                    <h5><i class="bi bi-clock-history me-2"></i>Aktivitas Role Terbaru</h5>
                </div>
                <div v-if="recentActivity.length === 0" class="activity-empty">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada aktivitas</p>
                </div>
                <div v-else class="activity-list">
                    <div v-for="(activity, idx) in recentActivity" :key="idx" class="activity-item">
                        <div
                            class="activity-indicator"
                            :class="activity.Flag_Aktif === 'Y' ? 'active' : 'inactive'"
                        ></div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong>{{ activity.user_nama || 'Unknown' }}</strong>
                                <span :class="activity.Flag_Aktif === 'Y' ? 'text-success' : 'text-danger'">
                                    {{ activity.Flag_Aktif === 'Y' ? 'assigned to' : 'removed from' }}
                                </span>
                                <span class="activity-role-badge">{{ activity.Role_Name }}</span>
                            </div>
                            <small class="activity-time text-muted">{{ formatDate(activity.Assigned_At) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="totals" class="metrics-grid mb-4">
            <div class="metric-card">
                <div class="metric-icon bg-blue"><i class="bi bi-people-fill"></i></div>
                <div class="metric-info">
                    <div class="metric-value">{{ totals.total_users_with_access }}</div>
                    <div class="metric-label">Active Users</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon bg-green"><i class="bi bi-diagram-3"></i></div>
                <div class="metric-info">
                    <div class="metric-value">{{ totals.total_page_access }}</div>
                    <div class="metric-label">Access Granted</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon bg-purple"><i class="bi bi-grid"></i></div>
                <div class="metric-info">
                    <div class="metric-value">{{ totals.total_menus }}</div>
                    <div class="metric-label">Menus Mapped</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon bg-orange"><i class="bi bi-toggles"></i></div>
                <div class="metric-info">
                    <div class="metric-value">{{ totals.total_capabilities }}</div>
                    <div class="metric-label">Capabilities Setup</div>
                </div>
            </div>
        </div>

        <div class="row g-3" v-if="!loading && totals">
            <!-- Menus Stats -->
            <div class="col-md-6 mb-3">
                <div class="stat-box">
                    <h6 class="stat-title"><i class="bi bi-star-fill text-warning me-2"></i>Top Menus by Access</h6>
                    <div class="stat-list">
                        <div v-for="ms in menuStats.slice(0, 5)" :key="ms.Jenis_page" class="stat-item">
                            <div class="stat-item-main">
                                <span class="stat-name">{{ ms.Nama_Menu }}</span>
                                <span class="stat-page">{{ ms.Jenis_page }}</span>
                            </div>
                            <div class="stat-item-values">
                                <span class="badge-count user-count"
                                    ><i class="bi bi-person"></i> {{ ms.user_count }} users</span
                                >
                            </div>
                        </div>
                        <div v-if="menuStats.length === 0" class="text-muted small text-center py-3">
                            Belum ada data
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group Stats -->
            <div class="col-md-6 mb-3">
                <div class="stat-box">
                    <h6 class="stat-title">
                        <i class="bi bi-collection-fill text-primary me-2"></i>Module Group Stats
                    </h6>
                    <div class="stat-list">
                        <div v-for="gs in groupStats" :key="gs.Kode_Grup_Fitur" class="stat-item">
                            <div class="stat-item-main">
                                <span class="stat-name">{{ gs.Nama_Grup_Fitur }}</span>
                                <span class="stat-page">{{ gs.Kode_Grup_Fitur }}</span>
                            </div>
                            <div class="stat-item-values">
                                <span class="badge-count menu-count"
                                    ><i class="bi bi-list"></i> {{ gs.menu_count }} menus</span
                                >
                            </div>
                        </div>
                        <div v-if="groupStats.length === 0" class="text-muted small text-center py-3">
                            Belum ada data
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scope Stats -->
            <div class="col-md-12">
                <div class="stat-box">
                    <h6 class="stat-title">
                        <i class="bi bi-funnel-fill text-danger me-2"></i>Scope Coverage (Legacy Role System)
                    </h6>
                    <div class="scope-grid">
                        <div v-for="s in scopeStats" :key="s.Scope_Name" class="scope-card">
                            <div class="scope-header">
                                <span class="scope-name">{{ s.Scope_Name }}</span>
                                <span class="badge bg-secondary badge-sm">{{ s.role_count }} roles</span>
                            </div>
                            <div class="scope-val text-muted">{{ s.Scope_Value }}</div>
                        </div>
                        <div v-if="scopeStats.length === 0" class="text-muted small text-center py-3 w-100">
                            Belum ada data
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MonitoringPanel',
    props: {
        modules: { type: Array, default: () => [] },
        legacyCount: { type: Number, default: 0 },
        recentActivity: { type: Array, default: () => [] },
    },
    data() {
        return {
            loading: false,
            totals: null,
            menuStats: [],
            groupStats: [],
            scopeStats: [],
        };
    },
    computed: {
        dashboardUsers() {
            return this.modules.reduce((sum, m) => sum + (m.users || 0), 0);
        },
        dashboardRoles() {
            return this.modules.reduce((sum, m) => sum + (m.roles || 0), 0);
        },
    },
    methods: {
        formatDate(dateStr) {
            if (!dateStr) return '-';
            try {
                return new Date(dateStr).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch {
                return dateStr;
            }
        },
        async fetchData() {
            this.loading = true;
            try {
                const res = await fetch('/api/v1/system-auth/monitoring');
                const data = await res.json();
                this.totals = data.totals;
                this.menuStats = data.menuStats || [];
                this.groupStats = data.groupStats || [];
                this.scopeStats = data.scopeStats || [];
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
    },
    mounted() {
        this.fetchData();
    },
};
</script>

<style scoped>
.panel-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.panel-subtitle {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}
.btn-refresh {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-500);
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-refresh:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
}
.btn-refresh:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
}
.summary-card > i {
    font-size: 1.35rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}
.summary-total > i {
    color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
}
.summary-legacy > i {
    color: var(--hcis-amber-500);
    background: var(--hcis-amber-50);
}
.summary-roles > i {
    color: var(--hcis-emerald-500);
    background: var(--hcis-emerald-50);
}
.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    line-height: 1;
}
.summary-label {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    margin-top: 0.15rem;
}

.activity-section {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    overflow: hidden;
}
.section-header {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}
.section-header h5 {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
}
.activity-empty {
    text-align: center;
    padding: 1.5rem;
    color: var(--hcis-slate-400);
}
.activity-list {
    max-height: 280px;
    overflow-y: auto;
}
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-50);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 0.4rem;
}
.activity-indicator.active {
    background: var(--hcis-emerald-500);
}
.activity-indicator.inactive {
    background: var(--hcis-red-500);
}
.activity-content {
    flex: 1;
    min-width: 0;
}
.activity-text {
    font-size: 0.8rem;
    color: var(--hcis-slate-600);
}
.activity-role-badge {
    display: inline-block;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
    padding: 0.1rem 0.45rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
}
.activity-time {
    font-size: 0.7rem;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.metric-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.metric-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
}
.bg-blue {
    background: linear-gradient(135deg, var(--hcis-blue-500), var(--hcis-blue-600));
}
.bg-green {
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
}
.bg-purple {
    background: linear-gradient(135deg, #8b5cf6, var(--hcis-violet-600));
}
.bg-orange {
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
}
.metric-label {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.metric-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    line-height: 1.2;
}

.stat-box {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 1.25rem;
    height: 100%;
}
.stat-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    margin-bottom: 1rem;
    margin-top: 0;
}
.stat-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem;
    background: var(--hcis-slate-50);
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-100);
}
.stat-item-main {
    display: flex;
    flex-direction: column;
}
.stat-name {
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--hcis-slate-800);
}
.stat-page {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    font-family: monospace;
}
.badge-count {
    font-size: 0.72rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}
.user-count {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.menu-count {
    background: var(--hcis-green-50);
    color: var(--hcis-green-600);
}

.scope-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}
.scope-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 0.75rem;
}
.scope-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.3rem;
}
.scope-name {
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--hcis-slate-800);
    line-height: 1.2;
}
.badge-sm {
    font-size: 0.6rem;
    padding: 0.15rem 0.3rem;
}
.scope-val {
    font-size: 0.68rem;
    font-family: monospace;
    word-break: break-all;
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

@media (max-width: 992px) {
    .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 576px) {
    .metrics-grid {
        grid-template-columns: 1fr;
    }
}
</style>
