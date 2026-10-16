# Table Design Rules

## Flags

Boolean-like column baru memakai `Flag_<Makna> char(1)`, panjang 1, dengan `Y` dan `T`. `Flag_Aktif` biasanya berarti row masih aktif/berlaku, tetapi flag tambahan boleh dibuat bila memiliki makna berbeda, misalnya `Flag_Sudah_Sync` atau `Flag_Hapus_Akses`. Default mengikuti konteks: `Flag_Aktif` umumnya `Y`, sedangkan flag kondisi yang belum terjadi umumnya `T`. Jangan memakai enum atau CHECK constraint untuk daftar nilai bisnis yang dapat berkembang; validasi utama berada di backend.

## Types and nullability

- `int` untuk ID biasa; `bigint` hanya bila skala memerlukan.
- `datetime2` untuk tanggal dan waktu.
- `varchar` atau `nvarchar` sesuai kebutuhan karakter, dengan panjang eksplisit.
- Precision dan scale decimal harus eksplisit.
- `NULL`/`NOT NULL` ditentukan oleh konteks bisnis.

## Audit

`Created_At`, `Created_By`, `Updated_At`, `Updated_By`, `Cancelled_At`, dan `Cancelled_By` ditambahkan sesuai lifecycle tabel, tidak otomatis. Actor audit default bertipe `varchar(100)`, tetapi sumbernya wajib dikonfirmasi sebelum DDL: `Kode_Karyawan`, `UserID_Web`, `UserID_Absen`, username, system/job, atau identifier lain serta sumber tabel/sistemnya.

## Constraints and indexes

Unique constraint/index, foreign key, index pencarian, dan cascade dipilih sesuai konteks bisnis, query, dan volume. Tidak dibuat mekanis. Cascade bukan default untuk data operasional.

## DDL dan DML

DDL dan DML selalu terpisah dan tidak diberi komentar dokumentasi panjang di dalam SQL.
