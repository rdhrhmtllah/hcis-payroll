# DDL-2026-149-system-panduan-versi

- **Target:** Web_HRIS development & production (SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-149-system-panduan-versi/forward.sql` (sha256 `9753833673c377577503f15aba785f6ae88c9a870ad06c50c436a8136fc6c302`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:**
  - `dbo.N_HRIS_Panduan_Versi` (CREATE TABLE) + `UX_HRIS_Panduan_Versi_No`
  - `dbo.N_HRIS_Panduan`: kolom baru `Id_Versi_Aktif`, `Versi_Aktif_Sejak` + `FK_HRIS_Panduan_Versi_Aktif`
- **Bergantung pada:** `DDL-2026-146-system-panduan-manual`
- **Pemilik:** SYSTEM › Application › Panduan (`PanduanVersiService`)

## Status

`DEV_EXECUTED` 2026-09-29 (Web_HRIS dev): 5 batch OK, idempoten saat diulang;
`PanduanVersiTest` 9 OK, `PanduanTest` 20 OK. SQL Tracker dan production masih `PENDING`.

## Tujuan

Permintaan user 29 Sep 2026: versioning berkas panduan, termasuk tanda "Baru diperbarui"
dan draf sebelum terbit.

- Tiap unggahan berkas = satu versi (`No_Versi` 1, 2, 3, …). Berkas lama **tidak dihapus**.
- `DRAF`: hanya admin yang melihat. Diterbitkan dengan satu klik.
- `TERBIT`: pernah terbit. Yang tampil ke pengguna hanya versi yang ditunjuk
  `N_HRIS_Panduan.Id_Versi_Aktif`. Versi lama bisa dijadikan aktif lagi (rollback).
- `N_HRIS_Panduan.Path_File` / `Nama_File_Asli` / `Ukuran_Byte` / `Versi` tetap
  mencerminkan versi aktif, jadi penyajian berkas ke pengguna tidak berubah.
- `Versi_Aktif_Sejak`: kapan versi aktif mulai berlaku (terbit atau rollback). Pengguna
  melihat tanda **Diperbarui** + catatan perubahan selama
  `PanduanVersiService::HARI_TANDA_BARU` (14) hari, bila panduan punya lebih dari satu
  versi terbit.
- Draf hanya bisa dihapus selama belum pernah terbit; versi terbit disimpan sebagai riwayat.

## Data yang sudah ada

Tidak ada backfill terpisah. Panduan yang dibuat sebelum DDL ini dicatat sebagai versi 1
(terbit, aktif, waktu lama, tanpa tanda "Diperbarui") saat riwayatnya pertama kali dibuka
atau versi barunya diunggah.

## Perilaku bila DDL belum dijalankan

Panduan tetap tampil dan bisa dibuat seperti sebelumnya. Dialog **Versi** menampilkan
"Versioning belum tersedia", dan tanda "Diperbarui" tidak muncul.

## Tugas user

1. Jalankan `forward.sql` lewat SQL Tracker di production **setelah** DDL-2026-146.
