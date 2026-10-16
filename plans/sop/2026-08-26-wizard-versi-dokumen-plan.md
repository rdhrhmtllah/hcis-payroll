# Wizard Versi Dokumen SOP — rencana implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB — gunakan
> `superpowers:subagent-driven-development` (disarankan) atau
> `superpowers:executing-plans` untuk mengerjakan rencana ini tugas demi tugas.
> Langkah memakai checkbox (`- [ ]`) sebagai penanda kemajuan.

**Tujuan:** Mengubah pendefinisian versi dokumen SOP dari formulir sesak di dalam
modal menjadi wizard berlangkah di halaman sendiri, dengan langkah aktif
ditentukan server sehingga layar berhenti melompat meninggalkan pekerjaan admin.

**Arsitektur:** Server jadi satu-satunya penentu. `SopTahapVersiService`
mendapat `alurVersi()` yang mengirim `Langkah_Aktif`, `Terkunci`, `Kurang`,
`Aksi_Utama`, dan `Aksi_Lain`; Vue murni menggambar. Formulir pindah ke halaman
Inertia baru yang memakai ulang gerbang `menuSopDokumenPage`, dipecah jadi
cangkang + rel langkah + empat komponen langkah. Tahap `APPROVED` dihidupkan
kembali di **langkah 4** supaya langkah Pemberkasan bisa dimasuki tanpa
menerbitkan lebih dulu.

**Tumpukan:** Laravel 11 · SQL Server (`Web_HRIS` dev) · Inertia + Vue 3 Options
API · kit `Hcis*` · PHPUnit · Vitest · Hashids koneksi `custom`

**Spec:** `plans/sop/2026-08-26-wizard-versi-dokumen-design.md`

## Status pelaksanaan (2026-08-26)

Task 1–16 **sudah dikerjakan dalam sesi yang sama**; rencana ini disimpan
sebagai catatan urutan dan alasan, bukan sebagai pekerjaan yang menunggu.

| Task | Keadaan |
|---|---|
| 1 · DML-047 reset data | **DEV_EXECUTED** — 4.135 baris terhapus, master utuh |
| 2 · DML-048 tahap Disahkan | **DEV_EXECUTED** — APPROVED aktif di langkah 4 |
| 3–6 · lapisan server | ditulis; `php -l` bersih |
| 7–16 · rute & layar | ditulis; `node --check` bersih untuk seluruh blok script |
| **17 · verifikasi menyeluruh** | **BELUM** — menunggu ACC |

Yang **belum pernah dijalankan**: `php artisan test`, `npx vitest`,
`npm run build`, dan susur manual di browser. Jangan mengklaim lulus sebelum
ketiganya jalan.

Dua penyimpangan dari rencana, keduanya disengaja:

- **`Lengkap` tidak jadi dibuang** dari `SopTahapVersiService` — ia fakta yang
  jujur dan dipakai kolom ringkasan; yang salah hanya pemakaiannya untuk
  memilih langkah. Dua uji lama yang menguncinya tetap berlaku.
- **Penanda "diubah" per kartu dikembalikan** ke wizard lewat prop `berubah`.
  Rencana awal menggantinya dengan satu lencana "belum disimpan" — itu
  pengurangan fungsi yang tak diminta siapa pun.

## Batasan global

- **Jangan jalankan `php artisan test`, `npx vitest`, atau `npm run build`
  sampai user memberi ACC.** Tulis kode dan berkas uji sampai selesai, lalu
  laporkan apa yang belum pernah dijalankan. HMR user sudah hidup, jadi build
  tak dibutuhkan untuk melihat perubahan.
- Target database: **`Web_HRIS` development saja**. `TRUNCATE`, `DROP`, dan
  migrasi dilarang dijalankan agen (`AGENTS.md`). DML hanya `DELETE`/`UPDATE`/
  `INSERT` berpredikat, dengan jumlah baris terduga disebut lebih dulu.
- Artefak data: `database/dml/<change-id>/data-change.sql` + dokumentasi
  `docs/changes/<change-id>/README.md`. Nama direktori **wajib** berawalan
  `DML-` atau `DDL-`, kalau tidak `.gitignore:128` membuangnya diam-diam.
- Kosakata layar: **Langkah 4 bernama "Pemberkasan"**, bukan "Terbit & Sebar".
- Struktur modul: seluruh berkas baru di bawah `LMS/SOP/Governance/` sesuai
  `docs/architecture/module-registry.md`. Tak ada grup `SOP`; SOP adalah
  submodul `LMS`.
- Kontrak lama `Tujuan` dan `Syarat_Kini` **tak boleh dibuang** — Builder
  membacanya.
- `Lengkap` per langkah **tak berubah artinya** (fakta berbasis isi, dipakai
  kolom ringkasan). Dua uji yang menguncinya harus tetap hijau.

---

## Peta berkas

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/LMS/SOP/SopTahapVersiService.php` | **ubah** — `alurVersi()`, `langkahBerjalan()`, `aksiUtama()`, `aksiLain()` |
| `app/Services/LMS/SOP/SopDokumenService.php` | **ubah** — penjaga tahap pada `terbitkanVersi()` |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` | **ubah** — `langkahVersi()` memakai `alurVersi()`; buang validasi `Tanggal_Kadaluarsa` |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php` | **baru** — hanya `halaman()` |
| `routes/LMS/SOP/Governance/SopGovernance.php` | **ubah** — satu rute halaman |
| `resources/js/.../Admin/SopVersiWizard.vue` | **baru** — cangkang halaman + kaki aksi |
| `resources/js/.../Admin/versi/useVersiAlur.js` | **baru** — satu-satunya pemuat alur |
| `resources/js/.../Admin/versi/VersiStepper.vue` | **baru** — rel langkah + kunci |
| `resources/js/.../Admin/versi/VersiRingkasan.vue` | **baru** — kolom kiri |
| `resources/js/.../Admin/versi/LangkahPengajuan.vue` | **baru** |
| `resources/js/.../Admin/versi/LangkahReview.vue` | **baru** |
| `resources/js/.../Admin/versi/LangkahPengesahan.vue` | **baru** |
| `resources/js/.../Admin/versi/LangkahPemberkasan.vue` | **baru** |
| `resources/js/.../Shared/components/ui/SopDokumenVersiModal.vue` | **ubah** — layar form dibuang, tinggal riwayat |
| `database/dml/DML-2026-047-reset-data-sop/data-change.sql` | **baru** |
| `database/dml/DML-2026-048-approved-langkah-pemberkasan/data-change.sql` | **baru** |

Urutan wajib: **Task 1 → 2** (data) sebelum **Task 3-8** (server) sebelum
**Task 9-17** (layar). Task 2 mengandaikan tabel versi sudah kosong.

---

## Task 1: DML-2026-047 — bersihkan paket & dokumen SOP

**Berkas:**
- Buat: `database/dml/DML-2026-047-reset-data-sop/data-change.sql`
- Buat: `docs/changes/DML-2026-047-reset-data-sop/README.md`

**Antarmuka:**
- Menghasilkan: modul SOP kosong — nol baris di `N_SOP_Dokumen`,
  `N_SOP_Item_Versi`, `N_SOP_Master_Paket`, dan seluruh jejak turunannya. Task 2
  bersandar pada `N_SOP_Item_Versi` kosong.

- [ ] **Langkah 1: Hitung baseline dan bandingkan dengan spec**

```bash
php artisan tinker --execute="
\$t=['N_SOP_Progress','N_SOP_Absensi','N_SOP_Kepatuhan_Riwayat','N_SOP_Coverage_Rollup',
'N_SOP_Penjadwalan_Detail','N_SOP_Penjadwalan_Integrasi','N_SOP_Penjadwalan','N_SOP_Favorit',
'N_SOP_Paket_Versi_Item','N_SOP_Paket_Versi','N_SOP_Paket_Item','N_SOP_Paket_Section',
'N_SOP_Paket_Draft_Item_Versi','N_SOP_Paket_Draft_Item','N_SOP_Paket_Draft_Section','N_SOP_Paket_Draft',
'N_SOP_Master_Paket','N_SOP_Item_Versi_Review_Lampiran','N_SOP_Item_Versi_Review',
'N_SOP_Item_Versi_Pengesah','N_SOP_Item_Versi_Berkas','N_SOP_Item_Versi','N_SOP_Dokumen','N_SOP_Log'];
foreach(\$t as \$x){ echo str_pad(\$x,38).\DB::table(\$x)->count().PHP_EOL; }"
```

Bandingkan dengan tabel di spec §6.1. **Angka yang meleset jauh = berhenti dan
lapor**, jangan lanjut. Data dev berubah antar hari; selisih kecil wajar, selisih
besar berarti ada yang salah paham soal cakupan.

- [ ] **Langkah 2: Tulis skrip DML**

`database/dml/DML-2026-047-reset-data-sop/data-change.sql`:

```sql
/*  DML-2026-047 — Reset data paket & dokumen SOP
    Target   : Web_HRIS (DEVELOPMENT)
    Maksud   : mengosongkan modul SOP agar alur wizard versi yang baru diisi
               dari data bersih. Master TIDAK disentuh.
    Catatan  : DELETE berpredikat, bukan TRUNCATE. Urut anak -> induk.
*/
SET NOCOUNT ON;
BEGIN TRAN;

