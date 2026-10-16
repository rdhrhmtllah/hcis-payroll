<template>
    <div class="gp sop-scope" :class="`f-${fase}`">
        <div class="gp-aurora" aria-hidden="true"></div>

        <div class="gp-shell">
            <nav class="gp-remah" aria-label="Jejak halaman">
                <a href="/sop/saya"><i class="bi bi-house-door-fill"></i> Functional Document</a>
                <i class="bi bi-chevron-right gp-remah-tengah"></i>
                <a href="/sop/repository" class="gp-remah-tengah">Perpustakaan</a>
                <i class="bi bi-chevron-right"></i>
                <strong>{{ paket.nama }}</strong>
            </nav>

            <div class="gp-kartu">
                <!-- ══ STEPPER — satu untuk SEMUA fase ═══════════════════════
                     Menggantikan cincin yang dulu melayang sendirian di pojok
                     kanan atas tanpa terhubung ke apa pun. Di sini ia jadi tulang
                     punggung halaman: hadir di tiap fase, di tempat yang sama. -->
                <ol class="gp-langkah">
                    <li v-for="l in langkah" :key="l.n" :class="l.nada">
                        <span class="gp-node">
                            <i v-if="l.nada === 'lewat'" class="bi bi-check-lg"></i>
                            <template v-else>{{ l.n }}</template>
                        </span>
                        <span class="gp-node-t">
                            <strong>{{ l.judul }}</strong>
                            <em>{{ l.sub }}</em>
                        </span>
                    </li>
                </ol>

                <!-- ══ FASE 1 — PERSIAPAN ════════════════════════════════ -->
                <div v-if="fase === 'siap'" class="gp-belah">
                    <!-- Kolom sampul. Tinggi kolom = tinggi kolom kanan berkat
                         align-items:stretch, jadi keduanya SELALU rata bawah
                         berapa pun panjang isinya. -->
                    <aside class="gp-sampul">
                        <img v-if="paket.cover_url" :src="paket.cover_url" :alt="paket.nama" />
                        <div v-else class="gp-sampul-kosong"><i class="bi bi-journal-richtext"></i></div>
                        <div class="gp-sampul-scrim"></div>

                        <div class="gp-sampul-atas">
                            <span v-if="paket.prioritas" class="gp-pil hangat"
                                ><i class="bi bi-flag-fill"></i> {{ labelPrioritas }}</span
                            >
                        </div>

                        <div class="gp-sampul-bawah">
                            <span class="gp-gembok"><i class="bi bi-lock-fill"></i></span>
                            <div class="min-w-0">
                                <strong>Dokumen terkunci</strong>
                                <em>Isi dokumen belum dikirim ke perangkat Anda.</em>
                            </div>
                        </div>
                    </aside>

                    <main class="gp-badan">
                        <span class="gp-tanda"><i class="bi bi-shield-lock-fill"></i> Verifikasi kehadiran wajib</span>

                        <h1 class="gp-judul">{{ paket.nama }}</h1>
                        <p v-if="garisOtoritas" class="gp-otoritas">
                            <i class="bi bi-patch-check-fill"></i> {{ garisOtoritas }}
                        </p>

                        <p class="gp-alert">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>{{
                                penjelasan ||
                                'Presensi kehadiran wajib dicatat sebelum dokumen ini dibuka, sebagai bukti kepatuhan.'
                            }}</span>
                        </p>

                        <div v-if="komposisi.length" class="gp-blok">
                            <h2 class="gp-h">Yang akan Anda baca</h2>
                            <ul class="gp-komposisi">
                                <li v-for="k in komposisi" :key="k.tipe">
                                    <i class="bi" :class="k.ikon"></i>
                                    <strong class="tnum">{{ k.jml }}</strong> {{ k.label }}
                                </li>
                            </ul>
                        </div>

                        <!-- Dua kartu pemeriksaan, tinggi sama, kolom sama lebar. -->
                        <div class="gp-cek">
                            <section class="gp-cek-kartu">
                                <h2 class="gp-h"><i class="bi bi-sliders2"></i> Kesiapan perangkat</h2>
                                <ul class="gp-izin">
                                    <li v-for="i in izin" :key="i.k" :class="i.nada">
                                        <span class="gp-izin-ico">
                                            <span v-if="i.sedang" class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi" :class="i.ikon"></i>
                                        </span>
                                        <span class="gp-izin-n">
                                            {{ i.label }}
                                            <em v-if="i.ket">{{ i.ket }}</em>
                                        </span>

                                        <!-- Tombol per baris: hanya muncul pada yang MEMANG bisa
                                             ditindak. Baris HTTPS tak punya aksi — statusnya
                                             ditentukan alamat halaman, bukan izin perangkat. -->
                                        <button
                                            v-if="i.aksi"
                                            class="gp-izin-minta"
                                            :disabled="i.sedang"
                                            @click="i.aksi()"
                                        >
                                            {{ i.aksiLabel }}
                                        </button>
                                        <span v-else class="gp-izin-pil">{{ i.status }}</span>
                                    </li>
                                </ul>

                                <!-- Peramban TIDAK akan memunculkan dialog lagi untuk origin yang
                                     sudah diblokir. Jadi yang berguna di sini bukan tombol, tapi
                                     langkah konkret membuka blokirnya. -->
                                <div v-if="adaDitolak" class="gp-bantu">
                                    <strong
                                        ><i class="bi bi-exclamation-triangle-fill"></i> Izin diblokir peramban</strong
                                    >
                                    <ol>
                                        <li>
                                            Klik ikon <i class="bi bi-sliders2"></i> atau
                                            <i class="bi bi-lock-fill"></i> di kiri bilah alamat.
                                        </li>
                                        <li>
                                            Ubah <strong>Kamera</strong> (dan <strong>Lokasi</strong>) jadi
                                            <strong>Izinkan</strong>.
                                        </li>
                                        <li>
                                            Kembali ke sini lalu tekan <strong>Minta izin perangkat</strong> — tanpa
                                            perlu muat ulang.
                                        </li>
                                    </ol>
                                </div>

                                <button class="gp-ulang" :disabled="mintaSedang" @click="mintaSemua">
                                    <span v-if="mintaSedang" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-shield-check"></i>
                                    {{ mintaSedang ? 'Meminta izin…' : 'Minta izin perangkat' }}
                                </button>
                            </section>

                            <!-- Menggantikan kartu radar GPS. Radar menampilkan angka
                                 yang tak bisa ditindaklanjuti siapa pun — orang tak bisa
                                 memperbaiki sinyalnya, dan lokasi memang tak pernah
                                 memblokir. Isi ini sebaliknya: seluruhnya bisa dikerjakan
                                 dalam beberapa detik, tepat sebelum kamera menyala. -->
                            <section class="gp-cek-kartu">
                                <h2 class="gp-h"><i class="bi bi-person-video2"></i> Sebelum memotret</h2>
                                <ul class="gp-tips">
                                    <li v-for="t in tips" :key="t.judul">
                                        <span class="gp-tips-ico"><i class="bi" :class="t.ikon"></i></span>
                                        <span class="min-w-0">
                                            <strong>{{ t.judul }}</strong>
                                            <em>{{ t.sub }}</em>
                                        </span>
                                    </li>
                                </ul>
                            </section>
                        </div>

                        <!-- Dermaga aksi: margin-top auto menempelkannya ke dasar
                             kolom, sehingga tombol utama selalu duduk sejajar
                             dengan ujung bawah sampul di sebelahnya. -->
                        <div class="gp-dok">
                            <div class="gp-dok-tombol">
                                <button
                                    class="gp-btn utama"
                                    :class="{ siap: bolehMulai }"
                                    :disabled="!bolehMulai"
                                    @click="fase = 'kamera'"
                                >
                                    <i class="bi bi-camera-fill"></i> Mulai check-in presensi
                                </button>
                                <a href="/sop/saya" class="gp-btn">Kembali</a>
                            </div>
                            <p v-if="!bolehMulai" class="gp-blokir">
                                <i class="bi bi-exclamation-triangle-fill"></i> {{ alasanBlokir }}
                            </p>
                            <details v-else class="gp-jujur">
                                <summary>Apa saja yang direkam?</summary>
                                <ul>
                                    <li>
                                        <i class="bi bi-camera-fill"></i>
                                        <span
                                            ><strong>Foto wajah</strong> — sekali, dari kamera langsung. Unggahan galeri
                                            ditolak server.</span
                                        >
                                    </li>
                                    <li>
                                        <i class="bi bi-clock-fill"></i>
                                        <span><strong>Waktu</strong> — dari jam server, bukan jam perangkat Anda.</span>
                                    </li>
                                    <li>
                                        <i class="bi bi-geo-alt-fill"></i>
                                        <span
                                            ><strong>Lokasi &amp; akurasinya</strong> — opsional, tak pernah
                                            memblokir.</span
                                        >
                                    </li>
                                </ul>
                            </details>
                        </div>
                    </main>
                </div>

                <!-- ══ FASE 2 — KAMERA ═══════════════════════════════════ -->
                <section v-else-if="fase === 'kamera'" class="gp-fokus">
                    <SopFaceCapture
                        :percobaan-awal="percobaanTerpakai"
                        @capture="terimaFoto"
                        @error="galatKamera"
                        @gagal="percobaanTerpakai = $event"
                    />
                    <button class="gp-btn halus" @click="fase = 'siap'">
                        <i class="bi bi-arrow-left"></i> Kembali ke persiapan
                    </button>
                </section>

                <!-- ══ FASE 3 — PRATINJAU ════════════════════════════════ -->
                <section v-else-if="fase === 'pratinjau'" class="gp-fokus">
                    <div class="gp-potret">
                        <img :src="hasil" alt="Pratinjau foto presensi" />
                        <span class="gp-potret-tanda"><i class="bi bi-check-circle-fill"></i> Foto siap dikirim</span>
                    </div>
                    <p class="gp-tanya">Wajah Anda terlihat jelas?</p>
                    <div class="gp-dok-tombol">
                        <button class="gp-btn" :disabled="sibuk" @click="ulangi">
                            <i class="bi bi-arrow-counterclockwise"></i> Ulangi foto
                        </button>
                        <button class="gp-btn utama" :disabled="sibuk" @click="kirim">
                            <span v-if="sibuk" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="bi bi-check2-circle"></i>
                            Kirim &amp; buka dokumen
                        </button>
                    </div>
                    <div class="gp-lokasi-pill">
                        <div class="gp-lokasi-pill-ico">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="gp-lokasi-pill-isi">
                            <span class="gp-lokasi-pill-label">Sinyal GPS Presensi</span>
                            <strong v-if="lokasi" class="gp-lokasi-pill-val"
                                >Akurasi ±{{ Math.round(lokasi.akurasi) }} m</strong
                            >
                            <strong v-else class="gp-lokasi-pill-val idle">Tanpa Koordinat — Presensi Sah</strong>
                        </div>
                        <span
                            v-if="lokasi"
                            class="gp-lokasi-pill-badge"
                            :class="lokasi.akurasi <= 20 ? 'presisi' : 'cukup'"
                        >
                            {{ lokasi.akurasi <= 20 ? 'Presisi Tinggi' : 'Presisi Cukup' }}
                        </span>
                    </div>
                </section>

                <!-- ══ FASE 4 — SUKSES ═══════════════════════════════════ -->
                <section v-else class="gp-fokus gp-sukses-stage">
                    <div class="gp-buka-wrap my-3">
                        <div class="gp-buka-riak r1"></div>
                        <div class="gp-buka-riak r2"></div>
                        <div class="gp-buka"><i class="bi bi-unlock-fill"></i></div>
                    </div>

                    <div class="text-center">
                        <span class="gp-verifikasi-badge mb-2">
                            <i class="bi bi-patch-check-fill text-success me-1"></i> Presensi Biometrik Terverifikasi
                        </span>
                        <h2 class="gp-judul tengah text-success fs-3 m-0">Gerbang Dokumen Terbuka!</h2>
                        <p class="gp-otoritas tengah mt-1">Akses dokumen resmi telah disetujui oleh sistem.</p>
                    </div>

                    <!-- Redirection Loading Indicator -->
                    <div class="gp-pengalihan-box mt-3">
                        <div class="gp-pengalihan-badge">
                            <span class="spinner-border spinner-border-sm text-success me-2" role="status"></span>
                            <span class="fw-bold">Mengalihkan Anda Ke Dokumen</span>
                            <i class="bi bi-arrow-right fs-5 text-success ms-1 gp-panah-jalan"></i>
                        </div>
                        <div class="gp-pengalihan-bar-wrap">
                            <div class="gp-pengalihan-bar"></div>
                        </div>
                    </div>
                </section>

                <p v-if="galat" class="gp-galat"><i class="bi bi-exclamation-triangle-fill"></i> {{ galat }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import SopFaceCapture from '../../Shared/components/ui/SopFaceCapture.vue';
import { temaAksen } from '../../Shared/support/constants.js';
import { metaPrioritas, setPrioritas } from '../../Shared/support/prioritas.js';
import '../../Shared/support/sopTheme.css';

const TIPE = {
    DOKUMEN: { label: 'dokumen', ikon: 'bi-file-earmark-text' },
    VIDEO: { label: 'video', ikon: 'bi-play-btn' },
    KUIS: { label: 'kuis', ikon: 'bi-patch-question' },
    FORM: { label: 'formulir', ikon: 'bi-file-earmark-arrow-down' },
    PARAGRAF: { label: 'bagian teks', ikon: 'bi-text-paragraph' },
    TAUTAN: { label: 'tautan', ikon: 'bi-link-45deg' },
    ABSENSI: { label: 'presensi', ikon: 'bi-geo-alt' },
};

const LANGKAH = [
    { n: 1, judul: 'Persiapan', sub: 'Perangkat & izin' },
    { n: 2, judul: 'Potret', sub: 'Deteksi wajah' },
    { n: 3, judul: 'Konfirmasi', sub: 'Kirim & buka' },
];

/**
 * Layar check-in presensi — MENGGANTIKAN halaman dokumen, bukan menumpanginya.
 *
 * Server (SopRepositoryController::show) merender halaman ini alih-alih
 * SopDetail selama presensi belum tercatat, dan API detail/konten/versi serta
 * token PDF aman menjawab 423. Tak ada satu byte pun isi dokumen yang sampai ke
 * perangkat sebelum check-in.
 *
 * TATA LETAK — tiga aturan yang menjaganya tetap rapi:
 *
 *  1. SATU KARTU, SATU STEPPER. Stepper berdiri di puncak kartu dan hadir di
 *     SEMUA fase, jadi posisinya tak pernah berpindah. Versi sebelumnya menaruh
 *     cincin langkah melayang di pojok kanan atas, tak terhubung ke apa pun.
 *
 *  2. DUA KOLOM SELALU RATA BAWAH. `align-items: stretch` pada grid ditambah
 *     `margin-top: auto` pada dermaga aksi membuat tombol utama duduk sejajar
 *     dengan ujung bawah sampul, berapa pun panjang teksnya.
 *
 *  3. NOL KELAS UTILITAS SPASI. Versi sebelumnya memakai px-2.5, py-1.5, p-2.5
 *     dan sejenisnya — Bootstrap hanya punya 0–5 bilangan bulat, jadi kelas itu
 *     TIDAK ADA dan tak melakukan apa pun. Itulah sumber utama spasi yang
 *     terlihat acak. Semua jarak di sini ditulis eksplisit di CSS berlingkup.
 */
export default {
    name: 'SopCheckin',
    /**
     * TANPA AppShell — sengaja.
     *
     * Dua alasan, satu teknis satu produk:
     *
     *  1. Di dalam shell halaman ini MUSTAHIL penuh. `.shell-content` sudah
     *     memakai `min-height: calc(100vh - 4rem)` DITAMBAH `padding: 1rem`,
     *     jadi `min-height: 100dvh` di dalamnya menghasilkan tinggi dokumen
     *     100dvh + 6rem — selalu meluap, selalu berbingkai.
     *
     *  2. Halaman ini GERBANG, bukan halaman aplikasi. Menampilkan sidebar
     *     penuh tautan di layar yang tugasnya menahan orang justru mengundang
     *     menyimpang, padahal fokusnya harus di potret. Jalan keluar tetap ada
     *     lewat remah di atas dan tombol "Kembali".
     *
     * Pola yang sama dipakai Login.vue dan Error.vue.
     */
    layout: null,
    components: { SopFaceCapture },
    props: {
        prioritas: { type: Array, default: () => [] },
        idPaket: { type: String, required: true },
        paket: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            fase: 'siap', // siap | kamera | pratinjau | sukses
            // Dipegang halaman, bukan komponen kamera: mengulang foto memasang
            // ulang komponennya, dan penghitung yang ikut ter-reset akan
            // menyembunyikan lagi tombol manual yang sudah pantas muncul.
            percobaanTerpakai: 0,
            hasil: null,
            sibuk: false,
            galat: '',
            lokasi: null,
            mencariLokasi: false,
            jamWatch: null,
            izinKamera: 'entah',
            izinLokasi: 'entah',
            mintaKamera: false,
            mintaLokasi: false,
            tips: [
                {
                    ikon: 'bi-person-bounding-box',
                    judul: 'Masuk ke dalam oval',
                    sub: 'Wajah penuh, bahu tak perlu ikut.',
                },
                { ikon: 'bi-brightness-high-fill', judul: 'Hadap cahaya', sub: 'Jangan membelakangi jendela.' },
                { ikon: 'bi-emoji-neutral', judul: 'Wajah terlihat utuh', sub: 'Lepas masker, topi, kacamata gelap.' },
                { ikon: 'bi-stopwatch', judul: 'Tahan diam 2 detik', sub: 'Foto diambil otomatis saat posisinya pas.' },
            ],
        };
    },
    computed: {
        langkahKe() {
            return { siap: 1, kamera: 2, pratinjau: 3, sukses: 3 }[this.fase] || 1;
        },
        langkah() {
            return LANGKAH.map((l) => ({
                ...l,
                nada: l.n < this.langkahKe ? 'lewat' : l.n === this.langkahKe ? 'kini' : 'nanti',
            }));
        },
        tema() {
            return temaAksen(this.paket.nama);
        },
        labelPrioritas() {
            return metaPrioritas(this.paket.prioritas).label;
        },
        penjelasan() {
            return this.paket.tujuan || '';
        },
        garisOtoritas() {
            const p = [];
            if (this.paket.kode) p.push(this.paket.kode);
            if (this.paket.versi) p.push(`Versi ${this.paket.versi}`);
            if (this.paket.berlaku) {
                p.push(
                    `berlaku sejak ${new Date(this.paket.berlaku).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}`,
                );
            }

            return p.join(' · ');
        },
        komposisi() {
            return Object.entries(this.paket.komposisi || {})
                .map(([tipe, jml]) => ({
                    tipe,
                    jml,
                    ...(TIPE[tipe] || { label: String(tipe).toLowerCase(), ikon: 'bi-dot' }),
                }))
                .sort((a, b) => b.jml - a.jml);
        },
        izin() {
            const peta = {
                granted: { status: 'Aktif', nada: 'ok', ikon: 'bi-check-lg' },
                denied: { status: 'Ditolak', nada: 'bad', ikon: 'bi-x-lg' },
                prompt: { status: 'Akan diminta', nada: '', ikon: 'bi-question-lg' },
                entah: { status: 'Belum terbaca', nada: '', ikon: 'bi-dash-lg' },
            };
            const aman = window.isSecureContext;

            // Label tombol mengikuti keadaan: yang belum pernah diminta berbunyi
            // "Minta", yang sudah ditolak berbunyi "Coba lagi" — karena setelah
            // orang membuka blokirnya di setelan situs, tombol inilah yang
            // menangkap perubahannya tanpa perlu muat ulang halaman.
            const aksiUntuk = (keadaan, minta) => (keadaan === 'granted' ? null : minta);
            const labelAksi = (keadaan) => (keadaan === 'denied' ? 'Coba lagi' : 'Minta izin');

            return [
                {
                    k: 'kamera',
                    label: 'Kamera wajah',
                    ...peta[this.izinKamera],
                    sedang: this.mintaKamera,
                    aksi: aksiUntuk(this.izinKamera, this.mintaIzinKamera),
                    aksiLabel: labelAksi(this.izinKamera),
                },
                {
                    k: 'lokasi',
                    label: 'Koordinat GPS',
                    ...peta[this.izinLokasi],
                    // Akurasi tetap ditampilkan — cuma turun pangkat dari kartu
                    // sendiri jadi keterangan sebaris, sesuai bobotnya: ia bukti
                    // yang direkam, bukan syarat yang harus dipenuhi.
                    ket: this.ketLokasi,
                    sedang: this.mintaLokasi,
                    aksi: aksiUntuk(this.izinLokasi, this.mintaIzinLokasi),
                    aksiLabel: labelAksi(this.izinLokasi),
                },
                {
                    k: 'aman',
                    label: 'Koneksi HTTPS',
                    status: aman ? 'Aman' : 'Tidak aman',
                    nada: aman ? 'ok' : 'bad',
                    ikon: aman ? 'bi-shield-check' : 'bi-shield-exclamation',
                    sedang: false,
                    aksi: null,
                },
            ];
        },
        ketLokasi() {
            if (this.mencariLokasi) return 'mengunci sinyal…';
            if (this.lokasi) return `akurasi ±${Math.round(this.lokasi.akurasi)} m · direkam sebagai bukti`;

            return '';
        },
        mintaSedang() {
            return this.mintaKamera || this.mintaLokasi;
        },
        adaDitolak() {
            return this.izinKamera === 'denied' || this.izinLokasi === 'denied';
        },
        /** Hanya dua hal yang benar-benar memblokir: izin kamera ditolak, atau konteks tak aman. */
        bolehMulai() {
            return this.izinKamera !== 'denied' && window.isSecureContext;
        },
        alasanBlokir() {
            if (!window.isSecureContext) return 'Halaman ini perlu dibuka lewat HTTPS agar kamera bisa dipakai.';

            return 'Izin kamera ditolak. Aktifkan kembali dari setelan peramban, lalu tekan “Cek ulang izin”.';
        },
    },
    async mounted() {
        // Baca dulu (murah, tanpa dialog), baru minta yang memang belum pernah
        // diminta. Urutannya penting: tanpa pembacaan awal, halaman akan
        // memunculkan dialog kamera pada orang yang sudah lama mengizinkannya.
        await this.periksaIzin();
        this.mintaOtomatis();
    },
    beforeUnmount() {
        this.lepasLokasi();
    },
    created() {
        setPrioritas(this.prioritas);
    },
    methods: {
        /**
         * Permissions API tak seragam antar peramban — Safari & Firefox lama
         * melempar untuk name:'camera'. Kegagalan di sini TIDAK boleh memblokir
         * apa pun; statusnya sekadar turun jadi "belum terbaca".
         */
        async periksaIzin() {
            if (!navigator.permissions?.query) return;
            for (const [nama, kunci] of [
                ['camera', 'izinKamera'],
                ['geolocation', 'izinLokasi'],
            ]) {
                try {
                    const r = await navigator.permissions.query({ name: nama });
                    this[kunci] = r.state;
                    r.onchange = () => {
                        this[kunci] = r.state;
                    };
                } catch (e) {
                    this[kunci] = 'entah';
                }
            }
        },
        /**
         * Minta izin KAMERA sungguhan.
         *
         * getUserMedia adalah satu-satunya cara memunculkan dialog izin kamera —
         * Permissions API hanya bisa MEMBACA. Aliran video langsung dimatikan
         * begitu didapat: tujuannya cuma memicu dialog, bukan menyalakan kamera,
         * dan lampu kamera yang menyala tanpa gambar bikin orang curiga.
         *
         * Catatan penting: bila origin sudah diblokir, panggilan ini melempar
         * NotAllowedError SEKETIKA tanpa dialog apa pun. Tak ada API yang bisa
         * memaksa peramban bertanya lagi (permissions.revoke sudah dihapus) —
         * jadi jalan satu-satunya lewat setelan situs, dan itulah yang
         * dijelaskan panel bantuan.
         */
        async mintaIzinKamera(olehPengguna = true) {
            if (!navigator.mediaDevices?.getUserMedia) {
                this.izinKamera = 'entah';
                return;
            }
            this.mintaKamera = true;
            try {
                const aliran = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false,
                });
                aliran.getTracks().forEach((t) => t.stop());
                this.izinKamera = 'granted';
                this.galat = '';
            } catch (e) {
                // NotAllowedError punya DUA arti yang tak bisa dibedakan dari
                // objek galatnya: "ditolak/diblokir" ATAU "Safari menuntut gestur
                // pengguna". Permintaan otomatis saat halaman dibuka karena itu
                // TIDAK PERNAH dicap "denied" — kalau salah cap, panel "izin
                // diblokir" muncul pada orang yang sebenarnya cuma perlu menekan
                // tombol, dan itu menyuruhnya mengubah setelan tanpa alasan.
                if (e?.name === 'NotAllowedError') this.izinKamera = olehPengguna ? 'denied' : 'entah';
                else this.izinKamera = 'entah';

                if (e?.name === 'NotFoundError' || e?.name === 'DevicesNotFoundError') {
                    this.galat = 'Perangkat kamera tidak ditemukan.';
                }
            } finally {
                this.mintaKamera = false;
            }
        },
        /** Minta izin LOKASI sungguhan; hasilnya sekalian dipakai sebagai koordinat awal. */
        mintaIzinLokasi(olehPengguna = true) {
            if (!navigator.geolocation) {
                this.izinLokasi = 'entah';
                return Promise.resolve();
            }
            this.mintaLokasi = true;

            return new Promise((selesai) => {
                navigator.geolocation.getCurrentPosition(
                    (p) => {
                        this.izinLokasi = 'granted';
                        const baru = {
                            lat: p.coords.latitude,
                            lng: p.coords.longitude,
                            akurasi: p.coords.accuracy || 0,
                        };
                        if (!this.lokasi || baru.akurasi <= this.lokasi.akurasi) this.lokasi = baru;
                        this.mencariLokasi = false;
                        this.mintaLokasi = false;
                        this.pantauLokasi(); // lanjut pantau supaya akurasinya membaik
                        selesai();
                    },
                    (e) => {
                        // PERMISSION_DENIED = 1. Sama seperti kamera, penolakan
                        // pada permintaan otomatis tak dicap permanen.
                        this.izinLokasi = e && e.code === 1 && olehPengguna ? 'denied' : 'entah';
                        this.mencariLokasi = false;
                        this.mintaLokasi = false;
                        selesai();
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
                );
            });
        },
        /**
         * Minta keduanya BERURUTAN, bukan bersamaan: sebagian peramban hanya
         * menampilkan satu dialog izin pada satu waktu dan menolak diam-diam
         * permintaan kedua yang datang bersamaan.
         */
        async mintaSemua() {
            await this.mintaIzinKamera();
            await this.mintaIzinLokasi();
        },
        /** Hanya minta yang memang belum pernah dijawab — jangan ganggu yang sudah. */
        async mintaOtomatis() {
            const perlu = (k) => k !== 'granted' && k !== 'denied';
            if (perlu(this.izinKamera)) await this.mintaIzinKamera(false);
            if (perlu(this.izinLokasi)) await this.mintaIzinLokasi(false);
            else this.pantauLokasi();
        },
        /**
         * watchPosition, bukan getCurrentPosition: akurasi membaik beberapa detik
         * setelah radio GPS menyala, dan sekali-ambil sering mengunci angka
         * terburuk — padahal angka itulah yang tersimpan jadi bukti.
         */
        pantauLokasi() {
            if (!navigator.geolocation || this.jamWatch !== null) return;
            this.mencariLokasi = true;
            this.jamWatch = navigator.geolocation.watchPosition(
                (p) => {
                    const baru = { lat: p.coords.latitude, lng: p.coords.longitude, akurasi: p.coords.accuracy || 0 };
                    if (!this.lokasi || baru.akurasi <= this.lokasi.akurasi) this.lokasi = baru;
                    this.mencariLokasi = false;
                },
                // Gagal ambil lokasi BUKAN kegagalan presensi.
                () => {
                    this.mencariLokasi = false;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
            );
        },
        lepasLokasi() {
            if (this.jamWatch !== null && navigator.geolocation) navigator.geolocation.clearWatch(this.jamWatch);
            this.jamWatch = null;
        },
        terimaFoto(data) {
            this.galat = '';
            this.hasil = data;
            this.fase = 'pratinjau';
        },
        galatKamera(pesan) {
            this.galat = pesan;
            this.fase = 'siap';
            this.periksaIzin();
        },
        ulangi() {
            this.hasil = null;
            this.fase = 'kamera';
        },
        async kirim() {
            if (!this.hasil) return;
            this.sibuk = true;
            try {
                const r = await axios.post(`/api/v1/sop/${this.idPaket}/absensi/checkin`, {
                    foto: this.hasil,
                    lat: this.lokasi ? this.lokasi.lat : null,
                    lng: this.lokasi ? this.lokasi.lng : null,
                    akurasi: this.lokasi ? this.lokasi.akurasi : null,
                });
                if (!r.data.success) throw new Error(r.data.message);
                this.fase = 'sukses';
                this.lepasLokasi();
                // Muat penuh, bukan router: gerbangnya ada di server, jadi halaman
                // detail harus diminta ulang untuk melewatinya.
                setTimeout(() => {
                    window.location.href = `/sop/${this.idPaket}`;
                }, 1200);
            } catch (e) {
                ElMessage.error(e.response?.data?.message || e.message || 'Gagal menyimpan presensi');
                this.sibuk = false;
            }
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.tengah {
    text-align: center;
}

/* ══════════════ Halaman ══════════════
   min-height + place-items, BUKAN height:100vh + overflow:hidden. Versi
   sebelumnya mengunci tinggi lalu menyembunyikan luapannya, sehingga di layar
   pendek isinya terpotong tanpa cara apa pun untuk menggulir. */
.gp {
    position: relative;
    min-height: 100vh;
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: clamp(0.75rem, 2vw, 1.5rem);
    background: #f6f8fc;
    color: var(--hcis-slate-800);
}
.gp-aurora {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(46% 34% at 12% 0%, rgba(99, 102, 241, 0.13), transparent 68%),
        radial-gradient(40% 32% at 92% 8%, rgba(56, 189, 248, 0.11), transparent 70%),
        radial-gradient(50% 40% at 50% 108%, rgba(139, 92, 246, 0.09), transparent 72%);
}

.gp-shell {
    position: relative;
    width: min(1180px, 100%);
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.gp-remah {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    font-size: 0.76rem;
    color: var(--hcis-slate-400);
}
.gp-remah a {
    color: var(--hcis-slate-500);
    text-decoration: none;
    font-weight: 600;
}
.gp-remah a:hover {
    color: var(--hcis-indigo-700);
}
.gp-remah > i {
    font-size: 0.6rem;
}
.gp-remah strong {
    color: var(--hcis-indigo-700);
    font-weight: 800;
    max-width: 32ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gp-kartu {
    --pad: clamp(1rem, 2.2vw, 1.6rem);
    background: #fff;
    border: 1px solid #e6eaf2;
    border-radius: 22px;
    padding: var(--pad);
    box-shadow: 0 30px 70px -42px rgba(15, 23, 42, 0.5);
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    animation: gp-naik 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes gp-naik {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

/* Di layar tinggi kartu ikut memanjang supaya halaman terasa terisi, bukan
   sekadar kotak kecil melayang di tengah lautan kosong. */
@media (min-width: 992px) and (min-height: 720px) {
    .gp-kartu {
        min-height: min(84dvh, 800px);
    }
    .gp-belah {
        flex: 1;
    }
}

/* ══════════════ Stepper ══════════════ */
/* Selebar KARTU, bukan selebar isinya: margin negatif seukuran padding kartu
   menariknya sampai ke tepi, lalu padding sendiri mengembalikan jarak bacanya.
   Radius atas dibuat 21px (22px radius kartu dikurangi 1px garis tepinya) agar
   sudut pita mengikuti sudut kartu dengan pas. */
.gp-langkah {
    list-style: none;
    margin: calc(var(--pad) * -1) calc(var(--pad) * -1) 0;
    padding: var(--pad) var(--pad) 1rem;
    /* FLEX, bukan grid 3 kolom sama lebar. Dengan grid, node ke-3 duduk di
       AWAL kolom ketiga (2/3 lebar) dan sisa kolomnya jadi ruang mati — pitanya
       selebar kartu tapi isinya berhenti di tengah. Di sini dua langkah pertama
       memuai (garis penghubungnya ikut memanjang) dan langkah terakhir memakai
       lebar seperlunya, sehingga labelnya persis mendarat di tepi kanan. */
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 1px solid #eef2f7;
    border-radius: 21px 21px 0 0;
    background: linear-gradient(180deg, #fbfcff, #fff);
}
.gp-langkah li {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
    flex: 1 1 0;
}
/* Langkah terakhir tak perlu memuai — ia penutup, bukan penghubung. */
.gp-langkah li:last-child {
    flex: 0 1 auto;
}
/* Garis penghubung digambar dari node, bukan elemen terpisah — jadi ia tak
   pernah meleset saat kolomnya melebar. */
.gp-langkah li:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 2.6rem;
    right: -0.5rem;
    height: 2px;
    /* 50% + translateY, BUKAN nilai piksel tetap: tinggi baris ditentukan blok
       teks yang lebih tinggi daripada nodenya, dan di layar sempit sub-labelnya
       disembunyikan sehingga tingginya berubah lagi. Nilai tetap akan meleset
       di kedua keadaan itu. */
    top: 50%;
    transform: translateY(-50%);
    background: #e8edf5;
    border-radius: 2px;
}
.gp-langkah li.lewat:not(:last-child)::after {
    background: var(--hcis-emerald-400);
}

.gp-node {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-100);
    border: 2px solid var(--hcis-slate-200);
    transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}
.gp-langkah li.kini .gp-node {
    color: #fff;
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.16);
}
.gp-langkah li.lewat .gp-node {
    color: #fff;
    background: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
}

.gp-node-t {
    min-width: 0;
}
.gp-node-t strong {
    display: block;
    font-size: 0.79rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    line-height: 1.25;
}
.gp-node-t em {
    font-style: normal;
    font-size: 0.68rem;
    color: var(--hcis-slate-300);
}
.gp-langkah li.kini .gp-node-t strong {
    color: var(--hcis-slate-800);
    font-weight: 800;
}
.gp-langkah li.lewat .gp-node-t strong {
    color: var(--hcis-slate-600);
}

/* ══════════════ Belah dua ══════════════ */
.gp-belah {
    display: grid;
    grid-template-columns: minmax(0, 300px) minmax(0, 1fr);
    gap: 1.4rem;
    align-items: stretch;
}

.gp-sampul {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    min-height: 320px;
    background: var(--hcis-slate-900);
    box-shadow: 0 20px 44px -24px rgba(15, 23, 42, 0.55);
}
.gp-sampul > img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.gp-sampul-kosong {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    font-size: 3.4rem;
    color: var(--hcis-indigo-300);
    background: linear-gradient(150deg, var(--hcis-indigo-900), var(--hcis-indigo-950));
}
.gp-sampul-scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.55) 0%, transparent 34%, rgba(15, 23, 42, 0.86) 100%);
}

.gp-sampul-atas {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    right: 0.75rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.gp-pil {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #fff;
    font-size: 0.66rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.22rem 0.6rem;
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(8px);
}
.gp-pil.hangat {
    color: var(--hcis-amber-200);
    border-color: rgba(253, 230, 138, 0.35);
}

.gp-sampul-bawah {
    position: absolute;
    left: 0.75rem;
    right: 0.75rem;
    bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    color: #fff;
}
.gp-gembok {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    background: rgba(239, 68, 68, 0.9);
    font-size: 0.9rem;
    animation: gp-napas 3s ease-in-out infinite;
}
@keyframes gp-napas {
    50% {
        transform: scale(1.07);
    }
}
.gp-sampul-bawah strong {
    display: block;
    font-size: 0.8rem;
    font-weight: 800;
}
.gp-sampul-bawah em {
    font-style: normal;
    display: block;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.75);
    line-height: 1.35;
}

/* ══════════════ Badan kanan ══════════════ */
.gp-badan {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.gp-tanda {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.24);
    border-radius: 999px;
    padding: 0.24rem 0.7rem;
}
.gp-judul {
    font-size: clamp(1.35rem, 2.6vw, 1.95rem);
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin: 0.6rem 0 0.25rem;
    line-height: 1.15;
    text-wrap: balance;
}
.gp-otoritas {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-slate-400);
}
.gp-otoritas i {
    color: var(--hcis-indigo-500);
}

.gp-alert {
    display: flex;
    gap: 0.5rem;
    margin: 0.9rem 0 0;
    padding: 0.65rem 0.85rem;
    border-radius: 12px;
    font-size: 0.79rem;
    line-height: 1.55;
    color: var(--hcis-indigo-800);
    background: rgba(99, 102, 241, 0.07);
    border: 1px solid rgba(99, 102, 241, 0.2);
}
.gp-alert i {
    margin-top: 0.15rem;
    color: var(--hcis-indigo-600);
}

.gp-blok {
    margin-top: 1rem;
}
.gp-h {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0 0 0.5rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--hcis-slate-400);
}
.gp-h i {
    color: var(--hcis-indigo-500);
    font-size: 0.85rem;
}

