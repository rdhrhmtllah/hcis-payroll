<template>
    <div class="sop-stack">
        <!-- AI Cockpit Pulse Banner -->
        <transition name="sop-swap">
            <div v-if="pulse.sejak" class="zs-pulse-banner sop-fade-up">
                <div class="zs-ico-box">
                    <i class="bi bi-stars"></i>
                </div>
                <div class="zs-teks">
                    <div class="d-flex align-items-center gap-2">
                        <strong class="zs-pulse-title">Sejak kunjungan terakhir Anda</strong>
                        <span class="zs-pulse-badge">Kabar Terbaru</span>
                    </div>
                    <div class="zs-chips mt-1.5">
                        <span v-if="pulse.sejak.selesai" class="zs-chip ok">
                            <i class="bi bi-check-circle-fill me-1.5"></i><strong>{{ pulse.sejak.selesai }}</strong> penugasan tuntas
                        </span>
                        <span v-if="pulse.sejak.dibuka_lagi" class="zs-chip warn">
                            <i class="bi bi-arrow-repeat me-1.5"></i><strong>{{ pulse.sejak.dibuka_lagi }}</strong> dibuka ulang (versi baru)
                        </span>
                        <span v-if="pulse.sejak.kadaluarsa" class="zs-chip danger">
                            <i class="bi bi-exclamation-triangle-fill me-1.5"></i><strong>{{ pulse.sejak.kadaluarsa }}</strong> kedaluwarsa
                        </span>
                        <span v-if="pulse.sejak.dicabut" class="zs-chip muted">
                            <i class="bi bi-x-circle-fill me-1.5"></i><strong>{{ pulse.sejak.dicabut }}</strong> akses dicabut
                        </span>
                    </div>
                </div>
                <button class="zs-btn-dibaca" title="Tandai sudah dibaca" @click="tandaiDibaca">
                    <i class="bi bi-check2-all me-1"></i>Tandai dibaca
                </button>
            </div>
        </transition>

        <!-- KPI QuickStat Container (HCIS Design System) -->
        <div class="sop-fade-up zs-stat-wrapper">
            <QuickStat :stats="quickStatItems" />
        </div>

        <!-- Antrean Kerja (Triage Hub) — Proaktif & Terintegrasi -->
        <div class="sop-fade-up">
            <HcisCard
                variant="glass"
                sentiment="brand"
                size="md"
                icon="lightning-charge-fill"
                title="Antrean Perlu Tindakan"
                description="Pusat triage operasional untuk memantau dan mendorong kepatuhan secara proaktif."
            >
                <template #actions>
                    <span class="zs-hint-badge">
                        <i class="bi bi-cursor-fill me-1 text-primary"></i>
                        Klik kartu untuk menyaring otomatis
                    </span>
                </template>

                <div class="zs-triage-grid">
                    <button
                        v-for="k in kartu"
                        :key="k.key"
                        type="button"
                        class="zs-tri-card"
                        :class="[`t-${k.tone}`, { kosong: !k.nilai }]"
                        @click="buka(k)"
                    >
                        <span class="zs-tri-accent" aria-hidden="true"></span>

                        <span class="zs-tri-ico"><i class="bi" :class="k.icon"></i></span>

                        <span class="zs-tri-body">
                            <span class="zs-tri-val">
                                {{ k.nilai }}
                                <span v-if="k.nilai > 0 && k.key === 'terlambat'" class="zs-dot danger ms-1"></span>
                            </span>
                            <span class="zs-tri-label">{{ k.label }}</span>
                            <span class="zs-tri-desc">{{ k.hint }}</span>
                        </span>

                        <span class="zs-tri-action">
                            <span
                                v-if="k.ingatkan && k.nilai > 0"
                                class="zs-tri-btn"
                                role="button"
                                tabindex="0"
                                :title="`Kirim pengingat ke semua (${k.nilai})`"
                                @click.stop="ingatkan(k)"
                                @keydown.enter.stop="ingatkan(k)"
                            >
                                <span v-if="mengirim === k.key" class="spinner-border spinner-border-sm me-1"></span>
                                <template v-else><i class="bi bi-bell-fill me-1"></i> Ingatkan</template>
                            </span>
                            <i v-else class="bi bi-arrow-right zs-tri-go"></i>
                        </span>
                    </button>
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { HcisCard, HcisButton, HcisBadge } from '@/components/ui';
import QuickStat from '@/components/legacy/quickStat.vue';

const API = '/api/v1/sop/admin/monitoring';
const KUNCI_KUNJUNGAN = 'sop_mon_kunjungan';

/**
 * Zona 1 — "Apa keadaan hari ini?"
 * Menampilkan QuickStat KPI dan kartu antrean kerja.
 */
