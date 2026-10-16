import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

import { describe, expect, it } from 'vitest'

/**
 * Ratchet: satu nama hanya boleh muncul sekali di dalam satu blok options Vue.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * Dalam objek JavaScript, kunci yang ditulis dua kali membuat yang TERAKHIR
 * menang; yang pertama hilang tanpa peringatan, tanpa galat, tanpa keluhan
 * linter. Di berkas .vue setebal ribuan baris kedua salinan bisa berjarak
 * seribu baris lebih, jadi yang terjadi bukan "kode berlebih" melainkan
 * berkasnya berbohong: yang dibaca reviewer belum tentu yang dijalankan.
 *
 * Saat penjaga ini ditulis ada 30 kunci kembar di 11 berkas. Dua puluh di
 * antaranya ISINYA BERBEDA, jadi perilakunya memang bukan yang tampak:
 *
 *   CutiAdmin.showToast     aman (ElMessage) mati, ber-innerHTML mentah hidup
 *   CutiAdmin.approveSelected  "buka modal" mati, "langsung setujui" hidup
 *   formKPI.updateActiveKpi    menulis activeKpi mati, menulis kpis[i] hidup
 *   izinPage.parseStatus       terima Y/T/P mati, terima Selesai/Ditolak hidup
 *
 * ── Cara membaca kegagalan ──────────────────────────────────────────────────
 *
 * Bila penjaga ini merah, JANGAN otomatis buang yang pertama. Tentukan dulu
 * mana yang benar: yang berjalan selama ini adalah yang TERAKHIR, dan bisa
 * jadi justru yang pertama yang lebih benar.
 */

const AKAR = 'resources/js'

const BLOK = /^\s{0,8}(methods|computed|watch)\s*:\s*\{/

/** Kata kunci pernyataan yang bentuknya mirip pemanggilan fungsi. */
const BUKAN_NAMA = ['if', 'for', 'while', 'switch', 'return', 'catch']

function berkasVue(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = join(dir, e.name)
    if (e.isDirectory()) return berkasVue(p)

    return /\.vue$/.test(e.name) ? [p] : []
  })
}

/**
 * Kunci pada kedalaman 1 di dalam sebuah blok options.
 *
 * Kedalaman dihitung dari kurung kurawal, dan kunci diperiksa SEBELUM kurawal
 * baris itu ikut dihitung -- baris `showToast(pesan) {` membuka kurawalnya
 * sendiri, jadi bila dihitung lebih dulu ia sudah berada di kedalaman 2 dan
 * tidak akan pernah terlihat.
 */
function kunciBlok(baris, iBlok) {
  const kunci = new Map()
  let dalam = 0
  let mulai = false
  let j = iBlok

  for (; j < baris.length; j++) {
    if (dalam === 1 && j > iBlok) {
      const m = baris[j].match(/^\s*(?:async\s+)?([A-Za-z_$][\w$]*)\s*\(/)
      if (m && !BUKAN_NAMA.includes(m[1])) {
        if (!kunci.has(m[1])) kunci.set(m[1], [])
        kunci.get(m[1]).push(j + 1)
      }
    }

    for (const c of baris[j]) {
      if (c === '{') {
        dalam++
        mulai = true
      } else if (c === '}') {
        dalam--
      }
    }

    if (mulai && dalam <= 0) break
  }

  return { kunci, akhir: j }
}

describe('kunci kembar di blok options Vue', () => {
  it('tidak ada satu pun', () => {
    const temuan = []

    for (const p of berkasVue(AKAR)) {
      const teks = readFileSync(p, 'utf8')
      const skrip = [...teks.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)]
        .map((m) => m[1])
        .join('\n')
      if (!skrip) continue

      const baris = skrip.split('\n')
      const rel = p.replace(/\\/g, '/').replace(AKAR + '/', '')

      for (let i = 0; i < baris.length; i++) {
        if (!BLOK.test(baris[i])) continue

        const { kunci, akhir } = kunciBlok(baris, i)
        const namaBlok = baris[i].trim().replace(/\s*:.*$/, '')

        for (const [nama, di] of kunci) {
          if (di.length > 1) {
            temuan.push(
              `${rel} ${namaBlok}.${nama}() ${di.length}x pada baris ${di.join(', ')}`
                + ` (relatif <script>) -- yang berjalan: baris ${di[di.length - 1]}`,
            )
          }
        }

        i = akhir
      }
    }

    expect(
      temuan,
      'Nama yang sama didefinisikan lebih dari sekali di dalam satu blok options. '
        + 'Kunci terakhir menang, jadi definisi sebelumnya adalah kode mati yang '
        + 'menyesatkan pembaca. Periksa dulu mana yang BENAR sebelum membuang salah '
        + 'satunya -- yang berjalan selama ini adalah yang terakhir.\n  '
        + temuan.join('\n  '),
    ).toEqual([])
  })
})
