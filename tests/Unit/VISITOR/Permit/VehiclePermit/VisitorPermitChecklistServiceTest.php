<?php

namespace Tests\Unit\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class VisitorPermitChecklistServiceTest extends TestCase
{
    public function test_answers_are_written_with_one_atomic_update_statement(): void
    {
        DB::shouldReceive('update')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertStringContainsString('INNER JOIN (VALUES (?, ?, ?), (?, ?, ?))', $sql);
                $this->assertSame([10, 'Y', null, 20, 'T', 'Perlu perbaikan', 'PMT-TEST'], $bindings);

                return true;
            })
            ->andReturn(2);

        $affected = app(VisitorPermitChecklistService::class)->saveAnswers('PMT-TEST', [
            ['question_id' => 10, 'jawaban' => 'Y'],
            ['question_id' => 20, 'jawaban' => 'T', 'catatan' => 'Perlu perbaikan'],
        ]);

        $this->assertSame(2, $affected);
    }

    public function test_duplicate_question_is_rejected_before_database_write(): void
    {
        DB::shouldReceive('update')->never();

        $this->expectException(InvalidArgumentException::class);

        app(VisitorPermitChecklistService::class)->saveAnswers('PMT-TEST', [
            ['question_id' => 10, 'jawaban' => 'Y'],
            ['question_id' => 10, 'jawaban' => 'T'],
        ]);
    }
}
