# Checklist Produksi — Modul DMS (Document Management System)

Status: `DRAFT` · Diperiksa terhadap `Web_HRIS` (development) pada 2026-08-28

Menjawab satu pertanyaan: **tabel apa saja yang harus sudah terisi di produksi
supaya admin bisa memakai modul DMS.**

Angka pada dokumen ini diukur dari development — satu-satunya lingkungan tempat
modul ini pernah hidup — dan berlaku sebagai *cuplikan bertanggal*, bukan
kebenaran abadi. Periksa ulang terhadap database sebelum eksekusi.

---

## Temuan utama: produksi belum menerima apa pun

Dari `database/ddl/status.json`:

| Tahap | Jumlah |
|---|---|
| Sudah dijalankan di **development** | 64 dari 67 |
| Terdaftar di **SQL Tracker** | **0** |
| Sudah dijalankan di **produksi** | **0** |

Jadi ini bukan "menambal beberapa master yang tertinggal". Produksi belum punya
satu pun tabel, kolom, maupun baris master milik modul ini. Seluruh 66 perubahan
(1 dibatalkan) harus melewati SQL Tracker lebih dulu.

**Tiga perubahan belum jalan di development sendiri** — jangan dikirim ke
produksi mendahului dev:

- `DML-2026-013-bersihkan-pengesahan-non-dokumen`
- `DML-2026-015-bersihkan-riwayat-kepatuhan-yatim`
- `DML-2026-054-subtitle-functional-document`

---

## 1. Master yang WAJIB terisi, dan seedernya sudah ada

Semuanya punya artefak DML. Tidak perlu diketik ulang oleh admin — jalankan
seedernya.

| Tabel | Isi di dev | Seeder |
|---|---|---|
| `N_HRIS_Master_Grup_Fitur` | 7 grup aktif (DMS = Id 7) | `20260816_175007`, `DML-2026-044` |
| `N_HRIS_Grup_Fitur_Map` | 14 halaman `menuSop*` | `DML-2026-044`, `-045`, `-046`, `-057`, `-058` |
| `N_HRIS_Menus` | 1 baris menu per halaman | sama seperti di atas |
| `N_HRIS_Master_Entitas` | 4 entitas aktif | `DML-2026-020` + DML `DDL-2026-056` |
| `N_HRIS_Master_Departemen` | 52 kode aktif | `DML-2026-028` |
| `N_SOP_Master_Jenis_Dokumen` | 7 jenis aktif | `DML-2026-017`, `-031` |
| `N_SOP_Master_Jenis_Dokumen_Tipe_Item` | 8 pemetaan | `DML-2026-017` |
| `N_SOP_Master_APQC` | 13 proses aktif | `DML-2026-024` |
| `N_SOP_Master_APQC_Tipe_Item` | 39 pemetaan | `DML-2026-024` |
| `N_SOP_Master_Tahap_Versi` | 8 baris (7 aktif) | `DML-2026-033`, `-042`, `-051` |
| `N_SOP_Master_Tahap_Versi_Syarat` | 6 syarat | `DML-2026-040`, `-041`, `-048`, `-050` |
| `N_SOP_Master_Tahap_Versi_Transisi` | 21 baris (10 aktif) | `DML-2026-033`, `-042` |

**Urutan yang mengikat.** Grup fitur → pemetaan halaman → menu → hak akses.
Entitas dan departemen sebelum dokumen apa pun dibuat, karena keduanya jadi
segmen nomor dokumen. Jenis dokumen + kode cetaknya sebelum nomor bisa dirakit.
Tahap versi sebelum versi mana pun bisa bergerak.

**Baris yang sengaja mati bukan kelalaian.** `menuSopKategoriPage` ber-`Flag_Aktif = 'T'`
karena Kategori dicabut lewat `DML-2026-058`; sebagian transisi tahap juga
nonaktif sejak alur versi disederhanakan. Menyalakannya kembali akan
menghidupkan kembali alur yang sudah dibuang.

---

## 2. Yang TIDAK punya seeder — harus dikerjakan manusia di produksi

### `HRIS_Page_Access` — ini yang paling mudah terlupa

Di dev hanya **dua user** yang bisa membuka modul ini (user 4 dan 622), masing-masing
dengan 13 halaman. Baris-baris itu **tak bisa disalin** ke produksi karena id
user-nya berbeda.

Ini bukan kenyamanan melainkan syarat: role di sistem ini memberi akses lewat
keanggotaan grup fitur, dan tak ada role yang memegang grup DMS. Tanpa baris
`HRIS_Page_Access` eksplisit, **setiap halaman DMS menolak semua orang** —
termasuk yang merasa dirinya administrator.

