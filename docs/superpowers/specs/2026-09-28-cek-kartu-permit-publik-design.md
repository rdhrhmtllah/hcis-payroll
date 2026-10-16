# Cek Kartu Permit Publik — Desain

Tanggal: 2026-09-28 · Branch: `feat/vms/api-cek-kartu-publik` · Modul: VISITOR › Permit › VehiclePermit

## Masalah

Management ingin QR pada kartu permit bisa dipindai dengan HP apa pun (publik), tetapi
domain dan server HCIS tidak boleh terekspos. Sebelumnya QR hanya berisi kode acak dan
hanya bisa diperiksa dari dalam HCIS oleh pengguna yang login.

## Keputusan (user, 28 Sep 2026)

- Layanan **terpisah** di Cloud Run (repo `rdhrhmtllah/cek-kartu-permit`), publik; QR
  kartu berisi URL layanan itu, bukan domain HCIS.
- Layanan itu memanggil HCIS **server-ke-server** sebagai aplikasi API terdaftar (pola
  sama dengan Web Careers dan mitra B2B) — status selalu terkini, termasuk pencabutan.
- Whitelist IP **tidak** dipakai (tidak bisa disetel); digantikan kontrol di bawah.

## Arsitektur

```
HP publik --scan--> cek-kartu.<domain>/k/<kode>   (Cloud Run, publik)
                          | HMAC-SHA512 + nonce + timestamp (hris.api.guard)
                          v
            HCIS  GET /api/v1/hcis/visitor/kartu-permit/{kode}
```

## Kontrol keamanan

| Kontrol | Tempat |
|---|---|
| HMAC + nonce + timestamp 60 dtk | `HrisApiGuard` (sudah ada) |
| Whitelist endpoint: aplikasi hanya boleh ke endpoint ini | DML-2026-143 |
| Jawaban minimal: status, kategori, plat tersamar, masa berlaku — tanpa nama/NIK/departemen/nomor transaksi/Id; alasan pencabutan tidak diteruskan | `VerifikasiKartuPermitService::keadaanPublik` |
| Kode kartu acak 128 bit; rute hanya menerima 32 heksa | `KartuPermitQr`, rute |
| Batas laju per aplikasi (600/menit, `VISITOR_KARTU_PUBLIK_PER_MENIT`) | `RouteServiceProvider` |
| Cache 30–60 dtk, batas laju per IP, galat HCIS tidak diteruskan | layanan publik |
| Kunci di Secret Manager, dirotasi; darurat: nonaktifkan aplikasi | DML-2026-143 |

Kunci yang bocor tidak memberi lebih dari memindai kartu sendiri: tidak ada pencarian,
daftar, maupun data pribadi.

## QR kartu

`VISITOR_KARTU_PUBLIK_URL` (mis. `https://cek-kartu.contoh.id/k`) → QR berisi
`<dasar>/<kode>`. Kosong → kode polos seperti sebelumnya. Pemindai di dalam HCIS membaca
kedua bentuk (`KartuPermitQr::kodeDariIsiQr`). Kartu lama (kode polos) dicetak ulang
bertahap; layanan publik menyediakan kolom ketik kode untuk masa transisi.

## Pengujian

`tests/Feature/VISITOR/Permit/VehiclePermit/KartuPermitPublikApiTest.php` — permintaan
bertanda tangan sungguhan lewat guard (kontrak penandatanganan layanan publik), data
minimal, pencabutan, kode tak dikenal, tanda tangan salah, bentuk kode, batas laju per
aplikasi, penyamaran plat, isi QR.
