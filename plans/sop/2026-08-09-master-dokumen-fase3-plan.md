# Master Dokumen SOP — FASE 3: Fan-out Revisi + Pagar Pengaman

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development` atau `superpowers:executing-plans` untuk mengerjakan rencana ini task demi task. Langkah memakai checkbox (`- [ ]`).

**Goal:** Ketika versi aktif sebuah dokumen berubah (publish atau rollback), **semua** item dan **semua** paket yang merujuk dokumen itu ikut bergerak — pointer digeser serentak, kewajiban karyawan direkonsiliasi lewat jalur terantri yang sama dengan publish, dan tak ada karyawan yang menerima notifikasi ganda untuk satu revisi.

**Architecture:** Mesin fan-out dibangun **selagi ia masih no-op**. Hari ini 1 dokumen = 1 item, jadi "semua paket perujuk" selalu berjumlah satu dan keluarannya identik dengan sekarang — persis pola yang membuat Fase 1–2 aman. Berbagi baru menyala di Fase 6; kalau mesinnya dibangun belakangan, ia lahir bersamaan dengan beban nyata dan tak ada keadaan tenang untuk mengujinya.

**Tech Stack:** Laravel 10, SQL Server (sqlsrv, tanpa RCSI), `DB::table()`, queue Google Cloud Tasks, PHPUnit + `DatabaseTransactions`.

---

## Global Constraints

- Tidak ada migration Laravel; DDL manual di `plans/sop/sql/`.
- Tidak ada Eloquent model / factory. Test memakai data seed nyata + `markTestSkipped()`.
- Bahasa Indonesia untuk nama, komentar, pesan, dan judul commit.
- **`Id_Item_Versi_Aktif` hanya boleh menunjuk versi `PUBLISHED`.** Berlaku pada setiap item yang disentuh fan-out, bukan hanya item pemicu.
- **Nol perubahan perilaku pada data 1:1.** Gerbangnya `sop:dokumen-baseline --bandingkan=` melaporkan "Identik" di hari yang sama.
- **Setiap pembatasan wajib dilaporkan.** Kalau fan-out membatasi jumlah paket, membatalkan sebagian, atau melewati jalur async karena worker mati — itu harus terlihat, bukan senyap.

---

## Keputusan Produk yang Sudah Diambil User (2026-08-09)

**Rollback IKUT fan-out.** Pointer versi aktif di **semua** paket perujuk mundur bersama, dan rekonsiliasinya memakai jalur terantri yang sama dengan publish.

Konsekuensi yang harus disengaja, bukan ditemukan belakangan: `rollback()` hari ini **sengaja senyap** — ia tak merekonsiliasi, sehingga karyawan yang sudah selesai membaca tidak dibuka ulang (`SopItemVersionService.php:167,197`). Keputusan ini **membalik** sifat itu. Rollback menjadi operasi berat yang membuka ulang kewajiban karyawan di banyak paket sekaligus. Itu pilihan sadar user demi konsistensi "satu dokumen terkendali = satu versi berlaku di mana pun", dan Task 4 wajib membuat konsekuensinya terlihat oleh admin **sebelum** ia menekan tombol.

---

## Temuan Riset yang Membentuk Rencana Ini

Diverifikasi pada kode dan DB dev 2026-08-09.

**1. `publish()` TIDAK merekonsiliasi apa pun.** Ia menggeser status versi, pointer aktif satu item, dan menonaktifkan integrasi ujian versi lama. Rekonsiliasi terjadi di tempat lain sepenuhnya: `SopAdminBuilderController:493` (`PAKET_PUBLISH`) dan `:581` (`MANUAL`).

**2. Pesan audit `publish()` menyesatkan.** Ia mencatat *"pointer aktif digeser, kewajiban di-reconcile"* (`SopItemVersionService.php:156`) padahal tak ada rekonsiliasi yang dipanggil — dibantah komentar dua baris di atasnya sendiri. Ini bukan sekadar kalimat jelek: jejak audit adalah bukti kepatuhan, dan bukti yang menyatakan sesuatu terjadi padahal tidak lebih buruk daripada tak ada jejak. **Diperbaiki di Task 3.**

**3. Lubang inti Fase 3 ada di sisi rekonsiliasi, bukan pointer.** Kalau dokumen D dipakai paket P1..P5 dan admin menerbitkan revisi lewat Builder P1, maka P1 direkonsiliasi lewat alurnya sendiri saat "Publish Paket" — sementara **P2..P5 tidak pernah tersentuh apa pun**. Karyawan di P2..P5 tetap membaca versi lama tanpa ada yang memberi tahu. Inilah yang Fase 3 tutup.

**4. Gerbang async sudah ada dan wajib dipakai ulang.** `SopSetting::int('penjadwalan_async_ambang', 30)` menggerbangi tiga jalur (`SopAdminBuilderController:485`, `SopAdminPenjadwalanController:289,528,612`). Di atas ambang → `ProsesReconcileSop` (queue). **Peringatan operasional yang wajib masuk rencana:** kalau worker queue tidak jalan, pekerjaan async tidak terjadi **diam-diam** — progres karyawan tak tersemai tanpa satu pun pesan error.

**5. Fan-out adalah no-op pada data hari ini.** Tidak ada satu pun dokumen yang dirujuk lebih dari satu item (invarian 1:1 dijaga `sop:dokumen-verify`). Karena itu **test wajib mensimulasikan berbagi** — menautkan item kedua ke dokumen yang sama di dalam transaksi test, teknik yang sudah terbukti di `test_sumbu_penomoran_benar_benar_dokumen_bukan_item` (Fase 2). Tanpa simulasi itu, seluruh Fase 3 akan "lulus" tanpa pernah dieksekusi.

---

## Struktur Berkas

| Berkas | Tanggung jawab | Aksi |
|---|---|---|
| `app/Services/SOP/SopDokumenFanoutService.php` | Menjawab "item & paket mana saja yang merujuk dokumen ini", dan menjalankan rekonsiliasi ke semuanya lewat gerbang ambang | **Buat** |
| `app/Services/SOP/SopItemVersionService.php` | `publish()` & `rollback()` menggeser pointer ke SEMUA item perujuk + memicu fan-out; pesan audit diperbaiki | Ubah |
| `app/Http/Controllers/Sop/SopAdminBuilderController.php` | Endpoint pratinjau dampak (berapa paket & karyawan terdampak) sebelum admin menekan publish/rollback | Ubah |
| `tests/Feature/LMS/SOP/SopDokumenFanoutTest.php` | Simulasi berbagi + invarian fan-out | **Buat** |

---

# Task 1 — Baseline Pra-Fase-3

Sidik jari menyerap nilai relatif-hari-ini, jadi baseline **wajib diambil di hari yang sama** dengan gerbang Task 6.

**Files:** `plans/sop/baseline/fase3-sebelum.json`

- [ ] **Step 1: Pastikan tree bersih & Fase 2 masih hijau**

```bash
git status --porcelain          # harus kosong
php artisan sop:dokumen-verify  # exit 0
```

- [ ] **Step 2: Ambil baseline**

```bash
php artisan sop:dokumen-baseline --simpan=plans/sop/baseline/fase3-sebelum.json
php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase3-sebelum.json
```
Expected: "Identik dengan baseline — nol regresi." Cakupan export harus penuh (`tercakup == jumlah`); kalau plafon 5000 tersentuh, naikkan dulu sebelum lanjut.

- [ ] **Step 3: Commit**

```bash
git add plans/sop/baseline/fase3-sebelum.json
git commit -m "chore(sop): baseline pra-Fase-3"
```

---

# Task 2 — `SopDokumenFanoutService`: Siapa Saja yang Terdampak

**Files:**
- Create: `app/Services/SOP/SopDokumenFanoutService.php`
- Test: `tests/Feature/LMS/SOP/SopDokumenFanoutTest.php`

**Interfaces (dipakai Task 3 & 4):**
- `itemPerujuk(int $idDokumen): array` — daftar `Id_SOP_Paket_Item` aktif yang merujuk dokumen
- `paketPerujuk(int $idDokumen): array` — daftar `Id_SOP_Paket` unik dari item-item itu
- `hitungDampak(int $idDokumen): array` — `['item' => int, 'paket' => int, 'penugasan' => int]`
- `rekonsiliasi(int $idDokumen, string $alasan, ?int $userId, ?int $kecualiPaket = null): array` — `['sinkron' => int[], 'antri' => int[]]`

- [ ] **Step 1: Test yang gagal — simulasikan berbagi**

```php
<?php

