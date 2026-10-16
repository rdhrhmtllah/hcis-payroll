<?php

namespace App\Models\ESS\Workflow\Case\Models;

use App\Models\ESS\Organization\Division\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cases extends Model
{
    use HasFactory;

    protected $table = 'KPI_Cases';

    protected $primaryKey = 'Id_Case';

    public function divisions(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'Division_Id', 'Id_Division');
    }
}
