# Master Dokumen SOP — FASE 4: Tulis Ulang Join Versi→Item

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: `superpowers:subagent-driven-development` atau `superpowers:executing-plans`. Langkah memakai checkbox (`- [ ]`).

**Goal:** Menghapus setiap tempat yang menyimpulkan **item dari versi** lewat FK tunggal `N_SOP_Item_Versi.Id_SOP_Paket_Item`, menggantinya dengan penelusuran lewat **dokumen + paket yang sedang ditanya**. Tanpa ini, menyalakan berbagi di Fase 6 membuat monitoring salah atribusi dan otorisasi salah menolak — keduanya diam-diam.

**Architecture:** Sama seperti tiga fase sebelumnya: perubahan ditulis selagi masih no-op pada data 1:1, dibuktikan lewat baseline, dan diuji dengan simulasi berbagi karena data nyata belum punya satu pun dokumen berbagi.

**Tech Stack:** Laravel 10, SQL Server (sqlsrv, tanpa RCSI), `DB::table()`, PHPUnit + `DatabaseTransactions`.

---

## Global Constraints

- Tidak ada migration Laravel; DDL manual di `plans/sop/sql/`, digerbangi test skema.
- Tidak ada Eloquent model / factory; test memakai data seed nyata + `markTestSkipped()`.
- Bahasa Indonesia untuk nama, komentar, pesan, dan judul commit.
- **Nol perubahan perilaku pada data 1:1**, dibuktikan `sop:dokumen-baseline --bandingkan=` **di hari yang sama**.
- **Versi tanpa dokumen wajib tetap berfungsi.** 18 baris yatim di dev (milik item PARAGRAF); setiap join baru wajib punya jalur mundur untuk mereka, bukan menghilangkan barisnya.
- **Setiap pembatasan dilaporkan**, tidak pernah senyap.

---

## Temuan Riset yang Membentuk Rencana Ini

Diverifikasi pada kode 2026-08-10.

**1. Ketiga situs gagal dengan cara YANG SAMA — dugaan awal saya keliru.** Rancangan induk menggambarkan tiga masalah berbeda (penggandaan baris di monitoring, otorisasi salah, akses PDF melebar). Yang sebenarnya: ketiganya menempuh `versi → i.Id_SOP_Paket_Item → paket`, dan FK itu menunjuk **satu** item — yaitu item yang kebetulan membuat versi tersebut. Tidak ada penggandaan baris sama sekali; yang terjadi adalah **atribusi ke item/paket yang salah**.

| Situs | Akibat nyata saat dokumen dibagi |
|---|---|
| `SopMonitoringService::detailPaket:203` | Baris rollup paket P2 menampilkan `Judul_Item`, `Tipe_Item`, dan `Urutan` milik item di **P1** |
| `SopMonitoringService::sectionCoverage:247` | Cakupan dikelompokkan ke `Id_SOP_Section` milik paket lain — section yang bahkan tak ada di P2 |
| `SopProgressReadService::versiVisibleInPaket:971` | Karyawan sah di P2 **DITOLAK**, karena versinya "milik" item di P1 |
| `SopSecurePdfAccessService:37,78` | Sama: penugasan di P2 tak ditemukan, akses PDF **ditolak** padahal sah |

**2. Akses PDF bukan "melebar", melainkan "salah tolak".** Rancangan induk menulis akses akan melebar ke semua paket pemegang dokumen. Kode sebenarnya sudah berkunci **konten** (`v.Id_Master_Konten`), dan komentarnya sendiri sudah menyadari satu konten dipakai banyak paket (`SopSecurePdfAccessService`, docblock `lewatJalurPublik`). Yang rusak justru sebaliknya: penelusuran ke paket lewat FK item tunggal **mempersempit** hasilnya. Pelebaran ke semua paket pemegang dokumen adalah **perbaikan yang diinginkan**, bukan efek samping yang harus ditakuti — tapi ia tetap perubahan semantik keamanan dan wajib punya test tersendiri.

