# Master Dokumen SOP — FASE 2: Baca & Tulis Versi Lewat Dokumen

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development` (disarankan) atau `superpowers:executing-plans` untuk mengerjakan rencana ini task demi task. Langkah memakai checkbox (`- [ ]`).

**Goal:** Memindahkan identitas versi dari **item** ke **dokumen** — setiap penulis baris `N_SOP_Item_Versi` mengisi `Id_SOP_Dokumen`, penomoran `Versi_Ke` dihitung per dokumen, dan keunikan ditegakkan di `(Id_SOP_Dokumen, Versi_Ke)` — **tanpa satu pun keluaran yang dilihat pengguna bergeser**.

**Architecture:** Hari ini 1 dokumen = 1 item (hasil backfill Fase 1), jadi "per item" dan "per dokumen" menghasilkan angka yang identik. Itulah yang membuat fase ini bisa memindahkan sumbu identitas tanpa perubahan perilaku, dan membuat baseline sidik jari jadi bukti yang sah. Pointer `N_SOP_Paket_Item.Id_Item_Versi_Aktif` **tidak disentuh sama sekali** — ~9 jalur baca besar menjoin lewatnya dan semuanya harus tetap berjalan tanpa diubah.

**Tech Stack:** Laravel 10, SQL Server (sqlsrv, tanpa RCSI), `DB::table()` query builder (modul SOP tidak memakai Eloquent maupun migration Laravel), PHPUnit + `DatabaseTransactions`.

---

## Global Constraints

- **Tidak ada migration Laravel.** DDL modul SOP ditulis sebagai berkas SQL bernomor di `plans/sop/sql/` dan dijalankan **manual** oleh user di SSMS. Kode tidak boleh mengasumsikan DDL sudah jalan tanpa gerbang test skema.
- **Tidak ada Eloquent model, tidak ada factory.** Test memakai data seed nyata lewat `DB::table()`, `markTestSkipped()` bila fixture tak ada, dan trait `DatabaseTransactions`.
- **Bahasa Indonesia** untuk nama variabel, komentar, pesan error, dan judul commit.
- **Nol perubahan perilaku.** Gerbang akhir fase adalah `sop:dokumen-baseline --bandingkan=` melaporkan "Identik" pada **hari yang sama** dengan pengambilan baselinenya.
- **Unique index baru WAJIB terfilter** (`WHERE Id_SOP_Dokumen IS NOT NULL`). SQL Server menganggap NULL **setara** di unique index; tanpa filter, baris `(NULL, 1)` kedua ditolak error 2601.
- **Pointer `Id_Item_Versi_Aktif` tidak boleh disentuh di fase ini.** Ia hanya boleh menunjuk versi PUBLISHED (invarian kepatuhan, lihat memori `sop-versi-aktif-pointer-invariant`). Fan-out pointer adalah Fase 3.
- **Token draft-overlay `di_`/`ds_`/`dp_`** menunjuk tabel overlay yang PK-nya beririsan dengan tabel live. Endpoint apa pun yang menerima id item wajib menolaknya bila ia hanya mampu memproses baris live.

---

## Temuan Fase 1 yang Membentuk Rencana Ini

Semua diverifikasi pada DB dev pada 2026-08-09, bukan diasumsikan.

**1. Ada TIGA penulis baris `N_SOP_Item_Versi` live, bukan satu.**

| Lokasi | Kapan jalan | Mengisi `Id_SOP_Dokumen` hari ini? |
|---|---|---|
| `SopItemVersionService::create()` :39-61 | admin tambah versi baru | **Tidak** |
| `SopItemVersionService::cloneActiveTo()` :204-230 | Duplikat item, Salin ke paket lain, Salin paket | **Tidak** |
| `SopPaketDraftService` :806 (promosi draft→live) | revisi paket terbit dipublikasikan | **Tidak** |

`SopPaketDraftService` :578 menulis ke `N_SOP_Paket_Draft_Item_Versi` (tabel **overlay**, bukan live) — di luar cakupan. `SopBerkasService` :76 menulis `N_SOP_Item_Versi_Berkas` (tabel berkas) — bukan penulis versi.

**2. `SopItemService::cloneRow()` tidak menyalin `Id_SOP_Dokumen`.** Daftar kolomnya eksplisit (`SopItemService.php:135-157`) dan `Id_SOP_Dokumen` tidak ada di dalamnya. Artinya **setiap item hasil salin sejak DDL 26A lahir tanpa dokumen**. Hal yang sama berlaku untuk item live yang lahir dari promosi draft (`SopPaketDraftService` :789 memakai `array_merge($payload, …)` dengan payload dari tabel draft yang tak punya kolom itu).

Ini bukan teori: backfill Fase 1 bersih hari ini, tapi **ia meluruh setiap kali admin menyalin item atau menerbitkan revisi paket**. Fase 2 wajib menutup sumbernya, bukan menjalankan backfill lagi.

**3. Ke-18 baris versi ber-`Id_SOP_Dokumen` NULL semuanya milik item PARAGRAF.**

```
18 x  PARAGRAF | item_aktif=Y | item_punya_doc=TIDAK
Versi NULL yang itemnya justru SUDAH punya dokumen: NOL — invarian backfill utuh
```

Ini versi yatim sungguhan: item yang `Tipe_Item`-nya pernah diubah menjauh dari tipe ber-versi, meninggalkan baris versi. **Sah** dan tidak boleh dipaksa punya dokumen. Angka 18 inilah yang mengharuskan unique index terfilter.

**4. `(Id_SOP_Dokumen, Versi_Ke)` sudah unik pada baris non-NULL** — index unik terfilter bisa dipasang tanpa membersihkan data lebih dulu.

**5. Index yang ada sekarang di `N_SOP_Item_Versi`:**

```
PK_N_SOP_Item_Versi          unik  [Id_SOP_Item_Versi]
UX_N_SOP_Item_Versi_Urut     unik  [Id_SOP_Paket_Item, Versi_Ke]
IX_N_SOP_Versi_Item          biasa [Id_SOP_Paket_Item, Status]
IX_N_SOP_Item_Versi_Dokumen  biasa [Id_SOP_Dokumen, Versi_Ke]   <- dari 26A
```

**6. Keputusan: `UX_N_SOP_Item_Versi_Urut` TIDAK di-drop di Fase 2.** Rencana induk menulis "re-key", tapi men-drop-nya sekarang menghapus satu-satunya pagar yang melindungi 18 baris ber-dokumen-NULL itu sementara pagar penggantinya (terfilter) sengaja tidak menyentuh mereka. Selama 1 dokumen = 1 item, **kedua** kendala terpenuhi bersamaan, jadi memasang yang baru tanpa membuang yang lama tidak berbiaya apa pun. Yang lama baru boleh dibuang di Fase 3, saat berbagi dokumen dimulai dan `Id_SOP_Paket_Item` pada baris versi benar-benar jadi vestigial — dan pembuangannya jadi bagian dari fase yang memang mengubah perilaku, bukan fase yang menjanjikan nol perubahan.

**7. Semantik salin di Fase 2 = dokumen BARU per item hasil salin.** Tujuan akhir Anda (salinan tetap seiring dengan sumbernya) adalah **berbagi** dokumen — dan berbagi mengubah perilaku, jadi ia milik Fase 6 saat Builder memilih Dokumen. Kalau berbagi dinyalakan di sini, `cloneActiveTo()` akan menulis `Versi_Ke = 1` untuk dokumen yang sudah punya versi 1 → **error 2601** begitu index terfilter terpasang. Fase 2 karena itu memberi tiap item hasil salin dokumennya sendiri: perilaku identik dengan hari ini, invarian 1:1 terjaga, dan jalan menuju berbagi tidak tertutup.

---

## Struktur Berkas

| Berkas | Tanggung jawab | Aksi |
|---|---|---|
| `app/Services/SOP/SopDokumenService.php` | Satu-satunya tempat yang membuat & menemukan `N_SOP_Dokumen` untuk sebuah item. Semua penulis versi memanggilnya. | **Buat** |
| `app/Services/SOP/SopItemVersionService.php` | `create()` & `cloneActiveTo()` mengisi `Id_SOP_Dokumen`; `Versi_Ke` dihitung per dokumen; `byItem()` membaca lewat dokumen | Ubah |
| `app/Services/SOP/SopItemService.php` | `cloneRow()` memberi item hasil salin dokumennya sendiri | Ubah |
| `app/Services/SOP/SopPaketDraftService.php` | promosi draft→live mengisi `Id_SOP_Dokumen` pada item & versi | Ubah `:789`, `:803-812` |
| `app/Console/Commands/SopDokumenBaseline.php` | cakupan baseline ditambah export rinci | Ubah |
| `plans/sop/sql/26B_sop_versi_dokumen_unique.sql` | unique index terfilter `(Id_SOP_Dokumen, Versi_Ke)` + blok rollback | **Buat** |
| `tests/Feature/LMS/SOP/SopDokumenFase2Test.php` | gerbang skema 26B + invarian penulis versi | **Buat** |
| `tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php` | tiap penulis versi menghasilkan baris bertaut dokumen | **Buat** |

---

# Task 1 — Perluas Baseline dengan Export Rinci, LALU Ambil Ulang

**Ini wajib jadi task pertama.** Baseline pra-perubahan hanya bisa diambil sebelum perubahan. Menambah cakupan setelah kode bergerak berarti membakukan hasil yang sudah berubah sebagai "sebelum", dan bukti nol-regresinya jadi kosong.

**Files:**
- Modify: `app/Console/Commands/SopDokumenBaseline.php` (method `ambil()`)
- Create: `plans/sop/baseline/fase2-sebelum.json` (ditimpa — berkas hari ini belum memuat export rinci)

**Interfaces:**
- Consumes: `SopExportRinciService::columns(array $grup): array`, `::count(array $f): int`, `::rows(array $f, int $page, int $limit): Collection`
- Produces: kunci baru `export_rinci` di JSON baseline, berisi `{ jumlah, sidik }`

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/LMS/SOP/SopDokumenBaselineTest.php`, tambahkan:

