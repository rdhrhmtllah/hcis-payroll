# DML-2026-130-visitor-selaraskan-flag-batal

## Ringkas

Menyelaraskan `Flag_Batal` dengan `Status_Efektif` pada dua tabel proyeksi yang
dibaca **aplikasi palang pihak ketiga** langsung dari basis data.

| | |
|---|---|
| Change ID | `DML-2026-130-visitor-selaraskan-flag-batal` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | — (koreksi data; kodenya sudah benar sejak DDL-2026-121) |
| Status | `DEV_EXECUTED` |

## Kontrak yang ditegakkan

Aplikasi palang tidak membaca layar HCIS; ia membaca dua kolom bendera:

| `Flag_Sync` | `Flag_Batal` | Arti bagi palang |
|---|---|---|
| `T` | `T` | Ada perubahan, **tarik**; akses berlaku sesuai jendela waktu |
| `T` | `Y` | Ada perubahan, **tarik**; akses **dibatalkan** |
| `Y` | `T` | Sudah tersinkron, tidak ada kerjaan — keadaan diam yang normal |
| `Y` | `Y` | Pembatalan sudah ditarik, lewati |

Aturannya satu arah dan tanpa pengecualian:

```
Status_Efektif  = 'DIBATALKAN'  <=>  Flag_Batal = 'Y'
Status_Efektif <> 'DIBATALKAN'  <=>  Flag_Batal = 'T'
setiap perubahan baris           =>  Flag_Sync  = 'T'
```

`EXPIRED` **tidak** ikut `Flag_Batal='Y'`. Kedaluwarsa bukan pencabutan: yang
pertama berakhir dengan sendirinya lewat jendela waktu, yang kedua adalah
keputusan orang. Petugas pos mencatat alasan penolakan dari perbedaan itu.

## Sebab

Pemindaian development menemukan **11 baris yang melanggar kontrak** — semuanya
bertanggal Agustus–September 2026 dan mendahului pekerjaan akses inspeksi:

| Tabel | Keadaan | Jumlah | Akibatnya di palang |
|---|---|---|---|
| `N_VISITOR_Permit_Efektif` | `DIBATALKAN` tapi `Flag_Batal='T'`, `Flag_Sync='Y'` | 2 | **Kendaraan yang sudah dicabut tetap dianggap sah**, dan karena sudah tersinkron ia tak akan pernah ditarik ulang. |
| `N_VISITOR_Permit_Efektif` | `AKTIF` tapi `Flag_Batal='Y'` | 2 | Permit sah ditolak di gerbang. |
| `N_VISITOR_Tamu_Efektif` | `AKTIF` tapi `Flag_Batal='Y'` | 7 | Tamu terjadwal ditolak di gerbang. |

Baris pertama itu yang berbahaya: kegagalannya **senyap dan ke arah yang
salah** — tidak ada galat, tidak ada catatan, hanya palang yang terbuka untuk
kendaraan yang seharusnya sudah ditolak.

`Status_Efektif` dipakai sebagai sumber kebenaran, bukan benderanya. Seluruh
baris yang bertentangan diperiksa satu per satu lebih dulu dan `Dibatalkan_At`
selalu sependapat dengan statusnya: yang `DIBATALKAN` punya `Dibatalkan_At`
terisi, yang `AKTIF` kosong dan headernya masih berjalan (`Flag_Approval='Y'`
atau `Status_Kunjungan='SCHEDULED'`). Jadi benderanya yang tertinggal.

## Jalur kode

Jalur pencabutan sendiri **sudah benar** dan tidak diubah oleh change ini:
`VisitorEfektifService::revokePermitEfektif()`, `revokePermitEfektifMany()`,
dan `revokeEfektifRow()` ketiganya menulis `Flag_Batal='Y'` + `Flag_Sync='T'`
dalam satu pernyataan yang sama dengan `Status_Efektif='DIBATALKAN'`, sehingga
keduanya tak bisa berpisah. `ExpireVisitorEfektif` menulis `Flag_Sync='T'`
tanpa menyentuh `Flag_Batal`, sesuai aturan di atas.

Dua celah pada jalur **penulisan biasa** ditutup di commit yang sama dengan
change ini (bukan bagian dari artefak SQL):

1. `syncPermitEfektif()` / `syncTamuEfektif()` hanya menyetel `Flag_Sync='T'`
   pada INSERT. Pada UPDATE benderanya dibiarkan `'Y'`, jadi setiap perubahan
   pada baris yang sudah pernah ditarik — persetujuan, ralat plat, geser
   jadwal — tidak pernah sampai ke palang.
2. `akhiriAksesInspeksi()` tidak menyetel bendera sama sekali, jadi pengakhiran
   akses inspeksi sementara juga tak terkirim.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `data-change.sql` | OK — 11 baris diselaraskan (permit 4, tamu 7); kueri verifikasi 0 dan 0. |
| 2026-09-22 | `data-change.sql` (ulang) | OK — tidak ada baris tersentuh; jumlah yang menunggu sync tetap 17/7. Idempoten terbukti. |

## Pekerjaan user

- Rekam di SQL Tracker. Tidak ada urutan khusus; berdiri sendiri.
- **Periksa production sebelum menjalankan.** Kueri verifikasi di kaki
  `data-change.sql` memberi jumlahnya. Bila baris "dicabut tapi belum bertanda
  batal" ada di production, itu berarti kendaraan yang sudah dicabut masih
  bisa membuka palang sampai change ini dijalankan.
