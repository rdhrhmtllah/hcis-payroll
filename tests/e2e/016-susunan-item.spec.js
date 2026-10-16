import { execFileSync } from 'node:child_process'
import { test, expect, peran, butuhEnvUat, tungguHalaman } from './support/uat.js'

/**
 * UAT-SOP-016 — susunan, penandaan, dan mode akses item paket.
 *
 * Seluruhnya janji tentang LAYAR. Urutan baca lahir dari seretan, bukan dari
 * kolom angka yang diketik; penanda wajib dan sorotan adalah dua kotak centang
 * yang mudah tertukar; dan mode akses berkas cuma dua tombol yang salah satunya
 * harus terlihat aktif setelah halaman dimuat ulang. Dari backend, ketiganya
 * hanya tampak sebagai kolom yang berubah nilai — yang tak dibuktikan backend
 * adalah apakah admin BISA mengubahnya dari layar, dan apakah pratinjau
 * memantulkan hasilnya.
 *
 * DUNIANYA PAKET DRAFT, bukan paket terbit. Pada paket yang sudah terbit,
 * Builder menulis ke overlay draft — tabel lain dengan id bertoken — sehingga
 * uji yang sama akan mengukur jalur yang bukan jalur yang dijanjikan skenario
 * ini. Paket draft juga berarti tak ada karyawan yang terikat padanya.
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
  dunia = artisan(['sop:uat-dunia', peran.karyawan, '--item=2', '--draft'])
  expect(dunia, 'Dunia uji peramban gagal dibangun.').toBeTruthy()
})

test.afterAll(() => {
  if (!dunia) return
  execFileSync('php', ['artisan', '--env=uat', 'sop:uat-bersihkan', dunia.jalan], {
    encoding: 'utf8',
    cwd: process.cwd(),
  })
})

/**
 * Buka paket dunia uji langsung pada langkah Struktur.
 *
 * Lewat ?paket=<hashid>, bukan dengan menyusuri daftar paket: daftarnya
 * berhalaman, dan paket yang baru lahir belum tentu ada di halaman pertama.
 */
async function bukaStruktur(page) {
  await page.goto(`/sop/admin/builder?paket=${dunia.paket_hash}&step=2`)
  await tungguHalaman(page)
  await expect(page.locator('.acc-list .acc-item')).toHaveCount(2, { timeout: 30_000 })
}

/** Judul item pada panel struktur, sesuai urutan tampilnya. */
async function urutanStruktur(page) {
  return (await page.locator('.acc-list .acc-item .row-title').allTextContents()).map((t) => t.trim())
}

function itemStruktur(page, n) {
  return page.locator('.acc-list .acc-item').filter({ hasText: `${dunia.tanda} Dokumen ${n}` })
}

/**
 * Dua sakelar di kaki editor item: 0 = Wajib, 1 = Sorot di detail.
 *
 * Dikembalikan sepasang — LABEL untuk ditekan, INPUT untuk dibaca. Kotak
 * centangnya disembunyikan CSS dan digantikan kotak gambar (.wt-box), sehingga
 * `check()` pada input-nya menunggu elemen yang memang tak akan pernah
 * terlihat, dan gagal dengan pesan yang menunjuk selektor alih-alih penyebabnya.
 */
function sakelar(editor, i) {
  const label = editor.locator('.toggles .wajib-toggle').nth(i)

  return { label, input: label.locator('input[type="checkbox"]') }
}

/**
 * Simpan editor item yang sedang terbuka.
 *
 * Editor item yang SUDAH ADA sengaja tetap terbuka setelah disimpan — panel
 * versi di bawahnya baru berguna justru sesudah itu. Menunggunya tertutup
 * berarti menunggu selamanya. Yang menandai simpan berhasil adalah pesan
 * "Item tersimpan", dan itu pula yang membedakan dari simpan yang ditolak
 * server. Akordeonnya tak perlu ditutup: Builder hanya menyimpan SATU
 * openItemId, jadi membuka item berikutnya menutup yang ini dengan sendirinya.
 */
