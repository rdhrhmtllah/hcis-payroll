import { execFileSync } from 'node:child_process'
import { test, expect, peran, butuhEnvUat, tungguHalaman } from './support/uat.js'

/**
 * UAT-SOP-019 — meninjau paket dari sisi karyawan, lalu menerbitkannya dengan
 * sifat revisi yang ditentukan PER DOKUMEN.
 *
 * Bagian yang hanya ada di layar: pratinjau "sebagai karyawan", dan layar
 * penerbitan yang memperlihatkan dokumen mana saja yang ikut naik versi
 * beserta paket lain yang terdampak — sebelum admin menekan terbit. Dari
 * backend, publish dengan sifat mayor dan minor sama-sama satu permintaan
 * PATCH; yang membedakan adalah apakah admin sempat MELIHAT dampaknya dan
 * memilih, atau menyetujuinya tanpa pernah diberi tahu.
 *
 * DUNIANYA MEMBAWA SATU REVISI TERTUNDA (--revisi). Tanpa itu layar penerbitan
 * berbunyi "Tak ada revisi dokumen yang ikut terbit" dan tak menawarkan pilihan
 * sifat apa pun — uji akan hijau tanpa pernah menyentuh bagian terpenting
 * skenario ini.
 */

butuhEnvUat()

function artisan(args) {
  const keluar = execFileSync('php', ['artisan', '--env=uat', ...args], {
    encoding: 'utf8',
    cwd: process.cwd(),
  })

  const json = keluar
    .split('\n')
    .map((b) => b.trim())
    .filter((b) => b.startsWith('{') && b.endsWith('}'))

  return json.length ? JSON.parse(json[json.length - 1]) : null
}

/**
 * Fakta yang tak terlihat di layar mana pun, dibaca langsung dari basis data.
 *
 * Sifat revisi tidak punya cerminan visual sesudah paket terbit — ia menentukan
 * apakah karyawan yang sudah menuntaskan dokumen ini diminta membaca ulang,
 * dan itu baru terlihat berhari-hari kemudian pada rekam kepatuhan. Uji yang
 * berhenti pada "berhasil terbit" akan sama hijaunya seandainya pilihan Minor
 * tadi dibuang diam-diam di perjalanan.
 */
function sifatRevisiTerakhir(idDokumen) {
  const keluar = execFileSync(
    'php',
    [
      'artisan',
      '--env=uat',
      'tinker',
      '--execute',
      `echo '<<' . DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', ${idDokumen})` +
        `->orderByDesc('Versi_Ke')->value('Wajib_Baca_Ulang') . '>>';`,
    ],
    { encoding: 'utf8', cwd: process.cwd() },
  )

  const m = keluar.match(/<<(.*?)>>/s)

  return m ? m[1].trim() : null
}

let dunia = null

test.beforeAll(() => {
  dunia = artisan(['sop:uat-dunia', peran.karyawan, '--item=2', '--revisi'])
  expect(dunia, 'Dunia uji peramban gagal dibangun.').toBeTruthy()
})

test.afterAll(() => {
  if (!dunia) return

  /* --paket WAJIB di sini. Menerbitkan paket dari peramban melahirkan rilis
     paket, isinya yang dibekukan, dan versi dokumen baru — semuanya sesudah
     jurnal ditulis. Tanpa disapu, pembersihan berhenti di tengah pada foreign
     key dan meninggalkan dokumen yatim di basis data dev. */
  artisan([
    'sop:uat-dunia', peran.karyawan,
    '--lengkapi', dunia.jalan,
    '--penjadwalan', String(dunia.penjadwalan),
    '--paket', String(dunia.paket),
  ])
  execFileSync('php', ['artisan', '--env=uat', 'sop:uat-bersihkan', dunia.jalan], {
    encoding: 'utf8',
    cwd: process.cwd(),
  })
})

async function bukaTinjau(page) {
  await page.goto(`/sop/admin/builder?paket=${dunia.paket_hash}&step=3`)
  await tungguHalaman(page)
  await expect(page.getByText(/tinjau sebagai karyawan/i)).toBeVisible({ timeout: 30_000 })
}

