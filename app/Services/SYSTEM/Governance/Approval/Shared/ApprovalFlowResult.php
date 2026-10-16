<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use Illuminate\Support\Collection;

/**
 * Data Transfer Object for ApprovalFlowService results
 *
 * Encapsulates the result of an approval flow creation operation,
 * including success/failure state, inserted record IDs, and first approver info.
 */
class ApprovalFlowResult
{
    public bool $success;

    public ?string $error;

    public Collection $insertedIds;

    public ?object $firstApprover;

    /**
     * Create a success result
     *
     * @param  Collection  $insertedIds  IDs of inserted HRIS_Approval_Request records
     * @param  object|null  $firstApprover  First approver info (for notifications)
     */
    public static function success(Collection $insertedIds, ?object $firstApprover = null): self
    {
        $result = new self;
        $result->success = true;
        $result->error = null;
        $result->insertedIds = $insertedIds;
        $result->firstApprover = $firstApprover;

        return $result;
    }

    /**
     * Create a failed result
     *
     * @param  string  $error  Error message
     */
    public static function failed(string $error): self
    {
        $result = new self;
        $result->success = false;
        $result->error = $error;
        $result->insertedIds = collect();
        $result->firstApprover = null;

        return $result;
    }

    /**
     * Check if first approver is available
     */
    public function hasFirstApprover(): bool
    {
        return $this->firstApprover !== null;
    }

    /**
     * Get first approver's Kode_Karyawan
     */
    public function getFirstApproverKode(): ?string
    {
        return $this->firstApprover?->kode_karyawan;
    }

    /**
     * Check if first approver is a group (parallel approval)
     */
    public function isGroupFirst(): bool
    {
        return $this->firstApprover?->type === 'GROUP';
    }

    /**
     * Get first approver's group ID (for group approvals)
     */
    public function getFirstApproverGroupId(): ?int
    {
        return $this->firstApprover?->group_id;
    }

    /**
     * Get count of approval steps created
     */
    public function getStepCount(): int
    {
        return $this->insertedIds->count();
    }
}
