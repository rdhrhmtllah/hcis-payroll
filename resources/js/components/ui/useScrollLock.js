import { onUnmounted } from 'vue';

/**
 * Kunci gulir halaman untuk lapisan menutup layar (dialog, laci, penampil berkas).
 *
 * Sebelumnya tiap komponen menyetel `document.body.style.overflow` sendiri.
 * Itu benar selama hanya satu lapisan terbuka — dan salah begitu ada dua,
 * yang di aplikasi ini justru lumrah: penampil berkas dibuka dari dalam
 * dialog rincian, laci konfirmasi dibuka di atas laci lain.
 *
 * Yang terjadi: lapisan kedua ditutup → ia menyetel overflow kembali kosong →
 * halaman di belakang lapisan PERTAMA yang masih terbuka ikut bisa digulir.
 * Isinya bergeser di balik tirai, dan saat lapisan pertama ditutup pengguna
 * mendapati dirinya di tempat lain.
 *
 * Karena itu kuncinya dihitung, bukan disetel. Halaman baru terbuka kembali
 * setelah lapisan TERAKHIR melepasnya.
 */

let jumlahKunci = 0;

/**
 * Nilai overflow milik halaman sebelum kunci pertama.
 *
 * Disimpan, bukan diasumsikan kosong: ada halaman warisan yang memang
 * menyetel overflow body-nya sendiri, dan mengembalikannya ke '' akan
 * merusak halaman itu — kerusakan yang baru muncul setelah dialog ditutup,
 * jadi hampir mustahil dihubungkan dengan penyebabnya.
 */
let overflowAsli = null;

function kunciGlobal() {
    if (typeof document === 'undefined') return;

    if (jumlahKunci === 0) {
        overflowAsli = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    }

    jumlahKunci += 1;
}

function bukaGlobal() {
    if (typeof document === 'undefined') return;

    // Pembukaan berlebih diabaikan. Tanpa penjaga ini, satu komponen yang
    // membuka dua kali akan membuat penghitungnya negatif dan lapisan lain
    // yang masih terbuka kehilangan kuncinya.
    if (jumlahKunci === 0) return;

    jumlahKunci -= 1;

    if (jumlahKunci === 0) {
        document.body.style.overflow = overflowAsli ?? '';
        overflowAsli = null;
    }
}

/**
 * Satu pemakai = paling banyak satu kunci, berapa kali pun `kunci()` dipanggil.
 * Dilepas otomatis saat komponennya dilepas, supaya komponen yang hilang
 * selagi terbuka tidak meninggalkan halaman yang tak bisa digulir selamanya.
 */
export function useScrollLock() {
    let terkunci = false;

    const kunci = () => {
        if (terkunci) return;
        terkunci = true;
        kunciGlobal();
    };

    const buka = () => {
        if (!terkunci) return;
        terkunci = false;
        bukaGlobal();
    };

    onUnmounted(buka);

    return { kunci, buka };
}

/** Hanya untuk pengujian: keadaan penghitung bersama. */
export function _keadaanKunci() {
    return { jumlahKunci, overflowAsli };
}
