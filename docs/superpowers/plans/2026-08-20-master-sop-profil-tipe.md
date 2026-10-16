# Master SOP — Profil Tipe: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Panel item di Master SOP hanya meminta apa yang benar-benar berlaku
bagi tipenya, dan aturan itu ditegakkan server, bukan cuma disembunyikan layar.

**Architecture:** Satu peta deklaratif di `SopTipeItem` menyatakan blok mana yang
berlaku/wajib per tipe. Tiga pihak membacanya: penjaga terbit, validasi server,
dan UI (lewat cermin di `constants.js`). Data pengesahan non-dokumen yang
terlanjur terkumpul dibersihkan lewat DML, setelah penjaganya sadar tipe.

**Tech Stack:** PHP 8 / Laravel, SQL Server, Vue 3 (Options API pada modal
lama), PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-20-master-sop-profil-tipe-design.md`

## Global Constraints

- **Empat keputusan mengikat** (spec §2): pendekatan profil deklaratif; data
  pengesahan non-dokumen dibersihkan; `Tanggal_Berlaku` tetap untuk semua tipe;
  Nomor SOP Resmi hilang sama sekali dari layar untuk non-dokumen.
- **Arti tiga nilai profil:** `wajib` = dirender + ditolak server bila kosong;
  `berlaku` = dirender, boleh kosong; **tidak berlaku** = tidak dirender **dan**
  ditolak server bila tetap dikirim.
- **Urutan tidak boleh terbalik:** Task 2 (penjaga sadar tipe) wajib mendarat
  sebelum Task 6 (pembersihan data).
- **Dua pintu penerbitan:** Master SOP lewat controllernya, dan Builder lewat
  `terbitkanVersi()`. Aturan tinggal di service, bukan controller.
- **`Tanggal_Kadaluarsa` dan `Tanggal_Review_Berikutnya` sudah nol di modul
  SOP.** Profil tipe tidak boleh memunculkannya kembali untuk tipe mana pun.
- **Perubahan data wajib lewat DML** sesuai `AGENTS.md`: change ID,
  `database/dml/<id>/data-change.sql`, README di `docs/changes/<id>/`, entri di
  `database/ddl/status.json`, pendaftaran ke `N_SYSTEM_Database_Change`.
- **Jangan `git add -A`.**

Perintah uji:

```bash
env -u DATABASE_URL php artisan test --filter=<NamaTest>
npx vitest run <jalur>
```

---

## Struktur berkas

| Berkas | Tanggung jawab |
| --- | --- |
| `app/Services/LMS/SOP/Support/SopTipeItem.php` | **Sumber tunggal** profil tipe |
| `app/Services/LMS/SOP/SopDokumenService.php` | Penjaga terbit sadar tipe + tolak field tak berlaku |
| `resources/js/Pages/LMS/SOP/Shared/support/constants.js` | Cermin profil untuk UI |
| `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenDetailDrawer.vue` | Panel detail dirakit dari profil |
| `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue` | Form versi dirakit dari profil |
| `database/dml/<id>/data-change.sql` | Pembersihan data pengesahan non-dokumen |

---

### Task 1: Profil tipe di `SopTipeItem`

**Files:**
- Modify: `app/Services/LMS/SOP/Support/SopTipeItem.php`
- Test: `tests/Unit/LMS/SOP/SopProfilTipeTest.php` (create)

**Interfaces:**
- Produces: `SopTipeItem::BLOK_NOMOR_RESMI`, `BLOK_PENGESAHAN`, `BLOK_BERKAS`,
  `BLOK_UJIAN` (string const); `SopTipeItem::blokBerlaku(string $tipe, string $blok): bool`;
  `SopTipeItem::blokWajib(string $tipe, string $blok): bool`;
  `SopTipeItem::profil(string $tipe): array`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\Support\SopTipeItem as T;
use PHPUnit\Framework\TestCase;

class SopProfilTipeTest extends TestCase
{
    public function test_nomor_resmi_dan_pengesahan_hanya_milik_dokumen(): void
    {
        $this->assertTrue(T::blokBerlaku(T::DOKUMEN, T::BLOK_NOMOR_RESMI));
        $this->assertTrue(T::blokBerlaku(T::DOKUMEN, T::BLOK_PENGESAHAN));

        foreach ([T::FORM, T::VIDEO, T::KUIS] as $tipe) {
            $this->assertFalse(T::blokBerlaku($tipe, T::BLOK_NOMOR_RESMI), $tipe);
            $this->assertFalse(T::blokBerlaku($tipe, T::BLOK_PENGESAHAN), $tipe);
        }
    }

    public function test_berkas_untuk_tipe_file_dan_ujian_untuk_kuis(): void
    {
        foreach ([T::DOKUMEN, T::FORM, T::VIDEO] as $tipe) {
            $this->assertTrue(T::blokBerlaku($tipe, T::BLOK_BERKAS), $tipe);
            $this->assertFalse(T::blokBerlaku($tipe, T::BLOK_UJIAN), $tipe);
        }

        $this->assertFalse(T::blokBerlaku(T::KUIS, T::BLOK_BERKAS));
        $this->assertTrue(T::blokBerlaku(T::KUIS, T::BLOK_UJIAN));
    }

    public function test_blok_yang_berlaku_untuk_dokumen_juga_wajib(): void
    {
        $this->assertTrue(T::blokWajib(T::DOKUMEN, T::BLOK_NOMOR_RESMI));
        $this->assertTrue(T::blokWajib(T::KUIS, T::BLOK_UJIAN));
        $this->assertFalse(T::blokWajib(T::FORM, T::BLOK_PENGESAHAN));
    }

    /* Masa berlaku sudah dicabut 15 Agustus 2026. Profil tidak boleh jadi pintu
       masuknya kembali. */
    public function test_profil_tidak_mengenal_masa_berlaku(): void
    {
        foreach (T::VERSIONED as $tipe) {
            $blok = array_keys(T::profil($tipe));
            $this->assertNotContains('kadaluarsa', $blok, $tipe);
            $this->assertNotContains('review_berikutnya', $blok, $tipe);
        }
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

```bash
env -u DATABASE_URL php artisan test --filter=SopProfilTipeTest
```

Diharapkan: GAGAL — `blokBerlaku()` belum ada.

- [ ] **Step 3: Tambahkan profil ke `SopTipeItem`**

Sisipkan setelah konstanta `VERSIONED`:

```php
    /* ── Profil tipe ───────────────────────────────────────────────────
     * Blok mana yang berlaku bagi sebuah tipe, dan mana yang wajib.
     *
     * Ini SATU-SATUNYA tempat pertanyaan itu dijawab. Sebelum peta ini ada,
     * UI memutuskan sendiri dan tidak ada pihak kedua yang menyetujuinya —
     * itulah sebabnya 47% versi FORM punya nomor pengesahan sementara 0%
     * punya nomor SOP resmi: form mengisi apa yang disodorkan layar, bukan
     * apa yang dibutuhkan.
     *
     * Nilai: 'wajib' (dirender + ditolak bila kosong) atau 'berlaku'
     * (dirender, boleh kosong). Blok yang tidak terdaftar TIDAK BERLAKU:
     * tidak dirender, dan ditolak server bila tetap dikirim.
     */
    const BLOK_NOMOR_RESMI = 'nomor_resmi';

    const BLOK_PENGESAHAN = 'pengesahan';

    const BLOK_BERKAS = 'berkas';

    const BLOK_UJIAN = 'ujian';

    const WAJIB = 'wajib';

    const BERLAKU = 'berlaku';

    private const PROFIL = [
        self::DOKUMEN => [
            self::BLOK_NOMOR_RESMI => self::WAJIB,
            self::BLOK_PENGESAHAN => self::WAJIB,
            self::BLOK_BERKAS => self::WAJIB,
        ],
        self::FORM => [
            self::BLOK_BERKAS => self::WAJIB,
        ],
        self::VIDEO => [
            self::BLOK_BERKAS => self::WAJIB,
        ],
        self::KUIS => [
            self::BLOK_UJIAN => self::WAJIB,
        ],
    ];

    /** Blok apa saja yang berlaku bagi tipe ini, beserta tingkatnya. */
    public static function profil(string $tipe): array
    {
        return self::PROFIL[$tipe] ?? [];
    }

    public static function blokBerlaku(string $tipe, string $blok): bool
    {
        return isset(self::PROFIL[$tipe][$blok]);
    }

    public static function blokWajib(string $tipe, string $blok): bool
    {
        return (self::PROFIL[$tipe][$blok] ?? null) === self::WAJIB;
    }
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

