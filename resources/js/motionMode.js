/**
 * Mode gerak HCIS.
 *
 * Aplikasi ini SENGAJA tidak lagi menuruti `prefers-reduced-motion` dari sistem
 * operasi. Alasannya bukan mengabaikan aksesibilitas, tapi karena sinyal itu
 * ternyata pemicu yang buruk untuk aplikasi internal: ia ikut menyala oleh mode
 * hemat baterai, profil performa Windows, dan setelan yang pernah diubah
 * bertahun lalu lalu dilupakan. Yang terjadi berulang kali di lapangan adalah
 * orang menerima aplikasi yang terasa mati tanpa pernah merasa memintanya, dan
 * tanpa satu pun jalan untuk mengembalikannya dari dalam aplikasi.
 *
 * Gantinya: saklar milik HCIS sendiri. Baku-nya PENUH untuk semua orang, dan
 * yang memang ingin gerak lebih tenang menyalakannya sendiri — pilihannya
 * diingat lintas kunjungan.
 *
 * Satu-satunya yang boleh menulis atribut ini adalah berkas ini.
 */

import { readonly, ref } from 'vue';

const KUNCI = 'hcis:motion';
const ATRIBUT = 'data-hcis-motion';

/** Nilai yang sah. 'full' diwakili oleh TIDAK ADANYA atribut, bukan oleh string. */
export const MODE_PENUH = 'full';
export const MODE_TENANG = 'reduced';

/*
 * Cermin reaktif dari atribut di <html>.
 *
 * Perlu, karena mode ini diubah dari satu tempat tapi terasa di seluruh
 * aplikasi. Tanpa satu sumber yang diamati bersama, saklar di satu layar dan
 * penanda di layar lain akan menampilkan keadaan yang berbeda — dan penanda
 * yang berbohong lebih buruk daripada tidak ada penanda sama sekali.
 */
const modeAktif = ref(MODE_PENUH);

/** Hanya-baca: satu-satunya yang boleh mengubahnya adalah setelModeGerak(). */
export const modeGerakAktif = readonly(modeAktif);

function akar() {
    return typeof document === 'undefined' ? null : document.documentElement;
}

/** Mode yang sedang berlaku. Tanpa atribut berarti penuh. */
export function modeGerak() {
    const el = akar();
    return el && el.getAttribute(ATRIBUT) === MODE_TENANG ? MODE_TENANG : MODE_PENUH;
}

/**
 * Terapkan mode dan ingat pilihannya.
 *
 * Mode penuh MENGHAPUS atributnya, bukan menyetelnya ke 'full'. Kalau ia
 * disetel ke string, setiap selektor `[data-hcis-motion]` tanpa nilai akan
 * ikut cocok — dan aturan mode tenang bocor ke pengguna yang justru memilih
 * gerak penuh.
 */
export function setelModeGerak(mode) {
    const el = akar();
    if (!el) return MODE_PENUH;

    const tenang = mode === MODE_TENANG;

    if (tenang) el.setAttribute(ATRIBUT, MODE_TENANG);
    else el.removeAttribute(ATRIBUT);

    modeAktif.value = tenang ? MODE_TENANG : MODE_PENUH;

    try {
        localStorage.setItem(KUNCI, tenang ? MODE_TENANG : MODE_PENUH);
    } catch {
        // Mode penyamaran / storage penuh. Pilihannya tetap berlaku untuk sesi
        // ini; hanya ingatannya yang hilang. Itu tidak layak melempar galat.
    }

    return tenang ? MODE_TENANG : MODE_PENUH;
}

/** Kebalikan dari mode yang sedang berlaku. */
export function balikModeGerak() {
    return setelModeGerak(modeGerak() === MODE_TENANG ? MODE_PENUH : MODE_TENANG);
}

/**
 * Dipanggil sekali saat aplikasi mulai.
 *
 * Sengaja TIDAK membaca matchMedia: kalau ia dipakai sebagai nilai awal,
 * pengguna dengan setelan OS menyala akan kembali menerima aplikasi yang
 * tenang tanpa pernah memilihnya — persis keadaan yang ditinggalkan.
 */
export function mulaiModeGerak() {
    let tersimpan = null;
    try {
        tersimpan = localStorage.getItem(KUNCI);
    } catch {
        tersimpan = null;
    }

    return setelModeGerak(tersimpan === MODE_TENANG ? MODE_TENANG : MODE_PENUH);
}
