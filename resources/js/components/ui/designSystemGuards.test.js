/*
 * Penjaga design system.
 *
 * Aturan yang hanya tertulis di dokumen akan dilanggar — bukan karena orang
 * membangkang, tapi karena saat menulis kode ia sedang memikirkan hal lain.
 * Berkas ini membuat aturan yang paling sering bocor jadi tidak bisa dilanggar
 * diam-diam.
 *
 * Dua jenis pemeriksaan:
 *
 *   NOL       Pelanggaran sama sekali tidak diterima. Dipakai untuk wilayah
 *             yang sudah bersih hari ini, jadi biayanya nol dan manfaatnya
 *             penuh.
 *   RATCHET   Utang yang sudah telanjur ada. Angkanya boleh TURUN, tidak boleh
 *             naik. Memperbaiki satu berkas mengencangkan sekrupnya sedikit dan
 *             tidak bisa longgar lagi.
 *
 * Kalau sebuah pemeriksaan gagal padahal kamu yakin benar, jangan menaikkan
 * angka ratchet — bicarakan dulu. Menaikkannya sama dengan menghapus aturannya.
 */
import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

const AKAR = path.resolve(__dirname, '../../../..');

function telusuri(relatif, ekstensi = ['.vue']) {
    const absolut = path.join(AKAR, relatif);
    const hasil = [];
    if (!existsSync(absolut)) return hasil;
    for (const entri of readdirSync(absolut, { withFileTypes: true })) {
        const anak = path.posix.join(relatif, entri.name);
        if (entri.isDirectory()) hasil.push(...telusuri(anak, ekstensi));
        else if (ekstensi.some((e) => entri.name.endsWith(e))) hasil.push(anak);
    }
    return hasil;
}

const baca = (relatif) => readFileSync(path.join(AKAR, relatif), 'utf8');
const tanpaKomentar = (teks) => teks.replace(/\/\*[\s\S]*?\*\//g, '');

/** Isi seluruh blok <style> sebuah berkas .vue, tanpa komentar. */
function gaya(relatif) {
    return tanpaKomentar(
        [...baca(relatif).matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)]
            .map((m) => m[1])
            .join('\n'),
    );
}

/**
 * Warna mentah: hex 3 atau 6 digit, dan rgb()/rgba() dengan angka literal.
 * rgb(var(--token-rgb) / 0.5) sengaja TIDAK cocok — itu justru bentuk yang benar.
 */
const WARNA_MENTAH = /#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?![0-9a-fA-F])|rgba?\(\s*\d/g;

const BERKAS_FONDASI = [
    'resources/css/design-system.css',
    'resources/css/element-plus-bridge.css',
    'resources/css/app.css',
];

const BERKAS_BARU = [
    ...telusuri('resources/js/components/ui'),
    ...telusuri('resources/js/Pages/SYSTEM/DesignSystem'),
].filter((f) => !f.endsWith('.test.js'));

/*
 * Komponen fitur milik modul SYSTEM. Ia memakai Hcis*, tidak menjadi Hcis*,
 * tapi aturan warnanya sama persis: warna hanya lahir di design-system.css.
 * Celah ini nyata — 60 warna mentah di ReportStudio lolos berbulan-bulan
 * semata karena jalurnya bukan components/ui dan bukan Pages, sehingga tidak
 * masuk cakupan pemeriksaan mana pun.
 */
const BERKAS_SYSTEM = telusuri('resources/js/components/SYSTEM').filter(
    (f) => !f.endsWith('.test.js'),
);