namespace Tests\Feature;

use App\Services\SOP\SopDokumenFanoutService;
use App\Services\SOP\Support\SopStatus;
use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDokumenFanoutTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Menautkan item KEDUA ke dokumen milik $item, di paket yang BERBEDA.
     * Keadaan ini baru sah di Fase 6 — di sini ia dipakai semata agar fan-out
     * benar-benar punya sesuatu untuk di-fan-out. Tanpa ini, seluruh Fase 3
     * "lulus" tanpa pernah dieksekusi (data dev 1:1 sepenuhnya).
     */
    private function tautkanItemKeduaKeDokumen(object $item): ?int
    {
        $paketLain = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (!$paketLain) return null;

        return DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket'   => $paketLain,
            'Tipe_Item'      => $item->Tipe_Item,
            'Judul_Item'     => 'Uji fan-out',
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Urutan'         => 9999,
            'Flag_Wajib'     => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By'     => 1,
            'Created_At'     => now(),
            'Flag_Aktif'     => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    private function itemBerVersi(): ?object
    {
        return DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
    }

    public function test_menemukan_semua_item_perujuk_bukan_hanya_pemicunya(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (!$kedua) $this->markTestSkipped('Tidak ada paket lain untuk simulasi berbagi.');

        $svc = app(SopDokumenFanoutService::class);
        $items = $svc->itemPerujuk((int) $item->Id_SOP_Dokumen);

        $this->assertContains((int) $item->Id_SOP_Paket_Item, $items);
        $this->assertContains((int) $kedua, $items,
            'Item kedua yang merujuk dokumen yang sama WAJIB ikut terdaftar — kalau tidak, '
            . 'karyawan di paketnya membaca versi lama tanpa ada yang memberi tahu.');
    }

    public function test_paket_perujuk_unik_dan_mencakup_paket_lain(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        if (!$this->tautkanItemKeduaKeDokumen($item)) $this->markTestSkipped('Tak ada paket lain.');

        $paket = app(SopDokumenFanoutService::class)->paketPerujuk((int) $item->Id_SOP_Dokumen);

        $this->assertSame(array_values(array_unique($paket)), array_values($paket),
            'Daftar paket wajib unik — kalau tidak, satu paket direkonsiliasi berkali-kali.');
        $this->assertGreaterThanOrEqual(2, count($paket));
    }

    public function test_item_nonaktif_tidak_ikut_fan_out(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (!$kedua) $this->markTestSkipped('Tak ada paket lain.');

        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $kedua)
            ->update(['Flag_Aktif' => SopStatus::INAKTIF]);

        $this->assertNotContains($kedua,
            app(SopDokumenFanoutService::class)->itemPerujuk((int) $item->Id_SOP_Dokumen),
            'Item nonaktif tak boleh ikut — fan-out ke item mati membuka ulang kewajiban hantu.');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=SopDokumenFanoutTest`
Expected: FAIL — `Class "App\Services\SOP\SopDokumenFanoutService" does not exist`

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Services\SOP;

use App\Jobs\Sop\ProsesReconcileSop;
use App\Services\SOP\Support\SopSetting;
use App\Services\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;

/**
 * Fan-out revisi dokumen — Fase 3 Master Dokumen SOP.
 *
 * MASALAH YANG DITUTUP. Sebelum ini, menerbitkan revisi lewat Builder paket P1
 * hanya merekonsiliasi P1 (SopAdminBuilderController:493). Kalau dokumen yang
 * sama juga dipakai P2..P5, karyawan di sana tetap membaca versi lama dan tak
 * ada satu pun jalur yang memberi tahu mereka.
 *
 * NO-OP HARI INI, DAN ITU DISENGAJA. Data dev 1 dokumen = 1 item, jadi
 * paketPerujuk() selalu mengembalikan satu paket dan keluarannya identik
 * dengan sebelum fase ini. Mesinnya dibangun selagi tenang supaya Fase 6 —
 * saat berbagi benar-benar menyala — tidak melahirkan mesin dan beban nyata
 * pada saat yang sama.
 */
class SopDokumenFanoutService
{
    /** Item aktif yang merujuk dokumen ini. */
    public function itemPerujuk(int $idDokumen): array
    {
        return DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Id_SOP_Paket_Item')
            ->pluck('Id_SOP_Paket_Item')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** Paket unik dari item-item perujuk. Unik WAJIB: satu paket dua item = rekonsiliasi ganda. */
    public function paketPerujuk(int $idDokumen): array
    {
        return DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->orderBy('Id_SOP_Paket')
            ->pluck('Id_SOP_Paket')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Dampak sebelum tombol ditekan. Dipakai endpoint pratinjau (Task 5) supaya
     * admin tahu bahwa satu klik menyentuh N paket dan M penugasan — terutama
     * penting untuk rollback, yang sejak Fase 3 membuka ulang kewajiban.
     */
    public function hitungDampak(int $idDokumen): array
    {
        $paket = $this->paketPerujuk($idDokumen);

        return [
            'item'      => count($this->itemPerujuk($idDokumen)),
            'paket'     => count($paket),
            'penugasan' => $paket === [] ? 0 : (int) DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->whereIn('pj.Id_SOP_Paket', $paket)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->count(),
        ];
    }

    /**
     * Rekonsiliasi ke semua paket perujuk, lewat gerbang ambang yang SAMA dengan
     * jalur penjadwalan (penjadwalan_async_ambang, default 30). $kecualiPaket
     * dipakai saat pemanggil sudah merekonsiliasi paketnya sendiri.
     *
     * PERINGATAN OPERASIONAL: jalur antri hanya benar-benar berjalan bila worker
     * queue hidup. Bila mati, pekerjaannya TIDAK terjadi tanpa satu pun pesan
     * error. Karena itu daftar paket yang diantrikan dikembalikan — pemanggil
     * WAJIB melaporkannya ke admin, bukan menelannya.
     */
    public function rekonsiliasi(int $idDokumen, string $alasan, ?int $userId, ?int $kecualiPaket = null): array
    {
        $ambang = SopSetting::int('penjadwalan_async_ambang', 30);
        $hasil = ['sinkron' => [], 'antri' => []];

        foreach ($this->paketPerujuk($idDokumen) as $idPaket) {
            if ($kecualiPaket !== null && $idPaket === $kecualiPaket) continue;

            $penugasan = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('pj.Id_SOP_Paket', $idPaket)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->count();

            if ($penugasan > $ambang) {
                ProsesReconcileSop::dispatch($idPaket, $alasan, $userId);
                $hasil['antri'][] = $idPaket;
            } else {
                app(SopReconciliationService::class)->reconcilePaket($idPaket, $alasan, $userId);
                $hasil['sinkron'][] = $idPaket;
            }
        }

        return $hasil;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenFanoutTest`
Expected: PASS (3 test)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/SopDokumenFanoutService.php tests/Feature/LMS/SOP/SopDokumenFanoutTest.php
git commit -m "feat(sop): SopDokumenFanoutService — peta item & paket perujuk dokumen"
```

---

# Task 3 — `publish()` Menggeser Pointer ke SEMUA Item Perujuk

**Files:**
- Modify: `app/Services/SOP/SopItemVersionService.php` (`publish()` :88-160)
- Test: `tests/Feature/LMS/SOP/SopDokumenFanoutTest.php` (tambah)

- [ ] **Step 1: Test yang gagal**

```php
    public function test_publish_menggeser_pointer_di_semua_item_perujuk(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $versi = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderByDesc('Versi_Ke')->first();
        if (!$versi) $this->markTestSkipped('Dokumen sumber tak punya versi.');

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (!$kedua) $this->markTestSkipped('Tak ada paket lain.');

        app(\App\Services\SOP\SopItemVersionService::class)
            ->publish((int) $versi->Id_SOP_Item_Versi, 1);

        $this->assertSame((int) $versi->Id_SOP_Item_Versi,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $kedua)->value('Id_Item_Versi_Aktif'),
            'Item kedua yang merujuk dokumen yang sama wajib ikut menunjuk versi baru. '
            . 'Kalau tidak, dua paket menyajikan revisi berbeda dari satu dokumen terkendali.');
    }

    public function test_pointer_hasil_fan_out_selalu_menunjuk_versi_published(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (!$kedua) $this->markTestSkipped('Tak ada paket lain.');

        $versi = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderByDesc('Versi_Ke')->first();
        if (!$versi) $this->markTestSkipped('Dokumen sumber tak punya versi.');

        app(\App\Services\SOP\SopItemVersionService::class)->publish((int) $versi->Id_SOP_Item_Versi, 1);

        foreach ([$item->Id_SOP_Paket_Item, $kedua] as $idItem) {
            $pointer = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_Item_Versi_Aktif');
            $this->assertSame(SopStatus::PAKET_PUBLISHED,
                DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $pointer)->value('Status'),
                'Pointer versi aktif hanya boleh menunjuk versi PUBLISHED — pada SETIAP item '
                . 'yang disentuh fan-out, bukan hanya item pemicu.');
        }
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=test_publish_menggeser_pointer_di_semua_item_perujuk`
Expected: FAIL — pointer item kedua masih nilai lamanya (atau NULL).

- [ ] **Step 3: Implementasi**

Di `publish()`, ganti ketiga blok yang berkunci `Id_SOP_Paket_Item` tunggal menjadi berkunci **dokumen**:

```php
        $fanout = app(SopDokumenFanoutService::class);
        $idDokumen = $versi->Id_SOP_Dokumen !== null ? (int) $versi->Id_SOP_Dokumen : null;

        /* Item yang disentuh = SEMUA item perujuk dokumen. Untuk versi tanpa
           dokumen (versi yatim milik tipe tak ber-versi) daftarnya mundur ke
           item versi itu sendiri — satu-satunya yang tersisa untuk mereka. */
        $itemTersentuh = $idDokumen !== null
            ? $fanout->itemPerujuk($idDokumen)
            : [(int) $versi->Id_SOP_Paket_Item];

        DB::transaction(function () use ($versi, $idVersi, $userId, $idDokumen, $itemTersentuh) {
            // obsolete versi published lama — berkunci DOKUMEN, bukan item
            $q = DB::table($this->table)->where('Status', SopStatus::PAKET_PUBLISHED)
                ->where('Id_SOP_Item_Versi', '<>', $idVersi);
            $idDokumen !== null
                ? $q->where('Id_SOP_Dokumen', $idDokumen)
                : $q->where('Id_SOP_Paket_Item', $versi->Id_SOP_Paket_Item);
            $q->update(['Status' => 'OBSOLETE', 'Updated_By' => $userId, 'Updated_At' => now()]);

            // publish versi ini (tidak berubah)
            DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->update([
                'Status'                  => SopStatus::PAKET_PUBLISHED,
                'Tanggal_Publish'         => now(),
                'Kode_Karyawan_Publisher' => Auth::user()?->karyawan?->Kode_Karyawan,
                'Updated_By'              => $userId,
                'Updated_At'              => now(),
            ]);

            // FAN-OUT POINTER: semua item perujuk, bukan hanya pemicunya
            DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Paket_Item', $itemTersentuh)
                ->update(['Id_Item_Versi_Aktif' => $idVersi, 'Updated_By' => $userId, 'Updated_At' => now()]);

            // PROTEKSI VERSI (KUIS) — ikut melebar ke semua item perujuk
            DB::table('N_SOP_Penjadwalan_Integrasi')
                ->whereIn('Id_SOP_Paket_Item', $itemTersentuh)
                ->where('Id_SOP_Item_Versi', '<>', $idVersi)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_At' => now()]);
        });
