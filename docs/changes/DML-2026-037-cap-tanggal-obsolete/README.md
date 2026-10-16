# DML-2026-037 — Cap dan backfill tanggal obsolete

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-037-cap-tanggal-obsolete` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Isi

1. Tahap `OBSOLETE` memperoleh `Cap_Tanggal = 'Tanggal_Obsolete'`.
2. Backfill 16 versi yang sudah berstatus OBSOLETE.

## Backfill diturunkan, bukan ditebak

Sebuah versi digantikan tepat ketika penerusnya terbit. Karena itu tanggalnya
adalah `Tanggal_Publish` versi berikutnya pada dokumen yang sama — yang paling
awal di antara para penerusnya.

| | Jumlah |
|---|---|
| Terisi dari tanggal terbit penerus | **14** |
| Sengaja dibiarkan kosong | **2** |

Dua yang kosong tak punya penerus yang pernah terbit. Mengisinya dengan tanggal
hari ini akan menuliskan peristiwa yang tak pernah terjadi pada tanggal itu.

## Rollback

Tidak ada. Mencabut aturan pencapan dilakukan dengan mengosongkan `Cap_Tanggal`
pada tahap Obsolete; tanggal yang sudah tercap tetap sah.
