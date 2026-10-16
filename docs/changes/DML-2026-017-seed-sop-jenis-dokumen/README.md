# DML-2026-017 — Seed empat jenis dokumen SOP dan pemetaan tipe item

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-017-seed-sop-jenis-dokumen` |
| Jenis | DML |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-017-seed-sop-jenis-dokumen/data-change.sql` |
| Prasyarat | `DDL-2026-016-sop-jenis-dokumen-kepemilikan` sudah dijalankan |

## Isi

Empat tingkat piramid mutu beserta tipe item yang sah bagi masing-masing.

```text
L1 KEBIJAKAN        -> DOKUMEN
L2 PROSEDUR         -> DOKUMEN
L3 INSTRUKSI_KERJA  -> DOKUMEN, VIDEO
L4 FORMULIR         -> FORM
```

`INSTRUKSI_KERJA` menerima `VIDEO` karena instruksi kerja yang direkam sebagai
video adalah praktik nyata di lantai produksi; menutupnya akan memaksa video
diklasifikasikan salah.

`KUIS` sengaja **tidak** dipetakan ke jenis mana pun. Kuis adalah alat
verifikasi pemahaman, bukan dokumen terkendali, dan memberinya tingkat piramid
akan mengaburkan arti piramid itu sendiri. Konsekuensinya eksplisit dan diuji:
dokumen bertipe `KUIS` tidak wajib berjenis.

## Dampak yang diperkirakan dan yang terjadi

| Tabel | Perkiraan | Hasil |
|---|---|---|
| `N_SOP_Master_Jenis_Dokumen` | 4 baris | 4 baris (Id 1–4) |
| `N_SOP_Master_Jenis_Dokumen_Tipe_Item` | 5 baris | 5 baris |
| `N_SYSTEM_Database_Change` | 2 baris | 2 baris (DDL-2026-016 + DML ini) |

Seluruh pernyataan `IF NOT EXISTS` / `WHERE NOT EXISTS`, jadi idempoten. Tidak
ada baris lama yang di-update atau dihapus.

## Sisa pekerjaan pengguna

1. Review isi seed — terutama pemetaan `INSTRUKSI_KERJA -> VIDEO`, yang adalah
   keputusan bisnis, bukan keputusan teknis.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
