<template>
    <section class="alur-panel">
        <div class="alur-header">
            <div class="min-w-0">
                <div class="alur-eyebrow">Peta alur dokumen</div>
                <h3>Dari dokumen sampai karyawan</h3>
                <p>
                    Geser kanvas dengan drag atau dua jari, pinch atau Ctrl + scroll untuk zoom. Arahkan kursor ke node
                    untuk menyorot jalurnya; klik node untuk pilihan tindakannya.
                </p>
            </div>
            <div class="alur-ringkas">
                <span>{{ versi.length }} versi</span>
                <span>{{ paket.length }} paket</span>
                <span>{{ totalOrang }} karyawan</span>
            </div>
        </div>

        <div v-if="!versi.length && !paket.length" class="alur-state">
            dokumen ini belum punya versi maupun paket pemakai, jadi belum ada alur yang bisa digambar.
        </div>

        <div
            v-else
            ref="viewport"
            class="alur-viewport"
            :style="{ height: tinggiViewport }"
            @wheel="onWheel"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
        >
            <div class="alur-kontrol">
                <button type="button" title="Perkecil" @click="zoomBy(0.85)">−</button>
                <span>{{ Math.round(view.zoom * 100) }}%</span>
                <button type="button" title="Perbesar" @click="zoomBy(1.18)">+</button>
                <button type="button" class="fit" title="Tampilkan seluruh alur" @click="fit">Fit</button>
            </div>

            <!-- Legenda: tebal garis dan garis putus membawa arti, dan arti yang
                 harus ditebak sama saja dengan tak ada arti. -->
            <div class="alur-legenda">
                <span><i class="lg-garis tebal"></i> makin tebal = makin banyak karyawan</span>
                <span><i class="lg-garis putus"></i> versi lama, tak mengalir</span>
            </div>

            <div class="alur-kanvas" :style="transform">
                <svg
                    class="alur-tepi"
                    :width="ukuran.w"
                    :height="ukuran.h"
                    :viewBox="`0 0 ${ukuran.w} ${ukuran.h}`"
                    aria-hidden="true"
                >
                    <defs>
                        <marker
                            id="sop-alur-panah"
                            viewBox="0 0 10 10"
                            refX="9"
                            refY="5"
                            markerWidth="5"
                            markerHeight="5"
                            orient="auto-start-reverse"
                        >
                            <path d="M 0 0 L 10 5 L 0 10 z" />
                        </marker>
                        <marker
                            id="sop-alur-panah-mati"
                            viewBox="0 0 10 10"
                            refX="9"
                            refY="5"
                            markerWidth="5"
                            markerHeight="5"
                            orient="auto-start-reverse"
                        >
                            <path d="M 0 0 L 10 5 L 0 10 z" />
                        </marker>
                    </defs>
                    <path
                        v-for="(e, i) in tepi"
                        :key="i"
                        :d="e.d"
                        class="tepi"
                        :class="{ mati: e.mati, redup: sorot && !e.terkait }"
                        :style="{ strokeWidth: e.tebal + 'px' }"
                        :marker-end="e.mati ? 'url(#sop-alur-panah-mati)' : 'url(#sop-alur-panah)'"
                    />
                </svg>

                <!-- Semua node bisa diklik, dan yang muncul popover — bukan
                     langsung berpindah halaman. Node paket dulu sebuah <a>:
                     satu klik langsung melempar admin keluar dari peta yang
                     sedang ia baca, tanpa sempat memilih. -->
                <div
                    v-for="n in node"
                    :key="n.id"
                    class="alur-node"
                    :class="[
                        n.kelas,
                        { redup: sorot && !terkait(n.id), bisa: !!n.aksi, pilih: menu && menu.id === n.id },
                    ]"
                    :style="{ left: n.x + 'px', top: n.y + 'px', width: LEBAR + 'px' }"
                    :data-node="n.id"
                    :role="n.aksi ? 'button' : null"
                    :tabindex="n.aksi ? 0 : null"
                    @mouseenter="sorot = n.id"
                    @mouseleave="sorot = null"
                    @keydown.enter.prevent="bukaMenu(n)"
                >
                    <div class="n-kepala">{{ n.kolom }}</div>
                    <div class="n-judul" :title="n.judul">{{ n.judul }}</div>
                    <div v-if="n.sub" class="n-sub">{{ n.sub }}</div>

                    <!-- Jangkauan dilebur ke node paket: ia properti paket, bukan
                         entitas sendiri. Satu kolom hilang, maknanya justru padat. -->
                    <div v-if="n.orang !== undefined" class="n-orang">
                        <i class="bi bi-people-fill"></i>
                        <span class="tnum">{{ n.orang }}</span> karyawan
                        <span class="n-bar"><span :style="{ width: n.barLebar }"></span></span>
                    </div>

                    <div v-if="n.tanda" class="n-tanda" :class="n.tandaKelas">{{ n.tanda }}</div>
                </div>

                <!-- Versi lama dilipat: kanvas harus bercerita tentang yang HIDUP.
                     Sepuluh versi OBSOLETE mendorong bagian yang berlaku keluar layar. -->
                <button
                    v-if="versiTersembunyi > 0"
                    type="button"
                    class="alur-lipat"
                    :style="{ left: lipat.x + 'px', top: lipat.y + 'px', width: LEBAR + 'px' }"
                    @click="bukaSemuaVersi = true"
                >
                    + {{ versiTersembunyi }} versi lama
                </button>
            </div>

            <!-- POPOVER DI LUAR LAPISAN YANG DISKALAKAN.
                 Di dalam kanvas, ia ikut mengecil bersama zoom — tulisannya
                 kabur justru saat admin memperkecil untuk melihat keseluruhan,
                 yaitu saat ia paling butuh membacanya. Posisinya dihitung dari
                 koordinat node dikali zoom, jadi ia tetap menempel di tempat
                 yang benar tanpa ikut mengecil. -->
            <div v-if="menu" class="alur-pop" :style="posisiMenu" @click.stop>
                <div class="ap-kepala">
                    <span class="ap-judul text-truncate">{{ menu.judul }}</span>
                    <span v-if="menu.tanda" class="ap-tanda" :class="menu.tandaKelas">{{ menu.tanda }}</span>
                </div>
                <p v-if="menu.sub" class="ap-sub text-truncate">{{ menu.sub }}</p>

                <template v-if="menu.aksi.tipe === 'versi'">
                    <!-- FORM ber-PDF bisa dibaca di layar DAN diunduh; FORM lain
                         (xlsx, docx) hanya bisa diunduh. -->
                    <button
                        v-if="!isKuis && menu.aksi.data.Punya_Berkas && (!isForm || menu.aksi.data.Bisa_Pratinjau)"
                        type="button"
                        class="ap-aksi"
                        :class="{ menunggu: aksiSibuk === 'lihat' }"
                        :disabled="!!aksiSibuk"
                        @click="pilihAksi('lihat')"
                    >
                        <i class="bi" :class="aksiSibuk === 'lihat' ? 'bi-arrow-repeat berputar' : 'bi-eye'"></i>
                        <!-- Labelnya ikut berubah, bukan cuma ikonnya berputar:
                             sebagian mesin menyalakan prefers-reduced-motion, dan
                             umpan balik yang hanya berupa animasi jadi tak terbaca
                             di sana. Teks selalu terbaca. -->
                        <span>{{ aksiSibuk === 'lihat' ? 'Membuka…' : 'Lihat dokumen' }}</span>
                    </button>
                    <button
                        v-if="!isKuis && menu.aksi.data.Punya_Berkas && isForm"
                        type="button"
                        class="ap-aksi"
                        :class="{ menunggu: aksiSibuk === 'unduh' }"
                        :disabled="!!aksiSibuk"
                        @click="pilihAksi('unduh')"
                    >
                        <i class="bi" :class="aksiSibuk === 'unduh' ? 'bi-arrow-repeat berputar' : 'bi-download'"></i>
                        <span>{{ aksiSibuk === 'unduh' ? 'Menyiapkan…' : 'Unduh berkas' }}</span>
                    </button>
                    <!-- HANYA DRAFT. Versi OBSOLETE dulu ikut menawarkan terbit, sebab
                         syaratnya cuma "bukan PUBLISHED". Menerbitkannya bukan
                         penerbitan melainkan PENGEMBALIAN: pointer mundur ke revisi
                         lama, jejak auditnya berbunyi "versi baru terbit", dan
                         rekonsiliasi milik rollback dilewati sama sekali. -->
                    <!-- Rollback punya tombolnya sendiri: memundurkan revisi
                         bukan penerbitan, dan jejaknya harus berbeda. -->
                    <button
                        v-if="menu.aksi.data.Status === 'OBSOLETE' && menu.aksi.data.Punya_Berkas"
                        type="button"
                        class="ap-aksi"
                        @click="pilihAksi('kembali')"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Kembalikan versi ini</span>
                    </button>
                    <button
                        v-if="menu.aksi.data.Status === 'DRAFT'"
                        type="button"
                        class="ap-aksi utama"
                        @click="pilihAksi('terbit')"
                    >
                        <i class="bi bi-broadcast"></i>
                        <span>Pratinjau terbit</span>
                    </button>
                    <p v-if="!menu.aksi.data.Punya_Berkas" class="ap-kosong">
                        Versi ini belum punya berkas, jadi belum ada yang bisa dibuka.
                    </p>
                </template>

                <a v-else class="ap-aksi" :href="menu.aksi.href">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Lihat paket di Builder</span>
                </a>
            </div>
        </div>
    </section>
