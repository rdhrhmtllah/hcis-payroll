<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use Tests\TestCase;

class AuditAksesTanpaTabelTest extends TestCase
{
    public function test_tanpa_tabel_tidak_gagal(): void
    {
        $audit = new class extends AuditAkses
        {
            protected function tabelAda(): bool
            {
                return false;
            }
        };

        $this->assertFalse($audit->catat('GRANT', null, 'BERI', [], ['x'], 1));
    }
}
