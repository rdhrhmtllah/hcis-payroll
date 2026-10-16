import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

import { describe, expect, it } from 'vitest'

/**
 * Ratchet: setiap penugasan ke `.innerHTML` harus terdaftar dan bersebab.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * `CutiAdmin.vue` punya DUA `showToast` di dalam blok `methods` yang sama.
 * Dalam objek JavaScript kunci terakhir menang, sehingga yang berlaku adalah
 * yang membangun elemen sendiri:
 *
 *     toast.innerHTML = `... <span>${message}</span> ...`
 *
 * sementara definisi pertama — yang memakai ElMessage dan meng-escape isinya —
 * tidak pernah dijalankan. Pemanggilnya menyusun pesan dari nama karyawan yang
 * diambil dari basis data, jadi nama yang mengandung markup dieksekusi sebagai
 * HTML di layar admin. Tidak ada yang terlihat salah saat berkasnya dibaca:
 * definisi yang aman ada, hanya saja mati.
 *
 * ── Kenapa daftar, bukan larangan ───────────────────────────────────────────
 *
 * Dua tempat memang butuh menulis HTML dan sudah menanganinya dengan benar:
 * satu menyaring lewat DOMPurify, satu meng-escape nilai yang disisipkan.
 * Melarang `.innerHTML` sama sekali akan menolak keduanya tanpa membuat apa
 * pun lebih aman. Yang dijaga adalah bahwa daftarnya tidak bertambah diam-diam
 * — situs baru harus lewat perubahan berkas ini, dengan sebabnya ditulis.
 *
 * Membaca `.innerHTML` (mis. `return div.innerHTML`) tidak ikut dijaga; yang
 * berbahaya adalah menulisinya.
 */

const AKAR = 'resources/js'

/**
 * Satu-satunya penugasan `.innerHTML` yang diizinkan, beserta sebabnya.
 * Kunci = jalur relatif terhadap resources/js.
 */
const DIIZINKAN = {
  'Pages/LMS/SOP/Shared/components/ui/SopRichText.vue':
    'isi editor Quill dilewatkan this.sanitize() (DOMPurify) sebelum ditulis',
  'utils/logout-transition.js':
    'templat statis; satu-satunya nilai sisipan dibungkus escapeHTML(), sisanya konstanta modul',
}

function berkasJs(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name)
    if (e.isDirectory()) return berkasJs(p)

    return /\.(vue|js)$/.test(e.name) && !/\.test\./.test(e.name) ? [p] : []
  })
}

/** Baris yang seluruhnya komentar tidak dihitung — kode mati bukan sink. */
const barisKomentar = (baris) => /^\s*(\/\/|\*|\/\*)/.test(baris)

describe('penugasan .innerHTML', () => {
  it('hanya terjadi di berkas yang terdaftar', () => {
    const ditemukan = new Map()

    for (const p of berkasJs(AKAR)) {
      const rel = p.replace(/\\/g, '/').replace(AKAR + '/', '')
      readFileSync(p, 'utf8')
        .split('\n')
        .forEach((baris, i) => {
          if (barisKomentar(baris)) return
          if (!/\.innerHTML\s*=[^=]/.test(baris)) return
          if (!ditemukan.has(rel)) ditemukan.set(rel, [])
          ditemukan.get(rel).push(i + 1)
        })
    }

    const takTerdaftar = [...ditemukan.keys()].filter((k) => !(k in DIIZINKAN))

    expect(
      takTerdaftar,
      'Penugasan .innerHTML baru. Bila nilainya berasal dari pengguna atau basis '
        + 'data, gunakan mekanisme yang meng-escape (ElMessage, interpolasi templat '
        + 'Vue) atau saring dengan DOMPurify. Bila memang harus, daftarkan berkasnya '
        + 'di DIIZINKAN pada berkas uji ini beserta sebabnya. Ditemukan di: '
        + takTerdaftar.map((k) => `${k}:${ditemukan.get(k).join(',')}`).join('  '),
    ).toEqual([])
  })

  it('daftarnya tidak menyimpan entri yang sudah tidak ada', () => {
    const isi = berkasJs(AKAR).reduce((acc, p) => {
      acc[p.replace(/\\/g, '/').replace(AKAR + '/', '')] = readFileSync(p, 'utf8')

      return acc
    }, {})

    for (const rel of Object.keys(DIIZINKAN)) {
      expect(isi[rel], `${rel} terdaftar tetapi berkasnya tidak ada`).toBeDefined()
      expect(
        /\.innerHTML\s*=[^=]/.test(isi[rel]),
        `${rel} terdaftar tetapi sudah tidak menugasi .innerHTML — coret dari DIIZINKAN`,
      ).toBe(true)
    }
  })
})
