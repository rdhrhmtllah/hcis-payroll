# DDL-2026-022 — Kolom logo pada master entitas

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-022-hris-entitas-logo` |
| Jenis | DDL |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-022-hris-entitas-logo/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-022-hris-entitas-logo/drop.sql` |

## Isi

```text
N_HRIS_Master_Entitas.Logo_Path  varchar(255) NULL
```

Satu kolom, ditambahkan. Tidak ada yang dihapus atau ditimpa.

## Kenapa satu kolom untuk dua bentuk

`Logo_Path` menampung **path GCS hasil unggah** maupun **URL/path publik yang
sudah ada**. Pembacaannya (`EntitasService::logoUrl`) meneruskan apa pun yang
sudah berupa `http(s)://` atau `/...` apa adanya, dan menandatangani sisanya
sebagai objek GCS.

Polanya menyalin `SopAdminBuilderController::resolveCover()`, bukan menciptakan
jalur unggah kedua dengan aturannya sendiri.

Dua bentuk dalam satu kolom juga yang membuat entitas tetap bisa berlogo di
environment yang kredensial GCS-nya belum siap — nyata di server dev ini, yang
path kuncinya bergaya Windows dijalankan di Linux.

## Kegagalan tanda tangan tidak dilempar

`logoUrl()` menangkap kegagalan penandatanganan dan mengembalikan `null` sambil
menulis peringatan. Membiarkannya naik akan menjatuhkan seluruh halaman master
hanya karena satu logo tak bisa ditampilkan; layar kemudian jatuh ke badge
singkatan di atas warna merek.

## Hasil eksekusi development

Dijalankan 2026-08-22 terhadap `Web_HRIS`, 1 batch OK. Kolom terverifikasi
`varchar(255)`, nullable. Empat baris entitas tidak tersentuh.

## Batas verifikasi yang jujur

Jalur **unggah ke GCS belum terbukti end-to-end di development**, karena
kredensial GCS di server ini rusak (path kunci bergaya Windows di Linux) — sama
sebabnya dengan tiga tes SOP yang sudah lama gagal 500. Yang sudah terbukti:
kolomnya, validasinya, jalur URL-langsung, penanganan kegagalan tanda tangan,
dan tampilannya di layar. Jalur unggah baru bisa diverifikasi setelah kredensial
GCS dibereskan.

## Sisa pekerjaan pengguna

1. Perbaiki kredensial GCS di development bila jalur unggah ingin diuji di sini.
2. Review forward DDL, catat ke SQL Tracker, jalankan ke production, verifikasi.