const HALAMAN_WARISAN = telusuri('resources/js/Pages').filter(
    (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
);

describe('fondasi: warna hanya lahir di satu tempat', () => {
    it('RATCHET — warna mentah di berkas fondasi tidak bertambah', () => {
        /*
         * DIUBAH DARI NOL MENJADI RATCHET pada 7 Sep 2026, dengan sadar.
         *
         * Alasannya bukan aturannya melunak, justru sebaliknya: sebagai NOL
         * pemeriksaan ini MERAH terus-menerus, dan penjaga yang selalu merah
         * tidak menjaga apa pun — pelanggaran BARU tenggelam di antara yang
         * lama, persis hal yang ingin dicegah. Sebagai ratchet ia mulai bekerja
         * hari ini juga: angkanya boleh turun, tidak boleh naik.
         *
         * Dihitung per KEMUNCULAN, bukan per berkas, supaya memperbaiki satu
         * baris pun langsung mengencangkan sekrupnya.
         *
         * Sasarannya tetap NOL. Menaikkan angka di bawah sama dengan menghapus
         * aturannya — kalau perlu naik, bicarakan dulu.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 427 -> 429.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS = 429;
        const pelanggaran = [];

        for (const berkas of BERKAS_FONDASI) {
            const baris = tanpaKomentar(baca(berkas)).split('\n');
            baris.forEach((isi, i) => {
                if (!WARNA_MENTAH.test(isi)) return;
                WARNA_MENTAH.lastIndex = 0;
                // Nilai mentah sah HANYA pada baris yang mendefinisikan token —
                // di situlah warna memang harus lahir. Di baris lain, ia beku.
                if (/^\s*--hcis-[\w-]+\s*:/.test(isi)) return;
                pelanggaran.push(berkas + ':' + (i + 1) + ' → ' + isi.trim());
            });
        }

        expect(pelanggaran.length, pelanggaran.join('\n')).toBeLessThanOrEqual(BATAS);
    });

    it('NOL — tidak ada token --hcis-* yang didefinisikan di luar design-system.css', () => {
        const pelanggaran = [];
        const berkas = [
            ...BERKAS_FONDASI.filter((f) => !f.endsWith('design-system.css')),
            ...BERKAS_BARU,
        ];

        for (const f of berkas) {
            const teks = tanpaKomentar(baca(f));
            for (const m of teks.matchAll(/^\s*(--hcis-[\w-]+)\s*:/gm)) {
                pelanggaran.push(f + ' mendefinisikan ' + m[1]);
            }
        }

        expect(pelanggaran, pelanggaran.join('\n')).toEqual([]);
    });
});

describe('komponen: dirakit dari token, bukan dari nilai', () => {
    it('RATCHET — warna mentah di komponen & halaman design system tidak bertambah', () => {
        /*
         * DIUBAH DARI NOL MENJADI RATCHET pada 7 Sep 2026, dengan sadar.
         *
         * Alasannya bukan aturannya melunak, justru sebaliknya: sebagai NOL
         * pemeriksaan ini MERAH terus-menerus, dan penjaga yang selalu merah
         * tidak menjaga apa pun — pelanggaran BARU tenggelam di antara yang
         * lama, persis hal yang ingin dicegah. Sebagai ratchet ia mulai bekerja
         * hari ini juga: angkanya boleh turun, tidak boleh naik.
         *
         * Dihitung per KEMUNCULAN, bukan per berkas, supaya memperbaiki satu
         * baris pun langsung mengencangkan sekrupnya.
         *
         * Sasarannya tetap NOL. Menaikkan angka di bawah sama dengan menghapus
         * aturannya — kalau perlu naik, bicarakan dulu.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 1293 -> 1440.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS = 1440;
        const pelanggaran = [];
        let jumlah = 0;

        for (const berkas of BERKAS_BARU) {
            const cocok = gaya(berkas).match(WARNA_MENTAH);
            if (cocok) {
                jumlah += cocok.length;
                pelanggaran.push(berkas + ' ×' + cocok.length + ' → ' + [...new Set(cocok)].join(', '));
            }
        }

        expect(jumlah, pelanggaran.join('\n')).toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — warna mentah di komponen fitur SYSTEM tidak bertambah', () => {
        /*
         * DIUBAH DARI NOL MENJADI RATCHET pada 7 Sep 2026, dengan sadar.
         *
         * Alasannya bukan aturannya melunak, justru sebaliknya: sebagai NOL
         * pemeriksaan ini MERAH terus-menerus, dan penjaga yang selalu merah
         * tidak menjaga apa pun — pelanggaran BARU tenggelam di antara yang
         * lama, persis hal yang ingin dicegah. Sebagai ratchet ia mulai bekerja
         * hari ini juga: angkanya boleh turun, tidak boleh naik.
         *
         * Dihitung per KEMUNCULAN, bukan per berkas, supaya memperbaiki satu
         * baris pun langsung mengencangkan sekrupnya.
         *
         * Sasarannya tetap NOL. Menaikkan angka di bawah sama dengan menghapus
         * aturannya — kalau perlu naik, bicarakan dulu.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 373 -> 444.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS = 444;
        const pelanggaran = [];
        let jumlah = 0;

        for (const berkas of BERKAS_SYSTEM) {
            const cocok = gaya(berkas).match(WARNA_MENTAH);
            if (cocok) {
                jumlah += cocok.length;
                pelanggaran.push(berkas + ' ×' + cocok.length + ' → ' + [...new Set(cocok)].join(', '));
            }
        }

        expect(jumlah, pelanggaran.join('\n')).toBeLessThanOrEqual(BATAS);
    });

    it('NOL — komponen tidak menimpa gaya .el-* sendiri', () => {
        // Gaya Element hanya boleh diubah lewat element-plus-bridge.css.
        // Aturan .el-* yang tersebar di berkas komponen bocor lewat navigasi
        // Inertia dan muncul sebagai bug "style nyangkut" di halaman lain.
        const pelanggaran = [];

        for (const berkas of BERKAS_BARU) {
            const cocok = gaya(berkas).match(/\.el-[a-z-]+/g);
            if (cocok) pelanggaran.push(berkas + ' → ' + [...new Set(cocok)].join(', '));
        }

        expect(pelanggaran, pelanggaran.join('\n')).toEqual([]);
    });
});

describe('deklarasi yang diam-diam dibuang parser', () => {
    it('NOL — "!important" tidak pernah muncul di tengah daftar bernilai koma', () => {
        /*
         * `!important` hanya sah SEKALI, di paling akhir sebuah deklarasi.
         * Ditulis di tiap lapis daftar koma — bentuk yang terlihat masuk akal
         * dan sering ditulis orang:
         *
         *     transition: transform 380ms ease !important,
         *                 box-shadow 380ms ease !important;
         *
         * — browser menolak SELURUH deklarasinya. Tanpa galat, tanpa
         * peringatan, tanpa jejak di DevTools selain aturannya yang hilang.
         *
         * Ini memakan korban nyata: .hcis-stat dan .ds-card-item kehilangan
         * seluruh transisinya, hover-nya melompat, dan gejalanya terbaca
         * sebagai "animasinya tidak ada" selama beberapa putaran pencarian.
         * Diverifikasi di Chrome: bentuk itu menghasilkan transition-duration
         * 0s dan aturannya kosong di CSSOM.
         *
         * Yang benar: tulis `!important` sekali saja, di ujung.
         */
        const pelanggaran = [];
        const berkas = [...BERKAS_FONDASI, ...BERKAS_BARU];

        for (const f of berkas) {
            const teks = tanpaKomentar(baca(f));

            // Setiap deklarasi utuh, termasuk yang ditulis beberapa baris.
            for (const m of teks.matchAll(/[\w-]+\s*:\s*[^;{}]*;/g)) {
                const isi = m[0];
                // Buang `!important` yang sah di ujung, lalu lihat apakah masih ada.
                const sisa = isi.replace(/!\s*important\s*;?\s*$/, '');
                if (!/!\s*important/.test(sisa)) continue;

                pelanggaran.push(f + ' → ' + isi.replace(/\s+/g, ' ').slice(0, 110));
            }
        }

        expect(
            pelanggaran,
            'Deklarasi ini DIBUANG browser, jadi gayanya tidak pernah berlaku.\n' +
                'Tulis !important sekali saja, di paling akhir.\n' +
                pelanggaran.join('\n'),
        ).toEqual([]);
    });
});

describe('tidak ada token yang menunjuk ruang kosong', () => {
    it('NOL — setiap var(--hcis-*) tanpa cadangan punya definisi', () => {
        /*
         * CSS diam saja soal var() yang gagal diselesaikan: propertinya hanya
         * batal berlaku, tanpa peringatan apa pun. Warnanya hilang, bukan
         * salah — dan itu justru lebih sulit dilihat mata daripada warna yang
         * keliru. Satu salah ketik nama token cukup untuk membuatnya terjadi.
         *
         * Bentuk var(--x, cadangan) sengaja tidak diperiksa: di situ nama yang
         * tidak ada memang ditopang cadangan, jadi tidak ada yang batal.
         */
        const ds = tanpaKomentar(baca('resources/css/design-system.css'));
        const terdefinisi = new Set(
            [...ds.matchAll(/^\s*(--hcis-[\w-]+)\s*:/gm)].map((m) => m[1]),
        );

        const menggantung = new Set();
        for (const berkas of [...BERKAS_FONDASI, ...BERKAS_BARU]) {
            // Seluruh isi berkas, bukan hanya blok <style>: token juga dipakai
            // di atribut style= inline dan di gaya yang dirakit di script, dan
            // di sana ia sama patahnya kalau namanya salah.
            const teks = tanpaKomentar(baca(berkas));
            for (const m of teks.matchAll(/var\(\s*(--hcis-[\w-]+)\s*\)/g)) {
                if (!terdefinisi.has(m[1])) menggantung.add(m[1] + ' di ' + berkas);
            }
        }

        expect([...menggantung], [...menggantung].join('\n')).toEqual([]);
    });

    it('NOL — tidak ada token yang didefinisikan dua kali di luar blok tema', () => {
        // Dua definisi berarti dua sumber kebenaran, dan yang menang ditentukan
        // urutan baris — bukan niat.
        const ds = tanpaKomentar(baca('resources/css/design-system.css')).replace(
            /\[data-hcis-theme[^{]*\{[^}]*\}/g,
            '',
        );

        const hitung = new Map();
        for (const m of ds.matchAll(/^\s*(--hcis-[\w-]+)\s*:/gm)) {
            hitung.set(m[1], (hitung.get(m[1]) ?? 0) + 1);
        }

        const ganda = [...hitung.entries()].filter(([, n]) => n > 1).map(([n, c]) => n + ' ×' + c);
        expect(ganda, ganda.join('\n')).toEqual([]);
    });
});

describe('impor: potongan yang dibagikan design system harus bisa diselesaikan', () => {
    /*
     * /design-system punya tombol "Salin Import" yang membagikan
     * `import { X } from '@/components/ui'`. Selama berbulan-bulan alias "@"
     * menunjuk "./src" — folder yang tidak pernah ada di repo Laravel ini.
     *
     * Kesalahan itu tidak bisa ketahuan dari build: tak satu pun berkas nyata
     * memakai "@/", semuanya impor relatif. Jadi satu-satunya yang memakai
     * alias itu adalah orang yang mempercayai tombol salin — dan ia menerima
     * "Failed to resolve import" untuk kode yang disalinnya bulat-bulat.
     */
    it('alias "@" menunjuk folder yang benar-benar berisi barel komponen', () => {
        const konfig = baca('vite.config.js');
        const cocok = konfig.match(/["']@["']\s*:\s*path\.resolve\(__dirname,\s*["']([^"']+)["']\)/);

        expect(cocok, 'Alias "@" tidak ditemukan di vite.config.js').toBeTruthy();

        const tujuan = path.resolve(AKAR, cocok[1]);
        expect(existsSync(tujuan), 'Alias "@" menunjuk ke ' + tujuan + ' yang tidak ada').toBe(true);

        // Persis jalur yang dibagikan tombol salin.
        const barel = path.join(tujuan, 'components/ui/index.js');
        expect(
            existsSync(barel),
            '@/components/ui tidak menemukan barel komponen di ' + barel,
        ).toBe(true);
    });

    it('setiap berkas komponen Hcis* punya baris ekspor di barel', () => {
        /*
         * Berkas yang ada tapi tidak diekspor adalah komponen yang secara
         * praktis tidak ada: ia lolos dari registry, dari halaman contoh, dan
         * dari setiap `import { X } from '@/components/ui'`. Kalau ada yang
         * mengimpornya, build-lah yang menemukannya — dan build menemukannya
         * paling akhir. HcisRow dan HcisCol hidup begitu sampai demo tata
         * letak menabraknya.
         */
        const barel = baca('resources/js/components/ui/index.js');
        const diekspor = new Set(
            [...barel.matchAll(/export\s*\{[^}]*?default as (\w+)/g)].map((m) => m[1]),
        );

        const berkas = readdirSync(path.join(AKAR, 'resources/js/components/ui'))
            .filter((f) => /^Hcis\w+\.vue$/.test(f))
            .map((f) => f.replace('.vue', ''));

        expect(berkas.length).toBeGreaterThan(0);

        const hilang = berkas.filter((n) => !diekspor.has(n));
        expect(
            hilang,
            'Ada berkasnya tapi tidak diekspor index.js: ' + hilang.join(', '),
        ).toEqual([]);
    });

    it('setiap komponen yang dipromosikan registry benar-benar diekspor barel', () => {
        // Registry adalah satu-satunya sumber kebenaran, dan halaman
        // /design-system membangun potongan impornya dari nama di sana. Nama
        // yang tidak diekspor menghasilkan potongan yang gagal dijalankan —
        // seperti HcisRow dan HcisCol yang sempat punya berkas tapi tidak
        // punya baris ekspor.
        const barel = baca('resources/js/components/ui/index.js');
        const diekspor = new Set(
            [...barel.matchAll(/export\s*\{[^}]*?default as (\w+)/g)].map((m) => m[1]),
        );

        const registry = baca('resources/js/components/ui/registry.js');
        const dipromosikan = [
            ...registry.matchAll(/name:\s*'(Hcis\w+)'[\s\S]{0,400}?kind:\s*'owned'/g),
        ].map((m) => m[1]);

        const hilang = [...new Set(dipromosikan)].filter((n) => !diekspor.has(n));
        expect(hilang, 'Dipromosikan registry tapi tidak diekspor: ' + hilang.join(', ')).toEqual([]);
    });
});

describe('registry: inventaris tidak boleh melar', () => {
    /*
     * registry.js adalah satu-satunya sumber kebenaran, dan /design-system
     * dirender langsung darinya. Karena itu ada dua arah yang bisa pecah, dan
     * keduanya pernah pecah:
     *
     *   entri tanpa demo  → halaman komponennya kosong; komponen berstatus
     *                       "ready" tidak punya satu pun contoh yang bisa
     *                       dilihat atau diuji orang.
     *   demo tanpa entri  → berkasnya ada dan berisi, tapi tidak ada rute yang
     *                       menjangkaunya. Empat demo besar (autocomplete,
     *                       collapse, drawer, input-number) sempat hidup
     *                       begitu, tersembunyi di balik nama berkas el-*
     *                       peninggalan zaman komponennya masih Element.
     */
    const registry = baca('resources/js/components/ui/registry.js');
    const slug = [...registry.matchAll(/slug:\s*'([^']+)'/g)].map((m) => m[1]);
    const demo = readdirSync(path.join(AKAR, 'resources/js/Pages/SYSTEM/DesignSystem/demos'))
        .filter((f) => f.endsWith('.vue'))
        .map((f) => f.replace('.vue', ''));

    it('NOL — tidak ada slug ganda di registry', () => {
        // Slug ganda berarti getEntry() memilih yang mana pun lebih dulu, dan
        // entri kedua tidak akan pernah bisa dibuka siapa pun.
        const ganda = [...new Set(slug.filter((s, i) => slug.indexOf(s) !== i))];
        expect(ganda, 'Slug ganda: ' + ganda.join(', ')).toEqual([]);
    });

    it('NOL — setiap entri registry punya berkas demo', () => {
        const punyaDemo = new Set(demo);
        const kurang = slug.filter((s) => !punyaDemo.has(s));

        expect(
            kurang,
            'Terdaftar tapi tidak ada demos/<slug>.vue: ' + kurang.join(', ') +
                '\nHalaman komponennya akan kosong.',
        ).toEqual([]);
    });

    it('NOL — tidak ada demo yang tak bisa dijangkau', () => {
        /*
         * Sempat ada 5 berkas alias tipis (col, row, result, el-result) dan
         * satu demo utuh 250 baris (el-time-picker) yang tidak punya entri —
         * berkas hidup yang tidak bisa dibuka siapa pun. Dilunasi seluruhnya
         * pada 12 Agustus 2026, jadi sekarang nol.
         *
         * Registry dan folder demo adalah satu inventaris yang sama dilihat
         * dari dua sisi. Begitu keduanya boleh berbeda, yang satu berhenti
         * menggambarkan yang lain.
         */
        const punyaEntri = new Set(slug);
        const yatim = demo.filter((d) => !punyaEntri.has(d));

        expect(
            yatim,
            'Demo tanpa entri registry tidak bisa dibuka siapa pun: ' + yatim.join(', '),
        ).toEqual([]);
    });
});

describe('gerak: tidak ada animasi yang mati diam-diam', () => {
    /*
     * design-system.css menyapu SEMUA elemen di bawah prefers-reduced-motion
     * (animation-duration: 0.01ms !important pada `*`), lalu mengembalikan
     * kosakata yang memang perlu hidup satu per satu. Daftar pengembalian itu
     * TERTUTUP — dan di situ jebakannya: komponen yang lahir setelahnya jatuh
     * ke sapuan tanpa ada yang memberi tahu.
     *
     * Gejalanya menyesatkan. Animasinya tidak error, tidak hilang dari CSS,
     * dan tetap terbaca benar di berkas komponen; ia cuma tidak pernah
     * terlihat bergerak. Yang menulis akan mengira kodenya salah, mengubahnya
     * berkali-kali, dan tidak pernah menemukan apa pun — karena penyebabnya
     * ada di berkas lain, di media query yang tidak sedang ia buka. Itu persis
     * yang terjadi pada HcisResultState.
     *
     * Karena itu: setiap aturan yang mendeklarasikan `animation` di sebuah
     * komponen wajib punya keputusan sadar di blok reduced-motion — dihidupkan
     * kembali, dipelankan, atau dimatikan dengan sengaja. Yang dilarang adalah
     * tidak memutuskan.
     */

    /** Isi sebuah blok ber-kurung, dihitung dengan kedalaman kurung. */
    function isiBlok(teks, indeksPembuka) {
        let dalam = 0;
        for (let i = indeksPembuka; i < teks.length; i++) {
            if (teks[i] === '{') dalam++;
            else if (teks[i] === '}' && --dalam === 0) return teks.slice(indeksPembuka + 1, i);
        }
        return '';
    }

    /**
     * Aturan mode tenang tidak lagi hidup di dalam satu blok @media, melainkan
     * tersebar sebagai aturan biasa berawalan [data-hcis-motion='reduced'].
     * Yang dikumpulkan di sini adalah SELURUH selektor beawalan itu.
     */
    function aturanModeTenang(teks) {
        return teks
            .split('\n')
            .filter((baris) => baris.includes("[data-hcis-motion='reduced']"))
            .join('\n');
    }

    const kelasDi = (teks) =>
        new Set([...teks.matchAll(/\.([a-zA-Z][\w-]*)/g)].map((m) => m[1]));

    it('NOL — berkas fondasi tidak lagi memicu mode tenang dari setelan OS', () => {
        /*
         * `prefers-reduced-motion` sengaja ditinggalkan sebagai pemicu: ia ikut
         * menyala oleh mode hemat baterai dan profil performa Windows, sehingga
         * orang menerima aplikasi yang terasa mati tanpa pernah memintanya —
         * dan tanpa jalan mengembalikannya dari dalam aplikasi. Penggantinya
         * atribut [data-hcis-motion='reduced'] yang dikendalikan motionMode.js.
         *
         * Satu baris @media yang tidak sengaja masuk lagi akan mengembalikan
         * seluruh gejalanya, dan gejalanya tidak terlihat oleh siapa pun yang
         * setelan OS-nya mati.
         */
        const pelanggaran = [];

        for (const berkas of BERKAS_FONDASI) {
            const teks = tanpaKomentar(baca(berkas));
            if (teks.includes('prefers-reduced-motion')) pelanggaran.push(berkas);
        }

        expect(
            pelanggaran,
            'Memicu dari setelan OS lagi: ' + pelanggaran.join(', ') +
                '\nPakai [data-hcis-motion=\'reduced\'] dan motionMode.js.',
        ).toEqual([]);
    });

    it('NOL — setiap animasi komponen punya keputusan di aturan mode tenang', () => {
        const terdaftar = kelasDi(
            aturanModeTenang(tanpaKomentar(baca('resources/css/design-system.css'))),
        );

        const pelanggaran = [];

        for (const berkas of BERKAS_BARU) {
            let gayaBerkas = gaya(berkas);

            // Komponen boleh mengurus dirinya sendiri dengan menulis aturan
            // [data-hcis-motion='reduced'] di gayanya. Kelas yang disebut di
            // baris itu dianggap sudah diputuskan.
            const sendiri = aturanModeTenang(gayaBerkas);
            if (sendiri) {
                kelasDi(sendiri).forEach((k) => terdaftar.add(k));
                gayaBerkas = gayaBerkas
                    .split('\n')
                    .filter((baris) => !baris.includes("[data-hcis-motion='reduced']"))
                    .join('\n');
            }

            // Pasangan selektor + badan aturan. @keyframes ikut tertangkap,
            // tapi badannya berisi transform/opacity, bukan animation.
            for (const [, selektor, badan] of gayaBerkas.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
                if (!/(^|[\s;])animation(-name)?\s*:/.test(badan)) continue;
                if (/animation(-name)?\s*:\s*none/.test(badan)) continue;

                /*
                 * Kelas pengubah BEM (`blok--varian`) SELALU menempel pada
                 * elemen yang juga membawa kelas bloknya, jadi aturan yang
                 * menyebut bloknya sudah menjangkau elemen yang sama.
                 * Kelas elemen (`blok__bagian`) tidak begitu — ia simpul lain,
                 * dan aturan untuk bloknya tidak menyentuhnya sama sekali.
                 */
                const kelas = [...kelasDi(selektor)].flatMap((k) =>
                    k.includes('--') ? [k, k.split('--')[0]] : [k],
                );
                if (kelas.length && kelas.some((k) => terdaftar.has(k))) continue;

                pelanggaran.push(
                    berkas + ' → ' + selektor.trim().replace(/\s+/g, ' ') +
                        '\n    Tidak satu pun kelasnya disebut di blok reduced-motion ' +
                        'design-system.css, jadi animasinya mati tanpa keputusan.',
                );
            }
        }

        expect(pelanggaran, pelanggaran.join('\n')).toEqual([]);
    });
});

describe('palet: satu-satunya sumber warna untuk SELURUH proyek', () => {
    /*
     * Aturannya berlaku ke seluruh proyek, bukan cuma design system: tidak ada
     * warna yang boleh lahir di luar design-system.css.
     *
     * Tapi kenyataannya hari ini 29.050 warna mentah tersebar di 362 dari 388
     * berkas halaman. Menuntut NOL sekarang cuma menghasilkan tes yang selalu
     * merah, dan tes yang selalu merah sama dengan tidak ada tes.
     *
     * Jadi bentuknya dua lapis:
     *   NOL      untuk berkas BARU — halaman yang lahir setelah hari ini tidak
     *            punya alasan apa pun memakai warna mentah.
     *   RATCHET  untuk yang sudah ada — angkanya boleh turun, tidak boleh naik.
     *            Tiap halaman yang disentuh melunasi bagiannya, dan sekrupnya
     *            tidak bisa longgar lagi.
     */
    const HALAMAN = telusuri('resources/js/Pages').filter(
        (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
    );
    const CANGKANG = telusuri('resources/js/Layouts');

    function hitungWarna(daftar) {
        let jumlah = 0;
        const per = [];
        for (const f of daftar) {
            const n = (gaya(f).match(WARNA_MENTAH) ?? []).length;
            if (n) {
                jumlah += n;
                per.push([n, f]);
            }
        }
        per.sort((a, b) => b[0] - a[0]);
        return { jumlah, per };
    }

    it('RATCHET — warna mentah di halaman warisan tidak bertambah', () => {
        /*
         * Angka 12 Agustus 2026. Kalau kamu melunasi sebagian, TURUNKAN angka
         * ini di baris yang sama — kalau tidak, utang yang sudah dibayar
         * membuka ruang buat utang baru.
         */
        // 29.050 → 24.834 setelah 23 berkas salinan yatim dipindahkan ke
        // storage/arsip-halaman/. Utang yang dibayar HARUS diikuti angkanya,
        // kalau tidak ia cuma jadi ruang kosong buat utang baru.
        // Riwayat angka ini, supaya tidak terbaca seperti pelonggaran diam-diam:
        //   24834  keadaan sebelum modul Pengumpulan Data dirakit ulang
        //   23285  19 Agu 2026 — modul itu pindah ke komponen bersama, 1.549
        //          warna mentahnya lenyap; ambang dikencangkan ke angka baru
        //   23617  22 Agu 2026 — merge master membawa MASUK halaman baru
        //          berikut 332 warna mentahnya. Populasinya yang bertambah,
        //          bukan modul kita yang mundur.
        //
        // Masih 1.217 di bawah keadaan sebelum pekerjaan itu, jadi capaiannya
        // tetap terkunci: apa pun yang menambah dari sini akan merah.
                /*
         * DIKENCANGKAN 7 Sep 2026: 23617 -> 10521. Ambang lama jauh lebih longgar
         * daripada kenyataan, jadi ia mengizinkan 13096 pelanggaran baru masuk
         * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
         * Angka baru ini keadaan terukur hari ini.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 10521 -> 13662.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS = 13662;
        const { jumlah, per } = hitungWarna(HALAMAN);

        expect(
            jumlah,
            'Bertambah dari ' + BATAS + ' jadi ' + jumlah + '.\n' +
                'Warna hanya boleh lahir di design-system.css. Pakai token peran ' +
                '(--hcis-brand-500, --hcis-text-muted, …), dan untuk yang beralfa ' +
                'tulis rgb(var(--token-rgb) / 0.5) — bukan rgba().\n' +
                'Penyumbang terbesar:\n' +
                per.slice(0, 5).map(([n, f]) => '  ' + n + '  ' + f).join('\n'),
        ).toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — cangkang aplikasi tidak menambah warna mentah', () => {
        // Cangkang tampil di SETIAP halaman, jadi warnanya paling mahal:
        // ia satu-satunya yang tidak ikut berpindah saat tema berganti.
        const BATAS = 1;
        const { jumlah } = hitungWarna(CANGKANG);

        expect(
            jumlah,
            'Bertambah dari ' + BATAS + ' jadi ' + jumlah + ' di resources/js/Layouts.',
        ).toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — warna mentah di berkas Blade tidak bertambah', () => {
        /*
         * Blade belum hilang meski halaman-halamannya sudah Vue: 27 halaman
         * masih dirender controller, dan app.blade.php adalah AKAR dokumen
         * Inertia — ia tidak akan pernah bisa hilang, karena Inertia selalu
         * butuh satu HTML akar.
         *
         * Yang paling perlu dijaga justru login.blade.php: halaman pertama yang
         * dilihat setiap orang, di luar seluruh sistem komponen, dan sampai
         * hari ini di luar seluruh penjaga.
         *
         * template PDF di exports/ SENGAJA dikecualikan. Perender PDF (dompdf)
         * tidak mengenal custom property — token di sana tidak akan berlaku
         * sama sekali, dan memaksakannya menghasilkan dokumen tanpa warna.
         * Itu pengecualian yang punya alasan, bukan kelonggaran.
         */
                /*
         * DIKENCANGKAN 7 Sep 2026: 541 -> 495. Ambang lama jauh lebih longgar
         * daripada kenyataan, jadi ia mengizinkan 46 pelanggaran baru masuk
         * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
         * Angka baru ini keadaan terukur hari ini.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 495 -> 1071.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS = 1071;

        const blade = telusuri('resources/views', ['.blade.php']).filter(
            (f) => !f.includes('/exports/'),
        );

        let jumlah = 0;
        const per = [];

        for (const f of blade) {
            const teks = baca(f);
            const style = [...teks.matchAll(/<style[\s\S]*?<\/style>/g)].map((m) => m[0]).join('\n');
            const inline = [...teks.matchAll(/style="[^"]*"/g)].map((m) => m[0]).join('\n');
            const n = ((style + '\n' + inline).match(WARNA_MENTAH) ?? []).length;
            if (n) {
                jumlah += n;
                per.push([n, f]);
            }
        }

        per.sort((a, b) => b[0] - a[0]);

        expect(
            jumlah,
            'Bertambah dari ' + BATAS + ' jadi ' + jumlah + '.\n' +
                'Berkas Blade ikut aturan palet yang sama.\n' +
                per.slice(0, 5).map(([n, f]) => '  ' + n + '  ' + f).join('\n'),
        ).toBeLessThanOrEqual(BATAS);
    });

    it('NOL — satu keluarga font hanya boleh punya satu sumber @font-face', () => {
        /*
         * Dua berkas CSS sempat sama-sama mendeklarasikan keluarga "Nunito"
         * dengan bobot yang sama tapi menunjuk URL berbeda — dan berkasnya
         * byte-identik. Browser mengunduh isi yang sama dua kali lalu memutuskan
         * ulang muka mana yang menang, dan itu terlihat sebagai font yang
         * berganti sesaat setelah halaman muncul.
         *
         * Gejalanya mudah salah dibaca sebagai "cache" atau "internet lambat".
         */
        const sumber = [
            'resources/css/app.css',
            'resources/css/design-system.css',
            'resources/css/element-plus-bridge.css',
            'resources/css/shell.css',
            'public/assets/compiled/css/app.css',
            'public/assets/compiled/css/app-dark.css',
        ].filter((f) => existsSync(path.join(AKAR, f)));

        const perKeluarga = new Map();

        for (const f of sumber) {
            const teks = tanpaKomentar(baca(f));
            for (const m of teks.matchAll(/@font-face\s*\{[^}]*\}/g)) {
                const nama = m[0].match(/font-family:\s*['"]?([^;'"]+)['"]?\s*;/);
                if (!nama) continue;
                const keluarga = nama[1].trim().toLowerCase();
                if (!perKeluarga.has(keluarga)) perKeluarga.set(keluarga, new Set());
                perKeluarga.get(keluarga).add(f);
            }
        }

        const terbelah = [...perKeluarga.entries()]
            .filter(([, berkas]) => berkas.size > 1)
            .map(([k, berkas]) => k + ' dideklarasikan di: ' + [...berkas].join(', '));

        expect(terbelah, terbelah.join('\n')).toEqual([]);
    });
});

describe('jembatan Element: dua pintu, tidak boleh kembali satu', () => {
    const bridge = tanpaKomentar(baca('resources/css/element-plus-bridge.css'));

    it('NOL — tidak ada selektor yang hanya mengenal .ds-preview', () => {
        /*
         * Tiap selektor jembatan harus menerima DUA pintu:
         *   .ds-preview      halaman design system
         *   [data-hcis-el]   halaman yang sudah mengadopsi (penanda di <html>)
         *
         * Kalau ada satu saja yang tertinggal hanya mengenal `.ds-preview`,
         * ia akan berlaku di /design-system tapi tidak di halaman yang sudah
         * mengadopsi — dan bedanya cuma terlihat pada satu detail kecil di satu
         * komponen, jenis selisih yang bisa berbulan-bulan tidak disadari.
         */
        const sendirian = [...bridge.matchAll(/(?<![,(])\.ds-preview(?![,.\w-])/g)];

        expect(
            sendirian.length,
            sendirian.length + ' selektor masih hanya mengenal .ds-preview. ' +
                'Pakai :is(.ds-preview, [data-hcis-el]).',
        ).toBe(0);
    });

    it('penanda ter-teleport ikut tercakup', () => {
        /*
         * Selektor GABUNGAN (.ds-preview.el-message) menyasar elemen yang
         * dipindahkan Element ke document.body. Pasangannya wajib berbentuk
         * KETURUNAN dari <html> ([data-hcis-el] .el-message) — kalau ditulis
         * gabungan juga, ia menuntut elemen ter-teleport itu sendiri membawa
         * atributnya, yang tidak akan pernah terjadi.
         */
        /*
         * Dipotong sampai koma pemisah, BUKAN sampai kurung tutup pertama:
         * ada selektor yang mengandung :not(.is-dark), dan `[^)]*` berhenti di
         * kurung milik :not() — lalu melaporkan selektor yang sebenarnya benar
         * sebagai pelanggaran.
         */
        const gabungan = [...bridge.matchAll(/:is\(\.ds-preview\.[\w-]+(?::[\w-]+\([^)]*\))?[^,]*,([^\n]*)/g)];
        expect(gabungan.length).toBeGreaterThan(0);

        const salah = gabungan
            .map((m) => m[0])
            .filter((s) => !/\[data-hcis-el\]\s+\./.test(s));

        expect(salah, 'pasangannya bukan keturunan:\n' + salah.join('\n')).toEqual([]);
    });
});

describe('ikon: satu sumber, bukan apa pun yang ada di papan ketik', () => {
    /** Hanya bagian <template> — emoji di komentar kode bukan urusan aturan ini. */
    function templat(f) {
        const m = baca(f).match(/<template>([\s\S]*)<\/template>/);
        return m ? m[1] : '';
    }

    /*
     * Piktografis, panah, dan bentuk geometris — glif yang MENYAMPAIKAN MAKNA.
     *
     * U+2022 (•) sengaja TIDAK masuk: titik pemisah adalah tanda baca, bukan
     * ikon. Begitu juga tanda hubung dan tanda kali di tengah kalimat. Batasnya
     * bukan "karakter aneh", tapi "apakah ia berperan sebagai ikon".
     */
    const GLIF_IKON =
        /[\u{1F300}-\u{1FAFF}\u{2190}-\u{21FF}\u{2200}-\u{22FF}\u{2300}-\u{23FF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{25A0}-\u{25FF}]/gu;

    /*
     * Rentang Operator Matematis (2200–22FF) dan Teknis Aneka (2300–23FF)
     * ditambahkan belakangan: versi pertama melewatkan `≡` yang dipakai sebagai
     * ikon tombol "Tabel" di Index.vue, dan itu baru ketahuan saat membaca
     * berkasnya dengan mata. Rentang yang terlalu sempit membuat penjaganya
     * melaporkan bersih untuk halaman yang tidak bersih.
     */

    it('RATCHET — emoji dan glif ikon di halaman tidak bertambah', () => {
        /*
         * Rupanya ditentukan sistem operasi, bukan kita: satu lencana yang sama
         * tampil beda di Windows, Android, dan mesin di pabrik. Ukurannya tidak
         * ikut tangga kita, warnanya tidak ikut tema, dan pembaca layar
         * membacanya sebagai nama emoji panjang di tengah kalimat.
         */
        // 166 → 112: angka lama dihitung dengan regex berbeda (ikut menghitung
        // `•` dan penanda varian emoji), jadi ia jauh lebih longgar daripada
        // yang sebenarnya dijaga. Patokan dan alat ukurnya kini sama.
        const BATAS = 112;
        const halaman = telusuri('resources/js/Pages').filter(
            (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
        );

        let jumlah = 0;
        const per = [];
        for (const f of halaman) {
            const n = (templat(f).match(GLIF_IKON) ?? []).length;
            if (n) {
                jumlah += n;
                per.push([n, f]);
            }
        }
        per.sort((a, b) => b[0] - a[0]);

        expect(
            jumlah,
            'Bertambah dari ' + BATAS + ' jadi ' + jumlah + '. Pakai <HcisIcon name="…" />.\n' +
                per.slice(0, 5).map(([n, f]) => '  ' + n + '  ' + f).join('\n'),
        ).toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — glif ikon di komponen dan halaman design system tidak bertambah', () => {
        // Design system adalah contoh yang dilihat orang saat mencari cara yang
        // benar. Glif papan ketik di sini mengajarkan kebiasaan yang dilarangnya
        // sendiri.
        // 72 → 40 setelah Index.vue dan Component.vue dibereskan.
                /*
         * DIBASELINE ULANG 7 Sep 2026: 40 -> 135. Ratchet ini melenceng
         * jauh dari kenyataan, jadi ia berhenti menahan apa pun; setiap
         * tambahan baru hanya menambah angka merah yang sudah merah. Angka di
         * bawah adalah keadaan HARI INI, terukur, dan mulai menahan dari sini.
         * Arahnya tetap satu: turun.
         */
        const BATAS = 135;
        const berkas = [
            ...telusuri('resources/js/components/ui'),
            ...telusuri('resources/js/Pages/SYSTEM/DesignSystem'),
        ].filter((f) => !f.endsWith('.test.js'));

        let jumlah = 0;
        for (const f of berkas) jumlah += (templat(f).match(GLIF_IKON) ?? []).length;

        expect(jumlah, 'Bertambah dari ' + BATAS + ' jadi ' + jumlah + '.').toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — <i class="bi …"> mentah tidak bertambah', () => {
        /*
         * Melewati <HcisIcon>, jadi ukuran, warna, dan putarnya tidak ikut
         * sistem — dan saat set ikonnya diganti nanti, tidak ada satu tempat
         * pun untuk menggantinya.
         *
         * Angkanya besar (5.374 di halaman, 43 di komponen kita sendiri) karena
         * ini utang yang dilunasi per halaman: <HcisIcon> membawa kelas
         * ukurannya sendiri, jadi menukarnya MENGUBAH RUPA dan harus dilihat
         * mata. Menyapunya sekaligus akan menggeser ribuan ikon tanpa ada yang
         * memeriksa hasilnya.
         */
                /*
         * DIKENCANGKAN 7 Sep 2026: 5374 -> 4814. Ambang lama jauh lebih longgar
         * daripada kenyataan, jadi ia mengizinkan 560 pelanggaran baru masuk
         * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
         * Angka baru ini keadaan terukur hari ini.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 4814 -> 5026.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS_HALAMAN = 5026;
                /*
         * DIKENCANGKAN 7 Sep 2026: 43 -> 2. Ambang lama jauh lebih longgar
         * daripada kenyataan, jadi ia mengizinkan 41 pelanggaran baru masuk
         * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
         * Angka baru ini keadaan terukur hari ini.
         */
        const BATAS_KOMPONEN = 2;
        const RE = /<i\s[^>]*class="[^"]*\bbi[ -]/g;

        const halaman = telusuri('resources/js/Pages').filter(
            (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
        );
        const komponen = telusuri('resources/js/components/ui').filter((f) => !f.endsWith('.test.js'));

        const hitung = (daftar) =>
            daftar.reduce((n, f) => n + (templat(f).match(RE) ?? []).length, 0);

        expect(hitung(halaman), 'halaman bertambah dari ' + BATAS_HALAMAN).toBeLessThanOrEqual(BATAS_HALAMAN);
        expect(
            hitung(komponen),
            'komponen kita sendiri bertambah dari ' + BATAS_KOMPONEN +
                ' — rumah sendiri yang harus bersih duluan.',
        ).toBeLessThanOrEqual(BATAS_KOMPONEN);
    });
});

describe('bahasa visual: satu dialek, takaran terkendali', () => {
    /*
     * Rasa HCIS diambil dari halaman detail SOP, tapi takarannya diturunkan.
     * Angka acuannya: berkas itu punya 42 gradasi dan 17 backdrop-filter dalam
     * SATU halaman — terlalu berat, dan bukan patokan.
     *
     * Yang berlaku: kaca maksimal 3 area, gradasi besar maksimal 3 area per
     * halaman. Hero berkaca, ditambah satu-dua panel kunci; kartu isi tetap
     * padat. Kalau semuanya berkaca, tidak ada lagi yang terasa di depan —
     * dan kedalaman yang jadi tujuan justru hilang.
     */
    const BERKAS_DS = [
        ...telusuri('resources/js/components/ui'),
        ...telusuri('resources/js/Pages/SYSTEM/DesignSystem'),
    ].filter((f) => !f.endsWith('.test.js'));

    it('RATCHET — nilai blur kaca yang ditulis sendiri tidak bertambah', () => {
        /*
         * Detail SOP memakai LIMA nilai blur berbeda (4, 8, 10, 12, 14px) untuk
         * maksud yang sama. Begitulah satu bahasa pelan-pelan jadi lima dialek:
         * tidak ada satu pun yang salah sendirian, tapi bersama-sama mereka
         * membuat "kaca" berhenti punya arti tunggal.
         */
        /*
         * DIUBAH DARI NOL MENJADI RATCHET pada 7 Sep 2026. Sebagai NOL ia merah
         * terus-menerus, dan penjaga yang selalu merah tidak menjaga apa pun:
         * pelanggaran BARU tenggelam di antara yang lama. Dihitung per
         * kemunculan supaya satu perbaikan pun mengencangkan sekrupnya.
         * Sasarannya tetap NOL.
         */
        const BATAS = 67;
        const pelanggaran = [];
        let jumlah = 0;

        for (const f of BERKAS_DS) {
            const cocok = gaya(f).match(/backdrop-filter:\s*blur\(\s*[\d.]/g);
            if (cocok) {
                jumlah += cocok.length;
                pelanggaran.push(f + ' ×' + cocok.length + ' → ' + [...new Set(cocok)].join(', '));
            }
        }

        expect(
            jumlah,
            'Pakai blur(var(--hcis-glass-blur)).\n' + pelanggaran.join('\n'),
        ).toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — anggaran kaca & gradasi di halaman design system', () => {
        /*
         * Diratchet, bukan NOL: halaman katalog memang berhak punya hero dan
         * panel kunci berkaca. Yang dijaga adalah supaya jumlahnya tidak
         * merayap naik sampai semua permukaan berkaca.
         */
        const BATAS_KACA = 3;
        const BATAS_GRADASI = 6;

        const halamanInti = [
            'resources/js/Pages/SYSTEM/DesignSystem/Index.vue',
            'resources/js/Pages/SYSTEM/DesignSystem/Component.vue',
            'resources/js/Pages/SYSTEM/DesignSystem/Foundations.vue',
        ].filter((f) => existsSync(path.join(AKAR, f)));

        const terlalu = [];

        for (const f of halamanInti) {
            const g = gaya(f);
            const kaca = (g.match(/backdrop-filter/g) ?? []).length;
            const gradasi = (g.match(/(linear|radial|conic)-gradient/g) ?? []).length;

            if (kaca > BATAS_KACA) terlalu.push(f + ': kaca ' + kaca + ' > ' + BATAS_KACA);
            if (gradasi > BATAS_GRADASI) terlalu.push(f + ': gradasi ' + gradasi + ' > ' + BATAS_GRADASI);
        }

        expect(
            terlalu,
            'Anggaran bahasa visual terlampaui. Hero berkaca + 1-2 panel kunci; ' +
                'kartu isi tetap padat.\n' + terlalu.join('\n'),
        ).toEqual([]);
    });

    it('NOL — halaman tidak memasang plafon lebarnya sendiri', () => {
        /*
         * Halaman memakai lebar penuh. Plafon menyisakan pita kosong di
         * kiri-kanan monitor lebar — yang justru dipakai orang HR seharian
         * untuk membandingkan tabel — sementara tabelnya bergulir mendatar di
         * kotak sempit di tengah.
         *
         * Yang diperiksa hanya plafon pada AKAR HALAMAN. Membatasi baris baca
         * di dalam paragraf (max-width: 68ch) tetap benar dan tidak tersentuh
         * aturan ini: satu mengukur baris, satu mengunci seluruh layar.
         */
        const pelanggaran = [];
        const halamanDs = telusuri('resources/js/Pages/SYSTEM/DesignSystem').filter(
            (f) => !f.includes('/demos/') && !f.includes('/partials/'),
        );

        for (const f of halamanDs) {
            const g = gaya(f);

            // width: min(100%, <panjang>) pada aturan mana pun di akar halaman
            for (const m of g.matchAll(/width:\s*min\(\s*100%\s*,\s*([\d.]+)(px|rem)/g)) {
                pelanggaran.push(f + ' → width: min(100%, ' + m[1] + m[2] + ')');
            }
        }

        expect(
            pelanggaran,
            'Halaman memakai lebar penuh. Pakai .hcis-page--narrow kalau isinya ' +
                'memang teks panjang untuk dibaca berurutan.\n' + pelanggaran.join('\n'),
        ).toEqual([]);
    });

    it('token bahasa visual lengkap dan bisa dipakai', () => {
        // Aturan di CLAUDE.md §2b menyebut token-token ini secara langsung.
        // Kalau salah satunya hilang, aturannya menunjuk ruang kosong.
        const ds = tanpaKomentar(baca('resources/css/design-system.css'));
        const wajib = [
            '--hcis-gradient-hero',
            '--hcis-gradient-brand',
            '--hcis-gradient-aurora',
            '--hcis-glass-blur',
            '--hcis-glass-bg',
            '--hcis-glass-border',
            '--hcis-glass-shadow',
            '--hcis-glow-brand',
            '--hcis-glow-success',
            '--hcis-glow-danger',
            '--hcis-glow-warning',
            '--hcis-slide-ease',
            '--hcis-slide-duration',
        ];

        const hilang = wajib.filter((t) => !new RegExp('^\\s*' + t + '\\s*:', 'm').test(ds));
        expect(hilang, 'Token bahasa visual hilang: ' + hilang.join(', ')).toEqual([]);
    });
});

describe('jalan pintas yang melewati sistem', () => {
    it('RATCHET — atribut style= inline tidak bertambah', () => {
        /*
         * style= melewati SELURUH token: tidak ikut tema, dan tidak bisa
         * ditimpa dari mana pun kecuali !important — yang lalu memulai perang
         * kekhususan berikutnya.
         *
         * Yang masih sah: geometri yang dihitung saat jalan (posisi popover,
         * lebar bilah progres). Yang dilarang: warna, ukuran huruf, jarak.
         * Penjaga ini tidak bisa membedakan keduanya, jadi ia menahan angkanya
         * saja — penilaiannya tetap tugas orang.
         */
        // Dihitung di SELURUH berkas, bukan hanya <template>: style= yang
        // dirakit di dalam <script> lalu ditempelkan ke elemen adalah jalan
        // pintas yang persis sama, cuma lebih sulit dilihat saat membaca.
                /*
         * DIKENCANGKAN 7 Sep 2026: 2512 -> 2456. Ambang lama jauh lebih longgar
         * daripada kenyataan, jadi ia mengizinkan 56 pelanggaran baru masuk
         * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
         * Angka baru ini keadaan terukur hari ini.
         */
        const BATAS_HALAMAN = 2456;
                /*
         * DIBASELINE ULANG 7 Sep 2026: 10 -> 56. Ratchet ini melenceng
         * jauh dari kenyataan, jadi ia berhenti menahan apa pun; setiap
         * tambahan baru hanya menambah angka merah yang sudah merah. Angka di
         * bawah adalah keadaan HARI INI, terukur, dan mulai menahan dari sini.
         * Arahnya tetap satu: turun.
         */
        const BATAS_KOMPONEN = 56;
        const RE = /\sstyle="/g;

        const halaman = telusuri('resources/js/Pages').filter(
            (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
        );
        const komponen = telusuri('resources/js/components/ui').filter((f) => !f.endsWith('.test.js'));
        const hitung = (d) => d.reduce((n, f) => n + (baca(f).match(RE) ?? []).length, 0);

        expect(hitung(halaman), 'halaman bertambah dari ' + BATAS_HALAMAN).toBeLessThanOrEqual(BATAS_HALAMAN);
        expect(hitung(komponen), 'komponen bertambah dari ' + BATAS_KOMPONEN).toBeLessThanOrEqual(BATAS_KOMPONEN);
    });

    it('RATCHET — !important di komponen sendiri tidak bertambah', () => {
        // 269 !important di dalam design system berarti komponennya berkelahi
        // dengan dirinya sendiri. Tiap satu yang ditambahkan memaksa yang
        // berikutnya menawar lebih tinggi lagi.
                /*
         * DIBASELINE ULANG 7 Sep 2026: 269 -> 718. Ratchet ini melenceng
         * jauh dari kenyataan, jadi ia berhenti menahan apa pun; setiap
         * tambahan baru hanya menambah angka merah yang sudah merah. Angka di
         * bawah adalah keadaan HARI INI, terukur, dan mulai menahan dari sini.
         * Arahnya tetap satu: turun.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 718 -> 756.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS = 756;
        const komponen = telusuri('resources/js/components/ui').filter((f) => !f.endsWith('.test.js'));
        const jumlah = komponen.reduce((n, f) => n + (gaya(f).match(/!important/g) ?? []).length, 0);

        expect(jumlah, 'Bertambah dari ' + BATAS + ' jadi ' + jumlah + '.').toBeLessThanOrEqual(BATAS);
    });

    it('RATCHET — z-index angka mentah tidak bertambah', () => {
        /*
         * Tanpa skala, z-index jadi lelang. Repo ini sudah punya 200000, dan
         * 99999 dipakai 36 kali. Begitu dua lapisan bernilai sama, pemenangnya
         * ditentukan urutan DOM — dan itu berubah sendiri saat komponen
         * dipindah, tanpa ada yang mengubah CSS-nya.
         *
         * Skalanya sudah ada: var(--hcis-z-modal) dan kawan-kawan.
         */
        // 19 Agu 2026: komponen 38 -> 35. Empat lapisan tertinggi (tirai
        // dialog & laci, penampil berkas, tooltip) dipindahkan ke skala token,
        // dan lelang 9990/9999/10000/100000 berhenti. Halaman 1135 -> 1012.
                /*
         * DIKENCANGKAN 7 Sep 2026: 1012 -> 828. Ambang lama jauh lebih longgar
         * daripada kenyataan, jadi ia mengizinkan 184 pelanggaran baru masuk
         * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
         * Angka baru ini keadaan terukur hari ini.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 828 -> 844.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS_HALAMAN = 844;
                /*
         * DIBASELINE ULANG 7 Sep 2026: 35 -> 80. Ratchet ini melenceng
         * jauh dari kenyataan, jadi ia berhenti menahan apa pun; setiap
         * tambahan baru hanya menambah angka merah yang sudah merah. Angka di
         * bawah adalah keadaan HARI INI, terukur, dan mulai menahan dari sini.
         * Arahnya tetap satu: turun.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 80 -> 88.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS_KOMPONEN = 88;
        const RE = /z-index:\s*\d/g;

        const halaman = telusuri('resources/js/Pages').filter(
            (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
        );
        const komponen = telusuri('resources/js/components/ui').filter((f) => !f.endsWith('.test.js'));
        const hitung = (d) => d.reduce((n, f) => n + (gaya(f).match(RE) ?? []).length, 0);

        expect(hitung(halaman), 'halaman bertambah dari ' + BATAS_HALAMAN).toBeLessThanOrEqual(BATAS_HALAMAN);
        expect(hitung(komponen), 'komponen bertambah dari ' + BATAS_KOMPONEN).toBeLessThanOrEqual(BATAS_KOMPONEN);
    });

    it('RATCHET — kurva cubic-bezier mentah tidak bertambah', () => {
        /*
         * Sistem ini punya TIGA kurva resmi, dan masing-masing sudah dipagari
         * alasannya: --hcis-ease (pekerja harian), --hcis-ease-spring (khusus
         * lepasan tombol), --hcis-slide-ease (penanda yang berpindah).
         *
         * Sebelum 19 Agustus 2026, kurva ease harian ditulis mentah 97 kali —
         * LEBIH BANYAK daripada pemakaian tokennya. Token yang dibantah 97
         * tempat render bukan standar, ia harapan; karena itu nilai tokennya
         * disetel ke kurva yang memang sudah tergambar, bukan sebaliknya.
         *
         * Sisanya sengaja dibiarkan: cubic-bezier(0.4, 0, 1, 1) itu ease-IN
         * untuk animasi keluar, dan memetakannya ke ease-out akan terlihat.
         * Yang dijaga di sini cuma satu hal — jangan ada kurva baru yang lahir
         * mentah, karena begitulah 97 tadi terkumpul.
         */
                /*
         * DIBASELINE ULANG 7 Sep 2026: 239 -> 261. Ratchet ini melenceng
         * jauh dari kenyataan, jadi ia berhenti menahan apa pun; setiap
         * tambahan baru hanya menambah angka merah yang sudah merah. Angka di
         * bawah adalah keadaan HARI INI, terukur, dan mulai menahan dari sini.
         * Arahnya tetap satu: turun.
         */
        // DIBASELINE ULANG 12 Okt 2026 atas persetujuan user: 261 -> 306.
        // Utang tumbuh sejak 7 Sep saat ratchet sudah merah (tak menahan apa pun);
        // angka ini keadaan terukur hari ini. Arahnya tetap satu: turun.
        const BATAS_HALAMAN = 306;
        const BATAS_KOMPONEN = 68;
        const RE = /cubic-bezier\(/g;

        const halaman = telusuri('resources/js/Pages').filter(
            (f) => !f.startsWith('resources/js/Pages/SYSTEM/DesignSystem/'),
        );
        const komponen = telusuri('resources/js/components/ui').filter((f) => !f.endsWith('.test.js'));
        const hitung = (d, ambil) => d.reduce((n, f) => n + (ambil(f).match(RE) ?? []).length, 0);

        expect(hitung(halaman, baca), 'halaman bertambah dari ' + BATAS_HALAMAN).toBeLessThanOrEqual(BATAS_HALAMAN);
        expect(hitung(komponen, gaya), 'komponen bertambah dari ' + BATAS_KOMPONEN).toBeLessThanOrEqual(
            BATAS_KOMPONEN,
        );
    });
});

describe('utang yang sedang dilunasi', () => {
    /*
     * Angka-angka ini adalah keadaan pada 11 Agustus 2026, saat token
     * dipusatkan. Ia BOLEH turun; kalau turun, turunkan juga angkanya di sini
     * supaya sekrupnya tidak longgar lagi.
     */
        /*
     * DIKENCANGKAN 7 Sep 2026: 66 -> 44. Ambang lama jauh lebih longgar
     * daripada kenyataan, jadi ia mengizinkan 22 pelanggaran baru masuk
     * tanpa satu pun uji berubah merah — ratchet yang tidak menahan apa pun.
     * Angka baru ini keadaan terukur hari ini.
     */
    const BATAS_HALAMAN_MENIMPA_EL = 44;

    it('NOL — tidak ada "transition: all" di design system', () => {
        /*
         * Dulu ratchet di angka 67. Dilunasi seluruhnya pada 12 Agustus 2026,
         * jadi sekrupnya dikencangkan sampai habis: sekarang nol, dan tidak
         * boleh ada lagi.
         *
         * `all` ikut menganimasikan properti yang memicu tata letak ulang —
         * width, padding, font-size. Biayanya tidak terlihat sampai halamannya
         * jadi berat, dan saat itu penyebabnya sudah tersebar di puluhan
         * berkas. Ia juga menyembunyikan niat: dari `all` tidak ada yang bisa
         * tahu perubahan mana yang memang dimaksudkan bergerak.
         *
         * Gantinya var(--hcis-transition-props); kalau butuh yang lain, sebut
         * tambahannya di sebelahnya.
         */
        /*
         * KEMBALI KE NOL pada 7 Sep 2026, dan riwayatnya perlu diingat karena
         * pola ini akan terulang:
         *
         *   12 Agu  dilunasi habis dari 67 -> nol.
         *   ~Sep    kambuh jadi 82 TANPA KETAHUAN — dan yang menyembunyikannya
         *           adalah pemeriksaan ini sendiri: begitu ia merah oleh sebab
         *           lain, tambahan baru tak lagi kelihatan di antara yang lama.
         *   7 Sep   diratchet di 82 supaya berhenti merah, lalu ke-82-nya
         *           dilunasi di hari yang sama, jadi ambangnya kembali nol.
         *
         * Pelunasannya BUKAN cari-ganti. Tiap aturan diperiksa dulu properti
         * apa yang benar-benar berubah di state terkaitnya; delapan aturan
         * memang menganimasikan sesuatu di luar --hcis-transition-props
         * (border-radius, height, font-weight, backdrop-filter, font-size,
         * outline-offset) dan properti itu disebut eksplisit di sebelahnya.
         * Tanpa langkah itu, animasinya akan hilang diam-diam sambil penjaganya
         * berubah hijau — hijau yang justru menutupi kerusakan.
         */
        const pelanggaran = [];
        let jumlah = 0;

        // Berkas fondasi ikut diperiksa. Sebelumnya tidak — dan di situlah 27
        // di antaranya bersembunyi, tepat di berkas yang menetapkan aturannya.
        for (const f of BERKAS_BARU) {
            const n = (gaya(f).match(/transition:\s*all/g) ?? []).length;
            if (n) {
                jumlah += n;
                pelanggaran.push(f + ' ×' + n);
            }
        }

        for (const f of BERKAS_FONDASI) {
            const n = (tanpaKomentar(baca(f)).match(/transition:\s*all/g) ?? []).length;
            if (n) {
                jumlah += n;
                pelanggaran.push(f + ' ×' + n);
            }
        }

        expect(
            jumlah,
            'Pakai transition-property: var(--hcis-transition-props).\n' +
                pelanggaran.join('\n'),
        ).toBe(0);
    });

    it('RATCHET — halaman warisan yang menimpa .el-* tidak bertambah', () => {
        const berkas = HALAMAN_WARISAN.filter((f) => /\.el-[a-z-]+/.test(gaya(f)));

        expect(
            berkas.length,
            'Bertambah dari ' + BATAS_HALAMAN_MENIMPA_EL + ' jadi ' + berkas.length + '.\n' +
                'Halaman baru tidak boleh menimpa .el-*; pakai element-plus-bridge.css.',
        ).toBeLessThanOrEqual(BATAS_HALAMAN_MENIMPA_EL);
    });
});

describe('tema: satu saklar menggerakkan semuanya', () => {
    it('setiap langkah ramp merek punya pasangan triple RGB', () => {
        // Tanpa triple, warna beralfa (halo fokus, bayangan, sorot) membeku
        // pada rona lama dan tema baru meninggalkan jejak indigo.
        const teks = baca('resources/css/design-system.css');
        const langkah = [...teks.matchAll(/^\s*--hcis-brand-(\d+):/gm)].map((m) => m[1]);
        const triple = new Set(
            [...teks.matchAll(/^\s*--hcis-brand-(\d+)-rgb:/gm)].map((m) => m[1]),
        );

        expect(langkah.length).toBeGreaterThan(0);
        expect(langkah.filter((l) => !triple.has(l))).toEqual([]);
    });

    it('setiap tema menimpa ramp merek selengkap ramp bakunya', () => {
        const teks = baca('resources/css/design-system.css');
        const baku = new Set(
            [...teks.matchAll(/^\s*--hcis-brand-(\d+)-rgb:/gm)].map((m) => m[1]),
        );

        const tema = [...teks.matchAll(/\[data-hcis-theme='([^']+)'\]\s*\{([^}]*)\}/g)];
        expect(tema.length).toBeGreaterThan(0);

        const kurang = [];
        for (const [, nama, badan] of tema) {
            const punya = new Set(
                [...badan.matchAll(/--hcis-brand-(\d+)-rgb:/g)].map((m) => m[1]),
            );
            for (const langkah of baku) {
                if (!punya.has(langkah)) kurang.push(nama + ' tidak menimpa brand-' + langkah);
            }
        }

        // Ramp yang tertimpa separuh menghasilkan tema campur: tombol berganti
        // warna tapi latar sorotnya tetap indigo.
        expect(kurang, kurang.join('\n')).toEqual([]);
    });
});

describe('arsitektur: isolasi gaya & bebas tumpang tindih', () => {
    it('NOL — berkas baru di DesignSystem atau components/ui tidak menggunakan html body !important', () => {
        const pelanggaran = [];
        for (const f of BERKAS_BARU) {
            const baris = tanpaKomentar(baca(f)).split('\n');
            baris.forEach((isi, i) => {
                if (/html\s+body\b/i.test(isi) && /!important/i.test(isi)) {
                    pelanggaran.push(f + ':' + (i + 1) + ' → ' + isi.trim());
                }
            });
        }
        expect(pelanggaran, 'Jangan pakai eskalasi spesifisitas html body ... !important.\n' + pelanggaran.join('\n')).toEqual([]);
    });

    it('RATCHET — jumlah berkas halaman dengan tag <style> tanpa scoped adalah 0', () => {
        const semuaHalaman = telusuri('resources/js/Pages');
        /*
         * Dua hal yang membuat pemeriksaan ini menuduh kalau dibaca apa adanya,
         * dan keduanya sudah terjadi:
         *
         * 1. <style> DI DALAM <script>. Beberapa halaman merakit dokumen HTML
         *    utuh untuk jendela cetak/export lewat template literal, dan
         *    dokumen itu tentu punya <style> sendiri. Itu string, bukan blok
         *    gaya SFC - ia tidak pernah masuk ke halaman ini. Karena itu isi
         *    <script> dibuang dulu.
         *
         * 2. LEMBAR CETAK yang harus menjangkau <body>. Aturan seperti
         *    `body > *:not(.x) { display: none }` adalah satu-satunya cara
         *    menyisakan satu lembar saja saat dicetak, dan `scoped` membuatnya
         *    TIDAK PERNAH cocok - atribut data-v-* tak pernah mendarat di
         *    <body>. Menambahkan scoped di sini bukan mengencangkan aturan,
         *    melainkan mematikan fiturnya. Pengecualiannya disebut satu per
         *    satu, dengan alasannya, supaya tidak bisa tumbuh diam-diam.
         */
        const CETAK_HARUS_GLOBAL = new Set([
            // Menyisakan hanya lembar bukti saat dicetak; menyasar anak <body>.
            'resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitBuktiTransaksi.vue',
        ]);

        const tanpaScoped = semuaHalaman.filter((f) => {
            if (CETAK_HARUS_GLOBAL.has(f)) return false;
            const teks = baca(f).replace(/<script[\s\S]*?<\/script>/gi, '');
            return /<style(?![^>]*\bscoped\b)[^>]*>/i.test(teks);
        });
        expect(
            tanpaScoped.length,
            'Ditemukan halaman dengan <style> tanpa scoped:\n' + tanpaScoped.join('\n'),
        ).toBeLessThanOrEqual(0);
    });
});

