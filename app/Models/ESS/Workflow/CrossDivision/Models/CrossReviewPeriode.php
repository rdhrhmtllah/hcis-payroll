<?php

namespace App\Models\ESS\Workflow\CrossDivision\Models;

use App\Models\ESS\Employee\Models\User;
use App\Models\ESS\Organization\Division\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossReviewPeriode extends Model
{
    use HasFactory;

    protected $table = 'KPI_Master_Cross_Periode';

    protected $primaryKey = 'Id_Periode';

    protected $guarded = [];

    public $timestamps = false;

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'Division_Id', 'Id_Division');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'User_Id', 'Id_Users');
    }
}
