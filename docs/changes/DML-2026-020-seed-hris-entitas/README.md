# DML-2026-020 — Seed empat entitas perusahaan

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-020-seed-hris-entitas` |
| Jenis | DML |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-020-seed-hris-entitas/data-change.sql` |
| Checksum | `859da14892605dec23e3f2a343cbfc6a3b4666e803a7d1a35ccf4f7ce70f96cf` |
| Prasyarat | `DDL-2026-019-hris-master-entitas` sudah dijalankan |

## Isi

```text
EHG   Evo Holding / Group              #4338CA   payung, tanpa induk
ENB   PT Evo Nusa Bersaudara           #1D4ED8   induk EHG
EMI   PT Evo Manufacturing Indonesia   #047857   induk EHG
GMN   PT Graha Maju Nusantara          #B45309   induk EHG
```

Payung disisipkan **lebih dulu** karena tiga PT di bawahnya menunjuknya lewat
`Induk_Entitas_Id`, dan foreign key menolak induk yang belum ada.

Warnanya sengaja berjauhan di roda warna supaya badge tetap terbedakan pada
layar kecil dan pada cetakan abu-abu. Nilai ini adalah tebakan awal yang aman —
warna merek resmi tiap PT sebaiknya dikonfirmasi dan disesuaikan.

## Dampak yang diperkirakan dan yang terjadi

| Tabel | Perkiraan | Hasil |
|---|---|---|
| `N_HRIS_Master_Entitas` | 4 baris | 4 baris (Id 1–4) |
| `N_SYSTEM_Database_Change` | 2 baris | 2 baris (DDL-2026-019 + DML ini) |

Idempoten. Tidak ada baris lama yang di-update atau dihapus.

## Sisa pekerjaan pengguna

1. Konfirmasi warna merek tiap entitas — nilai sekarang dipilih agar kontras,
   bukan diambil dari panduan merek.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
