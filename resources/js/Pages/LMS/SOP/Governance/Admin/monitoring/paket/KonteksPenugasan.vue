<template>
    <div class="row g-3">
        <!-- ══ KARTU 1: ALUR PENUGASAN & TENGGAT WAKTU (Col 7) ══ -->
        <div class="col-12 col-lg-7">
            <HcisCard
                size="sm"
                variant="glass"
                sentiment="brand"
                icon="calendar2-check"
                title="Konteks Penugasan & Tenggat Waktu"
                description="Status jadwal, batas waktu penyelesaian, dan riwayat stempel aktivitas"
                :badge="p.Sumber ? `Sumber ${p.Sumber}` : undefined"
                badge-variant="brand"
                class="h-100"
            >
                <!-- Banner Tenggat Kepatuhan -->
                <div class="kp-tenggat mb-3" :class="{ telat: p.Is_Terlambat, bebas: tanpaTenggat }">
                    <span class="kp-tenggat-ico">
                        <i
                            class="bi"
                            :class="tanpaTenggat ? 'bi-infinity' : p.Is_Terlambat ? 'bi-alarm-fill' : 'bi-calendar-check-fill'"
                        ></i>
                    </span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="kp-tenggat-val">
                            <template v-if="tanpaTenggat">Berlaku Tanpa Batas Tenggat</template>
                            <template v-else>{{ tgl(p.Tenggat) }}</template>
                        </div>
                        <div class="kp-tenggat-sub">
                            <template v-if="tanpaTenggat">Akses aktif hingga dicabut oleh admin atau sistem</template>
                            <template v-else-if="p.Is_Terlambat">
                                Terlambat {{ Math.abs(p.Hari_Tersisa) }} hari melewati batas tenggat
                            </template>
                            <template v-else-if="p.Hari_Tersisa !== null && p.Hari_Tersisa !== undefined">
                                Tersisa {{ p.Hari_Tersisa }} hari sebelum tenggat berakhir
                            </template>
                            <template v-else>Tenggat penugasan tercatat</template>
                        </div>
                    </div>
                    <span class="kp-status" :class="`s-${String(p.Status_Paket || '').toLowerCase()}`">
                        {{ p.Status_Paket || 'BERJALAN' }}
                    </span>
                </div>

                <!-- 4 Stempel Jejak Waktu Kronologis -->
                <h6 class="small fw-bold text-dark mb-2">
                    <i class="bi bi-clock-history me-1 text-primary"></i>4 Stempel Jejak Waktu Aktivitas
                </h6>
                <div class="kp-jejak">
                    <div v-for="j in jejak" :key="j.label" class="kp-jejak-sel" :class="{ hampa: !j.nilai }">
                        <div class="d-flex align-items-center gap-1.5 mb-1">
                            <i class="bi" :class="j.icon" style="font-size: 0.8rem"></i>
                            <span class="kp-jejak-lab">{{ j.label }}</span>
                        </div>
                        <span class="kp-jejak-val tnum">{{ j.nilai || j.kosong }}</span>
                    </div>
                </div>
            </HcisCard>
        </div>

        <!-- ══ KARTU 2: LEGALITAS & KELENGKAPAN DOKUMEN (Col 5) ══ -->
        <div class="col-12 col-lg-5">
            <HcisCard
                size="sm"
                variant="glass"
                sentiment="purple"
                icon="patch-check"
                title="Legalitas & Kelengkapan Dokumen"
                description="Status kelengkapan metadata resmi paket dokumen"
                class="h-100"
            >
                <!-- Kelengkapan Dokumen Matrix -->
                <div class="kp-dok mb-3">
                    <div v-for="d in dokumen" :key="d.label" class="kp-dok-sel">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="kp-dok-val tnum">{{ d.nilai }}</span>
                            <i class="bi" :class="d.icon" style="font-size: 1.1rem; opacity: 0.7"></i>
                        </div>
                        <span class="kp-dok-lab">{{ d.label }}</span>
                    </div>
                </div>

                <!-- Catatan / Warning Metadata (Jika Ada) -->
                <div v-if="catatan.length" class="kp-catatan p-2.5 bg-warning-subtle border border-warning-subtle rounded-3">
                    <div class="kp-catatan-judul text-warning-emphasis fw-bold mb-1.5">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                        Metadata yang Belum Dilengkapi
                    </div>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <span v-for="c in catatan" :key="c" class="kp-catatan-pil">
                            {{ c }}
                        </span>
                    </div>
                </div>
                <div v-else class="p-2.5 bg-success-subtle border border-success-subtle rounded-3 text-success small fw-semibold">
                    <i class="bi bi-check-circle-fill me-1"></i> Seluruh metadata legalitas dokumen telah lengkap.
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import { HcisCard } from '@/components/ui';

