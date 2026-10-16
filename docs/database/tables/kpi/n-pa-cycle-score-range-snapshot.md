# N_PA_Cycle_Score_Range_Snapshot

Salinan pita nilai yang berlaku di satu siklus: konversi antar skala, pita tiga
tingkat HAV Map, dan pita grade beserta target distribusinya.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-071-performance-appraisal-cycle-rule-snapshots |
| Forward SQL | `database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Score_Range_Snapshot` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `PA_Score_Range_Id` | `int` | Y | | FK jejak asal; NULL bila diisi manual |
| `Score_Type` | `varchar(20)` | T | | FK ke `N_PA_Score_Type.Kode_Type` |
| `Score_Usage` | `varchar(20)` | T | | FK ke `N_PA_Score_Usage.Kode_Usage` |
| `Kode_Range` | `varchar(40)` | T | | TINGGI/SEDANG/RENDAH untuk HAVMAP; A/B+/B/C+/C untuk GRADE |
| `Nama_Range` | `nvarchar(100)` | T | | |
| `Nilai_Dari` | `decimal(6,2)` | T | | Batas bawah, **inklusif** |
| `Nilai_Sampai` | `decimal(6,2)` | T | | Batas atas, **eksklusif** |
| `Nilai_Hasil` | `decimal(6,2)` | Y | | Hasil konversi; hanya untuk `KONVERSI` |
| `Target_Persen` | `decimal(5,2)` | Y | | Target distribusi; hanya untuk `GRADE` |
| `Warna` | `varchar(20)` | Y | | |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Snapshot_By` | `int` | Y | | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa pita nilai perlu disalin

Menggeser batas pita menggeser grade orang. Siklus 2025 yang sudah selesai harus tetap
bisa dibaca dengan batas yang berlaku saat itu, bukan dengan batas yang direvisi HC
setahun kemudian. Itu sebabnya `N_PA_Score_Range` digolongkan **aturan**, bukan kamus
(K26), dan aturan disalin.

## Score_Type dan Score_Usage tetap ber-foreign key di sini

Salinan bukan alasan untuk menerima ejaan yang ditolak masternya. Kedua kolom menunjuk
master yang sama dari DDL-2026-070, sehingga `COMPETENCY` alih-alih `KOMPETENSI` tetap
ditolak walaupun ini tabel salinan.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Score_Range_Snapshot` | Primary key | |
| `FK_PA_Cycle_Score_Snap_Cycle` | Foreign key | → `N_PA_Cycle` |
| `FK_PA_Cycle_Score_Snap_Source` | Foreign key | → `N_PA_Score_Range`, nullable |
| `FK_PA_Cycle_Score_Snap_Type` | Foreign key | → `N_PA_Score_Type` |
| `FK_PA_Cycle_Score_Snap_Usage` | Foreign key | → `N_PA_Score_Usage` |
| `UX_PA_Cycle_Score_Snap_Aktif` | Unique index tersaring | `(PA_Cycle_Id, Score_Type, Score_Usage, Kode_Range)` saat aktif |
| `IX_PA_Cycle_Score_Snap_Lookup` | Index | `(PA_Cycle_Id, Score_Type, Score_Usage, Flag_Aktif)` dengan `INCLUDE` seluruh kolom nilai — jalur "nilai sekian masuk pita mana" |
| `CK_PA_Cycle_Score_Snap_Batas` | Check | `Nilai_Sampai >= Nilai_Dari` |
| `CK_PA_Cycle_Score_Snap_Target` | Check | `Target_Persen` NULL atau 0–100 |
| `CK_PA_Cycle_Score_Snap_Urutan` | Check | `Urutan_Tampil >= 1` |

## Yang sengaja tidak dijaga database

Pita dalam satu gabungan sumbu dan kegunaan tidak boleh tumpang tindih, dan total
`Target_Persen` seluruh grade aktif harus 100. Keduanya lintas baris — gerbang
aplikasi.

Kolom `Nilai_Hasil` hanya bermakna untuk `KONVERSI` dan `Target_Persen` hanya untuk
`GRADE`; pemetaan itu tersimpan sebagai data di `N_PA_Score_Usage`, tetapi
penegakannya melibatkan dua tabel sehingga tetap aplikasi yang menjaganya.

## Kedudukan

Lapisan kedua, membeku saat kunci. **Berhenti di sini, tanpa lapisan per orang** — dua
orang bernilai sama harus mendapat grade sama.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong. Blueprint-nya pun kosong karena skala kanonik dan batas pitanya masih terbuka
(T05).
