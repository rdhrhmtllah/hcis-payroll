import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

import { describe, expect, it } from 'vitest'

/**
 * Ratchet: pemformat tanggal tidak boleh tumbuh kembali di dalam komponen.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * "Format sebuah tanggal" pernah tersalin 34 kali di halaman Vue, dalam bentuk
 * yang tidak semuanya sama. Dua di antaranya BERNAMA SAMA (`formatDateString`)
 * tetapi berbeda perilakunya pada masukan tak terurai:
 *
 *   20 salinan  ber-try/catch  -> mengembalikan teks aslinya
 *    7 salinan  tanpa catch    -> MELEMPAR RangeError
 *
 * Intl memang melempar untuk string tak-kosong yang tak terurai, dan di dalam
 * render Vue lemparan itu menghapus seluruh panel — bukan satu selnya.
 *
 * ── Kenapa ratchet, bukan larangan ──────────────────────────────────────────
 *
 * Sembilan belas pemformat lain TETAP tinggal di komponennya, dan itu
 * disengaja: bentuknya satuan — fallback '-' alih-alih '', dateStyle/timeStyle,
 * bahkan satu yang mengurai timestamp MySQL sendiri. Memaksa semuanya ke satu
 * rumah berarti menyamakan keluaran yang memang berbeda, dan itu keputusan
 * produk.
 *
 * Yang dijaga: jumlahnya tidak boleh BERTAMBAH, dan ketiga bentuk yang sudah
 * dipindahkan tidak boleh muncul lagi. Bila sebuah pemformat berhasil
 * dipindahkan, angka di bawah harus diturunkan — supaya daftar ini tidak
 * berubah menjadi tempat sampah abadi.
 */

const AKAR = 'resources/js'

/** Jumlah pemformat ber-Intl yang masih tinggal di dalam komponen. */
const SISA_DIIZINKAN = 19

const norm = (s) => s.replace(/["']/g, "'").replace(/\s+/g, ' ').trim()

/** Ketiga bentuk yang sudah punya rumah; tidak boleh muncul lagi di komponen. */
const SUDAH_PINDAH = [
  norm(
    `if (!dateString) return ""; try { const date = new Date(dateString); const options = { day: "numeric", month: "long", year: "numeric", }; return new Intl.DateTimeFormat("id-ID", options).format(date); } catch (error) { return dateString; }`,
  ),
  norm(
    `if (!dateString) return "N/A"; try { const date = new Date(dateString); const options = { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", }; return new Intl.DateTimeFormat("id-ID", options).format(date); } catch (error) { return dateString; }`,
  ),
  norm(
    `if (!dateString) return ""; const date = new Date(dateString); const options = { day: "numeric", month: "long", year: "numeric" }; return new Intl.DateTimeFormat("id-ID", options).format(date);`,
  ),
]

function berkasVue(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name)
    if (e.isDirectory()) return berkasVue(p)

    return e.name.endsWith('.vue') ? [p] : []
  })
}

/** Semua badan metode ber-nama *formatDate* yang memakai Intl. */
function pemformat() {
  const keluar = []
  for (const p of berkasVue(AKAR)) {
    const s = readFileSync(p, 'utf8')
    const pola = /(\w*[fF]ormatDate\w*)\s*\(([^)]*)\)\s*\{/g
    let m
    while ((m = pola.exec(s)) !== null) {
      const buka = s.indexOf('{', m.index + m[0].length - 1)
      let d = 0
      let i = buka
      for (; i < s.length; i++) {
        if (s[i] === '{') d++
        else if (s[i] === '}') {
          d--
          if (d === 0) break
        }
      }
      const badan = s.slice(buka + 1, i)
      if (!badan.includes('Intl.DateTimeFormat')) continue
      keluar.push({ berkas: p.replace(/\\/g, '/'), nama: m[1], badan })
    }
  }

  return keluar
}

describe('ratchet pemformat tanggal', () => {
  it('tidak ada komponen yang menulis ulang bentuk yang sudah punya rumah', () => {
    const kembali = pemformat()
      .filter((f) => SUDAH_PINDAH.includes(norm(f.badan)))
      .map((f) => `${f.berkas} :: ${f.nama}()`)

    expect(
      kembali,
      'Bentuk ini sudah pindah ke utils/tanggal.js. Salinan kedua akan berhenti '
        + 'sama pada perbaikan pertama yang hanya menyentuh satu sisi — dan salah '
        + 'satu bentuknya MELEMPAR pada tanggal tak terurai.',
    ).toEqual([])
  })

  it('jumlah pemformat di dalam komponen tidak bertambah', () => {
    const sisa = pemformat()

    expect(
      sisa.length,
      `Pemformat tanggal di dalam komponen bertambah menjadi ${sisa.length}. `
        + 'Pakai utils/tanggal.js. Kalau bentuknya memang berbeda dan harus '
        + 'tinggal di komponen, naikkan angkanya bersama alasannya.',
    ).toBeLessThanOrEqual(SISA_DIIZINKAN)
  })

  it('angka ratchet diturunkan ketika pemformat berhasil dipindahkan', () => {
    const sisa = pemformat()

    expect(
      sisa.length,
      `Tinggal ${sisa.length} pemformat di komponen, sementara ratchet masih `
        + `${SISA_DIIZINKAN}. Turunkan angkanya supaya daftar ini tidak menjadi `
        + 'tempat sampah abadi.',
    ).toBe(SISA_DIIZINKAN)
  })
})
