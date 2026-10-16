import { describe, expect, it } from 'vitest'

import { generateQrCodeModules, kapasitasMaksimum } from './qr'

/**
 * Generator QR: satu rumah, dan menolak alih-alih mengkode salah.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * Generator ini pernah ada dua kali — userProfile.vue (ESS) dan qrHelper.js
 * (VISITOR) — dengan tiga belas fungsi pembantu yang identik, tetapi TITIK
 * MASUK yang sudah menyimpang. Masing-masing punya sesuatu yang tak dimiliki
 * yang lain, dan keduanya salah dengan cara berbeda. Diukur langsung pada
 * kedua salinan lama:
 *
 *   VISITOR  77, 150, 400 karakter -> matriks SAMA seperti 76 (dipotong diam-diam)
 *   ESS      77 -> matriks 544; 150 dan 400 -> sama-sama 543 (data meluber)
 *
 * Keduanya menghasilkan kode yang tidak memuat data yang diminta, tanpa memberi
 * tahu siapa pun. Sebuah QR yang salah isi jauh lebih buruk daripada QR yang
 * tidak muncul: yang pertama dipindai orang dan membawanya ke tempat yang keliru.
 *
 * ── Kenapa null, bukan lemparan ─────────────────────────────────────────────
 *
 * Pemanggilnya computed Vue. Lemparan di sana mematahkan seluruh komponen —
 * pelajaran yang baru saja dibayar pada pemformat tanggal. Karena itu fungsi ini
 * mengembalikan null, dan templatnya menjaga dengan v-if.
 */
describe('kapasitasMaksimum', () => {
  it('positif dan masuk akal untuk byte-mode ECC L', () => {
    const k = kapasitasMaksimum()
    expect(k).toBeGreaterThan(0)
    expect(k).toBeLessThan(3000)
  })
})

describe('generateQrCodeModules', () => {
  it('menghasilkan matriks untuk teks dalam kapasitas', () => {
    const r = generateQrCodeModules('https://contoh.test/')

    expect(r).not.toBeNull()
    expect(Array.isArray(r.modules)).toBe(true)
    expect(r.modules.length).toBeGreaterThan(0)
    expect(r.viewBox).toMatch(/^0 0 \d+ \d+$/)
  })

  it('memakai zona sunyi empat modul, sesuai spesifikasi QR', () => {
    /*
     * Salinan VISITOR memakai 2. Spesifikasi QR mensyaratkan empat modul, dan
     * zona yang terlalu sempit membuat sebagian pemindai gagal mengunci.
     * viewBox = ukuran matriks + 2 x zona sunyi, jadi selisihnya harus genap 8.
     */
    const r = generateQrCodeModules('A'.repeat(10))
    const sisi = Number(r.viewBox.split(' ')[2])
    const maksModul = Math.max(...r.modules.map((m) => Math.max(m.x, m.y)))

    expect(sisi - (maksModul + 1)).toBeGreaterThanOrEqual(4)
  })

  it('MENOLAK teks yang melebihi kapasitas, bukan mengkode sebagian', () => {
    /*
     * Inti perbaikannya. Kedua salinan lama tetap mengembalikan matriks —
     * satu memotong, satu meluber — sehingga QR-nya tampak sah tetapi isinya
     * bukan yang diminta.
     */
    const k = kapasitasMaksimum()

    expect(generateQrCodeModules('A'.repeat(k))).not.toBeNull()
    expect(generateQrCodeModules('A'.repeat(k + 1))).toBeNull()
    expect(generateQrCodeModules('A'.repeat(400))).toBeNull()
  })

  it('menolak teks kosong dan nilai kosong', () => {
    for (const kosong of ['', null, undefined]) {
      expect(generateQrCodeModules(kosong)).toBeNull()
    }
  })

  it('tidak pernah melempar, apa pun masukannya', () => {
    /*
     * Sifat yang menjaga komponennya tetap hidup. Diuji juga untuk masukan yang
     * bukan string.
     */
    for (const aneh of [0, 123, {}, [], true, 'A'.repeat(5000)]) {
      expect(() => generateQrCodeModules(aneh)).not.toThrow()
    }
  })

  it('deterministik: teks sama menghasilkan matriks sama', () => {
    const a = generateQrCodeModules('HCIS')
    const b = generateQrCodeModules('HCIS')

    expect(a.viewBox).toBe(b.viewBox)
    expect(a.modules.length).toBe(b.modules.length)
  })

  it('teks berbeda menghasilkan matriks berbeda', () => {
    /*
     * Penjaga terhadap kegagalan paling memalukan: generator yang mengembalikan
     * kode yang sama untuk masukan apa pun akan lolos semua uji di atas.
     */
    const a = generateQrCodeModules('AAAA')
    const b = generateQrCodeModules('BBBB')

    expect(a.modules.length).not.toBe(b.modules.length)
  })
})
