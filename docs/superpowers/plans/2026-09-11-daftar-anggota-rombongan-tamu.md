# Daftar Anggota Rombongan Tamu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrasi tamu mencatat **siapa saja** yang datang, bukan hanya satu nama dan sebuah angka.

**Architecture:** Satu kolom JSON `Daftar_Anggota_Json` di transaksi dan di proyeksi tamu, berisi array `{nama, jenis_identitas?, no_identitas?}` yang memuat seluruh rombongan termasuk tamu utama. `Jumlah_Tamu` berhenti menjadi isian dan diturunkan dari panjang daftar di satu tempat penulisan, sehingga tidak ada dua kebenaran. Aturan penyusunan dan batasnya hidup di satu service murni tanpa database, sehingga ujinya masuk suite `Unit` — satu-satunya suite yang benar-benar dijalankan CI.

**Tech Stack:** PHP 8.1+, Laravel 10 (query builder `DB::table()`, bukan Eloquent — modul Visitor seluruhnya begitu), SQL Server 2022 (`Web_HRIS`, JSON via `ISJSON`), PHPUnit (`Tests\TestCase`, `DatabaseTransactions` untuk yang menyentuh DB), Vue 3 Options API + Vitest.

**Spec:** `docs/superpowers/specs/2026-09-11-daftar-anggota-rombongan-tamu-design.md`

## Global Constraints

- **Change ID:** `DDL-2026-100-visitor-daftar-anggota-tamu`. DDL di `database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu/` (`forward.sql` + `drop.sql`), artefak DML wajib di `database/dml/DDL-2026-100-visitor-daftar-anggota-tamu/data-change.sql`, dan entri wajib di `database/ddl/status.json`. Artefak DML hanya menjelaskan bahwa **tidak ada mutasi data** — data lama sengaja tidak di-backfill.
- **Database:** hanya `Web_HRIS` (development). Agent tidak pernah mengeksekusi ke production. `drop.sql` dibuat tetapi tidak pernah dijalankan agent. Tidak ada migration Laravel baru — penjaga CI `Perubahan database` menolaknya.
- **Struktur modul:** sebelum membuat file pertama, baca ulang `docs/architecture/module-registry.md` dan `docs/architecture/module-structure-inventory.md`, lalu verifikasi kepemilikan grup `VISITOR` terhadap `N_HRIS_Master_Grup_Fitur` **serta mapping halaman registrasi tamu terhadap `N_HRIS_Grup_Fitur_Map`** di `Web_HRIS`. Semua file baru tetap di pohon `VISITOR/Guest/Visit`; jangan membuat root bisnis generik.
- **Konvensi JSON repo ini:** kolom bernama `*_Json`, tipe `nvarchar(max)`, dijaga `CONSTRAINT CK_<tabel>_<kolom> CHECK (<kolom> IS NULL OR ISJSON(<kolom>) = 1)` — persis seperti `DDL-2026-007-create-pengumpulan-data-tables`.
- **`Jumlah_Tamu` DITURUNKAN dari daftar untuk semua registrasi baru.** Kolomnya tetap ditulis supaya papan harian, dashboard, laporan, dan aplikasi gerbang tidak perlu berubah. Nilai `jumlah_tamu` dari request diabaikan; key `daftar_anggota` wajib.
- **Daftar memuat SEMUA orang**, elemen pertama adalah tamu utama (`Nama_Lengkap_Tamu`). Input HTTP `daftar_anggota` hanya membawa anggota selain utama; backend menyusun bentuk simpan lengkap.
- **Registrasi baru wajib berdaftar.** UI selalu mengirim key `daftar_anggota` (boleh `[]` untuk tamu tunggal); backend menuntut key tersebut dan menolak isi yang bukan array atau punya nama kosong. Request tanpa key ditolak 422. Karena kolom database aditif dan nullable, kompatibilitas deployment dijaga lewat urutan rilis **DDL → kode backend+UI**; tidak ada fase backend baru dengan UI lama.
- **Data lama tidak di-backfill.** `Daftar_Anggota_Json IS NULL` berarti "daftar tidak pernah dikumpulkan"; pembaca jatuh kembali ke `Nama_Tamu`.
- **Ada DUA `baseTamuQuery()`** — satu di `GuestRegistrationController` (`protected`, ~baris 717) dan satu di `VisitorPermitQueryService` (`public`, ~baris 190). Keduanya WAJIB membawa kolom baru; menambal satu saja berarti separuh layar tidak melihat daftarnya, tanpa galat apa pun.
- **Fixture uji yang menyentuh DB wajib memakai `Kode_Karyawan` nyata** (mis. `A1`). Kode karangan membuat `syncTamuEfektif()` gagal karena `Nama_PIC` `NOT NULL` di-resolve lewat JOIN ke `Karyawan`.
- **Pint:** `./vendor/bin/pint --test` sudah merah di `master` untuk 24 berkas warisan. Yang wajib bersih hanya berkas yang task ini sentuh: `./vendor/bin/pint --test $(git diff --name-only origin/master...HEAD | grep '\.php$')`.
- **Kapan tes dijalankan:** user proyek ini meminta kode dan tes ditulis sampai selesai lebih dulu, lalu semua tes baru dijalankan setelah ACC. Karena itu, saat eksekusi **tulis seluruh test dulu tetapi tandai setiap langkah Run sebagai tertunda**; jangan menjalankan satu pun perintah tes, lint, build, atau penjaga sampai user memberi ACC eksplisit. Sesudah ACC, jalankan urutan MERAH/HIJAU yang dicatat plan sejauh masih dapat dibuktikan; untuk test yang implementation-nya sudah terlanjur ada, verifikasi perubahan penjaganya lewat mutation sementara lalu pulihkan, bukan mengklaim pernah melihat merah.

---

### Task 1: Skema — kolom daftar anggota

**Files:**
- Create: `database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu/forward.sql`
- Create: `database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu/drop.sql`
- Create: `database/dml/DDL-2026-100-visitor-daftar-anggota-tamu/data-change.sql`
- Create: `docs/changes/DDL-2026-100-visitor-daftar-anggota-tamu/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: —
- Produces: `N_EMI_VISITOR_Transaksi_Tamu_Detail.Daftar_Anggota_Json` dan `N_VISITOR_Tamu_Efektif.Daftar_Anggota_Json`, keduanya `nvarchar(max) NULL` berpenjaga `ISJSON`.

- [ ] **Step 1: Verifikasi struktur modul dan laporkan antrean DDL**

`CLAUDE.md` mewajibkan pemeriksaan registry sebelum membuat file, dan `AGENTS.md` mewajibkan laporan antrean sebelum change baru:

```bash
php artisan tinker --execute="
echo 'DB='.DB::selectOne('SELECT DB_NAME() AS db')->db.PHP_EOL;
\$g=DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'VISITOR')->where('Flag_Aktif','Y')->first(['Id_Grup_Fitur','Kode_Grup_Fitur','Nama_Grup_Fitur']);
echo 'GRUP='.json_encode(\$g).PHP_EOL;
foreach(DB::table('N_HRIS_Grup_Fitur_Map as gfm')->join('N_HRIS_Menus as m','m.Jenis_page','=','gfm.Jenis_page')->where('gfm.Id_Grup_Fitur', \$g->Id_Grup_Fitur)->where('gfm.Flag_Aktif','Y')->where(function(\$q){\$q->where('m.Url_Menu','like','%tamu%')->orWhere('m.Nama_Menu','like','%tamu%')->orWhere('m.Jenis_page','like','%tamu%');})->get(['m.Jenis_page','m.Nama_Menu','m.Url_Menu','gfm.Id_Grup_Fitur']) as \$m) echo 'MAP='.json_encode(\$m).PHP_EOL;"
php -r '$j=json_decode(file_get_contents("database/ddl/status.json"),true); foreach(($j["changes"]??$j) as $k=>$v){ $s=is_array($v)?($v["sql_tracker"]["status"]??"?"):$v; $id=is_array($v)?($v["change_id"]??$k):$k; if(!in_array($s,["TRACKER_RECORDED","CANCELLED"],true)) echo "$id => $s\n"; }'
```

Sebelum perintah pertama, kosongkan `DATABASE_URL` sesuai `AGENTS.md`. Harapan: koneksi ke `Web_HRIS`, grup aktif `VISITOR`, dan mapping page registrasi tamu lewat `Jenis_page` terverifikasi; semua change dengan tracker belum direkam dilaporkan. Catat environment, ID/kode grup, `Jenis_page`/URL, dan tanggal verifikasi 2026-09-11 di README change.

- [ ] **Step 2: Tulis `forward.sql`**

```sql
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-100-visitor-daftar-anggota-tamu
--
-- Registrasi tamu selama ini mencatat SATU nama dan sebuah ANGKA. Dari 83
-- registrasi di development, 62 (75%) membawa lebih dari satu orang — artinya
-- mayoritas orang yang melewati gerbang tidak tercatat namanya di mana pun.
--
-- Kolom ini menyimpan daftar SELURUH rombongan, termasuk tamu utama, sebagai
-- array JSON: [{"nama":"...","jenis_identitas":"KTP","no_identitas":"..."}]
-- Kedua kunci identitas opsional; hanya "nama" yang wajib.
--
-- JSON, bukan tabel anak, karena di gerbang rombongan diperlakukan sebagai satu
-- kesatuan: daftarnya manifes untuk dicocokkan mata, bukan catatan yang berubah
-- per orang. Tidak ada keadaan per-orang — dan justru keadaan per-orang itulah
-- satu-satunya hal yang membuat JSON menyakitkan.
--
-- NULL berarti "daftar tidak pernah dikumpulkan", bukan "rombongan kosong".
-- Baris lama SENGAJA tidak di-backfill: Jumlah_Tamu-nya sampai 5 sementara
-- hanya satu nama yang pernah tercatat, jadi daftar karangan satu elemen akan
-- bertentangan dengan jumlahnya selamanya — dan bertentangan diam-diam.
--
-- CHECK ISJSON mengikuti konvensi DDL-2026-007: penjaga murah yang mencegah
-- teks bukan-JSON masuk dan meledakkan pembaca jauh di hilir.
--
-- Kedua pernyataan idempoten.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Daftar_Anggota_Json nvarchar(max) NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json')
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail
    ADD CONSTRAINT CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json
        CHECK (Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1);
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Daftar_Anggota_Json nvarchar(max) NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json')
ALTER TABLE N_VISITOR_Tamu_Efektif
    ADD CONSTRAINT CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json
        CHECK (Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1);
