# Lampiran Form Kosong (Dokumen FORM) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dokumen SOP bertipe FORM bisa membawa beberapa "form kosong" per versi, dipasang admin di langkah Pemberkasan, dan diunduh karyawan dari kartu FORM.

**Architecture:** Tabel anak baru `N_SOP_Item_Versi_Lampiran` (menunjuk Library Konten). Satu service baru `SopVersiLampiranService` memegang validasi, penulisan selisih, dan pembacaan massal; `SopDokumenService` memanggilnya dari `tambahVersi()`/`ubahVersi()` sebagai field semu `Lampiran` yang tunduk pada `SopVersiKeadaan`. Karyawan mengunduh lewat endpoint baru yang memakai gerbang `apiVersiKonten`.

**Tech Stack:** Laravel 10 (PHP 8.2), SQL Server, Vue 3 Options API + Inertia, PHPUnit (DB dev `Web_HRIS`), Vitest.

**Spec:** `docs/superpowers/specs/2026-10-05-sop-lampiran-form-kosong-design.md`

## Global Constraints

- Lampiran hanya untuk tipe FORM; blok profil `lampiran` = `BERLAKU` (opsional), tak menahan terbit.
- Boleh beberapa lampiran per versi; konten yang sama tak boleh dua kali di satu versi.
- Izin ubah: `Lampiran` ada di `FIELD_DRAFT` dan `FIELD_ISI`, tidak di `FIELD_ARSIP`.
- Revisi baru tidak mewarisi lampiran. Unduh lampiran tidak mengubah `N_SOP_Progress`.
- Tidak ada Laravel migration. DDL: `database/ddl/DDL-2026-158-sop-versi-lampiran-form/{forward,drop}.sql` + `status.json`; forward hanya dijalankan agent di `Web_HRIS` (dev) setelah user menyetujui SQL-nya; `drop.sql` tak pernah dijalankan agent.
- Berkas baru tetap di pohon `LMS/SOP` (lihat memori registry DMS).
- Tidak menambah `<i class="bi …">` mentah (ratchet design system) — pakai `HcisIcon`/`HcisButton leading-icon`.
- Test PHP dijalankan per berkas: `php vendor/bin/phpunit <file>` (`--filter` fatal di repo ini).

## Review Focus

- Admin melepas SEMUA lampiran (larik kosong) → semua jadi `Flag_Aktif='T'`, bukan diabaikan. Test di Task 3.
- Formulir mengirim ulang daftar lampiran yang SAMA pada versi yang sudah dijadwalkan → tidak boleh ditolak (sama seperti field terlarang bernilai sama). Test di Task 3.
- Kunci `Lampiran` tidak dikirim (jalur non-layar) → lampiran lama utuh. Test di Task 3.
- Karyawan meminta lampiran milik versi lain / nonaktif lewat id tebakan → 404. Test di Task 5.
- Konten Library yang sudah dinonaktifkan masih tercantum sebagai lampiran → tetap tampil di admin, unduhan karyawan memakai `forKonten` yang menolak bila konten tak ada (404 jujur). Test di Task 5.

---

### Task 1: DDL tabel lampiran (dev)

**Files:**
- Create: `database/ddl/DDL-2026-158-sop-versi-lampiran-form/forward.sql`
- Create: `database/ddl/DDL-2026-158-sop-versi-lampiran-form/drop.sql`
- Create: `docs/changes/DDL-2026-158-sop-versi-lampiran-form/README.md`
- Create: `docs/database/tables/sop/n-sop-item-versi-lampiran.md`
- Modify: `database/ddl/status.json` (tambah entri)

**Interfaces:**
- Produces: tabel `dbo.N_SOP_Item_Versi_Lampiran(Id_Lampiran int PK, Id_SOP_Item_Versi int, Id_Master_Konten int, Urutan int, Flag_Aktif char(1), Created_At, Created_By, Updated_At, Updated_By)`.

- [ ] **Step 1: Tulis forward.sql**

