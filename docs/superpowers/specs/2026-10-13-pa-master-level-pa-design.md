# Master Level PA — level khusus PA (contoh: Officer Specialist)

Tanggal: 13 Oktober 2026 · Modul: KPI / PerformanceAppraisal / MasterData · Status: DISETUJUI 13 Okt 2026 (FK di dev diizinkan; Urutan Officer Specialist 4.50)

## 1. Masalah

Deck PIC (slide 4) memuat baris **Officer (Specialist)** dengan komposisi penilai sendiri
(Atasan 60 · Diri 15 · Rekan 25 · tanpa Bawahan). Pemilik proyek menegaskan (13 Okt 2026)
bahwa Officer Specialist juga butuh **kompetensi, soal, bobot pilar, dan kurva yang berbeda**
dari Officer — jadi ia harus berlaku seperti level biasa di semua matriks PA, tetapi
**tidak boleh menjadi level HRIS**.

Hari ini level PA terikat langsung ke `HRIS_Level`:

- 10 tabel PA ber-FK ke `HRIS_Level.ID_Level`: `N_PA_Pillar_Weight`, `N_PA_Rater_Policy`,
  `N_PA_Competency_Requirement`, `N_PA_Curve_Group_Level`, `N_PA_Cycle_Pillar_Weight_Snapshot`,
  `N_PA_Cycle_Rater_Policy_Snapshot`, `N_PA_Cycle_Competency_Requirement_Snapshot`,
  `N_PA_Cycle_Curve_Group_Level`, `N_PA_Cycle_Question_Package`, `N_PA_Participant.ID_Level_Saat_Cycle`.
- 22 berkas kode membaca nama/urutan level dari `HRIS_Level`.
- Koreksi Level PA (DDL-2026-177) hanya bisa memindahkan orang ke level HRIS yang ada.

Menambah baris ke `HRIS_Level` ditolak: level itu akan muncul di HRIS (data karyawan,
`Flag_Harian`, `Flag_Extra_Meal`/payroll).

## 2. Keputusan desain

### 2.1 Tabel baru `N_PA_Level` (master level milik PA)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `ID_Level` | bigint PK, **bukan identity** | level HRIS = ID yang sama; level PA ≥ 1.000.000 |
| `Nama_Level` | nvarchar(100) | |
| `Sumber` | varchar(10) | `HRIS` / `PA` (CHECK) |
| `ID_Level_HRIS_Dasar` | bigint NULL, FK `HRIS_Level` | level HRIS = dirinya; level PA = level asalnya (Officer Specialist → OFFICER) |
| `Urutan` | decimal(6,2) | menggantikan `Level_Hierarchy` di PA; Officer Specialist mis. 4.50 (di antara OFFICER 4 dan SR OFFICER 5) |
| `Flag_Aktif`, `Created_*`, `Updated_*` | baku proyek | |

CHECK: `(Sumber='HRIS' AND ID_Level < 1000000) OR (Sumber='PA' AND ID_Level >= 1000000)` —
rentang terpisah supaya pertumbuhan identity `HRIS_Level` tidak pernah bertabrakan.

**Isi awal (DML):** salinan 16 level HRIS dengan ID yang sama. Level HRIS baru di masa
depan disalin otomatis oleh aplikasi (`sinkronHris()` saat master dibuka dan saat
penyiapan siklus) — tanpa trigger di tabel HRIS.

### 2.2 Memindahkan 10 FK

Tiap FK `→ HRIS_Level` diganti `→ N_PA_Level`. Datanya **tidak berubah** (ID sama), jadi
siklus dan master yang sudah ada tetap sah. `N_PA_Karyawan_Level_PA.ID_Level_PA` (DDL-2026-177,
belum TRACKER_RECORDED) ikut diberi FK ke `N_PA_Level`.