</template>

<script>
/**
 * Peta alur satu Dokumen — dokumen → versi → paket (berikut jangkauannya).
 *
 * TIGA KOLOM, BUKAN EMPAT. Percobaan pertama memberi "Jangkauan" kolom sendiri;
 * itu menggandakan jumlah node hanya untuk menulis satu angka yang sebenarnya
 * properti paket. Sekarang angka karyawan hidup di dalam node paket, dan
 * kolom yang hilang justru membuat petanya lebih padat makna.
 *
 * YANG MEMBUATNYA MENJELASKAN SESUATU:
 *  - Hanya versi BERLAKU yang mengalir ke paket. Versi lama digambar buntu,
 *    sehingga "kenapa karyawan masih membaca v2" terjawab dengan melihat.
 *  - Tebal garis sebanding jumlah karyawan — volume terbaca tanpa angka.
 *  - Menyorot satu node meredupkan yang tak terhubung; ini kemenangan
 *    keterbacaan terbesar pada graf, dan paling murah.
 *
 * Tata letaknya dihitung sendiri (kolom tetap, node ditumpuk lalu dipusatkan)
 * alih-alih memakai pustaka graf: bentuknya berlapis dan searah, tak ada yang
 * perlu dipecahkan algoritma tata letak — dan bundel aplikasi ini sudah berat.
 */
