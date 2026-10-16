# DDL-2026-062 — Nomor versi unik hanya di antara versi yang hidup

| | |
|---|---|
| Change ID | `DDL-2026-062-versi-unik-hanya-yang-hidup` |
| Jenis | DDL (indeks; tanpa perubahan kolom, tanpa DML) |
| Target | `Web_HRIS` (development) |
| Status | **DEV DONE** — dijalankan 30 Agu 2026 atas izin eksplisit user untuk menjatuhkan indeks (aturan proyek biasanya melarang agen mengeksekusi DROP). SQL Tracker & produksi masih `PENDING`. |
| Forward | `database/ddl/DDL-2026-062-versi-unik-hanya-yang-hidup/forward.sql` |
| Drop | `database/ddl/DDL-2026-062-versi-unik-hanya-yang-hidup/drop.sql` (pemulihan manual) |
| Rancangan | `docs/superpowers/specs/2026-08-30-sop-versi-batal-beku-dan-nomor-dipakai-ulang-design.md` |

## Kenapa

Draft versi yang **dibatalkan** ikut mengunci nomornya selamanya. Bila v01
dibatalkan, versi berikutnya terpaksa bernomor v02 — dan nomor resmi dokumen
melompat dari `…/00` ke `…/02`, menyebut "Revisi 01" yang tak pernah diterbitkan
kepada siapa pun.

Dalam tata kelola dokumen, nomor revisi dikonsumsi saat revisi **diterbitkan**,
bukan saat draftnya dibuat. Draft yang batal tak pernah menjadi revisi.

## Yang berubah

```
DARI  UX_N_SOP_Item_Versi_Dokumen_Urut
      UNIQUE (Id_SOP_Dokumen, Versi_Ke)
      WHERE Id_SOP_Dokumen IS NOT NULL

JADI  UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup
      UNIQUE (Id_SOP_Dokumen, Versi_Ke)
      WHERE Id_SOP_Dokumen IS NOT NULL AND Flag_Aktif = 'Y'
```

Tidak ada baris data yang diubah.

## Kenapa `Flag_Aktif`, bukan "tahapnya CANCEL"

Indeks terfilter SQL Server tak boleh menengok tabel lain, jadi predikatnya
wajib satu kolom pada tabel yang sama.

`Flag_Aktif` tepat karena **hanya tahap CANCEL** yang punya
`Nonaktifkan_Versi = 'Y'` — satu-satunya tahap yang mematikan versinya.
Diverifikasi pada dev 30 Agu 2026:

```
CANCEL              Nonaktifkan_Versi = Y     ← satu-satunya
OBSOLETE            Nonaktifkan_Versi = T     ← versi lama tetap Flag_Aktif='Y'
DRAFTING/REVIEW/APPROVAL/APPROVED/FILING_REGISTERED  = T
```

Sehingga nomor yang **pernah benar-benar terbit** terkunci selamanya, walau
versinya sudah tergantikan. Memang harus: ia sudah beredar.

Ketepatan itu adalah kebetulan yang bisa bergeser. Dikunci uji invarian di
suite (hanya `CANCEL` yang boleh `Nonaktifkan_Versi='Y'`); menambah tahap kedua
yang mematikan versi akan menyalakan uji itu merah **sebelum** nomor mulai bocor.

## Urutan eksekusi

Indeks baru **dibuat lebih dulu**, baru yang lama dijatuhkan. Urutan sebaliknya
membuka jendela — sekecil apa pun — ketika tabel tak punya penjaga kembar sama
sekali.

Di kepala skrip ada penjaga yang menolak jalan bila sudah ada nomor kembar di
antara versi hidup, lengkap dengan daftar barisnya. Tanpa itu, kegagalannya
muncul sebagai galat 1505 yang tak menyebut baris mana.

## Pasangan perubahan kode

Sisi aplikasi sudah menyesuaikan — dijalankan bersamaan, bukan menunggu:

- `SopDokumenService::nomorVersiBerikut()` — `MAX(Versi_Ke)` hanya atas versi
  `Flag_Aktif='Y'`; jalur nomor manual memakai saringan yang sama.
- `SopDokumenService::ubahVersi()` — pemeriksaan bentrok nomor ikut menyaring
  versi hidup. Tanpa ini nomor yang sudah dibebaskan indeks tetap ditolak layar.
- `SopDokumenService::dibatalkan()` + penjaga arsip di `ubahVersi`,
  `hapusVersi`, dan `simpanReviewVersi`.

**Kalau DDL dijalankan tanpa kode**: aman, tapi tak ada efeknya — aplikasi tetap
menghitung nomor dari semua versi.
**Kalau kode dijalankan tanpa DDL**: aplikasi menawarkan nomor 01, lalu basis
data menolaknya dengan galat 2601. Jalankan DDL-nya.

## Hasil eksekusi (dev, 30 Agu 2026)

Lima belas batch, semuanya OK. Verifikasi sesudahnya:

```
INDEKS UNIK N_SOP_Item_Versi
  UX_N_SOP_Item_Versi_Satu_Published        ([Id_SOP_Dokumen] IS NOT NULL AND [Status]='PUBLISHED')
  UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup    ([Id_SOP_Dokumen] IS NOT NULL AND [Flag_Aktif]='Y')
  (UX_N_SOP_Item_Versi_Dokumen_Urut sudah tiada)

FK KE N_SOP_Item_Versi  — dari 2 menjadi 5
  N_SOP_Dokumen      FK_N_SOP_Dokumen_Versi_Aktif
  N_SOP_Paket_Item   FK_N_SOP_Paket_Item_Versi_Aktif
  N_SOP_Progress     FK_N_SOP_Progress_Versi

N_SOP_Dokumen.Id_Versi_Aktif  : bigint -> int
IX_N_SOP_Dokumen_Tipe         : kunci=(Flag_Aktif, Tipe) sertaan=(Judul, Nomor_SOP_Resmi, Id_Versi_Aktif)
                                — bentuknya SAMA PERSIS dengan sebelum dijatuhkan
```

Penjaganya dibuktikan menggigit, bukan sekadar terpasang:

```
dokumen 17054 (v00 terbit, v01 dibatalkan)
  versi baru lahir bernomor        : v01   ← sebelumnya v02
  PUBLISHED kedua                  : DITOLAK  UX_..._Satu_Published
  pointer ke versi yang tak ada    : DITOLAK  FK_N_SOP_Dokumen_Versi_Aktif
```

Suite `SopVersiBatalBekuTest` + `SopMasterDokumenPageTest` + `SopWizardHalamanTest`:
**53 lulus, 5 dilewati, 0 gagal.**

## Sisa pekerjaan user

1. Catat ke SQL Tracker, isi `SQL_Tracker_Reference`, ubah `Status_SQL_Tracker`
   menjadi `DONE`.
2. Produksi: jalankan `forward.sql` di sana. **Periksa dulu** bentuk
   `IX_N_SOP_Dokumen_Tipe` di produksi — bila berbeda dari yang tertulis di
   skrip, salin bentuk yang di sana, jangan yang di sini.
3. Perbarui `Status_Production` dan `Status_Verification`.