async function simpanItem(page, akordeon) {
  const pesan = page.locator('.el-message').filter({ hasText: /item tersimpan/i })

  /* Pesan sisa dari simpan SEBELUMNYA ditunggu hilang di SINI, sebagai prasyarat
     — bukan sesudah simpan sebagai penutup. Bedanya menentukan: menunggunya di
     akhir memberi jeda beberapa detik sebelum pemanggil sempat berbuat apa-apa,
     dan jeda itu menutupi persis cacat yang hendak dijaga uji ini. Terbukti:
     versi pertama penjaga regresi di bawah tetap HIJAU pada kode yang belum
     diperbaiki. */
  await expect(pesan).toHaveCount(0, { timeout: 20_000 })

  /* Polanya berjangkar di EKOR saja, dan itu disengaja. Tombolnya berikon
     (`<i class="bi bi-check-lg"></i> Simpan`), dan glif Bootstrap Icons itu
     disuntikkan lewat CSS ::before — Chromium ikut menghitungnya ke dalam nama
     aksesibel. Terukur pada tombol ini: `/simpan$/i` cocok, `/^simpan/i` tidak,
     dan `/^\s*simpan\s*$/i` juga tidak, karena yang di depan bukan spasi
     melainkan sebuah karakter. Pola berjangkar di kepala gagal diam-diam, dan
     kegagalannya terbaca seolah tombolnya tak ada di layar. */
  await akordeon.locator('.acc-editor .ins-actions').first()
    .getByRole('button', { name: /simpan$/i }).click()

  /* Berhenti di sini, TANPA menunggu pesannya pudar: begitu layar berkata
     tersimpan, pemanggil harus bebas berbuat apa pun seketika — itulah keadaan
     yang dialami admin sungguhan. */
  await expect(pesan, 'Simpan item tidak menghasilkan konfirmasi apa pun.').toBeVisible({
    timeout: 20_000,
  })
}

test('@UAT-SOP-016 urutan item berubah dengan menyeret, dan tersimpan', async ({ page, masuk }) => {
  await masuk(peran.admin)
  await bukaStruktur(page)

  expect(await urutanStruktur(page)).toEqual([
    `${dunia.tanda} Dokumen 1`,
    `${dunia.tanda} Dokumen 2`,
  ])

  /* Diseret pada .acc-head — di situlah draggable="true" dipasang, dan di situ
     pula @click membuka akordeon. Kalau seretannya tak dikenali peramban,
     yang terjadi bukan "tidak ada apa-apa" melainkan item terbuka sebagai
     editor; urutan yang tak berubah di bawah ini menangkap keduanya. */
  await itemStruktur(page, 2).locator('.acc-head').dragTo(itemStruktur(page, 1).locator('.acc-head'))

  await expect
    .poll(() => urutanStruktur(page), {
      message: 'Urutan tidak berubah setelah item kedua diseret ke atas item pertama.',
      timeout: 15_000,
    })
    .toEqual([`${dunia.tanda} Dokumen 2`, `${dunia.tanda} Dokumen 1`])

  /* Sisi yang membuat seretan berarti: ia TERSIMPAN. Urutan yang hanya berubah
     di layar mengembalikan admin ke susunan lama begitu halaman dibuka lagi,
     dan karyawan membaca dalam urutan yang tak pernah dipilih siapa pun. */
  await bukaStruktur(page)
  expect(await urutanStruktur(page)).toEqual([
    `${dunia.tanda} Dokumen 2`,
    `${dunia.tanda} Dokumen 1`,
  ])
})