Untuk tiap admin DMS di produksi, tanam satu baris per halaman (14 halaman,
minus `menuSopKategoriPage` yang memang mati), lalu panggil
`InertiaShellService::flushMenuCache()` — baris menu di-cache, jadi tanpa flush
sidebar tetap kosong.

### `N_SOP_Prioritas` — kosong, tapi aman

Kosong di dev (0 baris) dan **tak ada seeder**. Tidak memblokir apa pun:
`SopPrioritasService` jatuh ke tiga tingkat bawaan (Sangat Penting / Tinggi /
Normal) ketika tabelnya kosong maupun belum ada. Akibatnya hanya satu: halaman
Prioritas Dokumen tampil kosong sampai admin mengisinya sendiri.

### `N_SOP_Setting` — 7 baris, dan yang tidak ada pun punya makna

Terisi di dev: `aktivitas_log_retensi_hari`, `default_reminder_hari`,
`heartbeat_checkpoint_dtk`, `mover_auto_assign`, `mover_auto_release`,
`reader_dwell_min_dtk`, `video_ambang_persen`.

`penjadwalan_async_ambang` **tidak ada barisnya** dan jatuh ke bawaan 30. Itu
berlaku juga di produksi. Ambang inilah yang menentukan kapan penugasan pindah
ke antrean, jadi kalau populasi produksi jauh lebih besar dari dev, angkanya
layak ditetapkan secara sadar — bukan diwarisi diam-diam.

---

## 3. JANGAN dijalankan di produksi

| Artefak | Alasan |
|---|---|
| `DML-2026-047-reset-data-sop` | Mengosongkan seluruh rantai dokumen & paket beserta jejaknya (4.135 baris di dev). Ini alat pembersih data percobaan. |
| Seluruh berkas `drop.sql` | Berkas bantu pembersihan manual. AGENTS.md melarang agent menjalankannya, dan di produksi ia membuang kolom berikut isinya. |

---

## 4. Celah pencatatan yang perlu dibereskan lebih dulu

Tiga folder artefak ada di disk tetapi **tidak terdaftar** di
`database/ddl/status.json`, sehingga tak akan terbawa saat perubahan didaftarkan
ke SQL Tracker satu per satu:

- `DML-2026-028-kpi-tim-jadwal-perorangan`
- `DML-2026-047-reset-data-sop` *(memang tak boleh ke produksi — tapi ketidakhadirannya di daftar sebaiknya disengaja, bukan kebetulan)*
- `DML-2026-048-approved-langkah-pemberkasan`

---

## 5. Sesudah semua DML dijalankan

1. `php artisan sop:segarkan-nomor` — `Nomor_SOP_Resmi` adalah kolom
   **tersimpan** yang dibaca 45 berkas. Mengubah master tidak memicu
   penulisannya, jadi tanpa langkah ini nomor tersimpan dan nomor terakit bisa
   berbeda.
2. `InertiaShellService::flushMenuCache()` — sidebar membaca menu dari cache.
3. `php artisan route:clear` bila `bootstrap/cache` ikut terbawa rilis.
4. Verifikasi lewat layar, bukan lewat kueri saja: buka Master Dokumen sebagai
   admin produksi sungguhan dan pastikan sidebar, daftar, dan pratinjau nomor
   tampil.

---

## Cara memeriksa ulang cuplikan ini

```sql
-- master modul
SELECT 'Jenis_Dokumen' k, COUNT(*) n FROM N_SOP_Master_Jenis_Dokumen WHERE Flag_Aktif='Y'
UNION ALL SELECT 'APQC', COUNT(*) FROM N_SOP_Master_APQC WHERE Flag_Aktif='Y'
UNION ALL SELECT 'Tahap_Versi', COUNT(*) FROM N_SOP_Master_Tahap_Versi WHERE Flag_Aktif='Y'
UNION ALL SELECT 'Entitas', COUNT(*) FROM N_HRIS_Master_Entitas WHERE Flag_Aktif='Y'
UNION ALL SELECT 'Departemen', COUNT(*) FROM N_HRIS_Master_Departemen WHERE Flag_Aktif='Y'
UNION ALL SELECT 'Prioritas', COUNT(*) FROM N_SOP_Prioritas WHERE Flag_Aktif='Y';

-- pendaftaran halaman & akses
SELECT Jenis_page, Id_Grup_Fitur, Flag_Aktif FROM N_HRIS_Grup_Fitur_Map
 WHERE Jenis_page LIKE 'menuSop%' ORDER BY Jenis_page;

SELECT UserID_Web, COUNT(DISTINCT Jenis_page) n FROM HRIS_Page_Access
 WHERE Jenis_page LIKE 'menuSop%' AND Flag_Aktif='Y' GROUP BY UserID_Web;
```
