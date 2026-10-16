# API Kalender Operasional HO — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan dua endpoint baca-saja yang memberi tahu Web Careers tanggal berapa saja Head Office tidak beroperasi, supaya SLA rekrutmen mereka dihitung dalam hari kerja yang benar.

**Architecture:** Controller tipis (validasi kontrak + bungkus respons) → `KalenderOperasionalService` (kueri database + cache) → `PenentuTanggalTidakOperasional` (kelas murni tanpa database, memuat seluruh aturan bisnis). Tanpa tabel baru. Autentikasi memakai `hris.api.guard` yang sudah ada plus satu perbaikan agar rute berparameter bisa ditandatangani.

**Tech Stack:** Laravel 10.48, PHP 8, SQL Server (`Web_HRIS`), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md`

## Global Constraints

- Bungkus respons memakai kunci **`sukses`** (bukan `success`). `ResponseHelper` tidak dipakai di jalur ini.
- Prefix rute: `api/v1/hcis/kalender-operasional`.
- Zona waktu `Asia/Jakarta`; `digenerate_at` ISO 8601 dengan offset `+07:00`.
- Nilai `jenis`: `LIBUR_NASIONAL`, `CUTI_BERSAMA`, `LIBUR_PERUSAHAAN` (yang terakhir tidak pernah terbit hari ini).
- Nilai `status` (hanya untuk `CUTI_BERSAMA`): `TERJADWAL`, `BERJALAN`, `SELESAI`.
- Unit yang dikenal hari ini hanya `HEAD_OFFICE`, sekaligus nilai default.
- Batas rentang 400 hari inklusif; `tahun` antara 2020 dan 2100.
- Penanda HO dibaca dari `Karyawan.Cabang_Untuk_Gaji`, polanya dari config.
- `Flag_Executed` **tidak** ikut menentukan; `Flag_Aktif = 'Y'` dan `Flag_Cancelled <> 'Y'` yang menentukan.
- Dilarang menjalankan DDL/DML apa pun tanpa persetujuan eksplisit; `database/dml/` hanya berisi skrip, bukan eksekusi.
- Kunci API tidak pernah ditulis ke berkas yang di-commit.
- **Jangan jalankan `php artisan test` sampai pemilik produk memberi ACC** — suite di repo ini menembak DB dev dan mengunci UI pengembang.

---

### Task 1: Penentu tanggal tidak operasional (murni, tanpa database)

Seluruh aturan bisnis ada di sini. Karena tanpa database, ujinya cepat dan tidak mengunci DB dev.

**Files:**
- Create: `app/Services/ESS/Attendance/Calendar/Operational/PenentuTanggalTidakOperasional.php`
- Test: `tests/Unit/ESS/Attendance/Calendar/PenentuTanggalTidakOperasionalTest.php`

**Interfaces:**
- Produces:
  ```php
  final class PenentuTanggalTidakOperasional
  {
      public const JENIS_LIBUR_NASIONAL = 'LIBUR_NASIONAL';
      public const JENIS_CUTI_BERSAMA   = 'CUTI_BERSAMA';

      /**
       * @param  list<array{tanggal:string, nama:?string}>  $libur
       * @param  list<array{id:int, nama:?string, selected_dates:mixed, tanggal_mulai:?string,
       *                    tanggal_selesai:?string, flag_aktif:?string, flag_cancelled:?string,
       *                    filter_kode_karyawan:mixed, filter_cabang:mixed,
       *                    filter_divisi:mixed, filter_sub_divisi:mixed}>  $cutiBersama
       * @param  list<array{kode:string, cabang:?string, divisi:?string, sub_divisi:?string}>  $karyawanHo
       * @param  array<int, array<string, true>>  $pengecualian  [Id_Cuti_Bersama => [KODE => true]]
       * @return list<array{tanggal:string, jenis:string, nama:string, status?:string}>
       */
      public function tentukan(array $libur, array $cutiBersama, array $karyawanHo,
                               array $pengecualian, string $dari, string $sampai,
                               string $hariIni): array;

      public function tanggalCutiBersama(array $row): array;          // list<string> Y-m-d
      public function menyasarHeadOffice(array $row, array $karyawanHo, array $pengecualian): bool;
      public function statusCutiBersama(string $tanggal, string $hariIni): string;
  }
  ```

- [ ] **Step 1: Tulis uji yang gagal**

`tests/Unit/ESS/Attendance/Calendar/PenentuTanggalTidakOperasionalTest.php` — 13 uji yang mengunci spec §4 dan §5:

```php
private function ho(): array
{
    return [['kode' => 'HO1', 'cabang' => 'HEAD OFFICE EVO', 'divisi' => '22', 'sub_divisi' => '5']];
}

