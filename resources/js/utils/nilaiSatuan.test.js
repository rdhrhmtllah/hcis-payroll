import { describe, expect, it } from 'vitest'

import {
  adalahMataUang,
  bersihkanSatuan,
  escapeHtml,
  formatAngka,
  formatNilaiSatuan,
  formatNilaiSatuanSorot,
} from './nilaiSatuan'

/**
 * Aturan "angka + satuan" pada layar KPI.
 *
 * Yang paling penting di berkas ini bukan pemformatannya, melainkan bahwa
 * bentuk HTML-nya meng-escape NAMA SATUAN. Satuan berasal dari Simbol_Satuan
 * pada master satuan KPI -- bisa disunting admin -- dan hasilnya disisipkan ke
 * DOM lewat v-html, jadi tanpa escape ia dieksekusi sebagai markup.
 *
 * ── Batas yang jujur ────────────────────────────────────────────────────────
 *
 * Cabang MATA UANG tidak bisa diuji dengan markup, dan itu bukan kelalaian
 * ujinya: cabang itu hanya tercapai bila satuannya persis 'rp' atau 'idr'
 * setelah dipangkas dan dikecilkan. Satuan bermarkup selalu jatuh ke cabang
 * satuan biasa. Saya membuktikannya dengan mencabut escape di cabang mata uang
 * -- seluruh uji tetap HIJAU. Jadi yang menjaga cabang itu adalah DAFTAR
 * PUTIHnya, bukan escape-nya; escape di sana hanya lapis kedua yang murah.
 *
 * Yang benar-benar dijaga uji di bawah adalah cabang satuan biasa, satu-satunya
 * tempat nilai bebas dari basis data masuk ke keluaran HTML. Mencabut escape di
 * sana membuat tiga uji merah.
 */
describe('formatNilaiSatuan (teks polos)', () => {
  it('menaruh satuan mata uang di depan angka', () => {
    expect(formatNilaiSatuan(1000, 'Rp')).toBe('Rp 1.000')
  })

  it('menaruh satuan biasa di belakang angka', () => {
    expect(formatNilaiSatuan(12, 'unit')).toBe('12 unit')
  })

  it('mengembalikan null untuk nilai yang tidak ada', () => {
    expect(formatNilaiSatuan(null, 'Rp')).toBeNull()
    expect(formatNilaiSatuan(undefined, 'Rp')).toBeNull()
  })

  it('nol tetap diformat, bukan dianggap tidak ada', () => {
    expect(formatNilaiSatuan(0, 'unit')).toBe('0 unit')
  })
})

describe('aturan yang tadinya berbeda-beda antar komponen', () => {
  it('mengenali idr sebagai mata uang, bukan hanya rp', () => {
    expect(adalahMataUang('IDR')).toBe(true)
    expect(adalahMataUang('idr')).toBe(true)
    expect(adalahMataUang('Rp')).toBe(true)
    expect(adalahMataUang('kg')).toBe(false)
  })

  it('memangkas spasi di sekitar satuan', () => {
    expect(bersihkanSatuan('  Rp  ')).toBe('Rp')
    expect(adalahMataUang('  rp ')).toBe(true)
  })

  it('satuan yang bukan string dianggap tidak ada', () => {
    expect(bersihkanSatuan(null)).toBe('')
    expect(bersihkanSatuan(42)).toBe('')
    expect(formatNilaiSatuan(5, null)).toBe('5 ')
  })
})

describe('formatNilaiSatuanSorot (dirender lewat v-html)', () => {
  it('membungkus mata uang dengan kelas sorotan', () => {
    expect(formatNilaiSatuanSorot(1000, 'Rp')).toBe('<span class="currency-lola">Rp 1.000</span>')
  })

  /* Inilah alasan berkas ini ada: satuan bermarkup jatuh ke cabang satuan biasa. */
  it('meng-escape markup di dalam nama satuan', () => {
    const hasil = formatNilaiSatuanSorot(1000, '<img src=x onerror=alert(1)>')

    expect(hasil).not.toContain('<img')
    expect(hasil).toContain('&lt;img')
  })

  it('meng-escape juga pada cabang satuan biasa', () => {
    const hasil = formatNilaiSatuanSorot(7, '<b>kg</b>')

    expect(hasil).not.toContain('<b>')
    expect(hasil).toContain('&lt;b&gt;')
  })

  it('tidak bisa dipakai memecah atribut kelasnya sendiri', () => {
    const hasil = formatNilaiSatuanSorot(1, 'rp')

    expect(hasil).toBe('<span class="currency-lola">rp 1</span>')
    expect(formatNilaiSatuanSorot(1, '" onmouseover="x')).not.toContain('onmouseover="x')
  })

  it('mengembalikan null untuk nilai yang tidak ada', () => {
    expect(formatNilaiSatuanSorot(null, 'Rp')).toBeNull()
  })
})

describe('formatAngka', () => {
  it('memakai pemisah ribuan Indonesia', () => {
    expect(formatAngka(1234567)).toBe('1.234.567')
  })

  it('nilai yang tidak ada menjadi teks kosong', () => {
    expect(formatAngka(null)).toBe('')
  })
})
