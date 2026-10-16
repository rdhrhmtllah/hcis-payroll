# SOP Watermark Toggle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let SOP admins turn PDF watermarking on/off per `DOKUMEN` item in the SOP Builder, without affecting any other item that happens to reuse the same content-library file.

**Architecture:** Add a `Flag_Watermark CHAR(1)` column (Y/T, default Y) to `N_SOP_Paket_Item` and its draft mirror `N_SOP_Paket_Draft_Item`, thread it through the existing item-CRUD/draft-publish pipeline exactly like the existing `Flag_Wajib`/`Flag_Highlight` flags, expose a toggle in `ItemFields.vue`, and at PDF-token-issuance time resolve the flag for the specific item being opened (not the shared file) to decide whether `SopSecurePdfController::buildWatermark()` runs. The page-image render pipeline (`SecurePdfPageRenderService::applyWatermarkGd`) already returns a clean image when the watermark payload is empty, so no changes are needed there.

**Tech Stack:** Laravel (PHP), MSSQL, Vue 2 (Options API) + Element Plus, axios.

## Global Constraints

- DB flag convention: `CHAR(1)`, `'Y'`/`'T'`, matching `Flag_Wajib`/`Flag_Camera`/`Flag_Highlight` on the same tables. Use `SopStatus::AKTIF` (`'Y'`) / `SopStatus::INAKTIF` (`'T'`) constants where the surrounding code already does (`SopPaketDraftService`, `SopSecurePdfAccessService`); use raw `'Y'`/`'T'` literals where the surrounding code already does (`SopItemService`).
- Default is always **watermark ON** (`'Y'`) — both the DB column default and every PHP fallback (`?? true`) must preserve today's always-on behavior unless an admin explicitly turns a specific item off.
- Fail-safe: if the reader can't identify which item is being opened (missing/invalid `id_item`), the backend must keep the watermark ON. Never fail open.
- This repo has no Laravel migrations for SOP tables — schema changes are hand-run idempotent DDL scripts under `plans/sop/sql/*.sql` (pattern: `IF COL_LENGTH(...) IS NULL BEGIN ALTER TABLE ... END; GO`). Follow that pattern; do not create a `database/migrations` file.
- SOP tests in `tests/Feature/Sop*Test.php` are **read-only against the real dev database** — no factories, no `RefreshDatabase`, no test-created rows, no mutation. Follow that convention: don't write a test that inserts/updates/deletes real rows.

---

### Task 1: Add `Flag_Watermark` column (DDL)

**Files:**
- Create: `plans/sop/sql/26A_sop_item_flag_watermark_ddl.sql`

**Interfaces:**
- Produces: `N_SOP_Paket_Item.Flag_Watermark` and `N_SOP_Paket_Draft_Item.Flag_Watermark`, both `CHAR(1) NOT NULL DEFAULT ('Y')`. Every later task in this plan depends on these two columns existing.

- [ ] **Step 1: Write the DDL script**

```sql
/* ============================================================================
   SOP 26A - DDL Flag_Watermark per item dokumen

   Toggle watermark keamanan per item DOKUMEN, di-set admin di SOP Builder.
   Default 'Y' (aktif) supaya perilaku watermark-selalu-aktif yang berlaku
   sekarang tidak berubah untuk item lama maupun baru sampai admin sengaja
   mematikannya.
   ============================================================================ */

IF COL_LENGTH('dbo.N_SOP_Paket_Item', 'Flag_Watermark') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item
        ADD Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y');
END;
GO

IF COL_LENGTH('dbo.N_SOP_Paket_Draft_Item', 'Flag_Watermark') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item
        ADD Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y');
END;
GO
```

- [ ] **Step 2: Run the script against the dev database**

This is a hand-run DDL script (this repo has no migration runner for SOP tables — see `plans/sop/sql/22A_sop_paket_versi_ddl.sql` for precedent). Run it against the dev MSSQL database (`team311/Web_HRIS`) via SSMS/`sqlcmd`/whatever tool is normally used for these scripts, **before** starting Task 2. This step cannot be automated by an agent without DB credentials/tooling — flag it to the human operator if you don't have a way to execute it yourself.

- [ ] **Step 3: Verify the columns exist**

Run:
```sql
SELECT COL_LENGTH('dbo.N_SOP_Paket_Item', 'Flag_Watermark') AS live_col,
       COL_LENGTH('dbo.N_SOP_Paket_Draft_Item', 'Flag_Watermark') AS draft_col;
```
Expected: both columns return a non-NULL length (`1`).

- [ ] **Step 4: Commit**