GO
```

> **Kenapa `ALTER TABLE ADD` kolom dibungkus `BEGIN…END`:** `ALTER TABLE … ADD` harus menjadi pernyataan pertama dalam batch-nya bila didahului `IF`; membungkusnya dalam blok membuat batasannya terpenuhi dan skripnya tetap idempoten. CHECK-nya dipisah ke batch sendiri karena kolomnya baru ada sesudah batch sebelumnya selesai.

- [ ] **Step 3: Tulis `drop.sql`**

```sql
-- Change ID: DDL-2026-100-visitor-daftar-anggota-tamu
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Menjatuhkan kolom ini menghapus daftar nama SELURUH rombongan yang pernah
-- didaftarkan. Tidak ada sumber lain untuk memulihkannya: Jumlah_Tamu hanya
-- menyimpan angkanya, bukan namanya.

ALTER TABLE N_VISITOR_Tamu_Efektif DROP CONSTRAINT IF EXISTS CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json;
GO
ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN IF EXISTS Daftar_Anggota_Json;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP CONSTRAINT IF EXISTS CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN IF EXISTS Daftar_Anggota_Json;
GO
```

- [ ] **Step 4: Tulis artefak DML tanpa mutasi data**

`AGENTS.md` mewajibkan `database/dml/<change-id>/data-change.sql` untuk setiap schema change. Karena keputusan desain melarang backfill, isi berkasnya eksplisit:

```sql
-- DDL-2026-100-visitor-daftar-anggota-tamu
-- Target: Web_HRIS.
--
-- Tidak ada perubahan data untuk change ini.
-- Baris lama SENGAJA dibiarkan Daftar_Anggota_Json = NULL karena sistem hanya
-- pernah menyimpan satu nama sementara Jumlah_Tamu bisa lebih dari satu.
-- NULL berarti "daftar tidak pernah dikumpulkan"; backfill satu nama akan
-- mengarang manifes yang bertentangan dengan Jumlah_Tamu.
```

- [ ] **Step 5: Inspeksi objek dan minta persetujuan eksekusi development**

Verifikasi live connection dengan `SELECT DB_NAME()` dan host terkonfigurasi, lalu inspeksi dua tabel/kolom lewat katalog. Tampilkan SQL `forward.sql` persisnya dan sebutkan:

- target: `Web_HRIS` development;
- operasi: empat `ALTER TABLE` aditif (dua kolom nullable, dua CHECK);
- objek: `N_EMI_VISITOR_Transaksi_Tamu_Detail`, `N_VISITOR_Tamu_Efektif`;
- data terdampak: semua baris lama memperoleh kolom baru bernilai `NULL`; tidak ada row update;
- rollback manual: `drop.sql`, **tidak akan dijalankan agent**.

Jangan menjalankan DDL sebelum user menyetujuinya. Persetujuan desain atau plan bukan persetujuan eksekusi database.

- [ ] **Step 6: Jalankan `forward.sql` ke development sesudah persetujuan**

Jalankan forward DDL yang sudah disetujui ke `Web_HRIS`. Tidak ada `DROP`, `TRUNCATE`, atau DML.

- [ ] **Step 7: Verifikasi dari katalog sistem, bukan dari keluaran skrip**

```bash
php artisan tinker --execute="
\$k=DB::select(\"SELECT TABLE_NAME t, DATA_TYPE d, CHARACTER_MAXIMUM_LENGTH l, IS_NULLABLE n FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME='Daftar_Anggota_Json'\");
foreach(\$k as \$x) echo \$x->t.' | '.\$x->d.'('.\$x->l.') nullable='.\$x->n.PHP_EOL;
echo 'kolom: '.count(\$k).' (harapan 2)'.PHP_EOL;
foreach(DB::select(\"SELECT name FROM sys.check_constraints WHERE name IN ('CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json','CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json')\") as \$c) echo '  CHECK: '.\$c->name.PHP_EOL;
echo 'baris lama yang terisi: '.DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->whereNotNull('Daftar_Anggota_Json')->count().' (harapan 0 — tidak di-backfill)'.PHP_EOL;"
```

Harapan: `kolom: 2`, `CHARACTER_MAXIMUM_LENGTH` = `-1` (artinya `max`), dua CHECK terbentuk, dan 0 baris lama terisi.

- [ ] **Step 8: Buktikan CHECK-nya benar-benar menjaga tanpa menyisakan mutasi**

```bash
php artisan tinker --execute="
\$sebelum=DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi','__UJI_CHECK_JSON__')->value('Daftar_Anggota_Json');
try {
    DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi','__UJI_CHECK_JSON__')->update(['Daftar_Anggota_Json'=>'bukan json']);
    echo 'GAGAL: teks bukan-JSON diterima.'.PHP_EOL;
} catch (\Throwable \$e) {
    echo 'OK: ditolak — '.substr(\$e->getMessage(), 0, 90).PHP_EOL;
}
\$sesudah=DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi','__UJI_CHECK_JSON__')->value('Daftar_Anggota_Json');
echo 'TETAP='.((\$sebelum===\$sesudah)?'YA':'TIDAK').PHP_EOL;"
```

Sebelum menjalankan, buat satu fixture khusus `No_Transaksi='__UJI_CHECK_JSON__'` dalam `DatabaseTransactions` test atau transaksi manual yang tidak menelan state transaksi SQL Server sesudah CHECK error. Laporkan target `Web_HRIS`, tabel, predikat satu nomor transaksi, dan perkiraan satu baris. Hapus/rollback fixture sesudah verifikasi. Jangan memakai `UPDATE TOP (1)` ke baris bisnis acak. Ulangi bukti penolakan untuk `N_VISITOR_Tamu_Efektif` memakai fixture proyeksi khusus agar **kedua** CHECK pernah terlihat merah. Penjaga yang tidak pernah dilihat menolak apa pun belum terbukti ada.

- [ ] **Step 9: Tulis `docs/changes/DDL-2026-100-visitor-daftar-anggota-tamu/README.md`**

Isi wajib menurut `AGENTS.md`: change ID, database sasaran, deskripsi, objek terdampak, path DDL dan dokumentasi, checksum forward-DDL (`sha256sum`), status, pekerjaan agent, dan pekerjaan user. Bagian "Pekerjaan user" **wajib** memuat: kabari pemilik aplikasi gerbang bahwa kolom JSON ini ada dan perlu di-parse untuk menampilkan manifesnya — kolomnya aditif dan nullable sehingga gerbang lama tetap jalan, tetapi manifesnya tidak akan terlihat sampai mereka mengadopsinya.

- [ ] **Step 10: Tambahkan entri ke `database/ddl/status.json`**

Ikuti bentuk entri `DDL-2026-079-visitor-permit-api-b2b`, tetapi gunakan field nyata ledger: `artifact`, `dml_artifact`, `documentation`, `checksum_sha256`, `objek_terdampak`, `development`, `sql_tracker`, `production`, `verification`, dan `catatan`. `development.status` = `DEV_EXECUTED` dengan hasil Step 7 dan 8; `sql_tracker.status` = `TRACKER_PENDING`; `production.status` = `PENDING`; `verification.status` = `DEV_DONE`; `production_verified` = `false`.

- [ ] **Step 11: Commit**

```bash
git add database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu database/dml/DDL-2026-100-visitor-daftar-anggota-tamu docs/changes/DDL-2026-100-visitor-daftar-anggota-tamu database/ddl/status.json
git commit -m "feat(visitor): kolom daftar anggota rombongan pada tamu dan proyeksinya

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Service penyusun daftar rombongan

**Files:**
- Create: `app/Services/VISITOR/Guest/Visit/DaftarAnggotaRombongan.php`
- Test: `tests/Unit/VISITOR/Guest/Visit/DaftarAnggotaRombonganTest.php`

**Interfaces:**
- Consumes: —
- Produces:
  - `const MAKS_ANGGOTA_ROMBONGAN = 50`
  - `susun(string $namaUtama, array $anggotaLain): array` — mengembalikan daftar lengkap `[['nama'=>..., 'jenis_identitas'=>?, 'no_identitas'=>?], …]` dengan tamu utama sebagai elemen pertama; melempar `\InvalidArgumentException` bila tidak sah
  - `keJson(array $daftar): string`
  - `dariJson(?string $json): array` — `[]` bila `null` atau tidak terbaca
  - `jumlah(array $daftar): int`

> **Kenapa `tests/Unit/`, bukan `tests/Integration/`:** service ini murni, tidak menyentuh database. Menaruhnya di `Unit` berarti CI benar-benar menjalankannya — `quality.yml` hanya menjalankan `--testsuite=Unit`, sehingga aturan yang diuji di `Integration` tidak pernah dijaga pipeline.
>
> Sebelum membuat kedua file baru, nyatakan ownership: module `VISITOR`, submodule `Guest`, feature `Visit`, layer `Service` dan `Unit Test` — sesuai hasil verifikasi Task 1.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Unit\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Penyusun daftar rombongan tamu.
 *
 * Daftar ini adalah SATU-SATUNYA sumber Jumlah_Tamu. Kalau ia boleh berisi nama
 * kosong, berisi ribuan entri, atau melewatkan tamu utamanya, maka angka yang
 * dibaca papan harian, dashboard, laporan, dan aplikasi gerbang ikut salah —
 * dan salahnya tanpa satu pun galat.
 */
class DaftarAnggotaRombonganTest extends TestCase
{
    private DaftarAnggotaRombongan $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DaftarAnggotaRombongan;
    }

    public function test_tamu_utama_selalu_jadi_elemen_pertama(): void
    {
        $daftar = $this->svc->susun('Rudi Hartono', [
            ['nama' => 'Siti Aminah'],
            ['nama' => 'Budi'],
        ]);

        $this->assertCount(3, $daftar);
        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
    }

    public function test_identitas_opsional_tidak_ditulis_sebagai_kunci_kosong(): void
    {
        // Kunci bernilai null menyesatkan pembaca: ia tampak "ada tapi kosong",
        // padahal artinya identitasnya memang tidak pernah dikumpulkan.
        $daftar = $this->svc->susun('Rudi', [['nama' => 'Siti']]);

        $this->assertArrayNotHasKey('no_identitas', $daftar[1]);
        $this->assertArrayNotHasKey('jenis_identitas', $daftar[1]);
    }

    public function test_identitas_ikut_bila_diisi(): void
    {
        $daftar = $this->svc->susun('Rudi', [
            ['nama' => 'Siti', 'jenis_identitas' => 'KTP', 'no_identitas' => '3201234567890001'],
        ]);

        $this->assertSame('KTP', $daftar[1]['jenis_identitas']);
        $this->assertSame('3201234567890001', $daftar[1]['no_identitas']);
    }

    public function test_nama_dirapikan_dari_spasi_berlebih(): void
    {
        $daftar = $this->svc->susun('  Rudi Hartono  ', [['nama' => " Siti \n"]]);

        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
        $this->assertSame('Siti', $daftar[1]['nama']);
    }

    public function test_nama_kosong_ditolak_bukan_dibuang_diam_diam(): void
    {
        // Membuang barisnya diam-diam membuat pengguna mengira orangnya
        // terdaftar padahal tidak — dan tidak ada yang memberitahunya.
        $this->expectException(InvalidArgumentException::class);

        $this->svc->susun('Rudi', [['nama' => '   ']]);
    }

    public function test_tamu_utama_tanpa_nama_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->svc->susun('  ', []);
    }

    public function test_melebihi_batas_ditolak(): void
    {
        $lain = array_fill(0, DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN, ['nama' => 'Orang']);

        $this->expectException(InvalidArgumentException::class);

        // Utama + MAKS_ANGGOTA_ROMBONGAN = satu lebih banyak dari batas.
        $this->svc->susun('Rudi', $lain);
    }

    public function test_tepat_di_batas_masih_diterima(): void
    {
        $lain = array_fill(0, DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN - 1, ['nama' => 'Orang']);

        $this->assertCount(
            DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN,
            $this->svc->susun('Rudi', $lain),
        );
    }

    public function test_nama_kepanjangan_ditolak(): void
    {
        // Kolom Nama_Lengkap_Tamu varchar(150); nama yang lebih panjang akan
        // terpotong senyap di tempat lain, jadi lebih baik ditolak di sini.
        $this->expectException(InvalidArgumentException::class);

        $this->svc->susun('Rudi', [['nama' => str_repeat('a', 151)]]);
    }

    public function test_jenis_identitas_kepanjangan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->svc->susun('Rudi', [[
            'nama' => 'Siti',
            'jenis_identitas' => str_repeat('J', 21),
        ]]);
    }

    public function test_nomor_identitas_kepanjangan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->svc->susun('Rudi', [[
            'nama' => 'Siti',
            'no_identitas' => str_repeat('1', 101),
        ]]);
    }

    public function test_bolak_balik_json_tidak_mengubah_isi(): void
    {
        $daftar = $this->svc->susun('Rudi', [['nama' => 'Siti', 'jenis_identitas' => 'KTP', 'no_identitas' => '32']]);

        $this->assertSame($daftar, $this->svc->dariJson($this->svc->keJson($daftar)));
    }

    public function test_json_kosong_atau_rusak_dibaca_sebagai_daftar_kosong(): void
    {
        // Registrasi lama bernilai NULL. Pembaca harus mendapat array kosong,
        // bukan galat — daftarnya memang tidak pernah dikumpulkan.
        $this->assertSame([], $this->svc->dariJson(null));
        $this->assertSame([], $this->svc->dariJson(''));
        $this->assertSame([], $this->svc->dariJson('bukan json'));
        $this->assertSame([], $this->svc->dariJson('{"nama":"bukan array"}'));
    }

    public function test_json_array_kosong_tetap_dibaca_sebagai_array(): void
    {
        $this->assertSame([], $this->svc->dariJson('[]'));
    }

    public function test_jumlah_sama_dengan_panjang_daftar(): void
    {
        $daftar = $this->svc->susun('Rudi', [['nama' => 'Siti'], ['nama' => 'Budi']]);

        $this->assertSame(3, $this->svc->jumlah($daftar));
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Unit/VISITOR/Guest/Visit/DaftarAnggotaRombonganTest.php`
Expected: FAIL — `Class "App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan" not found`.

- [ ] **Step 3: Tulis service-nya**

```php
<?php

namespace App\Services\VISITOR\Guest\Visit;

use InvalidArgumentException;

/**
 * Penyusun daftar anggota rombongan tamu.
 *
 * Daftar ini adalah SATU-SATUNYA sumber Jumlah_Tamu. Sebelumnya jumlah diketik
 * terpisah dari namanya, dan dua angka yang ditulis terpisah pasti menyimpang.
 *
 * Murni: tidak menyentuh database, tidak menyentuh request. Itu disengaja —
 * aturannya jadi bisa diuji di suite Unit, satu-satunya suite yang benar-benar
 * dijalankan CI.
 */
class DaftarAnggotaRombongan
{
    /**
     * Batas anggota per rombongan.
     *
     * Data nyata maksimal 5, jadi 50 longgar. Tetapi tanpa batas, satu
     * permintaan bisa menempelkan ribuan nama ke kolom nvarchar(max) yang harus
     * di-parse aplikasi gerbang pada setiap penarikan.
     */
    public const MAKS_ANGGOTA_ROMBONGAN = 50;

    /** Kolom Nama_Lengkap_Tamu varchar(150); lebih panjang akan terpotong senyap. */
    private const MAKS_PANJANG_NAMA = 150;

    private const MAKS_PANJANG_JENIS_IDENTITAS = 20;

    private const MAKS_PANJANG_NO_IDENTITAS = 100;

    /**
     * Susun daftar lengkap: tamu utama lebih dulu, lalu anggota lainnya.
     *
     * @param  array<int, array<string, mixed>>  $anggotaLain
     * @return array<int, array<string, string>>
     */
    public function susun(string $namaUtama, array $anggotaLain): array
    {
        $daftar = [$this->bersihkan(['nama' => $namaUtama], 'Nama tamu utama')];

        foreach (array_values($anggotaLain) as $i => $anggota) {
            $daftar[] = $this->bersihkan(
                is_array($anggota) ? $anggota : ['nama' => $anggota],
                'Anggota ke-'.($i + 2),
            );
        }

        if (count($daftar) > self::MAKS_ANGGOTA_ROMBONGAN) {
            throw new InvalidArgumentException(
                'Jumlah anggota rombongan maksimal '.self::MAKS_ANGGOTA_ROMBONGAN.' orang, termasuk tamu utama.'
            );
        }

        return $daftar;
    }

    /** @param array<int, array<string, string>> $daftar */
    public function keJson(array $daftar): string
    {
        return json_encode(array_values($daftar), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Baca daftar dari kolom.
     *
     * NULL berarti registrasi lama yang daftarnya tidak pernah dikumpulkan —
     * itu keadaan yang sah, bukan galat. Teks rusak diperlakukan sama: pembaca
     * mendapat daftar kosong dan jatuh kembali ke Nama_Tamu, alih-alih meledak
     * di layar orang.
     *
     * @return array<int, array<string, string>>
     */
    public function dariJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $isi = json_decode($json, true);

        // Bentuk resmi wajib array JSON. Objek JSON juga di-decode sebagai
        // array asosiatif oleh PHP, tetapi bukan manifes anggota.
        if (! is_array($isi)) {
            return [];
        }

        if ($isi === []) {
            return [];
        }

        $kunci = array_keys($isi);

        return $kunci === range(0, count($isi) - 1) ? $isi : [];
    }

    /** @param array<int, array<string, string>> $daftar */
    public function jumlah(array $daftar): int
    {
        return count($daftar);
    }

    /**
     * @param  array<string, mixed>  $anggota
     * @return array<string, string>
     */
    private function bersihkan(array $anggota, string $label): array
    {
        $nama = trim((string) ($anggota['nama'] ?? ''));

        if ($nama === '') {
            // Ditolak, bukan dibuang diam-diam: membuangnya membuat pengguna
            // mengira orangnya terdaftar padahal tidak.
            throw new InvalidArgumentException($label.' wajib diisi.');
        }

        if (mb_strlen($nama) > self::MAKS_PANJANG_NAMA) {
            throw new InvalidArgumentException($label.' maksimal '.self::MAKS_PANJANG_NAMA.' karakter.');
        }

        $bersih = ['nama' => $nama];

        // Kunci identitas hanya ditulis bila benar-benar ada isinya. Kunci
        // bernilai null menyesatkan: ia tampak "ada tapi kosong", padahal
        // artinya identitasnya memang tidak pernah dikumpulkan.
        foreach ([
            'jenis_identitas' => self::MAKS_PANJANG_JENIS_IDENTITAS,
            'no_identitas' => self::MAKS_PANJANG_NO_IDENTITAS,
        ] as $kunci => $maksPanjang) {
            $nilai = trim((string) ($anggota[$kunci] ?? ''));

            if (mb_strlen($nilai) > $maksPanjang) {
                throw new InvalidArgumentException($label.' '.$kunci.' maksimal '.$maksPanjang.' karakter.');
            }

            if ($nilai !== '') {
                $bersih[$kunci] = $nilai;
            }
        }

        return $bersih;
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Unit/VISITOR/Guest/Visit/DaftarAnggotaRombonganTest.php`
Expected: PASS (15 tes).

- [ ] **Step 5: Commit**

```bash
git add app/Services/VISITOR/Guest/Visit/DaftarAnggotaRombongan.php tests/Unit/VISITOR/Guest/Visit/DaftarAnggotaRombonganTest.php
git commit -m "feat(visitor): penyusun daftar anggota rombongan tamu

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Registrasi menyimpan daftar dan menurunkan jumlahnya

**Files:**
- Create: `app/Http/Requests/VISITOR/Guest/Visit/SimpanTamuRequest.php`
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` — type-hint `store()`, insert detail (~baris 239-243)
- Test: `tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaTamuTest.php`

**Interfaces:**
- Consumes: `DaftarAnggotaRombongan::susun()`, `::keJson()`, `::jumlah()` (Task 2); kolom Task 1.
- Produces: `SimpanTamuRequest::rules(): array`; request menerima `daftar_anggota` sebagai array; `Daftar_Anggota_Json` dan `Jumlah_Tamu` terisi dari daftar.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Registrasi tamu mencatat SIAPA SAJA yang datang.
 *
 * Sebelum ini hanya satu nama dan sebuah angka yang tersimpan, sehingga 75%
 * registrasi membawa orang yang tidak tercatat namanya di mana pun.
 *
 * Yang paling penting dijaga di sini: Jumlah_Tamu diturunkan dari daftar, bukan
 * dari isian. Kalau keduanya boleh berbeda, papan harian, dashboard, laporan,
 * dan aplikasi gerbang membaca angka yang tidak sesuai daftar namanya — dan
 * tidak ada galat yang menyalakannya.
 */
class DaftarAnggotaTamuTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_daftar_tersimpan_lengkap_dengan_tamu_utama_di_depan(): void
    {
        $nomor = $this->daftarkan([
            'nama_tamu' => 'Rudi Hartono',
            'daftar_anggota' => [
                ['nama' => 'Siti Aminah', 'jenis_identitas' => 'KTP', 'no_identitas' => '3201234567890001'],
                ['nama' => 'Budi'],
            ],
        ]);

        $json = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json');

        $daftar = app(DaftarAnggotaRombongan::class)->dariJson($json);

        $this->assertCount(3, $daftar);
        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
        $this->assertSame('Siti Aminah', $daftar[1]['nama']);
        $this->assertSame('KTP', $daftar[1]['jenis_identitas']);
        $this->assertArrayNotHasKey('no_identitas', $daftar[2], 'Anggota tanpa identitas tidak boleh berkunci kosong.');
    }

    public function test_jumlah_tamu_diturunkan_dari_daftar_bukan_dari_isian(): void
    {
        // Formulir sengaja mengirim angka yang salah. Daftar yang harus menang.
        $nomor = $this->daftarkan([
            'jumlah_tamu' => 99,
            'daftar_anggota' => [['nama' => 'Siti'], ['nama' => 'Budi']],
        ]);

        $this->assertSame(
            3,
            (int) DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
                ->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'),
        );
    }

    public function test_tamu_tunggal_menghasilkan_daftar_satu_orang(): void
    {
        $nomor = $this->daftarkan(['nama_tamu' => 'Rudi Hartono', 'daftar_anggota' => []]);

        $daftar = app(DaftarAnggotaRombongan::class)->dariJson(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json')
        );

        $this->assertCount(1, $daftar);
        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
        $this->assertSame(1, (int) DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'));
    }

    public function test_anggota_bernama_kosong_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['daftar_anggota' => [['nama' => '   ']]]);
    }

    public function test_melebihi_batas_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan([
            'daftar_anggota' => array_fill(0, DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN, ['nama' => 'Orang']),
        ]);
    }

    public function test_daftar_bukan_array_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['daftar_anggota' => 'Siti, Budi']);
    }

    public function test_daftar_yang_tidak_dikirim_ditolak(): void
    {
        $payload = $this->payloadDasar();
        unset($payload['daftar_anggota']);

        $this->expectException(ValidationException::class);

        $this->daftarkanPayload($payload);
    }

    /** @param array<string,mixed> $ubah */
    private function daftarkan(array $ubah): string
    {
        return $this->daftarkanPayload(array_merge($this->payloadDasar(), $ubah));
    }

    /** @param array<string,mixed> $payload */
    private function daftarkanPayload(array $payload): string
    {
        $request = Request::create('/tamu', 'POST', $payload);
        $request->headers->set('Idempotency-Key', 'ANG-'.Str::upper(Str::random(20)));
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    /** @return array<string,mixed> */
    private function payloadDasar(): array
    {
        return [
            'nama_tamu' => 'Rudi Hartono',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'tanggal_kunjungan' => '2026-09-15',
            // Angka sengaja salah. Kontrak lama boleh masih mengirimnya, tetapi
            // backend harus mengabaikannya dan menurunkan jumlah dari daftar.
            'jumlah_tamu' => 99,
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'waktu_mulai_kunjungan' => '2026-09-15 08:00:00',
            'waktu_selesai_kunjungan' => '2026-09-15 17:00:00',
            'daftar_anggota' => [],
        ];
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaTamuTest.php`
Expected: FAIL — daftar belum disimpan, jumlah masih mengikuti input, dan request tanpa daftar belum ditolak.

- [ ] **Step 3: Pindahkan validasi `store()` ke Form Request baru**

Buat `app/Http/Requests/VISITOR/Guest/Visit/SimpanTamuRequest.php` agar kode baru mengikuti `backend-coding-standard.md`:

```php
<?php

namespace App\Http\Requests\VISITOR\Guest\Visit;

use Illuminate\Foundation\Http\FormRequest;

class SimpanTamuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi halaman dan tindakan tetap memakai middleware/policy Visitor.
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_tamu' => ['required', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'keperluan' => ['required', 'string', 'max:250'],
            'pic_tujuan' => ['required', 'string', 'max:100'],
            'tanggal_kunjungan' => ['required', 'date'],
            'waktu_mulai_kunjungan' => ['required', 'date'],
            'waktu_selesai_kunjungan' => ['required', 'date', 'after:waktu_mulai_kunjungan'],
            'instansi_asal' => ['required', 'string', 'max:150'],
            'plat_prefix' => ['nullable', 'string', 'max:5'],
            'plat_angka' => ['nullable', 'string', 'max:10'],
            'plat_suffix' => ['nullable', 'string', 'max:10'],
            'jenis_kendaraan' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'no_identitas' => ['nullable', 'string', 'max:100'],
            'jenis_identitas' => ['nullable', 'string', 'max:20'],
            // Daftar anggota WAJIB dikirim. [] sah untuk tamu tunggal; backend
            // menyisipkan tamu utama sebagai elemen pertama manifes lengkap.
            // Key wajib, tetapi [] sah untuk tamu tunggal. Backend kemudian
            // menyisipkan tamu utama sebagai elemen pertama bentuk simpan.
            'daftar_anggota' => ['present', 'array'],
            'daftar_anggota.*.nama' => ['required', 'string', 'max:150'],
            'daftar_anggota.*.jenis_identitas' => ['nullable', 'string', 'max:20'],
            'daftar_anggota.*.no_identitas' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

Ubah signature controller menjadi `store(SimpanTamuRequest $request)`, tambahkan import-nya, dan hapus blok `$request->validate([...])` lama. Jangan punya dua salinan aturan.

`jumlah_tamu` sengaja tidak punya aturan. Backend tidak lagi menerima dua sumber kebenaran; key dari klien lama boleh masih hadir tetapi nilainya **tidak pernah dipakai**. Kontrak wajib baru adalah `daftar_anggota`. Urutan rilis wajib: DDL lebih dulu, lalu backend+UI dari commit yang sama.

- [ ] **Step 4: Susun daftar dan turunkan jumlahnya di `store()`**

Tepat sebelum `DB::beginTransaction()`, sesudah validasi master data:

```php
// Daftar disusun SEBELUM transaksi: kalau isinya tidak sah, lebih baik gagal
// sekarang daripada membatalkan transaksi yang sudah menyentuh beberapa tabel.
try {
    $daftarAnggota = $this->daftarAnggota->susun(
        (string) $request->input('nama_tamu'),
        (array) $request->input('daftar_anggota'),
    );
} catch (\InvalidArgumentException $e) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        'daftar_anggota' => $e->getMessage(),
    ]);
}
```

Di `DB::table(self::T_DETAIL)->insert([...])`, ganti baris `'Jumlah_Tamu'`:

```php
// Manifes adalah SATU-SATUNYA sumber jumlah. Nilai jumlah_tamu dari request,
// bila masih dikirim klien, sengaja diabaikan.
'Jumlah_Tamu' => $this->daftarAnggota->jumlah($daftarAnggota),
'Daftar_Anggota_Json' => $this->daftarAnggota->keJson($daftarAnggota),


Tambahkan `DaftarAnggotaRombongan $daftarAnggota` ke constructor controller:

```php
public function __construct(
    protected VisitorApprovalPolicyService $visitorApprovalPolicy,
    protected VisitorEfektifService $visitorEfektif,
    protected VisitorIdempotencyService $visitorIdempotency,
    protected VisitorKehadiranQueryService $visitorKehadiran,
    protected DaftarAnggotaRombongan $daftarAnggota,
) {}
```

dengan `use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;` di kepala berkas.

- [ ] **Step 5: Sesuaikan fixture internal ke kontrak wajib baru**

Tambahkan `'daftar_anggota' => [],` pada payload tamu di:

- `tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php`;
- `tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php`;
- bagian registrasi tamu `tests/Integration/VISITOR/Workflow/Notification/VisitorSubmissionNotifiesAdminTest.php` (jangan pada payload permit).

Ini bukan kompatibilitas production client: ketiganya pemanggil test internal yang harus mengikuti kontrak wajib baru.

- [ ] **Step 6: Jalankan test baru dan pemanggil `store()`**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaTamuTest.php tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php tests/Integration/VISITOR/Workflow/Notification/VisitorSubmissionNotifiesAdminTest.php`
Expected: test roster dan fixture internal lolos. Kegagalan baseline notifikasi yang sudah dikenal dicatat terpisah, bukan dianggap regresi roster.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/VISITOR/Guest/Visit/SimpanTamuRequest.php app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaTamuTest.php tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php tests/Integration/VISITOR/Workflow/Notification/VisitorSubmissionNotifiesAdminTest.php
git commit -m "feat(visitor): registrasi tamu menyimpan daftar anggota dan menurunkan jumlahnya

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Daftar sampai ke proyeksi dan ke pembaca internal

**Files:**
- Modify: `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php` — `syncTamuEfektif()` array `$row` (~baris 153)
- Modify: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php` — `baseTamuQuery()` (~baris 216)
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` — `baseTamuQuery()` lokal (~baris 743)
- Test: `tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaProyeksiTest.php`

**Interfaces:**
- Consumes: kolom Task 1, daftar yang ditulis Task 3.
- Produces: `Daftar_Anggota_Json` tersedia di `N_VISITOR_Tamu_Efektif` dan di kedua `baseTamuQuery()`.

> **Ada DUA `baseTamuQuery()`.** Satu `protected` di `GuestRegistrationController` (dipakai riwayat tamu milik PIC), satu `public` di `VisitorPermitQueryService` (dipakai meja verifikator dan proyeksi). Menambal satu saja berarti separuh layar tidak melihat daftarnya — dan tidak ada galat yang menyalakannya, karena kolom yang tidak di-select hanya menjadi properti yang tidak ada.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Daftar rombongan harus sampai ke gerbang dan ke layar internal.
 *
 * Ada DUA baseTamuQuery() di repo ini — satu protected di GuestRegistrationController,
 * satu public di VisitorPermitQueryService. Menambal satu saja membuat separuh layar
 * tidak melihat daftarnya, tanpa satu pun galat: kolom yang tidak di-select
 * hanya menjadi properti yang tidak ada.
 */
class DaftarAnggotaProyeksiTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_daftar_sampai_ke_proyeksi_tamu(): void
    {
        $nomor = $this->buatTamuBerdaftar();

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $json = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json');
        $daftar = app(DaftarAnggotaRombongan::class)->dariJson($json);

        $this->assertCount(3, $daftar, 'Gerbang tidak menerima daftar rombongannya.');
        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
    }

    public function test_jumlah_di_proyeksi_sama_dengan_panjang_daftar(): void
    {
        $nomor = $this->buatTamuBerdaftar();

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $this->assertSame(
            3,
            (int) DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'),
        );
    }

    public function test_kedua_base_tamu_query_membawa_kolomnya(): void
    {
        $nomor = $this->buatTamuBerdaftar();

        $dariService = app(VisitorPermitQueryService::class)
            ->baseTamuQuery()->where('h.No_Transaksi', $nomor)->first();

        $controller = app(\App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController::class);
        $metode = new \ReflectionMethod($controller, 'baseTamuQuery');
        $metode->setAccessible(true);
        $dariController = $metode->invoke($controller)->where('h.No_Transaksi', $nomor)->first();

        $this->assertNotNull($dariService->Daftar_Anggota_Json, 'VisitorPermitQueryService tidak membawa kolomnya.');
        $this->assertNotNull($dariController->Daftar_Anggota_Json, 'baseTamuQuery lokal controller tidak membawa kolomnya.');
    }

    public function test_registrasi_lama_tanpa_daftar_tetap_terbaca(): void
    {
        // Data lama sengaja tidak di-backfill. Pembacanya harus tetap jalan dan
        // jumlahnya tidak boleh berubah jadi nol.
        $nomor = $this->buatTamuBerdaftar(tanpaDaftar: true);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $baris = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNull($baris->Daftar_Anggota_Json);
        $this->assertSame(4, (int) $baris->Jumlah_Tamu, 'Jumlah registrasi lama tidak boleh ikut berubah.');
        $this->assertSame([], app(DaftarAnggotaRombongan::class)->dariJson($baris->Daftar_Anggota_Json));
    }

    private function buatTamuBerdaftar(bool $tanpaDaftar = false): string
    {
        $nomor = 'VSR-ANG-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'TERJADWAL',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-15',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'ANG-'.Str::upper(Str::random(20)),
        ]);

        $daftar = app(DaftarAnggotaRombongan::class)->susun('Rudi Hartono', [
            ['nama' => 'Siti Aminah'],
            ['nama' => 'Budi'],
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Rudi Hartono',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => $tanpaDaftar ? 4 : 3,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
            'Waktu_Mulai_Kunjungan' => '2026-09-15 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-15 17:00:00',
            'Daftar_Anggota_Json' => $tanpaDaftar ? null : app(DaftarAnggotaRombongan::class)->keJson($daftar),
        ]);

        return $nomor;
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH — satu pintu setiap kali**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaProyeksiTest.php`
Expected: FAIL — proyeksi belum membawa JSON dan kedua kueri belum memilih kolomnya.

Catat kegagalan awal. Setelah Step 3 (kueri service + proyeksi), jalankan ulang: uji proyeksi harus hijau, tetapi `test_kedua_base_tamu_query_membawa_kolomnya` tetap MERAH karena kueri `protected` milik controller belum membawa kolom. Baru tambal pintu kedua pada Step 4. Ini membuktikan tes benar-benar menjaga **kedua** jalur, bukan kebetulan hijau dari satu jalur.

- [ ] **Step 3: Bawa kolom ke kueri service lalu proyeksi**

Tambahkan `'d.Daftar_Anggota_Json',` sesudah `'d.Jumlah_Tamu',` di `VisitorPermitQueryService::baseTamuQuery()`. Ini wajib lebih dulu karena `syncTamuEfektif()` mengambil `$header` dari kueri tersebut.

Lalu, di `VisitorEfektifService::syncTamuEfektif()`, pada array `$row`, tepat sesudah `'Jumlah_Tamu' => $header->Jumlah_Tamu,`:

```php
// Daftar rombongan disalin apa adanya. NULL berarti registrasi lama yang
// daftarnya tidak pernah dikumpulkan — bukan rombongan kosong.
'Daftar_Anggota_Json' => $header->Daftar_Anggota_Json ?? null,
```

- [ ] **Step 4: Bawa kolom ke kueri `protected` milik controller**

Sesudah menyaksikan uji dua pintu tetap merah, tambahkan ini sesudah `'d.Jumlah_Tamu',` di `GuestRegistrationController::baseTamuQuery()`:

```php
'd.Daftar_Anggota_Json',
```

Kedua kueri kini membawa kolomnya. `ReflectionMethod` sengaja dipakai hanya di uji untuk menyentuh metode `protected`; kode production tetap memakai endpoint `show()` seperti sebelumnya.

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaProyeksiTest.php`
Expected: PASS (4 tes).

- [ ] **Step 6: Commit**

```bash
git add app/Services/VISITOR app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php tests/Integration/VISITOR/Guest/Visit/DaftarAnggotaProyeksiTest.php
git commit -m "feat(visitor): daftar rombongan sampai ke proyeksi gerbang dan kedua kueri tamu

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Mengubah daftar lewat `update()` dengan kepemilikan terjaga

**Files:**
- Create: `app/Http/Requests/VISITOR/Guest/Visit/UbahTamuRequest.php`
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` — type-hint dan logika `update()`
- Test: `tests/Integration/VISITOR/Guest/Visit/UbahDaftarAnggotaTest.php`

**Interfaces:**
- Consumes: `DaftarAnggotaRombongan` (Task 2); sinkronisasi proyeksi yang sudah ada di `update()`.
- Produces: `UbahTamuRequest::rules(): array`; `update()` menerima `daftar_anggota`, menulis ulang daftar dan jumlahnya, lalu menyinkronkan proyeksi.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mengubah daftar rombongan harus sampai ke gerbang.
 *
 * Ini lubang yang sama persis dengan jendela jam dulu: update() dahulu mengubah
 * tabel transaksi tanpa menyentuh proyeksi, dan aplikasi palang terus membaca
 * nilai lama tanpa satu pun galat. Di sini akibatnya satpam memegang daftar
 * nama yang sudah tidak berlaku.
 */
class UbahDaftarAnggotaTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_mengubah_daftar_memperbarui_transaksi_dan_proyeksi(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['daftar_anggota' => [['nama' => 'Siti Aminah']]]);

        $svc = app(DaftarAnggotaRombongan::class);

        $transaksi = $svc->dariJson(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json')
        );
        $proyeksi = $svc->dariJson(
            DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json')
        );

        $this->assertCount(2, $transaksi);
        $this->assertSame($transaksi, $proyeksi, 'Gerbang memegang daftar yang sudah tidak berlaku.');
    }

    public function test_jumlah_ikut_diturunkan_ulang(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['daftar_anggota' => [['nama' => 'Siti'], ['nama' => 'Budi'], ['nama' => 'Andi']]]);

        $this->assertSame(
            4,
            (int) DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'),
        );
        $this->assertSame(
            4,
            (int) DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'),
        );
    }

    public function test_permintaan_tanpa_daftar_tidak_menghapus_daftar_yang_ada(): void
    {
        // update() bersifat tambal sulam: mengubah tanggal saja tidak boleh
        // diam-diam mengosongkan daftar orangnya.
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['tanggal_kunjungan' => '2026-09-20']);

        $daftar = app(DaftarAnggotaRombongan::class)->dariJson(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json')
        );

        $this->assertCount(3, $daftar, 'Daftar hilang padahal yang diubah cuma tanggalnya.');
    }

    public function test_daftar_tidak_sah_ditolak(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        [, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['daftar_anggota' => [['nama' => '  ']]]);
    }

    public function test_pic_lain_tidak_bisa_mengubah_daftar(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();
        $sebelum = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json');

        // Tidak perlu bergantung pada kode karyawan kedua di DB: identitas sesi
        // lain sudah cukup untuk membuktikan filter kepemilikan header.
        $request = Request::create('/tamu/'.$id, 'PUT', [
            'daftar_anggota' => [['nama' => 'Penyusup']],
        ]);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => 'PIC-LAIN']);

        $response = app(GuestRegistrationController::class)->update($request, (string) $id);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            $sebelum,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json'),
        );
    }

    /** @param array<string,mixed> $payload */
    private function ubah(int $id, array $payload): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/tamu/'.$id, 'PUT', $payload);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        return app(GuestRegistrationController::class)->update($request, (string) $id);
    }

    /** @return array{0:string,1:int} */
    private function buatTamuEfektif(): array
    {
        $nomor = 'VSR-UBA-'.Str::upper(Str::random(8));
        $svc = app(DaftarAnggotaRombongan::class);
        $daftar = $svc->susun('Rudi Hartono', [['nama' => 'Siti Aminah'], ['nama' => 'Budi']]);

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'TERJADWAL',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-15',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'UBA-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Rudi Hartono',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 3,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
            'Waktu_Mulai_Kunjungan' => '2026-09-15 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-15 17:00:00',
            'Daftar_Anggota_Json' => $svc->keJson($daftar),
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        return [$nomor, (int) $id];
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/UbahDaftarAnggotaTest.php`
Expected: FAIL — daftar tidak berubah dan jumlahnya tidak diturunkan ulang.

- [ ] **Step 3: Tutup kepemilikan lalu terima daftar di `update()`**

`update()` kini menyentuh manifes orang dan proyeksi gerbang; ia tidak boleh lagi mencari hanya berdasarkan ID. Tepat sebelum kueri header, resolve pemohon:

```php
$kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

if (! $kodeKaryawan) {
    return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
}
```

Lalu ubah kueri header menjadi:

```php
$header = DB::table(self::T_HEADER)
    ->where('Id_Transaksi_Tamu', $id)
    ->where('Kode_Karyawan_PIC', $kodeKaryawan)
    ->where('Flag_Aktif', 'Y')
    ->first();
```

Kembalikan 404 untuk ID milik PIC lain, bukan 403 — jangan bocorkan bahwa transaksi itu ada.

Buat `app/Http/Requests/VISITOR/Guest/Visit/UbahTamuRequest.php`:

```php
<?php

namespace App\Http\Requests\VISITOR\Guest\Visit;

use Illuminate\Foundation\Http\FormRequest;

class UbahTamuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_kunjungan' => ['nullable', 'date'],
            'waktu_mulai_kunjungan' => ['nullable', 'date'],
            'waktu_selesai_kunjungan' => ['nullable', 'date', 'after:waktu_mulai_kunjungan'],
            'daftar_anggota' => ['sometimes', 'array'],
            'daftar_anggota.*.nama' => ['required', 'string', 'max:150'],
            'daftar_anggota.*.jenis_identitas' => ['nullable', 'string', 'max:20'],
            'daftar_anggota.*.no_identitas' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

Ubah signature controller menjadi `update(UbahTamuRequest $request, string $id)`, tambahkan import-nya, lalu hapus blok `$request->validate([...])` lama. `sometimes`, bukan `present`: `update()` bersifat tambal sulam; perubahan tanggal tidak boleh mengosongkan daftar orangnya.

Sesudah `$detailUpdate` disusun, sebelum penulisannya:

```php
// Nama tamu utama diambil dari baris yang ada, bukan dari permintaan:
// update() tidak menerima nama_tamu, dan daftar harus tetap berkepala
// penanggung jawab yang sama.
if ($request->has('daftar_anggota')) {
    $namaUtama = (string) DB::table(self::T_DETAIL)
        ->where('No_Transaksi', $header->No_Transaksi)
        ->value('Nama_Lengkap_Tamu');

    try {
        $daftarBaru = $this->daftarAnggota->susun($namaUtama, (array) $request->input('daftar_anggota', []));
    } catch (\InvalidArgumentException $e) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'daftar_anggota' => $e->getMessage(),
        ]);
    }

    $detailUpdate['Daftar_Anggota_Json'] = $this->daftarAnggota->keJson($daftarBaru);
    $detailUpdate['Jumlah_Tamu'] = $this->daftarAnggota->jumlah($daftarBaru);
}
```

- [ ] **Step 4: Pertahankan jalur sinkronisasi yang sudah ada**

Jangan membuat transaction boundary baru dalam task roster. `update()` saat ini menulis header/detail lalu memanggil `syncTamuEfektif()` dan `syncPermitCoklatEfektif()` ketika ada perubahan; pertahankan alur itu agar roster mengikuti semantik update jendela yang sudah ada. Perubahan atomis lintas ketiga tabel adalah refactor use-case terpisah menurut `backend-coding-standard.md`, bukan syarat roster ini.

Pastikan `$detailUpdate['Daftar_Anggota_Json']` dan `$detailUpdate['Jumlah_Tamu']` sudah terisi **sebelum** blok penulisan existing. Blok existing lalu otomatis memanggil kedua sinkronisasi karena `$detailUpdate` tidak kosong.

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/UbahDaftarAnggotaTest.php tests/Integration/VISITOR/Guest/Visit/UbahJendelaTersinkronTest.php`
Expected: PASS (5 tes roster + 4 tes jendela).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/VISITOR/Guest/Visit/UbahTamuRequest.php app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php tests/Integration/VISITOR/Guest/Visit/UbahDaftarAnggotaTest.php
git commit -m "feat(visitor): perubahan daftar rombongan ikut tersinkron ke gerbang

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Formulir registrasi — isian daftar anggota