> **Perlu izin:** memindahkan FK berarti `ALTER TABLE … DROP CONSTRAINT` lalu `ADD CONSTRAINT`.
> AGENTS.md melarang agent mengeksekusi DROP. Pilihannya: (a) kamu mengizinkan eksplisit
> untuk eksekusi di **development** saja, atau (b) kamu yang mengeksekusi forward DDL di dev.
> Produksi tetap lewat SQL Tracker oleh kamu.

### 2.3 Kode

- Satu layanan baru `PaLevelMasterService` (daftar level, tambah/ubah level PA, `sinkronHris()`,
  salin matriks dari level dasar).
- Semua pembacaan **level PA** (nama, urutan, daftar level di matriks) pindah dari `HRIS_Level`
  ke `N_PA_Level`. Pembacaan **level HRIS karyawan** (`Karyawan.ID_Level`) tetap ke HRIS.
- `PaLevelPaResolver`/Koreksi Level PA: `ID_Level_PA` boleh level PA; nama level dibaca dari
  `N_PA_Level`.
- Logika yang memakai `Level_Hierarchy` (7 tempat, mis. batas "Leaders") memakai `Urutan`.

### 2.4 Layar

Di halaman **Koreksi Level PA** (`/performance-appraisal/master/level-pa`) ditambah tab
**Daftar Level PA**:

- level HRIS tampil hanya-baca; level PA bisa ditambah/diubah/dinonaktifkan;
- **Tambah level PA**: nama, level dasar, urutan, dan centang **"Salin isi matriks dari level
  dasar"** → bobot pilar, komposisi penilai, kebutuhan kompetensi, ikatan soal, dan keanggotaan
  grup kurva level dasar disalin sebagai titik awal, lalu admin menyesuaikan di tiap matriks.

Semua matriks yang sudah ada (bobot pilar, komposisi penilai, matriks kompetensi, matriks
soal, grup kurva, pengecualian level) otomatis memuat Officer Specialist karena daftar
levelnya dibaca dari `N_PA_Level`.

HR menetapkan siapa Officer Specialist lewat Koreksi Level PA (pilih level "OFFICER SPECIALIST").

## 3. Yang tidak berubah

- HRIS: tidak ada baris atau kolom baru.
- Siklus yang sudah berjalan: snapshot tetap; level baru hanya berlaku untuk siklus yang
  disiapkan sesudahnya (atau lewat Revisi → Terapkan Level PA terbaru).

## 4. Rencana perubahan basis data

- `DDL-2026-183-pa-master-level` — tabel `N_PA_Level`.
- `DDL-2026-185-pa-fk-level-ke-master-pa` — pemindahan 10 FK + FK `ID_Level_PA` (sesudah DML).
- `DML-2026-184-pa-master-level-isi-hris` — salinan 16 level HRIS (di antara kedua DDL: DDL dan DML tidak dicampur).
- Officer Specialist sendiri **dibuat lewat layar** (data, bukan DDL), di dev lalu di prod.

## 5. Uji

- Uji backend: `sinkronHris` idempoten; level PA ID ≥ 1.000.000; FK menolak level tak dikenal;
  salin matriks dari level dasar; peserta dengan koreksi ke level PA terbentuk dengan
  aturan/paket/kurva level PA; matriks memuat level PA.
- Regresi: suite PA (Penyiapan, Revisi, Penjadwalan, Hasil, KontrakRespons, Kurva).
- Uji nyata dev2: buat Officer Specialist, salin dari OFFICER, ubah komposisi 60/15/25,
  koreksi 2 karyawan, siapkan siklus uji, periksa peserta dan penugasannya.

## 6. Perkiraan

3–4 hari kerja (DDL+DML 0,5; layanan & 22 berkas 1,5; layar 1; uji & uji nyata 1).

## 7. Pertanyaan terbuka

1. Izin eksekusi DROP/ADD CONSTRAINT FK di development (lihat 2.2).
2. `Urutan` Officer Specialist: 4.50 (di antara OFFICER dan SENIOR OFFICER) — benar?
