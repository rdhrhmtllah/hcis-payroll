# DML-2026-035 — Aturan pencapan tanggal per tahap

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-035-cap-tanggal-tahap` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-035-cap-tanggal-tahap/data-change.sql` |

## Isi

| Tahap | Mencap |
|---|---|
| Review | `Tanggal_Permintaan` |
| Approval | `Tanggal_Respon` |
| Approved | `Tanggal_Akhir_Approval` |

Dicap **saat memasuki** tahapnya, dan **hanya bila kolomnya masih kosong**.

## Tahap lain sengaja tak mencap apa pun

- **Drafting** adalah titik awal, bukan peristiwa.
- **Hold**, **Cancel**, dan **Obsolete** adalah keadaan, bukan peristiwa yang
  punya tanggal resmi di dokumen.
- **Filing and Registered** sudah punya `Tanggal_Publish` sendiri, yang ditulis
  jalur terbit. Mencapnya dua kali akan melahirkan dua tanggal untuk satu
  peristiwa.

## Tetap bisa ditimpa admin

Ketiga tanggal itu ikut di form versi dan diterima
`SopAdminDokumenController::aturanVersi()`. Pencapan hanya mengisi yang kosong,
jadi nilai yang diketik admin tak pernah tergerus.

## Rollback

Tidak ada. Mencabut aturan pencapan dilakukan dengan mengosongkan `Cap_Tanggal`
pada tahap yang bersangkutan — tanggal yang sudah tercap di versi tetap sah dan
tak ikut terhapus.