.gp-komposisi {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.gp-komposisi li {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-50);
    border: 1px solid #e6eaf2;
    border-radius: 999px;
    padding: 0.26rem 0.65rem;
}
.gp-komposisi i {
    color: var(--hcis-indigo-400);
}
.gp-komposisi strong {
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.gp-cek {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.7rem;
    margin-top: 1rem;
    align-items: stretch;
}
.gp-cek-kartu {
    display: flex;
    flex-direction: column;
    background: var(--hcis-slate-50);
    border: 1px solid #e6eaf2;
    border-radius: 14px;
    padding: 0.8rem 0.9rem;
}

.gp-izin {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.gp-izin li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}
.gp-izin-ico {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 0.74rem;
    color: var(--hcis-slate-400);
    background: #eef2f7;
    transition:
        color 0.25s ease,
        background 0.25s ease;
}
.gp-izin li.ok .gp-izin-ico {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.14);
    animation: gp-pop 0.35s cubic-bezier(0.2, 0.9, 0.25, 1.15);
}
.gp-izin li.bad .gp-izin-ico {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.13);
}
@keyframes gp-pop {
    0% {
        transform: scale(0.6);
    }
    60% {
        transform: scale(1.14);
    }
}
.gp-izin-n {
    flex: 1;
    min-width: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    line-height: 1.35;
}
.gp-izin-pil {
    flex-shrink: 0;
    margin-top: 0.12rem;
    font-size: 0.62rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.14rem 0.48rem;
    color: var(--hcis-slate-500);
    background: #eef2f7;
}
.gp-izin li.ok .gp-izin-pil {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.14);
}
.gp-izin li.bad .gp-izin-pil {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.13);
}

