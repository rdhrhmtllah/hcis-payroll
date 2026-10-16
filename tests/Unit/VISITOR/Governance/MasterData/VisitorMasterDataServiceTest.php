<?php

namespace Tests\Unit\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataService;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class VisitorMasterDataServiceTest extends TestCase
{
    public function test_unique_lock_serializes_mutations_per_master_table(): void
    {
        $resources = [];
        DB::shouldReceive('transactionLevel')->twice()->andReturn(1);
        DB::shouldReceive('selectOne')
            ->twice()
            ->andReturnUsing(function (string $sql, array $bindings) use (&$resources) {
                $this->assertStringContainsString('sp_getapplock', $sql);
                $resources[] = $bindings[0];

                return (object) ['result' => 0];
            });

        $definition = [
            'table' => 'N_EMI_VISITOR_Master_Vehicle_Color',
            'unique_by' => ['Nama_Warna'],
        ];
        $method = new ReflectionMethod(VisitorMasterDataService::class, 'acquireUniqueMutationLock');
        $method->setAccessible(true);
        $service = app(VisitorMasterDataService::class);

        $method->invoke($service, $definition);
        $method->invoke($service, $definition);

        $this->assertCount(2, $resources);
        $this->assertSame($resources[0], $resources[1]);
    }

    public function test_unique_lock_failure_is_not_ignored(): void
    {
        DB::shouldReceive('transactionLevel')->once()->andReturn(1);
        DB::shouldReceive('selectOne')->once()->andReturn((object) ['result' => -1]);

        $method = new ReflectionMethod(VisitorMasterDataService::class, 'acquireUniqueMutationLock');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $method->invoke(app(VisitorMasterDataService::class), [
            'table' => 'N_EMI_VISITOR_Master_Purpose',
            'unique_by' => ['Nama_Keperluan'],
        ]);
    }
}
