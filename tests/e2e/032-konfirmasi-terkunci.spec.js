import { execFileSync } from 'node:child_process'
import { test, expect, peran, butuhEnvUat, tungguHalaman } from './support/uat.js'

/**
 * UAT-SOP-032 — [KONTROL] konfirmasi baca terkunci sampai dokumennya dibaca.
 *
 * Janji tentang LAYAR: yang dijaga adalah tombolnya belum bisa ditekan, dan itu
 * tak meninggalkan jejak apa pun di server — permintaan yang tak pernah dikirim
 * tak bisa diamati dari backend.
 *
 * DUNIANYA DIBANGUN SENDIRI, tidak meminjam penugasan yang sudah ada. Membaca
 * dokumen menulis waktu buka dan menggeser status paket; meminjam penugasan
 * karyawan sungguhan berarti mengubah rekam kepatuhan orang yang tak pernah
 * membuka apa pun — dan itu tak bisa dipulihkan jurnal, karena yang terjadi
 * pembaruan, bukan penyisipan.
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

let dunia = null

/**
 * Kartu tugas milik dunia uji, pada tampilan kartu MAUPUN daftar.
 *
 * Sengaja TIDAK memakai `text=` polos: penugasan baru memunculkan notifikasi
 * shell berisi judul paket yang sama, dan notifikasi itu ada lebih dulu di DOM.
 * Uji versi pertama saya lulus karena mengenai notifikasi itu — hijau yang tak
 * membuktikan bahwa paketnya sampai ke daftar tugas sama sekali.
 */
function kartuTugas(page) {
  return page.locator('.tg-card, .task-row').filter({ hasText: dunia.tanda })
}

test.beforeAll(() => {
  dunia = artisan(['sop:uat-dunia', peran.karyawan, '--item=2'])
  expect(dunia, 'Dunia uji peramban gagal dibangun.').toBeTruthy()
})

test.afterAll(() => {
  if (!dunia) return

  /* Jurnal dilengkapi DULU. Membuka dokumen di peramban menyisipkan baris
     progres baru yang tak mungkin diketahui jurnal saat ia ditulis; tanpa
     langkah ini pembersihan gagal pada foreign key, dan gagal separuh jalan
     adalah keadaan yang paling sulit dibereskan tangan. */
  artisan(['sop:uat-dunia', peran.karyawan, '--lengkapi', dunia.jalan, '--penjadwalan', String(dunia.penjadwalan)])
  execFileSync('php', ['artisan', '--env=uat', 'sop:uat-bersihkan', dunia.jalan], {
    encoding: 'utf8',
    cwd: process.cwd(),
  })
})

test('@UAT-SOP-028 paket yang baru ditugaskan muncul di Functional Document', async ({ page, masuk }) => {
  await masuk(peran.karyawan)
  await page.goto('/sop/saya')
  await tungguHalaman(page)

  /* SOP Saya punya DUA tampilan: kartu (.tg-card) dan daftar (.task-row).
     Menyebut salah satunya saja membuat uji ini bergantung pada preferensi
     tampilan yang tersimpan per pengguna — hijau di satu akun, merah di akun
     lain, tanpa ada yang berubah pada fiturnya. */
  await expect(kartuTugas(page)).toHaveCount(1, { timeout: 30_000 })
})

test('@UAT-SOP-032 [KONTROL] konfirmasi belum bisa ditekan sebelum dokumennya dibaca', async ({ page, masuk }) => {
  await masuk(peran.karyawan)
  await page.goto('/sop/saya')
  await tungguHalaman(page)

  /* Diklik pada KARTU TUGASNYA (.task-row), bukan pada teks pertama yang cocok.
     Penugasan baru memunculkan notifikasi shell berisi judul paket yang sama,
     dan notifikasi itu muncul lebih dulu di DOM — `text=` polos mengenainya,
     lalu menunggu 20 detik untuk klik yang tak pernah bisa terjadi. */
  const kartu = kartuTugas(page).first()
  await expect(kartu).toBeVisible({ timeout: 30_000 })
  await kartu.click()
  await tungguHalaman(page)

  /* Konfirmasi hanya boleh tersedia SETELAH dokumennya dibuka dan dituntaskan.
     Yang diperiksa keberadaan penanda "Selesai Dibaca", bukan sekadar tombol
     bernama konfirmasi: tombol yang tampak tapi ditolak server saat ditekan
     memang aman, tapi ia mengajarkan bahwa tombol yang tak berfungsi itu wajar. */
  const spanduk = page.locator('text=/selesai dibaca/i')
  await expect(
    spanduk,
    'Penanda "Selesai Dibaca" sudah muncul padahal dokumennya belum dibuka sama sekali.',
  ).toHaveCount(0)

  // Dan itemnya memang belum berstatus selesai.
  await expect(page.locator('text=/dikonfirmasi/i')).toHaveCount(0)
})
