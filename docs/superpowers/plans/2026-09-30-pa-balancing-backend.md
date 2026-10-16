# PA Balancing — Rencana Implementasi Bagian 1: Backend

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun data, mesin hitung, sesi, simulasi, hitung ulang, dan API Balancing
berulang sesudah Calculate, sehingga seluruh alur bisa dijalankan lewat API dan teruji.

**Architecture:** Lapisan penyesuaian di atas hasil hitung (Pendekatan 1). Enam tabel
`N_PA_Balancing_*` menyimpan sesi dan isiannya. Satu pembaca (`PaBalancingNilaiBerlaku`)
menyerahkan skor kompetensi yang berlaku ke `PaResultCalculationService::tahapKompetensi`,
yang menerapkan rumus selisih berbobot lewat kelas murni `PaBalancingKalkulator`. Simulasi
dan hitung ulang nyata memanggil jalur yang sama; simulasi hanya tidak menulis.

**Tech Stack:** Laravel 11 (PHP 8.3), SQL Server `Web_HRIS`, PHPUnit dengan
`DatabaseTransactions` terhadap basis data pengembangan, Mockery.

**Spec:** `docs/superpowers/specs/2026-09-30-pa-balancing-design.md`

**Bagian 2 (frontend: layar daftar sesi, board, panel simulasi, tab riwayat, menu)** ditulis
sebagai rencana terpisah sesudah API di rencana ini selesai, karena layar bergantung pada
bentuk respons yang dihasilkan di sini.

## Global Constraints

- Branch kerja di worktree dev2 `hcis-w-6`, dibuat dari `origin/staging`; PR ke `staging` (fitur tertahan PA). Merge hanya bila pemilik proyek memintanya.
- Jangan pernah `git add -A` / `git add .`; tambahkan berkas per path.
- Tanpa Laravel migration. Skema lewat `database/ddl/<change-id>/forward.sql` + `drop.sql`, entri `database/ddl/status.json`, dokumentasi `docs/changes/<change-id>/README.md` dan `docs/database/tables/kpi/`.
- DDL hanya dieksekusi ke `Web_HRIS @ instance-20240809-031237` (dev) **sesudah** pemilik proyek menyetujui; tanpa DROP/TRUNCATE.
- `unset DATABASE_URL` sebelum setiap perintah `php artisan` / `php vendor/bin/phpunit`.
- Jangan mencetak kata sandi, token, atau connection string.
- Nama tabel `N_PA_Balancing_*`; constraint tanpa `N_` (`PK_PA_Balancing_Sesi`, dst.). Flag `char(1)` `Y`/`T`. Tidak ada CHECK untuk daftar status yang bisa tumbuh; validasi di backend.
- Aktor audit kolom `*_By` = `KPI_Users.Id_Users` (`int`), sama dengan seluruh tabel PA yang ada.
- Skala Before/After **0–100**; After di luar rentang ditolak; alasan wajib bila |After − Before| ≥ 0,005.
- Rumus (B9, §6.1, §6.3 spec): `Nilai Kompetensi = mesin + Σ bobotₖ × (berlakuₖ − mesinₖ)`, bobot = porsi tepat kompetensi di Nilai Kompetensi.
- Final = `round(KPI × bobot_kpi/100 + Kompetensi × bobot_kompetensi/100, 2)` lewat `PaBalancingKalkulator::nilaiAkhir` (satu fungsi untuk hitung, simulasi, dan hitung ulang).
- Pesan galat ke pengguna dalam Bahasa Indonesia, menyebut nama sesi/peserta/kompetensi yang bermasalah.
- Modul **KPI** → submodul **Performance Appraisal** → fitur **Balancing**; berkas baru di `app/Services/KPI/PerformanceAppraisal/Balancing/`, `app/Http/Controllers/KPI/PerformanceAppraisal/Balancing/`, `routes/KPI/PerformanceAppraisal/Balancing/`, `tests/Feature/KPI/PerformanceAppraisal/Balancing/`.

## Review Focus

1. **Peserta yang sama ikut dua sesi berurutan.** Sesi kedua dibuat sesudah hitung ulang pertama; Before-nya = After sesi pertama, dan hitung ulang kedua tidak menjumlah selisih dua kali. Diuji di Task 4 (`test_dua_sesi_berurutan_tidak_menjumlah_dua_kali`).
2. **Kiriman HC Learn susulan saat siklus CLOSED memicu `hitung()` untuk peserta yang sedang dibahas.** Harus ditolak dengan menyebut nama sesi, bukan diam-diam membuat Before basi. Diuji di Task 4 (`test_hitung_ditolak_selama_peserta_ada_di_sesi_terbuka`).
3. **Siklus KURVA yang sebagian hasilnya sudah terbit atau dirilis ke leader.** Hitung ulang mengunci ulang kurva seluruh grup, jadi harus ditolak sebelum menyentuh apa pun. Diuji di Task 9 (`test_hitung_ulang_ditolak_bila_ada_yang_sudah_dirilis_ke_leader`).
4. **Satu peserta menjadi TERTAHAN di tengah hitung ulang** (mis. KPI berubah). Seluruh transaksi harus batal; kurva tidak boleh tertinggal dalam keadaan terbuka. Diuji di Task 9 (`test_satu_peserta_tertahan_membatalkan_seluruh_hitung_ulang`).
5. **Dua admin menyimpan board yang sama.** Yang kedua mendapat `PA_BAL_VERSI_BERUBAH` (409), bukan menimpa. Diuji di Task 6 (`test_versi_basi_ditolak`).

---

## Peta Berkas

| Berkas | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-165-pa-balancing/{forward,drop}.sql` | Enam tabel Balancing |
| `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingStatus.php` | Konstanta status sesi, status tinjau, sebab |
| `…/Balancing/PaBalancingException.php` | Galat ber-`error_code` |
| `…/Balancing/PaBalancingKalkulator.php` | Matematika murni: bobot, selisih berbobot, nilai akhir, sebab |
| `…/Balancing/PaBalancingNilaiBerlaku.php` | **Satu pintu** baca After yang berlaku (kompetensi dan kualitatif) |
| `…/Balancing/PaBalancingPenjaga.php` | Pertanyaan status: sesi terbuka, FINAL belum dihitung, `pastikanTuntas` |
| `…/Balancing/PaBalancingPotretHasil.php` | Potret Final/grade/HAV seluruh siklus (sebelum/sesudah) |
| `…/Balancing/PaBalancingKandidatService.php` | Daftar kandidat bersaring + alasan tidak layak |
| `…/Balancing/PaBalancingSesiService.php` | Buat, simpan draft, finalkan, batalkan, daftar, detail |
| `…/Balancing/PaBalancingSimulasiService.php` | Simulasi tanpa menulis |
| `…/Balancing/PaBalancingHitungUlangService.php` | Hitung ulang nyata + jejak |
| `app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php` | Diubah: porsi per penilai, bobot, lapisan balancing, penjaga, `rincianKompetensi`, `simulasiHasil` |
| `app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php` | Diubah: `simulasikanGrade` |
| `app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php` | Diubah: `pastikanTuntas` di lima pintu publish |
| `app/Services/KPI/PerformanceAppraisal/PaCycleGate.php` | Diubah: operasi `BALANCING` (CLOSED) |
| `app/Http/Controllers/KPI/PerformanceAppraisal/Balancing/PaBalancingController.php` | API |
| `app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/PaCycleAdminController.php` | Diubah: `kalibrasiHasil` ditutup |
| `routes/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php` | Rute API |
| `tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanBalancing.php` | Fixture uji |

---

### Task 1: DDL enam tabel Balancing

**Files:**
- Create: `database/ddl/DDL-2026-165-pa-balancing/forward.sql`
- Create: `database/ddl/DDL-2026-165-pa-balancing/drop.sql`
- Create: `docs/changes/DDL-2026-165-pa-balancing/README.md`
- Create: `docs/database/tables/kpi/n-pa-balancing-sesi.md`, `n-pa-balancing-forum.md`, `n-pa-balancing-peserta.md`, `n-pa-balancing-kompetensi.md`, `n-pa-balancing-kualitatif.md`, `n-pa-balancing-jejak-hitung.md`
- Modify: `database/ddl/status.json` (tambah satu entri di akhir `changes`)

**Interfaces:**
- Produces: tabel dan kolom persis seperti di `forward.sql` di bawah; semua task berikutnya memakai nama ini.

- [ ] **Step 1: Pastikan nomor 158 masih bebas di semua branch**

Run:
```bash
git fetch -q --all && for r in $(git branch -a --format='%(refname:short)' | grep -v HEAD); do git ls-tree --name-only $r database/ddl/ database/dml/ 2>/dev/null; done | grep -o "D[DM]L-2026-[0-9]*" | sort -u | tail -3
```
Expected: nomor tertinggi < 158. Bila 158 sudah terpakai, pakai nomor bebas berikutnya dan ganti `158` di seluruh task ini.

Periksa juga ledger: `python3 -c "import json;[print(c['change_id'],c['status']) for c in json.load(open('database/ddl/status.json'))['changes'] if c['status'] not in ('COMPLETED','PRODUCTION_EXECUTED')]" | tail -20` dan laporkan item tertunda ke pemilik proyek (AGENTS.md).

- [ ] **Step 2: Tulis `forward.sql`**

```sql
/* Change ID: DDL-2026-165-pa-balancing
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-080/081 (N_PA_Result, N_PA_Result_Competency), N_PA_Cycle,
                 N_PA_Participant, N_PA_Competency.
   Scope: sesi Balancing berulang sesudah Calculate.
   Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
*/

