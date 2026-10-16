# API B2B Permit Coklat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplikasi mitra B2B dapat mengajukan permit COKLAT lewat API terjaga, verifikator menyetujuinya di meja yang sudah ada, dan mitra memantau keputusannya lewat polling.

**Architecture:** Permit B2B adalah pengajuan permit sungguhan di `N_EMI_VISITOR_Transaksi_Permit`, ditandai `Sumber_Pengajuan='API'`. Penanda itu satu-satunya dasar untuk melewati tiga mesin permit karyawan (kuota, foto wajib, checklist) dan mengganti sumber durasi. Tidak ada baris registrasi tamu yang dibuat, sehingga jendela jam wajib dan berbatas — hanya jam selesai yang bisa mematikan permit itu.

**Tech Stack:** Laravel (query builder `DB::table()`, bukan Eloquent — modul Visitor seluruhnya begitu), SQL Server (`Web_HRIS`), middleware `hris.api.guard` (HMAC-SHA512 + nonce + whitelist), PHPUnit (`Tests\TestCase`, `DatabaseTransactions`, menembak database development), Vue 3 Options API + Vitest.

**Spec:** `docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md`

## Global Constraints

- **Change ID:** `DDL-2026-079-visitor-permit-api-b2b`. DDL di `database/ddl/DDL-2026-079-visitor-permit-api-b2b/` (`forward.sql` + `drop.sql`), DML di `database/dml/DDL-2026-079-visitor-permit-api-b2b/data-change.sql`, entri wajib di `database/ddl/status.json`. DDL dan DML tidak dicampur.
- **Database:** hanya `Web_HRIS` (development). Agent tidak pernah mengeksekusi ke production. `drop.sql` dibuat tetapi tidak pernah dijalankan agent. Tidak ada migration Laravel baru — penjaga CI `Perubahan database` menolaknya.
- **Prasyarat:** `DDL-2026-077-visitor-permit-coklat` sudah `DEV_EXECUTED` (kategori COKLAT, `Sumber_Permit`, kolom waktu di proyeksi).
- **Pembungkus respons API pihak ketiga:** `{sukses, message, result}` — kuncinya **`sukses`**, bukan `success`. Ini yang dipakai `KalenderOperasionalApiController` dan ditegaskan uji kontraknya.
- **Semua pengecualian dicabang dari `Sumber_Pengajuan='API'`**, tidak pernah dari kategori permit. Kategori COKLAT juga dipakai permit tamu HCIS yang bukan dari API.
- **Setiap penyaring `Sumber_Pengajuan` wajib `ISNULL(Sumber_Pengajuan,'HCIS')`** — kolomnya nullable, dan perbandingan terhadap `NULL` menghasilkan `UNKNOWN` sehingga baris lama lolos diam-diam.
- **`UPDATE` tidak boleh memakai alias tabel.** SQL Server menolak `UPDATE t AS p` dengan `Incorrect syntax near the keyword 'as'`; korelasi subquery memakai nama tabel penuh.
- **Fixture uji wajib memakai `Kode_Karyawan` nyata** yang ada di tabel `Karyawan` (mis. `A1`, `A2`, `B7`, `C9` di dev). Kode karangan membuat jalur proyeksi gagal karena kolom nama yang `NOT NULL` di-resolve lewat JOIN.
- **`Url_Route` di `N_HRIS_Api_Endpoint` wajib berisi POLA rute** termasuk `{no_transaksi}`, bukan nomor konkret — whitelist dicocokkan ke `route()->uri()`. `Id_Grup_Fitur` untuk VISITOR = `4`.
- **Endpoint baru wajib masuk `resources/openapi/hcis-third-party.yaml`**, kalau tidak uji dokumentasi merah.
- **Penjaga permit karyawan tidak boleh melemah.** `resolveDuration()` tetap menolak satuan selain `MONTH`; kuota, foto, dan checklist tetap berlaku penuh untuk `Sumber_Pengajuan='HCIS'`.
- **Kapan tes dijalankan:** user proyek ini meminta kode dan tes ditulis sampai selesai lebih dulu, dan suite baru dijalankan setelah ACC. Konfirmasikan di awal eksekusi apakah aturan itu berlaku; kalau ya, langkah "Run test" tetap ditulis tetapi dieksekusi berkelompok setelah ACC.

---

### Task 1: Skema — penanda sumber, jendela, identitas tamu

**Files:**
- Create: `database/ddl/DDL-2026-079-visitor-permit-api-b2b/forward.sql`
- Create: `database/ddl/DDL-2026-079-visitor-permit-api-b2b/drop.sql`
- Create: `database/dml/DDL-2026-079-visitor-permit-api-b2b/data-change.sql`
- Create: `docs/changes/DDL-2026-079-visitor-permit-api-b2b/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: kolom dari `DDL-2026-077` (`Sumber_Permit` di proyeksi).
- Produces: `N_HRIS_Api_Aplikasi.Kode_Karyawan_PIC`; `N_EMI_VISITOR_Transaksi_Permit.{Sumber_Pengajuan, Id_Api_Aplikasi, Referensi_Mitra}`; `N_EMI_VISITOR_Transaksi_Permit_Detail.{Waktu_Mulai_Berlaku, Waktu_Berakhir, Nama_Tamu, Instansi_Tamu, No_Identitas_Tamu, Jenis_Identitas_Tamu}`; `N_VISITOR_Permit_Efektif.{Nama_Pemegang, Instansi_Pemegang}`; indeks unik idempotensi bercakup `Id_Api_Aplikasi`.

- [ ] **Step 1: Laporkan antrean DDL yang menggantung**

`AGENTS.md` mewajibkannya sebelum membuat change baru:

```bash
php -r '$j=json_decode(file_get_contents("database/ddl/status.json"),true); foreach(($j["changes"]??$j) as $k=>$v){ $s=is_array($v)?($v["sql_tracker"]["status"]??"?"):$v; $id=is_array($v)?($v["change_id"]??$k):$k; if(!in_array($s,["TRACKER_RECORDED","CANCELLED"],true)) echo "$id => $s\n"; }'
```

- [ ] **Step 2: Tulis `forward.sql`**

```sql
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-079-visitor-permit-api-b2b
--
-- Permit COKLAT yang diajukan aplikasi mitra B2B lewat API. Ia adalah
-- pengajuan permit sungguhan, bukan bentuk data baru: yang membedakannya dari
-- pengajuan karyawan hanya penanda Sumber_Pengajuan, dan dari penanda itulah
-- tiga mesin permit karyawan (kuota, foto wajib, checklist) dilewati.
--
-- TIDAK ada baris registrasi tamu yang dibuat untuk permit ini. Konsekuensinya
-- satu-satunya yang bisa mematikannya adalah Waktu_Berakhir, sehingga jendela
-- jam wajib diisi dan dibatasi panjangnya di service.
--
-- Semua kolom NULL: baris lama tidak punya nilainya dan tidak boleh dipaksa
-- mengarang satu. Kewajiban mengisi ditegakkan di validasi pengajuan baru.
--
-- Seluruh pernyataan idempoten.

IF COL_LENGTH('N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC') IS NULL
ALTER TABLE N_HRIS_Api_Aplikasi ADD Kode_Karyawan_PIC varchar(20) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Sumber_Pengajuan') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Sumber_Pengajuan varchar(10) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Id_Api_Aplikasi') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Id_Api_Aplikasi int NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Referensi_Mitra') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Referensi_Mitra varchar(100) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Mulai_Berlaku') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Waktu_Mulai_Berlaku datetime2(0) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Berakhir') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Waktu_Berakhir datetime2(0) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Nama_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Nama_Tamu varchar(150) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Instansi_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Instansi_Tamu varchar(150) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'No_Identitas_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD No_Identitas_Tamu varchar(100) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Identitas_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Jenis_Identitas_Tamu varchar(20) NULL;
GO

-- Identitas pemegang WAJIB sampai ke proyeksi: itu satu-satunya tabel yang
-- dibaca aplikasi gerbang. Tanpa kolom ini satpam melihat izin atas nama
-- karyawan SHE dan tidak punya petunjuk siapa yang sebenarnya datang.
IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Nama_Pemegang') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Nama_Pemegang varchar(150) NULL;
GO

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Instansi_Pemegang') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Instansi_Pemegang varchar(150) NULL;
GO

-- Keunikan idempotensi diperluas mencakup aplikasi penerbit. Sebelumnya kunci
-- hanya unik per Kode_Karyawan_Pemohon; karena semua permit satu mitra memakai
-- satu PIC, dua mitra yang berbagi PIC bisa bertabrakan kuncinya dan mitra
-- kedua menerima nomor permit milik mitra pertama. Itu kebocoran data antar
-- pelanggan yang tidak menyalakan galat apa pun.
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
DROP INDEX UX_VISITOR_Permit_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Permit;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
    ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Id_Api_Aplikasi, Idempotency_Key)
    WHERE Idempotency_Key IS NOT NULL;
GO
```

- [ ] **Step 3: Tulis `drop.sql`**

```sql
-- Change ID: DDL-2026-079-visitor-permit-api-b2b
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Menjatuhkan kolom di bawah menghapus jendela dan identitas seluruh permit
-- B2B yang terlanjur terbit, dan mengembalikan indeks idempotensi ke bentuk
-- yang bisa ditabrak antar mitra.

DROP INDEX IF EXISTS UX_VISITOR_Permit_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Permit;
GO
CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
    ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Idempotency_Key)
    WHERE Idempotency_Key IS NOT NULL;