DECLARE @sebelum TABLE (Tabel varchar(64), Baris int);
INSERT INTO @sebelum VALUES
 ('N_SOP_Progress',(SELECT COUNT(*) FROM N_SOP_Progress)),
 ('N_SOP_Item_Versi',(SELECT COUNT(*) FROM N_SOP_Item_Versi)),
 ('N_SOP_Dokumen',(SELECT COUNT(*) FROM N_SOP_Dokumen)),
 ('N_SOP_Master_Paket',(SELECT COUNT(*) FROM N_SOP_Master_Paket));

-- 1..8  jejak turunan penugasan
DELETE FROM N_SOP_Progress                 WHERE 1=1;
DELETE FROM N_SOP_Absensi                  WHERE 1=1;
DELETE FROM N_SOP_Kepatuhan_Riwayat        WHERE 1=1;
DELETE FROM N_SOP_Coverage_Rollup          WHERE 1=1;
DELETE FROM N_SOP_Penjadwalan_Detail       WHERE 1=1;
DELETE FROM N_SOP_Penjadwalan_Integrasi    WHERE 1=1;
DELETE FROM N_SOP_Penjadwalan              WHERE 1=1;
DELETE FROM N_SOP_Favorit                  WHERE 1=1;

-- 9..17 paket terbit dan draftnya
DELETE FROM N_SOP_Paket_Versi_Item         WHERE 1=1;
DELETE FROM N_SOP_Paket_Versi              WHERE 1=1;
DELETE FROM N_SOP_Paket_Item               WHERE 1=1;
DELETE FROM N_SOP_Paket_Section            WHERE 1=1;
DELETE FROM N_SOP_Paket_Draft_Item_Versi   WHERE 1=1;
DELETE FROM N_SOP_Paket_Draft_Item         WHERE 1=1;
DELETE FROM N_SOP_Paket_Draft_Section      WHERE 1=1;
DELETE FROM N_SOP_Paket_Draft              WHERE 1=1;
DELETE FROM N_SOP_Master_Paket             WHERE 1=1;

-- 18..23 dokumen dan versinya
DELETE FROM N_SOP_Item_Versi_Review_Lampiran WHERE 1=1;
DELETE FROM N_SOP_Item_Versi_Review          WHERE 1=1;
DELETE FROM N_SOP_Item_Versi_Pengesah        WHERE 1=1;
DELETE FROM N_SOP_Item_Versi_Berkas          WHERE 1=1;
DELETE FROM N_SOP_Item_Versi                 WHERE 1=1;
DELETE FROM N_SOP_Dokumen                    WHERE 1=1;

-- 24 jejak audit modul; tak ada lagi yang dirujuknya
DELETE FROM N_SOP_Log                        WHERE 1=1;

/* Penjaga: master WAJIB utuh. Nol baris di sini berarti cakupan meleset. */
IF (SELECT COUNT(*) FROM N_SOP_Master_Tahap_Versi) = 0
   OR (SELECT COUNT(*) FROM N_SOP_Master_Jenis_Dokumen) = 0
   OR (SELECT COUNT(*) FROM N_SOP_Kategori) = 0
BEGIN
    ROLLBACK TRAN;
    THROW 50047, 'Master SOP ikut terhapus — dibatalkan.', 1;
END

SELECT s.Tabel, s.Baris AS Sebelum, 0 AS Sesudah FROM @sebelum s;
COMMIT TRAN;
```

- [ ] **Langkah 3: Tulis catatan perubahan**

`docs/changes/DML-2026-047-reset-data-sop/README.md` — memuat tabel wajib
`AGENTS.md`: Change ID, jenis, target development, deskripsi, objek terdampak,
seluruh path artefak, status `DEV_PENDING`, pekerjaan agen, dan sisa pekerjaan
user. Sertakan tabel baris hasil Langkah 1 sebagai baseline terverifikasi, dan
daftar tabel yang **sengaja tidak disentuh**.

- [ ] **Langkah 4: Commit — belum dieksekusi**

```bash
git add database/dml/DML-2026-047-reset-data-sop docs/changes/DML-2026-047-reset-data-sop
git commit -m "chore(sop): artefak DML-2026-047 reset data paket & dokumen"
```

- [ ] **Langkah 5: BERHENTI — minta konfirmasi eksekusi**

Tampilkan cakupan dan jumlah baris ke user, sebut database targetnya, tunggu izin
eksplisit. Baru sesudah itu jalankan ke development. Sesudah eksekusi, ulangi
hitungan Langkah 1 dan pastikan seluruhnya 0, lalu ubah status di README menjadi
`DEV_EXECUTED` / `TRACKER_PENDING`.

---

## Task 2: DML-2026-048 — hidupkan tahap Disahkan di langkah 4

**Berkas:**
- Buat: `database/dml/DML-2026-048-approved-langkah-pemberkasan/data-change.sql`
- Buat: `docs/changes/DML-2026-048-approved-langkah-pemberkasan/README.md`

**Antarmuka:**
- Mengonsumsi: `N_SOP_Item_Versi` kosong (Task 1).
- Menghasilkan: `APPROVED` aktif dengan `Langkah=4`, `Kelompok='TERBIT'`; transisi
  `APPROVAL→APPROVED`, `APPROVED→FILING_REGISTERED`, `APPROVED→APPROVAL`,
  `APPROVED→CANCEL` aktif; `APPROVAL→FILING_REGISTERED` mati. Task 4 dan 6
  bersandar pada susunan ini.

- [ ] **Langkah 1: Tulis skrip DML**

```sql
/*  DML-2026-048 — Tahap Disahkan kembali hidup, di langkah 4
    Target : Web_HRIS (DEVELOPMENT)
    Alasan : dengan langkah depan terkunci, langkah 4 (Pemberkasan) tak akan
             pernah terbuka bila satu-satunya jalan maju dari Approval adalah
             menerbitkan — sedangkan berkas final justru diisi DI langkah 4.
             APPROVED dipetakan ke langkah 4, BUKAN langkah 3 seperti sebelum
             DML-2026-042, sehingga tetap satu tombol lanjut per langkah dan
             keluhan klik-ganda 042 terjawab.
*/
SET NOCOUNT ON;
BEGIN TRAN;

/* Penjaga urutan: 048 mengandaikan 047 sudah jalan. Memindah tahap saat masih
   ada versi hidup berarti memigrasi keadaan tanpa rencana migrasi. */
IF (SELECT COUNT(*) FROM N_SOP_Item_Versi) > 0
BEGIN
    ROLLBACK TRAN;
    THROW 50048, 'Masih ada versi. Jalankan DML-2026-047 lebih dulu.', 1;
END

UPDATE N_SOP_Master_Tahap_Versi
   SET Flag_Aktif = 'Y',
       Langkah    = 4,
       Kelompok   = 'TERBIT',
       Nama_Tahap = 'Pemberkasan'
 WHERE Kode_Tahap = 'APPROVED';          -- 1 baris

DECLARE @approval int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap='APPROVAL');
DECLARE @approved int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap='APPROVED');
DECLARE @filing   int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap='FILING_REGISTERED');
DECLARE @cancel   int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap='CANCEL');

-- Aktifkan yang barisnya sudah ada (Flag_Aktif='T')
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif='Y'
 WHERE Dari_Tahap_Id=@approval AND Ke_Tahap_Id=@approved;      -- 1 baris
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif='Y'
 WHERE Dari_Tahap_Id=@approved AND Ke_Tahap_Id=@filing;        -- 1 baris
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif='Y'
 WHERE Dari_Tahap_Id=@approved AND Ke_Tahap_Id=@cancel;        -- 1 baris

-- Terbit kini hanya dari Disahkan
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif='T'
 WHERE Dari_Tahap_Id=@approval AND Ke_Tahap_Id=@filing;        -- 1 baris

-- Mundur satu langkah: barisnya belum ada
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Transisi
               WHERE Dari_Tahap_Id=@approved AND Ke_Tahap_Id=@approval)
    INSERT INTO N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id, Flag_Aktif, Created_At)
    VALUES (@approved, @approval, 'Y', GETDATE());             -- 1 baris

/* APPROVED->REVIEW sengaja DIBIARKAN MATI: mundur dua langkah sekaligus
   melewati Pengesahan tanpa jejak kenapa. */

