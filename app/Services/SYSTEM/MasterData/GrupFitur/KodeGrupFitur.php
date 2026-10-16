<?php

namespace App\Services\SYSTEM\MasterData\GrupFitur;

/**
 * Kode grup fitur KANONIK, dan alias yang dipakai lingkungan lain untuk grup yang sama.
 *
 * `N_HRIS_Master_Grup_Fitur.Kode_Grup_Fitur` tidak sama di setiap basis data. Diverifikasi
 * 14 Okt 2026: development memakai KPI dan VISITOR, production memakai PMS dan VMS — Id-nya
 * sama (2 dan 4). Kode program dan data turunan (kontak bantuan, menu) memakai nama kanonik;
 * kode yang mencocokkan langsung ke master grup patah di production — Menu & Matrix KPI dan
 * Visitor menjawab 404 di sana.
 *
 * Production sengaja dibiarkan PMS/VMS (keputusan user 14 Okt 2026). Karena itu setiap
 * pencocokan kode grup lewat kelas ini: `varian()` untuk mencari di master, `kanonik()`
 * untuk membandingkan.
 */
final class KodeGrupFitur
{
    /** kanonik => alias di lingkungan lain */
    private const ALIAS = [
        'KPI' => ['PMS'],
        'VISITOR' => ['VMS'],
    ];

    /** Kode kanonik (huruf besar) dari kode apa pun yang dipakai suatu lingkungan. */
    public static function kanonik(?string $kode): string
    {
        $kode = strtoupper(trim((string) $kode));

        foreach (self::ALIAS as $kanonik => $alias) {
            if (in_array($kode, $alias, true)) {
                return $kanonik;
            }
        }

        return $kode;
    }

    /**
     * Semua kode (huruf besar) yang berarti grup yang sama — untuk mencari di master grup.
     *
     * @return list<string>
     */
    public static function varian(string $kode): array
    {
        $kanonik = self::kanonik($kode);

        return array_values(array_unique([$kanonik, ...(self::ALIAS[$kanonik] ?? [])]));
    }
}