IF OBJECT_ID('N_PA_Balancing_Sesi', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Sesi (
        Id_PA_Balancing_Sesi int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Sesi PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Sesi_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        Nama_Sesi nvarchar(200) NOT NULL,
        PIC_User_Id int NOT NULL,
        Status_Sesi varchar(20) NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Status DEFAULT ('TERBUKA'),
        Catatan_Rapat nvarchar(max) NULL,
        Versi_Data int NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Versi DEFAULT (1),
        Final_At datetime2(0) NULL,
        Final_By int NULL,
        Nomor_Hitung_Ulang int NULL,
        Hitung_Ulang_At datetime2(0) NULL,
        Hitung_Ulang_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Alasan_Batal nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT CK_PA_Balancing_Sesi_Versi CHECK (Versi_Data >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Sesi') AND name = 'IX_PA_Balancing_Sesi_Cycle_Status')
    CREATE INDEX IX_PA_Balancing_Sesi_Cycle_Status ON N_PA_Balancing_Sesi (PA_Cycle_Id, Status_Sesi);
GO

IF OBJECT_ID('N_PA_Balancing_Forum', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Forum (
        Id_PA_Balancing_Forum int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Forum PRIMARY KEY,
        PA_Balancing_Sesi_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Forum_Sesi REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi),
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        Nama_Karyawan varchar(255) NOT NULL,
        Peran nvarchar(100) NOT NULL,
        Urutan_Tampil smallint NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Forum_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT CK_PA_Balancing_Forum_Urutan CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Forum') AND name = 'IX_PA_Balancing_Forum_Sesi')
    CREATE INDEX IX_PA_Balancing_Forum_Sesi ON N_PA_Balancing_Forum (PA_Balancing_Sesi_Id);
GO

IF OBJECT_ID('N_PA_Balancing_Peserta', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Peserta (
        Id_PA_Balancing_Peserta int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Peserta PRIMARY KEY,
        PA_Balancing_Sesi_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Peserta_Sesi REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi),
        PA_Participant_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Peserta_Participant REFERENCES N_PA_Participant (Id_PA_Participant),
        PA_Result_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Peserta_Result REFERENCES N_PA_Result (Id_PA_Result),
        Calculation_Version_Sebelum int NOT NULL,
        Nilai_KPI_Sebelum decimal(6,2) NULL,
        Nilai_Kompetensi_Sebelum decimal(6,2) NULL,
        Final_Score_Sebelum decimal(6,2) NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        Status_Tinjau varchar(20) NOT NULL
            CONSTRAINT DF_PA_Balancing_Peserta_Tinjau DEFAULT ('BELUM'),
        Alasan nvarchar(1000) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Peserta_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Peserta') AND name = 'UX_PA_Balancing_Peserta_Sesi_Participant')
    CREATE UNIQUE INDEX UX_PA_Balancing_Peserta_Sesi_Participant ON N_PA_Balancing_Peserta (PA_Balancing_Sesi_Id, PA_Participant_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Peserta') AND name = 'IX_PA_Balancing_Peserta_Participant')
    CREATE INDEX IX_PA_Balancing_Peserta_Participant ON N_PA_Balancing_Peserta (PA_Participant_Id) INCLUDE (PA_Balancing_Sesi_Id, Status_Tinjau);
GO

IF OBJECT_ID('N_PA_Balancing_Kompetensi', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Kompetensi (
        Id_PA_Balancing_Kompetensi int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Kompetensi PRIMARY KEY,
        PA_Balancing_Peserta_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kompetensi_Peserta REFERENCES N_PA_Balancing_Peserta (Id_PA_Balancing_Peserta),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kompetensi_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        Bobot decimal(9,6) NOT NULL,
        Nilai_Sebelum decimal(6,2) NOT NULL,
        Nilai_Sesudah decimal(6,2) NOT NULL,
        Alasan nvarchar(1000) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT CK_PA_Balancing_Kompetensi_Bobot CHECK (Bobot >= 0 AND Bobot <= 1),
        CONSTRAINT CK_PA_Balancing_Kompetensi_Nilai CHECK (Nilai_Sebelum >= 0 AND Nilai_Sebelum <= 100
                                                          AND Nilai_Sesudah >= 0 AND Nilai_Sesudah <= 100)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Kompetensi') AND name = 'UX_PA_Balancing_Kompetensi_Peserta_Competency')
    CREATE UNIQUE INDEX UX_PA_Balancing_Kompetensi_Peserta_Competency ON N_PA_Balancing_Kompetensi (PA_Balancing_Peserta_Id, PA_Competency_Id);
GO

IF OBJECT_ID('N_PA_Balancing_Kualitatif', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Kualitatif (
        Id_PA_Balancing_Kualitatif int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Kualitatif PRIMARY KEY,
        PA_Balancing_Peserta_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kualitatif_Peserta REFERENCES N_PA_Balancing_Peserta (Id_PA_Balancing_Peserta),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kualitatif_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        Isi_Sebelum nvarchar(max) NULL,
        Isi_Sesudah nvarchar(max) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Kualitatif') AND name = 'UX_PA_Balancing_Kualitatif_Peserta_Competency')
    CREATE UNIQUE INDEX UX_PA_Balancing_Kualitatif_Peserta_Competency ON N_PA_Balancing_Kualitatif (PA_Balancing_Peserta_Id, PA_Competency_Id);
GO

IF OBJECT_ID('N_PA_Balancing_Jejak_Hitung', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Jejak_Hitung (
        Id_PA_Balancing_Jejak_Hitung int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Jejak_Hitung PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        Nomor_Hitung_Ulang int NOT NULL,
        PA_Participant_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Participant REFERENCES N_PA_Participant (Id_PA_Participant),
        PA_Balancing_Sesi_Id int NULL
            CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Sesi REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi),
        Nilai_Kompetensi_Sebelum decimal(6,2) NULL,
        Nilai_Kompetensi_Sesudah decimal(6,2) NULL,
        Final_Score_Sebelum decimal(6,2) NULL,
        Final_Score_Sesudah decimal(6,2) NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        Kode_Grade_Sesudah varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        PA_Hav_Quadrant_Sesudah_Id int NULL,
        Sebab varchar(20) NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Jejak_Hitung_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT CK_PA_Balancing_Jejak_Hitung_Nomor CHECK (Nomor_Hitung_Ulang >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Jejak_Hitung') AND name = 'IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor')
    CREATE INDEX IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor ON N_PA_Balancing_Jejak_Hitung (PA_Cycle_Id, Nomor_Hitung_Ulang);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Jejak_Hitung') AND name = 'IX_PA_Balancing_Jejak_Hitung_Participant')
    CREATE INDEX IX_PA_Balancing_Jejak_Hitung_Participant ON N_PA_Balancing_Jejak_Hitung (PA_Participant_Id);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-165')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-165',
        'DDL',
        'Balancing berulang pada Performance Appraisal',
        'Enam tabel N_PA_Balancing_*: sesi, forum, peserta (snapshot Before), kompetensi (Before/After berbobot), kualitatif (Before/After teks kaya), dan jejak hitung ulang. Skor kompetensi yang berlaku = After sesi FINAL terbaru; N_PA_Result tidak berubah bentuk.',
        'database/ddl/DDL-2026-165-pa-balancing/forward.sql',
        'docs/changes/DDL-2026-165-pa-balancing/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Balancing. Rancangan disetujui pemilik proyek 30 September 2026.',
        NULL
    );
END;
GO
```

- [ ] **Step 3: Tulis `drop.sql`** (bantuan manual, TIDAK dijalankan agen)

```sql
/* DDL-2026-165-pa-balancing — bantuan reset MANUAL. Agen tidak menjalankan berkas ini. */
IF OBJECT_ID('N_PA_Balancing_Jejak_Hitung', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Jejak_Hitung;
IF OBJECT_ID('N_PA_Balancing_Kualitatif', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Kualitatif;
IF OBJECT_ID('N_PA_Balancing_Kompetensi', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Kompetensi;
IF OBJECT_ID('N_PA_Balancing_Peserta', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Peserta;
IF OBJECT_ID('N_PA_Balancing_Forum', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Forum;
IF OBJECT_ID('N_PA_Balancing_Sesi', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Sesi;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-165';
GO
```

- [ ] **Step 4: Tulis dokumentasi tabel**

Satu berkas per tabel di `docs/database/tables/kpi/`, mengikuti bentuk `n-pa-assignment-score-esai.md`: judul, tabel atribut (Modul/Submodul/Fitur `KPI / Performance Appraisal / Balancing`, Change ID `DDL-2026-165-pa-balancing`, Forward SQL path, Rancangan = path spec), tabel kolom (kolom | tipe | null | default | arti), constraint & indeks beserta alasannya, relasi, dan "Dipakai oleh" (isi dengan nama service dari Peta Berkas). Arti kolom yang wajib ditulis:

| Tabel | Kolom yang perlu penjelasan |
|---|---|
| `n-pa-balancing-sesi.md` | `Status_Sesi` = `TERBUKA`/`DRAFT`/`FINAL`/`DIBATALKAN` (divalidasi backend, tanpa CHECK); `Versi_Data` = kunci optimistis, naik tiap simpan; `PIC_User_Id` = `KPI_Users.Id_Users`; `Nomor_Hitung_Ulang` = nomor putaran hitung ulang yang memproses sesi ini |
| `n-pa-balancing-forum.md` | Anggota forum dicatat tanpa login; `Nama_Karyawan` salinan saat dicatat |
| `n-pa-balancing-peserta.md` | Semua kolom `*_Sebelum` = snapshot saat sesi dibuat; `Status_Tinjau` = `BELUM`/`TANPA_PERUBAHAN`/`DIUBAH` |
| `n-pa-balancing-kompetensi.md` | `Bobot` = porsi tepat kompetensi di Nilai Kompetensi (spec §6.1), dibekukan saat sesi dibuat; satu baris untuk SETIAP kompetensi berskor, termasuk yang tidak diubah |
| `n-pa-balancing-kualitatif.md` | Satu baris per kompetensi KUALITATIF aktif (QL01–QL04 per 30 Sep 2026); isi HTML aman lewat `PaTeksKaya::bersihkan` |
| `n-pa-balancing-jejak-hitung.md` | Hanya sisip; `Sebab` = `BALANCING`/`KURVA`/`KEDUANYA`/`TANPA_PERUBAHAN`; `PA_Balancing_Sesi_Id` kosong bila orangnya hanya tergeser kurva |

`docs/changes/DDL-2026-165-pa-balancing/README.md`: change ID, target `Web_HRIS`, deskripsi, objek terdampak (6 tabel + semua PK/FK/DF/CK/UX/IX di forward.sql + `N_SYSTEM_Database_Change`), path DDL/drop/dokumentasi, checksum forward (Step 5), status, pekerjaan agen, pekerjaan pengguna (tinjau, rekam SQL Tracker, eksekusi production, verifikasi).

- [ ] **Step 5: Hitung checksum dan tambahkan entri ledger**

Run: `sha256sum database/ddl/DDL-2026-165-pa-balancing/forward.sql`

Tambahkan di akhir `changes` pada `database/ddl/status.json` (pakai editor/python, jaga format dua spasi yang ada):

```json
{
 "change_id": "DDL-2026-165-pa-balancing",
 "type": "DDL",
 "title": "Balancing berulang pada Performance Appraisal",
 "affected_objects": ["N_PA_Balancing_Sesi", "N_PA_Balancing_Forum", "N_PA_Balancing_Peserta", "N_PA_Balancing_Kompetensi", "N_PA_Balancing_Kualitatif", "N_PA_Balancing_Jejak_Hitung", "N_SYSTEM_Database_Change"],
 "artifact_path": "database/ddl/DDL-2026-165-pa-balancing/forward.sql",
 "drop_path": "database/ddl/DDL-2026-165-pa-balancing/drop.sql",
 "artifact_revision": "r1",
 "documentation_path": "docs/changes/DDL-2026-165-pa-balancing/README.md",
 "forward_checksum_sha256": "<hasil sha256sum>",
 "development": "PENDING",
 "sql_tracker": "PENDING",
 "production": "PENDING",
 "verification": "PENDING",
 "status": "REVIEW_REQUIRED",
 "catatan": "KPI/Performance Appraisal/Balancing. Sesi Balancing berulang sesudah Calculate; rancangan docs/superpowers/specs/2026-09-30-pa-balancing-design.md."
}
```

- [ ] **Step 6: Minta persetujuan eksekusi dev**

Tampilkan ke pemilik proyek: forward.sql lengkap, target `Web_HRIS @ instance-20240809-031237`, dan bahwa semua objek baru (tidak ada ALTER atas tabel lama). **Tunggu persetujuan eksplisit.** Sesudah disetujui, set ledger `status` → `APPROVED`.

- [ ] **Step 7: Eksekusi ke dev lalu verifikasi**

Verifikasi target lebih dulu (tanpa mencetak kredensial): `SELECT DB_NAME(), @@SERVERNAME` harus `Web_HRIS` dan `instance-20240809-031237`. Periksa keenam tabel belum ada (`SELECT OBJECT_ID('N_PA_Balancing_Sesi')` → NULL). Jalankan forward.sql per batch `GO` lewat `sqlcmd` atau skrip PHP PDO yang sudah dipakai untuk DDL-2026-140. Verifikasi:

```sql
SELECT name FROM sys.tables WHERE name LIKE 'N_PA_Balancing_%' ORDER BY name;   -- 6 baris
SELECT COUNT(*) FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-165'; -- 1
```
Jalankan ulang forward.sql sekali: harus no-op. Ledger: `development` `DONE`, `verification` `DEV_DONE`, `status` `TRACKER_PENDING`, isi `execution` seperti entri DDL-2026-140.

- [ ] **Step 8: Commit**

```bash
git add database/ddl/DDL-2026-165-pa-balancing/forward.sql database/ddl/DDL-2026-165-pa-balancing/drop.sql \
  database/ddl/status.json docs/changes/DDL-2026-165-pa-balancing/README.md \
  docs/database/tables/kpi/n-pa-balancing-sesi.md docs/database/tables/kpi/n-pa-balancing-forum.md \
  docs/database/tables/kpi/n-pa-balancing-peserta.md docs/database/tables/kpi/n-pa-balancing-kompetensi.md \
  docs/database/tables/kpi/n-pa-balancing-kualitatif.md docs/database/tables/kpi/n-pa-balancing-jejak-hitung.md
git commit -m "feat(kpi-pa): DDL-2026-165 tabel Balancing berulang"
```

---

### Task 2: Status, galat, gerbang, dan kalkulator murni

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingStatus.php`
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingException.php`
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingKalkulator.php`
- Modify: `app/Services/KPI/PerformanceAppraisal/PaCycleGate.php` (konstanta + baris `IZIN`)
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKalkulatorTest.php`

**Interfaces:**
- Produces:
  - `PaBalancingStatus::{TERBUKA, DRAFT, FINAL, DIBATALKAN, BELUM, TANPA_PERUBAHAN, DIUBAH, SEBAB_BALANCING, SEBAB_KURVA, SEBAB_KEDUANYA, SEBAB_TANPA_PERUBAHAN, SESI_BISA_DIUBAH}`
  - `PaBalancingException(string $message, string $errorCode, int $httpStatus = 422)` dengan konstanta kode di bawah.
  - `PaBalancingKalkulator::bobotKompetensi(array $kategori): array<int,float>`
  - `PaBalancingKalkulator::porsiPenilai(Collection $butir): array<int,float>`
  - `PaBalancingKalkulator::nilaiKompetensi(float $mesin, array $bobot, array $mesinPerKompetensi, array $berlaku): float`
  - `PaBalancingKalkulator::nilaiAkhir(float $kpi, float $kompetensi, float $bobotKpi, float $bobotKompetensi): float`
  - `PaBalancingKalkulator::berubah(?float $a, ?float $b): bool`
  - `PaBalancingKalkulator::sebab(?float $finalLama, ?float $finalBaru, ?string $gradeLama, ?string $gradeTengah, ?string $gradeBaru): ?string`
  - `PaCycleGate::BALANCING` (hanya `CLOSED`)

- [ ] **Step 1: Tulis uji yang gagal**

`tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKalkulatorTest.php`:

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator as K;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingStatus as S;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use Tests\TestCase;

/**
 * Matematika Balancing, dipaku angka per angka. Setiap harapan dihitung tangan di komentar.
 */
class BalancingKalkulatorTest extends TestCase
{
    private function butir(array $b): \Illuminate\Support\Collection
    {
        return collect(array_map(fn ($x) => (object) [
            'Skor' => $x[0], 'Skor_Maksimal' => 100, 'PA_Competency_Id' => $x[1], 'Bobot_Sumber' => $x[2] ?? null,
        ], $b));
    }

    public function test_porsi_tanpa_bobot_butir_menurut_jumlah_butir(): void
    {
        // 3 butir: dua kompetensi 1, satu kompetensi 2 → 2/3 dan 1/3.
        $p = K::porsiPenilai($this->butir([[70, 1], [80, 1], [90, 2]]));
        $this->assertEqualsWithDelta(2 / 3, $p[1], 1e-9);
        $this->assertEqualsWithDelta(1 / 3, $p[2], 1e-9);
    }

    public function test_porsi_dengan_bobot_butir_dan_butir_tanpa_kompetensi(): void
    {
        // Bobot 2, 1, 1; butir ketiga tanpa kompetensi tetap masuk penyebut → 2/4 dan 1/4.
        $p = K::porsiPenilai($this->butir([[70, 1, 2], [80, 2, 1], [90, null, 1]]));
        $this->assertEqualsWithDelta(0.5, $p[1], 1e-9);
        $this->assertEqualsWithDelta(0.25, $p[2], 1e-9);
        $this->assertArrayNotHasKey(0, $p);
    }

    public function test_bobot_kompetensi_lintas_kategori_dan_penilai(): void
    {
        // ATASAN 60%: satu penilai, porsi k1 0,5 k2 0,5.
        // REKAN 40%: dua penilai bobot 3 dan 1 (dinormalkan 0,75/0,25), porsi k1 1,0 / 0,5.
        // k1 = 0,6×0,5 + 0,4×(0,75×1 + 0,25×0,5) = 0,3 + 0,4×0,875 = 0,65
        // k2 = 0,6×0,5 + 0,4×(0,25×0,5)           = 0,3 + 0,05       = 0,35
        $b = K::bobotKompetensi([
            ['bobot_terpakai' => 60.0, 'penilai' => [['bobot' => 100.0, 'porsi' => [1 => 0.5, 2 => 0.5]]]],
            ['bobot_terpakai' => 40.0, 'penilai' => [
                ['bobot' => 3.0, 'porsi' => [1 => 1.0]],
                ['bobot' => 1.0, 'porsi' => [1 => 0.5, 2 => 0.5]],
            ]],
        ]);
        $this->assertEqualsWithDelta(0.65, $b[1], 1e-9);
        $this->assertEqualsWithDelta(0.35, $b[2], 1e-9);
    }

    public function test_bobot_penilai_nol_semua_dirata_rata_biasa(): void
    {
        // Sama dengan nilaiSatuKategori: total bobot ≤ 0 → rata-rata sederhana.
        $b = K::bobotKompetensi([
            ['bobot_terpakai' => 100.0, 'penilai' => [
                ['bobot' => 0.0, 'porsi' => [1 => 1.0]],
                ['bobot' => 0.0, 'porsi' => [2 => 1.0]],
            ]],
        ]);
        $this->assertEqualsWithDelta(0.5, $b[1], 1e-9);
        $this->assertEqualsWithDelta(0.5, $b[2], 1e-9);
    }

    public function test_nilai_kompetensi_selisih_berbobot_dari_mesin(): void
    {
        // 77,5 + 0,5×(80 − 70) + 0,5×(85 − 85) = 82,5
        $this->assertSame(82.5, K::nilaiKompetensi(77.5, [1 => 0.5, 2 => 0.5], [1 => 70.0, 2 => 85.0], [1 => 80.0, 2 => 85.0]));
    }

    public function test_tanpa_nilai_berlaku_sama_dengan_mesin(): void
    {
        $this->assertSame(77.46, K::nilaiKompetensi(77.4567, [1 => 0.5], [1 => 70.0], []));
    }

    public function test_kompetensi_tanpa_bobot_atau_tanpa_mesin_diabaikan(): void
    {
        // Kompetensi 3 tidak punya bobot; kompetensi 4 tidak ada di mesin. Tidak ada yang digeser.
        $this->assertSame(70.0, K::nilaiKompetensi(70.0, [1 => 1.0], [1 => 70.0, 3 => 60.0], [3 => 90.0, 4 => 90.0]));
    }

    public function test_nilai_kompetensi_dibatasi_0_sampai_100(): void
    {
        $this->assertSame(100.0, K::nilaiKompetensi(95.0, [1 => 1.0], [1 => 90.0], [1 => 100.0]) + 0.0);
        $this->assertSame(0.0, K::nilaiKompetensi(2.0, [1 => 1.0], [1 => 10.0], [1 => 0.0]));
    }

    public function test_nilai_akhir_sama_dengan_rumus_hitung(): void
    {
        // 90 × 50% + 82,5 × 50% = 86,25
        $this->assertSame(86.25, K::nilaiAkhir(90.0, 82.5, 50.0, 50.0));
    }

    public function test_sebab(): void
    {
        $this->assertNull(K::sebab(80.0, 80.0, 'B', 'B', 'B'));
        $this->assertSame(S::SEBAB_KURVA, K::sebab(80.0, 80.0, 'B', 'B_PLUS', 'B_PLUS'));
        $this->assertSame(S::SEBAB_BALANCING, K::sebab(80.0, 81.0, 'B', 'B', 'B'));
        $this->assertSame(S::SEBAB_BALANCING, K::sebab(80.0, 85.0, 'B', 'B', 'B_PLUS'));
        $this->assertSame(S::SEBAB_KURVA, K::sebab(80.0, 81.0, 'B', 'B_PLUS', 'B_PLUS'));
        $this->assertSame(S::SEBAB_KEDUANYA, K::sebab(80.0, 90.0, 'B', 'B_PLUS', 'A'));
    }

    public function test_gerbang_balancing_hanya_saat_closed(): void
    {
        $this->assertSame(['CLOSED'], app(PaCycleGate::class)->statusUntukOperasi(PaCycleGate::BALANCING));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKalkulatorTest.php`
Expected: FAIL, `Class "App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator" not found`.

- [ ] **Step 3: Tulis `PaBalancingStatus.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

/**
 * Kosakata Balancing. Divalidasi di backend, bukan CHECK di basis data (AGENTS.md: daftar
 * nilai bisnis yang bisa tumbuh tidak dikunci constraint).
 */
final class PaBalancingStatus
{
    public const TERBUKA = 'TERBUKA';

    public const DRAFT = 'DRAFT';

    public const FINAL = 'FINAL';

    public const DIBATALKAN = 'DIBATALKAN';

    /** Status sesi yang isiannya masih boleh diubah. */
    public const SESI_BISA_DIUBAH = [self::TERBUKA, self::DRAFT];

    public const BELUM = 'BELUM';

    public const TANPA_PERUBAHAN = 'TANPA_PERUBAHAN';

    public const DIUBAH = 'DIUBAH';

    public const SEBAB_BALANCING = 'BALANCING';

    public const SEBAB_KURVA = 'KURVA';

    public const SEBAB_KEDUANYA = 'KEDUANYA';

    public const SEBAB_TANPA_PERUBAHAN = 'TANPA_PERUBAHAN';
}
```

- [ ] **Step 4: Tulis `PaBalancingException.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use RuntimeException;

/**
 * Penolakan Balancing yang dikenali layar lewat `error_code`. Turunan RuntimeException,
 * jadi pintu lama yang menangkap RuntimeException (mis. penerbitan) tetap menjawab 422.
 */
class PaBalancingException extends RuntimeException
{
    public const SIKLUS_BELUM_DITUTUP = 'PA_BAL_SIKLUS_BELUM_DITUTUP';

    public const KURVA_BELUM_DIKUNCI = 'PA_BAL_KURVA_BELUM_DIKUNCI';

    public const PESERTA_TIDAK_LAYAK = 'PA_BAL_PESERTA_TIDAK_LAYAK';

    public const SESI_TIDAK_DITEMUKAN = 'PA_BAL_SESI_TIDAK_DITEMUKAN';

    public const SESI_TERKUNCI = 'PA_BAL_SESI_TERKUNCI';

    public const VERSI_BERUBAH = 'PA_BAL_VERSI_BERUBAH';

    public const NILAI_DI_LUAR_RENTANG = 'PA_BAL_NILAI_DI_LUAR_RENTANG';

    public const ALASAN_WAJIB = 'PA_BAL_ALASAN_WAJIB';

    public const PESERTA_BELUM_DITINJAU = 'PA_BAL_PESERTA_BELUM_DITINJAU';

    public const SESI_MASIH_TERBUKA = 'PA_BAL_SESI_MASIH_TERBUKA';

    public const BELUM_DIHITUNG_ULANG = 'PA_BAL_BELUM_DIHITUNG_ULANG';

    public const TIDAK_ADA_SESI_FINAL = 'PA_BAL_TIDAK_ADA_SESI_FINAL';

    public const SUDAH_ADA_YANG_TERBIT = 'PA_BAL_SUDAH_ADA_YANG_TERBIT';

    public const HITUNG_ULANG_TERTAHAN = 'PA_BAL_HITUNG_ULANG_TERTAHAN';

    public const DATA_TIDAK_SAH = 'PA_BAL_DATA_TIDAK_SAH';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 5: Tulis `PaBalancingKalkulator.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Collection;

/**
 * Matematika Balancing — murni, tanpa basis data (spec §6).
 *
 * SATU FUNGSI UNTUK TIGA PEMAKAI. Hitung biasa, simulasi, dan hitung ulang Balancing semuanya
 * memanggil kelas ini. Itulah yang menjamin angka simulasi di rapat sama dengan angka yang
 * tersimpan sesudah hitung ulang.
 */
final class PaBalancingKalkulator
{
    /**
     * Porsi tiap kompetensi di nilai SATU penilai, dengan aturan yang sama persis dengan
     * `PaResultCalculationService::nilaiSatuPenilai`: bobot butir (`Bobot_Sumber`) dipakai
     * hanya bila SEMUA butir berbobot dan totalnya > 0; selain itu setiap butir bernilai 1.
     * Butir tanpa kompetensi tetap masuk penyebut, sehingga Σ porsi bisa < 1.
     *
     * @param  Collection<int, object>  $butir  baris berskor: PA_Competency_Id, Bobot_Sumber
     * @return array<int, float>
     */
    public static function porsiPenilai(Collection $butir): array
    {
        if ($butir->isEmpty()) {
            return [];
        }

        $semuaBerbobot = $butir->every(fn ($b) => $b->Bobot_Sumber !== null);
        $total = $semuaBerbobot ? (float) $butir->sum(fn ($b) => (float) $b->Bobot_Sumber) : 0.0;
        $pakaiBobot = $semuaBerbobot && $total > 0;
        $penyebut = $pakaiBobot ? $total : (float) $butir->count();

        $porsi = [];

        foreach ($butir as $b) {
            if ($b->PA_Competency_Id === null) {
                continue;
            }

            $c = (int) $b->PA_Competency_Id;
            $porsi[$c] = ($porsi[$c] ?? 0.0) + ($pakaiBobot ? (float) $b->Bobot_Sumber : 1.0) / $penyebut;
        }

        return $porsi;
    }

    /**
     * Bobot kompetensi = turunan Nilai Kompetensi terhadap skor kompetensi itu (spec §6.1).
     * Penilai dinormalkan seperti `nilaiSatuKategori`: total bobot ≤ 0 → rata-rata sederhana.
     *
     * @param  list<array{bobot_terpakai:float, penilai:list<array{bobot:float, porsi:array<int,float>}>}>  $kategori
     * @return array<int, float>
     */
    public static function bobotKompetensi(array $kategori): array
    {
        $hasil = [];

        foreach ($kategori as $k) {
            $penilai = $k['penilai'];

            if ($penilai === []) {
                continue;
            }

            $total = array_sum(array_map(fn ($p) => (float) $p['bobot'], $penilai));

            foreach ($penilai as $p) {
                $w = $total > 0 ? (float) $p['bobot'] / $total : 1 / count($penilai);

                foreach ($p['porsi'] as $c => $porsi) {
                    $hasil[(int) $c] = ($hasil[(int) $c] ?? 0.0) + ((float) $k['bobot_terpakai'] / 100) * $w * $porsi;
                }
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * mesin + Σ bobotₖ × (berlakuₖ − mesinₖ), dibatasi 0–100, dibulatkan 2 desimal.
     * Selisih SELALU diukur dari nilai mesin, jadi sesi berurutan tidak menjumlah dua kali.
     *
     * @param  array<int, float>  $bobot
     * @param  array<int, float>  $mesinPerKompetensi
     * @param  array<int, float>  $berlaku
     */
    public static function nilaiKompetensi(float $mesin, array $bobot, array $mesinPerKompetensi, array $berlaku): float
    {
        $geser = 0.0;

        foreach ($berlaku as $c => $nilai) {
            if (! isset($bobot[$c], $mesinPerKompetensi[$c])) {
                continue;
            }

            $geser += $bobot[$c] * ((float) $nilai - (float) $mesinPerKompetensi[$c]);
        }

        return round(max(0.0, min(100.0, $mesin + $geser)), 2);
    }

    public static function nilaiAkhir(float $kpi, float $kompetensi, float $bobotKpi, float $bobotKompetensi): float
    {
        return round($kpi * $bobotKpi / 100 + $kompetensi * $bobotKompetensi / 100, 2);
    }

    public static function berubah(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a !== $b;
        }

        return abs($a - $b) >= 0.005;
    }

    /**
     * Sebab perubahan grade/final seseorang.
     *
     * `$gradeTengah` = grade Final LAMA menurut kurva BARU. Bila itu sudah sama dengan grade baru,
     * kurvalah yang menggeser; bila sama dengan grade lama, angka orangnya yang menggeser.
     */
    public static function sebab(?float $finalLama, ?float $finalBaru, ?string $gradeLama, ?string $gradeTengah, ?string $gradeBaru): ?string
    {
        $finalBerubah = self::berubah($finalLama, $finalBaru);
        $gradeBerubah = $gradeLama !== $gradeBaru;

        if (! $finalBerubah && ! $gradeBerubah) {
            return null;
        }

        if (! $finalBerubah) {
            return PaBalancingStatus::SEBAB_KURVA;
        }

        if (! $gradeBerubah || $gradeTengah === $gradeLama) {
            return PaBalancingStatus::SEBAB_BALANCING;
        }

        return $gradeTengah === $gradeBaru ? PaBalancingStatus::SEBAB_KURVA : PaBalancingStatus::SEBAB_KEDUANYA;
    }
}
```

- [ ] **Step 6: Tambah operasi `BALANCING` di `PaCycleGate`**

Sesudah konstanta `BUKA_KURVA` (sekitar baris 173):

```php
    /** Sesi Balancing dan hitung ulangnya: hanya sesudah pengisian ditutup. */
    public const BALANCING = 'BALANCING';
```

Di `IZIN`, sesudah baris `self::BUKA_KURVA => ['OPEN', 'CLOSED'],`:

```php
        self::BALANCING => ['CLOSED'],
```

- [ ] **Step 7: Jalankan uji, pastikan lulus; jalankan uji gerbang lama**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKalkulatorTest.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php tests/Feature/KPI/PerformanceAppraisal/RantaiStatusTest.php`
Expected: semua PASS. Bila `RantaiStatusTest`/`CycleGateTest` menyebut daftar operasi lengkap, tambahkan `BALANCING` di daftar itu dengan status `['CLOSED']`.

- [ ] **Step 8: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingStatus.php \
  app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingException.php \
  app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingKalkulator.php \
  app/Services/KPI/PerformanceAppraisal/PaCycleGate.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKalkulatorTest.php
git commit -m "feat(kpi-pa): kalkulator murni dan gerbang Balancing"
```

---

### Task 3: Satu pintu nilai berlaku, penjaga, dan potret hasil

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingNilaiBerlaku.php`
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingPenjaga.php`
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingPotretHasil.php`
- Create: `tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanBalancing.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingNilaiBerlakuTest.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSatuPintuTest.php`

**Interfaces:**
- Consumes: tabel Task 1, `PaBalancingStatus`, `PaBalancingException`.
- Produces:
  - `PaBalancingNilaiBerlaku::kompetensiBerlaku(int $participantId, ?int $cycleId = null): array<int,float>`
  - `PaBalancingNilaiBerlaku::kualitatifBerlaku(int $participantId): array<int, array{sebelum:?string, sesudah:?string}>`
  - `PaBalancingNilaiBerlaku::muatSiklus(int $cycleId): void`
  - `PaBalancingPenjaga::sesiTerbuka(int $cycleId): Collection` (Id_PA_Balancing_Sesi, Nama_Sesi, Status_Sesi)
  - `PaBalancingPenjaga::sesiFinalBelumDihitung(int $cycleId): Collection` (sama)
  - `PaBalancingPenjaga::sesiTerbukaPeserta(int $participantId): ?object` (Id_PA_Balancing_Sesi, Nama_Sesi)
  - `PaBalancingPenjaga::pastikanTuntas(int $cycleId): void` (lempar `SESI_MASIH_TERBUKA` / `BELUM_DIHITUNG_ULANG`)
  - `PaBalancingPotretHasil::siklus(int $cycleId): array<int, object>` (pid => Nama, Nilai_KPI, Nilai_Kompetensi, Final_Score, Kode_Grade_Final, PA_Hav_Quadrant_Id, Kode_Kuadran, Status_Hasil)
  - Trait `MenyiapkanBalancing`: `pesertaDihitung(array $opsi = []): array`, `palsukanAturan(int $kategoriId, float $bobotKpi = 50.0): void`, `sesiLangsung(int $cycleId, array $afterPerPeserta, string $status = 'FINAL', array $kualitatif = []): int`, `penggunaHasil(): User`

- [ ] **Step 1: Tulis trait fixture**

`tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanBalancing.php`:

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Support;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaParticipantRuleResolver;
use Illuminate\Support\Facades\DB;

/**
 * Peserta yang sudah DIHITUNG, lengkap dengan baris agregat per kompetensi, untuk uji Balancing.
 * Memakai `lembarTerisi()` dari MenyiapkanLembarPenilaian: satu penilai ATASAN, dua kompetensi
 * berskor (bawaan 70 dan 85, tanpa bobot butir → porsi 0,5 dan 0,5).
 */
trait MenyiapkanBalancing
{
    /**
     * @param  array{cycle_id?:int, skor?:list<float>, kpi?:float, final?:float, grade?:string, status_hasil?:string}  $opsi
     * @return array{cycle_id:int, participant_id:int, result_id:int, kompetensi:list<int>, kategori_id:int, assignment_id:int}
     */
    protected function pesertaDihitung(array $opsi = []): array
    {
        $skor = $opsi['skor'] ?? [70.0, 85.0];
        $l = $this->lembarTerisi([
            'status_siklus' => 'CLOSED',
            'status_penugasan' => 'SELESAI',
            'skor' => $skor,
            ...(isset($opsi['cycle_id']) ? ['cycle_id' => $opsi['cycle_id']] : []),
        ]);

        $komp = round(array_sum($skor) / count($skor), 2);
        $kpi = $opsi['kpi'] ?? 90.0;

        $resultId = (int) DB::table('N_PA_Result')->insertGetId([
            'PA_Participant_Id' => $l['participant_id'],
            'Status_Hasil' => $opsi['status_hasil'] ?? 'DIHITUNG',
            'Nilai_KPI' => $kpi,
            'Nilai_Kompetensi' => $komp,
            'Final_Score' => $opsi['final'] ?? round($kpi * 0.5 + $komp * 0.5, 2),
            'Kode_Grade_Sistem' => $opsi['grade'] ?? 'B',
            'Kode_Grade_Final' => $opsi['grade'] ?? 'B',
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Result');

        foreach ($l['kompetensi'] as $i => $cid) {
            DB::table('N_PA_Result_Competency')->insert([
                'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $cid, 'PA_Rater_Category_Id' => null,
                'Nilai' => $skor[$i], 'Jumlah_Penilai' => 1, 'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
            ]);
        }

        return [
            'cycle_id' => $l['cycle_id'], 'participant_id' => $l['participant_id'], 'result_id' => $resultId,
            'kompetensi' => $l['kompetensi'], 'kategori_id' => $l['kategori_id'], 'assignment_id' => $l['assignment_id'],
        ];
    }

    /** Aturan penilai tiruan: satu kategori ATASAN 100%, tanpa jenis wajib, pilar KPI/Kompetensi. */
    protected function palsukanAturan(int $kategoriId, float $bobotKpi = 50.0): void
    {
        $this->mock(PaParticipantRuleResolver::class, function ($m) use ($kategoriId, $bobotKpi) {
            $m->shouldReceive('raterRules')->andReturn(['ATASAN' => [
                'kategori_id' => $kategoriId, 'kode_kategori' => 'ATASAN', 'nama_kategori' => 'Atasan',
                'bobot_persen' => 100.0, 'min_rater' => 1, 'min_rater_selesai' => 1, 'maks_rater' => null,
            ]]);
            $m->shouldReceive('competencyRequirements')->andReturn([]);
            $m->shouldReceive('pillarWeight')->andReturn([
                'bobot_kpi' => $bobotKpi, 'bobot_kompetensi' => 100 - $bobotKpi, 'sumber' => 'SALINAN', 'alasan_perubahan' => null,
            ]);
            $m->shouldReceive('muatOverrideSiklus')->andReturnNull();
        });
    }

    /**
     * Sesi yang ditanam langsung ke tabel — untuk uji pembaca dan hitung ulang, bukan uji penulis.
     *
     * @param  array<int, array<int, float>>  $afterPerPeserta  participant_id => [competency_id => After]
     * @param  array<int, array<int, string>>  $kualitatif  participant_id => [competency_id => isi sesudah]
     */
    protected function sesiLangsung(int $cycleId, array $afterPerPeserta, string $status = 'FINAL', array $kualitatif = []): int
    {
        $sesiId = (int) DB::table('N_PA_Balancing_Sesi')->insertGetId([
            'PA_Cycle_Id' => $cycleId, 'Nama_Sesi' => 'Uji '.substr(bin2hex(random_bytes(3)), 0, 6),
            'PIC_User_Id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'),
            'Status_Sesi' => $status, 'Final_At' => $status === 'FINAL' ? now() : null,
        ], 'Id_PA_Balancing_Sesi');

        foreach ($afterPerPeserta as $pid => $after) {
            $hasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->first();
            $bpId = (int) DB::table('N_PA_Balancing_Peserta')->insertGetId([
                'PA_Balancing_Sesi_Id' => $sesiId, 'PA_Participant_Id' => $pid, 'PA_Result_Id' => $hasil->Id_PA_Result,
                'Calculation_Version_Sebelum' => $hasil->Calculation_Version, 'Final_Score_Sebelum' => $hasil->Final_Score,
                'Nilai_Kompetensi_Sebelum' => $hasil->Nilai_Kompetensi, 'Nilai_KPI_Sebelum' => $hasil->Nilai_KPI,
                'Kode_Grade_Sebelum' => $hasil->Kode_Grade_Final, 'Status_Tinjau' => 'DIUBAH',
            ], 'Id_PA_Balancing_Peserta');

            $sebelum = DB::table('N_PA_Result_Competency')->where('PA_Result_Id', $hasil->Id_PA_Result)
                ->where('Flag_Aktif', 'Y')->whereNull('PA_Rater_Category_Id')->pluck('Nilai', 'PA_Competency_Id');

            foreach ($after as $cid => $nilai) {
                DB::table('N_PA_Balancing_Kompetensi')->insert([
                    'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => $cid, 'Bobot' => 0.5,
                    'Nilai_Sebelum' => (float) ($sebelum[$cid] ?? $nilai), 'Nilai_Sesudah' => $nilai, 'Alasan' => 'Uji',
                ]);
            }

            foreach ($kualitatif[$pid] ?? [] as $cid => $isi) {
                DB::table('N_PA_Balancing_Kualitatif')->insert([
                    'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => $cid, 'Isi_Sebelum' => null, 'Isi_Sesudah' => $isi,
                ]);
            }
        }

        return $sesiId;
    }

    protected function penggunaHasil(): User
    {
        $userId = DB::table('HRIS_Page_Access')->where('Jenis_page', 'PaHasilPage')->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')->value('UserID_Web');
        $this->assertNotNull($userId, 'Tidak ada user berakses PaHasilPage.');

        return User::find($userId);
    }
}
```

- [ ] **Step 2: Tulis uji pembaca yang gagal**

`tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingNilaiBerlakuTest.php`:

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingNilaiBerlakuTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_tanpa_sesi_final_tidak_ada_nilai_berlaku(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');

        $this->assertSame([], app(PaBalancingNilaiBerlaku::class)->kompetensiBerlaku($p['participant_id']));
    }

    public function test_sesi_final_terbaru_yang_menang(): void
    {
        $p = $this->pesertaDihitung();
        [$k1] = $p['kompetensi'];
        $lama = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 80.0]]);
        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $lama)->update(['Final_At' => now()->subDay()]);
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 75.0]]);
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 99.0]], 'DIBATALKAN');

        $this->assertSame([$k1 => 75.0], app(PaBalancingNilaiBerlaku::class)->kompetensiBerlaku($p['participant_id']));
    }

    public function test_muat_siklus_sama_dengan_baca_satu_per_satu(): void
    {
        $a = $this->pesertaDihitung();
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id']]);
        $this->sesiLangsung($a['cycle_id'], [$a['participant_id'] => [$a['kompetensi'][0] => 88.0]]);

        $pembaca = app(PaBalancingNilaiBerlaku::class);
        $pembaca->muatSiklus($a['cycle_id']);

        $this->assertSame([$a['kompetensi'][0] => 88.0], $pembaca->kompetensiBerlaku($a['participant_id'], $a['cycle_id']));
        $this->assertSame([], $pembaca->kompetensiBerlaku($b['participant_id'], $a['cycle_id']));
    }

    public function test_penjaga_menolak_publish_selama_sesi_terbuka_atau_belum_dihitung(): void
    {
        $p = $this->pesertaDihitung();
        $penjaga = app(PaBalancingPenjaga::class);
        $penjaga->pastikanTuntas($p['cycle_id']);

        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');

        try {
            $penjaga->pastikanTuntas($p['cycle_id']);
            $this->fail('Sesi terbuka harus menahan publish.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::SESI_MASIH_TERBUKA, $e->errorCode);
        }

        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->update(['Status_Sesi' => 'FINAL', 'Final_At' => now()]);

        try {
            $penjaga->pastikanTuntas($p['cycle_id']);
            $this->fail('Sesi FINAL yang belum dihitung ulang harus menahan publish.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::BELUM_DIHITUNG_ULANG, $e->errorCode);
        }

        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->update(['Hitung_Ulang_At' => now(), 'Nomor_Hitung_Ulang' => 1]);
        $penjaga->pastikanTuntas($p['cycle_id']);
        $this->assertNull($penjaga->sesiTerbukaPeserta($p['participant_id']));
    }
}
```

- [ ] **Step 3: Jalankan, pastikan gagal**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingNilaiBerlakuTest.php`
Expected: FAIL, kelas `PaBalancingNilaiBerlaku` tidak ditemukan.

- [ ] **Step 4: Tulis `PaBalancingNilaiBerlaku.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Facades\DB;

/**
 * SATU PINTU nilai After yang berlaku (spec §4–§5).
 *
 * Nilai berlaku satu peserta = isian sesi FINAL TERBARU yang memuatnya. Sesi DRAFT atau
 * DIBATALKAN tidak pernah berlaku. Hanya kelas ini yang boleh membaca `Nilai_Sesudah` dan
 * `Isi_Sesudah` untuk keperluan nilai; `BalancingSatuPintuTest` menjaganya.
 */
class PaBalancingNilaiBerlaku
{
    private ?int $siklusTermuat = null;

    /** @var array<int, array<int, float>> */
    private array $petaSiklus = [];

    /**
     * Memuat nilai berlaku seluruh peserta satu siklus dalam satu kueri. Dipanggil
     * `hitungSiklus`; tanpa ini tiap peserta menambah satu perjalanan ke basis data.
     */
    public function muatSiklus(int $cycleId): void
    {
        $terbaru = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Status_Sesi', PaBalancingStatus::FINAL)
            ->orderBy('s.Final_At')->orderBy('s.Id_PA_Balancing_Sesi')
            ->get(['bp.PA_Participant_Id', 'bp.Id_PA_Balancing_Peserta'])
            // Urut naik lalu ditimpa: yang tersisa per peserta adalah sesi FINAL terbaru.
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Participant_Id => (int) $r->Id_PA_Balancing_Peserta]);

        $this->petaSiklus = [];

        if ($terbaru->isNotEmpty()) {
            $peserta = $terbaru->flip();

            foreach ($terbaru->values()->chunk(1000) as $potongan) {
                DB::table('N_PA_Balancing_Kompetensi')
                    ->whereIn('PA_Balancing_Peserta_Id', $potongan->all())
                    ->get(['PA_Balancing_Peserta_Id', 'PA_Competency_Id', 'Nilai_Sesudah'])
                    ->each(function ($r) use ($peserta) {
                        $this->petaSiklus[(int) $peserta[(int) $r->PA_Balancing_Peserta_Id]][(int) $r->PA_Competency_Id] = (float) $r->Nilai_Sesudah;
                    });
            }
        }

        $this->siklusTermuat = $cycleId;
    }

    /** @return array<int, float> competency_id => After */
    public function kompetensiBerlaku(int $participantId, ?int $cycleId = null): array
    {
        if ($cycleId !== null && $cycleId === $this->siklusTermuat) {
            return $this->petaSiklus[$participantId] ?? [];
        }

        $bp = $this->pesertaSesiFinalTerbaru($participantId);

        if ($bp === null) {
            return [];
        }

        return DB::table('N_PA_Balancing_Kompetensi')->where('PA_Balancing_Peserta_Id', $bp)
            ->get(['PA_Competency_Id', 'Nilai_Sesudah'])
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Competency_Id => (float) $r->Nilai_Sesudah])
            ->all();
    }

    /** @return array<int, array{sebelum:?string, sesudah:?string}> competency_id => isi */
    public function kualitatifBerlaku(int $participantId): array
    {
        $bp = $this->pesertaSesiFinalTerbaru($participantId);

        if ($bp === null) {
            return [];
        }

        return DB::table('N_PA_Balancing_Kualitatif')->where('PA_Balancing_Peserta_Id', $bp)
            ->get(['PA_Competency_Id', 'Isi_Sebelum', 'Isi_Sesudah'])
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Competency_Id => ['sebelum' => $r->Isi_Sebelum, 'sesudah' => $r->Isi_Sesudah]])
            ->all();
    }

    public function lupakan(): void
    {
        $this->siklusTermuat = null;
        $this->petaSiklus = [];
    }

    private function pesertaSesiFinalTerbaru(int $participantId): ?int
    {
        $id = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('bp.PA_Participant_Id', $participantId)
            ->where('s.Status_Sesi', PaBalancingStatus::FINAL)
            ->orderByDesc('s.Final_At')->orderByDesc('s.Id_PA_Balancing_Sesi')
            ->value('bp.Id_PA_Balancing_Peserta');

        return $id === null ? null : (int) $id;
    }
}
```

- [ ] **Step 5: Tulis `PaBalancingPenjaga.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Pertanyaan status Balancing yang dipakai lintas service (hitung, publish, sesi). */
class PaBalancingPenjaga
{
    public function sesiTerbuka(int $cycleId): Collection
    {
        return DB::table('N_PA_Balancing_Sesi')->where('PA_Cycle_Id', $cycleId)
            ->whereIn('Status_Sesi', PaBalancingStatus::SESI_BISA_DIUBAH)
            ->orderBy('Id_PA_Balancing_Sesi')->get(['Id_PA_Balancing_Sesi', 'Nama_Sesi', 'Status_Sesi']);
    }

    public function sesiFinalBelumDihitung(int $cycleId): Collection
    {
        return DB::table('N_PA_Balancing_Sesi')->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Sesi', PaBalancingStatus::FINAL)->whereNull('Hitung_Ulang_At')
            ->orderBy('Final_At')->orderBy('Id_PA_Balancing_Sesi')->get(['Id_PA_Balancing_Sesi', 'Nama_Sesi', 'Status_Sesi']);
    }

    public function sesiTerbukaPeserta(int $participantId): ?object
    {
        return DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('bp.PA_Participant_Id', $participantId)
            ->whereIn('s.Status_Sesi', PaBalancingStatus::SESI_BISA_DIUBAH)
            ->first(['s.Id_PA_Balancing_Sesi', 's.Nama_Sesi']);
    }

    /** Syarat publish (rilis leader maupun terbit): tidak ada sesi terbuka, semua FINAL sudah dihitung ulang. */
    public function pastikanTuntas(int $cycleId): void
    {
        $terbuka = $this->sesiTerbuka($cycleId);

        if ($terbuka->isNotEmpty()) {
            throw new PaBalancingException(
                'Masih ada sesi Balancing yang terbuka: '.$terbuka->pluck('Nama_Sesi')->implode(', ')
                .'. Finalkan atau batalkan sesinya lebih dulu.',
                PaBalancingException::SESI_MASIH_TERBUKA,
            );
        }

        $belum = $this->sesiFinalBelumDihitung($cycleId);

        if ($belum->isNotEmpty()) {
            throw new PaBalancingException(
                'Sesi Balancing '.$belum->pluck('Nama_Sesi')->implode(', ')
                .' sudah FINAL tetapi belum dihitung ulang. Jalankan hitung ulang Balancing lebih dulu.',
                PaBalancingException::BELUM_DIHITUNG_ULANG,
            );
        }
    }
}
```

- [ ] **Step 6: Tulis `PaBalancingPotretHasil.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Facades\DB;

/** Final, grade, dan kotak HAV seluruh hasil aktif satu siklus — untuk sebelum/sesudah. */
class PaBalancingPotretHasil
{
    /** @return array<int, object> participant_id => baris */
    public function siklus(int $cycleId): array
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')
            ->get([
                'p.Id_PA_Participant', 'p.Nama_Karyawan_Saat_Cycle as Nama', 'r.Status_Hasil', 'r.Nilai_KPI',
                'r.Nilai_Kompetensi', 'r.Final_Score', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id', 'q.Kode_Kuadran',
            ])
            ->mapWithKeys(fn ($r) => [(int) $r->Id_PA_Participant => (object) [
                'Nama' => $r->Nama,
                'Status_Hasil' => $r->Status_Hasil,
                'Nilai_KPI' => $r->Nilai_KPI === null ? null : (float) $r->Nilai_KPI,
                'Nilai_Kompetensi' => $r->Nilai_Kompetensi === null ? null : (float) $r->Nilai_Kompetensi,
                'Final_Score' => $r->Final_Score === null ? null : (float) $r->Final_Score,
                'Kode_Grade_Final' => $r->Kode_Grade_Final,
                'PA_Hav_Quadrant_Id' => $r->PA_Hav_Quadrant_Id === null ? null : (int) $r->PA_Hav_Quadrant_Id,
                'Kode_Kuadran' => $r->Kode_Kuadran,
            ]])
            ->all();
    }
}
```

- [ ] **Step 7: Tulis uji satu pintu**

`tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSatuPintuTest.php`:

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Skor After Balancing hanya boleh DIBACA lewat PaBalancingNilaiBerlaku (spec §5).
 *
 * Pembaca kedua yang menerapkan After sendiri — radar, ekspor, laporan — akan diam-diam
 * berbeda dari mesin hitung begitu aturan "sesi FINAL terbaru" berubah. Penulis isian
 * (sesi) dan tampilan riwayat sesi boleh menyentuhnya; semuanya disebut di daftar izin.
 */
class BalancingSatuPintuTest extends TestCase
{
    private const KOLOM = ['Nilai_Sesudah', 'Isi_Sesudah'];

    private const DAFTAR_IZIN = [
        // Pintunya sendiri.
        'app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingNilaiBerlaku.php',
        // Penulis isian sesi dan pembaca detail sesi (menampilkan isian, bukan menghitung).
        'app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSesiService.php',
        // Simulasi membaca isian sesi yang SEDANG dibahas sebagai titik awal isian layar.
        'app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSimulasiService.php',
    ];

    public function test_hanya_pintu_nilai_berlaku_yang_membaca_after(): void
    {
        $akar = base_path('app');
        $pelanggar = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar)) as $berkas) {
            if (! $berkas->isFile() || $berkas->getExtension() !== 'php') {
                continue;
            }

            $relatif = 'app/'.ltrim(str_replace($akar, '', $berkas->getPathname()), '/');

            if (in_array($relatif, self::DAFTAR_IZIN, true)) {
                continue;
            }

            $isi = file_get_contents($berkas->getPathname());

            foreach (self::KOLOM as $kolom) {
                if (str_contains($isi, $kolom)) {
                    $pelanggar[] = "{$relatif} ({$kolom})";
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "After Balancing hanya boleh dibaca lewat PaBalancingNilaiBerlaku.\n"
            .'Pelanggaran: '.implode('; ', $pelanggar));
    }
}
```

- [ ] **Step 8: Jalankan kedua uji, pastikan lulus**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/`
Expected: PASS (uji satu pintu lulus karena `PaBalancingSesiService`/`PaBalancingSimulasiService` belum ada).

- [ ] **Step 9: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingNilaiBerlaku.php \
  app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingPenjaga.php \
  app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingPotretHasil.php \
  tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanBalancing.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingNilaiBerlakuTest.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSatuPintuTest.php
git commit -m "feat(kpi-pa): satu pintu nilai berlaku dan penjaga Balancing"
```

---

### Task 4: Lapisan Balancing di mesin hitung

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingMesinHitungTest.php`

**Interfaces:**
- Consumes: `PaBalancingKalkulator::{porsiPenilai, bobotKompetensi, nilaiKompetensi, nilaiAkhir}`, `PaBalancingNilaiBerlaku::{kompetensiBerlaku, muatSiklus}`, `PaBalancingPenjaga::sesiTerbukaPeserta`, trait `MenyiapkanBalancing`.
- Produces:
  - `PaResultCalculationService::rincianKompetensi(int $participantId, ?array $berlakuOverride = null): array` — keluaran `tahapKompetensi` ditambah kunci `nilai_mesin:?float`, `bobot_kompetensi:array<int,float>`, `kompetensi_mesin:array<int,float>`.
  - `PaResultCalculationService::simulasiHasil(int $participantId, ?array $berlakuOverride = null): array{nilai_kompetensi:?float, final:?float, kuadran_id:?int, kode_kuadran:?string, grade_pita:?string, alasan_tertahan:?string}`
  - `hitung()` menolak peserta yang ada di sesi TERBUKA/DRAFT.

- [ ] **Step 1: Tulis uji yang gagal**

`tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingMesinHitungTest.php`:

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingMesinHitungTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private function mesin(): PaResultCalculationService
    {
        return app(PaResultCalculationService::class);
    }

    public function test_tanpa_balancing_nilai_sama_dengan_mesin(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);

        $r = $this->mesin()->rincianKompetensi($p['participant_id']);

        // (70 + 85) / 2 = 77,5; porsi 0,5 dan 0,5.
        $this->assertSame(77.5, $r['nilai']);
        $this->assertSame(77.5, $r['nilai_mesin']);
        $this->assertEqualsWithDelta(0.5, $r['bobot_kompetensi'][$p['kompetensi'][0]], 1e-9);
        $this->assertEqualsWithDelta(0.5, $r['bobot_kompetensi'][$p['kompetensi'][1]], 1e-9);
    }

    public function test_bobot_tepat_sama_dengan_menggeser_jawaban_penilai(): void
    {
        // Sifat §6.1: menaikkan semua jawaban kompetensi k sebesar Δ menaikkan Nilai Kompetensi
        // tepat sebesar bobotₖ × Δ. Dibuktikan dengan mengubah skor mentah lalu menghitung ulang.
        $p = $this->pesertaDihitung(['skor' => [60.0, 80.0, 90.0]]);
        $this->palsukanAturan($p['kategori_id']);
        $k1 = $p['kompetensi'][0];

        $awal = $this->mesin()->rincianKompetensi($p['participant_id']);
        DB::table('N_PA_Assignment_Score')->where('PA_Competency_Id', $k1)->where('Kode_Soal_Sumber', 'UJI1')
            ->whereIn('PA_Assignment_Payload_Id', DB::table('N_PA_Assignment_Payload as ap')
                ->join('N_PA_Assignment_Integration as ai', 'ai.Id_PA_Assignment_Integration', '=', 'ap.PA_Assignment_Integration_Id')
                ->where('ai.PA_Assignment_Id', $p['assignment_id'])->pluck('ap.Id_PA_Assignment_Payload'))
            ->update(['Skor' => 72.0]);
        app()->forgetInstance(PaResultCalculationService::class);
        $sesudah = $this->mesin()->rincianKompetensi($p['participant_id']);

        $this->assertEqualsWithDelta($awal['nilai'] + $awal['bobot_kompetensi'][$k1] * 12.0, $sesudah['nilai'], 0.01);
    }

    public function test_sesi_final_menggeser_nilai_dan_baris_radar(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 80.0, $k2 => 85.0]]);

        $r = $this->mesin()->rincianKompetensi($p['participant_id']);

        // 77,5 + 0,5 × (80 − 70) = 82,5
        $this->assertSame(82.5, $r['nilai']);
        $this->assertSame(77.5, $r['nilai_mesin']);
        $agregat = collect($r['kompetensi'])->whereNull('kategori_id')->pluck('nilai', 'competency_id');
        $this->assertSame(80.0, (float) $agregat[$k1]);
    }

    public function test_dua_sesi_berurutan_tidak_menjumlah_dua_kali(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $pertama = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 80.0, $k2 => 85.0]]);
        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $pertama)
            ->update(['Final_At' => now()->subHour(), 'Hitung_Ulang_At' => now()->subMinutes(30), 'Nomor_Hitung_Ulang' => 1]);
        // Sesi kedua: Before = 80 (After sesi pertama), After = 90.
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 90.0, $k2 => 85.0]]);

        // 77,5 + 0,5 × (90 − 70) = 87,5 — bukan 77,5 + 0,5×10 + 0,5×10 dari dua selisih bertumpuk.
        $this->assertSame(87.5, $this->mesin()->rincianKompetensi($p['participant_id'])['nilai']);
    }

    public function test_simulasi_memakai_isian_layar_tanpa_menulis(): void
    {
        $p = $this->pesertaDihitung(['kpi' => 90.0]);
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $sebelum = DB::table('N_PA_Result')->where('Id_PA_Result', $p['result_id'])->first();

        $s = $this->mesin()->simulasiHasil($p['participant_id'], [$k1 => 90.0, $k2 => 85.0]);

        // Komp 77,5 + 0,5 × 20 = 87,5; Final = 90 × 50% + 87,5 × 50% = 88,75.
        $this->assertSame(87.5, $s['nilai_kompetensi']);
        $this->assertSame(PaBalancingKalkulator::nilaiAkhir(90.0, 87.5, 50.0, 50.0), $s['final']);
        $this->assertEquals($sebelum, DB::table('N_PA_Result')->where('Id_PA_Result', $p['result_id'])->first(), 'Simulasi tidak boleh menulis.');
    }

    public function test_hitung_ditolak_selama_peserta_ada_di_sesi_terbuka(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 80.0]], 'DRAFT');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sedang dibahas di sesi Balancing/');
        $this->mesin()->hitung($p['participant_id']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingMesinHitungTest.php`
Expected: FAIL, `Call to undefined method …::rincianKompetensi()`.

- [ ] **Step 3: Sambungkan dependensi**

Di `PaResultCalculationService.php` tambah `use`:

```php
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
```

Konstruktor (baris ~107) menjadi:

```php
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaBobotGuard $bobot,
        private readonly KpiScoringService $scoring,
        private readonly PaSkorEfektif $skorEfektif,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingNilaiBerlaku $balancing,
        private readonly PaBalancingPenjaga $penjagaBalancing,
    ) {}
```

- [ ] **Step 4: Porsi per penilai**

Di `penugasanDenganNilai()`, baris penyusun penilai menjadi:

```php
            $hasil[$kategoriId]['penilai'][] = [
                'assignment_id' => (int) $t->Id_PA_Assignment,
                'bobot' => (float) $t->Bobot_Dalam_Kategori,
                'nilai' => $this->nilaiSatuPenilai($butir),
                'kompetensi' => $this->nilaiKompetensiSatuPenilai($butir),
                // Porsi tiap kompetensi di nilai penilai ini — bahan bobot Balancing (spec §6.1).
                'porsi' => PaBalancingKalkulator::porsiPenilai($butir),
            ];
```

- [ ] **Step 5: Lapisan Balancing di `tahapKompetensi`**

Ubah tanda tangan:

```php
    private function tahapKompetensi(int $participantId, ?array $berlakuOverride = null, ?int $cycleId = null): array
```

Ganti blok `return` terakhir (jalur sukses, sesudah pemeriksaan `$tidakTercakup`) dengan:

```php
        /* LAPISAN BALANCING (spec §6). Nilai mesin tidak pernah ditimpa; yang berlaku adalah
           mesin + selisih berbobot dari After sesi FINAL terbaru. Tanpa sesi, hasilnya sama
           persis dengan sebelum fitur ini ada. */
        $bobotKompetensi = PaBalancingKalkulator::bobotKompetensi(array_values(array_map(
            fn (string $kode) => ['bobot_terpakai' => (float) $bobotTerpakai[$kode], 'penilai' => $kategoriInfo[$kode]['penilai']],
            array_keys($bobotTerpakai),
        )));

        $kompetensiMesin = [];

        foreach ($kompetensi as $k) {
            if ($k['kategori_id'] === null) {
                $kompetensiMesin[$k['competency_id']] = (float) $k['nilai'];
            }
        }

        $berlaku = $berlakuOverride ?? $this->balancing->kompetensiBerlaku($participantId, $cycleId);

        foreach ($kompetensi as $i => $k) {
            // Radar menampilkan nilai yang berlaku; baris per kategori tetap apa kata penilai.
            if ($k['kategori_id'] === null && isset($berlaku[$k['competency_id']])) {
                $kompetensi[$i]['nilai'] = round((float) $berlaku[$k['competency_id']], 2);
            }
        }

        return [
            'nilai' => PaBalancingKalkulator::nilaiKompetensi($nilai, $bobotKompetensi, $kompetensiMesin, $berlaku),
            'nilai_mesin' => round($nilai, 2),
            'bobot_kompetensi' => $bobotKompetensi,
            'kompetensi_mesin' => $kompetensiMesin,
            'rater_diharapkan' => $diharapkanTotal,
            'rater_mengisi' => $mengisiTotal,
            'kategori' => $this->tanpaPenilai($kategoriInfo),
            'kompetensi' => $kompetensi,
            'alasan_tertahan' => null,
        ];
```

Catatan: `tanpaPenilai()` membuang kunci `penilai` dari kategori, jadi `porsi` tidak ikut tersimpan.

- [ ] **Step 6: Penjaga dan rumus di `hitung()`**

Sesudah blok penolakan `STATUS_TERBIT` di `hitung()`:

```php
        /* Peserta yang SEDANG dibahas di sesi Balancing tidak dihitung ulang. Before di sesi
           itu adalah potret hasil saat sesi dibuat; menghitung ulang sekarang membuatnya basi
           tanpa ada yang tahu — termasuk dari kiriman HC Learn susulan. */
        $sesiTerbuka = $this->penjagaBalancing->sesiTerbukaPeserta($participantId);

        if ($sesiTerbuka !== null) {
            throw new RuntimeException(
                "Peserta {$participantId} sedang dibahas di sesi Balancing \"{$sesiTerbuka->Nama_Sesi}\". "
                .'Finalkan atau batalkan sesinya lebih dulu, lalu hitung ulang.'
            );
        }
```

Ganti `$kompetensi = $this->tahapKompetensi($participantId);` menjadi:

```php
        $kompetensi = $this->tahapKompetensi($participantId, null, (int) $peserta->PA_Cycle_Id);
```

Ganti perhitungan `$final = round(...)` menjadi:

```php
                $final = PaBalancingKalkulator::nilaiAkhir(
                    (float) $kpi['nilai'], (float) $kompetensi['nilai'],
                    (float) $pilar['bobot_kpi'], (float) $pilar['bobot_kompetensi'],
                );
```

Di `hitungSiklus()`, sesudah `$this->resolver->muatOverrideSiklus($cycleId);`:

```php
        // Nilai berlaku Balancing seluruh siklus dimuat sekali, bukan satu kueri per peserta.
        $this->balancing->muatSiklus($cycleId);
```

Di `lupakanKonteks()` tambahkan `$this->balancing->lupakan();`.

- [ ] **Step 7: Metode publik baca-saja**

Tambahkan sesudah `hitung()`:

```php
    /**
     * Rincian pilar kompetensi TANPA menulis apa pun. Dipakai Balancing untuk membekukan
     * bobot saat sesi dibuat, dan untuk simulasi.
     *
     * @param  array<int, float>|null  $berlakuOverride  null = nilai berlaku dari sesi FINAL
     * @return array<string, mixed>
     */
    public function rincianKompetensi(int $participantId, ?array $berlakuOverride = null): array
    {
        return $this->tahapKompetensi($participantId, $berlakuOverride);
    }

    /**
     * Nilai Kompetensi, Final, kotak HAV, dan grade PITA seandainya After = `$berlakuOverride`.
     * Tidak menulis. KPI dibaca dari hasil tersimpan: Balancing tidak menyentuh pilar KPI.
     * Grade siklus KURVA TIDAK dihitung di sini — ia bergantung pada orang lain dan dihitung
     * `PaKurvaService::simulasikanGrade` atas seluruh grup.
     *
     * @param  array<int, float>|null  $berlakuOverride
     * @return array{nilai_kompetensi:?float, final:?float, kuadran_id:?int, kode_kuadran:?string, grade_pita:?string, alasan_tertahan:?string}
     */
    public function simulasiHasil(int $participantId, ?array $berlakuOverride = null): array
    {
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');
        $kpi = DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)->where('Flag_Aktif', 'Y')->value('Nilai_KPI');
        $komp = $this->tahapKompetensi($participantId, $berlakuOverride, $cycleId);

        if ($komp['nilai'] === null || $kpi === null) {
            return [
                'nilai_kompetensi' => $komp['nilai'], 'final' => null, 'kuadran_id' => null, 'kode_kuadran' => null,
                'grade_pita' => null, 'alasan_tertahan' => $komp['alasan_tertahan'] ?? 'Nilai KPI peserta ini belum ada.',
            ];
        }

        $pilar = $this->resolver->pillarWeight($participantId);
        $final = PaBalancingKalkulator::nilaiAkhir((float) $kpi, (float) $komp['nilai'], (float) $pilar['bobot_kpi'], (float) $pilar['bobot_kompetensi']);
        [$kuadranId, $kodeKuadran] = $this->cariKuadran($cycleId, (float) $kpi, (float) $komp['nilai']);

        return [
            'nilai_kompetensi' => (float) $komp['nilai'],
            'final' => $final,
            'kuadran_id' => $kuadranId,
            'kode_kuadran' => $kodeKuadran,
            'grade_pita' => $this->konteks($cycleId)['metode_grade'] === 'KURVA'
                ? null
                : $this->cariPita($cycleId, 'FINAL', 'GRADE', $final)?->Kode_Range,
            'alasan_tertahan' => null,
        ];
    }
```

- [ ] **Step 8: Jalankan uji baru dan uji perhitungan lama**

Run:
```bash
unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/ \
  tests/Feature/KPI/PerformanceAppraisal/ResultCalculationArithmeticTest.php \
  tests/Feature/KPI/PerformanceAppraisal/ServiceFlowTest.php \
  tests/Feature/KPI/PerformanceAppraisal/RantaiPenuhTest.php \
  tests/Feature/KPI/PerformanceAppraisal/KurvaPerhitunganTest.php \
  tests/Feature/KPI/PerformanceAppraisal/EsaiPerSoalTest.php
```
Expected: PASS. Kegagalan data-dependent yang sudah tercatat di memori `kendala-environment-kpi-unreal` (mis. `CycleSetupTest`) bukan regresi. Bila ragu, jalankan uji yang sama di worktree lain yang berada di `origin/staging` lalu bandingkan (jangan pakai `git stash`).

- [ ] **Step 9: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingMesinHitungTest.php
git commit -m "feat(kpi-pa): lapisan Balancing di mesin hitung kompetensi"
```

---

### Task 5: Kandidat peserta sesi

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingKandidatService.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKandidatTest.php`

**Interfaces:**
- Consumes: `PaBalancingStatus`.
- Produces:
  - `PaBalancingKandidatService::daftar(int $cycleId, array $saringan = []): list<array{participant_id:int, nama:string, kode_karyawan:string, level_id:?int, level:?string, divisi_id:?int, divisi:?string, sub_divisi_id:?int, sub_divisi:?string, grup_kurva_id:?int, grup_kurva:?string, grade:?string, kuadran_id:?int, kode_kuadran:?string, final:?float, jumlah_sesi:int, layak:bool, alasan:?string}>`
    - saringan: `level_ids`, `divisi_ids`, `sub_divisi_ids`, `grup_kurva_ids`, `grade` (list kode), `kuadran_ids`, `jumlah_sesi` (int), `cari` (string)
  - `PaBalancingKandidatService::alasanTidakLayak(int $cycleId, array $participantIds): list<array{participant_id:int, nama:string, alasan:string}>`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingKandidatTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_alasan_tidak_layak_disebut_per_orang(): void
    {
        $siap = $this->pesertaDihitung();
        $c = $siap['cycle_id'];
        $tertahan = $this->pesertaDihitung(['cycle_id' => $c, 'status_hasil' => 'TERTAHAN']);
        $terbit = $this->pesertaDihitung(['cycle_id' => $c, 'status_hasil' => 'TERBIT']);
        $leader = $this->pesertaDihitung(['cycle_id' => $c]);
        DB::table('N_PA_Result')->where('Id_PA_Result', $leader['result_id'])->update(['Published_Leader_At' => now()]);
        $dibahas = $this->pesertaDihitung(['cycle_id' => $c]);
        $this->sesiLangsung($c, [$dibahas['participant_id'] => [$dibahas['kompetensi'][0] => 80.0]], 'DRAFT');

        $daftar = collect(app(PaBalancingKandidatService::class)->daftar($c))->keyBy('participant_id');

        $this->assertTrue($daftar[$siap['participant_id']]['layak']);
        $this->assertStringContainsString('belum DIHITUNG', $daftar[$tertahan['participant_id']]['alasan']);
        $this->assertStringContainsString('sudah terbit', $daftar[$terbit['participant_id']]['alasan']);
        $this->assertStringContainsString('dirilis ke leader', $daftar[$leader['participant_id']]['alasan']);
        $this->assertStringContainsString('Sedang dibahas di sesi', $daftar[$dibahas['participant_id']]['alasan']);

        $tolak = app(PaBalancingKandidatService::class)->alasanTidakLayak($c, [$siap['participant_id'], $tertahan['participant_id'], 999999999]);
        $this->assertSame([$tertahan['participant_id'], 999999999], array_column($tolak, 'participant_id'));
    }

    public function test_saringan_jumlah_sesi_dan_grade(): void
    {
        $a = $this->pesertaDihitung(['grade' => 'A']);
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'grade' => 'B']);
        $this->sesiLangsung($a['cycle_id'], [$a['participant_id'] => [$a['kompetensi'][0] => 80.0]]);

        $layanan = app(PaBalancingKandidatService::class);

        $this->assertSame([$a['participant_id']], array_column($layanan->daftar($a['cycle_id'], ['jumlah_sesi' => 1]), 'participant_id'));
        $this->assertSame([$b['participant_id']], array_column($layanan->daftar($a['cycle_id'], ['grade' => ['B']]), 'participant_id'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKandidatTest.php` → FAIL (kelas tidak ada).

- [ ] **Step 3: Tulis `PaBalancingKandidatService.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Facades\DB;

/**
 * Kandidat peserta sesi Balancing (B6). Kandidat yang tidak layak TETAP dikembalikan beserta
 * alasannya: layar menampilkannya abu-abu, jadi admin tahu kenapa seseorang tidak bisa dipilih.
 */
class PaBalancingKandidatService
{
    /** @return list<array<string, mixed>> */
    public function daftar(int $cycleId, array $saringan = []): array
    {
        $q = $this->kueri($cycleId);

        foreach ([
            'level_ids' => 'p.ID_Level_Saat_Cycle', 'divisi_ids' => 'p.ID_Divisi_Saat_Cycle',
            'sub_divisi_ids' => 'p.ID_Sub_Divisi_Saat_Cycle', 'grup_kurva_ids' => 'gl.PA_Cycle_Curve_Group_Id',
            'grade' => 'r.Kode_Grade_Final', 'kuadran_ids' => 'r.PA_Hav_Quadrant_Id',
        ] as $kunci => $kolom) {
            if (! empty($saringan[$kunci])) {
                $q->whereIn($kolom, (array) $saringan[$kunci]);
            }
        }

        if (($saringan['cari'] ?? '') !== '') {
            $cari = '%'.trim((string) $saringan['cari']).'%';
            $q->where(fn ($w) => $w->where('p.Nama_Karyawan_Saat_Cycle', 'like', $cari)->orWhere('p.Kode_Karyawan', 'like', $cari));
        }

        $baris = $q->orderBy('p.Nama_Karyawan_Saat_Cycle')->get();
        [$terbuka, $jumlah] = $this->petaSesi($cycleId);

        $hasil = [];

        foreach ($baris as $r) {
            $pid = (int) $r->Id_PA_Participant;
            $jumlahSesi = $jumlah[$pid] ?? 0;

            if (array_key_exists('jumlah_sesi', $saringan) && $saringan['jumlah_sesi'] !== null && $jumlahSesi !== (int) $saringan['jumlah_sesi']) {
                continue;
            }

            $alasan = $this->alasan($r, $terbuka[$pid] ?? null);

            $hasil[] = [
                'participant_id' => $pid,
                'nama' => (string) $r->Nama_Karyawan_Saat_Cycle,
                'kode_karyawan' => (string) $r->Kode_Karyawan,
                'level_id' => $r->ID_Level_Saat_Cycle === null ? null : (int) $r->ID_Level_Saat_Cycle,
                'level' => $r->Nama_Level_Saat_Cycle,
                'divisi_id' => $r->ID_Divisi_Saat_Cycle === null ? null : (int) $r->ID_Divisi_Saat_Cycle,
                'divisi' => $r->Nama_Divisi_Saat_Cycle,
                'sub_divisi_id' => $r->ID_Sub_Divisi_Saat_Cycle === null ? null : (int) $r->ID_Sub_Divisi_Saat_Cycle,
                'sub_divisi' => $r->Nama_Sub_Divisi_Saat_Cycle,
                'grup_kurva_id' => $r->grup_id === null ? null : (int) $r->grup_id,
                'grup_kurva' => $r->Nama_Grup,
                'grade' => $r->Kode_Grade_Final,
                'kuadran_id' => $r->PA_Hav_Quadrant_Id === null ? null : (int) $r->PA_Hav_Quadrant_Id,
                'kode_kuadran' => $r->Kode_Kuadran,
                'final' => $r->Final_Score === null ? null : (float) $r->Final_Score,
                'jumlah_sesi' => $jumlahSesi,
                'layak' => $alasan === null,
                'alasan' => $alasan,
            ];
        }

        return $hasil;
    }

    /** @return list<array{participant_id:int, nama:string, alasan:string}> */
    public function alasanTidakLayak(int $cycleId, array $participantIds): array
    {
        $baris = $this->kueri($cycleId)->whereIn('p.Id_PA_Participant', $participantIds)->get()->keyBy('Id_PA_Participant');
        [$terbuka] = $this->petaSesi($cycleId);
        $tolak = [];

        foreach ($participantIds as $pid) {
            $r = $baris[$pid] ?? null;

            if ($r === null) {
                $tolak[] = ['participant_id' => (int) $pid, 'nama' => "#{$pid}", 'alasan' => 'Bukan peserta siklus ini'];

                continue;
            }

            $alasan = $this->alasan($r, $terbuka[(int) $pid] ?? null);

            if ($alasan !== null) {
                $tolak[] = ['participant_id' => (int) $pid, 'nama' => (string) $r->Nama_Karyawan_Saat_Cycle, 'alasan' => $alasan];
            }
        }

        return $tolak;
    }

    private function kueri(int $cycleId)
    {
        return DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', fn ($j) => $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', 'Y'))
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->leftJoin('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Cycle_Curve_Group as g', 'g.Id_PA_Cycle_Curve_Group', '=', 'gl.PA_Cycle_Curve_Group_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->select([
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle', 'p.ID_Level_Saat_Cycle',
                'p.Nama_Level_Saat_Cycle', 'p.ID_Divisi_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle',
                'p.ID_Sub_Divisi_Saat_Cycle', 'p.Nama_Sub_Divisi_Saat_Cycle', 'gl.PA_Cycle_Curve_Group_Id as grup_id',
                'g.Nama_Grup', 'r.Status_Hasil', 'r.Published_Leader_At', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id',
                'q.Kode_Kuadran', 'r.Final_Score',
            ]);
    }

    /** @return array{0: array<int, string>, 1: array<int, int>} [sesi terbuka per peserta, jumlah sesi FINAL per peserta] */
    private function petaSesi(int $cycleId): array
    {
        $baris = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->whereIn('s.Status_Sesi', [...PaBalancingStatus::SESI_BISA_DIUBAH, PaBalancingStatus::FINAL])
            ->get(['bp.PA_Participant_Id', 's.Nama_Sesi', 's.Status_Sesi']);

        $terbuka = [];
        $jumlah = [];

        foreach ($baris as $b) {
            $pid = (int) $b->PA_Participant_Id;

            if ($b->Status_Sesi === PaBalancingStatus::FINAL) {
                $jumlah[$pid] = ($jumlah[$pid] ?? 0) + 1;
            } else {
                $terbuka[$pid] = (string) $b->Nama_Sesi;
            }
        }

        return [$terbuka, $jumlah];
    }

    private function alasan(object $r, ?string $sesiTerbuka): ?string
    {
        if ($r->Status_Hasil === 'TERBIT') {
            return 'Hasil sudah terbit ke karyawan';
        }

        if ($r->Status_Hasil !== 'DIHITUNG') {
            return 'Hasil belum DIHITUNG (status '.($r->Status_Hasil ?? 'tanpa hasil').')';
        }

        if ($r->Published_Leader_At !== null) {
            return 'Hasil sudah dirilis ke leader';
        }

        return $sesiTerbuka === null ? null : "Sedang dibahas di sesi \"{$sesiTerbuka}\"";
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus** — Run perintah Step 2 → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingKandidatService.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKandidatTest.php
git commit -m "feat(kpi-pa): kandidat peserta sesi Balancing beserta alasan"
```

---

### Task 6: Siklus hidup sesi

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSesiService.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSesiTest.php`

**Interfaces:**
- Consumes: `PaCycleGate::BALANCING`, `PaKurvaService::{metode, terkunci}`, `PaBalancingKandidatService::alasanTidakLayak`, `PaResultCalculationService::rincianKompetensi`, `PaTeksKaya::{bersihkan, dariEsaiHcLearn}`, `PaBalancingPenjaga`.
- Produces:
  - `buat(int $cycleId, array $data, ?int $olehUserId): array` — `$data = ['nama' => string, 'pic_user_id' => int, 'forum' => list<['kode_perusahaan','kode_karyawan','peran']>, 'peserta_ids' => list<int>]`; kembali `detail()`.
  - `simpanDraft(int $sesiId, int $versiData, array $peserta, ?string $catatanRapat, ?int $olehUserId): array` — `$peserta = list<['participant_id' => int, 'status_tinjau' => ?string, 'alasan' => ?string, 'kompetensi' => list<['competency_id','nilai_sesudah','alasan']>, 'kualitatif' => list<['competency_id','isi_sesudah']>]>`; kembali `detail()`.
  - `finalkan(int $sesiId, int $versiData, ?int $olehUserId): array`
  - `batalkan(int $sesiId, string $alasan, ?int $olehUserId): array`
  - `daftar(int $cycleId): list<array>` — `id, nama, status, pic_user_id, pic_nama, jumlah_peserta, jumlah_forum, created_at, final_at, hitung_ulang_at, nomor_hitung_ulang`
  - `detail(int $sesiId): array` — `id, cycle_id, nama, status, versi_data, catatan_rapat, pic_user_id, pic_nama, final_at, hitung_ulang_at, alasan_batal, forum[], peserta[]` dengan `peserta[] = participant_id, nama, kode_karyawan, level, divisi, sub_divisi, status_tinjau, alasan, sebelum{nilai_kpi, nilai_kompetensi, final, grade, kuadran_id}, kompetensi[]{competency_id, kode, nama, deskripsi, bobot, sebelum, sesudah, alasan}, kualitatif[]{competency_id, kode, nama, sebelum, sesudah}`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSesiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingSesiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private function layanan(): PaBalancingSesiService
    {
        return app(PaBalancingSesiService::class);
    }

    private function sesiBaru(array $p): array
    {
        $this->palsukanAturan($p['kategori_id']);
        $forum = DB::table('Karyawan')->where('Aktif', 'Y')->orderByDesc('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        return $this->layanan()->buat($p['cycle_id'], [
            'nama' => 'Balancing Staff–SPV', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'),
            'forum' => [['kode_perusahaan' => $forum->Kode_Perusahaan, 'kode_karyawan' => $forum->Kode_Karyawan, 'peran' => 'Dept Head']],
            'peserta_ids' => [$p['participant_id']],
        ], 1);
    }

    private function galat(callable $f, string $kode): void
    {
        try {
            $f();
            $this->fail("Harus ditolak dengan {$kode}.");
        } catch (PaBalancingException $e) {
            $this->assertSame($kode, $e->errorCode, $e->getMessage());
        }
    }

    public function test_buat_sesi_membekukan_before_dan_bobot(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);

        $this->assertSame('TERBUKA', $d['status']);
        $this->assertCount(1, $d['forum']);
        $orang = $d['peserta'][0];
        $this->assertSame('BELUM', $orang['status_tinjau']);
        $this->assertSame(77.5, $orang['sebelum']['nilai_kompetensi']);
        $k = collect($orang['kompetensi'])->keyBy('competency_id');
        $this->assertSame(70.0, $k[$p['kompetensi'][0]]['sebelum']);
        $this->assertSame(70.0, $k[$p['kompetensi'][0]]['sesudah']);
        $this->assertEqualsWithDelta(0.5, $k[$p['kompetensi'][0]]['bobot'], 1e-6);
        $this->assertNotEmpty($orang['kualitatif'], 'Satu baris per kompetensi kualitatif aktif.');
    }

    public function test_peserta_yang_sedang_dibahas_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiBaru($p);

        $this->galat(fn () => $this->sesiBaru($p), PaBalancingException::PESERTA_TIDAK_LAYAK);
    }

    public function test_simpan_menolak_luar_rentang_dan_tanpa_alasan(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $k1 = $p['kompetensi'][0];

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 101, 'alasan' => 'x']],
        ]], null, 1), PaBalancingException::NILAI_DI_LUAR_RENTANG);

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80, 'alasan' => '  ']],
        ]], null, 1), PaBalancingException::ALASAN_WAJIB);
    }

    public function test_simpan_menurunkan_status_tinjau_dan_menaikkan_versi(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $k1 = $p['kompetensi'][0];

        $d2 = $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'],
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80, 'alasan' => 'Konsisten memimpin proyek']],
            'kualitatif' => [['competency_id' => $d['peserta'][0]['kualitatif'][0]['competency_id'], 'isi_sesudah' => '<p onclick="x()">Rapi</p><script>1</script>']],
        ]], '<p>Rapat 1</p>', 1);

        $this->assertSame('DRAFT', $d2['status']);
        $this->assertSame($d['versi_data'] + 1, $d2['versi_data']);
        $this->assertSame('DIUBAH', $d2['peserta'][0]['status_tinjau']);
        $this->assertSame('<p>Rapi</p>', $d2['peserta'][0]['kualitatif'][0]['sesudah'], 'Teks kaya wajib disaring server.');

        // Dikembalikan ke Before → bukan DIUBAH lagi.
        $d3 = $this->layanan()->simpanDraft($d2['id'], $d2['versi_data'], [[
            'participant_id' => $p['participant_id'], 'status_tinjau' => 'TANPA_PERUBAHAN',
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 70, 'alasan' => null]],
            'kualitatif' => [['competency_id' => $d['peserta'][0]['kualitatif'][0]['competency_id'], 'isi_sesudah' => $d['peserta'][0]['kualitatif'][0]['sebelum']]],
        ]], null, 1);
        $this->assertSame('TANPA_PERUBAHAN', $d3['peserta'][0]['status_tinjau']);
    }

    public function test_versi_basi_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [], '<p>a</p>', 1);

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [], '<p>b</p>', 1), PaBalancingException::VERSI_BERUBAH);
    }

    public function test_finalkan_menuntut_semua_ditinjau_lalu_mengunci(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);

        $this->galat(fn () => $this->layanan()->finalkan($d['id'], $d['versi_data'], 1), PaBalancingException::PESERTA_BELUM_DITINJAU);

        $d2 = $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [['participant_id' => $p['participant_id'], 'status_tinjau' => 'TANPA_PERUBAHAN']], null, 1);
        $d3 = $this->layanan()->finalkan($d2['id'], $d2['versi_data'], 1);

        $this->assertSame('FINAL', $d3['status']);
        $this->galat(fn () => $this->layanan()->simpanDraft($d3['id'], $d3['versi_data'], [], null, 1), PaBalancingException::SESI_TERKUNCI);
    }

    public function test_batalkan_wajib_alasan_dan_membebaskan_peserta(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);

        $this->galat(fn () => $this->layanan()->batalkan($d['id'], 'no', 1), PaBalancingException::DATA_TIDAK_SAH);
        $this->assertSame('DIBATALKAN', $this->layanan()->batalkan($d['id'], 'Rapat ditunda', 1)['status']);
        $this->assertSame('TERBUKA', $this->sesiBaru($p)['status'], 'Peserta sesi batal boleh masuk sesi baru.');
    }

    public function test_siklus_belum_ditutup_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Status_Cycle' => 'OPEN']);

        $this->galat(fn () => $this->sesiBaru($p), PaBalancingException::SIKLUS_BELUM_DITUTUP);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSesiTest.php` → FAIL (kelas tidak ada).

- [ ] **Step 3: Tulis `PaBalancingSesiService.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaTeksKaya;
use Illuminate\Support\Facades\DB;

