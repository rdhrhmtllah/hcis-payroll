<template>
    <div class="leader-panel">
        <div class="leader-panel__head">
            <div>
                <span class="section-label">Team List</span>
                <h2>Daftar Anggota Tim</h2>
            </div>

            <span class="panel-count">{{ pagination.total || members.length }}</span>
        </div>

        <div class="toolbar-actions">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input :value="searchKeyword" type="text" placeholder="Cari anggota..." @input="emitSearch($event)" />
            </div>

            <select :value="selectedAttendance" class="filter-control" @change="$emit('update:selected-attendance', $event.target.value)">
                <option value="all">Semua Absensi</option>
                <option value="present">Hadir</option>
                <option value="absent">Alpha / Tidak Hadir</option>
                <option value="late">Terlambat</option>
                <option value="no-checkout">Belum Checkout</option>
                <option value="leave">Izin / Cuti / Sakit</option>
            </select>

            <select :value="selectedKpi" class="filter-control" @change="$emit('update:selected-kpi', $event.target.value)">
                <option value="all">Semua KPI</option>
                <option value="kpi-alert">Ada Alert KPI</option>
                <option value="complete">KPI Lengkap</option>
            </select>

            <select :value="selectedLms" class="filter-control" @change="$emit('update:selected-lms', $event.target.value)">
                <option value="all">Semua LMS</option>
                <option value="pending">Training Pending</option>
                <option value="complete">Training Selesai</option>
            </select>
        </div>

        <CardState
            v-if="!loading && !members.length && !searchKeyword.trim() && selectedAttendance === 'all' && selectedKpi === 'all' && selectedLms === 'all'"
            type="empty"
            :message="'Data anggota akan muncul setelah panel ini dibuka.'"
            :retryable="false"
        />

        <CardState
            v-else-if="!loading && !members.length"
            type="empty"
            :message="'Data tidak ditemukan untuk kombinasi filter saat ini.'"
            :retryable="false"
        />

        <CardSkeleton v-else-if="loading" variant="absensi" />

        <div v-else class="member-list">
            <article
                v-for="member in members"
                :key="member.id"
                class="member-card"
                :class="{ 'is-open': openedMemberId === member.id }"
            >
                <button type="button" class="member-row" @click="$emit('toggle-member', member)">
                    <div class="member-profile">
                        <div class="member-avatar">{{ member.initials }}</div>

                        <div class="member-name-wrap">
                            <div class="member-name-line">
                                <h3>{{ member.name }}</h3>

                                <span class="shift-pill">
                                    {{ member.shift?.name || 'No Shift' }}
                                    <small>{{ member.shift?.time || '-' }}</small>
                                </span>
                            </div>

                            <p>{{ member.position || '-' }} - {{ member.department || '-' }}</p>
                        </div>
                    </div>

                    <div class="member-metrics">
                        <div class="mini-metric">
                            <strong>{{ member.attendance?.todayLabel || '-' }}</strong>
                            <span>Status Hari Ini</span>
                        </div>

                        <div class="mini-metric">
                            <strong>{{ member.issueCount || 0 }}</strong>
                            <span>Issue</span>
                        </div>

                        <div class="mini-metric">
                            <strong>{{ member.kpi?.score || '-' }}</strong>
                            <span>KPI</span>
                        </div>

                        <div class="mini-metric">
                            <strong>{{ member.lms?.progress || 0 }}%</strong>
                            <span>LMS</span>
                        </div>
                    </div>

                    <div class="member-row-action">
                        <div class="issue-count" :class="{ 'has-issue': member.issueCount > 0 }">
                            <strong>{{ member.issueCount || 0 }}</strong>
                            <span>Issue</span>
                        </div>

                        <i class="bi bi-chevron-down member-chevron" :class="{ 'is-open': openedMemberId === member.id }"></i>
                    </div>
                </button>

                <transition name="accordion">
                    <div v-if="openedMemberId === member.id" class="member-detail">
                        <div class="detail-top">
                            <div>
                                <strong>Ringkasan Monitoring</strong>
                                <span>{{ detailFor(member)?.followUpSummary || member.followUpSummary || buildFollowUpSummary(member) }}</span>
                            </div>

                            <div class="detail-tags">
                                <span v-if="member.attendance?.issues?.length" class="tag is-danger">
                                    {{ member.attendance.issues.length }} issue absensi
                                </span>

                                <span v-if="member.kpi?.pendingItems?.length" class="tag is-warning">
                                    {{ member.kpi.pendingItems.length }} alert KPI
                                </span>

                                <span v-if="member.approvals?.length" class="tag is-success">
                                    {{ member.approvals.length }} approval
                                </span>

                                <span v-if="detailLoadingId === member.id" class="tag is-info">Memuat detail...</span>
                            </div>
                        </div>

                        <div class="detail-grid">
                            <section class="detail-panel">
                                <header class="detail-panel__head">
                                    <strong>HCIS</strong>
                                    <span>{{ member.attendance?.issues?.length || 0 }} issue</span>
                                </header>

                                <div class="metric-grid">
                                    <div><span>Check In</span><strong>{{ detailFor(member)?.attendance?.checkIn || member.attendance?.checkIn || '-' }}</strong></div>
                                    <div><span>Check Out</span><strong>{{ detailFor(member)?.attendance?.checkOut || member.attendance?.checkOut || '-' }}</strong></div>
                                    <div><span>Hari Kerja</span><strong>{{ member.attendance?.workDays || 0 }}</strong></div>
                                    <div><span>Alpha</span><strong>{{ member.attendance?.alphaCount || 0 }}</strong></div>
                                    <div><span>Terlambat</span><strong>{{ member.attendance?.lateCount || 0 }}x</strong></div>
                                    <div><span>No Checkout</span><strong>{{ member.attendance?.noCheckoutCount || 0 }}x</strong></div>
                                </div>

                                <div class="issue-list">
                                    <div
                                        v-for="issue in detailFor(member)?.attendance?.issues || member.attendance?.issues || []"
                                        :key="issue.id || issue.title"
                                        class="issue-item"
                                        :class="`is-${issue.tone || 'info'}`"
                                    >
                                        <i :class="issue.icon || 'bi bi-info-circle'"></i>
                                        <div>
                                            <strong>{{ issue.title }}</strong>
                                            <span>{{ issue.description }}</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="detail-panel">
                                <header class="detail-panel__head">
                                    <strong>KPI</strong>
                                    <span>{{ member.kpi?.performancePeriod || '-' }}</span>
                                </header>

                                <div class="metric-grid">
                                    <div><span>Score</span><strong>{{ detailFor(member)?.kpi?.score || member.kpi?.score || '-' }}</strong></div>
                                    <div><span>Grade</span><strong>{{ detailFor(member)?.kpi?.grade || member.kpi?.grade || '-' }}</strong></div>
                                    <div><span>Achievement</span><strong>{{ member.kpi?.achievement || 0 }}%</strong></div>
                                    <div><span>Pending</span><strong>{{ member.kpi?.pendingItems?.length || 0 }}</strong></div>
                                </div>

                                <div class="issue-list">
                                    <div
                                        v-for="item in detailFor(member)?.kpi?.pendingItems || member.kpi?.pendingItems || []"
                                        :key="item.id || item.title"
                                        class="issue-item is-warning"
                                    >
                                        <i class="bi bi-graph-up-arrow"></i>
                                        <div>
                                            <strong>{{ item.title }}</strong>
                                            <span>{{ item.description }}</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="detail-panel">
                                <header class="detail-panel__head">
                                    <strong>LMS</strong>
                                    <span>{{ member.lms?.progress || 0 }}%</span>
                                </header>

                                <div class="metric-grid">
                                    <div><span>Selesai</span><strong>{{ member.lms?.completed || 0 }}</strong></div>
                                    <div><span>Total</span><strong>{{ member.lms?.total || 0 }}</strong></div>
                                    <div><span>Avg Score</span><strong>{{ member.lms?.averageScore || '-' }}</strong></div>
                                    <div><span>Gain</span><strong>{{ member.lms?.learningGain ? `+${member.lms.learningGain}%` : '-' }}</strong></div>
                                </div>

                                <div class="training-list">
                                    <div
                                        v-for="training in detailFor(member)?.lms?.trainings || member.lms?.trainings || []"
                                        :key="training.title"
                                        class="training-row"
                                    >
                                        <div>
                                            <strong>{{ training.title }}</strong>
                                            <span>{{ training.deadline || 'Tidak ada deadline' }}</span>
                                        </div>
                                        <span class="training-status" :class="`is-${training.status || 'pending'}`">
                                            {{ training.statusLabel || 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </transition>
            </article>
        </div>

        <div v-if="pagination.total > pagination.per_page" class="pagination-row">
            <button type="button" class="pagination-btn" :disabled="!pagination.has_prev || loading" @click="$emit('load-page', pagination.current_page - 1)">
                Prev
            </button>

            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>

            <button type="button" class="pagination-btn" :disabled="!pagination.has_next || loading" @click="$emit('load-page', pagination.current_page + 1)">
                Next
            </button>
        </div>
    </div>
</template>

<script setup>
import CardSkeleton from '../snapshots/components/CardSkeleton.vue';
import CardState from '../snapshots/components/CardState.vue';

const props = defineProps({
    loading: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 10, total: 0, has_prev: false, has_next: false }) },
    searchKeyword: { type: String, default: '' },
    selectedAttendance: { type: String, default: 'all' },
    selectedKpi: { type: String, default: 'all' },
    selectedLms: { type: String, default: 'all' },
    openedMemberId: { type: [String, Number], default: '' },
    memberDetailCache: { type: Object, default: () => ({}) },
    memberDetailLoadingId: { type: [String, Number], default: '' },
});

