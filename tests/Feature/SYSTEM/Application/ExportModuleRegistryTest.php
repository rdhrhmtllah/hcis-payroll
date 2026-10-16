<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Registry modul export (config/exports.php) wajib memuat SETIAP service export.
 *
 * Mendaftarkan modul adalah langkah manual yang tidak dituntut apa pun: sebuah
 * service export baru bisa dibuat, di-dispatch, memproses data, menulis berkas,
 * dan menandai dirinya 'ready' TANPA satu pun baris di registry. Kegagalannya
 * baru muncul pada klik terakhir — ActivityCenterController::download()
 * meresolusi service lewat config("exports.modules.{Module}"), gagal
 * mendapatkannya, dan menjawab 404 "Export tidak ditemukan." untuk berkas yang
 * sebenarnya ada di disk dan siap diunduh.
 *
 * Itulah yang terjadi pada 'visitor-studio' dan 'pengumpulan-data-export':
 * keduanya menghasilkan berkas utuh yang tak bisa diunduh siapa pun.
 *
 * Uji ini memindai kelas, bukan daftar tetap, supaya modul export ke-tujuh pun
 * ikut terjaga tanpa ada yang perlu ingat memperbarui uji ini.
 */
class ExportModuleRegistryTest extends TestCase
{
    /** @return class-string<AbstractExportService>[] */
    private function serviceExport(): array
    {
        $berkas = Finder::create()->files()->in(app_path('Services'))->name('*.php');

        $kelas = [];
        foreach ($berkas as $f) {
            $nama = $this->kelasDari($f);
            if (! class_exists($nama)) {
                continue;
            }

            $r = new ReflectionClass($nama);
            if ($r->isAbstract() || ! $r->isSubclassOf(AbstractExportService::class)) {
                continue;
            }

            $kelas[] = $nama;
        }

        return $kelas;
    }

    /**
     * Nama kelas diturunkan dari JALUR berkas (PSR-4), bukan dari isinya.
     *
     * Menebaknya lewat regex `class (\w+)` menghasilkan nama yang bisa tidak
     * cocok dengan berkasnya; autoloader lalu memuat berkas itu untuk nama yang
     * salah, dan pemuatan berikutnya untuk nama yang benar mati dengan
     * "Cannot declare class ..., because the name is already in use" — jauh dari
     * penyebabnya dan tak ada hubungannya dengan registry yang sedang diperiksa.
     */
    private function kelasDari(SplFileInfo $f): string
    {
        $relatif = str_replace(
            [app_path().DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
            ['', '\\'],
            $f->getRealPath(),
        );

        return 'App\\'.substr($relatif, 0, -4);
    }

    private function moduleKey(string $kelas): string
    {
        // newInstanceWithoutConstructor: moduleKey() mengembalikan literal dan
        // tak menyentuh keadaan, jadi tak perlu memenuhi dependensi konstruktor
        // tiap service hanya untuk membaca identitasnya.
        $r = new ReflectionClass($kelas);
        $m = $r->getMethod('moduleKey');
        $m->setAccessible(true);

        return $m->invoke($r->newInstanceWithoutConstructor());
    }

    public function test_setiap_service_export_terdaftar_di_registry(): void
    {
        $daftar = config('exports.modules');
        $service = $this->serviceExport();

        $this->assertNotEmpty($service, 'Tidak ada service export yang terpindai — pemindainya yang rusak, bukan registry-nya.');

        $hilang = [];
        foreach ($service as $kelas) {
            $kunci = $this->moduleKey($kelas);
            if (($daftar[$kunci] ?? null) !== $kelas) {
                $hilang[] = "{$kunci} => {$kelas} (registry: ".($daftar[$kunci] ?? 'TIDAK ADA').')';
            }
        }

        $this->assertSame([], $hilang, "Service export berikut tidak terdaftar di config/exports.php, sehingga unduhannya akan 404 meski berkasnya siap:\n- ".implode("\n- ", $hilang));
    }

    public function test_setiap_baris_registry_menunjuk_service_yang_kuncinya_cocok(): void
    {
        // Kunci yang salah ketik sama diamnya dengan kunci yang hilang: baris
        // registry-nya ada, tapi tak pernah cocok dengan kolom Module mana pun.
        foreach (config('exports.modules') as $kunci => $kelas) {
            $this->assertTrue(class_exists($kelas), "Registry menunjuk kelas yang tidak ada: {$kunci} => {$kelas}");
            $this->assertTrue(
                is_subclass_of($kelas, AbstractExportService::class),
                "Registry '{$kunci}' menunjuk kelas yang bukan turunan AbstractExportService: {$kelas}",
            );
            $this->assertSame(
                $kunci,
                $this->moduleKey($kelas),
                "Kunci registry '{$kunci}' tidak sama dengan moduleKey() milik {$kelas} — kolom Module tak akan pernah cocok.",
            );
        }
    }
}