```bash
env -u DATABASE_URL php artisan test --filter=SopProfilTipeTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/LMS/SOP/Support/SopTipeItem.php tests/Unit/LMS/SOP/SopProfilTipeTest.php
git commit -m "feat(sop): profil tipe deklaratif di SopTipeItem"
```

---

### Task 2: Penjaga terbit jadi sadar tipe

Wajib mendarat **sebelum** Task 5.

**Files:**
- Modify: `app/Services/LMS/SOP/SopDokumenService.php` (`pastikanAdaPengesah`)
- Test: `tests/Feature/LMS/SOP/SopTerbitPengesahPerTipeTest.php` (create)

**Interfaces:**
- Consumes: `SopTipeItem::blokBerlaku()` dari Task 1
- Produces: `pastikanAdaPengesah(int $idVersi)` lolos lebih awal bila tipe versi
  itu tidak memberlakukan blok pengesahan

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga "tak boleh terbit tanpa pengesah" harus berhenti berlaku untuk tipe
 * yang memang tidak mengenal pengesahan — kalau tidak, FORM/VIDEO/KUIS jadi
 * mustahil diterbitkan begitu blok tanda tangannya dibersihkan.
 */
class SopTerbitPengesahPerTipeTest extends TestCase
{
    use DatabaseTransactions;

