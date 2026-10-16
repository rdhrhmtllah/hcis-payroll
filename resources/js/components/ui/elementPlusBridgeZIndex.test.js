import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * LAPISAN MENGAMBANG ELEMENT PLUS TIDAK BOLEH DIPAKU KETINGGIANNYA.
 *
 * `app.js` memasang Element Plus dengan `{ zIndex: 3000 }`. Angka itu nilai
 * AWAL sebuah PENCACAH, bukan nilai tetap: tiap lapisan baru mengambil nomor
 * berikutnya dan menuliskannya INLINE. Laci wizard mendapat 3002, dropdown yang
 * dibuka di dalamnya mendapat 3008 — dan justru urutan itulah yang membuat
 * dropdown selalu berada di atas wadahnya.
 *
 * Memaku `z-index: … !important` di stylesheet mengalahkan nilai inline itu dan
 * mengembalikan semua popper ke satu angka mati. Terukur pada 10 Sep 2026:
 * seluruh saringan el-select di dalam laci "Jadwalkan Dokumen" (tipe,
 * departemen, divisi, lokasi, level) terbuka — `aria-hidden="false"`, inline
 * `z-index: 3008` — tetapi computed-nya 3000, di bawah laci 3002, sehingga
 * tersembunyi di belakang lacinya tanpa satu pun galat di konsol. Yang kena
 * bukan satu halaman: setiap el-select di dalam el-drawer atau el-dialog mana
 * pun di HCIS.
 *
 * Berkas uji ini membaca CSS-nya sebagai TEKS, bukan me-render komponen.
 * Alasannya sederhana: cacatnya hidup di stylesheet, bukan di komponen, dan
 * jsdom tak menghitung layer stacking sama sekali — uji komponen apa pun akan
 * hijau sambil membiarkan cacat ini lewat.
 */

const BERKAS = resolve(__dirname, '../../../css/element-plus-bridge.css');

/** Kelas lapisan mengambang yang ketinggiannya WAJIB diserahkan ke Element. */
const POPPER_ELEMENT = [
    'el-select__popper',
    'el-select-dropdown',
    'el-picker__popper',
    'el-dropdown__popper',
    'el-autocomplete-suggestion',
    'el-popper',
];

/**
 * Pecah CSS jadi blok `{ selektor, isi }` yang kasar tapi cukup.
 *
 * Cukup karena yang dicari cuma satu bentuk: deklarasi z-index di dalam blok
 * yang selektornya menyebut popper. Parser CSS penuh akan menambah satu
 * ketergantungan demi ketelitian yang tak dipakai pertanyaan ini.
 */
function blokCss(teks) {
    const bersih = teks.replace(/\/\*[\s\S]*?\*\//g, '');
    const blok = [];
    const re = /([^{}]+)\{([^{}]*)\}/g;
    let m;
    while ((m = re.exec(bersih)) !== null) {
        blok.push({ selektor: m[1].trim(), isi: m[2] });
    }

    return blok;
}

describe('element-plus-bridge.css — ketinggian lapisan mengambang', () => {
    const isi = readFileSync(BERKAS, 'utf8');
    const blok = blokCss(isi);

    it('tidak memaku z-index pada popper Element mana pun', () => {
        const pelanggar = blok
            .filter((b) => POPPER_ELEMENT.some((k) => b.selektor.includes(k)))
            .filter((b) => /(^|[;\s])z-index\s*:/.test(b.isi))
            .map((b) => b.selektor.replace(/\s+/g, ' ').slice(0, 160));

        expect(
            pelanggar,
            'Blok berikut memaku z-index pada popper Element. Serahkan urutannya '
                + 'ke pencacah Element (nilai inline), atau dropdown di dalam laci '
                + 'akan tenggelam di belakang lacinya:\n  - '
                + pelanggar.join('\n  - '),
        ).toEqual([]);
    });

    /* UJI PENOLAKAN. Tanpa ini, pemecah blok yang mengembalikan larik kosong
       pun lulus — dan penjaganya berhenti menjaga apa pun tanpa ada yang tahu. */
    it('pemecah bloknya benar-benar melihat isi berkas', () => {
        expect(blok.length).toBeGreaterThan(200);
        expect(blok.some((b) => b.selektor.includes('el-select__popper'))).toBe(true);
    });

    it('menangkap pakuan bila kelak dipasang ulang', () => {
        const palsu = blokCss('.el-select__popper { z-index: 3000 !important; }');
        const pelanggar = palsu
            .filter((b) => POPPER_ELEMENT.some((k) => b.selektor.includes(k)))
            .filter((b) => /(^|[;\s])z-index\s*:/.test(b.isi));

        expect(pelanggar).toHaveLength(1);
    });
});
