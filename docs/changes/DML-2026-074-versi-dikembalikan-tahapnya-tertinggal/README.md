# DML-2026-074 — Versi yang dikembalikan: tahap & tanggal digantikan tertinggal

| | |
|---|---|
| Change ID | `DML-2026-074-versi-dikembalikan-tahapnya-tertinggal` |
| Jenis | DML (tanpa DDL) |
| Target | `Web_HRIS` (development) |
| Status | **DEV DONE** — dijalankan 31 Agu 2026. SQL Tracker & produksi `PENDING`. |
| Artefak | `database/dml/DML-2026-074-versi-dikembalikan-tahapnya-tertinggal/data-change.sql` |

## Kenapa

`SopItemVersionService::rollback()` mengembalikan versi lama menjadi berlaku
dengan menulis `Status='PUBLISHED'` **saja**. `publish()` pada berkas yang sama
menggeser `Tahap_Id` juga — rollback tidak. Satu aturan ditulis di dua pintu,
dan hanya satu yang dirawat.

Akibatnya versi yang **sedang berlaku**:

| Gejala | Yang dilihat admin |
|---|---|
| `Tahap_Id` tetap `OBSOLETE` | stepper wizard menampilkannya sebagai arsip |
| `Tanggal_Obsolete` tetap terisi | riwayat menyebutnya "digantikan pada" tanggal yang sudah lewat |
| `Status` ≠ `Status_Teknis` tahap | dua sumber untuk satu keadaan — persis yang rancangan tahap ini ada untuk mencegahnya |

Cacat ini **lebih tua** dari jalur pembatalan versi terbit (DML-2026-064); yang
baru hanyalah membuatnya sering terjadi, karena membatalkan versi terbit selalu
mengembalikan penggantinya lewat `rollback()`.

## Yang diperbaiki di kode

`SopItemVersionService::rollback()` kini menggeser `Tahap_Id` ke
`FILING_REGISTERED` dan mengosongkan `Tanggal_Obsolete` pada versi target —
mencerminkan `publish()`.

## Ruang lingkup — dicari lewat POLA, bukan id yang dipatok

```
Flag_Aktif = 'Y'  AND  Status = 'PUBLISHED'  AND  Kode_Tahap <> 'FILING_REGISTERED'
```

| Tabel | Operasi | Terduga | Hasil |
|---|---|---|---|
| `N_SOP_Item_Versi` | UPDATE | 1 (versi `12139`, dok `17054`) | 1 ✔ |

```
SEBELUM  id=12139 v00 PUBLISHED flag=Y tahap=OBSOLETE          obsolete=2026-08-30
SESUDAH  id=12139 v00 PUBLISHED flag=Y tahap=FILING_REGISTERED obsolete=NULL
```

## Verifikasi sesudahnya

```
A1 Status != Status_Teknis                  : 0
B1 pointer -> bukan PUBLISHED               : 0
B4 pointer -> baris tak ada                 : 0
B5 dokumen >1 PUBLISHED hidup               : 0
B6 pointer -> versi mati                    : 0
C1 nomor kembar antar versi hidup           : 0
D1 PUBLISHED hidup ber-Tanggal_Obsolete     : 0
E1 tahap menonaktifkan tapi Flag_Aktif='Y'  : 0
```

## Sisa pekerjaan user

1. Catat ke SQL Tracker, isi `SQL_Tracker_Reference`, ubah `Status_SQL_Tracker`.
2. Jalankan di produksi **bersama** perbaikan kodenya — data yang dibereskan
   akan rusak lagi pada rollback berikutnya bila kodenya tertinggal.
3. Perbarui `Status_Production` dan `Status_Verification`.