const MAKS_VERSI = 3;

/* Bagian yang menangani kliknya SENDIRI — peta tak boleh ikut campur.
   Satu tetapan untuk pointerdown DAN pointerup. Sebelumnya aturan ini cuma
   dipasang di pointerdown, dan itulah yang membuat popover tak bisa disentuh:
   pointerup pada tombolnya menggelembung ke viewport, viewport menganggapnya
   "menekan latar" lalu menutup popover, dan tombolnya lenyap dari DOM sebelum
   browser sempat mengirim click. Di layar gejalanya "tombol tak bisa diklik";
   sebenarnya tombolnya sudah tidak ada saat kliknya tiba. */
const ABAIKAN_PETA = '.alur-lipat, .alur-kontrol, .alur-pop';

export default {
    name: 'SopDokumenAlur',
    emits: ['lihat-versi', 'terbit-versi', 'kembalikan-versi'],
    props: {
        dokumen: { type: Object, default: null },
        versi: { type: Array, default: () => [] },
        paket: { type: Array, default: () => [] },
        /* Id versi yang isinya sedang diambil induk. Peta ini tak melakukan
           permintaan apa pun sendiri, jadi ia tak bisa tahu ada yang sedang
           berjalan — dan tanpa tahu, klik pada "Lihat dokumen" tampak tak
           terjadi apa-apa sampai berkasnya muncul. */
        sibuk: { type: [Number, String], default: null },
    },
    data() {
        return {
            LEBAR: 232,
            /* Tinggi SERAGAM. Percobaan pertama memberi node paket tinggi
               sendiri karena memuat bar jangkauan; hasilnya baris bergerigi
               dan tiap kolom tampak tak sejajar. Satu tinggi untuk semua
               membuat barisnya rata, dan ruang sisa pada node yang isinya
               lebih sedikit tidak mengganggu. */
            TINGGI: 86,
            JARAK_KOLOM: 104,
            JARAK_BARIS: 14,
            TEPI: 28,
            view: { x: 24, y: 24, zoom: 1 },
            menu: null,
            /* Menyeret kanvas TIDAK boleh membuka popover. Tanpa pembeda ini,
               setiap kali admin menggeser peta dengan menekan sebuah node,
               popover ikut terbuka di ujung gerakan. */
            /* Aksi yang sedang ditunggu pada popover ('lihat' | 'unduh'),
               supaya tombolnya bisa memperlihatkan dirinya sedang bekerja. */
            aksiSibuk: null,
            geserJauh: false,
            awalKlik: null,
            nodeTekan: null,
            pointers: new Map(),
            panAwal: null,
            pinchAwal: null,
            sorot: null,
            bukaSemuaVersi: false,
        };
    },
    computed: {
        totalOrang() {
            return this.paket.reduce((a, p) => a + (p.Jumlah_Karyawan || 0), 0);
        },
        versiBerlaku() {
            return this.versi.find((v) => v.Status === 'PUBLISHED') || null;
        },
        /** Versi berlaku selalu tampil; sisanya yang terbaru, sampai MAKS_VERSI. */
        versiTampil() {
            if (this.bukaSemuaVersi) return this.versi;

            const hidup = this.versi.filter((v) => v.Status === 'PUBLISHED');
            const lain = this.versi.filter((v) => v.Status !== 'PUBLISHED');

            return [...hidup, ...lain].slice(0, Math.max(MAKS_VERSI, hidup.length));
        },
        versiTersembunyi() {
            return Math.max(0, this.versi.length - this.versiTampil.length);
        },
        puncakOrang() {
            return Math.max(1, ...this.paket.map((p) => p.Jumlah_Karyawan || 0));
        },
        tata() {
            const kolom = [];

            kolom.push([
                {
                    id: 'dok',
                    kolom: 'Dokumen',
                    judul: this.dokumen?.Judul || 'Dokumen',
                    sub: this.dokumen?.Nomor_SOP_Resmi || 'tanpa nomor',
                    kelas: 'dok',
                    tinggi: this.TINGGI,
                },
            ]);

            kolom.push(
                this.versiTampil.map((v) => ({
                    id: 'v' + v.Id_SOP_Item_Versi,
                    kolom: 'Versi',
                    judul: 'v' + v.Versi_Ke,
                    sub: this.labelMasa(v),
                    tanda: v.Status,
                    /* Muatan mentahnya ikut: popover perlu tahu statusnya dan
                   punya-berkas atau tidak, dan menyalin field satu per satu
                   akan menyimpang begitu payloadnya bertambah. */
                    aksi: { tipe: 'versi', data: v },
                    tandaKelas: v.Status === 'PUBLISHED' ? 'ok' : v.Status === 'OBSOLETE' ? 'mati' : 'draft',
                    kelas: v.Status === 'PUBLISHED' ? 'versi hidup' : 'versi mati',
                    tinggi: this.TINGGI,
                })),
            );

            kolom.push(
                this.paket.map((p) => ({
                    id: 'p' + p.Id_SOP_Paket,
                    kolom: 'Paket',
                    judul: p.Nama_Paket,
                    orang: p.Jumlah_Karyawan || 0,
                    barLebar: this.barLebar(p),
                    tanda: p.Status === 'PUBLISHED' ? 'Terbit' : 'Draft',
                    tandaKelas: p.Status === 'PUBLISHED' ? 'ok' : 'draft',
                    kelas: p.Status === 'PUBLISHED' ? 'paket' : 'paket draft',
                    aksi: { tipe: 'paket', href: '/sop/admin/builder?paket=' + p.Id_SOP_Paket },
                    tinggi: this.TINGGI,
                })),
            );

            const isi = kolom.filter((k) => k.length);
            const tinggiKolom = isi.map(
                (k, i) =>
                    k.reduce((a, n) => a + n.tinggi, 0) +
                    (k.length - 1) * this.JARAK_BARIS +
                    (i === 1 && this.versiTersembunyi > 0 ? this.JARAK_BARIS + 26 : 0),
            );
            const puncak = Math.max(...tinggiKolom, this.TINGGI);

            const node = [];
            let lipat = { x: 0, y: 0 };

            isi.forEach((k, ci) => {
                let y = this.TEPI + (puncak - tinggiKolom[ci]) / 2;
                const x = this.TEPI + ci * (this.LEBAR + this.JARAK_KOLOM);

                k.forEach((n) => {
                    node.push({ ...n, x, y });
                    y += n.tinggi + this.JARAK_BARIS;
                });

                if (ci === 1 && this.versiTersembunyi > 0) lipat = { x, y };
            });

            return {
                node,
                lipat,
                w: this.TEPI * 2 + isi.length * this.LEBAR + (isi.length - 1) * this.JARAK_KOLOM,
                h: this.TEPI * 2 + puncak,
            };
        },
        node() {
            return this.tata.node;
        },
        lipat() {
            return this.tata.lipat;
        },
        ukuran() {
            return { w: this.tata.w, h: this.tata.h };
        },
        tepi() {
            const cari = (id) => this.node.find((n) => n.id === id);
            const keluar = (n) => ({ x: n.x + this.LEBAR, y: n.y + n.tinggi / 2 });
            const masuk = (n) => ({ x: n.x, y: n.y + n.tinggi / 2 });
            const kurva = (a, b) => {
                const dx = Math.max(30, (b.x - a.x) / 2);

                return `M ${a.x} ${a.y} C ${a.x + dx} ${a.y}, ${b.x - dx} ${b.y}, ${b.x} ${b.y}`;
            };

            const hasil = [];
            const dok = cari('dok');

            for (const v of this.versiTampil) {
                const n = cari('v' + v.Id_SOP_Item_Versi);
                if (!dok || !n) continue;
                hasil.push({
                    d: kurva(keluar(dok), masuk(n)),
                    mati: v.Status !== 'PUBLISHED',
                    tebal: v.Status === 'PUBLISHED' ? 2 : 1.2,
                    dari: 'dok',
                    ke: n.id,
                });
            }

            const vb = this.versiBerlaku ? cari('v' + this.versiBerlaku.Id_SOP_Item_Versi) : null;
            for (const p of this.paket) {
                const np = cari('p' + p.Id_SOP_Paket);
                if (!vb || !np) continue;

                /* Tebal sebanding jumlah karyawan: aliran yang membawa 88 orang
                   harus terlihat lebih deras daripada yang membawa 6. */
                /* Skala hanya dipakai bila ADA yang dibandingkan. Dengan satu
                   paket, rasionya otomatis 1 dan garisnya jadi gemuk tanpa
                   menyampaikan apa pun — tebal yang tak membedakan apa-apa
                   hanya terbaca sebagai berat. */
                const rasio = (p.Jumlah_Karyawan || 0) / this.puncakOrang;
                hasil.push({
                    d: kurva(keluar(vb), masuk(np)),
                    mati: false,
                    tebal: this.paket.length < 2 ? 2.2 : 1.4 + rasio * 4.5,
                    dari: vb.id,
                    ke: np.id,
                });
            }

            return hasil.map((e) => ({
                ...e,
                terkait: !this.sorot || e.dari === this.sorot || e.ke === this.sorot,
            }));
        },
        /** Node yang terhubung langsung ke node yang sedang disorot. */
        tetangga() {
            if (!this.sorot) return null;
            const s = new Set([this.sorot]);
            for (const e of this.tepi) {
                if (e.dari === this.sorot) s.add(e.ke);
                if (e.ke === this.sorot) s.add(e.dari);
            }

            return s;
        },
        isKuis() {
            return this.dokumen?.Tipe === 'KUIS';
        },
        isForm() {
            return this.dokumen?.Tipe === 'FORM';
        },
        /* Koordinat LAYAR, bukan koordinat kanvas: popover hidup di luar
           lapisan yang diskalakan, jadi posisinya harus dihitung sendiri
           dari transform yang sedang berlaku. */
        posisiMenu() {
            if (!this.menu) return {};
            const z = this.view.zoom;

            return {
                left: this.view.x + (this.menu.x + this.LEBAR / 2) * z + 'px',
                top: this.view.y + (this.menu.y + this.menu.tinggi) * z + 12 + 'px',
            };
        },
        transform() {
            return { transform: `translate(${this.view.x}px, ${this.view.y}px) scale(${this.view.zoom})` };
        },
        /* Tinggi kanvas MENGIKUTI ISI, bukan dipaku 56vh. Graf satu dokumen –
           satu versi – satu paket hanya setinggi ~140px; memberinya 470px
           menghasilkan lautan kosong yang membuat halaman terasa rusak, bukan
           lapang. Batas atas tetap ada supaya graf lebar tidak mengambil alih
           seluruh layar. */
        tinggiViewport() {
            const isi = this.ukuran.h + 24;

            return Math.round(Math.min(Math.max(isi, 220), Math.min(520, window.innerHeight * 0.56))) + 'px';
        },
    },
    watch: {
        paket: {
            deep: true,
            handler() {
                this.reset();
            },
        },
        versi: {
            deep: true,
            handler() {
                this.reset();
            },
        },
        /* Induk selesai — baik berhasil maupun gagal; keduanya sama-sama
           mengosongkan `sibuk`. Popover ditutup di sini, bukan saat tombolnya
           ditekan, supaya ia menutup ketika pekerjaannya benar-benar usai dan
           bukan ketika pekerjaannya baru dimulai. */
        sibuk(baru, lama) {
            if (lama && !baru) {
                this.aksiSibuk = null;
                this.menu = null;
            }
        },
    },
    mounted() {
        this.$nextTick(() => this.fit());
    },
    methods: {
        reset() {
            this.bukaSemuaVersi = false;
            this.sorot = null;
            this.$nextTick(() => this.fit());
        },
        terkait(id) {
            return !this.tetangga || this.tetangga.has(id);
        },
        /* Sebuah versi berlaku sampai digantikan versi berikutnya — tak ada
           tanggal akhir lagi. Kalimatnya dibuat utuh supaya keadaan "tak
           diisi" terbaca sebagai keadaan, bukan galat. */
        labelMasa(v) {
            return v.Tanggal_Berlaku ? 'berlaku sejak ' + v.Tanggal_Berlaku : 'tanggal berlaku belum di-set';
        },
        barLebar(p) {
            const n = p.Jumlah_Karyawan || 0;

            // Paket tanpa penugasan tetap bergaris tipis, bukan hilang —
            // "0 orang" adalah informasi, bukan ketiadaan.
            return n === 0 ? '3px' : Math.max(8, Math.round((n / this.puncakOrang) * 100)) + '%';
        },
        zoomBy(f) {
            this.view.zoom = Math.min(1.8, Math.max(0.3, this.view.zoom * f));
        },
        fit() {
            const vp = this.$refs.viewport;
            if (!vp) return;

            const z = Math.min(1, Math.min(vp.clientWidth / this.ukuran.w, vp.clientHeight / this.ukuran.h));
            this.view.zoom = Math.max(0.3, z);
            this.view.x = (vp.clientWidth - this.ukuran.w * this.view.zoom) / 2;
            this.view.y = (vp.clientHeight - this.ukuran.h * this.view.zoom) / 2;
        },
        onWheel(e) {
            if (!e.ctrlKey && Math.abs(e.deltaY) < 2) return;
            e.preventDefault();
            this.zoomBy(e.deltaY > 0 ? 0.92 : 1.08);
        },
        bukaMenu(n) {
            if (!n.aksi || this.geserJauh) return;
            this.menu = this.menu && this.menu.id === n.id ? null : n;
        },
        tutupMenu() {
            this.menu = null;
        },
        pilihAksi(apa) {
            const v = this.menu?.aksi?.data;
            if (!v) {
                this.menu = null;

                return;
            }

            /* Terbit dan kembalikan membuka modal sendiri yang langsung menutupi
               peta — popovernya ditutup seketika karena tak ada yang perlu
               ditunggu di sini. */
            if (apa === 'terbit' || apa === 'kembali') {
                this.menu = null;

                return this.$emit(apa === 'terbit' ? 'terbit-versi' : 'kembalikan-versi', v);
            }

            /* Lihat dan unduh menunggu permintaan jaringan induk, dan selama itu
               layar tak berubah sama sekali — klik terasa tak terjadi apa-apa,
               lalu berkasnya tiba-tiba muncul. Popover DIBIARKAN terbuka dengan
               tombolnya menyatakan dirinya sedang bekerja, dan ditutup oleh
               watcher `sibuk` begitu induk selesai. */
            this.aksiSibuk = apa;

            /* Mode ikut dikirim: penerima memakai satu jalur untuk keduanya,
               dan menyimpulkannya dari tipe akan membuat tombol Lihat pada
               FORM ber-PDF justru mengunduh. */
            this.$emit('lihat-versi', v, apa === 'unduh');

            /* Pengaman: bila induk ternyata tak melaporkan kesibukan sama
               sekali, popover tak boleh menggantung terbuka selamanya. */
            setTimeout(() => {
                if (this.aksiSibuk === apa && !this.sibuk) {
                    this.aksiSibuk = null;
                    this.menu = null;
                }
            }, 400);
        },
        onPointerDown(e) {
            // Klik pada node tautan tak boleh dibajak jadi pan.
            /* Node TIDAK lagi dikecualikan: menyeret dari atas node adalah cara
               paling wajar menggeser peta yang penuh node. Yang membedakan
               seretan dari klik kini jaraknya, bukan tempat menekannya. */
            if (e.target.closest?.(ABAIKAN_PETA)) return;

            this.geserJauh = false;
            this.awalKlik = { x: e.clientX, y: e.clientY };
            /* Node yang ditekan dicatat DI SINI, bukan diandalkan dari @click.
               setPointerCapture di bawah mengalihkan seluruh peristiwa pointer
               ke viewport, sehingga click pada node tak pernah berjalan — itu
               pula sebabnya kode lama mengecualikan node dari pan. */
            this.nodeTekan = e.target.closest?.('.alur-node')?.getAttribute('data-node') || null;
            this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (this.pointers.size === 1) this.panAwal = { x: e.clientX - this.view.x, y: e.clientY - this.view.y };
            if (this.pointers.size === 2) this.pinchAwal = { jarak: this.jarakPointer(), zoom: this.view.zoom };
            e.currentTarget.setPointerCapture?.(e.pointerId);
        },
        onPointerMove(e) {
            if (!this.pointers.has(e.pointerId)) return;
            this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

            /* Ambang 4px, bukan nol: jari dan tetikus selalu bergeser sedikit
               saat menekan, dan menganggapnya seretan membuat klik tak pernah
               terdaftar di layar sentuh. */
            if (this.awalKlik && Math.hypot(e.clientX - this.awalKlik.x, e.clientY - this.awalKlik.y) > 4) {
                this.geserJauh = true;
                this.menu = null;
            }

            if (this.pointers.size === 2 && this.pinchAwal) {
                const j = this.jarakPointer();
                if (j > 0)
                    this.view.zoom = Math.min(1.8, Math.max(0.3, this.pinchAwal.zoom * (j / this.pinchAwal.jarak)));

                return;
            }
            if (this.panAwal) {
                this.view.x = e.clientX - this.panAwal.x;
                this.view.y = e.clientY - this.panAwal.y;
            }
        },
        onPointerUp(e) {
            /* Pasangan penjaga di onPointerDown. Tanpa ini, melepas klik di
               atas tombol popover ditafsirkan sebagai "melepas di latar" dan
               popovernya ditutup sebelum kliknya sempat berjalan. */
            if (e.target.closest?.(ABAIKAN_PETA)) return;

            this.pointers.delete(e.pointerId);
            if (this.pointers.size < 2) this.pinchAwal = null;
            if (this.pointers.size === 0) this.panAwal = null;

            /* Menekan lalu melepas TANPA menyeret = memilih. Menyeret jauh
               berarti admin menggeser peta, bukan memilih node. */
            if (this.geserJauh) return;

            if (!this.nodeTekan) {
                this.menu = null; // menekan latar menutup popover

                return;
            }
            const n = this.node.find((x) => x.id === this.nodeTekan);
            this.nodeTekan = null;
            if (n) this.bukaMenu(n);
        },
        jarakPointer() {
            const [a, b] = [...this.pointers.values()];
            if (!a || !b) return 0;

            return Math.hypot(a.x - b.x, a.y - b.y);
        },
    },
};
</script>

