# Export Studio PA — Rencana Implementasi Tahap 1

> **Untuk pengeksekusi:** SUB-SKILL WAJIB — pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans` untuk mengerjakan rencana ini
> tugas demi tugas. Langkahnya memakai checkbox (`- [ ]`) untuk penandaan.

**Tujuan:** Menambahkan Export Studio untuk Performance Appraisal dengan dua
dataset pertama — `hasil` dan `penugasan` — sampai bisa dipakai admin.

**Arsitektur:** Memakai mesin laporan generik yang sudah ada di
`app/Services/SYSTEM/Application/Reports/`. PA menjadi penghuni ketiga sesudah
SOP dan VISITOR; tidak ada mesin, komponen layar, atau tabel antrean baru. Yang
ditulis hanya kelas dataset, service export, controller tipis, halaman
pembungkus, dan baris hak akses.

**Tech stack:** Laravel 11 · SQL Server (`sqlsrv`) · Inertia + Vue 3 · PHPUnit ·
Vitest.

**Spec:** `docs/superpowers/specs/2026-09-09-pa-export-studio-design.md`

## Batasan Global

Berlaku untuk **setiap** tugas di bawah, tanpa perlu diulang.

- Modul: `KPI` → submodul `PerformanceAppraisal` → fitur `Export`. Jangan menaruh
  berkas baru di akar generik atau folder peran.
- Seluruh dataset menyaring `Flag_Aktif = 'Y'`.
- Identitas organisasi dibaca dari kolom `..._Saat_Cycle` pada
  `N_PA_Participant`, **bukan** dari master karyawan hidup, dan **tidak boleh**
  join ke `View_Divisi_Sub_Divisi` (view itu berbaris satu per pasangan divisi ×
  sub-divisi sehingga menggandakan orang).
- Tidak ada `Lingkup`: `terapkanLingkup()` mengembalikan kueri apa adanya bila
  `$lingkup->adminPenuh`, dan `$q->whereRaw('1 = 0')` untuk selain itu.
- Label pada ekspresi `CASE` dan daftar `opsiSaringan()` wajib dirender dari
  konstanta yang sama. Ditulis terpisah, memilih opsi membandingkan teks yang tak
  pernah dihasilkan kueri → nol baris **tanpa galat**.
- Hanya `COUNT(*)` dan `SUM()` yang boleh masuk baris GRAND TOTAL. Rata-rata dan
  persentase ditandai `Ukuran::TOTAL_ABAIKAN`.
- Jangan membuat migration Laravel. Perubahan basis data ditulis sebagai berkas
  di `database/dml/<change-id>/` beserta entri di `database/ddl/status.json`.
- Branch kerja untuk seluruh rencana ini: `feat/performance-apprisal`. PA adalah
  fitur yang **ditahan** (`.github/gerbang-rilis.json`, `boleh_di: ["staging"]`),
  jadi PR-nya ke `staging`, tidak pernah ke `master`.

### Kontrak mesin yang dipakai (jangan ditebak, ini bentuk sebenarnya)

```php
interface DatasetLaporan {
    public function kunci(): string;
    public function label(): string;
    public function grain(): string;
    public function dimensi(): array;   // list<Dimensi>
    public function ukuran(): array;    // list<Ukuran>
    public function saringan(): array;  // list<Saringan>
    public function baseQuery(): \Illuminate\Database\Query\Builder;
    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder;
}

new Dimensi(key, label, select, tipe, grup, sensitif = false, format = 'teks');
new Ukuran(key, label, agregat, format, sensitif = false, totalisasi = Ukuran::TOTAL_JUMLAH);
new Saringan(key, label, kolom, tipe, operator, wajibSaatJalan = false);

interface PenyediaOpsiSaringan { public function opsiSaringan(): array; }
```

---

### Task 1: Satu sumber untuk arti status penugasan

Ini tugas pertama karena dua tugas berikutnya bergantung padanya, dan karena ia
memperbaiki cacat yang sudah ada sebelum Export Studio: arti "sudah dikerjakan"
saat ini **diketik ulang** di tiga service. Menambah pemakai keempat tanpa
menyatukannya berarti menjamin Export Studio dan halaman pemantauan suatu hari
melaporkan angka kemajuan berbeda untuk siklus yang sama.

`TERKUMPUL` **bukan** selesai: lembarnya terisi tetapi penilainya belum menekan
kirim untuk seluruh tugasnya. `MENUNGGU_VERIFIKASI` menunggu pengesah di HC
Learn. Keduanya sudah dikerjakan penilainya, sehingga **tidak terhitung
terlambat**, tetapi hanya `SELESAI` yang dianggap tuntas.

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/PaStatusPenugasan.php`
- Create: `tests/Unit/KPI/PerformanceAppraisal/PaStatusPenugasanTest.php`
- Modify: `app/Services/KPI/PerformanceAppraisal/PaAdminDashboardService.php`
  (baris yang memuat `NOT IN ('SELESAI','DIBATALKAN','TERKUMPUL')`)
- Modify: `app/Services/KPI/PerformanceAppraisal/PaLeaderService.php`
  (baris yang memuat `NOT IN ('SELESAI','TERKUMPUL')`)
- Modify: `app/Services/KPI/PerformanceAppraisal/PaNotificationService.php`
  (baris `whereNotIn('a.Status_Penugasan', [...])`)

**Interfaces:**
- Produces: `PaStatusPenugasan::TUNTAS`, `::SUDAH_DIKERJAKAN`,
  `::TIDAK_DIHITUNG_TERLAMBAT`, `::sqlDaftar(array $status): string`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use PHPUnit\Framework\TestCase;

class PaStatusPenugasanTest extends TestCase
{
    public function test_terkumpul_bukan_tuntas(): void
    {
        // Lembarnya terisi, tetapi penilainya belum menekan kirim untuk seluruh
        // tugasnya. Menghitungnya tuntas membuat hasil lahir di atas penilaian
        // yang pengisinya sendiri belum menganggap selesai.
        $this->assertNotContains('TERKUMPUL', PaStatusPenugasan::TUNTAS);
        $this->assertSame(['SELESAI'], PaStatusPenugasan::TUNTAS);
    }

