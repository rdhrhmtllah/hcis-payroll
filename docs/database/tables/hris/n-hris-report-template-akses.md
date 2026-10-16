# N_HRIS_Report_Template_Akses

Daftar sasaran berbagi untuk template ber-`Tingkat_Bagi = 'DIBAGIKAN'`.

**Change ID:** `DDL-2026-009-create-report-template-tables`
**SQL:** `database/ddl/DDL-2026-009-create-report-template-tables/forward.sql`

## Tujuan

Template `PRIBADI` tak punya baris di sini; `ORGANISASI` juga tidak — keduanya
ditentukan kolom `Tingkat_Bagi` saja. Tabel ini hanya dipakai saat berbagi
menyasar orang atau unit tertentu.

Audiens Export Studio adalah admin dan leader, sehingga "bagikan ke tim saya"
bukan bentuk yang berguna; yang dibutuhkan adalah sasaran bernama.

## Kolom

| Kolom | Tipe | Null | Arti |
|---|---|---|---|
| `Id_Report_Template_Akses` | `int IDENTITY(1,1)` | tidak | Primary key. |
| `Report_Template_Id` | `int` | tidak | Menunjuk `N_HRIS_Report_Template.Id_Report_Template`. Modul ikut dari template, tidak diduplikasi di sini. |
| `Tipe_Sasaran` | `varchar(20)` | tidak | `KARYAWAN`, `DIVISI`, `DEPARTEMEN`, atau `LOKASI`. |
| `Nilai_Sasaran` | `varchar(100)` | tidak | Nilai sasaran sesuai tipenya. |
| `Flag_Aktif` | `char(1)` | tidak | `Y` berlaku, `T` dicabut. Default `Y`. |
| `Created_At` | `datetime2(0)` | tidak | Default `SYSDATETIME()`. |
| `Created_By` | `varchar(100)` | ya | `Kode_Karyawan` dari `HRIS_Karyawan`. |

## Constraint dan index

| Objek | Alasan |
|---|---|
| `UX_Report_Template_Akses_Sasaran` | Satu sasaran hanya boleh terdaftar sekali per template. |
| `IX_Report_Template_Akses_Cari` | Arah baca sebaliknya: "template apa saja yang dibagikan ke divisi X". |

Tanpa FOREIGN KEY dan tanpa cascade. Menonaktifkan template tidak menghapus
baris akses — riwayat berbaginya tetap terbaca.

## Dipakai oleh

Diisi setelah fitur berbagi bersasaran diimplementasikan. Fase 1 hanya membuat
tabelnya; jalur tulisnya belum ada.