<style scoped>
/* Di-scope: kelas non-scoped di modul ini terbukti nyangkut antar halaman Inertia. */
.alur-panel {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    background: #fff;
    overflow: hidden;
}

.alur-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--hcis-slate-200);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-indigo-50) 100%);
}
.alur-eyebrow {
    margin-bottom: 5px;
    color: var(--hcis-indigo-600);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.alur-header h3 {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 15px;
    font-weight: 800;
}
.alur-header p {
    max-width: 700px;
    margin: 4px 0 0;
    color: var(--hcis-slate-500);
    font-size: 11px;
    line-height: 1.55;
}
.alur-ringkas {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    min-width: 180px;
}
.alur-ringkas span {
    padding: 6px 10px;
    border: 1px solid #ddd6fe;
    border-radius: 999px;
    background: #fff;
    color: var(--hcis-indigo-700);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.alur-state {
    padding: 42px 24px;
    color: var(--hcis-slate-500);
    font-size: 13px;
    text-align: center;
}

.alur-viewport {
    position: relative;
    overflow: hidden;
    background-color: var(--hcis-slate-50);
    background-image: radial-gradient(var(--hcis-slate-300) 1px, transparent 1px);
    background-size: 18px 18px;
    cursor: grab;
    touch-action: none;
    user-select: none;
}
.alur-viewport:active {
    cursor: grabbing;
}

.alur-kontrol {
    position: absolute;
    right: 12px;
    top: 12px;
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(4px);
    font-size: 11px;
    color: var(--hcis-slate-600);
}
.alur-kontrol button {
    border: 0;
    background: transparent;
    width: 24px;
    height: 24px;
    border-radius: 999px;
    font-weight: 800;
    color: var(--hcis-slate-700);
    cursor: pointer;
}
.alur-kontrol button:hover {
    background: var(--hcis-indigo-50);
}
.alur-kontrol .fit {
    width: auto;
    padding: 0 8px;
}

/* ── Popover node ─────────────────────────────────────────────────
   Hidup DI LUAR lapisan yang diskalakan, jadi ukurannya tetap terbaca
   di zoom berapa pun. Ekornya menunjuk node yang dipilih supaya kaitannya
   tak perlu ditebak saat beberapa node berdekatan. */
.alur-pop {
    position: absolute;
    z-index: 6;
    transform: translateX(-50%);
    min-width: 210px;
    max-width: 260px;
    padding: 0.6rem;
    background: #fff;
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 12px;
    box-shadow: 0 18px 40px -12px rgba(15, 23, 42, 0.28);
    animation: ap-masuk 0.12s ease-out;
}
.alur-pop::before {
    content: '';
    position: absolute;
    top: -5px;
    left: 50%;
    width: 9px;
    height: 9px;
    margin-left: -4.5px;
    background: #fff;
    border-left: 1px solid var(--bs-border-color, #e5e7eb);
    border-top: 1px solid var(--bs-border-color, #e5e7eb);
    transform: rotate(45deg);
}
@keyframes ap-masuk {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-4px);
    }
}
@media (prefers-reduced-motion: reduce) {
    .alur-pop {
        animation: none;
    }
}

.ap-kepala {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}
.ap-judul {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    flex: 1 1 auto;
    min-width: 0;
}
.ap-tanda {
    flex: none;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.ap-tanda.ok {
    background: var(--hcis-green-100);
    color: #166534;
}
.ap-tanda.draft {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}
.ap-tanda.mati {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
}
.ap-sub {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    margin: 0.15rem 0 0.5rem;
}

.ap-aksi {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    width: 100%;
    font-size: 0.79rem;
    font-weight: 600;
    color: var(--hcis-slate-900);
    text-decoration: none;
    background: transparent;
    border: 1px solid var(--bs-border-color, #e5e7eb);
    border-radius: 9px;
    padding: 0.42rem 0.55rem;
    margin-top: 0.3rem;
    cursor: pointer;
    text-align: left;
}
.ap-aksi:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}
.ap-aksi:disabled {
    cursor: default;
}
/* Yang ditunggu tetap penuh warna; SAUDARANYA yang diredupkan. Meredupkan
   tombol yang sedang bekerja membuatnya tampak mati, bukan sibuk. */
.ap-aksi:disabled:not(.menunggu) {
    opacity: 0.45;
}
.ap-aksi.menunggu {
    border-color: var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
}
.berputar {
    display: inline-block;
    animation: alur-putar 0.9s linear infinite;
}
@keyframes alur-putar {
    to {
        transform: rotate(360deg);
    }
}
.ap-aksi.utama {
    border-color: var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
}
.ap-aksi.utama:hover {
    background: var(--hcis-indigo-100);
}
.ap-kosong {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin: 0.35rem 0 0;
}

/* Node yang sedang dipilih tetap terlihat meski yang lain diredupkan. */
.alur-node.bisa {
    cursor: pointer;
}
.alur-node.pilih {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}

.alur-legenda {
    position: absolute;
    left: 12px;
    bottom: 10px;
    z-index: 3;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 5px 10px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(4px);
    font-size: 10.5px;
    color: var(--hcis-slate-500);
}
.alur-legenda span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.lg-garis {
    display: inline-block;
    width: 20px;
    height: 0;
    border-top: 3px solid var(--hcis-indigo-300);
    border-radius: 2px;
}
.lg-garis.putus {
    border-top: 2px dashed var(--hcis-slate-300);
}

.alur-kanvas {
    position: absolute;
    left: 0;
    top: 0;
    transform-origin: 0 0;
}
.alur-tepi {
    position: absolute;
    left: 0;
    top: 0;
    pointer-events: none;
}
.tepi {
    fill: none;
    stroke: var(--hcis-indigo-300);
    transition: opacity 0.15s ease;
}
.tepi.mati {
    stroke: var(--hcis-slate-300);
    stroke-dasharray: 4 4;
}
.tepi.redup {
    opacity: 0.15;
}
marker#sop-alur-panah path {
    fill: var(--hcis-indigo-300);
}
marker#sop-alur-panah-mati path {
    fill: var(--hcis-slate-300);
}

.alur-node {
    position: absolute;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
    padding: 8px 10px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    display: block;
    transition:
        opacity 0.15s ease,
        border-color 0.15s ease,
        transform 0.12s ease,
        box-shadow 0.15s ease;
}
.alur-node.redup {
    opacity: 0.22;
}
.alur-node.tautan {
    cursor: pointer;
}
.alur-node.tautan:hover {
    border-color: var(--hcis-indigo-500);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.14);
}

