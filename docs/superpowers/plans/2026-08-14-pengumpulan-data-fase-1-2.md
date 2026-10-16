# Pengumpulan Data Karyawan — Rencana Implementasi Fase 1 & 2

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini task demi task. Langkah memakai sintaks checkbox (`- [ ]`) untuk pelacakan.

**Goal:** Karyawan bisa membuka `/pengumpulan-data`, mengisi wizard 4 langkah, dan mengirimnya sekali — tersimpan sebagai JSON di tabel baru, terkunci setelah terkirim.

**Architecture:** Definisi field hidup di satu berkas config PHP yang menjadi sumber kebenaran untuk validasi server **dan** render UI. Status per karyawan disimpan di baris ringan terpisah dari JSON jawaban, dibaca lewat prop Inertia lazy yang di-cache. Fase ini belum memasang modal atau bar — halaman hanya bisa dicapai lewat URL langsung, sehingga aman naik ke produksi.

**Tech Stack:** Laravel 10 + Inertia + Vue 3 (Composition API, `<script setup>`), SQL Server, PHPUnit (`tests/Feature`, `tests/Unit`), Vitest + `@vue/test-utils` (berkas `*.test.js` bersebelahan dengan komponen).

**Spec:** `docs/superpowers/specs/2026-08-14-pengumpulan-data-karyawan-design.md`

## Global Constraints

- **DDL sudah dieksekusi pemilik repo.** Jangan membuat migration Laravel, jangan menjalankan DDL apa pun. Tabel yang tersedia: `N_HRIS_Pengumpulan_Data_Submission`, `N_HRIS_Pengumpulan_Data_Status`, `N_HRIS_Pengumpulan_Data_Config`, `N_HRIS_Pengumpulan_Data_Audit`. Referensi kolom: `database/pengumpulan_data/2026_08_14_pengumpulan_data_ddl.sql`.
- **Tabel lama tidak boleh disentuh.** Tidak ada `UPDATE`/`INSERT` ke `Karyawan` atau turunannya. Hanya `SELECT`.
- **Test menyentuh database dev sungguhan.** Setiap test yang menulis WAJIB memakai `use Illuminate\Foundation\Testing\DatabaseTransactions;` — pola yang sudah dipakai `tests/Feature/Kpi/KpiRealisasiGandaTest.php`. Test yang hanya membaca tidak perlu.
- **Jangan menjalankan `php artisan test` tanpa filter.** Selalu batasi ke berkas yang sedang dikerjakan (`--filter`), karena suite penuh mengunci DB dev yang sedang dipakai orang.
- **Versi skema saat ini: `1`.** Setiap submission menyimpannya di kolom `Versi_Skema`.
- **Nilai PTKP yang sah (8, persis segini):** `TK/0`, `TK/1`, `TK/2`, `TK/3`, `K/0`, `K/1`, `K/2`, `K/3`.
- **Jenjang pendidikan yang sah (9, persis segini):** `SD`, `SMP`, `SMA/SMK`, `D1`, `D2`, `D3`, `D4/S1`, `S2`, `S3`.
- **Hubungan kontak darurat yang sah (5):** `Orang Tua`, `Pasangan`, `Anak`, `Saudara Kandung`, `Lainnya`.
- **Karyawan aktif** = `Karyawan.Aktif = 'Y'` **dan** `Karyawan.Tanggal_Resign IS NULL`.
- **Berkas rute modul di-`require` dari `routes/kpi/ridhoDevEvo.php`**, bukan dari `routes/web.php`. Ikuti pola itu.
- **Render Inertia memakai helper `inertia('Path/Ke/Komponen')`**, bukan `Inertia::render`.
- **Bahasa penamaan:** kelas dan berkas baru memakai bahasa Indonesia, mengikuti modul SOP/Cuti yang sudah ada.
- **Jangan menambah `<style>` global tanpa scope.** Semua gaya komponen wajib `<style scoped>` — repo ini punya riwayat "style nyangkut" antar halaman Inertia.

---

## Peta Berkas

**Dibuat:**

| Berkas | Tanggung jawab |
|---|---|
| `config/pengumpulan_data.php` | Definisi 14 field, frekuensi modal, jatah penundaan, versi skema |
| `app/Services/PengumpulanData/SkemaPengumpulanData.php` | Membaca config → aturan validasi Laravel + struktur untuk UI |
| `app/Services/PengumpulanData/StatusKaryawanResolver.php` | Menentukan apakah seorang karyawan PHL |
| `app/Services/PengumpulanData/KonfigurasiKampanye.php` | Membaca baris `_Config` (sakelar + teks pengumuman), dengan cache |
| `app/Services/PengumpulanData/StatusPengumpulanService.php` | Baca/tulis baris status, cache, catat penundaan, simpan draf |
| `app/Services/PengumpulanData/PengirimanDataService.php` | Validasi akhir, snapshot data lama, tulis submission, kunci status |
| `app/Http/Controllers/PengumpulanData/PengumpulanDataController.php` | Halaman wizard, halaman ringkasan, endpoint draf/kirim/tunda |
| `routes/PengumpulanData/PengumpulanDataWeb.php` | Rute modul |
| `resources/js/Pages/PengumpulanData/Wizard.vue` | Halaman wizard 4 langkah + peninjauan |
| `resources/js/Pages/PengumpulanData/Ringkasan.vue` | Halaman "sudah terkirim, terkunci" |
| `resources/js/Pages/PengumpulanData/components/LangkahIndikator.vue` | Baris titik progres langkah |
| `resources/js/Pages/PengumpulanData/components/BidangIsian.vue` | Satu field generik (teks/tanggal/pilihan/cari) |
| `resources/js/Pages/PengumpulanData/components/PanelPeninjauan.vue` | Daftar jawaban sebelum kirim, bisa diklik untuk lompat |

**Diubah:**

| Berkas | Perubahan |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Menambah shared prop lazy `pengumpulanData` |
| `routes/kpi/ridhoDevEvo.php` | Menambah satu baris `require` di akhir berkas |

---

### Task 1: Definisi skema field

**Files:**
- Create: `config/pengumpulan_data.php`
- Create: `app/Services/PengumpulanData/SkemaPengumpulanData.php`
- Test: `tests/Unit/PengumpulanData/SkemaPengumpulanDataTest.php`

**Interfaces:**
- Consumes: tidak ada (task pertama)
- Produces:
  - `SkemaPengumpulanData::versi(): int`
  - `SkemaPengumpulanData::langkah(): array` — daftar berurutan, tiap elemen `['kunci' => string, 'judul' => string, 'field' => array<string>]`
  - `SkemaPengumpulanData::semuaField(): array` — peta `kunci => ['label','tipe','pilihan','aturan','khusus_phl']`
  - `SkemaPengumpulanData::fieldBerlaku(bool $isPhl): array` — sama seperti `semuaField()` tetapi sudah menyaring field khusus PHL. **Task 5 memakai kuncinya untuk membuang field yang disusupkan dari browser** — jangan hapus atau ganti nama.
  - `SkemaPengumpulanData::aturanValidasi(bool $isPhl): array` — siap dipakai `Validator::make`
  - `SkemaPengumpulanData::untukUi(bool $isPhl): array` — `['versi','langkah','field']`

Tipe field yang dikenal: `teks`, `tanggal`, `pilihan`, `cari_karyawan`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Unit\PengumpulanData;

use App\Services\PengumpulanData\SkemaPengumpulanData;
use Tests\TestCase;

class SkemaPengumpulanDataTest extends TestCase
{
    private function skema(): SkemaPengumpulanData
    {
        return app(SkemaPengumpulanData::class);
    }

    public function test_skema_punya_empat_langkah_berurutan(): void
    {
        $langkah = $this->skema()->langkah();

        $this->assertCount(4, $langkah);
        $this->assertSame(
            ['identitas', 'kontak', 'kepegawaian', 'darurat'],
            array_column($langkah, 'kunci'),
        );
    }

    public function test_field_atasan_hanya_ikut_validasi_untuk_phl(): void
    {
        $aturanPhl = $this->skema()->aturanValidasi(isPhl: true);
        $aturanBukanPhl = $this->skema()->aturanValidasi(isPhl: false);

        $this->assertArrayHasKey('atasan_spv', $aturanPhl);
        $this->assertContains('required', $aturanPhl['atasan_spv']);

        $this->assertArrayNotHasKey('atasan_spv', $aturanBukanPhl);
        $this->assertArrayNotHasKey('atasan_dept_head', $aturanBukanPhl);
    }

    public function test_nik_ktp_wajib_tepat_enam_belas_digit(): void
    {
        $aturan = $this->skema()->aturanValidasi(isPhl: false);

        $this->assertContains('digits:16', $aturan['nik_ktp']);
    }

    public function test_daftar_ptkp_persis_delapan_nilai_resmi(): void
    {
        $pilihan = $this->skema()->semuaField()['status_ptkp']['pilihan'];

        $this->assertSame(
            ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'],
            $pilihan,
        );
    }

