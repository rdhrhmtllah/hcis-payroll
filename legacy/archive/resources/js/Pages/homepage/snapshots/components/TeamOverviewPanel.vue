<template>
    <div v-if="isLeader" class="team-overview mb-4">
        <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <div class="icon-box" style="background:#F0FDFA;"><i class="bi bi-people-fill" style="color:#14B8A6;"></i></div>
                    Ringkasan Tim
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ teamData.length }} anggota</span>
                </h5>
                <div class="d-flex gap-2 align-items-center">
                    <div v-if="pendingApprovals > 0" class="pending-badge d-flex align-items-center gap-2 px-3 py-2 rounded-pill" style="background:#FEF2F2;">
                        <i class="bi bi-exclamation-circle-fill" style="color:#EF4444;"></i>
                        <span class="fw-bold small" style="color:#991B1B;">{{ pendingApprovals }} Pending</span>
                    </div>
                    <button id="cta-team-overview" aria-label="Lihat detail ringkasan tim" class="btn btn-outline-secondary btn-sm rounded-pill" @click="$emit('navigate', '/uDash')">
                        <i class="bi bi-arrow-up-right me-1" aria-hidden="true"></i>Detail
                    </button>
                </div>
            </div>

            <!-- Attendance Summary Bar -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3" v-for="(s, i) in summaryStats" :key="i">
                    <div class="p-3 rounded-4 text-center" :style="{ background: s.bg }">
                        <h4 class="fw-bold mb-0" :style="{ color: s.color }">{{ s.value }}</h4>
                        <small class="text-muted">{{ s.label }}</small>
                    </div>
                </div>
            </div>

            <!-- Member List -->
            <div class="team-list">
                <div v-for="(m, i) in visibleMembers" :key="i"
                    class="team-member d-flex align-items-center gap-3 p-3 rounded-3 mb-2"
                    :class="i % 2 === 0 ? 'bg-light' : ''">
                    <!-- Avatar -->
                    <div class="avatar-circle d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                        :style="{ background: avatarColor(m.nama), width: '38px', height: '38px' }">
                        <span class="text-white fw-bold small">{{ initials(m.nama) }}</span>
                    </div>
                    <!-- Info -->
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="mb-0 fw-bold small text-truncate">{{ m.nama }}</p>
                        <small class="text-muted">{{ m.shift_hari_ini || 'NO SHIFT' }} · {{ m.jam_masuk_hari_ini || '--:--' }} - {{ m.jam_keluar_hari_ini || '--:--' }}</small>
                    </div>
                    <!-- Status -->
                    <div class="text-end flex-shrink-0">
                        <span class="badge rounded-pill px-2 py-1" :class="statusClass(m.status_hari_ini)">
                            {{ m.status_hari_ini || '-' }}
                        </span>
                    </div>
                    <!-- Mini Stats -->
                    <div class="d-none d-md-flex gap-2 flex-shrink-0">
                        <span class="badge bg-light text-dark border rounded-pill px-2" style="font-size:0.65rem;" :title="'Hadir'">
                            {{ m.label_hadir || '-' }}
                        </span>
                        <span v-if="m.total_keterlambatan_menit > 0" class="badge bg-warning-subtle text-warning rounded-pill px-2" style="font-size:0.65rem;" :title="'Terlambat'">
                            {{ m.total_keterlambatan_menit }}m late
                        </span>
                    </div>
                </div>
            </div>

            <!-- Show More -->
            <button v-if="teamData.length > maxVisible && !showAll"
                class="btn btn-sm btn-link text-muted fw-semibold text-decoration-none w-100 mt-2"
                @click="showAll = true">
                Tampilkan {{ teamData.length - maxVisible }} anggota lainnya <i class="bi bi-chevron-down ms-1"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TeamOverviewPanel',
    props: {
        isLeader: { type: Boolean, default: false },
        teamData: { type: Array, default: () => [] },
        pendingApprovals: { type: Number, default: 0 },
        maxVisible: { type: Number, default: 5 },
    },
    emits: ['navigate'],
    data() {
        return { showAll: false };
    },
    computed: {
        visibleMembers() {
            return this.showAll ? this.teamData : this.teamData.slice(0, this.maxVisible);
        },
        summaryStats() {
            const hadir = this.teamData.filter(m => m.status_hari_ini === 'Hadir').length;
            const tidakHadir = this.teamData.filter(m => m.status_hari_ini === 'Tidak Hadir').length;
            const totalLate = this.teamData.reduce((s, m) => s + (m.total_keterlambatan_menit || 0), 0);
            return [
                { label: 'Hadir', value: hadir, color: '#10B981', bg: '#ECFDF5' },
                { label: 'Tidak Hadir', value: tidakHadir, color: '#EF4444', bg: '#FEF2F2' },
                { label: 'Total Tim', value: this.teamData.length, color: '#3B82F6', bg: '#EFF6FF' },
                { label: 'Total Late', value: totalLate + 'm', color: '#F59E0B', bg: '#FFFBEB' },
            ];
        },
    },
    methods: {
        initials(name) {
            if (!name) return '?';
            return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
        },
        avatarColor(name) {
            const colors = ['#6366F1','#10B981','#F59E0B','#EF4444','#3B82F6','#EC4899','#14B8A6','#8B5CF6'];
            let hash = 0;
            for (let i = 0; i < (name||'').length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
            return colors[Math.abs(hash) % colors.length];
        },
        statusClass(status) {
            if (status === 'Hadir') return 'bg-success-subtle text-success';
            if (status === 'Tidak Hadir') return 'bg-danger-subtle text-danger';
            return 'bg-secondary-subtle text-secondary';
        },
    },
};
</script>

<style scoped>
.icon-box { width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:1.15rem;flex-shrink:0; }
.team-list { max-height: 400px; overflow-y: auto; }
.team-member { transition: background 0.2s ease; }
.team-member:hover { background: #f8fafc !important; }
</style>