```

- [ ] **Step 4: Perbaiki pesan audit yang menyesatkan**

`publish()` mencatat *"kewajiban di-reconcile"* padahal tak ada rekonsiliasi yang dipanggil di sana. Jejak audit adalah bukti kepatuhan; bukti yang menyatakan sesuatu terjadi padahal tidak lebih buruk daripada tak ada jejak. Ganti menjadi:

```php
            'Versi item dipublikasikan. Pointer aktif digeser pada ' . count($itemTersentuh)
                . ' item perujuk. Rekonsiliasi kewajiban dijalankan terpisah saat Publish Paket.',
```

- [ ] **Step 5: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenFanoutTest`
Expected: PASS (5 test)

- [ ] **Step 6: Commit**

```bash
git add app/Services/SOP/SopItemVersionService.php tests/Feature/LMS/SOP/SopDokumenFanoutTest.php
git commit -m "feat(sop): publish menggeser pointer ke semua item perujuk dokumen"
```

---

# Task 4 — `rollback()` Ikut Fan-out (Keputusan User)

**Files:**
- Modify: `app/Services/SOP/SopItemVersionService.php` (`rollback()` :170-210)
- Test: `tests/Feature/LMS/SOP/SopDokumenFanoutTest.php` (tambah)

- [ ] **Step 1: Test yang gagal**