```sql
-- Change ID: DDL-2026-158-sop-versi-lampiran-form
-- Target: Web_HRIS. Form kosong (lampiran unduhan) per versi dokumen FORM.
IF OBJECT_ID('dbo.N_SOP_Item_Versi_Lampiran', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Item_Versi_Lampiran (
        Id_Lampiran        int IDENTITY(1,1) NOT NULL,
        Id_SOP_Item_Versi  int          NOT NULL,
        Id_Master_Konten   int          NOT NULL,
        Urutan             int          NOT NULL CONSTRAINT DF_SOP_Versi_Lampiran_Urutan DEFAULT (0),
        Flag_Aktif         char(1)      NOT NULL CONSTRAINT DF_SOP_Versi_Lampiran_Aktif DEFAULT ('Y'),
        Created_At         datetime2(0) NOT NULL CONSTRAINT DF_SOP_Versi_Lampiran_Created DEFAULT (SYSDATETIME()),
        Created_By         int          NULL,
        Updated_At         datetime2(0) NULL,
        Updated_By         int          NULL,
        CONSTRAINT PK_SOP_Item_Versi_Lampiran PRIMARY KEY (Id_Lampiran),
        CONSTRAINT FK_SOP_Versi_Lampiran_Versi FOREIGN KEY (Id_SOP_Item_Versi)
            REFERENCES dbo.N_SOP_Item_Versi (Id_SOP_Item_Versi),
        CONSTRAINT FK_SOP_Versi_Lampiran_Konten FOREIGN KEY (Id_Master_Konten)
            REFERENCES dbo.N_LMS_Master_Konten (Id_Master_Konten)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_SOP_Item_Versi_Lampiran') AND name = 'IX_SOP_Versi_Lampiran_Versi')
    CREATE INDEX IX_SOP_Versi_Lampiran_Versi
        ON dbo.N_SOP_Item_Versi_Lampiran (Id_SOP_Item_Versi, Flag_Aktif, Urutan);
GO
```

(Sebelum menulis FK ke `N_LMS_Master_Konten`, pastikan kolom PK-nya memang `Id_Master_Konten` int — sudah diverifikasi 5 Okt 2026.)

- [ ] **Step 2: Tulis drop.sql** (bantuan manual, tak dijalankan agent)

```sql
-- Change ID: DDL-2026-158-sop-versi-lampiran-form
-- PEMBERSIHAN MANUAL — tidak dijalankan agent.
IF OBJECT_ID('dbo.N_SOP_Item_Versi_Lampiran', 'U') IS NOT NULL
    DROP TABLE dbo.N_SOP_Item_Versi_Lampiran;
GO
```

- [ ] **Step 3: Entri status.json + README perubahan + dok tabel.** Entri mengikuti bentuk DDL-2026-149: `status: "REVIEW_REQUIRED"`, `development.status: "PENDING"`, `sql_tracker.status: "PENDING"`, `production.status: "PENDING"`, `catatan` berisi checksum sha256 `forward.sql` (`sha256sum`).

- [ ] **Step 4: Tunjukkan SQL ke user, minta persetujuan.** BERHENTI sampai user setuju.

- [ ] **Step 5: Jalankan forward.sql di dev.** Verifikasi `SELECT DB_NAME()` = `Web_HRIS`, eksekusi per batch `GO` lewat `DB::unprepared`, jalankan ulang sekali (harus idempoten), cek `INFORMATION_SCHEMA.COLUMNS` = 9 kolom + FK + indeks. Perbarui status.json: `status: "DEV_EXECUTED"`, `development.status: "DONE"` + hasil.

- [ ] **Step 6: Commit**

```bash
git add database/ddl/DDL-2026-158-sop-versi-lampiran-form database/ddl/status.json docs/changes/DDL-2026-158-sop-versi-lampiran-form docs/database/tables/sop/n-sop-item-versi-lampiran.md
git commit -m "feat(sop): DDL-2026-158 tabel lampiran form kosong per versi"
```

---

### Task 2: Profil tipe + izin field

**Files:**
- Modify: `app/Services/LMS/SOP/Support/SopTipeItem.php` (konstanta `BLOK_LAMPIRAN`, PROFIL FORM)
- Modify: `app/Services/LMS/SOP/Support/SopVersiKeadaan.php` (`FIELD_DRAFT`, `FIELD_ISI`)
- Modify: `app/Services/LMS/SOP/SopDokumenService.php` (`FIELD_BLOK`, pesan `pastikanFieldSesuaiTipe`, `LABEL_FIELD`)
- Modify: `app/Services/LMS/SOP/Support/SopDiff.php` (label `Lampiran`)
- Modify: `resources/js/Pages/LMS/SOP/Shared/support/constants.js` (`BLOK.LAMPIRAN`, `PROFIL_TIPE.FORM`)
- Test: `resources/js/Pages/LMS/SOP/Shared/support/constants.test.js`, `tests/Feature/LMS/SOP/SopIzinUbahVersiTest.php`

