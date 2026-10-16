# DDL-2026-127-visitor-kode-kartu-permit

## Ringkas

QR pada kartu permit yang ditempel di dashboard kini berisi **satu kode acak
kriptografis**, bukan nomor permit + tanda tangan HMAC `APP_KEY`.

| | |
|---|---|
| Change ID | `DDL-2026-127-visitor-kode-kartu-permit` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | — (kolom berdiri sendiri; dipakai fitur kartu & verifikasi QR) |
| Status | `DEV_EXECUTED` |

## Sebab

Bentuk lama `HCIS-PASS-1.<id permit>.<hmac>` punya dua kelemahan:

1. **Nomor internal permit ikut tercetak** di kartu yang ditempel di kaca mobil
   dan bisa dibaca siapa pun yang memindainya dengan kamera biasa.
2. **Seluruh kartu bergantung pada `APP_KEY`.** Rotasi kunci aplikasi —
   pekerjaan keamanan yang normal — mematikan semua kartu yang sudah tercetak
   sekaligus, dan gejalanya muncul sebagai "kartu tidak dikenali" di gerbang.

Kekuatan tanda tangannya sendiri tidak pernah jadi masalah; yang diperbaiki di
sini adalah apa yang ikut tercetak dan apa yang menjadi tumpuannya.

## Objek

**DDL (`forward.sql`)**

| Kolom / indeks | Keterangan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Permit.Kode_Kartu varchar(32) NULL` | 32 heksadesimal = 16 byte = 128 bit. `NULL` untuk permit yang kartunya belum pernah dicetak. |
| `UX_VISITOR_Permit_Kode_Kartu` | Unik **tersaring** `Kode_Kartu IS NOT NULL`. Indeks unik biasa akan menolak `NULL` kedua, padahal permit tanpa kode justru banyak. |

**DML (`data-change.sql`)**: isi `Kode_Kartu` untuk permit aktif yang belum
punya, memakai `CRYPT_GEN_RANDOM(16)` — pembangkit acak kriptografis, dan
sudah diperiksa di development bahwa ia dievaluasi **per baris**. `NEWID()`
sengaja tidak dipakai karena bukan CSPRNG.

## Yang menjaga kartu

- Kodenya acak 128 bit dan **hanya ada di basis data**. Tidak ada rumus yang
  menghubungkan kode dengan nomor permitnya, jadi memegang satu kartu tidak
  menolong siapa pun menebak kartu lain.
- Pemeriksa membaca **keadaan terkini** permit, bukan isi kertas: kartu yang
  sudah dicabut tetap terbaca tidak berlaku walau cetakannya mulus.
- Kode satu kartu bisa **diputar** (`KartuPermitQr::putar`) bila bocor; kartu
  lain tidak ikut mati.
- Dipindai kamera biasa atau Google Lens, yang muncul hanya deret heksadesimal
  — bukan tautan, jadi tidak ada halaman yang terbuka.

## Kode

`KartuPermitQr` (kode/putar/bacaId/dataUri), `VerifikasiKartuPermitController`
(dua pintu: karyawan hanya kartunya sendiri, admin bebas),
`exports/partials/_kartu-permit` (QR pada kartu).

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-21 | `forward.sql` | OK — kolom + indeks unik tersaring terbentuk. |
| 2026-09-21 | `data-change.sql` | OK — 40 permit aktif terisi, 40 kode unik. |

## Pekerjaan user

- Rekam di SQL Tracker; urutan `forward.sql` lalu `data-change.sql`.
- Kartu yang sudah terlanjur dicetak dengan kode bentuk lama **perlu dicetak
  ulang**: kode lamanya tidak lagi dikenali.
