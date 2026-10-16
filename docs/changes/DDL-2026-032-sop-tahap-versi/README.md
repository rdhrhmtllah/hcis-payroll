# DDL-2026-032 — Tahap proses versi dokumen SOP

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-032-sop-tahap-versi` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-032-sop-tahap-versi/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-032-sop-tahap-versi/drop.sql` |

## Isi

```text
N_SOP_Master_Tahap_Versi            8 kolom pengatur + audit
N_SOP_Master_Tahap_Versi_Transisi   pasangan (Dari, Ke) yang diizinkan
N_SOP_Item_Versi.Tahap_Id           int NULL, FK ke master tahap
```

## Masalah yang diobati

Seluruh versi yang belum terbit hanya bernama `DRAFT`. Tak ada bedanya antara
"baru diketik", "sedang direview", "menunggu tanda tangan", dan "ditahan" —
padahal keempatnya menuntut tindakan yang berbeda dari orang yang berbeda.

Tiga puluh dari 75 versi memang sudah lahir tanpa berkas, jadi yang hilang bukan
kemampuan menyiapkan versi lebih dulu, melainkan **tahapannya**.

## Melapisi, bukan mengganti

`N_SOP_Item_Versi.Status` dibaca rilis paket, progres karyawan, monitoring, dan
ekspor. Mengganti isinya dengan delapan nilai baru memaksa 40-an pembaca berubah
serentak, dan yang terlewat satu pun akan diam-diam salah membaca keadaan versi.

Karena itu `Status` **tetap** empat nilai teknis, dan pemetaan tahap ke Status
disimpan sebagai **data** di kolom `Status_Teknis`:

| Tahap | `Status_Teknis` |
|---|---|
| Drafting, Review, Approval, Hold, Cancel, Approved | `DRAFT` |
| Filing and Registered | `PUBLISHED` |
| Obsolete | `OBSOLETE` |

Admin bisa menambah tahap tanpa satu baris kode berubah, selama tahap itu
menyatakan berdiri di atas Status yang mana.

## `Status_Teknis` dibatasi CHECK constraint yang sudah ada

`CK_N_SOP_Versi_Status` hanya mengenal `DRAFT`, `PUBLISHED`, dan `OBSOLETE`.
Rancangan awal memetakan Approved ke `READY` — itu akan meledak sebagai
pelanggaran constraint pada perpindahan pertama, bukan sebagai pesan yang bisa
dibaca admin. Ada tes yang menjaga hal ini
(`test_status_teknis_tak_pernah_melanggar_check_constraint`).

`READY` sendiri hanya punya arti pada salinan kerja paket terbit
(`N_SOP_Paket_Draft_Item_Versi`), yang tak dijaga constraint itu.

## Syarat masuk tahap juga data

| Kolom | Arti |
|---|---|
| `Butuh_Berkas` | tahap ini menuntut isi versi sudah dilampirkan |
| `Butuh_Pengesahan` | tahap ini menuntut blok pengesah terisi |
| `Is_Terminal` | tahap akhir; tak punya transisi keluar |
| `Nonaktifkan_Versi` | masuk tahap ini menonaktifkan versinya (dipakai Cancel) |

Inilah yang menegakkan aturan bisnisnya: **Filing and Registered mustahil
dimasuki sebelum berkasnya terunggah**, sementara Drafting sampai Approval bebas
tanpa berkas sama sekali.

## Transisi sebagai tabel

Alur persetujuan berubah lebih sering daripada perangkat lunaknya. Menuliskan
"Review hanya boleh ke Approval" di dalam kode berarti setiap perubahan
kebijakan menunggu rilis.

## `Tahap_Id` nullable, dan tetap nullable

Tujuh puluh lima versi lahir sebelum kolom ini ada. Yang kosong dibaca sebagai
tahap **turunan dari Status**-nya (`SopTahapVersiService::tahapVersi`), sehingga
tak ada versi yang mendadak kehilangan keadaan hanya karena backfill belum
menyentuhnya — termasuk versi yang lahir dari jalur lain setelah rilis ini.

## Eksekusi

`Web_HRIS` (development): **9 batch OK**. Dua tabel, satu kolom, satu foreign
key, tiga indeks.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**. Status teknis selamat, tetapi
riwayat "siapa menahan di Approval berapa lama" hilang tanpa sisa.
