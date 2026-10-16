# Koreksi Target KPI dari Halaman Enforcement — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memberi admin pintu untuk mengoreksi salah ketik `Nilai_Target`, hanya pada target yang belum dinilai dan belum punya draf, dengan jejak yang tidak bisa hilang.

**Architecture:** Satu service (`KpiTargetKoreksiService`) memiliki seluruh aturan kelayakan dan penerapan; controller dan layar hanya memanggilnya. Perubahan ditulis ke `KPI_Pengajuan_Det.Nilai_Target` bersama satu baris jejak di tabel baru `N_KPI_Target_Koreksi`, dalam satu transaksi. Izinnya kunci terpisah yang sengaja tidak masuk grup fitur.

**Tech Stack:** Laravel 11, SQL Server (`Web_HRIS`), Vue 3 Options API, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-03-kpi-koreksi-target-enforcement-design.md`

## Global Constraints

- Modul `KPI`, submodul `Monitoring/Enforcement`. Berkas baru mengikuti struktur itu.
- Change ID database: `DDL-2026-064-kpi-koreksi-target`. Verifikasi ulang nomor bebasnya di `database/ddl/status.json` sebelum membuat folder — nomor bisa diklaim cabang lain.
- **DDL dieksekusi oleh pengguna, bukan oleh agen.** Agen menyiapkan artefaknya dan meminta persetujuan.
- Granularitas aturan: per `Id_Pengajuan_Det` (satu parameter, satu periode).
- Setiap penjaga regresi **wajib pernah dilihat MERAH** sebelum implementasinya ditulis.
- Uji membuat keadaannya sendiri di dalam `DatabaseTransactions`; tidak menunggu data dev kebetulan berbentuk tertentu.
- Kunci izin `kpiKoreksiTarget` **tidak boleh** ditambahkan ke `N_HRIS_Grup_Fitur_Map`.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-064-kpi-koreksi-target/forward.sql` | membuat `N_KPI_Target_Koreksi` |
| `database/ddl/DDL-2026-064-kpi-koreksi-target/drop.sql` | membatalkannya |
| `docs/changes/DDL-2026-064-kpi-koreksi-target/README.md` | konteks perubahan |
| `app/Services/KPI/KpiTargetKoreksiService.php` | gerbang kelayakan, penerapan, jejak, peringatan menyimpang |
| `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiTargetKoreksiController.php` | dua endpoint; tanpa aturan bisnis |
| `routes/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php` | grup rute berkunci `kpiKoreksiTarget` |
| `tests/Feature/KPI/Monitoring/KoreksiTargetGerbangTest.php` | penjaga gerbang |
| `tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php` | penerapan, jejak, gagal-tertutup |
| `tests/Feature/KPI/Monitoring/KoreksiTargetIzinTest.php` | penjaga izin |
| `resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue` | sel bisa disunting + kotak sebab |

---

### Task 1: Skema jejak

