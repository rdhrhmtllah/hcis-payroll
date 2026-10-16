import { onUnmounted } from 'vue';

/**
 * Menyalakan jembatan gaya Element Plus untuk halaman yang memanggilnya.
 *
 * Selama ini seluruh isi `element-plus-bridge.css` berlingkup `.ds-preview`,
 * jadi ia hanya berlaku di `/design-system`. Di 88 halaman nyata, 971 komponen
 * Element tampil dengan gaya bawaannya sendiri — tidak mengenal token kita,
 * tidak ikut berganti saat tema berganti.
 *
 * Melepas lingkupnya sekaligus akan mengubah rupa 88 halaman dalam satu
 * langkah, tanpa satu pun yang sempat diperiksa. Jadi perpindahannya dibuat
 * per halaman: halaman yang sudah diperiksa memanggil composable ini, sisanya
 * belum berubah sama sekali.
 *
 * ## Kenapa penandanya di <html>, bukan di akar halaman
 *
 * 61 dari 176 selektor jembatan menyasar elemen yang di-TELEPORT Element ke
 * `document.body` — dropdown select, popper tanggal, message, tirai laci.
 * Elemen-elemen itu berada di LUAR pohon halaman, jadi penanda di akar halaman
 * tidak akan pernah menjangkaunya: isian-nya tergaya, tapi dropdown yang
 * terbuka darinya tidak. `<html>` adalah leluhur semuanya, termasuk yang
 * ter-teleport.
 *
 * ## Dihitung, bukan disetel
 *
 * Satu halaman bisa punya beberapa komponen yang memanggilnya. Kalau tiap
 * pemanggil menyetel dan menghapus atributnya sendiri, komponen pertama yang
 * dilepas mematikan jembatan untuk seluruh halaman yang masih tampil.
 */

const ATRIBUT = 'data-hcis-el';

let jumlahPemakai = 0;

function akar() {
    return typeof document === 'undefined' ? null : document.documentElement;
}

export function useElementBridge() {
    let terpasang = false;

    const pasang = () => {
        if (terpasang) return;
        terpasang = true;
        jumlahPemakai += 1;
        if (jumlahPemakai === 1) akar()?.setAttribute(ATRIBUT, '');
    };

    const lepas = () => {
        if (!terpasang) return;
        terpasang = false;
        jumlahPemakai -= 1;
        if (jumlahPemakai === 0) akar()?.removeAttribute(ATRIBUT);
    };

    pasang();

    /*
     * Dilepas saat komponennya dilepas. Penting untuk navigasi Inertia: kalau
     * atributnya tertinggal, halaman BERIKUTNYA yang belum diperiksa ikut
     * memakai jembatan — perpindahan yang justru sedang kita hindari.
     */
    onUnmounted(lepas);

    return { pasang, lepas };
}

/** Hanya untuk pengujian. */
export function _jumlahPemakaiJembatan() {
    return jumlahPemakai;
}
