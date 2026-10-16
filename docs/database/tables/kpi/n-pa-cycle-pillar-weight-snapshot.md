# N_PA_Cycle_Pillar_Weight_Snapshot

Pembagian pilar KPI berbanding kompetensi yang berlaku di satu siklus. Satu baris per
pasangan siklus dan level. **Satu-satunya salinan siklus yang sengaja tidak membeku
saat kunci.**

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-071-performance-appraisal-cycle-rule-snapshots |
| Forward SQL | `database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Pillar_Weight_Snapshot` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `ID_Level` | `bigint` | T | | FK ke `HRIS_Level` |
| `PA_Pillar_Weight_Id` | `int` | Y | | FK jejak asal ke cetak biru; **NULL bila diisi manual** |
| `Bobot_KPI` | `decimal(5,2)` | T | | Porsi pilar KPI |
| `Bobot_Kompetensi` | `decimal(5,2)` | T | | Porsi pilar kompetensi |
| `Alasan_Perubahan` | `nvarchar(500)` | Y | | Wajib diisi aplikasi bila diubah setelah pengisian ditutup |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | Kapan disalin dari cetak biru |
| `Snapshot_By` | `int` | Y | | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | Perubahan manual terakhir |

## Kenapa tabel ini tidak membeku (K23)

Bobot penilai wajib pasti **sebelum** pengisian dimulai, karena angkanya bukan sekadar
pengali — dia juga menentukan siapa ditugaskan lewat `Min_Rater`, `Maks_Rater`, dan
tipe relasi. Bobot pilar tidak menentukan apa pun soal siapa mengisi apa; dia baru
dipakai di langkah terakhir, saat seluruh skor mentah sudah ada.

Kalau ikut dibekukan, admin yang ingin mengubah 60/40 jadi 70/30 setelah semua orang
selesai menilai akan buntu: salinan beku memang sengaja tidak boleh berubah, sehingga
satu-satunya jalan adalah menembus jaminan utama desain ini. Padahal nilai akhir itu
angka turunan — skor mentah tersimpan permanen di `N_PA_Assignment_Score`, jadi
menghitung ulang dengan bobot berbeda adalah operasi wajar, bukan pelanggaran.

## Akibat pada bentuknya

**Tanpa `Flag_Aktif`.** Empat salinan siklus lain memakai flag itu karena salinan yang
keliru dipensiunkan lalu diganti. Yang ini disunting di tempat, jadi keunikannya tidak
disaring: tepat satu baris per pasangan siklus dan level, selalu.

**Punya `Alasan_Perubahan`.** Karena boleh berubah setelah pengisian selesai,
perubahannya wajib bersebab. Kolom ini yang menampungnya.

**Jejak asal nullable.** Admin boleh memasukkan angka yang tidak pernah ada di cetak
biru; dalam kasus itu `PA_Pillar_Weight_Id` kosong.

## Jejak audit

Bobot yang benar-benar dipakai menghitung disalin lagi ke `N_PA_Result` sebagai
`Bobot_KPI_Terpakai` dan `Bobot_Kompetensi_Terpakai`. Tanpa itu, menghitung ulang akan
menghapus keterangan hasil lama dihitung dengan angka berapa.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Pillar_Weight_Snapshot` | Primary key | |
| `FK_PA_Cycle_Pillar_Snap_Cycle` | Foreign key | → `N_PA_Cycle` |
| `FK_PA_Cycle_Pillar_Snap_Level` | Foreign key | → `HRIS_Level` |
| `FK_PA_Cycle_Pillar_Snap_Source` | Foreign key | → `N_PA_Pillar_Weight`, nullable |
| `UX_PA_Cycle_Pillar_Snap_Cycle_Level` | Unique index | `(PA_Cycle_Id, ID_Level)`, **tidak** disaring, dengan `INCLUDE` kedua bobot |
| `CK_PA_Cycle_Pillar_Snap_Total` | Check | `Bobot_KPI + Bobot_Kompetensi = 100` |
| `CK_PA_Cycle_Pillar_Snap_Non_Negatif` | Check | Kedua bobot `>= 0` |

Aturan jumlah 100 bisa dijaga CHECK di sini karena keduanya kolom pada baris yang
sama — beda dari total bobot kategori penilai yang lintas baris dan terpaksa jadi
gerbang aplikasi.

## Aturan aplikasi

- Baris dibuat saat siklus masuk `PREPARED`, satu per level yang punya peserta,
  disalin dari `N_PA_Pillar_Weight`.
- Boleh diubah sampai siklus `CLOSED`. Bila siklus sudah `PUBLISHED` lalu bobotnya
  diubah, hasil terbit menjadi basi — status terbitnya wajib ditarik sampai
  perhitungan ulang selesai.
- Peserta yang levelnya tidak punya baris di sini **memblokir perhitungan**, bukan
  memblokir penguncian. Dua hal itu terjadi di waktu berbeda.

## Kedudukan

Lapisan kedua. Berhenti di sini, tanpa lapisan per orang — dua orang bernilai sama
harus mendapat komposisi yang sama.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
