# Monitoring Kehadiran — Visualisasi (heatmap, tren, komposisi, peringkat)

Tanggal: 14 Okt 2026 · Modul: ESS › Attendance › Monitoring · Lanjutan dari
`2026-10-14-ess-monitoring-kehadiran-design.md` (PR #741) · Rancangan disetujui user di chat.

## Tujuan
Admin HC Personel melihat pola, bukan hanya daftar: unit dan tanggal yang bermasalah,
tren sepanjang periode, gelombang kedatangan hari ini. Semua angka grafik berasal dari
basis kueri yang sama dengan tabel (cakupan admin, saringan, batas sampai hari ini, alpha
hanya tanggal lampau), sehingga grafik dan tabel tak pernah berselisih.

## Tampilan
**Tab Hari Ini** — baris grafik di atas daftar:
- Donut komposisi kategori (dari `ringkasan.kategori`, tanpa kueri baru), warna = warna kartu.
- Histogram jam check-in per 15 menit + penanda jam masuk shift terbanyak.

**Tab Rekap Periode** — tampilan baru "Grafik" di samping per karyawan/divisi/departemen/lokasi:
1. Tren harian: batang bertumpuk Tepat waktu, Terlambat, Sakit/Izin, Cuti, Alpha per tanggal.
2. Heatmap: baris = unit (divisi/departemen/lokasi, dipilih), kolom = tanggal; metrik
   % tidak hadir / terlambat (kali) / alpha. Makin gelap = makin buruk. Maks 25 unit terburuk
   (sisanya disebut jumlahnya). Klik sel → tab Hari Ini pada tanggal itu dengan saringan unit itu.
3. Peringkat unit: bar horizontal 10 terburuk untuk % tidak hadir, menit terlambat, jam lembur.

## Data
- `GET /admin/monitoring-kehadiran/data/grafik?dari&sampai&dimensi&<saringan>` →
  `{ tren: [{tanggal, terjadwal, hadir, hadir_terlambat, sakit, izin, cuti, alpha}],
     sel: [{kelompok, tanggal, terjadwal, hadir, terlambat_kali, alpha}],
     kelompok: <rekapKelompok> }` — tiga GROUP BY di atas `MonitoringKehadiranQuery::basis`,
  memakai agregat yang sama dengan rekap; cache 30 menit untuk periode tutup & bersih.
- `GET /data/hari-ini` menambah `grafik: { sebaran: [{menit, jumlah}], jam_masuk_umum }`
  (dua kueri kecil, saringan sama dengan kartu).
- Agregat baru `hadir_terlambat` (WORK, check-in, RealLate > 0) agar tumpukan tren tepat.

## Komponen
- `Components/grafikMonitoring.js` — pembentuk data grafik murni (diuji unit Vitest).
- `Components/GrafikHariIni.vue`, `Components/GrafikRekap.vue` — memakai `HcisChart`.
  Warna kategori dibaca dari token CSS (`--hcis-*-500`) saat jalan; tanpa warna mentah.

## Pengujian
- PHPUnit: tren per tanggal dijumlahkan = rekap per karyawan dijumlahkan; sel heatmap satu
  unit = rekap kelompok unit itu; sebaran check-in total = jumlah baris ber-check-in.
- Vitest: pembentuk heatmap (urut terburuk, batas 25, % tidak hadir), peringkat 10 terburuk,
  klik sel memindah ke tab Hari Ini dengan tanggal & saringan unit.

## Rencana implementasi (ringkas)
1. Backend: `hadir_terlambat`, `RekapPeriodeService::grafik()`, `MonitoringHariIniService::grafik()`,
   endpoint + rute; uji fitur.
2. Frontend: `grafikMonitoring.js` + uji; `GrafikHariIni.vue`; `GrafikRekap.vue`; integrasi
   tab & drill-down; uji Vitest.
3. Verifikasi: suite Monitoring + guard design system; push ke PR #741.
