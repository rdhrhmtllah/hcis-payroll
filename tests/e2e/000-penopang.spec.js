import { test, expect, peran, butuhEnvUat, tungguHalaman } from './support/uat.js'

/**
 * Penopang lapis peramban — dijalankan lebih dulu dari spesifikasi lain.
 *
 * Kalau berkas ini merah, seluruh spesifikasi sesudahnya tak berarti apa-apa:
 * kegagalannya akan menunjuk ke fitur, padahal yang rusak adalah cara masuknya.
 * Memisahkannya membuat perbedaan itu terbaca dari nama berkasnya saja.
 */

butuhEnvUat()

test('@smoke sesi cetakan artisan benar-benar membuka halaman terlindung', async ({ page, masuk }) => {
  await masuk(peran.admin)
  await page.goto('/sop/admin/dokumen')
  await tungguHalaman(page)

  await expect(page).toHaveURL(/\/sop\/admin\/dokumen/)
  await expect(page.locator('#app')).not.toBeEmpty()
})

test('@smoke tanpa sesi, halaman terlindung menolak', async ({ page }) => {
  await page.goto('/sop/admin/dokumen')

  // Aplikasi mengalihkan ke login; yang penting bukan tetap di halaman admin.
  await expect(page).not.toHaveURL(/\/sop\/admin\/dokumen$/)
})

test('@smoke karyawan biasa tidak melihat menu pengelolaan SOP', async ({ page, masuk }) => {
  test.skip(peran.karyawan === peran.admin,
    'UAT_NIK_KARYAWAN_1 sama dengan UAT_NIK_ADMIN pada .env.uat, sehingga batas ' +
    'akses tak bisa dibedakan. Isi NIK karyawan yang bukan admin.')

  await masuk(peran.karyawan)
  await page.goto('/sop/saya')
  await tungguHalaman(page)

  const menu = page.locator('a[href*="/sop/admin/"]')
  await expect(menu).toHaveCount(0)
})
