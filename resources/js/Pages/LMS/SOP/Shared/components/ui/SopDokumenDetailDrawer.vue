<template>
    <SopModal
        :show="show"
        :title="dokumen?.Judul || 'Detail Dokumen'"
        subtitle="Dokumen terkendali — paket pemakai & riwayat versi"
        icon="bi-file-earmark-text"
        size="lg"
        @close="$emit('close')"
    >
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else-if="gagal" class="alert alert-warning small mb-0">
            {{ gagal }}
        </div>

        <div v-else class="dd-stack">
            <!-- Identitas dokumen -->
            <section class="dd-sec">
                <div class="dd-head">
                    <span class="dd-eyebrow"><i class="bi bi-file-earmark-text"></i> Identitas</span>
                </div>
                <div class="dd-grid">
                    <div v-if="pakaiNomorResmi" class="dd-f">
                        <span class="dd-lab">Nomor Dokumen</span>
                        <span class="dd-val">{{ dokumen?.Nomor_SOP_Resmi || '—' }}</span>
                    </div>
                    <div class="dd-f">
                        <span class="dd-lab">Tipe</span>
                        <span class="dd-val">{{ dokumen?.Tipe || '—' }}</span>
                    </div>
                    <div class="dd-f dd-f-wide">
                        <span class="dd-lab">Deskripsi</span>
                        <span class="dd-val">{{ dokumen?.Deskripsi || '—' }}</span>
                    </div>
                </div>
                <p class="dd-note">
                    Penyuntingan identitas dokumen belum tersedia. Item masih membawa judul &amp; nomornya sendiri —
                    mengubah salah satunya saja akan menghasilkan dua nama untuk satu dokumen.
                </p>
            </section>

            <!-- Paket pemakai -->
            <section class="dd-sec">
                <div class="dd-head">
                    <span class="dd-eyebrow"><i class="bi bi-box-seam"></i> Dipakai paket</span>
                    <span class="dd-count">{{ paket.length }}</span>
                </div>

                <p class="dd-note mb-2">
                    Inilah yang terdampak bila dokumen ini direvisi. Paket berstatus
                    <strong>Draft</strong> ikut terdaftar karena ia akan terbit membawa dokumen ini.
                </p>

                <div v-if="!paket.length" class="text-muted small">Belum dipakai paket mana pun.</div>
                <div v-else class="dd-pills">
                    <span
                        v-for="p in paket"
                        :key="p.Id_SOP_Paket"
                        class="dd-pill"
                        :class="p.Status === 'PUBLISHED' ? 'on' : 'draft'"
                    >
                        {{ p.Nama_Paket }}
                        <small>{{ p.Status === 'PUBLISHED' ? 'Terbit' : 'Draft' }}</small>
                    </span>
                </div>
            </section>

            <!-- Riwayat versi -->
            <section class="dd-sec">
                <div class="dd-head">
                    <span class="dd-eyebrow"><i class="bi bi-clock-history"></i> Riwayat versi</span>
                    <span class="dd-count">{{ versi.length }}</span>
                </div>

                <div v-if="!versi.length" class="text-muted small">Belum ada versi.</div>
                <div v-else class="dd-vlist">
                    <div
                        v-for="v in versi"
                        :key="v.Id_SOP_Item_Versi"
                        class="dd-v"
                        :class="{ aktif: String(v.Id_SOP_Item_Versi) === String(dokumen?.Id_Versi_Aktif) }"
                    >
                        <div class="dd-v-top">
                            <span class="dd-vnum">{{ $vlabel(v.Versi_Ke) }}</span>
                            <span class="dd-vstat" :class="statusTone(v.Status)">{{ v.Status }}</span>
                            <span
                                v-if="String(v.Id_SOP_Item_Versi) === String(dokumen?.Id_Versi_Aktif)"
                                class="dd-vaktif"
                            >
                                <i class="bi bi-check-circle-fill"></i> Berlaku
                            </span>
                        </div>
                        <div v-if="v.Ringkasan_Perubahan" class="dd-vring">{{ v.Ringkasan_Perubahan }}</div>
                        <div class="dd-vmeta">
                            <span><span class="dd-lab">Berlaku</span> {{ v.Tanggal_Berlaku || '—' }}</span>
                            <span v-if="pakaiPengesahan">
                                <span class="dd-lab">Pengesahan</span>
                                <template v-if="v.Disahkan_Oleh">
                                    {{ v.Disahkan_Oleh
                                    }}<template v-if="v.Nomor_Pengesahan"> · {{ v.Nomor_Pengesahan }}</template>
                                </template>
                                <em v-else class="text-danger">Belum disahkan</em>
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </SopModal>
</template>

<script>
import axios from 'axios';
import SopModal from './SopModal.vue';
import { BLOK, blokBerlaku } from '@/Pages/LMS/SOP/Shared/support/constants';