**Files:**
- Create: `database/ddl/DDL-2026-064-kpi-koreksi-target/forward.sql`
- Create: `database/ddl/DDL-2026-064-kpi-koreksi-target/drop.sql`
- Create: `docs/changes/DDL-2026-064-kpi-koreksi-target/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: —
- Produces: tabel `N_KPI_Target_Koreksi` dengan kolom `Id_Target_Koreksi, No_Batch, Id_Pengajuan_Det, Nilai_Target_Lama, Nilai_Target_Baru, Sebab, Id_User_Aktor, Waktu`.

- [ ] **Step 1: Pastikan nomornya masih bebas**

```bash
python -c "
import json,re
d=json.load(open('database/ddl/status.json'))
n=[int(m.group(1)) for c in d['changes'] if (m:=re.search(r'-2026-(\d{3})', c['change_id']))]
print('tertinggi:', max(n), '| 064 dipakai:', 64 in n)
"
```

Bila 064 sudah dipakai, naikkan ke nomor bebas berikutnya di seluruh berkas task ini.

- [ ] **Step 2: Tulis `forward.sql`**

```sql
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_KPI_Target_Koreksi')
CREATE TABLE N_KPI_Target_Koreksi (
    Id_Target_Koreksi   int IDENTITY(1,1) NOT NULL,
    No_Batch            varchar(40)   NOT NULL,
    Id_Pengajuan_Det    int           NOT NULL,
    Nilai_Target_Lama   float         NOT NULL,
    Nilai_Target_Baru   float         NOT NULL,
    Sebab               nvarchar(500) NOT NULL,
    Id_User_Aktor       int           NOT NULL,
    Waktu               datetime      NOT NULL CONSTRAINT DF_N_KPI_Target_Koreksi_Waktu DEFAULT (GETDATE()),
    CONSTRAINT PK_N_KPI_Target_Koreksi PRIMARY KEY (Id_Target_Koreksi)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_KPI_Target_Koreksi_Det')
    CREATE INDEX IX_N_KPI_Target_Koreksi_Det
        ON N_KPI_Target_Koreksi (Id_Pengajuan_Det, Waktu DESC);
GO
```

Tidak ada foreign key ke `KPI_Pengajuan_Det`: jejak harus bertahan meski baris targetnya kelak dihapus (`kpi:rapikan-target-sebelum-mulai --terapkan` memang menghapus baris target).

- [ ] **Step 3: Tulis `drop.sql`**

```sql
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_KPI_Target_Koreksi')
    DROP TABLE N_KPI_Target_Koreksi;
GO
```

- [ ] **Step 4: Tulis README perubahan**

`docs/changes/DDL-2026-064-kpi-koreksi-target/README.md` memuat: alasan (salah ketik target permanen karena tak ada jalur ubah), isi tabel, kenapa tabel dan bukan kolom (koreksi bisa berulang; kolom hanya mengingat yang terakhir), dan kenapa tanpa foreign key.

- [ ] **Step 5: Daftarkan di `status.json`**

Salin bentuk entri `DDL-2026-045-kpi-cap-per-parameter` yang sudah ada: `change_id`, `type: "DDL"`, `title`, `status: "DRAFT"`, `artifact_path`, `drop_path`, `documentation_path`, `forward_checksum_sha256`, `affected_objects: ["N_KPI_Target_Koreksi"]`.

Checksum: `sha256sum database/ddl/DDL-2026-064-kpi-koreksi-target/forward.sql`

- [ ] **Step 6: Minta persetujuan pengguna untuk mengeksekusi**

**BERHENTI DI SINI.** Tampilkan isi `forward.sql` dan mintakan persetujuan. Jangan jalankan DDL sendiri. Setelah dieksekusi, perbarui `status.json` (`status`, `execution.result`) dengan hasil sebenarnya.

- [ ] **Step 7: Commit**

```bash
git add database/ddl/DDL-2026-064-kpi-koreksi-target docs/changes/DDL-2026-064-kpi-koreksi-target database/ddl/status.json
git commit -m "chore(kpi): artefak DDL-2026-064 tabel jejak koreksi target"
```

---

### Task 2: Gerbang kelayakan

**Files:**
- Create: `app/Services/KPI/KpiTargetKoreksiService.php`
- Test: `tests/Feature/KPI/Monitoring/KoreksiTargetGerbangTest.php`

**Interfaces:**
- Consumes: tabel dari Task 1 (belum dipakai di task ini).
- Produces:

```php
public function periksaKelayakan(array $idPengajuanDet): array
// [int $id => [
//     'boleh'         => bool,
//     'kode'          => 'BOLEH'|'TIDAK_DITEMUKAN'|'SUDAH_FINAL'|'ADA_DRAF'|'PENGAJUAN_TIDAK_HIDUP',
//     'keterangan'    => string,   // siap tampil
//     'pemegang_draf' => ?string,  // nama karyawan, hanya saat kode ADA_DRAF
// ]]
```

- [ ] **Step 1: Tulis uji yang gagal**

`tests/Feature/KPI/Monitoring/KoreksiTargetGerbangTest.php`:

```php
<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Services\KPI\KpiTargetKoreksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KoreksiTargetGerbangTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): KpiTargetKoreksiService
    {
        return app(KpiTargetKoreksiService::class);
    }

    /** Target hidup: pengajuan aktif+disetujui, tanpa final, tanpa draf. */
    private function targetHidup(): ?object
    {
        return DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail as upd')
                ->whereColumn('upd.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail_Sementara as s')
                ->whereColumn('s.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->orderByDesc('det.Id_Pengajuan_Det')
            ->select('det.Id_Pengajuan_Det', 'det.Nilai_Target', 'p.Id_Pengajuan')
            ->first();
    }

    public function test_target_bersih_boleh_dikoreksi(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det]);

        $this->assertTrue($hasil[(int) $t->Id_Pengajuan_Det]['boleh']);
        $this->assertSame('BOLEH', $hasil[(int) $t->Id_Pengajuan_Det]['kode']);
    }

    public function test_ditolak_ketika_draf_ada_dan_menyebut_pemegangnya(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $karyawan = DB::table('Karyawan')->whereNotNull('Nama')->first();
        $header = DB::table('KPI_Update_Performance_Sementara')->orderByDesc('Id_Update_Performance_Sementara')->first();
        if (! $karyawan || ! $header) {
            $this->markTestSkipped('Data dev tidak punya karyawan/header draf untuk disandarkan.');
        }

        DB::table('KPI_Update_Performance_Detail_Sementara')->insert([
            'Id_Update_Performance_Sementara' => $header->Id_Update_Performance_Sementara,
            'Id_Pengajuan_Det' => $t->Id_Pengajuan_Det,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nilai_Actual' => 1,
            'Score' => 1,
            'Achievement' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('ADA_DRAF', $hasil['kode']);
        $this->assertSame($karyawan->Nama, $hasil['pemegang_draf'],
            'Penolakan ADA_DRAF harus menyebut pemegangnya; tanpa itu admin tak tahu siapa yang dikejar.');
    }

    public function test_ditolak_ketika_sudah_ada_final(): void
    {
        $sudahFinal = DB::table('KPI_Update_Performance_Detail')
            ->orderByDesc('Id_Update_Performance_Detail')
            ->value('Id_Pengajuan_Det');

        if (! $sudahFinal) {
            $this->markTestSkipped('Tidak ada detail final di data dev.');
        }

        $hasil = $this->svc()->periksaKelayakan([(int) $sudahFinal])[(int) $sudahFinal];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('SUDAH_FINAL', $hasil['kode']);
    }

    public function test_ditolak_ketika_pengajuan_tidak_hidup(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        DB::table('KPI_Pengajuan')->where('Id_Pengajuan', $t->Id_Pengajuan)->update(['Flag_Aktif' => 'T']);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('PENGAJUAN_TIDAK_HIDUP', $hasil['kode']);
    }

    public function test_id_yang_tidak_ada_dilaporkan_bukan_dilewati(): void
    {
        $hasil = $this->svc()->periksaKelayakan([999999999]);

        $this->assertArrayHasKey(999999999, $hasil, 'Id tak dikenal harus tetap muncul di hasil.');
        $this->assertSame('TIDAK_DITEMUKAN', $hasil[999999999]['kode']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetGerbangTest.php`
Expected: FAIL — `Target class [App\Services\KPI\KpiTargetKoreksiService] does not exist`

- [ ] **Step 3: Tulis servicenya**

`app/Services/KPI/KpiTargetKoreksiService.php`:

```php
<?php

namespace App\Services\KPI;

use Illuminate\Support\Facades\DB;

/**
 * Pemilik TUNGGAL aturan koreksi target.
 *
 * Baik pemeriksa kelayakan (yang mematikan sel di layar) maupun jalur simpan
 * memanggil fungsi yang sama. Aturan ini tidak boleh disalin ke controller
 * atau ke frontend: cacat yang paling sering berulang di proyek ini adalah
 * aturan yang terpasang di sebagian pintu saja.
 */
class KpiTargetKoreksiService
{
    /**
     * @param  array<int>  $idPengajuanDet
     * @return array<int, array{boleh: bool, kode: string, keterangan: string, pemegang_draf: ?string}>
     */
    public function periksaKelayakan(array $idPengajuanDet): array
    {
        $ids = array_values(array_unique(array_map('intval', $idPengajuanDet)));

        if ($ids === []) {
            return [];
        }

        $target = DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->whereIn('det.Id_Pengajuan_Det', $ids)
            ->get([
                'det.Id_Pengajuan_Det',
                'p.Flag_Aktif',
                'p.Flag_Approval',
                'p.Status',
            ])
            ->keyBy('Id_Pengajuan_Det');

        // Satu kueri per jenis penghalang, bukan satu per target.
        $final = DB::table('KPI_Update_Performance_Detail')
            ->whereIn('Id_Pengajuan_Det', $ids)
            ->pluck('Id_Pengajuan_Det')
            ->flip();

        $draf = DB::table('KPI_Update_Performance_Detail_Sementara as s')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 's.Kode_Karyawan')
            ->whereIn('s.Id_Pengajuan_Det', $ids)
            ->pluck('k.Nama', 's.Id_Pengajuan_Det');

        $hasil = [];

        foreach ($ids as $id) {
            $hasil[$id] = $this->nilaiSatu($id, $target->get($id), isset($final[$id]), $draf);
        }

        return $hasil;
    }

    private function nilaiSatu(int $id, $baris, bool $adaFinal, $draf): array
    {
        if (! $baris) {
            return $this->tolak('TIDAK_DITEMUKAN', 'Target tidak ditemukan.');
        }

        if ($adaFinal) {
            return $this->tolak('SUDAH_FINAL', 'Sudah dinilai; targetnya tidak bisa diubah lagi.');
        }

        if ($draf->has($id)) {
            $nama = $draf->get($id);

            return $this->tolak(
                'ADA_DRAF',
                'Sedang diisi'.($nama ? ' oleh '.$nama : '').'; minta drafnya dikirim atau dibatalkan dulu.',
                $nama,
            );
        }

        $hidup = ($baris->Flag_Aktif ?? null) === 'Y'
            && ($baris->Flag_Approval ?? null) === 'Y'
            && ($baris->Status ?? null) === null;

        if (! $hidup) {
            return $this->tolak('PENGAJUAN_TIDAK_HIDUP', 'Pengajuan parameternya tidak aktif atau belum disetujui.');
        }

        return ['boleh' => true, 'kode' => 'BOLEH', 'keterangan' => '', 'pemegang_draf' => null];
    }

    private function tolak(string $kode, string $keterangan, ?string $pemegang = null): array
    {
        return ['boleh' => false, 'kode' => $kode, 'keterangan' => $keterangan, 'pemegang_draf' => $pemegang];
    }
}
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetGerbangTest.php`
Expected: PASS (5 uji)

- [ ] **Step 5: Buktikan tiap penjaga menggigit**

Untuk masing-masing, rusak satu syarat, jalankan, pastikan uji yang bersangkutan MERAH, lalu kembalikan:

| Yang dirusak | Uji yang harus merah |
|---|---|
| hapus blok `$adaFinal` | `sudah ada final` |
| hapus blok `$draf->has($id)` | `draf ada` |
| hapus blok `$hidup` | `pengajuan tidak hidup` |
| kosongkan `$nama` jadi `null` | `menyebut pemegangnya` |

- [ ] **Step 6: Commit**

```bash
git add app/Services/KPI/KpiTargetKoreksiService.php tests/Feature/KPI/Monitoring/KoreksiTargetGerbangTest.php
git commit -m "feat(kpi): gerbang kelayakan koreksi target"
```

---

### Task 3: Penerapan dan jejak

**Files:**
- Modify: `app/Services/KPI/KpiTargetKoreksiService.php`
- Test: `tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php`

**Interfaces:**
- Consumes: `periksaKelayakan()` dari Task 2; tabel dari Task 1.
- Produces:

```php
public function terapkan(
    array $perubahan,        // [['id_pengajuan_det' => int, 'nilai_baru' => float], ...]
    string $sebab,
    int $idUserAktor,
    bool $bolehSebagian = false,
): array
// [
//   'diterapkan' => [['id_pengajuan_det'=>int,'nilai_lama'=>float,'nilai_baru'=>float], ...],
//   'ditolak'    => [['id_pengajuan_det'=>int,'kode'=>string,'keterangan'=>string,'pemegang_draf'=>?string], ...],
//   'no_batch'   => ?string,   // null bila tidak ada yang diterapkan
// ]
```

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Services\KPI\KpiTargetKoreksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KoreksiTargetTerapkanTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): KpiTargetKoreksiService
    {
        return app(KpiTargetKoreksiService::class);
    }

    private function targetHidup(): ?object
    {
        return DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail as upd')
                ->whereColumn('upd.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail_Sementara as s')
                ->whereColumn('s.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->orderByDesc('det.Id_Pengajuan_Det')
            ->select('det.Id_Pengajuan_Det', 'det.Nilai_Target')
            ->first();
    }

    public function test_nilai_berubah_dan_jejaknya_menyimpan_nilai_lama(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $lama = (float) $t->Nilai_Target;
        $baru = $lama + 7;

        $hasil = $this->svc()->terapkan(
            [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $baru]],
            'salah ketik saat pengajuan',
            1,
        );

        $this->assertCount(1, $hasil['diterapkan']);
        $this->assertSame([], $hasil['ditolak']);

        $this->assertSame($baru, (float) DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)->value('Nilai_Target'));

        $jejak = DB::table('N_KPI_Target_Koreksi')
            ->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)
            ->orderByDesc('Id_Target_Koreksi')->first();

        $this->assertNotNull($jejak, 'Perubahan tanpa jejak berarti target aslinya hilang selamanya.');
        $this->assertSame($lama, (float) $jejak->Nilai_Target_Lama, 'Jejak harus menyimpan nilai SEBELUM koreksi.');
        $this->assertSame($baru, (float) $jejak->Nilai_Target_Baru);
        $this->assertSame('salah ketik saat pengajuan', $jejak->Sebab);
        $this->assertSame($hasil['no_batch'], $jejak->No_Batch);
    }

    public function test_gagal_tertutup_satu_ditolak_maka_nol_diterapkan(): void
    {
        $t = $this->targetHidup();
        $terkunci = DB::table('KPI_Update_Performance_Detail')
            ->orderByDesc('Id_Update_Performance_Detail')->value('Id_Pengajuan_Det');

        if (! $t || ! $terkunci) {
            $this->markTestSkipped('Butuh satu target bersih dan satu yang sudah final.');
        }

        $lama = (float) $t->Nilai_Target;

        $hasil = $this->svc()->terapkan([
            ['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $lama + 3],
            ['id_pengajuan_det' => (int) $terkunci, 'nilai_baru' => 1],
        ], 'coba campuran', 1);

        $this->assertSame([], $hasil['diterapkan'], 'Ada yang ditolak, jadi TIDAK BOLEH ada yang diterapkan.');
        $this->assertCount(2, $hasil['ditolak']);
        $this->assertNull($hasil['no_batch']);

        $this->assertSame($lama, (float) DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)->value('Nilai_Target'));
    }

    public function test_sebagian_hanya_saat_diminta_eksplisit(): void
    {
        $t = $this->targetHidup();
        $terkunci = DB::table('KPI_Update_Performance_Detail')
            ->orderByDesc('Id_Update_Performance_Detail')->value('Id_Pengajuan_Det');

        if (! $t || ! $terkunci) {
            $this->markTestSkipped('Butuh satu target bersih dan satu yang sudah final.');
        }

        $hasil = $this->svc()->terapkan([
            ['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => (float) $t->Nilai_Target + 3],
            ['id_pengajuan_det' => (int) $terkunci, 'nilai_baru' => 1],
        ], 'campuran disengaja', 1, bolehSebagian: true);

        $this->assertCount(1, $hasil['diterapkan']);
        $this->assertCount(1, $hasil['ditolak']);
        $this->assertSame('SUDAH_FINAL', $hasil['ditolak'][0]['kode']);
    }

    public function test_sebab_kosong_ditolak(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $this->expectException(\InvalidArgumentException::class);

        $this->svc()->terapkan(
            [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => 1]],
            '   ',
            1,
        );
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php`
Expected: FAIL — `Call to undefined method ...::terapkan()`

- [ ] **Step 3: Tambahkan `terapkan()` ke service**

```php
    /**
     * Terapkan koreksi. Gagal-tertutup: bila ada yang ditolak, TIDAK ADA yang
     * diterapkan kecuali pemanggil meminta $bolehSebagian secara eksplisit.
     * Hasil separuh harus jadi keputusan sadar, bukan efek samping.
     */
    public function terapkan(
        array $perubahan,
        string $sebab,
        int $idUserAktor,
        bool $bolehSebagian = false,
    ): array {
        $sebab = trim($sebab);

        if ($sebab === '') {
            throw new \InvalidArgumentException('Sebab koreksi wajib diisi.');
        }

        $kelayakan = $this->periksaKelayakan(array_column($perubahan, 'id_pengajuan_det'));

        $lolos = [];
        $ditolak = [];

        foreach ($perubahan as $p) {
            $id = (int) $p['id_pengajuan_det'];
            $status = $kelayakan[$id] ?? $this->tolak('TIDAK_DITEMUKAN', 'Target tidak ditemukan.');

            if ($status['boleh']) {
                $lolos[$id] = (float) $p['nilai_baru'];
            } else {
                $ditolak[] = [
                    'id_pengajuan_det' => $id,
                    'kode' => $status['kode'],
                    'keterangan' => $status['keterangan'],
                    'pemegang_draf' => $status['pemegang_draf'],
                ];
            }
        }

        if ($ditolak !== [] && ! $bolehSebagian) {
            return ['diterapkan' => [], 'ditolak' => $ditolak, 'no_batch' => null];
        }

        if ($lolos === []) {
            return ['diterapkan' => [], 'ditolak' => $ditolak, 'no_batch' => null];
        }

        $noBatch = 'KT'.now()->format('ymdHis').strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $diterapkan = [];

        DB::transaction(function () use ($lolos, $sebab, $idUserAktor, $noBatch, &$diterapkan) {
            // Nilai lama dibaca DI DALAM transaksi: jejak harus merekam angka
            // yang benar-benar tergantikan, bukan yang terbaca sebelum kunci.
            $lama = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Det', array_keys($lolos))
                ->lockForUpdate()
                ->pluck('Nilai_Target', 'Id_Pengajuan_Det');

            $jejak = [];

            foreach ($lolos as $id => $nilaiBaru) {
                $nilaiLama = (float) ($lama[$id] ?? 0);

                DB::table('KPI_Pengajuan_Det')
                    ->where('Id_Pengajuan_Det', $id)
                    ->update(['Nilai_Target' => $nilaiBaru]);

                $jejak[] = [
                    'No_Batch' => $noBatch,
                    'Id_Pengajuan_Det' => $id,
                    'Nilai_Target_Lama' => $nilaiLama,
                    'Nilai_Target_Baru' => $nilaiBaru,
                    'Sebab' => $sebab,
                    'Id_User_Aktor' => $idUserAktor,
                    'Waktu' => now(),
                ];

                $diterapkan[] = [
                    'id_pengajuan_det' => $id,
                    'nilai_lama' => $nilaiLama,
                    'nilai_baru' => $nilaiBaru,
                ];
            }

            DB::table('N_KPI_Target_Koreksi')->insert($jejak);
        });

        return ['diterapkan' => $diterapkan, 'ditolak' => $ditolak, 'no_batch' => $noBatch];
    }
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php`
Expected: PASS (4 uji)

- [ ] **Step 5: Buktikan penjaganya menggigit**

| Yang dirusak | Uji yang harus merah |
|---|---|
| tulis `$nilaiBaru` ke `Nilai_Target_Lama` | `jejaknya menyimpan nilai lama` |
| hapus cek `! $bolehSebagian` | `gagal tertutup` |
| hapus lemparan saat `$sebab === ''` | `sebab kosong ditolak` |

- [ ] **Step 6: Commit**

```bash
git add app/Services/KPI/KpiTargetKoreksiService.php tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php
git commit -m "feat(kpi): penerapan koreksi target beserta jejaknya"
```

---

### Task 4: Peringatan menyimpang

**Files:**
- Modify: `app/Services/KPI/KpiTargetKoreksiService.php`
- Test: `tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php`

**Interfaces:**
- Produces:

```php
public function periksaMenyimpang(int $idPengajuanDet, float $nilaiBaru, float $faktor = 10.0): ?array
// null bila wajar; selain itu ['median' => float, 'rasio' => float]
```

Aturannya sama persis dengan `kpi:cek-target-menyimpang`: median `Nilai_Target` seluruh periode pada `Id_Pengajuan_Detail` yang sama, menyimpang bila `rasio > faktor` atau `rasio < 1/faktor`. Baku `faktor` 10, minimum 2.

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `KoreksiTargetTerapkanTest`:

```php
    public function test_nilai_baru_yang_menyimpang_diperingatkan_tapi_tetap_diterapkan(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $ekstrem = max(1.0, (float) $t->Nilai_Target) * 1000;

        $peringatan = $this->svc()->periksaMenyimpang((int) $t->Id_Pengajuan_Det, $ekstrem);

        $this->assertNotNull($peringatan, 'Nilai 1000x median harus diperingatkan.');
        $this->assertGreaterThan(10, $peringatan['rasio']);

        // Memperingatkan, BUKAN memblokir: nilai yang benar tetap milik pemilik prosesnya.
        $hasil = $this->svc()->terapkan(
            [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $ekstrem]],
            'disengaja',
            1,
        );

        $this->assertCount(1, $hasil['diterapkan']);
    }
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php --filter=menyimpang`
Expected: FAIL — `Call to undefined method ...::periksaMenyimpang()`

- [ ] **Step 3: Tambahkan metodenya**

```php
    /**
     * Peringatkan bila nilai BARU justru menyimpang jauh dari saudara sebarisnya,
     * supaya koreksi tidak melahirkan salah ketik berikutnya.
     *
     * Memperingatkan, tidak memblokir — prinsip yang sama yang membuat
     * kpi:cek-target-menyimpang tidak pernah memperbaiki apa pun sendiri.
     */
    public function periksaMenyimpang(int $idPengajuanDet, float $nilaiBaru, float $faktor = 10.0): ?array
    {
        $faktor = max(2.0, $faktor);

        $idDetail = DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Det', $idPengajuanDet)
            ->value('Id_Pengajuan_Detail');

        if (! $idDetail) {
            return null;
        }

        $nilai = DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Detail', $idDetail)
            ->where('Id_Pengajuan_Det', '<>', $idPengajuanDet)
            ->pluck('Nilai_Target')
            ->map(fn ($v) => (float) $v)
            ->all();

        $median = $this->median($nilai);

        if ($median <= 0 || $nilaiBaru <= 0) {
            return null; // median nol -> rasio tidak bermakna
        }

        $rasio = $nilaiBaru / $median;

        if ($rasio > $faktor || $rasio < 1 / $faktor) {
            return ['median' => $median, 'rasio' => $rasio];
        }

        return null;
    }

    private function median(array $nilai): float
    {
        sort($nilai);
        $n = count($nilai);

        if ($n === 0) {
            return 0.0;
        }

        $tengah = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $nilai[$tengah]
            : ((float) $nilai[$tengah - 1] + (float) $nilai[$tengah]) / 2;
    }
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php`
Expected: PASS (5 uji)

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/KpiTargetKoreksiService.php tests/Feature/KPI/Monitoring/KoreksiTargetTerapkanTest.php
git commit -m "feat(kpi): peringatan nilai koreksi yang justru menyimpang"
```

---

### Task 5: Endpoint dan izin

**Files:**
- Create: `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiTargetKoreksiController.php`
- Create: `routes/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php`
- Modify: `routes/web.php` (tambahkan `require` berkas rute baru, di dekat require rute enforcement yang sudah ada)
- Test: `tests/Feature/KPI/Monitoring/KoreksiTargetIzinTest.php`

**Interfaces:**
- Consumes: `KpiTargetKoreksiService::periksaKelayakan()`, `terapkan()`, `periksaMenyimpang()`.
- Produces:
  - `GET  /api/v1/kpi-enforcement/target/kelayakan?ids[]=`
  - `POST /api/v1/kpi-enforcement/target/koreksi` (query `?sebagian=1` opsional)

- [ ] **Step 1: Tulis uji izin yang gagal**

```php
<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KoreksiTargetIzinTest extends TestCase
{
    use DatabaseTransactions;

    /** Pemegang peran PEMANTAU saja tidak boleh bisa mengubah angka penilaian. */
    public function test_pemantau_saja_ditolak(): void
    {
        $user = User::query()
            ->whereNotNull('Id_Users')
            ->orderBy('Id_Users')
            ->first();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user di data dev.');
        }

        if ($user->memilikiJabatan('kpiKoreksiTarget')) {
            $this->markTestSkipped('User contoh justru sudah punya izin koreksi.');
        }

        $this->actingAs($user)
            ->postJson('/api/v1/kpi-enforcement/target/koreksi', [
                'sebab' => 'coba',
                'perubahan' => [['id_pengajuan_det' => 1, 'nilai_baru' => 1]],
            ])
            ->assertStatus(403);
    }

    /** Izin diberikan per orang lewat HRIS_Page_Access, bukan lewat grup fitur. */
    public function test_pemegang_izin_eksplisit_diterima(): void
    {
        $user = User::query()->whereNotNull('Id_Users')->orderBy('Id_Users')->first();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user di data dev.');
        }

        // Baris DIKLON dari yang sudah ada, bukan disusun dari nol: tabelnya
        // punya Kode_Perusahaan/ID_Level/ID_Divisi yang ikut menentukan bentuk
        // baris yang sah, dan menebaknya membuat uji gagal karena alasan yang
        // tidak ada hubungannya dengan izin. Perhatikan ejaan `Jenis_page`.
        $contoh = DB::table('HRIS_Page_Access')->where('UserID_Web', $user->Id_Users)->first();

        if (! $contoh) {
            $this->markTestSkipped('User contoh belum punya baris HRIS_Page_Access untuk diklon.');
        }

        $baris = (array) $contoh;
        unset($baris['Id_Page_Access']);
        $baris['Jenis_page'] = 'kpiKoreksiTarget';
        $baris['Flag_Aktif'] = 'Y';

        DB::table('HRIS_Page_Access')->insert($baris);

        $res = $this->actingAs($user)
            ->getJson('/api/v1/kpi-enforcement/target/kelayakan?ids[]=999999999');

        $this->assertNotSame(403, $res->status(), 'Izin eksplisit lewat HRIS_Page_Access harus diterima.');
    }

    /** Kunci ini TIDAK BOLEH ada di grup fitur: satu role memberi seluruh grup. */
    public function test_kunci_izin_tidak_terdaftar_di_grup_fitur(): void
    {
        $this->assertSame(
            0,
            DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', 'kpiKoreksiTarget')->count(),
            'kpiKoreksiTarget di grup fitur berarti setiap pemegang role KPI ikut dapat kuasa mengubah target.',
        );
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetIzinTest.php`
Expected: FAIL — 404 (rutenya belum ada), bukan 403

- [ ] **Step 3: Tulis controllernya**

```php
<?php

namespace App\Http\Controllers\KPI\Monitoring\Enforcement;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\KpiTargetKoreksiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dua pintu koreksi target. TANPA aturan bisnis: seluruh keputusan kelayakan
 * dan penerapan tinggal di KpiTargetKoreksiService supaya layar dan simpan
 * tidak pernah bisa berselisih.
 */
class KpiTargetKoreksiController extends Controller
{
    public function __construct(private KpiTargetKoreksiService $svc) {}

    public function kelayakan(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'required|integer',
        ]);

        return ResponseHelper::success($this->svc->periksaKelayakan($data['ids']), 'Kelayakan dinilai', 200);
    }

    public function koreksi(Request $request)
    {
        $data = $request->validate([
            'sebab' => 'required|string|max:500',
            'perubahan' => 'required|array|min:1|max:500',
            'perubahan.*.id_pengajuan_det' => 'required|integer',
            'perubahan.*.nilai_baru' => 'required|numeric',
        ]);

        $bolehSebagian = $request->boolean('sebagian');

        $hasil = $this->svc->terapkan(
            $data['perubahan'],
            $data['sebab'],
            (int) Auth::id(),
            $bolehSebagian,
        );

        // Ada yang ditolak sementara pemanggil tidak meminta hasil separuh:
        // laporkan sebagai penolakan, jangan diam-diam menerapkan sebagian.
        if ($hasil['ditolak'] !== [] && ! $bolehSebagian) {
            // response()->json langsung, bukan ResponseHelper::error(): balasan
            // ini HARUS membawa daftar penolakannya, dan error() hanya
            // memulangkan success/status/message tanpa tempat untuk hasil.
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Sebagian target tidak layak dikoreksi',
                'result' => $hasil,
            ], 422);
        }

        return ResponseHelper::success($hasil, 'Koreksi diterapkan', 200);
    }
}
```

- [ ] **Step 4: Tulis berkas rute**

`routes/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php`:

```php
<?php

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiTargetKoreksiController;
use Illuminate\Support\Facades\Route;

