# DML-2026-024 — Seed 13 kategori APQC beserta pemetaan tipe item

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-024-seed-sop-apqc` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-024-seed-sop-apqc/data-change.sql` |
| Prasyarat | `DDL-2026-023-sop-master-apqc` sudah dijalankan |

## Isi

Tiga belas kategori APQC PCF tingkat pertama, `01` sampai `13`.

**Satu ejaan diperbaiki dari daftar asal**, atas konfirmasi pengguna:

```text
asal      : 08  "Managet Informatioin Technology"
disimpan  : 08  "Manage Information Technology"
```

Nilai ini dikutip di dokumen dan audit, jadi ejaannya dibetulkan sejak seed
pertama — mengubahnya nanti berarti mengubah nilai yang sudah terlanjur dirujuk.

## Pemetaan tipe item

Default: setiap APQC dipetakan ke `DOKUMEN`, `FORM`, dan `VIDEO` — tiga tipe
yang membawa isi terkendali. `KUIS` sengaja tidak dipetakan, dengan alasan yang
sama seperti pada jenis dokumen: ia alat verifikasi pemahaman, bukan dokumen
yang memetakan proses.

Ini **default, bukan aturan tetap**. Admin menyesuaikannya lewat halaman Master
APQC, dan karena kewajiban mengisi APQC diturunkan dari pemetaan ini, mengubah
pemetaan berarti mengubah kewajiban — tanpa rilis.

## Dampak yang diperkirakan dan yang terjadi

| Tabel | Perkiraan | Hasil |
|---|---|---|
| `N_SOP_Master_APQC` | 13 baris | 13 baris |
| `N_SOP_Master_APQC_Tipe_Item` | 39 baris (13 × 3) | 39 baris |
| `N_SYSTEM_Database_Change` | 2 baris | 2 baris |

Idempoten. Tidak ada baris lama yang di-update atau dihapus.

## Sisa pekerjaan pengguna

1. Tinjau pemetaan default — terutama apakah FORM dan VIDEO memang layak
   memetakan proses APQC di organisasi Anda.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
