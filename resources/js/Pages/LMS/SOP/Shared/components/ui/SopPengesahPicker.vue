<template>
    <div class="sop-pengesah-matrix d-flex flex-column gap-2.5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
            <label class="form-label text-xs fw-bold text-dark mb-0 d-flex align-items-center gap-1.5">
                <span>{{ label }}</span>
                <HcisBadge v-if="tanda" label="Diubah" variant="warning" size="xs" dot />
            </label>
            <span class="text-muted text-xs">Tanda tangan basah tetap dibubuhkan pada dokumen fisik</span>
        </div>

        <div v-for="(p, i) in rows" :key="i" class="sop-pengesah-row p-2.5 rounded-2 border d-flex align-items-center gap-2 flex-wrap">
            <!-- 1. Pilihan Peran Struktural (HcisSelect) -->
            <div style="width: 11rem; min-width: 9.5rem;" class="flex-shrink-0">
                <HcisSelect
                    :model-value="p.Peran"
                    placeholder="— Peran —"
                    size="sm"
                    leading-icon="person-badge"
                    :clearable="false"
                    class="w-100"
                    :disabled="terkunci"
                    @update:model-value="p.Peran = $event"
                >
                    <HcisSelectOption
                        v-for="r in peranOpsi"
                        :key="r"
                        :value="r"
                        :label="r"
                        :icon="r === 'Disetujui' ? 'shield-check' : (r === 'Diperiksa' ? 'search' : 'pencil-square')"
                    />
                </HcisSelect>
            </div>

            <!-- 2. Pencarian Nama Karyawan dengan Autocomplete -->
            <div :ref="`cari${i}`" class="pg-cari flex-grow-1" style="min-width: 14rem;">
                <input
                    v-model="p.Nama"
                    class="form-control form-control-sm rounded-2"
                    placeholder="Ketik nama untuk mencari dari master HRIS…"
                    autocomplete="off"
                    :disabled="terkunci"
                    :readonly="terkunci"
                    @input="onKetik(i)"
                    @focus="onFokus(i)"
                    @blur="tutupSaran"
                />
                <!-- DITELEPORTASI KE <body>, bukan kenyamanan melainkan syarat.

                     Sebagai anak `.pg-cari`, daftar ini hidup di dalam kartu
                     langkah: leluhur ber-`overflow` memotongnya, dan baris
                     pengesah BERIKUTNYA menimpanya karena tiap baris membentuk
                     konteks penumpukannya sendiri — z-index 1050 hanya berlaku
                     di dalam konteksnya, bukan melawan saudaranya.

                     Posisinya dihitung dari kotak input (`position: fixed`),
                     bukan diwarisi dari induknya, jadi ia tak lagi bergantung
                     pada satu pun leluhur. Pola yang sama dipakai SopModal, dan
                     dengan alasan yang sama. -->
                <Teleport to="body">
                    <ul
                        v-if="saranUntuk === i && saran.length"
                        class="pg-saran sop-scope"
                        :style="{ top: `${posisi.top}px`, left: `${posisi.left}px`, width: `${posisi.width}px` }"
                    >
                        <li v-for="k in saran" :key="k.Kode_Karyawan" @mousedown.prevent="pilih(i, k)">
                            <span class="pg-s-nama text-truncate">{{ k.Nama }}</span>
                            <span class="pg-s-jab text-truncate">{{ k.Jabatan || 'tanpa jabatan' }}</span>
                        </li>
                        <!-- Pembatasan DINYATAKAN. Daftar yang mentok di plafon tanpa
                             keterangan terbaca sebagai daftar yang lengkap, dan admin
                             menyimpulkan orangnya tak ada padahal cuma tak muat. -->
                        <li v-if="terpotong" class="pg-s-batas">
                            <i class="bi bi-three-dots me-1"></i>Masih ada nama lain — ketik lebih spesifik.
                        </li>
                    </ul>
                </Teleport>
            </div>

            <!-- 3. Jabatan Struktural -->
            <div style="width: 13rem; min-width: 10rem;" class="flex-shrink-0">
                <input
                    v-model="p.Jabatan"
                    class="form-control form-control-sm rounded-2"
                    placeholder="Jabatan pejabat"
                    :disabled="terkunci"
                    :readonly="terkunci"
                />
            </div>

            <!-- 4. Status Sumber (Data HRIS vs Manual) -->
            <div class="flex-shrink-0 d-flex align-items-center gap-1">
                <HcisBadge
                    :label="p.Kode_Karyawan ? 'Master HRIS' : 'Manual'"
                    :variant="p.Kode_Karyawan ? 'success' : 'neutral'"
                    size="xs"
                    :icon="p.Kode_Karyawan ? 'patch-check-fill' : 'pencil'"
                />
                <!-- Baris WARISAN dari versi sebelumnya. Ditandai supaya tak
                     terbaca sebagai tanda tangan yang sudah dibubuhkan — ia
                     usulan yang masih menunggu persetujuan admin. -->
                <HcisBadge
                    v-if="p.Dari_Versi !== undefined && p.Dari_Versi !== null"
                    :label="`dari v${String(p.Dari_Versi).padStart(2, '0')}`"
                    variant="neutral"
                    size="xs"
                    icon="arrow-return-right"
                />
                <!-- Pengesah lama yang sudah tak aktif TETAP ditawarkan, tapi
                     dikatakan. Membuangnya diam-diam membuat matriks tampak
                     lengkap padahal ada peran yang kosong, dan admin kehilangan
                     petunjuk siapa yang dulu mengisinya. -->
                <HcisBadge
                    v-if="p.Aktif === false"
                    label="sudah tidak aktif"
                    variant="warning"
                    size="xs"
                    icon="person-dash"
                />
            </div>

            <!-- 5. Tombol Hapus Baris -->
            <button
                v-if="!terkunci"
                type="button"
                class="btn btn-sm btn-outline-danger p-1 border-0"
                title="Hapus baris penanda tangan"
                style="width: 1.75rem; height: 1.75rem; display: grid; place-items: center;"
                @click="buang(i)"
            >
                <i class="bi bi-x-lg" style="font-size: 0.75rem;"></i>
            </button>
        </div>

        <div v-if="!terkunci">
            <HcisButton
                variant="secondary"
                size="xs"
                leading-icon="plus-lg"
                label="Tambah Penanda Tangan"
                @click="tambah"
            />
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import { PERAN_PENGESAH } from '../../support/constants.js';