/**
 * Siklus hidup sesi Balancing: TERBUKA → DRAFT → FINAL, atau DIBATALKAN (spec §3).
 *
 * Isian sesi FINAL tidak pernah disunting; koreksi sesudahnya lewat sesi baru, sehingga
 * riwayat setiap putaran tetap utuh.
 */
class PaBalancingSesiService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingKandidatService $kandidat,
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaTeksKaya $teksKaya,
    ) {}

    public function buat(int $cycleId, array $data, ?int $olehUserId): array
    {
        $this->pastikanSiklusSiap($cycleId);

        $nama = trim((string) ($data['nama'] ?? ''));

        if ($nama === '' || mb_strlen($nama) > 200) {
            throw new PaBalancingException('Nama sesi wajib diisi, paling banyak 200 karakter.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $picId = (int) ($data['pic_user_id'] ?? 0);

        if (! DB::table('KPI_Users')->where('Id_Users', $picId)->exists()) {
            throw new PaBalancingException('PIC sesi tidak ditemukan di daftar pengguna.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $pesertaIds = array_values(array_unique(array_map('intval', (array) ($data['peserta_ids'] ?? []))));

        if ($pesertaIds === []) {
            throw new PaBalancingException('Pilih sekurangnya satu peserta.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $forum = $this->forumSah((array) ($data['forum'] ?? []));
        $this->pastikanLayak($cycleId, $pesertaIds);

        // Dibaca di luar transaksi: membaca penugasan tiap peserta, tidak menulis apa pun.
        $bobot = [];

        foreach ($pesertaIds as $pid) {
            $bobot[$pid] = $this->perhitungan->rincianKompetensi($pid)['bobot_kompetensi'] ?? [];
        }

        $sesiId = DB::transaction(function () use ($cycleId, $nama, $picId, $forum, $pesertaIds, $bobot, $olehUserId) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->first(['Id_PA_Cycle']);
            // Diperiksa ulang di dalam kunci: dua admin bisa membuat sesi untuk orang yang sama bersamaan.
            $this->pastikanLayak($cycleId, $pesertaIds);

            $sesiId = (int) DB::table('N_PA_Balancing_Sesi')->insertGetId([
                'PA_Cycle_Id' => $cycleId, 'Nama_Sesi' => $nama, 'PIC_User_Id' => $picId,
                'Status_Sesi' => PaBalancingStatus::TERBUKA, 'Versi_Data' => 1, 'Created_By' => $olehUserId,
            ], 'Id_PA_Balancing_Sesi');

            foreach ($forum as $i => $f) {
                DB::table('N_PA_Balancing_Forum')->insert([...$f, 'PA_Balancing_Sesi_Id' => $sesiId, 'Urutan_Tampil' => $i + 1, 'Created_By' => $olehUserId]);
            }

            foreach ($pesertaIds as $pid) {
                $this->potretPeserta($sesiId, $pid, $bobot[$pid], $olehUserId);
            }

            return $sesiId;
        });

        return $this->detail($sesiId);
    }

    public function simpanDraft(int $sesiId, int $versiData, array $peserta, ?string $catatanRapat, ?int $olehUserId): array
    {
        DB::transaction(function () use ($sesiId, $versiData, $peserta, $catatanRapat, $olehUserId) {
            $sesi = $this->kunciSesiTerbuka($sesiId, $versiData);
            $jam = FormatTanggalHelper::getCurrentTime();
            $anggota = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'bp.PA_Participant_Id')
                ->where('bp.PA_Balancing_Sesi_Id', $sesiId)
                ->get(['bp.*', 'p.Nama_Karyawan_Saat_Cycle as Nama'])->keyBy('PA_Participant_Id');
            $namaKompetensi = DB::table('N_PA_Competency')->pluck('Nama_Competency', 'Id_PA_Competency');

            foreach ($peserta as $p) {
                $bp = $anggota[(int) ($p['participant_id'] ?? 0)] ?? null;

                if ($bp === null) {
                    throw new PaBalancingException('Peserta #'.($p['participant_id'] ?? '?').' bukan anggota sesi ini.', PaBalancingException::DATA_TIDAK_SAH);
                }

                $this->simpanKompetensi($bp, (array) ($p['kompetensi'] ?? []), $namaKompetensi, $jam, $olehUserId);
                $this->simpanKualitatif($bp, (array) ($p['kualitatif'] ?? []), $jam, $olehUserId);

                $alasan = trim((string) ($p['alasan'] ?? ''));

                DB::table('N_PA_Balancing_Peserta')->where('Id_PA_Balancing_Peserta', $bp->Id_PA_Balancing_Peserta)->update([
                    'Status_Tinjau' => $this->statusTinjau($bp, $p['status_tinjau'] ?? null),
                    'Alasan' => $alasan === '' ? $bp->Alasan : mb_substr($alasan, 0, 1000),
                    'Updated_At' => $jam, 'Updated_By' => $olehUserId,
                ]);
            }

            DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->update([
                'Status_Sesi' => PaBalancingStatus::DRAFT,
                'Catatan_Rapat' => $catatanRapat === null ? $sesi->Catatan_Rapat : ($this->teksKaya->bersihkan($catatanRapat) ?: null),
                'Versi_Data' => (int) $sesi->Versi_Data + 1,
                'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        });

        return $this->detail($sesiId);
    }

    public function finalkan(int $sesiId, int $versiData, ?int $olehUserId): array
    {
        DB::transaction(function () use ($sesiId, $versiData, $olehUserId) {
            $sesi = $this->kunciSesiTerbuka($sesiId, $versiData);
            $belum = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'bp.PA_Participant_Id')
                ->where('bp.PA_Balancing_Sesi_Id', $sesiId)->where('bp.Status_Tinjau', PaBalancingStatus::BELUM)
                ->pluck('p.Nama_Karyawan_Saat_Cycle');

            if ($belum->isNotEmpty()) {
                throw new PaBalancingException(
                    'Masih ada peserta yang belum ditinjau: '.$belum->implode(', ').'. Tandai "tanpa perubahan" atau ubah nilainya.',
                    PaBalancingException::PESERTA_BELUM_DITINJAU,
                );
            }

            $jam = FormatTanggalHelper::getCurrentTime();
            DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->update([
                'Status_Sesi' => PaBalancingStatus::FINAL, 'Final_At' => $jam, 'Final_By' => $olehUserId,
                'Versi_Data' => (int) $sesi->Versi_Data + 1, 'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        });

        return $this->detail($sesiId);
    }

    public function batalkan(int $sesiId, string $alasan, ?int $olehUserId): array
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 500) {
            throw new PaBalancingException('Alasan membatalkan sesi wajib diisi, 5 sampai 500 karakter.', PaBalancingException::DATA_TIDAK_SAH);
        }

        DB::transaction(function () use ($sesiId, $alasan, $olehUserId) {
            $sesi = $this->kunciSesiTerbuka($sesiId, null);
            $jam = FormatTanggalHelper::getCurrentTime();
            DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->update([
                'Status_Sesi' => PaBalancingStatus::DIBATALKAN, 'Cancelled_At' => $jam, 'Cancelled_By' => $olehUserId,
                'Alasan_Batal' => $alasan, 'Versi_Data' => (int) $sesi->Versi_Data + 1, 'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        });

        return $this->detail($sesiId);
    }

    /** @return list<array<string, mixed>> */
    public function daftar(int $cycleId): array
    {
        return DB::table('N_PA_Balancing_Sesi as s')
            ->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 's.PIC_User_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->orderByDesc('s.Id_PA_Balancing_Sesi')
            ->get([
                's.Id_PA_Balancing_Sesi', 's.Nama_Sesi', 's.Status_Sesi', 's.PIC_User_Id', 'u.Nama as PIC_Nama',
                's.Created_At', 's.Final_At', 's.Hitung_Ulang_At', 's.Nomor_Hitung_Ulang',
                DB::raw('(SELECT COUNT(*) FROM N_PA_Balancing_Peserta bp WHERE bp.PA_Balancing_Sesi_Id = s.Id_PA_Balancing_Sesi) AS Jumlah_Peserta'),
                DB::raw('(SELECT COUNT(*) FROM N_PA_Balancing_Forum f WHERE f.PA_Balancing_Sesi_Id = s.Id_PA_Balancing_Sesi) AS Jumlah_Forum'),
            ])
            ->map(fn ($s) => [
                'id' => (int) $s->Id_PA_Balancing_Sesi, 'nama' => $s->Nama_Sesi, 'status' => $s->Status_Sesi,
                'pic_user_id' => (int) $s->PIC_User_Id, 'pic_nama' => $s->PIC_Nama,
                'jumlah_peserta' => (int) $s->Jumlah_Peserta, 'jumlah_forum' => (int) $s->Jumlah_Forum,
                'created_at' => $s->Created_At, 'final_at' => $s->Final_At, 'hitung_ulang_at' => $s->Hitung_Ulang_At,
                'nomor_hitung_ulang' => $s->Nomor_Hitung_Ulang === null ? null : (int) $s->Nomor_Hitung_Ulang,
            ])->all();
    }

    public function detail(int $sesiId): array
    {
        $s = DB::table('N_PA_Balancing_Sesi as s')->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 's.PIC_User_Id')
            ->where('s.Id_PA_Balancing_Sesi', $sesiId)->first(['s.*', 'u.Nama as PIC_Nama']);

        if ($s === null) {
            throw new PaBalancingException("Sesi Balancing {$sesiId} tidak ditemukan.", PaBalancingException::SESI_TIDAK_DITEMUKAN, 404);
        }

        $bp = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'bp.PA_Participant_Id')
            ->where('bp.PA_Balancing_Sesi_Id', $sesiId)->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get(['bp.*', 'p.Nama_Karyawan_Saat_Cycle', 'p.Kode_Karyawan', 'p.Nama_Level_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Sub_Divisi_Saat_Cycle']);
        $ids = $bp->pluck('Id_PA_Balancing_Peserta')->all();

        $komp = DB::table('N_PA_Balancing_Kompetensi as k')->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'k.PA_Competency_Id')
            ->whereIn('k.PA_Balancing_Peserta_Id', $ids)->orderBy('c.Urutan_Tampil')->orderBy('c.Id_PA_Competency')
            ->get(['k.*', 'c.Kode_Competency', 'c.Nama_Competency', 'c.Deskripsi'])->groupBy('PA_Balancing_Peserta_Id');
        $kual = DB::table('N_PA_Balancing_Kualitatif as k')->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'k.PA_Competency_Id')
            ->whereIn('k.PA_Balancing_Peserta_Id', $ids)->orderBy('c.Urutan_Tampil')->orderBy('c.Id_PA_Competency')
            ->get(['k.*', 'c.Kode_Competency', 'c.Nama_Competency'])->groupBy('PA_Balancing_Peserta_Id');

        $angka = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id' => (int) $s->Id_PA_Balancing_Sesi, 'cycle_id' => (int) $s->PA_Cycle_Id, 'nama' => $s->Nama_Sesi,
            'status' => $s->Status_Sesi, 'versi_data' => (int) $s->Versi_Data, 'catatan_rapat' => $s->Catatan_Rapat,
            'pic_user_id' => (int) $s->PIC_User_Id, 'pic_nama' => $s->PIC_Nama, 'final_at' => $s->Final_At,
            'hitung_ulang_at' => $s->Hitung_Ulang_At, 'alasan_batal' => $s->Alasan_Batal,
            'forum' => DB::table('N_PA_Balancing_Forum')->where('PA_Balancing_Sesi_Id', $sesiId)->orderBy('Urutan_Tampil')
                ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan', 'Peran'])
                ->map(fn ($f) => ['kode_perusahaan' => $f->Kode_Perusahaan, 'kode_karyawan' => $f->Kode_Karyawan, 'nama' => $f->Nama_Karyawan, 'peran' => $f->Peran])->all(),
            'peserta' => $bp->map(fn ($b) => [
                'participant_id' => (int) $b->PA_Participant_Id, 'nama' => $b->Nama_Karyawan_Saat_Cycle,
                'kode_karyawan' => $b->Kode_Karyawan, 'level' => $b->Nama_Level_Saat_Cycle,
                'divisi' => $b->Nama_Divisi_Saat_Cycle, 'sub_divisi' => $b->Nama_Sub_Divisi_Saat_Cycle,
                'status_tinjau' => $b->Status_Tinjau, 'alasan' => $b->Alasan,
                'sebelum' => [
                    'nilai_kpi' => $angka($b->Nilai_KPI_Sebelum), 'nilai_kompetensi' => $angka($b->Nilai_Kompetensi_Sebelum),
                    'final' => $angka($b->Final_Score_Sebelum), 'grade' => $b->Kode_Grade_Sebelum,
                    'kuadran_id' => $b->PA_Hav_Quadrant_Sebelum_Id === null ? null : (int) $b->PA_Hav_Quadrant_Sebelum_Id,
                ],
                'kompetensi' => collect($komp->get($b->Id_PA_Balancing_Peserta, []))->map(fn ($k) => [
                    'competency_id' => (int) $k->PA_Competency_Id, 'kode' => $k->Kode_Competency, 'nama' => $k->Nama_Competency,
                    'deskripsi' => $k->Deskripsi, 'bobot' => (float) $k->Bobot, 'sebelum' => (float) $k->Nilai_Sebelum,
                    'sesudah' => (float) $k->Nilai_Sesudah, 'alasan' => $k->Alasan,
                ])->values()->all(),
                'kualitatif' => collect($kual->get($b->Id_PA_Balancing_Peserta, []))->map(fn ($k) => [
                    'competency_id' => (int) $k->PA_Competency_Id, 'kode' => $k->Kode_Competency, 'nama' => $k->Nama_Competency,
                    'sebelum' => $k->Isi_Sebelum, 'sesudah' => $k->Isi_Sesudah,
                ])->values()->all(),
            ])->all(),
        ];
    }

    private function pastikanSiklusSiap(int $cycleId): void
    {
        if (! $this->gate->boleh(PaCycleGate::BALANCING, $cycleId)) {
            throw new PaBalancingException('Balancing hanya bisa dijalankan sesudah pengisian siklus ditutup (CLOSED).', PaBalancingException::SIKLUS_BELUM_DITUTUP);
        }

        if ($this->kurva->metode($cycleId) === 'KURVA' && ! $this->kurva->terkunci($cycleId)) {
            throw new PaBalancingException('Kunci kurva grade siklus ini lebih dulu; Before di sesi harus memakai grade resmi.', PaBalancingException::KURVA_BELUM_DIKUNCI);
        }
    }

    private function pastikanLayak(int $cycleId, array $pesertaIds): void
    {
        $tolak = $this->kandidat->alasanTidakLayak($cycleId, $pesertaIds);

        if ($tolak !== []) {
            throw new PaBalancingException(
                'Peserta berikut tidak bisa masuk sesi: '.implode('; ', array_map(fn ($t) => "{$t['nama']} ({$t['alasan']})", $tolak)).'.',
                PaBalancingException::PESERTA_TIDAK_LAYAK,
            );
        }
    }

    /** @return list<array{Kode_Perusahaan:string, Kode_Karyawan:string, Nama_Karyawan:string, Peran:string}> */
    private function forumSah(array $forum): array
    {
        $hasil = [];

        foreach ($forum as $f) {
            $peran = trim((string) ($f['peran'] ?? ''));
            $k = DB::table('Karyawan')->where('Kode_Perusahaan', (string) ($f['kode_perusahaan'] ?? ''))
                ->where('Kode_Karyawan', (string) ($f['kode_karyawan'] ?? ''))->where('Aktif', 'Y')
                ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

            if ($k === null || $peran === '' || mb_strlen($peran) > 100) {
                throw new PaBalancingException('Anggota forum harus karyawan aktif dengan peran (paling banyak 100 karakter).', PaBalancingException::DATA_TIDAK_SAH);
            }

            $hasil[] = ['Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan, 'Nama_Karyawan' => $k->Nama, 'Peran' => $peran];
        }

        return $hasil;
    }

    /** @param array<int, float> $bobot */
    private function potretPeserta(int $sesiId, int $pid, array $bobot, ?int $olehUserId): void
    {
        $hasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->first();

        $bpId = (int) DB::table('N_PA_Balancing_Peserta')->insertGetId([
            'PA_Balancing_Sesi_Id' => $sesiId, 'PA_Participant_Id' => $pid, 'PA_Result_Id' => $hasil->Id_PA_Result,
            'Calculation_Version_Sebelum' => (int) $hasil->Calculation_Version, 'Nilai_KPI_Sebelum' => $hasil->Nilai_KPI,
            'Nilai_Kompetensi_Sebelum' => $hasil->Nilai_Kompetensi, 'Final_Score_Sebelum' => $hasil->Final_Score,
            'Kode_Grade_Sebelum' => $hasil->Kode_Grade_Final, 'PA_Hav_Quadrant_Sebelum_Id' => $hasil->PA_Hav_Quadrant_Id,
            'Status_Tinjau' => PaBalancingStatus::BELUM, 'Created_By' => $olehUserId,
        ], 'Id_PA_Balancing_Peserta');

        // Before = nilai radar yang berlaku (agregat aktif), sudah memuat After sesi sebelumnya.
        $radar = DB::table('N_PA_Result_Competency')->where('PA_Result_Id', $hasil->Id_PA_Result)
            ->where('Flag_Aktif', 'Y')->whereNull('PA_Rater_Category_Id')->whereNotNull('Nilai')
            ->get(['PA_Competency_Id', 'Nilai']);

        foreach ($radar as $r) {
            DB::table('N_PA_Balancing_Kompetensi')->insert([
                'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => (int) $r->PA_Competency_Id,
                'Bobot' => round($bobot[(int) $r->PA_Competency_Id] ?? 0.0, 6),
                'Nilai_Sebelum' => $r->Nilai, 'Nilai_Sesudah' => $r->Nilai,
            ]);
        }

        $kualitatif = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')->where('t.Kode_Type', 'KUALITATIF')->pluck('k.Id_PA_Competency');
        // Before kualitatif = catatan ADMIN yang aktif (T3). Kosong bila admin belum menulis.
        $catatan = DB::table('N_PA_Result_Note')->where('PA_Result_Id', $hasil->Id_PA_Result)->where('Flag_Aktif', 'Y')
            ->whereNull('PA_Assignment_Id')->orderBy('Urutan_Tampil')->get(['PA_Competency_Id', 'Isi'])->groupBy('PA_Competency_Id');

        foreach ($kualitatif as $cid) {
            $isi = collect($catatan->get($cid, []))->map(fn ($n) => $this->teksKaya->dariEsaiHcLearn((string) $n->Isi))->implode('');
            DB::table('N_PA_Balancing_Kualitatif')->insert([
                'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => (int) $cid,
                'Isi_Sebelum' => $isi === '' ? null : $isi, 'Isi_Sesudah' => $isi === '' ? null : $isi,
            ]);
        }
    }

    private function kunciSesiTerbuka(int $sesiId, ?int $versiData): object
    {
        $sesi = DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->lockForUpdate()->first();

        if ($sesi === null) {
            throw new PaBalancingException("Sesi Balancing {$sesiId} tidak ditemukan.", PaBalancingException::SESI_TIDAK_DITEMUKAN, 404);
        }

        if (! in_array($sesi->Status_Sesi, PaBalancingStatus::SESI_BISA_DIUBAH, true)) {
            throw new PaBalancingException("Sesi \"{$sesi->Nama_Sesi}\" sudah {$sesi->Status_Sesi}; isiannya tidak bisa diubah. Buat sesi baru untuk koreksi.", PaBalancingException::SESI_TERKUNCI);
        }

        if ($versiData !== null && (int) $sesi->Versi_Data !== $versiData) {
            throw new PaBalancingException('Data sesi sudah diubah orang lain. Muat ulang lalu ulangi perubahan Anda.', PaBalancingException::VERSI_BERUBAH, 409);
        }

        return $sesi;
    }

    private function simpanKompetensi(object $bp, array $kompetensi, $namaKompetensi, string $jam, ?int $olehUserId): void
    {
        $lama = DB::table('N_PA_Balancing_Kompetensi')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)
            ->get()->keyBy('PA_Competency_Id');

        foreach ($kompetensi as $k) {
            $cid = (int) ($k['competency_id'] ?? 0);
            $baris = $lama[$cid] ?? null;
            $label = "{$bp->Nama} — ".($namaKompetensi[$cid] ?? "kompetensi #{$cid}");

            if ($baris === null) {
                throw new PaBalancingException("{$label} tidak ada di sesi ini.", PaBalancingException::DATA_TIDAK_SAH);
            }

            $nilai = (float) ($k['nilai_sesudah'] ?? -1);

            if ($nilai < 0 || $nilai > 100) {
                throw new PaBalancingException("{$label}: nilai After harus 0 sampai 100.", PaBalancingException::NILAI_DI_LUAR_RENTANG);
            }

            $alasan = trim((string) ($k['alasan'] ?? ''));

            if (PaBalancingKalkulator::berubah((float) $baris->Nilai_Sebelum, $nilai) && $alasan === '') {
                throw new PaBalancingException("{$label}: alasan wajib diisi karena nilainya diubah.", PaBalancingException::ALASAN_WAJIB);
            }

            DB::table('N_PA_Balancing_Kompetensi')->where('Id_PA_Balancing_Kompetensi', $baris->Id_PA_Balancing_Kompetensi)->update([
                'Nilai_Sesudah' => round($nilai, 2), 'Alasan' => $alasan === '' ? null : mb_substr($alasan, 0, 1000),
                'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        }
    }

    private function simpanKualitatif(object $bp, array $kualitatif, string $jam, ?int $olehUserId): void
    {
        foreach ($kualitatif as $q) {
            $n = DB::table('N_PA_Balancing_Kualitatif')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)
                ->where('PA_Competency_Id', (int) ($q['competency_id'] ?? 0))
                ->update(['Isi_Sesudah' => $this->teksKaya->bersihkan((string) ($q['isi_sesudah'] ?? '')) ?: null, 'Updated_At' => $jam, 'Updated_By' => $olehUserId]);

            if ($n === 0) {
                throw new PaBalancingException("Kategori kualitatif #{$q['competency_id']} tidak ada di sesi ini.", PaBalancingException::DATA_TIDAK_SAH);
            }
        }
    }

    /** DIUBAH diturunkan dari isian; admin hanya bisa memilih BELUM atau TANPA_PERUBAHAN. */
    private function statusTinjau(object $bp, ?string $diminta): string
    {
        $adaUbah = DB::table('N_PA_Balancing_Kompetensi')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)->get(['Nilai_Sebelum', 'Nilai_Sesudah'])
            ->contains(fn ($k) => PaBalancingKalkulator::berubah((float) $k->Nilai_Sebelum, (float) $k->Nilai_Sesudah))
            || DB::table('N_PA_Balancing_Kualitatif')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)->get(['Isi_Sebelum', 'Isi_Sesudah'])
                ->contains(fn ($k) => (string) $k->Isi_Sebelum !== (string) $k->Isi_Sesudah);

        if ($adaUbah) {
            return PaBalancingStatus::DIUBAH;
        }

        if (in_array($diminta, [PaBalancingStatus::BELUM, PaBalancingStatus::TANPA_PERUBAHAN], true)) {
            return $diminta;
        }

        return $bp->Status_Tinjau === PaBalancingStatus::DIUBAH ? PaBalancingStatus::BELUM : $bp->Status_Tinjau;
    }
}
```

- [ ] **Step 4: Jalankan uji sesi dan uji satu pintu**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/`
Expected: PASS. Bila `test_simpan_menurunkan_status_tinjau…` gagal pada perbandingan `<p>Rapi</p>`, periksa keluaran `PaTeksKaya::bersihkan` untuk masukan itu dan sesuaikan harapan uji dengan keluaran aslinya (penyaring sudah diuji terpisah; yang dijaga di sini hanya bahwa atribut dan `<script>` hilang).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSesiService.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSesiTest.php
git commit -m "feat(kpi-pa): siklus hidup sesi Balancing"
```

---

### Task 7: Simulasi grade kurva

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKurvaSimulasiTest.php`

**Interfaces:**
- Produces: `PaKurvaService::simulasikanGrade(int $cycleId, array $finalPengganti): array{grade: array<int,?string>, grup: array<int,int>, pita: array<int, list<array>>}`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

class BalancingKurvaSimulasiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    public function test_simulasi_sama_dengan_kunci_ulang_sungguhan(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [50, 55, 60, 65, 70, 75, 80]]);
        $kurva = app(PaKurvaService::class);
        $kurva->kunci($s['cycle_id'], 7);
        [$p0, , , , , , $p6] = $s['peserta'][$level];

        $sim = $kurva->simulasikanGrade($s['cycle_id'], [$p0 => 72.0, $p6 => 95.0]);

        // Jalur sungguhan: buka → tulis Final baru → kunci.
        $kurva->buka($s['cycle_id'], 'Uji simulasi', 7);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $p0)->update(['Final_Score' => 72.0]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $p6)->update(['Final_Score' => 95.0]);
        $kurva->kunci($s['cycle_id'], 7);

        foreach ($s['peserta'][$level] as $pid) {
            $this->assertSame(
                DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->value('Kode_Grade_Sistem'),
                $sim['grade'][$pid],
                "Grade simulasi peserta {$pid} berbeda dari hasil kunci ulang.",
            );
        }
    }

    public function test_simulasi_tidak_menulis(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [50, 60, 70, 80, 90]]);
        $kurva = app(PaKurvaService::class);
        $kurva->kunci($s['cycle_id'], 7);
        $sebelum = DB::table('N_PA_Result')->whereIn('PA_Participant_Id', $s['peserta'][$level])->orderBy('Id_PA_Result')->get();

        $kurva->simulasikanGrade($s['cycle_id'], [$s['peserta'][$level][0] => 99.0]);

        $this->assertEquals($sebelum, DB::table('N_PA_Result')->whereIn('PA_Participant_Id', $s['peserta'][$level])->orderBy('Id_PA_Result')->get());
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->count());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKurvaSimulasiTest.php` → FAIL (`simulasikanGrade` tidak ada).

- [ ] **Step 3: Tambahkan metode di `PaKurvaService`** (sesudah `gradeSementara()`)

```php
    /**
     * Grade SELURUH hasil siklus seandainya Final sebagian orang diganti — dibaca, tidak disimpan.
     *
     * Jalurnya sama dengan `kunci()`: hasilDihitung → statBeku → PaKurvaStatistik::pita →
     * gradeUntuk. Karena itu simulasi Balancing di rapat sama dengan grade sesudah hitung ulang.
     *
     * @param  array<int, float>  $finalPengganti  Id_PA_Participant => Final baru
     * @return array{grade: array<int, ?string>, grup: array<int, int>, pita: array<int, list<array>>}
     */
    public function simulasikanGrade(int $cycleId, array $finalPengganti): array
    {
        $gradeDef = $this->gradeSiklus($cycleId);

        if ($gradeDef === []) {
            throw new RuntimeException('Batas z grade belum diatur di salinan siklus ini.');
        }

        $hasil = $this->hasilDihitung($cycleId)->map(function ($r) use ($finalPengganti) {
            if (array_key_exists((int) $r->pid, $finalPengganti)) {
                $r->Final_Score = $finalPengganti[(int) $r->pid];
            }

            return $r;
        });

        $grade = [];
        $grup = [];
        $pita = [];

        foreach ($hasil->whereNotNull('grup_id')->groupBy('grup_id') as $grupId => $anggota) {
            $stat = $this->statBeku($anggota->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all());
            $pita[(int) $grupId] = PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);

            foreach ($anggota as $r) {
                $grade[(int) $r->pid] = PaKurvaStatistik::gradeUntuk($pita[(int) $grupId], (float) $r->Final_Score);
                $grup[(int) $r->pid] = (int) $grupId;
            }
        }

        return ['grade' => $grade, 'grup' => $grup, 'pita' => $pita];
    }
```

- [ ] **Step 4: Jalankan uji baru dan uji kurva lama** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKurvaSimulasiTest.php tests/Feature/KPI/PerformanceAppraisal/KurvaKunciTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingKurvaSimulasiTest.php
git commit -m "feat(kpi-pa): simulasi grade kurva tanpa menulis"
```

---

### Task 8: Simulasi sesi

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSimulasiService.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSimulasiTest.php`

**Interfaces:**
- Consumes: `PaResultCalculationService::simulasiHasil`, `PaKurvaService::{metode, simulasikanGrade}`, `PaKurvaStatistik::gradeUntuk`, `PaBalancingPotretHasil::siklus`, `PaBalancingKalkulator::{sebab, berubah}`.
- Produces: `PaBalancingSimulasiService::simulasikan(int $sesiId, array $draft): array{metode:string, peserta:list<Baris>, terdampak:list<Baris>}` dengan `Baris = array{participant_id:int, nama:string, sebelum:array{nilai_kompetensi:?float, final:?float, grade:?string, kuadran_id:?int, kode_kuadran:?string}, sesudah:(sama), sebab:?string}`; `$draft = list<['participant_id' => int, 'kompetensi' => list<['competency_id' => int, 'nilai_sesudah' => float]>]>`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSimulasiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingSimulasiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_simulasi_pita_memakai_isian_layar_dan_tidak_menulis(): void
    {
        $p = $this->pesertaDihitung(['kpi' => 90.0]);
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 70.0, $k2 => 85.0]], 'DRAFT');
        $jumlahHasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $p['participant_id'])->get();

        $r = app(PaBalancingSimulasiService::class)->simulasikan($sesi, [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 90.0]],
        ]]);

        $orang = $r['peserta'][0];
        $this->assertSame('PITA', $r['metode']);
        $this->assertSame(83.75, $orang['sebelum']['final']);
        $this->assertSame(87.5, $orang['sesudah']['nilai_kompetensi']);   // 77,5 + 0,5 × 20
        $this->assertSame(88.75, $orang['sesudah']['final']);             // 90×0,5 + 87,5×0,5
        $this->assertSame('BALANCING', $orang['sebab']);
        $this->assertEquals($jumlahHasil, DB::table('N_PA_Result')->where('PA_Participant_Id', $p['participant_id'])->get());
    }

    public function test_isian_di_luar_rentang_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 70.0]], 'DRAFT');

        $this->expectException(PaBalancingException::class);
        app(PaBalancingSimulasiService::class)->simulasikan($sesi, [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $p['kompetensi'][0], 'nilai_sesudah' => 120]],
        ]]);
    }
}
```

Catatan fixture: siklus `lembarTerisi` tidak punya pita salinan, jadi `grade_pita` bernilai `null` — uji ini sengaja tidak menegaskan grade PITA. Kesetaraan grade KURVA dijaga Task 7; kesetaraan Final dijaga Task 4 (fungsi `nilaiAkhir` yang sama).

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSimulasiTest.php` → FAIL.

- [ ] **Step 3: Tulis `PaBalancingSimulasiService.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Support\Facades\DB;

/**
 * Simulasi hitung ulang selama sesi berlangsung (spec §6.4, B11). TIDAK menulis apa pun.
 *
 * Titik awal = After yang tersimpan di sesi ini, ditimpa isian layar yang belum disimpan.
 * Peserta sesi lain yang sudah FINAL tetapi belum dihitung ulang ikut dengan nilai berlakunya,
 * supaya gambaran kurva lengkap.
 */
class PaBalancingSimulasiService
{
    public function __construct(
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingPenjaga $penjaga,
        private readonly PaBalancingPotretHasil $potret,
    ) {}

    public function simulasikan(int $sesiId, array $draft): array
    {
        $sesi = DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->first();

        if ($sesi === null || $sesi->Status_Sesi === PaBalancingStatus::DIBATALKAN) {
            throw new PaBalancingException("Sesi Balancing {$sesiId} tidak ditemukan atau sudah dibatalkan.", PaBalancingException::SESI_TIDAK_DITEMUKAN, 404);
        }

        $cycleId = (int) $sesi->PA_Cycle_Id;
        $isian = $this->isianAwal($sesiId);

        foreach ($draft as $d) {
            $pid = (int) ($d['participant_id'] ?? 0);

            if (! isset($isian[$pid])) {
                throw new PaBalancingException("Peserta #{$pid} bukan anggota sesi ini.", PaBalancingException::DATA_TIDAK_SAH);
            }

            foreach ((array) ($d['kompetensi'] ?? []) as $k) {
                $nilai = (float) ($k['nilai_sesudah'] ?? -1);

                if ($nilai < 0 || $nilai > 100) {
                    throw new PaBalancingException('Nilai After harus 0 sampai 100.', PaBalancingException::NILAI_DI_LUAR_RENTANG);
                }

                $isian[$pid][(int) $k['competency_id']] = $nilai;
            }
        }

        $lain = DB::table('N_PA_Balancing_Peserta')
            ->whereIn('PA_Balancing_Sesi_Id', $this->penjaga->sesiFinalBelumDihitung($cycleId)->pluck('Id_PA_Balancing_Sesi'))
            ->whereNotIn('PA_Participant_Id', array_keys($isian))
            ->distinct()->pluck('PA_Participant_Id')->map(fn ($x) => (int) $x)->all();

        $baru = [];

        foreach ($isian as $pid => $after) {
            $baru[$pid] = $this->perhitungan->simulasiHasil($pid, $after);
        }

        foreach ($lain as $pid) {
            $baru[$pid] = $this->perhitungan->simulasiHasil($pid);
        }

        $sebelum = $this->potret->siklus($cycleId);
        $metode = $this->kurva->metode($cycleId);
        $finalBaru = array_filter(array_map(fn ($b) => $b['final'], $baru), fn ($v) => $v !== null);
        $sim = $metode === 'KURVA' ? $this->kurva->simulasikanGrade($cycleId, $finalBaru) : null;

        $peserta = [];
        $terdampak = [];

        foreach ($sebelum as $pid => $s) {
            $b = $baru[$pid] ?? null;
            $finalSesudah = $b['final'] ?? $s->Final_Score;
            $gradeSesudah = $sim !== null ? ($sim['grade'][$pid] ?? $s->Kode_Grade_Final) : ($b['grade_pita'] ?? $s->Kode_Grade_Final);
            $gradeTengah = ($sim !== null && isset($sim['grup'][$pid]) && $s->Final_Score !== null)
                ? PaKurvaStatistik::gradeUntuk($sim['pita'][$sim['grup'][$pid]], $s->Final_Score)
                : $s->Kode_Grade_Final;

            $baris = [
                'participant_id' => $pid,
                'nama' => $s->Nama,
                'sebelum' => [
                    'nilai_kompetensi' => $s->Nilai_Kompetensi, 'final' => $s->Final_Score, 'grade' => $s->Kode_Grade_Final,
                    'kuadran_id' => $s->PA_Hav_Quadrant_Id, 'kode_kuadran' => $s->Kode_Kuadran,
                ],
                'sesudah' => [
                    'nilai_kompetensi' => $b['nilai_kompetensi'] ?? $s->Nilai_Kompetensi, 'final' => $finalSesudah, 'grade' => $gradeSesudah,
                    'kuadran_id' => $b === null ? $s->PA_Hav_Quadrant_Id : $b['kuadran_id'],
                    'kode_kuadran' => $b === null ? $s->Kode_Kuadran : $b['kode_kuadran'],
                ],
                'sebab' => PaBalancingKalkulator::sebab($s->Final_Score, $finalSesudah, $s->Kode_Grade_Final, $gradeTengah, $gradeSesudah),
            ];

            if (isset($isian[$pid])) {
                $peserta[] = $baris;
            } elseif ($baris['sebab'] !== null) {
                $terdampak[] = $baris;
            }
        }

        return ['metode' => $metode, 'peserta' => $peserta, 'terdampak' => $terdampak];
    }

    /** @return array<int, array<int, float>> participant_id => [competency_id => After tersimpan] */
    private function isianAwal(int $sesiId): array
    {
        $isian = [];

        DB::table('N_PA_Balancing_Peserta as bp')
            ->leftJoin('N_PA_Balancing_Kompetensi as k', 'k.PA_Balancing_Peserta_Id', '=', 'bp.Id_PA_Balancing_Peserta')
            ->where('bp.PA_Balancing_Sesi_Id', $sesiId)
            ->get(['bp.PA_Participant_Id', 'k.PA_Competency_Id', 'k.Nilai_Sesudah'])
            ->each(function ($r) use (&$isian) {
                $isian[(int) $r->PA_Participant_Id] ??= [];

                if ($r->PA_Competency_Id !== null) {
                    $isian[(int) $r->PA_Participant_Id][(int) $r->PA_Competency_Id] = (float) $r->Nilai_Sesudah;
                }
            });

        return $isian;
    }
}
```

- [ ] **Step 4: Jalankan semua uji Balancing** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSimulasiService.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingSimulasiTest.php
git commit -m "feat(kpi-pa): simulasi sesi Balancing tanpa menulis"
```

---

### Task 9: Hitung ulang Balancing

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingHitungUlangService.php`
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingHitungUlangTest.php`

**Interfaces:**
- Consumes: `PaCycleGate::BALANCING`, `PaBalancingPenjaga`, `PaBalancingPotretHasil`, `PaBalancingNilaiBerlaku::kualitatifBerlaku`, `PaResultCalculationService::hitung`, `PaKurvaService::{metode, terkunci, buka, kunci, gradeBeku}`, `PaBalancingKalkulator::sebab`.
- Produces: `PaBalancingHitungUlangService::jalankan(int $cycleId, ?int $olehUserId): array{nomor:int, sesi:list<int>, dihitung:int, kalibrasi_dilepas:int, berubah:int}`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingHitungUlangService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/**
 * Orkestrasi hitung ulang. `hitung()` dipalsukan: matematikanya dijaga Task 4, sedangkan yang
 * diuji di sini urutan buka → hitung → kunci, jejak, dan transaksinya.
 */
class BalancingHitungUlangTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    private array $kompetensi = [];

    /** User nyata: riwayat kalibrasi dan kolom *_By bisa ber-FK ke KPI_Users. */
    private function userUji(): int
    {
        return (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users');
    }

    /** Siklus KURVA 7 orang, tiap orang punya dua kompetensi radar 70/85 dan KPI 90; kurva dikunci. */
    private function siklusSiap(): array
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70, 72, 74, 76, 78, 80, 82]]);
        $this->kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')->limit(2)->pluck('Id_PA_Competency')->map(fn ($x) => (int) $x)->all();

        foreach ($s['peserta'][$level] as $pid) {
            $r = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->first();
            DB::table('N_PA_Result')->where('Id_PA_Result', $r->Id_PA_Result)->update(['Nilai_KPI' => 90, 'Nilai_Kompetensi' => 77.5]);

            foreach ([70.0, 85.0] as $i => $nilai) {
                DB::table('N_PA_Result_Competency')->insert([
                    'PA_Result_Id' => $r->Id_PA_Result, 'PA_Competency_Id' => $this->kompetensi[$i], 'PA_Rater_Category_Id' => null,
                    'Nilai' => $nilai, 'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
                ]);
            }
        }

        app(PaKurvaService::class)->kunci($s['cycle_id'], 7);

        return [$s['cycle_id'], $s['peserta'][$level]];
    }

    /** hitung() tiruan: Nilai Kompetensi = rata-rata nilai berlaku (bobot 0,5/0,5), Final 50/50. */
    private function palsukanHitung(?int $pidTertahan = null): void
    {
        $this->partialMock(PaResultCalculationService::class, function ($m) use ($pidTertahan) {
            $m->shouldReceive('hitung')->andReturnUsing(function (int $pid) use ($pidTertahan) {
                if ($pid === $pidTertahan) {
                    return ['status' => 'TERTAHAN', 'alasan_tertahan' => 'KPI belum disetujui'];
                }

                $berlaku = app(PaBalancingNilaiBerlaku::class)->kompetensiBerlaku($pid);
                $komp = round(array_sum($berlaku) / count($berlaku), 2);
                $final = PaBalancingKalkulator::nilaiAkhir(90.0, $komp, 50.0, 50.0);
                DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->update([
                    'Nilai_Kompetensi' => $komp, 'Final_Score' => $final, 'Status_Hasil' => 'DIHITUNG',
                    'Kode_Grade_Sistem' => null, 'Kode_Grade_Final' => null, 'PA_Cycle_Curve_Id' => null,
                ]);

                return ['status' => 'DIHITUNG', 'alasan_tertahan' => null, 'final_score' => $final];
            });
        });
    }

    public function test_hitung_ulang_menulis_jejak_dan_mengunci_kurva_baru(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        [$k1, $k2] = $this->kompetensi;
        $sesi = $this->sesiLangsung($cycleId, [$pids[0] => [$k1 => 100.0, $k2 => 100.0]]);
        $this->palsukanHitung();

        $r = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $this->assertSame(1, $r['nomor']);
        $this->assertSame([$sesi], $r['sesi']);
        $this->assertSame(95.0, (float) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[0])->value('Final_Score'));
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->count());
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'T')->count(), 'Kurva lama tetap tersimpan nonaktif.');
        $jejak = DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Cycle_Id', $cycleId)->where('PA_Participant_Id', $pids[0])->first();
        $this->assertSame($sesi, (int) $jejak->PA_Balancing_Sesi_Id);
        $this->assertContains($jejak->Sebab, ['BALANCING', 'KEDUANYA', 'KURVA']);
        $this->assertNotNull(DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->value('Hitung_Ulang_At'));
    }

    public function test_hitung_ulang_ditolak_selama_ada_sesi_terbuka(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        $this->sesiLangsung($cycleId, [$pids[1] => [$this->kompetensi[0] => 90.0]], 'DRAFT');

        try {
            app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());
            $this->fail('Hitung ulang harus menunggu semua sesi final atau batal.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::SESI_MASIH_TERBUKA, $e->errorCode);
        }
    }

    public function test_hitung_ulang_ditolak_bila_ada_yang_sudah_dirilis_ke_leader(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[3])->update(['Published_Leader_At' => now()]);

        try {
            app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());
            $this->fail('Kurva tidak boleh dikunci ulang selagi ada hasil yang sudah dibaca leader.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::SUDAH_ADA_YANG_TERBIT, $e->errorCode);
        }
    }

    public function test_satu_peserta_tertahan_membatalkan_seluruh_hitung_ulang(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0], $pids[1] => [$this->kompetensi[0] => 90.0]]);
        $kurvaSebelum = DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->get();
        $this->palsukanHitung($pids[1]);

        try {
            app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());
            $this->fail('Peserta yang menjadi TERTAHAN harus membatalkan hitung ulang.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::HITUNG_ULANG_TERTAHAN, $e->errorCode);
        }

        $this->assertEquals($kurvaSebelum, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->get(), 'Kurva harus kembali seperti semula.');
        $this->assertSame(0, DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Cycle_Id', $cycleId)->count());
    }

    public function test_kalibrasi_forum_lama_dilepas_dan_tercatat(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[5])->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'A']);
        $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[5])->value('Id_PA_Result');
        $this->palsukanHitung();

        $r = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $this->assertSame(1, $r['kalibrasi_dilepas']);
        $this->assertSame('T', DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->value('Flag_Dikalibrasi'));
        $this->assertStringContainsString('Balancing', (string) DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->orderByDesc('Id_PA_Calibration_History')->value('Alasan'));
    }

    public function test_kualitatif_after_menjadi_catatan_admin(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $ql = (int) DB::table('N_PA_Competency as k')->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('t.Kode_Type', 'KUALITATIF')->where('k.Flag_Aktif', 'Y')->orderBy('k.Id_PA_Competency')->value('k.Id_PA_Competency');
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]], 'FINAL', [$pids[0] => [$ql => '<p>Teliti</p>']]);
        $this->palsukanHitung();

        app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[0])->value('Id_PA_Result');
        $this->assertSame(['<p>Teliti</p>'], DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)
            ->where('PA_Competency_Id', $ql)->whereNull('PA_Assignment_Id')->where('Flag_Aktif', 'Y')->pluck('Isi')->all());
    }
}
```

Periksa nama PK riwayat kalibrasi sebelum menjalankan: `SELECT name FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Calibration_History') AND column_id = 1`. Bila bukan `Id_PA_Calibration_History`, ganti di uji terakhir.

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingHitungUlangTest.php` → FAIL (kelas tidak ada).

- [ ] **Step 3: Tulis `PaBalancingHitungUlangService.php`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang sesudah satu atau lebih sesi FINAL (spec §6.3). SATU TRANSAKSI: bila satu
 * langkah gagal, tidak ada hasil, kurva, maupun jejak yang tertinggal setengah jadi.
 *
 * Yang dihitung ulang hanya peserta sesi; orang lain hanya berubah grade lewat kurva baru,
 * dan jejaknya tetap dicatat dengan sebab KURVA.
 */
class PaBalancingHitungUlangService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaBalancingPenjaga $penjaga,
        private readonly PaBalancingPotretHasil $potret,
        private readonly PaBalancingNilaiBerlaku $nilaiBerlaku,
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaKurvaService $kurva,
    ) {}

    public function jalankan(int $cycleId, ?int $olehUserId): array
    {
        if (! $this->gate->boleh(PaCycleGate::BALANCING, $cycleId)) {
            throw new PaBalancingException('Hitung ulang Balancing hanya untuk siklus CLOSED.', PaBalancingException::SIKLUS_BELUM_DITUTUP);
        }

        $terbuka = $this->penjaga->sesiTerbuka($cycleId);

        if ($terbuka->isNotEmpty()) {
            throw new PaBalancingException(
                'Masih ada sesi terbuka: '.$terbuka->pluck('Nama_Sesi')->implode(', ').'. Finalkan atau batalkan lebih dulu.',
                PaBalancingException::SESI_MASIH_TERBUKA,
            );
        }

        $sesi = $this->penjaga->sesiFinalBelumDihitung($cycleId);

        if ($sesi->isEmpty()) {
            throw new PaBalancingException('Tidak ada sesi FINAL yang menunggu hitung ulang.', PaBalancingException::TIDAK_ADA_SESI_FINAL);
        }

        $pakaiKurva = $this->kurva->metode($cycleId) === 'KURVA';

        if ($pakaiKurva && $this->jumlahSudahDibaca($cycleId) > 0) {
            throw new PaBalancingException(
                'Hitung ulang mengunci ulang kurva seluruh grup, padahal sebagian hasil siklus ini sudah terbit atau dirilis ke leader. '
                .'Cabut rilis leader lebih dulu; hasil yang sudah terbit tidak bisa dihitung ulang.',
                PaBalancingException::SUDAH_ADA_YANG_TERBIT,
            );
        }

        $sesiIds = $sesi->pluck('Id_PA_Balancing_Sesi')->map(fn ($x) => (int) $x)->all();
        // Peserta → sesi terbaru yang memuatnya (urut Final_At naik, yang terakhir menang).
        $sesiPeserta = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->whereIn('bp.PA_Balancing_Sesi_Id', $sesiIds)->orderBy('s.Final_At')->orderBy('s.Id_PA_Balancing_Sesi')
            ->get(['bp.PA_Participant_Id', 'bp.PA_Balancing_Sesi_Id'])
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Participant_Id => (int) $r->PA_Balancing_Sesi_Id])->all();

        return DB::transaction(function () use ($cycleId, $olehUserId, $pakaiKurva, $sesiIds, $sesiPeserta) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->first(['Id_PA_Cycle']);
            $jam = FormatTanggalHelper::getCurrentTime();
            $sebelum = $this->potret->siklus($cycleId);
            $dilepas = $this->lepasKalibrasiForum($cycleId, $olehUserId, $jam);

            if ($pakaiKurva && $this->kurva->terkunci($cycleId)) {
                $this->kurva->buka($cycleId, 'Hitung ulang Balancing', $olehUserId);
            }

            foreach (array_keys($sesiPeserta) as $pid) {
                $h = $this->perhitungan->hitung($pid, $olehUserId);

                if (($h['status'] ?? null) !== 'DIHITUNG') {
                    $nama = $sebelum[$pid]->Nama ?? "#{$pid}";
                    throw new PaBalancingException(
                        "{$nama} menjadi TERTAHAN saat dihitung ulang: {$h['alasan_tertahan']}. Hitung ulang dibatalkan seluruhnya.",
                        PaBalancingException::HITUNG_ULANG_TERTAHAN,
                    );
                }
            }

            if ($pakaiKurva) {
                $this->kurva->kunci($cycleId, $olehUserId);
            }

            $this->tulisKualitatif(array_keys($sesiPeserta), $olehUserId);

            $sesudah = $this->potret->siklus($cycleId);
            $nomor = (int) DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Cycle_Id', $cycleId)->max('Nomor_Hitung_Ulang') + 1;
            $jejak = $this->jejak($cycleId, $nomor, $sebelum, $sesudah, $sesiPeserta, $pakaiKurva, $olehUserId);

            foreach (array_chunk($jejak, 100) as $potongan) {
                DB::table('N_PA_Balancing_Jejak_Hitung')->insert($potongan);
            }

            DB::table('N_PA_Balancing_Sesi')->whereIn('Id_PA_Balancing_Sesi', $sesiIds)->update([
                'Nomor_Hitung_Ulang' => $nomor, 'Hitung_Ulang_At' => $jam, 'Hitung_Ulang_By' => $olehUserId,
                'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);

            return [
                'nomor' => $nomor,
                'sesi' => $sesiIds,
                'dihitung' => count($sesiPeserta),
                'kalibrasi_dilepas' => $dilepas,
                'berubah' => count(array_filter($jejak, fn ($j) => $j['Sebab'] !== PaBalancingStatus::SEBAB_TANPA_PERUBAHAN)),
            ];
        });
    }

    private function jumlahSudahDibaca(int $cycleId): int
    {
        return (int) DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')
            ->where(fn ($w) => $w->where('r.Status_Hasil', 'TERBIT')->orWhereNotNull('r.Published_Leader_At'))
            ->count();
    }

    /** T1: Kalibrasi Forum lama dilepas pada hitung ulang Balancing, dengan riwayat. */
    private function lepasKalibrasiForum(int $cycleId, ?int $olehUserId, string $jam): int
    {
        $baris = DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Flag_Dikalibrasi', 'Y')
            ->get(['r.Id_PA_Result', 'r.Kode_Grade_Final', 'r.Kode_Grade_Sistem', 'r.PA_Hav_Quadrant_Id']);

        foreach ($baris as $r) {
            DB::table('N_PA_Calibration_History')->insert([
                'PA_Result_Id' => (int) $r->Id_PA_Result,
                'Kode_Grade_Sebelum' => $r->Kode_Grade_Final,
                'Kode_Grade_Sesudah' => $olehUserId === null ? null : $r->Kode_Grade_Sistem,
                'PA_Hav_Quadrant_Sebelum_Id' => $r->PA_Hav_Quadrant_Id,
                'PA_Hav_Quadrant_Sesudah_Id' => null,
                'Alasan' => 'Kalibrasi Forum dilepas: siklus ini memakai Balancing, dan grade kini hanya bergeser lewat skor kompetensi dan kurva.',
                'Performed_By' => $olehUserId,
                'Performed_By_Nama' => $olehUserId === null ? 'Sistem (hitung ulang Balancing)' : null,
                'Performed_At' => $jam,
            ]);
        }

        if ($baris->isNotEmpty()) {
            DB::table('N_PA_Result')->whereIn('Id_PA_Result', $baris->pluck('Id_PA_Result'))->update([
                'Flag_Dikalibrasi' => 'T', 'Kode_Grade_Final' => DB::raw('Kode_Grade_Sistem'),
            ]);
        }

        return $baris->count();
    }

    /** Kualitatif After sesi FINAL terbaru menjadi catatan admin, hanya bila berbeda dari Before. */
    private function tulisKualitatif(array $pids, ?int $olehUserId): void
    {
        foreach ($pids as $pid) {
            $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->value('Id_PA_Result');

            foreach ($this->nilaiBerlaku->kualitatifBerlaku($pid) as $cid => $isi) {
                if ((string) $isi['sebelum'] === (string) $isi['sesudah']) {
                    continue;
                }

                DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)->where('PA_Competency_Id', $cid)
                    ->whereNull('PA_Assignment_Id')->where('Flag_Aktif', 'Y')
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $olehUserId]);

                if (($isi['sesudah'] ?? '') !== '') {
                    DB::table('N_PA_Result_Note')->insert([
                        'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $cid, 'PA_Assignment_Id' => null,
                        'Isi' => $isi['sesudah'], 'Urutan_Tampil' => 1, 'Flag_Aktif' => 'Y', 'Created_By' => $olehUserId,
                    ]);
                }
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function jejak(int $cycleId, int $nomor, array $sebelum, array $sesudah, array $sesiPeserta, bool $pakaiKurva, ?int $olehUserId): array
    {
        $baris = [];

        foreach ($sesudah as $pid => $s) {
            $b = $sebelum[$pid] ?? null;
            $finalLama = $b?->Final_Score;
            $gradeTengah = ($pakaiKurva && $finalLama !== null)
                ? $this->kurva->gradeBeku($pid, $finalLama)['kode']
                : $b?->Kode_Grade_Final;
            $sebab = PaBalancingKalkulator::sebab($finalLama, $s->Final_Score, $b?->Kode_Grade_Final, $gradeTengah, $s->Kode_Grade_Final);
            $diSesi = isset($sesiPeserta[$pid]);

            if ($sebab === null && ! $diSesi) {
                continue;
            }

            $baris[] = [
                'PA_Cycle_Id' => $cycleId, 'Nomor_Hitung_Ulang' => $nomor, 'PA_Participant_Id' => $pid,
                'PA_Balancing_Sesi_Id' => $sesiPeserta[$pid] ?? null,
                'Nilai_Kompetensi_Sebelum' => $b?->Nilai_Kompetensi, 'Nilai_Kompetensi_Sesudah' => $s->Nilai_Kompetensi,
                'Final_Score_Sebelum' => $finalLama, 'Final_Score_Sesudah' => $s->Final_Score,
                'Kode_Grade_Sebelum' => $b?->Kode_Grade_Final, 'Kode_Grade_Sesudah' => $s->Kode_Grade_Final,
                'PA_Hav_Quadrant_Sebelum_Id' => $b?->PA_Hav_Quadrant_Id, 'PA_Hav_Quadrant_Sesudah_Id' => $s->PA_Hav_Quadrant_Id,
                'Sebab' => $sebab ?? PaBalancingStatus::SEBAB_TANPA_PERUBAHAN,
                'Created_By' => $olehUserId,
            ];
        }

        return $baris;
    }
}
```