**Interfaces:**
- Produces: `SopTipeItem::BLOK_LAMPIRAN = 'lampiran'`; `SopTipeItem::blokBerlaku('FORM','lampiran') === true`; `'Lampiran'` ∈ `FIELD_DRAFT`, `FIELD_ISI`; ∉ `FIELD_ARSIP`, `FIELD_TERBIT`. JS: `BLOK.LAMPIRAN = 'lampiran'`, `PROFIL_TIPE.FORM = [BLOK.BERKAS, BLOK.LAMPIRAN]`.

- [ ] **Step 1: Test gagal.** Di `SopIzinUbahVersiTest::test_kebijakan_izin_per_keadaan` tambahkan:

```php
        /* Lampiran form kosong ikut aturan isi (5 Okt 2026). */
        $this->assertContains('Lampiran', SopVersiKeadaan::FIELD_DRAFT);
        $this->assertContains('Lampiran', SopVersiKeadaan::FIELD_ISI);
        $this->assertNotContains('Lampiran', SopVersiKeadaan::FIELD_ARSIP);
        $this->assertNotContains('Lampiran', SopVersiKeadaan::FIELD_TERBIT);
        $this->assertTrue(\App\Services\LMS\SOP\Support\SopTipeItem::blokBerlaku('FORM', 'lampiran'));
        $this->assertFalse(\App\Services\LMS\SOP\Support\SopTipeItem::blokWajib('FORM', 'lampiran'));
        $this->assertFalse(\App\Services\LMS\SOP\Support\SopTipeItem::blokBerlaku('DOKUMEN', 'lampiran'));
```

Di `constants.test.js` tambahkan:

```js
    it('lampiran form kosong hanya untuk FORM', () => {
        expect(blokBerlaku('FORM', BLOK.LAMPIRAN)).toBe(true);
        for (const t of ['DOKUMEN', 'VIDEO', 'KUIS']) {
            expect(blokBerlaku(t, BLOK.LAMPIRAN), t).toBe(false);
        }
    });
```

- [ ] **Step 2: Jalankan, pastikan merah.** `php vendor/bin/phpunit tests/Feature/LMS/SOP/SopIzinUbahVersiTest.php` dan `npx vitest run resources/js/Pages/LMS/SOP/Shared/support/constants.test.js`.

- [ ] **Step 3: Implementasi.**
  - `SopTipeItem`: `const BLOK_LAMPIRAN = 'lampiran';` dan PROFIL FORM `[BLOK_BERKAS => WAJIB, BLOK_LAMPIRAN => BERLAKU]`.
  - `SopVersiKeadaan`: `FIELD_DRAFT` tambah `'Lampiran'`; `FIELD_ISI = ['Id_Master_Konten', 'Id_Master_Ujian', 'Nama_Ujian', 'Lampiran']`.
  - `SopDokumenService::FIELD_BLOK` tambah `SopTipeItem::BLOK_LAMPIRAN => ['Lampiran']`; pesan `pastikanFieldSesuaiTipe` diganti menjadi `'Field "'.$f.'" tidak berlaku untuk tipe '.$tipe.'.'` (pesan lama menyebut "hanya dipakai tipe DOKUMEN", salah untuk lampiran). `LABEL_FIELD['Lampiran'] = 'Form kosong'`.
  - `SopDiff` label `'Lampiran' => 'Form kosong'`.
  - `constants.js`: `LAMPIRAN: 'lampiran'` di `BLOK`, `FORM: [BLOK.BERKAS, BLOK.LAMPIRAN]`.

- [ ] **Step 4: Jalankan lagi, hijau** (dua perintah di Step 2).

- [ ] **Step 5: Commit** `feat(sop): blok lampiran untuk FORM dan izin field Lampiran`.

---

### Task 3: SopVersiLampiranService + integrasi tulis

**Files:**
- Create: `app/Services/LMS/SOP/SopVersiLampiranService.php`
- Modify: `app/Services/LMS/SOP/SopDokumenService.php` (`tambahVersi`, `ubahVersi`)
- Test: `tests/Feature/LMS/SOP/SopVersiLampiranTest.php`

