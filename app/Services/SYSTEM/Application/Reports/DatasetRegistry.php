<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use RuntimeException;

/**
 * Katalog dataset per modul.
 *
 * Kunci penyimpanannya (module, kunci) dan bukan kunci saja: modul boleh punya
 * dataset bernama sama tanpa bertabrakan, dan yang lebih penting, satu modul
 * tidak bisa menjangkau dataset modul lain hanya dengan menebak namanya.
 */
class DatasetRegistry
{
    /** @var array<string, array<string, DatasetLaporan>> */
    private array $peta = [];

    public function daftarkan(string $module, DatasetLaporan $dataset): void
    {
        $this->peta[$module][$dataset->kunci()] = $dataset;
    }

    public function ambil(string $module, string $kunci): DatasetLaporan
    {
        // Sengaja melempar, bukan mengembalikan null: pemanggil yang lupa
        // memeriksa null akan meneruskan nilai kosong ke compiler dan gagal
        // jauh dari sebabnya.
        if (! isset($this->peta[$module][$kunci])) {
            throw new RuntimeException("Dataset tidak dikenal: {$module}/{$kunci}");
        }

        return $this->peta[$module][$kunci];
    }

    /** @return array<string, DatasetLaporan> */
    public function semua(string $module): array
    {
        return $this->peta[$module] ?? [];
    }

    /**
     * Seluruh dataset terdaftar, dari SEMUA modul sekaligus.
     *
     * Dipakai penjaga lintas modul (mis. tabrakan kunci dimensi/ukuran) yang
     * perlu menyapu setiap dataset yang pernah didaftarkan lewat daftarkan(),
     * tanpa perlu tahu di muka daftar nama modulnya.
     *
     * @return array<string, array<string, DatasetLaporan>>
     */
    public function semuaModul(): array
    {
        return $this->peta;
    }
}