.gp-izin-minta {
    flex-shrink: 0;
    margin-top: 0.08rem;
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-radius: 999px;
    padding: 0.16rem 0.6rem;
    font-size: 0.64rem;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
    transition:
        background 0.15s ease,
        border-color 0.15s ease;
}
.gp-izin-minta:hover:not(:disabled) {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-300);
}
.gp-izin-minta:disabled {
    opacity: 0.55;
    cursor: default;
}
.gp-izin li.bad .gp-izin-minta {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
}
.gp-izin li.bad .gp-izin-minta:hover:not(:disabled) {
    background: var(--hcis-red-100);
}

.gp-bantu {
    margin-top: 0.75rem;
    padding: 0.65rem 0.75rem;
    border-radius: 11px;
    background: rgba(239, 68, 68, 0.06);
    border: 1px solid rgba(239, 68, 68, 0.22);
}
.gp-bantu strong {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.73rem;
    font-weight: 800;
    color: var(--hcis-red-700);
}
.gp-bantu ol {
    margin: 0.4rem 0 0;
    padding-left: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.gp-bantu li {
    font-size: 0.71rem;
    color: var(--hcis-red-900);
    line-height: 1.5;
}
.gp-bantu li strong {
    display: inline;
    color: var(--hcis-red-900);
    font-size: inherit;
}

.gp-ulang {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: auto;
    padding-top: 0.75rem;
    border: 0;
    background: transparent;
    color: var(--hcis-indigo-600);
    font-size: 0.73rem;
    font-weight: 700;
    cursor: pointer;
}
.gp-ulang:hover:not(:disabled) {
    text-decoration: underline;
}
.gp-ulang:disabled {
    color: var(--hcis-slate-400);
    cursor: default;
}

.gp-tips {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}
.gp-tips li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}
.gp-tips-ico {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 0.76rem;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.11);
}
.gp-tips strong {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    line-height: 1.3;
}
.gp-tips em {
    font-style: normal;
    display: block;
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    line-height: 1.4;
}

