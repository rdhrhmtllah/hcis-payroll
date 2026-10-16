# DML-2026-109-anggota-verifikator-visitor

## Ringkas

Mengisi anggota role verifikator Visitor (`DML-2026-108`) beserta kunci halaman
yang menyertainya (`DML-2026-105`).

| Orang | Kode | User | ADMIN_PERMIT | ADMIN_TAMU |
|---|---|---|---|---|
| Arya | `C9` | 624 | ✔ | ✔ |
| Ridho *(login `NOIR`)* | `H45` | 4 | ✔ | ✔ |
| Andre | `B6` | 662 | ✔ | — |
| Imam | `J31` | 1885 | — | ✔ |

Kunci halaman diberikan mengikuti rolenya: `visitorVerifikatorPermitPage` untuk
pemegang `ADMIN_PERMIT`, `visitorVerifikatorTamuPage` untuk `ADMIN_TAMU`.

## Catatan identitas

**"Noir" pada permintaan user adalah USERNAME, bukan nama karyawan.**
Pemiliknya Ridho (`H45`, user 4). Tidak ada karyawan bernama Noir di basis data
dan hanya satu baris login yang cocok, jadi pemetaannya tidak ambigu — tetapi
dicatat di sini supaya keliru-orang dapat ditelusuri, bukan ditebak ulang kelak.

## Keputusan desain

**Role dan kunci halaman diberikan bersamaan, dari daftar yang SAMA.** Keduanya
hal terpisah: role menentukan kepada siapa pengajuan dikirim, kunci halaman
menentukan siapa boleh membukanya. Memberi salah satunya saja menghasilkan orang
yang menerima antrean tanpa bisa memprosesnya — atau membuka meja yang selamanya
kosong. Dua-duanya tidak menampilkan galat apa pun. Karena itu sasarannya
dituliskan sekali di satu tabel dan dipakai dua kali; dua daftar terpisah akan
menyimpang saat salah satunya disunting.

**Ada penjaga yang membatalkan seluruh transaksi bila sasarannya keliru.** Baris
dengan `UserID_Web` yang bukan karyawan aktif akan lolos diam-diam sebagai
keanggotaan yatim: role terlihat terisi, tetapi `isRoleApprover()` menolaknya
karena JOIN ke `Karyawan` tidak menghasilkan baris. Antreannya tampak berpemilik
padahal tidak.

**Dimensi perusahaan/level/divisi diambil dari baris akses milik orangnya
sendiri**, bukan ditulis tangan — nilainya berbeda per orang, dan angka yang
dikarang membuat barisnya tidak pernah cocok dengan penyaring menu.

**Keanggotaan yang sudah ada tidak dicabut.** Change ini murni penambahan;
pencabutan adalah keputusan tersendiri dan pantas punya jejaknya sendiri.

## Status

Development: **DONE** (2026-09-15, `Web_HRIS`).

Diverifikasi dari basis data **dan dari perilaku aplikasinya** — yang kedua
adalah pembuktian sebenarnya, karena keanggotaan yang benar di tabel tetap bisa
ditolak `isRoleApprover()`:

- Keanggotaan role: `ADMIN_PERMIT` = Andre, Arya, Ridho; `ADMIN_TAMU` = Arya,
  Imam, Ridho.
- Kunci halaman: persis mengikuti rolenya, satu-ke-satu.
- Pada pengajuan permit yang benar-benar hidup (`PMT0926-0013`):
  Arya **boleh**, Ridho **boleh**, Andre **boleh**, Imam **tidak**.
- Pada alur tamu yang dibuat khusus untuk diuji lalu di-*rollback*:
  Arya **boleh**, Ridho **boleh**, Imam **boleh**, Andre **tidak**.

Pemisahannya karena itu bukan lagi sekadar konfigurasi — ia benar-benar menolak
orang yang salah pada kedua arah.

## Yang perlu diketahui: antrean tamu masih tidur

`VISITOR_TAMU_NEEDS_APPROVAL` bernilai **false**, jadi registrasi tamu
auto-approve dan `createFlow('tamu', ...)` berhenti lebih awal — **tidak satu
pun pengajuan tamu yang benar-benar terbit ke `ADMIN_TAMU` hari ini.** Itulah
sebabnya pembuktian sisi tamu di atas harus menyalakan kebijakannya sementara di
dalam proses uji.

Wewenangnya sudah terpasang dan benar; ia baru akan terpakai ketika approval
tamu dinyalakan. Sampai saat itu, Imam akan melihat meja Verifikator Tamu yang
kosong — bukan karena haknya kurang.

Sebagai catatan terkait: dari 17 pengajuan Visitor yang belum berkeputusan, **14
di antaranya sudah EXPIRED** (`Expired_At` terlampaui), bukan menunggu. Hanya 4
yang benar-benar berstatus CURRENT, dan keempatnya permit.

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-109-anggota-verifikator-visitor/data-change.sql` |

Tidak ada DDL dan tidak ada perubahan kode.

## Pekerjaan user

- Merekam change ini di SQL Tracker bersama `DML-2026-105`…`108`, lalu
  mengeksekusi ke production dan memverifikasinya. **UserID_Web di production
  belum tentu sama dengan development** — periksa keempat id-nya sebelum
  menjalankan; penjaga di dalam skrip akan membatalkan seluruh transaksi bila
  ada yang bukan karyawan aktif, tetapi ia tidak bisa mendeteksi id yang
  kebetulan valid namun milik orang lain.
- Memutuskan apakah approval tamu dinyalakan (`VISITOR_TAMU_NEEDS_APPROVAL`).
  Tanpa itu, role `ADMIN_TAMU` tidak pernah menerima apa pun.
