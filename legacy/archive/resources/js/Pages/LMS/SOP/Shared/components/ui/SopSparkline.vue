<template>
    <svg class="spark" :viewBox="`0 0 ${w} ${h}`" preserveAspectRatio="none" role="img" :aria-label="aria">
        <defs>
            <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="warna" stop-opacity="0.28" />
                <stop offset="100%" :stop-color="warna" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path v-if="isi" :d="isi" :fill="`url(#${gid})`" />
        <path
            :d="garis"
            fill="none"
            :stroke="warna"
            stroke-width="1.6"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle v-if="titikAkhir" :cx="titikAkhir.x" :cy="titikAkhir.y" r="2.4" :fill="warna" />
    </svg>
</template>

<script>
let seq = 0;

/**
 * Sparkline SVG murni — SENGAJA bukan apexcharts.
 *
 * Ini muncul di kartu kokpit yang dilihat setiap pemuatan halaman; menariknya
 * dari apexcharts (~130 KB) berarti seluruh pengunjung membayar ongkos itu demi
 * garis selebar 90px. Tab Analitik tetap memakai apexcharts untuk grafik
 * sungguhan yang butuh sumbu, tooltip, dan interaksi.
 */
export default {
    name: 'SopSparkline',
    props: {
        data: { type: Array, default: () => [] }, // [{tanggal, nilai}] atau [number]
        warna: { type: String, default: '#6366f1' },
        w: { type: Number, default: 90 },
        h: { type: Number, default: 26 },
        label: { type: String, default: '' },
    },
    data() {
        seq += 1;
        return { gid: `spk${seq}` };
    },
    computed: {
        nilai() {
            return (this.data || []).map((d) => (typeof d === 'number' ? d : Number(d?.nilai) || 0));
        },
        titik() {
            const n = this.nilai;
            if (n.length < 2) return [];
            const maks = Math.max(...n);
            const min = Math.min(...n);
            // Deret datar digambar di tengah, bukan menempel di dasar —
            // garis mepet sumbu terbaca "nol" padahal nilainya bisa konstan tinggi.
            const rentang = maks - min || 1;
            const pad = 3;

            return n.map((v, i) => ({
                x: (i / (n.length - 1)) * this.w,
                y: maks === min ? this.h / 2 : this.h - pad - ((v - min) / rentang) * (this.h - pad * 2),
            }));
        },
        garis() {
            return this.titik.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
        },
        isi() {
            if (this.titik.length < 2) return '';
            const t = this.titik;

            return `${this.garis} L${this.w},${this.h} L0,${this.h} Z`;
        },
        titikAkhir() {
            return this.titik.length ? this.titik[this.titik.length - 1] : null;
        },
        aria() {
            const n = this.nilai;
            if (!n.length) return this.label || 'Tidak ada data';

            return `${this.label || 'Tren'}: ${n.join(', ')}`;
        },
    },
};
</script>

<style scoped>
.spark {
    display: block;
    width: 100%;
    height: 26px;
    overflow: visible;
}
</style>