```php
    public function test_rollback_menggeser_pointer_di_semua_item_perujuk(): void
    {
        $item = $this->itemBerVersi();
        if (!$item) $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');

        $versiLama = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Versi_Ke')->first();
        if (!$versiLama) $this->markTestSkipped('Dokumen sumber tak punya versi.');

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (!$kedua) $this->markTestSkipped('Tak ada paket lain.');

        app(\App\Services\SOP\SopItemVersionService::class)
            ->rollback((int) $versiLama->Id_SOP_Item_Versi, 1);

        $this->assertSame((int) $versiLama->Id_SOP_Item_Versi,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $kedua)->value('Id_Item_Versi_Aktif'),
            'Keputusan produk 2026-08-09: rollback IKUT fan-out — kelima paket mundur bersama.');
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=test_rollback_menggeser_pointer_di_semua_item_perujuk`
Expected: FAIL — pointer item kedua tak bergeser.

- [ ] **Step 3: Implementasi**

Terapkan pola `$itemTersentuh` yang sama seperti Task 3 pada `rollback()`, lalu **tambahkan rekonsiliasi** yang sebelumnya sengaja tidak ada:

```php
        /* KEPUTUSAN PRODUK 2026-08-09 — MEMBALIK PERILAKU SEBELUMNYA.
           rollback() dulu SENGAJA senyap: karyawan yang sudah selesai membaca
           tidak dibuka ulang. Sejak dokumen bisa dipakai banyak paket, senyap
           berarti paket-paket menyimpang diam-diam tanpa jejak — persis masalah
           yang Master Dokumen dibuat untuk menyelesaikan. Rollback kini
           merekonsiliasi lewat jalur terantri yang SAMA dengan publish.

           Konsekuensinya berat dan harus disengaja: satu klik rollback membuka
           ulang kewajiban karyawan di SEMUA paket perujuk. Endpoint pratinjau
           (Task 5) wajib menampilkan angkanya sebelum tombol ditekan. */
        if ($idDokumen !== null) {
            $hasil = app(SopDokumenFanoutService::class)
                ->rekonsiliasi($idDokumen, 'VERSI_ROLLBACK', $userId);
        }
```

