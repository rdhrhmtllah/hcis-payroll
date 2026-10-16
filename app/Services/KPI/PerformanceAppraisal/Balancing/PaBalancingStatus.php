<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

/**
 * Kosakata Balancing. Divalidasi di backend, bukan CHECK di basis data (AGENTS.md: daftar
 * nilai bisnis yang bisa tumbuh tidak dikunci constraint).
 */
final class PaBalancingStatus
{
    public const TERBUKA = 'TERBUKA';

    public const DRAFT = 'DRAFT';

    public const FINAL = 'FINAL';

    public const DIBATALKAN = 'DIBATALKAN';

    /** Status sesi yang isiannya masih boleh diubah. */
    public const SESI_BISA_DIUBAH = [self::TERBUKA, self::DRAFT];

    public const BELUM = 'BELUM';

    public const TANPA_PERUBAHAN = 'TANPA_PERUBAHAN';

    public const DIUBAH = 'DIUBAH';

    public const SEBAB_BALANCING = 'BALANCING';

    public const SEBAB_KURVA = 'KURVA';

    public const SEBAB_KEDUANYA = 'KEDUANYA';

    public const SEBAB_TANPA_PERUBAHAN = 'TANPA_PERUBAHAN';
}
