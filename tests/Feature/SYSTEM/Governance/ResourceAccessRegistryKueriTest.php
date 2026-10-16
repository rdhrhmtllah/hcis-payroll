<?php

namespace Tests\Feature\SYSTEM\Governance;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationResourceRegistryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Keanggotaan registry resource diambil sekali, bukan per baris.
 *
 * `isRegistered()` menembak satu kueri `exists` tiap kali dipanggil, dan dua
 * tempat memanggilnya di dalam perulangan baris. Pada
 * `api/v1/system-auth/resource-access` itu berarti **20 kueri `exists`** untuk
 * satu halaman, dan angkanya naik lurus mengikuti ukuran halaman.
 *
 * Sesudah dilayani `terdaftarMassal()`: **26 → 7 kueri**, jawaban identik.
 *
 * MENGAPA BUKAN MEMOISASI DI DALAM isRegistered().
 * Jalur tulis (`saveResourceAccess`) mengubah tabel yang sama. Ingatan yang
 * dipasang di dalam pemeriksanya bisa terbaca basi dalam satu permintaan;
 * bentuk jamak yang dipanggil sekali di awal perulangan tidak punya masalah itu.
 */
class ResourceAccessRegistryKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const TABEL = 'N_HRIS_Role_Resource_Access';

    protected function setUp(): void
    {
        parent::setUp();

        $aktor = User::query()->where('Id_Users', 4)->first()
            ?? User::query()->orderBy('Id_Users')->first();

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user di data dev.');
        }

        $this->withoutMiddleware();
        Auth::login($aktor);
    }

    public function test_keanggotaan_registry_tidak_ditanya_per_baris(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson('/api/v1/system-auth/resource-access');
        $res->assertOk();

        $baris = count($res->json('data') ?? []);
        if ($baris < 3) {
            $this->markTestSkipped("Hanya {$baris} baris; terlalu sedikit untuk membedakan sekali dari per-baris.");
        }

        $n = 0;
        foreach ($kueri as $sql) {
            if (stripos($sql, self::TABEL) !== false) {
                $n++;
            }
        }

        /* Ambangnya RELATIF terhadap jumlah baris, bukan angka mati.
           Percobaan pertama memakai batas 3 dan langsung merah pada 4 kueri
           yang semuanya sah: hitungan total, daftar utama, Schema::hasColumn
           yang menyebut nama tabel yang sama, dan keanggotaan massal. Ambang
           mati seperti itu menuduh kode yang sudah benar.

           Yang benar-benar dijaga: jumlahnya tidak boleh tumbuh mengikuti
           baris. Empat untuk 20 baris jelas tetap; dua puluh untuk 20 baris
           jelas per-baris. */
        $this->assertLessThan(
            $baris,
            $n,
            'Kueri ke '.self::TABEL." berjalan {$n}x untuk {$baris} baris — biayanya ikut tumbuh "
            .'per baris. Keanggotaan registry harus diambil massal lewat terdaftarMassal() '
            .'sebelum map(), bukan lewat isRegistered() per baris.'
        );

        $this->assertLessThanOrEqual(
            6,
            $n,
            'Kueri ke '.self::TABEL." berjalan {$n}x. Meski belum sebanyak barisnya, itu lebih "
            .'dari yang dibutuhkan satu halaman; periksa apakah ada pemeriksaan yang menyelinap '
            .'kembali ke dalam perulangan.'
        );
    }

    /**
     * Sisi kedua: memangkas kueri tak berguna kalau jawabannya berubah.
     *
     * Kalau kunci pemetaan meleset, `master_registered` jatuh ke false untuk
     * semua baris — dan jumlah kuerinya justru terlihat makin bagus.
     */
    public function test_master_registered_tidak_seluruhnya_jatuh_ke_false(): void
    {
        $res = $this->getJson('/api/v1/system-auth/resource-access');
        $res->assertOk();

        $rows = $res->json('data') ?? [];
        if ($rows === []) {
            $this->markTestSkipped('Daftar resource access kosong di data dev.');
        }

        foreach ($rows as $baris) {
            $this->assertArrayHasKey('master_registered', $baris);
        }

        /* Premisnya diperiksa lebih dulu: kalau memang tak ada satu pun baris
           aktif di tabelnya, semua false itu jawaban yang benar. */
        $adaAktif = DB::table(self::TABEL)->where('Flag_Aktif', 'Y')->exists();
        if (! $adaAktif) {
            $this->markTestSkipped('Tak ada baris resource access aktif di data dev.');
        }

        $terdaftar = count(array_filter($rows, fn ($r) => ! empty($r['master_registered'])));

        $this->assertGreaterThan(
            0,
            $terdaftar,
            'Seluruh '.count($rows).' baris ber-master_registered=false, padahal tabelnya punya '
            .'baris aktif. Kunci pemetaan terdaftarMassal() kemungkinan meleset — gejala yang '
            .'justru membuat jumlah kueri tampak membaik.'
        );
    }

    /**
     * Bentuk jamak harus SEPAKAT dengan bentuk tunggal.
     *
     * BATAS YANG DIAKUI — soal huruf besar-kecil TIDAK terjaga di sini.
     * Bentuk tunggal membandingkan lewat SQL Server, yang dengan collation baku
     * tidak peka huruf; bentuk jamak mencocokkan di PHP, yang peka. Karena itu
     * kunci pencocokannya dihuruf-kecilkan.
     *
     * Tapi uji ini TIDAK bisa membuktikannya, dan itu sudah dicoba: mengubah
     * pencocokan jadi peka huruf tetap membuat uji ini hijau. Sebabnya variasi
     * huruf besar sudah ditolak lebih dulu oleh pemeriksaan `options()` — yang
     * peka huruf — di KEDUA bentuk, sehingga keduanya sepakat menjawab "tidak
     * terdaftar" tanpa pernah menyentuh basis data.
     *
     * Jadi normalisasi huruf itu bersifat pencegahan: ia baru berarti bila suatu
     * hari `Resource_Code` tersimpan dengan huruf yang berbeda dari definisinya
     * di `options()`. Ditulis di sini supaya tak ada yang mengira uji ini
     * menjaganya.
     *
     * Yang BENAR-BENAR dijaga uji ini: kedua bentuk memberi jawaban yang sama
     * untuk setiap baris yang diperiksa — termasuk sepakat menolak.
     */
    public function test_bentuk_jamak_sepakat_dengan_bentuk_tunggal(): void
    {
        $baris = DB::table(self::TABEL)
            ->where('Flag_Aktif', 'Y')
            ->limit(15)
            ->get(['Jenis_page', 'Resource_Type', 'Resource_Code']);

        if ($baris->isEmpty()) {
            $this->markTestSkipped('Tak ada baris resource access aktif di data dev.');
        }

        /** @var AuthorizationResourceRegistryService $svc */
        $svc = app(AuthorizationResourceRegistryService::class);

        /* Variasi huruf sengaja disertakan: kalau bentuk jamak peka huruf
           sementara bentuk tunggal tidak, perbedaannya muncul di sini. */
        $permintaan = [];
        foreach ($baris as $b) {
            $permintaan[] = [$b->Jenis_page, (string) $b->Resource_Type, (string) $b->Resource_Code];
            $permintaan[] = [$b->Jenis_page, strtoupper((string) $b->Resource_Type), strtoupper((string) $b->Resource_Code)];
        }

        $jamak = $svc->terdaftarMassal($permintaan);

        foreach ($permintaan as [$page, $type, $code]) {
            $tunggal = $svc->isRegistered($page, $type, $code);
            $kunci = AuthorizationResourceRegistryService::kunciTerdaftar($page, $type, $code);

            $this->assertArrayHasKey($kunci, $jamak, "terdaftarMassal() tak menjawab untuk kunci {$kunci}.");
            $this->assertSame(
                $tunggal,
                $jamak[$kunci],
                "isRegistered() dan terdaftarMassal() berbeda untuk [{$page}|{$type}|{$code}]: "
                .'tunggal='.var_export($tunggal, true).', jamak='.var_export($jamak[$kunci], true).'.'
            );
        }
    }
}
