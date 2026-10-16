<template>
    <div class="sop-stack bab-keadaan">
        <!-- Strip KPI. Lima kartu utama keadaan sekarang.

             PALING ATAS, di atas kartu kabar. Kartu kabar muncul BERSYARAT
             (hanya bila ada perubahan sejak kunjungan terakhir), jadi selama ia
             di atas, baris pertama halaman berpindah-pindah tergantung ada
             tidaknya kabar — dan angka utama yang letaknya tak tetap harus
             dicari ulang tiap kali halaman dibuka. -->
        <HcisStatGroup :stats="kpi" :cols="5" gap="sm" layout="compact" appearance="crystal" @item-click="klikKpi" />

        <!-- Kabar sejak kunjungan terakhir. Ditandai dibaca lewat localStorage,
             jadi ia menghilang untuk pembaca ini saja — bukan untuk semua admin. -->
        <transition name="sop-swap">
            <HcisCard
                v-if="adaKabar"
                variant="glass"
                sentiment="info"
                size="sm"
                icon="stars"
                icon-animation="sparkle"
                title="Aktivitas Baru"
                description="Pembaruan sejak kunjungan terakhir Anda."
                class="bk-kabar"
            >
                <template #actions>
                    <HcisButton
                        label="Tandai dibaca"
                        icon="check2-all"
                        variant="ghost"
                        size="sm"
                        @click="tandaiDibaca"
                    />
                </template>

                <div class="bk-kabar-chips">
                    <HcisBadge
                        v-for="c in kabar"
                        :key="c.key"
                        :label="`${c.nilai} ${c.label}`"
                        :variant="c.variant"
                        :icon="c.icon"
                        appearance="subtle"
                        size="md"
                    />
                </div>
            </HcisCard>
        </transition>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { HcisCard, HcisButton, HcisBadge, HcisStatGroup } from '@/components/ui';
import { ambil, angka, formatLabelRentang, sentimenPersen } from './agregat.js';

const KUNCI_KUNJUNGAN = 'sop_mon_kunjungan';

/**
 * Bab 1 — "Di mana kita sekarang?"
 *
 * Isinya sengaja dibatasi pada hal yang bisa dijawab tanpa berpikir: berapa
 * angkanya, siapa yang perlu didorong hari ini.
 */