-- Syarat masuk Disahkan, menggantikan jalur cadangan Butuh_Pengesahan
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Syarat
               WHERE Tahap_Id=@approved AND Kode_Syarat='REALISASI_PENGESAH')
    INSERT INTO N_SOP_Master_Tahap_Versi_Syarat
        (Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan, Flag_Aktif, Created_At)
    VALUES (@approved, 'REALISASI_PENGESAH', 'Realisasi pengesah', 1, 'Y', GETDATE());

/* FILING_REGISTERED tetap menuntut BERKAS + REALISASI_PENGESAH — tak disentuh. */

SELECT Kode_Tahap, Flag_Aktif, Langkah, Kelompok, Nama_Tahap
  FROM N_SOP_Master_Tahap_Versi ORDER BY Urutan;
COMMIT TRAN;
```

- [ ] **Langkah 2: Tulis catatan perubahan**

`docs/changes/DML-2026-048-approved-langkah-pemberkasan/README.md`. Wajib memuat
bagian **"Hubungan dengan DML-2026-042"** yang menjelaskan bahwa 042 membuang
`APPROVED` karena klik ganda di langkah 3, dan 048 mengembalikannya di langkah 4
justru supaya klik ganda itu tak terjadi — supaya pembaca berikutnya tak
menyimpulkan 042 dibatalkan sembarangan.

- [ ] **Langkah 3: Commit**

```bash
git add database/dml/DML-2026-048-approved-langkah-pemberkasan docs/changes/DML-2026-048-approved-langkah-pemberkasan
git commit -m "chore(sop): artefak DML-2026-048 tahap Disahkan di langkah 4"
```

- [ ] **Langkah 4: BERHENTI — konfirmasi, lalu eksekusi ke development**

Sesudah eksekusi, verifikasi:

```bash
php artisan tinker --execute="
foreach(\DB::table('N_SOP_Master_Tahap_Versi')->orderBy('Urutan')->get() as \$r){
 echo str_pad(\$r->Kode_Tahap,20).' aktif='.\$r->Flag_Aktif.' langkah='.(\$r->Langkah??'-').' kel='.(\$r->Kelompok??'-').PHP_EOL; }"
```

Harapan: `APPROVED aktif=Y langkah=4 kel=TERBIT`.

---

## Task 3: `langkahBerjalan()` — langkah tempat versi berdiri

**Berkas:**
- Ubah: `app/Services/LMS/SOP/SopTahapVersiService.php`
- Uji: `tests/Feature/LMS/SOP/SopWizardVersiTest.php` (baru)

**Antarmuka:**
- Menghasilkan: `public function langkahBerjalan(int $idVersi): int` — nomor
  langkah tempat tahap versi berada. Dipakai Task 4, 5, dan 6.

- [ ] **Langkah 1: Tulis uji yang gagal**

`tests/Feature/LMS/SOP/SopWizardVersiTest.php`:

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Wizard versi dokumen SOP.
 *
 * Yang diuji di sini: langkah yang dibuka layar ditentukan POSISI TAHAP, bukan
 * kelengkapan isi. Sebelum perbaikan ini, layar memilih "langkah pertama yang
 * isinya kurang" — sehingga versi yang baru disimpan di Pengajuan langsung
 * melompat ke Review dan tak pernah kembali.
 */
class SopWizardVersiTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopTahapVersiService
    {
        return app(SopTahapVersiService::class);
    }

    private function dok(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function versiBaru(): int
    {
        $id = $this->dok()->buat([
            'Judul' => 'SOP UJI WIZARD '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = $this->dok()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        if (! $idVersi) {
            $this->markTestSkipped('Versi gagal dibuat.');
        }

        return $idVersi;
    }

    public function test_versi_baru_berdiri_di_langkah_satu(): void
    {
        /* REGRESI. Versi yang baru lahir ada di DRAFTING. Sebelum perbaikan,
           layar membuka Review (atau Pengesahan) karena Pengajuan selalu
           dilaporkan lengkap — kerja admin tersimpan tapi tak pernah terlihat. */
        $idVersi = $this->versiBaru();

        $this->assertSame(1, $this->svc()->langkahBerjalan($idVersi),
            'Versi di DRAFTING harus berdiri di langkah 1, bukan melompat ke langkah yang isinya kurang.');
    }

    public function test_langkah_berjalan_mengikuti_tahap_bukan_isi(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);

        /* Tanpa satu pun catatan review ditulis — isi langkah 2 masih kosong,
           tetapi versinya memang BERDIRI di langkah 2. */
        $this->assertSame(2, $this->svc()->langkahBerjalan($idVersi));
    }
}
```

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL**

Ditahan sampai ACC. Bila kelak dijalankan:
`php artisan test --filter=SopWizardVersiTest`
Harapan: FAIL — `Call to undefined method … ::langkahBerjalan()`.

- [ ] **Langkah 3: Implementasi minimal**

Di `SopTahapVersiService`, tepat di bawah `langkahVersi()`, tambahkan pembungkus
publik untuk `langkahKini()` yang sudah ada (private):

```php
    /**
     * Nomor langkah tempat versi BERDIRI — bukan langkah yang isinya kurang.
     *
     * Perbedaan itu yang dulu melahirkan gejala "selalu balik ke Review":
     * layar memilih langkah pertama yang `Lengkap`-nya false, dan karena
     * Pengajuan selalu lengkap, ia tak pernah berhenti di Pengajuan. Langkah
     * yang dibuka sekarang POSISIONAL; kelengkapan isi tetap dilaporkan, tapi
     * hanya sebagai keterangan.
     */
    public function langkahBerjalan(int $idVersi): int
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Created_At', 'Tanggal_Publish', 'Tanggal_Permintaan',
                'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Obsolete']);
        if (! $versi) {
            return 1;
        }

        return $this->langkahKini($this->tahapVersi($idVersi), $versi) ?? 1;
    }
```

- [ ] **Langkah 4: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 5: Commit**

```bash
git add app/Services/LMS/SOP/SopTahapVersiService.php tests/Feature/LMS/SOP/SopWizardVersiTest.php
git commit -m "feat(sop): langkahBerjalan() menjawab posisi tahap, bukan kelengkapan isi"
```

---

## Task 4: `alurVersi()` — satu payload berisi langkah, kunci, dan aksi

**Berkas:**
- Ubah: `app/Services/LMS/SOP/SopTahapVersiService.php`
- Ubah: `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php:1563-1594`
- Uji: `tests/Feature/LMS/SOP/SopWizardVersiTest.php`

**Antarmuka:**
- Mengonsumsi: `langkahBerjalan()` (Task 3), susunan tahap dari Task 2.
- Menghasilkan: `public function alurVersi(int $idVersi): array` dengan kunci
  `Langkah`, `Langkah_Aktif`, `Aksi_Utama`, `Aksi_Lain`, `Tujuan`. Bentuk
  `Aksi_Utama` = `['Jenis'=>string,'Label'=>string,'Ke_Tahap'=>?string,
  'Syarat_Kurang'=>array<string>]` dengan `Jenis` ∈ `SIMPAN|SIMPAN_LANJUT|
  TERBITKAN`. Dipakai `useVersiAlur.js` (Task 10).

- [ ] **Langkah 1: Tulis uji yang gagal**

Tambahkan ke `SopWizardVersiTest`:

```php
    public function test_langkah_di_depan_terkunci(): void
    {
        $idVersi = $this->versiBaru();
        $alur = $this->svc()->alurVersi($idVersi);

        $terkunci = collect($alur['Langkah'])->pluck('Terkunci', 'Nomor');

        $this->assertFalse($terkunci[1], 'Langkah berjalan tak boleh terkunci.');
        $this->assertTrue($terkunci[2], 'Langkah 2 di depan tahap berjalan.');
        $this->assertTrue($terkunci[3]);
        $this->assertTrue($terkunci[4]);
        $this->assertSame(1, $alur['Langkah_Aktif']);
    }

    public function test_langkah_yang_sudah_dilewati_terbuka_kembali(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);

        $terkunci = collect($this->svc()->alurVersi($idVersi)['Langkah'])->pluck('Terkunci', 'Nomor');

        $this->assertFalse($terkunci[1], 'Langkah yang sudah lewat harus bisa dibuka & disunting lagi.');
        $this->assertFalse($terkunci[2]);
        $this->assertTrue($terkunci[3]);
    }

    public function test_aksi_utama_bernama_dan_menyebut_tujuannya(): void
    {
        $idVersi = $this->versiBaru();
        $aksi = $this->svc()->alurVersi($idVersi)['Aksi_Utama'];

        $this->assertSame('SIMPAN_LANJUT', $aksi['Jenis']);
        $this->assertSame('REVIEW', $aksi['Ke_Tahap']);
        $this->assertStringContainsString('Review', $aksi['Label'],
            'Label datang dari server supaya layar tak menyusun kalimatnya sendiri.');
        $this->assertSame([], $aksi['Syarat_Kurang']);
    }

    public function test_aksi_utama_jadi_simpan_saja_saat_syarat_kurang(): void
    {
        /* Di Pengesahan tanpa pengesah, maju ke Disahkan belum sah. Tombolnya
           tetap "Simpan", dan yang kurang disebut apa adanya — bukan tombol
           lanjut yang nanti ditolak server. */
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $aksi = $this->svc()->alurVersi($idVersi)['Aksi_Utama'];

        $this->assertSame('SIMPAN', $aksi['Jenis']);
        $this->assertNotEmpty($aksi['Syarat_Kurang']);
    }
```

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL** *(ditahan sampai ACC)*

