import { readFileSync } from 'node:fs'

import { describe, expect, it } from 'vitest'

import { ATRIBUT_DIIZINKAN, sanitasiSop, sanitasiSopAtau, TAG_DIIZINKAN } from './sopHtml'

/**
 * Sanitasi HTML SOP: satu rumah, dan sejalan dengan penyaring di server.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * Daftar putih yang sama persis ditulis TIGA kali di komponen Vue (SopDetail,
 * LangkahReview, SopPreviewItem), sementara permukaan keempat —
 * SopVersiDetailModal — merender `Hasil_Review` lewat v-html TANPA sanitasi
 * sama sekali. Field yang sama disanitasi di satu layar dan mentah di layar
 * lain.
 *
 * ── Tingkat gawatnya, apa adanya ────────────────────────────────────────────
 *
 * SopVersiReviewService memanggil SopHtml::sanitize() saat MENYIMPAN, jadi isi
 * yang ditulis lewat aplikasi memang sudah bersih — ini lapisan kedua, bukan
 * satu-satunya. Yang ditutup adalah persis risiko yang sudah disebut sendiri
 * oleh LangkahReview.vue: isi tersimpan bisa berasal dari jalur lain (seeder,
 * impor, perbaikan data lewat SQL, jalur kode lama). Jadi ini pertahanan
 * berlapis yang bolong di satu titik, bukan celah yang terbukti sedang
 * dieksploitasi.
 */
describe('sanitasiSop', () => {
  it('membuang script beserta isinya', () => {
    const hasil = sanitasiSop('<p>Aman</p><script>alert(1)</script>')

    expect(hasil).toContain('Aman')
    expect(hasil).not.toContain('script')
    expect(hasil).not.toContain('alert')
  })

  it('membuang atribut event handler', () => {
    const hasil = sanitasiSop('<p onclick="alert(1)">Teks</p>')

    expect(hasil).toContain('Teks')
    expect(hasil).not.toContain('onclick')
  })

  it('membuang iframe dan objek tersemat', () => {
    for (const jahat of [
      '<iframe src="https://jahat.test"></iframe>',
      '<object data="x"></object>',
      '<embed src="x">',
    ]) {
      const hasil = sanitasiSop('<p>ok</p>' + jahat)
      expect(hasil).toContain('ok')
      expect(hasil.toLowerCase()).not.toMatch(/iframe|object|embed/)
    }
  })

  it('mempertahankan tag kaya yang memang diizinkan', () => {
    const hasil = sanitasiSop(
      '<p><strong>tebal</strong> <em>miring</em></p><ul><li>satu</li></ul><blockquote>kutipan</blockquote>',
    )

    for (const tag of ['strong', 'em', 'ul', 'li', 'blockquote']) {
      expect(hasil).toContain('<' + tag)
    }
  })

  it('mengembalikan string kosong untuk nilai kosong, tanpa melempar', () => {
    for (const kosong of ['', '   ', null, undefined]) {
      expect(sanitasiSop(kosong)).toBe('')
    }
    expect(() => sanitasiSop({})).not.toThrow()
  })
})

describe('sanitasiSopAtau', () => {
  it('memakai penanda kosong hanya bila isinya benar-benar kosong', () => {
    expect(sanitasiSopAtau('', '<p>kosong</p>')).toBe('<p>kosong</p>')
    expect(sanitasiSopAtau(null, '<p>kosong</p>')).toBe('<p>kosong</p>')
    expect(sanitasiSopAtau('<p>ada</p>', '<p>kosong</p>')).toContain('ada')
  })

  it('penanda kosong milik komponen, bukan milik aturan sanitasi', () => {
    /*
     * Ketiga komponen yang digantikan punya kalimat kosongnya masing-masing.
     * Memaksanya seragam akan mengubah apa yang dibaca pengguna, jadi ia
     * diserahkan sebagai argumen.
     */
    expect(sanitasiSopAtau('', 'A')).toBe('A')
    expect(sanitasiSopAtau('', 'B')).toBe('B')
  })
})

describe('paritas dengan penyaring server', () => {
  it('daftar tag JS sama persis dengan SopHtml::ALLOWED_TAGS di PHP', () => {
    /*
     * Server memutuskan apa yang boleh TERSIMPAN; klien memutuskan apa yang
     * boleh TAMPIL. Klien yang lebih longgar membuka kembali apa yang sudah
     * ditutup server; klien yang lebih ketat diam-diam menghapus isi yang sah.
     * Uji ini membaca berkas PHP-nya langsung supaya paritasnya tidak bisa
     * menyimpang tanpa ketahuan.
     */
    const php = readFileSync('app/Services/LMS/SOP/Support/SopHtml.php', 'utf8')
    const blok = php.match(/private const ALLOWED_TAGS = \[([\s\S]*?)\];/)

    expect(blok, 'ALLOWED_TAGS tidak ditemukan di SopHtml.php').not.toBeNull()

    const tagPhp = [...blok[1].matchAll(/'([^']+)'/g)].map((m) => m[1])

    expect([...tagPhp].sort()).toEqual([...TAG_DIIZINKAN].sort())
  })

  it('daftar atribut tidak mengizinkan penangan event', () => {
    for (const attr of ATRIBUT_DIIZINKAN) {
      expect(attr.toLowerCase().startsWith('on')).toBe(false)
    }
  })
})