/* Keterangan sebaris di bawah nama izin — tempat akurasi GPS mendarat setelah
   turun pangkat dari kartunya sendiri. */
.gp-izin-n em {
    font-style: normal;
    display: block;
    font-size: 0.66rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
    line-height: 1.35;
}

/* margin-top:auto — dermaga selalu menempel ke dasar kolom, sehingga tombol
   utama sejajar dengan ujung bawah sampul di sebelahnya. */
.gp-dok {
    margin-top: auto;
    padding-top: 1.1rem;
}
.gp-dok-tombol {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.gp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 12px;
    padding: 0.62rem 1.1rem;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    transition:
        transform 0.14s ease,
        box-shadow 0.16s ease,
        background 0.16s ease,
        border-color 0.16s ease;
}
.gp-btn:hover:not(:disabled) {
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
    transform: translateY(-1px);
}
.gp-btn.utama {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-violet-600));
    box-shadow: 0 12px 26px -14px rgba(99, 102, 241, 0.95);
}
.gp-btn.utama:hover:not(:disabled) {
    color: #fff;
    box-shadow: 0 16px 32px -14px rgba(99, 102, 241, 1);
}
.gp-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.gp-btn.halus {
    background: transparent;
    border-color: transparent;
    color: var(--hcis-slate-400);
}
.gp-btn.halus:hover {
    color: var(--hcis-indigo-700);
}
/* Denyut halus HANYA saat sudah bisa ditekan — gerakan berarti "giliranmu". */
.gp-btn.utama.siap {
    animation: gp-siap 2.8s ease-in-out infinite;
}
@keyframes gp-siap {
    0%,
    100% {
        box-shadow: 0 12px 26px -14px rgba(99, 102, 241, 0.95);
    }
    50% {
        box-shadow: 0 14px 32px -10px rgba(99, 102, 241, 1);
    }
}