const emit = defineEmits([
    'toggle-member',
    'load-page',
    'update:search-keyword',
    'update:selected-attendance',
    'update:selected-kpi',
    'update:selected-lms',
]);

function detailFor(member) {
    return member?.id ? (props.memberDetailCache?.[member.id] || null) : null;
}

function emitSearch(event) {
    emit('update:search-keyword', event?.target?.value ?? '');
}

function buildFollowUpSummary(member) {
    if (member?.approvals?.length) return `Ada ${member.approvals.length} pengajuan yang menunggu approval.`;
    if (member?.attendance?.todayStatus === 'absent') return 'Tidak hadir hari ini dan perlu validasi izin/sakit/cuti.';
    if (member?.attendance?.todayStatus === 'no-checkout') {
        return 'Check-out belum tercatat, perlu dicek apakah masih bekerja atau lupa absen.';
    }
    if ((member?.attendance?.earlyOutCount || 0) > 0) {
        return `Ada ${member.attendance.earlyOutCount} kejadian pulang cepat pada periode ini.`;
    }
    if ((member?.kpi?.pendingItems || []).length) {
        return `Ada ${member.kpi.pendingItems.length} alert KPI yang perlu diperhatikan.`;
    }
    if ((member?.lms?.progress || 0) < 100) return `LMS belum selesai, progress saat ini ${member.lms.progress || 0}%.`;

    return 'Tidak ada item mendesak pada periode ini.';
}
</script>