Jejak auditnya wajib memuat jumlah paket sinkron & terantri — karena jalur terantri tak berjalan bila worker mati, dan admin harus punya bekas untuk menelusurinya.

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=SopDokumenFanoutTest`
Expected: PASS (6 test)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/SopItemVersionService.php tests/Feature/LMS/SOP/SopDokumenFanoutTest.php
git commit -m "feat(sop): rollback ikut fan-out + rekonsiliasi (keputusan produk 2026-08-09)"
```

---

# Task 5 — Pratinjau Dampak Sebelum Tombol Ditekan

Rollback berubah dari operasi senyap menjadi operasi yang membuka ulang kewajiban banyak karyawan. Admin **wajib** melihat angkanya lebih dulu.

**Files:**
- Modify: `app/Http/Controllers/Sop/SopAdminBuilderController.php`
- Modify: `routes/Sop/SopAdminWeb.php`

- [ ] **Step 1: Endpoint**

`GET /api/v1/sop/admin/builder/versi/{id}/dampak` → `{ item, paket, penugasan, ambang, akan_antri: bool }`.

Wajib menolak token draft-overlay `di_` seperti endpoint lain di controller ini — PK tabel overlay beririsan dengan tabel live, dan endpoint yang lupa menolaknya akan memproses baris yang sama sekali lain sambil membalas sukses.