Harapan: FAIL — `Call to undefined method … ::alurVersi()`.

- [ ] **Langkah 3: Implementasi**

Tambahkan di `SopTahapVersiService`:

```php
    /**
     * Seluruh yang dibutuhkan wizard untuk menggambar dirinya, dalam satu
     * panggilan — termasuk langkah mana yang harus dibuka.
     *
     * KENAPA SERVER YANG MEMUTUSKAN. Selama layar ikut memutuskan, ada dua
     * sumber kebenaran: server menegakkan syarat transisi, layar menebak
     * langkah. Keduanya pasti menyimpang, dan menyimpangnya tak terlihat
     * sampai admin menemukan dirinya di langkah yang salah.
     */
    public function alurVersi(int $idVersi): array
    {
        $kini = $this->tahapVersi($idVersi);
        $aktif = $this->langkahBerjalan($idVersi);
        $tujuan = $this->tujuanSah($idVersi);

        $langkah = $this->langkahVersi($idVersi);
        foreach ($langkah as &$l) {
            /* Terkunci itu POSISIONAL. Langkah yang sudah lewat tetap terbuka:
               mengoreksi ringkasan perubahan setelah masuk Review adalah hal
               wajar, dan mengunci masa lalu memaksa admin membatalkan versi
               hanya untuk memperbaiki satu kalimat. */
            $l['Terkunci'] = $l['Nomor'] > $aktif;
            $l['Kurang'] = $this->kurangLangkah($l, $tujuan);
        }
        unset($l);

        return [
            'Langkah' => $langkah,
            'Langkah_Aktif' => $aktif,
            'Aksi_Utama' => $this->aksiUtama($tujuan),
            'Aksi_Lain' => $this->aksiLain($tujuan),
            'Tujuan' => $tujuan,
        ];
    }

    /**
     * Syarat yang belum terpenuhi untuk MENINGGALKAN sebuah langkah.
     *
     * Diambil dari tujuan maju yang sah, bukan dihitung ulang: daftar yang
     * dibaca layar dan daftar yang menegakkan transisi harus satu.
     */
    private function kurangLangkah(array $langkah, array $tujuan): array
    {
        $maju = array_values(array_filter(
            $tujuan,
            fn ($t) => in_array($t['Aksi'] ?? '', ['MAJU', 'TERBITKAN'], true),
        ));

        $kurang = [];
        foreach ($maju as $t) {
            foreach ($t['Syarat']['daftar'] ?? [] as $s) {
                if (! ($s['terpenuhi'] ?? false)) {
                    $kurang[] = $s['Nama'];
                }
            }
        }

        return array_values(array_unique($kurang));
    }

    /**
     * SATU aksi utama, sudah bernama lengkap dengan akibatnya.
     *
     * Layar tak menyusun kalimat "Simpan & Lanjut ke X" sendiri: begitu tahap
     * dinamai ulang di master, kalimat yang dirakit layar ikut basi tanpa ada
     * yang tahu.
     *
     * Ambigu — lebih dari satu tujuan maju yang sah — sengaja jatuh ke SIMPAN.
     * Memilihkan satu di antara dua jalan yang sama-sama sah bukan wewenang
     * sebuah tombol simpan.
     */
    private function aksiUtama(array $tujuan): array
    {
        $simpan = ['Jenis' => 'SIMPAN', 'Label' => 'Simpan', 'Ke_Tahap' => null, 'Syarat_Kurang' => []];

        $maju = array_values(array_filter(
            $tujuan,
            fn ($t) => in_array($t['Aksi'] ?? '', ['MAJU', 'TERBITKAN'], true),
        ));
        if (count($maju) !== 1) {
            return $simpan;
        }

        $t = $maju[0];
        $kurang = array_values(array_map(
            fn ($s) => $s['Nama'],
            array_filter($t['Syarat']['daftar'] ?? [], fn ($s) => ! ($s['terpenuhi'] ?? false)),
        ));

        if ($kurang !== []) {
            /* Syaratnya belum lengkap: tombolnya tetap Simpan biasa, dan yang
               kurang disebut apa adanya. Menawarkan tombol lanjut yang pasti
               ditolak berarti penolakan datang SESUDAH admin menekannya. */
            return array_merge($simpan, ['Syarat_Kurang' => $kurang]);
        }

        return $t['Aksi'] === 'TERBITKAN'
            ? ['Jenis' => 'TERBITKAN', 'Label' => 'Pratinjau Terbit',
                'Ke_Tahap' => $t['Kode_Tahap'], 'Syarat_Kurang' => []]
            : ['Jenis' => 'SIMPAN_LANJUT', 'Label' => 'Simpan & Lanjut ke '.$t['Nama_Tahap'],
                'Ke_Tahap' => $t['Kode_Tahap'], 'Syarat_Kurang' => []];
    }

    /** Aksi bermaksud lain — mundur dan batal — masing-masing dinamai akibatnya. */
    private function aksiLain(array $tujuan): array
    {
        $out = [];
        foreach ($tujuan as $t) {
            $jenis = $t['Aksi'] ?? '';
            if ($jenis === 'KEMBALIKAN') {
                $out[] = ['Jenis' => 'KEMBALIKAN', 'Label' => 'Kembalikan ke '.$t['Nama_Tahap'],
                    'Ke_Tahap' => $t['Kode_Tahap']];
            }
            if ($jenis === 'BATALKAN') {
                $out[] = ['Jenis' => 'BATALKAN', 'Label' => 'Batalkan Versi',
                    'Ke_Tahap' => $t['Kode_Tahap']];
            }
        }

        return $out;
    }
```

- [ ] **Langkah 4: Sambungkan ke endpoint**

Di `SopAdminDokumenController::langkahVersi()`, ganti blok `ResponseHelper::success`
menjadi:

```php
            return ResponseHelper::success(
                $svc->alurVersi($decoded) + [
                    'Tahap' => [
                        'Kode_Tahap' => $kini->Kode_Tahap,
                        'Nama_Tahap' => $kini->Nama_Tahap,
                        'Warna' => $kini->Warna,
                        'Langkah' => $kini->Langkah !== null ? (int) $kini->Langkah : null,
                        'Is_Terminal' => $kini->Is_Terminal === SopStatus::AKTIF,
                    ],
                    /* Kontrak lama dipertahankan — Builder masih membacanya. */
                    'Syarat_Kini' => $svc->syaratTahap($decoded, $kini),
                ], 'Data Ditemukan');
```

`alurVersi()` sudah menyertakan `Tujuan`, jadi kunci itu tak ditulis dua kali.

- [ ] **Langkah 5: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 6: Commit**

```bash
git add app/Services/LMS/SOP/SopTahapVersiService.php app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php tests/Feature/LMS/SOP/SopWizardVersiTest.php
git commit -m "feat(sop): alurVersi() mengirim langkah aktif, kunci, dan aksi bernama"
```

---

## Task 5: Penjaga tahap pada penerbitan versi

**Berkas:**
- Ubah: `app/Services/LMS/SOP/SopDokumenService.php:1614` (`terbitkanVersi`)
- Uji: `tests/Feature/LMS/SOP/SopWizardVersiTest.php`

**Antarmuka:**
- Mengonsumsi: `SopTahapVersiService::tujuanSah()`.
- Menghasilkan: `terbitkanVersi()` melempar `\DomainException` bila
  `FILING_REGISTERED` bukan tujuan sah dari tahap versi saat ini.

> **Peringatan.** Penjaga di service bersama menyentuh SETIAP pemanggil. Sesuai
> `penjaga-baru-jalankan-suite-penuh`, seluruh suite SOP wajib dijalankan
> (sesudah ACC), bukan hanya berkas yang disentuh.

- [ ] **Langkah 1: Tulis uji yang menuntut PENOLAKAN**

```php
    public function test_terbit_ditolak_dari_tahap_yang_bukan_disahkan(): void
    {
        /* Penjaga wajib punya uji yang menuntut PENOLAKAN. Uji jalur sukses
           saja pernah membuat penjaga mati tanpa ketahuan berhari-hari. */
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $this->expectException(\DomainException::class);
        app(SopDokumenService::class)->terbitkanVersi($idVersi, 1);
    }
```

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL** *(ditahan sampai ACC)*