GO
ALTER TABLE N_VISITOR_Permit_Efektif DROP COLUMN IF EXISTS Instansi_Pemegang, Nama_Pemegang;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail DROP COLUMN IF EXISTS Jenis_Identitas_Tamu, No_Identitas_Tamu, Instansi_Tamu, Nama_Tamu, Waktu_Berakhir, Waktu_Mulai_Berlaku;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit DROP COLUMN IF EXISTS Referensi_Mitra, Id_Api_Aplikasi, Sumber_Pengajuan;
GO
ALTER TABLE N_HRIS_Api_Aplikasi DROP COLUMN IF EXISTS Kode_Karyawan_PIC;
GO
```

- [ ] **Step 4: Tulis `data-change.sql` (backfill saja, belum ada mitra)**

```sql
-- Change ID: DDL-2026-079-visitor-permit-api-b2b (DML)
-- Target: Web_HRIS (development).
--
-- Backfill penanda sumber pada pengajuan permit yang sudah ada. Penyaring di
-- kode memang sudah dilindungi ISNULL(Sumber_Pengajuan,'HCIS'), tetapi laporan
-- dan layar yang memisah pengajuan mitra dari pengajuan karyawan akan salah
-- tanpa backfill ini.
--
-- Pendaftaran aplikasi mitra TIDAK ada di sini: kunci publik/rahasia dan PIC-nya
-- adalah keputusan pemilik repo, bukan sesuatu yang boleh dikarang. Lihat
-- docs/changes/DDL-2026-079-visitor-permit-api-b2b/README.md bagian "Pekerjaan user".
--
-- Idempoten.