`gradeBeku` dipanggil per orang yang masuk jejak — hanya orang yang berubah atau peserta sesi, bukan seluruh siklus.

- [ ] **Step 4: Jalankan uji, pastikan lulus** — Run perintah Step 2 → PASS. Bila `buka()` menolak karena gerbang `BUKA_KURVA`, periksa bahwa siklus uji berstatus `CLOSED` (bawaan `siklusKurva`).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingHitungUlangService.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingHitungUlangTest.php
git commit -m "feat(kpi-pa): hitung ulang Balancing dengan kurva dikunci ulang dan jejak"
```

---

### Task 10: Penjaga publish dan penutupan Kalibrasi Forum

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php` (konstruktor + lima metode publik)
- Modify: `app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/PaCycleAdminController.php` (`kalibrasiHasil`)
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingPublishTest.php`

**Interfaces:**
- Consumes: `PaBalancingPenjaga::pastikanTuntas`.
- Produces: `terbitkan`, `terbitkanPeserta`, `rilisKeLeader`, `rilisKeLeaderPeserta`, `tutupPenerbitan` menolak dengan `PaBalancingException` bila Balancing belum tuntas; `POST /api/v1/performance-appraisal/results/{id}/kalibrasi` → 422 `PA_KALIBRASI_FORUM_DITUTUP`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingPublishTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_semua_pintu_publish_menunggu_balancing_tuntas(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');
        $layanan = app(PaResultPublishService::class);

        foreach ([
            'terbitkan' => fn () => $layanan->terbitkan($p['cycle_id'], 1),
            'terbitkanPeserta' => fn () => $layanan->terbitkanPeserta($p['participant_id'], 1),
            'rilisKeLeader' => fn () => $layanan->rilisKeLeader($p['cycle_id'], 1),
            'rilisKeLeaderPeserta' => fn () => $layanan->rilisKeLeaderPeserta($p['participant_id'], 1),
            'tutupPenerbitan' => fn () => $layanan->tutupPenerbitan($p['cycle_id'], 1),
        ] as $pintu => $f) {
            try {
                $f();
                $this->fail("{$pintu} harus menunggu sesi Balancing selesai.");
            } catch (PaBalancingException $e) {
                $this->assertSame(PaBalancingException::SESI_MASIH_TERBUKA, $e->errorCode, $pintu);
            }
        }
    }

    public function test_kalibrasi_forum_ditutup(): void
    {
        $p = $this->pesertaDihitung();
        $this->actingAs($this->penggunaHasil());

        $r = $this->postJson("/api/v1/performance-appraisal/results/{$p['result_id']}/kalibrasi", ['kode_grade' => 'A', 'alasan' => 'uji']);

        $r->assertStatus(422);
        $this->assertSame('PA_KALIBRASI_FORUM_DITUTUP', $r->json('error_code'));
    }
}
```