.gp-blokir {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0.65rem 0 0;
    font-size: 0.74rem;
    color: var(--hcis-amber-700);
    line-height: 1.5;
}
.gp-jujur {
    margin-top: 0.7rem;
    font-size: 0.73rem;
    color: var(--hcis-slate-400);
}
.gp-jujur summary {
    cursor: pointer;
    font-weight: 700;
    color: var(--hcis-indigo-600);
}
.gp-jujur ul {
    list-style: none;
    margin: 0.55rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.38rem;
}
.gp-jujur li {
    display: flex;
    gap: 0.45rem;
    line-height: 1.5;
}
.gp-jujur i {
    color: var(--hcis-indigo-400);
    margin-top: 0.15rem;
}
.gp-jujur strong {
    color: var(--hcis-slate-600);
}

/* ══════════════ Fase potret ══════════════ */
.gp-fokus {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    padding: 0.5rem 0 0.75rem;
}
.gp-potret {
    position: relative;
    width: min(340px, 86vw, calc((100dvh - 300px) * 0.75));
    aspect-ratio: 3 / 4;
    border-radius: 20px;
    overflow: hidden;
    border: 2px solid rgba(16, 185, 129, 0.45);
    background: var(--hcis-slate-900);
    box-shadow: 0 24px 54px -30px rgba(15, 23, 42, 0.55);
}
.gp-potret img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gp-potret-tanda {
    position: absolute;
    left: 50%;
    bottom: 0.7rem;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-emerald-800);
    background: rgba(255, 255, 255, 0.94);
    border-radius: 999px;
    padding: 0.24rem 0.7rem;
}
.gp-potret-tanda i {
    color: var(--hcis-emerald-500);
}
.gp-tanya {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
}

