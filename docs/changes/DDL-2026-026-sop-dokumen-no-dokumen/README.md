# DDL-2026-026 — Nomor urut dokumen pada master SOP

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-026-sop-dokumen-no-dokumen` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-026-sop-dokumen-no-dokumen/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-026-sop-dokumen-no-dokumen/drop.sql` |

## Isi

```text
N_SOP_Dokumen.No_Dokumen  int NULL
```

## BUKAN nomor versi

Nomor versi bergerak sepanjang siklus revisi di `N_SOP_Item_Versi`. Nomor
dokumen ditetapkan sekali di master dan **tidak ikut naik** saat versi baru
terbit — itu keputusan pemilik, dan diuji.

## Disimpan INT, ditampilkan dua digit

Alasannya sudah tertulis di `resources/js/Pages/LMS/SOP/Shared/support/versi.js`
untuk nomor versi, dan berlaku sama di sini: sebagai teks, `"10"` lebih kecil
daripada `"9"` secara leksikal, sehingga `MAX()` — yang justru dipakai menghitung
saran nomor berikutnya — ikut salah. Dua digit adalah urusan tampilan.

Karena keduanya kini tampil berdampingan, pemformatannya dipisah menjadi satu
fungsi bersama di berkas itu: dua aturan format untuk dua nomor yang bersebelahan
pasti menyimpang, dan yang menyimpang diam-diam adalah yang jarang diperhatikan.

## Deret per kombinasi lima field

Nomor berjalan per kombinasi **Jenis Dokumen + Entitas pemilik + Departemen
pemilik + Ruang lingkup + Proses APQC**. Kombinasi berbeda memulai deretnya
sendiri dari 00.

Konsekuensi yang disadari: dengan lingkup seketat ini, sebagian besar dokumen
akan menjadi yang pertama di kombinasinya dan bernomor 00. Itu perilaku yang
diminta, bukan gejala.

Empat puluh dokumen lama seluruh field kombinasinya masih kosong, jadi mereka
berada di satu grup bersama dan menomori diri berurutan begitu mulai disunting.

## Saran nomor: MAX + 1, termasuk yang nonaktif

Sengaja bukan "nomor bebas terkecil". Mendaur ulang nomor milik dokumen yang
dinonaktifkan membuat dua dokumen pernah menyandang nomor yang sama — dan yang
sudah tercetak di luar sistem tidak ikut berubah.

## Tanpa unique index, dan itu disengaja

Empat dari lima kolom pembentuk grup nullable, sedangkan SQL Server
memperlakukan NULL sebagai nilai yang sama di unique index — 40 dokumen lama
yang kelimanya kosong akan saling bertabrakan sejak hari pertama. Penjagaannya
di backend (`pastikanNoDokumenUnik`), mengikuti pola `pastikanNomorUnik` untuk
`Nomor_SOP_Resmi` yang juga tak punya constraint di skema.

Tidak ada indeks pencarian yang ditambahkan: tabelnya 42 baris, dan kueri
`MAX(No_Dokumen)` dengan kesamaan pada lima kolom tidak akan menjadi masalah
pada skala itu. Indeks ditambahkan saat ada bukti butuh, bukan secara mekanis.

## Hasil eksekusi development

Dijalankan 2026-08-22 terhadap `Web_HRIS`, 1 batch OK. Kolom `int` nullable
terbentuk; 42 baris dokumen tidak tersentuh.

## Pengujian

`tests/Feature/LMS/SOP/SopNoDokumenTest.php` — 11 tes, termasuk: deret per
kombinasi, nomor kustom dihormati, kembar ditolak dalam kombinasi yang sama tapi
diperbolehkan lintas kombinasi, dokumen nonaktif tetap dihitung, grup
"belum diklasifikasi" menemukan anggotanya sendiri (`whereNull`, bukan `= null`),
memindahkan kombinasi memeriksa kembar di kombinasi TUJUAN, dan nomor tidak naik
saat versi baru terbit.

## Yang sengaja tidak dikerjakan

Blok "Dokumen Baru" di Builder **tidak** mendapat isian No. Dokumen. Dokumen yang
lahir dari sana tetap mendapat nomor otomatis dari server; menambah isian
kedelapan ke blok yang sudah panjang tidak sepadan sampai ada yang benar-benar
meminta menomori manual dari Builder.

## Sisa pekerjaan pengguna

1. Review forward DDL.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