Periksa tanda tangan `terbitkanPeserta` (baris ~129 di `PaResultPublishService`) sebelum menjalankan; sesuaikan argumen di uji bila berbeda dari `(int $participantId, ?int $olehUserId)`.

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingPublishTest.php` → FAIL.

- [ ] **Step 3: Sambungkan penjaga di `PaResultPublishService`**

Tambah `use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;` dan konstruktor:

```php
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaNotificationService $pemberitahuan,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingPenjaga $balancing,
    ) {}
```

Di `terbitkan`, `rilisKeLeader`, `tutupPenerbitan`: tepat sesudah baris `$this->kurva->pastikanTerkunci($cycleId);` tambahkan

```php
        // Publish menunggu Balancing tuntas: tanpa sesi terbuka, semua sesi FINAL sudah dihitung ulang.
        $this->balancing->pastikanTuntas($cycleId);
```

Di `terbitkanPeserta` dan `rilisKeLeaderPeserta`: `pastikanTerkunci` dipanggil dengan siklus yang dibaca dari `N_PA_Participant`. Ubah blok itu supaya siklusnya disimpan ke variabel lalu dipakai dua kali:

```php
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');
        $this->kurva->pastikanTerkunci($cycleId);
        $this->balancing->pastikanTuntas($cycleId);