private function cb(array $ubah = []): array
{
    return array_merge([
        'id' => 1, 'nama' => 'Cuti Bersama Idul Fitri',
        'selected_dates' => '["2026-04-02"]',
        'tanggal_mulai' => '2026-04-02', 'tanggal_selesai' => '2026-04-02',
        'flag_aktif' => 'Y', 'flag_cancelled' => 'T',
        'filter_kode_karyawan' => null, 'filter_cabang' => null,
        'filter_divisi' => null, 'filter_sub_divisi' => null,
    ], $ubah);
}
```

Uji yang wajib ada (nama metode → perilaku spec §10):

1. `cuti_bersama_tanpa_filter_menutup_head_office` — `cb()` polos ⇒ terbit
2. `cuti_bersama_yang_hanya_menyasar_cabang_lain_tidak_menutup_head_office` — `filter_cabang => '["CELENTANG"]'` ⇒ tidak terbit
3. `cuti_bersama_yang_menyasar_cabang_head_office_menutup_head_office` — `filter_cabang => '["HEAD OFFICE EVO"]'` ⇒ terbit
4. `cuti_bersama_yang_dibatalkan_tidak_terbit` — `flag_cancelled => 'Y'` ⇒ tidak terbit
5. `cuti_bersama_tidak_aktif_tidak_terbit` — `flag_aktif => 'T'` ⇒ tidak terbit
6. `cuti_bersama_yang_seluruh_sasaran_ho_nya_dikecualikan_tidak_terbit` — `$pengecualian = [1 => ['HO1' => true]]` ⇒ tidak terbit
7. `cuti_bersama_yang_belum_dieksekusi_tetap_terbit` — baris tanpa `flag_executed` sama sekali ⇒ terbit (§3.3)
8. `selected_dates_dipakai_bila_terisi` — `selected_dates => '["2026-04-02","2026-04-06"]'`, rentang mulai/selesai `2026-04-02..2026-04-06` ⇒ **2** tanggal, bukan 5
9. `rentang_dibentangkan_bila_selected_dates_kosong` — `selected_dates => null`, `2026-04-02..2026-04-04` ⇒ 3 tanggal
10. `libur_nasional_menang_atas_cuti_bersama_pada_tanggal_sama` — keduanya `2026-04-02` ⇒ satu entri, `jenis = LIBUR_NASIONAL`
11. `keluaran_urut_menaik_dan_tanpa_duplikat`
12. `cuti_bersama_ber_id_terkecil_menang_saat_tanggalnya_sama` — id 9 dan id 2 pada tanggal sama ⇒ nama milik id 2
13. `status_dihitung_dari_posisi_tanggal_terhadap_hari_ini` — kemarin ⇒ `SELESAI`, hari ini ⇒ `BERJALAN`, besok ⇒ `TERJADWAL`

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```
php artisan test tests/Unit/ESS/Attendance/Calendar/PenentuTanggalTidakOperasionalTest.php
```
Harapan: gagal dengan `Class "App\Services\...\PenentuTanggalTidakOperasional" not found`.
**Jangan lanjut sebelum melihat merahnya.** (Uji ini murni, tidak menyentuh DB dev — aman dijalankan kapan pun.)

- [ ] **Step 3: Tulis implementasinya**

Aturan sasaran disalin apa adanya dari `cutiBersamaService.php:256`:

```php
$diKode   = in_array($kode, $filterKode, true);
$cocokCab = $filterCabang === []    || in_array($cabang, $filterCabang, true);
$cocokDiv = $filterDivisi === []    || in_array($divisi, $filterDivisi, true);
$cocokSub = $filterSubDivisi === [] || in_array($subDivisi, $filterSubDivisi, true);
$diUnit   = $adaFilterUnit && $cocokCab && $cocokDiv && $cocokSub;

