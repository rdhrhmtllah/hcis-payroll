<?php

namespace App\Models\ESS\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Progress extends Model
{
    use HasFactory;

    protected $table = 'KPI_Master_Progress';

    protected $PrimaryKey = 'Id_Progress';

    public $timestamp = false;

    protected $guarded = [];
}