/**
 * SopPengesahPicker — blok penanda tangan versi dokumen dengan HcisSelect.
 */
export default {
    name: 'SopPengesahPicker',
    components: {
        HcisSelect,
        HcisSelectOption,
        HcisBadge,
        HcisButton,
    },
    props: {
        rows: { type: Array, required: true },
        basis: { type: String, default: '/api/v1/sop/admin/dokumen' },
        label: { type: String, default: 'Disahkan oleh' },
        tanda: { type: Boolean, default: false },
        /**
         * MATRIKS INI SEBELUMNYA TAK PUNYA KUNCI SAMA SEKALI.
         *
         * Komponen ini dipakai langkah Pengesahan, dan langkah itu meneruskan
         * `terkunci` ke isian lain — tapi tidak ke sini, sebab propnya memang
         * tak ada. Akibatnya blok pengesah versi ARSIP (dibatalkan) dan RIWAYAT
         * (obsolete) tetap bisa ditambah dan diubah di layar. Server menolak
         * simpanannya, jadi datanya selamat — tapi admin mengerjakan sesuatu
         * yang baru ditolak di ujung, dan blok penanda tangan adalah tempat
         * terakhir yang boleh terasa seperti itu.
         */
        terkunci: { type: Boolean, default: false },
    },
    data() {
        return {
            peranOpsi: PERAN_PENGESAH,
            saran: [],
            saranUntuk: null,
            saranTimer: null,
            /** Daftar mentok di plafon server (30), jadi masih ada yang tak tampil. */
            terpotong: false,
            /** Kotak daftar saran di koordinat layar — dihitung, bukan diwarisi. */
            posisi: { top: 0, left: 0, width: 0 },
        };
    },
    created() {
        if (!this.rows.length) this.tambah();
    },
    mounted() {
        /* `capture: true` supaya gulir di dalam panel wizard pun tertangkap —
           peristiwa scroll tak menggelembung, jadi listener biasa di window
           hanya melihat gulir halaman. */
        window.addEventListener('scroll', this.ikutiKolom, true);
        window.addEventListener('resize', this.ikutiKolom);
    },
    beforeUnmount() {
        clearTimeout(this.saranTimer);
        window.removeEventListener('scroll', this.ikutiKolom, true);
        window.removeEventListener('resize', this.ikutiKolom);
    },
    methods: {
        tambah() {
            this.rows.push({ Peran: '', Nama: '', Jabatan: '', Kode_Karyawan: null });
        },
        buang(i) {
            this.rows.splice(i, 1);
            if (!this.rows.length) this.tambah();
            this.saranUntuk = null;
        },
        /**
         * MENGETIK memutus tautan karyawan — isinya memang tak lagi orang yang
         * dipilih dari master.
         */
        onKetik(i) {
            if (this.terkunci) return;
            this.rows[i].Kode_Karyawan = null;
            this.lepasWarisan(i);
            this.saranUntuk = i;
            this.hitungPosisi(i);
            clearTimeout(this.saranTimer);
            this.saranTimer = setTimeout(() => this.cari(i), 250);
        },
        /**
         * MEMBUKA daftar saran tanpa menyentuh isinya.
         *
         * Dulu @focus memanggil onKetik(), sehingga sekadar mengklik kolom nama
         * sudah menghapus Kode_Karyawan: baris yang tadinya "Master HRIS"
         * berubah jadi "Manual" tanpa satu huruf pun diketik. Itu tak terlihat
         * karena namanya tetap terpampang — yang hilang cuma tautannya, dan
         * tautan itulah yang membedakan pengesah terverifikasi dari teks bebas.
         */
        /**
         * Letakkan daftar tepat di bawah kolomnya, dalam koordinat layar.
         *
         * Dihitung ulang setiap kali daftar dibuka DAN setiap kali halaman
         * bergulir: `position: fixed` tak ikut bergerak bersama isi halaman,
         * jadi tanpa ini daftarnya menggantung di tempat lamanya begitu admin
         * menggulir.
         */
        /** Jaga daftar tetap menempel pada kolomnya saat halaman bergulir. */
        ikutiKolom() {
            if (this.saranUntuk !== null) this.hitungPosisi(this.saranUntuk);
        },
        hitungPosisi(i) {
            const el = this.$refs[`cari${i}`];
            const kotak = (Array.isArray(el) ? el[0] : el)?.getBoundingClientRect?.();
            if (!kotak) return;
            this.posisi = { top: kotak.bottom + 4, left: kotak.left, width: kotak.width };
        },
        onFokus(i) {
            if (this.terkunci) return;
            this.saranUntuk = i;
            this.hitungPosisi(i);
            /* Tetap mencari meski kolomnya kosong: endpoint-nya memang
               mengembalikan 30 nama pertama untuk kueri kosong, dan itulah cara
               admin MENELUSURI alih-alih menebak ejaan. Menahannya sampai ada
               huruf pertama menghapus satu-satunya cara membuka daftar tanpa
               tahu namanya lebih dulu. */
            clearTimeout(this.saranTimer);
            this.saranTimer = setTimeout(() => this.cari(i), 150);
        },
        /**
         * Ambil kandidat dari master HRIS.
         *
         * BENTUK BALASANNYA `{ result: { rows, tercakup } }` — pembungkus baku
         * ResponseHelper. Sebelum ini baris di bawah membaca `r.data.items`,
         * kunci yang tak pernah dikirim siapa pun, sehingga daftar saran selalu
         * kosong dan popover-nya TIDAK PERNAH muncul. Cacatnya tak
         * memunculkan galat apa pun: permintaannya berhasil, isinya cuma
         * undefined, dan `v-if="saran.length"` menelannya diam-diam.
         */
        async cari(i) {
            try {
                const r = await axios.get(`${this.basis}/options/pengesah`, {
                    params: { q: this.rows[i]?.Nama || '' },
                });
                if (this.saranUntuk !== i) return;
                this.saran = r.data?.result?.rows || [];
                /* Server sengaja melaporkan apakah daftarnya terpotong di 30.
                   Menelan penanda itu membuat "tak ketemu" tertukar dengan
                   "ada, tapi tak muat" — persis yang endpoint-nya hindari. */
                this.terpotong = r.data?.result?.tercakup === false;
            } catch {
                if (this.saranUntuk === i) {
                    this.saran = [];
                    this.terpotong = false;
                }
            }
        },
        pilih(i, k) {
            this.rows[i].Nama = k.Nama;
            this.rows[i].Jabatan = k.Jabatan || '';
            this.rows[i].Kode_Karyawan = k.Kode_Karyawan;
            /* Begitu barisnya diisi orang lain, ia bukan lagi warisan versi
               sebelumnya. Membiarkan penandanya menempel membuat lencana
               berbohong tentang asal baris — dan picker hanya menawarkan
               karyawan aktif, jadi peringatan "tidak aktif" pun jadi salah. */
            this.lepasWarisan(i);
            this.saranUntuk = null;
            this.saran = [];
        },
        /** Cabut penanda asal dari sebuah baris yang baru saja disunting. */
        lepasWarisan(i) {
            delete this.rows[i].Dari_Versi;
            delete this.rows[i].Aktif;
        },
        tutupSaran() {
            setTimeout(() => {
                this.saranUntuk = null;
            }, 200);
        },
    },
};
</script>