export default {
    name: 'BabKeadaan',
    components: { HcisCard, HcisButton, HcisBadge, HcisStatGroup },
    props: {
        saringan: { type: Object, default: () => ({}) },
        /**
         * Angka lapis dokumen, dititipkan induk dari BabKesehatanDokumen.
         *
         * Bukan diambil sendiri: /dokumen/kesehatan adalah permintaan terberat
         * di halaman ini, dan memanggilnya dua kali berarti dua kali kerja
         * server untuk satu layar. Konsekuensinya kedua kartu paling kanan
         * terisi sesaat setelah tiga kartu pertama — itulah gunanya 'loading'.
         */
        dokumen: { type: Object, default: () => ({}) },
    },
    emits: ['lencana'],
    data() {
        return {
            summary: { coverage: 0, paket_aktif: 0, tertunda: 0, jatuh_tempo: 0, terlambat: 0, user_total: 0, user_ditugaskan: 0 },
            pulse: { selesai_7hr: { kini: 0, lalu: null, delta: null }, sejak: null },
            memuat: true,
        };
    },
    computed: {
        labelRentang() {
            return formatLabelRentang(this.saringan);
        },
        adaKabar() {
            return this.kabar.length > 0;
        },
        kabar() {
            const s = this.pulse.sejak;
            if (!s) return [];

            return [
                { key: 'selesai', nilai: s.selesai, label: 'penugasan tuntas', variant: 'success', icon: 'check-circle-fill' },
                { key: 'dibuka_lagi', nilai: s.dibuka_lagi, label: 'dibuka ulang (versi baru)', variant: 'warning', icon: 'arrow-repeat' },
                { key: 'kadaluarsa', nilai: s.kadaluarsa, label: 'kedaluwarsa', variant: 'danger', icon: 'exclamation-triangle-fill' },
                { key: 'dicabut', nilai: s.dicabut, label: 'akses dicabut', variant: 'neutral', icon: 'x-circle-fill' },
            ].filter((c) => Number(c.nilai) > 0);
        },
        /**
         * Lima kartu utama.
         *
         * JUDULNYA INGGRIS, KETERANGANNYA INDONESIA — dan itu keputusan, bukan
         * kelalaian. Judul kartu dibaca sekilas dan sering dikutip apa adanya;
         * keterangannya dibaca untuk memahami angkanya, jadi ia tetap memakai
         * bahasa yang dipakai seluruh modul. Menyeragamkan salah satu arah akan
         * membalik keputusan itu diam-diam.
         *
         * Ini juga menyimpang dari kosakata resmi Monitoring SOP (20 Agu 2026)
         * yang mewajibkan istilah Indonesia; pengecualiannya SEBATAS lima judul
         * di strip ini.
         */
        kpi() {
            const r = this.labelRentang;
            const dok = this.dokumen || {};

            /* DUA KESIAPAN YANG BERBEDA. Tiga kartu pertama menunggu /summary,
               dua terakhir menunggu titipan lapis dokumen — keduanya datang
               sendiri-sendiri, jadi satu penanda bersama akan menahan kartu
               yang datanya sudah ada. */
            const adaRingkas = ! this.memuat;
            const adaDokumen = Boolean(dok.siap);

            const persen = Number(this.summary.coverage) || 0;
            const semuaUser = Number(this.summary.user_total) || 0;
            const ditugaskan = Number(this.summary.user_ditugaskan) || 0;
            const bagian = semuaUser > 0 ? Math.round((ditugaskan * 100) / semuaUser) : 0;

            return [
                {
                    key: 'penyelesaian',
                    label: 'Reading Completion Rate',
                    value: this.nilai(adaRingkas, `${persen}%`),
                    progress: adaRingkas ? persen : 0,
                    caption: this.ekor('Dari seluruh penugasan aktif', r, adaRingkas),
                    sentiment: sentimenPersen(persen),
                    icon: 'shield-check',
                },
                {
                    key: 'user',
                    label: 'Total User',
                    /* ANGKA UTAMANYA yang DIBERI PENUGASAN, bukan seluruh
                       karyawan. Kartu ini berdiri di papan kepatuhan: orang
                       tanpa satu pun penugasan tak punya apa pun yang bisa
                       diukur di sini, jadi memakai populasi penuh sebagai angka
                       utama membuat kartu ini menghitung populasi HR, bukan
                       populasi yang sedang dipantau. Populasi penuhnya tetap
                       disebut di keterangan — tanpa itu angkanya tak bisa
                       dinilai besar atau kecil. */
                    value: this.nilai(adaRingkas, angka(ditugaskan)),
                    caption: this.ekor(`${bagian}% dari ${angka(semuaUser)} user aktif`, r, adaRingkas),
                    sentiment: 'info',
                    icon: 'people-fill',
                },
                {
                    key: 'paket',
                    label: 'Active Document Packages',
                    value: this.nilai(adaRingkas, angka(this.summary.paket_aktif)),
                    caption: this.sekarang('Terbit & berlaku', r, adaRingkas),
                    sentiment: 'purple',
                    icon: 'collection-fill',
                },
                {
                    key: 'dokumen',
                    label: 'Uploaded Documents',
                    value: this.nilai(adaDokumen, angka(dok.total || 0)),
                    suffix: adaDokumen ? 'dok' : '',
                    /* Pecahan tiga kelompok tetap disebut. Tanpa itu angkanya
                       hanya menghitung berkas; dengan itu ia menyatakan berapa
                       yang benar-benar sampai ke orang. */
                    caption: this.sekarang(
                        `${angka(dok.fungsional || 0)} ditugaskan · ${angka(dok.publik || 0)} beredar · ${angka(dok.belumBeredar || 0)} belum beredar`,
                        r,
                        adaDokumen,
                    ),
                    sentiment: 'neutral',
                    icon: 'file-earmark-text-fill',
                    clickable: true,
                },
                {
                    key: 'leadtime',
                    label: 'Average Lead Time',
                    value: this.nilai(adaDokumen, angka(dok.leadTime?.rerata || 0)),
                    suffix: adaDokumen ? 'hari' : '',
                    /* "N dari M" — bukan "N" saja. Angka tunggal di sini
                       terbaca sebagai jumlah dokumen dan langsung berselisih
                       dengan kartu di sebelahnya, sehingga pembacanya menduga
                       salah satunya keliru. Yang membedakan justru penyebutnya:
                       lead time hanya bisa dihitung untuk dokumen yang SUDAH
                       terbit. */
                    caption: this.sekarang(
                        `Respon → terbit · ${angka(dok.leadTime?.terbit || 0)} dari ${angka(dok.total || 0)} dokumen sudah terbit`,
                        r,
                        adaDokumen,
                    ),
                    sentiment: 'primary',
                    icon: 'stopwatch-fill',
                },
            ];
        },
    },
    mounted() {
        this.muat();
    },
    watch: {
        saringan: {
            deep: true,
            handler() {
                this.muat();
            },
        },
    },
    methods: {
        angka,

        /**
         * Keterangan kartu: ARTI ANGKANYA lebih dulu, rentang menyusul.
         *
         * Sebelum ini rentang MENGGANTIKAN artinya begitu saringan tanggal
         * dipasang, sehingga "Total User 958" tersisa berketerangan
         * "Rentang: Jan–Mar" — yang justru pada saat itulah pembacanya paling
         * perlu tahu angka itu menghitung apa.
         */
        ekor(arti, rentang, siap = true) {
            if (! siap) {
                return 'Memuat…';
            }

            return rentang ? `${arti} · ${rentang}` : arti;
        },

        /**
         * Keterangan kartu yang TIDAK mengikuti rentang tanggal.
         *
         * Tiga kartu mengukur keadaan sekarang, bukan aktivitas dalam sebuah
         * jendela: jumlah paket yang berlaku, jumlah dokumen yang ada, dan lama
         * penerbitan yang sudah terjadi. Menempelkan rentang pada keterangannya
         * — seperti yang dilakukan ekor() — membuat kartunya MENGAKU tersaring
         * padahal angkanya tak bergeser satu pun saat rentang digeser, dan
         * pengakuan palsu itu jauh lebih menyesatkan daripada tak menyebut
         * apa-apa. Yang disebut justru sebaliknya: bahwa ia tak ikut.
         */
        sekarang(arti, rentang, siap = true) {
            if (! siap) {
                return 'Memuat…';
            }

            return rentang ? `${arti} · keadaan sekarang` : arti;
        },

        /**
         * Nilai kartu selagi datanya belum sampai.
         *
         * Tata letak compact TIDAK menggambar kerangka pemuat — cabang
         * skeleton hanya ada pada tata letak default, dan `.is-loading` tak
         * punya satu pun aturan gaya. Tanpa penjaga ini, kartu yang sedang
         * menunggu menampilkan "0" — yang tak bisa dibedakan dari perusahaan
         * yang memang belum punya satu dokumen pun.
         */
        nilai(siap, v) {
            return siap ? v : '—';
        },

        klikKpi({ item }) {
            if (item.key === 'dokumen') router.visit('/sop/admin/dokumen');
        },
        async muat() {
            this.memuat = true;
            await Promise.all([this.ambilSummary(), this.ambilPulse()]);
            this.memuat = false;
        },
        async ambilSummary() {
            try {
                this.summary = await ambil('/summary', { ...this.saringan });
                this.$emit('lencana', { key: 'karyawan', value: Number(this.summary.terlambat) || 0 });
            } catch (e) {
                /* kartu tetap menampilkan nilai terakhir yang diketahui */
            }
        },
        async ambilPulse() {
            let sejak = null;
            try {
                sejak = localStorage.getItem(KUNCI_KUNJUNGAN);
            } catch (e) {
                /* penyimpanan diblokir peramban */
            }
            try {
                const r = await ambil('/pulse', { sejak: sejak || undefined, ...this.saringan });
                this.pulse = { ...this.pulse, ...r };
            } catch (e) {
                /* diam: kabar bukan informasi kritis */
            }
        },
        tandaiDibaca() {
            try {
                localStorage.setItem(KUNCI_KUNJUNGAN, new Date().toISOString());
            } catch (e) {
                /* penyimpanan diblokir peramban */
            }
            this.pulse = { ...this.pulse, sejak: null };
        },
    },
};
</script>

<style scoped>
.bab-keadaan {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.bk-kabar-chips {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
}
</style>
