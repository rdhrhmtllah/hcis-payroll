<template>
    <SopModal
        :show="show"
        title="Terbitkan paket ini?"
        subtitle="Revisi dokumen di dalamnya berlaku serentak di semua paket pemakainya"
        icon="bi-broadcast"
        size="md"
        @close="$emit('close')"
    >
        <div v-if="memuat" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else>
            <!-- RILIS PERTAMA vs REVISI SUSUNAN — dua keadaan yang sama-sama
                 "tanpa revisi dokumen", tapi akibatnya berlawanan besarnya.
                 Satu kalimat untuk keduanya membuat penerbitan pertama sebuah
                 paket — saat dokumen ditugaskan ke karyawan untuk PERTAMA KALI —
                 terbaca seolah cuma pemindahan urutan. -->
            <p v-if="!versi.length && rilisPertama" class="pp-hint mb-0">
                <i class="bi bi-send-fill"></i>
                Paket ini terbit untuk <strong>pertama kali</strong>. Seluruh isinya —
                <strong>{{ jumlahItem }} item</strong> — mulai ditugaskan ke penerimanya. Tak ada revisi dokumen yang
                ikut naik: dokumen di dalamnya memakai versi yang sudah berlaku.
            </p>

            <p v-else-if="!versi.length" class="pp-hint mb-0">
                Tak ada revisi dokumen yang ikut terbit. Yang berubah susunan paket ini — item, urutan, atau
                pengaturannya. Mengganti item berarti penerima membaca dokumen yang berbeda, walau dokumennya sendiri tak
                direvisi.
            </p>

            <template v-else>
                <div class="pp-eyebrow">{{ versi.length }} dokumen ikut terbit revisinya</div>

                <ul class="pp-daftar">
                    <li v-for="(v, i) in versi" :key="i">
                        <div class="pp-baris">
                            <span class="pp-judul text-truncate">{{ v.Judul_Item }}</span>
                            <span class="pp-naik tnum">
                                {{ $vlabel(v.Versi_Sekarang) }} <i class="bi bi-arrow-right"></i>
                                {{ $vlabel(v.Versi_Baru) }}
                            </span>
                        </div>

                        <!-- SIFATNYA DIPILIH PER DOKUMEN, di baris dokumennya.
                             Satu publish bisa menaikkan beberapa dokumen dengan
                             alasan yang berbeda-beda: satu cuma salah ketik,
                             satu lagi benar-benar berubah isinya. Satu saklar
                             untuk semuanya memaksa admin memilih yang paling
                             ketat — dan karena kalau ragu orang memilih mayor,
                             hasilnya sama saja dengan tak punya pilihan. -->
                        <div v-if="v.Id_SOP_Dokumen" class="pp-pilih">
                            <button
                                v-for="o in OPSI"
                                :key="o.nilai"
                                type="button"
                                class="pp-pil"
                                :class="{ on: sifatDok(v) === o.nilai, minor: o.nilai === 'T' }"
                                :aria-pressed="sifatDok(v) === o.nilai"
                                @click="setSifatDok(v, o.nilai)"
                            >
                                <i class="bi" :class="o.ikon"></i> {{ o.label }}
                            </button>
                        </div>
                        <div v-else class="pp-baru">
                            <i class="bi bi-plus-circle"></i> Item baru — selalu jadi kewajiban baru.
                        </div>

                        <!-- INTI PRATINJAU INI. Tanpa baris ini admin mengira ia
                             hanya menyentuh paketnya sendiri — dugaan yang salah
                             dengan akibat nyata bagi karyawan paket lain. -->
                        <div v-if="v.Paket_Lain.length" class="pp-lain">
                            <i class="bi bi-arrow-return-right"></i>
                            juga dipakai:
                            <span v-for="(p, j) in v.Paket_Lain" :key="j">
                                {{ p.Nama_Paket }} <b class="tnum">({{ p.Jumlah_Karyawan }} org)</b
                                ><span v-if="j < v.Paket_Lain.length - 1"> · </span>
                            </span>
                        </div>
                        <div v-else class="pp-lain sepi"><i class="bi bi-arrow-return-right"></i> hanya paket ini</div>
                    </li>
                </ul>

                <!-- Sifat revisi ditanyakan DI SINI, bukan di panel rilis di atas:
                     admin baru bisa menimbang "perlu dibaca ulang atau tidak"
                     setelah melihat siapa saja yang terdampak. -->
                <div class="pp-sifat">
                    <p class="pp-ket">
                        <b>Mayor</b> — isinya berubah, karyawan wajib membaca ulang. <b>Minor</b> — perbaikan ketik atau
                        format, yang sudah selesai tetap terhitung selesai.
                    </p>
                    <!-- Pintasan, bukan pengganti. Ia mengisi ketiga baris di atas
                         sekaligus supaya publish berisi belasan dokumen tak
                         menuntut belasan klik — dan pilihannya tetap terlihat
                         per baris sesudahnya, jadi yang menyimpang bisa
                         dikembalikan satu per satu. -->
                    <div class="pp-semua">
                        <span>Setel semuanya:</span>
                        <button type="button" class="pp-pil" @click="setSemua('Y')">
                            <i class="bi bi-megaphone-fill"></i> Mayor
                        </button>
                        <button type="button" class="pp-pil minor" @click="setSemua('T')">
                            <i class="bi bi-feather"></i> Minor
                        </button>
                    </div>
                    <p v-if="jumlahMinor" class="pp-catat">
                        <i class="bi bi-info-circle-fill"></i>
                        <b class="tnum">{{ jumlahMinor }}</b> dari {{ berdokumen.length }} dokumen ditandai minor —
                        karyawan yang sudah menuntaskannya tidak diminta membaca ulang.
                    </p>
                </div>

                <p v-if="paketLain" class="pp-total">
                    <i class="bi bi-people-fill"></i>
                    Terdampak: paket ini + <b class="tnum">{{ paketLain }}</b> paket lain,
                    <b class="tnum">{{ karyawanLain }}</b> karyawan di luar paket ini.
                </p>
            </template>
        </div>

        <template #footer>
            <button class="stitch-btn secondary" :disabled="mengirim" @click="$emit('close')">Batal</button>
            <button class="stitch-btn primary" :disabled="mengirim || memuat" @click="terbitkan">
                {{ mengirim ? 'Menerbitkan…' : 'Ya, terbitkan' }}
            </button>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import SopModal from './SopModal.vue';