Harapan: FAIL — tak ada exception, atau exception lain (`pastikanIsiSah`).
Bila yang muncul galat berkas, penjaga baru harus dipasang **sebelum**
`pastikanIsiSah()` supaya sebab penolakan tak tertukar.

- [ ] **Langkah 3: Implementasi**

Di `terbitkanVersi()`, tepat sesudah penjaga `Status !== DRAFT` dan **sebelum**
`$this->pastikanIsiSah($idVersi)`:

```php
        /* TERBIT HANYA DARI TAHAP YANG MEMANG MENGARAH KE SANA.
           Aturannya tetap tinggal di N_SOP_Master_Tahap_Versi_Transisi, bukan
           di sini: menuliskan "hanya dari APPROVED" sebagai kode berarti setiap
           perubahan kebijakan alur menunggu rilis, padahal seluruh rancangan
           tahap ini justru ada supaya alur bisa berubah sebagai data. */
        $tahap = app(SopTahapVersiService::class);
        $bolehTerbit = collect($tahap->tujuanSah($idVersi))
            ->contains('Kode_Tahap', 'FILING_REGISTERED');
        if (! $bolehTerbit) {
            $kini = $tahap->tahapVersi($idVersi);
            throw new \DomainException(
                'Versi ini belum boleh diterbitkan dari tahap '
                .($kini->Nama_Tahap ?? 'sekarang')
                .'. Selesaikan pengesahannya lebih dulu, lalu terbitkan dari langkah Pemberkasan.');
        }
```

- [ ] **Langkah 4: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 5: Commit**

```bash
git add app/Services/LMS/SOP/SopDokumenService.php tests/Feature/LMS/SOP/SopWizardVersiTest.php
git commit -m "fix(sop): terbit versi ditolak dari tahap yang tak mengarah ke Filing"
```

---

## Task 6: Buang input `Tanggal_Kadaluarsa`

**Berkas:**
- Ubah: `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php:812`
- Ubah: `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue`
  (blok label "Tanggal Kadaluarsa" beserta `HcisDatePicker`-nya, dan kunci
  `Tanggal_Kadaluarsa` di `form`)

**Antarmuka:**
- Menghasilkan: `Tanggal_Kadaluarsa` tak lagi diterima maupun dikirim layar.
  Task 16 (`LangkahPemberkasan.vue`) tak boleh memunculkannya kembali.

- [ ] **Langkah 1: Pastikan benar-benar nol pembaca di lingkup SOP**

```bash
grep -rn "Tanggal_Kadaluarsa" app/Services/LMS/SOP app/Http/Controllers/LMS/SOP resources/js/Pages/LMS/SOP
```

Harapan: hanya validasi controller + input modal. **Ada hasil lain = berhenti
dan lapor**, jangan hapus.

- [ ] **Langkah 2: Hapus baris validasi**

Hapus `'Tanggal_Kadaluarsa' => 'nullable|date',` dari `validasiVersi()`, ganti
dengan komentar:

```php
        /* Tanggal_Kadaluarsa TIDAK diterima lagi. Masa berlaku SOP dicabut
           2026-08-15; kolomnya sengaja tak dijatuhkan, tapi memintanya dari
           admin berarti menuntut keputusan yang tak dibaca siapa pun. */
```

- [ ] **Langkah 3: Hapus input di modal**

Hapus blok kolom "Tanggal Kadaluarsa" beserta `fieldBerubah.Tanggal_Kadaluarsa`,
dan kunci `Tanggal_Kadaluarsa` dari objek `form` serta dari muatan simpan.

- [ ] **Langkah 4: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue
git commit -m "refactor(sop): buang input Tanggal Kadaluarsa yang tak punya pembaca"
```

---

## Task 7: Rute & controller halaman wizard

**Berkas:**
- Buat: `app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php`
- Ubah: `routes/LMS/SOP/Governance/SopGovernance.php` (grup
  `check.jabatan:menuSopDokumenPage`, sesudah baris rute `sop/admin/dokumen`)
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue` (rangka
  minimal dulu)
- Uji: `tests/Feature/LMS/SOP/SopWizardHalamanTest.php`

**Antarmuka:**
- Menghasilkan: rute bernama `sop.admin.dokumen.versi`, prop Inertia
  `dokumen` (objek identitas) dan `idVersi` (hashid atau `null`).

- [ ] **Langkah 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SopWizardHalamanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_halaman_wizard_menolak_tanpa_hak_dokumen(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $this->markTestSkipped('Tak ada user di database uji.');
        }

        $this->actingAs($user)
            ->get('/sop/admin/dokumen/xxxx/versi')
            ->assertStatus(403);
    }
}
```

Uji jalur 200 memerlukan user pemegang `menuSopDokumenPage` — memori proyek
menyebut user 4 & 622. Bila keduanya tak ada di database uji, `markTestSkipped`,
jangan mengarang hak akses.

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL** *(ditahan sampai ACC)*

Harapan: FAIL 404 — rutenya belum ada.

- [ ] **Langkah 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopDokumenService;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Halaman wizard versi dokumen SOP.
 *
 * HANYA merender halaman. Seluruh data dibaca lewat API
 * /api/v1/sop/admin/dokumen yang sudah ada, sehingga tak ada kontrak kedua
 * yang harus dijaga sejalan dengan modal riwayat.
 *
 * Memakai ULANG gerbang menuSopDokumenPage. Halaman rincian bukan menu
 * sidebar, jadi ia tak butuh baris di N_HRIS_Grup_Fitur_Map — dan dengan
 * begitu terhindar dari jebakan "halaman baru = 403 untuk semua orang" yang
 * menuntut tiga baris page-access ditanam manual lebih dulu.
 */
class SopAdminVersiWizardController extends Controller
{
    public function __construct(private SopDokumenService $service) {}

    private function decode($id): ?int
    {
        return Hashids::connection('custom')->decode((string) $id)[0] ?? null;
    }

    /** GET /sop/admin/dokumen/{dokumen}/versi/{versi?} */
    public function halaman($dokumen, $versi = null)
    {
        $idDokumen = $this->decode($dokumen);
        abort_if($idDokumen === null, 404, 'Dokumen tidak dikenali.');

        $detail = $this->service->detail($idDokumen);
        abort_if(! $detail, 404, 'Dokumen tidak ditemukan.');

        return inertia('LMS/SOP/Governance/Admin/SopVersiWizard', [
            'dokumen' => $detail,
            /* Hashid diteruskan apa adanya: layar memanggil API dengan bentuk
               yang sama seperti modal, jadi tak ada penyandian kedua. */
            'idVersi' => $versi,
        ]);
    }
}
```

Sesuaikan nama metode `detail()` dengan yang benar-benar ada di
`SopDokumenService` — periksa lebih dulu dengan
`grep -n "public function" app/Services/LMS/SOP/SopDokumenService.php`, dan
pakai metode yang dipanggil `SopAdminDokumenController::detail()`.

- [ ] **Langkah 4: Daftarkan rute**

Di dalam grup `check.jabatan:menuSopDokumenPage` yang sudah ada, tepat di bawah
rute `sop/admin/dokumen`:

```php
    /* Wizard versi. Berada di grup yang SAMA supaya haknya satu: siapa pun yang
       boleh membuka Master Dokumen boleh mendefinisikan versinya. Kunci baru di
       sini berarti tiga baris page-access yang harus ditanam manual, dan sampai
       itu terjadi halamannya menolak semua orang. */
    Route::get('sop/admin/dokumen/{dokumen}/versi/{versi?}',
        [SopAdminVersiWizardController::class, 'halaman'])->name('sop.admin.dokumen.versi');
```

Tambahkan `use App\Http\Controllers\LMS\SOP\Governance\SopAdminVersiWizardController;`
di kepala berkas rute.

> Rute baru tak terlihat sampai cache rute dibuang. `php artisan route:clear` —
> cache itu per-worktree, dan "worktree lain aman" justru petunjuk bahwa cache
> di worktree ini yang basi.

- [ ] **Langkah 5: Rangka halaman minimal**

`SopVersiWizard.vue` sementara cukup menerima prop dan menampilkan judul
dokumen; isinya dirakit Task 16.

```vue
<template>
    <AppLayout>
        <div class="sop-versi-wizard sop-scope">
            <h1 class="h5 fw-bold mb-0">{{ dokumen?.Judul }}</h1>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    name: 'SopVersiWizard',
    components: { AppLayout },
    props: {
        dokumen: { type: Object, required: true },
        idVersi: { type: String, default: null },
    },
};
</script>
```

Periksa nama & jalur layout yang dipakai `SopMasterDokumen.vue` dan **ikuti itu**
— jangan mengarang `AppLayout` bila proyek memakai nama lain.