```php
public function test_baseline_mencakup_export_rinci(): void
{
    $berkas = storage_path('app/uji-baseline-export.json');
    @unlink($berkas);

    $this->artisan('sop:dokumen-baseline', ['--simpan' => $berkas])->assertExitCode(0);

    $isi = json_decode(file_get_contents($berkas), true);
    @unlink($berkas);

    $this->assertArrayHasKey('export_rinci', $isi,
        'Export rinci menjoin lewat i.Id_Item_Versi_Aktif — jalur yang justru dipindahkan Fase 2. '
        . 'Tanpa ia di baseline, bukti nol-regresinya kosong.');
    $this->assertArrayHasKey('sidik', $isi['export_rinci']);
    $this->assertNotEmpty($isi['export_rinci']['sidik']);
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=test_baseline_mencakup_export_rinci`
Expected: FAIL — "Failed asserting that an array has the key 'export_rinci'"

- [ ] **Step 3: Implementasi**

Di `SopDokumenBaseline.php`, tambahkan `SopExportRinciService` ke signature `handle()` dan teruskan ke `ambil()`. Di akhir `ambil()`, sebelum `return $hasil;`:

```php
        /* EXPORT RINCI — sengaja ditunda dari Fase 1 (ia menjoin lewat
           i.Id_Item_Versi_Aktif yang tak tersentuh di sana), tapi Fase 2
           memindahkan pembacaan versi, jadi justru di sinilah ia bisa bergeser.
           Halamannya dikunci (page=1, limit=500) supaya sidik jarinya
           deterministik; jumlah total dicatat terpisah agar pertumbuhan data
           terlihat sebagai pertumbuhan, bukan menyamar jadi regresi. */
        $filter = [];
        $barisExport = $export->rows($filter, 1, 500);
        $hasil['export_rinci'] = [
            'jumlah' => $export->count($filter),
            // columns() mengembalikan LIST of ['key' => ...], bukan array
            // asosiatif — array_keys() menghasilkan 0,1,2,... bukan nama kolom.
            'kolom'  => array_column($export->columns(), 'key'),
            'sidik'  => hash('sha256', json_encode(
                $barisExport->map(fn ($r) => (array) $r)->sortBy(fn ($r) => json_encode($r))->values()->all(),
                JSON_UNESCAPED_UNICODE
            )),
        ];
        if ($barisExport->count() >= 500) {
            $this->warn('Export rinci menyentuh plafon 500 baris — sidik jarinya hanya menutupi halaman pertama. '
                . 'Naikkan limit sebelum mengandalkannya sebagai bukti nol regresi.');
        }
```

