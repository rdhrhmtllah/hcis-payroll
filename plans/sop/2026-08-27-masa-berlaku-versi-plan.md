# Masa Berlaku & Jadwal Review Versi SOP — rencana implementasi (slice A)

> **Untuk pelaksana agen:** kerjakan tugas demi tugas berurutan. Langkah memakai
> checkbox (`- [ ]`). Setiap tugas berakhir pada deliverable yang bisa diuji
> sendiri.

**Tujuan:** Langkah Pemberkasan meminta tiga tanggal (berlaku, kadaluarsa,
jadwal review berikutnya) dengan baku dari jenis dokumen, dan wizard menyimpan
seluruh isian — termasuk catatan review — dalam satu klik.

**Pendekatan:** Aturan masa berlaku hidup sebagai data di master jenis dokumen;
kewajibannya ditegakkan sebagai baris syarat transisi, bukan sebagai `NOT NULL`
maupun cabang di kode. Catatan review pindah kepemilikan ke cangkang wizard
sehingga aksi utama bisa menyertakannya, tanpa mengubah bentuk muatan yang
dikirim ke endpoint review.

**Tech stack:** Laravel 11 · SQL Server (ODBC 18) · Inertia + Vue 3 Options API ·
PHPUnit · Vitest

**Spec:** `plans/sop/2026-08-27-masa-berlaku-versi-design.md`

## Batasan global

- Target database development: **`Web_HRIS`** pada host team311. Verifikasi
  dengan `SELECT DB_NAME()` sebelum eksekusi apa pun.
- **Jangan pernah** menjalankan `drop.sql`, `DROP`, `TRUNCATE`, atau migration
  Laravel. Hanya `forward.sql` dan `data-change.sql` yang boleh dieksekusi agen.
- `unset DATABASE_URL` sebelum setiap perintah `php artisan`.
- Jangan pernah `git add -A` atau `git add .` — tambahkan berkas per path.
- Nama kolom baku proyek: `Kata_Berkapital_Dipisah_Underscore`.
- Baku masa berlaku **24 bulan**, siklus review **12 bulan**. Angka ini hidup di
  `N_SOP_Master_Jenis_Dokumen`; `24`/`12` di kode hanya fallback terakhir.
- Nama kolom baru wajib **`Tanggal_Review_Berikutnya`** — bukan `Tanggal_Review`,
  yang sudah dipakai `N_SOP_Item_Versi_Review` untuk arah waktu sebaliknya.
- Urutan tanggal yang sah: `Tanggal_Berlaku < Tanggal_Review_Berikutnya ≤ Tanggal_Kadaluarsa`.
- Berkas baru SOP tetap di pohon `LMS/SOP`, bukan `DMS` (keputusan 2026-08-27,
  spec §4).

---

## Peta berkas

