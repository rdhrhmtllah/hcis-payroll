<?php

namespace App\Models\KPI\Planning\Period\Models;

use App\Models\ESS\Organization\Division\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterPeriode extends Model
{
    use HasFactory;

    protected $table = 'kpi_master_periode';

    protected $guarded = [];

    public $timestamps = false;

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'Division_Id', 'Id_Division');
    }
}