export default {
    name: 'ZonaSekarang',
    components: { QuickStat, HcisCard, HcisButton, HcisBadge },
    props: {
        // Saringan tab Ringkasan. Diteruskan apa adanya ke setiap panggilan
        // supaya kartu di zona ini tak bisa bicara tentang himpunan yang
        // berbeda dari kartu di zona lain pada layar yang sama.
        saringan: { type: Object, default: () => ({}) },
        totalKilat: { type: Number, default: 0 },
    },
    emits: ['buka-tab', 'lencana', 'gulung', 'buka-drawer'],
    data() {
        return {
            summary: { coverage: 0, paket_aktif: 0, tertunda: 0, jatuh_tempo: 0, terlambat: 0 },
            pulse: { selesai_7hr: { kini: 0, lalu: null, delta: null }, sparkline: [], sejak: null },
            mengirim: null,
        };
    },
    computed: {
        quickStatItems() {
            const d = this.pulse.selesai_7hr?.delta;

            return [
                {
                    title: 'Tingkat Penyelesaian',
                    value: `${Number(this.summary.coverage) || 0}%`,
                    unit: d === null || d === undefined ? '' : `${d >= 0 ? '+' : ''}${d} vs 7hr`,
                    icon: 'bi bi-shield-check',
                    bgIcon: 'bg-success-light',
                    size: 'col-6 col-md-3',
                },
                {
                    title: 'Paket Aktif',
                    value: this.summary.paket_aktif || 0,
                    unit: 'paket',
                    icon: 'bi bi-collection-fill',
                    bgIcon: 'bg-purple-light',
                    size: 'col-6 col-md-3',
                },
                {
                    title: 'Masih Tertunda',
                    value: this.summary.tertunda || 0,
                    unit: 'penugasan',
                    icon: 'bi bi-hourglass-split',
                    bgIcon: 'bg-warning-light',
                    size: 'col-6 col-md-3',
                },
                {
                    title: 'Terlambat',
                    value: this.summary.terlambat || 0,
                    unit: 'penugasan',
                    icon: 'bi bi-alarm-fill',
                    bgIcon: 'bg-danger-light',
                    size: 'col-6 col-md-3',
                },
            ];
        },
        kartu() {
            return [
                {
                    key: 'terlambat',
                    tone: 'bahaya',
                    icon: 'bi-exclamation-octagon-fill',
                    nilai: this.summary.terlambat,
                    label: 'Penugasan Terlambat',
                    hint: 'Sudah lewat tenggat & belum selesai',
                    tab: 'karyawan',
                    filters: { status: 'terlambat' },
                    ingatkan: { status: 'terlambat' },
                },
                {
                    key: 'jatuh_tempo',
                    tone: 'waspada',
                    icon: 'bi-alarm-fill',
                    nilai: this.summary.jatuh_tempo,
                    label: 'Tenggat ≤ 7 Hari',
                    hint: 'Perlu didorong sebelum lewat',
                    tab: 'karyawan',
                    filters: { status: 'progress' },
                    ingatkan: { status: 'progress' },
                },
                {
                    key: 'kilat',
                    tone: 'bahaya',
                    icon: 'bi-lightning-charge-fill',
                    nilai: this.totalKilat,
                    label: 'Konfirmasi Terlalu Cepat',
                    hint: 'Terlalu cepat untuk benar-benar dibaca',
                    gulung: 'tindakan',
                },
            ];
        },
    },
    mounted() {
        this.fetchPulse();
        this.fetchSummary();
    },
    watch: {
        saringan: {
            deep: true,
            handler() {
                this.fetchPulse();
                this.fetchSummary();
            },
        },
    },
    methods: {
        buka(k) {
            if (k.gulung) this.$emit('gulung', k.gulung);
            else this.$emit('buka-tab', { tab: k.tab, filters: k.filters });
        },
        async fetchPulse() {
            let sejak = null;
            try {
                sejak = localStorage.getItem(KUNCI_KUNJUNGAN);
            } catch (e) {
                /* diblokir */
            }
            try {
                const r = await axios.get(`${API}/pulse`, { params: { sejak: sejak || undefined, ...this.saringan } });
                if (r.data.success) this.pulse = { ...this.pulse, ...r.data.result };
            } catch (e) {
                /* silent */
            }
        },
        tandaiDibaca() {
            try {
                localStorage.setItem(KUNCI_KUNJUNGAN, new Date().toISOString());
            } catch (e) {
                /* diblokir */
            }
            this.pulse = { ...this.pulse, sejak: null };
        },
        async ingatkan(k) {
            try {
                await ElMessageBox.confirm(
                    `<p>Kirim pengingat ke <strong>${k.nilai} karyawan</strong> pada antrean “${k.label}”?</p>
                     <p class="text-muted small mb-0">Notifikasi masuk ke aplikasi mereka. Hanya penugasan yang
                     BELUM tuntas yang dikirimi — yang sudah selesai tak akan diganggu.</p>`,
                    'Kirim pengingat',
                    {
                        dangerouslyUseHTMLString: true,
                        confirmButtonText: 'Ya, kirim',
                        cancelButtonText: 'Batal',
                        type: 'warning',
                    },
                );
            } catch (e) {
                return;
            }

            this.mengirim = k.key;
            try {
                const r = await axios.post(`${API}/ingatkan`, { filter: k.ingatkan });
                if (r.data.success) {
                    ElMessage.success(
                        `${r.data.result.terkirim} pengingat terkirim ke ${r.data.result.karyawan} karyawan.`,
                    );
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengirim pengingat');
            } finally {
                this.mengirim = null;
            }
        },
        async fetchSummary() {
            try {
                const r = await axios.get(`${API}/summary`, { params: { ...this.saringan } });
                if (r.data.success) {
                    this.summary = r.data.result;
                    this.$emit('lencana', { key: 'karyawan', value: Number(this.summary.terlambat) || 0 });
                }
            } catch (e) {
                /* silent */
            }
        },
    },
};
</script>

<style scoped>
.zs-card {
    position: relative;
    border-radius: 20px;
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 10px 28px -8px rgba(15, 23, 42, 0.07);
    padding: 1.4rem 1.6rem;
}

.zs-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
    margin-bottom: 0.2rem;
}