| Berkas | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-049-.../forward.sql` | tiga kolom baru |
| `database/dml/DML-2026-050-.../data-change.sql` | seed baku + tiga baris syarat |
| `app/Services/LMS/SOP/SopMasaBerlakuService.php` | **satu-satunya** penghitung baku & turunan |
| `SopDokumenService.php` | persistensi + projeksi kolom baru |
| `SopTahapVersiService.php` | evaluasi syarat tanggal + pelaporan langkah Review |
| `SopAdminDokumenController.php` | validasi bentuk + urutan tanggal |
| `SopAdminVersiWizardController.php` | mengirim dua angka bulan sebagai prop |
| `LangkahPemberkasan.vue` | tiga picker + auto-isi yang tahu diri |
| `LangkahReview.vue` | komponen terkendali; logika berkas tetap di sini |
| `SopVersiWizard.vue` | pemilik state review + simpan dua kiriman |

---

## Tugas 1: Artefak & eksekusi basis data

**Berkas:**
- Buat: `database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql`
- Buat: `database/ddl/DDL-2026-049-sop-masa-berlaku-versi/drop.sql`
- Buat: `database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql`
- Buat: `docs/changes/DDL-2026-049-sop-masa-berlaku-versi/README.md`
- Buat: `docs/changes/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/README.md`
- Ubah: `database/ddl/status.json`

**Menghasilkan:** kolom `N_SOP_Item_Versi.Tanggal_Review_Berikutnya`,
`N_SOP_Master_Jenis_Dokumen.Masa_Berlaku_Bulan`,
`N_SOP_Master_Jenis_Dokumen.Siklus_Review_Bulan`; tiga baris
`N_SOP_Master_Tahap_Versi_Syarat` untuk `FILING_REGISTERED`.

- [ ] **Langkah 1: Periksa keadaan objek sebelum menyentuhnya**

```bash
unset DATABASE_URL && php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
echo 'DB=' . DB::selectOne('SELECT DB_NAME() AS n')->n . PHP_EOL;
foreach (['N_SOP_Item_Versi','N_SOP_Master_Jenis_Dokumen'] as \\\$t)
  foreach (DB::select(\"SELECT COLUMN_NAME c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='\\\$t'\") as \\\$r)
    echo \\\$t.'.'.\\\$r->c.PHP_EOL;
"
```

Harapan: `DB=Web_HRIS`; tidak ada `Tanggal_Review_Berikutnya`,
`Masa_Berlaku_Bulan`, maupun `Siklus_Review_Bulan`.

- [ ] **Langkah 2: Tulis `forward.sql`**

```sql
/* Change ID: DDL-2026-049-sop-masa-berlaku-versi */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Review_Berikutnya')
    ALTER TABLE N_SOP_Item_Versi ADD Tanggal_Review_Berikutnya date NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Masa_Berlaku_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen ADD Masa_Berlaku_Bulan int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Siklus_Review_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen ADD Siklus_Review_Bulan int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-049-sop-masa-berlaku-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-049-sop-masa-berlaku-versi', 'DDL', 'Masa berlaku dan jadwal review versi', 'Tanggal_Review_Berikutnya pada versi; Masa_Berlaku_Bulan dan Siklus_Review_Bulan sebagai aturan baku per jenis dokumen.', 'database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql', 'docs/changes/DDL-2026-049-sop-masa-berlaku-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'masa-berlaku');
GO
```

- [ ] **Langkah 3: Tulis `drop.sql` — pendamping manual, JANGAN dijalankan**

```sql
/* Change ID: DDL-2026-049-sop-masa-berlaku-versi — pembersihan manual.
   Tidak boleh dijalankan agen. */

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Review_Berikutnya')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tanggal_Review_Berikutnya;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Masa_Berlaku_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen DROP COLUMN Masa_Berlaku_Bulan;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Siklus_Review_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen DROP COLUMN Siklus_Review_Bulan;
GO
DELETE FROM N_SOP_Master_Tahap_Versi_Syarat
WHERE Kode_Syarat IN ('TANGGAL_BERLAKU', 'TANGGAL_KADALUARSA', 'TANGGAL_REVIEW_BERIKUTNYA');
GO
```

- [ ] **Langkah 4: Jalankan `forward.sql` ke development**

Cakupan: 3 `ALTER TABLE ... ADD` (tambah kolom nullable, tidak menimpa data)
+ 1 `INSERT` ke `N_SYSTEM_Database_Change`. Perkiraan baris terpengaruh: 1
baris insert; ALTER tidak menyentuh baris data.

```bash
unset DATABASE_URL && php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
\\\$sql = file_get_contents('database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql');
foreach (preg_split('/^\s*GO\s*$/mi', \\\$sql) as \\\$b) { \\\$b = trim(\\\$b); if (\\\$b !== '') DB::unprepared(\\\$b); }
echo 'OK' . PHP_EOL;
"
```

- [ ] **Langkah 5: Verifikasi kolomnya ada**

Ulangi kueri Langkah 1. Harapan: ketiga kolom muncul.

- [ ] **Langkah 6: Tulis `data-change.sql`**

```sql
/* Change ID: DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal */

UPDATE N_SOP_Master_Jenis_Dokumen
SET Masa_Berlaku_Bulan = 24
WHERE Masa_Berlaku_Bulan IS NULL;
GO

UPDATE N_SOP_Master_Jenis_Dokumen
SET Siklus_Review_Bulan = 12
WHERE Siklus_Review_Bulan IS NULL;
GO

INSERT INTO N_SOP_Master_Tahap_Versi_Syarat
(Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan, Flag_Aktif, Created_By)
SELECT t.Id_Tahap, s.kode, s.nama, s.urut, 'Y', NULL
FROM (VALUES
    ('FILING_REGISTERED', 'TANGGAL_BERLAKU',           N'Tanggal mulai berlaku',  3),
    ('FILING_REGISTERED', 'TANGGAL_KADALUARSA',        N'Tanggal kadaluarsa',     4),
    ('FILING_REGISTERED', 'TANGGAL_REVIEW_BERIKUTNYA', N'Jadwal review berikutnya', 5)
) s(tahap, kode, nama, urut)
JOIN N_SOP_Master_Tahap_Versi t ON t.Kode_Tahap = s.tahap
WHERE NOT EXISTS (
    SELECT 1 FROM N_SOP_Master_Tahap_Versi_Syarat x
    WHERE x.Tahap_Id = t.Id_Tahap AND x.Kode_Syarat = s.kode
);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal', 'DML', 'Baku masa berlaku dan syarat tanggal terbit', 'Baku 24/12 bulan per jenis dokumen; tiga syarat tanggal untuk meninggalkan Pemberkasan menuju terbit.', 'database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql', 'docs/changes/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'masa-berlaku');
GO
```

- [ ] **Langkah 7: Jalankan `data-change.sql` ke development**

Cakupan yang harus dilaporkan sebelum eksekusi: `N_SOP_Master_Jenis_Dokumen`
predikat `Masa_Berlaku_Bulan IS NULL` — **7 baris**; predikat
`Siklus_Review_Bulan IS NULL` — **7 baris**; `N_SOP_Master_Tahap_Versi_Syarat`
insert — **3 baris**; `N_SYSTEM_Database_Change` insert — **1 baris**.

Perintahnya sama seperti Langkah 4 dengan path DML.

- [ ] **Langkah 8: Verifikasi seed dan syarat**

```bash
unset DATABASE_URL && php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
foreach (DB::table('N_SOP_Master_Jenis_Dokumen')->get(['Kode_Jenis','Masa_Berlaku_Bulan','Siklus_Review_Bulan']) as \\\$r)
  echo \\\$r->Kode_Jenis.' '.\\\$r->Masa_Berlaku_Bulan.'/'.\\\$r->Siklus_Review_Bulan.PHP_EOL;
foreach (DB::table('N_SOP_Master_Tahap_Versi_Syarat as s')->join('N_SOP_Master_Tahap_Versi as t','t.Id_Tahap','=','s.Tahap_Id')->where('t.Kode_Tahap','FILING_REGISTERED')->orderBy('s.Urutan')->get(['s.Kode_Syarat','s.Nama_Syarat']) as \\\$r)
  echo \\\$r->Kode_Syarat.' | '.\\\$r->Nama_Syarat.PHP_EOL;
"
```

Harapan: 7 jenis semuanya `24/12`; lima syarat `FILING_REGISTERED`
(`BERKAS`, `REALISASI_PENGESAH`, lalu tiga yang baru).

- [ ] **Langkah 9: Tulis dua README `docs/changes/`**

Isi wajib tiap README: change ID, target database, deskripsi, objek terdampak,
seluruh path DDL/DML/dokumentasi, checksum forward, status, pekerjaan yang
diselesaikan agen, dan pekerjaan yang tersisa untuk pengguna.

Checksum diambil dengan:

```bash
sha256sum database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql
sha256sum database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql
```

- [ ] **Langkah 10: Daftarkan keduanya di `database/ddl/status.json`**

Tambahkan dua objek ke array `changes`, mengikuti bentuk baris terakhir yang
sudah ada (`change_id`, `type`, `title`, `status`, `development`, `sql_tracker`,
`production`, `verification`, `artifact_path`, `drop_path`,
`documentation_path`, `forward_checksum_sha256`, `affected_objects`, `catatan`,
`execution`). `status` = `DEV_DONE`, `sql_tracker` = `PENDING`,
`production` = `PENDING`.

- [ ] **Langkah 11: Commit**

```bash
git add database/ddl/DDL-2026-049-sop-masa-berlaku-versi database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal docs/changes/DDL-2026-049-sop-masa-berlaku-versi docs/changes/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal database/ddl/status.json
git commit -m "feat(dms): kolom masa berlaku dan jadwal review versi SOP (DDL-2026-049, DML-2026-050)"
```

---

## Tugas 2: Resolver baku

**Berkas:**
- Buat: `app/Services/LMS/SOP/SopMasaBerlakuService.php`
- Uji: `tests/Unit/LMS/SOP/SopMasaBerlakuTest.php`

**Memakai:** kolom dari Tugas 1.

**Menghasilkan:**

```php
aturanDokumen(int $idDokumen): array   // {Masa_Berlaku_Bulan:int, Siklus_Review_Bulan:int}
turunanDari(int $idDokumen, ?string $tanggalBerlaku): array
                                       // {Tanggal_Kadaluarsa:?string, Tanggal_Review_Berikutnya:?string}
