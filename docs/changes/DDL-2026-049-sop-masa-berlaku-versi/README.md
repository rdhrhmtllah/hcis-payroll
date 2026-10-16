# DDL-2026-049 — Masa berlaku dan jadwal review versi SOP

| | |
|---|---|
| Change ID | `DDL-2026-049-sop-masa-berlaku-versi` |
| Jenis | DDL |
| Target development | `Web_HRIS` (host team311) |
| Status | `DEV_EXECUTED` — SQL Tracker & produksi masih `PENDING` |
| Checksum forward (sha256) | `57b548f6f4240338290c4df360df5fa65f127388d3207114690c1e2b217dfb8a` |
| Dieksekusi ke development | 2026-08-27 |

## Path

- Forward: `database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql`
- Drop (manual, **jangan dijalankan agen**): `database/ddl/DDL-2026-049-sop-masa-berlaku-versi/drop.sql`
- DML pendamping: `database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql`
- Desain: `plans/sop/2026-08-27-masa-berlaku-versi-design.md`
- Rencana: `plans/sop/2026-08-27-masa-berlaku-versi-plan.md`

## Objek terdampak

| Tabel | Kolom | Tipe | Null |
|---|---|---|---|
| `N_SOP_Item_Versi` | `Tanggal_Review_Berikutnya` | `date` | YES |
| `N_SOP_Master_Jenis_Dokumen` | `Masa_Berlaku_Bulan` | `int` | YES |
| `N_SOP_Master_Jenis_Dokumen` | `Siklus_Review_Bulan` | `int` | YES |
| `N_SYSTEM_Database_Change` | 1 baris pencatatan | — | — |

## Kenapa

Langkah Pemberkasan pada wizard versi hanya meminta `Tanggal_Berlaku`. Sebuah
versi bisa terbit tanpa pernah menyatakan sampai kapan ia berlaku dan kapan ia
ditinjau ulang — dan karena tak ada tanggalnya, tak ada satu pun mekanisme yang
bisa mengingatkan siapa pun.

`Tanggal_Kadaluarsa` sudah ada sejak lama di `N_SOP_Item_Versi` dan **tidak
diduplikasi**; ia hanya dimunculkan kembali lewat validasi dan form (lihat
DML-2026-050 dan desainnya). Yang benar-benar baru hanya jadwal review.

### Kenapa `Tanggal_Review_Berikutnya`, bukan `Tanggal_Review`

Nama `Tanggal_Review` sudah dipakai `N_SOP_Item_Versi_Review` (DDL-2026-038)
untuk tanggal putaran review yang **sudah terjadi**. Dua kolom bernama sama
dengan arah waktu berlawanan adalah jebakan bagi kueri yang menggabungkan
keduanya.

### Kenapa aturannya di master jenis, bukan konstanta

Modul SOP konsisten menaruh aturan sebagai data — `Cap_Tanggal`,
`Status_Teknis`, `Butuh_Berkas`, seluruh tabel transisi dan syarat. Konstanta
"24 bulan" di dalam service akan menjadi satu-satunya aturan SOP yang butuh
rilis untuk diubah, padahal biaya kolomnya sama saja. Dengan kolom ini,
Kebijakan bisa 3 tahun dan Instruksi Kerja 1 tahun tanpa menyentuh kode.

Nama `Masa_Berlaku_Bulan` mengikuti kosakata yang sudah dipakai
`N_HRIS_Jenis_Cuti` dan `N_WEB_CAREERS_Master_Jenis_Tes`.

### Kenapa ketiganya NULL

Versi lama tidak punya nilainya, dan `NOT NULL` berarti memilihkan tanggal untuk
dokumen yang tak seorang pun pernah putuskan. Kewajiban ditegakkan sebagai baris
syarat transisi di DML-2026-050 — yaitu saat versi **meninggalkan** Pemberkasan
menuju terbit, bukan saat draft disimpan.

Tidak ada kolom audit baru: ketiganya menempel pada baris yang audit-nya sudah
ada.

## Catatan pemeriksaan

- `N_SOP_Item_Versi` berisi 1 baris pada 2026-08-27 (DML-2026-047 mengosongkan
  rantai dokumen SOP); nol baris memiliki `Tanggal_Kadaluarsa` terisi. Tidak ada
  backfill yang diperlukan.
- `N_SOP_Paket_Draft_Item_Versi` **sudah memiliki** kolom
  `Tanggal_Review_Berikutnya` sebelum perubahan ini, tanpa satu pun penulis atau
  pembaca di kode. Kolom dormant itu tidak disentuh; jalur salinan kerja paket
  tidak menyalin kolom tanggal versi sama sekali.

## Pemakaian di aplikasi

- `app/Services/LMS/SOP/SopMasaBerlakuService.php` — membaca `Masa_Berlaku_Bulan`
  dan `Siklus_Review_Bulan`, menghitung dua tanggal turunan.
- `app/Services/LMS/SOP/SopDokumenService.php` — menulis
  `Tanggal_Review_Berikutnya` pada `tambahVersi()` dan `ubahVersi()`.
- `app/Services/LMS/SOP/SopDocumentControlService.php` — mengembalikan kolomnya
  pada dua projeksi riwayat versi.
- `app/Services/LMS/SOP/SopTahapVersiService.php` — mengevaluasi syarat
  `TANGGAL_KADALUARSA` dan `TANGGAL_REVIEW_BERIKUTNYA`.
- `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` —
  validasi bentuk dan urutan tanggal.
- `app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php` —
  mengirim dua angka bulan sebagai prop halaman.

## Pekerjaan yang diselesaikan agen

- Menulis `forward.sql` dan `drop.sql`.
- Memeriksa keadaan objek sebelum eksekusi (ketiga kolom belum ada).
- Mengeksekusi `forward.sql` ke `Web_HRIS` development dan memverifikasi ketiga
  kolom terpasang dengan tipe dan nullability yang benar.
- Mencatat perubahan ke `N_SYSTEM_Database_Change` dan `database/ddl/status.json`.

## Pekerjaan yang tersisa untuk pengguna

- Meninjau dan menyetujui forward DDL.
- Merekam forward DDL yang sama persis ke SQL Tracker (checksum di atas).
- Mengeksekusi ke produksi lewat SQL Tracker.
- Memverifikasi hasil produksi, lalu menaikkan status ke `COMPLETED`.
