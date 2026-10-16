# Organization Relationship — blueprint implementasi

Tanggal: 2026-08-29  
Change ID: `DDL-2026-061-hris-organization-relationship`  
Status: `DEV_EXECUTED` / `TRACKER_PENDING` — terlaksana di `Web_HRIS` development

## Basis terverifikasi

Target development telah diverifikasi read-only sebagai `Web_HRIS`, dengan koneksi project ke host `team311.dyndns.info:1433` dan `APP_ENV=local`.

Ownership pemeliharaan awal dan posisi fondasi:

```text
Modul      ESS
Submodul   Organization
Fitur      Relationship
Layer      database DDL/DML + dokumentasi tabel/perubahan
Konsumen   lintas modul; KPI / Appraisal adalah konsumen pertama
```

Database master dan mapping aktif dibaca ulang 2026-08-29:

- `ESS` aktif sebagai `N_HRIS_Master_Grup_Fitur.Id_Grup_Fitur = 1`.
- `KPI` aktif sebagai `Id_Grup_Fitur = 2`.
- Page organisasi existing `addKaryawanTeam` dipetakan ke ESS.
- PK `Karyawan` adalah komposit `(Kode_Perusahaan varchar(3), Kode_Karyawan varchar(255))`, keduanya NOT NULL dan memakai collation `SQL_Latin1_General_CP1_CI_AS`. Data development berisi 965 pasangan berbeda.
- `KPI_Users.Id_Users` adalah PK `int`; dipakai sebagai sumber semantik audit actor manusia. Audit actor nullable supaya seed/system process tidak membutuhkan akun palsu.

Karena candidate key resminya komposit, kedua ujung relationship menyimpan kode perusahaan dan kode karyawan lalu memakai composite FK. Percobaan parse-only membuktikan FK ke `Kode_Karyawan` saja ditolak SQL Server; menambah unique constraint baru ke master legacy hanya demi feature ini dinilai tidak aman. DDL tidak menyentuh master `Karyawan`, Divisi, Level, Jabatan, atau tabel appraisal.

## Masalah

HCIS belum mempunyai authority untuk hubungan employee-to-employee. `KPI_Approval_Flow_Performance` menyimpan urutan approval, bukan struktur organisasi, dan data development-nya memuat data uji. Menggunakannya langsung membuat assignment appraisal bergantung pada workflow KPI dan tidak dapat menyimpan atasan fungsional maupun periode berlaku dengan benar.

## Keputusan

### 1. Master jenis relasi

`N_HRIS_Organization_Relationship_Type` menyimpan business key jenis relasi. Kode bersifat unik dan stabil; nama/deskripsi dapat berubah tanpa mengubah referensi.

Seed awal:

| Kode | Fungsi |
|---|---|
| `DIRECT_SUPERVISOR` | Atasan struktural langsung; sumber utama kandidat Atasan 1. |
| `FUNCTIONAL_SUPERVISOR` | Atasan fungsional; kandidat sesuai policy modul konsumen. |
| `PEER` | Peer eksplisit bila organisasi memang memeliharanya; policy tetap boleh menurunkan kandidat lain. |
| `PROJECT_MANAGER` | Manager proyek lintas unit; dipakai sesuai policy modul konsumen. |
| `MATRIX_SUPERVISOR` | Atasan struktur matrix/cross-functional, terpisah dari direct reporting. |

`SUBORDINATE` tidak disimpan karena merupakan pembacaan terbalik `DIRECT_SUPERVISOR`. `SECOND_LEVEL_SUPERVISOR` juga diturunkan melalui traversal, bukan diduplikasi sebagai master awal. Functional, project, dan matrix tidak digabung karena policy penggunaannya dapat berbeda.

### 2. Relasi aktual

`N_HRIS_Organization_Relationship` menyimpan satu baris directional:

```text
Kode_Karyawan_Subjek --Relationship_Type--> Kode_Karyawan_Terkait
```

Relasi memiliki periode berlaku, primary, priority, provenance, flag aktif, dan audit. Tidak ada bobot appraisal.

Tabel ini adalah fondasi organisasi lintas modul. Subject dan related employee boleh berasal dari Divisi, Departemen, Level, Jabatan, atau perusahaan berbeda. Tidak ada constraint same-unit/same-position. `Flag_Utama` menandai primary di dalam jenis relasi yang sama; primary project/matrix tidak menggantikan direct supervisor. Setiap modul menentukan akses dan scope-nya sendiri.