**Interfaces:**
- Produces:
  - `SopVersiLampiranService::rapikan(mixed $mentah): array` → `list<array{Id_Master_Konten:int, Urutan:int}>`; melempar `\DomainException` untuk konten tak ada / bukan Scope SOP / tak cocok FORM / kembar.
  - `SopVersiLampiranService::aktif(int $idVersi): list<int>` → id konten aktif berurutan.
  - `SopVersiLampiranService::simpan(int $idVersi, array $daftar, int $userId): array{masuk: list<int>, keluar: list<int>}`.
  - `SopVersiLampiranService::untukVersi(iterable $idVersi): array<int, list<object{Id_Lampiran,Id_Master_Konten,Judul,Ext,Ukuran_Byte,Urutan}>>`.

- [ ] **Step 1: Test gagal** — `tests/Feature/LMS/SOP/SopVersiLampiranTest.php` (DatabaseTransactions). Fixture: dokumen FORM baru lewat `SopDokumenService::buat(['Judul'=>..., 'Tipe'=>'FORM'], 1)` + `tambahVersi($idDok, ['Ringkasan_Perubahan'=>'Awal'], 1)`; dua konten: `DB::table('N_LMS_Master_Konten')->where('Scope','SOP')->where('Flag_Aktif','Y')->limit(3)->pluck('Id_Master_Konten')` (skip bila < 3). Kasus:
  1. `ubahVersi($v, ['Lampiran'=>[['Id_Master_Konten'=>$k1],['Id_Master_Konten'=>$k2]]], 1)` → null; `aktif($v) === [$k1,$k2]`.
  2. Simpan ulang `[[k2],[k3]]` → `aktif === [$k2,$k3]`; baris k1 `Flag_Aktif='T'`; `N_SOP_Log` terakhir untuk versi ini punya `Meta_JSON` dengan `lampiran_masuk=[k3]`, `lampiran_keluar=[k1]`.
  3. `['Lampiran'=>[]]` → `aktif === []`.
  4. Tanpa kunci `Lampiran` (hanya `Ringkasan_Perubahan`) → lampiran tak berubah.
  5. Kembar `[[k1],[k1]]` → `ubahVersi` melempar/mengembalikan pesan yang memuat "dua kali".
  6. Konten tak ada (`999999999`) → ditolak.
  7. Dokumen DOKUMEN mengirim lampiran terisi → `\DomainException` "tidak berlaku".
  8. Versi terbit (`Status=PUBLISHED`, `Tahap_Id`=FILING_REGISTERED) belum dijadwalkan → menerima; sesudah `jadwalkan()` (salin helper dari `SopIzinUbahVersiTest`) → pesan memuat "dijadwalkan", lampiran tetap; mengirim daftar yang SAMA → null (tidak ditolak).
  9. Versi OBSOLETE → ditolak, lampiran tetap.

- [ ] **Step 2: Jalankan, merah.** `php vendor/bin/phpunit tests/Feature/LMS/SOP/SopVersiLampiranTest.php`

- [ ] **Step 3: Implementasi service**