**3. `N_SOP_Coverage_Rollup` tidak menyimpan `Id_SOP_Paket_Item`.** Kolomnya: `Id_SOP_Coverage_Rollup, Id_SOP_Paket, Id_SOP_Item_Versi, Total_Target, Jumlah_Dibuka, Jumlah_Dikonfirmasi, Persen_Dikonfirmasi, Updated_At`. Karena itu perbaikan monitoring **tidak bisa** sekadar "lewat rollup → Id_SOP_Paket_Item" seperti tertulis di rancangan induk — kolom itu tak ada. Yang dipakai: `r.Id_SOP_Paket` **plus** `v.Id_SOP_Dokumen` sebagai pasangan kunci ke item yang benar.

**4. Pola perbaikannya tunggal dan seragam:**

```
LAMA:  join item on i.Id_SOP_Paket_Item = v.Id_SOP_Paket_Item
BARU:  join item on i.Id_SOP_Dokumen   = v.Id_SOP_Dokumen
                and i.Id_SOP_Paket     = <paket yang sedang ditanya>
                and i.Flag_Aktif       = 'Y'
       (mundur ke pola LAMA bila v.Id_SOP_Dokumen NULL)
```

Pada data 1:1 keduanya memilih baris yang sama persis — itulah yang membuat baseline bisa jadi bukti.

---

## Struktur Berkas

| Berkas | Tanggung jawab | Aksi |
|---|---|---|
| `app/Services/SOP/SopMonitoringService.php` | `detailPaket()` & `sectionCoverage()` menautkan item lewat (dokumen, paket) | Ubah `:203`, `:247` |
| `app/Services/SOP/SopProgressReadService.php` | `versiVisibleInPaket()` memeriksa keterlihatan lewat dokumen | Ubah `:971` |
| `app/Services/SOP/SopSecurePdfAccessService.php` | `canAccess()` & `lewatJalurPublik()` menelusuri paket lewat dokumen | Ubah `:37`, `:78` |
| `plans/sop/sql/26C_drop_index_versi_per_item.sql` | Buang `UX_N_SOP_Item_Versi_Urut` (ditunda dari Fase 2 & 3) | **Buat** |
| `tests/Feature/LMS/SOP/SopDokumenJoinVersiTest.php` | Simulasi berbagi untuk keempat situs | **Buat** |

---

# Task 1 — Baseline Pra-Fase-4 (WAJIB HARI INI)

Baseline Fase 3 diambil **2026-08-09**. Sidik jarinya menyerap nilai relatif-hari-ini (sisa hari, ember kadaluarsa), jadi ia **tidak akan pernah** melaporkan "Identik" hari ini. Memakainya sebagai pembanding akan menghasilkan "BERUBAH" yang seluruhnya derau — dan gerbang yang berisik adalah gerbang yang diabaikan.

- [ ] **Step 1: Pastikan tree bersih & Fase 3 masih utuh**

```bash
git status --porcelain          # kosong
php artisan sop:dokumen-verify  # exit 0
```

- [ ] **Step 2: Ambil baseline HARI INI**

```bash
php artisan sop:dokumen-baseline --simpan=plans/sop/baseline/fase4-sebelum.json
php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase4-sebelum.json
```
Expected: "Identik dengan baseline — nol regresi.", dan `tercakup == jumlah` pada `export_rinci`.

**Kalau gerbang Task 6 jatuh di hari berbeda, ulangi Step 2 pada keadaan bersih** (`git stash`) sebelum membandingkan. Jangan menafsirkan drift tanggal sebagai regresi, dan jangan menafsirkan regresi sebagai drift tanggal — pisahkan dengan tiga uji yang sudah terbukti: (a) ada baris `N_SOP*` berubah setelah stempel baseline? (b) dua pengambilan berturut identik? (c) `Carbon::setTestNow()` dibekukan ke stempel baseline mereproduksi hash lama?