    public function test_terkumpul_dan_menunggu_verifikasi_tidak_terhitung_terlambat(): void
    {
        $this->assertContains('TERKUMPUL', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        $this->assertContains('MENUNGGU_VERIFIKASI', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        $this->assertContains('DIBATALKAN', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
    }

    public function test_sudah_dikerjakan_memuat_terkumpul(): void
    {
        $this->assertContains('TERKUMPUL', PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertContains('SELESAI', PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertNotContains('BELUM_MULAI', PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertNotContains('DIBATALKAN', PaStatusPenugasan::SUDAH_DIKERJAKAN);
    }

    public function test_sql_daftar_mengutip_setiap_nilai(): void
    {
        $this->assertSame("'SELESAI','TERKUMPUL'", PaStatusPenugasan::sqlDaftar(['SELESAI', 'TERKUMPUL']));
    }

    public function test_sql_daftar_menolak_nilai_di_luar_kamus(): void
    {
        // Nilai status dirangkai ke dalam SQL mentah. Tanpa penjagaan ini, satu
        // salah ketik menjadi SQL, dan salahnya tidak terlihat sebagai salah ketik.
        $this->expectException(\InvalidArgumentException::class);
        PaStatusPenugasan::sqlDaftar(['SELESAI', "X'; DROP TABLE y--"]);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test --filter=PaStatusPenugasanTest`
Expected: FAIL — `Class "App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan" not found`

- [ ] **Step 3: Tulis kelasnya**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal;

use InvalidArgumentException;

/**
 * Arti status penugasan, di satu tempat.
 *
 * Sebelum kelas ini ada, arti "sudah dikerjakan" diketik ulang di
 * PaAdminDashboardService, PaLeaderService, dan PaNotificationService. Tiga
 * salinan berarti tiga kesempatan untuk menyimpang, dan menyimpangnya tidak
 * menggagalkan apa pun — ia hanya membuat dua halaman melaporkan angka kemajuan
 * berbeda untuk siklus yang sama, tanpa cara mengetahui mana yang benar.
 */
final class PaStatusPenugasan
{
    public const BELUM_MULAI = 'BELUM_MULAI';
    public const BERJALAN = 'BERJALAN';
    public const MENUNGGU_VERIFIKASI = 'MENUNGGU_VERIFIKASI';
    public const TERKUMPUL = 'TERKUMPUL';
    public const SELESAI = 'SELESAI';
    public const DIBATALKAN = 'DIBATALKAN';

    /** Seluruh nilai yang sah. Dipakai sebagai kamus oleh sqlDaftar(). */
    public const SEMUA = [
        self::BELUM_MULAI, self::BERJALAN, self::MENUNGGU_VERIFIKASI,
        self::TERKUMPUL, self::SELESAI, self::DIBATALKAN,
    ];

    /**
     * TUNTAS — pekerjaannya benar-benar selesai dan boleh ikut menghasilkan nilai.
     * TERKUMPUL sengaja TIDAK di sini.
     */
    public const TUNTAS = [self::SELESAI];

    /**
     * SUDAH_DIKERJAKAN — penilainya sudah mengisi lembarnya, apa pun yang masih
     * ditunggu sesudahnya. Inilah pembilang angka kemajuan pengisian.
     */
    public const SUDAH_DIKERJAKAN = [self::SELESAI, self::TERKUMPUL, self::MENUNGGU_VERIFIKASI];

    /**
     * TIDAK_DIHITUNG_TERLAMBAT — status yang tidak boleh dihitung terlambat
     * meskipun Due_At sudah lewat. DIBATALKAN ikut di sini karena penugasan yang
     * dicabut bukan keterlambatan siapa pun.
     */
    public const TIDAK_DIHITUNG_TERLAMBAT = [
        self::SELESAI, self::TERKUMPUL, self::MENUNGGU_VERIFIKASI, self::DIBATALKAN,
    ];

    /**
     * Merangkai daftar status menjadi potongan SQL `'A','B'`.
     *
     * Nilainya diperiksa terhadap kamus lebih dulu: status dirangkai ke dalam SQL
     * mentah, jadi satu salah ketik akan menjadi SQL dan salahnya tidak akan
     * terbaca sebagai salah ketik.
     *
     * @param  list<string>  $status
     */
    public static function sqlDaftar(array $status): string
    {
        foreach ($status as $s) {
            if (! in_array($s, self::SEMUA, true)) {
                throw new InvalidArgumentException("Status penugasan tidak dikenal: '{$s}'.");
            }
        }

        return implode(',', array_map(fn ($s) => "'{$s}'", $status));
    }
}
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan test --filter=PaStatusPenugasanTest`
Expected: PASS (5 uji)

- [ ] **Step 5: Alihkan tiga pemakai lama ke konstanta itu**

Di `PaAdminDashboardService`, ganti daftar yang diketik langsung dengan
`PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT)`;
di `PaLeaderService` dan `PaNotificationService` lakukan hal yang sama pada
daftarnya masing-masing. **Perilakunya tidak boleh berubah** — kalau daftar lama
berbeda isinya dari konstanta, jangan diam-diam disamakan: hentikan dan laporkan
selisihnya, karena itu berarti dua halaman selama ini memang melaporkan angka
berbeda dan yang benar harus diputuskan orang.

- [ ] **Step 6: Jalankan suite PA penuh**

Run: `env -u DATABASE_URL php artisan test --filter=Pa`
Expected: tidak ada uji yang berubah dari hijau menjadi merah. Konstanta bersama
menyentuh **setiap** pemanggilnya, jadi menjalankan hanya uji yang tersentuh
tidak cukup.

- [ ] **Step 7: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaStatusPenugasan.php \
        tests/Unit/KPI/PerformanceAppraisal/PaStatusPenugasanTest.php \
        app/Services/KPI/PerformanceAppraisal/PaAdminDashboardService.php \
        app/Services/KPI/PerformanceAppraisal/PaLeaderService.php \
        app/Services/KPI/PerformanceAppraisal/PaNotificationService.php
git commit -m "refactor(pa): arti status penugasan jadi satu sumber

TERKUMPUL bukan tuntas -- lembarnya terisi tetapi penilainya belum menekan
kirim. Artinya sebelumnya diketik ulang di tiga service; salinan ketiga sudah
cukup untuk membuat dua halaman melaporkan angka kemajuan berbeda untuk siklus
yang sama tanpa cara mengetahui mana yang benar."
```

---

### Task 2: Dataset `hasil`

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaHasilDataset.php`
- Create: `tests/Unit/KPI/PerformanceAppraisal/Export/PaHasilDatasetTest.php`

**Interfaces:**
- Consumes: kontrak `DatasetLaporan`, `Dimensi`, `Ukuran`, `Saringan`, `Lingkup`,
  `PenyediaOpsiSaringan`
- Produces: `PaHasilDataset` dengan `kunci() === 'hasil'`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaHasilDataset;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

class PaHasilDatasetTest extends TestCase
{
    private PaHasilDataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataset = new PaHasilDataset();
    }

    public function test_kunci_dan_butiran(): void
    {
        $this->assertSame('hasil', $this->dataset->kunci());
        $this->assertStringContainsString('satu peserta', $this->dataset->grain());
    }

    public function test_status_hasil_selalu_tersedia_sebagai_dimensi(): void
    {
        // Keputusan produk: hasil yang belum terbit BOLEH ikut terekspor, tetapi
        // statusnya wajib terbaca di berkasnya. Tanpa kolom ini, angka draft dan
        // angka final tidak bisa dibedakan oleh yang membukanya.
        $kunci = array_map(fn ($d) => $d->key, $this->dataset->dimensi());
        $this->assertContains('status_hasil', $kunci);
        $this->assertContains('alasan_tertahan', $kunci);
    }

    public function test_identitas_dibaca_dari_snapshot_siklus(): void
    {
        $select = [];
        foreach ($this->dataset->dimensi() as $d) {
            $select[$d->key] = $d->select;
        }

        $this->assertStringContainsString('Saat_Cycle', $select['nama_karyawan']);
        $this->assertStringContainsString('Saat_Cycle', $select['divisi']);
    }

    public function test_rata_rata_tidak_ikut_grand_total(): void
    {
        foreach ($this->dataset->ukuran() as $u) {
            if (str_starts_with($u->agregat, 'AVG(')) {
                $this->assertSame(Ukuran::TOTAL_ABAIKAN, $u->totalisasi,
                    "Ukuran {$u->key} memakai AVG tetapi ikut ditotal.");
            }
        }
    }

    public function test_base_query_menyaring_flag_aktif_dan_tidak_menyentuh_view_divisi(): void
    {
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('N_PA_Result', $sql);
        $this->assertStringContainsString('N_PA_Participant', $sql);
        // Perhitungan ulang meninggalkan baris versi lama; tanpa saringan ini satu
        // peserta muncul berkali-kali dengan angka berbeda-beda.
        $this->assertStringContainsString('Flag_Aktif', $sql);
        // View itu berbaris satu per pasangan divisi x sub-divisi, sehingga orang
        // yang bernaung di dua divisi akan tergandakan.
        $this->assertStringNotContainsString('View_Divisi_Sub_Divisi', $sql);
    }

    public function test_join_score_range_memakai_score_type_dan_usage(): void
    {
        // N_PA_Score_Range berkunci Score_Type + Score_Usage juga. Join hanya lewat
        // Kode_Range menggandakan baris peserta tanpa satu pun galat.
        $sql = $this->dataset->baseQuery()->toSql();

        if (str_contains($sql, 'N_PA_Score_Range')) {
            $this->assertStringContainsString('Score_Type', $sql);
            $this->assertStringContainsString('Score_Usage', $sql);
        }
    }

    public function test_bukan_admin_mendapat_nol_baris(): void
    {
        $q = $this->dataset->terapkanLingkup(
            $this->dataset->baseQuery(),
            new Lingkup('EMP001', false, [], false)
        );

        $this->assertStringContainsString('1 = 0', $q->toSql());
    }

    public function test_admin_penuh_tidak_disempitkan(): void
    {
        $awal = $this->dataset->baseQuery()->toSql();
        $sesudah = $this->dataset->terapkanLingkup(
            $this->dataset->baseQuery(),
            new Lingkup('EMP001', true, [], false)
        )->toSql();

        $this->assertSame($awal, $sesudah);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test --filter=PaHasilDatasetTest`
Expected: FAIL — kelasnya belum ada

- [ ] **Step 3: Tulis datasetnya**

Bentuknya mengikuti `app/Services/VISITOR/Workflow/Reporting/Export/VisitorPermitDataset.php`
— baca berkas itu seluruhnya sebelum mulai. Yang khas PA:

```php
public function baseQuery(): Builder
{
    return DB::table('N_PA_Result as r')
        ->join('N_PA_Participant as p', function ($j) {
            $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
              ->where('p.Flag_Aktif', '=', 'Y');
        })
        ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
        ->leftJoin('N_PA_Hav_Quadrant as hav', 'hav.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
        ->leftJoin('N_PA_Score_Range as sr', function ($j) {
            // Tiga kunci, bukan satu. Kode_Range saja menggandakan baris peserta.
            $j->on('sr.Kode_Range', '=', 'r.Kode_Grade_Final')
              ->where('sr.Score_Type', '=', 'FINAL')
              ->where('sr.Score_Usage', '=', 'GRADE')
              ->where('sr.Flag_Aktif', '=', 'Y');
        })
        ->where('r.Flag_Aktif', '=', 'Y');
}
```

Dimensi minimal (key, label, select, tipe, grup):

| key | select | grup |
|---|---|---|
| `kode_cycle` | `c.Kode_Cycle` | siklus |
| `nama_cycle` | `c.Nama_Cycle` | siklus |
| `tahun` | `c.Tahun` | siklus |
| `kode_karyawan` | `p.Kode_Karyawan` | peserta |
| `nama_karyawan` | `p.Nama_Karyawan_Saat_Cycle` | peserta |
| `level` | `p.Nama_Level_Saat_Cycle` | peserta |
| `divisi` | `p.Nama_Divisi_Saat_Cycle` | peserta |
| `sub_divisi` | `p.Nama_Sub_Divisi_Saat_Cycle` | peserta |
| `jabatan` | `p.Nama_Jabatan_Saat_Cycle` | peserta |
| `lokasi` | `p.Lokasi_Saat_Cycle` | peserta |
| `status_hasil` | `r.Status_Hasil` | hasil |
| `alasan_tertahan` | `r.Alasan_Tertahan` | hasil |
| `grade_sistem` | `r.Kode_Grade_Sistem` | hasil |
| `grade_final` | `r.Kode_Grade_Final` | hasil |
| `nama_grade` | `sr.Nama_Range` | hasil |
| `dikalibrasi` | `r.Flag_Dikalibrasi` | hasil |
| `kuadran` | `hav.Nama_Kuadran` | hav |
| `nomor_box` | `hav.Nomor_Box` | hav |
| `calculated_at` | `r.Calculated_At` | audit |
| `published_at` | `r.Published_At` | audit |

Ukuran:

```php
new Ukuran('jumlah_peserta', 'Jumlah Peserta', 'COUNT(*)', 'angka'),
new Ukuran('nilai_kpi', 'Nilai KPI', 'AVG(CAST(r.Nilai_KPI AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
new Ukuran('nilai_kompetensi', 'Nilai Kompetensi', 'AVG(CAST(r.Nilai_Kompetensi AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
new Ukuran('final_score', 'Nilai Akhir', 'AVG(CAST(r.Final_Score AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
new Ukuran('rater_diharapkan', 'Penilai Diharapkan', 'SUM(COALESCE(r.Jumlah_Rater_Diharapkan, 0))', 'angka'),
new Ukuran('rater_mengisi', 'Penilai Mengisi', 'SUM(COALESCE(r.Jumlah_Rater_Mengisi, 0))', 'angka'),
```

`terapkanLingkup()`:

```php
public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
{
    return $lingkup->adminPenuh ? $q : $q->whereRaw('1 = 0');
}
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan test --filter=PaHasilDatasetTest`
Expected: PASS (8 uji)

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Export/PaHasilDataset.php \
        tests/Unit/KPI/PerformanceAppraisal/Export/PaHasilDatasetTest.php
git commit -m "feat(pa): dataset hasil untuk Export Studio"
```

---

### Task 3: Dataset `penugasan`

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaPenugasanDataset.php`
- Create: `tests/Unit/KPI/PerformanceAppraisal/Export/PaPenugasanDatasetTest.php`

**Interfaces:**
- Consumes: `PaStatusPenugasan` dari Task 1
- Produces: `PaPenugasanDataset` dengan `kunci() === 'penugasan'`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaPenugasanDataset;
use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

class PaPenugasanDatasetTest extends TestCase
{
    private PaPenugasanDataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataset = new PaPenugasanDataset();
    }

    public function test_butiran_satu_penugasan(): void
    {
        $this->assertSame('penugasan', $this->dataset->kunci());
        $this->assertStringContainsString('satu penugasan', $this->dataset->grain());
    }

    public function test_ukuran_terisi_memakai_konstanta_bersama_bukan_teks_sendiri(): void
    {
        // Inti tugas ini. Kalau daftar statusnya diketik ulang di sini, Export
        // Studio dan halaman pemantauan akan menyimpang suatu hari, dan tidak ada
        // yang tahu kapan.
        $agregat = [];
        foreach ($this->dataset->ukuran() as $u) {
            $agregat[$u->key] = $u->agregat;
        }

        $daftar = PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertStringContainsString($daftar, $agregat['sudah_terisi']);
    }

    public function test_terlambat_tidak_menghitung_terkumpul(): void
    {
        $agregat = [];
        foreach ($this->dataset->ukuran() as $u) {
            $agregat[$u->key] = $u->agregat;
        }

        $this->assertStringContainsString(
            PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT),
            $agregat['terlambat']
        );
        $this->assertStringContainsString('Due_At', $agregat['terlambat']);
    }

    public function test_persen_kemajuan_tidak_ikut_grand_total(): void
    {
        foreach ($this->dataset->ukuran() as $u) {
            if ($u->key === 'persen_kemajuan') {
                $this->assertSame(Ukuran::TOTAL_ABAIKAN, $u->totalisasi);

                return;
            }
        }

        $this->fail('Ukuran persen_kemajuan tidak ada.');
    }

    public function test_opsi_status_dirender_dari_konstanta_yang_sama(): void
    {
        // Label pada CASE dan daftar opsi harus lahir dari sumber yang sama.
        // Ditulis terpisah, memilih opsi membandingkan teks yang tak pernah
        // dihasilkan kueri -> nol baris TANPA galat.
        $opsi = $this->dataset->opsiSaringan()['status_penugasan'];
        $nilai = array_column($opsi, 'value');

        $this->assertSame(PaStatusPenugasan::SEMUA, $nilai);
    }

    public function test_base_query_menyaring_flag_aktif_penugasan_dan_peserta(): void
    {
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('N_PA_Assignment', $sql);
        $this->assertStringContainsString('N_PA_Participant', $sql);
        $this->assertStringNotContainsString('View_Divisi_Sub_Divisi', $sql);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test --filter=PaPenugasanDatasetTest`
Expected: FAIL — kelasnya belum ada

- [ ] **Step 3: Tulis datasetnya**

```php
public function baseQuery(): Builder
{
    return DB::table('N_PA_Assignment as a')
        ->join('N_PA_Participant as p', function ($j) {
            $j->on('p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
              ->where('p.Flag_Aktif', '=', 'Y');
        })
        ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
        ->leftJoin('N_PA_Rater_Category as rc', 'rc.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
        ->where('a.Flag_Aktif', '=', 'Y');
}
```

Ukuran — perhatikan bahwa daftar statusnya **dirangkai dari konstanta**, tidak
pernah diketik sebagai teks di berkas ini:

```php
$sudah = PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::SUDAH_DIKERJAKAN);
$amanTerlambat = PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);

return [
    new Ukuran('jumlah_penugasan', 'Jumlah Penugasan', 'COUNT(*)', 'angka'),
    new Ukuran('sudah_terisi', 'Sudah Terisi',
        "SUM(CASE WHEN a.Status_Penugasan IN ({$sudah}) THEN 1 ELSE 0 END)", 'angka'),
    new Ukuran('belum_terisi', 'Belum Terisi',
        "SUM(CASE WHEN a.Status_Penugasan NOT IN ({$sudah}) THEN 1 ELSE 0 END)", 'angka'),
    new Ukuran('terlambat', 'Terlambat',
        "SUM(CASE WHEN a.Due_At IS NOT NULL AND a.Due_At < CAST(GETDATE() AS date) "
        ."AND a.Status_Penugasan NOT IN ({$amanTerlambat}) THEN 1 ELSE 0 END)", 'angka'),
    new Ukuran('persen_kemajuan', 'Persen Kemajuan',
        "CAST(100.0 * SUM(CASE WHEN a.Status_Penugasan IN ({$sudah}) THEN 1 ELSE 0 END) "
        .'/ NULLIF(COUNT(*), 0) AS decimal(5,2))', 'persen', false, Ukuran::TOTAL_ABAIKAN),
];
```

Dimensi: seluruh identitas peserta seperti Task 2, ditambah
`a.Kode_Karyawan_Penilai`, `a.Nama_Penilai_Saat_Cycle`, `rc.Nama_Kategori`,
`a.Sumber_Penugasan`, `a.Alasan_Manual`, `a.Bobot_Kustom`,
`a.Status_Penugasan`, `a.Due_At`, `a.Started_At`, `a.Finished_At`.

`opsiSaringan()` untuk `status_penugasan` dibangun dari `PaStatusPenugasan::SEMUA`,
bukan daftar yang diketik ulang.

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan test --filter=PaPenugasanDatasetTest`
Expected: PASS (6 uji)

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Export/PaPenugasanDataset.php \
        tests/Unit/KPI/PerformanceAppraisal/Export/PaPenugasanDatasetTest.php
git commit -m "feat(pa): dataset penugasan, arti status dipinjam bukan diketik ulang"
```

---

### Task 4: Pendaftaran dataset, service export, dan job

Tiga berkas kecil yang tidak berguna sendiri-sendiri, jadi satu tugas: tanpa
ketiganya, dataset yang sudah jadi tidak bisa dicapai siapa pun.

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaReportServiceProvider.php`
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaReportExportService.php`
- Create: `app/Jobs/KPI/PerformanceAppraisal/Export/GeneratePaReportExportJob.php`
- Modify: `config/app.php` (daftar provider, sebelah `VisitorReportServiceProvider`)
- Modify: `config/exports.php` (registri modul + `pa_report_max_rows`)
- Create: `tests/Feature/KPI/PerformanceAppraisal/Export/PaExportPendaftaranTest.php`

**Interfaces:**
- Consumes: `PaHasilDataset`, `PaPenugasanDataset`
- Produces: modul registry bernama `pa`, `moduleKey()` bernilai `'pa-studio'`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaReportExportService;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use Tests\TestCase;

class PaExportPendaftaranTest extends TestCase
{
    public function test_kedua_dataset_terdaftar_pada_modul_pa(): void
    {
        $registry = app(DatasetRegistry::class);

        $this->assertSame('hasil', $registry->ambil('pa', 'hasil')->kunci());
        $this->assertSame('penugasan', $registry->ambil('pa', 'penugasan')->kunci());
    }

    public function test_module_key_terdaftar_di_config_exports(): void
    {
        // Lupa mendaftarkan di sini tidak menggagalkan apa pun sampai klik unduh:
        // berkasnya tetap dibuat dan ditandai siap, lalu unduhannya gagal.
        $peta = config('exports.modules');

        $this->assertArrayHasKey('pa-studio', $peta);
        $this->assertSame(PaReportExportService::class, $peta['pa-studio']);
    }

    public function test_plafon_baris_pa_terdefinisi_untuk_tiap_format(): void
    {
        $plafon = config('exports.pa_report_max_rows');

        $this->assertIsArray($plafon);
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->assertArrayHasKey($format, $plafon);
            $this->assertGreaterThan(0, $plafon[$format]);
        }
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test --filter=PaExportPendaftaranTest`
Expected: FAIL — `DatasetRegistry` melempar untuk modul `pa`

- [ ] **Step 3: Tulis ketiga berkas dan dua perubahan config**

```php
// PaReportServiceProvider.php
class PaReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(DatasetRegistry::class);
        $registry->daftarkan('pa', $this->app->make(PaHasilDataset::class));
        $registry->daftarkan('pa', $this->app->make(PaPenugasanDataset::class));
    }
}
```

```php
// PaReportExportService.php — tiru VisitorReportExportService seluruhnya,
// termasuk processOrFail() dan shouldRetryAfterProcess().
class PaReportExportService extends ReportExportService
{
    protected function moduleKey(): string
    {
        return 'pa-studio';
    }

    public function dispatchJob(string $exportId): void
    {
        GeneratePaReportExportJob::dispatch($exportId);
    }
}
```

`GeneratePaReportExportJob` meniru `GenerateVisitorReportExportJob`, termasuk
`onQueue(config('queue.names.async_export'))`.

`config/app.php`: tambahkan `App\Services\KPI\PerformanceAppraisal\Export\PaReportServiceProvider::class,`
tepat di bawah `VisitorReportServiceProvider::class,`.

`config/exports.php`: tambahkan `'pa-studio' => \App\Services\KPI\PerformanceAppraisal\Export\PaReportExportService::class,`
ke dalam `modules`, dan blok baru:

```php
// Dataset penugasan berbutir peserta x penilai: satu siklus 4.790 peserta
// menghasilkan 7.308 penugasan sebelum saringan apa pun.
'pa_report_max_rows' => [
    'csv' => (int) env('PA_REPORT_MAX_ROWS_CSV', 50000),
    'xlsx' => (int) env('PA_REPORT_MAX_ROWS_XLSX', 20000),
    'pdf' => (int) env('PA_REPORT_MAX_ROWS_PDF', 200),
],
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan config:clear && env -u DATABASE_URL php artisan test --filter=PaExportPendaftaranTest`
Expected: PASS (3 uji)

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Export/PaReportServiceProvider.php \
        app/Services/KPI/PerformanceAppraisal/Export/PaReportExportService.php \
        app/Jobs/KPI/PerformanceAppraisal/Export/GeneratePaReportExportJob.php \
        config/app.php config/exports.php \
        tests/Feature/KPI/PerformanceAppraisal/Export/PaExportPendaftaranTest.php
git commit -m "feat(pa): daftarkan dataset, service export, dan job Export Studio"
```

---

### Task 5: Controller, FormRequest, dan rute

**Files:**
- Create: `app/Http/Controllers/KPI/PerformanceAppraisal/Export/PaExportStudioController.php`
- Create: `app/Http/Requests/KPI/PerformanceAppraisal/Export/RunPaReportRequest.php`
- Create: `app/Http/Requests/KPI/PerformanceAppraisal/Export/SavePaReportTemplateRequest.php`
- Create: `routes/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php`
- Modify: `routes/web.php` (satu baris `require`)
- Create: `tests/Feature/KPI/PerformanceAppraisal/Export/PaExportStudioAksesTest.php`

**Interfaces:**
- Consumes: `DatasetRegistry` modul `pa`, `PaReportExportService`
- Produces: rute bernama `pa.export-studio`, prefix API
  `api/v1/performance-appraisal/export-studio/{studioDataset}`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Export;

use Tests\TestCase;

class PaExportStudioAksesTest extends TestCase
{
    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/performance-appraisal/export-studio')->assertRedirect();
    }

    public function test_api_juga_digerbangi_bukan_hanya_halamannya(): void
    {
        // Menggerbangi halaman saja hanya menyembunyikan tombolnya; datanya tetap
        // bisa ditarik langsung dari endpoint.
        $this->getJson('/api/v1/performance-appraisal/export-studio/pa/katalog/hasil')
            ->assertStatus(401);
    }

    public function test_dataset_tak_dikenal_menjawab_kode_galat_yang_jadi_kontrak(): void
    {
        // Sengaja TIDAK dibatasi di route: nama tak dikenal harus kembali sebagai
        // REPORT_DATASET_UNKNOWN, karena kode galat itulah kontraknya dengan layar.
        $this->actingAsAdminPa()
            ->getJson('/api/v1/performance-appraisal/export-studio/pa/katalog/entah-apa')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'REPORT_DATASET_UNKNOWN');
    }
}
```

Helper `actingAsAdminPa()` ditambahkan ke `tests/TestCase.php` bila belum ada;
ia membuat user dengan hak halaman `PaExportStudioPage`.

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test --filter=PaExportStudioAksesTest`
Expected: FAIL — 404 pada semua rute karena berkas rutenya belum ada

- [ ] **Step 3: Tulis controller, request, dan rute**

Controller meniru `VisitorExportStudioController` **seluruhnya** — sembilan
metode: `halaman`, `katalog`, `galeri`, `simpan`, `perbarui`, `duplikat`,
`hapus`, `pratinjau`, `jalankan`. Ganti modulnya menjadi `'pa'` dan service
export-nya menjadi `PaReportExportService`. Baca berkas Visitor itu sepenuhnya
sebelum menulis; jangan menyalin sebagian.

Berkas rute:

```php
// SATU kunci hak, bukan bentuk OR. Gerbang API memakai kunci yang sama dengan
// gerbang halaman.
Route::middleware(['auth', 'check.jabatan:PaExportStudioPage'])->group(function () {
    Route::get('performance-appraisal/export-studio', [PaExportStudioController::class, 'halaman'])
        ->name('pa.export-studio');
});

Route::middleware(['auth', 'check.jabatan:PaExportStudioPage'])
    ->prefix('api/v1/performance-appraisal/export-studio/{studioDataset}')
    ->group(function () {
        // {dataset} SENGAJA tidak dibatasi -- lihat uji di atas.
        Route::get('/katalog/{dataset}', [PaExportStudioController::class, 'katalog']);
        Route::get('/template', [PaExportStudioController::class, 'galeri']);
        Route::post('/template', [PaExportStudioController::class, 'simpan']);
        Route::put('/template/{kode}', [PaExportStudioController::class, 'perbarui']);
        Route::post('/template/{kode}/duplikat', [PaExportStudioController::class, 'duplikat']);
        Route::delete('/template/{kode}', [PaExportStudioController::class, 'hapus']);
        Route::post('/pratinjau', [PaExportStudioController::class, 'pratinjau']);
        Route::post('/jalankan', [PaExportStudioController::class, 'jalankan']);
    });
```

`routes/web.php`: tambahkan `require __DIR__.'/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php';`
di dalam kelompok PA yang sudah ada. **Perhatikan gaya `__DIR__.'/...'` tanpa
spasi** — master sudah dirapikan Pint, dan menulis `__DIR__ . '/...'` akan
menimbulkan konflik pada merge berikutnya.

- [ ] **Step 4: Bersihkan cache rute, lalu jalankan uji**

Run: `env -u DATABASE_URL php artisan route:clear && env -u DATABASE_URL php artisan test --filter=PaExportStudioAksesTest`
Expected: PASS (3 uji)

Route cache yang basi lintas worktree memunculkan "Target class does not exist"
padahal kodenya benar — jalankan `route:clear` sebelum menyimpulkan ada yang rusak.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/KPI/PerformanceAppraisal/Export \
        app/Http/Requests/KPI/PerformanceAppraisal/Export \
        routes/KPI/PerformanceAppraisal/Export routes/web.php \
        tests/Feature/KPI/PerformanceAppraisal/Export/PaExportStudioAksesTest.php
git commit -m "feat(pa): controller, request, dan rute Export Studio"
```

---

### Task 6: Dua template sistem

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaSystemTemplates.php`
- Create: `tests/Unit/KPI/PerformanceAppraisal/Export/PaSystemTemplatesTest.php`

**Interfaces:**
- Consumes: kunci dimensi dan ukuran dari Task 2 dan Task 3
- Produces: `PaSystemTemplates::semua(): array` berisi dua definisi

- [ ] **Step 1: Tulis uji yang gagal**

```php
public function test_setiap_template_hanya_memakai_kunci_yang_benar_benar_ada(): void
{
    // Template sistem yang menyebut kunci yang tidak ada tidak menggagalkan
    // apa pun saat dimuat -- kolomnya hanya digugurkan diam-diam, dan yang
    // membuka berkasnya menyangka datanya memang kosong.
    $dataset = ['hasil' => new PaHasilDataset(), 'penugasan' => new PaPenugasanDataset()];

    foreach (PaSystemTemplates::semua() as $t) {
        $d = $dataset[$t['dataset']];
        $dimensiSah = array_map(fn ($x) => $x->key, $d->dimensi());
        $ukuranSah = array_map(fn ($x) => $x->key, $d->ukuran());

        foreach ($t['definisi']['dimensi'] as $k) {
            $this->assertContains($k, $dimensiSah, "Template {$t['kode']} menyebut dimensi {$k} yang tidak ada.");
        }
        foreach ($t['definisi']['ukuran'] as $k) {
            $this->assertContains($k, $ukuranSah, "Template {$t['kode']} menyebut ukuran {$k} yang tidak ada.");
        }
    }
}

public function test_dua_template_tahap_satu_tersedia(): void
{
    $kode = array_column(PaSystemTemplates::semua(), 'kode');

    $this->assertContains('pa-rekap-nilai-akhir', $kode);
    $this->assertContains('pa-papan-kemajuan', $kode);
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test --filter=PaSystemTemplatesTest`
Expected: FAIL — kelasnya belum ada

- [ ] **Step 3: Tulis kelasnya**

Bentuk larik mengikuti `VisitorSystemTemplates` — baca berkas itu untuk struktur
`kode`, `nama`, `dataset`, `deskripsi`, `definisi`. Dua isian:

| kode | dataset | mode | isi |
|---|---|---|---|
| `pa-rekap-nilai-akhir` | `hasil` | rinci | kode & nama karyawan, divisi, jabatan, status hasil, grade final, kuadran, nilai akhir |
| `pa-papan-kemajuan` | `penugasan` | ringkasan | dikelompokkan `divisi` × `kategori_penilai`; ukuran `jumlah_penugasan`, `sudah_terisi`, `belum_terisi`, `terlambat`, `persen_kemajuan` |

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan test --filter=PaSystemTemplatesTest`
Expected: PASS (2 uji)

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Export/PaSystemTemplates.php \
        tests/Unit/KPI/PerformanceAppraisal/Export/PaSystemTemplatesTest.php
git commit -m "feat(pa): dua template sistem Export Studio"
```

---

### Task 7: Halaman

**Files:**
- Create: `resources/js/Pages/KPI/PerformanceAppraisal/Export/PaExportStudioPage.vue`
- Create: `resources/js/Pages/KPI/PerformanceAppraisal/Export/PaExportStudioPage.test.js`

**Interfaces:**
- Consumes: komponen bersama `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Produces: halaman yang dirender oleh `PaExportStudioController::halaman()`

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('melahirkan ulang perakit saat dataset berganti', async () => {
    // Tanpa :key, pilihan dimensi dataset lama bocor ke katalog dataset baru
    // dan pengguna melihat kolom yang tidak ada di dataset yang sedang dibuka.
    const wrapper = mount(PaExportStudioPage, { global: { stubs: uiStubs } });
    const studioAwal = wrapper.findComponent({ name: 'ReportStudio' });
    const keyAwal = studioAwal.vm.$.vnode.key;

    await wrapper.vm.pilihDataset('penugasan');

    expect(wrapper.findComponent({ name: 'ReportStudio' }).vm.$.vnode.key).not.toBe(keyAwal);
});

it('menempelkan dataset aktif ke baseUrl', () => {
    const wrapper = mount(PaExportStudioPage, { global: { stubs: uiStubs } });
    const studio = wrapper.findComponent({ name: 'ReportStudio' });

    expect(studio.props('baseUrl')).toContain('/export-studio/hasil');
});
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/KPI/PerformanceAppraisal/Export`
Expected: FAIL — berkasnya belum ada

- [ ] **Step 3: Tulis halamannya**

Tiru `resources/js/Pages/VISITOR/Workflow/Reporting/ExportStudio/exportStudioVisitor.vue`.
Wajib: `:key="datasetAktif"` pada `<ReportStudio>`, dan `baseUrl` yang menempelkan
dataset aktif.

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/KPI/PerformanceAppraisal/Export`
Expected: PASS (2 uji)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/KPI/PerformanceAppraisal/Export
git commit -m "feat(pa): halaman Export Studio"
```

---

### Task 8: Hak akses

**Files:**
- Create: `database/dml/DML-2026-XXX-menu-pa-export-studio/data-change.sql`
- Create: `docs/changes/DML-2026-XXX-menu-pa-export-studio/README.md`
- Modify: `database/ddl/status.json`

Nomor `XXX` diambil dengan memeriksa nomor tertinggi yang sudah dipakai di
`status.json` **dan** di `database/dml/`, lalu menambah satu. Perhatikan: nomor
DML sempat bertabrakan antara branch PA dan master, jadi periksa keduanya.

- [ ] **Step 1: Verifikasi grup fitur dari basis data, bukan dari dokumen**

Run:
```sh
env -u DATABASE_URL php artisan tinker --execute="foreach (DB::table('N_HRIS_Master_Grup_Fitur')->where('Flag_Aktif','Y')->get(['Id_Grup_Fitur','Nama_Grup_Fitur']) as \$g) { echo \$g->Id_Grup_Fitur.' '.\$g->Nama_Grup_Fitur.PHP_EOL; }"
```

Catat Id grup `KPI` yang benar-benar aktif hari ini. Daftar bertanggal di
`CLAUDE.md` **tidak boleh** dipakai sebagai sumber.

- [ ] **Step 2: Tulis DML dengan tiga baris wajib**

`N_HRIS_Menus` (label "Export Studio", `Jenis_page = 'PaExportStudioPage'`,
`Url_Menu = '/performance-appraisal/export-studio'`, di bawah header
`administrator_pa`), `N_HRIS_Grup_Fitur_Map` (**tanpa baris ini menunya tidak
pernah muncul walau haknya sudah benar**), dan `HRIS_Page_Access` untuk peran
admin PA.

- [ ] **Step 3: Catat di status.json**

Entri baru dengan `type: "DML"`, `development: "PENDING"`, `sql_tracker:
"PENDING"`, `production: "PENDING"`, `status: "DRAFT"`, beserta
`dml_checksum_sha256` dari berkasnya.

- [ ] **Step 4: Jalankan di development, lalu bersihkan cache menu**

Run: jalankan DML-nya terhadap `Web_HRIS`, lalu
`env -u DATABASE_URL php artisan tinker --execute="app(\App\Services\SYSTEM\Application\Shell\InertiaShellService::class)->flushMenuCache();"`
dan `env -u DATABASE_URL php artisan route:clear`.
Perbarui statusnya di `status.json` menjadi `development: "DONE"`,
`status: "TRACKER_PENDING"`, `verification: "DEV_DONE"`.

- [ ] **Step 5: Commit**

```bash
git add database/dml/DML-2026-XXX-menu-pa-export-studio docs/changes/DML-2026-XXX-menu-pa-export-studio database/ddl/status.json
git commit -m "chore(db): menu dan hak akses PaExportStudioPage"
```

---

### Task 9: Uji mode ringkasan dan siklus penuh

Tugas terakhir, dan yang paling mungkin menemukan cacat: dimensi yang jalan
sempurna di mode rinci bisa gagal total begitu dikelompokkan, karena **SQL Server
melarang sub-kueri di `GROUP BY`** dan `ReportCompiler` menaruh ekspresi dimensi
apa adanya ke sana.

**Files:**
- Create: `tests/Feature/KPI/PerformanceAppraisal/Export/PaExportStudioJalanTest.php`

- [ ] **Step 1: Tulis uji yang gagal**

```php
public function test_setiap_dataset_bisa_dijalankan_dalam_mode_RINGKASAN(): void
{
    // Ukurannya BEDA per dataset: 'hasil' menghitung peserta, 'penugasan'
    // menghitung penugasan. Memakai satu nama untuk keduanya membuat ujinya
    // gagal karena kunci yang tidak ada, bukan karena mode ringkasannya rusak --
    // dan sebabnya akan disalahpahami.
    $kasus = [
        'hasil' => ['dimensi' => 'divisi', 'ukuran' => 'jumlah_peserta'],
        'penugasan' => ['dimensi' => 'divisi', 'ukuran' => 'jumlah_penugasan'],
    ];

    foreach ($kasus as $dataset => $k) {
        $res = $this->actingAsAdminPa()->postJson(
            '/api/v1/performance-appraisal/export-studio/pa/pratinjau',
            ['dataset' => $dataset, 'mode' => 'ringkasan', 'dimensi' => [$k['dimensi']],
             'ukuran' => [$k['ukuran']], 'saringan' => []]
        );

        $res->assertOk();
    }
}

public function test_siklus_penuh_definisi_sampai_berkas(): void
{
    $jalan = $this->actingAsAdminPa()->postJson(
        '/api/v1/performance-appraisal/export-studio/pa/jalankan',
        ['dataset' => 'hasil', 'mode' => 'rinci', 'format' => 'csv',
         'dimensi' => ['kode_karyawan', 'status_hasil'], 'ukuran' => [], 'saringan' => []]
    );

    $jalan->assertOk()->assertJsonStructure(['result' => ['export_id']]);
}
```

Sesuaikan nama kunci payload dengan yang benar-benar diterima
`RunVisitorReportRequest` — baca berkas itu, jangan menebak bentuknya.

- [ ] **Step 2: Jalankan, pastikan MERAH lebih dulu**

Run: `env -u DATABASE_URL php artisan test --filter=PaExportStudioJalanTest`
Expected: FAIL sebelum perbaikan apa pun. Kalau langsung hijau tanpa pernah
merah, ujinya belum tentu menguji apa yang dikira.

- [ ] **Step 3: Perbaiki dimensi yang gagal saat dikelompokkan**

Kalau ada dimensi berbentuk sub-kueri, ubah menjadi LEFT JOIN ke tabel turunan
`SELECT DISTINCT ...` sehingga dimensinya menyusut jadi pemeriksaan kolom biasa.

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan test --filter=PaExportStudio`
Expected: PASS

- [ ] **Step 5: Jalankan suite PA penuh sebelum menutup tahap 1**

Run: `env -u DATABASE_URL php artisan test --filter=Pa` dan
`npx vitest run resources/js/Pages/KPI/PerformanceAppraisal`

- [ ] **Step 6: Commit dan buka PR ke `staging`**

```bash
git add tests/Feature/KPI/PerformanceAppraisal/Export/PaExportStudioJalanTest.php
git commit -m "test(pa): mode ringkasan dan siklus penuh Export Studio"
git push origin feat/performance-apprisal
gh pr create --base staging --title "feat(pa): Export Studio tahap 1"
```

`--base staging` bukan `master`: PA adalah fitur yang ditahan, dan PR ke `master`
akan ditolak `Gerbang rilis`.

---

## Yang sengaja di luar Tahap 1

Dataset `kompetensi` dan `peserta`, beserta dua template sisanya
(*Gap Kompetensi per Departemen*, *Audit Cakupan Siklus*). Spec sudah memuat
keduanya sehingga tahap 2 tidak memaksa membongkar bentuk yang sudah jadi.

Saat mengerjakan dataset `kompetensi` nanti, satu hal wajib diingat dan sudah
tertulis di spec: `N_PA_Result_Competency` menyimpan **dua butiran** dalam satu
tabel — baris per kategori penilai dan baris agregat lintas kategori
ber-`PA_Rater_Category_Id NULL`. Basisnya harus dikunci ke baris agregat.