// Kunci izin SENDIRI, bukan kpiEnforcementMonitoring: peran pemantau tidak
// boleh ikut mendapat kuasa mengubah angka penilaian.
Route::middleware(['auth', 'check.jabatan:kpiKoreksiTarget'])->group(function () {
    Route::prefix('/api/v1/kpi-enforcement/target')->group(function () {
        Route::get('/kelayakan', [KpiTargetKoreksiController::class, 'kelayakan']);
        Route::post('/koreksi', [KpiTargetKoreksiController::class, 'koreksi']);
    });
});
```

Lalu tambahkan di `routes/web.php`, sebaris setelah `require` rute enforcement yang sudah ada:

```php
require __DIR__ . '/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php';
```

- [ ] **Step 5: Bersihkan cache rute lalu jalankan**

```bash
php artisan route:clear
php artisan test tests/Feature/KPI/Monitoring/KoreksiTargetIzinTest.php
```

Expected: PASS (3 uji)

Cache rute basi memunculkan 404 pada rute yang jelas ada — jangan kejar hantunya.

- [ ] **Step 6: Buktikan penjaga izinnya menggigit**

Ganti `check.jabatan:kpiKoreksiTarget` jadi `check.jabatan:kpiEnforcementMonitoring`, jalankan, pastikan `pemantau saja ditolak` MERAH, lalu kembalikan.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/KPI/Monitoring/Enforcement/KpiTargetKoreksiController.php routes/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php routes/web.php tests/Feature/KPI/Monitoring/KoreksiTargetIzinTest.php
git commit -m "feat(kpi): endpoint koreksi target berkunci izin sendiri"
```

