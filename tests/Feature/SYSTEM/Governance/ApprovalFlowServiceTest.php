<?php

namespace Tests\Feature\SYSTEM\Governance;

use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowResult;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApproverResolverService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for ApprovalFlowService
 *
 * Run with: php artisan test --filter=ApprovalFlowServiceTest
 *
 * Note: These tests use existing data in HRIS_Approval_Flow to avoid
 * complex table constraints. Only cleanup HRIS_Approval_Request test data.
 */
class ApprovalFlowServiceTest extends TestCase
{
    protected ApprovalFlowService $service;

    protected ?string $realKaryawan = null;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = new ApproverResolverService;
        $this->service = new ApprovalFlowService($resolver);

        // Find a real karyawan that has approval flow for testing
        $flow = DB::table('HRIS_Approval_Flow')
            ->whereNull('Status')
            ->first();

        if ($flow) {
            $this->realKaryawan = $flow->Kode_Karyawan_Requester;
        }

        // Clean up any previous test data (only approval requests)
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    protected function cleanupTestData(): void
    {
        // Only clean approval requests with TEST prefix
        DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', 'like', 'TEST-%')
            ->delete();
    }

    // =====================
    // ApprovalFlowResult Tests (Unit Tests - No DB)
    // =====================

    public function test_approval_flow_result_success_returns_correct_data(): void
    {
        $insertedIds = collect([1, 2, 3]);
        $firstApprover = (object) ['type' => 'SERIAL', 'kode_karyawan' => 'EMP001'];

        $result = ApprovalFlowResult::success($insertedIds, $firstApprover);

        $this->assertTrue($result->success);
        $this->assertNull($result->error);
        $this->assertEquals(3, $result->getStepCount());
        $this->assertTrue($result->hasFirstApprover());
        $this->assertEquals('EMP001', $result->getFirstApproverKode());
    }

    public function test_approval_flow_result_failed_returns_error(): void
    {
        $result = ApprovalFlowResult::failed('Error message');

        $this->assertFalse($result->success);
        $this->assertEquals('Error message', $result->error);
        $this->assertEquals(0, $result->getStepCount());
        $this->assertFalse($result->hasFirstApprover());
    }

    public function test_approval_flow_result_group_detection(): void
    {
        $groupApprover = (object) ['type' => 'GROUP', 'kode_karyawan' => null, 'group_id' => 100];
        $result = ApprovalFlowResult::success(collect([1]), $groupApprover);

        $this->assertTrue($result->isGroupFirst());
        $this->assertNull($result->getFirstApproverKode());
        $this->assertEquals(100, $result->getFirstApproverGroupId());
    }

    // =====================
    // createFlowForLegacy Tests (Integration with Real Data)
    // =====================

    public function test_create_flow_for_legacy_inserts_approval_requests(): void
    {
        if (! $this->realKaryawan) {
            $this->markTestSkipped('No approval flow data available for testing.');
        }

        // Act
        $result = $this->service->createFlowForLegacy('TEST-001', $this->realKaryawan);

        // Assert
        $this->assertTrue($result->success, 'Flow creation should succeed: '.($result->error ?? ''));
        $this->assertGreaterThan(0, $result->getStepCount(), 'Should create at least 1 approval step');

        // Verify database
        $requests = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', 'TEST-001')
            ->orderBy('Request_Order_Flow')
            ->get();

        $this->assertGreaterThan(0, $requests->count());
        $this->assertEquals(1, $requests->first()->Request_Order_Flow);
    }

    public function test_create_flow_for_legacy_fails_when_no_approver(): void
    {
        // Act - use a non-existent karyawan
        $result = $this->service->createFlowForLegacy('TEST-002', 'ZZZZ_NONEXISTENT_KARYAWAN_999');

        // Assert
        $this->assertFalse($result->success);
        $this->assertStringContainsString('tidak ditemukan', $result->error);
    }

    public function test_create_flow_for_legacy_returns_first_approver(): void
    {
        if (! $this->realKaryawan) {
            $this->markTestSkipped('No approval flow data available for testing.');
        }

        // Act
        $result = $this->service->createFlowForLegacy('TEST-003', $this->realKaryawan);

        // Assert
        $this->assertTrue($result->hasFirstApprover());
        $this->assertNotEmpty($result->getFirstApproverKode());
        $this->assertFalse($result->isGroupFirst());
    }

    // =====================
    // createFlowWithHC Tests
    // =====================

    public function test_create_flow_with_hc_adds_group_at_end(): void
    {
        if (! $this->realKaryawan) {
            $this->markTestSkipped('No approval flow data available for testing.');
        }

        // Act
        $result = $this->service->createFlowWithHC('TEST-004', $this->realKaryawan, 100);

        // Assert
        $this->assertTrue($result->success);
        $this->assertGreaterThan(1, $result->getStepCount()); // Approvers + HC group

        // Verify last request is group
        $lastRequest = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', 'TEST-004')
            ->orderBy('Request_Order_Flow', 'desc')
            ->first();

        $this->assertEquals('Y', $lastRequest->Flag_Group);
        $this->assertEquals(100, $lastRequest->Group_Reference_Id);
    }

    // =====================
    // Order Flow Tests
    // =====================

    public function test_request_order_flow_is_properly_indexed(): void
    {
        if (! $this->realKaryawan) {
            $this->markTestSkipped('No approval flow data available for testing.');
        }

        // Act
        $result = $this->service->createFlowForLegacy('TEST-005', $this->realKaryawan);

        // Assert
        $requests = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', 'TEST-005')
            ->orderBy('Request_Order_Flow')
            ->get();

        // Verify sequential ordering starts from 1
        foreach ($requests as $index => $request) {
            $expectedOrder = $index + 1;
            $this->assertEquals(
                $expectedOrder,
                $request->Request_Order_Flow,
                "Request order flow should be {$expectedOrder}"
            );
        }
    }
}
