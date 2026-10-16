# DML-2026-088 — Label header Administrator PA

| | |
|---|---|
| Change ID | `DML-2026-088-label-header-administrator-pa` |
| Jenis | DML · r1 · `TRACKER_PENDING` |
| Pemicu | Permintaan jalur frontend |

## Yang dilakukan

`Header_Label` pada kedelapan halaman master PA: **"Administrator Performance Appraisal" → "Administrator PA"**.

## Kenapa `Header_Code` TIDAK ikut berubah

Yang diubah hanya teks yang dibaca manusia. `Header_Code` (`administrator_pa`) adalah
**kunci pengelompokan** yang dipakai `InertiaShellService` untuk merakit sidebar —
mengubahnya akan memecah kedelapan halaman master menjadi dua header berbeda selama cache
belum dibuang, dan gejalanya (menu yang tiba-tiba muncul dua kali) tidak akan terbaca
sebagai akibat penggantian label.

## Kenapa plafonnya delapan baris, bukan satu

Label didenormalisasi **per baris menu**; tidak ada tabel header terpisah. Satu perubahan
label karena itu menyentuh delapan baris sekaligus.

## Penjaga

Selain menolak baris berlabel lama (`50381`), artefaknya menolak keadaan **terpecah**:
`COUNT(DISTINCT Header_Label)` untuk satu `Header_Code` wajib **1** (`50382`). Label yang
berubah sebagian memecah sidebar menjadi dua kelompok yang tampak seperti dua modul.

## Verifikasi

Dijalankan dua kali pada dev, 1 September 2026 — keduanya OK, 8 baris berlabel baru,
`COUNT(DISTINCT Header_Label) = 1`, cache menu dibuang.