```

(Pertahankan persis argumen dan urutan yang ada; hanya ekstraksi variabel dan satu baris baru.)

- [ ] **Step 4: Tutup Kalibrasi Forum di controller**

Ganti isi `kalibrasiHasil` di `PaCycleAdminController` dengan:

```php
    /**
     * KALIBRASI FORUM DITUTUP (B7, spec 2026-09-30 Balancing). Grade kini hanya bergeser lewat
     * skor kompetensi di sesi Balancing dan kurva, sehingga grade selalu cocok dengan angkanya.
     * Rutenya dipertahankan supaya klien lama mendapat penjelasan, bukan 404.
     */
    public function kalibrasiHasil(Request $request, int $resultId)
    {
        return ResponseHelper::errorWithCode(
            'Kalibrasi Forum sudah digantikan Balancing. Geser skor kompetensinya lewat sesi Balancing, lalu hitung ulang.',
            'PA_KALIBRASI_FORUM_DITUTUP',
            422,
        );
    }
```

Pertahankan tanda tangan metode yang ada (periksa parameternya dulu). `PaCalibrationService` tetap ada: dipakai perintah semaian dan pembaca riwayat.

- [ ] **Step 5: Jalankan uji publish dan uji lama**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/ tests/Feature/KPI/PerformanceAppraisal/ResultPublishTest.php tests/Feature/KPI/PerformanceAppraisal/CalibrationTest.php tests/Feature/KPI/PerformanceAppraisal/LeaderTest.php`
Expected: PASS. Uji di `CalibrationTest` yang memanggil **endpoint HTTP** kalibrasi (bukan service) akan merah — ubah harapannya menjadi 422 `PA_KALIBRASI_FORUM_DITUTUP` dengan komentar merujuk B7; uji yang memanggil `PaCalibrationService` langsung tetap lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php \
  app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/PaCycleAdminController.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingPublishTest.php
