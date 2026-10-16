<?php

namespace App\Models\ESS\Workflow\Evaluation\Models;

use App\Models\ESS\Workflow\CrossDivision\Models\CategoryCross;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationCross extends Model
{
    use HasFactory;

    protected $table = 'KPI_Evaluations_Cross';

    protected $primaryKey = 'Id_Evaluations_Cross';

    protected $guarded = [];

    public function categoryCross(): BelongsTo
    {
        return $this->belongsTo(CategoryCross::class, 'Category_Cross_Id', 'Id_Category_Cross');
    }
}
