<template>
    <!-- Muncul HANYA bila berkasnya memang sudah dipakai dokumen lain. Selama
         belum, ia tak menampilkan apa pun — pemberitahuan yang selalu ada
         berhenti dibaca. -->
    <div v-if="lain.length" class="kt-kotak" :class="{ diakui }">
        <!-- SETELAH DIKONFIRMASI, peringatan ini menyusut jadi satu baris.
             Peringatan yang tetap berteriak sesudah dijawab membuat admin ragu
             apakah jawabannya sudah masuk, dan yang berikutnya akan dilewati
             tanpa dibaca. Rinciannya tetap bisa dibuka lagi. -->
        <div v-if="diakui" class="kt-kepala">
            <i class="bi bi-check-circle-fill"></i>
            <span>
                Anda memilih tetap memakai berkas ini, walau sudah dipakai
                <b>{{ lain.length }}</b> dokumen lain.
            </span>
            <button type="button" class="kt-lagi kt-tinjau" @click="diakui = false">Tinjau ulang</button>
        </div>

        <template v-else>
            <div class="kt-kepala">
                <i class="bi bi-info-circle-fill"></i>
                <span>
                    Berkas ini sudah dipakai <b>{{ lain.length }}</b>
                    {{ lain.length > 1 ? 'dokumen lain' : 'dokumen lain' }}.
                </span>
            </div>

            <!-- Daftarnya disebut, bukan cuma jumlahnya: "sudah dipakai" tanpa
             menyebut di mana tak menolong siapa pun memutuskan. -->
            <ul class="kt-daftar">
                <li v-for="d in tampil" :key="d.Id_SOP_Dokumen">
                    <div class="min-w-0">
                        <div class="kt-judul text-truncate">
                            {{ d.Judul }}
                            <span v-if="d.Nomor_SOP_Resmi" class="kt-nomor">{{ d.Nomor_SOP_Resmi }}</span>
                        </div>
                        <div class="kt-paket text-truncate">
                            <template v-if="d.Paket.length">Dipakai: {{ d.Paket.join(' · ') }}</template>
                            <template v-else>Belum dipakai paket mana pun</template>
                        </div>
                    </div>
                    <!-- Dokumen tanpa versi berlaku tak bisa dipakai item: yang
                     menunjuknya tak menyajikan apa pun ke karyawan, dan server
                     menolaknya saat disimpan. Menawarkan tombolnya berarti
                     mengundang admin ke penolakan yang sudah pasti. -->
                    <button
                        v-if="bisaPakai"
                        type="button"
                        class="kt-pakai"
                        :class="{ mati: d.Punya_Versi_Berlaku === false }"
                        :disabled="d.Punya_Versi_Berlaku === false"
                        :title="
                            d.Punya_Versi_Berlaku === false
                                ? 'Dokumen ini belum punya versi berlaku'
                                : 'Pakai dokumen ini'
                        "
                        @click="$emit('pakai', d)"
                    >
                        {{ d.Punya_Versi_Berlaku === false ? 'Belum ada versi' : 'Pakai ini' }}
                    </button>
                </li>
            </ul>

            <button v-if="lain.length > batas && !semua" type="button" class="kt-lagi" @click="semua = true">
                + {{ lain.length - batas }} dokumen lainnya
            </button>

            <p class="kt-saran">
                Membuat dokumen baru untuk berkas yang sama akan menambah satu dokumen terkendali lagi dengan isi identik.
                <!-- Kalimatnya ikut gerbangnya. Tanpa tombol "Pakai ini",
                     menyuruh admin "pakai saja" menunjuk tindakan yang tak ada
                     di layarnya. -->
                <template v-if="bisaPakai">Kalau salah satu di atas memang yang Anda maksud, pakai saja.</template>
                <template v-else>
                    Kalau memang disengaja — misalnya satu lampiran dipakai dua dokumen berbeda — lanjutkan saja.
                </template>
            </p>

            <!-- Jawaban yang bisa diberikan, bukan cuma peringatan yang harus
                 ditanggung. Tanpa ini satu-satunya cara meneruskan adalah
                 mengabaikan kotak yang menyala — dan mengabaikan peringatan
                 adalah kebiasaan yang menular ke peringatan berikutnya. -->
            <button type="button" class="kt-tetap" @click="tetapPakai">
                <i class="bi bi-check2"></i> Tetap pakai berkas ini
            </button>
        </template>
    </div>
</template>

<script>
import axios from 'axios';

/**
 * Pemberitahuan: berkas ini sudah menopang dokumen lain.
 *
 * KENAPA ADA. Satu berkas di Library melahirkan dokumen terkendali baru setiap
 * kali seseorang melampirkannya tanpa tahu sudah ada yang memakainya. Di data
 * dev, satu berkas menopang LIMA BELAS dokumen di delapan paket — itulah sumber
 * judul dan nomor dokumen kembar di arsip.
 *
 * MEMBERI TAHU, BUKAN MELARANG. Ada alasan sah satu berkas menopang dua dokumen
 * (lampiran yang dipakai dua dokumen berbeda). Melarang menjebak yang benar;
 * memberi tahu sudah cukup menghentikan yang tak sengaja.
 *
 * Basis-url dibuat prop karena endpoint yang sama dipasang di dua gerbang —
 * Master dan Builder — dan komponen ini dipakai keduanya.
 */
