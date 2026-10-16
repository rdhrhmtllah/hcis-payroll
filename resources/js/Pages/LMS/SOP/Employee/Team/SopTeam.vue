<template>
    <div class="container-fluid px-0 sop-scope">
        <HeaderKpi
            title="Kepatuhan Tim Saya"
            subtitle="Pantau progres dokumen anggota tim Anda."
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />

        <div class="mt-n4 mb-5 sop-stack">
            <!-- Bukan leader -->
            <div v-if="!loading.sum && !isLeader" class="stitch-card stitch-pad sop-fade-up">
                <EmptyState
                    icon="bi-people"
                    title="Anda belum memimpin tim"
                    subtitle="Halaman ini untuk atasan yang memiliki anggota tim. Bila ini keliru, hubungi admin HRIS."
                />
            </div>

            <template v-else>
                <!-- KPI cockpit -->
                <div class="stitch-card stitch-pad cockpit sop-fade-up">
                    <div class="cockpit-ring">
                        <SopRing :value="kpi.coverage" :size="150" label="Kepatuhan Tim" />
                    </div>
                    <div class="cockpit-stats">
                        <div class="ck-stat" :style="ckStyle('#6366f1')">
                            <div class="ck-ico"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <div class="ck-val tnum">{{ kpi.anggota }}</div>
                                <div class="ck-lab">Anggota Tim</div>
                            </div>
                        </div>
                        <div class="ck-stat" :style="ckStyle('#10b981')">
                            <div class="ck-ico"><i class="bi bi-check2-circle"></i></div>
                            <div>
                                <div class="ck-val tnum">{{ kpi.coverage }}%</div>
                                <div class="ck-lab">Rata-rata Kepatuhan</div>
                            </div>
                        </div>
                        <div class="ck-stat" :style="ckStyle('#dc2626')">
                            <div class="ck-ico"><i class="bi bi-exclamation-octagon-fill"></i></div>
                            <div>
                                <div class="ck-val tnum">{{ kpi.terlambat }}</div>
                                <div class="ck-lab">Dokumen Terlambat</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar anggota -->
                <div class="stitch-card stitch-pad sop-fade-up">
                    <div class="mb-3">
                        <div class="sop-eyebrow">Tim</div>
                        <h6 class="sop-h">Kepatuhan per Anggota</h6>
                    </div>

                    <ModernDataTable
                        :data="anggota"
                        :columns="columns"
                        :mobile-columns="mobileColumns"
                        :loading="loading.sum"
                        :full-pagination="[]"
                        :show-actions="false"
                        clickable
                        card-title-key="Nama"
                        empty-message="Belum ada anggota tim dengan penugasan dokumen."
                        row-key="Kode_Karyawan"
                        @row-click="openDrill"
                    >
                        <template #column-Nama="{ item }">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar">{{ initials(item.Nama) }}</span>
                                <div>
                                    <div class="fw-bold text-dark">{{ item.Nama }}</div>
                                    <div class="text-muted" style="font-size: 0.7rem">{{ item.Kode_Karyawan }}</div>
                                </div>
                            </div>
                        </template>
                        <template #column-Selesai="{ item }">
                            <span class="tnum">{{ item.Selesai }}/{{ item.Total }}</span>
                        </template>
                        <template #column-Terlambat="{ item }">
                            <span v-if="item.Terlambat > 0" class="late-pill">{{ item.Terlambat }}</span>
                            <span v-else class="text-muted">—</span>
                        </template>
                        <template #column-Persen="{ item }">
                            <div class="d-flex align-items-center gap-2">
                                <ProgressBar :value="item.Persen" style="flex: 1" />
                                <span class="small fw-bold tnum" :class="persenClass(item.Persen)"
                                    >{{ item.Persen }}%</span
                                >
                            </div>
                        </template>
                        <template #card-status="{ item }">{{ item.Persen }}%</template>
                        <template #mobile-Persen="{ item }"><ProgressBar :value="item.Persen" showLabel /></template>
                    </ModernDataTable>
                </div>
            </template>
        </div>

        <!-- Drill: dokumen satu anggota -->
        <SopModal
            :show="drill.show"
            :title="drill.nama"
            subtitle="Daftar Dokumen anggota"
            icon="bi-person-badge"
            size="lg"
            @close="drill.show = false"
        >
            <div v-if="loading.drill" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <template v-else>
                <div class="drill-table drill-scroll">
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Dokumen</th>
                                <th>Status</th>
                                <th>Tenggat</th>
                                <th>Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!drill.sop.length">
                                <td colspan="4" class="text-muted small py-3">Anggota ini belum ditugaskan dokumen.</td>
                            </tr>
                            <tr v-for="(s, i) in drill.sop" :key="i">
                                <td class="fw-semibold small">{{ s.Nama_Paket }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <StatusPill
                                            :text="statusLabel(s.Status_Paket)"
                                            :variant="statusVariant(s.Status_Paket)"
                                        />
                                        <span v-if="s.Is_Terlambat" class="late-pill">Terlambat</span>
                                    </div>
                                </td>
                                <td class="small text-muted">{{ s.Tenggat || 'Tanpa tenggat' }}</td>
                                <td class="small text-muted">{{ s.Tanggal_Selesai || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </SopModal>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import HeaderKpi from '@/components/legacy/header.vue';
import ModernDataTable from '@/components/legacy/table-noir.vue';
import ProgressBar from '../../Shared/components/ui/ProgressBar.vue';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import SopRing from '../../Shared/components/ui/SopRing.vue';
import SopModal from '../../Shared/components/ui/SopModal.vue';
import EmptyState from '../../Shared/components/ui/EmptyState.vue';
import { STATUS_PAKET } from '../../Shared/support/constants.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/tim';

/** Kepatuhan Tim Saya (leader) — read-only. */
export default {
    name: 'SopTeam',
    components: { HeaderKpi, ModernDataTable, ProgressBar, StatusPill, SopRing, SopModal, EmptyState },
    mixins: [pageMeta],
    data() {
        return {
            isLeader: false,
            kpi: { anggota: 0, coverage: 0, terlambat: 0 },
            anggota: [],
            loading: { sum: false, drill: false },
            drill: { show: false, nama: '', sop: [] },
            columns: [
                { key: 'Nama', title: 'Anggota', icon: 'bi-person', sticky: true },
                { key: 'Selesai', title: 'Selesai', align: 'center' },
                { key: 'Terlambat', title: 'Telat', align: 'center' },
                { key: 'Persen', title: 'Kepatuhan', width: '34%' },
            ],
            mobileColumns: [
                { key: 'Selesai', title: 'Selesai', col: 6 },
                { key: 'Terlambat', title: 'Telat', col: 6 },
                { key: 'Persen', title: 'Kepatuhan', col: 12 },
            ],
        };
    },
    mounted() {
        this.fetchRingkasan();
    },
    methods: {
        statusLabel(s) {
            return (STATUS_PAKET[s] || {}).label || s;
        },
        statusVariant(s) {
            return (STATUS_PAKET[s] || {}).variant || 'idle';
        },
        persenClass(p) {
            return p >= 80 ? 'text-success' : p >= 40 ? 'text-warning' : 'text-danger';
        },
        ckStyle(c) {
            return { '--ck': c };
        },
        initials(name) {
            return String(name || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },
        async fetchRingkasan() {
            this.loading.sum = true;
            try {
                const r = await axios.get(`${API}/ringkasan`);
                if (r.data.success) {
                    this.isLeader = r.data.result.is_leader;
                    this.kpi = r.data.result.kpi;
                    this.anggota = r.data.result.anggota;
                }
            } catch (e) {
                ElMessage.error('Gagal memuat kepatuhan tim');
            } finally {
                this.loading.sum = false;
            }
        },
        async openDrill(a) {
            this.drill = { show: true, nama: a.Nama, sop: [] };
            this.loading.drill = true;
            try {
                const r = await axios.get(`${API}/anggota/${encodeURIComponent(a.Kode_Karyawan)}`);
                if (r.data.success) this.drill.sop = r.data.result.sop;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat detail anggota');
            } finally {
                this.loading.drill = false;
            }
        },
    },
};
</script>

<style scoped>
.cockpit {
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    background: linear-gradient(135deg, var(--hcis-white), #f5f6ff);
}
.cockpit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.85rem;
    flex-grow: 1;
}
.ck-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #eef2f7;
    border-left: 3px solid var(--ck);
}
.ck-ico {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    color: var(--ck);
    background: color-mix(in srgb, var(--ck) 12%, transparent);
    flex-shrink: 0;
}
.ck-val {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1;
    color: var(--hcis-slate-800);
}
.ck-lab {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    margin-top: 2px;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.mini-table thead th {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400);
    font-weight: 800;
    padding: 0.5rem 0.6rem;
    border-bottom: 1px solid #eef2f7;
    text-align: left;
}
.mini-table tbody td {
    padding: 0.55rem 0.6rem;
    border-bottom: 1px solid #f4f6fa;
    vertical-align: middle;
}
.drill-scroll {
    max-height: 320px;
    overflow-y: auto;
}
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-400));
    color: #fff;
    font-size: 0.72rem;
    font-weight: 800;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.late-pill {
    display: inline-block;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--hcis-red-700);
    background: rgba(220, 38, 38, 0.12);
    border: 1px solid rgba(220, 38, 38, 0.2);
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    line-height: 1.4;
}
@media (max-width: 575px) {
    .cockpit {
        justify-content: center;
    }
    .cockpit-stats {
        grid-template-columns: 1fr;
        width: 100%;
    }
}
</style>