> Kelas `sop-scope` wajib ikut. Tanpanya, modal SOP yang di-`Teleport` ke body
> kehilangan gayanya.

- [ ] **Langkah 6: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 7: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php routes/LMS/SOP/Governance/SopGovernance.php resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue tests/Feature/LMS/SOP/SopWizardHalamanTest.php
git commit -m "feat(sop): halaman wizard versi di gerbang menuSopDokumenPage"
```

---

## Task 8: `useVersiAlur.js` — satu-satunya pemuat alur

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/useVersiAlur.js`
- Uji: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/useVersiAlur.test.js`

**Antarmuka:**
- Menghasilkan: `createAlur(api)` yang mengembalikan objek reaktif
  `{ memuat, galat, langkah, langkahAktif, dibuka, tahap, aksiUtama, aksiLain,
  muat(idVersi), buka(nomor) }`.
  `buka(nomor)` **menolak** langkah `Terkunci` dan mengembalikan `false`.
  Dipakai Task 9 dan 16.

- [ ] **Langkah 1: Tulis uji yang gagal**

```js
import { describe, it, expect, vi } from 'vitest';
import { createAlur } from './useVersiAlur.js';

const payload = {
    Langkah_Aktif: 2,
    Langkah: [
        { Nomor: 1, Nama: 'Pengajuan', Kelompok: 'PENGAJUAN', Keadaan: 'selesai', Terkunci: false },
        { Nomor: 2, Nama: 'Review', Kelompok: 'REVIEW', Keadaan: 'berjalan', Terkunci: false },
        { Nomor: 3, Nama: 'Pengesahan', Kelompok: 'PENGESAHAN', Keadaan: 'belum', Terkunci: true },
        { Nomor: 4, Nama: 'Pemberkasan', Kelompok: 'TERBIT', Keadaan: 'belum', Terkunci: true },
    ],
    Aksi_Utama: { Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Pengesahan', Ke_Tahap: 'APPROVAL', Syarat_Kurang: [] },
    Aksi_Lain: [],
    Tahap: { Kode_Tahap: 'REVIEW', Nama_Tahap: 'Review' },
};

const apiPalsu = () => ({ get: vi.fn().mockResolvedValue({ data: { result: payload } }) });

describe('useVersiAlur', () => {
    it('membuka langkah yang ditunjuk server, bukan langkah pertama yang kurang', async () => {
        const alur = createAlur(apiPalsu());
        await alur.muat('abc');

        expect(alur.langkahAktif).toBe(2);
        expect(alur.dibuka).toBe(2);
    });

    it('menolak membuka langkah yang terkunci', async () => {
        const alur = createAlur(apiPalsu());
        await alur.muat('abc');

        expect(alur.buka(3)).toBe(false);
        expect(alur.dibuka).toBe(2);
    });

    it('mengizinkan kembali ke langkah yang sudah dilewati', async () => {
        const alur = createAlur(apiPalsu());
        await alur.muat('abc');

        expect(alur.buka(1)).toBe(true);
        expect(alur.dibuka).toBe(1);
    });

    it('memuat ulang tidak menyeret pemakai keluar dari langkah yang sedang dibuka', async () => {
        /* REGRESI. Gejala aslinya: tiap simpan memanggil muat ulang, dan muat
           ulang memindahkan layar ke langkah lain — kerja admin tersimpan tapi
           layarnya melompat. */
        const api = apiPalsu();
        const alur = createAlur(api);
        await alur.muat('abc');
        alur.buka(1);

        await alur.muat('abc', { pertahankanDibuka: true });

        expect(alur.dibuka).toBe(1);
    });
});
```

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL** *(ditahan sampai ACC)*

Harapan: FAIL — modul belum ada.

- [ ] **Langkah 3: Implementasi**

```js
import { reactive } from 'vue';
import axios from 'axios';

const API = '/api/v1/sop/admin/dokumen';

/**
 * Keadaan alur versi — SATU-SATUNYA pemuat.
 *
 * Langkah yang dibuka datang dari server (`Langkah_Aktif`). Layar tak pernah
 * menghitungnya sendiri: versi sebelumnya memilih "langkah pertama yang isinya
 * kurang", dan karena Pengajuan selalu dilaporkan lengkap, layar melompat ke
 * Review setiap kali admin menyimpan.
 *
 * `pertahankanDibuka` dipakai sesudah menyimpan tanpa berpindah tahap: yang
 * disegarkan datanya, bukan tempat berdiri pemakainya.
 */
export function createAlur(api = axios) {
    const s = reactive({
        memuat: false,
        galat: '',
        untuk: null,
        langkah: [],
        langkahAktif: 1,
        dibuka: 1,
        tahap: null,
        aksiUtama: null,
        aksiLain: [],

        async muat(idVersi, { pertahankanDibuka = false } = {}) {
            if (!idVersi) return;
            s.memuat = true;
            s.galat = '';
            const sebelumnya = s.dibuka;
            try {
                const { data } = await api.get(`${API}/versi/${idVersi}/langkah`);
                const r = data?.result || {};
                s.langkah = r.Langkah || [];
                s.langkahAktif = r.Langkah_Aktif || 1;
                s.tahap = r.Tahap || null;
                s.aksiUtama = r.Aksi_Utama || null;
                s.aksiLain = r.Aksi_Lain || [];
                s.untuk = idVersi;

                const masihBoleh = s.langkah.find((l) => l.Nomor === sebelumnya && !l.Terkunci);
                s.dibuka = pertahankanDibuka && masihBoleh ? sebelumnya : s.langkahAktif;
            } catch (e) {
                s.galat = e?.response?.data?.message || 'Gagal memuat perjalanan versi.';
            } finally {
                s.memuat = false;
            }
        },

        /** Membuka langkah. Yang terkunci ditolak — itu satu-satunya penjaga di layar. */
        buka(nomor) {
            const l = s.langkah.find((x) => x.Nomor === nomor);
            if (!l || l.Terkunci) return false;
            s.dibuka = nomor;

            return true;
        },
    });

    return s;
}
```

- [ ] **Langkah 4: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 5: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/useVersiAlur.js resources/js/Pages/LMS/SOP/Governance/Admin/versi/useVersiAlur.test.js
git commit -m "feat(sop): useVersiAlur sebagai pemuat tunggal alur versi"
```

---

## Task 9: `VersiStepper.vue` — rel langkah dengan kunci

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiStepper.vue`
- Uji: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiStepper.test.js`

**Antarmuka:**
- Mengonsumsi: bentuk `langkah` dari Task 8.
- Props: `langkah: Array`, `dibuka: Number`, `memuat: Boolean`.
- Emits: `pilih` dengan muatan `Number` (nomor langkah) — **tidak diemit**
  untuk langkah terkunci.

- [ ] **Langkah 1: Tulis uji yang gagal**

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import VersiStepper from './VersiStepper.vue';

const langkah = [
    { Nomor: 1, Nama: 'Pengajuan', Keadaan: 'selesai', Terkunci: false, Kurang: [] },
    { Nomor: 2, Nama: 'Review', Keadaan: 'berjalan', Terkunci: false, Kurang: [] },
    { Nomor: 3, Nama: 'Pengesahan', Keadaan: 'belum', Terkunci: true, Kurang: ['Realisasi pengesah'] },
];

describe('VersiStepper', () => {
    it('tidak mengemit pilih untuk langkah terkunci', async () => {
        const w = mount(VersiStepper, { props: { langkah, dibuka: 2 } });
        await w.findAll('button')[2].trigger('click');

        expect(w.emitted('pilih')).toBeUndefined();
    });

    it('mengemit pilih untuk langkah yang sudah dilewati', async () => {
        const w = mount(VersiStepper, { props: { langkah, dibuka: 2 } });
        await w.findAll('button')[0].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual([1]);
    });

    it('menyebut apa yang kurang pada langkah terkunci', () => {
        const w = mount(VersiStepper, { props: { langkah, dibuka: 2 } });

        expect(w.text()).toContain('Realisasi pengesah');
    });
});
```

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL** *(ditahan sampai ACC)*

- [ ] **Langkah 3: Implementasi**

```vue
<template>
    <div class="versi-stepper" role="list">
        <button
            v-for="l in langkah"
            :key="l.Nomor"
            type="button"
            role="listitem"
            class="versi-stepper__item"
            :class="[`is-${l.Keadaan || 'belum'}`, {
                'is-dibuka': dibuka === l.Nomor,
                'is-terkunci': l.Terkunci,
            }]"
            :disabled="l.Terkunci"
            :aria-current="dibuka === l.Nomor ? 'step' : undefined"
            :title="l.Terkunci && l.Kurang?.length
                ? `Terkunci — ${l.Kurang.join(', ')}`
                : l.Nama"
            @click="pilih(l)"
        >
            <span class="versi-stepper__nomor">
                <i v-if="l.Keadaan === 'selesai'" class="bi bi-check-lg"></i>
                <i v-else-if="l.Keadaan === 'cancel'" class="bi bi-x-lg"></i>
                <i v-else-if="l.Terkunci" class="bi bi-lock-fill"></i>
                <span v-else>{{ l.Nomor }}</span>
            </span>
            <span class="versi-stepper__isi">
                <strong>{{ l.Nama }}</strong>
                <small v-if="l.Terkunci && l.Kurang?.length">{{ l.Kurang.join(' · ') }}</small>
                <small v-else-if="l.Ringkasan">{{ l.Ringkasan }}</small>
            </span>
        </button>
    </div>