    private function versiTanpaPengesah(string $tipe): int
    {
        $idDok = DB::table('N_SOP_Dokumen')->insertGetId([
            'Nomor_SOP_Resmi' => null,
            'Judul' => 'Uji '.$tipe,
            'Tipe' => $tipe,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ], 'Id_SOP_Dokumen');

        return DB::table('N_SOP_Item_Versi')->insertGetId([
            'Id_SOP_Dokumen' => $idDok,
            'Versi_Ke' => 1,
            'Status' => 'DRAFT',
            'Disahkan_Oleh' => null,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ], 'Id_SOP_Item_Versi');
    }

    private function panggilPenjaga(int $idVersi): void
    {
        $svc = app(\App\Services\LMS\SOP\SopDokumenService::class);
        $m = new \ReflectionMethod($svc, 'pastikanAdaPengesah');
        $m->setAccessible(true);
        $m->invoke($svc, $idVersi);
    }

    public function test_dokumen_tanpa_pengesah_tetap_ditolak(): void
    {
        $this->expectException(\DomainException::class);
        $this->panggilPenjaga($this->versiTanpaPengesah(SopTipeItem::DOKUMEN));
    }

    public function test_form_tanpa_pengesah_boleh_terbit(): void
    {
        $this->panggilPenjaga($this->versiTanpaPengesah(SopTipeItem::FORM));
        $this->addToAssertionCount(1);
    }

