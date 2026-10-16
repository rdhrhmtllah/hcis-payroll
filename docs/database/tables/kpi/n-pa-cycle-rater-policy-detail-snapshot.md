# N_PA_Cycle_Rater_Policy_Detail_Snapshot

Salinan aturan per kategori penilai milik satu siklus: bobot antar kategori, jumlah
penilai minimum dan maksimum, serta tipe relasi sumber kandidat. Inilah yang dibaca
perhitungan, bukan `N_PA_Rater_Policy_Detail` yang masih hidup.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-071-performance-appraisal-cycle-rule-snapshots |
| Forward SQL | `database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Rater_Policy_Detail_Snapshot` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Rater_Policy_Snapshot_Id` | `int` | T | | FK ke salinan header |
| `PA_Rater_Policy_Detail_Id` | `int` | T | | FK jejak asal ke detail blueprint |
| `PA_Rater_Category_Id` | `int` | T | | FK ke master kategori penilai |
| `Kode_Kategori_Salinan` | `varchar(40)` | T | | Salinan ATASAN / BAWAHAN / PEER / SELF |
| `Nama_Kategori_Salinan` | `nvarchar(100)` | T | | Salinan nama untuk histori |
| `Organization_Relationship_Type_Id` | `int` | Y | | FK jejak asal tipe relasi; NULL untuk SELF |
| `Kode_Relasi_Salinan` | `varchar(50)` | Y | | Salinan kode relasi agar hasil lama dapat direproduksi |
| `Bobot_Persen` | `decimal(5,2)` | T | | Bobot kategori yang sudah dibekukan |
| `Min_Rater` | `smallint` | T | | Jumlah minimum penilai |
| `Maks_Rater` | `smallint` | Y | | NULL berarti tanpa batas |
| `Min_Rater_Selesai` | `smallint` | Y | | NULL berarti semua penilai yang ditugaskan harus selesai |
| `Flag_Izinkan_Manual` | `char(1)` | T | `'T'` | Izin override manual |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Catatan_Aturan_Salinan` | `nvarchar(500)` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Snapshot_By` | `int` | Y | | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa tipe relasi ikut disalin sebagai kode

`Organization_Relationship_Type_Id` menjaga jejak asal, tetapi `Kode_Relasi_Salinan`
yang membuat hasil lama tetap terbaca kalau master relasi disunting atau tipe itu
dinonaktifkan belakangan. Tanpa salinan kodenya, laporan siklus lama akan menampilkan
tipe yang sudah berubah artinya.

## Tidak ada kolom metode agregasi

Nilai kategori adalah rata-rata **tertimbang** dari penilai di dalamnya, memakai
`Bobot_Dalam_Kategori` pada baris penugasan masing-masing (K18), lalu dikalikan
`Bobot_Persen` di sini. Bobot rata tetap jadi baku, bukan aturan: dua atasan tanpa
penyetelan admin berarti 50/50.

Perhatikan bedanya dua bobot ini. `Bobot_Persen` di sini bobot **antar** kategori dan
berlaku selevel; `Bobot_Dalam_Kategori` nanti bobot **di dalam** satu kategori dan
berlaku seorang. Keduanya dikalikan berurutan, jangan ditukar.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Rater_Policy_Detail_Snapshot` | Primary key | |
| `FK_PA_Cycle_Rater_Det_Snap_Header` | Foreign key | → salinan header |
| `FK_PA_Cycle_Rater_Det_Snap_Source` | Foreign key | → `N_PA_Rater_Policy_Detail` |
| `FK_PA_Cycle_Rater_Det_Snap_Category` | Foreign key | → `N_PA_Rater_Category` |
| `FK_PA_Cycle_Rater_Det_Snap_Org_Type` | Foreign key | → master tipe relasi organisasi |
| `UX_PA_Cycle_Rater_Det_Snap_Aktif` | Unique index tersaring | satu kategori aktif per salinan header |
| `CK_..._Bobot` / `_Min` / `_Maks` / `_Selesai` / `_Urutan` | Check | Diulang persis dari tabel sumbernya |

Seluruh CHECK dari tabel sumber sengaja diulang di sini. Salinan yang bisa menampung
bobot di atas 100 atau maksimum di bawah minimum bukan salinan yang setia.

## Yang sengaja tidak dijaga database

Total `Bobot_Persen` seluruh baris aktif di bawah satu header harus 100. Aturan itu
lintas baris — gerbang aplikasi saat siklus dikunci.

## Kedudukan

Lapisan kedua, membeku saat kunci. Inilah bobot kategori yang berlaku bagi seluruh
peserta di levelnya; satu orang menyimpang lewat `N_PA_Participant_Rater_Override`
(DDL-074), yang menunjuk balik ke baris ini sebagai jejak asalnya.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