.n-kepala {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--hcis-slate-400);
}
.n-judul {
    font-size: 12.5px;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.n-sub {
    font-size: 10px;
    color: var(--hcis-slate-500);
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.n-orang {
    font-size: 10.5px;
    color: var(--hcis-slate-500);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.n-orang .tnum {
    font-weight: 800;
    color: var(--hcis-slate-900);
}
.n-bar {
    flex: 1 1 auto;
    height: 4px;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    overflow: hidden;
    margin-left: 2px;
}
.n-bar span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: var(--hcis-indigo-500);
}
.n-tanda {
    display: inline-block;
    margin-top: 4px;
    font-size: 9px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.n-tanda.ok {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
}
.n-tanda.draft {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
}
.n-tanda.mati {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
}

.alur-node.dok {
    border-color: var(--hcis-indigo-200);
    background: linear-gradient(135deg, var(--hcis-indigo-50), var(--hcis-violet-50));
}
.alur-node.versi.hidup {
    border-color: var(--hcis-emerald-300);
}
.alur-node.versi.mati {
    background: var(--hcis-zinc-50);
}
.alur-node.versi.mati .n-judul {
    color: var(--hcis-slate-400);
}
.alur-node.paket {
    border-left: 3px solid var(--hcis-emerald-500);
}
.alur-node.paket.draft {
    border-left: 3px solid var(--hcis-slate-300);
    background: var(--hcis-zinc-50);
}
.alur-node.paket.draft .n-bar span {
    background: var(--hcis-slate-300);
}

.alur-lipat {
    position: absolute;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 10px;
    background: #fff;
    padding: 4px 8px;
    font-size: 10.5px;
    font-weight: 700;
    color: var(--hcis-slate-500);
    cursor: pointer;
}
.alur-lipat:hover {
    border-color: var(--hcis-slate-400);
    color: var(--hcis-slate-900);
}

@media (prefers-reduced-motion: reduce) {
    .alur-node,
    .tepi {
        transition: none;
    }
}
</style>
