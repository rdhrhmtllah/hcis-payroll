# DDL-2026-129 — Rekap INDEKS & CONSTRAINT production

**Tanggal survei:** 21 September 2026
**Sasaran:** production `emi_db_web_hr` (34.143.236.80)
**Cara survei:** koneksi READ ONLY; tidak satu pun pernyataan tulis dijalankan.
**Pasangan:** [DDL-2026-128](../DDL-2026-128-rekap-struktur-production/README.md) — struktur (tabel & kolom).

## Kenapa terpisah dari strukturnya

Struktur dan indeks adalah dua jenis pekerjaan yang berbeda, walau sama-sama
DDL. `CREATE TABLE` dan `ALTER TABLE ADD <kolom>` selesai dalam hitungan
milidetik dan nyaris tidak mengunci apa pun. Membangun indeks di atas tabel yang
sudah berisi banyak baris — `Transaksi_Cuti_Det`, `HRIS_Page_Access`,
`N_HRIS_Transaksi_Shift_Det` — memindai seluruh tabel dan mengunci penulisan
selama pembangunannya. Menggabungkan keduanya berarti bagian yang aman ikut
menunggu bagian yang berat, dan bila yang berat perlu dibatalkan, yang aman ikut
tergulung.

Karena itu: struktur naik lebih dulu dan cepat, indeks menyusul di jendela
waktunya sendiri.

## Isi berkas

`forward.sql` (1.598 baris) memuat **hanya indeks dan constraint**: 56
`CREATE INDEX`, 25 `ADD CONSTRAINT` (primary key dan default/check), dan 5
`DROP INDEX`. Nol `CREATE TABLE`, nol `ALTER TABLE … ADD <kolom>`.

Yang benar-benar belum ada di production: **36 indeks/constraint**.

| Kelompok | Jumlah | Tabel |
|---|---|---|
| Penyaring Master Dokumen SOP | 8 | `N_SOP_Dokumen` (jenis, pemilik, entitas, lingkup, APQC, departemen, nonaktif, tipe) |
| Aturan penugasan SOP | 1 | `N_SOP_Assignment_Rule` (unique) |
| Otorisasi & absensi yang selama ini heap | 11 | `N_HRIS_Role_Menu_Access`, `HRIS_Page_Access`, `N_HRIS_Grup_Fitur_Map`, `HRIS_Karyawan_Team`, `Transaksi_Cuti_Det`, `N_HRIS_Transaksi_Shift_Det` |
| Diagnostik permintaan | 2 | `N_HRIS_Request_Diagnostics` |
| Anti-replay LMS | 2 | `N_LMS_Api_Nonces`, `N_LMS_Api_Logs` |
| Visitor | 12 | tamu, permit, matriks, dan enam tabel jadwal inspeksi |

Lima `DROP INDEX` yang ikut: tiga bawaan DDL-2026-118 (penggantian indeks yang
definisinya kedaluwarsa, sudah ditinjau di change tersebut) dan dua penggantian
sejenis dari DDL-2026-102/062. Semuanya berpenjaga `IF EXISTS` dan hanya
menyentuh indeks — tidak ada `DROP TABLE`, `DROP COLUMN`, `TRUNCATE`, `UPDATE`,
`DELETE`, atau `MERGE`.

## Urutan dan prasyarat

**Jalankan SESUDAH DDL-2026-128.** Dua belas indeks di sini berdiri di atas
tujuh tabel Visitor yang baru lahir di berkas itu; bila strukturnya belum naik,
bagian tersebut berhenti pada penjaga `OBJECT_ID` — tidak menggagalkan berkas,
tetapi indeksnya juga tidak terpasang, dan blok VERIFIKASI akan menyebutnya
sebagai `TABELNYA BELUM ADA`.

Seluruh pernyataan berpenjaga (`IF NOT EXISTS` / `OBJECT_ID`), jadi berkas ini
aman diulang.

## Catatan teknis

Isinya disalin apa adanya dari berkas DDL yang sudah ditinjau dan dijalankan di
development. Satu penyesuaian: penjaga `IF DB_NAME() <> 'Web_HRIS'` pada
DDL-087/088/090 dicabut, sebab sasaran berkas ini memang production; penjaga
keberadaan objek tetap dipertahankan. Seluruh 101 batch divalidasi dengan
`SET PARSEONLY ON` — nol galat sintaks.

Dua temuan survei yang ditutup di sini:

1. **DDL-2026-053 tercatat production DONE, tetapi indeksnya tidak ada.**
   `UQ_N_SOP_Assignment_Rule_Paket` tidak ditemukan di production.
2. **`DDL-2026-062-versi-unik-hanya-yang-hidup` tidak punya entri ledger.**
   Nomor 062 sudah dipakai change lain, sehingga folder ini luput dicatat;
   indeksnya (`IX_N_SOP_Dokumen_Tipe`) juga belum ada di production.

## Tugas pengguna

- Jalankan DDL-2026-128 lebih dulu dan pastikan verifikasinya nol baris.
- Tinjau `forward.sql` ini, rekam ke SQL Tracker, jalankan pada jendela waktu
  yang menerima penguncian penulisan sesaat pada tabel-tabel besar.
- Jalankan blok VERIFIKASI INDEKS di akhir berkas; **harus nol baris**.
- Koreksi status DDL-2026-053 di ledger, dan daftarkan folder
  `DDL-2026-062-versi-unik-hanya-yang-hidup` yang selama ini tak tercatat.