/** Kode peringatan metadata → kalimat yang bisa dibaca. */
const LABEL_CATATAN = {
    KODE_PAKET_KOSONG: 'Kode paket belum diisi',
    OWNER_BELUM_TERCATAT: 'Pemilik paket belum dicatat',
    PUBLISHER_DOKUMEN_BELUM_TERCATAT: 'Publisher dokumen belum dicatat',
    PENGESAHAN_DOKUMEN_BELUM_TERCATAT: 'Pengesahan dokumen belum dicatat',
    TANGGAL_BERLAKU_BELUM_TERCATAT: 'Tanggal berlaku dokumen belum diatur',
};

export default {
    name: 'KonteksPenugasan',
    components: { HcisCard },
    props: {
        penugasan: { type: Object, default: () => ({}) },
        bukti: { type: Object, default: () => ({}) },
        paketResmi: { type: Object, default: () => ({}) },
    },
    computed: {
        p() {
            return this.penugasan || {};
        },
        tanpaTenggat() {
            return this.p.Tipe_Tenggat === 'SAMPAI_DICABUT' || !this.p.Tenggat;
        },
        jejak() {
            const b = this.bukti || {};

            return [
                {
                    label: 'Ditugaskan',
                    nilai: this.tgl(b.tanggal_assign || this.p.Tanggal_Assign),
                    kosong: '—',
                    icon: 'bi-send-check text-primary',
                },
                {
                    label: 'Pertama dibuka',
                    nilai: this.tglJam(b.pertama_dibuka),
                    kosong: 'Belum pernah dibuka',
                    icon: 'bi-box-arrow-in-right text-info',
                },
                {
                    label: 'Konfirmasi terakhir',
                    nilai: this.tglJam(b.konfirmasi_terakhir),
                    kosong: 'Belum ada konfirmasi',
                    icon: 'bi-clock text-warning',
                },
                {
                    label: 'Selesai',
                    nilai: this.tgl(b.tanggal_selesai || this.p.Tanggal_Selesai),
                    kosong: 'Belum selesai',
                    icon: 'bi-check2-circle text-success',
                },
            ];
        },
        dokumen() {
            const r = this.paketResmi || {};

            return [
                { label: 'Total Dokumen', nilai: r.dokumen_total ?? 0, icon: 'bi-files text-primary' },
                { label: 'Disahkan', nilai: r.dokumen_disahkan ?? 0, icon: 'bi-patch-check-fill text-success' },
                { label: 'Publisher Tercatat', nilai: r.dokumen_dengan_publisher ?? 0, icon: 'bi-building text-info' },
                { label: 'Tanggal Berlaku Valid', nilai: r.dokumen_dengan_validitas ?? 0, icon: 'bi-calendar-check text-purple' },
            ];
        },
        catatan() {
            return ((this.paketResmi || {}).metadata_warnings || []).map((k) => LABEL_CATATAN[k] || k);
        },
    },
    methods: {
        tgl(d) {
            if (!d) return '';
            const x = new Date(d);

            return isNaN(x) ? d : x.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        tglJam(d) {
            if (!d) return '';
            const tanggal = this.tgl(String(d).slice(0, 10));
            const jam = String(d).slice(11, 16);

            return jam ? `${tanggal} ${jam}` : tanggal;
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.kp-tenggat {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.kp-tenggat.telat {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
}

.kp-tenggat.bebas {
    border-color: var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.kp-tenggat-ico {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}

.kp-tenggat.telat .kp-tenggat-ico {
    background: rgba(220, 38, 38, 0.12);
    color: var(--hcis-red-700);
}

.kp-tenggat-val {
    font-size: 0.96rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.2;
}

.kp-tenggat-sub {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    margin-top: 0.1rem;
}

.kp-tenggat.telat .kp-tenggat-sub {
    color: var(--hcis-red-700);
    font-weight: 650;
}

.kp-status {
    margin-left: auto;
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
}

.kp-status.s-selesai {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.kp-status.s-progress {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    border-color: rgba(199, 210, 254, 0.8);
}

.kp-status.s-belum {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
    border-color: var(--hcis-amber-200);
}

/* ══ Jejak Waktu Grid ══ */
.kp-jejak {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.65rem;
}

.kp-jejak-sel {
    display: flex;
    flex-direction: column;
    padding: 0.65rem 0.8rem;
    border-radius: 10px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.kp-jejak-sel.hampa .kp-jejak-val {
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.kp-jejak-lab {
    font-size: 0.66rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.kp-jejak-val {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

/* ══ Dokumen Matrix ══ */
.kp-dok {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.65rem;
}

.kp-dok-sel {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.75rem 0.85rem;
    border-radius: 12px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.kp-dok-val {
    font-size: 1.25rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    line-height: 1.1;
}

.kp-dok-lab {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

/* ══ Catatan / Warning ══ */
.kp-catatan-pil {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 999px;
    padding: 0.15rem 0.6rem;
}
</style>
