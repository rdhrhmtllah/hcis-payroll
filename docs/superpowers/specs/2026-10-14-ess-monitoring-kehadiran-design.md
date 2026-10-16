# Monitoring Kehadiran ESS untuk Admin HC Personel — Tahap 1

Tanggal: 14 Okt 2026 · Modul: ESS › Attendance · Status: rancangan disetujui per bagian, menunggu tinjauan spec

## 1. Tujuan

Admin HC Personel belum punya tempat untuk melihat gambaran kehadiran secara utuh. Halaman admin yang ada (Izin, Cuti, Lembur, Shift, Konversi, Pengajuan Shift, Perbaikan Absensi) berorientasi **tindakan per transaksi**. Halaman baru ini berorientasi **pemantauan**:

1. **Hari Ini** — siapa sudah/belum absen, terlambat, pulang cepat, izin, sakit, cuti, day off, lembur hari ini.
2. **Rekap Periode** — per karyawan dan per kelompok selama satu periode cut-off, bahan payroll/laporan.

Di luar lingkup tahap ini (disepakati user): **Perlu Tindakan** (tahap 2, spec sendiri) dan **Profil 360° karyawan** (tidak diminta).

Keberhasilan: admin bisa menjawab "siapa yang belum absen sekarang" dan "siapa paling sering terlambat periode ini" dalam satu halaman, dengan angka yang sama dengan yang dihitung sistem absensi.

## 2. Sumber data tunggal

`dbo.N_HRIS_Absen_Snapshot` — satu baris per (perusahaan, karyawan, tanggal), dihitung ulang terus oleh pemrosesan absensi. Production 14 Okt 2026: ±1.224 baris/hari untuk 1.231 karyawan aktif; baris hari ini terakhir dihitung beberapa menit sebelum dicek.

Aturan baca kolom (diverifikasi pada data production 13 Okt 2026):

| Kolom | Arti |
|---|---|
| `StatusHari` | `WORK` / `OFF` (sudah disederhanakan oleh pemrosesan) |
| `CheckIn`, `CheckOut` | NULL = belum ada tap |
| `RealLate`, `RealEarly` | menit terlambat / pulang cepat sebenarnya (`TLate` = dibulatkan untuk denda, tidak dipakai) |
| `No_Transaksi_Cuti/Izin/Sakit/Lembur/...` | `'-'` atau NULL = tidak ada |
| `Status_Kehadiran` | `CUTI`, `SAKIT`, `IZIN TERLAMBAT`, `IZIN PULANG CEPAT`, NULL |
| `Flag_Alpha` | `Y` = alpha |
| `Koefisien_OT` | **jam lembur dibayar** (sama dengan kartu Total Lembur homepage) |
| `Flag_Konversi_Lembur` | `Y` = lembur dikonversi |
| `Flag_Generate_Ulang` | `Y` = baris menunggu hitung ulang |
| `Last_Calculated_At` | waktu hitung terakhir |

Atribut organisasi (divisi, departemen, jabatan, level, lokasi) diambil dari `Karyawan` + `View_Divisi_Sub_Divisi` + `View_Golongan_Sub_Golongan_Level_Jabatan` (bukan dari kolom teks snapshot), sama dengan halaman admin lain.

## 3. Akses & cakupan

- Halaman baru `essMonitoringKehadiranPage`, menu **"Monitoring Kehadiran"** di header *Administrator HCIS*, grup ESS (`Id_Grup_Fitur = 1`), `Flag_Hibah_Role = 'T'`. Akses awal disalin dari pemegang eksplisit **Izin Admin** (`IzinPageAdmin`) via DML.
- Rute di-gate `check.jabatan:essMonitoringKehadiranPage`.
- **Cakupan data mengikuti cakupan admin yang ada** (keputusan user): baris karyawan dibatasi `FunctionalRole::applyScope()` pada join `Karyawan`, aturan yang sama dengan daftar karyawan di halaman admin lain.
- Murni baca; tidak ada aksi ubah data di halaman ini — aksi dilakukan lewat tautan ke halaman admin terkait.

## 4. Tab "Hari Ini"