    public function test_untuk_ui_tidak_membocorkan_field_phl_ke_non_phl(): void
    {
        $ui = $this->skema()->untukUi(isPhl: false);

        $this->assertSame(1, $ui['versi']);
        $this->assertArrayNotHasKey('atasan_spv', $ui['field']);
        $this->assertNotContains('atasan_spv', $ui['langkah'][2]['field']);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=SkemaPengumpulanDataTest`
Expected: FAIL — `Class "App\Services\PengumpulanData\SkemaPengumpulanData" does not exist`

- [ ] **Step 3: Tulis config**

```php
<?php
// config/pengumpulan_data.php

return [
    'versi_skema' => 1,

    // SESI | HARIAN | HALAMAN — dipakai fase 3.
    'frekuensi_modal' => 'SESI',
    'maks_penundaan' => 3,
    'tampilkan_bar_menetap' => true,

    // Dipakai hanya bila baris N_HRIS_Pengumpulan_Data_Config belum ada.
    'aktif_default' => false,

    'langkah' => [
        ['kunci' => 'identitas',   'judul' => 'Identitas',      'field' => ['nama_lengkap', 'nik_ktp', 'tanggal_lahir', 'jenis_kelamin']],
        ['kunci' => 'kontak',      'judul' => 'Kontak',         'field' => ['email', 'no_hp']],
        ['kunci' => 'kepegawaian', 'judul' => 'Kepegawaian',    'field' => ['departemen', 'pendidikan_terakhir', 'status_ptkp', 'atasan_spv', 'atasan_dept_head']],
        ['kunci' => 'darurat',     'judul' => 'Kontak Darurat', 'field' => ['darurat_nama', 'darurat_hubungan', 'darurat_no_hp']],
    ],

    'field' => [
        'nama_lengkap' => [
            'label' => 'Nama lengkap',
            'tipe' => 'teks',
            'aturan' => ['required', 'string', 'min:3', 'max:150'],
        ],
        'nik_ktp' => [
            'label' => 'NIK KTP',
            'tipe' => 'teks',
            'petunjuk' => '16 digit sesuai KTP, bukan nomor karyawan',
            'aturan' => ['required', 'digits:16'],
        ],
        'tanggal_lahir' => [
            'label' => 'Tanggal lahir',
            'tipe' => 'tanggal',
            'aturan' => ['required', 'date', 'before:-15 years', 'after:-70 years'],
        ],
        'jenis_kelamin' => [
            'label' => 'Jenis kelamin',
            'tipe' => 'pilihan',
            'pilihan' => ['L', 'P'],
            'label_pilihan' => ['L' => 'Laki-laki', 'P' => 'Perempuan'],
            'aturan' => ['required', 'in:L,P'],
        ],
        'email' => [
            'label' => 'Email aktif',
            'tipe' => 'teks',
            'aturan' => ['required', 'email:rfc', 'max:150'],
        ],
        'no_hp' => [
            'label' => 'Nomor HP terupdate',
            'tipe' => 'teks',
            'petunjuk' => 'Contoh: 081234567890',
            'aturan' => ['required', 'regex:/^08[0-9]{8,12}$/'],
        ],
        'departemen' => [
            'label' => 'Departemen',
            'tipe' => 'pilihan',
            'sumber_pilihan' => 'divisi',
            'aturan' => ['required', 'string', 'max:50'],
        ],
        'pendidikan_terakhir' => [
            'label' => 'Pendidikan terakhir',
            'tipe' => 'pilihan',
            'pilihan' => ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4/S1', 'S2', 'S3'],
            'aturan' => ['required', 'in:SD,SMP,SMA/SMK,D1,D2,D3,D4/S1,S2,S3'],
        ],
        'status_ptkp' => [
            'label' => 'Status pernikahan (PTKP)',
            'tipe' => 'pilihan',
            'pilihan' => ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'],
            'petunjuk' => 'Untuk pembaruan Coretax',
            'aturan' => ['required', 'in:TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3'],
        ],
        'atasan_spv' => [
            'label' => 'Atasan langsung (SPV)',
            'tipe' => 'cari_karyawan',
            'khusus_phl' => true,
            'aturan' => ['required', 'string', 'max:20'],
        ],
        'atasan_dept_head' => [
            'label' => 'Atasan langsung (Dept Head)',
            'tipe' => 'cari_karyawan',
            'khusus_phl' => true,
            'aturan' => ['required', 'string', 'max:20'],
        ],
        'darurat_nama' => [
            'label' => 'Nama kontak darurat',
            'tipe' => 'teks',
            'aturan' => ['required', 'string', 'min:3', 'max:150'],
        ],
        'darurat_hubungan' => [
            'label' => 'Hubungan',
            'tipe' => 'pilihan',
            'pilihan' => ['Orang Tua', 'Pasangan', 'Anak', 'Saudara Kandung', 'Lainnya'],
            'aturan' => ['required', 'in:Orang Tua,Pasangan,Anak,Saudara Kandung,Lainnya'],
        ],
        'darurat_no_hp' => [
            'label' => 'Nomor HP kontak darurat',
            'tipe' => 'teks',
            'aturan' => ['required', 'regex:/^08[0-9]{8,12}$/'],
        ],
    ],
];
```

- [ ] **Step 4: Tulis kelas skema**

```php
<?php

namespace App\Services\PengumpulanData;

/**
 * Sumber kebenaran tunggal untuk daftar pertanyaan.
 *
 * Dipakai dua arah: server memakai aturanValidasi() untuk memvalidasi kiriman,
 * Vue memakai untukUi() untuk merender wizard. Tidak ada daftar field yang
 * ditulis dua kali — menambah pertanyaan cukup menyunting config.
 */
class SkemaPengumpulanData
{
    public function versi(): int
    {
        return (int) config('pengumpulan_data.versi_skema', 1);
    }

    public function langkah(): array
    {
        return config('pengumpulan_data.langkah', []);
    }

    public function semuaField(): array
    {
        return config('pengumpulan_data.field', []);
    }

    /** Field yang berlaku untuk seorang karyawan, sudah menyaring field khusus PHL. */
    public function fieldBerlaku(bool $isPhl): array
    {
        return array_filter(
            $this->semuaField(),
            fn (array $def) => $isPhl || ! ($def['khusus_phl'] ?? false),
        );
    }

    public function aturanValidasi(bool $isPhl): array
    {
        $aturan = [];

        foreach ($this->fieldBerlaku($isPhl) as $kunci => $def) {
            $aturan[$kunci] = $def['aturan'];
        }

        return $aturan;
    }

    public function untukUi(bool $isPhl): array
    {
        $berlaku = $this->fieldBerlaku($isPhl);

        $langkah = array_map(function (array $l) use ($berlaku) {
            $l['field'] = array_values(array_filter(
                $l['field'],
                fn (string $kunci) => array_key_exists($kunci, $berlaku),
            ));

            return $l;
        }, $this->langkah());

        // Aturan validasi sengaja TIDAK dikirim ke UI: aturan di browser hanya
        // hiasan, yang mengikat adalah server. Mengirimnya cuma menambah berat.
        $field = array_map(
            fn (array $def) => collect($def)->except('aturan', 'khusus_phl')->all(),
            $berlaku,
        );

        return [
            'versi' => $this->versi(),
            'langkah' => array_values($langkah),
            'field' => $field,
        ];
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=SkemaPengumpulanDataTest`
Expected: PASS, 5 test.

- [ ] **Step 6: Commit**

```bash
git add config/pengumpulan_data.php app/Services/PengumpulanData/SkemaPengumpulanData.php tests/Unit/PengumpulanData/SkemaPengumpulanDataTest.php
git commit -m "feat(pengumpulan-data): definisi skema field sebagai sumber kebenaran tunggal"
```

---

### Task 2: Deteksi PHL

**Files:**
- Create: `app/Services/PengumpulanData/StatusKaryawanResolver.php`
- Test: `tests/Feature/PengumpulanData/StatusKaryawanResolverTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `StatusKaryawanResolver::isPhl(string $kodeKaryawan): bool`

**Konteks yang wajib diperiksa lebih dulu.** Repo ini punya **dua** konvensi PHL yang tidak sama dan salah satunya kemungkinan keliru:

- `app/Services/SOP/SopPenjadwalanTargetService.php:299` → `lvl.Id_Level = 21` pada view `View_Golongan_Sub_Golongan_Level_Jabatan`
- `app/Services/Cuti/Dashboard/CutiDashboardService.php:71` → `Karyawan.ID_Level_Jabatan NOT IN (21)`

Keduanya membandingkan angka 21 tapi ke **kolom yang berbeda**. Rencana ini memakai konvensi SOP/LMS (`Id_Level` pada view), karena komentar di sana menyebutnya konvensi bersama picker LMS.

- [ ] **Step 1: Verifikasi konvensi terhadap database — sebelum menulis kode**

Jalankan query ini di SQL Server dan bandingkan hasilnya:

```sql
SELECT TOP 20
    k.Kode_Karyawan,
    k.ID_Level_Jabatan          AS Kolom_Di_Karyawan,
    lvl.Id_Level                AS Kolom_Di_View,
    lvl.Nama_Jabatan
FROM Karyawan k
LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan lvl
       ON lvl.ID_Level_Jabatan = k.ID_Level_Jabatan
WHERE k.Aktif = 'Y' AND k.Tanggal_Resign IS NULL
ORDER BY lvl.Id_Level DESC;
```

Bila `Id_Level = 21` tidak berkorelasi dengan jabatan PHL, **berhenti dan laporkan** — jangan menebak. Deteksi yang salah membuat field atasan muncul untuk orang yang salah.

- [ ] **Step 2: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\PengumpulanData;

use App\Services\PengumpulanData\StatusKaryawanResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusKaryawanResolverTest extends TestCase
{
    private function resolver(): StatusKaryawanResolver
    {
        return app(StatusKaryawanResolver::class);
    }

    private function ambilKode(bool $phl): ?string
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->when($phl,
                fn ($q) => $q->where('lvl.Id_Level', 21),
                fn ($q) => $q->where(fn ($w) => $w->where('lvl.Id_Level', '<>', 21)->orWhereNull('lvl.Id_Level')),
            )
            ->value('k.Kode_Karyawan');
    }

    public function test_karyawan_level_21_dikenali_sebagai_phl(): void
    {
        $kode = $this->ambilKode(phl: true);
        $this->assertNotNull($kode, 'Butuh minimal satu karyawan PHL aktif di DB untuk uji ini.');

        $this->assertTrue($this->resolver()->isPhl($kode));
    }

    public function test_karyawan_biasa_bukan_phl(): void
    {
        $kode = $this->ambilKode(phl: false);
        $this->assertNotNull($kode, 'Butuh minimal satu karyawan non-PHL aktif di DB untuk uji ini.');

        $this->assertFalse($this->resolver()->isPhl($kode));
    }

    public function test_kode_tidak_dikenal_dianggap_bukan_phl(): void
    {
        $this->assertFalse($this->resolver()->isPhl('KODE-TIDAK-ADA-XYZ'));
    }
}
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=StatusKaryawanResolverTest`
Expected: FAIL — kelas belum ada.

- [ ] **Step 4: Tulis implementasi**

```php
<?php

namespace App\Services\PengumpulanData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusKaryawanResolver
{
    /**
     * PHL = Id_Level 21 pada View_Golongan_Sub_Golongan_Level_Jabatan.
     * Konvensi yang sama dipakai picker LMS dan target penjadwalan SOP.
     */
    private const LEVEL_PHL = 21;

    public function isPhl(string $kodeKaryawan): bool
    {
        if ($kodeKaryawan === '') {
            return false;
        }

        // Level jabatan hampir tak pernah berubah dalam hitungan jam, dan nilai
        // ini dibaca di jalur render halaman — jadi di-cache.
        return Cache::remember(
            "pengumpulan-data:is-phl:{$kodeKaryawan}",
            now()->addHours(6),
            function () use ($kodeKaryawan): bool {
                $level = DB::table('Karyawan as k')
                    ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                    ->where('k.Kode_Karyawan', $kodeKaryawan)
                    ->value('lvl.Id_Level');

                return (int) $level === self::LEVEL_PHL;
            },
        );
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=StatusKaryawanResolverTest`
Expected: PASS, 3 test.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PengumpulanData/StatusKaryawanResolver.php tests/Feature/PengumpulanData/StatusKaryawanResolverTest.php
git commit -m "feat(pengumpulan-data): deteksi PHL lewat Id_Level view jabatan"
```

---

### Task 3: Konfigurasi kampanye (sakelar + teks pengumuman)

**Files:**
- Create: `app/Services/PengumpulanData/KonfigurasiKampanye.php`
- Test: `tests/Feature/PengumpulanData/KonfigurasiKampanyeTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces:
  - `KonfigurasiKampanye::aktif(): bool`
  - `KonfigurasiKampanye::pengumuman(): array` — `['judul','teks','label_tombol_isi','label_tombol_tunda']`
  - `KonfigurasiKampanye::bersihkanCache(): void`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\PengumpulanData;

use App\Services\PengumpulanData\KonfigurasiKampanye;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KonfigurasiKampanyeTest extends TestCase
{
    use DatabaseTransactions;

    private function konfigurasi(): KonfigurasiKampanye
    {
        return app(KonfigurasiKampanye::class);
    }

    public function test_membaca_sakelar_dari_baris_config(): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'Y']);
        $this->konfigurasi()->bersihkanCache();

        $this->assertTrue($this->konfigurasi()->aktif());

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'T']);
        $this->konfigurasi()->bersihkanCache();

        $this->assertFalse($this->konfigurasi()->aktif());
    }

    public function test_pengumuman_membawa_judul_dan_teks(): void
    {
        $pengumuman = $this->konfigurasi()->pengumuman();

        $this->assertArrayHasKey('judul', $pengumuman);
        $this->assertArrayHasKey('teks', $pengumuman);
        $this->assertNotSame('', $pengumuman['judul']);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KonfigurasiKampanyeTest`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Tulis implementasi**

```php
<?php

namespace App\Services\PengumpulanData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Baris N_HRIS_Pengumpulan_Data_Config menampung hal yang harus bisa diubah
 * saat sistem berjalan TANPA deploy: sakelar kampanye dan kalimat pengumuman.
 *
 * Perilaku teknis (frekuensi modal, jatah penundaan, definisi field) TIDAK di
 * sini — itu di config/pengumpulan_data.php.
 */
class KonfigurasiKampanye
{
    private const KUNCI_CACHE = 'pengumpulan-data:config';

    public function aktif(): bool
    {
        $baris = $this->baris();

        if ($baris === null) {
            return (bool) config('pengumpulan_data.aktif_default', false);
        }

        return ($baris->Is_Aktif ?? 'T') === 'Y';
    }

    public function pengumuman(): array
    {
        $baris = $this->baris();

        return [
            'judul' => (string) ($baris->Judul_Pengumuman ?? 'Pembaruan data karyawan'),
            'teks' => (string) ($baris->Teks_Pengumuman ?? 'Mohon lengkapi data karyawanmu.'),
            'label_tombol_isi' => (string) ($baris->Label_Tombol_Isi ?? 'Isi sekarang'),
            'label_tombol_tunda' => (string) ($baris->Label_Tombol_Tunda ?? 'Nanti dulu'),
        ];
    }

    public function bersihkanCache(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private function baris(): ?object
    {
        return Cache::remember(self::KUNCI_CACHE, now()->addMinutes(10), function (): ?object {
            return DB::table('N_HRIS_Pengumpulan_Data_Config')->orderBy('Id')->first();
        });
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=KonfigurasiKampanyeTest`
Expected: PASS, 2 test.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PengumpulanData/KonfigurasiKampanye.php tests/Feature/PengumpulanData/KonfigurasiKampanyeTest.php
git commit -m "feat(pengumpulan-data): sakelar kampanye dan teks pengumuman dari DB"
```

---

### Task 4: Status per karyawan (penundaan, draf, cache)

**Files:**
- Create: `app/Services/PengumpulanData/StatusPengumpulanService.php`
- Test: `tests/Feature/PengumpulanData/StatusPengumpulanServiceTest.php`

**Interfaces:**
- Consumes: tidak ada (sengaja — layanan ini tidak boleh bergantung pada sakelar kampanye; penjagaan sakelar dilakukan di lapisan pemanggil, Task 6)
- Produces:
  - `StatusPengumpulanService::untuk(string $kodeKaryawan): array` — `['status','jumlah_tunda','sisa_tunda','terkunci','is_dikecualikan','draft_langkah']`
  - `StatusPengumpulanService::catatTunda(string $kodeKaryawan): array` — mengembalikan status terbaru
  - `StatusPengumpulanService::simpanDraft(string $kodeKaryawan, array $data, int $langkah): void`
  - `StatusPengumpulanService::ambilDraft(string $kodeKaryawan): array` — `['data' => array, 'langkah' => int]`
  - `StatusPengumpulanService::tandaiSelesai(string $kodeKaryawan): void`
  - `StatusPengumpulanService::bersihkanCache(string $kodeKaryawan): void`

Nilai `status`: `BELUM` | `TERKUNCI` | `SELESAI`. `terkunci` bernilai `true` saat `status === 'TERKUNCI'`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\PengumpulanData;

use App\Services\PengumpulanData\StatusPengumpulanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusPengumpulanServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const KODE = 'UJI-PD-001';

    private function service(): StatusPengumpulanService
    {
        return app(StatusPengumpulanService::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', self::KODE)->delete();
        $this->service()->bersihkanCache(self::KODE);
    }

    public function test_karyawan_baru_berstatus_belum_dengan_jatah_penuh(): void
    {
        $status = $this->service()->untuk(self::KODE);

        $this->assertSame('BELUM', $status['status']);
        $this->assertSame(0, $status['jumlah_tunda']);
        $this->assertSame(3, $status['sisa_tunda']);
        $this->assertFalse($status['terkunci']);
    }

    public function test_penundaan_ketiga_mengunci_dan_penundaan_berikutnya_tidak_menambah(): void
    {
        $this->service()->catatTunda(self::KODE);
        $this->service()->catatTunda(self::KODE);
        $akhir = $this->service()->catatTunda(self::KODE);

        $this->assertSame('TERKUNCI', $akhir['status']);
        $this->assertSame(0, $akhir['sisa_tunda']);

        $lagi = $this->service()->catatTunda(self::KODE);

        $this->assertSame(3, $lagi['jumlah_tunda'], 'Jatah tidak boleh melewati maksimum.');
        $this->assertSame('TERKUNCI', $lagi['status']);
    }

    public function test_draf_tersimpan_dan_terbaca_kembali(): void
    {
        $this->service()->simpanDraft(self::KODE, ['email' => 'a@b.co'], 2);

        $draf = $this->service()->ambilDraft(self::KODE);

        $this->assertSame(['email' => 'a@b.co'], $draf['data']);
        $this->assertSame(2, $draf['langkah']);
    }

    public function test_selesai_menutup_status_selamanya(): void
    {
        $this->service()->catatTunda(self::KODE);
        $this->service()->tandaiSelesai(self::KODE);

        $status = $this->service()->untuk(self::KODE);

        $this->assertSame('SELESAI', $status['status']);
        $this->assertFalse($status['terkunci']);
    }

    public function test_karyawan_dikecualikan_ditandai(): void
    {
        $this->service()->untuk(self::KODE); // pastikan baris ada
        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', self::KODE)
            ->update(['Is_Dikecualikan' => 'Y']);
        $this->service()->bersihkanCache(self::KODE);

        $this->assertTrue($this->service()->untuk(self::KODE)['is_dikecualikan']);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=StatusPengumpulanServiceTest`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Tulis implementasi**

```php
<?php

namespace App\Services\PengumpulanData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Baris ringan yang dibaca di SETIAP kunjungan halaman.
 *
 * Sengaja tidak menyentuh tabel submission: yang dibaca berulang harus baris
 * kecil, bukan JSON jawaban. Repo ini punya riwayat "Login timeout expired"
 * akibat koneksi jenuh, jadi jalur ini di-cache dan dibuang saat ada tulisan.
 */
class StatusPengumpulanService
{
    public function untuk(string $kodeKaryawan): array
    {
        return Cache::remember(
            $this->kunciCache($kodeKaryawan),
            now()->addMinutes(5),
            fn () => $this->bacaLangsung($kodeKaryawan),
        );
    }

    public function catatTunda(string $kodeKaryawan): array
    {
        $this->pastikanBarisAda($kodeKaryawan);

        $maks = $this->maksPenundaan();

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Status', 'BELUM')
            ->where('Jumlah_Tunda', '<', $maks)
            ->update([
                'Jumlah_Tunda' => DB::raw('Jumlah_Tunda + 1'),
                'Terakhir_Tunda_At' => now(),
                'Updated_At' => now(),
            ]);

        // Kunci dipasang sebagai langkah terpisah supaya aturannya satu tempat:
        // status jadi TERKUNCI begitu pencacah menyentuh maksimum.
        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Status', 'BELUM')
            ->where('Jumlah_Tunda', '>=', $maks)
            ->update(['Status' => 'TERKUNCI', 'Updated_At' => now()]);

        $this->bersihkanCache($kodeKaryawan);

        return $this->untuk($kodeKaryawan);
    }

    public function simpanDraft(string $kodeKaryawan, array $data, int $langkah): void
    {
        $this->pastikanBarisAda($kodeKaryawan);

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereIn('Status', ['BELUM', 'TERKUNCI'])
            ->update([
                'Draft_Json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'Draft_Langkah' => $langkah,
                'Draft_Updated_At' => now(),
                'Updated_At' => now(),
            ]);

        $this->bersihkanCache($kodeKaryawan);
    }

    public function ambilDraft(string $kodeKaryawan): array
    {
        $baris = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first(['Draft_Json', 'Draft_Langkah']);

        return [
            'data' => json_decode($baris->Draft_Json ?? '{}', true) ?: [],
            'langkah' => (int) ($baris->Draft_Langkah ?? 0),
        ];
    }

    public function tandaiSelesai(string $kodeKaryawan): void
    {
        $this->pastikanBarisAda($kodeKaryawan);

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->update([
                'Status' => 'SELESAI',
                'Selesai_At' => now(),
                'Draft_Json' => null,
                'Draft_Langkah' => null,
                'Updated_At' => now(),
            ]);

        $this->bersihkanCache($kodeKaryawan);
    }

    public function bersihkanCache(string $kodeKaryawan): void
    {
        Cache::forget($this->kunciCache($kodeKaryawan));
    }

    private function bacaLangsung(string $kodeKaryawan): array
    {
        $baris = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first(['Status', 'Jumlah_Tunda', 'Is_Dikecualikan', 'Draft_Langkah']);

        $status = (string) ($baris->Status ?? 'BELUM');
        $tunda = (int) ($baris->Jumlah_Tunda ?? 0);
        $maks = $this->maksPenundaan();

        return [
            'status' => $status,
            'jumlah_tunda' => $tunda,
            'sisa_tunda' => max(0, $maks - $tunda),
            'terkunci' => $status === 'TERKUNCI',
            'is_dikecualikan' => ($baris->Is_Dikecualikan ?? 'T') === 'Y',
            'draft_langkah' => (int) ($baris->Draft_Langkah ?? 0),
        ];
    }

    private function pastikanBarisAda(string $kodeKaryawan): void
    {
        $ada = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->exists();

        if (! $ada) {
            DB::table('N_HRIS_Pengumpulan_Data_Status')->insert([
                'Kode_Karyawan' => $kodeKaryawan,
                'Status' => 'BELUM',
                'Jumlah_Tunda' => 0,
                'Is_Dikecualikan' => 'T',
                'Created_At' => now(),
            ]);
        }
    }

    private function maksPenundaan(): int
    {
        return (int) config('pengumpulan_data.maks_penundaan', 3);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=StatusPengumpulanServiceTest`
Expected: PASS, 5 test.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PengumpulanData/StatusPengumpulanService.php tests/Feature/PengumpulanData/StatusPengumpulanServiceTest.php
git commit -m "feat(pengumpulan-data): status per karyawan dengan jatah penundaan dan draf"
```

---

### Task 5: Layanan pengiriman

**Files:**
- Create: `app/Services/PengumpulanData/PengirimanDataService.php`
- Test: `tests/Feature/PengumpulanData/PengirimanDataServiceTest.php`

**Interfaces:**
- Consumes: `SkemaPengumpulanData` (Task 1), `StatusKaryawanResolver` (Task 2), `StatusPengumpulanService` (Task 4)
- Produces:
  - `PengirimanDataService::kirim(string $kodeKaryawan, array $data, array $meta = []): int` — mengembalikan `Id` submission. `$meta` menerima `['ip' => string, 'user_agent' => string]`.
  - Melempar `Illuminate\Validation\ValidationException` bila data tidak sah.
  - Melempar `App\Services\PengumpulanData\SudahTerkirimException` bila karyawan sudah `SELESAI`.
- Create juga: `app/Services/PengumpulanData/SudahTerkirimException.php` (extends `\RuntimeException`).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\PengumpulanData;

use App\Services\PengumpulanData\PengirimanDataService;
use App\Services\PengumpulanData\StatusPengumpulanService;
use App\Services\PengumpulanData\SudahTerkirimException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PengirimanDataServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): PengirimanDataService
    {
        return app(PengirimanDataService::class);
    }

    private function kodeNonPhl(): string
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(fn ($w) => $w->where('lvl.Id_Level', '<>', 21)->orWhereNull('lvl.Id_Level'))
            ->value('k.Kode_Karyawan');
    }

    private function dataLengkap(): array
    {
        return [
            'nama_lengkap' => 'Budi Santoso',
            'nik_ktp' => '3201234567890123',
            'tanggal_lahir' => '1995-04-17',
            'jenis_kelamin' => 'L',
            'email' => 'budi@evonusabersaudara.co.id',
            'no_hp' => '081234567890',
            'departemen' => 'IT',
            'pendidikan_terakhir' => 'D4/S1',
            'status_ptkp' => 'K/1',
            'darurat_nama' => 'Siti Aminah',
            'darurat_hubungan' => 'Pasangan',
            'darurat_no_hp' => '081298765432',
        ];
    }

    public function test_pengiriman_sah_menyimpan_json_dan_mengunci_status(): void
    {
        $kode = $this->kodeNonPhl();
        $this->assertNotNull($kode);

        $id = $this->service()->kirim($kode, $this->dataLengkap());

        $baris = DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->first();

        $this->assertSame('Y', $baris->Flag_Aktif);
        $this->assertSame(1, (int) $baris->Versi_Skema);
        $this->assertSame('3201234567890123', json_decode($baris->Data_Json, true)['nik_ktp']);
        $this->assertNotNull($baris->Snapshot_Lama_Json);

        $this->assertSame('SELESAI', app(StatusPengumpulanService::class)->untuk($kode)['status']);
    }

    public function test_nik_kurang_dari_enam_belas_digit_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $data = $this->dataLengkap();
        $data['nik_ktp'] = '32012345';

        $this->expectException(ValidationException::class);
        $this->service()->kirim($kode, $data);
    }

    public function test_field_atasan_dari_non_phl_tidak_ikut_tersimpan(): void
    {
        $kode = $this->kodeNonPhl();
        $data = $this->dataLengkap();
        $data['atasan_spv'] = 'DISUSUPKAN';

        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertArrayNotHasKey('atasan_spv', $tersimpan);
    }

    public function test_pengiriman_kedua_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $this->service()->kirim($kode, $this->dataLengkap());

        $this->expectException(SudahTerkirimException::class);
        $this->service()->kirim($kode, $this->dataLengkap());
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=PengirimanDataServiceTest`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Tulis kelas exception**

```php
<?php

namespace App\Services\PengumpulanData;

use RuntimeException;

class SudahTerkirimException extends RuntimeException
{
    public function __construct(string $message = 'Data sudah pernah dikirim dan terkunci.')
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 4: Tulis layanan pengiriman**

```php
<?php

namespace App\Services\PengumpulanData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PengirimanDataService
{
    public function __construct(
        private SkemaPengumpulanData $skema,
        private StatusKaryawanResolver $resolver,
        private StatusPengumpulanService $status,
    ) {
    }

    public function kirim(string $kodeKaryawan, array $data, array $meta = []): int
    {
        if ($this->status->untuk($kodeKaryawan)['status'] === 'SELESAI') {
            throw new SudahTerkirimException;
        }

        $isPhl = $this->resolver->isPhl($kodeKaryawan);

        // Hanya field yang BERLAKU untuk orang ini yang divalidasi dan disimpan.
        // Field khusus PHL yang dikirim oleh non-PHL dibuang di sini, bukan
        // dipercaya dari browser.
        $berlaku = array_keys($this->skema->fieldBerlaku($isPhl));
        $bersih = array_intersect_key($data, array_flip($berlaku));

        $tervalidasi = Validator::make($bersih, $this->skema->aturanValidasi($isPhl))->validate();

        $snapshot = $this->snapshotDataLama($kodeKaryawan);

        return DB::transaction(function () use ($kodeKaryawan, $tervalidasi, $snapshot, $meta): int {
            // Indeks unik tersaring memaksa hanya satu baris Flag_Aktif='Y'.
            DB::table('N_HRIS_Pengumpulan_Data_Submission')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

            $id = DB::table('N_HRIS_Pengumpulan_Data_Submission')->insertGetId([
                'Kode_Karyawan' => $kodeKaryawan,
                'Versi_Skema' => $this->skema->versi(),
                'Data_Json' => json_encode($tervalidasi, JSON_UNESCAPED_UNICODE),
                'Snapshot_Lama_Json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'Flag_Aktif' => 'Y',
                'Submitted_At' => now(),
                'Submitted_Ip' => $meta['ip'] ?? null,
                'Submitted_User_Agent' => substr((string) ($meta['user_agent'] ?? ''), 0, 500) ?: null,
                'Created_At' => now(),
            ]);

            $this->status->tandaiSelesai($kodeKaryawan);

            return (int) $id;
        });
    }

    /**
     * Salinan nilai dari sistem induk pada detik pengiriman. Inilah sumber
     * kolom "berbeda dari data lama" di sisi admin (fase 4). Murni SELECT —
     * tabel induk tidak pernah ditulis.
     */
    private function snapshotDataLama(string $kodeKaryawan): array
    {
        $baris = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Kode_Karyawan', $kodeKaryawan)
            ->first(['k.Nama', 'k.No_Induk_Karyawan', 'k.Kode_Karyawan', 'vds.*']);

        return $baris ? (array) $baris : [];
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=PengirimanDataServiceTest`
Expected: PASS, 4 test.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PengumpulanData/PengirimanDataService.php app/Services/PengumpulanData/SudahTerkirimException.php tests/Feature/PengumpulanData/PengirimanDataServiceTest.php
git commit -m "feat(pengumpulan-data): layanan pengiriman dengan snapshot data lama"
```

---

### Task 6: Rute, controller, dan shared prop

**Files:**
- Create: `app/Http/Controllers/PengumpulanData/PengumpulanDataController.php`
- Create: `routes/PengumpulanData/PengumpulanDataWeb.php`
- Modify: `routes/kpi/ridhoDevEvo.php` (tambah satu `require` di akhir berkas)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:98` (tambah shared prop setelah `'layout'`)
- Test: `tests/Feature/PengumpulanData/PengumpulanDataHalamanTest.php`

**Interfaces:**
- Consumes: seluruh service Task 1–5
- Produces: nama rute `pengumpulanData.index`, `pengumpulanData.ringkasan`, `pengumpulanData.draft`, `pengumpulanData.kirim`, `pengumpulanData.tunda`; shared prop Inertia `pengumpulanData`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\PengumpulanData;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PengumpulanDataHalamanTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Baris config tersisip dalam keadaan MATI (Is_Aktif='T') supaya fase 1-2
     * aman di produksi. Test halaman karena itu harus menyalakannya lebih dulu,
     * kalau tidak semua permintaan tertolak 403 sebelum sempat diuji.
     * DatabaseTransactions mengembalikannya setelah test selesai.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'Y']);
        app(\App\Services\PengumpulanData\KonfigurasiKampanye::class)->bersihkanCache();
    }

    private function userAktif(): User
    {
        $kode = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->value('Kode_Karyawan');

        $idUsers = DB::table('Karyawan')->where('Kode_Karyawan', $kode)->value('Id_Users');

        return User::where('UserID_Web', $idUsers)->firstOrFail();
    }

    public function test_halaman_wizard_dapat_dibuka_dan_membawa_skema(): void
    {
        $this->actingAs($this->userAktif())
            ->get('/pengumpulan-data')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PengumpulanData/Wizard')
                ->has('skema.langkah', 4)
                ->has('status'));
    }

    public function test_tunda_menaikkan_pencacah(): void
    {
        $user = $this->userAktif();

        $this->actingAs($user)->post('/pengumpulan-data/tunda')->assertOk();

        $kode = $user->karyawan->Kode_Karyawan;
        $this->assertSame(
            1,
            (int) DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $kode)->value('Jumlah_Tunda'),
        );
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/pengumpulan-data')->assertRedirect();
    }

    public function test_kampanye_mati_menutup_halaman_dan_endpoint_kirim(): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'T']);
        app(\App\Services\PengumpulanData\KonfigurasiKampanye::class)->bersihkanCache();

        $user = $this->userAktif();

        $this->actingAs($user)->get('/pengumpulan-data')->assertForbidden();
        $this->actingAs($user)->postJson('/pengumpulan-data/kirim', ['data' => []])->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=PengumpulanDataHalamanTest`
Expected: FAIL — rute `/pengumpulan-data` mengembalikan 404.

- [ ] **Step 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers\PengumpulanData;

use App\Http\Controllers\Controller;
use App\Services\PengumpulanData\KonfigurasiKampanye;
use App\Services\PengumpulanData\PengirimanDataService;
use App\Services\PengumpulanData\SkemaPengumpulanData;
use App\Services\PengumpulanData\StatusKaryawanResolver;
use App\Services\PengumpulanData\StatusPengumpulanService;
use App\Services\PengumpulanData\SudahTerkirimException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PengumpulanDataController extends Controller
{
    public function __construct(
        private SkemaPengumpulanData $skema,
        private StatusKaryawanResolver $resolver,
        private StatusPengumpulanService $status,
        private PengirimanDataService $pengiriman,
        private KonfigurasiKampanye $konfigurasi,
    ) {
    }

    public function index(Request $request)
    {
        if ($tolak = $this->tolakBilaKampanyeMati($request)) {
            return $tolak;
        }

        $kode = $this->kodeKaryawan();
        $status = $this->status->untuk($kode);

        if ($status['status'] === 'SELESAI') {
            return redirect()->route('pengumpulanData.ringkasan');
        }

        return inertia('PengumpulanData/Wizard', [
            'skema' => $this->skema->untukUi($this->resolver->isPhl($kode)),
            'status' => $status,
            'draft' => $this->status->ambilDraft($kode),
            'pilihanDivisi' => $this->pilihanDivisi(),
        ]);
    }

    public function ringkasan()
    {
        $kode = $this->kodeKaryawan();

        $baris = DB::table('N_HRIS_Pengumpulan_Data_Submission')
            ->where('Kode_Karyawan', $kode)
            ->where('Flag_Aktif', 'Y')
            ->first(['Data_Json', 'Submitted_At']);

        if (! $baris) {
            return redirect()->route('pengumpulanData.index');
        }

        return inertia('PengumpulanData/Ringkasan', [
            'skema' => $this->skema->untukUi($this->resolver->isPhl($kode)),
            'jawaban' => json_decode($baris->Data_Json, true),
            'dikirimPada' => $baris->Submitted_At,
        ]);
    }

    public function simpanDraft(Request $request): JsonResponse
    {
        if ($tolak = $this->tolakBilaKampanyeMati($request)) {
            return $tolak;
        }

        $validated = $request->validate([
            'data' => ['required', 'array'],
            'langkah' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        $this->status->simpanDraft($this->kodeKaryawan(), $validated['data'], $validated['langkah']);

        return response()->json(['ok' => true]);
    }

    public function tunda(Request $request): JsonResponse
    {
        if ($tolak = $this->tolakBilaKampanyeMati($request)) {
            return $tolak;
        }

        return response()->json($this->status->catatTunda($this->kodeKaryawan()));
    }

    public function kirim(Request $request)
    {
        if ($tolak = $this->tolakBilaKampanyeMati($request)) {
            return $tolak;
        }

        try {
            $this->pengiriman->kirim(
                $this->kodeKaryawan(),
                (array) $request->input('data', []),
                ['ip' => $request->ip(), 'user_agent' => (string) $request->userAgent()],
            );
        } catch (SudahTerkirimException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return redirect()->route('pengumpulanData.ringkasan');
    }

    private function kodeKaryawan(): string
    {
        return (string) (Auth::user()?->karyawan?->Kode_Karyawan ?? '');
    }

    /**
     * Sakelar mati harus menutup PINTU, bukan sekadar menyembunyikan modal.
     * Tanpa penjagaan ini, siapa pun masih bisa mengetik URL /pengumpulan-data
     * dan mengirim data saat kampanye sengaja dimatikan.
     */
    private function tolakBilaKampanyeMati(Request $request)
    {
        if ($this->konfigurasi->aktif()) {
            return null;
        }

        $pesan = 'Pengumpulan data sedang tidak dibuka.';

        return $request->expectsJson()
            ? response()->json(['message' => $pesan], 403)
            : abort(403, $pesan);
    }

    private function pilihanDivisi(): array
    {
        return DB::table('View_Divisi_Sub_Divisi')
            ->orderBy('NAMA_DIVISI_SUB_DIVISI')
            ->pluck('NAMA_DIVISI_SUB_DIVISI')
            ->unique()
            ->values()
            ->all();
    }
}
```

> **Catatan untuk pelaksana:** nama kolom pada `View_Divisi_Sub_Divisi` di
> `pilihanDivisi()` **wajib diverifikasi** dulu dengan
> `SELECT TOP 1 * FROM View_Divisi_Sub_Divisi;`. `HandleInertiaRequests.php:64-68`
> menunjukkan view ini punya beberapa varian penamaan kolom
> (`nama_divisi` / `Nama_Divisi` / `nama_sub_divisi`). Sesuaikan, jangan
> mengasumsikan.

- [ ] **Step 4: Tulis berkas rute**

```php
<?php
// routes/PengumpulanData/PengumpulanDataWeb.php

use App\Http\Controllers\PengumpulanData\PengumpulanDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pengumpulan Data Karyawan — sisi karyawan
|--------------------------------------------------------------------------
| Fase 1-2: halaman hanya dicapai lewat URL langsung. Modal dan bar dipasang
| di fase 3, jadi berkas ini aman naik ke produksi lebih dulu.
*/

Route::prefix('pengumpulan-data')->middleware(['auth'])->name('pengumpulanData.')->group(function () {
    Route::get('/', [PengumpulanDataController::class, 'index'])->name('index');
    Route::get('/ringkasan', [PengumpulanDataController::class, 'ringkasan'])->name('ringkasan');
    Route::post('/draft', [PengumpulanDataController::class, 'simpanDraft'])->name('draft');
    Route::post('/tunda', [PengumpulanDataController::class, 'tunda'])->name('tunda');
    Route::post('/kirim', [PengumpulanDataController::class, 'kirim'])->name('kirim');
});
```

- [ ] **Step 5: Daftarkan berkas rute**

Tambahkan di **akhir** `routes/kpi/ridhoDevEvo.php`, mengikuti baris `require` yang sudah ada di sana (lihat baris 1134–1135):

```php
require base_path('routes/PengumpulanData/PengumpulanDataWeb.php');
```

- [ ] **Step 6: Tambah shared prop**

Di `app/Http/Middleware/HandleInertiaRequests.php`, sisipkan setelah baris `'layout' => ...` (baris 98):

```php
            // PERF: lazy, sama alasannya dengan 'auth' dan 'layout'. Closure hanya
            // dievaluasi saat response Inertia dirender, jadi polling notifikasi
            // dan XHR JSON tidak ikut menyeret pembacaan status ini.
            'pengumpulanData' => function () use ($user) {
                if (! $user) {
                    return null;
                }

                $konfigurasi = app(\App\Services\PengumpulanData\KonfigurasiKampanye::class);

                if (! $konfigurasi->aktif()) {
                    return null;
                }

                $kode = (string) ($user->karyawan?->Kode_Karyawan ?? '');

                if ($kode === '') {
                    return null;
                }

                $status = app(\App\Services\PengumpulanData\StatusPengumpulanService::class)->untuk($kode);

                if ($status['is_dikecualikan'] || $status['status'] === 'SELESAI') {
                    return null;
                }

                return [
                    'status' => $status,
                    'pengumuman' => $konfigurasi->pengumuman(),
                    'frekuensi' => config('pengumpulan_data.frekuensi_modal', 'SESI'),
                    'tampilkanBar' => (bool) config('pengumpulan_data.tampilkan_bar_menetap', true),
                ];
            },
```

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=PengumpulanDataHalamanTest`
Expected: PASS, 4 test.

- [ ] **Step 8: Verifikasi prop tidak menambah query saat kampanye mati**

Pastikan baris `N_HRIS_Pengumpulan_Data_Config` masih `Is_Aktif='T'`, lalu buka `/homepage` di browser dan periksa Laravel Debugbar/log query: pembacaan `N_HRIS_Pengumpulan_Data_Status` **tidak boleh** muncul. Sakelar mati harus memotong jalur sebelum menyentuh tabel status.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/PengumpulanData routes/PengumpulanData routes/kpi/ridhoDevEvo.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/PengumpulanData/PengumpulanDataHalamanTest.php
git commit -m "feat(pengumpulan-data): rute, controller, dan shared prop lazy"
```

---

### Task 7: Komponen bidang isian

**Files:**
- Create: `resources/js/Pages/PengumpulanData/components/BidangIsian.vue`
- Test: `resources/js/Pages/PengumpulanData/components/BidangIsian.test.js`

**Interfaces:**
- Consumes: struktur `field` dari `skema.field` (Task 1)
- Produces: komponen dengan props `{ kunci: String, definisi: Object, modelValue: [String, null], galat: [String, null], pilihanDinamis: Array }` dan emit `update:modelValue`

- [ ] **Step 1: Tulis test yang gagal**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import BidangIsian from './BidangIsian.vue';

const buat = (definisi, props = {}) =>
    mount(BidangIsian, {
        props: { kunci: 'uji', definisi, modelValue: '', ...props },
    });

describe('BidangIsian', () => {
    it('merender input teks beserta labelnya', () => {
        const w = buat({ label: 'Nama lengkap', tipe: 'teks' });

        expect(w.find('label').text()).toContain('Nama lengkap');
        expect(w.find('input[type="text"]').exists()).toBe(true);
    });

    it('merender semua pilihan untuk tipe pilihan', () => {
        const w = buat({ label: 'Status', tipe: 'pilihan', pilihan: ['TK/0', 'K/1'] });

        const opsi = w.findAll('option').map((o) => o.text());
        expect(opsi).toContain('TK/0');
        expect(opsi).toContain('K/1');
    });

    it('memakai label_pilihan bila tersedia', () => {
        const w = buat({
            label: 'Jenis kelamin',
            tipe: 'pilihan',
            pilihan: ['L', 'P'],
            label_pilihan: { L: 'Laki-laki', P: 'Perempuan' },
        });

        expect(w.findAll('option').map((o) => o.text())).toContain('Laki-laki');
    });

    it('menampilkan galat dan menandai input sebagai tidak sah', () => {
        const w = buat({ label: 'NIK KTP', tipe: 'teks' }, { galat: 'Wajib 16 digit' });

        expect(w.text()).toContain('Wajib 16 digit');
        expect(w.find('input').attributes('aria-invalid')).toBe('true');
    });

    it('meneruskan perubahan lewat update:modelValue', async () => {
        const w = buat({ label: 'Email', tipe: 'teks' });

        await w.find('input').setValue('a@b.co');

        expect(w.emitted('update:modelValue')[0]).toEqual(['a@b.co']);
    });
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `npx vitest run resources/js/Pages/PengumpulanData/components/BidangIsian.test.js`
Expected: FAIL — berkas komponen belum ada.

- [ ] **Step 3: Tulis komponen**

```vue
<template>
    <div class="bidang">
        <label :for="idInput" class="bidang-label">
            {{ definisi.label }}
        </label>

        <p v-if="definisi.petunjuk" class="bidang-petunjuk">{{ definisi.petunjuk }}</p>

        <select
            v-if="definisi.tipe === 'pilihan'"
            :id="idInput"
            class="bidang-kontrol"
            :value="modelValue"
            :aria-invalid="galat ? 'true' : 'false'"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option value="">Pilih…</option>
            <option v-for="opsi in daftarPilihan" :key="opsi" :value="opsi">
                {{ definisi.label_pilihan?.[opsi] ?? opsi }}
            </option>
        </select>

        <input
            v-else
            :id="idInput"
            class="bidang-kontrol"
            :type="definisi.tipe === 'tanggal' ? 'date' : 'text'"
            :value="modelValue"
            :aria-invalid="galat ? 'true' : 'false'"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <p v-if="galat" class="bidang-galat" role="alert">{{ galat }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    kunci: { type: String, required: true },
    definisi: { type: Object, required: true },
    modelValue: { type: [String, null], default: '' },
    galat: { type: [String, null], default: null },
    // Untuk field yang pilihannya datang dari server (mis. daftar divisi).
    pilihanDinamis: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);

const idInput = computed(() => `pd-${props.kunci}`);

const daftarPilihan = computed(() =>
    props.definisi.sumber_pilihan ? props.pilihanDinamis : (props.definisi.pilihan ?? []),
);
</script>

<style scoped>
.bidang {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 1rem;
}

.bidang-label {
    font-size: 0.8125rem;
    font-weight: 600;
}

.bidang-petunjuk {
    font-size: 0.75rem;
    opacity: 0.65;
    margin: 0;
}

.bidang-kontrol {
    width: 100%;
    padding: 0.65rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    font-size: 0.9375rem;
}

.bidang-kontrol[aria-invalid='true'] {
    border-color: #e11d48;
}

.bidang-galat {
    font-size: 0.75rem;
    color: #e11d48;
    margin: 0;
}
</style>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `npx vitest run resources/js/Pages/PengumpulanData/components/BidangIsian.test.js`
Expected: PASS, 5 test.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/PengumpulanData/components/BidangIsian.vue resources/js/Pages/PengumpulanData/components/BidangIsian.test.js
git commit -m "feat(pengumpulan-data): komponen bidang isian generik"
```

---

### Task 8: Halaman wizard dan ringkasan

**Files:**
- Create: `resources/js/Pages/PengumpulanData/components/LangkahIndikator.vue`
- Create: `resources/js/Pages/PengumpulanData/components/PanelPeninjauan.vue`
- Create: `resources/js/Pages/PengumpulanData/Wizard.vue`
- Create: `resources/js/Pages/PengumpulanData/Ringkasan.vue`
- Test: `resources/js/Pages/PengumpulanData/Wizard.test.js`

**Interfaces:**
- Consumes: `BidangIsian.vue` (Task 7); props halaman dari controller (Task 6): `skema`, `status`, `draft`, `pilihanDivisi`
- Produces: halaman final fase 2

`LangkahIndikator.vue` — props `{ langkah: Array, aktif: Number, selesai: Array }`, menampilkan titik dan garis penghubung.
`PanelPeninjauan.vue` — props `{ skema: Object, nilai: Object }`, emit `lompat` dengan indeks langkah.

- [ ] **Step 1: Tulis test yang gagal**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Wizard from './Wizard.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: {} }),
}));

// Draf disimpan lewat axios; di test cukup dijadikan janji yang selalu sukses.
vi.mock('axios', () => ({
    default: { post: vi.fn(() => Promise.resolve({ data: { ok: true } })) },
}));

const skema = {
    versi: 1,
    langkah: [
        { kunci: 'identitas', judul: 'Identitas', field: ['nama_lengkap'] },
        { kunci: 'kontak', judul: 'Kontak', field: ['email'] },
    ],
    field: {
        nama_lengkap: { label: 'Nama lengkap', tipe: 'teks' },
        email: { label: 'Email aktif', tipe: 'teks' },
    },
};

const buat = (props = {}) =>
    mount(Wizard, {
        props: {
            skema,
            status: { status: 'BELUM', sisa_tunda: 3, terkunci: false, draft_langkah: 0 },
            draft: { data: {}, langkah: 0 },
            pilihanDivisi: [],
            ...props,
        },
        global: { stubs: { Head: true } },
    });

describe('Wizard pengumpulan data', () => {
    it('memulai di langkah pertama dan hanya menampilkan fieldnya', () => {
        const w = buat();

        expect(w.text()).toContain('Identitas');
        expect(w.find('#pd-nama_lengkap').exists()).toBe(true);
        expect(w.find('#pd-email').exists()).toBe(false);
    });

    it('menolak maju bila field wajib pada langkah ini masih kosong', async () => {
        const w = buat();

        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('#pd-nama_lengkap').exists()).toBe(true);
        expect(w.text()).toContain('Wajib diisi');
    });

    it('maju ke langkah berikutnya setelah field terisi', async () => {
        const w = buat();

        await w.find('#pd-nama_lengkap').setValue('Budi');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('#pd-email').exists()).toBe(true);
    });

    it('memulihkan draf ke langkah yang tersimpan', () => {
        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        expect(w.find('#pd-email').exists()).toBe(true);
    });

    it('menampilkan panel peninjauan setelah langkah terakhir', async () => {
        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        await w.find('#pd-email').setValue('a@b.co');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).toContain('Periksa sekali lagi');
        expect(w.find('[data-uji="kirim"]').exists()).toBe(true);
    });
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `npx vitest run resources/js/Pages/PengumpulanData/Wizard.test.js`
Expected: FAIL — berkas komponen belum ada.

- [ ] **Step 3: Tulis `LangkahIndikator.vue`**

```vue
<template>
    <ol class="indikator" aria-label="Langkah pengisian">
        <li
            v-for="(l, i) in langkah"
            :key="l.kunci"
            class="indikator-item"
            :class="{ 'is-aktif': i === aktif, 'is-selesai': selesai.includes(i) }"
            :aria-current="i === aktif ? 'step' : undefined"
        >
            <span class="indikator-titik" />
            <span class="indikator-judul">{{ l.judul }}</span>
        </li>
    </ol>
</template>

<script setup>
defineProps({
    langkah: { type: Array, required: true },
    aktif: { type: Number, required: true },
    selesai: { type: Array, default: () => [] },
});
</script>

<style scoped>
.indikator {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem;
}

.indikator-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}

.indikator-titik {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 50%;
    background: #cbd5e1;
}

.is-aktif .indikator-titik,
.is-selesai .indikator-titik {
    background: #0ea5e9;
}

.indikator-judul {
    font-size: 0.6875rem;
    opacity: 0.7;
    text-align: center;
}

.is-aktif .indikator-judul {
    opacity: 1;
    font-weight: 600;
}

@media (max-width: 480px) {
    .indikator-judul {
        display: none;
    }

    .is-aktif .indikator-judul {
        display: block;
    }
}
</style>
```

- [ ] **Step 4: Tulis `PanelPeninjauan.vue`**

```vue
<template>
    <div class="peninjauan">
        <h2 class="peninjauan-judul">Periksa sekali lagi</h2>
        <p class="peninjauan-catatan">
            Setelah dikirim, jawaban ini terkunci dan hanya bisa dibuka oleh admin HR.
        </p>

        <div v-for="(l, i) in skema.langkah" :key="l.kunci" class="peninjauan-grup">
            <div class="peninjauan-kepala">
                <h3>{{ l.judul }}</h3>
                <button type="button" class="peninjauan-ubah" @click="$emit('lompat', i)">Ubah</button>
            </div>
            <dl>
                <div v-for="kunci in l.field" :key="kunci" class="peninjauan-baris">
                    <dt>{{ skema.field[kunci].label }}</dt>
                    <dd>{{ tampilkan(kunci) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    skema: { type: Object, required: true },
    nilai: { type: Object, required: true },
});

defineEmits(['lompat']);

const tampilkan = (kunci) => {
    const nilai = props.nilai[kunci];
    if (nilai === undefined || nilai === '') return '—';

    return props.skema.field[kunci].label_pilihan?.[nilai] ?? nilai;
};
</script>

<style scoped>
.peninjauan-judul {
    font-size: 1.125rem;
    margin: 0 0 0.35rem;
}

.peninjauan-catatan {
    font-size: 0.8125rem;
    opacity: 0.7;
    margin: 0 0 1.25rem;
}

.peninjauan-grup {
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    padding: 0.85rem 1rem;
    margin-bottom: 0.75rem;
}

.peninjauan-kepala {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
}

.peninjauan-kepala h3 {
    font-size: 0.875rem;
    margin: 0;
}

.peninjauan-ubah {
    background: none;
    border: none;
    color: #0284c7;
    font-size: 0.8125rem;
    cursor: pointer;
}

.peninjauan-baris {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.3rem 0;
    font-size: 0.8125rem;
}

.peninjauan-baris dt {
    opacity: 0.65;
}

.peninjauan-baris dd {
    margin: 0;
    font-weight: 600;
    text-align: right;
}
</style>
```

- [ ] **Step 5: Tulis `Wizard.vue`**

```vue
<template>
    <div class="pd-halaman">
        <div class="pd-kartu">
            <LangkahIndikator :langkah="skema.langkah" :aktif="indeks" :selesai="langkahSelesai" />

            <template v-if="!diPeninjauan">
                <p class="pd-posisi">Langkah {{ indeks + 1 }} dari {{ skema.langkah.length }}</p>
                <h1 class="pd-judul">{{ langkahAktif.judul }}</h1>

                <BidangIsian
                    v-for="kunci in langkahAktif.field"
                    :key="kunci"
                    :kunci="kunci"
                    :definisi="skema.field[kunci]"
                    :model-value="nilai[kunci] ?? ''"
                    :galat="galat[kunci] ?? null"
                    :pilihan-dinamis="pilihanDivisi"
                    @update:model-value="(v) => setNilai(kunci, v)"
                />
            </template>

            <PanelPeninjauan v-else :skema="skema" :nilai="nilai" @lompat="lompatKe" />

            <div class="pd-aksi">
                <button v-if="indeks > 0 || diPeninjauan" type="button" class="pd-tombol" @click="mundur">
                    Kembali
                </button>
                <button
                    v-if="!diPeninjauan"
                    type="button"
                    class="pd-tombol pd-tombol-utama"
                    data-uji="lanjut"
                    @click="maju"
                >
                    Lanjut
                </button>
                <button v-else type="button" class="pd-tombol pd-tombol-utama" data-uji="kirim" @click="kirim">
                    Kirim
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BidangIsian from './components/BidangIsian.vue';
import LangkahIndikator from './components/LangkahIndikator.vue';
import PanelPeninjauan from './components/PanelPeninjauan.vue';

const props = defineProps({
    skema: { type: Object, required: true },
    status: { type: Object, required: true },
    draft: { type: Object, required: true },
    pilihanDivisi: { type: Array, default: () => [] },
});

const nilai = ref({ ...props.draft.data });
const indeks = ref(Math.min(props.draft.langkah ?? 0, props.skema.langkah.length - 1));
const diPeninjauan = ref(false);
const galat = ref({});

const langkahAktif = computed(() => props.skema.langkah[indeks.value]);
const langkahSelesai = computed(() =>
    props.skema.langkah.map((_, i) => i).filter((i) => i < indeks.value),
);

const setNilai = (kunci, v) => {
    nilai.value[kunci] = v;
    if (galat.value[kunci]) delete galat.value[kunci];
};

// Pemeriksaan di browser hanya untuk mencegah maju dengan tangan kosong.
// Validasi yang MENGIKAT tetap di server saat kirim.
const langkahIniLengkap = () => {
    const kurang = {};

    langkahAktif.value.field.forEach((kunci) => {
        const v = nilai.value[kunci];
        if (v === undefined || String(v).trim() === '') kurang[kunci] = 'Wajib diisi';
    });

    galat.value = kurang;

    return Object.keys(kurang).length === 0;
};

// Endpoint draf mengembalikan JSON biasa, BUKAN respons Inertia — jadi wajib
// lewat axios. Memakai router.post di sini membuat Inertia menolak responsnya.
// Kegagalan sengaja ditelan: draf yang gagal tersimpan tidak boleh menghalangi
// user melanjutkan pengisian.
const simpanDraf = () => {
    axios
        .post('/pengumpulan-data/draft', { data: nilai.value, langkah: indeks.value })
        .catch(() => {});
};

const maju = () => {
    if (!langkahIniLengkap()) return;

    if (indeks.value < props.skema.langkah.length - 1) {
        indeks.value += 1;
    } else {
        diPeninjauan.value = true;
    }

    simpanDraf();
};

const mundur = () => {
    if (diPeninjauan.value) {
        diPeninjauan.value = false;
        return;
    }

    if (indeks.value > 0) indeks.value -= 1;
};

const lompatKe = (i) => {
    diPeninjauan.value = false;
    indeks.value = i;
};

const kirim = () => {
    router.post('/pengumpulan-data/kirim', { data: nilai.value });
};
</script>

<style scoped>
.pd-halaman {
    display: flex;
    justify-content: center;
    padding: 1rem;
}

.pd-kartu {
    width: 100%;
    max-width: 34rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.9rem;
    padding: 1.5rem;
}

.pd-posisi {
    font-size: 0.75rem;
    opacity: 0.6;
    margin: 0;
}

.pd-judul {
    font-size: 1.25rem;
    margin: 0.2rem 0 1.25rem;
}

.pd-aksi {
    display: flex;
    gap: 0.6rem;
    margin-top: 1.25rem;
}

.pd-tombol {
    flex: 1;
    padding: 0.75rem 1rem;
    border-radius: 0.6rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-size: 0.9375rem;
    cursor: pointer;
}

.pd-tombol-utama {
    background: #0ea5e9;
    border-color: #0ea5e9;
    color: #fff;
    font-weight: 600;
}

@media (min-width: 768px) {
    .pd-kartu {
        padding: 2rem 2.25rem;
    }
}
</style>
```

- [ ] **Step 6: Tulis `Ringkasan.vue`**

```vue
<template>
    <div class="pd-halaman">
        <div class="pd-kartu">
            <p class="pd-lencana">Terkirim</p>
            <h1 class="pd-judul">Terima kasih — data kamu sudah masuk</h1>
            <p class="pd-catatan">
                Dikirim pada {{ dikirimPada }}. Jawaban ini terkunci. Bila ada yang keliru,
                hubungi HR untuk membuka kuncinya.
            </p>

            <div v-for="l in skema.langkah" :key="l.kunci" class="pd-grup">
                <h2>{{ l.judul }}</h2>
                <dl>
                    <div v-for="kunci in l.field" :key="kunci" class="pd-baris">
                        <dt>{{ skema.field[kunci].label }}</dt>
                        <dd>{{ skema.field[kunci].label_pilihan?.[jawaban[kunci]] ?? jawaban[kunci] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    skema: { type: Object, required: true },
    jawaban: { type: Object, required: true },
    dikirimPada: { type: String, default: '' },
});
</script>

<style scoped>
.pd-halaman {
    display: flex;
    justify-content: center;
    padding: 1rem;
}

.pd-kartu {
    width: 100%;
    max-width: 34rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.9rem;
    padding: 1.5rem;
}

.pd-lencana {
    display: inline-block;
    font-size: 0.6875rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    background: #dcfce7;
    color: #166534;
    padding: 0.25rem 0.6rem;
    border-radius: 99px;
    margin: 0;
}

.pd-judul {
    font-size: 1.25rem;
    margin: 0.6rem 0 0.35rem;
}

.pd-catatan {
    font-size: 0.8125rem;
    opacity: 0.7;
    margin: 0 0 1.25rem;
}

.pd-grup {
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    padding: 0.85rem 1rem;
    margin-bottom: 0.75rem;
}

.pd-grup h2 {
    font-size: 0.875rem;
    margin: 0 0 0.5rem;
}

.pd-baris {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.3rem 0;
    font-size: 0.8125rem;
}

.pd-baris dt {
    opacity: 0.65;
}

.pd-baris dd {
    margin: 0;
    font-weight: 600;
    text-align: right;
}
</style>
```

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run: `npx vitest run resources/js/Pages/PengumpulanData/Wizard.test.js`
Expected: PASS, 5 test.

- [ ] **Step 8: Coba di browser sungguhan**

Nyalakan kampanye sementara, lalu buka `/pengumpulan-data` lewat vhost (`hcis-kpi-grouped.test`, **bukan** `artisan serve`). Periksa:
- lebar 360px (HP), 768px (tablet), 1440px (desktop) — tidak ada gulir mendatar
- isi langkah 1–2, tutup tab, buka lagi → kembali di langkah yang sama
- kirim → mendarat di halaman ringkasan
- buka `/pengumpulan-data` lagi → dialihkan ke ringkasan, tidak bisa mengisi ulang

Kembalikan `Is_Aktif` ke `'T'` setelah selesai mencoba.

- [ ] **Step 9: Commit**

```bash
git add resources/js/Pages/PengumpulanData
git commit -m "feat(pengumpulan-data): halaman wizard 4 langkah dan ringkasan terkunci"
```

---

## Yang sengaja belum dikerjakan di fase ini

- Modal hero, bar menetap, dan tiga mode frekuensi → **fase 3**
- Halaman admin (pantau, detail, pengecualian, buka kunci, sakelar, audit) → **fase 4**
- Ekspor Excel, pemeriksa kualitas data, kirim pengingat → **fase 5**
- Baris menu `N_HRIS_Menus` untuk ESS dan Administrator HCIS → **fase 4**, setelah struktur tabel menu dibaca langsung dari DB
- `useScrollLock.js` (kunci gulir berhitung) → **fase 3**, dibutuhkan pertama kali oleh modal

Selama fase 1–2 berjalan di produksi, kolom `Is_Aktif` pada `N_HRIS_Pengumpulan_Data_Config` tetap `'T'` — tidak ada karyawan yang melihat apa pun.
