<?php

namespace App\Models\SYSTEM\Application\Outbox\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTaskOutbox extends Model
{
    protected $table = 'N_SYSTEM_Task_Outbox';

    protected $primaryKey = 'Id_Task_Outbox';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'Payload' => 'array',
        'Attempt_Count' => 'integer',
        'Max_Attempts' => 'integer',
        'Aggregate_Id' => 'integer',
        'Available_At' => 'datetime',
        'Locked_At' => 'datetime',
        'Dispatched_At' => 'datetime',
        'Created_At' => 'datetime',
        'Updated_At' => 'datetime',
    ];

    public const STATUS_QUEUED = 'QUEUED';

    public const STATUS_DISPATCHING = 'DISPATCHING';

    public const STATUS_DISPATCHED = 'DISPATCHED';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_RETRY_PENDING = 'RETRY_PENDING';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELLED = 'CANCELLED';
}