# tambahkan tests/Feature/KPI/PerformanceAppraisal/CalibrationTest.php bila Step 5 mengubahnya
git commit -m "feat(kpi-pa): publish menunggu Balancing, Kalibrasi Forum ditutup"
```

---

### Task 11: API Balancing

**Files:**
- Create: `app/Http/Controllers/KPI/PerformanceAppraisal/Balancing/PaBalancingController.php`
- Create: `routes/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php`
- Modify: `routes/web.php` (satu `require` sesudah baris Export PA)
- Test: `tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingApiTest.php`

**Interfaces:**
- Consumes: semua service Task 5–9.
- Produces (semua di prefix `/api/v1/performance-appraisal`, middleware `auth`, `check.jabatan:PaHasilPage`):

| Metode | Alamat | Badan | Hasil (`result`) |
|---|---|---|---|
| GET | `/cycles/{cycleId}/balancing/sesi` | – | `{sesi: daftar(), menunggu_hitung_ulang: int, terbuka: int}` |
| GET | `/cycles/{cycleId}/balancing/kandidat` | query saringan Task 5 | `list` kandidat |
| POST | `/cycles/{cycleId}/balancing/sesi` | `nama, pic_user_id, forum[], peserta_ids[]` | `detail()` |
| GET | `/balancing/sesi/{sesiId}` | – | `detail()` |
| PUT | `/balancing/sesi/{sesiId}` | `versi_data, peserta[], catatan_rapat?` | `detail()` |
| POST | `/balancing/sesi/{sesiId}/final` | `versi_data` | `detail()` |
| POST | `/balancing/sesi/{sesiId}/batal` | `alasan` | `detail()` |
| POST | `/balancing/sesi/{sesiId}/simulasi` | `peserta[]` (draft) | hasil simulasi Task 8 |
| POST | `/cycles/{cycleId}/balancing/hitung-ulang` | – | hasil Task 9 |
| GET | `/participants/{participantId}/balancing` | – | `{sesi: list<{id, nama, status, final_at, nomor_hitung_ulang, kompetensi: list<{nama, sebelum, sesudah, alasan}>}>, jejak: list<baris jejak>}` |

Galat `PaBalancingException` → `ResponseHelper::errorWithCode(pesan, errorCode, httpStatus)`; `RuntimeException` lain → 422.

- [ ] **Step 1: Tulis uji HTTP yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingApiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private const API = '/api/v1/performance-appraisal';

    public function test_alur_buat_simpan_simulasi_final_lewat_http(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        $this->actingAs($this->penggunaHasil());
        [$k1] = $p['kompetensi'];

        $kandidat = $this->getJson(self::API."/cycles/{$p['cycle_id']}/balancing/kandidat");
        $kandidat->assertOk();
        $this->assertContains($p['participant_id'], array_column($kandidat->json('result'), 'participant_id'));

        $buat = $this->postJson(self::API."/cycles/{$p['cycle_id']}/balancing/sesi", [
            'nama' => 'Sesi HTTP', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'), 'forum' => [], 'peserta_ids' => [$p['participant_id']],
        ]);
        $buat->assertOk();
        $id = $buat->json('result.id');

        $simpan = $this->putJson(self::API."/balancing/sesi/{$id}", [
            'versi_data' => $buat->json('result.versi_data'),
            'peserta' => [['participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80, 'alasan' => 'Konsisten']]]],
        ]);
        $simpan->assertOk();
        $this->assertSame('DIUBAH', $simpan->json('result.peserta.0.status_tinjau'));

        $sim = $this->postJson(self::API."/balancing/sesi/{$id}/simulasi", ['peserta' => []]);
        $sim->assertOk();
        $this->assertSame(82.5, $sim->json('result.peserta.0.sesudah.nilai_kompetensi'));

        $final = $this->postJson(self::API."/balancing/sesi/{$id}/final", ['versi_data' => $simpan->json('result.versi_data')]);
        $final->assertOk();
        $this->assertSame('FINAL', $final->json('result.status'));

        $daftar = $this->getJson(self::API."/cycles/{$p['cycle_id']}/balancing/sesi");
        $this->assertSame(1, $daftar->json('result.menunggu_hitung_ulang'));

        $riwayat = $this->getJson(self::API."/participants/{$p['participant_id']}/balancing");
        $riwayat->assertOk();
        $this->assertSame('Sesi HTTP', $riwayat->json('result.sesi.0.nama'));
    }

    public function test_galat_membawa_error_code(): void
    {
        $p = $this->pesertaDihitung();
        $this->actingAs($this->penggunaHasil());
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Status_Cycle' => 'OPEN']);

        $r = $this->postJson(self::API."/cycles/{$p['cycle_id']}/balancing/sesi", [
            'nama' => 'X', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'), 'forum' => [], 'peserta_ids' => [$p['participant_id']],
        ]);

        $r->assertStatus(422);
        $this->assertSame('PA_BAL_SIKLUS_BELUM_DITUTUP', $r->json('error_code'));
        $this->assertNotEmpty($r->json('request_id'));
    }

    public function test_tanpa_hak_hasil_ditolak(): void
    {
        $this->actingAs($this->penilaiUji());
        $this->getJson(self::API.'/cycles/1/balancing/sesi')->assertStatus(403);
    }
}
```

