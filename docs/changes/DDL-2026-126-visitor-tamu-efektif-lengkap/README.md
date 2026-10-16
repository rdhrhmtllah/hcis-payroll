# DDL-2026-126-visitor-tamu-efektif-lengkap

## Ringkas

`N_VISITOR_Tamu_Efektif` adalah satu-satunya tabel tamu yang dibaca aplikasi
palang. Pemisahan Tamu Regular/B2B (DDL-2026-121) dan matriks departemen
(DDL-2026-122) menambah kolom di Detail kunjungan, tetapi proyeksinya tidak
ikut — palang hanya melihat `Tanggal_Kunjungan` + jam estimasi, sehingga tidak
tahu kunjungan **MITRA** berlaku lebih dari sehari. Change ini melengkapinya.

| | |
|---|---|
| Change ID | `DDL-2026-126-visitor-tamu-efektif-lengkap` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DDL-2026-121`, `DDL-2026-122` (kolom asal di Detail) |
| Status | `DEV_EXECUTED` (artifact r2) |

Nomor 124 dan 125 sudah dipakai change lain (`DML-2026-124` di master,
`DML-2026-125` di pekerjaan PA), jadi change ini memakai 126.

## Keputusan user (2026-09-19)

- "Semuanya harus ada di N_Visitor_Tamu_Efektif" — seluruh kolom baru kunjungan
  ikut diproyeksikan.
- Rentang estimasi **wajib bernama "Estimasi"** (`Waktu_Estimasi_Masuk` /
  `Waktu_Estimasi_Keluar`), karena tabel ini juga punya `Waktu_Aktual_Masuk` /
  `Waktu_Aktual_Keluar` dari palang — nama `Waktu_Mulai/Selesai_Kunjungan`
  membingungkan pembacanya. → amandemen r2.

## Objek

**DDL (`forward.sql`)** — kolom baru `NULL` di `N_VISITOR_Tamu_Efektif`, tipe
sama persis dengan asalnya di `N_EMI_VISITOR_Transaksi_Tamu_Detail`:

| Kolom | Tipe | Arti |
|---|---|---|
| `Waktu_Estimasi_Masuk` | `datetime2(0)` | Estimasi Masuk (asal Detail: `Waktu_Mulai_Kunjungan`) |
| `Waktu_Estimasi_Keluar` | `datetime2(0)` | Estimasi Keluar — batas berlaku kunjungan (asal Detail: `Waktu_Selesai_Kunjungan`) |
| `Tamu_Kategori` | `varchar(10)` | `VISITOR` / `MITRA` (MITRA = rentang > 24 jam, boleh keluar-masuk) |
| `B2B_Unit` | `varchar(20)` | `PROCUREMENT` / `LOGISTIC`, hanya tamu B2B |
| `Path_Foto_Kendaraan` | `varchar(500)` | path GCS foto kendaraan (opsional) |

Kolom lama (`Tanggal_Kunjungan`, `Waktu_Estimasi_Kunjungan`, `Status_Efektif`,
waktu aktual) **tidak diubah** — palang yang ada tetap berjalan. Baris lama tanpa
estimasi membawa `NULL`; pembaca memakai cadangan yang sama dengan HCIS:
masuk = `Tanggal_Kunjungan`, keluar = `Tanggal_Kunjungan` + 1 hari.

Contoh isi (baris contoh dev `VSR-CONTOH-MITRA`):

| Kolom | VISITOR (sehari) | MITRA (> 24 jam) |
|---|---|---|
| `Tipe_Tamu` | `REGULAR` / `B2B` | `B2B` |
| `Tamu_Kategori` | `VISITOR` | `MITRA` |
| `B2B_Unit` | `NULL` (Regular) | `LOGISTIC` |
| `Tanggal_Kunjungan` | `2026-09-20` | `2026-09-20` |
| `Waktu_Estimasi_Masuk` | `2026-09-20 09:00` | `2026-09-20 08:00` |
| `Waktu_Estimasi_Keluar` | `2026-09-20 15:00` | `2026-09-22 17:00` |
| `Waktu_Aktual_Masuk/Keluar` | diisi palang | diisi palang (boleh keluar-masuk selama rentang) |

**DML (`data-change.sql`)**: isi ulang kelima kolom dari Detail untuk semua
baris (kolom keterangan, bukan keadaan palang), pendaftaran dashboard change.

## Kode

`VisitorEfektifService::syncTamuEfektif` menulis kelima kolom pada setiap sinkron
(approval, sunting kunjungan, registrasi berapproval-otomatis), sehingga
perubahan estimasi setelah disetujui ikut terbawa ke palang.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-19 | `forward.sql` | OK — lima kolom terbentuk. |
| 2026-09-19 | `data-change.sql` | OK — 15 baris diisi ulang; 15/15 identik dengan Detail. |
| 2026-09-19 | penyelarasan r2 (lihat bawah) | OK — dua kolom di-rename; rerun `forward.sql` r2 + `data-change.sql` r2 = no-op DDL, 15/15 tetap identik. |

## Revisi artifact

| Revisi | Isi |
|---|---|
| r1 | Kolom rentang bernama `Waktu_Mulai_Kunjungan` / `Waktu_Selesai_Kunjungan` (dijalankan di dev 2026-09-19). |
| r2 | Diganti `Waktu_Estimasi_Masuk` / `Waktu_Estimasi_Keluar` atas permintaan user. Production langsung memakai r2 — tidak pernah melihat nama r1. |

## Penyelarasan development (BUKAN artifact — jangan dijalankan di production)

Dev sudah menjalankan r1, jadi diselaraskan ke bentuk r2 dengan:

```sql
EXEC sp_rename 'N_VISITOR_Tamu_Efektif.Waktu_Mulai_Kunjungan', 'Waktu_Estimasi_Masuk', 'COLUMN';
EXEC sp_rename 'N_VISITOR_Tamu_Efektif.Waktu_Selesai_Kunjungan', 'Waktu_Estimasi_Keluar', 'COLUMN';
```

Bukti selaras: rerun artifact r2 tidak menambah kolom apa pun, dan kolom nama r1 tidak tersisa.

## Data contoh development

`VSR-CONTOH-MITRA` — "[CONTOH] Mitra Logistik", B2B Logistic, 20–22 Sep 2026
(3 hari → `MITRA` dihitung `TipeTamu::kategori`), diproyeksikan lewat
`syncTamuEfektif`. Hanya di `Web_HRIS`; tiga baris (header, Detail, efektif)
dengan `No_Transaksi = 'VSR-CONTOH-MITRA'`, bebas dihapus.

## Pekerjaan user

- Rekam di SQL Tracker **setelah** DDL-2026-121 dan DDL-2026-122; urutan `forward.sql` lalu `data-change.sql`.
- Beri tahu tim aplikasi palang: kolom baru tersedia; batas berlaku kunjungan
  kini `Waktu_Estimasi_Keluar`, dan `Tamu_Kategori = 'MITRA'` boleh keluar-masuk.