lengkapiBaku(int $idDokumen, array $data): array  // isi yang kosong saja
```

- [ ] **Langkah 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\SopMasaBerlakuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Baku masa berlaku adalah DATA, bukan konstanta.
 *
 * Uji ini menjaga dua hal sekaligus: angkanya benar-benar dibaca dari master
 * jenis dokumen, DAN dokumen yang tak punya jenis tetap mendapat baku. Yang
 * kedua bukan kemewahan — Jenis_Dokumen_Id boleh NULL, dan dokumen tanpa baku
 * akan lolos diam-diam dari seluruh mekanisme peringatan yang dibangun di
 * atasnya.
 */
class SopMasaBerlakuTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopMasaBerlakuService
    {
        return app(SopMasaBerlakuService::class);
    }

    private function dokumenDenganJenis(?int $bulanBerlaku, ?int $bulanReview): int
    {
        $idJenis = DB::table('N_SOP_Master_Jenis_Dokumen')->insertGetId([
            'Kode_Jenis' => 'UJI'.substr((string) microtime(true), -6),
            'Nama_Jenis' => 'Jenis Uji Masa Berlaku',
            'Level_Hirarki' => 9,
            'Urutan' => 999,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Masa_Berlaku_Bulan' => $bulanBerlaku,
            'Siklus_Review_Bulan' => $bulanReview,
        ], 'Id_SOP_Jenis_Dokumen');

        return $this->dokumen($idJenis);
    }

    private function dokumen(?int $idJenis): int
    {
        return DB::table('N_SOP_Dokumen')->insertGetId([
            'Judul' => 'SOP UJI MASA BERLAKU '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $idJenis,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ], 'Id_SOP_Dokumen');
    }

    public function test_aturan_dibaca_dari_jenis_dokumen(): void
    {
        $id = $this->dokumenDenganJenis(36, 18);

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 36, 'Siklus_Review_Bulan' => 18],
            $this->svc()->aturanDokumen($id),
        );
    }

    public function test_dokumen_tanpa_jenis_tetap_dapat_baku(): void
    {
        $id = $this->dokumen(null);

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 24, 'Siklus_Review_Bulan' => 12],
            $this->svc()->aturanDokumen($id),
        );
    }

    public function test_jenis_tanpa_setelan_jatuh_ke_baku(): void
    {
        $id = $this->dokumenDenganJenis(null, null);

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 24, 'Siklus_Review_Bulan' => 12],
            $this->svc()->aturanDokumen($id),
        );
    }

    public function test_turunan_dihitung_dari_tanggal_berlaku(): void
    {
        $id = $this->dokumenDenganJenis(24, 12);

        $this->assertSame([
            'Tanggal_Kadaluarsa' => '2028-03-15',
            'Tanggal_Review_Berikutnya' => '2027-03-15',
        ], $this->svc()->turunanDari($id, '2026-03-15'));
    }

    /**
     * Tanpa tanggal berlaku, TIDAK ada turunan.
     *
     * Menghitungnya dari hari ini berarti mengarang fakta: tanggal berlakunya
     * memang belum diputuskan, dan kadaluarsa yang lahir dari tebakan akan
     * memicu notifikasi atas keputusan yang tak pernah diambil siapa pun.
     */
    public function test_tanpa_tanggal_berlaku_tidak_ada_turunan(): void
    {
        $id = $this->dokumenDenganJenis(24, 12);

        $this->assertSame([
            'Tanggal_Kadaluarsa' => null,
            'Tanggal_Review_Berikutnya' => null,
        ], $this->svc()->turunanDari($id, null));
    }

    /** Isian admin TIDAK ditimpa baku. */
    public function test_lengkapi_baku_menghormati_isian_yang_sudah_ada(): void
    {
        $id = $this->dokumenDenganJenis(24, 12);

        $hasil = $this->svc()->lengkapiBaku($id, [
            'Tanggal_Berlaku' => '2026-03-15',
            'Tanggal_Kadaluarsa' => '2030-01-01',
        ]);

        $this->assertSame('2030-01-01', $hasil['Tanggal_Kadaluarsa']);
        $this->assertSame('2027-03-15', $hasil['Tanggal_Review_Berikutnya']);
    }
}
```

- [ ] **Langkah 2: Jalankan, pastikan gagal**

```bash
unset DATABASE_URL && php artisan test --filter=SopMasaBerlakuTest
```

Harapan: GAGAL — `Class "App\Services\LMS\SOP\SopMasaBerlakuService" not found`.

- [ ] **Langkah 3: Tulis service-nya**

