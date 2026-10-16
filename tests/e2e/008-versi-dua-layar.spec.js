import { test, expect, peran, butuhEnvUat, tungguHalaman } from './support/uat.js'

/**
 * UAT-SOP-008 — modal Versi & Pengesahan bekerja sebagai DUA LAYAR.
 * UAT-SOP-009 — [KONTROL] meninggalkan formulir terisi dikonfirmasi dulu.
 *
 * Keduanya janji tentang LAYAR, dan tak satu pun bisa dibuktikan dari backend.
 * Endpoint penyimpan versi berperilaku sama persis entah formulirnya
 * menggantikan riwayat atau menggantung di bawahnya; yang membedakan hanya apa
 * yang dilihat admin. Begitu pula 009: konfirmasi saat meninggalkan formulir
 * adalah perilaku dialog, dan dari sisi server ia tak meninggalkan jejak apa
 * pun — permintaan yang tak pernah dikirim tak bisa diamati.
 *
 * 009 adalah skenario KONTROL. Yang dijaganya kehilangan yang tak bisa
 * dibatalkan: formulir versi memuat berkas, tiga tanggal, blok penanda tangan,
 * dan ringkasan perubahan — dan satu klik pada "Kembali" tak boleh membuangnya
 * diam-diam.
 */

butuhEnvUat()

/**
 * Buka dokumen pertama pada daftar, lalu tampilkan modal versinya.
 *
 * Halaman ini master-detail, bukan tabel: daftarnya `ul.mdx-items > li.mdx-item`,
 * dan modalnya baru muncul setelah tombol "Versi & pengesahan" pada panel detail
 * ditekan. Menebaknya sebagai tabel membuat seluruh berkas ini gagal pada baris
 * pertama, dengan pesan yang menunjuk selektor — bukan ke halaman yang berbeda
 * bentuk dari yang dikira.
 */
async function bukaModalVersi(page) {
  await page.goto('/sop/admin/dokumen')
  await tungguHalaman(page)

  const item = page.locator('ul.mdx-items li.mdx-item').first()
  await expect(item).toBeVisible({ timeout: 30_000 })
  await item.click()

  const tombolVersi = page.getByRole('button', { name: /versi\s*&\s*pengesahan/i }).first()
  await expect(tombolVersi).toBeVisible({ timeout: 20_000 })
  await tombolVersi.click()

  const modal = page.locator('.dv-stack').first()
  await expect(modal).toBeVisible({ timeout: 20_000 })

  return modal
}

test('@UAT-SOP-008 layar bawaan modal adalah RIWAYAT, bukan formulir', async ({ page, masuk }) => {
  await masuk(peran.admin)
  const modal = await bukaModalVersi(page)

  await expect(modal).toContainText(/riwayat/i)

  /* Formulir yang SELALU terbuka membuat layar terus menampilkan pekerjaan yang
     belum tentu diminta — dan menuntut admin menggulir melewati seluruh riwayat
     hanya untuk membuat versi. */
  await expect(modal.locator('input[type="file"]')).toHaveCount(0)
})

test('@UAT-SOP-008 menekan "Versi baru" MENGGANTIKAN riwayat dengan formulir', async ({ page, masuk }) => {
  await masuk(peran.admin)
  const modal = await bukaModalVersi(page)

  const tombol = modal.getByRole('button', { name: /versi baru|buat versi pertama/i }).first()
  await expect(tombol).toBeVisible()
  await tombol.click()

  /* Formulir muncul. Penandanya BUKAN input[type=file]: berkas dipilih lewat
     pemilih konten, tidak diunggah di sini — jadi tak ada input berkas sama
     sekali, dan menunggunya berarti menunggu sesuatu yang memang tak pernah
     ada. Yang menandai layar formulir adalah tombol Kembali dan kolom nomor
     versinya. */
  await expect(modal.locator('.dv-kembali')).toBeVisible({ timeout: 15_000 })
  await expect(modal.locator('.dv-nomor')).toBeVisible()

  // ...dan riwayatnya TIDAK lagi bertumpuk di bawahnya.
  await expect(modal.getByRole('button', { name: /^versi baru$|^buat versi pertama$/i })).toHaveCount(0)

  // Kepala formulir menyebut apa yang sedang dibuat, dan dari mana asalnya.
  await expect(modal.locator('.dv-eyebrow').last()).toContainText(/versi baru|versi pertama/i)
})

test('@UAT-SOP-009 [KONTROL] meninggalkan formulir yang sudah terisi dikonfirmasi dulu', async ({ page, masuk }) => {
  await masuk(peran.admin)
  const modal = await bukaModalVersi(page)

  await modal.getByRole('button', { name: /versi baru|buat versi pertama/i }).first().click()
  await expect(modal.locator('.dv-kembali')).toBeVisible({ timeout: 15_000 })

  // Isi sesuatu, supaya ada yang bisa hilang.
  const ringkasan = modal.locator('textarea').first()
  await expect(ringkasan).toBeVisible()
  await ringkasan.fill('UAT-AUTO isian yang tidak boleh hilang diam-diam')

  await modal.locator('.dv-kembali').click()

  /* Konfirmasinya ElMessageBox (Element Plus), BUKAN dialog bawaan peramban —
     jadi page.on('dialog') tak akan pernah menyala — dan ia TER-TELEPORT ke
     body, di luar modal. Mencarinya di dalam modal menghasilkan "tidak ada
     konfirmasi" untuk konfirmasi yang jelas-jelas muncul di layar. */
  const konfirmasi = page.locator('.el-message-box')
  await expect(
    konfirmasi,
    'Formulir yang sudah terisi ditinggalkan tanpa konfirmasi apa pun. Berkas, tiga ' +
      'tanggal, blok penanda tangan, dan ringkasan perubahan hilang karena satu klik.',
  ).toBeVisible({ timeout: 10_000 })
  await expect(konfirmasi).toContainText(/belum disimpan/i)

  /* Sisi yang membuat konfirmasi berarti: MEMBATALKANNYA harus mengembalikan
     admin ke formulir dengan isian utuh. Konfirmasi yang tetap membuang isian
     apa pun jawabannya hanya menunda kehilangan satu klik. */
  await konfirmasi.getByRole('button', { name: /lanjut mengisi/i }).click()

  await expect(modal.locator('.dv-kembali')).toBeVisible()
  await expect(ringkasan).toHaveValue(/tidak boleh hilang diam-diam/)
})
