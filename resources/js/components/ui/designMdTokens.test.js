/*
 * Kontrak design.md.
 *
 * Nilai di sini disalin dari design.md apa adanya — termasuk yang terasa
 * salah. Kalau sebuah nilai perlu berubah, yang diubah adalah design.md dan
 * berkas ini bersama-sama, lewat keputusan pemilik proyek. Mengubah
 * design-system.css sendirian akan membuat tes ini merah, dan itu memang
 * gunanya: nilai token tidak boleh bergeser diam-diam.
 *
 * Spec: docs/superpowers/specs/2026-08-13-design-md-lapis-2-design.md
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const AKAR = path.resolve(__dirname, '../../../..');
const DS = readFileSync(path.join(AKAR, 'resources/css/design-system.css'), 'utf8');

/**
 * Nilai sebuah token, diambil dari definisi PERTAMA-nya di design-system.css.
 *
 * Sengaja yang pertama: lapis 3 (tema) mendefinisikan ulang sebagian token di
 * bawah [data-hcis-theme]. Yang jadi kontrak design.md adalah lapis 2, dan
 * lapis 2 selalu lebih dulu di berkas ini.
 */
function nilaiToken(nama) {
    const cocok = DS.match(new RegExp('^\\s*' + nama + '\\s*:\\s*([^;]+);', 'm'));
    if (!cocok) return null;
    return cocok[1].replace(/\/\*[\s\S]*?\*\//g, '').trim();
}

describe('design.md — primitif', () => {
    it('#607080 tidak lagi lahir sebagai primitif', () => {
        /*
         * Sempat ditambahkan sebagai --hcis-steel-500 untuk memuat
         * color.text.primary. Dicabut setelah dihitung: dipasang sebagai teks
         * utama, ia menyeret rentang teks dari 6,96:1 ke 1,07:1 dan MEMBALIK
         * urutannya — text 5,09:1 sementara muted 7,58:1, jadi judul lebih
         * pudar daripada keterangannya.
         *
         * Primitif tanpa pemakai adalah utang: yang berikutnya akan mengira
         * ia sengaja disediakan untuk dipakai.
         */
        expect(nilaiToken('--hcis-steel-500')).toBeNull();
        expect(nilaiToken('--hcis-steel-500-rgb')).toBeNull();
    });

    it('hitam sudah ada dan tidak diduplikasi', () => {
        expect(nilaiToken('--hcis-black-rgb')).toBe('0 0 0');
        expect(nilaiToken('--hcis-black')).toBe('rgb(var(--hcis-black-rgb))');
    });
});

describe('design.md — warna lapis 2', () => {
    /*
     * DUA nilai design.md sengaja TIDAK dipakai, dan keduanya ditolak karena
     * alasan yang sama: ekstraktornya mengambilnya dari elemen yang perannya
     * bukan yang namanya sebutkan.
     *
     *   color.surface.base = #000000  → permukaan kartu hitam berisi teks
     *       #607080. Itu bukan tema gelap, melainkan satu token terbalik
     *       sendirian, karena teksnya tidak ikut dibalik.
     *   color.text.inverse = #334155  → token ini dipakai di ATAS
     *       surface-inverse (slate-900). #334155 di sana 1,9:1.
     *
     * surface-brand memakai var(--hcis-brand-500), bukan var(--hcis-indigo-500):
     * keduanya #6366f1 pada tema baku, tapi brand-500 ikut berpindah saat tema
     * laut/hutan dinyalakan. Lapis 2 menyebut ARTI, bukan rona.
     */
    it.each([
        ['--hcis-surface', 'var(--hcis-white)'],
        ['--hcis-surface-rgb', 'var(--hcis-white-rgb)'],
        ['--hcis-surface-raised', 'var(--hcis-white)'],
        ['--hcis-surface-soft', 'var(--hcis-slate-50)'],
        // surface.strong #6366f1 TIDAK dipakai di sini: perannya "latar sorot
        // bernuansa merek" — semburat tipis di belakang teks gelap. Dipasang
        // brand-500, tiga pemakainya dapat indigo pekat. #6366f1 sudah punya
        // nama sendiri: --hcis-brand-500.
        ['--hcis-surface-brand', 'var(--hcis-brand-50)'],
        ['--hcis-surface-inverse', 'var(--hcis-slate-900)'],
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('raised harus berbeda dari soft', () => {
        // design.md surface.raised = #f8fafc, persis surface-soft. Permukaan
        // yang namanya "terangkat" jadi sewarna dengan yang cekung: 1,00:1.
        expect(nilaiToken('--hcis-surface-raised')).not.toBe(nilaiToken('--hcis-surface-soft'));
    });

    it('surface.base #000000 tidak boleh kembali diam-diam', () => {
        // Penjaga arah: kalau suatu hari --hcis-surface hitam lagi, itu
        // keputusan yang harus diambil sadar, bukan hasil menyalin ulang
        // design.md tanpa membaca catatan ini.
        expect(nilaiToken('--hcis-surface')).not.toContain('black');
        expect(nilaiToken('--hcis-surface-rgb')).not.toContain('black');
    });

    it.each([
        ['--hcis-text', 'var(--hcis-slate-700)'],
        ['--hcis-text-strong', 'var(--hcis-slate-900)'],
        ['--hcis-text-muted', 'var(--hcis-slate-500)'],
        ['--hcis-text-subtle', 'var(--hcis-slate-400)'],
        ['--hcis-text-inverse', 'var(--hcis-white)'],
    ])('%s = %s (tangga teruji, bukan palet design.md)', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('tangga teks menurun terang, tidak terbalik dan tidak rata', () => {
        /*
         * Penjaga arah, dan ini yang paling penting dari seluruh berkas.
         *
         * Palet teks design.md membuat --hcis-text (5,09:1) lebih PUDAR
         * daripada --hcis-text-muted (7,58:1): judul dan isi jadi kalah
         * menonjol dari keterangan pendukungnya. Cacat itu tidak terasa
         * sebagai warna yang salah, melainkan sebagai halaman yang "semuanya
         * menyatu" — dan itu jauh lebih sulit dilacak.
         *
         * Diperiksa lewat urutan langkah slate, bukan lewat perhitungan
         * kontras, supaya tesnya tidak ikut menebak model warna.
         */
        const langkah = (t) => {
            const m = nilaiToken(t).match(/--hcis-slate-(\d+)/);
            return m ? Number(m[1]) : null;
        };

        const strong = langkah('--hcis-text-strong');
        const text = langkah('--hcis-text');
        const muted = langkah('--hcis-text-muted');
        const subtle = langkah('--hcis-text-subtle');

        expect(strong).toBeGreaterThan(text);   // strong paling gelap
        expect(text).toBeGreaterThan(muted);    // <-- yang terbalik oleh design.md
        expect(muted).toBeGreaterThan(subtle);

        // Rentangnya harus benar-benar terasa, bukan sekadar berurutan.
        expect(strong - subtle).toBeGreaterThanOrEqual(300);
    });

    it.each([
        ['--hcis-border', 'var(--hcis-slate-200)'],
        ['--hcis-border-medium', 'var(--hcis-slate-300)'],
        ['--hcis-border-strong', 'var(--hcis-slate-400)'],
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('tangga batas punya empat tingkat yang benar-benar berbeda', () => {
        /*
         * Di atas halaman slate-50: subtle 1,03:1 · border 1,18:1 ·
         * medium 1,52:1 · strong 4,76:1.
         *
         * border-medium lahir karena ada lubang nyata di antara border dan
         * border-strong. border cukup untuk memisahkan isi DI DALAM kartu
         * putih, tidak cukup untuk menggambar tepi panel yang berdiri di atas
         * halaman. border-strong terlalu tegas untuk itu — ia garis KONTROL,
         * dan panel yang memakainya terlihat seperti kotak isian raksasa.
         */
        const langkah = (t) => Number(nilaiToken(t).match(/--hcis-slate-(\d+)/)[1]);
        expect(langkah('--hcis-border-subtle')).toBeLessThan(langkah('--hcis-border'));
        expect(langkah('--hcis-border')).toBeLessThan(langkah('--hcis-border-medium'));
        expect(langkah('--hcis-border-medium')).toBeLessThan(langkah('--hcis-border-strong'));
    });
});

describe('design.md — huruf', () => {
    it('keluarga huruf persis font.family.stack', () => {
        // Ini yang DIPAKAI dari bagian huruf design.md. Cadangan Segoe UI /
        // system-ui dilepas karena design.md menyebut tumpukannya utuh.
        expect(nilaiToken('--hcis-font-body')).toBe("'Inter', sans-serif");
    });

    it('bobot dasar 400 sudah sesuai dan tidak diubah', () => {
        expect(nilaiToken('--hcis-weight-regular')).toBe('400');
    });

    /*
     * Skala ukurannya TIDAK dipakai. Delapan anak tangga design.md seluruhnya
     * muat di 10,4–13,6px — rentang 3,2px untuk hierarki yang punya delapan
     * tingkat. Judul kartu jadi praktis sebesar isinya.
     *
     * Tanda paling jelas bahwa yang terkumpul bukan skala: design.md
     * menetapkan font.size.base = 16px, lebih besar dari SELURUH skalanya.
     */
    it.each([
        ['--hcis-font-size-2xs', '0.6875rem'],
        ['--hcis-font-size-xs', '0.75rem'],
        ['--hcis-font-size-sm', '0.875rem'],
        ['--hcis-font-size-md', '1rem'],
        ['--hcis-font-size-lg', '1.125rem'],
        ['--hcis-font-size-xl', '1.5rem'],
        ['--hcis-font-size-2xl', '2rem'],
    ])('%s = %s (skala teruji, bukan skala design.md)', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('hierarki huruf punya rentang yang benar-benar terasa', () => {
        // Penjaga arah. Kalau suatu saat skala design.md disalin ulang, ini
        // yang menyala lebih dulu: display (2xl) harus jauh lebih besar dari
        // isi (sm), bukan berselisih dua piksel.
        const rem = (t) => parseFloat(nilaiToken(t));
        expect(rem('--hcis-font-size-2xl') / rem('--hcis-font-size-sm')).toBeGreaterThan(2);
    });
});

describe('design.md — jarak', () => {
    /*
     * Skala jarak design.md (1–6,72px) TIDAK dipakai. Ekstraktornya mengambil
     * gap dan padding elemen-elemen terkecil di halaman SOP, jadi yang
     * terkumpul bukan skala tata letak melainkan daftar jarak terkecil yang
     * kebetulan ada di sana.
     *
     * Akibatnya terlihat langsung: padding kartu 5px sehingga isinya menempel
     * ke tepi, dan --sm/biasa/--lg berselisih 2,7px total sehingga varian
     * ukurannya hilang tanpa pernah dihapus.
     */
    it.each([
        ['--hcis-space-1', '0.25rem'],
        ['--hcis-space-2', '0.5rem'],
        ['--hcis-space-3', '0.75rem'],
        ['--hcis-space-4', '1rem'],
        ['--hcis-space-5', '1.25rem'],
        ['--hcis-space-6', '1.5rem'],
        ['--hcis-space-8', '2rem'],
        ['--hcis-space-10', '2.5rem'],
        ['--hcis-space-12', '3rem'],
        ['--hcis-space-16', '4rem'],
    ])('%s = %s (skala teruji, bukan skala design.md)', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('kartu punya bantalan yang cukup untuk isinya', () => {
        // Penjaga arah: padding kartu baku diambil dari space-5/space-6.
        // Di bawah 12px, isi kartu menempel ke tepi dan kisi kartu berhenti
        // terbaca sebagai kumpulan kartu.
        expect(parseFloat(nilaiToken('--hcis-space-5')) * 16).toBeGreaterThanOrEqual(12);
        expect(parseFloat(nilaiToken('--hcis-space-6')) * 16).toBeGreaterThanOrEqual(12);
    });
});

describe('design.md — radius', () => {
    /*
     * Turunan calc(--hcis-radius-base * n) dibongkar jadi nilai langsung:
     * skala design.md bukan skala berkelipatan, jadi rumusnya tidak berlaku.
     *
     * Efek yang perlu disadari: radius-sm (elemen kecil DI DALAM kontrol)
     * kini LEBIH BULAT daripada radius-md (tombol). Itu bawaan urutan di
     * design.md, bukan salah salin.
     */
    it.each([
        ['--hcis-radius-xs', '0.25rem'],
        ['--hcis-radius-base', '0.5rem'],
        ['--hcis-radius-sm', '0.375rem'],
        ['--hcis-radius-md', '0.625rem'],
        ['--hcis-radius-lg', '0.875rem'],
        ['--hcis-radius-xl', '1.125rem'],
        ['--hcis-radius-2xl', '1.5rem'],
        ['--hcis-radius-3xl', '1.75rem'],
        ['--hcis-radius-4xl', '2rem'],
        ['--hcis-radius-pill', '9999px'],
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });
});

describe('design.md — bayangan', () => {
    /*
     * design.md memberi EMPAT resep, dua di antaranya glow berwarna. Jadi
     * sistem turun dari empat tingkat ketinggian jadi dua, dan sisanya
     * dialiaskan — bukan dikarang. Mengarang tingkat ketiga sama dengan
     * mengarang pengecualian, yang design.md larang.
     *
     * Semua ditulis SATU BARIS: penjaga NOL hanya menerima nilai mentah pada
     * baris yang mendefinisikan token, jadi nilai multi-baris akan menjatuhkan
     * seluruh berkas fondasi.
     */
    it('shadow-xs = design.md shadow.4', () => {
        expect(nilaiToken('--hcis-shadow-xs')).toBe(
            '0 1px 3px 0 rgb(var(--hcis-shadow-rgb) / 0.04)',
        );
    });

    it('shadow-raised = design.md shadow.1, sebagai tingkat SENDIRI', () => {
        /*
         * Resep ini sempat dipasang sebagai --hcis-shadow-sm, dan hasilnya
         * terlihat: bayangan dekatnya beralfa 0,05 (setengah -sm), jadi panel
         * putih di atas halaman slate-50 kehilangan separasinya. Sorot
         * dalamnya putih 0,9 — di atas permukaan putih ia tidak melakukan
         * apa pun, jadi bagian yang seharusnya menggambar tepi atas justru
         * menganggur.
         *
         * Dipakai HANYA untuk permukaan bertint atau berkaca.
         */
        expect(nilaiToken('--hcis-shadow-raised')).toBe(
            '0 1px 2px 0 rgb(var(--hcis-shadow-rgb) / 0.05), ' +
                '0 10px 26px -16px rgb(var(--hcis-shadow-rgb) / 0.22), ' +
                'inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)',
        );
    });

    it.each([
        ['--hcis-shadow-sm', '0 1px 3px 0 rgb(var(--hcis-shadow-rgb) / 0.1), 0 1px 2px -1px rgb(var(--hcis-shadow-rgb) / 0.1)'],
        ['--hcis-shadow-md', '0 4px 6px -1px rgb(var(--hcis-shadow-rgb) / 0.1), 0 2px 4px -2px rgb(var(--hcis-shadow-rgb) / 0.1)'],
        ['--hcis-shadow-lg', '0 10px 15px -3px rgb(var(--hcis-shadow-rgb) / 0.1), 0 4px 6px -4px rgb(var(--hcis-shadow-rgb) / 0.1)'],
        ['--hcis-shadow-card', '0 10px 30px -4px rgb(var(--hcis-shadow-rgb) / 0.06), 0 4px 12px -2px rgb(var(--hcis-shadow-rgb) / 0.03), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.8)'],
    ])('%s punya tingkatnya sendiri, tidak dialiaskan', (token, nilai) => {
        // Mengaliaskan md/lg/card ke -sm membuat kartu, bilah filter, dan
        // panel rata sama tinggi — kisi kartu berhenti terbaca sebagai
        // kumpulan benda terpisah.
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('shadow-glow menunjuk glow-brand design.md', () => {
        expect(nilaiToken('--hcis-shadow-glow')).toBe('var(--hcis-glow-brand)');
    });

    it('glow-brand = design.md shadow.2', () => {
        expect(nilaiToken('--hcis-glow-brand')).toBe(
            '0 8px 18px -8px rgb(var(--hcis-brand-500-rgb) / 0.6)',
        );
    });

    it('glow-warning = design.md shadow.3', () => {
        expect(nilaiToken('--hcis-glow-warning')).toBe(
            '0 0 0 4px rgb(var(--hcis-warning-500-rgb) / 0.12), ' +
                '0 22px 44px -20px rgb(var(--hcis-warning-500-rgb) / 0.4), ' +
                'inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)',
        );
    });

    it.each([
        ['--hcis-glow-success', 'success'],
        ['--hcis-glow-danger', 'danger'],
    ])('%s memakai resep shadow.3 dengan rona diganti', (token, rona) => {
        // design.md tidak menyebut keduanya, tapi penjaga "token bahasa visual
        // lengkap" menuntut keduanya ada. Resep shadow.3 dipilih karena ia
        // SATU-SATUNYA contoh keadaan berwarna di design.md — keluarga glow-*
        // harus satu dialek.
        expect(nilaiToken(token)).toBe(
            `0 0 0 4px rgb(var(--hcis-${rona}-500-rgb) / 0.12), ` +
                `0 22px 44px -20px rgb(var(--hcis-${rona}-500-rgb) / 0.4), ` +
                'inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)',
        );
    });
});

describe('design.md — gerak', () => {
    /*
     * Tangga delapan tingkat design.md diringkas jadi EMPAT pada 19 Agustus 2026.
     *
     * Yang dihapus — normal (220), slow (240), slower (250) — berdesakan dalam
     * 50ms bersama fast (200): selisih 10%, 9%, dan 4%, sementara beda yang
     * mulai terasa pada durasi transisi sekitar 20–25%. Ketiganya bukan pilihan,
     * hanya angka. Dan page (500) dihapus karena tidak pernah dipakai satu kali
     * pun; rung mati adalah contoh hidup bahwa tangga ini boleh diabaikan.
     *
     * press turun 160 → 100. Komentar lama di sini sudah menandai 160 "di ambang
     * terasa lamban" dan menyebut penurunannya sebagai keputusan terpisah —
     * inilah keputusan itu. 100ms praktis tak terbedakan dari 75ms yang selama
     * ini benar-benar berjalan, dan aman di bawah ±150ms tempat sebuah tekanan
     * mulai terasa lepas dari jari.
     */
    it.each([
        ['--hcis-duration-press', '100ms'],
        ['--hcis-duration-fast', '200ms'],
        ['--hcis-duration-panel', '300ms'],
        ['--hcis-duration-move', '400ms'],
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    /*
     * Penjaga arah, bukan penjaga nilai: rung yang sudah dihapus tidak boleh
     * lahir kembali. Tanpa ini, orang berikutnya yang butuh "sedikit lebih
     * lambat dari fast" akan menambahkan normal lagi, dan tangganya kembali
     * berdesakan dalam setahun.
     */
    it.each(['--hcis-duration-normal', '--hcis-duration-slow', '--hcis-duration-slower', '--hcis-duration-page'])(
        '%s sudah dihapus dan tidak boleh kembali',
        (token) => {
            expect(nilaiToken(token)).toBeNull();
        },
    );

    it('slide-duration menunjuk tangga yang sama, bukan angka lepas', () => {
        expect(nilaiToken('--hcis-slide-duration')).toBe('var(--hcis-duration-move)');
    });
});

describe('cacat yang ketemu saat memasang design.md', () => {
    /*
     * Ini bukan bagian design.md — ini cacat yang ketemu saat mengerjakannya.
     *
     * Sorotan baris ditulis DUA KALI dengan angka berbeda: pada `tr` di
     * design-system.css dan pada `td` di HcisTable.vue. Latar td selalu menang
     * atas latar tr, jadi yang di design-system.css TIDAK PERNAH terlihat —
     * dan siapa pun yang menyetelnya di sana akan menyimpulkan perubahannya
     * tidak berpengaruh, lalu mencari sebabnya di tempat yang salah.
     */
    it.each([
        ['--hcis-row-hover', 'rgb(var(--hcis-brand-50-rgb) / 0.45)'],
        ['--hcis-row-striped', 'rgb(var(--hcis-slate-50-rgb) / 0.6)'],
        ['--hcis-row-selected', 'rgb(var(--hcis-brand-50-rgb) / 0.75)'],
    ])('%s lahir sekali di design-system.css', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('bilah filter punya tepi yang benar-benar terlihat', () => {
        /*
         * Cacat lain yang ketemu lewat mata, bukan lewat tes.
         *
         *   --glass  memasang latar BURAM lalu backdrop-filter di atasnya.
         *            Blur di belakang latar buram tidak melakukan apa pun,
         *            jadi variannya tidak pernah benar-benar berkaca. Ia juga
         *            tidak punya bayangan sama sekali.
         *   --flat   memakai --hcis-surface-soft, yaitu warna yang SAMA PERSIS
         *            dengan latar halaman.
         *
         * Keduanya ditambah --hcis-border-subtle (slate-100) yang di atas
         * slate-50 cuma 1,03:1 — garisnya ada di CSS, tidak ada di layar.
         */
        const bar = readFileSync(
            path.join(AKAR, 'resources/js/components/ui/HcisFilterBar.vue'),
            'utf8',
        );
        const gaya = bar.slice(bar.indexOf('<style'));
        const varian = (nama) => {
            const m = gaya.match(new RegExp('\\.hcis-filter-bar--' + nama + '\\s*\\{([^}]*)\\}'));
            return m ? m[1] : '';
        };

        for (const nama of ['card', 'glass', 'flat']) {
            // border-medium (1,52:1 di atas slate-50), bukan border (1,18:1)
            // dan bukan border-subtle (1,03:1).
            expect(varian(nama), nama).toContain('1px solid var(--hcis-border-medium)');
            expect(varian(nama), nama).not.toContain('--hcis-border-subtle');
            // Latar putih di atas slate-50 hanya 1,05:1, jadi tepi SAJA tidak
            // cukup — tiap varian wajib punya bayangannya sendiri.
            expect(varian(nama), nama).toContain('box-shadow:');
        }

        // Kaca harus beralfa, kalau tidak blur-nya menganggur.
        expect(varian('glass')).toContain('background: var(--hcis-glass-bg)');

        // "Flat" berarti tidak terangkat, bukan tidak terlihat.
        expect(varian('flat')).not.toContain('var(--hcis-surface-soft)');
    });

    it('pagination tidak memakai warna latar halaman untuk bagiannya', () => {
        /*
         * Pola cacat yang sama, ketiga kalinya: bagian yang seharusnya
         * terlihat diberi --hcis-surface-soft (warna latar halaman) plus
         * --hcis-border-subtle (1,05:1 di atasnya). Lencana total dan tombol
         * halaman jadi tidak punya batas sampai kursor lewat.
         *
         * Ditambah dua bayangan yang ditulis angka mentah, jadi keduanya tidak
         * ikut berpindah saat tangga bayangan berubah.
         */
        const pg = readFileSync(
            path.join(AKAR, 'resources/js/components/ui/HcisPagination.vue'),
            'utf8',
        );
        const gaya = pg.slice(pg.indexOf('<style'));
        const blok = (sel) => {
            const m = gaya.match(new RegExp(sel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*\\{([^}]*)\\}'));
            return m ? m[1] : '';
        };

        expect(blok('.hcis-pagination__total-badge')).toContain('background-color: var(--hcis-surface)');
        expect(blok('.hcis-pagination__total-badge')).toContain('var(--hcis-border-medium)');
        expect(blok('.hcis-pagination__size-trigger')).toContain('var(--hcis-border-medium)');

        // Tombol halaman punya tepi sejak diam, bukan baru saat disorot.
        expect(gaya).not.toContain('border: 1px solid transparent');

        // Bayangan lewat token, bukan angka.
        expect(blok('.hcis-pagination__size-menu')).toContain('box-shadow: var(--hcis-shadow-lg)');
        expect(gaya).not.toMatch(/box-shadow:\s*0 10px 25px/);
    });

    it('HcisTable memakai token itu, bukan angkanya sendiri', () => {
        const tabel = readFileSync(
            path.join(AKAR, 'resources/js/components/ui/HcisTable.vue'),
            'utf8',
        );
        const gaya = tabel.slice(tabel.indexOf('<style'));

        expect(gaya).toContain('background-color: var(--hcis-row-hover)');
        expect(gaya).toContain('background-color: var(--hcis-row-selected)');
        expect(gaya).toContain('background-color: var(--hcis-row-striped)');
        expect(gaya).not.toContain('var(--hcis-brand-50, var(--hcis-violet-50))');
    });
});