---

### Task 6: Layar

**Files:**
- Modify: `resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue`

**Interfaces:**
- Consumes: kedua endpoint dari Task 5.
- Produces: —

- [ ] **Step 1: Ambil kelayakan saat tabel parameter dimuat**

Setelah baris monitoring parameter diterima, kumpulkan seluruh `Id_Pengajuan_Det` yang tampil lalu:

```js
async muatKelayakanTarget(ids) {
    if (!ids.length) { this.kelayakanTarget = {}; return; }
    try {
        const { data } = await axios.get('/api/v1/kpi-enforcement/target/kelayakan', {
            params: { ids },
        });
        this.kelayakanTarget = data?.result ?? {};
    } catch (e) {
        // Gagal-tertutup: tanpa jawaban, TIDAK ADA sel yang boleh disunting.
        this.kelayakanTarget = {};
        console.error('Gagal mengambil kelayakan koreksi target:', e);
    }
},
```

Tambahkan `kelayakanTarget: {}` dan `izinKoreksiTarget: false` ke `data()`.

- [ ] **Step 2: Sel hanya bisa disunting bila layak**

```js
selBolehDisunting(idPengajuanDet) {
    return this.izinKoreksiTarget && this.kelayakanTarget?.[idPengajuanDet]?.boleh === true;
},
alasanSelTerkunci(idPengajuanDet) {
    return this.kelayakanTarget?.[idPengajuanDet]?.keterangan ?? '';
},
```