- [ ] **Step 3: Commit**

```bash
git add plans/sop/baseline/fase4-sebelum.json
git commit -m "chore(sop): baseline pra-Fase-4"
```

---

# Task 2 — Monitoring Menautkan Item Lewat (Dokumen, Paket)

**Files:**
- Modify: `app/Services/SOP/SopMonitoringService.php` (`detailPaket()` :202-205, `sectionCoverage()` :246-249)
- Test: `tests/Feature/LMS/SOP/SopDokumenJoinVersiTest.php`

- [ ] **Step 1: Test yang gagal**

```php
<?php

namespace Tests\Feature;

use App\Services\SOP\SopMonitoringService;
use App\Services\SOP\Support\SopStatus;
use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 4 — join yang menyimpulkan ITEM DARI VERSI.
 *
 * Semua test di sini mensimulasikan berbagi dokumen, karena data dev
 * sepenuhnya 1 dokumen = 1 item. Tanpa simulasi, keempat situs akan "lulus"
 * tanpa pernah dieksekusi pada keadaan yang justru merusaknya.
 */
class SopDokumenJoinVersiTest extends TestCase
{
    use DatabaseTransactions;

    private function itemBerVersiDenganRollup(): ?object
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->join('N_SOP_Coverage_Rollup as r', function ($j) {
                $j->on('r.Id_SOP_Item_Versi', '=', 'v.Id_SOP_Item_Versi')
                  ->on('r.Id_SOP_Paket', '=', 'i.Id_SOP_Paket');
            })
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->first(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Paket', 'i.Id_SOP_Dokumen',
                     'i.Tipe_Item', 'i.Flag_Wajib', 'i.Flag_Highlight', 'v.Id_SOP_Item_Versi']);
    }

    /** Item kedua ber-judul KHAS di paket lain, berbagi dokumen yang sama. */
    private function itemKeduaBerbagi(object $item, int $idPaketLain, string $judul): int
    {
        return (int) DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket'   => $idPaketLain,
            'Tipe_Item'      => $item->Tipe_Item,
            'Judul_Item'     => $judul,
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Id_Item_Versi_Aktif' => $item->Id_SOP_Item_Versi,
            'Urutan'         => 9999,
            'Flag_Wajib'     => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By'     => 1,
            'Created_At'     => now(),
            'Flag_Aktif'     => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    public function test_detail_paket_menampilkan_judul_item_paket_ITU_bukan_paket_lain(): void
    {
        $item = $this->itemBerVersiDenganRollup();
        if (!$item) $this->markTestSkipped('Tak ada item ber-versi dengan baris rollup di data dev.');

        $paketLain = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)->value('Id_SOP_Paket');
        if (!$paketLain) $this->markTestSkipped('Tak ada paket lain.');

        $this->itemKeduaBerbagi($item, (int) $paketLain, 'JUDUL KHAS PAKET LAIN');

        // Baris rollup untuk paket LAIN atas versi yang sama.
        DB::table('N_SOP_Coverage_Rollup')->insert([
            'Id_SOP_Paket' => $paketLain, 'Id_SOP_Item_Versi' => $item->Id_SOP_Item_Versi,
            'Total_Target' => 1, 'Jumlah_Dibuka' => 0, 'Jumlah_Dikonfirmasi' => 0,
            'Persen_Dikonfirmasi' => 0, 'Updated_At' => now(),
        ]);

        $judul = collect(app(SopMonitoringService::class)->detailPaket((int) $paketLain)['items'] ?? [])
            ->pluck('Judul_Item')->all();

        $this->assertContains('JUDUL KHAS PAKET LAIN', $judul,
            'Monitoring paket harus menampilkan item MILIK PAKET ITU. Menautkan lewat '
            . 'v.Id_SOP_Paket_Item membuat baris rollup paket ini menampilkan judul, tipe, '
            . 'dan urutan milik item di paket lain — salah atribusi yang senyap.');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=test_detail_paket_menampilkan_judul_item_paket_ITU_bukan_paket_lain`
