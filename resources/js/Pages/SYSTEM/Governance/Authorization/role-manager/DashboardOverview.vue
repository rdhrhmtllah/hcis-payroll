<template>
    <div class="dashboard-overview">
        <div class="hero-card mb-3">
            <div class="hero-head">
                <h4>Ringkasan Akses Anda</h4>
                <span class="tier-pill">{{ adminTierLabel }}</span>
            </div>
            <p class="hero-desc">
                Dashboard ini hanya menampilkan informasi sesuai scope akun Anda. Untuk detail teknis dan aktivitas
                sistem, lihat tab Monitoring.
            </p>
            <div class="module-chip-list" v-if="modules.length">
                <span v-for="mod in modules" :key="mod.id" class="module-chip">
                    {{ mod.kode }}
                </span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="summary-card summary-total">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <div>
                        <div class="summary-value">{{ modules.length }}</div>
                        <div class="summary-label">Modul Dalam Scope</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card summary-legacy">
                    <i class="bi bi-people-fill"></i>
                    <div>
                        <div class="summary-value">{{ totalUsers }}</div>
                        <div class="summary-label">User Dengan Role Aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="summary-card summary-roles">
                    <i class="bi bi-shield-check"></i>
                    <div>
                        <div class="summary-value">{{ totalRoles }}</div>
                        <div class="summary-label">Total Role Aktif</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="module-admin-section" v-if="moduleAdminCards.length > 0">
            <div class="section-header">
                <h5><i class="bi bi-person-badge me-2"></i>Admin Dalam Scope Anda</h5>
            </div>
            <div class="module-admin-grid">
                <div v-for="moduleCard in moduleAdminCards" :key="moduleCard.id" class="module-admin-card">
                    <div class="module-admin-head">
                        <div>
                            <div class="module-admin-title">{{ moduleCard.nama }}</div>
                            <div class="module-admin-code">{{ moduleCard.kode }}</div>
                        </div>
                        <span class="module-admin-count">{{ moduleCard.admins.length }} admin</span>
                    </div>

                    <div class="module-admin-list" v-if="moduleCard.admins.length">
                        <div
                            v-for="admin in moduleCard.admins"
                            :key="`${moduleCard.id}-${admin.user_id}`"
                            class="module-admin-item"
                        >
                            <div class="module-admin-user">
                                <strong>{{ admin.user_nama || admin.user_id }}</strong>
                                <small v-if="admin.user_kode" class="text-muted">{{ admin.user_kode }}</small>
                            </div>
                            <div class="module-admin-roles">
                                <span v-for="(role, idx) in admin.roles.slice(0, 3)" :key="idx" class="role-chip">
                                    {{ role }}
                                </span>
                                <span v-if="admin.roles.length > 3" class="role-chip role-chip-more">
                                    +{{ admin.roles.length - 3 }} role
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="module-admin-empty" v-else>Belum ada admin aktif pada modul ini.</div>
                </div>
            </div>
        </div>

        <div class="empty-admin-note" v-else>
            <i class="bi bi-info-circle me-1"></i>
            Tidak ada daftar admin yang perlu ditampilkan untuk scope ini.
        </div>
    </div>
</template>

<script>
export default {
    name: 'DashboardOverview',
    props: {
        modules: { type: Array, default: () => [] },
        scope: { type: Object, default: () => ({}) },
        loading: { type: Boolean, default: false },
    },
    computed: {
        totalUsers() {
            return this.modules.reduce((sum, m) => sum + (m.users || 0), 0);
        },
        totalRoles() {
            return this.modules.reduce((sum, m) => sum + (m.roles || 0), 0);
        },
        adminTierLabel() {
            const tier = this.scope?.adminTier || 'ADMIN_BIASA';
            if (tier === 'SYSTEM_ADMIN') return 'System Admin';
            if (tier === 'SUPER_ADMIN') return 'Super Admin';
            return 'Admin';
        },
        moduleAdminCards() {
            return this.modules
                .map((m) => {
                    const byUser = new Map();

                    (m.admins || []).forEach((admin) => {
                        const key = admin.user_id || admin.user_nama || 'unknown';
                        if (!byUser.has(key)) {
                            byUser.set(key, {
                                user_id: admin.user_id,
                                user_nama: admin.user_nama,
                                user_kode: admin.user_kode,
                                roles: [],
                            });
                        }

                        const entry = byUser.get(key);
                        if (admin.role_name && !entry.roles.includes(admin.role_name)) {
                            entry.roles.push(admin.role_name);
                        }
                    });

                    return {
                        id: m.id,
                        kode: m.kode,
                        nama: m.nama || m.kode,
                        admins: Array.from(byUser.values()),
                    };
                })
                .filter((m) => m.admins.length > 0);
        },
    },
};
</script>

<style scoped>
.dashboard-overview {
    animation: fadeIn 0.3s ease;
}

.hero-card {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #eff6ff 0%, var(--hcis-white) 100%);
    border: 1px solid #dbeafe;
    border-radius: 14px;
}

.hero-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
}

.hero-head h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}

.hero-desc {
    margin: 0;
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
}

.tier-pill {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-indigo-800);
    background: var(--hcis-indigo-100);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    white-space: nowrap;
}

.module-chip-list {
    margin-top: 0.6rem;
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.module-chip {
    font-size: 0.68rem;
    font-weight: 700;
    color: #1d4ed8;
    background: #dbeafe;
    border-radius: 999px;
    padding: 0.18rem 0.55rem;
}

/* Summary Cards */
.summary-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    transition: all 0.2s ease;
}

.summary-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.summary-card > i {
    font-size: 1.5rem;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
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
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    line-height: 1;
}

.summary-label {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    margin-top: 0.15rem;
}

/* Activity Section */
.module-admin-section {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    overflow: hidden;
}

.section-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.section-header h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}

.activity-empty {
    text-align: center;
    padding: 2rem;
    color: var(--hcis-slate-400);
}

.activity-empty i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.module-admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 0.8rem;
    padding: 0.9rem;
}

.module-admin-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
}

.module-admin-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    background: var(--hcis-slate-50);
}

.module-admin-title {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}

.module-admin-code {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
}

.module-admin-count {
    font-size: 0.7rem;
    font-weight: 700;
    color: #0f766e;
    background: #ccfbf1;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    white-space: nowrap;
}

.module-admin-list {
    max-height: 260px;
    overflow-y: auto;
}

.module-admin-item {
    padding: 0.6rem 0.9rem;
    border-bottom: 1px solid var(--hcis-slate-50);
}

.module-admin-item:last-child {
    border-bottom: none;
}

.module-admin-user {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
    margin-bottom: 0.35rem;
    font-size: 0.78rem;
    color: var(--hcis-slate-700);
}

.module-admin-roles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.role-chip {
    font-size: 0.68rem;
    font-weight: 600;
    color: #1d4ed8;
    background: #dbeafe;
    padding: 0.12rem 0.42rem;
    border-radius: 999px;
}

.role-chip-more {
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
}

.module-admin-empty {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    padding: 0.8rem 0.9rem;
}

.empty-admin-note {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 10px;
    padding: 0.75rem 0.9rem;
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
    .summary-value {
        font-size: 1.1rem;
    }
}
</style>
