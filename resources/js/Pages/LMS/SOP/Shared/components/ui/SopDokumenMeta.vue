<template>
    <div class="dm">
        <div class="dm-head">
            <div class="dm-ident min-w-0">
                <span v-if="info.nomor" class="dm-nomor">{{ info.nomor }}</span>
                <span class="dm-versi">v{{ info.versi || 1 }}</span>
            </div>
            <span class="dm-badge" :class="badge.variant" :title="info.alasan">
                <i class="bi" :class="badge.icon"></i> {{ badge.label }}
            </span>
        </div>

        <p v-if="info.alasan" class="dm-alasan">{{ info.alasan }}</p>

        <!--
        Baris kosong sengaja tidak dirender. Status di atas tetap tampil karena
        diturunkan dari identitas versi, bukan dari tanggal yang mungkin kosong.
        -->
        <dl v-if="baris.length" class="dm-list">
            <template v-for="b in baris" :key="b.label">
                <dt class="dm-row-label">{{ b.label }}</dt>
                <dd class="dm-row">
                    {{ b.nilai }}
                    <small v-if="b.sub" class="dm-sub">{{ b.sub }}</small>
                </dd>
            </template>
        </dl>

        <p v-else class="dm-empty">
            <i class="bi bi-info-circle"></i>
            Belum ada catatan pengesahan untuk versi ini.
        </p>

        <div v-if="info.perubahan" class="dm-change">
            <div class="dm-change-t">Perubahan pada v{{ info.versi || 1 }}</div>
            <p class="dm-change-b">{{ info.perubahan }}</p>
        </div>

        <p v-if="bacaVersiLama" class="dm-active-note">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Anda membaca versi lama. Versi yang berlaku sekarang adalah {{ $vlabel(dokumen.Versi) }}.
        </p>

        <div v-if="adaRiwayat" class="dm-riwayat">
            <button type="button" class="dm-expand" @click="bentangkan">
                <i class="bi" :class="terbentang ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                {{ labelBentang }}
            </button>

            <div v-if="terbentang" class="dm-riwayat-body">
                <div v-if="galatRiwayat" class="dm-error">
                    <span>{{ galatRiwayat }}</span>
                    <button type="button" class="dm-retry" @click="$emit('muat-riwayat')">Coba lagi</button>
                </div>
                <div v-else-if="memuatRiwayat" class="dm-loading">Memuat riwayat…</div>
                <SopVersiTimeline v-else-if="riwayat.length" :versions="versiTimeline" :show-baca="false" />
            </div>
        </div>

        <p v-if="versiSebelumnya" class="dm-prev">
            <i class="bi bi-arrow-return-right"></i>
            Menggantikan {{ $vlabel(versiSebelumnya.Versi_Ke)
            }}<span v-if="rentangSebelumnya">, {{ rentangSebelumnya }}</span>
        </p>
    </div>
</template>

<script>
import SopVersiTimeline from './SopVersiTimeline.vue';

const BADGE = {
    BERLAKU: { label: 'Berlaku', variant: 'ok', icon: 'bi-patch-check-fill' },
    BELUM_BERLAKU: { label: 'Belum berlaku', variant: 'warn', icon: 'bi-hourglass-split' },
    VERSI_LAMA: { label: 'Versi lama', variant: 'idle', icon: 'bi-archive-fill' },
};

/**
 * Panel "Tentang dokumen ini" — penyaji murni.
 *
 * Sengaja tidak tahu-menahu soal HTTP: pemuatan riwayat diminta lewat event
 * `muat-riwayat` ke induk. Status keberlakuan pun tidak dihitung di sini —
 * datang jadi dari server, karena jam browser bukan dasar yang sah untuk
 * mengklaim sebuah dokumen masih berlaku.
 */