```php
<?php

namespace App\Services\LMS\SOP;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Baku masa berlaku & jadwal review sebuah dokumen SOP.
 *
 * KENAPA DATA, BUKAN KONSTANTA. Modul ini konsisten menaruh aturan sebagai
 * baris master — Cap_Tanggal, Status_Teknis, Butuh_Berkas, seluruh tabel
 * transisi dan syarat. Konstanta 24 di dalam kelas ini akan jadi satu-satunya
 * aturan SOP yang butuh rilis untuk diubah, padahal biaya kolomnya sama saja.
 *
 * FALLBACK BERLAPIS, dan itu disengaja. Jenis_Dokumen_Id boleh NULL, dan jenis
 * yang ada boleh belum disetel. Tanpa nilai terakhir yang selalu ada, dokumen
 * tanpa jenis akan lahir tanpa kadaluarsa dan lolos diam-diam dari seluruh
 * mekanisme peringatan yang dibangun di atas kolom ini.
 */
class SopMasaBerlakuService
{
    /** Jaring terakhir ketika master tak menyebut apa pun. */
    private const BAKU_BERLAKU_BULAN = 24;

    private const BAKU_REVIEW_BULAN = 12;

    /** @return array{Masa_Berlaku_Bulan:int, Siklus_Review_Bulan:int} */
    public function aturanDokumen(int $idDokumen): array
    {
        $r = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as j', 'j.Id_SOP_Jenis_Dokumen', '=', 'd.Jenis_Dokumen_Id')
            ->where('d.Id_SOP_Dokumen', $idDokumen)
            ->first(['j.Masa_Berlaku_Bulan', 'j.Siklus_Review_Bulan']);

        /* Nol dan negatif diperlakukan sama dengan kosong: "berlaku 0 bulan"
           bukan kebijakan, itu baris master yang salah isi. */
        $bulan = fn ($v, int $baku) => ((int) $v) > 0 ? (int) $v : $baku;

        return [
            'Masa_Berlaku_Bulan' => $bulan($r->Masa_Berlaku_Bulan ?? null, self::BAKU_BERLAKU_BULAN),
            'Siklus_Review_Bulan' => $bulan($r->Siklus_Review_Bulan ?? null, self::BAKU_REVIEW_BULAN),
        ];
    }

    /** @return array{Tanggal_Kadaluarsa:?string, Tanggal_Review_Berikutnya:?string} */
    public function turunanDari(int $idDokumen, ?string $tanggalBerlaku): array
    {
        if (! filled($tanggalBerlaku)) {
            return ['Tanggal_Kadaluarsa' => null, 'Tanggal_Review_Berikutnya' => null];
        }

        $aturan = $this->aturanDokumen($idDokumen);
        $mulai = CarbonImmutable::parse($tanggalBerlaku)->startOfDay();

        return [
            'Tanggal_Kadaluarsa' => $mulai->addMonths($aturan['Masa_Berlaku_Bulan'])->format('Y-m-d'),
            'Tanggal_Review_Berikutnya' => $mulai->addMonths($aturan['Siklus_Review_Bulan'])->format('Y-m-d'),
        ];
    }

    /**
     * Mengisi yang KOSONG saja.
     *
     * Dipanggil di jalur simpan supaya API di luar wizard tak melahirkan versi
     * tanpa masa berlaku. Isian admin tak pernah ditimpa — baku adalah usulan,
     * bukan koreksi.
     */
    public function lengkapiBaku(int $idDokumen, array $data): array
    {
        $turunan = $this->turunanDari($idDokumen, $data['Tanggal_Berlaku'] ?? null);

        foreach ($turunan as $kunci => $nilai) {
            if ($nilai !== null && ! filled($data[$kunci] ?? null)) {
                $data[$kunci] = $nilai;
            }
        }

        return $data;
    }
}
```

- [ ] **Langkah 4: Jalankan, pastikan lulus**

```bash
unset DATABASE_URL && php artisan test --filter=SopMasaBerlakuTest
```

Harapan: 6 lulus.

- [ ] **Langkah 5: Commit**

```bash
git add app/Services/LMS/SOP/SopMasaBerlakuService.php tests/Unit/LMS/SOP/SopMasaBerlakuTest.php
git commit -m "feat(dms): resolver baku masa berlaku per jenis dokumen"
```

---

## Tugas 3: Backend menerima, menyimpan, dan mengembalikan tiga tanggal

**Berkas:**
- Ubah: `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` (`aturanVersi()`, `storeVersi()`, `updateVersi()`)
- Ubah: `app/Services/LMS/SOP/SopDokumenService.php` (`tambahVersi()`, `ubahVersi()`, peta label)
- Ubah: `app/Services/LMS/SOP/SopDocumentControlService.php` (dua projeksi, baris 191 & 240)

**Memakai:** `SopMasaBerlakuService` dari Tugas 2.

- [ ] **Langkah 1: Kembalikan `Tanggal_Kadaluarsa` ke validasi, tambah yang baru**

Di `aturanVersi()`, ganti blok komentar "Tanggal_Kadaluarsa TIDAK diterima lagi"
dengan:

```php
            /* Masa berlaku diminta lagi sejak 2026-08-27. Pencabutannya
               2026-08-15 beralasan "tak ada aturan yang bertindak atasnya", dan
               itu benar saat itu. Yang berubah bukan pendapat melainkan fakta:
               slice B/C memberi kolom ini status KEDALUWARSA, spanduk pembaca,
               dan notifikasi hitung mundur. Lihat
               plans/sop/2026-08-27-masa-berlaku-versi-design.md §2.3. */
            'Tanggal_Kadaluarsa' => 'nullable|date',
            'Tanggal_Review_Berikutnya' => 'nullable|date',
```

- [ ] **Langkah 2: Tegakkan urutan tanggal di controller**

Tambahkan metode privat, lalu panggil di `storeVersi()` dan `updateVersi()`
tepat sesudah `$request->validate(...)`:

```php
    /**
     * Urutan tiga tanggal masa berlaku.
     *
     * DI SINI, bukan sebagai aturan `after:` di daftar validasi: ketiganya
     * boleh kosong pada draft, dan `after:Tanggal_Berlaku` pada field nullable
     * diam-diam lolos begitu acuannya null — sehingga versi bisa menyimpan
     * kadaluarsa yang mendahului tanggal berlakunya sendiri.
     */
    private function periksaUrutanTanggal(array $d): ?string
    {
        $t = fn (string $k) => filled($d[$k] ?? null) ? strtotime((string) $d[$k]) : null;

        [$berlaku, $review, $kadaluarsa] =
            [$t('Tanggal_Berlaku'), $t('Tanggal_Review_Berikutnya'), $t('Tanggal_Kadaluarsa')];

        if ($berlaku && $kadaluarsa && $kadaluarsa <= $berlaku) {
            return 'Tanggal kadaluarsa harus setelah tanggal mulai berlaku.';
        }
        if ($berlaku && $review && $review <= $berlaku) {
            return 'Jadwal review berikutnya harus setelah tanggal mulai berlaku.';
        }
        if ($review && $kadaluarsa && $review > $kadaluarsa) {
            return 'Jadwal review berikutnya tidak boleh melewati tanggal kadaluarsa.';
        }

        return null;
    }
```

Pemanggilannya, di kedua handler:

```php
        if (($galat = $this->periksaUrutanTanggal($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }
```

- [ ] **Langkah 3: Persistensi**

Di `SopDokumenService::tambahVersi()`, sesudah baris `'Tanggal_Kadaluarsa' => ...`:

```php
            'Tanggal_Review_Berikutnya' => $data['Tanggal_Review_Berikutnya'] ?? null,
```

Di `ubahVersi()` — tiga tempat: daftar kolom `first([...])`, muatan `update([...])`,
dan array pembanding `bedaNilai()`. Tambahkan `'Tanggal_Review_Berikutnya'` /
`'Tanggal_Review_Berikutnya' => $data['Tanggal_Review_Berikutnya'] ?? null,`.

Di peta label (`Tanggal_Kadaluarsa' => 'Tanggal kadaluarsa',`), tambahkan:

```php
        'Tanggal_Review_Berikutnya' => 'Jadwal review berikutnya',
```

