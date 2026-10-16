<?php

namespace Tests\Unit\SYSTEM\Governance;

use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApproverResolverService;
use Mockery;
use Tests\TestCase;

class ApprovalFlowServiceDecisionTest extends TestCase
{
    public function test_direct_date_policy_does_not_require_approval_config_lookup(): void
    {
        $service = new ApprovalFlowService(Mockery::mock(ApproverResolverService::class));

        $decision = $service->decide([
            'approval_type' => 'CONFIG_THAT_DOES_NOT_NEED_TO_EXIST',
            'requester_kode' => 'K001',
            'tanggal' => '2026-05-16',
            'classification' => [
                'jenis_pengajuan' => 'HARI_H',
                'needs_approval' => false,
            ],
        ]);

        $this->assertSame('DIRECT', $decision['action']);
        $this->assertNull($decision['config_id']);
        $this->assertNull($decision['config_name']);
    }
}
