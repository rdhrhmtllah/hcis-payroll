<template>
    <section class="approval-dashboard">
        <header class="dashboard-hero">
            <div class="hero-copy">
                <div class="eyebrow">
                    <i class="bi bi-activity"></i>
                    Pusat kontrol approval
                </div>
                <h4>{{ featureName }}</h4>
                <p>
                    {{
                        isSystemAdmin
                            ? 'Pantau kesehatan konfigurasi, source assignment, dan struktur alur.'
                            : 'Pantau assignment approver dan selesaikan masalah langsung dari alur fitur.'
                    }}
                </p>
                <div class="hero-badges">
                    <span class="persona-badge">
                        <i :class="isSystemAdmin ? 'bi bi-shield-check' : 'bi bi-person-workspace'"></i>
                        {{ personaLabel }}
                    </span>
                    <span :class="['health-badge', issueCount ? 'has-issues' : 'is-healthy']">
                        <i :class="issueCount ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill'"></i>
                        {{ issueCount ? `${issueCount} hal perlu ditangani` : 'Tidak ada masalah utama' }}
                    </span>
                </div>
            </div>
            <div class="hero-actions">
                <button class="dashboard-btn secondary" :disabled="loading" @click="$emit('refresh')">
                    <i :class="['bi bi-arrow-clockwise', { spinning: loading }]"></i>
                    Refresh
                </button>
                <button class="dashboard-btn primary" @click="$emit('open-flow')">
                    <i class="bi bi-diagram-3-fill"></i>
                    {{ isSystemAdmin ? 'Kelola Config' : 'Lihat Alur Fitur' }}
                </button>
            </div>
        </header>

        <div v-if="loading && !summary" class="dashboard-skeleton" aria-label="Memuat ringkasan approval">
            <div class="skeleton-metrics">
                <span v-for="index in 5" :key="index" class="skeleton-block"></span>
            </div>
            <div class="skeleton-panels">
                <span class="skeleton-block tall"></span>
                <span class="skeleton-block tall"></span>
            </div>
        </div>

        <div v-else-if="error && !summary" class="dashboard-state error-state">
            <i class="bi bi-cloud-slash"></i>
            <div>
                <strong>Ringkasan belum dapat dimuat</strong>
                <p>{{ error }}</p>
            </div>
            <button class="dashboard-btn secondary" @click="$emit('refresh')">Coba lagi</button>
        </div>

        <template v-else>
            <div class="metric-grid">
                <article v-for="metric in metricCards" :key="metric.key" :class="['metric-card', metric.tone]">
                    <div class="metric-icon"><i :class="metric.icon"></i></div>
                    <div class="metric-copy">
                        <span>{{ metric.label }}</span>
                        <strong>{{ metric.value }}</strong>
                        <small>{{ metric.caption }}</small>
                    </div>
                </article>
            </div>

            <div v-if="error" class="inline-warning">
                <i class="bi bi-exclamation-circle"></i>
                Data terakhir tetap ditampilkan. Refresh gagal: {{ error }}
            </div>

            <div class="dashboard-main-grid">
                <article class="dashboard-panel attention-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">Prioritas admin</span>
                            <h5>Perlu Ditangani</h5>
                        </div>
                        <span class="panel-count">{{ issues.length }}</span>
                    </div>

                    <div v-if="!issues.length" class="healthy-state">
                        <div class="healthy-icon"><i class="bi bi-check2"></i></div>
                        <strong>Semua kondisi utama terlihat baik</strong>
                        <p>Tidak ada source invalid, approver nonaktif, atau urutan approval bermasalah.</p>
                        <button class="text-action" @click="$emit('open-flow')">Periksa alur fitur <i class="bi bi-arrow-right"></i></button>
                    </div>

                    <div v-else class="issue-list">
                        <button
                            v-for="issue in issues"
                            :key="issueKey(issue)"
                            :class="['issue-card', issue.severity]"
                            @click="openIssue(issue)"
                        >
                            <span class="issue-icon"><i :class="issueIcon(issue)"></i></span>
                            <span class="issue-copy">
                                <span class="issue-topline">
                                    <strong>{{ issue.title }}</strong>
                                    <em>{{ severityLabel(issue.severity) }}</em>
                                </span>
                                <small>{{ issue.description }}</small>
                            </span>
                            <i class="bi bi-chevron-right issue-arrow"></i>
                        </button>
                    </div>
                </article>

                <article class="dashboard-panel coverage-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">Per source unik · {{ metrics.active_employees || 0 }} karyawan aktif</span>
                            <h5>Assignment Health</h5>
                        </div>
                        <span class="panel-count neutral">{{ coverage.length }}</span>
                    </div>

                    <div v-if="!coverage.length" class="empty-state">
                        <i class="bi bi-database-x"></i>
                        <strong>Belum ada source assignment yang dapat dikelola</strong>
                        <p>Buka alur fitur untuk memeriksa posisi approval yang tersedia.</p>
                    </div>

                    <div v-else class="coverage-list">
                        <button
                            v-for="source in coverage"
                            :key="source.source_reference"
                            class="coverage-card"
                            @click="openCoverage(source)"
                        >
                            <span :class="['source-status', source.status]"><i :class="statusIcon(source.status)"></i></span>
                            <span class="coverage-copy">
                                <span class="coverage-title">
                                    <strong>{{ source.source_reference }}</strong>
                                    <em v-if="source.flow_count > 1">{{ source.flow_count }} alur terhubung</em>
                                </span>
                                <span class="coverage-stats">
                                    <span><b>{{ source.covered_employee_count }}/{{ source.expected_employee_count }}</b> karyawan</span>
                                    <span><b>{{ source.approver_count }}</b> approver</span>
                                    <span><b>{{ source.max_order }}</b> level</span>
                                </span>
                                <span class="coverage-progress">
                                    <span :style="{ width: `${source.coverage_percent || 0}%` }"></span>
                                </span>
                                <small v-if="source.missing_employee_count" class="source-warning">
                                    {{ source.missing_employee_count }} karyawan belum memiliki approval:
                                    {{ missingEmployeeNames(source) }}
                                </small>
                                <small v-else-if="source.inactive_approver_count || source.order_issue_count" class="source-warning">
                                    {{ source.inactive_approver_count }} approver nonaktif · {{ source.order_issue_count }} urutan bermasalah
                                </small>
                                <small v-else>Coverage lengkap · {{ source.config_codes?.join(', ') }}</small>
                            </span>
                            <i class="bi bi-arrow-up-right coverage-arrow"></i>
                        </button>
                    </div>
                </article>
            </div>

            <div class="dashboard-secondary-grid">
                <article class="dashboard-panel composition-panel">
                    <div class="panel-heading compact">
                        <div>
                            <span class="panel-kicker">Struktur alur</span>
                            <h5>Komposisi Posisi Approval</h5>
                        </div>
                    </div>
                    <div v-if="!composition.length" class="empty-compact">Belum ada posisi approval.</div>
                    <div v-else class="composition-list">
                        <div v-for="item in composition" :key="item.type" class="composition-row">
                            <div class="composition-label">
                                <span>{{ sourceTypeLabel(item.type) }}</span>
                                <strong>{{ item.count }}</strong>
                            </div>
                            <div class="composition-track">
                                <span :style="{ width: compositionWidth(item.count) }"></span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="dashboard-panel activity-panel">
                    <div class="panel-heading compact">
                        <div>
                            <span class="panel-kicker">Audit terbaru</span>
                            <h5>Aktivitas Terakhir</h5>
                        </div>
                        <button class="text-action" @click="$emit('open-history')">Lihat semua</button>
                    </div>
                    <div v-if="!recentActivity.length" class="empty-compact">Belum ada perubahan assignment yang tercatat.</div>
                    <div v-else class="activity-list">
                        <div v-for="activity in recentActivity" :key="activity.Id || `${activity.Created_At}-${activity.Kode_Karyawan_Requester}`" class="activity-row">
                            <span class="activity-dot"></span>
                            <div>
                                <strong>{{ activityLabel(activity.Jenis_Transaksi) }}</strong>
                                <p>{{ activity.Nama_Approval || activity.Kode_Config || 'Approval matrix' }}</p>
                                <small>{{ activity.Created_By_Nama || activity.Created_By || 'Admin' }} · {{ formatDate(activity.Created_At) }}</small>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </template>

        <el-dialog
            v-model="coverageModalOpen"
            width="min(620px, 94vw)"
            destroy-on-close
            class="coverage-gap-dialog"
            :title="selectedCoverage?.source_reference || 'Coverage Assignment'"
        >
            <div v-if="selectedCoverage" class="coverage-modal">
                <div class="coverage-modal-summary">
                    <div>
                        <span>Coverage karyawan aktif</span>
                        <strong>{{ selectedCoverage.coverage_percent }}%</strong>
                    </div>
                    <div>
                        <span>Sudah memiliki approval</span>
                        <strong>{{ selectedCoverage.covered_employee_count }}/{{ selectedCoverage.expected_employee_count }}</strong>
                    </div>
                    <div class="danger">
                        <span>Belum memiliki approval</span>
                        <strong>{{ selectedCoverage.missing_employee_count }}</strong>
                    </div>
                </div>

                <template v-if="selectedCoverage.missing_employee_count">
                    <div class="missing-modal-heading">
                        <div>
                            <strong>Karyawan yang perlu dilengkapi</strong>
                            <small>Baseline: seluruh karyawan aktif pada source assignment ini.</small>
                        </div>
                        <input v-model="missingSearch" type="search" placeholder="Cari nama / kode..." />
                    </div>
                    <div class="missing-employee-list">
                        <div v-for="employee in filteredMissingEmployees" :key="employee.kode" class="missing-employee-row">
                            <span class="employee-avatar">{{ employeeInitials(employee.nama) }}</span>
                            <span class="employee-copy">
                                <strong>{{ employee.nama }}</strong>
                                <small>{{ employee.kode }}<template v-if="employee.nik"> · {{ employee.nik }}</template></small>
                            </span>
                            <span class="missing-pill">Belum ada alur</span>
                        </div>
                        <div v-if="!filteredMissingEmployees.length" class="empty-compact">Karyawan tidak ditemukan.</div>
                    </div>
                </template>
                <div v-else class="modal-healthy">
                    <i class="bi bi-check-circle-fill"></i>
                    Seluruh karyawan aktif sudah memiliki approval pada source ini.
                </div>
            </div>
            <template #footer>
                <button class="dashboard-btn secondary" @click="coverageModalOpen = false">Tutup</button>
                <button class="dashboard-btn primary" @click="manageSelectedCoverage">
                    <i class="bi bi-person-gear"></i>
                    Kelola Assignment
                </button>
            </template>
        </el-dialog>
    </section>