- [ ] **Langkah 4: Lengkapi baku di sisi server**

Di `tambahVersi()` tepat sesudah `pastikanFieldSesuaiTipe(...)`:

```php
        $data = app(SopMasaBerlakuService::class)->lengkapiBaku($idDokumen, $data);
```

Di `ubahVersi()` tepat sesudah `pastikanFieldSesuaiTipe(...)` — id dokumennya
dibaca dari baris versi, jadi tempatkan sesudah `$versi` terbaca:

```php
        $data = app(SopMasaBerlakuService::class)
            ->lengkapiBaku((int) $versi->Id_SOP_Dokumen, $data);
```

Tambahkan `use App\Services\LMS\SOP\SopMasaBerlakuService;` bila belum ada.

- [ ] **Langkah 5: Projeksi**

Di `SopDocumentControlService.php`, pada **kedua** daftar kolom (baris ±191 dan
±240), ubah:

```php
'Tanggal_Permintaan', 'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa',
'Tanggal_Review_Berikutnya',
```

Kolom yang hanya ada di salah satu kueri berakhir sebagai "Undefined property"
— komentar di berkas itu sudah memperingatkannya.

- [ ] **Langkah 6: Periksa sintaksis**

```bash
php -l app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php
php -l app/Services/LMS/SOP/SopDokumenService.php
php -l app/Services/LMS/SOP/SopDocumentControlService.php
```

- [ ] **Langkah 7: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php app/Services/LMS/SOP/SopDokumenService.php app/Services/LMS/SOP/SopDocumentControlService.php
git commit -m "feat(dms): terima, simpan, dan kembalikan tanggal kadaluarsa serta jadwal review"
```

---

## Tugas 4: Syarat tanggal & pelaporan langkah Review

**Berkas:**
- Ubah: `app/Services/LMS/SOP/SopTahapVersiService.php` (`nilaiSyarat()`, `langkahVersi()`)
- Uji: `tests/Feature/LMS/SOP/SopWizardVersiTest.php`

**Memakai:** baris syarat dari Tugas 1, kolom dari Tugas 3.

- [ ] **Langkah 1: Tulis uji yang gagal**

Tambahkan ke `SopWizardVersiTest`:

```php
    /* ── Syarat tanggal masa berlaku ────────────────────────────────────── */

    public function test_terbit_ditolak_tanpa_tiga_tanggal(): void
    {
        $idVersi = $this->versiBaru();

        $kurang = $this->svc()->alurVersi($idVersi)['Langkah'];
        $syarat = collect($kurang)->firstWhere('Kelompok', 'TERBIT');

        $this->assertNotNull($syarat);

        /* Versi baru belum punya satu pun tanggal, jadi ketiganya harus
           disebut sebagai yang kurang begitu versinya berdiri di Pemberkasan. */
        $nilai = (new \ReflectionMethod($this->svc(), 'nilaiSyarat'));
        $nilai->setAccessible(true);
        $hasil = $nilai->invoke($this->svc(), $idVersi);

        $this->assertFalse($hasil['TANGGAL_BERLAKU']);
        $this->assertFalse($hasil['TANGGAL_KADALUARSA']);
        $this->assertFalse($hasil['TANGGAL_REVIEW_BERIKUTNYA']);
    }

    public function test_tiga_tanggal_terisi_memenuhi_syarat(): void
    {
        $idVersi = $this->versiBaru();

        /* Cukup mengisi Tanggal_Berlaku: dua sisanya dilengkapi baku oleh
           SopMasaBerlakuService di jalur simpan. Itu justru yang diuji. */
        $this->dok()->ubahVersi($idVersi, ['Tanggal_Berlaku' => '2026-03-15'], 1);

        $nilai = (new \ReflectionMethod($this->svc(), 'nilaiSyarat'));
        $nilai->setAccessible(true);
        $hasil = $nilai->invoke($this->svc(), $idVersi);

        $this->assertTrue($hasil['TANGGAL_BERLAKU']);
        $this->assertTrue($hasil['TANGGAL_KADALUARSA']);
        $this->assertTrue($hasil['TANGGAL_REVIEW_BERIKUTNYA']);
    }

    /* ── Review kosong itu SAH ──────────────────────────────────────────── */

    /**
     * REGRESI. Versi yang melewati Review tanpa catatan pernah selamanya
     * berbunyi "0 putaran review" dengan Lengkap=false, dan admin membacanya
     * sebagai pekerjaan yang tertinggal. Padahal DML-2026-042 sudah mencabut
     * syarat CATATAN_REVIEW justru karena review kosong itu sah — perpanjangan
     * dan review yang mulus memang tak meninggalkan catatan.
     */
    public function test_review_terlewati_tanpa_catatan_terhitung_selesai(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindahTahap($idVersi, 'REVIEW', 1);
        $this->svc()->pindahTahap($idVersi, 'APPROVAL', 1);

        $review = collect($this->svc()->alurVersi($idVersi)['Langkah'])
            ->firstWhere('Kelompok', 'REVIEW');

        $this->assertTrue($review['Lengkap']);
        $this->assertSame('tanpa catatan review', $review['Ringkasan']);
    }
```

> Sebelum menulis, konfirmasi nama metode perpindahan tahap di
> `SopTahapVersiService` (`pindahTahap` atau nama lain) dan sesuaikan; jangan
> menebak.

- [ ] **Langkah 2: Jalankan, pastikan gagal**

```bash
unset DATABASE_URL && php artisan test --filter=SopWizardVersiTest
```

Harapan: tiga uji baru GAGAL — kunci `TANGGAL_BERLAKU` tidak ada; Ringkasan
berbunyi `'0 putaran review'`.

- [ ] **Langkah 3: Tambah tiga nilai syarat**

Di `nilaiSyarat()`, tambahkan ketiga kolom ke `first([...])`:

```php
            ->first(['v.Id_Master_Konten', 'v.Id_Master_Ujian', 'v.Disahkan_Oleh', 'd.Tipe',
                'v.Tanggal_Berlaku', 'v.Tanggal_Kadaluarsa', 'v.Tanggal_Review_Berikutnya']);