<style scoped>
.leader-panel {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.leader-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.leader-panel__head h2 {
    margin: 0.12rem 0 0;
    font-size: 1rem;
    font-weight: 950;
}

.panel-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.1rem;
    padding: 0.3rem 0.55rem;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.68rem;
    font-weight: 900;
}

.toolbar-actions {
    display: grid;
    grid-template-columns: 1.35fr repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
}

.search-box,
.filter-control {
    min-height: 2.25rem;
    border-radius: 0.85rem;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0 0.8rem;
    background: white;
    border: 1px solid #e2e8f0;
}

.search-box input,
.filter-control {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    font-size: 0.73rem;
}

.filter-control {
    padding: 0 0.7rem;
    background: white;
    border: 1px solid #e2e8f0;
}

.member-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.member-card {
    border-radius: 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.member-row {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 0.7rem;
    align-items: center;
    padding: 0.7rem;
    border: 0;
    background: transparent;
    text-align: left;
}

.member-profile {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-width: 0;
}

.member-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.8rem;
    color: white;
    background: linear-gradient(135deg, #4f46e5, #2563eb);
    font-weight: 900;
    flex-shrink: 0;
}

.member-name-wrap {
    min-width: 0;
}

.member-name-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.member-name-line h3 {
    margin: 0;
    font-size: 0.86rem;
    font-weight: 900;
}

.member-name-wrap p {
    margin: 0.2rem 0 0;
    font-size: 0.69rem;
    color: #64748b;
}

.shift-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.22rem 0.48rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.6rem;
    font-weight: 800;
}