> Saat mengeksekusi task UI ini, gunakan skill `frontend-design` sebelum mengubah komponen. Pertahankan bahasa visual HCIS dan komponen resmi yang sudah dipakai form; task ini mengubah isi, bukan merombak halaman.

**Files:**
- Modify: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue`
- Test: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`

**Interfaces:**
- Consumes: kontrak backend Task 3 (`daftar_anggota` array).
- Produces: `buildPayload()` mengirim `daftar_anggota` dan `jumlah_tamu` kompatibilitas; `jumlahOrang` menjadi nilai turunan dari `form.anggota_lain`.

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan `describe` berikut ke `TamuForm.test.js`. Berkas itu memakai `shallowMount(TamuForm)` polos, mengisi state lewat `Object.assign(wrapper.vm.form, {...})`, dan memeriksa kontrak lewat `wrapper.vm.buildPayload()` — ikuti pola itu, jangan membuat helper baru.

```js
describe('TamuForm daftar anggota rombongan', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({
            data: { success: true, result: { plat_codes: ['B'], vehicle_types: [], keperluan: [], jenis_identitas: ['KTP'] } },
        });
    });

    it('mengirim daftar anggota selain tamu utama', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [
                { nama: 'Siti Aminah', jenis_identitas: 'KTP', no_identitas: '3201' },
                { nama: 'Budi', jenis_identitas: '', no_identitas: '' },
            ],
        });
        await wrapper.vm.$nextTick();

        const payload = wrapper.vm.buildPayload();

        expect(payload.daftar_anggota).toHaveLength(2);
        expect(payload.daftar_anggota[0].nama).toBe('Siti Aminah');
        expect(payload.daftar_anggota[1].nama).toBe('Budi');
    });

    it('membuang kunci identitas kosong dari payload', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [{ nama: 'Budi', jenis_identitas: '  ', no_identitas: '' }],
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.buildPayload().daftar_anggota[0]).toEqual({ nama: 'Budi' });
    });

    it('jumlah orang adalah nilai turunan, bukan isian', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [{ nama: 'Siti' }, { nama: 'Budi' }],
        });
        await wrapper.vm.$nextTick();

        // Tamu utama + dua anggota.
        expect(wrapper.vm.jumlahOrang).toBe(3);
        expect(wrapper.vm.buildPayload().jumlah_tamu).toBe(3);
    });

    it('tamu tunggal tetap sah dengan daftar kosong', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, { nama_tamu: 'Rudi Hartono', anggota_lain: [] });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jumlahOrang).toBe(1);
        expect(wrapper.vm.buildPayload().daftar_anggota).toEqual([]);
    });

    it('anggota bernama kosong membuat form belum sah', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [{ nama: '   ' }],
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.daftarAnggotaValid).toBe(false);
    });

    it('membatasi rombongan menjadi 50 orang termasuk tamu utama', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: Array.from({ length: 49 }, (_, i) => ({ nama: `Anggota ${i + 1}` })),
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jumlahOrang).toBe(50);
        expect(wrapper.vm.bolehTambahAnggota).toBe(false);
        wrapper.vm.tambahAnggota();
        expect(wrapper.vm.form.anggota_lain).toHaveLength(49);
    });

    it('tidak lagi merender isian jumlah yang bisa diketik', () => {
        const source = readFileSync(path.resolve(__dirname, 'TamuForm.vue'), 'utf8');

        expect(source).not.toContain('v-model="form.jumlah_tamu"');
        expect(source).toContain('jumlahOrang');
        expect(source).toContain('anggota_lain');
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`
Expected: FAIL — `jumlahOrang` dan `anggota_lain` belum ada.