test('@UAT-SOP-016 penanda wajib, sorotan, dan mode akses tersimpan serta tercermin di pratinjau', async ({
  page,
  masuk,
}) => {
  await masuk(peran.admin)
  await bukaStruktur(page)

  // --- Dokumen 1: disorot, dan berkasnya boleh diunduh.
  const satu = itemStruktur(page, 1)
  await satu.locator('.acc-head').click()

  const editorSatu = satu.locator('.acc-editor')
  await expect(editorSatu).toBeVisible({ timeout: 15_000 })
  await editorSatu.getByRole('button', { name: /boleh unduh/i }).click()

  const sorot = sakelar(editorSatu, 1)
  await sorot.label.click()
  await expect(sorot.input).toBeChecked()

  await simpanItem(page, satu)

  // --- Dokumen 2: tidak wajib. Langsung, tanpa memuat ulang halaman.
  const dua = itemStruktur(page, 2)
  await dua.locator('.acc-head').click()

  /* PENJAGA REGRESI. Dulu di sini muncul "Perubahan belum disimpan — akan
     hilang", padahal simpannya baru saja berhasil: penandaan formulir-bersih
     menunggu di belakang reloadItems(), sehingga ada jeda selebar satu
     permintaan jaringan ketika layar sudah berkata "Item tersimpan" tapi
     penjaganya masih menganggap formulirnya kotor (terukur ±1,5 detik).
     Peringatan yang muncul sesudah simpan berhasil mengajarkan orang menekan
     "Tinggalkan" tanpa membaca — dan kebiasaan itu yang membuat penjaga
     perubahan-belum-disimpan berhenti menjaga, termasuk penjaga di 009. */
  await expect(
    page.locator('.el-message-box'),
    'Berpindah item sesudah simpan berhasil masih diperingatkan "Perubahan belum disimpan".',
  ).toHaveCount(0)

  const editorDua = dua.locator('.acc-editor')
  await expect(editorDua).toBeVisible({ timeout: 15_000 })

  const wajib = sakelar(editorDua, 0)
  await expect(wajib.input, 'Dunia uji seharusnya lahir dengan item yang wajib.').toBeChecked()
  await wajib.label.click()
  await expect(wajib.input).not.toBeChecked()

  await simpanItem(page, dua)

  /* Dimuat ulang lebih dulu. Pratinjau ikut hidup dari keadaan yang sama dengan
     panel struktur, jadi memeriksanya tanpa memuat ulang hanya membuktikan Vue
     bereaksi terhadap dirinya sendiri — bukan bahwa servernya menyimpan apa
     pun. */
  await bukaStruktur(page)

  const pratinjau = page.locator('.preview-pane')
  await expect(pratinjau).toBeVisible()

  const kartuSatu = pratinjau.locator('.ritem').filter({ hasText: `${dunia.tanda} Dokumen 1` })
  const kartuDua = pratinjau.locator('.ritem').filter({ hasText: `${dunia.tanda} Dokumen 2` })

  await expect(kartuSatu, 'Pratinjau tidak memuat item pertama.').toHaveCount(1, { timeout: 20_000 })
  await expect(kartuSatu.locator('.ritem-ribbon')).toContainText(/penting/i)
  await expect(kartuSatu.locator('.ri-wajib')).toHaveCount(1)

  await expect(
    kartuDua.locator('.ri-wajib'),
    'Item yang centang wajibnya dilepas masih ditandai Wajib di pratinjau.',
  ).toHaveCount(0)
  await expect(kartuDua.locator('.ritem-ribbon')).toHaveCount(0)

  /* Mode akses tak punya penanda di pratinjau — ia izin, bukan tampilan. Yang
     membuktikannya tersimpan adalah tombolnya kembali dalam keadaan aktif
     setelah halaman dimuat ulang dari server. */
  await satu.locator('.acc-head').click()
  await expect(editorSatu).toBeVisible({ timeout: 15_000 })
  await expect(editorSatu.locator('.acc-seg button.active')).toContainText(/boleh unduh/i)
  await expect(sakelar(editorSatu, 1).input).toBeChecked()
})

/**
 * Pintu KEDUA dari cacat yang sama, pada formulir identitas paket.
 *
 * Ditaruh di berkas ini karena berbagi dunia uji dan modul yang sama, tapi
 * ditandai @UAT-SOP-015 — di sanalah "isi judul, kategori, keterangan, simpan"
 * dijanjikan. Cacat "peringatan sesudah simpan berhasil" berulang di modul ini
 * karena aturannya kerap dipasang hanya pada satu dari dua jalur yang setara;
 * menjaga satu pintu saja berarti menunggu pintu satunya dilupakan lagi.
 */
test('@UAT-SOP-015 menyimpan identitas paket tidak menyisakan peringatan "belum disimpan"', async ({
  page,
  masuk,
}) => {
  await masuk(peran.admin)
  await page.goto(`/sop/admin/builder?paket=${dunia.paket_hash}&step=1`)
  await tungguHalaman(page)

  const deskripsi = page.locator('textarea[placeholder*="Ringkas isi"]')
  await expect(deskripsi).toBeVisible({ timeout: 30_000 })
  await deskripsi.fill('UAT-AUTO keterangan paket, disunting lalu disimpan.')

  await page.getByRole('button', { name: /lanjut ke struktur/i }).click()
  await expect(page.locator('.el-message').filter({ hasText: /identitas tersimpan/i })).toBeVisible({
    timeout: 30_000,
  })

  /* Ditekan SEKETIKA, tanpa menunggu apa pun pudar. Di jeda inilah cacatnya
     hidup: layar sudah berkata tersimpan, sedangkan penandaan formulir-bersih
     masih menunggu di belakang permintaan jaringan berikutnya. */
  await page.locator('.wiz-steps .wiz-step').filter({ hasText: /struktur/i }).click()

  await expect(
    page.locator('.el-message-box'),
    'Berpindah langkah sesudah identitas tersimpan masih diperingatkan "Perubahan belum disimpan".',
  ).toHaveCount(0)

  await expect(page.locator('.acc-list .acc-item')).toHaveCount(2, { timeout: 30_000 })
})