/* Dua pemanggil dengan gerbang izin BERBEDA: halaman Master Dokumen
   (menuSopDokumenPage) dan register di Monitoring (menuSopMonitoringPage).
   Menembak endpoint monitoring dari halaman Master akan membuat admin yang
   hanya punya akses Master menerima 403 saat membuka detail. */
const BASE = {
    monitoring: '/api/v1/sop/admin/monitoring/dokumen',
    dokumen: '/api/v1/sop/admin/dokumen',
};

/**
 * Detail satu dokumen terkendali — Fase 5 Master Dokumen.
 *
 * BACA-SAJA, dan itu disengaja. Item masih membawa Judul_Item dan
 * Nomor_SOP_Resmi sendiri, dan itulah yang dilihat karyawan di halaman dokumen.
 * Menyunting judul dokumen tanpa mengubah judul item menghasilkan dua nama
 * untuk satu dokumen terkendali — persis penyakit yang diobati proyek ini.
 * Penyuntingan pindah ke sini di Fase 6, saat item berhenti membawa
 * identitasnya sendiri.
 */
export default {
    name: 'SopDokumenDetailDrawer',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        idDokumen: { type: [String, Number], default: null },
        sumber: { type: String, default: 'monitoring' }, // monitoring | dokumen
    },
    emits: ['close'],
    data() {
        return { loading: false, gagal: '', dokumen: null, paket: [], versi: [] };
    },
    watch: {
        show(v) {
            if (v && this.idDokumen) this.muat();
        },
        idDokumen() {
            if (this.show) this.muat();
        },
    },
    computed: {
        /* Profil tipe yang memutuskan, bukan komponen ini.
         *
         * Yang paling merugikan sebelum ini bukan field nomor dokumen yang selalu
         * "—", melainkan label "Belum disahkan" BERWARNA MERAH pada setiap
         * form, video, dan kuis: ia menandai mereka seolah cacat, padahal
         * ketiganya memang tidak mengenal pengesahan. */
        pakaiPengesahan() {
            return blokBerlaku(this.dokumen?.Tipe, BLOK.PENGESAHAN);
        },
        pakaiNomorResmi() {
            return blokBerlaku(this.dokumen?.Tipe, BLOK.NOMOR_RESMI);
        },
    },
    methods: {
        statusTone(s) {
            if (s === 'PUBLISHED') return 'ok';
            if (s === 'OBSOLETE') return 'mute';

            return 'warn';
        },
        async muat() {
            this.loading = true;
            this.gagal = '';
            try {
                const base = BASE[this.sumber] || BASE.monitoring;
                const r = await axios.get(`${base}/${this.idDokumen}/detail`);
                const d = r.data?.result || {};
                this.dokumen = d.dokumen || null;
                this.paket = d.paket_pemakai || [];
                this.versi = d.riwayat_versi || [];
            } catch (e) {
                this.gagal = e?.response?.data?.message || 'Gagal memuat detail dokumen.';
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
/* Di-scope: kelas non-scoped di modul ini terbukti nyangkut antar halaman Inertia. */
.dd-stack {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.dd-sec {
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 14px;
    padding: 0.9rem 1rem;
}
.dd-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border-bottom: 1px solid var(--bs-border-color, #e5e7eb);
    padding-bottom: 0.5rem;
    margin-bottom: 0.75rem;
}
.dd-eyebrow {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.dd-count {
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-slate-900);
}
.dd-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}
.dd-f {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}
.dd-f-wide {
    grid-column: 1 / -1;
}
.dd-lab {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-400);
}
.dd-val {
    font-weight: 600;
    color: var(--hcis-slate-900);
    overflow-wrap: anywhere;
}
.dd-note {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    margin: 0.6rem 0 0;
}

.dd-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.dd-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.6rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid transparent;
}
.dd-pill small {
    font-weight: 600;
    opacity: 0.75;
}
.dd-pill.on {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
    border-color: var(--hcis-emerald-200);
}
.dd-pill.draft {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border-color: var(--hcis-slate-300);
}

.dd-vlist {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.dd-v {
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 12px;
    padding: 0.6rem 0.75rem;
}
.dd-v.aktif {
    border-color: var(--hcis-emerald-300);
    background: var(--hcis-green-50);
}
.dd-v-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.dd-vnum {
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.dd-vstat {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.dd-vstat.ok {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
}
.dd-vstat.warn {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
}
.dd-vstat.mute {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
}
.dd-vaktif {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-emerald-700);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.dd-vring {
    font-size: 0.82rem;
    color: var(--hcis-slate-700);
    margin-top: 0.35rem;
}
.dd-vmeta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.4rem 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--hcis-slate-700);
}
.dd-vmeta .dd-lab {
    display: block;
}
</style>
