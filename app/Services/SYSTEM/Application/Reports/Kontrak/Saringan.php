<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

class Saringan
{
    /** @param string[] $operator */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $kolom,
        public readonly string $tipe,
        public readonly array $operator,
        public readonly bool $wajibSaatJalan = false,
    ) {}
}