```php
<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;

/**
 * Form kosong (lampiran unduhan) per versi dokumen FORM.
 *
 * Berkas utama versi FORM adalah contoh pengisian; form kosongnya diunduh
 * karyawan untuk diisi sendiri. Boleh beberapa per versi (mis. Word dan
 * Excel), karena itu tabel anak, bukan kolom.
 */
class SopVersiLampiranService
{
    private string $table = 'N_SOP_Item_Versi_Lampiran';

    /** @return list<array{Id_Master_Konten:int, Urutan:int}> */
    public function rapikan($mentah): array
    {
        $ids = collect(is_array($mentah) ? $mentah : [])
            ->map(fn ($x) => (int) (is_array($x) ? ($x['Id_Master_Konten'] ?? 0) : $x))
            ->values();

        if ($ids->contains(fn ($id) => $id <= 0)) {
            throw new \DomainException('Ada form kosong yang berkasnya tak dikenali. Pilih ulang dari Library.');
        }
        if ($ids->count() !== $ids->unique()->count()) {
            throw new \DomainException('Form kosong yang sama dipasang dua kali.');
        }
        if ($ids->isEmpty()) {
            return [];
        }

        $konten = DB::table('N_LMS_Master_Konten')
            ->whereIn('Id_Master_Konten', $ids->all())
            ->where('Scope', 'SOP')
            ->get(['Id_Master_Konten', 'Tipe_Konten'])
            ->keyBy('Id_Master_Konten');

        foreach ($ids as $id) {
            $k = $konten->get($id);
            if (! $k) {
                throw new \DomainException('Berkas form kosong tidak ditemukan di Library Konten SOP.');
            }
            if (! SopTipeItem::kontenCocok(SopTipeItem::FORM, $k->Tipe_Konten)) {
                throw new \DomainException('Jenis berkas form kosong tidak didukung.');
            }
        }

        return $ids->map(fn ($id, $i) => ['Id_Master_Konten' => $id, 'Urutan' => $i])->all();
    }

    /** @return list<int> */
    public function aktif(int $idVersi): array
    {
        return DB::table($this->table)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')->orderBy('Id_Lampiran')
            ->pluck('Id_Master_Konten')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Tulis SELISIH: yang tetap diperbarui urutannya, yang dilepas
     * dinonaktifkan (jejaknya tetap), yang baru di-insert.
     *
     * @return array{masuk: list<int>, keluar: list<int>}
     */
    public function simpan(int $idVersi, array $daftar, int $userId): array
    {
        $lama = $this->aktif($idVersi);
        $baru = array_column($daftar, 'Id_Master_Konten');

        $keluar = array_values(array_diff($lama, $baru));
        $masuk = array_values(array_diff($baru, $lama));

        if ($keluar !== []) {
            DB::table($this->table)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->whereIn('Id_Master_Konten', $keluar)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_At' => now(), 'Updated_By' => $userId]);
        }

        foreach ($daftar as $baris) {
            if (in_array($baris['Id_Master_Konten'], $masuk, true)) {
                DB::table($this->table)->insert([
                    'Id_SOP_Item_Versi' => $idVersi,
                    'Id_Master_Konten' => $baris['Id_Master_Konten'],
                    'Urutan' => $baris['Urutan'],
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Created_At' => now(),
                    'Created_By' => $userId,
                ]);

                continue;
            }
            DB::table($this->table)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->where('Id_Master_Konten', $baris['Id_Master_Konten'])
                ->where('Urutan', '<>', $baris['Urutan'])
                ->update(['Urutan' => $baris['Urutan'], 'Updated_At' => now(), 'Updated_By' => $userId]);
        }

        return ['masuk' => $masuk, 'keluar' => $keluar];
    }

    /** @return array<int, list<object>> keyed Id_SOP_Item_Versi */
    public function untukVersi(iterable $idVersi): array
    {
        $ids = collect($idVersi)->filter()->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table($this->table.' as l')
            ->join('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'l.Id_Master_Konten')
            ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'l.Id_Master_Konten')
            ->whereIn('l.Id_SOP_Item_Versi', $ids->all())
            ->where('l.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('l.Urutan')->orderBy('l.Id_Lampiran')
            ->get(['l.Id_Lampiran', 'l.Id_SOP_Item_Versi', 'l.Id_Master_Konten', 'l.Urutan',
                'k.Judul_Konten as Judul', 'b.Ext', 'b.Ukuran_Byte', 'b.File_Path'])
            ->unique('Id_Lampiran')
            ->groupBy('Id_SOP_Item_Versi')
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }
}
```

(Pastikan `SopStatus::INAKTIF` ada — dipakai controller dokumen; bila tidak, pakai `'T'`.)

- [ ] **Step 4: Integrasi `ubahVersi()`** (setelah `$sudahDijadwalkan`/izin dihitung, sebelum `periksaIzinField`):

```php
        $lampiranSvc = app(SopVersiLampiranService::class);
        $lampiranBaru = null;
        if (array_key_exists('Lampiran', $data)) {
            $lampiranBaru = $lampiranSvc->rapikan($data['Lampiran']);
            $lampiranBerubah = array_column($lampiranBaru, 'Id_Master_Konten') !== $lampiranSvc->aktif($idVersi);
            if ($lampiranBerubah && ! in_array('Lampiran', $izin, true)) {
                return $sudahDijadwalkan
                    ? 'Form kosong tak bisa diganti lagi: dokumen ini sudah dijadwalkan ke karyawan lewat paket. '
                        .'Terbitkan versi baru untuk menggantinya.'
                    : ($alasanBeku ?? SopVersiKeadaan::alasanTerkunci($versi->Status, $tahap));
            }
            if (! $lampiranBerubah) {
                $lampiranBaru = null;   // sama persis: jangan sentuh
            }
        }
        unset($data['Lampiran']);   // bukan kolom versi; periksaIzinField tak perlu melihatnya
```

(`$alasanBeku` dihitung sebelum blok ini — pindahkan blok ke sesudah perhitungan `$alasanBeku`.) Di dalam `DB::transaction` setelah update versi:

```php
            if ($lampiranBaru !== null) {
                $lampiranJejak = $lampiranSvc->simpan($idVersi, $lampiranBaru, $userId);
            }
```

`$lampiranJejak` dideklarasikan `['masuk'=>[], 'keluar'=>[]]` sebelum transaksi dan di-`use (&$lampiranJejak, ...)`. Jejak `VERSI_UBAH` menambah `'lampiran_masuk' => $lampiranJejak['masuk'], 'lampiran_keluar' => $lampiranJejak['keluar']`, dan kalimat ditambah `' Form kosong: +X −Y.'` bila ada.

- [ ] **Step 5: Integrasi `tambahVersi()`**: `rapikan($data['Lampiran'] ?? [])` sebelum transaksi; `unset($data['Lampiran'])`; setelah baris versi lahir, `simpan($idBaru, $daftar, $userId)` bila tidak kosong.

- [ ] **Step 6: Jalankan, hijau** + `SopIzinUbahVersiTest.php` tetap hijau.

- [ ] **Step 7: Commit** `feat(sop): simpan lampiran form kosong per versi`.

---

### Task 4: Muatan admin + validasi controller

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` (`aturanVersi`, `storeVersi`, `updateVersi`, `formatRiwayatVersiRows`)
- Test: `tests/Feature/LMS/SOP/SopVersiLampiranTest.php` (tambah kasus HTTP)

**Interfaces:**
- Consumes: `SopVersiLampiranService::untukVersi`.
- Produces: tiap baris `GET /api/v1/sop/admin/dokumen/{dok}/versi` punya `Lampiran: [{Id_Lampiran:int, Id_Master_Konten:string(hashid), Judul:string, Ext:?string, Urutan:int}]`. Body PUT/POST menerima `Lampiran: [{Id_Master_Konten: hashid|int}]`.

- [ ] **Step 1: Test gagal** — kasus HTTP (admin = user pertama dengan `memilikiJabatan('menuSopDokumenPage')`, skip bila tak ada): PUT `/api/v1/sop/admin/dokumen/versi/{hashVersi}` dengan `Lampiran` dua hashid konten → 200; GET riwayat versi → baris versi punya `Lampiran` 2 item berurutan dengan `Judul` terisi. Hitung kueri ke `N_SOP_Item_Versi_Lampiran` lewat `DB::listen` saat GET: ≤ 1.

- [ ] **Step 2: Merah.**

- [ ] **Step 3: Implementasi.**
  - `aturanVersi()`: `'Lampiran' => 'sometimes|array|max:20'`, `'Lampiran.*.Id_Master_Konten' => 'required'`.
  - Di `storeVersi`/`updateVersi`, setelah decode `Id_Master_Konten`: bila `array_key_exists('Lampiran', $data)`, petakan tiap `Id_Master_Konten` lewat `decodeKonten()`; satu yang `null` → `ResponseHelper::error('Form kosong yang dipilih tak dikenali. Pilih ulang dari Library.', 422)`.
  - `formatRiwayatVersiRows`: sebelum `foreach`, `$lampiran = app(SopVersiLampiranService::class)->untukVersi(collect($riwayat)->pluck('Id_SOP_Item_Versi')->map(fn ($x) => (int) $x))` (ambil id SEBELUM baris di-hash — periksa urutan hashing di fungsi itu); per baris `$v->Lampiran = collect($lampiran[$idMentah] ?? [])->map(fn ($l) => ['Id_Lampiran' => (int) $l->Id_Lampiran, 'Id_Master_Konten' => Hashids::connection('custom')->encode($l->Id_Master_Konten), 'Judul' => $l->Judul, 'Ext' => $l->Ext, 'Urutan' => (int) $l->Urutan])->all();`.

- [ ] **Step 4: Hijau.** **Step 5: Commit** `feat(sop): muatan dan validasi lampiran form kosong di admin`.

---

### Task 5: Unduhan karyawan (API)

**Files:**
- Modify: `app/Services/LMS/SOP/SopProgressReadService.php` (`detailBundle`: `Lampiran` per item FORM)
- Modify: `app/Http/Controllers/LMS/SOP/Employee/SopRepositoryController.php` (method `apiVersiLampiran`)
- Modify: `routes/LMS/SOP/Employee/SopEmployee.php`
- Test: `tests/Feature/LMS/SOP/SopVersiLampiranUnduhTest.php`

**Interfaces:**
- Produces: item FORM di `detailBundle` punya `Lampiran: [{Id_Lampiran: hashid, Nama: string, Ext: ?string, Ukuran: ?int}]` (kosong untuk tipe lain); `GET /api/v1/sop/{idPaket}/versi/{idVersi}/lampiran/{idLampiran}` → `{url, nama_berkas, mime}` (unduhan).

- [ ] **Step 1: Test gagal.** Pakai data dev bila ada paket published dengan item FORM yang dijadwalkan ke seorang karyawan (`N_SOP_Penjadwalan_Detail` aktif); skip jika tak ada. Pasang 1 lampiran ke versi aktif item itu. `actingAs` user karyawan tersebut:
  1. `GET /api/v1/sop/{hashPaket}` → item FORM punya `Lampiran` 1 entri.
  2. GET lampiran sah → 200, `result.url` terisi.
  3. `idLampiran` milik versi lain → 404. Lampiran `Flag_Aktif='T'` → 404.
  4. Versi yang bukan berlaku lagi → 410 (pakai gerbang `versiBerlakuDiPaket`).

- [ ] **Step 2: Merah.**

- [ ] **Step 3: Implementasi.**
  - `detailBundle`: setelah `$berkasByVersi`, `$lampiranByVersi = app(SopVersiLampiranService::class)->untukVersi($items->pluck('Id_Item_Versi_Aktif'));`. Di `$itemsOut[]`: `'Lampiran' => $it->Tipe_Item === 'FORM' ? collect($lampiranByVersi[$verId] ?? [])->map(fn ($l) => ['Id_Lampiran' => $this->enc($l->Id_Lampiran), 'Nama' => $l->Judul, 'Ext' => SopAksesBerkas::ekstensi($l->Ext ?? null, $l->File_Path ?? null), 'Ukuran' => $l->Ukuran_Byte !== null ? (int) $l->Ukuran_Byte : null])->all() : [],`.
  - Controller `apiVersiLampiran(Request $request, $idPaket, $idVersi, $idLampiran)`: salin urutan gerbang `apiVersiKonten` (parseId tiga id, `kodeKaryawan`, `tolakBilaBelumAbsen`, `versiVisibleInPaket` → 403, `versiBerlakuDiPaket` → 410); lalu `$k = DB::table('N_SOP_Item_Versi_Lampiran')->where('Id_Lampiran', $l)->where('Id_SOP_Item_Versi', $v)->where('Flag_Aktif', 'Y')->value('Id_Master_Konten');` → 404 bila null; `$res = (new SopKontenViewService)->forKonten((int) $k, true);` → error bila `_error`; sukses kembalikan `$res`.
  - Route: `Route::get('/{idPaket}/versi/{idVersi}/lampiran/{idLampiran}', [SopRepositoryController::class, 'apiVersiLampiran']);` di sebelah rute `konten`.

- [ ] **Step 4: Hijau.** **Step 5: Commit** `feat(sop): karyawan bisa mengunduh form kosong`.

---

### Task 6: Layar admin — kartu Form Kosong

**Files:**
- Create: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/KartuLampiranForm.vue`
- Create: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/KartuLampiranForm.test.js`
- Modify: `resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue` (render kartu bila `blokBerlaku(tipe,'lampiran')`; prop `tipe`; emit `pilih-lampiran`)
- Modify: `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue` (`kosong()` + muat `Lampiran`; `muatan()`; picker dua tujuan)

**Interfaces:**
- `KartuLampiranForm` props: `modelValue: Array<{Id_Master_Konten, Judul, Ext}>`, `terkunci: Boolean`; emits `update:modelValue`, `tambah`, `pratinjau(item)`. Aksi: hapus (filter), naik/turun (tukar indeks).
- Wizard: `form.Lampiran` (array); `muatan()` mengirim `Lampiran: form.Lampiran.map((l) => ({ Id_Master_Konten: l.Id_Master_Konten }))` hanya bila `blokBerlaku(dokumen.Tipe, BLOK.LAMPIRAN)`; `pickerTujuan: 'berkas'|'lampiran'`; `onKonten(k)` → bila `pickerTujuan === 'lampiran'` dan id belum ada, push `{Id_Master_Konten: k.id, Judul: k.judul, Ext: k.ext}`.

- [ ] **Step 1: Vitest gagal** untuk `KartuLampiranForm`: render dua item; klik hapus item pertama → emit `update:modelValue` berisi item kedua saja; klik turun item pertama → urutan tertukar; `terkunci` → tombol tambah/hapus/geser disabled; daftar kosong → teks "Belum ada form kosong".

- [ ] **Step 2: Merah** — `npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/versi/KartuLampiranForm.test.js`.

- [ ] **Step 3: Implementasi komponen** (Options API, `HcisButton`/`HcisIcon`/`HcisBadge` dari `@/components/ui`, `<style scoped>`, tanpa `<i class="bi">`). Pratinjau memakai endpoint `GET /api/v1/sop/admin/dokumen/konten/{id}/berkas` dan `HcisFileViewer` seperti `bukaPratinjau()` di `LangkahPemberkasan.vue:374-400`.

- [ ] **Step 4: Integrasi** `LangkahPemberkasan` (kartu di bawah widget berkas, hanya mode non-kuis, `:terkunci="mati('Lampiran')"`) dan wizard (state, muat dari `v.Lampiran`, `muatan`, picker). Pastikan `kosong()` punya `Lampiran: []` sehingga `berubah`/`kotor` ikut menghitungnya.

- [ ] **Step 5: Hijau** + `npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin` tidak menambah kegagalan dibanding baseline master; `npx prettier --check` berkas baru.

- [ ] **Step 6: Commit** `feat(sop): kartu form kosong di langkah Pemberkasan`.

---

### Task 7: Layar karyawan — tombol unduh form kosong

**Files:**
- Modify: `resources/js/Pages/LMS/SOP/Employee/Detail/SopDetail.vue` (kartu FORM, method `unduhLampiran`)

**Interfaces:**
- Consumes: `it.Lampiran` (Task 5) dan endpoint `/api/v1/sop/{idPaket}/versi/{versiId}/lampiran/{idLampiran}`.

- [ ] **Step 1:** Di kartu FORM (sekitar `SopDetail.vue:1498-1535`, di bawah baris tombol), tambah blok `v-if="it.Tipe_Item === 'FORM' && it.Lampiran && it.Lampiran.length"` berjudul "Form kosong untuk diisi" dengan satu `HcisButton` per lampiran (`leading-icon="download"`, label `l.Nama`, `:loading="unduhLampiranId === l.Id_Lampiran"`).
- [ ] **Step 2:** Method:

```js
        async unduhLampiran(it, l) {
            if (this.unduhLampiranId) return;
            this.unduhLampiranId = l.Id_Lampiran;
            try {
                const r = await axios.get(
                    `/api/v1/sop/${this.idPaket}/versi/${it.Id_Item_Versi_Aktif}/lampiran/${l.Id_Lampiran}`);
                const url = r.data.success ? r.data.result?.url : null;
                if (!url) {
                    ElMessage.error(r.data.message || 'Gagal menyiapkan form kosong');
                    return;
                }
                window.location.href = url;
                ElMessage.success('Form kosong sedang diunduh');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyiapkan form kosong');
            } finally {
                this.unduhLampiranId = null;
            }
        },