- [ ] **Step 3: Ganti isian jumlah dengan daftar anggota di template**

Ganti blok `HcisInputNumber` untuk `form.jumlah_tamu` (sekitar baris 97-106) menjadi tampilan turunan:

```vue
<HcisField class="visitor-form-grid__count" id="guest-count" label="Jumlah orang">
    <div class="visitor-guest-form__jumlah-turunan font-tabular">
        {{ jumlahOrang }} orang
        <span class="visitor-guest-form__jumlah-catatan">dihitung dari daftar di bawah</span>
    </div>
</HcisField>
```

Lalu tambahkan daftar anggota di kartu yang sama, **sesudah** penutup `.visitor-form-grid--transport` dan sebelum alert "Datang tanpa kendaraan". Jangan menaruhnya di dalam grid transport dua kolom karena satu baris roster punya layout dan responsivitas sendiri:

```vue
<div class="visitor-guest-form__anggota">
    <p class="visitor-guest-form__anggota-judul">
        Anggota rombongan lain <span>(selain {{ form.nama_tamu || 'tamu utama' }})</span>
    </p>

    <div v-for="(anggota, i) in form.anggota_lain" :key="i" class="visitor-guest-form__anggota-baris">
        <HcisField
            :id="'anggota-nama-' + i"
            v-model="anggota.nama"
            label="Nama"
            placeholder="Nama lengkap"
            required
            :error="anggota.nama !== undefined && String(anggota.nama).trim() === '' ? 'Nama wajib diisi.' : ''"
        />
        <HcisIdentityInput
            :id="'anggota-identitas-' + i"
            v-model="anggota.no_identitas"
            :type-value="anggota.jenis_identitas"
            :type-options="identityOptions"
            label="Identitas anggota"
            hint="Opsional."
            @update:type-value="anggota.jenis_identitas = $event"
        />
        <HcisButton
            label="Hapus"
            leading-icon="trash"
            size="xs"
            variant="danger-quiet"
            @click="hapusAnggota(i)"
        />
    </div>

    <HcisButton
        label="Tambah anggota"
        leading-icon="person-plus-fill"
        size="sm"
        variant="secondary"
        :disabled="!bolehTambahAnggota"
        @click="tambahAnggota"
    />
</div>
```

