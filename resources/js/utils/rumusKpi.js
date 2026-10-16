/**
 * Evaluasi rumus KPI di sisi klien — cermin KpiFormulaHelper.php.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * Lima tempat di halaman KPI menyerahkan rumus dari basis data langsung ke
 * `new Function(...)` dan menjalankannya di browser SETIAP pengguna, TANPA
 * validasi apa pun:
 *
 *     return new Function(`… return (${formulaWithActual}); `)()
 *
 * Sisi server sudah memutuskan bahwa ini butuh daftar putih — KpiFormulaHelper
 * memeriksa bentuknya lewat bentukAman() sebelum eval(). Sisi klien
 * melewatinya sepenuhnya, jadi aturannya cuma berlaku di satu dari dua pintu.
 *
 * Rumusnya ditulis admin dan disimpan di basis data. Tanpa penjaga, siapa pun
 * yang bisa menyunting rumus KPI mendapat eksekusi JavaScript pada browser
 * setiap orang yang membuka KPI itu.
 *
 * ── Daftar putih ini HARUS sama dengan KpiFormulaHelper.php ─────────────────
 *
 * Klien yang lebih longgar membuka kembali apa yang ditutup server; klien yang
 * lebih ketat diam-diam menolkan rumus yang sah — dan capaian KPI orang ikut
 * nol. Paritasnya dijaga uji yang membaca berkas PHP-nya langsung.
 */

/**
 * Kata yang boleh muncul di rumus — persis yang disediakan sandbox di bawah.
 * Menambah entri di sini tanpa menambahkannya di sandbox membuat kata itu lolos
 * sebagai identifier global apa adanya.
 */
export const FUNGSI_DIIZINKAN = ['MIN', 'MAX', 'ABS', 'ROUND', 'CEIL', 'FLOOR', 'SQRT', 'POW', 'IF']

/**
 * Karakter yang boleh tersisa setelah nama fungsi yang diizinkan dibuang.
 *
 * `e` dan `E` ada di sini BUKAN karena longgar melainkan karena wajib:
 * substitusi memakai nilai numerik apa adanya, dan JavaScript merender pecahan
 * sangat kecil dalam notasi ilmiah — String(0.0000001) menghasilkan '1e-7'.
 * Daftar putih yang lupa mengizinkannya akan diam-diam menolak justru
 * nilai-nilai terkecil. Huruf `e` sendirian tidak membentuk nama fungsi mana
 * pun, dan tanpa huruf lain tidak ada identifier yang bisa dirujuk.
 *
 * Sama persis dengan KpiFormulaHelper::KARAKTER_DIIZINKAN.
 */
export const POLA_KARAKTER_TERLARANG = /[^0-9.eE\s+\-*/(),<>=!]/

/**
 * Apakah ekspresi (yang nilainya SUDAH disubstitusi) aman dieksekusi?
 *
 * Cerminan bentukAman() di PHP, termasuk urutannya: nama fungsi yang diizinkan
 * dibuang lebih dulu, lalu sisanya diperiksa karakter demi karakter.
 */
export function bentukAman(ekspresi) {
  const teks = String(ekspresi ?? '')
  if (teks.trim() === '') {
    return false
  }

  const sisa = teks.replace(new RegExp('\\b(' + FUNGSI_DIIZINKAN.join('|') + ')\\b', 'gi'), '')

  /*
   * SATU ATURAN YANG TIDAK ADA DI SISI PHP, DAN MEMANG TIDAK BOLEH ADA DI SANA.
   *
   * Daftar karakter di atas harus mengizinkan '=' dan '>' supaya '>=' dan
   * '<=' bisa dipakai. Di PHP itu aman: menulis fungsi anonim di sana butuh
   * huruf ('fn' atau 'function'), dan huruf sudah ditolak. Di JavaScript
   * tidak:
   *
   *     (() => 1)()
   *
   * seluruhnya tersusun dari karakter yang diizinkan, tanpa satu huruf pun.
   * Terbukti lolos saat uji pertama penjaga ini dijalankan.
   *
   * Karena itu klien HARUS lebih ketat daripada server di titik ini.
   * Menyalin aturan PHP mentah-mentah justru memindahkan lubang yang di
   * sana tidak ada.
   */
  if (sisa.includes('=>')) {
    return false
  }

  return ! POLA_KARAKTER_TERLARANG.test(sisa)
}

/**
 * Hitung rumus yang nilainya sudah disubstitusi; null bila tidak aman atau
 * tidak menghasilkan angka.
 *
 * NULL, bukan lemparan: pemanggil yang ada sudah menurunkan hasil non-numerik
 * menjadi nol di dalam try/catch-nya, jadi null mendarat tepat di perilaku yang
 * sudah berlaku hari ini.
 */
export function hitungRumus(ekspresi) {
  if (! bentukAman(ekspresi)) {
    return null
  }

  try {
    /*
     * Sandbox yang sama dengan kelima salinan lama — sembilan fungsi ini, dan
     * tidak lebih. Daftarnya harus sejalan dengan FUNGSI_DIIZINKAN di atas.
     */
    const hasil = new Function(`
      const IF = (c, a, b) => (c ? a : b);
      const MIN = (...args) => Math.min(...args);
      const MAX = (...args) => Math.max(...args);
      const ABS = (x) => Math.abs(x);
      const ROUND = (x, d) => d !== undefined ? parseFloat(x.toFixed(+d)) : Math.round(x);
      const CEIL = (x) => Math.ceil(x);
      const FLOOR = (x) => Math.floor(x);
      const SQRT = (x) => Math.sqrt(x);
      const POW = (x, y) => Math.pow(x, y);
      return (${ekspresi});
    `)()

    return typeof hasil === 'number' && Number.isFinite(hasil) ? hasil : null
  } catch {
    return null
  }
}