UPDATE N_EMI_VISITOR_Transaksi_Permit SET Sumber_Pengajuan = 'HCIS' WHERE Sumber_Pengajuan IS NULL;
GO
```

- [ ] **Step 5: Jalankan forward.sql lalu data-change.sql ke development**

Tampilkan SQL persisnya dan sebutkan objek sasaran sebelum eksekusi. Tidak ada `TRUNCATE`; satu `DROP INDEX` yang langsung dibuat ulang di batch berikutnya (perluasan kunci, bukan penghapusan).

- [ ] **Step 6: Verifikasi dari katalog sistem, bukan dari keluaran skrip**

```bash
php artisan tinker --execute="
\$k=DB::select(\"SELECT TABLE_NAME t, COLUMN_NAME c FROM INFORMATION_SCHEMA.COLUMNS WHERE (TABLE_NAME='N_HRIS_Api_Aplikasi' AND COLUMN_NAME='Kode_Karyawan_PIC') OR (TABLE_NAME='N_EMI_VISITOR_Transaksi_Permit' AND COLUMN_NAME IN ('Sumber_Pengajuan','Id_Api_Aplikasi','Referensi_Mitra')) OR (TABLE_NAME='N_EMI_VISITOR_Transaksi_Permit_Detail' AND COLUMN_NAME IN ('Waktu_Mulai_Berlaku','Waktu_Berakhir','Nama_Tamu','Instansi_Tamu','No_Identitas_Tamu','Jenis_Identitas_Tamu')) OR (TABLE_NAME='N_VISITOR_Permit_Efektif' AND COLUMN_NAME IN ('Nama_Pemegang','Instansi_Pemegang'))\");
echo 'kolom: '.count(\$k).' (harapan 12)'.PHP_EOL;
foreach(DB::select(\"SELECT c.name FROM sys.indexes i JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id JOIN sys.columns c ON c.object_id=i.object_id AND c.column_id=ic.column_id WHERE i.name='UX_VISITOR_Permit_Requester_Idempotency' ORDER BY ic.key_ordinal\") as \$x) echo '  kunci: '.\$x->name.PHP_EOL;
echo 'Sumber_Pengajuan NULL tersisa: '.DB::table('N_EMI_VISITOR_Transaksi_Permit')->whereNull('Sumber_Pengajuan')->count().PHP_EOL;"
```

Harapan: `kolom: 12`; kunci indeks berisi `Kode_Karyawan_Pemohon`, `Id_Api_Aplikasi`, `Idempotency_Key`; `Sumber_Pengajuan NULL tersisa: 0`.

- [ ] **Step 7: Tulis `docs/changes/DDL-2026-079-visitor-permit-api-b2b/README.md`**

Isi wajib menurut `AGENTS.md`: change ID, database sasaran, deskripsi, objek terdampak, path DDL/DML/dokumentasi, checksum forward-DDL (`sha256sum`), status, pekerjaan agent, dan pekerjaan user. Bagian "Pekerjaan user" **wajib** memuat: mendaftarkan aplikasi mitra (nama, pasangan kunci, `Kode_Karyawan_PIC`), membagikan kunci rahasia lewat kanal aman, dan mencatat prosedur pencabutan aplikasi (`Flag_Aktif='N'`).

- [ ] **Step 8: Tambahkan entri ke `database/ddl/status.json`**

Ikuti bentuk entri `DDL-2026-077-visitor-permit-coklat` yang sudah ada: `change_id`, `type`, `description`, `artifact`, `dml_artifact`, `documentation`, `checksum_sha256`, `objek_terdampak`, `development`, `sql_tracker` (`TRACKER_PENDING`), `production` (`PENDING`), `verification`.

- [ ] **Step 9: Commit**

```bash
git add database/ddl/DDL-2026-079-visitor-permit-api-b2b database/dml/DDL-2026-079-visitor-permit-api-b2b docs/changes/DDL-2026-079-visitor-permit-api-b2b database/ddl/status.json
git commit -m "feat(visitor): skema pengajuan permit B2B lewat API"
```

---

### Task 2: Service aturan permit API

**Files:**
- Create: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitApiService.php`
- Test: `tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitApiServiceTest.php`

**Interfaces:**
- Consumes: kolom Task 1.
- Produces:
  - `const MAKS_JENDELA_JAM = 24`
  - `const MAKS_PERMIT_AKTIF_PER_APLIKASI = 50`
  - `picAplikasi(int $idAplikasi): string` — melempar `\DomainException` bila kosong
  - `permitAktif(int $idAplikasi): int`
  - `melampauiBatas(int $idAplikasi): bool`
  - `jendelaSah(string $mulai, string $selesai): bool`

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aturan yang hanya berlaku bagi pengajuan permit dari aplikasi mitra.
 *
 * Dua di antaranya menggantikan mesin permit karyawan yang tidak masuk akal di
 * sini: kuota per karyawan (semua permit satu mitra memakai satu PIC, jadi
 * permit ketiga akan ditolak "jatah penuh") dan durasi bulanan.
 */
class VisitorPermitApiServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_pic_diambil_dari_pendaftaran_aplikasi(): void
    {
        $id = $this->buatAplikasi(self::PIC);

        $this->assertSame(self::PIC, app(VisitorPermitApiService::class)->picAplikasi($id));
    }

    public function test_aplikasi_tanpa_pic_ditolak_keras(): void
    {
        // Tanpa PIC, proyeksi tidak bisa mengisi Nama_Pemohon yang NOT NULL —
        // dan gagalnya akan muncul jauh di hilir sebagai galat sisipan.
        $id = $this->buatAplikasi(null);

        $this->expectException(\DomainException::class);
        app(VisitorPermitApiService::class)->picAplikasi($id);
    }

    public function test_jendela_lebih_panjang_dari_batas_ditolak(): void
    {
        $svc = app(VisitorPermitApiService::class);
        $jam = VisitorPermitApiService::MAKS_JENDELA_JAM;

        $this->assertTrue($svc->jendelaSah('2026-09-10 08:00:00', '2026-09-10 09:00:00'));
        $this->assertTrue($svc->jendelaSah('2026-09-10 08:00:00', '2026-09-11 08:00:00'));
        $this->assertFalse(
            $svc->jendelaSah('2026-09-10 08:00:00', '2026-09-11 09:00:00'),
            "Jendela melebihi {$jam} jam harus ditolak.",
        );
    }

    public function test_jendela_terbalik_atau_nol_ditolak(): void
    {
        $svc = app(VisitorPermitApiService::class);

        $this->assertFalse($svc->jendelaSah('2026-09-10 17:00:00', '2026-09-10 08:00:00'));
        $this->assertFalse($svc->jendelaSah('2026-09-10 08:00:00', '2026-09-10 08:00:00'));
    }

    public function test_hitungan_permit_aktif_hanya_milik_aplikasi_itu(): void
    {
        $a = $this->buatAplikasi(self::PIC);
        $b = $this->buatAplikasi(self::PIC);

        $this->buatPermitApi($a, null);   // PENDING
        $this->buatPermitApi($a, 'Y');    // APPROVED
        $this->buatPermitApi($a, 'T');    // REJECTED — membebaskan jatah
        $this->buatPermitApi($b, null);

        $svc = app(VisitorPermitApiService::class);

        $this->assertSame(2, $svc->permitAktif($a));
        $this->assertSame(1, $svc->permitAktif($b));
    }

    private function buatAplikasi(?string $pic): int
    {
        return (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => $pic,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function buatPermitApi(int $idAplikasi, ?string $flagApproval): string
    {
        $nomor = 'PMT-API-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $flagApproval,
            'Sumber_Pengajuan' => 'API',
            'Id_Api_Aplikasi' => $idAplikasi,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'API-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Mitra',
            'Instansi_Tamu' => 'PT Mitra',
            'Waktu_Mulai_Berlaku' => now()->format('Y-m-d H:i:s'),
            'Waktu_Berakhir' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        return $nomor;
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitApiServiceTest.php`
Expected: FAIL — `Class "App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService" not found`.

- [ ] **Step 3: Tulis service-nya**

```php
<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aturan yang berlaku HANYA bagi pengajuan permit dari aplikasi mitra B2B.
 *
 * Dua batas di bawah sengaja konstanta, bukan kolom per aplikasi: satu tempat
 * diubah, dan tidak ada keputusan yang harus diambil ulang tiap mitra baru.
 */
class VisitorPermitApiService
{
    /**
     * Panjang maksimum jendela berlaku.
     *
     * Permit API tidak punya baris tamu, sehingga tidak ada check-out yang bisa
     * memutusnya lebih awal — satu-satunya yang mematikannya adalah jam
     * selesai. Batas ini yang menjaga izin salah-terbit tidak hidup berhari-hari.
     */
    public const MAKS_JENDELA_JAM = 24;

    /** Pengganti kuota per karyawan, yang tidak berlaku karena satu mitra satu PIC. */
    public const MAKS_PERMIT_AKTIF_PER_APLIKASI = 50;

    /** Status yang masih menahan jatah — sejalan dengan VisitorPermitQuotaService. */
    private const FLAG_AKTIF = [null, 'Y'];

    public function picAplikasi(int $idAplikasi): string
    {
        $pic = trim((string) DB::table('N_HRIS_Api_Aplikasi')
            ->where('Id_Api_Aplikasi', $idAplikasi)
            ->value('Kode_Karyawan_PIC'));

        if ($pic === '') {
            throw new \DomainException(
                "Aplikasi API {$idAplikasi} belum punya Kode_Karyawan_PIC. "
                .'Permit tidak bisa diterbitkan tanpa penanggung jawab.'
            );
        }

        return $pic;
    }

    public function permitAktif(int $idAplikasi): int
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Id_Api_Aplikasi', $idAplikasi)
            ->where('Flag_Aktif', 'Y')
            ->where(function ($q) {
                // PENDING (NULL) dan APPROVED ('Y') menahan jatah; REJECTED ('T')
                // membebaskannya. whereIn tidak bisa dipakai karena NULL tidak
                // pernah cocok dengan IN.
                $q->whereNull('Flag_Approval')->orWhere('Flag_Approval', 'Y');
            })
            ->count();
    }

    public function melampauiBatas(int $idAplikasi): bool
    {
        return $this->permitAktif($idAplikasi) >= self::MAKS_PERMIT_AKTIF_PER_APLIKASI;
    }

    public function jendelaSah(string $mulai, string $selesai): bool
    {
        try {
            $awal = Carbon::parse($mulai);
            $akhir = Carbon::parse($selesai);
        } catch (\Throwable) {
            return false;
        }

        if ($akhir <= $awal) {
            return false;
        }

        return $awal->diffInMinutes($akhir) <= self::MAKS_JENDELA_JAM * 60;
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitApiServiceTest.php`
Expected: PASS (5 tes).

- [ ] **Step 5: Commit**

```bash
git add app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitApiService.php tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitApiServiceTest.php
git commit -m "feat(visitor): aturan pengajuan permit dari aplikasi mitra"
```

---

### Task 3: Endpoint POST — ajukan permit

**Files:**
- Create: `app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php`
- Create: `app/Http/Requests/VISITOR/Permit/VehiclePermit/AjukanPermitApiRequest.php`
- Create: `routes/VISITOR/Permit/VehiclePermit/PermitTamuApi.php`
- Modify: `routes/api.php` (tambah `require`)
- Modify: `app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php` (`store()` menulis `Sumber_Pengajuan='HCIS'`)
- Test: `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiAjukanTest.php`

**Interfaces:**
- Consumes: `VisitorPermitApiService` (Task 2); `VisitorApprovalPolicyService::createFlow(string $module, string $noTransaksi, string $kodeKaryawan): void`.
- Produces: `POST /api/v1/hcis/visitor/permit-tamu` → `201 {sukses, message, result:{no_transaksi, status, waktu_mulai, waktu_selesai}}`; header `Idempotency-Key` wajib; `Id_Api_Aplikasi` diambil dari `$request->attributes->get('hris_api_app_id')` yang di-inject `HrisApiGuard`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use App\Http\Middleware\HrisApiGuard;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kontrak endpoint pengajuan permit mitra.
 *
 * Guard sengaja dilewati di sini — keamanannya diuji terpisah
 * (HrisApiGuardJalurBertandaTanganTest dan Task 8). Yang ditagih berkas ini
 * adalah kontraknya DAN ketiga pengecualian yang membedakannya dari pengajuan
 * karyawan: tanpa foto, tanpa checklist, tanpa kuota per karyawan.
 */
class PermitTamuApiAjukanTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    private const URL = '/api/v1/hcis/visitor/permit-tamu';

    private int $idAplikasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HrisApiGuard::class);
        $this->idAplikasi = $this->buatAplikasi(self::PIC);
    }

    public function test_pengajuan_sah_menghasilkan_permit_pending(): void
    {
        $isi = $this->ajukan()->assertStatus(201)->json();

        $this->assertTrue($isi['sukses']);
        $this->assertSame('PENDING', $isi['result']['status']);

        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $isi['result']['no_transaksi'])->first();

        $this->assertSame('API', $baris->Sumber_Pengajuan);
        $this->assertSame($this->idAplikasi, (int) $baris->Id_Api_Aplikasi);
        $this->assertSame(self::PIC, trim((string) $baris->Kode_Karyawan_Pemohon));
        $this->assertNull($baris->Flag_Approval, 'Permit mitra tidak boleh langsung aktif.');
    }

    public function test_identitas_tamu_dan_jendela_tersimpan(): void
    {
        $no = $this->ajukan()->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $no)->first();

        $this->assertSame('Rudi Mitra', $detail->Nama_Tamu);
        $this->assertSame('PT Mitra Sejahtera', $detail->Instansi_Tamu);
        $this->assertSame('2026-09-10 08:00:00', substr((string) $detail->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-10 17:00:00', substr((string) $detail->Waktu_Berakhir, 0, 19));
    }

    public function test_tanpa_foto_tanpa_checklist_tanpa_kuota(): void
    {
        // Ketiga mesin permit karyawan tidak berlaku. Kalau salah satunya masih
        // menyala, pengajuan mitra tidak akan pernah bisa masuk.
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->where('No_Transaksi', $no)->count(),
            'Pengajuan mitra tidak boleh menuntut dokumen.',
        );
    }

    public function test_kuota_karyawan_tidak_menghalangi_pengajuan_mitra(): void
    {
        // Kuota permit karyawan maksimal 2 aktif. Semua permit mitra memakai satu
        // PIC, jadi tanpa pengecualian permit ketiga akan ditolak "jatah penuh".
        $this->ajukan()->assertStatus(201);
        $this->ajukan()->assertStatus(201);
        $this->ajukan()->assertStatus(201);
    }

    public function test_kiriman_ulang_dengan_kunci_sama_tidak_menggandakan(): void
    {
        $kunci = 'IDEM-'.Str::upper(Str::random(20));

        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');
        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertTrue($kedua->json('result.idempotent_replay'));
    }

    public function test_jendela_melebihi_batas_ditolak(): void
    {
        $jam = VisitorPermitApiService::MAKS_JENDELA_JAM;

        $this->ajukan(ubah: [
            'waktu_mulai' => '2026-09-10 08:00:00',
            'waktu_selesai' => '2026-09-12 08:00:00',
        ])->assertStatus(422);

        $this->assertGreaterThan(0, $jam);
    }

    public function test_jendela_terbalik_dan_kosong_ditolak(): void
    {
        $this->ajukan(ubah: ['waktu_selesai' => '2026-09-10 07:00:00'])->assertStatus(422);
        $this->ajukan(ubah: ['waktu_mulai' => null, 'waktu_selesai' => null])->assertStatus(422);
    }

    public function test_tanpa_header_idempotency_ditolak(): void
    {
        $this->postJson(self::URL, $this->payload())->assertStatus(422);
    }

    public function test_tamu_tanpa_plat_tetap_diterima(): void
    {
        // Konsisten dengan keputusan permit coklat: pejalan kaki tetap berpermit.
        $this->ajukan(ubah: [
            'plat_prefix' => null, 'plat_angka' => null, 'plat_suffix' => null,
            'jenis_kendaraan' => null,
        ])->assertStatus(201);
    }

    /** @param array<string,mixed> $ubah */
    private function ajukan(array $ubah = [], ?string $kunci = null): \Illuminate\Testing\TestResponse
    {
        // array_merge, bukan '+': nilai null yang sengaja dikirim (mis. plat
        // kosong) harus MENIMPA nilai baku, bukan diabaikan.
        return $this->withHeader('Idempotency-Key', $kunci ?? 'API-'.Str::upper(Str::random(20)))
            ->postJson(self::URL, array_merge($this->payload(), $ubah));
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'nama_tamu' => 'Rudi Mitra',
            'instansi' => 'PT Mitra Sejahtera',
            'keperluan' => 'Pengiriman material',
            'jenis_kendaraan' => 'Mobil',
            'plat_prefix' => 'BG',
            'plat_angka' => '1234',
            'plat_suffix' => 'MT',
            'waktu_mulai' => '2026-09-10 08:00:00',
            'waktu_selesai' => '2026-09-10 17:00:00',
            'referensi_mitra' => 'GUEST-'.Str::random(6),
        ];
    }

    private function buatAplikasi(string $pic): int
    {
        $id = (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => $pic,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);

        // Guard dilewati, jadi atribut yang biasanya di-inject-nya disuntikkan
        // di sini — controller membacanya dari tempat yang sama.
        $this->app['request']->attributes->set('hris_api_app_id', $id);

        return $id;
    }
}
```

> **Catatan untuk pelaksana — injeksi `hris_api_app_id`.** `withoutMiddleware(HrisApiGuard::class)` ikut mematikan injeksi atribut yang biasanya dilakukan guard, dan `$this->app['request']` bukan objek request yang sama dengan yang dipakai `postJson()`. Daftarkan middleware tiruan di `setUp()`:
>
> ```php
> $idAplikasi = $this->idAplikasi;
> $this->app['router']->pushMiddlewareToGroup('api', function ($request, $next) use ($idAplikasi) {
>     $request->attributes->set('hris_api_app_id', $idAplikasi);
>     return $next($request);
> });
> ```
>
> Jangan menambahkan jalan pintas berbasis header ke kode produksi — jalan pintas uji yang hidup di produksi adalah pintu belakang.

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiAjukanTest.php`
Expected: FAIL — rute `/api/v1/hcis/visitor/permit-tamu` tidak ada (404).

- [ ] **Step 3: Tulis `AjukanPermitApiRequest`**

```php
<?php

namespace App\Http\Requests\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AjukanPermitApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_tamu' => ['required', 'string', 'max:150'],
            'instansi' => ['required', 'string', 'max:150'],
            'no_identitas' => ['nullable', 'string', 'max:100'],
            'jenis_identitas' => ['nullable', 'string', 'max:20'],
            'jenis_kendaraan' => ['nullable', 'string', 'max:50'],
            'plat_prefix' => ['nullable', 'string', 'max:5'],
            'plat_angka' => ['nullable', 'string', 'max:10'],
            'plat_suffix' => ['nullable', 'string', 'max:10'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date', 'after:waktu_mulai'],
            'keperluan' => ['required', 'string', 'max:250'],
            'referensi_mitra' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $kunci = trim((string) $this->header('Idempotency-Key'));

            if ($kunci === '' || strlen($kunci) > 100) {
                $v->errors()->add('Idempotency-Key', 'Header Idempotency-Key wajib diisi dan maksimal 100 karakter.');
            }

            // Batas panjang jendela hidup di service, bukan di aturan validasi,
            // supaya angkanya punya satu tempat dan satu penjelasan.
            if ($this->filled('waktu_mulai') && $this->filled('waktu_selesai')
                && ! app(VisitorPermitApiService::class)->jendelaSah($this->input('waktu_mulai'), $this->input('waktu_selesai'))) {
                $v->errors()->add(
                    'waktu_selesai',
                    'Jendela berlaku maksimal '.VisitorPermitApiService::MAKS_JENDELA_JAM.' jam dan harus berakhir setelah waktu mulai.',
                );
            }
        });
    }
}
```

- [ ] **Step 4: Tulis controller-nya (metode `ajukan`)**

```php
<?php

namespace App\Http\Controllers\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Permit\VehiclePermit\AjukanPermitApiRequest;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint permit COKLAT untuk aplikasi mitra B2B.
 *
 * Ini endpoint TULIS pertama di lapisan N_HRIS_Api_*; seluruh konsumen
 * sebelumnya hanya membaca. Karena itu tiga hal dijaga ketat di sini:
 * idempotensi (kiriman ulang tidak melahirkan izin kedua), isolasi antar mitra
 * (Id_Api_Aplikasi menyaring setiap pembacaan), dan pertanggungjawaban (PIC
 * diambil dari pendaftaran aplikasi, tidak pernah dari payload pemanggil).
 */
class PermitTamuApiController extends Controller
{
    public function __construct(
        private readonly VisitorPermitApiService $permitApi,
        private readonly VisitorApprovalPolicyService $visitorApprovalPolicy,
    ) {}

    public function ajukan(AjukanPermitApiRequest $request): JsonResponse
    {
        $idAplikasi = (int) $request->attributes->get('hris_api_app_id');
        $kunci = trim((string) $request->header('Idempotency-Key'));

        try {
            $pic = $this->permitApi->picAplikasi($idAplikasi);
        } catch (\DomainException $e) {
            return $this->galat($e->getMessage(), 422);
        }

        $ada = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Id_Api_Aplikasi', $idAplikasi)
            ->where('Idempotency_Key', $kunci)
            ->first();

        if ($ada) {
            return $this->sukses('Pengajuan permit sudah pernah diterima', [
                'no_transaksi' => $ada->No_Transaksi,
                'status' => 'PENDING',
                'idempotent_replay' => true,
            ], 200);
        }

        if ($this->permitApi->melampauiBatas($idAplikasi)) {
            return $this->galat(
                'Batas permit aktif aplikasi ini sudah tercapai ('
                .VisitorPermitApiService::MAKS_PERMIT_AKTIF_PER_APLIKASI.').',
                429,
            );
        }

        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Nama_Kategori', 'COKLAT')->where('Flag_Aktif', 'Y')->first();

        if (! $kategori) {
            return $this->galat('Master kategori permit COKLAT tidak tersedia.', 503);
        }

        $noTransaksi = $this->nomorTransaksi();

        DB::transaction(function () use ($request, $idAplikasi, $kunci, $pic, $kategori, $noTransaksi) {
            $headerId = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan_Pemohon' => $pic,
                'Flag_Aktif' => 'Y',
                'Flag_Approval' => null,
                'Sumber_Pengajuan' => 'API',
                'Id_Api_Aplikasi' => $idAplikasi,
                'Referensi_Mitra' => $request->input('referensi_mitra'),
                'Created_At' => now(),
                'Created_By' => $pic,
                'Updated_At' => now(),
                'Updated_By' => $pic,
                'Idempotency_Key' => $kunci,
            ]);

            DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
                'Id_Transaksi_Permit' => $headerId,
                'No_Transaksi' => $noTransaksi,
                'Plat_Nomor_Prefix' => $request->input('plat_prefix'),
                'Plat_Nomor_Angka' => $request->input('plat_angka'),
                'Plat_Nomor_Suffix' => $request->input('plat_suffix'),
                'Id_Master_Kategori_Permit' => $kategori->Id_Kategori_Permit,
                'Snapshot_Kategori_Permit' => $kategori->Nama_Kategori,
                'Id_Master_Durasi' => null,
                'Snapshot_Nama_Durasi' => null,
                'Keterangan_Pengajuan' => $request->input('keperluan'),
                'Nama_Tamu' => $request->input('nama_tamu'),
                'Instansi_Tamu' => $request->input('instansi'),
                'No_Identitas_Tamu' => $request->input('no_identitas'),
                'Jenis_Identitas_Tamu' => $request->input('jenis_identitas'),
                'Waktu_Mulai_Berlaku' => $request->input('waktu_mulai'),
                'Waktu_Berakhir' => $request->input('waktu_selesai'),
                'Tanggal_Mulai_Berlaku' => null,
                'Tanggal_Kadaluarsa' => null,
            ]);

            // Tanpa foto, tanpa checklist, tanpa kuota karyawan — ketiganya
            // mesin permit karyawan yang tidak berlaku bagi pengajuan mitra.
            $this->visitorApprovalPolicy->createFlow('permit', $noTransaksi, $pic);
        });

        return $this->sukses('Pengajuan permit diterima', [
            'no_transaksi' => $noTransaksi,
            'status' => 'PENDING',
            'waktu_mulai' => $request->input('waktu_mulai'),
            'waktu_selesai' => $request->input('waktu_selesai'),
        ], 201);
    }

    /** @param array<string,mixed> $result */
    private function sukses(string $message, array $result, int $status): JsonResponse
    {
        return response()->json(['sukses' => true, 'message' => $message, 'result' => $result], $status);
    }

    private function galat(string $message, int $status): JsonResponse
    {
        return response()->json(['sukses' => false, 'message' => $message, 'result' => null], $status);
    }

    private function nomorTransaksi(): string
    {
        // Memakai pembangkit yang sama dengan pengajuan permit HCIS supaya
        // nomornya sederet dan tidak ada penomoran kedua yang bisa bertabrakan.
        $controller = app(VehiclePermitController::class);
        $metode = new \ReflectionMethod($controller, 'generateNoTransaksi');
        $metode->setAccessible(true);

        return $metode->invoke($controller, 'PMT');
    }
}
```

> **Catatan pelaksana:** bila `generateNoTransaksi()` masih `private`, naikkan ke `protected` dan panggil lewat subkelas, atau — lebih baik — pindahkan ke service kecil yang dipakai kedua controller. Refleksi di kode produksi hanya boleh jadi langkah sementara; pilih pemindahan ke service dan perbarui `VehiclePermitController` untuk memakainya.

- [ ] **Step 5: Daftarkan rutenya**

`routes/VISITOR/Permit/VehiclePermit/PermitTamuApi.php`:

```php
<?php

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\PermitTamuApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Permit COKLAT untuk aplikasi mitra B2B (API pihak ke-3)
|--------------------------------------------------------------------------
|
| Dijaga hris.api.guard (HMAC-SHA512 + nonce + whitelist endpoint).
|
| Url_Route di N_HRIS_Api_Endpoint WAJIB berisi POLA rute — termasuk
| "{no_transaksi}", bukan nomor konkret — karena whitelist dicocokkan ke
| route()->uri().
|
| Spec: docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md
|
*/

