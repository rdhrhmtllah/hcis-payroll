/**
 * Angka dalam bentuk yang dibaca orang Indonesia: 3.582, bukan 3582.
 *
 * ----------------------------------------------------------------------------------------
 * KENAPA INI BARU LAHIR SEKARANG
 *
 * Fungsi ini sudah ada enam belas kali di modul Performance Appraisal — satu salinan di
 * setiap berkas yang menampilkan angka, dengan tiga nama parameter berbeda (`value`, `num`,
 * `n`) dan badan yang persis sama. Selama itu tidak ada yang rusak, karena keenam belasnya
 * memang identik.
 *
 * Yang rusak adalah pemakai KETUJUH BELAS. `siklus.vue` memanggil `formatAngka()` di lima
 * tempat dan tidak pernah menyalin badannya, dan akibatnya bukan angka yang salah format:
 * seluruh halaman Daftar Siklus gagal dirender dengan `_ctx.formatAngka is not a function`.
 * Template Vue tidak diperiksa saat dibangun, jadi pemanggilan ke fungsi yang tidak ada lolos
 * sampai ke layar admin dan mendarat sebagai halaman kosong.
 *
 * Itulah sifat yang membuat salin-tempel berbahaya di sini. Bahayanya bukan salinan yang
 * menyimpang — enam belas salinan itu tidak menyimpang sama sekali. Bahayanya adalah salinan
 * yang TIDAK DIBUAT, dan tidak ada satu pun alat di jalur ini yang akan menyebutkannya.
 *
 * Keenam belas salinan lama sengaja DIBIARKAN untuk sekarang: memindahkannya menyentuh
 * enam belas berkas di empat submodul sekaligus, dan itu pekerjaan tersendiri yang pantas
 * punya persetujuan sendiri. Yang penting sekarang rumahnya sudah ada, dan pemakai baru
 * mengimpor alih-alih menyalin.
 *
 * @param {number|string|null|undefined} nilai
 * @returns {string} angka berpemisah ribuan; '0' untuk nilai kosong atau bukan angka
 */
export function formatAngka(nilai) {
    return Number(nilai || 0).toLocaleString('id-ID');
}

export default formatAngka;