.gp-lokasi-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
    margin-top: 0.35rem;
}
.gp-lokasi-pill-ico {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.78rem;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.12);
    flex-shrink: 0;
}
.gp-lokasi-pill-isi {
    display: flex;
    flex-direction: column;
    text-align: left;
}
.gp-lokasi-pill-label {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
}
.gp-lokasi-pill-val {
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}
.gp-lokasi-pill-val.idle {
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.gp-lokasi-pill-badge {
    margin-left: 0.3rem;
    font-size: 0.6rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.12rem 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.gp-lokasi-pill-badge.presisi {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.14);
    border: 1px solid var(--hcis-emerald-200);
}
.gp-lokasi-pill-badge.cukup {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.14);
    border: 1px solid var(--hcis-amber-200);
}

.gp-buka {
    position: relative;
    z-index: 2;
    width: 82px;
    height: 82px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 2rem;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    box-shadow: 0 20px 44px -18px rgba(16, 185, 129, 0.85);
    animation: gp-terbuka 0.6s cubic-bezier(0.2, 0.9, 0.25, 1.1);
}
@keyframes gp-terbuka {
    0% {
        transform: scale(0.4) rotate(-14deg);
        opacity: 0;
    }
    60% {
        transform: scale(1.14) rotate(5deg);
    }
    100% {
        transform: scale(1) rotate(0);
        opacity: 1;
    }
}

