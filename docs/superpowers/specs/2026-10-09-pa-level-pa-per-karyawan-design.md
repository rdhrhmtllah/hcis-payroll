# Level PA per Karyawan — Desain bertahap

Tanggal: 2026-10-09 · Modul: KPI / Performance Appraisal · Disetujui user di chat (bertahap).

## Masalah
Level HRIS sebagian karyawan tidak sesuai pekerjaannya (mis. SENIOR STAFF yang bekerja sebagai
OFFICER dan punya bawahan). Karena seluruh aturan PA berbasis level, orang seperti ini dikecualikan
(level 3) atau dinilai dengan aturan level yang salah.

## Keputusan
- Koreksi **per karyawan** khusus PA (HRIS tidak diubah), wajib alasan, punya masa berlaku.
- Level PA memakai **ID_Level HRIS yang sudah ada** sebagai patokan → seluruh master PA (bobot,
  komposisi penilai, Matriks Kompetensi, Matriks Soal, grup kurva, pengecualian) tidak berubah.
- Dipakai di **satu titik**: pembentukan peserta siklus; level asli HRIS ikut disalin untuk jejak.
- Koreksi yang level HRIS-nya sudah berubah sejak dibuat ditandai **perlu ditinjau**, tidak dipakai diam-diam.
- Konsistensi hanya di dalam PA; modul lain tetap level HRIS.

## Hasil scan (Tahap 1, 9 Okt)
**Titik penentu level peserta (wajib diubah):**
- `PaCyclePreparationService::karyawanAktif()` → dipakai `nilaiKelayakan()` dan penyisipan peserta
  (`ID_Level_Saat_Cycle`, `Nama_Level_Saat_Cycle`, `ID_Level_Jabatan_Saat_Cycle`).

**Hitungan/pratinjau karyawan per level (diselaraskan supaya angka setup = isi peserta):**
- `PaCyclePreparationService::levelTanpaAturan()` (jumlah karyawan per level).
- `PaCycleSetupStepService::levelSiklus()`, `opsiFilterKaryawan()`, `cariKaryawan()` (pratinjau & pra-pengecualian).
- `PaJenisPenilaiMatriksService::matriks()` (jumlah karyawan per level di matriks jenis penilai).
- `PaCompetencyRequirementService::matriks()` (API matriks per jenis lama — tak dipakai layar, cukup diselaraskan).

**Informasi penilai, bukan peserta (cukup tampilan, prioritas rendah):**
- `PaAssignmentBuilderService` pencarian penilai manual; `Balancing/PaBalancingTimPenilaiService` (tim forum).

**Hilir (otomatis konsisten):** 22 berkas backend & 14 komponen frontend membaca salinan peserta
(`ID_Level_Saat_Cycle` / `Nama_Level_Saat_Cycle`).

## Tahapan
1. **Scan & rencana** — dokumen ini.
2. **Data**: DDL tabel `N_PA_Karyawan_Level_PA` (koreksi per karyawan: level PA, level HRIS saat
   dibuat, alasan, berlaku dari/sampai, aktif, audit) + kolom jejak di `N_PA_Participant`
   (level HRIS asli, sumber level, id koreksi). Satu layanan `PaLevelPaResolver`.
3. **Resolusi**: `karyawanAktif()` dan hitungan per level memakai resolver; tampilan peserta
   "OFFICER (HRIS: SENIOR STAFF)".
4. **Master "Koreksi Level PA"**: CRUD, status perlu ditinjau, impor/ekspor Excel.
5. **Deteksi calon**: karyawan berlevel dikecualikan/di bawah Officer tetapi punya bawahan di
   relasi organisasi, atau jabatan SPV/Officer berlevel Staff — ditinjau admin, tidak otomatis.

Siklus berjalan tidak terpengaruh; koreksi berlaku untuk siklus yang dibuat sesudahnya.

## Status (9 Okt)
- Tahap 1 PR #606 · Tahap 2 PR #607 (DDL-2026-177) · Tahap 3 PR #608 · Tahap 4 PR #609 (DML-2026-178 menu) ·
  Tahap 5: tab "Calon terdeteksi" di halaman Koreksi Level PA (`PaKoreksiLevelPaService::calon`).

## Penyempurnaan sesudah audit ujung ke ujung (9 Okt)
- PR #611: penyiapan sempat gagal (batas 2.100 parameter SQL Server karena 3 kolom baru) — ukuran sisip kini dihitung dari jumlah kolom.
- PR #612–#616: formulir memakai komponen Hcis, pilihan level = level yang dipakai karyawan aktif, templat impor berpetunjuk
  (kolom "Kode Karyawan", dropdown Level PA), tambah koreksi untuk banyak karyawan sekaligus (semua-atau-tidak).
- **Siapkan ulang menyegarkan Level PA peserta yang sudah ada** (siklus PREPARED). Peserta yang levelnya berubah sejak
  dibentuk (koreksi baru, diubah, dicabut, atau level HRIS bergeser) ditulis ulang level + statusnya. Ditahan — dilaporkan
  di layar Siapkan beserta sebabnya — bila sudah punya penugasan aktif atau penyesuaian aturan per orang, atau diikutkan
  manual oleh admin padahal level barunya belum beraturan. Status yang pernah diputuskan admin (`Updated_By` terisi)
  dipertahankan; hanya level dan salinan aturannya yang ikut.
- Simpan/ubah/cabut koreksi mengembalikan `dampak`: siklus belum terbit tempat orang itu sudah peserta, dengan tindakan
  (PREPARED → jalankan Siapkan lagi; dikunci → berlaku mulai siklus berikutnya). Ditampilkan di halaman koreksi.
- Label level di pencarian calon penilai dan pemilih tim Balancing ikut Level PA.
