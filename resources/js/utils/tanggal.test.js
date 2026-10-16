import { describe, expect, it } from 'vitest'

import { tanggalIso, tanggalPanjang, tanggalWaktu } from './tanggal'

/**
 * Pemformat tanggal: satu rumah, dan tak pernah melempar ke render.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * "Format sebuah tanggal" tersalin 21 kali di halaman Vue, dalam DUA varian
 * yang BERNAMA SAMA (`formatDateString`) tetapi berbeda perilakunya pada
 * masukan tak terurai: 16 salinan membungkusnya dengan try/catch dan
 * mengembalikan teks aslinya, 5 salinan tidak — dan Intl memang melempar
 * RangeError. Di dalam render Vue, lemparan itu menghapus seluruh panel,
 * bukan satu selnya.
 *
 * Yang dijaga di sini bukan gaya kode melainkan tiga sifat yang bisa
 * menghilang tanpa terlihat.
 */
describe('tanggalPanjang', () => {
  it('memformat tanggal sah dalam bahasa Indonesia', () => {
    expect(tanggalPanjang('2026-09-08')).toBe('8 September 2026')
  })

  it('mengembalikan string kosong untuk nilai kosong', () => {
    /*
     * Penjagaan di depan ini bukan hiasan: new Date(null) diam-diam bernilai
     * 1 Januari 1970, jadi tanpa penjagaan itu kolom kosong akan tampil
     * sebagai tanggal yang tampak sah.
     */
    for (const kosong of [null, undefined, '', 0]) {
      expect(tanggalPanjang(kosong)).toBe('')
    }
  })

  it('mengembalikan teks aslinya, BUKAN melempar, untuk tanggal tak terurai', () => {
    expect(() => tanggalPanjang('bukan-tanggal')).not.toThrow()
    expect(tanggalPanjang('bukan-tanggal')).toBe('bukan-tanggal')
    expect(tanggalPanjang('0000-00-00')).toBe('0000-00-00')
  })
})

describe('tanggalWaktu', () => {
  it('memformat tanggal beserta jamnya', () => {
    const hasil = tanggalWaktu('2026-09-08T14:30:00')
    expect(hasil).toContain('8 Sep 2026')
    expect(hasil).toMatch(/14[.:]30/)
  })

  it("mengembalikan 'N/A' untuk nilai kosong, bukan string kosong", () => {
    /*
     * Berbeda dari tanggalPanjang, dan itu DISENGAJA: ketujuh salinan aslinya
     * memakai 'N/A'. Menyeragamkannya mengubah apa yang dibaca pengguna.
     */
    for (const kosong of [null, undefined, '']) {
      expect(tanggalWaktu(kosong)).toBe('N/A')
    }
  })

  it('tidak melempar untuk tanggal tak terurai', () => {
    expect(() => tanggalWaktu('bukan-tanggal')).not.toThrow()
    expect(tanggalWaktu('bukan-tanggal')).toBe('bukan-tanggal')
  })
})

describe('tanggalIso', () => {
  it('membangun YYYY-MM-DD dengan padding', () => {
    expect(tanggalIso(new Date(2026, 0, 5))).toBe('2026-01-05')
    expect(tanggalIso(new Date(2026, 11, 31))).toBe('2026-12-31')
  })

  it('memakai tanggal LOKAL, bukan UTC', () => {
    /*
     * Sifat yang paling mudah hilang saat seseorang "menyederhanakannya"
     * menjadi toISOString().slice(0, 10): toISOString mengubah ke UTC lebih
     * dulu, sehingga pukul 23.30 di Asia/Jakarta (UTC+7) mundur menjadi
     * tanggal SEBELUMNYA. Nilai ini dikirim ke server sebagai tanggal
     * pengajuan — mundur satu hari berarti pengajuan untuk hari yang salah.
     */
    const malam = new Date(2026, 8, 8, 23, 30, 0)
    expect(tanggalIso(malam)).toBe('2026-09-08')

    const pagi = new Date(2026, 8, 8, 0, 30, 0)
    expect(tanggalIso(pagi)).toBe('2026-09-08')
  })

  it('menerima string maupun objek Date', () => {
    expect(tanggalIso('2026-09-08T10:00:00')).toBe('2026-09-08')
  })

  it('mengembalikan string kosong untuk nilai kosong atau tak terurai', () => {
    for (const buruk of [null, undefined, '', 'bukan-tanggal']) {
      expect(tanggalIso(buruk)).toBe('')
    }
  })
})
