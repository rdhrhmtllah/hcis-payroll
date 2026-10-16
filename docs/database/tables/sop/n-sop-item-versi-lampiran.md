# N_SOP_Item_Versi_Lampiran

Form kosong (lampiran unduhan) per versi dokumen SOP bertipe FORM.

| Bidang | Nilai |
|---|---|
| Modul | LMS / SOP / Governance — Master Dokumen |
| Change ID | `DDL-2026-158-sop-versi-lampiran-form` |
| Forward SQL | `database/ddl/DDL-2026-158-sop-versi-lampiran-form/forward.sql` |
| Service | `app/Services/LMS/SOP/SopVersiLampiranService.php` |

## Tujuan

Berkas utama versi FORM (`N_SOP_Item_Versi.Id_Master_Konten`) adalah **contoh
pengisian**. Tabel ini menyimpan **form kosong** yang diunduh karyawan untuk diisi
sendiri. Opsional, boleh lebih dari satu per versi, dan tidak diwariskan ke revisi baru.

## Kolom

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `Id_Lampiran` | `int IDENTITY` | NO | — | PK. |
| `Id_SOP_Item_Versi` | `int` | NO | — | FK ke `N_SOP_Item_Versi`. |
| `Id_Master_Konten` | `int` | NO | — | FK ke `N_LMS_Master_Konten` (Scope SOP). |
| `Urutan` | `int` | NO | `0` | Urutan tampil, mulai 0. |
| `Flag_Aktif` | `char(1)` | NO | `'Y'` | `'T'` = dilepas admin; barisnya disimpan sebagai jejak. |
| `Created_At` | `datetime2(0)` | NO | `SYSDATETIME()` | |
| `Created_By` | `int` | YES | — | `Id_Users` admin. |
| `Updated_At` | `datetime2(0)` | YES | — | |
| `Updated_By` | `int` | YES | — | |

Indeks: `IX_SOP_Versi_Lampiran_Versi (Id_SOP_Item_Versi, Flag_Aktif, Urutan)`.

## Aturan

- Hanya untuk tipe FORM (`SopTipeItem` blok `lampiran`, opsional).
- Boleh diubah selama versi draft, dan sesudah terbit selama dokumennya belum dijadwalkan
  ke karyawan (`SopVersiKeadaan::FIELD_ISI`). Arsip tidak menerima perubahan.
- Satu konten tak boleh dipasang dua kali pada versi yang sama.