Route::middleware('hris.api.guard')->prefix('v1/hcis/visitor/permit-tamu')->group(function () {
    Route::post('/', [PermitTamuApiController::class, 'ajukan']);
    Route::get('/{no_transaksi}', [PermitTamuApiController::class, 'status']);
    Route::post('/{no_transaksi}/batal', [PermitTamuApiController::class, 'batal']);
});
```

Di `routes/api.php`, sesudah `require __DIR__ . '/ESS/Attendance/Calendar/OperationalCalendarApi.php';`:

```php
require __DIR__ . '/VISITOR/Permit/VehiclePermit/PermitTamuApi.php';
```

- [ ] **Step 6: Tandai pengajuan HCIS**

Di `VehiclePermitController::store()`, pada `insertGetId` header, tambahkan:

```php
// Pasangan Sumber_Pengajuan='API'. Ditulis eksplisit supaya baris baru tidak
// bergantung pada backfill sekali jalan.
'Sumber_Pengajuan' => 'HCIS',
```

- [ ] **Step 7: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiAjukanTest.php`
Expected: PASS (9 tes).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/VISITOR/Permit/VehiclePermit app/Http/Requests/VISITOR/Permit/VehiclePermit routes tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiAjukanTest.php
git commit -m "feat(visitor): endpoint pengajuan permit coklat untuk mitra B2B"
```

---

### Task 4: Endpoint status dan pembatalan, dengan isolasi antar mitra

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php` (metode `status`, `batal`)
- Test: `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiStatusBatalTest.php`

