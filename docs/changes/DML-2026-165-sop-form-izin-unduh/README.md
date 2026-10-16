# DML-2026-165 — Item FORM yang sudah ada tetap boleh unduh contoh pengisian

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-165-sop-form-izin-unduh` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Artifact | `database/dml/DML-2026-165-sop-form-izin-unduh/data-change.sql` (sha256 `3bbf6b7490ee67c809f50aa8a8f694be4d58323c60d71fe2a0863d8da810d480`) |
| Bergantung pada | Branch `feat/sop/form-contoh-pengisian-pdf` |
| Status | `REVIEW_REQUIRED` |

## Latar

Dokumen FORM punya contoh pengisian (berkas utama) dan form kosong (lampiran,
DDL-2026-158). Sampai 10 Okt 2026 contoh pengisian selalu bisa diunduh:
`SopAksesBerkas::bolehUnduh()` mengecualikan FORM dari `Mode_Akses`, dan wizard
tak pernah menanyakan kolom itu untuk FORM sehingga setiap item FORM tersimpan
`VIEW_ONLY`.

Mulai rilis branch di atas, contoh pengisian FORM wajib PDF dan izin unduhnya
mengikuti `Mode_Akses` item paket. Contoh non-PDF warisan tetap selalu boleh
diunduh, dan form kosong tidak berubah. Rancangan:
`docs/superpowers/specs/2026-10-10-sop-form-contoh-pengisian-pdf-design.md`.

## Cakupan (keputusan user, 10 Okt 2026)

Item FORM yang sudah ada **tetap boleh unduh**. Item FORM baru default
"Hanya lihat".

```text
N_SOP_Paket_Item        Tipe_Item='FORM' → Mode_Akses='VIEW_UNDUH', Flag_Watermark='T'
N_SOP_Paket_Draft_Item  Tipe_Item='FORM' → sama (draft yang terbit kemudian
                        akan menimpa item dengan nilainya sendiri)
```

`Flag_Watermark='T'` mengikuti `SopAksesBerkas::watermarkTersimpan()`: berkas
yang boleh diunduh tidak ber-tanda-air.

Terukur di development (baca saja, 10 Okt 2026): 1 baris di masing-masing tabel.

## Urutan rilis

**Jalankan di production SEBELUM PR kodenya di-merge.** Kalau kode rilis lebih
dulu, seluruh contoh pengisian PDF yang sudah beredar sempat menjadi "hanya
lihat" sampai DML ini dijalankan. Menjalankan DML lebih dulu aman: kode lama
memang sudah mengizinkan FORM diunduh apa pun `Mode_Akses`-nya.

## Idempotensi dan verifikasi

Hanya baris FORM yang belum (`VIEW_UNDUH`, `'T'`) yang diubah; menjalankan ulang
mengubah 0 baris. Kueri verifikasi ada di akhir `data-change.sql` dan harus
mengembalikan 0 untuk kedua tabel.

## Rollback

Tidak disediakan. Nilai lama seragam `VIEW_ONLY` dan tidak pernah dibaca untuk
FORM, jadi tidak ada informasi yang hilang. Kalau perlu, admin bisa
mengembalikan izin per item dari Builder paket.

## Pekerjaan

| Siapa | Apa | Status |
|---|---|---|
| Agent | Tulis SQL, dokumentasi, entri `status.json` | Selesai |
| User | Setujui SQL | Menunggu |
| Agent | Jalankan di `Web_HRIS` (development) sesudah disetujui | Menunggu |
| User | Rekam di SQL Tracker, jalankan di production sebelum merge | Menunggu |
