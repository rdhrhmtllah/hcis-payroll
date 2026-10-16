import { execFileSync } from 'node:child_process'
import { test, expect, peran, butuhEnvUat, tungguHalaman } from './support/uat.js'

/**
 * UAT-SOP-031 — pembaca dokumen melanjutkan dari halaman terakhir, dan
 * berkasnya tak bisa diunduh maupun dicetak dari sana.
 *
 * Dua janji yang hanya hidup di layar. "Melanjutkan dari halaman terakhir"
 * adalah keadaan yang lahir dari membaca sungguhan lalu menutup lalu kembali —
 * bukan sesuatu yang bisa dilihat dari satu permintaan HTTP. Dan "tak bisa
 * diunduh" adalah pertanyaan tentang apa yang TERSEDIA di layar: berkas yang
 * ditolak server tapi tombolnya tetap ada mengajarkan bahwa tombol yang gagal
 * itu wajar.
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

test.beforeAll(() => {
  dunia = artisan(['sop:uat-dunia', peran.karyawan, '--item=2'])
  expect(dunia, 'Dunia uji peramban gagal dibangun.').toBeTruthy()
})

test.afterAll(() => {
  if (!dunia) return

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

/** Buka pembaca dokumen item pertama, dari SOP Saya sampai layar pembaca. */
async function bukaPembaca(page) {
  await page.goto('/sop/saya')
  await tungguHalaman(page)

  const kartu = page.locator('.tg-card, .task-row').filter({ hasText: dunia.tanda }).first()
  await expect(kartu).toBeVisible({ timeout: 30_000 })
  await kartu.click()
  await tungguHalaman(page)

  const item = page.locator('.ritem, .flow-item').filter({ hasText: `${dunia.tanda} Dokumen 1` }).first()
  await expect(item).toBeVisible({ timeout: 30_000 })
  await item.locator('.ri-btn-doc-cta').first().click()

  const pembaca = page.locator('.rd')
  await expect(pembaca, 'Pembaca dokumen tidak terbuka.').toBeVisible({ timeout: 60_000 })

  return pembaca
}

/** Nomor halaman yang sedang dibaca, dari lencana "cur / total". */
async function halamanSekarang(pembaca) {
  const teks = await pembaca.locator('.rd-chip').first().textContent()

  return Number((teks || '').split('/')[0].trim())
}

test('@UAT-SOP-031 pembaca dibuka kembali pada halaman terakhir yang dibaca', async ({ page, masuk }) => {
  await masuk(peran.karyawan)
  const pembaca = await bukaPembaca(page)

  const chip = pembaca.locator('.rd-chip').first()
  await expect(chip).toHaveText(/^\s*\d+\s*\/\s*\d+\s*$/, { timeout: 60_000 })

  const total = Number((await chip.textContent()).split('/')[1].trim())
  expect(total, 'Berkas dunia uji hanya satu halaman, jadi "lanjut dari halaman terakhir" tak bisa dibuktikan.')
    .toBeGreaterThan(1)
  expect(await halamanSekarang(pembaca)).toBe(1)

  /* Tombol maju sengaja terkunci sampai halamannya sempat dibaca ("Baca dulu
     Ns"). Menunggunya aktif adalah bagian dari alur yang sebenarnya, bukan
     kelambatan yang perlu diakali. */
  const maju = pembaca.locator('.rd-nav.right .rd-navbtn')
  await expect(maju).toBeEnabled({ timeout: 60_000 })
  await maju.click()

  await expect
    .poll(() => halamanSekarang(pembaca), { timeout: 30_000 })
    .toBe(2)

  // Ditutup seperti karyawan menutupnya, lewat tombol kembali di bilah atas.
  await pembaca.locator('.rd-bar .rd-ico').first().click()
  await expect(pembaca).toBeHidden({ timeout: 20_000 })

  const lagi = await bukaPembaca(page)

  /* INTI SKENARIO. Spanduk "Dilanjutkan dari halaman N" adalah satu-satunya
     tanda bahwa halaman terakhir benar-benar tersimpan lintas kunjungan —
     pembaca yang selalu mulai dari halaman 1 tetap terlihat baik-baik saja. */
  await expect(
    lagi.locator('.rd-res'),
    'Pembaca dibuka kembali dari awal; halaman terakhir tidak tersimpan.',
  ).toBeVisible({ timeout: 60_000 })
  await expect(lagi.locator('.rd-res')).toContainText(/dilanjutkan dari halaman\s*2/i)

  await expect
    .poll(() => halamanSekarang(lagi), { timeout: 30_000 })
    .toBe(2)
})

test('@UAT-SOP-031 pembaca tidak menyediakan jalan mengunduh atau mencetak', async ({ page, masuk }) => {
  await masuk(peran.karyawan)
  const pembaca = await bukaPembaca(page)

  await expect(pembaca.locator('.rd-sec')).toContainText(/terlindungi/i)

  /* Tak ada tautan unduh, dan tak ada tombol cetak. Dihitung pada SELURUH DOM
     pembaca, bukan hanya yang terlihat: panel alat (.rd-tools) sudah ada di DOM
     sejak awal dan hanya disembunyikan CSS, dan tombol yang baru muncul setelah
     menu dibuka tetap tombol yang ada. Sengaja tidak menekan .rd-more lebih
     dulu — di lebar layar ini tombol itu `display: none`, dan menunggunya
     terlihat berarti menunggu selamanya. */
  await expect(pembaca.locator('a[download]')).toHaveCount(0)
  await expect(pembaca.getByRole('button', { name: /unduh|download|cetak|print/i })).toHaveCount(0)
  await expect(pembaca.getByRole('link', { name: /unduh|download|cetak|print/i })).toHaveCount(0)

  /* Menu klik-kanan peramban ditahan, supaya "Simpan gambar sebagai…" tidak
     jadi pintu belakang untuk halaman yang dirender sebagai gambar. */
  const dicegah = await pembaca.evaluate((el) => {
    const ev = new MouseEvent('contextmenu', { bubbles: true, cancelable: true })
    el.dispatchEvent(ev)

    return ev.defaultPrevented
  })
  expect(dicegah, 'Klik kanan tidak ditahan di dalam pembaca dokumen.').toBe(true)
})