Sel yang terkunci menampilkan `alasanSelTerkunci()` sebagai `title`, sehingga penolakan `ADA_DRAF` yang menyebut nama pemegangnya terbaca saat disorot. Yang mati adalah **sel per sel** — bukan baris, bukan kolom.

- [ ] **Step 3: Satu kotak sebab, satu simpan**

Kirim seluruh sel yang diubah dalam satu `POST`, dengan satu `sebab`. Saat balasannya 422, tampilkan daftar `result.ditolak` apa adanya (kode + keterangan per target) dan tawarkan tombol "terapkan yang bisa saja" yang mengulang permintaan dengan `?sebagian=1`.

Jangan pernah mengirim `sebagian=1` tanpa pengguna menekannya.

- [ ] **Step 4: Bangun**

Run: `npx vite build --logLevel error`
Expected: keluar dengan kode 0

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue
git commit -m "feat(kpi): sel target bisa dikoreksi di halaman enforcement"
```

---

### Task 7: Verifikasi menyeluruh

**Files:** —

- [ ] **Step 1: Jalankan suite KPI**

Run: `php artisan test tests/Unit/KPI tests/Feature/KPI`
Expected: 0 gagal. Bandingkan dengan garis dasar sebelum pekerjaan ini dimulai; kegagalan baru harus dijelaskan, bukan diterima.

- [ ] **Step 2: Pastikan tak ada aturan yang tersalin**

```bash
grep -rn "SUDAH_FINAL\|ADA_DRAF\|PENGAJUAN_TIDAK_HIDUP" --include=*.php app/ | grep -v KpiTargetKoreksiService
```

Expected: hanya controller yang meneruskan hasil, tidak ada yang menilai ulang sendiri.

- [ ] **Step 3: Perbarui `status.json` bila DDL sudah dieksekusi**

Isi `execution.result` dengan hasil sebenarnya (jumlah objek terpasang, tanggal, database).

- [ ] **Step 4: Commit dan buka PR**

```bash
git push -u origin feat/kpi/koreksi-target-enforcement
```

PR menyebut: masalah salah ketik target yang permanen, ketiga syarat gerbang, alasan draf memblokir (finalisasi menyalin `Score`), kenapa izinnya tidak masuk grup fitur, dan bahwa skor serta YTD yang sudah terbit tidak bergeser.

---

## Catatan untuk pelaksana

**`Id_Target_Breakdown` sengaja tidak disentuh.** `terapkan()` hanya menulis
`Nilai_Target`. Tautan ke breakdown dibiarkan apa adanya supaya asal-usul baris
tetap terlacak; fakta bahwa angkanya diubah manusia sudah tersimpan di
`N_KPI_Target_Koreksi`. Jangan "merapikan" dengan mengosongkannya — itu
keputusan rancangan, bukan kelalaian (lihat spec, Keputusan nomor 4).

**Yang paling mudah salah:**

1. **Menambahkan `kpiKoreksiTarget` ke `N_HRIS_Grup_Fitur_Map`.** Terasa benar karena begitulah halaman lain didaftarkan. Di sini justru memusnahkan tujuannya: satu role memberi seluruh halaman grup fiturnya sekaligus. Task 5 Step 1 menguji ini.

2. **Menyalin aturan kelayakan ke frontend** supaya sel bisa dimatikan tanpa memanggil server. Layar dan server lalu bisa berselisih diam-diam. Panggil endpoint kelayakan.

3. **Menerapkan sebagian secara diam-diam** ketika ada yang ditolak. Gagal-tertutup adalah keputusan sadar, dan `sebagian=1` hanya boleh datang dari tekanan tombol pengguna.

4. **Membaca nilai lama di luar transaksi.** Jejaknya lalu bisa merekam angka yang bukan angka yang tergantikan.