```bash
git add plans/sop/sql/26A_sop_item_flag_watermark_ddl.sql
git commit -m "sop: add Flag_Watermark column to item + draft-item tables"
```

---

### Task 2: Accept `Flag_Watermark` in item validation

**Files:**
- Modify: `app/Http/Controllers/Sop/SopAdminBuilderController.php:1399-1401`

**Interfaces:**
- Consumes: nothing new.
- Produces: `$data['Flag_Watermark']` (boolean, may be absent) available inside `storeItem()` and `updateItem()` after `validateItem()` runs — Tasks 3 and 4 depend on this key existing in the validated array.

- [ ] **Step 1: Add the validation rule**

In `private function validateItem(Request $request, bool $withTipe = true): array`, right after the existing `Flag_Highlight` rule:

```php
            'Flag_Camera' => 'nullable|in:Y,T',
            'Flag_Wajib' => 'nullable|boolean',
            'Flag_Highlight' => 'nullable|boolean',
            'Flag_Watermark' => 'nullable|boolean',
        ];
```

(This replaces the existing 3-line block ending in `];` at lines 1399-1402 with the same block plus the new rule.)

- [ ] **Step 2: Verify with a quick manual check**

Not independently testable via automated test (this is a private validation method reached only through HTTP). Verified together with Task 3/Task 9's manual QA — no standalone step here beyond confirming the file still parses:

```bash
php -l app/Http/Controllers/Sop/SopAdminBuilderController.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Sop/SopAdminBuilderController.php
git commit -m "sop: validate Flag_Watermark on item create/update"
```

---

### Task 3: Write `Flag_Watermark` in `SopItemService` (live table)

**Files:**
- Modify: `app/Services/SOP/SopItemService.php:39-68` (`create`), `:70-91` (`update`), `:126-162` (`cloneRow`)

