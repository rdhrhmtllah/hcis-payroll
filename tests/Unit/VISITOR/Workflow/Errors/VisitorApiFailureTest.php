<?php

namespace Tests\Unit\VISITOR\Workflow\Errors;

use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class VisitorApiFailureTest extends TestCase
{
    public function test_internal_exception_is_logged_but_not_exposed(): void
    {
        Log::shouldReceive('error')->once()->withArgs(function (string $event, array $context): bool {
            $this->assertSame('VISITOR_API_OPERATION_FAILED', $event);
            $this->assertSame('VISITOR_TEST_FAILED', $context['error_code']);
            $this->assertInstanceOf(RuntimeException::class, $context['exception']);

            return true;
        });

        $response = VisitorApiFailure::respond(
            new RuntimeException('Invalid object name N_SECRET_TABLE'),
            'test_operation',
            'VISITOR_TEST_FAILED',
            'Operasi gagal diproses.',
        );
        $payload = $response->getData(true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Operasi gagal diproses.', $payload['message']);
        $this->assertSame('VISITOR_TEST_FAILED', $payload['error_code']);
        $this->assertArrayHasKey('request_id', $payload);
        $this->assertStringNotContainsString('N_SECRET_TABLE', json_encode($payload));
    }
}
