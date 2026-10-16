import { readFileSync } from 'node:fs'

import { describe, expect, it } from 'vitest'

import { bentukAman, FUNGSI_DIIZINKAN, hitungRumus } from './rumusKpi'

/**
 * Rumus KPI: daftar putih di klien, sejalan dengan yang di server.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * Lima tempat di halaman KPI menyerahkan rumus dari basis data langsung ke
 * `new Function(...)` dan menjalankannya di browser SETIAP pengguna, tanpa
 * validasi apa pun. Sisi server sudah memutuskan bahwa ini butuh daftar putih —
 * KpiFormulaHelper::bentukAman() memeriksa bentuknya sebelum eval(). Klien
 * melewatinya sepenuhnya: aturan yang sama hanya berlaku di satu dari dua pintu.
 *
 * Rumusnya ditulis admin dan disimpan di basis data, jadi tanpa penjaga siapa
 * pun yang bisa menyunting rumus KPI mendapat eksekusi JavaScript pada browser
 * setiap orang yang membuka KPI itu.
 */
describe('bentukAman', () => {
  it('menerima rumus aritmetika biasa', () => {
    for (const r of ['(80/100)*100', '80 + 20', '((1+2)*3)/4', '100']) {
      expect(bentukAman(r), r).toBe(true)
    }
  })

  it('menerima kesembilan fungsi yang disediakan sandbox', () => {
    for (const f of FUNGSI_DIIZINKAN) {
      expect(bentukAman(`${f}(1, 2)`), f).toBe(true)
    }
  })

  it('menerima notasi ilmiah', () => {
    /*
     * Bukan kelonggaran melainkan kewajiban: String(0.0000001) menghasilkan
     * '1e-7'. Daftar putih yang lupa mengizinkan e/E akan diam-diam menolak
     * justru nilai-nilai terkecil, dan capaian KPI orang ikut nol.
     */
    expect(bentukAman('1e-7*100')).toBe(true)
    expect(bentukAman('1.5E3/2')).toBe(true)
  })

  it('MENOLAK apa pun yang bukan aritmetika', () => {
    for (const jahat of [
      'alert(1)',
      'fetch("//jahat.test")',
      'window.location = "x"',
      'document.cookie',
      '(() => 1)()',
      'this.$root',
      'globalThis',
      '[].constructor',
      'import("x")',
      '`${1}`',
    ]) {
      expect(bentukAman(jahat), jahat).toBe(false)
    }
  })

  it('menolak nilai kosong', () => {
    for (const kosong of ['', '   ', null, undefined]) {
      expect(bentukAman(kosong)).toBe(false)
    }
  })
})

describe('hitungRumus', () => {
  it('menghitung rumus yang sah', () => {
    expect(hitungRumus('(80/100)*100')).toBe(80)
    expect(hitungRumus('MIN(120, 80)')).toBe(80)
    expect(hitungRumus('IF(80>=100, 100, 80)')).toBe(80)
    expect(hitungRumus('ROUND(80.456, 1)')).toBe(80.5)
  })

  it('mengembalikan null untuk rumus yang ditolak — bukan menjalankannya', () => {
    expect(hitungRumus('alert(1)')).toBeNull()
    expect(hitungRumus('fetch("//x")')).toBeNull()
  })

  it('mengembalikan null untuk hasil yang bukan angka berhingga', () => {
    /*
     * Pembagian nol menghasilkan Infinity. Pemanggil yang ada menurunkan hasil
     * non-numerik menjadi nol, jadi null mendarat tepat di perilaku yang sudah
     * berlaku.
     */
    expect(hitungRumus('80/0')).toBeNull()
  })

  it('tidak pernah melempar, apa pun masukannya', () => {
    for (const aneh of ['((((', '1+', null, undefined, 0, {}, '*/*']) {
      expect(() => hitungRumus(aneh)).not.toThrow()
    }
  })
})

describe('paritas dengan penjaga server', () => {
  const php = () => readFileSync('app/Helpers/KpiFormulaHelper.php', 'utf8')

  it('daftar fungsi JS sama persis dengan FUNGSI_DIIZINKAN di PHP', () => {
    /*
     * Klien yang lebih longgar membuka kembali apa yang ditutup server; klien
     * yang lebih ketat diam-diam menolkan rumus yang sah. Uji ini membaca
     * berkas PHP-nya langsung supaya keduanya tidak bisa menyimpang diam-diam.
     */
    const blok = php().match(/private const FUNGSI_DIIZINKAN = \[([^\]]*)\]/)

    expect(blok, 'FUNGSI_DIIZINKAN tidak ditemukan di KpiFormulaHelper.php').not.toBeNull()

    const fungsiPhp = [...blok[1].matchAll(/'([^']+)'/g)].map((m) => m[1])

    expect([...fungsiPhp].sort()).toEqual([...FUNGSI_DIIZINKAN].sort())
  })

  it('klien LEBIH KETAT daripada server: bentuk fungsi panah ditolak', () => {
    /*
     * Daftar karakter di kedua sisi memang sama, dan harus mengizinkan '=' dan
     * '>' supaya '>=' dan '<=' bisa dipakai. Di PHP itu aman — menulis fungsi
     * anonim di sana butuh huruf, dan huruf sudah ditolak. Di JavaScript tidak:
     * `(() => 1)()` tersusun seluruhnya dari karakter yang diizinkan.
     *
     * Uji ini pertama kali MERAH dan itulah yang mengungkap lubangnya. Menyalin
     * aturan PHP mentah-mentah akan memindahkan celah yang di sana tidak ada.
     */
    expect(bentukAman('(() => 1)()')).toBe(false)
    expect(bentukAman('1 => 2')).toBe(false)

    /* dan perbandingan yang sah tidak boleh ikut jadi korban */
    expect(bentukAman('80>=100')).toBe(true)
    expect(bentukAman('80<=100')).toBe(true)
    expect(bentukAman('IF(1>=0, 1, 2)')).toBe(true)
  })

  it('daftar karakter dasarnya tetap sama dengan yang di PHP', () => {
    const blok = php().match(/private const KARAKTER_DIIZINKAN = '[^']*'/)

    expect(blok, 'KARAKTER_DIIZINKAN tidak ditemukan di KpiFormulaHelper.php').not.toBeNull()

    /*
     * Yang diperiksa keberadaan kelas karakternya, bukan kesamaan teks mentah:
     * PHP meng-escape garis miring, JS tidak. Kalau server melonggarkan
     * daftarnya (mis. mengizinkan huruf), uji ini merah dan pemindahannya harus
     * dibahas, bukan diikuti diam-diam.
     */
    for (const wajib of ['0-9', 'eE', '+', '*', '(', ')', ',', '<', '>', '=', '!']) {
      expect(blok[0], `PHP tidak lagi mengizinkan ${wajib}`).toContain(wajib)
    }
    expect(blok[0], 'PHP kini mengizinkan huruf selain e/E').not.toMatch(/a-z|A-Z/)
  })
})
