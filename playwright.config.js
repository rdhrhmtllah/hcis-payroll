import { defineConfig, devices } from '@playwright/test'
import fs from 'node:fs'
import path from 'node:path'

/**
 * Konfigurasi lapis peramban otomasi UAT SOP.
 *
 * Aplikasi yang diuji dijalankan dengan `--env=uat`, sehingga QUEUE_CONNECTION
 * bernilai `sync`. Tanpa itu, empat pekerjaan berantre SOP menunggu di Cloud
 * Tasks dan test akan menerbitkan paket lalu tidak menemukan apa-apa di SOP
 * Saya — kegagalan yang terlihat persis seperti bug fitur.
 *
 * SATU PEKERJA, TANPA PARALEL. Lapis ini menulis ke database dev yang dipakai
 * orang bekerja, dan server itu punya RCSI mati sehingga pembaca antre di
 * belakang penulis. Menjalankan spesifikasi serentak memperpanjang antrean itu
 * persis pada jam kerja.
 */

function envUat() {
  const berkas = path.resolve('.env.uat')
  if (!fs.existsSync(berkas)) return {}

  return Object.fromEntries(
    fs
      .readFileSync(berkas, 'utf8')
      .split('\n')
      .map((baris) => baris.trim())
      .filter((baris) => baris && !baris.startsWith('#') && baris.includes('='))
      .map((baris) => {
        const i = baris.indexOf('=')
        return [baris.slice(0, i).trim(), baris.slice(i + 1).trim()]
      }),
  )
}

const env = envUat()

export default defineConfig({
  testDir: './tests/e2e',
  // Alur SOP menyeberangi beberapa halaman Inertia dan menunggu antrean sync.
  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: 'storage/app/uat-auto/playwright.json' }]],
  use: {
    /* Variabel proses menang atas .env.uat: itu yang membuat satu jalan bisa
       diarahkan ke vhost Laragon tanpa mengubah berkas yang dipakai jalan
       lain. `php artisan serve` di Windows melayani SATU permintaan pada satu
       waktu, dan peramban sungguhan memuat puluhan aset sekaligus. */
    baseURL: process.env.UAT_BASE_URL || env.UAT_BASE_URL || 'http://127.0.0.1:5678',
    // Jejak hanya disimpan saat gagal: uji peramban yang gagal tanpa jejak
    // memaksa orang menebak apa yang terlihat di layar saat itu.
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 20_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
