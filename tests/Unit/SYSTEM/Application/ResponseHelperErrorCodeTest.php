<?php

namespace Tests\Unit\SYSTEM\Application;

use App\Helpers\ResponseHelper;
use Tests\TestCase;

class ResponseHelperErrorCodeTest extends TestCase
{
    public function test_mengirim_error_code_dan_errors(): void
    {
        $res = ResponseHelper::errorWithCode(
            'Definisi laporan tidak sah.',
            'REPORT_DEFINITION_INVALID',
            422,
            ['dimensi' => ['Dimensi tidak dikenal: xyz']]
        );

        $this->assertSame(422, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('REPORT_DEFINITION_INVALID', $body['error_code']);
        $this->assertSame(['dimensi' => ['Dimensi tidak dikenal: xyz']], $body['errors']);
        $this->assertArrayHasKey('request_id', $body);
    }

    public function test_error_lama_tidak_berubah(): void
    {
        $body = json_decode(ResponseHelper::error('Gagal', 500)->getContent(), true);

        $this->assertSame(['success', 'status', 'message'], array_keys($body));
    }
}