Urutan disortir stabil sebelum di-hash, dengan alasan yang sama persis seperti `sidikRegister()`: `ORDER BY` di hilir bisa bergeser mengikuti rencana kueri dan memproduksi "BERUBAH" palsu.

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenBaselineTest`
Expected: PASS, semua test kelas ini hijau.

- [ ] **Step 5: Ambil ulang baseline (WAJIB, di hari yang sama dengan Task 6)**

```bash
php artisan sop:dokumen-baseline --simpan=plans/sop/baseline/fase2-sebelum.json
php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase2-sebelum.json
```
Expected: "Identik dengan baseline — nol regresi."

**Peringatan yang wajib dipatuhi:** sidik jari menyerap nilai relatif-hari-ini (sisa hari, ember kadaluarsa). Baseline yang diambil hari ini **tidak akan pernah** melaporkan "Identik" di hari berikutnya. Bila Task 6 jatuh di hari berbeda, ulangi Step 5 pada `git stash` keadaan bersih lebih dulu — kalau tidak, gerbangnya berisik dan akan diabaikan justru saat paling dibutuhkan.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/SopDokumenBaseline.php tests/Feature/LMS/SOP/SopDokumenBaselineTest.php plans/sop/baseline/fase2-sebelum.json
git commit -m "test(sop): masukkan export rinci ke baseline sebelum kode Fase 2 bergerak"
```

---

# Task 2 — `SopDokumenService`: Satu Tempat Membuat Dokumen

**Files:**
- Create: `app/Services/SOP/SopDokumenService.php`
- Test: `tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php`

**Interfaces:**
- Produces:
  - `forItem(int $idItem): ?int` — id dokumen item, atau `null`
  - `createForItem(object $item, int $userId): int` — buat dokumen dari baris item, kembalikan id
  - `ensureForItem(int $idItem, int $userId): ?int` — kembalikan dokumen item; buat bila belum ada; `null` bila item tak ada atau tipenya tak ber-versi

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature;

