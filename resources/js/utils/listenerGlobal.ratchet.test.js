import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

import { describe, expect, it } from 'vitest'

/**
 * Ratchet: listener pada document/window di komponen Vue harus bisa dilepas.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * `document` dan `window` hidup lebih lama daripada komponennya. Di SPA Inertia
 * komponen di-mount ulang setiap kunjungan, jadi listener yang tidak terlepas
 * MENUMPUK — dan handler lama tetap dipanggil sambil memegang instance komponen
 * yang sudah mati.
 *
 * Yang dijaga bukan "ada removeEventListener", melainkan bahwa handler-nya
 * berupa RUJUKAN BERNAMA. Sebuah arrow inline tidak punya nilai yang bisa
 * diberikan ke removeEventListener, jadi listener-nya tak mungkin dilepas —
 * cacat konstruksi, bukan kelalaian. Dua bentuk yang ditemukan saat penjaga ini
 * ditulis keduanya TAMPAK sudah benar saat dibaca:
 *
 *   uDash.vue        pasang arrow inline, lalu removeEventListener diberi arrow
 *                    BARU yang ditulis terpisah -> tidak melepas apa pun
 *   izinPageDH.vue   pasang arrow inline, lalu unmounted() melepas
 *                    this.updateWindowWidth -> fungsi yang sama sekali berbeda
 *   DatePicker.vue   mousemove di document tanpa pelepasan sama sekali; komponen
 *                    ini dipakai di 28 halaman dan mousemove menyala terus
 *
 * ── Komentar harus dibuang lebih dulu ───────────────────────────────────────
 *
 * Versi pertama pemindai ini melaporkan lima temuan; dua di antaranya baris yang
 * sudah DIKOMENTARI di izinPage.vue dan izinPageAdmin.vue. Tanpa membuang
 * komentar, penjaga ini akan merah selamanya karena kode mati.
 */

const AKAR = 'resources/js'

/** Buang komentar tetapi pertahankan jumlah baris, supaya nomor barisnya benar. */
function tanpaKomentar(teks) {
  return teks
    .replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' '))
    .replace(/(^|[^:])\/\/[^\n]*/gm, (m, awal) => awal + ' '.repeat(m.length - awal.length))
}

function berkasVue(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name)
    if (e.isDirectory()) return berkasVue(p)

    return /\.vue$/.test(e.name) && !/\.test\./.test(e.name) ? [p] : []
  })
}

/** Handler yang ditulis di tempat: tidak ada rujukan yang bisa dilepas. */
const INLINE = /^(function\b|async\b|\([\w$,\s]*\)\s*=>|[\w$]+\s*=>)/

describe('listener document/window di komponen Vue', () => {
  it('selalu memakai rujukan bernama, bukan fungsi inline', () => {
    const temuan = []

    for (const p of berkasVue(AKAR)) {
      const teks = tanpaKomentar(readFileSync(p, 'utf8'))
      const rel = p.replace(/\\/g, '/').replace(AKAR + '/', '')

      for (const m of teks.matchAll(
        /\b(document|window)\.addEventListener\s*\(\s*(['"`])([^'"`]+)\2\s*,\s*([\s\S]{0,70})/g,
      )) {
        const handler = m[4].trim().replace(/\s+/g, ' ')
        if (!INLINE.test(handler)) continue

        const baris = teks.slice(0, m.index).split('\n').length
        temuan.push(`${rel}:${baris} ${m[1]}.addEventListener('${m[3]}', <inline>)`)
      }
    }

    temuan.sort()

    expect(
      temuan,
      'Listener dipasang di document/window dengan fungsi yang ditulis di tempat. '
        + 'Tidak ada rujukan yang bisa diberikan ke removeEventListener, jadi listener ini '
        + 'TIDAK MUNGKIN dilepas dan akan menumpuk setiap kali komponennya di-mount ulang. '
        + 'Pindahkan handler-nya menjadi metode, lalu pasang dan lepas dengan rujukan yang '
        + `sama.\n  ${temuan.join('\n  ')}`,
    ).toEqual([])
  })

  it('peristiwa yang dipasang juga dilepas di berkas yang sama', () => {
    const temuan = []

    for (const p of berkasVue(AKAR)) {
      const teks = tanpaKomentar(readFileSync(p, 'utf8'))
      const rel = p.replace(/\\/g, '/').replace(AKAR + '/', '')

      const dilepas = new Set(
        [...teks.matchAll(/\b(?:document|window)\.removeEventListener\s*\(\s*(['"`])([^'"`]+)\1/g)].map(
          (m) => m[2],
        ),
      )

      for (const m of teks.matchAll(
        /\b(document|window)\.addEventListener\s*\(\s*(['"`])([^'"`]+)\2/g,
      )) {
        if (dilepas.has(m[3])) continue

        const baris = teks.slice(0, m.index).split('\n').length
        temuan.push(`${rel}:${baris} ${m[1]}.addEventListener('${m[3]}') tanpa removeEventListener`)
      }
    }

    temuan.sort()

    expect(
      temuan,
      'Peristiwa dipasang di document/window tetapi tidak pernah dilepas di berkas yang sama. '
        + `Lepaskan di beforeUnmount() dengan rujukan handler yang sama.\n  ${temuan.join('\n  ')}`,
    ).toEqual([])
  })
})