.gp-buka-wrap {
    position: relative;
    display: grid;
    place-items: center;
}

.gp-buka-riak {
    position: absolute;
    width: 82px;
    height: 82px;
    border-radius: 50%;
    border: 2px solid var(--hcis-emerald-500);
    animation: gp-riak 1.8s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
}
.gp-buka-riak.r2 {
    animation-delay: 0.5s;
}
@keyframes gp-riak {
    0% {
        transform: scale(0.9);
        opacity: 0.8;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

.gp-verifikasi-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 999px;
    padding: 0.3rem 0.85rem;
}

.gp-pengalihan-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.65rem;
    width: min(340px, 90vw);
    padding: 0.85rem 1.1rem;
    border-radius: 16px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 8px 24px -10px rgba(15, 23, 42, 0.08);
}

.gp-pengalihan-badge {
    display: flex;
    align-items: center;
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
}

.gp-panah-jalan {
    animation: gp-panah 0.8s ease-in-out infinite alternate;
}
@keyframes gp-panah {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(6px);
    }
}

.gp-pengalihan-bar-wrap {
    width: 100%;
    height: 4px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
}

.gp-pengalihan-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
    border-radius: 999px;
    width: 0%;
    animation: gp-isi-bar 1.1s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
@keyframes gp-isi-bar {
    to {
        width: 100%;
    }
}

