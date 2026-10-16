# DDL-2026-036 — Tanggal obsolete pada versi

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-036-sop-versi-tanggal-obsolete` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Isi

```text
N_SOP_Item_Versi.Tanggal_Obsolete  date NULL
```

## Beda dari tiga tanggal proses sebelumnya

Tanggal Permintaan, Respon, dan Akhir Approval hanya dicapai lewat **satu**
jalur: pemindahan tahap. Obsolete dicapai lewat **dua**, dan yang dominan justru
bukan tombol:

1. pemindahan tahap Filing and Registered → Obsolete — jarang, manual
2. **otomatis**, saat versi lain terbit atau dikembalikan; `SopItemVersionService`
   meng-obsolete-kan versi berlaku sebelumnya

Mencapnya hanya di jalur pertama akan melewatkan hampir seluruh kejadian nyata —
kolomnya tampak berfungsi tetapi hampir selalu kosong. Karena itu pencapan
dipasang di **ketiga titik** (`pindah()` plus dua tempat di
`SopItemVersionService`), masing-masing dengan `whereNull` supaya yang sudah
punya tanggal tak tertimpa.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**.
