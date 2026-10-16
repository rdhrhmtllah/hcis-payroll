# DDL-2026-166-pa-expected-behaviour-kompetensi

## Metadata

- **Change ID**: `DDL-2026-166-pa-expected-behaviour-kompetensi`
- **Target Database**: `Web_HRIS`
- **Type**: DDL
- **Revision**: `r2`
- **Forward Checksum (SHA-256)**: `939520dc58759b3510b29c5b7e083c54704c53852224dd899461be8543360cc8`
- **Affected Objects**: `N_PA_Competency (Expected_Behaviour)`, `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`

## Deskripsi

Satu kolom pada kamus kompetensi PA:

| Kolom | Tipe | Arti |
| --- | --- | --- |
| `Expected_Behaviour` | `nvarchar(2000) NULL` | Perilaku yang terlihat bila kompetensi terpenuhi, **satu perilaku per baris**. `NULL` = belum ditulis HC. |

`Deskripsi` menjelaskan *apa arti* kompetensi; `Expected_Behaviour` menjelaskan *perilaku
apa yang terlihat* bila kompetensi itu terpenuhi. Diisi HC dari
`/performance-appraisal/master/kompetensi` dan ditampilkan sebagai daftar butir di detail
hasil peserta (admin), Penilaian Saya, detail anggota Penilaian Tim, dan matriks Balancing.

## Kamus, bukan snapshot per siklus

Menurut K26 (README `DDL-2026-067`) kolom yang menyatakan ARTI tinggal di kamus dan
dikoreksi global. Kolom ini tidak menghitung apa pun (tidak masuk `N_PA_Result`, grade,
kuadran HAV, atau kelayakan). Bila kelak dipakai memutuskan sesuatu, ia menjadi aturan dan
wajib pindah ke snapshot per siklus lewat perubahan baru.

## Eksekusi development

- 2026-10-05, `Web_HRIS @ instance-20240809-031237` (diverifikasi lewat `DB_NAME()` + `@@SERVERNAME`).
- Sebelum: kolom tidak ada, ledger kosong, 24 baris kompetensi.
- `forward.sql` 2 batch OK; sesudah: kolom `nvarchar(2000) NULL`, ledger 1 baris, 24 baris kompetensi tidak berubah.
- Dijalankan ulang: no-op.

## Agent completed

- Artefak `database/ddl/DDL-2026-166-pa-expected-behaviour-kompetensi/forward.sql` + `drop.sql` (drop tidak dijalankan).
- Eksekusi dan verifikasi di development (di atas); entri `database/ddl/status.json`.

## User tasks

1. Tinjau `forward.sql` dan catat **persis** isinya di SQL Tracker (checksum di atas).
2. Jalankan ke production lewat SQL Tracker, lalu verifikasi kolom ada di production.

## Amandemen r2 (2026-10-06)

Dinomori ulang dari `DDL-2026-162-pa-expected-behaviour-kompetensi` karena nomornya juga dipakai `DDL-2026-162-sinkron-prod-tabel-kolom` di master. Isi perubahan tidak berubah selain kode change. **Checksum lama `8045994a02bd2b9890fef834920598c1a3c5911aa4ff88110cef8f0d6df12ea6` tidak boleh dicatat di SQL Tracker**; pakai checksum di atas.