Periksa kode status yang dikembalikan `check.jabatan` untuk permintaan JSON tanpa hak (lihat uji HTTP lain yang menegaskan penolakan akses, mis. `grep -rn "assertStatus(403)\|assertForbidden" tests/Feature/KPI/PerformanceAppraisal`). Bila `penilaiUji()` kebetulan memegang `PaHasilPage`, pilih user lain yang tidak memegangnya.

- [ ] **Step 2: Jalankan, pastikan gagal** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingApiTest.php` → FAIL (404).

- [ ] **Step 3: Ambil baseline snapshot rute SEBELUM menambah rute**

Run: `unset DATABASE_URL; php scripts/route-snapshot.php /tmp/claude-1000/rute-sebelum.json` (pakai direktori scratchpad sesi).

- [ ] **Step 4: Tulis controller**

```php
<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Balancing;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingHitungUlangService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSesiService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSimulasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** API Balancing (spec 2026-09-30). Semua penolakan aturan → 422/404/409 dengan `error_code`. */
class PaBalancingController extends Controller
{
    public function __construct(
        private readonly PaBalancingSesiService $sesi,
        private readonly PaBalancingKandidatService $kandidat,
        private readonly PaBalancingSimulasiService $simulasi,
        private readonly PaBalancingHitungUlangService $hitungUlang,
        private readonly PaBalancingPenjaga $penjaga,
    ) {}

    public function daftarSesi(int $cycleId)
    {
        return $this->jalankan(fn () => [
            'sesi' => $this->sesi->daftar($cycleId),
            'menunggu_hitung_ulang' => $this->penjaga->sesiFinalBelumDihitung($cycleId)->count(),
            'terbuka' => $this->penjaga->sesiTerbuka($cycleId)->count(),
        ]);
    }

    public function kandidat(Request $request, int $cycleId)
    {
        $s = $request->validate([
            'level_ids' => ['nullable', 'array'], 'level_ids.*' => ['integer'],
            'divisi_ids' => ['nullable', 'array'], 'divisi_ids.*' => ['integer'],
            'sub_divisi_ids' => ['nullable', 'array'], 'sub_divisi_ids.*' => ['integer'],
            'grup_kurva_ids' => ['nullable', 'array'], 'grup_kurva_ids.*' => ['integer'],
            'grade' => ['nullable', 'array'], 'grade.*' => ['string', 'max:40'],
            'kuadran_ids' => ['nullable', 'array'], 'kuadran_ids.*' => ['integer'],
            'jumlah_sesi' => ['nullable', 'integer', 'min:0'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->jalankan(fn () => $this->kandidat->daftar($cycleId, $s));
    }

    public function buatSesi(Request $request, int $cycleId)
    {
        $d = $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'pic_user_id' => ['required', 'integer'],
            'forum' => ['present', 'array', 'max:30'],
            'forum.*.kode_perusahaan' => ['required', 'string', 'max:3'],
            'forum.*.kode_karyawan' => ['required', 'string', 'max:255'],
            'forum.*.peran' => ['required', 'string', 'max:100'],
            'peserta_ids' => ['required', 'array', 'min:1', 'max:500'],
            'peserta_ids.*' => ['integer'],
        ]);

        return $this->jalankan(fn () => $this->sesi->buat($cycleId, $d, $this->userId()));
    }

    public function detailSesi(int $sesiId)
    {
        return $this->jalankan(fn () => $this->sesi->detail($sesiId));
    }

    public function simpanSesi(Request $request, int $sesiId)
    {
        $d = $request->validate([
            'versi_data' => ['required', 'integer', 'min:1'],
            'catatan_rapat' => ['nullable', 'string', 'max:20000'],
            'peserta' => ['present', 'array'],
            'peserta.*.participant_id' => ['required', 'integer'],
            'peserta.*.status_tinjau' => ['nullable', 'in:BELUM,TANPA_PERUBAHAN'],
            'peserta.*.alasan' => ['nullable', 'string', 'max:1000'],
            'peserta.*.kompetensi' => ['nullable', 'array'],
            'peserta.*.kompetensi.*.competency_id' => ['required', 'integer'],
            'peserta.*.kompetensi.*.nilai_sesudah' => ['required', 'numeric'],
            'peserta.*.kompetensi.*.alasan' => ['nullable', 'string', 'max:1000'],
            'peserta.*.kualitatif' => ['nullable', 'array'],
            'peserta.*.kualitatif.*.competency_id' => ['required', 'integer'],
            'peserta.*.kualitatif.*.isi_sesudah' => ['nullable', 'string', 'max:20000'],
        ]);

        return $this->jalankan(fn () => $this->sesi->simpanDraft(
            $sesiId, (int) $d['versi_data'], $d['peserta'], $d['catatan_rapat'] ?? null, $this->userId(),
        ));
    }

    public function finalkanSesi(Request $request, int $sesiId)
    {
        $d = $request->validate(['versi_data' => ['required', 'integer', 'min:1']]);

        return $this->jalankan(fn () => $this->sesi->finalkan($sesiId, (int) $d['versi_data'], $this->userId()));
    }

    public function batalkanSesi(Request $request, int $sesiId)
    {
        $d = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        return $this->jalankan(fn () => $this->sesi->batalkan($sesiId, $d['alasan'], $this->userId()));
    }

    public function simulasikan(Request $request, int $sesiId)
    {
        $d = $request->validate([
            'peserta' => ['present', 'array'],
            'peserta.*.participant_id' => ['required', 'integer'],
            'peserta.*.kompetensi' => ['nullable', 'array'],
            'peserta.*.kompetensi.*.competency_id' => ['required', 'integer'],
            'peserta.*.kompetensi.*.nilai_sesudah' => ['required', 'numeric'],
        ]);

        return $this->jalankan(fn () => $this->simulasi->simulasikan($sesiId, $d['peserta']));
    }

    public function hitungUlang(int $cycleId)
    {
        // Menghitung ulang peserta sesi satu per satu; beberapa puluh orang bisa melewati 30 detik.
        @set_time_limit(600);

        return $this->jalankan(fn () => $this->hitungUlang->jalankan($cycleId, $this->userId()));
    }

    public function riwayatPeserta(int $participantId)
    {
        return $this->jalankan(function () use ($participantId) {
            $sesi = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
                ->where('bp.PA_Participant_Id', $participantId)
                ->orderByDesc('s.Id_PA_Balancing_Sesi')
                ->get(['s.Id_PA_Balancing_Sesi']);

            return [
                'sesi' => $sesi->map(function ($s) use ($participantId) {
                    $d = $this->sesi->detail((int) $s->Id_PA_Balancing_Sesi);
                    $orang = collect($d['peserta'])->firstWhere('participant_id', $participantId);

                    return [
                        'id' => $d['id'], 'nama' => $d['nama'], 'status' => $d['status'], 'final_at' => $d['final_at'],
                        'hitung_ulang_at' => $d['hitung_ulang_at'], 'status_tinjau' => $orang['status_tinjau'] ?? null,
                        'kompetensi' => collect($orang['kompetensi'] ?? [])
                            ->map(fn ($k) => ['nama' => $k['nama'], 'sebelum' => $k['sebelum'], 'sesudah' => $k['sesudah'], 'alasan' => $k['alasan']])
                            ->values()->all(),
                    ];
                })->all(),
                'jejak' => DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Participant_Id', $participantId)
                    ->orderByDesc('Nomor_Hitung_Ulang')
                    ->get(['Nomor_Hitung_Ulang', 'PA_Balancing_Sesi_Id', 'Final_Score_Sebelum', 'Final_Score_Sesudah',
                        'Kode_Grade_Sebelum', 'Kode_Grade_Sesudah', 'PA_Hav_Quadrant_Sebelum_Id', 'PA_Hav_Quadrant_Sesudah_Id', 'Sebab', 'Created_At'])
                    ->all(),
            ];
        });
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (PaBalancingException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->errorCode, $e->httpStatus);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'PA_BAL_DITOLAK', 422);
        }
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
```

`riwayatPeserta` memanggil `detail()` per sesi — lewat pintu `PaBalancingSesiService`, sehingga controller tidak menyentuh kolom After sendiri (uji satu pintu tetap hijau).

- [ ] **Step 5: Tulis berkas rute dan daftarkan**

`routes/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php`:

```php
<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Balancing\PaBalancingController;
use Illuminate\Support\Facades\Route;

/*
 * Performance Appraisal — Balancing berulang sesudah Calculate
 * (docs/superpowers/specs/2026-09-30-pa-balancing-design.md).
 *
 * Kunci halaman PaHasilPage, sama dengan Hitung dan Terbitkan: Balancing mengubah angka yang
 * sampai ke karyawan, jadi ia milik fase Hasil.
 */
Route::middleware(['auth', 'check.jabatan:PaHasilPage'])
    ->prefix('api/v1/performance-appraisal')
    ->group(function () {
        Route::get('/cycles/{cycleId}/balancing/sesi', [PaBalancingController::class, 'daftarSesi'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/balancing/kandidat', [PaBalancingController::class, 'kandidat'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/balancing/sesi', [PaBalancingController::class, 'buatSesi'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/balancing/hitung-ulang', [PaBalancingController::class, 'hitungUlang'])->whereNumber('cycleId');
        Route::get('/balancing/sesi/{sesiId}', [PaBalancingController::class, 'detailSesi'])->whereNumber('sesiId');
        Route::put('/balancing/sesi/{sesiId}', [PaBalancingController::class, 'simpanSesi'])->whereNumber('sesiId');
        Route::post('/balancing/sesi/{sesiId}/final', [PaBalancingController::class, 'finalkanSesi'])->whereNumber('sesiId');
        Route::post('/balancing/sesi/{sesiId}/batal', [PaBalancingController::class, 'batalkanSesi'])->whereNumber('sesiId');
        Route::post('/balancing/sesi/{sesiId}/simulasi', [PaBalancingController::class, 'simulasikan'])->whereNumber('sesiId');
        Route::get('/participants/{participantId}/balancing', [PaBalancingController::class, 'riwayatPeserta'])->whereNumber('participantId');
    });
```

Di `routes/web.php`, sesudah `require __DIR__.'/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php';`:

```php
    require __DIR__.'/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php';
```

- [ ] **Step 6: Jalankan uji HTTP** — Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingApiTest.php` → PASS.

- [ ] **Step 7: Bandingkan snapshot rute**

Run: `unset DATABASE_URL; php scripts/route-snapshot.php /tmp/claude-1000/rute-sesudah.json && diff <(jq -S . /tmp/claude-1000/rute-sebelum.json) <(jq -S . /tmp/claude-1000/rute-sesudah.json)`
Expected: tepat 10 rute baru `api/v1/performance-appraisal/.../balancing...`, tidak ada rute lain berubah. (Periksa cara pakai skrip dengan `php scripts/route-snapshot.php --help` atau membaca kepalanya bila argumennya berbeda.)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/KPI/PerformanceAppraisal/Balancing/PaBalancingController.php \
  routes/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php routes/web.php \
  tests/Feature/KPI/PerformanceAppraisal/Balancing/BalancingApiTest.php
git commit -m "feat(kpi-pa): API Balancing

Rute baru (route-snapshot): 10 endpoint di api/v1/performance-appraisal/*balancing*,
semuanya check.jabatan:PaHasilPage. Tidak ada rute lain berubah."
```

---

### Task 12: Gerbang struktural dan regresi PA

**Files:** tidak ada berkas baru; perbaikan hanya bila gerbang menemukan masalah.

- [ ] **Step 1: Gerbang struktural**

Run:
```bash
unset DATABASE_URL
composer dump-autoload -o --strict-psr
php scripts/verify-references.php --max=0
php scripts/verify-frontend-imports.php
php scripts/verify-inertia-pages.php
```
Expected: semuanya lulus tanpa temuan baru.

- [ ] **Step 2: Suite PA lengkap**

Run: `unset DATABASE_URL; php vendor/bin/phpunit tests/Feature/KPI/PerformanceAppraisal`
Expected: semua PASS kecuali kegagalan data-dependent yang sudah tercatat di memori `kendala-environment-kpi-unreal` (mis. `CycleSetupTest`). Setiap kegagalan lain dibandingkan dengan `origin/staging` sebelum dianggap bukan regresi; laporkan keduanya dengan keluaran aslinya.

- [ ] **Step 3: Uji asap di dev lewat API**

Dengan server dev2 (`hcis-w-6`) berjalan dan login admin `NOIR` (kata sandi pengembangan), pada siklus #8781 (`PA-2026-01`): catatan — #8781 sudah TERBIT, jadi kandidatnya harus tampil tidak layak dengan alasan "sudah terbit". Itu bukti penjaga bekerja; uji alur penuh ada di Bagian 2 sesudah siklus uji baru disiapkan. Jalankan:

```bash
# memakai helper api dari sesi sebelumnya (cookie sesi + X-CSRF-TOKEN)
GET  /api/v1/performance-appraisal/cycles/8781/balancing/kandidat   → layak=false, alasan "Hasil sudah terbit ke karyawan"
GET  /api/v1/performance-appraisal/cycles/8781/balancing/sesi       → sesi: [], menunggu_hitung_ulang: 0
```

- [ ] **Step 4: Laporan akhir tugas (AGENTS.md)**

Dua bagian: **Agent completed** (berkas, eksekusi & verifikasi DDL dev, uji) dan **User tasks** (tinjau PR, rekam DDL-2026-165 di SQL Tracker, eksekusi production, verifikasi production). Push branch dan buka PR ke `staging` hanya bila pemilik proyek memintanya.

---

## Catatan untuk Bagian 2 (frontend)

Bagian 2 memakai kontrak API di tabel Task 11 dan akan mencakup: fungsi `paApi.js` untuk sepuluh endpoint; halaman `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/` (daftar sesi + buat sesi, board dengan matriks kompetensi/kualitatif, panel simulasi dengan HAV map sebelum/sesudah memakai `havScatter.js`); tab "Balancing" di `PaResultDialog.vue` dan pelepasan tombol Kalibrasi Forum; rute halaman + DML menu (mengalihkan butir `PaKalibrasiPage` "Kalibrasi Grade Akhir" menjadi "Balancing"); uji Vitest; dan uji manual penuh dua putaran di siklus uji dev baru.

Penyimpangan kecil dari spec yang dibawa rencana ini, untuk ditinjau pemilik proyek:
- **Expected Behaviour** tidak ada di master kompetensi (`N_PA_Competency` hanya punya `Deskripsi`); layar menampilkan `Deskripsi` sebagai definisi.
- **Kualitatif** mengikuti master kompetensi KUALITATIF (Strength, Development Area, Initiative, Recommendation per 30 Sep 2026), bukan dua jenis tetap.
- Kandidat yang **sudah dirilis ke leader** tidak layak masuk sesi, dan hitung ulang siklus KURVA ditolak selama ada rilis leader aktif (kurva seluruh grup ikut bergeser).
