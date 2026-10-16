<?php

namespace App\Services\SYSTEM\Application\Reports;

/**
 * Pembuat tautan berkas untuk kolom export berformat `tautan:{kunci}`.
 *
 * Nilai kolomnya di SQL hanyalah ID berkas; tautan yang bisa dibuka baru
 * dibuat saat baris disajikan (ReportRunner::formatNilai), karena tanda
 * tangan dan masa berlakunya dihitung di PHP, bukan di basis data. Modul
 * mendaftarkan pembuatnya sendiri — mesin laporan tidak perlu tahu di mana
 * berkas modul itu disimpan atau bagaimana ia disajikan.
 */
class TautanBerkasRegistry
{
    /** @var array<string, callable(string): ?string> */
    private array $pembuat = [];

    public function daftarkan(string $kunci, callable $pembuat): void
    {
        $this->pembuat[$kunci] = $pembuat;
    }

    public function punya(string $kunci): bool
    {
        return isset($this->pembuat[$kunci]);
    }

    /** Tautan untuk satu nilai, atau null bila kunci tak dikenal / berkas tak ada. */
    public function buat(string $kunci, string $nilai): ?string
    {
        if (! isset($this->pembuat[$kunci])) {
            return null;
        }

        return ($this->pembuat[$kunci])($nilai);
    }
}
