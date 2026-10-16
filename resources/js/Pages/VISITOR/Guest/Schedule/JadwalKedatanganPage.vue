<template>
    <VisitorWorkspace
        title="Jadwal Kedatangan Tamu"
        subtitle="Agenda kedatangan tamu departemen Anda — pantau jadwal, kendaraan, dan status kehadiran secara real-time."
        kicker="Visitor · Jadwal Departemen"
        icon="calendar-week"
        sentiment="brand"
        :stats="statCards"
        :stat-columns="4"
        section-title="Buku Tamu & Jadwal Kedatangan"
        section-description="Seluruh agenda tamu dari departemen Anda ditampilkan secara terpadu sesuai matriks hak akses."
        section-icon="calendar-event-fill"
        @stat-click="tanganiKlikStat"
    >
        <template #actions>
            <div class="d-flex align-items-center gap-2">
                <span v-if="waktuTerakhirDiperbarui" class="jk-last-updated-text font-tabular">
                    <HcisIcon name="clock-history" size="2xs" />
                    <span>Diperbarui {{ waktuTerakhirDiperbarui }}</span>
                </span>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    label="Segarkan"
                    :loading="memuat"
                    title="Segarkan data jadwal kedatangan"
                    @click="refreshData"
                />
            </div>
        </template>

        <template #context>
            <!-- Department Access Matrix (Standard HCIS Design System HcisCard) -->
            <HcisCard
                v-if="departemen.length"
                sentiment="brand"
                variant="glass"
                size="sm"
                icon="shield-lock-fill"
                class="mb-3"
            >
                <template #title>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-bold text-slate-900 text-sm">Akses Departemen:</span>
                        <HcisBadge
                            v-for="d in departemen"
                            :key="`dept-tag-${d.nama}`"
                            :label="d.nama"
                            icon="buildings"
                            variant="neutral"
                            size="sm"
                            appearance="subtle"
                        />
                    </div>
                </template>

                <template #actions>
                    <HcisButton
                        size="xs"
                        variant="secondary"
                        :leading-icon="aksesTerbuka ? 'chevron-up' : 'info-circle'"
                        :label="aksesTerbuka ? 'Sembunyikan Rincian' : 'Rincian Hak Akses'"
                        @click="aksesTerbuka = !aksesTerbuka"
                    />
                </template>

                <!-- Scopes Row: Lihat & Ajukan -->
                <div class="jk-card-scopes">
                    <div class="jk-card-scope-item">
                        <span class="jk-card-scope-label">
                            <HcisIcon name="eye-fill" size="2xs" class="text-indigo-600" />
                            <span>Dapat Melihat:</span>
                        </span>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <HcisBadge
                                v-for="k in gabunganLihat"
                                :key="`gl-${k}`"
                                :label="k"
                                variant="brand"
                                size="sm"
                                appearance="subtle"
                            />
                        </div>
                    </div>

                    <div v-if="gabunganAjukan.length" class="jk-card-scope-item">
                        <span class="jk-card-scope-label">
                            <HcisIcon name="plus-circle-fill" size="2xs" class="text-emerald-600" />
                            <span>Boleh Mengajukan:</span>
                        </span>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <HcisBadge
                                v-for="k in gabunganAjukan"
                                :key="`ga-${k}`"
                                :label="k"
                                variant="success"
                                size="sm"
                                appearance="subtle"
                            />
                        </div>
                    </div>
                </div>

                <!-- Expandable Detailed Matrix Breakdown inside HcisCard -->
                <Transition name="jk-matrix-expand">
                    <div v-if="aksesTerbuka" class="jk-card-matrix-detail mt-3 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2.5 flex-wrap">
                            <div class="d-flex align-items-center gap-1.5 text-xs text-slate-700 fw-bold">
                                <HcisIcon name="diagram-3-fill" size="2xs" class="text-indigo-600" />
                                <span>Rincian Wewenang per Role Departemen</span>
                            </div>
                            <span class="text-2xs text-muted">Disaring otomatis oleh server (MatriksTamuService)</span>
                        </div>

                        <div class="jk-matrix-cards-grid">
                            <div
                                v-for="d in departemen"
                                :key="`matrix-card-${d.nama}`"
                                class="jk-matrix-role-card"
                            >
                                <div class="jk-matrix-role-card__header">
                                    <div class="jk-matrix-role-card__icon">
                                        <HcisIcon name="person-workspace" size="2xs" />
                                    </div>
                                    <strong class="jk-matrix-role-card__name">{{ d.nama }}</strong>
                                </div>

                                <div class="jk-matrix-role-card__body">
                                    <!-- Read column -->
                                    <div class="jk-matrix-capability">
                                        <span class="jk-matrix-capability__title">
                                            <HcisIcon name="eye" size="2xs" class="text-indigo-600" />
                                            <span>Melihat Agenda</span>
                                        </span>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <HcisBadge
                                                v-for="k in d.lihat"
                                                :key="`l-${d.nama}-${k}`"
                                                :label="k"
                                                variant="brand"
                                                size="xs"
                                                appearance="subtle"
                                            />
                                        </div>
                                    </div>

                                    <!-- Write column -->
                                    <div class="jk-matrix-capability">
                                        <span class="jk-matrix-capability__title">
                                            <HcisIcon name="pencil" size="2xs" class="text-emerald-600" />
                                            <span>Pengajuan Baru</span>
                                        </span>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <template v-if="d.ajukan && d.ajukan.length">
                                                <HcisBadge
                                                    v-for="k in d.ajukan"
                                                    :key="`a-${d.nama}-${k}`"
                                                    :label="k"
                                                    variant="success"
                                                    size="xs"
                                                    appearance="subtle"
                                                />
                                            </template>
                                            <span v-else class="text-2xs text-slate-400 fst-italic">
                                                Hanya baca (Read-only)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </HcisCard>

            <HcisAlert
                v-if="statsError"
                variant="warning"
                title="Ringkasan belum dapat diperbarui"
                :description="statsError"
                closable
                class="mt-3"
                @close="statsError = ''"
            />
        </template>

        <HcisEmptyState
            v-if="!kategori.length"
            title="Departemen Anda belum terdaftar"
            description="Jadwal kedatangan tamu tampil sesuai matriks departemen. Hubungi admin Visitor untuk didaftarkan ke role departemen Anda."
            icon="person-lock"
        />

        <template v-else>
            <TamuHistory
                ref="historyRef"
                api-prefix="jadwal-kedatangan-tamu"
                title="Agenda Kedatangan Tamu"
                :read-only="true"
                :schedule-mode="true"
                :kategori-options="kategori"
                @tamu-updated="loadStats"
            />
        </template>
    </VisitorWorkspace>
