# DDL-2026-089 — Riwayat kalibrasi bisa mencatat pelepasan

**Modul:** KPI / Performance Appraisal
**Objek:** `N_PA_Calibration_History`
**Status:** dev DONE, tracker PENDING
**Tanggal:** 7 September 2026

## Sebab

`N_PA_Calibration_History` dirancang untuk satu peristiwa: forum menggeser grade seseorang.
Peristiwa itu selalu punya grade sesudah dan selalu punya pelaku manusia, jadi
`Kode_Grade_Sesudah` dan `Performed_By` dibuat `NOT NULL`, dan tidak ada yang salah dengan
itu.

Sekarang ada peristiwa kedua di tabel yang sama. Perhitungan ulang tidak lagi menimpa
keputusan kalibrasi — grade final dan kuadran hasil forum dipertahankan. Kecuali satu
keadaan: bila perhitungan ulang membuat hasilnya **TERTAHAN**. Hasil tertahan tidak punya
grade sama sekali, jadi grade final hasil kalibrasi tidak bisa berdiri di atasnya.
Kalibrasinya dilepas, dan pelepasan itu perlu tercatat.

Dua kolom menolaknya:

| Kolom | Kenapa pelepasan tidak punya nilainya |
| --- | --- |
| `Kode_Grade_Sesudah` | Hasil tertahan tidak punya grade. "Sesudah"-nya memang tidak ada. |
| `Performed_By` | Pemicu tersering adalah kiriman terakhir penilai lewat callback HC Learn, diproses di antrean. Tidak ada sesi HCIS di sana. |

Tiga jalan lain semuanya buruk. Grade karangan (`'-'`, `'TERTAHAN'`) menaruh nilai yang
bukan grade ke dalam kolom grade, dan layar mana pun yang membacanya menampilkannya sebagai
grade. User karangan menaruh nama orang pada tindakan yang tidak ia lakukan. Tidak mencatat
sama sekali berarti grade yang pernah disepakati forum lenyap tanpa satu pun keterangan —
padahal riwayat ini satu-satunya tempat yang menyimpan nilai **sebelumnya**.

## Yang dijalankan

1. `Kode_Grade_Sesudah` → `VARCHAR(40) NULL`
2. `Performed_By` → `INT NULL`
3. `CK_PA_Calibration_History_Pelaku`: `Performed_By IS NOT NULL OR Kode_Grade_Sesudah IS NULL`

Poin ketiga bukan pelengkap. `Performed_By` yang polos NULL-able melemahkan kolom audit yang
justru paling penting: kalibrasi manual yang kehilangan pelakunya karena bug tidak akan
berbunyi sebagai galat, ia hanya menjadi baris tanpa nama yang baru terasa saat ada yang
bertanya siapa menggeser grade seseorang. CHECK-nya mengikat kelonggaran itu pada baris yang
memang pelepasan, sehingga kalibrasi manual tanpa pelaku tetap ditolak basis data persis
seperti sebelum perubahan ini.

Tidak ada baris yang diubah dan tidak ada kolom yang dibuang. Yang berubah hanya bahwa baris
**baru** boleh tidak punya keduanya.

## Yang diperiksa di dev

- 0 baris terdampak — riwayat kalibrasi kosong sesudah DML-2026-109.
- Tiga batch lolos, tiga penjaga lolos: kedua kolom `is_nullable = 1`, CHECK terpasang,
  panjang kolom grade tetap 40.
- `CK_PA_Calibration_Ada_Perubahan` yang sudah ada tidak bentrok. Dengan
  `Kode_Grade_Sesudah` NULL ekspresinya menjadi UNKNOWN, dan CHECK hanya menolak FALSE.

Penjaga panjang kolom ada karena `ALTER COLUMN` yang salah ketik panjangnya memotong data
tanpa peringatan — kesalahan ketik satu angka pada skrip yang benar-benar dijalankan di
production.

## Penyerta di kode

- `PaResultCalculationService::lepaskanKalibrasi()` menulis baris pelepasannya, memotong
  `Alasan` di 1000 karakter (kolom yang kepenuhan menolak seluruh baris), dan mengisi
  `Performed_By_Nama` dengan `Sistem (perhitungan ulang otomatis)` supaya audit tidak
  menandainya `aktor_tidak_diketahui`.
- `PaAuditService::kalibrasi()` sudah memakai `COALESCE(Kode_Grade_Sesudah, '-')`, jadi
  barisnya terbaca "Grade B+ menjadi -" tanpa perubahan apa pun.
- Saringan `hanya_keputusan_manusia` mengecualikannya lewat `whereNotNull('aktor_id')`.

## Pembalikan

`drop.sql` ada sebagai dokumentasi reset manual dan **berhenti dengan pesan** bila sudah ada
baris pelepasan. Membersihkan jalannya sendiri berarti membuang catatan bahwa kalibrasi
seseorang pernah dilepas dan kenapa; nasib baris-baris itu keputusan manusia, di luar skrip.