- [ ] **Step 4: Tambahkan state, computed, dan metodenya**

Di `defaultForm()`, ganti `jumlah_tamu: 1,` menjadi:

```js
// Jumlah orang tidak lagi diketik; ia diturunkan dari daftar ini.
anggota_lain: [],
```

Di `computed`:

```js
jumlahOrang() {
    // Tamu utama selalu terhitung, lalu setiap anggota lain.
    return 1 + (this.form.anggota_lain?.length ?? 0);
},
daftarAnggotaValid() {
    return (this.form.anggota_lain ?? []).every(
        (a) => String(a?.nama ?? '').trim() !== '',
    );
},
bolehTambahAnggota() {
    // 50 termasuk tamu utama, sejalan dengan MAKS_ANGGOTA_ROMBONGAN di backend.
    return this.jumlahOrang < 50;
},
```

Di `methods`:

```js
tambahAnggota() {
    if (!this.bolehTambahAnggota) return;

    this.form.anggota_lain.push({ nama: '', jenis_identitas: '', no_identitas: '' });
},
hapusAnggota(i) {
    this.form.anggota_lain.splice(i, 1);
},
```

Di `buildPayload()`, ganti `jumlah_tamu: this.form.jumlah_tamu,`:

```js
jumlah_tamu: this.jumlahOrang,
// Hanya anggota SELAIN tamu utama; backend yang menaruh tamu utama di depan.
// Kunci identitas yang kosong dibuang di sini supaya tidak tersimpan sebagai
// kunci bernilai kosong yang menyesatkan pembaca.
daftar_anggota: (this.form.anggota_lain ?? []).map((a) => {
    const bersih = { nama: String(a.nama ?? '').trim() };

    if (String(a.jenis_identitas ?? '').trim() !== '') bersih.jenis_identitas = String(a.jenis_identitas).trim();
    if (String(a.no_identitas ?? '').trim() !== '') bersih.no_identitas = String(a.no_identitas).trim();

    return bersih;
}),
```