- [ ] **Step 2: Test**

```php
    public function test_endpoint_dampak_menolak_token_draft_overlay(): void
    {
        // Pola sama dengan SopBuilderCopyItemToPaketTest::draft overlay token
        // never copies an unrelated live item.
    }
```

Isi test ini mengikuti pola yang sudah ada di `tests/Feature/LMS/SOP/SopBuilderCopyItemToPaketTest.php` — salin strukturnya, jangan mengarang pola baru.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Sop/SopAdminBuilderController.php routes/Sop/SopAdminWeb.php tests/
git commit -m "feat(sop): pratinjau dampak fan-out sebelum publish/rollback"
```

---

# Task 6 — Gerbang

- [ ] `php artisan sop:dokumen-verify` → exit 0
- [ ] `php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase3-sebelum.json` → "Identik" (fan-out no-op pada data 1:1 — kalau ia BERUBAH, fan-out menyentuh sesuatu yang seharusnya tak tersentuh)
- [ ] `php artisan test --filter=Sop` → nol gagal
- [ ] `php artisan test` → kegagalan tetap 19 dan seluruhnya di keenam berkas pra-eksisting (`tests/Unit/AuthorizationScopeResolverTest.php`, `tests/Unit/ESS/Dashboard/HomepageLmsServiceTest.php`, `tests/Feature/ESS/Dashboard/HomepageControllerTest.php`, `tests/Feature/SYSTEM/Governance/LemburSubmitSmokeTest.php`, `tests/Feature/SYSTEM/Application/RuntimeRecoveryApiTest.php`, `tests/Feature/SYSTEM/Application/AllTransactionsReadParityTest.php`). Jalankan keenamnya terpisah: bila jumlahnya 19, sisa suite menyumbang nol.

---

---

## Ledger Eksekusi (2026-08-09)

| Task | Commit | Catatan |
|---|---|---|
| 1 — Baseline pra-Fase-3 | `9f4d…` | Cakupan export 916/916, self-compare "Identik" |
| 2 — `SopDokumenFanoutService` | `285cf549` | Merah dulu (class not found), 4 test simulasi berbagi |
| 3 — `publish()` fan-out | `04d1e7d2` | 2 dari 3 test merah dulu; jejak audit diperbaiki |
| 4 — `rollback()` fan-out | `5845eef8` | Merah dulu; durasi test 2s → 11s membuktikan rekonsiliasi jalan |
| 5 — Endpoint pratinjau dampak | `90038c43` | 1 test merah karena kesalahan saya, bukan endpointnya |

### Penyimpangan & koreksi

**1. Test endpoint memakai kunci `data`, seharusnya `result`.** `ResponseHelper::success()` membungkus payload di `result`. Testnya yang salah, bukan endpointnya — diperbaiki di test.

**2. Drop `UX_N_SOP_Item_Versi_Urut` dibatalkan sebagai prasyarat.** Gerbang Fase 2 menjadwalkannya di sini. Riset Fase 3 menunjukkan ia tak menghalangi: baris versi tetap membawa satu `Id_SOP_Paket_Item` dan pasangannya tetap unik meski dokumen dibagi. Dipindah ke Fase 4 sebagai kebersihan.

### Hasil gerbang

| Butir | Hasil |
|---|---|
| `sop:dokumen-verify` | exit 0 |
| Baseline nol regresi | "Identik" |
| Suite SOP | 155 lulus, 2 skip, 0 gagal (dari 144) |
| Suite penuh | 19 gagal, 288 lulus (dari 277) |

**Temuan sampingan: ada test flaky di keenam berkas pra-eksisting.** Perintah
yang sama dijalankan dua kali berturut memberi **20** lalu **19** kegagalan
(35 vs 36 lulus). Jalan kedua cocok persis dengan suite penuh. Ke-19 namanya
seluruhnya di wilayah non-SOP: authorization scope (9), homepage
LMS/scheduler (3), lembur submit smoke (3), runtime recovery (3), transaction
parity (1). Kesimpulan nol-regresi tetap berdiri, tapi ketidakdeterminanan
itu masalah pra-eksisting yang layak ditangani terpisah — kalau dibiarkan, ia
melemahkan setiap pembuktian nol-regresi berikutnya.

### Yang terbukti saat eksekusi

**Test rollback melonjak dari ~2s ke ~11s** begitu rekonsiliasi dipasang. Itu ukuran kasar beban yang kini ditanggung satu klik rollback — dan pembenaran paling konkret untuk endpoint pratinjau dampak di Task 5.

**Baseline "Identik" pada Fase 3 punya arti khusus.** Pada data 1:1, fan-out seharusnya no-op. Kalau baseline melaporkan BERUBAH, itu berarti mesin fan-out bocor ke paket yang bukan urusannya — satu-satunya cara ia bisa salah pada data hari ini.

---

## Gerbang Menuju Fase 4

- [ ] Seluruh Task 6 hijau
- [ ] **Dedup notifikasi diputuskan & diuji.** Satu karyawan yang ditugaskan ke 3 paket berbagi dokumen tak boleh menerima 3 notifikasi untuk satu revisi. Ini belum dirinci di sini karena bentuknya bergantung pada apa yang `reconcilePaket()` hasilkan saat fan-out benar-benar menyentuh >1 paket — **ukur dulu di Task 6, baru rancang.**
- [ ] **`UX_N_SOP_Item_Versi_Urut` masih dipertahankan.** Fase 2 menundanya ke sini, tapi riset Fase 3 menunjukkan ia tidak benar-benar menghalangi: baris versi tetap membawa satu `Id_SOP_Paket_Item` dan pasangannya tetap unik meski dokumen dibagi. Membuangnya karena itu **bukan prasyarat** — ia jadi pekerjaan kebersihan Fase 4, saat join versi→item ditulis ulang dan kolom itu benar-benar jadi vestigial.
