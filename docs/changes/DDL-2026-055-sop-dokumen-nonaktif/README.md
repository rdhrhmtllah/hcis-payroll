# DDL-2026-055 — Jejak Penonaktifan Dokumen

**Target development:** `Web_HRIS` @ team311 · **Status:** PENDING · SQL Tracker PENDING · Production PENDING

## Alasan Perubahan

Menonaktifkan dokumen yang sudah tersebar sebelumnya **dilarang**, bukan sekadar
tidak diperingatkan. `SopAdminDokumenController::destroy()` menolak dengan 422
begitu ada satu item paket aktif yang memakainya, dan layarnya mematikan
tombolnya. Alasan larangan itu tertulis di kodenya: item paket membaca judul dan
nomor dokumen dari `N_SOP_Dokumen`, sehingga dokumen yang mati meninggalkan item
tanpa nama di layar karyawan.

Keputusan produk 2026-08-27 mengubahnya: dokumen **boleh** dinonaktifkan kapan
pun, tetapi penonaktifan hanya menutup **pemakaian baru**. Paket yang sudah
berjalan tetap berjalan sampai adminnya melepas sendiri. Admin harus melihat
angka sebarannya sebelum menekan, wajib meninggalkan catatan, dan jejaknya harus
bisa dibaca kembali berikut kondisi sebaran pada saat itu.

## Kenapa kolom baru, bukan `Flag_Aktif`

`Flag_Aktif` dokumen ditanyakan **28 kueri**. Tiga di antaranya jalur baca
karyawan:

| Berkas | Baris |
|---|---|
| `app/Services/LMS/SOP/SopProgressReadService.php` | 1639 |
| `app/Services/LMS/SOP/SopRepositoryReadService.php` | 550 |
| `app/Services/LMS/SOP/SopJangkauanPublikService.php` | 71 |

Membalik `Flag_Aktif` mencabut dokumen dari paket yang sedang berjalan — persis
kebalikan dari keputusan produknya. Menyunting ketiga pembaca itu mungkin, tetapi
setiap suntingan adalah satu kesempatan dokumen mati bocor ke tempat yang salah,
dan modul ini punya sejarah aturan yang hanya terpasang di satu dari dua pintu.

Dengan `Nonaktif_At` sebagai kolom tersendiri, **tak satu pun pembaca berubah**.
Yang berubah hanya pemilih dokumen di Document Builder dan daftar Master Dokumen.

Pola ini bukan hal baru di modul ini. Pemilih dokumen di Builder sudah memakainya
untuk tipe yang dimatikan, dengan komentar: *"memasang item baru ADALAH
pembuatan, jadi ia ikut tertutup. Dokumen lamanya tidak hilang ke mana-mana —
item yang sudah terpasang tetap menyajikannya."*

---

## Lingkup

- Tabel: `N_SOP_Dokumen`
- Kolom baru: `Nonaktif_At`, `Nonaktif_By`, `Nonaktif_Catatan`, `Nonaktif_Paket`, `Nonaktif_Orang`
- Indeks baru: `IX_N_SOP_Dokumen_Nonaktif_At` (terfilter, `WHERE Nonaktif_At IS NOT NULL`)
- Tanpa DML. Baris yang sudah `Flag_Aktif = 'T'` dari jalur lama **sengaja tidak
  disentuh**: semuanya dinonaktifkan sewaktu belum dipakai, jadi tak ada jejak
  sebaran yang bisa dipulihkan secara jujur.

### Kenapa `Nonaktif_Paket` & `Nonaktif_Orang` disimpan

Keduanya cuplikan, bukan turunan. Justru karena paket tetap berjalan sesudah
dokumen dinonaktifkan, angkanya berubah terus; "kondisi terakhir saat
dinonaktifkan" hanya bisa benar bila dibekukan pada detik itu.

### Kenapa `Nonaktif_Catatan` NULL-able

Semata-mata karena baris lama tak punya catatan. Aplikasi mewajibkannya untuk
setiap penonaktifan baru. `NOT NULL` di tingkat skema menuntut nilai bohongan
bagi seluruh baris yang sudah ada, dan nilai bohongan tak bisa dibedakan dari
catatan sungguhan.

---

## Eksekusi & Verifikasi Development

Belum dijalankan. Menunggu persetujuan user.

Verifikasi sesudah dijalankan:

```sql
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME LIKE 'Nonaktif[_]%';
-- harap: 5 baris, semuanya IS_NULLABLE = YES

SELECT name, has_filter, filter_definition
FROM sys.indexes
WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'IX_N_SOP_Dokumen_Nonaktif_At';
-- harap: 1 baris, has_filter = 1
```

## Tugas User Selanjutnya

1. Review `database/ddl/DDL-2026-055-sop-dokumen-nonaktif/forward.sql`.
2. Setujui eksekusi ke development (`Web_HRIS`).
3. Daftarkan forward DDL ke SQL Tracker, lalu jalankan ke staging/production.
4. Verifikasi lewat kueri di atas, dan lewat layar: nonaktifkan satu dokumen uji
   yang sedang dipakai paket, lalu pastikan karyawan di paket itu **masih**
   melihat dokumennya di Functional Document.