**Interfaces:**
- Consumes: `$data['Flag_Watermark']` (bool|null, from Task 2's validated payload).
- Produces: `N_SOP_Paket_Item.Flag_Watermark` correctly populated for items on packages that are still in `DRAFT` status (never-published packages write directly to this table — see `SopAdminBuilderController::storeItem`/`updateItem`, the `$published` branch).

- [ ] **Step 1: Update `create()`**

Change:
```php
            'Flag_Camera'     => ($data['Flag_Camera'] ?? null) === 'Y' ? 'Y' : 'T',
            'Urutan'          => $maxUrutan + 1,
            'Flag_Wajib'      => ($data['Flag_Wajib'] ?? true) ? 'Y' : 'T',
            'Flag_Highlight'  => ($data['Flag_Highlight'] ?? false) ? 'Y' : 'T',
            'Created_By'      => $userId,
            'Created_At'      => now(),
            'Flag_Aktif'      => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    /** Update item. */
    public function update(int $id, array $data, int $userId): void
```
to:
```php
            'Flag_Camera'     => ($data['Flag_Camera'] ?? null) === 'Y' ? 'Y' : 'T',
            'Urutan'          => $maxUrutan + 1,
            'Flag_Wajib'      => ($data['Flag_Wajib'] ?? true) ? 'Y' : 'T',
            'Flag_Highlight'  => ($data['Flag_Highlight'] ?? false) ? 'Y' : 'T',
            'Flag_Watermark'  => ($data['Flag_Watermark'] ?? true) ? 'Y' : 'T',
            'Created_By'      => $userId,
            'Created_At'      => now(),
            'Flag_Aktif'      => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    /** Update item. */
    public function update(int $id, array $data, int $userId): void
```

- [ ] **Step 2: Update `update()`**

Change:
```php
            'Flag_Camera'     => ($data['Flag_Camera'] ?? null) === 'Y' ? 'Y' : 'T',
            'Flag_Wajib'      => ($data['Flag_Wajib'] ?? true) ? 'Y' : 'T',
            'Flag_Highlight'  => ($data['Flag_Highlight'] ?? false) ? 'Y' : 'T',
            'Updated_By'      => $userId,
            'Updated_At'      => now(),
        ]);
    }
```
(this is the block inside `update()`, distinct from the identical-looking block in `create()` above — it's the one immediately followed by `public function reorder`) to:
```php
            'Flag_Camera'     => ($data['Flag_Camera'] ?? null) === 'Y' ? 'Y' : 'T',
            'Flag_Wajib'      => ($data['Flag_Wajib'] ?? true) ? 'Y' : 'T',
            'Flag_Highlight'  => ($data['Flag_Highlight'] ?? false) ? 'Y' : 'T',
            'Flag_Watermark'  => ($data['Flag_Watermark'] ?? true) ? 'Y' : 'T',
            'Updated_By'      => $userId,
            'Updated_At'      => now(),
        ]);
    }
```

- [ ] **Step 3: Update `cloneRow()`**

Change:
```php
            'Flag_Camera'     => $src->Flag_Camera ?? 'T',
            'Urutan'          => $maxUrutan + 1,
            'Flag_Wajib'      => $src->Flag_Wajib,
            'Flag_Highlight'  => $src->Flag_Highlight,
            'Created_By'      => $userId,
            'Created_At'      => now(),
            'Flag_Aktif'      => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');

        if (SopTipeItem::isFile($src->Tipe_Item)) {
```
to:
```php
            'Flag_Camera'     => $src->Flag_Camera ?? 'T',
            'Urutan'          => $maxUrutan + 1,
            'Flag_Wajib'      => $src->Flag_Wajib,
            'Flag_Highlight'  => $src->Flag_Highlight,
            'Flag_Watermark'  => $src->Flag_Watermark ?? 'Y',
            'Created_By'      => $userId,
            'Created_At'      => now(),
            'Flag_Aktif'      => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');

        if (SopTipeItem::isFile($src->Tipe_Item)) {
```

- [ ] **Step 4: Syntax check**

```bash
php -l app/Services/SOP/SopItemService.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/SopItemService.php
git commit -m "sop: persist Flag_Watermark on live item create/update/clone"
```

---

### Task 4: Write `Flag_Watermark` in `SopPaketDraftService` (draft table + publish)

**Files:**
- Modify: `app/Services/SOP/SopPaketDraftService.php` at six spots: `:27-31` (`ITEM_COLUMNS`), `:131-149` (`ensure()` — seed draft from live), `:396-426` (`createItem()`), `:429-447` (`updateItem()`), `:470-514` (`cloneItemRow()`), `:337-352` (`items()` — reads `ITEM_COLUMNS`, no edit needed once the constant is updated), `:648-668` (`pendingChanges()` — `$itemFields` diff list), `:770-793` (`applyItems()` — draft→live publish copy), `:1010-1016` (field label map)

**Interfaces:**
- Consumes: `$data['Flag_Watermark']` (bool|null, from Task 2) in `createItem()`/`updateItem()`.
- Produces: `N_SOP_Paket_Draft_Item.Flag_Watermark` correctly populated for packages that are already `PUBLISHED` (edits to a published package go through the working-draft table, then get copied into `N_SOP_Paket_Item` by `applyItems()` on Publish). Also produces a human-readable "Watermark" label in the release-notes diff.

- [ ] **Step 1: Add to `ITEM_COLUMNS`**

Change (lines 27-31):
```php
    private const ITEM_COLUMNS = ['Id_SOP_Draft_Item', 'Id_SOP_Paket_Draft', 'Id_SOP_Paket_Item_Asal',
        'Id_SOP_Draft_Section', 'Id_SOP_Item_Versi_Target', 'Tipe_Item', 'Judul_Item', 'Nomor_SOP_Resmi',
        'Deskripsi_Item', 'Konten_Teks', 'Mode_Akses', 'URL_Tautan', 'Label_Tautan', 'Jenis_Quiz',
        'Nilai_Minimum', 'Id_Master_Ujian', 'Nama_Ujian', 'Flag_Camera', 'Urutan', 'Flag_Wajib',
        'Flag_Highlight', 'Flag_Aktif', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At'];
```
to:
```php
    private const ITEM_COLUMNS = ['Id_SOP_Draft_Item', 'Id_SOP_Paket_Draft', 'Id_SOP_Paket_Item_Asal',
        'Id_SOP_Draft_Section', 'Id_SOP_Item_Versi_Target', 'Tipe_Item', 'Judul_Item', 'Nomor_SOP_Resmi',
        'Deskripsi_Item', 'Konten_Teks', 'Mode_Akses', 'URL_Tautan', 'Label_Tautan', 'Jenis_Quiz',
        'Nilai_Minimum', 'Id_Master_Ujian', 'Nama_Ujian', 'Flag_Camera', 'Urutan', 'Flag_Wajib',
        'Flag_Highlight', 'Flag_Watermark', 'Flag_Aktif', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At'];
```

- [ ] **Step 2: Copy the flag when seeding a working draft from the live item (`ensure()`)**

Change (inside the `foreach (DB::table('N_SOP_Paket_Item')...)` loop):
```php
                // N_SOP_Paket_Item.Flag_Camera boleh NULL, tapi kolom kembarannya di tabel
                // draft NOT NULL. Menyalin apa adanya membuat pembuatan working copy gagal,
                // sehingga Builder untuk paket terbit balas 500 dan paket tak bisa direvisi.
                'Flag_Camera' => $item->Flag_Camera ?: 'T', 'Urutan' => $item->Urutan ?? 0,
                'Flag_Wajib' => $item->Flag_Wajib ?: 'Y', 'Flag_Highlight' => $item->Flag_Highlight ?: 'T',
                'Created_By' => $userId, 'Created_At' => now(),
            ]);
```
to:
```php
                // N_SOP_Paket_Item.Flag_Camera boleh NULL, tapi kolom kembarannya di tabel
                // draft NOT NULL. Menyalin apa adanya membuat pembuatan working copy gagal,
                // sehingga Builder untuk paket terbit balas 500 dan paket tak bisa direvisi.
                'Flag_Camera' => $item->Flag_Camera ?: 'T', 'Urutan' => $item->Urutan ?? 0,
                'Flag_Wajib' => $item->Flag_Wajib ?: 'Y', 'Flag_Highlight' => $item->Flag_Highlight ?: 'T',
                'Flag_Watermark' => $item->Flag_Watermark ?: 'Y',
                'Created_By' => $userId, 'Created_At' => now(),
            ]);
```

- [ ] **Step 3: Write it in `createItem()`**

Change:
```php
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === SopStatus::AKTIF ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Urutan' => $urutan, 'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Item');
```
to:
```php
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === SopStatus::AKTIF ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Urutan' => $urutan, 'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Watermark' => ($data['Flag_Watermark'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Item');
```

- [ ] **Step 4: Write it in `updateItem()`**

Change:
```php
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === SopStatus::AKTIF ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Updated_By' => $userId, 'Updated_At' => now(),
        ]);

        return true;
    }
```
to:
```php
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === SopStatus::AKTIF ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Watermark' => ($data['Flag_Watermark'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Updated_By' => $userId, 'Updated_At' => now(),
        ]);

        return true;
    }
```

- [ ] **Step 5: Copy it in `cloneItemRow()`**

Change:
```php
            'Nama_Ujian' => $source->Nama_Ujian, 'Flag_Camera' => $source->Flag_Camera,
            'Urutan' => ((int) DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $source->Id_SOP_Paket_Draft)->max('Urutan')) + 1,
            'Flag_Wajib' => $source->Flag_Wajib, 'Flag_Highlight' => $source->Flag_Highlight,
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Item');
```
to:
```php
            'Nama_Ujian' => $source->Nama_Ujian, 'Flag_Camera' => $source->Flag_Camera,
            'Urutan' => ((int) DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $source->Id_SOP_Paket_Draft)->max('Urutan')) + 1,
            'Flag_Wajib' => $source->Flag_Wajib, 'Flag_Highlight' => $source->Flag_Highlight,
            'Flag_Watermark' => $source->Flag_Watermark,
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Item');
```

- [ ] **Step 6: Add to the `pendingChanges()` diff field list**

Change:
```php
        $itemFields = ['Tipe_Item', 'Judul_Item', 'Nomor_SOP_Resmi', 'Deskripsi_Item', 'Konten_Teks', 'Mode_Akses',
            'URL_Tautan', 'Label_Tautan', 'Jenis_Quiz', 'Nilai_Minimum', 'Id_Master_Ujian', 'Nama_Ujian',
            'Flag_Camera', 'Urutan', 'Flag_Wajib', 'Flag_Highlight'];
```
to:
```php
        $itemFields = ['Tipe_Item', 'Judul_Item', 'Nomor_SOP_Resmi', 'Deskripsi_Item', 'Konten_Teks', 'Mode_Akses',
            'URL_Tautan', 'Label_Tautan', 'Jenis_Quiz', 'Nilai_Minimum', 'Id_Master_Ujian', 'Nama_Ujian',
            'Flag_Camera', 'Urutan', 'Flag_Wajib', 'Flag_Highlight', 'Flag_Watermark'];
```

- [ ] **Step 7: Copy it in `applyItems()` (draft → live, on Publish)**

Change:
```php
                'Flag_Camera' => $row->Flag_Camera, 'Urutan' => $row->Urutan, 'Flag_Wajib' => $row->Flag_Wajib,
                'Flag_Highlight' => $row->Flag_Highlight, 'Flag_Aktif' => SopStatus::AKTIF,
                'Updated_By' => $userId, 'Updated_At' => now(),
            ];
```
to:
```php
                'Flag_Camera' => $row->Flag_Camera, 'Urutan' => $row->Urutan, 'Flag_Wajib' => $row->Flag_Wajib,
                'Flag_Highlight' => $row->Flag_Highlight, 'Flag_Watermark' => $row->Flag_Watermark,
                'Flag_Aktif' => SopStatus::AKTIF,
                'Updated_By' => $userId, 'Updated_At' => now(),
            ];
```

- [ ] **Step 8: Add a diff label**

Change:
```php
            'Flag_Camera' => 'Kamera',
            'Flag_Wajib' => 'Wajib',
            'Flag_Highlight' => 'Sorotan',
        ][$field] ?? $field;
    }
```
to:
```php
            'Flag_Camera' => 'Kamera',
            'Flag_Wajib' => 'Wajib',
            'Flag_Highlight' => 'Sorotan',
            'Flag_Watermark' => 'Watermark',
        ][$field] ?? $field;
    }
```

- [ ] **Step 9: Syntax check**

```bash
php -l app/Services/SOP/SopPaketDraftService.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 10: Commit**

```bash
git add app/Services/SOP/SopPaketDraftService.php
git commit -m "sop: thread Flag_Watermark through draft create/update/clone/publish"
```

---

### Task 5: Resolve per-item watermark decision + automated test

**Files:**
- Modify: `app/Services/SOP/SopSecurePdfAccessService.php`
- Test: `tests/Feature/LMS/SOP/SopWatermarkToggleTest.php`

**Interfaces:**
- Consumes: `N_SOP_Paket_Item.Flag_Watermark` (from Task 1's column), `N_SOP_Paket_Item.Id_Item_Versi_Aktif`, `N_SOP_Item_Versi.Id_Master_Konten`, `N_LMS_Master_Konten_Berkas.Id_Master_Konten_Berkas`.
- Produces: `SopSecurePdfAccessService::watermarkAktif(int $realBerkasId, ?int $idItem): bool` — Task 6 (`SopSecurePdfController::token()`) calls this to decide whether to build the watermark payload.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LMS/SOP/SopWatermarkToggleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\SOP\SopSecurePdfAccessService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Watermark selalu aktif secara default, dan HANYA boleh mati bila item yang
 * dibuka (bukan sekadar file-nya) eksplisit mengizinkannya. Kelas ini
 * read-only terhadap DB dev, mengikuti konvensi test SOP lain — tidak ada
 * baris yang ditulis/diubah.
 */
class SopWatermarkToggleTest extends TestCase
{
    private function svc(): SopSecurePdfAccessService
    {
        return app(SopSecurePdfAccessService::class);
    }

    /** @return array{0:int,1:int} [idItem, realBerkasId] item DOKUMEN aktif nyata dengan versi & berkas. */
    private function itemDokumenNyata(): array
    {
        $row = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('i.Flag_Aktif', 'Y')
            ->where('i.Tipe_Item', 'DOKUMEN')
            ->first(['i.Id_SOP_Paket_Item', 'b.Id_Master_Konten_Berkas']);

        $this->assertNotNull($row, 'Butuh minimal satu item DOKUMEN aktif dengan versi & berkas di DB.');

        return [(int) $row->Id_SOP_Paket_Item, (int) $row->Id_Master_Konten_Berkas];
    }

    public function test_default_column_value_keeps_watermark_on(): void
    {
        [$idItem, $realBerkasId] = $this->itemDokumenNyata();

        $this->assertTrue($this->svc()->watermarkAktif($realBerkasId, $idItem));
    }

    public function test_missing_item_context_fails_safe_to_watermark_on(): void
    {
        [, $realBerkasId] = $this->itemDokumenNyata();

        $this->assertTrue($this->svc()->watermarkAktif($realBerkasId, null));
    }

    public function test_item_and_berkas_mismatch_fails_safe_to_watermark_on(): void
    {
        [$idItem] = $this->itemDokumenNyata();

        // Berkas id yang hampir pasti tidak ada / tidak berkaitan dengan $idItem.
        $this->assertTrue($this->svc()->watermarkAktif(999999999, $idItem));
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

```bash
php artisan test --filter=SopWatermarkToggleTest
```
Expected: FAIL — `Call to undefined method App\Services\SOP\SopSecurePdfAccessService::watermarkAktif()`. (If instead it fails with "Butuh minimal satu item DOKUMEN aktif..." the dev DB has no DOKUMEN item yet — pick any existing one manually and hardcode it temporarily to unblock, or ask the user; this precondition mirrors the existing `SopDokumenKeberlakuanTest::penugasanNyata()` pattern.)

- [ ] **Step 3: Implement `watermarkAktif()`**

Add this method to `app/Services/SOP/SopSecurePdfAccessService.php`, right after `canAccess()` (before `lewatJalurPublik()`):

```php
    /**
     * Apakah item yang sedang dibuka meminta watermark di-burn ke halamannya.
     *
     * Default AMAN: watermark tetap aktif kalau $idItem kosong (link/handshake
     * lama tanpa konteks item) ATAU tidak cocok dengan berkas yang diminta —
     * mematikan watermark HANYA terjadi lewat kecocokan eksplisit item+berkas,
     * supaya idItem asing tak bisa dipakai memadamkan watermark dokumen lain.
     */
    public function watermarkAktif(int $realBerkasId, ?int $idItem): bool
    {
        if (!$idItem) return true;

        $flag = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('i.Id_SOP_Paket_Item', $idItem)
            ->where('b.Id_Master_Konten_Berkas', $realBerkasId)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->value('i.Flag_Watermark');

        return $flag !== SopStatus::INAKTIF;
    }
```

- [ ] **Step 4: Run the test again to confirm it passes**

```bash
php artisan test --filter=SopWatermarkToggleTest
```
Expected: all 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/SopSecurePdfAccessService.php tests/Feature/LMS/SOP/SopWatermarkToggleTest.php
git commit -m "sop: resolve per-item watermark decision with fail-safe default"
```

---

### Task 6: Wire the decision into the PDF token endpoint

**Files:**
- Modify: `app/Http/Controllers/Sop/SopSecurePdfController.php:58-90`

**Interfaces:**
- Consumes: `SopSecurePdfAccessService::watermarkAktif()` (Task 5), `$request->input('id_item')` (Task 7 makes the frontend send this).
- Produces: the cached handshake payload's `watermark` key is `[]` when the item's flag is off, `[]` being the exact shape `SecurePdfPageRenderService::applyWatermarkGd()` already treats as "no watermark, return clean re-encoded image" (see `$lines === []` check) — no downstream LMS code needs to change.

- [ ] **Step 1: Update `token()`**

Change:
```php
        try {
            $tokenRaw = Str::random(40);
            $signature = LmsSecurePdfService::generate($tokenRaw);

            $watermark = $this->buildWatermark($request, $user);

            Cache::store('database')->put(
```
to:
```php
        try {
            $tokenRaw = Str::random(40);
            $signature = LmsSecurePdfService::generate($tokenRaw);

            $idItem = $this->access->decodeBerkasId((string) $request->input('id_item', ''));
            $watermark = $this->access->watermarkAktif($realId, $idItem)
                ? $this->buildWatermark($request, $user)
                : [];

            Cache::store('database')->put(
```

(`decodeBerkasId()` is a generic hashid decoder despite its name — it already handles hashids, raw numeric strings, and `''`/`'undefined'`/`'null'` by returning `null`, which is exactly the fail-safe input `watermarkAktif()` expects.)

- [ ] **Step 2: Syntax check**

```bash
php -l app/Http/Controllers/Sop/SopSecurePdfController.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Sop/SopSecurePdfController.php
git commit -m "sop: gate PDF watermark payload on the opened item's Flag_Watermark"
```

---

### Task 7: Send `id_item` from the PDF reader

**Files:**
- Modify: `resources/js/Pages/SOP/components/ui/SopPdfReader.vue:933-967` (`handshake()`), `:1069-1079` (`rehandshake()`)

**Interfaces:**
- Consumes: `this.idItem` (existing prop, already populated by both callers — `SopDetail.vue:1022` and `SopKontenViewer.vue:5`).
- Produces: `POST /api/v1/sop/secure-pdf/token/{berkasId}` now receives `{ id_item: <hashid> }` in its body, which Task 6's controller reads.

- [ ] **Step 1: Update `handshake()`**

Change:
```js
        async handshake() {
            this.err = '';
            try {
                const t = await axios.post(`/api/v1/sop/secure-pdf/token/${this.berkasId}`, {}, HDR);
```
to:
```js
        async handshake() {
            this.err = '';
            try {
                const t = await axios.post(
                    `/api/v1/sop/secure-pdf/token/${this.berkasId}`,
                    { id_item: this.idItem },
                    HDR,
                );
```

- [ ] **Step 2: Update `rehandshake()`**

Change:
```js
        async rehandshake() {
            try {
                const t = await axios.post(`/api/v1/sop/secure-pdf/token/${this.berkasId}`, {}, HDR);
```
to:
```js
        async rehandshake() {
            try {
                const t = await axios.post(
                    `/api/v1/sop/secure-pdf/token/${this.berkasId}`,
                    { id_item: this.idItem },
                    HDR,
                );
```

- [ ] **Step 3: Manual verification**

Automated JS tests don't exist for this component. Verify manually once Task 9's checklist is reachable — for now just confirm the dev build compiles:

```bash
npm run build
```
Expected: build succeeds with no errors referencing `SopPdfReader.vue`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/SOP/components/ui/SopPdfReader.vue
git commit -m "sop: send id_item in secure-pdf handshake so watermark resolves per item"
```

---

### Task 8: Default `Flag_Watermark` in the Builder's item form

**Files:**
- Modify: `resources/js/Pages/SOP/modules/admin/SopBuilder.vue:2231-2258` (`toggleItem()`), `:2261-2283` (`startNewItem()`)

**Interfaces:**
- Consumes: `it.Flag_Watermark` (Y/T string from the backend, once Task 3/4 populate it).
- Produces: `this.itemForm.Flag_Watermark` (boolean) — Task 9's `ItemFields.vue` toggle binds directly to this.

- [ ] **Step 1: Populate it when opening an existing item for edit (`toggleItem`)**

Change:
```js
                Flag_Camera: it.Flag_Camera === 'Y' ? 'Y' : 'T',
                Flag_Wajib: it.Flag_Wajib === 'Y',
                Flag_Highlight: it.Flag_Highlight === 'Y',
                Id_Master_Konten: null,
```
to:
```js
                Flag_Camera: it.Flag_Camera === 'Y' ? 'Y' : 'T',
                Flag_Wajib: it.Flag_Wajib === 'Y',
                Flag_Highlight: it.Flag_Highlight === 'Y',
                Flag_Watermark: it.Flag_Watermark !== 'T',
                Id_Master_Konten: null,
```

(`!== 'T'` rather than `=== 'Y'` so that a row inserted before this feature shipped — which, thanks to the DDL default, will already be `'Y'` — and any unexpected `NULL` both read as "on", matching the fail-safe-on principle from the Global Constraints.)

- [ ] **Step 2: Default it to `true` for new items (`startNewItem`)**

Change:
```js
                Mode_Akses: 'VIEW_ONLY',
                Flag_Wajib: true,
                Flag_Highlight: false,
                Id_Master_Konten: null,
```
to:
```js
                Mode_Akses: 'VIEW_ONLY',
                Flag_Wajib: true,
                Flag_Highlight: false,
                Flag_Watermark: true,
                Id_Master_Konten: null,
```

- [ ] **Step 3: Manual verification**

```bash
npm run build
```
Expected: build succeeds with no errors referencing `SopBuilder.vue`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/SOP/modules/admin/SopBuilder.vue
git commit -m "sop: default Flag_Watermark in the Builder item form"
```

---

### Task 9: Add the toggle to the item editor UI

**Files:**
- Modify: `resources/js/Pages/SOP/components/ui/ItemFields.vue:26-40`

**Interfaces:**
- Consumes: `form.Flag_Watermark` (boolean, from Task 8's `itemForm`).
- Produces: user-visible toggle inside the `Tipe_Item === 'DOKUMEN'` block of the item editor.

- [ ] **Step 1: Add the toggle**

Change:
```html
        <!-- DOKUMEN -->
        <template v-if="form.Tipe_Item === 'DOKUMEN'">
            <div class="mb-3"><label class="sop-label">Nomor SOP resmi</label><input v-model="form.Nomor_SOP_Resmi" class="sop-field" placeholder="mis. SOP-KEU-001" /></div>
            <div class="mb-3">
                <label class="sop-label">Hak akses berkas</label>
                <div class="acc-seg">
                    <button type="button" :class="{ active: form.Mode_Akses === 'VIEW_ONLY' }" @click="form.Mode_Akses = 'VIEW_ONLY'">
                        <i class="bi bi-eye"></i> Hanya lihat
                    </button>
                    <button type="button" :class="{ active: form.Mode_Akses === 'VIEW_UNDUH' }" @click="form.Mode_Akses = 'VIEW_UNDUH'">
                        <i class="bi bi-download"></i> Boleh unduh
                    </button>
                </div>
                <p class="field-hint"><i class="bi bi-info-circle"></i> Karyawan selalu bisa membaca. "Boleh unduh" menambah izin mengunduh berkas.</p>
            </div>
        </template>
```
to:
```html
        <!-- DOKUMEN -->
        <template v-if="form.Tipe_Item === 'DOKUMEN'">
            <div class="mb-3"><label class="sop-label">Nomor SOP resmi</label><input v-model="form.Nomor_SOP_Resmi" class="sop-field" placeholder="mis. SOP-KEU-001" /></div>
            <div class="mb-3">
                <label class="sop-label">Hak akses berkas</label>
                <div class="acc-seg">
                    <button type="button" :class="{ active: form.Mode_Akses === 'VIEW_ONLY' }" @click="form.Mode_Akses = 'VIEW_ONLY'">
                        <i class="bi bi-eye"></i> Hanya lihat
                    </button>
                    <button type="button" :class="{ active: form.Mode_Akses === 'VIEW_UNDUH' }" @click="form.Mode_Akses = 'VIEW_UNDUH'">
                        <i class="bi bi-download"></i> Boleh unduh
                    </button>
                </div>
                <p class="field-hint"><i class="bi bi-info-circle"></i> Karyawan selalu bisa membaca. "Boleh unduh" menambah izin mengunduh berkas.</p>
            </div>
            <div class="cam-toggle mb-3">
                <div class="cam-toggle-txt">
                    <span class="cam-toggle-title"><i class="bi bi-shield-lock"></i> Watermark keamanan</span>
                    <span class="field-hint">Saat aktif, tiap halaman dibubuhi watermark nama pembaca, IP, dan tanggal.</span>
                </div>
                <el-switch v-model="form.Flag_Watermark" active-text="Aktif" inactive-text="Nonaktif" inline-prompt />
            </div>
        </template>
```

(`el-switch` with no `active-value`/`inactive-value` overrides binds plain `true`/`false`, matching `form.Flag_Watermark`'s boolean type from Task 8 — same as the plain-boolean convention `Flag_Wajib`/`Flag_Highlight` already use. The `cam-toggle`/`cam-toggle-txt`/`cam-toggle-title` classes already exist in this file's scoped styles for the KUIS camera switch — reused here instead of introducing new CSS.)

- [ ] **Step 2: Manual verification**

```bash
npm run build
```
Expected: build succeeds with no errors referencing `ItemFields.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/SOP/components/ui/ItemFields.vue
git commit -m "sop: add watermark toggle to the DOKUMEN item editor"
```

---

### Task 10: End-to-end manual verification

**Files:** none (verification only).

**Interfaces:**
- Consumes: everything from Tasks 1-9.

No automated test can exercise the full Builder → publish → reader flow without mutating the real dev database (out of step with this repo's existing SOP test conventions — see Global Constraints). Run this checklist by hand against the dev environment before calling the feature done:

- [ ] **Step 1: New item defaults to watermark ON**

In SOP Builder, add a new `DOKUMEN` item to any package. Confirm the "Watermark keamanan" switch shows "Aktif" without touching it. Save. Query `N_SOP_Paket_Item.Flag_Watermark` (or `N_SOP_Paket_Draft_Item.Flag_Watermark` if the package was already published) for the new row — expect `'Y'`.

- [ ] **Step 2: Toggle OFF, publish, and confirm the reader shows a clean page**

Open that same item, flip the switch to "Nonaktif", save. If the package is `PUBLISHED`, go to Step 3 (Tinjau & Publish) and publish the release. Open the document as an employee (or via the Builder's live preview) through the PDF reader — confirm the page image has **no** diagonal watermark text and the "Watermark Keamanan Terdaftar" line in the info panel is absent (since `watermark` is `[]`).

- [ ] **Step 3: Old items are unaffected**

Open a `DOKUMEN` item that existed before this change shipped (never touched by an admin since). Confirm its switch shows "Aktif" (DDL default) and its reader page still shows the watermark as before.

- [ ] **Step 4: Shared content isn't cross-contaminated**

If two different `DOKUMEN` items in different packages point at the same content-library file (`Id_Master_Konten`), turn watermark OFF on one and confirm the other item still shows the watermark when read. This is the scenario `watermarkAktif()`'s item+berkas join exists to protect — if you don't have two such items in dev data, this step can be skipped, but note it as unverified rather than silently passing over it.

- [ ] **Step 5: Missing item context fails safe**

Manually POST to `/api/v1/sop/secure-pdf/token/{berkasId}` without an `id_item` field (e.g. via browser devtools on an old cached page, or curl with a valid session cookie) for a berkas whose item has watermark OFF. Confirm the response's `watermark` field is still populated (non-empty) — i.e., it does NOT silently inherit "off" from the item.

- [ ] **Step 6: Discarding a draft doesn't leak into the live table**

On a `PUBLISHED` package, open a `DOKUMEN` item and flip watermark OFF, but do **not** publish — instead use "Buang Draft Revisi" (discard working draft). Confirm `N_SOP_Paket_Item.Flag_Watermark` for that item is unchanged (still whatever it was before you touched the draft) — the draft-table isolation that already protects every other field (`Flag_Wajib`, `Mode_Akses`, etc.) must protect this one too, since Task 4 only ever writes the live table from `applyItems()` during Publish.
