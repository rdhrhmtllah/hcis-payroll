# DML-2026-119 — Izin koreksi target KPI

| | |
| --- | --- |
| Change ID | `DML-2026-119-izin-koreksi-target-kpi` |
| Tipe | DML |
| Target | `emi_db_web_hr` (**production**) |
| Objek | `dbo.HRIS_Page_Access` (INSERT, 5 baris) |
| Artefak | `database/dml/DML-2026-119-izin-koreksi-target-kpi/data-change.sql` |
| Verifikasi | `database/dml/DML-2026-119-izin-koreksi-target-kpi/verify.sql` |
| Status | `REVIEW_REQUIRED` |

## Latar

Fitur "Perbaiki Target" pada **Monitoring › KPI Submission** — mengoreksi nilai
target untuk pengajuan yang belum diisi realisasinya atau yang realisasinya sudah
dibatalkan — dilaporkan hilang dari layar. Penelusuran 16 September 2026
menunjukkan fiturnya tidak hilang dan tidak tertinggal di branch lain.

Seluruh bagiannya ada di `master` dan di worktree ini, dari enam commit
3–9 September 2026:

| Bagian | Berkas |
| --- | --- |
| Aturan bisnis | `app/Services/KPI/KpiTargetKoreksiService.php` |
| Endpoint | `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiTargetKoreksiController.php` |
| Rute | `routes/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php` (di-`require` di `routes/web.php:157`) |
| Layar | modal "Perbaiki Target" di `resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue` |
| Uji | `KoreksiTargetGerbangTest`, `KoreksiTargetIzinTest`, `KoreksiTargetTerapkanTest` |
| Tabel jejak | `dbo.N_KPI_Target_Koreksi` — sudah ada di production |

## Sebab fitur tidak terlihat

Kunci izin `kpiKoreksiTarget` **belum pernah diberikan kepada siapa pun**.
Diukur READ ONLY di production, 16 September 2026:

| Tabel | Baris untuk `kpiKoreksiTarget` | Pembanding `kpiEnforcementMonitoring` |
| --- | --- | --- |
| `HRIS_Page_Access` | 0 | 5 aktif |
| `N_HRIS_Menus` | 0 | — |
| `N_HRIS_Grup_Fitur_Map` | 0 *(disengaja)* | 1 |
| `N_KPI_Target_Koreksi` | 0 baris data | — |

Karena itu `/api/v1/kpi-enforcement/target/kelayakan` menjawab 403 untuk semua
orang. Layar **gagal-tertutup**: tanpa jawaban kelayakan tidak ada sel yang boleh
disunting, dan 403 sengaja tidak ditulis ke console
(`kpiEnforcementDashboard.vue`, blok `catch` pada `muatKelayakanTarget`). Tombol
"Perbaiki Target" hanya muncul bila ada target yang layak, sehingga hasil
akhirnya: fitur yang ada terlihat persis seperti fitur yang tidak ada.

## Kenapa per orang, bukan lewat role

Disengaja, dan alasannya tertulis di berkas rutenya sendiri. Satu role memberi
**seluruh** halaman dalam grup fiturnya sekaligus, jadi mendaftarkan
`kpiKoreksiTarget` di `N_HRIS_Grup_Fitur_Map` berarti memberikan kuasa mengubah
angka penilaian kepada setiap pemegang role KPI mana pun. Seluruh titik desain
fitur ini adalah bahwa **memantau tidak sama dengan boleh mengubah angka**.

Verifikasi nomor 4 menjaga pemisahan itu: `N_HRIS_Grup_Fitur_Map` harus tetap
kosong untuk kunci ini.

## Penerima

Keputusan pemilik proses, 16 September 2026: seluruh pemegang
`kpiEnforcementMonitoring` yang aktif saat ini.

| UserID_Web | Kode_Karyawan | Nama |
| --- | --- | --- |
| 285 | RIDHO RAHMAT | RIDHO RAHMATULLAH |
| 367 | ABDUL RACHMAN | ABDUL RACHMAN |
| 899 | OKKY | OKKY ADMIRAL NUGRATAMA |
| 1093 | QORY AZZAHRA | QORY AZZAHRA |
| 1836 | DESTU SYAH | DESTU SYAH INANDA |

Daftarnya ditulis eksplisit di SQL, **bukan** diturunkan dari "siapa pun
pemegang `kpiEnforcementMonitoring`". Kalau diturunkan, orang keenam yang diberi
izin memantau besok ikut mendapat kuasa mengoreksi tanpa seorang pun
memutuskannya.

`Kode_Perusahaan`, `ID_Level`, dan `ID_Divisi` ketiganya `NOT NULL` dan
menggambarkan orangnya, bukan halamannya. Nilainya disalin dari baris akses milik
orang itu sendiri yang paling baru — tidak ada angka yang ditebak.

## Sifat eksekusi

- Aman dijalankan ulang: baris yang sudah ada dilewati (`NOT EXISTS`).
- Berjalan dalam satu transaksi dengan penjaga: bila tidak tepat 5 penerima yang
  berakhir memegang izin aktif, seluruhnya di-`ROLLBACK` dan `RAISERROR`. Lebih
  baik nol daripada empat dari lima, karena selisih diam-diam pada izin baru
  ketahuan berminggu-minggu kemudian.
- Tidak ada perubahan skema. Tidak ada `UPDATE`/`DELETE`.

## Dikerjakan agen

- Menyusun `data-change.sql` dan `verify.sql`.
- Menyusun dokumen ini dan mendaftarkan perubahan di `database/ddl/status.json`.
- Seluruh penelusuran ke production **READ ONLY**; agen tidak menjalankan satu
  pun pernyataan pengubah data.

## Tugas pengguna

1. Tinjau daftar penerima — ini pemberian kuasa mengubah angka penilaian.
2. Jalankan `data-change.sql` ke production melalui SQL Tracker.
3. Jalankan `verify.sql`; harapkan 5 periksa, 5 LULUS, 0 GAGAL.
4. Minta salah satu penerima membuka **Monitoring › KPI Submission → Detail
   Pengajuan**; tombol "Perbaiki Target" harus muncul pada pengajuan yang punya
   target layak koreksi.
5. Perbarui status di `database/ddl/status.json` mengikuti hasil sebenarnya.

## Catatan lanjutan (di luar cakupan perubahan ini)

Layar menelan 403 tanpa jejak sama sekali. Selama itu dibiarkan, "fitur belum
diberi izin" tidak bisa dibedakan dari "fitur belum ada" — persis kebingungan
yang memicu penelusuran ini. Perbaikannya (mencatat penolakan izin tanpa
menampilkan apa pun ke pengguna biasa) sebaiknya jadi pekerjaan tersendiri.
