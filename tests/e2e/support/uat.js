import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { test as base, expect } from '@playwright/test'
import {
  formatBrowserQualityFailures,
  isApplicationConsoleError,
  isCriticalRequestFailure,
  isCriticalResponse,
} from './browserQualityGate.js'

/**
 * Penopang lapis peramban otomasi UAT SOP.
 *
 * MASUK TANPA KATA SANDI, DAN TANPA RUTE PINTAS. Cookie sesi dicetak perintah
 * `php artisan sop:uat-sesi`, lalu dipasang ke konteks peramban. Akun di basis
 * data dev adalah salinan akun karyawan sungguhan; memakai kata sandinya untuk
 * otomasi berarti memakai kata sandi orang lain. Dan menambah endpoint HTTP
 * "login sebagai siapa saja" berarti menaruh pintas autentikasi permanen di
 * dalam aplikasi, dengan satu variabel lingkungan sebagai satu-satunya pemisah
 * dari produksi.
 */

export function envUat() {
  const berkas = path.resolve('.env.uat')
  if (!fs.existsSync(berkas)) return {}

  return Object.fromEntries(
    fs
      .readFileSync(berkas, 'utf8')
      .split('\n')
      .map((b) => b.trim())
      .filter((b) => b && !b.startsWith('#') && b.includes('='))
      .map((b) => {
        const i = b.indexOf('=')
        return [b.slice(0, i).trim(), b.slice(i + 1).trim()]
      }),
  )
}

const env = envUat()

/**
 * Cookie sesi per NIK, diingat selama satu jalan-uji.
 *
 * Tanpa ini tiap test menyalakan satu proses `php artisan` sendiri hanya untuk
 * mencetak cookie yang isinya sama. Di Windows itu bukan sekadar lambat:
 * penyalaan proses beruntun akhirnya ditolak sistem dengan
 * STATUS_DLL_INIT_FAILED (0xC0000142), dan Playwright melaporkannya sebagai
 * "worker process exited unexpectedly" — kegagalan yang berpindah-pindah test
 * setiap kali dijalankan, sehingga terbaca seperti uji yang goyah padahal
 * mesinnya yang kehabisan napas.
 *
 * Sesinya memang boleh dipakai ulang: ia berumur sepanjang jalan-uji, dan tiap
 * test tetap memasangnya ke konteks peramban yang baru.
 */
const sesiTersimpan = new Map()

/** Cookie sesi untuk satu NIK, dicetak artisan. */
export function sesiUntuk(nik) {
  if (sesiTersimpan.has(nik)) return sesiTersimpan.get(nik)

  const s = cetakSesi(nik)
  sesiTersimpan.set(nik, s)

  return s
}

function cetakSesi(nik) {
  const keluar = execFileSync(
    'php',
    ['artisan', '--env=uat', 'sop:uat-sesi', nik, '--json'],
    { encoding: 'utf8', cwd: process.cwd() },
  )

  /* Keluaran artisan bisa didahului peringatan lain; ambil baris JSON-nya saja
     alih-alih mengurai seluruh keluaran. Mengurai semuanya membuat satu
     peringatan tak berbahaya menggagalkan seluruh lapis peramban. */
  const baris = keluar
    .split('\n')
    .map((b) => b.trim())
    .filter((b) => b.startsWith('{') && b.endsWith('}'))

  if (!baris.length) {
    throw new Error(`sop:uat-sesi tidak mencetak JSON untuk ${nik}:\n${keluar}`)
  }

  return JSON.parse(baris[baris.length - 1])
}

/**
 * Peran uji yang tersedia, dibaca dari .env.uat.
 * Nilai kosong berarti lapis peramban belum bisa dijalankan.
 */
export const peran = {
  admin: env.UAT_NIK_ADMIN,
  leader: env.UAT_NIK_LEADER,
  karyawan: env.UAT_NIK_KARYAWAN_1,
  karyawan2: env.UAT_NIK_KARYAWAN_2,
}

export const baseURL = process.env.UAT_BASE_URL || env.UAT_BASE_URL || 'http://127.0.0.1:5678'

/**
 * `test` yang sudah membawa fixture `masuk(nik)`.
 *
 * Melewatkan seluruh berkas dengan pesan yang menyebut APA yang kurang, bukan
 * gagal: lapis ini memang butuh .env.uat, dan suite yang merah karena berkas
 * konfigurasi belum dibuat melatih orang mengabaikan warna merah.
 */
export const test = base.extend({
  browserQualityGate: [
    async ({ page }, use, testInfo) => {
      const failures = []

      page.on('pageerror', (error) => {
        failures.push({ kind: 'pageerror', detail: error.stack || error.message })
      })

      page.on('console', (message) => {
        if (isApplicationConsoleError(message)) {
          const location = message.location()
          const source = location?.url ? ` [${location.url}:${location.lineNumber ?? 0}:${location.columnNumber ?? 0}]` : ''
          failures.push({ kind: 'console.error', detail: `${message.text()}${source}` })
        }
      })

      page.on('response', (response) => {
        if (isCriticalResponse(response)) {
          failures.push({
            kind: 'http-5xx',
            detail: `${response.status()} ${response.request().method()} ${response.url()}`,
          })
        }
      })

      page.on('requestfailed', (request) => {
        if (isCriticalRequestFailure(request)) {
          failures.push({
            kind: 'requestfailed',
            detail: `${request.method()} ${request.url()} (${request.failure()?.errorText || 'unknown'})`,
          })
        }
      })

      await use()

      const uniqueFailures = failures.filter(
        (failure, index, all) =>
          all.findIndex((candidate) => candidate.kind === failure.kind && candidate.detail === failure.detail) === index,
      )

      if (uniqueFailures.length) {
        await testInfo.attach('browser-quality-gate.txt', {
          body: formatBrowserQualityFailures(uniqueFailures),
          contentType: 'text/plain',
        })
        throw new Error(
          `Browser quality gate menemukan ${uniqueFailures.length} failure:
${formatBrowserQualityFailures(uniqueFailures)}`,
        )
      }
    },
    { auto: true },
  ],
  masuk: async ({ context, page }, use) => {
    await use(async (nik) => {
      const s = sesiUntuk(nik)
      const url = new URL(baseURL)

      await context.addCookies([
        {
          name: s.name,
          value: s.value,
          domain: url.hostname,
          path: '/',
          httpOnly: true,
          secure: false,
          sameSite: 'Lax',
        },
      ])

      return s
    })
  },
})

export { expect }

/** Panggil di awal tiap berkas spesifikasi. */
export function butuhEnvUat() {
  test.skip(
    !peran.admin || !peran.karyawan,
    'Lapis peramban butuh .env.uat berikut UAT_NIK_ADMIN dan UAT_NIK_KARYAWAN_1. ' +
      'Salin .env.uat.example dan isi NIK-nya.',
  )
}

/**
 * Tunggu Inertia selesai memasang halaman.
 *
 * Menunggu `networkidle` saja tak cukup pada SPA ini: kerangka halaman sudah
 * ada sejak respons pertama, sedangkan isinya baru muncul setelah komponen Vue
 * dipasang. Uji yang membaca terlalu cepat akan melihat kerangka kosong dan
 * melaporkan "elemen tidak ditemukan" untuk elemen yang sedetik kemudian ada.
 */
export async function tungguHalaman(page) {
  await page.waitForLoadState('domcontentloaded')
  await page.waitForFunction(() => document.querySelector('#app')?.children.length > 0, {
    timeout: 30_000,
  })
}
