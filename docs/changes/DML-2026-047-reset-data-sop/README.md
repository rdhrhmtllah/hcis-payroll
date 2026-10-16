# DML-2026-047 — Reset data paket & dokumen SOP

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-047-reset-data-sop` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artefak DML | `database/dml/DML-2026-047-reset-data-sop/data-change.sql` |
| Desain | `plans/sop/2026-08-26-wizard-versi-dokumen-design.md` §6.1 |
| Rencana | `plans/sop/2026-08-26-wizard-versi-dokumen-plan.md` Task 1 |

## Alasan

Pendefinisian versi dokumen SOP dirombak menjadi wizard berlangkah. Dokumen dan
paket yang terlanjur dibuat lewat alur lama tak mewakili alur baru: sebagiannya
lahir sebelum tahap versi ada, sebagiannya lagi puing uji beban. Menguji alur
baru di atasnya berarti setiap keanehan harus diselidiki dua kali — apakah cacat
alur baru, atau bekas alur lama.

Permintaan datang langsung dari pemilik produk: *"untuk data yang sudah telanjur
terbuat hapus saja semua dulu paket dan dokumen yang sudah terbuat sehingga data
yang akan kita kelola benaran bersih."*

## Cakupan

Seluruh rantai dokumen dan paket **beserta jejak turunannya**. Menghapus paket &
dokumen tanpa menyapu jejaknya akan meninggalkan 2.079 baris progres dan 368
detail penjadwalan yang menunjuk id yang sudah tak ada.

### Objek terdampak

| # | Tabel | Baris (2026-08-26) |
|---|---|---|
| 1 | `N_SOP_Progress` | 2.079 |
| 2 | `N_SOP_Absensi` | 7 |
| 3 | `N_SOP_Kepatuhan_Riwayat` | 303 |
| 4 | `N_SOP_Coverage_Rollup` | 132 |
| 5 | `N_SOP_Penjadwalan_Detail` | 368 |
| 6 | `N_SOP_Penjadwalan_Integrasi` | 9 |
| 7 | `N_SOP_Penjadwalan` | 22 |
| 8 | `N_SOP_Favorit` | 1 |
| 9 | `N_SOP_Paket_Versi_Item` | 88 |
| 10 | `N_SOP_Paket_Versi` | 34 |
| 11 | `N_SOP_Paket_Item` | 72 |
| 12 | `N_SOP_Paket_Section` | 26 |
| 13 | `N_SOP_Paket_Draft_Item_Versi` | 2 |
| 14 | `N_SOP_Paket_Draft_Item` | 43 |
| 15 | `N_SOP_Paket_Draft_Section` | 11 |
| 16 | `N_SOP_Paket_Draft` | 11 |
| 17 | `N_SOP_Master_Paket` | 17 |
| 18 | `N_SOP_Item_Versi_Review_Lampiran` | 2 |
| 19 | `N_SOP_Item_Versi_Review` | 1 |
| 20 | `N_SOP_Item_Versi_Pengesah` | 34 |
| 21 | `N_SOP_Item_Versi_Berkas` | 8 |
| 22 | `N_SOP_Item_Versi` | 76 |
| 23 | `N_SOP_Dokumen` | 44 |
| 24 | `N_SOP_Log` | 745 |
| | **Total** | **4.135** |

### Sengaja TIDAK disentuh

`N_SOP_Kategori` (9) · `N_SOP_Master_APQC` (13) + `_Tipe_Item` (39) ·
`N_SOP_Master_Jenis_Dokumen` (7) + `_Tipe_Item` (8) ·
`N_SOP_Master_Tahap_Versi` (8) + `_Syarat` (2) + `_Transisi` (20) ·
`N_SOP_Setting` (7) · `N_SOP_Prioritas` (0) · `N_SOP_Assignment_Rule` (0) ·
`N_SOP_Automation_Run` (0) · `N_SOP_Kuis_Integrasi` (0) ·
`N_SOP_Aktivitas_Log` (0).

Library konten juga tak disentuh — ia milik modul lain, bukan "paket dan
dokumen".

## Cara

`DELETE` berpredikat di dalam satu transaksi, urut anak → induk. **Bukan
`TRUNCATE`** — `AGENTS.md` melarang agen menjalankannya, dan `TRUNCATE` tak
menghormati kunci asing sehingga kegagalan di tengah tak bisa dibaca.

Skrip memuat penjaga: bila salah satu master (`Tahap_Versi`, `Jenis_Dokumen`,
`Kategori`, `APQC`) berakhir kosong, seluruh transaksi di-`ROLLBACK` dan
melempar galat `50047`. Modul tanpa master lebih buruk daripada modul yang tak
jadi dibersihkan.

## Hubungan dengan DML-2026-048

`DML-2026-048` (tahap `APPROVED` pindah ke langkah 4) **wajib dijalankan
sesudah** change ini, dan skripnya menolak jalan bila `N_SOP_Item_Versi` belum
kosong. Urutan itu menghilangkan seluruh kebutuhan migrasi keadaan: tak ada
versi yang perlu dipindahkan tahapnya.

## Yang dikerjakan agen

- Menghitung baseline terhadap `Web_HRIS` development (angka di tabel di atas)
- Menulis `data-change.sql` dan dokumentasi ini
- Menjalankan skrip ke **development** dan memverifikasi seluruh tabel bernilai 0

## Sisa pekerjaan user

- Meninjau cakupan penghapusan
- Mencatat change ini di SQL Tracker
- Memutuskan apakah reset ini perlu dijalankan ke produksi sama sekali —
  **kemungkinan besar TIDAK.** Change ini lahir untuk membersihkan data
  percobaan di lingkungan pengembangan; menjalankannya di produksi akan
  menghapus dokumen SOP sungguhan beserta seluruh riwayat kepatuhan karyawan.