```

dan ke nilai yang dikembalikan:

```php
        return $this->cacheNilai[$idVersi] = [
            'BERKAS' => $adaIsi,
            'REALISASI_PENGESAH' => $adaPengesah,
            'CATATAN_REVIEW' => $adaReview,
            /* Tiga tanggal masa berlaku. Gerbangnya di FILING_REGISTERED —
               saat MENINGGALKAN Pemberkasan menuju terbit, bukan saat simpan.
               Draft boleh setengah jadi; yang tidak boleh setengah jadi adalah
               dokumen yang mulai mengikat orang. */
            'TANGGAL_BERLAKU' => filled($v->Tanggal_Berlaku ?? null),
            'TANGGAL_KADALUARSA' => filled($v->Tanggal_Kadaluarsa ?? null),
            'TANGGAL_REVIEW_BERIKUTNYA' => filled($v->Tanggal_Review_Berikutnya ?? null),
        ];
```

- [ ] **Langkah 4: Perbaiki pelaporan langkah Review**

Di `langkahVersi()`, ganti cabang `REVIEW` pada `Ringkasan`:

```php
                'REVIEW' => $review > 0
                    ? $review.' putaran review'
                    : (($langkahKini ?? 0) > $langkah['Nomor'] ? 'tanpa catatan review' : 'belum ada catatan'),
```

dan pada `Lengkap`:

```php
                /* Langkah yang SUDAH DILEWATI adalah langkah selesai, apa pun
                   jumlah catatannya. Syarat CATATAN_REVIEW dicabut
                   DML-2026-042; yang tersisa cuma kalimatnya, dan "0 putaran
                   review" terbaca sebagai pekerjaan yang tertinggal. */
                'REVIEW' => $review > 0 || ($langkahKini ?? 0) > $langkah['Nomor'],
```

- [ ] **Langkah 5: Jalankan, pastikan lulus**

```bash
unset DATABASE_URL && php artisan test --filter=SopWizardVersiTest
```

- [ ] **Langkah 6: Commit**

```bash
git add app/Services/LMS/SOP/SopTahapVersiService.php tests/Feature/LMS/SOP/SopWizardVersiTest.php
git commit -m "feat(dms): syarat tanggal masa berlaku dan review kosong terhitung selesai"
```

---

## Tugas 5: Angka bulan sampai ke layar

**Berkas:**
- Ubah: `app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php`
- Uji: `tests/Feature/LMS/SOP/SopWizardHalamanTest.php`

**Menghasilkan:** prop `dokumen.Masa_Berlaku_Bulan` dan
`dokumen.Siklus_Review_Bulan` (keduanya `int`).

- [ ] **Langkah 1: Tulis uji yang gagal**

```php
    public function test_halaman_membawa_baku_masa_berlaku(): void
    {
        // Pakai pembangun dokumen yang sudah ada di berkas uji ini.
        $response = $this->aktorAdmin()->get($this->urlWizard());

        $response->assertInertia(fn ($p) => $p
            ->where('dokumen.Masa_Berlaku_Bulan', 24)
            ->where('dokumen.Siklus_Review_Bulan', 12));
    }
```

> Sesuaikan `aktorAdmin()`/`urlWizard()` dengan helper yang sudah ada di
> `SopWizardHalamanTest`; jangan membuat helper baru bila sudah tersedia.

- [ ] **Langkah 2: Jalankan, pastikan gagal**

```bash
unset DATABASE_URL && php artisan test --filter=SopWizardHalamanTest
```

- [ ] **Langkah 3: Kirim dua angka itu sebagai prop**

```php
        /* Baku masa berlaku ikut sebagai prop, bukan endpoint tersendiri.
           Layar menghitung `berlaku + N bulan` dari tanggal yang DIKETIK
           admin — aritmetika itu tak menyentuh jam browser sama sekali, jadi
           keberatan "jam klien bisa salah" yang berlaku untuk keberlakuanItem()
           tidak berlaku di sini. */
        $aturan = app(\App\Services\LMS\SOP\SopMasaBerlakuService::class)
            ->aturanDokumen($idDokumen);

        return Inertia::render('LMS/SOP/Governance/Admin/SopVersiWizard', [
            'idDokumen' => $dokumen,
            'idVersi' => $versi,
            'dokumen' => [
                'Judul' => $dok->Judul,
                'Tipe' => $dok->Tipe,
                'Nomor_SOP_Resmi' => $dok->Nomor_SOP_Resmi,
                'No_Dokumen' => $dok->No_Dokumen,
                'Masa_Berlaku_Bulan' => $aturan['Masa_Berlaku_Bulan'],
                'Siklus_Review_Bulan' => $aturan['Siklus_Review_Bulan'],
            ],
        ]);
```

- [ ] **Langkah 4: Jalankan, pastikan lulus; lalu commit**

```bash
unset DATABASE_URL && php artisan test --filter=SopWizardHalamanTest
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php tests/Feature/LMS/SOP/SopWizardHalamanTest.php
git commit -m "feat(dms): kirim baku masa berlaku sebagai prop halaman wizard"
```

---

## Tugas 6: Tiga picker dengan auto-isi yang tahu diri

**Berkas:**
- Ubah: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue`
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.test.js`

**Memakai:** prop `masaBerlakuBulan` / `siklusReviewBulan` dari Tugas 5.

**Menghasilkan:** komponen menerima dua prop `Number` baru; `modelValue` kini
memuat `Tanggal_Kadaluarsa` dan `Tanggal_Review_Berikutnya`.

- [ ] **Langkah 1: Tulis uji yang gagal**

```js
import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import LangkahPemberkasan from './LangkahPemberkasan.vue';

/**
 * Auto-isi HARUS tahu diri.
 *
 * Auto-isi yang menimpa ketikan admin adalah persis perilaku yang membuat
 * formulir ini terasa melawan pemakainya. Ketiga uji di bawah mengunci
 * batasnya: mengikuti selama belum disentuh, berhenti begitu disentuh, dan
 * bisa diminta mengikuti lagi.
 */
const pasang = (nilai = {}) => mount(LangkahPemberkasan, {
    props: {
        modelValue: {
            Tanggal_Berlaku: '', Tanggal_Kadaluarsa: '', Tanggal_Review_Berikutnya: '',
            Id_Master_Konten: null, Konten_Nama: '', ...nilai,
        },
        masaBerlakuBulan: 24,
        siklusReviewBulan: 12,
    },
    global: { stubs: { HcisCard: true, HcisBadge: true, HcisButton: true, HcisField: true,
        HcisUpload: true, HcisIcon: true, SopKontenTerpakai: true, HcisDatePicker: true } },
});

