import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

import { describe, expect, it } from 'vitest'

/**
 * Ratchet: setiap tempat HTML mentah masuk ke DOM harus terdaftar dan bersebab.
 *
 * ── Kenapa daftar ini ada ───────────────────────────────────────────────────
 *
 * Sudah tiga kali cacat yang sama muncul dengan SINTAKS BERBEDA, dan tiap kali
 * penjaga sebelumnya tidak menangkapnya:
 *
 *   .innerHTML =        nama karyawan di toast CutiAdmin           (#387)
 *   v-html              nama satuan KPI di formatValueWithUnit     (#396)
 *   raw-content         nama approver di tooltip izinPage*         (ini)
 *
 * `raw-content` adalah prop el-tooltip milik Element Plus: isinya dirender
 * sebagai HTML. Tidak ada kata "innerHTML" maupun "v-html" di sana, jadi kedua
 * penjaga sebelumnya buta terhadapnya. Daftar di bawah menyatukan ketiganya
 * supaya bentuk keempat tidak lolos dengan cara yang sama.
 *
 * ── Bentuk daftarnya ────────────────────────────────────────────────────────
 *
 * Per berkas, bukan per baris: nomor baris bergeser tiap kali berkasnya
 * disunting, dan daftar yang tiap hari perlu diperbarui akan diperbarui tanpa
 * dibaca. Yang dijaga adalah bahwa TIDAK ADA BERKAS BARU yang ikut merender
 * HTML mentah tanpa seseorang menuliskan alasannya di sini.
 */

const AKAR = 'resources/js'

/**
 * Berkas yang boleh merender HTML mentah, beserta sebabnya.
 * Menambah entri di sini berarti menyatakan nilainya aman — sebutkan kenapa.
 */
const DIIZINKAN = {
  'components/ui/HcisPhoneInput.vue':
    'SVG bendera berupa literal template di berkas itu sendiri; tak ada nilai dari luar',
  'components/ui/HcisTooltip.vue':
    'v-html hanya aktif bila pemanggil menyalakan prop rawContent; per hari ini tak ada pemanggil di repo',
  'Pages/ESS/Dashboard/Personal/uDash.vue':
    'v-html memakai DOMPurify.sanitize; tooltip raw-content hanya menyusun literal dan tanggal terformat',
  'Pages/ESS/Attendance/Leave/Personal/izinPage.vue':
    'tooltip raw-content: nama approver dilewatkan escapeHtml',
  'Pages/ESS/Attendance/Leave/Personal/izinPageDH.vue':
    'tooltip raw-content: nama approver dilewatkan escapeHtml',
  'Pages/ESS/Attendance/Leave/Admin/izinPageAdmin.vue':
    'tooltip raw-content: nama approver dilewatkan escapeHtml',
  'Pages/KPI/Performance/Update/updatePerformance.vue':
    'v-html memakai formatNilaiSatuanSorot, yang meng-escape nama satuan',
  'Pages/KPI/Performance/Update/team/updatePerformance.vue':
    'v-html memakai formatNilaiSatuanSorot, yang meng-escape nama satuan',
  'Pages/KPI/Performance/Update/team/updatePerformanceByPass.vue':
    'v-html memakai formatNilaiSatuanSorot, yang meng-escape nama satuan',
  'Pages/LMS/SOP/Employee/Detail/SopDetail.vue': 'v-html memakai sanitasiSop (DOMPurify)',
  'Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue': 'v-html memakai sanitasiSopAtau (DOMPurify)',
  'Pages/LMS/SOP/Shared/components/ui/SopPreviewItem.vue': 'v-html memakai sanitasiSop (DOMPurify)',
  'Pages/LMS/SOP/Shared/components/ui/SopVersiDetailModal.vue': 'v-html memakai sanitasiSop (DOMPurify)',
  'Pages/LMS/SOP/Shared/components/ui/SopRichText.vue': 'editor Quill; isinya disaring this.sanitize sebelum ditulis',
}

/** Buang komentar HTML dan JS, pertahankan jumlah baris. */
function tanpaKomentar(teks) {
  return teks
    .replace(/<!--[\s\S]*?-->/g, (m) => m.replace(/[^\n]/g, ' '))
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

/** Tiga sintaks yang sama-sama menyerahkan HTML mentah ke DOM. */
const SINK = [
  { nama: 'v-html', pola: /\bv-html\s*=/ },
  { nama: '.innerHTML =', pola: /\.innerHTML\s*=[^=]/ },
  { nama: 'raw-content', pola: /(^|\s):?raw-content(\s|=|>|$)/ },
]

function sinkDalam(teks) {
  return SINK.filter((s) => s.pola.test(teks)).map((s) => s.nama)
}

describe('tempat HTML mentah masuk ke DOM', () => {
  it('hanya di berkas yang terdaftar', () => {
    const takTerdaftar = []

    for (const p of berkasVue(AKAR)) {
      const rel = p.replace(/\\/g, '/').replace(AKAR + '/', '')
      if (rel in DIIZINKAN) continue

      const ditemukan = sinkDalam(tanpaKomentar(readFileSync(p, 'utf8')))
      if (ditemukan.length > 0) takTerdaftar.push(`${rel}  (${ditemukan.join(', ')})`)
    }

    takTerdaftar.sort()

    expect(
      takTerdaftar,
      'Berkas ini merender HTML mentah. Vue tidak meng-escape apa pun di sini, jadi nilai '
        + 'apa pun yang berasal dari basis data atau pengguna akan dijalankan sebagai markup. '
        + 'Pakai interpolasi biasa bila bisa; kalau memang harus HTML, saring dengan DOMPurify '
        + 'atau lewatkan escapeHtml, lalu daftarkan berkasnya di DIIZINKAN beserta '
        + `sebabnya.\n  ${takTerdaftar.join('\n  ')}`,
    ).toEqual([])
  })

  it('daftarnya tidak menyimpan entri yang sudah tidak merender HTML mentah', () => {
    const basi = []

    for (const rel of Object.keys(DIIZINKAN)) {
      let teks
      try {
        teks = readFileSync(join(AKAR, rel), 'utf8')
      } catch {
        basi.push(`${rel} — berkasnya sudah tidak ada`)
        continue
      }

      if (sinkDalam(tanpaKomentar(teks)).length === 0) {
        basi.push(`${rel} — sudah tidak merender HTML mentah`)
      }
    }

    basi.sort()

    expect(
      basi,
      `Coret dari DIIZINKAN supaya daftar ini tidak berubah jadi tempat sampah abadi.\n  ${basi.join('\n  ')}`,
    ).toEqual([])
  })
})