use App\Services\SOP\SopDokumenService;
use App\Services\SOP\Support\SopStatus;
use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDokumenPenulisVersiTest extends TestCase
{
    use DatabaseTransactions;

    /** Item ber-versi mana pun yang ada, untuk dipakai sebagai sumber salin. */
    private function itemBerVersi(): ?object
    {
        return DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
    }

    public function test_ensure_mengembalikan_dokumen_yang_sudah_ada_tanpa_membuat_baru(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi bertaut dokumen di DB ini.');

        $sebelum = DB::table('N_SOP_Dokumen')->count();
        $id = app(SopDokumenService::class)->ensureForItem((int) $item->Id_SOP_Paket_Item, 1);

        $this->assertSame((int) $item->Id_SOP_Dokumen, $id);
        $this->assertSame($sebelum, DB::table('N_SOP_Dokumen')->count(),
            'ensureForItem tidak boleh membuat dokumen kedua untuk item yang sudah punya.');
    }

    public function test_ensure_membuat_dokumen_untuk_item_ber_versi_yang_belum_punya(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->update(['Id_SOP_Dokumen' => null]);

        $id = app(SopDokumenService::class)->ensureForItem((int) $item->Id_SOP_Paket_Item, 1);
        $this->assertNotNull($id);

        $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame($item->Tipe_Item, $dok->Tipe, 'Tipe dokumen mengikuti tipe itemnya.');
        $this->assertNotSame('', trim((string) $dok->Judul), 'N_SOP_Dokumen.Judul NOT NULL.');
        $this->assertSame($id, (int) DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)->value('Id_SOP_Dokumen'));
    }

    public function test_ensure_menolak_item_tipe_tak_ber_versi(): void
    {
        $paragraf = DB::table('N_SOP_Paket_Item')
            ->where('Tipe_Item', SopTipeItem::PARAGRAF)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (!$paragraf) $this->markTestSkipped('Tidak ada item PARAGRAF di DB ini.');

        $this->assertNull(app(SopDokumenService::class)->ensureForItem((int) $paragraf->Id_SOP_Paket_Item, 1),
            'PARAGRAF tak boleh dipaksa punya dokumen — 18 versi yatim di dev justru miliknya.');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SopDokumenPenulisVersiTest`
Expected: FAIL — `Class "App\Services\SOP\SopDokumenService" not found`

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Services\SOP;

use App\Services\SOP\Support\SopStatus;
use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;

/**
 * Master Dokumen SOP — satu-satunya tempat yang MEMBUAT baris N_SOP_Dokumen
 * untuk sebuah item.
 *
 * KENAPA TERPUSAT. Ada tiga penulis baris N_SOP_Item_Versi live
 * (SopItemVersionService::create, ::cloneActiveTo, dan promosi draft di
 * SopPaketDraftService). Kalau masing-masing membuat dokumennya sendiri,
 * pemetaan field-nya akan menyimpang diam-diam dan backfill Fase 1 meluruh
 * satu jalur pada satu waktu. Pemetaannya karena itu dikunci di sini dan
 * SENGAJA identik dengan SopDokumenBackfill — dokumen yang lahir hari ini
 * harus tak bisa dibedakan dari dokumen hasil backfill.
 *
 * Fase 2 tetap 1 dokumen = 1 item. Berbagi dokumen antar paket adalah Fase 6,
 * lewat pemilih Dokumen di Builder — bukan efek samping penyalinan item.
 */
class SopDokumenService
{
    private string $table = 'N_SOP_Dokumen';

    /** Id dokumen milik item, atau null. */
    public function forItem(int $idItem): ?int
    {
        $id = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_SOP_Dokumen');
        return $id !== null ? (int) $id : null;
    }

    /**
     * Buat dokumen dari sebuah baris item. Id_Versi_Aktif SENGAJA dibiarkan
     * NULL: ia hanya boleh menunjuk versi PUBLISHED, dan dokumen yang baru
     * lahir belum punya satu pun. Memasangnya lebih awal berarti menyajikan
     * versi yang belum sah ke karyawan.
     */
    public function createForItem(object $item, int $userId): int
    {
        return DB::table($this->table)->insertGetId([
            'Nomor_SOP_Resmi' => $item->Nomor_SOP_Resmi ?? null,
            // Judul NOT NULL di 26A sementara Judul_Item nullable — fallback
            // yang sama persis dengan SopDokumenBackfill::judulDokumen().
            'Judul'           => trim((string) ($item->Judul_Item ?? '')) !== ''
                                    ? $item->Judul_Item : 'Dokumen tanpa judul',
            'Tipe'            => $item->Tipe_Item,
            'Deskripsi'       => $item->Deskripsi_Item ?? null,
            'Id_Versi_Aktif'  => null,
            'Flag_Aktif'      => SopStatus::AKTIF,
            'Created_By'      => $userId,
            'Created_At'      => now(),
        ], 'Id_SOP_Dokumen');
    }

    /**
     * Pastikan item ber-versi punya dokumen. Mengembalikan id dokumennya.
     * null bila item tak ada atau tipenya tak ber-versi (PARAGRAF/TAUTAN/
     * ABSENSI tak boleh dipaksa punya dokumen).
     */
    public function ensureForItem(int $idItem, int $userId): ?int
    {
        $item = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->first();
        if (!$item || !SopTipeItem::isVersioned($item->Tipe_Item)) return null;
        if ($item->Id_SOP_Dokumen !== null) return (int) $item->Id_SOP_Dokumen;

        $idDokumen = $this->createForItem($item, $userId);
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)
            ->update(['Id_SOP_Dokumen' => $idDokumen]);

        return $idDokumen;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenPenulisVersiTest`
Expected: PASS (3 test)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/SopDokumenService.php tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php
git commit -m "feat(sop): SopDokumenService sebagai satu-satunya pembuat N_SOP_Dokumen"
```

---

# Task 3 — Ketiga Penulis Versi Mengisi `Id_SOP_Dokumen`

**Files:**
- Modify: `app/Services/SOP/SopItemVersionService.php` (`create()` :39, `cloneActiveTo()` :204)
- Modify: `app/Services/SOP/SopItemService.php` (`cloneRow()` :131)
- Modify: `app/Services/SOP/SopPaketDraftService.php` (:789 item live, :803-812 versi live)
- Test: `tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php` (tambah)

**Interfaces:**
- Consumes: `SopDokumenService::ensureForItem(int, int): ?int` dari Task 2
- Produces: setiap baris `N_SOP_Item_Versi` yang lahir dari jalur live membawa `Id_SOP_Dokumen` yang sama dengan `Id_SOP_Dokumen` itemnya

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `SopDokumenPenulisVersiTest`:

```php
    public function test_create_versi_baru_bertaut_ke_dokumen_itemnya(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $idVersi = app(\App\Services\SOP\SopItemVersionService::class)
            ->create((int) $item->Id_SOP_Paket_Item, [], 1);

        $this->assertSame((int) $item->Id_SOP_Dokumen,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Id_SOP_Dokumen'),
            'Versi baru wajib bertaut ke dokumen itemnya — kalau tidak, backfill Fase 1 meluruh tiap revisi.');
    }

    public function test_item_hasil_salin_dapat_dokumen_SENDIRI_bukan_berbagi(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        // Tabel paket bernama N_SOP_Master_Paket, bukan N_SOP_Paket.
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Status', SopStatus::PAKET_DRAFT)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (!$paket) $this->markTestSkipped('Tidak ada paket DRAFT tujuan.');

        // Section diperiksa SEBELUM dipakai: (int) null = 0 akan menulis baris
        // item yatim ke section yang tak ada, bukan melewatkan test.
        $idSection = DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', $paket->Id_SOP_Paket)->value('Id_SOP_Section');
        if (!$idSection) $this->markTestSkipped('Paket DRAFT tujuan tak punya section.');

        $baru = app(\App\Services\SOP\SopItemService::class)->copyToPaket(
            (int) $item->Id_SOP_Paket_Item, (int) $paket->Id_SOP_Paket, (int) $idSection,
            1, app(\App\Services\SOP\SopItemVersionService::class)
        );
        $this->assertNotNull($baru, 'copyToPaket menolak item ini — fixture tak cocok.');

        $docBaru = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $baru)->value('Id_SOP_Dokumen');

        $this->assertNotNull($docBaru, 'Item hasil salin wajib punya dokumen.');
        $this->assertNotSame((int) $item->Id_SOP_Dokumen, (int) $docBaru,
            'Fase 2 masih 1:1. Berbagi dokumen adalah Fase 6; menyalakannya di sini membuat '
            . 'cloneActiveTo menulis Versi_Ke=1 pada dokumen yang sudah punya versi 1 -> error 2601.');

        foreach (DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $baru)->get() as $v) {
            $this->assertSame((int) $docBaru, (int) $v->Id_SOP_Dokumen,
                'Versi hasil salin bertaut ke dokumen item barunya.');
        }
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SopDokumenPenulisVersiTest`
Expected: FAIL — `Failed asserting that null matches expected int` pada test pertama (kolom masih NULL).

- [ ] **Step 3: Implementasi — `SopItemVersionService::create()`**

Ganti baris 41 dan tambahkan kolomnya:

```php
    public function create(int $idItem, array $data, int $userId): int
    {
        // Dokumen dipastikan ADA lebih dulu: penomoran versi dihitung per
        // dokumen (Task 4), jadi item tanpa dokumen tak punya sumbu penomoran.
        $idDokumen = app(SopDokumenService::class)->ensureForItem($idItem, $userId);

        $next = (int) (DB::table($this->table)->where('Id_SOP_Paket_Item', $idItem)->max('Versi_Ke') ?? 0) + 1;

        return DB::table($this->table)->insertGetId([
            'Id_SOP_Paket_Item'         => $idItem,
            'Id_SOP_Dokumen'            => $idDokumen,
            'Versi_Ke'                  => $next,
            // ...sisanya tidak berubah
```

- [ ] **Step 4: Implementasi — `cloneActiveTo()`**

Tambahkan satu baris sebelum `insertGetId` dan satu kolom di dalamnya:

```php
        // Item baru sudah dapat dokumennya sendiri di cloneRow(); di sini kita
        // cuma mengikutinya. ensureForItem() aman dipanggil dua kali.
        $idDokumen = app(SopDokumenService::class)->ensureForItem($newItemId, $userId);

        $newVersiId = DB::table($this->table)->insertGetId([
            'Id_SOP_Paket_Item'         => $newItemId,
            'Id_SOP_Dokumen'            => $idDokumen,
            'Versi_Ke'                  => 1,
            // ...sisanya tidak berubah
```

- [ ] **Step 5: Implementasi — `SopItemService::cloneRow()`**

Setelah `insertGetId` menghasilkan `$newId`, sebelum blok `isVersioned`:

```php
        /* Id_SOP_Dokumen SENGAJA tidak disalin dari $src: Fase 2 masih 1:1, dan
           menyalinnya berarti dua item berbagi satu dokumen — yang membuat
           cloneActiveTo menulis (dokumen, Versi_Ke=1) kedua dan ditolak unique
           index terfilter 26B. Item hasil salin dapat dokumennya SENDIRI. */
        if (SopTipeItem::isVersioned($src->Tipe_Item)) {
            app(SopDokumenService::class)->ensureForItem($newId, $userId);
            $versiSvc->cloneActiveTo((int) $src->Id_SOP_Paket_Item, $newId, $userId);
        }
```

- [ ] **Step 6: Implementasi — promosi draft di `SopPaketDraftService`**

Pada blok `:801-812`, sebelum menghitung `$activeVersion`:

```php
            if ($ready) {
                // Item live hasil promosi draft lahir tanpa Id_SOP_Dokumen
                // (tabel draft tak punya kolom itu). Dipastikan di sini supaya
                // revisi paket terbit tidak meluruhkan backfill Fase 1.
                $idDokumen = app(\App\Services\SOP\SopDokumenService::class)->ensureForItem($activeId, $userId);

                $activeVersion = ((int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $activeId)->max('Versi_Ke')) + 1;
                // ...update OBSOLETE tidak berubah...
                $pointer = DB::table('N_SOP_Item_Versi')->insertGetId([
                    'Id_SOP_Paket_Item' => $activeId,
                    'Id_SOP_Dokumen'    => $idDokumen,
                    'Versi_Ke' => $activeVersion, 'Status' => SopStatus::VERSI_PUBLISHED,
                    // ...sisanya tidak berubah
```

- [ ] **Step 7: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenPenulisVersiTest`
Expected: PASS (5 test)

Run: `php artisan sop:dokumen-verify`
Expected: exit 0

- [ ] **Step 8: Commit**

```bash
git add app/Services/SOP/SopItemVersionService.php app/Services/SOP/SopItemService.php app/Services/SOP/SopPaketDraftService.php tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php
git commit -m "fix(sop): ketiga penulis versi live mengisi Id_SOP_Dokumen"
```

---

# Task 4 — Penomoran & Pembacaan Versi Lewat Dokumen

Inilah pemindahan sumbu identitasnya. Selama 1 dokumen = 1 item, hasilnya identik — itulah yang membuat baseline jadi bukti sah.

**Files:**
- Modify: `app/Services/SOP/SopItemVersionService.php` (`create()`, `byItem()`)
- Test: `tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php` (tambah)

- [ ] **Step 1: Tulis test yang gagal**

```php
    public function test_penomoran_versi_dihitung_per_dokumen(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $tertinggi = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)->max('Versi_Ke');

        $idVersi = app(\App\Services\SOP\SopItemVersionService::class)
            ->create((int) $item->Id_SOP_Paket_Item, [], 1);

        $this->assertSame($tertinggi + 1,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Versi_Ke'),
            'Versi_Ke wajib lanjut dari nomor tertinggi DOKUMEN, bukan item.');
    }

    public function test_byItem_membaca_lewat_dokumen_dan_hasilnya_tetap_sama(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $svc = app(\App\Services\SOP\SopItemVersionService::class);
        $lewatDokumen = $svc->byItem((int) $item->Id_SOP_Paket_Item)->pluck('Versi_Ke')->all();

        $langsung = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('Versi_Ke')->pluck('Versi_Ke')->all();

        $this->assertSame($langsung, $lewatDokumen,
            'Fase 2 masih 1:1 — membaca lewat dokumen wajib menghasilkan daftar yang sama persis.');
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=test_penomoran_versi_dihitung_per_dokumen`
Expected: FAIL selama `create()` masih menghitung `max('Versi_Ke')` per `Id_SOP_Paket_Item`.

> Catatan jujur: dengan data 1:1 kedua test ini bisa **kebetulan lulus** sebelum implementasi. Itu bukan alasan melewatinya — ia justru gerbang yang menahan Fase 3, saat 1:1 berakhir. Bila ia lulus di Step 2, catat itu di ledger dan lanjutkan; jangan mengubah test agar "gagal dulu".

- [ ] **Step 3: Implementasi — penomoran per dokumen**

```php
        $idDokumen = app(SopDokumenService::class)->ensureForItem($idItem, $userId);

        /* Sumbu penomoran = DOKUMEN. Selama 1 dokumen = 1 item hasilnya identik
           dengan penomoran per item; begitu Fase 6 menyalakan berbagi, ini yang
           mencegah dua paket memproduksi "Versi 3" yang berbeda isi. Item tanpa
           dokumen (tipe tak ber-versi) mundur ke sumbu lama — satu-satunya
           jalan yang tersisa untuk mereka. */
        $next = $idDokumen !== null
            ? (int) (DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)->max('Versi_Ke') ?? 0) + 1
            : (int) (DB::table($this->table)->where('Id_SOP_Paket_Item', $idItem)->max('Versi_Ke') ?? 0) + 1;
```

- [ ] **Step 4: Implementasi — `byItem()` lewat dokumen**

```php
    /** Semua versi satu item (terbaru dulu) + nama konten library. Id encoded. */
    public function byItem(int $idItem)
    {
        $idDokumen = app(SopDokumenService::class)->forItem($idItem);

        return DB::table("{$this->table} as v")
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            // Item ber-dokumen dibaca lewat dokumennya; item tanpa dokumen
            // (tipe tak ber-versi, 18 baris yatim di dev) tetap lewat itemnya —
            // memaksa mereka lewat dokumen akan menyembunyikan barisnya.
            ->when($idDokumen !== null,
                fn ($q) => $q->where('v.Id_SOP_Dokumen', $idDokumen),
                fn ($q) => $q->where('v.Id_SOP_Paket_Item', $idItem))
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('v.Versi_Ke')
            ->select('v.*', 'k.Judul_Konten as Konten_Nama', 'k.Tipe_Konten as Konten_Tipe')
            ->get()
            ->map(function ($v) {
                $v->Id_SOP_Item_Versi = Hashids::connection('custom')->encode($v->Id_SOP_Item_Versi);
                return $v;
            });
    }
```

- [ ] **Step 5: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenPenulisVersiTest`
Expected: PASS (7 test)

- [ ] **Step 6: Commit**

```bash
git add app/Services/SOP/SopItemVersionService.php tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php
git commit -m "feat(sop): penomoran & pembacaan versi memakai dokumen sebagai sumbu"
```

---

# Task 5 — DDL 26B: Unique Index Terfilter

**Files:**
- Create: `plans/sop/sql/26B_sop_versi_dokumen_unique.sql`
- Create: `tests/Feature/LMS/SOP/SopDokumenFase2Test.php`

- [ ] **Step 1: Tulis gerbang skema yang gagal**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang skema Fase 2. DDL modul SOP dijalankan MANUAL (tak ada migration
 * Laravel), jadi test ini adalah satu-satunya yang membuktikan 26B benar-benar
 * terpasang sebelum kode mengandalkannya.
 */
class SopDokumenFase2Test extends TestCase
{
    use DatabaseTransactions;

    private function index(string $nama): ?object
    {
        return DB::selectOne("
            SELECT i.name, i.is_unique, i.has_filter, i.filter_definition
            FROM sys.indexes i
            WHERE i.object_id = OBJECT_ID('N_SOP_Item_Versi') AND i.name = ?", [$nama]);
    }

    public function test_unique_index_dokumen_versi_terpasang_dan_TERFILTER(): void
    {
        $ix = $this->index('UX_N_SOP_Item_Versi_Dokumen_Urut');
        $this->assertNotNull($ix, 'Jalankan plans/sop/sql/26B_sop_versi_dokumen_unique.sql lebih dulu.');
        $this->assertSame(1, (int) $ix->is_unique);
        $this->assertSame(1, (int) $ix->has_filter,
            'WAJIB terfilter: SQL Server menganggap NULL setara di unique index, '
            . 'jadi baris (NULL, 1) kedua akan ditolak error 2601.');
        $this->assertStringContainsString('Id_SOP_Dokumen', (string) $ix->filter_definition);
    }

    public function test_index_lama_per_item_SENGAJA_dipertahankan(): void
    {
        $this->assertNotNull($this->index('UX_N_SOP_Item_Versi_Urut'),
            'UX_N_SOP_Item_Versi_Urut adalah satu-satunya pagar untuk baris ber-dokumen NULL, '
            . 'yang sengaja tak disentuh index terfilter. Ia baru boleh dibuang di Fase 3.');
    }

    public function test_pasangan_dokumen_versi_unik_pada_baris_bertaut(): void
    {
        $ganda = DB::select("
            SELECT Id_SOP_Dokumen, Versi_Ke, COUNT(*) c FROM N_SOP_Item_Versi
            WHERE Id_SOP_Dokumen IS NOT NULL
            GROUP BY Id_SOP_Dokumen, Versi_Ke HAVING COUNT(*) > 1");
        $this->assertCount(0, $ganda, 'Fase 2 masih 1:1 — tak boleh ada (dokumen, versi) ganda.');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SopDokumenFase2Test`
Expected: FAIL — "Jalankan plans/sop/sql/26B_… lebih dulu."

- [ ] **Step 3: Tulis DDL-nya**

```sql
/* =====================================================================
   26B — Keunikan versi berpindah ke DOKUMEN (Fase 2 Master Dokumen SOP)

   TERFILTER, dan itu bukan gaya. sop:dokumen-verify melaporkan 18 dari 54
   baris N_SOP_Item_Versi ber-Id_SOP_Dokumen NULL (versi yatim milik item
   yang tipenya diubah menjauh dari tipe ber-versi — sah, dan tak boleh
   dipaksa punya dokumen). SQL Server menganggap NULL SETARA di unique
   index, jadi UNIQUE (Id_SOP_Dokumen, Versi_Ke) polos akan ditolak dengan
   error 2601 pada baris (NULL, 1) yang KEDUA.

   UX_N_SOP_Item_Versi_Urut (Id_SOP_Paket_Item, Versi_Ke) SENGAJA TIDAK
   di-drop di sini. Ia satu-satunya pagar yang melindungi ke-18 baris itu,
   sementara index baru sengaja tak menyentuh mereka. Selama 1 dokumen = 1
   item, kedua kendala terpenuhi bersamaan sehingga mempertahankannya tak
   berbiaya. Pembuangannya adalah pekerjaan Fase 3, bersama fan-out yang
   memang mengubah perilaku.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
                 AND name = 'UX_N_SOP_Item_Versi_Dokumen_Urut')
BEGIN
    CREATE UNIQUE INDEX UX_N_SOP_Item_Versi_Dokumen_Urut
        ON N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke)
        WHERE Id_SOP_Dokumen IS NOT NULL;
END
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila Fase 2 dibatalkan.

   DROP INDEX UX_N_SOP_Item_Versi_Dokumen_Urut ON N_SOP_Item_Versi;

   Tak ada data yang hilang: index ini murni kendala, tak menyimpan apa pun
   yang tak ada di kolomnya. Kode Fase 2 tetap berjalan tanpanya — yang
   hilang cuma penegakannya.
   --------------------------------------------------------------------- */
```

- [ ] **Step 4: Minta user menjalankan DDL di SSMS**

Berhenti di sini dan minta user mengeksekusi `plans/sop/sql/26B_sop_versi_dokumen_unique.sql`. Jangan lanjut sebelum ia mengonfirmasi.

- [ ] **Step 5: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenFase2Test`
Expected: PASS (3 test)

- [ ] **Step 6: Commit**

```bash
git add plans/sop/sql/26B_sop_versi_dokumen_unique.sql tests/Feature/LMS/SOP/SopDokumenFase2Test.php
git commit -m "feat(sop): unique index terfilter (Id_SOP_Dokumen, Versi_Ke)"
```

---

# Task 6 — Gerbang: Buktikan Nol Regresi

- [ ] **Step 1: Integritas dokumen**

Run: `php artisan sop:dokumen-verify`
Expected: exit 0, nol pelanggaran.

- [ ] **Step 2: Bukti nol regresi lawan baseline Task 1**

Run: `php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase2-sebelum.json`
Expected: "Identik dengan baseline — nol regresi."

**Bila ia melaporkan BERUBAH, jangan otomatis menyalahkan tanggal.** Pisahkan dulu, dengan cara yang sudah terbukti: (a) periksa apakah ada baris `N_SOP*` dibuat/diubah setelah stempel baseline; (b) ambil baseline dua kali berturut-turut dan bandingkan satu sama lain untuk memastikan sidiknya deterministik; (c) hitung ulang sidik paket yang berubah dengan `Carbon::setTestNow()` dibekukan ke stempel baseline — bila cocok, penyebabnya tanggal. Hanya setelah ketiganya, drift tanggal boleh disimpulkan.

- [ ] **Step 3: Suite SOP**

Run: `php artisan test --filter=Sop`
Expected: nol gagal.

- [ ] **Step 4: Suite penuh + pembuktian pra-eksisting**

Run: `php artisan test`

Bila ada kegagalan, buktikan ia bukan ulah cabang ini sebelum melaporkannya sebagai aman: checkout `master`, jalankan berkas yang sama, dan `comm` nama test yang gagal di kedua sisi. Hanya selisih kosong di kedua arah yang berarti nol regresi.

- [ ] **Step 5: Commit ledger**

```bash
git add plans/sop/
git commit -m "docs(sop): ledger Fase 2 + bukti nol regresi"
```

---

---

## Ledger Eksekusi (2026-08-09)

| Task | Commit | Catatan |
|---|---|---|
| 1 — Export rinci ke baseline | `2999f4ca` | Cacat ditemukan saat eksekusi (lihat bawah) |
| 2 — `SopDokumenService` | `4dbad128` | Merah dibuktikan dengan menyingkirkan berkas service |
| 3 — Tiga penulis versi | `aef2d570` | Kedua test merah lebih dulu — peluruhan terbukti nyata |
| 4 — Sumbu penomoran & pembacaan | `702e95e1` | Butuh test tambahan agar merah (lihat bawah) |
| 5 — DDL 26B | `314e08cb` | Dijalankan user di SSMS; pre-flight 0 pasangan ganda |

### Empat penyimpangan dari rencana, dan alasannya

**1. `array_keys($export->columns())` salah.** `columns()` mengembalikan *list* berisi `['key' => …]`, bukan array asosiatif, jadi ia menghasilkan `0,1,2,…`. Diganti `array_column($export->columns(), 'key')`.

**2. Tabel paket bernama `N_SOP_Master_Paket`, bukan `N_SOP_Paket`.** Test Task 3 di rencana awal menyebut tabel yang tidak ada.

**3. Cakupan sidik export rinci bolong — ditemukan oleh peringatan yang dipasang rencana ini sendiri.** Limit tetap 500 pada 916 baris di dev berarti **45% keluaran tak terlindungi**: regresi di sana akan dilaporkan "Identik. Nol regresi." Limitnya kini mengikuti jumlah baris sebenarnya dengan plafon keras 5000, kunci `tercakup` dicatat di JSON, dan `test_sidik_export_rinci_menutupi_SELURUH_baris_bukan_satu_halaman` menahannya agar tak kembali.

**4. Kedua test sumbu Task 4 lulus SEBELUM implementasi.** Rencana sudah memperingatkan ini dan melarang memelintir test agar merah. Yang dilakukan sebagai gantinya: menambah `test_sumbu_penomoran_benar_benar_dokumen_bukan_item`, yang menautkan item **kedua** ke dokumen yang sama untuk memisahkan kedua sumbu — sumbu item menghasilkan `Versi_Ke=1`, sumbu dokumen menghasilkan `N+1`. Ia merah sebelum perubahan dan hijau sesudahnya. Selisih itulah seluruh isi Fase 2.

### Temuan yang nilainya melampaui fase ini

Peluruhan backfill **bukan teori** — ia dibuktikan oleh dua test yang gagal sebelum Task 3. Sejak DDL 26A dijalankan, setiap item yang disalin dan setiap item yang lahir dari penerbitan revisi paket lahir tanpa dokumen. Kalau Fase 2 ditunda beberapa minggu lagi, `sop:dokumen-verify` akan menyala merah dan satu-satunya obatnya SQL manual.

---

## Gerbang Menuju Fase 3

Fase 3 (fan-out saat publish) **tidak boleh dimulai** sebelum:

- [ ] `sop:dokumen-verify` → exit 0
- [ ] `sop:dokumen-baseline --bandingkan=` → "Identik", di hari yang sama dengan pengambilannya
- [ ] `php artisan test --filter=Sop` → nol gagal
- [ ] **Semantik `rollback()` untuk dokumen bersama sudah diputuskan user.** Hari ini rollback sengaja senyap — tak merekonsiliasi (`SopItemVersionService.php:174`). Rekomendasi: pointer wajib ikut fan-out (kalau tidak, paket-paket menyimpang tanpa jejak) dan rekonsiliasi memakai jalur terantri yang sama dengan publish. Ini pilihan kebijakan kepatuhan, bukan teknis — **belum dijawab, dan Fase 3 tak bisa dirancang tanpanya.**

**Yang wajib dikerjakan di awal Fase 3:**

- [ ] **Drop `UX_N_SOP_Item_Versi_Urut`** (lihat Temuan 6). Begitu satu dokumen dirujuk banyak item, index per-item jadi menyesatkan dan bisa menolak penulisan yang sah.
- [ ] **`copyToPaket` beralih ke berbagi dokumen.** Fase 2 sengaja memberi dokumen sendiri (Temuan 7). Peralihannya wajib disertai penanganan `Versi_Ke`: item hasil salin tidak lagi memulai dari 1, ia mewarisi seluruh riwayat versi dokumennya — dan `cloneActiveTo()` berhenti relevan dalam bentuk sekarang.
