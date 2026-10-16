<?php

namespace App\Models\ESS\Workflow\CrossDivision\Models;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossReviewDetails extends Model
{
    use HasFactory;

    protected $table = 'KPI_Cross_Review_Detail';

    protected $guarded = [];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'Employee_Id', 'Id_Users');
    }
}