/**
 * Konfirmasi publish paket — dengan dampaknya ke paket LAIN.
 *
 * Sebelumnya publish paket cuma bertanya "Terbitkan dokumen ini?". Itu cukup
 * selama satu dokumen hanya dipakai satu paket. Sejak dokumen bisa dibagi,
 * menerbitkan paket ini bisa menerbitkan revisi yang juga disajikan paket
 * lain — dan admin menyetujuinya tanpa pernah diberi tahu.
 */
export default {
    name: 'SopPublishPaketPratinjau',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        /** Hashid paket; dampaknya diambil saat modal dibuka. */
        paket: { type: String, default: null },
        mengirim: { type: Boolean, default: false },
    },
    emits: ['close', 'terbitkan'],
    data() {
        return {
            versi: [],
            paketLain: 0,
            karyawanLain: 0,
            memuat: false,
            // Rilis pertama tak punya revisi dokumen, dan itu bukan hal yang sama
            // dengan revisi susunan — lihat kalimat di template.
            rilisPertama: false,
            jumlahItem: 0,
            /* Peta hashid dokumen => 'Y' | 'T'. Yang tak tercatat di sini
               diperlakukan MAYOR oleh sifatDok(), jadi keadaan awal yang kosong
               berarti "semua mayor" — bukan "belum diputuskan". Admin yang tak
               menyentuh apa pun tak boleh diam-diam menghasilkan "tak usah
               baca". */
            pilihan: {},
            OPSI: [
                { nilai: 'Y', label: 'Mayor', ikon: 'bi-megaphone-fill' },
                { nilai: 'T', label: 'Minor', ikon: 'bi-feather' },
            ],
        };
    },
    computed: {
        /** Baris yang punya dokumen — hanya mereka yang bisa dipilih sifatnya. */
        berdokumen() {
            return this.versi.filter((v) => v.Id_SOP_Dokumen);
        },
        jumlahMinor() {
            return this.berdokumen.filter((v) => this.sifatDok(v) === 'T').length;
        },
    },
    watch: {
        show: {
            immediate: true,
            handler(v) {
                if (v) this.muat();
            },
        },
    },
    methods: {
        sifatDok(v) {
            return this.pilihan[v.Id_SOP_Dokumen] === 'T' ? 'T' : 'Y';
        },
        setSifatDok(v, nilai) {
            this.pilihan = { ...this.pilihan, [v.Id_SOP_Dokumen]: nilai };
        },
        setSemua(nilai) {
            const p = {};
            this.berdokumen.forEach((v) => {
                p[v.Id_SOP_Dokumen] = nilai;
            });
            this.pilihan = p;
        },
        /*
         * Yang dikirim: peta per dokumen, PLUS sifat menyeluruh untuk yang tak
         * ada di peta — item yang baru lahir di publish ini belum punya dokumen
         * saat pratinjau dihitung, jadi ia tak bisa disebut satu per satu.
         * Menyeluruhnya selalu mayor: item baru memang kewajiban baru.
         */
        terbitkan() {
            this.$emit('terbitkan', 'Y', { ...this.pilihan });
        },
        async muat() {
            this.versi = [];
            this.paketLain = 0;
            this.karyawanLain = 0;
            this.pilihan = {};
            if (!this.paket) return;

            this.memuat = true;
            try {
                const r = await axios.get(`/api/v1/sop/admin/builder/paket/${this.paket}/dampak-publish`);
                const d = r.data?.result || {};
                this.versi = d.versi || [];
                this.paketLain = d.paket_lain || 0;
                this.karyawanLain = d.karyawan_lain || 0;
                this.rilisPertama = !!d.rilis_pertama;
                this.jumlahItem = d.jumlah_item || 0;
            } catch (e) {
                /* Gagal menghitung dampak TIDAK boleh jatuh ke penerbitan
                   langsung — menerbitkan tanpa memperlihatkan akibatnya persis
                   keadaan yang konfirmasi ini ada untuk mencegah. */
                ElMessage.error(e?.response?.data?.message || 'Gagal menghitung dampak penerbitan.');
                this.$emit('close');
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
/* Di-scope: kelas non-scoped di modul ini terbukti nyangkut antar halaman Inertia. */
.pp-hint {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
}
.pp-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400);
    margin-bottom: 0.4rem;
}
.pp-daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.pp-daftar li {
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 10px;
    padding: 0.5rem 0.6rem;
}
.pp-baris {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    min-width: 0;
}
.pp-judul {
    font-size: 0.83rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    flex: 1 1 auto;
    min-width: 0;
}
.pp-naik {
    flex: none;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-emerald-700);
    white-space: nowrap;
}
.pp-lain {
    font-size: 0.71rem;
    color: var(--hcis-amber-700);
    margin-top: 0.25rem;
}
.pp-lain.sepi {
    color: var(--hcis-slate-400);
}