Di daftar kelengkapan langkah (sekitar baris 701), ganti entri `Jumlah rombongan`:

```js
{ label: 'Daftar rombongan', complete: this.daftarAnggotaValid },
```

Di **dua** ringkasan template (sidebar sekitar baris 272 dan mobile sheet sekitar baris 392), ganti `{{ form.jumlah_tamu }} orang` menjadi `{{ jumlahOrang }} orang`. `confirmReceiptItems` tetap membaca `p.jumlah_tamu` karena payload sudah berisi nilai turunannya.

Hapus metode `clampJumlah()` beserta pemanggilnya. Hapus `HcisInputNumber` dari import dan registrasi `components` karena pemakai terakhirnya hilang. Sesuaikan uji komposisi lama: ganti ekspektasi `expect(source).toContain('<HcisInputNumber')` menjadi ekspektasi negatif dan cek tampilan `jumlahOrang`.

Tambahkan gaya ber-scope di blok `<style scoped>`:

```css
.visitor-guest-form__jumlah-turunan {
    display: flex;
    flex-direction: column;
    font-weight: 600;
    color: #0f172a;
}

.visitor-guest-form__jumlah-catatan {
    font-size: 0.6875rem;
    font-weight: 400;
    color: var(--hcis-text-muted, #64748b);
}

.visitor-guest-form__anggota {
    margin-top: 1rem;
}

.visitor-guest-form__anggota-judul {
    margin: 0 0 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
}

.visitor-guest-form__anggota-judul span {
    font-weight: 400;
    color: var(--hcis-text-muted, #64748b);
}

.visitor-guest-form__anggota-baris {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.5fr) auto;
    gap: 0.5rem;
    align-items: end;
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .visitor-guest-form__anggota-baris {
        grid-template-columns: minmax(0, 1fr);
    }
}
```

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`
Expected: PASS. Uji lama di berkas itu yang mengisi `jumlah_tamu` perlu ikut disesuaikan ke `anggota_lain`; sesuaikan alih-alih menghapusnya.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js
git commit -m "feat(visitor): isian daftar anggota rombongan pada formulir tamu

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Daftar terlihat di riwayat tamu dan meja verifikator

**Files:**
- Create: `resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.js`
- Modify: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue`
- Modify: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue`
- Test: `resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.test.js`
- Test: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js`
- Test: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.test.js`

**Interfaces:**
- Consumes: `Daftar_Anggota_Json` dari kedua `baseTamuQuery()` (Task 4).
- Produces: `bacaDaftarAnggota(json): array` sebagai pembaca bersama; daftar nama tampil di detail riwayat tamu dan modal review verifikator.

- [ ] **Step 1: Tulis tes pembaca JSON yang gagal**

Buat `resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.test.js`:

```js
import { describe, expect, it } from 'vitest';
import { bacaDaftarAnggota } from './daftarAnggota';

