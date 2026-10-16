# DML-2026-051 — Tinjauan Akhir sebagai langkah kelima

| | |
|---|---|
| Change ID | `DML-2026-051-langkah-tinjauan-akhir` |
| Jenis | DML |
| Target development | `Web_HRIS` (host team311) |
| Status | `DEV_EXECUTED` — SQL Tracker & produksi masih `PENDING` |
| Checksum (sha256) | `9341ce0e744c88fd985fd73ba8247040fae698d745d43938e0e74a5b9adc698d` |
| Dieksekusi ke development | 2026-08-27 |

## Path

- DML: `database/dml/DML-2026-051-langkah-tinjauan-akhir/data-change.sql`
- Desain: `plans/sop/2026-08-27-masa-berlaku-versi-design.md` §2.6

## Objek terdampak & cakupan

| Tabel | Operasi | Predikat | Baris |
|---|---|---|---|
| `N_SOP_Master_Tahap_Versi` | UPDATE | `Kode_Tahap = 'FILING_REGISTERED'` → `Langkah = 5`, `Kelompok = 'TINJAUAN'` | 1 |
| `N_SYSTEM_Database_Change` | INSERT | pencatatan | 1 |

Tidak ada baris transaksi yang disentuh. Yang berubah hanya pemetaan langkah
sebuah tahap di master.

## Pemetaan langkah sesudah perubahan

| Langkah | Nama | Tahap | Kelompok |
|---|---|---|---|
| 1 | Pengajuan | `DRAFTING` | `PENGAJUAN` |
| 2 | Review | `REVIEW` | `REVIEW` |
| 3 | Pengesahan | `APPROVAL` | `PENGESAHAN` |
| 4 | Pemberkasan | `APPROVED` | `TERBIT` |
| 5 | **Tinjauan Akhir** | `FILING_REGISTERED` | `TINJAUAN` |

Sebelumnya `APPROVED` dan `FILING_REGISTERED` sama-sama menempati langkah 4.

## Kenapa

Penerbitan menggeser pointer dokumen, meng-obsolete versi lama, merakit ulang
nomor resmi, dan merekonsiliasi paket perujuk — semuanya tak bisa ditarik.
Sampai perubahan ini, satu-satunya cara melihat kembali apa yang sudah diisi
sebelum menerbitkan adalah menyusuri ulang empat langkah satu per satu, dan tak
seorang pun melakukannya.

Langkah 5 menyodorkan seluruh isian sekaligus sebagai **rekap baca-saja**, lalu
menawarkan tombol terbit. Tombol di langkah 4 karena itu berubah dari
"Terbitkan" menjadi **"Simpan & Lanjut ke Tinjauan Akhir"** — ia mengantar, bukan
menerbitkan dari kejauhan.

### Kenapa lewat data, bukan kotak yang dihafal layar

Empat langkah sebelumnya semuanya lahir dari kolom `Langkah` di master ini.
Menambah langkah kelima sebagai konstanta di layar akan menjadikannya
satu-satunya langkah yang tak bisa diubah tanpa rilis, sekaligus sumber
kebenaran kedua yang seluruh rancangan wizard ini ada untuk menghindarinya.

### Kenapa bernama "Tinjauan Akhir", bukan "Review"

Langkah 2 sudah bernama Review dan mengurus catatan review. Dua kotak di rel
langkah yang sama berbunyi identik adalah cara termurah membuat admin salah
membuka langkah.

## Perubahan kode yang menyertainya

- `SopTahapVersiService` — `KELOMPOK_SAH` menerima `TINJAUAN`; `NAMA_LANGKAH[5]`
  = `Tinjauan Akhir`; ringkasan & kelengkapan langkah `TINJAUAN`; `OBSOLETE`
  kini menutup langkah terakhir lewat `array_key_last()` alih-alih angka mati.
- `SopTahapVersiService::aksiAntarLangkah()` — aksi `LANJUT_LANGKAH` yang
  memindahkan **layar**, bukan tahap. Hanya lahir menjelang penerbitan.
- `alurVersi()` — mengirim `Aksi_Antar`, dan melonggarkan kunci **hanya** untuk
  langkah tujuan penerbitan. Langkah maju yang lain tetap terkunci.
- `LangkahTinjauan.vue` — rekap baca-saja dengan tombol "Sunting" per blok.

## Rollback

Tidak ada berkas drop: pembalikannya satu `UPDATE`.

```sql
UPDATE N_SOP_Master_Tahap_Versi
SET Langkah = 4, Kelompok = 'TERBIT', Updated_At = SYSDATETIME()
WHERE Kode_Tahap = 'FILING_REGISTERED';
```

Menjalankannya mengembalikan alur ke empat langkah. Kode yang menyertainya tetap
berjalan — langkah `TINJAUAN` sekadar tak pernah muncul lagi.

## Pekerjaan yang diselesaikan agen

- Menulis `data-change.sql`.
- Melaporkan cakupan (1 baris UPDATE) sebelum eksekusi.
- Mengeksekusi ke `Web_HRIS` development dan memverifikasi pemetaan kelima
  langkah.
- Mencatat perubahan ke `N_SYSTEM_Database_Change` dan `database/ddl/status.json`.

## Pekerjaan yang tersisa untuk pengguna

- Meninjau dan menyetujui DML.
- Merekam DML yang sama persis ke SQL Tracker (checksum di atas).
- Mengeksekusi ke produksi lewat SQL Tracker, **sesudah** DDL-2026-049 dan
  DML-2026-050.
- Memverifikasi hasil produksi, lalu menaikkan status ke `COMPLETED`.
