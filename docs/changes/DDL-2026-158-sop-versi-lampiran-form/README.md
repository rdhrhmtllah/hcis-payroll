# DDL-2026-158-sop-versi-lampiran-form

- **Target:** Web_HRIS development & production (SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-158-sop-versi-lampiran-form/forward.sql` (sha256 `85616b3cccfb511407ca9de8150bd4ddb478ddf192df130644d734a18f4db1dd`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:** `dbo.N_SOP_Item_Versi_Lampiran` (CREATE TABLE) + `PK_SOP_Item_Versi_Lampiran`, `FK_SOP_Versi_Lampiran_Versi`, `FK_SOP_Versi_Lampiran_Konten`, `IX_SOP_Versi_Lampiran_Versi`
- **Bergantung pada:** tabel `N_SOP_Item_Versi` dan `N_LMS_Master_Konten` (sudah ada)
- **Pemilik:** LMS › SOP › Governance (`SopVersiLampiranService`)
- **Spesifikasi:** `docs/superpowers/specs/2026-10-05-sop-lampiran-form-kosong-design.md`

## Status

`DEV_EXECUTED` 2026-10-05 (Web_HRIS dev) atas persetujuan user: 2 batch OK, idempoten saat
diulang; 9 kolom, PK, 2 FK, 1 indeks terverifikasi. SQL Tracker dan production masih `PENDING`.

## Tujuan

Permintaan user 5 Okt 2026: dokumen bertipe FORM punya **form kosong** untuk diunduh
karyawan, terpisah dari berkas utama versi (contoh pengisian).

- Beberapa form kosong per versi (mis. Word dan Excel), karena itu tabel anak.
- Berkasnya menunjuk Library Konten (`Id_Master_Konten`), sama seperti berkas utama.
- Lampiran yang dilepas dinonaktifkan (`Flag_Aktif = 'T'`), tidak dihapus.

## Perilaku bila DDL belum dijalankan

Kode yang membaca/menulis lampiran akan gagal dengan "Invalid object name" pada halaman
versi dan detail paket. DDL ini wajib dijalankan sebelum kode rilisnya ter-deploy.