export default {
    name: 'SopDokumenMeta',
    components: { SopVersiTimeline },
    props: {
        /** Elemen detail.items[] yang sedang dibaca (metadata versi aktif). */
        dokumen: { type: Object, required: true },
        /** Baris riwayat versi; kosong sampai induk memuatnya. */
        riwayat: { type: Array, default: () => [] },
        memuatRiwayat: { type: Boolean, default: false },
        galatRiwayat: { type: String, default: '' },
        /** Terisi bila yang dibuka adalah versi lama, bukan versi aktif. */
        versiDibaca: { type: Object, default: null },
    },
    emits: ['muat-riwayat'],
    data() {
        return { terbentang: false };
    },
    computed: {
        bacaVersiLama() {
            return !!this.versiDibaca && this.versiDibaca.Is_Aktif === false;
        },
        /** Satu bentuk seragam, apa pun sumbernya (item aktif atau baris riwayat). */
        info() {
            const d = this.versiDibaca || this.dokumen;
            const k = d.Keberlakuan || {};

            return {
                // Nomor Dokumen resmi melekat pada dokumennya, bukan pada versi.
                nomor: this.dokumen.Nomor_SOP_Resmi || '',
                versi: this.versiDibaca ? d.Versi_Ke : d.Versi,
                berlaku: d.Tanggal_Berlaku || null,
                pengesah: d.Disahkan_Oleh || null,
                jabatan: d.Jabatan_Pengesah || null,
                tglPengesahan: d.Tanggal_Pengesahan || null,
                noPengesahan: d.Nomor_Pengesahan || null,
                perubahan: d.Ringkasan_Perubahan || null,
                status: k.status || 'BERLAKU',
                alasan: k.alasan || '',
            };
        },
        badge() {
            return BADGE[this.info.status] || BADGE.BERLAKU;
        },
        baris() {
            const i = this.info;
            return [
                { label: 'Berlaku sejak', nilai: this.fmt(i.berlaku) },
                { label: 'Disahkan', nilai: i.pengesah, sub: i.jabatan },
                { label: 'No. pengesahan', nilai: i.noPengesahan },
                { label: 'Tgl. pengesahan', nilai: this.fmt(i.tglPengesahan) },
            ].filter((b) => !!b.nilai);
        },
        adaRiwayat() {
            return !!this.dokumen.Ada_Riwayat || this.riwayat.length > 0;
        },
        labelBentang() {
            return this.riwayat.length ? `Lihat seluruh riwayat versi (${this.riwayat.length})` : 'Lihat riwayat versi';
        },
        versiSebelumnya() {
            if (this.riwayat.length < 2) return null;
            const idx = this.riwayat.findIndex((v) => v.Is_Aktif);
            const dari = idx >= 0 ? idx : 0;
            return this.riwayat[dari + 1] || null;
        },
        /**
         * Rentang HANYA dari field versi itu sendiri. Menyimpulkan tanggal akhir
         * dari tanggal mulai versi baru berarti mengarang fakta di fitur yang
         * justru dibangun untuk dipercaya.
         */
        rentangSebelumnya() {
            const v = this.versiSebelumnya;
            if (!v || !v.Tanggal_Berlaku) return '';
            const mulai = this.fmt(v.Tanggal_Berlaku);
            return `berlaku sejak ${mulai}`;
        },
        versiTimeline() {
            return this.riwayat.map((v) => ({
                id: v.Id_SOP_Item_Versi,
                versi: v.Versi_Ke,
                aktif: v.Is_Aktif,
                changelog: v.Ringkasan_Perubahan,
                tanggal: v.Tanggal_Publish || v.Tanggal_Berlaku,
            }));
        },
    },
    methods: {
        fmt(d) {
            if (!d) return '';
            const x = new Date(d);
            return isNaN(x) ? d : x.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        bentangkan() {
            this.terbentang = !this.terbentang;
            if (this.terbentang && !this.riwayat.length) this.$emit('muat-riwayat');
        },
    },
};
</script>

<style scoped>
.dm {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.min-w-0 {
    min-width: 0;
}

.dm-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.dm-ident {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}
.dm-nomor {
    font-family: monospace, SFMono-Regular, Consolas, sans-serif;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
}
.dm-versi {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 0.1rem 0.42rem;
    border-radius: 6px;
}
.dm-badge {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.16rem 0.5rem;
    border-radius: 999px;
    border: 1px solid transparent;
    white-space: nowrap;
}
.dm-badge.ok {
    color: #166534;
    background: var(--hcis-green-100);
    border-color: var(--hcis-green-200);
}
.dm-badge.warn {
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}
.dm-badge.bad {
    color: var(--hcis-red-800);
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
}
.dm-badge.idle {
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-200);
}

.dm-alasan {
    margin: 0;
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
}

.dm-list {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.35rem 0.75rem;
    margin: 0;
    padding-top: 0.55rem;
    border-top: 1px solid #e7ecf3;
}
.dm-row-label {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
    margin: 0;
}
.dm-row {
    font-size: 0.78rem;
    color: var(--hcis-slate-700);
    font-weight: 700;
    margin: 0;
    text-align: right;
}
.dm-sub {
    display: block;
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.dm-empty {
    margin: 0;
    font-size: 0.74rem;
    color: var(--hcis-slate-400);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding-top: 0.55rem;
    border-top: 1px solid #e7ecf3;
}

.dm-change {
    padding-top: 0.55rem;
    border-top: 1px solid #e7ecf3;
}
.dm-change-t {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
    margin-bottom: 0.15rem;
}
.dm-change-b {
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-slate-700);
}

.dm-active-note {
    margin: 0;
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.74rem;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
    padding: 0.45rem 0.6rem;
    border-radius: 10px;
}

.dm-prev {
    margin: 0;
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
}

.dm-riwayat {
    padding-top: 0.5rem;
    border-top: 1px solid #e7ecf3;
}
.dm-expand {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: none;
    border: 0;
    padding: 0;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-indigo-600);
}
.dm-riwayat-body {
    margin-top: 0.5rem;
}
.dm-loading {
    font-size: 0.74rem;
    color: var(--hcis-slate-400);
}
.dm-error {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.74rem;
    color: var(--hcis-red-800);
    background: var(--hcis-red-100);
    border: 1px solid var(--hcis-red-200);
    padding: 0.45rem 0.6rem;
    border-radius: 10px;
}
.dm-retry {
    background: none;
    border: 1px solid var(--hcis-red-200);
    border-radius: 8px;
    padding: 0.1rem 0.45rem;
    cursor: pointer;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-red-800);
}
</style>
