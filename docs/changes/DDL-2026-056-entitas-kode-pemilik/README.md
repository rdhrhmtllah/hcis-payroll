# DDL-2026-056 — Kode Cetak Entitas sebagai Pemilik

**Target development:** `Web_HRIS` @ team311 · **Status:** PENDING · SQL Tracker PENDING · Production PENDING

## Alasan Perubahan

Nomor dokumen memuat entitas pada **dua posisi dengan arti yang berbeda**:

```
IK / ENB / PROD / EHG / 09 / 03 / 01
     ^pemilik          ^ruang lingkup
```

Keduanya membaca `Kode_Entitas` dari master yang sama, sehingga dokumen milik
holding tercetak `EHG` di kedua posisi. Padahal artinya tak sama:

| Posisi | Arti |
|---|---|
| Pemilik | badan hukum yang bertanggung jawab |
| Ruang lingkup | berlaku di mana — `EHG` berarti **"seluruh entitas"**, dihitung dari `Induk_Entitas_Id`, bukan nama sebuah perusahaan |

Standar penomoran perusahaan memakai **`HOL`** untuk pemilik-holding dan tetap
`EHG` untuk lingkup seluruh-entitas.

## Kenapa bukan baris master baru

Menambah baris `HOL` memecah satu badan hukum menjadi dua:

- **Hirarki rusak.** ENB/EMI/GMN menunjuk `Induk_Entitas_Id = 1` (EHG). Baris
  `HOL` tak punya anak, jadi `Cakup_Semua` tak menganggapnya payung — payungnya
  tetap EHG sementara pemiliknya HOL.
- **Laporan ambigu selamanya.** "Berapa dokumen milik holding" harus menjumlah
  dua baris, dan laporan baru yang lupa menjumlahkannya melapor lebih kecil
  tanpa terlihat salah.
- **Dua baris untuk satu PT pasti menyimpang** saat nama, logo, atau alamatnya
  berubah.

Kolomnya dibuat **umum**, bukan khusus EHG. Biayanya sama, dan bila kelak ada PT
lain yang kode penomorannya berbeda dari kode identitasnya, tak perlu DDL lagi.

---

## Lingkup

- Tabel: `N_HRIS_Master_Entitas`
- Kolom baru: `Kode_Sebagai_Pemilik varchar(10) NULL` — NULL berarti "pakai `Kode_Entitas`"
- DML: mengisi `HOL` pada baris `EHG` saja, dibatalkan bila baris terdampak bukan tepat satu
- Tiga entitas lain dibiarkan NULL; mengisinya dengan nilai yang sama hanya
  menambah tempat yang bisa menyimpang

## Jebakan: tiga perakit, satu aturan

Segmen pemilik dirakit di **tiga berkas terpisah**:

| Tempat | Peran |
|---|---|
| `SopAdminDokumenController::getDataCurrent` | kartu daftar |
| `SopAdminDokumenController::detail` | panel detail |
| `SopDokumenService::segmenNomor` | penyimpanan **dan** pratinjau formulir |

Aturan yang hanya terpasang di sebagian membuat ketiganya menyebut nomor yang
berbeda tanpa satu pun pesan galat. Penutupnya: aturan tinggal di **satu**
tempat, `EntitasService::kodePemilik()`, dan lapisan resolusi menghasilkan dua
field bernama berbeda — `Entitas_Pemilik_Kode` (identitas entitas, dipakai
badge) dan `Entitas_Pemilik_Kode_Nomor` (kode cetak, dipakai perakit). Nama yang
berbeda membuat pemakaian yang keliru terlihat saat dibaca.

Dijaga `tests/Feature/LMS/SOP/SopKodePemilikNomorTest.php`, yang menuntut
ketiganya menghasilkan nomor identik **dan** nomor itu benar-benar memuat kode
pemiliknya — kesepakatan tiga nilai kosong bukan bukti apa pun.

## WAJIB sesudah DML: `php artisan sop:segarkan-nomor`

`Nomor_SOP_Resmi` adalah kolom **tersimpan**, dibaca 45 berkas dan dicerminkan
ke `N_SOP_Paket_Item`. Penulisannya dipicu peristiwa — dokumen lahir, identitas
diubah, versi berlaku berganti — dan **mengubah master tak memicu satu pun dari
ketiganya**. Tanpa penyegaran, dokumen milik holding menyimpan nomor ber-`EHG`
sementara layar merakit nomor ber-`HOL`.

```
php artisan sop:segarkan-nomor --dry-run --entitas=EHG   # lihat dulu
php artisan sop:segarkan-nomor --entitas=EHG             # tulis
```

Perintah itu tak merakit nomor sendiri; seluruhnya lewat
`SopDokumenService::segarkanNomorResmi()`, penulis tunggal yang sama dengan
jalur biasa, berikut penyebarannya ke item paket.

---

## Eksekusi & Verifikasi Development

**Sudah dijalankan** di `Web_HRIS` (2026-08-28): DDL, DML, dan
`sop:segarkan-nomor --entitas=EHG`.

```sql
SELECT Kode_Entitas, Kode_Sebagai_Pemilik FROM N_HRIS_Master_Entitas ORDER BY Urutan;
-- hasil: EHG -> HOL, tiga lainnya NULL
```

Hasil penyegaran nomor: **7 dokumen diperbarui**, 3 dilewati karena identitasnya
belum lengkap (nomor lamanya dibiarkan — 16 dokumen lama menyimpan penomoran
ketik tangan yang masih beredar).

```
PLC/EHG/IA/EHG/01/00/00   ->  PLC/HOL/IA/EHG/01/00/00
IK/EHG/HC/EHG/09/07/00    ->  IK/HOL/HC/EHG/09/07/00
```

Verifikasi terhadap data sungguhan:

| Pemeriksaan | Hasil |
|---|---|
| Dokumen milik holding yang bernomor | 7 |
| Nomor yang memuat `/HOL/` | 7 dari 7 |
| Selisih nomor **tersimpan** vs **terakit** | 0 |
| Item paket yang nomornya beda dari dokumennya | 0 dari 6 |
| Nomor yang segmen lingkupnya bukan `EHG` | 0 |

## Tugas User Selanjutnya

1. Review `forward.sql` dan `database/dml/DDL-2026-056-entitas-kode-pemilik/data-change.sql`.
2. Daftarkan forward DDL + DML ke SQL Tracker, lalu jalankan ke staging/production.
3. **Jalankan `sop:segarkan-nomor` di setiap lingkungan sesudah DML-nya** —
   melewatkannya meninggalkan dua nomor untuk satu dokumen.
4. Periksa apakah ada cetakan/PDF lama yang memakai nomor ber-`EHG` di posisi
   pemilik; nomor itu tak lagi cocok dengan yang di layar.