Expected: FAIL — judul yang muncul adalah judul item paket asal.

- [ ] **Step 3: Implementasi**

Di `detailPaket()` dan `sectionCoverage()`, ganti join item:

```php
            ->join('N_SOP_Paket_Item as i', function ($j) {
                /* Item ditautkan lewat (DOKUMEN, PAKET), bukan lewat
                   v.Id_SOP_Paket_Item. FK itu menunjuk SATU item — yang
                   kebetulan membuat versinya — sehingga begitu dokumen dibagi,
                   monitoring paket ini menampilkan judul/tipe/urutan/section
                   milik item di paket LAIN. Bukan penggandaan baris, melainkan
                   salah atribusi, dan itu jauh lebih sulit terlihat.

                   Versi tanpa dokumen (18 baris yatim di dev) mundur ke FK
                   lama — satu-satunya jalur yang tersisa untuk mereka. */
                $j->on(DB::raw('COALESCE(i.Id_SOP_Dokumen, -1)'), '=', DB::raw('COALESCE(v.Id_SOP_Dokumen, -2)'))
                  ->on('i.Id_SOP_Paket', '=', 'r.Id_SOP_Paket')
                  ->where('i.Flag_Aktif', '=', SopStatus::AKTIF);
            })
```

Jalur mundur untuk versi ber-dokumen NULL ditangani lewat `orWhere` pada `v.Id_SOP_Paket_Item`; tulis eksplisit dan sertakan test bahwa baris yatim tidak hilang dari monitoring.

- [ ] **Step 4: Test baris yatim tidak hilang**

```php
    public function test_versi_tanpa_dokumen_tetap_muncul_di_monitoring(): void
    {
        // Cari baris rollup yang versinya ber-Id_SOP_Dokumen NULL; pastikan
        // detailPaket() paket tsb tetap memuat itemnya.
    }
```

Isi test ini ditulis saat implementasi, memakai data nyata yang ditemukan — jangan mengarang fixture.

- [ ] **Step 5: Jalankan, pastikan LULUS** — `php artisan test --filter=SopDokumenJoinVersiTest`

- [ ] **Step 6: Commit**

```bash
git commit -m "fix(sop): monitoring menautkan item lewat (dokumen, paket), bukan FK versi"
```

---

# Task 3 — `versiVisibleInPaket()` Berhenti Salah Menolak

**Files:** `app/Services/SOP/SopProgressReadService.php` :960-977

- [ ] **Step 1: Test yang gagal**

```php
    public function test_versi_terlihat_di_paket_lain_yang_berbagi_dokumen(): void
    {
        // Karyawan sah di paket P2 yang berbagi dokumen dengan P1.
        // Sebelum perbaikan: DITOLAK, karena versi "milik" item di P1.
    }
```

Test ini butuh karyawan dengan penugasan aktif; pakai pola `SopProgressReadService` yang sudah dipakai `SopDokumenBaseline::ambil()` untuk menemukan pemanggil yang sah, jangan mengarang kode karyawan.

- [ ] **Step 2: Implementasi**

```php
        /* Keterlihatan diputuskan lewat DOKUMEN: apakah paket ini punya item
           aktif yang merujuk dokumen versi tersebut. Menautkan lewat
           v.Id_SOP_Paket_Item menolak karyawan yang sah di paket lain yang
           menyajikan dokumen yang sama — salah tolak, bukan salah izinkan,
           tapi sama-sama cacat otorisasi. */
        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on(DB::raw('COALESCE(i.Id_SOP_Dokumen, -1)'), '=', DB::raw('COALESCE(v.Id_SOP_Dokumen, -2)'))
                  ->orOn('i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item');
            })
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('v.Status', [SopStatus::PAKET_PUBLISHED, 'OBSOLETE'])
            ->exists();
```

