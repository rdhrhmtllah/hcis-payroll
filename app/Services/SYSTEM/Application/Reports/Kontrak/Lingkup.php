<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Siapa yang menjalankan, dan apa yang boleh ia lihat.
 *
 * TIDAK PERNAH disimpan di template. Dibangun ulang tiap kali laporan
 * dijalankan — itulah yang membuat satu template bisa dibagikan ke banyak
 * leader dan tiap orang menerima barisnya sendiri.
 */
class Lingkup
{
    /** @param string[] $anggotaTim */
    public function __construct(
        public readonly string $kodeKaryawan,
        public readonly bool $adminPenuh,
        public readonly array $anggotaTim,
        public readonly bool $bolehFieldTerbatas,
    ) {}
}