.zs-h {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

.zs-hint-badge {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
}

.zs-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-red-500);
    box-shadow: 0 0 8px var(--hcis-red-500);
}

/* Triage Grid */
.zs-triage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
}

.zs-tri-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    text-align: left;
    padding: 1.15rem 1.35rem 1.15rem 1.5rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    background: var(--hcis-white);
    cursor: pointer;
    overflow: hidden;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.zs-tri-card:hover {
    transform: translateY(-3px);
    border-color: rgba(var(--tri-rgb), 0.4);
    box-shadow: 0 14px 28px -10px rgba(var(--tri-rgb), 0.25);
}

.zs-tri-card.kosong {
    opacity: 0.65;
}
.zs-tri-card.kosong:hover {
    opacity: 1;
}

.zs-tri-accent {
    position: absolute;
    inset: 0 auto 0 0;
    width: 5px;
    background: linear-gradient(180deg, rgb(var(--tri-rgb)), rgba(var(--tri-rgb), 0.4));
}

.zs-tri-ico {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 1.3rem;
    color: rgb(var(--tri-rgb));
    background: rgba(var(--tri-rgb), 0.1);
    transition: transform 0.2s ease;
}
.zs-tri-card:hover .zs-tri-ico {
    transform: scale(1.08);
}

.zs-tri-body {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.zs-tri-val {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    color: var(--hcis-slate-900);
    font-variant-numeric: tabular-nums;
}

.zs-tri-label {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    margin-top: 0.2rem;
}

.zs-tri-desc {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    margin-top: 0.05rem;
}

.zs-tri-action {
    margin-left: auto;
    flex-shrink: 0;
}

.zs-tri-btn {
    display: inline-flex;
    align-items: center;
    border: 1px solid rgba(var(--tri-rgb), 0.3);
    background: rgba(var(--tri-rgb), 0.08);
    color: rgb(var(--tri-rgb));
    border-radius: 999px;
    padding: 0.35rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.18s ease;
}

.zs-tri-btn:hover {
    background: rgb(var(--tri-rgb));
    color: var(--hcis-white);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--tri-rgb), 0.3);
}

.zs-tri-go {
    color: var(--hcis-slate-300);
    font-size: 1.15rem;
    transition: all 0.18s ease;
}

.zs-tri-card:hover .zs-tri-go {
    color: rgb(var(--tri-rgb));
    transform: translateX(4px);
}

.t-bahaya {
    --tri-rgb: 239, 68, 68;
}
.t-waspada {
    --tri-rgb: 245, 158, 11;
}

/* Cockpit Pulse Banner */
.zs-pulse-banner {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    padding: 1.1rem 1.35rem;
    border-radius: 20px;
    background: linear-gradient(
        135deg,
        rgba(99, 102, 241, 0.1) 0%,
        rgba(16, 185, 129, 0.08) 50%,
        rgba(245, 158, 11, 0.06) 100%
    );
    border: 1px solid rgba(99, 102, 241, 0.22);
    box-shadow: 0 10px 28px -10px rgba(99, 102, 241, 0.15);
}

.zs-ico-box {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    flex-shrink: 0;
    box-shadow: 0 6px 16px -4px rgba(79, 70, 229, 0.35);
    font-size: 1.3rem;
}

.zs-teks {
    min-width: 0;
    flex: 1;
}

.zs-pulse-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-indigo-950);
}

.zs-pulse-badge {
    background-color: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-700);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 800;
}

.zs-chips {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.zs-chip {
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.22rem 0.7rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
}

.zs-chip.ok {
    background: rgba(16, 185, 129, 0.14);
    color: var(--hcis-emerald-700);
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.zs-chip.warn {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-700);
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.zs-chip.danger {
    background: rgba(239, 68, 68, 0.14);
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.zs-chip.muted {
    background: rgba(148, 163, 184, 0.18);
    color: var(--hcis-slate-700);
    border: 1px solid rgba(148, 163, 184, 0.25);
}

.zs-btn-dibaca {
    margin-left: auto;
    border: 1px solid rgba(99, 102, 241, 0.3);
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    padding: 0.45rem 0.95rem;
    border-radius: 12px;
    cursor: pointer;
    flex-shrink: 0;
    font-size: 0.78rem;
    font-weight: 800;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.zs-btn-dibaca:hover {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    border-color: var(--hcis-indigo-600);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
}
</style>