**Snapshot rilis tidak disentuh.** Cabang `releasedSnapshot()` di atasnya membekukan `Id_SOP_Item_Versi` per rilis dan tetap jadi sumber kebenaran untuk paket terbit — itu yang membuat rilis lama akurat setelah dokumen direvisi.

- [ ] **Step 3: Commit**

```bash
git commit -m "fix(sop): versiVisibleInPaket memutuskan lewat dokumen, berhenti salah menolak"
```

---

# Task 4 — Akses PDF Menelusuri Paket Lewat Dokumen

**Files:** `app/Services/SOP/SopSecurePdfAccessService.php` :37, :78

Ini **perubahan semantik keamanan** dan wajib disengaja: setelah perbaikan, karyawan yang ditugaskan ke paket mana pun yang menyajikan dokumen itu boleh membuka berkasnya. Itu hasil yang benar — dan tepat itulah arti "dokumen terkendali bersama" — tapi ia harus punya test yang menyatakannya, bukan lolos sebagai efek samping.

- [ ] **Step 1: Dua test — satu izinkan, satu tolak**

```php
    public function test_karyawan_di_paket_yang_berbagi_dokumen_boleh_membuka_berkas(): void
    public function test_karyawan_tanpa_penugasan_ke_paket_mana_pun_tetap_ditolak(): void
```

Test kedua adalah pagarnya: memastikan pelebaran berhenti pada "paket yang menyajikan dokumen", bukan menjadi akses bebas.

- [ ] **Step 2: Implementasi** — ganti join item pada `canAccess()` dan `lewatJalurPublik()` dengan pola (dokumen, mundur ke FK) yang sama.

- [ ] **Step 3: Commit**

```bash
git commit -m "fix(sop): akses PDF menelusuri paket lewat dokumen (perubahan semantik disengaja)"
```

---

# Task 5 — DIBATALKAN: `UX_N_SOP_Item_Versi_Urut` TETAP DIPERTAHANKAN

**Langkah ini dibatalkan saat eksekusi, dan itu koreksi terhadap rencana ini sendiri.**

Premisnya salah. Rencana ini menyatakan kolomnya "benar-benar jadi vestigial setelah Task 2–4". Ia tidak. `v.Id_SOP_Paket_Item` masih dipakai empat service — dan **setiap perbaikan Fase 4 justru mempertahankannya sebagai jalur mundur** untuk versi ber-`Id_SOP_Dokumen` NULL. Di data dev jalur mundur itu menanggung 56% baris rollup (18 dari 32); tanpanya lebih dari separuh isi monitoring lenyap.

Tiga alasan konkret untuk mempertahankan indeksnya:

1. **Ia masih satu-satunya pagar untuk baris ber-dokumen NULL.** Index terfilter 26B sengaja tak menyentuh mereka.
2. **Ia tidak menghalangi berbagi.** Setiap baris versi tetap membawa satu `Id_SOP_Paket_Item`, dan pasangan `(item, Versi_Ke)` tetap unik meski dokumennya dibagi — diperiksa ulang terhadap keempat jalur penulis versi.
3. **Membuangnya adalah kehilangan bersih.** Nol manfaat, satu pagar hilang.

Ini ditunda tiga kali dengan alasan berbeda-beda; menuliskan pembatalannya di sini menghentikan penundaan itu jadi kebiasaan. Kalau kelak `Id_SOP_Paket_Item` benar-benar dilepas dari `N_SOP_Item_Versi` (bukan pekerjaan yang sedang direncanakan), indeksnya ikut turun bersamanya.

---

# ~~Task 5 lama — DDL 26C~~ (tidak dikerjakan)

**Files:** `plans/sop/sql/26C_drop_index_versi_per_item.sql`, `tests/Feature/LMS/SOP/SopDokumenFase2Test.php` (ubah test yang menegaskan index lama masih ada)

- [ ] **Step 1: DDL + blok rollback**

