<template>
    <div v-if="summary" class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white">
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
            <div>
                <p class="section-kicker mb-1">Leader Mode</p>
                <h5 class="fw-bold mb-0">Pending Approval & Team Summary</h5>
            </div>
            <button class="btn btn-dark btn-sm rounded-pill px-3" @click="$emit('navigate', '/leader-shift')">
                Buka Approval Center
            </button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3 col-xl">
                <div class="leader-stat tone-ink">
                    <small>Pending Approval</small>
                    <strong>{{ summary.pending_approvals || 0 }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl" v-for="item in countCards" :key="item.label">
                <div class="leader-stat" :style="{ background: item.bg, color: item.color }">
                    <small>{{ item.label }}</small>
                    <strong>{{ item.value }}</strong>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div v-if="(summary.members || []).length" class="member-stack">
                    <div v-for="member in summary.members || []" :key="member.kode_karyawan" class="member-row">
                        <div class="avatar">{{ initials(member.nama) }}</div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <strong>{{ member.nama }}</strong>
                                <span class="badge rounded-pill" :class="statusBadge(member.status_hari_ini)">
                                    {{ member.status_hari_ini }}
                                </span>
                                <span v-if="member.is_lembur_today" class="badge bg-danger-subtle text-danger rounded-pill">
                                    Sedang lembur
                                </span>
                            </div>
                            <div class="text-muted small mt-1">
                                {{ member.shift_hari_ini || 'NO SHIFT' }} | {{ member.jam_masuk_hari_ini || '--:--' }} - {{ member.jam_keluar_hari_ini || '--:--' }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold small">{{ member.label_hadir }}</div>
                            <small class="text-muted">{{ member.total_keterlambatan_menit || 0 }} menit late</small>
                        </div>
                    </div>
                </div>
                <div v-else class="member-empty">
                    Ringkasan anggota tim akan muncul setelah data absensi tim berhasil dibaca.
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="attention-card">
                    <div class="fw-bold mb-2">Siapa yang perlu perhatian?</div>
                    <ul class="attention-list mb-0">
                        <li v-if="(summary.counts?.belum_absen || 0) > 0">{{ summary.counts.belum_absen }} anggota belum absen.</li>
                        <li v-if="(summary.counts?.terlambat || 0) > 0">{{ summary.counts.terlambat }} anggota terlambat hari ini.</li>
                        <li v-if="(summary.counts?.izin || 0) > 0">{{ summary.counts.izin }} anggota sedang izin.</li>
                        <li v-if="(summary.counts?.sakit || 0) > 0">{{ summary.counts.sakit }} anggota sakit.</li>
                        <li v-if="(summary.counts?.cuti || 0) > 0">{{ summary.counts.cuti }} anggota cuti.</li>
                        <li v-if="(summary.counts?.off || 0) > 0">{{ summary.counts.off }} anggota OFF.</li>
                        <li v-if="(summary.counts?.lembur || 0) > 0">{{ summary.counts.lembur }} anggota sedang lembur.</li>
                        <li v-if="isCalmState">Tidak ada lonjakan isu besar di tim hari ini.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisLeaderPanel',
    props: {
        summary: {
            type: Object,
            default: null,
        },
    },
    emits: ['navigate'],
    computed: {
        countCards() {
            return [
                { label: 'Belum Absen', value: this.summary?.counts?.belum_absen ?? 0, bg: '#fff1f2', color: '#be123c' },
                { label: 'Terlambat', value: this.summary?.counts?.terlambat ?? 0, bg: '#fffbeb', color: '#b45309' },
                { label: 'Izin/Sakit/Cuti', value: (this.summary?.counts?.izin ?? 0) + (this.summary?.counts?.sakit ?? 0) + (this.summary?.counts?.cuti ?? 0), bg: '#eff6ff', color: '#1d4ed8' },
                { label: 'OFF', value: this.summary?.counts?.off ?? 0, bg: '#f8fafc', color: '#334155' },
            ];
        },
        isCalmState() {
            const counts = this.summary?.counts || {};
            return (
                Number(counts.belum_absen || 0) === 0 &&
                Number(counts.terlambat || 0) === 0 &&
                Number(counts.izin || 0) === 0 &&
                Number(counts.sakit || 0) === 0 &&
                Number(counts.cuti || 0) === 0 &&
                Number(counts.lembur || 0) === 0
            );
        },
    },
    methods: {
        initials(name) {
            return String(name || '?')
                .split(' ')
                .slice(0, 2)
                .map((part) => part[0])
                .join('')
                .toUpperCase();
        },
        statusBadge(status) {
            const map = {
                'Belum Absen': 'bg-danger-subtle text-danger',
                Terlambat: 'bg-warning-subtle text-warning',
                Izin: 'bg-primary-subtle text-primary',
                Sakit: 'bg-danger-subtle text-danger',
                Cuti: 'bg-info-subtle text-info',
                OFF: 'bg-secondary-subtle text-secondary',
                Hadir: 'bg-success-subtle text-success',
            };

            return map[status] || 'bg-light text-dark';
        },
    },
};
</script>

<style scoped>
.section-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    font-weight: 700;
}

.leader-stat {
    border-radius: 1rem;
    padding: 1rem;
    min-height: 94px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid rgba(148, 163, 184, 0.12);
}

.leader-stat strong {
    font-size: 1.35rem;
    font-weight: 800;
}

.leader-stat small {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.75;
}

.tone-ink {
    background: #0f172a;
    color: #fff;
}

.member-stack {
    display: grid;
    gap: 0.8rem;
}

.member-row {
    display: flex;
    gap: 0.85rem;
    align-items: center;
    padding: 0.95rem 1rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
}

.avatar {
    width: 42px;
    height: 42px;
    border-radius: 0.95rem;
    background: #0f766e;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
}

.member-empty {
    height: 100%;
    min-height: 196px;
    border-radius: 1rem;
    border: 1px dashed #cbd5e1;
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 1.25rem;
    font-weight: 600;
}

.attention-card {
    height: 100%;
    border-radius: 1.2rem;
    background: linear-gradient(135deg, #fff7ed, #fffbeb);
    border: 1px solid #fed7aa;
    padding: 1.1rem;
}

.attention-list {
    padding-left: 1rem;
    color: #7c2d12;
}

.attention-list li + li {
    margin-top: 0.65rem;
}
</style>