<style scoped>
.sop-pengesah-row {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-200) !important;
    transition: border-color 0.15s ease;
}

.sop-pengesah-row:hover {
    border-color: var(--hcis-slate-300) !important;
}

.pg-cari {
    position: relative;
}

/* Koordinat LAYAR, bukan relatif induk — lihat catatan Teleport di template.
   `left`, `top`, dan `width` datang dari gaya inline yang dihitung. */
.pg-saran {
    position: fixed;
    /* Di atas dialog wizard (HcisDialog memakai lapisan 1055-an). Daftar yang
       terbit dari sebuah kolom harus mengalahkan panel yang memuat kolom itu,
       kalau tidak ia muncul di belakangnya dan terlihat seperti "tertindih". */
    z-index: 2000;
    margin: 0;
    list-style: none;
    padding: 0.35rem;
    max-height: 13rem;
    overflow-y: auto;
    /* PADAT, tanpa transparansi. Latar tembus membuat teks baris di belakangnya
       ikut terbaca dan daftar ini tampak kotor. */
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    border-radius: var(--hcis-radius-md, 8px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18), 0 2px 6px rgba(15, 23, 42, 0.08);
}

.pg-saran li {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.4rem 0.6rem;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.1s ease;
}

.pg-saran li:hover {
    background: var(--hcis-slate-100);
}

.pg-s-nama {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.pg-s-jab {
    font-size: 0.725rem;
    color: var(--hcis-slate-500);
}

/* Baris keterangan, bukan pilihan: tak boleh terlihat bisa diklik. */
.pg-saran li.pg-s-batas {
    cursor: default;
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    border-top: 1px solid var(--hcis-slate-100);
    border-radius: 0;
    margin-top: 0.15rem;
}

.pg-saran li.pg-s-batas:hover {
    background: transparent;
}
</style>
