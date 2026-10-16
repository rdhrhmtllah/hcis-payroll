# N_PA_Assignment_Integration

Jembatan satu penugasan ke HC Learn: token ujiannya, balasan mentah saat token dicetak,
dan jendela waktu pengerjaannya.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Assignment |
| Change ID | DDL-2026-075-performance-appraisal-assignment |
| Forward SQL | `database/ddl/DDL-2026-075-performance-appraisal-assignment/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Assignment_Integration` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Assignment_Id` | `int` | T | | FK ke `N_PA_Assignment` |
| `PA_Cycle_Question_Package_Id` | `int` | Y | | Paket soal yang dikirimkan; jejak kontrak versi mana |
| `Id_Ujian_Token` | `varchar(255)` | Y | | Token dari HC Learn; **kosong berarti belum pernah dikirim** |
| `Payload_Json` | `nvarchar(max)` | Y | | Balasan mentah saat token dicetak; `Link_Ujian` diekstrak dari sini |
| `Started_At` | `datetime2(0)` | Y | | Awal jendela ujian |
| `Finished_At` | `datetime2(0)` | Y | | Akhir jendela; dipakai memeriksa token kedaluwarsa |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` memensiunkan baris saat paket soalnya diganti |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa tabel sendiri, bukan kolom di penugasan

Karena dua modul yang sudah berjalan melakukannya begitu, dan itu diperiksa di kode
bukan diingat-ingat. `N_LMS_Penjadwalan_Det` (Training) dan `N_SOP_Penjadwalan_Detail`
(SOP Kuis) sama sekali **tidak punya kolom token**; tokennya ada di
`N_LMS_Penjadwalan_Integrasi` dan `N_SOP_Penjadwalan_Integrasi`, masing-masing bersama
`Payload_Json` dan jendela `Started_At`/`Finished_At`.

Alasan bentuknya nyata: satu penugasan bisa punya lebih dari satu baris di sini.

## Banyak baris, satu yang aktif

Di SOP, baris baru lahir **hanya** ketika admin mengganti versi soal. Kirim ulang dan
perpanjangan selalu `UPDATE` baris yang sama, dengan alasan yang ditulis eksplisit di
kodenya: membuang token berarti hasil ujian yang sedang dikirim ulang HC Learn tidak
dikenali lagi. Aturan yang sama berlaku di sini, dijaga
`UX_PA_Assignment_Integration_Aktif`.

## Callback mencari lewat token, tanpa menyaring Flag_Aktif

Orang yang telanjur mulai mengerjakan versi lama tetap harus boleh menyetor. Jadi
pencarian dari callback **sengaja** mengabaikan `Flag_Aktif`. Perilaku ini diuji: baris
yang sudah dinonaktifkan tetap ditemukan lewat tokennya.

## Satu hal yang sengaja dibuat lebih baik dari acuannya

`SopKuisCallbackService` terpaksa mencari barisnya dengan `orderByDesc(...)->first()`
karena kolom tokennya tidak dijamin unik — artinya ia menebak baris mana yang dimaksud.
Di sini `UX_PA_Assignment_Integration_Token` adalah unique index tersaring pada
`Id_Ujian_Token` saat terisi, sehingga pencarian callback deterministik. Baris yang
belum pernah dikirim tetap boleh banyak, karena NULL tidak saling bentrok pada index
tersaring — kedua sisinya sudah diuji.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Assignment_Integration` | Primary key | |
| `FK_PA_Assignment_Integration_Assignment` | Foreign key | → `N_PA_Assignment` |
| `FK_PA_Assignment_Integration_Package` | Foreign key | → `N_PA_Cycle_Question_Package`, nullable |
| `UX_PA_Assignment_Integration_Token` | Unique index tersaring | `WHERE Id_Ujian_Token IS NOT NULL` |
| `UX_PA_Assignment_Integration_Aktif` | Unique index tersaring | Satu baris aktif per penugasan |
| `CK_PA_Assignment_Integration_Jendela` | Check | Akhir tidak boleh mendahului awal |

## Yang sengaja tidak disimpan

Kolom status kirim, penghitung percobaan, hash, dan versi API. Kedua modul acuan pun
tidak memilikinya — status "sudah dikirim atau belum" disimpulkan dari `Id_Ujian_Token`
kosong atau tidak, dan sisanya cukup terekam di `Payload_Json`.

Hasil ujiannya juga bukan urusan tabel ini. Payload balasan **hasil** akan masuk ke
`N_PA_Assignment_Payload` yang sisip saja; yang di sini hanya balasan saat token
dicetak.

## Kedudukan

Lapisan pelaksanaan, anak dari `N_PA_Assignment`.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
