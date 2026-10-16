# DDL-2026-091 — Kolom `Tanggal_Akhir_Probation` hilang di production

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.
**Target:** production. Development sudah memilikinya.

## Gejalanya

Dua widget dashboard cuti mati di production:

```
CutiDashboardService::saldoAkanAktif failed
CutiDashboardService::karyawanBerpotensiKonflik failed
SQLSTATE[42S22]: Invalid column name 'Tanggal_Akhir_Probation'.
```

## Yang diperiksa

| lingkungan | kolom `Karyawan.Tanggal_Akhir_Probation` |
|---|---|
| development (`team311`/`Web_HRIS`) | **ada** |
| salinan lokal | **ada** |
| production | **tidak ada** (dari pesan galat) |

Bukan cacat kode — `CutiDashboardService` memilih kolom yang memang ada di skema
resmi. Ini **drift skema**.

## Kenapa driftnya bisa terjadi

Tidak ada satu pun berkas di `database/ddl/` yang pernah menambahkan kolom ini.
Ia masuk ke development lewat ALTER langsung, di luar alur DDL, sehingga tak
pernah ada artefak yang bisa dijalankan ke production.

## Definisinya

`datetime`, NULL diizinkan, tanpa default. Terisi **1 dari 965 baris** di
development — jadi praktis kosong; yang dibutuhkan production hanya
keberadaannya agar kueri tidak gagal.

## Risiko

Menambah kolom NULL tanpa default adalah perubahan metadata saja di SQL Server:
tanpa penulisan ulang tabel dan tanpa lock lama. Tidak ada data yang berubah.

## Pekerjaan pengguna

1. Review `forward.sql`; setujui atau tolak.
2. Eksekusi di production lewat SQL Tracker (development sudah punya kolomnya;
   blok BAGIAN 1 bersyarat sehingga aman dijalankan di sana sebagai no-op).
3. Putuskan terpisah apakah nilainya perlu diisi — itu DML, bukan bagian ini.
4. Jalankan `php artisan db:inventaris-skema` untuk memeriksa drift kolom lain;
   yang ini kemungkinan bukan satu-satunya.