describe('LangkahPemberkasan — turunan tanggal', () => {
    it('mengisi kadaluarsa dan jadwal review saat tanggal berlaku diisi', async () => {
        const w = pasang();
        await w.vm.ubah('Tanggal_Berlaku', '2026-03-15');

        const t = w.emitted('update:modelValue').at(-1)[0];
        expect(t.Tanggal_Kadaluarsa).toBe('2028-03-15');
        expect(t.Tanggal_Review_Berikutnya).toBe('2027-03-15');
    });

    it('berhenti mengikuti setelah admin mengetik kadaluarsanya sendiri', async () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15' });
        await w.vm.ubah('Tanggal_Kadaluarsa', '2030-01-01');
        await w.vm.ubah('Tanggal_Berlaku', '2026-06-01');

        const t = w.emitted('update:modelValue').at(-1)[0];
        expect(t.Tanggal_Kadaluarsa).toBe('2030-01-01');
        // Jadwal review belum disentuh, jadi ia tetap mengikuti.
        expect(t.Tanggal_Review_Berikutnya).toBe('2027-06-01');
    });

    it('"ikuti baku lagi" mengembalikan turunannya', async () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15' });
        await w.vm.ubah('Tanggal_Kadaluarsa', '2030-01-01');
        await w.vm.ikutiBakuLagi('Tanggal_Kadaluarsa');

        const t = w.emitted('update:modelValue').at(-1)[0];
        expect(t.Tanggal_Kadaluarsa).toBe('2028-03-15');
    });
});
```

- [ ] **Langkah 2: Jalankan, pastikan gagal**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.test.js
```

- [ ] **Langkah 3: Ganti kartu "Mulai Berlaku"**

Ganti seluruh blok `HcisCard title="Mulai Berlaku"` (termasuk komentar
"Masa berlaku: HANYA tanggal mulai" di atasnya) dengan kartu tiga picker
berjudul **"Masa Berlaku & Jadwal Review"**, `icon="calendar-range"`. Tiap
turunan diberi keterangan asal-usul dan tautan *"ikuti baku lagi"* yang hanya
muncul saat field itu sudah disentuh.

- [ ] **Langkah 4: Tambah prop, data, dan metode**

```js
    props: {
        // ... prop yang sudah ada
        masaBerlakuBulan: { type: Number, default: 24 },
        siklusReviewBulan: { type: Number, default: 12 },
    },
    data() {
        /* Jejak "sudah disentuh" hidup DI SINI, bukan di form: ia bukan fakta
           tentang versi, ia fakta tentang sesi penyuntingan. Menyimpannya ke
           server berarti membuat kolom untuk mengingat gerakan tangan admin
           tiga bulan lalu. */
        return { disentuh: { Tanggal_Kadaluarsa: false, Tanggal_Review_Berikutnya: false } };
    },
    methods: {
        tambahBulan(tanggal, bulan) {
            if (!tanggal) return null;
            const d = new Date(`${tanggal}T00:00:00`);
            if (Number.isNaN(d.getTime())) return null;
            const hariAsli = d.getDate();
            d.setMonth(d.getMonth() + bulan);
            /* 31 Jan + 1 bulan meluber ke 3 Mar di JS. Dijepit ke akhir bulan
               supaya "sebulan lagi" tak pernah melompati satu bulan penuh. */
            if (d.getDate() !== hariAsli) d.setDate(0);

            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },
        ubah(kunci, v) {
            if (kunci === 'Tanggal_Kadaluarsa' || kunci === 'Tanggal_Review_Berikutnya') {
                this.disentuh[kunci] = true;
            }

            const baru = { ...this.nilai, [kunci]: v };
            if (kunci === 'Tanggal_Berlaku') Object.assign(baru, this.turunan(v));

            this.$emit('update:modelValue', baru);
        },
        turunan(berlaku) {
            const out = {};
            if (!this.disentuh.Tanggal_Kadaluarsa) {
                out.Tanggal_Kadaluarsa = this.tambahBulan(berlaku, this.masaBerlakuBulan);
            }
            if (!this.disentuh.Tanggal_Review_Berikutnya) {
                out.Tanggal_Review_Berikutnya = this.tambahBulan(berlaku, this.siklusReviewBulan);
            }

            return out;
        },
        ikutiBakuLagi(kunci) {
            this.disentuh[kunci] = false;
            const bulan = kunci === 'Tanggal_Kadaluarsa' ? this.masaBerlakuBulan : this.siklusReviewBulan;
            this.$emit('update:modelValue', {
                ...this.nilai,
                [kunci]: this.tambahBulan(this.nilai.Tanggal_Berlaku, bulan),
            });
        },
    },
```

- [ ] **Langkah 5: Teruskan prop dari cangkang**

Di `SopVersiWizard.vue`, pada `<LangkahPemberkasan>`:

```html
                    :masa-berlaku-bulan="dokumen.Masa_Berlaku_Bulan || 24"
                    :siklus-review-bulan="dokumen.Siklus_Review_Bulan || 12"
```

dan tambahkan dua kunci ke `kosong()`:

```js
                Tanggal_Kadaluarsa: '',
                Tanggal_Review_Berikutnya: '',
```

serta ke pemetaan di `muatVersi()`:

```js
                    Tanggal_Kadaluarsa: v.Tanggal_Kadaluarsa || '',
                    Tanggal_Review_Berikutnya: v.Tanggal_Review_Berikutnya || '',
```

- [ ] **Langkah 6: Jalankan, pastikan lulus; commit**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.test.js
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.test.js resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue
git commit -m "feat(dms): tiga tanggal masa berlaku di langkah Pemberkasan"
```

---

## Tugas 7: Satu klik menyimpan versi DAN catatan review

**Berkas:**
- Ubah: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue`
- Ubah: `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue`
- Ubah: `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js`

**Memakai:** endpoint `PUT /versi/{id}/review` yang sudah ada — bentuk
muatannya **tidak berubah**.

**Menghasilkan:** `LangkahReview` menerima `baris` (`Array`) + `memuat`
(`Boolean`) dan meng-emit `update:baris`; ia tak lagi memanggil `axios` sendiri.

- [ ] **Langkah 1: Tulis uji yang gagal**

