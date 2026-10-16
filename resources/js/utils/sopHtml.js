import DOMPurify from 'dompurify'

/**
 * Sanitasi HTML kaya milik SOP — cermin sisi klien dari SopHtml.php.
 *
 * ── Kenapa ada sanitasi di klien padahal server sudah menyaring ─────────────
 *
 * `SopVersiReviewService` memanggil `SopHtml::sanitize()` saat MENYIMPAN, jadi
 * isi yang ditulis lewat aplikasi memang sudah bersih. Sanitasi ulang saat
 * membaca tetap dipertahankan dengan alasan yang sudah ditulis di
 * LangkahReview.vue dan masih berlaku: yang tersimpan bisa berasal dari jalur
 * lain — seeder, impor, perbaikan data langsung lewat SQL, atau jalur kode
 * lama — dan `v-html` atas isi yang dipercaya begitu saja adalah cara paling
 * murah membuka celah.
 *
 * ── Kenapa dipusatkan ───────────────────────────────────────────────────────
 *
 * Daftar putih yang sama persis ditulis TIGA kali di komponen Vue
 * (SopDetail, LangkahReview, SopPreviewItem), dan satu permukaan keempat —
 * SopVersiDetailModal — merender `Hasil_Review` dengan `v-html` TANPA sanitasi
 * sama sekali. Jadi field yang sama disanitasi di satu layar dan mentah di
 * layar lain.
 *
 * Empat salinan aturan berarti pengetatan berikutnya hanya sampai ke sebagian.
 *
 * ── Daftar putih ini HARUS sama dengan SopHtml.php ──────────────────────────
 *
 * Server memutuskan apa yang boleh tersimpan; klien memutuskan apa yang boleh
 * tampil. Bila keduanya berbeda, yang longgar di klien membuka kembali apa yang
 * sudah ditutup server, dan yang lebih ketat di klien diam-diam menghapus isi
 * yang sah. Paritasnya dijaga uji (sopHtml.test.js) yang membaca kedua berkas.
 */

/** Sama persis dengan SopHtml::ALLOWED_TAGS di PHP. */
export const TAG_DIIZINKAN = [
  'p',
  'br',
  'b',
  'strong',
  'i',
  'em',
  'u',
  's',
  'h1',
  'h2',
  'h3',
  'ul',
  'ol',
  'li',
  'blockquote',
  'a',
  'span',
]

/** Atribut yang boleh bertahan; sama dengan ketiga salinan yang digantikan. */
export const ATRIBUT_DIIZINKAN = ['href', 'target', 'rel', 'class', 'style']

/**
 * Bersihkan HTML kaya SOP untuk ditampilkan lewat v-html.
 *
 * Selalu mengembalikan string — tidak pernah null dan tidak pernah melempar —
 * supaya pemanggil di dalam computed Vue tidak perlu menjaga apa pun.
 */
export function sanitasiSop(html) {
  const isi = String(html ?? '').trim()

  /*
   * Early-return ini REDUNDAN dan dipertahankan sebagai kejelasan, bukan
   * sebagai penjaga: DOMPurify.sanitize('') juga mengembalikan ''. Dicatat
   * supaya pembaca berikutnya tidak mengira ia menanggung sesuatu — yang
   * benar-benar menanggung adalah trim() di atas, dan itu yang diuji.
   */
  if (isi === '') {
    return ''
  }

  return DOMPurify.sanitize(isi, {
    ALLOWED_TAGS: TAG_DIIZINKAN,
    ALLOWED_ATTR: ATRIBUT_DIIZINKAN,
  })
}

/**
 * Seperti sanitasiSop, tetapi mengembalikan penanda kosong bila tak ada isi.
 *
 * Ketiga komponen yang digantikan masing-masing punya kalimat kosongnya
 * sendiri, dan kalimat itu MILIK komponennya — bukan milik aturan sanitasi.
 * Karena itu ia diserahkan sebagai argumen, bukan dipaksa seragam.
 */
export function sanitasiSopAtau(html, penandaKosong) {
  const bersih = sanitasiSop(html)

  return bersih === '' ? penandaKosong : bersih
}
