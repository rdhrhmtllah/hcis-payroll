/**
 * Satu rumah untuk "angka + satuan" pada layar KPI.
 *
 * ── Kenapa dipusatkan ───────────────────────────────────────────────────────
 *
 * Aturan ini tersalin di ENAM komponen dan ketiganya menyimpang:
 *
 *   satuan     lima memakai `unit || ''`, satu memangkas spasi dan menjaga tipe
 *   mata uang  lima hanya mengenali 'rp', satu juga mengenali 'idr'
 *   bungkus    tiga mengembalikan teks polos, tiga membungkus <span class="currency-lola">
 *
 * Akibatnya satuan yang sama tampil berbeda tergantung layar mana yang dibuka.
 *
 * ── Kenapa ada dua bentuk keluaran ──────────────────────────────────────────
 *
 * Tiga komponen merender hasilnya lewat interpolasi `{{ }}` (aman), tiga lagi
 * lewat `v-html`. Yang lewat v-html menyisipkan NAMA SATUAN dari basis data --
 * Simbol_Satuan pada master satuan KPI, yang bisa disunting admin -- langsung ke
 * dalam HTML. Satuan bernama `<img src=x onerror=...>` akan dieksekusi.
 *
 * Karena itu bentuk HTML-nya meng-escape satuannya, dan bentuk teks polos tetap
 * disediakan supaya komponen yang tidak butuh bungkus tidak perlu memakai
 * v-html sama sekali.
 */

import { escapeHtml } from './html'

/** Satuan yang ditulis di depan angka, seperti mata uang. */
const MATA_UANG = ['rp', 'idr']

/** Normalkan satuan: apa pun yang bukan string dianggap tidak ada. */
export function bersihkanSatuan(unit) {
  return typeof unit === 'string' ? unit.trim() : ''
}

export function adalahMataUang(unit) {
  return MATA_UANG.includes(bersihkanSatuan(unit).toLowerCase())
}

export function formatAngka(value) {
  if (value === null || value === undefined) return ''

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Bentuk TEKS POLOS. Aman dirender lewat interpolasi biasa.
 *
 * @returns {string|null} null bila nilainya tidak ada — sama seperti sebelumnya,
 *   supaya pemanggil yang memeriksa null tidak berubah perilakunya.
 */
export function formatNilaiSatuan(value, unit) {
  if (value === null || value === undefined) return null

  const satuan = bersihkanSatuan(unit)
  const angka = formatAngka(value)

  return adalahMataUang(satuan) ? `${satuan} ${angka}` : `${angka} ${satuan}`
}

/**
 * Bentuk HTML dengan sorotan mata uang, untuk pemanggil yang memakai v-html.
 *
 * Satuannya di-escape: ia berasal dari master yang bisa disunting admin, dan
 * hasilnya disisipkan ke DOM tanpa melewati interpolasi Vue.
 */
export function formatNilaiSatuanSorot(value, unit) {
  if (value === null || value === undefined) return null

  const satuan = bersihkanSatuan(unit)
  const angka = formatAngka(value)

  if (! adalahMataUang(satuan)) {
    return `${escapeHtml(angka)} ${escapeHtml(satuan)}`
  }

  return `<span class="currency-lola">${escapeHtml(satuan)} ${escapeHtml(angka)}</span>`
}
