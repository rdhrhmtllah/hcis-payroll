<?php

namespace App\Models\ESS\Workflow\Project\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $table = 'KPI_Master_Project';

    protected $primaryKey = 'Id_Project';

    protected $guarded = [];

    public $timestamps = false;
}