    public function test_kuis_tanpa_pengesah_boleh_terbit(): void
    {
        $this->panggilPenjaga($this->versiTanpaPengesah(SopTipeItem::KUIS));
        $this->addToAssertionCount(1);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH pada dua uji terakhir**

```bash
env -u DATABASE_URL php artisan test --filter=SopTerbitPengesahPerTipeTest
```

Diharapkan: `test_dokumen_...` HIJAU, dua lainnya GAGAL dengan `DomainException`.
Kalau ketiganya hijau sejak awal, penjaganya tidak diuji — periksa fixture-nya.

- [ ] **Step 3: Tambahkan gerbang tipe**

Di awal `pastikanAdaPengesah()`, sebelum pemeriksaan blok:

```php
        /* Tipe yang tidak mengenal pengesahan lolos lebih awal.
           Tanpa gerbang ini, FORM/VIDEO/KUIS jadi mustahil diterbitkan begitu
           blok tanda tangannya dibersihkan — penjaga menuntut sesuatu yang
           profilnya sendiri nyatakan tidak berlaku. */
        $tipe = (string) DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->value('d.Tipe');

        if ($tipe !== '' && ! SopTipeItem::blokBerlaku($tipe, SopTipeItem::BLOK_PENGESAHAN)) {
            return;
        }
```

`SopTipeItem` sudah diimpor di berkas ini (baris 8).

- [ ] **Step 4: Jalankan, pastikan HIJAU semua**

```bash
env -u DATABASE_URL php artisan test --filter=SopTerbitPengesahPerTipeTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/LMS/SOP/SopDokumenService.php tests/Feature/LMS/SOP/SopTerbitPengesahPerTipeTest.php
git commit -m "fix(sop): penjaga terbit berhenti menuntut pengesah pada tipe yang tak mengenalnya"
```

---

### Task 3: Cermin profil di frontend

**Files:**
- Modify: `resources/js/Pages/LMS/SOP/Shared/support/constants.js`
- Test: `resources/js/Pages/LMS/SOP/Shared/support/constants.test.js` (create)

**Interfaces:**
- Produces: `PROFIL_TIPE` (objek), `blokBerlaku(tipe, blok)`, `BLOK` (konstanta
  nama blok) — nama blok **identik** dengan Task 1

- [ ] **Step 1: Tulis uji yang gagal**

```js
import { describe, expect, it } from 'vitest';
import { BLOK, blokBerlaku } from './constants';

describe('profil tipe (cermin backend SopTipeItem)', () => {
    it('nomor resmi & pengesahan hanya milik DOKUMEN', () => {
        expect(blokBerlaku('DOKUMEN', BLOK.NOMOR_RESMI)).toBe(true);
        expect(blokBerlaku('DOKUMEN', BLOK.PENGESAHAN)).toBe(true);

        for (const t of ['FORM', 'VIDEO', 'KUIS']) {
            expect(blokBerlaku(t, BLOK.NOMOR_RESMI), t).toBe(false);
            expect(blokBerlaku(t, BLOK.PENGESAHAN), t).toBe(false);
        }
    });

    it('berkas untuk tipe file, ujian untuk kuis', () => {
        for (const t of ['DOKUMEN', 'FORM', 'VIDEO']) {
            expect(blokBerlaku(t, BLOK.BERKAS), t).toBe(true);
        }
        expect(blokBerlaku('KUIS', BLOK.BERKAS)).toBe(false);
        expect(blokBerlaku('KUIS', BLOK.UJIAN)).toBe(true);
    });

    it('tipe tak dikenal tidak memberlakukan blok apa pun', () => {
        expect(blokBerlaku('TAUTAN', BLOK.BERKAS)).toBe(false);
        expect(blokBerlaku(undefined, BLOK.BERKAS)).toBe(false);
    });
});
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Shared/support/constants.test.js
```

- [ ] **Step 3: Tambahkan cermin di `constants.js`**

```js
/*
 * Cermin app/Services/LMS/SOP/Support/SopTipeItem::PROFIL.
 *
 * Nama blok WAJIB identik dengan backend. Kalau keduanya menyimpang, layar dan
 * server tidak lagi sepakat soal field mana yang berlaku — dan itu persis
 * keadaan yang peta ini dibuat untuk mengakhiri.
 */
export const BLOK = {
    NOMOR_RESMI: 'nomor_resmi',
    PENGESAHAN: 'pengesahan',
    BERKAS: 'berkas',
    UJIAN: 'ujian',
};

export const PROFIL_TIPE = {
    DOKUMEN: [BLOK.NOMOR_RESMI, BLOK.PENGESAHAN, BLOK.BERKAS],
    FORM: [BLOK.BERKAS],
    VIDEO: [BLOK.BERKAS],
    KUIS: [BLOK.UJIAN],
};

export function blokBerlaku(tipe, blok) {
    return (PROFIL_TIPE[tipe] ?? []).includes(blok);
}
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Shared/support/constants.test.js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Shared/support/constants.js resources/js/Pages/LMS/SOP/Shared/support/constants.test.js
git commit -m "feat(sop): cermin profil tipe di constants frontend"
```

---

### Task 4: Panel dirakit dari profil

**Files:**
- Modify: `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue`
- Modify: `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenDetailDrawer.vue`
- Test: `resources/js/Pages/LMS/SOP/Shared/components/ui/SopPanelProfilTipe.test.js` (create)

**Interfaces:**
- Consumes: `blokBerlaku`, `BLOK` dari Task 3
- Produces: computed `pakaiPengesahan` dan `pakaiNomorResmi` pada kedua komponen

- [ ] **Step 1: Tulis uji yang gagal**

```js
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopDokumenVersiModal from './SopDokumenVersiModal.vue';

const pasang = (tipe) =>
    mount(SopDokumenVersiModal, {
        props: { dokumen: { Id_SOP_Dokumen: 1, Judul: 'Uji', Tipe: tipe }, show: true },
        global: { stubs: { teleport: true } },
    });

describe('panel versi mengikuti profil tipe', () => {
    it('DOKUMEN menampilkan blok pengesahan', () => {
        expect(pasang('DOKUMEN').vm.pakaiPengesahan).toBe(true);
    });

    it('FORM, VIDEO, dan KUIS tidak merender blok pengesahan', () => {
        for (const t of ['FORM', 'VIDEO', 'KUIS']) {
            const w = pasang(t);
            expect(w.vm.pakaiPengesahan, t).toBe(false);
            expect(w.html(), t).not.toContain('Nomor pengesahan');
        }
    });
});
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Shared/components/ui/SopPanelProfilTipe.test.js
```

- [ ] **Step 3: Tambahkan computed dan gerbang render**

Pada `SopDokumenVersiModal.vue`, di blok `computed` (dekat `isKuis`/`isForm`):

```js
        /* Blok mana yang berlaku ditentukan profil tipe, bukan diputuskan
           komponen ini. Sebelumnya field pengesahan dirender tanpa syarat tipe
           sama sekali — v-if-nya hanya memeriksa apakah nilainya ada. */
        pakaiPengesahan() {
            return blokBerlaku(this.dokumen?.Tipe, BLOK.PENGESAHAN);
        },
        pakaiNomorResmi() {
            return blokBerlaku(this.dokumen?.Tipe, BLOK.NOMOR_RESMI);
        },
```

Impor di bagian `<script>`:

```js
import { BLOK, blokBerlaku } from '@/Pages/LMS/SOP/Shared/support/constants';
```

Bungkus blok pengesahan di template (sekitar baris 250–270, yang memuat
`Nomor pengesahan` dan `Tanggal pengesahan`) dengan `v-if="pakaiPengesahan"`,
dan blok nomor resmi dengan `v-if="pakaiNomorResmi"`.

Terapkan pasangan computed yang sama di `SopDokumenDetailDrawer.vue` dan
bungkus baris ringkasan pengesahan/nomor resmi dengannya.

- [ ] **Step 4: Jalankan, pastikan HIJAU**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Shared/components/ui/SopPanelProfilTipe.test.js
```

- [ ] **Step 5: Pastikan uji SOP lain tak patah**

```bash
npx vitest run resources/js/Pages/LMS/SOP
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Shared/components/ui
git commit -m "feat(sop): panel versi & detail dirakit dari profil tipe"
```

---

### Task 5: Server menolak field yang tak berlaku

Konsumen ketiga profil (spec §3). Tanpa ini, menyembunyikan field di UI hanya
memindahkan masalahnya: Builder, impor, atau endpoint yang belum ada tetap bisa
menuliskan pengesahan ke sebuah FORM.

**Files:**
- Modify: `app/Services/LMS/SOP/SopDokumenService.php`
- Test: `tests/Feature/LMS/SOP/SopTolakFieldTakBerlakuTest.php` (create)

**Interfaces:**
- Consumes: `SopTipeItem::blokBerlaku()` dari Task 1
- Produces: `SopDokumenService::pastikanFieldSesuaiTipe(string $tipe, array $data): void`
  — melempar `\DomainException` bila `$data` memuat field milik blok yang tidak
  berlaku bagi `$tipe`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Tests\TestCase;

class SopTolakFieldTakBerlakuTest extends TestCase
{
    private function periksa(string $tipe, array $data): void
    {
        $svc = app(SopDokumenService::class);
        $m = new \ReflectionMethod($svc, 'pastikanFieldSesuaiTipe');
        $m->setAccessible(true);
        $m->invoke($svc, $tipe, $data);
    }

    public function test_form_menolak_nomor_pengesahan(): void
    {
        $this->expectException(\DomainException::class);
        $this->periksa(SopTipeItem::FORM, ['Nomor_Pengesahan' => 'SK-001/2026']);
    }

    public function test_kuis_menolak_nomor_sop_resmi(): void
    {
        $this->expectException(\DomainException::class);
        $this->periksa(SopTipeItem::KUIS, ['Nomor_SOP_Resmi' => 'SOP-01']);
    }

    public function test_dokumen_menerima_keduanya(): void
    {
        $this->periksa(SopTipeItem::DOKUMEN, [
            'Nomor_Pengesahan' => 'SK-001/2026',
            'Nomor_SOP_Resmi' => 'SOP-01',
        ]);
        $this->addToAssertionCount(1);
    }

    /* Nilai kosong bukan pelanggaran: form yang mengirim field kosong karena
       payload lama tidak boleh ditolak — yang dilarang adalah MENGISInya. */
    public function test_field_kosong_tidak_dianggap_pelanggaran(): void
    {
        $this->periksa(SopTipeItem::FORM, ['Nomor_Pengesahan' => null, 'Disahkan_Oleh' => '']);
        $this->addToAssertionCount(1);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

```bash
env -u DATABASE_URL php artisan test --filter=SopTolakFieldTakBerlakuTest
```

- [ ] **Step 3: Tambahkan penjaga di `SopDokumenService`**

```php
    /** Field yang dimiliki tiap blok profil. */
    private const FIELD_BLOK = [
        SopTipeItem::BLOK_NOMOR_RESMI => ['Nomor_SOP_Resmi'],
        SopTipeItem::BLOK_PENGESAHAN => [
            'Nomor_Pengesahan', 'Disahkan_Oleh', 'Jabatan_Pengesah',
            'Tanggal_Pengesahan', 'Kode_Karyawan_Pengesah',
        ],
    ];

    /**
     * Tolak field milik blok yang tidak berlaku bagi tipe ini.
     *
     * Menyembunyikan field di UI saja tidak cukup: Builder, impor, dan endpoint
     * yang belum ada tetap bisa menuliskannya. Persis begitulah 47% versi FORM
     * mendapat nomor pengesahan yang tak pernah dibutuhkan.
     *
     * Nilai KOSONG dibiarkan lolos — yang dilarang mengisinya, bukan
     * menyebutnya. Payload lama yang masih mengirim kunci kosong tidak boleh
     * ditolak.
     *
     * @throws \DomainException
     */
    private function pastikanFieldSesuaiTipe(string $tipe, array $data): void
    {
        foreach (self::FIELD_BLOK as $blok => $fields) {
            if (SopTipeItem::blokBerlaku($tipe, $blok)) {
                continue;
            }

            foreach ($fields as $f) {
                if (array_key_exists($f, $data) && trim((string) $data[$f]) !== '') {
                    throw new \DomainException(
                        'Field "'.$f.'" tidak berlaku untuk tipe '.$tipe.'. '
                        .'Blok "'.$blok.'" hanya dipakai tipe DOKUMEN.'
                    );
                }
            }
        }
    }
```

- [ ] **Step 4: Panggil dari jalur simpan versi**

Panggil `$this->pastikanFieldSesuaiTipe($tipe, $data);` di awal metode yang
menulis versi (sebelum transaksi), dengan `$tipe` dibaca dari
`N_SOP_Dokumen.Tipe` milik versi tersebut.

- [ ] **Step 5: Jalankan, pastikan HIJAU**

```bash
env -u DATABASE_URL php artisan test --filter=SopTolakFieldTakBerlakuTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/LMS/SOP/SopDokumenService.php tests/Feature/LMS/SOP/SopTolakFieldTakBerlakuTest.php
git commit -m "feat(sop): server menolak field yang tak berlaku bagi tipe item"
```

---

### Task 6: Pembersihan data pengesahan non-dokumen (DML)

Hanya setelah Task 2 mendarat.

**Files:**
- Create: `database/dml/DML-2026-013-bersihkan-pengesahan-non-dokumen/data-change.sql`
- Create: `docs/changes/DML-2026-013-bersihkan-pengesahan-non-dokumen/README.md`
- Modify: `database/ddl/status.json`

- [ ] **Step 1: Rekam angka SEBELUM**

```bash
php artisan tinker --execute="
\$q = DB::table('N_SOP_Item_Versi as v')->join('N_SOP_Dokumen as d','d.Id_SOP_Dokumen','=','v.Id_SOP_Dokumen')->where('d.Tipe','<>','DOKUMEN');
echo 'versi berkolom pengesahan : '.(clone \$q)->where(function(\$w){ foreach(['Nomor_Pengesahan','Disahkan_Oleh','Jabatan_Pengesah','Tanggal_Pengesahan','Kode_Karyawan_Pengesah'] as \$k) \$w->orWhereNotNull('v.'.\$k); })->count().PHP_EOL;
"
```

Diharapkan: **10**. Kalau bukan 10, datanya sudah berubah sejak spec ditulis —
berhenti dan laporkan sebelum menjalankan DML.

- [ ] **Step 2: Tulis `data-change.sql`**

```sql
/* DML-2026-013 — Bersihkan data pengesahan pada item SOP non-dokumen.

   Pengesahan hanya berlaku untuk tipe DOKUMEN (profil tipe, SopTipeItem).
   Data pada FORM/VIDEO/KUIS terkumpul karena UI menyodorkan fieldnya, bukan
   karena dibutuhkan — 47% versi FORM punya nomor pengesahan sementara 0%
   punya nomor SOP resmi.

   Termasuk 1 versi berstatus PUBLISHED. Itu keputusan sadar pemilik proyek,
   bukan efek samping: pembersihan diminta menyeluruh agar tidak ambigu.

   Prasyarat: penjaga terbit sudah sadar tipe. Tanpa itu, versi non-dokumen
   yang kehilangan blok tanda tangan tidak bisa diterbitkan lagi.
*/

BEGIN TRANSACTION;

DELETE p
FROM N_SOP_Item_Versi_Pengesah p
INNER JOIN N_SOP_Item_Versi v ON v.Id_SOP_Item_Versi = p.Id_SOP_Item_Versi
INNER JOIN N_SOP_Dokumen d ON d.Id_SOP_Dokumen = v.Id_SOP_Dokumen
WHERE d.Tipe <> 'DOKUMEN';

UPDATE v
SET v.Nomor_Pengesahan = NULL,
    v.Disahkan_Oleh = NULL,
    v.Jabatan_Pengesah = NULL,
    v.Tanggal_Pengesahan = NULL,
    v.Kode_Karyawan_Pengesah = NULL
FROM N_SOP_Item_Versi v
INNER JOIN N_SOP_Dokumen d ON d.Id_SOP_Dokumen = v.Id_SOP_Dokumen
WHERE d.Tipe <> 'DOKUMEN';

COMMIT;
```

`Tanggal_Berlaku` sengaja **tidak** disentuh (keputusan mengikat).

- [ ] **Step 3: Tulis README perubahan**

`docs/changes/DML-2026-013-bersihkan-pengesahan-non-dokumen/README.md` memuat:
sasaran, alasan, cakupan persis (10 versi + 17 baris blok pada 7 versi, 1 di
antaranya PUBLISHED), prasyarat Task 2, dan cara memverifikasi sesudahnya.

- [ ] **Step 4: Daftarkan ke `status.json` dan `N_SYSTEM_Database_Change`**

Ikuti pola entri DML yang sudah ada di `database/ddl/status.json`.

- [ ] **Step 5: Jalankan dan verifikasi angka SESUDAH**

```bash
php artisan tinker --execute="
\$q = DB::table('N_SOP_Item_Versi as v')->join('N_SOP_Dokumen as d','d.Id_SOP_Dokumen','=','v.Id_SOP_Dokumen')->where('d.Tipe','<>','DOKUMEN');
echo 'versi berkolom pengesahan : '.(clone \$q)->where(function(\$w){ foreach(['Nomor_Pengesahan','Disahkan_Oleh','Jabatan_Pengesah','Tanggal_Pengesahan','Kode_Karyawan_Pengesah'] as \$k) \$w->orWhereNotNull('v.'.\$k); })->count().PHP_EOL;
echo 'baris blok pengesah       : '.DB::table('N_SOP_Item_Versi_Pengesah as p')->join('N_SOP_Item_Versi as v','v.Id_SOP_Item_Versi','=','p.Id_SOP_Item_Versi')->join('N_SOP_Dokumen as d','d.Id_SOP_Dokumen','=','v.Id_SOP_Dokumen')->where('d.Tipe','<>','DOKUMEN')->count().PHP_EOL;
echo 'Tanggal_Berlaku non-dokumen (harus TETAP): '.(clone \$q)->whereNotNull('v.Tanggal_Berlaku')->count().PHP_EOL;
"
```

Diharapkan: 0, 0, dan **6** (tidak berubah).

- [ ] **Step 6: Commit**

```bash
git add database/dml/DML-2026-013-bersihkan-pengesahan-non-dokumen docs/changes/DML-2026-013-bersihkan-pengesahan-non-dokumen database/ddl/status.json
git commit -m "chore(db): DML-2026-013 bersihkan pengesahan non-dokumen"
```

---

## Verifikasi akhir

- [ ] `env -u DATABASE_URL php artisan test --filter="SopProfilTipe|SopTerbitPengesahPerTipe"` hijau
- [ ] `npx vitest run resources/js/Pages/LMS/SOP` hijau
- [ ] `npx vitest run` — suite penuh hijau
- [ ] Buka Master SOP: item DOKUMEN menampilkan nomor resmi + blok pengesahan;
      FORM/VIDEO/KUIS tidak menampilkan keduanya
- [ ] Terbitkan satu versi FORM lewat **kedua** pintu (Master SOP dan Builder) —
      keduanya harus berhasil tanpa blok tanda tangan
- [ ] `git status` bersih dari berkas tak terduga
