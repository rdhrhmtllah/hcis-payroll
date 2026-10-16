<?php

namespace App\Services\VISITOR\Workflow\Verification;

/** Penolakan koreksi permit yang membawa kode galat dan status HTTP-nya sendiri. */
class PermitKoreksiException extends \RuntimeException
{
    public function __construct(string $pesan, public readonly string $kode, public readonly int $status)
    {
        parent::__construct($pesan);
    }

    public static function validasi(string $pesan): self
    {
        return new self($pesan, 'VISITOR_PERMIT_KOREKSI_VALIDASI', 422);
    }
}