```

  Tambah `unduhLampiranId: null` di `data()`. Tidak memanggil `buka()` (unduhan lampiran tak mengubah progres).
- [ ] **Step 3:** `npx vitest run resources/js/Pages/LMS/SOP/Employee` tak menambah kegagalan; prettier untuk blok yang diubah.
- [ ] **Step 4: Commit** `feat(sop): tombol unduh form kosong di kartu FORM`.

---

### Task 8: Verifikasi menyeluruh

- [ ] `php scripts/verify-references.php`, `php scripts/route-snapshot.php` (rute baru muncul), `php scripts/verify-frontend-imports.php`, `vendor/bin/pint --test` berkas tersentuh.
- [ ] Test PHP: `SopVersiLampiranTest`, `SopVersiLampiranUnduhTest`, `SopIzinUbahVersiTest`, `SopVersiBatalBekuTest`, `SopBatalVersiTerbitTest`, `SopDetailVersiPayloadTest`.
- [ ] Browser dev (sesi login uji dibuat lalu dihapus): admin membuka versi FORM di langkah Pemberkasan → tambah dua form kosong → simpan → muat ulang tetap ada; karyawan terjadwal membuka paket → tombol "Unduh" form kosong → mendapat tautan unduh. Data uji dibersihkan atau di-rollback.
- [ ] Laporan akhir dua bagian (Agent completed / User tasks) sesuai AGENTS.md, termasuk status DDL-2026-158.