</template>

<script>
export default {
    name: 'VersiStepper',
    props: {
        langkah: { type: Array, default: () => [] },
        dibuka: { type: Number, default: 1 },
        memuat: { type: Boolean, default: false },
    },
    emits: ['pilih'],
    methods: {
        pilih(l) {
            /* Penjaga ganda dengan :disabled. Aturan yang cuma dipasang di satu
               dari dua pintu setara adalah bug berulang di modul ini. */
            if (l.Terkunci) return;
            this.$emit('pilih', l.Nomor);
        },
    },
};
</script>

<style scoped>
/* Gaya di-scope. Style global tak ber-namespace adalah sebab "style nyangkut"
   antar halaman Inertia. */
.versi-stepper { display: flex; gap: .5rem; flex-wrap: wrap; }
.versi-stepper__item { display: flex; align-items: center; gap: .625rem;
    border: 1px solid var(--hcis-border, #e2e8f0); border-radius: .75rem;
    background: var(--hcis-surface, #fff); padding: .625rem .875rem; text-align: left; }
.versi-stepper__item.is-terkunci { opacity: .55; cursor: not-allowed; }
.versi-stepper__item.is-dibuka { border-color: var(--hcis-brand, #2563eb); }
.versi-stepper__nomor { display: grid; place-items: center; width: 1.75rem; height: 1.75rem;
    border-radius: 50%; background: var(--hcis-surface-muted, #f1f5f9); font-weight: 700; }
.versi-stepper__isi { display: flex; flex-direction: column; line-height: 1.2; }
.versi-stepper__isi small { color: var(--hcis-text-muted, #64748b); font-size: .72rem; }
</style>
```

- [ ] **Langkah 4: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 5: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiStepper.vue resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiStepper.test.js
git commit -m "feat(sop): rel langkah wizard menolak langkah terkunci"
```

---

## Task 10: `VersiRingkasan.vue` — kolom keterangan

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiRingkasan.vue`

**Antarmuka:**
- Props: `versi: Object`, `langkah: Array`, `tahap: Object`.
- Emits: `riwayat` (tanpa muatan) — membuka daftar riwayat versi.

- [ ] **Langkah 1: Implementasi**

Kartu ringkas berisi: nomor versi, tahap berjalan (pil berwarna `tahap.Warna`),
lalu empat baris fakta yang dibaca dari `Ringkasan` masing-masing langkah
(`Lengkap` menentukan ikon ✓/–). Tombol "Riwayat Versi" di kaki kartu mengemit
`riwayat`.

```vue
<template>
    <HcisCard title="Ringkasan Versi" icon="info-circle" size="sm" variant="outline">
        <ul class="versi-ringkasan">
            <li v-for="l in langkah" :key="l.Nomor">
                <i class="bi" :class="l.Lengkap ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-muted'"></i>
                <span>{{ l.Nama }}</span>
                <small>{{ l.Ringkasan || '—' }}</small>
            </li>
        </ul>
        <template #footer>
            <HcisButton variant="ghost" size="xs" leading-icon="clock-history"
                label="Riwayat Versi" @click="$emit('riwayat')" />
        </template>
    </HcisCard>
</template>

<script>
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisButton from '@/components/ui/HcisButton.vue';

export default {
    name: 'VersiRingkasan',
    components: { HcisCard, HcisButton },
    props: {
        versi: { type: Object, default: null },
        langkah: { type: Array, default: () => [] },
        tahap: { type: Object, default: null },
    },
    emits: ['riwayat'],
};
</script>

<style scoped>
.versi-ringkasan { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .5rem; }
.versi-ringkasan li { display: grid; grid-template-columns: 1.25rem 1fr auto; align-items: center; gap: .5rem; }
.versi-ringkasan small { color: var(--hcis-text-muted, #64748b); font-size: .72rem; }
</style>
```

Sebelum menulis, periksa kontrak `HcisCard` (`size`, `variant`, slot `#footer`)
dan `HcisButton` di `resources/js/components/ui/` — kit ini punya beberapa
kontrak yang tak seperti dugaan.

- [ ] **Langkah 2: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiRingkasan.vue
git commit -m "feat(sop): kolom ringkasan versi sebagai keterangan, bukan gerbang"
```

---

## Task 11: `LangkahPengajuan.vue`

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPengajuan.vue`

**Antarmuka:**
- Props: `modelValue: Object` (form versi), `terkunci: Boolean`.
- Emits: `update:modelValue`.
- Field yang dikelola: `Versi_Ke`, `Ringkasan_Perubahan`, `Tanggal_Permintaan`,
  `Tanggal_Respon`, ditambah keadaan lokal `manualNomor`.

- [ ] **Langkah 1: Implementasi**

Pindahkan blok `kelompokAktif === 'PENGAJUAN'` dari `SopDokumenVersiModal.vue`
(baris 368-408) apa adanya, ganti `form.X` menjadi `nilai.X` di mana `nilai`
adalah computed dua arah atas `modelValue`, dan tambahkan `:disabled="terkunci"`
pada setiap input.

```js
computed: {
    nilai: {
        get() { return this.modelValue; },
        set(v) { this.$emit('update:modelValue', v); },
    },
},
```

`Tanggal_Kadaluarsa` **tidak ikut** (Task 6).

- [ ] **Langkah 2: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPengajuan.vue
git commit -m "feat(sop): langkah Pengajuan sebagai komponen sendiri"
```

---

## Task 12: `LangkahReview.vue`

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue`

**Antarmuka:**
- Props: `idVersi: String`, `terkunci: Boolean`.
- Emits: `tersimpan` — dipancarkan sesudah catatan review tersimpan, supaya
  cangkang memuat ulang alur.
- Memanggil sendiri `GET/PUT /api/v1/sop/admin/dokumen/versi/{id}/review`.

- [ ] **Langkah 1: Implementasi**

Pindahkan blok `kelompokAktif === 'REVIEW'` (baris 581-693) beserta metode
`tambahReview`, `hapusReview`, `pilihLampiran`, `hapusLampiran`, `simpanReview`
dari modal. Komponen memuat datanya sendiri saat `idVersi` berubah.

Peringatan spesifik langkah ini: batas 2 MB per gambar sudah ditegakkan di layar
dan server; jangan menyalin hanya salah satunya.

- [ ] **Langkah 2: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue
git commit -m "feat(sop): langkah Review sebagai komponen mandiri"
```

---

## Task 13: `LangkahPengesahan.vue`

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPengesahan.vue`

**Antarmuka:**
- Props: `modelValue: Object`, `terkunci: Boolean`, `pakaiPengesahan: Boolean`.
- Emits: `update:modelValue`.
- Field: `Tanggal_Akhir_Approval`, `Pengesah` (lewat `SopPengesahPicker` dengan
  `basis="/api/v1/sop/admin/dokumen"`).

- [ ] **Langkah 1: Implementasi**

Pindahkan blok `kelompokAktif === 'PENGESAHAN'` (baris 698-745). `Nomor_SK` dan
`Tanggal_Pengesahan` sudah dibuang 2026-08-25 — **jangan dihidupkan kembali**.

- [ ] **Langkah 2: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPengesahan.vue
git commit -m "feat(sop): langkah Pengesahan sebagai komponen mandiri"
```

---

## Task 14: `LangkahPemberkasan.vue`

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue`

**Antarmuka:**
- Props: `modelValue: Object`, `terkunci: Boolean`, `isKuis: Boolean`,
  `tipe: String`, `ujianList: Array`, `ujianLoading: Boolean`.
- Emits: `update:modelValue`, `pilih-berkas`, `pratinjau-terbit`.
- Field: `Id_Master_Konten` / `Id_Master_Ujian`, `Konten_Nama`, `Tanggal_Berlaku`.

- [ ] **Langkah 1: Implementasi**

Pindahkan blok `kelompokAktif === 'TERBIT'` (baris 420-578) **dikurangi** kolom
`Tanggal_Kadaluarsa`. Judul kartu mengikuti istilah baru: "Pemberkasan".

- [ ] **Langkah 2: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue
git commit -m "feat(sop): langkah Pemberkasan sebagai komponen mandiri"
```

---

## Task 15: Rakit `SopVersiWizard.vue`

**Berkas:**
- Ubah: `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue`
- Uji: `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js`

**Antarmuka:**
- Mengonsumsi: `createAlur` (Task 8), `VersiStepper` (9), `VersiRingkasan` (10),
  keempat komponen langkah (11-14).

- [ ] **Langkah 1: Tulis uji yang gagal**

```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SopVersiWizard from './SopVersiWizard.vue';

describe('SopVersiWizard', () => {
    it('menampilkan label aksi utama apa adanya dari server', async () => {
        /* Layar TIDAK menyusun "Simpan & Lanjut ke X" sendiri. Begitu tahap
           dinamai ulang di master, kalimat rakitan layar ikut basi diam-diam. */
        const w = mount(SopVersiWizard, {
            props: { dokumen: { Judul: 'SOP Uji', Tipe: 'DOKUMEN' }, idVersi: 'abc' },
            global: { stubs: { AppLayout: { template: '<div><slot /></div>' } } },
        });
        w.vm.alur.aksiUtama = { Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Pemberkasan', Ke_Tahap: 'APPROVED', Syarat_Kurang: [] };
        await w.vm.$nextTick();

        expect(w.text()).toContain('Simpan & Lanjut ke Pemberkasan');
    });

    it('menandai formulir bersih sebelum pesan sukses muncul', async () => {
        /* Menandainya sesudah await berikutnya membuat "belum disimpan" muncul
           SESUDAH simpan berhasil; jendelanya ~1,5 detik. */
        const w = mount(SopVersiWizard, {
            props: { dokumen: { Judul: 'SOP Uji', Tipe: 'DOKUMEN' }, idVersi: 'abc' },
            global: { stubs: { AppLayout: { template: '<div><slot /></div>' } } },
        });
        w.vm.kotor = true;
        w.vm.tandaiBersih();

        expect(w.vm.kotor).toBe(false);
    });
});
```

- [ ] **Langkah 2: Jalankan uji, pastikan GAGAL** *(ditahan sampai ACC)*

- [ ] **Langkah 3: Implementasi cangkang**

Susunan: `HcisContextBanner` (judul dokumen + nomor resmi + pil tahap) →
`VersiStepper` → grid dua kolom (`VersiRingkasan` | komponen langkah sesuai
`alur.langkah[dibuka].Kelompok`) → kaki aksi.

Aturan yang wajib dipegang cangkang:

1. **Kaki aksi digambar dari `alur.aksiUtama` dan `alur.aksiLain`.** Tak ada
   `v-if` pada kode tahap yang dihafal layar.
2. **Sesudah simpan tanpa pindah tahap**, panggil
   `alur.muat(idVersi, { pertahankanDibuka: true })`.
3. **Sesudah pindah tahap**, panggil `alur.muat(idVersi)` tanpa opsi —
   perpindahan memang seharusnya memindahkan pemakai.
4. **`tandaiBersih()` dipanggil SEBELUM `await` berikutnya**, bukan sesudah
   pesan sukses.
5. `SopTerbitPratinjau` dipasang di cangkang, dipicu `Aksi_Utama.Jenis === 'TERBITKAN'`.
6. Aksi `KEMBALIKAN` dan `BATALKAN` wajib lewat `SopCabutAksesDialog` yang sudah
   ada — keduanya wajib bersebab.

```js
data() {
    return { alur: createAlur(), form: { …}, kotor: false, menyimpan: false };
},
methods: {
    tandaiBersih() { this.kotor = false; },
    async simpan() {
        this.menyimpan = true;
        try {
            await axios.put(`${API}/versi/${this.idVersi}`, this.form);
            this.tandaiBersih();                     // SEBELUM apa pun yang menunggu
            await this.alur.muat(this.idVersi, { pertahankanDibuka: true });
            this.$toast?.success('Perubahan tersimpan.');
        } finally {
            this.menyimpan = false;
        }
    },
},
```

- [ ] **Langkah 4: Jalankan uji, pastikan LULUS** *(ditahan sampai ACC)*

- [ ] **Langkah 5: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js
git commit -m "feat(sop): wizard versi dirakit dari aksi yang dikirim server"
```

---

## Task 16: Rampingkan `SopDokumenVersiModal.vue`

**Berkas:**
- Ubah: `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue`

**Antarmuka:**
- Menghasilkan: modal tinggal daftar riwayat versi; tombol "Buat Versi
  Pertama" / "Tambah Versi Baru" / "Sunting" **menavigasi** ke
  `route('sop.admin.dokumen.versi', …)`.

- [ ] **Langkah 1: Buang layar form**

Hapus `<section v-show="layar === 'form'">` (baris 223-747) beserta blok footer
`v-if="layar === 'form'"`, lalu hapus metode & computed yang jadi yatim:
`langkahPertamaBelumSelesai`, `langkahLengkap`, `pilihLangkah`, `muatAlur`,
`muatTemplateLangkah`, `simpanLaluLanjut`, `bukaLangkahTerbit`, `jalankanAksi`,
`kelompokAktif`, `tujuanPindah`, `tujuanMaju`, `aksiKembalikan`, `aksiBatalkan`,
`tujuanLanjut`, `bisaLanjut`, `tujuanTerbit`, `syaratTerbitSiap`,
`medanTanggalProses`, dan keadaan `alur`, `form`, `review`.

Jalankan pencarian yatim sesudahnya:

```bash
grep -n "alur\.\|this\.form\.\|review\." resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue
```

Hasil = nol, kecuali di dalam daftar riwayat.

- [ ] **Langkah 2: Arahkan tombol ke halaman**

```js
        bukaVersiBaru() {
            this.$inertia.visit(this.$route('sop.admin.dokumen.versi', {
                dokumen: this.dokumen.Id_SOP_Dokumen,
            }));
        },
        suntingVersi(v) {
            this.$inertia.visit(this.$route('sop.admin.dokumen.versi', {
                dokumen: this.dokumen.Id_SOP_Dokumen,
                versi: v.Id_SOP_Item_Versi,
            }));
        },
```

Periksa helper rute yang benar-benar dipakai proyek (`$route` vs `route` global)
di `SopMasterDokumen.vue` sebelum menulis, dan ikuti yang itu.

- [ ] **Langkah 3: Periksa ukuran akhir**

```bash
wc -l resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue
```

Harapan: ~900 baris (dari 2.326). Jauh di atas itu = ada blok yatim yang belum
dibuang.

- [ ] **Langkah 4: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue
git commit -m "refactor(sop): modal versi kembali jadi daftar riwayat saja"
```

---

## Task 17: Verifikasi menyeluruh — SESUDAH ACC

**Berkas:** —

- [ ] **Langkah 1: Suite SOP penuh**

```bash
php artisan test --filter=Sop
```

Baseline: ~19 kegagalan non-SOP yang **memang sudah ada** sebelum pekerjaan ini.
Bandingkan dengan baseline, bukan dengan nol. Kegagalan BARU di berkas SOP mana
pun = penjaga Task 5 menyentuh pemanggil yang tak terduga; telusuri, jangan
longgarkan penjaganya.

- [ ] **Langkah 2: Vitest**

```bash
npx vitest run resources/js/Pages/LMS/SOP
```

- [ ] **Langkah 3: Build**

```bash
npm run build
```

Bila dua worktree sama-sama menjalankan `npm run dev`, hentikan salah satunya —
port HMR yang bentrok membuat halaman memuat JS milik worktree tetangga, dan
gejalanya menyamar jadi "komponen hilang sendiri".

- [ ] **Langkah 4: Susur manual di HMR**

Buat satu dokumen baru → versi pertama → lewati keempat langkah → terbitkan.
Yang wajib terlihat:

1. Sesudah menyimpan di langkah 1, layar **tetap** di langkah 1
2. Langkah 2-4 terkunci sampai dimasuki
3. Langkah 1 tetap bisa dibuka & disunting sesudah masuk langkah 2
4. Di langkah 3 tanpa pengesah, tombol utama berbunyi "Simpan" dan menyebut apa
   yang kurang
5. Sesudah pengesah diisi, tombolnya menjadi "Simpan & Lanjut ke Pemberkasan"
6. Terbit hanya bisa dari langkah 4

- [ ] **Langkah 5: Perbarui dokumentasi arsitektur**

Perbarui `docs/architecture/feature-mapping-lms-sop-master-document.md` dengan
halaman, controller, dan komponen baru. Ubah status di kepala
`plans/sop/2026-08-26-wizard-versi-dokumen-design.md` menjadi **TERLAKSANA**.

- [ ] **Langkah 6: Commit**

```bash
git add docs/architecture/feature-mapping-lms-sop-master-document.md plans/sop/2026-08-26-wizard-versi-dokumen-design.md
git commit -m "docs(sop): catat wizard versi dokumen sebagai terlaksana"
```