Tanggal = hari ini (zona Asia/Jakarta); bisa diganti ke tanggal lain untuk melihat hari kemarin dengan aturan yang sama.

Setiap karyawan masuk **tepat satu** kategori, urutan dicek:

| # | Kategori | Aturan |
|---|---|---|
| 1 | Cuti | `No_Transaksi_Cuti` ada |
| 2 | Sakit / Izin | `No_Transaksi_Sakit` atau `No_Transaksi_Izin` ada |
| 3 | Day Off / Libur | `StatusHari = 'OFF'` |
| 4 | Hadir — tepat waktu / terlambat | `CheckIn` ada; terlambat bila `RealLate > 0`, ditandai "berizin" bila `Status_Kehadiran = 'IZIN TERLAMBAT'` |
| 5 | Belum absen | `WORK`, `CheckIn` NULL, jam masuk shift sudah lewat (untuk tanggal lampau: selalu) |
| 6 | Belum jam masuk | `WORK`, `CheckIn` NULL, jam masuk belum tiba (hanya untuk hari ini) |

Angka tambahan (tidak eksklusif): Pulang cepat (`RealEarly > 0`), Lembur (`Flag_Lembur = 'Y'` atau `Koefisien_OT > 0`), Sedang diperbarui (`Flag_Generate_Ulang = 'Y'`).

Daftar: kolom karyawan (nama, kode, jabatan, departemen, lokasi), shift + jam, check-in/out, menit terlambat, status, keterangan (nomor transaksi), tautan aksi (Belum absen → Perbaikan Absensi; Izin/Sakit → Izin Admin; Cuti → Cuti Admin; Lembur → Lembur Admin). Kartu menyaring daftar; saringan lokasi, divisi, departemen, jabatan, shift, pencarian. Paginasi server.

Kesegaran: tampilkan "data per pukul …" (maks `Last_Calculated_At`), muat ulang otomatis tiap 5 menit + tombol segarkan.

## 5. Tab "Rekap Periode"

Periode: pilihan periode cut-off (`services.hcis.cutoff_payroll_date`, bawaan 16 → 15; default periode berjalan) atau rentang tanggal bebas (maks 62 hari).

**Per karyawan** — satu baris per orang:

| Kolom | Aturan |
|---|---|
| Hari terjadwal | `StatusHari = 'WORK'` |
| Hadir | WORK dengan `CheckIn` |
| Terlambat | kejadian & total menit `RealLate > 0`; berizin dipisah |
| Pulang cepat | kejadian & total menit `RealEarly > 0`; berizin dipisah |
| Sakit / Izin / Cuti | hari per jenis dari nomor transaksinya |
| Alpha | `Flag_Alpha = 'Y'` |
| Lupa check-out | `CheckIn` ada, `CheckOut` NULL (tanggal sebelum hari ini) |
| Lembur | `SUM(Koefisien_OT)` jam + jumlah hari |
| Konversi lembur | hari `Flag_Konversi_Lembur = 'Y'` |

Bisa diurutkan per kolom; klik karyawan → rincian harian periode itu (tanggal, shift, check-in/out, status, nomor transaksi).

**Per kelompok** — tombol ganti ke rangkuman per divisi / departemen / lokasi: jumlah karyawan, % hadir (hadir ÷ terjadwal), total menit terlambat, total alpha, total jam lembur.

Peringatan bila periode masih punya baris `Flag_Generate_Ulang = 'Y'`: "sebagian data sedang diperbarui (N baris)".

**Ekspor Excel** lewat ekspor terpusat yang ada (`N_HRIS_Export_Jobs`), diproses di latar belakang.

## 6. Arsitektur

```
routes/ESS/Attendance/Monitoring/MonitoringKehadiran.php
app/Http/Controllers/ESS/Attendance/Monitoring/MonitoringKehadiranController.php
app/Services/ESS/Attendance/Monitoring/
    MonitoringKehadiranQuery.php      basis kueri snapshot + join organisasi + cakupan + saringan
    KategoriKehadiran.php             aturan kategori (murni, diuji unit) + padanan SQL CASE
    MonitoringHariIniService.php      ringkasan kartu + daftar paginasi
    RekapPeriodeService.php           periode cut-off, rekap per karyawan/kelompok, rincian harian
    RekapPeriodeExport.php            dataset ekspor terpusat
resources/js/Pages/ESS/Attendance/Monitoring/MonitoringKehadiran.vue   (tab, saringan bersama)
resources/js/Pages/ESS/Attendance/Monitoring/Components/TabHariIni.vue
resources/js/Pages/ESS/Attendance/Monitoring/Components/TabRekapPeriode.vue
```

