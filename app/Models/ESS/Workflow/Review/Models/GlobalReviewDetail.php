<?php

namespace App\Models\ESS\Workflow\Review\Models;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalReviewDetail extends Model
{
    use HasFactory;

    protected $table = 'KPI_Global_Review_Detail';
    // protected $primaryKey = 'Id_Global_Review';

    protected $fillable = [
        'No_Review',
        // 'Date',
        // 'Time',
        'Score',
        'Evaluation_Id',
        'Assesor_Id',
        // 'Employee_Id',
        // 'Tanggal_Mulai',
        // 'Tanggal_Akhir'
    ];

    public $timestamps = false;

    public function userEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'Id_Users', 'Employee_Id');
    }
}
