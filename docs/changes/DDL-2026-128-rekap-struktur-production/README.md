# DDL-2026-128 — Rekap STRUKTUR production (tabel & kolom)

**Tanggal survei:** 21 September 2026
**Sasaran:** production `emi_db_web_hr` (34.143.236.80)
**Cara survei:** koneksi READ ONLY dari repo ini; tidak satu pun pernyataan tulis dijalankan.

## Kenapa rekap ini ada

Master sudah jauh di depan production, dan tunggakannya tersebar di 22 change
DDL serta 55 change DML. Menjalankannya satu per satu berarti 77 keputusan
kecil yang masing-masing bisa terlewat; lebih buruk lagi, sebagian di antaranya
**sudah berlaku di production** tanpa pernah dicatat, sehingga daftar tunggakan
versi ledger tidak bisa dipercaya apa adanya.

Karena itu yang dipakai di sini bukan status ledger, melainkan **keadaan nyata
production**: untuk setiap objek yang disebut berkas DDL, keberadaannya dicek
langsung ke `sys.tables`, `sys.columns`, dan `sys.indexes`.

## Hasil survei

Dari 86 kolom, 189 indeks/constraint, dan seluruh tabel yang disebut berkas DDL
di repo, yang **benar-benar belum ada** di production:

| Jenis | Jumlah |
|---|---|
| Tabel | 7 |
| Kolom | 11 |
| Indeks / constraint | 36 |

### Tabel yang belum ada (7)

Semuanya Visitor, dari dua fitur yang memang belum pernah rilis:

- `N_VISITOR_Matriks_Tamu` — matriks departemen × jenis tamu (DDL-2026-122)
- `N_VISITOR_Master_Shift_Inspeksi`, `N_VISITOR_Master_Pos_Inspeksi`,
  `N_VISITOR_Inspeksi_Pola`, `N_VISITOR_Inspeksi_Pengecualian`,
  `N_VISITOR_Inspeksi_Booking`, `N_VISITOR_Inspeksi_Hari_Shift` — jadwal
  inspeksi permit (DDL-2026-123)

### Kolom yang belum ada (11)

| Tabel | Kolom | Asal |
|---|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `Tipe_Tamu` | DDL-2026-101 / 118 |
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `Tamu_Kategori`, `Path_Foto_Kendaraan` | DDL-2026-121 |
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `B2B_Unit` | DDL-2026-122 |
| `N_VISITOR_Tamu_Efektif` | `Tipe_Tamu` | DDL-2026-101 / 118 |
| `N_VISITOR_Tamu_Efektif` | `Waktu_Estimasi_Masuk`, `Waktu_Estimasi_Keluar`, `Tamu_Kategori`, `B2B_Unit`, `Path_Foto_Kendaraan` | DDL-2026-126 |
| `N_EMI_VISITOR_Transaksi_Permit` | `Kode_Kartu` | DDL-2026-127 |

### Indeks yang belum ada (36)

Seluruhnya pindah ke **DDL-2026-129**; daftar dan verifikasinya ada di sana.

## Isi berkas

`forward.sql` (1.272 baris) memuat **hanya struktur**: 14 `CREATE TABLE`
berpenjaga (tujuh di antaranya benar-benar baru di production) dan 57
`ALTER TABLE … ADD <kolom>` berpenjaga (sebelas benar-benar baru). Nol
`CREATE INDEX`, nol `ADD CONSTRAINT`, nol `DROP INDEX` — semuanya pindah ke
**DDL-2026-129**.

Pemisahan ini disengaja: struktur berjalan cepat dan nyaris tanpa risiko
penguncian, sedangkan pembangunan indeks pada tabel besar (`Transaksi_Cuti_Det`,
`HRIS_Page_Access`) adalah pekerjaan yang lain sama sekali dan pantas punya
jendela waktunya sendiri. Kalau indeksnya perlu dibatalkan, strukturnya tidak
ikut tersentuh.

**URUTAN: berkas ini DULU.** Tujuh tabel Visitor lahir di sini; indeks
DDL-2026-129 berdiri di atasnya dan akan berhenti pada penjaga `OBJECT_ID`
bila strukturnya belum naik.

Isinya disalin **apa adanya** dari 17 berkas DDL yang sudah ditinjau dan
dijalankan di development. Tidak ada SQL yang ditulis ulang.

Seluruh pernyataan berpenjaga (`IF NOT EXISTS`, `COL_LENGTH`, `OBJECT_ID`),
jadi berkas ini **aman diulang**: menjalankannya dua kali tidak menggandakan
apa pun dan tidak menggagalkan sisanya. Tiga `DROP INDEX` bawaan DDL-2026-118
ikut serta; ketiganya penggantian indeks yang definisinya kedaluwarsa dan sudah
ditinjau di change tersebut. Tidak ada `DROP TABLE`, `DROP COLUMN`, `TRUNCATE`,
`UPDATE`, `DELETE`, atau `MERGE`.