### 3. Constraint

- FK type memastikan hanya jenis relasi master yang dipakai.
- Dua composite FK employee memastikan kedua pasangan `(Kode_Perusahaan, Kode_Karyawan)` ada di `Karyawan`, termasuk untuk hubungan lintas perusahaan.
- Unique index `(type, subject, related, start date)` mencegah duplikasi identik.
- Filtered unique index `(type, subject)` untuk row primary, aktif, dan open-ended mencegah dua primary terbuka untuk jenis yang sama.
- Check tanggal memastikan akhir tidak mendahului awal.
- Check employee memastikan subjek tidak berelasi ke dirinya sendiri.
- Nilai kode jenis dan flag tidak dibatasi CHECK enum; backend mengelola daftar berkembang sesuai aturan repo.

Overlap pada dua periode bounded tidak dapat dijaga secara aman oleh index biasa. Service penulis kelak wajib memvalidasi overlap di dalam transaksi. Trigger tidak dibuat pada fase tabel dasar.

### 4. Index pola baca

1. `IX_HRIS_Org_Rel_Subject_Type_Period`: kandidat atasan/peer dari pasangan perusahaan+karyawan subjek.
2. `IX_HRIS_Org_Rel_Related_Type_Period`: inverse lookup untuk kandidat bawahan.
3. `UX_HRIS_Org_Rel_Type_Subject_Related_Start`: idempotensi baris bisnis pada kedua pasangan employee.
4. `UX_HRIS_Org_Rel_Primary_Open`: satu primary aktif open-ended per subjek dan tipe.

### 5. Audit dan provenance

`Created_By`, `Updated_By`, dan `Cancelled_By` bertipe `int NULL`, dengan sumber semantik `KPI_Users.Id_Users` untuk tindakan manusia. Tidak diberi FK agar histori tidak bergantung pada keberadaan akun dan agar system/import actor dapat memakai NULL.

`Sumber_Data` dan `Referensi_Sumber` menjelaskan asal fakta, misalnya `MANUAL`, `IMPORT`, atau `KPI_APPROVAL_FLOW`. Daftar sumber divalidasi application layer, bukan CHECK constraint. `Referensi_Sumber` tidak pernah dianggap authority.

### 6. Batas domain

Organization tables hanya menyimpan fakta organisasi. Rater Policy di KPI/Appraisal:

1. membaca participant level/division snapshot;
2. memilih policy detail;
3. mengambil kandidat dari relationship pada tanggal acuan;
4. menurunkan bawahan dan atasan tingkat dua;
5. menampilkan rekomendasi beserta provenance;
6. membekukan keputusan admin ke Participant Rater Assignment per cycle.

Perubahan relationship setelah cycle dikunci tidak mengubah assignment cycle lama.

## DML dan cakupan baris

DML hanya menyentuh dua tabel yang langsung terkait:

| Tabel | Predicate/scope | Expected fresh development rows |
|---|---|---:|
| `N_HRIS_Organization_Relationship_Type` | lima `Kode_Relasi` awal | 5 INSERT |
| `N_SYSTEM_Database_Change` | `Kode_Change = DDL-2026-061-hris-organization-relationship` | 1 INSERT |

DML bersifat idempoten: hanya menyisipkan kode/record yang belum ada dan tidak menimpa baris existing. Pada fresh development setelah DDL, expected affected rows adalah enam.

## Artefak

```text
database/ddl/DDL-2026-061-hris-organization-relationship/forward.sql
database/ddl/DDL-2026-061-hris-organization-relationship/drop.sql
database/dml/DDL-2026-061-hris-organization-relationship/data-change.sql
docs/changes/DDL-2026-061-hris-organization-relationship/README.md
docs/database/tables/hris/n-hris-organization-relationship-type.md
docs/database/tables/hris/n-hris-organization-relationship.md
database/ddl/status.json
```

`drop.sql` hanya bantuan reset manual dan tidak boleh dijalankan agent. Forward DDL dan DML yang diuji di development harus identik dengan artefak yang diteruskan ke SQL Tracker.

## Status akhir yang diizinkan pada task ini

Sesudah eksekusi dan verifikasi development:

```text
Development   DONE / DEV_EXECUTED
SQL Tracker   PENDING
Production    PENDING
Verification  DEV_DONE
Overall       TRACKER_PENDING
```

Production tidak boleh ditandai selesai tanpa konfirmasi user setelah menjalankan artefak melalui SQL Tracker.
