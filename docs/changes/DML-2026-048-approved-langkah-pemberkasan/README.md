# DML-2026-048 — Tahap "Disahkan" kembali hidup, di langkah 4

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-048-approved-langkah-pemberkasan` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artefak DML | `database/dml/DML-2026-048-approved-langkah-pemberkasan/data-change.sql` |
| Prasyarat | `DML-2026-047-reset-data-sop` (wajib lebih dulu) |
| Desain | `plans/sop/2026-08-26-wizard-versi-dokumen-design.md` §2.3 & §6.2 |

## Masalah

Wizard versi yang baru mengunci langkah di depan tahap berjalan. Dengan susunan
tahap sekarang, **langkah 4 (Pemberkasan) tak akan pernah terbuka**: satu-satunya
jalan maju dari `APPROVAL` adalah `FILING_REGISTERED` — yaitu menerbitkan —
sedangkan berkas final yang menjadi syarat terbit justru diisi di langkah 4 itu.
Buntu.

## Hubungan dengan DML-2026-042 — dibaca dulu sebelum menyimpulkan

`DML-2026-042` (2026-08-24) mematikan `APPROVED` dengan alasan yang **benar**:

> "Langkah Pengesahan diwakili dua tahap — Approval lalu Approved — sehingga
> admin menekan 'Simpan & Lanjut' dua kali untuk satu langkah."

Change ini **tidak membatalkan** keputusan itu. Bentuk lamanya tidak
dikembalikan. `APPROVED` dipetakan ke **langkah 4**, bukan langkah 3, sehingga
tetap berlaku satu tombol lanjut per langkah — keluhan 042 justru terjawab.

Keputusan 042 yang lain tetap berlaku penuh dan **tidak disentuh**:

- `HOLD` tetap mati
- Syarat `CATATAN_REVIEW` tetap tidak ada — review boleh kosong
- Gerbang isi tetap satu, di `FILING_REGISTERED`

## Alur sesudah perubahan

```
Pengajuan  ──►  Review   ──►  Pengesahan ──►  Pemberkasan ──►  (terbit)
DRAFTING       REVIEW        APPROVAL        APPROVED         FILING_REGISTERED
langkah 1      langkah 2     langkah 3       langkah 4        langkah 4
                                  │               │
                                  └── CANCEL ─────┘
```

## Objek terdampak

| Tabel | Operasi | Baris |
|---|---|---|
| `N_SOP_Master_Tahap_Versi` | UPDATE `APPROVED` → aktif, `Langkah=4`, `Kelompok='TERBIT'`, `Nama_Tahap='Pemberkasan'`, `Cap_Tanggal=NULL` | 1 |
| `N_SOP_Master_Tahap_Versi_Transisi` | aktifkan `APPROVAL→APPROVED`, `APPROVED→FILING_REGISTERED`, `APPROVED→CANCEL` | 3 |
| `N_SOP_Master_Tahap_Versi_Transisi` | matikan `APPROVAL→FILING_REGISTERED` | 1 |
| `N_SOP_Master_Tahap_Versi_Transisi` | INSERT `APPROVED→APPROVAL` (mundur satu langkah) | 1 |
| `N_SOP_Master_Tahap_Versi_Syarat` | INSERT `REALISASI_PENGESAH` untuk `APPROVED` | 1 |

### Keputusan kecil yang perlu dicatat

- **`APPROVED→REVIEW` dibiarkan mati.** Mundur dua langkah sekaligus melewati
  Pengesahan tanpa meninggalkan sebab kenapa pengesahannya batal.
- **`APPROVAL→FILING_REGISTERED` dimatikan.** Membiarkannya hidup berarti ada
  dua jalan menerbitkan, dan yang satu melewati langkah tempat berkas finalnya
  dipasang.
- **`Cap_Tanggal` `APPROVED` dikosongkan.** Sebelumnya ia mencap
  `Tanggal_Akhir_Approval`, kolom yang sudah dicap `APPROVAL` saat masuk
  Pengesahan — pencapan kedua tak pernah menulis apa pun karena `capTanggal()`
  hanya mengisi yang `NULL`. Dengan `NULL`, tanggal langkah 4 jatuh ke
  `Tanggal_Publish`, dan itulah yang memang ingin dibaca orang dari langkah
  Pemberkasan.
- **`Butuh_Pengesahan` tetap `'Y'`, `Butuh_Berkas` tetap `'T'`** — sesuai
  `DML-2026-041`.

## Penjaga

Skrip menolak jalan bila `N_SOP_Item_Versi` masih berisi baris (galat `50048`).
Memindahkan tahap ke langkah lain saat masih ada versi hidup berarti memigrasi
keadaan tanpa rencana migrasi. Dijalankan sesudah 047, jumlah versi nol dan
seluruh persoalan itu lenyap.

## Perubahan kode yang menyertai

- `SopTahapVersiService::NAMA_LANGKAH[4]` : `'Terbit & Sebar'` → `'Pemberkasan'`
- `SopDokumenService::terbitkanVersi()` : penjaga baru menolak penerbitan bila
  `FILING_REGISTERED` bukan tujuan sah dari tahap versi saat ini — aturannya
  tetap dibaca dari tabel transisi, bukan dihafal kode.

## Yang dikerjakan agen

- Menulis `data-change.sql` dan dokumentasi ini
- Menjalankan skrip ke **development** dan memverifikasi susunan tahap & transisi

## Sisa pekerjaan user

- Meninjau keputusan "APPROVED di langkah 4", terutama bagian yang menyinggung
  DML-2026-042
- Mencatat change ini di SQL Tracker
- Menjalankan ke produksi **bersama** perubahan kode wizard — master tahap yang
  berubah tanpa kode yang membacanya akan membuat langkah 4 tak terjangkau di
  produksi