Endpoint (semua GET, JSON): `/admin/monitoring-kehadiran` (halaman + opsi saringan), `/data/hari-ini`, `/data/rekap`, `/data/rekap-kelompok`, `/data/rincian/{kode}`; POST `/ekspor/rekap` (antre ekspor).

Aturan kategori ditulis **sekali** sebagai SQL `CASE` (dipakai hitung & saring di server) dengan padanan PHP murni untuk uji unit, agar kartu dan daftar tak pernah berbeda.

Komponen UI memakai design system HCIS (HcisHeader, HcisTabs, HcisStatGroup/HcisStatCard, HcisFilterBar, HcisTable, HcisPagination, HcisDrawer untuk rincian) — tanpa warna/z-index mentah.

## 7. Kinerja

Diukur di production (baca-saja, 14 Okt 2026): kueri Hari Ini 167 ms; rekap satu periode per karyawan 1.327 ms (RTT ±27 ms). Semua indeks snapshot diawali karyawan/user, tidak ada yang diawali tanggal.

- **DDL baru (diusulkan):** indeks `IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal (Kode_Perusahaan, Tanggal) INCLUDE (Kode_Karyawan, StatusHari, Jam_Masuk, CheckIn, CheckOut, RealLate, RealEarly, Status_Kehadiran, Flag_Alpha, Flag_Lembur, Koefisien_OT, Flag_Konversi_Lembur, Flag_Generate_Ulang, Last_Calculated_At, No_Transaksi_Cuti, No_Transaksi_Izin, No_Transaksi_Sakit, Nama_Shift)`. Dijalankan di dev setelah persetujuan; angka sebelum/sesudah dicatat. Production oleh user lewat SQL Tracker.
- Rekap periode yang **sudah tutup** (tanggal akhir < hari ini dan tanpa baris kotor) di-cache 30 menit per (periode, cakupan, saringan); periode berjalan tidak di-cache.
- Jumlah kueri per permintaan tetap (tak bergantung jumlah karyawan); `whereIn` saringan dibatasi 200 nilai (batas 2.100 parameter SQL Server).
- Baca memakai `WITH (NOLOCK)` seperti pembaca snapshot lain (RCSI mati di Web_HRIS), agar tak antre di belakang pemrosesan absensi.

## 8. Database (DDL/DML)

| Change | Isi |
|---|---|
| DDL-2026-192 | indeks pada `N_HRIS_Absen_Snapshot` (§7) |
| DML-2026-193 | menu `essMonitoringKehadiranPage` + grup fitur + akses dari pemegang `IzinPageAdmin` |

Nomor dicek ulang terhadap semua branch remote sebelum dibuat.

## 9. Pengujian

- Unit: aturan kategori (`KategoriKehadiran`) untuk setiap kategori & urutan prioritas, termasuk `'-'` sebagai kosong dan "belum jam masuk" vs "belum absen"; periode cut-off (16→15, lintas tahun); normalisasi saringan.
- Fitur: gerbang akses (tanpa hak → 403), cakupan admin membatasi baris, kartu = jumlah baris daftar per kategori, rekap per karyawan = penjumlahan rincian hariannya.
- Vitest: kartu menyaring daftar, ganti tab mempertahankan saringan, peringatan "sedang diperbarui", muat ulang otomatis dihentikan saat halaman ditinggal.
- Verifikasi angka terhadap production (baca-saja) untuk satu tanggal & satu periode.

## 10. Tahap berikutnya (bukan bagian spec ini)

Tab **Perlu Tindakan**: lupa check-out, alpha berulang, pengajuan menggantung di approver, karyawan tanpa shift/approver, lembur tanpa absen.
