# Monitoring Kehadiran ESS — Tahap 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman admin "Monitoring Kehadiran" dengan tab Hari Ini dan Rekap Periode, dibaca dari `N_HRIS_Absen_Snapshot`, terbatas cakupan admin.

**Architecture:** Satu basis kueri (`MonitoringKehadiranQuery`) menyatukan snapshot + organisasi + cakupan + saringan; aturan kategori ditulis sekali (`KategoriKehadiran`, PHP murni + padanan SQL CASE). Dua service baca (Hari Ini, Rekap) dan satu service ekspor menumpang `AbstractExportService`. UI Inertia/Vue 3 dengan design system HCIS.

**Tech Stack:** Laravel 10, SQL Server (Web_HRIS dev), Inertia + Vue 3 Options API, design system `@/components/ui`, PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-14-ess-monitoring-kehadiran-design.md`

## Global Constraints

- Modul ESS › Attendance; berkas di `app/Services/ESS/Attendance/Monitoring/`, `app/Http/Controllers/ESS/Attendance/Monitoring/`, `routes/ESS/Attendance/Monitoring/`, `resources/js/Pages/ESS/Attendance/Monitoring/`.
- Sumber tunggal `dbo.N_HRIS_Absen_Snapshot` dibaca `WITH (NOLOCK)`; `Kode_Perusahaan = '001'`.
- Kolom nomor transaksi kosong = NULL, `''`, atau `'-'`.
- Cakupan admin = `FunctionalRole::applyScope()` diterapkan pada subkueri `Karyawan` (kolom scope tak berawalan tabel), bukan pada kueri ber-join.
- Rentang tanggal bebas maks 62 hari; periode cut-off 16 → 15 (`services.hcis.cutoff_payroll_date`).
- `whereIn` saringan maks 200 nilai per kunci.
- UI hanya token design system (tanpa warna/z-index mentah, tanpa `<i class="bi …">` mentah — pakai `HcisIcon`).
- DDL/DML hanya dijalankan di dev setelah persetujuan user; nomor DDL-2026-192, DML-2026-193.
- Tanpa subagent (preferensi user); eksekusi inline.

## Review Focus

1. Tanggal lampau: "belum jam masuk" tak boleh muncul — semua tanpa check-in di hari kerja = belum absen. → uji di Task 1.
2. `StatusHari` NULL (baris jadwal tak lengkap, 6 baris/hari di production) harus terlihat sebagai kategori sendiri, bukan hilang. → uji di Task 1.
3. Periode cut-off lintas tahun (16 Des → 15 Jan) dan tanggal tepat 15/16. → uji di Task 2.
4. Ekspor di antrean tanpa sesi login tetap memakai cakupan pemohon. → uji di Task 5.
5. Jumlah kartu per kategori = jumlah baris daftar dengan kategori itu. → uji di Task 3.

---

### Task 1: Aturan kategori kehadiran

**Files:**
- Create: `app/Services/ESS/Attendance/Monitoring/KategoriKehadiran.php`
- Test: `tests/Unit/ESS/Attendance/Monitoring/KategoriKehadiranTest.php`

**Interfaces:**
- Produces: `KategoriKehadiran::tentukan(array $baris, string $jamAcuan): string`; `KategoriKehadiran::sqlCase(string $alias = 's'): string` (memakai satu binding `?` = jam acuan `H:i:s`); `KategoriKehadiran::ada(?string $no): bool`; konstanta `CUTI, SAKIT_IZIN, LIBUR, TEPAT_WAKTU, TERLAMBAT, BELUM_ABSEN, BELUM_JAM, TANPA_JADWAL`, `URUTAN`; `KategoriKehadiran::jamAcuan(string $tanggal, Carbon $sekarang): string`.

- [ ] Step 1: tulis uji — tiap kategori, urutan prioritas (cuti mengalahkan off & check-in), `'-'` = kosong, jam acuan (hari ini = jam sekarang, lampau = `23:59:59`, depan = `00:00:00`), `StatusHari` NULL = `TANPA_JADWAL`, jam masuk `HH:MM` dan `HH:MM:SS`.
- [ ] Step 2: jalankan `php vendor/bin/phpunit tests/Unit/ESS/Attendance/Monitoring/KategoriKehadiranTest.php` → FAIL (kelas belum ada).
- [ ] Step 3: implementasi (lihat kode di bawah), sqlCase memakai `TRY_CAST(... AS time)`.
- [ ] Step 4: jalankan lagi → PASS; jalankan `sqlCase` pada dev untuk satu tanggal dan bandingkan dengan `tentukan` per baris (harus identik).
- [ ] Step 5: commit.

```php
public static function tentukan(array $r, string $jamAcuan): string
{
    if (self::ada($r['No_Transaksi_Cuti'] ?? null)) return self::CUTI;
    if (self::ada($r['No_Transaksi_Sakit'] ?? null) || self::ada($r['No_Transaksi_Izin'] ?? null)) return self::SAKIT_IZIN;
    $status = strtoupper(trim((string) ($r['StatusHari'] ?? '')));
    if ($status === '') return self::TANPA_JADWAL;
    if (str_starts_with($status, 'OFF')) return self::LIBUR;
    if (! empty($r['CheckIn'])) return ((float) ($r['RealLate'] ?? 0)) > 0 ? self::TERLAMBAT : self::TEPAT_WAKTU;
    $masuk = self::normalJam((string) ($r['Jam_Masuk'] ?? ''));
    return $masuk !== null && $masuk > $jamAcuan ? self::BELUM_JAM : self::BELUM_ABSEN;
}
```

### Task 2: Periode cut-off & saringan

**Files:**
- Create: `app/Services/ESS/Attendance/Monitoring/PeriodeCutoff.php`, `app/Services/ESS/Attendance/Monitoring/SaringanMonitoring.php`
- Test: `tests/Unit/ESS/Attendance/Monitoring/PeriodeCutoffTest.php`, `tests/Unit/ESS/Attendance/Monitoring/SaringanMonitoringTest.php`

**Interfaces:**
- Produces: `PeriodeCutoff::untuk(Carbon $tgl, int $mulai = 16, int $akhir = 15): array{mulai:string, akhir:string, label:string}`; `PeriodeCutoff::daftar(Carbon $acuan, int $jumlah = 12): array`; `SaringanMonitoring::normalisasi(array $f): array` (kunci: divisi, departemen, jabatan, lokasi, shift = list ≤200; search; kategori ∈ URUTAN atau null; page ≥1; limit 10–100; urut; arah); `SaringanMonitoring::rentang(?string $dari, ?string $sampai): array{dari,sampai}` (lempar `InvalidArgumentException` bila > 62 hari atau terbalik).

- [ ] Step 1: uji — 15 Okt → 16 Sep–15 Okt; 16 Okt → 16 Okt–15 Nov; 5 Jan 2027 → 16 Des 2026–15 Jan 2027; daftar 12 periode menurun; rentang 63 hari ditolak; nilai asing/duplikat dibuang.
- [ ] Step 2: FAIL. Step 3: implementasi. Step 4: PASS. Step 5: commit.

### Task 3: Basis kueri + service Hari Ini

**Files:**
- Create: `app/Services/ESS/Attendance/Monitoring/MonitoringKehadiranQuery.php`, `app/Services/ESS/Attendance/Monitoring/MonitoringHariIniService.php`
- Test: `tests/Feature/ESS/Attendance/Monitoring/MonitoringHariIniTest.php`

**Interfaces:**
- Consumes: Task 1, Task 2.
- Produces: `MonitoringKehadiranQuery::basis(string $dari, string $sampai, array $f, FunctionalRole $role): Builder` (alias `s`, `k`, `vd`, `lvl`); `MonitoringKehadiranQuery::opsiSaringan(FunctionalRole $role): array`; `MonitoringHariIniService::ringkasan(string $tanggal, array $f, FunctionalRole $role): array{total,kategori:array<string,int>,pulang_cepat,lembur,diperbarui,data_per}`; `MonitoringHariIniService::daftar(string $tanggal, array $f, FunctionalRole $role): array{items,total,page,limit}`.

- [ ] Step 1: uji fitur (data dev, `DatabaseTransactions`) — jumlah per kategori di ringkasan = total daftar bersaring kategori itu; total = jumlah baris snapshot tanggal itu dalam cakupan; saringan lokasi mempersempit.
- [ ] Step 2: FAIL. Step 3: implementasi (kategori via `KategoriKehadiran::sqlCase('s')` di SELECT + WHERE; cakupan `whereIn('s.Kode_Karyawan', subkueri Karyawan ber-scope)`). Step 4: PASS + ukur jumlah kueri (tetap). Step 5: commit.

### Task 4: Service Rekap Periode

**Files:**
- Create: `app/Services/ESS/Attendance/Monitoring/RekapPeriodeService.php`
- Test: `tests/Feature/ESS/Attendance/Monitoring/RekapPeriodeTest.php`

**Interfaces:**
- Produces: `rekapKaryawan(string $dari, string $sampai, array $f, FunctionalRole $role, bool $semua = false): array{items,total,page,limit}` (kolom: terjadwal, hadir, terlambat_kali, terlambat_menit, terlambat_berizin, cepat_kali, cepat_menit, cepat_berizin, sakit, izin, cuti, alpha, lupa_checkout, lembur_jam, lembur_hari, konversi); `rekapKelompok(string $dari, string $sampai, string $dimensi, array $f, FunctionalRole $role): array`; `rincian(string $kode, string $dari, string $sampai, FunctionalRole $role): ?array`; `barisKotor(string $dari, string $sampai, array $f, FunctionalRole $role): int`. Cache 30 menit hanya bila `$sampai < hari ini` dan `barisKotor = 0`.

- [ ] Step 1: uji — penjumlahan rincian harian satu karyawan = baris rekapnya (terjadwal, terlambat_menit, lembur_jam); rekap kelompok: jumlah karyawan per dimensi = jumlah baris rekap karyawan bersaring dimensi itu; karyawan di luar cakupan → `rincian` null.
- [ ] Step 2: FAIL. Step 3: implementasi. Step 4: PASS. Step 5: commit.

### Task 5: Ekspor rekap (antrean)

**Files:**
- Create: `app/Services/ESS/Attendance/Monitoring/RekapKehadiranExportService.php`, `app/Jobs/ESS/Attendance/Monitoring/GenerateRekapKehadiranExportJob.php`
- Modify: `config/exports.php` (daftarkan `'monitoring-kehadiran-rekap'`)
- Test: `tests/Feature/ESS/Attendance/Monitoring/RekapKehadiranExportTest.php`

**Interfaces:**
- Consumes: Task 4 (`rekapKaryawan(..., semua: true)`).
- Produces: payload `{dari, sampai, filter, user_id}`; dataset memakai `new FunctionalRole($payload['user_id'])` (tanpa `Auth`).

- [ ] Step 1: uji — `buildDataset` dari payload tanpa sesi login menghasilkan baris = `rekapKaryawan` pemohon; berkas xlsx terbentuk (disk palsu).
- [ ] Step 2: FAIL. Step 3: implementasi (pola `PengumpulanDataExportService`). Step 4: PASS. Step 5: commit.

### Task 6: Controller, rute, menu & indeks

**Files:**
- Create: `app/Http/Controllers/ESS/Attendance/Monitoring/MonitoringKehadiranController.php`, `routes/ESS/Attendance/Monitoring/MonitoringKehadiran.php`, `database/ddl/DDL-2026-192-absen-snapshot-indeks-tanggal/{forward,drop}.sql`, `database/dml/DML-2026-193-menu-monitoring-kehadiran/{data-change,rollback}.sql`, `docs/changes/…/README.md` ×2
- Modify: `routes/web.php`, `database/ddl/status.json`
- Test: `tests/Feature/ESS/Attendance/Monitoring/MonitoringKehadiranAksesTest.php`

Rute (`auth`, `check.jabatan:essMonitoringKehadiranPage`, prefix `admin/monitoring-kehadiran`): `GET /` (Inertia `ESS/Attendance/Monitoring/MonitoringKehadiran`, props `opsiSaringan`, `periode`, `hariIni`), `GET /hari-ini`, `GET /rekap`, `GET /rekap-kelompok`, `GET /rincian/{kode}`, `POST /ekspor`, `GET /ekspor/{id}`, `GET /ekspor/{id}/unduh`. Galat validasi rentang → 422.

- [ ] Step 1: uji akses (tanpa hak halaman → 403 untuk semua endpoint; rentang 63 hari → 422).
- [ ] Step 2: FAIL. Step 3: implementasi + berkas DDL/DML + ledger. Step 4: PASS. Step 5: commit; minta persetujuan user untuk DDL/DML di dev.

### Task 7: Halaman Vue

**Files:**
- Create: `resources/js/Pages/ESS/Attendance/Monitoring/MonitoringKehadiran.vue`, `Components/TabHariIni.vue`, `Components/TabRekapPeriode.vue`, uji `*.test.js` masing-masing.

- [ ] Step 1: uji Vitest — kartu menyaring daftar (parameter `kategori`), ganti tab mempertahankan saringan organisasi, peringatan "sedang diperbarui" muncul bila `kotor > 0`, interval muat ulang dihentikan saat komponen dilepas, ekspor memulai job lalu menampilkan tautan unduh saat `ready`.
- [ ] Step 2: FAIL. Step 3: implementasi dengan HcisHeader, HcisTabs, HcisStatGroup/HcisStatCard, HcisFilterBar, HcisTable, HcisPagination, HcisDrawer. Step 4: PASS + `designSystemGuards`. Step 5: commit.

### Task 8: Verifikasi angka & penyelesaian

- [ ] Jalankan service terhadap production baca-saja (koneksi `prod_ro`) untuk 13 Okt 2026 dan periode 16 Sep–15 Okt; bandingkan dengan agregat SQL mentah (kategori & kolom rekap identik).
- [ ] Jalankan DDL/DML di dev setelah disetujui; flush cache menu semua checkout; ukur kueri rekap sebelum/sesudah indeks.
- [ ] Suite terkait hijau; push; PR.