test('@UAT-SOP-019 pratinjau menampilkan paket seperti yang akan dilihat karyawan', async ({ page, masuk }) => {
  await masuk(peran.admin)
  await bukaTinjau(page)

  /* Pratinjau langkah Tinjau ada di .review-stage, bukan .preview-pane —
     panel samping di langkah Struktur itu pendamping saat menyusun, sedangkan
     yang ini panggung penuh berbingkai perangkat. Menyebut yang salah membuat
     uji ini gagal seolah pratinjaunya tak ada. */
  const pratinjau = page.locator('.review-stage')
  await expect(pratinjau).toBeVisible()

  /* Isinya, bukan sekadar bingkainya. Panel pratinjau yang tampil tapi kosong
     tetap lolos kalau yang diperiksa hanya keberadaan panelnya — dan itu
     persis kegagalan yang paling mungkin terjadi di sini. */
  for (const n of [1, 2]) {
    await expect(
      pratinjau.locator('.ritem').filter({ hasText: `${dunia.tanda} Dokumen ${n}` }),
      `Item ${n} tidak muncul di pratinjau karyawan.`,
    ).toHaveCount(1, { timeout: 20_000 })
  }

  await expect(pratinjau).toContainText(`${dunia.tanda} Section`)
})

test('@UAT-SOP-019 layar penerbitan menyebut dokumen yang ikut naik versi, dan sifatnya bisa dipilih per dokumen', async ({
  page,
  masuk,
}) => {
  await masuk(peran.admin)
  await bukaTinjau(page)

  await page.locator('textarea[placeholder*="Ringkas perubahan"]').fill(
    'UAT-AUTO — rilis uji otomatis, sifat revisi ditentukan per dokumen.',
  )

  await page.getByRole('button', { name: /publish pembaruan/i }).click()

  /* Menekan Publish TIDAK BOLEH langsung menerbitkan. Sejak satu dokumen bisa
     dipakai banyak paket, menerbitkan paket ini bisa menerbitkan revisi yang
     juga disajikan paket lain — dan itu harus terlihat sebelum disetujui,
     bukan diberitahukan sesudahnya.

     Modalnya SopModal buatan sendiri (.sop-modal), bukan el-dialog Element
     Plus. Keduanya terlihat sama di layar dan sama sekali berbeda di DOM. */
  const modal = page.locator('.sop-modal').filter({ hasText: /terbitkan paket ini/i })
  await expect(modal, 'Publish langsung berjalan tanpa memperlihatkan dampaknya.').toBeVisible({
    timeout: 20_000,
  })

  const baris = modal.locator('.pp-daftar li')
  await expect(baris).toHaveCount(1, { timeout: 20_000 })
  await expect(baris.first()).toContainText(`${dunia.tanda} Dokumen 1`)

  // Jangkauannya ikut disebut, meski jawabannya "hanya paket ini".
  await expect(baris.first().locator('.pp-lain')).toContainText(/hanya paket ini/i)

  /* Bawaannya MAYOR, dan itu disengaja: admin yang tak menyentuh apa pun tak
     boleh diam-diam menghasilkan "tak usah dibaca ulang". */
  await expect(baris.first().locator('.pp-pil.on')).toContainText(/mayor/i)

  await baris.first().locator('.pp-pil.minor').click()
  await expect(baris.first().locator('.pp-pil.minor')).toHaveClass(/\bon\b/)
  await expect(modal.locator('.pp-catat')).toContainText(/1.*dari 1 dokumen ditandai minor/i)

  const mulai = Date.now()
  await modal.getByRole('button', { name: /ya, terbitkan/i }).click()

  /* Tenggatnya panjang karena lamanya TIDAK TETAP: terukur 18 detik pada satu
     jalan dan 55 detik pada jalan lain, dengan dunia uji yang sama. Penugasannya
     di bawah ambang async sehingga rekonsiliasi berjalan inline; yang berayun
     adalah pengiriman notifikasi ke Cloud Tasks sesudah commit, sebab vhost
     memakai .env biasa. Basis datanya sudah benar jauh sebelum layarnya berubah
     — tenggat yang dipas-paskan ke angka tercepat akan melaporkan "penerbitan
     gagal" untuk penerbitan yang sebenarnya berhasil, dan itu terjadi sesekali
     saja, yang justru paling sulit dipercaya. */
  await expect(
    page.locator('.el-message').filter({ hasText: /paket diterbitkan/i }),
    'Penerbitan tidak menghasilkan konfirmasi apa pun.',
  ).toBeVisible({ timeout: 150_000 })

  console.log(`  penerbitan selesai dalam ${Math.round((Date.now() - mulai) / 1000)} detik`)

  /* Dan pilihan Minor itu benar-benar tersimpan. Ini satu-satunya bagian
     skenario yang tak punya cerminan di layar mana pun sesudah paket terbit. */
  expect(
    sifatRevisiTerakhir(dunia.dokumen[0]),
    'Sifat revisi tersimpan sebagai MAYOR padahal admin memilih Minor.',
  ).toBe('T')
})