.shift-pill small {
    font-size: 0.58rem;
}

.member-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.45rem;
}

.mini-metric {
    padding: 0.4rem 0.48rem;
    border-radius: 0.8rem;
    background: #f8fafc;
    text-align: center;
}

.mini-metric strong {
    display: block;
    font-size: 0.8rem;
    font-weight: 900;
}

.mini-metric span {
    font-size: 0.6rem;
    color: #64748b;
}

.member-row-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.issue-count {
    min-width: 3rem;
    padding: 0.35rem 0.55rem;
    border-radius: 0.8rem;
    background: #f1f5f9;
    text-align: center;
}

.issue-count.has-issue {
    background: #fee2e2;
    color: #b91c1c;
}

.issue-count strong {
    display: block;
    font-size: 0.82rem;
    font-weight: 900;
}

.issue-count span {
    font-size: 0.6rem;
}

.member-chevron {
    transition: transform 0.2s ease;
}

.member-chevron.is-open {
    transform: rotate(180deg);
}

.member-detail {
    padding: 0.7rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.detail-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.6rem;
    margin-bottom: 0.7rem;
}

.detail-top strong {
    display: block;
    font-size: 0.8rem;
    font-weight: 900;
}

.detail-top span {
    font-size: 0.68rem;
    color: #475569;
}

.detail-tags {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.tag {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    font-size: 0.6rem;
    font-weight: 800;
}

.tag.is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.tag.is-warning {
    background: #ffedd5;
    color: #c2410c;
}

.tag.is-success {
    background: #dcfce7;
    color: #15803d;
}

.tag.is-info {
    background: #dbeafe;
    color: #1d4ed8;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
}

.detail-panel {
    padding: 0.65rem;
    border-radius: 0.9rem;
    background: white;
    border: 1px solid #e2e8f0;
}

.detail-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.detail-panel__head strong {
    font-size: 0.76rem;
    font-weight: 900;
}

.detail-panel__head span {
    font-size: 0.62rem;
    color: #64748b;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem;
    margin-bottom: 0.55rem;
}

.metric-grid div {
    padding: 0.4rem 0.48rem;
    border-radius: 0.7rem;
    background: #f8fafc;
}

.metric-grid span {
    display: block;
    font-size: 0.6rem;
    color: #64748b;
}

.metric-grid strong {
    font-size: 0.76rem;
    font-weight: 900;
}

.issue-list,
.training-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.issue-item,
.training-row {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.45rem 0.5rem;
    border-radius: 0.7rem;
    background: #f8fafc;
}

.issue-item strong,
.training-row strong {
    display: block;
    font-size: 0.7rem;
    font-weight: 850;
}

.issue-item span,
.training-row span {
    font-size: 0.6rem;
    color: #64748b;
}

.training-status {
    margin-left: auto;
    font-size: 0.58rem;
    font-weight: 800;
}

.training-status.is-complete {
    color: #15803d;
}

.training-status.is-progress {
    color: #1d4ed8;
}

.training-status.is-pending {
    color: #b45309;
}

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
}

.pagination-btn {
    min-height: 2rem;
    padding: 0 0.8rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: white;
    font-size: 0.65rem;
    font-weight: 800;
}

.accordion-enter-active,
.accordion-leave-active {
    transition: all 0.18s ease;
}

.accordion-enter-from,
.accordion-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (max-width: 1080px) {
    .toolbar-actions,
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .member-row {
        grid-template-columns: 1fr;
    }

    .member-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .member-metrics,
    .metric-grid {
        grid-template-columns: 1fr;
    }
}
</style>
