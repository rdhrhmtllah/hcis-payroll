<?php

namespace App\Services\VISITOR\Workflow\Idempotency;

use Illuminate\Database\QueryException;
use Throwable;

class VisitorIdempotencyService
{
    public function isUniqueKeyConflict(Throwable $exception, string $expectedIndex): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $errorInfo = array_filter(
            $exception->errorInfo ?? [],
            static fn ($value) => is_scalar($value),
        );
        $databaseMessage = $exception->getMessage().' '.implode(' ', $errorInfo);

        $isSqlServerDuplicate = str_contains($databaseMessage, '2601')
            || str_contains($databaseMessage, '2627');

        return $isSqlServerDuplicate && str_contains($databaseMessage, $expectedIndex);
    }
}
