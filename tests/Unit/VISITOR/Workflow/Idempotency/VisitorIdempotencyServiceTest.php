<?php

namespace Tests\Unit\VISITOR\Workflow\Idempotency;

use App\Services\VISITOR\Workflow\Idempotency\VisitorIdempotencyService;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisitorIdempotencyServiceTest extends TestCase
{
    #[DataProvider('duplicateCodes')]
    public function test_recognizes_expected_sql_server_unique_index_conflict(int $code): void
    {
        $previous = new PDOException(
            "SQLSTATE[23000]: Cannot insert duplicate key row in object with unique index 'UX_VISITOR_Test' ({$code})",
            23000,
        );
        $previous->errorInfo = ['23000', $code, "Violation of unique index 'UX_VISITOR_Test'"];
        $exception = new QueryException('sqlsrv', 'insert into visitor values (?)', ['key'], $previous);

        $this->assertTrue(
            app(VisitorIdempotencyService::class)->isUniqueKeyConflict($exception, 'UX_VISITOR_Test'),
        );
    }

    public function test_rejects_other_indexes_and_non_database_errors(): void
    {
        $previous = new PDOException(
            "Cannot insert duplicate key row with unique index 'UX_OTHER' (2601)",
            23000,
        );
        $exception = new QueryException('sqlsrv', 'insert into visitor values (?)', ['key'], $previous);
        $service = app(VisitorIdempotencyService::class);

        $this->assertFalse($service->isUniqueKeyConflict($exception, 'UX_VISITOR_Test'));
        $this->assertFalse($service->isUniqueKeyConflict(new \RuntimeException('2601 UX_VISITOR_Test'), 'UX_VISITOR_Test'));
    }

    public static function duplicateCodes(): array
    {
        return [[2601], [2627]];
    }
}