```js
    it('satu klik Simpan & Lanjut menyimpan versi lalu catatan review', async () => {
        // aksiUtama = SIMPAN_LANJUT, ada satu baris review yang belum tersimpan
        const w = pasangWizard({ aksiUtama: { Jenis: 'SIMPAN_LANJUT', Ke_Tahap: 'APPROVAL', Label: 'Simpan & Lanjut ke Pengesahan', Syarat_Kurang: [] } });
        w.vm.review = [{ kunci: 'baru-1', Review_Ke: 1, Tanggal_Review: '2026-04-01', Hasil_Review: 'Perbaikan redaksi', Lampiran: [] }];

        await w.vm.jalankanUtama();

        const url = axios.put.mock.calls.map((c) => c[0]);
        expect(url.filter((u) => /\/versi\/[^/]+$/.test(u))).toHaveLength(1);
        expect(url.filter((u) => /\/review$/.test(u))).toHaveLength(1);
        expect(url.findIndex((u) => /\/review$/.test(u)))
            .toBeGreaterThan(url.findIndex((u) => /\/versi\/[^/]+$/.test(u)));
    });

    it('tidak mengirim review bila tak ada perubahan', async () => {
        const w = pasangWizard();
        await w.vm.simpan();

        expect(axios.put.mock.calls.map((c) => c[0]).filter((u) => /\/review$/.test(u))).toHaveLength(0);
    });
```

> Sesuaikan `pasangWizard()` dengan helper yang sudah ada di
> `SopVersiWizard.test.js`.

- [ ] **Langkah 2: Jalankan, pastikan gagal**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js
```

- [ ] **Langkah 3: Jadikan `LangkahReview` terkendali**

Hapus `data().baris`, `muat()`, `simpan()`, `untuk`, dan impor `axios` dari
`LangkahReview.vue`. Ganti dengan:

```js
    props: {
        idVersi: { type: String, default: null },
        terkunci: { type: Boolean, default: false },
        baris: { type: Array, default: () => [] },
        memuat: { type: Boolean, default: false },
        menyimpan: { type: Boolean, default: false },
        galat: { type: String, default: '' },
    },
    emits: ['update:baris', 'simpan-sementara'],
```

Seluruh mutasi (`tambah`, `hapus`, `hapusLampiran`, `pilihLampiran`) bekerja di
atas salinan lalu meng-emit `update:baris`. Logika baca berkas, batas 2 MB, dan
batas 10 lampiran **tetap di sini** — itu urusan tampilan.

Tombol kaki menjadi checkpoint:

```html
                    <HcisButton
                        v-if="adaPerubahan"
                        variant="ghost"
                        size="sm"
                        leading-icon="bookmark"
                        :loading="menyimpan"
                        :disabled="!idVersi || terkunci"
                        title="Kunci progres sekarang, lanjutkan nanti"
                        label="Simpan Sementara"
                        @click="$emit('simpan-sementara')"
                    />
```

- [ ] **Langkah 4: Pindahkan state & pemuatan ke cangkang**

Di `SopVersiWizard.vue` — `data()` bertambah:

```js
            review: [],
            reviewAsli: '[]',
            reviewMemuat: false,
            reviewGalat: '',
```

`computed` bertambah:

```js
        reviewKotor() {
            return JSON.stringify(this.muatanReview()) !== this.reviewAsli;
        },
        kotor() {
            return this.berubah.length > 0 || this.reviewKotor;
        },
```

Metode `muatReview(idVersi)` memindahkan isi `LangkahReview.muat()` apa adanya,
dan `simpanReview()` memindahkan isi `LangkahReview.simpan()` — **bentuk
muatannya tidak diubah sedikit pun**, termasuk pengiriman balik lampiran
tersimpan sebagai `Path_Berkas`.

Template:

```html
                <LangkahReview
                    v-else-if="alur.kelompok === 'REVIEW'"
                    v-model:baris="review"
                    :id-versi="idVersiKini"
                    :terkunci="langkahTerkunci"
                    :memuat="reviewMemuat"
                    :menyimpan="menyimpan"
                    :galat="reviewGalat"
                    @simpan-sementara="simpanReview({ diam: false })"
                />
```

- [ ] **Langkah 5: Sisipkan review ke `simpan()`**

Di `simpan()`, tepat sebelum tiap `return true`:

```js
                    /* SATU KLIK, DUA KIRIMAN. Muatannya tetap terpisah —
                       lampiran gambar tak boleh ikut terangkut di setiap
                       penyimpanan kecil. Yang berubah cuma siapa yang
                       memicunya. */
                    if (this.reviewKotor) await this.simpanReview({ diam: true });
```

Pada jalur versi baru, panggilan itu diletakkan **sesudah** `this.idVersiKini`
terisi — endpoint review menuntut id yang sudah ada.

- [ ] **Langkah 6: Jalankan, pastikan lulus; commit**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js
git commit -m "feat(dms): simpan dan lanjut sekaligus menyimpan catatan review"
```

---

## Tugas 8: Dokumentasi & gerbang verifikasi

**Berkas:**
- Ubah: `docs/architecture/feature-mapping-lms-sop-master-document.md`
- Ubah: `plans/sop/2026-08-27-masa-berlaku-versi-design.md` (status)

- [ ] **Langkah 1: Balikkan catatan §"`Tanggal_Kadaluarsa` berhenti diminta"**

Jangan hapus bagian itu — tambahkan sub-bagian bertanggal 2026-08-27 yang
menyebut apa yang berubah dan **kenapa alasan 2026-08-15 tak lagi berlaku**
(slice B/C memberi kolom itu aturan yang bertindak). Riwayat keputusan yang
dihapus adalah riwayat yang akan diulang.

- [ ] **Langkah 2: Jalankan gerbang struktural**

```bash
composer dump-autoload -o --strict-psr
php scripts/verify-references.php --max=0
php scripts/verify-frontend-imports.php
php scripts/verify-inertia-pages.php
```

Jangan menaikkan `--max` untuk meloloskan temuan baru.

- [ ] **Langkah 3: Uji terfokus**

```bash
unset DATABASE_URL && php artisan test --filter='SopMasaBerlaku|SopWizardVersi|SopWizardHalaman|SopTahapVersi|SopDokumenKeberlakuan'
npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/
```

`SopDokumenKeberlakuanTest` **harus tetap hijau** — slice A tidak mengubah
aturan keberlakuan. Merah di sana berarti ada yang tak sengaja menyentuh
`keberlakuanItem()`.

- [ ] **Langkah 4: Build**

```bash
npm run build
```

- [ ] **Langkah 5: Commit**

```bash
git add docs/architecture/feature-mapping-lms-sop-master-document.md plans/sop/2026-08-27-masa-berlaku-versi-design.md
git commit -m "docs(dms): catat pembalikan keputusan masa berlaku 2026-08-15"
```

---

## Setelah slice A

Slice B, C, dan D sudah tertulis di spec §7 sebagai rencana yang tinggal
dieksekusi. Masing-masing mendapat siklus spec → plan → implementasi sendiri.
Urutannya terkunci oleh data: ketiganya membaca kolom yang lahir di slice A.