/* Pilihan per baris: pil kecil yang menempel pada dokumennya, bukan radio
   panjang — tiga baris dokumen dengan dua radio bertakarir jadi dinding teks. */
.pp-pilih {
    display: flex;
    gap: 0.3rem;
    margin-top: 0.4rem;
}
.pp-pil {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.01em;
    color: var(--hcis-slate-500);
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    cursor: pointer;
    transition:
        background 0.15s ease,
        color 0.15s ease,
        border-color 0.15s ease;
}
.pp-pil:hover {
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-600);
}
.pp-pil.on {
    color: #fff;
    background: var(--hcis-red-600);
    border-color: var(--hcis-red-600);
}
.pp-pil.minor.on {
    background: #0f766e;
    border-color: #0f766e;
}
.pp-baru {
    margin-top: 0.4rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.pp-semua {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
}
.pp-catat {
    margin: 0.55rem 0 0;
    font-size: 0.72rem;
    line-height: 1.5;
    color: #0f766e;
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    border-radius: 10px;
    padding: 0.45rem 0.6rem;
}

.pp-sifat {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: 0.9rem;
}
.pp-opsi {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    cursor: pointer;
    margin: 0;
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 10px;
    padding: 0.55rem 0.7rem;
}
.pp-opsi.on {
    border-color: #0f766e;
    background: #f0fdfa;
}
.pp-opsi input {
    margin-top: 0.2rem;
    flex: none;
}
.pp-opsi b {
    display: block;
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
}
.pp-opsi em {
    display: block;
    font-style: normal;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.pp-ket {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    margin: 0.1rem 0 0;
}

.pp-total {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0.8rem 0 0;
    font-size: 0.76rem;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-300);
    border-radius: 10px;
    padding: 0.5rem 0.6rem;
}
</style>
