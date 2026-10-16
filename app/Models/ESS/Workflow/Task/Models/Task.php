<?php

namespace App\Models\ESS\Workflow\Task\Models;

use App\Models\ESS\Employee\Models\User;
use App\Models\ESS\Organization\Division\Models\Division;
use App\Models\ESS\Workflow\Models\Progress;
use App\Models\ESS\Workflow\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'KPI_Task_Manager';

    protected $primaryKey = 'Id_Task';

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'Project_Id', 'Id_Project');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'User_Id', 'Id_Users');
    }

    public function progress(): BelongsTo
    {
        return $this->belongsTo(Progress::class, 'Progress_Id', 'Id_Progress');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'Division_Id', 'Id_Division');
    }
}
