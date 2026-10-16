# Monitoring Visitor per domain: Tamu | Permit, masing-masing Dashboard + All Monitoring

- Tanggal: 2026-08-25
- Modul: `VISITOR` / submodul `Workflow` / fitur `Reporting`
- Tanpa DDL, tanpa perubahan menu, tanpa kunci halaman baru

## Masalah

Halaman Monitoring mencampur tamu dan permit dalam dua tab datar (Dashboard,
Papan harian). Admin yang sedang mengurus permit tetap melihat antrean tamu, dan
tidak ada daftar lengkap berpaginasi untuk menelusuri data di luar hari ini.

## Struktur

```
Monitoring Visitor
├── Tamu ────────┬── Dashboard        chart & tabel tamu
│                └── All Monitoring   daftar paginasi + filter + cari + urut
└── Permit ──────┬── Dashboard        chart & tabel permit
                 └── All Monitoring   daftar paginasi + filter + cari + urut
```

Tab luar memakai `VisitorWorkspace` yang sudah ada; sub-tab memakai `HcisTabs`
di dalam panel — pola yang sudah dipakai halaman Export Studio.

## Keadaan yang ditemukan sebelum merancang

**Endpoint daftarnya sudah ada dan sudah berfilter.** "All Monitoring" karena itu
bukan fitur baru melainkan menghidupkan kembali tampilan yang dicabut pada commit
`ccc4d967`, kali ini per-domain dan memakai kit Hcis.

| Endpoint | Filter yang sudah ada |
|---|---|
| `laporanvisitor-tamu` | rentang tanggal, status, keperluan, paginasi, stats |
| `laporanvisitor-permit` | kategori, status berlaku, jenis kendaraan, paginasi |

Yang belum ada: pencarian dan pengurutan yang bisa dipilih.

**Hampir semua blok dashboard sudah satu domain**, jadi pemisahannya pemindahan,
bukan penulisan ulang.

## Pembagian blok

| Blok | Tujuan |
|---|---|
| Tren kunjungan + Jam sibuk kedatangan | Tamu |
| Kesehatan data | Tamu |
| Papan harian kelompok tamu | Tamu |
| Permit per kategori + akan kedaluwarsa | Permit |
| Papan harian kelompok permit | Permit |

Dua blok bercampur dan **disaring per domain**:

- **Kartu keadaan** — "di dalam area" dan "terjadwal belum datang" murni tamu;
  "antrian approval" mencakup keduanya.
- **Daftar tindakan** — antrean approval kedua domain.

`VisitorDashboardOperasionalService::queryAntrianApproval()` menyaring ke ranah
visitor lewat `EXISTS ... OR EXISTS ...` ke kedua tabel transaksi, jadi penyaringan
per domain cukup memilih satu cabang. Ditambahkan sebagai parameter opsional
`?string $domain`; tanpa argumen perilakunya persis seperti sekarang, sehingga
pemanggil lama tidak berubah.

## All Monitoring

| | Tamu | Permit |
|---|---|---|
| Filter | rentang tanggal, status, keperluan | kategori, status berlaku, jenis kendaraan |
| Cari | nama tamu, instansi, no transaksi | pemohon, plat, no transaksi |
| Urut | tanggal kunjungan, jam masuk, nama | tanggal kedaluwarsa, mulai berlaku, pemohon |

Pengurutan memakai **daftar putih kolom**, bukan nilai mentah dari permintaan:
menyambung input pengguna ke `ORDER BY` adalah lubang injeksi, dan kolom yang tak
dikenal harus jatuh ke urutan baku, bukan menghasilkan galat SQL.

## Bug yang ikut diperbaiki

Stats `getLaporanTamu` membaca `d.Waktu_Aktual_Keluar` / `d.Waktu_Aktual_Masuk`
**tanpa COALESCE** — dua pembaca terakhir dari sensus 2026-08-25 yang belum
diperbaiki. Akibatnya "sudah keluar" dan "tidak pernah datang" tidak menghitung
tamu yang lewat palang. Masuk lingkup karena All Monitoring menampilkan angka itu.

Perbaikannya lewat `VisitorKehadiranQueryService`, bukan COALESCE yang ditulis
ulang di tempat.

## Uji

| Yang diuji | Kenapa |
|---|---|
| Tiap panel merender bloknya sendiri dan BUKAN milik domain tetangga | Pemisahan yang hanya di judul lolos bila hanya diperiksa "ada isinya" |
| Antrian per domain memuat miliknya dan membuang milik tetangga | Penyaring diuji dua sisi |
| Filter & urutan benar-benar MENGUBAH hasil | Parameter yang terkirim tapi diabaikan lolos uji yang hanya memeriksa status 200 |
| Halaman 2 berisi baris berbeda dari halaman 1 | Paginasi yang mengembalikan halaman yang sama lolos uji jumlah baris |
| Kolom urut tak dikenal jatuh ke baku, bukan galat | Nilai dari permintaan tidak boleh menyentuh ORDER BY |
| Stats tamu menghitung kehadiran dari palang | Wajib merah dulu |

## Sisa pekerjaan pengguna

Tidak ada langkah basis data.