describe('bacaDaftarAnggota', () => {
    it('menerjemahkan JSON array menjadi daftar nama', () => {
        const daftar = bacaDaftarAnggota(
            '[{"nama":"Rudi"},{"nama":"Siti","jenis_identitas":"KTP","no_identitas":"32"}]',
        );

        expect(daftar).toHaveLength(2);
        expect(daftar[1].nama).toBe('Siti');
    });

    it.each([null, '', 'bukan json', '{"nama":"bukan array"}'])(
        'mengembalikan daftar kosong untuk nilai lama atau rusak: %s',
        (json) => expect(bacaDaftarAnggota(json)).toEqual([]),
    );
});
```

- [ ] **Step 2: Jalankan tes pembaca, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.test.js`
Expected: FAIL — modul `./daftarAnggota` belum ada.

- [ ] **Step 3: Buat satu pembaca bersama**

Buat `resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.js`:

```js
/**
 * Baca manifes rombongan dari kolom JSON.
 *
 * Registrasi lama bernilai null karena daftarnya memang tidak pernah
 * dikumpulkan. Teks rusak atau objek JSON diperlakukan sama: daftar kosong,
 * bukan layar yang meledak.
 */
export function bacaDaftarAnggota(json) {
    if (!json) return [];

    try {
        const isi = JSON.parse(json);

        return Array.isArray(isi) ? isi : [];
    } catch {
        return [];
    }
}
```

