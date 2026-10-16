/**
 * Pemformat tanggal untuk tampilan — satu rumah.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * "Format sebuah tanggal" tersalin 21 kali di halaman-halaman Vue, dalam DUA
 * varian yang BERNAMA SAMA (`formatDateString`) tetapi berperilaku berbeda pada
 * masukan yang tidak terurai:
 *
 *   16 salinan  membungkusnya dengan try/catch  -> mengembalikan teks aslinya
 *    5 salinan  tanpa try/catch                 -> MELEMPAR RangeError
 *
 * Bedanya tidak terlihat sampai datanya kotor, dan Intl memang melempar:
 *
 *   new Intl.DateTimeFormat('id-ID', opsi).format(new Date('bukan-tanggal'))
 *   -> RangeError: Invalid time value
 *
 * Di dalam render Vue, lemparan itu mematahkan komponennya — satu nilai tanggal
 * yang rusak menghapus seluruh panel, bukan satu selnya.
 *
 * Nilai kosong (null, undefined, '') sudah dijaga lebih dulu oleh KEDUA varian,
 * jadi yang membedakan hanyalah string tak-kosong yang tak bisa diurai. Perlu
 * disebut karena `new Date(null)` diam-diam bernilai 1 Januari 1970 — penjagaan
 * di depan itu bukan hiasan.
 *
 * ── Bentuk yang dipertahankan ───────────────────────────────────────────────
 *
 * Ketiga fungsi mempertahankan keluaran varian yang PALING BANYAK dipakai,
 * termasuk nilai baliknya saat kosong ('' untuk tanggal, 'N/A' untuk
 * tanggal-waktu). Menyeragamkan keduanya akan mengubah apa yang dibaca
 * pengguna, dan itu keputusan produk.
 */

/** Kembalikan teks aslinya bila Intl menolak; jangan pernah melempar ke render. */
function aman(nilai, format) {
  try {
    return format(new Date(nilai))
  } catch {
    return String(nilai)
  }
}

/**
 * "8 September 2026" — bulan panjang, tanpa waktu.
 * Kosong menghasilkan '' (bukan 'N/A'), seperti keenam belas salinan aslinya.
 */
export function tanggalPanjang(nilai) {
  if (!nilai) return ''

  return aman(nilai, (d) =>
    new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(d),
  )
}

/**
 * "8 Sep 2026, 14.30" — bulan pendek dengan jam.
 * Kosong menghasilkan 'N/A', seperti ketujuh salinan aslinya.
 */
export function tanggalWaktu(nilai) {
  if (!nilai) return 'N/A'

  return aman(nilai, (d) =>
    new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(d),
  )
}

/**
 * "2026-09-08" — bentuk yang dikirim ke server, bukan untuk dibaca manusia.
 *
 * Sengaja dibangun dari getFullYear/getMonth/getDate, BUKAN toISOString():
 * toISOString mengubahnya ke UTC lebih dulu, sehingga tanggal sore hari di
 * Asia/Jakarta mundur satu hari. Keenam salinan aslinya sudah benar soal ini,
 * dan sifat itu dijaga uji.
 */
export function tanggalIso(nilai) {
  if (!nilai) return ''

  const d = nilai instanceof Date ? nilai : new Date(nilai)
  if (Number.isNaN(d.getTime())) return ''

  const bulan = String(d.getMonth() + 1).padStart(2, '0')
  const hari = String(d.getDate()).padStart(2, '0')

  return `${d.getFullYear()}-${bulan}-${hari}`
}