if ($diKode || $diUnit || (! $adaFilterKode && ! $adaFilterUnit)) {
    return true;   // cukup SATU karyawan HO
}
```

Kode karyawan dinormalkan `strtoupper(trim(...))` di kedua sisi (filter, daftar HO, dan peta pengecualian) — kolomnya `varchar` dan bisa berspasi ekor.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**
- [ ] **Step 5: Commit** — `feat(ess): aturan penentu tanggal tidak operasional HO`

---

### Task 2: Config unit dan service pengambil data

**Files:**
- Create: `config/kalender_operasional.php`
- Create: `app/Services/ESS/Attendance/Calendar/Operational/KalenderOperasionalService.php`

**Interfaces:**
- Consumes: `PenentuTanggalTidakOperasional::tentukan()` (Task 1)
- Produces:
  ```php
  // TIDAK final: uji kontrak Task 3 menggantinya dengan subkelas anonim
  // supaya uji HTTP tidak menyentuh database.
  class KalenderOperasionalService
  {
      public const CACHE_KUNCI_VERSI = 'kalender_operasional:versi';

      /** @return array{unit:string, hari_kerja_mingguan:list<int>,
       *                digenerate_at:string,
       *                tanggal_tidak_operasional:list<array<string,mixed>>} */
      public function untukRentang(string $unit, string $dari, string $sampai): array;

      public function unitDikenal(string $unit): bool;
      public static function bersihkanCache(): int;   // mengembalikan versi baru
  }
  ```

Config:

```php
return [
    'batas_rentang_hari' => 400,
    'tahun_minimal' => 2020,
    'tahun_maksimal' => 2100,
    'cache_ttl_detik' => 900,
    'unit' => [
        'HEAD_OFFICE' => [
            'hari_kerja_mingguan' => [1, 2, 3, 4, 5, 6],
            'pola_cabang' => ['%HEAD OFFICE%'],   // dicocokkan ke Karyawan.Cabang_Untuk_Gaji
        ],
    ],
];
```

Empat kueri, semuanya bertutup rentang (spec §8):

1. `HRIS_Hari_Libur` `whereBetween('Tanggal', [dari, sampai])`
2. `N_HRIS_Cuti_Bersama` yang beririsan rentang: `Flag_Aktif = 'Y'`, `(Flag_Cancelled IS NULL OR Flag_Cancelled <> 'Y')`, `Tanggal_Mulai <= sampai`, `COALESCE(Tanggal_Selesai, Tanggal_Mulai) >= dari`
3. Daftar karyawan HO: `Aktif = 'Y'` dan `Cabang_Untuk_Gaji LIKE` tiap pola (di-`orWhere`). `Tanggal_Resign` disaring di PHP dengan `empty()` — kolomnya bertipe longgar dan `empty()` adalah aturan yang sudah dipakai `cutiBersamaService`
4. `N_HRIS_Cuti_Bersama_Exclusion` `whereIn('Id_Cuti_Bersama', $idTerkumpul)` — hanya bila ada id, jangan kirim `IN ()` kosong

Cache: kunci `kalender_operasional:v{versi}:{unit}:{dari}:{sampai}`, TTL dari config. `bersihkanCache()` menaikkan angka versi, jadi berfungsi di driver apa pun tanpa cache tag.

- [ ] **Step 1: Tulis service dan config**
- [ ] **Step 2: Periksa muat kelasnya** — `composer dump-autoload -o --strict-psr` lalu `php artisan tinker --execute="app(App\Services\ESS\Attendance\Calendar\Operational\KalenderOperasionalService::class); echo 'ok';"`
- [ ] **Step 3: Commit** — `feat(ess): service kalender operasional + config unit`

---

### Task 3: Controller, validasi kontrak, dan rute

**Files:**
- Create: `app/Http/Controllers/ESS/Attendance/Calendar/Operational/KalenderOperasionalApiController.php`
- Create: `routes/ESS/Attendance/Calendar/OperationalCalendarApi.php`
- Modify: `routes/api.php` (tambah satu `require`)
- Test: `tests/Feature/ESS/Attendance/Calendar/KalenderOperasionalApiTest.php`

**Interfaces:**
- Consumes: `KalenderOperasionalService::untukRentang()`, `::unitDikenal()` (Task 2)
- Produces: dua rute bernama jalur `api/v1/hcis/kalender-operasional/tahun/{tahun}` dan `api/v1/hcis/kalender-operasional`

- [ ] **Step 1: Tulis uji kontrak yang gagal**

Uji memakai `$this->withoutMiddleware(\App\Http\Middleware\HrisApiGuard::class)` supaya menguji kontrak, bukan keamanan, dan supaya tidak menyentuh registry. Service di-`mock` lewat container agar uji tidak menyentuh database sama sekali:

```php
$this->instance(KalenderOperasionalService::class, new class extends KalenderOperasionalService {
    public function untukRentang(string $unit, string $dari, string $sampai): array
    {
        return ['unit' => $unit, 'hari_kerja_mingguan' => [1,2,3,4,5,6],
                'digenerate_at' => '2026-09-02T08:00:00+07:00',
                'tanggal_tidak_operasional' => []];
    }
    public function unitDikenal(string $unit): bool { return $unit === 'HEAD_OFFICE'; }
});
```

Uji yang wajib ada:

1. `dari_kosong_ditolak_400` → `message` = `Parameter 'dari' wajib diisi (format YYYY-MM-DD).`, `sukses` false, `result` null
2. `sampai_kosong_ditolak_400`
3. `format_tanggal_salah_ditolak_400` (`dari=2026-13-45`)
4. `sampai_lebih_awal_dari_dari_ditolak_400`
5. `rentang_lebih_dari_400_hari_ditolak_400`
6. `unit_tak_dikenal_ditolak_400`
7. `tahun_di_luar_batas_ditolak_400` (`2019` dan `2101`)
8. `tahun_bukan_empat_digit_ditolak_400` (`abcd`)
9. `rentang_tanpa_libur_balas_200_dengan_array_kosong`
10. `endpoint_tahun_setara_rentang_satu_januari_sampai_tiga_puluh_satu_desember` — periksa argumen yang diterima service
11. `bungkus_respons_memakai_kunci_sukses_bukan_success` — `assertJsonStructure(['sukses','message','result'])` dan `assertJsonMissingPath('success')`

- [ ] **Step 2: Jalankan uji, pastikan MERAH** — harapannya 404 karena rutenya belum ada
- [ ] **Step 3: Tulis controller, berkas rute, dan `require` di `routes/api.php`**

Controller hanya punya dua aksi publik dan sekumpulan metode privat: `validasiRentang()`, `validasiTahun()`, `sukses()`, `galat()`. Tidak ada kueri.

Berkas rute:

```php
Route::middleware('hris.api.guard')->prefix('v1/hcis/kalender-operasional')->group(function () {
    Route::get('/tahun/{tahun}', [KalenderOperasionalApiController::class, 'tahun']);
    Route::get('/', [KalenderOperasionalApiController::class, 'rentang']);
});
```

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**
- [ ] **Step 5: Periksa rute terdaftar** — `php artisan route:list --path=kalender-operasional`
- [ ] **Step 6: Commit** — `feat(ess): endpoint API kalender operasional HO`

---

### Task 4: Perbaikan jalur bertanda tangan di `HrisApiGuard`

Tanpa ini, endpoint tahun **selalu** 401. Lihat spec §7.1.

**Files:**
- Modify: `app/Http/Middleware/HrisApiGuard.php`
- Test: `tests/Feature/SYSTEM/Integration/HrisApiGuardJalurBertandaTanganTest.php`

- [ ] **Step 1: Tulis uji regresi yang gagal**

Dua uji, keduanya tanpa database (memanggil middleware dengan `Route` tiruan):

1. `rute_statis_menghasilkan_jalur_tanda_tangan_yang_sama_seperti_sebelumnya` — untuk `api/v1/hris/third-party/sop/exam/complete`, jalur tanda tangan tetap `/api/v1/hris/third-party/sop/exam/complete`. **Ini yang membuktikan konsumen lama tidak terpengaruh.**
2. `rute_berparameter_menandatangani_jalur_sebenarnya_bukan_polanya` — untuk permintaan ke `/api/v1/hcis/kalender-operasional/tahun/2026`, jalur tanda tangan adalah jalur itu, **bukan** `.../tahun/{tahun}`, sementara jalur whitelist tetap polanya.

- [ ] **Step 2: Jalankan uji, pastikan MERAH** (uji ke-2 gagal, uji ke-1 lulus sejak awal — itu memang perannya sebagai jaring pengaman)
- [ ] **Step 3: Pisahkan kedua peran jalur**

```php
$polaRute = '/'.ltrim($request->route()->uri(), '/');  // untuk whitelist
$path     = $request->getPathInfo();                    // untuk tanda tangan & log
```

`$polaRute` hanya dipakai di kueri whitelist; `$path` dipakai di payload HMAC dan di `logAttempt()`. Sertakan komentar yang menjelaskan sebabnya agar tidak "dirapikan" kembali oleh orang berikutnya.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**
- [ ] **Step 5: Commit** — `fix(system): tandatangani jalur sebenarnya, cocokkan whitelist ke pola rute`

---

### Task 5: Perintah pembersih cache

**Files:**
- Create: `app/Console/Commands/ESS/Attendance/Calendar/FlushKalenderOperasionalCommand.php`

Signature `kalender:flush`, memanggil `KalenderOperasionalService::bersihkanCache()` dan mencetak versi barunya. `app/Console/Kernel.php` memuat `app/Console/Commands` secara rekursif, jadi tidak ada pendaftaran manual.

- [ ] **Step 1: Tulis perintahnya**
- [ ] **Step 2: Pastikan terdaftar** — `php artisan list | grep kalender`
- [ ] **Step 3: Commit** — `feat(ess): perintah kalender:flush`

---

### Task 6: Skrip DML pendaftaran integrasi dan data uji

**Tidak dijalankan.** Task ini hanya menghasilkan berkas; eksekusi menunggu persetujuan eksplisit pemilik produk.

**Files:**
- Create: `database/dml/DML-2026-075-registrasi-api-kalender-ho/data-change.sql`
- Create: `docs/changes/DML-2026-075-registrasi-api-kalender-ho/README.md`

Isi skrip, berurutan dan tiap bagian bisa dijalankan sendiri:

1. `N_HRIS_Api_Aplikasi` — Web Careers, `Arah = 'IN'`, `Id_Grup_Fitur = 1` (ESS), `Flag_Aktif = 'Y'`. `Api_Public`/`Api_Secret` sebagai placeholder `@ApiPublic` / `@ApiSecret` yang diisi saat eksekusi; **kunci asli tidak pernah masuk berkas ini**
2. Dua `N_HRIS_Api_Endpoint`, `Method = 'GET'`, `Arah = 'IN'`, `Id_Grup_Fitur = 1`, `Url_Route` berisi **pola**:
   - `/api/v1/hcis/kalender-operasional/tahun/{tahun}`
   - `/api/v1/hcis/kalender-operasional`
3. Dua `N_HRIS_Api_Akses_Endpoint` yang menghubungkan aplikasi ke kedua endpoint
4. Bagian **data uji dev, terpisah dan berkomentar jelas**: `UPDATE Karyawan SET Cabang_Untuk_Gaji = 'HEAD OFFICE EVO' WHERE Kode_Karyawan IN (...)`, didahului `SELECT` yang mencetak nilai lama, dan diikuti skrip pengembalian yang mengembalikan nilai lama itu satu per satu

- [ ] **Step 1: Tulis skrip dan dokumen perubahannya**
- [ ] **Step 2: Commit** — `chore(db): skrip DML pendaftaran API kalender HO (belum dieksekusi)`

---

### Task 7: Dokumentasi konsumen dan gerbang struktural

**Files:**
- Create: `docs/HCIS/Integrasi/api-kalender-operasional-ho.md`
- Modify: `docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md` (tandai selesai)

Dokumen konsumen memuat: kedua endpoint, contoh menandatangani permintaan `GET` (termasuk `X-HC-Body-Hash` = `sha256("")` dan bahwa yang ditandatangani adalah **jalur sebenarnya**), tabel field respons, dan tabel galat.

- [ ] **Step 1: Tulis dokumen konsumen**
- [ ] **Step 2: Ambil baseline gerbang struktural** — jalankan `php scripts/route-snapshot.php` dan `php scripts/console-snapshot.php` pada `HEAD` sebelum perubahan (gunakan worktree bersih), simpan hasilnya
- [ ] **Step 3: Jalankan gerbang** — `composer dump-autoload -o --strict-psr`, `php scripts/verify-references.php --max=0`, `php scripts/verify-frontend-imports.php`, `php scripts/verify-inertia-pages.php`, lalu bandingkan kedua snapshot
- [ ] **Step 4: Jelaskan selisihnya** — diharapkan tepat +2 rute dan +1 perintah konsol; tidak ada perubahan frontend
- [ ] **Step 5: Commit** — `docs(ess): panduan konsumen API kalender operasional HO`

---

## Catatan pelaksanaan

- Uji Task 1, 3, dan 4 tidak menyentuh database dan aman dijalankan kapan saja.
- Verifikasi ujung-ke-ujung yang sungguhan (butir §10 nomor 14–15) baru mungkin **setelah** DML Task 6 dieksekusi, dan itu menunggu persetujuan pemilik produk.
- Angka apa pun dari database di spec adalah snapshot 2026-09-04; verifikasi ulang sebelum produksi.