Jalankan ulang tes pembaca. Expected: PASS (2 tes/data-driven cases).

- [ ] **Step 4: Tulis penjaga kedua layar, lalu saksikan MERAH**

Tambahkan ke `TamuHistory.test.js`:

```js
it('detail riwayat menampilkan daftar anggota dengan fallback data lama', () => {
    const source = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue'),
        'utf8',
    );

    expect(source).toContain("from '../daftarAnggota'");
    expect(source).toContain('bacaDaftarAnggota(tamuData.Daftar_Anggota_Json)');
    expect(source).toContain('daftar nama tidak dikumpulkan');
});
```

Buat `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.test.js` mengikuti gaya `PermitReviewModal.test.js`:

```js
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(
    path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue'),
    'utf8',
);

describe('TamuReviewModal daftar anggota rombongan', () => {
    it('menampilkan manifes sebelum verifikator memutuskan', () => {
        // Approver perlu tahu SIAPA yang akan masuk, bukan hanya berapa orang.
        expect(source).toContain("from '../../../Guest/Visit/daftarAnggota'");
        expect(source).toContain('bacaDaftarAnggota(tamu.Daftar_Anggota_Json)');
        expect(source).toContain('daftar nama tidak dikumpulkan');
    });
});
```

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.test.js`
Expected: kedua penjaga MERAH karena komponen belum memakai helper dan belum merender manifes.

- [ ] **Step 5: Impor helper ke kedua komponen**

Di `TamuHistory.vue`:

```js
import { bacaDaftarAnggota } from '../daftarAnggota';
```

Lalu expose sebagai method Options API tanpa menyalin implementasi:

```js
methods: {
    bacaDaftarAnggota,
    // ...method yang sudah ada
},
```

Di `TamuReviewModal.vue`:

```js
import { bacaDaftarAnggota } from '../../../Guest/Visit/daftarAnggota';
```

Lalu tambahkan `bacaDaftarAnggota,` pada blok `methods` yang sudah ada. Satu helper mencegah dua parser pelan-pelan berbeda perilaku.

- [ ] **Step 6: Tampilkan daftarnya**

Di detail `TamuHistory.vue`, pada grid spesifikasi yang sudah ada, tambahkan satu tile:

```vue
<div class="tamu-spec-tile">
    <span class="tamu-spec-tile__label">Rombongan</span>
    <div class="tamu-spec-tile__val text-slate-700 mt-1">
        <template v-if="bacaDaftarAnggota(tamuData.Daftar_Anggota_Json).length">
            <div v-for="(a, i) in bacaDaftarAnggota(tamuData.Daftar_Anggota_Json)" :key="i">
                {{ i + 1 }}. {{ a.nama }}<span v-if="a.no_identitas"> — {{ a.jenis_identitas || 'ID' }} {{ a.no_identitas }}</span>
            </div>
        </template>
        <span v-else>{{ tamuData.Jumlah_Tamu || 1 }} orang (daftar nama tidak dikumpulkan)</span>
    </div>
</div>
```

Di `TamuReviewModal.vue`, tambahkan tile full-width yang setara sesudah tile "Jumlah Tamu", memakai `tamu.Daftar_Anggota_Json`:

```vue
<div class="tamu-spec-tile tamu-spec-tile--full">
    <span class="tamu-spec-tile__label">Rombongan</span>
    <div class="tamu-spec-tile__val text-slate-700 mt-1">
        <template v-if="bacaDaftarAnggota(tamu.Daftar_Anggota_Json).length">
            <div v-for="(a, i) in bacaDaftarAnggota(tamu.Daftar_Anggota_Json)" :key="i">
                {{ i + 1 }}. {{ a.nama }}<span v-if="a.no_identitas"> — {{ a.jenis_identitas || 'ID' }} {{ a.no_identitas }}</span>
            </div>
        </template>
        <span v-else>{{ tamu.Jumlah_Tamu || 1 }} orang (daftar nama tidak dikumpulkan)</span>
    </div>
</div>
```

Teks fallback penting: registrasi lama menampilkan jumlah apa adanya beserta alasannya, bukan daftar kosong yang tampak seperti tidak ada orang. Jangan menampilkan roster pada kartu permit COKLAT; scope hanya dua pembaca tamu ini.

- [ ] **Step 7: Jalankan tes, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.test.js resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.test.js`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.js resources/js/Pages/VISITOR/Guest/Visit/daftarAnggota.test.js resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.test.js
git commit -m "feat(visitor): daftar rombongan tampil di riwayat tamu dan meja verifikator

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Verifikasi Akhir

- [ ] `php artisan test tests/Unit/VISITOR` hijau — ini yang benar-benar dijalankan CI.
- [ ] `php artisan test tests/Integration/VISITOR tests/Feature/VISITOR` — kegagalan yang tersisa harus **sama persis** dengan baseline `master` (10 pre-existing: 6 `VerifikatorDecisionNotifiesRequesterTest`, 2 `VisitorSubmissionNotifiesAdminTest`, 2 `PermitEfektifFlagBatalTest`). Apa pun di luar itu adalah regresi dari pekerjaan ini.
- [ ] `npx vitest run resources/js/Pages/VISITOR` hijau.
- [ ] `./vendor/bin/pint --test $(git diff --name-only origin/master...HEAD | grep '\.php$')` bersih.
- [ ] `composer dump-autoload -o --strict-psr`, `php scripts/verify-references.php --max=0`, `php scripts/verify-frontend-imports.php`, dan `php scripts/verify-inertia-pages.php` keluar 0; file baru backend/frontend harus lolos structural gates.
- [ ] `node .github/scripts/penjaga-perubahan-db.mjs --base origin/master --head HEAD` dan `node .github/scripts/gerbang-rilis.mjs --target master --base origin/master --head HEAD` keduanya keluar 0.
- [ ] `database/ddl/status.json` memuat `DDL-2026-100` dengan checksum yang cocok terhadap forward SQL yang benar-benar dijalankan.
- [ ] Laporan dua bagian sesuai `AGENTS.md`: **Agent completed** dan **User tasks**.
- [ ] **Pekerjaan user:** rekam `DDL-2026-100` di SQL Tracker lalu eksekusi ke production; dan kabari pemilik aplikasi gerbang bahwa `Daftar_Anggota_Json` ada dan perlu di-parse untuk menampilkan manifes rombongan — kolomnya aditif dan nullable sehingga gerbang lama tetap berjalan, tetapi manifesnya tidak akan terlihat sampai mereka mengadopsinya.