```sql
IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE object_id = OBJECT_ID('N_SOP_Item_Versi') AND name = 'UX_N_SOP_Item_Versi_Urut')
BEGIN
    DROP INDEX UX_N_SOP_Item_Versi_Urut ON N_SOP_Item_Versi;
    PRINT '26C: UX_N_SOP_Item_Versi_Urut dibuang.';
END
GO
/* ROLLBACK:
   CREATE UNIQUE INDEX UX_N_SOP_Item_Versi_Urut
       ON N_SOP_Item_Versi (Id_SOP_Paket_Item, Versi_Ke);
   Aman dipasang ulang selama 1 dokumen = 1 item. Setelah Fase 6 menyalakan
   berbagi, PERIKSA duplikat lebih dulu — pemasangan ulang bisa ditolak 2601. */
```

- [ ] **Step 2: Ubah `SopDokumenFase2Test::test_index_lama_per_item_sengaja_dipertahankan`**

Test itu sekarang **harus dibalik**, bukan dihapus: ia menjadi pernyataan bahwa index lama sudah dibuang, dengan alasan yang dicatat. Menghapus test berarti menghapus jejak keputusannya.

- [ ] **Step 3: Minta user menjalankan DDL di SSMS.** Berhenti sampai dikonfirmasi.

- [ ] **Step 4: Commit**

---

# Task 6 — Hasil Pengukuran Dedup Notifikasi

Ditunda dari Fase 3 dengan alasan "ukur dulu, baru rancang". Diukur 2026-08-10.

**Angkanya:**

```
Karyawan ditugaskan ke >1 paket : 58 dari 59
Paket terbanyak per karyawan    : 6
```

Jadi begitu berbagi menyala, satu revisi dokumen bisa menghasilkan sampai **6 notifikasi** untuk satu orang.

**Tapi itu belum tentu duplikasi.** `notifDiperbarui()` bersifat **per paket**: pesannya menyebut nama paket dan menaut ke `/sop/{paket}`. Dua paket = dua kewajiban terpisah, masing-masing dengan tenggat dan halamannya sendiri. Mengirim sekali saja akan **menyembunyikan salah satu kewajiban** — cacat yang lebih berbahaya daripada berisik.

**Keputusan: tidak ada dedup di Fase 4, dan itu bukan penundaan malas.** Hari ini tak ada satu pun dokumen yang dibagi, jadi nol notifikasi berganda benar-benar terjadi. Pertanyaannya baru hidup di **Fase 6**, saat Builder mulai berbagi dokumen — dan bentuk jawabannya (gabungkan jadi satu notifikasi multi-paket? tetap per paket tapi diberi jeda? kelompokkan per dokumen?) adalah keputusan produk, bukan teknis.

**Satu bug objektif ditemukan di jalur yang sama dan DIPERBAIKI:** pencarian `Ringkasan_Perubahan` (`SopReconciliationService:155`) juga menjoin lewat FK versi. Paket yang menyajikan dokumen bersama tak akan menemukan ringkasan apa pun, sehingga notifikasi "SOP diperbarui" terkirim **tanpa menyebutkan apa yang berubah** — persis informasi yang membuat karyawan tahu bagian mana yang harus dibaca ulang.

---

# Task 7 — Gerbang

- [ ] `php artisan sop:dokumen-verify` → exit 0
- [ ] `php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase4-sebelum.json` → "Identik" (di hari yang sama dengan Task 1)
- [ ] `php artisan test --filter=Sop` → nol gagal
- [ ] `php artisan test` → kegagalan tetap terkurung di keenam berkas pra-eksisting. **Jalankan keenamnya DUA KALI** — ada test flaky yang terbukti memberi 20 lalu 19 pada perintah identik (dicatat di ledger Fase 3).

---

---

## Ledger Eksekusi (2026-08-10)