**Interfaces:**
- Consumes: `VisitorPermitQueryService::permitStatusSql(): string`; `VisitorEfektifService::revokePermitEfektif(string $noTransaksi, ?string $alasan, string $olehKode): int`.
- Produces: `GET /{no_transaksi}` → `200` dengan `status`, `alasan`, `waktu_mulai`, `waktu_selesai`; `404` bila milik mitra lain. `POST /{no_transaksi}/batal` → `200`; `404` bila milik mitra lain.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use App\Http\Middleware\HrisApiGuard;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Status dan pembatalan permit mitra.
 *
 * Uji terpenting di berkas ini adalah ISOLASI: mitra A tidak boleh membaca
 * maupun membatalkan permit mitra B. Kegagalannya tidak menyalakan galat apa
 * pun — yang terjadi adalah data satu pelanggan terbaca pelanggan lain.
 * Balasannya 404, bukan 403, karena 403 mengonfirmasi bahwa nomornya ada.
 */
class PermitTamuApiStatusBatalTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_status_permit_sendiri_terbaca(): void
    {
        [$app, $no] = $this->permitMilikAplikasiBaru();

        $isi = $this->sebagai($app)->getJson($this->url($no))->assertStatus(200)->json();

        $this->assertTrue($isi['sukses']);
        $this->assertSame('PENDING', $isi['result']['status']);
        $this->assertSame($no, $isi['result']['no_transaksi']);
    }

    public function test_permit_mitra_lain_dibalas_404(): void
    {
        [, $no] = $this->permitMilikAplikasiBaru();
        $lain = $this->buatAplikasi();

        $this->sebagai($lain)->getJson($this->url($no))->assertStatus(404);
    }

    public function test_pembatalan_permit_mitra_lain_dibalas_404(): void
    {
        [, $no] = $this->permitMilikAplikasiBaru();
        $lain = $this->buatAplikasi();

        $this->sebagai($lain)->postJson($this->url($no).'/batal')->assertStatus(404);

        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'),
            'Permit mitra lain ikut tersentuh.',
        );
    }

    public function test_pembatalan_saat_pending_menutup_alurnya(): void
    {
        [$app, $no] = $this->permitMilikAplikasiBaru();

        $this->sebagai($app)->postJson($this->url($no).'/batal')->assertStatus(200);

        $this->assertSame(
            'T',
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'),
        );
    }

    public function test_pembatalan_saat_approved_mencabut_izin_efektifnya(): void
    {
        [$app, $no] = $this->permitMilikAplikasiBaru();

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)
            ->update(['Flag_Approval' => 'Y', 'Approved_At' => now()]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->update([
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->toDateString(),
        ]);
        app(VisitorEfektifService::class)->syncPermitEfektif($no);

        $this->sebagai($app)->postJson($this->url($no).'/batal')->assertStatus(200);

        $this->assertSame(
            'DIBATALKAN',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_nomor_yang_tidak_ada_dibalas_404(): void
    {
        $app = $this->buatAplikasi();

        $this->sebagai($app)->getJson($this->url('PMT-TIDAK-ADA'))->assertStatus(404);
    }

    private function url(string $no): string
    {
        return '/api/v1/hcis/visitor/permit-tamu/'.$no;
    }

    private function sebagai(int $idAplikasi): self
    {
        $this->withoutMiddleware(HrisApiGuard::class);
        $this->app['request']->attributes->set('hris_api_app_id', $idAplikasi);

        return $this;
    }

    /** @return array{0:int,1:string} */
    private function permitMilikAplikasiBaru(): array
    {
        $app = $this->buatAplikasi();
        $nomor = 'PMT-API-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => 'API',
            'Id_Api_Aplikasi' => $app,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'API-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Mitra',
            'Instansi_Tamu' => 'PT Mitra',
            'Waktu_Mulai_Berlaku' => now()->format('Y-m-d H:i:s'),
            'Waktu_Berakhir' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        return [$app, $nomor];
    }

    private function buatAplikasi(): int
    {
        return (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => self::PIC,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiStatusBatalTest.php`
Expected: FAIL — metode `status` dan `batal` belum ada di controller.

- [ ] **Step 3: Tambahkan `status()` dan `batal()` ke controller**

```php
public function status(Request $request, string $no_transaksi): JsonResponse
{
    $baris = $this->permitMitra($request, $no_transaksi);

    if (! $baris) {
        return $this->galat('Permit tidak ditemukan.', 404);
    }

    return $this->sukses('Status permit', [
        'no_transaksi' => $baris->No_Transaksi,
        'status' => $baris->Status_Permit,
        'alasan' => $baris->Alasan_Pencabutan,
        'waktu_mulai' => $baris->Waktu_Mulai_Berlaku,
        'waktu_selesai' => $baris->Waktu_Berakhir,
        'referensi_mitra' => $baris->Referensi_Mitra,
    ], 200);
}

public function batal(Request $request, string $no_transaksi): JsonResponse
{
    $baris = $this->permitMitra($request, $no_transaksi);

    if (! $baris) {
        return $this->galat('Permit tidak ditemukan.', 404);
    }

    $alasan = 'Dibatalkan aplikasi mitra.';

    DB::transaction(function () use ($baris, $alasan) {
        DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $baris->No_Transaksi)
            ->whereNull('Flag_Approval')
            ->update(['Flag_Approval' => 'T', 'Updated_At' => now()]);

        DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $baris->No_Transaksi)
            ->whereNull('Flag_Approval')
            ->update(['Flag_Approval' => 'T', 'Catatan' => $alasan, 'Tanggal_Approval' => now(), 'update_at' => now()]);
    });

    // No-op bila permitnya belum pernah disetujui; kalau sudah, inilah yang
    // benar-benar mematikan aksesnya di gerbang.
    app(\App\Services\VISITOR\Operations\Expiration\VisitorEfektifService::class)
        ->revokePermitEfektif($baris->No_Transaksi, $alasan, (string) $baris->Kode_Karyawan_Pemohon);

    return $this->sukses('Permit dibatalkan', ['no_transaksi' => $baris->No_Transaksi], 200);
}

/**
 * Permit milik aplikasi pemanggil saja.
 *
 * Penyaring Id_Api_Aplikasi ini SATU-SATUNYA yang memisahkan data antar mitra.
 * Nomor milik mitra lain dibalas seperti nomor yang tidak ada — 403 akan
 * mengonfirmasi bahwa nomornya memang ada.
 */
private function permitMitra(Request $request, string $noTransaksi): ?object
{
    $idAplikasi = (int) $request->attributes->get('hris_api_app_id');

    return app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)
        ->basePermitQuery()
        ->where('h.No_Transaksi', $noTransaksi)
        ->where('h.Id_Api_Aplikasi', $idAplikasi)
        ->first();
}
```

`basePermitQuery()` perlu ikut mengembalikan `h.Referensi_Mitra`, `d.Waktu_Mulai_Berlaku`, dan `d.Waktu_Berakhir`; tambahkan ketiganya ke `select(...)` di `VisitorPermitQueryService::basePermitQuery()`.

- [ ] **Step 4: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiStatusBatalTest.php`
Expected: PASS (6 tes).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiStatusBatalTest.php
git commit -m "feat(visitor): status dan pembatalan permit mitra dengan isolasi antar aplikasi"
```

---

### Task 5: Approval final permit API tanpa durasi bulanan

**Files:**
- Modify: `app/Services/VISITOR/Workflow/Approval/VisitorApprovalDecisionService.php` (`approvePermit()`)
- Modify: `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php` (`syncPermitEfektif()`)
- Test: `tests/Integration/VISITOR/Workflow/Approval/ApprovalPermitApiTest.php`

**Interfaces:**
- Consumes: kolom Task 1.
- Produces: `approvePermit()` menerima permit `Sumber_Pengajuan='API'` tanpa `id_durasi_permit`; proyeksi berisi `Sumber_Permit='API'`, `Waktu_Mulai_Berlaku`/`Waktu_Berakhir`, `Tanggal_*` turunannya, serta `Nama_Pemegang`/`Instansi_Pemegang`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Workflow\Approval;

use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Approval final permit mitra memakai jendela dari pengajuannya, bukan durasi
 * bulanan yang dipilih approver.
 *
 * Penjaga MONTH di resolveDuration() melindungi permit KARYAWAN dan tidak boleh
 * dilonggarkan untuk ini — permit API memang tidak melewatinya sama sekali.
 * Uji cermin di bawah menagih fakta itu secara langsung.
 */
class ApprovalPermitApiTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_permit_api_disetujui_tanpa_id_durasi(): void
    {
        $no = $this->buatPermitApi();

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $this->assertSame('Y', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'));
    }

    public function test_proyeksi_membawa_jendela_identitas_dan_sumber(): void
    {
        $no = $this->buatPermitApi();

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();

        $this->assertNotNull($pe, 'Permit mitra tidak sampai ke proyeksi gerbang.');
        $this->assertSame('API', $pe->Sumber_Permit);
        $this->assertSame('Rudi Mitra', $pe->Nama_Pemegang);
        $this->assertSame('PT Mitra Sejahtera', $pe->Instansi_Pemegang);
        $this->assertSame('2026-09-10 08:00:00', substr((string) $pe->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-10 17:00:00', substr((string) $pe->Waktu_Berakhir, 0, 19));
    }

    public function test_kolom_tanggal_lama_ikut_terisi(): void
    {
        // Invarian kontrak gerbang: gate yang belum mengadopsi kolom jam hanya
        // membaca Tanggal_*; membiarkannya kosong membuat permit tak terlihat.
        $no = $this->buatPermitApi();

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();

        $this->assertSame('2026-09-10', substr((string) $pe->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-10', substr((string) $pe->Tanggal_Kadaluarsa, 0, 10));
    }

    public function test_cermin_permit_hcis_masih_menuntut_durasi_bulanan(): void
    {
        $no = $this->buatPermitHcis();

        $this->expectException(\InvalidArgumentException::class);
        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);
    }

    private function buatPermitApi(): string
    {
        return $this->buatPermit('API', '2026-09-10 08:00:00', '2026-09-10 17:00:00');
    }

    private function buatPermitHcis(): string
    {
        return $this->buatPermit('HCIS', null, null);
    }

    private function buatPermit(string $sumber, ?string $mulai, ?string $selesai): string
    {
        $nomor = 'PMT-APV-'.Str::upper(Str::random(8));
        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori', 'COKLAT')->first();

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => $sumber,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'APV-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'MT',
            'Id_Master_Kategori_Permit' => $kategori->Id_Kategori_Permit,
            'Snapshot_Kategori_Permit' => $kategori->Nama_Kategori,
            'Nama_Tamu' => 'Rudi Mitra',
            'Instansi_Tamu' => 'PT Mitra Sejahtera',
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $selesai,
        ]);

        return $nomor;
    }
}
```

> **Catatan pelaksana.** Tanda tangannya sudah cocok: `approvePermit(string $noTransaksi, string $approverKode, array $data, bool $checklistRequired): bool`. `$header` di dalamnya diambil dengan `SELECT *`, jadi `Sumber_Pengajuan` otomatis ikut dan tidak perlu ditambahkan ke `select`. Yang WAJIB diperhatikan: `approvePermit()` baru sampai ke penetapan masa berlaku bila `isApprovalComplete()` bernilai true. Fixture di atas belum punya baris `HRIS_Approval_Request`, jadi sisipkan satu baris yang **sudah disetujui** untuk nomor itu sebelum memanggilnya — kalau tidak, tesnya hijau karena tidak pernah sampai ke kode yang diuji.

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Workflow/Approval/ApprovalPermitApiTest.php`
Expected: FAIL — `resolveDuration()` melempar "Approver final wajib memilih durasi permit."

- [ ] **Step 3: Cabangkan `approvePermit()` di titik durasi**

Ganti blok pengambilan durasi menjadi:

```php
$detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
    ->where('No_Transaksi', $noTransaksi)
    ->first();

$dariApi = strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS')) === 'API';

if ($dariApi) {
    // Permit mitra membawa jendela jamnya sendiri sejak diajukan; tidak ada
    // durasi bulanan untuk dipilih. Penjaga MONTH di resolveDuration() sengaja
    // TIDAK disentuh — permit karyawan tetap melewatinya utuh.
    $mulai = Carbon::parse($detail->Waktu_Mulai_Berlaku);
    $akhir = Carbon::parse($detail->Waktu_Berakhir);

    DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
        ->where('No_Transaksi', $noTransaksi)
        ->update([
            'Id_Master_Durasi' => null,
            'Snapshot_Nama_Durasi' => $this->labelJendela($mulai, $akhir),
            // Kolom tanggal lama tetap diisi turunan jendelanya: aplikasi
            // gerbang yang belum mengadopsi kolom jam hanya membaca ini.
            'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
            'Tanggal_Kadaluarsa' => $akhir->toDateString(),
        ]);
} else {
    $duration = $this->resolveDuration($data['id_durasi_permit'] ?? null);
    [$start, $expiry] = $this->validityCalculator->calculate(
        FormatTanggalHelper::getCurrentTime(),
        (int) $duration->Jumlah_Angka,
    );

    DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
        ->where('No_Transaksi', $noTransaksi)
        ->update([
            'Id_Master_Durasi' => $duration->Id_Durasi_Permit,
            'Snapshot_Nama_Durasi' => $duration->Nama_Durasi,
            'Tanggal_Mulai_Berlaku' => $start,
            'Tanggal_Kadaluarsa' => $expiry,
        ]);
}
```

Tambahkan helper privat `labelJendela(Carbon $mulai, Carbon $akhir): string` yang mengembalikan `"6 jam"` / `"6 jam 30 menit"` — bentuknya sama dengan `labelDurasiJendela()` di `VisitorEfektifService`; pindahkan ke satu helper bersama bila keduanya identik.

Pastikan `$header` pada `approvePermit()` sudah memuat `Sumber_Pengajuan` (tambahkan ke `select` bila kuerinya eksplisit).

- [ ] **Step 4: Bawa jendela dan identitas ke proyeksi**

Di `VisitorEfektifService::syncPermitEfektif()`, pada array `$row`:

```php
// Permit mitra membawa jendela jam dan identitas pemegang; permit karyawan
// tidak, dan nilainya tetap NULL. Sumber_Permit mengikuti sumber pengajuan.
'Sumber_Permit' => strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS')) === 'API' ? 'API' : 'KARYAWAN',
'Waktu_Mulai_Berlaku' => $header->Waktu_Mulai_Berlaku ?? null,
'Waktu_Berakhir' => $header->Waktu_Berakhir ?? null,
'Nama_Pemegang' => $header->Nama_Tamu ?? null,
'Instansi_Pemegang' => $header->Instansi_Tamu ?? null,
```

`basePermitQuery()` harus ikut mengembalikan `h.Sumber_Pengajuan`, `d.Nama_Tamu`, dan `d.Instansi_Tamu` (kolom jendela sudah ditambahkan di Task 4).

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Workflow/Approval/ApprovalPermitApiTest.php`
Expected: PASS (4 tes).

- [ ] **Step 6: Commit**

```bash
git add app/Services/VISITOR tests/Integration/VISITOR/Workflow/Approval/ApprovalPermitApiTest.php
git commit -m "feat(visitor): approval permit mitra memakai jendela jam pengajuannya"
```

---

### Task 6: Status dan sapuan kedaluwarsa yang sadar jam

**Files:**
- Modify: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php` (`permitStatusSql()`)
- Modify: `app/Console/Commands/VISITOR/Operations/Expiration/ExpireVisitorEfektif.php` (cabang 2)
- Test: `tests/Integration/VISITOR/Operations/PermitApiBerakhirTest.php`

**Interfaces:**
- Consumes: `Waktu_Berakhir` di detail dan proyeksi.
- Produces: `permitStatusSql()` menghitung `EXPIRED` dari jam bila jendelanya ada; cabang jam pada sapuan berlaku untuk **semua** sumber.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit berjendela jam harus mati tepat pada jamnya — di sapuan MAUPUN di
 * layar.
 *
 * Sebelum ini keduanya berketelitian hari: permit yang habis pukul 12:00 tetap
 * terbaca APPROVED di meja verifikator sampai lewat tengah malam, dan sapuannya
 * hanya menyentuh Sumber_Permit='TAMU' sehingga permit mitra tidak pernah
 * tersapu sama sekali.
 */
class PermitApiBerakhirTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_permit_api_mati_di_jam_selesainya(): void
    {
        $no = $this->buatPermitEfektifApi(now()->subHours(3), now()->subHour());

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'EXPIRED',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_permit_api_yang_belum_habis_tetap_hidup(): void
    {
        $no = $this->buatPermitEfektifApi(now()->subHour(), now()->addHours(3));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'AKTIF',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_status_di_layar_ikut_sadar_jam(): void
    {
        $no = $this->buatPengajuanDisetujui(now()->subHours(3), now()->subHour());

        $baris = app(VisitorPermitQueryService::class)->basePermitQuery()
            ->where('h.No_Transaksi', $no)->first();

        $this->assertSame('EXPIRED', $baris->Status_Permit);
    }

    public function test_permit_karyawan_berbulan_tidak_tersentuh(): void
    {
        $no = $this->buatPengajuanDisetujui(null, null, kadaluarsa: now()->addMonths(2));

        $baris = app(VisitorPermitQueryService::class)->basePermitQuery()
            ->where('h.No_Transaksi', $no)->first();

        $this->assertSame('APPROVED', $baris->Status_Permit);
    }

    private function buatPermitEfektifApi(\Illuminate\Support\Carbon $mulai, \Illuminate\Support\Carbon $akhir): string
    {
        $nomor = 'PMT-EXA-'.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Nama_Pemohon' => 'PIC Uji',
            'Nama_Kategori_Permit' => 'COKLAT',
            'Sumber_Permit' => 'API',
            'Status_Efektif' => 'AKTIF',
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $akhir,
            'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
            'Tanggal_Kadaluarsa' => $akhir->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'T',
        ]);

        return $nomor;
    }

    private function buatPengajuanDisetujui(
        ?\Illuminate\Support\Carbon $mulai,
        ?\Illuminate\Support\Carbon $akhir,
        ?\Illuminate\Support\Carbon $kadaluarsa = null,
    ): string {
        $nomor = 'PMT-STS-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Approved_At' => now()->subDay(),
            'Sumber_Pengajuan' => $mulai ? 'API' : 'HCIS',
            'Created_At' => now()->subDays(2),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'STS-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'ST',
            'Waktu_Mulai_Berlaku' => $mulai?->format('Y-m-d H:i:s'),
            'Waktu_Berakhir' => $akhir?->format('Y-m-d H:i:s'),
            'Tanggal_Mulai_Berlaku' => ($mulai ?? now()->subMonth())->toDateString(),
            'Tanggal_Kadaluarsa' => ($akhir ?? $kadaluarsa)->toDateString(),
        ]);

        return $nomor;
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitApiBerakhirTest.php`
Expected: FAIL — permit API tidak tersapu (`AKTIF`), dan status di layar terbaca `APPROVED`.

- [ ] **Step 3: Buat `permitStatusSql()` sadar jam**

Ganti cabang `EXPIRED`-nya:

```php
public function permitStatusSql(): string
{
    // Cabang EXPIRED punya DUA ketelitian. Permit berjendela jam (mitra B2B)
    // dinilai dari Waktu_Berakhir; permit karyawan berdurasi bulan tetap
    // dinilai per hari. Tanpa cabang pertama, permit yang habis pukul 12:00
    // terbaca APPROVED di meja verifikator sampai lewat tengah malam.
    return "CASE WHEN ".$this->dicabutSql()." THEN 'REVOKED'"
        ." WHEN h.Flag_Approval='Y' AND (d.Tanggal_Mulai_Berlaku IS NULL OR d.Tanggal_Kadaluarsa IS NULL) THEN 'VALIDITY_REQUIRED'"
        ." WHEN h.Flag_Approval='Y' AND d.Waktu_Berakhir IS NOT NULL AND d.Waktu_Berakhir < GETDATE() THEN 'EXPIRED'"
        ." WHEN h.Flag_Approval='Y' AND d.Waktu_Berakhir IS NULL AND d.Tanggal_Kadaluarsa < CAST(GETDATE() AS date) THEN 'EXPIRED'"
        ." WHEN h.Flag_Approval='Y' THEN 'APPROVED'"
        ." WHEN h.Flag_Approval='T' THEN 'REJECTED'"
        ." WHEN NOT EXISTS (SELECT 1 FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi=h.No_Transaksi) THEN 'NO_FLOW'"
        ." WHEN EXISTS (SELECT 1 FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi=h.No_Transaksi AND ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At<GETDATE()) THEN 'EXPIRED'"
        ." ELSE 'PENDING' END";
}
```

- [ ] **Step 4: Umumkan cabang jam pada sapuan**

Di `ExpireVisitorEfektif::handle()` cabang 2, buang penyaring sumbernya:

```php
// Cabang 2 — SEMUA permit berjendela jam, apa pun sumbernya.
//
// Sebelumnya cabang ini menyaring Sumber_Permit='TAMU'. Begitu muncul sumber
// ketiga (permit mitra 'API'), penyaring itu diam-diam melewatkannya dan
// permitnya tidak pernah mati. Aturannya sekarang bersandar pada FAKTA yang
// relevan — punya jendela dan jendelanya sudah lewat — bukan pada daftar
// sumber yang harus diingat setiap kali bertambah.
$permitBerjendela = DB::table('N_VISITOR_Permit_Efektif')
    ->where('Status_Efektif', 'AKTIF')
    ->whereNotNull('Waktu_Berakhir')
    ->where('Waktu_Berakhir', '<', $now)
    ->update([
        'Status_Efektif' => 'EXPIRED',
        'Alasan_Status' => 'Jendela berlaku berakhir',
        'Updated_At' => $now,
        'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
        'Flag_Sync' => 'T',
    ]);
```

Cabang 1 (permit karyawan per hari) sudah menyaring `ISNULL(Sumber_Permit,'KARYAWAN') <> 'TAMU'`; ubah menjadi `whereNull('Waktu_Berakhir')` supaya kedua cabang tidak pernah menilai baris yang sama dua kali dengan ketelitian berbeda. Perbarui pesan `$this->info(...)` dan docblock kelasnya.

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitApiBerakhirTest.php tests/Integration/VISITOR/Operations/PermitCoklatBerakhirTest.php`
Expected: PASS. `PermitCoklatBerakhirTest` **wajib** ikut hijau — ia menjaga perilaku permit coklat tamu yang cabangnya baru saja diubah.

- [ ] **Step 6: Commit**

```bash
git add app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php app/Console/Commands/VISITOR/Operations/Expiration/ExpireVisitorEfektif.php tests/Integration/VISITOR/Operations/PermitApiBerakhirTest.php
git commit -m "fix(visitor): status dan sapuan kedaluwarsa sadar jam untuk semua permit berjendela"
```

---

### Task 7: Meja verifikator — lencana mitra dan panel penjelas

**Files:**
- Modify: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php` (`basePermitQuery()` membawa nama mitra)
- Modify: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/VerifikatorPermitPanel.vue`
- Modify: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue`
- Test: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.test.js`

**Interfaces:**
- Consumes: `Sumber_Pengajuan`, `Nama_Mitra` (dari `N_HRIS_Api_Aplikasi.Nama_Aplikasi`), `Nama_Tamu`, `Instansi_Tamu`, `Referensi_Mitra`, `Waktu_Mulai_Berlaku`, `Waktu_Berakhir`.
- Produces: lencana pada baris daftar; panel dokumen tergantikan keterangan untuk `Sumber_Pengajuan==='API'`.

- [ ] **Step 1: Bawa nama mitra dari backend**

Di `basePermitQuery()`, tambahkan ke `select(...)`:

```php
'h.Sumber_Pengajuan',
'h.Referensi_Mitra',
// Sub-kueri, bukan JOIN: kueri ini sudah menggabung tujuh tabel, dan JOIN
// kedelapan yang meleset menggandakan baris permit — kekeliruan yang pernah
// terjadi dan gejalanya "permit saya terhitung dua kali", bukan galat.
DB::raw('(SELECT TOP 1 ap.Nama_Aplikasi FROM N_HRIS_Api_Aplikasi ap WHERE ap.Id_Api_Aplikasi = h.Id_Api_Aplikasi) as Nama_Mitra'),
```

- [ ] **Step 2: Tulis tes frontend yang gagal**

Tambahkan ke `PermitReviewModal.test.js` (ikuti pola mount yang sudah dipakai berkas itu; jangan membuat helper baru):

```js
describe('PermitReviewModal pengajuan mitra B2B', () => {
    it('mengganti panel dokumen dengan keterangan untuk pengajuan API', () => {
        const source = readFileSync(
            path.resolve(
                process.cwd(),
                'resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue',
            ),
            'utf8',
        );

        expect(source).toContain("Sumber_Pengajuan === 'API'");
        expect(source).toContain('dokumen kendaraan tidak berlaku');
        expect(source).toContain('Nama_Tamu');
        expect(source).toContain('Referensi_Mitra');
    });
});
```

- [ ] **Step 3: Jalankan tes, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.test.js`
Expected: FAIL — penanda belum ada di sumber komponen.

- [ ] **Step 4: Tambahkan lencana di daftar**

Di `VerifikatorPermitPanel.vue`, pada sel identitas pemohon tiap baris:

```vue
<span v-if="row.Sumber_Pengajuan === 'API'" class="permit-mitra-badge">
    {{ row.Nama_Mitra || 'Mitra API' }}
</span>
```

Dengan gaya ber-scope:

```css
.permit-mitra-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 600;
    color: #0369a1;
    background: rgba(3, 105, 161, 0.1);
    border: 1px solid rgba(3, 105, 161, 0.22);
}
```

- [ ] **Step 5: Ganti panel dokumen di modal review**

Di `PermitReviewModal.vue`, bungkus blok dokumen yang sudah ada:

```vue
<div v-if="permit.Sumber_Pengajuan === 'API'" class="permit-mitra-note">
    <p>
        Diajukan lewat API oleh <strong>{{ permit.Nama_Mitra || 'mitra' }}</strong>;
        dokumen kendaraan tidak berlaku untuk pengajuan mitra.
    </p>
    <dl>
        <dt>Tamu</dt><dd>{{ permit.Nama_Tamu || '-' }} ({{ permit.Instansi_Tamu || '-' }})</dd>
        <dt>Jendela</dt><dd class="font-tabular">{{ jendelaPermit }}</dd>
        <dt>Referensi mitra</dt><dd class="font-tabular">{{ permit.Referensi_Mitra || '-' }}</dd>
    </dl>
</div>
<template v-else>
    <!-- blok dokumen yang sudah ada, tidak diubah isinya -->
</template>
```

Dengan computed:

```js
jendelaPermit() {
    const p = this.permit || {};

    if (!p.Waktu_Mulai_Berlaku || !p.Waktu_Berakhir) return '-';

    return `${String(p.Waktu_Mulai_Berlaku).slice(0, 16)} s/d ${String(p.Waktu_Berakhir).slice(0, 16)}`;
},
```

- [ ] **Step 6: Jalankan tes, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Verification/Verifikator`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php resources/js/Pages/VISITOR/Workflow/Verification
git commit -m "feat(visitor): tandai pengajuan permit mitra di meja verifikator"
```

---

### Task 8: Pendaftaran endpoint dan dokumentasi OpenAPI

**Files:**
- Modify: `database/dml/DDL-2026-079-visitor-permit-api-b2b/data-change.sql`
- Modify: `resources/openapi/hcis-third-party.yaml`
- Test: `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiRegistrasiTest.php`

**Interfaces:**
- Consumes: rute Task 3.
- Produces: tiga baris `N_HRIS_Api_Endpoint` berpola benar; tiga path di YAML.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Pendaftaran endpoint dan dokumentasinya.
 *
 * Url_Route WAJIB berisi POLA rute, bukan nomor konkret: whitelist guard
 * dicocokkan ke route()->uri(). Pola yang salah membuat rute berparameter
 * ditolak 401 tanpa petunjuk penyebabnya — dan dua dari tiga endpoint di sini
 * berparameter.
 */
class PermitTamuApiRegistrasiTest extends TestCase
{
    use DatabaseTransactions;

    private const POLA = [
        'POST' => '/api/v1/hcis/visitor/permit-tamu',
        'GET' => '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}',
        'POST_BATAL' => '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal',
    ];

    public function test_ketiga_endpoint_terdaftar_dengan_pola_rute(): void
    {
        foreach (self::POLA as $pola) {
            $this->assertTrue(
                DB::table('N_HRIS_Api_Endpoint')->where('Url_Route', $pola)->where('Flag_Aktif', 'Y')->exists(),
                "Endpoint {$pola} belum terdaftar.",
            );
        }
    }

    public function test_tidak_ada_url_route_berisi_nomor_konkret(): void
    {
        $salah = DB::table('N_HRIS_Api_Endpoint')
            ->where('Url_Route', 'LIKE', '/api/v1/hcis/visitor/permit-tamu/%')
            ->where('Url_Route', 'NOT LIKE', '%{no_transaksi}%')
            ->pluck('Url_Route');

        $this->assertCount(0, $salah, 'Url_Route berisi nomor konkret: '.$salah->implode(', '));
    }

    public function test_pola_rute_cocok_dengan_rute_laravel(): void
    {
        $terdaftar = collect(app('router')->getRoutes())
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->filter(fn ($u) => str_starts_with($u, '/api/v1/hcis/visitor/permit-tamu'))
            ->values()->all();

        foreach (self::POLA as $pola) {
            $this->assertContains($pola, $terdaftar, "Pola {$pola} tidak cocok dengan rute mana pun.");
        }
    }

    public function test_ketiga_rute_dijaga_hris_api_guard(): void
    {
        // Guard-nya sendiri sudah diuji terpisah; yang ditagih di sini adalah
        // bahwa rute BARU benar-benar memakainya. Rute tulis yang lupa dijaga
        // berarti siapa pun di internet bisa menerbitkan izin gerbang.
        $rute = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_starts_with('/'.ltrim($r->uri(), '/'), '/api/v1/hcis/visitor/permit-tamu'));

        $this->assertCount(3, $rute, 'Jumlah rute permit mitra berubah.');

        foreach ($rute as $r) {
            $this->assertContains(
                'hris.api.guard',
                $r->gatherMiddleware(),
                'Rute '.$r->uri().' tidak dijaga hris.api.guard.',
            );
        }
    }

    public function test_ketiga_endpoint_ada_di_openapi(): void
    {
        $spec = Yaml::parseFile(base_path('resources/openapi/hcis-third-party.yaml'));

        foreach (self::POLA as $pola) {
            $this->assertArrayHasKey($pola, $spec['paths'], "Path {$pola} belum ada di OpenAPI.");
        }
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiRegistrasiTest.php`
Expected: FAIL — belum ada baris registry maupun path YAML.

- [ ] **Step 3: Tambahkan pendaftaran endpoint ke `data-change.sql`**

```sql
-- Tiga endpoint permit mitra. Url_Route WAJIB berisi POLA rute termasuk
-- "{no_transaksi}" — whitelist guard dicocokkan ke route()->uri(), dan pola
-- yang salah membuat rute berparameter ditolak 401 tanpa petunjuk penyebabnya.
-- Id_Grup_Fitur 4 = VISITOR.
MERGE N_HRIS_Api_Endpoint AS t
USING (VALUES
    ('/api/v1/hcis/visitor/permit-tamu', 'POST', N'Ajukan permit COKLAT untuk tamu mitra B2B'),
    ('/api/v1/hcis/visitor/permit-tamu/{no_transaksi}', 'GET', N'Status permit COKLAT mitra B2B'),
    ('/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal', 'POST', N'Pembatalan permit COKLAT oleh mitra B2B')
) AS s (Url_Route, Method, Deskripsi)
ON t.Url_Route = s.Url_Route AND t.Method = s.Method
WHEN NOT MATCHED THEN
    INSERT (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
    VALUES (4, s.Url_Route, s.Method, 'IN', s.Deskripsi, SYSDATETIME(), 'Y');
GO
```

- [ ] **Step 4: Tambahkan ketiga path ke `resources/openapi/hcis-third-party.yaml`**

Sisipkan di bawah `paths:`, mengikuti gaya entri `/api/v1/hcis/kalender-operasional/tahun/{tahun}` yang sudah ada:

```yaml
  /api/v1/hcis/visitor/permit-tamu:
    post:
      tags: [Visitor]
      summary: Ajukan permit COKLAT untuk tamu mitra
      description: >
        Membuat pengajuan permit akses gerbang untuk seorang tamu milik aplikasi
        mitra. Permit TIDAK langsung aktif: ia menunggu persetujuan verifikator.
        Pantau keputusannya lewat GET pada nomor transaksi yang dikembalikan.
      parameters:
        - name: Idempotency-Key
          in: header
          required: true
          schema: { type: string, maxLength: 100 }
          description: Kiriman ulang dengan kunci yang sama tidak melahirkan permit kedua.
      requestBody:
        required: true
        content:
          application/json:
            schema: { $ref: '#/components/schemas/PermitTamuRequest' }
      responses:
        '201': { description: Pengajuan diterima }
        '200': { description: Kiriman ulang idempoten }
        '422': { description: Payload atau jendela tidak sah }
        '429': { description: Batas permit aktif aplikasi tercapai }

  /api/v1/hcis/visitor/permit-tamu/{no_transaksi}:
    get:
      tags: [Visitor]
      summary: Status permit COKLAT mitra
      parameters:
        - name: no_transaksi
          in: path
          required: true
          schema: { type: string }
      responses:
        '200': { description: Status permit }
        '404': { description: Permit tidak ditemukan, atau bukan milik aplikasi pemanggil }

  /api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal:
    post:
      tags: [Visitor]
      summary: Batalkan permit COKLAT mitra
      description: >
        Selagi PENDING, pembatalan menutup alur persetujuannya. Sesudah APPROVED,
        ia mencabut izin efektifnya sehingga akses di gerbang berhenti.
      parameters:
        - name: no_transaksi
          in: path
          required: true
          schema: { type: string }
      responses:
        '200': { description: Permit dibatalkan }
        '404': { description: Permit tidak ditemukan, atau bukan milik aplikasi pemanggil }
```

Tambahkan `Visitor` ke blok `tags:` bila belum ada, lalu dua skema ini ke `components.schemas`:

```yaml
    PermitTamuRequest:
      type: object
      required: [nama_tamu, instansi, keperluan, waktu_mulai, waktu_selesai]
      properties:
        nama_tamu: { type: string, maxLength: 150 }
        instansi: { type: string, maxLength: 150 }
        no_identitas: { type: string, maxLength: 100, nullable: true }
        jenis_identitas: { type: string, maxLength: 20, nullable: true }
        jenis_kendaraan: { type: string, maxLength: 50, nullable: true }
        plat_prefix: { type: string, maxLength: 5, nullable: true }
        plat_angka: { type: string, maxLength: 10, nullable: true }
        plat_suffix: { type: string, maxLength: 10, nullable: true }
        waktu_mulai: { type: string, example: '2026-09-10 08:00:00' }
        waktu_selesai: { type: string, example: '2026-09-10 17:00:00' }
        keperluan: { type: string, maxLength: 250 }
        referensi_mitra: { type: string, maxLength: 100, nullable: true }

    PermitTamuStatus:
      type: object
      properties:
        sukses: { type: boolean }
        message: { type: string }
        result:
          type: object
          properties:
            no_transaksi: { type: string }
            status: { type: string, enum: [PENDING, APPROVED, REJECTED, REVOKED, EXPIRED] }
            alasan: { type: string, nullable: true }
            waktu_mulai: { type: string, nullable: true }
            waktu_selesai: { type: string, nullable: true }
            referensi_mitra: { type: string, nullable: true }
```

Kunci rahasia tidak pernah diletakkan di contoh URL maupun di contoh nilai.

- [ ] **Step 5: Jalankan DML ke development lalu verifikasi**

```bash
php artisan tinker --execute="foreach(DB::table('N_HRIS_Api_Endpoint')->where('Url_Route','LIKE','/api/v1/hcis/visitor/permit-tamu%')->get() as \$e) echo \$e->Method.' '.\$e->Url_Route.' | '.\$e->Flag_Aktif.PHP_EOL;"
```

Harapan: tiga baris, dua di antaranya memuat `{no_transaksi}`.

- [ ] **Step 6: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiRegistrasiTest.php`
Expected: PASS (5 tes).

- [ ] **Step 7: Perbarui `status.json` dan README change**

Set `development.status` = `DEV_EXECUTED` dengan hasil verifikasi Step 5 dan Task 1 Step 6. Bagian "Pekerjaan user" di README tetap memuat pendaftaran aplikasi mitra.

- [ ] **Step 8: Commit**

```bash
git add database/dml/DDL-2026-079-visitor-permit-api-b2b resources/openapi/hcis-third-party.yaml database/ddl/status.json docs/changes/DDL-2026-079-visitor-permit-api-b2b tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiRegistrasiTest.php
git commit -m "feat(visitor): daftarkan endpoint permit mitra dan dokumentasinya"
```

---

## Verifikasi Akhir

- [ ] `php artisan test tests/Integration/VISITOR tests/Feature/VISITOR tests/Unit/VISITOR` hijau, kecuali sepuluh kegagalan yang sudah ada sebelum pekerjaan ini (6 `VerifikatorDecisionNotifiesRequesterTest`, 2 `VisitorSubmissionNotifiesAdminTest`, 2 `PermitEfektifFlagBatalTest` — identik dengan baseline `master`).
- [ ] `php artisan test tests/Feature/SYSTEM/Integration` hijau — guard dan penyaring OpenAPI ikut terdampak.
- [ ] `npx vitest run resources/js/Pages/VISITOR` hijau.
- [ ] `php artisan test` penuh hijau sebelum PR; penjaga baru menyentuh `basePermitQuery()` dan `permitStatusSql()` yang dipakai banyak halaman.
- [ ] Laporan dua bagian sesuai `AGENTS.md`: **Agent completed** dan **User tasks**.
- [ ] **Pekerjaan user yang tidak bisa diambil alih agent:** mendaftarkan aplikasi mitra (nama, pasangan kunci publik/rahasia, `Kode_Karyawan_PIC`) dan memberinya akses ketiga endpoint di `N_HRIS_Api_Akses_Endpoint`; membagikan kunci rahasia lewat kanal aman; serta menetapkan prosedur pencabutan aplikasi (`Flag_Aktif='N'`) sebelum darurat, bukan saat darurat.