**Satu penyesuaian yang bukan salinan:** tiga berkas sumber (087, 088, 090)
memuat penjaga `IF DB_NAME() <> 'Web_HRIS'` yang akan menghentikan skrip di
production dengan `RAISERROR`. Penjaga itu dicabut di rekap ini dan digantikan
komentar; penjaga keberadaan objek tetap dipertahankan. Setiap 183 batch sudah
divalidasi dengan `SET PARSEONLY ON` — nol galat sintaks.

## Yang sengaja TIDAK ikut

| Change | Alasan |
|---|---|
| DDL-2026-067 (RCSI) | `ALTER DATABASE` tingkat basis data. Production terkonfirmasi masih `is_read_committed_snapshot_on = 0`. Ini keputusan tersendiri dan butuh jendela pemeliharaan — bukan tumpangan rekap. |
| DDL-2026-089 (indeks kembar) | Berisi `DROP INDEX`. Rekap ini hanya menambah. |
| DDL-2026-059 (kategori NULL) | Sudah berlaku: kedua kolom `Id_Kategori_SOP` terbukti sudah NULLABLE di production. Ledger-nya basi. |
| DDL-2026-101/102/103/114 | Seluruh objeknya tercakup Bagian C lewat DDL-2026-118. |

## Dua temuan yang perlu dicatat terpisah

1. **DDL-2026-053 berstatus production DONE, tetapi indeksnya tidak ada.**
   `UQ_N_SOP_Assignment_Rule_Paket` tidak ditemukan di production. Rekap ini
   memasangnya, tetapi status DONE itu berarti ledger pernah mencatat sesuatu
   yang tidak terjadi.
2. **`DDL-2026-062-versi-unik-hanya-yang-hidup` tidak punya entri ledger sama
   sekali.** Nomor 062 sudah dipakai change lain (`visitor-permit-efektif-nik`),
   sehingga folder ini luput dari pencatatan. Indeksnya
   (`IX_N_SOP_Dokumen_Tipe`) juga belum ada di production dan ikut dipasang di
   sini.

## DML — keadaan nyata di production

Ledger menyebut 55 DML belum dijalankan. Setelah kunci halaman (`Jenis_page`)
dan kode role yang disebut tiap skrip dicari langsung di production:

| Keadaan | Jumlah | Artinya |
|---|---|---|
| **SUDAH berlaku** | 17 | Ledger basi. Termasuk seluruh pemisahan menu Visitor (105, 106, 107), role verifikator per domain (108), penyelarasan grup Visitor (124), dan menu SYSTEM/SOP lama. |
| **SEBAGIAN** | 7 | Sebagian kuncinya ada, sebagian belum — perlu dibuka satu per satu. |
| **BELUM** | 1 | `DML-2026-119-izin-koreksi-target-kpi` — `HRIS_Page_Access` nol baris untuk `kpiKoreksiTarget`, terkonfirmasi hari ini. |
| **Perlu tinjau manual** | 30 | Skripnya tidak menyebut kunci halaman/role, jadi tidak bisa dinilai otomatis (seed master, perbaikan data, penjadwalan). |

Seed master besar sudah diperiksa terpisah lewat jumlah barisnya dan **semuanya
sudah ada di production**: jenis dokumen SOP (8), entitas HRIS (4), APQC (14),
departemen HRIS (52), tahap versi SOP (8), prioritas SOP (3). Yang **belum**:
`N_HRIS_Api_Aplikasi` masih 0 baris di production (dev 3) — artinya
DML-2026-075 (API kalender HO) dan DML-2026-080 (aplikasi mitra B2B) memang
belum pernah dijalankan.

### DML yang SEBAGIAN (7) — perlu dibuka satu per satu

`DML-2026-008` (menu Pengumpulan Data, 2/3), `DML-2026-018` (menu jenis dokumen
SOP, 1/2), `DML-2026-044` (modul & menu DMS, 14/15), `DML-2026-045` (urutan menu
DMS, 12/13), `DML-2026-046` (library konten, 10/11), `DML-2026-057` (izin
prioritas SOP, 1/2), `DML-2026-058` (cabut kategori SOP, 1/2).

## Urutan eksekusi yang disarankan

1. `forward.sql` **berkas ini** (struktur) — tabel dan kolom.
2. Blok VERIFIKASI STRUKTUR di akhir berkas; **harus nol baris**.
3. `DDL-2026-129/forward.sql` (indeks & constraint), lalu blok VERIFIKASI
   INDEKS-nya; **harus nol baris** juga.
4. Baru kemudian DML: mulai dari `DML-2026-119` (satu-satunya yang pasti belum),
   lalu `DML-2026-075`/`080` bila fitur API-nya memang mau dihidupkan, lalu
   tujuh DML "SEBAGIAN".
5. Tiga puluh DML sisanya ditinjau manual sebelum dijalankan — jangan dijalankan
   borongan hanya karena statusnya PENDING; 17 rekannya terbukti sudah berlaku.

## Tugas pengguna

- Tinjau `forward.sql`, lalu rekam ke SQL Tracker dan jalankan ke production.
- Jalankan blok VERIFIKASI dan simpan hasilnya (harus nol baris).
- Perbarui `database/ddl/status.json`: change ini menjadi `DONE`, dan ke-17 DML
  yang terbukti sudah berlaku dikoreksi statusnya agar ledger berhenti
  menyesatkan survei berikutnya.
