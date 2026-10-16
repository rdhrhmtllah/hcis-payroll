# DML-2026-188-pa-master-production

| | |
|---|---|
| Change ID | `DML-2026-188-pa-master-production` |
| Jenis | DML |
| Target | **Production** `emi_db_web_hr`, hanya melalui SQL Tracker |
| Development | Tidak dijalankan (data sumbernya justru development) |
| Berkas | `database/dml/DML-2026-188-pa-master-production/data-change.sql` |
| Checksum (sha256) | `70fc31ca2b60a1ebb8c513547e10897ce1b6866c312ba50a8f0c169dce899ade` |
| Bergantung pada | `DDL-2026-187-pa-skema-lengkap-production` |
| Status | REVIEW_REQUIRED |

## Untuk apa

Mengisi data **master** Performance Appraisal production dalam keadaan bersih, sehingga sesudah
`DDL-2026-187` aplikasi PA langsung bisa dipakai menyiapkan siklus. Tidak ada data transaksi
(siklus, peserta, penugasan, hasil, balancing) maupun data per karyawan.

## Isinya

| Bagian | Tabel | Baris |
|---|---|---|
| Tipe relasi organisasi (ATASAN, BAWAHAN, PEER) | `N_HRIS_Organization_Relationship_Type` | 3 |
| Status & lookup | `N_PA_Assignment_Source` 2, `N_PA_Assignment_Status` 6, `N_PA_Cycle_Status` 7, `N_PA_Participant_Status` 3, `N_PA_Rater_Policy_Status` 3, `N_PA_Result_Status` 4, `N_PA_Result_Note_Type` 2, `N_PA_Score_Type` 3, `N_PA_Score_Usage` 3, `N_PA_Rater_Category` 4, `N_PA_Hav_Quadrant` 9, `N_PA_Score_Range` 12 | 61 |
| Kompetensi | `N_PA_Competency_Type` 4, `N_PA_Competency` 24, `N_PA_Competency_Proficiency` 83 | 111 |
| Level PA | `N_PA_Level`: seluruh `HRIS_Level` production saat dijalankan + OFFICER SPECIALIST (1000000, dasar OFFICER) | 17 per 14 Okt |
| Kebutuhan per level | `N_PA_Competency_Requirement` 35, `N_PA_Competency_Level_Requirement` 205 | 240 |
| Bobot & penilai | `N_PA_Pillar_Weight` 14, `N_PA_Rater_Policy` 14, `N_PA_Rater_Policy_Detail` 47 | 75 |
| Level dikecualikan | `N_PA_Level_Exclusion`: FREELANCE, NOT SET, BELUM, ADVISOR | 4 |
| Grup kurva | `N_PA_Curve_Group` STAFF (2, 3), OFFICER (4, 5, 1000000), MANAJERIAL (6–13); `N_PA_Curve_Group_Level` 13 | 16 |
| Ledger | `N_SYSTEM_Database_Change` | 1 |

ID master dibawa dari development (`IDENTITY_INSERT`); tabelnya masih kosong di production.
Kolom pelaku (`*_By`) diisi NULL, `Created_At` waktu eksekusi.

## Keputusan pemilik proyek (14 Okt 2026)

- Tipe relasi hanya ATASAN, BAWAHAN, PEER. Relasi per karyawan dibentuk di production lewat
  aplikasi dari HRIS dan approval KPI — tidak lewat DML.
- **Matriks Soal tidak dibawa**: paket ujian HC Learn production berbeda; dipetakan lewat layar
  *Master · Matriks Soal* setelah ujiannya tersedia.
- Level dikecualikan hanya yang bukan level jabatan sebenarnya (FREELANCE, NOT SET, BELUM) dan ADVISOR.
- Expected Behaviour contoh UAT dikosongkan; diisi sesudah dokumen resmi HC.
- Ubahan uji UAT esai dikembalikan dulu (CV01/CV02 nonaktif, CV04 aktif); jenis/kompetensi uji
  (CONTOH1, FAMILY, CTH01) tidak dibawa.

## Pengaman

1. Berhenti (`THROW 50188`) bila skema `DDL-2026-187` belum ada.
2. Berhenti bila master sudah berisi — tidak bisa terjalankan dua kali.
3. Berhenti bila ID/nama `HRIS_Level` production untuk level 1–15 dan 21 tidak sama dengan yang
   dipetakan (dicek 14 Okt: sama; production menambah 24 ADVISOR).
4. Satu batch, satu transaksi (`XACT_ABORT ON`): seluruhnya masuk atau tidak sama sekali.

## Verifikasi (agent, 14 Okt 2026)

- Uji di development dalam transaksi yang di-ROLLBACK di atas skema `DDL-2026-187` yang dibangun
  sementara: 17 level; setiap aturan penilai berjumlah 100; setiap bobot pilar berjumlah 100;
  setiap level yang tidak dikecualikan punya aturan penilai dan bobot pilar; tidak ada teks
  "Contoh UAT"; percobaan kedua ditolak pengaman; tidak ada sisa objek.
- Kompilasi tanpa eksekusi (`SET NOEXEC ON`) di development: OK.
- Production dibaca saja (akun READ ONLY): pengaman level lolos; `HRIS_Level` 17 baris, ID unik.

## DML lama yang DIGANTIKAN untuk production

Jangan jalankan di production (tetap catatan sejarah development):

- `DML-2026-077-seed-aturan-performance-appraisal`
- `DML-2026-078-seed-pita-nilai-performance-appraisal`
- `DML-2026-104-status-penugasan-menunggu-verifikasi`
- `DML-2026-113-status-penugasan-terkumpul`
- `DML-2026-176-pa-matriks-kompetensi-awal`
- `DML-2026-184-pa-master-level-isi-hris`

`DML-2026-167-pa-draf-deskripsi-perilaku-kompetensi` (draf deskripsi perilaku, opsional) tidak dipakai.

## Urutan di production

`DDL-2026-187` → **`DML-2026-188`** → `DML-2026-189` (menu, jadwal, endpoint callback).