export default {
    name: 'SopKontenTerpakai',
    props: {
        /** Hashid konten yang baru dipilih; null = tak memeriksa apa pun. */
        konten: { type: String, default: null },
        /** Dokumen yang sedang dibuka, supaya ia tak menyebut dirinya sendiri. */
        kecuali: { type: String, default: null },
        /** Basis endpoint: berbeda gerbang, metode yang sama. */
        basis: { type: String, default: '/api/v1/sop/admin/dokumen' },
        /** Builder bisa menautkan ke dokumen lain; Master sudah berada di dalam satu. */
        bisaPakai: { type: Boolean, default: false },
    },
    emits: ['pakai', 'tetap-pakai'],
    data() {
        return { rows: [], semua: false, batas: 3, diakui: false };
    },
    computed: {
        lain() {
            return this.rows.filter((d) => d.Id_SOP_Dokumen !== this.kecuali);
        },
        tampil() {
            return this.semua ? this.lain : this.lain.slice(0, this.batas);
        },
    },
    watch: {
        konten: { immediate: true, handler: 'muat' },
    },
    methods: {
        tetapPakai() {
            this.diakui = true;
            /* Diteruskan ke induk supaya keputusannya bisa dicatat kalau
               nanti dikehendaki. Komponen ini sendiri tak menyimpan apa pun —
               ia memberi tahu, dan kini juga menerima jawaban. */
            this.$emit('tetap-pakai', this.konten);
        },
        async muat() {
            this.rows = [];
            this.semua = false;
            /* Berkas yang berbeda adalah keputusan yang berbeda. Membiarkan
               pengakuan lama berlaku untuk berkas baru berarti peringatannya
               tak pernah muncul lagi setelah sekali dijawab. */
            this.diakui = false;
            if (!this.konten) return;

            try {
                const r = await axios.get(`${this.basis}/konten/${this.konten}/pemakai`);
                this.rows = r.data?.result?.rows || [];
            } catch (e) {
                /* Diam. Ini pemberitahuan, bukan syarat — menggagalkan pemilihan
                   berkas karena pemeriksaan tambahan gagal akan menukar satu
                   masalah kecil dengan yang lebih besar. */
                this.rows = [];
            }
        },
    },
};
</script>

<style scoped>
/* Di-scope: kelas non-scoped di modul ini terbukti nyangkut antar halaman Inertia. */
.kt-kotak {
    margin-top: 0.5rem;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    border-radius: 10px;
    padding: 0.55rem 0.65rem;
}
.kt-kepala {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: #1e40af;
}
.kt-daftar {
    list-style: none;
    margin: 0.45rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.kt-daftar li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    background: #fff;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    padding: 0.35rem 0.5rem;
}
.kt-judul {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}
.kt-nomor {
    font-size: 0.64rem;
    font-weight: 700;
    color: #1e40af;
    background: #dbeafe;
    border-radius: 999px;
    padding: 0.05rem 0.35rem;
    margin-left: 0.3rem;
}
.kt-paket {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
}
.kt-pakai {
    flex: none;
    font-size: 0.7rem;
    font-weight: 700;
    color: #1d4ed8;
    background: #fff;
    border: 1px solid #93c5fd;
    border-radius: 7px;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
}
.kt-pakai:hover {
    background: #dbeafe;
}
.kt-pakai.mati {
    color: var(--hcis-slate-400);
    border-color: var(--hcis-slate-200);
    border-style: dashed;
    background: var(--hcis-slate-50);
    cursor: not-allowed;
}
.kt-pakai.mati:hover {
    background: var(--hcis-slate-50);
}
.kt-lagi {
    margin-top: 0.35rem;
    font-size: 0.7rem;
    color: #1e40af;
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    text-decoration: underline;
}
.kt-saran {
    font-size: 0.68rem;
    color: var(--hcis-slate-600);
    margin: 0.45rem 0 0;
}

/* Setelah dijawab, kotaknya turun nada: hijau tenang, bukan biru yang menuntut
   perhatian. Warnanya sendiri yang mengabarkan bahwa urusannya sudah selesai. */
.kt-kotak.diakui {
    border-color: var(--hcis-emerald-200);
    background: var(--hcis-emerald-50);
}
.kt-kotak.diakui .kt-kepala {
    color: var(--hcis-emerald-700);
}
.kt-tetap {
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #1d4ed8;
    background: #fff;
    border: 1px solid #93c5fd;
    border-radius: 8px;
    padding: 0.28rem 0.6rem;
    cursor: pointer;
}
.kt-tetap:hover {
    background: #dbeafe;
}
.kt-tinjau {
    margin: 0 0 0 auto;
    flex: none;
    color: var(--hcis-emerald-700);
}
</style>
