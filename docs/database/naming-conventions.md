# Database Naming Conventions

Berlaku untuk tabel dan kolom baru. Tabel legacy tidak diubah hanya untuk menyamakan format.

## Table

Format: `N_<Modul>_<Fitur>`, dengan segmen tambahan bila dibutuhkan agar nama berkelanjutan dan mudah dicari. Nama entitas tunggal dan deskriptif.

Contoh:

```text
N_HRIS_Karyawan
N_SOP_Dokumen_Versi_Berkas_Data
N_KPI_Target_Karyawan
N_LMS_Pelatihan
```

Prefix resmi saat ini: `N_HRIS_`, `N_SOP_`, `N_KPI_`, `N_LMS_`, `N_PA_`. Prefix modul baru harus dikonfirmasi terlebih dahulu. Jangan menambahkan `dbo.` atau schema lain pada nama baru kecuali diminta eksplisit.

`N_PA_` adalah prefix domain Performance Appraisal, submodul dari KPI. Ia berdiri sendiri karena Performance Appraisal punya siklus, peserta, penilai, dan hasil sendiri yang tidak berbagi bentuk dengan tabel `N_KPI_`. Tabel organisasi lintas modul tetap `N_HRIS_Organization_*` dan bukan milik PA.

## Constraint dan index

Nama constraint dan index memakai prefix domain yang sama dengan tabelnya, tanpa penanda `N_`:

```text
N_PA_Rater_Policy
  PK_PA_Rater_Policy                 primary key
  FK_PA_Rater_Policy_Level           foreign key
  CK_PA_Rater_Policy_Versi           check constraint
  DF_PA_Rater_Policy_Aktif           default constraint
  IX_PA_Rater_Policy_Level_Period    index biasa
  UX_PA_Rater_Policy_Kode_Versi      index unik
```

Nama metadata tidak boleh membawa prefix yang sudah tidak dipakai tabelnya. Kalau nama tabel berubah, nama constraint dan index ikut berubah dalam perubahan yang sama.

## Column

Gunakan kapitalisasi per kata dengan underscore: `Nama_Barang`, `Tanggal_Mulai`, `Nomor_NIK`. Singkatan umum dan tidak ambigu boleh digunakan.

Primary key mengidentifikasi entitas, misalnya `Id_Dokumen_Versi`. Foreign key memakai `<Entitas>_Id`, misalnya `Dokumen_Versi_Id`.