</template>

<script>
export default {
    name: 'ApprovalDashboard',
    props: {
        summary: { type: Object, default: null },
        loading: { type: Boolean, default: false },
        error: { type: String, default: null },
        featureName: { type: String, default: 'Fitur terpilih' },
        isSystemAdmin: { type: Boolean, default: false },
        canAssign: { type: Boolean, default: false },
    },
    emits: ['refresh', 'open-flow', 'manage-step', 'open-config', 'open-history'],
    data() {
        return {
            coverageModalOpen: false,
            selectedCoverage: null,
            missingSearch: '',
        };
    },
    computed: {
        metrics() {
            return this.summary?.metrics || {};
        },
        issues() {
            return this.summary?.issues || [];
        },
        coverage() {
            return this.summary?.coverage || [];
        },
        composition() {
            return this.summary?.composition || [];
        },
        recentActivity() {
            return this.summary?.recent_activity || [];
        },
        issueCount() {
            return this.metrics.issue_count || this.issues.length || 0;
        },
        personaLabel() {
            if (this.isSystemAdmin) return 'System Admin';
            return this.canAssign ? 'Admin Assignment' : 'View Only';
        },
        metricCards() {
            return [
                {
                    key: 'flows',
                    label: this.isSystemAdmin ? 'Config Aktif' : 'Alur Aktif',
                    value: this.metrics.active_configs || 0,
                    caption: `${this.metrics.total_configs || 0} total alur`,
                    icon: 'bi bi-diagram-3-fill',
                    tone: 'indigo',
                },
                {
                    key: 'positions',
                    label: 'Posisi Editable',
                    value: this.metrics.editable_positions || 0,
                    caption: `${this.metrics.total_positions || 0} seluruh posisi`,
                    icon: 'bi bi-node-plus-fill',
                    tone: 'blue',
                },
                {
                    key: 'requesters',
                    label: 'Coverage Karyawan',
                    value: `${this.metrics.employee_coverage_percent ?? 100}%`,
                    caption: `${this.metrics.missing_assignments || 0} assignment belum tersedia`,
                    icon: 'bi bi-people-fill',
                    tone: 'teal',
                },
                {
                    key: 'approvers',
                    label: 'Approver Aktif',
                    value: this.metrics.active_approvers || 0,
                    caption: 'Unik pada source aktif',
                    icon: 'bi bi-person-check-fill',
                    tone: 'green',
                },
                {
                    key: 'issues',
                    label: 'Perlu Ditangani',
                    value: this.issueCount,
                    caption: this.issueCount ? 'Buka untuk perbaikan' : 'Kondisi utama baik',
                    icon: this.issueCount ? 'bi bi-exclamation-triangle-fill' : 'bi bi-check-circle-fill',
                    tone: this.issueCount ? 'amber' : 'green',
                },
            ];
        },
        maxComposition() {
            return Math.max(...this.composition.map((item) => Number(item.count) || 0), 1);
        },
        filteredMissingEmployees() {
            const employees = this.selectedCoverage?.missing_employees || [];
            const search = this.missingSearch.trim().toLowerCase();
            if (!search) return employees;
            return employees.filter((employee) =>
                [employee.nama, employee.kode, employee.nik].some((value) => String(value || '').toLowerCase().includes(search)),
            );
        },
    },
    methods: {
        openCoverage(source) {
            if (!source.missing_employee_count) {
                this.$emit('manage-step', source);
                return;
            }
            this.selectedCoverage = source;
            this.missingSearch = '';
            this.coverageModalOpen = true;
        },
        manageSelectedCoverage() {
            if (!this.selectedCoverage) return;
            this.$emit('manage-step', this.selectedCoverage);
            this.coverageModalOpen = false;
        },
        employeeInitials(name) {
            return String(name || '?')
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => part[0])
                .join('')
                .toUpperCase();
        },
        issueKey(issue) {
            return `${issue.type}-${issue.config_id || 0}-${issue.config_detail_id || 0}-${issue.source_reference || ''}`;
        },
        openIssue(issue) {
            if (issue.type === 'missing_coverage') {
                const source = this.coverage.find((item) => item.source_reference === issue.source_reference);
                if (source) {
                    this.openCoverage(source);
                    return;
                }
            }
            if (issue.action === 'assignment') {
                this.$emit('manage-step', issue);
            } else if (issue.action === 'config') {
                this.$emit('open-config', issue);
            } else {
                this.$emit('open-flow', issue);
            }
        },
        issueIcon(issue) {
            return {
                invalid_source: 'bi bi-database-x',
                inactive_approver: 'bi bi-person-x-fill',
                order_issue: 'bi bi-list-ol',
                config_without_steps: 'bi bi-node-minus-fill',
                missing_coverage: 'bi bi-person-exclamation',
            }[issue.type] || 'bi bi-exclamation-circle-fill';
        },
        severityLabel(severity) {
            return { critical: 'Prioritas tinggi', warning: 'Perlu diperiksa', info: 'Perlu diketahui' }[severity] || 'Informasi';
        },
        statusIcon(status) {
            return { healthy: 'bi bi-check2', attention: 'bi bi-exclamation-lg', empty: 'bi bi-dash-lg' }[status] || 'bi bi-dash-lg';
        },
        sourceTypeLabel(type) {
            return {
                TABLE: 'Table Assignment',
                KPI_TABLE: 'KPI Table',
                ROLE: 'Role Approval',
                DIRECT: 'Direct Approver',
                CUSTOM: 'Custom Approver',
            }[type] || type;
        },
        compositionWidth(count) {
            return `${Math.max(8, Math.round(((Number(count) || 0) / this.maxComposition) * 100))}%`;
        },
        missingEmployeeNames(source) {
            const employees = source.missing_employees || [];
            const names = employees.slice(0, 3).map((employee) => employee.nama || employee.kode);
            const remaining = Math.max(0, employees.length - names.length);
            return `${names.join(', ')}${remaining ? `, dan ${remaining} lainnya` : ''}`;
        },
        activityLabel(type) {
            return {
                REMOVE: 'Assignment dihapus',
                REPLACE_FLOW: 'Alur requester diperbarui',
                BULK_ASSIGN: 'Bulk assignment diterapkan',
                BULK_REPLACE: 'Paket alur diperbarui',
                BULK_SUBSTITUTE: 'Approver disubstitusi',
            }[type] || type || 'Assignment diperbarui';
        },
        formatDate(value) {
            if (!value) return '-';
            return new Date(value).toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>

<style scoped>
.approval-dashboard {
    --ink: #172033;
    --muted: #68758c;
    --line: #e6eaf1;
    display: grid;
    gap: 1rem;
    padding: 0.5rem;
    color: var(--ink);
}
.dashboard-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1.35rem;
    border: 1px solid #dfe4ff;
    border-radius: 18px;
    background: linear-gradient(130deg, #f8f9ff 0%, var(--hcis-indigo-50) 58%, #f0fdfa 100%);
}
.dashboard-hero::after {
    position: absolute;
    right: -60px;
    bottom: -85px;
    width: 220px;
    height: 220px;
    border: 35px solid rgba(79, 70, 229, 0.07);
    border-radius: 50%;
    content: '';
}
.hero-copy, .hero-actions { position: relative; z-index: 1; }
.eyebrow, .panel-kicker {
    color: var(--hcis-indigo-600);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}
.eyebrow { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.45rem; }
.hero-copy h4 { margin: 0; font-size: clamp(1.1rem, 2vw, 1.45rem); font-weight: 800; }
.hero-copy p { max-width: 700px; margin: 0.3rem 0 0.8rem; color: var(--muted); font-size: 0.82rem; }
.hero-badges, .hero-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.hero-actions { justify-content: flex-end; align-self: flex-start; }
.persona-badge, .health-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.36rem 0.62rem;
    border: 1px solid #dfe4ee;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    font-size: 0.7rem;
    font-weight: 700;
}
.health-badge.has-issues { border-color: #fed7aa; color: var(--hcis-amber-700); background: #fff7ed; }
.health-badge.is-healthy { border-color: var(--hcis-green-200); color: var(--hcis-green-700); background: var(--hcis-green-50); }
.dashboard-btn {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.55rem 0.8rem;
    border: 1px solid transparent;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 750;
    transition: 0.18s ease;
}
.dashboard-btn.primary { background: var(--hcis-indigo-600); color: #fff; box-shadow: 0 7px 18px rgba(79, 70, 229, 0.2); }
.dashboard-btn.secondary { border-color: #d9deea; background: #fff; color: #344054; }
.dashboard-btn:hover:not(:disabled) { transform: translateY(-1px); }
.metric-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.75rem; }
.metric-card {
    min-width: 0;
    display: flex;
    gap: 0.72rem;
    padding: 0.9rem;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.035);
}
.metric-icon {
    flex: 0 0 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    font-size: 1rem;
}
.metric-copy { min-width: 0; display: grid; }
.metric-copy span { color: var(--muted); font-size: 0.63rem; font-weight: 750; text-transform: uppercase; letter-spacing: 0.04em; }
.metric-copy strong { font-size: 1.28rem; line-height: 1.2; }
.metric-copy small { overflow-wrap: anywhere; color: #8993a5; font-size: 0.64rem; }
.metric-card.indigo .metric-icon { color: var(--hcis-indigo-600); background: var(--hcis-indigo-50); }
.metric-card.blue .metric-icon { color: var(--hcis-blue-600); background: #eff6ff; }
.metric-card.teal .metric-icon { color: #0f766e; background: #f0fdfa; }
.metric-card.green .metric-icon { color: var(--hcis-green-700); background: var(--hcis-green-50); }
.metric-card.amber .metric-icon { color: var(--hcis-amber-700); background: #fff7ed; }
.dashboard-main-grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 1rem; }
.dashboard-secondary-grid { display: grid; grid-template-columns: minmax(0, 4fr) minmax(0, 8fr); gap: 1rem; }
.dashboard-panel {
    min-width: 0;
    padding: 1rem;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
}
.panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.8rem; }
.panel-heading.compact { margin-bottom: 0.7rem; }
.panel-heading h5 { margin: 0.1rem 0 0; font-size: 0.92rem; font-weight: 800; }
.panel-count {
    min-width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #fff7ed;
    color: var(--hcis-amber-700);
    font-size: 0.72rem;
    font-weight: 800;
}
.panel-count.neutral { color: var(--hcis-indigo-600); background: var(--hcis-indigo-50); }
.issue-list, .coverage-list, .activity-list { display: grid; gap: 0.55rem; max-height: 370px; overflow: auto; padding-right: 0.15rem; }
.issue-card, .coverage-card {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.72rem;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
    color: inherit;
    text-align: left;
    transition: 0.16s ease;
}
.issue-card:hover, .coverage-card:hover { border-color: var(--hcis-indigo-200); background: #fafaff; transform: translateY(-1px); }
.issue-card.critical { border-left: 3px solid var(--hcis-red-500); }
.issue-card.warning { border-left: 3px solid var(--hcis-amber-500); }
.issue-card.info { border-left: 3px solid var(--hcis-indigo-500); }
.issue-icon, .source-status {
    flex: 0 0 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #f4f6fa;
    color: #667085;
}
.issue-card.critical .issue-icon { color: var(--hcis-red-600); background: var(--hcis-red-50); }
.issue-card.warning .issue-icon { color: var(--hcis-amber-700); background: #fff7ed; }
.issue-card.info .issue-icon { color: var(--hcis-indigo-600); background: var(--hcis-indigo-50); }
.issue-copy, .coverage-copy { min-width: 0; flex: 1; display: grid; gap: 0.18rem; }
.issue-topline, .coverage-title { min-width: 0; display: flex; justify-content: space-between; gap: 0.6rem; align-items: flex-start; }
.issue-copy strong, .coverage-copy strong { overflow-wrap: anywhere; font-size: 0.75rem; }
.issue-copy small, .coverage-copy small { overflow-wrap: anywhere; color: var(--muted); font-size: 0.66rem; line-height: 1.4; }
.issue-topline em, .coverage-title em {
    flex: 0 0 auto;
    padding: 0.18rem 0.35rem;
    border-radius: 5px;
    background: #f4f6fa;
    color: #667085;
    font-size: 0.55rem;
    font-style: normal;
    font-weight: 750;
}
.issue-arrow, .coverage-arrow { flex: 0 0 auto; color: #98a2b3; font-size: 0.75rem; }
.source-status.healthy { color: var(--hcis-green-700); background: var(--hcis-green-50); }
.source-status.attention { color: var(--hcis-red-600); background: var(--hcis-red-50); }
.source-status.empty { color: var(--hcis-amber-700); background: #fff7ed; }
.coverage-stats { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.coverage-stats span { padding: 0.16rem 0.35rem; border-radius: 5px; background: #f6f7f9; color: #667085; font-size: 0.58rem; }
.coverage-stats b { color: #344054; }
.coverage-progress { height: 5px; overflow: hidden; border-radius: 99px; background: #eef1f5; }
.coverage-progress span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--hcis-amber-500), #22c55e); }
.source-warning { color: var(--hcis-amber-700) !important; }
.healthy-state, .empty-state {
    min-height: 220px;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 0.35rem;
    padding: 1rem;
    color: var(--muted);
    text-align: center;
}
.healthy-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    font-size: 1.3rem;
}
.healthy-state strong, .empty-state strong { color: var(--ink); font-size: 0.8rem; }
.healthy-state p, .empty-state p { max-width: 330px; margin: 0; font-size: 0.68rem; }
.empty-state > i { color: #98a2b3; font-size: 1.7rem; }
.text-action { border: 0; background: transparent; color: var(--hcis-indigo-600); font-size: 0.68rem; font-weight: 750; }
.composition-list { display: grid; gap: 0.7rem; }
.composition-label { display: flex; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem; font-size: 0.68rem; }
.composition-track { height: 7px; overflow: hidden; border-radius: 99px; background: #eef1f5; }
.composition-track span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--hcis-indigo-500), #14b8a6); }
.activity-list { max-height: 230px; }
.activity-row { position: relative; display: flex; gap: 0.65rem; padding: 0.35rem 0.15rem; }
.activity-dot { flex: 0 0 9px; height: 9px; margin-top: 0.25rem; border: 2px solid var(--hcis-indigo-200); border-radius: 50%; background: var(--hcis-indigo-600); }
.activity-row strong { display: block; font-size: 0.7rem; }
.activity-row p { margin: 0.08rem 0; color: var(--muted); font-size: 0.65rem; }
.activity-row small { color: #98a2b3; font-size: 0.6rem; }
.empty-compact { padding: 1rem 0; color: var(--muted); font-size: 0.7rem; }
.dashboard-state, .inline-warning {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.85rem 1rem;
    border: 1px solid #fed7aa;
    border-radius: 12px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 0.72rem;
}
.dashboard-state > i { font-size: 1.3rem; }
.dashboard-state div { flex: 1; }
.dashboard-state p { margin: 0.1rem 0 0; }
.coverage-modal { display: grid; gap: 1rem; }
.coverage-modal-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.55rem; }
.coverage-modal-summary div { display: grid; gap: 0.15rem; padding: 0.7rem; border: 1px solid var(--line); border-radius: 10px; background: var(--hcis-slate-50); }
.coverage-modal-summary span { color: var(--muted); font-size: 0.62rem; }
.coverage-modal-summary strong { font-size: 1rem; }
.coverage-modal-summary .danger { border-color: #fed7aa; background: #fff7ed; color: var(--hcis-amber-700); }
.missing-modal-heading { display: flex; justify-content: space-between; align-items: end; gap: 0.75rem; }
.missing-modal-heading div { display: grid; }
.missing-modal-heading strong { font-size: 0.78rem; }
.missing-modal-heading small { color: var(--muted); font-size: 0.62rem; }
.missing-modal-heading input {
    min-height: 38px;
    max-width: 220px;
    padding: 0.45rem 0.65rem;
    border: 1px solid #d9deea;
    border-radius: 9px;
    outline: none;
    font-size: 0.7rem;
}
.missing-modal-heading input:focus { border-color: var(--hcis-indigo-400); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
.missing-employee-list { display: grid; gap: 0.4rem; max-height: 360px; overflow: auto; }
.missing-employee-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem; border: 1px solid var(--line); border-radius: 10px; }
.employee-avatar { flex: 0 0 32px; height: 32px; display: grid; place-items: center; border-radius: 9px; background: var(--hcis-indigo-50); color: var(--hcis-indigo-600); font-size: 0.62rem; font-weight: 800; }
.employee-copy { min-width: 0; flex: 1; display: grid; }
.employee-copy strong { overflow-wrap: anywhere; font-size: 0.7rem; }
.employee-copy small { color: var(--muted); font-size: 0.6rem; }
.missing-pill { flex: 0 0 auto; padding: 0.2rem 0.4rem; border-radius: 99px; background: #fff7ed; color: var(--hcis-amber-700); font-size: 0.55rem; font-weight: 750; }
.modal-healthy { display: flex; align-items: center; gap: 0.5rem; padding: 0.8rem; border-radius: 10px; background: var(--hcis-green-50); color: var(--hcis-green-700); font-size: 0.72rem; font-weight: 700; }
.skeleton-metrics { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; }
.skeleton-panels { display: grid; grid-template-columns: 5fr 7fr; gap: 1rem; margin-top: 1rem; }
.skeleton-block { height: 82px; border-radius: 14px; background: linear-gradient(90deg, #f1f3f7 25%, #fafbfc 50%, #f1f3f7 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
.skeleton-block.tall { height: 310px; }
.spinning { animation: spin 0.8s linear infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 1199px) {
    .metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .dashboard-main-grid, .dashboard-secondary-grid { grid-template-columns: 1fr; }
}
@media (max-width: 767px) {
    .approval-dashboard { padding: 0; }
    .dashboard-hero { flex-direction: column; padding: 1rem; border-radius: 14px; }
    .hero-actions { width: 100%; justify-content: stretch; }
    .hero-actions .dashboard-btn { flex: 1; }
    .metric-grid, .skeleton-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .skeleton-panels { grid-template-columns: 1fr; }
    .dashboard-panel { padding: 0.8rem; border-radius: 14px; }
    .issue-topline, .coverage-title { display: grid; justify-content: stretch; }
    .issue-topline em, .coverage-title em { width: max-content; }
    .coverage-modal-summary { grid-template-columns: 1fr; }
    .missing-modal-heading { display: grid; align-items: stretch; }
    .missing-modal-heading input { max-width: none; width: 100%; }
}
@media (max-width: 430px) {
    .metric-grid { grid-template-columns: 1fr; }
    .hero-actions { display: grid; grid-template-columns: 1fr; }
    .coverage-stats { gap: 0.2rem; }
}
</style>