</template>

<script>
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisIcon,
} from '@/components/ui';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import { bacaRujukanDetail } from '@/components/VISITOR/tautanDetail';
import TamuHistory from '@/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue';

export default {
    name: 'JadwalKedatanganPage',
    components: {
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisEmptyState,
        HcisIcon,
        VisitorWorkspace,
        TamuHistory,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        serverDate: { type: String, default: null },
        // Kategori yang diizinkan untuk departemen pengguna (sudah difilter di server).
        kategori: { type: Array, default: () => [] },
        // Daftar role departemen pengguna & haknya (nama, lihat[], ajukan[]).
        departemen: { type: Array, default: () => [] },
    },
    data() {
        return {
            aksesTerbuka: false,
            memuat: false,
            waktuTerakhirDiperbarui: '',
            stats: {
                sedang_di_area: 0,
                hari_ini: 0,
                sudah_disetujui: 0,
                menunggu_persetujuan: 0,
                selesai_keluar: 0,
                total: 0,
            },
            statsLoading: true,
            statsError: '',
        };
    },
    computed: {
        gabunganLihat() {
            const set = new Set();
            for (const d of this.departemen) {
                for (const k of d.lihat || []) set.add(k);
            }
            return Array.from(set);
        },
        gabunganAjukan() {
            const set = new Set();
            for (const d of this.departemen) {
                for (const k of d.ajukan || []) set.add(k);
            }
            return Array.from(set);
        },
        statCards() {
            return [
                {
                    key: 'active',
                    chipValue: 'CHECKED_IN',
                    label: 'Check-In (Aktif)',
                    value: this.stats.sedang_di_area,
                    suffix: 'Tamu',
                    icon: 'geo-alt-fill',
                    iconAnimation: this.stats.sedang_di_area ? 'pulse' : '',
                    sentiment: 'brand',
                    pattern: 'crystal',
                    caption: this.stats.sedang_di_area > 0 ? 'Tamu Aktif di Lokasi' : 'Belum Ada Tamu di Lokasi',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    key: 'today',
                    chipValue: 'TODAY',
                    label: 'Kedatangan Hari Ini',
                    value: this.stats.hari_ini,
                    suffix: 'Tamu',
                    icon: 'calendar-event-fill',
                    sentiment: 'success',
                    pattern: 'grid',
                    caption: this.stats.hari_ini > 0 ? 'Jadwal Tamu Hari Ini' : 'Tidak Ada Agenda Hari Ini',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    key: 'scheduled',
                    chipValue: 'SCHEDULED',
                    label: 'Scheduled',
                    value: this.stats.sudah_disetujui,
                    suffix: 'Tamu',
                    icon: 'calendar-check-fill',
                    sentiment: 'primary',
                    pattern: 'dots',
                    caption: 'Siap Masuk / Izin Aktif',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    key: 'done',
                    chipValue: 'CHECKED_OUT',
                    label: 'Check-Out (Selesai)',
                    value: this.stats.selesai_keluar,
                    suffix: 'Tamu',
                    icon: 'box-arrow-right',
                    sentiment: 'neutral',
                    pattern: 'crosshair',
                    caption: 'Kunjungan Telah Selesai',
                    interactive: true,
                    loading: this.statsLoading,
                },
            ];
        },
    },
    mounted() {
        this.waktuTerakhirDiperbarui = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        this.loadStats();
        this.bukaDetailDariTautan();
    },
    methods: {
        tanganiKlikStat(stat) {
            if (stat?.chipValue && this.$refs.historyRef) {
                this.$refs.historyRef.onChipClick({ value: stat.chipValue });
            }
        },
        async loadStats() {
            this.statsLoading = true;
            this.statsError = '';
            try {
                const response = await fetch('/jadwal-kedatangan-tamu-stats');
                const json = await response.json();
                if (!response.ok || !json.success) {
                    throw new Error('Respons statistik tidak valid.');
                }
                this.stats = {
                    sedang_di_area: json.result.sedang_di_area || 0,
                    sudah_disetujui: json.result.sudah_disetujui || 0,
                    menunggu_persetujuan: json.result.menunggu_persetujuan || 0,
                    selesai_keluar: json.result.selesai_keluar || 0,
                    total: json.result.total || 0,
                };
            } catch (error) {
                this.statsError = 'Ringkasan jadwal belum dapat diperbarui. Data utama tetap dapat digunakan.';
            } finally {
                this.statsLoading = false;
            }
        },
        async refreshData() {
            this.memuat = true;
            this.waktuTerakhirDiperbarui = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            try {
                await Promise.all([
                    this.loadStats(),
                    this.$refs.historyRef?.fetchData(),
                ]);
            } finally {
                this.memuat = false;
            }
        },
        bukaDetailDariTautan() {
            const rujukan = bacaRujukanDetail(window.location.search);
            if (!rujukan) {
                return;
            }
            this.$nextTick(() => {
                this.$refs.historyRef?.viewDetail({ Id: rujukan });
            });
        },
    },
};
</script>

<style scoped>
/* ══ 1. CONTEXT / DEPT ACCESS (HCIS DESIGN SYSTEM) ══ */
.jk-card-scopes {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.jk-card-scope-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.jk-card-scope-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
}

.jk-matrix-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 0.75rem;
}

.jk-matrix-role-card {
    background: var(--hcis-surface-sunken, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.08));
    border-radius: var(--hcis-radius-md, 0.5rem);
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.jk-matrix-role-card__header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.06));
}

.jk-matrix-role-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: var(--hcis-radius-sm, 0.25rem);
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
}

.jk-matrix-role-card__name {
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.jk-matrix-role-card__body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.jk-matrix-capability {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.jk-matrix-capability__title {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
}

/* Transition */
.jk-matrix-expand-enter-active,
.jk-matrix-expand-leave-active {
    transition: all 0.2s ease-in-out;
    max-height: 500px;
    overflow: hidden;
}

.jk-matrix-expand-enter-from,
.jk-matrix-expand-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}

.jk-last-updated-text {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.25rem 0.5rem;
    background: var(--hcis-surface-sunken, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.06));
    border-radius: var(--hcis-radius-sm, 0.25rem);
}
</style>