| Task | Commit | Catatan |
|---|---|---|
| 1 — Baseline pra-Fase-4 | `de3f…` | Diambil ulang hari ini; baseline Fase 3 mustahil "Identik" lintas hari |
| 2 — Monitoring | `cdf4f566` | Merah dulu; butuh `MIN()` agar tak menggandakan `Total_Target` |
| 3 — `versiVisibleInPaket` | `4d7e15f7` | Merah dulu; pasangan test izinkan/tolak |
| 4 — Akses PDF | `6dfd210a` | Fixture harus dibangun ulang **dua kali** |
| 4b — Dua situs terlewat | `8bbe6901` | Tidak ada di daftar berkas rencana ini |
| 5 — Drop index | — | **DIBATALKAN**, premisnya salah |
| 6 — Dedup notifikasi | `4eafe7b5` | Diukur; sengaja tidak dikerjakan + 1 bug diperbaiki |

### Hasil gerbang

| Butir | Hasil |
|---|---|
| `sop:dokumen-verify` | exit 0 |
| Baseline nol regresi | "Identik" — termasuk sidik isi register kontrol dokumen, jalur yang paling banyak diubah fase ini |
| Suite SOP | 163 lulus, 2 skip, 0 gagal (dari 155) |
| Suite penuh | 19 gagal, 296 lulus (dari 288) |
| Keterkurungan 19 | Keenam berkas pra-eksisting sendirian menghasilkan 19, **dua jalan berturut konsisten** — sisa suite menyumbang nol |

### Lima situs, bukan tiga

Rencana ini menyebut tiga service. Yang benar lima. Dua ditemukan dengan menelusuri `v.Id_SOP_Paket_Item` alih-alih mengikuti daftar berkas; satu lagi muncul saat mengukur notifikasi. Yang paling berbahaya — register kontrol dokumen — **tidak ada di daftar mana pun**, dan kalau lolos, ia baru ketahuan di Fase 5 ketika halaman Master Dokumen kehilangan baris tanpa sebab yang jelas.

Pelajarannya: daftar berkas dalam rencana adalah titik awal, bukan batas. Yang menentukan cakupan adalah **pola yang dicari** (`v.Id_SOP_Paket_Item`), bukan daftar yang ditulis sebelum bekerja.

### Tiga kali test mengoreksi saya, bukan kode

1. **Test section coverage lulus sebelum perbaikan** — tidak diskriminatif pada data ini. Dipertahankan sebagai pagar, dicatat sebagai bukan-bukti.
2. **Test akses PDF pertama memakai konten PUBLIK**, tempat `lewatJalurPublik()` sengaja mengizinkan siapa pun tanpa penugasan. Baik test positif maupun negatif tak membuktikan apa pun; bahkan kode karyawan fiktif lolos. Itu desain, bukan celah.
3. **Test akses PDF kedua memilih paket terkunci presensi** — gagal karena alasan yang benar sambil menyamar sebagai cacat join yang tak pernah ada. Nyaris membuat saya "memperbaiki" hal yang tidak rusak.

Diskriminasi test akses PDF akhirnya dibuktikan dengan mengembalikan join lama sementara: merah, lalu hijau lagi setelah dipulihkan.

### Jalur mundur itu load-bearing

`v.Id_SOP_Dokumen` NULL bukan kasus pinggir: **18 dari 32 baris rollup (56%)** menunjuk versi semacam itu. Setiap join baru di fase ini mempertahankan cabang FK lama, dan satu test khusus menguncinya. Ini juga yang membatalkan Task 5.

---

## Gerbang Menuju Fase 5

- [ ] Seluruh Task 7 hijau
- [ ] Hasil pengukuran dedup notifikasi (Task 6) tercatat, beserta keputusannya
- [ ] Fase 5 adalah permukaan admin pertama: register kontrol dokumen di Monitoring **ditumbuhkan** jadi halaman Master Dokumen — bukan halaman kedua. Membuat layar dokumen kedua akan memberi admin dua angka berbeda untuk hal yang sama.
