# DDL-2026-039 — Alur berlangkah versi dan syarat generik

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-039-sop-langkah-versi` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Isi

```text
N_SOP_Master_Tahap_Versi.Langkah        int NULL
N_SOP_Master_Tahap_Versi.Kelompok       varchar(60) NULL
N_SOP_Item_Versi_Pengesah.Jenis         varchar(12) NOT NULL DEFAULT 'REALISASI'
N_SOP_Master_Tahap_Versi_Syarat         (Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan)
```

## Masalah

Delapan tahap sudah hidup dan menegakkan sejak DDL-2026-032, tetapi layar
menampilkan satu form panjang yang isinya tak berubah antara Drafting dan
Approval. Tahapnya terasa seperti hiasan.

## Urutan langkah diturunkan dari master

`Langkah`/`Kelompok` menyatakan langkah mana yang diwakili sebuah tahap dan
kelompok field mana yang dibukanya. Tahap baru cukup menyatakan ia duduk di
langkah berapa — tak ada daftar langkah kedua di kode.

## Pengesah tetap satu blok

Kolom `Jenis` sempat disiapkan untuk memisahkan RENCANA/REALISASI, tetapi konsep
rencana terpisah dibatalkan 2026-08-24. Semua baris memakai `REALISASI`, dan
pengisian pengesah hanya ada di langkah Pengesahan. Kolom dipertahankan untuk
kompatibilitas skema, bukan sebagai fitur yang ditampilkan.

## Syarat generik per tahap

Alih-alih menambah kolom `Butuh_X` setiap kali ada jenis syarat baru, satu
tabel generik menampungnya. `Kode_Syarat` disaring daftar putih di service;
kode karangan tidak dianggap terpenuhi.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**. Ia menghapus pemetaan langkah,
tabel syarat, dan kolom kompatibilitas `Jenis`.