.gp-galat {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.07);
    border: 1px solid rgba(239, 68, 68, 0.24);
    border-radius: 12px;
    padding: 0.6rem 0.85rem;
}

/* ══════════════ Responsif ══════════════ */
/* ══════════════ Tablet ══════════════ */
@media (max-width: 900px) {
    .gp-belah {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    /* Sampul jadi pita mendatar: gambar setinggi 320px di layar sempit
       mendorong tombolnya jauh ke bawah lipatan. */
    .gp-sampul {
        min-height: 0;
        aspect-ratio: 16 / 9;
    }
}

/* ══════════════ Ponsel ══════════════
   Prinsipnya proporsi, bukan sekadar mengecilkan: setiap angka di bawah ini
   menyesuaikan sesuatu yang memang berlebihan pada lebar 360-430px, dan
   sasaran sentuh justru DIPERBESAR karena jari bukan kursor. */
@media (max-width: 700px) {
    .gp {
        padding: 0.6rem;
    }
    .gp-shell {
        gap: 0.55rem;
    }
    .gp-kartu {
        --pad: 0.9rem;
        border-radius: 18px;
        gap: 0.9rem;
    }

    /* Remah 4 tingkat membungkus jadi dua baris di ponsel dan memakan tinggi
       tanpa memberi arah. Yang berguna hanya titik asal dan tujuan. */
    .gp-remah-tengah {
        display: none;
    }
    .gp-remah {
        font-size: 0.72rem;
    }
    .gp-remah strong {
        max-width: 20ch;
    }

    .gp-langkah {
        border-radius: 17px 17px 0 0;
        padding: 0.75rem 0.75rem 0.65rem;
        gap: 0;
        justify-content: space-between;
    }
    .gp-langkah li {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.35rem;
        position: relative;
        flex: 1;
    }
    .gp-langkah li:last-child {
        flex: 1;
    }
    .gp-langkah li:not(:last-child)::after {
        top: 15px;
        left: 50%;
        right: -50%;
        height: 2px;
        z-index: 0;
    }
    .gp-node {
        width: 30px;
        height: 30px;
        font-size: 0.75rem;
        z-index: 2;
        margin: 0 auto;
    }
    .gp-node-t {
        text-align: center;
    }
    .gp-node-t strong {
        font-size: 0.72rem;
        line-height: 1.1;
    }
    .gp-node-t em {
        display: none;
    }

    .gp-sampul {
        aspect-ratio: 2 / 1;
        border-radius: 14px;
    }
    .gp-gembok {
        width: 30px;
        height: 30px;
        font-size: 0.82rem;
    }
    .gp-sampul-bawah strong {
        font-size: 0.75rem;
    }
    .gp-sampul-bawah em {
        font-size: 0.66rem;
    }

    .gp-judul {
        font-size: 1.18rem;
        margin: 0.5rem 0 0.2rem;
    }
    .gp-otoritas {
        font-size: 0.71rem;
    }
    .gp-alert {
        margin-top: 0.75rem;
        padding: 0.6rem 0.7rem;
        font-size: 0.75rem;
    }
    .gp-blok {
        margin-top: 0.85rem;
    }
    .gp-cek {
        grid-template-columns: 1fr;
        gap: 0.6rem;
        margin-top: 0.85rem;
    }
    .gp-cek-kartu {
        padding: 0.75rem 0.8rem;
    }

    /* Sasaran sentuh: 0,16rem padding hanya menghasilkan tinggi ~22px, jauh di
       bawah ambang nyaman untuk jari. */
    .gp-izin li {
        gap: 0.55rem;
    }
    .gp-izin-minta {
        padding: 0.4rem 0.75rem;
        font-size: 0.7rem;
    }
    .gp-ulang {
        padding-top: 0.85rem;
        font-size: 0.78rem;
    }

    /* Tombol DITUMPUK, bukan berdampingan: "Mulai check-in presensi" pada
       separuh lebar 360px pasti pecah jadi dua baris dan tombolnya jadi tinggi
       tak beraturan. */
    .gp-dok {
        padding-top: 0.9rem;
    }
    .gp-dok-tombol {
        flex-direction: column;
        gap: 0.45rem;
    }
    .gp-dok-tombol .gp-btn {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.86rem;
    }

    .gp-fokus {
        gap: 0.7rem;
        padding: 0.25rem 0 0.5rem;
    }
    .gp-jujur ul {
        gap: 0.45rem;
    }
}

@media (max-width: 430px) {
    .gp-node {
        width: 28px;
        height: 28px;
        font-size: 0.72rem;
    }
    .gp-langkah li:not(:last-child)::after {
        top: 14px;
    }
    .gp-node-t strong {
        font-size: 0.68rem;
    }
    .gp-judul {
        font-size: 1.08rem;
    }
    .gp-komposisi li {
        font-size: 0.71rem;
        padding: 0.22rem 0.55rem;
    }
    .gp-tips strong {
        font-size: 0.75rem;
    }
    .gp-tips em {
        font-size: 0.67rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .gp-kartu,
    .gp-gembok,
    .gp-btn.utama.siap,
    .gp-buka,
    .gp-izin li.ok .gp-izin-ico {
        animation: none;
    }
}
</style>
